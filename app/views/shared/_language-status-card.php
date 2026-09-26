<?php
/**
 * Where each language stands, on the edit form.
 *
 * The list pages show chips that open a panel, because a table of twenty
 * posts has no room for anything larger. An edit form has room, and a
 * staff member here is about to act — so the reason and the fix are laid
 * out in full rather than hidden behind a click.
 *
 * Placed ABOVE the hand-entry fields on purpose. Someone who can see that
 * English is missing because the daily allowance ran out will leave it for
 * tonight; someone who cannot will retype it, and their typing then
 * becomes the version the runner must never overwrite — a permanent cost
 * paid for a temporary problem.
 *
 * Expects the same variables as _language-badges.php:
 *   $lbRow, $lbType, $lbBodyField, $lbAttempts, $lbRedirect
 */

use App\Services\TranslationOutcome;
use App\Services\TranslationService;

$lbRow       = $lbRow       ?? [];
$lbType      = $lbType      ?? 'announcement';
$lbBodyField = $lbBodyField ?? 'body';
$lbAttempts  = $lbAttempts  ?? [];
$lbRedirect  = $lbRedirect  ?? '/admin/announcements';

$lscId     = (int) ($lbRow['id'] ?? 0);
$lscStatus = TranslationService::statusFor($lbRow, $lbBodyField);
?>
<div class="lang-status-card mb-4">
    <p class="lang-status-card__title">
        <i class="bi bi-translate me-1"></i><?= e(t('translation_health.card_title')) ?>
    </p>

    <?php foreach (['fil' => 'fil', 'en' => 'en', 'msm' => 'manobo'] as $lscLang => $lscKey):
        $lscIsSource = $lscStatus['source'] === $lscKey;
        $lscHas      = $lscStatus[$lscKey]['has'];
        $lscMachine  = $lscStatus[$lscKey]['machine'];
        $lscState    = $lscIsSource ? 'source' : (!$lscHas ? 'missing' : ($lscMachine ? 'machine' : 'manual'));

        $lscAttempt = $lbAttempts[$lscLang . '.text'] ?? null;
        $lscReason  = (string) ($lscAttempt['reason_code'] ?? '');

        // Missing with no attempt row: nothing has ever tried. Saying so is
        // honest; inventing a provider error is not.
        $lscNever = $lscState === 'missing' && $lscReason === '';

        // Only these two are worth explaining or acting on.
        $lscActionable = \in_array($lscState, ['missing', 'machine'], true);
    ?>
    <div class="lang-status-row">
        <span class="lang-chip lang-chip--<?= e($lscState) ?>">
            <?= e(strtoupper(locale_short_code($lscLang))) ?>
        </span>

        <div class="lang-status-row__body">
            <p class="lang-status-row__state"><?= e(t('translation_health.state_' . $lscState)) ?></p>

            <?php if ($lscActionable): ?>
            <p class="lang-status-row__reason">
                <?= e($lscNever
                    ? t('translation_health.never_tried')
                    : t(TranslationOutcome::messageKey($lscReason))) ?>
            </p>
            <p class="lang-status-row__fix">
                <?= e($lscNever
                    ? t('translation_health.never_tried_fix')
                    : t(TranslationOutcome::fixKey($lscReason))) ?>
            </p>

            <?php
            /* The literal variable name, because that is the string
               someone editing .env searches for. */
            $lscEnv = $lscNever ? null : TranslationOutcome::envKey($lscReason);
            if ($lscEnv !== null): ?>
            <p class="lang-status-row__env"><code><?= e($lscEnv) ?></code></p>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($lscActionable): ?>
        <?php /* Its own form, not a nested one: this sits inside the post's
                 edit form on some pages, and a nested <form> is invalid HTML
                 whose submit button silently submits the OUTER form —
                 saving the post instead of translating a language. */ ?>
        <button type="submit"
                form="retranslate-<?= e($lscLang) ?>-<?= $lscId ?>"
                class="lang-status-row__btn">
            <?= e(t('translation_health.translate_now')) ?>
        </button>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php
/*
 * The <form> elements are NOT written here — and that is the whole point.
 *
 * This card is included in the MIDDLE of the post's own edit form, so a
 * form emitted at this point is nested inside it. Nested forms are invalid
 * HTML: the browser drops the inner one, and its submit button then posts
 * the OUTER form. "Translate now" would save the post instead of
 * translating a language — a bug that looks like it works.
 *
 * I wrote this file with the forms inline and shipped exactly that. The
 * page-level test caught it.
 *
 * A button may reference a form anywhere in the document by id, so the
 * forms are collected here and rendered by _language-retry-forms.php at
 * the end of the page, outside everything — the same shape the list pages
 * already use for their detail panel.
 */
$GLOBALS['bg_retry_forms'] = $GLOBALS['bg_retry_forms'] ?? [];

foreach (['fil', 'en', 'msm'] as $lscLang) {
    $lscKey      = $lscLang === 'msm' ? 'manobo' : $lscLang;
    $lscIsSource = $lscStatus['source'] === $lscKey;
    $lscHas      = $lscStatus[$lscKey]['has'];
    $lscMachine  = $lscStatus[$lscKey]['machine'];
    $lscState    = $lscIsSource ? 'source' : (!$lscHas ? 'missing' : ($lscMachine ? 'machine' : 'manual'));

    if (!\in_array($lscState, ['missing', 'machine'], true)) {
        continue;
    }

    $GLOBALS['bg_retry_forms'][] = [
        'id'        => 'retranslate-' . $lscLang . '-' . $lscId,
        'type'      => $lbType,
        'contentId' => $lscId,
        'lang'      => $lscLang,
        'redirect'  => $lbRedirect,
    ];
}
?>
