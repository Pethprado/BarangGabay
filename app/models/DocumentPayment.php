<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class DocumentPayment
{
    public const STATUS_PENDING          = 'PENDING';
    public const STATUS_PROCESSING       = 'PROCESSING';
    public const STATUS_PAID             = 'PAID';
    public const STATUS_FAILED           = 'FAILED';
    public const STATUS_CANCELLED        = 'CANCELLED';
    public const STATUS_EXPIRED          = 'EXPIRED';
    public const STATUS_REFUNDED         = 'REFUNDED';
    public const STATUS_NOT_REQUIRED     = 'NOT_REQUIRED';

    // Legacy / specific aliases for backward compatibility
    public const STATUS_FREE             = 'FREE';
    public const STATUS_UNPAID           = 'UNPAID';
    public const STATUS_PAYMENT_PENDING  = 'PAYMENT_PENDING';
    public const STATUS_APPROVED         = 'APPROVED';
    public const STATUS_PROOF_SUBMITTED  = 'PAYMENT_PROOF_SUBMITTED';
    public const STATUS_UNDER_REVIEW     = 'UNDER_REVIEW';
    public const STATUS_PAID_VERIFIED    = 'PAID_VERIFIED';
    public const STATUS_PAYMENT_REJECTED = 'PAYMENT_REJECTED';
    public const STATUS_PAY_AT_PICKUP    = 'PAY_AT_PICKUP';
    public const STATUS_PAID_AT_PICKUP   = 'PAID_AT_PICKUP';
    public const STATUS_WAIVED           = 'WAIVED';

    /** Paid one way or another — the request may proceed. */
    public const PAID_STATES = ['PAID', 'PAID_VERIFIED', 'PAID_AT_PICKUP'];

    /** No further payment can happen: paid, or settled without money. */
    public const SETTLED_STATES = ['PAID', 'PAID_VERIFIED', 'PAID_AT_PICKUP', 'WAIVED', 'REFUNDED', 'NOT_REQUIRED', 'FREE'];

    /** Admin filter tabs → the statuses each one covers. */
    public const FILTER_GROUPS = [
        'awaiting'      => ['PENDING', 'UNPAID', 'PAYMENT_PENDING', 'PROCESSING'],
        'manual_review' => ['PAYMENT_PROOF_SUBMITTED', 'UNDER_REVIEW'],
        'failed'        => ['FAILED', 'PAYMENT_REJECTED'],
        'expired'       => ['EXPIRED', 'CANCELLED'],
    ];

    public const STATUS_LABELS = [
        self::STATUS_PAID             => 'Bayad na (Paid ✓)',
        self::STATUS_PAID_VERIFIED    => 'Bayad na (Paid ✓)',
        self::STATUS_PENDING          => 'Naghihintay ng Bayad (Awaiting Payment)',
        self::STATUS_PROCESSING       => 'Ipinoproseso (Processing)',
        self::STATUS_NOT_REQUIRED     => 'Libre (Not Required)',
        self::STATUS_FREE             => 'Libre (Free)',
        self::STATUS_UNPAID           => 'Kailangang Bayaran (Unpaid)',
        self::STATUS_PAYMENT_PENDING  => 'Naghihintay ng Bayad (Pending)',
        self::STATUS_APPROVED         => 'Inaprubahan (Approved)',
        self::STATUS_PROOF_SUBMITTED  => 'Manual Review — may patunay',
        self::STATUS_UNDER_REVIEW     => 'Manual Review',
        self::STATUS_PAYMENT_REJECTED => 'Tinanggihan (Rejected)',
        self::STATUS_FAILED           => 'Bigo ang Pagbabayad (Failed)',
        self::STATUS_CANCELLED        => 'Kinansela (Cancelled)',
        self::STATUS_EXPIRED          => 'Nag-expire (Expired)',
        self::STATUS_PAY_AT_PICKUP    => 'Magbabayad sa Counter (Pay at Pickup)',
        self::STATUS_PAID_AT_PICKUP   => 'Nabayaran sa Counter (Paid at Pickup)',
        self::STATUS_WAIVED           => 'Libre / Pinalampas (Waived)',
        self::STATUS_REFUNDED         => 'Isinauli ang Bayad (Refunded)',
    ];

    public static function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? ucfirst(strtolower(str_replace('_', ' ', $status)));
    }

    /**
     * Unique, unguessable payment reference: BG-YYYY-XXXXXXXX (8 random hex)
     */
    public static function generatePaymentRef(): string
    {
        return 'BG-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
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
        
        $provider = match ($method) {
            'paymongo', 'gcash' => 'paymongo',
            default             => 'manual',
        };
        $initialStatus = match ($method) {
            'free'              => self::STATUS_NOT_REQUIRED,
            'pickup'            => self::STATUS_PAY_AT_PICKUP,
            'paymongo', 'gcash' => $isFree ? self::STATUS_NOT_REQUIRED : self::STATUS_PENDING,
            default             => $isFree ? self::STATUS_NOT_REQUIRED : self::STATUS_PENDING,
        };

        $pdo = db();
        $stmt = $pdo->prepare(
            'INSERT INTO document_payments 
             (payment_ref, request_id, user_id, document_type, amount_due, payment_method, provider, gcash_account_id, payment_status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $paymentRef,
            $requestId,
            $userId,
            $docType,
            $amountDue,
            $method,
            $provider,
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
     * Find payment record by payment_ref.
     */
    public static function findByPaymentRef(string $paymentRef): ?array
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
              WHERE p.payment_ref = ? LIMIT 1'
        );
        $stmt->execute([trim($paymentRef)]);
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
                    SET payment_status = ?, verification_type = \'manual\', verified_by = ?, verified_at = NOW(),
                        paid_at = COALESCE(paid_at, NOW()), notes = COALESCE(?, notes), updated_at = NOW()
                  WHERE id = ? AND payment_status IN (\'PAYMENT_PROOF_SUBMITTED\', \'UNDER_REVIEW\')'
            );
            $stmt->execute([$newStatus, $staffId, $note ? trim($note) : null, $paymentId]);

            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                return false; // not in a state this action applies to
            }

            // Update request row: mark verified and activate to pending if awaiting payment
            $pdo->prepare(
                'UPDATE document_requests 
                    SET payment_status = ?, 
                        status = CASE WHEN status = \'awaiting_payment\' THEN \'pending_review\' ELSE status END,
                        updated_at = NOW()
                  WHERE id = ?'
            )->execute([$newStatus, (int) $payment['request_id']]);

            $details = sprintf('Payment manually verified by Staff ID #%d', $staffId);
            if ($note) {
                $details .= ' (Note: ' . trim($note) . ')';
            }

            self::logAudit(
                (int) $payment['request_id'],
                $paymentId,
                $staffId,
                'PAYMENT_MANUALLY_VERIFIED',
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
                  WHERE id = ? AND payment_status IN (\'PAYMENT_PROOF_SUBMITTED\', \'UNDER_REVIEW\')'
            );
            $stmt->execute([$newStatus, trim($reason), $note ? trim($note) : null, $paymentId]);

            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                return false; // not in a state this action applies to
            }

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
                'PAYMENT_REJECTED',
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
                    SET payment_status = ?, verification_type = \'counter\', amount_reported = amount_due, verified_by = ?,
                        verified_at = NOW(), paid_at = NOW(), notes = COALESCE(?, notes), updated_at = NOW()
                  WHERE id = ? AND payment_status NOT IN (' . self::inList(self::SETTLED_STATES) . ')'
            );
            $stmt->execute([$newStatus, $staffId, $note ? trim($note) : null, $paymentId]);

            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                return false; // not in a state this action applies to
            }

            $pdo->prepare(
                'UPDATE document_requests
                    SET payment_status = ?,
                        status = CASE WHEN status = \'awaiting_payment\' THEN \'pending_review\' ELSE status END,
                        updated_at = NOW()
                  WHERE id = ?'
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
                    SET payment_status = ?, verification_type = \'waiver\', waiver_reason = ?, verified_by = ?, verified_at = NOW(), updated_at = NOW()
                  WHERE id = ? AND payment_status NOT IN (' . self::inList(self::SETTLED_STATES) . ')'
            );
            $stmt->execute([$newStatus, trim($reason), $staffId, $paymentId]);

            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                return false; // not in a state this action applies to
            }

            $pdo->prepare(
                'UPDATE document_requests 
                    SET payment_status = ?, 
                        status = CASE WHEN status = \'awaiting_payment\' THEN \'pending_review\' ELSE status END,
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
     * Dashboard stats for Admin Payment Management — real rows only.
     */
    public static function getDashboardStats(): array
    {
        $stats = [
            'today_collected'   => 0.00,
            'total_collected'   => 0.00,
            'awaiting_count'    => 0,
            'review_count'      => 0,
            'paid_today_count'  => 0,
            'failed_count'      => 0,
            // Kept for older views that still read them.
            'pending_count'       => 0,
            'verified_today'      => 0,
            'pay_at_pickup_count' => 0,
            'rejected_count'      => 0,
        ];

        $paid     = self::inList(self::PAID_STATES);
        $awaiting = self::inList(self::FILTER_GROUPS['awaiting']);
        $review   = self::inList(self::FILTER_GROUPS['manual_review']);
        $failed   = self::inList(self::FILTER_GROUPS['failed']);

        try {
            $pdo = db();
            $stats['total_collected']  = (float) $pdo->query("SELECT COALESCE(SUM(amount_due), 0) FROM document_payments WHERE payment_status IN ($paid)")->fetchColumn();
            $stats['today_collected']  = (float) $pdo->query("SELECT COALESCE(SUM(amount_due), 0) FROM document_payments WHERE payment_status IN ($paid) AND COALESCE(paid_at, verified_at, updated_at) >= CURRENT_DATE")->fetchColumn();
            $stats['paid_today_count'] = (int) $pdo->query("SELECT COUNT(*) FROM document_payments WHERE payment_status IN ($paid) AND COALESCE(paid_at, verified_at, updated_at) >= CURRENT_DATE")->fetchColumn();
            $stats['awaiting_count']   = (int) $pdo->query("SELECT COUNT(*) FROM document_payments WHERE payment_status IN ($awaiting)")->fetchColumn();
            $stats['review_count']     = (int) $pdo->query("SELECT COUNT(*) FROM document_payments WHERE payment_status IN ($review)")->fetchColumn();
            $stats['failed_count']     = (int) $pdo->query("SELECT COUNT(*) FROM document_payments WHERE payment_status IN ($failed)")->fetchColumn();
            $stats['pay_at_pickup_count'] = (int) $pdo->query("SELECT COUNT(*) FROM document_payments WHERE payment_status = 'PAY_AT_PICKUP'")->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[DocumentPayment::getDashboardStats] ' . $e->getMessage());
        }

        $stats['pending_count']  = $stats['review_count'];
        $stats['verified_today'] = $stats['paid_today_count'];
        $stats['rejected_count'] = $stats['failed_count'];

        return $stats;
    }

    /** Quoted IN-list of status constants (constants only, never user input). */
    private static function inList(array $statuses): string
    {
        return implode(', ', array_map(static fn (string $s): string => "'" . str_replace("'", '', $s) . "'", $statuses));
    }

    /**
     * Transactions with filtering and search.
     *
     * Filters: group (awaiting|paid|failed|expired|manual_review), status, method,
     * doc_type, search (payment ref, request ref, resident, document, PayMongo ids).
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

        $group = (string) ($filters['group'] ?? '');
        if ($group === 'paid') {
            $sql .= ' AND p.payment_status IN (' . self::inList(self::PAID_STATES) . ')';
        } elseif (isset(self::FILTER_GROUPS[$group])) {
            $sql .= ' AND p.payment_status IN (' . self::inList(self::FILTER_GROUPS[$group]) . ')';
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND p.payment_status = ?';
            $params[] = (string) $filters['status'];
        }
        if (!empty($filters['method'])) {
            $sql .= ' AND p.payment_method = ?';
            $params[] = (string) $filters['method'];
        }
        if (!empty($filters['doc_type'])) {
            $sql .= ' AND p.document_type = ?';
            $params[] = (string) $filters['doc_type'];
        }
        if (!empty($filters['search'])) {
            // LOWER() LIKE works on both MariaDB (local) and PostgreSQL (Render).
            $needle  = '%' . mb_strtolower(trim((string) $filters['search'])) . '%';
            $columns = ['p.payment_ref', 'r.reference_no', 'u.full_name', 'p.document_type', 'p.gcash_reference_no',
                        'p.paymongo_checkout_id', 'p.paymongo_payment_id', 'p.paymongo_payment_intent_id'];
            $sql .= ' AND (' . implode(' OR ', array_map(static fn (string $c): string => "LOWER(COALESCE($c, '')) LIKE ?", $columns)) . ')';
            foreach ($columns as $_) {
                $params[] = $needle;
            }
        }

        $sql .= " ORDER BY CASE
                     WHEN p.payment_status IN (" . self::inList(self::FILTER_GROUPS['manual_review']) . ") THEN 1
                     WHEN p.payment_status IN (" . self::inList(self::FILTER_GROUPS['awaiting']) . ") THEN 2
                     ELSE 3 END,
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

    // ── Webhook idempotency ──────────────────────────────────────────────

    public static function isWebhookProcessed(string $eventId): bool
    {
        try {
            $stmt = db()->prepare('SELECT 1 FROM payment_webhook_events WHERE event_id = ? LIMIT 1');
            $stmt->execute([$eventId]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** The payload is not stored — it adds nothing the payment row lacks. */
    public static function recordWebhookEvent(string $eventId, string $eventType, ?string $resourceId, string $provider = 'paymongo'): bool
    {
        try {
            return db()->prepare(
                'INSERT INTO payment_webhook_events (event_id, event_type, provider, resource_id, payload, created_at)
                 VALUES (?, ?, ?, ?, NULL, NOW())'
            )->execute([$eventId, $eventType, $provider, $resourceId]);
        } catch (\Throwable $e) {
            return false; // a duplicate event id is exactly the idempotent case
        }
    }

    // ── PayMongo ─────────────────────────────────────────────────────────

    /** Remember the hosted checkout so a refresh or double click reuses it. */
    public static function setPaymongoCheckout(int $paymentId, string $checkoutId, string $checkoutUrl): bool
    {
        try {
            return db()->prepare(
                'UPDATE document_payments
                    SET paymongo_checkout_id = ?, checkout_url = ?, checkout_created_at = NOW(),
                        provider = \'paymongo\', payment_method = \'gcash\', payment_status = ?,
                        failure_reason = NULL, updated_at = NOW()
                  WHERE id = ? AND payment_status NOT IN (' . self::inList(self::PAID_STATES) . ')'
            )->execute([$checkoutId, $checkoutUrl, self::STATUS_PENDING, $paymentId]);
        } catch (\Throwable $e) {
            error_log('[DocumentPayment::setPaymongoCheckout] ' . $e->getMessage());
            return false;
        }
    }

    public static function findByPaymongoCheckoutId(string $checkoutId): ?array
    {
        return self::findOneWhere('p.paymongo_checkout_id = ?', [$checkoutId]);
    }

    public static function findByPaymongoPaymentIntentId(string $intentId): ?array
    {
        return self::findOneWhere('p.paymongo_payment_intent_id = ?', [$intentId]);
    }

    private static function findOneWhere(string $where, array $params): ?array
    {
        try {
            $stmt = db()->prepare(
                "SELECT p.*, r.reference_no AS request_ref, r.status AS document_status, r.delivery_method,
                        u.full_name, u.email, u.phone, u.zone
                   FROM document_payments p
                   JOIN document_requests r ON r.id = p.request_id
                   JOIN users u ON u.id = p.user_id
                  WHERE $where LIMIT 1"
            );
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Mark a payment PAID from a PayMongo-confirmed payment. Atomic and
     * idempotent: the UPDATE only matches a row that is not already settled,
     * so a duplicate webhook, a racing return page and a staff "Check status"
     * click cannot all apply it. Returns true only for the call that changed it.
     *
     * The caller (PaymentConfirmation) has already checked status, amount and
     * currency against the official fee.
     */
    public static function markPaymongoPaid(
        int $paymentId,
        string $paymongoPaymentId,
        ?string $paymentIntentId,
        float $amountPesos,
        string $sourceType,
        ?string $eventId,
        ?string $checkoutId
    ): bool {
        $payment = self::find($paymentId);
        if (!$payment) {
            return false;
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE document_payments
                    SET payment_status = ?, verification_type = \'paymongo\', provider = \'paymongo\',
                        payment_method = \'gcash\', amount_reported = ?, currency = \'PHP\',
                        paymongo_payment_id = ?,
                        paymongo_payment_intent_id = COALESCE(?, paymongo_payment_intent_id),
                        paymongo_checkout_id = COALESCE(paymongo_checkout_id, ?),
                        paymongo_source_type = ?, paymongo_event_id = COALESCE(?, paymongo_event_id),
                        failure_reason = NULL, paid_at = NOW(), verified_at = NOW(), verified_by = NULL,
                        updated_at = NOW()
                  WHERE id = ? AND payment_status NOT IN (' . self::inList(self::SETTLED_STATES) . ')'
            );
            $stmt->execute([
                self::STATUS_PAID, $amountPesos, $paymongoPaymentId, $paymentIntentId,
                $checkoutId, $sourceType, $eventId, $paymentId,
            ]);

            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                return false; // already settled by another caller
            }

            // Paid requests leave "awaiting payment" and join the processing queue.
            $pdo->prepare(
                "UPDATE document_requests
                    SET payment_status = ?, payment_method = 'gcash',
                        status = CASE WHEN status = 'awaiting_payment' THEN 'pending_review' ELSE status END,
                        updated_at = NOW()
                  WHERE id = ?"
            )->execute([self::STATUS_PAID, (int) $payment['request_id']]);

            self::logAudit(
                (int) $payment['request_id'], $paymentId, null, 'PAYMENT_CONFIRMED',
                (string) $payment['payment_status'], self::STATUS_PAID,
                sprintf('PayMongo %s payment %s (₱%.2f PHP) confirmed%s.', strtoupper($sourceType ?: 'gcash'),
                    $paymongoPaymentId, $amountPesos, $eventId ? ' by webhook ' . $eventId : ' by API check')
            );

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            // A unique-index violation means this PayMongo payment already
            // settled a different record — never apply it twice.
            error_log('[DocumentPayment::markPaymongoPaid] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Move an unsettled payment to another non-paid state (FAILED, EXPIRED,
     * CANCELLED, UNDER_REVIEW). Atomic; returns true only if it changed.
     */
    public static function transitionUnsettled(int $paymentId, string $toStatus, string $action, ?int $actorId, string $reason): bool
    {
        if (in_array($toStatus, self::SETTLED_STATES, true)) {
            return false; // paid states only come from the confirmation path
        }
        $payment = self::find($paymentId);
        if (!$payment || (string) $payment['payment_status'] === $toStatus) {
            return false;
        }

        $stmt = db()->prepare(
            'UPDATE document_payments SET payment_status = ?, failure_reason = ?, updated_at = NOW()
              WHERE id = ? AND payment_status NOT IN (' . self::inList(self::SETTLED_STATES) . ')'
        );
        $stmt->execute([$toStatus, mb_substr($reason, 0, 255), $paymentId]);
        if ($stmt->rowCount() !== 1) {
            return false;
        }

        db()->prepare('UPDATE document_requests SET payment_status = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$toStatus, (int) $payment['request_id']]);

        self::logAudit((int) $payment['request_id'], $paymentId, $actorId, $action,
            (string) $payment['payment_status'], $toStatus, $reason);

        return true;
    }
}
