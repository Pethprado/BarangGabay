<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class User
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO users
             (full_name, email, password_hash, phone, address, zone, role, status,
              id_photo_url, id_verified_by_ai, id_ai_status,
              avatar_url, email_verified, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())'
        );
        $stmt->execute([
            $data['full_name'],
            $data['email'],
            $data['password_hash'],
            $data['phone']             ?? null,
            $data['address']           ?? null,
            // No default purok: see barangay_subdivisions() in helpers.php.
            // Recording a resident in a made-up subdivision is worse than
            // recording none.
            $data['zone']              ?? null,
            $data['role']              ?? 'resident',
            $data['status']            ?? 'pending',
            $data['id_photo_url']      ?? null,
            $data['id_verified_by_ai'] ?? null,
            $data['id_ai_status']      ?? null,
            $data['avatar_url']        ?? null,
        ]);
        return (int) db()->lastInsertId();
    }

    // ── Self-service account updates (/admin/account) ────────────────
    //
    // Every method below writes a FIXED column list. A user editing their own
    // account can never reach role or status through them, no matter what the
    // POST body contains — that is the whole point of routing self-service
    // writes through here rather than building an UPDATE from request keys.

    /**
     * Update the profile fields a user may change about themselves.
     *
     * @param array{full_name:string, phone:?string, designation:?string} $data
     */
    public static function updateOwnProfile(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE users SET full_name = ?, phone = ?, designation = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([
            $data['full_name'],
            $data['phone']       ?? null,
            $data['designation'] ?? null,
            $id,
        ]);
    }

    /**
     * Stamp "this user just opened their dashboard".
     *
     * Best-effort: a failed write here must never break the page it is called
     * from. See ResidentController::home() for how the previous value is held
     * steady for the length of a visit.
     */
    public static function touchLastSeen(int $id): void
    {
        try {
            db()->prepare('UPDATE users SET last_seen_at = NOW() WHERE id = ?')->execute([$id]);
        } catch (\Throwable $e) {
            error_log('[User::touchLastSeen] ' . $e->getMessage());
        }
    }

    /**
     * Stamp a successful sign-in.
     *
     * The users.last_login_at column has existed since the original schema but
     * nothing ever wrote to it, so it read NULL for every account no matter
     * how often they signed in. The staff list uses it to flag an account that
     * has never been used — the usual sign that the password set at creation
     * never reached the person — which only works if this is kept current.
     *
     * Best-effort, like touchLastSeen(): a failed write here must never turn a
     * successful sign-in into an error.
     */
    public static function touchLastLogin(int $id): void
    {
        try {
            db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$id]);
        } catch (\Throwable $e) {
            error_log('[User::touchLastLogin] ' . $e->getMessage());
        }
    }

    /** Point a user's avatar at a newly uploaded file. */
    public static function updateOwnAvatar(int $id, string $avatarUrl): void
    {
        db()->prepare('UPDATE users SET avatar_url = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$avatarUrl, $id]);
    }

    /**
     * Change a user's email. Always resets email_verified — the new address is
     * unproven until its verification link is clicked.
     */
    public static function updateOwnEmail(int $id, string $email): void
    {
        db()->prepare('UPDATE users SET email = ?, email_verified = 0, updated_at = NOW() WHERE id = ?')
            ->execute([$email, $id]);
    }

    /** Store a new password hash. The caller hashes; this never sees plaintext. */
    public static function updateOwnPassword(int $id, string $passwordHash): void
    {
        db()->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$passwordHash, $id]);
    }

    /**
     * Set another user's password hash, for an admin-issued reset.
     *
     * Deliberately separate from updateOwnPassword() even though the SQL is
     * identical: that method belongs to the self-service contract in
     * AccountController, where the caller has already proven the current
     * password. This one is an administrative override with a different
     * authorisation story, and keeping the two apart means neither can be
     * reused by accident in the other's place. The caller hashes; this never
     * sees plaintext, and it never touches role or status.
     */
    public static function setPasswordHashByAdmin(int $id, string $passwordHash): void
    {
        db()->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$passwordHash, $id]);
    }

    /**
     * Persist UI language and back-office notification preferences.
     *
     * @param array{locale:?string, notify_feedback:bool, notify_registrations:bool, notify_content:bool} $data
     */
    public static function updateOwnPreferences(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE users
                SET locale = ?, notify_feedback = ?, notify_registrations = ?, notify_content = ?,
                    updated_at = NOW()
              WHERE id = ?'
        );
        $stmt->execute([
            $data['locale'] ?: null,
            !empty($data['notify_feedback'])      ? 1 : 0,
            !empty($data['notify_registrations']) ? 1 : 0,
            !empty($data['notify_content'])       ? 1 : 0,
            $id,
        ]);
    }

    /**
     * How many verified residents share a zone, excluding the person asking.
     * Powers the "neighbours in your zone" figure on the resident dashboard.
     */
    public static function countVerifiedInZone(string $zone, int $exceptUserId = 0): int
    {
        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM users
              WHERE role = 'resident' AND status = 'verified' AND zone = ? AND id <> ?"
        );
        $stmt->execute([$zone, $exceptUserId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Is this email already on another account? Used before an email change so
     * the unique index is never the thing that reports the clash to the user.
     */
    public static function emailTakenByAnother(string $email, int $exceptUserId): bool
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
        $stmt->execute([$email, $exceptUserId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Back-office users (staff/admin/superadmin) who still want a given class
     * of alert. $preference must be one of the notify_* columns — it is
     * whitelisted here because it is interpolated into the SQL.
     *
     * @return list<int> user ids
     */
    public static function backOfficeWanting(string $preference, int $exceptUserId = 0): array
    {
        $allowed = ['notify_feedback', 'notify_registrations', 'notify_content'];
        if (!in_array($preference, $allowed, true)) {
            return [];
        }

        $stmt = db()->prepare(
            "SELECT id FROM users
              WHERE role IN ('staff','admin','superadmin')
                AND status = 'verified'
                AND {$preference} = 1
                AND id <> ?"
        );
        $stmt->execute([$exceptUserId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Every back-office account, for the staff management screen.
     *
     * Ordered so the most privileged appear first, then by name, which is how
     * someone scanning the list actually looks for a person. Includes the
     * last successful sign-in so an account that has never been used — the
     * usual sign of a password that was never delivered — is obvious.
     *
     * @return list<array<string,mixed>>
     */
    public static function backOfficeAccounts(): array
    {
        return db()->query(
            "SELECT id, full_name, email, role, status, email_verified, designation,
                    last_login_at, created_at
               FROM users
              WHERE role IN ('staff','admin','superadmin')
              ORDER BY FIELD(role,'superadmin','admin','staff'), full_name"
        )->fetchAll();
    }

    /**
     * Residents who can actually receive an SMS, for the recipient picker.
     *
     * Staff used to have to know a number by heart and type it into a blank
     * box, which is both slow and the easiest possible way to text the wrong
     * person. This lets them search by name instead.
     *
     * Deliberately mirrors SmsController::getVerifiedPhones(): verified
     * residents with a non-empty phone. If someone appears in this list they
     * are also in the broadcast, so the two cannot disagree about who is
     * reachable.
     *
     * An empty query returns the first page rather than nothing — staff asked
     * to be able to browse who is registered, not only to search blind.
     *
     * @return list<array{id:int, full_name:string, phone:string, zone:?string}>
     */
    public static function reachableBySms(string $query = '', int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $query = trim($query);

        $sql = "SELECT id, full_name, phone, zone
                  FROM users
                 WHERE role = 'resident'
                   AND status = 'verified'
                   AND phone IS NOT NULL
                   AND TRIM(phone) <> ''";

        $params = [];
        if ($query !== '') {
            // Name as typed, plus the number with punctuation ignored so
            // "0951 834" finds "09518345491".
            //
            // The digit clause is added ONLY when the query actually contains
            // digits. Stripping non-digits from a pure-text search like "Zzqq"
            // leaves an empty string, and LIKE '%%' matches every row — so a
            // search for a name nobody has was returning the whole list.
            $digits = preg_replace('/[^0-9]/', '', $query);

            if ($digits !== '') {
                $sql .= " AND (full_name LIKE ?
                           OR REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?)";
                $params[] = '%' . $query . '%';
                $params[] = '%' . $digits . '%';
            } else {
                $sql .= ' AND full_name LIKE ?';
                $params[] = '%' . $query . '%';
            }
        }

        $sql .= ' ORDER BY full_name LIMIT ' . $limit;

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** How many residents are reachable by SMS in total. */
    public static function countReachableBySms(): int
    {
        return (int) db()->query(
            "SELECT COUNT(*) FROM users
              WHERE role = 'resident' AND status = 'verified'
                AND phone IS NOT NULL AND TRIM(phone) <> ''"
        )->fetchColumn();
    }

    /**
     * How many residents an SMS would reach, per purok.
     *
     * Shown on the announcement form beside the purok selector so a staff
     * member sees what a targeted send costs before they send it — the point
     * of targeting is spending credits on the people a notice concerns, and
     * that argument is easier to make when the number is on screen.
     *
     * Residents with no purok recorded are counted under '' so they are
     * visible rather than silently missing from every targeted send.
     *
     * @return array<string,int> purok => reachable residents
     */
    public static function countReachableByPurok(): array
    {
        try {
            $rows = db()->query(
                "SELECT COALESCE(zone, '') AS purok, COUNT(*) AS n
                   FROM users
                  WHERE role = 'resident' AND status = 'verified'
                    AND phone IS NOT NULL AND TRIM(phone) <> ''
                  GROUP BY purok"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['purok']] = (int) $row['n'];
        }

        return $out;
    }

    public static function verify(int $id): void
    {
        $stmt = db()->prepare('UPDATE users SET status = ?, email_verified = 1, updated_at = NOW() WHERE id = ?');
        $stmt->execute(['verified', $id]);
    }

    public static function suspend(int $id): void
    {
        $stmt = db()->prepare('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute(['suspended', $id]);
    }

    public static function allPending(): array
    {
        $stmt = db()->query('SELECT * FROM users WHERE status = "pending" ORDER BY created_at DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function allVerified(): array
    {
        $stmt = db()->query('SELECT * FROM users WHERE status = "verified" ORDER BY full_name ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verified residents' id/name/email — used for bulk email notifications
     * (e.g. new-announcement alerts). Mirrors the role/status scope of
     * SmsController::getVerifiedPhones() so email and SMS reach the same set.
     */
    public static function allVerifiedResidentEmails(): array
    {
        $stmt = db()->query(
            "SELECT id, full_name, email FROM users
             WHERE status = 'verified' AND role = 'resident'"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * All residents with optional status and name/email search.
     */
    public static function allResidents(string $status = '', string $search = ''): array
    {
        $where  = ["role = 'resident'"];
        $params = [];

        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[] = '(full_name LIKE ? OR email LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like);
        }

        $sql  = 'SELECT * FROM users WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count residents by status. Empty string = all residents.
     */
    public static function countByStatus(string $status = ''): int
    {
        if ($status === '') {
            return (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'resident'")->fetchColumn();
        }
        $stmt = db()->prepare("SELECT COUNT(*) FROM users WHERE role = 'resident' AND status = ?");
        $stmt->execute([$status]);
        return (int) $stmt->fetchColumn();
    }
}
