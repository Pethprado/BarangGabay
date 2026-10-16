<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\DocumentPayment;
use App\Models\DocumentRequest;
use App\Services\DocumentQr;
use App\Services\GcashQr;

/**
 * The page a document's QR opens: Scan → details and fee → pay to the
 * barangay GCash / InstaPay QR → submit proof → staff verify → unlocked.
 *
 * Payment itself is NOT handled here: proof goes through the existing
 * POST /documents/{id}/payment and only staff Verify (PaymentController)
 * can mark it paid. This page only reads the state and shows the next step,
 * and the document links stay behind the same isPaymentVerified() check
 * as /documents/{id}/download and /preview.
 */
class DocumentClaimController
{
    /**
     * GET /d/{token} — the request's own page, for its owner (or staff).
     */
    public function show(array $params): void
    {
        $request = $this->load((string) ($params['token'] ?? ''));
        $userId  = (int) ($_SESSION['user_id'] ?? 0);
        $isStaff = in_array((string) ($_SESSION['role'] ?? ''), ['admin', 'staff', 'superadmin'], true);

        if ($request === null || (!$isStaff && (int) $request['user_id'] !== $userId)) {
            http_response_code(404);
            flash('error', __('This document QR is not valid for your account. Sign in with the account that made the request.'));
            redirect($isStaff ? '/admin/documents' : '/documents');
        }

        $payment  = DocumentPayment::findByRequest((int) $request['id']);
        $fee      = (float) ($request['fee_amount'] ?? 0);
        $unlocked = DocumentRequest::isPaymentVerified($request);
        $payState = strtoupper((string) ($payment['payment_status'] ?? $request['payment_status'] ?? ''));

        // One word for where the request is, so the page can show one clear next step.
        $closed = in_array((string) $request['status'], ['rejected', 'cancelled'], true);
        $stage  = match (true) {
            $closed                                                    => 'closed',
            $unlocked                                                  => 'unlocked',
            ($request['payment_method'] ?? '') === 'pickup'            => 'pay_at_hall',
            in_array($payState, [DocumentPayment::STATUS_PROOF_SUBMITTED, DocumentPayment::STATUS_UNDER_REVIEW], true) => 'verifying',
            $payState === DocumentPayment::STATUS_PAYMENT_REJECTED     => 'rejected',
            default                                                    => 'pay',
        };

        $view = [
            'request'   => $request,
            'payment'   => $payment,
            'fee'       => $fee,
            'stage'     => $stage,
            'hasFile'   => !empty($request['document_file_name']),
            'qrSvg'     => DocumentQr::svg($request, 220),
            'qrUrl'     => DocumentQr::url($request),
            'gcash'     => [
                'name'   => (string) \App\Models\Setting::get('gcash_receive_name', GcashQr::BUNDLED_NAME),
                'number' => (string) \App\Models\Setting::get('gcash_receive_number', ''),
                'has_qr' => GcashQr::available(),
            ],
            'isStaff'   => $isStaff,
            'pageTitle' => DocumentRequest::label((string) $request['document_type']) . ' — ' . $request['reference_no'],
        ];
        view('resident/document_claim', $view);
    }

    /**
     * GET /documents/{id}/qr — the request's QR as an SVG image (owner or staff),
     * to show, save or print.
     */
    public function qrImage(array $params): void
    {
        $request = DocumentRequest::find((int) ($params['id'] ?? 0));
        $isStaff = in_array((string) ($_SESSION['role'] ?? ''), ['admin', 'staff', 'superadmin'], true);
        if ($request === null || (!$isStaff && (int) $request['user_id'] !== (int) ($_SESSION['user_id'] ?? 0))) {
            http_response_code(404);
            exit('Not found.');
        }

        header('Content-Type: image/svg+xml');
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        if (isset($_GET['download'])) {
            header('Content-Disposition: attachment; filename="document-qr-' . preg_replace('/[^A-Za-z0-9\-]/', '', (string) $request['reference_no']) . '.svg"');
        }
        echo DocumentQr::svg($request, 400);
        exit;
    }

    /** @return array<string,mixed>|null The request, only when the token's signature matches. */
    private function load(string $token): ?array
    {
        $id = DocumentQr::idFrom($token);
        if ($id === null) {
            return null;
        }
        $request = DocumentRequest::find($id);

        return $request !== null && DocumentQr::matches($token, $request) ? $request : null;
    }
}
