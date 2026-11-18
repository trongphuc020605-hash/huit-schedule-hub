<?php

declare(strict_types=1);

namespace HuitSchedule\Repositories;

use HuitSchedule\Core\Database;
use PDO;

final class TeacherRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(?string $role = null): array
    {
        $sql = 'SELECT t.*,
                       (SELECT COUNT(*) FROM schedules s WHERE s.teacher_id = t.id) AS schedule_count,
                       (SELECT COALESCE(SUM(s.end_period - s.start_period + 1), 0) FROM schedules s WHERE s.teacher_id = t.id) AS total_periods,
                       (SELECT COUNT(*) FROM teacher_availabilities a WHERE a.teacher_id = t.id) AS availability_count
                FROM teachers t';
        $params = [];
        if ($role !== null) {
            $sql .= ' WHERE t.role = :role';
            $params[':role'] = $role;
        }
        $sql .= ' ORDER BY t.role ASC, t.full_name ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM teachers WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT * FROM teachers WHERE email = :email';
        $params = [':email' => strtolower(trim($email))];
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByPortalToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM teachers WHERE portal_token = :token LIMIT 1');
        $stmt->execute([':token' => trim($token)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $password = (string) ($data['password'] ?? 'Teacher@123');
        $passwordHash = (string) ($data['password_hash'] ?? password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]));
        $portalToken = (string) ($data['portal_token'] ?? bin2hex(random_bytes(32)));

        $stmt = $this->pdo->prepare(
            'INSERT INTO teachers (full_name, email, phone, expertise, password_hash, role, portal_token, max_periods_per_week)
             VALUES (:full_name, :email, :phone, :expertise, :password_hash, :role, :portal_token, :max_periods)'
        );
        $stmt->execute([
            ':full_name'     => trim((string) $data['full_name']),
            ':email'         => strtolower(trim((string) $data['email'])),
            ':phone'         => trim((string) $data['phone']),
            ':expertise'     => trim((string) $data['expertise']),
            ':password_hash' => $passwordHash,
            ':role'          => (string) ($data['role'] ?? 'teacher'),
            ':portal_token'  => $portalToken,
            ':max_periods'   => (int) ($data['max_periods_per_week'] ?? 18),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $fields = [
            'full_name = :full_name',
            'email = :email',
            'phone = :phone',
            'expertise = :expertise',
            'role = :role',
            'max_periods_per_week = :max_periods',
        ];
        $params = [
            ':id'          => $id,
            ':full_name'   => trim((string) $data['full_name']),
            ':email'       => strtolower(trim((string) $data['email'])),
            ':phone'       => trim((string) $data['phone']),
            ':expertise'   => trim((string) $data['expertise']),
            ':role'        => (string) ($data['role'] ?? 'teacher'),
            ':max_periods' => (int) ($data['max_periods_per_week'] ?? 18),
        ];

        if (!empty($data['password'])) {
            $fields[] = 'password_hash = :password_hash';
            $params[':password_hash'] = password_hash((string) $data['password'], PASSWORD_BCRYPT, ['cost' => 10]);
        }

        $sql = 'UPDATE teachers SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function countSchedules(int $teacherId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM schedules WHERE teacher_id = :id');
        $stmt->execute([':id' => $teacherId]);
        return (int) $stmt->fetchColumn();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM teachers WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function regeneratePortalToken(int $id): string
    {
        $token = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare('UPDATE teachers SET portal_token = :token WHERE id = :id');
        $stmt->execute([':token' => $token, ':id' => $id]);
        return $token;
    }
}
