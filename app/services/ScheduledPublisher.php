<?php
declare(strict_types=1);

namespace App\Services;

use App\Controllers\SmsController;
use App\Models\Announcement;
use App\Models\User;

/**
 * Turns a due announcement into a published one — the side-effect half of
 * publishing.
 *
 * Publishing an announcement is two separate things, and separating them is
 * the whole design of the scheduling feature:
 *
 *   Visibility  is decided by a WHERE clause (Announcement::visibleSql()). It
 *               needs nothing to run. A post scheduled for Monday 8am is on
 *               the resident's screen at 8am even if this class never
 *               executes, because "is it visible" is recomputed on every read.
 *
 *   Dispatch    is the in-app notification, the SMS blast and the email batch.
 *               These have side effects that cannot be undone or recomputed,
 *               so they must fire exactly once — which means something has to
 *               actually run, and something has to remember it ran.
 *
 * This class is that something. It is deliberately the same code path used
 * when staff hit "publish now", so a scheduled post and an immediate post
 * notify residents identically — there is no second, subtly-different
 * publishing routine to drift out of sync.
 */
class ScheduledPublisher
{
    /**
     * How many posts one sweep will dispatch.
     *
     * Low on purpose. A sweep runs inside somebody's ordinary page load, and
     * each post here can mean an SMS to every resident with a phone. Clearing
     * a backlog two posts at a time across several page loads is much kinder
     * than making one staff member wait on a hundred HTTP calls to Semaphore.
     */
    private const SWEEP_BATCH = 2;

    /**
     * Dispatch the go-live notifications for one announcement.
     *
     * Claims the row before doing any work: if another request got there
     * first, this returns false and sends nothing. The claim is released again
     * if the dispatch throws, so a transient failure (SMTP down, Semaphore
     * unreachable) is retried by a later sweep rather than silently swallowing
     * the announcement.
     *
     * @param array<string,mixed> $post  Row with at least id, title, slug, urgency.
     * @return bool True if this call is the one that sent the notifications.
     */
    public function dispatch(array $post, ?int $actorId = null): bool
    {
        $id = (int) ($post['id'] ?? 0);
        if ($id <= 0) {
            return false;
        }

        // Claim first, send second. Whoever wins the UPDATE owns the dispatch.
        if (!Announcement::claimForNotification($id)) {
            return false;
        }

        $title   = (string) ($post['title']   ?? '');
        $slug    = (string) ($post['slug']    ?? '');
        $urgency = (string) ($post['urgency'] ?? 'normal');
        $body    = (string) ($post['body']    ?? '');
        $actorId = $actorId ?? (int) ($post['author_id'] ?? 0);

        /*
         * Which purok this notice is for.
         *
         * Empty means the whole barangay, which is the ordinary case and what
         * every post did before this existed — so a row saved without the
         * column, or by a staff member who did not choose, behaves exactly as
         * it always has.
         *
         * When it IS set, only that purok is notified and texted. The post
         * itself stays readable by everyone on the site; narrowing the push is
         * not the same as hiding a public notice, and only the first is right
         * on a government notice board.
         *
         * The reason this matters is cost and attention. A water interruption
         * in one purok used to text all seven — six times the money for a
         * message most recipients cannot act on, and every such message teaches
         * residents that barangay SMS is usually not about them. The one they
         * then ignore might be a storm-surge advisory.
         */
        $purok = trim((string) ($post['target_purok'] ?? ''));

        try {
            $notifications = new NotificationService();

            if ($purok !== '') {
                $notifications->notifyZone(
                    $purok,
                    'announcement',
                    'Bagong Anunsyo',
                    "May bagong anunsyo para sa {$purok}: {$title}",
                    $id,
                    'announcement'
                );
            } else {
                $notifications->broadcast(
                    'announcement',
                    'Bagong Anunsyo',
                    "May bagong anunsyo: {$title}",
                    $id,
                    'announcement'
                );
            }

            // Cache the Manobo rendering for the on-demand widget.
            if ($body !== '') {
                TranslationService::autoTranslate(
                    'announcement',
                    $id,
                    $title . '. ' . \strip_tags($body),
                    $actorId
                );
            }

            // SMS only if staff asked for it. The in-app notification and the
            // email always go out — those are free — but every text message
            // costs credits, so this one is opt-out and the choice is the
            // author's, made on the form and stored on the row.
            //
            // Defaults to sending when the column is absent (migration 020 not
            // run) or the caller did not say, which is what this did before the
            // checkbox existed.
            if ((int) ($post['notify_sms'] ?? 1) === 1) {
                $this->sendSms($id, $title, $urgency, $purok);
            }

            $this->sendEmails([
                'id'      => $id,
                'title'   => $title,
                'slug'    => $slug,
                'urgency' => $urgency,
            ]);

            (new NotificationService())->notifyBackOffice(
                'notify_content',
                'announcement',
                'Bagong anunsyo na-publish',
                "Na-publish ang anunsyo: {$title}",
                $id,
                'announcement',
                $actorId
            );
        } catch (\Throwable $e) {
            // Hand the post back to the queue rather than losing the notice.
            Announcement::releaseNotificationClaim($id);
            error_log('[ScheduledPublisher] dispatch failed for announcement #' . $id . ' — ' . $e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Dispatch any announcements whose scheduled moment has arrived.
     *
     * Safe to call on an ordinary web request: it is one indexed query against
     * `(notified_at, published_at)`, and on the overwhelmingly common path it
     * finds nothing and returns immediately. Every failure is contained — a
     * broken sweep must never take a page down with it.
     *
     * @return int How many posts were dispatched.
     */
    public function run(int $limit = self::SWEEP_BATCH): int
    {
        try {
            $due = Announcement::dueForPublishing($limit);
        } catch (\Throwable $e) {
            // Most likely the migration has not run yet (no notified_at column).
            return 0;
        }

        $sent = 0;
        foreach ($due as $post) {
            if ($this->dispatch($post)) {
                $sent++;
            }
        }

        return $sent;
    }

    // ── Internals ─────────────────────────────────────────────────────────

    /**
     * SMS the residents this notice is for.
     *
     * Mirrors the message format staff already see for immediate publishes so
     * a scheduled notice is indistinguishable from a live one on the handset.
     *
     * A targeted notice names its purok in the text. That is not decoration:
     * it tells the recipient at a glance that the message IS about them, which
     * is the whole point of narrowing the send in the first place.
     *
     * @param string $purok '' for the whole barangay.
     */
    private function sendSms(int $id, string $title, string $urgency, string $purok = ''): void
    {
        try {
            $phones = $purok !== ''
                ? SmsController::getVerifiedPhonesInPurok($purok)
                : SmsController::getVerifiedPhones();

            if (empty($phones)) {
                return;
            }

            // PostSms owns the wording and the 160-character arithmetic. A
            // targeted send passes the purok as the sign-off, so the recipient
            // can tell from the text alone that it concerns them.
            $message = PostSms::build(
                'announcement',
                ['title' => $title, 'urgency' => $urgency],
                $purok
            );

            (new SemaphoreSmsService())->sendBulk($phones, $message, 'announcement', $id);
        } catch (\Throwable $e) {
            error_log('[ScheduledPublisher] SMS failed for announcement #' . $id . ' — ' . $e->getMessage());
        }
    }

    /**
     * Email every verified resident. Each send is isolated so one bad address
     * or an unreachable SMTP server never blocks the rest of the batch.
     *
     * @param array<string,mixed> $announcement
     */
    private function sendEmails(array $announcement): void
    {
        try {
            $residents = User::allVerifiedResidentEmails();
        } catch (\Throwable $e) {
            error_log('[ScheduledPublisher] could not load resident emails — ' . $e->getMessage());
            return;
        }

        if (empty($residents)) {
            return;
        }

        $mailer = new MailService();
        foreach ($residents as $resident) {
            try {
                $mailer->sendAnnouncementNotification($resident, $announcement);
            } catch (\Throwable $e) {
                error_log('[ScheduledPublisher] email failed for user #' . $resident['id'] . ' — ' . $e->getMessage());
            }
        }
    }
}
