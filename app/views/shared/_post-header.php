<?php
/**
 * _post-header.php — the shared top of every single-post page.
 *
 * Used by events, announcements and ordinances so all three read the same
 * way: a social-style author row, then a news-style headline and hero image.
 *
 * Set before requiring:
 *   $__pRow     (array)       the content row (must carry the author_* aliases
 *                             the three detail queries now select)
 *   $__pTitle   (string)      the title, already localised by the caller
 *   $__pBadges  (list<array>) [['label' => 'Upcoming', 'style' => 'css…'], …]
 *   $__pStamp   (string|null) timestamp to show; defaults to published_at,
 *                             then created_at
 *   $__pImage   (string|null) hero image path, or null
 *   $__pEyebrow (string|null) small label above the title (e.g. ordinance no.)
 *
 * Colours come from the theme tokens in main.css rather than fixed Tailwind
 * greys, so the header follows the light/dark toggle like the rest of the app.
 */
$__pRow     = $__pRow     ?? [];
$__pTitle   = (string) ($__pTitle ?? '');
$__pBadges  = $__pBadges  ?? [];
$__pImage   = $__pImage   ?? null;
$__pEyebrow = $__pEyebrow ?? null;

// Prefer the moment the post went live over the moment the row was created —
// a draft written last week and published this morning is this morning's news.
$__pStamp = $__pStamp
    ?? ($__pRow['published_at'] ?? null)
    ?: ($__pRow['created_at'] ?? null);

$__pName   = trim((string) ($__pRow['author_name'] ?? '')) ?: 'Barangay Staff';
$__pAvatar = trim((string) ($__pRow['author_avatar'] ?? ''));
$__pRole   = (string) ($__pRow['author_role'] ?? '');
$__pDesig  = trim((string) ($__pRow['author_designation'] ?? ''));

// A designation the barangay set ("Barangay Secretary") is more informative
// than the system role, so it wins when present.
$__pRoleLabel = $__pDesig !== '' ? $__pDesig : match ($__pRole) {
    'superadmin' => t('post.role_superadmin'),
    'admin'      => t('post.role_admin'),
    'staff'      => t('post.role_staff'),
    default      => '',
};

// The seeded superadmin is literally named "Super Admin", which rendered as
// "Super Admin · Super Admin". If the role says nothing the name has not
// already said, drop it rather than printing it twice.
if ($__pRoleLabel !== '' && mb_strtolower($__pRoleLabel) === mb_strtolower($__pName)) {
    $__pRoleLabel = '';
}

$__pInitial = mb_strtoupper(mb_substr($__pName, 0, 1));
$__pRelative = relative_time($__pStamp);
?>

<article class="post-shell">

    <!-- ── Author row (social) ──────────────────────────────────────────
         Who is telling me this, and how fresh is it — the two questions a
         resident asks before reading a barangay notice at all. -->
    <header class="post-byline">
        <?php if ($__pAvatar !== ''): ?>
        <img src="<?= e(asset($__pAvatar)) ?>" alt="" class="post-avatar">
        <?php else: ?>
        <span class="post-avatar post-avatar-initial" aria-hidden="true"><?= e($__pInitial) ?></span>
        <?php endif; ?>

        <div class="post-byline-text">
            <p class="post-author">
                <?= e($__pName) ?><?php if ($__pRoleLabel !== ''): ?><span class="post-author-role"> · <?= e($__pRoleLabel) ?></span><?php endif; ?>
            </p>
            <p class="post-meta">
                <?php if ($__pRelative !== ''): ?>
                <time datetime="<?= e((string) $__pStamp) ?>" title="<?= e(date('F j, Y g:i A', strtotime((string) $__pStamp))) ?>">
                    <?= e($__pRelative) ?>
                </time>
                <?php endif; ?>
                <?php foreach ($__pBadges as $__b): ?>
                    <?php if (trim((string) ($__b['label'] ?? '')) === '') { continue; } ?>
                <span class="post-badge <?= e($__b['class'] ?? '') ?>" style="<?= e($__b['style'] ?? '') ?>">
                    <?= e($__b['label']) ?>
                </span>
                <?php endforeach; ?>
            </p>
        </div>
    </header>

    <!-- ── Headline (news) ────────────────────────────────────────────── -->
    <?php if ($__pEyebrow !== null && trim((string) $__pEyebrow) !== ''): ?>
    <p class="post-eyebrow"><?= e($__pEyebrow) ?></p>
    <?php endif; ?>

    <h1 class="post-title"><?= e($__pTitle) ?></h1>

    <!-- Hero sits between headline and body, the way a news article runs. -->
    <?php if ($__pImage !== null && trim((string) $__pImage) !== ''): ?>
    <figure class="post-hero">
        <img src="<?= e(asset($__pImage)) ?>" alt="<?= e($__pTitle) ?>" loading="lazy" onerror="this.closest('figure').remove()">
    </figure>
    <?php endif; ?>
</article>
