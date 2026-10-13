<?php
/**
 * Trash — deleted announcements, events and ordinances.
 *
 * Variables: $items (list), $type (?string filter), $isAdmin (bool), $pageTitle
 */
$items   = $items ?? [];
$type    = $type ?? null;
$isAdmin = (bool) ($isAdmin ?? false);

$typeInfo = [
    'announcement' => ['Announcement', 'bi-megaphone',      'bg-success-subtle text-success-emphasis'],
    'event'        => ['Event',        'bi-calendar-event', 'bg-warning-subtle text-warning-emphasis'],
    'ordinance'    => ['Ordinance',    'bi-journal-text',   'bg-primary-subtle text-primary-emphasis'],
];
$tabs = ['' => 'All', 'announcement' => 'Announcements', 'event' => 'Events', 'ordinance' => 'Ordinances'];

ob_start();
?>
<div class="admin-card">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1"><i class="bi bi-trash3 me-1" aria-hidden="true"></i>Trash</h2>
            <p class="text-muted small mb-0">
                Deleted announcements, events and ordinances wait here. <strong>Restore</strong> puts a post back
                exactly as it was, and residents can see it again.
                <?php if ($isAdmin): ?>
                    Only admins can delete a post permanently.
                <?php else: ?>
                    You see the posts you wrote. Ask an admin to delete one permanently.
                <?php endif; ?>
            </p>
        </div>
        <?php if ($isAdmin && $items !== [] && $type === null): ?>
        <form method="post" action="<?= e(route('admin/trash/empty')) ?>"
              onsubmit="return confirm('Delete everything in the Trash permanently? This cannot be undone.');">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-trash3-fill me-1" aria-hidden="true"></i>Empty Trash
            </button>
        </form>
        <?php endif; ?>
    </div>

    <div class="btn-group mb-3 flex-wrap" role="group" aria-label="Filter">
        <?php foreach ($tabs as $key => $label):
            $active = ($type ?? '') === $key; ?>
        <a href="<?= e(route('admin/trash') . ($key !== '' ? '?type=' . $key : '')) ?>"
           class="btn btn-sm <?= $active ? 'btn-success' : 'btn-outline-success' ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($items === []): ?>
    <div class="text-center text-muted py-5">
        <i class="bi bi-trash3 fs-1 d-block mb-2" aria-hidden="true"></i>
        The Trash is empty.
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Type</th>
                    <th scope="col">Title</th>
                    <th scope="col">Posted by</th>
                    <th scope="col">Deleted</th>
                    <th scope="col" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $it):
                [$tLabel, $tIcon, $tClass] = $typeInfo[$it['content_type']] ?? ['Post', 'bi-file-text', 'bg-secondary-subtle'];
                $tid = (int) $it['id']; ?>
                <tr data-trash-id="<?= $tid ?>">
                    <td><span class="badge <?= e($tClass) ?>"><i class="bi <?= e($tIcon) ?> me-1" aria-hidden="true"></i><?= e($tLabel) ?></span></td>
                    <td class="fw-semibold" style="max-width:28rem;"><?= e((string) $it['title']) ?></td>
                    <td class="small"><?= e((string) ($it['author_name'] ?? '—')) ?></td>
                    <td class="small text-muted">
                        <?= e(relative_time((string) $it['deleted_at'])) ?>
                        <?php if (!empty($it['deleted_by_name'])): ?><br>by <?= e((string) $it['deleted_by_name']) ?><?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <form method="post" action="<?= e(route('admin/trash/' . $tid . '/restore')) ?>" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <button type="submit" class="btn btn-sm btn-success">
                                <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Restore
                            </button>
                        </form>
                        <?php if ($isAdmin): ?>
                        <form method="post" action="<?= e(route('admin/trash/' . $tid . '/delete')) ?>" class="d-inline"
                              onsubmit="return confirm(<?= e(json_encode('Delete "' . $it['title'] . '" permanently? This cannot be undone.')) ?>);">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete permanently">
                                <i class="bi bi-x-octagon" aria-hidden="true"></i><span class="visually-hidden">Delete permanently</span>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
