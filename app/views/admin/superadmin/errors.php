<?php
/**
 * Super Admin — System Error Logs (module feature 1).
 *
 * Read-only viewer over the error_logs table with severity / date / keyword
 * filters, pagination, an expandable stack trace and a purge control.
 */
$logs       = $logs       ?? [];
$total      = $total      ?? 0;
$page       = $page       ?? 1;
$perPage    = $perPage    ?? 25;
$stats      = $stats      ?? ['total' => 0, 'today' => 0, 'week' => 0, 'unresolved' => 0, 'critical' => 0];
$filters    = $filters    ?? ['severity' => '', 'from' => '', 'to' => '', 'q' => '', 'unresolved' => false];
$severities = $severities ?? ['notice', 'warning', 'error', 'critical'];
$detail     = $detail     ?? null;
$ready      = $ready      ?? true;
$totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

/** Badge colours per severity — light values, dark overrides live in the <style> below. */
$severityMeta = [
    'notice'   => ['sev-notice',   t('superadmin.sev_notice')],
    'warning'  => ['sev-warning',  t('superadmin.sev_warning')],
    'error'    => ['sev-error',    t('superadmin.sev_error')],
    'critical' => ['sev-critical', t('superadmin.sev_critical')],
];

/** Rebuild the current query string with one key changed — used by pagination. */
$queryWith = static function (array $overrides) use ($filters, $page): string {
    $params = array_filter([
        'severity'   => $filters['severity'],
        'from'       => $filters['from'],
        'to'         => $filters['to'],
        'q'          => $filters['q'],
        'unresolved' => $filters['unresolved'] ? '1' : '',
        'page'       => (string) $page,
    ], static fn ($v): bool => $v !== '' && $v !== null);

    return http_build_query(array_merge($params, $overrides));
};

ob_start();
?>

<style>
    .sev-notice   { background:#f1f5f9; color:#475569; }
    .sev-warning  { background:#fef9c3; color:#92400e; }
    .sev-error    { background:#fee2e2; color:#991b1b; }
    .sev-critical { background:#7f1d1d; color:#fff;    }

    :root[data-theme="dark"] .sev-notice   { background:rgba(148,163,184,.20); color:#cbd5e1; }
    :root[data-theme="dark"] .sev-warning  { background:rgba(217,119,6,.22);   color:#fcd34d; }
    :root[data-theme="dark"] .sev-error    { background:rgba(220,38,38,.22);   color:#fca5a5; }
    :root[data-theme="dark"] .sev-critical { background:#991b1b;               color:#fff;    }

    @media (prefers-color-scheme: dark) {
        :root:not([data-theme="light"]) .sev-notice   { background:rgba(148,163,184,.20); color:#cbd5e1; }
        :root:not([data-theme="light"]) .sev-warning  { background:rgba(217,119,6,.22);   color:#fcd34d; }
        :root:not([data-theme="light"]) .sev-error    { background:rgba(220,38,38,.22);   color:#fca5a5; }
        :root:not([data-theme="light"]) .sev-critical { background:#991b1b;               color:#fff;    }
    }

    .sev-badge {
        font-size:.68rem; font-weight:700; border-radius:20px; padding:2px 10px;
        text-transform:uppercase; letter-spacing:.04em; white-space:nowrap;
    }
    .err-trace {
        background:var(--surface-muted,#0f172a); color:var(--text-primary,#e2e8f0);
        border-radius:8px; padding:14px 16px; font-family:ui-monospace,Consolas,monospace;
        font-size:.74rem; line-height:1.7; white-space:pre-wrap; word-break:break-word;
        max-height:420px; overflow:auto; margin:0;
    }
    .err-msg { font-family:ui-monospace,Consolas,monospace; font-size:.78rem; word-break:break-word; }

    /* ── Diagnosis panel ────────────────────────────────────────────────
       Prose, deliberately: the trace above it already covers "where", and
       this is the part that says what it means and what to do next. */
    .err-diagnosis {
        border:1px solid var(--tb-border,#e2e8f0);
        border-left:3px solid var(--brand-primary,#1652f0);
        border-radius:8px; padding:12px 14px;
        background:var(--surface-muted,#f8fafc);
    }
    .err-diagnosis-title {
        display:flex; align-items:center; gap:.4rem; flex-wrap:wrap;
        margin:0 0 .35rem; font-size:.85rem; font-weight:700;
        color:var(--text-primary);
    }
    .err-diagnosis-title .bi { color:var(--brand-primary,#1652f0); }
    /* Says up front whether a button can do anything about this one, so a
       reader is not left hunting for a fix that was never offered. */
    .err-diagnosis-tag {
        font-size:.62rem; font-weight:800; letter-spacing:.04em;
        text-transform:uppercase; padding:.1rem .45rem; border-radius:999px;
        background:var(--surface-card,#fff); color:var(--text-muted,#64748b);
        border:1px solid var(--tb-border,#e2e8f0);
    }
    .err-diagnosis-tag.is-fixable { background:#dcfce7; color:#15803d; border-color:#86efac; }
    :root[data-theme="dark"] .err-diagnosis-tag.is-fixable {
        background:rgba(22,163,74,.22); color:#86efac; border-color:rgba(22,163,74,.4);
    }
    .err-diagnosis-text {
        margin:0; font-size:.8rem; line-height:1.65; color:var(--text-secondary);
    }
    .err-diagnosis-steps {
        margin:.5rem 0 0; padding-left:1.15rem;
        font-size:.78rem; line-height:1.7; color:var(--text-secondary);
    }
</style>

<!-- ── Page header ─────────────────────────────────────────────────────── -->
<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <p class="mb-0" style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);">
            <?= e(t('admin_nav.system')) ?>
        </p>
        <h1 class="mb-0 mt-1" style="font-size:1.55rem;font-weight:800;color:var(--text-primary);line-height:1.1;">
            <?= e(t('superadmin.errors_title')) ?>
        </h1>
        <p class="text-muted mt-1 mb-0" style="font-size:.82rem;">
            <?= e(t('superadmin.errors_subtitle')) ?>
        </p>
    </div>
</div>

<?php if (!$ready): ?>
<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:10px;padding:16px 20px;margin-bottom:1.5rem;">
    <p style="margin:0 0 6px;font-weight:700;color:#991b1b;font-size:.92rem;">
        <?= e(t('superadmin.err_table_missing_title')) ?>
    </p>
    <p style="margin:0;color:#7f1d1d;font-size:.83rem;line-height:1.7;">
        <?= e(t('superadmin.err_table_missing_body')) ?>
        <code style="background:rgba(0,0,0,.06);padding:2px 6px;border-radius:4px;">
            mysql -u root baranggabay &lt; database/migrations/007_add_error_logs.sql
        </code>
    </p>
</div>
<?php endif; ?>

<!-- ── Stat cards ──────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['superadmin.stat_total',      $stats['total'],      'bi-collection',           'var(--brand-primary)'],
        ['superadmin.stat_today',      $stats['today'],      'bi-calendar-day',         '#0ea5e9'],
        ['superadmin.stat_week',       $stats['week'],       'bi-calendar-week',        '#8b5cf6'],
        ['superadmin.stat_unresolved', $stats['unresolved'], 'bi-exclamation-circle',   '#d97706'],
        ['superadmin.stat_critical',   $stats['critical'],   'bi-shield-exclamation',   '#dc2626'],
    ];
    foreach ($cards as [$key, $value, $icon, $colour]): ?>
    <div class="col-6 col-lg">
        <div class="admin-card h-100">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi <?= e($icon) ?>" style="color:<?= e($colour) ?>;font-size:1.05rem;"></i>
                <span style="font-size:.7rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-muted);">
                    <?= e(t($key)) ?>
                </span>
            </div>
            <p style="margin:0;font-size:1.6rem;font-weight:800;color:var(--text-primary);line-height:1.1;">
                <?= (int) $value ?>
            </p>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Filters ─────────────────────────────────────────────────────────── -->
<div class="admin-card mb-3">
    <form method="GET" action="<?= e(route('superadmin/errors')) ?>" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('superadmin.filter_keyword')) ?></label>
            <input type="text" name="q" value="<?= e($filters['q']) ?>" class="form-control"
                   placeholder="<?= e(t('superadmin.filter_keyword_ph')) ?>" style="border-radius:8px;">
        </div>
        <div class="col-md-2">
            <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('superadmin.filter_severity')) ?></label>
            <select name="severity" class="form-select" style="border-radius:8px;">
                <option value=""><?= e(t('superadmin.filter_all')) ?></option>
                <?php foreach ($severities as $sev): ?>
                <option value="<?= e($sev) ?>" <?= $filters['severity'] === $sev ? 'selected' : '' ?>>
                    <?= e($severityMeta[$sev][1] ?? $sev) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('superadmin.filter_from')) ?></label>
            <input type="date" name="from" value="<?= e($filters['from']) ?>" class="form-control" style="border-radius:8px;">
        </div>
        <div class="col-md-2">
            <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('superadmin.filter_to')) ?></label>
            <input type="date" name="to" value="<?= e($filters['to']) ?>" class="form-control" style="border-radius:8px;">
        </div>
        <div class="col-md-3 d-flex gap-2">
            <div class="form-check me-2" style="white-space:nowrap;align-self:center;">
                <input class="form-check-input" type="checkbox" name="unresolved" value="1"
                       id="f-unresolved" <?= $filters['unresolved'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="f-unresolved" style="font-size:.78rem;">
                    <?= e(t('superadmin.filter_unresolved')) ?>
                </label>
            </div>
            <button type="submit" class="btn btn-barangay flex-grow-1" style="border-radius:8px;font-weight:600;">
                <?= e(t('superadmin.filter_apply')) ?>
            </button>
            <?php if ($filters['q'] !== '' || $filters['severity'] !== '' || $filters['from'] !== '' || $filters['to'] !== '' || $filters['unresolved']): ?>
            <a href="<?= e(route('superadmin/errors')) ?>" class="btn btn-outline-secondary" style="border-radius:8px;">
                <i class="bi bi-x-lg"></i>
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ── Log table ───────────────────────────────────────────────────────── -->
<div class="admin-card p-0">
    <div class="table-responsive">
        <table class="table align-middle mb-0" style="font-size:.85rem;">
            <thead>
                <tr style="border-bottom:2px solid var(--border);">
                    <?php foreach (['col_when', 'col_severity', 'col_type', 'col_message', 'col_route', 'col_user'] as $col): ?>
                    <th style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);">
                        <?= e(t('superadmin.' . $col)) ?>
                    </th>
                    <?php endforeach; ?>
                    <th class="text-end" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);">
                        <?= e(t('superadmin.col_actions')) ?>
                    </th>
                </tr>
            </thead>
            <tbody>
            <?php if ($logs === []): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        <i class="bi bi-check-circle me-1" style="color:#16a34a;"></i>
                        <?= e(t('superadmin.no_errors')) ?>
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($logs as $log): ?>
                <?php [$sevClass, $sevLabel] = $severityMeta[$log['severity']] ?? ['sev-notice', $log['severity']]; ?>
                <tr <?= $log['resolved_at'] !== null ? 'style="opacity:.55;"' : '' ?>>
                    <td style="white-space:nowrap;color:var(--text-muted);font-size:.78rem;">
                        <?= e(date('M j, Y', strtotime($log['created_at']))) ?><br>
                        <span style="font-size:.72rem;"><?= e(date('H:i:s', strtotime($log['created_at']))) ?></span>
                    </td>
                    <td>
                        <span class="sev-badge <?= e($sevClass) ?>"><?= e($sevLabel) ?></span>
                        <?php if ($log['resolved_at'] !== null): ?>
                        <br><span style="font-size:.66rem;color:#16a34a;font-weight:700;">
                            <i class="bi bi-check-lg"></i> <?= e(t('superadmin.resolved')) ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.78rem;font-weight:600;color:var(--text-primary);word-break:break-word;max-width:180px;">
                        <?= e($log['type']) ?>
                        <br><span style="font-size:.68rem;color:var(--text-muted);font-weight:400;"><?= e($log['error_id']) ?></span>
                    </td>
                    <td class="err-msg" style="max-width:340px;color:var(--text-primary);">
                        <?= e(mb_strimwidth($log['message'], 0, 140, '…')) ?>
                        <?php if (!empty($log['file'])): ?>
                        <br><span style="font-size:.7rem;color:var(--text-muted);">
                            <?= e(basename((string) $log['file'])) ?><?= $log['line'] ? ':' . (int) $log['line'] : '' ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.75rem;color:var(--text-muted);max-width:200px;word-break:break-all;">
                        <?php if (!empty($log['route'])): ?>
                        <strong><?= e((string) $log['method']) ?></strong> <?= e(mb_strimwidth((string) $log['route'], 0, 60, '…')) ?>
                        <?php else: ?>
                        <span style="opacity:.6;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.78rem;color:var(--text-muted);white-space:nowrap;">
                        <?= $log['user_name'] !== null ? e((string) $log['user_name']) : '<span style="opacity:.6;">' . e(t('superadmin.guest')) . '</span>' ?>
                        <?php if (!empty($log['ip_address'])): ?>
                        <br><span style="font-size:.68rem;"><?= e((string) $log['ip_address']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end" style="white-space:nowrap;">
                        <a href="<?= e(route('superadmin/errors') . '?' . $queryWith(['view' => (string) $log['id']])) ?>#detail"
                           class="btn btn-sm btn-outline-secondary" style="border-radius:6px;"
                           title="<?= e(t('superadmin.view_trace')) ?>">
                            <i class="bi bi-eye"></i>
                        </a>
                        <?php
                        /* The repair button only appears where a repair exists.
                           A wrench on every row would promise the same thing
                           for an out-of-credit API key as for a missing folder,
                           and only one of those is ours to fix. */
                        $rowFix = \App\Services\ErrorRemedy::diagnose($log);
                        ?>
                        <?php if ($rowFix['fixable'] && $log['resolved_at'] === null): ?>
                        <form method="POST" action="<?= e(route('superadmin/errors/fix')) ?>" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int) $log['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-primary" style="border-radius:6px;"
                                    title="<?= e(t('superadmin.try_fix_title')) ?>">
                                <i class="bi bi-wrench-adjustable"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" action="<?= e(route('superadmin/errors/resolve')) ?>" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int) $log['id'] ?>">
                            <?php if ($log['resolved_at'] !== null): ?>
                            <input type="hidden" name="reopen" value="1">
                            <button type="submit" class="btn btn-sm btn-outline-secondary" style="border-radius:6px;"
                                    title="<?= e(t('superadmin.reopen')) ?>"><i class="bi bi-arrow-counterclockwise"></i></button>
                            <?php else: ?>
                            <button type="submit" class="btn btn-sm btn-outline-success" style="border-radius:6px;"
                                    title="<?= e(t('superadmin.mark_resolved')) ?>"><i class="bi bi-check-lg"></i></button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination + purge -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2"
         style="padding:12px 18px;border-top:1px solid var(--border);">
        <p class="mb-0 text-muted" style="font-size:.78rem;">
            <?= e(t('superadmin.showing', ['shown' => count($logs), 'total' => $total])) ?>
        </p>

        <?php if ($totalPages > 1): ?>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= e($queryWith(['page' => (string) ($page - 1)])) ?>">&laquo;</a>
                </li>
                <li class="page-item disabled">
                    <span class="page-link"><?= (int) $page ?> / <?= (int) $totalPages ?></span>
                </li>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= e($queryWith(['page' => (string) ($page + 1)])) ?>">&raquo;</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>

        <form method="POST" action="<?= e(route('superadmin/errors/purge')) ?>" class="d-flex gap-2 align-items-center"
              onsubmit="return confirm(<?= e(json_encode(t('superadmin.purge_confirm'))) ?>);">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <select name="days" class="form-select form-select-sm" style="border-radius:6px;width:auto;">
                <option value="30"><?= e(t('superadmin.purge_30')) ?></option>
                <option value="7"><?= e(t('superadmin.purge_7')) ?></option>
                <option value="0"><?= e(t('superadmin.purge_all')) ?></option>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius:6px;">
                <i class="bi bi-trash me-1"></i><?= e(t('superadmin.purge')) ?>
            </button>
        </form>
    </div>
</div>

<!-- ── Expanded detail ─────────────────────────────────────────────────── -->
<?php if ($detail !== null): ?>
<div class="admin-card mt-3" id="detail">
    <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h2 style="font-size:1rem;font-weight:700;color:var(--text-primary);margin:0 0 4px;">
                <?= e($detail['type']) ?>
            </h2>
            <p class="text-muted mb-0" style="font-size:.78rem;">
                <?= e(t('superadmin.error_id')) ?>: <code><?= e($detail['error_id']) ?></code>
                &middot; <?= e($detail['created_at']) ?>
                <?php if (!empty($detail['file'])): ?>
                &middot; <?= e((string) $detail['file']) ?><?= $detail['line'] ? ':' . (int) $detail['line'] : '' ?>
                <?php endif; ?>
            </p>
        </div>
        <a href="<?= e(route('superadmin/errors') . '?' . $queryWith(['view' => ''])) ?>"
           class="btn btn-sm btn-outline-secondary" style="border-radius:6px;">
            <i class="bi bi-x-lg"></i>
        </a>
    </div>

    <p class="err-msg mb-3" style="color:var(--text-primary);"><?= e($detail['message']) ?></p>

    <?php
    /*
     * What this error means, in words, and what to do about it.
     *
     * Shown for every error including the ones nothing here can repair — a
     * stack trace tells you where it happened, and this is meant to tell you
     * why and what comes next. For the unrepairable ones that sentence IS the
     * feature: "the Anthropic account is out of credit" is an answer, and a
     * wrench that pretended otherwise would not be.
     */
    $dx = \App\Services\ErrorRemedy::diagnose($detail);
    ?>
    <div class="err-diagnosis mb-3">
        <p class="err-diagnosis-title">
            <i class="bi <?= $dx['fixable'] ? 'bi-wrench-adjustable' : 'bi-info-circle' ?>" aria-hidden="true"></i>
            <?= e($dx['title']) ?>
            <span class="err-diagnosis-tag<?= $dx['fixable'] ? ' is-fixable' : '' ?>">
                <?= e($dx['fixable'] ? t('remedy.tag_fixable') : t('remedy.tag_manual')) ?>
            </span>
        </p>
        <p class="err-diagnosis-text"><?= e($dx['explain']) ?></p>

        <?php if ($dx['steps'] !== []): ?>
        <ol class="err-diagnosis-steps">
            <?php foreach ($dx['steps'] as $__step): ?>
            <li><?= e($__step) ?></li>
            <?php endforeach; ?>
        </ol>
        <?php endif; ?>

        <?php if ($dx['fixable'] && $detail['resolved_at'] === null): ?>
        <form method="POST" action="<?= e(route('superadmin/errors/fix')) ?>" class="mt-2">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) $detail['id'] ?>">
            <button type="submit" class="btn btn-sm btn-barangay">
                <i class="bi bi-wrench-adjustable me-1"></i><?= e(t('superadmin.try_fix')) ?>
            </button>
            <span class="text-muted ms-2" style="font-size:.74rem;">
                <?= e(t('remedy.only_closes_if_verified')) ?>
            </span>
        </form>
        <?php endif; ?>
    </div>

    <?php if (!empty($detail['user_agent'])): ?>
    <p class="text-muted mb-3" style="font-size:.74rem;word-break:break-all;">
        <?= e(t('superadmin.user_agent')) ?>: <?= e((string) $detail['user_agent']) ?>
    </p>
    <?php endif; ?>

    <?php if (!empty($detail['stack_trace'])): ?>
    <p style="font-size:.78rem;font-weight:700;color:var(--text-primary);margin:0 0 6px;">
        <?= e(t('superadmin.stack_trace')) ?>
    </p>
    <pre class="err-trace"><?= e((string) $detail['stack_trace']) ?></pre>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
