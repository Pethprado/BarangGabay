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

// Pre-compute accordion open-states (used as PHP-to-Alpine boot values).
$announcementsOpen = str_starts_with($currentPath, '/admin/announcements') ? 'true' : 'false';
$eventsOpen        = str_starts_with($currentPath, '/admin/events')        ? 'true' : 'false';

// Avatar initial – first character of full name.
$avatarInitial = mb_strtoupper(mb_substr($userName, 0, 1, 'UTF-8'), 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Bitter:wght@600;700;800&display=swap" rel="stylesheet">

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
</head>
<body>

<div x-data="adminLayout()" x-init="init()">

    <!-- ── Mobile sidebar overlay ──────────────────────────── -->
    <div class="sidebar-overlay"
         x-show="sidebarOpen && isMobile"
         x-transition.opacity
         @click="sidebarOpen = false"></div>

    <!-- ====================================================
         SIDEBAR
         ==================================================== -->
    <aside class="admin-sidebar" :class="{ 'sidebar-hidden': !sidebarOpen && isMobile }">

        <!-- Brand -->
        <div class="sidebar-brand">
            <div class="sidebar-brand-text d-flex align-items-center">
                <?= baranggabay_logo('dark', ['size' => 'large', 'href' => route('admin')]) ?>
            </div>
            <button class="sidebar-close d-lg-none" @click="sidebarOpen = false"
                    aria-label="Close sidebar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <!-- Navigation -->
        <nav class="sidebar-nav" aria-label="Admin navigation">

            <!-- ── Overview ─────────────────────────────── -->
            <p class="nav-section-label"><?= e(t('admin_nav.overview')) ?></p>

            <a href="<?= e(route('admin')) ?>"
               class="sidebar-link <?= $isActive('/admin', true) ?>">
                <i class="bi bi-speedometer2 nav-icon"></i>
                <span><?= e(t('admin_nav.dashboard')) ?></span>
            </a>

            <!-- ── Content ──────────────────────────────── -->
            <p class="nav-section-label"><?= e(t('admin_nav.content')) ?></p>

            <!-- Announcements accordion -->
            <div x-data="{ open: <?= $announcementsOpen ?> }">
                <button @click="open = !open"
                        class="sidebar-link sidebar-accordion <?= $isActive('/admin/announcements', false) ?>"
                        :aria-expanded="open">
                    <i class="bi bi-megaphone nav-icon"></i>
                    <span><?= e(t('admin_nav.announcements')) ?></span>
                    <i class="bi bi-chevron-down accordion-arrow ms-auto"
                       :class="{ rotated: open }"></i>
                </button>
                <div class="sidebar-sub" x-show="open" x-transition>
                    <a href="<?= e(route('admin/announcements')) ?>"
                       class="sidebar-sub-link <?= $isActive('/admin/announcements', true) ?>">
                        <i class="bi bi-list-ul"></i> <?= e(t('admin_nav.all_announcements')) ?>
                    </a>
                    <a href="<?= e(route('admin/announcements/create')) ?>"
                       class="sidebar-sub-link <?= $isActive('/admin/announcements/create', true) ?>">
                        <i class="bi bi-plus-circle"></i> <?= e(t('admin_nav.create_new')) ?>
                    </a>
                </div>
            </div>

            <!-- Events accordion -->
            <div x-data="{ open: <?= $eventsOpen ?> }">
                <button @click="open = !open"
                        class="sidebar-link sidebar-accordion <?= $isActive('/admin/events', false) ?>"
                        :aria-expanded="open">
                    <i class="bi bi-calendar-event nav-icon"></i>
                    <span><?= e(t('admin_nav.events')) ?></span>
                    <i class="bi bi-chevron-down accordion-arrow ms-auto"
                       :class="{ rotated: open }"></i>
                </button>
                <div class="sidebar-sub" x-show="open" x-transition>
                    <a href="<?= e(route('admin/events')) ?>"
                       class="sidebar-sub-link <?= $isActive('/admin/events', true) ?>">
                        <i class="bi bi-list-ul"></i> <?= e(t('admin_nav.all_events')) ?>
                    </a>
                    <a href="<?= e(route('admin/events/create')) ?>"
                       class="sidebar-sub-link <?= $isActive('/admin/events/create', true) ?>">
                        <i class="bi bi-plus-circle"></i> <?= e(t('admin_nav.create_new')) ?>
                    </a>
                </div>
            </div>

            <!-- Ordinances -->
            <a href="<?= e(route('admin/ordinances')) ?>"
               class="sidebar-link <?= $isActive('/admin/ordinances', false) ?>">
                <i class="bi bi-journal-text nav-icon"></i>
                <span><?= e(t('admin_nav.ordinances')) ?></span>
            </a>

            <!-- ── Management (admin + staff + superadmin). Every link here is
                 open to all three, matching each one's route:role restriction;
                 Manobo below is the one exception and stays admin-only. ── -->
            <?php if (in_array($userRole, ['admin', 'staff', 'superadmin'], true)): ?>

            <p class="nav-section-label"><?= e(t('admin_nav.management')) ?></p>

            <a href="<?= e(route('admin/residents')) ?>"
               class="sidebar-link <?= $isActive('/admin/residents', false) ?>">
                <i class="bi bi-people nav-icon"></i>
                <span><?= e(t('admin_nav.residents')) ?></span>
                <?php if ($pendingCount > 0): ?>
                <span class="sidebar-badge"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>

            <!-- Staff accounts. Admin/superadmin only, matching the route:
                 a staff member cannot manage other back-office accounts. -->
            <?php if (in_array($userRole, ['admin', 'superadmin'], true)): ?>
            <a href="<?= e(route('admin/staff')) ?>"
               class="sidebar-link <?= $isActive('/admin/staff', false) ?>">
                <i class="bi bi-person-badge nav-icon"></i>
                <span><?= e(t('staff_list.title')) ?></span>
            </a>
            <?php endif; ?>

            <!-- Urgent announcements whose machine translation is held back.
                 Badged, because holding a storm warning's translation is only
                 responsible if somebody is told it is being held. -->
            <?php
            try {
                $__pendingTx = \App\Models\Announcement::countAwaitingTranslationReview();
            } catch (\Throwable) {
                $__pendingTx = 0;
            }
            ?>
            <?php if ($__pendingTx > 0): ?>
            <a href="<?= e(route('admin/translations')) ?>"
               class="sidebar-link <?= $isActive('/admin/translations', false) ?>">
                <i class="bi bi-translate nav-icon"></i>
                <span><?= e(t('translation_review.title')) ?></span>
                <span class="sidebar-badge"><?= (int) $__pendingTx ?></span>
            </a>
            <?php endif; ?>

            <?php /* Always present, unlike the review queue above, which
                     only appears when something is waiting. "Is anything
                     missing?" is a question worth being able to ask on a
                     day when the answer is no. */ ?>
            <a href="<?= e(route('admin/translation-health')) ?>"
               class="sidebar-link <?= $isActive('/admin/translation-health', false) ?>">
                <i class="bi bi-clipboard-check nav-icon"></i>
                <span><?= e(t('translation_health.page_title')) ?></span>
            </a>

            <?php /* Document requests carry a badge for the same reason the
                     verification queue does: it is work waiting on a person,
                     and an unbadged link is one nobody opens until reminded. */ ?>
            <a href="<?= e(route('admin/documents')) ?>"
               class="sidebar-link <?= $isActive('/admin/documents', false) ?>">
                <i class="bi bi-file-earmark-text nav-icon"></i>
                <span><?= e(t('admin_nav.documents')) ?></span>
                <?php
                $__docOpen = \App\Models\DocumentRequest::countOpen();
                if ($__docOpen > 0): ?>
                <span class="sidebar-badge"><?= (int) $__docOpen ?></span>
                <?php endif; ?>
            </a>

            <a href="<?= e(route('admin/safety')) ?>"
               class="sidebar-link <?= $isActive('/admin/safety', false) ?>">
                <i class="bi bi-shield-check nav-icon"></i>
                <span><?= e(t('admin_nav.safety')) ?></span>
            </a>

            <a href="<?= e(route('admin/evacuation')) ?>"
               class="sidebar-link <?= $isActive('/admin/evacuation', false) ?>">
                <i class="bi bi-house-exclamation nav-icon"></i>
                <span><?= e(t('admin_nav.evacuation')) ?></span>
            </a>

            <a href="<?= e(route('admin/feedback')) ?>"
               class="sidebar-link <?= $isActive('/admin/feedback', false) ?>">
                <i class="bi bi-chat-dots nav-icon"></i>
                <span><?= e(t('admin_nav.feedback')) ?></span>
                <?php
                try {
                    $fbUnread = \App\Models\Feedback::unreadCountAdmin();
                    if ($fbUnread > 0): ?>
                <span class="sidebar-badge"><?= $fbUnread ?></span>
                <?php endif; } catch (\Throwable) {} ?>
            </a>

            <a href="<?= e(route('admin/reports')) ?>"
               class="sidebar-link <?= $isActive('/admin/reports', false) ?>">
                <i class="bi bi-bar-chart-line nav-icon"></i>
                <span><?= e(t('admin_nav.reports')) ?></span>
            </a>

            <a href="<?= e(route('admin/sms')) ?>"
               class="sidebar-link <?= $isActive('/admin/sms', false) ?>">
                <i class="bi bi-phone nav-icon"></i>
                <span><?= e(t('admin_nav.sms')) ?></span>
            </a>

            <?php if (in_array($userRole, ['admin', 'superadmin'], true)): ?>
            <a href="<?= e(route('admin/manobo')) ?>"
               class="sidebar-link <?= $isActive('/admin/manobo', false) ?>">
                <i class="bi bi-translate nav-icon"></i>
                <span><?= e(t('admin_nav.manobo')) ?></span>
            </a>
            <?php endif; ?>

            <?php endif; ?>

            <!-- ── System (superadmin only) ──────────────────── -->
            <?php if ($userRole === 'superadmin'): ?>
            <p class="nav-section-label"><?= e(t('admin_nav.system')) ?></p>

            <a href="<?= e(route('superadmin/errors')) ?>"
               class="sidebar-link <?= $isActive('/superadmin/errors', false) ?>">
                <i class="bi bi-bug nav-icon"></i>
                <span><?= e(t('admin_nav.error_logs')) ?></span>
                <?php
                try {
                    $sysErrors = (int) db()->query(
                        "SELECT COUNT(*) FROM error_logs WHERE resolved_at IS NULL AND severity = 'critical'"
                    )->fetchColumn();
                    if ($sysErrors > 0): ?>
                <span class="sidebar-badge"><?= $sysErrors ?></span>
                <?php endif;
                } catch (\Throwable $e) {
                    // Table not migrated yet — the nav link still works.
                }
                ?>
            </a>

            <a href="<?= e(route('superadmin/sessions')) ?>"
               class="sidebar-link <?= $isActive('/superadmin/sessions', false) ?>">
                <i class="bi bi-person-check nav-icon"></i>
                <span><?= e(t('admin_nav.sessions')) ?></span>
            </a>

            <a href="<?= e(route('superadmin/backups')) ?>"
               class="sidebar-link <?= $isActive('/superadmin/backups', false) ?>">
                <i class="bi bi-database nav-icon"></i>
                <span><?= e(t('admin_nav.backups')) ?></span>
            </a>

            <a href="<?= e(route('superadmin/ai-accuracy')) ?>"
               class="sidebar-link <?= $isActive('/superadmin/ai-accuracy', false) ?>">
                <i class="bi bi-graph-up nav-icon"></i>
                <span><?= e(t('admin_nav.ai_accuracy')) ?></span>
            </a>

            <a href="<?= e(route('superadmin/settings')) ?>"
               class="sidebar-link <?= $isActive('/superadmin/settings', false) ?>">
                <i class="bi bi-sliders nav-icon"></i>
                <span><?= e(t('admin_nav.settings')) ?></span>
                <?php if (setting('maintenance_mode', false)): ?>
                <span class="sidebar-badge" style="background:#dc2626;" title="Maintenance mode is on">!</span>
                <?php endif; ?>
            </a>
            <?php endif; ?>

        </nav><!-- /sidebar-nav -->

        <!-- User footer -->
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-avatar" aria-hidden="true"><?= e($avatarInitial) ?></div>
                <div class="sidebar-user-info">
                    <p class="sidebar-user-name"><?= e($userName) ?></p>
                    <p class="sidebar-user-role"><?= ucfirst(e($userRole)) ?></p>
                </div>
            </div>
            <!-- Self-service account settings — the back-office counterpart of
                 the resident /profile page. Open to every back-office role. -->
            <a href="<?= e(route('admin/account')) ?>"
               class="sidebar-link <?= $isActive('/admin/account', false) ?>">
                <i class="bi bi-person-gear nav-icon"></i>
                <span><?= e(t('admin_nav.account')) ?></span>
            </a>
            <a href="<?= e(route('logout')) ?>" class="sidebar-logout">
                <i class="bi bi-box-arrow-right"></i> <?= e(t('admin_nav.signout')) ?>
            </a>
        </div>

    </aside><!-- /admin-sidebar -->

    <!-- ====================================================
         MAIN AREA
         ==================================================== -->
    <div class="admin-main">

        <!-- Top bar -->
        <header class="admin-topbar">
            <div class="topbar-left">
                <!-- Hamburger (mobile only) -->
                <button class="topbar-hamburger d-lg-none"
                        @click="sidebarOpen = !sidebarOpen"
                        aria-label="Toggle sidebar">
                    <i class="bi bi-list"></i>
                </button>
                <h1 class="topbar-title"><?= e($pageTitle) ?></h1>
            </div>

            <div class="topbar-right">
                <!-- No language switch here on purpose. The FIL / EN / MN
                     buttons are a resident feature: they exist so Manobo
                     residents can read barangay announcements in a language
                     they are comfortable with. The back office is written
                     Tagalog-first throughout and always renders in Filipino
                     (see current_locale()), so a switch here would have
                     nothing to switch. Staff who want the resident view in
                     another language still get the buttons on those pages. -->

                <!-- Dark / light mode toggle -->
                <button type="button" class="topbar-icon-btn" onclick="window.toggleBgTheme()"
                        title="<?= e(t('theme.toggle')) ?>" aria-label="<?= e(t('theme.toggle')) ?>">
                    <i class="bi theme-toggle-icon"></i>
                </button>

                <!-- Notification bell -->
                <a href="<?= e(route('notifications')) ?>" class="topbar-icon-btn"
                   title="<?= e(t('nav.notifications')) ?>" aria-label="<?= e(t('nav.notifications')) ?>">
                    <i class="bi bi-bell"></i>
                </a>

                <!-- Role badge -->
                <span class="role-badge role-<?= e($userRole) ?>"><?= ucfirst(e($userRole)) ?></span>

                <!-- Admin name — links to their own account settings -->
                <a href="<?= e(route('admin/account')) ?>" class="topbar-username d-none d-sm-inline"
                   title="<?= e(t('admin_nav.account')) ?>" style="text-decoration:none;color:inherit;">
                    <?= e($userName) ?>
                </a>

                <!-- Logout button (desktop) -->
                <a href="<?= e(route('logout')) ?>" class="topbar-logout d-none d-md-inline-flex">
                    <i class="bi bi-box-arrow-right"></i>
                    <span><?= e(t('admin_nav.signout')) ?></span>
                </a>
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

