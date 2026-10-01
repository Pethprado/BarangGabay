<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\DocumentFee;
use App\Models\DocumentPayment;
use App\Models\DocumentRequest;
use App\Models\GcashAccount;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\OneWaySmsService;
use App\Services\PayPalService;

class PaymentController
{
    /**
     * GET /admin/payments — Payment Dashboard & Transactions
     */
    public function adminIndex(): void
    {
        $status   = trim($_GET['status'] ?? '');
        $method   = trim($_GET['method'] ?? '');
        $docType  = trim($_GET['doc_type'] ?? '');
        $search   = trim($_GET['search'] ?? '');

        $stats        = DocumentPayment::getDashboardStats();
        $transactions = DocumentPayment::getTransactions([
            'status'   => $status,
            'method'   => $method,
            'doc_type' => $docType,
            'search'   => $search,
        ]);

        $gcashAccounts = GcashAccount::getActive();
        $docFees       = DocumentFee::getAll();
        $pageTitle     = 'Pamamahala ng Pagbabayad (Payment Management)';

        view('admin/payments/index', compact(
            'stats',
            'transactions',
            'gcashAccounts',
            'docFees',
            'status',
            'method',
            'docType',
            'search',
            'pageTitle'
        ));
    }

    /**
     * POST /admin/payments/{id}/verify — Admin/Staff marks payment as verified
     */
    public function verify(array $params): void
    {
        check_csrf();

        $id      = (int) ($params['id'] ?? 0);
        $staffId = (int) ($_SESSION['user_id'] ?? 0);
        $note    = trim($_POST['note'] ?? '');

        $payment = DocumentPayment::find($id);
        if (!$payment) {
            flash('error', t('flash.not_found'));
            redirect('/admin/payments');
        }

        if ($payment['payment_status'] === DocumentPayment::STATUS_PAID_VERIFIED) {
            flash('warning', 'Ang bayad na ito ay naproseso at na-verify na.');
            redirect('/admin/payments');
        }

        if (DocumentPayment::verifyPayment($id, $staffId, $note)) {
            AuditLog::record(
                $staffId,
                'payment.verify',
                sprintf('Verified payment %s for request %s (₱%.2f)', (string) $payment['payment_ref'], (string) $payment['request_ref'], (float) $payment['amount_due'])
            );

            // In-app resident notification
            $userId    = (int) $payment['user_id'];
            $reqRef    = (string) $payment['request_ref'];
            $docLabel  = DocumentRequest::label((string) $payment['document_type']);
            try {
                (new NotificationService())->notifyUser(
                    $userId,
                    'system',
                    'Naberipika na ang Inyong Bayad (' . $reqRef . ')',
                    sprintf('Naberipika na ng barangay ang inyong bayad para sa %s (%s). Kasalukuyan nang inihahanda ang inyong dokumento.', $docLabel, $reqRef),
                    0,
                    'document_payment'
                );
            } catch (\Throwable $e) {
                error_log('[PaymentController::verify] notification error: ' . $e->getMessage());
            }

            // SMS Notification if phone exists
            $phone = trim((string) ($payment['phone'] ?? ''));
            if ($phone !== '') {
                try {
                    $smsMsg = sprintf('BARANGGABAY: Payment for request %s has been verified. Your document is now being processed.', $reqRef);
                    (new OneWaySmsService())->send($phone, $smsMsg, 'payment_verified', $id);
                } catch (\Throwable $e) {
                    error_log('[PaymentController::verify] SMS error: ' . $e->getMessage());
                }
            }

            flash('success', sprintf('Matagumpay na na-verify ang bayad para sa %s (%s).', $reqRef, (string) $payment['payment_ref']));
        } else {
            flash('error', 'Nagka-problema sa pag-verify ng bayad.');
        }

        $returnUrl = !empty($_POST['return_to']) ? (string) $_POST['return_to'] : '/admin/payments';
        redirect($returnUrl);
    }

    /**
     * POST /admin/payments/{id}/reject — Admin/Staff rejects receipt
     */
    public function reject(array $params): void
    {
        check_csrf();

        $id      = (int) ($params['id'] ?? 0);
        $staffId = (int) ($_SESSION['user_id'] ?? 0);
        $reason  = trim($_POST['rejection_reason'] ?? '');
        $note    = trim($_POST['rejection_note'] ?? '');

        if ($reason === '') {
            flash('error', 'Mangyaring pumili ng dahilan ng pagtanggi sa resibo.');
            redirect('/admin/payments');
        }

        $payment = DocumentPayment::find($id);
        if (!$payment) {
            flash('error', t('flash.not_found'));
            redirect('/admin/payments');
        }

        if (DocumentPayment::rejectPayment($id, $staffId, $reason, $note)) {
            AuditLog::record(
                $staffId,
                'payment.reject',
                sprintf('Rejected payment %s for request %s. Reason: %s', (string) $payment['payment_ref'], (string) $payment['request_ref'], $reason)
            );

            // In-app resident notification
            $userId   = (int) $payment['user_id'];
            $reqRef   = (string) $payment['request_ref'];
            $docLabel = DocumentRequest::label((string) $payment['document_type']);
            try {
                $reasonDesc = $note !== '' ? sprintf('%s (%s)', $reason, $note) : $reason;
                (new NotificationService())->notifyUser(
                    $userId,
                    'system',
                    'Kailangang Suriin ang Patunay ng Bayad (' . $reqRef . ')',
                    sprintf('Hindi naberipika ang inyong patunay ng bayad para sa %s (%s). Dahilan: %s. Mangyaring mag-upload muli ng wastong resibo.', $docLabel, $reqRef, $reasonDesc),
                    0,
                    'document_payment'
                );
            } catch (\Throwable $e) {
                error_log('[PaymentController::reject] notification error: ' . $e->getMessage());
            }

            // SMS Notification if phone exists
            $phone = trim((string) ($payment['phone'] ?? ''));
            if ($phone !== '') {
                try {
                    $smsMsg = sprintf('BARANGGABAY: Payment proof for request %s needs review. Please log in to BarangGabay to check the reason and submit a new receipt.', $reqRef);
                    (new OneWaySmsService())->send($phone, $smsMsg, 'payment_rejected', $id);
                } catch (\Throwable $e) {
                    error_log('[PaymentController::reject] SMS error: ' . $e->getMessage());
                }
            }

            flash('warning', sprintf('Tinanggihan ang patunay ng bayad para sa %s. Naabisuhan na ang residente.', $reqRef));
        } else {
            flash('error', 'Nagka-problema sa pagtanggi sa bayad.');
        }

        $returnUrl = !empty($_POST['return_to']) ? (string) $_POST['return_to'] : '/admin/payments';
        redirect($returnUrl);
    }

    /**
     * POST /admin/payments/{id}/mark-pickup — Staff marks cash received at pickup
     */
    public function markPaidAtPickup(array $params): void
    {
        check_csrf();

        $id      = (int) ($params['id'] ?? 0);
        $staffId = (int) ($_SESSION['user_id'] ?? 0);
        $note    = trim($_POST['note'] ?? '');

        $payment = DocumentPayment::find($id);
        if (!$payment) {
            flash('error', t('flash.not_found'));
            redirect('/admin/payments');
        }

        if (DocumentPayment::markPaidAtPickup($id, $staffId, $note)) {
            AuditLog::record(
                $staffId,
                'payment.paid_at_pickup',
                sprintf('Collected cash payment %s for request %s (₱%.2f)', (string) $payment['payment_ref'], (string) $payment['request_ref'], (float) $payment['amount_due'])
            );

            flash('success', sprintf('Naitampok na bayad na sa counter ang ₱%.2f para sa request %s.', (float) $payment['amount_due'], (string) $payment['request_ref']));
        } else {
            flash('error', 'Hindi na-update ang estado ng bayad.');
        }

        $returnUrl = !empty($_POST['return_to']) ? (string) $_POST['return_to'] : '/admin/payments';
        redirect($returnUrl);
    }

    /**
     * POST /admin/documents/{id}/pickup-payment — Staff marks cash received from Document Requests table
     */
    public function markRequestPaidAtPickup(array $params): void
    {
        check_csrf();

        $requestId = (int) ($params['id'] ?? 0);
        $staffId   = (int) ($_SESSION['user_id'] ?? 0);
        $note      = trim($_POST['note'] ?? 'Cash payment received at barangay counter.');

        $req = DocumentRequest::find($requestId);
        if (!$req) {
            flash('error', t('flash.not_found'));
            redirect('/admin/documents');
        }

        // Find or create the payment record for this request
        $payment = DocumentPayment::findByRequest($requestId);
        if (!$payment) {
            $paymentId = DocumentPayment::createForRequest(
                $requestId,
                (int) $req['user_id'],
                (string) $req['document_type'],
                (float) ($req['fee_amount'] ?? 0),
                'pickup'
            );
            $payment = DocumentPayment::find($paymentId);
        }

        if ($payment && DocumentPayment::markPaidAtPickup((int)$payment['id'], $staffId, $note)) {
            AuditLog::record(
                $staffId,
                'payment.paid_at_pickup',
                sprintf('Collected cash payment for request %s (₱%.2f)', (string) $req['reference_no'], (float) ($req['fee_amount'] ?? 0))
            );
            flash('success', sprintf('Naitampok na bayad na sa counter ang ₱%.2f para sa request %s.', (float) ($req['fee_amount'] ?? 0), (string) $req['reference_no']));
        } else {
            flash('error', 'Hindi na-update ang estado ng bayad.');
        }

        redirect('/admin/documents');
    }

    /**
     * POST /admin/payments/{id}/waive — Admin waives fee
     */
    public function waive(array $params): void
    {
        check_csrf();

        $id      = (int) ($params['id'] ?? 0);
        $staffId = (int) ($_SESSION['user_id'] ?? 0);
        $reason  = trim($_POST['waiver_reason'] ?? '');

        if ($reason === '') {
            flash('error', 'Kailangan ng dahilan para sa pagpapalampas / libreng dokumento.');
            redirect('/admin/payments');
        }

        $payment = DocumentPayment::find($id);
        if (!$payment) {
            flash('error', t('flash.not_found'));
            redirect('/admin/payments');
        }

        if (DocumentPayment::waivePayment($id, $staffId, $reason)) {
            AuditLog::record(
                $staffId,
                'payment.waive',
                sprintf('Waived payment %s for request %s. Reason: %s', (string) $payment['payment_ref'], (string) $payment['request_ref'], $reason)
            );

            flash('success', sprintf('Naitalang libre / waived ang bayad para sa %s.', (string) $payment['request_ref']));
        } else {
            flash('error', 'Hindi nai-waive ang bayad.');
        }

        redirect('/admin/payments');
    }

    /**
     * POST /admin/payments/{id}/refund — Admin records refund
     */
    public function refund(array $params): void
    {
        check_csrf();

        $id      = (int) ($params['id'] ?? 0);
        $staffId = (int) ($_SESSION['user_id'] ?? 0);
        $amount  = (float) ($_POST['refund_amount'] ?? 0);
        $reason  = trim($_POST['refund_reason'] ?? '');

        if ($amount <= 0 || $reason === '') {
            flash('error', 'Ilagay ang wastong halaga at dahilan ng refund.');
            redirect('/admin/payments');
        }

        $payment = DocumentPayment::find($id);
        if (!$payment) {
            flash('error', t('flash.not_found'));
            redirect('/admin/payments');
        }

        if (DocumentPayment::recordRefund($id, $staffId, $amount, $reason)) {
            AuditLog::record(
                $staffId,
                'payment.refund',
                sprintf('Recorded refund of ₱%.2f for %s (%s)', $amount, (string) $payment['payment_ref'], (string) $payment['request_ref'])
            );

            flash('success', sprintf('Naitala ang refund na ₱%.2f para sa %s.', $amount, (string) $payment['request_ref']));
        } else {
            flash('error', 'Hindi naitala ang refund.');
        }

        redirect('/admin/payments');
    }

    /**
     * GET /admin/payments/{id}/receipt — Secure preview of receipt image or PDF
     */
    public function receiptPreview(array $params): void
    {
        $id     = (int) ($params['id'] ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $role   = (string) ($_SESSION['role'] ?? $_SESSION['user_role'] ?? '');

        $payment = DocumentPayment::find($id);
        if (!$payment || empty($payment['receipt_file_data'])) {
            http_response_code(404);
            exit('Walang natagpuang resibo.');
        }

        // RBAC: Resident can view own receipt, staff/admin can view any
        $isStaff = in_array($role, ['admin', 'staff', 'superadmin'], true);
        if (!$isStaff && (int) $payment['user_id'] !== $userId) {
            http_response_code(403);
            exit('Access Denied.');
        }

        $mime = (string) ($payment['receipt_mime'] ?? 'image/jpeg');
        $raw  = base64_decode((string) $payment['receipt_file_data']);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($raw));
        header('Cache-Control: private, max-age=3600');
        header('Content-Disposition: inline; filename="' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', (string) $payment['receipt_file_name']) . '"');
        echo $raw;
        exit;
    }

    /**
     * GET /admin/payments/fees — Document Fees Settings
     */
    public function feesIndex(): void
    {
        $fees      = DocumentFee::getAll();
        $pageTitle = 'Presyo ng mga Dokumento (Document Fees)';
        view('admin/payments/fees', compact('fees', 'pageTitle'));
    }

    /**
     * POST /admin/payments/fees — Update Document Fees
     */
    public function feesUpdate(): void
    {
        check_csrf();
        $staffId = (int) ($_SESSION['user_id'] ?? 0);
        $feesPost = $_POST['fees'] ?? [];

        if (is_array($feesPost)) {
            foreach ($feesPost as $type => $data) {
                if (array_key_exists($type, DocumentRequest::TYPES)) {
                    $amount = (float) ($data['amount'] ?? 0);
                    $isFree = !empty($data['is_free']);
                    DocumentFee::setFee($type, $amount, $isFree, $staffId);
                }
            }
            AuditLog::record($staffId, 'payment.fees_update', 'Updated document fee settings.');
            flash('success', 'Matagumpay na na-update ang presyo ng mga dokumento.');
        }

        redirect('/admin/payments/fees');
    }

    /**
     * GET /admin/payments/gcash — GCash Accounts Management
     */
    public function gcashIndex(): void
    {
        $accounts  = GcashAccount::getAll();
        $pageTitle = 'Pamamahala ng GCash Accounts';
        view('admin/payments/gcash', compact('accounts', 'pageTitle'));
    }

    /**
     * POST /admin/payments/gcash — Add GCash Account
     */
    public function gcashStore(): void
    {
        check_csrf();
        $staffId = (int) ($_SESSION['user_id'] ?? 0);

        $name   = trim($_POST['account_name'] ?? '');
        $number = trim($_POST['mobile_number'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $isDef  = !empty($_POST['is_default']);

        if ($name === '' || $number === '') {
            flash('error', 'Kailangan ang Pangalan ng Account at GCash Mobile Number.');
            redirect('/admin/payments/gcash');
        }

        $qrData = null;
        $qrMime = null;

        if (isset($_FILES['qr_image']) && $_FILES['qr_image']['error'] === UPLOAD_ERR_OK) {
            $tmp = (string) $_FILES['qr_image']['tmp_name'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $tmp) : '';
            if ($finfo) finfo_close($finfo);

            if (in_array($mime, ['image/png', 'image/jpeg', 'image/jpg'], true)) {
                $bytes = file_get_contents($tmp);
                if ($bytes !== false) {
                    $qrData = base64_encode($bytes);
                    $qrMime = $mime;
                }
            } else {
                flash('warning', 'Ang QR code image ay dapat JPG, JPEG, o PNG lamang.');
            }
        }

        $accId = GcashAccount::create([
            'account_name'  => $name,
            'mobile_number' => $number,
            'description'   => $desc,
            'is_default'    => $isDef,
            'is_active'     => true,
            'qr_image_data' => $qrData,
            'qr_mime_type'  => $qrMime,
        ], $staffId);

        AuditLog::record($staffId, 'payment.gcash_create', sprintf('Added GCash account "%s" (%s)', $name, $number));
        flash('success', sprintf('Matagumpay na naidagdag ang GCash account "%s".', $name));
        redirect('/admin/payments/gcash');
    }

    /**
     * POST /admin/payments/gcash/{id} — Update GCash Account
     */
    public function gcashUpdate(array $params): void
    {
        check_csrf();
        $id      = (int) ($params['id'] ?? 0);
        $staffId = (int) ($_SESSION['user_id'] ?? 0);

        $name   = trim($_POST['account_name'] ?? '');
        $number = trim($_POST['mobile_number'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $isDef  = !empty($_POST['is_default']);
        $isAct  = !empty($_POST['is_active']);

        $data = [
            'account_name'  => $name,
            'mobile_number' => $number,
            'description'   => $desc,
            'is_default'    => $isDef,
            'is_active'     => $isAct,
        ];

        if (isset($_FILES['qr_image']) && $_FILES['qr_image']['error'] === UPLOAD_ERR_OK) {
            $tmp = (string) $_FILES['qr_image']['tmp_name'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $tmp) : '';
            if ($finfo) finfo_close($finfo);

            if (in_array($mime, ['image/png', 'image/jpeg', 'image/jpg'], true)) {
                $bytes = file_get_contents($tmp);
                if ($bytes !== false) {
                    $data['qr_image_data'] = base64_encode($bytes);
                    $data['qr_mime_type']  = $mime;
                }
            }
        }

        GcashAccount::update($id, $data, $staffId);
        AuditLog::record($staffId, 'payment.gcash_update', sprintf('Updated GCash account #%d (%s)', $id, $name));
        flash('success', 'Matagumpay na na-update ang GCash account.');
        redirect('/admin/payments/gcash');
    }

    /**
     * POST /admin/payments/gcash/{id}/default — Set as default account
     */
    public function gcashSetDefault(array $params): void
    {
        check_csrf();
        $id      = (int) ($params['id'] ?? 0);
        $staffId = (int) ($_SESSION['user_id'] ?? 0);

        if (GcashAccount::setDefault($id, $staffId)) {
            AuditLog::record($staffId, 'payment.gcash_default', sprintf('Set GCash account #%d as default', $id));
            flash('success', 'Naitakda na bilang pangunahing (default) GCash account.');
        } else {
            flash('error', 'Hindi naitakda bilang default.');
        }

        redirect('/admin/payments/gcash');
    }

    /**
     * POST /admin/payments/gcash/{id}/toggle — Toggle active state
     */
    public function gcashToggle(array $params): void
    {
        check_csrf();
        $id      = (int) ($params['id'] ?? 0);
        $staffId = (int) ($_SESSION['user_id'] ?? 0);

        if (GcashAccount::toggleActive($id, $staffId)) {
            flash('success', 'Na-update ang estado ng GCash account.');
        } else {
            flash('error', 'Hindi mabago ang estado ng pangunahing (default) account.');
        }

        redirect('/admin/payments/gcash');
    }

    /**
     * GET /admin/payments/export — Export transactions to CSV
     */
    public function exportCsv(): void
    {
        $transactions = DocumentPayment::getTransactions();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="BarangGabay_Payments_' . date('Y-m-d_His') . '.csv"');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Excel
        fputs($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'Payment Ref',
            'Request Ref',
            'Resident Name',
            'Phone',
            'Document Type',
            'Delivery Method',
            'Amount Due (PHP)',
            'Amount Reported (PHP)',
            'Payment Method',
            'GCash Account',
            'GCash Reference No',
            'Payment Status',
            'Document Status',
            'Date Created',
            'Date Verified',
            'Verified By',
        ]);

        foreach ($transactions as $t) {
            fputcsv($out, [
                $t['payment_ref'],
                $t['request_ref'],
                $t['full_name'],
                $t['phone'] ?? '',
                DocumentRequest::label((string) $t['document_type']),
                DocumentRequest::deliveryLabel((string) $t['delivery_method']),
                number_format((float) $t['amount_due'], 2, '.', ''),
                $t['amount_reported'] !== null ? number_format((float) $t['amount_reported'], 2, '.', '') : '',
                strtoupper((string) $t['payment_method']),
                $t['gcash_account_name'] ?? 'Official Barangay',
                $t['gcash_reference_no'] ?? '',
                DocumentPayment::statusLabel((string) $t['payment_status']),
                (string) $t['document_status'],
                $t['created_at'],
                $t['verified_at'] ?? '',
                $t['verified_by_name'] ?? '',
            ]);
        }

        fclose($out);
        exit;
    }

    // ── Resident Payment Actions ─────────────────────────────────────────

    /**
     * GET /documents/{id}/payment — Dedicated GCash Payment Checkout Page
     */
    public function checkout(array $params): void
    {
        $id     = (int) ($params['id'] ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $role   = (string) ($_SESSION['role'] ?? $_SESSION['user_role'] ?? '');

        $request = DocumentRequest::find($id);
        if (!$request) {
            flash('error', t('flash.not_found'));
            redirect('/documents');
        }

        $isStaff = in_array($role, ['admin', 'staff', 'superadmin'], true);
        if (!$isStaff && (int) $request['user_id'] !== $userId) {
            http_response_code(403);
            exit('Access Denied.');
        }

        // If free document, no checkout needed
        $fee = (float) ($request['fee_amount'] ?? 0);
        if ($fee <= 0.00 || ($request['payment_status'] ?? '') === 'FREE') {
            flash('info', 'Ang dokumentong ito ay libre. Hindi kailangan ng pagbabayad.');
            redirect('/documents');
        }

        // If pay upon pickup, no online checkout needed
        if (($request['payment_method'] ?? '') === 'pickup' && ($request['delivery_method'] ?? '') === 'pickup') {
            flash('info', 'Ang inyong kahilingan ay nakatakda para sa Pay Upon Pickup sa Barangay Hall.');
            redirect('/documents');
        }

        // Find or create payment record
        $payment = DocumentPayment::findByRequest($id);
        $method = (string) ($request['payment_method'] ?? 'paypal');
        if (!$payment) {
            $defaultGcash = GcashAccount::getDefault();
            $gcashAccountId = $defaultGcash ? (int) $defaultGcash['id'] : null;
            $paymentId = DocumentPayment::createForRequest(
                $id,
                (int) $request['user_id'],
                (string) $request['document_type'],
                $fee,
                $method,
                $gcashAccountId
            );
            $payment = DocumentPayment::find($paymentId);
        }

        // Load active GCash accounts so resident/staff can choose who to pay
        $gcashAccounts = GcashAccount::getActive();
        $gcash = null;

        if (isset($_GET['gcash_account_id'])) {
            $switchId = (int) $_GET['gcash_account_id'];
            $switchAcc = GcashAccount::find($switchId);
            if ($switchAcc && (int) $switchAcc['is_active'] === 1) {
                $gcash = $switchAcc;
                db()->prepare('UPDATE document_payments SET gcash_account_id = ? WHERE id = ?')
                    ->execute([$switchId, (int) $payment['id']]);
            }
        }

        if (!$gcash && !empty($payment['gcash_account_id'])) {
            $gcash = GcashAccount::find((int) $payment['gcash_account_id']);
        }
        if (!$gcash) {
            $gcash = GcashAccount::getDefault();
        }

        $paypalClientId     = PayPalService::getClientId();
        $paypalMode         = PayPalService::getMode();
        $isPaypalConfigured = PayPalService::isConfigured();
        $currency           = 'PHP';

        $pageTitle = 'Payment Checkout — BarangGabay';
        view('resident/payment_checkout', compact(
            'request',
            'payment',
            'gcash',
            'gcashAccounts',
            'pageTitle',
            'paypalClientId',
            'paypalMode',
            'isPaypalConfigured',
            'currency'
        ));
    }

    /**
     * POST /documents/{id}/payment — Resident uploads proof of payment
     */
    public function residentUploadProof(array $params): void
    {
        check_csrf();

        $requestId = (int) ($params['id'] ?? 0);
        $userId    = (int) ($_SESSION['user_id'] ?? 0);

        $request = DocumentRequest::find($requestId);
        if (!$request || (int) $request['user_id'] !== $userId) {
            flash('error', t('flash.not_found'));
            redirect('/documents');
        }

        $payment = DocumentPayment::findByRequest($requestId);
        if (!$payment) {
            flash('error', 'Walang natagpuang talaan ng pagbabayad para sa kahilingang ito.');
            redirect('/documents');
        }

        $reportedAmount = (float) ($_POST['amount_reported'] ?? $payment['amount_due']);
        $gcashRef       = trim($_POST['gcash_reference_no'] ?? '');
        $note           = trim($_POST['notes'] ?? '');

        if ($gcashRef === '') {
            flash('error', 'Kailangan ilagay ang GCash Reference Number.');
            redirect('/documents/' . $requestId . '/payment');
        }

        // Validate receipt file
        if (!isset($_FILES['receipt_file']) || $_FILES['receipt_file']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Kailangan mag-upload ng larawan o PDF ng inyong resibo.');
            redirect('/documents/' . $requestId . '/payment');
        }

        $file     = $_FILES['receipt_file'];
        $maxBytes = 10485760; // 10MB
        if ((int) $file['size'] > $maxBytes) {
            flash('error', 'Ang sukat ng resibo ay dapat hindi hihigit sa 10MB.');
            redirect('/documents/' . $requestId . '/payment');
        }

        $tmpPath = (string) $file['tmp_name'];
        $finfo   = finfo_open(FILEINFO_MIME_TYPE);
        $mime    = $finfo ? (string) finfo_file($finfo, $tmpPath) : '';
        if ($finfo) finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        if (!in_array($mime, $allowedMimes, true)) {
            flash('error', 'Tanging JPG, PNG, o PDF lamang ang tinatanggap para sa resibo.');
            redirect('/documents/' . $requestId . '/payment');
        }

        $rawBytes = file_get_contents($tmpPath);
        if ($rawBytes === false) {
            flash('error', 'Hindi mabasa ang na-upload na resibo.');
            redirect('/documents/' . $requestId . '/payment');
        }

        $fileHash = hash('sha256', $rawBytes);
        $origName = basename((string) $file['name']);
        $cleanName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $origName);

        $fileMeta = [
            'name'        => $cleanName,
            'mime'        => $mime,
            'size'        => (int) $file['size'],
            'hash'        => $fileHash,
            'data_base64' => base64_encode($rawBytes),
        ];

        // Check if duplicate reference exists (log warning)
        $isDuplicateRef = DocumentPayment::checkDuplicateGcashRef($gcashRef, (int) $payment['id']);
        if ($isDuplicateRef) {
            $note .= ($note ? ' ' : '') . '[Babala sa Admin: Posibleng duplicate reference number]';
        }

        if (DocumentPayment::submitProof((int) $payment['id'], $reportedAmount, $gcashRef, $fileMeta, $note)) {
            // Notify staff of payment submission
            try {
                (new NotificationService())->notifyBackOffice(
                    'notify_content',
                    'system',
                    'May Bagong Patunay ng Bayad (' . (string) $request['reference_no'] . ')',
                    sprintf('Nagsumite si %s ng patunay ng bayad (Ref: %s, Halaga: ₱%.2f) para sa %s.', (string) $request['full_name'], $gcashRef, $reportedAmount, (string) $request['reference_no']),
                    0,
                    'document_payment',
                    $userId
                );
            } catch (\Throwable $e) {
                error_log('[PaymentController::residentUploadProof] notify error: ' . $e->getMessage());
            }

            flash('success', 'Naisumite na ang inyong patunay ng bayad. Mangyaring hintayin ang pagsusuri at beripikasyon ng kawani ng barangay.');
        }

        redirect('/documents/' . $requestId . '/payment');
    }

    /**
     * GET /documents/{id}/acknowledgement — Digital payment acknowledgement
     */
    public function acknowledgement(array $params): void
    {
        $requestId = (int) ($params['id'] ?? 0);
        $userId    = (int) ($_SESSION['user_id'] ?? 0);
        $role      = (string) ($_SESSION['role'] ?? $_SESSION['user_role'] ?? '');

        $request = DocumentRequest::find($requestId);
        if (!$request) {
            flash('error', t('flash.not_found'));
            redirect('/documents');
        }

        $isStaff = in_array($role, ['admin', 'staff', 'superadmin'], true);
        if (!$isStaff && (int) $request['user_id'] !== $userId) {
            http_response_code(403);
            exit('Access Denied.');
        }

        $payment = DocumentPayment::findByRequest($requestId);
        if (!$payment) {
            flash('error', 'Walang talaan ng pagbabayad.');
            redirect('/documents');
        }

        view('resident/payment_acknowledgement', compact('request', 'payment'));
    }

    /**
     * POST /api/payments/paypal/create-order
     * Server-side authoritative PayPal order creation
     */
    public function paypalCreateOrder(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $role   = (string) ($_SESSION['role'] ?? $_SESSION['user_role'] ?? '');
        $isStaff = in_array($role, ['admin', 'staff', 'superadmin'], true);

        if ($userId <= 0) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true) ?: [];
        $requestId = (int) ($input['request_id'] ?? ($_POST['request_id'] ?? 0));
        $paymentId = (int) ($input['payment_id'] ?? ($_POST['payment_id'] ?? 0));

        $payment = null;
        if ($paymentId > 0) {
            $payment = DocumentPayment::find($paymentId);
        } elseif ($requestId > 0) {
            $payment = DocumentPayment::findByRequest($requestId);
        }

        if (!$payment && $requestId > 0) {
            $request = DocumentRequest::find($requestId);
            if ($request) {
                $fee = (float) ($request['fee_amount'] ?? 0);
                if ($fee > 0.00) {
                    $newPayId = DocumentPayment::createForRequest(
                        $requestId,
                        (int) $request['user_id'],
                        (string) $request['document_type'],
                        $fee,
                        'paypal'
                    );
                    $payment = DocumentPayment::find($newPayId);
                }
            }
        }

        if (!$payment) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Payment record not found']);
            return;
        }

        // Ownership check
        if (!$isStaff && (int) $payment['user_id'] !== $userId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            return;
        }

        // Authoritative fee check
        $amountDue = (float) $payment['amount_due'];
        if ($amountDue <= 0.00) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Document is free, payment not required']);
            return;
        }

        // Check if already paid
        if ($payment['payment_status'] === DocumentPayment::STATUS_PAID_VERIFIED) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Payment already verified', 'already_paid' => true]);
            return;
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $returnUrl = $scheme . '://' . $host . '/documents/' . (int) $payment['request_id'] . '/acknowledgement';
        $cancelUrl = $scheme . '://' . $host . '/documents/' . (int) $payment['request_id'] . '/payment?status=cancelled';

        $description = sprintf('BarangGabay %s Fee (%s)', (string) $payment['document_type'], (string) $payment['payment_ref']);

        $res = PayPalService::createOrder(
            (int) $payment['request_id'],
            (string) $payment['payment_ref'],
            $amountDue,
            'PHP',
            (string) $payment['document_type'],
            (string) $payment['request_ref']
        );

        if (!$res['success'] || empty($res['order_id'])) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => $res['error'] ?? 'Failed to create PayPal order'
            ]);
            return;
        }

        $paypalOrderId = (string) $res['order_id'];
        DocumentPayment::setPaypalOrder((int) $payment['id'], $paypalOrderId, json_encode($res['raw'] ?? $res));

        AuditLog::record(
            $userId,
            'payment.paypal_order_created',
            sprintf('Created PayPal order %s for payment %s (₱%.2f)', $paypalOrderId, (string) $payment['payment_ref'], $amountDue)
        );

        echo json_encode([
            'success'   => true,
            'orderID'   => $paypalOrderId,
            'order_id'  => $paypalOrderId,
            'status'    => $res['status'] ?? 'CREATED',
            'simulated' => !empty($res['mock']),
        ]);
    }

    /**
     * POST /api/payments/paypal/capture-order/{orderId}
     * Server-side PayPal order capture & verification
     */
    public function paypalCaptureOrder(array $params): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $role   = (string) ($_SESSION['role'] ?? $_SESSION['user_role'] ?? '');
        $isStaff = in_array($role, ['admin', 'staff', 'superadmin'], true);

        if ($userId <= 0) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        $orderId = trim((string) ($params['orderId'] ?? ''));
        if ($orderId === '') {
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?: [];
            $orderId = trim((string) ($input['orderID'] ?? $input['order_id'] ?? ''));
        }

        if ($orderId === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing PayPal Order ID']);
            return;
        }

        $payment = DocumentPayment::findByPaypalOrderId($orderId);
        if (!$payment) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Payment record not found for this PayPal Order ID']);
            return;
        }

        // Ownership check
        if (!$isStaff && (int) $payment['user_id'] !== $userId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            return;
        }

        // Idempotency: if already paid and verified
        if ($payment['payment_status'] === DocumentPayment::STATUS_PAID_VERIFIED) {
            echo json_encode([
                'success'        => true,
                'status'         => 'COMPLETED',
                'message'        => 'Payment is already verified.',
                'payment_ref'    => $payment['payment_ref'],
                'redirect_url'   => '/documents/' . (int) $payment['request_id'] . '/acknowledgement',
            ]);
            return;
        }

        $res = PayPalService::captureOrder($orderId);
        if (!$res['success']) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => $res['error'] ?? 'PayPal capture failed'
            ]);
            return;
        }

        $captureStatus = (string) ($res['status'] ?? '');
        if ($captureStatus !== 'COMPLETED') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'PayPal order capture status is ' . $captureStatus . ' (expected COMPLETED)'
            ]);
            return;
        }

        // Validate captured amount
        $captureAmount   = $res['amount'] !== null ? (float) $res['amount'] : (float) $payment['amount_due'];
        $captureCurrency = (string) ($res['currency'] ?? 'PHP');
        $expectedAmount  = (float) $payment['amount_due'];

        $captureId   = (string) ($res['capture_id'] ?? ('CAP-' . bin2hex(random_bytes(6))));
        $payerId     = (string) ($res['payer_id'] ?? null);
        $payerEmail  = (string) ($res['payer_email'] ?? null);
        $rawResponse = json_encode($res['raw'] ?? $res);

        $saved = DocumentPayment::recordPaypalCapture(
            (int) $payment['id'],
            $captureId,
            $captureAmount > 0 ? $captureAmount : $expectedAmount,
            $captureCurrency,
            $payerId,
            $payerEmail,
            $rawResponse
        );

        if (!$saved) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error recording capture']);
            return;
        }

        // Audit Log
        AuditLog::record(
            $userId,
            'payment.paypal_captured',
            sprintf('PayPal payment captured for %s (Ref: %s, Capture ID: %s, ₱%.2f)', (string) $payment['request_ref'], (string) $payment['payment_ref'], $captureId, $captureAmount)
        );

        // Notifications
        $reqRef   = (string) $payment['request_ref'];
        $docLabel = DocumentRequest::label((string) $payment['document_type']);
        try {
            (new NotificationService())->notifyUser(
                (int) $payment['user_id'],
                'system',
                'Naberipika na ang Inyong Bayad sa PayPal (' . $reqRef . ')',
                sprintf('Matagumpay na natanggap at naberipika ang inyong bayad via PayPal para sa %s (%s). Kasalukuyan nang inihahanda ang inyong dokumento.', $docLabel, $reqRef),
                0,
                'document_payment'
            );
        } catch (\Throwable $e) {
            error_log('[PaymentController::paypalCaptureOrder] notification error: ' . $e->getMessage());
        }

        // SMS Notification if phone configured
        $phone = trim((string) ($payment['phone'] ?? ''));
        if ($phone !== '') {
            try {
                $smsMsg = sprintf('BARANGGABAY: Payment for %s has been confirmed. Your request is now being processed.', $reqRef);
                (new OneWaySmsService())->send($phone, $smsMsg, 'payment_verified', (int) $payment['id']);
            } catch (\Throwable $e) {
                error_log('[PaymentController::paypalCaptureOrder] SMS error: ' . $e->getMessage());
            }
        }

        echo json_encode([
            'success'        => true,
            'status'         => 'COMPLETED',
            'message'        => 'Payment successfully captured and verified.',
            'payment_ref'    => $payment['payment_ref'],
            'capture_id'     => $captureId,
            'redirect_url'   => '/documents/' . (int) $payment['request_id'] . '/acknowledgement',
        ]);
    }

    /**
     * POST /api/payments/paypal/webhook
     * PayPal Webhook Listener for real-time order/payment status verification
     */
    public function paypalWebhook(): void
    {
        $rawPayload = file_get_contents('php://input');
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (!$headers) {
            $headers = [];
            foreach ($_SERVER as $k => $v) {
                if (str_starts_with($k, 'HTTP_')) {
                    $h = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
                    $headers[$h] = $v;
                }
            }
        }

        $data = json_decode($rawPayload, true);
        if (!$data || !is_array($data)) {
            http_response_code(400);
            echo 'Invalid payload';
            return;
        }

        $eventId   = (string) ($data['id'] ?? '');
        $eventType = (string) ($data['event_type'] ?? '');
        $resource  = $data['resource'] ?? [];

        // Verify webhook signature
        $isVerified = PayPalService::verifyWebhookSignature($headers, $rawPayload);
        if (!$isVerified) {
            error_log('[PayPal Webhook] Signature verification failed for event: ' . $eventId);
            http_response_code(400);
            echo 'Webhook signature verification failed';
            return;
        }

        // Webhook Idempotency: skip if already processed
        if ($eventId !== '' && DocumentPayment::isWebhookProcessed($eventId)) {
            http_response_code(200);
            echo json_encode(['status' => 'already_processed']);
            return;
        }

        // Process Event
        switch ($eventType) {
            case 'PAYMENT.CAPTURE.COMPLETED':
                $captureId = (string) ($resource['id'] ?? '');
                $amount    = (float) ($resource['amount']['value'] ?? 0);
                $currency  = (string) ($resource['amount']['currency_code'] ?? 'PHP');
                $customId  = (string) ($resource['custom_id'] ?? '');

                // Find payment record
                $payment = null;
                if (!empty($resource['supplementary_data']['related_ids']['order_id'])) {
                    $orderId = (string) $resource['supplementary_data']['related_ids']['order_id'];
                    $payment = DocumentPayment::findByPaypalOrderId($orderId);
                }
                if (!$payment && $customId !== '') {
                    $payment = DocumentPayment::findByRequest((int) $customId);
                }

                if ($payment && $payment['payment_status'] !== DocumentPayment::STATUS_PAID_VERIFIED) {
                    $payerId = (string) ($resource['payer']['payer_id'] ?? null);
                    $payerEmail = (string) ($resource['payer']['email_address'] ?? null);
                    DocumentPayment::recordPaypalCapture(
                        (int) $payment['id'],
                        $captureId,
                        $amount > 0 ? $amount : (float) $payment['amount_due'],
                        $currency,
                        $payerId,
                        $payerEmail,
                        $rawPayload
                    );

                    AuditLog::record(
                        (int) $payment['user_id'],
                        'payment.paypal_webhook_verified',
                        sprintf('Verified payment via webhook event %s for %s', $eventId, (string) $payment['payment_ref'])
                    );
                }
                break;

            case 'CHECKOUT.ORDER.APPROVED':
                // Log approval
                break;

            case 'PAYMENT.CAPTURE.DENIED':
            case 'PAYMENT.CAPTURE.DECLINED':
                $customId = (string) ($resource['custom_id'] ?? '');
                if ($customId !== '') {
                    $payment = DocumentPayment::findByRequest((int) $customId);
                    if ($payment) {
                        db()->prepare('UPDATE document_payments SET payment_status = ?, updated_at = NOW() WHERE id = ?')
                            ->execute([DocumentPayment::STATUS_FAILED, (int) $payment['id']]);
                    }
                }
                break;
        }

        // Record processed webhook event for idempotency
        if ($eventId !== '') {
            $resourceId = (string) ($resource['id'] ?? '');
            DocumentPayment::recordWebhookEvent($eventId, $eventType, $resourceId, $rawPayload);
        }

        http_response_code(200);
        echo json_encode(['status' => 'success']);
    }
}

