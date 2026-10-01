<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class DocumentFee
{
    /** Default document fee structure */
    public const DEFAULTS = [
        'clearance' => ['amount' => 50.00,  'is_free' => false],
        'residency' => ['amount' => 30.00,  'is_free' => false],
        'indigency' => ['amount' => 0.00,   'is_free' => true],
        'business'  => ['amount' => 100.00, 'is_free' => false],
        'other'     => ['amount' => 50.00,  'is_free' => false],
    ];

    /**
     * Get all document fee settings with labels.
     *
     * @return array<string, array{document_type: string, label: string, amount: float, is_free: bool, is_active: bool}>
     */
    public static function getAll(): array
    {
        $pdo = db();
        $fees = [];

        try {
            $stmt = $pdo->query('SELECT * FROM document_fees ORDER BY id ASC');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $type = (string) $r['document_type'];
                $fees[$type] = [
                    'id'            => (int) $r['id'],
                    'document_type' => $type,
                    'label'         => DocumentRequest::label($type),
                    'amount'        => (float) $r['amount'],
                    'is_free'       => ((int) ($r['is_free'] ?? 0)) === 1 || (float) $r['amount'] <= 0.0,
                    'is_active'     => ((int) ($r['is_active'] ?? 1)) === 1,
                    'updated_by'    => $r['updated_by'] ? (int) $r['updated_by'] : null,
                    'updated_at'    => $r['updated_at'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            error_log('[DocumentFee::getAll] ' . $e->getMessage());
        }

        // Fill any missing types from DEFAULTS
        foreach (DocumentRequest::TYPES as $type => $label) {
            if (!isset($fees[$type])) {
                $def = self::DEFAULTS[$type] ?? ['amount' => 50.00, 'is_free' => false];
                $fees[$type] = [
                    'id'            => 0,
                    'document_type' => $type,
                    'label'         => $label,
                    'amount'        => (float) $def['amount'],
                    'is_free'       => (bool) $def['is_free'],
                    'is_active'     => true,
                    'updated_by'    => null,
                    'updated_at'    => null,
                ];
            }
        }

        return $fees;
    }

    /**
     * Get fee snapshot for a specific document type.
     *
     * @return array{amount: float, is_free: bool}
     */
    public static function getFeeForType(string $type): array
    {
        try {
            $stmt = db()->prepare('SELECT amount, is_free, is_active FROM document_fees WHERE document_type = ? LIMIT 1');
            $stmt->execute([$type]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $isFree = ((int) ($row['is_free'] ?? 0)) === 1 || (float) $row['amount'] <= 0.0;
                return [
                    'amount'  => $isFree ? 0.00 : (float) $row['amount'],
                    'is_free' => $isFree,
                ];
            }
        } catch (\Throwable $e) {
            error_log('[DocumentFee::getFeeForType] ' . $e->getMessage());
        }

        $def = self::DEFAULTS[$type] ?? ['amount' => 50.00, 'is_free' => false];
        return [
            'amount'  => (bool) $def['is_free'] ? 0.00 : (float) $def['amount'],
            'is_free' => (bool) $def['is_free'],
        ];
    }

    /**
     * Update or create fee for a document type.
     */
    public static function setFee(string $type, float $amount, bool $isFree, int $updatedBy): bool
    {
        if (!array_key_exists($type, DocumentRequest::TYPES)) {
            return false;
        }

        $amount = $isFree ? 0.00 : max(0.00, round($amount, 2));
        $isFreeVal = ($isFree || $amount <= 0.00) ? 1 : 0;

        $pdo = db();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO document_fees (document_type, amount, is_free, is_active, updated_by, created_at, updated_at)
                 VALUES (?, ?, ?, 1, ?, NOW(), NOW())
                 ON CONFLICT (document_type) 
                 DO UPDATE SET amount = EXCLUDED.amount, is_free = EXCLUDED.is_free, updated_by = EXCLUDED.updated_by, updated_at = NOW()'
            );
            return $stmt->execute([$type, $amount, $isFreeVal, $updatedBy]);
        } catch (\Throwable $e) {
            // MySQL fallback if ON CONFLICT syntax differs
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO document_fees (document_type, amount, is_free, is_active, updated_by, created_at, updated_at)
                     VALUES (?, ?, ?, 1, ?, NOW(), NOW())
                     ON DUPLICATE KEY UPDATE amount = VALUES(amount), is_free = VALUES(is_free), updated_by = VALUES(updated_by), updated_at = NOW()'
                );
                return $stmt->execute([$type, $amount, $isFreeVal, $updatedBy]);
            } catch (\Throwable $e2) {
                error_log('[DocumentFee::setFee] ' . $e2->getMessage());
                return false;
            }
        }
    }
}
