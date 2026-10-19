<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Puroks of the barangay and who lives in each.
 *
 * A resident's purok is users.zone (the column registration, SMS-by-purok
 * and evacuation already read). Only resident accounts are counted: staff
 * and admins are roles, not households.
 *
 * Residents added by staff without an email get a placeholder address on
 * NO_EMAIL_DOMAIN and an unusable password: they are counted and can be
 * texted, but cannot sign in until they register an email of their own.
 */
final class Purok
{
    public const NO_EMAIL_DOMAIN = 'no-email.baranggabay.local';

    /**
     * Barangay-wide resident totals.
     *
     * @return array{total:int, verified:int, pending:int, suspended:int, no_purok:int, with_phone:int}
     */
    public static function totals(): array
    {
        $row = db()->query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'verified'  THEN 1 ELSE 0 END) AS verified,
                    SUM(CASE WHEN status = 'pending'   THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) AS suspended,
                    SUM(CASE WHEN zone IS NULL OR TRIM(zone) = '' THEN 1 ELSE 0 END) AS no_purok,
                    SUM(CASE WHEN phone IS NOT NULL AND TRIM(phone) <> '' THEN 1 ELSE 0 END) AS with_phone
               FROM users
              WHERE role = 'resident' AND deleted_at IS NULL"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $out = [];
        foreach (['total', 'verified', 'pending', 'suspended', 'no_purok', 'with_phone'] as $key) {
            $out[$key] = (int) ($row[$key] ?? 0);
        }

        return $out;
    }

    /**
     * One row per purok: the barangay's official list first (even when empty),
     * then any other purok name found on accounts.
     *
     * @return list<array{name:string, total:int, verified:int, pending:int, suspended:int, with_phone:int}>
     */
    public static function summary(): array
    {
        $rows = db()->query(
            "SELECT TRIM(zone) AS name,
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'verified'  THEN 1 ELSE 0 END) AS verified,
                    SUM(CASE WHEN status = 'pending'   THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) AS suspended,
                    SUM(CASE WHEN phone IS NOT NULL AND TRIM(phone) <> '' THEN 1 ELSE 0 END) AS with_phone
               FROM users
              WHERE role = 'resident' AND deleted_at IS NULL AND zone IS NOT NULL AND TRIM(zone) <> ''
              GROUP BY TRIM(zone)"
        )->fetchAll(PDO::FETCH_ASSOC);

        $found = [];
        foreach ($rows as $row) {
            $found[(string) $row['name']] = $row;
        }

        $names = barangay_subdivisions();
        foreach (array_keys($found) as $name) {
            if (!in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        $out = [];
        foreach ($names as $name) {
            $row   = $found[$name] ?? [];
            $out[] = [
                'name'       => $name,
                'total'      => (int) ($row['total'] ?? 0),
                'verified'   => (int) ($row['verified'] ?? 0),
                'pending'    => (int) ($row['pending'] ?? 0),
                'suspended'  => (int) ($row['suspended'] ?? 0),
                'with_phone' => (int) ($row['with_phone'] ?? 0),
            ];
        }

        return $out;
    }

    /** True for a purok name staff may assign: the official list or one already in use. */
    public static function isKnown(string $name): bool
    {
        $name = trim($name);
        if ($name === '') {
            return false;
        }
        foreach (self::summary() as $row) {
            if ($row['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Residents of one purok ('' = residents with no purok yet).
     *
     * @return list<array<string,mixed>>
     */
    public static function members(string $purok, string $search = ''): array
    {
        [$where, $params] = self::purokWhere($purok, true);
        self::addSearch($where, $params, $search);

        $stmt = db()->prepare(
            'SELECT id, full_name, email, phone, address, status, created_at
               FROM users
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY full_name'
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Residents who are NOT in this purok, to pick from when adding.
     *
     * @return list<array<string,mixed>>
     */
    public static function candidates(string $purok, string $search = '', int $limit = 50): array
    {
        [$where, $params] = self::purokWhere($purok, false);
        self::addSearch($where, $params, $search);
        $limit = max(1, min(200, $limit));

        $stmt = db()->prepare(
            'SELECT id, full_name, email, phone, zone, status
               FROM users
              WHERE ' . implode(' AND ', $where) . "
              ORDER BY full_name
              LIMIT {$limit}"
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Put residents in a purok, or take them out of any purok ($purok = '').
     * Only resident accounts are touched.
     *
     * @param list<int> $userIds
     * @return int How many accounts changed.
     */
    public static function assign(array $userIds, string $purok): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return 0;
        }

        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt  = db()->prepare(
            "UPDATE users SET zone = ?, updated_at = NOW()
              WHERE role = 'resident' AND id IN ({$marks})"
        );
        $stmt->execute(array_merge([trim($purok) !== '' ? trim($purok) : null], $ids));

        return $stmt->rowCount();
    }

    /**
     * Register a resident on their behalf (done in person at the barangay
     * hall, so the account is verified straight away).
     *
     * @param array{full_name:string, purok:string, phone?:string, address?:string, email?:string} $data
     * @return array{id:int, email:string, password:?string} password is the temporary one when an email was given.
     */
    public static function createResident(array $data): array
    {
        $email    = strtolower(trim((string) ($data['email'] ?? '')));
        $password = null;

        if ($email === '') {
            $email = 'resident-' . bin2hex(random_bytes(6)) . '@' . self::NO_EMAIL_DOMAIN;
            $hash  = password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT);   // nobody knows it
        } else {
            $password = self::temporaryPassword();
            $hash     = password_hash($password, PASSWORD_BCRYPT);
        }

        $stmt = db()->prepare(
            "INSERT INTO users (full_name, email, password_hash, phone, address, zone, role, status, email_verified, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 'resident', 'verified', 1, NOW(), NOW())"
        );
        $stmt->execute([
            trim($data['full_name']),
            $email,
            $hash,
            trim((string) ($data['phone'] ?? '')) ?: null,
            trim((string) ($data['address'] ?? '')) ?: null,
            trim($data['purok']),
        ]);

        return ['id' => (int) db()->lastInsertId(), 'email' => $email, 'password' => $password];
    }

    /** True for the placeholder address of a resident added without an email. */
    public static function isPlaceholderEmail(string $email): bool
    {
        return str_ends_with(strtolower($email), '@' . self::NO_EMAIL_DOMAIN);
    }

    /** Easy to read aloud and type: no 0/O or 1/l look-alikes. */
    private static function temporaryPassword(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $out   = '';
        for ($i = 0; $i < 10; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $out . '!' . random_int(10, 99);
    }

    /** @return array{0:list<string>, 1:list<mixed>} */
    private static function purokWhere(string $purok, bool $inside): array
    {
        $purok = trim($purok);
        $where = ["role = 'resident'", 'deleted_at IS NULL'];

        if ($purok === '') {
            $where[] = $inside ? "(zone IS NULL OR TRIM(zone) = '')" : "(zone IS NOT NULL AND TRIM(zone) <> '')";
            return [$where, []];
        }

        $where[] = $inside ? 'TRIM(zone) = ?' : "(zone IS NULL OR TRIM(zone) <> ?)";

        return [$where, [$purok]];
    }

    /** Case-insensitive name / email / phone search that works on MySQL and PostgreSQL. */
    private static function addSearch(array &$where, array &$params, string $search): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }
        $where[] = '(LOWER(full_name) LIKE ? OR LOWER(email) LIKE ? OR phone LIKE ?)';
        $like    = '%' . mb_strtolower($search) . '%';
        array_push($params, $like, $like, '%' . $search . '%');
    }
}
