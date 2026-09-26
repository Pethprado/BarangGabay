<?php
/**
 * Super Admin — Database Backups (module feature 3).
 *
 * On-demand backups, history with download/delete, and the status of the
 * scheduled task. Restore is intentionally not here — see
 * storage/backups/README.md for why and for the CLI procedure.
 */
$backups    = $backups    ?? [];
$lastBackup = $lastBackup ?? null;
$overdue    = $overdue    ?? true;
$totalSize  = $totalSize  ?? 0;
$available  = $available  ?? false;
$directory  = $directory  ?? '';
$keepLatest = $keepLatest ?? 10;
$staleHours = $staleHours ?? 24;
$scheduled  = $scheduled  ?? null;

/** Bytes to a short human string. */
$bytes = static function (int $n): string {
    if ($n >= 1048576) { return number_format($n / 1048576, 1) . ' MB'; }
    if ($n >= 1024)    { return number_format($n / 1024, 1) . ' KB'; }
    return $n . ' B';
};

/** "3 hours ago" style relative time. */
$ago = static function (int $ts): string {
    $diff = max(0, time() - $ts);
    if ($diff < 60)    { return t('superadmin.bk_just_now'); }
    if ($diff < 3600)  { return t('superadmin.bk_mins_ago',  ['n' => (int) floor($diff / 60)]); }
    if ($diff < 86400) { return t('superadmin.bk_hours_ago', ['n' => (int) floor($diff / 3600)]); }
    return t('superadmin.bk_days_ago', ['n' => (int) floor($diff / 86400)]);
};

ob_start();
?>

<style>
    .bk-note { border-radius:10px; padding:14px 18px; margin-bottom:1.25rem;
               display:flex; gap:12px; align-items:flex-start; }
    .bk-warn { background:#fefce8; border:1px solid #fde047; }
    .bk-warn .bk-title { color:#92400e; } .bk-warn .bk-body { color:#78350f; }
    .bk-bad  { background:#fef2f2; border:1px solid #fca5a5; }
    .bk-bad  .bk-title { color:#991b1b; } .bk-bad .bk-body { color:#7f1d1d; }
    .bk-ok   { background:#f0fdf4; border:1px solid #bbf7d0; }
    .bk-ok   .bk-title { color:#166534; } .bk-ok .bk-body { color:#14532d; }

    :root[data-theme="dark"] .bk-warn { background:rgba(217,119,6,.12); border-color:rgba(253,224,71,.32); }
    :root[data-theme="dark"] .bk-warn .bk-title { color:#fcd34d; }
    /* The body was left behind when the title was remapped: #78350f stayed
       dark brown on a background that had turned dark, about 1.9:1. */
    :root[data-theme="dark"] .bk-warn .bk-body  { color:var(--text-secondary); }
    :root[data-theme="dark"] .bk-bad  { background:rgba(220,38,38,.14); border-color:rgba(248,113,113,.35); }
    :root[data-theme="dark"] .bk-bad  .bk-title { color:#fca5a5; }
    :root[data-theme="dark"] .bk-bad  .bk-body  { color:var(--text-secondary); }
    :root[data-theme="dark"] .bk-ok   { background:rgba(22,163,74,.14); border-color:rgba(134,239,172,.30); }
    :root[data-theme="dark"] .bk-ok   .bk-title { color:#86efac; }
    :root[data-theme="dark"] .bk-ok   .bk-body  { color:var(--text-secondary); }
    :root[data-theme="dark"] .bk-note .bk-body { color:var(--text-primary); }

    @media (prefers-color-scheme: dark) {
        :root:not([data-theme="light"]) .bk-warn { background:rgba(217,119,6,.12); border-color:rgba(253,224,71,.32); }
        :root:not([data-theme="light"]) .bk-warn .bk-title { color:#fcd34d; }
        :root:not([data-theme="light"]) .bk-bad  { background:rgba(220,38,38,.14); border-color:rgba(248,113,113,.35); }
        :root:not([data-theme="light"]) .bk-bad  .bk-title { color:#fca5a5; }
        :root:not([data-theme="light"]) .bk-ok   { background:rgba(22,163,74,.14); border-color:rgba(134,239,172,.30); }
        :root:not([data-theme="light"]) .bk-ok   .bk-title { color:#86efac; }
        :root:not([data-theme="light"]) .bk-note .bk-body { color:var(--text-primary); }
    }
    .bk-code { background:rgba(0,0,0,.06); padding:2px 6px; border-radius:4px;
               font-family:ui-monospace,Consolas,monospace; font-size:.78rem; word-break:break-all; }
    :root[data-theme="dark"] .bk-code { background:rgba(255,255,255,.08); }
</style>

<!-- ── Page header ─────────────────────────────────────────────────────── -->
<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <p class="mb-0" style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8;">
            <?= e(t('admin_nav.system')) ?>
        </p>
        <h1 class="mb-0 mt-1" style="font-size:1.55rem;font-weight:800;color:var(--text-primary);line-height:1.1;">
            <?= e(t('superadmin.backups_title')) ?>
        </h1>
        <p class="text-muted mt-1 mb-0" style="font-size:.82rem;">
            <?= e(t('superadmin.backups_subtitle')) ?>
        </p>
    </div>
    <?php if ($available): ?>
    <form method="POST" action="<?= e(route('superadmin/backups')) ?>"
          onsubmit="this.querySelector('button').disabled = true;
                    this.querySelector('button').innerHTML =
                    '<span class=\'spinner-border spinner-border-sm me-1\'></span><?= e(t('superadmin.bk_running')) ?>';">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button type="submit" class="btn btn-barangay" style="border-radius:8px;font-weight:600;">
            <i class="bi bi-database-down me-1"></i><?= e(t('superadmin.bk_run_now')) ?>
        </button>
    </form>
    <?php endif; ?>
</div>

<!-- ── mysqldump missing ───────────────────────────────────────────────── -->
<?php if (!$available): ?>
<div class="bk-note bk-bad">
    <i class="bi bi-x-octagon-fill" style="color:#dc2626;font-size:1.15rem;flex-shrink:0;margin-top:2px;"></i>
    <div>
        <p class="bk-title" style="margin:0 0 5px;font-weight:700;font-size:.92rem;">
            <?= e(t('superadmin.bk_no_dump_title')) ?>
        </p>
        <p class="bk-body" style="margin:0;font-size:.83rem;line-height:1.7;">
            <?= e(t('superadmin.bk_no_dump_body')) ?>
            <span class="bk-code">MYSQLDUMP_PATH=C:\xampp\mysql\bin\mysqldump.exe</span>
        </p>
    </div>
</div>
<?php endif; ?>

<!-- ── Overdue / healthy ───────────────────────────────────────────────── -->
<?php if ($overdue): ?>
<div class="bk-note bk-warn">
    <i class="bi bi-exclamation-triangle-fill" style="color:#d97706;font-size:1.15rem;flex-shrink:0;margin-top:2px;"></i>
    <div>
        <p class="bk-title" style="margin:0 0 5px;font-weight:700;font-size:.92rem;">
            <?= e($lastBackup === null ? t('superadmin.bk_none_title') : t('superadmin.bk_overdue_title')) ?>
        </p>
        <p class="bk-body" style="margin:0;font-size:.83rem;line-height:1.7;">
            <?= e(t('superadmin.bk_overdue_body', ['hours' => $staleHours])) ?>
        </p>
    </div>
</div>
<?php else: ?>
<div class="bk-note bk-ok">
    <i class="bi bi-check-circle-fill" style="color:#16a34a;font-size:1.15rem;flex-shrink:0;margin-top:2px;"></i>
    <div>
        <p class="bk-title" style="margin:0;font-weight:700;font-size:.92rem;">
            <?= e(t('superadmin.bk_healthy', ['when' => $ago((int) $lastBackup)])) ?>
        </p>
    </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <!-- ── Summary ─────────────────────────────────────────────────────── -->
    <div class="col-lg-5">
        <div class="admin-card h-100">
            <h2 style="font-size:.95rem;font-weight:700;color:var(--text-primary);margin:0 0 12px;">
                <i class="bi bi-hdd me-1" style="color:var(--brand-primary);"></i>
                <?= e(t('superadmin.bk_summary')) ?>
            </h2>
            <?php
            $summary = [
                [t('superadmin.bk_count'),  count($backups) . ' / ' . $keepLatest],
                [t('superadmin.bk_total'),  $bytes($totalSize)],
                [t('superadmin.bk_last'),   $lastBackup === null ? '—' : $ago((int) $lastBackup)],
                [t('superadmin.bk_sched'),
                    $scheduled === true  ? t('superadmin.bk_sched_on')
                  : ($scheduled === false ? t('superadmin.bk_sched_off') : t('superadmin.bk_sched_unknown'))],
            ];
            foreach ($summary as [$label, $value]): ?>
            <div style="display:flex;justify-content:space-between;gap:12px;padding:7px 0;
                        border-bottom:1px solid var(--border);font-size:.82rem;">
                <span style="color:var(--text-muted);"><?= e($label) ?></span>
                <span style="font-weight:600;color:var(--text-primary);text-align:right;"><?= e($value) ?></span>
            </div>
            <?php endforeach; ?>
            <p class="text-muted mt-3 mb-0" style="font-size:.74rem;line-height:1.6;word-break:break-all;">
                <?= e(t('superadmin.bk_stored_in')) ?><br><span class="bk-code"><?= e($directory) ?></span>
            </p>
        </div>
    </div>

    <!-- ── Scheduling + restore guidance ───────────────────────────────── -->
    <div class="col-lg-7">
        <div class="admin-card h-100">
            <h2 style="font-size:.95rem;font-weight:700;color:var(--text-primary);margin:0 0 6px;">
                <i class="bi bi-clock me-1" style="color:var(--brand-primary);"></i>
                <?= e(t('superadmin.bk_schedule_title')) ?>
            </h2>
            <p class="text-muted mb-2" style="font-size:.82rem;line-height:1.7;">
                <?= e(t('superadmin.bk_schedule_body')) ?>
            </p>
            <p class="bk-code mb-3" style="display:block;padding:10px 12px;line-height:1.7;">
                schtasks /create /tn "BarangGabay Nightly Backup"<br>
                &nbsp;&nbsp;/tr "<?= e(str_replace('/', '\\', dirname($directory, 2))) ?>\tools\backup-task.bat"<br>
                &nbsp;&nbsp;/sc daily /st 02:00 /rl HIGHEST
            </p>

            <h2 style="font-size:.95rem;font-weight:700;color:var(--text-primary);margin:0 0 6px;">
                <i class="bi bi-arrow-counterclockwise me-1" style="color:#d97706;"></i>
                <?= e(t('superadmin.bk_restore_title')) ?>
            </h2>
            <p class="text-muted mb-0" style="font-size:.82rem;line-height:1.7;">
                <?= e(t('superadmin.bk_restore_body')) ?>
                <span class="bk-code">storage/backups/README.md</span>
            </p>
        </div>
    </div>
</div>

<!-- ── Backup list ─────────────────────────────────────────────────────── -->
<div class="admin-card p-0">
    <div class="table-responsive">
        <table class="table align-middle mb-0" style="font-size:.85rem;">
            <thead>
                <tr style="border-bottom:2px solid var(--border);">
                    <th style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);"><?= e(t('superadmin.bk_col_file')) ?></th>
                    <th style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);"><?= e(t('superadmin.bk_col_created')) ?></th>
                    <th style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);"><?= e(t('superadmin.bk_col_size')) ?></th>
                    <th class="text-end" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);"><?= e(t('superadmin.col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($backups === []): ?>
                <tr><td colspan="4" class="text-center text-muted py-4"><?= e(t('superadmin.bk_none')) ?></td></tr>
            <?php endif; ?>

            <?php foreach ($backups as $i => $b): ?>
                <tr>
                    <td style="font-family:ui-monospace,Consolas,monospace;font-size:.78rem;word-break:break-all;color:var(--text-primary);">
                        <i class="bi bi-file-earmark-zip me-1" style="color:var(--text-muted);"></i>
                        <?= e($b['filename']) ?>
                        <?php if ($i === 0): ?>
                        <span style="font-size:.66rem;font-weight:700;background:#dcfce7;color:#166534;
                                     border-radius:4px;padding:1px 6px;margin-left:6px;">
                            <?= e(t('superadmin.bk_latest')) ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--text-muted);font-size:.78rem;white-space:nowrap;">
                        <?= e(date('M j, Y H:i', $b['created_at'])) ?><br>
                        <span style="font-size:.72rem;"><?= e($ago($b['created_at'])) ?></span>
                    </td>
                    <td style="color:var(--text-muted);font-size:.8rem;white-space:nowrap;"><?= e($bytes($b['size'])) ?></td>
                    <td class="text-end" style="white-space:nowrap;">
                        <a href="<?= e(route('superadmin/backups/download') . '?file=' . urlencode($b['filename'])) ?>"
                           class="btn btn-sm btn-outline-secondary" style="border-radius:6px;"
                           title="<?= e(t('common.download')) ?>">
                            <i class="bi bi-download"></i>
                        </a>
                        <form method="POST" action="<?= e(route('superadmin/backups/delete')) ?>" class="d-inline"
                              onsubmit="return confirm(<?= e(json_encode(t('superadmin.bk_delete_confirm'))) ?>);">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="file" value="<?= e($b['filename']) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius:6px;">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div style="padding:12px 18px;border-top:1px solid var(--border);">
        <p class="mb-0 text-muted" style="font-size:.78rem;">
            <?= e(t('superadmin.bk_retention', ['n' => $keepLatest])) ?>
        </p>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
