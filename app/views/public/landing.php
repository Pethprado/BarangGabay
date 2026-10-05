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
 * Public landing page — mockup layout: photo hero (text left, quote over the
 * photo), five service cards overlapping its foot, Latest Announcements with
 * photos. Announcements are real published posts; no invented statistics.
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
<script>
    (function () {
        try { var t = localStorage.getItem('bg-theme'); if (t === 'dark' || t === 'light') { document.documentElement.setAttribute('data-theme', t); } } catch (e) {}
    })();
</script>
<style>
    .mk { --mk-max: 1240px; font-family: var(--font-body); color: var(--text-primary); background: var(--surface-page); }
    .mk-wrap { max-width: var(--mk-max); margin: 0 auto; padding: 0 1.25rem; }
    /* Nav */
    .mk-nav { position: sticky; top: 0; z-index: 50; background: rgba(255,253,248,.92); backdrop-filter: blur(8px); border-bottom: 1px solid var(--border); }
    :root[data-theme="dark"] .mk-nav { background: rgba(16,36,26,.92); }
    @media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) .mk-nav { background: rgba(16,36,26,.92); } }
    .mk-nav__row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; min-height: 68px; }
    .mk-nav__links { display: none; align-items: center; gap: .15rem; }
    .mk-nav__links a { padding: .45rem .7rem; border-radius: .5rem; font-size: .88rem; font-weight: 600; color: var(--text-primary); text-decoration: none; }
    .mk-nav__links a:hover { color: var(--brand-primary); }
    @media (min-width: 1000px) { .mk-nav__links { display: flex; } }
    .mk-lang { display: inline-flex; padding: 3px; gap: 2px; border-radius: 999px; background: var(--surface-muted); border: 1px solid var(--border); }
    .mk-lang a { padding: .25rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 800; color: var(--text-secondary); text-decoration: none; }
    .mk-lang a[aria-current="true"] { background: var(--surface-card); color: var(--text-primary); box-shadow: var(--shadow-card); }
    .mk-round { width: 38px; height: 38px; display: inline-grid; place-items: center; border-radius: 999px; border: 1px solid var(--border); background: var(--surface-card); color: var(--brand-primary); cursor: pointer; }
    .mk-btn { display: inline-flex; align-items: center; justify-content: center; gap: .45rem; min-height: 44px; padding: .65rem 1.25rem; border-radius: .7rem; font-weight: 700; font-size: .92rem; text-decoration: none; border: 1px solid transparent; transition: transform .15s, box-shadow .15s; }
    .mk-btn:hover { transform: translateY(-1px); box-shadow: var(--shadow-lift); }
    .mk .mk-btn--solid, .mk .mk-btn--solid:visited { background: var(--action-solid); color: #fff !important; }
    .mk .mk-btn--solid:hover { background: var(--action-solid-hover); color: #fff !important; }
    .mk-btn--ghost { background: var(--surface-card); color: var(--text-primary); border-color: var(--action-solid); }
    .mk-hide-sm { display: none; }
    @media (min-width: 640px) { .mk-hide-sm { display: inline-flex; } }
    /* Hero */
    .mk-hero { position: relative; overflow: hidden; background-color: var(--surface-page);
        background-image: linear-gradient(90deg, var(--surface-page) 0%, var(--surface-page) 34%, rgba(250,244,232,.55) 50%, rgba(250,244,232,0) 64%), url('<?= e(asset('assets/images/ui/hero-landscape.jpg')) ?>');
        background-repeat: no-repeat; background-size: auto, auto 100%; background-position: 0 0, right center; }
    :root[data-theme="dark"] .mk-hero { background-image: linear-gradient(90deg, var(--surface-page) 0%, var(--surface-page) 34%, rgba(13,21,17,.55) 50%, rgba(13,21,17,0) 64%), url('<?= e(asset('assets/images/ui/hero-landscape.jpg')) ?>'); }
    .mk-hero__grid { position: relative; display: grid; gap: 1.5rem; align-items: start; padding: 3.5rem 0 7.5rem; }
    @media (min-width: 900px) { .mk-hero__grid { grid-template-columns: 1fr 1fr; padding: 4.5rem 0 8.5rem; } }
    @media (max-width: 899px) { .mk-hero { background-size: auto, cover; background-image: linear-gradient(180deg, rgba(250,244,232,.92) 0%, rgba(250,244,232,.86) 60%, rgba(250,244,232,.6) 100%), url('<?= e(asset('assets/images/ui/hero-landscape.jpg')) ?>'); } }
    .mk-hero h1 { margin: 0 0 1.1rem; font-weight: 900; line-height: 1.04; letter-spacing: -.02em; font-size: clamp(2.3rem, 5.2vw, 3.6rem); color: #10241a; }
    :root[data-theme="dark"] .mk-hero h1 { color: #f3f7f4; }
    .mk-hero h1 .mk-g { color: var(--action-solid); }
    :root[data-theme="dark"] .mk-hero h1 .mk-g { color: #6fcf97; }
    .mk-hero p.mk-sub { margin: 0 0 1.75rem; max-width: 34rem; font-size: 1.05rem; line-height: 1.65; color: var(--text-secondary); }
    .mk-quote { justify-self: end; max-width: 22rem; margin-top: 1.5rem; text-align: right; color: #fff; text-shadow: 0 2px 12px rgba(0,0,0,.45); }
    .mk-quote p { margin: 0; font-style: italic; font-size: 1.45rem; line-height: 1.35; font-weight: 500; }
    .mk-quote span { display: block; margin-top: .6rem; font-size: .85rem; opacity: .95; }
    @media (max-width: 899px) { .mk-quote { display: none; } }
    /* Services */
    .mk-services { position: relative; z-index: 2; margin-top: -5rem; display: grid; gap: .9rem; grid-template-columns: repeat(2, minmax(0,1fr)); }
    @media (min-width: 760px) { .mk-services { grid-template-columns: repeat(3, minmax(0,1fr)); } }
    @media (min-width: 1100px) { .mk-services { grid-template-columns: repeat(5, minmax(0,1fr)); } }
    .mk-card { background: var(--surface-card); border: 1px solid var(--border); border-radius: 1rem; box-shadow: var(--shadow-card); transition: transform .15s, box-shadow .15s; }
    .mk-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lift); }
    .mk-svc { display: block; padding: 1.15rem .9rem; text-align: center; text-decoration: none; }
    .mk-svc__icon { width: 46px; height: 46px; margin: 0 auto .7rem; display: grid; place-items: center; border-radius: 999px; font-size: 1.25rem; background: var(--brand-primary-light); color: var(--action-solid); }
    .mk-svc__icon--red { background: var(--status-danger-bg); color: #e8590c; }
    .mk-svc__icon--orange { background: rgba(232,89,12,.12); color: #e8590c; }
    .mk-svc h3 { margin: 0; font-size: .92rem; font-weight: 800; color: var(--text-primary); }
    .mk-svc p { margin: .25rem 0 0; font-size: .78rem; color: var(--text-muted); }
    /* Posts */
    .mk-section { padding: 3rem 0 3.5rem; }
    .mk-head { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; margin-bottom: 1.1rem; }
    .mk-head h2 { margin: 0; font-size: 1.45rem; font-weight: 800; color: var(--text-primary); }
    .mk-head a { color: var(--action-solid); font-weight: 700; font-size: .9rem; text-decoration: none; }
    :root[data-theme="dark"] .mk-head a { color: #6fcf97; }
    .mk-posts { display: grid; gap: 1.25rem; }
    @media (min-width: 760px) { .mk-posts { grid-template-columns: repeat(3, minmax(0,1fr)); } }
    .mk-post { overflow: hidden; display: flex; flex-direction: column; }
    .mk-post__img { aspect-ratio: 3 / 1.05; background-size: cover; background-position: center; }
    .mk-post__body { padding: .9rem 1rem 1.1rem; display: flex; flex-direction: column; gap: .35rem; flex: 1; }
    .mk-post__meta { display: flex; align-items: center; gap: .5rem; font-size: .76rem; color: var(--text-muted); }
    .mk-badge { display: inline-flex; align-items: center; gap: .25rem; padding: .12rem .5rem; border-radius: 999px; font-size: .7rem; font-weight: 800; background: var(--status-danger-bg); color: var(--status-danger); }
    .mk-post h3 { margin: 0; font-size: 1rem; font-weight: 800; line-height: 1.3; color: var(--text-primary); }
    .mk-post p { margin: 0; font-size: .86rem; line-height: 1.5; color: var(--text-secondary); }
    .mk-post .mk-more { margin-top: auto; padding-top: .35rem; font-size: .86rem; font-weight: 700; color: var(--action-solid); text-decoration: none; }
    :root[data-theme="dark"] .mk-post .mk-more { color: #6fcf97; }
    .mk-mobile { display: block; position: relative; }
    @media (min-width: 1000px) { .mk-mobile { display: none; } }
    .mk-mobile summary { list-style: none; cursor: pointer; }
    .mk-mobile__panel { position: absolute; right: 0; top: 46px; z-index: 60; width: 220px; padding: .5rem; background: var(--surface-card); border: 1px solid var(--border); border-radius: 1rem; box-shadow: var(--shadow-lift); }
    .mk-mobile__panel a { display: block; padding: .55rem .75rem; border-radius: .5rem; color: var(--text-primary); font-weight: 600; text-decoration: none; }
    .mk-mobile__panel a:hover { background: var(--surface-muted); }
    .mk :focus-visible { outline: 3px solid var(--focus-ring); outline-offset: 2px; }
    @media (prefers-reduced-motion: reduce) { .mk-btn, .mk-card { transition: none; } .mk-btn:hover, .mk-card:hover { transform: none; } }
</style>

<div class="mk">
<header class="mk-nav">
    <div class="mk-wrap mk-nav__row">
        <?= baranggabay_logo('auto', ['size' => 'medium', 'href' => route('')]) ?>
        <nav class="mk-nav__links" aria-label="Main">
            <a href="<?= e(route('')) ?>"><?= e(t('landing.nav_home')) ?></a>
            <a href="<?= e(route('announcements')) ?>"><?= e(t('landing.nav_announcements')) ?></a>
            <a href="<?= e(route('events')) ?>"><?= e(t('nav.events')) ?></a>
            <a href="<?= e(route('ordinances')) ?>"><?= e(t('nav.ordinances')) ?></a>
            <a href="<?= e(route('documents')) ?>"><?= e(t('nav.documents')) ?></a>
            <a href="#about"><?= e(t('landing.nav_about')) ?></a>
        </nav>
        <div style="display:flex;align-items:center;gap:.5rem;">
            <div class="mk-lang mk-hide-sm" role="group" aria-label="Language">
                <?php foreach ($__langs as $code => $label): ?>
                    <a href="<?= e(route('set-locale/' . $code)) ?>" aria-current="<?= $__locale === $code ? 'true' : 'false' ?>"><?= $label ?></a>
                <?php endforeach; ?>
            </div>
            <button type="button" class="mk-round" onclick="mkToggleTheme()" aria-label="Toggle dark mode"><i class="bi bi-moon-stars-fill" id="mkThemeIcon"></i></button>
            <a href="<?= e(route('login')) ?>" class="mk-btn mk-btn--solid mk-hide-sm" style="min-height:40px;padding:.5rem 1.2rem;"><?= e(t('landing.login')) ?></a>
            <details class="mk-mobile">
                <summary class="mk-round" aria-label="Menu"><i class="bi bi-list"></i></summary>
                <div class="mk-mobile__panel">
                    <a href="<?= e(route('announcements')) ?>"><?= e(t('landing.nav_announcements')) ?></a>
                    <a href="<?= e(route('events')) ?>"><?= e(t('nav.events')) ?></a>
                    <a href="<?= e(route('ordinances')) ?>"><?= e(t('nav.ordinances')) ?></a>
                    <a href="<?= e(route('documents')) ?>"><?= e(t('nav.documents')) ?></a>
                    <a href="<?= e(route('login')) ?>"><?= e(t('landing.login')) ?></a>
                    <a href="<?= e(route('register')) ?>"><?= e(t('landing.register')) ?></a>
                    <div class="mk-lang" style="margin:.4rem .5rem;">
                        <?php foreach ($__langs as $code => $label): ?>
                            <a href="<?= e(route('set-locale/' . $code)) ?>" aria-current="<?= $__locale === $code ? 'true' : 'false' ?>"><?= $label ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>
        </div>
    </div>
</header>

<main>
<section class="mk-hero" aria-labelledby="mkTitle">
    <div class="mk-wrap">
        <div class="mk-hero__grid">
            <div>
                <h1 id="mkTitle"><?= e(t('landing.hero_title_1')) ?><br><span class="mk-g"><?= e(t('landing.hero_title_2')) ?></span><br><span class="mk-g"><?= e(t('landing.hero_title_3')) ?></span></h1>
                <p class="mk-sub"><?= e(t('landing.hero_sub')) ?></p>
                <div style="display:flex;flex-wrap:wrap;gap:.75rem;">
                    <a href="<?= e(route('announcements')) ?>" class="mk-btn mk-btn--solid"><?= e(t('landing.cta_announcements')) ?></a>
                    <a href="#about" class="mk-btn mk-btn--ghost"><?= e(t('landing.about_btn')) ?></a>
                </div>
            </div>
            <figure class="mk-quote">
                <p lang="ceb">&ldquo;<?= e(t('landing.quote')) ?>&rdquo;</p>
                <span><?= e(t('landing.quote_sub')) ?></span>
            </figure>
        </div>
    </div>
</section>

<div class="mk-wrap">
    <div class="mk-services">
        <?php foreach ([
            ['bi-megaphone-fill', 's_announce', '', route('announcements')],
            ['bi-exclamation-triangle-fill', 's_alerts', 'orange', route('evacuation')],
            ['bi-translate', 's_lang', '', route('dictionary')],
            ['bi-volume-up-fill', 's_voice', '', route('announcements')],
            ['bi-calendar-event-fill', 's_events', 'orange', route('events')],
        ] as [$icon, $key, $tone, $href]): ?>
            <a href="<?= e($href) ?>" class="mk-card mk-svc">
                <div class="mk-svc__icon <?= $tone ? 'mk-svc__icon--' . $tone : '' ?>" aria-hidden="true"><i class="bi <?= $icon ?>"></i></div>
                <h3><?= e(t('landing.' . $key)) ?></h3>
                <p><?= e(t('landing.' . $key . '_d')) ?></p>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<section class="mk-section" aria-labelledby="mkLatest">
    <div class="mk-wrap">
        <div class="mk-head">
            <h2 id="mkLatest"><?= e(t('landing.latest')) ?></h2>
            <a href="<?= e(route('announcements')) ?>"><?= e(t('landing.view_all')) ?> <i class="bi bi-arrow-right"></i></a>
        </div>
        <?php if (!$__latest): ?>
            <div class="mk-card" style="padding:2rem;text-align:center;color:var(--text-muted);"><?= e(t('landing.no_posts')) ?></div>
        <?php else: ?>
            <div class="mk-posts">
                <?php foreach ($__latest as $a):
                    $aTitle = localised_text($a, 'title');
                    $aBody  = \App\Services\SpokenText::repairJoins(\App\Services\SpokenText::plain(localised_text($a, 'body')));
                    $aUrg   = (string) ($a['urgency'] ?? 'normal');
                    $aPh    = post_placeholder_image($a['category'] ?? '', $aUrg);
                    $aImg   = trim((string) ($a['cover_image_url'] ?? ''));
                    $aUrl   = route('announcements/' . $a['slug']);
                ?>
                    <article class="mk-card mk-post">
                        <div class="mk-post__img" role="img" aria-label="" style="background-image:<?= $aImg !== '' ? "url('" . e(asset($aImg)) . "'), " : '' ?>url('<?= e($aPh) ?>');"></div>
                        <div class="mk-post__body">
                            <div class="mk-post__meta">
                                <?php if ($aUrg === 'urgent'): ?><span class="mk-badge"><i class="bi bi-exclamation-triangle-fill"></i><?= e(t('landing.urgent')) ?></span><?php endif; ?>
                                <time datetime="<?= e((string) $a['published_at']) ?>"><?= e(relative_time((string) $a['published_at'])) ?></time>
                            </div>
                            <h3><a href="<?= e($aUrl) ?>" style="color:inherit;text-decoration:none;"><?= e($aTitle) ?></a></h3>
                            <p><?= e(mb_strimwidth($aBody, 0, 95, '…')) ?></p>
                            <a href="<?= e($aUrl) ?>" class="mk-more" aria-label="<?= e(t('landing.read_more') . ': ' . $aTitle) ?>"><?= e(t('landing.read_more')) ?> <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
</main>

<footer class="footer-premium" id="about">
    <div class="mk-wrap" style="padding-top:3rem;padding-bottom:2rem;">
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
                <p class="font-semibold mb-4"><?= e(t('landing.how_title')) ?></p>
                <ol class="space-y-2 text-sm" style="padding-left:1rem;">
                    <li><?= e(t('landing.step1')) ?> — <?= e(t('landing.step1_d')) ?></li>
                    <li><?= e(t('landing.step2')) ?> — <?= e(t('landing.step2_d')) ?></li>
                    <li><?= e(t('landing.step3')) ?> — <?= e(t('landing.step3_d')) ?></li>
                </ol>
            </div>
        </div>
        <hr class="footer-divider">
        <p class="l-dedication"><?= e(t('landing.dedication')) ?></p>
        <div class="text-center text-sm footer-copyright">&copy; <?= date('Y') ?> BarangGabay — <?= e($__location) ?></div>
    </div>
</footer>
</div>

<script>
    function mkDark() {
        var a = document.documentElement.getAttribute('data-theme');
        return a === 'dark' || (!a && window.matchMedia('(prefers-color-scheme: dark)').matches);
    }
    function mkIcon() { var i = document.getElementById('mkThemeIcon'); if (i) { i.className = 'bi ' + (mkDark() ? 'bi-sun-fill' : 'bi-moon-stars-fill'); } }
    function mkToggleTheme() {
        var next = mkDark() ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        try { localStorage.setItem('bg-theme', next); } catch (e) {}
        mkIcon();
    }
    mkIcon();
</script>
</body>
</html>
