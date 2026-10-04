<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\PostAudio;
use App\Models\VoiceProfile;
use App\Models\VoiceSample;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Ordinance;
use App\Services\PostScript;
use App\Services\VoiceAudioStore;
use App\Services\VoiceResolver;
use App\Services\VoiceText;
use App\Services\VoiceUsageIndex;

/**
 * Voice Training & AI Dataset Hub.
 *
 * Pipeline: record → upload (audio bytes stored in the database) → pending or
 * approved → VoiceResolver picks approved clips → Resident Voice Reader.
 */
class VoiceTrainingController
{
    /** Shortest recording accepted, in seconds. Short words are fine; zero is not. */
    private const MIN_DURATION = 0.2;

    /** Bump when VoiceUsageIndex changes what it indexes; triggers one rebuild. */
    private const USAGE_INDEX_VERSION = '3';

    private const LANGUAGE_NAMES = ['msm' => 'Manobo', 'en' => 'English', 'fil' => 'Filipino', 'ceb' => 'Bisaya'];

    /**
     * Display the Voice Training dashboard.
     */
    public function index(): void
    {
        $this->ensureAdmin();

        VoiceProfile::seedDefaultProfiles();

        $stats = VoiceSample::getStatsByLanguage();

        $activeProfiles = [];
        foreach (VoiceSample::LANGUAGES as $lang) {
            $activeProfiles[$lang] = VoiceProfile::getActiveProfile($lang);
        }
        $activeProfileCount = count(array_filter($activeProfiles, static fn (array $p): bool => !empty($p['id']) && (int) $p['is_active'] === 1));

        // First visit after a deploy that changed how words are indexed:
        // rebuild once so every language's missing list is populated
        // without anyone clicking Rescan.
        try {
            if ((string) \App\Models\Setting::get('voice_usage_index_version', '') !== self::USAGE_INDEX_VERSION) {
                VoiceUsageIndex::rebuild();
                \App\Models\Setting::set('voice_usage_index_version', self::USAGE_INDEX_VERSION);
            }
        } catch (\Throwable $e) {
            error_log('[VoiceTrainingController::index] usage bootstrap: ' . $e->getMessage());
        }

        $reports  = VoiceUsageIndex::reports();
        $usage    = $reports['msm'];
        $coverage = VoiceSample::getManoboCoverageStats();
        $missing  = VoiceSample::missingDictionaryEntries(15, $usage['words']);
        $health   = VoiceSample::health();

        // Record queues per language, most-needed first. Manobo: dictionary
        // entries without a recording. Others: words residents actually meet
        // in visible posts (recording a whole English dictionary is not the goal).
        $queues = ['msm' => array_map(static fn (array $m): array => [
            'text' => $m['manobo'], 'translation' => $m['translation'], 'dictionary_entry_id' => $m['id'], 'used' => $m['priority'],
        ], VoiceSample::missingDictionaryEntries(0, $usage['words'])['items'])];
        foreach (['en', 'fil', 'ceb'] as $lang) {
            $queues[$lang] = [];
            foreach ($reports[$lang]['words'] as $w) {
                if (!$w['recorded']) {
                    $queues[$lang][] = ['text' => $w['word'], 'translation' => $w['translation'], 'dictionary_entry_id' => null, 'used' => $w['priority']];
                }
            }
        }

        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = 15;

        $status = strtolower((string) ($_GET['status'] ?? ''));
        $filters = [
            'language' => in_array($_GET['language'] ?? '', VoiceSample::LANGUAGES, true) ? (string) $_GET['language'] : '',
            'status'   => in_array($status, VoiceSample::STATUSES, true) ? $status : '',
            'speaker'  => trim((string) ($_GET['speaker'] ?? '')),
            'search'   => trim((string) ($_GET['search'] ?? '')),
            'sort'     => in_array($_GET['sort'] ?? '', ['newest', 'oldest', 'text'], true) ? (string) $_GET['sort'] : 'newest',
        ];

        $samples      = VoiceSample::all($filters, $limit, ($page - 1) * $limit);
        $totalSamples = VoiceSample::count($filters);

        $recentPosts = [];
        foreach (['announcements', 'events', 'ordinances'] as $table) {
            try {
                $recentPosts[$table] = db()->query("SELECT id, title, title_fil, title_en, title_manobo FROM {$table} ORDER BY id DESC LIMIT 25")
                                           ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                $recentPosts[$table] = [];
            }
        }

        view('admin/voice-training/index', [
            'title'              => 'Voice Training & AI Dataset Hub',
            'stats'              => $stats,
            'activeProfiles'     => $activeProfiles,
            'activeProfileCount' => $activeProfileCount,
            'coverage'           => $coverage,
            'usage'              => $usage,
            'reports'            => $reports,
            'queues'             => $queues,
            'fallbackMode'       => VoiceResolver::fallbackMode(),
            'health'             => $health,
            'filters'            => $filters,
            'samples'            => $samples,
            'pagination'         => [
                'total'       => $totalSamples,
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => max(1, (int) ceil($totalSamples / $limit)),
            ],
            'missingPronunciations' => $missing['items'],
            'missingTotal'          => $missing['total'],
            'dictionaryEntries'     => VoiceSample::dictionaryCoverage(),
            'recentPosts'           => $recentPosts,
            'isSuperadmin'          => ($_SESSION['role'] ?? '') === 'superadmin',
        ]);
    }

    /**
     * POST /admin/voice-training/samples — save a new recording or upload.
     *
     * Multipart fields: audio_file (Blob from MediaRecorder, or a chosen file),
     * language, text, translation, dictionary_entry_id, post_link, speaker_label,
     * voice_type, notes, duration, save_as ('pending'|'approved'),
     * duplicate_mode ('' | 'replace' | 'version').
     *
     * Success is reported only after the row and its audio are both committed.
     */
    public function storeSample(): void
    {
        $this->ensureAdmin();
        check_csrf();
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        try {
            $language = (string) ($_POST['language'] ?? 'msm');
            if (!in_array($language, VoiceSample::LANGUAGES, true)) {
                throw new \InvalidArgumentException('Choose Manobo, Filipino or English.');
            }

            $text = trim((string) ($_POST['text'] ?? ''));
            if (VoiceText::normalize($text) === '') {
                throw new \InvalidArgumentException('Type the word or phrase that was recorded.');
            }
            if (mb_strlen($text) > 500) {
                throw new \InvalidArgumentException('The word or phrase is too long (max 500 characters).');
            }

            if (empty($_POST['consent_confirmed'])) {
                throw new \InvalidArgumentException('Confirm that the speaker agreed to this recording being used.');
            }

            $duration = isset($_POST['duration']) && $_POST['duration'] !== '' ? (float) $_POST['duration'] : null;
            if ($duration !== null && $duration < self::MIN_DURATION) {
                throw new \InvalidArgumentException('The recording is too short to contain speech. Record it again.');
            }

            $audio = VoiceAudioStore::readUpload($_FILES['audio_file'] ?? []);

            $saveAs = ($_POST['save_as'] ?? '') === VoiceSample::STATUS_APPROVED
                ? VoiceSample::STATUS_APPROVED
                : VoiceSample::STATUS_PENDING;

            [$contentType, $contentId] = $this->parsePostLink((string) ($_POST['post_link'] ?? ''));

            $dictEntryId = !empty($_POST['dictionary_entry_id']) ? (int) $_POST['dictionary_entry_id'] : null;
            if ($dictEntryId === null && $language === 'msm') {
                $dictEntryId = VoiceUsageIndex::dictionaryByWord()[VoiceText::normalize($text)]['id'] ?? null;
            }

            $duplicateMode = (string) ($_POST['duplicate_mode'] ?? '');
            if ($saveAs === VoiceSample::STATUS_APPROVED && $duplicateMode === '') {
                $dup = VoiceSample::findApprovedDuplicate($language, $text);
                if ($dup !== null) {
                    $this->json([
                        'success'   => false,
                        'duplicate' => ['id' => (int) $dup['id'], 'text' => $dup['text']],
                        'error'     => "An approved recording of \"{$dup['text']}\" already exists (sample #{$dup['id']}).",
                    ], 409);
                    return;
                }
            }

            $profile = VoiceProfile::getActiveProfile($language);

            $pdo = db();
            $pdo->beginTransaction();
            try {
                $sampleId = VoiceSample::create([
                    'language'            => $language,
                    'text'                => $text,
                    'translation'         => trim((string) ($_POST['translation'] ?? '')),
                    'dictionary_entry_id' => $dictEntryId,
                    'content_type'        => $contentType,
                    'content_id'          => $contentId,
                    'profile_id'          => $profile['id'] ?? null,
                    'audio_url'           => '',
                    'speaker_label'       => trim((string) ($_POST['speaker_label'] ?? '')) ?: 'Community Speaker',
                    'voice_type'          => $this->cleanVoiceType((string) ($_POST['voice_type'] ?? 'community')),
                    'status'              => $saveAs,
                    'duration'            => $duration,
                    'mime_type'           => $audio['mime'],
                    'file_size'           => $audio['size'],
                    'notes'               => trim((string) ($_POST['notes'] ?? '')) ?: null,
                    'created_by'          => $userId,
                    'approved_by'         => $saveAs === VoiceSample::STATUS_APPROVED ? $userId : null,
                ]);

                VoiceAudioStore::put($sampleId, $audio['bytes'], $audio['mime']);
                VoiceSample::update($sampleId, ['audio_url' => VoiceAudioStore::urlPath($sampleId)]);

                if ($saveAs === VoiceSample::STATUS_APPROVED && $duplicateMode === 'replace') {
                    VoiceSample::retireApprovedDuplicates($language, $text, $sampleId, $userId);
                }

                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            VoiceResolver::flush();
            if ($saveAs === VoiceSample::STATUS_APPROVED) {
                $this->syncSampleToPost($sampleId, $userId);
            }

            AuditLog::record($userId, 'voice_sample.create', "Created voice sample #{$sampleId} '{$text}' ({$language}, {$saveAs})");

            $message = $saveAs === VoiceSample::STATUS_APPROVED
                ? "Saved and approved \"{$text}\". Residents will now hear this recording."
                : "Saved \"{$text}\" as pending. Approve it to make it available to residents.";

            $this->respond(true, $message, ['sample_id' => $sampleId, 'status' => $saveAs, 'audio_url' => asset(VoiceAudioStore::urlPath($sampleId))]);
        } catch (\InvalidArgumentException $e) {
            $this->respond(false, $e->getMessage(), [], 422);
        } catch (\Throwable $e) {
            error_log(sprintf('[VoiceTrainingController::storeSample] user=%d lang=%s: %s', $userId, (string) ($_POST['language'] ?? ''), $e->getMessage()));
            $this->respond(false, 'The recording could not be saved because of a server error. It has not been added to the dataset. Please try again; if it keeps failing, check the error log.', [], 500);
        }
    }

    /**
     * POST /admin/voice-training/update — approve, reject, delete, edit, or re-record a sample.
     */
    public function updateSample(): void
    {
        $this->ensureAdmin();
        check_csrf();

        $id     = (int) ($_POST['id'] ?? 0);
        $action = (string) ($_POST['action'] ?? 'edit');
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $sample = VoiceSample::findById($id);
        if (!$sample) {
            $this->respond(false, 'Voice sample not found.', [], 404);
            return;
        }

        try {
            switch ($action) {
                case 'approve':
                    if (!VoiceSample::isPlayable($sample)) {
                        throw new \InvalidArgumentException('This sample has no playable audio (broken). Re-record it before approving.');
                    }
                    VoiceSample::updateStatus($id, VoiceSample::STATUS_APPROVED, $userId);
                    $replaced = VoiceSample::retireApprovedDuplicates((string) $sample['language'], (string) $sample['text'], $id, $userId);
                    AuditLog::record($userId, 'voice_sample.approve', "Approved voice sample #{$id}");
                    $this->syncSampleToPost($id, $userId);
                    $message = "Approved \"{$sample['text']}\"." . ($replaced ? " It replaces {$replaced} older approved recording(s) of the same text." : '');
                    break;

                case 'reject':
                    VoiceSample::updateStatus($id, VoiceSample::STATUS_REJECTED, $userId);
                    AuditLog::record($userId, 'voice_sample.reject', "Rejected voice sample #{$id}");
                    $message = "Rejected \"{$sample['text']}\". It will not be used by the Voice Reader.";
                    break;

                case 'pending':
                    VoiceSample::updateStatus($id, VoiceSample::STATUS_PENDING, null);
                    AuditLog::record($userId, 'voice_sample.pending', "Moved voice sample #{$id} back to pending");
                    $message = "Moved \"{$sample['text']}\" back to pending.";
                    break;

                case 'delete':
                    VoiceSample::delete($id);
                    AuditLog::record($userId, 'voice_sample.delete', "Deleted voice sample #{$id} '{$sample['text']}'");
                    $message = "Deleted \"{$sample['text']}\".";
                    break;

                case 'rerecord':
                    $audio    = VoiceAudioStore::readUpload($_FILES['audio_file'] ?? []);
                    $duration = isset($_POST['duration']) && $_POST['duration'] !== '' ? (float) $_POST['duration'] : null;
                    $pdo = db();
                    $pdo->beginTransaction();
                    try {
                        VoiceAudioStore::put($id, $audio['bytes'], $audio['mime']);
                        VoiceSample::update($id, [
                            'audio_url' => VoiceAudioStore::urlPath($id),
                            'mime_type' => $audio['mime'],
                            'file_size' => $audio['size'],
                            'duration'  => $duration,
                        ]);
                        // New audio needs a fresh review unless the reviewer approves it now.
                        $newStatus = ($_POST['save_as'] ?? '') === VoiceSample::STATUS_APPROVED ? VoiceSample::STATUS_APPROVED : VoiceSample::STATUS_PENDING;
                        VoiceSample::updateStatus($id, $newStatus, $newStatus === VoiceSample::STATUS_APPROVED ? $userId : null);
                        $pdo->commit();
                    } catch (\Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $e;
                    }
                    AuditLog::record($userId, 'voice_sample.rerecord', "Re-recorded voice sample #{$id}");
                    $message = "Replaced the audio for \"{$sample['text']}\".";
                    break;

                case 'edit':
                default:
                    $text = trim((string) ($_POST['text'] ?? $sample['text']));
                    if (VoiceText::normalize($text) === '') {
                        throw new \InvalidArgumentException('The word or phrase cannot be empty.');
                    }
                    VoiceSample::update($id, [
                        'text'                => $text,
                        'translation'         => trim((string) ($_POST['translation'] ?? $sample['translation'] ?? '')),
                        'dictionary_entry_id' => !empty($_POST['dictionary_entry_id']) ? (int) $_POST['dictionary_entry_id'] : null,
                        'speaker_label'       => trim((string) ($_POST['speaker_label'] ?? $sample['speaker_label'])),
                        'voice_type'          => $this->cleanVoiceType((string) ($_POST['voice_type'] ?? $sample['voice_type'])),
                        'notes'               => trim((string) ($_POST['notes'] ?? $sample['notes'] ?? '')),
                    ]);
                    AuditLog::record($userId, 'voice_sample.edit', "Edited voice sample #{$id}");
                    $message = "Updated \"{$text}\".";
                    break;
            }

            VoiceResolver::flush();
            $this->respond(true, $message);
        } catch (\InvalidArgumentException $e) {
            $this->respond(false, $e->getMessage(), [], 422);
        } catch (\Throwable $e) {
            error_log(sprintf('[VoiceTrainingController::updateSample] user=%d sample=%d action=%s: %s', $userId, $id, $action, $e->getMessage()));
            $this->respond(false, 'The change could not be saved because of a server error.', [], 500);
        }
    }

    /**
     * POST /admin/voice-training/profile — edit or activate a language's voice profile.
     */
    public function updateProfile(): void
    {
        $this->ensureAdmin();
        check_csrf();

        $action   = (string) ($_POST['action'] ?? 'activate');
        $id       = (int) ($_POST['id'] ?? 0);
        $language = (string) ($_POST['language'] ?? 'msm');
        $userId   = (int) ($_SESSION['user_id'] ?? 0);

        try {
            if (!in_array($language, VoiceSample::LANGUAGES, true)) {
                throw new \InvalidArgumentException('Unknown language.');
            }

            if ($action === 'activate') {
                VoiceProfile::setActive($id, $language);
                AuditLog::record($userId, 'voice_profile.activate', "Activated voice profile #{$id} for '{$language}'");
                $message = 'Voice profile activated.';
            } else {
                $name = trim((string) ($_POST['profile_name'] ?? $_POST['name'] ?? ''));
                if ($name === '') {
                    throw new \InvalidArgumentException('Profile name is required.');
                }
                $provider = (string) ($_POST['provider'] ?? 'system');
                if (!in_array($provider, ['dataset_hybrid', 'system', 'google_tts'], true)) {
                    $provider = 'system';
                }
                $rate = isset($_POST['speaking_rate']) ? max(0.6, min(1.4, (float) $_POST['speaking_rate'])) : 0.95;

                $savedId = VoiceProfile::create([
                    'language'          => $language,
                    'name'              => $name,
                    'provider'          => $provider,
                    'provider_voice_id' => trim((string) ($_POST['provider_voice_id'] ?? '')) ?: null,
                    'description'       => trim((string) ($_POST['description'] ?? '')) ?: null,
                    'speaking_rate'     => $rate,
                    'is_active'         => !empty($_POST['is_active']) ? 1 : 0,
                    'updated_by'        => $userId,
                ]);
                if ($savedId <= 0) {
                    throw new \RuntimeException('VoiceProfile::create returned 0');
                }
                AuditLog::record($userId, 'voice_profile.save', "Saved voice profile #{$savedId} for '{$language}'");
                $message = "Saved voice profile \"{$name}\".";
            }

            $this->respond(true, $message);
        } catch (\InvalidArgumentException $e) {
            $this->respond(false, $e->getMessage(), [], 422);
        } catch (\Throwable $e) {
            error_log('[VoiceTrainingController::updateProfile] ' . $e->getMessage());
            $this->respond(false, 'The voice profile could not be saved because of a server error.', [], 500);
        }
    }

    /**
     * POST /admin/voice-training/test — the Interactive Voice Tester.
     *
     * Returns the same segment plan the Resident Voice Reader plays.
     */
    public function testVoice(): void
    {
        $this->ensureAdmin();
        check_csrf();

        $text     = trim((string) ($_POST['text'] ?? ''));
        $language = (string) ($_POST['language'] ?? 'msm');

        if ($text === '' || !in_array($language, VoiceSample::LANGUAGES, true)) {
            $this->json(['success' => false, 'error' => 'Type some text to preview.'], 422);
            return;
        }

        $this->json(['success' => true] + $this->plan($language, $text, false));
    }

    /**
     * POST /api/voice/resolve — playback plan for text (residents and staff).
     *
     * Used when the reader's text changes after the page rendered (e.g. a
     * resident's on-demand translation). Manobo words with no recording are
     * counted for the admin's priority list.
     */
    public function resolve(): void
    {
        check_csrf();

        $language = (string) ($_POST['language'] ?? '');
        if (!in_array($language, VoiceSample::LANGUAGES, true)) {
            $this->json(['success' => false, 'error' => 'Unknown language.'], 422);
            return;
        }

        // Batch form: texts[] → one plan per text (the reader's sentence chunks).
        if (isset($_POST['texts']) && is_array($_POST['texts'])) {
            $plans    = [];
            $missed   = [];
            $fallback = VoiceResolver::fallbackMode();
            foreach (array_slice($_POST['texts'], 0, 300) as $chunkText) {
                $plan    = VoiceResolver::resolve($language, mb_substr((string) $chunkText, 0, 2000));
                $missed  = array_merge($missed, VoiceResolver::missingWords($plan));
                // Recorded-only: every chunk needs its plan (missing parts are skipped).
                $plans[] = ($fallback === VoiceResolver::FALLBACK_NONE || VoiceResolver::hasRecording($plan)) ? $plan : null;
            }
            VoiceUsageIndex::recordReaderMisses($missed, $language);
            $this->json(['success' => true, 'plans' => $plans, 'fallback' => $fallback]);
            return;
        }

        $text = mb_substr(trim((string) ($_POST['text'] ?? '')), 0, 20000);
        if ($text === '') {
            $this->json(['success' => false, 'error' => 'Nothing to read.'], 422);
            return;
        }

        $this->json(['success' => true] + $this->plan($language, $text, true));
    }

    /**
     * GET /voice/audio/{id} — stream a stored recording.
     *
     * Approved audio is playable by any signed-in user (it is what the
     * resident reader uses); unreviewed audio only by staff. Supports Range
     * requests, which Safari/iOS require before they will play audio.
     */
    public function audio(array $params = []): void
    {
        $sampleId = (int) ($params['id'] ?? 0);
        $sample   = VoiceSample::findById($sampleId);
        $role     = (string) ($_SESSION['role'] ?? '');
        $isStaff  = in_array($role, ['staff', 'admin', 'superadmin'], true);

        if (!$sample || (strtolower((string) $sample['status']) !== VoiceSample::STATUS_APPROVED && !$isStaff)) {
            http_response_code(404);
            return;
        }

        $audio = VoiceAudioStore::get($sampleId);
        if ($audio === null) {
            http_response_code(404);
            return;
        }

        $bytes = $audio['bytes'];
        $size  = strlen($bytes);
        $etag  = '"' . md5($bytes) . '"';

        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: ' . $audio['mime']);
        header('Accept-Ranges: bytes');
        header('ETag: ' . $etag);
        header('Cache-Control: private, max-age=300');
        header('X-Content-Type-Options: nosniff');

        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            return;
        }

        $start = 0;
        $end   = $size - 1;
        if (preg_match('/^bytes=(\d*)-(\d*)$/', (string) ($_SERVER['HTTP_RANGE'] ?? ''), $m)) {
            if ($m[1] === '' && $m[2] !== '') {
                $start = max(0, $size - (int) $m[2]);
            } else {
                $start = (int) $m[1];
                $end   = $m[2] !== '' ? min((int) $m[2], $size - 1) : $end;
            }
            if ($start > $end || $start >= $size) {
                http_response_code(416);
                header("Content-Range: bytes */{$size}");
                return;
            }
            http_response_code(206);
            header("Content-Range: bytes {$start}-{$end}/{$size}");
        }

        header('Content-Length: ' . ($end - $start + 1));
        echo substr($bytes, $start, $end - $start + 1);
    }

    /**
     * POST /admin/voice-training/rescan — rebuild the Manobo usage index from all posts.
     */
    public function rescan(): void
    {
        $this->ensureAdmin();
        check_csrf();

        try {
            $language = in_array($_POST['language'] ?? '', VoiceSample::LANGUAGES, true) ? (string) $_POST['language'] : null;
            $count    = VoiceUsageIndex::rebuild($language);
            $label    = $language === null ? 'all languages' : self::LANGUAGE_NAMES[$language];
            AuditLog::record((int) ($_SESSION['user_id'] ?? 0), 'voice_usage.rescan', "Rescanned {$count} posts ({$label})");
            $this->respond(true, "Rescanned {$count} posts for {$label}. The missing-pronunciation lists are up to date.");
        } catch (\Throwable $e) {
            error_log('[VoiceTrainingController::rescan] ' . $e->getMessage());
            $this->respond(false, 'Rescan failed because of a server error.', [], 500);
        }
    }

    /**
     * POST /admin/voice-training/settings — what residents hear for unrecorded words.
     */
    public function settings(): void
    {
        $this->ensureAdmin();
        check_csrf();

        $mode   = ($_POST['voice_fallback'] ?? '') === VoiceResolver::FALLBACK_TTS ? VoiceResolver::FALLBACK_TTS : VoiceResolver::FALLBACK_NONE;
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        try {
            \App\Models\Setting::set('voice_fallback', $mode, $userId);
            AuditLog::record($userId, 'voice_settings.fallback', "Voice fallback set to {$mode}");
            $this->respond(true, $mode === VoiceResolver::FALLBACK_NONE
                ? 'Residents now hear only approved recordings. Unrecorded words are skipped and added to the missing lists.'
                : 'Residents hear approved recordings, with the device voice reading unrecorded words.');
        } catch (\Throwable $e) {
            error_log('[VoiceTrainingController::settings] ' . $e->getMessage());
            $this->respond(false, 'The setting could not be saved because of a server error.', [], 500);
        }
    }

    /**
     * POST /admin/voice-training/diagnostics — Super Admin self-test of the pipeline.
     */
    public function diagnostics(): void
    {
        $this->ensureAdmin();
        check_csrf();
        if (($_SESSION['role'] ?? '') !== 'superadmin') {
            $this->json(['success' => false, 'error' => 'Super Admin only.'], 403);
            return;
        }

        $results = [];
        $run = static function (string $name, callable $test) use (&$results): void {
            try {
                $detail    = $test();
                $results[] = ['name' => $name, 'status' => 'passed', 'detail' => (string) $detail];
            } catch (\Throwable $e) {
                $results[] = ['name' => $name, 'status' => 'failed', 'detail' => $e->getMessage()];
            }
        };

        $run('Database connection', static function (): string {
            db()->query('SELECT 1')->fetchColumn();
            return 'Driver: ' . db()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        });

        $run('Voice tables', static function (): string {
            foreach (['voice_samples', 'voice_profiles', 'voice_sample_audio', 'voice_word_usage'] as $table) {
                db()->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
            }
            db()->query('SELECT content_type, content_id, translation, profile_id, approved_at FROM voice_samples WHERE 1 = 0');
            return 'All tables and columns present';
        });

        $run('Audio storage round-trip', static function (): string {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $probe = random_bytes(512);
                VoiceAudioStore::put(2147480000, $probe, 'audio/webm');
                $back = VoiceAudioStore::get(2147480000);
                if ($back === null || $back['bytes'] !== $probe) {
                    throw new \RuntimeException('Stored bytes did not read back identically');
                }
            } finally {
                $pdo->rollBack();
            }
            return 'Write, read and verify succeeded (rolled back)';
        });

        $run('Broken audio links', static function (): string {
            $broken = VoiceSample::health()['broken'];
            if ($broken > 0) {
                throw new \RuntimeException("{$broken} sample(s) have no playable audio — re-record them");
            }
            return 'None';
        });

        foreach (self::LANGUAGE_NAMES as $lang => $label) {
            $run("{$label} dataset lookup", static function () use ($lang): string {
                $index = VoiceResolver::index($lang);
                return count($index['map']) . ' approved playable recording(s), longest phrase ' . $index['max'] . ' word(s)';
            });
        }

        $run('Resident reader resolver', static function (): string {
            $map = VoiceResolver::index('msm')['map'];
            if (!$map) {
                return 'No approved Manobo recordings yet — resolver returns all-missing plans';
            }
            $word = (string) array_key_first($map);
            $plan = VoiceResolver::resolve('msm', $word);
            if (($plan[0]['type'] ?? '') !== 'recorded') {
                throw new \RuntimeException("Approved word \"{$word}\" did not resolve to its recording");
            }
            return "\"{$word}\" resolves to sample #{$plan[0]['sample_id']}";
        });

        $run('Missing-word scanner', static function (): string {
            $parts = [];
            foreach (VoiceUsageIndex::reports() as $lang => $report) {
                $parts[] = self::LANGUAGE_NAMES[$lang] . ": {$report['used_missing']} of {$report['used_words']} used words missing";
            }
            return implode('; ', $parts);
        });

        $tts = (string) env('TTS_PROVIDER', '');
        $results[] = [
            'name'   => 'Server text-to-speech (optional)',
            'status' => $tts === '' ? 'config_missing' : 'passed',
            'detail' => ($tts === '' ? 'TTS_PROVIDER not set' : "Provider: {$tts}") . ' — resident fallback mode: ' . VoiceResolver::fallbackMode(),
        ];

        $this->json(['success' => true, 'results' => $results]);
    }

    /**
     * GET /admin/voice-training/export — dataset metadata as CSV or JSON.
     */
    public function exportDataset(): void
    {
        $this->ensureAdmin();

        $format   = (string) ($_GET['format'] ?? 'csv');
        $language = in_array($_GET['language'] ?? '', VoiceSample::LANGUAGES, true) ? (string) $_GET['language'] : '';
        $status   = in_array($_GET['status'] ?? '', VoiceSample::STATUSES, true) ? (string) $_GET['status'] : '';

        $rows = [];
        foreach (VoiceSample::getAll(['language' => $language, 'status' => $status, 'sort' => 'oldest']) as $s) {
            $rows[] = [
                'id'                  => (int) $s['id'],
                'language'            => $s['language'],
                'source_text'         => $s['text'],
                'normalized_text'     => $s['normalized_text'],
                'translation'         => $s['translation'] ?? ($s['tagalog'] ?? ''),
                'dictionary_entry_id' => $s['dictionary_entry_id'],
                'dictionary_headword' => $s['manobo'] ?? '',
                'speaker'             => $s['speaker_label'],
                'voice_type'          => $s['voice_type'],
                'profile_id'          => $s['profile_id'] ?? null,
                'status'              => $s['status'],
                'audio_url'           => $s['playable_url'] ?? '',
                'audio_format'        => $s['mime_type'],
                'duration_seconds'    => $s['duration'],
                'file_size_bytes'     => $s['file_size'],
                'created_at'          => $s['created_at'],
                'approved_by'         => $s['approved_by'],
                'approved_at'         => $s['approved_at'] ?? null,
            ];
        }

        AuditLog::record((int) ($_SESSION['user_id'] ?? 0), 'voice_dataset.export', 'Exported ' . count($rows) . " voice samples as {$format}");
        $filename = 'baranggabay_voice_dataset_' . date('Y-m-d');

        if ($format === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"{$filename}.json\"");
            echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Manobo/Filipino characters correctly
        fputcsv($out, array_keys($rows[0] ?? ['id' => '']));
        foreach ($rows as $row) {
            // Neutralise spreadsheet formula injection in text cells.
            fputcsv($out, array_map(static fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $row));
        }
        fclose($out);
        exit;
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /**
     * Segment plan for text, plus the profile/voice the player should use.
     *
     * @return array<string,mixed>
     */
    private function plan(string $language, string $text, bool $countMisses): array
    {
        $segments = VoiceResolver::resolve($language, $text);
        if ($countMisses) {
            VoiceUsageIndex::recordReaderMisses(VoiceResolver::missingWords($segments), $language);
        }

        $profile = VoiceProfile::getActiveProfile($language);
        return [
            'language'      => $language,
            'segments'      => $segments,
            'recorded'      => count(array_filter($segments, static fn (array $s): bool => $s['type'] === 'recorded')),
            'missing'       => count(array_filter($segments, static fn (array $s): bool => $s['type'] === 'missing')),
            'speech_lang'   => \App\Services\SpokenText::speechLang($language) ?? 'fil-PH',
            'voices'        => $language === 'ceb' ? ['ceb-PH', 'ceb', 'fil-PH', 'fil'] : \App\Services\SpokenText::voiceCandidates($language),
            'approximate'   => in_array($language, ['msm', 'ceb'], true),
            'fallback'      => VoiceResolver::fallbackMode(),
            'speaking_rate' => (float) ($profile['speaking_rate'] ?? 0.95),
            'profile_name'  => (string) ($profile['profile_name'] ?? $profile['name'] ?? ''),
        ];
    }

    /**
     * A recording explicitly linked to a post is that post's narration:
     * register it in post_audio so the reader plays it as the full track.
     */
    private function syncSampleToPost(int $sampleId, int $userId): void
    {
        try {
            $sample = VoiceSample::findById($sampleId);
            if (!$sample || empty($sample['content_type']) || empty($sample['content_id'])) {
                return;
            }
            $type     = (string) $sample['content_type'];
            $id       = (int) $sample['content_id'];
            $language = (string) $sample['language'];

            $post = match ($type) {
                'announcement' => Announcement::find($id),
                'event'        => Event::find($id),
                'ordinance'    => Ordinance::find($id),
                default        => null,
            };
            if (!$post) {
                return;
            }

            $script = PostScript::build($type, $post, $language);
            PostAudio::put(
                $type,
                $id,
                $language,
                PostAudio::SOURCE_HUMAN,
                VoiceAudioStore::urlPath($sampleId),
                (string) ($sample['speaker_label'] ?: 'Community Recording'),
                $script['hash'] ?: PostScript::hash((string) $sample['text']),
                null,
                $userId
            );
        } catch (\Throwable $e) {
            error_log("[VoiceTrainingController::syncSampleToPost] sample={$sampleId}: " . $e->getMessage());
        }
    }

    /**
     * "announcement:12" → ['announcement', 12]; anything else → [null, null].
     *
     * @return array{0: ?string, 1: ?int}
     */
    private function parsePostLink(string $link): array
    {
        if (preg_match('/^(announcement|event|ordinance):(\d+)$/', $link, $m)) {
            return [$m[1], (int) $m[2]];
        }
        return [null, null];
    }

    private function cleanVoiceType(string $type): string
    {
        return in_array($type, ['community', 'male', 'female', 'neutral'], true) ? $type : 'community';
    }

    private function ensureAdmin(): void
    {
        $role = strtolower((string) ($_SESSION['role'] ?? ''));
        if (!in_array($role, ['admin', 'superadmin'], true)) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => 'Admin access is required.'], 403);
                exit;
            }
            flash('error', 'Kailangan ng Admin access para sa pahinang ito.');
            redirect('login');
        }
    }

    private function isAjax(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    /**
     * JSON for fetch() callers, flash + redirect for plain form posts.
     *
     * @param array<string,mixed> $extra
     */
    private function respond(bool $ok, string $message, array $extra = [], int $code = 200): void
    {
        if ($this->isAjax()) {
            $this->json(['success' => $ok, ($ok ? 'message' : 'error') => $message] + $extra, $ok ? 200 : $code);
            return;
        }
        flash($ok ? 'success' : 'error', $message);
        $back = (string) ($_POST['return_to'] ?? '');
        redirect(str_starts_with($back, 'admin/voice-training') ? $back : 'admin/voice-training');
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function json(array $payload, int $code = 200): void
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
