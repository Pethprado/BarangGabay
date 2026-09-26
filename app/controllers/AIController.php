<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Ordinance;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\TranslationLog;
use App\Services\AIService;
use App\Services\ManoboAutoTranslator;

class AIController
{
    private AIService $ai;

    public function __construct()
    {
        $this->ai = new AIService();
    }

    /** POST /api/ai/summarize */
    public function summarize(): void
    {
        header('Content-Type: application/json');
        check_csrf();

        $ordinanceId = (int) ($_POST['ordinance_id'] ?? 0);
        if ($ordinanceId <= 0) {
            echo json_encode(['error' => 'Missing ordinance ID.']);
            return;
        }

        $ordinance = Ordinance::find($ordinanceId);
        if (!$ordinance) {
            echo json_encode(['error' => 'Ordinance not found.']);
            return;
        }

        if (!empty($ordinance['ai_summary'])) {
            echo json_encode(['summary' => $ordinance['ai_summary'], 'cached' => true]);
            return;
        }

        $text = $ordinance['description'] ?? $ordinance['title'];
        try {
            $summary = $this->ai->summarizeOrdinance($ordinanceId, $text);
            Ordinance::updateSummary($ordinanceId, $summary);

            // Log AI usage
            $userId = (int) ($_SESSION['user_id'] ?? 0);
            $pdo = db();
            $pdo->prepare('INSERT INTO ai_logs (user_id, ordinance_id, created_at) VALUES (?, ?, NOW())')
                ->execute([$userId ?: null, $ordinanceId]);

            AuditLog::record($userId, 'ai.summarize', "Ordinance #{$ordinanceId} summarized via AI");
            echo json_encode(['summary' => $summary, 'cached' => false]);
        } catch (\Throwable $e) {
            /*
             * This block used to do neither of the two things a failed call
             * owes anybody.
             *
             * It logged NOTHING, while the translate path beside it logged
             * everything — so every "AI service unavailable" on this screen
             * left no trace at all, and the cause could only be guessed at.
             *
             * And it told the reader to "try again later" whatever had
             * happened. When the account has no credit balance, trying again
             * cannot succeed, and the button offering it is a lie that keeps
             * working for as long as the balance stays at zero.
             */
            error_log(sprintf(
                '[%s] ai.summarize (%s) ordinance #%d: %s in %s:%d' . PHP_EOL,
                date('Y-m-d H:i:s'), AIService::classifyFailure($e), $ordinanceId,
                $e->getMessage(), $e->getFile(), $e->getLine()
            ), 3, __DIR__ . '/../../storage/logs/error.log');

            $kind      = AIService::classifyFailure($e);
            $retryable = AIService::isRetryable($e);

            /*
             * Something rather than nothing. The ordinance's own description
             * is written by the barangay, already on file, and is what the
             * reader came for — the AI only ever restated it in simpler
             * words. Offered plainly, and never cached, so it can never be
             * mistaken for the generated summary or block a real one later.
             */
            $fallback = trim(strip_tags((string) ($ordinance['description'] ?? '')));

            http_response_code(503);
            echo json_encode([
                'error'     => t('ai_summary.err_' . $kind),
                'kind'      => $kind,
                'retryable' => $retryable,
                'fallback'  => $fallback !== '' ? $fallback : null,
                'fallback_label' => $fallback !== '' ? t('ai_summary.fallback_label') : null,
                // Staff can see the real reason; residents get the plain
                // sentence above and nothing about our billing.
                'detail'    => $this->isStaff() ? $e->getMessage() : null,
            ]);
        }
    }

    /** Is the current session a back-office one? */
    private function isStaff(): bool
    {
        return \in_array($_SESSION['role'] ?? '', ['staff', 'admin', 'superadmin'], true);
    }

    /** POST /api/ai/translate — translate content text to Manobo for indigenous residents */
    public function translateManobo(): void
    {
        header('Content-Type: application/json');
        check_csrf();

        $text        = trim($_POST['text'] ?? '');
        $contentType = trim($_POST['content_type'] ?? '');
        $contentId   = (int) ($_POST['content_id'] ?? 0);

        if (\strlen($text) < 3) {
            echo json_encode(['error' => 'Walang teksto para isalin.']);
            return;
        }

        $validTypes = ['announcement', 'event', 'ordinance'];
        if (!\in_array($contentType, $validTypes, true) || $contentId <= 0) {
            echo json_encode(['error' => 'Invalid content type or ID.']);
            return;
        }

        // No API key: fall back to the offline dictionary before giving up.
        if (empty(env('ANTHROPIC_API_KEY', ''))) {
            $gloss = $this->offlineGloss($text);
            if ($gloss !== null) {
                echo json_encode($gloss);
                return;
            }

            http_response_code(503);
            echo json_encode([
                'success' => false,
                'error'   => 'Hindi pa naka-configure ang AI key. '
                           . 'Idagdag ang ANTHROPIC_API_KEY sa .env file.',
            ]);
            return;
        }

        // Return cached translation if available
        $cached = TranslationLog::findCached($contentType, $contentId);
        if ($cached) {
            echo json_encode(['success' => true, 'translation' => $cached['translated_text'], 'cached' => true]);
            return;
        }

        try {
            $translation = $this->ai->translateToManobo($text);
            $userId      = (int) ($_SESSION['user_id'] ?? 0);

            TranslationLog::create($userId ?: null, $contentType, $contentId, $text, $translation);
            AuditLog::record($userId, 'ai.translate', "Translated {$contentType} #{$contentId} to Manobo");

            // Also count against the rate-limit table
            db()->prepare('INSERT INTO ai_logs (user_id, ordinance_id, created_at) VALUES (?, NULL, NOW())')
               ->execute([$userId ?: null]);

            echo json_encode(['success' => true, 'translation' => $translation, 'cached' => false]);
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[%s] ai.translate exception: %s in %s:%d',
                date('Y-m-d H:i:s'), $e->getMessage(), $e->getFile(), $e->getLine()
            ), 3, __DIR__ . '/../../storage/logs/error.log');
            // The API is unreachable — out of credits, rate limited, offline.
            // The bundled dictionary needs none of those, so give the resident
            // the words the barangay has actually collected rather than
            // nothing at all. Clearly flagged as a gloss, and never cached.
            $gloss = $this->offlineGloss($text);
            if ($gloss !== null) {
                echo json_encode($gloss + ['ai_error' => $e->getMessage()]);
                return;
            }

            http_response_code(500);
            // Surface Anthropic's actual error message (e.g. "credit balance too low")
            // so the user sees something actionable rather than a generic failure string.
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Word-by-word Manobo gloss from the barangay's own offline dictionaries.
     *
     * The fallback for when the AI is unavailable. Neither dictionary needs
     * network or credits, so this keeps the feature useful on a barangay
     * budget — and it improves every time a speaker adds a word at
     * /admin/manobo or /admin/bisaya.
     *
     * CHANGED: used to call ManoboDictionary::translate() directly, which
     * only tries the WHOLE input as one phrase and then falls to single
     * words — a multi-sentence announcement got shredded into single-word
     * glosses even where a multi-word saying existed in the dictionary. Now
     * uses ManoboAutoTranslator (see its docblock), which segments the whole
     * text with longest-phrase-first matching and a Bisaya fallback, and
     * returns per-segment source tags so the widget can show a Manobo word
     * in plain text, a Bisaya fallback underlined, and an unmatched word
     * flagged — rather than one undifferentiated blob of "some of this is
     * real Manobo, some of it might not be".
     *
     * Two rules it will not break:
     *
     *   1. Never presented as a finished translation. A gloss chain applies no
     *      affixes and no Manobo word order, so the response is flagged
     *      `offline: true` with its coverage, and the widget renders it as a
     *      word-by-word aid.
     *   2. Never written to translation_logs. Caching a gloss would let it
     *      masquerade as the real translation forever, and would block the AI
     *      from producing a proper one once credits exist.
     *
     * @return array<string,mixed>|null Null when nothing matched — an
     *                                  unchanged sentence helps nobody.
     */
    private function offlineGloss(string $text): ?array
    {
        try {
            $result = (new ManoboAutoTranslator())->translateBlock($text);
        } catch (\Throwable $e) {
            error_log('[AIController::offlineGloss] ' . $e->getMessage());
            return null;
        }

        if (($result['success'] ?? false) !== true) {
            return null;
        }

        $segments = $result['segments'] ?? [];
        $glossed  = trim((new ManoboAutoTranslator())->render($result));
        if ($glossed === '') {
            return null;
        }

        $matchedManobo = (int) ($result['matched_manobo'] ?? 0);
        $matchedBisaya = (int) ($result['matched_bisaya'] ?? 0);
        $matched       = $matchedManobo + $matchedBisaya;
        $total         = $matched + (int) ($result['unmatched'] ?? 0);

        // Worth showing when at least two words resolved, or when a short
        // sentence is meaningfully covered. A realistic barangay sentence runs
        // ~13 words and the dictionaries reach two of them — a flat
        // percentage floor rejected exactly the case the feature exists for.
        // One stray match inside a long announcement is still suppressed.
        if ($matched < 2 && !($total > 0 && $matched / $total >= 0.2)) {
            return null;
        }

        $missing = [];
        foreach ($segments as $segment) {
            if (($segment['source'] ?? '') === 'none') {
                $missing[] = $segment['text'];
            }
        }

        return [
            'success'        => true,
            'offline'        => true,
            'translation'    => $glossed,
            'cached'         => false,
            'matched'        => $matched,
            'matched_manobo' => $matchedManobo,
            'matched_bisaya' => $matchedBisaya,
            'total'          => $total,
            'missing'        => array_values(array_slice(array_unique($missing), 0, 12)),
            // ADDED: per-word source tags, so the widget can render Manobo
            // in plain text, a Bisaya fallback underlined, and an unmatched
            // word flagged — instead of one undifferentiated string.
            'segments'       => array_map(
                static fn (array $s): array => [
                    'text'    => (string) $s['text'],
                    'display' => (string) $s['display'],
                    'source'  => (string) $s['source'],
                ],
                $segments
            ),
        ];
    }

    /** POST /api/ai/chat */
    public function chat(): void
    {
        header('Content-Type: application/json');
        check_csrf();

        $question = trim($_POST['question'] ?? '');
        if (\strlen($question) < 3) {
            echo json_encode(['error' => t('ai_chat.invalid_question')]);
            return;
        }

        $recentPosts = Announcement::getPublished(5);
        try {
            $answer = $this->ai->chat($question, $recentPosts);

            // Log to ai_logs so RateLimitMiddleware counts chat requests correctly.
            $userId = (int) $_SESSION['user_id'];
            db()->prepare('INSERT INTO ai_logs (user_id, ordinance_id, created_at) VALUES (?, NULL, NOW())')
               ->execute([$userId ?: null]);

            AuditLog::record($userId, 'ai.chat', 'AI chat question asked');
            echo json_encode(['answer' => $answer]);
        } catch (\Throwable $e) {
            error_log('[AIController::chat] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => t('ai_chat.service_unavailable')]);
        }
    }
}