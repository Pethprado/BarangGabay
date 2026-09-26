<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Dark mode is reached two ways, and both must arrive at the same page.
 *
 *   - the explicit toggle stamps [data-theme="dark"] on <html>
 *   - the OS preference matches @media (prefers-color-scheme: dark)
 *
 * Every remap rule is therefore written twice, by hand. A rule added to one
 * copy and forgotten in the other is invisible three ways over: it looks
 * correct in the diff, it looks correct in light mode, and it looks correct
 * to whichever half of the team reached dark mode the other way. It shows
 * up only as a user saying some words cannot be seen — on a page that looks
 * fine to everybody else.
 *
 * Four rules had already drifted apart this way: .bg-slate-200,
 * .bg-slate-100.text-slate-700 and the two .divide-slate-* rules existed
 * only on the toggle path, so an OS-dark reader kept a light grey fill and
 * light divider lines drawn across dark cards.
 *
 * This test compares the two rule sets as sets. It does not care what the
 * rules say — only that neither path knows something the other does not.
 */
final class DarkPathParityTest extends TestCase
{
    private static string $css;

    public static function setUpBeforeClass(): void
    {
        // Comments contain selector-shaped prose; strip them first.
        self::$css = (string) preg_replace(
            '#/\*.*?\*/#s',
            '',
            (string) file_get_contents(\dirname(__DIR__, 2) . '/public/assets/css/main.css')
        );
    }

    /** Everything inside @media (prefers-color-scheme: dark), braces balanced. */
    private function insideDarkMedia(): string
    {
        $css = self::$css;
        $out = '';
        $len = strlen($css);

        if (!preg_match_all('~@media \(prefers-color-scheme: dark\)\s*\{~', $css, $m, PREG_OFFSET_CAPTURE)) {
            return '';
        }
        foreach ($m[0] as $hit) {
            $open  = $hit[1] + strlen($hit[0]) - 1;
            $depth = 1;
            $i     = $open + 1;
            while ($i < $len && $depth > 0) {
                if ($css[$i] === '{')      { $depth++; }
                elseif ($css[$i] === '}')  { $depth--; }
                $i++;
            }
            $out .= substr($css, $open + 1, $i - $open - 2) . "\n";
        }
        return $out;
    }

    /** Everything NOT inside an @media block. */
    private function outsideMedia(): string
    {
        return (string) preg_replace(
            '~@media[^{]*\{(?:[^{}]|\{[^{}]*\})*\}~',
            '',
            self::$css
        );
    }

    /**
     * Selector (with the theme scope stripped) => declaration body.
     *
     * @return array<string,string>
     */
    private function rulesUnder(string $css, string $scopePattern): array
    {
        $out = [];
        if (!preg_match_all('~([^{}]+)\{([^{}]*)\}~', $css, $m, PREG_SET_ORDER)) {
            return $out;
        }
        foreach ($m as $rule) {
            $body = trim((string) preg_replace('/\s+/', ' ', $rule[2]));
            foreach (array_map('trim', explode(',', trim($rule[1]))) as $selector) {
                if (!preg_match($scopePattern, $selector)) { continue; }
                $bare = trim((string) preg_replace($scopePattern, '', $selector));
                if ($bare === '') { continue; }
                $out[$bare] = $body;
            }
        }
        return $out;
    }

    private function togglePath(): array
    {
        return $this->rulesUnder($this->outsideMedia(), '~:root\[data-theme="dark"\]\s*~');
    }

    private function osPath(): array
    {
        return $this->rulesUnder($this->insideDarkMedia(), '~:root:not\(\[data-theme="light"\]\)\s*~');
    }

    public function testBothPathsCarryTheSameSelectors(): void
    {
        $toggle = $this->togglePath();
        $osDark = $this->osPath();

        $this->assertNotEmpty($toggle, 'no toggle-scoped rules found — the scan is broken, not the CSS');
        $this->assertNotEmpty($osDark, 'no OS-scoped rules found — the scan is broken, not the CSS');

        $missingFromOs = array_values(array_diff(array_keys($toggle), array_keys($osDark)));
        $missingFromToggle = array_values(array_diff(array_keys($osDark), array_keys($toggle)));

        $this->assertSame(
            [], $missingFromOs,
            "styled for the toggle but not for an OS-dark reader:\n  " . implode("\n  ", $missingFromOs)
        );
        $this->assertSame(
            [], $missingFromToggle,
            "styled for an OS-dark reader but not for the toggle:\n  " . implode("\n  ", $missingFromToggle)
        );
    }

    public function testBothPathsDeclareTheSameThing(): void
    {
        $toggle = $this->togglePath();
        $osDark = $this->osPath();

        foreach (array_intersect_key($toggle, $osDark) as $selector => $body) {
            $this->assertSame(
                $body,
                $osDark[$selector],
                "{$selector} is declared differently on the two dark paths, so the page "
                . 'renders differently depending on how dark mode was reached'
            );
        }
    }
}
