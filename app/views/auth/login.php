<?php
/**
 * Sign-in page — split screen (scenic Barangay Bayogo panel + form).
 *
 * Variables from AuthController::showLogin(): $entry ('resident'|'staff'|…),
 * $showRegister, $otherEntries. One form posts to /login from every entry
 * point; ?as= only rebuilds the redirect after a failed attempt.
 *
 * Client-side checks (email format, required password) are UX only; the
 * controller re-validates everything and owns the real error messages.
 */
$successMsg = flash('success');
$errorMsg   = flash('error');
$errorKind  = flash('error_kind');
$errorEntry = flash('error_entry');

$entry        = $entry        ?? 'resident';
$showRegister = $showRegister ?? true;
$otherEntries = $otherEntries ?? ['staff'];
$social       = \App\Controllers\SocialAuthController::enabled();
$formAction = route('login') . ($entry === 'resident' ? '' : '?as=' . $entry);
$authTitle    = t('login.welcome_back');

// A wrong email/password marks both fields invalid, so screen readers announce it on the fields too.
$credentialError = $errorMsg && !in_array($errorKind, ['pending', 'suspended', 'wrong_entry', 'rate_limited'], true);

require __DIR__ . '/_auth_head.php';
require __DIR__ . '/_auth_open.php';
?>
<section class="auth-card" aria-labelledby="authTitle">
    <div class="auth-card__body">
        <div class="auth-brand">
            <?= baranggabay_logo('auto', ['size' => 'large', 'href' => null]) ?>
        </div>

        <h1 class="auth-title" id="authTitle"><?= e(t('login.welcome_back')) ?></h1>
        <p class="auth-sub"><?= e(t('login.entry_' . $entry . '_sub')) ?></p>

        <?php if ($successMsg): ?>
            <div class="auth-alert auth-alert--success" role="status"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span><?= e($successMsg) ?></span></div>
        <?php endif; ?>

        <?php if ($errorMsg):
            $isPending    = $errorKind === 'pending'
                         || ($errorKind === null && (stripos($errorMsg, 'pending') !== false || stripos($errorMsg, 'verification') !== false));
            $isSuspended  = $errorKind === 'suspended';
            $isWrongEntry = $errorKind === 'wrong_entry';
            $tone = $isPending || $isWrongEntry ? 'warning' : ($isSuspended ? 'info' : 'danger');
            $icon = $isWrongEntry ? 'bi-signpost-split' : ($isPending ? 'bi-hourglass-split' : ($isSuspended ? 'bi-slash-circle' : 'bi-exclamation-circle-fill'));
        ?>
            <div class="auth-alert auth-alert--<?= $tone ?>" role="alert" id="loginError">
                <i class="bi <?= $icon ?>" aria-hidden="true"></i>
                <div>
                    <span><?= e($errorMsg) ?></span>
                    <?php if ($isSuspended): ?><div style="font-size:.8rem;margin-top:.25rem;"><?= e(t('login.suspended_contact')) ?></div><?php endif; ?>
                    <?php if ($isWrongEntry && in_array($errorEntry, ['resident', 'staff'], true)): ?>
                        <div style="margin-top:.4rem;"><a class="auth-link" href="<?= e(route('login') . ($errorEntry === 'resident' ? '' : '?as=' . $errorEntry)) ?>"><?= e(t('login.go_to_' . $errorEntry)) ?> →</a></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (($_GET['from'] ?? '') === 'pending'): ?>
            <div class="auth-alert auth-alert--warning" role="status"><i class="bi bi-hourglass-split" aria-hidden="true"></i>
                <div><strong><?= e(t('login.pending_title')) ?></strong><div style="font-size:.8rem;margin-top:.2rem;"><?= e(t('login.pending_body')) ?></div></div>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e($formAction) ?>" novalidate data-auth-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="auth-field">
                <label class="auth-label" for="loginEmail"><i class="bi bi-person" aria-hidden="true"></i><?= e(t('login.email_label')) ?></label>
                <input class="auth-input<?= $credentialError ? ' is-invalid' : '' ?>" type="email" id="loginEmail" name="email"
                       value="<?= e(old('email')) ?>" placeholder="you@example.com" required autocomplete="email" inputmode="email"
                       autofocus data-validate<?= $credentialError ? ' aria-invalid="true" aria-describedby="loginError"' : '' ?>>
            </div>
            <div class="auth-field">
                <label class="auth-label" for="loginPassword"><i class="bi bi-lock" aria-hidden="true"></i><?= e(t('login.password_label')) ?></label>
                <div class="auth-input-wrap">
                    <input class="auth-input auth-input--pw<?= $credentialError ? ' is-invalid' : '' ?>" type="password" id="loginPassword" name="password"
                           placeholder="<?= e(t('login.password_placeholder')) ?>" required autocomplete="current-password"
                           data-validate data-msg-required="<?= e(t('auth_hero.err_password')) ?>"<?= $credentialError ? ' aria-invalid="true" aria-describedby="loginError"' : '' ?>>
                    <button type="button" class="auth-eye" data-pw-toggle="loginPassword" aria-pressed="false" aria-label="<?= e(t('login.show_password')) ?>"><i class="bi bi-eye" aria-hidden="true"></i></button>
                </div>
            </div>
            <div class="auth-row">
                <label class="auth-check"><input type="checkbox" name="remember" value="1"> <?= e(t('login.remember_me')) ?></label>
                <a class="auth-link" href="<?= e(route('forgot-password')) ?>"><?= e(t('login.forgot_password')) ?></a>
            </div>
            <button type="submit" class="auth-btn" data-loading-text="<?= e(t('auth_hero.signing_in')) ?>"><?= e(t('login.submit')) ?></button>
        </form>

        <?php /* Google and Facebook sign-in were removed at the barangay's
                 request: residents sign in with email and password only. */ ?>

        <?php if ($showRegister): ?>
            <p class="auth-small"><?= e(t('login.no_account')) ?> <a class="auth-link" href="<?= e(route('register')) ?>"><?= e(t('login.register_here')) ?></a></p>
        <?php endif; ?>

        <nav class="auth-entries" aria-label="<?= e(t('login.entry_nav_label')) ?>">
            <?php foreach ($otherEntries as $__other): ?>
                <span><?= e(t('login.entry_' . $__other . '_prompt')) ?> <a class="auth-link" href="<?= e(route('login') . ($__other === 'resident' ? '' : '?as=' . $__other)) ?>"><?= e(t('login.entry_' . $__other . '_link')) ?></a></span>
            <?php endforeach; ?>
            <a class="auth-link" href="<?= e(route('')) ?>"><?= e(t('login.about_system')) ?></a>
        </nav>
    </div>
</section>
<?php require __DIR__ . '/_auth_close.php'; ?>
