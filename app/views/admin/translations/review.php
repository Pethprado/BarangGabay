<?php
/**
 * Admin: review one urgent announcement's machine English translation.
 *
 * Variables: $announcement (array)
 *
 * Source on the left, translation on the right and editable. The reviewer has
 * to press "Confirm & publish" — opening this page changes nothing, because a
 * gate that clears itself on a glance is not a gate.
 */
$announcement = $announcement ?? [];
$sourceTitle  = (string) ($announcement['title'] ?? '');
$sourceBody   = trim(strip_tags((string) ($announcement['body'] ?? '')));

ob_start();
?>

<div class="admin-card mb-4">
    <div class="admin-card-header">
        <h2 class="admin-card-title d-flex align-items-center gap-2 flex-wrap">
            <span class="badge rounded-pill badge-urgent-pulse"
                  style="background:#f8d7da;color:#721c24;font-size:.66rem;font-weight:700;padding:.3em .7em;">
                <?= e(t('translation_review.urgent_badge')) ?>
            </span>
            <?= e(t('translation_review.review_title')) ?>
        </h2>
        <p class="text-muted mb-0 mt-1" style="font-size:.78rem;line-height:1.6;">
            <?= e(t('translation_review.review_help')) ?>
        </p>
    </div>

    <div class="admin-card-body">

        <!-- Worked example of what can go wrong, so a reviewer skimming this
             page knows what kind of error to look for. -->
        <div class="mb-4 rounded-3 p-3" style="background:#fef3c7;border:1px solid #fcd34d;">
            <p class="mb-1 fw-semibold" style="font-size:.78rem;color:#92400e;">
                <i class="bi bi-exclamation-triangle-fill me-1"></i><?= e(t('translation_review.warn_title')) ?>
            </p>
            <p class="mb-0" style="font-size:.76rem;line-height:1.7;color:#92400e;">
                <?= e(t('translation_review.warn_example')) ?>
            </p>
        </div>

        <form method="post" action="<?= e(route('admin/translations/' . (int) $announcement['id'])) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <div class="row g-3">
                <!-- ── Source (read-only) ──────────────────────────────── -->
                <div class="col-12 col-lg-6">
                    <p class="fw-bold mb-2" style="font-size:.74rem;letter-spacing:.06em;text-transform:uppercase;color:var(--text-secondary);">
                        <i class="bi bi-file-text me-1"></i><?= e(t('translation_review.source_heading')) ?>
                    </p>

                    <label class="form-label fw-semibold mb-1" style="font-size:.8rem;"><?= e(t('translation_review.field_title')) ?></label>
                    <div class="form-control mb-3" style="background:var(--surface-muted);font-size:.875rem;min-height:38px;">
                        <?= e($sourceTitle) ?>
                    </div>

                    <label class="form-label fw-semibold mb-1" style="font-size:.8rem;"><?= e(t('translation_review.field_body')) ?></label>
                    <div class="form-control" style="background:var(--surface-muted);font-size:.875rem;line-height:1.7;white-space:pre-line;min-height:180px;">
                        <?= e($sourceBody) ?>
                    </div>
                </div>

                <!-- ── Translation (editable) ──────────────────────────── -->
                <div class="col-12 col-lg-6">
                    <p class="fw-bold mb-2" style="font-size:.74rem;letter-spacing:.06em;text-transform:uppercase;color:#1652f0;">
                        <i class="bi bi-translate me-1"></i><?= e(t('translation_review.translation_heading')) ?>
                    </p>

                    <label for="title_en" class="form-label fw-semibold mb-1" style="font-size:.8rem;"><?= e(t('translation_review.field_title')) ?></label>
                    <input type="text"
                           id="title_en"
                           name="title_en"
                           class="form-control mb-3"
                           value="<?= e((string) ($announcement['title_en'] ?? '')) ?>"
                           maxlength="500"
                           style="font-size:.875rem;">

                    <label for="body_en" class="form-label fw-semibold mb-1" style="font-size:.8rem;"><?= e(t('translation_review.field_body')) ?></label>
                    <textarea id="body_en"
                              name="body_en"
                              class="form-control"
                              rows="9"
                              style="font-size:.875rem;line-height:1.7;"><?= e((string) ($announcement['body_en'] ?? '')) ?></textarea>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap mt-4 pt-3" style="border-top:1px solid var(--tb-border);">
                <button type="submit" class="btn-barangay">
                    <i class="bi bi-check-lg me-1"></i><?= e(t('translation_review.confirm_btn')) ?>
                </button>
                <a href="<?= e(route('admin/translations')) ?>" class="btn btn-outline-secondary" style="font-size:.85rem;">
                    <?= e(t('translation_review.back_btn')) ?>
                </a>
                <span class="text-muted ms-auto" style="font-size:.74rem;">
                    <?= e(t('translation_review.confirm_hint')) ?>
                </span>
            </div>
        </form>
    </div>
</div>

<?php
$content   = ob_get_clean();
$pageTitle ??= t('translation_review.review_title');
require __DIR__ . '/../../layouts/admin.php';
?>
