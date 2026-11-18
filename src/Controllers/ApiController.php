<?php

declare(strict_types=1);

namespace HuitSchedule\Controllers;

use HuitSchedule\Core\Response;
use HuitSchedule\Repositories\BranchRepository;
use HuitSchedule\Repositories\ScheduleRepository;
use HuitSchedule\Services\AnalyticsService;
use HuitSchedule\Services\ConflictDetectorService;
use HuitSchedule\Services\ScheduleOptimizerService;
use HuitSchedule\Services\TeacherRecommendationService;
use InvalidArgumentException;

final class ApiController
{
    /**
     * GET /api/suggest-teachers
     */
    public function suggestTeachers(): void
    {
        try {
            $context = [
                'subject'      => trim((string) ($_GET['subject'] ?? '')),
                'branch_id'    => (int) ($_GET['branch_id'] ?? 0),
                'day_of_week'  => (int) ($_GET['day_of_week'] ?? 2),
                'start_period' => (int) ($_GET['start_period'] ?? 1),
                'end_period'   => (int) ($_GET['end_period'] ?? 3),
                'start_date'   => (string) ($_GET['start_date'] ?? date('Y-m-01')),
                'end_date'     => (string) ($_GET['end_date'] ?? date('Y-m-t', strtotime('+2 months'))),
                'exclude_id'   => !empty($_GET['exclude_id']) ? (int) $_GET['exclude_id'] : null,
            ];

            $service = new TeacherRecommendationService();
            $result  = $service->suggestForSlot($context);

            Response::jsonSuccess($result, [
                'recommended_count' => count($result['recommended']),
                'unavailable_count' => count($result['unavailable']),
            ]);
        } catch (InvalidArgumentException $e) {
            Response::jsonError($e->getMessage(), 422);
        }
    }

    /**
     * GET /api/check-conflict
     */
    public function checkConflict(): void
    {
        try {
            $payload = [
                'teacher_id'   => (int) ($_GET['teacher_id'] ?? 0),
                'branch_id'    => (int) ($_GET['branch_id'] ?? 0),
                'room'         => trim((string) ($_GET['room'] ?? '')),
                'day_of_week'  => (int) ($_GET['day_of_week'] ?? 2),
                'start_period' => (int) ($_GET['start_period'] ?? 1),
                'end_period'   => (int) ($_GET['end_period'] ?? 3),
                'start_date'   => (string) ($_GET['start_date'] ?? date('Y-m-01')),
                'end_date'     => (string) ($_GET['end_date'] ?? date('Y-m-t', strtotime('+2 months'))),
            ];
            $excludeId = !empty($_GET['exclude_id']) ? (int) $_GET['exclude_id'] : null;

            $detector = new ConflictDetectorService();
            $hard = $detector->checkConflicts($payload, $excludeId, false);

            // Also run soft travel & dead-gap evaluation for selected teacher
            $warnings = [];
            $optBadges = [];
            if ($payload['teacher_id'] > 0 && $payload['branch_id'] > 0 && !$hard['has_conflict']) {
                $schedRepo  = new ScheduleRepository();
                $branchRepo = new BranchRepository();
                $optimizer  = new ScheduleOptimizerService();

                $branch = $branchRepo->findById($payload['branch_id']);
                $daySchedules = $schedRepo->findTeacherDaySchedules(
                    $payload['teacher_id'],
                    $payload['day_of_week'],
                    $payload['start_date'],
                    $payload['end_date'],
                    $excludeId
                );

                $eval = $optimizer->evaluateCandidateInDay(
                    array_merge($payload, ['branch_code' => $branch['code'] ?? ('CS' . $payload['branch_id'])]),
                    $daySchedules,
                    $branchRepo->getTravelMatrix()
                );

                $warnings  = $eval['warnings'];
                $optBadges = $eval['badges'];
            }

            Response::jsonSuccess([
                'has_conflict'      => $hard['has_conflict'],
                'teacher_conflicts' => $hard['teacher_conflicts'],
                'room_conflicts'    => $hard['room_conflicts'],
                'messages'          => $hard['messages'],
                'warnings'          => $warnings,
                'badges'            => $optBadges,
            ]);
        } catch (InvalidArgumentException $e) {
            Response::jsonError($e->getMessage(), 422);
        }
    }

    /**
     * GET /api/suggest-slots
     */
    public function suggestSlots(): void
    {
        $teacherId = (int) ($_GET['teacher_id'] ?? 0);
        $branchId  = (int) ($_GET['branch_id'] ?? 0);
        $duration  = (int) ($_GET['duration'] ?? 3);
        $startDate = (string) ($_GET['start_date'] ?? date('Y-m-01'));
        $endDate   = (string) ($_GET['end_date'] ?? date('Y-m-t', strtotime('+2 months')));
        $excludeId = !empty($_GET['exclude_id']) ? (int) $_GET['exclude_id'] : null;

        if ($teacherId <= 0 || $branchId <= 0) {
            Response::jsonError('Vui lòng chọn Giảng viên và Cơ sở để gợi ý 5 ô tiết trống tối ưu.', 422);
            return;
        }

        try {
            $optimizer = new ScheduleOptimizerService();
            $slots = $optimizer->suggestOptimalSlots($teacherId, $branchId, $duration, $startDate, $endDate, $excludeId, 5);
            Response::jsonSuccess(['slots' => $slots]);
        } catch (InvalidArgumentException $e) {
            Response::jsonError($e->getMessage(), 422);
        }
    }

    /**
     * GET /api/dashboard-stats
     */
    public function dashboardStats(): void
    {
        $filters = [];
        if (!empty($_GET['branch_id'])) {
            $filters['branch_id'] = (int) $_GET['branch_id'];
        }
        if (!empty($_GET['teacher_id'])) {
            $filters['teacher_id'] = (int) $_GET['teacher_id'];
        }
        if (!empty($_GET['day_of_week'])) {
            $filters['day_of_week'] = (int) $_GET['day_of_week'];
        }
        if (!empty($_GET['search'])) {
            $filters['search'] = trim((string) $_GET['search']);
        }

        $analytics = new AnalyticsService();
        $data = $analytics->getDashboardData($filters);

        Response::jsonSuccess($data);
    }

    /**
     * GET /api/available-rooms
     */
    public function availableRooms(): void
    {
        $branchId    = (int) ($_GET['branch_id'] ?? 0);
        $dayOfWeek   = (int) ($_GET['day_of_week'] ?? 2);
        $startPeriod = (int) ($_GET['start_period'] ?? 1);
        $endPeriod   = (int) ($_GET['end_period'] ?? 3);
        $startDate   = (string) ($_GET['start_date'] ?? date('Y-m-01'));
        $endDate     = (string) ($_GET['end_date'] ?? date('Y-m-t', strtotime('+2 months')));
        $excludeId   = !empty($_GET['exclude_id']) ? (int) $_GET['exclude_id'] : null;

        if ($branchId <= 0) {
            Response::jsonSuccess(['available' => [], 'occupied' => []]);
            return;
        }

        try {
            new \HuitSchedule\Domain\TimeSlot($dayOfWeek, $startPeriod, $endPeriod, $startDate, $endDate);
        } catch (InvalidArgumentException $e) {
            Response::jsonError($e->getMessage(), 422);
            return;
        }

        $branchRepo = new BranchRepository();
        $schedRepo  = new ScheduleRepository();

        $branch = $branchRepo->findById($branchId);
        if ($branch === null) {
            Response::jsonError('Cơ sở không tồn tại.', 404);
            return;
        }

        $allRooms = $schedRepo->getKnownRoomsByBranch($branchId, (string) $branch['code']);
        $occupiedMap = $schedRepo->findOccupiedRoomsBySlot(
            $branchId,
            $dayOfWeek,
            $startPeriod,
            $endPeriod,
            $startDate,
            $endDate,
            $excludeId
        );

        $available = [];
        $occupied  = [];

        foreach ($allRooms as $roomCode) {
            $isLab = str_starts_with($roomCode, 'PM') || str_starts_with($roomCode, 'LAB');
            $roomType = $isLab ? 'Phòng máy' : 'Lý thuyết';
            if (isset($occupiedMap[$roomCode])) {
                $occ = $occupiedMap[$roomCode];
                $occupied[] = [
                    'room'         => $roomCode,
                    'type'         => $roomType,
                    'subject_name' => (string) $occ['subject_name'],
                    'teacher_name' => (string) $occ['teacher_name'],
                    'shift'        => (string) $occ['shift_or_periods'],
                ];
            } else {
                $available[] = [
                    'room' => $roomCode,
                    'type' => $roomType,
                ];
            }
        }

        Response::jsonSuccess([
            'branch_code' => (string) $branch['code'],
            'available'   => $available,
            'occupied'    => $occupied,
        ]);
    }
}
