<?php
declare(strict_types=1);

/**
 * Generate the Manobo (MN) form of every interface string.
 *
 *   php tools/generate-manobo-ui.php            # translate new/changed strings
 *   php tools/generate-manobo-ui.php --offline  # no machine translation calls
 *
 * Reads every English string in lang/en.php (t() keys) and lang/phrases/en.php
 * (__() phrases) and writes:
 *
 *   lang/msm_auto.php       dot key  => MN text   (read by t())
 *   lang/phrases/msm.php    phrase   => MN text   (read by __())
 *
 * How each string is made — Manobo wherever the barangay dataset has it,
 * Bisaya everywhere else, never English or Tagalog left in place:
 *
 *   1. The whole label is a Manobo dataset entry → that entry.
 *   2. Otherwise the English becomes Bisaya (Cebuano) — hand-written in
 *      data/manobo/ui_bisaya_overrides.json if it is there, else machine
 *      translated (Google's free endpoint, MyMemory as backup) — and every Bisaya word the
 *      Manobo dataset knows is then swapped for its Manobo form
 *      (ManoboHybridTranslator::refineBisayaWithManobo, the same step the
 *      post translator uses).
 *   3. With no machine translation available, the local hybrid translator
 *      (Manobo dataset + Bisaya dictionary) is used instead.
 *
 * Placeholders (":name"), HTML entities and tags are kept out of the machine
 * translator and must all come back, or the string is retried locally.
 *
 * The expensive machine step is cached in data/manobo/ui_bisaya_cache.json
 * (English → Bisaya), so a re-run only translates strings that are new. The
 * Manobo swap is redone on every run, so words added at /admin/manobo flow
 * into the interface the next time this runs (and immediately for whole
 * labels — see manobo_ui_text() in app/helpers.php).
 *
 * Run it after adding interface text, then commit the two generated files.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Services\ManoboHybridTranslator;

$offline   = in_array('--offline', $argv, true);
$root      = dirname(__DIR__);
$cachePath = $root . '/data/manobo/ui_bisaya_cache.json';
$cache     = is_file($cachePath) ? (json_decode((string) file_get_contents($cachePath), true) ?: []) : [];

// Hand-written Bisaya for strings the machine translator handed back in
// English ("Edit", "Sign Out"). Wins over the machine cache.
$overridePath = $root . '/data/manobo/ui_bisaya_overrides.json';
$overrides    = is_file($overridePath) ? (json_decode((string) file_get_contents($overridePath), true) ?: []) : [];
unset($overrides['_comment']);

/** @return array<string,string> */
function flatten_lang(array $arr, string $prefix = ''): array
{
    $out = [];
    foreach ($arr as $k => $v) {
        $key = $prefix === '' ? (string) $k : $prefix . '.' . $k;
        if (is_array($v)) {
            $out += flatten_lang($v, $key);
        } elseif (is_string($v)) {
            $out[$key] = $v;
        }
    }
    return $out;
}

/**
 * Swap placeholders, entities and tags for {n} tokens the translator leaves alone.
 *
 * @return array{0:string,1:list<string>}
 */
function mask(string $text): array
{
    $kept = [];
    $masked = preg_replace_callback(
        '/<[^>]+>|&[a-z#0-9]+;|:[a-z_]+|https?:\/\/\S+|[\w.+-]+@[\w-]+\.[\w.]+/i',
        function (array $m) use (&$kept): string {
            $kept[] = $m[0];
            return '{' . (count($kept) - 1) . '}';
        },
        $text
    ) ?? $text;

    return [$masked, $kept];
}

/** Put tokens back. Null when the translator lost or mangled one. */
function unmask(string $text, array $kept): ?string
{
    // Translators sometimes write "{ 0 }" or "{0 }".
    $text = preg_replace('/\{\s*(\d+)\s*\}/', '{$1}', $text) ?? $text;
    foreach ($kept as $i => $original) {
        if (!str_contains($text, '{' . $i . '}')) {
            return null;
        }
        $text = str_replace('{' . $i . '}', $original, $text);
    }

    return preg_match('/\{\d+\}/', $text) ? null : $text;
}

/**
 * English → Bisaya for many strings at once through Google's free endpoint.
 * Strings are sent newline-joined in batches; a batch whose line count does
 * not come back intact is dropped (those strings fall back to MyMemory).
 *
 * @param  list<string> $texts
 * @return array<string,string>
 */
function google_batch(array $texts): array
{
    $client = new \GuzzleHttp\Client(['timeout' => 30, 'http_errors' => false]);
    $out    = [];
    $batch  = [];
    $size   = 0;

    $flush = function () use (&$batch, &$size, &$out, $client): void {
        if (!$batch) {
            return;
        }
        try {
            $res = $client->post('https://translate.googleapis.com/translate_a/single', [
                'query'       => ['client' => 'gtx', 'sl' => 'en', 'tl' => 'ceb', 'dt' => 't'],
                'form_params' => ['q' => implode("\n", $batch)],
            ]);
            $data = json_decode((string) $res->getBody(), true);
            if ($res->getStatusCode() === 200 && is_array($data[0] ?? null)) {
                $joined = '';
                foreach ($data[0] as $segment) {
                    $joined .= (string) ($segment[0] ?? '');
                }
                $lines = explode("\n", rtrim($joined, "\n"));
                if (count($lines) === count($batch)) {
                    foreach ($batch as $i => $src) {
                        $out[$src] = trim($lines[$i]);
                    }
                } else {
                    fwrite(STDERR, "  batch of " . count($batch) . " came back with " . count($lines) . " lines; skipped\n");
                }
            } else {
                fwrite(STDERR, "  Google HTTP " . $res->getStatusCode() . "\n");
            }
        } catch (\Throwable $e) {
            fwrite(STDERR, '  Google failed: ' . $e->getMessage() . "\n");
        }
        $batch = [];
        $size  = 0;
        usleep(400000);
    };

    foreach ($texts as $text) {
        if ($size + strlen($text) > 3500 || count($batch) >= 60) {
            $flush();
        }
        $batch[] = $text;
        $size   += strlen($text) + 1;
    }
    $flush();

    return $out;
}

/** One string through MyMemory, the backup provider. */
function mymemory_one(string $text): ?string
{
    try {
        $client = new \GuzzleHttp\Client(['timeout' => 10, 'http_errors' => false]);
        $res = $client->get('https://api.mymemory.translated.net/get', [
            'query' => ['q' => $text, 'langpair' => 'en|ceb', 'mt' => '1']
                + ((string) env('MYMEMORY_EMAIL', '') !== '' ? ['de' => (string) env('MYMEMORY_EMAIL')] : []),
        ]);
        $data = json_decode((string) $res->getBody(), true);
        $out  = trim((string) ($data['responseData']['translatedText'] ?? ''));
        if ($out === '' || (int) ($data['responseStatus'] ?? 0) !== 200
            || preg_match('/MYMEMORY WARNING|QUERY LENGTH LIMIT|DAILY LIMIT/i', $out)) {
            return null;
        }
        return $out;
    } catch (\Throwable) {
        return null;
    }
}

// ── 1. Collect every English interface string ──────────────────────────────
$keyStrings    = flatten_lang(require $root . '/lang/en.php');
$filKeyStrings = flatten_lang(require $root . '/lang/fil.php');
$phraseEn      = is_file($root . '/lang/phrases/en.php') ? require $root . '/lang/phrases/en.php' : [];
$phraseFil     = is_file($root . '/lang/phrases/fil.php') ? require $root . '/lang/phrases/fil.php' : [];

/** @var array<string,array{en:string,fil:?string}> $jobs  id => sources */
$jobs = [];
foreach ($keyStrings as $key => $english) {
    $jobs['k:' . $key] = ['en' => $english, 'fil' => $filKeyStrings[$key] ?? null];
}
foreach ($phraseEn as $phrase => $english) {
    $jobs['p:' . $phrase] = ['en' => (string) $english, 'fil' => $phraseFil[$phrase] ?? null];
}
echo count($jobs), " interface strings\n";

// ── 2. Machine Bisaya for every masked English string not cached yet ───────
$maskedOf = [];
$need     = [];
foreach ($jobs as $id => $src) {
    [$masked] = mask($src['en']);
    $maskedOf[$id] = $masked;
    if (preg_match('/\p{L}{2,}/u', $masked) && !isset($cache[$masked]) && !isset($overrides[$masked])) {
        $need[$masked] = true;
    }
}
echo count($need), " need machine translation\n";

if (!$offline && $need) {
    $got = google_batch(array_keys($need));
    foreach ($got as $src => $ceb) {
        $cache[$src] = $ceb;
    }
    $left = array_diff_key($need, $got);
    echo count($got), " from Google, ", count($left), " left for MyMemory\n";
    foreach (array_keys($left) as $src) {
        $ceb = mymemory_one($src);
        if ($ceb !== null) {
            $cache[$src] = $ceb;
        }
        usleep(300000);
    }
    ksort($cache, SORT_STRING);
    file_put_contents($cachePath, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
}

// ── 3. Manobo first, Bisaya for the rest ───────────────────────────────────
$translator  = new ManoboHybridTranslator();
$manoboIndex = $translator->loadManoboIndex();
ManoboHybridTranslator::$machineDeadline = 0.0;   // local dictionaries only from here on

$stats   = ['manobo_whole' => 0, 'bisaya_refined' => 0, 'local' => 0, 'kept' => 0];
$keysOut = [];
$phrOut  = [];

foreach ($jobs as $id => $src) {
    $english = $src['en'];
    [$masked, $kept] = mask($english);
    $result = null;

    if (!preg_match('/\p{L}{2,}/u', $masked)) {
        $result = $english;                      // nothing to translate: "#", "₱:amount"
        $stats['kept']++;
    }

    if ($result === null) {
        foreach ([$english, $src['fil']] as $candidate) {
            if ($candidate !== null && ($whole = manobo_word($candidate)) !== null) {
                $result = $whole;
                $stats['manobo_whole']++;
                break;
            }
        }
    }

    $bisaya = $overrides[$masked] ?? $cache[$masked] ?? '';
    if ($result === null && $bisaya !== '') {
        $refined = $translator->refineBisayaWithManobo($bisaya, $manoboIndex);
        $result  = unmask($refined['text'], $kept);
        if ($result !== null) {
            $stats['bisaya_refined']++;
        }
    }

    if ($result === null) {
        try {
            $local  = $translator->translate($english, 'en', ['force_refresh' => true]);
            $result = trim((string) ($local['translation'] ?? '')) ?: null;
        } catch (\Throwable) {
            $result = null;
        }
        $stats['local']++;
    }

    if ($result === null) {
        continue;
    }

    if (str_starts_with($id, 'k:')) {
        $keysOut[substr($id, 2)] = $result;
    } else {
        $phrOut[substr($id, 2)] = $result;
    }
}

// ── 4. Write ───────────────────────────────────────────────────────────────
$header = static fn(string $what): string => "<?php\ndeclare(strict_types=1);\n\n"
    . "/**\n * GENERATED by tools/generate-manobo-ui.php — do not edit by hand.\n *\n"
    . " * Manobo (MN) form of every {$what}: Manobo from the barangay dataset\n"
    . " * wherever it has the word, Bisaya everywhere else. To change a word, edit\n"
    . " * it at /admin/manobo (or /admin/bisaya) and re-run the generator.\n */\nreturn [\n";

$write = static function (string $path, array $map, string $what) use ($header): void {
    ksort($map, SORT_STRING);
    $body = '';
    foreach ($map as $k => $v) {
        $body .= '    ' . var_export((string) $k, true) . ' => ' . var_export($v, true) . ",\n";
    }
    file_put_contents($path, $header($what) . $body . "];\n");
};

$write($root . '/lang/msm_auto.php', $keysOut, 'interface string in lang/en.php');
$write($root . '/lang/phrases/msm.php', $phrOut, 'phrase in lang/phrases/en.php');

printf(
    "wrote %d keys + %d phrases  (whole Manobo: %d, Bisaya+Manobo: %d, local fallback: %d, unchanged: %d)\n",
    count($keysOut), count($phrOut),
    $stats['manobo_whole'], $stats['bisaya_refined'], $stats['local'], $stats['kept']
);
