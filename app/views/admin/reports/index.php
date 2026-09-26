<?php
/**
 * Admin reports & analytics dashboard.
 * Variables: $residentStats, $announcementStats, $eventStats, $ordinanceStats,
 *            $aiUsage (int), $feedbackStats, $regData, $catData
 */
$residentStats     = $residentStats     ?? ['total' => 0, 'verified' => 0, 'pending' => 0, 'suspended' => 0];
$announcementStats = $announcementStats ?? ['total' => 0, 'published' => 0, 'draft' => 0];
$eventStats        = $eventStats        ?? ['total' => 0, 'upcoming' => 0, 'ongoing' => 0, 'completed' => 0, 'cancelled' => 0];
$ordinanceStats    = $ordinanceStats    ?? ['total' => 0, 'active' => 0, 'draft' => 0, 'repealed' => 0];
$aiUsage           = (int) ($aiUsage    ?? 0);
$feedbackStats     = $feedbackStats     ?? ['total' => 0, 'unanswered' => 0];
$regData           = $regData           ?? ['labels' => [], 'values' => []];
$catData           = $catData           ?? ['labels' => [], 'values' => []];
$translationStats  = $translationStats  ?? ['total' => 0, 'this_month' => 0];
$translationLogs   = $translationLogs   ?? [];
$translationByType = $translationByType ?? [];

ob_start();
?>

<!-- ── Summary stat cards ──────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">

    <div class="col-6 col-lg-3">
        <div class="admin-card text-center py-4" style="border-top:3px solid #1a6b3a;">
            <p class="fs-2 fw-black mb-0" style="color:var(--brand-primary);"><?= $residentStats['total'] ?></p>
            <p class="small fw-semibold text-muted mb-0"><?= e(t('admin_reports.stat_total_residents')) ?></p>
            <p class="text-muted mb-0" style="font-size:.7rem;">
                <?= t('admin_reports.stat_verified_pending', ['verified' => $residentStats['verified'], 'pending' => $residentStats['pending']]) ?>
            </p>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="admin-card text-center py-4" style="border-top:3px solid #3b82f6;">
            <p class="fs-2 fw-black mb-0" style="color:#3b82f6;"><?= $announcementStats['total'] ?></p>
            <p class="small fw-semibold text-muted mb-0"><?= e(t('admin_reports.stat_announcements')) ?></p>
            <p class="text-muted mb-0" style="font-size:.7rem;">
                <?= t('admin_reports.stat_published_draft', ['published' => $announcementStats['published'], 'draft' => $announcementStats['draft']]) ?>
            </p>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="admin-card text-center py-4" style="border-top:3px solid #f59e0b;">
            <p class="fs-2 fw-black mb-0" style="color:#f59e0b;"><?= $eventStats['total'] ?></p>
            <p class="small fw-semibold text-muted mb-0"><?= e(t('admin_reports.stat_events')) ?></p>
            <p class="text-muted mb-0" style="font-size:.7rem;">
                <?= t('admin_reports.stat_upcoming_ongoing', ['upcoming' => $eventStats['upcoming'], 'ongoing' => $eventStats['ongoing']]) ?>
            </p>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="admin-card text-center py-4" style="border-top:3px solid #8b5cf6;">
            <p class="fs-2 fw-black mb-0" style="color:#8b5cf6;"><?= $aiUsage ?></p>
            <p class="small fw-semibold text-muted mb-0"><?= e(t('admin_reports.stat_ai_simplifications')) ?></p>
            <p class="text-muted mb-0" style="font-size:.7rem;">
                <?= t('admin_reports.stat_feedback_unanswered', ['total' => $feedbackStats['total'], 'unanswered' => $feedbackStats['unanswered']]) ?>
            </p>
        </div>
    </div>

</div>

<!-- ── Charts row ─────────────────────────────────────────────────────────── -->
<div class="row g-4 mb-4">

    <!-- Registrations line chart -->
    <div class="col-lg-8">
        <div class="admin-card h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-bold mb-0"><?= e(t('admin_reports.chart_registrations_title')) ?></h6>
                <span class="badge" style="background:#d4edda;border:1px solid #c3e6cb;color:#155724;">
                    <?= array_sum($regData['values']) ?> <?= e(t('admin_reports.total_suffix')) ?>
                </span>
            </div>
            <canvas id="regChart" height="110"></canvas>
        </div>
    </div>

    <!-- Announcements doughnut -->
    <div class="col-lg-4">
        <div class="admin-card h-100">
            <h6 class="fw-bold mb-3"><?= e(t('admin_reports.chart_announcements_category')) ?></h6>
            <?php if (array_sum($catData['values']) > 0): ?>
            <canvas id="catChart" height="180"></canvas>
            <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-bar-chart-line fs-2 d-block mb-2 opacity-25"></i>
                <p class="small mb-0"><?= e(t('admin_reports.no_data')) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ── Second charts row ──────────────────────────────────────────────────── -->
<div class="row g-4 mb-4">

    <!-- Resident status pie -->
    <div class="col-sm-6 col-lg-4">
        <div class="admin-card">
            <h6 class="fw-bold mb-3"><?= e(t('admin_reports.chart_resident_status')) ?></h6>
            <canvas id="residentPieChart" height="200"></canvas>
            <div class="mt-3 d-flex flex-wrap gap-2 justify-content-center">
                <span class="badge" style="background:#d1fae5;color:#065f46;font-weight:600;padding:.35rem .6rem;">
                    ✔ <?= e(t('admin_reports.badge_verified')) ?>: <?= $residentStats['verified'] ?>
                </span>
                <span class="badge" style="background:#fef3c7;color:#92400e;font-weight:600;padding:.35rem .6rem;">
                    ⏳ <?= e(t('admin_reports.badge_pending')) ?>: <?= $residentStats['pending'] ?>
                </span>
                <span class="badge" style="background:#fee2e2;color:#991b1b;font-weight:600;padding:.35rem .6rem;">
                    ✕ <?= e(t('admin_reports.badge_suspended')) ?>: <?= $residentStats['suspended'] ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Event status pie -->
    <div class="col-sm-6 col-lg-4">
        <div class="admin-card">
            <h6 class="fw-bold mb-3"><?= e(t('admin_reports.chart_events_status')) ?></h6>
            <?php if ($eventStats['total'] > 0): ?>
            <canvas id="eventChart" height="200"></canvas>
            <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-calendar-x fs-2 d-block mb-2 opacity-25"></i>
                <p class="small mb-0"><?= e(t('admin_reports.no_events')) ?></p>
                <p class="small text-muted opacity-75"><?= e(t('admin_reports.chart_appears_events')) ?></p>
            </div>
            <?php endif; ?>
            <div class="mt-3 d-flex flex-wrap gap-2 justify-content-center">
                <span class="badge" style="background:#dbeafe;color:#1e40af;font-weight:600;padding:.35rem .6rem;">
                    <?= e(t('admin_events.status_upcoming')) ?>: <?= $eventStats['upcoming'] ?>
                </span>
                <span class="badge" style="background:#dcfce7;color:#166534;font-weight:600;padding:.35rem .6rem;">
                    <?= e(t('admin_events.status_ongoing')) ?>: <?= $eventStats['ongoing'] ?>
                </span>
                <span class="badge" style="background:#f1f5f9;color:#475569;font-weight:600;padding:.35rem .6rem;">
                    <?= e(t('admin_events.status_completed')) ?>: <?= $eventStats['completed'] ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Ordinance status pie -->
    <div class="col-sm-6 col-lg-4">
        <div class="admin-card">
            <h6 class="fw-bold mb-3"><?= e(t('admin_reports.chart_ordinances_status')) ?></h6>
            <?php if ($ordinanceStats['total'] > 0): ?>
            <canvas id="ordinanceChart" height="200"></canvas>
            <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-journal-x fs-2 d-block mb-2 opacity-25"></i>
                <p class="small mb-0"><?= e(t('admin_reports.no_ordinances')) ?></p>
                <p class="small text-muted opacity-75"><?= e(t('admin_reports.chart_appears_upload')) ?></p>
            </div>
            <?php endif; ?>
            <div class="mt-3 d-flex flex-wrap gap-2 justify-content-center">
                <span class="badge" style="background:#d1fae5;color:#065f46;font-weight:600;padding:.35rem .6rem;">
                    <?= e(t('admin_reports.badge_active')) ?>: <?= $ordinanceStats['active'] ?>
                </span>
                <span class="badge" style="background:#fef3c7;color:#92400e;font-weight:600;padding:.35rem .6rem;">
                    <?= e(t('admin_reports.badge_draft')) ?>: <?= $ordinanceStats['draft'] ?>
                </span>
                <span class="badge" style="background:#fee2e2;color:#991b1b;font-weight:600;padding:.35rem .6rem;">
                    <?= e(t('admin_reports.badge_repealed')) ?>: <?= $ordinanceStats['repealed'] ?>
                </span>
            </div>
        </div>
    </div>

</div>

<!-- ── Manobo Translation Usage ────────────────────────────────────────────── -->
<div class="admin-card mb-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold mb-0">
            <?= e(t('admin_reports.manobo_usage_title')) ?>
        </h6>
        <div class="d-flex gap-2">
            <span class="badge" style="background:#d1fae5;color:#065f46;font-weight:600;padding:.35rem .6rem;">
                <?= $translationStats['this_month'] ?> <?= e(t('admin_reports.this_month_suffix')) ?>
            </span>
            <span class="badge" style="background:#e0f2fe;color:#0369a1;font-weight:600;padding:.35rem .6rem;">
                <?= $translationStats['total'] ?> <?= e(t('admin_reports.total_suffix')) ?>
            </span>
        </div>
    </div>

    <?php if (!empty($translationByType)): ?>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <?php
        $typeLabels = ['announcement' => t('nav.announcements'), 'event' => t('nav.events'), 'ordinance' => t('nav.ordinances')];
        $typeColors = ['announcement' => '#dcfce7;color:#166534', 'event' => '#dbeafe;color:#1e40af', 'ordinance' => '#fef3c7;color:#92400e'];
        foreach ($translationByType as $row):
            $tLabel = $typeLabels[$row['content_type']] ?? ucfirst($row['content_type']);
            $tColor = $typeColors[$row['content_type']] ?? '#f1f5f9;color:#475569';
        ?>
        <span class="badge" style="background:<?= $tColor ?>;font-weight:600;padding:.35rem .6rem;">
            <?= e($tLabel) ?>: <?= (int) $row['total'] ?>
        </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($translationLogs)): ?>
    <p class="text-muted small mb-0">
        <i class="bi bi-translate me-1 opacity-50"></i>
        <?= e(t('admin_reports.no_translations')) ?>
    </p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" style="font-size:.825rem;">
            <thead class="table-light">
                <tr>
                    <th><?= e(t('admin_feedback.col_date')) ?></th>
                    <th><?= e(t('residents.col_resident')) ?></th>
                    <th><?= e(t('admin_reports.col_content_type')) ?></th>
                    <th><?= e(t('admin_reports.col_content_id')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($translationLogs as $log): ?>
                <tr>
                    <td class="text-muted"><?= e(date('M j, Y g:i A', strtotime($log['created_at']))) ?></td>
                    <td><?= e($log['user_name'] ?? '—') ?></td>
                    <td>
                        <?php
                        $badgeStyle = match($log['content_type']) {
                            'announcement' => 'background:#d4edda;border:1px solid #c3e6cb;color:#155724;',
                            'event'        => 'background:#dbeafe;border:1px solid #bfdbfe;color:#1e40af;',
                            'ordinance'    => 'background:#fff3cd;border:1px solid #ffe69c;color:#856404;',
                            default        => 'background:#f1f5f9;border:1px solid #cbd5e1;color:#475569;',
                        };
                        ?>
                        <span class="badge" style="<?= $badgeStyle ?>">
                            <?= e(ucfirst($log['content_type'])) ?>
                        </span>
                    </td>
                    <td class="font-monospace text-muted">#<?= (int) $log['content_id'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ── Export card ─────────────────────────────────────────────────────────── -->
<div class="admin-card">
    <h6 class="fw-bold mb-3"><i class="bi bi-download me-2"></i><?= e(t('admin_reports.export_title')) ?></h6>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= e(route('admin/residents')) ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-people me-1"></i> <?= e(t('admin_reports.view_residents_btn')) ?>
        </a>
        <a href="<?= e(route('admin/announcements')) ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-megaphone me-1"></i> <?= e(t('admin_reports.view_announcements_btn')) ?>
        </a>
        <a href="<?= e(route('admin/feedback')) ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chat-dots me-1"></i> <?= e(t('admin_reports.view_feedback_btn')) ?>
        </a>
        <button onclick="window.print()" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-printer me-1"></i> <?= e(t('admin_reports.print_report_btn')) ?>
        </button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const gridColor = 'rgba(0,0,0,.06)';
    const repI18n = <?= json_encode([
        'newResidents'  => t('dashboard.js_new_resident_label'),
        'tooltipResidents' => t('admin_reports.js_resident_suffix'),
        'annSingular'   => t('admin_reports.js_announcement_singular'),
        'annPlural'     => t('admin_reports.js_announcement_plural'),
        'evtSingular'   => t('admin_reports.js_event_singular'),
        'evtPlural'     => t('admin_reports.js_event_plural'),
        'ordinanceSuffix' => t('admin_reports.js_ordinance_suffix'),
        'verified'      => t('admin_reports.badge_verified'),
        'pending'       => t('admin_reports.badge_pending'),
        'suspended'     => t('admin_reports.badge_suspended'),
        'upcoming'      => t('admin_events.status_upcoming'),
        'ongoing'       => t('admin_events.status_ongoing'),
        'completed'     => t('admin_events.status_completed'),
        'cancelled'     => t('admin_events.status_cancelled'),
        'active'        => t('admin_reports.badge_active'),
        'draft'         => t('admin_reports.badge_draft'),
        'repealed'      => t('admin_reports.badge_repealed'),
    ]) ?>;

    // ── Registrations line chart ──────────────────────────────────────────
    const regCtx = document.getElementById('regChart');
    if (regCtx) {
        const regValues = <?= json_encode($regData['values']) ?>;
        // Compute a sensible y-axis ceiling so a small data point doesn't sit
        // right at the top edge of the chart (e.g. max=1 → ceiling=3).
        const regPeak   = Math.max(...regValues, 0);
        const regCeil   = regPeak <= 1 ? Math.max(regPeak + 2, 3)   // flat/sparse — give headroom
                        : regPeak <= 5 ? regPeak + 2
                        : Math.ceil(regPeak * 1.25);                 // 25 % headroom for larger sets

        new Chart(regCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($regData['labels']) ?>,
                datasets: [{
                    label: repI18n.newResidents,
                    data:  regValues,
                    borderColor:          '#1a6b3a',
                    backgroundColor:      'rgba(26,107,58,.10)',
                    borderWidth:          2.5,
                    pointRadius:          4,
                    pointHoverRadius:     6,
                    pointBackgroundColor: '#1a6b3a',
                    pointBorderColor:     '#fff',
                    pointBorderWidth:     2,
                    fill:                 true,
                    tension:              0.4,
                }],
            },
            options: {
                responsive: true,
                plugins: {
                    legend:  { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' ' + ctx.parsed.y + repI18n.tooltipResidents,
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max:         regCeil,
                        ticks:       { stepSize: 1, precision: 0 },
                        grid:        { color: gridColor },
                    },
                    x: {
                        grid:  { display: false },
                        ticks: { maxTicksLimit: 10, maxRotation: 0 },
                    },
                },
            },
        });
    }

    // ── Announcements by category doughnut ───────────────────────────────
    const catCtx = document.getElementById('catChart');
    if (catCtx) {
        const palette = ['#1a6b3a','#3b82f6','#f59e0b','#ef4444','#8b5cf6','#06b6d4'];
        new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels:   <?= json_encode($catData['labels']) ?>,
                datasets: [{
                    data:            <?= json_encode($catData['values']) ?>,
                    backgroundColor: palette,
                    borderWidth:     2,
                    borderColor:     '#fff',
                    hoverOffset:     6,
                }],
            },
            options: {
                responsive: true,
                cutout: '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels:   { boxWidth: 12, font: { size: 11 }, padding: 12 },
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' ' + ctx.label + ': ' + ctx.parsed + ' ' + (ctx.parsed !== 1 ? repI18n.annPlural : repI18n.annSingular),
                        },
                    },
                },
            },
        });
    }

    // ── Resident verification doughnut ────────────────────────────────────
    const resCtx = document.getElementById('residentPieChart');
    if (resCtx) {
        new Chart(resCtx, {
            type: 'doughnut',
            data: {
                labels:   [repI18n.verified, repI18n.pending, repI18n.suspended],
                datasets: [{
                    data:            [<?= $residentStats['verified'] ?>, <?= $residentStats['pending'] ?>, <?= $residentStats['suspended'] ?>],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                    borderWidth:     2,
                    borderColor:     '#fff',
                    hoverOffset:     6,
                }],
            },
            options: {
                responsive: true,
                cutout: '65%',
                plugins: {
                    legend: {
                        display:  true,
                        position: 'bottom',
                        labels:   { boxWidth: 12, font: { size: 11 }, padding: 10 },
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' ' + ctx.label + ': ' + ctx.parsed + repI18n.tooltipResidents,
                        },
                    },
                },
            },
        });
    }

    // ── Events by status doughnut (only rendered when total > 0) ─────────
    const evCtx = document.getElementById('eventChart');
    if (evCtx) {
        new Chart(evCtx, {
            type: 'doughnut',
            data: {
                labels:   [repI18n.upcoming, repI18n.ongoing, repI18n.completed, repI18n.cancelled],
                datasets: [{
                    data:            [<?= $eventStats['upcoming'] ?>, <?= $eventStats['ongoing'] ?>, <?= $eventStats['completed'] ?>, <?= $eventStats['cancelled'] ?>],
                    backgroundColor: ['#3b82f6', '#10b981', '#94a3b8', '#ef4444'],
                    borderWidth:     2,
                    borderColor:     '#fff',
                    hoverOffset:     6,
                }],
            },
            options: {
                responsive: true,
                cutout: '65%',
                plugins: {
                    legend: {
                        display:  true,
                        position: 'bottom',
                        labels:   { boxWidth: 12, font: { size: 11 }, padding: 10 },
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' ' + ctx.label + ': ' + ctx.parsed + ' ' + (ctx.parsed !== 1 ? repI18n.evtPlural : repI18n.evtSingular),
                        },
                    },
                },
            },
        });
    }

    // ── Ordinances by status doughnut (only rendered when total > 0) ─────
    const ordCtx = document.getElementById('ordinanceChart');
    if (ordCtx) {
        new Chart(ordCtx, {
            type: 'doughnut',
            data: {
                labels:   [repI18n.active, repI18n.draft, repI18n.repealed],
                datasets: [{
                    data:            [<?= $ordinanceStats['active'] ?>, <?= $ordinanceStats['draft'] ?>, <?= $ordinanceStats['repealed'] ?>],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                    borderWidth:     2,
                    borderColor:     '#fff',
                    hoverOffset:     6,
                }],
            },
            options: {
                responsive: true,
                cutout: '65%',
                plugins: {
                    legend: {
                        display:  true,
                        position: 'bottom',
                        labels:   { boxWidth: 12, font: { size: 11 }, padding: 10 },
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' ' + ctx.label + ': ' + ctx.parsed + repI18n.ordinanceSuffix,
                        },
                    },
                },
            },
        });
    }

});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';