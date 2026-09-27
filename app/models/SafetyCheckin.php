<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * "Ligtas ako" — a resident's one-tap answer after a storm.
 *
 * The useful output of this is not the list of the safe. It is the list of the
 * SILENT, broken down by purok, so the tanod know which houses to walk to
 * first. Everything here is shaped around producing that list quickly.
 *
 * A check-in always answers a specific advisory, never floats free. "Safe" has
 * to mean "safe as of this storm"; a tap from last year's typhoon must not be
 * readable as today's answer.
 */
class SafetyCheckin
{
    /**
     * Record or update one resident's answer.
     *
     * Tapping again updates rather than duplicates — a resident who marked
     * themselves safe and then needed help must be able to say so, and that
     * correction is the most important write this table takes.
     */
    public static function record(int $advisoryId, int $userId, string $status, string $note = ''): void
    {
        $status = \in_array($status, ['safe', 'needs_help'], true) ? $status : 'safe';

        // The purok is copied in rather than joined at read time, so the
        // roll-up for a past storm still reflects where the resident lived
        // when they answered, even if they later move.
        $stmt = db()->prepare('SELECT zone FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $purok = $stmt->fetchColumn() ?: null;

        $isPgsql = (db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql');
        $sql = $isPgsql
            ? 'INSERT INTO safety_checkins (advisory_id, user_id, status, purok, note, checked_in_at)
               VALUES (?, ?, ?, ?, ?, NOW())
               ON CONFLICT (advisory_id, user_id) DO UPDATE SET
                  status = EXCLUDED.status,
                  note   = EXCLUDED.note,
                  purok  = EXCLUDED.purok,
                  checked_in_at = NOW()'
            : 'INSERT INTO safety_checkins (advisory_id, user_id, status, purok, note, checked_in_at)
               VALUES (?, ?, ?, ?, ?, NOW())
               ON DUPLICATE KEY UPDATE
                  status = VALUES(status),
                  note   = VALUES(note),
                  purok  = VALUES(purok),
                  checked_in_at = NOW()';

        db()->prepare($sql)->execute([
            $advisoryId,
            $userId,
            $status,
            $purok !== null ? (string) $purok : null,
            trim($note) !== '' ? mb_substr(trim($note), 0, 255) : null,
        ]);
    }

    /** One resident's answer to one advisory, or null if they have not said. */
    public static function forUser(int $advisoryId, int $userId): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM safety_checkins WHERE advisory_id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$advisoryId, $userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * The roll-up the barangay actually acts on.
     *
     * Per purok: how many verified residents there are, how many said they are
     * safe, how many asked for help, and — the number that matters — how many
     * have not answered at all.
     *
     * @return list<array{purok:string, residents:int, safe:int, needs_help:int, silent:int}>
     */
    public static function rollUp(int $advisoryId): array
    {
        $stmt = db()->prepare(
            "SELECT COALESCE(NULLIF(u.zone, ''), '(walang purok)') AS purok,
                    COUNT(*)                                          AS residents,
                    SUM(c.status = 'safe')                            AS safe,
                    SUM(c.status = 'needs_help')                      AS needs_help,
                    SUM(c.id IS NULL)                                 AS silent
               FROM users u
               LEFT JOIN safety_checkins c
                      ON c.user_id = u.id AND c.advisory_id = ?
              WHERE u.role = 'resident' AND u.status = 'verified'
              GROUP BY purok
              ORDER BY needs_help DESC, silent DESC, purok"
        );
        $stmt->execute([$advisoryId]);

        return array_map(static fn (array $r): array => [
            'purok'      => (string) $r['purok'],
            'residents'  => (int) $r['residents'],
            'safe'       => (int) $r['safe'],
            'needs_help' => (int) $r['needs_help'],
            'silent'     => (int) $r['silent'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * The residents who asked for help, and the ones who have not answered.
     *
     * Names and phone numbers, because the next action is someone picking up a
     * phone or walking to a house.
     */
    public static function needingAttention(int $advisoryId): array
    {
        $stmt = db()->prepare(
            "SELECT u.id, u.full_name, u.phone, u.zone, u.address,
                    COALESCE(c.status, 'silent') AS state, c.note, c.checked_in_at
               FROM users u
               LEFT JOIN safety_checkins c
                      ON c.user_id = u.id AND c.advisory_id = ?
              WHERE u.role = 'resident' AND u.status = 'verified'
                AND (c.id IS NULL OR c.status = 'needs_help')
              ORDER BY FIELD(COALESCE(c.status,'silent'),'needs_help','silent'), u.zone, u.full_name"
        );
        $stmt->execute([$advisoryId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * How long a check-in request keeps asking.
     *
     * Without a limit the dashboard would still be asking "are you safe?"
     * about a typhoon that passed in March. Worse, it would keep counting
     * people as silent long after the emergency ended, so the one number
     * the barangay acts on would slowly fill with noise. Seven days is past
     * the point where a check-in is still news and well inside the window
     * where someone cut off by a landslide might only now be back online.
     */
    public const ASKS_FOR_DAYS = 7;

    /**
     * The check-in this resident is currently being asked for, if any.
     *
     * Returns the advisory together with THIS resident's answer to it, so
     * the dashboard can render the card in one query rather than asking for
     * the advisory and then asking again whether they replied.
     *
     * Null is the ordinary case: most days nobody is being asked anything,
     * and the card renders nothing at all.
     *
     * @return array{id:int, title:string, slug:string, published_at:string,
     *               my_status:?string, my_note:?string, my_checked_in_at:?string}|null
     */
    public static function openForUser(int $userId): ?array
    {
        $isPgsql = (db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql');
        $dateCond = $isPgsql
            ? "a.published_at >= NOW() - INTERVAL '" . self::ASKS_FOR_DAYS . " days'"
            : "a.published_at >= DATE_SUB(NOW(), INTERVAL " . self::ASKS_FOR_DAYS . " DAY)";

        $stmt = db()->prepare(
            "SELECT a.id, a.title, a.slug, a.published_at,
                    c.status        AS my_status,
                    c.note          AS my_note,
                    c.checked_in_at AS my_checked_in_at
               FROM announcements a
               LEFT JOIN safety_checkins c
                      ON c.advisory_id = a.id AND c.user_id = ?
              WHERE a.asks_safety_checkin = 1
                AND a.status = 'published'
                AND a.published_at IS NOT NULL
                AND {$dateCond}
              ORDER BY a.published_at DESC
              LIMIT 1"
        );
        $stmt->execute([$userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return [
            'id'               => (int) $row['id'],
            'title'            => (string) $row['title'],
            'slug'             => (string) $row['slug'],
            'published_at'     => (string) $row['published_at'],
            'my_status'        => $row['my_status'] !== null ? (string) $row['my_status'] : null,
            'my_note'          => $row['my_note'] !== null ? (string) $row['my_note'] : null,
            'my_checked_in_at' => $row['my_checked_in_at'] !== null ? (string) $row['my_checked_in_at'] : null,
        ];
    }

    /**
     * How one purok is answering — COUNTS ONLY.
     *
     * This is the figure a resident is shown, and it is deliberately not the
     * same data the barangay gets. needingAttention() returns names, phone
     * numbers and addresses because a tanod is about to walk to a house.
     * A neighbour needs none of that to be useful: knowing that four people
     * on your street have not answered is enough to make you knock, and it
     * discloses nothing about who they are.
     *
     * So this returns four integers and nothing else. If that ever needs to
     * become a list of names, it becomes an admin screen, not this one.
     *
     * @return array{purok:string, residents:int, answered:int, safe:int,
     *                needs_help:int, silent:int}|null
     */
    public static function purokPulse(?string $purok, int $advisoryId): ?array
    {
        $purok = $purok !== null ? trim($purok) : '';
        if ($purok === '' || $advisoryId <= 0) {
            return null;
        }

        $stmt = db()->prepare(
            "SELECT COUNT(*)                        AS residents,
                    SUM(c.id IS NOT NULL)           AS answered,
                    SUM(c.status = 'safe')          AS safe,
                    SUM(c.status = 'needs_help')    AS needs_help,
                    SUM(c.id IS NULL)               AS silent
               FROM users u
               LEFT JOIN safety_checkins c
                      ON c.user_id = u.id AND c.advisory_id = ?
              WHERE u.role = 'resident' AND u.status = 'verified'
                AND u.zone = ?"
        );
        $stmt->execute([$advisoryId, $purok]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || (int) $row['residents'] === 0) {
            return null;
        }

        return [
            'purok'      => $purok,
            'residents'  => (int) $row['residents'],
            'answered'   => (int) $row['answered'],
            'safe'       => (int) $row['safe'],
            'needs_help' => (int) $row['needs_help'],
            'silent'     => (int) $row['silent'],
        ];
    }

    /** Advisories that asked for a check-in, newest first. */
    public static function activeAdvisories(): array
    {
        return db()->query(
            "SELECT id, title, slug, published_at
               FROM announcements
              WHERE asks_safety_checkin = 1
              ORDER BY published_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
