<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class ProfileUpdateRequest
{
    public const STATUS_PENDING    = 'pending';
    public const STATUS_APPROVED   = 'approved';
    public const STATUS_REJECTED   = 'rejected';
    public const STATUS_NEEDS_INFO = 'needs_info';

    /**
     * Create a new profile update request with optional supporting document blob.
     */
    public static function create(
        int $userId,
        array $requestedChanges,
        array $currentValues,
        string $reason,
        ?array $file = null
    ): int {
        $pdo = db();

        $docName = null;
        $docType = null;
        $docSize = null;
        $docData = null;

        if ($file && !empty($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
            $docName = mb_substr(basename((string) $file['name']), 0, 255);
            $docType = (string) ($file['type'] ?? 'application/octet-stream');
            $docSize = (int) $file['size'];
            $docData = file_get_contents($file['tmp_name']);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO profile_update_requests
             (user_id, requested_changes, current_values, reason,
              supporting_doc_name, supporting_doc_type, supporting_doc_size, supporting_doc_data,
              status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );

        // Bind parameters safely, especially binary blob for Postgres/MySQL
        $jsonChanges = json_encode($requestedChanges, JSON_UNESCAPED_UNICODE);
        $jsonCurrent = json_encode($currentValues, JSON_UNESCAPED_UNICODE);
        $status      = self::STATUS_PENDING;

        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $jsonChanges, PDO::PARAM_STR);
        $stmt->bindValue(3, $jsonCurrent, PDO::PARAM_STR);
        $stmt->bindValue(4, $reason, PDO::PARAM_STR);
        $stmt->bindValue(5, $docName, $docName !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(6, $docType, $docType !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(7, $docSize, $docSize !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        if ($docData !== null) {
            $stmt->bindValue(8, $docData, PDO::PARAM_LOB);
        } else {
            $stmt->bindValue(8, null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(9, $status, PDO::PARAM_STR);

        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT p.id, p.user_id, p.requested_changes, p.current_values, p.reason,
                    p.supporting_doc_name, p.supporting_doc_type, p.supporting_doc_size,
                    p.status, p.rejection_reason, p.admin_notes, p.reviewed_by, p.reviewed_at,
                    p.created_at, p.updated_at,
                    u.full_name AS resident_name, u.email AS resident_email, u.phone AS resident_phone,
                    rev.full_name AS reviewer_name
             FROM profile_update_requests p
             JOIN users u ON u.id = p.user_id
             LEFT JOIN users rev ON rev.id = p.reviewed_by
             WHERE p.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['requested_changes_arr'] = json_decode((string) $row['requested_changes'], true) ?: [];
        $row['current_values_arr']    = json_decode((string) ($row['current_values'] ?? '{}'), true) ?: [];
        return $row;
    }

    /**
     * Get binary data of supporting document.
     */
    public static function getDocumentData(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT supporting_doc_name, supporting_doc_type, supporting_doc_size, supporting_doc_data
             FROM profile_update_requests WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['supporting_doc_data'])) {
            return null;
        }

        $data = $row['supporting_doc_data'];
        if (is_resource($data)) {
            $data = stream_get_contents($data);
        }

        return [
            'name' => $row['supporting_doc_name'],
            'type' => $row['supporting_doc_type'],
            'size' => $row['supporting_doc_size'],
            'data' => $data,
        ];
    }

    /**
     * List requests for a specific resident.
     */
    public static function forUser(int $userId): array
    {
        $stmt = db()->prepare(
            'SELECT id, user_id, requested_changes, current_values, reason,
                    supporting_doc_name, status, rejection_reason, created_at, updated_at
             FROM profile_update_requests
             WHERE user_id = ?
             ORDER BY created_at DESC'
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['requested_changes_arr'] = json_decode((string) $r['requested_changes'], true) ?: [];
            $r['current_values_arr']    = json_decode((string) ($r['current_values'] ?? '{}'), true) ?: [];
        }
        return $rows;
    }

    /**
     * Queue for admin/staff.
     */
    public static function queue(string $status = '', string $search = ''): array
    {
        $where = [];
        $params = [];

        if ($status !== '') {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }

        if ($search !== '') {
            $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR p.reason LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = 'SELECT p.id, p.user_id, p.requested_changes, p.current_values, p.reason,
                       p.supporting_doc_name, p.status, p.rejection_reason, p.created_at, p.updated_at,
                       u.full_name AS resident_name, u.email AS resident_email, u.phone AS resident_phone
                FROM profile_update_requests p
                JOIN users u ON u.id = p.user_id';

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY CASE p.status WHEN "pending" THEN 1 WHEN "needs_info" THEN 2 ELSE 3 END, p.created_at DESC';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['requested_changes_arr'] = json_decode((string) $r['requested_changes'], true) ?: [];
            $r['current_values_arr']    = json_decode((string) ($r['current_values'] ?? '{}'), true) ?: [];
        }
        return $rows;
    }

    /**
     * Count pending profile update requests.
     */
    public static function countPending(): int
    {
        return (int) db()->query("SELECT COUNT(*) FROM profile_update_requests WHERE status = 'pending'")->fetchColumn();
    }

    /**
     * Admin approves profile update: automatically modifies resident profile in DB!
     */
    public static function approve(int $id, int $adminId, ?string $adminNotes = null): bool
    {
        $req = self::find($id);
        if (!$req || $req['status'] !== self::STATUS_PENDING && $req['status'] !== self::STATUS_NEEDS_INFO) {
            return false;
        }

        $userId  = (int) $req['user_id'];
        $changes = $req['requested_changes_arr'];

        $allowedColumns = [
            'first_name', 'middle_name', 'last_name', 'suffix',
            'date_of_birth', 'sex', 'civil_status',
            'house_no', 'street', 'purok', 'barangay', 'city', 'province',
            'household_no', 'is_household_head', 'head_relationship',
            'phone',
        ];

        $updateCols = [];
        $updateVals = [];

        foreach ($changes as $k => $v) {
            if (in_array($k, $allowedColumns, true)) {
                $updateCols[] = "$k = ?";
                $updateVals[] = $v;
            }
        }

        // Re-compose full_name if name parts changed
        $user = User::find($userId);
        if ($user) {
            $fn = $changes['first_name']  ?? $user['first_name']  ?? '';
            $mn = $changes['middle_name'] ?? $user['middle_name'] ?? '';
            $ln = $changes['last_name']   ?? $user['last_name']   ?? '';
            $sx = $changes['suffix']      ?? $user['suffix']      ?? '';

            if (isset($changes['first_name']) || isset($changes['last_name']) || isset($changes['middle_name']) || isset($changes['suffix'])) {
                $composedName = trim("$fn " . ($mn ? "$mn " : "") . "$ln" . ($sx ? " $sx" : ""));
                if ($composedName !== '') {
                    $updateCols[] = "full_name = ?";
                    $updateVals[] = $composedName;
                }
            }

            // Re-compose address if address parts changed
            $hNo = $changes['house_no'] ?? $user['house_no'] ?? '';
            $st  = $changes['street']   ?? $user['street']   ?? '';
            $pk  = $changes['purok']    ?? $user['purok']    ?? ($user['zone'] ?? '');
            $bg  = $changes['barangay'] ?? $user['barangay'] ?? 'Bayogo';
            $ct  = $changes['city']     ?? $user['city']     ?? 'Madrid';
            $pv  = $changes['province'] ?? $user['province'] ?? 'Surigao del Sur';

            if (isset($changes['house_no']) || isset($changes['street']) || isset($changes['purok']) || isset($changes['barangay']) || isset($changes['city']) || isset($changes['province'])) {
                $addrParts = array_filter([$hNo ? "House/Block $hNo" : '', $st, $pk ? "Purok $pk" : '', $bg, $ct, $pv]);
                $composedAddr = implode(', ', $addrParts);
                if ($composedAddr !== '') {
                    $updateCols[] = "address = ?";
                    $updateVals[] = $composedAddr;
                    $updateCols[] = "zone = ?";
                    $updateVals[] = $pk;
                }
            }
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            if (!empty($updateCols)) {
                $updateCols[] = "updated_at = NOW()";
                $updateVals[] = $userId;
                $updateSql = 'UPDATE users SET ' . implode(', ', $updateCols) . ' WHERE id = ?';
                $pdo->prepare($updateSql)->execute($updateVals);
            }

            // Update request status to approved
            $stmt = $pdo->prepare(
                'UPDATE profile_update_requests
                 SET status = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW()
                 WHERE id = ?'
            );
            $stmt->execute([self::STATUS_APPROVED, $adminNotes, $adminId, $id]);

            $pdo->commit();

            AuditLog::record(
                $adminId,
                'resident.profile_update.approve',
                sprintf('Approved profile update request #%d for resident #%d (%s)', $id, $userId, (string) ($req['resident_name'] ?? ''))
            );

            return true;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[ProfileUpdateRequest::approve] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Admin rejects profile update request. Requires reason.
     */
    public static function reject(int $id, int $adminId, string $rejectionReason, ?string $adminNotes = null): bool
    {
        $req = self::find($id);
        if (!$req || $req['status'] !== self::STATUS_PENDING && $req['status'] !== self::STATUS_NEEDS_INFO) {
            return false;
        }

        $userId = (int) $req['user_id'];
        $stmt = db()->prepare(
            'UPDATE profile_update_requests
             SET status = ?, rejection_reason = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([self::STATUS_REJECTED, $rejectionReason, $adminNotes, $adminId, $id]);

        AuditLog::record(
            $adminId,
            'resident.profile_update.reject',
            sprintf('Rejected profile update request #%d for resident #%d. Dahilan: %s', $id, $userId, $rejectionReason)
        );

        return true;
    }
}
