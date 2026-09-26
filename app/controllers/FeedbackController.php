<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Feedback;
use App\Models\Notification;
use App\Models\AuditLog;
use App\Services\NotificationService;

class FeedbackController
{
    // Message length bounds shared by both the resident's opening message and every reply.
    private const MSG_MIN = 5;
    private const MSG_MAX = 1000;

    // ── Resident ─────────────────────────────────────────────────────────────

    /** GET /feedback */
    public function index(): void
    {
        $userId = (int) $_SESSION['user_id'];

        $feedbacks = Feedback::allForUser($userId);
        foreach ($feedbacks as &$fb) {
            $fb['messages'] = Feedback::findMessages((int) $fb['id']);
        }
        unset($fb);

        // Viewing the page counts as reading every staff/admin reply across all threads.
        Feedback::markResidentReadForUser($userId);

        view('resident/feedback', compact('feedbacks'));
    }

    /** POST /feedback — opens a new thread */
    public function store(): void
    {
        check_csrf();

        $userId  = (int) $_SESSION['user_id'];
        $message = \trim($_POST['message'] ?? '');

        if ($error = $this->validateMessage($message)) {
            flash('error', $error);
            redirect('/feedback');
        }

        $feedbackId = Feedback::create([
            'user_id'     => $userId,
            'message'     => $message,
            'sender_role' => $_SESSION['role'] ?? 'resident',
        ]);
        AuditLog::record($userId, 'feedback.sent', 'Feedback thread opened');

        $this->alertStaffOfMessage($feedbackId, $userId, true);

        flash('success', 'Naipadala na ang iyong mensahe. Abangan ang sagot mula sa aming mga kawani.');
        redirect('/feedback');
    }

    /** POST /feedback/{id}/reply — resident replying inside an existing thread */
    public function residentReply(array $params): void
    {
        check_csrf();

        $id      = (int) ($params['id'] ?? 0);
        $userId  = (int) $_SESSION['user_id'];
        $message = \trim($_POST['message'] ?? '');

        if ($error = $this->validateMessage($message)) {
            flash('error', $error);
            redirect('/feedback');
        }

        // Authorization: a resident may only reply into their own thread — never
        // trust the {id} in the URL alone, someone could increment it. redirect()
        // sends a plain 302 (it always overrides any prior http_response_code()
        // once it sets the Location header, so there's no real 403 to send here),
        // but the effect is the same as everywhere else in this codebase: blocked,
        // flashed error, no row written — verified via a live cross-user attempt.
        if (!Feedback::belongsToUser($id, $userId)) {
            flash('error', 'Hindi mo maa-access ang thread na ito.');
            redirect('/feedback');
        }

        Feedback::addMessage($id, $userId, $_SESSION['role'] ?? 'resident', $message);
        AuditLog::record($userId, 'feedback.reply', "Resident replied to feedback thread #{$id}");

        $this->alertStaffOfMessage($id, $userId, false);

        flash('success', 'Naipadala ang iyong sagot.');
        redirect('/feedback');
    }

    // ── Admin ─────────────────────────────────────────────────────────────────

    /** GET /admin/feedback */
    public function adminIndex(): void
    {
        $search    = \trim($_GET['search'] ?? '');
        $feedbacks = Feedback::allAdmin($search);
        $unread    = Feedback::unreadCountAdmin();
        $pendingCount = (int) db()->query(
            "SELECT COUNT(*) FROM users WHERE status = 'pending'"
        )->fetchColumn();
        $pageTitle = t('admin_feedback.page_title');

        view('admin/feedback/index', compact('feedbacks', 'unread', 'search', 'pendingCount', 'pageTitle'));
    }

    /** GET /admin/feedback/{id}/messages — full thread, for the reply modal (AJAX) */
    public function adminThread(array $params): void
    {
        header('Content-Type: application/json');

        $id       = (int) ($params['id'] ?? 0);
        $feedback = Feedback::find($id);
        if (!$feedback) {
            http_response_code(404);
            echo json_encode(['error' => t('flash.fb_thread_not_found')]);
            return;
        }

        Feedback::markAdminRead($id);

        echo json_encode([
            'resident_name'  => $feedback['resident_name'],
            'resident_email' => $feedback['resident_email'],
            'messages'       => Feedback::findMessages($id),
        ]);
    }

    /** POST /admin/feedback/{id}/reply — appends a reply; works for staff and admin alike */
    public function adminReply(array $params): void
    {
        check_csrf();

        $id      = (int) ($params['id'] ?? 0);
        $adminId = (int) $_SESSION['user_id'];
        $role    = $_SESSION['role'] ?? 'staff';
        $reply   = \trim($_POST['reply'] ?? '');

        if ($reply === '') {
            flash('error', t('flash.fb_reply_required'));
            redirect('/admin/feedback');
        }
        if (\strlen($reply) > self::MSG_MAX) {
            flash('error', "Ang sagot ay masyadong mahaba (maximum " . self::MSG_MAX . " karakter).");
            redirect('/admin/feedback');
        }

        $feedback = Feedback::find($id);
        if (!$feedback) {
            flash('error', t('flash.fb_message_not_found'));
            redirect('/admin/feedback');
        }

        Feedback::addMessage($id, $adminId, $role, $reply);
        Feedback::markAdminRead($id);

        // In-app notification for the resident
        Notification::create([
            'user_id'      => (int) $feedback['user_id'],
            'title'        => 'Nasagot ang iyong mensahe',
            'message'      => 'Ang iyong mensahe ay nasagot na ng aming kawani. Bisitahin ang Feedback page para makita ang sagot.',
            'type'         => 'system',
            'related_id'   => null,
            'related_type' => null,
        ]);

        AuditLog::record($adminId, 'feedback.reply', "Replied to feedback thread #{$id}");
        flash('success', t('flash.fb_reply_sent'));
        redirect('/admin/feedback');
    }

    // ── Private ──────────────────────────────────────────────────────────────

    /**
     * Tell back-office users a resident is waiting on them.
     *
     * Only reaches staff/admin/superadmin who have the "new feedback message"
     * preference on (/admin/account → Preferences); the sending resident is
     * skipped. Best-effort — an alert must never fail the message itself.
     */
    private function alertStaffOfMessage(int $feedbackId, int $residentId, bool $isNewThread): void
    {
        // Written in English, not through t(). These notifications are stored
        // for STAFF, but they are created inside a resident's request — so t()
        // would render them in whichever language that resident happens to be
        // using, and the staff member would later read a notice in Manobo.
        // The audience is fixed, so the language is too.
        $name = $_SESSION['full_name'] ?? 'A resident';

        (new NotificationService())->notifyBackOffice(
            'notify_feedback',
            'system',
            $isNewThread ? 'New resident feedback' : 'New reply on feedback',
            $isNewThread
                ? "{$name} sent a new message. Open the Feedback page to read it."
                : "{$name} replied on a feedback thread. Open the Feedback page to read it.",
            $feedbackId,
            'feedback',
            $residentId
        );
    }

    /**
     * Returns a localised error message if invalid, or null if it passes.
     *
     * Goes through t() because both audiences reach it: a resident submitting
     * feedback sees it in their chosen language, a staff member replying sees
     * it in English with the rest of the back office.
     */
    private function validateMessage(string $message): ?string
    {
        if (\strlen($message) < self::MSG_MIN) {
            return t('flash.msg_too_short', ['n' => self::MSG_MIN]);
        }
        if (\strlen($message) > self::MSG_MAX) {
            return t('flash.msg_too_long', ['n' => self::MSG_MAX]);
        }
        return null;
    }
}