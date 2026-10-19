<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Removes the demo accounts the seeders created — and only their own data —
 * before the system is used for real.
 *
 * What counts as demo: the fixed seeder emails below, plus throwaway test
 * domains (@test.local, @example.com/org). Nothing is matched by name, date
 * or status, so a real resident's account, document request or payment can
 * never be caught. A superadmin is never removed (admin@baranggabay.ph is the
 * owner's login even though a seeder created it).
 *
 * For each demo account:
 *   - its document requests and payments (and their files, logs and payment
 *     audit rows) are deleted;
 *   - content it authored (sample announcements, events, ordinances, media)
 *     is reassigned to the oldest real superadmin, so no page loses its post;
 *   - every other row pointing at it follows the database's own rule
 *     (CASCADE deletes, SET NULL keeps the row), read from the live schema;
 *   - the account itself is deleted.
 *
 * Everything runs in one transaction. preview() does the same work and rolls
 * it back, so the counts it reports are exactly what run() would change.
 */
final class DemoCleanup
{
    /** Accounts created by tools/seed_comprehensive_demo.php, seed_content.php and DemoDataSeeder.php. */
    public const DEMO_EMAILS = [
        'rosa.santos@baranggabay.ph', 'miguel.delacruz@baranggabay.ph',
        'michelle@baranggabay.ph', 'juan.delacruz@baranggabay.ph',
        'maria.clara@baranggabay.ph', 'pedro.penduko@baranggabay.ph',
        'carla.rivera@barangay.ph', 'johnpaolo.gutierrez@barangay.ph', 'liza.navarro@barangay.ph',
        'elias.mercado@barangay.ph', 'aileen.ramos@barangay.ph', 'noel.bautista@barangay.ph',
        'mara.velasco@barangay.ph', 'renan.magtulis@barangay.ph', 'kristine.oliva@barangay.ph',
        'joel.martinez@barangay.ph',
    ];

    /** Throwaway domains no real resident registers with. */
    public const DEMO_DOMAINS = ['test.local', 'example.com', 'example.org'];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * What run() would remove, without changing anything.
     *
     * @return array{accounts: list<array{id:int,email:string,role:string}>, counts: array<string,int>, kept_owner: ?int}
     */
    public function preview(): array
    {
        return $this->execute(false);
    }

    /**
     * Remove the demo accounts and their own requests/payments.
     *
     * @return array{accounts: list<array{id:int,email:string,role:string}>, counts: array<string,int>, kept_owner: ?int}
     */
    public function run(): array
    {
        return $this->execute(true);
    }

    /**
     * Remove duplicate SAMPLE posts. Two Render instances booting together
     * both ran the sample seeder, so every sample announcement/event/ordinance
     * existed twice (the copy got a "-2" slug). Keeps the oldest copy of each
     * title; only is_sample = 1 rows are ever touched. Safe to run on every boot.
     *
     * @return array<string,int> rows removed per table
     */
    public function removeDuplicateSamples(): array
    {
        $removed = [];
        foreach (['announcements' => 'announcement', 'events' => 'event', 'ordinances' => 'ordinance'] as $table => $type) {
            $ids = $this->ids(
                "SELECT dup.id FROM {$table} dup
                   JOIN {$table} kept ON kept.title = dup.title AND kept.is_sample = 1 AND kept.id < dup.id
                  WHERE dup.is_sample = 1",
                []
            );
            $ids = array_values(array_unique($ids));
            if ($ids === []) {
                continue;
            }
            $this->exec(
                'DELETE FROM notifications WHERE related_type = ? AND related_id IN (' . $this->marks($ids) . ')',
                [$type, ...$ids]
            );
            $removed[$table] = $this->exec("DELETE FROM {$table} WHERE id IN (" . $this->marks($ids) . ')', $ids);
        }
        return $removed;
    }

    /** @return list<array{id:int,email:string,role:string}> */
    public function demoAccounts(): array
    {
        $emails = array_map('strtolower', self::DEMO_EMAILS);
        $where  = 'LOWER(email) IN (' . $this->marks($emails) . ')';
        $params = $emails;
        foreach (self::DEMO_DOMAINS as $domain) {
            $where   .= ' OR LOWER(email) LIKE ?';
            $params[] = '%@' . $domain;
        }
        $stmt = $this->pdo->prepare(
            "SELECT id, email, role FROM users WHERE ({$where}) AND role <> 'superadmin' ORDER BY id"
        );
        $stmt->execute($params);

        return array_map(
            static fn (array $row): array => ['id' => (int) $row['id'], 'email' => (string) $row['email'], 'role' => (string) $row['role']],
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    private function execute(bool $commit): array
    {
        $accounts = $this->demoAccounts();
        $ids      = array_column($accounts, 'id');
        $counts   = [];
        if ($ids === []) {
            return ['accounts' => [], 'counts' => [], 'kept_owner' => null];
        }

        $owner = $this->pdo->prepare(
            "SELECT id FROM users WHERE role = 'superadmin' AND id NOT IN ({$this->marks($ids)}) ORDER BY id LIMIT 1"
        );
        $owner->execute($ids);
        $ownerId = (int) ($owner->fetchColumn() ?: 0);
        if ($ownerId === 0) {
            throw new \RuntimeException('No real superadmin to take over the demo accounts\' posts; nothing removed.');
        }

        $this->pdo->beginTransaction();
        try {
            // 1. Their own document requests and payments.
            $requestIds = $this->ids('SELECT id FROM document_requests WHERE user_id IN (' . $this->marks($ids) . ')', $ids);
            $paymentIds = $this->ids(
                'SELECT id FROM document_payments WHERE user_id IN (' . $this->marks($ids) . ')'
                . ($requestIds ? ' OR request_id IN (' . $this->marks($requestIds) . ')' : ''),
                [...$ids, ...$requestIds]
            );
            if ($paymentIds || $requestIds) {
                $counts['payment_audit_logs'] = $this->exec(
                    'DELETE FROM payment_audit_logs WHERE '
                    . ($paymentIds ? 'payment_id IN (' . $this->marks($paymentIds) . ')' : '1 = 0')
                    . ($requestIds ? ' OR request_id IN (' . $this->marks($requestIds) . ')' : ''),
                    [...$paymentIds, ...$requestIds]
                );
            }
            if ($paymentIds) {
                $counts['document_payments'] = $this->exec(
                    'DELETE FROM document_payments WHERE id IN (' . $this->marks($paymentIds) . ')', $paymentIds
                );
            }
            if ($requestIds) {
                foreach (['document_request_files', 'document_request_logs'] as $child) {
                    $counts[$child] = $this->exec(
                        "DELETE FROM {$child} WHERE request_id IN (" . $this->marks($requestIds) . ')', $requestIds
                    );
                }
                $counts['document_requests'] = $this->exec(
                    'DELETE FROM document_requests WHERE id IN (' . $this->marks($requestIds) . ')', $requestIds
                );
            }

            // 2. Rows the database would refuse to orphan: authored content
            //    moves to the owner; rows that are the account's own go.
            foreach ($this->restrictingForeignKeys() as [$table, $column]) {
                if ($table === 'users') {
                    continue;
                }
                $sql = $column === 'user_id'
                    ? "DELETE FROM {$table} WHERE {$column} IN (" . $this->marks($ids) . ')'
                    : "UPDATE {$table} SET {$column} = ? WHERE {$column} IN (" . $this->marks($ids) . ')';
                $n = $this->exec($sql, $column === 'user_id' ? $ids : [$ownerId, ...$ids]);
                if ($n > 0) {
                    $counts[($column === 'user_id' ? 'deleted ' : 'reassigned ') . "{$table}.{$column}"] = $n;
                }
            }

            // 3. The accounts (CASCADE / SET NULL rows follow the schema).
            $counts['users'] = $this->exec('DELETE FROM users WHERE id IN (' . $this->marks($ids) . ')', $ids);

            $commit ? $this->pdo->commit() : $this->pdo->rollBack();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return ['accounts' => $accounts, 'counts' => $counts, 'kept_owner' => $ownerId];
    }

    /**
     * Foreign keys to users(id) whose delete rule blocks deleting a user
     * (RESTRICT / NO ACTION), read from the live schema so tables added
     * later are covered without editing this list.
     *
     * @return list<array{0:string,1:string}>
     */
    private function restrictingForeignKeys(): array
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $sql = "SELECT kcu.table_name, kcu.column_name, rc.delete_rule
                      FROM information_schema.referential_constraints rc
                      JOIN information_schema.key_column_usage kcu
                        ON kcu.constraint_name = rc.constraint_name AND kcu.constraint_schema = rc.constraint_schema
                      JOIN information_schema.constraint_column_usage ccu
                        ON ccu.constraint_name = rc.constraint_name AND ccu.constraint_schema = rc.constraint_schema
                     WHERE ccu.table_name = 'users' AND ccu.column_name = 'id' AND rc.constraint_schema = current_schema()";
        } else {
            $sql = "SELECT k.TABLE_NAME AS table_name, k.COLUMN_NAME AS column_name, r.DELETE_RULE AS delete_rule
                      FROM information_schema.KEY_COLUMN_USAGE k
                      JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                        ON r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
                       AND r.TABLE_NAME = k.TABLE_NAME
                     WHERE k.TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME = 'users'";
        }

        $keys = [];
        foreach ($this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $row = array_change_key_case($row, CASE_LOWER);
            if (in_array(strtoupper((string) $row['delete_rule']), ['RESTRICT', 'NO ACTION'], true)
                && preg_match('/^\w+$/', (string) $row['table_name'] . (string) $row['column_name'])) {
                $keys[$row['table_name'] . '.' . $row['column_name']] = [(string) $row['table_name'], (string) $row['column_name']];
            }
        }
        return array_values($keys);
    }

    /** @return list<int> */
    private function ids(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    private function exec(string $sql, array $params): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    private function marks(array $values): string
    {
        return implode(', ', array_fill(0, max(1, count($values)), '?'));
    }
}
