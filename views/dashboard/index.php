<?php

declare(strict_types=1);

$healthScore = (int) ($kpi['health_score'] ?? 100);
$healthColor = $healthScore >= 80 ? '#059669' : ($healthScore >= 60 ? '#b45309' : '#dc2626');
?>

<!-- Filter Bar -->
<form method="GET" action="<?= e(url('/dashboard')) ?>" class="filter-bar" id="dashboard-filter-form" data-base-url="<?= e(base_url()) ?>">
    <div class="filter-item">
        <label class="form-label" for="f-branch">Cơ sở đào tạo</label>
        <select name="branch_id" id="f-branch" class="form-select">
            <option value="">Tất cả cơ sở</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= (int) $b['id'] ?>" <?= ((int) ($filters['branch_id'] ?? 0) === (int) $b['id']) ? 'selected' : '' ?>>
                    <?= e($b['code'] . ' — ' . $b['branch_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-item">
        <label class="form-label" for="f-teacher">Giảng viên</label>
        <select name="teacher_id" id="f-teacher" class="form-select">
            <option value="">Tất cả giảng viên</option>
            <?php foreach ($teachers as $t): ?>
                <option value="<?= (int) $t['id'] ?>" <?= ((int) ($filters['teacher_id'] ?? 0) === (int) $t['id']) ? 'selected' : '' ?>>
                    <?= e($t['full_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-item" style="max-width:165px;">
        <label class="form-label" for="f-day">Thứ trong tuần</label>
        <select name="day_of_week" id="f-day" class="form-select">
            <option value="">Tất cả thứ</option>
            <?php foreach (app_config('days', []) as $dNum => $dLabel): ?>
                <option value="<?= (int) $dNum ?>" <?= ((int) ($filters['day_of_week'] ?? 0) === (int) $dNum) ? 'selected' : '' ?>>
                    <?= e($dLabel) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-item">
        <label class="form-label" for="f-search">Từ khóa (Môn / Phòng)</label>
        <input type="text" name="search" id="f-search" class="form-control" placeholder="VD: PHP, A301..."
               value="<?= e((string) ($filters['search'] ?? '')) ?>">
    </div>

    <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Lọc dữ liệu</button>
        <button type="button" id="btn-refresh-charts" class="btn btn-secondary">Làm mới biểu đồ</button>
        <a href="<?= e(url('/dashboard')) ?>" class="btn btn-ghost">Đặt lại</a>
    </div>
</form>

<!-- 5 KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card" style="--kpi-accent:#1d4ed8;">
        <div class="kpi-label">Cơ sở đào tạo</div>
        <div class="kpi-value"><?= (int) $kpi['total_branches'] ?></div>
        <div class="kpi-sub">Ma trận di chuyển 2 chiều</div>
    </div>

    <div class="kpi-card" style="--kpi-accent:#0284c7;">
        <div class="kpi-label">Đội ngũ Giảng viên</div>
        <div class="kpi-value"><?= (int) $kpi['total_teachers'] ?></div>
        <div class="kpi-sub">Độ lệch chuẩn tải: <?= e((string) $kpi['load_stddev']) ?></div>
    </div>

    <div class="kpi-card" style="--kpi-accent:#4f46e5;">
        <div class="kpi-label">Lớp / Tổng Tiết dạy</div>
        <div class="kpi-value"><span id="kpi-schedules"><?= (int) $kpi['total_schedules'] ?></span> <span style="font-size:1rem;font-weight:600;color:#64748b;">lớp</span></div>
        <div class="kpi-sub" id="kpi-periods"><?= (int) $kpi['total_periods'] ?> tiết / tuần</div>
    </div>

    <div class="kpi-card" style="--kpi-accent:<?= e($healthColor) ?>;">
        <div class="kpi-label">Sức khỏe Tối ưu Toàn trường</div>
        <div class="kpi-value" id="kpi-health" style="color:<?= e($healthColor) ?>;"><?= $healthScore ?>%</div>
        <div class="kpi-sub">Chỉ số đa mục tiêu HUIT</div>
    </div>

    <div class="kpi-card" style="--kpi-accent:<?= (int) $kpi['critical_alert_count'] > 0 ? '#dc2626' : '#b45309' ?>;">
        <div class="kpi-label">Cảnh báo cần xử lý</div>
        <div class="kpi-value" id="kpi-alerts" style="color:<?= (int) $kpi['alert_count'] > 0 ? '#dc2626' : '#059669' ?>;"><?= (int) $kpi['alert_count'] ?></div>
        <div class="kpi-sub">
            <?= (int) $kpi['critical_alert_count'] ?> nghiêm trọng | <?= (int) $kpi['wasted_periods'] ?> tiết trống chết
        </div>
    </div>
</div>

<!-- Row 1 Charts: Teacher Workload + Branch Doughnut -->
<div class="charts-grid">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Phân bổ Số tiết dạy theo Giảng viên (so với Định mức)</h2>
            <a href="<?= e(url('/schedules/create')) ?>" class="btn btn-primary btn-xs">Phân lịch mới</a>
        </div>
        <div class="card-body">
            <div class="chart-box-tall">
                <canvas id="chart-teacher-workload"></canvas>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Tỷ trọng Phân bổ theo Cơ sở</h2>
            <a href="<?= e(url('/branches')) ?>" class="btn btn-secondary btn-xs">Quản lý cơ sở</a>
        </div>
        <div class="card-body">
            <div class="chart-box-tall">
                <canvas id="chart-branch-distribution"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Row 2 Chart: Day x Session Stacked Bar -->
<div class="card mb-3">
    <div class="card-header">
        <h2 class="card-title">Mật độ Lịch dạy theo Thứ x Buổi (Sáng / Chiều / Tối)</h2>
        <a href="<?= e(url('/schedules/matrix')) ?>" class="btn btn-secondary btn-xs">Mở Ma trận Trực quan</a>
    </div>
    <div class="card-body">
        <div class="chart-box">
            <canvas id="chart-day-sessions"></canvas>
        </div>
    </div>
</div>

<!-- Row 3: Daily Gantt Timeline (07:00 - 20:30) showing Teaching Blocks, Travel Risks & Dead Gaps -->
<?php if (!empty($timeline) && !empty($timeline['days'])): ?>
    <div class="card mb-3">
        <div class="card-header">
            <div>
                <h2 class="card-title" id="timeline-teacher-title">Dòng thời gian Phân bổ (07:00 – 20:30): <?= e((string) ($timeline['teacher_name'] ?? '')) ?></h2>
                <div class="text-xs text-muted">Hiển thị trực quan ca dạy theo màu cơ sở, khoảng trống chết và cảnh báo di chuyển trong ngày</div>
            </div>
            <div class="flex gap-1 flex-wrap text-xs">
                <span class="badge badge-danger"><span class="legend-swatch" style="background:#dc2626;"></span>Không kịp ĐC</span>
                <span class="badge badge-warning"><span class="legend-swatch" style="border:1px dashed #d97706;background:#fef3c7;"></span>Khoảng trống chết</span>
                <a href="<?= e(url('/portal?teacher_id=' . (int) $timeline['teacher_id'])) ?>" id="timeline-portal-link" class="btn btn-secondary btn-xs">Mở Portal GV</a>
            </div>
        </div>
        <div class="card-body" id="timeline-card-body">
            <div class="timeline-header">
                <div class="text-xs text-muted font-bold">Thứ / Giờ</div>
                <div class="timeline-ticks">
                    <?php foreach ($timeline['ticks'] as $tick): ?>
                        <span class="timeline-tick align-<?= e((string) $tick['align']) ?>"
                              style="left:<?= e((string) $tick['left_pct']) ?>%;"><?= e((string) $tick['label']) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php foreach ($timeline['days'] as $tDay): ?>
                <div class="timeline-row-group">
                    <div class="timeline-row">
                        <div class="timeline-day-label"><?= e((string) $tDay['label']) ?></div>
                        <div class="timeline-track">
                            <?php foreach ($timeline['ticks'] as $tick): ?>
                                <?php if ($tick['left_pct'] > 0 && $tick['left_pct'] < 100): ?>
                                    <span class="timeline-gridline" style="left:<?= e((string) $tick['left_pct']) ?>%;"></span>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <?php foreach ($tDay['gaps'] as $gap): ?>
                                <?php if (!empty($gap['is_narrow'])): ?>
                                    <div class="timeline-gap-marker <?= e((string) $gap['type']) ?>"
                                         style="left:<?= e((string) $gap['left_pct']) ?>%;width:<?= e((string) $gap['width_pct']) ?>%;"
                                         title="<?= e((string) $gap['label']) ?>"></div>
                                <?php else: ?>
                                    <div class="timeline-gap <?= e((string) $gap['type']) ?>"
                                         style="left:<?= e((string) $gap['left_pct']) ?>%;width:<?= e((string) $gap['width_pct']) ?>%;"
                                         title="<?= e((string) $gap['label']) ?>">
                                        <?= e((string) $gap['label']) ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <?php foreach ($tDay['blocks'] as $blk): ?>
                                <a href="<?= e(url('/schedules/edit/' . (int) $blk['id'])) ?>"
                                   class="timeline-block"
                                   style="left:<?= e((string) $blk['left_pct']) ?>%;width:<?= e((string) $blk['width_pct']) ?>%;background:<?= e((string) $blk['branch_color']) ?>;"
                                   title="<?= e($blk['subject_name'] . ' | ' . $blk['branch_code'] . ' P.' . $blk['room'] . ' (' . $blk['time_range'] . ')') ?>">
                                    <strong><?= e($blk['branch_code'] . ' · ' . $blk['room']) ?></strong>
                                    <span style="opacity:.92;"><?= e($blk['subject_name']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php
                        $narrowGaps = array_values(array_filter($tDay['gaps'], static fn(array $g): bool => !empty($g['is_narrow'])));
                    ?>
                    <?php if (!empty($narrowGaps)): ?>
                        <div class="timeline-day-notes">
                            <div></div>
                            <div class="flex gap-1 flex-wrap">
                                <?php foreach ($narrowGaps as $ng): ?>
                                    <span class="badge <?= $ng['type'] === 'impossible_travel' ? 'badge-danger' : 'badge-warning' ?>">
                                        <?= e((string) $ng['label']) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Optimization Alert Center -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Trung tâm Cảnh báo Tối ưu (Optimization Center)</h2>
        <div class="flex gap-1 flex-wrap">
            <span class="badge badge-danger">Không kịp ĐC: <?= (int) $kpi['counts']['impossible_travel'] ?></span>
            <span class="badge badge-warning">ĐC sát giờ: <?= (int) $kpi['counts']['tight_travel'] ?></span>
            <span class="badge badge-warning">Khoảng trống chết: <?= (int) $kpi['counts']['dead_gap'] ?></span>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (empty($alerts)): ?>
            <div class="card-body text-muted">
                Không ghi nhận cảnh báo di chuyển hoặc khoảng trống chết trong phạm vi lọc hiện tại.
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                <tr>
                    <th>Loại cảnh báo</th>
                    <th>Giảng viên &amp; Ngày</th>
                    <th>Chi tiết phân tích</th>
                    <th>Gợi ý Khắc phục Tự động</th>
                    <th style="text-align:right;">Hành động</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($alerts as $alert): ?>
                    <?php
                        $typeLabel = match ($alert['type']) {
                            'IMPOSSIBLE_TRAVEL' => ['Không kịp di chuyển', 'badge-danger'],
                            'TIGHT_TRAVEL'      => ['Di chuyển sát giờ', 'badge-warning'],
                            'DEAD_GAP'          => ['Khoảng trống chết', 'badge-warning'],
                            'EXCESSIVE_COMMUTE' => ['Di chuyển quá nhiều', 'badge-warning'],
                            default             => [$alert['type'], 'badge-neutral'],
                        };
                        $fix = $alert['fix'] ?? null;
                        $targetId = (int) ($alert['to_schedule_id'] ?? 0);
                    ?>
                    <tr>
                        <td><span class="badge <?= e($typeLabel[1]) ?>"><?= e($typeLabel[0]) ?></span></td>
                        <td>
                            <strong><?= e((string) $alert['teacher_name']) ?></strong>
                            <div class="text-xs text-muted"><?= e(day_label((int) $alert['day_of_week'])) ?></div>
                        </td>
                        <td><?= e((string) $alert['message']) ?></td>
                        <td>
                            <?php if (is_array($fix)): ?>
                                <div class="text-sm" style="color:#065f46;font-weight:600;"><?= e((string) $fix['summary']) ?></div>
                                <div class="text-xs text-muted">
                                    Lý do: <?= e(implode(' | ', (array) ($fix['suggested']['reasons'] ?? []))) ?>
                                </div>
                            <?php else: ?>
                                <span class="text-xs text-muted">Mở lịch để điều chỉnh thủ công</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;white-space:nowrap;">
                            <?php if (is_array($fix) && $targetId > 0): ?>
                                <?php
                                    $fixUrl = url('/schedules/edit/' . $targetId)
                                        . '?day_of_week=' . (int) $fix['suggested']['day_of_week']
                                        . '&start_period=' . (int) $fix['suggested']['start_period']
                                        . '&end_period=' . (int) $fix['suggested']['end_period'];
                                ?>
                                <form method="POST" action="<?= e(url('/schedules/auto-fix/' . $targetId)) ?>" style="display:inline;"
                                      data-confirm="Áp dụng ngay gợi ý chuyển lịch sang <?= e((string) $fix['suggested']['day_label']) ?> - <?= e((string) $fix['suggested']['shift_label']) ?>?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="day_of_week" value="<?= (int) $fix['suggested']['day_of_week'] ?>">
                                    <input type="hidden" name="start_period" value="<?= (int) $fix['suggested']['start_period'] ?>">
                                    <input type="hidden" name="end_period" value="<?= (int) $fix['suggested']['end_period'] ?>">
                                    <button type="submit" class="btn btn-success btn-xs">Áp dụng ngay</button>
                                </form>
                                <a href="<?= e($fixUrl) ?>" class="btn btn-secondary btn-xs">Sửa chi tiết</a>
                            <?php elseif ($targetId > 0): ?>
                                <a href="<?= e(url('/schedules/edit/' . $targetId)) ?>" class="btn btn-secondary btn-xs">Chỉnh sửa</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
    window.HUIT_INITIAL_CHARTS = <?= json_encode($charts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
