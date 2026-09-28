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
.quick-action-btn .qa-icon { width:34px; height:34px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:.95rem; flex-shrink:0; background:var(--brand-primary); color:#fff; }
.chart-placeholder { display:flex; align-items:center; justify-content:center; min-height:200px; color:var(--text-muted); font-size:.85rem; }
.sys-row { display:flex; justify-content:space-between; align-items:center; padding:7px 0; border-bottom:1px solid var(--tb-border); font-size:.845rem; color:var(--text-primary); }
.sys-row:last-child { border-bottom:none; padding-bottom:0; }
.sys-row .text-muted { color:var(--text-secondary) !important; }
</style>

<!-- ── Page heading ──────────────────────────────────────────────── -->
<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <p class="mb-0"
           style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);">
            <?= e(t('dashboard.eyebrow')) ?>
        </p>
        <h1 class="mb-0 mt-1"
            style="font-size:1.55rem;font-weight:800;color:var(--text-primary);line-height:1.1;">
            <?= e(t('dashboard.title')) ?>
        </h1>
    </div>
    <span class="text-muted" style="font-size:.8rem;padding-top:4px;">
        <?= e(date('l, F j, Y')) ?>
    </span>
</div>

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
            <?= e(t('dashboard.attention_title')) ?>
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
                      style="background:var(--brand-primary);color:#fff;font-size:.72rem;font-weight:700;">
                    <?= (int) $item['count'] ?>
                </span>
                <i class="bi bi-chevron-right" style="color:var(--text-muted);font-size:.75rem;"></i>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($todayEvents || $weekEvents): ?>
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

<!-- ── Stat cards ─────────────────────────────────────────────────────
     Each card is one link over its whole surface, and each one opens the
     list FILTERED to exactly what the card counted. A card reading "1
     verified resident" that opened the full resident list would have made
     the reader do the counting a second time.

     One anchor per card rather than a small link inside it: the number is
     the thing people aim at, and a 12-pixel "Manage" beneath it is a poor
     target on a phone. That also means no nested anchors — the old inner
     links are gone, replaced by a hint line that is part of the card.

     All four destinations sit behind the same role:admin,staff as this
     dashboard, so no card can lead a staff member into a 403. -->
<div class="row g-3 mb-4">

    <?php
    $statCards = [
        [
            'label'  => t('dashboard.stat_verified'),
            'value'  => (int) $verifiedCount,
            'href'   => route('admin/residents') . '?status=verified',
            'icon'   => 'bi-people-fill',
            'tone'   => 'stat-icon-green',
            'hint'   => t('dashboard.stat_verified_sub'),
            'action' => t('dashboard.stat_verified_cta'),
            'delay'  => '',
        ],
        [
            'label'  => t('dashboard.stat_pending'),
            'value'  => (int) $pendingCount,
            'href'   => route('admin/residents') . '?status=pending',
            'icon'   => 'bi-clock-fill',
            'tone'   => 'stat-icon-yellow',
            // With nothing waiting, the card still opens the queue — it just
            // says so plainly instead of implying there is work to do.
            'hint'   => $pendingCount > 0 ? '' : t('dashboard.stat_pending_none'),
            'action' => $pendingCount > 0 ? t('dashboard.stat_pending_cta') : t('dashboard.stat_pending_view'),
            'urgent' => $pendingCount > 0,
            'delay'  => 'fade-up-delay-1',
        ],
        [
            'label'  => t('dashboard.stat_published'),
            'value'  => (int) $publishedCount,
            'href'   => route('admin/announcements') . '?status=published',
            'icon'   => 'bi-megaphone-fill',
            'tone'   => 'stat-icon-blue',
            'hint'   => '',
            'action' => t('dashboard.stat_published_cta'),
            'delay'  => 'fade-up-delay-2',
        ],
        [
            'label'  => t('dashboard.stat_upcoming'),
            'value'  => (int) $upcomingCount,
            'href'   => route('admin/events') . '?status=upcoming',
            'icon'   => 'bi-calendar-event-fill',
            'tone'   => 'stat-icon-green',
            'hint'   => '',
            'action' => t('dashboard.stat_upcoming_cta'),
            'delay'  => 'fade-up-delay-3',
        ],
    ];
    ?>

    <?php foreach ($statCards as $__c):
        $__urgent = !empty($__c['urgent']); ?>
    <div class="col-sm-6 col-xl-3 fade-up <?= e($__c['delay']) ?>">
        <?php /* data-accent keeps the existing gold top-bar treatment on the
                 pending card — see .stat-card[data-accent="gold"] in admin.css. */ ?>
        <a class="stat-card stat-card-link<?= $__urgent ? ' stat-card-urgent' : '' ?>"
           <?= $__urgent ? 'data-accent="gold"' : '' ?>
           href="<?= e($__c['href']) ?>"
           aria-label="<?= e($__c['label'] . ': ' . number_format($__c['value']) . '. ' . $__c['action']) ?>">
            <div class="d-flex align-items-start justify-content-between gap-3">
                <div style="min-width:0;">
                    <p class="stat-card-label"><?= e($__c['label']) ?></p>
                    <p class="stat-card-value"<?= $__urgent ? ' style="color:var(--status-warning);"' : '' ?>>
                        <span data-countup="<?= $__c['value'] ?>"><?= number_format($__c['value']) ?></span>
                    </p>
                    <?php if ($__c['hint'] !== ''): ?>
                    <p class="mb-0" style="font-size:.73rem;color:var(--text-muted);"><?= e($__c['hint']) ?></p>
                    <?php endif; ?>
                    <span class="stat-card-action<?= $__urgent ? ' stat-card-action-urgent' : '' ?>">
                        <?= e($__c['action']) ?><i class="bi bi-arrow-right-short" aria-hidden="true"></i>
                    </span>
                </div>
                <div class="stat-card-icon <?= e($__c['tone']) ?> flex-shrink-0">
                    <i class="bi <?= e($__c['icon']) ?>" aria-hidden="true"></i>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>

</div><!-- /stat cards -->

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

<!-- ── Activity feed + Quick actions ─────────────────────────────── -->
<div class="row g-3">

    <!-- Recent activity feed -->
    <div class="col-lg-8 fade-up">
        <div class="admin-card">
            <div class="admin-card-header">
                <div>
                    <h2 class="admin-card-title mb-0">
                        <i class="bi bi-activity me-2" style="color:var(--brand-primary);"></i>
                        <?= e(t('dashboard.recent_activity')) ?>
                        <span class="live-dot ms-2" title="Live"></span>
                    </h2>
                    <p class="text-muted mb-0 mt-1" style="font-size:.78rem;">
                        <?= e(t('dashboard.recent_activity_desc')) ?>
                    </p>
                </div>
                <span class="badge text-bg-light border" style="font-size:.7rem;">
                    <?= count($recentActivity) ?> <?= e(t('dashboard.entries_suffix')) ?>
                </span>
            </div>

            <?php if (empty($recentActivity)): ?>
            <div class="admin-card-body text-center py-5">
                <i class="bi bi-clock-history" style="font-size:2.5rem;color:var(--border);"></i>
                <p class="mt-3 mb-0 text-muted" style="font-size:.855rem;">
                    <?= e(t('dashboard.no_activity')) ?>
                </p>
            </div>
            <?php else: ?>
            <ul class="list-unstyled mb-0">
                <?php foreach ($recentActivity as $log):
                    [$iconClass, $iconBg, $iconColor] = $actionIcon($log['action'] ?? '');
                    $description = $log['description']
                        ?: ucwords(str_replace(['.', '_'], [': ', ' '], $log['action'] ?? 'system'));
                    $userName    = $log['user_name'] ?? t('dashboard.system_user');
                    $timeStr     = $log['created_at']
                        ? date('M j, Y · g:i A', strtotime($log['created_at']))
                        : '—';
                ?>
                <li class="activity-item">
                    <div class="activity-icon"
                         style="background:<?= $iconBg ?>;color:<?= $iconColor ?>;">
                        <i class="bi <?= $iconClass ?>"></i>
                    </div>
                    <div style="min-width:0;flex:1;">
                        <p class="activity-text"><?= e($description) ?></p>
                        <p class="activity-meta">
                            <i class="bi bi-person" style="font-size:.65rem;"></i>
                            <?= e($userName) ?>
                            &nbsp;&middot;&nbsp;
                            <i class="bi bi-clock" style="font-size:.65rem;"></i>
                            <?= e($timeStr) ?>
                        </p>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick actions + system summary -->
    <div class="col-lg-4 d-flex flex-column gap-3 fade-up fade-up-delay-1">

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
        '#2f5d3a', '#c8992e', '#7b1e22', '#8a5a12',
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
                                backgroundColor: '#241b18',
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
                                grid: { color: 'rgba(36,27,24,.05)' },
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
                                backgroundColor: '#241b18',
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