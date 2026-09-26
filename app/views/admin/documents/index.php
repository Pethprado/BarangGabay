<?php
/**
 * The document-request queue.
 *
 * Ordered so that work waiting on staff floats to the top — a queue sorted
 * only by date buries a week-old pending request under yesterday's released
 * one, and the whole point of a queue is knowing what to do next.
 *
 * Variables: $requests (list), $status (string filter), $pageTitle, $pendingCount
 */
$requests = $requests ?? [];
$status   = (string) ($status ?? '');

$statusClass = [
    'pending'    => 'background:#f1f5f9;color:#475569;',
    'processing' => 'background:#fef3c7;color:#92400e;',
    'ready'      => 'background:#d4edda;color:#155724;',
    'released'   => 'background:#dbeafe;color:#1d4ed8;',
    'rejected'   => 'background:#fee2e2;color:#991b1b;',
];

$counts = ['' => count($requests)];
ob_start();
?>

<div class="admin-card mb-4">
    <div class="admin-card-body">
        <h1 style="font-size:1.15rem;font-weight:800;margin:0 0 .25rem;"><?= e(t('admin_documents.title')) ?></h1>
        <p class="text-muted mb-3" style="font-size:.82rem;line-height:1.6;">
            <?= e(t('admin_documents.subtitle')) ?>
        </p>

        <!-- Status filter -->
        <div class="d-flex flex-wrap gap-2">
            <?php
            $filters = ['' => t('admin_documents.filter_all')];
            foreach (array_keys(\App\Models\DocumentRequest::TRANSITIONS) as $s) {
                $filters[$s] = t('documents.status_' . $s);
            }
            foreach ($filters as $key => $label):
                $active = $status === $key;
            ?>
            <a href="<?= e(route('admin/documents' . ($key !== '' ? '?status=' . urlencode($key) : ''))) ?>"
               class="status-badge"
               style="text-decoration:none;<?= $active ? 'background:var(--brand-primary);color:#fff;' : 'background:var(--surface-muted);color:var(--text-secondary);' ?>">
                <?= e($label) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($requests === []): ?>
<div class="admin-card">
    <div class="admin-card-body text-center" style="padding:3rem 1rem;">
        <i class="bi bi-inbox" style="font-size:2.5rem;color:var(--border);"></i>
        <p class="text-muted mt-3 mb-0" style="font-size:.9rem;"><?= e(t('admin_documents.empty')) ?></p>
    </div>
</div>
<?php else: ?>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table admin-table mb-0">
            <thead>
                <tr>
                    <th style="padding-left:14px;"><?= e(t('admin_documents.col_reference')) ?></th>
                    <th><?= e(t('admin_documents.col_resident')) ?></th>
                    <th><?= e(t('admin_documents.col_document')) ?></th>
                    <th><?= e(t('admin_documents.col_purpose')) ?></th>
                    <th><?= e(t('residents.col_status')) ?></th>
                    <th style="width:270px;text-align:right;padding-right:16px;"><?= e(t('residents.col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($requests as $r):
                $st    = (string) $r['status'];
                $next  = \App\Models\DocumentRequest::TRANSITIONS[$st] ?? [];
            ?>
            <tr>
                <td style="padding-left:14px;white-space:nowrap;">
                    <span style="font-family:ui-monospace,monospace;font-size:.78rem;">
                        <?= e((string) $r['reference_no']) ?>
                    </span>
                    <div class="text-muted" style="font-size:.72rem;">
                        <?= e(format_datetime((string) $r['requested_at'])) ?>
                    </div>
                </td>
                <td>
                    <div style="font-weight:600;font-size:.85rem;"><?= e((string) $r['full_name']) ?></div>
                    <div class="text-muted" style="font-size:.74rem;">
                        <?= e((string) ($r['zone'] ?: t('admin_documents.no_purok'))) ?>
                        <?php if (!empty($r['phone'])): ?>
                        &bull; <?= e((string) $r['phone']) ?>
                        <?php endif; ?>
                    </div>
                </td>
                <td style="font-size:.85rem;">
                    <?= e(\App\Models\DocumentRequest::label((string) $r['document_type'])) ?>
                </td>
                <td class="text-muted" style="font-size:.8rem;max-width:230px;">
                    <?= e(mb_substr((string) $r['purpose'], 0, 90)) ?>
                </td>
                <td>
                    <span class="status-badge" style="<?= $statusClass[$st] ?? '' ?>">
                        <?= e(t('documents.status_' . $st)) ?>
                    </span>
                </td>
                <td style="text-align:right;padding-right:14px;">
                    <?php if ($next === []): ?>
                    <span class="text-muted" style="font-size:.76rem;"><?= e(t('admin_documents.final')) ?></span>
                    <?php else: ?>
                    <form method="post"
                          action="<?= e(route('admin/documents/' . (int) $r['id'] . '/status')) ?>"
                          class="d-flex gap-1 justify-content-end align-items-center flex-wrap">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="return_status" value="<?= e($status) ?>">
                        <input type="text" name="staff_note" maxlength="2000"
                               placeholder="<?= e(t('admin_documents.note_ph')) ?>"
                               class="form-control form-control-sm"
                               style="max-width:130px;font-size:.76rem;">
                        <select name="status" class="form-select form-select-sm"
                                style="max-width:110px;font-size:.76rem;">
                            <?php foreach ($next as $to): ?>
                            <option value="<?= e($to) ?>"><?= e(t('documents.status_' . $to)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-action" title="<?= e(t('admin_documents.apply')) ?>">
                            <i class="bi bi-check2"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
$content   = ob_get_clean();
$pageTitle = $pageTitle ?? t('admin_documents.title');
require __DIR__ . '/../../layouts/admin.php';
