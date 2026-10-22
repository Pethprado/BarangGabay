<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\VoiceSample;
use App\Services\VoiceAudioStore;

/**
 * Stored word recordings.
 *
 * The Voice Training hub and the resident Voice Reader were removed on
 * 2026-10-22. What remains is playback of the recordings already in the
 * database, which the Manobo dictionary still offers next to its headwords.
 */
class VoiceTrainingController
{
    /**
     * GET /voice/audio/{id} — stream a stored recording.
     *
     * Approved audio is playable by any signed-in user (the Manobo dictionary
     * plays it); unreviewed audio only by staff. Supports Range requests,
     * which Safari/iOS require before they will play audio.
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
}
