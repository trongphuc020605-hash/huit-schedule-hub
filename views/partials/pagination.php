<?php

declare(strict_types=1);

if (empty($pagination) || !is_array($pagination)) {
    return;
}

$page       = (int) ($pagination['page'] ?? 1);
$totalPages = (int) ($pagination['total_pages'] ?? 1);
$totalItems = (int) ($pagination['total_items'] ?? 0);
$perPage    = (int) ($pagination['per_page'] ?? 10);
$basePath   = (string) ($pagination['base_path'] ?? '/');
$query      = (array) ($pagination['query'] ?? []);

if ($totalItems === 0) {
    return;
}

$from = (($page - 1) * $perPage) + 1;
$to   = min($totalItems, $page * $perPage);

$buildPageUrl = static function (int $targetPage) use ($basePath, $query): string {
    $q = array_merge($query, ['page' => $targetPage]);
    return url($basePath . '?' . http_build_query($q));
};
?>
<nav class="pagination-bar" aria-label="Phân trang">
    <div class="text-xs text-muted">
        Hiển thị <strong><?= $from ?>–<?= $to ?></strong> trên tổng <strong><?= $totalItems ?></strong> bản ghi
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="pagination-controls">
            <?php if ($page > 1): ?>
                <a class="page-btn" href="<?= e($buildPageUrl($page - 1)) ?>">Trang trước</a>
            <?php else: ?>
                <span class="page-btn disabled" aria-disabled="true">Trang trước</span>
            <?php endif; ?>

            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php if ($p === $page): ?>
                    <span class="page-btn active" aria-current="page"><?= $p ?></span>
                <?php else: ?>
                    <a class="page-btn" href="<?= e($buildPageUrl($p)) ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a class="page-btn" href="<?= e($buildPageUrl($page + 1)) ?>">Trang sau</a>
            <?php else: ?>
                <span class="page-btn disabled" aria-disabled="true">Trang sau</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</nav>
