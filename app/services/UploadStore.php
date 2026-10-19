<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Database copy of every file written under public/uploads/.
 *
 * Render's disk is wiped on every deploy and restart, so a file saved only to
 * public/uploads/ (a resident's valid ID, an avatar, a cover photo, an
 * ordinance PDF) disappeared while the database still pointed at it — the
 * broken "Valid ID" image and the 404 on "Open in new tab".
 *
 * Every writer calls UploadStore::keep() after saving to disk. A request for
 * /uploads/... that is not on disk falls through to public/index.php, which
 * calls UploadStore::serve(): the bytes come from the database and are put
 * back on disk so later requests are served by Apache directly.
 *
 * Valid IDs are personal data: from the database they are served only to
 * staff/admins or to the resident who uploaded them.
 */
final class UploadStore
{
    /** Bigger files stay disk-only rather than bloating the database. */
    private const MAX_BYTES = 12 * 1024 * 1024;

    private const PRIVATE_DIRS = ['id-photos'];

    private const MIME = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        'gif' => 'image/gif', 'pdf' => 'application/pdf', 'mp3' => 'audio/mpeg', 'webm' => 'audio/webm',
        'ogg' => 'audio/ogg', 'wav' => 'audio/wav', 'm4a' => 'audio/mp4',
    ];

    /**
     * Save a database copy of a file just written to public/uploads/.
     * Never throws: the disk copy already exists, so a failure here only
     * costs persistence across deploys, and is logged.
     *
     * @param string $publicPath e.g. /uploads/id-photos/ab12….jpg (leading slash optional)
     */
    public static function keep(string $publicPath, ?string $diskFile = null): void
    {
        $path = self::normalise($publicPath);
        if ($path === null) {
            return;
        }
        $diskFile ??= self::diskPath($path);
        try {
            if (!is_file($diskFile) || filesize($diskFile) > self::MAX_BYTES) {
                return;
            }
            $bytes = (string) file_get_contents($diskFile);
            $pdo   = db();
            $pdo->prepare('DELETE FROM uploaded_files WHERE path = ?')->execute([$path]);
            $pdo->prepare('INSERT INTO uploaded_files (path, mime, size, data) VALUES (?, ?, ?, ?)')
                ->execute([$path, self::mimeFor($path), strlen($bytes), base64_encode($bytes)]);
        } catch (\Throwable $e) {
            error_log('[UploadStore::keep] ' . $path . ': ' . $e->getMessage());
        }
    }

    /** Drop the database copy when the file itself is deleted. */
    public static function forget(string $publicPath): void
    {
        $path = self::normalise($publicPath);
        if ($path === null) {
            return;
        }
        try {
            db()->prepare('DELETE FROM uploaded_files WHERE path = ?')->execute([$path]);
        } catch (\Throwable $e) {
            error_log('[UploadStore::forget] ' . $e->getMessage());
        }
    }

    /**
     * Answer a request for a missing /uploads/ file from the database.
     * Returns false (and sends nothing) when there is no copy, so the caller
     * shows its normal 404.
     */
    public static function serve(string $uri): bool
    {
        $path = self::normalise($uri);
        if ($path === null) {
            return false;
        }
        try {
            $stmt = db()->prepare('SELECT mime, data FROM uploaded_files WHERE path = ?');
            $stmt->execute([$path]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[UploadStore::serve] ' . $e->getMessage());
            return false;
        }
        if (!$row) {
            return false;
        }

        $private = self::isPrivate($path);
        if ($private && !self::maySee($path)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Forbidden';
            return true;
        }

        $bytes = base64_decode((string) $row['data'], true);
        if ($bytes === false) {
            return false;
        }

        // Put it back on disk so Apache serves it until the next deploy.
        // Not for private files: on disk they would be readable by URL alone.
        if (!$private) {
            $disk = self::diskPath($path);
            if (is_dir(\dirname($disk)) || @mkdir(\dirname($disk), 0755, true)) {
                @file_put_contents($disk, $bytes);
            }
        }

        header('Content-Type: ' . $row['mime']);
        header('Content-Length: ' . strlen($bytes));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline');
        header($private ? 'Cache-Control: private, no-store' : 'Cache-Control: public, max-age=604800');
        echo $bytes;
        return true;
    }

    /** Whether an upload can still be shown: on disk now, or in the database copy. */
    public static function exists(string $publicPath): bool
    {
        $path = self::normalise($publicPath);
        if ($path === null) {
            return false;
        }
        if (is_file(self::diskPath($path))) {
            return true;
        }
        try {
            $stmt = db()->prepare('SELECT 1 FROM uploaded_files WHERE path = ?');
            $stmt->execute([$path]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /** "/uploads/{dir}/{file}" or null when the path is not a plain upload path. */
    public static function normalise(string $path): ?string
    {
        $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
        return preg_match('#^/uploads/[a-z0-9_-]+(?:/[a-z0-9_-]+)*/[A-Za-z0-9_-]+\.[A-Za-z0-9]{2,5}$#', $path) ? $path : null;
    }

    public static function isPrivate(string $path): bool
    {
        $dir = explode('/', ltrim($path, '/'))[1] ?? '';
        return in_array($dir, self::PRIVATE_DIRS, true);
    }

    private static function maySee(string $path): bool
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }
        if (in_array($_SESSION['role'] ?? '', ['staff', 'admin', 'superadmin'], true)) {
            return true;
        }
        try {
            $stmt = db()->prepare('SELECT 1 FROM users WHERE id = ? AND id_photo_url IN (?, ?)');
            $stmt->execute([$userId, $path, ltrim($path, '/')]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private static function diskPath(string $path): string
    {
        return \dirname(__DIR__, 2) . '/public' . $path;
    }

    private static function mimeFor(string $path): string
    {
        return self::MIME[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    }
}
