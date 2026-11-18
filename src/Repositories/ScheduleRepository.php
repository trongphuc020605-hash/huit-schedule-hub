<?php

declare(strict_types=1);

namespace HuitSchedule\Repositories;

use HuitSchedule\Core\Database;
use PDO;

final class ScheduleRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*,
                    t.full_name AS teacher_name, t.email AS teacher_email, t.expertise AS teacher_expertise, t.max_periods_per_week,
                    b.branch_name, b.code AS branch_code, b.address AS branch_address, b.campus_zone, b.color_hex AS branch_color
             FROM schedules s
             INNER JOIN teachers t ON t.id = s.teacher_id
             INNER JOIN branches b ON b.id = s.branch_id
             WHERE s.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function findAll(array $filters = []): array
    {
        $sql = 'SELECT s.*,
                       t.full_name AS teacher_name, t.email AS teacher_email, t.expertise AS teacher_expertise, t.max_periods_per_week,
                       b.branch_name, b.code AS branch_code, b.address AS branch_address, b.campus_zone, b.color_hex AS branch_color
                FROM schedules s
                INNER JOIN teachers t ON t.id = s.teacher_id
                INNER JOIN branches b ON b.id = s.branch_id
                WHERE 1=1';

        $params = [];

        if (!empty($filters['branch_id'])) {
            $sql .= ' AND s.branch_id = :branch_id';
            $params[':branch_id'] = (int) $filters['branch_id'];
        }
        if (!empty($filters['teacher_id'])) {
            $sql .= ' AND s.teacher_id = :teacher_id';
            $params[':teacher_id'] = (int) $filters['teacher_id'];
        }
        if (!empty($filters['day_of_week'])) {
            $sql .= ' AND s.day_of_week = :day_of_week';
            $params[':day_of_week'] = (int) $filters['day_of_week'];
        }
        if (!empty($filters['search'])) {
            $q = '%' . trim((string) $filters['search']) . '%';
            $sql .= ' AND (s.subject_name LIKE :q1 OR s.room LIKE :q2 OR t.full_name LIKE :q3 OR b.branch_name LIKE :q4)';
            $params[':q1'] = $q;
            $params[':q2'] = $q;
            $params[':q3'] = $q;
            $params[':q4'] = $q;
        }
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $sql .= ' AND s.start_date <= :date_to AND s.end_date >= :date_from';
            $params[':date_from'] = (string) $filters['date_from'];
            $params[':date_to']   = (string) $filters['date_to'];
        }

        $sql .= ' ORDER BY s.day_of_week ASC, s.start_period ASC, b.code ASC, s.room ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByTeacher(int $teacherId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $filters = ['teacher_id' => $teacherId];
        if ($dateFrom !== null && $dateTo !== null) {
            $filters['date_from'] = $dateFrom;
            $filters['date_to']   = $dateTo;
        }
        return $this->findAll($filters);
    }

    /**
     * Find conflicting schedules for a teacher with optional row-level FOR UPDATE lock.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findTeacherConflicts(
        int $teacherId,
        int $dayOfWeek,
        int $startPeriod,
        int $endPeriod,
        string $startDate,
        string $endDate,
        ?int $excludeId = null,
        bool $forUpdate = false
    ): array {
        $sql = 'SELECT s.*, b.branch_name, b.code AS branch_code, t.full_name AS teacher_name
                FROM schedules s
                INNER JOIN branches b ON b.id = s.branch_id
                INNER JOIN teachers t ON t.id = s.teacher_id
                WHERE s.teacher_id = :teacher_id
                  AND s.day_of_week = :day_of_week
                  AND s.start_period <= :end_period
                  AND s.end_period >= :start_period
                  AND s.start_date <= :end_date
                  AND s.end_date >= :start_date';

        $params = [
            ':teacher_id'   => $teacherId,
            ':day_of_week'  => $dayOfWeek,
            ':start_period' => $startPeriod,
            ':end_period'   => $endPeriod,
            ':start_date'   => $startDate,
            ':end_date'     => $endDate,
        ];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND s.id != :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Find conflicting schedules for a room at a branch with optional row-level FOR UPDATE lock.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findRoomConflicts(
        int $branchId,
        string $room,
        int $dayOfWeek,
        int $startPeriod,
        int $endPeriod,
        string $startDate,
        string $endDate,
        ?int $excludeId = null,
        bool $forUpdate = false
    ): array {
        $sql = 'SELECT s.*, b.branch_name, b.code AS branch_code, t.full_name AS teacher_name
                FROM schedules s
                INNER JOIN branches b ON b.id = s.branch_id
                INNER JOIN teachers t ON t.id = s.teacher_id
                WHERE s.branch_id = :branch_id
                  AND LOWER(TRIM(s.room)) = LOWER(TRIM(:room))
                  AND s.day_of_week = :day_of_week
                  AND s.start_period <= :end_period
                  AND s.end_period >= :start_period
                  AND s.start_date <= :end_date
                  AND s.end_date >= :start_date';

        $params = [
            ':branch_id'    => $branchId,
            ':room'         => trim($room),
            ':day_of_week'  => $dayOfWeek,
            ':start_period' => $startPeriod,
            ':end_period'   => $endPeriod,
            ':start_date'   => $startDate,
            ':end_date'     => $endDate,
        ];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND s.id != :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Lock all schedules of a teacher on a day (prevents concurrent insertion race conditions).
     */
    public function lockTeacherDayRows(int $teacherId, int $dayOfWeek): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM schedules WHERE teacher_id = :teacher_id AND day_of_week = :day_of_week FOR UPDATE'
        );
        $stmt->execute([
            ':teacher_id'  => $teacherId,
            ':day_of_week' => $dayOfWeek,
        ]);
        $stmt->fetchAll();

        // Also lock the parent teacher row to serialize concurrent first-time inserts for the teacher
        $tStmt = $this->pdo->prepare('SELECT id FROM teachers WHERE id = :id FOR UPDATE');
        $tStmt->execute([':id' => $teacherId]);
        $tStmt->fetch();
    }

    /**
     * Lock branch row + room rows on a day to serialize concurrent room bookings.
     */
    public function lockBranchRoomDayRows(int $branchId, string $room, int $dayOfWeek): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM schedules
             WHERE branch_id = :branch_id AND LOWER(TRIM(room)) = LOWER(TRIM(:room)) AND day_of_week = :day_of_week
             FOR UPDATE'
        );
        $stmt->execute([
            ':branch_id'   => $branchId,
            ':room'        => trim($room),
            ':day_of_week' => $dayOfWeek,
        ]);
        $stmt->fetchAll();

        $bStmt = $this->pdo->prepare('SELECT id FROM branches WHERE id = :id FOR UPDATE');
        $bStmt->execute([':id' => $branchId]);
        $bStmt->fetch();
    }

    /**
     * Find all schedules of a teacher on a given day overlapping a date range.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findTeacherDaySchedules(
        int $teacherId,
        int $dayOfWeek,
        string $startDate,
        string $endDate,
        ?int $excludeId = null
    ): array {
        $sql = 'SELECT s.*, b.branch_name, b.code AS branch_code, b.address AS branch_address, b.color_hex AS branch_color
                FROM schedules s
                INNER JOIN branches b ON b.id = s.branch_id
                WHERE s.teacher_id = :teacher_id
                  AND s.day_of_week = :day_of_week
                  AND s.start_date <= :end_date
                  AND s.end_date >= :start_date';
        $params = [
            ':teacher_id'  => $teacherId,
            ':day_of_week' => $dayOfWeek,
            ':start_date'  => $startDate,
            ':end_date'    => $endDate,
        ];
        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND s.id != :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }
        $sql .= ' ORDER BY s.start_period ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO schedules
                (teacher_id, branch_id, subject_name, room, day_of_week, shift_or_periods, start_period, end_period, start_date, end_date, notes)
             VALUES
                (:teacher_id, :branch_id, :subject_name, :room, :day_of_week, :shift_or_periods, :start_period, :end_period, :start_date, :end_date, :notes)'
        );
        $stmt->execute([
            ':teacher_id'       => (int) $data['teacher_id'],
            ':branch_id'        => (int) $data['branch_id'],
            ':subject_name'     => trim((string) $data['subject_name']),
            ':room'             => strtoupper(trim((string) $data['room'])),
            ':day_of_week'      => (int) $data['day_of_week'],
            ':shift_or_periods' => trim((string) $data['shift_or_periods']),
            ':start_period'     => (int) $data['start_period'],
            ':end_period'       => (int) $data['end_period'],
            ':start_date'       => (string) $data['start_date'],
            ':end_date'         => (string) $data['end_date'],
            ':notes'            => isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE schedules
             SET teacher_id = :teacher_id,
                 branch_id = :branch_id,
                 subject_name = :subject_name,
                 room = :room,
                 day_of_week = :day_of_week,
                 shift_or_periods = :shift_or_periods,
                 start_period = :start_period,
                 end_period = :end_period,
                 start_date = :start_date,
                 end_date = :end_date,
                 notes = :notes
             WHERE id = :id'
        );
        return $stmt->execute([
            ':id'               => $id,
            ':teacher_id'       => (int) $data['teacher_id'],
            ':branch_id'        => (int) $data['branch_id'],
            ':subject_name'     => trim((string) $data['subject_name']),
            ':room'             => strtoupper(trim((string) $data['room'])),
            ':day_of_week'      => (int) $data['day_of_week'],
            ':shift_or_periods' => trim((string) $data['shift_or_periods']),
            ':start_period'     => (int) $data['start_period'],
            ':end_period'       => (int) $data['end_period'],
            ':start_date'       => (string) $data['start_date'],
            ':end_date'         => (string) $data['end_date'],
            ':notes'            => isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM schedules WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Sum assigned periods per teacher (excluding optional schedule id).
     *
     * @return array<int, int> teacher_id => total_periods
     */
    public function getAssignedPeriodsByTeacher(?int $excludeId = null): array
    {
        $sql = 'SELECT teacher_id, SUM(end_period - start_period + 1) AS total_periods
                FROM schedules';
        $params = [];
        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' WHERE id != :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }
        $sql .= ' GROUP BY teacher_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['teacher_id']] = (int) $row['total_periods'];
        }
        return $map;
    }

    /**
     * Return distinct room codes observed at a branch, merged with default campus room catalog.
     *
     * @return string[]
     */
    public function getKnownRoomsByBranch(int $branchId, string $branchCode = ''): array
    {
        $stmt = $this->pdo->prepare('SELECT DISTINCT room FROM schedules WHERE branch_id = :branch_id ORDER BY room ASC');
        $stmt->execute([':branch_id' => $branchId]);
        $dbRooms = array_map(static fn(array $r): string => strtoupper(trim((string) $r['room'])), $stmt->fetchAll());

        $catalog = match (strtoupper($branchCode)) {
            'CS1'   => ['A201', 'A202', 'A301', 'A302', 'A303', 'A401', 'A402', 'B101', 'B204', 'B205', 'B301', 'B305', 'B306', 'B404'],
            'CS2'   => ['C101', 'C102', 'C103', 'C201', 'C202', 'C203', 'C204', 'C301'],
            'CS3'   => ['PM01', 'PM02', 'PM03', 'PM04', 'PM05', 'PM06', 'LAB01', 'LAB02'],
            'CS4'   => ['D101', 'D102', 'D104', 'D201', 'D203', 'D205', 'D301'],
            default => ['P101', 'P102', 'P201', 'P202', 'PM01', 'PM02'],
        };

        $merged = array_values(array_unique(array_merge($catalog, $dbRooms)));
        sort($merged, SORT_NATURAL);
        return $merged;
    }

    /**
     * Find all occupied rooms at a branch during a specific slot.
     *
     * @return array<string, array<string, mixed>> roomCode => conflicting schedule row
     */
    public function findOccupiedRoomsBySlot(
        int $branchId,
        int $dayOfWeek,
        int $startPeriod,
        int $endPeriod,
        string $startDate,
        string $endDate,
        ?int $excludeId = null
    ): array {
        $sql = 'SELECT s.room, s.subject_name, s.shift_or_periods, t.full_name AS teacher_name
                FROM schedules s
                INNER JOIN teachers t ON t.id = s.teacher_id
                WHERE s.branch_id = :branch_id
                  AND s.day_of_week = :day_of_week
                  AND s.start_period <= :end_period
                  AND s.end_period >= :start_period
                  AND s.start_date <= :end_date
                  AND s.end_date >= :start_date';
        $params = [
            ':branch_id'    => $branchId,
            ':day_of_week'  => $dayOfWeek,
            ':start_period' => $startPeriod,
            ':end_period'   => $endPeriod,
            ':start_date'   => $startDate,
            ':end_date'     => $endDate,
        ];
        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND s.id != :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $occupied = [];
        foreach ($stmt->fetchAll() as $row) {
            $code = strtoupper(trim((string) $row['room']));
            $occupied[$code] = $row;
        }
        return $occupied;
    }
}
