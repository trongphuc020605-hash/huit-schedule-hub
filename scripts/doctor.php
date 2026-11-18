<?php

declare(strict_types=1);

/**
 * HUIT Schedule Hub — Environment & Architecture Health Doctor (composer doctor)
 * Verifies PHP 8.1+, extensions, Composer packages, .env, MySQL huit_db, SQL dump, and Clean Architecture guards.
 */

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;

function doctorCheck(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    $status = $ok ? 'OK  ' : 'FAIL';
    if ($ok) {
        $passed++;
    } else {
        $failed++;
    }
    echo sprintf("  [%s] %s%s\n", $status, $label, $detail !== '' ? (' — ' . $detail) : '');
}

echo "=== HUIT Schedule Hub: Environment Doctor ===\n\n";

// 1. PHP Version & Extensions
doctorCheck(PHP_VERSION_ID >= 80100, 'PHP Version >= 8.1', PHP_VERSION);
foreach (['pdo', 'pdo_mysql', 'mbstring', 'json', 'openssl'] as $ext) {
    doctorCheck(extension_loaded($ext), "PHP Extension: {$ext}");
}

// 2. Composer Autoload & Required OSS Packages
$autoloadOk = is_file($root . '/vendor/autoload.php');
doctorCheck($autoloadOk, 'Composer vendor/autoload.php');
if ($autoloadOk) {
    require_once $root . '/vendor/autoload.php';
    doctorCheck(class_exists(\Dotenv\Dotenv::class), 'Package vlucas/phpdotenv loaded');
    doctorCheck(class_exists(\Faker\Factory::class), 'Package fakerphp/faker loaded');
    doctorCheck(class_exists(\HuitSchedule\Domain\TimeSlot::class), 'PSR-4 Namespace HuitSchedule\\ autoload');
}

// 3. Required Open Source & Config Files
foreach (['.env', '.env.example', '.gitignore', '.htaccess', 'LICENSE', 'README.md', 'database/schema.sql', 'database/huit_db.sql', '.github/workflows/php-lint.yml'] as $reqFile) {
    doctorCheck(is_file($root . '/' . $reqFile), "File exists: {$reqFile}");
}

// 4. Database Connectivity & Required Tables
if ($autoloadOk && is_file($root . '/.env')) {
    try {
        require_once $root . '/config/bootstrap.php';
        $pdo = \HuitSchedule\Core\Database::getConnection();
        $ver = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        doctorCheck(true, 'MySQL connection (' . ($_ENV['DB_NAME'] ?? '') . ')', 'MySQL ' . $ver);

        $counts = [];
        foreach (['branches', 'teachers', 'schedules', 'teacher_availabilities', 'branch_travel_times'] as $tbl) {
            $cnt = (int) $pdo->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
            $counts[] = "{$tbl}={$cnt}";
        }
        doctorCheck(true, 'All 5 normalized tables ready', implode(', ', $counts));
    } catch (\Throwable $e) {
        doctorCheck(false, 'MySQL connection failed', $e->getMessage());
    }
}

// 5. Architecture Guards (No SQL in views/, strict_types in src/)
$viewSqlViolations = 0;
$vIt = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/views'));
foreach ($vIt as $f) {
    if ($f->isFile() && $f->getExtension() === 'php') {
        $code = (string) file_get_contents($f->getPathname());
        if (preg_match('/\b(PDO|SELECT\s+[\w\*]+\s+FROM|INSERT\s+INTO|DELETE\s+FROM|UPDATE\s+\w+\s+SET)\b/i', $code)) {
            $viewSqlViolations++;
        }
    }
}
doctorCheck($viewSqlViolations === 0, 'Clean Architecture: 0 SQL/PDO statements inside views/');

$strictViolations = 0;
$sIt = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src'));
foreach ($sIt as $f) {
    if ($f->isFile() && $f->getExtension() === 'php') {
        $code = (string) file_get_contents($f->getPathname());
        if (!str_contains($code, 'declare(strict_types=1);')) {
            $strictViolations++;
        }
    }
}
doctorCheck($strictViolations === 0, 'Strict Typing: 100% files in src/ declare(strict_types=1)');

echo "\n--------------------------------------------\n";
echo "DOCTOR SUMMARY: {$passed} OK, {$failed} FAILED.\n";
exit($failed > 0 ? 1 : 0);
