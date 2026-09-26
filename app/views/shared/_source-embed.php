<?php
/**
 * _source-embed.php — show the original post a notice came from.
 *
 * Set before requiring:
 *   $__seRow (array) the content row (uses source_url / source_platform)
 *
 * Renders nothing when a post was written here, which is the ordinary case.
 *
 * Two jobs at once, and the second is the one that matters: attribution. If
 * the barangay copied a notice across from its own Facebook page, linking back
 * is a courtesy. If the words came from a news outlet or somebody else's post,
 * the link is what makes this a quotation rather than a repost — so the source
 * is shown whether or not the platform offers an embed.
 *
 * The iframe is sandboxed and lazy-loaded, and there is always a card
 * underneath that opens the original in a new tab: third-party embeds fail for
 * reasons we do not control — a post set to friends-only, a blocked tracker,
 * no network — and a dead grey box with no way out is worse than no embed at
 * all. The card is a real button-sized target rather than a line of small
 * text, because on a phone it is often the only way through to the post.
 */

use App\Services\SourceLink;

$__seRow = (array) ($__seRow ?? []);
$__seUrl = trim((string) ($__seRow['source_url'] ?? ''));

if ($__seUrl === '' || !preg_match('#^https?://#i', $__seUrl)) {
    return;
}

// The stored platform is authoritative — it was settled when staff pasted the
// link. detect() is re-run only to rebuild the embed URL, never to re-decide
// what the link is.
$__seStored   = (string) ($__seRow['source_platform'] ?? '');
$__seDetected = SourceLink::detect($__seUrl);
$__sePlatform = $__seStored !== '' ? $__seStored : $__seDetected['platform'];
$__seEmbed    = $__seDetected['embed'];

// Shown on the card so a resident can see where the button goes before they
// press it — a bare "view the original" says nothing about where it leads.
$__seHost = strtolower((string) (parse_url($__seUrl, PHP_URL_HOST) ?: ''));
$__seHost = preg_replace('/^www\./', '', $__seHost) ?? $__seHost;

$__seIcon = match ($__sePlatform) {
    SourceLink::FACEBOOK => 'bi-facebook',
    SourceLink::YOUTUBE  => 'bi-youtube',
    SourceLink::DRIVE,
    SourceLink::DOCS     => 'bi-google',
    default              => 'bi-link-45deg',
};
?>

<div class="source-embed">
    <p class="source-embed-head">
        <i class="bi <?= e($__seIcon) ?>" aria-hidden="true"></i>
        <span><?= e(t('source.from', ['platform' => t('source.platform_' . $__sePlatform)])) ?></span>
    </p>

    <?php if ($__seEmbed !== null): ?>
    <div class="source-embed-frame<?= $__sePlatform === SourceLink::YOUTUBE ? ' is-video' : '' ?>">
        <?php /*
             * sandbox without allow-same-origin would break every one of these
             * players, so the grant is deliberate and narrow: scripts and
             * popups for the player's own controls, and nothing else. No
             * allow-forms, no allow-top-navigation — an embedded post must not
             * be able to move the resident off the barangay's page.
             *
             * loading="lazy" keeps a post further down the page from making
             * every reader fetch Facebook before they have scrolled to it.
             */ ?>
        <iframe src="<?= e($__seEmbed) ?>"
                title="<?= e(t('source.embed_title', ['platform' => t('source.platform_' . $__sePlatform)])) ?>"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox allow-presentation"
                allow="encrypted-media; picture-in-picture"
                allowfullscreen></iframe>
    </div>
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

    <?php if ($__seEmbed !== null): ?>
    <p class="source-embed-fallback"><?= e(t('source.embed_fallback')) ?></p>
    <?php endif; ?>
</div>
