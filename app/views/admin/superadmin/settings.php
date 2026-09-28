<?php
/**
 * Super Admin — System Settings (module feature 4).
 *
 * Branding, location, date/time, login limits and maintenance mode. Values
 * live in the settings table, so the system can be re-deployed for another
 * barangay or organisation without a code change.
 */
$settings    = $settings    ?? [];
$logoUrl     = $logoUrl     ?? null;
$timezones   = $timezones   ?? ['Asia/Manila'];
$lastUpdated  = $lastUpdated  ?? null;
$twofaRoles   = $twofaRoles   ?? [];
$selfEnrolled = $selfEnrolled ?? false;

$val = static fn (string $key, string $fallback = ''): string
    => (string) ($settings[$key] ?? $fallback);

$maintenanceOn = !empty($settings['maintenance_mode']);

ob_start();
?>

<style>
    .set-section { margin-bottom:1.25rem; }
    .set-legend  { font-size:.95rem; font-weight:700; color:var(--text-primary); margin:0 0 4px; }
    .set-help    { font-size:.8rem; color:var(--text-muted); line-height:1.7; margin:0 0 14px; }
    .danger-zone { border:1px solid #fca5a5; background:#fef2f2; border-radius:12px; padding:18px 20px; }
    .danger-zone .dz-title { color:#991b1b; }
    .danger-zone .dz-body  { color:#7f1d1d; }
    :root[data-theme="dark"] .danger-zone { background:rgba(220,38,38,.12); border-color:rgba(248,113,113,.35); }
    :root[data-theme="dark"] .danger-zone .dz-title { color:#fca5a5; }
    :root[data-theme="dark"] .danger-zone .dz-body  { color:var(--text-primary); }
    @media (prefers-color-scheme: dark) {
        :root:not([data-theme="light"]) .danger-zone { background:rgba(220,38,38,.12); border-color:rgba(248,113,113,.35); }
        :root:not([data-theme="light"]) .danger-zone .dz-title { color:#fca5a5; }
        :root:not([data-theme="light"]) .danger-zone .dz-body  { color:var(--text-primary); }
    }
    .logo-preview { max-height:64px; max-width:220px; border-radius:8px;
                    background:var(--surface-muted,#f1f5f9); padding:8px 12px; }
</style>

<!-- ── Page header ─────────────────────────────────────────────────────── -->
<div class="mb-4">
    <p class="mb-0" style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8;">
        <?= e(t('admin_nav.system')) ?>
    </p>
    <h1 class="mb-0 mt-1" style="font-size:1.55rem;font-weight:800;color:var(--text-primary);line-height:1.1;">
        <?= e(t('superadmin.settings_title')) ?>
    </h1>
    <p class="text-muted mt-1 mb-0" style="font-size:.82rem;">
        <?= e(t('superadmin.settings_subtitle')) ?>
        <?php if ($lastUpdated !== null): ?>
        &middot; <?= e(t('superadmin.set_last_updated', ['when' => date('M j, Y H:i', strtotime($lastUpdated))])) ?>
        <?php endif; ?>
    </p>
</div>

<!-- ── Maintenance banner ──────────────────────────────────────────────── -->
<?php if ($maintenanceOn): ?>
<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:10px;padding:14px 18px;margin-bottom:1.5rem;
            display:flex;gap:12px;align-items:flex-start;">
    <i class="bi bi-cone-striped" style="color:#dc2626;font-size:1.15rem;flex-shrink:0;margin-top:2px;"></i>
    <div>
        <p style="margin:0 0 4px;font-weight:700;color:#991b1b;font-size:.92rem;">
            <?= e(t('superadmin.set_maint_on_title')) ?>
        </p>
        <p style="margin:0;color:#7f1d1d;font-size:.83rem;line-height:1.7;">
            <?= e(t('superadmin.set_maint_on_body')) ?>
        </p>
    </div>
</div>
<?php endif; ?>

<form method="POST" action="<?= e(route('superadmin/settings')) ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div class="row g-3">
        <!-- ── Identity ────────────────────────────────────────────────── -->
        <div class="col-lg-6">
            <div class="admin-card h-100 set-section">
                <p class="set-legend"><i class="bi bi-building me-1" style="color:var(--brand-primary);"></i><?= e(t('superadmin.set_identity')) ?></p>
                <p class="set-help"><?= e(t('superadmin.set_identity_help')) ?></p>

                <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('superadmin.set_system_name')) ?> *</label>
                <input type="text" name="system_name" required maxlength="100"
                       value="<?= e($val('system_name', 'BarangGabay')) ?>"
                       class="form-control mb-3" style="border-radius:8px;">

                <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('superadmin.set_tagline')) ?></label>
                <input type="text" name="system_tagline" maxlength="150"
                       value="<?= e($val('system_tagline')) ?>"
                       class="form-control mb-3" style="border-radius:8px;">

                <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('superadmin.set_location_name')) ?></label>
                <input type="text" name="location_name" maxlength="100"
                       value="<?= e($val('location_name')) ?>"
                       class="form-control mb-1" style="border-radius:8px;">
                <p class="form-text mb-3" style="font-size:.74rem;"><?= e(t('superadmin.set_location_name_help')) ?></p>

                <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('superadmin.set_location_full')) ?></label>
                <input type="text" name="location_full" maxlength="191"
                       value="<?= e($val('location_full')) ?>"
                       class="form-control mb-1" style="border-radius:8px;">
                <p class="form-text mb-0" style="font-size:.74rem;"><?= e(t('superadmin.set_location_full_help')) ?></p>
            </div>
        </div>

        <!-- ── Date & time ─────────────────────────────────────────────── -->
        <div class="col-lg-6">
            <div class="admin-card h-100 set-section">
                <p class="set-legend"><i class="bi bi-clock me-1" style="color:var(--brand-primary);"></i><?= e(t('superadmin.set_datetime')) ?></p>
                <p class="set-help"><?= e(t('superadmin.set_datetime_help')) ?></p>

                <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('superadmin.set_timezone')) ?></label>
                <select name="timezone" class="form-select mb-1" style="border-radius:8px;">
                    <?php $current = $val('timezone', 'Asia/Manila'); ?>
                    <?php foreach ($timezones as $tz): ?>
                    <option value="<?= e($tz) ?>" <?= $tz === $current ? 'selected' : '' ?>><?= e($tz) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="form-text mb-3" style="font-size:.74rem;">
                    <?= e(t('superadmin.set_timezone_help', ['now' => date('M j, Y g:i A')])) ?>
                </p>

                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('superadmin.set_date_format')) ?></label>
                        <input type="text" name="date_format" value="<?= e($val('date_format', 'M j, Y')) ?>"
                               class="form-control" style="border-radius:8px;">
                        <p class="form-text mb-0" style="font-size:.72rem;"><?= e(date($val('date_format', 'M j, Y'))) ?></p>
                    </div>
                    <div class="col-6">
                        <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('superadmin.set_time_format')) ?></label>
                        <input type="text" name="time_format" value="<?= e($val('time_format', 'g:i A')) ?>"
                               class="form-control" style="border-radius:8px;">
                        <p class="form-text mb-0" style="font-size:.72rem;"><?= e(date($val('time_format', 'g:i A'))) ?></p>
                    </div>
                </div>

                <hr style="border-color:var(--border);margin:1.1rem 0;">

                <p class="set-legend" style="font-size:.88rem;"><i class="bi bi-shield-lock me-1" style="color:var(--brand-primary);"></i><?= e(t('superadmin.set_login_limits')) ?></p>
                <p class="set-help" style="margin-bottom:10px;"><?= e(t('superadmin.set_login_limits_help')) ?></p>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('superadmin.set_max_attempts')) ?></label>
                        <input type="number" name="login_max_attempts" min="1" max="100"
                               value="<?= e($val('login_max_attempts', '5')) ?>"
                               class="form-control" style="border-radius:8px;">
                    </div>
                    <div class="col-6">
                        <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('superadmin.set_lockout_min')) ?></label>
                        <input type="number" name="login_lockout_min" min="1" max="1440"
                               value="<?= e($val('login_lockout_min', '15')) ?>"
                               class="form-control" style="border-radius:8px;">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Two-factor policy ───────────────────────────────────────────── -->
    <div class="admin-card set-section mt-3">
        <p class="set-legend">
            <i class="bi bi-shield-lock me-1" style="color:var(--brand-primary);"></i><?= e(t('superadmin.set_twofa')) ?>
        </p>
        <p class="set-help"><?= e(t('superadmin.set_twofa_help')) ?></p>

        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="twofa-on"
                   name="twofa_enabled" value="1" <?= !empty($settings['twofa_enabled']) ? 'checked' : '' ?>
                   style="cursor:pointer;">
            <label class="form-check-label" for="twofa-on" style="font-weight:600;font-size:.86rem;cursor:pointer;">
                <?= e(t('superadmin.set_twofa_enable')) ?>
            </label>
        </div>

        <p class="form-label mb-2" style="font-size:.78rem;font-weight:600;"><?= e(t('superadmin.set_twofa_required')) ?></p>
        <div class="d-flex gap-3 flex-wrap mb-2">
            <?php foreach (['superadmin', 'admin', 'staff'] as $role): ?>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="tfr-<?= e($role) ?>"
                       name="twofa_required_roles[]" value="<?= e($role) ?>"
                       <?= in_array($role, $twofaRoles, true) ? 'checked' : '' ?> style="cursor:pointer;">
                <label class="form-check-label" for="tfr-<?= e($role) ?>"
                       style="font-size:.85rem;cursor:pointer;text-transform:capitalize;">
                    <?= e($role) ?>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="form-text mb-0" style="font-size:.74rem;"><?= e(t('superadmin.set_twofa_roles_help')) ?></p>

        <?php if (!$selfEnrolled): ?>
        <div style="background:#fefce8;border:1px solid #fde047;border-radius:8px;padding:10px 14px;margin-top:12px;">
            <p style="margin:0;font-size:.8rem;color:#92400e;line-height:1.6;">
                <i class="bi bi-exclamation-triangle me-1"></i><?= e(t('superadmin.set_twofa_warn_self')) ?>
                <a href="<?= e(route('two-factor')) ?>" style="color:#92400e;font-weight:700;">
                    <?= e(t('superadmin.set_twofa_setup_link')) ?>
                </a>
            </p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Free machine translation ────────────────────────────────────── -->
    <div class="set-section">
        <p class="set-legend">
            <i class="bi bi-translate me-1" style="color:#1652f0;"></i><?= e(t('superadmin.set_free_translation')) ?>
        </p>
        <p class="set-help"><?= e(t('superadmin.set_free_translation_help')) ?></p>

        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="freeTranslation"
                   name="free_translation_enabled" value="1"
                   <?= (int) ($settings['free_translation_enabled'] ?? 1) === 1 ? 'checked' : '' ?>
                   style="cursor:pointer;">
            <label class="form-check-label" for="freeTranslation" style="font-weight:600;font-size:.86rem;cursor:pointer;">
                <?= e(t('superadmin.set_free_translation_toggle')) ?>
            </label>
        </div>
    </div>

    <!-- ── Maintenance mode ────────────────────────────────────────────── -->
    <div class="danger-zone mt-3">
        <p class="set-legend dz-title" style="margin-bottom:4px;">
            <i class="bi bi-cone-striped me-1"></i><?= e(t('superadmin.set_maintenance')) ?>
        </p>
        <p class="dz-body" style="font-size:.82rem;line-height:1.7;margin:0 0 12px;">
            <?= e(t('superadmin.set_maintenance_help')) ?>
        </p>

        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="maint"
                   name="maintenance_mode" value="1" <?= $maintenanceOn ? 'checked' : '' ?>
                   style="cursor:pointer;">
            <label class="form-check-label" for="maint" style="font-weight:600;font-size:.86rem;cursor:pointer;">
                <?= e(t('superadmin.set_maintenance_toggle')) ?>
            </label>
        </div>

        <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('superadmin.set_maintenance_msg')) ?></label>
        <textarea name="maintenance_message" rows="2" class="form-control" style="border-radius:8px;"><?= e($val('maintenance_message')) ?></textarea>
    </div>

    <div class="mt-3 mb-4">
        <button type="submit" class="btn btn-barangay" style="border-radius:8px;font-weight:600;">
            <i class="bi bi-check-lg me-1"></i><?= e(t('superadmin.set_save')) ?>
        </button>
    </div>
</form>

<!-- ── Logo (separate form — file upload) ──────────────────────────────── -->
<div class="admin-card">
    <p class="set-legend"><i class="bi bi-image me-1" style="color:var(--brand-primary);"></i><?= e(t('superadmin.set_logo')) ?></p>
    <p class="set-help"><?= e(t('superadmin.set_logo_help')) ?></p>

    <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
        <span style="font-size:.78rem;font-weight:600;color:var(--text-muted);"><?= e(t('superadmin.set_logo_current')) ?></span>
        <img src="<?= e($logoUrl ?? asset('images/logo-dark.svg')) ?>" alt="<?= e(system_name()) ?>" class="logo-preview">
        <?php if ($logoUrl === null): ?>
        <span style="font-size:.75rem;color:var(--text-muted);"><?= e(t('superadmin.set_logo_default')) ?></span>
        <?php endif; ?>
    </div>

    <div class="d-flex gap-2 align-items-end flex-wrap">
        <form method="POST" action="<?= e(route('superadmin/settings/logo')) ?>" enctype="multipart/form-data"
              class="d-flex gap-2 align-items-end flex-wrap" style="flex:1 1 320px;margin:0;">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div style="flex:1 1 240px;">
                <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('superadmin.set_logo_file')) ?></label>
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" required
                       class="form-control" style="border-radius:8px;">
            </div>
            <button type="submit" class="btn btn-barangay" style="border-radius:8px;font-weight:600;">
                <i class="bi bi-upload me-1"></i><?= e(t('superadmin.set_logo_upload')) ?>
            </button>
        </form>

        <?php if ($logoUrl !== null): ?>
        <form method="POST" action="<?= e(route('superadmin/settings/logo/reset')) ?>" style="margin:0;"
              onsubmit="return confirm(<?= e(json_encode(t('superadmin.set_logo_reset_confirm'))) ?>);">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="btn btn-outline-secondary" style="border-radius:8px;">
                <?= e(t('superadmin.set_logo_reset_btn')) ?>
            </button>
        </form>
        <?php endif; ?>
    </div>
    <p class="form-text mt-2 mb-0" style="font-size:.74rem;"><?= e(t('superadmin.set_logo_limits')) ?></p>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
