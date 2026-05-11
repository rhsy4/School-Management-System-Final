<?php
/**
 * Pagination UI Component
 * 
 * Хэрэглэх:
 *   $pag = paginate($totalCount, (int)($_GET['page'] ?? 1), 20);
 *   // ... query-д LIMIT $pag['offset'], $pag['perPage'] нэмэх
 *   include __DIR__ . '/pagination.php'; // $pag variable-тай байх ёстой
 */

if (!isset($pag) || $pag['totalPages'] <= 1) return;

$currentPage = $pag['page'];
$totalPages  = $pag['totalPages'];
$total       = $pag['total'];

// Build query string preserving existing parameters
$queryParams = $_GET;
unset($queryParams['page']);
$baseQuery = http_build_query($queryParams);
$baseUrl = '?' . ($baseQuery ? $baseQuery . '&' : '');

// Window range
$windowSize = 2;
$startPage = max(1, $currentPage - $windowSize);
$endPage   = min($totalPages, $currentPage + $windowSize);
?>

<div class="pagination-wrap" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-top:24px; padding:16px 0;">
    <div style="font-size:13px; color:var(--muted);">
        Нийт <strong><?= number_format($total) ?></strong> бичлэг · 
        <?= $currentPage ?>/<?= $totalPages ?> хуудас
    </div>
    <div style="display:flex; gap:4px; align-items:center;">
        <?php if ($currentPage > 1): ?>
        <a href="<?= $baseUrl ?>page=1" class="btn btn-sm btn-secondary" style="min-width:36px; padding:6px 10px; border-radius:8px;" title="Эхний хуудас">
            <i class="fas fa-angle-double-left"></i>
        </a>
        <a href="<?= $baseUrl ?>page=<?= $currentPage - 1 ?>" class="btn btn-sm btn-secondary" style="min-width:36px; padding:6px 10px; border-radius:8px;">
            <i class="fas fa-angle-left"></i>
        </a>
        <?php endif; ?>

        <?php if ($startPage > 1): ?>
            <span style="padding:6px 4px; color:var(--muted);">…</span>
        <?php endif; ?>

        <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
        <a href="<?= $baseUrl ?>page=<?= $p ?>" 
           class="btn btn-sm <?= $p === $currentPage ? 'btn-primary' : 'btn-secondary' ?>"
           style="min-width:36px; padding:6px 10px; border-radius:8px; font-weight:<?= $p === $currentPage ? '700' : '400' ?>;">
            <?= $p ?>
        </a>
        <?php endfor; ?>

        <?php if ($endPage < $totalPages): ?>
            <span style="padding:6px 4px; color:var(--muted);">…</span>
        <?php endif; ?>

        <?php if ($currentPage < $totalPages): ?>
        <a href="<?= $baseUrl ?>page=<?= $currentPage + 1 ?>" class="btn btn-sm btn-secondary" style="min-width:36px; padding:6px 10px; border-radius:8px;">
            <i class="fas fa-angle-right"></i>
        </a>
        <a href="<?= $baseUrl ?>page=<?= $totalPages ?>" class="btn btn-sm btn-secondary" style="min-width:36px; padding:6px 10px; border-radius:8px;" title="Сүүлийн хуудас">
            <i class="fas fa-angle-double-right"></i>
        </a>
        <?php endif; ?>
    </div>
</div>
