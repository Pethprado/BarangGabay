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
 * Requests are filed online and picked up at the Barangay Hall. There is no
 * online payment: staff approve a request when the document is ready for
 * pickup, and any fee is paid in cash at the hall. Older Digital Soft Copy
 * requests keep their preview and download.
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

        view('resident/documents', compact('user', 'requests', 'types', 'docFees'));
    }

    /**
     * GET /api/documents/changes — a fingerprint of the requests and payments
     * this user can see. Pages poll it and refresh when it changes, so new
     * requests, payments and status updates appear without reloading by hand.
     * Staff see everything; a resident sees only their own.
     */
    public function changes(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $userId  = (int) ($_SESSION['user_id'] ?? 0);
        $isStaff = in_array($_SESSION['role'] ?? '', ['staff', 'admin', 'superadmin'], true);

        $where  = $isStaff ? '' : ' WHERE r.user_id = ?';
        $params = $isStaff ? [] : [$userId];
        try {
            $stmt = db()->prepare(
                "SELECT COUNT(*) AS n, MAX(r.updated_at) AS r_at, MAX(p.updated_at) AS p_at
                   FROM document_requests r
              LEFT JOIN document_payments p ON p.request_id = r.id" . $where
            );
            $stmt->execute($params);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            $row = [];
        }
        echo json_encode(['sig' => md5(json_encode($row))]);
    }

    /** POST /documents — file a new request. */
    public function store(): void
    {
        check_csrf();

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $user   = \App\Models\User::find($userId);

        $isAjax = (!empty($_POST['is_ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')));
        $isVerifiedResident = ($user && ($user['status'] ?? '') === 'verified');

        if (!$isVerifiedResident) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => __('Kailangan munang maging beripikado ang inyong account bago makahiling ng dokumento.')]);
                return;
            }
            flash('warning', __('Ang inyong resident account ay hindi pa ganap na beripikado. Ang mga beripikadong residente lamang ang maaaring humiling ng opisyal na dokumento ng barangay.'));
            redirect('/documents');
        }

        $type           = trim($_POST['document_type'] ?? '');
        $purposeChoice  = trim($_POST['purpose'] ?? '');
        $purposeOther   = trim($_POST['purpose_other'] ?? '');
        $purpose        = ($purposeChoice === 'Other' && $purposeOther !== '') ? $purposeOther : $purposeChoice;
        $notes          = trim($_POST['notes'] ?? '');
        // Every request is picked up at the Barangay Hall.
        $deliveryMethod = 'pickup';

        // "Other" (typed) requests were removed from the form. Requests filed
        // before that stay valid; new ones are refused like an unknown type.
        if (!\array_key_exists($type, DocumentRequest::TYPES) || $type === 'other') {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => t('documents.err_type')]);
                return;
            }
            flash('error', t('documents.err_type'));
            redirect('/documents');
        }

        if (mb_strlen($purpose) < 3) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => __('Mangyaring ilagay ang layunin (purpose) ng kahilingan.')]);
                return;
            }
            flash('error', t('documents.err_purpose'));
            redirect('/documents');
        }

        // A Business Permit names the business; the rest comes from the profile.
        $businessName = mb_substr(trim((string) ($_POST['business_name'] ?? '')), 0, 200);
        $businessType = mb_substr(trim((string) ($_POST['business_type'] ?? '')), 0, 120);
        $businessAddr = mb_substr(trim((string) ($_POST['business_address'] ?? '')), 0, 255);
        if ($type === 'business' && (mb_strlen($businessName) < 2 || mb_strlen($businessType) < 2)) {
            $bizMsg = __('Enter the business name and the kind of business (e.g. Sari-sari store).');
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $bizMsg]);
                return;
            }
            flash('error', $bizMsg);
            redirect('/documents');
        }

        // Prevent duplicate open request per document type
        foreach (DocumentRequest::forUser($userId) as $existing) {
            if ($existing['document_type'] === $type
                && \in_array($existing['status'], ['awaiting_payment', 'pending', 'pending_review', 'under_review', 'processing',
                                                     'needs_information', 'approved', 'ready', 'ready_for_pickup'], true)) {
                $dupMsg = t('documents.err_duplicate', [
                    'type'      => DocumentRequest::label($type),
                    'reference' => (string) $existing['reference_no'],
                ]);
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => $dupMsg]);
                    return;
                }
                flash('warning', $dupMsg);
                redirect('/documents');
            }
        }

        // Official fee snapshot, from document_fees only — never from the form.
        $feeInfo = \App\Models\DocumentFee::getFeeForType($type);
        if (!$feeInfo['is_active']) {
            $offMsg = DocumentRequest::label($type) . ' is not available for online requests right now.';
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $offMsg]);
                return;
            }
            flash('error', $offMsg);
            redirect('/documents');
        }
        $feeAmount = $feeInfo['is_free'] ? 0.00 : (float) $feeInfo['amount'];

        // No online payment: a fee, if any, is paid in cash at the hall on pickup.
        $isFree        = $feeAmount <= 0.00;
        $paymentMethod = $isFree ? 'free' : 'pickup';

        $created = DocumentRequest::create(
            $userId,
            $type,
            $purpose,
            $notes,
            $deliveryMethod,
            $feeAmount,
            $paymentMethod
        );

        $newId     = (int) $created['id'];
        $reference = (string) $created['reference'];
        if ($type === 'business') {
            DocumentRequest::setBusinessDetails($newId, $businessName, $businessType, $businessAddr);
        }

        // Tell back office there is work waiting
        $deliveryText = ' (Personal Pickup)';
        $feeText = $isFree ? ' [LIBRE]' : sprintf(' [₱%.2f, paid at the hall]', $feeAmount);
        // Every staff member, whatever their "content alerts" choice: this is
        // work waiting, and it opens straight on the request.
        (new NotificationService())->notifyBackOffice(
            'always',
            'system',
            t('documents.notify_staff_title'),
            t('documents.notify_staff_body', [
                'type'      => DocumentRequest::label($type) . $deliveryText . $feeText,
                'reference' => $reference,
            ]),
            $newId,
            'document_request',
            $userId
        );

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success'      => true,
                'is_free'      => $isFree,
                'request_id'   => $newId,
                'reference'    => $reference,
                'redirect_url' => route('documents?tab=requests&ref=' . urlencode($reference)),
            ]);
            return;
        }

        flash('success', t('documents.filed', ['reference' => $reference]));
        redirect('/documents?tab=requests&ref=' . urlencode($reference));
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

        // Locked until the fee is paid and approved, whatever the delivery method,
        // and closed for good once the barangay cancels the document.
        if (!$isStaff && !empty($request['revoked_at'])) {
            flash('error', __('This document was cancelled by the barangay.'));
            redirect('/documents');
        }
        if (!$isStaff && !DocumentRequest::isPaymentVerified($request)) {
            http_response_code(403);
            flash('error', __('Kailangan munang maberipika ang inyong bayad bago ma-download ang dokumento.'));
            redirect('/documents');
        }

        $storageService = new DocumentStorageService();
        $file = $storageService->getFile($id);

        if ($file === null) {
            flash('error', __('Wala pang kalakip na soft copy para sa request na ito.'));
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

        // Locked until the fee is paid and approved, whatever the delivery method,
        // and closed for good once the barangay cancels the document.
        if (!$isStaff && !empty($request['revoked_at'])) {
            flash('error', __('This document was cancelled by the barangay.'));
            redirect('/documents');
        }
        if (!$isStaff && !DocumentRequest::isPaymentVerified($request)) {
            http_response_code(403);
            flash('error', __('Kailangan munang maberipika ang inyong bayad bago ma-preview ang dokumento.'));
            redirect('/documents');
        }

        $storageService = new DocumentStorageService();
        $file = $storageService->getFile($id);

        if ($file === null) {
            flash('error', __('Wala pang kalakip na soft copy para sa request na ito.'));
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

        // Payment gate (the same rule DocumentRequest::setStatus enforces):
        // nothing moves forward until the payment is verified.
        if ($blocked = DocumentRequest::paymentBlocksMove($request, $to)) {
            flash('error', $blocked);
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
            flash('error', __('Hindi pa beripikado ang bayad ng residente. Kailangan munang beripikahin ang bayad bago ipadala o i-upload ang opisyal na digital na dokumento.'));
            redirect('/admin/documents');
        }

        if (!isset($_FILES['document_file'])) {
            flash('error', __('Walang napiling file para i-upload.'));
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
            flash('error', __('Nagka-problema sa pag-upload: :error', ['error' => $e->getMessage()]));
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
            flash('error', __('Wala pang kalakip na dokumento.'));
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
            flash('error', __('Wala pang kalakip na dokumento.'));
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
            echo json_encode(['error' => __('Not found')]);
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
                (int) $request['id'],
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

        $orNumber = mb_substr(trim((string) ($_POST['or_number'] ?? '')), 0, 40);
        $res = DocumentRequest::approveAndGenerate($id, $staffId, $note, $orNumber !== '' ? $orNumber : null);
        if (!$res['success']) {
            flash('error', $res['error'] ?? 'Nabigong aprubahan ang kahilingan.');
        } else {
            $request = DocumentRequest::find($id);
            $texted  = $request !== null && $this->textReadyForPickup($request);
            AuditLog::record($staffId, 'documents.ready', 'Ready for pickup: ' . ($request['reference_no'] ?? ('#' . $id)));
            flash('success', sprintf(
                __('Ready for pickup: %s. The resident was notified in the app%s and can now check the details.'),
                $res['certificate_no'],
                $texted ? ' ' . __('and by SMS') : ''
            ));
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

        $request = DocumentRequest::find($id);
        $blocked = $request ? DocumentRequest::paymentBlocksMove($request, 'released') : null;
        if ($blocked !== null) {
            flash('error', $blocked . ' Record the payment first.');
        } elseif (DocumentRequest::markAsClaimed($id, $staffId, $note)) {
            flash('success', __('Matagumpay na minarkahan bilang nakuha na (Claimed) sa Barangay Hall.'));
        } else {
            flash('error', __('Nabigong markahan bilang nakuha.'));
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
            flash('error', __('Nabigong i-update ang delivery.'));
        }

        $redirectQuery = $this->buildRedirectQuery();
        redirect('/admin/documents' . ($redirectQuery ? '?' . $redirectQuery : ''));
    }

    /**
     * GET /documents/history — Requirement 18: Resident Document History.
     */
    /**
     * GET /documents/{id}/proof — the document as it will be printed, for the
     * resident to check their details.
     *
     * Deliberately NOT the real PDF: this page is rendered from the same
     * template and the same profile fields, but carries no signature, seal,
     * QR or O.R. number, and is covered by a watermark with the viewer's name
     * and the time. Printing, saving and copying are blocked in the page, and
     * nothing is cached. No web page can stop a camera or every screenshot
     * tool, so the watermark is what makes a copy useless as a document.
     */
    public function proof(array $params): void
    {
        $id      = (int) ($params['id'] ?? 0);
        $userId  = (int) ($_SESSION['user_id'] ?? 0);
        $isStaff = in_array((string) ($_SESSION['role'] ?? ''), ['admin', 'staff', 'superadmin'], true);
        $request = DocumentRequest::find($id);

        if ($request === null || (!$isStaff && (int) $request['user_id'] !== $userId)) {
            http_response_code(404);
            flash('error', t('flash.not_found'));
            redirect('/documents?tab=requests');
        }
        if (in_array((string) $request['status'], ['rejected', 'cancelled'], true) || !empty($request['revoked_at'])) {
            flash('error', __('This request is closed, so there is no document to check.'));
            redirect('/documents?tab=requests');
        }

        if ((string) $request['document_type'] === 'other') {
            flash('warning', __('A typed request has no preview. Use Messages to talk with barangay staff about it.'));
            redirect($isStaff ? '/admin/documents?open=' . $id : '/documents?tab=requests&ref=' . urlencode((string) $request['reference_no']));
        }

        $resident = \App\Models\User::find((int) $request['user_id']);
        if ($resident === null) {
            http_response_code(404);
            exit;
        }

        $type     = (string) $request['document_type'];
        $template = \App\Models\DocumentTemplate::get(in_array($type, \App\Models\DocumentTemplate::TYPES, true) ? $type : 'clearance');
        $issued   = !empty($request['issued_at']);
        $values   = \App\Services\DocumentFields::for($request, $resident, $issued ? null : date('Y-m-d H:i:s'));

        // **bold** and ^^superscript^^ are the template's own markup.
        $render = static function (string $text) use ($values): string {
            $html = htmlspecialchars(\App\Services\DocumentFields::fill($text, $values), ENT_QUOTES, 'UTF-8');
            $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html) ?? $html;
            return preg_replace('/\^\^(.+?)\^\^/s', '<sup>$1</sup>', $html) ?? $html;
        };

        if (!$isStaff) {
            DocumentRequest::logActivity($id, $userId, 'proof_viewed', (string) $request['status'], (string) $request['status'],
                'Resident opened the proof copy to check the details.');
        }

        // Never stored by the browser or a proxy, never framed, never indexed.
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        header('Pragma: no-cache');
        header('X-Frame-Options: DENY');
        header("Content-Security-Policy: frame-ancestors 'none'");
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow');

        $viewer = $isStaff ? (string) ($_SESSION['full_name'] ?? 'Staff') : (string) ($resident['full_name'] ?? '');

        view('resident/document_proof', [
            'request'    => $request,
            'resident'   => $resident,
            'values'     => $values,
            'office'     => $render((string) $template['office']),
            'title'      => $render((string) $template['title']),
            'paragraphs' => array_map($render, (array) $template['paragraphs']),
            'header'     => [
                'REPUBLIC OF THE PHILIPPINES',
                mb_strtoupper(\App\Models\DocumentTemplate::setting('doc_province')),
                mb_strtoupper(\App\Models\DocumentTemplate::setting('doc_municipality')),
                mb_strtoupper(\App\Models\DocumentTemplate::setting('doc_barangay_name')),
            ],
            'captain'    => \App\Models\DocumentTemplate::setting('doc_punong_barangay'),
            'secretary'  => \App\Models\DocumentTemplate::setting('doc_barangay_secretary'),
            'watermark'  => mb_strtoupper(trim($viewer)) . ' · ' . date('M j, Y g:i A') . ' · ' . $request['reference_no'],
            'isStaff'    => $isStaff,
            'pageTitle'  => __('Check your document') . ' — ' . $request['reference_no'],
        ]);
    }

    /**
     * POST /documents/{id}/check — the resident confirms the details, or
     * reports what is wrong. A report reaches every staff member.
     */
    public function residentCheck(array $params): void
    {
        check_csrf();

        $id      = (int) ($params['id'] ?? 0);
        $userId  = (int) ($_SESSION['user_id'] ?? 0);
        $request = DocumentRequest::find($id);
        if ($request === null || (int) $request['user_id'] !== $userId) {
            http_response_code(404);
            redirect('/documents?tab=requests');
        }

        $result = (string) ($_POST['result'] ?? '');
        $note   = trim((string) ($_POST['note'] ?? ''));
        if ($result === 'issue' && mb_strlen($note) < 5) {
            flash('error', __('Please write what is wrong, for example the correct spelling of your name.'));
            redirect('/documents/' . $id . '/proof');
        }
        if (!DocumentRequest::setResidentCheck($id, $result, $result === 'issue' ? $note : '')) {
            flash('error', __('Sorry, your answer could not be saved. Please try again.'));
            redirect('/documents/' . $id . '/proof');
        }

        $label = DocumentRequest::label((string) $request['document_type']);
        if ($result === 'issue') {
            DocumentRequest::logActivity($id, $userId, 'resident_reported_issue', (string) $request['status'], (string) $request['status'],
                'Resident reported a mistake: ' . mb_substr($note, 0, 500));
            (new NotificationService())->notifyBackOffice(
                'always',
                'system',
                'May mali raw sa dokumento — ' . $request['reference_no'],
                $request['reference_no'] . ' (' . $label . '): ' . mb_substr($note, 0, 300),
                $id,
                'document_request'
            );
            flash('success', __('Thank you. Barangay staff were told what to correct; you will be notified when the corrected document is ready.'));
        } else {
            DocumentRequest::logActivity($id, $userId, 'resident_confirmed', (string) $request['status'], (string) $request['status'],
                'Resident confirmed the details are correct.');
            flash('success', __('Thank you for checking. Your details are confirmed.'));
        }

        redirect('/documents?tab=requests&ref=' . urlencode((string) $request['reference_no']));
    }

    /**
     * Text the resident that their document is ready for pickup, through the
     * barangay's configured SMS provider. Best effort: true only if sent.
     */
    private function textReadyForPickup(array $request): bool
    {
        try {
            $resident = \App\Models\User::find((int) $request['user_id']);
            $phone    = trim((string) ($resident['phone'] ?? ''));
            $sms      = \App\Services\SmsGateway::make();
            if ($phone === '' || !$sms->isConfigured() || $sms->cleanPhone($phone) === null) {
                return false;
            }
            $fee = (float) ($request['fee_amount'] ?? 0);
            $message = sprintf(
                'BarangGabay: Your %s (%s) is READY for pickup at the Barangay Hall. Bring a valid ID%s. You can check the details in My Requests.',
                DocumentRequest::label((string) $request['document_type']),
                (string) $request['reference_no'],
                $fee > 0 ? sprintf(' and the fee of P%.2f', $fee) : ''
            );
            $result = $sms->send($phone, $message, 'document_request', (int) $request['id']);
            return !empty($result['success']);
        } catch (\Throwable $e) {
            error_log('[DocumentRequestController] ready SMS failed for #' . ($request['id'] ?? '?') . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * GET /documents/{id}/messages — the thread between the resident and
     * staff on one request (the owner or staff only), oldest first.
     */
    public function messages(array $params): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        [$request, $isStaff] = $this->threadAccess((int) ($params['id'] ?? 0));
        if ($request === null) {
            http_response_code(404);
            echo json_encode(['ok' => false]);
            return;
        }

        $me  = (int) ($_SESSION['user_id'] ?? 0);
        $out = [];
        foreach (DocumentRequest::messages((int) $request['id']) as $m) {
            $out[] = [
                'id'       => (int) $m['id'],
                'role'     => (string) $m['sender_role'],
                'name'     => $m['sender_role'] === 'staff' ? __('Barangay staff') : (string) ($m['full_name'] ?? ''),
                'message'  => (string) $m['message'],
                'time'     => format_datetime((string) $m['created_at']),
                'time_iso' => iso_time((string) $m['created_at']),
                'mine'     => ($m['sender_role'] === 'staff') === $isStaff,
            ];
        }
        echo json_encode(['ok' => true, 'messages' => $out, 'me' => $me]);
    }

    /**
     * POST /documents/{id}/messages — send a message. A resident's message
     * reaches every staff member; a staff reply reaches the resident.
     */
    public function sendMessage(array $params): void
    {
        header('Content-Type: application/json; charset=utf-8');
        check_csrf();
        [$request, $isStaff] = $this->threadAccess((int) ($params['id'] ?? 0));
        if ($request === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => t('flash.not_found')]);
            return;
        }

        $text = trim((string) ($_POST['message'] ?? ''));
        if (mb_strlen($text) < 1 || mb_strlen($text) > 1000) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => __('Write a message of up to 1,000 characters.')]);
            return;
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $id     = (int) $request['id'];
        $ref    = (string) $request['reference_no'];
        try {
            DocumentRequest::addMessage($id, $userId, $isStaff ? 'staff' : 'resident', $text);
        } catch (\Throwable $e) {
            error_log('[DocumentRequestController::sendMessage] #' . $id . ': ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => __('Sorry, your message could not be sent. Please try again.')]);
            return;
        }

        $preview = mb_strlen($text) > 140 ? mb_substr($text, 0, 137) . '…' : $text;
        if ($isStaff) {
            (new NotificationService())->notifyUser(
                (int) $request['user_id'], 'system',
                'Sagot ng Barangay — ' . $ref,
                DocumentRequest::displayLabel($request) . ': ' . $preview,
                $id, 'document_message'
            );
            AuditLog::record($userId, 'documents.message', 'Replied on ' . $ref);
        } else {
            (new NotificationService())->notifyBackOffice(
                'always', 'system',
                'Mensahe mula sa residente — ' . $ref,
                ($request['full_name'] ?? '') . ' (' . DocumentRequest::displayLabel($request) . '): ' . $preview,
                $id, 'document_message', $userId
            );
        }

        echo json_encode(['ok' => true]);
    }

    /**
     * The request, if this user may read and write its thread.
     *
     * @return array{0: ?array, 1: bool} [request|null, isStaff]
     */
    private function threadAccess(int $id): array
    {
        $isStaff = in_array((string) ($_SESSION['role'] ?? ''), ['admin', 'staff', 'superadmin'], true);
        $request = DocumentRequest::find($id);
        if ($request === null || (!$isStaff && (int) $request['user_id'] !== (int) ($_SESSION['user_id'] ?? 0))) {
            return [null, $isStaff];
        }
        return [$request, $isStaff];
    }

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
            // Only issued documents (they have a control number) can be verified,
            // and only the fields the page shows are read.
            'SELECT r.document_type, r.certificate_no, r.issued_at, r.revoked_at, r.business_name, r.business_type,
                    r.permit_no, r.valid_until, u.full_name
             FROM document_requests r
             JOIN users u ON u.id = r.user_id
             WHERE r.certificate_no = ? AND r.issued_at IS NOT NULL
             LIMIT 1'
        );
        $stmt->execute([$ref]);
        $doc = $stmt->fetch(\PDO::FETCH_ASSOC);

        view('public/verify_certificate', compact('doc', 'ref'));
    }
}
