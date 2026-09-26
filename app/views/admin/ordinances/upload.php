<?php
/**
 * Admin — upload ordinance form.
 * Variables: $errors (array), $old (array — repopulate on failed submit)
 */
$errors = $errors ?? [];
$old    = $old    ?? [];
$get    = static fn(string $k): string => e($old[$k] ?? '');

ob_start();
?>

<!-- Breadcrumb -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?= e(route('admin/ordinances')) ?>"><?= e(t('admin_nav.ordinances')) ?></a></li>
            <li class="breadcrumb-item active"><?= e(t('admin_ordinances.breadcrumb_upload_new')) ?></li>
        </ol>
    </nav>
</div>

<div class="row justify-content-center">
<div class="col-xl-9 col-lg-10">

<div class="admin-card">

    <!-- Card header -->
    <div class="d-flex align-items-center gap-3 mb-4 pb-4 border-bottom">
        <div class="p-2 rounded-3" style="background:rgba(26,107,58,.1);">
            <i class="bi bi-file-earmark-arrow-up fs-4" style="color:var(--brand-primary);"></i>
        </div>
        <div>
            <h5 class="fw-bold mb-0"><?= e(t('admin_ordinances.upload_btn')) ?></h5>
            <p class="text-muted small mb-0"><?= e(t('admin_ordinances.header_desc')) ?></p>
        </div>
    </div>

    <!-- Flash errors -->
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong><?= e(t('admin_ordinances.errors_intro')) ?></strong>
        <ul class="mb-0 mt-1 ps-3">
            <?php foreach ($errors as $err): ?>
            <li class="small"><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <form method="post"
          action="<?= e(route('admin/ordinances')) ?>"
          enctype="multipart/form-data"
          novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <!-- ── Row 1: Title + Ordinance Number ──────────────────────────── -->
        <div class="row g-3 mb-4">
            <div class="col-sm-7">
                <label for="title" class="form-label fw-semibold">
                    <?= e(t('admin_announcements.field_title')) ?> <span class="text-danger">*</span>
                </label>
                <input type="text"
                       id="title"
                       name="title"
                       class="form-control <?= !empty($errors['title']) ? 'is-invalid' : '' ?>"
                       value="<?= $get('title') ?>"
                       placeholder="<?= e(t('admin_ordinances.title_ph')) ?>"
                       maxlength="500"
                       required>
                <?php if (!empty($errors['title'])): ?>
                <div class="invalid-feedback"><?= e($errors['title']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-sm-5">
                <label for="ordinance_no" class="form-label fw-semibold">
                    <?= e(t('admin_ordinances.field_ordinance_no')) ?> <span class="text-danger">*</span>
                </label>
                <input type="text"
                       id="ordinance_no"
                       name="ordinance_no"
                       class="form-control <?= !empty($errors['ordinance_no']) ? 'is-invalid' : '' ?>"
                       value="<?= $get('ordinance_no') ?>"
                       placeholder="<?= e(t('admin_ordinances.ordinance_no_ph')) ?>"
                       maxlength="100"
                       required>
                <?php if (!empty($errors['ordinance_no'])): ?>
                <div class="invalid-feedback"><?= e($errors['ordinance_no']) ?></div>
                <?php endif; ?>
                <div class="form-text"><?= e(t('admin_ordinances.ordinance_no_help')) ?></div>
            </div>
        </div>

        <!-- ── Row 2: Description ────────────────────────────────────────── -->
        <div class="mb-4">
            <label for="description" class="form-label fw-semibold"><?= e(t('admin_ordinances.field_description')) ?></label>
            <textarea id="description"
                      name="description"
                      class="form-control"
                      rows="4"
                      placeholder="<?= e(t('admin_ordinances.description_ph')) ?>"><?= $get('description') ?></textarea>
            <div class="form-text"><?= e(t('admin_ordinances.description_help')) ?></div>
        </div>

        <!-- ── Row 3: Category · Enacted Date · Status ───────────────────── -->
        <div class="row g-3 mb-4">
            <div class="col-sm-5">
                <label for="category" class="form-label fw-semibold">
                    <?= e(t('admin_announcements.field_category')) ?> <span class="text-danger">*</span>
                </label>
                <select id="category" name="category"
                        class="form-select <?= !empty($errors['category']) ? 'is-invalid' : '' ?>"
                        required>
                    <option value=""><?= e(t('admin_ordinances.category_placeholder')) ?></option>
                    <?php
                    $cats = [
                        'General'        => 'General',
                        'Health'         => 'Health (Kalusugan)',
                        'Safety'         => 'Safety (Kaligtasan)',
                        'Environmental'  => 'Environmental (Kalikasan)',
                        'Government'     => 'Government (Pamahalaan)',
                        'Education'      => 'Education (Edukasyon)',
                        'Infrastructure' => 'Infrastructure (Imprastruktura)',
                        'Social'         => 'Social (Panlipunan)',
                    ];
                    $oldCat = $old['category'] ?? '';
                    foreach ($cats as $val => $lbl): ?>
                    <option value="<?= e($val) ?>" <?= $oldCat === $val ? 'selected' : '' ?>>
                        <?= e($lbl) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['category'])): ?>
                <div class="invalid-feedback"><?= e($errors['category']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-sm-4">
                <label for="enacted_date" class="form-label fw-semibold">
                    <?= e(t('admin_ordinances.field_enacted_date')) ?>
                    <span class="text-muted fw-normal"><?= e(t('common.optional')) ?></span>
                </label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                    <input type="date"
                           id="enacted_date"
                           name="enacted_date"
                           class="form-control"
                           value="<?= $get('enacted_date') ?>">
                </div>
                <div class="form-text"><?= e(t('admin_ordinances.enacted_date_help')) ?></div>
            </div>
            <div class="col-sm-3">
                <label for="status" class="form-label fw-semibold">
                    <?= e(t('admin_announcements.field_status')) ?>
                </label>
                <select id="status" name="status" class="form-select">
                    <?php
                    $statuses  = [
                        'active'   => t('admin_ordinances.status_active'),
                        'draft'    => t('admin_ordinances.status_draft'),
                        'repealed' => t('admin_ordinances.status_repealed'),
                    ];
                    $oldStatus = $old['status'] ?? 'active';
                    foreach ($statuses as $val => $lbl): ?>
                    <option value="<?= $val ?>" <?= $oldStatus === $val ? 'selected' : '' ?>>
                        <?= $lbl ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- ── Row 4: PDF Upload drop-zone ──────────────────────────────── -->
        <div class="mb-4" x-data="pdfUpload()">
            <label class="form-label fw-semibold">
                <?= e(t('admin_ordinances.field_pdf')) ?> <span class="text-danger">*</span>
            </label>

            <label for="pdf_file"
                   class="d-flex flex-column align-items-center justify-content-center gap-2
                          border border-2 border-dashed rounded-3 p-4 text-center w-100"
                   :class="fileName ? 'border-success' : (pdfError ? 'border-danger' : 'border-secondary')"
                   :style="{
                       minHeight: '130px', cursor: 'pointer',
                       transition: 'border-color .2s, background .2s',
                       background: fileName ? '#d4edda' : (pdfError ? '#f8d7da' : 'transparent')
                   }"
                   @dragover.prevent="dragActive = true"
                   @dragleave.prevent="dragActive = false"
                   @drop.prevent="handleDrop($event)">

                <!-- Empty state -->
                <template x-if="!fileName">
                    <div>
                        <i class="bi bi-file-earmark-pdf fs-1 text-danger opacity-50"></i>
                        <p class="mb-0 mt-2 fw-semibold small">
                            <?= t('admin_ordinances.pdf_dropzone_text') ?>
                        </p>
                        <p class="mb-0 small text-muted mt-1"><?= t('admin_ordinances.pdf_hint') ?></p>
                    </div>
                </template>

                <!-- File selected -->
                <template x-if="fileName">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-file-earmark-check fs-1 text-success"></i>
                        <div class="text-start">
                            <p class="mb-0 fw-semibold small text-success" x-text="fileName"></p>
                            <p class="mb-0 small text-muted mt-1" x-text="fileSize"></p>
                        </div>
                        <button type="button"
                                @click.prevent="clearFile()"
                                class="btn btn-sm btn-outline-danger ms-2">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </template>

                <input type="file"
                       id="pdf_file"
                       name="file"
                       accept="application/pdf"
                       required
                       class="d-none"
                       @change="handleFile($event)">
            </label>

            <!-- Validation message -->
            <p x-show="pdfError" class="text-danger small mt-1 mb-0" x-text="pdfError"></p>
            <?php if (!empty($errors['file'])): ?>
            <p class="text-danger small mt-1 mb-0"><?= e($errors['file']) ?></p>
            <?php endif; ?>
        </div>

        <!-- ── Manobo translation widget ────────────────────────────────── -->
        <?php
        $__mTitleVal  = '';
        $__mBodyVal   = '';
        $__mBodyName  = 'description_manobo';
        $__mBodyLabel = t('manobo_fields.label_description');
        $__mAudioPath = null;
        $__eTitleVal  = '';
            $__eBodyVal   = '';
            $__eBodyName  = 'description_en';
            $__eBodyLabel = t('manobo_fields.label_description');
            // Staff hear the post before a resident does — the only reliable
            // way to catch an abbreviation or a missing full stop that reads
            // fine but sounds wrong. Above the translation panels because it
            // is about the content just typed, not a translation of it.
            // For an ordinance the link tab fetches the PDF itself from a
            // Drive share link and stores it through the normal upload path.
            $__ipType   = 'ordinance';
            $__ipSource = null;
            require __DIR__ . '/../../shared/_import-panel.php';

            $__vpSourceLang = $__eSourceLang ?? 'fil';
            $__vpType       = 'ordinance';
            $__vpRow        = null;          // nothing saved yet — preview only
            require __DIR__ . '/../../shared/_voice-preview.php';

            require __DIR__ . '/../../shared/_english-fields.php';

            /* An ordinance description is the longest text this
               system handles, so the cap warning matters most here. */
            $tpTitleField = 'title';
            $tpBodyField  = 'description';
            $tpExisting   = 'fil';
            require __DIR__ . '/../../shared/_translation-plan.php';

            require __DIR__ . '/../../shared/_manobo-fields.php';
        ?>

        <!-- ── Row 5: Notify residents ───────────────────────────────────── -->
        <?php require __DIR__ . '/../../shared/_sms-notify.php'; ?>

        <!-- ── Row 6: Actions ────────────────────────────────────────────── -->
        <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-2">
            <a href="<?= e(route('admin/ordinances')) ?>"
               class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> <?= e(t('admin_ordinances.back_to_list_btn')) ?>
            </a>
            <button type="submit" class="btn btn-barangay btn-lg px-5">
                <i class="bi bi-cloud-arrow-up me-2"></i> <?= e(t('admin_ordinances.upload_ordinance_btn')) ?>
            </button>
        </div>

    </form>
</div><!-- /admin-card -->

</div>
</div>

<script>
const ordI18n = <?= json_encode([
    'pdfOnly' => t('admin_ordinances.pdf_only_error'),
    'pdfSize' => t('admin_ordinances.pdf_size_error'),
]) ?>;
function pdfUpload() {
    return {
        fileName:   null,
        fileSize:   '',
        pdfError:   '',
        dragActive: false,

        handleFile(e) {
            this.setFile(e.target.files[0]);
        },

        handleDrop(e) {
            this.dragActive = false;
            const f = e.dataTransfer.files[0];
            if (!f) return;
            if (f.type !== 'application/pdf') {
                this.pdfError = ordI18n.pdfOnly;
                return;
            }
            document.getElementById('pdf_file').files = e.dataTransfer.files;
            this.setFile(f);
        },

        setFile(f) {
            if (!f) return;
            if (f.type !== 'application/pdf') {
                this.pdfError = ordI18n.pdfOnly;
                return;
            }
            if (f.size > 10 * 1024 * 1024) {
                this.pdfError = ordI18n.pdfSize;
                return;
            }
            this.pdfError  = '';
            this.fileName  = f.name;
            this.fileSize  = (f.size / 1024 / 1024).toFixed(2) + ' MB';
        },

        clearFile() {
            this.fileName = null;
            this.fileSize = '';
            this.pdfError = '';
            document.getElementById('pdf_file').value = '';
        },
    };
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';