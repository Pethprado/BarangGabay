<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Every literal phrase passed to __() must exist in all three catalogs.
 *
 * A phrase missing from lang/phrases/en.php or fil.php is shown exactly as
 * typed — which is how the EN button used to show Filipino and the FIL
 * button English. A phrase missing from msm.php falls back to a runtime
 * guess. This test names the phrase and the file so the fix is obvious:
 * add it to lang/phrases/en.php and fil.php, then run
 * `php tools/generate-manobo-ui.php`.
 */
final class PhraseCatalogTest extends TestCase
{
    /** @return array<string,list<string>> phrase => where it is used */
    private static function phrasesInCode(): array
    {
        $root  = dirname(__DIR__, 2);
        $found = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app'));

        foreach ($files as $file) {
            $path = (string) $file;
            if (!str_ends_with($path, '.php')) {
                continue;
            }
            $tokens = array_values(array_filter(
                token_get_all((string) file_get_contents($path)),
                static fn($t) => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
            ));
            foreach ($tokens as $i => $token) {
                if (!is_array($token) || $token[0] !== T_STRING || $token[1] !== '__' || ($tokens[$i + 1] ?? null) !== '(') {
                    continue;
                }
                $arg = $tokens[$i + 2] ?? null;
                if (!is_array($arg) || $arg[0] !== T_CONSTANT_ENCAPSED_STRING) {
                    continue;   // a variable: checked where its values are listed
                }
                $raw  = substr($arg[1], 1, -1);
                $text = $arg[1][0] === "'" ? str_replace(["\\'", '\\\\'], ["'", '\\'], $raw) : stripcslashes($raw);
                $found[phrase_key($text)][] = substr($path, strlen($root) + 1) . ':' . $arg[2];
            }
        }

        return $found;
    }

    public function testEveryPhraseHasAnEnglishFilipinoAndManoboForm(): void
    {
        $catalogs = [];
        foreach (['en', 'fil', 'msm'] as $locale) {
            $catalogs[$locale] = require dirname(__DIR__, 2) . "/lang/phrases/{$locale}.php";
        }

        $phrases = self::phrasesInCode();
        $this->assertGreaterThan(500, count($phrases), 'The scan found too few phrases — it is broken, not the code');

        $missing = [];
        foreach ($phrases as $phrase => $where) {
            foreach ($catalogs as $locale => $catalog) {
                if (!array_key_exists($phrase, $catalog)) {
                    $missing[] = "[{$locale}] \"{$phrase}\" (" . $where[0] . ')';
                }
            }
        }

        $this->assertSame([], $missing, "Phrases with no translation:\n  " . implode("\n  ", $missing));
    }
}
