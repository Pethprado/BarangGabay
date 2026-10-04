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
$__tnGated = false;

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
    $__tnGated   = $__tnGated || !empty($__tnOne['gated']);
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
<?php
/*
 * A missing translation is generated on the spot instead of serving the
 * source text under the reader's language. The page shows "Translating…",
 * asks /api/content/translate to create and store it (the same runner staff
 * use, saved for everyone after), then reloads once to show it. Only an
 * urgent post's machine translation held for staff review is not
 * generated here — that gate exists so a mistranslated storm warning never
 * reaches residents unchecked.
 */
$__tnType = $__tnType ?? null;
$__tnId   = (int) ($__tnId ?? 0);
$__tnAuto = $__tnMissing && !$__tnGated && $__tnType !== null && $__tnId > 0;
?>
<?php if ($__tnAuto): ?>
<div data-translation-notice="translating" class="post-note" role="status" aria-live="polite"
     id="tnAuto"
     data-type="<?= e((string) $__tnType) ?>" data-id="<?= $__tnId ?>"
     data-locale="<?= e((string) $__tnPick['locale']) ?>">
    <span class="spinner-border spinner-border-sm" aria-hidden="true" id="tnSpin" style="width:1rem;height:1rem;"></span>
    <span id="tnText"><?= e(t('content_lang.translating', ['language' => $__tnLanguage])) ?></span>
    <button type="button" id="tnRetry" class="post-note-shown" style="display:none;text-decoration:underline;background:none;border:0;padding:0;cursor:pointer;">
        <?= e(t('content_lang.translate_retry')) ?>
    </button>
</div>
<script>
(function () {
    var box = document.getElementById('tnAuto');
    if (!box) { return; }
    var key = 'tn:' + box.dataset.type + ':' + box.dataset.id + ':' + box.dataset.locale;
    var failedMsg = <?= json_encode(t('content_lang.translate_failed', ['language' => $__tnLanguage])) ?>;
    var reviewMsg = <?= json_encode(t('content_lang.pending_review', ['language' => $__tnLanguage])) ?>;

    function fail(msg) {
        document.getElementById('tnSpin').style.display = 'none';
        document.getElementById('tnText').textContent = msg;
        document.getElementById('tnRetry').style.display = msg === failedMsg ? 'inline' : 'none';
    }

    function run(manual) {
        // One automatic attempt per post and language per session: never a reload loop.
        var tried = false;
        try { tried = sessionStorage.getItem(key) === '1'; sessionStorage.setItem(key, '1'); } catch (e) {}
        if (tried && !manual) { fail(failedMsg); return; }

        document.getElementById('tnSpin').style.display = '';
        document.getElementById('tnRetry').style.display = 'none';
        var fd = new FormData();
        fd.set('content_type', box.dataset.type);
        fd.set('content_id', box.dataset.id);
        fd.set('locale', box.dataset.locale);
        fd.set('csrf_token', (window.BarangGabay && window.BarangGabay.csrfToken) || '');
        var base = ((window.BarangGabay && window.BarangGabay.baseUrl) || '').replace(/\/$/, '');
        fetch(base + '/api/content/translate', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.status === 'ready') { window.location.reload(); return; }
                if (d.status === 'pending_review') { fail(reviewMsg); return; }
                fail(failedMsg);
            })
            .catch(function () { fail(failedMsg); });
    }

    document.getElementById('tnRetry').addEventListener('click', function () { run(true); });
    run(false);
})();
</script>
<?php elseif ($__tnMissing && $__tnGated): ?>
<p data-translation-notice="pending-review" class="post-note">
    <i class="bi bi-shield-check"></i>
    <span><?= e(t('content_lang.pending_review', ['language' => $__tnLanguage])) ?></span>
    <?php if ($__tnShownName !== null): ?>
    <span class="post-note-shown"><?= e(t('content_lang.showing_instead', ['language' => $__tnShownName])) ?></span>
    <?php endif; ?>
</p>
<?php elseif ($__tnMissing): ?>
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
