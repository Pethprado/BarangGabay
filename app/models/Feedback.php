<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * A `feedbacks` row is a conversation thread (the resident's original
 * concern); the actual back-and-forth lives in `feedback_messages`. See
 * database/migrations/006_add_feedback_messages.sql for the full rationale —
 * feedbacks.message is still written once at thread creation for any legacy
 * direct-query tooling, but admin_reply/replied_by/replied_at/is_read_admin
 * are never written to anymore; feedback_messages is the source of truth.
 */
class Feedback
{
    /**
     * Ensure the feedback_messages table exists (PostgreSQL safety net).
     *
     * Migration 006 uses MySQL-only syntax (ENGINE=InnoDB, ENUM, TINYINT,
     * SET NAMES) that silently fails on PostgreSQL. This method creates the
     * table if it is missing, and backfills any existing feedbacks rows that
     * have no corresponding feedback_messages entry.
     *
     * Runs at most once per request (static flag). Uses IF NOT EXISTS so it
     * is safe to call repeatedly across deploys.
     */
    public static function ensureMessagesTable(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        $pdo = db();

        try {
            // Detect driver — only needed on PostgreSQL; MySQL migration 006 handles it.
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driver !== 'pgsql') {
                return;
            }

            // Check if table exists
            $stmt = $pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = 'public' AND table_name = 'feedback_messages'"
            );
            if ((int) $stmt->fetchColumn() > 0) {
                return; // table already exists
            }

            // Create the table
            $pdo->exec("CREATE TABLE IF NOT EXISTS feedback_messages (
                id SERIAL PRIMARY KEY,
                feedback_id INT NOT NULL,
                sender_id INT NOT NULL,
                sender_role VARCHAR(20) NOT NULL,
                message TEXT NOT NULL,
                read_by_resident SMALLINT NOT NULL DEFAULT 0,
                read_by_staff SMALLINT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT NOW(),
                FOREIGN KEY (feedback_id) REFERENCES feedbacks(id) ON DELETE CASCADE,
                FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
            )");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_fm_feedback_id ON feedback_messages(feedback_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_fm_created ON feedback_messages(created_at)");

            // Backfill: copy each feedback's original message into feedback_messages
            $pdo->exec(
                "INSERT INTO feedback_messages (feedback_id, sender_id, sender_role, message, read_by_resident, read_by_staff, created_at)
                 SELECT f.id, f.user_id, 'resident', f.message, 1, 1, f.created_at
                 FROM feedbacks f
                 WHERE NOT EXISTS (SELECT 1 FROM feedback_messages fm WHERE fm.feedback_id = f.id)"
            );

            // Backfill: copy any existing admin_reply
            $pdo->exec(
                "INSERT INTO feedback_messages (feedback_id, sender_id, sender_role, message, read_by_resident, read_by_staff, created_at)
                 SELECT f.id, f.replied_by, COALESCE(u.role, 'staff'), f.admin_reply, 1, 1, COALESCE(f.replied_at, f.created_at)
                 FROM feedbacks f
                 LEFT JOIN users u ON u.id = f.replied_by
                 WHERE f.admin_reply IS NOT NULL AND f.admin_reply <> ''
                   AND f.replied_by IS NOT NULL
                   AND NOT EXISTS (
                       SELECT 1 FROM feedback_messages fm
                       WHERE fm.feedback_id = f.id AND fm.sender_id = f.replied_by AND fm.message = f.admin_reply
                   )"
            );

            error_log('[Feedback] Created feedback_messages table and backfilled existing data on PostgreSQL');
        } catch (\Throwable $e) {
            error_log('[Feedback] ensureMessagesTable error: ' . $e->getMessage());
        }
    }

    /**
     * Open a new feedback thread with the resident's first message.
     *
     * @param array{user_id:int, message:string, sender_role?:string} $data
     */
    public static function create(array $data): int
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO feedbacks (user_id, message, created_at) VALUES (?, ?, NOW())'
            );
            $stmt->execute([$data['user_id'], $data['message']]);
            $feedbackId = (int) $pdo->lastInsertId();

            self::addMessage(
                $feedbackId,
                (int) $data['user_id'],
                $data['sender_role'] ?? 'resident',
                $data['message']
            );

            $pdo->commit();
            return $feedbackId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Append a message to an existing thread. A message is naturally "read"
     * by its own sender's side and unread by the other side.
     */
    public static function addMessage(int $feedbackId, int $senderId, string $senderRole, string $message): int
    {
        $isResident = $senderRole === 'resident';
        $stmt = db()->prepare(
            'INSERT INTO feedback_messages (feedback_id, sender_id, sender_role, message, read_by_resident, read_by_staff, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $feedbackId,
            $senderId,
            $senderRole,
            $message,
            $isResident ? 1 : 0,
            $isResident ? 0 : 1,
        ]);
        return (int) db()->lastInsertId();
    }

    /** Full ordered conversation for one thread (oldest first). */
    public static function findMessages(int $feedbackId): array
    {
        $stmt = db()->prepare(
            // sender_designation lets a resident see "Maria Santos — Barangay
            // Secretary" instead of a generic "Staff" label. NULL for
            // residents and for staff who have not set one on /admin/account.
            'SELECT fm.*, u.full_name AS sender_name, u.role AS sender_current_role,
                    u.designation AS sender_designation
             FROM feedback_messages fm
             JOIN users u ON u.id = fm.sender_id
             WHERE fm.feedback_id = ?
             ORDER BY fm.created_at ASC, fm.id ASC'
        );
        $stmt->execute([$feedbackId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Threads opened by one resident (most recently active first), each
     * enriched with message_count / last_message_at / unread_reply_count.
     */
    public static function allForUser(int $userId): array
    {
        $stmt = db()->prepare(
            "SELECT f.id, f.user_id, f.created_at,
                    COUNT(fm.id) AS message_count,
                    MAX(fm.created_at) AS last_message_at,
                    SUM(CASE WHEN fm.sender_role != 'resident' AND fm.read_by_resident = 0 THEN 1 ELSE 0 END) AS unread_reply_count
             FROM feedbacks f
             LEFT JOIN feedback_messages fm ON fm.feedback_id = f.id
             WHERE f.user_id = ?
             GROUP BY f.id, f.user_id, f.created_at
             ORDER BY COALESCE(MAX(fm.created_at), f.created_at) DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * One resident's threads for the dashboard widget, most recently active
     * first — enriched with the last message, who sent it, and how many staff
     * replies they have not read.
     *
     * Deliberately a single query with correlated subselects rather than
     * allForUser() + a findMessages() call per thread: the dashboard loads on
     * every visit and must not fan out into N+1.
     *
     * @return list<array<string,mixed>>
     */
    public static function dashboardForUser(int $userId, int $limit = 3): array
    {
        $stmt = db()->prepare(
            "SELECT f.id, f.created_at,
                    COUNT(fm.id) AS message_count,
                    MAX(fm.created_at) AS last_message_at,
                    SUM(CASE WHEN fm.sender_role != 'resident' AND fm.read_by_resident = 0 THEN 1 ELSE 0 END) AS unread_reply_count,
                    (SELECT fm2.message FROM feedback_messages fm2
                       WHERE fm2.feedback_id = f.id
                       ORDER BY fm2.created_at DESC, fm2.id DESC LIMIT 1) AS last_message,
                    (SELECT fm3.sender_role FROM feedback_messages fm3
                       WHERE fm3.feedback_id = f.id
                       ORDER BY fm3.created_at DESC, fm3.id DESC LIMIT 1) AS last_sender_role,
                    (SELECT u.full_name FROM feedback_messages fm4
                       JOIN users u ON u.id = fm4.sender_id
                       WHERE fm4.feedback_id = f.id
                       ORDER BY fm4.created_at DESC, fm4.id DESC LIMIT 1) AS last_sender_name,
                    (SELECT u2.designation FROM feedback_messages fm5
                       JOIN users u2 ON u2.id = fm5.sender_id
                       WHERE fm5.feedback_id = f.id
                       ORDER BY fm5.created_at DESC, fm5.id DESC LIMIT 1) AS last_sender_designation
             FROM feedbacks f
             LEFT JOIN feedback_messages fm ON fm.feedback_id = f.id
             WHERE f.user_id = ?
             GROUP BY f.id, f.created_at
             ORDER BY COALESCE(MAX(fm.created_at), f.created_at) DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $userId,       PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** How many of a resident's threads carry an unread staff reply. */
    public static function unreadThreadCountForUser(int $userId): int
    {
        $stmt = db()->prepare(
            "SELECT COUNT(DISTINCT fm.feedback_id)
             FROM feedback_messages fm
             JOIN feedbacks f ON f.id = fm.feedback_id
             WHERE f.user_id = ? AND fm.sender_role != 'resident' AND fm.read_by_resident = 0"
        );
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    /** How many threads this resident has open. */
    public static function countForUser(int $userId): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM feedbacks WHERE user_id = ?');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Threads for the admin panel — threads with an unread resident message
     * first, then most recently active. Enriched with resident identity,
     * message_count, last_message (+ its sender role), and unread_count.
     */
    public static function allAdmin(string $search = ''): array
    {
        $where  = '';
        $params = [];
        if ($search !== '') {
            $where  = 'WHERE u.full_name LIKE ?
                        OR EXISTS (SELECT 1 FROM feedback_messages fm2 WHERE fm2.feedback_id = f.id AND fm2.message LIKE ?)';
            $like   = '%' . $search . '%';
            $params = [$like, $like];
        }

        $sql = "SELECT f.id, f.user_id, f.created_at, u.full_name AS resident_name, u.email AS resident_email,
                       COUNT(fm.id) AS message_count,
                       MAX(fm.created_at) AS last_message_at,
                       SUM(CASE WHEN fm.sender_role = 'resident' AND fm.read_by_staff = 0 THEN 1 ELSE 0 END) AS unread_count,
                       (SELECT fm3.message FROM feedback_messages fm3
                          WHERE fm3.feedback_id = f.id ORDER BY fm3.created_at DESC, fm3.id DESC LIMIT 1) AS last_message,
                       (SELECT fm4.sender_role FROM feedback_messages fm4
                          WHERE fm4.feedback_id = f.id ORDER BY fm4.created_at DESC, fm4.id DESC LIMIT 1) AS last_sender_role,
                       EXISTS (SELECT 1 FROM feedback_messages fm5
                                WHERE fm5.feedback_id = f.id AND fm5.sender_role != 'resident') AS has_staff_reply
                FROM feedbacks f
                JOIN users u ON u.id = f.user_id
                LEFT JOIN feedback_messages fm ON fm.feedback_id = f.id
                {$where}
                GROUP BY f.id, f.user_id, f.created_at, u.full_name, u.email
                ORDER BY unread_count DESC, COALESCE(MAX(fm.created_at), f.created_at) DESC";

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Find one thread's header, with the resident's identity joined in. */
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT f.id, f.user_id, f.created_at, u.full_name AS resident_name, u.email AS resident_email
             FROM feedbacks f
             JOIN users u ON u.id = f.user_id
             WHERE f.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Authorization check — does this thread belong to this resident?
     * Callers MUST check this before letting a resident reply into a thread.
     */
    public static function belongsToUser(int $feedbackId, int $userId): bool
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM feedbacks WHERE id = ? AND user_id = ?');
        $stmt->execute([$feedbackId, $userId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Count of threads that have at least one resident message unread by staff. */
    public static function unreadCountAdmin(): int
    {
        return (int) db()->query(
            "SELECT COUNT(DISTINCT feedback_id) FROM feedback_messages WHERE sender_role = 'resident' AND read_by_staff = 0"
        )->fetchColumn();
    }

    /** Mark every resident message in a thread as read by staff (call when staff opens the thread). */
    public static function markAdminRead(int $id): void
    {
        $stmt = db()->prepare(
            "UPDATE feedback_messages SET read_by_staff = 1 WHERE feedback_id = ? AND sender_role = 'resident' AND read_by_staff = 0"
        );
        $stmt->execute([$id]);
    }

    /** Mark every staff/admin message across all of a resident's threads as read (call when they view their feedback page). */
    public static function markResidentReadForUser(int $userId): void
    {
        $stmt = db()->prepare(
            "UPDATE feedback_messages
             SET read_by_resident = 1
             WHERE sender_role != 'resident'
               AND read_by_resident = 0
               AND feedback_id IN (SELECT id FROM feedbacks WHERE user_id = ?)"
        );
        $stmt->execute([$userId]);
    }
}