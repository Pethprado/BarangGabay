<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Where each purok goes when a warning is raised.
 *
 * Bayogo is coastal and on the typhoon side of Mindanao. During a warning a
 * resident does not want the barangay's full list of centres — they want the
 * answer to "where do I go", which is a different and much faster question
 * when the wind is already up and the phone is on low battery.
 */
class EvacuationCenter
{
    /**
     * The centres serving one purok: its own, plus any that serve the whole
     * barangay.
     *
     * Barangay-wide centres are included rather than filtered out because a
     * purok with no centre of its own must still be told somewhere to go. An
     * empty answer during a storm warning is the one outcome this must never
     * produce.
     */
    public static function forPurok(?string $purok): array
    {
        if ($purok === null || trim($purok) === '') {
            return self::all();
        }

        $stmt = db()->prepare(
            'SELECT * FROM evacuation_centers
              WHERE is_active = 1 AND (purok = ? OR purok IS NULL)
              ORDER BY (purok IS NULL), name'      // the purok’s own first
        );
        $stmt->execute([$purok]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Every active centre, purok order. */
    public static function all(): array
    {
        return db()->query(
            'SELECT * FROM evacuation_centers WHERE is_active = 1
              ORDER BY (purok IS NULL), purok, name'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Every centre including retired ones — the admin list. */
    public static function allAdmin(): array
    {
        return db()->query(
            'SELECT * FROM evacuation_centers ORDER BY is_active DESC, (purok IS NULL), purok, name'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM evacuation_centers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        db()->prepare(
            'INSERT INTO evacuation_centers
             (name, purok, address, latitude, longitude, capacity, contact_person, contact_phone, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute(self::bind($data));

        return (int) db()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        $params   = self::bind($data);
        $params[] = $id;

        db()->prepare(
            'UPDATE evacuation_centers
                SET name = ?, purok = ?, address = ?, latitude = ?, longitude = ?,
                    capacity = ?, contact_person = ?, contact_phone = ?, is_active = ?
              WHERE id = ?'
        )->execute($params);
    }

    public static function delete(int $id): void
    {
        db()->prepare('DELETE FROM evacuation_centers WHERE id = ?')->execute([$id]);
    }

    /**
     * Normalise a form payload into bind order.
     *
     * An empty purok becomes NULL rather than '' — NULL is the meaningful
     * value here ("serves the whole barangay") and an empty string would match
     * no purok at all, quietly hiding the centre from every resident.
     *
     * @param  array<string,mixed> $d
     * @return list<mixed>
     */
    private static function bind(array $d): array
    {
        $purok = trim((string) ($d['purok'] ?? ''));
        $lat   = trim((string) ($d['latitude'] ?? ''));
        $lng   = trim((string) ($d['longitude'] ?? ''));
        $cap   = trim((string) ($d['capacity'] ?? ''));

        return [
            mb_substr(trim((string) ($d['name'] ?? '')), 0, 150),
            $purok !== '' ? $purok : null,
            mb_substr(trim((string) ($d['address'] ?? '')), 0, 255) ?: null,
            $lat !== '' ? (float) $lat : null,
            $lng !== '' ? (float) $lng : null,
            $cap !== '' ? (int) $cap : null,
            mb_substr(trim((string) ($d['contact_person'] ?? '')), 0, 120) ?: null,
            mb_substr(trim((string) ($d['contact_phone'] ?? '')), 0, 30) ?: null,
            !empty($d['is_active']) ? 1 : 0,
        ];
    }
}
