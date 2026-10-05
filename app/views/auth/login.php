<?php
/**
 * Sign-in page (mockup layout): deep-green page, centred card, landscape foot.
 *
 * Variables from AuthController::showLogin(): $entry ('resident'|'staff'|…),
 * $showRegister, $otherEntries. One form posts to /login from every entry
 * point; ?as= only rebuilds the redirect after a failed attempt.
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
?>
<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(t('login.page_title', ['system' => system_name()])) ?></title>
    <base href="<?= e(base_url()) ?>">
    <script>
        (function () {
            try { var t = localStorage.getItem('bg-theme'); if (t === 'dark' || t === 'light') { document.documentElement.setAttribute('data-theme', t); } } catch (e) {}
        })();
    </script>
    <link rel="icon" href="<?= e(asset('images/logo-icon.svg')) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Bitter:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= e(asset_v('assets/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('assets/css/theme.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('assets/css/auth.css')) ?>">
</head>
<body class="auth-page">
<main class="auth-wrap">
    <section class="auth-card" aria-labelledby="authTitle">
        <div class="auth-card__body">
            <div class="auth-brand">
                <?php if (system_logo_url() !== null): ?>
                    <img src="<?= e((string) system_logo_url()) ?>" alt="" class="auth-brand__mark" style="object-fit:contain;">
                <?php else: ?>
                    <img src="<?= e(asset('images/logo-icon.svg')) ?>" alt="" class="auth-brand__mark">
                <?php endif; ?>
                <p class="auth-brand__name">BARANG<span>GABAY</span></p>
                <p class="auth-brand__tag">Your Barangay. Connected.</p>
            </div>

            <h1 class="auth-title" id="authTitle"><?= e(t('login.welcome_back')) ?></h1>
            <p class="auth-sub"><?= e(t('login.entry_' . $entry . '_sub')) ?></p>

            <?php if ($successMsg): ?>
                <div class="auth-alert auth-alert--success" role="status"><i class="bi bi-check-circle-fill"></i><span><?= e($successMsg) ?></span></div>
            <?php endif; ?>

            <?php if ($errorMsg):
                $isPending    = $errorKind === 'pending'
                             || ($errorKind === null && (stripos($errorMsg, 'pending') !== false || stripos($errorMsg, 'verification') !== false));
                $isSuspended  = $errorKind === 'suspended';
                $isWrongEntry = $errorKind === 'wrong_entry';
                $tone = $isPending || $isWrongEntry ? 'warning' : ($isSuspended ? 'info' : 'danger');
                $icon = $isWrongEntry ? 'bi-signpost-split' : ($isPending ? 'bi-hourglass-split' : ($isSuspended ? 'bi-slash-circle' : 'bi-exclamation-circle-fill'));
            ?>
                <div class="auth-alert auth-alert--<?= $tone ?>" role="alert">
                    <i class="bi <?= $icon ?>"></i>
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
                <div class="auth-alert auth-alert--warning" role="status"><i class="bi bi-hourglass-split"></i>
                    <div><strong><?= e(t('login.pending_title')) ?></strong><div style="font-size:.8rem;margin-top:.2rem;"><?= e(t('login.pending_body')) ?></div></div>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e($formAction) ?>" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="auth-field">
                    <label class="auth-label" for="loginEmail"><i class="bi bi-person" aria-hidden="true"></i><?= e(t('login.email_label')) ?></label>
                    <input class="auth-input" type="email" id="loginEmail" name="email" value="<?= e(old('email')) ?>" placeholder="you@example.com" required autocomplete="email">
                </div>
                <div class="auth-field">
                    <label class="auth-label" for="loginPassword"><i class="bi bi-lock" aria-hidden="true"></i><?= e(t('login.password_label')) ?></label>
                    <div class="auth-input-wrap">
                        <input class="auth-input auth-input--pw" type="password" id="loginPassword" name="password" placeholder="<?= e(t('login.password_placeholder')) ?>" required autocomplete="current-password">
                        <button type="button" class="auth-eye" data-pw-toggle="loginPassword" aria-label="<?= e(t('login.show_password')) ?>"><i class="bi bi-eye" aria-hidden="true"></i></button>
                    </div>
                </div>
                <div class="auth-row">
                    <label class="auth-check"><input type="checkbox" name="remember" value="1"> <?= e(t('login.remember_me')) ?></label>
                    <a class="auth-link" href="<?= e(route('forgot-password')) ?>"><?= e(t('login.forgot_password')) ?></a>
                </div>
                <button type="submit" class="auth-btn"><?= e(t('login.submit')) ?></button>
            </form>

            <?php if ($entry === 'resident' && ($social['google'] || $social['facebook'])): ?>
                <div class="auth-divider"><?= e(t('login.or_continue')) ?></div>
                <div class="auth-social">
                    <?php if ($social['google']): ?>
                        <a href="<?= e(route('auth/google')) ?>"><svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg><?= e(t('login.continue_google')) ?></a>
                    <?php endif; ?>
                    <?php if ($social['facebook']): ?>
                        <a href="<?= e(route('auth/facebook')) ?>"><i class="bi bi-facebook" style="color:#1877f2;font-size:1.1rem;" aria-hidden="true"></i><?= e(t('login.continue_facebook')) ?></a>
                    <?php endif; ?>
                </div>
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
        <div class="auth-card__foot" role="presentation"></div>
    </section>
</main>
<script>
document.querySelectorAll('[data-pw-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var input = document.getElementById(btn.dataset.pwToggle);
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.querySelector('i').className = 'bi ' + (show ? 'bi-eye-slash' : 'bi-eye');
        btn.setAttribute('aria-label', show ? <?= json_encode(t('login.hide_password')) ?> : <?= json_encode(t('login.show_password')) ?>);
    });
});
</script>
</body>
</html>
