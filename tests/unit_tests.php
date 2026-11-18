<?php

declare(strict_types=1);

/**
 * HUIT Schedule Hub — Standalone Pure PHP Unit Test Suite (No DB Required)
 * Runs cleanly both locally and in GitHub Actions CI.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use HuitSchedule\Domain\ExpertiseMatcher;
use HuitSchedule\Domain\TimeSlot;
use HuitSchedule\Services\ICalendarExporterService;
use HuitSchedule\Services\ScheduleOptimizerService;
use HuitSchedule\Services\TeacherRecommendationService;

$passed = 0;
$failed = 0;

function assertTrue(bool $cond, string $label): void
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

function assertEquals(mixed $expected, mixed $actual, string $label): void
{
    $ok = ($expected === $actual);
    $detail = $ok ? $label : sprintf('%s (expected %s, got %s)', $label, var_export($expected, true), var_export($actual, true));
    assertTrue($ok, $detail);
}

echo "=== HUIT Schedule Hub: Unit Tests ===\n\n";

// --------------------------------------------------------------------------
// 1. TimeSlot Domain Value Object Tests
// --------------------------------------------------------------------------
echo "[Suite 1] TimeSlot Overlap & Break-Minute Algebra\n";

$slotMonC1 = new TimeSlot(2, 1, 3, '2026-09-01', '2026-12-31');
$slotMonC2 = new TimeSlot(2, 4, 6, '2026-09-01', '2026-12-31');
$slotMonC3 = new TimeSlot(2, 7, 9, '2026-09-01', '2026-12-31');
$slotMonC4 = new TimeSlot(2, 10, 12, '2026-09-01', '2026-12-31');
$slotMonOverlap = new TimeSlot(2, 3, 5, '2026-10-01', '2026-11-30');
$slotMonDisjointDate = new TimeSlot(2, 1, 3, '2027-01-01', '2027-05-01');

assertTrue($slotMonC1->overlaps($slotMonOverlap), 'Detects partial period + date overlap (T1-3 vs T3-5)');
assertTrue(!$slotMonC1->overlaps($slotMonC2), 'Non-overlapping adjacent shifts (Ca 1 vs Ca 2)');
assertTrue(!$slotMonC1->overlaps($slotMonDisjointDate), 'Same periods on disjoint date ranges do NOT overlap');
assertEquals(10, $slotMonC1->breakMinutes($slotMonC2), 'Break between Ca 1 (09:30) and Ca 2 (09:40) is exactly 10 minutes');
assertEquals(20, $slotMonC2->breakMinutes($slotMonC3), 'Lunch break between Ca 2 (12:10) and Ca 3 (12:30) is exactly 20 minutes');
assertEquals(6, $slotMonC1->gapPeriods($slotMonC4), 'Gap periods between Ca 1 (T1-3) and Ca 4 (T10-12) is 6 periods');

// --------------------------------------------------------------------------
// 2. ExpertiseMatcher Vietnamese NLP-lite & Taxonomy Tests
// --------------------------------------------------------------------------
echo "\n[Suite 2] ExpertiseMatcher (Vietnamese Normalization + Taxonomy + Jaccard)\n";

$matcher = new ExpertiseMatcher();
assertEquals('Phat trien phan mem ma nguon mo', ExpertiseMatcher::removeVietnameseAccents('Phát triển phần mềm mã nguồn mở'), 'Strips Vietnamese accents accurately including đ/Đ');

$direct = $matcher->match('Thực hành Phát triển phần mềm mã nguồn mở (PHP)', 'Phát triển phần mềm mã nguồn mở, PHP, MySQL, Git');
assertEquals(40, $direct['score'], 'Direct keyword/phrase match yields 40 points');
assertEquals('direct', $direct['level'], 'Direct match level is "direct"');

$cluster = $matcher->match('Hệ quản trị Cơ sở dữ liệu Oracle nâng cao', 'MySQL, PostgreSQL, NoSQL, MongoDB, Redis');
assertEquals(25, $cluster['score'], 'Same IT cluster (Database) without direct keyword yields 25 points');
assertEquals('cluster', $cluster['level'], 'Cluster match level is "cluster"');

$none = $matcher->match('Lập trình ReactJS & UI/UX', 'An toàn thông tin, Cisco, Linux, Firewall');
assertEquals(0, $none['score'], 'Unrelated IT domains yield 0 expertise points');

// --------------------------------------------------------------------------
// 3. ScheduleOptimizerService Tests
// --------------------------------------------------------------------------
echo "\n[Suite 3] ScheduleOptimizerService (Travel Matrix, Dead Gap, Health Score)\n";

$optimizer = new ScheduleOptimizerService();
$travelMatrix = [
    1 => [1 => ['travel_minutes' => 0, 'distance_km' => 0.0], 2 => ['travel_minutes' => 15, 'distance_km' => 2.6], 3 => ['travel_minutes' => 6, 'distance_km' => 0.8], 4 => ['travel_minutes' => 35, 'distance_km' => 9.5]],
    2 => [1 => ['travel_minutes' => 15, 'distance_km' => 2.6], 2 => ['travel_minutes' => 0, 'distance_km' => 0.0], 3 => ['travel_minutes' => 14, 'distance_km' => 2.3], 4 => ['travel_minutes' => 38, 'distance_km' => 10.4]],
    3 => [1 => ['travel_minutes' => 6, 'distance_km' => 0.8], 2 => ['travel_minutes' => 14, 'distance_km' => 2.3], 3 => ['travel_minutes' => 0, 'distance_km' => 0.0], 4 => ['travel_minutes' => 40, 'distance_km' => 11.0]],
    4 => [1 => ['travel_minutes' => 35, 'distance_km' => 9.5], 2 => ['travel_minutes' => 38, 'distance_km' => 10.4], 3 => ['travel_minutes' => 40, 'distance_km' => 11.0], 4 => ['travel_minutes' => 0, 'distance_km' => 0.0]],
];

// IMPOSSIBLE_TRAVEL: CS1 Ca 1 -> CS4 Ca 2 (10m break vs 35m travel)
$impRes = $optimizer->analyzeTeacherDay([
    ['id' => 1, 'teacher_id' => 10, 'teacher_name' => 'GV A', 'branch_id' => 1, 'branch_code' => 'CS1', 'day_of_week' => 2, 'start_period' => 1, 'end_period' => 3, 'start_date' => '2026-09-01', 'end_date' => '2026-12-31'],
    ['id' => 2, 'teacher_id' => 10, 'teacher_name' => 'GV A', 'branch_id' => 4, 'branch_code' => 'CS4', 'day_of_week' => 2, 'start_period' => 4, 'end_period' => 6, 'start_date' => '2026-09-01', 'end_date' => '2026-12-31'],
], $travelMatrix);
assertTrue(count($impRes['alerts']) === 1 && $impRes['alerts'][0]['type'] === 'IMPOSSIBLE_TRAVEL', 'Detects IMPOSSIBLE_TRAVEL when travel (35m) > break (10m)');

// TIGHT_TRAVEL: CS1 Ca 2 (ends 12:10) -> CS2 Ca 3 (starts 12:30 -> 20m break vs 15m travel -> margin 5m < 15m)
$tightRes = $optimizer->analyzeTeacherDay([
    ['id' => 3, 'teacher_id' => 10, 'teacher_name' => 'GV A', 'branch_id' => 1, 'branch_code' => 'CS1', 'day_of_week' => 3, 'start_period' => 4, 'end_period' => 6, 'start_date' => '2026-09-01', 'end_date' => '2026-12-31'],
    ['id' => 4, 'teacher_id' => 10, 'teacher_name' => 'GV A', 'branch_id' => 2, 'branch_code' => 'CS2', 'day_of_week' => 3, 'start_period' => 7, 'end_period' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-12-31'],
], $travelMatrix);
assertTrue(count($tightRes['alerts']) === 1 && $tightRes['alerts'][0]['type'] === 'TIGHT_TRAVEL', 'Detects TIGHT_TRAVEL when break - travel (20-15=5m) < 15m');

// DEAD_GAP: CS1 Ca 1 -> CS1 Ca 4 (gap = 6 >= 3)
$gapRes = $optimizer->analyzeTeacherDay([
    ['id' => 5, 'teacher_id' => 10, 'teacher_name' => 'GV A', 'branch_id' => 1, 'branch_code' => 'CS1', 'day_of_week' => 4, 'start_period' => 1, 'end_period' => 3, 'start_date' => '2026-09-01', 'end_date' => '2026-12-31'],
    ['id' => 6, 'teacher_id' => 10, 'teacher_name' => 'GV A', 'branch_id' => 1, 'branch_code' => 'CS1', 'day_of_week' => 4, 'start_period' => 10, 'end_period' => 12, 'start_date' => '2026-09-01', 'end_date' => '2026-12-31'],
], $travelMatrix);
assertTrue(count($gapRes['alerts']) === 1 && $gapRes['alerts'][0]['type'] === 'DEAD_GAP', 'Detects DEAD_GAP when gapPeriods >= 3');
assertEquals(6, $gapRes['dead_gap_day'], 'DeadGapDay equals 6 periods for Ca 1 + Ca 4');

// HealthScore exact formula: 100 - 15*2 - 5*1 - 3*2 - 2*2.5 = 100 - 30 - 5 - 6 - 5 = 54
assertEquals(54, $optimizer->computeHealthScore(2, 1, 2, 2.5), 'HealthScore matches exact mathematical formula (54%)');
assertEquals(0, $optimizer->computeHealthScore(10, 5, 5, 10.0), 'HealthScore clamps cleanly at lower bound 0');

// --------------------------------------------------------------------------
// 4. TeacherRecommendationService Ranking & Hard Exclusion Tests
// --------------------------------------------------------------------------
echo "\n[Suite 4] TeacherRecommendationService (0-100 Scoring & Hard Busy Exclusion)\n";

$recService = new TeacherRecommendationService($matcher, $optimizer);
$mockTeachers = [
    ['id' => 1, 'full_name' => 'GV Best (PHP + Liền ca CS1 + Rảnh)', 'email' => 'a@huit.edu.vn', 'expertise' => 'PHP, Laravel, Mã nguồn mở', 'max_periods_per_week' => 18],
    ['id' => 2, 'full_name' => 'GV Busy (Trùng Ca 2)', 'email' => 'b@huit.edu.vn', 'expertise' => 'PHP, Web, MySQL', 'max_periods_per_week' => 18],
    ['id' => 3, 'full_name' => 'GV Other (Mạng)', 'email' => 'c@huit.edu.vn', 'expertise' => 'Mạng máy tính, Cisco', 'max_periods_per_week' => 18],
];
$mockSchedules = [
    // GV 1 teaches CS1 Ca 1 -> Ca 2 at CS1 is adjacent same branch (+20 opt)!
    ['id' => 100, 'teacher_id' => 1, 'branch_id' => 1, 'branch_code' => 'CS1', 'subject_name' => 'Web PHP', 'room' => 'A101', 'day_of_week' => 2, 'shift_or_periods' => 'Ca 1 (Tiết 1-3)', 'start_period' => 1, 'end_period' => 3, 'start_date' => '2026-09-01', 'end_date' => '2026-12-31'],
    // GV 2 is already teaching Ca 2 -> MUST be hard excluded into unavailable!
    ['id' => 101, 'teacher_id' => 2, 'branch_id' => 2, 'branch_code' => 'CS2', 'subject_name' => 'SQL', 'room' => 'C101', 'day_of_week' => 2, 'shift_or_periods' => 'Ca 2 (Tiết 4-6)', 'start_period' => 4, 'end_period' => 6, 'start_date' => '2026-09-01', 'end_date' => '2026-12-31'],
];
$mockAvail = [
    1 => [['day_of_week' => 2, 'start_period' => 4, 'end_period' => 6, 'preferred_branch_id' => 1]],
];

$recResult = $recService->rankCandidates([
    'subject' => 'Phát triển phần mềm mã nguồn mở PHP',
    'branch_id' => 1,
    'branch_code' => 'CS1',
    'day_of_week' => 2,
    'start_period' => 4,
    'end_period' => 6,
    'start_date' => '2026-09-01',
    'end_date' => '2026-12-31',
], $mockTeachers, $mockSchedules, $mockAvail, $travelMatrix);

assertEquals(1, count($recResult['unavailable']), 'Busy teacher is strictly excluded into unavailable list');
assertEquals(2, $recResult['unavailable'][0]['teacher_id'], 'Unavailable list contains busy teacher ID=2 with reason');
assertEquals(1, $recResult['recommended'][0]['teacher_id'], 'Top-1 recommended teacher is GV 1 (Expertise + Avail + Adjacent Same Branch)');
assertTrue($recResult['recommended'][0]['score'] >= 90, 'Top-1 candidate scores >= 90/100 (got ' . $recResult['recommended'][0]['score'] . ')');

// --------------------------------------------------------------------------
// 5. ICalendarExporterService RFC 5545 Format Tests
// --------------------------------------------------------------------------
echo "\n[Suite 5] ICalendarExporterService (RFC 5545 Compliance)\n";

$exporter = new ICalendarExporterService();
$ics = $exporter->export(
    ['id' => 1, 'full_name' => 'ThS. Nguyễn Minh Quân'],
    [[
        'id' => 55, 'day_of_week' => 2, 'start_period' => 1, 'end_period' => 3,
        'shift_or_periods' => 'Ca 1 (Tiết 1-3)', 'start_date' => '2026-09-01', 'end_date' => '2026-11-30',
        'subject_name' => 'Phát triển phần mềm mã nguồn mở', 'branch_code' => 'CS1',
        'branch_name' => 'Cơ sở 1', 'branch_address' => '140 Lê Trọng Tấn, Tân Phú', 'room' => 'A301',
    ]]
);

assertTrue(str_contains($ics, "BEGIN:VCALENDAR\r\n") && str_contains($ics, "END:VCALENDAR\r\n"), 'ICS contains valid VCALENDAR wrapper with CRLF');
assertTrue(str_contains($ics, 'BEGIN:VEVENT') && str_contains($ics, 'RRULE:FREQ=WEEKLY;UNTIL='), 'ICS contains VEVENT with weekly RRULE UNTIL');
assertTrue(str_contains($ics, 'LOCATION:Cơ sở 1 - 140 Lê Trọng Tấn\, Tân Phú - Phòng A301'), 'ICS escapes commas properly in LOCATION with branch + address + room');

echo "\n--------------------------------------------\n";
echo "RESULT: {$passed} passed, {$failed} failed.\n";
exit($failed > 0 ? 1 : 0);
