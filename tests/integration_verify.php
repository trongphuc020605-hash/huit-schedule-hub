<?php

declare(strict_types=1);

/**
 * HUIT Schedule Hub — MySQL Integration Verification Suite (10 Real-World Scenarios)
 * Wrapped inside an outer transaction that ALWAYS rolls back so huit_db remains clean.
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

use HuitSchedule\Core\Auth;
use HuitSchedule\Core\Database;
use HuitSchedule\Repositories\AvailabilityRepository;
use HuitSchedule\Repositories\BranchRepository;
use HuitSchedule\Repositories\ScheduleRepository;
use HuitSchedule\Repositories\TeacherRepository;
use HuitSchedule\Services\ConflictDetectorService;
use HuitSchedule\Services\ICalendarExporterService;
use HuitSchedule\Services\ScheduleOptimizerService;
use HuitSchedule\Services\TeacherRecommendationService;

$passed = 0;
$failed = 0;

function verify(bool $cond, string $label): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$label}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$label}\n";
    }
}

echo "=== HUIT Schedule Hub: MySQL Integration Verification (10 Scenarios) ===\n\n";

$pdo = Database::getConnection();
$pdo->beginTransaction();

try {
    $branchRepo   = new BranchRepository($pdo);
    $teacherRepo  = new TeacherRepository($pdo);
    $scheduleRepo = new ScheduleRepository($pdo);
    $availRepo    = new AvailabilityRepository($pdo);
    $detector     = new ConflictDetectorService($scheduleRepo);
    $optimizer    = new ScheduleOptimizerService();
    $recommender  = new TeacherRecommendationService();
    $icsExporter  = new ICalendarExporterService();

    $branches     = $branchRepo->all();
    $travelMatrix = $branchRepo->getTravelMatrix();
    $branchByCode = [];
    foreach ($branches as $b) {
        $branchByCode[$b['code']] = $b;
    }

    $cs1 = (int) $branchByCode['CS1']['id'];
    $cs4 = (int) $branchByCode['CS4']['id'];

    // Create 2 isolated test teachers inside this rolled-back transaction
    $testTeacher1 = $teacherRepo->create([
        'full_name'            => 'TS. Kiểm Thử PHP',
        'email'                => 'test.php.verify@huit.edu.vn',
        'phone'                => '0909000111',
        'expertise'            => 'Phát triển phần mềm mã nguồn mở, PHP, Laravel, MySQL',
        'role'                 => 'teacher',
        'max_periods_per_week' => 18,
    ]);
    $testTeacher2 = $teacherRepo->create([
        'full_name'            => 'ThS. Kiểm Thử Bận',
        'email'                => 'test.busy.verify@huit.edu.vn',
        'phone'                => '0909000222',
        'expertise'            => 'Phát triển phần mềm mã nguồn mở, PHP, Web',
        'role'                 => 'teacher',
        'max_periods_per_week' => 18,
    ]);

    $sDate = '2026-11-01';
    $eDate = '2026-12-31';

    // Base schedule for testTeacher1: Mon Ca 1 at CS1 room Z901
    $baseRes = $detector->createScheduleAtomic([
        'teacher_id'       => $testTeacher1,
        'branch_id'        => $cs1,
        'subject_name'     => 'Lập trình PHP Cơ bản',
        'room'             => 'Z901',
        'day_of_week'      => 2,
        'shift_or_periods' => 'Ca 1 (Tiết 1-3)',
        'start_period'     => 1,
        'end_period'       => 3,
        'start_date'       => $sDate,
        'end_date'         => $eDate,
    ]);
    $baseId = (int) ($baseRes['id'] ?? 0);

    // Scenario 1: Block Teacher double-booking
    $dupTeacher = $detector->createScheduleAtomic([
        'teacher_id'       => $testTeacher1,
        'branch_id'        => $cs1,
        'subject_name'     => 'Môn trùng GV',
        'room'             => 'Z902',
        'day_of_week'      => 2,
        'shift_or_periods' => 'Tiết 2-4',
        'start_period'     => 2,
        'end_period'       => 4,
        'start_date'       => $sDate,
        'end_date'         => $eDate,
    ]);
    verify(!$dupTeacher['ok'] && !empty($dupTeacher['conflicts']['teacher_conflicts']),
        '(1) Chặn trùng lịch giảng viên (atomic transaction + FOR UPDATE)');

    // Scenario 2: Block Room double-booking at same campus
    $dupRoom = $detector->createScheduleAtomic([
        'teacher_id'       => $testTeacher2,
        'branch_id'        => $cs1,
        'subject_name'     => 'Môn trùng phòng',
        'room'             => 'Z901',
        'day_of_week'      => 2,
        'shift_or_periods' => 'Ca 1 (Tiết 1-3)',
        'start_period'     => 1,
        'end_period'       => 3,
        'start_date'       => $sDate,
        'end_date'         => $eDate,
    ]);
    verify(!$dupRoom['ok'] && !empty($dupRoom['conflicts']['room_conflicts']),
        '(2) Chặn trùng phòng học tại cùng cơ sở');

    // Scenario 3: Allow updating own schedule without self-conflict
    $selfUpdate = $detector->updateScheduleAtomic($baseId, [
        'teacher_id'       => $testTeacher1,
        'branch_id'        => $cs1,
        'subject_name'     => 'Lập trình PHP Nâng cao (Updated)',
        'room'             => 'Z901',
        'day_of_week'      => 2,
        'shift_or_periods' => 'Ca 1 (Tiết 1-3)',
        'start_period'     => 1,
        'end_period'       => 3,
        'start_date'       => $sDate,
        'end_date'         => $eDate,
    ]);
    verify($selfUpdate['ok'] === true,
        '(3) Cho phép cập nhật chính bản ghi không tự xung đột');

    // Scenario 4: Block deleting branch/teacher that still has schedules (both app guard & DB FK)
    $branchHasSched  = $branchRepo->countSchedules($cs1) > 0;
    $teacherHasSched = $teacherRepo->countSchedules($testTeacher1) > 0;
    $fkBlocked = false;
    try {
        $teacherRepo->delete($testTeacher1);
    } catch (\PDOException $ex) {
        $fkBlocked = true;
    }
    verify($branchHasSched && $teacherHasSched && $fkBlocked,
        '(4) Chặn xóa cơ sở/giảng viên đang có lịch (ứng dụng + FK ON DELETE RESTRICT)');

    // Scenario 5: Detect IMPOSSIBLE_TRAVEL between Tan Phu (CS1) & Q12 (CS4)
    $impSchedRes = $detector->createScheduleAtomic([
        'teacher_id'       => $testTeacher1,
        'branch_id'        => $cs4,
        'subject_name'     => 'Lập trình Web Q12',
        'room'             => 'Q901',
        'day_of_week'      => 2,
        'shift_or_periods' => 'Ca 2 (Tiết 4-6)',
        'start_period'     => 4,
        'end_period'       => 6,
        'start_date'       => $sDate,
        'end_date'         => $eDate,
    ]);
    $t1Day = $scheduleRepo->findByTeacher($testTeacher1);
    $impAnalysis = $optimizer->analyzeAll($t1Day, $travelMatrix);
    verify($impAnalysis['counts']['impossible_travel'] >= 1,
        '(5) Phát hiện đúng IMPOSSIBLE_TRAVEL giữa CS1 (Tân Phú) & CS4 (Quận 12)');
    // Clean up that impossible slot before next scenarios
    $scheduleRepo->delete((int) $impSchedRes['id']);

    // Scenario 6: Detect DEAD_GAP between Ca 1 (T1-3) and Ca 4 (T10-12)
    $gapSchedRes = $detector->createScheduleAtomic([
        'teacher_id'       => $testTeacher1,
        'branch_id'        => $cs1,
        'subject_name'     => 'Thực hành Laravel chiều',
        'room'             => 'Z905',
        'day_of_week'      => 2,
        'shift_or_periods' => 'Ca 4 (Tiết 10-12)',
        'start_period'     => 10,
        'end_period'       => 12,
        'start_date'       => $sDate,
        'end_date'         => $eDate,
    ]);
    $t1DayGap = $scheduleRepo->findByTeacher($testTeacher1);
    $gapAnalysis = $optimizer->analyzeAll($t1DayGap, $travelMatrix);
    verify($gapAnalysis['counts']['dead_gap'] >= 1 && $gapAnalysis['total_wasted_periods'] >= 6,
        '(6) Phát hiện đúng DEAD_GAP (6 tiết trống chết) giữa Ca 1 và Ca 4');
    $scheduleRepo->delete((int) $gapSchedRes['id']);

    // Scenario 7: /api/suggest-teachers ranks matching + adjacent same-branch teacher #1 and excludes busy teacher
    // Register availability for testTeacher1 on Mon Ca 2 at CS1 (adjacent to Mon Ca 1 CS1!)
    $availRepo->upsert([
        'teacher_id'          => $testTeacher1,
        'day_of_week'         => 2,
        'shift_or_periods'    => 'Ca 2 (Tiết 4-6)',
        'start_period'        => 4,
        'end_period'          => 6,
        'preferred_branch_id' => $cs1,
    ]);
    // Make testTeacher2 busy on Mon Ca 2
    $detector->createScheduleAtomic([
        'teacher_id'       => $testTeacher2,
        'branch_id'        => $cs4,
        'subject_name'     => 'Môn giữ chỗ Ca 2',
        'room'             => 'Q909',
        'day_of_week'      => 2,
        'shift_or_periods' => 'Ca 2 (Tiết 4-6)',
        'start_period'     => 4,
        'end_period'       => 6,
        'start_date'       => $sDate,
        'end_date'         => $eDate,
    ]);

    $rec = $recommender->suggestForSlot([
        'subject'      => 'Phát triển phần mềm mã nguồn mở PHP',
        'branch_id'    => $cs1,
        'day_of_week'  => 2,
        'start_period' => 4,
        'end_period'   => 6,
        'start_date'   => $sDate,
        'end_date'     => $eDate,
    ]);
    $top1Id = (int) ($rec['recommended'][0]['teacher_id'] ?? 0);
    $unavailIds = array_column($rec['unavailable'], 'teacher_id');
    verify($top1Id === $testTeacher1 && in_array($testTeacher2, $unavailIds, true),
        '(7) Engine suggest-teachers xếp hạng Top 1 đúng GV chuyên môn + liền ca CS1 và loại GV bận');

    // Scenario 8: /api/suggest-slots returns Top 5 non-conflicting optimal slots with Mon Ca 2 CS1 ranked #1
    $slots = $optimizer->suggestOptimalSlots($testTeacher1, $cs1, 3, $sDate, $eDate, null, 5);
    $topSlot = $slots[0] ?? null;
    $noOverlap = true;
    foreach ($slots as $sl) {
        if ($sl['day_of_week'] === 2 && $sl['start_period'] === 1) {
            $noOverlap = false;
        }
    }
    verify(count($slots) === 5 && $noOverlap && $topSlot !== null && $topSlot['day_of_week'] === 2 && $topSlot['start_period'] === 4,
        '(8) Engine suggest-slots trả về Top 5 ô tối ưu không trùng lịch (Top 1 là Thứ 2 Ca 2 liền ca CS1)');

    // Scenario 9: RBAC Admin vs Teacher + Personal Portal Token lookup
    $adminOk   = Auth::attempt('admin@huit.edu.vn', 'Admin@123') && Auth::isAdmin();
    Auth::logout();
    $teacherOk = Auth::attempt('giangvien@huit.edu.vn', 'Teacher@123') && Auth::isTeacher() && !Auth::isAdmin();
    $demoUser  = Auth::user();
    Auth::logout();
    $tokenAccess = Auth::resolvePortalAccess((string) ($demoUser['portal_token'] ?? ''));
    verify($adminOk && $teacherOk && $tokenAccess !== null && $tokenAccess['read_only'] === true && $tokenAccess['via_token'] === true,
        '(9) Phân quyền Admin vs Teacher & truy cập Portal cá nhân qua portal_token (read-only)');

    // Scenario 10: Weekly/Monthly data & RFC 5545 .ics export for demo teacher
    $demoTeacher   = $teacherRepo->findByEmail('giangvien@huit.edu.vn');
    $demoSchedules = $scheduleRepo->findByTeacher((int) $demoTeacher['id']);
    $ics           = $icsExporter->export($demoTeacher, $demoSchedules);
    $hasMonthlyMatch = false;
    $todayMonthStart = new DateTimeImmutable(date('Y-m-01'));
    for ($d = 0; $d < 28; $d++) {
        $dt = $todayMonthStart->modify('+' . $d . ' days');
        $huitDow = ((int) $dt->format('N')) + 1;
        $ds = $dt->format('Y-m-d');
        foreach ($demoSchedules as $sc) {
            if ((int) $sc['day_of_week'] === $huitDow && $ds >= $sc['start_date'] && $ds <= $sc['end_date']) {
                $hasMonthlyMatch = true;
                break 2;
            }
        }
    }
    verify(count($demoSchedules) >= 3 && $hasMonthlyMatch && str_contains($ics, 'BEGIN:VCALENDAR') && str_contains($ics, 'RRULE:FREQ=WEEKLY'),
        '(10) Dữ liệu Lịch Tuần / Lịch Tháng khớp học kỳ & xuất file .ics RFC 5545 hợp lệ');

} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo "\n--------------------------------------------\n";
echo "INTEGRATION RESULT: {$passed} passed, {$failed} failed (DB rolled back cleanly).\n";
exit($failed > 0 ? 1 : 0);
