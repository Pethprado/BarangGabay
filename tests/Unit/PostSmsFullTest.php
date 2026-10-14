<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PostSms;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';

/**
 * "Send a Post by SMS" texts the whole post (title, content, link), not just
 * the title; a resident who taps the link while signed out returns to it.
 */
class PostSmsFullTest extends TestCase
{
    public function testAnnouncementCarriesTitleWholeContentAndLink(): void
    {
        $msg = PostSms::full('announcement', [
            'id' => 7, 'slug' => 'water-interruption', 'title' => 'Water interruption',
            'body' => '<p>No water on <strong>Monday</strong> 8 AM – 5 PM.</p><ul><li>Store water</li><li>Boil before drinking</li></ul>',
        ]);

        $this->assertStringContainsString('Water interruption', $msg);
        $this->assertStringContainsString("No water on Monday 8 AM - 5 PM.\n- Store water\n- Boil before drinking", $msg);
        $this->assertStringContainsString('Basahin dito: ' . app_url('announcements/water-interruption'), $msg);
        $this->assertStringNotContainsString('<', $msg, 'no HTML reaches the phone');
        $this->assertTrue(PostSms::isGsm($msg), 'dashes are made plain so parts stay 153 characters');
    }

    public function testEventHasDateVenueDescriptionAndLink(): void
    {
        $msg = PostSms::full('event', [
            'id' => 3, 'slug' => 'drill', 'title' => 'Earthquake drill', 'venue' => 'Covered Court',
            'event_date' => '2026-11-07 09:00:00', 'end_date' => '2026-11-07 11:00:00',
            'description' => '<p>Bring water.</p>',
        ]);

        $this->assertStringContainsString("Petsa: Nov 07, 2026 9:00 AM - 11:00 AM\nLugar: Covered Court", $msg);
        $this->assertStringContainsString('Bring water.', $msg);
        $this->assertStringContainsString(app_url('events/drill'), $msg);
    }

    public function testOrdinanceFallsBackToSummaryAndLinksById(): void
    {
        $msg = PostSms::full('ordinance', [
            'id' => 12, 'ordinance_no' => 'Ord. 2026-003', 'title' => 'Curfew',
            'description' => '', 'ai_summary' => 'Minors home by 10 PM.',
        ]);

        $this->assertStringContainsString('Ord. 2026-003 - Curfew', $msg);
        $this->assertStringContainsString('Minors home by 10 PM.', $msg);
        $this->assertStringContainsString(app_url('ordinances/12'), $msg);
    }

    public function testVeryLongContentIsShortenedButTheLinkAlwaysArrives(): void
    {
        $msg = PostSms::full('announcement', [
            'id' => 1, 'slug' => 'long', 'title' => 'Long',
            'body' => '<p>' . str_repeat('Mahabang paalala para sa lahat. ', 200) . '</p>',
        ]);

        $this->assertLessThanOrEqual(PostSms::MAX_LENGTH, mb_strlen($msg));
        $this->assertStringContainsString('...', $msg);
        $this->assertStringContainsString(app_url('announcements/long'), $msg);
    }

    public function testLoginReturnsOnlyToPostPages(): void
    {
        $saved = [$_SERVER['REQUEST_URI'] ?? null, $_SERVER['REQUEST_METHOD'] ?? null];
        $base  = rtrim((string) parse_url(get_base_url(), PHP_URL_PATH), '/');
        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI']    = $base . '/events/earthquake-drill?ref=sms';
            remember_intended_url();
            $this->assertSame('/events/earthquake-drill', pull_intended_url());
            $this->assertNull(pull_intended_url(), 'used once');

            $_SERVER['REQUEST_URI'] = $base . '/admin/users';
            remember_intended_url();
            $this->assertNull(pull_intended_url(), 'only post pages are remembered');
        } finally {
            [$_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']] = $saved;
            unset($_SESSION['intended_url']);
        }
    }
}
