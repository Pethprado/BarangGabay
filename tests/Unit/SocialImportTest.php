<?php
declare(strict_types=1);

use App\Services\SocialText;
use App\Services\SourceLink;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * The two tiers that need no platform cooperation at all: cleaning up a
 * caption somebody pasted, and recognising where a link points.
 *
 * The paste tier is the one the barangay will actually use most days — no API,
 * no key, nothing Facebook can switch off — so it is tested as a first-class
 * path rather than as a fallback.
 */
final class SocialImportTest extends TestCase
{
    // ── Cleaning a pasted caption ────────────────────────────────────────────

    /** The truncation control gets copied along with the text every time. */
    public function testSeeMoreIsRemoved(): void
    {
        $clean = SocialText::clean("Libreng check-up bukas sa Barangay Hall… See more");

        $this->assertStringNotContainsString('See more', $clean['text']);
        $this->assertStringNotContainsString('…', $clean['text']);
        $this->assertStringContainsString('Libreng check-up bukas', $clean['text']);
        $this->assertContains('see_more', $clean['removed']);
    }

    public function testReactionCountersAreRemoved(): void
    {
        $clean = SocialText::clean("Salamat sa lahat ng dumalo!\n248 likes\n31 comments\n5 shares");

        $this->assertStringNotContainsString('248', $clean['text']);
        $this->assertStringNotContainsString('comments', $clean['text']);
        $this->assertSame('Salamat sa lahat ng dumalo!', $clean['text']);
    }

    /**
     * A real paste has "See more" at the end of a line and the counters on the
     * next one. Removing "See more" must not swallow the newline, or the two
     * lines weld together and the counter rule — which only matches a counter
     * alone on its own line — silently stops seeing them.
     */
    public function testSeeMoreRemovalDoesNotWeldTheNextLineOn(): void
    {
        $clean = SocialText::clean("Magdala po ng ID… See more\n248 likes\nSalamat po.");

        $this->assertStringNotContainsString('248 likes', $clean['text']);
        $this->assertStringNotContainsString('See more', $clean['text']);
        $this->assertSame("Magdala po ng ID\nSalamat po.", $clean['text']);
    }

    /**
     * Hashtags at the end are tagging and come out; hashtags inside a sentence
     * are part of the sentence and stay. That distinction is the difference
     * between tidying somebody's post and rewriting it.
     */
    public function testTrailingHashtagsAreLiftedOutButInlineOnesStay(): void
    {
        $clean = SocialText::clean(
            "Sumali sa #BrigadaEskwela sa Sabado.\n\n#Bayogo #BarangayBayogo #Serbisyo"
        );

        $this->assertStringContainsString('#BrigadaEskwela', $clean['text'], 'Inline hashtag is part of the sentence');
        $this->assertStringNotContainsString('#Bayogo', $clean['text']);
        $this->assertSame(['Bayogo', 'BarangayBayogo', 'Serbisyo'], $clean['tags']);
        $this->assertContains('hashtags', $clean['removed']);
    }

    /** The very common shape: caption and hashtags on one final line. */
    public function testHashtagsAtTheEndOfTheLastLineAreLifted(): void
    {
        $clean = SocialText::clean('Maraming salamat po! #Bayogo #Barangay');

        $this->assertSame('Maraming salamat po!', $clean['text']);
        $this->assertSame(['Bayogo', 'Barangay'], $clean['tags']);
    }

    /** A single trailing hashtag reads as part of the sentence, so it stays. */
    public function testOneTrailingHashtagOnALineWithTextIsNotLifted(): void
    {
        $clean = SocialText::clean('Handa na ang #BayanihanCenter');

        $this->assertStringContainsString('#BayanihanCenter', $clean['text']);
        $this->assertSame([], $clean['tags']);
    }

    public function testBlankLineRunsBecomeParagraphBreaks(): void
    {
        $clean = SocialText::clean("Una.\n\n\n\n\nPangalawa.\n\n\nPangatlo.");

        $this->assertSame("Una.\n\nPangalawa.\n\nPangatlo.", $clean['text']);
    }

    /**
     * Zero-width characters ride along in a paste, are invisible, and break
     * every pattern above if left in.
     */
    public function testInvisibleCharactersAreStripped(): void
    {
        $clean = SocialText::clean("Libre\u{200B}ng check-up\u{FEFF} bukas");

        $this->assertSame('Librena check-up bukas', str_replace('ng', 'na', $clean['text']));
        $this->assertStringNotContainsString("\u{200B}", $clean['text']);
        $this->assertStringNotContainsString("\u{FEFF}", $clean['text']);
    }

    public function testBareUrlsAreFoundAndLinked(): void
    {
        $clean = SocialText::clean('Magparehistro sa https://baranggabay.ph/register bago mag-Lunes.');

        $this->assertSame(['https://baranggabay.ph/register'], $clean['links']);
        $this->assertStringContainsString('<a href="https://baranggabay.ph/register"', $clean['html']);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $clean['html']);
    }

    /**
     * The caption came from a stranger on the internet. It is escaped before a
     * single tag is added, not after.
     */
    public function testPastedMarkupIsEscapedNotRendered(): void
    {
        $clean = SocialText::clean('<script>alert(1)</script> <img src=x onerror=alert(1)>');

        $this->assertStringNotContainsString('<script>', $clean['html']);
        $this->assertStringNotContainsString('<img', $clean['html']);
        $this->assertStringContainsString('&lt;script&gt;', $clean['html']);
    }

    /** A URL containing a quote must not be able to break out of the href. */
    public function testAQuoteInAUrlCannotEscapeTheAttribute(): void
    {
        $clean = SocialText::clean('See https://evil.example/"onmouseover="alert(1) now');

        $this->assertStringNotContainsString('onmouseover="alert', $clean['html']);
        $this->assertStringNotContainsString('"onmouseover', $clean['html']);
    }

    public function testParagraphsSurviveIntoHtml(): void
    {
        $html = SocialText::toHtml("Unang talata.\n\nPangalawang talata.");

        $this->assertSame(2, substr_count($html, '<p>'));
        $this->assertStringContainsString('<p>Unang talata.</p>', $html);
    }

    public function testEmptyInputIsHandled(): void
    {
        $clean = SocialText::clean('   ');

        $this->assertSame('', $clean['text']);
        $this->assertSame('', $clean['html']);
        $this->assertSame([], $clean['tags']);
    }

    // ── Recognising a link ───────────────────────────────────────────────────

    public function testYouTubeLinksInEveryShapeAreRecognised(): void
    {
        $shapes = [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ',
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ',
            'https://m.youtube.com/watch?v=dQw4w9WgXcQ&t=30s',
        ];

        foreach ($shapes as $url) {
            $link = SourceLink::detect($url);

            $this->assertSame(SourceLink::YOUTUBE, $link['platform'], $url);
            $this->assertSame('dQw4w9WgXcQ', $link['id'], $url);
            $this->assertTrue($link['embeddable'], $url);
            // nocookie: an embedded notice should not hand every resident a
            // tracking cookie they did not ask for.
            $this->assertStringContainsString('youtube-nocookie.com/embed/', (string) $link['embed']);
        }
    }

    public function testDriveLinksYieldAPreviewAndADownload(): void
    {
        $url  = 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUv/view?usp=sharing';
        $link = SourceLink::detect($url);

        $this->assertSame(SourceLink::DRIVE, $link['platform']);
        $this->assertSame('1AbCdEfGhIjKlMnOpQrStUv', $link['id']);
        $this->assertStringContainsString('/preview', (string) $link['embed']);

        $this->assertSame(
            'https://drive.google.com/uc?export=download&id=1AbCdEfGhIjKlMnOpQrStUv',
            SourceLink::driveDownloadUrl($url)
        );
    }

    /**
     * Facebook gets the Social Plugin, which takes the post URL itself. It is
     * the only route to a real post without an app token — server-side fetching
     * returns a login wall, which is why LinkImporter refuses it by name.
     */
    public function testFacebookGetsTheSocialPluginEmbed(): void
    {
        $link = SourceLink::detect('https://www.facebook.com/BarangayBayogo/posts/123456789');

        $this->assertSame(SourceLink::FACEBOOK, $link['platform']);
        $this->assertTrue($link['embeddable']);
        $this->assertStringContainsString('facebook.com/plugins/post.php', (string) $link['embed']);
        $this->assertStringContainsString(rawurlencode('https://www.facebook.com/BarangayBayogo/posts/123456789'), (string) $link['embed']);
    }

    public function testAnOrdinaryWebsiteIsAttributedButNotEmbedded(): void
    {
        $link = SourceLink::detect('https://www.pna.gov.ph/articles/1234567');

        $this->assertSame(SourceLink::OTHER, $link['platform']);
        $this->assertFalse($link['embeddable'], 'No official embed exists, so a link is all we can offer');
        $this->assertNull($link['embed']);
    }

    /**
     * Embed URLs are built from an identifier this class extracted itself,
     * never by pasting the input into an iframe src. Anything that is not a
     * platform identifier is stripped out of the id entirely.
     */
    public function testAnEmbedUrlCannotBeSmuggledThroughAnIdentifier(): void
    {
        $link = SourceLink::detect('https://www.youtube.com/watch?v=abc"></iframe><script>alert(1)</script>');

        $this->assertSame(SourceLink::YOUTUBE, $link['platform']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', (string) $link['id']);
        $this->assertStringNotContainsString('<', (string) $link['embed']);
        $this->assertStringNotContainsString('"', (string) $link['embed']);
    }

    public function testNonHttpLinksAreNeverEmbeddable(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', '', 'not a url'] as $value) {
            $link = SourceLink::detect($value);

            $this->assertSame(SourceLink::OTHER, $link['platform'], $value);
            $this->assertFalse($link['embeddable'], $value);
        }
    }

    // ── Facebook share stubs ─────────────────────────────────────────────────

    /**
     * The bug this guards against: a share link was handed straight to the
     * Social Plugin, which answered "This Facebook post is no longer
     * available" for a post that was public and perfectly fine. A stub carries
     * no post id, so the plugin cannot render it — no embed is far better than
     * one that tells residents the notice has been deleted.
     */
    public function testAFacebookShareStubIsNotHandedToTheEmbed(): void
    {
        foreach ([
            'https://www.facebook.com/share/p/18Lsj2jkY4/',
            'https://www.facebook.com/share/v/abcDEF123/',
            'https://fb.watch/xY1z2AbC/',
        ] as $url) {
            $link = SourceLink::detect($url);

            $this->assertSame(SourceLink::FACEBOOK, $link['platform'], $url);
            $this->assertFalse($link['embeddable'], $url);
            $this->assertNull($link['embed'], $url);
            $this->assertTrue(SourceLink::isFacebookShareLink($url), $url);
        }
    }

    /** The forms the plugin does understand still get an embed. */
    public function testCanonicalFacebookPostsAreStillEmbeddable(): void
    {
        foreach ([
            'https://www.facebook.com/BarangayBayogo/posts/123456789',
            'https://www.facebook.com/BarangayBayogo/videos/987654321',
            'https://www.facebook.com/reel/112233445566',
            'https://www.facebook.com/permalink.php?story_fbid=123&id=456',
            'https://www.facebook.com/photo.php?fbid=98765',
            'https://www.facebook.com/groups/bayogo/posts/55667788',
        ] as $url) {
            $this->assertTrue(SourceLink::isCanonicalFacebookPost($url), $url);
            $this->assertTrue(SourceLink::detect($url)['embeddable'], $url);
            $this->assertFalse(SourceLink::isFacebookShareLink($url), $url);
        }
    }

    /**
     * rdid and share_url identify the share that was clicked, not the post.
     * They would otherwise be stored, shown, and sent back to Facebook on
     * every resident's page load.
     */
    public function testShareTrackingParametersAreStrippedFromAResolvedLink(): void
    {
        $clean = SourceLink::stripShareTracking(
            'https://www.facebook.com/bayogo/posts/pfbid02Abc?rdid=2BIUFc41&share_url=https%3A%2F%2Ffb.me%2Fx'
        );

        $this->assertSame('https://www.facebook.com/bayogo/posts/pfbid02Abc', $clean);
    }

    /** A parameter that is part of the post itself must survive the cleaning. */
    public function testStrippingTrackingKeepsParametersThatIdentifyThePost(): void
    {
        $clean = SourceLink::stripShareTracking(
            'https://www.facebook.com/permalink.php?story_fbid=123&id=456&fbclid=XYZ'
        );

        $this->assertStringContainsString('story_fbid=123', $clean);
        $this->assertStringContainsString('id=456', $clean);
        $this->assertStringNotContainsString('fbclid', $clean);
    }

    /** Anything that is not a share stub is returned untouched, without a request. */
    public function testCanonicaliseLeavesNonShareLinksAlone(): void
    {
        foreach ([
            'https://www.facebook.com/BarangayBayogo/posts/123456789',
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://www.pna.gov.ph/articles/1234567',
            'not a url',
            '',
        ] as $url) {
            $this->assertSame($url, SourceLink::canonicalise($url), $url);
        }
    }

    // ── Every platform staff paste from ──────────────────────────────────────

    /**
     * Each platform gets its own official player, built from an id this class
     * extracted — the shape of the frame follows what it holds.
     */
    public function testEachSocialPlatformGetsItsOwnOfficialEmbed(): void
    {
        $cases = [
            ['https://www.tiktok.com/@brgybayogo/video/7301234567890123456?lang=en', SourceLink::TIKTOK, 'https://www.tiktok.com/embed/v2/7301234567890123456', 'tiktok'],
            ['https://www.instagram.com/p/C1aBcDeFgHi/', SourceLink::INSTAGRAM, 'https://www.instagram.com/p/C1aBcDeFgHi/embed/captioned/', 'post'],
            ['https://www.instagram.com/reel/C1aBcDeFgHi/?igsh=xyz', SourceLink::INSTAGRAM, 'https://www.instagram.com/reel/C1aBcDeFgHi/embed/captioned/', 'post'],
            ['https://x.com/pagasa/status/1712345678901234567', SourceLink::TWITTER, 'https://platform.twitter.com/embed/Tweet.html?dnt=true&id=1712345678901234567', 'post'],
            ['https://twitter.com/pagasa/status/1712345678901234567?s=20', SourceLink::TWITTER, 'https://platform.twitter.com/embed/Tweet.html?dnt=true&id=1712345678901234567', 'post'],
            ['https://www.threads.net/@brgy.bayogo/post/C1aBcDeFgHi', SourceLink::THREADS, 'https://www.threads.net/@brgy.bayogo/post/C1aBcDeFgHi/embed', 'post'],
            ['https://vimeo.com/123456789', SourceLink::VIMEO, 'https://player.vimeo.com/video/123456789?dnt=1', 'landscape'],
            ['https://www.dailymotion.com/video/x8abcd1', SourceLink::DAILYMOTION, 'https://geo.dailymotion.com/player.html?video=x8abcd1', 'landscape'],
            ['https://www.youtube.com/shorts/dQw4w9WgXcQ', SourceLink::YOUTUBE, 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0&playsinline=1', 'portrait'],
        ];

        foreach ($cases as [$url, $platform, $embed, $shape]) {
            $link = SourceLink::detect($url);

            $this->assertSame($platform, $link['platform'], $url);
            $this->assertTrue($link['embeddable'], $url);
            $this->assertSame($embed, $link['embed'], $url);
            $this->assertSame($shape, $link['shape'], $url);
            $this->assertSame(SourceLink::RENDER_IFRAME, $link['render'], $url);
        }
    }

    /** A Facebook post is drawn by the SDK; a Facebook video by its video player. */
    public function testFacebookPostsAndVideosAreDrawnByTheSdk(): void
    {
        $post = SourceLink::detect('https://www.facebook.com/BarangayBayogo/posts/pfbid02AbC?rdid=XYZ');
        $this->assertSame(SourceLink::RENDER_FACEBOOK, $post['render']);
        $this->assertSame('post', $post['fb_type']);
        $this->assertSame('https://www.facebook.com/BarangayBayogo/posts/pfbid02AbC', $post['href']);

        foreach ([
            'https://www.facebook.com/BarangayBayogo/videos/987654321',
            'https://www.facebook.com/reel/112233445566',
            'https://www.facebook.com/watch/?v=112233445566',
        ] as $url) {
            $video = SourceLink::detect($url);
            $this->assertSame('video', $video['fb_type'], $url);
            $this->assertStringContainsString('plugins/video.php', (string) $video['embed'], $url);
        }

        $this->assertTrue(SourceLink::detect('https://www.facebook.com/photo/?fbid=123456&set=a.789')['embeddable']);
        $this->assertTrue(SourceLink::detect('https://www.facebook.com/groups/bayogo/permalink/55667788/')['embeddable']);
    }

    /** A direct video file plays in a <video> tag, never in a frame. */
    public function testADirectVideoFilePlaysInPlace(): void
    {
        $link = SourceLink::detect('https://example.org/media/clean-up-drive.mp4');

        $this->assertSame(SourceLink::VIDEO, $link['platform']);
        $this->assertSame(SourceLink::RENDER_VIDEO, $link['render']);
    }

    /** Short share links carry no id yet, so they are not embedded unresolved. */
    public function testShortShareLinksAreRecognisedButNotEmbeddedUnresolved(): void
    {
        $this->assertTrue(SourceLink::isTikTokShortLink('https://vm.tiktok.com/ZSabc123/'));
        $this->assertTrue(SourceLink::isTikTokShortLink('https://www.tiktok.com/t/ZTabc123/'));
        $this->assertFalse(SourceLink::isTikTokShortLink('https://www.tiktok.com/@a/video/7301234567890123456'));

        $link = SourceLink::detect('https://vm.tiktok.com/ZSabc123/');
        $this->assertSame(SourceLink::TIKTOK, $link['platform']);
        $this->assertFalse($link['embeddable']);
    }

    // ── What staff paste ─────────────────────────────────────────────────────

    /**
     * Facebook's "Embed" menu is the reliable way to a link the SDK renders,
     * so its code is accepted and unwrapped to the post it names.
     */
    public function testFacebookEmbedCodeIsUnwrappedToThePost(): void
    {
        $code = '<iframe src="https://www.facebook.com/plugins/post.php?href=https%3A%2F%2Fwww.facebook.com%2FBarangayBayogo%2Fposts%2Fpfbid0ABC&show_text=true&width=500" width="500" height="591" style="border:none;overflow:hidden" scrolling="no" frameborder="0" allowfullscreen="true"></iframe>';

        $this->assertSame('https://www.facebook.com/BarangayBayogo/posts/pfbid0ABC', SourceLink::extractUrl($code));
    }

    /** An embed snippet carries several links; the post is the one kept. */
    public function testTheEmbeddablePostIsPickedOutOfASnippet(): void
    {
        $tweet = '<blockquote class="twitter-tweet"><p>Walang pasok</p>&mdash; PAGASA (@dost_pagasa) <a href="https://twitter.com/dost_pagasa/status/1712345678901234567?ref_src=twsrc%5Etfw">Oct 1</a></blockquote> <script async src="https://platform.twitter.com/widgets.js"></script>';
        $this->assertStringContainsString('/status/1712345678901234567', SourceLink::extractUrl($tweet));

        $tiktok = '<blockquote class="tiktok-embed" cite="https://www.tiktok.com/@brgy/video/7301234567890123456" data-video-id="7301234567890123456"><section><a href="https://www.tiktok.com/@brgy">@brgy</a></section></blockquote>';
        $this->assertSame('https://www.tiktok.com/@brgy/video/7301234567890123456', SourceLink::extractUrl($tiktok));
    }

    /** A link pasted with a sentence around it, as chat apps copy it. */
    public function testALinkIsLiftedOutOfSurroundingText(): void
    {
        $this->assertSame(
            'https://youtu.be/dQw4w9WgXcQ',
            SourceLink::extractUrl('Panoorin ninyo: https://youtu.be/dQw4w9WgXcQ salamat!')
        );
        $this->assertSame('', SourceLink::extractUrl('<a href="javascript:alert(1)">x</a>'));
        $this->assertSame('', SourceLink::prepare('javascript:alert(1)')['url']);
    }

    // ── A post that is only a link ───────────────────────────────────────────

    /** The platform's caption fills an empty body; staff's own words always win. */
    public function testALinkOnlyPostGetsItsBodyFromTheCaption(): void
    {
        $source = [
            'url'      => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'platform' => SourceLink::YOUTUBE,
            'meta'     => ['title' => 'Clean-up drive sa Purok 3'],
        ];

        [$title, $body] = SourceLink::fillLinkOnly($source, '', '<p><br></p>', true);
        $this->assertSame('Clean-up drive sa Purok 3', $title);
        $this->assertStringContainsString('Clean-up drive sa Purok 3', $body);

        [$title, $body] = SourceLink::fillLinkOnly($source, 'Sariling pamagat', 'Sariling teksto', false);
        $this->assertSame('Sariling pamagat', $title);
        $this->assertSame('Sariling teksto', $body);
    }

    /** No caption (Facebook gives none): a line pointing at the post below. */
    public function testALinkOnlyPostWithoutACaptionPointsAtThePost(): void
    {
        $source = ['url' => 'https://www.facebook.com/BarangayBayogo/posts/1', 'platform' => SourceLink::FACEBOOK, 'meta' => []];

        [$title, $body] = SourceLink::fillLinkOnly($source, 'Pulong', '', false);
        $this->assertSame('Pulong', $title);
        $this->assertStringContainsString('Facebook', $body);
    }

    /** No link, nothing filled: an empty post is still refused by validation. */
    public function testNothingIsFilledWithoutALink(): void
    {
        [$title, $body] = SourceLink::fillLinkOnly(['url' => '', 'platform' => null, 'meta' => []], '', '', true);

        $this->assertSame('', $title);
        $this->assertSame('', $body);
    }

    /** The resident view puts the player it built in the frame, never the pasted value. */
    public function testTheEmbedPartialRendersTheBuiltPlayerOnly(): void
    {
        $__seRow = [
            'source_url'      => 'https://www.tiktok.com/@brgy/video/7301234567890123456',
            'source_platform' => 'tiktok',
            'source_meta'     => null,
        ];
        $__seNoAssets = true;
        ob_start();
        require __DIR__ . '/../../app/views/shared/_source-embed.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('src="https://www.tiktok.com/embed/v2/7301234567890123456"', $html);
        $this->assertStringContainsString('allow-same-origin', $html, 'players refuse to start without their own storage');
        $this->assertStringNotContainsString('allow-top-navigation', $html);
    }
}
