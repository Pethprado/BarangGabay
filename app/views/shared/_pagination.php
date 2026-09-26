<?php
/**
 * Shared pagination component.
 *
 * Expected variables (passed via extract or direct scope):
 *   int    $page      – current page number
 *   int    $total     – total number of records
 *   int    $perPage   – records per page
 *   array  $query     – extra GET params to preserve in page links (e.g. filters)
 *
 * Usage:
 *   <?php include __DIR__ . '/../shared/_pagination.php'; ?>
 */

$totalPages = (int) ceil($total / max(1, $perPage));

if ($totalPages <= 1) {
    return; // nothing to render
}

$query    = $query ?? [];
$from     = (($page - 1) * $perPage) + 1;
$to       = min($page * $perPage, $total);

$pageLink = function (int $p) use ($query): string {
    $params = array_merge($query, ['page' => $p]);
    return '?' . http_build_query($params);
};

$window = 2; // pages to show either side of current
$start  = max(1, $page - $window);
$end    = min($totalPages, $page + $window);
?>

<nav class="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-between"
     aria-label="Pagination">

    <!-- Result count. The label is escaped first, then the placeholders are
         swapped for the bold spans, so the only markup that reaches the page
         is ours and the numbers inside it are escaped too. -->
    <p class="text-sm text-slate-500 order-2 sm:order-1">
        <?= strtr(e(t('common.showing_range')), [
            ':from'  => '<strong class="text-slate-700">' . e($from . '–' . $to) . '</strong>',
            ':total' => '<strong class="text-slate-700">' . e(number_format($total)) . '</strong>',
        ]) ?>
    </p>

    <!-- Page links -->
    <div class="order-1 sm:order-2 flex items-center gap-1">

        <!-- Previous -->
        <?php if ($page > 1): ?>
        <a href="<?= e($pageLink($page - 1)) ?>"
           class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 text-sm transition hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700"
           aria-label="<?= e(t('common.prev_page')) ?>">
            <i class="bi bi-chevron-left" style="font-size:.8rem;"></i>
        </a>
        <?php else: ?>
        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-100 bg-slate-50 text-slate-300 text-sm cursor-default">
            <i class="bi bi-chevron-left" style="font-size:.8rem;"></i>
        </span>
        <?php endif; ?>

        <!-- First page + ellipsis -->
        <?php if ($start > 1): ?>
        <a href="<?= e($pageLink(1)) ?>"
           class="inline-flex h-9 min-w-[2.25rem] items-center justify-center rounded-xl border border-slate-200 bg-white px-2 text-sm text-slate-600 transition hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700">
            1
        </a>
        <?php if ($start > 2): ?>
        <span class="inline-flex h-9 w-9 items-center justify-center text-slate-400 text-sm">…</span>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Window pages -->
        <?php for ($p = $start; $p <= $end; $p++): ?>
        <?php if ($p === $page): ?>
        <span class="inline-flex h-9 min-w-[2.25rem] items-center justify-center rounded-xl bg-blue-700 px-2 text-sm font-semibold text-white shadow-sm"
              aria-current="page">
            <?= $p ?>
        </span>
        <?php else: ?>
        <a href="<?= e($pageLink($p)) ?>"
           class="inline-flex h-9 min-w-[2.25rem] items-center justify-center rounded-xl border border-slate-200 bg-white px-2 text-sm text-slate-600 transition hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700">
            <?= $p ?>
        </a>
        <?php endif; ?>
        <?php endfor; ?>

        <!-- Last page + ellipsis -->
        <?php if ($end < $totalPages): ?>
        <?php if ($end < $totalPages - 1): ?>
        <span class="inline-flex h-9 w-9 items-center justify-center text-slate-400 text-sm">…</span>
        <?php endif; ?>
        <a href="<?= e($pageLink($totalPages)) ?>"
           class="inline-flex h-9 min-w-[2.25rem] items-center justify-center rounded-xl border border-slate-200 bg-white px-2 text-sm text-slate-600 transition hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700">
            <?= $totalPages ?>
        </a>
        <?php endif; ?>

        <!-- Next -->
        <?php if ($page < $totalPages): ?>
        <a href="<?= e($pageLink($page + 1)) ?>"
           class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 text-sm transition hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700"
           aria-label="<?= e(t('common.next_page')) ?>">
            <i class="bi bi-chevron-right" style="font-size:.8rem;"></i>
        </a>
        <?php else: ?>
        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-100 bg-slate-50 text-slate-300 text-sm cursor-default">
            <i class="bi bi-chevron-right" style="font-size:.8rem;"></i>
        </span>
        <?php endif; ?>

    </div>
</nav>
