<?php
/**
 * My Account — self-service settings for staff, admin and superadmin.
 *
 * Rendered in the admin layout (the resident equivalent is
 * resident/profile.php, which uses the Tailwind resident chrome).
 *
 * Variables from AccountController::index():
 *   array $user, array $sessions, array $activity, string $currentHash,
 *   int $idleMinutes, array $designations, bool $twoFaEnabled,
 *   bool $twoFaRequired, bool $twoFaFeature, array $locales
 *
 * NOTE: role and status are rendered as read-only text on purpose. There is
 * deliberately no input for either — see the security contract on
 * AccountController.
 */
$user         = $user         ?? [];
$sessions     = $sessions     ?? [];
$activity     = $activity     ?? [];
$currentHash  = $currentHash  ?? '';
$idleMinutes  = (int) ($idleMinutes ?? 15);
$designations = $designations ?? [];
$twoFaEnabled = (bool) ($twoFaEnabled ?? false);
$twoFaRequired= (bool) ($twoFaRequired ?? false);
$twoFaFeature = (bool) ($twoFaFeature ?? true);
$locales      = $locales      ?? ['en' => 'English'];

$role          = (string) ($user['role'] ?? 'staff');
$emailVerified = (int) ($user['email_verified'] ?? 0) === 1;
$initial       = mb_strtoupper(mb_substr((string) ($user['full_name'] ?? '?'), 0, 1, 'UTF-8'), 'UTF-8');
$minPassword   = \App\Controllers\AuthController::PASSWORD_MIN_LENGTH;

/** Best-effort, purely cosmetic device label for the sessions table. */
$deviceLabel = static function (?string $agent): string {
    $agent = (string) $agent;
    if ($agent === '') {
        return 'Unknown device';
    }
    $browser = 'Browser';
    foreach (['Edg' => 'Edge', 'OPR' => 'Opera', 'Firefox' => 'Firefox', 'Chrome' => 'Chrome', 'Safari' => 'Safari'] as $token => $name) {
        if (str_contains($agent, $token)) {
            $browser = $name;
            break;
        }
    }
    $os = 'Unknown OS';
    foreach (['Windows' => 'Windows', 'Android' => 'Android', 'iPhone' => 'iOS', 'iPad' => 'iPadOS', 'Mac OS' => 'macOS', 'Linux' => 'Linux'] as $token => $name) {
        if (str_contains($agent, $token)) {
            $os = $name;
            break;
        }
    }
    return $browser . ' · ' . $os;
};

ob_start();
?>

<style>
    .acct-legend { font-size:.95rem; font-weight:700; color:var(--text-primary); margin:0 0 4px;
                   display:flex; align-items:center; gap:.45rem; }
    .acct-help   { font-size:.8rem; color:var(--text-muted); line-height:1.7; margin:0 0 16px; }
    .acct-label  { font-size:.78rem; font-weight:600; }
    .acct-avatar { width:76px; height:76px; border-radius:50%; object-fit:cover; }
    .acct-avatar-fallback { width:76px; height:76px; border-radius:50%; display:flex;
                            align-items:center; justify-content:center; font-size:1.75rem;
                            font-weight:800; color:#fff; background:var(--brand-primary); }
    .acct-locked { font-size:.74rem; color:var(--text-muted); line-height:1.6; }
    .acct-pill   { display:inline-flex; align-items:center; gap:.3rem; border-radius:999px;
                   padding:2px 10px; font-size:.7rem; font-weight:700; }
    .acct-pill-ok   { background:#dcfce7; color:#166534; }
    .acct-pill-warn { background:#fef3c7; color:#92400e; }
    .acct-table td, .acct-table th { font-size:.8rem; vertical-align:middle; }
    .acct-mono { font-family:ui-monospace,Consolas,monospace; font-size:.74rem; }
    :root[data-theme="dark"] .acct-pill-ok   { background:rgba(22,163,74,.18);  color:#86efac; }
    :root[data-theme="dark"] .acct-pill-warn { background:rgba(217,119,6,.18);  color:#fcd34d; }
    @media (prefers-color-scheme: dark) {
        :root:not([data-theme="light"]) .acct-pill-ok   { background:rgba(22,163,74,.18); color:#86efac; }
        :root:not([data-theme="light"]) .acct-pill-warn { background:rgba(217,119,6,.18); color:#fcd34d; }
    }
</style>

<!-- ── Page header ─────────────────────────────────────────────────────── -->
<div class="mb-4">
    <p class="mb-0" style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8;">
        <?= e(t('account.eyebrow')) ?>
    </p>
    <h1 class="mb-0 mt-1" style="font-size:1.55rem;font-weight:800;color:var(--text-primary);line-height:1.1;">
        <?= e(t('account.title')) ?>
    </h1>
    <p class="text-muted mt-1 mb-0" style="font-size:.82rem;max-width:70ch;">
        <?= e(t('account.subtitle')) ?>
    </p>
</div>

<div class="row g-3">

    <!-- ── Identity summary (read-only) ────────────────────────────────── -->
    <div class="col-lg-4">
        <div class="admin-card h-100">
            <div class="d-flex align-items-center gap-3 mb-3">
                <?php if (!empty($user['avatar_url'])): ?>
                <img src="<?= e(asset((string) $user['avatar_url'])) ?>" alt="" class="acct-avatar">
                <?php else: ?>
                <div class="acct-avatar-fallback" aria-hidden="true"><?= e($initial) ?></div>
                <?php endif; ?>
                <div style="min-width:0;">
                    <p class="mb-0 fw-bold text-truncate" style="color:var(--text-primary);font-size:1rem;">
                        <?= e((string) ($user['full_name'] ?? '')) ?>
                    </p>
                    <p class="mb-1 text-muted text-truncate" style="font-size:.78rem;">
                        <?= e((string) ($user['email'] ?? '')) ?>
                    </p>
                    <?php if ($emailVerified): ?>
                    <span class="acct-pill acct-pill-ok"><i class="bi bi-patch-check-fill"></i> <?= e(t('account.email_verified')) ?></span>
                    <?php else: ?>
                    <span class="acct-pill acct-pill-warn"><i class="bi bi-exclamation-circle-fill"></i> <?= e(t('account.email_unverified')) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$emailVerified): ?>
            <p class="acct-help mb-3"><?= e(t('account.email_unverified_help')) ?></p>
            <?php endif; ?>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="role-badge role-<?= e($role) ?>"><?= e(ucfirst($role)) ?></span>
                <span class="acct-pill acct-pill-ok"><?= e(ucfirst((string) ($user['status'] ?? ''))) ?></span>
            </div>

            <p class="mb-1" style="font-size:.8rem;color:var(--text-primary);">
                <i class="bi bi-person-badge me-1 text-muted"></i>
                <?php if (!empty($user['designation'])): ?>
                <?= e((string) $user['designation']) ?>
                <?php else: ?>
                <span class="text-muted fst-italic"><?= e(t('account.designation_none')) ?></span>
                <?php endif; ?>
            </p>

            <!-- Role/status are assigned by an admin, never self-served. -->
            <p class="acct-locked mt-3 mb-0">
                <i class="bi bi-lock-fill me-1"></i>
                <?= e(t('account.locked_note')) ?>
            </p>
        </div>
    </div>

    <!-- ── Profile details ─────────────────────────────────────────────── -->
    <div class="col-lg-8">
        <div class="admin-card h-100">
            <p class="acct-legend">
                <i class="bi bi-person-lines-fill" style="color:var(--brand-primary);"></i><?= e(t('account.profile_title')) ?>
            </p>
            <p class="acct-help"><?= e(t('account.profile_help')) ?></p>

            <form method="POST" action="<?= e(route('admin/account/profile')) ?>" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="full_name" class="form-label acct-label"><?= e(t('account.field_name')) ?> *</label>
                        <input type="text" id="full_name" name="full_name" required maxlength="150"
                               value="<?= e((string) ($user['full_name'] ?? '')) ?>"
                               class="form-control" style="border-radius:8px;">
                    </div>

                    <div class="col-md-6">
                        <label for="phone" class="form-label acct-label"><?= e(t('account.field_phone')) ?></label>
                        <input type="text" id="phone" name="phone" maxlength="20"
                               value="<?= e((string) ($user['phone'] ?? '')) ?>"
                               placeholder="09XX-XXX-XXXX"
                               class="form-control" style="border-radius:8px;">
                    </div>

                    <div class="col-md-6">
                        <label for="designation" class="form-label acct-label"><?= e(t('account.field_designation')) ?></label>
                        <input type="text" id="designation" name="designation" maxlength="100"
                               list="designation-options"
                               value="<?= e((string) ($user['designation'] ?? '')) ?>"
                               class="form-control" style="border-radius:8px;">
                        <datalist id="designation-options">
                            <?php foreach ($designations as $option): ?>
                            <option value="<?= e($option) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <p class="form-text mb-0" style="font-size:.74rem;"><?= e(t('account.designation_help')) ?></p>
                    </div>

                    <div class="col-md-6">
                        <label for="avatar" class="form-label acct-label"><?= e(t('account.field_avatar')) ?></label>
                        <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png"
                               class="form-control" style="border-radius:8px;">
                        <p class="form-text mb-0" style="font-size:.74rem;"><?= e(t('account.avatar_help')) ?></p>
                    </div>
                </div>

                <button type="submit" class="btn-barangay mt-3">
                    <i class="bi bi-check-lg me-1"></i><?= e(t('account.save_profile')) ?>
                </button>
            </form>
        </div>
    </div>

    <!-- ── Change email ────────────────────────────────────────────────── -->
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <p class="acct-legend">
                <i class="bi bi-envelope-at" style="color:var(--brand-primary);"></i><?= e(t('account.email_title')) ?>
            </p>
            <p class="acct-help"><?= e(t('account.email_help')) ?></p>

            <form method="POST" action="<?= e(route('admin/account/email')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <label for="new_email" class="form-label acct-label"><?= e(t('account.field_email_new')) ?> *</label>
                <input type="email" id="new_email" name="email" required maxlength="191"
                       value="<?= e((string) ($user['email'] ?? '')) ?>"
                       class="form-control mb-3" style="border-radius:8px;">

                <label for="email_current_password" class="form-label acct-label"><?= e(t('account.field_current_pw')) ?> *</label>
                <input type="password" id="email_current_password" name="current_password" required
                       autocomplete="current-password"
                       class="form-control mb-1" style="border-radius:8px;">
                <p class="form-text mb-3" style="font-size:.74rem;"><?= e(t('account.current_pw_help')) ?></p>

                <button type="submit" class="btn-barangay">
                    <i class="bi bi-envelope-check me-1"></i><?= e(t('account.save_email')) ?>
                </button>
            </form>
        </div>
    </div>

    <!-- ── Change password ─────────────────────────────────────────────── -->
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <p class="acct-legend">
                <i class="bi bi-shield-lock" style="color:var(--brand-primary);"></i><?= e(t('account.password_title')) ?>
            </p>
            <p class="acct-help"><?= e(t('account.password_help')) ?></p>

            <form method="POST" action="<?= e(route('admin/account/password')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <label for="pw_current" class="form-label acct-label"><?= e(t('account.field_current_pw')) ?> *</label>
                <input type="password" id="pw_current" name="current_password" required
                       autocomplete="current-password"
                       class="form-control mb-3" style="border-radius:8px;">

                <label for="pw_new" class="form-label acct-label"><?= e(t('account.field_password_new')) ?> *</label>
                <input type="password" id="pw_new" name="new_password" required
                       minlength="<?= $minPassword ?>" autocomplete="new-password"
                       class="form-control mb-1" style="border-radius:8px;">
                <p class="form-text mb-3" style="font-size:.74rem;">
                    <?= e(t('account.password_min_help', ['n' => $minPassword])) ?>
                </p>

                <label for="pw_confirm" class="form-label acct-label"><?= e(t('account.field_password_confirm')) ?> *</label>
                <input type="password" id="pw_confirm" name="confirm_password" required
                       minlength="<?= $minPassword ?>" autocomplete="new-password"
                       class="form-control mb-3" style="border-radius:8px;">

                <button type="submit" class="btn-barangay">
                    <i class="bi bi-key me-1"></i><?= e(t('account.save_password')) ?>
                </button>
            </form>
        </div>
    </div>

    <!-- ── Security: 2FA + active sessions ─────────────────────────────── -->
    <div class="col-lg-7">
        <div class="admin-card h-100">
            <p class="acct-legend">
                <i class="bi bi-shield-check" style="color:var(--brand-primary);"></i><?= e(t('account.security_title')) ?>
            </p>

            <!-- 2FA status links into the existing /two-factor pages; nothing is rebuilt here. -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-3"
                 style="border-bottom:1px solid var(--border,#e2e8f0);">
                <div>
                    <p class="mb-1" style="font-size:.85rem;font-weight:600;color:var(--text-primary);">
                        <?= e(t('account.twofa_label')) ?>
                        <?php if ($twoFaEnabled): ?>
                        <span class="acct-pill acct-pill-ok ms-1"><?= e(t('account.twofa_on')) ?></span>
                        <?php else: ?>
                        <span class="acct-pill acct-pill-warn ms-1"><?= e(t('account.twofa_off')) ?></span>
                        <?php endif; ?>
                    </p>
                    <?php if (!$twoFaFeature): ?>
                    <p class="acct-help mb-0"><?= e(t('account.twofa_unavailable')) ?></p>
                    <?php elseif ($twoFaRequired): ?>
                    <p class="acct-help mb-0"><?= e(t('account.twofa_required')) ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($twoFaFeature): ?>
                <a href="<?= e(route('two-factor')) ?>" class="btn-barangay" style="text-decoration:none;">
                    <i class="bi bi-phone me-1"></i>
                    <?= e($twoFaEnabled ? t('account.twofa_manage') : t('account.twofa_enable')) ?>
                </a>
                <?php endif; ?>
            </div>

            <!-- Active sessions — same UserSession module the super admin panel uses. -->
            <p class="acct-legend" style="font-size:.85rem;">
                <i class="bi bi-laptop" style="color:var(--brand-primary);"></i><?= e(t('account.sessions_title')) ?>
            </p>
            <p class="acct-help"><?= e(t('account.sessions_help')) ?></p>

            <?php if (!$sessions): ?>
            <p class="text-muted mb-0" style="font-size:.8rem;"><?= e(t('account.sessions_empty')) ?></p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm acct-table mb-2">
                    <thead>
                        <tr>
                            <th><?= e(t('account.col_device')) ?></th>
                            <th><?= e(t('account.col_ip')) ?></th>
                            <th><?= e(t('account.col_lastseen')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sessions as $session): ?>
                        <?php $isCurrent = hash_equals($currentHash, (string) ($session['session_hash'] ?? '')); ?>
                        <tr>
                            <td>
                                <?= e($deviceLabel($session['user_agent'] ?? null)) ?>
                                <?php if ($isCurrent): ?>
                                <span class="acct-pill acct-pill-ok ms-1"><?= e(t('account.sessions_this')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="acct-mono"><?= e((string) ($session['ip_address'] ?? '—')) ?></td>
                            <td class="text-muted">
                                <?php $idle = (int) ($session['idle_minutes'] ?? 0); ?>
                                <?= $idle < $idleMinutes
                                        ? e(t('account.idle_now'))
                                        : e(t('account.idle_minutes', ['n' => $idle])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <form method="POST" action="<?= e(route('admin/account/sessions/revoke')) ?>"
                  onsubmit="return confirm('<?= e(t('account.sessions_confirm')) ?>');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="btn-action btn-action-warning">
                    <i class="bi bi-box-arrow-right me-1"></i><?= e(t('account.sessions_signout_others')) ?>
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Preferences ─────────────────────────────────────────────────── -->
    <div class="col-lg-5">
        <div class="admin-card h-100">
            <p class="acct-legend">
                <i class="bi bi-sliders" style="color:var(--brand-primary);"></i><?= e(t('account.prefs_title')) ?>
            </p>
            <p class="acct-help"><?= e(t('account.prefs_help')) ?></p>

            <form method="POST" action="<?= e(route('admin/account/preferences')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <?php
                $toggles = [
                    'notify_feedback'      => t('account.pref_feedback'),
                    'notify_registrations' => t('account.pref_registrations'),
                    'notify_content'       => t('account.pref_content'),
                ];
                foreach ($toggles as $name => $label):
                    // Absent column (migration not yet applied) reads as ON,
                    // matching the column default.
                    $on = !array_key_exists($name, $user) || (int) $user[$name] === 1;
                ?>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="<?= e($name) ?>" name="<?= e($name) ?>" value="1"
                           <?= $on ? 'checked' : '' ?>>
                    <label class="form-check-label" for="<?= e($name) ?>" style="font-size:.8rem;">
                        <?= e($label) ?>
                    </label>
                </div>
                <?php endforeach; ?>

                <label for="locale" class="form-label acct-label mt-3"><?= e(t('account.field_language')) ?></label>
                <?php $savedLocale = (string) ($user['locale'] ?? '') ?: current_locale(); ?>
                <select id="locale" name="locale" class="form-select" style="border-radius:8px;">
                    <?php foreach ($locales as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= $savedLocale === $code ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="form-text mb-3" style="font-size:.74rem;"><?= e(t('account.language_help')) ?></p>

                <button type="submit" class="btn-barangay">
                    <i class="bi bi-check-lg me-1"></i><?= e(t('account.save_prefs')) ?>
                </button>
            </form>
        </div>
    </div>

    <!-- ── My recent activity (read-only) ──────────────────────────────── -->
    <div class="col-12">
        <div class="admin-card">
            <p class="acct-legend">
                <i class="bi bi-clock-history" style="color:var(--brand-primary);"></i><?= e(t('account.activity_title')) ?>
            </p>
            <p class="acct-help"><?= e(t('account.activity_help')) ?></p>

            <?php if (!$activity): ?>
            <p class="text-muted mb-0" style="font-size:.8rem;"><?= e(t('account.activity_empty')) ?></p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm acct-table mb-0">
                    <thead>
                        <tr>
                            <th style="width:22%;"><?= e(t('account.col_action')) ?></th>
                            <th><?= e(t('account.col_details')) ?></th>
                            <th style="width:16%;"><?= e(t('account.col_ip')) ?></th>
                            <th style="width:18%;"><?= e(t('account.col_when')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($activity as $row): ?>
                        <tr>
                            <td class="acct-mono"><?= e((string) $row['action']) ?></td>
                            <td class="text-muted"><?= e((string) ($row['description'] ?? '—')) ?></td>
                            <td class="acct-mono text-muted"><?= e((string) ($row['ip_address'] ?? '—')) ?></td>
                            <td class="text-muted"><?= e(date('M j, Y H:i', strtotime((string) $row['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /row -->

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
