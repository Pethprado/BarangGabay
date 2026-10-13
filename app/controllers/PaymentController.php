<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\DocumentFee;
use App\Models\DocumentPayment;
use App\Models\DocumentRequest;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\OneWaySmsService;
use App\Services\PayMongoService;
use App\Services\PaymentConfirmation;
use App\Services\PaymongoCheckout;

class PaymentController
{
    /**
     * GET /admin/payments — Payment Dashboard & Transactions
     */
    public function adminIndex(): void
    {
        $group    = trim($_GET['group'] ?? '');
        $group    = ($group === 'paid' || isset(DocumentPayment::FILTER_GROUPS[$group])) ? $group : '';
        $status   = trim($_GET['status'] ?? '');
        $method   = trim($_GET['method'] ?? '');
        $docType  = trim($_GET['doc_type'] ?? '');
        $search   = trim($_GET['search'] ?? '');

        $stats        = DocumentPayment::getDashboardStats();
        $transactions = DocumentPayment::getTransactions([
            'group'    => $group,
            'status'   => $status,
            'method'   => $method,
            'doc_type' => $docType,
            'search'   => $search,
        ]);

        $docFees   = DocumentFee::getAll();
        $pageTitle = 'Payment Management';

        view('admin/payments/index', compact(
            'stats',
            'transactions',
            'docFees',
            'group',
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

        // Manual verification is only for payments in Manual Review. An online
        // PayMongo payment is confirmed by PayMongo — use "Check PayMongo Status".
        if (!in_array((string) $payment['payment_status'], DocumentPayment::FILTER_GROUPS['manual_review'], true)) {
            flash('warning', __('Only payments in Manual Review can be verified by hand. For online payments, use "Check PayMongo Status".'));
            redirect($this->safeReturn('/admin/payments'));
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
            flash('error', __('Nagka-problema sa pag-verify ng bayad.'));
        }

        redirect($this->safeReturn('/admin/payments'));
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
            flash('error', __('Mangyaring pumili ng dahilan ng pagtanggi sa resibo.'));
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
            flash('error', __('Nagka-problema sa pagtanggi sa bayad.'));
        }

        redirect($this->safeReturn('/admin/payments'));
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

        if (!empty($payment['paymongo_checkout_id'])) {
            $check = PaymentConfirmation::reconcile($payment, $staffId);
            if (in_array($check['outcome'], [PaymentConfirmation::OUTCOME_CONFIRMED, PaymentConfirmation::OUTCOME_ALREADY], true)) {
                flash('warning', __('PayMongo shows this request is already paid online — do not collect cash.') . ' ' . $check['message']);
                redirect($this->safeReturn('/admin/payments'));
            }
        }

        if (DocumentPayment::markPaidAtPickup($id, $staffId, $note)) {
            AuditLog::record(
                $staffId,
                'payment.paid_at_pickup',
                sprintf('Collected cash payment %s for request %s (₱%.2f)', (string) $payment['payment_ref'], (string) $payment['request_ref'], (float) $payment['amount_due'])
            );

            flash('success', sprintf('Naitampok na bayad na sa counter ang ₱%.2f para sa request %s.', (float) $payment['amount_due'], (string) $payment['request_ref']));
        } else {
            flash('error', __('Hindi na-update ang estado ng bayad.'));
        }

        redirect($this->safeReturn('/admin/payments'));
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

        if ($payment && !empty($payment['paymongo_checkout_id'])) {
            $check = PaymentConfirmation::reconcile($payment, $staffId);
            if (in_array($check['outcome'], [PaymentConfirmation::OUTCOME_CONFIRMED, PaymentConfirmation::OUTCOME_ALREADY], true)) {
                flash('warning', __('PayMongo shows this request is already paid online — do not collect cash.'));
                redirect('/admin/documents');
            }
        }

        if ($payment && DocumentPayment::markPaidAtPickup((int)$payment['id'], $staffId, $note)) {
            AuditLog::record(
                $staffId,
                'payment.paid_at_pickup',
                sprintf('Collected cash payment for request %s (₱%.2f)', (string) $req['reference_no'], (float) ($req['fee_amount'] ?? 0))
            );
            flash('success', sprintf('Naitampok na bayad na sa counter ang ₱%.2f para sa request %s.', (float) ($req['fee_amount'] ?? 0), (string) $req['reference_no']));
        } else {
            flash('error', __('Hindi na-update ang estado ng bayad.'));
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
            flash('error', __('Kailangan ng dahilan para sa pagpapalampas / libreng dokumento.'));
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
            flash('error', __('Hindi nai-waive ang bayad.'));
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
            flash('error', __('Ilagay ang wastong halaga at dahilan ng refund.'));
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
            flash('error', __('Hindi naitala ang refund.'));
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
     * GET /admin/payments/fees — Document types & fees.
     */
    public function feesIndex(): void
    {
        $fees      = DocumentFee::getAll();
        $pageTitle = 'Document Fees';
        view('admin/payments/fees', compact('fees', 'pageTitle'));
    }

    /**
     * POST /admin/payments/fees — Save document types & fees (admin only).
     * Every change is written to the payment audit trail with old → new.
     */
    public function feesUpdate(): void
    {
        check_csrf();
        $staffId  = (int) ($_SESSION['user_id'] ?? 0);
        $feesPost = $_POST['fees'] ?? [];
        if (!is_array($feesPost)) {
            redirect('/admin/payments/fees');
        }

        $changed = 0;
        foreach ($feesPost as $type => $data) {
            if (!is_string($type) || !array_key_exists($type, DocumentRequest::TYPES) || !is_array($data)) {
                continue;
            }
            $amount   = (float) str_replace(',', '', (string) ($data['amount'] ?? '0'));
            $isFree   = ($data['payment_required'] ?? '1') !== '1';
            $isActive = !empty($data['is_active']);

            // A paid fee under PayMongo's minimum could never be paid online.
            if (!$isFree && (bool) \App\Models\Setting::get('paymongo_checkout_enabled', false)
                && PayMongoService::toCentavos($amount) < PayMongoService::MIN_AMOUNT_CENTAVOS) {
                flash('error', sprintf('%s: a fee that requires payment must be at least ₱%.2f (PayMongo minimum). Not saved.',
                    DocumentRequest::label($type), PayMongoService::MIN_AMOUNT_CENTAVOS / 100));
                continue;
            }

            $before = DocumentFee::setFee($type, $amount, $isFree, $staffId, $isActive,
                (string) ($data['description'] ?? ''), (string) ($data['requirements'] ?? ''));
            if ($before === null) {
                continue;
            }

            $after = DocumentFee::getAll()[$type];
            if ($before['amount'] !== $after['amount'] || $before['is_free'] !== $after['is_free'] || $before['is_active'] !== $after['is_active']) {
                $changed++;
                $describe = static fn (array $f): string => sprintf('%s, %s, %s',
                    $f['is_free'] ? 'free' : '₱' . number_format($f['amount'], 2), $f['is_free'] ? 'no payment' : 'payment required',
                    $f['is_active'] ? 'enabled' : 'disabled');
                DocumentPayment::logAudit(0, null, $staffId, 'DOCUMENT_FEE_CHANGED', null, null,
                    sprintf('%s: %s → %s', DocumentRequest::label($type), $describe($before), $describe($after)));
                AuditLog::record($staffId, 'payment.fee_changed', sprintf('%s: %s → %s', DocumentRequest::label($type), $describe($before), $describe($after)));
            }
        }

        flash('success', $changed > 0
            ? sprintf('Saved. %d document fee setting(s) changed; new requests use the new fees.', $changed)
            : 'Saved. Descriptions and requirements are up to date.');
        redirect('/admin/payments/fees');
    }

    /**
     * POST /admin/payments/{id}/check-status — Ask PayMongo for the real
     * state of this payment. Never marks anything paid by itself: only a
     * PayMongo-confirmed, amount-matching payment can become PAID.
     */
    public function checkStatus(array $params): void
    {
        check_csrf();
        $staffId = (int) ($_SESSION['user_id'] ?? 0);
        $payment = DocumentPayment::find((int) ($params['id'] ?? 0));
        if (!$payment) {
            flash('error', t('flash.not_found'));
            redirect('/admin/payments');
        }

        $result = PaymentConfirmation::reconcile($payment, $staffId);
        $tone   = match ($result['outcome']) {
            PaymentConfirmation::OUTCOME_CONFIRMED, PaymentConfirmation::OUTCOME_ALREADY => 'success',
            PaymentConfirmation::OUTCOME_ERROR, PaymentConfirmation::OUTCOME_MISMATCH    => 'error',
            default                                                                       => 'warning',
        };
        flash($tone, $payment['payment_ref'] . ': ' . $result['message']);
        redirect($this->safeReturn('/admin/payments'));
    }

    /**
     * GET /admin/payments/{id}/history — Audit trail of one payment (JSON).
     */
    public function auditHistory(array $params): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $payment = DocumentPayment::find((int) ($params['id'] ?? 0));
        if (!$payment) {
            http_response_code(404);
            echo json_encode(['error' => __('Not found')]);
            return;
        }
        $rows = array_map(static fn (array $l): array => [
            'at'     => (string) $l['created_at'],
            'action' => (string) $l['action'],
            'from'   => (string) ($l['old_status'] ?? ''),
            'to'     => (string) ($l['new_status'] ?? ''),
            'by'     => (string) ($l['actor_name'] ?? 'System / PayMongo'),
            'detail' => (string) ($l['details'] ?? ''),
        ], DocumentPayment::getAuditLogs((int) $payment['id']));

        echo json_encode(['payment_ref' => $payment['payment_ref'], 'entries' => $rows]);
    }

    /** A return path inside the back office only — never an external URL. */
    private function safeReturn(string $fallback): string
    {
        $to = (string) ($_POST['return_to'] ?? '');
        return preg_match('#^/admin(/[A-Za-z0-9/_\-]*)?(\?[A-Za-z0-9=&_\-%]*)?$#', $to) ? $to : $fallback;
    }

    /**
     * POST /admin/payments/gcash-account — the barangay GCash that residents
     * send document fees to (admin only): name, number, QR image, and whether
     * PayMongo checkout is also offered.
     */
    public function gcashAccountUpdate(): void
    {
        check_csrf();
        $staffId = (int) ($_SESSION['user_id'] ?? 0);
        $name    = trim((string) ($_POST['gcash_name'] ?? ''));
        $number  = preg_replace('/\D/', '', (string) ($_POST['gcash_number'] ?? ''));
        if (str_starts_with($number, '63') && strlen($number) === 12) {
            $number = '0' . substr($number, 2);
        }

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            flash('error', __('Enter the GCash account name (as shown in GCash).'));
            redirect('/admin/payments/fees#gcash-account');
        }
        if (!preg_match('/^09\d{9}$/', $number)) {
            flash('error', __('Enter a valid 11-digit GCash mobile number, e.g. 09171234567.'));
            redirect('/admin/payments/fees#gcash-account');
        }

        $values = [
            'gcash_receive_name'        => $name,
            'gcash_receive_number'      => $number,
            'paymongo_checkout_enabled' => !empty($_POST['paymongo_checkout_enabled']),
        ];

        if (!empty($_POST['remove_qr'])) {
            $values['gcash_receive_qr']      = '';
            $values['gcash_receive_qr_mime'] = '';
        } elseif (isset($_FILES['gcash_qr']) && ($_FILES['gcash_qr']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['gcash_qr'];
            if ($file['error'] !== UPLOAD_ERR_OK || (int) $file['size'] > 3 * 1024 * 1024) {
                flash('error', __('The QR image could not be uploaded (max 3 MB).'));
                redirect('/admin/payments/fees#gcash-account');
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = (string) finfo_file($finfo, (string) $file['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
                flash('error', __('The QR must be a PNG, JPG or WEBP image.'));
                redirect('/admin/payments/fees#gcash-account');
            }
            $values['gcash_receive_qr']      = base64_encode((string) file_get_contents((string) $file['tmp_name']));
            $values['gcash_receive_qr_mime'] = $mime;
        }

        \App\Models\Setting::setMany($values, $staffId);
        AuditLog::record($staffId, 'payment.gcash_account', sprintf('GCash receiving account set to %s (%s)%s',
            $name, $number, isset($values['gcash_receive_qr']) ? ($values['gcash_receive_qr'] === '' ? ', QR removed' : ', QR updated') : ''));
        flash('success', __('GCash receiving account saved.'));
        redirect('/admin/payments/fees#gcash-account');
    }

    /**
     * GET /payments/gcash-qr — the barangay GCash QR image, for signed-in users.
     */
    public function gcashQr(): void
    {
        $data = (string) \App\Models\Setting::get('gcash_receive_qr', '');
        $mime = (string) \App\Models\Setting::get('gcash_receive_qr_mime', '');
        $raw  = $data !== '' ? base64_decode($data, true) : false;
        if ($raw === false || !in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            http_response_code(404);
            exit('No GCash QR has been uploaded.');
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($raw));
        header('Cache-Control: private, max-age=300');
        header('X-Content-Type-Options: nosniff');
        if (isset($_GET['download'])) {
            header('Content-Disposition: attachment; filename="barangay-gcash-qr.' . ($mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg')) . '"');
        }
        echo $raw;
        exit;
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
     * GET /documents/{id}/payment — the resident's "Pay with GCash" page.
     */
    public function checkout(array $params): void
    {
        $id      = (int) ($params['id'] ?? 0);
        $userId  = (int) ($_SESSION['user_id'] ?? 0);
        $request = DocumentRequest::find($id);
        if (!$request || (int) $request['user_id'] !== $userId) {
            flash('error', t('flash.not_found'));
            redirect('/documents');
        }

        if ((float) ($request['fee_amount'] ?? 0) <= 0.00) {
            flash('info', __('Ang dokumentong ito ay libre. Hindi kailangan ng pagbabayad.'));
            redirect('/documents');
        }
        if (($request['payment_method'] ?? '') === 'pickup') {
            flash('info', __('Ang inyong kahilingan ay babayaran sa Barangay Hall sa pagkuha.'));
            redirect('/documents');
        }

        $payment = DocumentPayment::findByRequest($id);
        if (!$payment) {
            flash('error', __('Walang talaan ng pagbabayad para sa kahilingang ito.'));
            redirect('/documents');
        }

        // A resident coming back to this page (refresh, back button) sees the
        // real status: if PayMongo already has the money, settle it now.
        $unsettled = !in_array((string) $payment['payment_status'], DocumentPayment::SETTLED_STATES, true);
        if ($unsettled && !empty($payment['paymongo_checkout_id'])) {
            PaymentConfirmation::reconcile($payment);
            $payment = DocumentPayment::findByRequest($id);
            $request = DocumentRequest::find($id);
        }

        $paymongoEnabled = (bool) \App\Models\Setting::get('paymongo_checkout_enabled', false) && PayMongoService::isConfigured();
        $gcash = [
            'name'   => (string) \App\Models\Setting::get('gcash_receive_name', ''),
            'number' => (string) \App\Models\Setting::get('gcash_receive_number', ''),
            'has_qr' => (string) \App\Models\Setting::get('gcash_receive_qr', '') !== '',
        ];
        $pageTitle = 'Pay with GCash — BarangGabay';
        view('resident/payment_checkout', compact('request', 'payment', 'pageTitle', 'paymongoEnabled', 'gcash'));
    }

    /**
     * POST /documents/{id}/payment — the resident submits proof of a GCash
     * payment (reference number + screenshot). The payment goes to Manual
     * Review; it becomes PAID only when staff verify it.
     */
    public function residentUploadProof(array $params): void
    {
        check_csrf();

        $requestId = (int) ($params['id'] ?? 0);
        $userId    = (int) ($_SESSION['user_id'] ?? 0);
        $back      = '/documents/' . $requestId . '/payment';

        $request = DocumentRequest::find($requestId);
        if (!$request || (int) $request['user_id'] !== $userId) {
            flash('error', t('flash.not_found'));
            redirect('/documents');
        }
        $payment = DocumentPayment::findByRequest($requestId);
        if (!$payment) {
            flash('error', __('No payment record was found for this request.'));
            redirect('/documents');
        }
        if (in_array((string) $payment['payment_status'], DocumentPayment::SETTLED_STATES, true)) {
            flash('info', __('This request is already paid — no proof is needed.'));
            redirect($back);
        }
        if (in_array((string) $request['status'], ['rejected', 'cancelled'], true)) {
            flash('error', $request['status'] === 'rejected'
                ? __('This request was rejected and can no longer be paid.')
                : __('This request was cancelled and can no longer be paid.'));
            redirect('/documents');
        }

        // GCash reference numbers are 13 digits; accept spaces/dashes as typed.
        $gcashRef = preg_replace('/[\s\-]/', '', (string) ($_POST['gcash_reference_no'] ?? ''));
        if (!preg_match('/^\d{8,20}$/', $gcashRef)) {
            flash('error', __('Enter the GCash reference number (the 13-digit "Ref. No." on your GCash receipt).'));
            redirect($back);
        }

        $file = $_FILES['receipt_file'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', __('Attach a screenshot of your GCash receipt.'));
            redirect($back);
        }
        if ((int) $file['size'] > 10 * 1024 * 1024) {
            flash('error', __('The screenshot must be 10 MB or smaller.'));
            redirect($back);
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = (string) finfo_file($finfo, (string) $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
            flash('error', __('Upload a JPG, PNG, WEBP or PDF file.'));
            redirect($back);
        }

        $rawBytes = (string) file_get_contents((string) $file['tmp_name']);
        $fileMeta = [
            'name'        => preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename((string) $file['name'])) ?: 'gcash-receipt',
            'mime'        => $mime,
            'size'        => (int) $file['size'],
            'hash'        => hash('sha256', $rawBytes),
            'data_base64' => base64_encode($rawBytes),
        ];

        $note = mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 500);
        $warnings = [];
        if (DocumentPayment::checkDuplicateGcashRef($gcashRef, (int) $payment['id'])) {
            $warnings[] = 'reference number already used on another payment';
        }
        if (DocumentPayment::checkDuplicateHash($fileMeta['hash'], (int) $payment['id'])) {
            $warnings[] = 'same screenshot already used on another payment';
        }
        if ($warnings) {
            $note = trim($note . ' [Staff warning: ' . implode('; ', $warnings) . ']');
        }

        // The amount owed is the official fee; the resident does not type it.
        if (!DocumentPayment::submitProof((int) $payment['id'], (float) $payment['amount_due'], $gcashRef, $fileMeta, $note ?: null)) {
            flash('error', __('Your proof could not be saved. Please try again.'));
            redirect($back);
        }

        try {
            (new NotificationService())->notifyBackOffice(
                'notify_content', 'system',
                'GCash payment to verify — ' . $request['reference_no'],
                sprintf('%s sent GCash proof for %s (₱%.2f, Ref. No. %s).%s',
                    (string) $request['full_name'], DocumentRequest::label((string) $request['document_type']),
                    (float) $payment['amount_due'], $gcashRef, $warnings ? ' Warning: ' . implode('; ', $warnings) . '.' : ''),
                0, 'document_payment', $userId
            );
        } catch (\Throwable $e) {
            error_log('[PaymentController::residentUploadProof] notify: ' . $e->getMessage());
        }

        flash('success', __('Proof sent. The barangay will verify your GCash payment — this page updates automatically.'));
        redirect($back);
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
            flash('error', __('Walang talaan ng pagbabayad.'));
            redirect('/documents');
        }

        view('resident/payment_acknowledgement', compact('request', 'payment'));
    }

    /**
     * POST /api/payments/paymongo/create-checkout — "Pay with GCash".
     *
     * JSON in: {request_id, csrf_token}. The amount is NOT accepted from the
     * client; PaymongoCheckout charges the fee stored on the payment row.
     */
    public function paymongoCreateCheckout(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $input  = json_decode((string) file_get_contents('php://input'), true);
        $input  = is_array($input) ? $input : $_POST;

        $token = (string) ($input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if ($userId <= 0 || !hash_equals(csrf_token(), $token)) {
            http_response_code($userId <= 0 ? 401 : 403);
            echo json_encode(['success' => false, 'error' => __('Your session expired. Please reload the page.')]);
            return;
        }

        if (!(bool) \App\Models\Setting::get('paymongo_checkout_enabled', false)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => __('Online checkout is turned off. Please pay by sending GCash and uploading your proof.')]);
            return;
        }

        $requestId = (int) ($input['request_id'] ?? 0);
        $request   = $requestId > 0 ? DocumentRequest::find($requestId) : null;
        $payment   = $request ? DocumentPayment::findByRequest($requestId) : null;

        // Only the resident who filed the request can pay for it.
        if (!$request || !$payment || (int) $request['user_id'] !== $userId) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => __('Request not found.')]);
            return;
        }

        $res = PaymongoCheckout::start((int) $payment['id'], $userId);
        if (!$res['success']) {
            http_response_code($res['already_paid'] ? 409 : 422);
            echo json_encode(['success' => false, 'already_paid' => $res['already_paid'], 'error' => $res['error']]);
            return;
        }

        echo json_encode(['success' => true, 'checkout_url' => $res['checkout_url'], 'reused' => $res['reused']]);
    }

    /**
     * GET /payments/paymongo/return — older return URL; same as the success page.
     */
    public function paymongoReturn(): void
    {
        redirect('/documents/payment/success?request_id=' . (int) ($_GET['request_id'] ?? 0));
    }

    /**
     * POST /api/webhooks/paymongo (also /api/payments/paymongo/webhook and
     * /payments/paymongo/webhook for webhooks registered earlier).
     *
     * The authoritative confirmation. Public route, no session, no CSRF — the
     * Paymongo-Signature HMAC is the authentication. Handles:
     *   checkout_session.payment.paid, payment.paid, payment.failed
     *
     * Responses: 200 handled (or already handled) · 401 bad signature ·
     * 503 webhook secret not configured · 500 processing error (PayMongo retries).
     */
    public function paymongoWebhook(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $rawPayload = (string) file_get_contents('php://input');

        if (!PayMongoService::isWebhookConfigured()) {
            error_log('[PayMongo webhook] rejected: PAYMONGO_WEBHOOK_SECRET is not set.');
            http_response_code(503);
            echo json_encode(['error' => 'webhook not configured']);
            return;
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[str_replace('_', '-', strtolower(substr($key, 5)))] = $value;
            }
        }
        if (!PayMongoService::verifyWebhookSignature($headers, $rawPayload)) {
            error_log('[PayMongo webhook] rejected: invalid signature.');
            http_response_code(401);
            echo json_encode(['error' => 'invalid signature']);
            return;
        }

        $data = json_decode($rawPayload, true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid payload']);
            return;
        }

        $event = PayMongoService::parseWebhookEvent($data);
        if ($event['event_id'] !== '' && DocumentPayment::isWebhookProcessed($event['event_id'])) {
            echo json_encode(['status' => 'already_processed']);
            return;
        }

        try {
            $result = match ($event['event_type']) {
                'checkout_session.payment.paid', 'payment.paid' => $this->webhookPaid($event),
                'payment.failed'                                 => $this->webhookFailed($event),
                default                                          => 'ignored',
            };
        } catch (\Throwable $e) {
            error_log('[PayMongo webhook] processing error for ' . $event['event_id'] . ': ' . $e->getMessage());
            http_response_code(500); // not recorded → PayMongo retries
            echo json_encode(['error' => 'processing error']);
            return;
        }

        if ($event['event_id'] !== '') {
            DocumentPayment::recordWebhookEvent($event['event_id'], $event['event_type'],
                $event['checkout_session_id'] ?? ($event['payments'][0]['id'] ?? null));
        }
        echo json_encode(['status' => $result]);
    }

    /** Find the BarangGabay payment a PayMongo event belongs to. */
    private function paymentForEvent(array $event): ?array
    {
        if (!empty($event['checkout_session_id'])) {
            $found = DocumentPayment::findByPaymongoCheckoutId((string) $event['checkout_session_id']);
            if ($found) {
                return $found;
            }
        }
        foreach ($event['payments'] as $pm) {
            if (!empty($pm['payment_intent_id'])) {
                $found = DocumentPayment::findByPaymongoPaymentIntentId((string) $pm['payment_intent_id']);
                if ($found) {
                    return $found;
                }
            }
        }
        // Metadata fallback (e.g. a second session from a double click): both
        // our unguessable payment ref AND the request id must agree.
        $meta = $event['metadata'];
        if (!empty($meta['payment_ref']) && !empty($meta['document_request_id'])) {
            $found = DocumentPayment::findByPaymentRef((string) $meta['payment_ref']);
            if ($found && (int) $found['request_id'] === (int) $meta['document_request_id']) {
                return DocumentPayment::find((int) $found['id']);
            }
        }
        return null;
    }

    private function webhookPaid(array $event): string
    {
        $payment = $this->paymentForEvent($event);
        if (!$payment) {
            error_log('[PayMongo webhook] no payment matches event ' . $event['event_id']);
            return 'unmatched';
        }
        foreach ($event['payments'] as $pm) {
            if ($pm['status'] === 'paid') {
                return PaymentConfirmation::confirm($payment, $pm, $event['event_id'], $event['checkout_session_id']);
            }
        }
        return 'no_paid_payment';
    }

    private function webhookFailed(array $event): string
    {
        $payment = $this->paymentForEvent($event);
        if (!$payment) {
            return 'unmatched';
        }
        $reason = 'PayMongo reported the payment as failed (event ' . $event['event_id'] . ').';
        if (DocumentPayment::transitionUnsettled((int) $payment['id'], DocumentPayment::STATUS_FAILED, 'PAYMENT_FAILED', null, $reason)) {
            try {
                (new NotificationService())->notifyUser((int) $payment['user_id'], 'system',
                    'GCash payment failed — ' . $payment['request_ref'],
                    'Hindi natuloy ang inyong GCash na bayad. Walang nabawas. Maaari kayong magbayad muli mula sa Aking Mga Kahilingan.',
                    0, 'document_payment');
            } catch (\Throwable $e) {
                error_log('[PayMongo webhook] failure notification: ' . $e->getMessage());
            }
            return 'marked_failed';
        }
        return 'unchanged';
    }

    /**
     * GET /documents/payment/success — the resident returns from PayMongo.
     *
     * Returning here proves nothing. The page asks PayMongo directly; until
     * PayMongo (or the webhook) confirms, it shows "verification in progress".
     */
    public function paymentSuccess(): void
    {
        $userId    = (int) ($_SESSION['user_id'] ?? 0);
        $requestId = (int) ($_GET['request_id'] ?? 0);
        $request   = $requestId > 0 ? DocumentRequest::find($requestId) : null;
        if (!$request || (int) $request['user_id'] !== $userId) {
            flash('error', t('flash.not_found'));
            redirect('/documents');
        }

        $payment = DocumentPayment::findByRequest($requestId);
        if (!$payment) {
            flash('error', __('Walang natagpuang talaan ng pagbabayad.'));
            redirect('/documents');
        }

        if (!in_array((string) $payment['payment_status'], DocumentPayment::SETTLED_STATES, true)) {
            PaymentConfirmation::reconcile($payment);
            $payment = DocumentPayment::findByRequest($requestId);
            $request = DocumentRequest::find($requestId);
        }

        $pageTitle = 'Payment Status — BarangGabay';
        view('resident/payment_success', compact('request', 'payment', 'pageTitle'));
    }

    /**
     * GET /documents/payment/cancel — the resident backed out of PayMongo.
     */
    public function paymentCancel(): void
    {
        $userId    = (int) ($_SESSION['user_id'] ?? 0);
        $requestId = (int) ($_GET['request_id'] ?? 0);
        $request   = $requestId > 0 ? DocumentRequest::find($requestId) : null;
        if (!$request || (int) $request['user_id'] !== $userId) {
            redirect('/documents');
        }

        $payment = DocumentPayment::findByRequest($requestId);
        if ($payment) {
            // Ask PayMongo first: a "cancel" can race a payment that went through.
            $check = PaymentConfirmation::reconcile($payment);
            if (in_array($check['outcome'], [PaymentConfirmation::OUTCOME_CONFIRMED, PaymentConfirmation::OUTCOME_ALREADY], true)) {
                redirect('/documents/payment/success?request_id=' . $requestId);
            }
            if ($check['outcome'] === PaymentConfirmation::OUTCOME_NOT_PAID) {
                DocumentPayment::transitionUnsettled((int) $payment['id'], DocumentPayment::STATUS_CANCELLED,
                    'PAYMENT_CANCELLED', $userId, 'Resident left the PayMongo checkout without paying.');
            }
        }

        flash('warning', __('Hindi natuloy ang pagbabayad. Naka-save pa rin ang inyong kahilingan — maaari kayong magbayad muli anumang oras.'));
        redirect('/documents/' . $requestId . '/payment');
    }

    /**
     * GET /api/payments/status/{id} — status poll for the resident's own request.
     * Asks PayMongo at most once every 5 seconds per session.
     */
    public function apiPaymentStatus(array $params): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $userId    = (int) ($_SESSION['user_id'] ?? 0);
        $requestId = (int) ($params['id'] ?? 0);
        $request   = $requestId > 0 ? DocumentRequest::find($requestId) : null;
        $payment   = $request ? DocumentPayment::findByRequest($requestId) : null;
        if (!$request || !$payment || (int) $request['user_id'] !== $userId) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => __('Not found')]);
            return;
        }

        $throttleKey = 'pm_poll_' . $requestId;
        $settled     = in_array((string) $payment['payment_status'], DocumentPayment::SETTLED_STATES, true);
        if (!$settled && !empty($payment['paymongo_checkout_id']) && (time() - (int) ($_SESSION[$throttleKey] ?? 0)) >= 5) {
            $_SESSION[$throttleKey] = time();
            PaymentConfirmation::reconcile($payment);
            $payment = DocumentPayment::findByRequest($requestId);
            $request = DocumentRequest::find($requestId);
        }

        echo json_encode([
            'success'        => true,
            'is_paid'        => in_array((string) $payment['payment_status'], DocumentPayment::PAID_STATES, true),
            'payment_status' => (string) $payment['payment_status'],
            'status_label'   => DocumentPayment::statusLabel((string) $payment['payment_status']),
            'request_status' => (string) ($request['status'] ?? ''),
            'amount_due'     => number_format((float) $payment['amount_due'], 2),
            'payment_ref'    => (string) $payment['payment_ref'],
            'payment_method' => 'GCash via PayMongo',
            'document_label' => DocumentRequest::label((string) $request['document_type']),
            'paid_at'        => (string) ($payment['paid_at'] ?? ''),
        ]);
    }
}
