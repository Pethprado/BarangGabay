<?php
/**
 * Admin dashboard view.
 * Variables: $verifiedCount (int), $pendingCount (int), $publishedCount (int),
 *            $upcomingCount (int), $pendingResidents (array), $recentActivity (array)
 */
$verifiedCount    = $verifiedCount    ?? 0;
$pendingCount     = $pendingCount     ?? 0;
$publishedCount   = $publishedCount   ?? 0;
$upcomingCount    = $upcomingCount    ?? 0;
$pendingResidents = $pendingResidents ?? [];
$recentActivity   = $recentActivity   ?? [];

/**
 * Maps an audit_log action string to [icon-class, bg-color, icon-color].
 * CHANGED: retinted from the old green/blue/amber/red set to the theme's
 * earthy palette (each pair still measured for contrast: darkest ink on
 * its own light tint, all >= 6:1).
 */
$actionIcon = static function (string $action): array {
    if (str_starts_with($action, 'announcement')) return ['bi-megaphone-fill',      '#e6ecdf', '#3f6b34'];
    if (str_starts_with($action, 'event'))        return ['bi-calendar-event-fill', '#ece7dd', '#5b5240'];
    if (str_starts_with($action, 'ordinance'))    return ['bi-journal-text',         '#f5e8d0', '#8a5a12'];
    if (str_starts_with($action, 'resident') ||
        str_starts_with($action, 'user'))         return ['bi-person-fill',          '#f6ded7', '#a3311c'];
    if (str_starts_with($action, 'login') ||
        str_starts_with($action, 'logout'))       return ['bi-box-arrow-in-right',   '#f1e6d2', '#4a3f38'];
    return                                               ['bi-activity',             '#f6ecd2', '#7a5c11'];
};

ob_start();
?>

<style>
/* ── Dashboard-only styles ──────────────────────────────────────── */
.activity-item { display:flex; gap:12px; padding:12px 1.25rem; border-bottom:1px solid var(--tb-border); }
.activity-item:last-child { border-bottom:none; }
.activity-icon { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:.8rem; }
.activity-text { margin:0 0 2px; font-size:.845rem; color:var(--text-primary); font-weight:500; line-height:1.4; }
.activity-meta { margin:0; font-size:.72rem; color:var(--text-muted); }
.quick-action-btn { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:8px; font-size:.875rem; font-weight:600; text-decoration:none; color:var(--text-primary); background:var(--surface-muted); border:1px solid var(--tb-border); transition:all .15s; }
.quick-action-btn:hover { background:var(--brand-primary-light); border-color:var(--brand-primary); color:var(--brand-primary-dark); text-decoration:none; }
.quick-action-btn .qa-icon { width:34px; height:34px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:.95rem; flex-shrink:0; background:var(--action-solid); color:#fff; }
.chart-placeholder { display:flex; align-items:center; justify-content:center; min-height:200px; color:var(--text-muted); font-size:.85rem; }
.sys-row { display:flex; justify-content:space-between; align-items:center; padding:7px 0; border-bottom:1px solid var(--tb-border); font-size:.845rem; color:var(--text-primary); }
.sys-row:last-child { border-bottom:none; padding-bottom:0; }
.sys-row .text-muted { color:var(--text-secondary) !important; }
</style>

<?php
// The greeting lives in the layout's top bar, as in the mockup.
$__hour  = (int) date('G');
$__greet = t('admin_nav.' . ($__hour < 12 ? 'greet_morning' : ($__hour < 18 ? 'greet_afternoon' : 'greet_evening')));
$__first = explode(' ', trim((string) ($_SESSION['full_name'] ?? '')))[0] ?? '';
$__role  = (string) ($_SESSION['role'] ?? '');
$topbarGreeting = $__role === 'staff'
    ? t('admin_nav.welcome_name', ['name' => $__first !== '' ? $__first : 'Staff'])
    : t('admin_nav.greet_name', ['greeting' => $__greet, 'name' => trim((string) ($_SESSION['full_name'] ?? 'Admin'))]);
$topbarSubtitle = t($__role === 'staff' ? 'admin_nav.dash_staff_sub' : 'admin_nav.dash_admin_sub');
$topbarIcon     = $__role === 'staff' ? 'bi-person-workspace' : 'bi-person-gear';
?>

<!-- ── Mockup dashboard: KPI row, modules, activity, coverage ─────────
     Admin and staff share one shell; what differs is the data. Every
     number is a live count — the reference image's figures are only
     placeholders and none of them are reproduced here. -->
<?php
$__isStaffView = ($_SESSION['role'] ?? '') === 'staff';
$__isAdmin     = in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'], true);
$staffKpi      = $staffKpi ?? ['announcements' => 0, 'events' => 0, 'feedback' => 0, 'approvals' => 0];
$recentTasks   = $recentTasks ?? [];
$__kpis = $__isStaffView ? [
    ['My Announcements', (int) $staffKpi['announcements'], route('admin/announcements') . '?mine=1', 'bi-megaphone-fill', 'green'],
    ['My Events', (int) $staffKpi['events'], route('admin/events') . '?mine=1', 'bi-calendar-event-fill', 'orange'],
    ['Feedback', (int) $staffKpi['feedback'], route('admin/feedback'), 'bi-chat-square-text-fill', 'blue'],
    ['Pending Approvals', (int) $staffKpi['approvals'], route('admin/documents') . '?status=pending', 'bi-shield-fill-exclamation', 'red'],
] : [
    ['Total Residents', (int) ($totalResidents ?? $verifiedCount), route('admin/residents'), 'bi-people-fill', 'green'],
    ['Active Staff', (int) ($activeStaffCount ?? 0), $__isAdmin ? route('admin/staff') : route('admin'), 'bi-person-fill', 'blue'],
    ['Total Announcements', (int) $publishedCount, route('admin/announcements') . '?status=published', 'bi-file-earmark-text-fill', 'orange'],
    ['Active Events', (int) $upcomingCount, route('admin/events') . '?status=upcoming', 'bi-calendar2-week-fill', 'purple'],
];
$__langName = ['msm' => 'Manobo', 'en' => 'English', 'fil' => 'Filipino', 'ceb' => 'Bisaya'];
$__langFlag = ['msm' => 'MN', 'en' => 'EN', 'fil' => 'FIL', 'ceb' => 'BIS'];
?>
<style>
.dash-kpis { display:grid; gap:1rem; grid-template-columns:repeat(2,minmax(0,1fr)); margin-bottom:1rem; }
@media (min-width:1200px) { .dash-kpis { grid-template-columns:repeat(4,minmax(0,1fr)); } }
.dash-kpi { display:flex; align-items:center; gap:.9rem; padding:1rem 1.1rem; border-radius:14px; background:var(--surface-card); border:1px solid var(--tb-border); box-shadow:var(--shadow-card); text-decoration:none; transition:transform .15s, box-shadow .15s; }
.dash-kpi:hover { transform:translateY(-2px); box-shadow:var(--shadow-lift); text-decoration:none; }
.dash-kpi strong { display:block; font-size:1.55rem; font-weight:800; line-height:1.1; color:var(--text-primary); }
.dash-kpi small { display:block; margin-top:.15rem; font-size:.8rem; color:var(--text-secondary); }
.dash-ico { width:48px; height:48px; flex-shrink:0; border-radius:12px; display:grid; place-items:center; font-size:1.3rem; }
.dash-ico--green { background:#e2f2e8; color:#168a4b; } .dash-ico--blue { background:#e3ecfb; color:#2468d8; }
.dash-ico--orange { background:#fcebd9; color:#d9731a; } .dash-ico--purple { background:#ece6fb; color:#7451d6; }
.dash-ico--red { background:#f8e1e1; color:#b23a3a; }
:root[data-theme="dark"] .dash-ico--green { background:rgba(22,138,75,.22); color:#7ee2a8; }
:root[data-theme="dark"] .dash-ico--blue { background:rgba(36,104,216,.25); color:#9cc0ff; }
:root[data-theme="dark"] .dash-ico--orange { background:rgba(217,115,26,.25); color:#ffc48a; }
:root[data-theme="dark"] .dash-ico--purple { background:rgba(116,81,214,.28); color:#c7b6ff; }
:root[data-theme="dark"] .dash-ico--red { background:rgba(178,58,58,.3); color:#ffaaaa; }
.dash-module { display:flex; gap:1rem; align-items:flex-start; padding:1.25rem 1.35rem; height:100%; }
.dash-module__icon { width:56px; height:56px; border-radius:14px; display:grid; place-items:center; font-size:1.6rem; flex-shrink:0; background:var(--brand-primary-light); color:var(--brand-primary); }
.dash-module h2 { font-size:1.1rem; font-weight:800; margin:0 0 .3rem; color:var(--text-primary); }
.dash-module p { font-size:.86rem; color:var(--text-secondary); margin:0 0 .9rem; }
.dash-h { font-size:1rem; font-weight:800; margin:0; color:var(--text-primary); }
.dash-act { display:flex; align-items:center; gap:.75rem; padding:.6rem 0; border-bottom:1px solid var(--tb-border); }
.dash-act:last-child { border-bottom:0; }
.dash-act .dash-ico { width:30px; height:30px; border-radius:999px; font-size:.8rem; }
.dash-act p { margin:0; flex:1; min-width:0; font-size:.85rem; color:var(--text-primary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.dash-act time { font-size:.75rem; color:var(--text-muted); white-space:nowrap; }
.dash-cov-row { padding:.6rem 0; border-bottom:1px solid var(--tb-border); display:flex; gap:.75rem; align-items:center; }
.dash-cov-row:last-child { border-bottom:none; }
.dash-cov-flag { width:34px; height:34px; flex-shrink:0; border-radius:999px; display:grid; place-items:center; font-size:.66rem; font-weight:800; background:var(--brand-primary-light); color:var(--brand-primary); }
.dash-cov-top { display:flex; justify-content:space-between; gap:.5rem; font-size:.85rem; color:var(--text-primary); }
.dash-cov-top small { color:var(--text-muted); }
.dash-cov-bar { height:8px; border-radius:999px; background:var(--surface-muted); overflow:hidden; margin-top:.35rem; }
.dash-cov-bar > span { display:block; height:100%; border-radius:999px; background:#168a4b; }
.dash-cov-bar > span.is-low { background:#d9731a; }
.dash-qa { display:grid; grid-template-columns:1fr 1fr; gap:.65rem; }
.dash-qa a { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.35rem; min-height:86px; padding:.75rem .5rem; border-radius:12px; text-align:center; font-size:.8rem; font-weight:700; text-decoration:none; }
.dash-qa a i { font-size:1.35rem; }
.dash-qa .qa-green { background:#e2f2e8; color:#0f6b3a; } .dash-qa .qa-orange { background:#fcebd9; color:#a65410; }
.dash-qa .qa-blue { background:#e3ecfb; color:#1d55b3; } .dash-qa .qa-gold { background:#f8eed6; color:#8a6412; }
:root[data-theme="dark"] .dash-qa .qa-green { background:rgba(22,138,75,.22); color:#9ef0c0; }
:root[data-theme="dark"] .dash-qa .qa-orange { background:rgba(217,115,26,.22); color:#ffcf9e; }
:root[data-theme="dark"] .dash-qa .qa-blue { background:rgba(36,104,216,.25); color:#b8d2ff; }
:root[data-theme="dark"] .dash-qa .qa-gold { background:rgba(201,154,55,.22); color:#f3d68f; }
.dash-task { display:flex; align-items:center; gap:.75rem; padding:.7rem 0; border-bottom:1px solid var(--tb-border); text-decoration:none; }
.dash-task:last-child { border-bottom:0; }
.dash-ev { display:flex; gap:.85rem; align-items:flex-start; padding:.6rem 0; border-bottom:1px solid var(--tb-border); text-decoration:none; }
.dash-ev:last-child { border-bottom:0; }
.dash-ev__date { width:48px; flex-shrink:0; border-radius:10px; overflow:hidden; text-align:center; border:1px solid var(--tb-border); background:var(--surface-card); }
.dash-ev__date span { display:block; background:#b23a3a; color:#fff; font-size:.62rem; font-weight:800; letter-spacing:.06em; padding:.1rem 0; }
.dash-ev__date strong { display:block; font-size:1.2rem; font-weight:800; color:var(--text-primary); padding:.15rem 0; }
@media (prefers-reduced-motion: reduce) { .dash-kpi { transition:none; } .dash-kpi:hover { transform:none; } }
</style>

<div class="dash-kpis fade-up">
    <?php foreach ($__kpis as [$__l, $__v, $__h, $__i, $__t]): ?>
        <a class="dash-kpi" href="<?= e($__h) ?>" aria-label="<?= e($__l . ': ' . number_format($__v)) ?>">
            <span class="dash-ico dash-ico--<?= $__t ?>" aria-hidden="true"><i class="bi <?= $__i ?>"></i></span>
            <span><strong data-countup="<?= $__v ?>"><?= number_format($__v) ?></strong><small><?= e($__l) ?></small></span>
        </a>
    <?php endforeach; ?>
</div>

<?php if (!$__isStaffView): ?>
<div class="row g-3 mb-3">
    <?php if ($__isAdmin): ?>
    <div class="col-12 col-lg-6">
        <div class="admin-card dash-module">
            <div class="dash-module__icon" aria-hidden="true"><i class="bi bi-soundwave"></i></div>
            <div>
                <h2>Voice Training &amp; AI Dataset Hub</h2>
                <p>Train native voice pronunciations and manage datasets for Manobo, Filipino, English and Bisaya. Residents hear only approved recordings.</p>
                <a href="<?= e(route('admin/voice-training')) ?>" class="btn-barangay">Manage Voice Dataset <i class="bi bi-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="col-12 col-lg-<?= $__isAdmin ? '6' : '12' ?>">
        <div class="admin-card dash-module">
            <div class="dash-module__icon" aria-hidden="true"><i class="bi bi-translate"></i></div>
            <div>
                <h2>Translation Management</h2>
                <p>Manage translations for all content types. New posts are auto-translated; review failures and rebuild Manobo with the current dictionaries.</p>
                <a href="<?= e(route('admin/translation-health')) ?>" class="btn-barangay">Manage Translations <i class="bi bi-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-lg-7">
        <div class="admin-card h-100" style="padding:1.15rem 1.25rem;">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <h2 class="dash-h">System Activity</h2>
                <a href="<?= e(route('admin/reports')) ?>" style="font-size:.78rem;font-weight:700;color:var(--brand-primary);">View all <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php if (empty($recentActivity)): ?>
                <p class="mb-0 mt-2" style="font-size:.85rem;color:var(--text-muted);"><?= e(t('dashboard.no_activity')) ?></p>
            <?php else: foreach (array_slice($recentActivity, 0, 6) as $__log):
                [$__ic] = $actionIcon($__log['action'] ?? '');
                $__tone = str_starts_with((string) $__log['action'], 'announcement') ? 'green'
                    : (str_starts_with((string) $__log['action'], 'event') ? 'orange'
                    : (str_starts_with((string) $__log['action'], 'voice') ? 'purple' : 'blue'));
                $__desc = $__log['description'] ?: ucwords(str_replace(['.', '_'], [': ', ' '], (string) ($__log['action'] ?? 'system')));
            ?>
                <div class="dash-act">
                    <span class="dash-ico dash-ico--<?= $__tone ?>" aria-hidden="true"><i class="bi <?= $__ic ?>"></i></span>
                    <p title="<?= e($__desc) ?>"><?= e($__desc) ?></p>
                    <time datetime="<?= e((string) $__log['created_at']) ?>"><?= e(relative_time((string) $__log['created_at'])) ?></time>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <div class="col-12 col-lg-5">
        <div class="admin-card h-100" style="padding:1.15rem 1.25rem;">
            <h2 class="dash-h mb-1">Language Dataset Coverage</h2>
            <p class="mb-1" style="font-size:.76rem;color:var(--text-muted);">Words used in visible posts that have an approved recording.</p>
            <?php foreach (($datasetCoverage ?? []) as $__lc => $__r):
                $__pct = (float) ($__r['usage_coverage'] ?? 0); ?>
                <div class="dash-cov-row">
                    <span class="dash-cov-flag" aria-hidden="true"><?= e($__langFlag[$__lc] ?? strtoupper($__lc)) ?></span>
                    <div style="flex:1;min-width:0;">
                        <div class="dash-cov-top">
                            <span><strong><?= e($__langName[$__lc] ?? $__lc) ?> Words</strong><br><small><?= number_format((int) $__r['used_recorded']) ?> / <?= number_format((int) $__r['used_words']) ?></small></span>
                            <strong><?= number_format($__pct, 0) ?>%</strong>
                        </div>
                        <div class="dash-cov-bar" role="progressbar" aria-label="<?= e($__langName[$__lc] ?? $__lc) ?> coverage" aria-valuenow="<?= $__pct ?>" aria-valuemin="0" aria-valuemax="100"><span class="<?= $__pct < 50 ? 'is-low' : '' ?>" style="width:<?= max(0, min(100, $__pct)) ?>%"></span></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!empty($manoboDictionary['total_dictionary_words'])): ?>
                <p class="mb-0 mt-2" style="font-size:.76rem;color:var(--text-muted);">Manobo dictionary: <?= (int) $manoboDictionary['words_with_audio'] ?> of <?= (int) $manoboDictionary['total_dictionary_words'] ?> entries recorded.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php else:
    $__taskPill = static function (string $st): array {
        if ($st === 'pending') { return ['Pending', 'warning']; }
        if (in_array($st, ['released', 'completed', 'claimed'], true)) { return ['Completed', 'success']; }
        if (in_array($st, ['rejected', 'cancelled'], true)) { return [ucfirst($st), 'neutral']; }
        return ['In Progress', 'info'];
    };
    $__upcoming = array_slice(array_merge($todayEvents ?? [], $weekEvents ?? []), 0, 3);
?>
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-8">
        <div class="admin-card h-100" id="tasks" style="padding:1.15rem 1.25rem;scroll-margin-top:90px;">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <h2 class="dash-h">Recent Tasks</h2>
                <a href="<?= e(route('admin/documents')) ?>" style="font-size:.78rem;font-weight:700;color:var(--brand-primary);">View all <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php if (!$recentTasks): ?>
                <p class="mb-0 mt-2" style="font-size:.85rem;color:var(--text-muted);">No document requests yet.</p>
            <?php else: foreach ($recentTasks as $__t): [$__pl, $__tone] = $__taskPill((string) $__t['status']); ?>
                <a class="dash-task" href="<?= e(route('admin/documents') . '?search=' . rawurlencode((string) $__t['reference_no'])) ?>">
                    <span class="dash-ico dash-ico--blue" style="width:36px;height:36px;border-radius:999px;font-size:.9rem;" aria-hidden="true"><i class="bi bi-file-earmark-text"></i></span>
                    <span class="flex-grow-1" style="min-width:0;">
                        <span class="d-block text-truncate" style="font-size:.86rem;font-weight:700;color:var(--text-primary);"><?= e(\App\Models\DocumentRequest::label((string) $__t['document_type'])) ?></span>
                        <span class="d-block text-truncate" style="font-size:.75rem;color:var(--text-muted);"><?= e((string) ($__t['full_name'] ?? '')) ?> · <?= e((string) $__t['reference_no']) ?> · <?= e(relative_time((string) $__t['requested_at'])) ?></span>
                    </span>
                    <span class="ds-status ds-status--<?= $__tone ?>"><?= e($__pl) ?></span>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <div class="col-12 col-lg-4 d-flex flex-column gap-3">
        <div class="admin-card" style="padding:1.15rem 1.25rem;">
            <h2 class="dash-h mb-2">Quick Actions</h2>
            <div class="dash-qa">
                <a class="qa-green" href="<?= e(route('admin/announcements/create')) ?>"><i class="bi bi-megaphone-fill" aria-hidden="true"></i>Create Announcement</a>
                <a class="qa-orange" href="<?= e(route('admin/events/create')) ?>"><i class="bi bi-calendar-plus-fill" aria-hidden="true"></i>Create Event</a>
                <a class="qa-blue" href="<?= e(route('admin/residents')) ?>"><i class="bi bi-people-fill" aria-hidden="true"></i>Manage Residents</a>
                <a class="qa-gold" href="<?= e(route('admin/translation-health')) ?>"><i class="bi bi-translate" aria-hidden="true"></i>Review Translations</a>
            </div>
        </div>
        <div class="admin-card" style="padding:1.15rem 1.25rem;">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <h2 class="dash-h">Upcoming Activities</h2>
                <a href="<?= e(route('admin/events')) ?>" style="font-size:.78rem;font-weight:700;color:var(--brand-primary);">View all <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php if (!$__upcoming): ?>
                <p class="mb-0 mt-2" style="font-size:.85rem;color:var(--text-muted);">Nothing scheduled this week.</p>
            <?php else: foreach ($__upcoming as $__ev): $__ts = strtotime((string) $__ev['event_date']); ?>
                <a class="dash-ev" href="<?= e(route('admin/events/' . (int) $__ev['id'] . '/edit')) ?>">
                    <span class="dash-ev__date" aria-hidden="true"><span><?= strtoupper(date('M', $__ts)) ?></span><strong><?= date('j', $__ts) ?></strong></span>
                    <span style="min-width:0;">
                        <span class="d-block" style="font-size:.86rem;font-weight:700;color:var(--text-primary);"><?= e((string) $__ev['title']) ?></span>
                        <span class="d-block" style="font-size:.75rem;color:var(--text-muted);"><i class="bi bi-clock me-1"></i><?= date('g:i A', $__ts) ?><?= !empty($__ev['end_date']) ? ' – ' . date('g:i A', strtotime((string) $__ev['end_date'])) : '' ?></span>
                        <?php if (!empty($__ev['venue'])): ?><span class="d-block" style="font-size:.75rem;color:var(--text-muted);"><i class="bi bi-geo-alt me-1"></i><?= e((string) $__ev['venue']) ?></span><?php endif; ?>
                    </span>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Needs your attention ───────────────────────────────────────────
     The open work, above the numbers.

     Everything below this card reports state: how many residents are
     verified, how many posts exist, what the trend looks like. None of it
     says what to do next, so whoever opened this page had to go and check
     Feedback, Residents and Translations separately to find their own
     queue. Each row here is a count the viewer can personally clear, linked
     to the page that clears it. Rows with nothing outstanding are not shown
     at all — a worklist of zeroes is just decoration. -->
<?php
$attention   = $attention   ?? [];
$todayEvents = $todayEvents ?? [];
$weekEvents  = $weekEvents  ?? [];
?>
<div class="admin-card mb-4 fade-up">
    <div class="admin-card-header">
        <h2 class="admin-card-title mb-0" style="font-size:1rem;">
            <i class="bi bi-list-check me-1" style="color:var(--brand-primary);"></i>
            <?= e(($_SESSION['role'] ?? '') === 'staff' ? t('dashboard.attention_title_staff') : t('dashboard.attention_title')) ?>
        </h2>
        <p class="text-muted mb-0 mt-1" style="font-size:.78rem;"><?= e(t('dashboard.attention_help')) ?></p>
    </div>
    <div class="admin-card-body">
        <?php if (!$attention): ?>
        <div class="d-flex align-items-center gap-2" style="color:var(--brand-primary);">
            <i class="bi bi-check2-circle" style="font-size:1.25rem;"></i>
            <p class="mb-0 fw-semibold" style="font-size:.875rem;"><?= e(t('dashboard.attention_clear')) ?></p>
        </div>
        <?php else: ?>
        <div class="d-flex flex-column gap-2">
            <?php foreach ($attention as $item): ?>
            <a href="<?= e(route(ltrim($item['url'], '/'))) ?>"
               class="d-flex align-items-center gap-3 text-decoration-none"
               style="padding:.7rem .85rem;border:1px solid var(--tb-border);border-radius:10px;background:var(--surface-card);">
                <span class="d-inline-flex align-items-center justify-content-center flex-shrink-0"
                      style="width:2.1rem;height:2.1rem;border-radius:.6rem;background:var(--brand-primary-light);color:var(--brand-primary);">
                    <i class="bi <?= e($item['icon']) ?>"></i>
                </span>
                <span class="flex-grow-1" style="color:var(--text-primary);font-size:.875rem;font-weight:600;">
                    <?= e(t('dashboard.attention_' . $item['key'], ['n' => $item['count']])) ?>
                </span>
                <span class="badge rounded-pill"
                      style="background:var(--action-solid);color:#fff;font-size:.72rem;font-weight:700;">
                    <?= (int) $item['count'] ?>
                </span>
                <i class="bi bi-chevron-right" style="color:var(--text-muted);font-size:.75rem;"></i>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (($todayEvents || $weekEvents) && empty($__isStaffView)): ?>
        <!-- What staff have to be ready for. "Upcoming: 3" on a stat card does
             not say whether that means tonight or next March. -->
        <div class="mt-3 pt-3" style="border-top:1px solid var(--tb-border);">
            <?php foreach ([['today', $todayEvents], ['week', $weekEvents]] as [$bucket, $rows]): ?>
                <?php if (!$rows) { continue; } ?>
            <p class="fw-bold mb-2" style="font-size:.72rem;letter-spacing:.06em;text-transform:uppercase;color:var(--text-secondary);">
                <?= e(t('dashboard.events_' . $bucket)) ?>
            </p>
            <div class="d-flex flex-column gap-1 mb-2">
                <?php foreach ($rows as $ev): ?>
                <a href="<?= e(route('admin/events/' . (int) $ev['id'] . '/edit')) ?>"
                   class="d-flex align-items-center gap-2 text-decoration-none"
                   style="font-size:.845rem;color:var(--text-primary);">
                    <i class="bi bi-calendar-event" style="color:var(--text-muted);font-size:.8rem;"></i>
                    <span class="flex-grow-1"><?= e($ev['title']) ?></span>
                    <span class="text-muted" style="font-size:.76rem;white-space:nowrap;">
                        <?= e(date('M j, g:i A', strtotime((string) $ev['event_date']))) ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Hazard advisory ────────────────────────────────────────────────
     Sits above the stat cards because it is the one control on this page that
     is time-critical: when a signal is raised, whoever is on duty needs to
     reach it without scrolling. -->
<?php
$advisoryText  = trim((string) ($advisoryText  ?? ''));
$advisoryLevel = (string) ($advisoryLevel ?? 'warning');
$advisoryOn    = $advisoryText !== '';
?>
<div class="admin-card mb-4 fade-up" style="<?= $advisoryOn ? 'border-color:var(--status-danger);' : '' ?>">
    <div class="admin-card-header">
        <h2 class="admin-card-title mb-0" style="font-size:1rem;">
            <i class="bi bi-broadcast me-1" style="color:<?= $advisoryOn ? 'var(--status-danger)' : 'var(--brand-primary)' ?>;"></i>
            <?= e(t('admin_advisory.title')) ?>
        </h2>
    </div>
    <div class="admin-card-body">
        <p class="text-muted mb-3" style="font-size:.8rem;line-height:1.7;">
            <?= e(t('admin_advisory.help')) ?>
        </p>

        <p class="mb-3" style="font-size:.82rem;font-weight:600;color:<?= $advisoryOn ? 'var(--status-danger)' : 'var(--text-secondary)' ?>;">
            <i class="bi <?= $advisoryOn ? 'bi-exclamation-triangle-fill' : 'bi-check2-circle' ?> me-1"></i>
            <?= e($advisoryOn ? t('admin_advisory.active') : t('admin_advisory.inactive')) ?>
        </p>

        <form method="POST" action="<?= e(route('admin/advisory')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <div class="row g-3">
                <div class="col-lg-8">
                    <label for="advisory_text" class="form-label" style="font-size:.78rem;font-weight:600;">
                        <?= e(t('admin_advisory.field_label')) ?>
                    </label>
                    <textarea id="advisory_text" name="advisory_text" rows="2" maxlength="300"
                              placeholder="<?= e(t('admin_advisory.placeholder')) ?>"
                              class="form-control" style="border-radius:8px;"><?= e($advisoryText) ?></textarea>
                </div>
                <div class="col-lg-4">
                    <label for="advisory_level" class="form-label" style="font-size:.78rem;font-weight:600;">
                        <?= e(t('admin_advisory.level_label')) ?>
                    </label>
                    <select id="advisory_level" name="advisory_level" class="form-select" style="border-radius:8px;">
                        <?php foreach (['info', 'warning', 'danger'] as $lvl): ?>
                        <option value="<?= $lvl ?>" <?= $advisoryLevel === $lvl ? 'selected' : '' ?>>
                            <?= e(t('admin_advisory.level_' . $lvl)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="submit" class="btn-barangay">
                    <i class="bi bi-megaphone"></i> <?= e(t('admin_advisory.save')) ?>
                </button>
                <?php if ($advisoryOn): ?>
                <button type="submit" name="clear" value="1" class="btn-action btn-action-danger">
                    <i class="bi bi-x-circle me-1"></i><?= e(t('admin_advisory.clear')) ?>
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- ── Pending verifications section ─────────────────────────────── -->
<?php if ($pendingCount > 0): ?>
<div class="admin-card mb-4 fade-up" style="border-color:var(--status-warning);">
    <div class="admin-card-header d-flex align-items-start flex-wrap gap-3"
         style="background:var(--status-warning-bg);border-bottom-color:var(--status-warning);">
        <div style="flex:1;min-width:0;">
            <h2 class="admin-card-title mb-0" style="font-size:1rem;">
                <i class="bi bi-exclamation-circle-fill me-2" style="color:var(--status-warning);"></i>
                <?= e(t('dashboard.pending_section_title')) ?>
                <span class="ms-2 badge"
                      style="background:var(--motif-gold);color:var(--text-primary);font-size:.72rem;border-radius:20px;padding:3px 8px;">
                    <?= $pendingCount ?>
                </span>
            </h2>
            <p class="mb-0 mt-1 text-muted" style="font-size:.8rem;">
                <?= e(t('dashboard.pending_section_desc')) ?>
            </p>
        </div>
        <a href="<?= e(route('admin/residents')) ?>"
           class="btn-barangay flex-shrink-0"
           style="font-size:.8rem;padding:7px 14px;">
            <i class="bi bi-people"></i> <?= e(t('dashboard.view_all')) ?>
        </a>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th><?= e(t('dashboard.col_name')) ?></th>
                    <th><?= e(t('dashboard.col_email')) ?></th>
                    <th><?= e(t('dashboard.col_registered')) ?></th>
                    <th><?= e(t('dashboard.col_valid_id')) ?></th>
                    <th style="width:120px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($pendingResidents, 0, 5) as $resident): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:30px;height:30px;border-radius:50%;background:var(--brand-gradient-gold);color:#fff;font-size:.72rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <?= e(mb_strtoupper(mb_substr($resident['full_name'], 0, 1, 'UTF-8'), 'UTF-8')) ?>
                            </div>
                            <span class="fw-semibold" style="font-size:.855rem;">
                                <?= e($resident['full_name']) ?>
                            </span>
                        </div>
                    </td>
                    <td class="text-muted" style="font-size:.845rem;"><?= e($resident['email']) ?></td>
                    <td class="text-muted" style="font-size:.845rem;">
                        <?= e(date('M j, Y', strtotime($resident['created_at']))) ?>
                    </td>
                    <td>
                        <?php if (!empty($resident['id_photo_url'])): ?>
                        <span class="badge text-bg-success" style="font-size:.72rem;">
                            <i class="bi bi-check-circle-fill me-1"></i><?= e(t('dashboard.uploaded')) ?>
                        </span>
                        <?php else: ?>
                        <span class="badge text-bg-secondary" style="font-size:.72rem;"><?= e(t('dashboard.none')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post"
                              action="<?= e(route('admin/residents/' . (int) $resident['id'] . '/verify')) ?>"
                              onsubmit="return confirm(<?= e(json_encode(t('residents.confirm_verify', ['name' => $resident['full_name']]))) ?>)">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <button type="submit" class="btn-barangay"
                                    style="padding:5px 12px;font-size:.775rem;">
                                <i class="bi bi-check-circle"></i> <?= e(t('dashboard.verify_btn')) ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if ($pendingCount > 5): ?>
                <tr>
                    <td colspan="5" class="text-center py-3" style="background:var(--status-warning-bg);">
                        <a href="<?= e(route('admin/residents')) ?>"
                           class="text-decoration-none fw-semibold" style="color:var(--status-warning);font-size:.845rem;">
                            <i class="bi bi-arrow-right-short"></i>
                            <?= e(t('dashboard.view_more_pending', ['count' => $pendingCount - 5])) ?>
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ── Charts row ─────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">

    <!-- Line chart: Registrations over 30 days -->
    <div class="col-lg-8 fade-up">
        <div class="admin-card h-100 d-flex flex-column">
            <div class="admin-card-header">
                <div>
                    <h2 class="admin-card-title mb-0"><?= e(t('dashboard.new_residents_title')) ?></h2>
                    <p class="text-muted mb-0 mt-1" style="font-size:.78rem;">
                        <?= e(t('dashboard.new_residents_desc')) ?>
                    </p>
                </div>
                <span id="regChartBadge" class="badge text-bg-light border"
                      style="font-size:.7rem;"><?= e(t('dashboard.loading')) ?></span>
            </div>
            <div class="admin-card-body flex-grow-1"
                 style="padding:1.25rem 1.25rem 1rem;">
                <div id="regChartLoading" class="chart-placeholder">
                    <div class="spinner-border spinner-border-sm me-2" role="status"
                         style="color:var(--brand-primary);"></div>
                    <span><?= e(t('dashboard.loading_data')) ?></span>
                </div>
                <canvas id="registrationChart" style="display:none;max-height:280px;"></canvas>
            </div>
        </div>
    </div>

    <!-- Doughnut chart: Announcements by category -->
    <div class="col-lg-4 fade-up fade-up-delay-1">
        <div class="admin-card h-100 d-flex flex-column">
            <div class="admin-card-header">
                <div>
                    <h2 class="admin-card-title mb-0"><?= e(t('dashboard.announcements_by_category')) ?></h2>
                    <p class="text-muted mb-0 mt-1" style="font-size:.78rem;"><?= e(t('dashboard.all_statuses')) ?></p>
                </div>
                <span id="catChartBadge" class="badge text-bg-light border"
                      style="font-size:.7rem;"><?= e(t('dashboard.loading')) ?></span>
            </div>
            <div class="admin-card-body flex-grow-1"
                 style="padding:1.25rem 1.25rem 1rem;">
                <div id="catChartLoading" class="chart-placeholder">
                    <div class="spinner-border spinner-border-sm me-2" role="status"
                         style="color:var(--brand-primary);"></div>
                    <span><?= e(t('dashboard.loading_data')) ?></span>
                </div>
                <canvas id="categoryChart" style="display:none;max-height:280px;"></canvas>
            </div>
        </div>
    </div>

</div><!-- /charts row -->

<?php if (!$__isStaffView): ?>
<!-- ── Activity feed + Quick actions ─────────────────────────────── -->
<div class="row g-3">

    <!-- Quick actions + system summary -->
    <div class="col-12 d-grid gap-3 fade-up" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr));">

        <!-- Quick actions card -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h2 class="admin-card-title mb-0">
                    <i class="bi bi-lightning-charge-fill me-2" style="color:var(--motif-gold);"></i>
                    <?= e(t('dashboard.quick_actions')) ?>
                </h2>
            </div>
            <div class="admin-card-body d-flex flex-column gap-2">

                <a href="<?= e(route('admin/announcements/create')) ?>"
                   class="quick-action-btn">
                    <span class="qa-icon"><i class="bi bi-megaphone-fill"></i></span>
                    <div>
                        <div style="font-weight:700;font-size:.875rem;line-height:1.2;">
                            <?= e(t('dashboard.qa_new_announcement')) ?>
                        </div>
                        <div style="font-size:.73rem;color:var(--text-muted);">
                            <?= e(t('dashboard.qa_new_announcement_desc')) ?>
                        </div>
                    </div>
                </a>

                <a href="<?= e(route('admin/events/create')) ?>"
                   class="quick-action-btn">
                    <span class="qa-icon"><i class="bi bi-calendar-plus-fill"></i></span>
                    <div>
                        <div style="font-weight:700;font-size:.875rem;line-height:1.2;">
                            <?= e(t('dashboard.qa_new_event')) ?>
                        </div>
                        <div style="font-size:.73rem;color:var(--text-muted);">
                            <?= e(t('dashboard.qa_new_event_desc')) ?>
                        </div>
                    </div>
                </a>

                <a href="<?= e(route('admin/ordinances/upload')) ?>"
                   class="quick-action-btn">
                    <span class="qa-icon"><i class="bi bi-file-earmark-arrow-up-fill"></i></span>
                    <div>
                        <div style="font-weight:700;font-size:.875rem;line-height:1.2;">
                            <?= e(t('dashboard.qa_upload_ordinance')) ?>
                        </div>
                        <div style="font-size:.73rem;color:var(--text-muted);">
                            <?= e(t('dashboard.qa_upload_ordinance_desc')) ?>
                        </div>
                    </div>
                </a>

                <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'], true)): ?>
                <a href="<?= e(route('admin/residents')) ?>"
                   class="quick-action-btn">
                    <span class="qa-icon" style="background:var(--status-warning);">
                        <i class="bi bi-person-check-fill"></i>
                    </span>
                    <div>
                        <div style="font-weight:700;font-size:.875rem;line-height:1.2;">
                            <?= e(t('dashboard.qa_verify_resident')) ?>
                        </div>
                        <div style="font-size:.73rem;color:var(--text-muted);">
                            <?= $pendingCount > 0
                                ? e(t('dashboard.qa_pending_approval', ['count' => number_format($pendingCount)]))
                                : e(t('dashboard.qa_manage_residents')) ?>
                        </div>
                    </div>
                </a>

                <a href="<?= e(route('admin/system/seed-demo')) ?>"
                   class="quick-action-btn"
                   onclick="return confirm('Nais mo bang itanim o i-refresh ang sample / demo data sa sistema?')">
                    <span class="qa-icon" style="background:var(--brand-primary, #0d6efd);">
                        <i class="bi bi-database-fill-gear"></i>
                    </span>
                    <div>
                        <div style="font-weight:700;font-size:.875rem;line-height:1.2;">
                            Itanim ang Sample Data
                        </div>
                        <div style="font-size:.73rem;color:var(--text-muted);">
                            I-populate ang demo announcements, events, atbp.
                        </div>
                    </div>
                </a>
                <?php endif; ?>

            </div>
        </div>

        <!-- System summary -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h2 class="admin-card-title mb-0">
                    <i class="bi bi-bar-chart-fill me-2" style="color:var(--brand-primary);"></i>
                    <?= e(t('dashboard.system_summary')) ?>
                </h2>
            </div>
            <div class="admin-card-body">
                <div class="sys-row">
                    <span class="text-muted"><?= e(t('dashboard.sys_verified_residents')) ?></span>
                    <span class="fw-bold"><?= number_format($verifiedCount) ?></span>
                </div>
                <div class="sys-row">
                    <span class="text-muted"><?= e(t('dashboard.sys_published_announcements')) ?></span>
                    <span class="fw-bold"><?= number_format($publishedCount) ?></span>
                </div>
                <div class="sys-row">
                    <span class="text-muted"><?= e(t('dashboard.sys_upcoming_events')) ?></span>
                    <span class="fw-bold"><?= number_format($upcomingCount) ?></span>
                </div>
                <div class="sys-row">
                    <span class="text-muted"><?= e(t('dashboard.sys_pending_verify')) ?></span>
                    <span class="fw-bold"
                          style="<?= $pendingCount > 0 ? 'color:var(--status-warning);' : '' ?>">
                        <?= number_format($pendingCount) ?>
                    </span>
                </div>
            </div>
        </div>

    </div><!-- /right column -->

</div><!-- /bottom row -->

<?php endif; ?>

<!-- ── Chart scripts ─────────────────────────────────────────────── -->
<script>
(function () {
    'use strict';

    // Read lazily. This script is parsed as part of the page body, which the
    // layout renders *before* its footer script defines window.BarangGabay —
    // touching it at parse time throws and kills the whole chart block.
    const base = () => (window.BarangGabay?.baseUrl || '').replace(/\/$/, '');
    // CHANGED: chart colours retinted from the old forest-green/amber set
    // to the exact earth-palette hex values theme.css uses.
    const GREEN   = '#2f5d3a';
    const PALETTE = [
        '#2f5d3a', '#c8992e', '#17603a', '#8a5a12',
        '#6fae7e', '#a3311c', '#7a5c11', '#5b5240',
        '#e2b955', '#6b5d52',
    ];

    const I18N = <?= json_encode([
        'loaded'              => t('dashboard.js_loaded'),
        'error'               => t('dashboard.js_error'),
        'new_resident_label'  => t('dashboard.js_new_resident_label'),
        'tooltip_residents'   => t('dashboard.js_tooltip_residents'),
        'tooltip_announcements' => t('dashboard.js_tooltip_announcements'),
        'no_announcements'    => t('dashboard.js_no_announcements'),
        'load_error'          => t('dashboard.js_load_error'),
    ]) ?>;

    function setBadgeOk(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = I18N.loaded;
        el.style.cssText = 'font-size:.7rem;background:#e6ecdf;color:#3f6b34;border:1px solid #c3d4bb;border-radius:20px;padding:3px 8px;';
    }
    function setBadgeErr(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = I18N.error;
        el.style.cssText = 'font-size:.7rem;background:#f6ded7;color:#a3311c;border:1px solid #e8c0b3;border-radius:20px;padding:3px 8px;';
    }
    function showCanvas(loadingId, canvasId) {
        const loading = document.getElementById(loadingId);
        const canvas  = document.getElementById(canvasId);
        if (loading) loading.style.display = 'none';
        if (canvas)  canvas.style.display  = 'block';
    }
    function showError(loadingId, message) {
        const el = document.getElementById(loadingId);
        if (el) el.innerHTML =
            '<span style="color:#dc3545;font-size:.8rem;">' +
            '<i class="bi bi-x-circle me-1"></i>' + message + '</span>';
    }

    /* ── Line chart: resident registrations ──────────────────── */
    async function loadRegistrationChart() {
        try {
            const res = await fetch(base() + '/api/admin/charts/registrations');
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const { labels, values } = await res.json();

            showCanvas('regChartLoading', 'registrationChart');
            setBadgeOk('regChartBadge');

            new Chart(
                document.getElementById('registrationChart').getContext('2d'),
                {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label: I18N.new_resident_label,
                            data: values,
                            borderColor: GREEN,
                            backgroundColor: 'rgba(47,93,58,.09)',
                            borderWidth: 2.5,
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: GREEN,
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 3.5,
                            pointHoverRadius: 6,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        interaction: { intersect: false, mode: 'index' },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#10241a',
                                titleFont: { size: 12 },
                                bodyFont: { size: 12 },
                                callbacks: {
                                    label: ctx => '  ' + ctx.parsed.y + I18N.tooltip_residents,
                                },
                            },
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: {
                                    maxTicksLimit: 9,
                                    font: { size: 11 },
                                    color: '#6b5d52',
                                },
                                border: { color: '#e4d7c2' },
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    precision: 0,
                                    font: { size: 11 },
                                    color: '#6b5d52',
                                },
                                grid: { color: 'rgba(16,36,26,.05)' },
                                border: { color: '#e4d7c2', dash: [4, 4] },
                            },
                        },
                    },
                }
            );
        } catch (err) {
            console.error('Registration chart:', err);
            setBadgeErr('regChartBadge');
            showError('regChartLoading', I18N.load_error);
        }
    }

    /* ── Doughnut chart: announcements by category ───────────── */
    async function loadCategoryChart() {
        try {
            const res = await fetch(base() + '/api/admin/charts/announcement-categories');
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const { labels, values } = await res.json();

            if (!labels || !labels.length) {
                showError('catChartLoading', I18N.no_announcements);
                setBadgeErr('catChartBadge');
                return;
            }

            showCanvas('catChartLoading', 'categoryChart');
            setBadgeOk('catChartBadge');

            new Chart(
                document.getElementById('categoryChart').getContext('2d'),
                {
                    type: 'doughnut',
                    data: {
                        labels,
                        datasets: [{
                            data: values,
                            backgroundColor: PALETTE,
                            borderWidth: 2.5,
                            borderColor: '#ffffff',
                            hoverOffset: 8,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        cutout: '62%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    boxWidth: 12,
                                    padding: 12,
                                    font: { size: 11 },
                                    color: '#4a3f38',
                                },
                            },
                            tooltip: {
                                backgroundColor: '#10241a',
                                callbacks: {
                                    label: ctx => '  ' + ctx.label + ': ' + ctx.parsed + I18N.tooltip_announcements,
                                },
                            },
                        },
                    },
                }
            );
        } catch (err) {
            console.error('Category chart:', err);
            setBadgeErr('catChartBadge');
            showError('catChartLoading', I18N.load_error);
        }
    }

    /* Boot — Chart.js CDN loads synchronously before Alpine defer */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            loadRegistrationChart();
            loadCategoryChart();
        });
    } else {
        loadRegistrationChart();
        loadCategoryChart();
    }
}());
</script>

<?php
$content   = ob_get_clean();
$pageTitle = $pageTitle ?? t('dashboard.title');
require __DIR__ . '/../layouts/admin.php';