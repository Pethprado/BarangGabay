<?php
/**
 * Admin — view one event without leaving the admin panel.
 * The sidebar stays; the event is shown on the right exactly as residents
 * see it (the resident page in embed mode, so its own styles never clash
 * with the admin theme).
 * Variables: $event (array), $pageTitle (string)
 */
ob_start();
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <a href="<?= e(route('admin/events')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i><?= e(__('Back to events')) ?>
    </a>
    <div class="d-flex gap-2">
        <a href="<?= e(route('admin/events/' . (int) $event['id'] . '/edit')) ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-pencil me-1"></i><?= e(__('Edit')) ?>
        </a>
    </div>
</div>

<div class="admin-card p-0" style="overflow:hidden;">
    <iframe src="<?= e(route('events/' . $event['slug']) . '?embed=1') ?>"
            title="<?= e(localised_text($event, 'title')) ?>"
            style="display:block;width:100%;height:calc(100vh - 190px);min-height:480px;border:0;background:var(--surface-card);"></iframe>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
