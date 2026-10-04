<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\PostAudio;
use App\Models\TranslationAttempt;

/**
 * Owns a post's narration: what exists, what is stale, and what to generate.
 *
 * The rule the whole design turns on is that audio is made once per post per
 * language, at publish time, by staff — never on a resident's page view.
 * Generating on playback would put a multi-second wait behind a button meant
 * for someone who finds reading hard, and would spend a month's free tier in a
 * week the first time a post got shared around.
 *
 * So a resident's page only ever reads rows. If a track is missing or stale it
 * is simply not offered, and the player falls back to the device's own speech
 * engine for that language — the feature degrades, it never disappears.
 *
 * Source precedence for a language is fixed: a recording made by a person
 * outranks a generated voice, always. For Manobo that is the difference
 * between a community member reading their own language and a Filipino voice
 * approximating it.
 */
class PostAudioService
{
    /** Where generated narration lives, under public/uploads/. */
    private const DIR = 'voice';

    private TtsService $tts;
    private array $config;

    public function __construct(?TtsService $tts = null, ?array $config = null)
    {
        $this->config = $config ?? require \dirname(__DIR__, 2) . '/config/tts.php';
        $this->tts    = $tts    ?? new TtsService($this->config);
    }

    public function isConfigured(): bool
    {
        return $this->tts->isConfigured();
    }

    // ── Controller entry points ──────────────────────────────────────────────

    /**
     * Bring a just-saved post's narration up to date.
     *
     * The one call the controllers make, placed after the translations have
     * been filled in — the audio has to be generated from the final text, not
     * from what was in the form.
     *
     * Never throws and never blocks the save. A provider outage, an expired
     * free tier or a missing key all end the same way: the post is published,
     * the audio is simply not cached, and the reader falls back to the
     * device's own voice until someone presses Generate.
     */
    public static function refresh(string $type, int $id, ?int $userId = null): void
    {
        try {
            $row = self::loadRow($type, $id);
            if ($row === null) {
                return;
            }

            $service = new self();

            // Runs whether or not a provider is configured: knowing which
            // wording a community recording was made against costs nothing and
            // is the only way the barangay can be told an edit has outdated it.
            $service->syncHumanRecording($type, $row, $userId);

            if ($service->isConfigured()) {
                $service->ensure($type, $row, $userId);
            }
        } catch (\Throwable $e) {
            error_log("[PostAudioService::refresh] {$type} #{$id}: " . $e->getMessage());
        }
    }

    /** Forget a recording staff removed, and delete a post's narration. */
    public static function purgeFor(string $type, int $id): void
    {
        try {
            (new self())->purge($type, $id);
        } catch (\Throwable $e) {
            error_log("[PostAudioService::purgeFor] {$type} #{$id}: " . $e->getMessage());
        }
    }

    /** @return array<string,mixed>|null */
    private static function loadRow(string $type, int $id): ?array
    {
        return match ($type) {
            'announcement' => \App\Models\Announcement::find($id),
            'event'        => \App\Models\Event::find($id),
            'ordinance'    => \App\Models\Ordinance::find($id),
            default        => null,
        };
    }

    // ── Reading ──────────────────────────────────────────────────────────────

    /**
     * Everything the player needs, per language.
     *
     * @param array<string,mixed> $row A content row (the detail query's output).
     * @return array<string,array{
     *     locale:string, available:bool, audioUrl:string|null, source:string,
     *     approximate:bool, stale:bool, voiceName:string|null,
     *     altAudioUrl:string|null, altVoiceName:string|null,
     *     speechLang:string|null, voices:list<string>,
     *     chunks:list<array{say:string,find:string|null,kind:string}>
     * }>
     */
    public function tracks(string $type, array $row): array
    {
        $id     = (int) ($row['id'] ?? 0);
        $stored = [];
        foreach (PostAudio::forPost($type, $id) as $track) {
            $stored[$track['locale'] . ':' . $track['source']] = $track;
        }

        // The human Manobo recording still lives on the content table, where
        // the upload form has always put it. The post_audio 'human' row exists
        // only to remember which wording it was recorded against.
        $humanManobo = trim((string) ($row['audio_manobo_path'] ?? ''));

        $fallback = VoiceResolver::fallbackMode();

        $out = [];
        foreach (PostAudio::LOCALES as $locale) {
            $script = PostScript::build($type, $row, $locale);

            $track = [
                'locale'      => $locale,
                'title'       => $script['title'],
                'available'   => false,
                'audioUrl'    => null,
                'source'      => 'none',
                // A property of the language, not of which source happens to be
                // playing: any machine voice reading Manobo is an approximation.
                // The player decides when to show the label — it must not appear
                // over a community recording, which is a real Manobo speaker.
                'approximate' => SpokenText::isApproximateVoice($locale),
                'stale'       => false,
                'voiceName'   => null,
                'altAudioUrl'  => null,
                'altVoiceName' => null,
                'speechLang'  => SpokenText::speechLang($locale),
                'voices'      => SpokenText::voiceCandidates($locale),
                'chunks'      => $script['chunks'],
            ];

            if (!$script['available']) {
                // No text in this language: offer nothing rather than reading
                // another language under this one's label.
                $out[$locale] = $track;
                continue;
            }

            $track['available'] = true;

            if ($locale === 'msm' && $humanManobo !== '') {
                $record = $stored['msm:human'] ?? null;

                $track['source']   = PostAudio::SOURCE_HUMAN;
                $track['audioUrl'] = asset($humanManobo);
                // Unknown rather than false when no row was recorded: a
                // recording uploaded before this table existed has no wording
                // to compare against, and guessing "current" would be a lie.
                $track['stale']    = $record !== null && $record['text_hash'] !== $script['hash'];

                /*
                 * The generated approximation is offered alongside it, not
                 * instead of it. The recording stays the default — it is the
                 * only actual Manobo voice in existence for this post — but a
                 * listener who wants the other one should not have to ask
                 * staff to delete a recording to hear it.
                 */
                $alt = $stored['msm:' . PostAudio::SOURCE_AI] ?? null;
                $track['fallback']    = $fallback;
                $track['hasRecorded'] = true;
                if ($alt !== null && $alt['text_hash'] === $script['hash'] && $fallback !== VoiceResolver::FALLBACK_NONE) {
                    $track['altAudioUrl']  = asset((string) $alt['audio_path']);
                    $track['altVoiceName'] = $alt['voice_name'] ?? null;
                }

                $out[$locale] = $track;
                continue;
            }

            $ai = $stored[$locale . ':' . PostAudio::SOURCE_AI] ?? null;
            $human = $stored[$locale . ':' . PostAudio::SOURCE_HUMAN] ?? null;

            // A full-post narration counts only when it is explicitly linked
            // to this post, or recorded word-for-word for the whole text.
            // (Matching on a fragment would let one recorded word replace the
            // entire narration.)
            $datasetMatch = \App\Models\VoiceSample::findSampleForContent($type, $id, $locale)
                         ?? \App\Models\VoiceSample::findMatchingSample($locale, (string) ($script['text'] ?? ''));

            if ($datasetMatch && !empty($datasetMatch['audio_url'])) {
                $track['source']      = 'dataset';
                $track['audioUrl']    = (string) $datasetMatch['audio_url'];
                $track['voiceName']   = $datasetMatch['speaker_label'] ?: 'Voice Dataset Audio';
                $track['stale']       = false;
                $track['approximate'] = false;
            } elseif ($human !== null && !empty($human['audio_path'])) {
                $track['source']      = PostAudio::SOURCE_HUMAN;
                $track['audioUrl']    = asset((string) $human['audio_path']);
                $track['voiceName']   = $human['voice_name'] ?? 'Community Recording';
                $track['stale']       = $human['text_hash'] !== $script['hash'];
                $track['approximate'] = false;
            } elseif ($ai !== null && $ai['text_hash'] === $script['hash']) {
                $track['source']    = PostAudio::SOURCE_AI;
                $track['audioUrl']  = asset((string) $ai['audio_path']);
                $track['voiceName'] = $ai['voice_name'] ?? null;
            } else {
                // Missing, or the post was edited after it was generated. Either
                // way the device's own voice reads the current wording, which is
                // the one thing that must never be wrong.
                $track['source'] = 'speech';
                $track['stale']  = $ai !== null;
            }

            // Word/phrase recordings inside each spoken chunk (longest phrase
            // first) — the same resolver as the admin Interactive Voice Tester.
            //
            // Recorded-only mode (the default): every chunk carries its plan;
            // the player plays the recorded segments and skips the rest, and
            // no machine narration is offered. With the TTS fallback enabled,
            // only chunks containing a recording carry a plan and the device
            // voice reads everything else.
            $recordedOnly = $fallback === VoiceResolver::FALLBACK_NONE;
            $hasSegments  = false;
            $missedWords  = [];
            foreach ($track['chunks'] as &$chunk) {
                if (empty($chunk['say'])) {
                    continue;
                }
                $plan = VoiceResolver::resolve($locale, (string) $chunk['say']);
                array_push($missedWords, ...VoiceResolver::missingWords($plan));
                if (VoiceResolver::hasRecording($plan)) {
                    $hasSegments = true;
                }
                if ($recordedOnly || VoiceResolver::hasRecording($plan)) {
                    $chunk['segments'] = $plan;
                }
            }
            unset($chunk);

            $track['fallback']    = $fallback;
            $track['hasRecorded'] = $hasSegments || ($track['audioUrl'] !== null && $track['source'] !== PostAudio::SOURCE_AI);

            // Machine narration is not the admin's voice: drop it in
            // recorded-only mode, and for Manobo whenever native recordings
            // cover part of the post.
            if ($track['source'] === PostAudio::SOURCE_AI && ($recordedOnly || ($locale === 'msm' && $hasSegments))) {
                $track['source']    = 'speech';
                $track['audioUrl']  = null;
                $track['voiceName'] = null;
            }
            if ($recordedOnly) {
                $track['approximate'] = false;   // nothing machine-made is played
            }

            // Count unrecorded words the reader will need — once per post and
            // language per session, so a refresh does not inflate the priority.
            if ($missedWords && $id > 0 && session_status() === PHP_SESSION_ACTIVE) {
                $seenKey = "{$type}:{$id}:{$locale}";
                if (empty($_SESSION['voice_miss_counted'][$seenKey])) {
                    $_SESSION['voice_miss_counted'][$seenKey] = 1;
                    VoiceUsageIndex::recordReaderMisses($missedWords, $locale);
                }
            }

            // Attach active voice profile settings configured in Voice Training Hub
            $activeProfile = \App\Models\VoiceProfile::getActiveProfile($locale);
            $track['activeProfile'] = $activeProfile;
            $track['speakingRate']  = (float) ($activeProfile['speaking_rate'] ?? 0.95);
            if (!empty($activeProfile['provider_voice_id'])) {
                array_unshift($track['voices'], $activeProfile['provider_voice_id']);
            }
            if (!empty($activeProfile['fallback_voice'])) {
                $track['voices'][] = $activeProfile['fallback_voice'];
            }

            $out[$locale] = $track;
        }

        return $out;
    }

    /**
     * Per-language state for the admin panel.
     *
     * @return array<string,array{state:string, voiceName:string|null, chars:int, generatedAt:string|null}>
     *         state ∈ unavailable | none | ready | stale | human | human_stale
     */
    public function status(string $type, array $row): array
    {
        $out = [];
        foreach ($this->tracks($type, $row) as $locale => $track) {
            $script = PostScript::build($type, $row, $locale);

            $state = match (true) {
                !$track['available']                        => 'unavailable',
                $track['source'] === PostAudio::SOURCE_HUMAN => $track['stale'] ? 'human_stale' : 'human',
                $track['source'] === PostAudio::SOURCE_AI    => 'ready',
                $track['stale']                              => 'stale',
                default                                      => 'none',
            };

            $stored = PostAudio::find($type, (int) ($row['id'] ?? 0), $locale, PostAudio::SOURCE_AI);

            $out[$locale] = [
                'state'       => $state,
                'voiceName'   => $track['voiceName'] ?? ($stored['voice_name'] ?? null),
                'chars'       => $script['chars'],
                'generatedAt' => $stored['generated_at'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Write an audio outcome next to the translation ones.
     *
     * Same table, same reason codes, kind='audio'. Two pipelines fail for
     * overlapping reasons — no text to work from, too long, provider down —
     * and a barangay secretary looking at a post with no Manobo audio
     * should not have to learn a second vocabulary to find out why.
     *
     * The source text is passed so the hash is stored: a track recorded for
     * wording that has since been edited is worse than no track, because it
     * reads the old words under the new headline, and comparing hashes is
     * how that is noticed.
     */
    private function record(
        string  $type,
        int     $id,
        string  $locale,
        string  $code,
        ?string $message = null,
        string  $sourceText = ''
    ): void {
        TranslationAttempt::record(
            $type,
            $id,
            $locale,
            $code,
            'audio',
            $message,
            // '' when nothing is configured and the browser voice is all
            // there is — recorded as 'browser' so the health page can say
            // which engine a listener actually hears.
            $this->tts->provider() !== '' ? $this->tts->provider() : 'browser',
            $sourceText
        );
    }

    // ── Generating ───────────────────────────────────────────────────────────

    /**
     * Generate every language track this post is missing or has outdated.
     *
     * Called from the controllers after a post is saved and its translations
     * have been filled in. Never throws: a provider outage must not stop staff
     * from publishing a notice, and the reader still works without it.
     *
     * @return array<string,string> locale => 'generated' | 'skipped' | 'current' | 'unavailable' | error text
     */
    public function ensure(string $type, array $row, ?int $userId = null, bool $force = false): array
    {
        $result = [];

        $id = (int) ($row['id'] ?? 0);

        if (!$this->isConfigured()) {
            foreach (PostAudio::LOCALES as $locale) {
                $result[$locale] = 'skipped';
                /* Recorded, not just returned. Until now every one of these
                   outcomes was thrown away by the caller, so "why has this
                   post no Manobo audio?" had no answer anywhere — the same
                   silence the translation side had. Same table, same codes,
                   kind='audio'. */
                $this->record($type, $id, $locale, TranslationOutcome::NO_TTS_PROVIDER,
                    'No TTS provider is configured; the browser voice is used instead.');
            }
            return $result;
        }

        $budget = (int) $this->config['max_chars_per_post'];

        foreach (PostAudio::LOCALES as $locale) {
            $script = PostScript::build($type, $row, $locale);

            /*
             * THE ORDERING RULE, made explicit.
             *
             * Audio for a language can only be made after the TEXT for that
             * language exists — there is otherwise nothing to read. This is
             * why refresh() is called after the translations are filled in,
             * and this guard is what makes the rule hold even if that order
             * is ever changed: no text, no track, and the reason is written
             * down rather than the language quietly having no audio.
             */
            if (!$script['available']) {
                $result[$locale] = 'unavailable';
                $this->record($type, $id, $locale, TranslationOutcome::NO_SOURCE_TEXT,
                    'There is no text in this language yet, so there is nothing to read aloud.',
                    '');
                continue;
            }

            /*
             * Manobo with a recording still gets a generated track. It is not
             * a replacement — the recording stays the default source — but it
             * costs a few hundred characters and it means a listener can hear
             * the other one without staff deleting a community member's work
             * to make that possible.
             */
            $existing = PostAudio::find($type, $id, $locale, PostAudio::SOURCE_AI);
            if (!$force && $existing !== null && $existing['text_hash'] === $script['hash']) {
                $result[$locale] = 'current';        // already matches the wording
                continue;
            }

            if ($script['chars'] > $budget) {
                $result[$locale] = 'too_long';
                error_log("[PostAudioService] {$type} #{$id} ({$locale}) exceeds the per-post character budget");
                $this->record($type, $id, $locale, TranslationOutcome::TEXT_TOO_LONG,
                    sprintf('%d characters is over what one post may spend on narration.', $script['chars']),
                    $script['text'] ?? '');
                continue;
            }
            $budget -= $script['chars'];

            try {
                $this->generate($type, $id, $locale, $script, $userId, $existing);
                $result[$locale] = 'generated';
                $this->record($type, $id, $locale, TranslationOutcome::OK, null, $script['text'] ?? '');
            } catch (\Throwable $e) {
                // Logged, not surfaced: the post is saved either way and the
                // browser voice covers this language until someone retries.
                error_log("[PostAudioService] {$type} #{$id} ({$locale}) failed: " . $e->getMessage());
                $result[$locale] = 'failed';
                $this->record($type, $id, $locale, TranslationOutcome::PROVIDER_ERROR,
                    $e->getMessage(), $script['text'] ?? '');
            }
        }

        return $result;
    }

    /**
     * Synthesise and store one track, replacing whatever was there.
     *
     * @param array<string,mixed>      $script   PostScript::build() output.
     * @param array<string,mixed>|null $existing The row being replaced, if any.
     *
     * @throws \RuntimeException when the provider fails or the file cannot be written
     */
    public function generate(
        string $type,
        int    $id,
        string $locale,
        array  $script,
        ?int   $userId = null,
        ?array $existing = null
    ): void {
        $audio = $this->tts->synthesize($script['text'], $locale);
        $path  = $this->store($audio['bytes']);

        PostAudio::put(
            $type,
            $id,
            $locale,
            PostAudio::SOURCE_AI,
            $path,
            $audio['voice_name'],
            $script['hash'],
            $this->estimateSeconds((int) $script['chars']),
            $userId
        );

        // Only once the new row is safely written — a failure above must leave
        // the old audio playable rather than leaving the post silent.
        if ($existing !== null && !empty($existing['audio_path'])) {
            $this->deleteFile((string) $existing['audio_path']);
        }
    }

    /**
     * Keep the human-recording bookkeeping in step with the content table.
     *
     * The recording itself still lives in audio_manobo_path, where the upload
     * form has always put it. What is recorded here is which wording it was
     * made against — and it is refreshed only when the file itself changes.
     *
     * That distinction is the whole point. Staff uploading a recording in the
     * same save as an edit get a hash matching the new text. Staff editing the
     * text later leave the recording's hash where it was, and the barangay is
     * told their recording now describes an older version of the notice —
     * which is exactly the thing nobody would otherwise notice.
     */
    public function syncHumanRecording(string $type, array $row, ?int $userId = null): void
    {
        $id   = (int) ($row['id'] ?? 0);
        $path = trim((string) ($row['audio_manobo_path'] ?? ''));

        if ($id <= 0) {
            return;
        }

        $record = PostAudio::find($type, $id, 'msm', PostAudio::SOURCE_HUMAN);

        if ($path === '') {
            if ($record !== null) {
                PostAudio::forget($type, $id, 'msm', PostAudio::SOURCE_HUMAN);
            }
            return;
        }

        if ($record !== null && (string) $record['audio_path'] === $path) {
            return;                              // same file: leave its hash alone
        }

        $script = PostScript::build($type, $row, 'msm');
        if (!$script['available']) {
            return;                              // no Manobo text to have recorded
        }

        try {
            PostAudio::put($type, $id, 'msm', PostAudio::SOURCE_HUMAN, $path, null, $script['hash'], null, $userId);
        } catch (\Throwable $e) {
            error_log('[PostAudioService] syncHumanRecording failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete a post's narration — files and rows.
     *
     * Called when the post itself is deleted. Only the generated files are
     * removed here; the human recording is the content table's own upload and
     * is deleted by whatever deletes that.
     */
    public function purge(string $type, int $id): void
    {
        foreach (PostAudio::forPost($type, $id) as $track) {
            if (($track['source'] ?? '') === PostAudio::SOURCE_AI && !empty($track['audio_path'])) {
                $this->deleteFile((string) $track['audio_path']);
            }
        }

        PostAudio::forgetPost($type, $id);
    }

    // ── Files ────────────────────────────────────────────────────────────────

    /**
     * Write MP3 bytes under public/uploads/voice/ with a random name.
     *
     * Random rather than predictable so a regenerated track cannot be served
     * from a browser or CDN cache under the name of the version it replaced.
     *
     * @return string Relative public path, e.g. /uploads/voice/ab12….mp3
     */
    private function store(string $bytes): string
    {
        $dir = \dirname(__DIR__, 2) . '/public/uploads/' . self::DIR;

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create the voice upload directory.');
        }

        $name = bin2hex(random_bytes(16)) . '.mp3';
        if (@file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $bytes) === false) {
            throw new \RuntimeException('Could not write the generated audio file.');
        }

        return '/uploads/' . self::DIR . '/' . $name;
    }

    /** Remove a generated file, refusing anything outside the uploads tree. */
    private function deleteFile(string $publicPath): void
    {
        try {
            (new FileService())->delete($publicPath);
        } catch (\Throwable $e) {
            error_log('[PostAudioService] could not delete ' . $publicPath . ': ' . $e->getMessage());
        }
    }

    /**
     * Rough spoken length, for the admin panel only.
     *
     * Deliberately an estimate: reading the real duration means parsing MP3
     * frame headers, and the player uses the audio element's own duration
     * anyway. About fourteen characters a second is close for Filipino and
     * English narration at this rate.
     */
    private function estimateSeconds(int $chars): int
    {
        $rate = max(0.5, (float) $this->config['speaking_rate']);

        return (int) max(1, round($chars / (14 * $rate)));
    }
}
