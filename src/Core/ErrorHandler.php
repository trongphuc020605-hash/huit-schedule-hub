<?php

declare(strict_types=1);

namespace HuitSchedule\Core;

use ErrorException;
use Throwable;

final class ErrorHandler
{
    public static function register(): void
    {
        $isProd = self::isProduction();
        ini_set('display_errors', $isProd ? '0' : '1');
        error_reporting(E_ALL);

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler([self::class, 'handleException']);
    }

    public static function handleException(Throwable $e): void
    {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, sprintf("[%s] %s in %s:%d\n%s\n", get_class($e), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()));
            exit(1);
        }

        $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int) $e->getCode() : 500;
        $isProd = self::isProduction();
        $isApi = str_starts_with(current_path(), '/api/');

        $safeMessage = $isProd
            ? 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau hoặc liên hệ quản trị viên.'
            : self::sanitizeMessage($e->getMessage());

        if ($isApi) {
            $payload = [
                'ok'    => false,
                'error' => $safeMessage,
                'code'  => $statusCode,
            ];
            if (!$isProd) {
                $payload['debug'] = [
                    'type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ];
            }
            Response::json($payload, $statusCode);
            return;
        }

        http_response_code($statusCode);
        header('Content-Type: text/html; charset=UTF-8');

        $title = $statusCode === 404 ? 'Không tìm thấy trang (404)' : ($statusCode === 403 ? 'Truy cập bị từ chối (403)' : 'Lỗi hệ thống (' . $statusCode . ')');
        $debugHtml = '';
        if (!$isProd) {
            $debugHtml = '<div style="margin-top:1.5rem;padding:1rem;background:#0f172a;color:#e2e8f0;border-radius:10px;font-family:monospace;font-size:12px;overflow:auto;text-align:left;">'
                . '<strong>' . e(get_class($e)) . '</strong>: ' . e(self::sanitizeMessage($e->getMessage())) . '<br>'
                . '<em>' . e($e->getFile()) . ':' . $e->getLine() . '</em><pre style="margin-top:8px;white-space:pre-wrap;">'
                . e(self::sanitizeMessage($e->getTraceAsString())) . '</pre></div>';
        }

        echo '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . e($title) . ' - HUIT Schedule Hub</title>'
            . '<style>body{font-family:system-ui,-apple-system,sans-serif;background:#f8fafc;color:#0f172a;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:1.5rem;}'
            . '.card{max-width:680px;width:100%;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:2rem;box-shadow:0 10px 30px rgba(15,23,42,.08);text-align:center;}'
            . '.code{font-size:3rem;font-weight:800;color:#1d4ed8;line-height:1;margin-bottom:.5rem;}'
            . 'a.btn{display:inline-block;margin-top:1.25rem;padding:.65rem 1.25rem;background:#1d4ed8;color:#fff;text-decoration:none;border-radius:10px;font-weight:600;}</style>'
            . '</head><body><div class="card"><div class="code">' . $statusCode . '</div>'
            . '<h1 style="margin:0 0 .5rem;font-size:1.35rem;">' . e($title) . '</h1>'
            . '<p style="color:#475569;margin:0;">' . e($safeMessage) . '</p>'
            . $debugHtml
            . '<a class="btn" href="' . e(url('/')) . '">Quay về trang chủ</a></div></body></html>';
        exit;
    }

    private static function isProduction(): bool
    {
        return strtolower((string) ($_ENV['APP_ENV'] ?? 'local')) === 'production';
    }

    private static function sanitizeMessage(string $message): string
    {
        $pass = (string) ($_ENV['DB_PASS'] ?? '');
        if ($pass !== '') {
            $message = str_replace($pass, '[REDACTED]', $message);
        }
        return $message;
    }
}
