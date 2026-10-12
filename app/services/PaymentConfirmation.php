<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DocumentPayment;
use App\Models\DocumentRequest;

/**
 * The ONE place a PayMongo payment becomes PAID.
 *
 * Every path — the webhook (authoritative), the resident's return page, the
 * status poll and the staff "Check PayMongo Status" button — ends here, so the
 * rules are applied identically:
 *
 *   1. PayMongo itself reports the payment as `paid`
 *      (a return to success_url, or anything the browser says, never counts);
 *   2. currency is PHP;
 *   3. amount in centavos equals the official fee stored on the payment row
 *      (set server-side from document_fees when the request was filed).
 *
 * A paid-but-wrong-amount payment is moved to manual review, never to PAID.
 * Settling is an atomic conditional UPDATE, so duplicates are harmless.
 */
final class PaymentConfirmation
{
    public const OUTCOME_CONFIRMED = 'confirmed';
    public const OUTCOME_ALREADY   = 'already_paid';
    public const OUTCOME_MISMATCH  = 'manual_review';
    public const OUTCOME_NOT_PAID  = 'not_paid';
    public const OUTCOME_EXPIRED   = 'expired';
    public const OUTCOME_ERROR     = 'error';

    /**
     * Pure check of one PayMongo payment against the official fee.
     *
     * @param array{status:string, amount:int, currency:string} $pm  normalised PayMongo payment
     * @return array{ok:bool, paid:bool, reason:?string}
     */
    public static function evaluate(float $officialFeePesos, array $pm): array
    {
        if (($pm['status'] ?? '') !== 'paid') {
            return ['ok' => false, 'paid' => false, 'reason' => 'PayMongo status is "' . ($pm['status'] ?? 'unknown') . '".'];
        }
        if (strtoupper((string) ($pm['currency'] ?? '')) !== 'PHP') {
            return ['ok' => false, 'paid' => true, 'reason' => 'Currency ' . ($pm['currency'] ?? '?') . ' is not PHP.'];
        }
        $expected = PayMongoService::toCentavos($officialFeePesos);
        if ((int) ($pm['amount'] ?? -1) !== $expected) {
            return ['ok' => false, 'paid' => true, 'reason' => sprintf(
                'Amount paid ₱%.2f does not match the official fee ₱%.2f.', ((int) ($pm['amount'] ?? 0)) / 100, $expected / 100
            )];
        }
        return ['ok' => true, 'paid' => true, 'reason' => null];
    }

    /**
     * Apply one PayMongo payment to a BarangGabay payment row.
     *
     * @param array $payment  DocumentPayment::find() row
     * @param array $pm       PayMongoService::normalizePayment() result
     */
    public static function confirm(array $payment, array $pm, ?string $eventId = null, ?string $checkoutId = null): string
    {
        if (in_array((string) $payment['payment_status'], DocumentPayment::SETTLED_STATES, true)) {
            return self::OUTCOME_ALREADY;
        }

        $verdict = self::evaluate((float) $payment['amount_due'], $pm);
        if (!$verdict['paid']) {
            return self::OUTCOME_NOT_PAID;
        }
        if (!$verdict['ok']) {
            $moved = DocumentPayment::transitionUnsettled(
                (int) $payment['id'], DocumentPayment::STATUS_UNDER_REVIEW, 'PAYMENT_AMOUNT_MISMATCH', null,
                'PayMongo payment ' . $pm['id'] . ': ' . $verdict['reason']
            );
            if ($moved) {
                self::notifyBackOffice($payment, 'Payment needs manual review (' . $payment['request_ref'] . ')',
                    'PayMongo reported a payment that does not match the official fee. ' . $verdict['reason']);
            }
            return self::OUTCOME_MISMATCH;
        }

        $changed = DocumentPayment::markPaymongoPaid(
            (int) $payment['id'], $pm['id'], $pm['payment_intent_id'] ?? null,
            ((int) $pm['amount']) / 100, $pm['source_type'] ?: 'gcash', $eventId, $checkoutId
        );
        if (!$changed) {
            return self::OUTCOME_ALREADY;
        }

        self::afterConfirmed($payment, (string) ($pm['source_type'] ?: 'gcash'));
        return self::OUTCOME_CONFIRMED;
    }

    /**
     * Ask PayMongo for the real state of a payment's checkout session and act
     * on it. Used by the return page, the status poll and the staff button.
     *
     * @return array{outcome:string, paymongo_status:?string, message:string}
     */
    public static function reconcile(array $payment, ?int $actorId = null): array
    {
        $checkoutId = (string) ($payment['paymongo_checkout_id'] ?? '');
        if ($checkoutId === '') {
            return ['outcome' => self::OUTCOME_NOT_PAID, 'paymongo_status' => null,
                    'message' => 'No PayMongo checkout has been started for this payment yet.'];
        }

        $session = PayMongoService::retrieveCheckoutSession($checkoutId);
        if (!$session['success']) {
            return ['outcome' => self::OUTCOME_ERROR, 'paymongo_status' => null,
                    'message' => 'Could not reach PayMongo: ' . $session['error']];
        }

        $outcome = self::OUTCOME_NOT_PAID;
        foreach ($session['payments'] as $pm) {
            if ($pm['status'] === 'paid') {
                $outcome = self::confirm($payment, $pm, null, $checkoutId);
                break;
            }
        }

        if ($outcome === self::OUTCOME_NOT_PAID && $session['status'] === 'expired') {
            DocumentPayment::transitionUnsettled((int) $payment['id'], DocumentPayment::STATUS_EXPIRED,
                'PAYMENT_EXPIRED', $actorId, 'PayMongo checkout session ' . $checkoutId . ' expired unpaid.');
            $outcome = self::OUTCOME_EXPIRED;
        }

        if ($actorId !== null) {
            DocumentPayment::logAudit((int) $payment['request_id'], (int) $payment['id'], $actorId,
                'PAYMENT_STATUS_RECONCILED', (string) $payment['payment_status'], null,
                sprintf('Checked PayMongo: session %s is "%s"; result: %s.', $checkoutId, $session['status'], $outcome));
        }

        $messages = [
            self::OUTCOME_CONFIRMED => 'PayMongo confirmed the payment. It is now marked PAID.',
            self::OUTCOME_ALREADY   => 'This payment was already settled.',
            self::OUTCOME_MISMATCH  => 'PayMongo shows a payment, but the amount or currency does not match. It was moved to manual review.',
            self::OUTCOME_EXPIRED   => 'The checkout session expired without payment. The resident can start a new one.',
            self::OUTCOME_NOT_PAID  => 'PayMongo has no successful payment for this checkout yet (session: ' . $session['status'] . ').',
        ];

        return ['outcome' => $outcome, 'paymongo_status' => $session['status'], 'message' => $messages[$outcome]];
    }

    /** Resident + back-office notifications after a confirmed payment. */
    private static function afterConfirmed(array $payment, string $source): void
    {
        $ref   = (string) $payment['request_ref'];
        $label = DocumentRequest::label((string) $payment['document_type']);

        try {
            (new NotificationService())->notifyUser(
                (int) $payment['user_id'], 'system',
                'Payment received — ' . $ref,
                sprintf('Natanggap ang inyong bayad na ₱%.2f via %s para sa %s (%s). Ipoproseso na ang inyong dokumento.',
                    (float) $payment['amount_due'], strtoupper($source), $label, $ref),
                0, 'document_payment'
            );
        } catch (\Throwable $e) {
            error_log('[PaymentConfirmation] resident notification: ' . $e->getMessage());
        }

        self::notifyBackOffice($payment, 'Paid request ready for processing (' . $ref . ')',
            sprintf('%s paid ₱%.2f via PayMongo %s for %s. Verified automatically by PayMongo.',
                (string) ($payment['full_name'] ?? 'A resident'), (float) $payment['amount_due'], strtoupper($source), $label));

        $phone = trim((string) ($payment['phone'] ?? ''));
        if ($phone !== '') {
            try {
                (new OneWaySmsService())->send($phone,
                    sprintf('BARANGGABAY: Natanggap ang bayad para sa %s. Ipoproseso na ang inyong dokumento.', $ref),
                    'payment_verified', (int) $payment['id']);
            } catch (\Throwable $e) {
                error_log('[PaymentConfirmation] SMS: ' . $e->getMessage());
            }
        }

        AuditLog::record(null, 'payment.confirmed', sprintf('PayMongo payment confirmed for %s (₱%.2f)', $ref, (float) $payment['amount_due']));
    }

    private static function notifyBackOffice(array $payment, string $title, string $message): void
    {
        try {
            (new NotificationService())->notifyBackOffice('notify_content', 'system', $title, $message, 0, 'document_payment', (int) $payment['user_id']);
        } catch (\Throwable $e) {
            error_log('[PaymentConfirmation] back-office notification: ' . $e->getMessage());
        }
    }
}
