<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Ordinance;
use App\Models\TranslationAttempt;
use App\Services\PostAudioService;
use App\Services\PostScript;
use App\Services\SpokenText;
use App\Services\TranslationRetryRunner;

/**
 * Builds the spoken script for a piece of text on demand.
 *
 * The resident detail pages do NOT go through here — their script is built
 * during the page render and embedded in the HTML, so pressing "Pakinggan"
 * starts speaking with no round trip and works on a phone with a weak signal.
 *
 * This endpoint exists for the two cases where the text is not known at render
 * time:
 *
 *   - Staff previewing a post they are still typing, before it is saved.
 *   - A resident who has just generated an ordinance summary with the AI
 *     button and now wants to hear it.
 *
 * It calls nothing external, writes nothing, and reads no records: it is a pure
 * string transformation of text the caller already has in front of them. That
 * is why 'auth' alone is the right gate — there is nothing here to disclose.
 */
class VoiceController
{
    /**
     * Longest input accepted, in characters.
     *
     * Generous next to SpokenText::MAX_CHARS, which does the real capping, but
     * it keeps a pasted PDF from being tokenised before being thrown away.
     */
    private const MAX_INPUT = 20000;

    /**
     * POST /api/voice/script
     *
     * Accepts title / lead / body / tail (lead and tail newline-separated) and
     * returns the chunk list the player speaks, plus the voice language to ask
     * the device for.
     */
    public function script(): void
    {
        header('Content-Type: application/json');
        check_csrf();

        $locale = (string) ($_POST['locale'] ?? '');
        if (!array_key_exists($locale, available_locales())) {
            $locale = current_locale();
        }

        $title = $this->clean($_POST['title'] ?? '');
        $body  = $this->clean($_POST['body']  ?? '');

        if ($title === '' && $body === '') {
            echo json_encode([
                'success' => false,
                'error'   => t('voice_reader.err_empty'),
            ]);
            return;
        }

        $script = SpokenText::script([
            'locale'       => $locale,
            'title'        => $title,
            'lead'         => $this->lines($_POST['lead'] ?? ''),
            'body'         => $body,
            // Announcement bodies are Quill HTML; everything else is plain. The
            // caller knows which it is holding, and HTML is the safe default —
            // stripping tags from text that has none changes nothing.
            'body_is_html' => ($_POST['body_is_html'] ?? '1') !== '0',
            'tail'         => $this->lines($_POST['tail'] ?? ''),
            'label'        => 'voice preview (' . $locale . ')',
        ]);

        echo json_encode([
            'success'     => true,
            'chunks'      => $script['chunks'],
            'chars'       => $script['chars'],
            'truncated'   => $script['truncated'],
            'speech_lang' => SpokenText::speechLang($locale),
            'voices'      => SpokenText::voiceCandidates($locale),
        ]);
    }

    /**
     * POST /api/admin/voice/generate
     *
     * Build (or rebuild) the cached narration for one post. Publishing already
     * does this automatically; this endpoint exists for the two cases that are
     * not a publish: regenerating after an edit left a track stale, and
     * retrying when the provider had a bad minute.
     *
     * Deliberately staff-only and deliberately not reachable from a resident's
     * page. Generating audio costs the barangay characters from a metered
     * allowance, so the decision to spend them belongs to the people who
     * publish, not to whoever happens to load a page.
     *
     * Params: type, id, and an optional locale (all available languages when
     * it is omitted).
     */
    public function generate(): void
    {
        header('Content-Type: application/json');
        check_csrf();

        $type = (string) ($_POST['type'] ?? '');
        $id   = (int) ($_POST['id'] ?? 0);
        $row  = $this->row($type, $id);

        if ($row === null) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => t('voice_reader.gen_not_found')]);
            return;
        }

        $service = new PostAudioService();
        if (!$service->isConfigured()) {
            echo json_encode(['success' => false, 'error' => t('voice_reader.gen_unconfigured')]);
            return;
        }

        $locale = (string) ($_POST['locale'] ?? '');
        $userId = (int) ($_SESSION['user_id'] ?? 0) ?: null;

        if ($locale !== '' && array_key_exists($locale, available_locales())) {
            // One language: always a deliberate rebuild, so force past the
            // "already matches" check the bulk path applies.
            $script = PostScript::build($type, $row, $locale);

            if (!$script['available']) {
                echo json_encode(['success' => false, 'error' => t('voice_reader.gen_no_text')]);
                return;
            }

            try {
                $service->generate(
                    $type,
                    $id,
                    $locale,
                    $script,
                    $userId,
                    \App\Models\PostAudio::find($type, $id, $locale, \App\Models\PostAudio::SOURCE_AI)
                );
            } catch (\Throwable $e) {
                error_log("[VoiceController::generate] {$type} #{$id} ({$locale}): " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => t('voice_reader.gen_failed')]);
                return;
            }
        } else {
            $service->ensure($type, $row, $userId, true);
        }

        AuditLog::record(
            (int) ($_SESSION['user_id'] ?? 0),
            'voice.generate',
            "Generated voice audio for {$type} #{$id}" . ($locale !== '' ? " ({$locale})" : '')
        );

        // Re-read: generate() wrote rows the status must reflect.
        echo json_encode([
            'success'  => true,
            'statuses' => $service->status($type, $this->row($type, $id) ?? $row),
        ]);
    }

    /**
     * POST /api/voice/translate-now
     *
     * A resident opened a language the voice player has no text for and
     * pressed "Isalin ngayon" instead of finding a greyed-out button. This is
     * deliberately scoped to TEXT only — it reuses the exact pipeline staff
     * already trigger from /admin/retranslate (TranslationRetryRunner::runOne(),
     * same *_is_auto safety rule, same reason codes) — and never generates a
     * paid MP3. PostAudioService's own design note explains why: synthesising
     * audio on a page view would put a multi-second wait behind a button meant
     * for someone who finds reading hard, and would spend a metered TTS
     * allowance every time a post got shared around. Text translation is the
     * one piece of that pipeline cheap and fast enough to run on demand; once
     * it is stored the browser's own speech engine reads it immediately, at no
     * server cost.
     *
     * Rate-limited (route middleware) so a resident cannot spend the
     * barangay's translation allowance by mashing the button across many
     * posts — the same 'rate-limit' bucket the AI widgets already share.
     */
    public function translateNow(): void
    {
        header('Content-Type: application/json');
        check_csrf();

        $type   = (string) ($_POST['content_type'] ?? '');
        $id     = (int) ($_POST['content_id'] ?? 0);
        $locale = (string) ($_POST['locale'] ?? '');

        if (!\in_array($type, ['announcement', 'event', 'ordinance'], true)
            || $id <= 0
            || !\in_array($locale, TranslationAttempt::LANGS, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => t('voice_reader.err_empty')]);
            return;
        }

        // A person pressing this IS the new information — same reasoning as
        // the admin "retry" button — so the backoff clock is cleared first.
        TranslationAttempt::makeDue($type, $id, $locale);
        $result = (new TranslationRetryRunner())->runOne($type, $id, $locale);

        $row = $this->row($type, $id);
        if ($row === null) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => t('voice_reader.gen_not_found')]);
            return;
        }

        $script = PostScript::build($type, $row, $locale);

        echo json_encode([
            'success'     => $script['available'],
            'available'   => $script['available'],
            'title'       => $script['title'],
            'chunks'      => $script['chunks'],
            'speech_lang' => SpokenText::speechLang($locale),
            'voices'      => SpokenText::voiceCandidates($locale),
            // Present even on success, so the caller can log why a retry was
            // still needed later; absent nothing is hidden either way.
            'reason'      => $result['reason'] ?? null,
            'error'       => $script['available'] ? null : t('voice_reader.unavailable_in', [
                'language' => t('voice_reader.lang_' . ($locale === 'msm' ? 'mn' : $locale)),
            ]),
        ]);
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /**
     * Load one content row by type.
     *
     * @return array<string,mixed>|null
     */
    private function row(string $type, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return match ($type) {
            'announcement' => Announcement::find($id),
            'event'        => Event::find($id),
            'ordinance'    => Ordinance::find($id),
            default        => null,
        };
    }

    /** Trim and cap one free-text field. */
    private function clean(mixed $value): string
    {
        return mb_substr(trim((string) $value), 0, self::MAX_INPUT);
    }

    /**
     * Split a newline-separated field into the list of lines SpokenText wants.
     *
     * @return list<string>
     */
    private function lines(mixed $value): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $this->clean($value)) ?: [];

        return array_values(array_filter(
            array_map('trim', $lines),
            static fn (string $line): bool => $line !== ''
        ));
    }
}
