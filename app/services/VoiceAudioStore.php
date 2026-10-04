<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Persistent storage for voice dataset recordings.
 *
 * Bytes are kept in the voice_sample_audio table rather than public/uploads/:
 * the Render web service runs on an ephemeral disk, so a file written there is
 * gone after the next deploy or idle spin-down while its database row lives
 * on as a broken link. Word and phrase recordings are small (tens of KB), so
 * the database is the one store this deployment already keeps permanently.
 * document_request_files uses the same approach.
 */
final class VoiceAudioStore
{
    public const MAX_BYTES = 5 * 1024 * 1024;   // 5 MB — minutes of speech

    /** Container MIME types the recorder or an upload may produce → file extension. */
    public const ALLOWED = [
        'audio/webm'  => 'webm',
        'audio/ogg'   => 'ogg',
        'audio/mp4'   => 'm4a',
        'audio/x-m4a' => 'm4a',
        'audio/aac'   => 'aac',
        'audio/mpeg'  => 'mp3',
        'audio/wav'   => 'wav',
        'audio/x-wav' => 'wav',
        'audio/wave'  => 'wav',
    ];

    /**
     * Validate an uploaded audio file and return its bytes and real MIME type.
     *
     * The type comes from the file's own bytes (finfo), never from the
     * browser's claim. libmagic reports audio-only WebM/MP4 as video/*, which
     * is mapped back to the audio type.
     *
     * @param  array<string,mixed> $file  One entry of $_FILES.
     * @return array{bytes: string, mime: string, size: int}
     * @throws \InvalidArgumentException  With a message fit to show the admin.
     */
    public static function readUpload(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new \InvalidArgumentException('No audio was received. Record or choose a file first.');
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new \InvalidArgumentException('The audio file is larger than the server allows (max 5 MB).');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException("The audio upload did not complete (upload error {$error}). Please try again.");
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \InvalidArgumentException('Invalid audio upload.');
        }

        $size = (int) filesize($tmp);
        if ($size <= 0) {
            throw new \InvalidArgumentException('The recording is empty — the microphone produced no audio. Check that the right microphone is selected and try again.');
        }
        if ($size > self::MAX_BYTES) {
            throw new \InvalidArgumentException('The audio file is larger than 5 MB.');
        }

        $mime = self::detectMime($tmp);
        if (!isset(self::ALLOWED[$mime])) {
            throw new \InvalidArgumentException("Unsupported audio format ({$mime}). Use WebM, OGG, M4A/MP4, MP3 or WAV.");
        }

        $bytes = file_get_contents($tmp);
        if ($bytes === false) {
            throw new \RuntimeException('Could not read the uploaded audio file.');
        }

        return ['bytes' => $bytes, 'mime' => $mime, 'size' => $size];
    }

    /**
     * Save (or replace) the audio for a sample. Call inside the same
     * transaction that creates the sample row.
     */
    public static function put(int $sampleId, string $bytes, string $mime): void
    {
        $pdo = db();
        $pdo->prepare('DELETE FROM voice_sample_audio WHERE sample_id = ?')->execute([$sampleId]);

        $stmt = $pdo->prepare(
            'INSERT INTO voice_sample_audio (sample_id, mime_type, byte_size, audio_data, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->bindValue(1, $sampleId, PDO::PARAM_INT);
        $stmt->bindValue(2, $mime, PDO::PARAM_STR);
        $stmt->bindValue(3, strlen($bytes), PDO::PARAM_INT);
        $stmt->bindValue(4, $bytes, PDO::PARAM_LOB);
        $stmt->execute();
    }

    /**
     * Fetch a sample's audio.
     *
     * @return array{mime: string, bytes: string}|null
     */
    public static function get(int $sampleId): ?array
    {
        $stmt = db()->prepare('SELECT mime_type, audio_data FROM voice_sample_audio WHERE sample_id = ? LIMIT 1');
        $stmt->execute([$sampleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $data = $row['audio_data'];
        if (is_resource($data)) {
            $data = (string) stream_get_contents($data);
        }
        return ['mime' => (string) $row['mime_type'], 'bytes' => (string) $data];
    }

    /** Remove a sample's audio. */
    public static function delete(int $sampleId): void
    {
        db()->prepare('DELETE FROM voice_sample_audio WHERE sample_id = ?')->execute([$sampleId]);
    }

    /** Public URL path the player uses for a stored sample. */
    public static function urlPath(int $sampleId): string
    {
        return 'voice/audio/' . $sampleId;
    }

    /**
     * Real MIME type of a file, folded to an audio/* type we accept.
     */
    public static function detectMime(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? (string) finfo_file($finfo, $path) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        return match ($mime) {
            'video/webm'                => 'audio/webm',
            'video/mp4', 'audio/x-mp4'  => 'audio/mp4',
            'video/ogg', 'application/ogg' => 'audio/ogg',
            'audio/mp3'                 => 'audio/mpeg',
            'audio/vnd.wave'            => 'audio/wav',
            default                     => $mime,
        };
    }
}
