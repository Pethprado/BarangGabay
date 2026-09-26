<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * tokens.css is now the single source of colour, shape and motion for the
 * login page, the resident side and the admin panel together. That makes it
 * a much better place to put a value — and a much worse place to get one
 * wrong, because a mistake reaches all three at once.
 *
 * These are the rules the file promises in its own header, enforced:
 *
 *   1. Every colour is declared in the light :root first. A colour that
 *      exists only inside a dark block is missing from light mode entirely.
 *   2. The two dark blocks — OS preference and explicit toggle — are
 *      separate copies and must stay identical, or a reader who never
 *      touched the toggle sees a different page from one who did.
 *   3. Text colours clear 4.5:1 on the surface they are used against.
 *   4. A fill that carries white text does NOT lighten in dark mode. This
 *      is the rule one overloaded token used to break: --brand-primary was
 *      both the link colour and the button fill, so making links readable
 *      on a dark card dragged every solid button down to 2.44:1.
 *
 * @see public/assets/css/tokens.css
 */
final class DesignTokensTest extends TestCase
{
    private static string $css;

    public static function setUpBeforeClass(): void
    {
        self::$css = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/public/assets/css/tokens.css'
        );
    }

    /** @return array<string,string> custom property => value, from one block */
    private function tokensIn(string $block): array
    {
        $out = [];
        if (preg_match_all('~--([a-z0-9-]+)\s*:\s*([^;]+);~i', $block, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) { $out[$hit[1]] = trim($hit[2]); }
        }
        return $out;
    }

    private function block(string $pattern): string
    {
        $this->assertSame(1, preg_match($pattern, self::$css, $m), 'block not found: ' . $pattern);
        return $m[1];
    }

    private function light(): array
    {
        return $this->tokensIn($this->block('~^:root \{(.*?)\n\}~ms'));
    }

    private function darkToggle(): array
    {
        return $this->tokensIn($this->block('~:root\[data-theme="dark"\] \{(.*?)\n\}~s'));
    }

    private function darkOs(): array
    {
        return $this->tokensIn($this->block(
            '~@media \(prefers-color-scheme: dark\) \{\s*:root:not\(\[data-theme="light"\]\) \{(.*?)\n    \}~s'
        ));
    }

    /** Relative luminance, per WCAG 2.1. */
    private function luminance(string $hex): float
    {
        $hex = ltrim(trim($hex), '#');
        if (\strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $channel = static function (int $v): float {
            $s = $v / 255;
            return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel((int) hexdec(substr($hex, 0, 2)))
             + 0.7152 * $channel((int) hexdec(substr($hex, 2, 2)))
             + 0.0722 * $channel((int) hexdec(substr($hex, 4, 2)));
    }

    private function contrast(string $a, string $b): float
    {
        $la = $this->luminance($a);
        $lb = $this->luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    public function testNoColourExistsOnlyInADarkBlock(): void
    {
        $light = $this->light();
        $this->assertNotEmpty($light, 'the light :root defines nothing — the scan is broken, not the CSS');

        $orphans = array_diff(array_keys($this->darkToggle()), array_keys($light));

        $this->assertSame(
            [], array_values($orphans),
            'declared only in dark, so light mode has no value at all: ' . implode(', ', $orphans)
        );
    }

    public function testBothDarkPathsAgreeExactly(): void
    {
        $toggle = $this->darkToggle();
        $osDark = $this->darkOs();

        $this->assertNotEmpty($osDark, 'the OS dark block defines nothing');

        $this->assertSame(
            [], array_values(array_diff(array_keys($toggle), array_keys($osDark))),
            'overridden for the toggle but not for the OS default'
        );
        $this->assertSame(
            [], array_values(array_diff(array_keys($osDark), array_keys($toggle))),
            'overridden for the OS default but not for the toggle'
        );

        foreach ($toggle as $name => $value) {
            $this->assertSame(
                $value, $osDark[$name],
                "--{$name} differs between the two dark paths, so the toggle and the OS default render differently"
            );
        }
    }

    public function testEveryHexIsWellFormed(): void
    {
        // A Devanagari digit once got typed into a hex value here. It looks
        // almost exactly like the ASCII one and silently voids the property.
        preg_match_all('~--[a-z0-9-]+\s*:\s*([^;]*#[^;]+);~i', self::$css, $m);

        foreach ($m[1] as $value) {
            preg_match_all('~#[^\s,)]+~u', $value, $hexes);
            foreach ($hexes[0] as $hex) {
                $this->assertMatchesRegularExpression(
                    '~^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$~',
                    $hex,
                    "not a valid hex colour: {$hex}"
                );
            }
        }
    }

    /**
     * @dataProvider textOnSurface
     */
    public function testTextClearsAaOnItsSurface(string $theme, string $text, string $surface): void
    {
        $tokens = $theme === 'light'
            ? $this->light()
            : array_merge($this->light(), $this->darkToggle());

        $this->assertArrayHasKey($text, $tokens, "--{$text} is not defined");
        $this->assertArrayHasKey($surface, $tokens, "--{$surface} is not defined");

        $ratio = $this->contrast($tokens[$text], $tokens[$surface]);

        $this->assertGreaterThanOrEqual(
            4.5,
            round($ratio, 2),
            sprintf('%s: --%s on --%s is %.2f:1, below the 4.5:1 floor for body text',
                $theme, $text, $surface, $ratio)
        );
    }

    public static function textOnSurface(): array
    {
        $cases = [];
        foreach (['light', 'dark'] as $theme) {
            foreach (['text-primary', 'text-secondary', 'text-muted'] as $text) {
                foreach (['surface-card', 'surface-input'] as $surface) {
                    $cases["{$theme} --{$text} on --{$surface}"] = [$theme, $text, $surface];
                }
            }
        }
        return $cases;
    }

    /**
     * The rule that the overloaded token broke.
     *
     * --action-solid is a fill that carries white text, so it must hold its
     * value across themes. --brand-primary is text, so it is free to lighten
     * — and does, which is exactly why the two cannot be the same token.
     */
    public function testActionFillDoesNotLightenInDarkMode(): void
    {
        foreach (['action-solid', 'action-solid-hover', 'text-on-action'] as $name) {
            $this->assertArrayHasKey($name, $this->light(), "--{$name} is not defined");
            $this->assertArrayNotHasKey(
                $name,
                $this->darkToggle(),
                "--{$name} is overridden in dark mode. It is a fill carrying white text; "
                . 'lightening it is what dropped every solid button to 2.44:1.'
            );
        }
    }

    public function testWhiteLabelIsReadableOnEveryActionFill(): void
    {
        $light = $this->light();

        foreach (['action-solid', 'action-solid-hover'] as $fill) {
            $ratio = $this->contrast($light['text-on-action'], $light[$fill]);
            $this->assertGreaterThanOrEqual(
                4.5,
                round($ratio, 2),
                sprintf('--text-on-action on --%s is %.2f:1', $fill, $ratio)
            );
        }
    }
}
