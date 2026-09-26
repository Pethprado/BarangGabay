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
}
