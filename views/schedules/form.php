<?php

declare(strict_types=1);

$isEdit = ($mode ?? 'create') === 'edit';
$actionUrl = $isEdit ? url('/schedules/update/' . (int) $schedule['id']) : url('/schedules');
$shifts = app_config('shifts', []);
?>

<div class="scheduler-grid">
    <!-- Left Column: Schedule Form -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= $isEdit ? 'Chỉnh sửa Lịch Giảng dạy #' . (int) $schedule['id'] : 'Tạo Lịch Giảng dạy Thông minh' ?></h2>
            <a href="<?= e(url('/schedules')) ?>" class="btn btn-ghost btn-xs">Quay lại danh sách</a>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= e($actionUrl) ?>" id="schedule-form"
                  data-base-url="<?= e(base_url()) ?>"
                  data-exclude-id="<?= $isEdit ? (int) $schedule['id'] : '' ?>">
                <?= csrf_field() ?>

                <div class="form-grid">
                    <div class="form-group full">
                        <label class="form-label" for="subject_name">Tên môn học / Học phần *</label>
                        <input type="text" id="subject_name" name="subject_name" class="form-control" required
                               placeholder="VD: Phát triển phần mềm mã nguồn mở (PHP & MySQL)"
                               value="<?= e((string) $schedule['subject_name']) ?>">
                        <span class="text-xs text-muted">Nhập tên môn để hệ thống chấm điểm chuyên môn giảng viên theo thời gian thực (0–40đ).</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="branch_id">Cơ sở giảng dạy *</label>
                        <select id="branch_id" name="branch_id" class="form-select" required>
                            <option value="">Chọn cơ sở</option>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= (int) $b['id'] ?>" <?= ((int) $schedule['branch_id'] === (int) $b['id']) ? 'selected' : '' ?>>
                                    <?= e($b['code'] . ' — ' . $b['branch_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="room">Phòng học *</label>
                        <input type="text" id="room" name="room" class="form-control" required
                               placeholder="VD: A301, B204, PM05"
                               value="<?= e((string) $schedule['room']) ?>">
                    </div>

                    <div class="form-group full" id="available-rooms-section" style="margin-top:-0.3rem;">
                        <span class="form-label">Phòng học trống tại cơ sở theo khung giờ đã chọn (click để điền nhanh)</span>
                        <div id="available-rooms-box" class="room-chips-wrap">
                            <span class="text-xs text-muted">Chọn cơ sở để tải danh sách phòng trống...</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="day_of_week">Thứ trong tuần *</label>
                        <select id="day_of_week" name="day_of_week" class="form-select" required>
                            <?php foreach (app_config('days', []) as $dNum => $dLabel): ?>
                                <option value="<?= (int) $dNum ?>" <?= ((int) $schedule['day_of_week'] === (int) $dNum) ? 'selected' : '' ?>>
                                    <?= e($dLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="shift_preset">Chọn nhanh Ca học chuẩn HUIT</label>
                        <select id="shift_preset" class="form-select">
                            <?php foreach ($shifts as $sNum => $sh): ?>
                                <option value="<?= (int) $sNum ?>"><?= e($sh['label'] . ' | ' . $sh['time']) ?></option>
                            <?php endforeach; ?>
                            <option value="custom">Tùy biến Tiết bắt đầu – kết thúc</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="start_period">Tiết bắt đầu (1–15) *</label>
                        <input type="number" id="start_period" name="start_period" class="form-control" min="1" max="15" required
                               value="<?= (int) $schedule['start_period'] ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="end_period">Tiết kết thúc (1–15) *</label>
                        <input type="number" id="end_period" name="end_period" class="form-control" min="1" max="15" required
                               value="<?= (int) $schedule['end_period'] ?>">
                    </div>

                    <div class="form-group full">
                        <label class="form-label" for="shift_or_periods">Nhãn hiển thị Ca / Tiết (tự đồng bộ)</label>
                        <input type="text" id="shift_or_periods" name="shift_or_periods" class="form-control"
                               value="<?= e((string) $schedule['shift_or_periods']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="start_date">Ngày bắt đầu *</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" required
                               value="<?= e((string) $schedule['start_date']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="end_date">Ngày kết thúc *</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" required
                               value="<?= e((string) $schedule['end_date']) ?>">
                    </div>

                    <div class="form-group full">
                        <label class="form-label" for="teacher_id">Giảng viên phụ trách *</label>
                        <select id="teacher_id" name="teacher_id" class="form-select" required>
                            <option value="">Chọn giảng viên hoặc bấm "Chọn nhanh" bên phải</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?= (int) $t['id'] ?>" <?= ((int) $schedule['teacher_id'] === (int) $t['id']) ? 'selected' : '' ?>>
                                    <?= e($t['full_name'] . ' — ' . $t['expertise']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group full">
                        <label class="form-label" for="notes">Ghi chú lớp học phần</label>
                        <input type="text" id="notes" name="notes" class="form-control"
                               placeholder="VD: Lớp 13DHTH02, phòng máy thực hành..."
                               value="<?= e((string) ($schedule['notes'] ?? '')) ?>">
                    </div>
                </div>

                <div class="flex gap-2 mt-2">
                    <button type="submit" id="submit-schedule-btn" class="btn btn-primary">
                        <?= $isEdit ? 'Cập nhật Lịch dạy' : 'Lưu Phân lịch' ?>
                    </button>
                    <a href="<?= e(url('/schedules')) ?>" class="btn btn-secondary">Hủy</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Right Column: Live Inspector -->
    <div class="inspector-stack">
        <!-- Box 1: Live Conflict & Travel Inspector -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Kiểm tra Xung đột &amp; Di chuyển Realtime</h3>
                <span class="badge badge-primary">Live 250ms</span>
            </div>
            <div class="card-body" id="live-conflict-box">
                <div class="text-muted text-sm">Đang kiểm tra trạng thái phòng học và giảng viên...</div>
            </div>
        </div>

        <!-- Box 3: Top-5 Optimal Slot Finder -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Gợi ý Top 5 Ô Tiết trống Tối ưu</h3>
                <button type="button" id="btn-suggest-slots" class="btn btn-success btn-xs">
                    Tìm 5 ô tối ưu cho GV đang chọn
                </button>
            </div>
            <div class="card-body">
                <div id="optimal-slots-box" style="display:flex;flex-direction:column;gap:0.5rem;">
                    <div class="text-muted text-sm">Chọn Giảng viên và Cơ sở rồi bấm nút trên để hệ thống đề xuất 5 khung giờ liền ca, 0 phút di chuyển và khớp tiết trống.</div>
                </div>
            </div>
        </div>

        <!-- Box 2: Explainable Teacher Recommendation Engine (0-100) -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Gợi ý Giảng viên Tối ưu (Thang điểm 0–100)</h3>
            </div>
            <div class="card-body">
                <div class="tabs" style="margin-bottom:0.85rem;">
                    <button type="button" class="tab-btn active" data-rec-tab="recommended">
                        Khả dụng (<span id="rec-count">0</span>)
                    </button>
                    <button type="button" class="tab-btn" data-rec-tab="unavailable">
                        Đang bận (<span id="unavail-count">0</span>)
                    </button>
                </div>

                <div id="recommended-teachers-list" style="display:flex;flex-direction:column;gap:0.65rem;max-height:540px;overflow-y:auto;">
                    <div class="text-muted text-sm">Đang tải danh sách giảng viên gợi ý...</div>
                </div>

                <div id="unavailable-teachers-list" style="display:none;flex-direction:column;gap:0.55rem;max-height:420px;overflow-y:auto;"></div>
            </div>
        </div>
    </div>
</div>
