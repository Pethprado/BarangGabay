<?php
declare(strict_types=1);

/**
 * Replace the demo posts with current, fully trilingual sample content.
 *
 *   php tools/seed_current_content.php              # remove sample posts, seed the new set
 *   php tools/seed_current_content.php --purge-all  # remove EVERY post first (real ones too)
 *
 * Also run once automatically on PostgreSQL deployments (config/database.php,
 * setting 'demo_current_content_v2'), where it only ever removes rows flagged
 * is_sample = 1 — a real post a staff member wrote on the live site is never
 * touched by an automatic step.
 *
 * Content: tools/current-content/content.php (EN, FIL and MN written by
 * hand). Covers: public/assets/images/posts/*.png (sources in
 * tools/current-content/covers/*.svg). Ordinance PDFs:
 * public/assets/samples/ordinances/*.pdf. All three are committed files, so
 * they survive Render restarts, unlike public/uploads.
 *
 * Safe to run on a live system:
 *   - no network calls (the Manobo step uses the local dataset only);
 *   - no notifications or SMS: rows are inserted with notified_at set, so
 *     ScheduledPublisher never sees them as due;
 *   - every row is is_sample = 1, so it can be removed before go-live.
 */

if (!function_exists('bg_seed_current_content')) {

/**
 * Delete posts and everything that points at them.
 *
 * @param bool $all false = only is_sample = 1 rows; true = every post
 * @return array<string,int> rows removed per table
 */
function bg_purge_posts(PDO $pdo, bool $all, ?callable $log = null): array
{
    $log ??= static function (string $m): void {};
    $root    = \dirname(__DIR__);
    $removed = [];
    $tables  = [
        'announcement' => ['announcements', ['cover_image_url']],
        'event'        => ['events',        ['cover_image_url']],
        'ordinance'    => ['ordinances',    ['file_url']],
    ];

    foreach ($tables as $type => [$table, $fileCols]) {
        $rows = $pdo->query("SELECT * FROM {$table}" . ($all ? '' : ' WHERE is_sample = 1'))->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $id = (int) $row['id'];

            // Generated audio first (files + cache rows).
            try {
                \App\Services\PostAudioService::purgeFor($type, $id);
            } catch (\Throwable $e) {
            }

            // Rows that point at the post. Each table may not exist on every
            // install, so each statement stands alone.
            $deletes = [
                'DELETE FROM notifications WHERE related_type = ? AND related_id = ?',
                'DELETE FROM translation_attempts WHERE content_type = ? AND content_id = ?',
                'DELETE FROM translation_logs WHERE content_type = ? AND content_id = ?',
                'DELETE FROM post_audio WHERE content_type = ? AND content_id = ?',
                'DELETE FROM bookmarks WHERE content_type = ? AND content_id = ?',
                'DELETE FROM media_files WHERE related_type = ? AND related_id = ?',
                'DELETE FROM voice_word_usage WHERE content_type = ? AND content_id = ?',
                // A community recording is kept; it just stops pointing at a post that is gone.
                'UPDATE voice_samples SET content_type = NULL, content_id = NULL WHERE content_type = ? AND content_id = ?',
            ];
            foreach ($deletes as $sql) {
                try {
                    $pdo->prepare($sql)->execute([$type, $id]);
                } catch (\Throwable $e) {
                }
            }

            // Uploaded files only — never a committed asset under assets/.
            foreach ($fileCols as $col) {
                $path = ltrim((string) ($row[$col] ?? ''), '/');
                if (str_starts_with($path, 'uploads/') && !str_contains($path, '..')) {
                    @unlink($root . '/public/' . $path);
                }
            }
        }

        $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);
        if ($ids !== []) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM {$table} WHERE id IN ({$marks})")->execute($ids);
        }
        $removed[$table] = count($ids);
        $log("  removed {$removed[$table]} {$table}");
    }

    return $removed;
}

/**
 * INSERT only the columns this database actually has.
 *
 * The live PostgreSQL schema is not built from the MySQL migrations, so a few
 * columns (notified_at, notify_sms, …) exist locally but not there. A value of
 * true for a key means NOW().
 *
 * @param array<string,mixed> $row
 */
function bg_insert_existing(PDO $pdo, string $table, array $row): void
{
    static $columns = [];
    if (!isset($columns[$table])) {
        $isPgsql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
        $stmt = $pdo->prepare($isPgsql
            ? "SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ?"
            : "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        $columns[$table] = array_flip(array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    }

    $names = [];
    $marks = [];
    $args  = [];
    foreach ($row as $name => $value) {
        if (!isset($columns[$table][strtolower($name)])) {
            continue;
        }
        $names[] = $name;
        if ($value === true) {
            $marks[] = 'NOW()';
        } else {
            $marks[] = '?';
            $args[]  = $value;
        }
    }
    $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(', ', $names) . ') VALUES (' . implode(', ', $marks) . ')')
        ->execute($args);
}

/**
 * Seed the current trilingual sample set (after removing old sample posts).
 *
 * @return array<string,int> rows created per table
 */
function bg_seed_current_content(PDO $pdo, string $driver = 'mysql', bool $purgeAll = false, ?callable $log = null): array
{
    $log ??= static function (string $m): void {};
    $root    = \dirname(__DIR__);
    $content = require $root . '/tools/current-content/content.php';

    $log('Removing ' . ($purgeAll ? 'ALL posts' : 'sample posts') . '...');
    bg_purge_posts($pdo, $purgeAll, $log);

    // Author: a verified staff member, else an admin, else the superadmin.
    $author = 0;
    foreach (['staff', 'admin', 'superadmin'] as $role) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = ? AND status = 'verified' ORDER BY id LIMIT 1");
        $stmt->execute([$role]);
        $author = (int) $stmt->fetchColumn();
        if ($author > 0) {
            break;
        }
    }
    if ($author === 0) {
        throw new \RuntimeException('No verified staff/admin account to attribute the posts to.');
    }

    // MN: Bisaya text with every word the Manobo dataset knows swapped in.
    $translator = new \App\Services\ManoboHybridTranslator($pdo);
    $index      = $translator->loadManoboIndex();
    // Agency names, place names and other ALL-CAPS words are kept as written:
    // "PAGASA" otherwise became "ANDE", the dataset's word for pag-asa (hope).
    $manobo = static function (string $text) use ($translator, $index): string {
        $parts = preg_split('/(<[^>]+>)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        foreach ($parts as $i => $part) {
            if ($part === '' || $part[0] === '<' || !preg_match('/\p{L}/u', $part)) {
                continue;
            }
            [$masked, $map] = \App\Services\FreeTranslationService::protectAcronyms($part);
            $masked = preg_replace_callback('/(?<![\p{L}\p{N}])\p{Lu}{2,}(?![\p{L}\p{N}])/u', static function (array $m) use (&$map): string {
                $token = 'ZQX' . count($map);
                $map[$token] = $m[0];
                return $token;
            }, $masked) ?? $masked;
            $refined   = $translator->refineBisayaWithManobo($masked, $index)['text'];
            $parts[$i] = \App\Services\FreeTranslationService::restoreAcronyms($refined, $map);
        }
        return implode('', $parts);
    };

    $slugFree = static function (string $table, string $slug) use ($pdo): string {
        $candidate = $slug;
        for ($n = 2; $n < 50; $n++) {
            $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE slug = ? LIMIT 1");
            $stmt->execute([$candidate]);
            if (!$stmt->fetchColumn()) {
                return $candidate;
            }
            $candidate = $slug . '-' . $n;
        }
        return $slug . '-' . bin2hex(random_bytes(3));
    };

    $created = ['announcements' => 0, 'events' => 0, 'ordinances' => 0];

    $log('Seeding announcements...');
    foreach ($content['announcements'] as $a) {
        $published = date('Y-m-d H:i:s', strtotime('-' . (int) $a['days_ago'] . ' days 08:00'));
        bg_insert_existing($pdo, 'announcements', [
            'title' => $a['title']['fil'], 'title_fil' => $a['title']['fil'], 'title_en' => $a['title']['en'],
            'title_manobo' => $manobo($a['title']['mn']), 'slug' => $slugFree('announcements', $a['slug']),
            'body' => $a['body']['fil'], 'body_fil' => $a['body']['fil'], 'body_en' => $a['body']['en'],
            'body_manobo' => $manobo($a['body']['mn']),
            'source_lang' => 'fil', 'en_is_auto' => 0, 'fil_is_auto' => 0, 'manobo_is_auto' => 1, 'en_review_state' => 'none',
            'category' => $a['category'], 'urgency' => $a['urgency'], 'author_id' => $author,
            'cover_image_url' => 'assets/images/posts/' . $a['cover'], 'status' => 'published',
            'published_at' => $published, 'notified_at' => true, 'notify_sms' => 0, 'is_sample' => 1,
            'created_at' => true, 'updated_at' => true,
        ]);
        $created['announcements']++;
        $log('  + ' . $a['title']['en']);
    }

    $log('Seeding events...');
    foreach ($content['events'] as $e) {
        bg_insert_existing($pdo, 'events', [
            'title' => $e['title']['fil'], 'title_fil' => $e['title']['fil'], 'title_en' => $e['title']['en'],
            'title_manobo' => $manobo($e['title']['mn']), 'slug' => $slugFree('events', $e['slug']),
            'description' => $e['body']['fil'], 'description_fil' => $e['body']['fil'], 'description_en' => $e['body']['en'],
            'description_manobo' => $manobo($e['body']['mn']),
            'source_lang' => 'fil', 'en_is_auto' => 0, 'fil_is_auto' => 0, 'manobo_is_auto' => 1,
            'venue' => $e['venue'], 'latitude' => $e['lat'], 'longitude' => $e['lng'],
            'event_date' => $e['start'], 'end_date' => $e['end'],
            'cover_image_url' => 'assets/images/posts/' . $e['cover'], 'created_by' => $author,
            'status' => 'upcoming', 'is_sample' => 1, 'created_at' => true, 'updated_at' => true,
        ]);
        $created['events']++;
        $log('  + ' . $e['title']['en']);
    }

    $log('Seeding ordinances...');
    foreach ($content['ordinances'] as $o) {
        $stmt = $pdo->prepare('SELECT 1 FROM ordinances WHERE ordinance_no = ? LIMIT 1');
        $stmt->execute([$o['number']]);
        if ($stmt->fetchColumn()) {
            $log('  = ' . $o['number'] . ' already exists (a real ordinance with this number is never replaced)');
            continue;
        }
        bg_insert_existing($pdo, 'ordinances', [
            'title' => $o['title']['fil'], 'title_fil' => $o['title']['fil'], 'title_en' => $o['title']['en'],
            'title_manobo' => $manobo($o['title']['mn']), 'ordinance_no' => $o['number'],
            'description' => $o['body']['fil'], 'description_fil' => $o['body']['fil'], 'description_en' => $o['body']['en'],
            'description_manobo' => $manobo($o['body']['mn']),
            'source_lang' => 'fil', 'en_is_auto' => 0, 'fil_is_auto' => 0, 'manobo_is_auto' => 1,
            'category' => $o['category'], 'file_url' => 'assets/samples/ordinances/' . $o['pdf'],
            'enacted_date' => $o['enacted'], 'uploaded_by' => $author, 'status' => 'active',
            'is_sample' => 1, 'created_at' => true, 'updated_at' => true,
        ]);
        $created['ordinances']++;
        $log('  + ' . $o['title']['en']);
    }

    return $created;
}

/**
 * Write the sample ordinance PDFs (committed files; run when the text changes).
 */
function bg_write_current_ordinance_pdfs(): int
{
    $root = \dirname(__DIR__);
    require_once $root . '/tools/sample-content/media.php';
    $content = require $root . '/tools/current-content/content.php';
    $dir = $root . '/public/assets/samples/ordinances';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $n = 0;
    foreach ($content['ordinances'] as $o) {
        $lines = [];
        foreach (explode("\n", $o['body']['fil'] . "\n\n" . $o['body']['en']) as $para) {
            foreach (sample_wrap($para, 80) as $line) {
                $lines[] = $line;
            }
        }
        file_put_contents($dir . '/' . $o['pdf'], sample_pdf($o['title']['fil'], $lines));
        $n++;
    }
    return $n;
}

}

// ── CLI ─────────────────────────────────────────────────────────────────
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    $root = \dirname(__DIR__);
    require $root . '/vendor/autoload.php';
    if (is_file($root . '/.env')) {
        \Dotenv\Dotenv::createImmutable($root)->safeLoad();
    }
    require $root . '/app/helpers.php';
    require $root . '/config/database.php';

    $purgeAll = in_array('--purge-all', $argv, true);
    $pdo      = db();
    $driver   = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $log      = static function (string $m): void { echo $m, "\n"; };

    echo 'PDFs written: ', bg_write_current_ordinance_pdfs(), "\n";
    $created = bg_seed_current_content($pdo, $driver, $purgeAll, $log);
    \App\Models\Setting::set('demo_current_content_v2', '1');
    echo 'Created: ', json_encode($created), "\n";
}
