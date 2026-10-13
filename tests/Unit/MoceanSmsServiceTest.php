<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MoceanSmsService;
use App\Services\OneWaySmsService;
use App\Services\SmsGateway;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';

/**
 * MoceanAPI against a mocked HTTP client: no real SMS, no credit spent.
 * sms_logs rows written by these tests are removed again.
 */
class MoceanSmsServiceTest extends TestCase
{
    private const MSG = 'zz-mocean-unit-test';

    protected function tearDown(): void
    {
        try {
            db()->prepare('DELETE FROM sms_logs WHERE message = ?')->execute([self::MSG]);
        } catch (\Throwable $e) {
        }
    }

    /** @param list<Response> $responses */
    private function service(array $responses, array $config, array &$history): MoceanSmsService
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        return new MoceanSmsService(new Client(['handler' => $stack, 'http_errors' => false]), $config);
    }

    public function testSendsWithBearerTokenAndPhilippineNumbers(): void
    {
        $history = [];
        $sms = $this->service([
            new Response(202, [], '{"messages":[{"status":0,"receiver":"639171234567","msgid":"a1"},{"status":0,"receiver":"639281234567","msgid":"a2"}]}'),
        ], ['MOCEAN_API_TOKEN' => 'apit-test', 'MOCEAN_SENDER_ID' => 'BarangGabay'], $history);

        $res = $sms->sendBulk(['0917 123 4567', '+639281234567', 'not a number'], self::MSG, 'manual');

        $this->assertSame(2, $res['sent']);
        $this->assertSame(0, $res['failed']);
        $request = $history[0]['request'];
        $this->assertSame('https://rest.moceanapi.com/rest/2/sms', (string) $request->getUri());
        $this->assertSame('Bearer apit-test', $request->getHeaderLine('Authorization'));
        parse_str((string) $request->getBody(), $form);
        $this->assertSame('639171234567,639281234567', $form['mocean-to']);
        $this->assertSame('BarangGabay', $form['mocean-from']);
        $this->assertSame('JSON', $form['mocean-resp-format']);
        $this->assertArrayNotHasKey('mocean-api-secret', $form, 'the token path never sends key/secret');
    }

    public function testFallsBackToKeyAndSecret(): void
    {
        $history = [];
        $sms = $this->service([new Response(202, [], '{"messages":[{"status":0,"receiver":"639171234567","msgid":"b1"}]}')],
            ['MOCEAN_API_KEY' => 'k', 'MOCEAN_API_SECRET' => 's'], $history);

        $this->assertTrue($sms->send('09171234567', self::MSG)['success']);
        parse_str((string) $history[0]['request']->getBody(), $form);
        $this->assertSame('k', $form['mocean-api-key']);
        $this->assertSame('s', $form['mocean-api-secret']);
        $this->assertSame('', $history[0]['request']->getHeaderLine('Authorization'));
    }

    public function testEachReceiverGetsItsOwnResult(): void
    {
        $history = [];
        $sms = $this->service([new Response(202, [], '{"messages":[{"status":0,"receiver":"639171234567","msgid":"c1"},{"status":4,"receiver":"639281234567","err_msg":"whitelist"}]}')],
            ['MOCEAN_API_TOKEN' => 't'], $history);

        $res = $sms->sendBulk(['09171234567', '09281234567'], self::MSG);
        $this->assertSame(1, $res['sent']);
        $this->assertSame(1, $res['failed']);
        $this->assertStringContainsString('whitelisted', (string) $res['error'], 'trial accounts: explained in plain words');
    }

    public function testWrongTokenFailsEveryNumberWithAClearReason(): void
    {
        $history = [];
        $sms = $this->service([new Response(401, [], '{"messages":[{"status":1,"err_msg":"Authorization failed"}]}')],
            ['MOCEAN_API_TOKEN' => 'wrong'], $history);

        $res = $sms->sendBulk(['09171234567', '09281234567'], self::MSG);
        $this->assertSame(0, $res['sent']);
        $this->assertSame(2, $res['failed']);
        $this->assertStringContainsString('MOCEAN_API_TOKEN', (string) $res['error']);
    }

    public function testNotConfiguredAndTestMode(): void
    {
        $history = [];
        $this->assertFalse((new MoceanSmsService(null, ['MOCEAN_API_TOKEN' => '']))->isConfigured());

        $sms = $this->service([], ['MOCEAN_API_TOKEN' => 't', 'MOCEAN_TEST_MODE' => 'true'], $history);
        $res = $sms->send('09171234567', self::MSG);
        $this->assertTrue($res['success']);
        $this->assertTrue($res['test_mode']);
        $this->assertSame([], $history, 'test mode never calls Mocean');
    }

    public function testSavingATokenChecksItWithMoceanAndStoresItEncrypted(): void
    {
        $this->assertNotNull(MoceanSmsService::saveToken('not-a-token', '', 0), 'wrong format is refused');

        $previous  = \App\Models\Setting::get(MoceanSmsService::SETTING_TOKEN, '');
        $hadSecret = $_ENV['JWT_SECRET'] ?? null;
        $_ENV['JWT_SECRET'] ??= 'unit-test-secret';   // tests do not load .env
        try {
            $rejecting = new Client(['handler' => HandlerStack::create(new MockHandler([
                new Response(401, [], '{"status":1,"err_msg":"Authorization failed"}'),
            ])), 'http_errors' => false]);
            $this->assertNotNull(MoceanSmsService::saveToken('apit-wrongwrongwrong', 'BarangGabay', 0, $rejecting), 'a token Mocean refuses is not saved');

            $accepting = new Client(['handler' => HandlerStack::create(new MockHandler([
                new Response(200, [], '{"status":0,"value":19}'),
            ])), 'http_errors' => false]);
            $this->assertNull(MoceanSmsService::saveToken('apit-goodgoodgoodgood', 'BarangGabay', 0, $accepting));

            \App\Models\Setting::flush();
            $stored = (string) \App\Models\Setting::get(MoceanSmsService::SETTING_TOKEN, '');
            $this->assertStringNotContainsString('apit-good', $stored, 'stored encrypted, never in plain text');
            $this->assertSame('apit-goodgoodgoodgood', \App\Services\SecretBox::decrypt($stored));
        } finally {
            \App\Models\Setting::set(MoceanSmsService::SETTING_TOKEN, (string) $previous);
            \App\Models\Setting::flush();
            if ($hadSecret === null) { unset($_ENV['JWT_SECRET']); } else { $_ENV['JWT_SECRET'] = $hadSecret; }
        }
    }

    public function testGatewayPrefersMoceanWhenConfigured(): void
    {
        $saved = [$_ENV['MOCEAN_API_TOKEN'] ?? null, $_ENV['SMS_PROVIDER'] ?? null];
        try {
            $_ENV['SMS_PROVIDER'] = '';
            $_ENV['MOCEAN_API_TOKEN'] = 'apit-x';
            $this->assertInstanceOf(MoceanSmsService::class, SmsGateway::make());

            $_ENV['SMS_PROVIDER'] = 'oneway';
            $this->assertInstanceOf(OneWaySmsService::class, SmsGateway::make());
        } finally {
            foreach (['MOCEAN_API_TOKEN' => $saved[0], 'SMS_PROVIDER' => $saved[1]] as $k => $v) {
                if ($v === null) { unset($_ENV[$k]); } else { $_ENV[$k] = $v; }
            }
        }
    }
}
