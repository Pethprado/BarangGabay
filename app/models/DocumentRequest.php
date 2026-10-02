<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * A resident asking the barangay to prepare a document.
 *
 * Supports both Personal Pickup at the barangay hall and Digital Soft Copy
 * delivery with secure in-app upload, preview, and download.
 */
class DocumentRequest
{
    /** The documents a barangay issues. */
    public const TYPES = [
        'clearance'  => 'Barangay Clearance',
        'residency'  => 'Certificate of Residency',
        'indigency'  => 'Certificate of Indigency',
        'business'   => 'Barangay Business Clearance',
        'other'      => 'Iba pa',
    ];

    /** Delivery methods supported by the system (Digital Copy & Pickup at Barangay Hall). */
    public const DELIVERY_METHODS = [
        'digital' => 'Digital Copy (Online)',
        'pickup'  => 'Pickup at Barangay Hall',
    ];

    /**
     * Where a request can go next.
     */
    public const TRANSITIONS = [
        'awaiting_payment'       => ['pending_review', 'pending', 'rejected', 'cancelled'],
        'pending_review'         => ['under_review', 'processing', 'needs_information', 'approved', 'ready', 'rejected', 'cancelled'],
        'pending'                => ['pending_review', 'under_review', 'processing', 'needs_information', 'approved', 'ready', 'rejected', 'cancelled'],
        'under_review'           => ['needs_information', 'approved', 'ready', 'rejected', 'cancelled'],
        'processing'             => ['needs_information', 'approved', 'ready', 'rejected', 'cancelled'],
        'needs_information'      => ['pending_review', 'pending', 'under_review', 'processing', 'approved', 'ready', 'rejected', 'cancelled'],
        'approved'               => ['available_for_download', 'ready_for_pickup', 'ready', 'released', 'completed', 'rejected', 'cancelled'],
        'ready'                  => ['available_for_download', 'ready_for_pickup', 'released', 'completed', 'rejected', 'cancelled'],
        'ready_for_pickup'       => ['completed', 'released', 'cancelled'],
        'available_for_download' => ['completed', 'released'],
        'released'               => ['completed'],
        'completed'              => [],
        'rejected'               => [],
        'cancelled'              => [],
    ];

    public static function label(string $type): string
    {
        return self::TYPES[$type] ?? $type;
    }

    public static function deliveryLabel(string $method): string
    {
        return self::DELIVERY_METHODS[$method] ?? ($method === 'digital' ? 'Digital Copy' : 'Pickup at Barangay Hall');
    }

    public static function statusLabel(string $status): string
    {
        $normalized = strtolower(trim($status));
        return match ($normalized) {
            'draft'                  => 'Draft',
            'awaiting_payment'       => 'Naghihintay ng Bayad (Awaiting Payment)',
            'pending_review', 'pending' => 'Nakabinbin para sa Pagsusuri (Pending Review)',
            'under_review', 'processing' => 'Sinusuri ng Barangay (Under Review)',
            'needs_information'      => 'Kailangan ng Impormasyon (Needs Info)',
            'approved'               => 'Naaprubahan (Approved)',
            'ready', 'ready_for_pickup' => 'Handa nang Kunin sa Hall (Ready for Pickup)',
            'available_for_download' => 'Mada-download na (Available for Download)',
            'released', 'completed'  => 'Nakumpleto / Nakuha na (Completed)',
            'rejected'               => 'Tinanggihan (Rejected)',
            'cancelled'              => 'Kinansela (Cancelled)',
            default                  => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public static function canMove(string $from, string $to): bool
    {
        $f = strtolower(trim($from));
        $t = strtolower(trim($to));

        if ($f === $t) return true;
        // Aliases for seamless backward compatibility
        if ($f === 'processing' && $t === 'under_review') return true;
        if ($f === 'under_review' && $t === 'processing') return true;
        if ($f === 'pending' && $t === 'pending_review') return true;
        if ($f === 'pending_review' && $t === 'pending') return true;
        if ($t === 'completed' && in_array($f, ['ready', 'ready_for_pickup', 'available_for_download', 'released', 'approved'], true)) return true;
        if ($t === 'released' && in_array($f, ['ready', 'ready_for_pickup', 'available_for_download', 'completed', 'approved'], true)) return true;

        return \in_array($t, self::TRANSITIONS[$f] ?? [], true);
    }

    /**
     * Check if payment is verified (or document is free / waived).
     */
    public static function isPaymentVerified(array|int $request): bool
    {
        if (is_int($request)) {
            $request = self::find($request);
        }
        if (!$request) {
            return false;
        }

        $fee = (float) ($request['fee_amount'] ?? 0);
        if ($fee <= 0.0) {
            return true;
        }

        $status = strtoupper(trim((string) ($request['payment_status'] ?? '')));
        return in_array($status, [
            DocumentPayment::STATUS_PAID,
            DocumentPayment::STATUS_PAID_VERIFIED,
            DocumentPayment::STATUS_PAID_AT_PICKUP,
            DocumentPayment::STATUS_NOT_REQUIRED,
            DocumentPayment::STATUS_FREE,
            DocumentPayment::STATUS_WAIVED,
        ], true);
    }

    /**
     * Check if this request requires payment.
     */
    public static function requiresPayment(array|int $request): bool
    {
        if (is_int($request)) {
            $request = self::find($request);
        }
        if (!$request) {
            return false;
        }

        $fee = (float) ($request['fee_amount'] ?? 0);
        $status = (string) ($request['payment_status'] ?? '');
        return $fee > 0.0 && !in_array($status, [DocumentPayment::STATUS_FREE, DocumentPayment::STATUS_WAIVED], true);
    }

    /**
     * File a request with chosen delivery method and fee snapshot. Returns reference number.
     */
    public static function create(
        int $userId,
        string $type,
        string $purpose,
        string $notes = '',
        string $deliveryMethod = 'pickup',
        float $feeAmount = 0.00,
        string $paymentMethod = 'free',
        ?int $gcashAccountId = null,
        ?string $deliveryAddress = null,
        float $deliveryFee = 0.00
    ): array {
        $reference = 'BRG-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $method    = \array_key_exists($deliveryMethod, self::DELIVERY_METHODS) ? $deliveryMethod : 'pickup';
        $docType   = \array_key_exists($type, self::TYPES) ? $type : 'other';
        $cleanPurp = mb_substr(trim($purpose), 0, 255);
        $cleanNote = trim($notes) !== '' ? mb_substr(trim($notes), 0, 2000) : null;

        $isFree = $feeAmount <= 0.00;
        $payStatus = match ($paymentMethod) {
            'free'              => DocumentPayment::STATUS_NOT_REQUIRED,
            'pickup'            => DocumentPayment::STATUS_PAY_AT_PICKUP,
            'paymongo', 'gcash' => $isFree ? DocumentPayment::STATUS_NOT_REQUIRED : DocumentPayment::STATUS_PENDING,
            default             => $isFree ? DocumentPayment::STATUS_NOT_REQUIRED : DocumentPayment::STATUS_PENDING,
        };

        // Requirement 21 & 23:
        // Free documents start at pending_review with payment NOT_REQUIRED.
        // Paid documents start at awaiting_payment with payment PENDING until PayMongo confirms!
        $initialDocStatus = $isFree ? 'pending_review' : 'awaiting_payment';

        $pdo = db();
        $stmt = $pdo->prepare(
            'INSERT INTO document_requests
             (reference_no, user_id, document_type, purpose, notes, status, delivery_method,
              delivery_address, delivery_fee, fee_amount, payment_method, payment_status, requested_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $reference,
            $userId,
            $docType,
            $cleanPurp,
            $cleanNote,
            $initialDocStatus,
            $method,
            $deliveryAddress ?: null,
            $deliveryFee,
            $feeAmount,
            $paymentMethod,
            $payStatus,
        ]);

        $newId = (int) $pdo->lastInsertId();

        // Create financial payment record if paid document or tracked
        $paymentId = DocumentPayment::createForRequest($newId, $userId, $docType, $feeAmount, $paymentMethod, $gcashAccountId);

        self::logActivity(
            $newId,
            $userId,
            'requested',
            null,
            $initialDocStatus,
            sprintf('Submitted request for %s via %s. Fee: ₱%.2f (%s)', self::label($docType), self::deliveryLabel($method), $feeAmount, strtoupper($paymentMethod))
        );

        return [
            'id'             => $newId,
            'reference'      => $reference,
            'payment_id'     => $paymentId,
            'payment_method' => $paymentMethod,
            'status'         => $initialDocStatus,
            'fee_amount'     => $feeAmount,
            'is_free'        => $isFree,
        ];
    }

    /**
     * Requirement 16 & 2: Automatically generate official certificate on approval.
     */
    public static function approveAndGenerate(int $id, int $staffId, ?string $staffNote = null): array
    {
        $request = self::find($id);
        if (!$request) {
            return ['success' => false, 'error' => 'Hindi nahanap ang request.'];
        }

        // Check payment if required for digital release
        if ($request['delivery_method'] === 'digital' && !self::isPaymentVerified($request)) {
            return ['success' => false, 'error' => 'Kailangan munang beripikado ang bayad bago maaprubahan ang digital soft copy.'];
        }

        $resident = User::find((int) $request['user_id']);
        if (!$resident) {
            return ['success' => false, 'error' => 'Hindi nahanap ang residente.'];
        }

        // Generate sequential certificate number if not yet assigned
        $certNo = !empty($request['certificate_no'])
            ? (string) $request['certificate_no']
            : \App\Services\CertificateGeneratorService::generateCertificateNumber((string) $request['document_type']);

        $request['certificate_no'] = $certNo;

        // Auto-generate official PDF certificate
        $pdfBinary = \App\Services\CertificateGeneratorService::generatePdf($request, $resident);

        $docTitleSlug = str_replace([' ', '/'], '_', self::label((string) $request['document_type']));
        $cleanFileName = sprintf('%s_%s.pdf', $docTitleSlug, $certNo);

        // Store into database blob via DocumentStorageService (survives Render restarts)
        $storageService = new \App\Services\DocumentStorageService();
        $stored = $storageService->storeRaw($id, $pdfBinary, $cleanFileName, 'application/pdf');

        $method = (string) ($request['delivery_method'] ?? 'pickup');
        $newStatus = 'ready'; // ready for pickup, or available for download
        $deliveryStatus = ($method === 'delivery') ? 'Preparing' : null;

        $pdo = db();
        $stmt = $pdo->prepare(
            'UPDATE document_requests
             SET status = ?, certificate_no = ?,
                 document_file_url = ?, document_file_name = ?, document_file_type = ?, document_file_size = ?,
                 document_uploaded_at = NOW(), document_uploaded_by = ?,
                 issued_at = NOW(), issued_by = ?, handled_by = ?, ready_at = NOW(),
                 delivery_status = COALESCE(?, delivery_status),
                 staff_note = COALESCE(?, staff_note),
                 updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            $newStatus,
            $certNo,
            $stored['url'],
            $stored['clean_name'],
            $stored['mime'],
            $stored['size'],
            $staffId,
            $staffId,
            $staffId,
            $deliveryStatus,
            $staffNote ?: null,
            $id,
        ]);

        self::logActivity(
            $id,
            $staffId,
            'approved_generated',
            (string) $request['status'],
            $newStatus,
            sprintf('Approved request and automatically generated official certificate %s (%s)', $certNo, $stored['clean_name'])
        );

        // Tell resident
        $noticeMsg = match ($method) {
            'digital'  => "Naaprubahan na ang inyong {$certNo} at handa nang i-download ang inyong opisyal na kopya.",
            'delivery' => "Naaprubahan na ang inyong {$certNo} at kasalukuyan nang inihahanda para sa delivery.",
            default    => "Naaprubahan na ang inyong {$certNo} at handa nang kunin sa Barangay Hall.",
        };

        try {
            (new \App\Services\NotificationService())->notifyUser(
                (int) $request['user_id'],
                'system',
                'Naaprubahan ang Inyong Dokumento (' . $certNo . ')',
                $noticeMsg,
                0,
                'document_request'
            );
        } catch (\Throwable $e) {}

        return [
            'success'        => true,
            'certificate_no' => $certNo,
            'file_name'      => $stored['clean_name'],
            'url'            => $stored['url'],
        ];
    }

    /**
     * Requirement 6: Barangay Hall Pickup — Mark as Claimed.
     */
    public static function markAsClaimed(int $id, int $staffId, ?string $notes = null): bool
    {
        $request = self::find($id);
        if (!$request) {
            return false;
        }

        $pdo = db();
        $stmt = $pdo->prepare(
            'UPDATE document_requests
             SET status = "released", claimed_at = NOW(), claimed_by = ?, released_at = NOW(), completed_at = NOW(),
                 staff_note = COALESCE(?, staff_note), updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$staffId, $notes ?: null, $id]);

        self::logActivity(
            $id,
            $staffId,
            'claimed',
            (string) $request['status'],
            'released',
            sprintf('Marked document %s as claimed at Barangay Hall.', (string) ($request['certificate_no'] ?? $request['reference_no']))
        );

        try {
            (new \App\Services\NotificationService())->notifyUser(
                (int) $request['user_id'],
                'system',
                'Nakuha na ang Dokumento',
                'Matagumpay na naitala ang pagkuha sa inyong opisyal na dokumento (' . (string) ($request['certificate_no'] ?? $request['reference_no']) . ') sa Barangay Hall. Maraming salamat!',
                0,
                'document_request'
            );
        } catch (\Throwable $e) {}

        return true;
    }

    /**
     * Requirement 7: Delivery management status updates.
     */
    public static function updateDelivery(int $id, string $deliveryStatus, ?string $deliveredBy, int $staffId, ?string $notes = null): bool
    {
        $request = self::find($id);
        if (!$request) {
            return false;
        }

        $dispatchedAt = ($deliveryStatus === 'Out for Delivery') ? ', dispatched_at = NOW()' : '';
        $deliveredAt  = ($deliveryStatus === 'Delivered') ? ', delivered_at = NOW(), completed_at = NOW(), released_at = NOW(), status = "released"' : '';

        $pdo = db();
        $sql = "UPDATE document_requests
                SET delivery_status = ?, delivered_by = COALESCE(?, delivered_by),
                    staff_note = COALESCE(?, staff_note), updated_at = NOW()
                    {$dispatchedAt} {$deliveredAt}
                WHERE id = ?";
        $pdo->prepare($sql)->execute([$deliveryStatus, $deliveredBy ?: null, $notes ?: null, $id]);

        self::logActivity(
            $id,
            $staffId,
            'delivery_update',
            (string) $request['status'],
            ($deliveryStatus === 'Delivered') ? 'released' : (string) $request['status'],
            sprintf('Updated delivery status to "%s"%s', $deliveryStatus, $deliveredBy ? " by $deliveredBy" : '')
        );

        try {
            $msg = match ($deliveryStatus) {
                'Out for Delivery' => 'Ang inyong dokumento (' . (string) ($request['certificate_no'] ?? $request['reference_no']) . ') ay papalabas na para i-deliver!',
                'Delivered'        => 'Matagumpay nang nai-deliver ang inyong dokumento (' . (string) ($request['certificate_no'] ?? $request['reference_no']) . ').',
                default            => 'Na-update ang estado ng inyong delivery sa: ' . $deliveryStatus,
            };
            (new \App\Services\NotificationService())->notifyUser(
                (int) $request['user_id'],
                'system',
                'Delivery Update: ' . $deliveryStatus,
                $msg,
                0,
                'document_request'
            );
        } catch (\Throwable $e) {}

        return true;
    }

    /**
     * Requirement 1: Modern Government Portal Status Badges.
     */
    public static function statusBadgeHtml(array $request): string
    {
        $status = (string) ($request['status'] ?? 'pending');
        $method = (string) ($request['delivery_method'] ?? 'pickup');
        $delivStatus = (string) ($request['delivery_status'] ?? '');

        if ($method === 'delivery' && !empty($delivStatus)) {
            return match ($delivStatus) {
                'Out for Delivery' => '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-800 border border-purple-200"><i class="bi bi-truck"></i> Out for Delivery</span>',
                'Delivered'        => '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200"><i class="bi bi-check2-circle"></i> Delivered</span>',
                'Failed Delivery'  => '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200"><i class="bi bi-exclamation-triangle"></i> Failed Delivery</span>',
                default            => '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 border border-indigo-200"><i class="bi bi-box-seam"></i> Preparing Delivery</span>',
            };
        }

        return match ($status) {
            'pending'          => '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200"><i class="bi bi-hourglass-split"></i> Pending</span>',
            'under_review',
            'processing'       => '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 border border-indigo-200"><i class="bi bi-arrow-repeat"></i> Under Review</span>',
            'needs_information'=> '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-orange-100 text-orange-800 border border-orange-200"><i class="bi bi-info-circle"></i> Needs Information</span>',
            'approved'         => '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200"><i class="bi bi-check-circle"></i> Approved</span>',
            'ready'            => ($method === 'digital')
                ? '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-200"><i class="bi bi-download"></i> Available for Download</span>'
                : '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-cyan-100 text-cyan-800 border border-cyan-200"><i class="bi bi-building"></i> Ready for Pickup</span>',
            'out_for_delivery' => '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-800 border border-purple-200"><i class="bi bi-truck"></i> Out for Delivery</span>',
            'released',
            'completed'        => '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200"><i class="bi bi-check2-all"></i> Completed</span>',
            'rejected'         => '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200"><i class="bi bi-x-circle"></i> Rejected</span>',
            'cancelled'        => '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200"><i class="bi bi-slash-circle"></i> Cancelled</span>',
            default            => '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">' . htmlspecialchars(ucfirst($status)) . '</span>',
        };
    }

    /** One request, with requester's details, staff names, and payment details. */
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT r.*, u.full_name, u.phone, u.email, u.zone, u.address,
                    su.full_name AS uploaded_by_name,
                    hb.full_name AS handled_by_name,
                    p.payment_ref, p.amount_reported, p.gcash_reference_no, p.receipt_file_name, p.rejection_reason, p.receipt_file_hash,
                    g.account_name AS gcash_account_name, g.mobile_number AS gcash_mobile_number
               FROM document_requests r
               JOIN users u ON u.id = r.user_id
          LEFT JOIN users su ON su.id = r.document_uploaded_by
          LEFT JOIN users hb ON hb.id = r.handled_by
          LEFT JOIN document_payments p ON p.id = r.payment_id
          LEFT JOIN gcash_accounts g ON g.id = p.gcash_account_id
              WHERE r.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Everything one resident has asked for, newest first. */
    public static function forUser(int $userId): array
    {
        $stmt = db()->prepare(
            'SELECT r.*, su.full_name AS uploaded_by_name,
                    p.payment_ref, p.amount_reported, p.gcash_reference_no, p.receipt_file_name, p.rejection_reason, p.rejection_note, p.verified_at,
                    g.account_name AS gcash_account_name, g.mobile_number AS gcash_mobile_number, g.qr_image_data, g.qr_mime_type
               FROM document_requests r
          LEFT JOIN users su ON su.id = r.document_uploaded_by
          LEFT JOIN document_payments p ON p.id = r.payment_id
          LEFT JOIN gcash_accounts g ON g.id = p.gcash_account_id
              WHERE r.user_id = ?
              ORDER BY r.requested_at DESC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * The admin queue with status, delivery method, payment status, and search filtering.
     */
    public static function queue(string $status = '', string $delivery = '', string $search = '', string $paymentStatus = ''): array
    {
        $sql = "SELECT r.*, u.full_name, u.phone, u.zone,
                       su.full_name AS uploaded_by_name,
                       p.payment_ref, p.amount_reported, p.gcash_reference_no, p.receipt_file_name, p.rejection_reason,
                       g.account_name AS gcash_account_name
                  FROM document_requests r
                  JOIN users u ON u.id = r.user_id
             LEFT JOIN users su ON su.id = r.document_uploaded_by
             LEFT JOIN document_payments p ON p.id = r.payment_id
             LEFT JOIN gcash_accounts g ON g.id = p.gcash_account_id
                 WHERE 1=1";
        $params = [];

        if ($status !== '' && \array_key_exists($status, self::TRANSITIONS)) {
            $sql .= ' AND r.status = ?';
            $params[] = $status;
        }

        if ($delivery !== '' && \array_key_exists($delivery, self::DELIVERY_METHODS)) {
            $sql .= ' AND r.delivery_method = ?';
            $params[] = $delivery;
        }

        if ($paymentStatus !== '') {
            $sql .= ' AND r.payment_status = ?';
            $params[] = $paymentStatus;
        }

        $search = trim($search);
        if ($search !== '') {
            $sql .= ' AND (r.reference_no ILIKE ? OR u.full_name ILIKE ? OR r.purpose ILIKE ? OR r.document_type ILIKE ? OR p.payment_ref ILIKE ? OR p.gcash_reference_no ILIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= " ORDER BY CASE r.status WHEN 'pending' THEN 1 WHEN 'processing' THEN 2 WHEN 'ready' THEN 3 WHEN 'awaiting_payment' THEN 4 WHEN 'released' THEN 5 WHEN 'rejected' THEN 6 ELSE 7 END,
                           r.requested_at ASC";

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Move a request to a new status and record timestamp and audit log.
     */
    public static function setStatus(int $id, string $to, int $staffId, string $staffNote = ''): bool
    {
        $current = self::find($id);
        if ($current === null || !self::canMove((string) $current['status'], $to)) {
            return false;
        }

        $oldStatus       = (string) $current['status'];
        $deliveryMethod  = (string) ($current['delivery_method'] ?? 'pickup');

        $stamp = match ($to) {
            'ready'    => ', ready_at = NOW()',
            'released' => ($deliveryMethod === 'digital') ? ', released_at = NOW(), completed_at = NOW()' : ', released_at = NOW()',
            default    => '',
        };

        $noteValue = trim($staffNote) !== '' ? mb_substr(trim($staffNote), 0, 2000) : $current['staff_note'];

        db()->prepare(
            "UPDATE document_requests
                SET status = ?, staff_note = ?, handled_by = ?{$stamp}, updated_at = NOW()
              WHERE id = ?"
        )->execute([
            $to,
            $noteValue,
            $staffId,
            $id,
        ]);

        $details = sprintf('Status updated from %s to %s', $oldStatus, $to);
        if (trim($staffNote) !== '') {
            $details .= ' (Staff Note: ' . trim($staffNote) . ')';
        }
        self::logActivity($id, $staffId, 'status_change', $oldStatus, $to, $details);

        return true;
    }

    /**
     * Attach or replace uploaded document soft copy.
     *
     * @param int $id
     * @param array{url: string, clean_name: string, mime: string, size: int} $fileMeta
     * @param int $staffId
     * @param string $adminNote
     * @return bool
     */
    public static function attachFile(int $id, array $fileMeta, int $staffId, string $adminNote = ''): bool
    {
        $current = self::find($id);
        if ($current === null) {
            return false;
        }

        $isReplacement = !empty($current['document_file_name']);

        $noteUpdate = '';
        $params = [
            $fileMeta['url'],
            $fileMeta['clean_name'],
            $fileMeta['mime'],
            $fileMeta['size'],
            $staffId,
        ];

        if (trim($adminNote) !== '') {
            $noteUpdate = ', staff_note = ?';
            $params[]   = mb_substr(trim($adminNote), 0, 2000);
        }

        $params[] = $id;

        $sql = "UPDATE document_requests
                   SET document_file_url = ?,
                       document_file_name = ?,
                       document_file_type = ?,
                       document_file_size = ?,
                       document_uploaded_at = NOW(),
                       document_uploaded_by = ?,
                       updated_at = NOW()
                       {$noteUpdate}
                 WHERE id = ?";

        db()->prepare($sql)->execute($params);

        $action  = $isReplacement ? 'file_replaced' : 'file_uploaded';
        $details = sprintf(
            '%s: %s (%s)',
            $isReplacement ? 'Replaced document file' : 'Uploaded document file',
            $fileMeta['clean_name'],
            self::formatFileSize((int) $fileMeta['size'])
        );
        if (trim($adminNote) !== '') {
            $details .= ' Note: ' . trim($adminNote);
        }

        self::logActivity(
            $id,
            $staffId,
            $action,
            (string) $current['status'],
            (string) $current['status'],
            $details
        );

        return true;
    }

    /**
     * Remove attached file and delete file record.
     */
    public static function removeFile(int $id, int $staffId, string $reason = ''): bool
    {
        $current = self::find($id);
        if ($current === null) {
            return false;
        }

        $oldName = (string) ($current['document_file_name'] ?? '');

        // Remove from storage / DB blob
        (new \App\Services\DocumentStorageService())->deleteFile($id);

        db()->prepare(
            'UPDATE document_requests
                SET document_file_url = NULL,
                    document_file_name = NULL,
                    document_file_type = NULL,
                    document_file_size = NULL,
                    document_uploaded_at = NULL,
                    document_uploaded_by = NULL,
                    updated_at = NOW()
              WHERE id = ?'
        )->execute([$id]);

        $details = 'Removed attached file: ' . ($oldName ?: 'Document file');
        if (trim($reason) !== '') {
            $details .= ' Reason: ' . trim($reason);
        }

        self::logActivity(
            $id,
            $staffId,
            'file_deleted',
            (string) $current['status'],
            (string) $current['status'],
            $details
        );

        return true;
    }

    /**
     * Log an action in document_request_logs.
     */
    public static function logActivity(
        int $requestId,
        ?int $userId,
        string $action,
        ?string $oldStatus = null,
        ?string $newStatus = null,
        ?string $details = null
    ): void {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            db()->prepare(
                'INSERT INTO document_request_logs
                 (request_id, user_id, action, old_status, new_status, details, ip_address, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                $requestId,
                $userId,
                $action,
                $oldStatus,
                $newStatus,
                $details,
                $ip,
            ]);
        } catch (\Throwable $e) {
            error_log('[DocumentRequest::logActivity] ' . $e->getMessage());
        }
    }

    /**
     * Get all audit logs for a request.
     */
    public static function getActivityLogs(int $requestId): array
    {
        try {
            $stmt = db()->prepare(
                'SELECT l.*, u.full_name AS actor_name, u.role AS actor_role
                   FROM document_request_logs l
              LEFT JOIN users u ON u.id = l.user_id
                  WHERE l.request_id = ?
                  ORDER BY l.created_at ASC, l.id ASC'
            );
            $stmt->execute([$requestId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Record download action.
     */
    public static function recordDownload(int $requestId, int $userId): void
    {
        self::logActivity(
            $requestId,
            $userId,
            'file_downloaded',
            null,
            null,
            'Resident downloaded or opened document soft copy.'
        );
    }

    /**
     * How many requests are waiting on staff.
     */
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

    /**
     * Format byte size to human readable string.
     */
    public static function formatFileSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);
        return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
    }
}
