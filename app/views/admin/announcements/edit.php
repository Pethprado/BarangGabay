<?php
/**
 * Admin — edit announcement form.
 * Variables: $announcement (array)
 */
$announcement = $announcement ?? [];

ob_start();
?>

<!-- Print-only styles -->
<style>
@media print {
    .admin-sidebar, .admin-topbar, .pending-banner,
    .admin-flash, .no-print { display: none !important; }
    .admin-main  { margin-left: 0 !important; }
    .admin-content { padding: 0 !important; }
    .print-only  { display: block !important; }
    body { background: #fff !important; }
}
</style>

<!-- Print preview (hidden on screen, visible when printing) -->
<div class="print-only" style="display:none; padding:2rem; font-family:Georgia,serif;">
    <div style="text-align:center; border-bottom:2px solid #000; padding-bottom:1rem; margin-bottom:1.5rem;">
        <h2 style="margin:0; font-size:1.1rem; text-transform:uppercase; letter-spacing:.05em;">
            BARANGAY BAYOGO, MADRID, SURIGAO DEL SUR
        </h2>
        <p style="margin:.25rem 0 0; font-size:.85rem;">Official Barangay Communication</p>
    </div>
    <h1 style="font-size:1.4rem; margin-bottom:.5rem;"><?= e($announcement['title'] ?? '') ?></h1>
    <p style="font-size:.8rem; color:#555; margin-bottom:1.5rem;">
        Category: <?= e(ucfirst($announcement['category'] ?? '')) ?> &nbsp;|&nbsp;
        Urgency: <?= e(ucfirst($announcement['urgency'] ?? '')) ?> &nbsp;|&nbsp;
        Status: <?= e(ucfirst($announcement['status'] ?? '')) ?> &nbsp;|&nbsp;
        Date: <?= !empty($announcement['published_at']) ? date('F j, Y', strtotime($announcement['published_at'])) : date('F j, Y') ?>
    </p>
    <div style="line-height:1.7; font-size:.95rem;">
        <?= $announcement['body'] ?? '' ?>
    </div>
    <div style="margin-top:3rem; border-top:1px solid #000; padding-top:1rem; font-size:.75rem; color:#555; text-align:center;">
        Barangay Bayogo, Madrid, Surigao del Sur &mdash; Official Announcement
    </div>
</div>

<!-- Screen-only form -->
<div class="no-print">

<div class="d-flex align-items-center justify-content-between mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?= e(route('admin/announcements')) ?>"><?= e(t('admin_nav.announcements')) ?></a></li>
            <li class="breadcrumb-item active"><?= e(t('admin_announcements.breadcrumb_edit_prefix')) ?>: <?= e($announcement['title'] ?? '') ?></li>
        </ol>
    </nav>
    <button type="button" onclick="window.print()"
            class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
        <i class="bi bi-printer"></i> <?= e(t('admin_announcements.print_btn')) ?>
    </button>
</div>

<form method="post"
      action="<?= e(route('admin/announcements/' . ($announcement['id'] ?? ''))) ?>"
      enctype="multipart/form-data"
      x-data="editAnnouncement()">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="row g-4">

        <!-- Left column -->
        <div class="col-lg-8 fade-up">
            <div class="admin-card mb-4">
                <div class="admin-card-header">
                    <h2 class="admin-card-title">
                        <i class="bi bi-type me-2" style="color:var(--brand-green);"></i><?= e(t('admin_announcements.section_content')) ?>
                    </h2>
                </div>
                <div class="admin-card-body">

                    <div class="mb-4">
                        <label for="title" class="form-label fw-semibold" style="font-size:.855rem;">
                            <?= e(t('admin_announcements.field_title')) ?> <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="title" name="title" class="form-control"
                               value="<?= e($announcement['title'] ?? '') ?>" maxlength="500" required
                               style="font-size:.9rem;">
                    </div>

                    <div>
                        <label class="form-label fw-semibold" style="font-size:.855rem;">
                            <?= e(t('admin_announcements.field_content')) ?> <span class="text-danger">*</span>
                        </label>
                        <!-- Quill mounts here; its HTML is copied to the hidden textarea on submit -->
                        <div id="quill-editor"
                             style="border:1px solid #dee2e6;border-radius:.5rem;"
                             aria-label="<?= e(t('admin_announcements.field_content_aria')) ?>"></div>
                        <!-- Hidden field — submitted to controller as $_POST['body'].
                             Not `required`: a display:none control cannot be
                             focused, so the browser blocks the whole form with
                             only a console error instead of showing a message.
                             Here it happens to be pre-filled, which masked the
                             problem — clearing the body was enough to make the
                             form silently refuse to save. Validated server-side
                             instead, where the error is actually reported. -->
                        <textarea name="body"
                                  id="body-hidden"
                                  style="display:none;"><?= e($announcement['body'] ?? '') ?></textarea>
                        <p class="form-text text-muted mt-1" style="font-size:.77rem;">
                            <i class="bi bi-info-circle me-1"></i><?= e(t('admin_announcements.field_content_help')) ?>
                        </p>
                    </div>

                </div>
            </div>

            <!-- Manobo translation widget -->
            <?php
            $__mTitleVal  = $announcement['title_manobo'] ?? '';
            $__mBodyVal   = $announcement['body_manobo']  ?? '';
            $__mBodyName  = 'body_manobo';
            $__mBodyLabel = t('manobo_fields.label_body');
            $__mAudioPath = $announcement['audio_manobo_path'] ?? null;
            $__eSourceLang = $announcement['source_lang'] ?? 'fil';
            $__eTitleVal  = $announcement['title_en'] ?? '';
            $__eBodyVal   = $announcement['body_en'] ?? '';
            $__eBodyName  = 'body_en';
            $__eBodyLabel = t('manobo_fields.label_body');
            // Staff hear the post before a resident does — the only reliable
            // way to catch an abbreviation or a missing full stop that reads
            // fine but sounds wrong. Above the translation panels because it
            // is about the content just typed, not a translation of it.
            // See the note in create.php.
            $__ipType   = 'announcement';
            $__ipSource = $announcement['source_url'] ?? null;
            require __DIR__ . '/../../shared/_import-panel.php';

            $__vpSourceLang = $__eSourceLang ?? 'fil';
            $__vpType       = 'announcement';
            $__vpRow        = $announcement;
            require __DIR__ . '/../../shared/_voice-preview.php';

            /* Where each language stands, above the fields for filling them
               in by hand — the order matters. A staff member who can see
               that EN is missing because the daily allowance ran out will
               wait for tonight; one who cannot will retype it. */
            $lbRow       = $announcement;
            $lbType      = 'announcement';
            $lbBodyField = 'body';
            $lbAttempts  = \App\Models\TranslationAttempt::forContent('announcement', (int) $announcement['id']);
            $lbRedirect  = '/admin/announcements';
            require __DIR__ . '/../../shared/_language-status-card.php';

            require __DIR__ . '/../../shared/_english-fields.php';

            /* After the source-language radios, because it reports on the
               choice made there — and before the hand-entry fields, because
               it is what tells someone whether typing them is necessary. */
            $tpTitleField = 'title';
            $tpBodyField  = 'body';
            $tpExisting   = (string) ($announcement['source_lang'] ?? 'fil');
            require __DIR__ . '/../../shared/_translation-plan.php';

            require __DIR__ . '/../../shared/_manobo-fields.php';
            ?>

            <!-- Cover image card -->
            <div class="admin-card mt-4">
                <div class="admin-card-header">
                    <h2 class="admin-card-title">
                        <i class="bi bi-image me-2" style="color:var(--brand-green);"></i><?= e(t('admin_announcements.cover_image_title')) ?>
                        <span class="text-muted fw-normal ms-1" style="font-size:.78rem;"><?= e(t('common.optional')) ?></span>
                    </h2>
                </div>
                <div class="admin-card-body">

                    <?php if (!empty($announcement['cover_image_url'])): ?>
                    <p class="mb-2" style="font-size:.78rem;color:var(--text-muted);"><?= e(t('admin_announcements.current_cover')) ?></p>
                    <img src="<?= e(asset($announcement['cover_image_url'])) ?>" alt=""
                         class="upload-preview-img mb-3" x-show="!preview">
                    <?php endif; ?>

                    <div class="drop-zone"
                         :class="{ 'has-file': preview }"
                         @click="$refs.fileInput.click()"
                         @dragover.prevent="$el.classList.add('drag-active')"
                         @dragleave.prevent="$el.classList.remove('drag-active')"
                         @drop.prevent="handleDrop($event)">

                        <template x-if="!preview">
                            <div>
                                <i class="bi bi-cloud-arrow-up"
                                   style="font-size:2rem;color:#a8d5b5;display:block;margin-bottom:.5rem;"></i>
                                <p style="margin:0;font-size:.855rem;color:var(--text-secondary);font-weight:600;">
                                    <?= e(t('admin_announcements.dropzone_replace_text')) ?>
                                </p>
                                <p style="margin:.25rem 0 0;font-size:.77rem;color:var(--text-muted);">
                                    <?= e(t('admin_announcements.dropzone_hint')) ?>
                                </p>
                            </div>
                        </template>

                        <template x-if="preview">
                            <div @click.stop>
                                <img :src="preview" alt="Preview" class="upload-preview-img mb-3">
                                <button type="button" class="btn btn-sm btn-outline-danger" @click="clearImage()">
                                    <i class="bi bi-x-lg me-1"></i><?= e(t('admin_announcements.remove_new_selection')) ?>
                                </button>
                            </div>
                        </template>
                    </div>
                    <input type="file" name="cover_image" x-ref="fileInput" accept="image/jpeg,image/png"
                           style="display:none;" @change="handleFileChange($event)">
                </div>
            </div>
        </div>

        <!-- Right column -->
        <div class="col-lg-4 fade-up fade-up-delay-1">

            <div class="admin-card mb-4">
                <h6 class="fw-semibold mb-3 text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.08em;"><?= e(t('admin_announcements.settings_title')) ?></h6>

                <div class="mb-3">
                    <label for="category" class="form-label fw-semibold small"><?= e(t('admin_announcements.field_category')) ?></label>
                    <select id="category" name="category" class="form-select">
                        <?php
                        $cats = ['general','health','safety','government','infrastructure','social'];
                        foreach ($cats as $c):
                            $sel = ($announcement['category'] ?? '') === $c ? 'selected' : '';
                        ?>
                        <option value="<?= $c ?>" <?= $sel ?>><?= ucfirst($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="urgency" class="form-label fw-semibold small"><?= e(t('admin_announcements.field_urgency')) ?></label>
                    <select id="urgency" name="urgency" class="form-select">
                        <?php foreach (['normal','important','urgent'] as $u):
                            $sel = ($announcement['urgency'] ?? '') === $u ? 'selected' : '';
                        ?>
                        <option value="<?= $u ?>" <?= $sel ?>><?= ucfirst($u) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="status" class="form-label fw-semibold small"><?= e(t('admin_announcements.field_status')) ?></label>
                    <select id="status" name="status" class="form-select">
                        <?php foreach (['draft','published','archived'] as $s):
                            $sel = ($announcement['status'] ?? '') === $s ? 'selected' : '';
                        ?>
                        <option value="<?= $s ?>" <?= $sel ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php
                // Publish timing is only offered for a post that has not gone
                // out yet. Once residents have been notified, the publish date
                // is history — the record of when they were told — so editing
                // a typo must not let it be rewritten.
                $isLive      = \App\Models\Announcement::isVisibleNow($announcement);
                $isScheduled = \App\Models\Announcement::isScheduled($announcement);
                ?>

                <?php if ($isLive): ?>
                <div>
                    <label class="form-label fw-semibold small"><?= e(t('admin_announcements.col_published')) ?></label>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-check-circle-fill me-1 text-success"></i>
                        <?= e(format_datetime($announcement['published_at'] ?? '')) ?>
                    </p>
                </div>
                <?php else: ?>
                <div x-data="{
                        timing: <?= e(json_encode($isScheduled ? 'later' : 'now')) ?>,
                        publishAt: <?= e(json_encode(
                            format_datetime_input($announcement['published_at'] ?? '')
                                ?: date('Y-m-d\TH:i', strtotime('tomorrow 08:00'))
                        )) ?>
                     }">
                    <label class="form-label fw-semibold small">
                        <i class="bi bi-clock me-1" style="color:var(--brand-green);"></i>
                        <?= e(t('admin_announcements.timing_label')) ?>
                    </label>

                    <div class="form-check mb-1">
                        <input class="form-check-input" type="radio" id="edit-timing-now"
                               value="now" x-model="timing">
                        <label class="form-check-label small" for="edit-timing-now">
                            <?= e(t('admin_announcements.timing_now')) ?>
                        </label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="edit-timing-later"
                               value="later" x-model="timing">
                        <label class="form-check-label small" for="edit-timing-later">
                            <?= e(t('admin_announcements.timing_schedule')) ?>
                        </label>
                    </div>

                    <!-- Disabled rather than merely hidden: a hidden input
                         still posts, and a stale date would delay the post. -->
                    <div x-show="timing === 'later'" x-cloak class="mt-2">
                        <input type="datetime-local"
                               name="publish_at"
                               class="form-control"
                               x-model="publishAt"
                               :disabled="timing !== 'later'"
                               :required="timing === 'later'">
                        <p class="form-text text-muted mt-1" style="font-size:.77rem;">
                            <?= e(t('admin_announcements.timing_when_help')) ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <?php
                // The SMS choice only means something while the post has not
                // been announced yet. Once notified_at is stamped the dispatch
                // is spent — ScheduledPublisher claims on that column, so
                // re-ticking the box would do nothing at all. Showing a live
                // control that silently does nothing is worse than showing
                // none, so an already-announced post gets a statement of fact
                // instead.
                $alreadyAnnounced = !empty($announcement['notified_at']);
                ?>

                <?php if ($alreadyAnnounced): ?>
                <div>
                    <label class="form-label fw-semibold small">
                        <i class="bi bi-chat-dots me-1" style="color:var(--brand-green);"></i>
                        <?= e(t('sms_notify.label')) ?>
                    </label>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-check-circle-fill me-1 text-success"></i>
                        <?= e(t('sms_notify.already_sent', [
                            'when' => format_datetime($announcement['notified_at']),
                        ])) ?>
                    </p>
                </div>
                <?php else: ?>
                <?php
                $smsChecked = (int) ($announcement['notify_sms'] ?? 1) === 1;
                $smsNote    = t('sms_notify.scheduled_note');
                require __DIR__ . '/../../shared/_sms-notify.php';
                ?>
                <?php endif; ?>

                <?php
                // Outside the branch above: which purok a notice is for stays
                // editable whether or not it has already been sent, because it
                // is also what the post's badge says to readers.
                $ptSelected = (string) ($announcement['target_purok'] ?? '');
                $ptCheckin  = (int) ($announcement['asks_safety_checkin'] ?? 0) === 1;
                require __DIR__ . '/../../shared/_purok-target.php';
                ?>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-barangay">
                    <i class="bi bi-check-lg me-1"></i> <?= e(t('admin_announcements.save_changes_btn')) ?>
                </button>
                <a href="<?= e(route('admin/announcements')) ?>" class="btn btn-outline-secondary"><?= e(t('common.back')) ?></a>
            </div>

            <!-- Danger zone — the delete button below targets #deleteAnnouncementForm via the
                 form="" attribute rather than a nested <form>, since a <form> nested inside
                 another <form> is invalid HTML: the browser drops the inner opening tag during
                 parsing, so a truly nested delete form would silently submit to the EDIT
                 action instead of the delete route. -->
            <div class="admin-card mt-4 border-danger border-opacity-25">
                <h6 class="fw-semibold mb-2 text-danger text-uppercase" style="font-size:.7rem;letter-spacing:.08em;"><?= e(t('admin_announcements.danger_zone')) ?></h6>
                <p class="small text-muted mb-3"><?= e(t('admin_announcements.danger_zone_desc')) ?></p>
                <button type="submit" form="deleteAnnouncementForm" class="btn btn-outline-danger btn-sm w-100"
                        onclick="return confirm(<?= e(json_encode(t('admin_announcements.confirm_delete_simple'))) ?>);">
                    <i class="bi bi-trash me-1"></i> <?= e(t('admin_announcements.delete_title')) ?>
                </button>
            </div>

        </div>
    </div>
</form>

<!-- Delete form lives outside the edit form on purpose — see comment above. -->
<form id="deleteAnnouncementForm" method="post"
      action="<?= e(route('admin/announcements/' . ($announcement['id'] ?? '') . '/delete')) ?>"
      style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
</form>

</div><!-- /no-print -->

<!-- Quill CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">
<style>
.ql-container { font-size:.9rem; font-family:inherit; min-height:220px; border-bottom-left-radius:.5rem!important; border-bottom-right-radius:.5rem!important; }
.ql-toolbar { border-top-left-radius:.5rem!important; border-top-right-radius:.5rem!important; background:#f9fafb; }
.ql-editor { min-height:220px; line-height:1.7; }
</style>

<!-- Quill JS -->
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
// ── Quill rich-text editor, pre-filled with the existing announcement body ──
let quillEditor = null;

document.addEventListener('DOMContentLoaded', function () {
    quillEditor = new Quill('#quill-editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ header: [2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['blockquote', 'link'],
                ['clean'],
            ],
        },
    });

    const bodyHidden = document.getElementById('body-hidden');
    if (bodyHidden && bodyHidden.value) {
        quillEditor.root.innerHTML = bodyHidden.value;
    }

    // Sync as staff type, not only on submit, so the posted value can never
    // be stale. See the note on the textarea above.
    const syncBody = function () {
        if (bodyHidden && quillEditor) {
            bodyHidden.value = quillEditor.getText().trim() === ''
                ? ''
                : quillEditor.root.innerHTML;
        }
    };

    quillEditor.on('text-change', syncBody);

    const form = document.querySelector('form[x-data]');
    if (form) {
        form.addEventListener('submit', syncBody);
    }
});

// ── Alpine component: cover image replace/preview ───────────────────────
function editAnnouncement() {
    return {
        preview: null,

        handleFileChange(e) {
            const file = e.target.files[0];
            if (!file) return;
            if (file.size > 5 * 1024 * 1024) {
                alert(<?= json_encode(t('admin_announcements.alert_image_size')) ?>);
                e.target.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = (ev) => { this.preview = ev.target.result; };
            reader.readAsDataURL(file);
        },

        handleDrop(e) {
            e.currentTarget.classList.remove('drag-active');
            const file = e.dataTransfer.files[0];
            if (!file || !file.type.startsWith('image/')) return;
            const dt = new DataTransfer();
            dt.items.add(file);
            this.$refs.fileInput.files = dt.files;
            this.$refs.fileInput.dispatchEvent(new Event('change'));
        },

        clearImage() {
            this.preview = null;
            this.$refs.fileInput.value = '';
        },
    };
}
</script>

<?php
/* The Translate now forms, out here where they are legal HTML — the status
   card above sits inside the post's edit form and cannot emit them itself. */
require __DIR__ . '/../../shared/_language-retry-forms.php';
?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';