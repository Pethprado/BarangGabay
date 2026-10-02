<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Services\DocumentStorageService;
use App\Services\NotificationService;
use App\Services\SemaphoreSmsService;

/**
 * Residents asking the barangay to prepare a document, and staff working
 * through the queue.
 *
 * Supports both:
 * 1. Personal Pickup at the Barangay Hall.
 * 2. Digital Soft Copy delivery online (direct download and printing).
 */
class DocumentRequestController
{
    // ── Resident ─────────────────────────────────────────────────────────

    /** GET /documents — the request form and this resident's own history. */
    public function index(): void
    {
        $userId          = (int) ($_SESSION['user_id'] ?? 0);
        $user            = \App\Models\User::find($userId);
        $requests        = DocumentRequest::forUser($userId);
        $types           = DocumentRequest::TYPES;
        $docFees         = \App\Models\DocumentFee::getAll();
        $defaultGcash    = \App\Models\GcashAccount::getDefault();
        $gcashAccounts   = \App\Models\GcashAccount::getActive();
        $deliveryEnabled = (bool) \App\Models\Setting::get('doc_delivery_enabled', true);
        $deliveryFee     = (float) \App\Models\Setting::get('doc_delivery_fee', 40.00);

        view('resident/documents', compact('user', 'requests', 'types', 'docFees', 'defaultGcash', 'gcashAccounts', 'deliveryEnabled', 'deliveryFee'));
    }

    /** POST /documents — file a new request. */
    public function store(): void
    {
        check_csrf();

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $user   = \App\Models\User::find($userId);

        // Requirement 11: Resident Verification Gate
        if (!$user || ($user['status'] ?? '') !== 'verified') {
            flash('warning', 'Ang inyong resident account ay hindi pa ganap na beripikado. Ang mga beripikadong residente lamang ang maaaring humiling ng opisyal na dokumento ng barangay.');
            redirect('/documents');
        }

        $type           = trim($_POST['document_type'] ?? '');
        $purpose        = trim($_POST['purpose'] ?? '');
        $notes          = trim($_POST['notes'] ?? '');
        $deliveryMethod = trim($_POST['delivery_method'] ?? 'pickup');

        if (!\array_key_exists($type, DocumentRequest::TYPES)) {
            flash('error', t('documents.err_type'));
            redirect('/documents');
        }

        if (!\array_key_exists($deliveryMethod, DocumentRequest::DELIVERY_METHODS)) {
            flash('error', t('documents.err_delivery'));
            redirect('/documents');
        }

        if (mb_strlen($purpose) < 3) {
            flash('error', t('documents.err_purpose'));
            redirect('/documents');
        }

        // Prevent duplicate open request per document type
        foreach (DocumentRequest::forUser($userId) as $existing) {
            if ($existing['document_type'] === $type
                && \in_array($existing['status'], ['pending', 'processing', 'ready'], true)) {
                flash('warning', t('documents.err_duplicate', [
                    'type'      => DocumentRequest::label($type),
                    'reference' => (string) $existing['reference_no'],
                ]));
                redirect('/documents');
            }
        }

        // Calculate document fee snapshot
        $feeInfo   = \App\Models\DocumentFee::getFeeForType($type);
        $feeAmount = $feeInfo['is_free'] ? 0.00 : (float) $feeInfo['amount'];

        // Delivery handling
        $deliveryAddress = null;
        $deliveryFee     = 0.00;
        if ($deliveryMethod === 'delivery') {
            $isDeliveryEnabled = (bool) \App\Models\Setting::get('doc_delivery_enabled', true);
            if (!$isDeliveryEnabled) {
                flash('error', 'Kasalukuyang hindi available ang serbisyong delivery.');
                redirect('/documents');
            }
            $deliveryAddress = trim($_POST['delivery_address'] ?? '');
            if ($deliveryAddress === '') {
                $deliveryAddress = \App\Models\User::formatAddress($user);
            }
            $deliveryFee = (float) \App\Models\Setting::get('doc_delivery_fee', 40.00);
            $feeAmount += $deliveryFee;
        }

        $isFree       = $feeAmount <= 0.00;
        $chosenMethod = trim($_POST['payment_method'] ?? ($isFree ? 'free' : 'pickup'));

        if ($isFree) {
            $paymentMethod = 'free';
        } else {
            // For digital soft copy or delivery, online payment (GCash or PayPal) is standard
            if ($deliveryMethod === 'digital' || $deliveryMethod === 'delivery') {
                $paymentMethod = in_array($chosenMethod, ['gcash', 'paypal'], true) ? $chosenMethod : 'gcash';
            } else {
                $paymentMethod = in_array($chosenMethod, ['gcash', 'paypal', 'pickup'], true) ? $chosenMethod : 'pickup';
            }
        }

        $chosenGcashId = !empty($_POST['gcash_account_id']) ? (int) $_POST['gcash_account_id'] : null;
        if (!$chosenGcashId) {
            $defaultGcash = \App\Models\GcashAccount::getDefault();
            $chosenGcashId = $defaultGcash ? (int) $defaultGcash['id'] : null;
        }

        $created = DocumentRequest::create(
            $userId,
            $type,
            $purpose,
            $notes,
            $deliveryMethod,
            $feeAmount,
            $paymentMethod,
            $chosenGcashId,
            $deliveryAddress,
            $deliveryFee
        );

        $newId     = (int) $created['id'];
        $reference = (string) $created['reference'];

        // Tell back office there is work waiting
        $deliveryText = $deliveryMethod === 'digital' ? ' (Digital Soft Copy)' : ' (Personal Pickup)';
        $feeText = $isFree ? ' [LIBRE]' : sprintf(' [₱%.2f via %s]', $feeAmount, strtoupper($paymentMethod));
        (new NotificationService())->notifyBackOffice(
            'notify_content',
            'system',
            t('documents.notify_staff_title'),
            t('documents.notify_staff_body', [
                'type'      => DocumentRequest::label($type) . $deliveryText . $feeText,
                'reference' => $reference,
            ]),
            0,
            'document_request',
            $userId
        );

        // If Online payment (PayPal / GCash) is required: redirect immediately to the checkout page!
        if (in_array($paymentMethod, ['paypal', 'gcash'], true) && !$isFree) {
            flash('info', 'Nalikha na ang inyong kahilingan (' . $reference . '). Kumpletuhin ang pagbabayad gamit ang PayPal Checkout sa ibaba.');
            redirect('/documents/' . $newId . '/payment');
        }

        flash('success', t('documents.filed', ['reference' => $reference]));
        redirect('/documents');
    }

    /** GET /documents/{id}/download — Resident downloading attached soft copy */
    public function download(array $params): void
    {
        $id     = (int) ($params['id'] ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $role   = (string) ($_SESSION['role'] ?? $_SESSION['user_role'] ?? '');

        $request = DocumentRequest::find($id);
        if ($request === null) {
            flash('error', t('flash.not_found'));
            redirect('/documents');
        }

        // RBAC: resident can only download their own request, staff/admin can download any
        $isStaff = in_array($role, ['admin', 'staff', 'superadmin'], true);
        if (!$isStaff && (int) $request['user_id'] !== $userId) {
            http_response_code(403);
            exit('Access Denied.');
        }

        // SERVER-SIDE CHECK: For digital delivery with fee, payment must be verified before resident can download
        if (!$isStaff && $request['delivery_method'] === 'digital' && !DocumentRequest::isPaymentVerified($request)) {
            http_response_code(403);
            flash('error', 'Kailangan munang maberipika ang inyong bayad bago ma-download ang dokumento.');
            redirect('/documents');
        }

        $storageService = new DocumentStorageService();
        $file = $storageService->getFile($id);

        if ($file === null) {
            flash('error', 'Wala pang kalakip na soft copy para sa request na ito.');
            redirect($isStaff ? '/admin/documents' : '/documents');
        }

        // Record download in audit log
        DocumentRequest::recordDownload($id, $userId);

        $downloadName = sprintf('%s_%s', (string) $request['reference_no'], $file['file_name']);
        $storageService->streamFile($file, false, $downloadName);
    }

    /** GET /documents/{id}/preview — Resident previewing attached soft copy */
    public function preview(array $params): void
    {
        $id     = (int) ($params['id'] ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $role   = (string) ($_SESSION['role'] ?? $_SESSION['user_role'] ?? '');

        $request = DocumentRequest::find($id);
        if ($request === null) {
            flash('error', t('flash.not_found'));
            redirect('/documents');
        }

        $isStaff = in_array($role, ['admin', 'staff', 'superadmin'], true);
        if (!$isStaff && (int) $request['user_id'] !== $userId) {
            http_response_code(403);
            exit('Access Denied.');
        }

        // SERVER-SIDE CHECK: For digital delivery with fee, payment must be verified before resident can preview
        if (!$isStaff && $request['delivery_method'] === 'digital' && !DocumentRequest::isPaymentVerified($request)) {
            http_response_code(403);
            flash('error', 'Kailangan munang maberipika ang inyong bayad bago ma-preview ang dokumento.');
            redirect('/documents');
        }

        $storageService = new DocumentStorageService();
        $file = $storageService->getFile($id);

        if ($file === null) {
            flash('error', 'Wala pang kalakip na soft copy para sa request na ito.');
            redirect($isStaff ? '/admin/documents' : '/documents');
        }

        $storageService->streamFile($file, true, $file['file_name']);
    }

    // ── Admin ────────────────────────────────────────────────────────────

    /** GET /admin/documents — the queue with delivery, payment, and search filters. */
    public function adminIndex(): void
    {
        $status        = trim($_GET['status'] ?? '');
        $delivery      = trim($_GET['delivery'] ?? '');
        $paymentStatus = trim($_GET['payment_status'] ?? '');
        $search        = trim($_GET['search'] ?? '');

        $requests            = DocumentRequest::queue($status, $delivery, $search, $paymentStatus);
        $pageTitle           = t('admin_documents.title');
        $pendingCount        = (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
        $pendingPaymentCount = (int) db()->query("SELECT COUNT(*) FROM document_payments WHERE payment_status = 'PAYMENT_PROOF_SUBMITTED'")->fetchColumn();

        view('admin/documents/index', compact('requests', 'status', 'delivery', 'paymentStatus', 'search', 'pageTitle', 'pendingCount', 'pendingPaymentCount'));
    }

    /**
     * POST /admin/documents/{id}/status — move a request along.
     */
    public function updateStatus(array $params): void
    {
        check_csrf();

        $id      = (int) ($params['id'] ?? 0);
        $to      = trim($_POST['status'] ?? '');
        $note    = trim($_POST['staff_note'] ?? '');
        $staffId = (int) ($_SESSION['user_id'] ?? 0);

        $request = DocumentRequest::find($id);
        if ($request === null) {
            flash('error', t('flash.not_found'));
            redirect('/admin/documents');
        }

        // CRITICAL SERVER-SIDE CHECK: For digital delivery, payment must be verified before marking ready or released
        if ($request['delivery_method'] === 'digital' && in_array($to, ['ready', 'released'], true) && !DocumentRequest::isPaymentVerified($request)) {
            flash('error', 'Hindi pa beripikado ang bayad ng residente. Kailangan munang beripikahin ang bayad (PAID_VERIFIED) bago maging handa (Ready) ang digital na dokumento.');
            redirect('/admin/documents');
        }

        // CRITICAL SERVER-SIDE CHECK: For pickup delivery, cannot mark released/collected if payment is unpaid or pay at pickup
        if ($to === 'released' && !DocumentRequest::isPaymentVerified($request)) {
            flash('error', 'Hindi pa nababayaran ang dokumentong ito. Kumpirmahin muna ang bayad ng residente (Mark Paid at Pickup) bago markahang nakuha (Collected).');
            redirect('/admin/documents');
        }

        if (!DocumentRequest::setStatus($id, $to, $staffId, $note)) {
            flash('error', t('admin_documents.err_transition', [
                'from' => (string) $request['status'],
                'to'   => $to,
            ]));
            redirect('/admin/documents');
        }

        $this->tellResident($request, $to, $note);

        AuditLog::record(
            $staffId,
            'document_request.status',
            sprintf('%s → %s (%s)', (string) $request['status'], $to, (string) $request['reference_no'])
        );

        $statusLabel = ($request['delivery_method'] === 'digital' && $to === 'ready')
            ? t('documents.status_ready_digital')
            : t('documents.status_' . $to);

        flash('success', t('admin_documents.updated', [
            'reference' => (string) $request['reference_no'],
            'status'    => $statusLabel,
        ]));

        $redirectQuery = $this->buildRedirectQuery();
        redirect('/admin/documents' . ($redirectQuery ? '?' . $redirectQuery : ''));
    }

    /**
     * POST /admin/documents/{id}/upload — Upload completed document soft copy.
     */
    public function uploadFile(array $params): void
    {
        check_csrf();

        $id       = (int) ($params['id'] ?? 0);
        $staffId  = (int) ($_SESSION['user_id'] ?? 0);
        $note     = trim($_POST['staff_note'] ?? '');
        $setReady = !empty($_POST['mark_ready']);

        $request = DocumentRequest::find($id);
        if ($request === null) {
            flash('error', t('flash.not_found'));
            redirect('/admin/documents');
        }

        // CRITICAL SERVER-SIDE PAYMENT CHECK BEFORE DIGITAL RELEASE
        if ($request['delivery_method'] === 'digital' && !DocumentRequest::isPaymentVerified($request)) {
            http_response_code(403);
            flash('error', 'Hindi pa beripikado ang bayad ng residente. Kailangan munang beripikahin ang bayad bago ipadala o i-upload ang opisyal na digital na dokumento.');
            redirect('/admin/documents');
        }

        if (!isset($_FILES['document_file'])) {
            flash('error', 'Walang napiling file para i-upload.');
            redirect('/admin/documents');
        }

        $storageService = new DocumentStorageService();
        $validated = $storageService->validateUpload($_FILES['document_file']);

        if (!$validated['valid']) {
            flash('error', $validated['error'] ?? 'Hindi wastong file.');
            redirect('/admin/documents');
        }

        try {
            $stored = $storageService->store($id, $validated, (string) $_FILES['document_file']['tmp_name']);
            DocumentRequest::attachFile($id, $stored, $staffId, $note);

            AuditLog::record(
                $staffId,
                'document_request.upload',
                sprintf('Uploaded document soft copy for %s (%s)', (string) $request['reference_no'], $stored['clean_name'])
            );

            // If requested or if status is pending/processing, transition to ready
            if ($setReady || in_array($request['status'], ['pending', 'processing'], true)) {
                DocumentRequest::setStatus($id, 'ready', $staffId, $note);
                $this->tellResident($request, 'ready', $note);
            } else {
                // Just notify about file upload
                $userId    = (int) $request['user_id'];
                $reference = (string) $request['reference_no'];
                $label     = DocumentRequest::label((string) $request['document_type']);
                try {
                    (new NotificationService())->notifyUser(
                        $userId,
                        'system',
                        t('documents.notify_title', ['reference' => $reference]),
                        'May bagong kalakip na opisyal na dokumento para sa ' . $label . ' (' . $reference . ').',
                        0,
                        'document_request'
                    );
                } catch (\Throwable $e) {
                    error_log('[DocumentRequestController] notification error: ' . $e->getMessage());
                }
            }

            flash('success', t('admin_documents.upload_success', ['reference' => (string) $request['reference_no']]));
        } catch (\Throwable $e) {
            error_log('[DocumentRequestController] upload error: ' . $e->getMessage());
            flash('error', 'Nagka-problema sa pag-upload: ' . $e->getMessage());
        }

        $redirectQuery = $this->buildRedirectQuery();
        redirect('/admin/documents' . ($redirectQuery ? '?' . $redirectQuery : ''));
    }

    /**
     * POST /admin/documents/{id}/replace — Replace existing soft copy.
     */
    public function replaceFile(array $params): void
    {
        // Re-use uploadFile flow with replace semantics
        $this->uploadFile($params);
    }

    /**
     * POST /admin/documents/{id}/remove-file — Remove soft copy.
     */
    public function removeFile(array $params): void
    {
        check_csrf();

        $id      = (int) ($params['id'] ?? 0);
        $staffId = (int) ($_SESSION['user_id'] ?? 0);
        $reason  = trim($_POST['reason'] ?? '');

        $request = DocumentRequest::find($id);
        if ($request === null) {
            flash('error', t('flash.not_found'));
            redirect('/admin/documents');
        }

        DocumentRequest::removeFile($id, $staffId, $reason);

        AuditLog::record(
            $staffId,
            'document_request.remove_file',
            sprintf('Removed document soft copy from %s', (string) $request['reference_no'])
        );

        flash('success', t('admin_documents.file_removed', ['reference' => (string) $request['reference_no']]));

        $redirectQuery = $this->buildRedirectQuery();
        redirect('/admin/documents' . ($redirectQuery ? '?' . $redirectQuery : ''));
    }

    /** GET /admin/documents/{id}/download — Admin download */
    public function adminDownload(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $request = DocumentRequest::find($id);

        if ($request === null) {
            flash('error', t('flash.not_found'));
            redirect('/admin/documents');
        }

        $storageService = new DocumentStorageService();
        $file = $storageService->getFile($id);

        if ($file === null) {
            flash('error', 'Wala pang kalakip na dokumento.');
            redirect('/admin/documents');
        }

        $downloadName = sprintf('%s_%s', (string) $request['reference_no'], $file['file_name']);
        $storageService->streamFile($file, false, $downloadName);
    }

    /** GET /admin/documents/{id}/preview — Admin preview */
    public function adminPreview(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $request = DocumentRequest::find($id);

        if ($request === null) {
            flash('error', t('flash.not_found'));
            redirect('/admin/documents');
        }

        $storageService = new DocumentStorageService();
        $file = $storageService->getFile($id);

        if ($file === null) {
            flash('error', 'Wala pang kalakip na dokumento.');
            redirect('/admin/documents');
        }

        $storageService->streamFile($file, true, $file['file_name']);
    }

    /** GET /admin/documents/{id}/details — JSON endpoint for modal details and audit logs */
    public function details(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $request = DocumentRequest::find($id);

        if ($request === null) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            exit;
        }

        $logs = DocumentRequest::getActivityLogs($id);

        header('Content-Type: application/json');
        echo json_encode([
            'request' => $request,
            'logs'    => $logs,
        ]);
        exit;
    }

    // ── Internals ────────────────────────────────────────────────────────

    /**
     * Notify resident via in-app notification and SMS tailored to delivery method and status.
     *
     * @param array<string,mixed> $request The request row.
     * @param string $to The new status ('processing', 'ready', 'released', 'rejected').
     * @param string $note Staff note.
     */
    private function tellResident(array $request, string $to, string $note): void
    {
        $userId         = (int) $request['user_id'];
        $reference      = (string) $request['reference_no'];
        $label          = DocumentRequest::label((string) $request['document_type']);
        $deliveryMethod = (string) ($request['delivery_method'] ?? 'pickup');

        // Tailor in-app notification message
        if ($deliveryMethod === 'digital' && $to === 'ready') {
            $body = t('documents.notify_ready_digital', ['type' => $label, 'reference' => $reference]);
        } elseif ($deliveryMethod === 'digital' && $to === 'released') {
            $body = t('documents.notify_released_digital', ['type' => $label, 'reference' => $reference]);
        } else {
            $body = t('documents.notify_' . $to, ['type' => $label, 'reference' => $reference]);
        }

        if ($note !== '') {
            $body .= ' — ' . $note;
        }

        try {
            (new NotificationService())->notifyUser(
                $userId,
                'system',
                t('documents.notify_title', ['reference' => $reference]),
                $body,
                0,
                'document_request'
            );
        } catch (\Throwable $e) {
            error_log('[DocumentRequestController] notify failed for #' . $request['id'] . ': ' . $e->getMessage());
        }

        // Fetch phone number from request or user profile
        $phone = trim((string) ($request['phone'] ?? ''));
        if ($phone === '') {
            try {
                $user = \App\Models\User::find($userId);
                $phone = trim((string) ($user['phone'] ?? ''));
            } catch (\Throwable) {}
        }

        if ($phone === '') {
            return;
        }

        // Tailor concise English SMS message per status
        $smsMessage = null;

        if ($to === 'processing') {
            $smsMessage = sprintf(
                'BARANGGABAY: Your %s request %s is being prepared.',
                $label,
                $reference
            );
        } elseif ($to === 'ready') {
            if ($deliveryMethod === 'digital') {
                $storageService = new DocumentStorageService();
                $hasFile = ($storageService->getFile((int) $request['id']) !== null);
                if ($hasFile) {
                    $smsMessage = sprintf(
                        'BARANGGABAY: Your %s request %s is ready. Log in to BarangGabay to download your digital copy.',
                        $label,
                        $reference
                    );
                }
            } else {
                $smsMessage = sprintf(
                    'BARANGGABAY: Your %s request %s is ready for pickup at the Barangay Office.',
                    $label,
                    $reference
                );
            }
        } elseif ($to === 'rejected') {
            $smsMessage = sprintf(
                'BARANGGABAY: Your document request %s could not be approved. Please log in to BarangGabay or contact the Barangay Office for details.',
                $reference
            );
        }

        if ($smsMessage !== null && $smsMessage !== '') {
            try {
                (new \App\Services\OneWaySmsService())->send(
                    $phone,
                    $smsMessage,
                    'document_request',
                    (int) $request['id']
                );
            } catch (\Throwable $e) {
                error_log('[DocumentRequestController] OneWaySMS failed for #' . $request['id'] . ': ' . $e->getMessage());
            }
        }
    }

    private function buildRedirectQuery(): string
    {
        $params = [];
        if (!empty($_POST['return_status'])) {
            $params['status'] = (string) $_POST['return_status'];
        }
        if (!empty($_POST['return_delivery'])) {
            $params['delivery'] = (string) $_POST['return_delivery'];
        }
        if (!empty($_POST['return_search'])) {
            $params['search'] = (string) $_POST['return_search'];
        }
        return http_build_query($params);
    }

    /**
     * POST /admin/documents/{id}/approve — Requirement 16: One-click Approve & Auto-Generate.
     */
    public function approve(array $params): void
    {
        check_csrf();

        $id      = (int) ($params['id'] ?? 0);
        $staffId = (int) ($_SESSION['user_id'] ?? 0);
        $note    = trim($_POST['staff_note'] ?? '');

        $res = DocumentRequest::approveAndGenerate($id, $staffId, $note);
        if (!$res['success']) {
            flash('error', $res['error'] ?? 'Nabigong aprubahan ang kahilingan.');
        } else {
            flash('success', sprintf('Matagumpay na naaprubahan ang kahilingan at awtomatikong nailikha ang opisyal na sertipiko: %s.', $res['certificate_no']));
        }

        $redirectQuery = $this->buildRedirectQuery();
        redirect('/admin/documents' . ($redirectQuery ? '?' . $redirectQuery : ''));
    }

    /**
     * POST /admin/documents/{id}/claim — Requirement 6: Barangay Hall Pickup Claimed.
     */
    public function claim(array $params): void
    {
        check_csrf();

        $id      = (int) ($params['id'] ?? 0);
        $staffId = (int) ($_SESSION['user_id'] ?? 0);
        $note    = trim($_POST['staff_note'] ?? '');

        if (DocumentRequest::markAsClaimed($id, $staffId, $note)) {
            flash('success', 'Matagumpay na minarkahan bilang nakuha na (Claimed) sa Barangay Hall.');
        } else {
            flash('error', 'Nabigong markahan bilang nakuha.');
        }

        $redirectQuery = $this->buildRedirectQuery();
        redirect('/admin/documents' . ($redirectQuery ? '?' . $redirectQuery : ''));
    }

    /**
     * POST /admin/documents/{id}/delivery-status — Requirement 7: Delivery management.
     */
    public function updateDelivery(array $params): void
    {
        check_csrf();

        $id             = (int) ($params['id'] ?? 0);
        $staffId        = (int) ($_SESSION['user_id'] ?? 0);
        $deliveryStatus = trim($_POST['delivery_status'] ?? '');
        $deliveredBy    = trim($_POST['delivered_by'] ?? '');
        $notes          = trim($_POST['staff_note'] ?? '');

        if (DocumentRequest::updateDelivery($id, $deliveryStatus, $deliveredBy, $staffId, $notes)) {
            flash('success', sprintf('Na-update ang estado ng delivery sa: %s.', $deliveryStatus));
        } else {
            flash('error', 'Nabigong i-update ang delivery.');
        }

        $redirectQuery = $this->buildRedirectQuery();
        redirect('/admin/documents' . ($redirectQuery ? '?' . $redirectQuery : ''));
    }

    /**
     * GET /documents/history — Requirement 18: Resident Document History.
     */
    public function history(): void
    {
        $userId   = (int) ($_SESSION['user_id'] ?? 0);
        $requests = DocumentRequest::forUser($userId);
        view('resident/document_history', compact('requests'));
    }

    /**
     * GET /documents/{ref}/verify — Public QR verification endpoint.
     */
    public function verifyPublic(array $params): void
    {
        $ref = trim((string) ($params['ref'] ?? ''));
        $stmt = db()->prepare(
            'SELECT r.*, u.full_name, u.zone, u.address, su.full_name AS issued_by_name
             FROM document_requests r
             JOIN users u ON u.id = r.user_id
             LEFT JOIN users su ON su.id = r.issued_by
             WHERE r.certificate_no = ? OR r.reference_no = ?
             LIMIT 1'
        );
        $stmt->execute([$ref, $ref]);
        $doc = $stmt->fetch(\PDO::FETCH_ASSOC);

        view('public/verify_certificate', compact('doc', 'ref'));
    }
}
