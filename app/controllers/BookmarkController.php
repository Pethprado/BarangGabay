<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Announcement;
use PDO;

/**
 * Resident "My Bookmarks": save announcements, events and ordinances.
 */
class BookmarkController
{
    private const TYPES = ['announcement', 'event', 'ordinance'];

    /** Whether the signed-in resident saved this item (for the button state). */
    public static function isSaved(string $type, int $id): bool
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }
        try {
            $stmt = db()->prepare('SELECT 1 FROM bookmarks WHERE user_id = ? AND content_type = ? AND content_id = ? LIMIT 1');
            $stmt->execute([$userId, $type, $id]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** POST /bookmarks/toggle — JSON {saved: bool}. */
    public function toggle(): void
    {
        header('Content-Type: application/json');
        check_csrf();
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $type   = (string) ($_POST['content_type'] ?? '');
        $id     = (int) ($_POST['content_id'] ?? 0);
        if (!in_array($type, self::TYPES, true) || $id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false]);
            return;
        }
        try {
            if (self::isSaved($type, $id)) {
                db()->prepare('DELETE FROM bookmarks WHERE user_id = ? AND content_type = ? AND content_id = ?')->execute([$userId, $type, $id]);
                echo json_encode(['success' => true, 'saved' => false]);
                return;
            }
            db()->prepare('INSERT INTO bookmarks (user_id, content_type, content_id, created_at) VALUES (?, ?, ?, NOW())')->execute([$userId, $type, $id]);
            echo json_encode(['success' => true, 'saved' => true]);
        } catch (\Throwable $e) {
            error_log('[BookmarkController::toggle] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false]);
        }
    }

    /** GET /bookmarks */
    public function index(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $items  = [];
        try {
            $stmt = db()->prepare('SELECT content_type, content_id, created_at FROM bookmarks WHERE user_id = ? ORDER BY created_at DESC');
            $stmt->execute([$userId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $b) {
                $row = match ($b['content_type']) {
                    'announcement' => $this->fetch('SELECT a.* FROM announcements a WHERE a.id = ? AND ' . Announcement::visibleSql('a'), (int) $b['content_id']),
                    'event'        => $this->fetch('SELECT * FROM events WHERE id = ?', (int) $b['content_id']),
                    'ordinance'    => $this->fetch("SELECT * FROM ordinances WHERE id = ? AND status IN ('active','repealed')", (int) $b['content_id']),
                    default        => null,
                };
                if ($row) {
                    $items[] = ['type' => $b['content_type'], 'row' => $row, 'saved_at' => $b['created_at']];
                }
            }
        } catch (\Throwable $e) {
            error_log('[BookmarkController::index] ' . $e->getMessage());
        }
        view('resident/bookmarks', ['pageTitle' => 'My Bookmarks', 'items' => $items]);
    }

    private function fetch(string $sql, int $id): ?array
    {
        $stmt = db()->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
