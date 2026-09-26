<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Dark mode is a contract, not a coat of paint.
 *
 * Two things here are easy to break and impossible to notice from the code:
 *
 *   1. A Tailwind accent class used in a view with no --tw-* token behind it
 *      keeps its light value. .text-blue-800 did exactly that and measured
 *      1.97:1 on the dark card — painted, and unreadable. That is the "some
 *      words can't be seen" report.
 *
 *   2. The two dark blocks — the OS preference and the explicit toggle — are
 *      separate copies. A token added to one and forgotten in the other means
 *      the page renders differently depending on how the reader got to dark
 *      mode, which nobody would think to test by hand.
 *
 * Both are checked against main.css itself, so they fail the moment a view
 * starts using an accent the theme does not define.
 */
final class DarkModeContrastTest extends TestCase
{
    private static string $css;

    public static function setUpBeforeClass(): void
    {
        self::$css = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/public/assets/css/main.css'
        );
    }

    /** @return array<string,string> token name => value, from one block */
    private function tokensIn(string $block): array
    {
        $out = [];
        if (preg_match_all('~--(tw-[a-z0-9-]+)\s*:\s*([^;]+);~', $block, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) { $out[$hit[1]] = trim($hit[2]); }
        }
        return $out;
    }

    private function block(string $pattern): string
    {
        $this->assertSame(1, preg_match($pattern, self::$css, $m), 'block not found: ' . $pattern);
        return $m[1];
    }

    /**
     * The OS-preference block and the explicit-toggle block must define the
     * same accent tokens with the same values.
     */
    public function testBothDarkPathsDefineTheSameAccents(): void
    {
        $osDark = $this->tokensIn($this->block(
            '~@media \(prefers-color-scheme: dark\)\s*\{\s*:root:not\(\[data-theme="light"\]\)\s*\{(.*?)\n    \}~s'
        ));
        $toggle = $this->tokensIn($this->block(
            '~:root\[data-theme="dark"\]\s*\{(.*?)\n\}~s'
        ));

        $this->assertNotEmpty($osDark, 'the OS dark block defines no accent tokens');

        $this->assertSame(
            [], array_diff(array_keys($osDark), array_keys($toggle)),
            'tokens defined for the OS default but missing from the toggle'
        );
        $this->assertSame(
            [], array_diff(array_keys($toggle), array_keys($osDark)),
            'tokens defined for the toggle but missing from the OS default'
        );

        foreach ($osDark as $name => $value) {
            $this->assertSame(
                $value, $toggle[$name],
                "--{$name} differs between the two dark paths, so the toggle and the OS default render differently"
            );
        }
    }

    /**
     * Every accent utility a resident-facing view uses must be backed by a
     * token. Without one it keeps its light value on a dark card.
     *
     * Light shades (50-300) are exempt: they are already pale and read fine on
     * a dark surface. It is the 500-900 weights, tuned for a white card, that
     * disappear.
     */
    public function testEveryAccentUsedInAResidentViewHasADarkToken(): void
    {
        $root  = \dirname(__DIR__, 2);
        $dirs  = ['/app/views/resident', '/app/views/layouts', '/app/views/auth'];
        $used  = [];

        foreach ($dirs as $dir) {
            if (!is_dir($root . $dir)) { continue; }
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . $dir)) as $file) {
                if (!$file->isFile() || !str_ends_with($file->getFilename(), '.php')) { continue; }

                $source = (string) file_get_contents($file->getPathname());
                // hover: variants count. They were the last gap found: the
                // resting colour was remapped and the hover was not, so a
                // "Read more" link was readable until a reader pointed at it.
                if (!preg_match_all('~\b(?:hover:)?(text|bg)-([a-z]+)-(\d{3})\b~', $source, $m, PREG_SET_ORDER)) {
                    continue;
                }
                foreach ($m as $hit) {
                    [, $kind, $family, $shade] = $hit;

                    // Neutrals are remapped wholesale by the slate/gray block.
                    if (\in_array($family, ['slate', 'gray', 'white', 'black', 'transparent'], true)) {
                        continue;
                    }
                    // Pale shades stay legible on a dark surface as they are.
                    if ((int) $shade < 500 && $kind === 'text') { continue; }
                    if ($kind === 'bg' && (int) $shade !== 100)  { continue; }

                    $used[$hit[0]] = $kind === 'text'
                        ? "tw-{$family}-{$shade}"
                        : "tw-bg-{$family}-{$shade}";
                }
            }
        }

        $this->assertNotEmpty($used, 'no accent utilities found — the scan is broken, not the CSS');

        $toggle  = $this->tokensIn($this->block('~:root\[data-theme="dark"\]\s*\{(.*?)\n\}~s'));
        $missing = [];

        foreach ($used as $class => $token) {
            if (!isset($toggle[$token])) { $missing[] = "{$class} (needs --{$token})"; }
        }

        sort($missing);
        $this->assertSame([], $missing,
            "these accent classes keep their light value in dark mode:\n  " . implode("\n  ", $missing));
    }

    /**
     * A token is only half the job — something has to consume it. An accent
     * class with a token but no rule reading that token still renders its
     * stock Tailwind value.
     */
    public function testEveryAccentTokenIsActuallyConsumedByARule(): void
    {
        $toggle  = $this->tokensIn($this->block('~:root\[data-theme="dark"\]\s*\{(.*?)\n\}~s'));
        $unused  = [];

        foreach (array_keys($toggle) as $token) {
            if (!str_contains(self::$css, 'var(--' . $token . ')')) {
                $unused[] = '--' . $token;
            }
        }

        sort($unused);
        $this->assertSame([], $unused,
            "these dark tokens are defined but nothing reads them, so the class still renders its light value:\n  "
            . implode("\n  ", $unused));
    }

    /**
     * Every hover variant used in a resident view must be remapped somewhere.
     * Checked separately from the token test because the neutral hovers
     * (slate/gray) are handled by direct rules rather than tokens.
     */
    public function testEveryHoverTextVariantIsRemappedForDarkMode(): void
    {
        $root  = \dirname(__DIR__, 2);
        $used  = [];

        foreach (['/app/views/resident', '/app/views/layouts', '/app/views/auth'] as $dir) {
            if (!is_dir($root . $dir)) { continue; }
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . $dir)) as $file) {
                if (!$file->isFile() || !str_ends_with($file->getFilename(), '.php')) { continue; }
                $source = (string) file_get_contents($file->getPathname());
                if (preg_match_all('~\bhover:text-([a-z]+)-(\d{3})\b~', $source, $m, PREG_SET_ORDER)) {
                    foreach ($m as $hit) {
                        // Pale shades stay legible on a dark surface untouched.
                        if ((int) $hit[2] < 500) { continue; }
                        $used['hover:text-' . $hit[1] . '-' . $hit[2]] = true;
                    }
                }
            }
        }

        $this->assertNotEmpty($used, 'no hover text variants found — the scan is broken, not the CSS');

        $missing = [];
        foreach (array_keys($used) as $class) {
            // Tailwind escapes the colon in the selector: .hover\:text-blue-900
            $escaped = str_replace(':', '\\:', $class);
            if (!preg_match('~\.' . preg_quote($escaped, '~') . ':hover~', self::$css)) {
                $missing[] = $class;
            }
        }

        sort($missing);
        $this->assertSame([], $missing,
            "these hover colours are never remapped, so the element changes to its light value under the pointer:\n  "
            . implode("\n  ", $missing));
    }

    /**
     * Post content must never carry its own colours. The purifier's whitelist
     * names allowed attributes explicitly, so `style` is dropped — a caption
     * pasted from Facebook brings inline colours with it, and a hardcoded
     * near-black on the dark card is unreachable by any stylesheet.
     */
    public function testThePurifierWhitelistAdmitsNoStyleAttribute(): void
    {
        // One shared configuration, used by the admin form, the import path
        // and the sample-content seeder alike.
        $source = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/app/services/PostHtml.php'
        );

        $this->assertSame(1, preg_match("~ALLOWED\s*=\s*\n?\s*'([^']+)'~", $source, $m),
            'the allowed-tag whitelist could not be found in PostHtml');

        $this->assertStringNotContainsString('style', $m[1],
            'style is allowed through the purifier — pasted colours would survive and break dark mode');
    }
}
