<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\SmsLog;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\PostSms;
use App\Services\OneWaySmsService;

/**
 * Admin SMS management — send, broadcast, view logs, check balance.
 *
 * All routes require ['auth', 'role:admin,staff'] middleware.
 */
class SmsController
{
    private OneWaySmsService $sms;

    public function __construct()
    {
        $this->sms = new OneWaySmsService();
    }

    // ── Pages ─────────────────────────────────────────────────────────────

    /**
     * GET /admin/sms
     * Main SMS dashboard: stats, logs, manual send form, bulk broadcast.
     */
    public function index(): void
    {
        $page    = max(1, (int) ($_GET['page']   ?? 1));
        $type    = trim($_GET['type']   ?? '');
        $status  = trim($_GET['status'] ?? '');
        $perPage = 20;

        $result   = SmsLog::paginate($page, $perPage, $type, $status);
        $stats    = SmsLog::stats();

        $recipientCount = (int) db()->query(
            "SELECT COUNT(*) FROM users WHERE status = 'verified' AND role = 'resident'
             AND phone IS NOT NULL AND phone != ''"
        )->fetchColumn();

        $pendingCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();

        /*
         * The puroks that actually have someone to text, with their counts.
         *
         * Read from users.zone rather than from a hardcoded list: a purok with
         * nobody reachable in it should not be offered as a send target, and a
         * purok that exists in the data but not in the code should still
         * appear. countReachableByPurok() already answers exactly this.
         */
        $purokReach = [];
        try {
            foreach (User::countReachableByPurok() as $purok => $n) {
                if (trim((string) $purok) !== '' && $n > 0) {
                    $purokReach[(string) $purok] = (int) $n;
                }
            }
            ksort($purokReach);
        } catch (\Throwable $e) {
            error_log('[SmsController::index] purok counts unavailable: ' . $e->getMessage());
        }

        // Step 4: /admin/sms?post_type=event&post_id=12 opens the panel with
        // that post already chosen, so "Send by SMS" on a list row lands
        // somewhere useful instead of on an empty form.
        $preType = trim((string) ($_GET['post_type'] ?? ''));
        $preType = \in_array($preType, ['announcement', 'event', 'ordinance'], true) ? $preType : '';
        $preId   = (int) ($_GET['post_id'] ?? 0);

        view('admin/sms/index', [
            'logs'           => $result['items'],
            'total'          => $result['total'],
            'page'           => $page,
            'perPage'        => $perPage,
            'stats'          => $stats,
            'recipientCount' => $recipientCount,
            'pendingCount'   => $pendingCount,
            'filterType'     => $type,
            'filterStatus'   => $status,
            'smsConfigured'  => $this->sms->isConfigured(),
            'testMode'       => $this->sms->isTestMode(),
            'purokReach'     => $purokReach,
            'preselectType'  => $preType,
            'preselectId'    => $preId,
        ]);
    }

    // ── Actions ───────────────────────────────────────────────────────────

    /**
     * POST /admin/sms/send
     * Send a test / manual SMS to a single number.
     */
    public function send(): void
    {
        check_csrf();

        $phone   = trim($_POST['phone']   ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($phone === '' || $message === '') {
            flash('error', t('flash.sms_number_message_req'));
            redirect('/admin/sms');
        }
        if (strlen($message) > 160) {
            flash('error', t('flash.sms_too_long'));
            redirect('/admin/sms');
        }

        $result = $this->sms->send($phone, $message, 'manual');

        if ($result['success']) {
            AuditLog::record(
                (int) $_SESSION['user_id'],
                'sms.manual_send',
                'Manual SMS sent to ' . $phone
            );
            flash('success', t('flash.sms_sent_to') . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '.');
        } else {
            flash('error', t('flash.sms_failed') . htmlspecialchars($result['error'] ?? 'Unknown error', ENT_QUOTES, 'UTF-8'));
        }

        redirect('/admin/sms');
    }

    /**
     * POST /admin/sms/broadcast
     * Broadcast a custom message to all verified residents with a phone number.
     */
    public function broadcast(): void
    {
        check_csrf();

        $message = trim($_POST['message'] ?? '');

        if ($message === '') {
            flash('error', t('flash.sms_message_required'));
            redirect('/admin/sms');
        }
        if (strlen($message) > 160) {
            flash('error', t('flash.sms_too_long'));
            redirect('/admin/sms');
        }

        $phones = $this->getVerifiedPhones();

        if (empty($phones)) {
            flash('error', t('flash.sms_no_recipients'));
            redirect('/admin/sms');
        }

        $result = $this->sms->sendBulk($phones, $message, 'broadcast');

        AuditLog::record(
            (int) $_SESSION['user_id'],
            'sms.broadcast',
            'Broadcast to ' . count($phones) . ' residents: ' . substr($message, 0, 80)
        );

        $sent   = $result['sent']   ?? 0;
        $failed = $result['failed'] ?? 0;
        flash('success', t('flash.sms_broadcast_done', ['sent' => $sent, 'failed' => $failed, 'total' => count($phones)]));
        redirect('/admin/sms');
    }

    /**
     * GET /api/admin/sms/recipients?q=
     *
     * Verified residents who have a phone number, for the recipient picker on
     * the SMS page. Read-only, staff-level, and capped — it exists so nobody
     * has to type a number from memory into a blank box.
     *
     * Returns phone numbers, which are personal data, so it is behind the same
     * role gate as /admin/residents where staff can already see them. Results
     * are capped rather than open-ended so this cannot be used to walk the
     * whole resident list in one request.
     */
    public function recipients(): void
    {
        header('Content-Type: application/json');

        $query = trim((string) ($_GET['q'] ?? ''));

        try {
            $rows  = User::reachableBySms($query, 20);
            $total = User::countReachableBySms();
        } catch (\Throwable $e) {
            error_log('[SmsController::recipients] ' . $e->getMessage());
            echo json_encode(['results' => [], 'total' => 0]);
            return;
        }

        echo json_encode([
            'results' => array_map(static fn (array $r): array => [
                'id'    => (int) $r['id'],
                'name'  => (string) $r['full_name'],
                'phone' => (string) $r['phone'],
                'zone'  => (string) ($r['zone'] ?? ''),
            ], $rows),
            'total' => $total,
        ]);
    }

    /**
     * GET /api/admin/sms/posts?type=&q=
     *
     * Published posts of one type, for the "Send a Post by SMS" picker.
     *
     * Each row carries the message that would actually be sent, built by the
     * same PostSms the auto-send uses, plus its length — so the staff member
     * sees the real text and its credit cost before choosing, not a guess
     * assembled in JavaScript that could differ from what the server sends.
     *
     * Capped like recipients(). This returns titles rather than personal data,
     * but it stays behind the same role gate: an unauthenticated list of
     * everything the barangay has published, searchable, is not something to
     * hand out casually.
     */
    public function posts(): void
    {
        header('Content-Type: application/json');

        $type  = trim((string) ($_GET['type'] ?? 'announcement'));
        $query = trim((string) ($_GET['q'] ?? ''));

        if (!\in_array($type, ['announcement', 'event', 'ordinance'], true)) {
            echo json_encode(['results' => [], 'total' => 0]);
            return;
        }

        try {
            $rows = $this->findPosts($type, $query, 20);
        } catch (\Throwable $e) {
            error_log('[SmsController::posts] ' . $e->getMessage());
            echo json_encode(['results' => [], 'total' => 0]);
            return;
        }

        $results = [];
        foreach ($rows as $row) {
            $message  = PostSms::build($type, $row);
            $lastSent = SmsLog::lastForPost($type, (int) $row['id']);

            $results[] = [
                'id'        => (int) $row['id'],
                'title'     => (string) $row['title'],
                'date'      => (string) ($row['sort_date'] ?? ''),
                'is_sample' => (int) ($row['is_sample'] ?? 0) === 1,
                'message'   => $message,
                'length'    => mb_strlen($message),
                'segments'  => max(1, (int) ceil(mb_strlen($message) / PostSms::LIMIT)),
                // Null unless this post has already gone out — the panel turns
                // this into the "already sent" warning.
                'last_sent' => $lastSent,
            ];
        }

        echo json_encode(['results' => $results, 'total' => count($results)]);
    }

    /**
     * POST /admin/sms/post
     *
     * Send an already-published post to residents as SMS.
     *
     * Everything that decides what goes out and to whom is settled here, not
     * in the form. The browser sends a post id, a recipient mode and possibly
     * an edited message; this re-loads the post, re-resolves the recipients
     * and re-checks the length. A form field is a request, not an instruction
     * — and this one spends money.
     */
    public function sendPost(): void
    {
        check_csrf();

        if (!$this->sms->isConfigured()) {
            flash('error', t('flash.sms_not_configured'));
            redirect('/admin/sms');
        }

        $type   = trim((string) ($_POST['post_type'] ?? ''));
        $postId = (int) ($_POST['post_id'] ?? 0);

        if (!\in_array($type, ['announcement', 'event', 'ordinance'], true)) {
            flash('error', t('flash.sms_post_type_invalid'));
            redirect('/admin/sms');
        }

        $post = $this->loadPublishedPost($type, $postId);
        if ($post === null) {
            flash('error', t('flash.sms_post_not_found'));
            redirect('/admin/sms');
        }

        /*
         * The message.
         *
         * Staff may edit the preview — a notice sometimes needs a word changed
         * before it goes out — so their text wins when they supplied one. It
         * is re-measured here regardless: the form's maxlength is a courtesy
         * to the typist, not a limit anyone has to respect.
         */
        $edited  = trim((string) ($_POST['message'] ?? ''));
        $message = $edited !== '' ? $edited : PostSms::build($type, $post);

        if ($message === '') {
            flash('error', t('flash.sms_message_required'));
            redirect('/admin/sms');
        }
        if (mb_strlen($message) > PostSms::LIMIT) {
            flash('error', t('flash.sms_too_long'));
            redirect('/admin/sms');
        }

        // ── Who receives it ──────────────────────────────────────────────
        $mode   = trim((string) ($_POST['recipient_mode'] ?? 'all'));
        $phones = $this->resolveRecipients($mode);

        if ($phones === []) {
            flash('error', t('flash.sms_no_recipients'));
            redirect('/admin/sms');
        }

        // ── Send ─────────────────────────────────────────────────────────
        // reference_id is the post, so every row in sms_logs points back at
        // what was sent and lastForPost() can warn on a repeat.
        $result = $this->sms->sendBulk($phones, $message, $type, $postId);

        $sent   = (int) ($result['sent']   ?? 0);
        $failed = (int) ($result['failed'] ?? 0);

        AuditLog::record(
            (int) $_SESSION['user_id'],
            'sms.post',
            sprintf(
                'Sent %s #%d to %d number(s) [%s]: %s',
                $type,
                $postId,
                count($phones),
                $mode,
                mb_substr($message, 0, 80)
            )
        );

        flash(
            $failed > 0 && $sent === 0 ? 'error' : 'success',
            t($this->sms->isTestMode() ? 'flash.sms_post_done_test' : 'flash.sms_post_done', [
                'sent'   => (string) $sent,
                'failed' => (string) $failed,
                'total'  => (string) count($phones),
            ])
        );
        redirect('/admin/sms');
    }

    /**
     * GET /api/admin/sms/balance
     * Returns Semaphore account balance as JSON (AJAX).
     */
    public function balance(): void
    {
        header('Content-Type: application/json');

        if (!$this->sms->isConfigured()) {
            echo json_encode(['error' => 'SMS API key not configured.']);
            return;
        }

        $data = $this->sms->getBalance();
        if ($data === null) {
            http_response_code(503);
            echo json_encode(['error' => 'Could not reach Semaphore API. Try again later.']);
            return;
        }

        echo json_encode($data);
    }

    // ── Internal helpers ──────────────────────────────────────────────────

    /**
     * Fetch phone numbers of all verified residents who provided one.
     *
     * @return string[]
     */
    // ── Internals for the post sender ────────────────────────────────────

    /**
     * Published posts of one type, newest first, optionally filtered.
     *
     * Each type lives in its own table with its own idea of "published" and
     * its own date column, so the three queries are written out rather than
     * generated. The column names are literals in this file; only the search
     * term is bound, and it is the only thing that comes from input.
     *
     * @return list<array<string,mixed>>
     */
    private function findPosts(string $type, string $query, int $limit): array
    {
        $like = '%' . $query . '%';

        [$sql, $params] = match ($type) {
            'event' => [
                "SELECT id, title, slug, venue, event_date, is_sample,
                        event_date AS sort_date
                   FROM events
                  WHERE (? = '' OR title LIKE ?)
                  ORDER BY event_date DESC
                  LIMIT {$limit}",
                [$query, $like],
            ],
            'ordinance' => [
                "SELECT id, title, ordinance_no, is_sample,
                        COALESCE(enacted_date, created_at) AS sort_date
                   FROM ordinances
                  WHERE status = 'active' AND (? = '' OR title LIKE ? OR ordinance_no LIKE ?)
                  ORDER BY sort_date DESC
                  LIMIT {$limit}",
                [$query, $like, $like],
            ],
            default => [
                // Published AND already live: a scheduled post must not be
                // textable before its own moment arrives.
                "SELECT id, title, slug, urgency, is_sample,
                        published_at AS sort_date
                   FROM announcements
                  WHERE status = 'published'
                    AND (published_at IS NULL OR published_at <= NOW())
                    AND (? = '' OR title LIKE ?)
                  ORDER BY published_at DESC
                  LIMIT {$limit}",
                [$query, $like],
            ],
        };

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Load one post, refusing anything that is not live.
     *
     * A draft, an archived notice or a scheduled post that has not reached its
     * moment must not be textable — residents would receive a message about
     * something they cannot then read on the portal.
     *
     * @return array<string,mixed>|null
     */
    private function loadPublishedPost(string $type, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $sql = match ($type) {
            'event'     => 'SELECT * FROM events WHERE id = ? LIMIT 1',
            'ordinance' => "SELECT * FROM ordinances WHERE id = ? AND status = 'active' LIMIT 1",
            default     => "SELECT * FROM announcements
                             WHERE id = ? AND status = 'published'
                               AND (published_at IS NULL OR published_at <= NOW())
                             LIMIT 1",
        };

        $stmt = db()->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * The phone numbers for the chosen recipient mode.
     *
     * Resolved server-side from the mode, never taken from a hidden field of
     * numbers — a form that carries its own recipient list is a form that can
     * be edited to text anyone.
     *
     * Hand-typed numbers are the one exception and are kept: staff sometimes
     * need to reach someone who is not a registered resident. They are
     * normalised and de-duplicated against the rest.
     *
     * @return list<string>
     */
    private function resolveRecipients(string $mode): array
    {
        $phones = match ($mode) {
            'purok'    => self::getVerifiedPhonesInPurok(trim((string) ($_POST['purok'] ?? ''))),
            'selected' => $this->phonesForUserIds((array) ($_POST['user_ids'] ?? [])),
            default    => self::getVerifiedPhones(),
        };

        // Numbers typed by hand, on any mode.
        foreach (preg_split('/[\s,;]+/', (string) ($_POST['extra_numbers'] ?? '')) ?: [] as $raw) {
            $clean = preg_replace('/[^0-9+]/', '', $raw) ?? '';
            if ($clean !== '' && mb_strlen($clean) >= 10) {
                $phones[] = $clean;
            }
        }

        return array_values(array_unique(array_filter(array_map('trim', $phones))));
    }

    /**
     * Phone numbers for specific residents, by id.
     *
     * The ids are re-checked against verified residents with a number — the
     * picker only ever offers those, but the form could say otherwise.
     *
     * @param  array<int|string> $ids
     * @return list<string>
     */
    private function phonesForUserIds(array $ids): array
    {
        $clean = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($clean === []) {
            return [];
        }

        $in   = implode(',', array_fill(0, count($clean), '?'));
        $stmt = db()->prepare(
            "SELECT phone FROM users
              WHERE id IN ({$in})
                AND status = 'verified' AND role = 'resident'
                AND phone IS NOT NULL AND TRIM(phone) <> ''"
        );
        $stmt->execute($clean);

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public static function getVerifiedPhones(): array
    {
        $stmt = db()->query(
            "SELECT phone FROM users
             WHERE status = 'verified' AND role = 'resident'
               AND phone IS NOT NULL AND phone != ''"
        );
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Verified residents of one purok who gave a phone number.
     *
     * Every text message costs credits, so a notice that concerns one of the
     * barangay's seven puroks should not be paid for seven times. The second
     * cost is attention: residents who keep receiving notices that are not
     * about them stop opening the ones that are.
     *
     * Returns an empty list for an unknown purok rather than falling back to
     * everyone — a typo in a purok name must not quietly turn a targeted
     * message into a barangay-wide broadcast.
     *
     * @return list<string>
     */
    public static function getVerifiedPhonesInPurok(string $purok): array
    {
        $purok = trim($purok);
        if ($purok === '') {
            return [];
        }

        $stmt = db()->prepare(
            "SELECT phone FROM users
             WHERE status = 'verified' AND role = 'resident'
               AND zone = ?
               AND phone IS NOT NULL AND phone != ''"
        );
        $stmt->execute([$purok]);

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
