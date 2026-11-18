<?php

declare(strict_types=1);

use HuitSchedule\Core\Auth;

$user = Auth::user();
$showSidebar = $showSidebar ?? false;
?>
<header class="topbar">
    <div class="topbar-left">
        <?php if ($showSidebar): ?>
            <button type="button" class="mobile-toggle" id="sidebar-toggle" aria-label="Mở menu điều hướng">Menu</button>
        <?php else: ?>
            <div class="brand-mark" style="width:32px;height:32px;font-size:0.8rem;">HU</div>
        <?php endif; ?>
        <h1 class="topbar-title"><?= e($pageTitle ?? 'HUIT Schedule Hub') ?></h1>
    </div>

    <div class="topbar-right">
        <?php if ($user !== null): ?>
            <div class="user-pill">
                <div class="user-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $user['full_name'], 0, 1, 'UTF-8'), 'UTF-8')) ?></div>
                <div>
                    <div style="font-weight:700;font-size:0.82rem;line-height:1.2;"><?= e((string) $user['full_name']) ?></div>
                    <div class="text-xs text-muted"><?= $user['role'] === 'admin' ? 'Cán bộ Đào tạo' : 'Giảng viên' ?></div>
                </div>
            </div>
            <form method="POST" action="<?= e(url('/logout')) ?>" style="margin:0;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">Đăng xuất</button>
            </form>
        <?php else: ?>
            <span class="badge badge-primary">Chế độ xem Portal cá nhân</span>
            <a href="<?= e(url('/login')) ?>" class="btn btn-primary btn-sm">Đăng nhập</a>
        <?php endif; ?>
    </div>
</header>
