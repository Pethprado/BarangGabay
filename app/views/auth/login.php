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

        <?php if ($entry === 'resident'): ?>
            <div class="auth-divider"><?= e(t('login.or_continue')) ?></div>
            <div class="auth-social">
                <?php $__google = '<svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>'; ?>
                <?php if ($social['google']): ?>
                    <a href="<?= e(route('auth/google')) ?>"><?= $__google ?><?= e(t('login.continue_google')) ?></a>
                <?php else: ?>
                    <button type="button" disabled title="<?= e(t('auth_hero.social_off')) ?>" aria-describedby="socialOffNote"><?= $__google ?><?= e(t('login.continue_google')) ?></button>
                <?php endif; ?>
                <?php if ($social['facebook']): ?>
                    <a href="<?= e(route('auth/facebook')) ?>"><i class="bi bi-facebook" style="color:#1877f2;font-size:1.1rem;" aria-hidden="true"></i><?= e(t('login.continue_facebook')) ?></a>
                <?php else: ?>
                    <button type="button" disabled title="<?= e(t('auth_hero.social_off')) ?>" aria-describedby="socialOffNote"><i class="bi bi-facebook" style="color:#1877f2;font-size:1.1rem;" aria-hidden="true"></i><?= e(t('login.continue_facebook')) ?></button>
                <?php endif; ?>
            </div>
            <?php if (!$social['google'] || !$social['facebook']): ?>
                <p class="auth-note" id="socialOffNote"><i class="bi bi-info-circle" aria-hidden="true"></i><?= e(t('auth_hero.social_off')) ?></p>
            <?php endif; ?>
        <?php endif; ?>

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
