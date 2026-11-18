<?php

declare(strict_types=1);

use HuitSchedule\Core\Auth;

$activeNav = $activeNav ?? '';
$isAdmin = Auth::isAdmin();
?>
<aside class="sidebar" id="app-sidebar" aria-label="Điều hướng chính">
    <div class="sidebar-brand">
        <div class="brand-mark">HU</div>
        <div>
            <div class="brand-title">HUIT Schedule Hub</div>
            <div class="brand-sub">Multi-Campus Optimization</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php if ($isAdmin): ?>
            <div class="nav-section-label">Điều hành Đào tạo</div>
            <a class="nav-link<?= $activeNav === 'dashboard' ? ' active' : '' ?>"
               href="<?= e(url('/dashboard')) ?>"
               <?= $activeNav === 'dashboard' ? 'aria-current="page"' : '' ?>>
                <span>Dashboard &amp; Tối ưu</span>
                <span class="nav-code">01</span>
            </a>
            <a class="nav-link<?= $activeNav === 'schedule_create' ? ' active' : '' ?>"
               href="<?= e(url('/schedules/create')) ?>"
               <?= $activeNav === 'schedule_create' ? 'aria-current="page"' : '' ?>>
                <span>Phân lịch Thông minh</span>
                <span class="nav-code">02</span>
            </a>
            <a class="nav-link<?= $activeNav === 'matrix' ? ' active' : '' ?>"
               href="<?= e(url('/schedules/matrix')) ?>"
               <?= $activeNav === 'matrix' ? 'aria-current="page"' : '' ?>>
                <span>Ma trận Tiết trống</span>
                <span class="nav-code">03</span>
            </a>
            <a class="nav-link<?= $activeNav === 'schedules' ? ' active' : '' ?>"
               href="<?= e(url('/schedules')) ?>"
               <?= $activeNav === 'schedules' ? 'aria-current="page"' : '' ?>>
                <span>Danh sách Lịch dạy</span>
                <span class="nav-code">04</span>
            </a>

            <div class="nav-section-label">Danh mục Nguồn lực</div>
            <a class="nav-link<?= $activeNav === 'branches' ? ' active' : '' ?>"
               href="<?= e(url('/branches')) ?>"
               <?= $activeNav === 'branches' ? 'aria-current="page"' : '' ?>>
                <span>Cơ sở &amp; Ma trận ĐC</span>
                <span class="nav-code">05</span>
            </a>
            <a class="nav-link<?= $activeNav === 'teachers' ? ' active' : '' ?>"
               href="<?= e(url('/teachers')) ?>"
               <?= $activeNav === 'teachers' ? 'aria-current="page"' : '' ?>>
                <span>Giảng viên &amp; Tiết trống</span>
                <span class="nav-code">06</span>
            </a>
        <?php endif; ?>

        <div class="nav-section-label">Cổng cá nhân</div>
        <a class="nav-link<?= $activeNav === 'portal' ? ' active' : '' ?>"
           href="<?= e(url('/portal')) ?>"
           <?= $activeNav === 'portal' ? 'aria-current="page"' : '' ?>>
            <span>Cổng Giảng viên</span>
            <span class="nav-code">PT</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div>Trường ĐH Công Thương TP.HCM</div>
        <div style="margin-top:2px;">v1.0.0 | Open Source MIT</div>
    </div>
</aside>
