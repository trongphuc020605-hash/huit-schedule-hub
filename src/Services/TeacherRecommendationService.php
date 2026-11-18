<?php

declare(strict_types=1);

namespace HuitSchedule\Services;

use HuitSchedule\Domain\ExpertiseMatcher;
use HuitSchedule\Domain\TimeSlot;
use HuitSchedule\Repositories\AvailabilityRepository;
use HuitSchedule\Repositories\BranchRepository;
use HuitSchedule\Repositories\ScheduleRepository;
use HuitSchedule\Repositories\TeacherRepository;

final class TeacherRecommendationService
{
    private ExpertiseMatcher $matcher;
    private ScheduleOptimizerService $optimizer;

    public function __construct(
        ?ExpertiseMatcher $matcher = null,
        ?ScheduleOptimizerService $optimizer = null
    ) {
        $this->matcher   = $matcher ?? new ExpertiseMatcher();
        $this->optimizer = $optimizer ?? new ScheduleOptimizerService();
    }

    /**
     * Fetch data from DB and rank available teachers for a scheduling slot.
     *
     * @param array<string, mixed> $context
     * @return array{recommended: array<int, array<string, mixed>>, unavailable: array<int, array<string, mixed>>}
     */
    public function suggestForSlot(array $context): array
    {
        $teacherRepo  = new TeacherRepository();
        $scheduleRepo = new ScheduleRepository();
        $availRepo    = new AvailabilityRepository();
        $branchRepo   = new BranchRepository();

        $teachers     = $teacherRepo->all('teacher');
        $allSchedules = $scheduleRepo->findAll();
        $availByTeach = $availRepo->allGroupedByTeacher();
        $travelMatrix = $branchRepo->getTravelMatrix();

        $branchId = (int) ($context['branch_id'] ?? 0);
        if ($branchId > 0 && empty($context['branch_code'])) {
            $branch = $branchRepo->findById($branchId);
            if ($branch !== null) {
                $context['branch_code'] = (string) $branch['code'];
            }
        }

        return $this->rankCandidates($context, $teachers, $allSchedules, $availByTeach, $travelMatrix);
    }

    /**
     * Pure multi-objective teacher scoring (0-100) with transparent explainability.
     * Hard-excludes busy teachers into `unavailable` with conflict reason.
     *
     * @param array<string, mixed> $context
     * @param array<int, array<string, mixed>> $teachers
     * @param array<int, array<string, mixed>> $allSchedules
     * @param array<int, array<int, array<string, mixed>>> $availByTeacher
     * @param array<int, array<int, array{travel_minutes: int, distance_km?: float}>> $travelMatrix
     * @return array{recommended: array<int, array<string, mixed>>, unavailable: array<int, array<string, mixed>>}
     */
    public function rankCandidates(
        array $context,
        array $teachers,
        array $allSchedules,
        array $availByTeacher,
        array $travelMatrix
    ): array {
        $subject     = trim((string) ($context['subject'] ?? $context['subject_name'] ?? ''));
        $branchId    = (int) ($context['branch_id'] ?? 0);
        $branchCode  = (string) ($context['branch_code'] ?? ('CS' . $branchId));
        $dayOfWeek   = (int) ($context['day_of_week'] ?? 2);
        $startPeriod = (int) ($context['start_period'] ?? 1);
        $endPeriod   = (int) ($context['end_period'] ?? 3);
        $startDate   = (string) ($context['start_date'] ?? date('Y-m-01'));
        $endDate     = (string) ($context['end_date'] ?? date('Y-m-t', strtotime('+2 months')));
        $excludeId   = isset($context['exclude_id']) && (int) $context['exclude_id'] > 0
            ? (int) $context['exclude_id']
            : null;

        $candSlot = new TimeSlot($dayOfWeek, $startPeriod, $endPeriod, $startDate, $endDate);

        // Index schedules by teacher (excluding excludeId)
        $schedulesByTeacher = [];
        $periodsByTeacher   = [];
        foreach ($allSchedules as $s) {
            if ($excludeId !== null && (int) ($s['id'] ?? 0) === $excludeId) {
                continue;
            }
            $tid = (int) $s['teacher_id'];
            $schedulesByTeacher[$tid][] = $s;
            $periodsByTeacher[$tid] = ($periodsByTeacher[$tid] ?? 0)
                + ((int) $s['end_period'] - (int) $s['start_period'] + 1);
        }

        $recommended = [];
        $unavailable = [];

        foreach ($teachers as $teacher) {
            $tid = (int) $teacher['id'];
            $tSchedules = $schedulesByTeacher[$tid] ?? [];
            $assignedPeriods = $periodsByTeacher[$tid] ?? 0;
            $maxPeriods = max(1, (int) ($teacher['max_periods_per_week'] ?? 18));

            // Hard constraint check: is teacher busy in this TimeSlot?
            $busyConflict = null;
            $daySchedules = [];
            foreach ($tSchedules as $s) {
                $sSlot = TimeSlot::fromArray($s);
                if ($candSlot->overlaps($sSlot)) {
                    $busyConflict = $s;
                    break;
                }
                if ((int) $s['day_of_week'] === $dayOfWeek && $candSlot->overlapsDates($sSlot)) {
                    $daySchedules[] = $s;
                }
            }

            if ($busyConflict !== null) {
                $unavailable[] = [
                    'teacher_id'           => $tid,
                    'full_name'            => (string) $teacher['full_name'],
                    'email'                => (string) ($teacher['email'] ?? ''),
                    'expertise'            => (string) ($teacher['expertise'] ?? ''),
                    'assigned_periods'     => $assignedPeriods,
                    'max_periods_per_week' => $maxPeriods,
                    'reason'               => sprintf(
                        'Bận dạy "%s" tại %s - Phòng %s (%s)',
                        $busyConflict['subject_name'],
                        $busyConflict['branch_code'] ?? ('CS' . $busyConflict['branch_id']),
                        $busyConflict['room'],
                        $busyConflict['shift_or_periods']
                    ),
                ];
                continue;
            }

            $badges = [];
            $warnings = [];

            // Criterion 1: Expertise match (0..40)
            $expMatch = $this->matcher->match($subject, (string) ($teacher['expertise'] ?? ''));
            $expScore = $expMatch['score'];
            if ($expMatch['level'] === 'direct') {
                $badges[] = 'Đúng chuyên môn (' . implode(', ', array_slice($expMatch['matched_keywords'], 0, 2)) . ')';
            } elseif ($expMatch['level'] === 'cluster') {
                $badges[] = 'Cùng nhóm chuyên ngành';
            } elseif ($expMatch['level'] === 'partial') {
                $badges[] = 'Khớp một phần chuyên môn';
            }

            // Criterion 2: Registered Availability match (0..25)
            $availScore = 0;
            $matchedAvail = false;
            $matchedBranchPref = false;
            foreach ($availByTeacher[$tid] ?? [] as $av) {
                if ((int) $av['day_of_week'] === $dayOfWeek
                    && (int) $av['start_period'] <= $startPeriod
                    && (int) $av['end_period'] >= $endPeriod) {
                    $matchedAvail = true;
                    $availScore = max($availScore, 20);
                    if ($branchId > 0 && isset($av['preferred_branch_id']) && (int) $av['preferred_branch_id'] === $branchId) {
                        $matchedBranchPref = true;
                        $availScore = 25;
                        break;
                    }
                }
            }
            if ($matchedAvail) {
                $badges[] = $matchedBranchPref
                    ? 'Khớp tiết trống + cơ sở ưu tiên'
                    : 'Khớp tiết trống đã đăng ký';
            }

            // Criterion 3: Commute & Dead-Gap Optimization (-25..+20)
            $optEval = $this->optimizer->evaluateCandidateInDay([
                'teacher_id'   => $tid,
                'branch_id'    => $branchId,
                'branch_code'  => $branchCode,
                'day_of_week'  => $dayOfWeek,
                'start_period' => $startPeriod,
                'end_period'   => $endPeriod,
                'start_date'   => $startDate,
                'end_date'     => $endDate,
            ], $daySchedules, $travelMatrix);

            $optScore = $optEval['score'];
            foreach ($optEval['badges'] as $b) {
                $badges[] = $b;
            }
            foreach ($optEval['warnings'] as $w) {
                $warnings[] = [
                    'type'     => $w['type'],
                    'severity' => $w['severity'],
                    'message'  => $w['message'],
                ];
            }

            // Criterion 4: Weekly Workload Balance (0..15)
            $loadRatio = $assignedPeriods / $maxPeriods;
            $loadScore = (int) round(15.0 * max(0.0, 1.0 - $loadRatio));
            if ($assignedPeriods + $candSlot->periodCount() > $maxPeriods) {
                $warnings[] = [
                    'type'     => 'OVERLOAD',
                    'severity' => 'warning',
                    'message'  => sprintf(
                        'Vượt định mức tuần (%d/%d tiết sau khi thêm)',
                        $assignedPeriods + $candSlot->periodCount(),
                        $maxPeriods
                    ),
                ];
            }

            $totalScore = max(0, min(100, $expScore + $availScore + $optScore + $loadScore));

            // Sort day context chronologically
            usort($daySchedules, static fn(array $a, array $b): int => ((int) $a['start_period']) <=> ((int) $b['start_period']));
            $dayContext = array_map(static function (array $s): array {
                $slot = TimeSlot::fromArray($s);
                return [
                    'id'           => (int) ($s['id'] ?? 0),
                    'subject_name' => (string) $s['subject_name'],
                    'branch_code'  => (string) ($s['branch_code'] ?? ('CS' . $s['branch_id'])),
                    'branch_color' => (string) ($s['branch_color'] ?? '#1D4ED8'),
                    'room'         => (string) $s['room'],
                    'shift'        => (string) $s['shift_or_periods'],
                    'time_range'   => $slot->timeRange(),
                ];
            }, $daySchedules);

            $recommended[] = [
                'teacher_id'           => $tid,
                'full_name'            => (string) $teacher['full_name'],
                'email'                => (string) ($teacher['email'] ?? ''),
                'expertise'            => (string) ($teacher['expertise'] ?? ''),
                'score'                => $totalScore,
                'tier'                 => $totalScore >= 75 ? 'excellent' : ($totalScore >= 50 ? 'good' : 'fair'),
                'breakdown'            => [
                    'expertise'    => $expScore,
                    'availability' => $availScore,
                    'optimization' => $optScore,
                    'workload'     => $loadScore,
                ],
                'expertise_reason'     => $expMatch['reason'],
                'badges'               => array_values(array_unique($badges)),
                'warnings'             => $warnings,
                'day_context'          => $dayContext,
                'assigned_periods'     => $assignedPeriods,
                'max_periods_per_week' => $maxPeriods,
            ];
        }

        usort($recommended, static function (array $a, array $b): int {
            if ($b['score'] !== $a['score']) {
                return $b['score'] <=> $a['score'];
            }
            if ($b['breakdown']['expertise'] !== $a['breakdown']['expertise']) {
                return $b['breakdown']['expertise'] <=> $a['breakdown']['expertise'];
            }
            return $a['assigned_periods'] <=> $b['assigned_periods'];
        });

        return [
            'recommended' => $recommended,
            'unavailable' => $unavailable,
        ];
    }
}
