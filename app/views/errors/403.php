<?php
/**
 * 403 — signed in, but this role may not open this page.
 *
 * Rendered by RoleMiddleware. Deliberately standalone rather than wrapped in
 * a layout: the admin layout queries the database and reads role-specific
 * navigation, and a permission failure is exactly the moment not to run more
 * privileged code. It also means this page works no matter which side of the
 * app the blocked route belonged to.
 */
$role = $_SESSION['role'] ?? 'guest';

// Send people somewhere they can actually use.
$isStaffSide = in_array($role, ['admin', 'staff', 'superadmin'], true);
$homeUrl     = $isStaffSide ? app_url('admin') : app_url('');
$homeLabel   = $isStaffSide ? t('errors.back_dashboard') : t('errors.back_home');
$appName     = system_name();
?>
<!DOCTYPE html>
<html lang="<?= e(current_locale() === 'fil' ? 'fil' : 'en') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(t('errors.403_title')) ?> — <?= e($appName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            --bg:      #eef2f7;
            --card:    #ffffff;
            --text:    #1e293b;
            --muted:   #64748b;
            --border:  #e2e8f0;
            --accent:  #2563eb;
        }
        /* Follows the OS theme — this page has no toggle of its own. */
        @media (prefers-color-scheme: dark) {
            :root {
                --bg:     #0b1220;
                --card:   #131b2c;
                --text:   #e7ecf3;
                --muted:  #a9b4c4;
                --border: #263145;
                --accent: #5b8def;
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: var(--bg); color: var(--text); padding: 1rem;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        .card {
            background: var(--card); border: 1px solid var(--border); border-radius: 1.25rem;
            padding: 2.75rem 2.25rem; max-width: 460px; width: 100%; text-align: center;
            box-shadow: 0 24px 60px rgba(15, 23, 42, .10);
        }
        .icon { font-size: 2.75rem; color: #d97706; margin-bottom: 1rem; display: block; }
        h1 { font-size: 1.35rem; font-weight: 800; margin-bottom: .5rem; }
        .role {
            display: inline-block; font-size: .72rem; font-weight: 700; letter-spacing: .05em;
            text-transform: uppercase; background: var(--bg); color: var(--muted);
            border: 1px solid var(--border); border-radius: 20px; padding: 3px 12px; margin-bottom: 1rem;
        }
        p { color: var(--muted); font-size: .9rem; line-height: 1.7; margin-bottom: 1.75rem; }
        .actions { display: flex; gap: .6rem; justify-content: center; flex-wrap: wrap; }
        a.btn, button.btn {
            display: inline-flex; align-items: center; gap: .45rem; cursor: pointer;
            border-radius: .625rem; padding: .65rem 1.4rem; font-size: .875rem;
            font-weight: 700; text-decoration: none; border: 1px solid transparent;
            font-family: inherit;
        }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-ghost   { background: transparent; color: var(--muted); border-color: var(--border); }
    </style>
</head>
<body>
    <div class="card">
        <i class="bi bi-shield-lock icon"></i>
        <h1><?= e(t('errors.403_title')) ?></h1>
        <span class="role"><?= e(t('errors.403_role', ['role' => $role])) ?></span>
        <p><?= e(t('errors.403_body')) ?></p>
        <div class="actions">
            <a href="<?= e($homeUrl) ?>" class="btn btn-primary">
                <i class="bi bi-house"></i> <?= e($homeLabel) ?>
            </a>
            <button type="button" class="btn btn-ghost" onclick="history.back()">
                <i class="bi bi-arrow-left"></i> <?= e(t('errors.go_back')) ?>
            </button>
        </div>
    </div>
</body>
</html>
