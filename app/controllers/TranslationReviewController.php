<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\TranslationService;

/**
 * Review gate for machine-translated URGENT announcements.
 *
 * Free machine translation is good enough for routine notices but it can
 * invert meaning. The case that prompted this:
 *
 *   Filipino : "May bagyo, lumikas na sa evacuation center."
 *              (there is a typhoon, evacuate to the evacuation center)
 *   Free MT  : "A hurricane has evacuated the evacuation center."
 *
 * So for urgency = 'urgent' the machine's English is parked and a person has
 * to confirm or correct it before residents see it. Auto-translation still
 * runs — no translation at all would be worse for a reader who does not speak
 * Filipino — it just does not publish itself.
 *
 * Authorisation: role:admin,staff (superadmin included by RoleMiddleware).
 * Staff can already write and publish the urgent announcement itself, so
 * requiring a higher role to approve its translation would be incoherent —
 * and would strand translations exactly when no admin is around.
 */
class TranslationReviewController
{
    /**
     * GET /admin/translations
     * Everything waiting on a person, so nothing sits forgotten.
     */
    public function index(): void
    {
        $pageTitle = t('translation_review.title');

        view('admin/translations/index', [
            'pending'      => Announcement::awaitingTranslationReview(),
            'pendingCount' => User::countByStatus('pending'),
            'pageTitle'    => $pageTitle,
        ]);
    }

    /**
     * GET /admin/translations/{id}
     * Source and machine translation side by side, the translation editable.
     */
    public function show(array $params): void
    {
        $announcement = Announcement::find((int) ($params['id'] ?? 0));

        if (!$announcement || ($announcement['en_review_state'] ?? 'none') !== Announcement::REVIEW_PENDING) {
            flash('error', t('translation_review.not_pending'));
            redirect('/admin/translations');
        }

        view('admin/translations/review', [
            'announcement' => $announcement,
            'pendingCount' => User::countByStatus('pending'),
            'pageTitle'    => t('translation_review.review_title'),
        ]);
    }

    /**
     * POST /admin/translations/{id}
     *
     * Publish the translation. Whatever is in the textareas is what gets
     * stored — so a reviewer who fixes "has evacuated" before confirming
     * publishes the fix, not the machine's original wording.
     */
    public function confirm(array $params): void
    {
        check_csrf();

        $id           = (int) ($params['id'] ?? 0);
        $announcement = Announcement::find($id);

        if (!$announcement || ($announcement['en_review_state'] ?? 'none') !== Announcement::REVIEW_PENDING) {
            flash('error', t('translation_review.not_pending'));
            redirect('/admin/translations');
        }

        $titleEn = trim((string) ($_POST['title_en'] ?? ''));
        $bodyEn  = trim((string) ($_POST['body_en']  ?? ''));

        if ($titleEn === '' && $bodyEn === '') {
            flash('error', t('translation_review.err_empty'));
            redirect('/admin/translations/' . $id);
        }

        // Did the reviewer change anything? If so the text is now a person's
        // work and loses the "machine translation" notice; if they confirmed
        // it as-is the notice stays, because it is still the machine's wording
        // even though a person vouched for it.
        $edited = $titleEn !== trim((string) ($announcement['title_en'] ?? ''))
            || $bodyEn  !== trim((string) ($announcement['body_en']  ?? ''));

        Announcement::updateEnglish($id, $titleEn, $bodyEn);
        TranslationService::flagAuto('announcement', $id, 'en_is_auto', !$edited);
        Announcement::setEnReviewState($id, Announcement::REVIEW_APPROVED);

        AuditLog::record(
            (int) $_SESSION['user_id'],
            'translation.approved',
            sprintf(
                'Approved the English translation of urgent announcement #%d (%s). %s',
                $id,
                $announcement['title'] ?? '',
                $edited ? 'Corrected before publishing.' : 'Published as translated.'
            )
        );

        flash('success', t($edited ? 'translation_review.done_edited' : 'translation_review.done_asis'));
        redirect('/admin/translations');
    }
}
