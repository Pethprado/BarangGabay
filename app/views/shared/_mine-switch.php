<?php
/**
 * _mine-switch.php — "Mine | All" toggle for back-office lists.
 *
 * The staff sidebar opens My Announcements / My Events / My Ordinances /
 * My Feedback with ?mine=1. Without a visible switch nothing said the list
 * was filtered, and there was no way back to everything.
 *
 * Set before requiring:
 *   $__msPath (string) route path, e.g. 'admin/announcements'
 *   $__msMine (bool)   whether ?mine=1 is active
 *   $__msMineLabel (string) e.g. 'My announcements'
 *   $__msAllLabel  (string) e.g. 'All announcements'
 */
$__msPath      = (string) ($__msPath ?? '');
$__msMine      = (bool) ($__msMine ?? false);
$__msMineLabel = (string) ($__msMineLabel ?? 'Mine');
$__msAllLabel  = (string) ($__msAllLabel ?? 'All');
// True when "Mine" was asked for but this person has posted nothing yet, so
// the controller shows everything instead of an empty page.
$__msFellBack  = (bool) ($__msFellBack ?? false);
if ($__msPath === '') {
    return;
}
?>
<?php if ($__msFellBack): ?>
<div class="alert alert-info d-flex align-items-start gap-2 py-2 mb-2" role="status">
    <i class="bi bi-info-circle mt-1" aria-hidden="true"></i>
    <span>You have not posted anything here yet, so this list shows everything residents can see.
        Anything you post will appear under <strong><?= e($__msMineLabel) ?></strong>.</span>
</div>
<?php endif; ?>
<div class="btn-group mb-3" role="group" aria-label="Show">
    <a href="<?= e(route($__msPath) . '?mine=1') ?>"
       class="btn btn-sm <?= $__msMine ? 'btn-success' : 'btn-outline-success' ?>"<?= $__msMine ? ' aria-current="page"' : '' ?>>
        <i class="bi bi-person me-1" aria-hidden="true"></i><?= e($__msMineLabel) ?>
    </a>
    <a href="<?= e(route($__msPath)) ?>"
       class="btn btn-sm <?= !$__msMine ? 'btn-success' : 'btn-outline-success' ?>"<?= !$__msMine ? ' aria-current="page"' : '' ?>>
        <i class="bi bi-people me-1" aria-hidden="true"></i><?= e($__msAllLabel) ?>
    </a>
</div>
