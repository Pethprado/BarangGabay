<?php
$authTitle  = 'Forgot password';
$successMsg = flash('success');
$errorMsg   = flash('error');
require __DIR__ . '/_auth_head.php';
?>
<main class="auth-wrap">
    <section class="auth-card" aria-labelledby="authTitle">
        <div class="auth-card__body">
            <?php require __DIR__ . '/_auth_brand.php'; ?>
            <h1 class="auth-title" id="authTitle">Forgot your password?</h1>
            <p class="auth-sub">Enter the email you registered with and we'll send you a link to set a new password.</p>
            <?php if ($successMsg): ?><div class="auth-alert auth-alert--success" role="status"><i class="bi bi-envelope-check-fill"></i><span><?= e($successMsg) ?></span></div><?php endif; ?>
            <?php if ($errorMsg): ?><div class="auth-alert auth-alert--danger" role="alert"><i class="bi bi-exclamation-circle-fill"></i><span><?= e($errorMsg) ?></span></div><?php endif; ?>
            <form method="post" action="<?= e(route('forgot-password')) ?>" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="auth-field">
                    <label class="auth-label" for="fpEmail"><i class="bi bi-envelope" aria-hidden="true"></i>Email address</label>
                    <input class="auth-input" type="email" id="fpEmail" name="email" required autocomplete="email" placeholder="you@example.com">
                </div>
                <button type="submit" class="auth-btn"><i class="bi bi-send" aria-hidden="true"></i>Send reset link</button>
            </form>
            <p class="auth-small"><a class="auth-link" href="<?= e(route('login')) ?>">← Back to sign in</a></p>
        </div>
        <div class="auth-card__foot" role="presentation"></div>
    </section>
</main>
</body>
</html>
