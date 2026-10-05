<?php
/** @var string $token @var bool $valid */
$authTitle = 'Reset password';
$errorMsg  = flash('error');
require __DIR__ . '/_auth_head.php';
?>
<main class="auth-wrap">
    <section class="auth-card" aria-labelledby="authTitle">
        <div class="auth-card__body">
            <?php require __DIR__ . '/_auth_brand.php'; ?>
            <h1 class="auth-title" id="authTitle">Set a new password</h1>
            <?php if (!$valid): ?>
                <p class="auth-sub">This link is invalid, already used, or older than one hour.</p>
                <a class="auth-btn" href="<?= e(route('forgot-password')) ?>">Ask for a new link</a>
            <?php else: ?>
                <p class="auth-sub">Choose a password of at least <?= (int) \App\Controllers\AuthController::PASSWORD_MIN_LENGTH ?> characters. You'll be signed out on every device.</p>
                <?php if ($errorMsg): ?><div class="auth-alert auth-alert--danger" role="alert"><i class="bi bi-exclamation-circle-fill"></i><span><?= e($errorMsg) ?></span></div><?php endif; ?>
                <form method="post" action="<?= e(route('reset-password')) ?>" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <div class="auth-field">
                        <label class="auth-label" for="rpPass"><i class="bi bi-lock" aria-hidden="true"></i>New password</label>
                        <input class="auth-input" type="password" id="rpPass" name="password" required minlength="<?= (int) \App\Controllers\AuthController::PASSWORD_MIN_LENGTH ?>" autocomplete="new-password">
                    </div>
                    <div class="auth-field">
                        <label class="auth-label" for="rpConfirm"><i class="bi bi-lock" aria-hidden="true"></i>Confirm new password</label>
                        <input class="auth-input" type="password" id="rpConfirm" name="password_confirmation" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="auth-btn">Change password</button>
                </form>
            <?php endif; ?>
            <p class="auth-small"><a class="auth-link" href="<?= e(route('login')) ?>">← Back to sign in</a></p>
        </div>
        <div class="auth-card__foot" role="presentation"></div>
    </section>
</main>
</body>
</html>
