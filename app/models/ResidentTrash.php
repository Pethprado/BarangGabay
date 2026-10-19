<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DemoCleanup;
use PDO;

/**
 * Resident Trash: deleting a resident is reversible.
 *
 * "Delete" keeps the account row and marks it (deleted_at / deleted_by /
 * deleted_prev_status) and sets status to 'suspended', so a deleted resident
 * cannot log in and is left out of every verified-only feature
 * (notifications, SMS, AI, safety check-ins) without touching those queries.
 * Their document requests, payments and history stay, so "Restore" brings the
 * account back exactly as it was, with its previous status.
 *
 * "Delete forever" (admins) removes the account through DemoCleanup's safe
 * path: their own requests/payments go, posts they wrote move to the owner.
 */
final class ResidentTrash
{
    /** Move a resident to the Trash. Returns false if not a live resident. */
    public static function trash(int $userId, int $byUserId): bool
    {
        $stmt = db()->prepare(
            "UPDATE users
                SET deleted_prev_status = status, status = 'suspended',
                    deleted_at = NOW(), deleted_by = ?, updated_at = NOW()
              WHERE id = ? AND role = 'resident' AND deleted_at IS NULL"
        );
        $stmt->execute([$byUserId, $userId]);
        if ($stmt->rowCount() === 0) {
            return false;
        }
        UserSession::revokeAllForUser($userId);   // signed out everywhere
        return true;
    }

    /** Bring a resident back with the status they had before. */
    public static function restore(int $userId): bool
    {
        $stmt = db()->prepare(
            "UPDATE users
                SET status = COALESCE(deleted_prev_status, 'pending'),
                    deleted_at = NULL, deleted_by = NULL, deleted_prev_status = NULL, updated_at = NOW()
              WHERE id = ? AND role = 'resident' AND deleted_at IS NOT NULL"
        );
        $stmt->execute([$userId]);
        return $stmt->rowCount() > 0;
    }

    /** Permanently delete a resident that is already in the Trash. */
    public static function purge(int $userId): bool
    {
        $stmt = db()->prepare("SELECT 1 FROM users WHERE id = ? AND role = 'resident' AND deleted_at IS NOT NULL");
        $stmt->execute([$userId]);
        if (!$stmt->fetchColumn()) {
            return false;
        }
        $result = (new DemoCleanup(db()))->purgeAccounts([$userId]);
        return ($result['counts']['users'] ?? 0) > 0;
    }

    /**
     * Trashed residents grouped by purok (official purok order first, then
     * any other purok name, then "No purok").
     *
     * @return array<string, list<array<string,mixed>>>
     */
    public static function byPurok(): array
    {
        $rows = db()->query(
            "SELECT u.id, u.full_name, u.email, u.phone, u.zone, u.deleted_at, u.deleted_prev_status,
                    d.full_name AS deleted_by_name
               FROM users u
               LEFT JOIN users d ON d.id = u.deleted_by
              WHERE u.role = 'resident' AND u.deleted_at IS NOT NULL
              ORDER BY u.deleted_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $groups = [];
        foreach (barangay_subdivisions() as $purok) {
            $groups[$purok] = [];
        }
        $none = 'No purok';   // back office is English-only
        foreach ($rows as $row) {
            $purok = trim((string) ($row['zone'] ?? ''));
            $groups[$purok !== '' ? $purok : $none][] = $row;
        }
        return array_filter($groups, static fn (array $list): bool => $list !== []);
    }

    public static function count(): int
    {
        try {
            return (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'resident' AND deleted_at IS NOT NULL")->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }
}
