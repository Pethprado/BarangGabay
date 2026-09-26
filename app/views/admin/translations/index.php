<?php
/**
 * Admin: urgent announcements whose English translation is awaiting review.
 *
 * Variables: $pending (list<array>)
 *
 * This list is the reason the gate is safe to have at all. Holding a
 * translation back is only responsible if somebody is told it is being held —
 * otherwise a storm warning sits half-translated and nobody notices.
 */
$pending = $pending ?? [];

ob_start();
?>

<div class="admin-card mb-4">
    <div class="admin-card-header">
        <h2 class="admin-card-title">
            <i class="bi bi-translate me-1" style="color:#e8a020;"></i><?= e(t('translation_review.title')) ?>
        </h2>
        <p class="text-muted mb-0 mt-1" style="font-size:.78rem;line-height:1.6;">
            <?= e(t('translation_review.subtitle')) ?>
        </p>
    </div>

    <div class="admin-card-body">
        <?php if (!$pending): ?>
        <div class="text-center py-4">
            <i class="bi bi-check2-circle" style="font-size:2rem;color:#1a6b3a;"></i>
            <p class="text-muted mb-0 mt-2" style="font-size:.85rem;"><?= e(t('translation_review.empty')) ?></p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0" style="font-size:.845rem;">
                <thead>
                    <tr style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-secondary);">
                        <th scope="col"><?= e(t('translation_review.col_announcement')) ?></th>
                        <th scope="col"><?= e(t('translation_review.col_published')) ?></th>
                        <th scope="col" class="text-end"><?= e(t('translation_review.col_action')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pending as $row): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge rounded-pill badge-urgent-pulse"
                                      style="background:#f8d7da;color:#721c24;font-size:.66rem;font-weight:700;padding:.3em .7em;">
                                    <?= e(t('translation_review.urgent_badge')) ?>
                                </span>
                                <span style="font-weight:600;color:var(--text-primary);"><?= e($row['title']) ?></span>
                            </div>
                            <div class="text-muted mt-1" style="font-size:.76rem;">
                                <?= e(mb_substr((string) ($row['title_en'] ?? ''), 0, 90)) ?>
                            </div>
                        </td>
                        <td>
                            <span class="text-muted" style="font-size:.78rem;">
                                <?= !empty($row['published_at'])
                                    ? e(date('M j, Y g:i A', strtotime((string) $row['published_at'])))
                                    : '—' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="<?= e(route('admin/translations/' . (int) $row['id'])) ?>"
                               class="btn btn-sm btn-barangay" style="font-size:.74rem;white-space:nowrap;">
                                <i class="bi bi-eye me-1"></i><?= e(t('translation_review.review_btn')) ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <p class="form-text mt-3 mb-0" style="line-height:1.6;">
            <i class="bi bi-info-circle me-1"></i><?= e(t('translation_review.why_note')) ?>
        </p>
    </div>
</div>

<?php
$content   = ob_get_clean();
$pageTitle ??= t('translation_review.title');
require __DIR__ . '/../../layouts/admin.php';
?>
