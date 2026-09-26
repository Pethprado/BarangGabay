<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * A var(--token) only works if the page can actually reach its declaration.
 *
 * CSS does not complain about an undefined custom property. The declaration
 * is simply dropped and the element keeps whatever it inherited, so the line
 * still renders — just not in the colour anyone chose. It looks fine unless
 * you knew what it was supposed to look like.
 *
 * Three real instances of this were live in the app:
 *
 *   - the purok form's "these residents have no purok" warning used
 *     --tw-amber-700, which is declared in main.css. That form renders in
 *     the ADMIN layout, which loads admin.css. The one line on the page
 *     meant to stand out was painted in the body colour.
 *   - two-factor-challenge.php styled its shield, its heading and both its
 *     links with --tw-green-700 while loading no stylesheet of ours at all.
 *   - register.php did the same on two lines.
 *
 * The --tw-* family is the trap: it is large, it looks shared, and it lives
 * in exactly one stylesheet. So this test checks that family specifically,
 * against the stylesheets each view can actually see.
 */
final class TokenReachabilityTest extends TestCase
{
    /** Tokens declared by one stylesheet. @return list<string> */
    private function declaredIn(string $file): array
    {
        $path = \dirname(__DIR__, 2) . '/public/assets/css/' . $file;
        if (!is_file($path)) { return []; }

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));
        preg_match_all('~(--[a-z0-9-]+)\s*:~i', $css, $m);

        return array_values(array_unique($m[1]));
    }

    /**
     * Which stylesheets a view ends up with.
     *
     * A standalone page links its own. A page rendered into a layout gets
     * whatever that layout links — resident views get main.css, admin views
     * get admin.css, and both now get tokens.css.
     *
     * @return list<string>
     */
    private function stylesheetsFor(string $relativePath, string $source): array
    {
        // Standalone: it links them itself.
        if (preg_match_all("~asset_v\('assets/css/([^']+)'\)~", $source, $m)) {
            return $m[1];
        }

        // Rendered into a layout — which one depends on where it lives.
        if (str_starts_with($relativePath, 'admin/')) {
            return ['tokens.css', 'admin.css'];
        }
        if (str_starts_with($relativePath, 'resident/') || str_starts_with($relativePath, 'public/')) {
            return ['tokens.css', 'main.css'];
        }

        /* shared/ partials and auth/ pages are included from either side, so
           they may only rely on what BOTH layouts provide. That is the
           stricter answer and the correct one: a partial that works on one
           side and not the other is the bug being tested for. */
        return ['tokens.css'];
    }

    public function testNoViewUsesATailwindTokenItCannotSee(): void
    {
        $root    = \dirname(__DIR__, 2) . '/app/views';
        $offenders = [];
        $checked   = 0;

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.php')) { continue; }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $source   = (string) file_get_contents($file->getPathname());

            if (!preg_match_all('~var\(\s*(--tw-[a-z0-9-]+)~i', $source, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $available = [];
            foreach ($this->stylesheetsFor($relative, $source) as $sheet) {
                $available = array_merge($available, $this->declaredIn($sheet));
            }

            foreach ($m[1] as [$token, $offset]) {
                $checked++;
                if (\in_array($token, $available, true)) { continue; }

                $line = substr_count(substr($source, 0, $offset), "\n") + 1;
                $offenders[] = sprintf(
                    '%s:%d uses %s, which none of [%s] declares',
                    $relative, $line, $token,
                    implode(', ', $this->stylesheetsFor($relative, $source))
                );
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "custom properties that resolve to nothing where they are used:\n  "
            . implode("\n  ", $offenders)
        );
    }

    /**
     * The shared stylesheet has to be loaded before the one that reads it.
     *
     * main.css and admin.css declare almost no colours of their own now —
     * they consume tokens.css. Link them in the wrong order and the cascade
     * still works (custom properties are not order-dependent in the same
     * way), but a page that links main.css and forgets tokens.css entirely
     * loses every colour. That is the case worth catching.
     */
    public function testEveryPageLinkingAStylesheetAlsoLinksTheTokens(): void
    {
        $root      = \dirname(__DIR__, 2) . '/app/views';
        $offenders = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.php')) { continue; }

            $source = (string) file_get_contents($file->getPathname());
            if (!preg_match_all("~asset_v\('assets/css/([^']+)'\)~", $source, $m)) { continue; }

            $sheets = $m[1];
            if (!array_intersect(['main.css', 'admin.css'], $sheets)) { continue; }
            if (\in_array('tokens.css', $sheets, true)) { continue; }

            $offenders[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        }

        $this->assertSame(
            [],
            $offenders,
            "these pages link a stylesheet that reads tokens.css but never load it:\n  "
            . implode("\n  ", $offenders)
        );
    }
}
