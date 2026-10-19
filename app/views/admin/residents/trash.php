<?php
/**
 * Admin — Resident Trash, grouped by purok.
 * Restore puts the account back with its previous status; Delete forever
 * removes it (their own requests/payments too) and cannot be undone.
 * Variables: $groups (array<string purok, list<row>>), $pageTitle
 */
$residentsView = 'trash';
$statusLabel = ['verified' => 'Verified', 'pending' => 'Pending', 'suspended' => 'Suspended'];
$total = array_sum(array_map('count', $groups));

ob_start();
require __DIR__ . '/_switch.php';
?>

<div class="mb-3">
    <h2 class="h5 fw-bold mb-1"><i class="bi bi-trash3 me-1"></i> Resident Trash</h2>
    <p class="text-muted mb-0" style="font-size:.85rem;">
        Deleted residents stay here until you restore them or delete them forever. While in the Trash they are
        signed out, cannot log in, and get no notifications or SMS. Their document requests and payments are kept.
    </p>
</div>

<?php if ($total === 0): ?>
<div class="admin-card text-center py-5">
    <i class="bi bi-trash3" style="font-size:2rem;color:var(--text-muted);"></i>
    <p class="mt-2 mb-0 text-muted">The Trash is empty.</p>
</div>
<?php endif; ?>

<?php foreach ($groups as $purok => $rows): ?>
<div class="admin-card p-0 mb-4">
    <div class="admin-card-header d-flex align-items-center justify-content-between">
        <h3 class="admin-card-title mb-0" style="font-size:1rem;">
            <i class="bi bi-houses me-1"></i><?= e($purok) ?>
        </h3>
        <span class="badge rounded-pill text-bg-secondary"><?= count($rows) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0" style="font-size:.85rem;">
            <thead>
                <tr>
                    <th>Resident</th>
                    <th>Phone</th>
                    <th>Status before</th>
                    <th>Deleted</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($row['full_name']) ?></div>
                        <div class="text-muted" style="font-size:.78rem;"><?= e($row['email']) ?></div>
                    </td>
                    <td><?= e($row['phone'] ?: '—') ?></td>
                    <td><?= e($statusLabel[$row['deleted_prev_status'] ?? ''] ?? '—') ?></td>
                    <td style="font-size:.8rem;">
                        <?= e(date('M j, Y g:i A', strtotime((string) $row['deleted_at']))) ?>
                        <?php if (!empty($row['deleted_by_name'])): ?>
                        <div class="text-muted">by <?= e($row['deleted_by_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1">
                            <form method="post" action="<?= e(route('admin/residents/trash/' . (int) $row['id'] . '/restore')) ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Restore
                                </button>
                            </form>
                            <form method="post" action="<?= e(route('admin/residents/trash/' . (int) $row['id'] . '/purge')) ?>"
                                  onsubmit="return confirm(<?= e(json_encode('Delete ' . $row['full_name'] . ' forever? Their account, document requests and payments will be removed. This cannot be undone.')) ?>)">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-x-octagon me-1"></i>Delete forever
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
