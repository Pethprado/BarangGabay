<?php
/** Shared <head> for the auth pages. Set $authTitle before including. */
?>
<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(($authTitle ?? 'BarangGabay') . ' — ' . system_name()) ?></title>
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
