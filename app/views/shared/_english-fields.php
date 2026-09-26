<?php
/**
 * _english-fields.php — manual English translation for a post.
 *
 * Include inside an admin <form> after setting:
 *   $__eTitleVal  (string) — existing title_en, '' for new posts
 *   $__eBodyVal   (string) — existing body_en / description_en, '' for new
 *   $__eBodyName  (string) — POST field name: 'body_en' or 'description_en'
 *   $__eBodyLabel (string) — human label for the body field
 *
 * Why this exists next to the AI translator: staff write posts in Filipino,
 * so a resident who picks EN has nothing to read unless a translation exists.
 * The AI can produce one automatically, but only when the Anthropic account
 * has credits — these fields are the path that always works, and they take
 * priority over the AI translation when filled.
 *
 * Only rendered for roles: staff, admin, superadmin.
 */
if (!in_array($_SESSION['role'] ?? '', ['staff', 'admin', 'superadmin'], true)) {
    return;
}

$__eTitleVal  = $__eTitleVal  ?? '';
$__eBodyVal   = $__eBodyVal   ?? '';
$__eBodyName  = $__eBodyName  ?? 'body_en';
$__eBodyLabel = $__eBodyLabel ?? t('manobo_fields.label_body');

// Which language the staff member is writing in. Everything else on this
// card is "the other language", so the heading and help text follow it.
//
// New posts start on 'auto'. Filipino used to be the default, and it was
// wrong often enough to matter: a post written or imported in English was
// filed as Filipino, so it was "translated" English→English, never got a
// Filipino version, and residents who tapped FIL were shown English under a
// Filipino badge with nothing to explain it. Defaulting to a guess the server
// checks against the actual text is better than defaulting to an assumption
// nobody checks. An explicit choice here always wins over the guess.
$__eSourceLang = $__eSourceLang ?? 'auto';
?>

<!-- ── Which language am I writing in? ────────────────────────────────────
     This decides the whole translation direction, so it sits above the
     translation panels rather than inside one of them. -->
<div class="admin-card mb-4" x-data="{ src: '<?= e($__eSourceLang) ?>' }">
    <div class="admin-card-body">
        <p class="fw-bold mb-1" style="font-size:.845rem;">
            <i class="bi bi-pencil-square me-1" style="color:var(--brand-primary);"></i><?= e(t('english_fields.source_lang_title')) ?>
        </p>
        <p class="text-muted mb-3" style="font-size:.78rem;line-height:1.6;">
            <?= e(t('english_fields.source_lang_help')) ?>
        </p>

        <div class="d-flex flex-wrap gap-3">
            <label class="d-inline-flex align-items-center gap-2" style="cursor:pointer;font-size:.845rem;">
                <input type="radio" name="source_lang" value="auto" x-model="src" class="form-check-input mt-0">
                <span><?= e(t('english_fields.source_auto')) ?></span>
            </label>
            <label class="d-inline-flex align-items-center gap-2" style="cursor:pointer;font-size:.845rem;">
                <input type="radio" name="source_lang" value="fil" x-model="src" class="form-check-input mt-0">
                <span><?= e(t('english_fields.source_fil')) ?></span>
            </label>
            <label class="d-inline-flex align-items-center gap-2" style="cursor:pointer;font-size:.845rem;">
                <input type="radio" name="source_lang" value="en" x-model="src" class="form-check-input mt-0">
                <span><?= e(t('english_fields.source_en')) ?></span>
            </label>
        </div>

        <!-- The panel below always targets the OTHER language, so its heading
             has to follow this choice live rather than on the next page load.
             On 'auto' it cannot name the language yet — the text decides that
             on save — so it says so rather than guessing in the browser. -->
        <p class="form-text mt-3 mb-0">
            <i class="bi bi-arrow-down me-1"></i>
            <span x-text="src === 'auto'
                ? <?= e(json_encode(t('english_fields.other_panel_auto'))) ?>
                : (src === 'fil'
                    ? <?= e(json_encode(t('english_fields.other_panel_en'))) ?>
                    : <?= e(json_encode(t('english_fields.other_panel_fil'))) ?>)"></span>
        </p>
    </div>
</div>

<div class="admin-card mb-4">

    <div class="admin-card-header">
        <h2 class="admin-card-title d-flex align-items-center gap-2 flex-wrap">
            <i class="bi bi-translate" style="color:var(--brand-primary);"></i>
            <?= e(t('english_fields.heading')) ?>
            <span class="badge rounded-pill ms-1"
                  style="background:#1652f0;color:#fff;font-size:.62rem;font-weight:700;padding:.28em .7em;letter-spacing:.03em;">
                <?= e(t('manobo_fields.optional_badge')) ?>
            </span>
        </h2>
        <p class="text-muted mb-0 mt-1" style="font-size:.78rem;line-height:1.5;">
            <?= e(t('english_fields.intro')) ?>
        </p>
    </div>

    <div class="admin-card-body" x-data="englishDraft()">

        <div class="mb-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                <label for="english_title_input" class="form-label fw-semibold mb-0" style="font-size:.845rem;">
                    <?= e(t('english_fields.title_label')) ?>
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
                   id="english_title_input"
                   name="title_en"
                   class="form-control"
                   value="<?= e($__eTitleVal) ?>"
                   maxlength="500"
                   placeholder="<?= e(t('english_fields.title_ph')) ?>"
                   style="font-size:.875rem;">
            <div class="form-text"><?= e(t('english_fields.title_help')) ?></div>
        </div>

        <div>
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                <label for="english_body_input" class="form-label fw-semibold mb-0" style="font-size:.845rem;">
                    <?= e(t('english_fields.body_label', ['label' => $__eBodyLabel])) ?>
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
            <textarea id="english_body_input"
                      name="<?= e($__eBodyName) ?>"
                      class="form-control"
                      rows="6"
                      placeholder="<?= e(t('english_fields.body_ph')) ?>"
                      style="font-size:.875rem;line-height:1.7;"><?= e($__eBodyVal) ?></textarea>
            <div class="form-text">
                <?= e(t('english_fields.body_help')) ?>
                <?php if ($__eTitleVal === '' && $__eBodyVal === ''): ?>
                <?= e(t('english_fields.body_help_ai')) ?>
                <?php else: ?>
                <?= e(t('english_fields.body_help_manual')) ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Draft feedback: coverage, and the words the dictionary could not
             reach — the worklist for whoever curates it next. -->
        <p class="form-text mt-2 mb-0">
            <i class="bi bi-info-circle me-1"></i><?= e(t('english_fields.draft_hint')) ?>
        </p>
        <div x-show="message" x-cloak
             class="mt-2 rounded-3 p-2"
             :style="ok ? 'background:#eef4ff;border:1px solid #c3d4f5;' : 'background:#fef3c7;border:1px solid #fcd34d;'">
            <p class="mb-0 fw-semibold" style="font-size:.78rem;" x-text="message"></p>
            <template x-if="missing.length">
                <p class="mb-0 mt-1 text-muted" style="font-size:.74rem;">
                    <?= e(t('admin_manobo.draft_missing')) ?>
                    <span class="font-monospace" x-text="missing.join(', ')"></span>
                </p>
            </template>
        </div>

    </div>
</div>

<script>
/*
 * Fills the English fields from the community dictionaries, word by word.
 *
 * Same shape as manoboDraft() in _manobo-fields.php, pointed at a different
 * endpoint. Kept as its own component rather than parameterising that one:
 * the two panels are separate cards with separate target fields, and sharing
 * state between them would let a draft land in the wrong box.
 *
 * NOTE: the strings below use bare json_encode(), NOT e(json_encode(...)).
 * A <script> element does not HTML-decode its contents, so an escaped quote
 * would reach the JS parser as "&quot;" and kill the whole block.
 */
if (typeof window.englishDraft === 'undefined') {
    window.englishDraft = function () {
        return {
            busy:    null,
            message: '',
            missing: [],
            ok:      false,

            /* The Filipino source for each English field. Announcements use a
               Quill editor whose HTML lands in a hidden input, hence stripping. */
            sourceText(which) {
                const form = this.$el.closest('form');
                if (!form) return '';

                const names = which === 'title' ? ['title'] : ['body', 'description'];
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
                    which === 'title' ? 'english_title_input' : 'english_body_input'
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
                    const res  = await fetch(base + '/api/english/draft', {
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
</script>
