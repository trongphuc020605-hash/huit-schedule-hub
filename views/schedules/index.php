<?php

declare(strict_types=1);

use HuitSchedule\Core\View;

$csvQuery = http_build_query(array_filter($filters, static fn($v): bool => $v !== '' && $v !== null));
$csvUrl = url('/schedules/export-csv' . ($csvQuery !== '' ? ('?' . $csvQuery) : ''));
?>

<form method="GET" action="<?= e(url('/schedules')) ?>" class="filter-bar">
    <div class="filter-item">
        <label class="form-label">Cơ sở</label>
        <select name="branch_id" class="form-select">
            <option value="">Tất cả</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= (int) $b['id'] ?>" <?= ((int) ($filters['branch_id'] ?? 0) === (int) $b['id']) ? 'selected' : '' ?>>
                    <?= e($b['code'] . ' — ' . $b['branch_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-item">
        <label class="form-label">Giảng viên</label>
        <select name="teacher_id" class="form-select">
            <option value="">Tất cả</option>
            <?php foreach ($teachers as $t): ?>
                <option value="<?= (int) $t['id'] ?>" <?= ((int) ($filters['teacher_id'] ?? 0) === (int) $t['id']) ? 'selected' : '' ?>>
                    <?= e($t['full_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-item" style="max-width:145px;">
        <label class="form-label">Thứ</label>
        <select name="day_of_week" class="form-select">
            <option value="">Tất cả</option>
            <?php foreach (app_config('days', []) as $dNum => $dLabel): ?>
                <option value="<?= (int) $dNum ?>" <?= ((int) ($filters['day_of_week'] ?? 0) === (int) $dNum) ? 'selected' : '' ?>>
                    <?= e($dLabel) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-item" style="max-width:170px;">
        <label class="form-label">Trạng thái tối ưu</label>
        <select name="status" class="form-select">
            <option value="">Tất cả trạng thái</option>
            <option value="alerts" <?= (($filters['status'] ?? '') === 'alerts') ? 'selected' : '' ?>>Chỉ lịch có cảnh báo</option>
        </select>
    </div>
    <div class="filter-item">
        <label class="form-label">Tìm kiếm</label>
        <input type="text" name="search" class="form-control" placeholder="Môn học, phòng, GV..."
               value="<?= e((string) ($filters['search'] ?? '')) ?>">
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Lọc</button>
        <a href="<?= e(url('/schedules')) ?>" class="btn btn-ghost">Đặt lại</a>
        <a href="<?= e($csvUrl) ?>" class="btn btn-secondary">Xuất CSV</a>
        <a href="<?= e(url('/schedules/create')) ?>" class="btn btn-success">Phân lịch mới</a>
    </div>
</form>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Danh sách Lịch Giảng dạy (<?= (int) ($totalItems ?? count($schedules)) ?> lớp học phần)</h2>
        <a href="<?= e(url('/schedules/matrix')) ?>" class="btn btn-secondary btn-sm">Xem dạng Ma trận</a>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Thứ / Ca &amp; Giờ</th>
                <th>Môn học</th>
                <th>Giảng viên</th>
                <th>Cơ sở &amp; Phòng</th>
                <th>Hiệu lực</th>
                <th>Trạng thái Tối ưu</th>
                <th style="text-align:right;">Thao tác</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($schedules)): ?>
                <tr><td colspan="7" class="text-muted" style="text-align:center;padding:2rem;">Chưa có lịch dạy nào khớp bộ lọc.</td></tr>
            <?php else: ?>
                <?php foreach ($schedules as $s): ?>
                    <?php $flags = $scheduleFlags[(int) $s['id']] ?? []; ?>
                    <tr>
                        <td>
                            <strong><?= e(day_label((int) $s['day_of_week'])) ?></strong>
                            <div class="text-xs"><?= e((string) $s['shift_or_periods']) ?></div>
                            <div class="text-xs text-muted"><?= e(period_time_range((int) $s['start_period'], (int) $s['end_period'])) ?></div>
                        </td>
                        <td>
                            <strong><?= e((string) $s['subject_name']) ?></strong>
                            <?php if (!empty($s['notes'])): ?>
                                <div class="text-xs text-muted"><?= e((string) $s['notes']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e((string) $s['teacher_name']) ?></td>
                        <td>
                            <span class="badge" style="background:<?= e((string) $s['branch_color']) ?>;color:#fff;"><?= e((string) $s['branch_code']) ?></span>
                            <strong>Phòng <?= e((string) $s['room']) ?></strong>
                            <div class="text-xs text-muted"><?= e((string) $s['branch_name']) ?></div>
                        </td>
                        <td class="text-xs">
                            <?= e((string) $s['start_date']) ?><br>đến <?= e((string) $s['end_date']) ?>
                        </td>
                        <td>
                            <?php if (empty($flags)): ?>
                                <span class="badge badge-success">Tối ưu</span>
                            <?php else: ?>
                                <?php foreach ($flags as $fl): ?>
                                    <?php
                                        $flCls = $fl === 'IMPOSSIBLE_TRAVEL' ? 'badge-danger' : 'badge-warning';
                                        $flText = match ($fl) {
                                            'IMPOSSIBLE_TRAVEL' => 'Không kịp ĐC',
                                            'TIGHT_TRAVEL'      => 'ĐC sát giờ',
                                            'DEAD_GAP'          => 'Khoảng trống chết',
                                            'EXCESSIVE_COMMUTE' => 'ĐC nhiều lần',
                                            default             => $fl,
                                        };
                                    ?>
                                    <span class="badge <?= e($flCls) ?>"><?= e($flText) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;white-space:nowrap;">
                            <a href="<?= e(url('/schedules/edit/' . (int) $s['id'])) ?>" class="btn btn-secondary btn-xs">Sửa</a>
                            <form method="POST" action="<?= e(url('/schedules/delete/' . (int) $s['id'])) ?>" style="display:inline;"
                                  data-confirm="Xóa lịch dạy môn <?= e((string) $s['subject_name']) ?>?">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-danger btn-xs">Xóa</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php View::partial('partials/pagination', ['pagination' => $pagination ?? []]); ?>
</div>
