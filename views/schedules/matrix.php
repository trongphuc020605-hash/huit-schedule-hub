<?php

declare(strict_types=1);
?>

<form method="GET" action="<?= e(url('/schedules/matrix')) ?>" class="filter-bar">
    <div class="filter-item" style="max-width:200px;">
        <label class="form-label">Chế độ Ma trận</label>
        <select name="mode" class="form-select" onchange="this.form.submit()">
            <option value="teacher" <?= $mode === 'teacher' ? 'selected' : '' ?>>Theo Giảng viên</option>
            <option value="branch" <?= $mode === 'branch' ? 'selected' : '' ?>>Theo Cơ sở</option>
        </select>
    </div>

    <?php if ($mode === 'teacher'): ?>
        <div class="filter-item">
            <label class="form-label">Chọn Giảng viên</label>
            <select name="teacher_id" class="form-select" onchange="this.form.submit()">
                <?php foreach ($teachers as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= ((int) $t['id'] === (int) $selectedTeacherId) ? 'selected' : '' ?>>
                        <?= e($t['full_name'] . ' — ' . $t['expertise']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php else: ?>
        <div class="filter-item">
            <label class="form-label">Chọn Cơ sở</label>
            <select name="branch_id" class="form-select" onchange="this.form.submit()">
                <?php foreach ($branches as $b): ?>
                    <option value="<?= (int) $b['id'] ?>" <?= ((int) $b['id'] === (int) $selectedBranchId) ? 'selected' : '' ?>>
                        <?= e($b['code'] . ' — ' . $b['branch_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>

    <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Xem Ma trận</button>
    </div>
</form>

<!-- Clean Color Swatch Legend -->
<div class="flex gap-2 flex-wrap mb-2 text-xs">
    <span class="badge badge-success">
        <span class="legend-swatch" style="background:#10b981;"></span>
        Tiết trống GV đã đăng ký (Bấm Phân lịch)
    </span>
    <span class="badge badge-warning">
        <span class="legend-swatch" style="background:#d97706;"></span>
        Ô tối ưu liền ca cùng cơ sở (0p di chuyển)
    </span>
    <span class="badge badge-danger">
        <span class="legend-swatch" style="background:#dc2626;"></span>
        Viền đỏ: Không kịp di chuyển
    </span>
    <span class="badge badge-warning">
        <span class="legend-swatch" style="border:1.5px dashed #b45309;background:transparent;"></span>
        Viền nét đứt: Khoảng trống chết / Sát giờ
    </span>
</div>

<?php $colMaxDay = (int) ($maxDay ?? 7); ?>
<div class="card">
    <div class="table-wrap">
        <table class="matrix-table">
            <thead>
            <tr>
                <th style="width:135px;">Ca / Thứ</th>
                <?php for ($d = 2; $d <= $colMaxDay; $d++): ?>
                    <th><?= e(day_label($d)) ?></th>
                <?php endfor; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($shifts as $shiftNum => $shift): ?>
                <tr>
                    <td class="matrix-shift-col">
                        <div><?= e($shift['name']) ?></div>
                        <div class="text-xs text-muted">Tiết <?= (int) $shift['start_period'] ?>–<?= (int) $shift['end_period'] ?></div>
                        <div class="text-xs text-muted"><?= e($shift['time']) ?></div>
                    </td>
                    <?php for ($d = 2; $d <= $colMaxDay; $d++): ?>
                        <?php
                            $cell = $grid[$d][$shiftNum];
                            $hasSchedules = !empty($cell['schedules']);
                            $hasAvail = !empty($cell['availabilities']);
                            $optimal = $cell['optimal'];

                            $cellClasses = [];
                            if (!$hasSchedules && $hasAvail) {
                                $cellClasses[] = 'matrix-cell-avail';
                            }
                            if (!$hasSchedules && $optimal !== null) {
                                $cellClasses[] = 'matrix-cell-optimal';
                            }

                            $createParams = [
                                'day_of_week'  => $d,
                                'start_period' => $shift['start_period'],
                                'end_period'   => $shift['end_period'],
                            ];
                            if ($mode === 'teacher') {
                                $createParams['teacher_id'] = $selectedTeacherId;
                                if ($optimal !== null) {
                                    $createParams['branch_id'] = $optimal['branch_id'];
                                } elseif ($hasAvail && !empty($cell['availabilities'][0]['preferred_branch_id'])) {
                                    $createParams['branch_id'] = $cell['availabilities'][0]['preferred_branch_id'];
                                }
                            } else {
                                $createParams['branch_id'] = $selectedBranchId;
                            }
                            $quickUrl = url('/schedules/create?' . http_build_query($createParams));
                        ?>
                        <td class="<?= e(implode(' ', $cellClasses)) ?>">
                            <?php if ($hasSchedules): ?>
                                <?php foreach ($cell['schedules'] as $s): ?>
                                    <?php
                                        $flags = $scheduleFlags[(int) $s['id']] ?? [];
                                        $evCls = in_array('IMPOSSIBLE_TRAVEL', $flags, true)
                                            ? 'has-critical'
                                            : (!empty($flags) ? 'has-warning' : '');
                                    ?>
                                    <div class="matrix-event <?= e($evCls) ?>" style="background:<?= e((string) $s['branch_color']) ?>;">
                                        <div style="font-weight:700;"><?= e((string) $s['subject_name']) ?></div>
                                        <div style="opacity:.92;">
                                            <?= e((string) $s['branch_code']) ?> | P.<?= e((string) $s['room']) ?>
                                            <?php if ($mode === 'branch'): ?>
                                                <br><?= e((string) $s['teacher_name']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex justify-between items-center mt-1">
                                            <?php if (!empty($flags)): ?>
                                                <span style="background:rgba(0,0,0,0.28);padding:1px 5px;border-radius:4px;font-size:0.65rem;">
                                                    <?= e(implode(', ', $flags)) ?>
                                                </span>
                                            <?php else: ?>
                                                <span></span>
                                            <?php endif; ?>
                                            <a href="<?= e(url('/schedules/edit/' . (int) $s['id'])) ?>" style="color:#fff;text-decoration:underline;font-size:0.68rem;">Sửa</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="display:flex;flex-direction:column;justify-content:space-between;height:100%;gap:0.35rem;">
                                    <div>
                                        <?php if ($optimal !== null): ?>
                                            <div class="text-xs" style="color:#b45309;font-weight:700;">[Ưu tiên] Liền ca <?= e((string) $optimal['branch_code']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($hasAvail): ?>
                                            <div class="text-xs" style="color:#065f46;font-weight:600;">
                                                GV đăng ký rảnh
                                                <?php if (!empty($cell['availabilities'][0]['preferred_branch_code'])): ?>
                                                    (<?= e((string) $cell['availabilities'][0]['preferred_branch_code']) ?>)
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <a href="<?= e($quickUrl) ?>" class="btn btn-secondary btn-xs" style="width:100%;">+ Phân lịch</a>
                                </div>
                            <?php endif; ?>
                        </td>
                    <?php endfor; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
