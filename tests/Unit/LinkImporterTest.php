<?php
declare(strict_types=1);

use App\Services\LinkImporter;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Pins the SSRF guards on the link importer.
 *
 * This is the one feature in the app that takes an address from a user and
 * makes the SERVER go there. On a barangay box that server sits behind the
 * firewall with the XAMPP admin pages and the database; on a hosted one it can
 * reach the cloud metadata endpoint that hands out credentials. So the tests
 * here are almost all about what must be refused, and they are written as
 * literal addresses rather than generated, so that loosening any single one
 * has to be a deliberate edit to a line that says what it is.
 */
final class LinkImporterTest extends TestCase
{
    private LinkImporter $importer;

    protected function setUp(): void
    {
        $this->importer = new LinkImporter();
        parent::setUp();
    }

    // ── Addresses that must never be reachable ───────────────────────────────

    /**
     * Loopback, private ranges, link-local and the reserved blocks. The
     * link-local entry is the one that matters most on a hosted server:
     * 169.254.169.254 is the cloud metadata service.
     */
    public function testPrivateAndReservedAddressesAreRefused(): void
    {
        $blocked = [
            '127.0.0.1',        // loopback
            '127.1.2.3',        // the rest of 127/8
            '0.0.0.0',          // "this network" — filter_var alone allows this
            '10.0.0.1',         // private
            '10.255.255.254',
            '172.16.0.1',       // private
            '172.31.255.254',
            '192.168.0.1',      // private
            '192.168.1.1',
            '169.254.169.254',  // cloud metadata
            '100.64.0.1',       // carrier-grade NAT
            '198.18.0.1',       // benchmarking
            '224.0.0.1',        // multicast
            '240.0.0.1',        // reserved
            '::1',              // IPv6 loopback
            '::',               // unspecified
            'fc00::1',          // unique local
            'fd00::1',
            'fe80::1',          // link-local
        ];

        foreach ($blocked as $ip) {
            $this->assertFalse(
                $this->importer->isPublicIp($ip),
                "{$ip} must never be reachable from the importer"
            );
        }
    }

    /**
     * An IPv4 address wearing an IPv6 costume is still that IPv4 address.
     * ::ffff:127.0.0.1 is loopback, and a check that only reads the IPv6 table
     * would wave it through.
     */
    public function testIpv4MappedIntoIpv6IsJudgedAsIpv4(): void
    {
        $this->assertFalse($this->importer->isPublicIp('::ffff:127.0.0.1'));
        $this->assertFalse($this->importer->isPublicIp('::ffff:10.0.0.1'));
        $this->assertFalse($this->importer->isPublicIp('::ffff:169.254.169.254'));
    }

    /** Ordinary public addresses still have to work, or the feature is useless. */
    public function testPublicAddressesAreAllowed(): void
    {
        foreach (['8.8.8.8', '1.1.1.1', '93.184.216.34', '2001:4860:4860::8888'] as $ip) {
            $this->assertTrue($this->importer->isPublicIp($ip), "{$ip} should be reachable");
        }
    }

    public function testGarbageIsNotMistakenForAnAddress(): void
    {
        foreach (['', 'not-an-ip', '999.999.999.999', '127.0.0.1 ', '0x7f000001'] as $value) {
            $this->assertFalse($this->importer->isPublicIp($value));
        }
    }

    // ── The three the definition of done names ───────────────────────────────

    /**
     * The exact cases to try by hand: localhost by name, 127.0.0.1 by address,
     * and a private address written as a URL.
     *
     * Refused before any socket is opened, so this test makes no network call.
     */
    public function testTheLoopbackUrlsAreRefusedWithTheRightReason(): void
    {
        $cases = [
            'http://127.0.0.1/',
            'http://localhost/',
            'http://localhost:8080/BarangGabay/public/',
            'http://[::1]/',
            'http://192.168.1.1/admin',
            'http://169.254.169.254/latest/meta-data/',
        ];

        foreach ($cases as $url) {
            $result = $this->importer->fetchPage($url);

            $this->assertFalse($result['ok'], "{$url} must be refused");
            $this->assertSame(
                'private_address',
                $result['code'],
                "{$url} should be refused for being private, not for some incidental reason"
            );
        }
    }

    /** Only http and https. A file:// URL would read the server's own disk. */
    public function testOnlyHttpAndHttpsAreAccepted(): void
    {
        foreach (['file:///c:/xampp/htdocs/BarangGabay/.env', 'php://filter/resource=index.php',
                  'gopher://evil/', 'ftp://example.com/x', 'javascript:alert(1)'] as $url) {
            $result = $this->importer->fetchPage($url);

            $this->assertFalse($result['ok'], "{$url} must be refused");
            $this->assertContains($result['code'], ['bad_scheme', 'bad_url'], $url);
        }
    }

    /**
     * Facebook is refused before a packet leaves, and by name.
     *
     * Not an optimisation. Facebook answers a logged-out server with a generic
     * shell whose og:title is literally "Facebook" — so without this the fetch
     * SUCCEEDS and hands back a post titled "Facebook" with no body, which
     * staff would then save. Verified against the live site: that is exactly
     * what comes back. There is no version of this that works without an app
     * token, so the refusal names the two paths that do.
     */
    public function testSocialPlatformsAreRefusedByNameRatherThanHalfImported(): void
    {
        $walls = [
            'https://www.facebook.com/BarangayBayogo/posts/123456789',
            'https://facebook.com/share/p/abc123/',
            'https://m.facebook.com/story.php?story_fbid=1&id=2',
            'https://fb.watch/abc123/',
            'https://www.instagram.com/p/Cxyz/',
            'https://x.com/someone/status/1',
        ];

        foreach ($walls as $url) {
            $result = $this->importer->fetchPage($url);

            $this->assertFalse($result['ok'], "{$url} must not half-import");
            $this->assertSame('login_wall', $result['code'], $url);
            $this->assertSame([], $result['meta'], 'Nothing may be returned to pre-fill a form with');
        }
    }

    // ── Reading a page ───────────────────────────────────────────────────────

    /** Open Graph first, then Twitter cards, then the plain title tag. */
    public function testOpenGraphTagsAreRead(): void
    {
        $html = '<html><head>'
            . '<meta property="og:title" content="Libreng check-up sa Barangay">'
            . '<meta property="og:description" content="Bukas, 8 AM sa Barangay Hall.">'
            . '<meta property="og:image" content="https://example.com/photo.jpg">'
            . '<meta property="og:site_name" content="Madrid LGU">'
            . '<meta property="article:published_time" content="2026-09-18T08:00:00+08:00">'
            . '<title>Ignored when og:title exists</title>'
            . '</head><body>x</body></html>';

        $meta = $this->importer->parseMeta($html, 'https://example.com/news/1');

        $this->assertSame('Libreng check-up sa Barangay', $meta['title']);
        $this->assertSame('Bukas, 8 AM sa Barangay Hall.', $meta['description']);
        $this->assertSame('https://example.com/photo.jpg', $meta['image']);
        $this->assertSame('Madrid LGU', $meta['site']);
        $this->assertStringStartsWith('2026-09-18', $meta['published']);
    }

    public function testItFallsBackToTheTitleTagAndMetaDescription(): void
    {
        $html = '<html><head><title>Barangay notice</title>'
              . '<meta name="description" content="A plain description.">'
              . '</head><body></body></html>';

        $meta = $this->importer->parseMeta($html, 'https://example.com/');

        $this->assertSame('Barangay notice', $meta['title']);
        $this->assertSame('A plain description.', $meta['description']);
    }

    /** A relative og:image is legal and common, and must still resolve. */
    public function testRelativeImagesAreMadeAbsolute(): void
    {
        $meta = $this->importer->parseMeta(
            '<html><head><meta property="og:image" content="/img/cover.jpg"></head></html>',
            'https://example.com/news/story'
        );

        $this->assertSame('https://example.com/img/cover.jpg', $meta['image']);
    }

    /**
     * Whatever comes back is hostile input. Nothing extracted may still be
     * markup by the time it reaches a form field.
     */
    public function testExtractedTextCarriesNoMarkup(): void
    {
        $html = '<html><head>'
            . '<meta property="og:title" content="&lt;script&gt;alert(1)&lt;/script&gt; Notice">'
            . '<meta property="og:description" content="&lt;img src=x onerror=alert(1)&gt;">'
            . '</head></html>';

        $meta = $this->importer->parseMeta($html, 'https://example.com/');

        $this->assertStringNotContainsString('<script', $meta['title']);
        $this->assertStringNotContainsString('<img', $meta['description'] ?? '');
        $this->assertStringNotContainsString('onerror', $meta['description'] ?? '');
    }

    public function testAPageWithNothingUsefulReturnsNothing(): void
    {
        $this->assertSame([], $this->importer->parseMeta('<html><body>hello</body></html>'));
        $this->assertSame([], $this->importer->parseMeta(''));
    }

    // ── URL resolution ───────────────────────────────────────────────────────

    public function testRelativeUrlsResolveAgainstTheirPage(): void
    {
        $base = 'https://example.com/news/2026/story.html';

        $this->assertSame('https://example.com/a.jpg',            $this->importer->absolutise('/a.jpg', $base));
        $this->assertSame('https://example.com/news/2026/a.jpg',  $this->importer->absolutise('a.jpg', $base));
        $this->assertSame('https://cdn.example.com/a.jpg',        $this->importer->absolutise('//cdn.example.com/a.jpg', $base));
        $this->assertSame('https://other.example/a.jpg',          $this->importer->absolutise('https://other.example/a.jpg', $base));
    }
}
