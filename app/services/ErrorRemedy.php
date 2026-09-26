<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Reads a logged error, says what it means, and — for a short list of causes
 * where it is genuinely safe — repairs it.
 *
 * ── What this is not ─────────────────────────────────────────────────────
 *
 * It is not an automatic bug fixer, and it must never pretend to be one. A
 * system that edited its own source or invented `ALTER TABLE` statements from
 * the text of an exception would be guessing at the cause, and it would be
 * guessing with the barangay's live database. The same message has many
 * possible causes; picking one and acting on it is how a small bug becomes a
 * lost table.
 *
 * So the rule here is narrow and strict: a remedy is only ever the re-running
 * of a maintenance routine this codebase already trusts — replaying the
 * migration files, creating an upload folder the app expects to exist. Every
 * one is idempotent, reversible or both, and each is paired with a check that
 * looks at the system afterwards to see whether the condition is actually
 * gone.
 *
 * ── Why the check matters more than the fix ──────────────────────────────
 *
 * Marking an error "resolved" because a repair ran is the failure mode worth
 * designing against: the row disappears from the unresolved list while the bug
 * is still live, and nobody looks again. So `apply()` reports three separate
 * things — whether a remedy existed, whether it ran, and whether the condition
 * verifiably cleared — and only the third is allowed to close an error.
 *
 * Anything with no safe remedy is diagnosed and left open, with the manual
 * steps spelled out. Saying "this one needs a person, and here is why" is a
 * better answer than a button that always claims success.
 */
final class ErrorRemedy
{
    /**
     * Work out what an error is, and whether anything here can act on it.
     *
     * @param array<string,mixed> $log A row from error_logs.
     * @return array{
     *     code:string, title:string, explain:string,
     *     steps:list<string>, fixable:bool, subject:string|null
     * }  `subject` is whatever the pattern captured — a table name, a path —
     *    and is the thing apply() then acts on and re-checks.
     */
    public static function diagnose(array $log): array
    {
        $message = (string) ($log['message'] ?? '');
        $type    = (string) ($log['type'] ?? '');
        $haystack = $type . ' ' . $message;

        // ── Repairable ───────────────────────────────────────────────────

        // "Base table or view not found: 1146 Table 'baranggabay.foo' doesn't exist"
        if (preg_match("/Table '(?:[^.']*\.)?([A-Za-z0-9_]+)' doesn't exist/i", $message, $m)) {
            return self::spec('db_missing_table', $m[1], true);
        }

        // "Unknown column 'related_id' in 'where clause'"
        if (preg_match("/Unknown column '([A-Za-z0-9_.]+)'/i", $message, $m)) {
            return self::spec('db_missing_column', $m[1], true);
        }

        // Our own upload services, and PHP's own stream errors, both end up
        // here when a folder under public/uploads has gone missing.
        if (preg_match('/(?:upload|voice)\s+directory|failed to open stream: No such file or directory/i', $haystack)
            && preg_match('#(uploads[/\\\\][A-Za-z0-9_\-]+)#', $haystack, $m)) {
            return self::spec('missing_directory', str_replace('\\', '/', $m[1]), true);
        }

        // ── Diagnosable, but not ours to fix ─────────────────────────────

        if (stripos($haystack, 'credit balance is too low') !== false) {
            return self::spec('ai_no_credit', null, false);
        }

        if (preg_match('/ANTHROPIC_API_KEY|API key not configured|authentication_error/i', $haystack)) {
            return self::spec('ai_no_key', null, false);
        }

        if (preg_match('/text-to-speech provider is configured|TTS_PROVIDER/i', $haystack)) {
            return self::spec('tts_unconfigured', null, false);
        }

        if (preg_match('/Permission denied|Access denied for user/i', $haystack)) {
            return self::spec('permissions', null, false);
        }

        if (preg_match('/Connection refused|Could not resolve host|cURL error|timed out/i', $haystack)) {
            return self::spec('network', null, false);
        }

        // A script that no longer exists — a one-off someone ran and deleted.
        // Worth naming, because it is the one case where "no action needed" is
        // the correct and complete answer.
        $file = (string) ($log['file'] ?? '');
        if ($file !== '' && !is_file($file) && !str_contains($file, 'vendor')) {
            return self::spec('stale_script', basename($file), false);
        }

        return self::spec('unknown', null, false);
    }

    /**
     * Run the remedy for this error, if there is one, then check whether it
     * worked.
     *
     * Never throws: this runs from a button on the page whose job is to show
     * errors, and failing there would be its own bad joke.
     *
     * @param array<string,mixed> $log
     * @return array{
     *     code:string, attempted:bool, verified:bool,
     *     summary:string, detail:list<string>
     * }  `verified` true means the condition was re-checked and is gone. Only
     *    that may close an error.
     */
    public function apply(array $log): array
    {
        $diagnosis = self::diagnose($log);
        $code      = $diagnosis['code'];
        $subject   = $diagnosis['subject'];

        if (!$diagnosis['fixable']) {
            return [
                'code'      => $code,
                'attempted' => false,
                'verified'  => false,
                'summary'   => t('remedy.' . $code . '.title'),
                'detail'    => $diagnosis['steps'],
            ];
        }

        try {
            return match ($code) {
                'db_missing_table'  => $this->replayMigrations($code, $subject, 'table'),
                'db_missing_column' => $this->replayMigrations($code, $subject, 'column'),
                'missing_directory' => $this->createDirectory($code, (string) $subject),
                default             => $this->nothingToDo($code),
            };
        } catch (\Throwable $e) {
            error_log('[ErrorRemedy] ' . $code . ': ' . $e->getMessage());

            return [
                'code'      => $code,
                'attempted' => true,
                'verified'  => false,
                'summary'   => t('remedy.failed'),
                'detail'    => [$e->getMessage()],
            ];
        }
    }

    // ── Remedies ─────────────────────────────────────────────────────────────

    /**
     * Re-apply every migration file, then look for the missing thing again.
     *
     * Worth knowing why this is usually a no-op and still worth running:
     * runPendingMigrations() already fires on every database connection, so by
     * the time anyone reads this error the migrations have replayed hundreds of
     * times. If the table or column is still absent, replaying proves it — and
     * that is the actual finding. It means no migration defines it, so somebody
     * has to write one; no amount of re-running will conjure it.
     *
     * The failure is therefore reported as a conclusion rather than as a
     * failure to fix: "the migrations ran and this is still missing" is a more
     * useful sentence than "could not repair".
     *
     * @return array{code:string, attempted:bool, verified:bool, summary:string, detail:list<string>}
     */
    private function replayMigrations(string $code, ?string $subject, string $kind): array
    {
        $pdo = db();

        if (\function_exists('runPendingMigrations')) {
            runPendingMigrations($pdo);
        }

        $exists = $kind === 'table'
            ? $this->tableExists($pdo, (string) $subject)
            : $this->columnExists($pdo, (string) $subject);

        // A column name alone does not say which table it belonged to, so
        // "it exists somewhere" is not proof this error is gone. Said plainly
        // rather than counted as a success.
        $conclusive = $kind === 'table';

        if ($exists && $conclusive) {
            return [
                'code'      => $code,
                'attempted' => true,
                'verified'  => true,
                'summary'   => t('remedy.migrations_restored', ['name' => (string) $subject]),
                'detail'    => [t('remedy.migrations_ran')],
            ];
        }

        if (!$exists) {
            return [
                'code'      => $code,
                'attempted' => true,
                'verified'  => false,
                'summary'   => t('remedy.migrations_still_missing', ['name' => (string) $subject]),
                'detail'    => [
                    t('remedy.migrations_ran'),
                    t('remedy.migrations_write_one', ['name' => (string) $subject]),
                ],
            ];
        }

        return [
            'code'      => $code,
            'attempted' => true,
            'verified'  => false,
            'summary'   => t('remedy.column_ambiguous', ['name' => (string) $subject]),
            'detail'    => [
                t('remedy.migrations_ran'),
                t('remedy.column_ambiguous_hint', [
                    'tables' => implode(', ', $this->tablesWithColumn($pdo, (string) $subject)),
                ]),
                t('remedy.retry_to_confirm'),
            ],
        ];
    }

    /**
     * Recreate an upload folder the app expects to exist.
     *
     * The narrowest remedy here and the only one that really is a fix: a
     * missing folder has exactly one cause and exactly one repair. Confined to
     * public/uploads so a crafted path can never point this at anything else.
     *
     * @return array{code:string, attempted:bool, verified:bool, summary:string, detail:list<string>}
     */
    private function createDirectory(string $code, string $relative): array
    {
        $root = \dirname(__DIR__, 2) . '/public';
        $safe = preg_replace('#[^A-Za-z0-9_\-/]#', '', $relative) ?? '';

        if (!str_starts_with($safe, 'uploads/') || str_contains($safe, '..')) {
            return [
                'code'      => $code,
                'attempted' => false,
                'verified'  => false,
                'summary'   => t('remedy.path_refused'),
                'detail'    => [$relative],
            ];
        }

        $target = $root . '/' . $safe;

        if (!is_dir($target)) {
            @mkdir($target, 0755, true);
        }

        $ok = is_dir($target) && is_writable($target);

        return [
            'code'      => $code,
            'attempted' => true,
            'verified'  => $ok,
            'summary'   => $ok
                ? t('remedy.directory_created', ['path' => $safe])
                : t('remedy.directory_failed', ['path' => $safe]),
            'detail'    => $ok ? [] : [t('remedy.directory_failed_hint')],
        ];
    }

    /** @return array{code:string, attempted:bool, verified:bool, summary:string, detail:list<string>} */
    private function nothingToDo(string $code): array
    {
        return [
            'code'      => $code,
            'attempted' => false,
            'verified'  => false,
            'summary'   => t('remedy.no_action'),
            'detail'    => [],
        ];
    }

    // ── Schema checks ────────────────────────────────────────────────────────

    private function tableExists(\PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function columnExists(\PDO $pdo, string $column): bool
    {
        return $this->tablesWithColumn($pdo, $column) !== [];
    }

    /** @return list<string> */
    private function tablesWithColumn(\PDO $pdo, string $column): array
    {
        // A qualified name ("notifications.related_id") narrows it usefully.
        $table = null;
        if (str_contains($column, '.')) {
            [$table, $column] = explode('.', $column, 2);
        }

        $sql    = 'SELECT TABLE_NAME FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = ?';
        $params = [$column];

        if ($table !== null) {
            $sql     .= ' AND TABLE_NAME = ?';
            $params[] = $table;
        }

        $stmt = $pdo->prepare($sql . ' ORDER BY TABLE_NAME');
        $stmt->execute($params);

        return array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: []);
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /**
     * Assemble one diagnosis. Every string comes from the lang files, so the
     * explanation and the steps are translated like the rest of the panel.
     *
     * @return array{code:string, title:string, explain:string, steps:list<string>, fixable:bool, subject:string|null}
     */
    private static function spec(string $code, ?string $subject, bool $fixable): array
    {
        $replace = ['name' => (string) $subject];

        $steps = [];
        for ($i = 1; $i <= 3; $i++) {
            $key  = 'remedy.' . $code . '.step' . $i;
            $text = t($key, $replace);
            if ($text !== $key) {           // t() returns the key when unset
                $steps[] = $text;
            }
        }

        return [
            'code'    => $code,
            'title'   => t('remedy.' . $code . '.title', $replace),
            'explain' => t('remedy.' . $code . '.explain', $replace),
            'steps'   => $steps,
            'fixable' => $fixable,
            'subject' => $subject,
        ];
    }
}
