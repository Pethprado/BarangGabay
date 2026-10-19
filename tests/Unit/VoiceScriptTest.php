<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PostScript;
use App\Services\VoiceScript;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';

/**
 * The Voice Training Recording Script lists every sentence and word residents
 * hear (numbers included), never HTML, and a translation stored as HTML is
 * read as clean sentences rather than with its tags spoken.
 */
class VoiceScriptTest extends TestCase
{
    public function testNumbersAreSpeakableButUrlPiecesAreNot(): void
    {
        $this->assertTrue(VoiceScript::speakable('2026'));
        $this->assertTrue(VoiceScript::speakable('nobyembre'));
        $this->assertFalse(VoiceScript::speakable('https'));
    }

    public function testHtmlTranslationIsReadAsCleanSentences(): void
    {
        $row = [
            'id' => 1, 'source_lang' => 'fil', 'title' => 'Titulo', 'title_manobo' => 'Ulohan',
            'body' => '<p>Una.</p>', 'body_manobo' => '<p>Una nga pulong.</p><h3>Pahinumdum</h3><ul><li>Limpyohi sa Oktubre 30.</li></ul>',
            'category' => 'general', 'urgency' => 'normal', 'published_at' => '2026-10-13 08:00:00', 'created_at' => '2026-10-13 08:00:00',
        ];
        $script = PostScript::build('announcement', $row, 'msm');
        $says   = array_column($script['chunks'], 'say');

        foreach ($says as $say) {
            $this->assertDoesNotMatchRegularExpression('/<\/?[a-z]/i', $say, 'no tag is ever spoken');
        }
        $this->assertContains('Pahinumdum', array_map('trim', $says), 'a heading is its own sentence');
    }

    public function testScriptShapeForTheHub(): void
    {
        $script = VoiceScript::build('msm');
        foreach (['posts', 'sentences', 'sentences_covered', 'words', 'words_recorded', 'numbers'] as $key) {
            $this->assertArrayHasKey($key, $script['totals']);
        }
        foreach ($script['posts'] as $post) {
            foreach ($post['sentences'] as $sentence) {
                $this->assertDoesNotMatchRegularExpression('/<\/?[a-z]/i', $sentence['text']);
            }
        }
    }
}
