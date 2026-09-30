<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Adapter maintaining backward compatibility for legacy call sites,
 * delegating all SMS requests to the primary OneWaySmsService.
 */
class SemaphoreSmsService
{
    private OneWaySmsService $oneWay;

    public function __construct()
    {
        $this->oneWay = new OneWaySmsService();
    }

    public function isTestMode(): bool
    {
        return $this->oneWay->isTestMode();
    }

    public function isConfigured(): bool
    {
        return $this->oneWay->isConfigured();
    }

    public function send(string $number, string $message, string $type = 'general', ?int $refId = null): array
    {
        return $this->oneWay->send($number, $message, $type, $refId);
    }

    public function sendBulk(array $numbers, string $message, string $type = 'general', ?int $refId = null): array
    {
        return $this->oneWay->sendBulk($numbers, $message, $type, $refId);
    }

    public function getBalance(): ?array
    {
        return $this->oneWay->getBalance();
    }

    public function cleanPhone(string $number): ?string
    {
        return $this->oneWay->cleanPhone($number);
    }
}
