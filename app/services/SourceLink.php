<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Recognises where a pasted link comes from, and how to show it.
 *
 * Two jobs that have to agree with each other: deciding what a URL is, and
 * building the official embed for it. Keeping them in one place means the
 * badge on the admin form and the iframe on the resident page can never
 * disagree about what a link is.
 *
 * Only official embed endpoints are used — the YouTube player, Google Drive's
 * /preview, the Facebook Social Plugin. Nothing here scrapes: for Facebook in
 * particular, fetching a post server-side returns a login wall rather than the
 * post (see LinkImporter), and the plugin is the supported way to show one.
 *
 * Every embed URL is built from an identifier this class extracted itself —
 * never by interpolating the pasted string into an iframe src. That is the
 * difference between embedding a video and letting someone choose what our
 * page loads in a frame.
 */
final class SourceLink
{
    public const FACEBOOK = 'facebook';
    public const YOUTUBE  = 'youtube';
    public const DRIVE    = 'drive';
    public const DOCS     = 'docs';
    public const OTHER    = 'other';

    /**
     * What is this link?
     *
     * @return array{
     *     platform:string, url:string, id:string|null,
     *     embed:string|null, embeddable:bool, label:string
     * }  `embed` is null when there is no official embed — the view then shows
     *    a plain "view original" link, which is always better than a dead frame.
     */
    public static function detect(string $url): array
    {
        $url = trim($url);

        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return self::spec(self::OTHER, $url, null, null);
        }

        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        $host = preg_replace('/^(www|m|web|mobile)\./', '', $host) ?? $host;

        // ── YouTube ──────────────────────────────────────────────────────
        if ($host === 'youtube.com' || $host === 'youtu.be' || $host === 'youtube-nocookie.com') {
            $id = self::youtubeId($url, $host);

            return self::spec(
                self::YOUTUBE,
                $url,
                $id,
                // nocookie: an embedded barangay notice should not hand every
                // resident a tracking cookie they did not ask for.
                $id !== null ? 'https://www.youtube-nocookie.com/embed/' . rawurlencode($id) : null
            );
        }

        // ── Google Drive ─────────────────────────────────────────────────
        if ($host === 'drive.google.com') {
            $id = self::driveId($url);

            return self::spec(
                self::DRIVE,
                $url,
                $id,
                $id !== null ? 'https://drive.google.com/file/d/' . rawurlencode($id) . '/preview' : null
            );
        }

        // ── Google Docs / Sheets / Slides ────────────────────────────────
        if ($host === 'docs.google.com') {
            // A published-to-web document is ordinary HTML and imports through
            // Tier 1; the embed is only for the document viewer form.
            $id = self::docsId($url);

            return self::spec(
                self::DOCS,
                $url,
                $id,
                $id !== null && preg_match('#/(document|presentation|spreadsheets)/#', $url, $m)
                    ? 'https://docs.google.com/' . $m[1] . '/d/' . rawurlencode($id) . '/preview'
                    : null
            );
        }

        // ── Facebook ─────────────────────────────────────────────────────
        if ($host === 'facebook.com' || $host === 'fb.com' || $host === 'fb.watch') {
            /*
             * The Social Plugin takes the post URL itself rather than an id,
             * and renders it client-side from Facebook's own servers. It is
             * the only route to a real post without an app token — and it
             * only works for a PUBLIC post, which is worth saying on screen
             * rather than leaving as an empty box.
             */
            return self::spec(self::FACEBOOK, $url, null, self::facebookEmbed($url));
        }

        return self::spec(self::OTHER, $url, null, null);
    }

    /** Platforms whose embeds this app knows how to render. */
    public static function isKnown(string $platform): bool
    {
        return in_array($platform, [self::FACEBOOK, self::YOUTUBE, self::DRIVE, self::DOCS], true);
    }

    // ── Facebook ─────────────────────────────────────────────────────────────

    /**
     * The Social Plugin address for a post, or null when the plugin cannot
     * render this particular link.
     *
     * The gate matters. Given facebook.com/share/p/XXXX the plugin returns
     * "This Facebook post is no longer available. It may have been removed or
     * the privacy settings of the post may have changed." — for a post that is
     * public and entirely fine. A share link is a redirect stub with no post
     * id in it, and the plugin will not follow it. Handed the address that
     * stub points at, the very same post renders.
     *
     * So an embed is offered only for a link the plugin actually understands.
     * Everything else falls through to the card in _source-embed.php, which
     * opens the post in a new tab — a working button beats a box that says the
     * post is gone when it is not.
     */
    public static function facebookEmbed(string $url): ?string
    {
        if (!self::isCanonicalFacebookPost($url)) {
            return null;
        }

        return 'https://www.facebook.com/plugins/post.php?href='
            . rawurlencode(self::stripShareTracking($url))
            . '&show_text=true&width=500';
    }

    /**
     * Is this a post address the Social Plugin can render — one that names the
     * post rather than pointing at it?
     */
    public static function isCanonicalFacebookPost(string $url): bool
    {
        if (self::facebookHost($url) === null) {
            return false;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');

        // /<page>/posts/<id>, /<page>/videos/<id>, /reel/<id>, /groups/<g>/posts/<id>
        if (preg_match('#/(?:posts|videos|photos|reel|reels)/[^/]+#i', $path) === 1) {
            return true;
        }

        parse_str((string) (parse_url($url, PHP_URL_QUERY) ?: ''), $query);

        // The older query forms, which carry the id in the query string.
        if (preg_match('#/(?:permalink|story|photo|video)\.php$#i', $path) === 1) {
            return isset($query['story_fbid']) || isset($query['fbid']) || isset($query['v']);
        }

        return preg_match('#^/watch/?$#i', $path) === 1 && isset($query['v']);
    }

    /**
     * Is this one of the redirect stubs Facebook's share button produces?
     *
     * These are what staff actually paste — the share sheet in the Facebook
     * app gives out nothing else — so they are the normal case, not the edge
     * case.
     */
    public static function isFacebookShareLink(string $url): bool
    {
        $host = self::facebookHost($url);
        if ($host === null) {
            return false;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');

        // fb.watch and fb.com exist only to redirect; on facebook.com itself
        // the share sheet writes /share/p/, /share/v/ and /share/r/.
        return $host === 'fb.watch'
            || $host === 'fb.com'
            || preg_match('#^/share/#i', $path) === 1;
    }

    /**
     * Follow a share stub to the address it points at.
     *
     * Returns the canonical post URL, or the original string unchanged when
     * the link is not a stub, cannot be reached, or lands somewhere that is
     * not a Facebook post. Never throws and never returns something unusable:
     * attribution must not be able to cost a staff member their post.
     *
     * The request goes through LinkImporter, so it gets the same address
     * checks as every other outbound fetch — scheme allowlist, every resolved
     * address checked, the connection pinned to the address that passed. Only
     * the final address is kept; the page itself is discarded unread.
     */
    public static function canonicalise(string $url): string
    {
        $url = trim($url);

        if (!self::isFacebookShareLink($url)) {
            return $url;
        }

        try {
            $resolved = (new LinkImporter())->resolveRedirect($url);
        } catch (\Throwable $e) {
            error_log('[SourceLink::canonicalise] ' . $e->getMessage());
            return $url;
        }

        // Anything other than a Facebook post at the end of the chain — a
        // login page, an error, a redirect off the site — means the stub is
        // the best address we have. Keep it.
        if ($resolved === null || !self::isCanonicalFacebookPost($resolved)) {
            return $url;
        }

        return mb_substr(self::stripShareTracking($resolved), 0, 500);
    }

    /**
     * Drop the analytics parameters Facebook staples onto a resolved address.
     *
     * `rdid` and `share_url` identify the share that was clicked, not the
     * post. They would be stored, shown, and handed back to Facebook on every
     * resident's page load, and they make the stored link needlessly long.
     */
    public static function stripShareTracking(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['query'])) {
            return $url;
        }

        parse_str($parts['query'], $query);
        foreach (['rdid', 'share_url', 'mibextid', 'fbclid'] as $noise) {
            unset($query[$noise]);
        }

        $rebuilt = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '')
            . ($parts['path'] ?? '');
        $tail = http_build_query($query);

        return $tail === '' ? $rebuilt : $rebuilt . '?' . $tail;
    }

    /** The Facebook host this URL names, normalised, or null if it is not one. */
    private static function facebookHost(string $url): ?string
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        $host = preg_replace('/^(www|m|web|mobile)\./', '', $host) ?? $host;

        return in_array($host, ['facebook.com', 'fb.com', 'fb.watch'], true) ? $host : null;
    }

    /**
     * Record where a post came from, or clear it when the field is emptied.
     *
     * The platform is settled here, once, rather than re-derived by every page
     * that shows the post — otherwise the admin list and the detail page can
     * disagree about what a link is.
     *
     * Never throws: attribution failing must not cost a staff member the post
     * they just wrote. A missing column (migration 022 not yet applied) simply
     * means no source is stored.
     *
     * @param string $contentType 'announcement' | 'event' | 'ordinance'
     */
    public static function store(string $contentType, int $id, string $url): void
    {
        $table = match ($contentType) {
            'announcement' => 'announcements',
            'event'        => 'events',
            'ordinance'    => 'ordinances',
            default        => null,
        };

        if ($table === null || $id <= 0) {
            return;
        }

        $url = trim($url);

        // Only http/https is ever stored: this value ends up in an href, and a
        // javascript: URL pasted here would be a stored XSS in one field.
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            return;
        }

        // Resolved once, here, at the moment staff attach the link — not on
        // every resident's page load. A share stub saved as-is is the reason
        // the embed below the post read "no longer available".
        if ($url !== '') {
            $url = self::canonicalise($url);
        }

        $platform = $url === '' ? null : self::detect($url)['platform'];

        try {
            db()->prepare("UPDATE {$table} SET source_url = ?, source_platform = ? WHERE id = ?")
                ->execute([$url !== '' ? mb_substr($url, 0, 500) : null, $platform, $id]);
        } catch (\Throwable $e) {
            error_log('[SourceLink::store] ' . $contentType . ' #' . $id . ': ' . $e->getMessage());
        }
    }

    /**
     * A Drive share link turned into something downloadable.
     *
     * Used by the ordinance form, where "import from Google" really means
     * "fetch this PDF and store it through the normal upload path". Only works
     * for a file shared as "anyone with the link"; Drive answers a private one
     * with an HTML sign-in page, which LinkImporter then rejects on content
     * type rather than saving as a corrupt PDF.
     */
    public static function driveDownloadUrl(string $url): ?string
    {
        $id = self::driveId($url);

        return $id === null ? null : 'https://drive.google.com/uc?export=download&id=' . rawurlencode($id);
    }

    // ── Identifier extraction ────────────────────────────────────────────────

    private static function youtubeId(string $url, string $host): ?string
    {
        if ($host === 'youtu.be') {
            $path = trim((string) (parse_url($url, PHP_URL_PATH) ?: ''), '/');
            return self::cleanId($path);
        }

        parse_str((string) (parse_url($url, PHP_URL_QUERY) ?: ''), $query);
        if (!empty($query['v']) && is_string($query['v'])) {
            return self::cleanId($query['v']);
        }

        // /embed/ID, /shorts/ID, /live/ID
        if (preg_match('#/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{6,})#', $url, $m)) {
            return self::cleanId($m[1]);
        }

        return null;
    }

    private static function driveId(string $url): ?string
    {
        if (preg_match('#/file/d/([A-Za-z0-9_-]{10,})#', $url, $m)) {
            return self::cleanId($m[1]);
        }

        parse_str((string) (parse_url($url, PHP_URL_QUERY) ?: ''), $query);
        if (!empty($query['id']) && is_string($query['id'])) {
            return self::cleanId($query['id']);
        }

        return null;
    }

    private static function docsId(string $url): ?string
    {
        return preg_match('#/d/(?:e/)?([A-Za-z0-9_-]{10,})#', $url, $m) ? self::cleanId($m[1]) : null;
    }

    /**
     * Identifiers are whitelisted to the characters these platforms actually
     * use, so nothing extracted can carry a quote or an angle bracket into an
     * iframe attribute even if the escaping downstream were ever weakened.
     */
    private static function cleanId(string $raw): ?string
    {
        $id = preg_replace('/[^A-Za-z0-9_-]/', '', $raw) ?? '';

        return $id === '' ? null : $id;
    }

    /**
     * @return array{platform:string, url:string, id:string|null, embed:string|null, embeddable:bool, label:string}
     */
    private static function spec(string $platform, string $url, ?string $id, ?string $embed): array
    {
        return [
            'platform'   => $platform,
            'url'        => $url,
            'id'         => $id,
            'embed'      => $embed,
            'embeddable' => $embed !== null,
            'label'      => t('source.platform_' . $platform),
        ];
    }
}
