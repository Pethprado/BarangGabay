<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/helpers.php';

/**
 * Sample post covers are openly licensed Wikimedia Commons photos: each one
 * must ship with its file and a credit (author + licence), and the sample
 * content must point only at covers that exist.
 */
class ImageCreditTest extends TestCase
{
    private const DIR = __DIR__ . '/../../public/assets/images/posts/';

    public function testEveryBundledCoverHasAFileAndACredit(): void
    {
        $content = require __DIR__ . '/../../tools/current-content/content.php';
        $covers  = [];
        array_walk_recursive($content, function ($value, $key) use (&$covers): void {
            if ($key === 'cover') {
                $covers[] = $value;
            }
        });

        $this->assertNotEmpty($covers);
        foreach ($covers as $cover) {
            $this->assertFileExists(self::DIR . $cover);
            $credit = image_credit('assets/images/posts/' . $cover);
            $this->assertNotNull($credit, "$cover has no credit");
            $this->assertNotSame('', $credit['license']);
            $this->assertStringStartsWith('https://commons.wikimedia.org/', $credit['source']);
        }
    }

    public function testUploadsNeverInheritACreditByName(): void
    {
        $this->assertNull(image_credit('uploads/announcements/ip-month.jpg'));
        $this->assertNull(image_credit('assets/images/posts/not-a-real-photo.jpg'));
    }
}
