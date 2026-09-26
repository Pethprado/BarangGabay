<?php
/**
 * Admin ordinance edit view.
 * Variables: $ordinance (array), $pageTitle (string)
 */
$ordinance = $ordinance ?? [];
$pendingCount = $pendingCount ?? 0;

ob_start();
?>

<!-- Page header -->
<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <p class="mb-0" style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8;"><?= e(t('admin_nav.ordinances')) ?></p>
        <h1 class="mb-0 mt-1" style="font-size:1.55rem;font-weight:800;color:var(--text-primary);line-height:1.1;"><?= e(t('admin_ordinances.edit_title')) ?></h1>
        <p class="text-muted mt-1 mb-0" style="font-size:.82rem;">
            <?= e($ordinance['ordinance_no'] ?? '') ?> &mdash; <?= e($ordinance['title'] ?? '') ?>
        </p>
    </div>
    <a href="<?= e(route('admin/ordinances')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> <?= e(t('common.back')) ?>
    </a>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title mb-0">
            <i class="bi bi-pencil-square me-2" style="color:var(--brand-primary);"></i>
            <?= e(t('admin_ordinances.edit_title')) ?>
        </h2>
    </div>
    <div class="admin-card-body">
        <form method="post"
              action="<?= e(route('admin/ordinances/' . (int) $ordinance['id'])) ?>"
              enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="row g-3">

                <!-- Title -->
                <div class="col-12">
                    <label for="title" class="form-label fw-semibold">
                        <?= e(t('admin_announcements.field_title')) ?> <span class="text-danger">*</span>
                    </label>
                    <input type="text"
                           id="title"
                           name="title"
                           class="form-control"
                           value="<?= e($ordinance['title'] ?? '') ?>"
                           required>
                </div>

                <!-- Ordinance Number -->
                <div class="col-sm-6">
                    <label for="ordinance_no" class="form-label fw-semibold">
                        <?= e(t('admin_ordinances.field_ordinance_no')) ?> <span class="text-danger">*</span>
                    </label>
                    <input type="text"
                           id="ordinance_no"
                           name="ordinance_no"
                           class="form-control font-monospace"
                           value="<?= e($ordinance['ordinance_no'] ?? '') ?>"
                           placeholder="e.g. Ordinance No. 2026-001"
                           required>
                </div>

                <!-- Category -->
                <div class="col-sm-6">
                    <label for="category" class="form-label fw-semibold"><?= e(t('admin_announcements.field_category')) ?></label>
                    <input type="text"
                           id="category"
                           name="category"
                           class="form-control"
                           value="<?= e($ordinance['category'] ?? '') ?>"
                           placeholder="e.g. Environmental, Public Order, Health">
                </div>

                <!-- Enacted Date -->
                <div class="col-sm-6">
                    <label for="enacted_date" class="form-label fw-semibold"><?= e(t('admin_ordinances.field_enacted_date')) ?></label>
                    <input type="date"
                           id="enacted_date"
                           name="enacted_date"
                           class="form-control"
                           value="<?= e($ordinance['enacted_date'] ?? '') ?>">
                </div>

                <!-- Status -->
                <div class="col-sm-6">
                    <label for="status" class="form-label fw-semibold">
                        <?= e(t('admin_announcements.field_status')) ?> <span class="text-danger">*</span>
                    </label>
                    <select id="status" name="status" class="form-select" required>
                        <?php foreach ([
                            'active'   => t('admin_ordinances.status_active'),
                            'draft'    => t('admin_ordinances.status_draft'),
                            'repealed' => t('admin_ordinances.status_repealed'),
                        ] as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= ($ordinance['status'] ?? '') === $val ? 'selected' : '' ?>>
                            <?= $lbl ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Description -->
                <div class="col-12">
                    <label for="description" class="form-label fw-semibold"><?= e(t('admin_events.field_description')) ?></label>
                    <textarea id="description"
                              name="description"
                              class="form-control"
                              rows="4"
                              placeholder="<?= e(t('admin_ordinances.description_ph')) ?>"><?= e($ordinance['description'] ?? '') ?></textarea>
                </div>

                <!-- PDF Upload (optional replacement) -->
                <div class="col-12">
                    <label for="file" class="form-label fw-semibold"><?= e(t('admin_ordinances.replace_pdf_label')) ?> <?= e(t('common.optional')) ?></label>

                    <?php if (!empty($ordinance['file_url'])): ?>
                    <div class="mb-2 d-flex align-items-center gap-2"
                         style="background:#fef3c7;border:1px solid #d4c48a;border-radius:8px;padding:10px 14px;">
                        <i class="bi bi-file-earmark-pdf text-danger"></i>
                        <span style="font-size:.845rem;color:#78350f;">
                            <?= e(t('admin_ordinances.current_file_label')) ?> <code><?= e(basename($ordinance['file_url'])) ?></code>
                        </span>
                        <a href="<?= e(asset($ordinance['file_url'])) ?>" target="_blank"
                           class="ms-auto btn btn-outline-secondary btn-sm" style="font-size:.75rem;">
                            <i class="bi bi-eye me-1"></i><?= e(t('admin_ordinances.view_btn')) ?>
                        </a>
                    </div>
                    <?php endif; ?>

                    <input type="file"
                           id="file"
                           name="file"
                           class="form-control"
                           accept=".pdf">
                    <div class="form-text"><?= e(t('admin_ordinances.pdf_replace_help')) ?></div>
                </div>

            </div><!-- /row -->

            <!-- Manobo translation widget -->
            <?php
            $__mTitleVal  = $ordinance['title_manobo']       ?? '';
            $__mBodyVal   = $ordinance['description_manobo'] ?? '';
            $__mBodyName  = 'description_manobo';
            $__mBodyLabel = t('manobo_fields.label_description');
            $__mAudioPath = $ordinance['audio_manobo_path']  ?? null;
            $__eSourceLang = $ordinance['source_lang'] ?? 'fil';
            $__eTitleVal  = $ordinance['title_en'] ?? '';
            $__eBodyVal   = $ordinance['description_en'] ?? '';
            $__eBodyName  = 'description_en';
            $__eBodyLabel = t('manobo_fields.label_description');
            // Staff hear the post before a resident does — the only reliable
            // way to catch an abbreviation or a missing full stop that reads
            // fine but sounds wrong. Above the translation panels because it
            // is about the content just typed, not a translation of it.
            // See the note in upload.php.
            $__ipType   = 'ordinance';
            $__ipSource = $ordinance['source_url'] ?? null;
            require __DIR__ . '/../../shared/_import-panel.php';

            $__vpSourceLang = $__eSourceLang ?? 'fil';
            $__vpType       = 'ordinance';
            $__vpRow        = $ordinance;
            require __DIR__ . '/../../shared/_voice-preview.php';

            /* Above the hand-entry fields — see the note in the
               announcement edit form. */
            $lbRow       = $ordinance;
            $lbType      = 'ordinance';
            $lbBodyField = 'description';
            $lbAttempts  = \App\Models\TranslationAttempt::forContent('ordinance', (int) $ordinance['id']);
            $lbRedirect  = '/admin/ordinances';
            require __DIR__ . '/../../shared/_language-status-card.php';

            require __DIR__ . '/../../shared/_english-fields.php';

            /* Reports on the source-language choice made just above. */
            $tpTitleField = 'title';
            $tpBodyField  = 'description';
            $tpExisting   = (string) ($ordinance['source_lang'] ?? 'fil');
            require __DIR__ . '/../../shared/_translation-plan.php';

            require __DIR__ . '/../../shared/_manobo-fields.php';
            ?>

            <div class="d-flex align-items-center gap-3 mt-4 pt-3 border-top">
                <button type="submit" class="btn-barangay">
                    <i class="bi bi-check-lg me-1"></i> <?= e(t('admin_events.save_changes_btn')) ?>
                </button>
                <a href="<?= e(route('admin/ordinances')) ?>"
                   class="btn btn-outline-secondary">
                    <?= e(t('common.cancel')) ?>
                </a>

                <!-- Delete (admin only) -->
                <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'], true)): ?>
                <?php /* Targets #deleteOrdinanceForm below via form="". This
                         WAS a nested form: the browser drops the inner
                         opening tag, so the button posted the EDIT action
                         and the ordinance was saved rather than deleted.
                         Only admins ever saw it, which is why it survived —
                         a staff-account walkthrough never renders it. */ ?>
                <button type="submit"
                        form="deleteOrdinanceForm"
                        class="btn btn-outline-danger btn-sm ms-auto">
                    <i class="bi bi-trash3 me-1"></i> <?= e(t('admin_announcements.delete_title')) ?>
                </button>
                <?php endif; ?>
            </div>

        </form>
    </div>
</div>

<?php if (in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'], true)): ?>
<?php /* The delete form, out here where it is legal HTML. The button above
         references it by id. */ ?>
<form id="deleteOrdinanceForm"
      method="post"
      action="<?= e(route('admin/ordinances/' . (int) $ordinance['id'] . '/delete')) ?>"
      onsubmit="return confirm(<?= e(json_encode(t('admin_ordinances.confirm_delete', ['title' => $ordinance['title'] ?? '']))) ?>)">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
</form>
<?php endif; ?>

<?php
// See the note in _language-retry-forms.php — these cannot live inside the
// edit form, and the status card is inside it.
require __DIR__ . '/../../shared/_language-retry-forms.php';
?>

<?php
$content   = ob_get_clean();
$pageTitle = $pageTitle ?? t('admin_ordinances.edit_title');
require __DIR__ . '/../../layouts/admin.php';