<?php
/**
 * 2FA login challenge — shown after the password is accepted but before the
 * session is granted. Standalone page (no layout), like the sign-in screen.
 */
$useBackup = $useBackup ?? false;
$remaining = $remaining ?? 0;
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(t('twofa.challenge_title')) ?> — <?= e(system_name()) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <?php /* This page styles itself with var(--status-success) and loaded no
             stylesheet of ours, so that token resolved to nothing and the
             icon, the heading and both links were painted in the inherited
             colour. tokens.css is where the shared values live. */ ?>
    <link rel="stylesheet" href="<?= e(asset_v('assets/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('assets/css/theme.css')) ?>">
    <style>
        body { min-height:100vh; display:flex; align-items:center; justify-content:center;
               background:linear-gradient(180deg,#ecf3ea,#d8e9db);
               font-family:'Segoe UI',system-ui,sans-serif; padding:1rem; }
        .card-2fa { background:#fff; border-radius:1.25rem; padding:2.5rem 2.25rem;
                    max-width:420px; width:100%; border:1px solid #e4ece6;
                    box-shadow:0 24px 80px rgba(15,64,35,.12); }
        .code-input { font-size:1.6rem; letter-spacing:.5em; text-align:center;
                      font-family:ui-monospace,Consolas,monospace; padding:.7rem; }
        .code-input.backup { font-size:1.1rem; letter-spacing:.15em; }
        .btn-bg { background:#1a6b3a; color:#fff; font-weight:700; }
        .btn-bg:hover { background:#145730; color:#fff; }
    </style>
</head>
<body>
    <div class="card-2fa">
        <div class="text-center mb-4">
            <i class="bi bi-shield-lock" style="font-size:2.4rem;color:var(--status-success);"></i>
            <h1 style="font-size:1.25rem;font-weight:800;color:var(--status-success);margin:.75rem 0 .35rem;">
                <?= e(t('twofa.challenge_title')) ?>
            </h1>
            <p class="text-muted mb-0" style="font-size:.86rem;line-height:1.6;">
                <?= e($useBackup ? t('twofa.challenge_backup_help') : t('twofa.challenge_help')) ?>
            </p>
        </div>

        <?php require __DIR__ . '/../shared/_flash.php'; ?>

        <form method="POST" action="<?= e(route('two-factor/challenge')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <?php if ($useBackup): ?>
            <input type="hidden" name="use_backup" value="1">
            <?php endif; ?>

            <input type="text" name="code" required autofocus autocomplete="one-time-code"
                   inputmode="<?= $useBackup ? 'text' : 'numeric' ?>"
                   <?= $useBackup ? 'maxlength="9"' : 'maxlength="6" pattern="[0-9]{6}"' ?>
                   class="form-control code-input <?= $useBackup ? 'backup' : '' ?> mb-3"
                   placeholder="<?= $useBackup ? 'XXXX-XXXX' : '000000' ?>">

            <button type="submit" class="btn btn-bg w-100 mb-3" style="border-radius:.6rem;padding:.65rem;">
                <?= e(t('twofa.verify')) ?>
            </button>
        </form>

        <div class="text-center" style="font-size:.83rem;">
            <?php if ($useBackup): ?>
                <a href="<?= e(route('two-factor/challenge')) ?>" style="color:var(--status-success);">
                    <?= e(t('twofa.use_app_instead')) ?>
                </a>
            <?php else: ?>
                <a href="<?= e(route('two-factor/challenge') . '?backup=1') ?>" style="color:var(--status-success);">
                    <?= e(t('twofa.use_backup_instead')) ?>
                </a>
                <?php if ($remaining > 0): ?>
                <span class="text-muted">(<?= (int) $remaining ?>)</span>
                <?php endif; ?>
            <?php endif; ?>
            <br>
            <a href="<?= e(route('two-factor/cancel')) ?>" class="text-muted d-inline-block mt-2">
                <?= e(t('twofa.cancel')) ?>
            </a>
        </div>
    </div>
</body>
</html>
