<?php

declare(strict_types=1);

use HuitSchedule\Core\View;
?>

<form method="GET" action="<?= e(url('/teachers')) ?>" class="filter-bar">
    <div class="filter-item">
        <label class="form-label" for="t-search">Tìm kiếm Giảng viên / Chuyên môn</label>
        <input type="text" id="t-search" name="search" class="form-control"
               placeholder="Nhập họ tên, email hoặc từ khóa chuyên môn (VD: PHP, AI, MySQL)..."
               value="<?= e((string) ($search ?? '')) ?>">
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Tìm kiếm</button>
        <?php if (!empty($search)): ?>
            <a href="<?= e(url('/teachers')) ?>" class="btn btn-ghost">Đặt lại</a>
        <?php endif; ?>
        <button type="button" class="btn btn-success" data-open-modal="modal-add-teacher">Thêm Giảng viên</button>
    </div>
</form>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Danh sách Giảng viên &amp; Cán bộ (<?= (int) ($totalItems ?? count($teachers)) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Họ tên &amp; Vai trò</th>
                <th>Liên hệ</th>
                <th>Chuyên môn giảng dạy</th>
                <th>Tải tuần / Tiết trống</th>
                <th>Portal Cá nhân</th>
                <th style="text-align:right;">Thao tác</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($teachers)): ?>
                <tr><td colspan="6" class="text-muted" style="text-align:center;padding:2rem;">Không tìm thấy giảng viên nào khớp từ khóa tìm kiếm.</td></tr>
            <?php else: ?>
            <?php foreach ($teachers as $t): ?>
                <?php
                    $tid = (int) $t['id'];
                    $portalUrl = full_base_url() . '/portal?token=' . urlencode((string) $t['portal_token']);
                    $totalP = (int) $t['total_periods'];
                    $maxP = (int) $t['max_periods_per_week'];
                    $loadCls = $totalP > $maxP ? 'badge-danger' : ($totalP >= $maxP - 3 ? 'badge-warning' : 'badge-success');
                ?>
                <tr>
                    <td>
                        <strong><?= e((string) $t['full_name']) ?></strong>
                        <div class="mt-1">
                            <span class="badge <?= $t['role'] === 'admin' ? 'badge-primary' : 'badge-neutral' ?>">
                                <?= $t['role'] === 'admin' ? 'Admin' : 'Giảng viên' ?>
                            </span>
                        </div>
                    </td>
                    <td>
                        <div><?= e((string) $t['email']) ?></div>
                        <div class="text-xs text-muted">SĐT: <?= e((string) $t['phone']) ?></div>
                    </td>
                    <td style="max-width:260px;">
                        <div class="text-sm"><?= e((string) $t['expertise']) ?></div>
                    </td>
                    <td>
                        <span class="badge <?= e($loadCls) ?>"><?= $totalP ?> / <?= $maxP ?> tiết</span>
                        <div class="text-xs text-muted mt-1">
                            <?= (int) $t['schedule_count'] ?> lớp | <?= (int) $t['availability_count'] ?> khung trống
                        </div>
                    </td>
                    <td>
                        <div class="flex gap-1 flex-wrap">
                            <button type="button" class="btn btn-secondary btn-xs"
                                    data-copy="<?= e($portalUrl) ?>"
                                    data-copy-msg="Đã sao chép Link Portal cá nhân của <?= e((string) $t['full_name']) ?>.">
                                Sao chép Link
                            </button>
                            <a href="<?= e(url('/portal?token=' . urlencode((string) $t['portal_token']))) ?>"
                               target="_blank" class="btn btn-ghost btn-xs">Mở trang</a>
                            <form method="POST" action="<?= e(url('/teachers/' . $tid . '/token')) ?>" style="display:inline;"
                                  data-confirm="Làm mới Portal Token của <?= e((string) $t['full_name']) ?>? Đường dẫn cũ sẽ ngừng hoạt động.">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-ghost btn-xs">Đổi Token</button>
                            </form>
                        </div>
                    </td>
                    <td style="text-align:right;white-space:nowrap;">
                        <button type="button" class="btn btn-secondary btn-xs" data-open-modal="avail-teacher-<?= $tid ?>">Tiết trống</button>
                        <button type="button" class="btn btn-secondary btn-xs" data-open-modal="edit-teacher-<?= $tid ?>">Sửa</button>
                        <form method="POST" action="<?= e(url('/teachers/delete/' . $tid)) ?>" style="display:inline;"
                              data-confirm="Xóa giảng viên <?= e((string) $t['full_name']) ?>?">
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

<!-- Modal Add Teacher -->
<div class="modal-backdrop" id="modal-add-teacher">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="card-title">Thêm Giảng viên Mới</h3>
            <button type="button" class="btn btn-ghost btn-xs" data-close-modal="modal-add-teacher">Đóng</button>
        </div>
        <form method="POST" action="<?= e(url('/teachers')) ?>">
            <?= csrf_field() ?>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group full">
                        <label class="form-label">Họ và tên *</label>
                        <input type="text" name="full_name" class="form-control" required placeholder="TS. Phạm Hoàng Nam">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email HUIT *</label>
                        <input type="email" name="email" class="form-control" required placeholder="namph@huit.edu.vn">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Số điện thoại *</label>
                        <input type="text" name="phone" class="form-control" required placeholder="0901234567">
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Chuyên môn (phân cách dấu phẩy) *</label>
                        <input type="text" name="expertise" class="form-control" required placeholder="PHP, Laravel, Phát triển phần mềm mã nguồn mở, MySQL">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Vai trò</label>
                        <select name="role" class="form-select">
                            <option value="teacher">Giảng viên</option>
                            <option value="admin">Cán bộ Đào tạo (Admin)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Định mức tối đa (tiết/tuần)</label>
                        <input type="number" name="max_periods_per_week" class="form-control" value="18" min="3" max="45">
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Mật khẩu khởi tạo (mặc định: Teacher@123)</label>
                        <input type="password" name="password" class="form-control" placeholder="Để trống dùng Teacher@123">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="modal-add-teacher">Hủy</button>
                <button type="submit" class="btn btn-primary">Tạo Giảng viên</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit & Availability Modals -->
<?php foreach ($teachers as $t): ?>
    <?php
        $tid = (int) $t['id'];
        $tAvails = $availGrouped[$tid] ?? [];
    ?>
    <div class="modal-backdrop" id="edit-teacher-<?= $tid ?>">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="card-title">Chỉnh sửa: <?= e((string) $t['full_name']) ?></h3>
                <button type="button" class="btn btn-ghost btn-xs" data-close-modal="edit-teacher-<?= $tid ?>">Đóng</button>
            </div>
            <form method="POST" action="<?= e(url('/teachers/update/' . $tid)) ?>">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group full">
                            <label class="form-label">Họ và tên</label>
                            <input type="text" name="full_name" class="form-control" required value="<?= e((string) $t['full_name']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required value="<?= e((string) $t['email']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Số điện thoại</label>
                            <input type="text" name="phone" class="form-control" required value="<?= e((string) $t['phone']) ?>">
                        </div>
                        <div class="form-group full">
                            <label class="form-label">Chuyên môn</label>
                            <input type="text" name="expertise" class="form-control" required value="<?= e((string) $t['expertise']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Vai trò</label>
                            <select name="role" class="form-select">
                                <option value="teacher" <?= $t['role'] === 'teacher' ? 'selected' : '' ?>>Giảng viên</option>
                                <option value="admin" <?= $t['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Định mức tuần (tiết)</label>
                            <input type="number" name="max_periods_per_week" class="form-control" value="<?= (int) $t['max_periods_per_week'] ?>" min="3" max="45">
                        </div>
                        <div class="form-group full">
                            <label class="form-label">Đổi mật khẩu (bỏ trống nếu giữ nguyên)</label>
                            <input type="password" name="password" class="form-control" placeholder="Nhập mật khẩu mới...">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-close-modal="edit-teacher-<?= $tid ?>">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-backdrop" id="avail-teacher-<?= $tid ?>">
        <div class="modal-dialog" style="max-width:640px;">
            <div class="modal-header">
                <h3 class="card-title">Khung Tiết trống — <?= e((string) $t['full_name']) ?></h3>
                <button type="button" class="btn btn-ghost btn-xs" data-close-modal="avail-teacher-<?= $tid ?>">Đóng</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="<?= e(url('/teachers/' . $tid . '/availabilities')) ?>" class="mb-3"
                      style="background:#f8fafc;padding:0.85rem;border-radius:8px;border:1px solid #e2e8f0;">
                    <?= csrf_field() ?>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Thứ</label>
                            <select name="day_of_week" class="form-select">
                                <?php foreach (app_config('days', []) as $dNum => $dLabel): ?>
                                    <option value="<?= (int) $dNum ?>"><?= e($dLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Cơ sở ưu tiên (tùy chọn)</label>
                            <select name="preferred_branch_id" class="form-select">
                                <option value="">Bất kỳ cơ sở</option>
                                <?php foreach ($branches as $b): ?>
                                    <option value="<?= (int) $b['id'] ?>"><?= e($b['code'] . ' — ' . $b['branch_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tiết bắt đầu</label>
                            <input type="number" name="start_period" class="form-control" min="1" max="15" value="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tiết kết thúc</label>
                            <input type="number" name="end_period" class="form-control" min="1" max="15" value="3">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Thêm khung tiết trống</button>
                </form>

                <?php if (empty($tAvails)): ?>
                    <div class="text-muted text-sm">Giảng viên này chưa đăng ký khung tiết trống nào.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                            <tr><th>Thứ</th><th>Khung ca/tiết</th><th>Cơ sở ưu tiên</th><th></th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($tAvails as $av): ?>
                                <tr>
                                    <td><strong><?= e(day_label((int) $av['day_of_week'])) ?></strong></td>
                                    <td><?= e((string) $av['shift_or_periods']) ?> <span class="text-xs text-muted">(<?= e(period_time_range((int) $av['start_period'], (int) $av['end_period'])) ?>)</span></td>
                                    <td><?= !empty($av['preferred_branch_code']) ? '<span class="badge badge-primary">' . e((string) $av['preferred_branch_code']) . '</span>' : '<span class="text-xs text-muted">Mọi cơ sở</span>' ?></td>
                                    <td style="text-align:right;">
                                        <form method="POST" action="<?= e(url('/teachers/' . $tid . '/availabilities/delete/' . (int) $av['id'])) ?>" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-danger btn-xs">Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>
