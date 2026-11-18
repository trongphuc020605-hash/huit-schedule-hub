<?php

declare(strict_types=1);

namespace HuitSchedule\Repositories;

use HuitSchedule\Core\Database;
use PDO;

final class AvailabilityRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByTeacher(int $teacherId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, b.branch_name AS preferred_branch_name, b.code AS preferred_branch_code, b.color_hex AS preferred_branch_color
             FROM teacher_availabilities a
             LEFT JOIN branches b ON b.id = a.preferred_branch_id
             WHERE a.teacher_id = :teacher_id
             ORDER BY a.day_of_week ASC, a.start_period ASC'
        );
        $stmt->execute([':teacher_id' => $teacherId]);
        return $stmt->fetchAll();
    }

    /**
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function allGroupedByTeacher(): array
    {
        $stmt = $this->pdo->query(
            'SELECT a.*, b.code AS preferred_branch_code, b.branch_name AS preferred_branch_name
             FROM teacher_availabilities a
             LEFT JOIN branches b ON b.id = a.preferred_branch_id
             ORDER BY a.teacher_id ASC, a.day_of_week ASC, a.start_period ASC'
        );
        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $grouped[(int) $row['teacher_id']][] = $row;
        }
        return $grouped;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upsert(array $data): int
    {
        $sql = 'INSERT INTO teacher_availabilities
                    (teacher_id, day_of_week, shift_or_periods, start_period, end_period, preferred_branch_id)
                VALUES
                    (:teacher_id, :day_of_week, :shift_or_periods, :start_period, :end_period, :preferred_branch_id)
                ON DUPLICATE KEY UPDATE
                    shift_or_periods = VALUES(shift_or_periods),
                    preferred_branch_id = VALUES(preferred_branch_id)';

        $preferred = isset($data['preferred_branch_id']) && $data['preferred_branch_id'] !== '' && (int) $data['preferred_branch_id'] > 0
            ? (int) $data['preferred_branch_id']
            : null;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':teacher_id'          => (int) $data['teacher_id'],
            ':day_of_week'         => (int) $data['day_of_week'],
            ':shift_or_periods'    => (string) $data['shift_or_periods'],
            ':start_period'        => (int) $data['start_period'],
            ':end_period'          => (int) $data['end_period'],
            ':preferred_branch_id' => $preferred,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id, ?int $teacherId = null): bool
    {
        $sql = 'DELETE FROM teacher_availabilities WHERE id = :id';
        $params = [':id' => $id];
        if ($teacherId !== null) {
            $sql .= ' AND teacher_id = :teacher_id';
            $params[':teacher_id'] = $teacherId;
        }
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
}
