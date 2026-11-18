<?php

declare(strict_types=1);

namespace HuitSchedule\Core;

final class Csrf
{
    private const SESSION_KEY = '_huit_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function verify(?string $submittedToken): bool
    {
        $sessionToken = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($sessionToken) || !is_string($submittedToken) || $submittedToken === '') {
            return false;
        }
        return hash_equals($sessionToken, $submittedToken);
    }

    public static function verifyRequest(): bool
    {
        $token = $_POST['_csrf']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? null;
        return self::verify(is_string($token) ? $token : null);
    }

    public static function enforce(): void
    {
        if (self::verifyRequest()) {
            return;
        }

        if (str_starts_with(current_path(), '/api/')) {
            Response::json([
                'ok'    => false,
                'error' => 'CSRF token không hợp lệ hoặc đã hết hạn.',
                'code'  => 403,
            ], 403);
            return;
        }

        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><title>403 Forbidden - CSRF</title></head>'
            . '<body style="font-family:system-ui,sans-serif;padding:3rem;text-align:center;">'
            . '<h1>403 - Yêu cầu không hợp lệ (CSRF Token Mismatch)</h1>'
            . '<p>Phiên làm việc đã hết hạn hoặc mã bảo mật không khớp. Vui lòng tải lại trang và thử lại.</p>'
            . '<p><a href="' . e(url('/')) . '">Quay về trang chủ</a></p></body></html>';
        exit;
    }
}
