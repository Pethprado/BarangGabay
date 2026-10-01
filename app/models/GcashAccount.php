<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class GcashAccount
{
    /**
     * Get the default active GCash account for receiving payments.
     */
    public static function getDefault(): ?array
    {
        try {
            $stmt = db()->prepare(
                'SELECT * FROM gcash_accounts 
                  WHERE is_active = 1 AND is_default = 1 
                  ORDER BY id ASC LIMIT 1'
            );
            $stmt->execute();
            $acc = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($acc) {
                return $acc;
            }

            // Fallback to any active account
            $stmt = db()->prepare(
                'SELECT * FROM gcash_accounts 
                  WHERE is_active = 1 
                  ORDER BY id ASC LIMIT 1'
            );
            $stmt->execute();
            $acc = $stmt->fetch(PDO::FETCH_ASSOC);
            return $acc ?: null;
        } catch (\Throwable $e) {
            error_log('[GcashAccount::getDefault] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all active accounts (for staff selection).
     */
    public static function getActive(): array
    {
        try {
            $stmt = db()->query(
                'SELECT * FROM gcash_accounts WHERE is_active = 1 ORDER BY is_default DESC, id ASC'
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[GcashAccount::getActive] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all accounts for Admin Management.
     */
    public static function getAll(): array
    {
        try {
            $stmt = db()->query(
                'SELECT a.*, cu.full_name AS creator_name, uu.full_name AS updater_name
                   FROM gcash_accounts a
              LEFT JOIN users cu ON cu.id = a.created_by
              LEFT JOIN users uu ON uu.id = a.updated_by
                  ORDER BY a.is_default DESC, a.is_active DESC, a.id ASC'
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[GcashAccount::getAll] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Find single account by ID.
     */
    public static function find(int $id): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM gcash_accounts WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Create a new GCash receiving account.
     */
    public static function create(array $data, int $userId): int
    {
        $pdo = db();
        $isDefault = !empty($data['is_default']) ? 1 : 0;

        // If this is set as default, unset other defaults
        if ($isDefault === 1) {
            $pdo->exec('UPDATE gcash_accounts SET is_default = 0');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO gcash_accounts 
             (account_name, mobile_number, qr_image_data, qr_mime_type, description, is_default, is_active, created_by, updated_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            trim((string) $data['account_name']),
            trim((string) $data['mobile_number']),
            $data['qr_image_data'] ?? null,
            $data['qr_mime_type'] ?? null,
            trim((string) ($data['description'] ?? '')),
            $isDefault,
            !empty($data['is_active']) ? 1 : 0,
            $userId,
            $userId,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Update an existing GCash account.
     */
    public static function update(int $id, array $data, int $userId): bool
    {
        $pdo = db();
        $isDefault = !empty($data['is_default']) ? 1 : 0;

        if ($isDefault === 1) {
            $pdo->prepare('UPDATE gcash_accounts SET is_default = 0 WHERE id != ?')->execute([$id]);
        }

        $updateQr = !empty($data['qr_image_data']);
        $sql = 'UPDATE gcash_accounts 
                   SET account_name = ?, mobile_number = ?, description = ?, is_default = ?, is_active = ?, updated_by = ?, updated_at = NOW()';
        $params = [
            trim((string) $data['account_name']),
            trim((string) $data['mobile_number']),
            trim((string) ($data['description'] ?? '')),
            $isDefault,
            !empty($data['is_active']) ? 1 : 0,
            $userId,
        ];

        if ($updateQr) {
            $sql .= ', qr_image_data = ?, qr_mime_type = ?';
            $params[] = $data['qr_image_data'];
            $params[] = $data['qr_mime_type'];
        }

        $sql .= ' WHERE id = ?';
        $params[] = $id;

        return $pdo->prepare($sql)->execute($params);
    }

    /**
     * Set one account as the default.
     */
    public static function setDefault(int $id, int $userId): bool
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->exec('UPDATE gcash_accounts SET is_default = 0');
            $stmt = $pdo->prepare('UPDATE gcash_accounts SET is_default = 1, is_active = 1, updated_by = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$userId, $id]);
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[GcashAccount::setDefault] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Toggle active state.
     */
    public static function toggleActive(int $id, int $userId): bool
    {
        $acc = self::find($id);
        if (!$acc) {
            return false;
        }
        $newActive = ((int) ($acc['is_active'] ?? 1)) === 1 ? 0 : 1;
        // Cannot deactivate default account if it's the only active one
        if ($newActive === 0 && ((int) ($acc['is_default'] ?? 0)) === 1) {
            return false;
        }

        $stmt = db()->prepare('UPDATE gcash_accounts SET is_active = ?, updated_by = ?, updated_at = NOW() WHERE id = ?');
        return $stmt->execute([$newActive, $userId, $id]);
    }
}
