<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

class DocumentStorageService
{
    public const MAX_BYTES = 10485760; // 10 MB

    public const ALLOWED_MIMES = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
    ];

    public const ALLOWED_EXTS = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

    /**
     * Validate an uploaded file array from $_FILES.
     *
     * @param array<string,mixed> $file
     * @return array{valid: bool, error: ?string, mime: string, ext: string, size: int, clean_name: string}
     */
    public function validateUpload(array $file): array
    {
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return [
                'valid'      => false,
                'error'      => 'No file was uploaded.',
                'mime'       => '',
                'ext'        => '',
                'size'       => 0,
                'clean_name' => '',
            ];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errMessage = match ($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ang dokumento ay lumagpas sa 10MB na limitasyon.',
                UPLOAD_ERR_PARTIAL => 'Hindi natapos ang pag-upload ng file.',
                default => 'Nagka-problema sa pag-upload ng file.',
            };
            return [
                'valid'      => false,
                'error'      => $errMessage,
                'mime'       => '',
                'ext'        => '',
                'size'       => 0,
                'clean_name' => '',
            ];
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            return [
                'valid'      => false,
                'error'      => 'Ang laki ng dokumento ay dapat hindi hihigit sa 10MB.',
                'mime'       => '',
                'ext'        => '',
                'size'       => $size,
                'clean_name' => '',
            ];
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmpPath)) {
            return [
                'valid'      => false,
                'error'      => 'Di-wastong file upload.',
                'mime'       => '',
                'ext'        => '',
                'size'       => $size,
                'clean_name' => '',
            ];
        }

        // Detect real MIME type using Fileinfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo ? (string) finfo_file($finfo, $tmpPath) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        if ($detectedMime === '' || !array_key_exists($detectedMime, self::ALLOWED_MIMES)) {
            // Some systems report docx as application/zip or octet-stream, check extension + mime
            $origName = (string) ($file['name'] ?? '');
            $origExt  = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            if ($origExt === 'docx' && ($detectedMime === 'application/zip' || $detectedMime === 'application/octet-stream')) {
                $detectedMime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            } elseif ($origExt === 'doc' && $detectedMime === 'application/octet-stream') {
                $detectedMime = 'application/msword';
            } else {
                return [
                    'valid'      => false,
                    'error'      => 'Di-wastong format. Ang tinatanggap lamang ay PDF, DOC, DOCX, JPG, o PNG.',
                    'mime'       => $detectedMime,
                    'ext'        => $origExt,
                    'size'       => $size,
                    'clean_name' => '',
                ];
            }
        }

        $origName = (string) ($file['name'] ?? 'document');
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, self::ALLOWED_EXTS, true)) {
            $ext = self::ALLOWED_MIMES[$detectedMime] ?? 'pdf';
        }

        // Clean original base name
        $rawBaseName = pathinfo($origName, PATHINFO_FILENAME);
        $cleanBase   = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $rawBaseName);
        $cleanBase   = trim((string) preg_replace('/_+/', '_', $cleanBase), '_');
        if ($cleanBase === '') {
            $cleanBase = 'document';
        }
        $cleanName = mb_substr($cleanBase, 0, 100) . '.' . $ext;

        return [
            'valid'      => true,
            'error'      => null,
            'mime'       => $detectedMime,
            'ext'        => $ext,
            'size'       => $size,
            'clean_name' => $cleanName,
        ];
    }

    /**
     * Persist uploaded document into database blob (for Render/ephemeral safety)
     * and optionally cache to local filesystem.
     *
     * @param int $requestId
     * @param array<string,mixed> $validated
     * @param string $tmpPath
     * @return array{stored: bool, clean_name: string, mime: string, size: int, url: string}
     */
    public function store(int $requestId, array $validated, string $tmpPath): array
    {
        $rawBytes = file_get_contents($tmpPath);
        if ($rawBytes === false) {
            throw new \RuntimeException('Failed to read uploaded temporary file.');
        }

        $cleanName = (string) $validated['clean_name'];
        $mime      = (string) $validated['mime'];
        $size      = (int) $validated['size'];

        // 1. Store in Database Table document_request_files
        $pdo = db();
        $checkStmt = $pdo->prepare('SELECT id FROM document_request_files WHERE request_id = ? LIMIT 1');
        $checkStmt->execute([$requestId]);
        $existingId = $checkStmt->fetchColumn();

        if ($existingId) {
            $stmt = $pdo->prepare(
                'UPDATE document_request_files
                    SET file_name = ?, file_type = ?, file_size = ?, file_data = ?, updated_at = NOW()
                  WHERE request_id = ?'
            );
            $stmt->bindParam(1, $cleanName, PDO::PARAM_STR);
            $stmt->bindParam(2, $mime, PDO::PARAM_STR);
            $stmt->bindParam(3, $size, PDO::PARAM_INT);
            $stmt->bindParam(4, $rawBytes, PDO::PARAM_LOB);
            $stmt->bindParam(5, $requestId, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO document_request_files
                 (request_id, file_name, file_type, file_size, file_data, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->bindParam(1, $requestId, PDO::PARAM_INT);
            $stmt->bindParam(2, $cleanName, PDO::PARAM_STR);
            $stmt->bindParam(3, $mime, PDO::PARAM_STR);
            $stmt->bindParam(4, $size, PDO::PARAM_INT);
            $stmt->bindParam(5, $rawBytes, PDO::PARAM_LOB);
            $stmt->execute();
        }

        // 2. Local Disk Cache (if writable)
        $diskPath = $this->getDiskCachePath($requestId, $cleanName);
        if ($diskPath !== null) {
            @file_put_contents($diskPath, $rawBytes);
        }

        $downloadUrl = '/documents/' . $requestId . '/download';

        return [
            'stored'     => true,
            'clean_name' => $cleanName,
            'mime'       => $mime,
            'size'       => $size,
            'url'        => $downloadUrl,
        ];
    }

    /**
     * Retrieve document file row from database.
     *
     * @param int $requestId
     * @return array{file_name: string, file_type: string, file_size: int, file_data: string}|null
     */
    public function getFile(int $requestId): ?array
    {
        $stmt = db()->prepare(
            'SELECT file_name, file_type, file_size, file_data
               FROM document_request_files
              WHERE request_id = ? LIMIT 1'
        );
        $stmt->execute([$requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $data = $row['file_data'];
        if (is_resource($data)) {
            $data = (string) stream_get_contents($data);
        }

        return [
            'file_name' => (string) $row['file_name'],
            'file_type' => (string) $row['file_type'],
            'file_size' => (int) $row['file_size'],
            'file_data' => (string) $data,
        ];
    }

    /**
     * Delete document file from database and cache.
     */
    public function deleteFile(int $requestId): bool
    {
        $stmt = db()->prepare('DELETE FROM document_request_files WHERE request_id = ?');
        $stmt->execute([$requestId]);

        // Clean disk cache if exists
        $uploadDir = $this->ensureStorageDir();
        if ($uploadDir !== null) {
            $pattern = $uploadDir . DIRECTORY_SEPARATOR . 'req_' . $requestId . '_*';
            $matches = glob($pattern) ?: [];
            foreach ($matches as $match) {
                @unlink($match);
            }
        }

        return true;
    }

    /**
     * Stream file bytes to HTTP client for inline preview or download.
     *
     * @param array{file_name: string, file_type: string, file_size: int, file_data: string} $file
     * @param bool $inline
     * @param string|null $downloadAsName
     */
    public function streamFile(array $file, bool $inline = false, ?string $downloadAsName = null): void
    {
        $mime = $file['file_type'];
        $size = strlen($file['file_data']);
        $name = $downloadAsName ?: $file['file_name'];

        // Clean filename for HTTP header
        $cleanHeaderName = preg_replace('/[^\w\.\-]/', '_', $name);

        $disposition = ($inline && $this->isViewableInline($mime)) ? 'inline' : 'attachment';

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . $size);
        header(sprintf('Content-Disposition: %s; filename="%s"', $disposition, $cleanHeaderName));
        header('Cache-Control: private, no-transform, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');

        echo $file['file_data'];
        exit;
    }

    public function isViewableInline(string $mime): bool
    {
        return in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true);
    }

    /**
     * Helper to get local disk cache path.
     */
    private function getDiskCachePath(int $requestId, string $cleanName): ?string
    {
        $dir = $this->ensureStorageDir();
        if ($dir === null) {
            return null;
        }

        return $dir . DIRECTORY_SEPARATOR . 'req_' . $requestId . '_' . $cleanName;
    }

    /**
     * Ensure storage directory exists and has protection against PHP script execution.
     */
    private function ensureStorageDir(): ?string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'documents';

        if (!is_dir($path)) {
            if (!@mkdir($path, 0755, true) && !is_dir($path)) {
                return null;
            }
        }

        $htaccess = $path . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "# Prevent script execution in uploads directory\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|phps|phar)$\">\n    Order Deny,Allow\n    Deny from all\n</FilesMatch>\n");
        }

        return $path;
    }
}
