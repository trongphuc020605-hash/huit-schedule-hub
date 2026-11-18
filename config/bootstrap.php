<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use HuitSchedule\Core\Csrf;
use HuitSchedule\Core\ErrorHandler;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/vendor/autoload.php';

// Load environment variables and enforce required database keys
if (file_exists(BASE_PATH . '/.env')) {
    $dotenv = Dotenv::createImmutable(BASE_PATH);
    $dotenv->load();
    $dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER'])->notEmpty();
}

$GLOBALS['APP_CONFIG'] = require BASE_PATH . '/config/app.php';

date_default_timezone_set((string) ($GLOBALS['APP_CONFIG']['timezone'] ?? 'Asia/Ho_Chi_Minh'));

// Error & Exception Handling
ErrorHandler::register();

// HTTP Hardening & Session setup (Web context only)
if (PHP_SAPI !== 'cli') {
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name('HUITSCHED_SESSID');
        session_start();
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('app_config')) {
    function app_config(?string $key = null, mixed $default = null): mixed
    {
        $config = $GLOBALS['APP_CONFIG'] ?? [];
        if ($key === null) {
            return $config;
        }
        $segments = explode('.', $key);
        $current = $config;
        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }
        return $current;
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return BASE_PATH . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
    }
}

if (!function_exists('base_url')) {
    function base_url(): string
    {
        if (PHP_SAPI === 'cli') {
            return rtrim((string) ($_ENV['APP_URL'] ?? 'http://huit-schedule.test'), '/');
        }
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $dir = rtrim(dirname($scriptName), '/');
        if ($dir === '.' || $dir === '\\') {
            $dir = '';
        }
        return $dir;
    }
}

if (!function_exists('full_base_url')) {
    function full_base_url(): string
    {
        if (PHP_SAPI === 'cli') {
            return rtrim((string) ($_ENV['APP_URL'] ?? 'http://huit-schedule.test'), '/');
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'huit-schedule.test';
        return $scheme . '://' . $host . base_url();
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = base_url();
        $cleanPath = '/' . ltrim($path, '/');
        if ($cleanPath === '/') {
            return $base !== '' ? $base . '/' : '/';
        }
        return $base . $cleanPath;
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
    }
}

if (!function_exists('current_path')) {
    function current_path(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
        $base = base_url();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }
        $path = '/' . trim($path, '/');
        return $path === '' ? '/' : $path;
    }
}

if (!function_exists('day_label')) {
    function day_label(int $day): string
    {
        $days = app_config('days', []);
        return $days[$day] ?? ('Thứ ' . $day);
    }
}

if (!function_exists('format_shift_label')) {
    function format_shift_label(int $start, int $end): string
    {
        $shifts = app_config('shifts', []);
        foreach ($shifts as $shift) {
            if ($shift['start_period'] === $start && $shift['end_period'] === $end) {
                return $shift['label'];
            }
        }
        return sprintf('Tiết %d-%d', $start, $end);
    }
}

if (!function_exists('period_time_range')) {
    function period_time_range(int $start, int $end): string
    {
        $periods = app_config('periods', []);
        $s = $periods[$start]['start'] ?? '07:00';
        $e = $periods[$end]['end'] ?? '09:30';
        return $s . ' - ' . $e;
    }
}
