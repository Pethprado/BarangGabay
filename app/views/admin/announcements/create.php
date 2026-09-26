<?php
ob_start();
?>

<!-- Quill CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">
<style>
.ql-container { font-size:.9rem; font-family:inherit; min-height:220px; border-bottom-left-radius:.5rem!important; border-bottom-right-radius:.5rem!important; }
.ql-toolbar { border-top-left-radius:.5rem!important; border-top-right-radius:.5rem!important; background:#f9fafb; }
.ql-editor { min-height:220px; line-height:1.7; }
.ql-editor.ql-blank::before { color:#94a3b8; font-style:normal; }
</style>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb" style="font-size:.8rem;margin:0;">
        <li class="breadcrumb-item">
            <a href="<?= e(route('admin')) ?>" class="text-decoration-none" style="color:var(--brand-green);"><?= e(t('admin_nav.dashboard')) ?></a>
        </li>
        <li class="breadcrumb-item">
            <a href="<?= e(route('admin/announcements')) ?>" class="text-decoration-none" style="color:var(--brand-green);"><?= e(t('admin_nav.announcements')) ?></a>
        </li>
        <li class="breadcrumb-item active text-muted"><?= e(t('admin_announcements.breadcrumb_create')) ?></li>
    </ol>
</nav>

<!-- Page header -->
<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <p class="mb-0" style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8;"><?= e(t('admin_announcements.eyebrow')) ?></p>
        <h1 class="mb-0 mt-1" style="font-size:1.55rem;font-weight:800;color:var(--text-primary);line-height:1.1;"><?= e(t('admin_announcements.create_btn')) ?></h1>
        <p class="text-muted mt-1 mb-0" style="font-size:.82rem;"><?= e(t('admin_announcements.create_subtitle')) ?></p>
    </div>
    <a href="<?= e(route('admin/announcements')) ?>"
       class="d-inline-flex align-items-center gap-2 btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> <?= e(t('common.back')) ?>
    </a>
</div>

<form method="post"
      action="<?= e(route('admin/announcements')) ?>"
      enctype="multipart/form-data"
      x-data="createAnnouncement()">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <!-- Status is set by the action buttons below; hidden field carries it to POST -->
    <input type="hidden" name="status" x-bind:value="status">

    <div class="row g-4">

        <!-- ── Left column: main content ──────────────────────── -->
        <div class="col-lg-8 fade-up">

            <!-- Content card -->
            <div class="admin-card mb-4">
                <div class="admin-card-header">
                    <h2 class="admin-card-title">
                        <i class="bi bi-type me-2" style="color:var(--brand-green);"></i><?= e(t('admin_announcements.section_content')) ?>
                    </h2>
                </div>
                <div class="admin-card-body">

                    <!-- Title -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold" style="font-size:.855rem;">
                            <?= e(t('admin_announcements.field_title')) ?> <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               name="title"
                               id="ann-title"
                               class="form-control"
                               placeholder="<?= e(t('admin_announcements.field_title_ph')) ?>"
                               maxlength="500"
                               required
                               style="font-size:.9rem;">
                        <div class="form-text"><?= e(t('admin_announcements.field_title_help')) ?></div>
                    </div>

                    <!-- Body — Quill rich-text editor -->
                    <div>
                        <label class="form-label fw-semibold" style="font-size:.855rem;">
                            <?= e(t('admin_announcements.field_content')) ?> <span class="text-danger">*</span>
                        </label>
                        <!-- Quill mounts here; its HTML is copied to the hidden textarea on submit -->
                        <div id="quill-editor"
                             style="border:1px solid #dee2e6;border-radius:.5rem;"
                             aria-label="<?= e(t('admin_announcements.field_content_aria')) ?>"></div>
                        <!-- Hidden field — submitted to controller as $_POST['body'].
                             Deliberately NOT `required`: the browser refuses to
                             submit a form containing an invalid control it cannot
                             focus, and a display:none textarea can never be
                             focused or shown a validation bubble. With `required`
                             here the form failed silently with nothing but a
                             console error, and no announcement could be created
                             at all. The body is validated server-side instead
                             (title required, body at least 50 characters), which
                             reports the problem where staff can actually read it. -->
                        <textarea name="body"
                                  id="body-hidden"
                                  style="display:none;"></textarea>
                        <p class="form-text text-muted mt-1" style="font-size:.77rem;">
                            <i class="bi bi-info-circle me-1"></i><?= e(t('admin_announcements.field_content_help')) ?>
                        </p>
                    </div>

                </div>
            </div>

            <!-- Manobo translation widget -->
            <?php
            $__mTitleVal  = '';
            $__mBodyVal   = '';
            $__mBodyName  = 'body_manobo';
            $__mBodyLabel = t('manobo_fields.label_body');
            $__mAudioPath = null;
            $__eTitleVal  = '';
            $__eBodyVal   = '';
            $__eBodyName  = 'body_en';
            $__eBodyLabel = t('manobo_fields.label_body');
            // Staff hear the post before a resident does — the only reliable
            // way to catch an abbreviation or a missing full stop that reads
            // fine but sounds wrong. Above the translation panels because it
            // is about the content just typed, not a translation of it.
            // Bring content in from a Facebook caption, a link, or attach the
            // original post. Placed above the translation panels because it
            // fills the fields those panels then translate.
            $__ipType   = 'announcement';
            $__ipSource = null;
            require __DIR__ . '/../../shared/_import-panel.php';

            $__vpSourceLang = $__eSourceLang ?? 'fil';
            $__vpType       = 'announcement';
            $__vpRow        = null;          // nothing saved yet — preview only
            require __DIR__ . '/../../shared/_voice-preview.php';

            require __DIR__ . '/../../shared/_english-fields.php';

            /* What Save will do, before it is pressed. On the create form
               this is the only chance to catch a too-long post or a missing
               provider before the first save rather than after it. */
            $tpTitleField = 'title';
            $tpBodyField  = 'body';
            $tpExisting   = 'fil';
            require __DIR__ . '/../../shared/_translation-plan.php';

            require __DIR__ . '/../../shared/_manobo-fields.php';
            ?>

            <!-- Cover image card -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h2 class="admin-card-title">
                        <i class="bi bi-image me-2" style="color:var(--brand-green);"></i><?= e(t('admin_announcements.cover_image_title')) ?>
                        <span class="text-muted fw-normal ms-1" style="font-size:.78rem;"><?= e(t('common.optional')) ?></span>
                    </h2>
                </div>
                <div class="admin-card-body">

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
                                <p style="margin:0;font-size:.855rem;color:#4a5568;font-weight:600;">
                                    <?= e(t('admin_announcements.dropzone_text')) ?>
                                </p>
                                <p style="margin:.25rem 0 0;font-size:.77rem;color:#94a3b8;">
                                    <?= e(t('admin_announcements.dropzone_hint')) ?>
                                </p>
                            </div>
                        </template>

                        <template x-if="preview">
                            <div @click.stop>
                                <img :src="preview" alt="Preview" class="upload-preview-img mb-3">
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <button type="button"
                                            @click="clearImage()"
                                            class="btn btn-sm btn-outline-danger"
                                            style="font-size:.77rem;">
                                        <i class="bi bi-trash3"></i> <?= e(t('admin_announcements.remove_btn')) ?>
                                    </button>
                                    <button type="button"
                                            @click="$refs.fileInput.click()"
                                            class="btn btn-sm btn-outline-secondary"
                                            style="font-size:.77rem;">
                                        <i class="bi bi-pencil"></i> <?= e(t('admin_announcements.replace_btn')) ?>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <input type="file"
                           name="cover_image"
                           accept="image/jpeg,image/png,image/gif"
                           x-ref="fileInput"
                           @change="handleFileChange($event)"
                           style="display:none;">

                </div>
            </div>

        </div><!-- /col-lg-8 -->

        <!-- ── Right column: settings panel ───────────────────── -->
        <div class="col-lg-4 fade-up fade-up-delay-1">

            <div class="admin-card mb-4" style="position:sticky;top:80px;">
                <div class="admin-card-header">
                    <h2 class="admin-card-title">
                        <i class="bi bi-sliders me-2" style="color:var(--brand-green);"></i><?= e(t('admin_announcements.settings_title')) ?>
                    </h2>
                </div>
                <div class="admin-card-body">

                    <!-- Category -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:.845rem;">
                            <?= e(t('admin_announcements.field_category')) ?> <span class="text-danger">*</span>
                        </label>
                        <select name="category" class="form-select" style="font-size:.875rem;">
                            <option value="general">🗂 General</option>
                            <option value="health">🏥 Health (Kalusugan)</option>
                            <option value="safety">⚠️ Safety (Kaligtasan)</option>
                            <option value="government">🏛 Government (Pamahalaan)</option>
                            <option value="infrastructure">🏗 Infrastructure (Imprastruktura)</option>
                            <option value="social">🤝 Social (Panlipunan)</option>
                        </select>
                    </div>

                    <!-- Urgency -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold" style="font-size:.845rem;">
                            <?= e(t('admin_announcements.field_urgency')) ?>
                        </label>
                        <select name="urgency" class="form-select" style="font-size:.875rem;">
                            <option value="normal">⚪ Normal</option>
                            <option value="important">🟡 Important (Mahalaga)</option>
                            <option value="urgent">🔴 Urgent (Napaka-importante)</option>
                        </select>
                        <p class="form-text text-muted mt-1" style="font-size:.77rem;">
                            <?= e(t('admin_announcements.urgency_hint')) ?>
                        </p>
                    </div>

                    <hr style="border-color:#e4ece6;margin:1rem 0 1.25rem;">

                    <!-- ── Publish timing ──────────────────────────────
                         Two radios rather than a date box that is always
                         present: an empty date field next to a "Publish"
                         button is ambiguous (does blank mean now, or does it
                         mean I forgot?), while picking "Publish immediately"
                         is an explicit choice. The date input is disabled —
                         not merely hidden — while "now" is selected, because
                         a hidden input still posts its value, and a leftover
                         date from a changed mind would silently delay the
                         announcement. -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold" style="font-size:.845rem;">
                            <i class="bi bi-clock me-1" style="color:var(--brand-green);"></i>
                            <?= e(t('admin_announcements.timing_label')) ?>
                        </label>

                        <div class="form-check mb-1">
                            <input class="form-check-input" type="radio" id="timing-now"
                                   value="now" x-model="timing">
                            <label class="form-check-label" for="timing-now" style="font-size:.845rem;">
                                <?= e(t('admin_announcements.timing_now')) ?>
                            </label>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="radio" id="timing-later"
                                   value="later" x-model="timing">
                            <label class="form-check-label" for="timing-later" style="font-size:.845rem;">
                                <?= e(t('admin_announcements.timing_schedule')) ?>
                            </label>
                        </div>

                        <div x-show="timing === 'later'" x-cloak class="mt-2">
                            <input type="datetime-local"
                                   name="publish_at"
                                   class="form-control"
                                   style="font-size:.875rem;"
                                   x-model="publishAt"
                                   :disabled="timing !== 'later'"
                                   :required="timing === 'later'"
                                   min="<?= e(date('Y-m-d\TH:i')) ?>">
                            <p class="form-text text-muted mt-1" style="font-size:.77rem;">
                                <?= e(t('admin_announcements.timing_when_help')) ?>
                            </p>
                        </div>

                        <p class="form-text text-muted mt-1" style="font-size:.77rem;"
                           x-show="timing === 'now'">
                            <?= e(t('admin_announcements.timing_now_help')) ?>
                        </p>
                    </div>

                    <?php
                    // Scheduled posts text residents at go-live, not at save,
                    // so say so here rather than letting staff wonder why no
                    // message went out.
                    $smsNote = t('sms_notify.scheduled_note');
                    require __DIR__ . '/../../shared/_sms-notify.php';

                    // Who the notice is for. Directly under the SMS choice,
                    // because together they decide the same thing: whether to
                    // send, and to whom.
                    $ptSelected = (string) old('target_purok', '');
                    $ptCheckin  = false;
                    require __DIR__ . '/../../shared/_purok-target.php';
                    ?>

                    <!-- Action buttons -->
                    <div class="d-grid gap-2">
                        <button type="submit"
                                @click="status = 'published'"
                                class="btn-barangay justify-content-center"
                                style="width:100%;padding:11px 16px;font-size:.875rem;">
                            <i class="bi me-1" :class="timing === 'later' ? 'bi-calendar-check' : 'bi-send-fill'"></i>
                            <span x-text="timing === 'later'
                                ? <?= e(json_encode(t('admin_announcements.timing_schedule'))) ?>
                                : <?= e(json_encode(t('admin_announcements.publish_now_btn'))) ?>"></span>
                        </button>
                        <button type="submit"
                                @click="status = 'draft'"
                                class="btn btn-outline-secondary"
                                style="font-size:.875rem;padding:10px 16px;">
                            <i class="bi bi-file-earmark-text me-1"></i>
                            <?= e(t('admin_announcements.save_draft_btn')) ?>
                        </button>
                    </div>

                </div>
            </div>

            <!-- Tip card -->
            <div class="admin-card" style="border-left:3px solid var(--brand-green);">
                <div class="admin-card-body" style="padding:.9rem 1rem;">
                    <p style="font-size:.8rem;font-weight:700;color:var(--brand-green);margin:0 0 6px;">
                        <i class="bi bi-lightbulb-fill me-1"></i><?= e(t('admin_announcements.tips_title')) ?>
                    </p>
                    <ul style="font-size:.77rem;color:#4a5568;margin:0;padding-left:1.1rem;line-height:1.9;">
                        <li><?= t('admin_announcements.tip_published') ?></li>
                        <li><?= t('admin_announcements.tip_draft') ?></li>
                        <li><?= t('admin_announcements.tip_urgent') ?></li>
                        <li><?= e(t('admin_announcements.tip_cover')) ?></li>
                    </ul>
                </div>
            </div>

        </div><!-- /col-lg-4 -->

    </div><!-- /row -->

</form>

<!-- Quill JS -->
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
// ── Quill rich-text editor ──────────────────────────────────────────────
let quillEditor = null;

document.addEventListener('DOMContentLoaded', function () {
    quillEditor = new Quill('#quill-editor', {
        theme: 'snow',
        placeholder: <?= json_encode(t('admin_announcements.quill_placeholder')) ?>,
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

    // Keep the hidden textarea in step with the editor as staff type, rather
    // than only at submit time. Syncing live means the field that actually
    // gets posted is never stale — including on paths that submit the form
    // without a click (autosave, Enter in a text input, a script submit()).
    const bodyHidden = document.getElementById('body-hidden');
    const syncBody = function () {
        if (bodyHidden && quillEditor) {
            // getLength() is 1 for an empty document (the trailing newline),
            // so an untouched editor posts "" rather than "<p><br></p>" —
            // which would sail past a server-side "is it empty" check.
            bodyHidden.value = quillEditor.getText().trim() === ''
                ? ''
                : quillEditor.root.innerHTML;
        }
    };

    quillEditor.on('text-change', syncBody);

    // Belt and braces for anything that changes the document without firing
    // text-change (a programmatic setContents, for instance).
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', syncBody);
    }
});

// ── Alpine component ────────────────────────────────────────────────────
function createAnnouncement() {
    return {
        status:  'draft',
        preview: null,

        // Publish timing. 'now' is the default because it is what most posts
        // are; scheduling is the deliberate extra step.
        timing: 'now',
        // Pre-filled with tomorrow morning so choosing "schedule" lands on a
        // sensible moment instead of an empty box. Staff adjust from there.
        publishAt: <?= json_encode(date('Y-m-d\TH:i', strtotime('tomorrow 08:00'))) ?>,

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
$content   = ob_get_clean();
$pageTitle = t('admin_announcements.create_btn');
require __DIR__ . '/../../layouts/admin.php';