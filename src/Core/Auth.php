<?php

declare(strict_types=1);

namespace HuitSchedule\Core;

use HuitSchedule\Repositories\TeacherRepository;

final class Auth
{
    private const SESSION_USER_KEY = '_huit_auth_user';

    /**
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        $user = $_SESSION[self::SESSION_USER_KEY] ?? null;
        return is_array($user) ? $user : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user !== null ? (int) $user['id'] : null;
    }

    public static function role(): ?string
    {
        $user = self::user();
        return $user !== null ? (string) $user['role'] : null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function isTeacher(): bool
    {
        return self::role() === 'teacher';
    }

    public static function attempt(string $email, string $password): bool
    {
        $repo = new TeacherRepository();
        $teacher = $repo->findByEmail(trim($email));

        if ($teacher === null) {
            return false;
        }

        if (!password_verify($password, (string) $teacher['password_hash'])) {
            return false;
        }

        self::login($teacher);
        return true;
    }

    /**
     * @param array<string, mixed> $teacher
     */
    public static function login(array $teacher): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION[self::SESSION_USER_KEY] = [
            'id'           => (int) $teacher['id'],
            'full_name'    => (string) $teacher['full_name'],
            'email'        => (string) $teacher['email'],
            'role'         => (string) $teacher['role'],
            'portal_token' => (string) $teacher['portal_token'],
            'expertise'    => (string) ($teacher['expertise'] ?? ''),
        ];
    }

    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_USER_KEY]);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Resolve portal access via either authenticated session or read-only personal token.
     *
     * @return array{teacher: array<string, mixed>, read_only: bool, via_token: bool}|null
     */
    public static function resolvePortalAccess(?string $token, ?int $requestedTeacherId = null): ?array
    {
        $repo = new TeacherRepository();

        if ($token !== null && trim($token) !== '') {
            $teacher = $repo->findByPortalToken(trim($token));
            if ($teacher !== null) {
                $isOwnerLoggedIn = self::check() && (self::id() === (int) $teacher['id'] || self::isAdmin());
                return [
                    'teacher'   => $teacher,
                    'read_only' => !$isOwnerLoggedIn,
                    'via_token' => true,
                ];
            }
            return null;
        }

        if (!self::check()) {
            return null;
        }

        $targetId = self::id();
        if (self::isAdmin()) {
            if ($requestedTeacherId !== null && $requestedTeacherId > 0) {
                $targetId = $requestedTeacherId;
            } else {
                $instructors = $repo->all('teacher');
                if ($instructors !== []) {
                    $targetId = (int) $instructors[0]['id'];
                }
            }
        }

        $teacher = $targetId !== null ? $repo->findById($targetId) : null;
        if ($teacher === null) {
            return null;
        }

        return [
            'teacher'   => $teacher,
            'read_only' => false,
            'via_token' => false,
        ];
    }
}
