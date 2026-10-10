<?php
/**
 * Super Admin — AI Accuracy (module feature 5).
 *
 * Scores the AI's ID-verification verdicts against what staff actually
 * decided. Staff approval/suspension is treated as ground truth.
 */
$stats        = $stats        ?? ['total' => 0, 'scored' => 0, 'correct' => 0, 'incorrect' => 0,
                                 'pending' => 0, 'not_scored' => 0, 'accuracy' => 0.0,
                                 'false_approvals' => 0, 'false_rejections' => 0];
$byConfidence = $byConfidence ?? [];
$trend        = $trend        ?? [];
$predictions  = $predictions  ?? [];
$total        = $total        ?? 0;
$page         = $page         ?? 1;
$perPage      = $perPage      ?? 25;
$outcome      = $outcome      ?? '';
$totalPages   = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

$hasData = $stats['scored'] > 0;

$outcomeMeta = [
    'correct'    => ['pill-ok',   t('superadmin.ai_correct')],
    'incorrect'  => ['pill-fail', t('superadmin.ai_incorrect')],
    'pending'    => ['pill-warn', t('superadmin.ai_pending')],
    'not_scored' => ['pill-idle', t('superadmin.ai_not_scored')],
];
$statusLabels = [
    'ai_passed'     => t('superadmin.ai_status_passed'),
    'ai_flagged'    => t('superadmin.ai_status_flagged'),
    'manual_review' => t('superadmin.ai_status_manual'),
    'skipped'       => t('superadmin.ai_status_skipped'),
    'pdf_manual'    => t('superadmin.ai_status_pdf'),
];

ob_start();
?>

<style>
    .pill { font-size:.68rem; font-weight:700; border-radius:20px; padding:2px 10px; white-space:nowrap; }
    .pill-ok   { background:#dcfce7; color:#166534; }
    .pill-fail { background:#fee2e2; color:#991b1b; }
    .pill-warn { background:#fef9c3; color:#92400e; }
    .pill-idle { background:#f1f5f9; color:#475569; }

    :root[data-theme="dark"] .pill-ok   { background:rgba(22,163,74,.22);  color:#86efac; }
    :root[data-theme="dark"] .pill-fail { background:rgba(220,38,38,.22);  color:#fca5a5; }
    :root[data-theme="dark"] .pill-warn { background:rgba(217,119,6,.22);  color:#fcd34d; }
    :root[data-theme="dark"] .pill-idle { background:rgba(148,163,184,.20);color:#cbd5e1; }
    @media (prefers-color-scheme: dark) {
        :root:not([data-theme="light"]) .pill-ok   { background:rgba(22,163,74,.22);  color:#86efac; }
        :root:not([data-theme="light"]) .pill-fail { background:rgba(220,38,38,.22);  color:#fca5a5; }
        :root:not([data-theme="light"]) .pill-warn { background:rgba(217,119,6,.22);  color:#fcd34d; }
        :root:not([data-theme="light"]) .pill-idle { background:rgba(148,163,184,.20);color:#cbd5e1; }
    }
    .acc-big { font-size:2.6rem; font-weight:800; line-height:1; color:var(--brand-primary); }
    .bar-track { height:8px; border-radius:99px; background:var(--surface-muted,#e2e8f0); overflow:hidden; }
    .bar-fill  { height:100%; border-radius:99px; background:#16a34a; }
</style>

<!-- ── Page header ─────────────────────────────────────────────────────── -->
<div class="mb-4">
    <p class="mb-0" style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);">
        <?= e(t('admin_nav.system')) ?>
    </p>
    <h1 class="mb-0 mt-1" style="font-size:1.55rem;font-weight:800;color:var(--text-primary);line-height:1.1;">
        <?= e(t('superadmin.ai_title')) ?>
    </h1>
    <p class="text-muted mt-1 mb-0" style="font-size:.82rem;">
        <?= e(t('superadmin.ai_subtitle')) ?>
    </p>
</div>

<!-- ── How this is measured ────────────────────────────────────────────── -->
<div style="background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.30);border-radius:10px;
            padding:14px 18px;margin-bottom:1.5rem;display:flex;gap:12px;align-items:flex-start;">
    <i class="bi bi-info-circle" style="color:#3b82f6;font-size:1.1rem;flex-shrink:0;margin-top:2px;"></i>
    <p style="margin:0;font-size:.83rem;line-height:1.7;color:var(--text-primary);">
        <?= e(t('superadmin.ai_method')) ?>
    </p>
</div>

<?php if (!$hasData): ?>
<div class="admin-card text-center" style="padding:2.5rem 1.5rem;">
    <i class="bi bi-graph-up" style="font-size:2.2rem;color:var(--text-muted);"></i>
    <h2 style="font-size:1.05rem;font-weight:700;color:var(--text-primary);margin:.75rem 0 .4rem;">
        <?= e(t('superadmin.ai_no_data_title')) ?>
    </h2>
    <p class="text-muted mb-0" style="font-size:.85rem;line-height:1.8;max-width:620px;margin:0 auto;">
        <?= e(t('superadmin.ai_no_data_body')) ?>
    </p>
    <?php if ($stats['pending'] > 0 || $stats['not_scored'] > 0): ?>
    <p class="mt-3 mb-0" style="font-size:.82rem;color:var(--text-muted);">
        <?= e(t('superadmin.ai_waiting', [
            'pending'    => $stats['pending'],
            'not_scored' => $stats['not_scored'],
        ])) ?>
    </p>
    <?php endif; ?>
</div>
<?php else: ?>

<!-- ── Headline metrics ────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="admin-card h-100 text-center" style="display:flex;flex-direction:column;justify-content:center;">
            <p style="font-size:.7rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-muted);margin:0 0 8px;">
                <?= e(t('superadmin.ai_accuracy')) ?>
            </p>
            <p class="acc-big mb-2"><?= e(number_format($stats['accuracy'], 1)) ?>%</p>
            <div class="bar-track mb-2" style="max-width:220px;margin:0 auto;">
                <div class="bar-fill" style="width:<?= (float) $stats['accuracy'] ?>%;"></div>
            </div>
            <p class="text-muted mb-0" style="font-size:.78rem;">
                <?= e(t('superadmin.ai_of_scored', ['correct' => $stats['correct'], 'scored' => $stats['scored']])) ?>
            </p>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="row g-3 h-100">
            <?php
            $cards = [
                ['superadmin.ai_correct',          $stats['correct'],          'bi-check-circle',      '#16a34a'],
                ['superadmin.ai_incorrect',        $stats['incorrect'],        'bi-x-circle',          '#dc2626'],
                ['superadmin.ai_false_approvals',  $stats['false_approvals'],  'bi-shield-exclamation','#dc2626'],
                ['superadmin.ai_false_rejections', $stats['false_rejections'], 'bi-person-x',          '#d97706'],
                ['superadmin.ai_pending',          $stats['pending'],          'bi-hourglass-split',   '#0ea5e9'],
                ['superadmin.ai_not_scored',       $stats['not_scored'],       'bi-dash-circle',       '#94a3b8'],
            ];
            foreach ($cards as [$key, $value, $icon, $colour]): ?>
            <div class="col-6 col-md-4">
                <div class="admin-card h-100">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <i class="bi <?= e($icon) ?>" style="color:<?= e($colour) ?>;font-size:1rem;"></i>
                        <span style="font-size:.68rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--text-muted);line-height:1.3;">
                            <?= e(t($key)) ?>
                        </span>
                    </div>
                    <p style="margin:0;font-size:1.5rem;font-weight:800;color:var(--text-primary);line-height:1.1;">
                        <?= (int) $value ?>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($stats['false_approvals'] > 0): ?>
<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:10px;padding:14px 18px;margin-bottom:1.5rem;">
    <p style="margin:0 0 4px;font-weight:700;color:#991b1b;font-size:.9rem;">
        <?= e(t('superadmin.ai_fa_title', ['n' => $stats['false_approvals']])) ?>
    </p>
    <p style="margin:0;color:#7f1d1d;font-size:.82rem;line-height:1.7;">
        <?= e(t('superadmin.ai_fa_body')) ?>
    </p>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <!-- ── Trend ───────────────────────────────────────────────────────── -->
    <div class="col-lg-7">
        <div class="admin-card h-100">
            <h2 style="font-size:.95rem;font-weight:700;color:var(--text-primary);margin:0 0 12px;">
                <i class="bi bi-graph-up-arrow me-1" style="color:var(--brand-primary);"></i>
                <?= e(t('superadmin.ai_trend')) ?>
            </h2>
            <?php if (count($trend) < 2): ?>
            <p class="text-muted mb-0" style="font-size:.84rem;"><?= e(t('superadmin.ai_trend_thin')) ?></p>
            <?php else: ?>
            <canvas id="accuracyChart" height="150"></canvas>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Accuracy by confidence ──────────────────────────────────────── -->
    <div class="col-lg-5">
        <div class="admin-card h-100">
            <h2 style="font-size:.95rem;font-weight:700;color:var(--text-primary);margin:0 0 4px;">
                <i class="bi bi-speedometer2 me-1" style="color:var(--brand-primary);"></i>
                <?= e(t('superadmin.ai_by_confidence')) ?>
            </h2>
            <p class="text-muted mb-3" style="font-size:.8rem;"><?= e(t('superadmin.ai_by_confidence_help')) ?></p>

            <?php if ($byConfidence === []): ?>
            <p class="text-muted mb-0" style="font-size:.84rem;"><?= e(t('superadmin.ai_no_confidence')) ?></p>
            <?php endif; ?>

            <?php foreach ($byConfidence as $row): ?>
                <?php
                $rowTotal = (int) $row['correct'] + (int) $row['incorrect'];
                $pct      = $rowTotal > 0 ? round((int) $row['correct'] / $rowTotal * 100, 1) : 0.0;
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-baseline mb-1">
                        <span style="font-size:.82rem;font-weight:600;color:var(--text-primary);text-transform:capitalize;">
                            <?= e((string) $row['confidence']) ?>
                        </span>
                        <span style="font-size:.78rem;color:var(--text-muted);">
                            <?= e(number_format($pct, 1)) ?>% &middot; <?= (int) $rowTotal ?>
                        </span>
                    </div>
                    <div class="bar-track">
                        <div class="bar-fill" style="width:<?= (float) $pct ?>%;
                             background:<?= $pct >= 90 ? '#16a34a' : ($pct >= 70 ? '#d97706' : '#dc2626') ?>;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Prediction log ──────────────────────────────────────────────────── -->
<div class="admin-card mb-3">
    <form method="GET" action="<?= e(route('superadmin/ai-accuracy')) ?>" class="d-flex gap-2 align-items-end flex-wrap">
        <div style="flex:0 0 220px;">
            <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('superadmin.ai_filter_outcome')) ?></label>
            <select name="outcome" class="form-select" style="border-radius:8px;">
                <option value=""><?= e(t('superadmin.filter_all')) ?></option>
                <?php foreach ($outcomeMeta as $key => [$cls, $label]): ?>
                <option value="<?= e($key) ?>" <?= $outcome === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-barangay" style="border-radius:8px;font-weight:600;">
            <?= e(t('superadmin.filter_apply')) ?>
        </button>
        <?php if ($outcome !== ''): ?>
        <a href="<?= e(route('superadmin/ai-accuracy')) ?>" class="btn btn-outline-secondary" style="border-radius:8px;">
            <i class="bi bi-x-lg"></i>
        </a>
        <?php endif; ?>
    </form>
</div>

<div class="admin-card p-0">
    <div class="table-responsive">
        <table class="table align-middle mb-0" style="font-size:.85rem;">
            <thead>
                <tr style="border-bottom:2px solid var(--border);">
                    <?php foreach (['ai_col_when', 'ai_col_resident', 'ai_col_predicted', 'ai_col_confidence', 'ai_col_actual', 'ai_col_outcome'] as $col): ?>
                    <th style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);">
                        <?= e(t('superadmin.' . $col)) ?>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php if ($predictions === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= e(t('superadmin.ai_no_rows')) ?></td></tr>
            <?php endif; ?>

            <?php foreach ($predictions as $p): ?>
                <?php [$cls, $label] = $outcomeMeta[$p['outcome']] ?? ['pill-idle', (string) $p['outcome']]; ?>
                <tr>
                    <td style="white-space:nowrap;color:var(--text-muted);font-size:.78rem;">
                        <?= e(date('M j, Y', strtotime((string) $p['created_at']))) ?><br>
                        <span style="font-size:.72rem;"><?= e(date('H:i', strtotime((string) $p['created_at']))) ?></span>
                    </td>
                    <td>
                        <?php if (!empty($p['full_name'])): ?>
                        <span style="font-weight:600;color:var(--text-primary);"><?= e((string) $p['full_name']) ?></span><br>
                        <span style="font-size:.74rem;color:var(--text-muted);"><?= e((string) $p['email']) ?></span>
                        <?php else: ?>
                        <span class="text-muted" style="font-size:.8rem;"><?= e(t('superadmin.ai_deleted_user')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.8rem;color:var(--text-primary);">
                        <?= e($statusLabels[$p['predicted_status']] ?? (string) $p['predicted_status']) ?>
                    </td>
                    <td style="font-size:.8rem;color:var(--text-muted);text-transform:capitalize;">
                        <?= e((string) ($p['confidence'] ?? '—')) ?>
                    </td>
                    <td style="font-size:.8rem;color:var(--text-muted);">
                        <?php if ($p['actual_status'] === null): ?>
                            <span style="opacity:.6;">—</span>
                        <?php else: ?>
                            <?= e($p['actual_status'] === 'verified'
                                ? t('superadmin.ai_actual_verified')
                                : t('superadmin.ai_actual_suspended')) ?>
                            <?php if (!empty($p['resolver_name'])): ?>
                            <br><span style="font-size:.72rem;"><?= e((string) $p['resolver_name']) ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td><span class="pill <?= e($cls) ?>"><?= e($label) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2"
         style="padding:12px 18px;border-top:1px solid var(--border);">
        <p class="mb-0 text-muted" style="font-size:.78rem;">
            <?= e(t('superadmin.ai_showing', ['shown' => count($predictions), 'total' => $total])) ?>
        </p>
        <?php if ($totalPages > 1): ?>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?outcome=<?= e($outcome) ?>&page=<?= (int) ($page - 1) ?>">&laquo;</a>
                </li>
                <li class="page-item disabled"><span class="page-link"><?= (int) $page ?> / <?= (int) $totalPages ?></span></li>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?outcome=<?= e($outcome) ?>&page=<?= (int) ($page + 1) ?>">&raquo;</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<?php if ($hasData && count($trend) >= 2): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    (function () {
        var trend = <?= json_encode(array_map(static function (array $r): array {
            $scored = (int) $r['correct'] + (int) $r['incorrect'];
            return [
                'period'   => $r['period'],
                'accuracy' => $scored > 0 ? round((int) $r['correct'] / $scored * 100, 1) : 0,
                'total'    => $scored,
            ];
        }, $trend), JSON_UNESCAPED_UNICODE) ?>;

        var canvas = document.getElementById('accuracyChart');
        if (!canvas || typeof Chart === 'undefined') { return; }

        // Read theme colours from CSS variables so the chart follows dark mode.
        var css   = getComputedStyle(document.documentElement);
        var text  = css.getPropertyValue('--text-muted').trim()  || '#64748b';
        var grid  = css.getPropertyValue('--border').trim()      || '#e2e8f0';

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: trend.map(function (t) { return t.period; }),
                datasets: [{
                    label: <?= json_encode(t('superadmin.ai_accuracy')) ?>,
                    data: trend.map(function (t) { return t.accuracy; }),
                    borderColor: '#16a34a',
                    backgroundColor: 'rgba(22,163,74,.12)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            afterLabel: function (ctx) {
                                return 'n = ' + trend[ctx.dataIndex].total;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        min: 0, max: 100,
                        ticks: { color: text, callback: function (v) { return v + '%'; } },
                        grid:  { color: grid }
                    },
                    x: { ticks: { color: text }, grid: { display: false } }
                }
            }
        });
    })();
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
