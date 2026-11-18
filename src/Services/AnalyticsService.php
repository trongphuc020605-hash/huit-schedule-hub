<?php

declare(strict_types=1);

namespace HuitSchedule\Services;

use HuitSchedule\Domain\TimeSlot;
use HuitSchedule\Repositories\AvailabilityRepository;
use HuitSchedule\Repositories\BranchRepository;
use HuitSchedule\Repositories\ScheduleRepository;
use HuitSchedule\Repositories\TeacherRepository;

final class AnalyticsService
{
    private BranchRepository $branchRepo;
    private TeacherRepository $teacherRepo;
    private ScheduleRepository $scheduleRepo;
    private AvailabilityRepository $availRepo;
    private ScheduleOptimizerService $optimizer;

    public function __construct(
        ?BranchRepository $branchRepo = null,
        ?TeacherRepository $teacherRepo = null,
        ?ScheduleRepository $scheduleRepo = null,
        ?AvailabilityRepository $availRepo = null,
        ?ScheduleOptimizerService $optimizer = null
    ) {
        $this->branchRepo   = $branchRepo ?? new BranchRepository();
        $this->teacherRepo  = $teacherRepo ?? new TeacherRepository();
        $this->scheduleRepo = $scheduleRepo ?? new ScheduleRepository();
        $this->availRepo    = $availRepo ?? new AvailabilityRepository();
        $this->optimizer    = $optimizer ?? new ScheduleOptimizerService();
    }

    /**
     * Build complete Dashboard KPI, Chart.js datasets, and Optimization Center alerts.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getDashboardData(array $filters = []): array
    {
        $branches       = $this->branchRepo->all();
        $teachers       = $this->teacherRepo->all('teacher');
        $schedules      = $this->scheduleRepo->findAll($filters);
        $allSchedules   = $filters === [] ? $schedules : $this->scheduleRepo->findAll();
        $travelMatrix   = $this->branchRepo->getTravelMatrix();
        $availByTeacher = $this->availRepo->allGroupedByTeacher();

        // Total periods in filtered dataset
        $totalPeriods = 0;
        $periodsByTeacher = [];
        $countsByBranch = [];
        $periodsByBranch = [];

        foreach ($branches as $b) {
            $countsByBranch[(int) $b['id']]  = 0;
            $periodsByBranch[(int) $b['id']] = 0;
        }
        foreach ($teachers as $t) {
            $periodsByTeacher[(int) $t['id']] = 0;
        }

        // Day (2..8) x Session (morning, afternoon, evening)
        $daySessions = [];
        for ($d = 2; $d <= 8; $d++) {
            $daySessions[$d] = ['morning' => 0, 'afternoon' => 0, 'evening' => 0];
        }

        foreach ($schedules as $s) {
            $pCount = (int) $s['end_period'] - (int) $s['start_period'] + 1;
            $totalPeriods += $pCount;

            $tid = (int) $s['teacher_id'];
            $bid = (int) $s['branch_id'];
            $day = (int) $s['day_of_week'];

            $periodsByTeacher[$tid] = ($periodsByTeacher[$tid] ?? 0) + $pCount;
            $countsByBranch[$bid]   = ($countsByBranch[$bid] ?? 0) + 1;
            $periodsByBranch[$bid]  = ($periodsByBranch[$bid] ?? 0) + $pCount;

            $slot = TimeSlot::fromArray($s);
            $sess = $slot->session();
            if (isset($daySessions[$day][$sess])) {
                $daySessions[$day][$sess]++;
            }
        }

        // Load standard deviation across teachers
        $loadValues = array_values($periodsByTeacher);
        $loadStdDev = $this->stdDev($loadValues);

        // Run Optimization Analysis globally so cross-campus alerts are preserved when filtering a single branch
        $globalAnalysis = $this->optimizer->analyzeAll($allSchedules, $travelMatrix);
        if ($filters === []) {
            $filteredAlerts = $globalAnalysis['alerts'];
            $counts         = $globalAnalysis['counts'];
            $wastedPeriods  = $globalAnalysis['total_wasted_periods'];
        } else {
            $filteredIds = array_flip(array_map(static fn(array $s): int => (int) $s['id'], $schedules));
            $filteredAlerts = [];
            $counts = [
                'impossible_travel' => 0,
                'tight_travel'      => 0,
                'dead_gap'          => 0,
                'excessive_commute' => 0,
            ];
            $wastedPeriods = 0;

            foreach ($globalAnalysis['alerts'] as $al) {
                $fid = (int) ($al['from_schedule_id'] ?? 0);
                $tid = (int) ($al['to_schedule_id'] ?? 0);
                if (isset($filteredIds[$fid]) || isset($filteredIds[$tid])) {
                    $filteredAlerts[] = $al;
                    match ($al['type']) {
                        'IMPOSSIBLE_TRAVEL' => $counts['impossible_travel']++,
                        'TIGHT_TRAVEL'      => $counts['tight_travel']++,
                        'DEAD_GAP'          => $counts['dead_gap']++,
                        'EXCESSIVE_COMMUTE' => $counts['excessive_commute']++,
                        default             => null,
                    };
                    if ($al['type'] === 'DEAD_GAP') {
                        $wastedPeriods += (int) ($al['wasted_periods'] ?? 0);
                    }
                }
            }
        }

        $healthScore = $this->optimizer->computeHealthScore(
            $counts['impossible_travel'],
            $counts['tight_travel'],
            $counts['dead_gap'],
            $loadStdDev
        );

        // Enrich each alert with an actionable 1-click reschedule suggestion
        $enrichedAlerts = [];
        foreach ($filteredAlerts as $alert) {
            $fix = $this->optimizer->suggestRescheduleFix($alert, $allSchedules, $availByTeacher, $travelMatrix);
            $alert['fix'] = $fix;
            $enrichedAlerts[] = $alert;
        }

        // Chart 1: Teacher workload (Horizontal Bar sorted by periods desc)
        $teacherRows = [];
        foreach ($teachers as $t) {
            $tid = (int) $t['id'];
            $assigned = $periodsByTeacher[$tid] ?? 0;
            if (!empty($filters['teacher_id']) && (int) $filters['teacher_id'] !== $tid) {
                continue;
            }
            $teacherRows[] = [
                'id'       => $tid,
                'name'     => (string) $t['full_name'],
                'assigned' => $assigned,
                'max'      => (int) $t['max_periods_per_week'],
            ];
        }
        usort($teacherRows, static fn(array $a, array $b): int => $b['assigned'] <=> $a['assigned']);

        // Chart 2: Branch distribution (Doughnut with color_hex)
        $branchChart = [
            'labels'  => [],
            'counts'  => [],
            'periods' => [],
            'colors'  => [],
        ];
        foreach ($branches as $b) {
            $bid = (int) $b['id'];
            $branchChart['labels'][]  = $b['code'] . ' - ' . $b['branch_name'];
            $branchChart['counts'][]  = $countsByBranch[$bid] ?? 0;
            $branchChart['periods'][] = $periodsByBranch[$bid] ?? 0;
            $branchChart['colors'][]  = (string) $b['color_hex'];
        }

        // Chart 3: Day x Session stacked bar
        $dayChart = [
            'labels'    => ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'CN'],
            'morning'   => [],
            'afternoon' => [],
            'evening'   => [],
        ];
        for ($d = 2; $d <= 8; $d++) {
            $dayChart['morning'][]   = $daySessions[$d]['morning'];
            $dayChart['afternoon'][] = $daySessions[$d]['afternoon'];
            $dayChart['evening'][]   = $daySessions[$d]['evening'];
        }

        // Timeline Gantt data (07:00 = 420m -> 20:30 = 1230m, span = 810m)
        $timelineTeacherId = !empty($filters['teacher_id'])
            ? (int) $filters['teacher_id']
            : (!empty($enrichedAlerts[0]['teacher_id']) ? (int) $enrichedAlerts[0]['teacher_id'] : (int) ($teachers[0]['id'] ?? 0));
        $timeline = $this->buildTeacherTimeline($timelineTeacherId, $teachers, $allSchedules, $travelMatrix);

        return [
            'kpi' => [
                'total_branches'       => count($branches),
                'total_teachers'       => count($teachers),
                'total_schedules'      => count($schedules),
                'total_periods'        => $totalPeriods,
                'health_score'         => $healthScore,
                'alert_count'          => count($enrichedAlerts),
                'critical_alert_count' => $counts['impossible_travel'],
                'warning_alert_count'  => $counts['tight_travel'] + $counts['dead_gap'] + $counts['excessive_commute'],
                'wasted_periods'       => $wastedPeriods,
                'load_stddev'          => round($loadStdDev, 2),
                'counts'               => $counts,
            ],
            'charts' => [
                'teacher_workload' => [
                    'labels'   => array_column($teacherRows, 'name'),
                    'assigned' => array_column($teacherRows, 'assigned'),
                    'max'      => array_column($teacherRows, 'max'),
                ],
                'branch_distribution' => $branchChart,
                'day_sessions'        => $dayChart,
            ],
            'alerts'         => $enrichedAlerts,
            'schedule_flags' => $globalAnalysis['schedule_flags'],
            'timeline'       => $timeline,
        ];
    }

    /**
     * Build a 07:00-20:30 daily Gantt timeline for a selected teacher showing classes, commutes, and dead gaps.
     *
     * @param array<int, array<string, mixed>> $teachers
     * @param array<int, array<string, mixed>> $allSchedules
     * @param array<int, array<int, array{travel_minutes: int, distance_km?: float}>> $travelMatrix
     * @return array<string, mixed>
     */
    private function buildTeacherTimeline(
        int $teacherId,
        array $teachers,
        array $allSchedules,
        array $travelMatrix
    ): array {
        $teacherName = '';
        foreach ($teachers as $t) {
            if ((int) $t['id'] === $teacherId) {
                $teacherName = (string) $t['full_name'];
                break;
            }
        }

        $dayStartMin = 420;  // 07:00
        $daySpanMin  = 810;  // 20:30 - 07:00

        $tSchedules = array_values(array_filter(
            $allSchedules,
            static fn(array $s): bool => (int) $s['teacher_id'] === $teacherId
        ));

        $days = [];
        for ($d = 2; $d <= 7; $d++) {
            $dayList = array_values(array_filter(
                $tSchedules,
                static fn(array $s): bool => (int) $s['day_of_week'] === $d
            ));
            usort($dayList, static fn(array $a, array $b): int => ((int) $a['start_period']) <=> ((int) $b['start_period']));

            $blocks = [];
            $gaps = [];
            foreach ($dayList as $idx => $s) {
                $slot = TimeSlot::fromArray($s);
                $leftPct  = round((($slot->startMinute() - $dayStartMin) / $daySpanMin) * 100, 2);
                $widthPct = round((($slot->endMinute() - $slot->startMinute()) / $daySpanMin) * 100, 2);

                $blocks[] = [
                    'id'           => (int) $s['id'],
                    'subject_name' => (string) $s['subject_name'],
                    'branch_code'  => (string) $s['branch_code'],
                    'branch_color' => (string) $s['branch_color'],
                    'room'         => (string) $s['room'],
                    'shift'        => (string) $s['shift_or_periods'],
                    'time_range'   => $slot->timeRange(),
                    'left_pct'     => $leftPct,
                    'width_pct'    => $widthPct,
                ];

                if ($idx > 0) {
                    $prev = $dayList[$idx - 1];
                    $prevSlot = TimeSlot::fromArray($prev);
                    $breakMin = $prevSlot->breakMinutes($slot);
                    $gapPeriods = $prevSlot->gapPeriods($slot);
                    if ($breakMin !== null && $gapPeriods !== null) {
                        $gapLeft  = round((($prevSlot->endMinute() - $dayStartMin) / $daySpanMin) * 100, 2);
                        $gapWidth = round(($breakMin / $daySpanMin) * 100, 2);

                        $fromBid  = (int) $prev['branch_id'];
                        $toBid    = (int) $s['branch_id'];
                        $fromCode = (string) ($prev['branch_code'] ?? ('CS' . $fromBid));
                        $toCode   = (string) ($s['branch_code'] ?? ('CS' . $toBid));
                        $travel   = $this->optimizer->travelMinutes($fromBid, $toBid, $travelMatrix);

                        if ($fromBid !== $toBid && $travel > $breakMin) {
                            $gaps[] = [
                                'type'      => 'impossible_travel',
                                'left_pct'  => $gapLeft,
                                'width_pct' => max(0.9, $gapWidth),
                                'is_narrow' => $gapWidth < 12.0,
                                'label'     => sprintf('Không kịp ĐC %s -> %s: cần %dp / nghỉ %dp (%s - %s)', $fromCode, $toCode, $travel, $breakMin, $prevSlot->endTime(), $slot->startTime()),
                            ];
                        } elseif ($gapPeriods >= 3) {
                            $gaps[] = [
                                'type'      => 'dead_gap',
                                'left_pct'  => $gapLeft,
                                'width_pct' => $gapWidth,
                                'is_narrow' => $gapWidth < 12.0,
                                'label'     => sprintf('Khoảng trống chết %d tiết (%d phút nghỉ: %s - %s)', $gapPeriods, $breakMin, $prevSlot->endTime(), $slot->startTime()),
                            ];
                        } elseif ($fromBid !== $toBid && ($breakMin - $travel) < 15) {
                            $gaps[] = [
                                'type'      => 'tight_travel',
                                'left_pct'  => $gapLeft,
                                'width_pct' => max(0.9, $gapWidth),
                                'is_narrow' => $gapWidth < 12.0,
                                'label'     => sprintf('Di chuyển sát giờ %s -> %s: cần %dp / nghỉ %dp (%s - %s)', $fromCode, $toCode, $travel, $breakMin, $prevSlot->endTime(), $slot->startTime()),
                            ];
                        }
                    }
                }
            }

            $days[] = [
                'day_of_week' => $d,
                'label'       => function_exists('day_label') ? day_label($d) : ('Thứ ' . $d),
                'blocks'      => $blocks,
                'gaps'        => $gaps,
            ];
        }

        // Exact proportional tick positions on 07:00 (420m) -> 20:30 (1230m) axis
        $tickDefs = [
            ['label' => '07:00', 'min' => 420,  'align' => 'start'],
            ['label' => '09:30', 'min' => 570,  'align' => 'center'],
            ['label' => '12:30', 'min' => 750,  'align' => 'center'],
            ['label' => '15:00', 'min' => 900,  'align' => 'center'],
            ['label' => '17:40', 'min' => 1060, 'align' => 'center'],
            ['label' => '20:30', 'min' => 1230, 'align' => 'end'],
        ];
        $ticks = array_map(static fn(array $tk): array => [
            'label'    => $tk['label'],
            'left_pct' => round((($tk['min'] - $dayStartMin) / $daySpanMin) * 100, 2),
            'align'    => $tk['align'],
        ], $tickDefs);

        return [
            'teacher_id'   => $teacherId,
            'teacher_name' => $teacherName,
            'ticks'        => $ticks,
            'days'         => $days,
        ];
    }

    /**
     * @param int[] $values
     */
    private function stdDev(array $values): float
    {
        $n = count($values);
        if ($n <= 1) {
            return 0.0;
        }
        $mean = array_sum($values) / $n;
        $sumSq = 0.0;
        foreach ($values as $v) {
            $sumSq += ($v - $mean) ** 2;
        }
        return sqrt($sumSq / $n);
    }
}
