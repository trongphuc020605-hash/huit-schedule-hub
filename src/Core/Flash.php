<?php

declare(strict_types=1);

namespace HuitSchedule\Core;

final class Flash
{
    private const FLASH_KEY = '_huit_flash';
    private const OLD_KEY   = '_huit_old';

    public static function set(string $type, string $message): void
    {
        $_SESSION[self::FLASH_KEY][] = [
            'type'    => $type,
            'message' => $message,
        ];
    }

    public static function success(string $message): void
    {
        self::set('success', $message);
    }

    public static function error(string $message): void
    {
        self::set('error', $message);
    }

    public static function warning(string $message): void
    {
        self::set('warning', $message);
    }

    public static function info(string $message): void
    {
        self::set('info', $message);
    }

    /**
     * @return array<int, array{type: string, message: string}>
     */
    public static function consume(): array
    {
        $messages = $_SESSION[self::FLASH_KEY] ?? [];
        unset($_SESSION[self::FLASH_KEY]);
        return is_array($messages) ? $messages : [];
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function setOld(array $input): void
    {
        unset($input['_csrf'], $input['password'], $input['password_confirm']);
        $_SESSION[self::OLD_KEY] = $input;
    }

    public static function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION[self::OLD_KEY][$key] ?? $default;
    }

    public static function clearOld(): void
    {
        unset($_SESSION[self::OLD_KEY]);
    }
}
