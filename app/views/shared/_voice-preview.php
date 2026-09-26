<?php
/**
 * _voice-preview.php — voice narration panel for the admin create/edit forms.
 *
 * Include inside an admin <form> after setting (all optional):
 *   $__vpSourceLang (string)      'fil' or 'en' — which language to preview first
 *   $__vpType       (string|null) 'announcement' | 'event' | 'ordinance'
 *   $__vpRow        (array|null)  the saved row, on edit forms only
 *
 * Two jobs, and they are genuinely different:
 *
 *   Preview  — hear the wording before publishing it. Abbreviations and typos
 *              that read perfectly well on screen fall apart out loud, and the
 *              only way an editor catches those is by hearing the post before
 *              a resident does. Uses the device's own voice, because this runs
 *              over and over while someone edits and spending the barangay's
 *              metered allowance on drafts nobody will hear is how the budget
 *              for the published version disappears.
 *
 *   Generate — build the cached MP3 residents will actually hear, per language.
 *              Saving the post already does this; the button is for rebuilding
 *              after an edit and for retrying a provider hiccup. Shown only on
 *              an edit form, since a post that does not exist yet has no audio.
 *
 * Manobo appears in both. It is spoken by the Filipino voice and labelled as
 * an approximation, so staff hear exactly what residents will hear rather than
 * a better version of it — and the human recording panel below stays the way
 * to replace it with an actual Manobo speaker.
 *
 * Only rendered for roles: staff, admin, superadmin.
 */

use App\Services\PostAudioService;

if (!in_array($_SESSION['role'] ?? '', ['staff', 'admin', 'superadmin'], true)) {
    return;
}

$__vpSourceLang = ($__vpSourceLang ?? 'fil') === 'en' ? 'en' : 'fil';
$__vpType       = $__vpType ?? null;
$__vpRow        = $__vpRow  ?? null;

$__vpService    = new PostAudioService();
$__vpConfigured = $__vpService->isConfigured();
$__vpId         = (int) ($__vpRow['id'] ?? 0);
$__vpCanGenerate = $__vpType !== null && $__vpId > 0;

$__vpStatuses = $__vpCanGenerate && is_array($__vpRow)
    ? $__vpService->status($__vpType, $__vpRow)
    : [];

/*
 * The recorded reason, beside the state.
 *
 * The states here — unavailable / none / ready / stale — say WHAT a
 * language's audio is, and never why it is that. "Unavailable" covers a
 * language with no text, a post over the narration budget and a provider
 * that refused, which are three different problems with three different
 * fixes shown identically. Those reasons are now recorded with the same
 * codes the translation side uses, so the panel can simply read them.
 */
$__vpReasons = [];
if ($__vpCanGenerate) {
    foreach (\App\Models\TranslationAttempt::forContent((string) $__vpType, $__vpId) as $__vpKey => $__vpRow2) {
        [$__vpLang, $__vpKind] = array_pad(explode('.', $__vpKey), 2, '');
        if ($__vpKind !== 'audio' || (int) $__vpRow2['ok'] === 1) {
            continue;
        }

        $__vpCodeNow = (string) $__vpRow2['reason_code'];
        $__vpReasons[$__vpLang] = [
            'reason' => t(\App\Services\TranslationOutcome::messageKey($__vpCodeNow)),
            'fix'    => t(\App\Services\TranslationOutcome::fixKey($__vpCodeNow)),
            'envKey' => \App\Services\TranslationOutcome::envKey($__vpCodeNow),
        ];
    }
}

$__vpLangNames = [
    'fil' => t('voice_reader.lang_fil'),
    'en'  => t('voice_reader.lang_en'),
    'msm' => t('voice_reader.lang_mn'),
];

$__vpPreviewConfig = [
    'locale'  => $__vpSourceLang,
    'strings' => [
        'empty'       => t('voice_reader.preview_empty'),
        'failed'      => t('voice_reader.preview_failed'),
        'no_voice'    => t('voice_reader.preview_no_voice'),
        'date_label'  => t('voice_reader.lead_when_label'),
        'venue_label' => t('voice_reader.lead_venue_label'),
    ],
];

$__vpGeneratorConfig = [
    'type'     => (string) $__vpType,
    'id'       => $__vpId,
    'locales'  => ['fil', 'en', 'msm'],
    'statuses' => $__vpStatuses,
    'strings'  => [
        'failed' => t('voice_reader.gen_failed'),
        'states' => [
            'ready'       => t('voice_reader.state_ready'),
            'stale'       => t('voice_reader.state_stale'),
            'none'        => t('voice_reader.state_none'),
            'unavailable' => t('voice_reader.state_unavailable'),
            'human'       => t('voice_reader.state_human'),
            'human_stale' => t('voice_reader.state_human_stale'),
        ],
    ],
];

$__vpEmitJs = !defined('BG_VOICE_JS_EMITTED');
if ($__vpEmitJs) {
    define('BG_VOICE_JS_EMITTED', true);
}
?>

<?php if ($__vpEmitJs): ?>
<?php /* Not deferred: Alpine is, and this must define the components first. */ ?>
<script src="<?= e(asset_v('assets/js/voice-reader.js')) ?>"></script>
<?php endif; ?>

<div class="admin-card mb-4">

    <div class="admin-card-header">
        <h2 class="admin-card-title d-flex align-items-center gap-2 flex-wrap">
            <i class="bi bi-volume-up-fill" style="color:var(--brand-primary);"></i>
            <?= e(t('voice_reader.preview_heading')) ?>
        </h2>
        <p class="text-muted mb-0 mt-1" style="font-size:.78rem;line-height:1.5;">
            <?= e(t('voice_reader.preview_intro')) ?>
        </p>
    </div>

    <div class="admin-card-body">

        <!-- ── Preview (device voice, costs nothing) ───────────────────── -->
        <div x-data="voicePreview()" class="mb-3 pb-3" style="border-bottom:1px solid var(--border);">

            <script type="application/json" data-voice-preview-config><?= json_encode(
                $__vpPreviewConfig,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
            ) ?></script>

            <template x-if="!supported">
                <p class="mb-0 text-muted" style="font-size:.82rem;">
                    <i class="bi bi-info-circle me-1"></i><?= e(t('voice_reader.preview_unsupported')) ?>
                </p>
            </template>

            <template x-if="supported">
                <div class="d-flex align-items-center gap-2 flex-wrap">

                    <button type="button"
                            @click="toggle()"
                            :disabled="busy"
                            class="btn btn-sm d-inline-flex align-items-center gap-2"
                            :class="speaking ? 'btn-danger' : 'btn-outline-secondary'"
                            style="font-size:.8rem;padding:.45rem .9rem;">
                        <i class="bi" :class="speaking ? 'bi-stop-fill' : 'bi-play-fill'"></i>
                        <span x-show="!speaking && !busy"><?= e(t('voice_reader.preview_btn')) ?></span>
                        <span x-show="busy" x-cloak><?= e(t('voice_reader.preview_working')) ?></span>
                        <span x-show="speaking" x-cloak><?= e(t('voice_reader.preview_stop')) ?></span>
                    </button>

                    <?php /* Which language version to hear. Each reads its own
                             translation fields; Manobo is spoken by the
                             Filipino voice, exactly as residents will hear it. */ ?>
                    <select x-model="locale"
                            @change="stop()"
                            class="form-select form-select-sm"
                            style="width:auto;font-size:.78rem;"
                            aria-label="<?= e(t('voice_reader.preview_language')) ?>">
                        <option value="fil"><?= e($__vpLangNames['fil']) ?></option>
                        <option value="en"><?= e($__vpLangNames['en']) ?></option>
                        <option value="msm"><?= e($__vpLangNames['msm']) ?></option>
                    </select>

                    <select x-model.number="rate"
                            class="form-select form-select-sm"
                            style="width:auto;font-size:.78rem;"
                            aria-label="<?= e(t('voice_reader.speed')) ?>">
                        <option value="0.75"><?= e(t('voice_reader.speed_slow')) ?></option>
                        <option value="1"><?= e(t('voice_reader.speed_normal')) ?></option>
                        <option value="1.25"><?= e(t('voice_reader.speed_fast')) ?></option>
                    </select>

                    <span class="text-muted" style="font-size:.74rem;" x-show="message" x-cloak x-text="message"></span>

                    <span class="text-muted" style="font-size:.74rem;" x-show="locale === 'msm'" x-cloak>
                        <i class="bi bi-robot me-1"></i><?= e(t('voice_reader.preview_manobo_note')) ?>
                    </span>
                </div>
            </template>

            <p class="form-text mt-2 mb-0">
                <i class="bi bi-info-circle me-1"></i><?= e(t('voice_reader.preview_hint')) ?>
            </p>
        </div>

        <!-- ── Generated narration (what residents actually hear) ──────── -->
        <?php if (!$__vpConfigured): ?>
        <p class="mb-0 text-muted" style="font-size:.8rem;line-height:1.55;">
            <i class="bi bi-exclamation-circle me-1"></i><?= e(t('voice_reader.gen_unconfigured')) ?>
        </p>

        <?php elseif (!$__vpCanGenerate): ?>
        <p class="mb-0 text-muted" style="font-size:.8rem;line-height:1.55;">
            <i class="bi bi-info-circle me-1"></i><?= e(t('voice_reader.gen_after_save')) ?>
        </p>

        <?php else: ?>
        <div x-data="voiceGenerator()">

            <script type="application/json" data-voice-generator-config><?= json_encode(
                $__vpGeneratorConfig,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
            ) ?></script>

            <p class="fw-bold mb-2" style="font-size:.78rem;letter-spacing:.06em;text-transform:uppercase;color:var(--brand-primary);">
                <i class="bi bi-soundwave me-1"></i> <?= e(t('voice_reader.gen_heading')) ?>
            </p>

            <div class="voice-gen-grid mb-2">
                <?php foreach (['fil', 'en', 'msm'] as $__vpCode): ?>
                <div class="voice-gen-row">
                    <span class="voice-gen-lang"><?= e($__vpLangNames[$__vpCode]) ?></span>

                    <span class="voice-gen-state" :class="stateClass('<?= $__vpCode ?>')"
                          x-text="label('<?= $__vpCode ?>')"></span>

                    <span class="voice-gen-detail"
                          x-text="voiceOf('<?= $__vpCode ?>') ? voiceOf('<?= $__vpCode ?>') + ' · ' + charsOf('<?= $__vpCode ?>') + ' chars' : charsOf('<?= $__vpCode ?>') + ' chars'"></span>

                    <?php /* Why, not just what. Rendered from PHP rather
                             than the Alpine config because it does not
                             change as the panel is used — it is the last
                             recorded outcome for this language. */ ?>
                    <?php if (isset($__vpReasons[$__vpCode])): ?>
                    <p class="voice-gen-why">
                        <?= e($__vpReasons[$__vpCode]['reason']) ?>
                        <span class="voice-gen-fix"><?= e($__vpReasons[$__vpCode]['fix']) ?></span>
                        <?php if ($__vpReasons[$__vpCode]['envKey'] !== null): ?>
                        <code><?= e($__vpReasons[$__vpCode]['envKey']) ?></code>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>

                    <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            style="font-size:.72rem;padding:.25rem .6rem;"
                            :disabled="!!busy || stateOf('<?= $__vpCode ?>') === 'unavailable'"
                            @click="generate('<?= $__vpCode ?>')">
                        <span x-show="busy !== '<?= $__vpCode ?>'"><?= e(t('voice_reader.gen_one')) ?></span>
                        <span x-show="busy === '<?= $__vpCode ?>'" x-cloak><?= e(t('voice_reader.gen_working')) ?></span>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button"
                        class="btn btn-sm btn-outline-primary"
                        style="font-size:.76rem;"
                        :disabled="!!busy"
                        @click="generate('')">
                    <i class="bi bi-arrow-repeat me-1"></i>
                    <span x-show="busy !== 'all'"><?= e(t('voice_reader.gen_all')) ?></span>
                    <span x-show="busy === 'all'" x-cloak><?= e(t('voice_reader.gen_working')) ?></span>
                </button>
                <span class="text-danger" style="font-size:.74rem;" x-show="error" x-cloak x-text="error"></span>
            </div>

            <p class="form-text mt-2 mb-0">
                <i class="bi bi-info-circle me-1"></i><?= e(t('voice_reader.gen_hint')) ?>
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>
