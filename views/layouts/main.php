<?php

declare(strict_types=1);

use HuitSchedule\Core\Auth;
use HuitSchedule\Core\View;

$isAuth = Auth::check();
$showSidebar = $isAuth;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e(($pageTitle ?? 'Trang chủ') . ' — ' . app_config('app_name', 'HUIT Schedule Hub')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>
</head>
<body>
<div class="app-shell">
    <?php if ($showSidebar): ?>
        <?php View::partial('partials/sidebar', ['activeNav' => $activeNav ?? '']); ?>
    <?php endif; ?>

    <div class="app-main<?= $showSidebar ? '' : ' no-sidebar' ?>">
        <?php View::partial('partials/navbar', [
            'pageTitle'   => $pageTitle ?? 'HUIT Schedule Hub',
            'showSidebar' => $showSidebar,
        ]); ?>

        <main class="page-content">
            <?php View::partial('partials/flash', ['flashes' => $flashes ?? []]); ?>
            <?= $content ?? '' ?>
        </main>
    </div>
</div>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php if (($activeNav ?? '') === 'dashboard'): ?>
    <script src="<?= e(asset('js/dashboard-charts.js')) ?>" defer></script>
<?php endif; ?>
<?php if (in_array($activeNav ?? '', ['schedule_create', 'schedules'], true) && isset($mode)): ?>
    <script src="<?= e(asset('js/scheduler.js')) ?>" defer></script>
<?php endif; ?>
</body>
</html>
