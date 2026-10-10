<?php
/**
 * Admin master layout.
 *
 * Variables injected by controllers / router:
 *   string $pageTitle    – page heading shown in the top bar and <title>
 *   int    $pendingCount – residents awaiting verification; amber banner shown when > 0
 *   string $content      – pre-rendered view HTML
 */

$pageTitle    = $pageTitle    ?? 'Dashboard';
$pendingCount = (int) ($pendingCount ?? 0);
$userRole     = $_SESSION['role']      ?? 'staff';
$userName     = $_SESSION['full_name'] ?? 'Admin';

// Normalise the current path (strip query string and base-folder prefix).
$currentPath = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$basePath    = rtrim(parse_url(base_url(), PHP_URL_PATH) ?? '', '/');
if ($basePath !== '' && str_starts_with($currentPath, $basePath)) {
    $currentPath = substr($currentPath, strlen($basePath));
}
$currentPath = '/' . ltrim($currentPath, '/');

/**
 * Returns 'active' when the current path matches $navPath.
 * Uses prefix matching by default; pass $exact = true for an exact match.
 */
$isActive = static function (string $navPath, bool $exact = false) use ($currentPath): string {
    $navPath = '/' . ltrim($navPath, '/');
    $match   = $exact
        ? ($currentPath === $navPath || $currentPath === rtrim($navPath, '/'))
        : str_starts_with($currentPath, rtrim($navPath, '/') . '/') || $currentPath === rtrim($navPath, '/');
    return $match ? 'active' : '';
};

// Avatar initial – first character of full name.
$avatarInitial = mb_strtoupper(mb_substr($userName, 0, 1, 'UTF-8'), 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= e(back_office_locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — BarangGabay Admin</title>
    <base href="<?= e(base_url()) ?>">
    <!-- Applied before first paint so navigating between admin pages never flashes the wrong theme. -->
    <script>
        (function () {
            var t = localStorage.getItem('bg-theme');
            if (t === 'dark' || t === 'light') { document.documentElement.setAttribute('data-theme', t); }
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons 1.11 -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- Shared design tokens. Must come BEFORE admin.css, which reads them. -->
    <link rel="stylesheet" href="<?= e(asset_v('assets/css/tokens.css')) ?>">
    <!-- Favicon -->
    <link rel="icon" href="<?= e(asset('images/logo-icon.svg')) ?>" type="image/svg+xml">
    <link rel="alternate icon" href="<?= e(asset('favicon.ico')) ?>">
    <!-- Admin styles -->
    <link rel="stylesheet" href="<?= e(asset_v('assets/css/admin.css')) ?>">
    <!-- System theme (Manobo-inspired earth palette) — loaded LAST so its
         overrides of tokens.css/admin.css win the cascade. -->
    <link rel="stylesheet" href="<?= e(asset_v('assets/css/theme.css')) ?>">
    <!-- App shell: sidebar, top bar and dark-mode legibility. After theme.css. -->
    <link rel="stylesheet" href="<?= e(asset_v('assets/css/shell.css')) ?>">
</head>
<body>

<div x-data="adminLayout()" x-init="init()">

    <!-- ── Mobile sidebar overlay ──────────────────────────── -->
    <div class="sidebar-overlay"
         x-show="sidebarOpen && isMobile"
         x-transition.opacity
         @click="sidebarOpen = false"></div>

    <?php
    /*
     * Sidebar items, one list per role, in the order the mockup shows them.
     * Each entry: [path, icon, label, extra active prefixes, badge count, exact?].
     * Every link here is already guarded by its route's role middleware —
     * this list only decides what is SHOWN, never what is allowed.
     */
    $__isStaff = $userRole === 'staff';
    try { $__pendingTx = \App\Models\Announcement::countAwaitingTranslationReview(); } catch (\Throwable) { $__pendingTx = 0; }
    try { $__docOpen = \App\Models\DocumentRequest::countOpen(); } catch (\Throwable) { $__docOpen = 0; }
    try { $__fbUnread = (int) \App\Models\Feedback::unreadCountAdmin(); } catch (\Throwable) { $__fbUnread = 0; }
    try {
        $__payPending = (int) db()->query("SELECT COUNT(*) FROM document_payments WHERE payment_status = 'PAYMENT_PROOF_SUBMITTED'")->fetchColumn();
    } catch (\Throwable) { $__payPending = 0; }
    $__sysErrors = 0;
    if ($userRole === 'superadmin') {
        try {
            $__sysErrors = (int) db()->query("SELECT COUNT(*) FROM error_logs WHERE resolved_at IS NULL AND severity = 'critical'")->fetchColumn();
        } catch (\Throwable) {}
    }

    if ($__isStaff) {
        $__primary = [
            ['/admin',                     'bi-grid-1x2',          t('admin_nav.dashboard'),          [], 0, true],
            ['/admin/announcements?mine=1','bi-megaphone',         t('admin_nav.my_announcements'),   ['/admin/announcements'], 0],
            ['/admin/events?mine=1',       'bi-calendar-event',    t('admin_nav.my_events'),          ['/admin/events'], 0],
            ['/admin#tasks',               'bi-list-check',        t('admin_nav.assigned_tasks'),     [], 0, 'never'],
            ['/admin/residents',           'bi-people',            t('admin_nav.residents'),          [], $pendingCount],
            ['/admin/documents',           'bi-file-earmark-text', t('admin_nav.documents'),          [], $__docOpen],
            ['/admin/feedback',            'bi-chat-left-text',    t('admin_nav.feedback'),           [], $__fbUnread],
            ['/admin/translation-health',  'bi-translate',         t('admin_nav.translation_review'), ['/admin/translations'], $__pendingTx],
            ['/admin/account',             'bi-person',            t('admin_nav.my_profile'),         [], 0],
        ];
        $__tools = [
            ['/admin/ordinances', 'bi-journal-text',      t('admin_nav.ordinances'), [], 0],
            ['/admin/payments',   'bi-wallet2',           t('admin_nav.payments'),   [], $__payPending],
            ['/admin/evacuation', 'bi-house-exclamation', t('admin_nav.evacuation'), [], 0],
            ['/admin/safety',     'bi-shield-check',      t('admin_nav.safety'),     [], 0],
            ['/admin/reports',    'bi-bar-chart-line',    t('admin_nav.analytics'),  [], 0],
            ['/admin/sms',        'bi-phone',             t('admin_nav.sms'),        [], 0],
        ];
    } else {
        $__primary = [
            ['/admin',                    'bi-grid-1x2',          t('admin_nav.dashboard'),       [], 0, true],
            ['/admin/announcements',      'bi-megaphone',         t('admin_nav.announcements'),   [], 0],
            ['/admin/events',             'bi-calendar-event',    t('admin_nav.events'),          [], 0],
            ['/admin/ordinances',         'bi-journal-text',      t('admin_nav.ordinances'),      [], 0],
            ['/admin/residents',          'bi-people',            t('admin_nav.residents'),       ['/admin/profile-updates'], $pendingCount],
            ['/admin/staff',              'bi-person-badge',      t('staff_list.title'),          [], 0],
            ['/admin/translation-health', 'bi-translate',         t('admin_nav.translation_hub'), ['/admin/translations'], $__pendingTx],
            ['/admin/documents',          'bi-file-earmark-text', t('admin_nav.documents'),       [], $__docOpen],
            ['/admin/reports',            'bi-bar-chart-line',    t('admin_nav.analytics'),       [], 0],
        ];
        if ($userRole === 'superadmin') {
            $__primary[] = ['/superadmin/settings', 'bi-gear', t('admin_nav.system_settings'), [], setting('maintenance_mode', false) ? '!' : 0];
        }
        $__tools = [
            ['/admin/voice-training', 'bi-mic',               t('admin_nav.voice_training'), [], 0],
            ['/admin/manobo',         'bi-book',              t('admin_nav.manobo'),         ['/admin/dictionary', '/admin/bisaya'], 0],
            ['/admin/payments',       'bi-wallet2',           t('admin_nav.payments'),       [], $__payPending],
            ['/admin/feedback',       'bi-chat-left-text',    t('admin_nav.feedback'),       [], $__fbUnread],
            ['/admin/evacuation',     'bi-house-exclamation', t('admin_nav.evacuation'),     [], 0],
            ['/admin/safety',         'bi-shield-check',      t('admin_nav.safety'),         [], 0],
            ['/admin/sms',            'bi-phone',             t('admin_nav.sms'),            [], 0],
        ];
        if ($userRole === 'superadmin') {
            array_push(
                $__tools,
                ['/superadmin/errors',      'bi-bug',          t('admin_nav.error_logs'),  [], $__sysErrors],
                ['/superadmin/sessions',    'bi-person-check', t('admin_nav.sessions'),    [], 0],
                ['/superadmin/backups',     'bi-database',     t('admin_nav.backups'),     [], 0],
                ['/superadmin/ai-accuracy', 'bi-graph-up',     t('admin_nav.ai_accuracy'), [], 0]
            );
        }
    }

    $__renderNav = static function (array $items) use ($isActive): string {
        $html = '';
        foreach ($items as $item) {
            [$path, $icon, $label, $extra, $badge] = $item;
            $exact  = $item[5] ?? false;
            $bare   = (string) strtok($path, '?#');
            $active = $exact === 'never' ? '' : $isActive($bare, $exact === true);
            foreach ($extra as $prefix) {
                $active = $active ?: $isActive($prefix);
            }
            $badgeHtml = ($badge === '!' || (int) $badge > 0)
                ? '<span class="sidebar-badge">' . e((string) $badge) . '</span>'
                : '';
            $html .= '<a href="' . e(route(ltrim($path, '/'))) . '" class="sidebar-link ' . $active . '"'
                . ($active ? ' aria-current="page"' : '') . '>'
                . '<i class="bi ' . $icon . ' nav-icon" aria-hidden="true"></i><span>' . e($label) . '</span>'
                . $badgeHtml . '</a>';
        }
        return $html;
    };
    $__primaryHtml = $__renderNav($__primary);
    $__toolsHtml   = $__renderNav($__tools);
    $__toolsOpen   = str_contains($__toolsHtml, 'sidebar-link active');
    $__roleLabel   = t('admin_nav.role_' . (in_array($userRole, ['superadmin', 'admin', 'staff'], true) ? $userRole : 'staff'));
    $__panelLabel  = $__isStaff ? t('admin_nav.staff_panel') : t('admin_nav.admin_panel');
    $__boLocale    = back_office_locale();
    ?>

    <!-- ====================================================
         SIDEBAR
         ==================================================== -->
    <aside class="admin-sidebar" id="adminSidebar" :class="{ 'sidebar-hidden': !sidebarOpen && isMobile }">

        <div class="sidebar-brand">
            <div class="sidebar-brand-text">
                <?= baranggabay_logo('dark', ['size' => 'large', 'href' => route('admin')]) ?>
                <p class="sidebar-panel-label"><?= e($__panelLabel) ?></p>
            </div>
            <button class="sidebar-close d-lg-none" type="button" @click="sidebarOpen = false"
                    aria-label="<?= e(t('admin_nav.close_menu')) ?>">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>

        <nav class="sidebar-nav" aria-label="<?= e($__panelLabel) ?>">
            <?= $__primaryHtml ?>

            <div class="sidebar-group" x-data="{ open: <?= $__toolsOpen ? 'true' : 'false' ?> }">
                <button type="button" class="nav-section-toggle" @click="open = !open"
                        :aria-expanded="open.toString()" aria-controls="sidebarTools">
                    <span><?= e(t('admin_nav.tools')) ?></span>
                    <i class="bi bi-chevron-down" :class="{ 'is-open': open }" aria-hidden="true"></i>
                </button>
                <div id="sidebarTools" x-show="open"<?= $__toolsOpen ? '' : ' style="display:none"' ?>>
                    <?= $__toolsHtml ?>
                </div>
            </div>
        </nav>

        <!-- User card — opens a small account menu, like the mockup's chevron row. -->
        <div class="sidebar-footer" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
            <div class="sidebar-user-menu" x-show="open" x-transition.opacity style="display:none" role="menu">
                <a href="<?= e(route('admin/account')) ?>" role="menuitem"><i class="bi bi-person-gear" aria-hidden="true"></i><?= e(t('admin_nav.account')) ?></a>
                <a href="<?= e(route('logout')) ?>" role="menuitem"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><?= e(t('admin_nav.signout')) ?></a>
            </div>
            <button type="button" class="sidebar-user" @click="open = !open" :aria-expanded="open.toString()"
                    aria-haspopup="menu" aria-label="<?= e(t('admin_nav.user_menu')) ?>">
                <span class="sidebar-avatar" aria-hidden="true"><?= e($avatarInitial) ?></span>
                <span class="sidebar-user-info">
                    <span class="sidebar-user-name"><?= e($userName) ?></span>
                    <span class="sidebar-user-role"><?= e($__roleLabel) ?></span>
                </span>
                <i class="bi bi-chevron-right sidebar-user-chevron" aria-hidden="true"></i>
            </button>
        </div>

    </aside><!-- /admin-sidebar -->

    <!-- ====================================================
         MAIN AREA
         ==================================================== -->
    <div class="admin-main">

        <!-- Top bar -->
        <header class="admin-topbar">
            <div class="topbar-left">
                <button class="topbar-hamburger d-lg-none" type="button"
                        @click="sidebarOpen = !sidebarOpen"
                        aria-controls="adminSidebar" :aria-expanded="sidebarOpen.toString()"
                        aria-label="<?= e(t('admin_nav.open_menu')) ?>">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <?php if (!empty($topbarGreeting)): ?>
                    <span class="topbar-greet-icon" aria-hidden="true"><i class="bi <?= e($topbarIcon ?? 'bi-person-workspace') ?>"></i></span>
                    <div class="topbar-heading">
                        <h1 class="topbar-title"><?= e($topbarGreeting) ?></h1>
                        <?php if (!empty($topbarSubtitle)): ?><p class="topbar-sub"><?= e($topbarSubtitle) ?></p><?php endif; ?>
                    </div>
                <?php else: ?>
                    <h1 class="topbar-title"><?= e($pageTitle) ?></h1>
                <?php endif; ?>
            </div>

            <div class="topbar-right">
                <!-- Interface language. Labels and menus follow it; post CONTENT in
                     the back office stays the original text (see back_office_locale()). -->
                <div class="topbar-lang d-none d-md-inline-flex" role="group" aria-label="<?= e(t('lang.switch_label')) ?>">
                    <?php foreach (available_locales() as $__code => $__label): ?>
                        <a href="<?= e(route('set-locale/' . $__code) . '?scope=admin') ?>" title="<?= e($__label) ?>"
                           class="<?= $__boLocale === $__code ? 'is-active' : '' ?>"<?= $__boLocale === $__code ? ' aria-current="true"' : '' ?>><?= e(locale_short_code($__code)) ?></a>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="topbar-icon-btn" onclick="window.toggleBgTheme()"
                        title="<?= e(t('theme.toggle')) ?>" aria-label="<?= e(t('theme.toggle')) ?>">
                    <i class="bi theme-toggle-icon" aria-hidden="true"></i>
                </button>

                <a href="<?= e(route('notifications')) ?>" class="topbar-icon-btn topbar-bell"
                   x-data="unreadBell(<?= e(json_encode(route('api/notifications/unread'))) ?>)"
                   title="<?= e(t('nav.notifications')) ?>" aria-label="<?= e(t('nav.notifications')) ?>">
                    <i class="bi bi-bell" aria-hidden="true"></i>
                    <span class="topbar-badge" x-show="count > 0" x-text="count > 99 ? '99+' : count" style="display:none"></span>
                </a>

                <div class="topbar-user" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                    <button type="button" class="topbar-user-btn" @click="open = !open" :aria-expanded="open.toString()"
                            aria-haspopup="menu" aria-label="<?= e(t('admin_nav.user_menu')) ?>">
                        <span class="topbar-avatar" aria-hidden="true"><?= e($avatarInitial) ?></span>
                        <span class="topbar-username"><?= e($userName) ?></span>
                        <i class="bi bi-chevron-down topbar-user-caret" aria-hidden="true"></i>
                    </button>
                    <div class="topbar-user-menu" x-show="open" x-transition.opacity style="display:none" role="menu">
                        <div class="topbar-user-menu__head"><strong><?= e($userName) ?></strong><span><?= e($__roleLabel) ?></span></div>
                        <div class="topbar-lang topbar-lang--menu d-md-none" role="group" aria-label="<?= e(t('lang.switch_label')) ?>">
                            <?php foreach (available_locales() as $__code => $__label): ?>
                                <a href="<?= e(route('set-locale/' . $__code) . '?scope=admin') ?>" title="<?= e($__label) ?>"
                                   class="<?= $__boLocale === $__code ? 'is-active' : '' ?>"><?= e(locale_short_code($__code)) ?></a>
                            <?php endforeach; ?>
                        </div>
                        <a href="<?= e(route('admin/account')) ?>" role="menuitem"><i class="bi bi-person-gear" aria-hidden="true"></i><?= e(t('admin_nav.account')) ?></a>
                        <a href="<?= e(route('logout')) ?>" role="menuitem"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><?= e(t('admin_nav.signout')) ?></a>
                    </div>
                </div>
            </div>
        </header><!-- /admin-topbar -->

        <!-- Pending verification banner -->
        <?php if ($pendingCount > 0): ?>
        <div class="pending-banner" role="alert" aria-live="polite">
            <div class="pending-banner-body">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>
                    <?= $pendingCount ?> resident<?= $pendingCount !== 1 ? 's' : '' ?> awaiting verification.
                </strong>
                <span class="d-none d-sm-inline">
                    Review uploaded IDs and approve access.
                </span>
            </div>
            <a href="<?= e(route('admin/residents')) ?>" class="pending-banner-link">
                Review now <i class="bi bi-arrow-right-short"></i>
            </a>
        </div>
        <?php endif; ?>

        <!-- Flash messages -->
        <div class="admin-flash">
            <?php require __DIR__ . '/../shared/_flash.php'; ?>
        </div>

        <!-- Page content -->
        <main class="admin-content" id="main-content">
            <?= $content ?? '' ?>
        </main>

    </div><!-- /admin-main -->

</div><!-- /x-data -->

<!-- ── Scripts (load order matters) ─────────────────────────── -->

<!-- Chart.js 4 (global before page scripts) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- Bootstrap 5.3 bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Alpine.js 3 — defer so it boots after the DOM is ready -->
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<script>
    // Globals accessible from view scripts and the AI widget.
    window.BarangGabay = {
        csrfToken: '<?= e(csrf_token()) ?>',
        baseUrl:   '<?= e(base_url()) ?>'
    };

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

    /**
     * Top-bar bell: polls the unread count once a minute (CLAUDE.md 7.5).
     * A failed poll leaves the last count in place rather than flashing 0.
     */
    function unreadBell(url) {
        return {
            count: 0,
            init() {
                const poll = () => fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then((r) => (r.ok ? r.json() : null))
                    .then((d) => { if (d) { this.count = parseInt(d.count ?? d.unread ?? 0, 10) || 0; } })
                    .catch(() => {});
                poll();
                setInterval(poll, 60000);
            }
        };
    }

    /**
     * Root Alpine component: manages sidebar state and
     * responsiveness without a full reactive framework.
     */
    function adminLayout() {
        return {
            sidebarOpen: window.innerWidth >= 992,
            isMobile:    window.innerWidth < 992,

            init() {
                const onResize = () => {
                    this.isMobile   = window.innerWidth < 992;
                    if (!this.isMobile) this.sidebarOpen = true;
                };
                window.addEventListener('resize', onResize);
            }
        };
    }
</script>

<script src="<?= e(asset_v('assets/js/admin.js')) ?>"></script>
</body>
</html>

