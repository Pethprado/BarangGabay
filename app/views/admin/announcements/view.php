<?php
/**
 * Admin — view one announcement without leaving the admin panel.
 * The sidebar stays; the announcement is shown on the right exactly as
 * residents see it (the resident page in embed mode, so its own styles never
 * clash with the admin theme). Same pattern as admin/events/view.php.
 * Variables: $announcement (array), $pageTitle (string)
 */
ob_start();
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <a href="<?= e(route('admin/announcements')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i><?= e(__('Back to announcements')) ?>
    </a>
    <a href="<?= e(route('admin/announcements/' . (int) $announcement['id'] . '/edit')) ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-pencil me-1"></i><?= e(__('Edit')) ?>
    </a>
</div>

<div class="admin-card p-0" style="overflow:hidden;">
    <iframe src="<?= e(route('announcements/' . $announcement['slug']) . '?embed=1') ?>"
            title="<?= e(localised_text($announcement, 'title')) ?>"
            style="display:block;width:100%;height:calc(100vh - 190px);min-height:480px;border:0;background:var(--surface-card);"></iframe>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
