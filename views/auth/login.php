<?php

declare(strict_types=1);

use HuitSchedule\Core\Flash;
use HuitSchedule\Core\View;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập — <?= e(app_config('app_name', 'HUIT Schedule Hub')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="login-page">
    <aside class="login-aside">
        <div>
            <div class="flex items-center gap-2">
                <div class="brand-mark" style="width:42px;height:42px;font-size:1rem;">HU</div>
                <div>
                    <div style="font-weight:800;font-size:1.15rem;color:#0f172a;">HUIT Schedule Hub</div>
                    <div class="text-xs text-muted">Trường Đại học Công Thương TP.HCM</div>
                </div>
            </div>

            <h1 style="margin:2rem 0 0.75rem;font-size:1.7rem;line-height:1.25;color:#0f172a;">
                Hệ thống Quản lý &amp; Phân lịch Giảng dạy Đa Cơ sở
            </h1>
            <p class="text-muted" style="max-width:480px;margin:0;">
                Hỗ trợ Phòng Đào tạo phân công học phần trên 4 cơ sở Tân Phú và Quận 12, phát hiện xung đột theo thời gian thực và tối ưu hóa lộ trình di chuyển cho giảng viên.
            </p>

            <ul class="login-feature-list">
                <li class="login-feature-item">
                    <strong>Kiểm tra xung đột giao dịch MySQL</strong>
                    <div class="text-xs text-muted mt-1">Khóa hàng SELECT ... FOR UPDATE đảm bảo không trùng giảng viên và phòng học.</div>
                </li>
                <li class="login-feature-item">
                    <strong>Ma trận di chuyển và khoảng trống chết</strong>
                    <div class="text-xs text-muted mt-1">Tự động cảnh báo chênh lệch phút nghỉ giữa các cơ sở và đề xuất khung tiết trống tối ưu.</div>
                </li>
                <li class="login-feature-item">
                    <strong>Cổng tra cứu Giảng viên và xuất lịch .ICS</strong>
                    <div class="text-xs text-muted mt-1">Xem thời khóa biểu theo tuần, tháng và đồng bộ trực tiếp sang Google Calendar / Apple Calendar.</div>
                </li>
            </ul>
        </div>

        <div class="text-xs text-muted">
            Phiên bản 1.0.0 | Giấy phép MIT Open Source
        </div>
    </aside>

    <main class="login-main">
        <div class="login-card">
            <h2 style="margin:0 0 0.35rem;font-size:1.3rem;color:#0f172a;">Đăng nhập hệ thống</h2>
            <p class="text-sm text-muted" style="margin:0 0 1.25rem;">Sử dụng tài khoản email HUIT được cấp để tiếp tục.</p>

            <?php View::partial('partials/flash', ['flashes' => $flashes ?? []]); ?>

            <form method="POST" action="<?= e(url('/login')) ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label" for="email">Email HUIT</label>
                    <input type="email" id="email" name="email" class="form-control" required autofocus
                           placeholder="admin@huit.edu.vn"
                           value="<?= e((string) Flash::old('email', '')) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Mật khẩu</label>
                    <input type="password" id="password" name="password" class="form-control" required
                           placeholder="Nhập mật khẩu">
                </div>

                <button type="submit" class="btn btn-primary w-full" style="padding:0.68rem;font-size:0.92rem;margin-top:0.35rem;">
                    Đăng nhập
                </button>
            </form>

            <div style="margin-top:1.35rem;padding:0.85rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;font-size:0.78rem;">
                <div style="font-weight:700;color:#0f172a;margin-bottom:0.4rem;">Tài khoản dùng thử (Demo):</div>
                <div style="margin-bottom:0.3rem;">
                    <span class="badge badge-primary">Admin</span>
                    <code>admin@huit.edu.vn</code> / <code>Admin@123</code>
                </div>
                <div>
                    <span class="badge badge-success">Giảng viên</span>
                    <code>giangvien@huit.edu.vn</code> / <code>Teacher@123</code>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>
