<?php
/**
 * _translation-notice.php — "this post has no translation in your language yet".
 *
 * Include with $__tnPick set to the array returned by localised_content().
 * Renders nothing when the text really is in the reader's language.
 *
 * Why show this at all: a resident who taps EN and still sees Filipino has no
 * way to tell whether the switch is broken or the translation simply does not
 * exist. Saying so plainly is the difference between a bug and a known gap.
 */
/*
 * $__tnPicks (plural) takes several parts of the same post — its title and its
 * body — and reports the weakest of them.
 *
 * It matters because they can differ. The Manobo gloss replaces the words it
 * has in the barangay dictionary and leaves the rest, so a post's body can
 * come back glossed while its short title matches nothing and stays Filipino.
 * Reading only the body then labelled the page "machine translated" while the
 * headline above it was untranslated Filipino — technically about the body,
 * and misleading about the page. If any part has no translation, the honest
 * thing to say is that the translation is missing.
 */
$__tnPicks = $__tnPicks ?? null;
if (!is_array($__tnPicks)) {
    $__tnPicks = isset($__tnPick) && is_array($__tnPick) ? [$__tnPick] : null;
}
if (!is_array($__tnPicks) || $__tnPicks === []) {
    return;
}

$__tnPick = $__tnPicks[0];

$__tnLanguage = available_locales()[$__tnPick['locale']] ?? $__tnPick['locale'];
$__tnMissing  = false;
$__tnMachine  = false;

/*
 * Which language is actually on the screen.
 *
 * Saying "there is no Manobo version yet" is half the truth; the other half
 * is what the reader is looking at instead. Without it they are told the
 * text is not Manobo and left to work out for themselves whether it is
 * Filipino or English — on a page whose language button says MN.
 *
 * Taken from the first part that fell back, since every part falls back to
 * the same source language.
 */
$__tnShown = null;

foreach ($__tnPicks as $__tnOne) {
    if (!is_array($__tnOne)) {
        continue;
    }
    // A part whose text is empty says nothing either way — an ordinance with
    // no description must not be reported as an untranslated one.
    if (trim((string) ($__tnOne['text'] ?? '')) === '') {
        continue;
    }
    $__tnMissing = $__tnMissing || !($__tnOne['translated'] ?? true);
    $__tnMachine = $__tnMachine || (bool) ($__tnOne['machine'] ?? false);

    if ($__tnShown === null
        && !($__tnOne['translated'] ?? true)
        && isset($__tnOne['shown_locale'])) {
        $__tnShown = (string) $__tnOne['shown_locale'];
    }
}

// Only name it when it really is a different language from the one asked for.
$__tnShownName = ($__tnShown !== null && $__tnShown !== $__tnPick['locale'])
    ? (available_locales()[$__tnShown] ?? $__tnShown)
    : null;

if (!$__tnMissing && !$__tnMachine) {
    return;                       // a person wrote this — nothing to say
}
?>
<?php /* Rendered as a quiet inline footnote rather than a boxed alert: this
         is a remark about the text, not a warning about the barangay, and a
         full-width amber panel above the article said otherwise. */ ?>
<?php if ($__tnMissing): ?>
<p data-translation-notice="missing"
   class="post-note"
   data-shown-locale="<?= e((string) ($__tnShown ?? '')) ?>">
    <i class="bi bi-info-circle"></i>
    <?php /* Two sentences, not one: what is missing, and what you are
             reading instead. The second half only appears when the text
             really is in another language — on a post with no translation
             AND no source to fall back to there is nothing to name. */ ?>
    <span><?= e(t('content_lang.no_translation', ['language' => $__tnLanguage])) ?></span>
    <?php if ($__tnShownName !== null): ?>
    <span class="post-note-shown">
        <?= e(t('content_lang.showing_instead', ['language' => $__tnShownName])) ?>
    </span>
    <?php endif; ?>
</p>
<?php else: ?>
<!-- Machine-produced: said plainly, because a rough gloss must not carry the
     same authority as a sentence a person wrote. -->
<p data-translation-notice="machine" class="post-note post-note-machine">
    <i class="bi bi-robot"></i>
    <span><?= e(t('content_lang.machine_translation')) ?></span>
</p>
<?php endif; ?>
