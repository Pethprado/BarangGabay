<?php
/**
 * _source-embed.php — show the original social media post a notice came from.
 *
 * Set before requiring:
 *   $__seRow      (array) the content row (uses source_url / source_platform /
 *                 source_meta)
 *   $__seNoAssets (bool, optional) skip the <link>/<script> tags — set by the
 *                 admin preview endpoint, whose HTML is injected into a page
 *                 that already loaded them.
 *
 * Renders nothing when a post was written here, which is the ordinary case.
 *
 * The goal is that a resident sees the post the way it looks on the platform:
 * a Facebook post with its photos and caption laid out by Facebook, a TikTok
 * with its player, a YouTube video that plays right here. So each platform is
 * drawn by its own official embed:
 *
 *   Facebook        the Facebook SDK (fb-post / fb-video), which sizes the
 *                   frame to the post instead of cropping it.
 *   Video players   YouTube, Vimeo, Dailymotion, Drive — an iframe with the
 *                   video's own shape (16:9, or 9:16 for Shorts).
 *   Social cards    TikTok, Instagram, X, Threads — their embed page, resized
 *                   to fit by source-embed.js where the platform reports its
 *                   height.
 *   A video file    <video controls>.
 *   Anything else   a preview card with the page's picture and title.
 *
 * There is always a card underneath that opens the original in a new tab:
 * third-party embeds fail for reasons we do not control — a post set to
 * friends-only, a blocked tracker, no network — and a dead grey box with no way
 * out is worse than no embed at all.
 */

use App\Services\SourceLink;

$__seRow = (array) ($__seRow ?? []);
$__seUrl = trim((string) ($__seRow['source_url'] ?? ''));

if ($__seUrl === '' || !preg_match('#^https?://#i', $__seUrl)) {
    unset($__seNoAssets);
    return;
}

// detect() is re-run to rebuild the embed from the stored address; the stored
// platform is only a fallback for rows saved before a platform was recognised.
$__seSpec     = SourceLink::detect($__seUrl);
$__sePlatform = $__seSpec['platform'] !== SourceLink::OTHER
    ? $__seSpec['platform']
    : ((string) ($__seRow['source_platform'] ?? '') ?: SourceLink::OTHER);
$__seMeta     = SourceLink::decodeMeta($__seRow['source_meta'] ?? null);
$__seLabel    = t('source.platform_' . $__sePlatform);

// Shown on the card so a resident can see where the button goes before they
// press it — a bare "view the original" says nothing about where it leads.
$__seHost = strtolower((string) (parse_url($__seUrl, PHP_URL_HOST) ?: ''));
$__seHost = preg_replace('/^www\./', '', $__seHost) ?? $__seHost;

$__seIcon = match ($__sePlatform) {
    SourceLink::FACEBOOK    => 'bi-facebook',
    SourceLink::YOUTUBE     => 'bi-youtube',
    SourceLink::TIKTOK      => 'bi-tiktok',
    SourceLink::INSTAGRAM   => 'bi-instagram',
    SourceLink::TWITTER     => 'bi-twitter-x',
    SourceLink::THREADS     => 'bi-threads',
    SourceLink::VIMEO       => 'bi-vimeo',
    SourceLink::DAILYMOTION,
    SourceLink::VIDEO       => 'bi-play-btn',
    SourceLink::IMAGE       => 'bi-image',
    SourceLink::DRIVE,
    SourceLink::DOCS        => 'bi-google',
    default                 => 'bi-link-45deg',
};

$__seRender = $__seSpec['render'];
$__seTitle  = t('source.embed_title', ['platform' => $__seLabel]);

// The stylesheet and script are emitted once per page, however many embeds it
// carries, and never on pages without one.
if (empty($__seNoAssets) && empty($GLOBALS['__seAssetsDone'])):
    $GLOBALS['__seAssetsDone'] = true;
?>
<link rel="stylesheet" href="<?= e(asset_v('assets/css/source-embed.css')) ?>">
<script src="<?= e(asset_v('assets/js/source-embed.js')) ?>" defer
        data-fb-locale="<?= current_locale() === 'en' ? 'en_US' : 'tl_PH' ?>"></script>
<?php endif; unset($__seNoAssets); ?>

<div class="source-embed" data-source-embed data-platform="<?= e($__sePlatform) ?>">
    <p class="source-embed-head">
        <i class="bi <?= e($__seIcon) ?>" aria-hidden="true"></i>
        <span><?= e(t('source.from', ['platform' => $__seLabel])) ?></span>
    </p>

    <?php if ($__seRender === SourceLink::RENDER_FACEBOOK): ?>
    <?php /*
         * Drawn by the Facebook SDK, which source-embed.js loads only on pages
         * that have one of these. The SDK reads data-href, fetches the public
         * post from Facebook and sizes the frame to it — the caption, photos,
         * and a video player that plays here, laid out the way Facebook does.
         * data-width is set by the script to the column width before parsing.
         */ ?>
    <div class="source-embed-fb">
        <div class="<?= $__seSpec['fb_type'] === 'video' ? 'fb-video' : 'fb-post' ?>"
             data-href="<?= e((string) $__seSpec['href']) ?>"
             data-show-text="true"
             data-allowfullscreen="true"
             data-lazy="true"></div>
    </div>

    <?php elseif ($__seRender === SourceLink::RENDER_IFRAME): ?>
    <div class="source-embed-frame shape-<?= e($__seSpec['shape']) ?>">
        <?php /*
             * Sandboxed, deliberately narrowly. allow-same-origin is required —
             * without it every one of these players refuses to start — and is
             * safe here because each frame is a different origin from ours: it
             * lets the player use its own storage, not touch our page. No
             * allow-forms, no allow-top-navigation: an embedded post must not
             * be able to move the resident off the barangay's page.
             *
             * data-autosize marks frames whose platform reports its height by
             * postMessage (Instagram, X); source-embed.js fits them to it.
             */ ?>
        <iframe src="<?= e((string) $__seSpec['embed']) ?>"
                title="<?= e($__seTitle) ?>"
                loading="lazy"
                referrerpolicy="strict-origin-when-cross-origin"
                sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-presentation"
                allow="autoplay; encrypted-media; picture-in-picture; fullscreen; clipboard-write; web-share"
                <?= in_array($__sePlatform, [SourceLink::INSTAGRAM, SourceLink::TWITTER], true) ? 'data-autosize="' . e($__sePlatform) . '"' : '' ?>
                allowfullscreen></iframe>
    </div>

    <?php elseif ($__seRender === SourceLink::RENDER_VIDEO): ?>
    <div class="source-embed-video">
        <video src="<?= e($__seUrl) ?>" controls playsinline preload="metadata"
               <?= !empty($__seMeta['image']) ? 'poster="' . e($__seMeta['image']) . '"' : '' ?>
               title="<?= e($__seTitle) ?>"></video>
    </div>

    <?php elseif ($__seRender === SourceLink::RENDER_IMAGE): ?>
    <div class="source-embed-image">
        <img src="<?= e($__seUrl) ?>" alt="<?= e($__seTitle) ?>" loading="lazy" referrerpolicy="no-referrer">
    </div>

    <?php elseif (!empty($__seMeta['title']) || !empty($__seMeta['image'])): ?>
    <?php /* No embed exists — a website, or a Facebook share link the plugin
             cannot follow. A preview card with the page's own picture and
             headline, the way a chat app shows a link. */ ?>
    <a class="source-embed-preview" href="<?= e($__seUrl) ?>" target="_blank" rel="noopener noreferrer nofollow">
        <?php if (!empty($__seMeta['image'])): ?>
        <img src="<?= e($__seMeta['image']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"
             onerror="this.remove()">
        <?php endif; ?>
        <span class="source-embed-preview-text">
            <?php if (!empty($__seMeta['site'])): ?>
            <small><?= e($__seMeta['site']) ?></small>
            <?php endif; ?>
            <?php if (!empty($__seMeta['title'])): ?>
            <strong><?= e($__seMeta['title']) ?></strong>
            <?php endif; ?>
            <?php if (!empty($__seMeta['description'])): ?>
            <span><?= e(mb_strimwidth($__seMeta['description'], 0, 220, '…')) ?></span>
            <?php endif; ?>
        </span>
    </a>
    <?php endif; ?>

    <a class="source-embed-card" href="<?= e($__seUrl) ?>"
       target="_blank" rel="noopener noreferrer nofollow">
        <span class="source-embed-card-icon" aria-hidden="true">
            <i class="bi <?= e($__seIcon) ?>"></i>
        </span>
        <span class="source-embed-card-text">
            <strong><?= e(t('source.view_original')) ?></strong>
            <?php if ($__seHost !== ''): ?>
            <small><?= e($__seHost) ?></small>
            <?php endif; ?>
        </span>
        <i class="bi bi-box-arrow-up-right source-embed-card-go" aria-hidden="true"></i>
    </a>

    <?php if ($__seRender !== null): ?>
    <p class="source-embed-fallback"><?= e(t('source.embed_fallback')) ?></p>
    <?php endif; ?>
</div>
