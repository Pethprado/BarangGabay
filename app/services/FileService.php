<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Handles validated file uploads and deletions.
 *
 * All uploads are stored under public/uploads/{type}/.
 * MIME type is detected with finfo — never trusted from $_FILES.
 */
class FileService
{
    /** Whitelisted MIME types mapped to their canonical extension. */
    private const MIME_EXT = [
        'image/jpeg'      => '.jpg',
        'image/png'       => '.png',
        'application/pdf' => '.pdf',
    ];

    /** Whitelisted audio MIME types (base type only, codec params stripped). */
    private const AUDIO_MIME_EXT = [
        'audio/mpeg'  => '.mp3',
        'audio/mp3'   => '.mp3',
        'audio/wav'   => '.wav',
        'audio/x-wav' => '.wav',
        'audio/ogg'   => '.ogg',
        'audio/webm'  => '.webm',
        // Some libmagic builds return video/* for audio-only containers
        'video/webm'  => '.webm',
        'audio/mp4'   => '.mp4',
        'video/mp4'   => '.mp4',
        'audio/x-m4a' => '.m4a',
        'audio/aac'   => '.aac',
        'application/ogg' => '.ogg',
    ];

    /**
     * Extension → MIME fallback used when finfo returns application/octet-stream.
     * This happens on Windows XAMPP with PHP's bundled libmagic (too old to identify
     * WebM/MP3 magic bytes). We trust the file extension only as a last resort;
     * the actual stored filename is always a random hex string so there is no
     * execution risk.
     */
    private const EXT_FALLBACK_MIME = [
        'mp3'  => 'audio/mpeg',
        'wav'  => 'audio/x-wav',
        'ogg'  => 'audio/ogg',
        'webm' => 'audio/webm',
        'm4a'  => 'audio/x-m4a',
        'aac'  => 'audio/aac',
        'mp4'  => 'audio/mp4',
    ];

    /** Max bytes: 5 MB for images, 10 MB for PDFs, 10 MB for audio. */
    private const MAX_IMAGE_BYTES = 5_242_880;
    private const MAX_PDF_BYTES   = 10_485_760;
    private const MAX_AUDIO_BYTES = 10_485_760;

    /**
     * Validate, move, and persist an uploaded file.
     *
     * @param  array  $file  One entry from $_FILES (e.g. $_FILES['cover_image'])
     * @param  string $type  Subdirectory name under public/uploads/
     *                       e.g. 'announcements', 'ordinances', 'id-photos'
     * @return string        Relative public URL, e.g. /uploads/announcements/abc.jpg
     *
     * @throws \RuntimeException on validation failure or filesystem error
     */
    public function upload(array $file, string $type): string
    {
        // 1. PHP upload-error check
        $code = $file['error'] ?? -1;
        if ($code !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            throw new \RuntimeException($this->errorMessage($code));
        }

        // 2. Detect real MIME type — finfo only, never $_FILES['type']
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = (string) finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        // 3. Whitelist check
        if (!array_key_exists($mime, self::MIME_EXT)) {
            throw new \RuntimeException(
                "Hindi pinahihintulutang uri ng file: {$mime}. "
                . 'Tanggap lamang: JPEG, PNG, PDF.'
            );
        }

        // 4. Size limit
        $maxBytes = ($mime === 'application/pdf') ? self::MAX_PDF_BYTES : self::MAX_IMAGE_BYTES;
        if ((int) $file['size'] > $maxBytes) {
            $mb = number_format($maxBytes / 1_048_576, 0);
            throw new \RuntimeException("Lumagpas sa {$mb} MB na limitasyon ng file.");
        }

        // 5. Sanitise the type key — prevent path traversal
        $type = preg_replace('/[^a-z0-9\-]/', '', strtolower($type));
        if ($type === '') {
            throw new \RuntimeException('Invalid upload type.');
        }

        // 6. Ensure upload subdirectory exists
        $publicRoot = \dirname(__DIR__, 2) . '/public';
        $uploadDir  = $publicRoot . '/uploads/' . $type;

        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)) {
            throw new \RuntimeException("Nabigong gumawa ng upload directory: {$uploadDir}");
        }

        // 7. Generate a cryptographically random filename with proper extension
        $ext      = self::MIME_EXT[$mime];
        $filename = bin2hex(random_bytes(16)) . $ext;
        $target   = $uploadDir . DIRECTORY_SEPARATOR . $filename;

        // 8. Move file
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new \RuntimeException('Nabigong ilipat ang na-upload na file.');
        }

        // 9. Return the relative public URL
        return '/uploads/' . $type . '/' . $filename;
    }

    /**
     * Delete a previously uploaded file given its relative public URL.
     *
     * @param string $filePath  e.g. /uploads/ordinances/abc.pdf
     * @throws \RuntimeException if the path escapes the uploads directory
     */
    public function delete(string $filePath): void
    {
        $publicRoot  = \dirname(__DIR__, 2) . '/public';
        $uploadsRoot = realpath($publicRoot . '/uploads');
        $absolute    = $publicRoot . '/' . ltrim($filePath, '/');
        $realTarget  = realpath($absolute);

        if ($realTarget === false) {
            return; // File doesn't exist — nothing to do
        }

        // Guard: must be inside public/uploads/ to prevent deletion of arbitrary files
        if ($uploadsRoot === false || !str_starts_with($realTarget, $uploadsRoot)) {
            throw new \RuntimeException('Bawal mag-delete ng file na nasa labas ng uploads directory.');
        }

        @unlink($realTarget);
    }

    /**
     * Validate, move, and persist an uploaded audio file.
     *
     * Accepts MP3, WAV, OGG, WebM (MediaRecorder output), M4A, AAC.
     * Strips codec parameters from the MIME type before matching
     * (e.g. "audio/webm;codecs=opus" → "audio/webm").
     *
     * @param  array  $file  One entry from $_FILES
     * @param  string $type  Subdirectory under public/uploads/ (e.g. 'manobo-audio')
     * @return string        Relative public URL, e.g. /uploads/manobo-audio/abc.webm
     *
     * @throws \RuntimeException on validation failure or filesystem error
     */
    public function uploadAudio(array $file, string $type): string
    {
        $code = $file['error'] ?? -1;
        if ($code !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            throw new \RuntimeException($this->errorMessage($code));
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = (string) finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        // Strip codec parameters (e.g. "audio/webm;codecs=opus" → "audio/webm")
        $mimeBase = explode(';', $mime)[0];

        // Windows XAMPP's bundled libmagic is often too old to identify WebM and MP3
        // magic bytes, falling back to application/octet-stream.  When that happens,
        // trust the original filename extension as a safe secondary check — the stored
        // file always gets a random hex name so there is no PHP-execution risk.
        if ($mimeBase === 'application/octet-stream') {
            $ext      = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
            $mimeBase = self::EXT_FALLBACK_MIME[$ext] ?? $mimeBase;
        }

        if (!\array_key_exists($mimeBase, self::AUDIO_MIME_EXT)) {
            throw new \RuntimeException(
                "Hindi pinahihintulutang uri ng audio: {$mime}. "
                . 'Tanggap lamang: MP3, WAV, OGG, WebM, M4A, AAC.'
            );
        }

        if ((int) $file['size'] > self::MAX_AUDIO_BYTES) {
            throw new \RuntimeException('Lumagpas sa 10 MB na limitasyon ng audio file.');
        }

        $type = preg_replace('/[^a-z0-9\-]/', '', strtolower($type));
        if ($type === '') {
            throw new \RuntimeException('Invalid upload type.');
        }

        $publicRoot = \dirname(__DIR__, 2) . '/public';
        $uploadDir  = $publicRoot . '/uploads/' . $type;

        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)) {
            throw new \RuntimeException("Nabigong gumawa ng upload directory: {$uploadDir}");
        }

        $ext      = self::AUDIO_MIME_EXT[$mimeBase];
        $filename = bin2hex(random_bytes(16)) . $ext;
        $target   = $uploadDir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new \RuntimeException('Nabigong ilipat ang na-upload na audio file.');
        }

        return '/uploads/' . $type . '/' . $filename;
    }

    /**
     * Store bytes that arrived over the network rather than through a form.
     *
     * Used by the link importer: an og:image is re-hosted here instead of being
     * hotlinked, so a cover picture does not vanish the day the source deletes
     * its post — and so residents' browsers are not made to fetch an asset from
     * a third party that then knows who read the notice.
     *
     * The bytes get exactly the same scrutiny as an upload, and for the same
     * reason: they came from somewhere we do not control. finfo reads the real
     * type out of the content, the whitelist is the one above, and the stored
     * name is random, so nothing about the remote filename survives to
     * influence what lands on disk.
     *
     * @param string       $bytes   Raw file content.
     * @param string       $type    Subdirectory under public/uploads/.
     * @param list<string> $accept  MIME types this caller allows.
     * @param int          $maxBytes
     * @return string Relative public URL.
     *
     * @throws \RuntimeException when the content is not an accepted type
     */
    public function storeFetched(string $bytes, string $type, array $accept, int $maxBytes): string
    {
        if ($bytes === '') {
            throw new \RuntimeException('Walang natanggap na file mula sa link.');
        }
        if (strlen($bytes) > $maxBytes) {
            $mb = number_format($maxBytes / 1_048_576, 0);
            throw new \RuntimeException("Lumagpas sa {$mb} MB na limitasyon ng file.");
        }

        // The real type, read from the content — never from the URL's
        // extension or the server's Content-Type header, both of which are
        // controlled by whoever we fetched from.
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = (string) finfo_buffer($finfo, $bytes);
        finfo_close($finfo);

        $allowed = array_intersect_key(self::MIME_EXT, array_flip($accept));
        if (!\array_key_exists($mime, $allowed)) {
            throw new \RuntimeException("Hindi pinahihintulutang uri ng file mula sa link: {$mime}.");
        }

        $type = preg_replace('/[^a-z0-9\-]/', '', strtolower($type));
        if ($type === '') {
            throw new \RuntimeException('Invalid upload type.');
        }

        $publicRoot = \dirname(__DIR__, 2) . '/public';
        $uploadDir  = $publicRoot . '/uploads/' . $type;

        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new \RuntimeException("Nabigong gumawa ng upload directory: {$uploadDir}");
        }

        $filename = bin2hex(random_bytes(16)) . $allowed[$mime];
        if (@file_put_contents($uploadDir . DIRECTORY_SEPARATOR . $filename, $bytes) === false) {
            throw new \RuntimeException('Nabigong i-save ang file mula sa link.');
        }

        return '/uploads/' . $type . '/' . $filename;
    }

    /**
     * Accept a path the import panel put in a hidden form field.
     *
     * The file is already on disk — storeFetched() validated and wrote it — but
     * the PATH arrives back through the browser, so it is treated as untrusted
     * input on the way in. Anything that is not a name this class itself
     * generated is refused, which stops a crafted field pointing a post's cover
     * image at some other file on the server.
     *
     * @param list<string> $extensions Allowed suffixes, e.g. ['.jpg', '.png'].
     * @return string|null The path if it is genuinely ours, null otherwise.
     */
    public function acceptImported(string $path, string $type, array $extensions): ?string
    {
        $type = preg_replace('/[^a-z0-9\-]/', '', strtolower($type)) ?? '';
        if ($type === '' || $path === '') {
            return null;
        }

        // storeFetched() names files as 32 hex characters plus a known
        // extension, so the shape alone rules out anything we did not write.
        $suffix = implode('|', array_map(
            static fn (string $ext): string => preg_quote(ltrim($ext, '.'), '#'),
            $extensions
        ));

        if (!preg_match('#^/uploads/' . preg_quote($type, '#') . '/[a-f0-9]{32}\.(?:' . $suffix . ')$#', $path)) {
            return null;
        }

        $absolute = \dirname(__DIR__, 2) . '/public' . $path;

        return is_file($absolute) ? $path : null;
    }

    // ── Private helpers ──────────────────────────────────────────────

    private function errorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE  => 'Lumagpas sa pinahintulutang sukat ng file.',
            UPLOAD_ERR_PARTIAL    => 'Hindi kumpleto ang pag-upload ng file.',
            UPLOAD_ERR_NO_FILE    => 'Walang file na na-upload.',
            UPLOAD_ERR_NO_TMP_DIR => 'Nawawala ang temporary upload directory.',
            UPLOAD_ERR_CANT_WRITE => 'Nabigong magsulat ng file sa disk.',
            UPLOAD_ERR_EXTENSION  => 'Pinigilan ng PHP extension ang upload.',
            default               => 'Nabigo ang pag-upload ng file.',
        };
    }
}
