<?php

declare(strict_types=1);

namespace HuitSchedule\Controllers;

use DateTimeImmutable;
use HuitSchedule\Core\Auth;
use HuitSchedule\Core\Flash;
use HuitSchedule\Core\Response;
use HuitSchedule\Core\View;
use HuitSchedule\Domain\TimeSlot;
use HuitSchedule\Repositories\AvailabilityRepository;
use HuitSchedule\Repositories\BranchRepository;
use HuitSchedule\Repositories\ScheduleRepository;
use HuitSchedule\Repositories\TeacherRepository;
use HuitSchedule\Services\ICalendarExporterService;
use HuitSchedule\Services\ScheduleOptimizerService;

final class PortalController
{
    private ScheduleRepository $scheduleRepo;
    private AvailabilityRepository $availRepo;
    private BranchRepository $branchRepo;
    private TeacherRepository $teacherRepo;
    private ScheduleOptimizerService $optimizer;

    public function __construct()
    {
        $this->scheduleRepo = new ScheduleRepository();
        $this->availRepo    = new AvailabilityRepository();
        $this->branchRepo   = new BranchRepository();
        $this->teacherRepo  = new TeacherRepository();
        $this->optimizer    = new ScheduleOptimizerService();
    }

    public function index(): void
    {
        $token = isset($_GET['token']) ? trim((string) $_GET['token']) : null;
        $requestedTeacherId = isset($_GET['teacher_id']) ? (int) $_GET['teacher_id'] : null;

        $access = Auth::resolvePortalAccess($token, $requestedTeacherId);
        if ($access === null) {
            if ($token !== null && $token !== '') {
                throw new \RuntimeException('Đường dẫn Portal cá nhân không hợp lệ hoặc token đã được thu hồi.', 403);
            }
            Flash::info('Vui lòng đăng nhập để xem Cổng thông tin Giảng viên.');
            Response::redirect('/login');
        }

        $teacher   = $access['teacher'];
        $readOnly  = $access['read_only'];
        $viaToken  = $access['via_token'];
        $teacherId = (int) $teacher['id'];

        $tab = (string) ($_GET['tab'] ?? 'week');
        if (!in_array($tab, ['week', 'month', 'availability'], true)) {
            $tab = 'week';
        }
        if ($tab === 'availability' && $readOnly) {
            $tab = 'week';
        }

        // Safe Week navigation (guards invalid dates like 2026-99-99)
        $weekParam = (string) ($_GET['week'] ?? date('Y-m-d'));
        $refDate = DateTimeImmutable::createFromFormat('!Y-m-d', $weekParam);
        if (!$refDate || $refDate->format('Y-m-d') !== $weekParam) {
            $refDate = new DateTimeImmutable(date('Y-m-d'));
        }
        $isoDow    = (int) $refDate->format('N'); // 1=Mon..7=Sun
        $weekStart = $refDate->modify('-' . ($isoDow - 1) . ' days');
        $weekEnd   = $weekStart->modify('+6 days');

        // Safe Month navigation (guards invalid months like 2026-99)
        $monthParam = (string) ($_GET['month'] ?? date('Y-m'));
        $monthFirst = DateTimeImmutable::createFromFormat('!Y-m-d', $monthParam . '-01');
        if (!$monthFirst || $monthFirst->format('Y-m') !== $monthParam) {
            $monthParam = date('Y-m');
            $monthFirst = new DateTimeImmutable($monthParam . '-01');
        }
        $monthLast = $monthFirst->modify('last day of this month');

        $allTeacherSchedules = $this->scheduleRepo->findByTeacher($teacherId);
        $travelMatrix        = $this->branchRepo->getTravelMatrix();
        $analysis            = $this->optimizer->analyzeAll($allTeacherSchedules, $travelMatrix);

        // Build Weekly Timetable (days 2..8 mapped to exact date in week)
        $weekDays = [];
        for ($i = 0; $i < 7; $i++) {
            $dObj = $weekStart->modify('+' . $i . ' days');
            $huitDay = $i + 2; // 2=Mon .. 8=Sun
            $dateStr = $dObj->format('Y-m-d');

            $items = [];
            foreach ($allTeacherSchedules as $s) {
                if ((int) $s['day_of_week'] === $huitDay
                    && $dateStr >= (string) $s['start_date']
                    && $dateStr <= (string) $s['end_date']) {
                    $slot = TimeSlot::fromArray($s);
                    $s['time_range'] = $slot->timeRange();
                    $s['flags']      = $analysis['schedule_flags'][(int) $s['id']] ?? [];
                    $items[] = $s;
                }
            }
            usort($items, static fn(array $a, array $b): int => ((int) $a['start_period']) <=> ((int) $b['start_period']));

            $weekDays[] = [
                'huit_day'  => $huitDay,
                'label'     => day_label($huitDay),
                'date'      => $dateStr,
                'display'   => $dObj->format('d/m'),
                'is_today'  => $dateStr === date('Y-m-d'),
                'schedules' => $items,
            ];
        }

        // Build Monthly Calendar Grid (Monday-first 6 rows x 7 cols)
        $firstDow = (int) $monthFirst->format('N'); // 1=Mon..7=Sun
        $gridStart = $monthFirst->modify('-' . ($firstDow - 1) . ' days');
        $lastDow = (int) $monthLast->format('N');
        $gridEnd = $monthLast->modify('+' . (7 - $lastDow) . ' days');

        $monthCells = [];
        $cursor = $gridStart;
        while ($cursor <= $gridEnd) {
            $dateStr = $cursor->format('Y-m-d');
            $huitDay = ((int) $cursor->format('N')) + 1;
            $inMonth = $cursor->format('Y-m') === $monthParam;

            $events = [];
            foreach ($allTeacherSchedules as $s) {
                if ((int) $s['day_of_week'] === $huitDay
                    && $dateStr >= (string) $s['start_date']
                    && $dateStr <= (string) $s['end_date']) {
                    $slot = TimeSlot::fromArray($s);
                    $events[] = [
                        'id'             => (int) $s['id'],
                        'subject_name'   => (string) $s['subject_name'],
                        'branch_name'    => (string) $s['branch_name'],
                        'branch_code'    => (string) $s['branch_code'],
                        'branch_address' => (string) $s['branch_address'],
                        'branch_color'   => (string) $s['branch_color'],
                        'room'           => (string) $s['room'],
                        'shift'          => (string) $s['shift_or_periods'],
                        'time_range'     => $slot->timeRange(),
                        'date'           => $cursor->format('d/m/Y'),
                        'day_label'      => day_label($huitDay),
                        'notes'          => (string) ($s['notes'] ?? ''),
                    ];
                }
            }
            usort($events, static fn(array $a, array $b): int => strcmp($a['time_range'], $b['time_range']));

            $monthCells[] = [
                'date'     => $dateStr,
                'day_num'  => (int) $cursor->format('j'),
                'in_month' => $inMonth,
                'is_today' => $dateStr === date('Y-m-d'),
                'events'   => $events,
            ];
            $cursor = $cursor->modify('+1 day');
        }

        $availabilities = $this->availRepo->findByTeacher($teacherId);
        $branches       = $this->branchRepo->all();
        $totalPeriods   = array_sum(array_map(
            static fn(array $s): int => (int) $s['end_period'] - (int) $s['start_period'] + 1,
            $allTeacherSchedules
        ));

        View::render('portal/index', [
            'pageTitle'      => 'Cổng Giảng viên - ' . $teacher['full_name'],
            'activeNav'      => 'portal',
            'teacher'        => $teacher,
            'readOnly'       => $readOnly,
            'viaToken'       => $viaToken,
            'token'          => $viaToken ? (string) $teacher['portal_token'] : null,
            'tab'            => $tab,
            'weekStart'      => $weekStart,
            'weekEnd'        => $weekEnd,
            'prevWeek'       => $weekStart->modify('-7 days')->format('Y-m-d'),
            'nextWeek'       => $weekStart->modify('+7 days')->format('Y-m-d'),
            'weekDays'       => $weekDays,
            'monthParam'     => $monthParam,
            'monthTitle'     => 'Tháng ' . $monthFirst->format('m/Y'),
            'prevMonth'      => $monthFirst->modify('-1 month')->format('Y-m'),
            'nextMonth'      => $monthFirst->modify('+1 month')->format('Y-m'),
            'monthCells'     => $monthCells,
            'schedules'      => $allTeacherSchedules,
            'totalPeriods'   => $totalPeriods,
            'commuteDays'    => $analysis['commute_days'],
            'alerts'         => $analysis['alerts'],
            'availabilities' => $availabilities,
            'branches'       => $branches,
            'allTeachers'    => Auth::isAdmin() ? $this->teacherRepo->all('teacher') : [],
        ]);
    }

    public function storeAvailability(): void
    {
        if (!Auth::check()) {
            Response::redirect('/login');
        }

        $teacherId = Auth::isAdmin() && !empty($_POST['teacher_id'])
            ? (int) $_POST['teacher_id']
            : (int) Auth::id();

        $redirectUrl = '/portal?tab=availability' . (Auth::isAdmin() ? ('&teacher_id=' . $teacherId) : '');

        $dayOfWeek   = (int) ($_POST['day_of_week'] ?? 2);
        $startPeriod = (int) ($_POST['start_period'] ?? 1);
        $endPeriod   = (int) ($_POST['end_period'] ?? 3);
        $prefBranch  = !empty($_POST['preferred_branch_id']) ? (int) $_POST['preferred_branch_id'] : null;

        try {
            $slot = new TimeSlot($dayOfWeek, $startPeriod, $endPeriod);
        } catch (\InvalidArgumentException $e) {
            Flash::error($e->getMessage());
            Response::redirect($redirectUrl);
        }

        $this->availRepo->upsert([
            'teacher_id'          => $teacherId,
            'day_of_week'         => $dayOfWeek,
            'shift_or_periods'    => $slot->shiftLabel(),
            'start_period'        => $startPeriod,
            'end_period'          => $endPeriod,
            'preferred_branch_id' => $prefBranch,
        ]);

        Flash::success('Đã đăng ký khung tiết trống cá nhân thành công.');
        Response::redirect($redirectUrl);
    }

    public function deleteAvailability(string $availId): void
    {
        if (!Auth::check()) {
            Response::redirect('/login');
        }
        $teacherId = Auth::isAdmin() ? null : Auth::id();
        $redirectTeacherId = Auth::isAdmin() && !empty($_POST['teacher_id']) ? (int) $_POST['teacher_id'] : 0;
        $this->availRepo->delete((int) $availId, $teacherId);
        Flash::success('Đã xóa khung tiết trống.');
        $redirectUrl = '/portal?tab=availability' . ($redirectTeacherId > 0 ? ('&teacher_id=' . $redirectTeacherId) : '');
        Response::redirect($redirectUrl);
    }

    public function exportIcs(): void
    {
        $token = isset($_GET['token']) ? trim((string) $_GET['token']) : null;
        $requestedTeacherId = isset($_GET['teacher_id']) ? (int) $_GET['teacher_id'] : null;

        $access = Auth::resolvePortalAccess($token, $requestedTeacherId);
        if ($access === null) {
            throw new \RuntimeException('Không có quyền xuất lịch .ics. Vui lòng đăng nhập hoặc dùng link Portal hợp lệ.', 403);
        }

        $teacher   = $access['teacher'];
        $schedules = $this->scheduleRepo->findByTeacher((int) $teacher['id']);

        $exporter = new ICalendarExporterService();
        $ics      = $exporter->export($teacher, $schedules);
        $slug     = preg_replace('/[^a-z0-9]+/', '-', strtolower(\HuitSchedule\Domain\ExpertiseMatcher::removeVietnameseAccents((string) $teacher['full_name'])));

        Response::icsDownload($ics, 'huit-schedule-' . trim((string) $slug, '-') . '.ics');
    }
}
