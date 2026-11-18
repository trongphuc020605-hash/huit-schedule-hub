<?php

declare(strict_types=1);

if (empty($flashes) || !is_array($flashes)) {
    return;
}
?>
<div class="flash-stack" role="status" aria-live="polite">
    <?php foreach ($flashes as $flash): ?>
        <?php
            $type = in_array($flash['type'] ?? 'info', ['success', 'error', 'warning', 'info'], true)
                ? (string) $flash['type']
                : 'info';
            $prefix = match ($type) {
                'success' => 'Thành công:',
                'error'   => 'Lỗi:',
                'warning' => 'Cảnh báo:',
                default   => 'Thông báo:',
            };
        ?>
        <div class="alert alert-<?= e($type) ?>">
            <div><strong><?= e($prefix) ?></strong> <?= e((string) ($flash['message'] ?? '')) ?></div>
            <button type="button" onclick="this.parentElement.remove()" aria-label="Đóng thông báo"
                    style="background:none;border:none;cursor:pointer;font-size:0.85rem;font-weight:700;opacity:.65;">Đóng</button>
        </div>
    <?php endforeach; ?>
</div>
