<?php

declare(strict_types=1);
?>

<div class="grid-2 mb-3">
    <!-- Add Branch Card -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Thêm Cơ sở Đào tạo Mới</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= e(url('/branches')) ?>">
                <?= csrf_field() ?>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="b-code">Mã cơ sở *</label>
                        <input type="text" id="b-code" name="code" class="form-control" required placeholder="VD: CS5">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="b-zone">Khu vực / Quận *</label>
                        <input type="text" id="b-zone" name="campus_zone" class="form-control" required placeholder="VD: Tân Phú">
                    </div>
                    <div class="form-group full">
                        <label class="form-label" for="b-name">Tên cơ sở *</label>
                        <input type="text" id="b-name" name="branch_name" class="form-control" required placeholder="VD: Cơ sở 5 - Trung tâm Thực hành">
                    </div>
                    <div class="form-group full">
                        <label class="form-label" for="b-addr">Địa chỉ đầy đủ *</label>
                        <input type="text" id="b-addr" name="address" class="form-control" required placeholder="Số nhà, Đường, Phường, Quận, TP.HCM">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="b-color">Màu định danh (Biểu đồ &amp; Lịch)</label>
                        <input type="color" id="b-color" name="color_hex" class="form-control" value="#1D4ED8" style="height:40px;padding:4px;">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Lưu Cơ sở</button>
            </form>
        </div>
    </div>

    <!-- Update Travel Matrix Card -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Cập nhật Ma trận Thời gian Di chuyển (2 chiều)</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= e(url('/branches/travel')) ?>">
                <?= csrf_field() ?>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="t-from">Từ cơ sở *</label>
                        <select id="t-from" name="from_branch_id" class="form-select" required>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= (int) $b['id'] ?>"><?= e($b['code'] . ' — ' . $b['branch_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="t-to">Đến cơ sở *</label>
                        <select id="t-to" name="to_branch_id" class="form-select" required>
                            <?php foreach ($branches as $idx => $b): ?>
                                <option value="<?= (int) $b['id'] ?>" <?= $idx === 1 ? 'selected' : '' ?>><?= e($b['code'] . ' — ' . $b['branch_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="t-min">Thời gian di chuyển (phút) *</label>
                        <input type="number" id="t-min" name="travel_minutes" class="form-control" min="1" max="180" value="15" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="t-km">Khoảng cách (km) *</label>
                        <input type="number" step="0.1" id="t-km" name="distance_km" class="form-control" min="0.1" max="100" value="2.5" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-secondary">Cập nhật tuyến di chuyển</button>
            </form>
            <p class="text-xs text-muted mt-2">
                Hệ thống sử dụng ma trận này để tự động phát hiện <code>IMPOSSIBLE_TRAVEL</code> (thời gian di chuyển &gt; phút nghỉ) và <code>TIGHT_TRAVEL</code> (dư &lt; 15 phút).
            </p>
        </div>
    </div>
</div>

<!-- Branches List -->
<div class="card mb-3">
    <div class="card-header">
        <h2 class="card-title">Danh sách Cơ sở Đào tạo HUIT (<?= count($branches) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Mã</th>
                <th>Tên cơ sở &amp; Khu vực</th>
                <th>Địa chỉ</th>
                <th>Số lịch dạy</th>
                <th style="text-align:right;">Thao tác</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($branches)): ?>
                <tr><td colspan="5" class="text-muted" style="text-align:center;padding:2rem;">Chưa có cơ sở đào tạo nào.</td></tr>
            <?php else: ?>
            <?php foreach ($branches as $b): ?>
                <tr>
                    <td>
                        <span class="badge" style="background:<?= e((string) $b['color_hex']) ?>;color:#fff;">
                            <?= e((string) $b['code']) ?>
                        </span>
                    </td>
                    <td>
                        <strong><?= e((string) $b['branch_name']) ?></strong>
                        <div class="text-xs text-muted">Khu vực: <?= e((string) $b['campus_zone']) ?></div>
                    </td>
                    <td>
                        <?= e((string) $b['address']) ?>
                        <a href="https://www.google.com/maps/search/?api=1&query=<?= rawurlencode((string) $b['address']) ?>"
                           target="_blank" rel="noopener noreferrer" class="text-xs" style="margin-left:6px;">[Bản đồ]</a>
                    </td>
                    <td><span class="badge badge-neutral"><?= (int) $b['schedule_count'] ?> lớp</span></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <button type="button" class="btn btn-secondary btn-xs" data-open-modal="edit-branch-<?= (int) $b['id'] ?>">Sửa</button>
                        <form method="POST" action="<?= e(url('/branches/delete/' . (int) $b['id'])) ?>" style="display:inline;"
                              data-confirm="Bạn chắc chắn muốn xóa cơ sở <?= e((string) $b['branch_name']) ?>?">
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
</div>

<!-- Travel Matrix Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Ma trận Thời gian Di chuyển Thực tế giữa các Cơ sở (phút / km)</h2>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Từ \ Đến</th>
                <?php foreach ($branches as $col): ?>
                    <th style="text-align:center;">
                        <span class="badge" style="background:<?= e((string) $col['color_hex']) ?>;color:#fff;"><?= e((string) $col['code']) ?></span>
                    </th>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($branches as $row): ?>
                <tr>
                    <td>
                        <strong><?= e((string) $row['code']) ?></strong>
                        <span class="text-xs text-muted">(<?= e((string) $row['campus_zone']) ?>)</span>
                    </td>
                    <?php foreach ($branches as $col): ?>
                        <?php
                            $fid = (int) $row['id'];
                            $tid = (int) $col['id'];
                            $cell = $travelMatrix[$fid][$tid] ?? null;
                        ?>
                        <td style="text-align:center;">
                            <?php if ($fid === $tid): ?>
                                <span class="badge badge-success">0 phút</span>
                            <?php elseif ($cell !== null): ?>
                                <?php
                                    $mins = (int) $cell['travel_minutes'];
                                    $cls = $mins >= 30 ? 'badge-danger' : ($mins >= 14 ? 'badge-warning' : 'badge-success');
                                ?>
                                <span class="badge <?= e($cls) ?>"><?= $mins ?> phút</span>
                                <div class="text-xs text-muted"><?= e((string) $cell['distance_km']) ?> km</div>
                            <?php else: ?>
                                <span class="text-xs text-muted">Mặc định 20p</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Modals for Branches -->
<?php foreach ($branches as $b): ?>
    <div class="modal-backdrop" id="edit-branch-<?= (int) $b['id'] ?>">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="card-title">Chỉnh sửa Cơ sở <?= e((string) $b['code']) ?></h3>
                <button type="button" class="btn btn-ghost btn-xs" data-close-modal="edit-branch-<?= (int) $b['id'] ?>">Đóng</button>
            </div>
            <form method="POST" action="<?= e(url('/branches/update/' . (int) $b['id'])) ?>">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Mã cơ sở</label>
                            <input type="text" name="code" class="form-control" required value="<?= e((string) $b['code']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Khu vực</label>
                            <input type="text" name="campus_zone" class="form-control" required value="<?= e((string) $b['campus_zone']) ?>">
                        </div>
                        <div class="form-group full">
                            <label class="form-label">Tên cơ sở</label>
                            <input type="text" name="branch_name" class="form-control" required value="<?= e((string) $b['branch_name']) ?>">
                        </div>
                        <div class="form-group full">
                            <label class="form-label">Địa chỉ</label>
                            <input type="text" name="address" class="form-control" required value="<?= e((string) $b['address']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Màu định danh</label>
                            <input type="color" name="color_hex" class="form-control" value="<?= e((string) $b['color_hex']) ?>" style="height:40px;padding:4px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-close-modal="edit-branch-<?= (int) $b['id'] ?>">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>
