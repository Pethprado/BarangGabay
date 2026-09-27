<!DOCTYPE html>
<html lang="fil">
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
<!-- CHANGED: .landing scopes every rule in landing.css to this page. -->
<body class="landing antialiased">

<!-- ── PREMIUM NAVBAR ──────────────────────────────────────────────── -->
<header id="mainNav" class="navbar-premium">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">

        <a href="<?= e(route('')) ?>" class="flex items-center gap-3 hover:opacity-90 transition-opacity flex-shrink-0">
            <?php
            // Same auto light/dark lockup swap as the resident navbar (main.php),
            // and bigger to match it (was hardcoded to a smaller 38px, light-only
            // logo here — inconsistent with the rest of the site). A logo uploaded
            // in Super Admin → Settings replaces both lockups.
            $__logo = system_logo_url();
            ?>
            <img src="<?= e($__logo ?? asset('images/logo-lockup-light.svg')) ?>" alt="BarangGabay"
                 class="logo-lockup <?= $__logo === null ? 'logo-lockup-light' : '' ?>"
                 onerror="this.style.display='none';document.getElementById('landing-logo-fallback').style.display='flex'">
            <?php if ($__logo === null): ?>
            <img src="<?= e(asset('images/logo-lockup-dark.svg')) ?>" alt="BarangGabay" class="logo-lockup logo-lockup-dark"
                 onerror="this.style.display='none';document.getElementById('landing-logo-fallback').style.display='flex'">
            <?php endif; ?>
            <span id="landing-logo-fallback"
                  style="display:none;background:var(--brand-primary);color:#fff;"
                  class="inline-flex h-11 w-11 items-center justify-center rounded-2xl text-lg font-bold ring-2 ring-white">B</span>
        </a>

        <!-- Desktop nav -->
        <div class="hidden items-center gap-1 md:flex">
            <a href="#features"      class="nav-link-premium">Mga Serbisyo</a>
            <a href="#how-it-works"  class="nav-link-premium">Paano ito gumagana</a>
        </div>

        <!-- Desktop CTA buttons -->
        <div class="hidden items-center gap-3 md:flex">
            <!-- CHANGED: the hardcoded blue-700 Tailwind classes are gone;
                 .l-nav-login is outlined in a gold dark enough to carry
                 text (5.69:1 on cream — the brighter gold is 2.38:1 and is
                 a border colour only). -->
            <a href="<?= e(route('login')) ?>" class="l-nav-login">
                <i class="bi bi-box-arrow-in-right"></i> Mag-login
            </a>
            <a href="<?= e(route('register')) ?>" class="btn-login">
                <i class="bi bi-person-plus"></i> Mag-register
            </a>
        </div>

        <!-- Mobile menu -->
        <details class="md:hidden relative">
            <summary class="cursor-pointer list-none rounded-2xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm">Menu</summary>
            <div class="absolute right-0 top-full z-50 mt-2 w-48 space-y-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-xl">
                <!-- CHANGED: blue-700 → the page's maroon, via .landing a -->
                <a href="<?= e(route('login')) ?>"    class="block rounded-xl px-4 py-2.5 text-sm font-medium">Mag-login</a>
                <a href="<?= e(route('register')) ?>" class="block rounded-xl px-4 py-2.5 text-sm font-semibold">Mag-register</a>
            </div>
        </details>
    </div>
</header>

<!-- ── PREMIUM HERO ─────────────────────────────────────────────────── -->
<section class="hero-premium relative overflow-hidden" style="min-height:88vh">
    <?php /* CHANGED: the four floating circles are replaced by a flat
             landscape — ridgelines, a river mouth, coconut and nipa.
             It is a PLACE, not a people: no figures, no costume, nothing
             that could be read as depicting anyone. Inline SVG so it
             scales and needs no image file. The abstract lattice behind it
             is drawn in CSS (.hero-premium::before) — see the note at the
             top of landing.css about what that geometry is and is not. */ ?>
    <svg class="l-hero-scape" viewBox="0 0 1440 420" preserveAspectRatio="xMidYMax slice"
         aria-hidden="true" focusable="false">
        <!-- Far ridge -->
        <path d="M0 250 L180 150 L300 215 L430 120 L560 220 L700 160 L840 235 L980 145
                 L1120 225 L1260 165 L1440 245 L1440 420 L0 420 Z"
              fill="#3a2a22" opacity=".85"/>
        <!-- Near ridge -->
        <path d="M0 305 L150 235 L290 300 L430 245 L580 310 L730 255 L880 315 L1030 250
                 L1180 305 L1320 255 L1440 300 L1440 420 L0 420 Z"
              fill="#2b1f1a"/>
        <!-- River, running out to the sea -->
        <path d="M690 420 C 705 350, 665 320, 690 300 C 712 282, 700 262, 718 252
                 C 736 262, 726 284, 744 302 C 768 324, 730 352, 748 420 Z"
              fill="#2f5d3a" opacity=".55"/>
        <!-- Coconut and nipa, as simple silhouettes -->
        <g fill="#1d1512" opacity=".9">
            <rect x="196" y="318" width="5" height="102" rx="2"/>
            <path d="M198 322c-30-16-54-12-70 4 22-4 44-2 62 8zM198 322c30-16 54-12 70 4-22-4-44-2-62 8z"/>
            <path d="M198 320c-12-28-8-50 6-64 6 22 6 44 0 62zM198 320c14-26 34-38 54-36-14 16-32 30-48 40z"/>

            <rect x="1258" y="332" width="4" height="88" rx="2"/>
            <path d="M1260 336c-24-13-43-10-56 3 18-3 35-2 50 6zM1260 336c24-13 43-10 56 3-18-3-35-2-50 6z"/>
            <path d="M1260 334c-10-22-6-40 5-51 5 18 5 35 0 49z"/>
        </g>
        <!-- Nipa / bamboo texture along the shoreline -->
        <g stroke="#1d1512" stroke-width="3" opacity=".45">
            <path d="M60 420v-34M76 420v-28M92 420v-38M108 420v-26M124 420v-32"/>
            <path d="M1330 420v-30M1346 420v-24M1362 420v-34M1378 420v-22"/>
        </g>
    </svg>

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-24 relative z-10 w-full">
        <div class="grid lg:grid-cols-2 gap-16 items-center">

            <!-- Left: main content -->
            <div>
                <h1 class="hero-title">
                    Maligayang pagdating sa <span class="highlight">BarangGabay</span>
                </h1>

                <p class="hero-subtitle">
                    Ang opisyal na portal ng Barangay Bayogo, Madrid para sa mga anunsyo,
                    kaganapan, at ordinansa — lahat sa isang lugar.
                </p>

                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="<?= e(route('register')) ?>" class="btn-primary-premium">
                        <i class="bi bi-person-plus-fill"></i>
                        Mag-register ngayon
                    </a>
                    <a href="#features" class="btn-secondary-premium">
                        <i class="bi bi-chevron-down"></i>
                        Alamin ang higit pa
                    </a>
                </div>

                <?php /* CHANGED: the mint #5eead4 and the rgba(255,255,255,.6)
                         labels are gone. Both were inline, so neither could
                         be themed; the label colour also measured under AA
                         on the new darker ground. Gold at 9.08:1 and a warm
                         stone at 8.19:1 replace them. */ ?>
                <div class="l-stats">
                    <div>
                        <p class="l-stat__n">100+</p>
                        <p class="l-stat__l">Verified Residents</p>
                    </div>
                    <div>
                        <p class="l-stat__n">50+</p>
                        <p class="l-stat__l">Announcements</p>
                    </div>
                    <div>
                        <p class="l-stat__n">20+</p>
                        <p class="l-stat__l">Ordinances</p>
                    </div>
                </div>
            </div>

            <!-- Right: floating feature cards (desktop only) -->
            <?php /* CHANGED: same four cards, same Filipino text, same order.
                     Each now carries a woven top edge (.l-hero-card::before,
                     drawn in CSS) and reads its colours from the palette
                     instead of white/10 and rgba(255,255,255,.6). */ ?>
            <div class="hidden lg:grid grid-cols-2 gap-4">
                <div class="l-hero-card">
                    <div class="l-hero-card__icon">📢</div>
                    <h3 class="l-hero-card__t">Anunsyo</h3>
                    <p class="l-hero-card__d">Pinakabagong balita mula sa barangay</p>
                </div>
                <div class="l-hero-card mt-8">
                    <div class="l-hero-card__icon">📅</div>
                    <h3 class="l-hero-card__t">Kaganapan</h3>
                    <p class="l-hero-card__d">Mga aktibidad ng komunidad</p>
                </div>
                <div class="l-hero-card">
                    <div class="l-hero-card__icon">📋</div>
                    <h3 class="l-hero-card__t">Ordinansa</h3>
                    <p class="l-hero-card__d">Mga batas at patakaran</p>
                </div>
                <div class="l-hero-card mt-8">
                    <div class="l-hero-card__icon">🤖</div>
                    <h3 class="l-hero-card__t">AI Assistant</h3>
                    <p class="l-hero-card__d">Tanong, sagutan ng AI</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php /* CHANGED: a woven band closes the hero and opens the next section.
         Original abstract geometry drawn in CSS — see landing.css. */ ?>
<div class="l-band" role="presentation"></div>

<!-- ── FEATURES SECTION ─────────────────────────────────────────────── -->
<section id="features" class="py-24 bg-white">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

        <div class="section-header fade-up">
            <span class="eyebrow">Mga Serbisyo</span>
            <h2>Ano ang makukuha mo?</h2>
            <p>Lahat ng kailangan mo para manatiling updated sa iyong barangay.</p>
        </div>

        <div class="grid gap-6 sm:grid-cols-3">

            <?php /* CHANGED: the three cards' top bars, icon tints and link
                     colours were three unrelated brand kits (blue, teal,
                     indigo) with two of them inline. All three top bars are
                     now the woven .card-top-bar from landing.css (maroon +
                     gold, drawn in CSS — not an image), and the tint/link
                     classes are defined once above. Text and links
                     unchanged. */ ?>
            <!-- Card 1: Announcements -->
            <div class="card-premium fade-up fade-up-delay-1">
                <div class="card-top-bar"></div>
                <div class="p-6">
                    <div class="card-icon-wrap green">📢</div>
                    <h3>Mga Anunsyo</h3>
                    <p>Makatanggap ng pinakabagong balita at paalala mula sa barangay nang real-time. Maaaring i-filter ayon sa kategorya at urgency.</p>
                    <a href="<?= e(route('login')) ?>" class="l-card-link mt-4 inline-flex items-center gap-1 text-sm font-semibold transition-colors">
                        Tingnan <i class="bi bi-arrow-right text-xs"></i>
                    </a>
                </div>
            </div>

            <!-- Card 2: Events -->
            <div class="card-premium fade-up fade-up-delay-2">
                <div class="card-top-bar"></div>
                <div class="p-6">
                    <div class="card-icon-wrap gold">📅</div>
                    <h3>Mga Kaganapan</h3>
                    <p>Alamin ang mga susunod na aktibidad at programa ng komunidad kasama ang lokasyon sa Google Maps.</p>
                    <a href="<?= e(route('login')) ?>" class="l-card-link mt-4 inline-flex items-center gap-1 text-sm font-semibold transition-colors">
                        Tingnan <i class="bi bi-arrow-right text-xs"></i>
                    </a>
                </div>
            </div>

            <!-- Card 3: Ordinances -->
            <div class="card-premium fade-up fade-up-delay-3">
                <div class="card-top-bar"></div>
                <div class="p-6">
                    <div class="card-icon-wrap indigo">📋</div>
                    <h3>Mga Ordinansa</h3>
                    <p>Basahin at unawain ang mga patakaran ng barangay sa simpleng paraan gamit ang AI summary na nasa Filipino.</p>
                    <a href="<?= e(route('login')) ?>" class="l-card-link mt-4 inline-flex items-center gap-1 text-sm font-semibold transition-colors">
                        Tingnan <i class="bi bi-arrow-right text-xs"></i>
                    </a>
                </div>
            </div>
        </div>

        <?php /* CHANGED: was a blue gradient (#0a1f5c → #1652f0) with mint
                 (#5eead4) accents — a second, unrelated colour system
                 sitting under the hero's warm palette. Now the same warm
                 dark ground as the hero (.l-ai-panel), with the same
                 gold-night accent (.l-ai-badge, .l-ai-chip). Text, links
                 and the mock chat content are unchanged. */ ?>
        <div class="l-ai-panel mt-12 rounded-3xl overflow-hidden fade-up">
            <div class="grid lg:grid-cols-2">
                <div class="p-10">
                    <span class="l-ai-badge inline-flex items-center gap-2 text-xs font-bold tracking-widest uppercase px-3 py-1 rounded-full mb-5">
                        ✨ Bagong Feature
                    </span>
                    <h3 class="text-2xl font-bold mb-3" style="color:var(--l-on-night);">
                        AI-Powered Ordinance Summarizer
                    </h3>
                    <p class="text-sm leading-relaxed mb-6" style="color:var(--l-on-night-2);">
                        Hindi na kailangang basahin ang buong dokumento. Ang aming AI ay mag-i-summarize ng kahit anong ordinansa sa simpleng Filipino na maiintindihan ng lahat.
                    </p>
                    <a href="<?= e(route('register')) ?>" class="btn-primary-premium">
                        Subukan ngayon
                    </a>
                </div>
                <div class="hidden lg:flex items-center justify-center p-10">
                    <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 w-full border border-white/20">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="l-ai-chip w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold">AI</div>
                            <span class="text-sm font-semibold" style="color:var(--l-on-night);">BarangGabay Assistant</span>
                            <span class="ml-auto text-xs px-2 py-0.5 rounded-full bg-green-500/20 text-green-300">● Online</span>
                        </div>
                        <div class="bg-white/10 rounded-xl p-3 mb-3">
                            <p class="text-xs mb-1" style="color:var(--l-on-night-3);">Ordinance No. 2024-001</p>
                            <p class="text-xs font-semibold mb-1" style="color:var(--l-on-night);">AI Summary:</p>
                            <p class="text-xs leading-relaxed" style="color:var(--l-on-night-2);">Ang ordinansang ito ay nagtatakda ng mga alituntunin sa tamang pagtatapon ng basura sa Barangay Bayogo, kabilang ang mga parusa para sa mga lumalabag...</p>
                        </div>
                        <div class="flex gap-2">
                            <div class="flex-1 bg-white/10 rounded-xl px-3 py-2 text-xs" style="color:var(--l-on-night-3);">Tanungin ang AI...</div>
                            <button class="l-ai-chip w-8 h-8 rounded-xl flex items-center justify-center text-sm font-bold">→</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<?php /* CHANGED: the section's #eff4ff blue-tint background is now cream,
         and each numbered circle's gradient (blue / teal / indigo, each
         inline) is replaced by .l-step-badge and its two modifiers —
         maroon → green → gold-ink, all measured with white text. */ ?>
<!-- ── HOW IT WORKS ──────────────────────────────────────────────────── -->
<section id="how-it-works" class="py-24">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

        <div class="section-header fade-up">
            <span class="eyebrow">Proseso</span>
            <h2>Paano ito gumagana?</h2>
            <p>Tatlong simpleng hakbang para makapagsimula.</p>
        </div>

        <div class="grid gap-8 sm:grid-cols-3">

            <!-- Step 1 -->
            <div class="relative text-center fade-up fade-up-delay-1">
                <div class="l-step-badge mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl shadow-lg">
                    <span class="text-2xl font-bold text-white" style="font-family:'Inter', sans-serif">1</span>
                </div>
                <div class="rounded-2xl p-6 shadow-sm border hover:shadow-md transition-all hover:-translate-y-1" style="background:var(--l-card);border-color:var(--l-line);">
                    <div class="text-3xl mb-3">📝</div>
                    <h3 class="mb-2 text-base font-bold" style="color:var(--l-ink);">Mag-register</h3>
                    <p class="text-sm leading-relaxed" style="color:var(--l-ink-soft);">
                        Gumawa ng account gamit ang iyong valid ID para mapatunayan ang iyong pagka-residente.
                    </p>
                </div>
            </div>

            <!-- Step 2 -->
            <div class="relative text-center fade-up fade-up-delay-2">
                <div class="l-step-badge l-step-badge--2 mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl shadow-lg">
                    <span class="text-2xl font-bold text-white" style="font-family:'Inter', sans-serif">2</span>
                </div>
                <div class="rounded-2xl p-6 shadow-sm border hover:shadow-md transition-all hover:-translate-y-1" style="background:var(--l-card);border-color:var(--l-line);">
                    <div class="text-3xl mb-3">⏳</div>
                    <h3 class="mb-2 text-base font-bold" style="color:var(--l-ink);">Hintayin ang Pag-apruba</h3>
                    <p class="text-sm leading-relaxed" style="color:var(--l-ink-soft);">
                        I-verify ng barangay staff ang iyong account. Makakatanggap ka ng email pagkatapos.
                    </p>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="relative text-center fade-up fade-up-delay-3">
                <div class="l-step-badge l-step-badge--3 mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl shadow-lg">
                    <span class="text-2xl font-bold text-white" style="font-family:'Inter', sans-serif">3</span>
                </div>
                <div class="rounded-2xl p-6 shadow-sm border hover:shadow-md transition-all hover:-translate-y-1" style="background:var(--l-card);border-color:var(--l-line);">
                    <div class="text-3xl mb-3">🎉</div>
                    <h3 class="mb-2 text-base font-bold" style="color:var(--l-ink);">I-access ang Portal</h3>
                    <p class="text-sm leading-relaxed" style="color:var(--l-ink-soft);">
                        Basahin ang lahat ng impormasyon ng barangay at gamitin ang AI assistant nang libre.
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-14 text-center fade-up">
            <a href="<?= e(route('register')) ?>" class="btn-primary-premium inline-flex">
                <i class="bi bi-arrow-right-circle-fill text-base"></i>
                Simulan ang iyong registration
            </a>
            <?php /* CHANGED: text-blue-700 → the page's default link colour
                     (.landing a is maroon; see landing.css). */ ?>
            <p class="mt-4 text-sm" style="color:var(--l-ink-soft);">
                Mayroon nang account?
                <a href="<?= e(route('login')) ?>" class="font-semibold hover:underline">Mag-login dito</a>
            </p>
        </div>
    </div>
</section>

<?php /* ADDED: a second divider, cream-ground into the dark footer. The
         brief asked for these as thin dividers, card borders AND a hero
         background — one band after the hero was not "dividers" (plural).
         Same original abstract geometry, on cream this time so the seam
         reads as paper meeting the footer rather than a stripe. */ ?>
<div class="l-band l-band--light" role="presentation"></div>

<!-- ── PREMIUM FOOTER ────────────────────────────────────────────────── -->
<footer class="footer-premium">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <?php /* CHANGED: the two inline rgba(255,255,255,…) text colours are
                 gone — they overrode the .footer-premium p/li rule in
                 landing.css by sitting on the style attribute, and one of
                 them (.35 alpha) is the copyright line below, which
                 main.css's own comment already flags as measuring 3.2:1 on
                 a dark footer and fixed with a .footer-copyright class
                 (~5.9:1) — a fix that existed in the stylesheet but was
                 never wired into this markup. Both are removed here so the
                 classes in landing.css (and that existing fix) apply. */ ?>
        <div class="grid gap-10 lg:grid-cols-3">
            <div>
                <div class="flex items-center gap-3 mb-4">
                    <img src="<?= e(asset('images/logo-lockup-dark.svg')) ?>" alt="BarangGabay" style="height:34px;width:auto;display:block;">
                </div>
                <p class="text-sm leading-6 max-w-xs">
                    Opisyal na portal ng Barangay Bayogo, Madrid. Serbisyo para sa residente, balita para sa komunidad.
                </p>
            </div>
            <div>
                <p class="font-semibold mb-4">Makipag-ugnayan</p>
                <ul class="space-y-2 text-sm">
                    <li>📍 Barangay Hall, Barangay Bayogo, Madrid</li>
                    <li>📞 Telepono: (0999) 123-4567</li>
                    <li>✉️ Email: info@baranggabay.ph</li>
                </ul>
            </div>
            <div>
                <p class="font-semibold mb-4">Mabilis na Link</p>
                <div class="grid grid-cols-2 gap-x-4">
                    <a href="<?= e(route('login')) ?>"    class="footer-link">Login</a>
                    <a href="<?= e(route('register')) ?>" class="footer-link">Register</a>
                </div>
            </div>
        </div>
        <hr class="footer-divider">

        <?php /* ADDED: the dedication line the brief asked for. Plain text,
                 no emblem or "tribal" graphic beside it — the geometry used
                 across this page is original abstract work (see the note
                 at the top of landing.css) and is never presented as
                 belonging to the Manobo community, so this line honours
                 them without implying it depicts their design. */ ?>
        <p class="l-dedication">
            Pinararangalan ang kultura ng mga Manobo at ang komunidad ng Barangay Bayogo.
        </p>

        <div class="text-center text-sm footer-copyright">
            &copy; <?= date('Y') ?> BarangGabay — Barangay Bayogo, Madrid · Surigao del Sur
        </div>
    </div>
</footer>

<!-- Scroll to top button -->
<button id="scrollTop" onclick="scrollToTop()" title="Back to top">↑</button>

<script>
    // Navbar scroll glass effect
    const nav = document.getElementById('mainNav');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            nav?.classList.add('scrolled');
            document.getElementById('scrollTop')?.classList.add('show');
        } else {
            nav?.classList.remove('scrolled');
            document.getElementById('scrollTop')?.classList.remove('show');
        }
    }, { passive: true });

    // Scroll to top
    function scrollToTop() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Fade-up scroll animation observer
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) entry.target.classList.add('visible');
            });
        },
        { threshold: 0.1 }
    );
    document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));

    // Page transition
    document.body.classList.add('page-transition');
</script>
</body>
</html>