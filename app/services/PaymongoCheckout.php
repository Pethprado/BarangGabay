<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\DocumentPayment;
use App\Models\DocumentRequest;

/**
 * Starts (or safely reuses) a PayMongo hosted checkout for a document payment.
 *
 * Shared by "file a request" and the "Pay with GCash" button so both behave
 * identically. The amount is always the server-side fee on the payment row —
 * nothing the browser sends is used. A still-open session is reused, so a
 * double click or page refresh never opens two checkouts for one request.
 */
final class PaymongoCheckout
{
    /** PayMongo checkout sessions stay open for about an hour; reuse well inside that. */
    private const REUSE_MINUTES = 45;

    /**
     * @return array{success:bool, checkout_url:?string, reused:bool, already_paid:bool, error:?string}
     */
    public static function start(int $paymentId, int $actorId): array
    {
        $result = static fn (bool $ok, ?string $url = null, ?string $error = null, bool $reused = false, bool $paid = false): array =>
            ['success' => $ok, 'checkout_url' => $url, 'reused' => $reused, 'already_paid' => $paid, 'error' => $error];

        $payment = DocumentPayment::find($paymentId);
        $request = $payment ? DocumentRequest::find((int) $payment['request_id']) : null;
        if (!$payment || !$request) {
            return $result(false, null, 'Payment record not found.');
        }

        $status = (string) $payment['payment_status'];
        if (in_array($status, DocumentPayment::SETTLED_STATES, true)) {
            return $result(false, null, 'This request is already paid.', false, true);
        }
        if (in_array($status, DocumentPayment::FILTER_GROUPS['manual_review'], true)) {
            return $result(false, null, 'Your payment is being reviewed by the barangay. Please wait before paying again.');
        }
        if ($status === DocumentPayment::STATUS_PAY_AT_PICKUP) {
            return $result(false, null, 'This request is set to be paid at the Barangay Hall.');
        }
        if (in_array((string) $request['status'], ['rejected', 'cancelled'], true)) {
            return $result(false, null, 'This request was ' . $request['status'] . ' and can no longer be paid.');
        }

        // The official fee, as stored server-side when the request was filed.
        $amountCentavos = PayMongoService::toCentavos($payment['amount_due']);
        if ($amountCentavos !== PayMongoService::toCentavos($request['fee_amount'] ?? 0)) {
            error_log(sprintf('[PaymongoCheckout] fee mismatch on payment %d (payment %s vs request %s)',
                $paymentId, $payment['amount_due'], $request['fee_amount']));
            return $result(false, null, 'The fee on this request is inconsistent. Please contact the Barangay Hall.');
        }
        if ($amountCentavos <= 0) {
            return $result(false, null, 'This document is free — no payment is needed.');
        }

        // Reuse an open session (double click, refresh, back button).
        $existingId = (string) ($payment['paymongo_checkout_id'] ?? '');
        $createdAt  = strtotime((string) ($payment['checkout_created_at'] ?? '')) ?: 0;
        if ($existingId !== '' && $createdAt > time() - self::REUSE_MINUTES * 60) {
            $session = PayMongoService::retrieveCheckoutSession($existingId);
            if ($session['success']) {
                foreach ($session['payments'] as $pm) {
                    if ($pm['status'] === 'paid') {
                        PaymentConfirmation::confirm($payment, $pm, null, $existingId);
                        return $result(false, null, 'This request is already paid.', false, true);
                    }
                }
                if ($session['status'] === 'active' && !empty($payment['checkout_url'])) {
                    return $result(true, (string) $payment['checkout_url'], null, true);
                }
            }
        }

        $label   = DocumentRequest::label((string) $request['document_type']);
        $session = PayMongoService::createCheckoutSession([
            'amount_centavos'  => $amountCentavos,
            'item_name'        => $label,
            'description'      => sprintf('BarangGabay — %s (%s)', $label, $request['reference_no']),
            'reference_number' => (string) $payment['payment_ref'],
            'success_url'      => route('documents/payment/success') . '?request_id=' . (int) $request['id'],
            'cancel_url'       => route('documents/payment/cancel') . '?request_id=' . (int) $request['id'],
            'metadata'         => [
                'document_request_id'       => (string) $request['id'],
                'resident_id'               => (string) $request['user_id'],
                'document_type'             => (string) $request['document_type'],
                'internal_reference_number' => (string) $request['reference_no'],
                'payment_ref'               => (string) $payment['payment_ref'],
                'system'                    => 'BarangGabay',
            ],
        ]);

        if (!$session['success']) {
            DocumentPayment::logAudit((int) $request['id'], $paymentId, $actorId, 'CHECKOUT_FAILED', $status, null,
                'PayMongo checkout could not be created: ' . $session['error']);
            return $result(false, null, (string) $session['error']);
        }

        DocumentPayment::setPaymongoCheckout($paymentId, (string) $session['checkout_id'], (string) $session['checkout_url']);
        DocumentPayment::logAudit((int) $request['id'], $paymentId, $actorId, 'CHECKOUT_CREATED', $status, DocumentPayment::STATUS_PENDING,
            sprintf('PayMongo checkout %s for ₱%.2f (%s mode).', $session['checkout_id'], $amountCentavos / 100, PayMongoService::getMode()));

        try {
            (new NotificationService())->notifyUser((int) $request['user_id'], 'system',
                'GCash checkout ready — ' . $request['reference_no'],
                sprintf('Nabuo ang GCash checkout para sa %s (₱%.2f). Kung hindi natapos, maaari itong ituloy mula sa Aking Mga Kahilingan.', $label, $amountCentavos / 100),
                0, 'document_payment');
        } catch (\Throwable $e) {
            error_log('[PaymongoCheckout] notification: ' . $e->getMessage());
        }

        return $result(true, (string) $session['checkout_url']);
    }
}
