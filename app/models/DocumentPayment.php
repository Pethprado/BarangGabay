<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class DocumentPayment
{
    public const STATUS_FREE             = 'FREE';
    public const STATUS_UNPAID           = 'UNPAID';
    public const STATUS_PROOF_SUBMITTED  = 'PAYMENT_PROOF_SUBMITTED';
    public const STATUS_UNDER_REVIEW     = 'UNDER_REVIEW';
    public const STATUS_PAID_VERIFIED    = 'PAID_VERIFIED';
    public const STATUS_PAYMENT_REJECTED = 'PAYMENT_REJECTED';
    public const STATUS_PAY_AT_PICKUP    = 'PAY_AT_PICKUP';
    public const STATUS_PAID_AT_PICKUP   = 'PAID_AT_PICKUP';
    public const STATUS_WAIVED           = 'WAIVED';
    public const STATUS_REFUNDED         = 'REFUNDED';

    public const STATUS_LABELS = [
        self::STATUS_FREE             => 'Libre',
        self::STATUS_UNPAID           => 'Kailangang Bayaran (Unpaid)',
        self::STATUS_PROOF_SUBMITTED  => 'Naipadala ang Patunay (Proof Submitted)',
        self::STATUS_UNDER_REVIEW     => 'Sinusuri (Under Review)',
        self::STATUS_PAID_VERIFIED    => 'Bayad na (Verified Paid)',
        self::STATUS_PAYMENT_REJECTED => 'Tinanggihan ang Resibo (Rejected)',
        self::STATUS_PAY_AT_PICKUP    => 'Magbabayad sa Counter (Pay at Pickup)',
        self::STATUS_PAID_AT_PICKUP   => 'Nabayaran sa Counter (Paid at Pickup)',
        self::STATUS_WAIVED           => 'Pinalampas / Libre (Waived)',
        self::STATUS_REFUNDED         => 'Isinauli ang Bayad (Refunded)',
    ];

    public static function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    /**
     * Generate unique payment reference: PAY-YYYY-XXXXXX
     */
    public static function generatePaymentRef(): string
    {
        return 'PAY-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    /**
     * Create payment record associated with a document request.
     */
    public static function createForRequest(
        int $requestId,
        int $userId,
        string $docType,
        float $amountDue,
        string $method,
        ?int $gcashAccountId = null
    ): int {
        $paymentRef = self::generatePaymentRef();
        $isFree = $amountDue <= 0.00;
        
        $initialStatus = match ($method) {
            'free'   => self::STATUS_FREE,
            'pickup' => self::STATUS_PAY_AT_PICKUP,
            default  => $isFree ? self::STATUS_FREE : self::STATUS_UNPAID,
        };

        $pdo = db();
        $stmt = $pdo->prepare(
            'INSERT INTO document_payments 
             (payment_ref, request_id, user_id, document_type, amount_due, payment_method, gcash_account_id, payment_status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $paymentRef,
            $requestId,
            $userId,
            $docType,
            $amountDue,
            $method,
            $gcashAccountId,
            $initialStatus,
        ]);

        $paymentId = (int) $pdo->lastInsertId();

        // Update request row with payment_id, fee_amount, payment_method, payment_status
        $pdo->prepare(
            'UPDATE document_requests 
                SET fee_amount = ?, payment_method = ?, payment_status = ?, payment_id = ?, updated_at = NOW()
              WHERE id = ?'
        )->execute([
            $amountDue,
            $method,
            $initialStatus,
            $paymentId,
            $requestId,
        ]);

        self::logAudit(
            $requestId,
            $paymentId,
            $userId,
            'payment_created',
            null,
            $initialStatus,
            sprintf('Generated payment %s for amount ₱%.2f via %s', $paymentRef, $amountDue, strtoupper($method))
        );

        return $paymentId;
    }

    /**
     * Find payment record by ID with related request, user, and GCash account details.
     */
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT p.*, r.reference_no AS request_ref, r.status AS document_status, r.delivery_method,
                    u.full_name, u.email, u.phone, u.zone,
                    g.account_name AS gcash_account_name, g.mobile_number AS gcash_mobile_number,
                    vu.full_name AS verified_by_name
               FROM document_payments p
               JOIN document_requests r ON r.id = p.request_id
               JOIN users u ON u.id = p.user_id
          LEFT JOIN gcash_accounts g ON g.id = p.gcash_account_id
          LEFT JOIN users vu ON vu.id = p.verified_by
              WHERE p.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Find payment by request ID.
     */
    public static function findByRequest(int $requestId): ?array
    {
        $stmt = db()->prepare(
            'SELECT p.*, r.reference_no AS request_ref, r.status AS document_status, r.delivery_method,
                    u.full_name, u.email, u.phone, u.zone,
                    g.account_name AS gcash_account_name, g.mobile_number AS gcash_mobile_number,
                    vu.full_name AS verified_by_name
               FROM document_payments p
               JOIN document_requests r ON r.id = p.request_id
               JOIN users u ON u.id = p.user_id
          LEFT JOIN gcash_accounts g ON g.id = p.gcash_account_id
          LEFT JOIN users vu ON vu.id = p.verified_by
              WHERE p.request_id = ? 
              ORDER BY p.id DESC LIMIT 1'
        );
        $stmt->execute([$requestId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Resident submits payment proof (receipt + GCash reference + amount).
     */
    public static function submitProof(
        int $paymentId,
        float $reportedAmount,
        string $gcashRef,
        array $fileMeta,
        ?string $note = null
    ): bool {
        $payment = self::find($paymentId);
        if (!$payment) {
            return false;
        }

        $oldStatus = (string) $payment['payment_status'];
        $newStatus = self::STATUS_PROOF_SUBMITTED;
        $isReupload = !empty($payment['receipt_file_name']);

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE document_payments 
                    SET amount_reported = ?,
                        gcash_reference_no = ?,
                        receipt_file_name = ?,
                        receipt_mime = ?,
                        receipt_size = ?,
                        receipt_file_data = ?,
                        receipt_file_hash = ?,
                        notes = ?,
                        payment_status = ?,
                        rejection_reason = NULL,
                        rejection_note = NULL,
                        updated_at = NOW()
                  WHERE id = ?'
            );
            $stmt->execute([
                $reportedAmount,
                trim($gcashRef),
                $fileMeta['name'],
                $fileMeta['mime'],
                $fileMeta['size'],
                $fileMeta['data_base64'],
                $fileMeta['hash'],
                $note ? trim($note) : null,
                $newStatus,
                $paymentId,
            ]);

            // Update request row
            $pdo->prepare(
                'UPDATE document_requests SET payment_status = ?, updated_at = NOW() WHERE id = ?'
            )->execute([$newStatus, (int) $payment['request_id']]);

            $action = $isReupload ? 'proof_resubmitted' : 'proof_submitted';
            $details = sprintf(
                'Uploaded payment proof: Ref# %s, Reported Amount ₱%.2f, File %s (%s)',
                trim($gcashRef),
                $reportedAmount,
                $fileMeta['name'],
                $fileMeta['hash'] ? substr($fileMeta['hash'], 0, 8) . '...' : 'n/a'
            );

            self::logAudit(
                (int) $payment['request_id'],
                $paymentId,
                (int) $payment['user_id'],
                $action,
                $oldStatus,
                $newStatus,
                $details
            );

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[DocumentPayment::submitProof] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Admin/Staff verifies payment.
     */
    public static function verifyPayment(int $paymentId, int $staffId, ?string $note = null): bool
    {
        $payment = self::find($paymentId);
        if (!$payment) {
            return false;
        }

        $oldStatus = (string) $payment['payment_status'];
        $newStatus = self::STATUS_PAID_VERIFIED;

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE document_payments 
                    SET payment_status = ?, verified_by = ?, verified_at = NOW(), notes = COALESCE(?, notes), updated_at = NOW()
                  WHERE id = ?'
            );
            $stmt->execute([$newStatus, $staffId, $note ? trim($note) : null, $paymentId]);

            // Update request row: mark verified and activate to pending if awaiting payment
            $pdo->prepare(
                'UPDATE document_requests 
                    SET payment_status = ?, 
                        status = CASE WHEN status = \'awaiting_payment\' THEN \'pending\' ELSE status END,
                        updated_at = NOW() 
                  WHERE id = ?'
            )->execute([$newStatus, (int) $payment['request_id']]);

            $details = sprintf('Payment verified by Staff ID #%d', $staffId);
            if ($note) {
                $details .= ' (Note: ' . trim($note) . ')';
            }

            self::logAudit(
                (int) $payment['request_id'],
                $paymentId,
                $staffId,
                'payment_verified',
                $oldStatus,
                $newStatus,
                $details
            );

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[DocumentPayment::verifyPayment] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Admin/Staff rejects payment proof.
     */
    public static function rejectPayment(int $paymentId, int $staffId, string $reason, ?string $note = null): bool
    {
        $payment = self::find($paymentId);
        if (!$payment) {
            return false;
        }

        $oldStatus = (string) $payment['payment_status'];
        $newStatus = self::STATUS_PAYMENT_REJECTED;

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE document_payments 
                    SET payment_status = ?, rejection_reason = ?, rejection_note = ?, updated_at = NOW()
                  WHERE id = ?'
            );
            $stmt->execute([$newStatus, trim($reason), $note ? trim($note) : null, $paymentId]);

            // Update request row
            $pdo->prepare(
                'UPDATE document_requests SET payment_status = ?, updated_at = NOW() WHERE id = ?'
            )->execute([$newStatus, (int) $payment['request_id']]);

            $details = sprintf('Payment rejected: Reason: %s', trim($reason));
            if ($note) {
                $details .= ' (Note: ' . trim($note) . ')';
            }

            self::logAudit(
                (int) $payment['request_id'],
                $paymentId,
                $staffId,
                'payment_rejected',
                $oldStatus,
                $newStatus,
                $details
            );

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[DocumentPayment::rejectPayment] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Staff marks payment paid at pickup (counter).
     */
    public static function markPaidAtPickup(int $paymentId, int $staffId, ?string $note = null): bool
    {
        $payment = self::find($paymentId);
        if (!$payment) {
            return false;
        }

        $oldStatus = (string) $payment['payment_status'];
        $newStatus = self::STATUS_PAID_AT_PICKUP;

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE document_payments 
                    SET payment_status = ?, amount_reported = amount_due, verified_by = ?, verified_at = NOW(), notes = COALESCE(?, notes), updated_at = NOW()
                  WHERE id = ?'
            );
            $stmt->execute([$newStatus, $staffId, $note ? trim($note) : null, $paymentId]);

            $pdo->prepare(
                'UPDATE document_requests SET payment_status = ?, updated_at = NOW() WHERE id = ?'
            )->execute([$newStatus, (int) $payment['request_id']]);

            $details = sprintf('Cash payment collected at pickup counter by Staff ID #%d', $staffId);
            if ($note) {
                $details .= ' (Note: ' . trim($note) . ')';
            }

            self::logAudit(
                (int) $payment['request_id'],
                $paymentId,
                $staffId,
                'paid_at_pickup',
                $oldStatus,
                $newStatus,
                $details
            );

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[DocumentPayment::markPaidAtPickup] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Admin waives payment for valid cases.
     */
    public static function waivePayment(int $paymentId, int $staffId, string $reason): bool
    {
        $payment = self::find($paymentId);
        if (!$payment) {
            return false;
        }

        $oldStatus = (string) $payment['payment_status'];
        $newStatus = self::STATUS_WAIVED;

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE document_payments 
                    SET payment_status = ?, waiver_reason = ?, verified_by = ?, verified_at = NOW(), updated_at = NOW()
                  WHERE id = ?'
            );
            $stmt->execute([$newStatus, trim($reason), $staffId, $paymentId]);

            $pdo->prepare(
                'UPDATE document_requests 
                    SET payment_status = ?, 
                        status = CASE WHEN status = \'awaiting_payment\' THEN \'pending\' ELSE status END,
                        updated_at = NOW() 
                  WHERE id = ?'
            )->execute([$newStatus, (int) $payment['request_id']]);

            self::logAudit(
                (int) $payment['request_id'],
                $paymentId,
                $staffId,
                'payment_waived',
                $oldStatus,
                $newStatus,
                'Payment waived: ' . trim($reason)
            );

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return false;
        }
    }

    /**
     * Admin records refund.
     */
    public static function recordRefund(int $paymentId, int $staffId, float $amount, string $reason): bool
    {
        $payment = self::find($paymentId);
        if (!$payment) {
            return false;
        }

        $oldStatus = (string) $payment['payment_status'];
        $newStatus = self::STATUS_REFUNDED;

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE document_payments 
                    SET payment_status = ?, refund_reason = ?, updated_at = NOW()
                  WHERE id = ?'
            );
            $stmt->execute([$newStatus, trim($reason) . sprintf(' (Refunded ₱%.2f by Staff ID #%d)', $amount, $staffId), $paymentId]);

            $pdo->prepare(
                'UPDATE document_requests SET payment_status = ?, updated_at = NOW() WHERE id = ?'
            )->execute([$newStatus, (int) $payment['request_id']]);

            self::logAudit(
                (int) $payment['request_id'],
                $paymentId,
                $staffId,
                'payment_refunded',
                $oldStatus,
                $newStatus,
                sprintf('Recorded refund of ₱%.2f. Reason: %s', $amount, trim($reason))
            );

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return false;
        }
    }

    /**
     * Duplicate GCash reference detection.
     */
    public static function checkDuplicateGcashRef(string $ref, ?int $excludePaymentId = null): bool
    {
        $ref = trim($ref);
        if ($ref === '') {
            return false;
        }

        $sql = 'SELECT COUNT(*) FROM document_payments WHERE gcash_reference_no = ?';
        $params = [$ref];
        if ($excludePaymentId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludePaymentId;
        }

        try {
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            return ((int) $stmt->fetchColumn()) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Duplicate Receipt file hash detection.
     */
    public static function checkDuplicateHash(string $hash, ?int $excludePaymentId = null): bool
    {
        $hash = trim($hash);
        if ($hash === '') {
            return false;
        }

        $sql = 'SELECT COUNT(*) FROM document_payments WHERE receipt_file_hash = ?';
        $params = [$hash];
        if ($excludePaymentId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludePaymentId;
        }

        try {
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            return ((int) $stmt->fetchColumn()) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Log audit action.
     */
    public static function logAudit(
        int $requestId,
        ?int $paymentId,
        ?int $userId,
        string $action,
        ?string $oldStatus = null,
        ?string $newStatus = null,
        ?string $details = null
    ): void {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            db()->prepare(
                'INSERT INTO payment_audit_logs 
                 (payment_id, request_id, user_id, action, old_status, new_status, details, ip_address, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                $paymentId,
                $requestId,
                $userId,
                $action,
                $oldStatus,
                $newStatus,
                $details,
                $ip,
            ]);
        } catch (\Throwable $e) {
            error_log('[DocumentPayment::logAudit] ' . $e->getMessage());
        }
    }

    /**
     * Get audit trail for a payment.
     */
    public static function getAuditLogs(int $paymentId): array
    {
        try {
            $stmt = db()->prepare(
                'SELECT l.*, u.full_name AS actor_name, u.role AS actor_role
                   FROM payment_audit_logs l
              LEFT JOIN users u ON u.id = l.user_id
                  WHERE l.payment_id = ?
                  ORDER BY l.created_at ASC, l.id ASC'
            );
            $stmt->execute([$paymentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Dashboard stats for Admin Payment Management.
     */
    public static function getDashboardStats(): array
    {
        $stats = [
            'today_collected'     => 0.00,
            'pending_count'       => 0,
            'verified_today'      => 0,
            'pay_at_pickup_count' => 0,
            'rejected_count'      => 0,
            'total_collected'     => 0.00,
        ];

        try {
            $pdo = db();
            // Total collected (verified paid + paid at pickup)
            $totalStmt = $pdo->query(
                "SELECT COALESCE(SUM(amount_due), 0) FROM document_payments WHERE payment_status IN ('PAID_VERIFIED', 'PAID_AT_PICKUP')"
            );
            $stats['total_collected'] = (float) $totalStmt->fetchColumn();

            // Today collected
            $todayStmt = $pdo->query(
                "SELECT COALESCE(SUM(amount_due), 0) FROM document_payments 
                  WHERE payment_status IN ('PAID_VERIFIED', 'PAID_AT_PICKUP') 
                    AND (verified_at >= CURRENT_DATE OR updated_at >= CURRENT_DATE)"
            );
            $stats['today_collected'] = (float) $todayStmt->fetchColumn();

            // Pending verification count
            $pendingStmt = $pdo->query(
                "SELECT COUNT(*) FROM document_payments WHERE payment_status = 'PAYMENT_PROOF_SUBMITTED'"
            );
            $stats['pending_count'] = (int) $pendingStmt->fetchColumn();

            // Verified today count
            $verTodayStmt = $pdo->query(
                "SELECT COUNT(*) FROM document_payments 
                  WHERE payment_status = 'PAID_VERIFIED' 
                    AND (verified_at >= CURRENT_DATE OR updated_at >= CURRENT_DATE)"
            );
            $stats['verified_today'] = (int) $verTodayStmt->fetchColumn();

            // Pay at pickup count
            $pickupStmt = $pdo->query(
                "SELECT COUNT(*) FROM document_payments WHERE payment_status = 'PAY_AT_PICKUP'"
            );
            $stats['pay_at_pickup_count'] = (int) $pickupStmt->fetchColumn();

            // Rejected count
            $rejStmt = $pdo->query(
                "SELECT COUNT(*) FROM document_payments WHERE payment_status = 'PAYMENT_REJECTED'"
            );
            $stats['rejected_count'] = (int) $rejStmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[DocumentPayment::getDashboardStats] ' . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Get transaction list with filtering and search.
     */
    public static function getTransactions(array $filters = []): array
    {
        $sql = "SELECT p.*, r.reference_no AS request_ref, r.status AS document_status, r.delivery_method,
                       u.full_name, u.phone, u.zone,
                       g.account_name AS gcash_account_name,
                       vu.full_name AS verified_by_name
                  FROM document_payments p
                  JOIN document_requests r ON r.id = p.request_id
                  JOIN users u ON u.id = p.user_id
             LEFT JOIN gcash_accounts g ON g.id = p.gcash_account_id
             LEFT JOIN users vu ON vu.id = p.verified_by
                 WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= ' AND p.payment_status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['method'])) {
            $sql .= ' AND p.payment_method = ?';
            $params[] = $filters['method'];
        }

        if (!empty($filters['doc_type'])) {
            $sql .= ' AND p.document_type = ?';
            $params[] = $filters['doc_type'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $sql .= ' AND (p.payment_ref ILIKE ? OR r.reference_no ILIKE ? OR u.full_name ILIKE ? OR p.gcash_reference_no ILIKE ?)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= " ORDER BY CASE p.payment_status 
                     WHEN 'PAYMENT_PROOF_SUBMITTED' THEN 1 
                     WHEN 'UNDER_REVIEW' THEN 2 
                     WHEN 'PAY_AT_PICKUP' THEN 3 
                     WHEN 'UNPAID' THEN 4 
                     WHEN 'PAID_VERIFIED' THEN 5 
                     WHEN 'PAID_AT_PICKUP' THEN 6 
                     ELSE 7 END, 
                  p.created_at DESC";

        try {
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[DocumentPayment::getTransactions] ' . $e->getMessage());
            return [];
        }
    }
}
