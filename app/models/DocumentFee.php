<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Official document fees — the ONLY source of what a request costs.
 *
 * A request copies the fee from here when it is filed; PayMongo is charged
 * from that stored copy. Nothing a browser sends ever sets an amount.
 */
class DocumentFee
{
    /** Used only until an admin saves the fee page for the first time. */
    public const DEFAULTS = [
        'clearance' => ['amount' => 50.00,  'is_free' => false],
        'residency' => ['amount' => 30.00,  'is_free' => false],
        'indigency' => ['amount' => 0.00,   'is_free' => true],
        'business'  => ['amount' => 100.00, 'is_free' => false],
        'other'     => ['amount' => 50.00,  'is_free' => false],
    ];

    /** Highest fee the form accepts — a typo guard, not a policy. */
    public const MAX_AMOUNT = 10000.00;

    /**
     * Every document type with its fee settings and labels.
     *
     * @return array<string, array{id:int, document_type:string, label:string, amount:float, is_free:bool,
     *               is_active:bool, description:string, requirements:string, updated_by:?int, updated_at:?string}>
     */
    public static function getAll(): array
    {
        $fees = [];
        try {
            $rows = db()->query('SELECT * FROM document_fees ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $fees[(string) $r['document_type']] = self::shape($r);
            }
        } catch (\Throwable $e) {
            error_log('[DocumentFee::getAll] ' . $e->getMessage());
        }

        foreach (DocumentRequest::TYPES as $type => $label) {
            if (!isset($fees[$type])) {
                $def = self::DEFAULTS[$type] ?? ['amount' => 50.00, 'is_free' => false];
                $fees[$type] = self::shape([
                    'id' => 0, 'document_type' => $type, 'amount' => $def['amount'],
                    'is_free' => $def['is_free'] ? 1 : 0, 'is_active' => 1,
                ]);
            }
        }

        // Only the types the system issues, in the order it lists them.
        return array_intersect_key(array_replace(DocumentRequest::TYPES, $fees), DocumentRequest::TYPES);
    }

    /**
     * The fee a NEW request of this type is charged.
     *
     * @return array{amount:float, is_free:bool, is_active:bool}
     */
    public static function getFeeForType(string $type): array
    {
        $fee = self::getAll()[$type] ?? null;
        if ($fee === null) {
            return ['amount' => 0.00, 'is_free' => true, 'is_active' => false];
        }
        return [
            'amount'    => $fee['is_free'] ? 0.00 : $fee['amount'],
            'is_free'   => $fee['is_free'],
            'is_active' => $fee['is_active'],
        ];
    }

    /**
     * Save one document's settings. Returns the previous settings (for the
     * audit trail), or null when nothing was saved.
     *
     * @return array{amount:float, is_free:bool, is_active:bool}|null
     */
    public static function setFee(
        string $type,
        float $amount,
        bool $isFree,
        int $updatedBy,
        bool $isActive = true,
        string $description = '',
        string $requirements = ''
    ): ?array {
        if (!array_key_exists($type, DocumentRequest::TYPES)) {
            return null;
        }

        $before    = self::getAll()[$type];
        $amount    = $isFree ? 0.00 : min(self::MAX_AMOUNT, max(0.00, round($amount, 2)));
        $isFreeVal = ($isFree || $amount <= 0.00) ? 1 : 0;
        $args      = [$type, $amount, $isFreeVal, $isActive ? 1 : 0, mb_substr(trim($description), 0, 1000),
                      mb_substr(trim($requirements), 0, 2000), $updatedBy];

        $pdo = db();
        try {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
                $sql = 'INSERT INTO document_fees (document_type, amount, is_free, is_active, description, requirements, updated_by, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                        ON CONFLICT (document_type) DO UPDATE SET amount = EXCLUDED.amount, is_free = EXCLUDED.is_free,
                            is_active = EXCLUDED.is_active, description = EXCLUDED.description,
                            requirements = EXCLUDED.requirements, updated_by = EXCLUDED.updated_by, updated_at = NOW()';
            } else {
                $sql = 'INSERT INTO document_fees (document_type, amount, is_free, is_active, description, requirements, updated_by, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                        ON DUPLICATE KEY UPDATE amount = VALUES(amount), is_free = VALUES(is_free), is_active = VALUES(is_active),
                            description = VALUES(description), requirements = VALUES(requirements),
                            updated_by = VALUES(updated_by), updated_at = NOW()';
            }
            $pdo->prepare($sql)->execute($args);
        } catch (\Throwable $e) {
            error_log('[DocumentFee::setFee] ' . $e->getMessage());
            return null;
        }

        return ['amount' => $before['amount'], 'is_free' => $before['is_free'], 'is_active' => $before['is_active']];
    }

    private static function shape(array $r): array
    {
        $type   = (string) $r['document_type'];
        $amount = (float) ($r['amount'] ?? 0);
        return [
            'id'            => (int) ($r['id'] ?? 0),
            'document_type' => $type,
            'label'         => DocumentRequest::label($type),
            'amount'        => $amount,
            'is_free'       => ((int) ($r['is_free'] ?? 0)) === 1 || $amount <= 0.0,
            'is_active'     => ((int) ($r['is_active'] ?? 1)) === 1,
            'description'   => (string) ($r['description'] ?? ''),
            'requirements'  => (string) ($r['requirements'] ?? ''),
            'updated_by'    => !empty($r['updated_by']) ? (int) $r['updated_by'] : null,
            'updated_at'    => $r['updated_at'] ?? null,
        ];
    }
}
