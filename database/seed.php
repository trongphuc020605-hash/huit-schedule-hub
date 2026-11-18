<?php

declare(strict_types=1);

/**
 * HUIT Schedule Hub — Idempotent Database Seeder (Faker vi_VN + Conflict-Free Guarantee)
 *
 * Usage:
 *   php database/seed.php          (seeds if DB is empty)
 *   php database/seed.php --fresh  (truncates and reseeds cleanly)
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

use Faker\Factory;
use HuitSchedule\Core\Database;
use HuitSchedule\Domain\TimeSlot;
use HuitSchedule\Repositories\AvailabilityRepository;
use HuitSchedule\Repositories\BranchRepository;
use HuitSchedule\Repositories\ScheduleRepository;
use HuitSchedule\Repositories\TeacherRepository;
use HuitSchedule\Services\ConflictDetectorService;
use HuitSchedule\Services\ScheduleOptimizerService;

$fresh = in_array('--fresh', $argv ?? [], true);
$pdo   = Database::getConnection();

$existingBranches = (int) $pdo->query('SELECT COUNT(*) FROM branches')->fetchColumn();
if ($existingBranches > 0 && !$fresh) {
    echo "[SEED] Database already contains data ({$existingBranches} branches). Use --fresh to reset and reseed.\n";
    exit(0);
}

if ($fresh) {
    echo "[SEED] Truncating existing tables (--fresh)...\n";
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['branch_travel_times', 'teacher_availabilities', 'schedules', 'teachers', 'branches'] as $tbl) {
        $pdo->exec("TRUNCATE TABLE `{$tbl}`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

$faker = Factory::create('vi_VN');
$faker->seed(20261118);

$branchRepo   = new BranchRepository($pdo);
$teacherRepo  = new TeacherRepository($pdo);
$availRepo    = new AvailabilityRepository($pdo);
$scheduleRepo = new ScheduleRepository($pdo);
$conflictSvc  = new ConflictDetectorService($scheduleRepo);

// 1. Seed 4 HUIT Campuses
$branchSpecs = [
    [
        'code'        => 'CS1',
        'branch_name' => 'Cơ sở 1 — Trụ sở chính Lê Trọng Tấn',
        'address'     => '140 Lê Trọng Tấn, P. Tây Thạnh, Q. Tân Phú, TP.HCM',
        'campus_zone' => 'Tân Phú',
        'color_hex'   => '#1D4ED8',
    ],
    [
        'code'        => 'CS2',
        'branch_name' => 'Cơ sở 2 — Tân Kỳ Tân Quý',
        'address'     => '93 Tân Kỳ Tân Quý, P. Tân Sơn Nhì, Q. Tân Phú, TP.HCM',
        'campus_zone' => 'Tân Phú',
        'color_hex'   => '#059669',
    ],
    [
        'code'        => 'CS3',
        'branch_name' => 'Cơ sở 3 — Trung tâm Thực hành Chế Lan Viên',
        'address'     => '31 Chế Lan Viên, P. Tây Thạnh, Q. Tân Phú, TP.HCM',
        'campus_zone' => 'Tân Phú',
        'color_hex'   => '#D97706',
    ],
    [
        'code'        => 'CS4',
        'branch_name' => 'Cơ sở 4 — Trung tâm Liên kết Quận 12',
        'address'     => '245 Tô Ký, P. Trung Mỹ Tây, Quận 12, TP.HCM',
        'campus_zone' => 'Quận 12',
        'color_hex'   => '#7C3AED',
    ],
];

$branchIds = [];
foreach ($branchSpecs as $spec) {
    $branchIds[$spec['code']] = $branchRepo->create($spec);
}

// 2. Seed Symmetric Travel Time Matrix (HUIT real geography)
foreach ($branchIds as $code => $id) {
    $branchRepo->upsertTravelTime($id, $id, 0, 0.0);
}
$travelPairs = [
    ['CS1', 'CS3', 6,  0.8],
    ['CS1', 'CS2', 15, 2.6],
    ['CS2', 'CS3', 14, 2.3],
    ['CS1', 'CS4', 35, 9.5],
    ['CS2', 'CS4', 38, 10.4],
    ['CS3', 'CS4', 40, 11.0],
];
foreach ($travelPairs as [$from, $to, $mins, $km]) {
    $branchRepo->upsertTravelTime($branchIds[$from], $branchIds[$to], $mins, $km);
}
echo "[SEED] Created 4 HUIT branches and symmetric travel matrix.\n";

// 3. Seed Admin + Demo Teacher + 14 Faker IT Faculty across 6 clusters
$adminHash   = password_hash('Admin@123', PASSWORD_BCRYPT, ['cost' => 10]);
$teacherHash = password_hash('Teacher@123', PASSWORD_BCRYPT, ['cost' => 10]);

$teacherRepo->create([
    'full_name'            => 'TS. Trần Quốc Bảo (Phòng Đào tạo)',
    'email'                => 'admin@huit.edu.vn',
    'phone'                => '0903140140',
    'expertise'            => 'Quản trị hệ thống đào tạo, Công nghệ phần mềm',
    'password_hash'        => $adminHash,
    'role'                 => 'admin',
    'portal_token'         => hash('sha256', 'huit-admin-portal-token-2026'),
    'max_periods_per_week' => 12,
]);

$demoTeacherId = $teacherRepo->create([
    'full_name'            => 'ThS. Nguyễn Minh Quân',
    'email'                => 'giangvien@huit.edu.vn',
    'phone'                => '0908123456',
    'expertise'            => 'Phát triển phần mềm mã nguồn mở, PHP, Web, MySQL, Laravel',
    'password_hash'        => $teacherHash,
    'role'                 => 'teacher',
    'portal_token'         => hash('sha256', 'huit-demo-teacher-portal-token-2026'),
    'max_periods_per_week' => 18,
]);

$facultyClusters = [
    [
        'expertise' => 'Phát triển phần mềm mã nguồn mở, PHP, Laravel, Git, REST API',
        'subjects'  => ['Phát triển phần mềm mã nguồn mở', 'Lập trình Web PHP & MySQL', 'Xây dựng RESTful API với Laravel'],
    ],
    [
        'expertise' => 'Cơ sở dữ liệu, MySQL, PostgreSQL, NoSQL, MongoDB, Redis, Big Data',
        'subjects'  => ['Hệ quản trị Cơ sở dữ liệu MySQL', 'Cơ sở dữ liệu NoSQL & Redis', 'Kho dữ liệu và Big Data'],
    ],
    [
        'expertise' => 'Trí tuệ nhân tạo, Machine Learning, Deep Learning, Python, Data Science',
        'subjects'  => ['Trí tuệ nhân tạo ứng dụng', 'Học máy (Machine Learning)', 'Khoa học dữ liệu với Python'],
    ],
    [
        'expertise' => 'Công nghệ phần mềm, OOP, Java, Spring Boot, C#, Kiểm thử phần mềm',
        'subjects'  => ['Lập trình hướng đối tượng Java', 'Công nghệ phần mềm nâng cao', 'Kiểm thử và Đảm bảo chất lượng PM'],
    ],
    [
        'expertise' => 'Mạng máy tính, An toàn thông tin, Linux, DevOps, Docker, Cloud AWS',
        'subjects'  => ['Mạng máy tính căn bản', 'An toàn thông tin & Bảo mật Web', 'DevOps, Docker & CI/CD'],
    ],
    [
        'expertise' => 'Frontend, ReactJS, VueJS, TypeScript, Thiết kế giao diện UI/UX, Figma',
        'subjects'  => ['Lập trình Frontend với ReactJS', 'Thiết kế giao diện UI/UX', 'Phát triển ứng dụng TypeScript'],
    ],
];

$titles = ['TS.', 'ThS.', 'ThS.', 'GVC. ThS.', 'TS.'];
$teacherRecords = [
    ['id' => $demoTeacherId, 'cluster_idx' => 0],
];

for ($i = 0; $i < 14; $i++) {
    $clusterIdx = $i % count($facultyClusters);
    $title      = $titles[$i % count($titles)];
    $rawName    = $faker->lastName() . ' ' . $faker->middleName() . ' ' . $faker->firstName();
    $fullName   = $title . ' ' . preg_replace('/\s+/', ' ', trim($rawName));
    $email      = sprintf('gv%02d.%s@huit.edu.vn', $i + 1, strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $faker->userName()) ?: 'huit'));
    $phone      = '09' . $faker->numerify('########');

    $tid = $teacherRepo->create([
        'full_name'            => $fullName,
        'email'                => $email,
        'phone'                => $phone,
        'expertise'            => $facultyClusters[$clusterIdx]['expertise'],
        'password_hash'        => $teacherHash,
        'role'                 => 'teacher',
        'portal_token'         => hash('sha256', 'huit-teacher-token-' . ($i + 1) . '-2026'),
        'max_periods_per_week' => ($i % 3 === 0) ? 15 : 18,
    ]);

    $teacherRecords[] = ['id' => $tid, 'cluster_idx' => $clusterIdx];
}
echo "[SEED] Created 1 Admin + 15 Teachers (1 demo + 14 Faker vi_VN).\n";

// 4. Seed 8-12 Availabilities per teacher
$shifts = app_config('shifts', []);
$allBranchIdList = array_values($branchIds);
$availCount = 0;

foreach ($teacherRecords as $idx => $rec) {
    $tid = $rec['id'];
    $slotPool = [];
    for ($d = 2; $d <= 7; $d++) {
        for ($sh = 1; $sh <= 5; $sh++) {
            $slotPool[] = [$d, $sh];
        }
    }
    shuffle($slotPool);
    $numSlots = 8 + ($idx % 5); // 8..12 slots
    $preferredBranch = $allBranchIdList[$idx % count($allBranchIdList)];

    // Guarantee demo teacher has Mon Ca 1,2,3 and Wed Ca 1,2 at CS1
    if ($tid === $demoTeacherId) {
        $guaranteed = [[2, 1], [2, 2], [2, 3], [4, 1], [4, 2], [5, 1], [5, 2], [6, 1], [6, 3], [7, 1]];
        foreach ($guaranteed as [$gd, $gsh]) {
            $sDef = $shifts[$gsh];
            $availRepo->upsert([
                'teacher_id'          => $tid,
                'day_of_week'         => $gd,
                'shift_or_periods'    => $sDef['label'],
                'start_period'        => $sDef['start_period'],
                'end_period'          => $sDef['end_period'],
                'preferred_branch_id' => $branchIds['CS1'],
            ]);
            $availCount++;
        }
        continue;
    }

    for ($k = 0; $k < $numSlots; $k++) {
        [$d, $sh] = $slotPool[$k];
        $sDef = $shifts[$sh];
        $availRepo->upsert([
            'teacher_id'          => $tid,
            'day_of_week'         => $d,
            'shift_or_periods'    => $sDef['label'],
            'start_period'        => $sDef['start_period'],
            'end_period'          => $sDef['end_period'],
            'preferred_branch_id' => ($k % 3 === 0) ? null : $preferredBranch,
        ]);
        $availCount++;
    }
}
echo "[SEED] Created {$availCount} teacher availability slots.\n";

// 5. Seed Semester Schedules covering current month + next 2 months (0 Hard Conflicts)
$startDate = date('Y-m-01');
$endDate   = date('Y-m-t', strtotime('+2 months'));

$createdSchedules = 0;
$tryInsert = static function (array $payload) use ($conflictSvc, $scheduleRepo, &$createdSchedules): bool {
    $check = $conflictSvc->checkConflicts($payload);
    if ($check['has_conflict']) {
        return false;
    }
    $scheduleRepo->create($payload);
    $createdSchedules++;
    return true;
};

// 5a. Curated Showcase Scenarios (Demo teacher + 2 IMPOSSIBLE_TRAVEL + 2 DEAD_GAP)
$curated = [
    // Demo Teacher (giangvien@huit.edu.vn): Optimal adjacent same-campus on Mon + healthy commute on Wed + Fri class
    [
        'teacher_id' => $demoTeacherId, 'branch_id' => $branchIds['CS1'], 'room' => 'A301',
        'subject_name' => 'Phát triển phần mềm mã nguồn mở', 'day_of_week' => 2,
        'start_period' => 1, 'end_period' => 3, 'shift_or_periods' => 'Ca 1 (Tiết 1-3)',
        'start_date' => $startDate, 'end_date' => $endDate, 'notes' => 'Lớp 13DHTH01 - Phòng thực hành Web',
    ],
    [
        'teacher_id' => $demoTeacherId, 'branch_id' => $branchIds['CS1'], 'room' => 'A302',
        'subject_name' => 'Lập trình Web PHP & MySQL', 'day_of_week' => 2,
        'start_period' => 4, 'end_period' => 6, 'shift_or_periods' => 'Ca 2 (Tiết 4-6)',
        'start_date' => $startDate, 'end_date' => $endDate, 'notes' => 'Lớp 13DHTH02 - Liền ca cùng CS1',
    ],
    [
        'teacher_id' => $demoTeacherId, 'branch_id' => $branchIds['CS1'], 'room' => 'B204',
        'subject_name' => 'Xây dựng RESTful API với Laravel', 'day_of_week' => 4,
        'start_period' => 1, 'end_period' => 3, 'shift_or_periods' => 'Ca 1 (Tiết 1-3)',
        'start_date' => $startDate, 'end_date' => $endDate, 'notes' => 'Lớp 13DHTH03',
    ],
    [
        'teacher_id' => $demoTeacherId, 'branch_id' => $branchIds['CS3'], 'room' => 'PM05',
        'subject_name' => 'Thực hành Git & DevOps Mã nguồn mở', 'day_of_week' => 4,
        'start_period' => 7, 'end_period' => 9, 'shift_or_periods' => 'Ca 3 (Tiết 7-9)',
        'start_date' => $startDate, 'end_date' => $endDate, 'notes' => 'Thực hành CS3 Chế Lan Viên',
    ],
    [
        'teacher_id' => $demoTeacherId, 'branch_id' => $branchIds['CS2'], 'room' => 'C102',
        'subject_name' => 'Đồ án Phát triển phần mềm mã nguồn mở', 'day_of_week' => 6,
        'start_period' => 1, 'end_period' => 3, 'shift_or_periods' => 'Ca 1 (Tiết 1-3)',
        'start_date' => $startDate, 'end_date' => $endDate, 'notes' => 'Hướng dẫn đồ án chuyên ngành',
    ],

    // IMPOSSIBLE_TRAVEL #1: Teacher #2 teaches CS1 Ca 1 (ends 09:30) then CS4 Ca 2 (starts 09:40 -> 10m break vs 35m travel!)
    [
        'teacher_id' => $teacherRecords[1]['id'], 'branch_id' => $branchIds['CS1'], 'room' => 'A401',
        'subject_name' => 'Hệ quản trị Cơ sở dữ liệu MySQL', 'day_of_week' => 3,
        'start_period' => 1, 'end_period' => 3, 'shift_or_periods' => 'Ca 1 (Tiết 1-3)',
        'start_date' => $startDate, 'end_date' => $endDate, 'notes' => 'Demo cảnh báo IMPOSSIBLE_TRAVEL #1',
    ],
    [
        'teacher_id' => $teacherRecords[1]['id'], 'branch_id' => $branchIds['CS4'], 'room' => 'D101',
        'subject_name' => 'Cơ sở dữ liệu NoSQL & Redis', 'day_of_week' => 3,
        'start_period' => 4, 'end_period' => 6, 'shift_or_periods' => 'Ca 2 (Tiết 4-6)',
        'start_date' => $startDate, 'end_date' => $endDate, 'notes' => 'Cần 35p CS1->CS4 nhưng chỉ nghỉ 10p',
    ],

    // IMPOSSIBLE_TRAVEL #2: Teacher #3 teaches CS3 Ca 3 (ends 15:00) then CS4 Ca 4 (starts 15:10 -> 10m break vs 40m travel!)
    [
        'teacher_id' => $teacherRecords[2]['id'], 'branch_id' => $branchIds['CS3'], 'room' => 'PM02',
        'subject_name' => 'Học máy (Machine Learning)', 'day_of_week' => 5,
        'start_period' => 7, 'end_period' => 9, 'shift_or_periods' => 'Ca 3 (Tiết 7-9)',
        'start_date' => $startDate, 'end_date' => $endDate, 'notes' => 'Demo cảnh báo IMPOSSIBLE_TRAVEL #2',
    ],
    [
        'teacher_id' => $teacherRecords[2]['id'], 'branch_id' => $branchIds['CS4'], 'room' => 'D205',
        'subject_name' => 'Khoa học dữ liệu với Python', 'day_of_week' => 5,
        'start_period' => 10, 'end_period' => 12, 'shift_or_periods' => 'Ca 4 (Tiết 10-12)',
        'start_date' => $startDate, 'end_date' => $endDate, 'notes' => 'Cần 40p CS3->CS4 nhưng chỉ nghỉ 10p',
    ],

    // DEAD_GAP #1: Teacher #4 teaches CS1 Ca 1 (T1-3) and CS1 Ca 4 (T10-12) -> 6 wasted periods!
    [
        'teacher_id' => $teacherRecords[3]['id'], 'branch_id' => $branchIds['CS1'], 'room' => 'B305',
        'subject_name' => 'Lập trình hướng đối tượng Java', 'day_of_week' => 2,
        'start_period' => 1, 'end_period' => 3, 'shift_or_periods' => 'Ca 1 (Tiết 1-3)',
        'start_date' => $startDate, 'end_date' => $endDate, 'notes' => 'Demo cảnh báo DEAD_GAP #1',
    ],
    [
        'teacher_id' => $teacherRecords[3]['id'], 'branch_id' => $branchIds['CS1'], 'room' => 'B306',
        'subject_name' => 'Công nghệ phần mềm nâng cao', 'day_of_week' => 2,
        'start_period' => 10, 'end_period' => 12, 'shift_or_periods' => 'Ca 4 (Tiết 10-12)',
        'start_date' => $startDate, 'end_date' => $endDate, 'notes' => 'Trống 6 tiết giữa Ca 1 và Ca 4',
    ],

    // DEAD_GAP #2: Teacher #5 teaches CS2 Ca 1 (T1-3) and CS2 Ca 5 (T13-15) -> 9 wasted periods!
    [
        'teacher_id' => $teacherRecords[4]['id'], 'branch_id' => $branchIds['CS2'], 'room' => 'C201',
        'subject_name' => 'Mạng máy tính căn bản', 'day_of_week' => 4,
        'start_period' => 1, 'end_period' => 3, 'shift_or_periods' => 'Ca 1 (Tiết 1-3)',
        'start_date' => $startDate, 'end_date' => $endDate, 'notes' => 'Demo cảnh báo DEAD_GAP #2',
    ],
    [
        'teacher_id' => $teacherRecords[4]['id'], 'branch_id' => $branchIds['CS2'], 'room' => 'C203',
        'subject_name' => 'An toàn thông tin & Bảo mật Web', 'day_of_week' => 4,
        'start_period' => 13, 'end_period' => 15, 'shift_or_periods' => 'Ca 5 (Tiết 13-15)',
        'start_date' => $startDate, 'end_date' => $endDate, 'notes' => 'Trống 9 tiết giữa Ca 1 và Ca 5',
    ],
];

foreach ($curated as $c) {
    $tryInsert($c);
}

// 5b. Fill up to 46 total conflict-free schedules across all 15 teachers & 4 campuses
$roomsByBranch = [
    $branchIds['CS1'] => ['A201', 'A202', 'A303', 'A402', 'B101', 'B205', 'B301', 'B404'],
    $branchIds['CS2'] => ['C101', 'C103', 'C202', 'C204', 'C301'],
    $branchIds['CS3'] => ['PM01', 'PM03', 'PM04', 'PM06', 'LAB01'],
    $branchIds['CS4'] => ['D102', 'D104', 'D201', 'D203', 'D301'],
];

$attempts = 0;
$targetTotal = 46;
while ($createdSchedules < $targetTotal && $attempts < 1000) {
    $attempts++;
    $rec = $teacherRecords[$attempts % count($teacherRecords)];
    $tid = $rec['id'];
    if ($tid === $demoTeacherId && $attempts % 5 !== 0) {
        continue;
    }

    $cluster  = $facultyClusters[$rec['cluster_idx']];
    $subject  = $cluster['subjects'][$faker->numberBetween(0, count($cluster['subjects']) - 1)];
    $branchId = $allBranchIdList[$faker->numberBetween(0, count($allBranchIdList) - 1)];
    $roomList = $roomsByBranch[$branchId];
    $room     = $roomList[$faker->numberBetween(0, count($roomList) - 1)];
    $day      = $faker->numberBetween(2, 7); // Mon..Sat
    $shiftNum = $faker->numberBetween(1, 5);
    $sDef     = $shifts[$shiftNum];

    // Prevent inadvertently stacking a 3rd class on curated conflict demo days
    if (($tid === $teacherRecords[1]['id'] && $day === 3)
        || ($tid === $teacherRecords[2]['id'] && $day === 5)
        || ($tid === $teacherRecords[3]['id'] && $day === 2)
        || ($tid === $teacherRecords[4]['id'] && $day === 4)) {
        continue;
    }

    $tryInsert([
        'teacher_id'       => $tid,
        'branch_id'        => $branchId,
        'subject_name'     => $subject,
        'room'             => $room,
        'day_of_week'      => $day,
        'shift_or_periods' => $sDef['label'],
        'start_period'     => $sDef['start_period'],
        'end_period'       => $sDef['end_period'],
        'start_date'       => $startDate,
        'end_date'         => $endDate,
        'notes'            => sprintf('Lớp 13DHTH%02d', 1 + ($attempts % 12)),
    ]);
}

// Summary verification
$optimizer = new ScheduleOptimizerService();
$allSched  = $scheduleRepo->findAll();
$analysis  = $optimizer->analyzeAll($allSched, $branchRepo->getTravelMatrix());

echo sprintf(
    "[SEED] Completed! %d schedules created (0 hard conflicts).\n",
    $createdSchedules
);
echo sprintf(
    "[SEED] Optimization alerts seeded for Dashboard showcase: IMPOSSIBLE_TRAVEL=%d, TIGHT_TRAVEL=%d, DEAD_GAP=%d.\n",
    $analysis['counts']['impossible_travel'],
    $analysis['counts']['tight_travel'],
    $analysis['counts']['dead_gap']
);
