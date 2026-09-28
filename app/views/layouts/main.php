<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(system_name()) ?></title>
    <base href="<?= e(base_url()) ?>">
    <!-- Applied before first paint so switching pages never flashes the wrong theme. -->
    <script>
        (function () {
            var t = localStorage.getItem('bg-theme');
            if (t === 'dark' || t === 'light') { document.documentElement.setAttribute('data-theme', t); }
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Bitter:wght@600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- Shared design tokens. Must come BEFORE main.css, which reads them. -->
    <link rel="stylesheet" href="<?= e(asset_v('assets/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('assets/css/main.css')) ?>">
    <!-- System theme (Manobo-inspired earth palette) — loaded LAST so its
         overrides of tokens.css/main.css win the cascade. -->
    <link rel="stylesheet" href="<?= e(asset_v('assets/css/theme.css')) ?>">
    <style>
        body {
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            background: var(--surface-page);
            color: var(--text-primary);
        }
        .content-card {
            background: var(--surface-card);
            box-shadow: 0 32px 80px rgba(11, 22, 60, 0.08);
        }
        .page-heading { display: grid; gap: 0.5rem; }
        .page-heading h1 {
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            font-size: clamp(1.75rem, 2.5vw, 2.75rem);
            font-weight: 800;
            letter-spacing: -0.01em;
            line-height: 1.05;
        }
    </style>
    <!-- Favicon -->
    <link rel="icon" href="<?= e(asset('images/logo-icon.svg')) ?>" type="image/svg+xml">
    <link rel="alternate icon" href="<?= e(asset('favicon.ico')) ?>">
</head>
<body class="text-slate-900 antialiased">

    <!-- ── PREMIUM NAVBAR ─────────────────────────────────────────── -->
    <header id="mainNav" class="navbar-premium">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">

            <!-- Logo -->
            <div class="flex items-center gap-3 flex-shrink-0">
                <?= baranggabay_logo('auto', ['size' => 'medium', 'href' => route('')]) ?>
            </div>

            <!-- Desktop nav links -->
            <nav class="hidden items-center gap-1 md:flex">
                <a href="<?= e(route('')) ?>"              class="nav-link-premium"><?= e(t('nav.home')) ?></a>
                <a href="<?= e(route('announcements')) ?>" class="nav-link-premium"><?= e(t('nav.announcements')) ?></a>
                <a href="<?= e(route('events')) ?>"        class="nav-link-premium"><?= e(t('nav.events')) ?></a>
                <a href="<?= e(route('ordinances')) ?>"    class="nav-link-premium"><?= e(t('nav.ordinances')) ?></a>
                <?php if (!empty($_SESSION['user_id'])): ?>
                <a href="<?= e(route('documents')) ?>"     class="nav-link-premium"><?= e(t('nav.documents')) ?></a>
                <?php /* Was in the mobile menu only, so on a laptop there was
                         no way to reach the evacuation centres at all unless an
                         advisory happened to be live. Knowing where to go is
                         most useful BEFORE the storm, which is exactly when
                         nothing is prompting you to look. */ ?>
                <a href="<?= e(route('evacuation')) ?>"    class="nav-link-premium"><?= e(t('nav.evacuation')) ?></a>
                <a href="<?= e(route('manobo')) ?>"        class="nav-link-premium"><?= e(t('nav.dictionary')) ?></a>
                <a href="<?= e(route('feedback')) ?>"      class="nav-link-premium"><?= e(t('nav.feedback')) ?></a>
                <?php endif; ?>
            </nav>

            <!-- Language switch — first-class header element, not buried in a menu -->
            <?php $__curLocale = current_locale(); ?>
            <div class="hidden items-center gap-0.5 rounded-full border border-slate-200 bg-slate-50 p-0.5 lg:flex" role="group" aria-label="<?= e(t('lang.switch_label')) ?>">
                <?php foreach (available_locales() as $__code => $__label): ?>
                <a href="<?= e(route('set-locale/' . $__code)) ?>"
                   class="rounded-full px-2.5 py-1 text-xs font-bold transition-colors <?= $__curLocale === $__code ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' ?>"
                   title="<?= e($__label) ?>"
                   <?= $__curLocale === $__code ? 'aria-current="true"' : '' ?>>
                    <?= e(locale_short_code($__code)) ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Desktop right side -->
            <div class="hidden items-center gap-3 md:flex">

                <!-- Dark / light mode toggle -->
                <button type="button" class="bell-btn" title="<?= e(t('theme.toggle')) ?>" aria-label="<?= e(t('theme.toggle')) ?>" onclick="window.toggleBgTheme()">
                    <i class="bi theme-toggle-icon"></i>
                </button>

                <?php if (!empty($_SESSION['user_id'])): ?>

                <!-- Notification bell with dropdown -->
                <div x-data="notifDropdown()" @click.outside="open = false" class="relative">
                    <button type="button"
                            @click="toggle()"
                            class="bell-btn"
                            title="<?= e(t('nav.notifications')) ?>">
                        <i class="bi bi-bell text-base"></i>
                        <span id="notif-badge"
                              style="display:none;"
                              class="bell-badge"></span>
                    </button>

                    <!-- Notification dropdown panel -->
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                         x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                         class="absolute right-0 top-full z-50 mt-2 w-80 origin-top-right rounded-2xl border border-slate-200 bg-white shadow-2xl"
                         style="display:none;">

                        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                            <h3 class="text-sm font-semibold text-slate-900"><?= e(t('notif_dropdown.title')) ?></h3>
                            <button @click="markAllRead()"
                                    class="text-xs font-medium text-blue-700 hover:underline">
                                <?= e(t('notif_dropdown.mark_all_read')) ?>
                            </button>
                        </div>

                        <div class="max-h-72 overflow-y-auto divide-y divide-slate-50">
                            <template x-if="loading">
                                <div class="py-8 text-center">
                                    <p class="text-sm text-slate-400"><?= e(t('notif_dropdown.loading')) ?></p>
                                </div>
                            </template>
                            <template x-if="!loading && notifications.length === 0">
                                <div class="py-8 text-center">
                                    <i class="bi bi-bell-slash text-2xl text-slate-300 block mb-2"></i>
                                    <p class="text-sm text-slate-400"><?= e(t('notif_dropdown.empty')) ?></p>
                                </div>
                            </template>
                            <template x-for="n in notifications" :key="n.id">
                                <button type="button"
                                        @click="markOneAndGo(n)"
                                        class="w-full text-left px-4 py-3 hover:bg-slate-50 transition-colors">
                                    <div class="flex items-start gap-2.5">
                                        <?php /* Read vs. unread is carried by the dot colour alone —
                                                 dimming the whole row with opacity used to drop read
                                                 notifications below AA contrast in dark mode. */ ?>
                                        <div class="flex-shrink-0 mt-1.5 w-2 h-2 rounded-full"
                                             :class="n.is_read == '1' ? 'bg-slate-200' : 'bg-blue-500'"></div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-xs font-semibold text-slate-900 line-clamp-1" x-text="n.title"></p>
                                            <p class="text-xs text-slate-500 line-clamp-2 mt-0.5" x-text="n.message"></p>
                                            <p class="text-[10px] text-slate-400 mt-1" x-text="n.time_ago"></p>
                                        </div>
                                    </div>
                                </button>
                            </template>
                        </div>

                        <div class="border-t border-slate-100 px-4 py-2.5 text-center">
                            <a href="<?= e(route('notifications')) ?>"
                               class="text-xs font-semibold text-blue-700 hover:underline">
                                <?= e(t('notif_dropdown.view_all')) ?> <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div><!-- /notif dropdown -->

                <!-- User dropdown menu -->
                <div class="relative" id="userMenu">
                    <button onclick="toggleUserMenu()"
                            class="flex items-center gap-2 px-3 py-2 rounded-xl hover:bg-slate-100 transition-all">
                        <div class="w-8 h-8 rounded-full bg-blue-700 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                            <?= e(strtoupper(mb_substr($_SESSION['full_name'] ?? 'U', 0, 1, 'UTF-8'))) ?>
                        </div>
                        <span class="hidden sm:block text-sm font-semibold text-gray-700 max-w-[100px] truncate">
                            <?= e(explode(' ', $_SESSION['full_name'] ?? 'User')[0]) ?>
                        </span>
                        <span class="text-gray-400 text-xs">▾</span>
                    </button>

                    <!-- Dropdown -->
                    <div id="userDropdown"
                         class="hidden absolute right-0 mt-2 w-52 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50">
                        <div class="px-4 py-2.5 border-b border-gray-100 mb-1">
                            <p class="font-semibold text-gray-800 text-sm truncate"><?= e($_SESSION['full_name'] ?? '') ?></p>
                            <p class="text-xs text-gray-500 capitalize"><?= e($_SESSION['role'] ?? 'resident') ?></p>
                        </div>
                        <a href="<?= e(route('profile')) ?>"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                            <i class="bi bi-person text-sm text-blue-700"></i> <?= e(t('nav.profile')) ?>
                        </a>
                        <a href="<?= e(route('notifications')) ?>"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                            <i class="bi bi-bell text-sm text-blue-700"></i> <?= e(t('nav.notifications')) ?>
                        </a>
                        <div class="border-t border-gray-100 mt-1 pt-1">
                            <a href="<?= e(route('logout')) ?>"
                               class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 w-full transition-colors">
                                <i class="bi bi-box-arrow-right text-sm"></i> <?= e(t('nav.logout')) ?>
                            </a>
                        </div>
                    </div>
                </div>

                <?php else: ?>
                <a href="<?= e(route('login')) ?>" class="btn-login">
                    <?= e(t('nav.signin')) ?>
                </a>
                <?php endif; ?>
            </div><!-- /desktop right -->

            <!-- Mobile hamburger -->
            <details class="md:hidden relative">
                <summary class="cursor-pointer list-none rounded-2xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm"><?= e(t('nav.menu')) ?></summary>
                <div class="absolute right-0 top-full mt-2 w-56 space-y-1 rounded-3xl bg-white p-4 shadow-xl border border-slate-100 z-50">
                    <button type="button" onclick="window.toggleBgTheme()"
                            class="flex w-full items-center gap-2 rounded-2xl px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        <i class="bi theme-toggle-icon"></i> <?= e(t('theme.toggle')) ?>
                    </button>
                    <!-- Language switch (mobile) -->
                    <div class="flex items-center gap-1 rounded-2xl px-4 py-2">
                        <span class="text-xs font-semibold text-slate-400 me-1"><?= e(t('lang.switch_label')) ?>:</span>
                        <?php foreach (available_locales() as $__code => $__label): ?>
                        <a href="<?= e(route('set-locale/' . $__code)) ?>"
                           class="rounded-full px-2 py-1 text-xs font-bold <?= $__curLocale === $__code ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600' ?>">
                            <?= e(locale_short_code($__code)) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <a href="<?= e(route('')) ?>"              class="block rounded-2xl px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50">🏠 <?= e(t('nav.home')) ?></a>
                    <a href="<?= e(route('announcements')) ?>" class="block rounded-2xl px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50">📢 <?= e(t('nav.announcements')) ?></a>
                    <a href="<?= e(route('events')) ?>"        class="block rounded-2xl px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50">📅 <?= e(t('nav.events')) ?></a>
                    <a href="<?= e(route('ordinances')) ?>"    class="block rounded-2xl px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50">📋 <?= e(t('nav.ordinances')) ?></a>
                    <?php if (!empty($_SESSION['user_id'])): ?>
                    <a href="<?= e(route('documents')) ?>"     class="block rounded-2xl px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50">📄 <?= e(t('nav.documents')) ?></a>
                    <a href="<?= e(route('evacuation')) ?>"    class="block rounded-2xl px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50">🏫 <?= e(t('nav.evacuation')) ?></a>
                    <a href="<?= e(route('manobo')) ?>"        class="block rounded-2xl px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50">📖 <?= e(t('nav.dictionary')) ?></a>
                    <a href="<?= e(route('feedback')) ?>"      class="block rounded-2xl px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50">💬 <?= e(t('nav.feedback')) ?></a>
                    <a href="<?= e(route('notifications')) ?>" class="block rounded-2xl px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        🔔 <?= e(t('nav.notifications')) ?> <span id="notif-badge-mobile" class="font-bold text-red-600"></span>
                    </a>
                    <a href="<?= e(route('profile')) ?>"       class="block rounded-2xl px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50">👤 <?= e(t('nav.profile')) ?></a>
                    <a href="<?= e(route('logout')) ?>"         class="block rounded-2xl px-4 py-3 text-sm font-medium text-red-600 hover:bg-red-50">🚪 <?= e(t('nav.logout')) ?></a>
                    <?php else: ?>
                    <a href="<?= e(route('login')) ?>"          class="block rounded-2xl px-4 py-3 text-sm font-semibold text-blue-700 hover:bg-blue-50"><?= e(t('nav.signin')) ?></a>
                    <?php endif; ?>
                </div>
            </details>
        </div>
    </header>

    <!-- ── OPTIONAL HERO ──────────────────────────────────────────── -->
    <?php if (!empty($showHero)): ?>
    <section class="hero-premium relative overflow-hidden">
        <div class="hero-circle" style="width:450px;height:450px;top:-120px;right:-80px;animation-delay:0s"></div>
        <div class="hero-circle" style="width:280px;height:280px;bottom:-60px;left:8%;animation-delay:3s"></div>
        <div class="hero-circle" style="width:160px;height:160px;top:28%;right:22%;animation-delay:6s"></div>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-20 relative z-10 w-full">
            <div class="max-w-3xl rounded-[2rem] border border-white/15 bg-white/10 p-8 backdrop-blur-xl sm:p-10">
                <h1 class="hero-title"><?= e(t('home_hero.title')) ?></h1>
                <p class="hero-subtitle"><?= e(t('home_hero.subtitle')) ?></p>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <a href="<?= e(route('announcements')) ?>" class="btn-primary-premium">📢 <?= e(t('home_hero.cta_announcements')) ?></a>
                    <a href="<?= e(route('ordinances')) ?>"    class="btn-secondary-premium">📋 <?= e(t('home_hero.cta_ordinances')) ?></a>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ── MAIN CONTENT ───────────────────────────────────────────── -->
    <main class="mx-auto max-w-7xl px-4 pb-16 pt-10 sm:px-6 lg:px-8">
        <div class="<?= !empty($showHero) ? '-mt-10' : 'mt-0' ?> rounded-[2rem] bg-white p-6 shadow-2xl shadow-slate-900/5 ring-1 ring-slate-200 sm:p-10 content-card">
            <?php require __DIR__ . '/../shared/_flash.php'; ?>
            <?= $content ?? '' ?>
        </div>
    </main>

    <!-- ── PREMIUM FOOTER ─────────────────────────────────────────── -->
    <footer class="footer-premium">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-10 lg:grid-cols-3">
                <div>
                    <div class="flex items-center gap-3 mb-4">
                        <?= baranggabay_logo('dark', ['size' => 'medium', 'href' => route('')]) ?>
                    </div>
                    <p class="text-sm leading-6 max-w-xs" style="color:rgba(255,255,255,0.55)">
                        <?= e(t('footer.tagline')) ?>
                    </p>
                </div>
                <div>
                    <p class="font-semibold text-white mb-4"><?= e(t('footer.contact_title')) ?></p>
                    <ul class="space-y-2 text-sm" style="color:rgba(255,255,255,0.55)">
                        <li>📍 <?= e(t('footer.address')) ?></li>
                        <li>📞 <?= e(t('footer.phone')) ?></li>
                        <li>✉️ <?= e(t('footer.email_label')) ?></li>
                    </ul>
                </div>
                <div>
                    <p class="font-semibold text-white mb-4"><?= e(t('footer.quicklinks_title')) ?></p>
                    <div class="grid grid-cols-2 gap-x-4">
                        <a href="<?= e(route('announcements')) ?>" class="footer-link"><?= e(t('nav.announcements')) ?></a>
                        <a href="<?= e(route('events')) ?>"        class="footer-link"><?= e(t('nav.events')) ?></a>
                        <a href="<?= e(route('ordinances')) ?>"    class="footer-link"><?= e(t('nav.ordinances')) ?></a>
                        <a href="<?= e(route('login')) ?>"         class="footer-link"><?= e(t('nav.signin')) ?></a>
                    </div>
                </div>
            </div>
            <hr class="footer-divider">
            <div class="text-center text-sm footer-copyright">
                <?= e(t('footer.copyright', ['year' => date('Y')])) ?>
            </div>
        </div>
    </footer>

    <!-- Scroll to top button -->
    <button id="scrollTop" onclick="scrollToTop()" title="<?= e(t('common.back')) ?>">↑</button>

    <!-- ── TOAST NOTIFICATIONS ────────────────────────────────────── -->
    <div x-data="toastManager()"
         x-init="init()"
         class="fixed right-4 top-4 z-50 flex flex-col gap-2"
         style="min-width:0;max-width:min(360px,calc(100vw - 2rem));">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-x-full"
                 x-transition:enter-end="opacity-100 translate-x-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-x-0"
                 x-transition:leave-end="opacity-0 translate-x-full"
                 class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-xl ring-1 ring-slate-100 toast-card"
                 role="alert"
                 aria-live="assertive">
                <span style="color:var(--brand-primary);font-size:1rem;flex-shrink:0;margin-top:1px;">🔔</span>
                <p class="flex-1 text-sm leading-6 text-slate-700 m-0" x-text="toast.message"></p>
                <button type="button"
                        @click="remove(toast.id)"
                        class="rounded-full p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 flex-shrink-0"
                        aria-label="<?= e(t('common.close')) ?>">✕</button>
            </div>
        </template>
    </div>

    <!-- ── AI CHAT WIDGET ─────────────────────────────────────────── -->
    <?php if (!empty($_SESSION['user_id'])): ?>
        <?php require __DIR__ . '/../shared/_ai-chat-widget.php'; ?>
    <?php endif; ?>

    <!-- ── SCRIPTS ────────────────────────────────────────────────── -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
        window.BarangGabay = Object.assign(window.BarangGabay || {}, {
            csrfToken: '<?= e(csrf_token()) ?>',
            baseUrl:   '<?= e(rtrim(base_url(), '/')) ?>'
        });

        function notifDropdown() {
            return {
                open:          false,
                loading:       false,
                notifications: [],

                toggle() {
                    this.open = !this.open;
                    if (this.open) { this.fetch(); }
                },

                async fetch() {
                    this.loading = true;
                    try {
                        const base = (window.BarangGabay?.baseUrl || '').replace(/\/$/, '');
                        const res  = await fetch(base + '/api/notifications/recent', { cache: 'no-store' });
                        if (res.ok) { this.notifications = await res.json(); }
                    } catch (_) {}
                    this.loading = false;
                },

                async markAllRead() {
                    const base = (window.BarangGabay?.baseUrl || '').replace(/\/$/, '');
                    try {
                        await fetch(base + '/api/notifications/mark-read', {
                            method:  'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body:    'csrf_token=' + encodeURIComponent(window.BarangGabay?.csrfToken || ''),
                        });
                        this.notifications = this.notifications.map(n => ({ ...n, is_read: '1' }));
                        const badge = document.getElementById('notif-badge');
                        if (badge) badge.style.display = 'none';
                    } catch (_) {}
                },

                async markOneAndGo(n) {
                    this.open = false;
                    const base = (window.BarangGabay?.baseUrl || '').replace(/\/$/, '');
                    try {
                        if (n.is_read != '1') {
                            await fetch(base + '/api/notifications/' + n.id + '/read', {
                                method:  'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body:    'csrf_token=' + encodeURIComponent(window.BarangGabay?.csrfToken || ''),
                            });
                        }
                    } catch (_) {}
                    window.location.href = n.url;
                },
            };
        }

        function toastManager() {
            return {
                toasts: [],
                init() {
                    document.addEventListener('bg:toast', (e) => {
                        this.add(e.detail && e.detail.message ? e.detail.message : '');
                    });
                },
                add(message) {
                    if (!message) return;
                    const id = Date.now();
                    this.toasts.push({ id, message });
                    setTimeout(() => this.remove(id), 5000);
                },
                remove(id) {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                },
            };
        }

        /* ── Premium JS ────────────────────────────────────────────── */

        // Dark / light theme toggle — persisted, defaults to OS preference
        // (the <head> blocking script already applied any stored explicit choice).
        function currentBgTheme() {
            const explicit = document.documentElement.getAttribute('data-theme');
            if (explicit) return explicit;
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        function updateThemeIcons() {
            const isDark = currentBgTheme() === 'dark';
            document.querySelectorAll('.theme-toggle-icon').forEach((el) => {
                el.classList.remove('bi-moon-stars-fill', 'bi-sun-fill');
                el.classList.add(isDark ? 'bi-sun-fill' : 'bi-moon-stars-fill');
            });
        }
        window.toggleBgTheme = function () {
            const next = currentBgTheme() === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('bg-theme', next);
            updateThemeIcons();
        };
        updateThemeIcons();

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

        // User dropdown toggle
        function toggleUserMenu() {
            document.getElementById('userDropdown')?.classList.toggle('hidden');
        }
        document.addEventListener('click', (e) => {
            const menu = document.getElementById('userMenu');
            const drop = document.getElementById('userDropdown');
            if (menu && !menu.contains(e.target)) {
                drop?.classList.add('hidden');
            }
        });

        // Scroll to top
        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Fade-up scroll animation
        const fadeEls = Array.prototype.slice.call(document.querySelectorAll('.fade-up'));
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.1 }
        );
        fadeEls.forEach(el => observer.observe(el));

        /* Safety net. .fade-up starts at opacity 0, so anything the observer
           misses stays invisible FOREVER — and its callbacks are async, so a
           fast flick-scroll on a long page (the resident dashboard is now
           many screens tall) can sail past a section before its entry is
           delivered. Content must never be lost to a decorative animation, so
           every element that has reached the viewport is revealed here too.
           Both paths add the same class, so they compose safely. */
        let sweepQueued = false;
        function sweepFadeUps() {
            sweepQueued = false;
            let pending = 0;
            fadeEls.forEach(el => {
                if (el.classList.contains('visible')) return;
                if (el.getBoundingClientRect().top < window.innerHeight) {
                    el.classList.add('visible');
                    observer.unobserve(el);
                } else {
                    pending++;
                }
            });
            if (pending === 0) {
                window.removeEventListener('scroll', queueSweep);
                window.removeEventListener('resize', queueSweep);
            }
        }
        function queueSweep() {
            if (sweepQueued) return;
            sweepQueued = true;
            requestAnimationFrame(sweepFadeUps);
        }
        window.addEventListener('scroll', queueSweep, { passive: true });
        window.addEventListener('resize', queueSweep, { passive: true });
        window.addEventListener('load', queueSweep);
        queueSweep();

        // Animated count-up for [data-countup] stat numbers (dashboard tiles)
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        document.querySelectorAll('[data-countup]').forEach((el) => {
            const target = parseInt(el.getAttribute('data-countup'), 10);
            if (isNaN(target)) return;
            if (prefersReducedMotion) {
                el.textContent = target.toLocaleString('en-US');
                return;
            }
            const duration = 900;
            const start = performance.now();
            function tick(now) {
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = Math.round(target * eased).toLocaleString('en-US');
                if (progress < 1) requestAnimationFrame(tick);
                else el.textContent = target.toLocaleString('en-US');
            }
            requestAnimationFrame(tick);
        });

        // Page transition
        document.body.classList.add('page-transition');

        // Auto-hide flash messages
        setTimeout(() => {
            document.querySelectorAll('.flash-message').forEach(el => {
                el.style.transition = 'opacity 0.3s, transform 0.3s';
                el.style.opacity = '0';
                el.style.transform = 'translateY(-10px)';
                setTimeout(() => el.remove(), 300);
            });
        }, 4000);
    </script>
    <script src="<?= e(asset_v('assets/js/main.js')) ?>"></script>
    <script>
        if (window.speechSynthesis) {
            window.speechSynthesis.onvoiceschanged = function () {
                window.speechSynthesis.getVoices();
            };
            window.addEventListener('beforeunload', function () {
                window.speechSynthesis.cancel();
            });
        }
    </script>
</body>
</html>
