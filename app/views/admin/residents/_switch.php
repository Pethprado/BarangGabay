<?php
/**
 * Residents management: the two views under one menu item.
 *   Residents list  /admin/residents
 *   By purok        /admin/residents?view=puroks
 *
 * Expects $residentsView ('list' | 'puroks').
 */
$residentsView ??= 'list';
try {
    $__swTotals = \App\Models\Purok::totals();
} catch (\Throwable $e) {
    $__swTotals = ['total' => 0, 'no_purok' => 0];
}
$__swPuroks = count(barangay_subdivisions());
?>
<nav class="d-flex flex-wrap gap-2 mb-4" aria-label="Residents views" style="border-bottom:1px solid var(--border);padding-bottom:.75rem;">
    <a href="<?= e(route('admin/residents')) ?>"
       class="d-inline-flex align-items-center gap-2 text-decoration-none px-3 py-2 rounded-3 fw-semibold"
       style="font-size:.86rem;<?= $residentsView === 'list' ? 'background:var(--action-solid);color:#fff;' : 'background:var(--surface-card);color:var(--text-secondary);border:1px solid var(--border);' ?>"
       <?= $residentsView === 'list' ? 'aria-current="page"' : '' ?>>
        <i class="bi bi-people" aria-hidden="true"></i> Residents list
        <span class="badge rounded-pill" style="background:rgba(0,0,0,.12);color:inherit;"><?= number_format((int) $__swTotals['total']) ?></span>
    </a>
    <a href="<?= e(route('admin/residents') . '?view=puroks') ?>"
       class="d-inline-flex align-items-center gap-2 text-decoration-none px-3 py-2 rounded-3 fw-semibold"
       style="font-size:.86rem;<?= $residentsView === 'puroks' ? 'background:var(--action-solid);color:#fff;' : 'background:var(--surface-card);color:var(--text-secondary);border:1px solid var(--border);' ?>"
       <?= $residentsView === 'puroks' ? 'aria-current="page"' : '' ?>>
        <i class="bi bi-houses" aria-hidden="true"></i> By purok
        <span class="badge rounded-pill" style="background:rgba(0,0,0,.12);color:inherit;"><?= $__swPuroks ?> puroks</span>
        <?php if ((int) $__swTotals['no_purok'] > 0): ?>
        <span class="badge rounded-pill bg-warning text-dark" title="Residents with no purok yet"><?= (int) $__swTotals['no_purok'] ?> unassigned</span>
        <?php endif; ?>
    </a>
</nav>
