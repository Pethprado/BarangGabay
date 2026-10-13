<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Picks the SMS provider every part of the app sends through.
 *
 *   SMS_PROVIDER=mocean | oneway   forces one
 *   (unset)                        MoceanAPI when MOCEAN_API_TOKEN (or key +
 *                                  secret) is set, otherwise OneWaySMS
 */
final class SmsGateway
{
    public static function make(): SmsSender
    {
        $forced = strtolower(trim((string) ($_ENV['SMS_PROVIDER'] ?? getenv('SMS_PROVIDER') ?: '')));

        if ($forced === 'oneway' || $forced === 'onewaysms') {
            return new OneWaySmsService();
        }

        $mocean = new MoceanSmsService();
        if ($forced === 'mocean' || $mocean->isConfigured()) {
            return $mocean;
        }

        return new OneWaySmsService();
    }
}
