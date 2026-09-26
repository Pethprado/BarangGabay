<?php
/**
 * Admin: back-office account list.
 *
 * Variables: $accounts (list<array>), $pendingCount (int)
 *
 * The only action here is the password reset. Role and status are shown for
 * context but are not editable — changing someone's role is a bigger
 * decision than this screen should carry, and doing it inline next to a
 * reset button invites mistakes.
 */
$accounts ??= [];

$myId   = (int) ($_SESSION['user_id'] ?? 0);
$myRole = (string) ($_SESSION['role'] ?? '');

// Shown once, immediately after a reset. flash() unsets on read, so a page
// refresh will not reveal it again.
$freshPassword = flash('reset_password');
$freshFor      = flash('reset_password_for');

$roleStyles = [
    'superadmin' => ['bg' => '#ede9fe', 'text' => '#5b21b6'],
    'admin'      => ['bg' => '#dbeafe', 'text' => '#1e40af'],
    'staff'      => ['bg' => '#dcfce7', 'text' => '#166534'],
];

ob_start();
?>

<div class="admin-card mb-4">
    <div class="admin-card-header d-flex align-items-start justify-content-between flex-wrap gap-2">
        <div>
            <h2 class="admin-card-title mb-0">
                <i class="bi bi-person-badge me-1" style="color:#e8a020;"></i><?= e(t('staff_list.title')) ?>
            </h2>
            <p class="text-muted mb-0 mt-1" style="font-size:.78rem;line-height:1.6;">
                <?= e(t('staff_list.subtitle')) ?>
            </p>
        </div>
        <a href="<?= e(route('admin/staff/create')) ?>" class="btn-barangay" style="white-space:nowrap;">
            <i class="bi bi-plus-lg me-1"></i><?= e(t('staff_list.add_btn')) ?>
        </a>
    </div>

    <?php if ($freshPassword !== null): ?>
    <div class="admin-card-body pb-0">
        <div style="background:#f0faf4;border:1px solid #c3e6cb;border-radius:10px;padding:14px;">
            <p style="font-size:.8rem;font-weight:700;color:#1c2b1e;margin:0 0 4px;">
                <i class="bi bi-key-fill me-1" style="color:#e8a020;"></i><?= e($freshFor ?? '') ?>
            </p>
            <p style="font-size:.76rem;color:#1c2b1e;margin:0 0 8px;">
                <?= e(t('residents_detail.reset_pw_shown')) ?>
            </p>
            <code style="display:block;font-size:1.05rem;font-weight:700;letter-spacing:.04em;background:#fff;border:1px solid #c3e6cb;border-radius:6px;padding:10px 13px;word-break:break-all;color:#1a6b3a;">
                <?= e($freshPassword) ?>
            </code>
            <p style="font-size:.74rem;color:#4a5568;margin:8px 0 0;">
                <?= e(t('residents_detail.reset_pw_advise')) ?>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <div class="admin-card-body">
        <?php if (!$accounts): ?>
        <p class="text-muted mb-0" style="font-size:.85rem;"><?= e(t('staff_list.empty')) ?></p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0" style="font-size:.845rem;">
                <thead>
                    <tr style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-secondary);">
                        <th scope="col"><?= e(t('staff_list.col_name')) ?></th>
                        <th scope="col"><?= e(t('staff_list.col_role')) ?></th>
                        <th scope="col"><?= e(t('staff_list.col_last_login')) ?></th>
                        <th scope="col" class="text-end"><?= e(t('staff_list.col_actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($accounts as $acct): ?>
                    <?php
                    $role     = (string) ($acct['role'] ?? 'staff');
                    $style    = $roleStyles[$role] ?? $roleStyles['staff'];
                    $isSelf   = (int) $acct['id'] === $myId;
                    // Mirrors the controller's guards exactly. A button that
                    // always 403s is worse than no button.
                    $canReset = in_array($myRole, ['admin', 'superadmin'], true)
                        && !$isSelf
                        && ($role !== 'superadmin' || $myRole === 'superadmin');
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;color:var(--text-primary);">
                                <?= e($acct['full_name']) ?>
                                <?php if ($isSelf): ?>
                                <span class="text-muted" style="font-weight:400;font-size:.76rem;">(<?= e(t('staff_list.you')) ?>)</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted" style="font-size:.76rem;"><?= e($acct['email']) ?></div>
                            <?php if (!empty($acct['designation'])): ?>
                            <div class="text-muted" style="font-size:.74rem;"><?= e($acct['designation']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge rounded-pill"
                                  style="background:<?= e($style['bg']) ?>;color:<?= e($style['text']) ?>;font-size:.68rem;font-weight:700;padding:.3em .7em;">
                                <?= e($role) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (empty($acct['last_login_at'])): ?>
                            <!-- Never signed in: the usual sign that the password
                                 set at creation never reached the person. -->
                            <span style="color:#b45309;font-weight:600;font-size:.78rem;">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i><?= e(t('staff_list.never_logged_in')) ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted" style="font-size:.78rem;">
                                <?= e(date('M j, Y g:i A', strtotime((string) $acct['last_login_at']))) ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($canReset): ?>
                            <form method="post"
                                  action="<?= e(route('admin/residents/' . (int) $acct['id'] . '/reset-password')) ?>"
                                  style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button type="submit"
                                        class="btn btn-sm btn-outline-secondary"
                                        style="font-size:.74rem;white-space:nowrap;"
                                        onclick="return confirm(<?= e(json_encode(t('residents_detail.reset_pw_confirm', ['name' => $acct['full_name'] ?? '']))) ?>)">
                                    <i class="bi bi-key me-1"></i><?= e(t('residents_detail.reset_pw_btn')) ?>
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="text-muted" style="font-size:.74rem;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="form-text mt-3 mb-0" style="line-height:1.6;">
            <i class="bi bi-info-circle me-1"></i><?= e(t('staff_list.reset_note')) ?>
        </p>
        <?php endif; ?>
    </div>
</div>

<?php
$content   = ob_get_clean();
$pageTitle ??= t('staff_list.title');
require __DIR__ . '/../../layouts/admin.php';
?>
