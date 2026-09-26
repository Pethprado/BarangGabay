<?php
/**
 * Two-factor setup / management, for any signed-in user.
 *
 * Uses the admin layout for staff-side roles and the resident layout
 * otherwise, so the page always sits inside familiar chrome.
 */
$enabled     = $enabled     ?? false;
$required    = $required    ?? false;
$secret      = $secret      ?? null;
$qrSvg       = $qrSvg       ?? null;
$uri         = $uri         ?? null;
$remaining   = $remaining   ?? 0;
$confirmedAt = $confirmedAt ?? null;
$newCodes    = $newCodes    ?? [];

$isAdminSide = in_array($_SESSION['role'] ?? '', ['admin', 'staff', 'superadmin'], true);

ob_start();
?>

<style>
    .tf-card { background:var(--surface-card,#fff); border:1px solid var(--border,#e2e8f0);
               border-radius:14px; padding:1.5rem; }
    .tf-qr   { background:#fff; padding:14px; border-radius:12px; display:inline-block;
               border:1px solid #e2e8f0; }
    .tf-qr svg { display:block; width:200px; height:200px; }
    .tf-secret { font-family:ui-monospace,Consolas,monospace; font-size:.9rem; letter-spacing:.08em;
                 background:var(--surface-muted,#f1f5f9); padding:8px 12px; border-radius:8px;
                 word-break:break-all; display:inline-block; }
    .tf-codes { display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:8px; }
    .tf-code  { font-family:ui-monospace,Consolas,monospace; font-size:1rem; font-weight:700;
                text-align:center; padding:8px 6px; border-radius:8px;
                background:#fff; border:1px dashed #94a3b8; color:#0f172a; }
    .tf-badge-on  { background:#dcfce7; color:#166534; }
    .tf-badge-off { background:#fee2e2; color:#991b1b; }
    .tf-badge { font-size:.72rem; font-weight:700; border-radius:20px; padding:3px 12px; }
    :root[data-theme="dark"] .tf-badge-on  { background:rgba(22,163,74,.22); color:#86efac; }
    :root[data-theme="dark"] .tf-badge-off { background:rgba(220,38,38,.22); color:#fca5a5; }
</style>

<div class="mb-4">
    <h1 style="font-size:1.5rem;font-weight:800;color:var(--text-primary,#0f172a);line-height:1.1;margin:0 0 6px;">
        <?= e(t('twofa.setup_title')) ?>
    </h1>
    <p class="text-muted mb-0" style="font-size:.85rem;">
        <?= e(t('twofa.setup_subtitle')) ?>
        <span class="tf-badge <?= $enabled ? 'tf-badge-on' : 'tf-badge-off' ?> ms-1">
            <?= e($enabled ? t('twofa.status_on') : t('twofa.status_off')) ?>
        </span>
    </p>
</div>

<?php require __DIR__ . '/../shared/_flash.php'; ?>

<!-- ── One-time backup codes ───────────────────────────────────────────── -->
<?php if ($newCodes !== []): ?>
<div class="tf-card mb-4" style="border-color:#fbbf24;background:rgba(217,119,6,.08);">
    <h2 style="font-size:1rem;font-weight:700;color:var(--text-primary,#0f172a);margin:0 0 4px;">
        <i class="bi bi-key me-1" style="color:#d97706;"></i><?= e(t('twofa.codes_title')) ?>
    </h2>
    <p style="font-size:.84rem;line-height:1.7;color:var(--text-primary,#0f172a);margin:0 0 14px;">
        <strong><?= e(t('twofa.codes_warning')) ?></strong> <?= e(t('twofa.codes_help')) ?>
    </p>
    <div class="tf-codes mb-3">
        <?php foreach ($newCodes as $code): ?>
        <div class="tf-code"><?= e($code) ?></div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;"
            onclick="navigator.clipboard.writeText(<?= e(json_encode(implode("\n", $newCodes))) ?>).then(function(){ this.textContent='<?= e(t('twofa.copied')) ?>'; }.bind(this));">
        <i class="bi bi-clipboard me-1"></i><?= e(t('twofa.copy_codes')) ?>
    </button>
</div>
<?php endif; ?>

<?php if (!$enabled): ?>
<!-- ── Enrolment ───────────────────────────────────────────────────────── -->
<div class="tf-card">
    <?php if ($required): ?>
    <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:10px;padding:12px 16px;margin-bottom:1.25rem;">
        <?php /* --tw-red-700 is declared only in main.css, and this page is
                 reached from the admin side too, where it resolved to nothing
                 and the warning lost its colour. --status-danger is shared. */ ?>
        <p style="margin:0;font-size:.85rem;color:var(--status-danger);font-weight:600;">
            <i class="bi bi-exclamation-triangle me-1"></i><?= e(t('twofa.required_notice')) ?>
        </p>
    </div>
    <?php endif; ?>

    <div class="row g-4 align-items-start">
        <div class="col-md-auto text-center">
            <div class="tf-qr"><?= $qrSvg /* trusted: generated locally by BaconQrCode */ ?></div>
        </div>
        <div class="col-md">
            <h2 style="font-size:1rem;font-weight:700;color:var(--text-primary,#0f172a);margin:0 0 10px;">
                <?= e(t('twofa.step1')) ?>
            </h2>
            <p class="text-muted" style="font-size:.85rem;line-height:1.7;">
                <?= e(t('twofa.step1_help')) ?>
            </p>

            <p class="text-muted mb-1" style="font-size:.8rem;"><?= e(t('twofa.manual_entry')) ?></p>
            <p class="mb-4"><span class="tf-secret"><?= e(chunk_split((string) $secret, 4, ' ')) ?></span></p>

            <h2 style="font-size:1rem;font-weight:700;color:var(--text-primary,#0f172a);margin:0 0 10px;">
                <?= e(t('twofa.step2')) ?>
            </h2>
            <form method="POST" action="<?= e(route('two-factor/enable')) ?>" class="d-flex gap-2 flex-wrap">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="text" name="code" required maxlength="6" pattern="[0-9]{6}"
                       inputmode="numeric" autocomplete="one-time-code" placeholder="000000"
                       class="form-control" style="max-width:160px;border-radius:8px;
                              font-family:ui-monospace,Consolas,monospace;letter-spacing:.3em;text-align:center;">
                <button type="submit" class="btn btn-barangay" style="border-radius:8px;font-weight:600;">
                    <?= e(t('twofa.turn_on')) ?>
                </button>
            </form>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ── Active ──────────────────────────────────────────────────────────── -->
<div class="row g-3">
    <div class="col-lg-6">
        <div class="tf-card h-100">
            <h2 style="font-size:1rem;font-weight:700;color:var(--text-primary,#0f172a);margin:0 0 6px;">
                <i class="bi bi-shield-check me-1" style="color:#16a34a;"></i><?= e(t('twofa.active_title')) ?>
            </h2>
            <p class="text-muted mb-3" style="font-size:.85rem;line-height:1.7;">
                <?= e(t('twofa.active_help')) ?>
                <?php if ($confirmedAt !== null): ?>
                <br><?= e(t('twofa.enabled_on', ['when' => date('M j, Y', strtotime((string) $confirmedAt))])) ?>
                <?php endif; ?>
            </p>

            <p style="font-size:.85rem;margin:0 0 4px;color:var(--text-primary,#0f172a);">
                <strong><?= (int) $remaining ?></strong> <?= e(t('twofa.codes_left')) ?>
            </p>
            <?php if ($remaining <= 2): ?>
            <p style="font-size:.8rem;color:var(--status-warning);margin:0 0 12px;">
                <i class="bi bi-exclamation-triangle me-1"></i><?= e(t('twofa.codes_low')) ?>
            </p>
            <?php endif; ?>

            <form method="POST" action="<?= e(route('two-factor/backup-codes')) ?>" class="d-flex gap-2 flex-wrap mt-3"
                  onsubmit="return confirm(<?= e(json_encode(t('twofa.regen_confirm'))) ?>);">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="text" name="code" required maxlength="6" pattern="[0-9]{6}"
                       inputmode="numeric" placeholder="000000" class="form-control"
                       style="max-width:140px;border-radius:8px;text-align:center;
                              font-family:ui-monospace,Consolas,monospace;">
                <button type="submit" class="btn btn-outline-secondary" style="border-radius:8px;font-weight:600;">
                    <?= e(t('twofa.regen_codes')) ?>
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="tf-card h-100">
            <h2 style="font-size:1rem;font-weight:700;color:var(--text-primary,#0f172a);margin:0 0 6px;">
                <?= e(t('twofa.disable_title')) ?>
            </h2>
            <?php if ($required): ?>
            <p class="text-muted mb-0" style="font-size:.85rem;line-height:1.7;">
                <i class="bi bi-lock me-1"></i><?= e(t('twofa.cannot_disable')) ?>
            </p>
            <?php else: ?>
            <p class="text-muted mb-3" style="font-size:.85rem;line-height:1.7;">
                <?= e(t('twofa.disable_help')) ?>
            </p>
            <form method="POST" action="<?= e(route('two-factor/disable')) ?>" class="d-flex gap-2 flex-wrap"
                  onsubmit="return confirm(<?= e(json_encode(t('twofa.disable_confirm'))) ?>);">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="text" name="code" required maxlength="9" placeholder="000000"
                       class="form-control" style="max-width:160px;border-radius:8px;text-align:center;
                              font-family:ui-monospace,Consolas,monospace;">
                <button type="submit" class="btn btn-outline-danger" style="border-radius:8px;font-weight:600;">
                    <?= e(t('twofa.turn_off')) ?>
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . ($isAdminSide ? '/../layouts/admin.php' : '/../layouts/main.php');
