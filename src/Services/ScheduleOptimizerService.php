<?php

declare(strict_types=1);

namespace HuitSchedule\Services;

use HuitSchedule\Domain\TimeSlot;
use HuitSchedule\Repositories\AvailabilityRepository;
use HuitSchedule\Repositories\BranchRepository;
use HuitSchedule\Repositories\ScheduleRepository;

final class ScheduleOptimizerService
{
    private const TIGHT_BUFFER_MINUTES = 15;
    private const DEAD_GAP_THRESHOLD   = 3;
    private const MAX_COMMUTES_PER_DAY = 2;
    private const DEFAULT_TRAVEL_MIN   = 20;

    /**
     * Lookup travel minutes between two branches from the travel matrix.
     *
     * @param array<int, array<int, array{travel_minutes: int, distance_km?: float}>> $travelMatrix
     */
    public function travelMinutes(int $fromBranchId, int $toBranchId, array $travelMatrix): int
    {
        if ($fromBranchId === $toBranchId) {
            return 0;
        }
        if (isset($travelMatrix[$fromBranchId][$toBranchId]['travel_minutes'])) {
            return (int) $travelMatrix[$fromBranchId][$toBranchId]['travel_minutes'];
        }
        if (isset($travelMatrix[$toBranchId][$fromBranchId]['travel_minutes'])) {
            return (int) $travelMatrix[$toBranchId][$fromBranchId]['travel_minutes'];
        }
        return self::DEFAULT_TRAVEL_MIN;
    }

    /**
     * Lookup distance in km between two branches.
     *
     * @param array<int, array<int, array{travel_minutes: int, distance_km?: float}>> $travelMatrix
     */
    public function travelDistanceKm(int $fromBranchId, int $toBranchId, array $travelMatrix): float
    {
        if ($fromBranchId === $toBranchId) {
            return 0.0;
        }
        if (isset($travelMatrix[$fromBranchId][$toBranchId]['distance_km'])) {
            return (float) $travelMatrix[$fromBranchId][$toBranchId]['distance_km'];
        }
        if (isset($travelMatrix[$toBranchId][$fromBranchId]['distance_km'])) {
            return (float) $travelMatrix[$toBranchId][$fromBranchId]['distance_km'];
        }
        return 5.0;
    }

    /**
     * Analyze a single teacher's schedules on a single day of week.
     *
     * @param array<int, array<string, mixed>> $daySchedules
     * @param array<int, array<int, array{travel_minutes: int, distance_km?: float}>> $travelMatrix
     * @return array{
     *     alerts: array<int, array<string, mixed>>,
     *     commute_count: int,
     *     total_travel_minutes: int,
     *     dead_gap_day: int,
     *     wasted_periods: int,
     *     legs: array<int, array<string, mixed>>
     * }
     */
    public function analyzeTeacherDay(array $daySchedules, array $travelMatrix): array
    {
        if (count($daySchedules) <= 1) {
            return [
                'alerts'               => [],
                'commute_count'        => 0,
                'total_travel_minutes' => 0,
                'dead_gap_day'         => 0,
                'wasted_periods'       => 0,
                'legs'                 => [],
            ];
        }

        usort($daySchedules, static fn(array $a, array $b): int => ((int) $a['start_period']) <=> ((int) $b['start_period']));

        $alerts = [];
        $legs = [];
        $commuteCount = 0;
        $totalTravel = 0;
        $wastedPeriods = 0;

        $count = count($daySchedules);
        for ($i = 0; $i < $count - 1; $i++) {
            $prev = $daySchedules[$i];
            $next = $daySchedules[$i + 1];

            $slotA = TimeSlot::fromArray($prev);
            $slotB = TimeSlot::fromArray($next);

            if (!$slotA->overlapsDates($slotB)) {
                continue;
            }

            $breakMin = $slotA->breakMinutes($slotB);
            $gapPeriods = $slotA->gapPeriods($slotB);
            if ($breakMin === null || $gapPeriods === null) {
                continue;
            }

            $fromBranchId = (int) $prev['branch_id'];
            $toBranchId   = (int) $next['branch_id'];
            $fromCode     = (string) ($prev['branch_code'] ?? ('CS' . $fromBranchId));
            $toCode       = (string) ($next['branch_code'] ?? ('CS' . $toBranchId));
            $teacherId    = (int) ($prev['teacher_id'] ?? 0);
            $teacherName  = (string) ($prev['teacher_name'] ?? ('GV #' . $teacherId));
            $dayOfWeek    = (int) $prev['day_of_week'];

            // 1. Travel feasibility between different campuses
            if ($fromBranchId !== $toBranchId) {
                $commuteCount++;
                $travelMin = $this->travelMinutes($fromBranchId, $toBranchId, $travelMatrix);
                $distKm = $this->travelDistanceKm($fromBranchId, $toBranchId, $travelMatrix);
                $totalTravel += $travelMin;
                $margin = $breakMin - $travelMin;

                $status = 'comfortable';
                if ($travelMin > $breakMin) {
                    $status = 'impossible';
                    $alerts[] = [
                        'type'              => 'IMPOSSIBLE_TRAVEL',
                        'severity'          => 'critical',
                        'teacher_id'        => $teacherId,
                        'teacher_name'      => $teacherName,
                        'day_of_week'       => $dayOfWeek,
                        'from_schedule_id'  => (int) ($prev['id'] ?? 0),
                        'to_schedule_id'    => (int) ($next['id'] ?? 0),
                        'target_schedule'   => $next,
                        'prev_schedule'     => $prev,
                        'from_branch_code'  => $fromCode,
                        'to_branch_code'    => $toCode,
                        'travel_minutes'    => $travelMin,
                        'break_minutes'     => $breakMin,
                        'distance_km'       => $distKm,
                        'message'           => sprintf(
                            'Cần %d phút di chuyển %s -> %s nhưng chỉ có %d phút nghỉ (%s -> %s).',
                            $travelMin,
                            $fromCode,
                            $toCode,
                            $breakMin,
                            $slotA->endTime(),
                            $slotB->startTime()
                        ),
                    ];
                } elseif ($margin < self::TIGHT_BUFFER_MINUTES) {
                    $status = 'tight';
                    $alerts[] = [
                        'type'              => 'TIGHT_TRAVEL',
                        'severity'          => 'warning',
                        'teacher_id'        => $teacherId,
                        'teacher_name'      => $teacherName,
                        'day_of_week'       => $dayOfWeek,
                        'from_schedule_id'  => (int) ($prev['id'] ?? 0),
                        'to_schedule_id'    => (int) ($next['id'] ?? 0),
                        'target_schedule'   => $next,
                        'prev_schedule'     => $prev,
                        'from_branch_code'  => $fromCode,
                        'to_branch_code'    => $toCode,
                        'travel_minutes'    => $travelMin,
                        'break_minutes'     => $breakMin,
                        'distance_km'       => $distKm,
                        'message'           => sprintf(
                            'Di chuyển sát giờ %s -> %s: cần %d phút, nghỉ %d phút (chỉ dư %d phút, rủi ro kẹt xe).',
                            $fromCode,
                            $toCode,
                            $travelMin,
                            $breakMin,
                            $margin
                        ),
                    ];
                }

                $legs[] = [
                    'from_branch_id'   => $fromBranchId,
                    'to_branch_id'     => $toBranchId,
                    'from_branch_code' => $fromCode,
                    'to_branch_code'   => $toCode,
                    'from_subject'     => (string) ($prev['subject_name'] ?? ''),
                    'to_subject'       => (string) ($next['subject_name'] ?? ''),
                    'from_time'        => $slotA->endTime(),
                    'to_time'          => $slotB->startTime(),
                    'travel_minutes'   => $travelMin,
                    'break_minutes'    => $breakMin,
                    'distance_km'      => $distKm,
                    'status'           => $status,
                ];
            }

            // 2. Dead Gap quantification (gapPeriods >= 3)
            if ($gapPeriods >= self::DEAD_GAP_THRESHOLD) {
                $wastedPeriods += $gapPeriods;
                $alerts[] = [
                    'type'              => 'DEAD_GAP',
                    'severity'          => 'warning',
                    'teacher_id'        => $teacherId,
                    'teacher_name'      => $teacherName,
                    'day_of_week'       => $dayOfWeek,
                    'from_schedule_id'  => (int) ($prev['id'] ?? 0),
                    'to_schedule_id'    => (int) ($next['id'] ?? 0),
                    'target_schedule'   => $next,
                    'prev_schedule'     => $prev,
                    'from_branch_code'  => $fromCode,
                    'to_branch_code'    => $toCode,
                    'wasted_periods'    => $gapPeriods,
                    'break_minutes'     => $breakMin,
                    'message'           => sprintf(
                        'Khoảng trống chết %d tiết (%d phút chờ) giữa %s (%s) và %s (%s).',
                        $gapPeriods,
                        $breakMin,
                        $slotA->shiftLabel(),
                        $fromCode,
                        $slotB->shiftLabel(),
                        $toCode
                    ),
                ];
            }
        }

        // 3. Excessive commutes (> 2 campus switches in the same day)
        if ($commuteCount > self::MAX_COMMUTES_PER_DAY) {
            $first = $daySchedules[0];
            $alerts[] = [
                'type'             => 'EXCESSIVE_COMMUTE',
                'severity'         => 'warning',
                'teacher_id'       => (int) ($first['teacher_id'] ?? 0),
                'teacher_name'     => (string) ($first['teacher_name'] ?? ''),
                'day_of_week'      => (int) $first['day_of_week'],
                'from_schedule_id' => (int) ($first['id'] ?? 0),
                'to_schedule_id'   => (int) ($daySchedules[$count - 1]['id'] ?? 0),
                'target_schedule'  => $daySchedules[$count - 1],
                'prev_schedule'    => $first,
                'commute_count'    => $commuteCount,
                'message'          => sprintf(
                    'Chuyển cơ sở %d lần trong cùng ngày (vượt ngưỡng %d lần/ngày, tổng %d phút di chuyển).',
                    $commuteCount,
                    self::MAX_COMMUTES_PER_DAY,
                    $totalTravel
                ),
            ];
        }

        // Formula: DeadGapDay = (max(e_i) - min(s_i) + 1) - sum(e_i - s_i + 1)
        $minStart = min(array_map(static fn(array $s): int => (int) $s['start_period'], $daySchedules));
        $maxEnd   = max(array_map(static fn(array $s): int => (int) $s['end_period'], $daySchedules));
        $sumPeriods = array_sum(array_map(static fn(array $s): int => (int) $s['end_period'] - (int) $s['start_period'] + 1, $daySchedules));
        $deadGapDay = max(0, ($maxEnd - $minStart + 1) - $sumPeriods);

        return [
            'alerts'               => $alerts,
            'commute_count'        => $commuteCount,
            'total_travel_minutes' => $totalTravel,
            'dead_gap_day'         => $deadGapDay,
            'wasted_periods'       => $wastedPeriods,
            'legs'                 => $legs,
        ];
    }

    /**
     * Analyze all schedules across all teachers and days.
     *
     * @param array<int, array<string, mixed>> $schedules
     * @param array<int, array<int, array{travel_minutes: int, distance_km?: float}>> $travelMatrix
     * @return array{
     *     alerts: array<int, array<string, mixed>>,
     *     counts: array{impossible_travel: int, tight_travel: int, dead_gap: int, excessive_commute: int},
     *     total_wasted_periods: int,
     *     commute_days: array<int, array<string, mixed>>,
     *     schedule_flags: array<int, string[]>
     * }
     */
    public function analyzeAll(array $schedules, array $travelMatrix): array
    {
        $grouped = [];
        foreach ($schedules as $s) {
            $key = ((int) $s['teacher_id']) . ':' . ((int) $s['day_of_week']);
            $grouped[$key][] = $s;
        }

        $allAlerts = [];
        $counts = [
            'impossible_travel' => 0,
            'tight_travel'      => 0,
            'dead_gap'          => 0,
            'excessive_commute' => 0,
        ];
        $totalWasted = 0;
        $commuteDays = [];
        $scheduleFlags = [];

        foreach ($grouped as $dayList) {
            $res = $this->analyzeTeacherDay($dayList, $travelMatrix);
            $totalWasted += $res['wasted_periods'];

            if ($res['commute_count'] > 0) {
                $sample = $dayList[0];
                $commuteDays[] = [
                    'teacher_id'           => (int) $sample['teacher_id'],
                    'teacher_name'         => (string) ($sample['teacher_name'] ?? ''),
                    'day_of_week'          => (int) $sample['day_of_week'],
                    'commute_count'        => $res['commute_count'],
                    'total_travel_minutes' => $res['total_travel_minutes'],
                    'dead_gap_day'         => $res['dead_gap_day'],
                    'legs'                 => $res['legs'],
                ];
            }

            foreach ($res['alerts'] as $alert) {
                $allAlerts[] = $alert;
                match ($alert['type']) {
                    'IMPOSSIBLE_TRAVEL' => $counts['impossible_travel']++,
                    'TIGHT_TRAVEL'      => $counts['tight_travel']++,
                    'DEAD_GAP'          => $counts['dead_gap']++,
                    'EXCESSIVE_COMMUTE' => $counts['excessive_commute']++,
                    default             => null,
                };

                foreach (['from_schedule_id', 'to_schedule_id'] as $idKey) {
                    $sid = (int) ($alert[$idKey] ?? 0);
                    if ($sid > 0) {
                        $scheduleFlags[$sid][] = $alert['type'];
                        $scheduleFlags[$sid] = array_values(array_unique($scheduleFlags[$sid]));
                    }
                }
            }
        }

        // Sort alerts: CRITICAL first, then WARNING
        usort($allAlerts, static function (array $a, array $b): int {
            $rank = ['critical' => 0, 'warning' => 1, 'info' => 2];
            $cmp = ($rank[$a['severity']] ?? 9) <=> ($rank[$b['severity']] ?? 9);
            if ($cmp !== 0) {
                return $cmp;
            }
            return ((int) $a['day_of_week']) <=> ((int) $b['day_of_week']);
        });

        return [
            'alerts'               => $allAlerts,
            'counts'               => $counts,
            'total_wasted_periods' => $totalWasted,
            'commute_days'         => $commuteDays,
            'schedule_flags'       => $scheduleFlags,
        ];
    }

    /**
     * Optimization Health Score (0 - 100%):
     * HealthScore = clamp(100 - 15*N_impossible - 5*N_tight - 3*N_dead_gap - 2*sigma_load, 0, 100)
     */
    public function computeHealthScore(int $impossibleCount, int $tightCount, int $deadGapCount, float $loadStdDev): int
    {
        $raw = 100.0
            - (15.0 * $impossibleCount)
            - (5.0 * $tightCount)
            - (3.0 * $deadGapCount)
            - (2.0 * $loadStdDev);

        return (int) max(0, min(100, (int) round($raw)));
    }

    /**
     * Evaluate inserting a candidate schedule into a teacher's day schedules.
     * Returns optimization score delta (-25..+20), badges, and warnings.
     *
     * @param array<string, mixed> $candidate
     * @param array<int, array<string, mixed>> $existingDaySchedules
     * @param array<int, array<int, array{travel_minutes: int, distance_km?: float}>> $travelMatrix
     * @return array{score: int, status: string, badges: string[], warnings: array<int, array<string, mixed>>}
     */
    public function evaluateCandidateInDay(array $candidate, array $existingDaySchedules, array $travelMatrix): array
    {
        if ($existingDaySchedules === []) {
            return [
                'score'    => 12,
                'status'   => 'clean_day',
                'badges'   => ['Lịch ngày sạch (0 di chuyển)'],
                'warnings' => [],
            ];
        }

        $candSlot = TimeSlot::fromArray($candidate);
        $candBranchId = (int) ($candidate['branch_id'] ?? 0);

        if ($candBranchId <= 0) {
            return [
                'score'    => 0,
                'status'   => 'branch_unset',
                'badges'   => ['Chọn cơ sở để đánh giá di chuyển'],
                'warnings' => [],
            ];
        }

        $combined = $existingDaySchedules;
        $combined[] = array_merge($candidate, ['id' => -1, '_is_candidate' => true]);

        $analysis = $this->analyzeTeacherDay($combined, $travelMatrix);

        // Filter alerts involving our candidate (-1)
        $candAlerts = array_values(array_filter(
            $analysis['alerts'],
            static fn(array $a): bool => (int) ($a['from_schedule_id'] ?? 0) === -1 || (int) ($a['to_schedule_id'] ?? 0) === -1
        ));

        $hasImpossible = false;
        $hasTight = false;
        $hasDeadGap = false;
        foreach ($candAlerts as $a) {
            if ($a['type'] === 'IMPOSSIBLE_TRAVEL') {
                $hasImpossible = true;
            } elseif ($a['type'] === 'TIGHT_TRAVEL') {
                $hasTight = true;
            } elseif ($a['type'] === 'DEAD_GAP') {
                $hasDeadGap = true;
            }
        }

        if ($hasImpossible) {
            return [
                'score'    => -25,
                'status'   => 'diff_impossible',
                'badges'   => [],
                'warnings' => $candAlerts,
            ];
        }

        if ($hasTight) {
            return [
                'score'    => -12,
                'status'   => 'diff_tight',
                'badges'   => [],
                'warnings' => $candAlerts,
            ];
        }

        // Check immediate chronological neighbors (immediately before and immediately after)
        usort($combined, static fn(array $a, array $b): int => ((int) $a['start_period']) <=> ((int) $b['start_period']));
        $candIdx = null;
        foreach ($combined as $idx => $item) {
            if ((int) ($item['id'] ?? 0) === -1) {
                $candIdx = $idx;
                break;
            }
        }

        $neighbors = [];
        if ($candIdx !== null) {
            if (isset($combined[$candIdx - 1])) {
                $neighbors[] = $combined[$candIdx - 1];
            }
            if (isset($combined[$candIdx + 1])) {
                $neighbors[] = $combined[$candIdx + 1];
            }
        }

        $adjacentSameBranch = false;
        $neighborDiffBranch = false;

        foreach ($neighbors as $nb) {
            $nbSlot = TimeSlot::fromArray($nb);
            if (!$candSlot->overlapsDates($nbSlot)) {
                continue;
            }
            $gap = $candSlot->gapPeriods($nbSlot);
            if ($gap === null) {
                continue;
            }
            if ((int) $nb['branch_id'] === $candBranchId) {
                if ($gap <= 1) {
                    $adjacentSameBranch = true;
                }
            } else {
                $neighborDiffBranch = true;
            }
        }

        if ($adjacentSameBranch && !$neighborDiffBranch) {
            return [
                'score'    => 20,
                'status'   => 'adjacent_same',
                'badges'   => ['Liền ca cùng cơ sở (0p di chuyển)'],
                'warnings' => $candAlerts,
            ];
        }

        if ($hasDeadGap && !$neighborDiffBranch) {
            return [
                'score'    => 0,
                'status'   => 'dead_gap',
                'badges'   => ['Cùng cơ sở nhưng có khoảng trống chết'],
                'warnings' => $candAlerts,
            ];
        }

        if ($neighborDiffBranch) {
            return [
                'score'    => -5,
                'status'   => 'diff_comfort',
                'badges'   => ['Khác cơ sở (đủ thời gian di chuyển)'],
                'warnings' => $candAlerts,
            ];
        }

        return [
            'score'    => 10,
            'status'   => 'same_branch_gap',
            'badges'   => ['Cùng cơ sở trong ngày'],
            'warnings' => $candAlerts,
        ];
    }

    /**
     * Suggest Top-N optimal free slots for a teacher at a given branch and duration.
     *
     * @return array<int, array<string, mixed>>
     */
    public function suggestOptimalSlots(
        int $teacherId,
        int $branchId,
        int $duration = 3,
        string $startDate = '',
        string $endDate = '',
        ?int $excludeId = null,
        int $limit = 5
    ): array {
        $duration = max(1, min(6, $duration));
        if ($startDate === '') {
            $startDate = date('Y-m-01');
        }
        if ($endDate === '') {
            $endDate = date('Y-m-t', strtotime('+2 months'));
        }

        $scheduleRepo = new ScheduleRepository();
        $availRepo    = new AvailabilityRepository();
        $branchRepo   = new BranchRepository();

        $teacherSchedules = $scheduleRepo->findByTeacher($teacherId);
        if ($excludeId !== null && $excludeId > 0) {
            $teacherSchedules = array_values(array_filter(
                $teacherSchedules,
                static fn(array $s): bool => (int) $s['id'] !== $excludeId
            ));
        }

        $availabilities = $availRepo->findByTeacher($teacherId);
        $travelMatrix   = $branchRepo->getTravelMatrix();
        $branch         = $branchRepo->findById($branchId);
        $branchCode     = $branch ? (string) $branch['code'] : ('CS' . $branchId);

        return $this->rankOptimalSlots(
            $teacherId,
            $branchId,
            $branchCode,
            $duration,
            $startDate,
            $endDate,
            $teacherSchedules,
            $availabilities,
            $travelMatrix,
            $limit
        );
    }

    /**
     * Pure ranking method for Top-N optimal free slots (unit-testable without DB).
     *
     * @param array<int, array<string, mixed>> $teacherSchedules
     * @param array<int, array<string, mixed>> $availabilities
     * @param array<int, array<int, array{travel_minutes: int, distance_km?: float}>> $travelMatrix
     * @return array<int, array<string, mixed>>
     */
    public function rankOptimalSlots(
        int $teacherId,
        int $branchId,
        string $branchCode,
        int $duration,
        string $startDate,
        string $endDate,
        array $teacherSchedules,
        array $availabilities,
        array $travelMatrix,
        int $limit = 5
    ): array {
        // Candidate period windows: 5 standard shifts if duration == 3, else clean non-crossing windows
        $windows = $duration === 3
            ? [[1, 3], [4, 6], [7, 9], [10, 12], [13, 15]]
            : array_values(array_filter(
                [[1, $duration], [4, min(6, 3 + $duration)], [7, min(9, 6 + $duration)], [10, min(12, 9 + $duration)], [13, min(15, 12 + $duration)]],
                static fn(array $w): bool => ($w[1] - $w[0] + 1) === $duration
            ));

        if ($windows === []) {
            for ($p = 1; $p <= 15 - $duration + 1; $p += $duration) {
                $windows[] = [$p, $p + $duration - 1];
            }
        }

        $candidates = [];
        for ($day = 2; $day <= 7; $day++) {
            $daySchedules = array_values(array_filter(
                $teacherSchedules,
                static fn(array $s): bool => (int) $s['day_of_week'] === $day
            ));

            foreach ($windows as [$sp, $ep]) {
                $candSlot = new TimeSlot($day, $sp, $ep, $startDate, $endDate);

                // Hard check: must not overlap any existing schedule of this teacher
                $conflict = false;
                foreach ($daySchedules as $ex) {
                    if ($candSlot->overlaps(TimeSlot::fromArray($ex))) {
                        $conflict = true;
                        break;
                    }
                }
                if ($conflict) {
                    continue;
                }

                $score = 50;
                $reasons = [];
                $matchesAvail = false;
                $matchesPreferredBranch = false;

                foreach ($availabilities as $av) {
                    if ((int) $av['day_of_week'] === $day
                        && (int) $av['start_period'] <= $sp
                        && (int) $av['end_period'] >= $ep) {
                        $matchesAvail = true;
                        if (isset($av['preferred_branch_id']) && (int) $av['preferred_branch_id'] === $branchId) {
                            $matchesPreferredBranch = true;
                            break;
                        }
                    }
                }

                if ($matchesAvail) {
                    $score += 25;
                    $reasons[] = 'Khớp tiết trống GV đã đăng ký';
                    if ($matchesPreferredBranch) {
                        $score += 5;
                        $reasons[] = 'Đúng cơ sở ưu tiên (' . $branchCode . ')';
                    }
                }

                $eval = $this->evaluateCandidateInDay([
                    'teacher_id'   => $teacherId,
                    'branch_id'    => $branchId,
                    'branch_code'  => $branchCode,
                    'day_of_week'  => $day,
                    'start_period' => $sp,
                    'end_period'   => $ep,
                    'start_date'   => $startDate,
                    'end_date'     => $endDate,
                ], $daySchedules, $travelMatrix);

                $score += $eval['score'];
                foreach ($eval['badges'] as $b) {
                    $reasons[] = $b;
                }
                if ($eval['status'] === 'diff_impossible') {
                    continue; // Never suggest an impossible-travel slot
                }
                if ($eval['status'] === 'dead_gap') {
                    $score -= 10;
                    $reasons[] = 'Lưu ý: tạo khoảng trống chết trong ngày';
                }

                $score = max(0, min(100, $score));
                $candidates[] = [
                    'day_of_week'          => $day,
                    'day_label'            => function_exists('day_label') ? day_label($day) : ('Thứ ' . $day),
                    'start_period'         => $sp,
                    'end_period'           => $ep,
                    'shift_label'          => $candSlot->shiftLabel(),
                    'time_range'           => $candSlot->timeRange(),
                    'score'                => $score,
                    'status'               => $eval['status'],
                    'matches_availability' => $matchesAvail,
                    'reasons'              => $reasons,
                ];
            }
        }

        usort($candidates, static function (array $a, array $b): int {
            $cmp = $b['score'] <=> $a['score'];
            if ($cmp !== 0) {
                return $cmp;
            }
            if ($a['day_of_week'] !== $b['day_of_week']) {
                return $a['day_of_week'] <=> $b['day_of_week'];
            }
            return $a['start_period'] <=> $b['start_period'];
        });

        return array_slice($candidates, 0, $limit);
    }

    /**
     * Build concrete 1-click fix suggestion for an optimization alert.
     *
     * @param array<string, mixed> $alert
     * @param array<int, array<string, mixed>> $allSchedules
     * @param array<int, array<int, array<string, mixed>>> $availByTeacher
     * @param array<int, array<int, array{travel_minutes: int, distance_km?: float}>> $travelMatrix
     * @return array<string, mixed>|null
     */
    public function suggestRescheduleFix(
        array $alert,
        array $allSchedules,
        array $availByTeacher,
        array $travelMatrix
    ): ?array {
        $target = $alert['target_schedule'] ?? null;
        if (!is_array($target) || empty($target['id'])) {
            return null;
        }

        $teacherId = (int) $target['teacher_id'];
        $branchId  = (int) $target['branch_id'];
        $targetId  = (int) $target['id'];
        $duration  = (int) $target['end_period'] - (int) $target['start_period'] + 1;

        $teacherSchedules = array_values(array_filter(
            $allSchedules,
            static fn(array $s): bool => (int) $s['teacher_id'] === $teacherId && (int) $s['id'] !== $targetId
        ));

        $slots = $this->rankOptimalSlots(
            $teacherId,
            $branchId,
            (string) ($target['branch_code'] ?? ('CS' . $branchId)),
            $duration,
            (string) $target['start_date'],
            (string) $target['end_date'],
            $teacherSchedules,
            $availByTeacher[$teacherId] ?? [],
            $travelMatrix,
            1
        );

        if ($slots === []) {
            return null;
        }

        $best = $slots[0];
        return [
            'schedule_id'  => $targetId,
            'subject_name' => (string) $target['subject_name'],
            'branch_code'  => (string) ($target['branch_code'] ?? ''),
            'current_slot' => (function_exists('day_label') ? day_label((int) $target['day_of_week']) : 'Thứ ' . $target['day_of_week'])
                . ' • ' . (string) $target['shift_or_periods'],
            'suggested'    => $best,
            'summary'      => sprintf(
                'Chuyển "%s" sang %s • %s (%d điểm tối ưu)',
                $target['subject_name'],
                $best['day_label'],
                $best['shift_label'],
                $best['score']
            ),
        ];
    }
}
