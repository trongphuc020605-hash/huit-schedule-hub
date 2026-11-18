<?php

declare(strict_types=1);

namespace HuitSchedule\Services;

use HuitSchedule\Core\Database;
use HuitSchedule\Domain\TimeSlot;
use HuitSchedule\Repositories\ScheduleRepository;
use InvalidArgumentException;
use PDO;

final class ConflictDetectorService
{
    private ScheduleRepository $scheduleRepo;

    public function __construct(?ScheduleRepository $scheduleRepo = null)
    {
        $this->scheduleRepo = $scheduleRepo ?? new ScheduleRepository();
    }

    /**
     * Check hard conflicts: (1) teacher double-booking and (2) room double-booking at the same branch.
     *
     * @param array<string, mixed> $data
     * @return array{
     *     has_conflict: bool,
     *     teacher_conflicts: array<int, array<string, mixed>>,
     *     room_conflicts: array<int, array<string, mixed>>,
     *     messages: string[]
     * }
     */
    public function checkConflicts(array $data, ?int $excludeId = null, bool $forUpdate = false): array
    {
        $teacherId   = (int) ($data['teacher_id'] ?? 0);
        $branchId    = (int) ($data['branch_id'] ?? 0);
        $room        = trim((string) ($data['room'] ?? ''));
        $dayOfWeek   = (int) ($data['day_of_week'] ?? 0);
        $startPeriod = (int) ($data['start_period'] ?? 0);
        $endPeriod   = (int) ($data['end_period'] ?? 0);
        $startDate   = (string) ($data['start_date'] ?? '');
        $endDate     = (string) ($data['end_date'] ?? '');

        // Validate TimeSlot invariants
        new TimeSlot($dayOfWeek, $startPeriod, $endPeriod, $startDate, $endDate);

        $teacherConflicts = [];
        if ($teacherId > 0) {
            $teacherConflicts = $this->scheduleRepo->findTeacherConflicts(
                $teacherId,
                $dayOfWeek,
                $startPeriod,
                $endPeriod,
                $startDate,
                $endDate,
                $excludeId,
                $forUpdate
            );
        }

        $roomConflicts = [];
        if ($branchId > 0 && $room !== '') {
            $roomConflicts = $this->scheduleRepo->findRoomConflicts(
                $branchId,
                $room,
                $dayOfWeek,
                $startPeriod,
                $endPeriod,
                $startDate,
                $endDate,
                $excludeId,
                $forUpdate
            );
        }

        $messages = [];
        foreach ($teacherConflicts as $c) {
            $messages[] = sprintf(
                'Trùng lịch giảng viên %s: đang dạy "%s" tại %s - Phòng %s (%s, %s đến %s).',
                $c['teacher_name'],
                $c['subject_name'],
                $c['branch_code'],
                $c['room'],
                $c['shift_or_periods'],
                $c['start_date'],
                $c['end_date']
            );
        }

        foreach ($roomConflicts as $c) {
            $messages[] = sprintf(
                'Trùng phòng %s tại %s: đã có lớp "%s" của GV %s (%s, %s đến %s).',
                $c['room'],
                $c['branch_code'],
                $c['subject_name'],
                $c['teacher_name'],
                $c['shift_or_periods'],
                $c['start_date'],
                $c['end_date']
            );
        }

        return [
            'has_conflict'      => $teacherConflicts !== [] || $roomConflicts !== [],
            'teacher_conflicts' => $teacherConflicts,
            'room_conflicts'    => $roomConflicts,
            'messages'          => $messages,
        ];
    }

    /**
     * Atomically create a schedule inside a MySQL transaction with SELECT ... FOR UPDATE row locking.
     *
     * @param array<string, mixed> $data
     * @return array{ok: bool, id?: int, conflicts?: array<string, mixed>, errors?: string[]}
     */
    public function createScheduleAtomic(array $data): array
    {
        $validationErrors = $this->validatePayload($data);
        if ($validationErrors !== []) {
            return ['ok' => false, 'errors' => $validationErrors];
        }

        $data['shift_or_periods'] = $this->normalizeShiftLabel($data);

        return Database::transaction(function (PDO $pdo) use ($data): array {
            $repo = new ScheduleRepository($pdo);
            $detector = new self($repo);

            // Acquire row-level locks to eliminate concurrent scheduling race conditions
            $repo->lockTeacherDayRows((int) $data['teacher_id'], (int) $data['day_of_week']);
            $repo->lockBranchRoomDayRows((int) $data['branch_id'], (string) $data['room'], (int) $data['day_of_week']);

            $check = $detector->checkConflicts($data, null, true);
            if ($check['has_conflict']) {
                return [
                    'ok'        => false,
                    'conflicts' => $check,
                    'errors'    => $check['messages'],
                ];
            }

            $id = $repo->create($data);
            return [
                'ok' => true,
                'id' => $id,
            ];
        });
    }

    /**
     * Atomically update a schedule inside a MySQL transaction with SELECT ... FOR UPDATE row locking.
     *
     * @param array<string, mixed> $data
     * @return array{ok: bool, id?: int, conflicts?: array<string, mixed>, errors?: string[]}
     */
    public function updateScheduleAtomic(int $id, array $data): array
    {
        $validationErrors = $this->validatePayload($data);
        if ($validationErrors !== []) {
            return ['ok' => false, 'errors' => $validationErrors];
        }

        $data['shift_or_periods'] = $this->normalizeShiftLabel($data);

        return Database::transaction(function (PDO $pdo) use ($id, $data): array {
            $repo = new ScheduleRepository($pdo);
            $detector = new self($repo);

            $repo->lockTeacherDayRows((int) $data['teacher_id'], (int) $data['day_of_week']);
            $repo->lockBranchRoomDayRows((int) $data['branch_id'], (string) $data['room'], (int) $data['day_of_week']);

            $check = $detector->checkConflicts($data, $id, true);
            if ($check['has_conflict']) {
                return [
                    'ok'        => false,
                    'conflicts' => $check,
                    'errors'    => $check['messages'],
                ];
            }

            $repo->update($id, $data);
            return [
                'ok' => true,
                'id' => $id,
            ];
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return string[]
     */
    public function validatePayload(array $data): array
    {
        $errors = [];

        if ((int) ($data['teacher_id'] ?? 0) <= 0) {
            $errors[] = 'Vui lòng chọn giảng viên.';
        }
        if ((int) ($data['branch_id'] ?? 0) <= 0) {
            $errors[] = 'Vui lòng chọn cơ sở giảng dạy.';
        }
        if (trim((string) ($data['subject_name'] ?? '')) === '') {
            $errors[] = 'Vui lòng nhập tên môn học.';
        }
        if (trim((string) ($data['room'] ?? '')) === '') {
            $errors[] = 'Vui lòng nhập mã phòng học.';
        }

        try {
            new TimeSlot(
                (int) ($data['day_of_week'] ?? 0),
                (int) ($data['start_period'] ?? 0),
                (int) ($data['end_period'] ?? 0),
                (string) ($data['start_date'] ?? ''),
                (string) ($data['end_date'] ?? '')
            );
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function normalizeShiftLabel(array $data): string
    {
        $provided = trim((string) ($data['shift_or_periods'] ?? ''));
        if ($provided !== '') {
            return $provided;
        }
        $slot = new TimeSlot(
            (int) $data['day_of_week'],
            (int) $data['start_period'],
            (int) $data['end_period'],
            (string) $data['start_date'],
            (string) $data['end_date']
        );
        return $slot->shiftLabel();
    }
}
