<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Recognises where a pasted link comes from, and how to show it.
 *
 * Two jobs that have to agree with each other: deciding what a URL is, and
 * building the official embed for it. Keeping them in one place means the
 * badge on the admin form and the player on the resident page can never
 * disagree about what a link is.
 *
 * Only official embed endpoints are used — the YouTube/Vimeo/Dailymotion
 * players, TikTok's and Instagram's embed pages, X's Tweet embed, Google
 * Drive's /preview, and the Facebook SDK. Nothing here scrapes: for Facebook
 * in particular, fetching a post server-side returns a login wall rather than
 * the post (see LinkImporter), and the SDK is the supported way to show one.
 * It renders the post exactly as Facebook lays it out — photos, caption,
 * video player — which is the point: residents see the post as it was
 * published, not our retelling of it.
 *
 * Every embed URL is built from an identifier this class extracted itself —
 * never by interpolating the pasted string into an iframe src. That is the
 * difference between embedding a video and letting someone choose what our
 * page loads in a frame. The two exceptions are deliberate and narrow: a
 * Facebook post address (the SDK takes the address itself, and it is only
 * accepted once it has been checked to be a facebook.com post), and a direct
 * video/image file, which is shown with <video>/<img> — never in a frame.
 */
final class SourceLink
{
    public const FACEBOOK    = 'facebook';
    public const YOUTUBE     = 'youtube';
    public const TIKTOK      = 'tiktok';
    public const INSTAGRAM   = 'instagram';
    public const TWITTER     = 'x';
    public const THREADS     = 'threads';
    public const VIMEO       = 'vimeo';
    public const DAILYMOTION = 'dailymotion';
    public const DRIVE       = 'drive';
    public const DOCS        = 'docs';
    public const VIDEO       = 'video';   // a direct .mp4/.webm file
    public const IMAGE       = 'image';   // a direct .jpg/.png file
    public const OTHER       = 'other';

    /** How the resident page draws each kind of embed. */
    public const RENDER_IFRAME   = 'iframe';
    public const RENDER_FACEBOOK = 'facebook';
    public const RENDER_VIDEO    = 'video';
    public const RENDER_IMAGE    = 'image';

    /**
     * What is this link?
     *
     * @return array{
     *     platform:string, url:string, id:string|null,
     *     embed:string|null, embeddable:bool, label:string,
     *     render:string|null, shape:string, fb_type:string|null, href:string|null
     * }  `embed` is null when there is no official embed — the view then shows
     *    a "view original" card, which is always better than a dead frame.
     *    `shape` sizes the frame: landscape (16:9), portrait (9:16), post (a
     *    social card that sizes itself), doc.
     */
    public static function detect(string $url): array
    {
        $url = trim($url);

        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return self::spec(self::OTHER, $url, null, null);
        }

        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        $host = preg_replace('/^(www|m|web|mobile|vm|vt)\./', '', $host) ?? $host;
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');

        // ── YouTube ──────────────────────────────────────────────────────
        if ($host === 'youtube.com' || $host === 'youtu.be' || $host === 'youtube-nocookie.com') {
            $id = self::youtubeId($url, $host);

            return self::spec(
                self::YOUTUBE,
                $url,
                $id,
                // nocookie: an embedded barangay notice should not hand every
                // resident a tracking cookie they did not ask for.
                $id !== null ? 'https://www.youtube-nocookie.com/embed/' . rawurlencode($id) . '?rel=0&playsinline=1' : null,
                str_contains($path, '/shorts/') ? 'portrait' : 'landscape'
            );
        }

        // ── TikTok ───────────────────────────────────────────────────────
        if ($host === 'tiktok.com') {
            $id = preg_match('#/(?:video|photo|v|embed/v2|embed)/(\d{8,})#', $path, $m) ? $m[1] : null;

            // embed/v2 is TikTok's own post card: the video with its caption
            // and author underneath, the way it looks in the app.
            return self::spec(
                self::TIKTOK,
                $url,
                $id,
                $id !== null ? 'https://www.tiktok.com/embed/v2/' . $id : null,
                'tiktok'
            );
        }

        // ── Instagram ────────────────────────────────────────────────────
        if ($host === 'instagram.com' || $host === 'instagr.am') {
            $id   = null;
            $kind = 'p';
            if (preg_match('#^/(?:[A-Za-z0-9._]+/)?(p|reel|reels|tv)/([A-Za-z0-9_-]{5,})#', $path, $m)) {
                $kind = $m[1] === 'reels' ? 'reel' : $m[1];
                $id   = self::cleanId($m[2]);
            }

            return self::spec(
                self::INSTAGRAM,
                $url,
                $id,
                $id !== null ? 'https://www.instagram.com/' . $kind . '/' . rawurlencode($id) . '/embed/captioned/' : null,
                'post'
            );
        }

        // ── X / Twitter ──────────────────────────────────────────────────
        if (in_array($host, ['x.com', 'twitter.com', 'mobile.twitter.com'], true)) {
            $id = preg_match('#/status(?:es)?/(\d+)#', $path, $m) ? $m[1] : null;

            return self::spec(
                self::TWITTER,
                $url,
                $id,
                $id !== null ? 'https://platform.twitter.com/embed/Tweet.html?dnt=true&id=' . $id : null,
                'post'
            );
        }

        // ── Threads ──────────────────────────────────────────────────────
        if ($host === 'threads.net' || $host === 'threads.com') {
            $id   = null;
            $user = null;
            if (preg_match('#^/@([A-Za-z0-9._]{1,40})/post/([A-Za-z0-9_-]{5,})#', $path, $m)) {
                $user = $m[1];
                $id   = self::cleanId($m[2]);
            }

            return self::spec(
                self::THREADS,
                $url,
                $id,
                $id !== null ? 'https://www.threads.net/@' . rawurlencode((string) $user) . '/post/' . rawurlencode($id) . '/embed' : null,
                'post'
            );
        }

        // ── Vimeo ────────────────────────────────────────────────────────
        if ($host === 'vimeo.com' || $host === 'player.vimeo.com') {
            $id   = null;
            $hash = null;
            if (preg_match('#^/(?:video/|channels/[^/]+/|groups/[^/]+/videos/)?(\d{5,})(?:/([A-Za-z0-9]{6,}))?#', $path, $m)) {
                $id   = $m[1];
                $hash = $m[2] ?? null;
            }
            parse_str((string) (parse_url($url, PHP_URL_QUERY) ?: ''), $query);
            if ($hash === null && !empty($query['h']) && is_string($query['h'])) {
                $hash = self::cleanId($query['h']);
            }

            return self::spec(
                self::VIMEO,
                $url,
                $id,
                $id !== null
                    ? 'https://player.vimeo.com/video/' . $id . '?dnt=1' . ($hash !== null ? '&h=' . rawurlencode($hash) : '')
                    : null
            );
        }

        // ── Dailymotion ──────────────────────────────────────────────────
        if ($host === 'dailymotion.com' || $host === 'dai.ly') {
            $id = $host === 'dai.ly'
                ? self::cleanId(trim($path, '/'))
                : (preg_match('#/video/([A-Za-z0-9]+)#', $path, $m) ? $m[1] : null);

            return self::spec(
                self::DAILYMOTION,
                $url,
                $id,
                $id !== null ? 'https://geo.dailymotion.com/player.html?video=' . rawurlencode($id) : null
            );
        }

        // ── Google Drive ─────────────────────────────────────────────────
        if ($host === 'drive.google.com') {
            $id = self::driveId($url);

            // /preview plays a video file and pages through a PDF alike.
            return self::spec(
                self::DRIVE,
                $url,
                $id,
                $id !== null ? 'https://drive.google.com/file/d/' . rawurlencode($id) . '/preview' : null,
                'doc'
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
                    : null,
                'doc'
            );
        }

        // ── Facebook ─────────────────────────────────────────────────────
        if ($host === 'facebook.com' || $host === 'fb.com' || $host === 'fb.watch') {
            /*
             * The SDK takes the post URL itself rather than an id, and renders
             * it client-side from Facebook's own servers. It is the only route
             * to a real post without an app token — and it only works for a
             * PUBLIC post, which is worth saying on screen rather than leaving
             * as an empty box.
             */
            $spec = self::spec(self::FACEBOOK, $url, null, self::facebookEmbed($url), 'post');
            if ($spec['embeddable']) {
                $spec['render']  = self::RENDER_FACEBOOK;
                $spec['fb_type'] = self::isFacebookVideo($url) ? 'video' : 'post';
                $spec['href']    = self::stripShareTracking($url);
            }

            return $spec;
        }

        // ── A file, linked directly ──────────────────────────────────────
        if (preg_match('#\.(mp4|webm|ogv|ogg|mov|m4v)$#i', $path)) {
            $spec           = self::spec(self::VIDEO, $url, null, $url);
            $spec['render'] = self::RENDER_VIDEO;
            return $spec;
        }
        if (preg_match('#\.(jpe?g|png|gif|webp)$#i', $path)) {
            $spec           = self::spec(self::IMAGE, $url, null, $url, 'doc');
            $spec['render'] = self::RENDER_IMAGE;
            return $spec;
        }

        return self::spec(self::OTHER, $url, null, null);
    }

    /** Platforms whose embeds this app knows how to render. */
    public static function isKnown(string $platform): bool
    {
        return $platform !== self::OTHER && $platform !== '';
    }

    // ── What staff paste ─────────────────────────────────────────────────────

    /**
     * Pull the post address out of whatever staff pasted.
     *
     * Usually that is a plain link. But every platform's "Embed" menu hands
     * out a block of HTML instead — and for Facebook that menu is the most
     * reliable way to get a link the SDK can render, because the share
     * button only gives out redirect stubs. So a pasted <iframe>/<blockquote>
     * is read for its src/cite/data-href, and a Facebook plugin address
     * (plugins/post.php?href=…) is unwrapped to the post it names.
     *
     * The HTML itself is never stored or rendered: only one address comes out,
     * and it goes through detect() like any other.
     */
    public static function extractUrl(string $input): string
    {
        $input = trim($input);
        if ($input === '') {
            return '';
        }

        $candidates = [];
        if (str_contains($input, '<')) {
            if (preg_match_all('#\b(?:data-href|cite|src|href)\s*=\s*["\']([^"\']+)["\']#i', $input, $m)) {
                foreach ($m[1] as $found) {
                    $candidates[] = html_entity_decode($found, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }
        } else {
            // A link copied out of a chat app often trails a sentence; keep
            // only the first thing that looks like an address.
            $candidates[] = preg_match('#https?://\S+#i', $input, $m) ? $m[0] : $input;
        }

        $first = '';
        foreach ($candidates as $candidate) {
            $candidate = self::unwrapPlugin(trim($candidate));
            if (!preg_match('#^https?://#i', $candidate)) {
                continue;
            }
            if ($first === '') {
                $first = $candidate;
            }
            // Embed snippets carry several links (the author's profile, a
            // hashtag, the embed script). The post itself is the one we can
            // actually embed.
            if (self::detect($candidate)['embeddable']) {
                return mb_substr($candidate, 0, 500);
            }
        }

        return mb_substr($first, 0, 500);
    }

    /** facebook.com/plugins/post.php?href=<post> → <post>. Anything else unchanged. */
    private static function unwrapPlugin(string $url): string
    {
        if (self::facebookHost($url) === null
            || !preg_match('#^/plugins/(?:post|video)\.php$#i', (string) (parse_url($url, PHP_URL_PATH) ?: ''))) {
            return $url;
        }

        parse_str((string) (parse_url($url, PHP_URL_QUERY) ?: ''), $query);

        return isset($query['href']) && is_string($query['href']) ? trim($query['href']) : $url;
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
     *
     * The resident page draws posts through the SDK (it sizes itself to the
     * post); this plugin address is the frame it falls back to.
     */
    public static function facebookEmbed(string $url): ?string
    {
        if (!self::isCanonicalFacebookPost($url)) {
            return null;
        }

        $plugin = self::isFacebookVideo($url) ? 'video.php' : 'post.php';

        return 'https://www.facebook.com/plugins/' . $plugin . '?href='
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

        // /<page>/posts/<id>, /<page>/videos/<id>, /reel/<id>, /groups/<g>/posts/<id>,
        // /groups/<g>/permalink/<id>
        if (preg_match('#/(?:posts|videos|photos|reel|reels|permalink)/[^/]+#i', $path) === 1) {
            return true;
        }

        parse_str((string) (parse_url($url, PHP_URL_QUERY) ?: ''), $query);

        // The older query forms, which carry the id in the query string.
        if (preg_match('#/(?:permalink|story|photo|video)\.php$#i', $path) === 1) {
            return isset($query['story_fbid']) || isset($query['fbid']) || isset($query['v']);
        }

        // /photo/?fbid=… — what "Copy link" gives for a single photo.
        if (preg_match('#^/photo/?$#i', $path) === 1) {
            return isset($query['fbid']);
        }

        return preg_match('#^/watch/?$#i', $path) === 1 && isset($query['v']);
    }

    /**
     * Should this post get the video player rather than the post card?
     *
     * The video plugin plays inline at full width; the post plugin shows a
     * video as a thumbnail inside the card. Both work — this only picks the
     * one that looks like the post.
     */
    public static function isFacebookVideo(string $url): bool
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');

        return preg_match('#/(?:videos|reel|reels)/|^/watch/?$|/video\.php$#i', $path) === 1;
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
     * TikTok's short links — vm.tiktok.com/XXXX, vt.tiktok.com/XXXX,
     * tiktok.com/t/XXXX — which the app's share button gives out. Like
     * Facebook's stubs, they carry no video id until followed.
     */
    public static function isTikTokShortLink(string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');

        return in_array($host, ['vm.tiktok.com', 'vt.tiktok.com'], true)
            || (in_array($host, ['tiktok.com', 'www.tiktok.com'], true) && preg_match('#^/t/#', $path) === 1);
    }

    /**
     * Follow a share stub to the address it points at.
     *
     * Returns the canonical post URL, or the original string unchanged when
     * the link is not a stub, cannot be reached, or lands somewhere that is
     * not a post. Never throws and never returns something unusable:
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

        $isFacebook = self::isFacebookShareLink($url);
        if (!$isFacebook && !self::isTikTokShortLink($url)) {
            return $url;
        }

        try {
            $resolved = (new LinkImporter())->resolveRedirect($url);
        } catch (\Throwable $e) {
            error_log('[SourceLink::canonicalise] ' . $e->getMessage());
            return $url;
        }

        if ($resolved === null) {
            return $url;
        }

        // Anything other than a post at the end of the chain — a login page,
        // an error, a redirect off the site — means the stub is the best
        // address we have. Keep it.
        if ($isFacebook) {
            return self::isCanonicalFacebookPost($resolved)
                ? mb_substr(self::stripShareTracking($resolved), 0, 500)
                : $url;
        }

        $tiktok = self::detect($resolved);

        return $tiktok['platform'] === self::TIKTOK && $tiktok['embeddable']
            ? mb_substr(strtok($resolved, '?') ?: $resolved, 0, 500)
            : $url;
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

    // ── Preview text and picture ─────────────────────────────────────────────

    /**
     * A caption and thumbnail for a link, where the platform will give one.
     *
     * Used two ways: to fill in the title and text of a post that is nothing
     * but a link, and to draw a proper preview card for a website that has no
     * embed at all. Sources, best first:
     *
     *   oEmbed  — YouTube, TikTok, Vimeo, X, Dailymotion publish keyless JSON
     *             endpoints for exactly this.
     *   og:tags — any ordinary website.
     *
     * Facebook, Instagram and Threads offer neither without an app token, so
     * they get nothing here — their embed carries the caption itself.
     *
     * Never throws. Best effort: an empty array is a normal answer.
     *
     * @return array<string,string> title, description, image, site, author
     */
    public static function fetchMeta(string $url): array
    {
        $spec = self::detect($url);

        $oembed = match ($spec['platform']) {
            self::YOUTUBE     => 'https://www.youtube.com/oembed?format=json&url=',
            self::TIKTOK      => 'https://www.tiktok.com/oembed?url=',
            self::VIMEO       => 'https://vimeo.com/api/oembed.json?url=',
            self::TWITTER     => 'https://publish.twitter.com/oembed?omit_script=1&dnt=true&url=',
            self::DAILYMOTION => 'https://www.dailymotion.com/services/oembed?format=json&url=',
            default           => null,
        };

        try {
            $importer = new LinkImporter();
            $meta     = [];

            if ($oembed !== null && $spec['embeddable']) {
                $data = $importer->fetchJson($oembed . rawurlencode($url));
                if ($data !== null) {
                    $meta = [
                        'title'  => (string) ($data['title'] ?? ''),
                        'image'  => (string) ($data['thumbnail_url'] ?? ''),
                        'author' => (string) ($data['author_name'] ?? ''),
                        'site'   => (string) ($data['provider_name'] ?? ''),
                    ];
                    // X sends the post text only inside its embed HTML.
                    if ($spec['platform'] === self::TWITTER && preg_match('#<p[^>]*>(.*?)</p>#s', (string) ($data['html'] ?? ''), $m)) {
                        $meta['description'] = $m[1];
                    }
                }
            } elseif (in_array($spec['platform'], [self::OTHER, self::DOCS], true)) {
                $page = $importer->fetchPage($url);
                if ($page['ok']) {
                    $meta = $page['meta'];
                }
            }
        } catch (\Throwable $e) {
            error_log('[SourceLink::fetchMeta] ' . $e->getMessage());
            return [];
        }

        $clean = [];
        foreach (['title' => 300, 'description' => 1500, 'image' => 500, 'site' => 80, 'author' => 120] as $key => $max) {
            $value = trim(strip_tags(html_entity_decode((string) ($meta[$key] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            // A picture address ends up in an <img src>; only web addresses.
            if ($key === 'image' && !preg_match('#^https://#i', $value)) {
                $value = '';
            }
            if ($value !== '') {
                $clean[$key] = mb_substr($value, 0, $max);
            }
        }

        return $clean;
    }

    // ── Saving ───────────────────────────────────────────────────────────────

    /**
     * Turn what staff pasted into what gets stored: the address, the platform
     * and a preview, settled once, here — not on every resident's page load.
     *
     * When the address is the one already stored on the post, the stored
     * platform and preview are reused rather than fetched again, so saving an
     * edit to the title does not wait on Facebook or TikTok.
     *
     * Never throws.
     *
     * @param array<string,mixed>|null $existing The row being edited, if any.
     * @return array{url:string, platform:string|null, meta:array<string,string>}
     *         url '' means "no source".
     */
    public static function prepare(string $input, ?array $existing = null): array
    {
        $url = self::extractUrl($input);

        // Only http/https is ever stored: this value ends up in an href, and a
        // javascript: URL pasted here would be a stored XSS in one field.
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return ['url' => '', 'platform' => null, 'meta' => []];
        }

        if ($existing !== null && trim((string) ($existing['source_url'] ?? '')) === $url) {
            return [
                'url'      => $url,
                'platform' => self::detect($url)['platform'],
                'meta'     => self::decodeMeta($existing['source_meta'] ?? null),
            ];
        }

        // A share stub saved as-is is the reason the embed below the post read
        // "no longer available"; follow it while staff are here to wait for it.
        $url = mb_substr(self::canonicalise($url), 0, 500);

        return [
            'url'      => $url,
            'platform' => self::detect($url)['platform'],
            'meta'     => self::fetchMeta($url),
        ];
    }

    /**
     * Record where a post came from, or clear it when the field is emptied.
     *
     * Never throws: attribution failing must not cost a staff member the post
     * they just wrote. A missing column (migration not yet applied) simply
     * means no source is stored.
     *
     * @param string                                                        $contentType 'announcement' | 'event' | 'ordinance'
     * @param array{url:string, platform:string|null, meta:array<string,string>}|string $source
     *        A prepare() result, or the raw pasted text.
     */
    public static function store(string $contentType, int $id, array|string $source): void
    {
        $table = self::tableFor($contentType);
        if ($table === null || $id <= 0) {
            return;
        }

        if (is_string($source)) {
            $source = self::prepare($source);
        }

        $url  = $source['url'];
        $meta = $source['meta'] !== [] ? json_encode($source['meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        try {
            db()->prepare("UPDATE {$table} SET source_url = ?, source_platform = ?, source_meta = ? WHERE id = ?")
                ->execute([$url !== '' ? $url : null, $url !== '' ? $source['platform'] : null, $url !== '' ? $meta : null, $id]);
        } catch (\Throwable $e) {
            // An older database without source_meta still keeps the link.
            try {
                db()->prepare("UPDATE {$table} SET source_url = ?, source_platform = ? WHERE id = ?")
                    ->execute([$url !== '' ? $url : null, $url !== '' ? $source['platform'] : null, $id]);
            } catch (\Throwable $e2) {
                error_log('[SourceLink::store] ' . $contentType . ' #' . $id . ': ' . $e2->getMessage());
            }
        }
    }

    /**
     * Text to stand in for an empty body when a post is only a link.
     *
     * The caption the platform gave us, when it gave one; otherwise a line
     * pointing at the original below. Either way the body is never blank —
     * search, notifications, SMS and the voice reader all read it.
     *
     * @param array{url:string, platform:string|null, meta:array<string,string>} $source
     */
    public static function fallbackBody(array $source, string $lang = 'fil'): string
    {
        $text = $source['meta']['description'] ?? $source['meta']['title'] ?? '';

        if ($text === '') {
            $text = t('source.see_original_below', [
                'platform' => t('source.platform_' . ($source['platform'] ?? self::OTHER), [], $lang),
            ], $lang);
        }

        return $text;
    }

    /**
     * Fill in what staff left empty on a post that is just a link.
     *
     * Staff should be able to copy a Facebook or TikTok link, give it a title
     * and publish — the embed IS the content. This supplies a body (and a
     * title, where the platform offered one) so the rest of the system, which
     * expects both, keeps working. Anything staff did type is left untouched.
     *
     * @param array{url:string, platform:string|null, meta:array<string,string>} $source
     * @return array{0:string, 1:string} [title, body]
     */
    public static function fillLinkOnly(array $source, string $title, string $body, bool $asHtml, string $lang = 'fil'): array
    {
        if ($source['url'] === '') {
            return [$title, $body];
        }

        if ($title === '') {
            $title = self::fallbackTitle($source);
        }

        $plain = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_replace('/[\s\x{00A0}]+/u', '', $plain) === '') {
            $text = self::fallbackBody($source, $lang);
            $body = $asHtml ? SocialText::toHtml($text) : $text;
        }

        return [$title, $body];
    }

    /**
     * A headline for a post that is only a link, or '' when the platform did
     * not offer one.
     *
     * @param array{url:string, platform:string|null, meta:array<string,string>} $source
     */
    public static function fallbackTitle(array $source): string
    {
        $title = $source['meta']['title'] ?? '';
        if ($title === '' && !empty($source['meta']['author'])) {
            $title = $source['meta']['author'];
        }

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $title) ?? ''), 0, 200);
    }

    /** @return array<string,string> */
    public static function decodeMeta(mixed $raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }

        return array_filter(
            array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', $data),
            static fn (string $v): bool => $v !== ''
        );
    }

    private static function tableFor(string $contentType): ?string
    {
        return match ($contentType) {
            'announcement' => 'announcements',
            'event'        => 'events',
            'ordinance'    => 'ordinances',
            default        => null,
        };
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
     * @return array{platform:string, url:string, id:string|null, embed:string|null, embeddable:bool, label:string, render:string|null, shape:string, fb_type:string|null, href:string|null}
     */
    private static function spec(string $platform, string $url, ?string $id, ?string $embed, string $shape = 'landscape'): array
    {
        return [
            'platform'   => $platform,
            'url'        => $url,
            'id'         => $id,
            'embed'      => $embed,
            'embeddable' => $embed !== null,
            'label'      => t('source.platform_' . $platform),
            'render'     => $embed !== null ? self::RENDER_IFRAME : null,
            'shape'      => $shape,
            'fb_type'    => null,
            'href'       => null,
        ];
    }
}
