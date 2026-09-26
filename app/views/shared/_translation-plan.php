<?php
/**
 * "Translation plan" — what Save will do, before it is pressed.
 *
 * Everything this panel reports was already knowable from the text in the
 * form, and none of it was said. A staff member wrote a long ordinance,
 * saved it, and found no English version; they re-saved it, and found no
 * English version again. Nothing anywhere said the post was longer than the
 * free provider accepts and that saving it a third time would not help.
 *
 * The answers come from the server on a debounce — see TranslationPlanner
 * for why they cannot honestly be computed here. The panel is additive:
 * if the request fails or JavaScript is off, the form behaves exactly as it
 * did before and the save is unaffected.
 *
 * Expects:
 *   $tpTitleField  string  name of the title input      (default 'title')
 *   $tpBodyField   string  name of the body field       (default 'body')
 *   $tpExisting    string  the row's current source_lang, '' when creating
 */

$tpTitleField = $tpTitleField ?? 'title';
$tpBodyField  = $tpBodyField  ?? 'body';
$tpExisting   = $tpExisting   ?? 'fil';
?>
<div class="admin-card mb-4 translation-plan"
     x-data="translationPlan(<?= e(json_encode([
         'url'      => route('admin/translation-plan'),
         'csrf'     => csrf_token(),
         'title'    => $tpTitleField,
         'body'     => $tpBodyField,
         'existing' => $tpExisting,
     ])) ?>)"
     x-init="init()">
    <div class="admin-card-body">

        <p class="fw-bold mb-1" style="font-size:.845rem;">
            <i class="bi bi-list-check me-1" style="color:var(--brand-primary);"></i>
            <?= e(t('translation_plan.title')) ?>
        </p>
        <p class="text-muted mb-3" style="font-size:.78rem;line-height:1.6;">
            <?= e(t('translation_plan.help')) ?>
        </p>

        <template x-if="!plan">
            <p class="text-muted mb-0" style="font-size:.8rem;">
                <?= e(t('translation_plan.waiting')) ?>
            </p>
        </template>

        <template x-if="plan">
            <div>
                <?php /* ── Source language ───────────────────────────── */ ?>
                <div class="tp-row">
                    <span class="tp-row__label"><?= e(t('translation_plan.written_in')) ?></span>
                    <span class="tp-row__value" x-text="langName(plan.source.lang)"></span>
                    <span class="tp-how" x-text="howText(plan.source.decidedBy)"></span>
                </div>

                <?php /* The one warning worth interrupting for: the chosen
                         source disagrees with what the text looks like.
                         Their choice still wins — but an English caption
                         filed as Filipino is translated into the language it
                         is already in, and nothing downstream can notice. */ ?>
                <div class="tp-warn" x-show="plan.source.overridden" x-cloak>
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <span x-text="mismatchText()"></span>
                </div>

                <?php /* ── Length against the real cap ───────────────── */ ?>
                <div class="tp-row">
                    <span class="tp-row__label"><?= e(t('translation_plan.length')) ?></span>
                    <span class="tp-row__value">
                        <span x-text="plan.length.characters"></span>
                        <?= e(t('translation_plan.characters')) ?>
                    </span>
                    <span class="tp-how">
                        <span x-text="plan.length.chunks"></span>/<span x-text="plan.length.maxChunks"></span>
                        <?= e(t('translation_plan.requests')) ?>
                    </span>
                </div>

                <div class="tp-warn" x-show="plan.length.overCap" x-cloak>
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <?= e(t('translation_plan.too_long_warning')) ?>
                </div>

                <?php /* ── What each language will get ───────────────── */ ?>
                <div class="tp-langs">
                    <template x-for="l in plan.languages" :key="l.lang">
                        <div class="tp-lang" :class="'tp-lang--' + l.will">
                            <span class="lang-chip" :class="chipClass(l.will)" x-text="shortCode(l.lang)"></span>
                            <div class="tp-lang__body">
                                <p class="tp-lang__will" x-text="willText(l.will)"></p>
                                <p class="tp-lang__why" x-show="l.will !== 'generate' && l.will !== 'source'"
                                   x-cloak x-text="reasonText(l.reason)"></p>
                                <p class="tp-lang__fix" x-show="l.fix" x-cloak x-text="fixText(l.fix)"></p>
                                <p class="tp-lang__env" x-show="l.envKey" x-cloak>
                                    <code x-text="l.envKey"></code>
                                </p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>
</div>

<script>
/*
 * The panel's behaviour. Registered globally rather than inline so the same
 * component serves all six create/edit forms.
 *
 * Every label comes from PHP below, so nothing here needs translating and
 * a new reason code cannot render as a bare key.
 */
function translationPlan(cfg) {
    return {
        plan:  null,
        timer: null,

        // Every string this component can display, from lang/.
        L: <?= json_encode([
            /* available_locales() is the list the FIL / EN / MN buttons are
               built from, so the panel names a language exactly as the
               resident-facing switch does. t() would have returned the key
               itself for a missing string, printing "lang.manobo" on the
               page rather than falling back to anything. */
            'langs' => available_locales(),
            'short' => array_combine(
                array_keys(available_locales()),
                array_map('locale_short_code', array_keys(available_locales()))
            ),
            'how' => [
                'chosen'   => t('translation_plan.how_chosen'),
                'detected' => t('translation_plan.how_detected'),
                'default'  => t('translation_plan.how_default'),
            ],
            'will' => [
                'source'   => t('translation_plan.will_source'),
                'generate' => t('translation_plan.will_generate'),
                'maybe'    => t('translation_plan.will_maybe'),
                'skip'     => t('translation_plan.will_skip'),
            ],
            'mismatch' => t('translation_plan.mismatch'),
            'reasons'  => array_combine(
                array_map('strtolower', \App\Services\TranslationOutcome::all()),
                array_map(
                    static fn (string $c): string => t(\App\Services\TranslationOutcome::messageKey($c)),
                    \App\Services\TranslationOutcome::all()
                )
            ),
            'fixes' => array_combine(
                array_map(
                    static fn (string $c): string => \App\Services\TranslationOutcome::fixKey($c),
                    \App\Services\TranslationOutcome::all()
                ),
                array_map(
                    static fn (string $c): string => t(\App\Services\TranslationOutcome::fixKey($c)),
                    \App\Services\TranslationOutcome::all()
                )
            ),
        ], JSON_HEX_TAG | JSON_HEX_AMP) ?>,

        init() {
            this.watch();
            this.refresh();
        },

        /* Redraw on a pause in typing, not on every keystroke: each refresh
           chunks the whole body on the server. 600ms is long enough that a
           sentence being typed does not fire once per letter. */
        schedule() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.refresh(), 600);
        },

        watch() {
            const title = document.querySelector('[name="' + cfg.title + '"]');
            if (title) { title.addEventListener('input', () => this.schedule()); }

            const body = document.querySelector('[name="' + cfg.body + '"]');
            if (body) { body.addEventListener('input', () => this.schedule()); }

            /* Quill writes into a hidden input on submit, not as you type,
               so the visible editor is watched directly when present. */
            const quill = document.querySelector('.ql-editor');
            if (quill) { quill.addEventListener('input', () => this.schedule()); }

            document.querySelectorAll('[name="source_lang"]').forEach((r) => {
                r.addEventListener('change', () => this.refresh());
            });
        },

        readBody() {
            const quill = document.querySelector('.ql-editor');
            if (quill) { return quill.innerText || ''; }

            const el = document.querySelector('[name="' + cfg.body + '"]');
            return el ? el.value : '';
        },

        async refresh() {
            const titleEl = document.querySelector('[name="' + cfg.title + '"]');
            const checked = document.querySelector('[name="source_lang"]:checked');

            const body = new URLSearchParams({
                csrf_token:           cfg.csrf,
                title:                titleEl ? titleEl.value : '',
                body:                 this.readBody(),
                source_lang:          checked ? checked.value : 'auto',
                existing_source_lang: cfg.existing,
            });

            try {
                const res = await fetch(cfg.url, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body:    body.toString(),
                });
                if (!res.ok) { return; }
                this.plan = await res.json();
            } catch (_) {
                /* The panel is advice, not a gate. If it cannot be reached
                   the form still saves exactly as before — failing loudly
                   here would block work over a cosmetic feature. */
            }
        },

        langName(c)  { return this.L.langs[c]  || c; },
        shortCode(c) { return this.L.short[c]  || c.toUpperCase(); },
        howText(k)   { return this.L.how[k]    || ''; },
        willText(k)  { return this.L.will[k]   || ''; },
        reasonText(code) { return this.L.reasons[String(code).toLowerCase()] || ''; },
        fixText(key)     { return this.L.fixes[key] || ''; },

        mismatchText() {
            if (!this.plan || !this.plan.source.detected) { return ''; }
            return this.L.mismatch
                .replace(':chosen',   this.langName(this.plan.source.lang))
                .replace(':detected', this.langName(this.plan.source.detected));
        },

        chipClass(will) {
            if (will === 'skip')     { return 'lang-chip--missing'; }
            if (will === 'maybe')    { return 'lang-chip--machine'; }
            if (will === 'generate') { return 'lang-chip--manual'; }
            return 'lang-chip--source';
        },
    };
}
</script>
