<?php
/**
 * _import-panel.php — bring content in from a link or a pasted caption.
 *
 * Include inside an admin create/edit <form> after setting:
 *   $__ipType   (string)      'announcement' | 'event' | 'ordinance'
 *   $__ipSource (string|null) existing source_url, on edit forms
 *
 * Three ways in, in the order staff will reach for them:
 *
 *   Paste text  — copy the caption out of Facebook. No API, no key, nothing
 *                 to break. This is the tab that opens first, because it is
 *                 the one that works every day.
 *   Paste link  — we fetch the page and read its Open Graph tags. Good for
 *                 news sites, YouTube, published Google Docs. Facebook will
 *                 refuse, and says so specifically rather than failing.
 *   Attach      — keep the original post beside ours, embedded.
 *
 * Nothing here saves anything. Everything lands in the form fields for a staff
 * member to read and edit before they press Publish — an official barangay
 * channel must not republish whatever a link happened to contain.
 *
 * Only rendered for roles: staff, admin, superadmin.
 */
if (!in_array($_SESSION['role'] ?? '', ['staff', 'admin', 'superadmin'], true)) {
    return;
}

$__ipType   = (string) ($__ipType ?? 'announcement');
$__ipSource = trim((string) ($__ipSource ?? ''));
$__ipIsOrd  = $__ipType === 'ordinance';

// Body field name differs per form; the panel writes into whichever exists.
$__ipConfig = [
    'type'     => $__ipType,
    'isPdf'    => $__ipIsOrd,
    'strings'  => [
        'working'     => t('import.working'),
        'failed'      => t('import.err_network'),
        'no_url'      => t('import.err_no_url'),
        'no_text'     => t('import.err_no_text'),
        'cleaned'     => t('import.cleaned'),
        'imported'    => t('import.imported'),
        'file_ok'     => t('import.file_ok'),
        'tags_found'  => t('import.tags_found'),
    ],
];
?>

<div class="admin-card mb-4" x-data="importPanel()">

    <script type="application/json" data-import-config><?= json_encode(
        $__ipConfig,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    ) ?></script>

    <div class="admin-card-header">
        <h2 class="admin-card-title d-flex align-items-center gap-2 flex-wrap">
            <i class="bi bi-box-arrow-in-down" style="color:var(--brand-primary);"></i>
            <?= e(t('import.heading')) ?>
            <span class="badge rounded-pill ms-1"
                  style="background:var(--surface-muted);color:var(--text-secondary);font-size:.62rem;font-weight:700;padding:.28em .7em;">
                <?= e(t('common.optional')) ?>
            </span>
        </h2>
        <p class="text-muted mb-0 mt-1" style="font-size:.78rem;line-height:1.5;">
            <?= e(t('import.intro')) ?>
        </p>
    </div>

    <div class="admin-card-body">

        <?php /*
             * Where a fetched file lands. The panel stores the cover image or
             * the PDF immediately (so staff can see it worked) and drops the
             * resulting path here; the controller re-validates it against the
             * filenames this app itself writes before using it. Empty unless
             * something was actually imported.
             */ ?>
        <?php if ($__ipIsOrd): ?>
        <input type="hidden" name="imported_file" value="">
        <?php else: ?>
        <input type="hidden" name="imported_cover" value="">
        <?php endif; ?>

        <!-- Tabs. Paste-text first: it is the one that always works. -->
        <div class="d-flex gap-2 mb-3 flex-wrap">
            <?php foreach ([
                ['paste', 'bi-clipboard', t('import.tab_paste')],
                ['link',  'bi-link-45deg', t('import.tab_link')],
                ['attach','bi-paperclip',  t('import.tab_attach')],
            ] as [$__tab, $__icon, $__label]): ?>
            <button type="button"
                    @click="tab = '<?= $__tab ?>'"
                    :class="tab === '<?= $__tab ?>' ? 'btn-barangay' : 'btn btn-outline-secondary'"
                    class="btn btn-sm d-inline-flex align-items-center gap-1"
                    style="font-size:.8rem;">
                <i class="bi <?= $__icon ?>"></i> <?= e($__label) ?>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- ══ Tier 3: paste the caption ══════════════════════════════════ -->
        <div x-show="tab === 'paste'">
            <label class="form-label fw-semibold" style="font-size:.845rem;">
                <?= e(t('import.paste_label')) ?>
            </label>
            <textarea x-model="pasted"
                      class="form-control"
                      rows="6"
                      placeholder="<?= e(t('import.paste_ph')) ?>"
                      style="font-size:.875rem;line-height:1.6;"></textarea>
            <div class="form-text"><?= e(t('import.paste_help')) ?></div>

            <button type="button" @click="cleanPaste()" :disabled="busy"
                    class="btn btn-sm btn-barangay mt-2">
                <i class="bi bi-magic me-1"></i>
                <span x-text="busy ? cfg.strings.working : <?= e(json_encode(t('import.paste_btn'))) ?>"></span>
            </button>
        </div>

        <!-- ══ Tier 1: fetch a link ═══════════════════════════════════════ -->
        <div x-show="tab === 'link'" x-cloak>
            <label class="form-label fw-semibold" style="font-size:.845rem;">
                <?= e($__ipIsOrd ? t('import.link_label_pdf') : t('import.link_label')) ?>
            </label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                <input type="url" x-model="url" class="form-control"
                       placeholder="https://..."
                       style="font-size:.875rem;">
                <button type="button" class="btn btn-barangay" @click="fetchLink()" :disabled="busy">
                    <span x-text="busy ? cfg.strings.working : <?= e(json_encode(t('import.link_btn'))) ?>"></span>
                </button>
            </div>
            <div class="form-text">
                <?= e($__ipIsOrd ? t('import.link_help_pdf') : t('import.link_help')) ?>
            </div>
        </div>

        <!-- ══ Tier 2: attach the original ════════════════════════════════ -->
        <div x-show="tab === 'attach'" x-cloak>
            <label class="form-label fw-semibold" style="font-size:.845rem;">
                <?= e(t('import.attach_label')) ?>
            </label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-paperclip"></i></span>
                <?php /* The one field on this panel that is actually submitted
                         with the form — everything else only fills in other
                         fields. See SourceLink::store(). */ ?>
                <input type="url"
                       name="source_url"
                       x-model="sourceUrl"
                       class="form-control"
                       placeholder="https://www.facebook.com/..."
                       maxlength="500"
                       style="font-size:.875rem;">
            </div>
            <div class="form-text"><?= e(t('import.attach_help')) ?></div>

            <p class="mt-2 mb-0" style="font-size:.76rem;color:var(--text-secondary);" x-show="sourceUrl" x-cloak>
                <i class="bi bi-info-circle me-1"></i><?= e(t('import.attach_note')) ?>
            </p>
        </div>

        <!-- ══ Result ═════════════════════════════════════════════════════ -->
        <div x-show="message" x-cloak class="mt-3 rounded-3 p-2"
             :style="ok ? 'background:#dcfce7;border:1px solid #86efac;' : 'background:#fef3c7;border:1px solid #fcd34d;'">
            <p class="mb-0 fw-semibold" style="font-size:.79rem;" x-text="message"></p>

            <?php /* A refusal has to say which refusal. "Could not import"
                     sends somebody off to retype a whole post without knowing
                     that Facebook will never work this way and that the tab
                     beside them will. */ ?>
            <p class="mb-0 mt-1" style="font-size:.75rem;color:var(--text-secondary);"
               x-show="hint" x-cloak x-text="hint"></p>

            <div class="mt-2 d-flex gap-2 flex-wrap" x-show="suggest === 'embed_or_paste'" x-cloak>
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        style="font-size:.74rem;"
                        @click="sourceUrl = url; tab = 'attach'">
                    <i class="bi bi-paperclip me-1"></i><?= e(t('import.suggest_attach')) ?>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        style="font-size:.74rem;" @click="tab = 'paste'">
                    <i class="bi bi-clipboard me-1"></i><?= e(t('import.suggest_paste')) ?>
                </button>
            </div>

            <template x-if="tags.length">
                <p class="mb-0 mt-2" style="font-size:.75rem;color:var(--text-secondary);">
                    <i class="bi bi-tags me-1"></i>
                    <span x-text="cfg.strings.tags_found"></span>
                    <span class="font-monospace" x-text="tags.join(', ')"></span>
                </p>
            </template>
        </div>

        <p class="form-text mt-3 mb-0">
            <i class="bi bi-shield-check me-1"></i><?= e(t('import.review_note')) ?>
        </p>
    </div>
</div>

<script>
/* One component for all three tiers. It only ever WRITES INTO the form's own
   fields — it never submits anything, so whatever it produces is a draft the
   staff member edits and saves themselves. */
if (typeof window.importPanel === 'undefined') {
    window.importPanel = function () {
        return {
            tab:       'paste',
            url:       '',
            sourceUrl: <?= json_encode($__ipSource) ?>,
            pasted:    '',
            busy:      false,
            ok:        false,
            message:   '',
            hint:      '',
            suggest:   null,
            tags:      [],
            cfg:       { strings: {} },

            init() {
                const raw = this.$el.querySelector('script[data-import-config]');
                if (raw) {
                    try { this.cfg = JSON.parse(raw.textContent || '{}'); } catch (e) {}
                }
                this.cfg.strings = this.cfg.strings || {};
            },

            _form() { return this.$el.closest('form'); },

            /* Write a value into a form field by name, if that field exists on
               this particular form. The three post types do not share a field
               list, so every write is conditional rather than assumed. */
            _set(names, value) {
                if (!value) { return false; }
                const form = this._form();
                if (!form) { return false; }

                for (const name of names) {
                    const field = form.querySelector('[name="' + name + '"]');
                    if (!field) { continue; }
                    field.value = value;
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    return true;
                }
                return false;
            },

            /* Point the form's source-language radio at whatever language the
               imported text is actually in.

               This is the half of the import that was missing, and it was not
               cosmetic. A post is translated INTO the languages it was not
               written in, so an English caption left on the Filipino setting
               was "translated" English→English: it never got a Filipino
               version, and a resident tapping FIL saw English under a Filipino
               badge. Nothing happens when the server was not sure of the
               language, or when staff have already chosen one themselves. */
            _setSourceLang(lang) {
                if (lang !== 'fil' && lang !== 'en') { return; }

                const form = this._form();
                if (!form) { return; }

                const chosen = form.querySelector('[name="source_lang"]:checked');
                if (chosen && chosen.value !== 'auto') { return; }

                const radio = form.querySelector('[name="source_lang"][value="' + lang + '"]');
                if (!radio) { return; }

                radio.checked = true;
                radio.dispatchEvent(new Event('change', { bubbles: true }));
            },

            /* The announcement body lives in Quill, whose editor is the source
               of truth — writing only to the hidden textarea would be silently
               overwritten on the next keystroke. */
            _setBody(html, plain) {
                if (window.quillEditor && html) {
                    window.quillEditor.root.innerHTML = html;
                    window.quillEditor.update();
                    this._set(['body'], html);
                    return true;
                }
                return this._set(['body', 'description'], plain || html);
            },

            async _post(path, body) {
                const base = (window.BarangGabay && window.BarangGabay.baseUrl || '').replace(/\/$/, '');
                const res  = await fetch(base + path, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body:    new URLSearchParams({
                        ...body,
                        csrf_token: (window.BarangGabay && window.BarangGabay.csrfToken) || '',
                    }),
                });
                return res.json();
            },

            _reset() {
                this.message = ''; this.hint = ''; this.suggest = null; this.tags = [];
            },

            // ── Tier 3 ────────────────────────────────────────────────────
            async cleanPaste() {
                if (this.busy) { return; }
                this._reset();

                if (!this.pasted.trim()) { this.ok = false; this.message = this.cfg.strings.no_text; return; }

                this.busy = true;
                try {
                    const data = await this._post('/api/admin/import/paste', { text: this.pasted });
                    if (!data.success) { this.ok = false; this.message = data.error; return; }

                    this._setBody(data.body, data.text);
                    this._setSourceLang(data.source_lang);
                    this.tags = data.tags || [];
                    this.ok      = true;
                    this.message = this.cfg.strings.cleaned;
                    this.hint    = (data.removed || []).join(' · ');
                } catch (e) {
                    this.ok = false; this.message = this.cfg.strings.failed;
                } finally {
                    this.busy = false;
                }
            },

            // ── Tier 1 ────────────────────────────────────────────────────
            async fetchLink() {
                if (this.busy) { return; }
                this._reset();

                if (!this.url.trim()) { this.ok = false; this.message = this.cfg.strings.no_url; return; }

                this.busy = true;
                try {
                    // An ordinance wants the PDF itself, not the page about it.
                    const path = this.cfg.isPdf ? '/api/admin/import/file' : '/api/admin/import/link';
                    const data = await this._post(path, { url: this.url });

                    if (!data.success) {
                        this.ok      = false;
                        this.message = data.error;
                        this.suggest = data.suggest || null;
                        return;
                    }

                    if (this.cfg.isPdf) {
                        // The file is already stored; the form carries its path
                        // so the controller does not re-upload it.
                        this._set(['imported_file'], data.path);
                        this.ok      = true;
                        this.message = this.cfg.strings.file_ok;
                        this.sourceUrl = data.source || this.url;
                        return;
                    }

                    this._set(['title'], data.title);
                    this._setBody(data.body, data.text);
                    this._setSourceLang(data.source_lang);
                    this._set(['imported_cover'], data.cover);
                    // Attribution comes along automatically — the point of
                    // importing is that somebody else wrote it.
                    this.sourceUrl = data.source || this.url;

                    this.ok      = true;
                    this.message = this.cfg.strings.imported;
                    this.hint    = data.note || data.site || '';
                } catch (e) {
                    this.ok = false; this.message = this.cfg.strings.failed;
                } finally {
                    this.busy = false;
                }
            },
        };
    };
}
</script>
