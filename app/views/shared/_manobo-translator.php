<?php
/**
 * Manobo Translator widget — reusable partial.
 *
 * Set these variables before requiring this file:
 *   $__mText   (string)      — content text, in the language it was written in
 *   $__mType   (string)      — 'announcement' | 'event' | 'ordinance'
 *   $__mId     (int)         — record ID
 *   $__mManual (string|null) — the Manobo already stored on the row
 *                              (title_manobo + body/description_manobo)
 *   $__mIsAuto (bool)        — whether that stored Manobo came from a machine
 *   $__mAudio  (string|null) — path to a community voice recording
 *
 * Where the Manobo comes from, in order:
 *
 *   1. The row's own Manobo columns. A staff member or a Manobo speaker typed
 *      these in the admin form, and they are the most trustworthy text this
 *      system has. Shown immediately; no API call, no credits, nothing to fail.
 *   2. A cached AI translation from a previous request.
 *   3. A fresh AI call, but only when neither of the above exists AND a key is
 *      configured.
 *
 * Order 1 is new, and it is the fix for the widget reporting "Manobo
 * translation is not available right now" on posts that had perfectly good
 * Manobo sitting in the database. The widget only ever consulted the
 * translation_logs cache, so a hand-written translation was invisible to it
 * and it fell through to an API call that cannot succeed without credits.
 */

$__mId       = (int) ($__mId   ?? 0);
$__mType     = (string) ($__mType ?? '');
$__mAudio    = $__mAudio ?? null;
$__mIsAuto   = (bool) ($__mIsAuto ?? false);
$__mSafeText = e(mb_substr(strip_tags((string) ($__mText ?? '')), 0, 4000));
$__apiKeySet = !empty(env('ANTHROPIC_API_KEY', ''));

// 1. The Manobo stored on the row itself. Body columns may hold Quill HTML
// or text whose tags were stripped bare; turn block ends into line breaks
// and put back spaces lost between sentences ("Sur.Intawa", "ngayonLahat").
$__manualText = \App\Services\SpokenText::repairJoins(
    \App\Services\SpokenText::plain((string) ($__mManual ?? ''))
);

// 2. Otherwise, a previously cached AI translation.
$__cachedText = '';
if ($__manualText === '') {
    $__cachedRow  = \App\Models\TranslationLog::findCached($__mType, $__mId);
    $__cachedText = $__cachedRow ? $__cachedRow['translated_text'] : '';
    // Filter out old fallback/error strings that were accidentally cached
    if (\str_contains($__cachedText, 'hindi available') ||
        \str_contains($__cachedText, 'translation service')) {
        $__cachedText = '';
    }
}

$__shownText  = $__manualText !== '' ? $__manualText : \App\Services\SpokenText::repairJoins($__cachedText);

// What the Listen button plays: approved dataset recordings (Manobo, plus
// Bisaya recordings for Bisaya fallback words), per the admin's Voice
// Fallback setting. Never the device's Filipino voice unless that fallback
// is switched on.
$__mFallback = \App\Services\VoiceResolver::fallbackMode();
$__safeCached = e($__shownText);

// A hand-written translation is not a machine's guess and must not wear the
// "cached AI translation" label — the difference is the whole point.
$__manualHuman = $__manualText !== '' && !$__mIsAuto;
?>

<style>
    /* Per-word source tags on an offline gloss (see the "Translated text"
       block below). Both must stay readable on their own — a resident is
       meant to actually read a Bisaya fallback or an untranslated word, not
       just notice it's flagged — so neither uses opacity/dimming. Colours
       come from tokens.css, already verified >=4.5:1 on both themes. */
    .mn-seg-bisaya {
        border-bottom: 2px dotted var(--status-warning);
        cursor: help;
    }
    .mn-seg-missing {
        color: var(--text-muted);
        border-bottom: 1px dotted var(--border-strong);
        cursor: help;
    }
</style>

<div class="mt-6"
     x-data="manoboTranslator()"
     data-mtype="<?= e($__mType) ?>"
     data-mid="<?= $__mId ?>"
     data-mtext="<?= $__mSafeText ?>"
     data-has-key="<?= $__apiKeySet ? '1' : '0' ?>"
     data-manual="<?= $__manualHuman ? '1' : '0' ?>"
     data-cached="<?= $__safeCached ?>"
     data-fallback="<?= e($__mFallback) ?>">

    <div class="post-card post-lang-card">

        <!-- ── Header bar ─────────────────────────────────────────── -->
        <div class="post-card-head post-lang-head">

            <div class="flex items-center gap-2.5">
                <span class="post-lang-mark" aria-hidden="true">🌿</span>
                <?php // Header ink now comes from the theme tokens, so it follows the
                      // light/dark toggle like every other card on the page. ?>
                <div>
                    <p class="post-lang-title"><?= e(t('manobo_widget.title')) ?></p>
                    <p class="post-lang-sub"><?= e(t('manobo_widget.subtitle')) ?></p>
                </div>
            </div>

            <!-- Translate button — always visible until a translation is loaded -->
            <button type="button"
                    @click="fetchTranslation()"
                    x-show="!translation && !loading"
                    class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-blue-700 px-4 py-2
                           text-sm font-bold text-white transition hover:bg-blue-800">
                🌐 <?= e(t('manobo_widget.translate')) ?>
            </button>

            <!-- Success chip -->
            <span x-show="!!translation && !loading"
                  class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-blue-800 px-4 py-2
                         text-sm font-semibold text-white opacity-80">
                ✅ <?= e(t('manobo_widget.translated')) ?>
            </span>

            <!-- Loading chip -->
            <span x-show="loading"
                  class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-blue-700 px-4 py-2
                         text-sm font-semibold text-white opacity-75">
                ⏳ <?= e(t('manobo_widget.translating_short')) ?>
            </span>
        </div>

        <?php if (!empty($__mAudio)): ?>
        <?php /*
             * The recording used to be played here, by a second <audio
             * controls> element with its own play button and its own timeline.
             * That meant two players on one page disagreeing about what was
             * playing, and the community recording being the only Manobo audio
             * a resident could reach.
             *
             * It now belongs to the voice reader at the top of the post, as the
             * MN option of the one player — where it is still the preferred
             * source, and where it sits beside the English and Filipino tracks
             * instead of below them in a separate card. This line is all that
             * remains: a pointer, so anyone who scrolled here knows the audio
             * exists and where it went.
             */ ?>
        <div class="post-lang-section">
            <p class="mb-0 flex items-center gap-2 text-sm font-semibold post-lang-listen">
                <i class="bi bi-mic-fill"></i><?= e(t('manobo_widget.listen_moved')) ?>
            </p>
        </div>
        <?php endif; ?>

        <!-- ── Loading spinner ──────────────────────────────────────── -->
        <div x-show="loading" class="post-lang-section text-center">
            <div class="mx-auto mb-3 h-9 w-9 animate-spin rounded-full border-4 border-t-transparent post-lang-spinner"></div>
            <p class="font-medium text-slate-600"><?= e(t('manobo_widget.translating')) ?></p>
            <p class="mt-1 text-sm text-slate-400"><?= e(t('manobo_widget.please_wait')) ?></p>
        </div>

        <?php
        /*
         * Whether the person reading this can act on a configuration problem.
         *
         * A resident cannot set an API key or top up a billing account, so
         * showing them either is noise at best — and at worst it puts a
         * vendor's billing page on a barangay notice board and makes the
         * barangay look broken. They get one neutral sentence instead; staff
         * get the detail that actually tells them what to do.
         */
        $__mtIsStaff = in_array($_SESSION['role'] ?? '', ['staff', 'admin', 'superadmin'], true);
        ?>
        <!-- ── Error state ────────────────────────────────────────────── -->
        <div x-show="errorMsg && !loading"
             :class="errorMsg && (errorMsg.includes('API key') || errorMsg.includes('credit balance')) ? 'bg-amber-50' : 'bg-red-50'"
             class="px-5 py-4">

            <!-- Not configured / no credits. Same message either way for a
                 resident: the reason is ours to fix, not theirs to read. -->
            <template x-if="errorMsg && (errorMsg.includes('AI key') || errorMsg.includes('API key') || errorMsg.includes('credit balance'))">
                <div>
                    <p class="font-semibold text-amber-800">
                        <?= e(t('manobo_widget.unavailable_title')) ?>
                    </p>
                    <p class="mt-1 text-sm text-amber-700">
                        <?= e(t('manobo_widget.unavailable_body')) ?>
                    </p>

                    <?php if ($__mtIsStaff): ?>
                    <!-- Staff only: the actionable detail. -->
                    <p class="mt-3 border-t border-amber-200 pt-2 text-xs text-amber-700">
                        <i class="bi bi-wrench-adjustable me-1"></i>
                        <span x-show="errorMsg && errorMsg.includes('credit balance')">
                            <?= e(t('manobo_widget.staff_note_credits')) ?>
                        </span>
                        <span x-show="errorMsg && !errorMsg.includes('credit balance')">
                            <?= e(t('manobo_widget.staff_note_key')) ?>
                        </span>
                    </p>
                    <?php endif; ?>
                </div>
            </template>
            <!-- Generic error. The condition mirrors the branch above exactly,
                 or an "API key" message would match both and the reader would
                 get the calm notice and the raw error stacked together. -->
            <template x-if="!errorMsg || (!errorMsg.includes('AI key') && !errorMsg.includes('API key') && !errorMsg.includes('credit balance'))">
                <div>
                    <!-- Residents see a plain sentence; the raw message is for
                         whoever can act on it. -->
                    <p class="font-medium text-red-600">
                        <?php if ($__mtIsStaff): ?>
                        ❌ <span x-text="errorMsg"></span>
                        <?php else: ?>
                        <?= e(t('manobo_widget.err_generic')) ?>
                        <?php endif; ?>
                    </p>
                    <button type="button"
                            @click="errorMsg = null; fetchTranslation()"
                            class="mt-2 rounded-lg bg-red-500 px-4 py-2 text-sm font-semibold text-white
                                   transition hover:bg-red-600">
                        🔄 <?= e(t('manobo_widget.retry')) ?>
                    </button>
                </div>
            </template>
        </div>

        <!-- ── Translation result ──────────────────────────────────────── -->
        <div x-show="!!translation && !loading"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="bg-amber-50">

            <!-- Badge row -->
            <div class="flex flex-wrap items-center gap-2 px-5 pt-4">
                <span class="inline-flex items-center gap-1 rounded-full bg-blue-700 px-3 py-0.5
                             text-xs font-bold text-white">
                    🌿 <?= e(t('manobo_widget.title')) ?>
                </span>
                <?php // An offline gloss is NOT a finished translation — the dictionary
                      // applies no affixes and no Manobo word order. Say so plainly
                      // rather than letting it pass as the real thing. ?>
                <span x-show="offline" x-cloak
                      class="inline-flex items-center gap-1 rounded-full bg-slate-200 px-3 py-0.5 text-xs font-bold text-slate-700">
                    📖 <?= e(t('manobo_widget.offline_badge')) ?>
                </span>
                <?php // Text a person wrote outranks anything generated, and
                      // says so plainly rather than sharing the AI's chip. ?>
                <span x-show="manual" x-cloak
                      class="inline-flex items-center gap-1 rounded-full bg-blue-700 px-3 py-0.5 text-xs font-bold text-white">
                    ✍️ <?= e(t('manobo_widget.manual_badge')) ?>
                </span>
                <span class="text-xs italic text-slate-400"><?= e(t('manobo_widget.dialect')) ?></span>
                <span x-show="cached && !manual" class="text-xs italic text-slate-400"><?= e(t('manobo_widget.cached')) ?></span>
            </div>

            <!-- Translated text -->
            <div class="px-5 py-4">
                <?php /*
                     * ADDED: when the offline gloss returned per-word source
                     * tags, render each word/phrase on its own — plain for a
                     * real Manobo match, underlined with a tooltip for a
                     * Bisaya fallback, and faded-with-tooltip for a word
                     * neither dictionary has. An AI translation, a cached
                     * one, or hand-typed text has no segments (a finished
                     * sentence isn't "made of" dictionary words), so those
                     * keep the plain paragraph they always had.
                     */ ?>
                <p x-show="offline && segments.length" x-cloak
                   class="whitespace-pre-line text-base font-medium leading-8 text-slate-800">
                    <template x-for="(seg, i) in segments" :key="i">
                        <span
                            x-text="seg.display"
                            :title="seg.source === 'bisaya' ? <?= e(json_encode(t('manobo_widget.bisaya_tooltip'))) ?>
                                  : (seg.source === 'none' ? <?= e(json_encode(t('manobo_widget.missing_tooltip'))) ?> : null)"
                            :class="{
                                'mn-seg-bisaya': seg.source === 'bisaya',
                                'mn-seg-missing': seg.source === 'none',
                            }"></span>
                    </template>
                </p>
                <p x-show="!(offline && segments.length)"
                   x-text="translation"
                   class="whitespace-pre-line text-base font-medium leading-8 text-slate-800"></p>

                <?php // Coverage + what the dictionary could not reach, shown only for
                      // an offline gloss so the resident can judge how much to trust it. ?>
                <div x-show="offline" x-cloak
                     class="mt-3 rounded-xl border border-slate-300 bg-white/70 p-3">
                    <p class="text-xs font-semibold text-slate-700">
                        <i class="bi bi-info-circle"></i>
                        <?= e(t('manobo_widget.offline_explain')) ?>
                    </p>
                    <p class="mt-1 text-xs text-slate-600">
                        <span x-text="matched"></span> / <span x-text="totalWords"></span>
                        <?= e(t('manobo_widget.offline_coverage')) ?>
                    </p>
                    <template x-if="missing.length">
                        <p class="mt-1 text-xs text-slate-500">
                            <?= e(t('manobo_widget.offline_missing')) ?>
                            <span class="font-mono" x-text="missing.join(', ')"></span>
                        </p>
                    </template>
                </div>
            </div>

            <!-- Action buttons -->
            <div class="flex flex-wrap items-center gap-3 px-5 pb-3">
                <button type="button"
                        @click="readAloud()"
                        class="listen-btn inline-flex items-center gap-2 rounded-xl px-5 py-2.5
                               text-sm font-bold text-white shadow-md transition"
                        :class="speaking ? 'bg-red-500 hover:bg-red-600' : 'bg-blue-600 hover:bg-blue-700'">
                    <span class="text-base" x-text="speaking ? '⏹' : '🔊'"></span>
                    <span x-text="speaking ? <?= e(json_encode(t('manobo_widget.speak_stop'))) ?> : <?= e(json_encode(t('manobo_widget.speak_btn'))) ?>"></span>
                </button>

                <button type="button"
                        @click="copyText()"
                        class="inline-flex items-center gap-2 rounded-xl bg-slate-600 px-4 py-2.5
                               text-sm font-semibold text-white transition hover:bg-slate-700">
                    📋 <?= e(t('manobo_widget.copy')) ?>
                </button>

                <button type="button"
                        @click="hideTranslation()"
                        class="inline-flex items-center gap-2 rounded-xl post-lang-speak px-4 py-2.5
                               text-sm font-semibold text-white transition ">
                    ✕ Itago
                </button>
            </div>

            <?php /* This widget's own speak button uses the device's Filipino
                     voice on Manobo text — the same approximation the main
                     voice reader makes, and it is labelled the same way here.
                     Saying it once at the top of the post is not enough when
                     there is a second button further down that does it too. */ ?>
            <p class="mx-5 mb-2 flex items-start gap-2 text-xs post-lang-footnote-text" aria-live="polite">
                <i class="bi" :class="fallback === 'recorded_only' ? 'bi-mic-fill' : 'bi-robot'"></i>
                <span x-text="voiceNotice || (fallback === 'recorded_only'
                    ? <?= e(json_encode(t('voice_reader.source_recorded_only'))) ?>
                    : <?= e(json_encode(t('voice_reader.source_dataset_mix'))) ?>)"></span>
            </p>

            <!-- Voice controls -->
            <div class="flex flex-wrap items-center gap-4 border-t post-lang-divider border-00 px-5 py-3">
                <span class="text-xs font-medium text-slate-500">🎙️ <?= e(t('manobo_widget.voice_speed')) ?></span>
                <select x-model.number="speechRate"
                        class="cursor-pointer rounded-lg border border-slate-200 bg-white
                               px-2 py-1 text-xs text-slate-700">
                    <option value="0.75">🐢 Mabagal</option>
                    <option value="1">🚶 Normal</option>
                    <option value="1.25">🏃 Mabilis</option>
                </select>
                <span class="text-xs text-slate-500">🔈</span>
                <input type="range" min="0.3" max="1" step="0.1"
                       x-model.number="speechVolume"
                       class="w-24 cursor-pointer accent-blue-700">
                <span class="text-xs text-slate-500">🔊</span>
            </div>

            <!-- Disclaimer. The AI caveat is wrong for text a person wrote, so
                 the two cases get their own sentence. -->
            <div class="mx-5 mb-4 rounded-xl post-lang-footnote border border-transparent bg-transparent-100 p-3">
                <p class="text-xs post-lang-footnote-text" x-show="!manual">
                    ⚠️ <strong>Paalala:</strong> <?= e(t('manobo_widget.ai_disclaimer')) ?>
                    <?= e(t('manobo_widget.official_note')) ?>
                </p>
                <p class="text-xs post-lang-footnote-text" x-show="manual" x-cloak>
                    ✍️ <?= e(t('manobo_widget.manual_disclaimer')) ?>
                    <?= e(t('manobo_widget.official_note')) ?>
                </p>
            </div>

        </div><!-- /translation result -->

    </div>
</div>

<script src="<?= e(asset_v('assets/js/voice-reader.js')) ?>"></script>
<script>
(function () {
    if (window.__manoboTranslatorDefined) return;
    window.__manoboTranslatorDefined = true;

    window.manoboTranslator = function () {
        return {
            _contentType: '',
            _contentId:   0,
            _text:        '',
            hasKey:       false,

            loading:      false,
            translation:  null,
            cached:       false,
            /* Set when the text came from the offline dictionary rather than
               the AI — drives the "word-by-word" labelling below. */
            offline:      false,
            /* Set when the text came from the row's own Manobo columns rather
               than from the AI — a person wrote it, so nothing here may label
               it as machine output or try to replace it. */
            manual:       false,
            matched:      0,
            totalWords:   0,
            missing:      [],
            /* Per-word source tags from the offline gloss — see the
               "Translated text" block above. Empty for an AI/cached/manual
               translation, which is one finished string, not a word list. */
            segments:     [],
            errorMsg:     null,
            speaking:     false,
            speechRate:   1,
            speechVolume: 1.0,
            fallback:     'recorded_only',
            voiceNotice:  '',
            _seg:         null,

            init() {
                this._contentType = this.$el.dataset.mtype  || '';
                this._contentId   = parseInt(this.$el.dataset.mid  || '0', 10);
                this._text        = this.$el.dataset.mtext || '';
                this.hasKey       = this.$el.dataset.hasKey === '1';
                this.fallback     = this.$el.dataset.fallback || 'recorded_only';

                /* Manobo already stored for this post — either typed by a person
                   in the admin form or cached from an earlier AI call. Shown
                   immediately, with no API call and no credits involved. This
                   is the path that works today. */
                const cached = this.$el.dataset.cached || '';
                if (cached.trim()) {
                    this.translation = cached;
                    this.manual      = this.$el.dataset.manual === '1';
                    this.cached      = !this.manual;
                    return; // already have translation — skip auto-fetch
                }

                /* Auto-fetch 1.5 s after page load. */
                setTimeout(() => this.fetchTranslation(), 1500);
            },

            async fetchTranslation() {
                if (this.loading || this.translation) return;
                this.loading  = true;
                this.errorMsg = null;

                try {
                    const base = (window.BarangGabay?.baseUrl || '').replace(/\/$/, '');
                    const fd   = new FormData();
                    fd.append('text',         this._text);
                    fd.append('content_type', this._contentType);
                    fd.append('content_id',   this._contentId);
                    fd.append('csrf_token',   window.BarangGabay?.csrfToken || '');

                    const res  = await fetch(base + '/api/ai/translate', { method: 'POST', body: fd });
                    const data = await res.json();

                    if (data.success) {
                        this.translation = data.translation;
                        this.cached      = data.cached || false;
                        /* Offline dictionary gloss, not an AI translation. Kept
                           in separate state so the UI can label it honestly. */
                        this.offline     = data.offline || false;
                        this.matched     = data.matched || 0;
                        this.totalWords  = data.total   || 0;
                        this.missing     = data.missing || [];
                        this.segments    = data.segments || [];
                        /* Pulse the listen button to draw attention. */
                        this.$nextTick(() => {
                            const btn = this.$el.querySelector('.listen-btn');
                            if (btn) {
                                btn.classList.add('ring-4', 'ring-blue-300', 'animate-pulse');
                                setTimeout(() => btn.classList.remove('ring-4', 'ring-blue-300', 'animate-pulse'), 4000);
                            }
                        });
                    } else {
                        this.errorMsg = data.error || <?= json_encode(t('manobo_widget.err_generic')) ?>;
                    }
                } catch (_) {
                    this.errorMsg = <?= json_encode(t('manobo_widget.err_network')) ?>;
                } finally {
                    this.loading = false;
                }
            },

            /* Plays approved recordings from the Voice Training dataset: the
               same server resolver and segment player as the Voice Reader at
               the top of the post (longest phrase first; Bisaya fallback words
               use Bisaya recordings). Unrecorded words are skipped unless the
               admin enabled the device-voice fallback. */
            async readAloud() {
                if (this.speaking) {
                    this._player().stop();
                    this.speaking = false;
                    return;
                }
                if (!window.VoiceSegmentPlayer || !this.translation) return;

                const player = this._player();
                player.unlock();            // inside the tap, for iOS
                this.speaking    = true;
                this.voiceNotice = '';

                try {
                    const base = (window.BarangGabay?.baseUrl || '').replace(/\/$/, '');
                    const fd   = new FormData();
                    fd.append('language',   'msm');
                    fd.append('text',       this.translation);
                    fd.append('csrf_token', window.BarangGabay?.csrfToken || '');
                    const res  = await fetch(base + '/api/voice/resolve', {
                        method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const plan = await res.json();
                    if (!plan.success) throw new Error(plan.error || 'resolve failed');

                    this.fallback = plan.fallback || this.fallback;
                    const recordedOnly = this.fallback === 'recorded_only';
                    if (recordedOnly && !plan.recorded) {
                        this.voiceNotice = <?= json_encode(t('voice_reader.no_recordings')) ?>;
                        this.speaking = false;
                        return;
                    }
                    if (recordedOnly && plan.missing) {
                        this.voiceNotice = <?= json_encode(t('voice_reader.some_missing')) ?>;
                    }

                    player.play(plan.segments, {
                        fallback: this.fallback,
                        lang:     plan.speech_lang || 'fil-PH',
                        voice:    window.voicePickVoice ? window.voicePickVoice(plan.voices || []) : null,
                        rate:     parseFloat(this.speechRate) || 1,
                        volume:   parseFloat(this.speechVolume),
                        onDone:   () => { this.speaking = false; },
                        onSpeechError: () => { this.speaking = false; },
                    });
                } catch (_) {
                    this.voiceNotice = <?= json_encode(t('manobo_widget.err_network')) ?>;
                    this.speaking = false;
                }
            },

            _player() {
                if (!this._seg) { this._seg = new window.VoiceSegmentPlayer(); }
                return this._seg;
            },

            hideTranslation() {
                if (this.speaking) { this._player().stop(); this.speaking = false; }
                this.translation = null;
                this.cached      = false;
                this.offline     = false;
                this.manual      = false;
                this.missing     = [];
                this.segments    = [];
            },

            copyText() {
                if (!this.translation) return;
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(this.translation)
                        .then(() => alert('✅ Nakopya ang Manobo na salin!'))
                        .catch(() => this._fallbackCopy());
                } else {
                    this._fallbackCopy();
                }
            },

            _fallbackCopy() {
                const el = document.createElement('textarea');
                el.value = this.translation || '';
                el.style.cssText = 'position:fixed;opacity:0;';
                document.body.appendChild(el);
                el.select();
                document.execCommand('copy');
                document.body.removeChild(el);
                alert('✅ Nakopya!');
            },
        };
    };
}());
</script>