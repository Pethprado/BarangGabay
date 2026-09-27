<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * A resident asking the barangay to prepare a document.
 *
 * Deliberately NOT an issuing system. Nothing here generates, signs or
 * releases a barangay clearance — that is a signed instrument and stays a
 * human act at the counter. This is the queue in front of it: the resident
 * asks, staff prepare it exactly as they do now, and the resident is told when
 * to collect it instead of walking to the hall to find out.
 *
 * The value is the trip not taken. From Purok 7 a wasted visit is a real cost.
 */
class DocumentRequest
{
    /** The documents a barangay of this size actually issues. */
    public const TYPES = [
        'clearance'  => 'Barangay Clearance',
        'residency'  => 'Certificate of Residency',
        'indigency'  => 'Certificate of Indigency',
        'business'   => 'Barangay Business Clearance',
        'other'      => 'Iba pa',
    ];

    /**
     * Where a request can go next.
     *
     * Stated as a map rather than checked inline so the rule lives in one
     * place: a released document cannot go back to pending, and a rejected one
     * is final. Staff correcting a mistake re-open it by having the resident
     * file again, which leaves both records intact.
     */
    public const TRANSITIONS = [
        'pending'    => ['processing', 'ready', 'rejected'],
        'processing' => ['ready', 'rejected'],
        'ready'      => ['released', 'rejected'],
        'released'   => [],
        'rejected'   => [],
    ];

    public static function label(string $type): string
    {
        return self::TYPES[$type] ?? $type;
    }

    public static function canMove(string $from, string $to): bool
    {
        return \in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * File a request. Returns the new reference number.
     *
     * The reference carries the year and a random tail rather than the row id:
     * a resident reads it aloud at the counter, and a sequential number would
     * also tell anyone who asked how many requests the barangay has had.
     */
    public static function create(int $userId, string $type, string $purpose, string $notes = ''): string
    {
        $reference = 'BRG-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));

        db()->prepare(
            'INSERT INTO document_requests
             (reference_no, user_id, document_type, purpose, notes, status, requested_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        )->execute([
            $reference,
            $userId,
            \array_key_exists($type, self::TYPES) ? $type : 'other',
            mb_substr(trim($purpose), 0, 255),
            trim($notes) !== '' ? mb_substr(trim($notes), 0, 2000) : null,
            'pending',
        ]);

        return $reference;
    }

    /** One request, with the requester's details for the admin queue. */
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT r.*, u.full_name, u.phone, u.email, u.zone, u.address
               FROM document_requests r
               JOIN users u ON u.id = r.user_id
              WHERE r.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Everything one resident has asked for, newest first. */
    public static function forUser(int $userId): array
    {
        $stmt = db()->prepare(
            'SELECT * FROM document_requests WHERE user_id = ? ORDER BY requested_at DESC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * The admin queue.
     *
     * Ordered by status first so the work waiting on staff floats to the top —
     * a list sorted only by date buries a week-old pending request under
     * yesterday's released one.
     */
    public static function queue(string $status = ''): array
    {
        $sql = "SELECT r.*, u.full_name, u.phone, u.zone
                  FROM document_requests r
                  JOIN users u ON u.id = r.user_id";
        $params = [];

        if ($status !== '' && \array_key_exists($status, self::TRANSITIONS)) {
            $sql .= ' WHERE r.status = ?';
            $params[] = $status;
        }

        $sql .= " ORDER BY CASE r.status WHEN 'pending' THEN 1 WHEN 'processing' THEN 2 WHEN 'ready' THEN 3 WHEN 'released' THEN 4 WHEN 'rejected' THEN 5 ELSE 6 END,
                           r.requested_at ASC";

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Move a request to a new status.
     *
     * Returns false when the move is not allowed, so a stale browser tab
     * cannot walk a released document back to pending.
     */
    public static function setStatus(int $id, string $to, int $staffId, string $staffNote = ''): bool
    {
        $current = self::find($id);
        if ($current === null || !self::canMove((string) $current['status'], $to)) {
            return false;
        }

        // The timestamps are set once, when the state is first reached.
        $stamp = match ($to) {
            'ready'    => ', ready_at = NOW()',
            'released' => ', released_at = NOW()',
            default    => '',
        };

        db()->prepare(
            "UPDATE document_requests
                SET status = ?, staff_note = ?, handled_by = ?{$stamp}
              WHERE id = ?"
        )->execute([
            $to,
            trim($staffNote) !== '' ? mb_substr(trim($staffNote), 0, 2000) : null,
            $staffId,
            $id,
        ]);

        return true;
    }

    /** How many are waiting on staff — for the admin dashboard badge. */
    public static function countOpen(): int
    {
        try {
            return (int) db()->query(
                "SELECT COUNT(*) FROM document_requests WHERE status IN ('pending','processing')"
            )->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
