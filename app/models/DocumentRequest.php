<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * A resident asking the barangay to prepare a document.
 *
 * Requested online, picked up at the Barangay Hall: staff approve the request
 * when the document is ready for pickup, then mark it claimed. Older requests
 * may still be Digital Soft Copy, which keeps its preview and download.
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
        return __(self::DELIVERY_METHODS[$method] ?? ($method === 'digital' ? 'Digital Copy' : 'Pickup at Barangay Hall'));
    }

    public static function statusLabel(string $status): string
    {
        $normalized = strtolower(trim($status));
        // Bilingual labels ("Tinanggihan (Rejected)"); __() shows one language.
        return __(match ($normalized) {
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
            default                  => ucwords(str_replace('_', ' ', $status)),
        });
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
     * Why payment blocks moving this request to $to, or null if it may move.
     *
     * Always null since 2026-10-22: online payment (GCash/PayMongo) was
     * removed. Residents request online and any fee is paid in cash at the
     * hall on pickup, so staff approve and release without a payment step.
     * Kept as the single hook setStatus, markAsClaimed and approveAndGenerate
     * call, in case a payment rule is ever needed again.
     */
    public static function paymentBlocksMove(array $request, string $to): ?string
    {
        return null;
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

        // Every request goes straight to staff review: there is no online
        // payment to wait for (any fee is paid at the hall on pickup).
        $initialDocStatus = 'pending_review';

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

    /** What a Business Permit is for, as the resident typed it. */
    public static function setBusinessDetails(int $id, string $name, string $type, string $address = ''): void
    {
        db()->prepare('UPDATE document_requests SET business_name = ?, business_type = ?, business_address = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$name, $type, $address !== '' ? $address : null, $id]);
    }

    /**
     * Requirement 16 & 2: Automatically generate official certificate on approval.
     */
    public static function approveAndGenerate(int $id, int $staffId, ?string $staffNote = null, ?string $orNumber = null): array
    {
        $request = self::find($id);
        if (!$request) {
            return ['success' => false, 'error' => 'Hindi nahanap ang request.'];
        }

        if ($blocked = self::paymentBlocksMove($request, 'approved')) {
            return ['success' => false, 'error' => $blocked];
        }

        // A final document carries the Punong Barangay's authorized
        // e-signature. Without it nothing is released.
        if (!\App\Models\DocumentTemplate::hasSignature('captain')) {
            return ['success' => false, 'error' => 'Signature Required: the Punong Barangay\'s authorized e-signature has not been uploaded yet (Admin → Document Templates). The request stays pending.'];
        }

        $type = (string) $request['document_type'];
        if ($type === 'business' && (trim((string) ($request['business_name'] ?? '')) === '' || trim((string) ($request['business_type'] ?? '')) === '')) {
            return ['success' => false, 'error' => 'This Business Permit has no business name or kind of business. Ask the resident for it (Request more info) before approving.'];
        }

        $resident = User::find((int) $request['user_id']);
        if (!$resident) {
            return ['success' => false, 'error' => 'Hindi nahanap ang residente.'];
        }

        try {
            // Numbers are assigned once; approving again keeps them.
            $certNo = !empty($request['certificate_no'])
                ? (string) $request['certificate_no']
                : \App\Services\CertificateGeneratorService::generateCertificateNumber($type);
            $orNumber = trim((string) $orNumber);
            if ($orNumber === '') {
                $orNumber = !empty($request['or_number']) ? (string) $request['or_number']
                    : ((float) ($request['fee_amount'] ?? 0) > 0 ? \App\Services\CertificateGeneratorService::nextOrNumber() : '');
            }
            $permitNo   = (string) ($request['permit_no'] ?? '');
            $validUntil = (string) ($request['valid_until'] ?? '');
            if ($type === 'business') {
                $permitNo   = $permitNo !== '' ? $permitNo : \App\Services\CertificateGeneratorService::nextPermitNumber();
                $validUntil = $validUntil !== '' ? $validUntil : date('Y-m-d', strtotime('+1 year'));
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $request['certificate_no'] = $certNo;
        $request['or_number']      = $orNumber !== '' ? $orNumber : null;
        $request['permit_no']      = $permitNo !== '' ? $permitNo : null;
        $request['valid_until']    = $validUntil !== '' ? $validUntil : null;
        $request['issued_at']      = date('Y-m-d H:i:s');

        // The official PDF from the barangay's template, filled from the profile
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
             SET status = ?, certificate_no = ?, or_number = ?, permit_no = ?, valid_until = ?,
                 revoked_at = NULL, revoked_by = NULL, revoke_reason = NULL,
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
            $request['or_number'],
            $request['permit_no'],
            $request['valid_until'],
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
            sprintf('Approved and generated %s from the %s template (%s)%s%s, signed with the Punong Barangay\'s e-signature',
                $certNo, $type, $stored['clean_name'],
                $request['or_number'] ? ', O.R. ' . $request['or_number'] : '',
                $request['permit_no'] ? ', permit ' . $request['permit_no'] : '')
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
        if (!$request || self::paymentBlocksMove($request, 'released') !== null) {
            return false;
        }

        $pdo = db();
        $stmt = $pdo->prepare(
            'UPDATE document_requests
             SET status = \'released\', claimed_at = NOW(), claimed_by = ?, released_at = NOW(), completed_at = NOW(),
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
                    p.paid_at, p.verification_type, p.paymongo_payment_id, p.paymongo_source_type
               FROM document_requests r
          LEFT JOIN users su ON su.id = r.document_uploaded_by
          LEFT JOIN document_payments p ON p.id = r.payment_id
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
                       p.id AS pay_id, p.paymongo_payment_id, p.paid_at, p.verification_type, p.paymongo_source_type
                  FROM document_requests r
                  JOIN users u ON u.id = r.user_id
             LEFT JOIN users su ON su.id = r.document_uploaded_by
             LEFT JOIN document_payments p ON p.id = r.payment_id
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
            // ILIKE exists only on PostgreSQL (Render); MariaDB/MySQL (XAMPP)
            // threw a syntax error on every search — the staff dashboard's
            // Recent Tasks links land here with ?search=<reference>. LIKE is
            // already case-insensitive there (utf8mb4_unicode_ci).
            $like = db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' ? 'ILIKE' : 'LIKE';
            $sql .= " AND (r.reference_no {$like} ? OR u.full_name {$like} ? OR r.purpose {$like} ? OR r.document_type {$like} ? OR p.payment_ref {$like} ? OR p.gcash_reference_no {$like} ?)";
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
        if (self::paymentBlocksMove($current, $to) !== null) {
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

    // ── Printed copies and revocation ───────────────────────────────────

    /**
     * The resident asks for the approved digital document on paper. The same
     * generated PDF goes to the staff Print Queue; nobody retypes it.
     *
     * @return string|null Error, or null when queued.
     */
    public static function requestPrint(int $id, int $userId): ?string
    {
        $request = self::find($id);
        if ($request === null || (int) $request['user_id'] !== $userId) {
            return 'Request not found.';
        }
        if (empty($request['document_file_name']) || empty($request['certificate_no']) || !empty($request['revoked_at'])) {
            return 'A printed copy can be requested once the document is approved.';
        }
        if (!self::isPaymentVerified($request)) {
            return 'Pay the fee first; the printed copy is made after the payment is approved.';
        }
        if (!empty($request['print_requested_at']) && empty($request['printed_at'])) {
            return 'A printed copy is already in the barangay\'s print queue.';
        }

        db()->prepare('UPDATE document_requests SET print_requested_at = NOW(), printed_at = NULL, printed_by = NULL, updated_at = NOW() WHERE id = ?')
            ->execute([$id]);
        self::logActivity($id, $userId, 'print_requested', (string) $request['status'], (string) $request['status'], 'Resident requested a printed copy of ' . $request['certificate_no']);

        try {
            (new \App\Services\NotificationService())->notifyBackOffice(
                'notify_content', 'system',
                'Printed copy requested — ' . $request['certificate_no'],
                $request['full_name'] . ' asked for a printed ' . self::label((string) $request['document_type']) . '. It is in the Print Queue.',
                0, 'document_request', $userId
            );
        } catch (\Throwable $e) {
        }

        return null;
    }

    /** Requests waiting to be printed, oldest first. */
    public static function printQueue(): array
    {
        return db()->query(
            "SELECT r.id, r.reference_no, r.certificate_no, r.document_type, r.print_requested_at, r.printed_at,
                    r.status, r.delivery_method, u.full_name, u.zone, u.phone
               FROM document_requests r
               JOIN users u ON u.id = r.user_id
              WHERE r.print_requested_at IS NOT NULL AND r.printed_at IS NULL AND r.revoked_at IS NULL
              ORDER BY r.print_requested_at ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Staff printed it; the resident is told to claim it at the hall. */
    public static function markPrinted(int $id, int $staffId): bool
    {
        $request = self::find($id);
        if ($request === null || empty($request['print_requested_at']) || !empty($request['printed_at'])) {
            return false;
        }
        db()->prepare('UPDATE document_requests SET printed_at = NOW(), printed_by = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$staffId, $id]);
        self::logActivity($id, $staffId, 'printed', (string) $request['status'], (string) $request['status'], 'Printed copy of ' . $request['certificate_no'] . ' made from the approved PDF');

        try {
            (new \App\Services\NotificationService())->notifyUser(
                (int) $request['user_id'], 'system',
                'Printed copy ready — ' . $request['certificate_no'],
                'Your printed ' . self::label((string) $request['document_type']) . ' is ready. Claim it at the Barangay Hall; show your document QR.',
                0, 'document_request'
            );
        } catch (\Throwable $e) {
        }

        return true;
    }

    /**
     * Cancel an issued document. Its verification page then says CANCELLED
     * and the resident can no longer download it.
     */
    public static function revoke(int $id, int $staffId, string $reason): bool
    {
        $request = self::find($id);
        if ($request === null || empty($request['certificate_no']) || !empty($request['revoked_at'])) {
            return false;
        }
        db()->prepare('UPDATE document_requests SET revoked_at = NOW(), revoked_by = ?, revoke_reason = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$staffId, mb_substr($reason, 0, 255), $id]);
        self::logActivity($id, $staffId, 'revoked', (string) $request['status'], (string) $request['status'], 'Cancelled ' . $request['certificate_no'] . ': ' . $reason);

        try {
            (new \App\Services\NotificationService())->notifyUser(
                (int) $request['user_id'], 'system',
                'Document cancelled — ' . $request['certificate_no'],
                'The barangay cancelled your ' . self::label((string) $request['document_type']) . '. Reason: ' . $reason,
                0, 'document_request'
            );
        } catch (\Throwable $e) {
        }

        return true;
    }

    /** Recent template-based generations and document actions, for the audit view. */
    public static function generationLog(int $limit = 30): array
    {
        $limit = max(1, min(200, $limit));

        return db()->query(
            "SELECT l.created_at, l.action, l.details, r.certificate_no, r.document_type, u.full_name AS by_name
               FROM document_request_logs l
               JOIN document_requests r ON r.id = l.request_id
          LEFT JOIN users u ON u.id = l.user_id
              WHERE l.action IN ('approved_generated', 'printed', 'print_requested', 'revoked', 'claimed')
              ORDER BY l.id DESC
              LIMIT {$limit}"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
