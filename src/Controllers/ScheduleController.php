<?php

declare(strict_types=1);

namespace HuitSchedule\Controllers;

use HuitSchedule\Core\Flash;
use HuitSchedule\Core\Response;
use HuitSchedule\Core\View;
use HuitSchedule\Repositories\AvailabilityRepository;
use HuitSchedule\Repositories\BranchRepository;
use HuitSchedule\Repositories\ScheduleRepository;
use HuitSchedule\Repositories\TeacherRepository;
use HuitSchedule\Services\ConflictDetectorService;
use HuitSchedule\Services\ScheduleOptimizerService;

final class ScheduleController
{
    private ScheduleRepository $scheduleRepo;
    private BranchRepository $branchRepo;
    private TeacherRepository $teacherRepo;
    private AvailabilityRepository $availRepo;
    private ConflictDetectorService $conflictDetector;
    private ScheduleOptimizerService $optimizer;

    public function __construct()
    {
        $this->scheduleRepo     = new ScheduleRepository();
        $this->branchRepo       = new BranchRepository();
        $this->teacherRepo      = new TeacherRepository();
        $this->availRepo        = new AvailabilityRepository();
        $this->conflictDetector = new ConflictDetectorService($this->scheduleRepo);
        $this->optimizer        = new ScheduleOptimizerService();
    }

    public function index(): void
    {
        $filters = $this->extractFilterQuery();
        $statusFilter = (string) ($_GET['status'] ?? '');

        $filteredSchedules = $this->scheduleRepo->findAll($filters);
        $globalSchedules   = $filters === [] ? $filteredSchedules : $this->scheduleRepo->findAll();
        $travelMatrix      = $this->branchRepo->getTravelMatrix();
        $analysis          = $this->optimizer->analyzeAll($globalSchedules, $travelMatrix);
        $flagsMap          = $analysis['schedule_flags'];

        if ($statusFilter === 'alerts') {
            $filteredSchedules = array_values(array_filter(
                $filteredSchedules,
                static fn(array $s): bool => !empty($flagsMap[(int) $s['id']])
            ));
        }

        $perPage = 12;
        $totalItems = count($filteredSchedules);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $page = max(1, min($totalPages, (int) ($_GET['page'] ?? 1)));
        $pagedSchedules = array_slice($filteredSchedules, ($page - 1) * $perPage, $perPage);

        View::render('schedules/index', [
            'pageTitle'     => 'Danh sách Lịch Giảng dạy',
            'activeNav'     => 'schedules',
            'schedules'     => $pagedSchedules,
            'totalItems'    => $totalItems,
            'scheduleFlags' => $flagsMap,
            'branches'      => $this->branchRepo->all(),
            'teachers'      => $this->teacherRepo->all('teacher'),
            'filters'       => array_merge($filters, ['status' => $statusFilter]),
            'pagination'    => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
                'base_path'   => '/schedules',
                'query'       => array_filter(array_merge($filters, ['status' => $statusFilter]), static fn($v): bool => $v !== '' && $v !== null),
            ],
        ]);
    }

    public function exportCsv(): void
    {
        $filters = $this->extractFilterQuery();
        $statusFilter = (string) ($_GET['status'] ?? '');

        $schedules       = $this->scheduleRepo->findAll($filters);
        $globalSchedules = $filters === [] ? $schedules : $this->scheduleRepo->findAll();
        $travelMatrix    = $this->branchRepo->getTravelMatrix();
        $analysis        = $this->optimizer->analyzeAll($globalSchedules, $travelMatrix);
        $flagsMap        = $analysis['schedule_flags'];

        if ($statusFilter === 'alerts') {
            $schedules = array_values(array_filter(
                $schedules,
                static fn(array $s): bool => !empty($flagsMap[(int) $s['id']])
            ));
        }

        $headers = [
            'ID', 'Thứ', 'Ca / Tiết', 'Khung giờ', 'Môn học', 'Giảng viên',
            'Mã cơ sở', 'Tên cơ sở', 'Phòng học', 'Từ ngày', 'Đến ngày', 'Trạng thái tối ưu', 'Ghi chú',
        ];

        $rows = [];
        foreach ($schedules as $s) {
            $flags = $flagsMap[(int) $s['id']] ?? [];
            $rows[] = [
                (int) $s['id'],
                day_label((int) $s['day_of_week']),
                (string) $s['shift_or_periods'],
                period_time_range((int) $s['start_period'], (int) $s['end_period']),
                (string) $s['subject_name'],
                (string) $s['teacher_name'],
                (string) $s['branch_code'],
                (string) $s['branch_name'],
                (string) $s['room'],
                (string) $s['start_date'],
                (string) $s['end_date'],
                $flags === [] ? 'Tối ưu' : implode(', ', $flags),
                (string) ($s['notes'] ?? ''),
            ];
        }

        Response::csvDownload($headers, $rows, 'huit-schedules-' . date('Ymd-His') . '.csv');
    }

    /**
     * One-Click Auto-Reschedule directly from Dashboard Optimization Alert Center.
     */
    public function autoFix(string $id): void
    {
        $scheduleId = (int) $id;
        $existing   = $this->scheduleRepo->findById($scheduleId);
        if ($existing === null) {
            Flash::error('Không tìm thấy lịch dạy cần tối ưu.');
            Response::redirect('/dashboard');
        }

        $targetDay   = (int) ($_POST['day_of_week'] ?? 0);
        $targetStart = (int) ($_POST['start_period'] ?? 0);
        $targetEnd   = (int) ($_POST['end_period'] ?? 0);

        $room = (string) $existing['room'];
        $branchId = (int) $existing['branch_id'];
        $branchCode = (string) ($existing['branch_code'] ?? '');

        // Auto-pick an available room at the same campus if current room is occupied at the new slot
        $occupied = $this->scheduleRepo->findOccupiedRoomsBySlot(
            $branchId,
            $targetDay,
            $targetStart,
            $targetEnd,
            (string) $existing['start_date'],
            (string) $existing['end_date'],
            $scheduleId
        );

        $roomSwitched = false;
        if (isset($occupied[strtoupper(trim($room))])) {
            $knownRooms = $this->scheduleRepo->getKnownRoomsByBranch($branchId, $branchCode);
            foreach ($knownRooms as $candidateRoom) {
                if (!isset($occupied[$candidateRoom])) {
                    $room = $candidateRoom;
                    $roomSwitched = true;
                    break;
                }
            }
        }

        $payload = [
            'teacher_id'       => (int) $existing['teacher_id'],
            'branch_id'        => $branchId,
            'subject_name'     => (string) $existing['subject_name'],
            'room'             => $room,
            'day_of_week'      => $targetDay,
            'shift_or_periods' => format_shift_label($targetStart, $targetEnd),
            'start_period'     => $targetStart,
            'end_period'       => $targetEnd,
            'start_date'       => (string) $existing['start_date'],
            'end_date'         => (string) $existing['end_date'],
            'notes'            => (string) ($existing['notes'] ?? ''),
        ];

        $result = $this->conflictDetector->updateScheduleAtomic($scheduleId, $payload);
        if (!$result['ok']) {
            foreach ($result['errors'] ?? ['Không thể tự động dời lịch do xung đột mới phát sinh.'] as $err) {
                Flash::error($err);
            }
            Response::redirect('/dashboard');
        }

        $msg = sprintf(
            'Đã tối ưu tự động môn "%s" sang %s - %s%s.',
            $existing['subject_name'],
            day_label($targetDay),
            $payload['shift_or_periods'],
            $roomSwitched ? (' (tự động đổi sang phòng trống ' . $room . ')') : ''
        );
        Flash::success($msg);
        Response::redirect('/dashboard');
    }

    public function create(): void
    {
        $defaultStart = date('Y-m-01');
        $defaultEnd   = date('Y-m-t', strtotime('+2 months'));

        $prefill = [
            'id'               => null,
            'teacher_id'       => (int) ($_GET['teacher_id'] ?? Flash::old('teacher_id', 0)),
            'branch_id'        => (int) ($_GET['branch_id'] ?? Flash::old('branch_id', 0)),
            'subject_name'     => (string) ($_GET['subject_name'] ?? Flash::old('subject_name', '')),
            'room'             => (string) ($_GET['room'] ?? Flash::old('room', '')),
            'day_of_week'      => (int) ($_GET['day_of_week'] ?? Flash::old('day_of_week', 2)),
            'start_period'     => (int) ($_GET['start_period'] ?? Flash::old('start_period', 1)),
            'end_period'       => (int) ($_GET['end_period'] ?? Flash::old('end_period', 3)),
            'shift_or_periods' => (string) Flash::old('shift_or_periods', ''),
            'start_date'       => (string) ($_GET['start_date'] ?? Flash::old('start_date', $defaultStart)),
            'end_date'         => (string) ($_GET['end_date'] ?? Flash::old('end_date', $defaultEnd)),
            'notes'            => (string) Flash::old('notes', ''),
        ];

        View::render('schedules/form', [
            'pageTitle' => 'Phân lịch Giảng dạy Thông minh',
            'activeNav' => 'schedule_create',
            'mode'      => 'create',
            'schedule'  => $prefill,
            'branches'  => $this->branchRepo->all(),
            'teachers'  => $this->teacherRepo->all('teacher'),
        ]);
    }

    public function store(): void
    {
        $payload = $this->extractFormPayload();
        $result  = $this->conflictDetector->createScheduleAtomic($payload);

        if (!$result['ok']) {
            Flash::setOld($payload);
            foreach ($result['errors'] ?? ['Không thể lưu lịch dạy.'] as $err) {
                Flash::error($err);
            }
            Response::redirect('/schedules/create');
        }

        Flash::clearOld();
        Flash::success('Đã phân lịch môn "' . $payload['subject_name'] . '" thành công (đã kiểm tra khóa giao dịch MySQL).');
        Response::redirect('/schedules');
    }

    public function edit(string $id): void
    {
        $schedule = $this->scheduleRepo->findById((int) $id);
        if ($schedule === null) {
            Flash::error('Không tìm thấy lịch dạy cần chỉnh sửa.');
            Response::redirect('/schedules');
        }

        foreach (['teacher_id', 'branch_id', 'subject_name', 'room', 'day_of_week', 'start_period', 'end_period', 'shift_or_periods', 'start_date', 'end_date', 'notes'] as $field) {
            $oldVal = Flash::old($field, null);
            if ($oldVal !== null) {
                $schedule[$field] = $oldVal;
            }
        }

        if (!empty($_GET['day_of_week'])) {
            $schedule['day_of_week'] = (int) $_GET['day_of_week'];
        }
        if (!empty($_GET['start_period']) && !empty($_GET['end_period'])) {
            $schedule['start_period'] = (int) $_GET['start_period'];
            $schedule['end_period']   = (int) $_GET['end_period'];
            $schedule['shift_or_periods'] = format_shift_label((int) $schedule['start_period'], (int) $schedule['end_period']);
        }

        View::render('schedules/form', [
            'pageTitle' => 'Chỉnh sửa Lịch Giảng dạy #' . $schedule['id'],
            'activeNav' => 'schedules',
            'mode'      => 'edit',
            'schedule'  => $schedule,
            'branches'  => $this->branchRepo->all(),
            'teachers'  => $this->teacherRepo->all('teacher'),
        ]);
    }

    public function update(string $id): void
    {
        $scheduleId = (int) $id;
        $existing   = $this->scheduleRepo->findById($scheduleId);
        if ($existing === null) {
            Flash::error('Không tìm thấy lịch dạy.');
            Response::redirect('/schedules');
        }

        $payload = $this->extractFormPayload();
        $result  = $this->conflictDetector->updateScheduleAtomic($scheduleId, $payload);

        if (!$result['ok']) {
            Flash::setOld($payload);
            foreach ($result['errors'] ?? ['Không thể cập nhật lịch dạy.'] as $err) {
                Flash::error($err);
            }
            Response::redirect('/schedules/edit/' . $scheduleId);
        }

        Flash::clearOld();
        Flash::success('Đã cập nhật lịch dạy #' . $scheduleId . ' thành công.');
        Response::redirect('/schedules');
    }

    public function destroy(string $id): void
    {
        $scheduleId = (int) $id;
        $existing   = $this->scheduleRepo->findById($scheduleId);
        if ($existing === null) {
            Flash::error('Lịch dạy không tồn tại.');
            Response::redirect('/schedules');
        }

        $this->scheduleRepo->delete($scheduleId);
        Flash::success('Đã xóa lịch dạy môn "' . $existing['subject_name'] . '".');
        Response::redirect('/schedules');
    }

    /**
     * @return array<string, mixed>
     */
    private function extractFilterQuery(): array
    {
        $filters = [];
        if (!empty($_GET['branch_id'])) {
            $filters['branch_id'] = (int) $_GET['branch_id'];
        }
        if (!empty($_GET['teacher_id'])) {
            $filters['teacher_id'] = (int) $_GET['teacher_id'];
        }
        if (!empty($_GET['day_of_week'])) {
            $filters['day_of_week'] = (int) $_GET['day_of_week'];
        }
        if (!empty($_GET['search'])) {
            $filters['search'] = trim((string) $_GET['search']);
        }
        return $filters;
    }

    /**
     * Visual Availability & Click-to-Schedule Matrix (Day 2-7, plus Day 8 if Sunday items exist x 5 Shifts).
     */
    public function matrix(): void
    {
        $mode      = ($_GET['mode'] ?? 'teacher') === 'branch' ? 'branch' : 'teacher';
        $teachers  = $this->teacherRepo->all('teacher');
        $branches  = $this->branchRepo->all();

        $selectedTeacherId = (int) ($_GET['teacher_id'] ?? ($teachers[0]['id'] ?? 0));
        $selectedBranchId  = (int) ($_GET['branch_id'] ?? ($branches[0]['id'] ?? 0));

        $travelMatrix = $this->branchRepo->getTravelMatrix();

        $schedules      = [];
        $availabilities = [];
        $scheduleFlags  = [];
        $optimalKeys    = [];

        if ($mode === 'teacher' && $selectedTeacherId > 0) {
            $schedules      = $this->scheduleRepo->findByTeacher($selectedTeacherId);
            $availabilities = $this->availRepo->findByTeacher($selectedTeacherId);
            $analysis       = $this->optimizer->analyzeAll($schedules, $travelMatrix);
            $scheduleFlags  = $analysis['schedule_flags'];

            $shifts = app_config('shifts', []);
            foreach ($schedules as $s) {
                $day = (int) $s['day_of_week'];
                $bid = (int) $s['branch_id'];
                foreach ($shifts as $shiftNum => $shift) {
                    $sp = $shift['start_period'];
                    $ep = $shift['end_period'];
                    $gapPrev = $sp - (int) $s['end_period'] - 1;
                    $gapNext = (int) $s['start_period'] - $ep - 1;
                    if (($gapPrev >= 0 && $gapPrev <= 1) || ($gapNext >= 0 && $gapNext <= 1)) {
                        $optimalKeys[$day . ':' . $shiftNum] = [
                            'branch_id'   => $bid,
                            'branch_code' => $s['branch_code'],
                        ];
                    }
                }
            }
        } elseif ($mode === 'branch' && $selectedBranchId > 0) {
            $schedules     = $this->scheduleRepo->findAll(['branch_id' => $selectedBranchId]);
            // Analyze globally so cross-campus IMPOSSIBLE_TRAVEL flags remain accurate when filtering one campus
            $analysis      = $this->optimizer->analyzeAll($this->scheduleRepo->findAll(), $travelMatrix);
            $scheduleFlags = $analysis['schedule_flags'];
        }

        $maxDay = 7;
        foreach ($schedules as $s) {
            if ((int) $s['day_of_week'] === 8) {
                $maxDay = 8;
            }
        }
        foreach ($availabilities as $av) {
            if ((int) $av['day_of_week'] === 8) {
                $maxDay = 8;
            }
        }

        $shifts = app_config('shifts', []);
        $grid = [];
        for ($d = 2; $d <= $maxDay; $d++) {
            foreach ($shifts as $shiftNum => $shift) {
                $grid[$d][$shiftNum] = [
                    'schedules'      => [],
                    'availabilities' => [],
                    'optimal'        => $optimalKeys[$d . ':' . $shiftNum] ?? null,
                ];
            }
        }

        foreach ($schedules as $s) {
            $d = (int) $s['day_of_week'];
            if ($d < 2 || $d > $maxDay) {
                continue;
            }
            foreach ($shifts as $shiftNum => $shift) {
                if ((int) $s['start_period'] <= $shift['end_period'] && (int) $s['end_period'] >= $shift['start_period']) {
                    $grid[$d][$shiftNum]['schedules'][] = $s;
                }
            }
        }

        foreach ($availabilities as $av) {
            $d = (int) $av['day_of_week'];
            if ($d < 2 || $d > $maxDay) {
                continue;
            }
            foreach ($shifts as $shiftNum => $shift) {
                if ((int) $av['start_period'] <= $shift['end_period'] && (int) $av['end_period'] >= $shift['start_period']) {
                    $grid[$d][$shiftNum]['availabilities'][] = $av;
                }
            }
        }

        View::render('schedules/matrix', [
            'pageTitle'         => 'Ma trận Tiết trống & Phân lịch Trực quan',
            'activeNav'         => 'matrix',
            'mode'              => $mode,
            'teachers'          => $teachers,
            'branches'          => $branches,
            'selectedTeacherId' => $selectedTeacherId,
            'selectedBranchId'  => $selectedBranchId,
            'shifts'            => $shifts,
            'maxDay'            => $maxDay,
            'grid'              => $grid,
            'scheduleFlags'     => $scheduleFlags,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractFormPayload(): array
    {
        $startPeriod = (int) ($_POST['start_period'] ?? 1);
        $endPeriod   = (int) ($_POST['end_period'] ?? 3);
        $shiftLabel  = trim((string) ($_POST['shift_or_periods'] ?? ''));
        if ($shiftLabel === '') {
            $shiftLabel = format_shift_label($startPeriod, $endPeriod);
        }

        return [
            'teacher_id'       => (int) ($_POST['teacher_id'] ?? 0),
            'branch_id'        => (int) ($_POST['branch_id'] ?? 0),
            'subject_name'     => trim((string) ($_POST['subject_name'] ?? '')),
            'room'             => strtoupper(trim((string) ($_POST['room'] ?? ''))),
            'day_of_week'      => (int) ($_POST['day_of_week'] ?? 2),
            'shift_or_periods' => $shiftLabel,
            'start_period'     => $startPeriod,
            'end_period'       => $endPeriod,
            'start_date'       => trim((string) ($_POST['start_date'] ?? '')),
            'end_date'         => trim((string) ($_POST['end_date'] ?? '')),
            'notes'            => trim((string) ($_POST['notes'] ?? '')),
        ];
    }
}
