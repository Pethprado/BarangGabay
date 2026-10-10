<?php
/**
 * Super Admin — User Sessions / System Health (module feature 2).
 *
 * Three parts: a health snapshot, the list of currently signed-in sessions
 * (revocable), and the login history including failed attempts and why they
 * failed.
 */
$active       = $active       ?? [];
$sessionStats = $sessionStats ?? ['active' => 0, 'online' => 0, 'today' => 0];
$history      = $history      ?? [];
$historyTotal = $historyTotal ?? 0;
$loginStats   = $loginStats   ?? ['failed_24h' => 0, 'success_24h' => 0, 'distinct_ips_24h' => 0];
$suspicious   = $suspicious   ?? [];
$lockedUsers  = $lockedUsers  ?? [];
$lockedIps    = $lockedIps    ?? [];
$maxAttempts  = $maxAttempts  ?? 5;
$maxAttemptsIp = $maxAttemptsIp ?? 20;
$lockoutMin   = $lockoutMin   ?? 15;
$health       = $health       ?? [];
$page         = $page         ?? 1;
$perPage      = $perPage      ?? 25;
$filters      = $filters      ?? ['email' => '', 'result' => '', 'from' => '', 'to' => ''];
$idleMinutes  = $idleMinutes  ?? 15;
$currentHash  = $currentHash  ?? '';
$ready        = $ready        ?? true;
$totalPages   = $perPage > 0 ? (int) ceil($historyTotal / $perPage) : 1;

/** Short, readable device string from a user agent. */
$device = static function (?string $ua): string {
    if ($ua === null || $ua === '') {
        return '—';
    }
    $os = 'Unknown OS';
    foreach (['Windows NT 10' => 'Windows', 'Windows' => 'Windows', 'Android' => 'Android',
              'iPhone' => 'iOS', 'iPad' => 'iPadOS', 'Mac OS X' => 'macOS', 'Linux' => 'Linux'] as $needle => $label) {
        if (stripos($ua, $needle) !== false) { $os = $label; break; }
    }
    $browser = 'Unknown';
    foreach (['Edg' => 'Edge', 'OPR' => 'Opera', 'Chrome' => 'Chrome',
              'Safari' => 'Safari', 'Firefox' => 'Firefox', 'curl' => 'curl'] as $needle => $label) {
        if (stripos($ua, $needle) !== false) { $browser = $label; break; }
    }
    return $browser . ' · ' . $os;
};

$reasonLabels = [
    'bad_credentials' => t('superadmin.reason_bad_credentials'),
    'unknown_email'   => t('superadmin.reason_unknown_email'),
    'suspended'       => t('superadmin.reason_suspended'),
    'rate_limited'    => t('superadmin.reason_rate_limited'),
    'ip_rate_limited' => t('superadmin.reason_ip_rate_limited'),
    'twofa_failed'    => t('superadmin.reason_twofa_failed'),
];

$queryWith = static function (array $overrides) use ($filters, $page): string {
    $params = array_filter([
        'email'  => $filters['email'],
        'result' => $filters['result'],
        'from'   => $filters['from'],
        'to'     => $filters['to'],
        'page'   => (string) $page,
    ], static fn ($v): bool => $v !== '' && $v !== null);

    return http_build_query(array_merge($params, $overrides));
};

ob_start();
?>

<style>
    .pill { font-size:.68rem; font-weight:700; border-radius:20px; padding:2px 10px; white-space:nowrap; }
    .pill-ok    { background:#dcfce7; color:#166534; }
    .pill-idle  { background:#f1f5f9; color:#475569; }
    .pill-fail  { background:#fee2e2; color:#991b1b; }
    .pill-warn  { background:#fef9c3; color:#92400e; }
    .pill-you   { background:#dbeafe; color:#1d4ed8; }

    :root[data-theme="dark"] .pill-ok   { background:rgba(22,163,74,.22);  color:#86efac; }
    :root[data-theme="dark"] .pill-idle { background:rgba(148,163,184,.20);color:#cbd5e1; }
    :root[data-theme="dark"] .pill-fail { background:rgba(220,38,38,.22);  color:#fca5a5; }
    :root[data-theme="dark"] .pill-warn { background:rgba(217,119,6,.22);  color:#fcd34d; }
    :root[data-theme="dark"] .pill-you  { background:rgba(59,130,246,.22); color:#93c5fd; }

    @media (prefers-color-scheme: dark) {
        :root:not([data-theme="light"]) .pill-ok   { background:rgba(22,163,74,.22);  color:#86efac; }
        :root:not([data-theme="light"]) .pill-idle { background:rgba(148,163,184,.20);color:#cbd5e1; }
        :root:not([data-theme="light"]) .pill-fail { background:rgba(220,38,38,.22);  color:#fca5a5; }
        :root:not([data-theme="light"]) .pill-warn { background:rgba(217,119,6,.22);  color:#fcd34d; }
        :root:not([data-theme="light"]) .pill-you  { background:rgba(59,130,246,.22); color:#93c5fd; }
    }
    .health-item { display:flex; justify-content:space-between; gap:12px; padding:7px 0;
                   border-bottom:1px solid var(--border); font-size:.82rem; }
    .health-item:last-child { border-bottom:none; }
</style>

<!-- ── Page header ─────────────────────────────────────────────────────── -->
<div class="mb-4">
    <p class="mb-0" style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);">
        <?= e(t('admin_nav.system')) ?>
    </p>
    <h1 class="mb-0 mt-1" style="font-size:1.55rem;font-weight:800;color:var(--text-primary);line-height:1.1;">
        <?= e(t('superadmin.sessions_title')) ?>
    </h1>
    <p class="text-muted mt-1 mb-0" style="font-size:.82rem;">
        <?= e(t('superadmin.sessions_subtitle')) ?>
    </p>
</div>

<?php if (!$ready): ?>
<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:10px;padding:16px 20px;margin-bottom:1.5rem;">
    <p style="margin:0 0 6px;font-weight:700;color:#991b1b;font-size:.92rem;">
        <?= e(t('superadmin.sess_table_missing_title')) ?>
    </p>
    <p style="margin:0;color:#7f1d1d;font-size:.83rem;">
        <code style="background:rgba(0,0,0,.06);padding:2px 6px;border-radius:4px;">
            mysql -u root baranggabay &lt; database/migrations/008_add_user_sessions.sql
        </code>
    </p>
</div>
<?php endif; ?>

<!-- ── Stat cards ──────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['superadmin.stat_online',      $sessionStats['online'],           'bi-broadcast',          '#16a34a'],
        ['superadmin.stat_sessions',    $sessionStats['active'],           'bi-people',             'var(--brand-primary)'],
        ['superadmin.stat_logins_today',$sessionStats['today'],            'bi-box-arrow-in-right', '#0ea5e9'],
        ['superadmin.stat_failed_24h',  $loginStats['failed_24h'],         'bi-shield-x',           '#dc2626'],
        ['superadmin.stat_ips_24h',     $loginStats['distinct_ips_24h'],   'bi-globe',              '#8b5cf6'],
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

<div class="row g-3 mb-4">
    <!-- ── System health ───────────────────────────────────────────────── -->
    <div class="col-lg-4">
        <div class="admin-card h-100">
            <h2 style="font-size:.95rem;font-weight:700;color:var(--text-primary);margin:0 0 12px;">
                <i class="bi bi-activity me-1" style="color:var(--brand-primary);"></i>
                <?= e(t('superadmin.health_title')) ?>
            </h2>
            <?php
            $rows = [
                ['health_php',      $health['php_version']  ?? null, ''],
                ['health_db',       $health['db_version']   ?? null, ''],
                ['health_db_size',  $health['db_size_mb']   ?? null, ' MB'],
                ['health_disk',     $health['disk_free_gb'] ?? null, ' GB'],
                ['health_log_size', $health['log_size_mb']  ?? null, ' MB'],
                ['health_errors',   $health['errors_24h']   ?? null, ''],
                ['health_timezone', $health['timezone']     ?? null, ''],
                ['health_time',     $health['server_time']  ?? null, ''],
            ];
            foreach ($rows as [$key, $value, $suffix]): ?>
            <div class="health-item">
                <span style="color:var(--text-muted);"><?= e(t('superadmin.' . $key)) ?></span>
                <span style="font-weight:600;color:var(--text-primary);">
                    <?= $value === null ? '—' : e((string) $value . $suffix) ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Locked out right now ────────────────────────────────────────── -->
    <div class="col-lg-8">
        <div class="admin-card h-100">
            <h2 style="font-size:.95rem;font-weight:700;color:var(--text-primary);margin:0 0 4px;">
                <i class="bi bi-lock-fill me-1" style="color:#dc2626;"></i>
                <?= e(t('superadmin.lock_title')) ?>
            </h2>
            <p class="text-muted mb-3" style="font-size:.8rem;">
                <?= e(t('superadmin.lock_subtitle', [
                    'attempts' => $maxAttempts,
                    'minutes'  => $lockoutMin,
                    'ip'       => $maxAttemptsIp,
                ])) ?>
            </p>

            <?php if ($lockedUsers === [] && $lockedIps === []): ?>
            <p class="text-muted mb-0" style="font-size:.85rem;">
                <i class="bi bi-check-circle me-1" style="color:#16a34a;"></i>
                <?= e(t('superadmin.lock_none')) ?>
            </p>
            <?php endif; ?>

            <?php if ($lockedUsers !== []): ?>
            <p style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;
                      color:var(--text-muted);margin:0 0 6px;">
                <?= e(t('superadmin.lock_accounts')) ?>
            </p>
            <div class="table-responsive mb-3">
                <table class="table table-sm align-middle mb-0" style="font-size:.83rem;">
                    <thead>
                        <tr style="border-bottom:2px solid var(--border);">
                            <?php foreach (['col_email', 'col_failures', 'col_ips', 'lock_until'] as $c): ?>
                            <th style="font-size:.7rem;text-transform:uppercase;color:var(--text-muted);"><?= e(t('superadmin.' . $c)) ?></th>
                            <?php endforeach; ?>
                            <th class="text-end" style="font-size:.7rem;text-transform:uppercase;color:var(--text-muted);"><?= e(t('superadmin.col_actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lockedUsers as $l): ?>
                        <?php $minsLeft = max(0, (int) ceil((strtotime((string) $l['unlocks_at']) - time()) / 60)); ?>
                        <tr>
                            <td style="word-break:break-all;">
                                <?= e((string) $l['email']) ?>
                                <?php if (!empty($l['full_name'])): ?>
                                <br><span style="font-size:.72rem;color:var(--text-muted);">
                                    <?= e((string) $l['full_name']) ?> · <?= e((string) $l['role']) ?>
                                </span>
                                <?php else: ?>
                                <br><span style="font-size:.72rem;color:var(--text-muted);"><?= e(t('superadmin.lock_no_account')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="pill pill-fail"><?= (int) $l['failures'] ?></span></td>
                            <td style="color:var(--text-muted);"><?= (int) $l['ip_count'] ?></td>
                            <td style="color:var(--text-muted);font-size:.78rem;white-space:nowrap;">
                                <?= $minsLeft > 0
                                    ? e(t('superadmin.lock_mins_left', ['n' => $minsLeft]))
                                    : e(t('superadmin.lock_expired')) ?>
                            </td>
                            <td class="text-end">
                                <form method="POST" action="<?= e(route('superadmin/sessions/unlock')) ?>" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="email" value="<?= e((string) $l['email']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" style="border-radius:6px;font-size:.72rem;">
                                        <i class="bi bi-unlock me-1"></i><?= e(t('superadmin.unlock')) ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if ($lockedIps !== []): ?>
            <p style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;
                      color:var(--text-muted);margin:0 0 6px;">
                <?= e(t('superadmin.lock_ips')) ?>
            </p>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" style="font-size:.83rem;">
                    <thead>
                        <tr style="border-bottom:2px solid var(--border);">
                            <?php foreach (['col_ip', 'col_failures', 'lock_accounts_hit', 'lock_until'] as $c): ?>
                            <th style="font-size:.7rem;text-transform:uppercase;color:var(--text-muted);"><?= e(t('superadmin.' . $c)) ?></th>
                            <?php endforeach; ?>
                            <th class="text-end" style="font-size:.7rem;text-transform:uppercase;color:var(--text-muted);"><?= e(t('superadmin.col_actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lockedIps as $l): ?>
                        <?php $minsLeft = max(0, (int) ceil((strtotime((string) $l['unlocks_at']) - time()) / 60)); ?>
                        <tr>
                            <td style="font-family:ui-monospace,Consolas,monospace;font-size:.8rem;">
                                <?= e((string) $l['ip_address']) ?>
                            </td>
                            <td><span class="pill pill-fail"><?= (int) $l['failures'] ?></span></td>
                            <td style="color:var(--text-muted);"><?= (int) $l['email_count'] ?></td>
                            <td style="color:var(--text-muted);font-size:.78rem;white-space:nowrap;">
                                <?= $minsLeft > 0
                                    ? e(t('superadmin.lock_mins_left', ['n' => $minsLeft]))
                                    : e(t('superadmin.lock_expired')) ?>
                            </td>
                            <td class="text-end">
                                <form method="POST" action="<?= e(route('superadmin/sessions/unlock-ip')) ?>" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="ip" value="<?= e((string) $l['ip_address']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" style="border-radius:6px;font-size:.72rem;">
                                        <i class="bi bi-unlock me-1"></i><?= e(t('superadmin.unlock')) ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Active sessions ─────────────────────────────────────────────────── -->
<div class="admin-card p-0 mb-4">
    <div style="padding:16px 18px 12px;">
        <h2 style="font-size:.95rem;font-weight:700;color:var(--text-primary);margin:0 0 2px;">
            <i class="bi bi-person-check me-1" style="color:var(--brand-primary);"></i>
            <?= e(t('superadmin.active_title')) ?>
        </h2>
        <p class="text-muted mb-0" style="font-size:.8rem;">
            <?= e(t('superadmin.active_subtitle', ['minutes' => $idleMinutes])) ?>
        </p>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0" style="font-size:.85rem;">
            <thead>
                <tr style="border-bottom:2px solid var(--border);">
                    <?php foreach (['col_user', 'col_role', 'col_state', 'col_login_time', 'col_last_seen', 'col_ip', 'col_device'] as $col): ?>
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
            <?php if ($active === []): ?>
                <tr><td colspan="8" class="text-center text-muted py-4"><?= e(t('superadmin.no_sessions')) ?></td></tr>
            <?php endif; ?>

            <?php foreach ($active as $s): ?>
                <?php
                $idle   = (int) $s['idle_minutes'];
                $isSelf = (int) $s['user_id'] === (int) ($_SESSION['user_id'] ?? 0);
                ?>
                <tr>
                    <td>
                        <span style="font-weight:600;color:var(--text-primary);"><?= e((string) $s['full_name']) ?></span>
                        <?php if ($isSelf): ?>
                        <span class="pill pill-you ms-1"><?= e(t('superadmin.you')) ?></span>
                        <?php endif; ?>
                        <br><span style="font-size:.74rem;color:var(--text-muted);"><?= e((string) $s['email']) ?></span>
                    </td>
                    <td><span class="pill pill-idle"><?= e(strtoupper((string) $s['role'])) ?></span></td>
                    <td>
                        <?php if ($idle <= $idleMinutes): ?>
                        <span class="pill pill-ok"><?= e(t('superadmin.state_online')) ?></span>
                        <?php else: ?>
                        <span class="pill pill-idle"><?= e(t('superadmin.state_idle')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--text-muted);font-size:.78rem;white-space:nowrap;">
                        <?= e(date('M j, H:i', strtotime((string) $s['login_at']))) ?>
                    </td>
                    <td style="color:var(--text-muted);font-size:.78rem;white-space:nowrap;">
                        <?= e(t('superadmin.minutes_ago', ['n' => $idle])) ?>
                    </td>
                    <td style="color:var(--text-muted);font-size:.78rem;"><?= e((string) ($s['ip_address'] ?? '—')) ?></td>
                    <td style="color:var(--text-muted);font-size:.78rem;"><?= e($device($s['user_agent'])) ?></td>
                    <td class="text-end">
                        <?php if (!$isSelf): ?>
                        <form method="POST" action="<?= e(route('superadmin/sessions/revoke')) ?>" class="d-inline"
                              onsubmit="return confirm(<?= e(json_encode(t('superadmin.revoke_confirm'))) ?>);">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:.72rem;">
                                <i class="bi bi-box-arrow-right me-1"></i><?= e(t('superadmin.revoke')) ?>
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="text-muted" style="font-size:.75rem;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Login history ───────────────────────────────────────────────────── -->
<div class="admin-card mb-3">
    <h2 style="font-size:.95rem;font-weight:700;color:var(--text-primary);margin:0 0 12px;">
        <i class="bi bi-clock-history me-1" style="color:var(--brand-primary);"></i>
        <?= e(t('superadmin.history_title')) ?>
    </h2>
    <form method="GET" action="<?= e(route('superadmin/sessions')) ?>" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('superadmin.filter_email')) ?></label>
            <input type="text" name="email" value="<?= e($filters['email']) ?>" class="form-control"
                   placeholder="<?= e(t('superadmin.filter_email_ph')) ?>" style="border-radius:8px;">
        </div>
        <div class="col-md-2">
            <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('superadmin.filter_result')) ?></label>
            <select name="result" class="form-select" style="border-radius:8px;">
                <option value=""><?= e(t('superadmin.filter_all')) ?></option>
                <option value="success" <?= $filters['result'] === 'success' ? 'selected' : '' ?>><?= e(t('superadmin.result_success')) ?></option>
                <option value="failed"  <?= $filters['result'] === 'failed'  ? 'selected' : '' ?>><?= e(t('superadmin.result_failed')) ?></option>
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
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-barangay flex-grow-1" style="border-radius:8px;font-weight:600;">
                <?= e(t('superadmin.filter_apply')) ?>
            </button>
            <?php if (array_filter([$filters['email'], $filters['result'], $filters['from'], $filters['to']])): ?>
            <a href="<?= e(route('superadmin/sessions')) ?>" class="btn btn-outline-secondary" style="border-radius:8px;">
                <i class="bi bi-x-lg"></i>
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="admin-card p-0">
    <div class="table-responsive">
        <table class="table align-middle mb-0" style="font-size:.85rem;">
            <thead>
                <tr style="border-bottom:2px solid var(--border);">
                    <?php foreach (['col_when', 'col_email', 'col_result', 'col_reason', 'col_ip', 'col_device'] as $col): ?>
                    <th style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);">
                        <?= e(t('superadmin.' . $col)) ?>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php if ($history === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= e(t('superadmin.no_history')) ?></td></tr>
            <?php endif; ?>

            <?php foreach ($history as $h): ?>
                <tr>
                    <td style="white-space:nowrap;color:var(--text-muted);font-size:.78rem;">
                        <?= e(date('M j, Y', strtotime((string) $h['attempted_at']))) ?><br>
                        <span style="font-size:.72rem;"><?= e(date('H:i:s', strtotime((string) $h['attempted_at']))) ?></span>
                    </td>
                    <td style="word-break:break-all;">
                        <?= e((string) $h['email']) ?>
                        <?php if (!empty($h['full_name'])): ?>
                        <br><span style="font-size:.74rem;color:var(--text-muted);"><?= e((string) $h['full_name']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int) $h['successful'] === 1): ?>
                        <span class="pill pill-ok"><?= e(t('superadmin.result_success')) ?></span>
                        <?php else: ?>
                        <span class="pill pill-fail"><?= e(t('superadmin.result_failed')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.78rem;color:var(--text-muted);">
                        <?php if ((int) $h['successful'] === 1): ?>
                            —
                        <?php else: ?>
                            <?= e($reasonLabels[$h['reason']] ?? (string) ($h['reason'] ?? '—')) ?>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.78rem;color:var(--text-muted);"><?= e((string) ($h['ip_address'] ?? '—')) ?></td>
                    <td style="font-size:.78rem;color:var(--text-muted);"><?= e($device($h['user_agent'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2"
         style="padding:12px 18px;border-top:1px solid var(--border);">
        <p class="mb-0 text-muted" style="font-size:.78rem;">
            <?= e(t('superadmin.showing_logins', ['shown' => count($history), 'total' => $historyTotal])) ?>
        </p>
        <?php if ($totalPages > 1): ?>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= e($queryWith(['page' => (string) ($page - 1)])) ?>">&laquo;</a>
                </li>
                <li class="page-item disabled"><span class="page-link"><?= (int) $page ?> / <?= (int) $totalPages ?></span></li>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= e($queryWith(['page' => (string) ($page + 1)])) ?>">&raquo;</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
