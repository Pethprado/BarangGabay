<?php
/**
 * Split-screen authentication shell — opening half. Pair with _auth_close.php.
 *
 * Left: a scenic Barangay Bayogo panel with the brand, a short promise and
 * three things the system does. Right: the form, on a plain surface.
 * Below 992px the scenic panel collapses into a short hero header, so a phone
 * sees the form almost immediately.
 *
 * Optional before including:
 *   $authWide (bool) — wider form column (registration).
 */
$__authWide = !empty($authWide);
$__curLocale = current_locale();
?>
<main class="auth-split">
    <aside class="auth-hero" aria-label="<?= e(system_name()) ?>">
        <div class="auth-hero__inner">
            <a class="auth-hero__brand" href="<?= e(route('')) ?>">
                <?= baranggabay_logo('dark', ['size' => 'large', 'href' => null]) ?>
            </a>

            <div class="auth-hero__copy">
                <p class="auth-hero__tag"><?= e(t('auth_hero.tagline')) ?></p>
                <h2 class="auth-hero__title"><?= e(t('auth_hero.title')) ?></h2>
                <p class="auth-hero__sub"><?= e(t('auth_hero.subtitle')) ?></p>
                <ul class="auth-hero__features">
                    <li><span aria-hidden="true"><i class="bi bi-megaphone-fill"></i></span><?= e(t('auth_hero.f_alerts')) ?></li>
                    <li><span aria-hidden="true"><i class="bi bi-file-earmark-text-fill"></i></span><?= e(t('auth_hero.f_docs')) ?></li>
                    <li><span aria-hidden="true"><i class="bi bi-translate"></i></span><?= e(t('auth_hero.f_lang')) ?></li>
                </ul>
            </div>

            <figure class="auth-hero__quote">
                <blockquote lang="ceb">“<?= e(t('auth_hero.quote')) ?>”</blockquote>
                <figcaption><?= e(t('auth_hero.quote_sub')) ?></figcaption>
            </figure>
        </div>
    </aside>

    <div class="auth-panel">
        <div class="auth-panel__tools">
            <a class="auth-panel__back" href="<?= e(route('')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i><?= e(t('auth_hero.back_home')) ?></a>
            <div class="auth-panel__tools-right">
                <div class="auth-lang" role="group" aria-label="<?= e(t('lang.switch_label')) ?>">
                    <?php foreach (available_locales() as $__code => $__label): ?>
                        <a href="<?= e(route('set-locale/' . $__code)) ?>" title="<?= e($__label) ?>"
                           class="<?= $__curLocale === $__code ? 'is-active' : '' ?>"<?= $__curLocale === $__code ? ' aria-current="true"' : '' ?>><?= e(locale_short_code($__code)) ?></a>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="auth-theme" data-theme-toggle
                        title="<?= e(t('theme.toggle')) ?>" aria-label="<?= e(t('theme.toggle')) ?>">
                    <i class="bi bi-moon-stars-fill" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <div class="auth-panel__body<?= $__authWide ? ' auth-panel__body--wide' : '' ?>">
