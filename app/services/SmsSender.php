<?php
declare(strict_types=1);

namespace App\Services;

/**
 * What every SMS provider in BarangGabay offers. Pick one with SmsGateway::make().
 */
interface SmsSender
{
    /** Short provider name for the admin SMS page, e.g. "MoceanAPI". */
    public function providerName(): string;

    public function isConfigured(): bool;

    public function isTestMode(): bool;

    public function getSenderId(): string;

    /** A Philippine mobile number as 639XXXXXXXXX, or null when it is not one. */
    public function cleanPhone(string $number): ?string;

    /** @return array{success:bool, response?:mixed, error?:string|null, test_mode?:bool} */
    public function send(string $number, string $message, string $type = 'general', ?int $refId = null): array;

    /** @return array{success:bool, sent:int, failed:int, error?:string} */
    public function sendBulk(array $numbers, string $message, string $type = 'general', ?int $refId = null): array;

    /** @return array<string,mixed>|null */
    public function getBalance(): ?array;
}
