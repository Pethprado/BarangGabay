<?php
declare(strict_types=1);

if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }
        $val = getenv($key);
        return $val !== false ? $val : $default;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function check_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals(csrf_token(), $token)) {
            http_response_code(403);
            echo '<h1>Invalid CSRF token</h1>';
            exit;
        }
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function get_base_url(): string
{
    $base = rtrim($_ENV['APP_URL'] ?? '', '/');
    if ($base !== '') {
        return $base;
    }

    // Detect HTTPS even when behind a reverse proxy (Render, Nginx, etc.)
    // that terminates TLS and forwards X-Forwarded-Proto.
    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['HTTP_X_FORWARDED_SSL']   ?? '') === 'on');

    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptPath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if ($scriptPath === '/' || $scriptPath === '\\') {
        $scriptPath = '';
    }

    return $scheme . '://' . $host . rtrim($scriptPath, '/');
}


function app_url(string $path = ''): string
{
    $base = rtrim(get_base_url(), '/');
    $path = ltrim($path, '/');
    return $base . ($path !== '' ? '/' . $path : '');
}

function base_url(): string
{
    return rtrim(get_base_url(), '/') . '/';
}

function asset(string $path): string
{
    return base_url() . ltrim($path, '/');
}

/**
 * An asset URL stamped with the file's own modification time.
 *
 * The layouts used to carry hand-written versions — main.css?v=11,
 * admin.css?v=17 — which meant every CSS change also needed somebody to
 * remember to bump a number. Nobody does, so browsers kept serving the old
 * stylesheet against new markup and the page rendered unstyled. The same
 * file was also pinned at ?v=11 in one layout and ?v=8 in another, so it
 * cached twice under two URLs.
 *
 * Deriving the stamp from filemtime() makes the URL change exactly when the
 * file does: no bump to forget, and nothing re-downloaded when it has not.
 */
function asset_v(string $path): string
{
    $url  = asset($path);
    $file = __DIR__ . '/../public/' . ltrim($path, '/');

    $stamp = is_file($file) ? filemtime($file) : false;

    return $stamp === false ? $url : $url . '?v=' . $stamp;
}

function route(string $path = ''): string
{
    return app_url($path);
}

function redirect(string $path): void
{
    if (strpos($path, '://') === false) {
        $path = app_url($path);
    }
    header('Location: ' . $path);
    exit;
}

function view(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require __DIR__ . '/views/' . $template . '.php';
}

function old(string $key, $default = '')
{
    if (isset($_POST[$key])) {
        return $_POST[$key];
    }
    if (isset($_SESSION['_old'][$key])) {
        return $_SESSION['_old'][$key];
    }
    return $default;
}

/**
 * Longest headline a post may carry, matching the title columns.
 *
 * There is deliberately no matching limit for a post's BODY: those columns are
 * LONGTEXT and nothing in the save path truncates them, so an announcement can
 * be as long as it needs to be. A title is different only because it is a
 * headline rendered in tables, cards and notification text, and because the
 * column has to be some finite width.
 *
 * Defined once, here, because three controllers and a migration all have to
 * agree on it. If this changes, database/migrations/023 changes with it —
 * otherwise the check passes and the database then truncates or throws.
 */
function post_title_limit(): int
{
    return 500;
}

/**
 * A URL-safe slug, capped so it always fits its column and never empty.
 *
 * ── Why the transliteration step exists ──────────────────────────────────
 *
 * Staff copy titles out of Facebook, and Facebook posts are full of styled
 * Unicode — "𝐂𝐞𝐥𝐞𝐛𝐫𝐚𝐭𝐢𝐧𝐠 𝐂𝐮𝐥𝐭𝐮𝐫𝐞" is not the letters C-e-l-e-b, it is the
 * mathematical-bold block at U+1D400. The old rule matched [a-z0-9] only, so
 * every one of those characters became a separator, the whole slug collapsed
 * to dashes, and trim() left an empty string. An empty slug means the post's
 * URL is /announcements/ — the link on the resident list simply does not open.
 * A published notice nobody can click is the worst failure this app has.
 *
 * iconv //TRANSLIT maps that block back to ASCII (and é→e, ñ→n with it). It is
 * platform-dependent, so its output is checked rather than trusted, and there
 * is a final backstop: a slug is NEVER returned empty. A title written wholly
 * in a non-Latin script — or in emoji — still gets a stable, working address.
 *
 * The cap is on the SLUG, never on the title. 180 leaves room for the "-2",
 * "-3" suffixes uniqueSlug() appends, and the cut lands on a word boundary.
 */
function generate_slug(string $text, int $maxLength = 180): string
{
    $text = trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    // Styled Unicode and accented Latin down to plain ASCII. Suppressed and
    // verified rather than trusted: iconv's TRANSLIT differs between glibc and
    // Windows, and on some builds it returns false outright.
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    if (is_string($ascii) && $ascii !== '') {
        // Some builds render an untranslatable character as "?" or '?'.
        $text = str_replace(['?', '`', "'", '"'], '', $ascii);
    }

    $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($text)) ?? '';
    $slug = trim($slug, '-');

    if ($slug === '') {
        /*
         * Nothing survived — a title entirely in emoji, or in a script iconv
         * could not transliterate. A derived, stable stub keeps the post
         * reachable, which matters far more than the address being pretty.
         * Hashed from the title so the same title always yields the same slug
         * and re-saving a post does not silently move its URL.
         */
        return 'post-' . substr(sha1($text !== '' ? $text : 'untitled'), 0, 10);
    }

    if (strlen($slug) <= $maxLength) {
        return $slug;
    }

    $slug     = substr($slug, 0, $maxLength);
    $lastDash = strrpos($slug, '-');

    // Only back up to the previous word if that leaves a usable slug.
    if ($lastDash !== false && $lastDash > $maxLength / 2) {
        $slug = substr($slug, 0, $lastDash);
    }

    return trim($slug, '-');
}

function flash(string $key, $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

/**
 * Read a system setting (see app/models/Setting.php).
 *
 * Never throws — falls back to the supplied default, then to the built-in
 * default, so views and bootstrap can call it before migrations have run.
 */
function setting(string $key, mixed $default = null): mixed
{
    return \App\Models\Setting::get($key, $default);
}

/** The configured system name, used wherever "BarangGabay" used to be hardcoded. */
function system_name(): string
{
    $name = (string) setting('system_name', 'BarangGabay');

    return $name !== '' ? $name : 'BarangGabay';
}

/** The configured place name, e.g. "Barangay Bayogo, Madrid, Surigao del Sur". */
function system_location(): string
{
    return (string) setting('location_full', 'Barangay Bayogo, Madrid, Surigao del Sur');
}

/**
 * The sub-areas a resident can say they live in.
 *
 * Barangay Bayogo has seven puroks, confirmed by the barangay itself. This
 * list was deliberately left empty during the relocation from the system's
 * previous location rather than carrying over that location's Zone 1–Zone 6,
 * because inventing subdivisions for a real barangay puts fake place names on
 * a government registration form.
 *
 * One list, read by the registration form, the profile page and the SMS
 * recipient filter — so a purok added or renamed here changes everywhere at
 * once and the three can never disagree about what exists.
 *
 * If the barangay later adopts sitio names alongside the numbers, add them
 * here; the form switches between a dropdown and a free-text box on its own
 * depending on whether this is empty.
 *
 * @return list<string>
 */
function barangay_subdivisions(): array
{
    return ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5', 'Purok 6', 'Purok 7'];
}

/**
 * URL of the configured logo, or null when none has been uploaded — callers
 * then fall back to the bundled default asset.
 */
function system_logo_url(): ?string
{
    $logo = trim((string) setting('system_logo', ''));
    if ($logo === '') {
        return null;
    }

    // Stored as a path relative to public/, e.g. "uploads/branding/logo.png".
    $absolute = \dirname(__DIR__) . '/public/' . ltrim($logo, '/');

    return is_file($absolute) ? asset(ltrim($logo, '/')) : null;
}

/**
 * Renders the official BARANGGABAY logo component with responsive sizing and theme support.
 *
 * @param string $variant 'auto' | 'dark' | 'light' | 'icon' | 'full' | 'horizontal'
 * @param array $options [
 *     'size'     => 'small' | 'medium' | 'large' | 'xlarge' | 'hero' | string,
 *     'iconOnly' => bool,
 *     'class'    => string,
 *     'style'    => string,
 *     'alt'      => string,
 *     'href'     => string|null,
 * ]
 * @return string HTML string
 */
function baranggabay_logo(string $variant = 'auto', array $options = []): string
{
    $customUploaded = system_logo_url();
    $size     = $options['size'] ?? 'medium';
    $iconOnly = !empty($options['iconOnly']);
    $class    = $options['class'] ?? '';
    $style    = $options['style'] ?? '';
    $alt      = $options['alt'] ?? system_name();
    $href     = array_key_exists('href', $options) ? $options['href'] : route('');

    $heightMap = [
        'small'  => '38px',
        'medium' => '48px',
        'large'  => '64px',
        'xlarge' => '80px',
        'hero'   => '96px',
    ];
    $height = $heightMap[$size] ?? (is_numeric($size) ? "{$size}px" : $size);

    if ($customUploaded !== null) {
        $src = $customUploaded;
    } else {
        if ($iconOnly || $variant === 'icon') {
            $src = asset('images/logo-icon.svg');
        } elseif ($variant === 'dark') {
            $src = asset('images/logo-dark.svg');
        } elseif ($variant === 'light') {
            $src = asset('images/logo-light.svg');
        } else {
            $lightSrc = asset('images/logo-light.svg');
            $darkSrc  = asset('images/logo-dark.svg');

            $html = sprintf(
                '<span class="baranggabay-logo-wrapper %s" style="display:inline-flex;align-items:center;vertical-align:middle;%s">
                   <img src="%s" alt="%s" class="baranggabay-logo-img logo-light-variant" style="height:%s;width:auto;max-width:100%%;object-fit:contain;" />
                   <img src="%s" alt="%s" class="baranggabay-logo-img logo-dark-variant" style="height:%s;width:auto;max-width:100%%;object-fit:contain;" />
                 </span>',
                e($class), e($style),
                e($lightSrc), e($alt), e($height),
                e($darkSrc), e($alt), e($height)
            );

            if ($href !== null) {
                return sprintf('<a href="%s" class="baranggabay-logo-link" style="text-decoration:none;display:inline-block;">%s</a>', e($href), $html);
            }
            return $html;
        }
    }

    $imgHtml = sprintf(
        '<img src="%s" alt="%s" class="baranggabay-logo-img %s" style="height:%s;width:auto;max-width:100%%;object-fit:contain;display:inline-block;vertical-align:middle;%s" />',
        e($src), e($alt), e($class), e($height), e($style)
    );

    if ($href !== null) {
        return sprintf('<a href="%s" class="baranggabay-logo-link" style="text-decoration:none;display:inline-block;">%s</a>', e($href), $imgHtml);
    }

    return $imgHtml;
}

/**
 * The badge classes for a post's category — one definition, every view.
 *
 * There were three copies of this map (home, the list, the detail page) and
 * they disagreed. Government was indigo on the list and the detail page but
 * blue on the home page, where it collided with infrastructure — so the same
 * post wore a different colour depending on the page a resident arrived
 * through, and two categories were indistinguishable on the busiest page in
 * the app. A colour that means "government" has to mean it everywhere or it
 * means nothing.
 *
 * Every pairing here is checked for contrast in both themes; see the
 * "Tailwind accent remap" block in main.css for how the dark values are
 * derived. Adding a category means adding its colour to BOTH halves of the
 * pair and giving the family a --tw-* token, or the badge silently falls back
 * to a stock Tailwind colour tuned for a white card.
 */
function category_badge_class(string $category): string
{
    return [
        'general'        => 'bg-slate-100 text-slate-700',
        'health'         => 'bg-rose-100 text-rose-700',
        'safety'         => 'bg-orange-100 text-orange-700',
        'government'     => 'bg-indigo-100 text-indigo-700',
        // CHANGED: was bg-blue-100/text-blue-700. theme.css retints generic
        // Tailwind "blue" to the same gold-ink used everywhere else in the
        // app (buttons, links, the "government" badge below) — reusing it
        // here would make infrastructure and government indistinguishable
        // again, which is exactly the bug this file's own header comment
        // says was already fixed once (by giving them different Tailwind
        // hues). Teal is retinted to a separate earthy tone in theme.css.
        'infrastructure' => 'bg-teal-100 text-teal-700',
        'social'         => 'bg-purple-100 text-purple-700',
    ][$category] ?? 'bg-slate-100 text-slate-700';
}

/**
 * Category names in the reader's language.
 *
 * The detail page had these hardcoded in Filipino, so a resident reading in
 * English saw an English post under a badge reading "Pamahalaan".
 *
 * @return array<string,string>
 */
function category_labels(): array
{
    return [
        'general'        => t('categories.general'),
        'health'         => t('categories.health'),
        'safety'         => t('categories.safety'),
        'government'     => t('categories.government'),
        'infrastructure' => t('categories.infrastructure'),
        'social'         => t('categories.social'),
    ];
}

/** Badge classes for an urgency level. Empty for 'normal' — no badge is shown. */
function urgency_badge_class(string $urgency): string
{
    return [
        'urgent'    => 'bg-red-100 text-red-700',
        'important' => 'bg-amber-100 text-amber-700',
    ][$urgency] ?? '';
}

/** The coloured left edge of a card, by urgency. */
function urgency_border_class(string $urgency): string
{
    return [
        'urgent'    => 'border-l-red-500',
        'important' => 'border-l-amber-400',
    ][$urgency] ?? 'border-l-slate-300';
}

function available_locales(): array
{
    return [
        'en'  => 'English',
        'fil' => 'Filipino',
        // Manobo (the msm variant this system uses). Has no hand-written string file —
        // its words come from data/manobo/manobo_dictionary.csv via
        // manobo_word() below, so the UI gains Manobo as the dataset grows.
        'msm' => 'Manobo',
    ];
}

/**
 * Whether a post's Manobo translation was written by a person, generated, or
 * is simply not there.
 *
 * Surfaced in the admin lists because the three cases need different actions
 * and look identical otherwise. "Missing" is the one that matters most: it is
 * the post whose MN button does nothing for residents, and the only fix is
 * somebody typing the Manobo — no amount of API credit changes a blank field
 * into a translation a Manobo speaker would recognise.
 *
 * @param  array<string,mixed> $row       A content row.
 * @param  string              $bodyField 'body' for announcements, otherwise
 *                                        'description'.
 * @return string 'manual' | 'auto' | 'missing'
 */
function manobo_text_state(array $row, string $bodyField = 'body'): string
{
    $title = trim((string) ($row['title_manobo'] ?? ''));
    $body  = trim((string) ($row[$bodyField . '_manobo'] ?? ''));

    if ($title === '' && $body === '') {
        return 'missing';
    }

    return (int) ($row['manobo_is_auto'] ?? 0) === 1 ? 'auto' : 'manual';
}

/**
 * Short code shown on the header language buttons.
 *
 * There is only one Manobo in this system now. The codebase used to carry two
 * — msm (Agusan Manobo, the variety spoken here and the one the dictionary
 * documents) and mbb (Western Bukidnon Manobo, a different language from a
 * different province) — which meant a resident could be shown "MNB" for a
 * language nobody here speaks. See data/manobo/README.md.
 */
function locale_short_code(string $code): string
{
    return $code === 'msm' ? 'MN' : strtoupper($code);
}

/**
 * Fold a phrase into the form used to match against the Manobo dataset:
 * lowercase, no parenthetical aside, no leading article/infinitive marker and
 * no trailing punctuation. "To walk" and "walk" both become "walk".
 */
function manobo_fold(string $text): string
{
    $text = trim(mb_strtolower($text, 'UTF-8'));
    $text = preg_replace('/\s*\([^)]*\)/u', '', $text) ?? $text;
    $text = preg_replace('/^(?:to|a|an|the|ang|mga)\s+/u', '', $text) ?? $text;
    $text = trim($text, " \t\n\r.!?:;…");

    return preg_replace('/\s+/u', ' ', $text) ?? $text;
}

/**
 * English/Tagalog term → Manobo, built once per request from the
 * curated dataset. Never throws: a missing or unreadable dataset just leaves
 * the UI in English.
 *
 * @return array<string,string>
 */
function manobo_word_map(): array
{
    static $map = null;

    if ($map !== null) {
        return $map;
    }

    $map = [];
    try {
        foreach ((new \App\Services\ManoboDictionary())->all() as $entry) {
            foreach (['english', 'tagalog'] as $field) {
                foreach (explode(',', (string) ($entry[$field] ?? '')) as $sense) {
                    $key = manobo_fold($sense);
                    if ($key === '') {
                        continue;
                    }
                    // First entry wins, so a minimal pair like 'hilu / hi'lu
                    // resolves to the one listed first rather than flapping.
                    if (!isset($map[$key])) {
                        $map[$key] = $entry['manobo'];
                    }
                    // Also index the singular, so an entry stored as
                    // "announcements" still answers a lookup for "announcement".
                    $singular = manobo_singular($key);
                    if ($singular !== null && $singular !== '' && !isset($map[$singular])) {
                        $map[$singular] = $entry['manobo'];
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        error_log('[manobo_word_map] dataset unavailable: ' . $e->getMessage());
        $map = [];
    }

    return $map;
}

/**
 * Reduce a simple English plural to its singular, or null when the word does
 * not look plural.
 *
 * This exists so a speaker adding "ordinance" to the dictionary also covers
 * the "Ordinances" label, without having to add both. It is intentionally
 * conservative: an over-eager rule that turns "address" into "addres" costs a
 * wrong translation, whereas a missed plural only costs a fallback to English.
 * Tagalog plurals need no rule here — manobo_fold() already strips "mga".
 */
function manobo_singular(string $word): ?string
{
    $length = mb_strlen($word, 'UTF-8');
    if ($length < 4) {
        return null;                       // too short to strip safely
    }

    // policies → policy, but not "series" → "sery"
    if (str_ends_with($word, 'ies') && $length > 4) {
        return mb_substr($word, 0, -3, 'UTF-8') . 'y';
    }

    // boxes → box, churches → church, dishes → dish
    foreach (['sses', 'shes', 'ches', 'xes', 'zes'] as $ending) {
        if (str_ends_with($word, $ending)) {
            return mb_substr($word, 0, -2, 'UTF-8');
        }
    }

    // Leave words that merely end in a sibilant or "us"/"is" alone:
    // address, status, analysis are not plurals.
    foreach (['ss', 'us', 'is'] as $ending) {
        if (str_ends_with($word, $ending)) {
            return null;
        }
    }

    // events → event, announcements → announcement
    if (str_ends_with($word, 's')) {
        return mb_substr($word, 0, -1, 'UTF-8');
    }

    return null;
}

/**
 * Translate one English or Tagalog word or fixed phrase into Manobo using the
 * curated dataset. Returns null when the dataset has no entry, so the caller
 * can fall back to English rather than invent a word.
 *
 * Tries the folded term first, then its singular — so adding "ordinance" to
 * the dictionary automatically covers the "Ordinances" label too. The map
 * itself indexes both forms as well, which covers the opposite case of an
 * entry stored in the plural.
 */
function manobo_word(string $text): ?string
{
    $map  = manobo_word_map();
    $key  = manobo_fold($text);

    if ($key === '') {
        return null;
    }
    if (isset($map[$key])) {
        return $map[$key];
    }

    $singular = manobo_singular($key);

    return $singular !== null ? ($map[$singular] ?? null) : null;
}

/**
 * Swap individual words inside a phrase for their Manobo equivalents,
 * leaving everything the dataset does not cover untouched.
 *
 * This is the fallback behind manobo_word(): when "Barangay Announcements" is
 * not in the dataset as a whole phrase, but "announcement" is, the resident
 * still sees the Manobo word rather than an entirely English label. Coverage
 * widens on its own as speakers add entries at /admin/manobo.
 *
 * Guards, every one of them learned from what the unguarded version produced
 * against the real dataset:
 *
 *   1. Nothing is invented — only words actually IN the dataset are replaced,
 *      the same rule lang/msm.php states.
 *   2. ":placeholder" tokens are never touched. Unguarded, "Welcome, :name"
 *      became "Welcome, :'ngadan", which silently broke t()'s own substitution
 *      and dropped the resident's name from the greeting.
 *   3. Only SHORT labels are glossed. Swapping one word inside a twelve-word
 *      sentence ("How can I help you?" → "How can A help you?") reads as a
 *      typo, not as Manobo.
 *   4. At least half the words must convert. A label is either mostly in
 *      Manobo or it stays English; a single hit in a long phrase is noise.
 *   5. Returns null on any miss, so t() falls through to English rather than
 *      handing back a mangled string.
 *
 * The result is a word-level gloss, not grammatical Manobo — the dictionary
 * carries no affix or word-order rules (see ManoboDictionary's docblock). That
 * is why long-form CONTENT still goes through the AI translator widget.
 */
function manobo_gloss_phrase(string $text): ?string
{
    $maxWords = 5;     // longest label worth glossing: nav items, buttons, badges
    $minRatio = 0.5;   // at least half the words must convert

    $map = manobo_word_map();
    if ($map === [] || $text === '') {
        return null;
    }

    // Split into words and the separators between them, keeping both so
    // punctuation and spacing survive the rebuild.
    $parts = preg_split('/([^\p{L}\p{N}\x27\-]+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return null;
    }

    $out        = '';
    $words      = 0;
    $swapped    = 0;
    $prevDelim  = '';

    foreach ($parts as $i => $part) {
        if ($i % 2 === 1) {            // odd indexes are the separators
            $out       .= $part;
            $prevDelim  = $part;
            continue;
        }
        if ($part === '') {
            continue;
        }

        $words++;

        // A token introduced by ":" is one of t()'s own placeholders.
        $isPlaceholder = $prevDelim !== '' && str_ends_with($prevDelim, ':');

        // Routed through manobo_word() so a single word inside a phrase gets
        // the same singular/plural handling as a whole-label lookup.
        $replacement = $isPlaceholder ? null : manobo_word($part);
        if ($replacement !== null) {
            $out .= manobo_match_case($part, $replacement);
            $swapped++;
        } else {
            $out .= $part;
        }
        $prevDelim = '';
    }

    if ($swapped === 0 || $words === 0 || $words > $maxWords) {
        return null;
    }

    return ($swapped / $words) >= $minRatio ? $out : null;
}

/**
 * Give a replacement word the capitalisation of the word it replaces, so a
 * substitution inside a Title-Cased label does not look pasted in.
 */
function manobo_match_case(string $original, string $replacement): string
{
    if ($original === '' || $replacement === '') {
        return $replacement;
    }

    // ALL CAPS stays all caps; Leading capital keeps a leading capital.
    if (mb_strtoupper($original, 'UTF-8') === $original && mb_strlen($original, 'UTF-8') > 1) {
        return mb_strtoupper($replacement, 'UTF-8');
    }

    $firstOriginal = mb_substr($original, 0, 1, 'UTF-8');
    if (mb_strtoupper($firstOriginal, 'UTF-8') === $firstOriginal) {
        return mb_strtoupper(mb_substr($replacement, 0, 1, 'UTF-8'), 'UTF-8')
             . mb_substr($replacement, 1, null, 'UTF-8');
    }

    return $replacement;
}

// ── Bisaya fallback for the MN locale ────────────────────────────────────
//
// Residents of Barangay Bayogo speak Manobo mixed with Surigaonon/Bisaya. The
// Manobo dataset (data/manobo) holds only sourced Manobo words, so it stays
// small. When it has no word, the MN button falls back to this separate
// Bisaya dictionary (data/bisaya/bisaya_dictionary.csv) rather than to
// English. Kept in its own file on purpose: a Bisaya word must never be
// recorded as Manobo in the Manobo dataset.

/** Lowercase, drop "(asides)" and edge punctuation. Unlike manobo_fold(), keeps "mga"/"ang". */
function bisaya_fold(string $text): string
{
    $text = trim(mb_strtolower($text, 'UTF-8'));
    $text = preg_replace('/\s*\([^)]*\)/u', '', $text) ?? $text;
    $text = trim($text, " \t\n\r.!?:;…,");

    return preg_replace('/\s+/u', ' ', $text) ?? $text;
}

/**
 * English → Bisaya and Tagalog → Bisaya maps, built once per request.
 * Kept separate per source language so English "at" is never read as
 * Tagalog "at" (= "ug"). Never throws.
 *
 * CHANGED: used to read data/bisaya/bisaya_dictionary.csv directly. That file
 * is now only the historical snapshot the table was seeded from (migration
 * 030 + tools/import-dictionaries-to-db.php) — reading it here would mean a
 * word added, corrected or trashed at /admin/bisaya never showed up in this
 * map. Reads App\Services\BisayaDictionary::all() instead, same as
 * manobo_word_map() already does for the Manobo side.
 *
 * @return array{english: array<string,string>, tagalog: array<string,string>}
 */
function bisaya_word_maps(): array
{
    static $maps = null;
    if ($maps !== null) {
        return $maps;
    }

    $maps = ['english' => [], 'tagalog' => []];

    try {
        foreach ((new \App\Services\BisayaDictionary())->all() as $entry) {
            $bisaya = trim((string) ($entry['bisaya'] ?? ''));
            if ($bisaya === '') {
                continue;
            }
            foreach (['english', 'tagalog'] as $field) {
                foreach (preg_split('~[,/]~u', (string) ($entry[$field] ?? '')) ?: [] as $sense) {
                    $key = bisaya_fold($sense);
                    if ($key === '') {
                        continue;
                    }
                    // First entry wins, same rule as the Manobo map.
                    $maps[$field][$key] ??= $bisaya;
                    if ($field === 'english') {
                        $singular = manobo_singular($key);
                        if ($singular !== null && $singular !== '') {
                            $maps[$field][$singular] ??= $bisaya;
                        }
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        error_log('[bisaya_word_maps] dataset unavailable: ' . $e->getMessage());
    }

    return $maps;
}

/**
 * Bisaya for one English or Tagalog word or whole label, or null.
 *
 * @param string $from 'english' | 'tagalog'
 */
function bisaya_word(string $text, string $from): ?string
{
    $map = bisaya_word_maps()[$from] ?? [];
    $key = bisaya_fold($text);
    if ($key === '') {
        return null;
    }
    if (isset($map[$key])) {
        return $map[$key];
    }
    if ($from === 'english') {
        $singular = manobo_singular($key);
        if ($singular !== null && isset($map[$singular])) {
            return $map[$singular];
        }
    }

    return null;
}

/**
 * Word-by-word "local blend" of a label: each word becomes Manobo if the
 * Manobo dataset has it, otherwise Bisaya, otherwise stays as it was. This
 * matches how Manobo is actually spoken here.
 *
 * Placeholders (":name"), numbers and ALL-CAPS acronyms (SMS, PDF) are left
 * alone and not counted. Returns null unless at least $minRatio of the
 * counted words were converted, so a half-translated label falls back to
 * plain Filipino instead of looking broken.
 *
 * @param string $from 'english' | 'tagalog'
 */
function local_blend_gloss(string $text, string $from, int $maxWords = 14, float $minRatio = 0.5): ?string
{
    if (trim($text) === '') {
        return null;
    }

    $parts = preg_split('/([^\p{L}\p{N}\x27\-]+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return null;
    }

    $out       = '';
    $words     = 0;
    $swapped   = 0;
    $prevDelim = '';

    foreach ($parts as $i => $part) {
        if ($i % 2 === 1) {
            $out      .= $part;
            $prevDelim = $part;
            continue;
        }
        if ($part === '') {
            continue;
        }

        $isPlaceholder = $prevDelim !== '' && str_ends_with($prevDelim, ':');
        $isNeutral     = $isPlaceholder
            || preg_match('/^\p{N}+$/u', $part) === 1
            || (mb_strlen($part, 'UTF-8') > 1 && mb_strtoupper($part, 'UTF-8') === $part);
        $prevDelim = '';

        if ($isNeutral) {
            $out .= $part;
            continue;
        }

        $words++;
        $replacement = manobo_word($part) ?? bisaya_word($part, $from);
        if ($replacement !== null) {
            $out .= manobo_match_case($part, $replacement);
            $swapped++;
        } else {
            $out .= $part;
        }
    }

    if ($words === 0 || $words > $maxWords) {
        return null;
    }

    return ($swapped / $words) >= $minRatio ? $out : null;
}

/**
 * How much of the UI the Manobo dataset can currently cover, as
 * ['translated' => int, 'total' => int] over every string in lang/en.php.
 * Used to label the MB button honestly instead of implying full coverage.
 */
function manobo_ui_coverage(): array
{
    static $coverage = null;

    if ($coverage !== null) {
        return $coverage;
    }

    $strings = [];
    $flatten = static function (array $arr) use (&$flatten, &$strings): void {
        foreach ($arr as $value) {
            if (is_array($value)) {
                $flatten($value);
            } elseif (is_string($value)) {
                $strings[] = $value;
            }
        }
    };
    $flatten(require __DIR__ . '/../lang/en.php');

    $translated = 0;
    foreach ($strings as $string) {
        if (manobo_word($string) !== null) {
            $translated++;
        }
    }

    return $coverage = ['translated' => $translated, 'total' => count($strings)];
}

/**
 * Whether the request being served is a back-office (staff/admin) page.
 *
 * Decided by the matched route's middleware rather than by the URL, because
 * the back office is not all under /admin — /superadmin/* is back office too,
 * and a path test would silently miss it. Every back-office route is gated by
 * a 'role:' middleware and no resident route is, so that is the real signal.
 *
 * Set by public/index.php once the route is matched. Defaults to false, so
 * anything rendering outside the front controller (tests, CLI) behaves as the
 * resident side.
 */
function is_back_office_request(): bool
{
    return ($GLOBALS['bg_is_back_office'] ?? false) === true;
}

/**
 * Pure form of the rule above, so it can be tested without a live request.
 *
 * @param list<string> $middleware The matched route's middleware list.
 */
function route_is_back_office(array $middleware): bool
{
    foreach ($middleware as $entry) {
        if (is_string($entry) && str_starts_with($entry, 'role:')) {
            return true;
        }
    }
    return false;
}

/**
 * The active UI locale for this session. Defaults to Filipino, matching this
 * app's original hardcoded strings.
 *
 * Back-office pages are always English. The language switch is a resident
 * feature — it exists so Manobo residents can read announcements in a language
 * they are comfortable with — and staff also browse the resident side, so
 * without this a staff member who picked MN there would land in an admin panel
 * in Manobo with no switcher to change it back.
 *
 * The stored preference is deliberately NOT modified here: it still applies
 * the moment that same user opens a resident page.
 */
function current_locale(): string
{
    if (is_back_office_request()) {
        return 'en';
    }

    $locale = $_SESSION['locale'] ?? $_COOKIE['bg_locale'] ?? 'fil';
    return array_key_exists($locale, available_locales()) ? $locale : 'fil';
}

/**
 * "Posted 2 hours ago" for something recent, a plain date for anything older.
 *
 * A relative stamp is only useful while it is still doing work: "3 hours ago"
 * tells a resident an advisory is live right now, but "417 days ago" makes
 * them do arithmetic to find out it was last March. The cutoff is where the
 * relative form stops being the more readable of the two.
 *
 * @param string|null $timestamp Any strtotime()-parsable value.
 * @param int|null    $now       Reference time; injected so this is testable.
 */
/**
 * An absolute date and time, written the way a notice board would write it.
 *
 * The counterpart to relative_time(). Relative phrasing is right for "when was
 * this posted", but wrong for a scheduled moment: "in 2 days" does not tell a
 * staff member whether the fiesta notice goes out before or after the Sunday
 * mass, and "Sep 20, 2026, 8:00 AM" does.
 *
 * 12-hour clock with AM/PM, which is how time is read aloud in the Philippines.
 *
 * @param string|null $timestamp Any strtotime()-parsable value.
 * @return string Empty string for missing or unparsable input, never a fake date.
 */
function format_datetime(?string $timestamp): string
{
    $timestamp = trim((string) $timestamp);
    if ($timestamp === '') {
        return '';
    }

    $when = strtotime($timestamp);

    return $when === false ? '' : date('M j, Y, g:i A', $when);
}

/**
 * The same moment formatted for <input type="datetime-local">, which accepts
 * exactly "Y-m-d\TH:i" and silently shows an empty box for anything else.
 */
function format_datetime_input(?string $timestamp): string
{
    $timestamp = trim((string) $timestamp);
    if ($timestamp === '') {
        return '';
    }

    $when = strtotime($timestamp);

    return $when === false ? '' : date('Y-m-d\TH:i', $when);
}

function relative_time(?string $timestamp, ?int $now = null): string
{
    $timestamp = trim((string) $timestamp);
    if ($timestamp === '') {
        return '';
    }

    $then = strtotime($timestamp);
    if ($then === false) {
        return '';
    }

    $now     = $now ?? time();
    $seconds = $now - $then;

    // A clock skew of a few seconds between PHP and MariaDB should read as
    // "just now", not as a post from the future.
    if ($seconds < 60) {
        return t('post.just_now');
    }
    if ($seconds < 3600) {
        $n = (int) floor($seconds / 60);
        return t($n === 1 ? 'post.minute_ago' : 'post.minutes_ago', ['n' => $n]);
    }
    if ($seconds < 86400) {
        $n = (int) floor($seconds / 3600);
        return t($n === 1 ? 'post.hour_ago' : 'post.hours_ago', ['n' => $n]);
    }
    if ($seconds < 604800) {
        $n = (int) floor($seconds / 86400);
        return t($n === 1 ? 'post.day_ago' : 'post.days_ago', ['n' => $n]);
    }

    // Older than a week: the date itself is more informative than the gap.
    return date('F j, Y', $then);
}

/**
 * Tagalog → English word map, built from the columns the two community
 * dictionaries already carry.
 *
 * Both CSVs record a Tagalog gloss and an English gloss for every entry, so
 * reading them in the tagalog→english direction costs nothing extra and needs
 * no new dataset. The Bisaya file supplies most of the vocabulary (it is the
 * larger of the two); the Manobo file adds the rest.
 *
 * This is a WORD list, not a translator. It exists to give a staff member a
 * rough first pass to correct in the admin form — never to be shown to a
 * resident as if it were a finished translation.
 *
 * @return array<string,string>
 */
function english_word_map(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $map = [];

    // CHANGED: used to read both CSV files directly. Reads the two DB-backed
    // dictionaries instead, so a word added/edited/trashed at /admin/bisaya
    // or /admin/manobo is reflected here too — Bisaya first, since it is the
    // larger, older dataset and "first entry wins" should still favour it for
    // any Tagalog word both dictionaries happen to gloss.
    foreach ([new \App\Services\BisayaDictionary(), new \App\Services\ManoboDictionary()] as $dictionary) {
        try {
            foreach ($dictionary->all() as $entry) {
                $english = trim((string) ($entry['english'] ?? ''));
                if ($english === '') {
                    continue;
                }
                // The english column may list several senses; the first is the
                // primary one and the only safe choice without context.
                $english = trim(preg_split('~[,/]~u', $english)[0] ?? '');

                // Drop the parenthetical disambiguator the dictionary uses to
                // separate homonyms — "open (not closed)", "last (before a
                // noun)". It is a note to a human reader of the dictionary,
                // and pasting it into a draft sentence is nonsense.
                $english = trim((string) preg_replace('/\s*\([^)]*\)/u', '', $english));

                if ($english === '') {
                    continue;                      // e.g. "(plural marker)"
                }

                foreach (preg_split('~[,/]~u', (string) ($entry['tagalog'] ?? '')) ?: [] as $sense) {
                    $key = bisaya_fold($sense);
                    if ($key !== '') {
                        $map[$key] ??= $english;   // first entry wins, as elsewhere
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[english_word_map] ' . $e->getMessage());
        }
    }

    return $map;
}

/**
 * Word-by-word English draft of a Filipino passage.
 *
 * Deliberately permissive compared with the resident-facing Manobo gloss:
 * this output only ever reaches a staff member inside an admin form, who
 * reads it before anything is published. Every matched word is typing saved;
 * an unmatched word is left exactly as it was so nothing is invented.
 *
 * @return array{text:string, matched:int, total:int, missing:list<string>}
 */
function english_gloss_phrase(string $text): array
{
    $map    = english_word_map();
    $result = ['text' => $text, 'matched' => 0, 'total' => 0, 'missing' => []];

    if ($map === [] || trim($text) === '') {
        return $result;
    }

    $parts = preg_split('/([^\p{L}\p{N}\x27\-]+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return $result;
    }

    $out       = '';
    $words     = 0;
    $swapped   = 0;
    $missing   = [];
    $prevDelim = '';

    foreach ($parts as $i => $part) {
        if ($i % 2 === 1) {
            $out      .= $part;
            $prevDelim = $part;
            continue;
        }
        if ($part === '') {
            continue;
        }

        // Leave placeholders, numbers and ALL-CAPS acronyms alone, and do not
        // count them against coverage — they are already language-neutral.
        $isPlaceholder = $prevDelim !== '' && str_ends_with($prevDelim, ':');
        if ($isPlaceholder || is_numeric($part) || (mb_strtoupper($part) === $part && mb_strlen($part) > 1)) {
            $out      .= $part;
            $prevDelim = '';
            continue;
        }

        $words++;
        $hit = $map[bisaya_fold($part)] ?? null;

        // Tagalog attaches a linker to a modifier: "libre" → "libreng",
        // "mahalaga" → "mahalagang", "buo" → "buong". The dictionary stores
        // the bare root, so strip the linker and look again before giving up.
        if ($hit === null) {
            $stem = preg_replace('/(ng|g)$/u', '', bisaya_fold($part));
            if (is_string($stem) && $stem !== '' && $stem !== bisaya_fold($part)) {
                $hit = $map[$stem] ?? null;
            }
        }

        if ($hit !== null) {
            $out .= manobo_match_case($part, $hit);
            $swapped++;
        } else {
            $out .= $part;
            $missing[] = $part;
        }
        $prevDelim = '';
    }

    return [
        'text'    => $out,
        'matched' => $swapped,
        'total'   => $words,
        'missing' => array_values(array_unique($missing)),
    ];
}

/**
 * Word-by-word Manobo gloss of a Filipino passage, or null if too little of it
 * could be reached.
 *
 * Used to auto-fill the Manobo translation on save. No machine translation
 * service supports Manobo, so the community dictionary is the only automatic
 * option — but a gloss that only converted a handful of words is the Filipino
 * original wearing a Manobo label, and publishing that under a Manobo heading
 * would mislead exactly the residents this feature exists to serve.
 *
 * The floor is therefore deliberately high compared with the admin draft
 * button: this output goes straight in front of residents, where the draft
 * button's output is corrected by a staff member first.
 */
function manobo_gloss_for(string $text, float $minRatio = 0.6, string $from = 'tagalog'): ?string
{
    $text = trim(strip_tags($text));
    if ($text === '') {
        return null;
    }
    if (!in_array($from, ['tagalog', 'english'], true)) {
        $from = 'tagalog';
    }

    // local_blend_gloss() already does Manobo-first, Bisaya-second word
    // substitution with placeholder and acronym handling.
    $glossed = local_blend_gloss($text, $from, 400, $minRatio);
    if ($glossed === null || trim($glossed) === '' || $glossed === $text) {
        return null;
    }

    // Coverage counts a word as "found" even when the dictionary maps it to
    // itself — "sa" → "sa", "barangay" → "barangay". A passage can therefore
    // clear the ratio while barely a word on screen has changed, which would
    // publish the Filipino text under a Manobo heading with a translation
    // notice on it. Require that a real share of the words actually differ.
    $before = preg_split('/[^\p{L}\p{N}\x27\-]+/u', $text,    -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $after  = preg_split('/[^\p{L}\p{N}\x27\-]+/u', $glossed, -1, PREG_SPLIT_NO_EMPTY) ?: [];

    if (\count($before) !== \count($after) || $before === []) {
        return $glossed;           // shapes differ; the ratio check already passed
    }

    $changed = 0;
    foreach ($before as $i => $word) {
        if (mb_strtolower($word) !== mb_strtolower($after[$i])) {
            $changed++;
        }
    }

    return ($changed / \count($before)) >= 0.30 ? $glossed : null;
}

/**
 * Pick the version of a post's field that matches the reader's chosen language.
 *
 * Announcements, events and ordinances are authored once by staff and then
 * optionally translated. The text a staff member types is Filipino in
 * practice, and the translated copies live in sibling columns:
 *
 *     title  →  title_en  /  title_manobo
 *     body   →  body_en   /  body_manobo
 *
 * So the rule is: Filipino reads the original, English and Manobo read their
 * own column, and anything with no translation yet falls back to the original
 * rather than showing an empty page.
 *
 * The fallback is reported back to the caller instead of being hidden,
 * because a resident who switched to English and is looking at Filipino text
 * deserves to be told that — silently serving the wrong language looks like
 * the switch is broken. Nothing here invents text.
 *
 * A translation may have been produced by a machine rather than written by a
 * person, so `machine` is reported too — the view uses it to label the text
 * honestly instead of giving a rough gloss the same authority as a sentence a
 * Manobo speaker wrote by hand.
 *
 * `is_original` says whether the text returned is the source-language copy
 * rather than one of the translation columns. That matters because the two are
 * stored differently: an announcement's original body is Quill HTML, while
 * every translation is plain text. A view that renders the original as plain
 * text prints the markup — literal `<p>` tags on the page — and a view that
 * renders a translation as HTML would trust text a machine produced.
 *
 * It is reported rather than inferred because the obvious proxy is wrong. The
 * detail page used to derive it as "translated and not Filipino", which held
 * only while every post was written in Filipino; the moment a post was
 * authored in English, its own original was escaped and residents saw the tags.
 *

 * @param  array<string,mixed> $row       A content row.
 * @param  string              $baseField 'title', 'body' or 'description'.
 * @param  string|null         $locale    Ask for a specific language instead of
 *                                        the reader's. The voice reader needs
 *                                        this: a Manobo page with no Manobo
 *                                        recording offers the Filipino reading,
 *                                        so it has to fetch Filipino text while
 *                                        the page itself is still in Manobo.
 * @return array{text:string, translated:bool, machine:bool, locale:string, is_original:bool}
 */
function localised_content(array $row, string $baseField, ?string $locale = null): array
{
    $locale   = $locale !== null && array_key_exists($locale, available_locales())
        ? $locale
        : current_locale();
    $original = (string) ($row[$baseField] ?? '');

    // Which language the staff member actually wrote in. Rows created before
    // migration 018 have no column and were all authored in Filipino, so that
    // is the default — existing content keeps behaving exactly as it did.
    $sourceLang = (string) ($row['source_lang'] ?? 'fil');

    // The source text IS that language's version. No language is special:
    // a post written in English is the English version, and Filipino is then
    // the one that needs translating.
    if ($locale === $sourceLang) {
        return [
            'text'        => $original,
            'translated'  => true,
            'machine'     => false,
            'locale'      => $locale,
            'is_original' => true,
            // Asked for and got. See the note on 'shown_locale' below.
            'shown_locale' => $locale,
        ];
    }

    [$column, $autoFlag] = match ($locale) {
        'en'    => [$baseField . '_en',     'en_is_auto'],
        'fil'   => [$baseField . '_fil',    'fil_is_auto'],
        'msm'   => [$baseField . '_manobo', 'manobo_is_auto'],
        default => [null, null],
    };

    if ($column === null) {
        return [
            'text'         => $original,
            'translated'   => true,
            'machine'      => false,
            'locale'       => $locale,
            'is_original'  => true,
            'shown_locale' => $sourceLang,
        ];
    }

    $translated = trim((string) ($row[$column] ?? ''));

    // An urgent announcement's machine translation is held back until a staff
    // member has checked it. Free machine translation can invert meaning, and
    // on a storm warning that is the difference between a family leaving and
    // a family staying put — so until it is confirmed this behaves exactly as
    // if no translation existed. See migration 017.
    //
    // The gate follows the machine-translated language, which is whichever of
    // Filipino/English the post was NOT written in. One state column is enough
    // because a post only ever has one such language. Manobo is never gated —
    // it is glossed locally from the barangay's own dictionary.
    //
    // Decision of the barangay (2026-10-13): show it anyway, flagged
    // 'unreviewed', so a resident who pressed EN reads English. The page
    // then shows a red "not yet checked by staff" warning and the original
    // one click away (_translation-notice.php), because the free translator
    // does make dangerous mistakes here: "May bagyo, lumikas na" became "A
    // hurricane has evacuated", "Bayogo Barangay Hall" became "Bay Barangay
    // Hall". Staff still confirm or correct it in the Translation Hub.
    $gatedLocale = $sourceLang === 'fil' ? 'en' : 'fil';
    $gated       = false;
    $unreviewed  = $locale === $gatedLocale
        && ($row['en_review_state'] ?? 'none') === 'pending'
        && $translated !== '';



    /*
     * 'locale' is what the reader ASKED for. 'shown_locale' is what they are
     * actually looking at, and the two differ exactly when a translation is
     * missing and the source text is served in its place.
     *
     * That distinction is the whole of the resident-side honesty problem. A
     * reader who taps MN and gets Filipino under a Manobo heading has no way
     * to tell whether the switch is broken, whether Manobo looks like that,
     * or whether the translation simply does not exist. Until now nothing in
     * this return said which language the text on screen was, so no caller
     * COULD say it — the notice could only report an absence, never name
     * what it had fallen back to.
     */

    // Translations are rendered as escaped plain text. Some were saved with
    // Quill HTML still in them, others with their tags stripped bare so
    // sentences ran together ("Sur.Intawa", "ngayonLahat"); normalise both
    // so residents read clean paragraphs in every language.
    if ($translated !== '') {
        if ($translated !== strip_tags($translated)) {
            $translated = \App\Services\SpokenText::plain($translated);
        }
        $translated = \App\Services\SpokenText::repairJoins($translated);
    }

    return $translated !== ''
        ? [
            'text'         => $translated,
            'translated'   => true,
            'machine'      => $unreviewed || (int) ($row[$autoFlag] ?? 1) === 1,
            'locale'       => $locale,
            'is_original'  => false,
            'shown_locale' => $locale,
            // Urgent post, machine translation not yet checked by staff:
            // shown with a warning and the original one click away.
            'unreviewed'   => $unreviewed,
            'original'     => $unreviewed ? $original : null,
            'source_lang'  => $sourceLang,
        ]
        : [
            'text'         => $original,
            'translated'   => false,
            'machine'      => false,
            'locale'       => $locale,
            // Fell back to the source text, so it is the original — and on an
            // announcement that means Quill HTML, not plain text.
            'is_original'  => true,
            'shown_locale' => $sourceLang,
            // True when a machine translation exists but is held for staff
            // review (urgent posts); the page must not try to generate one.
            'gated'        => $gated,
        ];
}

/** Just the text from localised_content(), for callers that don't need the flag. */
function localised_text(array $row, string $baseField): string
{
    return localised_content($row, $baseField)['text'];
}

/**
 * Set the active UI locale for this session and persist to cookie/profile.
 */
/**
 * The interface language of the back office (staff/admin pages).
 *
 * Kept apart from current_locale() on purpose. current_locale() also decides
 * which language version of a post is rendered, and the back office must keep
 * showing the original text — an edit form pre-filled with a machine
 * translation would save that translation over the source. So staff pages
 * stay pinned to English for CONTENT, while their labels, menus and headings
 * follow the EN / FIL / MN switch in the admin top bar.
 *
 * Defaults to English and has its own session key, so a staff member's
 * choice here never changes what language they read resident pages in.
 */
function back_office_locale(): string
{
    // The admin and staff back office is English only (the EN / FIL / MN
    // switch was removed from its top bar). The language switch and the
    // translator are for residents; see current_locale().
    return 'en';
}

/** Persist the back-office interface language. See back_office_locale(). */
function set_back_office_locale(string $locale): void
{
    if (!array_key_exists($locale, available_locales())) {
        return;
    }
    $_SESSION['bo_locale'] = $locale;
    if (!headers_sent()) {
        setcookie('bg_bo_locale', $locale, [
            'expires'  => time() + 86400 * 365,
            'path'     => '/',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }
}

function set_locale(string $locale): void
{
    if (array_key_exists($locale, available_locales())) {
        $_SESSION['locale'] = $locale;
        if (!headers_sent()) {
            setcookie('bg_locale', $locale, [
                'expires'  => time() + 86400 * 365,
                'path'     => '/',
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
        if (!empty($_SESSION['user']['id'])) {
            try {
                db()->prepare("UPDATE users SET locale = ? WHERE id = ?")
                    ->execute([$locale, $_SESSION['user']['id']]);
            } catch (\Throwable) {}
        }
    }
}

/**
 * Translate a dot-notation UI string key (e.g. 'nav.announcements') into the
 * active locale. Falls back to Filipino (for the Manobo locale) or English,
 * then to the raw key, so a missing translation is visibly obvious rather
 * than a blank string — this project's lang files are intentionally partial
 * (see lang/msm.php), and t() must never paper over that with fabricated
 * text.
 *
 * Why Filipino before English for Manobo specifically: residents here
 * naturally code-switch Manobo with Surigaonon and Bisaya, not with English —
 * a Manobo speaker who does not have a word for something is far more likely
 * to follow Filipino/Bisaya than English. See manoboSystemPrompt() in
 * AIService for the same rule applied to AI-generated content translations.
 *
 * @param array<string,string> $replace  ':placeholder' => value substitutions
 * @param string|null          $locale   Resolve in a specific language rather
 *                                       than the reader's. The voice reader
 *                                       needs it: an MP3 is generated for all
 *                                       three languages at once, from a staff
 *                                       request that is itself always English,
 *                                       so the spoken date and urgency lines
 *                                       have to be asked for by name.
 */
function t(string $key, array $replace = [], ?string $locale = null): string
{
    static $cache = [];

    $locale = $locale !== null && array_key_exists($locale, available_locales())
        ? $locale
        : (is_back_office_request() ? back_office_locale() : current_locale());
    if (!isset($cache[$locale])) {
        $file = __DIR__ . '/../lang/' . $locale . '.php';
        $cache[$locale] = is_file($file) ? require $file : [];
    }
    if (!isset($cache['en'])) {
        $cache['en'] = require __DIR__ . '/../lang/en.php';
    }

    $lookup = static function (array $arr, string $dotKey) {
        $cursor = $arr;
        foreach (explode('.', $dotKey) as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }
        return is_string($cursor) ? $cursor : null;
    };

    $value   = $lookup($cache[$locale], $key);
    $english = $lookup($cache['en'], $key);

    // Manobo has no hand-written string file (lang/msm.php stays empty). Each
    // label is resolved by manobo_ui_text(): the Manobo dataset first, then
    // Bisaya for whatever the dataset does not cover. See that function.
    if ($locale === 'msm' && $value === null) {
        if (!isset($cache['fil'])) {
            $filFile      = __DIR__ . '/../lang/fil.php';
            $cache['fil'] = is_file($filFile) ? require $filFile : [];
        }
        if (!isset($cache['msm_auto'])) {
            $autoFile          = __DIR__ . '/../lang/msm_auto.php';
            $cache['msm_auto'] = is_file($autoFile) ? require $autoFile : [];
        }
        $value = manobo_ui_text(
            $english ?? $key,
            $lookup($cache['fil'], $key),
            $cache['msm_auto'][$key] ?? null
        );
    }

    $value = $value ?? $english ?? $key;

    foreach ($replace as $placeholder => $val) {
        $value = str_replace(':' . $placeholder, (string) $val, $value);
    }

    return $value;
}

/**
 * The Manobo (MN) form of one interface string.
 *
 * Residents of Barangay Bayogo speak Manobo mixed with Surigaonon/Bisaya, so
 * the MN interface is Manobo wherever the barangay's dataset has the word and
 * Bisaya everywhere else — never English or Tagalog left in place when a
 * Bisaya form exists. In order:
 *
 *   1. the whole label is a Manobo dataset entry (live: a word added at
 *      /admin/manobo shows up on the next page load)
 *   2. the pre-generated form in lang/msm_auto.php or lang/phrases/msm.php
 *      (tools/generate-manobo-ui.php: dataset Manobo first, machine Bisaya
 *      for the rest, then every Bisaya word the dataset knows swapped back
 *      to Manobo)
 *   3. the hybrid translator at runtime, for a string added after the last
 *      generation run. Its result is cached in translation_cache, and the
 *      machine part is capped per request so a page never stalls on it.
 *   4. a word-by-word Manobo/Bisaya blend of the Filipino (or English) text
 *   5. Filipino, then English — only if every step above came up empty
 *
 * Nothing here invents a Manobo word: Manobo only ever comes from the dataset.
 */
function manobo_ui_text(string $english, ?string $filipino, ?string $generated): string
{
    foreach ([$english, $filipino] as $source) {
        if ($source !== null && trim($source) !== '') {
            $whole = manobo_word($source);
            if ($whole !== null) {
                return $whole;
            }
        }
    }

    if ($generated !== null && trim($generated) !== '') {
        return $generated;
    }

    $runtime = manobo_ui_runtime($english);
    if ($runtime !== null) {
        return $runtime;
    }

    $blend = $filipino !== null
        ? local_blend_gloss($filipino, 'tagalog', 40, 0.01)
        : local_blend_gloss($english, 'english', 40, 0.01);

    return $blend ?? $filipino ?? $english;
}

/**
 * Hybrid-translate one English interface string to Manobo/Bisaya at request
 * time. Only reached for strings the generator has not seen yet.
 *
 * At most a few strings per request may call the machine translator (each
 * call is cached in translation_cache, so the next page load is instant);
 * past that budget the local dictionaries alone are used. Placeholders such
 * as ":name" are kept out of the translator and must all survive, or the
 * result is discarded.
 */
function manobo_ui_runtime(string $english): ?string
{
    static $translator = null;
    static $machineCalls = 0;

    if (trim($english) === '' || !preg_match('/\p{L}{2,}/u', $english)) {
        return null;
    }

    // No database (unit tests, CLI without .env): nothing to translate with.
    // Checked once per request, not once per label.
    static $dbReachable = null;
    if ($dbReachable === null) {
        try {
            db();
            $dbReachable = true;
        } catch (\Throwable) {
            $dbReachable = false;
        }
    }
    if (!$dbReachable) {
        return null;
    }

    preg_match_all('/:[a-z_]+/', $english, $found);
    $placeholders = $found[0];

    $previousDeadline = \App\Services\ManoboHybridTranslator::$machineDeadline;
    // Three strings per request get up to ~2.5s of machine translation in
    // total; everything after that uses the local dictionaries only.
    \App\Services\ManoboHybridTranslator::$machineDeadline = $machineCalls < 3
        ? microtime(true) + 2.5
        : 0.0;
    $machineCalls++;

    try {
        $translator ??= new \App\Services\ManoboHybridTranslator();
        $result = $translator->translate($english, 'en');
    } catch (\Throwable $e) {
        error_log('[manobo_ui_runtime] ' . $e->getMessage());
        return null;
    } finally {
        \App\Services\ManoboHybridTranslator::$machineDeadline = $previousDeadline;
    }

    $text = trim((string) ($result['translation'] ?? ''));
    if ($text === '' || (int) ($result['unresolved'] ?? 1) > 0) {
        return null;
    }
    foreach ($placeholders as $placeholder) {
        if (!str_contains($text, $placeholder)) {
            return null;
        }
    }

    return $text;
}

/**
 * The lookup key for a phrase in lang/phrases/*.php: the source text with its
 * whitespace collapsed, so re-indenting a view never breaks a translation.
 */
function phrase_key(string $text): string
{
    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

/**
 * Translate a literal interface phrase into the reader's language.
 *
 * The phrase-keyed sibling of t(). t() looks a string up by a dot key in
 * lang/en.php / lang/fil.php; __() looks it up by the text itself in
 * lang/phrases/en.php / fil.php / msm.php. Views and controllers that were
 * written with the text inline ("Magpatuloy (Continue)", "Event not found.")
 * use __() so each language button shows that language only:
 *
 *   EN  → lang/phrases/en.php, else the text as written
 *   FIL → lang/phrases/fil.php, else the text as written
 *   MN  → manobo_ui_text(): Manobo dataset first, Bisaya for the rest
 *
 * The source text may be in either language — the catalogs hold both an
 * English and a Filipino form for every phrase, whichever it was written in.
 *
 * Same language rules as t(): back-office pages follow the admin top bar's
 * switch, resident pages the resident one.
 *
 * @param array<string,string|int|float> $replace ':placeholder' => value
 */
function __(string $text, array $replace = []): string
{
    static $catalogs = [];

    $locale = is_back_office_request() ? back_office_locale() : current_locale();
    $key    = phrase_key($text);

    foreach (['en', 'fil', $locale] as $lang) {
        if (!isset($catalogs[$lang])) {
            $file = __DIR__ . '/../lang/phrases/' . $lang . '.php';
            $catalogs[$lang] = is_file($file) ? require $file : [];
        }
    }

    $english  = $catalogs['en'][$key] ?? null;
    $filipino = $catalogs['fil'][$key] ?? null;

    $value = match ($locale) {
        'en'    => $english ?? $key,
        'fil'   => $filipino ?? $key,
        'msm'   => manobo_ui_text($english ?? $key, $filipino, $catalogs['msm'][$key] ?? null),
        default => $key,
    };

    foreach ($replace as $placeholder => $val) {
        $value = str_replace(':' . $placeholder, (string) $val, $value);
    }

    return $value;
}

/**
 * Badge tone for any status in the system (documents, payments, events,
 * ordinances, voice samples), so one meaning has one colour everywhere.
 * Returns a .ds-status modifier: neutral | info | warning | success | danger.
 */
function status_tone(string $status): string
{
    return match (strtolower(trim($status))) {
        'draft', 'pending', 'pending_review', 'submitted', 'upcoming', 'scheduled' => 'neutral',
        'awaiting_payment', 'payment_pending', 'payment_proof_submitted', 'needs_information', 'pay_at_pickup' => 'warning',
        'under_review', 'processing', 'ongoing', 'in_progress', 'approved', 'active' => 'info',
        'ready', 'ready_for_pickup', 'available_for_download', 'released', 'completed', 'paid', 'paid_verified', 'paid_at_pickup', 'verified', 'published' => 'success',
        'rejected', 'cancelled', 'payment_rejected', 'failed', 'repealed', 'suspended', 'refunded' => 'danger',
        default => 'neutral',
    };
}

/**
 * Render a status badge: <span class="ds-status ds-status--{tone}">label</span>.
 */
function status_badge(string $status, ?string $label = null): string
{
    // The fallback label is English ("Pending Review"); __() gives it in the
    // reader's language like any other phrase.
    $label ??= __(ucwords(strtolower(str_replace('_', ' ', $status))));
    return '<span class="ds-status ds-status--' . status_tone($status) . '">' . e($label) . '</span>';
}

/**
 * Placeholder photo for a post with no cover image (or whose file is gone):
 * safety/urgent → storm, government/infrastructure → barangay hall,
 * everything else → community. Public URL.
 */
function post_placeholder_image(?string $category, ?string $urgency = null): string
{
    $category = strtolower((string) $category);
    $file = match (true) {
        $urgency === 'urgent' || $category === 'safety'                => 'post-typhoon.jpg',
        in_array($category, ['government', 'infrastructure'], true)   => 'post-hall.jpg',
        default                                                        => 'post-community.jpg',
    };
    return asset('assets/images/ui/' . $file);
}

/**
 * Show a piece of system-written text in the reader's language.
 *
 * For text that has no per-language columns: notifications ("Naaprubahan ang
 * Inyong Dokumento…") and cached AI ordinance summaries. Both are stored once,
 * in whatever language they were written, so an EN reader used to get
 * Filipino and an MN reader got Tagalog.
 *
 *   EN / FIL → the free translation service (MyMemory, Google fallback)
 *   MN       → the Manobo hybrid translator: Manobo dataset first, Bisaya
 *              where the dataset has no word (never Tagalog left in place)
 *
 * Every result is cached in translation_cache, so a text is translated once
 * for everybody. Uncached work is capped per request; past the cap, or when a
 * provider is down, the original text is shown rather than a slow page.
 * Never throws.
 *
 * @param string      $text   Plain text (tags are stripped).
 * @param string|null $target 'en' | 'fil' | 'msm'; defaults to the reader's language.
 * @param bool        $onlyWhenSure Leave the text alone unless its language is
 *                    clearly detected — for short names such as event venues.
 */
function translate_for_display(string $text, ?string $target = null, bool $onlyWhenSure = false): string
{
    static $memo = [];
    static $deadline = null;

    $plain = trim(strip_tags($text));
    if ($plain === '' || !preg_match('/\p{L}{2,}/u', $plain)) {
        return $text;
    }

    $target ??= is_back_office_request() ? back_office_locale() : current_locale();
    if (!in_array($target, ['en', 'fil', 'msm'], true)) {
        return $text;
    }

    $source = \App\Services\LanguageGuess::detectOrNull($plain);
    if ($source === null && $onlyWhenSure) {
        return $text;
    }
    $source ??= 'fil';
    if ($source === $target) {
        return $text;
    }

    $memoKey = $target . '|' . $plain;
    if (isset($memo[$memoKey])) {
        return $memo[$memoKey];
    }

    $hash = hash('sha256', $plain);

    try {
        // A complete EN/FIL translation, cached for good.
        if ($target !== 'msm') {
            $cached = display_translation_row($hash, $source, $target, false);
            if ($cached !== null) {
                return $memo[$memoKey] = $cached;
            }
        }

        // A recent attempt that came back incomplete — the free services were
        // throttled. Its best result is reused for an hour instead of making
        // every page view wait on a provider that is refusing.
        $recent = display_translation_row($hash, $source, $target . '~', true);
        if ($recent !== null) {
            return $memo[$memoKey] = $recent;
        }

        // Uncached translation gets ~2.5 seconds per request in total.
        $deadline ??= microtime(true) + 2.5;
        if (microtime(true) > $deadline) {
            return $text;   // over budget: original now, translated on a later load
        }

        if ($target === 'msm') {
            $previous = \App\Services\ManoboHybridTranslator::$machineDeadline;
            \App\Services\ManoboHybridTranslator::$machineDeadline = $deadline;
            try {
                // Complete results are cached by the translator itself.
                $result = (new \App\Services\ManoboHybridTranslator())->translate($plain, $source);
            } finally {
                \App\Services\ManoboHybridTranslator::$machineDeadline = $previous;
            }
            $out      = trim((string) ($result['translation'] ?? ''));
            $complete = $out !== '' && (int) ($result['unresolved'] ?? 1) === 0;
        } else {
            $free = new \App\Services\FreeTranslationService(new \GuzzleHttp\Client([
                'timeout'     => 4,
                'http_errors' => false,
                'headers'     => ['User-Agent' => 'BarangGabay/1.0 (barangay notice board)'],
            ]));
            $out      = trim((string) $free->translate($plain, $source, $target));
            $complete = $out !== '';
            if ($complete) {
                display_translation_store($hash, $source, $target, $out);
            }
        }

        $best = $out !== '' ? $out : $text;
        if (!$complete) {
            display_translation_store($hash, $source, $target . '~', $best);
        }

        return $memo[$memoKey] = $best;
    } catch (\Throwable $e) {
        error_log('[translate_for_display] ' . $e->getMessage());
        return $text;
    }
}

/**
 * One translate_for_display() cache row (translation_cache, dictionary_version 0).
 * A target ending in "~" is a recent incomplete attempt, valid for one hour.
 */
function display_translation_row(string $hash, string $source, string $target, bool $recentOnly): ?string
{
    $sql = 'SELECT translated_text FROM translation_cache
             WHERE source_text_hash = ? AND source_lang = ? AND target_lang = ? AND dictionary_version = 0';
    if ($recentOnly) {
        $sql .= " AND updated_at > NOW() - INTERVAL '1' HOUR";
    }
    $stmt = db()->prepare($sql . ' LIMIT 1');
    $stmt->execute([$hash, $source, $target]);
    $text = $stmt->fetchColumn();

    return is_string($text) && $text !== '' ? $text : null;
}

/** Save a translate_for_display() result (see display_translation_row()). */
function display_translation_store(string $hash, string $source, string $target, string $text): void
{
    $pdo     = db();
    $isPgsql = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql';
    $pdo->prepare(
        'INSERT INTO translation_cache (source_text_hash, source_lang, target_lang, dictionary_version, translated_text, updated_at)
         VALUES (?, ?, ?, 0, ?, NOW()) '
        . ($isPgsql
            ? 'ON CONFLICT (source_text_hash, source_lang, target_lang, dictionary_version)
               DO UPDATE SET translated_text = EXCLUDED.translated_text, updated_at = NOW()'
            : 'ON DUPLICATE KEY UPDATE translated_text = VALUES(translated_text), updated_at = NOW()')
    )->execute([$hash, $source, $target, $text]);
}

/**
 * Notification rows with their title and message in the reader's language.
 *
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>>
 */
function localise_notifications(array $rows, ?string $target = null): array
{
    foreach ($rows as &$row) {
        foreach (['title', 'message'] as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $row[$field] = translate_for_display($row[$field], $target);
            }
        }
    }
    unset($row);

    return $rows;
}

/**
 * Move an announcement, event or ordinance to the Trash (the one delete path
 * for all three; see App\Models\PostTrash).
 *
 * Admins may trash any post; staff only the posts they wrote. Nothing is
 * deleted for good here — the post can be restored from /admin/trash.
 * Redirects; never returns.
 */
function trash_post(string $type, int $id, string $backTo): void
{
    [$table, $authorCol, $label] = \App\Models\PostTrash::TYPES[$type];
    $me   = (int) ($_SESSION['user_id'] ?? 0);
    $role = (string) ($_SESSION['role'] ?? '');

    $stmt = db()->prepare("SELECT id, title, {$authorCol} AS author FROM {$table} WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $post = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (!$post) {
        flash('error', $label . ' not found. It may already be in the Trash.');
        redirect($backTo);
    }

    if (!in_array($role, ['admin', 'superadmin'], true) && (int) $post['author'] !== $me) {
        flash('error', 'You can only delete ' . strtolower($label) . 's that you posted.');
        redirect($backTo);
    }

    try {
        \App\Models\PostTrash::moveToTrash($type, $id, $me);
    } catch (\Throwable $e) {
        error_log('[trash_post] ' . $e->getMessage());
        flash('error', 'Could not move this ' . strtolower($label) . ' to the Trash. Please try again.');
        redirect($backTo);
    }

    \App\Models\AuditLog::record($me, $type . '.trash', 'Moved to Trash: ' . $label . ' #' . $id . ' ' . $post['title']);
    flash('success', '"' . $post['title'] . '" was moved to the Trash. You can restore it from Trash.');
    redirect($backTo);
}
