<!DOCTYPE html>
<html lang="<?= e(current_locale() === 'msm' ? 'fil' : current_locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BarangGabay — Barangay Bayogo, Madrid</title>
    <base href="<?= e(base_url()) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- CHANGED: Bitter joins Inter — a sturdy serif for headings against a
         plain sans for reading. The pairing carries more of the page's
         character than any ornament does. -->
    <link href="https://fonts.googleapis.com/css2?family=Bitter:wght@600;700;800&family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- Shared design tokens. Must come BEFORE main.css, which reads them.
         This page links main.css directly rather than going through the
         resident layout, so it needs the tokens named here too. -->
    <link rel="stylesheet" href="<?= e(asset_v('assets/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('assets/css/main.css')) ?>">
    <!-- CHANGED: the system theme now retints tokens.css + main.css's shared
         classes (navbar-premium, card-premium, footer-premium, btn-login...)
         to this same earthy palette everywhere else in the app. Loaded
         before landing.css, which still has the final, page-specific word. -->
    <link rel="stylesheet" href="<?= e(asset_v('assets/css/theme.css')) ?>">
    <!-- landing page's own palette, LAST so it wins on anything it styles
         directly. Scoped under .landing. -->
    <link rel="stylesheet" href="<?= e(asset_v('assets/css/landing.css')) ?>">
</head>
<body class="landing antialiased">
<?php
/*
 * Public landing page (guests). Strings come from lang/*.php 'landing'; the
 * announcement grid is real published content — no invented statistics.
 */
$__locale = current_locale();
$__latest = [];
try {
    $__latest = \App\Models\Announcement::getPublished(3);
} catch (\Throwable $e) {
    error_log('[landing] latest announcements: ' . $e->getMessage());
}
$__contactPhone = trim((string) setting('contact_phone', ''));
$__contactEmail = trim((string) setting('contact_email', ''));
$__location     = trim((string) setting('location_full', 'Barangay Bayogo, Madrid, Surigao del Sur'));
$__langs        = ['en' => 'EN', 'fil' => 'FIL', 'msm' => 'MN'];
?>
<!-- Apply the saved light/dark choice before paint (same key as the app layouts). -->
<script>
    (function () {
        try { var t = localStorage.getItem('bg-theme'); if (t === 'dark' || t === 'light') { document.documentElement.setAttribute('data-theme', t); } } catch (e) {}
    })();
</script>
<style>
    .lp-wrap { max-width: 1200px; margin: 0 auto; padding: 0 1rem; }
    @media (min-width: 640px) { .lp-wrap { padding: 0 1.5rem; } }
    .lp-nav { position: sticky; top: 0; z-index: 40; background: var(--surface-card); border-bottom: 1px solid var(--border); }
    .lp-nav__row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; min-height: 72px; }
    .lp-nav__links { display: none; gap: .25rem; }
    .lp-nav__links a { padding: .5rem .8rem; border-radius: .6rem; font-size: .92rem; font-weight: 600; color: var(--text-secondary); text-decoration: none; }
    .lp-nav__links a:hover, .lp-nav__links a:focus-visible { color: var(--brand-primary); background: var(--brand-primary-light); }
    @media (min-width: 900px) { .lp-nav__links { display: flex; } }
    .lp-lang { display: inline-flex; padding: 3px; border: 1px solid var(--border); border-radius: 999px; background: var(--surface-muted); }
    .lp-lang a { padding: .3rem .7rem; border-radius: 999px; font-size: .78rem; font-weight: 800; color: var(--text-secondary); text-decoration: none; }
    .lp-lang a[aria-current="true"] { background: var(--surface-card); color: var(--brand-primary); box-shadow: var(--shadow-card); }
    .lp-iconbtn { width: 40px; height: 40px; display: inline-grid; place-items: center; border-radius: 999px; border: 1px solid var(--border); background: var(--surface-card); color: var(--text-primary); cursor: pointer; }
    .lp-btn { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; padding: .7rem 1.25rem; min-height: 44px; border-radius: .75rem; font-weight: 700; font-size: .95rem; text-decoration: none; transition: transform .15s ease, box-shadow .15s ease, background-color .15s ease; }
    .lp-btn:hover { transform: translateY(-1px); box-shadow: var(--shadow-lift); }
    .lp-btn--solid { background: var(--action-solid); color: var(--text-on-action); }
    .lp-btn--solid:hover { background: var(--action-solid-hover); color: var(--text-on-action); }
    .lp-btn--ghost { background: var(--surface-card); color: var(--text-primary); border: 1px solid var(--border-strong); }
    .lp-hero { position: relative; overflow: hidden; background: linear-gradient(180deg, var(--surface-page) 0%, var(--brand-primary-light) 100%); }
    .lp-hero__grid { display: grid; gap: 2.5rem; align-items: center; padding: 3.5rem 0 7rem; position: relative; z-index: 2; }
    @media (min-width: 1024px) { .lp-hero__grid { grid-template-columns: 1.1fr .9fr; padding: 5rem 0 9rem; } }
    .lp-hero h1 { font-family: var(--font-heading); font-weight: 800; line-height: 1.05; font-size: clamp(2.3rem, 5vw, 3.9rem); color: var(--text-primary); margin: 0 0 1.25rem; }
    .lp-hero h1 .lp-accent { color: var(--brand-primary); }
    .lp-hero p.lp-sub { font-size: 1.08rem; line-height: 1.7; color: var(--text-secondary); max-width: 36rem; margin: 0 0 2rem; }
    .lp-quote { background: var(--surface-card); border: 1px solid var(--border); border-left: 4px solid var(--motif-gold); border-radius: 1rem; padding: 1.5rem 1.75rem; box-shadow: var(--shadow-card); }
    .lp-quote p { margin: 0; font-family: var(--font-heading); font-style: italic; font-size: 1.35rem; color: var(--text-primary); }
    .lp-quote span { display: block; margin-top: .6rem; color: var(--text-muted); font-size: .9rem; }
    .lp-scape { position: absolute; left: 0; right: 0; bottom: 0; width: 100%; height: 46%; z-index: 1; opacity: .9; }
    .lp-services { position: relative; z-index: 3; margin-top: -4.5rem; display: grid; gap: .9rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    @media (min-width: 768px) { .lp-services { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (min-width: 1100px) { .lp-services { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
    .lp-card { background: var(--surface-card); border: 1px solid var(--border); border-radius: 1rem; box-shadow: var(--shadow-card); transition: transform .15s ease, box-shadow .15s ease; }
    .lp-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lift); }
    .lp-service { padding: 1.1rem 1rem; text-align: center; }
    .lp-service__icon { width: 44px; height: 44px; margin: 0 auto .6rem; display: grid; place-items: center; border-radius: .8rem; background: var(--brand-primary-light); color: var(--brand-primary); font-size: 1.25rem; }
    .lp-service__icon--alert { background: var(--status-danger-bg); color: var(--status-danger); }
    .lp-service__icon--gold { background: rgba(200,153,46,.16); color: var(--motif-gold-ink); }
    .lp-service h3 { margin: 0; font-size: .92rem; font-weight: 800; color: var(--text-primary); }
    .lp-service p { margin: .2rem 0 0; font-size: .8rem; color: var(--text-muted); }
    .lp-section { padding: 3.5rem 0; }
    .lp-section h2 { font-family: var(--font-heading); font-weight: 800; font-size: 1.6rem; color: var(--text-primary); margin: 0; }
    .lp-head { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; }
    .lp-head a { color: var(--brand-primary); font-weight: 700; text-decoration: none; font-size: .92rem; }
    .lp-posts { display: grid; gap: 1.25rem; }
    @media (min-width: 768px) { .lp-posts { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    .lp-post { overflow: hidden; display: flex; flex-direction: column; }
    .lp-post__img { aspect-ratio: 16 / 9; background: linear-gradient(135deg, var(--brand-primary-light), var(--surface-muted)); display: grid; place-items: center; color: var(--brand-primary); font-size: 2rem; }
    .lp-post__img img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .lp-post__body { padding: 1rem 1.1rem 1.2rem; display: flex; flex-direction: column; gap: .4rem; flex: 1; }
    .lp-post__meta { display: flex; align-items: center; gap: .5rem; font-size: .78rem; color: var(--text-muted); }
    .lp-badge { display: inline-flex; align-items: center; gap: .25rem; padding: .15rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 800; }
    .lp-badge--urgent { background: var(--status-danger-bg); color: var(--status-danger); }
    .lp-badge--important { background: var(--status-warning-bg); color: var(--status-warning); }
    .lp-post h3 { margin: 0; font-size: 1.02rem; font-weight: 800; line-height: 1.35; color: var(--text-primary); }
    .lp-post p { margin: 0; font-size: .88rem; line-height: 1.55; color: var(--text-secondary); }
    .lp-post .lp-more { margin-top: auto; padding-top: .4rem; color: var(--brand-primary); font-weight: 700; font-size: .88rem; text-decoration: none; }
    .lp-steps { display: grid; gap: 1rem; }
    @media (min-width: 768px) { .lp-steps { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    .lp-step { padding: 1.4rem; }
    .lp-step__n { width: 36px; height: 36px; display: grid; place-items: center; border-radius: 999px; background: var(--action-solid); color: var(--text-on-action); font-weight: 800; margin-bottom: .75rem; }
    .lp-step h3 { margin: 0 0 .3rem; font-size: 1rem; font-weight: 800; color: var(--text-primary); }
    .lp-step p { margin: 0; font-size: .9rem; color: var(--text-secondary); line-height: 1.55; }
    .lp-empty { padding: 2rem; text-align: center; color: var(--text-muted); }
    .lp-mobile { display: block; }
    @media (min-width: 900px) { .lp-mobile { display: none; } }
    .lp-mobile summary { list-style: none; cursor: pointer; }
    .lp-mobile__panel { position: absolute; right: 1rem; top: 68px; z-index: 50; width: 220px; padding: .6rem; background: var(--surface-card); border: 1px solid var(--border); border-radius: 1rem; box-shadow: var(--shadow-lift); }
    .lp-mobile__panel a { display: block; padding: .6rem .8rem; border-radius: .6rem; color: var(--text-primary); font-weight: 600; text-decoration: none; }
    .lp-mobile__panel a:hover { background: var(--surface-muted); }
    .lp-hide-sm { display: none; }
    @media (min-width: 640px) { .lp-hide-sm { display: inline-flex; } }
    a:focus-visible, button:focus-visible, summary:focus-visible { outline: var(--focus-ring-width, 3px) solid var(--focus-ring); outline-offset: 2px; }
    @media (prefers-reduced-motion: reduce) { .lp-btn, .lp-card { transition: none; } .lp-btn:hover, .lp-card:hover { transform: none; } }
</style>

<header class="lp-nav" id="mainNav">
    <div class="lp-wrap lp-nav__row">
        <?= baranggabay_logo('auto', ['size' => 'medium', 'href' => route('')]) ?>

        <nav class="lp-nav__links" aria-label="Main">
            <a href="#top"><?= e(t('landing.nav_home')) ?></a>
            <a href="#services"><?= e(t('landing.nav_services')) ?></a>
            <a href="#latest"><?= e(t('landing.nav_announcements')) ?></a>
            <a href="#how"><?= e(t('landing.nav_about')) ?></a>
        </nav>

        <div class="d-flex" style="display:flex;align-items:center;gap:.5rem;">
            <div class="lp-lang lp-hide-sm" role="group" aria-label="Language">
                <?php foreach ($__langs as $code => $label): ?>
                    <a href="<?= e(route('set-locale/' . $code)) ?>" aria-current="<?= $__locale === $code ? 'true' : 'false' ?>" lang="<?= $code === 'msm' ? 'mbt' : $code ?>"><?= $label ?></a>
                <?php endforeach; ?>
            </div>
            <button type="button" class="lp-iconbtn" onclick="lpToggleTheme()" aria-label="Toggle dark mode"><i class="bi bi-moon-stars-fill" id="lpThemeIcon"></i></button>
            <a href="<?= e(route('login')) ?>" class="lp-btn lp-btn--ghost lp-hide-sm"><?= e(t('landing.login')) ?></a>
            <a href="<?= e(route('register')) ?>" class="lp-btn lp-btn--solid lp-hide-sm"><?= e(t('landing.register')) ?></a>
            <details class="lp-mobile">
                <summary class="lp-iconbtn" aria-label="Menu"><i class="bi bi-list"></i></summary>
                <div class="lp-mobile__panel">
                    <a href="#services"><?= e(t('landing.nav_services')) ?></a>
                    <a href="#latest"><?= e(t('landing.nav_announcements')) ?></a>
                    <a href="#how"><?= e(t('landing.nav_about')) ?></a>
                    <a href="<?= e(route('login')) ?>"><?= e(t('landing.login')) ?></a>
                    <a href="<?= e(route('register')) ?>"><?= e(t('landing.register')) ?></a>
                    <div class="lp-lang" style="margin-top:.4rem;" role="group" aria-label="Language">
                        <?php foreach ($__langs as $code => $label): ?>
                            <a href="<?= e(route('set-locale/' . $code)) ?>" aria-current="<?= $__locale === $code ? 'true' : 'false' ?>"><?= $label ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>
        </div>
    </div>
</header>

<main id="top">
<section class="lp-hero" aria-labelledby="lpHeroTitle">
    <div class="lp-wrap">
        <div class="lp-hero__grid">
            <div>
                <h1 id="lpHeroTitle">
                    <?= e(t('landing.hero_title_1')) ?><br>
                    <span class="lp-accent"><?= e(t('landing.hero_title_2')) ?></span><br>
                    <span class="lp-accent"><?= e(t('landing.hero_title_3')) ?></span>
                </h1>
                <p class="lp-sub"><?= e(t('landing.hero_sub')) ?></p>
                <div style="display:flex;flex-wrap:wrap;gap:.75rem;">
                    <a href="<?= e(route('announcements')) ?>" class="lp-btn lp-btn--solid"><i class="bi bi-megaphone-fill"></i><?= e(t('landing.cta_announcements')) ?></a>
                    <a href="#services" class="lp-btn lp-btn--ghost"><?= e(t('landing.cta_services')) ?></a>
                </div>
            </div>
            <div>
                <figure class="lp-quote">
                    <p lang="ceb">&ldquo;<?= e(t('landing.quote')) ?>&rdquo;</p>
                    <span><?= e(t('landing.quote_sub')) ?></span>
                </figure>
            </div>
        </div>
    </div>
    <?php /* Flat landscape — ridgelines, river mouth, coconut silhouettes. A
             place, not a people; inline SVG so it needs no image file. */ ?>
    <svg class="lp-scape" viewBox="0 0 1440 420" preserveAspectRatio="xMidYMax slice" aria-hidden="true" focusable="false">
        <path d="M0 250 L180 150 L300 215 L430 120 L560 220 L700 160 L840 235 L980 145 L1120 225 L1260 165 L1440 245 L1440 420 L0 420 Z" fill="#2f6b45" opacity=".45"/>
        <path d="M0 305 L150 235 L290 300 L430 245 L580 310 L730 255 L880 315 L1030 250 L1180 305 L1320 255 L1440 300 L1440 420 L0 420 Z" fill="#1f5135" opacity=".7"/>
        <path d="M690 420 C 705 350, 665 320, 690 300 C 712 282, 700 262, 718 252 C 736 262, 726 284, 744 302 C 768 324, 730 352, 748 420 Z" fill="#7fb7c9" opacity=".55"/>
        <g fill="#163c27" opacity=".85">
            <rect x="196" y="318" width="5" height="102" rx="2"/>
            <path d="M198 322c-30-16-54-12-70 4 22-4 44-2 62 8zM198 322c30-16 54-12 70 4-22-4-44-2-62 8z"/>
            <path d="M198 320c-12-28-8-50 6-64 6 22 6 44 0 62zM198 320c14-26 34-38 54-36-14 16-32 30-48 40z"/>
            <rect x="1258" y="332" width="4" height="88" rx="2"/>
            <path d="M1260 336c-24-13-43-10-56 3 18-3 35-2 50 6zM1260 336c24-13 43-10 56 3-18-3-35-2-50 6z"/>
        </g>
    </svg>
</section>

<div class="lp-wrap" id="services">
    <div class="lp-services">
        <?php foreach ([
            ['bi-megaphone-fill', 's_announce', '', route('announcements')],
            ['bi-exclamation-triangle-fill', 's_alerts', 'alert', route('evacuation')],
            ['bi-translate', 's_lang', '', route('dictionary')],
            ['bi-volume-up-fill', 's_voice', 'gold', route('announcements')],
            ['bi-calendar-event-fill', 's_events', 'gold', route('events')],
            ['bi-file-earmark-text-fill', 's_docs', '', route('documents')],
        ] as [$icon, $key, $tone, $href]): ?>
            <a href="<?= e($href) ?>" class="lp-card lp-service" style="text-decoration:none;">
                <div class="lp-service__icon <?= $tone ? 'lp-service__icon--' . $tone : '' ?>" aria-hidden="true"><i class="bi <?= $icon ?>"></i></div>
                <h3><?= e(t('landing.' . $key)) ?></h3>
                <p><?= e(t('landing.' . $key . '_d')) ?></p>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<section class="lp-section" id="latest" aria-labelledby="lpLatest">
    <div class="lp-wrap">
        <div class="lp-head">
            <h2 id="lpLatest"><?= e(t('landing.latest')) ?></h2>
            <a href="<?= e(route('announcements')) ?>"><?= e(t('landing.view_all')) ?> <i class="bi bi-arrow-right"></i></a>
        </div>
        <?php if (!$__latest): ?>
            <div class="lp-card lp-empty"><?= e(t('landing.no_posts')) ?></div>
        <?php else: ?>
            <div class="lp-posts">
                <?php foreach ($__latest as $a):
                    $aTitle = localised_text($a, 'title');
                    $aBody  = \App\Services\SpokenText::repairJoins(\App\Services\SpokenText::plain(localised_text($a, 'body')));
                    $aUrg   = (string) ($a['urgency'] ?? 'normal');
                    $aImg   = trim((string) ($a['cover_image_url'] ?? ''));
                    $aUrl   = route('announcements/' . $a['slug']);
                ?>
                    <article class="lp-card lp-post">
                        <div class="lp-post__img">
                            <?php if ($aImg !== ''): ?>
                                <img src="<?= e(asset($aImg)) ?>" alt="" loading="lazy" onerror="this.remove()">
                            <?php endif; ?>
                            <i class="bi bi-megaphone" aria-hidden="true"></i>
                        </div>
                        <div class="lp-post__body">
                            <div class="lp-post__meta">
                                <?php if ($aUrg === 'urgent'): ?><span class="lp-badge lp-badge--urgent"><i class="bi bi-exclamation-triangle-fill"></i><?= e(t('landing.urgent')) ?></span><?php endif; ?>
                                <?php if ($aUrg === 'important'): ?><span class="lp-badge lp-badge--important"><?= e(t('landing.important')) ?></span><?php endif; ?>
                                <time datetime="<?= e((string) $a['published_at']) ?>"><?= e(date('M j, Y', strtotime((string) ($a['published_at'] ?? 'now')))) ?></time>
                            </div>
                            <h3><a href="<?= e($aUrl) ?>" style="color:inherit;text-decoration:none;"><?= e($aTitle) ?></a></h3>
                            <p><?= e(mb_strimwidth($aBody, 0, 130, '…')) ?></p>
                            <a href="<?= e($aUrl) ?>" class="lp-more" aria-label="<?= e(t('landing.read_more') . ': ' . $aTitle) ?>"><?= e(t('landing.read_more')) ?> <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="lp-section" id="how" aria-labelledby="lpHow" style="padding-top:0;">
    <div class="lp-wrap">
        <div class="lp-head"><h2 id="lpHow"><?= e(t('landing.how_title')) ?></h2></div>
        <div class="lp-steps">
            <?php foreach (['step1', 'step2', 'step3'] as $i => $k): ?>
                <div class="lp-card lp-step">
                    <div class="lp-step__n"><?= $i + 1 ?></div>
                    <h3><?= e(t('landing.' . $k)) ?></h3>
                    <p><?= e(t('landing.' . $k . '_d')) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
</main>

<footer class="footer-premium">
    <div class="lp-wrap" style="padding-top:3rem;padding-bottom:2rem;">
        <div class="grid gap-10 lg:grid-cols-3">
            <div>
                <div class="flex items-center gap-3 mb-4"><?= baranggabay_logo('dark', ['size' => 'medium', 'href' => route('')]) ?></div>
                <p class="text-sm leading-6 max-w-xs"><?= e(t('landing.footer_about')) ?></p>
            </div>
            <div>
                <p class="font-semibold mb-4"><?= e(t('landing.contact')) ?></p>
                <ul class="space-y-2 text-sm">
                    <li><i class="bi bi-geo-alt-fill me-1"></i><?= e($__location) ?></li>
                    <?php if ($__contactPhone !== ''): ?><li><i class="bi bi-telephone-fill me-1"></i><?= e($__contactPhone) ?></li><?php endif; ?>
                    <?php if ($__contactEmail !== ''): ?><li><i class="bi bi-envelope-fill me-1"></i><?= e($__contactEmail) ?></li><?php endif; ?>
                </ul>
            </div>
            <div>
                <p class="font-semibold mb-4"><?= e(t('landing.links')) ?></p>
                <div class="grid grid-cols-2 gap-x-4">
                    <a href="<?= e(route('login')) ?>" class="footer-link"><?= e(t('landing.login')) ?></a>
                    <a href="<?= e(route('register')) ?>" class="footer-link"><?= e(t('landing.register')) ?></a>
                    <a href="<?= e(route('announcements')) ?>" class="footer-link"><?= e(t('landing.nav_announcements')) ?></a>
                    <a href="<?= e(route('events')) ?>" class="footer-link"><?= e(t('landing.s_events')) ?></a>
                </div>
            </div>
        </div>
        <hr class="footer-divider">
        <p class="l-dedication"><?= e(t('landing.dedication')) ?></p>
        <div class="text-center text-sm footer-copyright">&copy; <?= date('Y') ?> BarangGabay — <?= e($__location) ?></div>
    </div>
</footer>

<script>
    function lpThemeIcon() {
        var dark = document.documentElement.getAttribute('data-theme') === 'dark'
            || (!document.documentElement.getAttribute('data-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
        var i = document.getElementById('lpThemeIcon');
        if (i) { i.className = 'bi ' + (dark ? 'bi-sun-fill' : 'bi-moon-stars-fill'); }
        return dark;
    }
    function lpToggleTheme() {
        var next = lpThemeIcon() ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        try { localStorage.setItem('bg-theme', next); } catch (e) {}
        lpThemeIcon();
    }
    lpThemeIcon();
</script>
</body>
</html>
