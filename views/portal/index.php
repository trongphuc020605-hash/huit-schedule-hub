<?php

declare(strict_types=1);

use HuitSchedule\Core\Auth;

$tokenQuery = $viaToken && $token ? ('&token=' . urlencode($token)) : '';
$teacherQuery = (Auth::isAdmin() && !$viaToken) ? ('&teacher_id=' . (int) $teacher['id']) : '';
$basePortalLink = full_base_url() . '/portal?token=' . urlencode((string) $teacher['portal_token']);
$icsQs = ltrim($tokenQuery . $teacherQuery, '&');
$icsUrl = url('/portal/export-ics' . ($icsQs !== '' ? ('?' . $icsQs) : ''));
?>

<!-- Teacher Header Banner -->
<div class="card mb-3">
    <div class="card-body flex justify-between items-center flex-wrap gap-3">
        <div>
            <div class="flex items-center gap-2 flex-wrap">
                <h2 style="margin:0;color:#0f172a;"><?= e((string) $teacher['full_name']) ?></h2>
                <span class="badge badge-primary"><?= (int) $totalPeriods ?> / <?= (int) $teacher['max_periods_per_week'] ?> tiết/tuần</span>
                <?php if ($readOnly): ?>
                    <span class="badge badge-neutral">Chế độ xem trực tiếp (Read-only)</span>
                <?php endif; ?>
            </div>
            <div class="text-sm text-muted mt-1">
                Email: <?= e((string) $teacher['email']) ?> | SĐT: <?= e((string) $teacher['phone']) ?> | Chuyên môn: <strong><?= e((string) $teacher['expertise']) ?></strong>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <?php if (Auth::isAdmin() && !$viaToken && !empty($allTeachers)): ?>
                <form method="GET" action="<?= e(url('/portal')) ?>" style="margin:0;">
                    <input type="hidden" name="tab" value="<?= e($tab) ?>">
                    <select name="teacher_id" class="form-select" onchange="this.form.submit()" style="min-width:210px;">
                        <?php foreach ($allTeachers as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" <?= ((int) $t['id'] === (int) $teacher['id']) ? 'selected' : '' ?>>
                                Xem GV: <?= e((string) $t['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>

            <button type="button" class="btn btn-secondary btn-sm" data-copy="<?= e($basePortalLink) ?>">
                Sao chép Link cá nhân
            </button>
            <a href="<?= e($icsUrl) ?>" class="btn btn-success btn-sm">
                Xuất lịch .ICS (Google/Apple Calendar)
            </a>
        </div>
    </div>
</div>

<!-- Travel Timeline & Alerts Widget -->
<?php if (!empty($commuteDays) || !empty($alerts)): ?>
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Lộ trình Cơ sở &amp; Cảnh báo Di chuyển trong Tuần</h3>
            <span class="badge <?= empty($alerts) ? 'badge-success' : 'badge-warning' ?>">
                <?= count($commuteDays) ?> ngày đi từ 2 cơ sở trở lên | <?= count($alerts) ?> cảnh báo
            </span>
        </div>
        <div class="card-body">
            <?php foreach ($commuteDays as $cd): ?>
                <div style="padding:0.65rem 0.85rem;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:0.55rem;background:#f8fafc;">
                    <div class="flex justify-between items-center flex-wrap gap-2">
                        <strong><?= e(day_label((int) $cd['day_of_week'])) ?></strong>
                        <span class="text-xs text-muted">
                            Chuyển cơ sở <?= (int) $cd['commute_count'] ?> lần | Ước tính tổng <?= (int) $cd['total_travel_minutes'] ?> phút di chuyển
                        </span>
                    </div>
                    <div class="mt-1 flex gap-2 flex-wrap">
                        <?php foreach ($cd['legs'] as $leg): ?>
                            <?php
                                $legCls = $leg['status'] === 'impossible'
                                    ? 'badge-danger'
                                    : ($leg['status'] === 'tight' ? 'badge-warning' : 'badge-success');
                            ?>
                            <span class="badge <?= e($legCls) ?>">
                                <?= e($leg['from_branch_code'] . ' (' . $leg['from_time'] . ') -> ' . $leg['to_branch_code'] . ' (' . $leg['to_time'] . ')') ?>
                                : cần <?= (int) $leg['travel_minutes'] ?>p / nghỉ <?= (int) $leg['break_minutes'] ?>p (<?= e((string) $leg['distance_km']) ?> km)
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php foreach ($alerts as $al): ?>
                <div class="alert <?= $al['severity'] === 'critical' ? 'alert-error' : 'alert-warning' ?>" style="margin-bottom:0.45rem;">
                    <div><strong><?= e(day_label((int) $al['day_of_week'])) ?>:</strong> <?= e((string) $al['message']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Portal Tabs -->
<div class="tabs">
    <a class="tab-btn<?= $tab === 'week' ? ' active' : '' ?>"
       href="<?= e(url('/portal?tab=week&week=' . urlencode($weekStart->format('Y-m-d')) . $tokenQuery . $teacherQuery)) ?>">
        Lịch Tuần (Weekly Grid)
    </a>
    <a class="tab-btn<?= $tab === 'month' ? ' active' : '' ?>"
       href="<?= e(url('/portal?tab=month&month=' . urlencode($monthParam) . $tokenQuery . $teacherQuery)) ?>">
        Lịch Tháng (Monthly Calendar)
    </a>
    <?php if (!$readOnly): ?>
        <a class="tab-btn<?= $tab === 'availability' ? ' active' : '' ?>"
           href="<?= e(url('/portal?tab=availability' . $teacherQuery)) ?>">
            Đăng ký Tiết trống cá nhân (<?= count($availabilities) ?>)
        </a>
    <?php endif; ?>
</div>

<?php if ($tab === 'week'): ?>
    <!-- WEEKLY TIMETABLE -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                Tuần từ <?= e($weekStart->format('d/m/Y')) ?> đến <?= e($weekEnd->format('d/m/Y')) ?>
            </h3>
            <div class="flex gap-1">
                <a class="btn btn-secondary btn-sm"
                   href="<?= e(url('/portal?tab=week&week=' . urlencode($prevWeek) . $tokenQuery . $teacherQuery)) ?>">Tuần trước</a>
                <a class="btn btn-primary btn-sm"
                   href="<?= e(url('/portal?tab=week&week=' . urlencode(date('Y-m-d')) . $tokenQuery . $teacherQuery)) ?>">Tuần hiện tại</a>
                <a class="btn btn-secondary btn-sm"
                   href="<?= e(url('/portal?tab=week&week=' . urlencode($nextWeek) . $tokenQuery . $teacherQuery)) ?>">Tuần sau</a>
            </div>
        </div>
        <div class="card-body">
            <div class="weekly-grid">
                <?php foreach ($weekDays as $wd): ?>
                    <div class="week-col<?= $wd['is_today'] ? ' is-today' : '' ?>">
                        <div class="week-col-head">
                            <div style="font-weight:700;"><?= e($wd['label']) ?></div>
                            <div class="text-xs" style="opacity:.85;"><?= e($wd['display']) ?></div>
                        </div>
                        <div class="week-col-body">
                            <?php if (empty($wd['schedules'])): ?>
                                <div class="text-xs text-muted" style="text-align:center;margin-top:1.5rem;">Không có lịch</div>
                            <?php else: ?>
                                <?php foreach ($wd['schedules'] as $s): ?>
                                    <div style="border-left:4px solid <?= e((string) $s['branch_color']) ?>;background:#f8fafc;border-radius:6px;padding:0.55rem 0.6rem;border-top:1px solid #e2e8f0;border-right:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;">
                                        <div class="text-xs" style="font-weight:700;color:<?= e((string) $s['branch_color']) ?>;">
                                            <?= e((string) $s['time_range']) ?>
                                        </div>
                                        <div style="font-weight:700;font-size:0.82rem;margin:2px 0;"><?= e((string) $s['subject_name']) ?></div>
                                        <div class="text-xs text-muted"><?= e((string) $s['shift_or_periods']) ?></div>
                                        <div class="text-xs mt-1">
                                            <span class="badge" style="background:<?= e((string) $s['branch_color']) ?>;color:#fff;"><?= e((string) $s['branch_code']) ?></span>
                                            <strong>P.<?= e((string) $s['room']) ?></strong>
                                        </div>
                                        <div class="text-xs text-muted mt-1">
                                            <?= e((string) $s['branch_address']) ?>
                                            <a href="https://www.google.com/maps/search/?api=1&query=<?= rawurlencode((string) $s['branch_address']) ?>"
                                               target="_blank" rel="noopener noreferrer" style="display:inline-block;margin-left:4px;">[Bản đồ]</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'month'): ?>
    <!-- MONTHLY CALENDAR -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= e($monthTitle) ?></h3>
            <div class="flex gap-1">
                <a class="btn btn-secondary btn-sm"
                   href="<?= e(url('/portal?tab=month&month=' . urlencode($prevMonth) . $tokenQuery . $teacherQuery)) ?>">Tháng trước</a>
                <a class="btn btn-primary btn-sm"
                   href="<?= e(url('/portal?tab=month&month=' . urlencode(date('Y-m')) . $tokenQuery . $teacherQuery)) ?>">Tháng hiện tại</a>
                <a class="btn btn-secondary btn-sm"
                   href="<?= e(url('/portal?tab=month&month=' . urlencode($nextMonth) . $tokenQuery . $teacherQuery)) ?>">Tháng sau</a>
            </div>
        </div>
        <div class="card-body">
            <div class="flex gap-2 flex-wrap mb-2">
                <?php foreach ($branches as $b): ?>
                    <span class="badge" style="background:<?= e((string) $b['color_hex']) ?>;color:#fff;">
                        <?= e($b['code'] . ' — ' . $b['branch_name']) ?>
                    </span>
                <?php endforeach; ?>
            </div>

            <div class="table-wrap">
            <div class="month-grid" style="min-width:720px;">
                <?php foreach (['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ nhật'] as $dow): ?>
                    <div class="month-dow"><?= e($dow) ?></div>
                <?php endforeach; ?>

                <?php foreach ($monthCells as $cell): ?>
                    <?php
                        $cls = 'month-cell';
                        if (!$cell['in_month']) $cls .= ' other-month';
                        if ($cell['is_today']) $cls .= ' is-today';
                    ?>
                    <div class="<?= e($cls) ?>">
                        <div class="month-day-num"><?= (int) $cell['day_num'] ?></div>
                        <?php foreach ($cell['events'] as $ev): ?>
                            <button type="button" class="month-event-pill"
                                    style="background:<?= e($ev['branch_color']) ?>;"
                                    data-event='<?= e(json_encode($ev, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
                                    title="<?= e($ev['subject_name'] . ' (' . $ev['time_range'] . ')') ?>">
                                <?= e($ev['branch_code'] . ' ' . substr($ev['time_range'], 0, 5) . ' ' . $ev['subject_name']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            </div>
        </div>
    </div>

    <!-- Modal for Monthly Event Detail -->
    <div class="modal-backdrop" id="portal-event-modal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="card-title" id="ev-modal-title">Chi tiết buổi giảng</h3>
                <button type="button" class="btn btn-ghost btn-xs" data-close-modal="portal-event-modal">Đóng</button>
            </div>
            <div class="modal-body" id="ev-modal-body"></div>
            <div class="modal-footer">
                <a href="https://www.google.com/maps" id="ev-modal-maps" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm">Mở Google Maps</a>
                <button type="button" class="btn btn-secondary btn-sm" data-close-modal="portal-event-modal">Đóng</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const esc = (v) => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            document.querySelectorAll('.month-event-pill').forEach(function (pill) {
                pill.addEventListener('click', function () {
                    const ev = JSON.parse(pill.getAttribute('data-event'));
                    document.getElementById('ev-modal-title').textContent = ev.subject_name;
                    document.getElementById('ev-modal-body').innerHTML =
                        '<p><strong>Ngày:</strong> ' + esc(ev.day_label) + ', ' + esc(ev.date) + '</p>' +
                        '<p><strong>Thời gian:</strong> ' + esc(ev.shift) + ' (' + esc(ev.time_range) + ')</p>' +
                        '<p><strong>Cơ sở:</strong> ' + esc(ev.branch_code) + ' — ' + esc(ev.branch_name) + '</p>' +
                        '<p><strong>Phòng học:</strong> ' + esc(ev.room) + '</p>' +
                        '<p><strong>Địa chỉ:</strong> ' + esc(ev.branch_address) + '</p>' +
                        (ev.notes ? '<p><strong>Ghi chú:</strong> ' + esc(ev.notes) + '</p>' : '');
                    document.getElementById('ev-modal-maps').href =
                        'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(ev.branch_address);
                    window.HuitApp.openModal('portal-event-modal');
                });
            });
        });
    </script>

<?php else: ?>
    <!-- SELF-REGISTERED AVAILABILITY TAB -->
    <div class="grid-2">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Đăng ký Khung Tiết trống Cá nhân</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= e(url('/portal/availabilities')) ?>">
                    <?= csrf_field() ?>
                    <?php if (Auth::isAdmin()): ?>
                        <input type="hidden" name="teacher_id" value="<?= (int) $teacher['id'] ?>">
                    <?php endif; ?>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Thứ trong tuần</label>
                            <select name="day_of_week" class="form-select">
                                <?php foreach (app_config('days', []) as $dNum => $dLabel): ?>
                                    <option value="<?= (int) $dNum ?>"><?= e($dLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Cơ sở mong muốn</label>
                            <select name="preferred_branch_id" class="form-select">
                                <option value="">Không cố định</option>
                                <?php foreach ($branches as $b): ?>
                                    <option value="<?= (int) $b['id'] ?>"><?= e($b['code'] . ' — ' . $b['branch_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tiết bắt đầu (1–15)</label>
                            <input type="number" name="start_period" class="form-control" min="1" max="15" value="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tiết kết thúc (1–15)</label>
                            <input type="number" name="end_period" class="form-control" min="1" max="15" value="3">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Lưu Khung Tiết trống</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Các khung tiết trống đã đăng ký</h3>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr><th>Thứ</th><th>Ca / Tiết</th><th>Cơ sở ưu tiên</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($availabilities)): ?>
                        <tr><td colspan="4" class="text-muted" style="text-align:center;padding:1.5rem;">Chưa có khung tiết trống nào.</td></tr>
                    <?php else: ?>
                        <?php foreach ($availabilities as $av): ?>
                            <tr>
                                <td><strong><?= e(day_label((int) $av['day_of_week'])) ?></strong></td>
                                <td><?= e((string) $av['shift_or_periods']) ?></td>
                                <td><?= !empty($av['preferred_branch_code']) ? '<span class="badge badge-primary">' . e((string) $av['preferred_branch_code']) . '</span>' : '<span class="text-xs text-muted">Bất kỳ</span>' ?></td>
                                <td style="text-align:right;">
                                    <form method="POST" action="<?= e(url('/portal/availabilities/delete/' . (int) $av['id'])) ?>" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <?php if (Auth::isAdmin()): ?>
                                            <input type="hidden" name="teacher_id" value="<?= (int) $teacher['id'] ?>">
                                        <?php endif; ?>
                                        <button type="submit" class="btn btn-danger btn-xs">Xóa</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>
