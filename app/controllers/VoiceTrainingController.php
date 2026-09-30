<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\VoiceProfile;
use App\Models\VoiceSample;
use App\Services\FileService;
use App\Services\ManoboDictionary;

class VoiceTrainingController
{
    /**
     * Display main Voice Training dashboard
     */
    public function index(): void
    {
        $this->ensureAdmin();

        // Seed default profiles if not present
        VoiceProfile::seedDefaultProfiles();

        // Gather language stats
        $stats = [
            'msm' => VoiceSample::getStats('msm'),
            'fil' => VoiceSample::getStats('fil'),
            'en'  => VoiceSample::getStats('en'),
        ];

        // Active profiles
        $activeProfiles = [
            'msm' => VoiceProfile::getActiveProfile('msm'),
            'fil' => VoiceProfile::getActiveProfile('fil'),
            'en'  => VoiceProfile::getActiveProfile('en'),
        ];

        // Manobo dataset coverage
        $coverage = VoiceSample::getManoboCoverageStats();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = 15;
        $offset = ($page - 1) * $limit;

        // Filter parameters
        $filters = [
            'language'             => (string) ($_GET['language'] ?? ''),
            'status'               => (string) ($_GET['status'] ?? ''),
            'speaker'              => (string) ($_GET['speaker'] ?? ''),
            'has_dictionary_match' => (string) ($_GET['has_dictionary_match'] ?? ''),
            'search'               => (string) ($_GET['search'] ?? ''),
            'page'                 => $page,
            'limit'                => $limit,
        ];

        // Fetch samples and counts
        $samples = VoiceSample::all($filters, $limit, $offset);
        $totalSamples = VoiceSample::count($filters);
        $totalPages = max(1, (int) ceil($totalSamples / $limit));

        // Missing pronunciations queue
        $missingPronunciations = VoiceSample::getMissingPronunciations(15);

        // All voice profiles
        $allProfiles = VoiceProfile::getAll();

        // Load dictionary words for linking
        $dictionaryWords = [];
        try {
            $dictService = new ManoboDictionary();
            $dictionaryWords = $dictService->getAllWords() ?? [];
        } catch (\Throwable $e) {
            error_log('[VoiceTrainingController] Could not fetch dictionary: ' . $e->getMessage());
        }

        view('admin/voice-training/index', [
            'title'                 => 'Voice Training & Dataset Management',
            'stats'                 => $stats,
            'activeProfiles'        => $activeProfiles,
            'coverage'              => $coverage,
            'filters'               => $filters,
            'samples'               => $samples,
            'pagination'            => [
                'total'        => $totalSamples,
                'page'         => $page,
                'limit'        => $limit,
                'total_pages'  => $totalPages,
            ],
            'missingPronunciations' => $missingPronunciations,
            'profiles'              => $allProfiles,
            'dictionaryWords'       => $dictionaryWords,
        ]);
    }

    /**
     * Store new voice sample (File upload or MediaRecorder blob)
     */
    public function storeSample(): void
    {
        $this->ensureAdmin();
        check_csrf();

        try {
            $language          = trim((string) ($_POST['language'] ?? 'msm'));
            $text              = trim((string) ($_POST['text'] ?? ''));
            $speakerLabel      = trim((string) ($_POST['speaker_label'] ?? ''));
            $voiceType         = trim((string) ($_POST['voice_type'] ?? 'community'));
            $dictEntryId       = !empty($_POST['dictionary_entry_id']) ? (int) $_POST['dictionary_entry_id'] : null;
            $notes             = trim((string) ($_POST['notes'] ?? ''));
            $status            = trim((string) ($_POST['status'] ?? VoiceSample::STATUS_APPROVED));
            $consentConfirmed = !empty($_POST['consent_confirmed']) ? 1 : 0;

            if (empty($text)) {
                throw new \InvalidArgumentException('Ang salita o parirala (Text) ay kailangan.');
            }

            // Handle audio file upload or recorded audio blob
            $audioUrl = '';
            $fileSize = 0;
            $mimeType = '';

            if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
                $fileService = new FileService();
                $audioUrl = $fileService->uploadAudio($_FILES['audio_file'], 'voice-samples');
                $fileSize = (int) $_FILES['audio_file']['size'];
                $mimeType = (string) $_FILES['audio_file']['type'];
            } elseif (!empty($_POST['audio_base64'])) {
                // Base64 audio fallback from browser recorder
                $audioUrl = $this->saveBase64Audio((string) $_POST['audio_base64']);
            } else {
                throw new \InvalidArgumentException('Kailangan ng audio file o recording.');
            }

            $userId = (int) ($_SESSION['user_id'] ?? 0);

            $sampleId = VoiceSample::create([
                'language'             => $language,
                'text'                 => $text,
                'dictionary_entry_id' => $dictEntryId,
                'audio_url'            => $audioUrl,
                'speaker_label'        => $speakerLabel ?: 'Community Recorder',
                'voice_type'           => $voiceType,
                'status'               => $status,
                'mime_type'            => $mimeType ?: 'audio/webm',
                'file_size'            => $fileSize,
                'consent_confirmed'    => $consentConfirmed,
                'notes'                => $notes,
                'created_by'           => $userId,
                'approved_by'          => ($status === VoiceSample::STATUS_APPROVED) ? $userId : null,
            ]);

            AuditLog::record(
                $userId,
                'voice_sample.create',
                "Created voice sample #{$sampleId} for '{$text}' ({$language})"
            );

            if ($this->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Ang voice sample ay matagumpay na naisave!',
                    'sample_id' => $sampleId,
                    'audio_url' => $audioUrl,
                ]);
                return;
            }

            set_flash('success', 'Ang voice sample ay matagumpay na naisave!');
            redirect('admin/voice-training');

        } catch (\Throwable $e) {
            error_log('[VoiceTrainingController::storeSample] ' . $e->getMessage());
            if ($this->isAjax()) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                return;
            }
            set_flash('error', $e->getMessage());
            redirect('admin/voice-training');
        }
    }

    /**
     * Update sample status or attributes
     */
    public function updateSample(): void
    {
        $this->ensureAdmin();
        check_csrf();

        $id     = (int) ($_POST['id'] ?? 0);
        $action = (string) ($_POST['action'] ?? 'update_status');
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $sample = VoiceSample::findById($id);
        if (!$sample) {
            $this->responseError('Voice sample not found.', 404);
            return;
        }

        try {
            if ($action === 'approve') {
                VoiceSample::updateStatus($id, VoiceSample::STATUS_APPROVED, $userId);
                AuditLog::record($userId, 'voice_sample.approve', "Approved voice sample #{$id}");
            } elseif ($action === 'reject') {
                VoiceSample::updateStatus($id, VoiceSample::STATUS_REJECTED, $userId);
                AuditLog::record($userId, 'voice_sample.reject', "Rejected voice sample #{$id}");
            } elseif ($action === 'delete') {
                // Delete physical audio file if local
                if (!empty($sample['audio_url'])) {
                    $fileService = new FileService();
                    try {
                        $fileService->delete($sample['audio_url']);
                    } catch (\Throwable $e) {
                        // ignore if file already missing
                    }
                }
                VoiceSample::delete($id);
                AuditLog::record($userId, 'voice_sample.delete', "Deleted voice sample #{$id}");
            } else {
                $status      = (string) ($_POST['status'] ?? $sample['status']);
                $dictEntryId = !empty($_POST['dictionary_entry_id']) ? (int) $_POST['dictionary_entry_id'] : null;
                $speaker     = trim((string) ($_POST['speaker_label'] ?? $sample['speaker_label']));
                $voiceType   = trim((string) ($_POST['voice_type'] ?? $sample['voice_type']));

                VoiceSample::update($id, [
                    'status'               => $status,
                    'dictionary_entry_id' => $dictEntryId,
                    'speaker_label'        => $speaker,
                    'voice_type'           => $voiceType,
                ]);

                if ($status === VoiceSample::STATUS_APPROVED && $sample['status'] !== VoiceSample::STATUS_APPROVED) {
                    VoiceSample::updateStatus($id, VoiceSample::STATUS_APPROVED, $userId);
                }
            }

            if ($this->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Nai-update ang voice sample.']);
                return;
            }

            set_flash('success', 'Nai-update ang voice sample.');
            redirect('admin/voice-training');

        } catch (\Throwable $e) {
            error_log('[VoiceTrainingController::updateSample] ' . $e->getMessage());
            $this->responseError($e->getMessage());
        }
    }

    /**
     * Activate or Save Voice Profile
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
            if ($action === 'activate') {
                VoiceProfile::setActive($id, $language);
                AuditLog::record($userId, 'voice_profile.activate', "Activated voice profile #{$id} for language '{$language}'");
            } elseif ($action === 'create' || $action === 'edit') {
                $name      = trim((string) ($_POST['profile_name'] ?? ''));
                $provider  = trim((string) ($_POST['provider'] ?? 'system'));
                $providerId = trim((string) ($_POST['provider_voice_id'] ?? ''));
                $desc      = trim((string) ($_POST['description'] ?? ''));
                $isActive  = !empty($_POST['is_active']) ? 1 : 0;
                $consent   = !empty($_POST['consent_confirmed']) ? 1 : 0;

                if (empty($name)) {
                    throw new \InvalidArgumentException('Profile name is required.');
                }

                $data = [
                    'language'          => $language,
                    'profile_name'      => $name,
                    'provider'          => $provider,
                    'provider_voice_id' => $providerId,
                    'description'       => $desc,
                    'is_active'         => $isActive,
                    'consent_confirmed' => $consent,
                    'created_by'        => $userId,
                ];

                if ($action === 'edit' && $id > 0) {
                    VoiceProfile::update($id, $data);
                } else {
                    VoiceProfile::create($data);
                }
            }

            if ($this->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Active voice profile updated.']);
                return;
            }

            set_flash('success', 'Active voice profile updated.');
            redirect('admin/voice-training');

        } catch (\Throwable $e) {
            error_log('[VoiceTrainingController::updateProfile] ' . $e->getMessage());
            $this->responseError($e->getMessage());
        }
    }

    /**
     * AJAX endpoint to test voice matching or synthesis
     */
    public function testVoice(): void
    {
        $this->ensureAdmin();
        header('Content-Type: application/json');

        $text     = trim((string) ($_POST['text'] ?? ''));
        $language = trim((string) ($_POST['language'] ?? 'msm'));

        if (empty($text)) {
            echo json_encode(['success' => false, 'error' => 'Maglagay ng text na itetest.']);
            return;
        }

        // 1. Check matching sample in dataset
        $match = VoiceSample::findMatchingSample($text, $language);
        $activeProfile = VoiceProfile::getActiveProfile($language);

        if ($match) {
            echo json_encode([
                'success'      => true,
                'source'       => 'dataset_recording',
                'audio_url'    => $match['audio_url'],
                'matched_text' => $match['text'],
                'speaker'      => $match['speaker_label'],
                'profile_name' => $activeProfile['profile_name'] ?? 'Active Profile',
            ]);
            return;
        }

        // 2. Fallback to active voice profile info
        echo json_encode([
            'success'      => true,
            'source'       => 'tts_synthesis',
            'audio_url'    => null, // Browser or server speech synthesis handles it
            'matched_text' => null,
            'profile'      => $activeProfile,
            'message'      => "Walang dataset recording for '{$text}'. Gagamitin ang active voice profile: {$activeProfile['profile_name']}",
        ]);
    }

    /**
     * Export voice dataset as CSV or JSON
     */
    public function exportDataset(): void
    {
        $this->ensureAdmin();

        $format   = (string) ($_GET['format'] ?? 'csv');
        $language = (string) ($_GET['language'] ?? '');

        $samples = VoiceSample::getAll([
            'language' => $language,
        ]);

        if ($format === 'json') {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="baranggabay_voice_dataset_' . date('Y-m-d') . '.json"');
            echo json_encode($samples, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Default CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="baranggabay_voice_dataset_' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Language', 'Text', 'Speaker', 'Voice Type', 'Status', 'Audio URL', 'Dictionary Match', 'Created At']);

        foreach ($samples as $s) {
            fputcsv($out, [
                $s['id'],
                $s['language'],
                $s['text'],
                $s['speaker_label'],
                $s['voice_type'],
                $s['status'],
                $s['audio_url'],
                $s['manobo_word'] ?? ($s['dictionary_entry_id'] ? "Entry #{$s['dictionary_entry_id']}" : 'None'),
                $s['created_at'],
            ]);
        }

        fclose($out);
        exit;
    }

    // ── Internals ────────────────────────────────────────────────────────────

    private function ensureAdmin(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $role = strtolower((string) ($_SESSION['role'] ?? ''));
        if (!in_array($role, ['admin', 'superadmin'], true)) {
            if ($this->isAjax()) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
                exit;
            }
            set_flash('error', 'Kailangan ng Admin access para sa pahinang ito.');
            redirect('login');
            exit;
        }
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    private function responseError(string $message, int $code = 400): void
    {
        if ($this->isAjax()) {
            header('Content-Type: application/json');
            http_response_code($code);
            echo json_encode(['success' => false, 'error' => $message]);
            exit;
        }
        set_flash('error', $message);
        redirect('admin/voice-training');
        exit;
    }

    private function saveBase64Audio(string $base64Data): string
    {
        if (preg_match('/^data:audio\/(\w+);base64,/', $base64Data, $type)) {
            $data = substr($base64Data, strpos($base64Data, ',') + 1);
            $ext  = strtolower($type[1]);
            if ($ext === 'mpeg') $ext = 'mp3';
        } else {
            $data = $base64Data;
            $ext  = 'webm';
        }

        $decoded = base64_decode($data);
        if ($decoded === false) {
            throw new \RuntimeException('Invalid base64 audio encoding.');
        }

        $publicRoot = \dirname(__DIR__, 2) . '/public';
        $uploadDir  = $publicRoot . '/uploads/voice-samples';

        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)) {
            throw new \RuntimeException('Cannot create upload directory.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $target   = $uploadDir . '/' . $filename;

        if (file_put_contents($target, $decoded) === false) {
            throw new \RuntimeException('Failed to save audio file.');
        }

        return '/uploads/voice-samples/' . $filename;
    }
}
