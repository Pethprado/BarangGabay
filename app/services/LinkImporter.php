<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Fetches a URL a staff member pasted, and reads its Open Graph tags.
 *
 * ── Why this class is mostly guard rails ─────────────────────────────────
 *
 * Fetching a user-supplied URL from the server is server-side request forgery
 * waiting to happen: the attacker does not control our network, but they do
 * control where we point our own HTTP client, and our client is already inside
 * the firewall. On a barangay server that means the XAMPP admin pages, the
 * MySQL port, and on a hosted box the cloud metadata endpoint that hands out
 * credentials. So the fetch is wrapped in five separate checks, and every one
 * of them has to pass on every redirect hop:
 *
 *   1. http/https only. No file://, no gopher://, no php://filter.
 *   2. The hostname is resolved, and EVERY address it resolves to must be
 *      public. A name can legitimately answer with several addresses.
 *   3. The connection is pinned to the address that was checked, with
 *      CURLOPT_RESOLVE. Without this, a host can answer the validation lookup
 *      with a public address and the real request with 127.0.0.1 — DNS
 *      rebinding, and checking-then-connecting is exactly the window it needs.
 *   4. Redirects are followed by hand, a few at most, each one re-validated.
 *      cURL's own follower would happily walk to a private address.
 *   5. The response is capped and its content type checked before a byte of
 *      it is parsed.
 *
 * ── And what happens to what comes back ──────────────────────────────────
 *
 * Nothing fetched is ever rendered as HTML. Tags are extracted as text, and
 * the caller escapes them and runs the body through the same HTMLPurifier path
 * the Quill editor uses. The remote page is treated as hostile input from the
 * first byte, because that is what it is.
 */
final class LinkImporter
{
    /** Seconds. Short: a staff member is watching a spinner. */
    private const TIMEOUT = 8;

    /** Redirect hops. Enough for the usual shortener → site, not a maze. */
    private const MAX_REDIRECTS = 3;

    /** Bytes of HTML read before giving up. Meta tags live in the first few KB. */
    private const MAX_HTML_BYTES = 1_500_000;

    /**
     * Address ranges that must never be reached.
     *
     * filter_var's NO_PRIV_RANGE / NO_RES_RANGE covers most of this, but it has
     * gaps that matter here — it accepts 0.0.0.0, and it does not reason about
     * IPv4 addresses written as IPv6 (::ffff:127.0.0.1). Both are spelled out
     * rather than trusted to the flag.
     *
     * @var list<array{0:string,1:int}> [network, prefix length]
     */
    private const BLOCKED_V4 = [
        ['0.0.0.0', 8],        // "this network"
        ['10.0.0.0', 8],       // private
        ['100.64.0.0', 10],    // carrier-grade NAT
        ['127.0.0.0', 8],      // loopback
        ['169.254.0.0', 16],   // link-local — the cloud metadata endpoint
        ['172.16.0.0', 12],    // private
        ['192.0.0.0', 24],     // IETF protocol assignments
        ['192.0.2.0', 24],     // documentation
        ['192.168.0.0', 16],   // private
        ['198.18.0.0', 15],    // benchmarking
        ['198.51.100.0', 24],  // documentation
        ['203.0.113.0', 24],   // documentation
        ['224.0.0.0', 4],      // multicast
        ['240.0.0.0', 4],      // reserved
    ];

    /** @var list<array{0:string,1:int}> */
    private const BLOCKED_V6 = [
        ['::', 128],       // unspecified
        ['::1', 128],      // loopback
        ['fc00::', 7],     // unique local
        ['fe80::', 10],    // link-local
        ['ff00::', 8],     // multicast
    ];

    /**
     * Fetch a page and read its Open Graph tags.
     *
     * @return array{
     *     ok:bool, error:string|null, code:string|null,
     *     url:string|null, meta:array<string,string>
     * }  `code` is a lang key suffix so the caller can explain the refusal in
     *    the reader's own language; `error` carries any detail worth showing.
     */
    public function fetchPage(string $url): array
    {
        $result = $this->request($url, ['text/html', 'application/xhtml+xml'], self::MAX_HTML_BYTES);

        if (!$result['ok']) {
            return ['ok' => false, 'error' => $result['error'], 'code' => $result['code'], 'url' => null, 'meta' => []];
        }

        return [
            'ok'    => true,
            'error' => null,
            'code'  => null,
            'url'   => $result['url'],          // the address actually reached
            'meta'  => $this->parseMeta($result['body'], $result['url']),
        ];
    }

    /**
     * Fetch a binary file — a cover image, an ordinance PDF.
     *
     * @param list<string> $allowMime Content types this caller will accept.
     * @return array{ok:bool, error:string|null, code:string|null, bytes:string, mime:string}
     */
    public function fetchFile(string $url, array $allowMime, int $maxBytes): array
    {
        $result = $this->request($url, $allowMime, $maxBytes);

        return [
            'ok'    => $result['ok'],
            'error' => $result['error'],
            'code'  => $result['code'],
            'bytes' => $result['ok'] ? $result['body'] : '',
            'mime'  => $result['mime'] ?? '',
        ];
    }

    /**
     * Where does this link end up? The address only — never the page.
     *
     * Exists for one narrow job: a Facebook share link
     * (facebook.com/share/p/XXXX) is a redirect stub, and the Social Plugin
     * cannot render one — it answers "This post is no longer available" even
     * when the post is public and perfectly fine. The canonical address the
     * stub points at renders correctly, so the stub has to be followed once,
     * when staff attach the link, and the real address stored instead.
     *
     * This deliberately skips the social-wall refusal in request(). That
     * refusal is about IMPORTING TEXT — Facebook hands a logged-out server a
     * generic shell, so a fetch that "succeeds" produces a post titled
     * "Facebook". None of that applies here: the body is thrown away and only
     * the address is kept, which redirects report honestly.
     *
     * Every hop still goes through validateUrl() and the pinned send(), so the
     * address checks are exactly the ones every other fetch gets. The body is
     * capped at a few kilobytes because we never look at it.
     *
     * @return string|null The final address, or null if it could not be reached.
     */
    public function resolveRedirect(string $url, int $maxHops = self::MAX_REDIRECTS): ?string
    {
        $current = trim($url);

        for ($hop = 0; $hop <= $maxHops; $hop++) {
            $check = $this->validateUrl($current);
            if (!$check['ok']) {
                return null;
            }

            $response = $this->send($current, $check['host'], $check['ip'], $check['port'], 8192);
            if (!$response['ok']) {
                return null;
            }

            if ($response['status'] >= 300 && $response['status'] < 400 && $response['location'] !== '') {
                $next = $this->absolutise($response['location'], $current);
                if ($next === '') {
                    return null;
                }
                $current = $next;
                continue;
            }

            // Any non-redirect answer means this is the end of the chain. Even
            // a 404 is a truthful address; judging the page is not this
            // method's job, and the caller checks the shape of what it gets.
            return $current;
        }

        return null;
    }

    // ── The guarded request ──────────────────────────────────────────────────

    /**
     * One request, following redirects by hand and re-checking every hop.
     *
     * @param list<string> $allowMime
     * @return array{ok:bool, error:string|null, code:string|null, url:string, body:string, mime:string}
     */
    private function request(string $url, array $allowMime, int $maxBytes): array
    {
        $current = trim($url);

        /*
         * Facebook and its siblings are refused before a single packet leaves.
         *
         * Not an optimisation — a correctness fix. Facebook answers a
         * logged-out server with a generic shell whose og:title is literally
         * "Facebook", so the fetch SUCCEEDS and hands back a post titled
         * "Facebook" with no body. That is worse than a refusal: staff would
         * save it. There is no version of this that works without an app
         * token, so the honest answer is to say so up front and point at the
         * embed and the paste box, both of which do work.
         */
        if ($this->isSocialWall($current, 200)) {
            return $this->fail('login_wall', null);
        }

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $check = $this->validateUrl($current);
            if (!$check['ok']) {
                return $this->fail($check['code'], $check['error']);
            }

            $response = $this->send($current, $check['host'], $check['ip'], $check['port'], $maxBytes);
            if (!$response['ok']) {
                return $this->fail('unreachable', $response['error']);
            }

            // Redirect: resolve it against the current URL and go round again,
            // so the next hop faces exactly the same checks as the first.
            if ($response['status'] >= 300 && $response['status'] < 400 && $response['location'] !== '') {
                $current = $this->absolutise($response['location'], $current);
                if ($current === '') {
                    return $this->fail('bad_url', null);
                }
                continue;
            }

            if ($response['status'] >= 400) {
                return $this->fail('http_error', (string) $response['status']);
            }

            $mime = strtolower(trim(explode(';', $response['mime'])[0]));
            if ($allowMime !== [] && !in_array($mime, $allowMime, true)) {
                return $this->fail('wrong_type', $mime !== '' ? $mime : null);
            }

            return [
                'ok' => true, 'error' => null, 'code' => null,
                'url' => $current, 'body' => $response['body'], 'mime' => $mime,
            ];
        }

        return $this->fail('too_many_redirects', null);
    }

    /**
     * Scheme, host and resolved addresses.
     *
     * @return array{ok:bool, code:string|null, error:string|null, host:string, ip:string, port:int}
     */
    private function validateUrl(string $url): array
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return ['ok' => false, 'code' => 'bad_url', 'error' => null, 'host' => '', 'ip' => '', 'port' => 0];
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            return ['ok' => false, 'code' => 'bad_scheme', 'error' => $scheme, 'host' => '', 'ip' => '', 'port' => 0];
        }

        // parse_url keeps the brackets on an IPv6 literal ("[::1]"), and with
        // them the host does not validate as an address — so it would fall
        // through to a DNS lookup and be refused only because that lookup
        // happens to fail. Stripping them means ::1 is caught by the address
        // table, on purpose, which is the check that has to hold.
        $host = trim($parts['host'], '[]');
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        // Named loopback never reaches DNS on some systems, so it is refused
        // by name as well as by address.
        if (in_array(strtolower($host), ['localhost', 'localhost.localdomain', 'ip6-localhost'], true)) {
            return ['ok' => false, 'code' => 'private_address', 'error' => $host, 'host' => '', 'ip' => '', 'port' => 0];
        }

        $addresses = $this->resolve($host);
        if ($addresses === []) {
            return ['ok' => false, 'code' => 'dns_failed', 'error' => $host, 'host' => '', 'ip' => '', 'port' => 0];
        }

        // EVERY answer must be public. One private address among several is
        // enough to make the name unusable — which is the whole trick.
        foreach ($addresses as $ip) {
            if (!$this->isPublicIp($ip)) {
                return ['ok' => false, 'code' => 'private_address', 'error' => $ip, 'host' => '', 'ip' => '', 'port' => 0];
            }
        }

        return ['ok' => true, 'code' => null, 'error' => null, 'host' => $host, 'ip' => $addresses[0], 'port' => $port];
    }

    /**
     * Send one request, pinned to an address that has already been checked.
     *
     * @return array{ok:bool, error:string|null, status:int, location:string, mime:string, body:string}
     */
    private function send(string $url, string $host, string $ip, int $port, int $maxBytes): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'curl unavailable', 'status' => 0, 'location' => '', 'mime' => '', 'body' => ''];
        }

        $body    = '';
        $tooBig  = false;

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,     // hop-by-hop, checked by us
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT      => 'BarangGabay/1.0 (+barangay content import)',
            CURLOPT_ACCEPT_ENCODING => '',
            /*
             * Pin the name to the address that passed validation, closing the
             * gap between the check and the connection — without this a host
             * can answer the validation lookup with a public address and the
             * real request with 127.0.0.1.
             *
             * Skipped when the URL already names an address: there is no DNS
             * step to rebind, and curl rejects a RESOLVE entry keyed on an IP.
             */
            CURLOPT_RESOLVE        => filter_var($host, FILTER_VALIDATE_IP) !== false
                ? []
                : ["{$host}:{$port}:{$ip}"],
            CURLOPT_WRITEFUNCTION  => static function ($_, string $chunk) use (&$body, &$tooBig, $maxBytes): int {
                $body .= $chunk;
                if (strlen($body) > $maxBytes) {
                    $tooBig = true;
                    return -1;                    // abort the transfer
                }
                return strlen($chunk);
            },
        ]);

        curl_exec($ch);
        $error    = curl_error($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $mime     = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $location = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);

        // Hitting the size cap is a success with a truncated body: the meta
        // tags we want are in the <head>, long before any cap.
        if ($status === 0 && !$tooBig) {
            return ['ok' => false, 'error' => $error, 'status' => 0, 'location' => '', 'mime' => '', 'body' => ''];
        }

        return [
            'ok'       => true,
            'error'    => null,
            'status'   => $status,
            'location' => $location,
            'mime'     => $mime,
            'body'     => $body,
        ];
    }

    // ── Address checks ───────────────────────────────────────────────────────

    /**
     * Every address a hostname answers with.
     *
     * @return list<string>
     */
    private function resolve(string $host): array
    {
        // Already an address? Nothing to resolve, but still everything to check.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $out = [];

        foreach (['A' => DNS_A, 'AAAA' => DNS_AAAA] as $key => $type) {
            $records = @dns_get_record($host, $type) ?: [];
            foreach ($records as $record) {
                $ip = $record[$key === 'A' ? 'ip' : 'ipv6'] ?? null;
                if (is_string($ip) && $ip !== '') {
                    $out[] = $ip;
                }
            }
        }

        // dns_get_record can come back empty on some Windows/XAMPP setups even
        // for names that resolve perfectly well, so fall back rather than
        // refusing a legitimate link. gethostbyname still gives an address to
        // validate, which is the part that matters.
        if ($out === []) {
            $ip = gethostbyname($host);
            if ($ip !== $host && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                $out[] = $ip;
            }
        }

        return array_values(array_unique($out));
    }

    /** Is this address safe to connect to from inside the barangay network? */
    public function isPublicIp(string $ip): bool
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        // IPv4 written as IPv6 (::ffff:127.0.0.1) is an IPv4 address wearing a
        // disguise, and must be judged as one.
        if (strlen($packed) === 16 && str_starts_with($packed, "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff")) {
            $ip     = inet_ntop(substr($packed, 12));
            $packed = @inet_pton((string) $ip);
            if ($packed === false) {
                return false;
            }
        }

        $ranges = strlen($packed) === 4 ? self::BLOCKED_V4 : self::BLOCKED_V6;
        foreach ($ranges as [$network, $bits]) {
            if ($this->inRange($packed, (string) inet_pton($network), $bits)) {
                return false;
            }
        }

        // Belt and braces: the built-in filter catches anything the table above
        // has not thought of.
        return filter_var(
            (string) inet_ntop($packed),
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    /** Bitwise prefix match on packed addresses. */
    private function inRange(string $ip, string $network, int $bits): bool
    {
        if (strlen($ip) !== strlen($network)) {
            return false;
        }

        $whole = intdiv($bits, 8);
        $rest  = $bits % 8;

        if ($whole > 0 && strncmp($ip, $network, $whole) !== 0) {
            return false;
        }
        if ($rest === 0) {
            return true;
        }

        $mask = ~((1 << (8 - $rest)) - 1) & 0xFF;

        return (ord($ip[$whole]) & $mask) === (ord($network[$whole]) & $mask);
    }

    // ── Reading the page ─────────────────────────────────────────────────────

    /**
     * Pull the Open Graph tags out of fetched HTML.
     *
     * Everything returned is plain text with tags stripped — this output is
     * escaped by the view and purified by the controller, and must never be
     * treated as markup at any point in between.
     *
     * @return array<string,string>
     */
    public function parseMeta(string $html, string $sourceUrl = ''): array
    {
        if (trim($html) === '') {
            return [];
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        // The charset hint matters: without it a UTF-8 page comes back mojibake.
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $meta  = [];

        $read = static function (string $query) use ($xpath): string {
            $node = $xpath->query($query)->item(0);

            return $node instanceof \DOMElement ? trim($node->getAttribute('content')) : '';
        };

        $meta['title'] = $read('//meta[@property="og:title"]')
            ?: $read('//meta[@name="twitter:title"]')
            ?: trim((string) ($xpath->query('//title')->item(0)->textContent ?? ''));

        $meta['description'] = $read('//meta[@property="og:description"]')
            ?: $read('//meta[@name="twitter:description"]')
            ?: $read('//meta[@name="description"]');

        $meta['image'] = $read('//meta[@property="og:image"]')
            ?: $read('//meta[@property="og:image:url"]')
            ?: $read('//meta[@name="twitter:image"]');

        $meta['published'] = $read('//meta[@property="article:published_time"]')
            ?: $read('//meta[@property="og:updated_time"]');

        $meta['site']  = $read('//meta[@property="og:site_name"]');
        $meta['type']  = $read('//meta[@property="og:type"]');

        // A relative og:image is legal and common.
        if ($meta['image'] !== '' && $sourceUrl !== '') {
            $meta['image'] = $this->absolutise($meta['image'], $sourceUrl);
        }

        foreach ($meta as $key => $value) {
            // Defence in depth: these are already attribute values, but a tag
            // smuggled through here would end up in a form field.
            $meta[$key] = trim(strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }

        return array_filter($meta, static fn (string $v): bool => $v !== '');
    }

    /** Resolve a possibly-relative URL against the page it came from. */
    public function absolutise(string $url, string $base): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $parts = parse_url($base);
        if ($parts === false || empty($parts['host'])) {
            return '';
        }

        $root = ($parts['scheme'] ?? 'https') . '://' . $parts['host']
              . (isset($parts['port']) ? ':' . $parts['port'] : '');

        if (str_starts_with($url, '//')) {
            return ($parts['scheme'] ?? 'https') . ':' . $url;
        }
        if (str_starts_with($url, '/')) {
            return $root . $url;
        }

        $dir = rtrim(\dirname($parts['path'] ?? '/'), '/');

        return $root . $dir . '/' . $url;
    }

    // ── Small helpers ────────────────────────────────────────────────────────

    /** Hosts that answer a server with a login page rather than the content. */
    private function isSocialWall(string $url, int $status): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));

        foreach (['facebook.com', 'fb.com', 'fb.watch', 'instagram.com', 'threads.net', 'x.com', 'twitter.com'] as $blocked) {
            if ($host === $blocked || str_ends_with($host, '.' . $blocked)) {
                return $status !== 0;
            }
        }

        return false;
    }

    /** @return array{ok:bool, error:string|null, code:string, url:string, body:string, mime:string} */
    private function fail(string $code, ?string $error): array
    {
        return ['ok' => false, 'error' => $error, 'code' => $code, 'url' => '', 'body' => '', 'mime' => ''];
    }
}
