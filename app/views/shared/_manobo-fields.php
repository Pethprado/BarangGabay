<?php
/**
 * _manobo-fields.php — Manual Manobo (may halong Bisaya/Surigaonon) translation widget.
 *
 * Include inside an admin <form enctype="multipart/form-data"> after setting:
 *   $__mTitleVal  (string)      — existing title_manobo, '' for new posts
 *   $__mBodyVal   (string)      — existing body_manobo / description_manobo, '' for new
 *   $__mBodyName  (string)      — POST field name: 'body_manobo' or 'description_manobo'
 *   $__mBodyLabel (string)      — human label, e.g. 'Katawan' or 'Paglalarawan'
 *   $__mAudioPath (string|null) — existing audio_manobo_path, or null for new posts
 *
 * Only rendered for roles: staff, admin, superadmin.
 */
if (!in_array($_SESSION['role'] ?? '', ['staff', 'admin', 'superadmin'], true)) {
    return;
}

$__mTitleVal  = $__mTitleVal  ?? '';
$__mBodyVal   = $__mBodyVal   ?? '';
$__mBodyName  = $__mBodyName  ?? 'body_manobo';
$__mBodyLabel = $__mBodyLabel ?? t('manobo_fields.label_body');
$__mAudioPath = $__mAudioPath ?? null;
?>

<div class="admin-card mb-4">

    <div class="admin-card-header">
        <h2 class="admin-card-title d-flex align-items-center gap-2 flex-wrap">
            <i class="bi bi-translate" style="color:#e8a020;"></i>
            <?= e(t('manobo_fields.heading')) ?>
            <span class="badge rounded-pill ms-1"
                  style="background:#e8a020;color:#fff;font-size:.62rem;font-weight:700;padding:.28em .7em;letter-spacing:.03em;">
                <?= e(t('manobo_fields.optional_badge')) ?>
            </span>
        </h2>
        <p class="text-muted mb-0 mt-1" style="font-size:.78rem;line-height:1.5;">
            <?= e(t('manobo_fields.intro_1')) ?>
            <?= e(t('manobo_fields.intro_2')) ?>
            <?= e(t('manobo_fields.intro_3_pre')) ?> <strong><?= e(t('manobo_fields.intro_3_strong')) ?></strong> <?= e(t('manobo_fields.intro_3_post')) ?>
        </p>
    </div>

    <div class="admin-card-body">

        <!-- ════════════════════════════════════════════════════════
             SECTION 1 — TEXT TRANSLATION
             ════════════════════════════════════════════════════════ -->
        <div class="mb-4 pb-4" style="border-bottom:1px solid #e4ece6;" x-data="manoboDraft()">

            <p class="fw-bold mb-2" style="font-size:.78rem;letter-spacing:.06em;text-transform:uppercase;color:var(--brand-primary);">
                <i class="bi bi-fonts me-1"></i> <?= e(t('manobo_fields.text_section')) ?>
            </p>

            <?php /*
                 * Why this is worth a staff member's two minutes.
                 *
                 * Typing here is the only thing that makes the MN button work
                 * for residents today: it needs Manobo text, not an API. The
                 * automatic translator needs Anthropic credits the account does
                 * not currently have, so a post with these fields empty has no
                 * Manobo at all — while a post with them filled reads aloud
                 * immediately, at no cost.
                 */ ?>
            <div class="mb-3 rounded-3 p-2"
                 style="background:var(--surface-muted);border-left:3px solid var(--brand-primary);">
                <p class="mb-0" style="font-size:.76rem;line-height:1.55;color:var(--text-secondary);">
                    <i class="bi bi-lightbulb me-1"></i><?= e(t('manobo_fields.why_manual')) ?>
                </p>
            </div>

            <?php /*
                 * The source text, side by side with the fields being typed.
                 * Somebody translating should not have to scroll back up the
                 * form to re-read the sentence they are translating.
                 */ ?>
            <div class="mb-3 rounded-3" style="border:1px solid var(--border);overflow:hidden;">
                <div class="d-flex align-items-center justify-content-between gap-2 px-2 py-1"
                     style="background:var(--surface-muted);">
                    <span class="fw-semibold" style="font-size:.72rem;color:var(--text-secondary);">
                        <i class="bi bi-file-text me-1"></i><?= e(t('manobo_fields.reference_heading')) ?>
                    </span>
                    <div class="d-flex align-items-center gap-1">
                        <button type="button" @click="refresh()"
                                class="btn btn-sm btn-link p-0 text-decoration-none"
                                style="font-size:.7rem;">
                            <i class="bi bi-arrow-clockwise"></i> <?= e(t('manobo_fields.reference_refresh')) ?>
                        </button>
                        <button type="button" @click="showRef = !showRef"
                                class="btn btn-sm btn-link p-0 text-decoration-none"
                                style="font-size:.7rem;">
                            <span x-text="showRef
                                ? <?= e(json_encode(t('common.hide'))) ?>
                                : <?= e(json_encode(t('common.show'))) ?>"></span>
                        </button>
                    </div>
                </div>
                <div x-show="showRef" class="px-2 py-2">
                    <p class="mb-1 fw-semibold" style="font-size:.78rem;color:var(--text-primary);"
                       x-text="refTitle || <?= e(json_encode(t('manobo_fields.reference_empty'))) ?>"></p>
                    <p class="mb-0" style="font-size:.76rem;line-height:1.6;white-space:pre-line;color:var(--text-secondary);max-height:9rem;overflow:auto;"
                       x-text="refBody"></p>
                </div>
            </div>

            <!-- Manobo title -->
            <div class="mb-3">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                    <label for="manobo_title_input" class="form-label fw-semibold mb-0" style="font-size:.845rem;">
                        <?= e(t('manobo_fields.title_label')) ?>
                    </label>
                    <button type="button"
                            @click="draft('title')"
                            :disabled="busy === 'title'"
                            class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1"
                            style="font-size:.74rem;">
                        <i class="bi bi-book"></i>
                        <span x-text="busy === 'title'
                            ? <?= e(json_encode(t('admin_manobo.draft_working'))) ?>
                            : <?= e(json_encode(t('admin_manobo.draft_btn'))) ?>"></span>
                    </button>
                </div>
                <input type="text"
                       id="manobo_title_input"
                       name="title_manobo"
                       class="form-control"
                       value="<?= e($__mTitleVal) ?>"
                       maxlength="500"
                       placeholder="<?= e(t('manobo_fields.title_ph')) ?>"
                       style="font-size:.875rem;">
                <div class="form-text">
                    <?= e(t('manobo_fields.title_help')) ?>
                </div>
            </div>

            <!-- Manobo body / description -->
            <div>
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                    <label for="manobo_body_input" class="form-label fw-semibold mb-0" style="font-size:.845rem;">
                        <?= e(t('manobo_fields.in_manobo', ['label' => $__mBodyLabel])) ?>
                    </label>
                    <button type="button"
                            @click="draft('body')"
                            :disabled="busy === 'body'"
                            class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1"
                            style="font-size:.74rem;">
                        <i class="bi bi-book"></i>
                        <span x-text="busy === 'body'
                            ? <?= e(json_encode(t('admin_manobo.draft_working'))) ?>
                            : <?= e(json_encode(t('admin_manobo.draft_btn'))) ?>"></span>
                    </button>
                </div>
                <textarea id="manobo_body_input"
                          name="<?= e($__mBodyName) ?>"
                          class="form-control"
                          rows="6"
                          placeholder="<?= e(t('manobo_fields.body_ph')) ?>"
                          style="font-size:.875rem;line-height:1.7;"><?= e($__mBodyVal) ?></textarea>
                <div class="form-text">
                    <?= e(t('manobo_fields.body_help')) ?>
                    <?php if ($__mTitleVal === '' && $__mBodyVal === ''): ?>
                    <?= e(t('manobo_fields.body_help_ai')) ?>
                    <?php else: ?>
                    <?= e(t('manobo_fields.body_help_manual')) ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Draft feedback: coverage, and the words the dictionary could
                 not reach — the worklist for whoever curates it next. -->
            <p class="form-text mt-2 mb-0">
                <i class="bi bi-info-circle me-1"></i><?= e(t('admin_manobo.draft_hint')) ?>
            </p>
            <div x-show="message" x-cloak
                 class="mt-2 rounded-3 p-2"
                 :style="ok ? 'background:#f0faf4;border:1px solid #c3e6cb;' : 'background:#fef3c7;border:1px solid #fcd34d;'">
                <p class="mb-0 fw-semibold" style="font-size:.78rem;" x-text="message"></p>
                <template x-if="missing.length">
                    <p class="mb-0 mt-1 text-muted" style="font-size:.74rem;">
                        <?= e(t('admin_manobo.draft_missing')) ?>
                        <span class="font-monospace" x-text="missing.join(', ')"></span>
                    </p>
                </template>
            </div>

        </div><!-- /text section -->

        <!-- ════════════════════════════════════════════════════════
             SECTION 2 — AUDIO VOICE RECORDING / UPLOAD
             ════════════════════════════════════════════════════════ -->
        <div x-data="manoboAudio()" x-init="initWidget()">

            <p class="fw-bold mb-2" style="font-size:.78rem;letter-spacing:.06em;text-transform:uppercase;color:var(--brand-primary);">
                <i class="bi bi-mic me-1"></i> <?= e(t('manobo_fields.audio_section')) ?>
            </p>

            <?php /* Stated here rather than assumed: this field is not a
                     nice-to-have extra, it is the entire Manobo half of the
                     voice reader. Nothing can substitute for it. */ ?>
            <p class="mb-3 rounded-3 p-2" style="font-size:.76rem;line-height:1.55;background:var(--surface-muted);color:var(--text-secondary);">
                <i class="bi bi-info-circle me-1"></i><?= e(t('manobo_fields.audio_reader_note')) ?>
            </p>

            <!-- ── Existing audio player (edit forms) ─────────────── -->
            <?php if ($__mAudioPath): ?>

            <div class="mb-3 rounded-3 p-3" style="background:#fef9c3;border:1px solid #d4c48a;" x-show="!deletePending">
                <p class="mb-2 fw-semibold" style="font-size:.8rem;color:#78350f;">
                    <i class="bi bi-volume-up-fill me-1"></i> Kasalukuyang Audio
                </p>
                <audio controls
                       class="w-100 mb-2"
                       src="<?= e(asset($__mAudioPath)) ?>"
                       style="height:40px;border-radius:8px;">
                    <?= e(t('manobo_fields.no_audio_support')) ?>
                </audio>
                <p class="mb-2 text-muted" style="font-size:.75rem;font-family:monospace;">
                    <?= e(basename($__mAudioPath)) ?>
                </p>
                <button type="button"
                        @click="deletePending = true"
                        class="btn btn-sm btn-outline-danger"
                        style="font-size:.77rem;">
                    <i class="bi bi-trash3 me-1"></i> <?= e(t('manobo_fields.remove_audio')) ?>
                </button>
            </div>

            <!-- Delete-pending confirmation banner -->
            <div class="mb-3 rounded-3 p-3"
                 style="background:#fee2e2;border:1px solid #fca5a5;display:none;"
                 x-show="deletePending"
                 x-cloak>
                <p class="mb-2 fw-semibold text-danger" style="font-size:.8rem;">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <?= e(t('manobo_fields.remove_notice_1')) ?>
                    <?= e(t('manobo_fields.remove_notice_2')) ?>
                </p>
                <button type="button"
                        @click="deletePending = false"
                        class="btn btn-sm btn-outline-secondary"
                        style="font-size:.77rem;">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Huwag alisin
                </button>
            </div>

            <!-- Submitted only when deletePending is true -->
            <input type="hidden" name="delete_audio_manobo" value="1"
                   x-bind:disabled="!deletePending">

            <?php endif; ?>

            <!-- Shared file input for both record-inject and direct upload -->
            <input type="file"
                   name="audio_manobo"
                   x-ref="audioInput"
                   accept="audio/*,.mp3,.wav,.m4a,.ogg,.webm"
                   style="display:none;"
                   @change="handleFileSelect($event)">

            <!-- ── Tab buttons ─────────────────────────────────────── -->
            <div class="d-flex gap-2 mb-3">
                <button type="button"
                        @click="switchTab('record')"
                        :class="tab === 'record'
                            ? 'btn-barangay'
                            : 'btn btn-outline-secondary'"
                        class="btn btn-sm d-inline-flex align-items-center gap-1"
                        style="font-size:.8rem;">
                    <i class="bi bi-mic-fill"></i> Mag-record
                </button>
                <button type="button"
                        @click="switchTab('upload')"
                        :class="tab === 'upload'
                            ? 'btn-barangay'
                            : 'btn btn-outline-secondary'"
                        class="btn btn-sm d-inline-flex align-items-center gap-1"
                        style="font-size:.8rem;">
                    <i class="bi bi-upload"></i> <?= e(t('manobo_fields.upload_file')) ?>
                </button>
            </div>

            <!-- ── RECORD TAB ──────────────────────────────────────── -->
            <div x-show="tab === 'record'">

                <!-- Idle -->
                <div x-show="recState === 'idle'"
                     class="text-center py-4 rounded-3"
                     style="border:2px dashed #c3e6cb;background:#f0faf4;">
                    <i class="bi bi-mic"
                       style="font-size:2.25rem;color:#a8d5b5;display:block;margin-bottom:.5rem;"></i>
                    <p class="mb-3 text-muted" style="font-size:.82rem;">
                        <?= e(t('manobo_fields.record_hint')) ?>
                    </p>
                    <button type="button"
                            @click="startRecord()"
                            class="btn-barangay d-inline-flex align-items-center gap-2"
                            style="font-size:.85rem;padding:9px 20px;">
                        <i class="bi bi-record-circle-fill"></i> <?= e(t('manobo_fields.start_recording')) ?>
                    </button>
                </div>

                <!-- Recording -->
                <div x-show="recState === 'recording'"
                     class="text-center py-4 rounded-3"
                     style="border:2px solid #dc3545;background:#fff5f5;">
                    <span class="d-inline-block mb-2"
                          style="width:14px;height:14px;border-radius:50%;background:#dc3545;
                                 animation:manobo-pulse 1s ease-in-out infinite;"></span>
                    <p class="mb-3 fw-semibold" style="font-size:.85rem;color:#dc3545;">
                        <?= e(t('manobo_fields.recording_now')) ?>
                    </p>
                    <button type="button"
                            @click="stopRecord()"
                            class="btn btn-danger d-inline-flex align-items-center gap-2"
                            style="font-size:.85rem;padding:9px 20px;">
                        <i class="bi bi-stop-circle-fill"></i> I-stop at I-save
                    </button>
                </div>

                <!-- Done -->
                <div x-show="recState === 'done'" x-cloak>
                    <div class="rounded-3 p-3 mb-3" style="background:#f0faf4;border:1px solid #c3e6cb;">
                        <p class="mb-2 fw-semibold" style="font-size:.8rem;color:var(--brand-primary);">
                            <i class="bi bi-check-circle-fill me-1"></i> Recording handa na
                        </p>
                        <audio controls
                               class="w-100"
                               :src="blobUrl"
                               style="height:40px;border-radius:8px;">
                        </audio>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button"
                                @click="clearRecord()"
                                class="btn btn-sm btn-outline-secondary"
                                style="font-size:.77rem;">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Ulit-ulitin
                        </button>
                    </div>
                    <p class="form-text mt-2">
                        <i class="bi bi-info-circle me-1"></i>
                        <?= e(t('manobo_fields.will_attach')) ?>
                    </p>
                </div>

            </div><!-- /record tab -->

            <!-- ── UPLOAD TAB ──────────────────────────────────────── -->
            <div x-show="tab === 'upload'" x-cloak>

                <!-- Drop zone (no file yet) -->
                <div x-show="!uploadPreviewUrl"
                     class="text-center py-4 rounded-3"
                     style="border:2px dashed #c3e6cb;background:#f0faf4;cursor:pointer;"
                     @click="$refs.audioInput.click()"
                     @dragover.prevent
                     @drop.prevent="handleDrop($event)">
                    <i class="bi bi-file-music"
                       style="font-size:2.25rem;color:#a8d5b5;display:block;margin-bottom:.5rem;"></i>
                    <p class="mb-1 fw-semibold" style="font-size:.85rem;color:var(--text-secondary);">
                        <?= e(t('manobo_fields.drop_hint')) ?>
                    </p>
                    <p class="mb-0 text-muted" style="font-size:.77rem;">
                        MP3, WAV, M4A, OGG, WebM &mdash; max 10 MB
                    </p>
                </div>

                <!-- File selected -->
                <div x-show="uploadPreviewUrl" x-cloak>
                    <div class="rounded-3 p-3 mb-3" style="background:#f0faf4;border:1px solid #c3e6cb;">
                        <p class="mb-2 fw-semibold" style="font-size:.8rem;color:var(--brand-primary);">
                            <i class="bi bi-file-music-fill me-1"></i>
                            <span x-text="uploadFileName" class="font-monospace"></span>
                        </p>
                        <audio controls
                               class="w-100"
                               :src="uploadPreviewUrl"
                               style="height:40px;border-radius:8px;">
                        </audio>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button"
                                @click="$refs.audioInput.click()"
                                class="btn btn-sm btn-outline-secondary"
                                style="font-size:.77rem;">
                            <i class="bi bi-pencil me-1"></i> <?= e(t('common.edit')) ?>
                        </button>
                        <button type="button"
                                @click="clearUpload()"
                                class="btn btn-sm btn-outline-danger"
                                style="font-size:.77rem;">
                            <i class="bi bi-trash3 me-1"></i> Alisin
                        </button>
                    </div>
                </div>

            </div><!-- /upload tab -->

        </div><!-- /x-data manoboAudio -->

    </div><!-- /admin-card-body -->
</div><!-- /admin-card -->

<style>
@keyframes manobo-pulse {
    0%, 100% { opacity: 1; transform: scale(1);    }
    50%       { opacity: .4; transform: scale(1.3); }
}
</style>

<script>
/* Dictionary-backed drafting for the two Manobo text fields.
   Reads whatever the editor has typed in the ordinary title/body fields of the
   same form, asks the server for a word-by-word draft, and drops it into the
   matching Manobo field for a human to correct. No AI, so it costs nothing. */
if (typeof window.__manoboDraftRegistered === 'undefined') {
    window.__manoboDraftRegistered = true;

    window.manoboDraft = function () {
        return {
            busy:    null,
            message: '',
            missing: [],
            ok:      false,

            /* The source text, mirrored beside the Manobo fields so whoever is
               translating can read the sentence without scrolling away from
               the box they are typing in. */
            refTitle: '',
            refBody:  '',
            showRef:  true,

            init() {
                this.refresh();

                /* Quill writes the body into a hidden textarea without firing
                   an input event, so there is nothing to listen to. Refreshing
                   whenever a Manobo field is focused covers the real workflow:
                   read the Filipino, click into the Manobo box, type. */
                this.$el.querySelectorAll('#manobo_title_input, #manobo_body_input')
                    .forEach(el => el.addEventListener('focus', () => this.refresh()));
            },

            refresh() {
                this.refTitle = this.sourceText('title');
                this.refBody  = this.sourceText('body');
            },

            /* The plain-language source for each Manobo field. Announcements use
               a Quill editor whose HTML lands in a hidden input, hence stripping. */
            sourceText(which) {
                const form = this.$el.closest('form');
                if (!form) return '';

                const names  = which === 'title' ? ['title'] : ['body', 'description'];
                for (const name of names) {
                    const field = form.querySelector('[name="' + name + '"]');
                    if (!field) continue;
                    const raw = (field.value || '').trim();
                    if (!raw) continue;
                    const stripped = raw.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                    if (stripped) return stripped;
                }
                return '';
            },

            targetField(which) {
                return document.getElementById(
                    which === 'title' ? 'manobo_title_input' : 'manobo_body_input'
                );
            },

            async draft(which) {
                if (this.busy) return;

                const source = this.sourceText(which);
                if (!source) {
                    this.ok      = false;
                    this.missing = [];
                    this.message = <?= json_encode(t('admin_manobo.draft_empty_source')) ?>;
                    return;
                }

                const target = this.targetField(which);
                if (!target) return;
                /* Never silently discard something a person already wrote. */
                if (target.value.trim() &&
                    !confirm(<?= json_encode(t('admin_manobo.draft_overwrite')) ?>)) {
                    return;
                }

                this.busy    = which;
                this.message = '';
                this.missing = [];

                try {
                    const base = (window.BarangGabay?.baseUrl || '').replace(/\/$/, '');
                    const body = new URLSearchParams({
                        text:       source,
                        csrf_token: window.BarangGabay?.csrfToken || '',
                    });
                    const res  = await fetch(base + '/api/manobo/draft', {
                        method:  'POST',
                        headers: {
                            'Content-Type':     'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: body,
                    });
                    const data = await res.json();

                    if (data.success) {
                        target.value = data.text;
                        target.dispatchEvent(new Event('input', { bubbles: true }));
                        this.ok      = true;
                        this.missing = data.missing || [];
                        this.message = <?= json_encode(t('admin_manobo.draft_coverage')) ?>
                            .replace(':matched', data.matched)
                            .replace(':total',   data.total);
                    } else {
                        this.ok      = false;
                        this.missing = [];
                        this.message = data.error || <?= json_encode(t('admin_manobo.draft_none')) ?>;
                    }
                } catch (_) {
                    this.ok      = false;
                    this.message = <?= json_encode(t('admin_manobo.draft_none')) ?>;
                } finally {
                    this.busy = null;
                }
            },
        };
    };
}

/* Prevent duplicate registration if widget is somehow included twice. */
if (typeof window.__manoboAudioRegistered === 'undefined') {
    window.__manoboAudioRegistered = true;

    window.manoboAudio = function () {
        return {
            tab: 'record',
            recState: 'idle',
            blob: null,
            blobUrl: null,
            uploadPreviewUrl: null,
            uploadFileName: '',
            mediaRecorder: null,
            chunks: [],
            deletePending: false,

            initWidget() {
                const form = this.$el.closest('form');
                if (form) {
                    form.addEventListener('submit', () => this.onFormSubmit());
                }
            },

            /* Before form submit: if on record tab with a finished recording,
               inject the blob into the shared file input so it's POSTed. */
            onFormSubmit() {
                if (this.tab === 'record' && this.recState === 'done' && this.blob) {
                    this.injectBlob();
                }
            },

            switchTab(newTab) {
                this.tab = newTab;
            },

            /* ── Record ─────────────────────────────────────────────── */

            async startRecord() {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    alert(<?= json_encode(t('manobo_fields.err_no_record')) ?>);
                    return;
                }
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    this.chunks = [];

                    const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
                        ? 'audio/webm;codecs=opus' : '';
                    this.mediaRecorder = new MediaRecorder(
                        stream, mimeType ? { mimeType } : {}
                    );

                    this.mediaRecorder.ondataavailable = e => {
                        if (e.data.size > 0) this.chunks.push(e.data);
                    };

                    this.mediaRecorder.onstop = () => {
                        stream.getTracks().forEach(t => t.stop());
                        const type = this.mediaRecorder.mimeType || 'audio/webm';
                        this.blob = new Blob(this.chunks, { type });
                        if (this.blobUrl) URL.revokeObjectURL(this.blobUrl);
                        this.blobUrl  = URL.createObjectURL(this.blob);
                        this.recState = 'done';
                    };

                    this.mediaRecorder.start();
                    this.recState = 'recording';
                } catch (err) {
                    alert(<?= json_encode(t('manobo_fields.err_no_mic')) ?>);
                }
            },

            stopRecord() {
                if (this.mediaRecorder && this.recState === 'recording') {
                    this.mediaRecorder.stop();
                }
            },

            clearRecord() {
                if (this.blobUrl) { URL.revokeObjectURL(this.blobUrl); this.blobUrl = null; }
                this.blob     = null;
                this.recState = 'idle';
                this.$refs.audioInput.value = '';
            },

            /* Inject the MediaRecorder blob into the hidden file input via DataTransfer
               so it is included in the multipart form POST. */
            injectBlob() {
                if (!this.blob) return;
                const baseType = (this.blob.type || 'audio/webm').split(';')[0];
                const extMap   = {
                    'audio/webm' : 'webm',
                    'audio/ogg'  : 'ogg',
                    'audio/mp4'  : 'mp4',
                    'audio/mpeg' : 'mp3',
                };
                const ext  = extMap[baseType] || 'webm';
                const file = new File([this.blob], `manobo-voice.${ext}`, { type: baseType });
                const dt   = new DataTransfer();
                dt.items.add(file);
                this.$refs.audioInput.files = dt.files;
            },

            /* ── Upload ─────────────────────────────────────────────── */

            handleFileSelect(e) {
                const file = e.target.files[0];
                if (!file) { this.uploadPreviewUrl = null; this.uploadFileName = ''; return; }
                if (file.size > 10 * 1024 * 1024) {
                    alert(<?= json_encode(t('manobo_fields.err_too_big')) ?>);
                    e.target.value       = '';
                    this.uploadPreviewUrl = null;
                    this.uploadFileName   = '';
                    return;
                }
                if (this.uploadPreviewUrl) URL.revokeObjectURL(this.uploadPreviewUrl);
                this.uploadPreviewUrl = URL.createObjectURL(file);
                this.uploadFileName   = file.name;
            },

            handleDrop(e) {
                const file = e.dataTransfer.files[0];
                if (!file) return;
                const dt = new DataTransfer();
                dt.items.add(file);
                this.$refs.audioInput.files = dt.files;
                this.$refs.audioInput.dispatchEvent(new Event('change'));
            },

            clearUpload() {
                if (this.uploadPreviewUrl) {
                    URL.revokeObjectURL(this.uploadPreviewUrl);
                    this.uploadPreviewUrl = null;
                }
                this.uploadFileName         = '';
                this.$refs.audioInput.value = '';
            },
        };
    };
}
</script>