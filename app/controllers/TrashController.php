<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\PostTrash;

/**
 * /admin/trash — deleted announcements, events and ordinances.
 *
 * Staff see and restore the posts they wrote; admins see and restore every
 * post. Only admins can delete from the Trash for good.
 */
class TrashController
{
    private static function isAdmin(): bool
    {
        return \in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'], true);
    }

    /** GET /admin/trash */
    public function index(): void
    {
        $type      = (string) ($_GET['type'] ?? '');
        $type      = isset(PostTrash::TYPES[$type]) ? $type : null;
        $me        = (int) ($_SESSION['user_id'] ?? 0);
        $isAdmin   = self::isAdmin();
        $items     = PostTrash::all($isAdmin ? null : $me, $type);
        $pageTitle = 'Trash';
        $pendingCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();

        view('admin/trash/index', compact('items', 'type', 'isAdmin', 'pageTitle', 'pendingCount'));
    }

    /** POST /admin/trash/{id}/restore — put the post back, live as it was. */
    public function restore(array $params): void
    {
        check_csrf();
        $item = PostTrash::find((int) ($params['id'] ?? 0));
        if ($item === null) {
            flash('error', 'That item is no longer in the Trash.');
            redirect('/admin/trash');
        }
        if (!self::isAdmin() && (int) $item['author_id'] !== (int) ($_SESSION['user_id'] ?? 0)) {
            flash('error', 'You can only restore posts that you wrote.');
            redirect('/admin/trash');
        }

        try {
            $done = PostTrash::restore((int) $item['id']);
        } catch (\Throwable $e) {
            error_log('[TrashController::restore] ' . $e->getMessage());
            flash('error', 'Could not restore "' . $item['title'] . '": ' . $e->getMessage());
            redirect('/admin/trash');
        }

        AuditLog::record((int) $_SESSION['user_id'], $done['type'] . '.restore', 'Restored from Trash: #' . $done['id'] . ' ' . $done['title']);
        flash('success', '"' . $done['title'] . '" was restored and is back where it was'
            . ($done['renamed'] ? ' (its link was renamed because another post now uses the old one).' : '.'));

        $lists = ['announcement' => '/admin/announcements', 'event' => '/admin/events', 'ordinance' => '/admin/ordinances'];
        redirect($lists[$done['type']] . (self::isAdmin() ? '' : '?mine=1'));
    }

    /** POST /admin/trash/{id}/delete — admin only: delete for good. */
    public function destroy(array $params): void
    {
        check_csrf();
        $item = PostTrash::purge((int) ($params['id'] ?? 0));
        if ($item === null) {
            flash('error', 'That item is no longer in the Trash.');
            redirect('/admin/trash');
        }

        AuditLog::record((int) $_SESSION['user_id'], $item['content_type'] . '.delete', 'Deleted permanently from Trash: #' . $item['content_id'] . ' ' . $item['title']);
        flash('success', '"' . $item['title'] . '" was deleted permanently.');
        redirect('/admin/trash');
    }

    /** POST /admin/trash/empty — admin only: delete everything in the Trash for good. */
    public function emptyTrash(): void
    {
        check_csrf();
        $n = 0;
        foreach (PostTrash::all() as $item) {
            if (PostTrash::purge((int) $item['id']) !== null) {
                $n++;
            }
        }

        AuditLog::record((int) $_SESSION['user_id'], 'trash.empty', "Emptied the Trash ({$n} items deleted permanently)");
        flash('success', $n . ' item(s) deleted permanently. The Trash is empty.');
        redirect('/admin/trash');
    }
}
