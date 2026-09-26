<?php
/**
 * FIL / EN / MN, and what to do when one of them is red.
 *
 * The chips themselves already existed — ✓ or ✗ with a tooltip. What they
 * could not say is WHY, which is the only part a staff member can act on.
 * A red MN told them Manobo was missing and left them to guess between "no
 * API key", "no credits", "the dictionary covered too little of this post"
 * and "nobody has tried yet" — four different problems with four different
 * fixes, shown identically.
 *
 * Now each chip carries its reason, the suggested fix, and a button for
 * that one language. Clicking opens the page's single detail panel rather
 * than each chip owning a popover: a table of twenty posts would otherwise
 * carry sixty of them, and popovers inside a scrolling table clip.
 *
 * Expects:
 *   $lbRow        array   The content row.
 *   $lbType       string  'announcement' | 'event' | 'ordinance'
 *   $lbBodyField  string  'body' or 'description'. Getting this wrong reads
 *                         an absent column and reports a filled language as
 *                         missing.
 *   $lbAttempts   array   Outcomes for this row, keyed "lang.kind", from
 *                         TranslationAttempt::forMany(). Optional.
 *   $lbRedirect   string  Where the retry button should return to.
 *
 * Include _language-badge-panel.php ONCE per page for the detail panel.
 */

use App\Models\TranslationAttempt;
use App\Services\TranslationOutcome;
use App\Services\TranslationService;

$lbRow       = $lbRow       ?? [];
$lbType      = $lbType      ?? 'announcement';
$lbBodyField = $lbBodyField ?? 'body';
$lbAttempts  = $lbAttempts  ?? [];
$lbRedirect  = $lbRedirect  ?? '/admin/announcements';

$lbId     = (int) ($lbRow['id'] ?? 0);
$lbStatus = TranslationService::statusFor($lbRow, $lbBodyField);
?>
<span class="lang-badges">
<?php
/* Keyed by the locale code the rest of the app uses; statusFor() answers
   under the column suffix, so 'msm' reads 'manobo'. */
foreach (['fil' => 'fil', 'en' => 'en', 'msm' => 'manobo'] as $lbLang => $lbKey):

    $lbIsSource = $lbStatus['source'] === $lbKey;
    $lbHas      = $lbStatus[$lbKey]['has'];
    $lbMachine  = $lbStatus[$lbKey]['machine'];

    // The four states, in the order a reader cares about them.
    $lbState = $lbIsSource ? 'source' : (!$lbHas ? 'missing' : ($lbMachine ? 'machine' : 'manual'));

    $lbAttempt = $lbAttempts[$lbLang . '.text'] ?? null;
    $lbReason  = (string) ($lbAttempt['reason_code'] ?? '');

    /*
     * A language can be missing with no attempt row at all — the post
     * predates this feature, or nothing has ever tried. Saying "no reason
     * recorded" is more honest than inventing one, and the fix is the same
     * either way: press the button.
     */
    if ($lbState === 'missing' && $lbReason === '') {
        $lbReason = 'never_tried';
    }

    $lbLabel = strtoupper(locale_short_code($lbLang));

    // Only missing and machine states are worth opening. The source
    // language and hand-written text need no explanation and no button.
    $lbClickable = \in_array($lbState, ['missing', 'machine'], true);

    $lbDetail = [
        'lang'      => $lbLang,
        'label'     => $lbLabel,
        'state'     => $lbState,
        'title'     => (string) ($lbRow['title'] ?? ''),
        'type'      => $lbType,
        'id'        => $lbId,
        'stateText' => t('translation_health.state_' . $lbState),
        'reason'    => $lbReason === 'never_tried'
            ? t('translation_health.never_tried')
            : t(TranslationOutcome::messageKey($lbReason)),
        'fix'       => $lbReason === 'never_tried'
            ? t('translation_health.never_tried_fix')
            : t(TranslationOutcome::fixKey($lbReason)),
        'envKey'    => $lbReason !== 'never_tried' ? TranslationOutcome::envKey($lbReason) : null,
        'detail'    => (string) ($lbAttempt['message'] ?? ''),
        'attempts'  => (int) ($lbAttempt['attempts'] ?? 0),
        'retryAt'   => (string) ($lbAttempt['retry_after'] ?? ''),
    ];
?>
    <?php if ($lbClickable): ?>
    <?php /* A real <button>: it is an action, it must be reachable by
             keyboard, and a <span> with a click handler is neither.
             Plain JS rather than Alpine so the chip needs no x-data of its
             own inside a table cell. */ ?>
    <button type="button"
            class="lang-chip lang-chip--<?= e($lbState) ?>"
            title="<?= e($lbLabel . ' — ' . $lbDetail['stateText']) ?>"
            onclick="window.dispatchEvent(new CustomEvent('lang-detail',{detail:<?= e(json_encode($lbDetail, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>}))">
        <?= e($lbLabel) ?><?= $lbState === 'missing' ? '&nbsp;✗' : '&nbsp;✓' ?>
        <?php if ($lbState === 'machine'): ?><i class="bi bi-robot ms-1"></i><?php endif; ?>
    </button>
    <?php else: ?>
    <span class="lang-chip lang-chip--<?= e($lbState) ?>"
          title="<?= e($lbLabel . ' — ' . $lbDetail['stateText']) ?>">
        <?= e($lbLabel) ?>&nbsp;✓
    </span>
    <?php endif; ?>
<?php endforeach; ?>
</span>
