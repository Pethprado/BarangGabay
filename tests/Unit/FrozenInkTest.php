<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * A hardcoded text colour in a view cannot follow the theme.
 *
 *   style="color:#4a5568"
 *
 * reads fine on a white card and is still exactly that grey when the card
 * turns near-black — 2.06:1, which is the "some words can't be seen"
 * report. Nothing in the stylesheets can rescue it: an inline style beats
 * every rule short of !important, and no rule names it anyway.
 *
 * The test MEASURES rather than forbids. Hardcoding a colour is not wrong
 * by itself — white on a brand-blue button is hardcoded and correct, and
 * stays correct in both themes because the button is hardcoded too. What is
 * wrong is a fixed ink on a surface that moves underneath it. So: any fixed
 * ink whose element does not also fix its background has to stay legible on
 * BOTH the light card and the dark card, because it will meet both.
 */
final class FrozenInkTest extends TestCase
{
    /** Colours that carry no lightness of their own. */
    private const ALWAYS_SAFE = ['transparent', 'inherit', 'currentcolor', 'initial', 'unset'];

    private const LIGHT_CARD = '#ffffff';
    private const DARK_CARD  = '#131b2c';

    /**
     * Pages that have not been converted to the shared tokens yet.
     *
     * register.php carries 47 hardcoded colours and no theme handling at
     * all — it pins its own panel to #fff, so its dark inks are correct
     * *for that page* and wrong for the system. Listing it here is not a
     * pardon: it is the debt, written down, so the failure this test exists
     * to catch is not drowned out by work that is simply not done yet.
     *
     * Delete the entry when the page is converted. Do not add to this list
     * to silence a new failure.
     */
    private const NOT_YET_THEMED = [
        'auth/register.php',
    ];

    /** @return array<string,string> relative path => source */
    private function views(): array
    {
        $root  = \dirname(__DIR__, 2) . '/app/views';
        $found = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.php')) { continue; }
            $name = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if (\in_array($name, self::NOT_YET_THEMED, true)) { continue; }

            $found[$name] = (string) file_get_contents($file->getPathname());
        }
        ksort($found);
        return $found;
    }

    /** @return array{0:int,1:int,2:int}|null */
    private function rgb(string $hex): ?array
    {
        $hex = ltrim(strtolower(trim($hex)), '#');
        if (\strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('~^[0-9a-f]{6}$~', $hex)) { return null; }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    private function luminance(array $rgb): float
    {
        $channel = static function (int $v): float {
            $s = $v / 255;
            return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        };
        return 0.2126 * $channel($rgb[0]) + 0.7152 * $channel($rgb[1]) + 0.0722 * $channel($rgb[2]);
    }

    private function contrast(string $a, string $b): ?float
    {
        $ra = $this->rgb($a);
        $rb = $this->rgb($b);
        if ($ra === null || $rb === null) { return null; }

        $la = $this->luminance($ra);
        $lb = $this->luminance($rb);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * Does this declaration text pin a background that cannot move with the
     * theme? A literal counts, and so does a token with no dark override —
     * --action-solid is #1652f0 in both themes precisely so that a white
     * label stays readable on it.
     */
    private function hasFixedBackground(string $text, array $constantTokens): bool
    {
        if (preg_match('~background[a-z-]*\s*:[^;]*(#[0-9a-f]{3,8}|rgba?\(|hsla?\(|gradient)~i', $text)) {
            return true;
        }
        if (preg_match_all('~background[a-z-]*\s*:[^;]*?var\(\s*--([a-z0-9-]+)~i', $text, $m)) {
            foreach ($m[1] as $token) {
                if (\in_array($token, $constantTokens, true)) { return true; }
            }
        }
        return false;
    }

    /**
     * Token names declared for light with no dark override, so they render
     * identically in both themes.
     *
     * Read from tokens.css rather than listed here: the day one of them is
     * given a dark value, every fixed ink sitting on it starts failing this
     * test instead of quietly turning grey-on-pale.
     *
     * @return list<string>
     */
    private function constantTokens(): array
    {
        $css = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/public/assets/css/tokens.css'
        );

        $names = static function (string $block): array {
            preg_match_all('~--([a-z0-9-]+)\s*:~i', $block, $m);
            return array_unique($m[1]);
        };

        $light = preg_match('~^:root \{(.*?)\n\}~ms', $css, $m) ? $names($m[1]) : [];
        $dark  = preg_match('~:root\[data-theme="dark"\] \{(.*?)\n\}~s', $css, $m) ? $names($m[1]) : [];

        $this->assertNotEmpty($light, 'tokens.css declares no light tokens — the scan is broken');

        return array_values(array_diff($light, $dark));
    }

    /**
     * @param list<array{0:string,1:string}> $sites  [where, colour]
     * @return list<string>
     */
    private function failures(array $sites): array
    {
        $out = [];
        foreach ($sites as [$where, $colour]) {
            $onLight = $this->contrast($colour, self::LIGHT_CARD);
            $onDark  = $this->contrast($colour, self::DARK_CARD);
            if ($onLight === null || $onDark === null) { continue; }

            if ($onLight >= 4.5 && $onDark >= 4.5) { continue; }

            /* Legible on the dark card but not the light one: this ink was
               written for a dark surface — a brand panel, a coloured banner
               — which its ancestor paints and which does not move between
               themes. White headings on the login's indigo panel are the
               common case. CSS alone cannot see that ancestor, so the
               asymmetry is the evidence: an ink that only works on dark is
               not an ink that light mode broke.

               The reverse asymmetry is the bug being hunted: readable on
               white, unreadable on the dark card, because it was written
               when there was only one theme. */
            if ($onDark >= 4.5 && $onLight < 4.5) { continue; }

            $out[] = sprintf(
                '%s  color:%s — %.2f:1 on the light card, %.2f:1 on the dark card',
                $where, $colour, $onLight, $onDark
            );
        }
        return $out;
    }

    /*
     * There is deliberately NO equivalent test for inline style="color:…"
     * attributes.
     *
     * An inline ink is judged by the surface behind it, and that surface is
     * usually painted by an ancestor element:
     *
     *     <div style="background:#fff5f5">        <- fixed light, on purpose
     *       <p style="color:#dc3545">…</p>        <- correct, and frozen too
     *     </div>
     *
     * Reading the file cannot tell that apart from the same <p> sitting on a
     * themed card, where the identical colour is a bug. Deciding it needs the
     * DOM, not the source. A version of this test that ignored ancestry
     * reported 55 sites, nearly all of them correct code — and a check that
     * cries wolf gets muted, which is worse than not having it.
     *
     * The rendered-page audit in the scratchpad walks the real DOM and does
     * decide this correctly; it is how the resident dashboard was cleared.
     * If it is ever worth automating, it belongs in an integration test with
     * a booted app, not here.
     */

    public function testStyleBlockInkSurvivesBothThemes(): void
    {
        $constant = $this->constantTokens();
        $sites    = [];

        foreach ($this->views() as $name => $src) {
            if (!preg_match_all('~<style>(.*?)</style>~s', $src, $m, PREG_OFFSET_CAPTURE)) { continue; }

            foreach ($m[1] as [$block, $blockOffset]) {
                // Comments in these blocks discuss colours in prose.
                $block = (string) preg_replace('#/\*.*?\*/#s', '', $block);

                /* Rule by rule, so "does this rule also set a background?" is
                   asked of the rule that actually sets the colour, not of a
                   window that reached into its neighbours. */
                if (!preg_match_all('~([^{}]*)\{([^{}]*)\}~', $block, $rules, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                    continue;
                }

                /* Selectors this block already re-colours for dark mode.
                   A page that writes
                       .dz-title { color:#991b1b; }
                       :root[data-theme="dark"] .dz-title { color:#fca5a5; }
                   has handled itself, and flagging the light half would be
                   reporting the fix as the fault. */
                /* Selectors are compared with their internal whitespace
                   collapsed: ".bk-bad  .bk-body" and ".bk-bad .bk-body" are
                   the same selector, and matching them as raw strings made
                   a handled rule look unhandled. */
                $normalise = static fn (string $s): string =>
                    trim((string) preg_replace('/\s+/', ' ', $s));

                $handled = [];
                foreach ($rules as $rule) {
                    if (!preg_match('~(?<![\w-])color\s*:~i', $rule[2][0])) { continue; }
                    foreach (array_map('trim', explode(',', $rule[1][0])) as $selector) {
                        if (!str_contains($selector, 'data-theme="dark"')
                            && !str_contains($selector, 'prefers-color-scheme')) {
                            continue;
                        }
                        $bare = $normalise((string) preg_replace(
                            '~:root(\[data-theme="dark"\]|:not\(\[data-theme="light"\]\))\s*~',
                            '',
                            $selector
                        ));
                        if ($bare !== '') { $handled[$bare] = true; }
                    }
                }

                foreach ($rules as $rule) {
                    [$body, $bodyAt] = $rule[2];

                    if (!preg_match('~(?<![\w-])color\s*:\s*(#[0-9a-f]{3,8})~i', $body, $cm)) { continue; }
                    if ($this->hasFixedBackground($body, $constant)) { continue; }

                    $covered = false;
                    foreach (explode(',', $rule[1][0]) as $selector) {
                        if (isset($handled[$normalise($selector)])) { $covered = true; break; }
                    }
                    if ($covered) { continue; }

                    $line    = substr_count(substr($src, 0, $blockOffset + $bodyAt), "\n") + 1;
                    $sites[] = ["{$name}:{$line}", strtolower($cm[1])];
                }
            }
        }

        $bad = $this->failures($sites);
        $this->assertSame(
            [], $bad,
            "fixed text colours in a view's <style> block that do not survive both themes:\n  "
            . implode("\n  ", $bad)
        );
    }
}
