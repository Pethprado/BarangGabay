<?php
// Read flash messages before any output (they are cleared on read).
$successMsg = flash('success');
$errorMsg   = flash('error');
$errorKind  = flash('error_kind');
// Set only on a wrong-door rejection: which entry point does admit them.
$errorEntry = flash('error_entry');

/*
 * Entry point. AuthController::showLogin() has already whitelisted ?as= into
 * one of its known keys, so nothing here is ever built from the raw parameter.
 * Defaults are set anyway so the page still renders if it is ever included
 * without them.
 *
 * Whichever door this is, the form below posts to the one POST /login: same
 * CSRF token, same rate limiting, same 2FA. Each door admits only its own
 * roles, and that check runs on the server after the password is verified —
 * there is deliberately no field on this page that could influence it, and the
 * query string can only ever cause a refusal, never grant one.
 */
$entry        = $entry        ?? 'resident';
$entryIcon    = $entryIcon    ?? 'bi-people-fill';
$entryAccent  = $entryAccent  ?? '#7a5c11';
$showRegister = $showRegister ?? true;
$otherEntries = $otherEntries ?? ['staff'];

/*
 * Optional background photograph.
 *
 * Dropped in as public/images/auth-bg.jpg, it replaces the CSS pattern below.
 * It is checked for on disk rather than assumed, so a missing file falls back
 * silently instead of leaving a broken image behind the card.
 *
 * WHAT BELONGS HERE: a photograph or textile from the community itself,
 * used with permission, with the credit recorded in the repository. It must
 * not be a stock "tribal" image — see the note on the pattern below.
 */
$authBgFile = \dirname(__DIR__, 3) . '/public/images/auth-bg.jpg';
$authBgUrl  = is_file($authBgFile) ? asset('images/auth-bg.jpg') : null;

// CHANGED: alert colour now comes from the theme's earthy status palette
// instead of Bootstrap's stock blue/red/grey — same condition logic as
// before, only the resulting class name changed.
?>
<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(t('login.page_title', ['system' => system_name()])) ?></title>
    <base href="<?= e(base_url()) ?>">

    <!-- Applied before first paint so arriving here never flashes the wrong theme. -->
    <script>
        (function () {
            var t = localStorage.getItem('bg-theme');
            if (t === 'dark' || t === 'light') { document.documentElement.setAttribute('data-theme', t); }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Bitter:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- The shared design tokens. Loaded last of the stylesheets so our own
         values win over Bootstrap's, and before the <style> block below,
         which is what reads them. -->
    <link rel="stylesheet" href="<?= e(asset_v('assets/css/tokens.css')) ?>">
    <!-- System theme, loaded after tokens.css so this page's own --brand /
         --ink local names (below) resolve to the earthy palette, and so
         .btn-theme / .alert-theme are available on this page. -->
    <link rel="stylesheet" href="<?= e(asset_v('assets/css/theme.css')) ?>">
    <style>
        /* ════════════════════════════════════════════════════════════════
           LOCAL NAMES FOR THE SHARED TOKENS

           This page used to declare its own palette twice over — once for
           light, once for dark — which is how the rest of the system ended
           up with three slightly different versions of the same grey.

           The colours now live in tokens.css, retinted by theme.css to the
           Manobo-inspired earth palette. What is left here is a set of
           short local names pointing at them, kept for one reason: var() is
           resolved where it is USED, not where it is declared. So --ink
           follows --text-primary into dark mode on its own, and the two
           dark blocks this page used to carry are simply gone — there is
           nothing left in them to restate.
           ════════════════════════════════════════════════════════════════ */
        :root {
            --brand:        var(--brand-primary);
            --brand-dark:   var(--brand-primary-dark);
            --accent:       var(--brand-secondary);

            /* The brand panel keeps the same dark tone in BOTH themes — it is
               the constant, like a letterhead — so these two are not
               overridden anywhere and need no dark counterpart. */
            --indigo-deep:  var(--surface-panel);
            --indigo-mid:   var(--surface-panel-2);

            --surface:      var(--surface-card);
            --surface-soft: var(--surface-input);
            --ink:          var(--text-primary);
            --ink-soft:     var(--text-secondary);
            --ink-faint:    var(--text-muted);
            --line:         var(--border);
            --radius:       var(--radius-lg);
        }

        *, *::before, *::after { box-sizing: border-box; }

        html, body { max-width: 100%; overflow-x: hidden; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            font-family: var(--font-body, 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif);
            background: var(--indigo-deep);
            position: relative;
        }

        /* ════════════════════════════════════════════════════════════════
           BACKGROUND — WOVEN MOTIF, NOT MANOBO DESIGN
           ════════════════════════════════════════════════════════════════
           This is an ABSTRACT geometric pattern: diamonds and chevrons drawn
           with CSS gradients in an earth palette. It is deliberately generic.

           It is NOT Manobo design, is not derived from any Manobo textile,
           and must never be described as such anywhere in this project. A
           government portal serving a Manobo community that decorated itself
           with an invented "tribal-looking" pattern would be appropriating
           the community it claims to serve — worse than a plain background.

           To replace it properly: put a photograph or a photographed textile
           supplied BY the community, WITH permission, at
           public/images/auth-bg.jpg. Record who provided it and on what terms
           in the repository. The PHP above picks the file up automatically.
           ════════════════════════════════════════════════════════════════ */
        .auth-bg {
            position: fixed;
            inset: -10%;
            z-index: 0;
            pointer-events: none;
            background-color: var(--indigo-deep);
            /* CHANGED: the mint/indigo radial accents (leftovers of the old
               blue theme) are now a gold glow and a maroon glow, matching
               the rest of the earth palette. The chevron lattice itself was
               already close to this palette by coincidence and is unchanged. */
            background-image:
                /* chevron band */
                repeating-linear-gradient(135deg,
                    rgba(200,153,46,.10) 0 14px,
                    transparent 14px 38px),
                /* opposing chevron, makes the diamond lattice */
                repeating-linear-gradient(45deg,
                    rgba(163,49,28,.10) 0 14px,
                    transparent 14px 38px),
                /* soft depth so the lattice is not flat */
                radial-gradient(ellipse at 22% 18%, rgba(200,153,46,.14) 0%, transparent 55%),
                radial-gradient(ellipse at 78% 82%, rgba(15,74,42,.50) 0%, transparent 60%),
                linear-gradient(150deg, var(--indigo-deep) 0%, var(--indigo-mid) 62%, #140f0d 100%);
            background-size: 120px 120px, 120px 120px, auto, auto, auto;
            animation: bgDrift var(--motion-drift) linear infinite;
        }

        <?php if ($authBgUrl !== null): ?>
        /* A community-supplied photograph is present, so it takes over. */
        .auth-bg {
            background-image:
                linear-gradient(150deg, rgba(16,36,26,.80), rgba(15,74,42,.55)),
                url('<?= e($authBgUrl) ?>');
            background-size: cover, cover;
            background-position: center, center;
            animation: none;
        }
        <?php endif; ?>

        @keyframes bgDrift {
            from { background-position: 0 0, 0 0, 0 0, 0 0, 0 0; }
            to   { background-position: 240px 240px, -240px 240px, 0 0, 0 0, 0 0; }
        }

        /* A scrim under the card. The motif is low-contrast on purpose, but
           a photograph dropped in later could be anything, and the text on
           top of it still has to clear 4.5:1. */
        .auth-scrim {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background: radial-gradient(ellipse at 50% 50%, rgba(8,18,13,.30) 0%, rgba(8,18,13,.62) 100%);
        }

        /* ════════════════════════════════════════════════════════════════
           THE CARD
           ════════════════════════════════════════════════════════════════ */
        .auth-shell {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 980px;
            display: grid;
            grid-template-columns: 45% 55%;
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-modal);
        }

        /* ── Left: brand panel (dark, woven) ─────────────────────────────── */
        .auth-brand {
            position: relative;
            padding: 2.75rem 2.25rem;
            color: #fff;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background:
                radial-gradient(circle at 80% 0%, rgba(200,153,46,.22) 0%, transparent 55%),
                linear-gradient(165deg, var(--indigo-mid) 0%, var(--indigo-deep) 100%);
            /* Slides in from the left and meets its other half. */
            animation: slideFromLeft var(--motion-panel) var(--ease-out-soft) both;
        }

        /* ADDED: a flat mountain silhouette, per the brief's "mountain
           silhouette" note. Same discipline as landing.css's hero SVG — a
           PLACE, not a people: no figures, no costumes, just ridgelines,
           low-opacity, behind the text. Built with plain SVG paths, not an
           image, so it stays crisp at any panel width. */
        .auth-brand__scape {
            position: absolute;
            left: 0; right: 0; bottom: 0;
            height: 42%;
            z-index: 0;
            opacity: .35;
            pointer-events: none;
        }
        .auth-brand > *:not(.auth-brand__scape) { position: relative; z-index: 1; }

        .auth-brand__logo {
            height: 76px;
            width: auto;
            max-width: 100%;
            object-fit: contain;
            margin-bottom: 1.5rem;
            filter: drop-shadow(0 4px 12px rgba(0,0,0,0.25));
        }
        .auth-brand__fallback {
            width: 62px;
            height: 62px;
            border-radius: 16px;
            background: var(--entry-accent, var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.7rem;
            color: #fff;
            margin-bottom: 1.5rem;
        }

        .auth-brand__eyebrow {
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            /* .78 alpha on this dark panel clears 4.5:1 comfortably. */
            color: rgba(255,255,255,.78);
            margin: 0 0 .5rem;
        }
        .auth-brand__name {
            font-family: var(--font-heading, 'Bitter', Georgia, serif);
            font-size: 1.55rem;
            font-weight: 800;
            line-height: 1.18;
            margin: 0 0 .4rem;
            color: #fff;
        }
        .auth-brand__place {
            font-size: .875rem;
            color: rgba(255,255,255,.82);
            margin: 0;
            line-height: 1.5;
        }

        .auth-brand__rule {
            height: 1px;
            background: rgba(255,255,255,.18);
            margin: 1.6rem 0 1.25rem;
        }

        .auth-feature {
            display: flex;
            align-items: flex-start;
            gap: .8rem;
            margin-bottom: .95rem;
        }
        .auth-feature__tile {
            flex: 0 0 auto;
            width: 30px;
            height: 30px;
            border-radius: 9px;
            background: rgba(255,255,255,.13);
            display: grid;
            place-items: center;
            font-size: .85rem;
            color: #fff;
        }
        .auth-feature__text {
            font-size: .825rem;
            line-height: 1.55;
            color: rgba(255,255,255,.88);
        }

        .auth-brand__foot {
            margin-top: auto;
            padding-top: 1.75rem;
            font-size: .72rem;
            /* .70 is the floor here: 4.6:1 on this panel. */
            color: rgba(255,255,255,.70);
        }

        /* ── Right: form panel (cream, zigzag gold top edge) ─────────────── */
        .auth-form {
            position: relative;
            background: var(--surface);
            padding: 2.75rem 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            animation: slideFromRight var(--motion-panel) var(--ease-out-soft) both;
        }

        /* ADDED: the theme's woven/zigzag divider, used here as the cream
           card's top edge — same technique as .card-theme in theme.css.
           Original abstract geometry, not Manobo design (see theme.css's
           header for the full disclosure). */
        .auth-form::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 6px;
            background-image:
                repeating-linear-gradient(135deg, var(--motif-gold) 0 5px, transparent 5px 14px),
                repeating-linear-gradient(45deg,  var(--action-solid) 0 5px, transparent 5px 14px);
            background-size: 14px 14px, 14px 14px;
        }

        .auth-form__title {
            font-family: var(--font-heading, 'Bitter', Georgia, serif);
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--ink);
            margin: 0 0 .25rem;
        }
        .auth-form__sub {
            font-size: .875rem;
            color: var(--ink-faint);
            margin: 0 0 1.5rem;
        }

        /* ── Panels meeting in the middle ──────────────────────────────── */
        @keyframes slideFromLeft {
            from { opacity: 0; transform: translateX(-42px); }
            to   { opacity: 1; transform: none; }
        }
        @keyframes slideFromRight {
            from { opacity: 0; transform: translateX(42px); }
            to   { opacity: 1; transform: none; }
        }

        /* Fields rise after the card has settled, one after another. The
           delay is on the wrapper, so nothing inside shifts position.

           Each row waits for the panels to finish (--motion-panel) and then
           one more step per row. Written as the arithmetic rather than seven
           hand-typed numbers, so changing the rhythm is one edit and the
           sequence cannot drift out of order. */
        .stagger > * {
            animation: riseIn var(--motion-rise) var(--ease-out) both;
        }
        .stagger > *:nth-child(1) { animation-delay: calc(var(--motion-panel) + 0 * var(--stagger-step)); }
        .stagger > *:nth-child(2) { animation-delay: calc(var(--motion-panel) + 1 * var(--stagger-step)); }
        .stagger > *:nth-child(3) { animation-delay: calc(var(--motion-panel) + 2 * var(--stagger-step)); }
        .stagger > *:nth-child(4) { animation-delay: calc(var(--motion-panel) + 3 * var(--stagger-step)); }
        .stagger > *:nth-child(5) { animation-delay: calc(var(--motion-panel) + 4 * var(--stagger-step)); }
        .stagger > *:nth-child(6) { animation-delay: calc(var(--motion-panel) + 5 * var(--stagger-step)); }
        .stagger > *:nth-child(7) { animation-delay: calc(var(--motion-panel) + 6 * var(--stagger-step)); }

        @keyframes riseIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: none; }
        }

        /* Leaving for another door: the form half slides out, then the
           browser navigates. Added by JS and removed if navigation fails. */
        .auth-form.is-leaving {
            animation: slideOutRight var(--motion-exit) var(--ease-in) both;
        }
        @keyframes slideOutRight {
            from { opacity: 1; transform: none; }
            to   { opacity: 0; transform: translateX(34px); }
        }

        /* ════════════════════════════════════════════════════════════════
           REDUCED MOTION
           Everything above is decoration. Someone who asked their device for
           less motion gets the finished page immediately, in its final
           position — not a slower version of the same movement.
           ════════════════════════════════════════════════════════════════ */
        @media (prefers-reduced-motion: reduce) {
            .auth-bg,
            .auth-brand,
            .auth-form,
            .auth-form.is-leaving,
            .stagger > * {
                animation: none !important;
                opacity: 1 !important;
                transform: none !important;
            }
            .btn-theme-primary:hover { transform: none; }
            * { transition-duration: .01ms !important; }
        }

        /* ── Form controls ─────────────────────────────────────────────── */
        .form-label {
            font-weight: 600;
            font-size: .85rem;
            color: var(--ink-soft);
            margin-bottom: .35rem;
        }

        .input-group-text {
            background: var(--surface-soft);
            border-color: var(--line);
            color: var(--ink-faint);
        }

        .form-control {
            background: var(--surface-soft);
            border-color: var(--line);
            color: var(--ink);
            border-radius: 0 !important;   /* overridden per-group */
            font-size: .9rem;
            padding: .65rem .85rem;
        }
        .form-control:focus {
            border-color: var(--brand);
            background: var(--surface);
            color: var(--ink);
            /* CHANGED: was a hand-written rgba(22,82,240,.14) kept
               deliberately weaker than the shared --focus-ring token,
               "reconciled when the rest of the system adopts one focus
               ring" — theme.css is that reconciliation. */
            box-shadow: 0 0 0 var(--focus-ring-width) var(--focus-ring);
        }
        .form-control::placeholder { color: var(--ink-faint); opacity: 1; }

        .auth-input-group {
            border-radius: var(--radius);
            overflow: hidden;
        }
        .auth-input-group .input-group-text:first-child  { border-radius: var(--radius) 0 0 var(--radius); border-right: none; }
        .auth-input-group .form-control:last-child        { border-radius: 0 var(--radius) var(--radius) 0; }
        .auth-input-group .form-control.has-toggle        { border-radius: 0; border-right: none; }
        .auth-input-group .toggle-pw                      { border-radius: 0 var(--radius) var(--radius) 0; }

        .toggle-pw {
            background: var(--surface-soft);
            border: 1px solid var(--line);
            border-left: none;
            padding: 0 .85rem;
            color: var(--ink-faint);
            font-size: .85rem;
            cursor: pointer;
            transition: color .15s;
        }
        .toggle-pw:hover { color: var(--brand); }
        .toggle-pw:focus-visible { outline: 2px solid var(--brand); outline-offset: -2px; }

        /* REMOVED: the local .btn-login block (background/hover/active/
           focus-visible, ~25 lines). The submit button below now uses the
           shared .btn-theme .btn-theme-primary component from theme.css,
           which already carries the same maroon fill, white text and
           focus ring — this page only adds the full-width layout tweak. */
        .auth-form .btn-theme { width: 100%; }

        .auth-link { color: var(--brand); font-weight: 600; text-decoration: none; }
        .auth-link:hover { color: var(--brand-dark); text-decoration: underline; }
        .auth-link:focus-visible {
            outline: 2px solid var(--brand);
            outline-offset: 2px;
            border-radius: .25rem;
            text-decoration: none;
        }

        .auth-divider {
            display: flex;
            align-items: center;
            gap: .75rem;
            color: var(--ink-faint);
            font-size: .8rem;
            margin: 1.25rem 0;
        }
        .auth-divider::before,
        .auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--line);
        }

        /* ── Entry points ───────────────────────────────────
           Quiet text links, the way a school portal offers a
           "Faculty" door beside the student one. Deliberately not
           buttons: nothing on this card should compete with Sign In.
           The last row here is the link back to the public homepage. */
        .entry-links {
            display: flex;
            flex-direction: column;
            gap: .5rem;
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px solid var(--line);
        }
        .entry-row {
            display: flex;
            align-items: baseline;
            justify-content: center;
            flex-wrap: wrap;          /* wraps instead of overflowing at 360px */
            gap: .35rem;
            font-size: .85rem;
            color: var(--ink-faint);
            text-align: center;
        }
        .entry-row .auth-link {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            padding: .15rem .1rem;    /* keeps the tap target honest */
        }
        .entry-row .auth-link .bi { font-size: .8rem; }

        /* ── Stacking ──────────────────────────────────────────────────── */
        @media (max-width: 767.98px) {
            body { padding: 1rem .75rem; align-items: flex-start; }

            .auth-shell { grid-template-columns: 1fr; max-width: 520px; }

            /* Short brand panel on top: logo and name only. The feature rows
               and the footer are the first thing to go — on a phone the form
               should be reachable without scrolling past a sales pitch. */
            .auth-brand { padding: 1.75rem 1.5rem 1.5rem; }
            .auth-brand__logo     { height: 56px; margin-bottom: 1rem; }
            .auth-brand__fallback { width: 48px; height: 48px; font-size: 1.3rem; margin-bottom: .9rem; }
            .auth-brand__name     { font-size: 1.2rem; }
            .auth-brand__rule,
            .auth-feature,
            .auth-brand__foot     { display: none; }

            .auth-form { padding: 1.75rem 1.5rem 2rem; }

            /* Both halves arrive from below when stacked — sliding sideways
               reads as wrong once they are no longer side by side. */
            .auth-brand { animation-name: riseIn; }
            .auth-form  { animation-name: riseIn; }
        }

        @media (max-width: 380px) {
            .auth-brand { padding: 1.5rem 1.15rem 1.25rem; }
            .auth-form  { padding: 1.5rem 1.15rem 1.75rem; }
            .entry-row  { font-size: .82rem; }
        }
    </style>
</head>
<body style="--entry-accent: <?= e($entryAccent) ?>;">

<div class="auth-bg" aria-hidden="true"></div>
<div class="auth-scrim" aria-hidden="true"></div>

<main class="auth-shell">

    <!-- ══ LEFT: who this is (dark, woven, mountain silhouette) ══════════ -->
    <aside class="auth-brand">

        <!-- ADDED: flat mountain silhouette, see .auth-brand__scape above -->
        <svg class="auth-brand__scape" viewBox="0 0 400 160" preserveAspectRatio="none" aria-hidden="true">
            <path d="M0,160 L0,95 L55,50 L100,90 L150,40 L210,100 L260,60 L320,105 L400,70 L400,160 Z"
                  fill="rgba(255,255,255,.10)"/>
            <path d="M0,160 L0,120 L70,85 L130,120 L190,80 L250,130 L310,95 L400,125 L400,160 Z"
                  fill="rgba(255,255,255,.16)"/>
        </svg>

        <?php
        /*
         * Our own mark. system_logo_url() returns the barangay's uploaded logo
         * when one is set; otherwise the bundled lockup; otherwise the entry
         * icon tile. Three steps down, so the panel is never empty.
         */
        ?>
        <?= baranggabay_logo('dark', ['size' => 'hero', 'href' => null, 'class' => 'auth-brand__logo']) ?>
        <div id="brand-fallback" class="auth-brand__fallback" style="display:none;">
            <i class="bi <?= e($entryIcon) ?>" aria-hidden="true"></i>
        </div>

        <p class="auth-brand__eyebrow"><?= e(t('login.republic')) ?></p>
        <h1 class="auth-brand__name"><?= e(system_name()) ?></h1>
        <p class="auth-brand__place"><?= e(system_location()) ?></p>

        <div class="auth-brand__rule"></div>

        <?php
        // Three things this system actually does. Icons are decorative — the
        // text beside each carries the meaning.
        $authFeatures = [
            ['bi-megaphone-fill', t('login.feature_posts')],
            ['bi-translate',      t('login.feature_languages')],
            ['bi-shield-check',   t('login.feature_secure')],
        ];
        foreach ($authFeatures as [$featIcon, $featText]):
        ?>
        <div class="auth-feature">
            <span class="auth-feature__tile" aria-hidden="true"><i class="bi <?= e($featIcon) ?>"></i></span>
            <span class="auth-feature__text"><?= e($featText) ?></span>
        </div>
        <?php endforeach; ?>

        <p class="auth-brand__foot mb-0"><?= e(system_location()) ?></p>
    </aside>

    <!-- ══ RIGHT: the form (cream, zigzag gold top edge) ═════════════════ -->
    <section class="auth-form" id="auth-form-panel">
        <div class="stagger">

            <div>
                <h2 class="auth-form__title"><?= e(t('login.sign_in')) ?></h2>
                <?php /* The heading is the same on every door; the line under
                         it is what changes. Both come from a whitelisted key,
                         never from the query string itself. */ ?>
                <p class="auth-form__sub"><?= e(t('login.entry_' . $entry . '_sub')) ?></p>
            </div>

            <div>
                <!-- Flash: success (e.g. after email verification) -->
                <?php if ($successMsg): ?>
                <div class="alert-theme alert-theme--success mb-3" role="alert">
                    <i class="bi bi-check-circle-fill mt-1 flex-shrink-0"></i>
                    <span><?= e($successMsg) ?></span>
                </div>
                <?php endif; ?>

                <!-- Flash: error — warning tint for "pending/verification", calmer
                     info tint for a suspended account, danger for everything else.
                     The kind is told to us by whoever set the flash. It used to be
                     guessed by searching the message for English words, which stopped
                     working the moment the message was written in Tagalog (and would have
                     broken again in each translated language). The text sniff is kept only
                     as a fallback for the other places in the app that redirect here
                     without setting a kind. -->
                <?php if ($errorMsg):
                    $isPending  = $errorKind === 'pending'
                               || ($errorKind === null && (stripos($errorMsg, 'pending') !== false
                                                        || stripos($errorMsg, 'verification') !== false));
                    $isSuspended  = $errorKind === 'suspended';
                    // Right password, wrong door. Warning, not danger: nothing went
                    // wrong with the credentials, they are simply on the other page.
                    $isWrongEntry = $errorKind === 'wrong_entry';
                    // CHANGED: alert-success/-warning/-secondary/-danger (Bootstrap's
                    // stock colours) -> the theme's own earthy status tints.
                    $alertClass   = $isPending || $isWrongEntry ? 'alert-theme--warning'
                                  : ($isSuspended ? 'alert-theme--info' : 'alert-theme--danger');
                    $alertIcon    = $isWrongEntry ? 'bi-signpost-split'
                                  : ($isPending   ? 'bi-hourglass-split'
                                  : ($isSuspended ? 'bi-slash-circle' : 'bi-exclamation-circle-fill'));
                ?>
                <div class="alert-theme <?= $alertClass ?> mb-3" role="alert">
                    <i class="bi <?= $alertIcon ?> mt-1 flex-shrink-0"></i>
                    <div>
                        <span><?= e($errorMsg) ?></span>
                        <?php if ($isSuspended): ?>
                        <div class="mt-1" style="font-size:.8rem;">
                            <?= e(t('login.suspended_contact')) ?>
                        </div>
                        <?php endif; ?>

                        <?php /* Send them to the door that does admit them. The entry key
                                 comes from the controller's own whitelist and is only ever
                                 'resident' or 'staff', so this can never disclose the
                                 unadvertised system entry. */ ?>
                        <?php if ($isWrongEntry && in_array($errorEntry, ['resident', 'staff'], true)): ?>
                        <div class="mt-2">
                            <a href="<?= e(route('login') . ($errorEntry === 'resident' ? '' : '?as=' . $errorEntry)) ?>"
                               class="auth-link" style="font-size:.85rem;">
                                <?= e(t('login.go_to_' . $errorEntry)) ?>
                                <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Pending-verification info box (shown when redirected from /pending) -->
                <?php if (!empty($_GET['from']) && $_GET['from'] === 'pending'): ?>
                <div class="alert-theme alert-theme--warning mb-3" role="alert">
                    <i class="bi bi-hourglass-split mt-1 flex-shrink-0"></i>
                    <div>
                        <strong><?= e(t('login.pending_title')) ?></strong>
                        <div style="font-size:.8rem;" class="mt-1">
                            <?= e(t('login.pending_body')) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php
            /*
             * The action carries ?as= so that a FAILED sign-in comes back to the same
             * door instead of silently dropping a staff member onto the resident
             * screen. It is not a field and it is not read as a role: POST /login
             * re-whitelists it purely to rebuild this redirect. The csrf_token below
             * is the only hidden input on this form, deliberately.
             */
            $formAction = route('login') . ($entry === 'resident' ? '' : '?as=' . $entry);
            ?>
            <!-- Login form — the one authentication endpoint, from every entry point -->
            <form method="post" action="<?= e($formAction) ?>"
                  x-data="{ showPw: false }" novalidate>
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <!-- Email -->
                <div class="mb-3">
                    <label for="loginEmail" class="form-label"><?= e(t('login.email_label')) ?></label>
                    <div class="input-group auth-input-group">
                        <span class="input-group-text">
                            <i class="bi bi-envelope" style="font-size:.9rem;"></i>
                        </span>
                        <input type="email" id="loginEmail" name="email"
                               class="form-control"
                               placeholder="you@example.com"
                               value="<?= e(old('email')) ?>"
                               required autocomplete="email">
                    </div>
                </div>

                <!-- Password -->
                <div class="mb-4">
                    <label for="loginPassword" class="form-label"><?= e(t('login.password_label')) ?></label>
                    <div class="input-group auth-input-group">
                        <span class="input-group-text">
                            <i class="bi bi-lock" style="font-size:.9rem;"></i>
                        </span>
                        <input :type="showPw ? 'text' : 'password'"
                               id="loginPassword" name="password"
                               class="form-control has-toggle"
                               placeholder="<?= e(t('login.password_placeholder')) ?>"
                               required autocomplete="current-password">
                        <button type="button" class="toggle-pw"
                                @click="showPw = !showPw"
                                :aria-label="showPw
                                    ? <?= e(json_encode(t('login.hide_password'))) ?>
                                    : <?= e(json_encode(t('login.show_password'))) ?>"
                                :title="showPw
                                    ? <?= e(json_encode(t('login.hide_password'))) ?>
                                    : <?= e(json_encode(t('login.show_password'))) ?>">
                            <i :class="showPw ? 'bi-eye-slash' : 'bi-eye'" class="bi"></i>
                        </button>
                    </div>
                </div>

                <!-- CHANGED: local .btn-login -> shared .btn-theme .btn-theme-primary
                     component (theme.css); .btn-theme-lg matches the old padding. -->
                <button type="submit" class="btn-theme btn-theme-primary btn-theme-lg">
                    <i class="bi bi-box-arrow-in-right me-2"></i><?= e(t('login.submit')) ?>
                </button>
            </form>

            <?php /* "Nakalimutan ang password?" — deliberately NOT a link: this
                     system has no self-service reset flow (see routes/web.php —
                     a lost password is reset by an admin). A link that went
                     nowhere would be worse than saying plainly who to ask. */ ?>
            <p class="text-center mb-0 mt-3" style="font-size:.82rem;color:var(--ink-faint);">
                <i class="bi bi-key me-1" aria-hidden="true"></i><?= e(t('login.forgot_help')) ?>
            </p>

            <?php /* Registration is a resident path — a staff account is created by an
                     admin, never self-served — so the link belongs to the resident
                     door only. */ ?>
            <div>
                <?php if ($showRegister): ?>
                <div class="auth-divider"><?= e(t('login.or')) ?></div>

                <p class="text-center mb-0" style="font-size:.875rem;color:var(--ink-faint);">
                    <?= e(t('login.no_account')) ?>
                    <a href="<?= e(route('register')) ?>" class="auth-link"><?= e(t('login.register_here')) ?></a>
                </p>
                <?php endif; ?>
            </div>

            <!-- ── Entry points, incl. the link back to the public homepage ──
                 Doors, not gates. Each one changes the line above and nothing
                 else; the form posts to the same place whichever you came
                 through. There is no Super Admin link here on purpose — that
                 account signs in from this very form, and advertising it would
                 only tell a stranger which account is worth attacking. -->
            <nav class="entry-links" aria-label="<?= e(t('login.entry_nav_label')) ?>">
                <?php foreach ($otherEntries as $__other): ?>
                <p class="entry-row mb-0">
                    <span><?= e(t('login.entry_' . $__other . '_prompt')) ?></span>
                    <a href="<?= e(route('login') . ($__other === 'resident' ? '' : '?as=' . $__other)) ?>"
                       class="auth-link" data-door>
                        <?= e(t('login.entry_' . $__other . '_link')) ?>
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                </p>
                <?php endforeach; ?>

                <!-- Link back to the homepage (unchanged href — points at the
                     public landing page's overview section). -->
                <p class="entry-row mb-0">
                    <a href="<?= e(route('') . '#features') ?>" class="auth-link">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        <?= e(t('login.about_system')) ?>
                    </a>
                </p>
            </nav>

        </div><!-- /stagger -->
    </section>

</main><!-- /auth-shell -->

<script>
/*
 * Switching doors: slide the form half out, then follow the link.
 *
 * Progressive on purpose. If this script never runs, the links are ordinary
 * anchors and still work — the animation is the only thing lost. The
 * navigation is also fired on a timer rather than on animationend, so a
 * browser that skips the animation entirely still gets there.
 */
(function () {
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced) { return; }

    var panel = document.getElementById('auth-form-panel');
    if (!panel) { return; }

    document.querySelectorAll('a[data-door]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            // Let modified clicks (new tab, download) behave normally.
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) { return; }
            e.preventDefault();
            panel.classList.add('is-leaving');
            window.setTimeout(function () { window.location.href = link.href; }, 200);
        });
    });
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
