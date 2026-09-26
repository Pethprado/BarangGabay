<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Ordinance;
use App\Models\AuditLog;
use App\Models\TranslationAttempt;
use App\Services\FileService;
use App\Services\NotificationService;
use App\Services\PostAudioService;
use App\Services\PostSms;
use App\Services\SourceLink;
use App\Services\SemaphoreSmsService;
use App\Services\TranslationService;

class OrdinanceController
{
    /**
     * GET /ordinances
     * Resident policy library with optional search + category filter.
     */
    public function index(): void
    {
        $search     = \trim($_GET['search']   ?? '');
        $category   = \trim($_GET['category'] ?? '');
        $ordinances = Ordinance::searchFiltered($search, $category);
        $categories = Ordinance::getCategories();

        view('resident/ordinances', compact('ordinances', 'categories', 'search', 'category'));
    }

    /**
     * GET /ordinances/{id}
     * Resident ordinance detail with PDF.js viewer and AI summarize.
     */
    public function show(array $params): void
    {
        $ordinance = Ordinance::find((int) $params['id']);
        if (!$ordinance) {
            http_response_code(404);
            view('errors/404');
            return;
        }

        // Build the absolute PDF URL (PDF.js requires an absolute URL to fetch the file).
        $pdfUrl = \rtrim(base_url(), '/') . '/' . \ltrim($ordinance['file_url'], '/');

        view('resident/ordinance-detail', compact('ordinance', 'pdfUrl'));
    }

    /** GET /admin/ordinances */
    public function adminIndex(): void
    {
        $ordinances   = Ordinance::all();
        $pageTitle    = t('admin_ordinances.title');
        $pendingCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
        /* Why a language is missing, for every ordinance on this page, in
           ONE query — the badges need it per row. */
        $ordAttempts = TranslationAttempt::forMany(
            'ordinance',
            array_map(static fn (array $o): int => (int) $o['id'], $ordinances)
        );

        view('admin/ordinances/index', compact('ordinances', 'pageTitle', 'pendingCount', 'ordAttempts'));
    }

    /** GET /admin/ordinances/upload */
    public function create(): void
    {
        $pageTitle    = t('admin_ordinances.upload_btn');
        $pendingCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
        view('admin/ordinances/upload', compact('pageTitle', 'pendingCount'));
    }

    /** POST /admin/ordinances */
    public function store(): void
    {
        check_csrf();

        $title       = \trim($_POST['title']        ?? '');
        $ordinanceNo = \trim($_POST['ordinance_no']  ?? '');
        $description = \trim($_POST['description']   ?? '');
        $category    = \trim($_POST['category']      ?? '');
        $enactedDate = \trim($_POST['enacted_date']  ?? '');
        $status      = \trim($_POST['status']        ?? 'active');

        // A PDF can arrive two ways: chosen in the file picker, or already
        // fetched from a Drive link by the import panel. The imported path is
        // re-checked against the names this app writes before it is accepted.
        $imported = (new FileService())->acceptImported(
            (string) ($_POST['imported_file'] ?? ''),
            'ordinances',
            ['.pdf']
        );

        if (!$title || !$ordinanceNo || (empty($_FILES['file']['tmp_name']) && $imported === null)) {
            flash('error', 'Title, ordinance number and PDF are required.');
            redirect('/admin/ordinances/upload');
        }

        // Reported rather than left to the column. The description has no
        // length rule at all, in either direction.
        if (mb_strlen($title) > post_title_limit()) {
            flash('error', t('flash.ann_title_too_long', ['max' => post_title_limit()]));
            redirect('/admin/ordinances/upload');
        }

        if (!empty($_FILES['file']['tmp_name'])) {
            try {
                $fileUrl = (new FileService())->upload($_FILES['file'], 'ordinances');
            } catch (\Throwable $e) {
                flash('error', 'PDF upload failed: ' . $e->getMessage());
                redirect('/admin/ordinances/upload');
            }
        } else {
            $fileUrl = $imported;
        }

        $ordinanceId = Ordinance::create([
            'title'        => $title,
            'ordinance_no' => $ordinanceNo,
            'description'  => $description,
            'category'     => $category,
            'file_url'     => $fileUrl,
            'enacted_date' => $enactedDate ?: null,
            'uploaded_by'  => (int) $_SESSION['user_id'],
            'status'       => $status,
        ]);

        // ── Manobo audio upload ───────────────────────────────────────
        $audioPath = $this->uploadAudio();
        if ($audioPath !== null) {
            Ordinance::updateAudio($ordinanceId, $audioPath);
        }

        // ── English text: manual input wins over AI translation ────────
        // Same rule as Manobo below. Residents who pick EN see the Filipino
        // original until one of these two produces an English copy.
        $titleEn = \trim($_POST['title_en'] ?? '');
        $bodyEn  = \trim($_POST['description_en'] ?? '');

        $sourceLang = TranslationService::resolveSourceLang(
            $_POST['source_lang'] ?? null,
            $title,
            $description
        );
        Ordinance::setSourceLang($ordinanceId, $sourceLang);
        $manualOther = $titleEn !== '' || $bodyEn !== '';

        if ($manualOther) {
            $sourceLang === 'fil'
                ? TranslationService::storeEnglish('ordinance', $ordinanceId, $titleEn, $bodyEn, false)
                : TranslationService::storeFilipino('ordinance', $ordinanceId, $titleEn, $bodyEn, false);
        }

        $titleManobo = \trim($_POST['title_manobo']       ?? '');
        $descManobo  = \trim($_POST['description_manobo'] ?? '');
        $manualManobo = $titleManobo !== '' || $descManobo !== '';

        if ($manualManobo) {
            Ordinance::updateManobo($ordinanceId, $titleManobo, $descManobo);
            TranslationService::flagAuto('ordinance', $ordinanceId, 'manobo_is_auto', false);
        }

        $auto = TranslationService::autoTranslateFrom(
            'ordinance', $ordinanceId, $title, $description, $sourceLang, !$manualOther, !$manualManobo
        );

        // Cache the narration residents will hear. An ordinance speaks its
        // plain-language summary, never the PDF — PostScript enforces that, so
        // twenty pages of legal text are never sent to a metered TTS provider.
        // Where this came from — for an ordinance, typically the Drive link
        // the PDF was pulled from. See the note in AnnouncementController.
        SourceLink::store('ordinance', $ordinanceId, (string) ($_POST['source_url'] ?? ''));

        PostAudioService::refresh('ordinance', $ordinanceId, (int) $_SESSION['user_id']);

        if ($manualManobo) {
            $successMsg = t('flash.ord_uploaded') . t('flash.manual_manobo_saved');
        } else {
            $successMsg = t('flash.ord_uploaded') . ($auto['manobo'] ? t('flash.auto_translated') : '');
            if (!$auto['manobo']) {
                flash('warning', t('flash.not_translated_create'));
            }
        }

        if ($status === 'active') {
            TranslationService::autoTranslate(
                'ordinance', $ordinanceId,
                $ordinanceNo . ': ' . $title . '. ' . $description,
                (int) $_SESSION['user_id']
            );
            // Back-office colleagues who want content alerts (the uploader is skipped).
            (new NotificationService())->notifyBackOffice(
                'notify_content',
                'ordinance',
                'Bagong ordinansa na-publish',
                'Na-publish ang ordinansa: ' . $ordinanceNo . ' — ' . $title,
                $ordinanceId,
                'ordinance',
                (int) $_SESSION['user_id']
            );

            // Text the residents, if staff asked for it. Ordinances had no SMS
            // path at all before this: a new policy could be published and the
            // only people told were back-office colleagues. An unchecked box
            // posts nothing, hence empty().
            if (!empty($_POST['send_sms'])) {
                $this->sendOrdinanceSms($ordinanceId, $ordinanceNo, $title);
            }
        }

        AuditLog::record((int) $_SESSION['user_id'], 'ordinance.upload', 'Uploaded ordinance: ' . $title);
        flash('success', $successMsg);
        redirect('/admin/ordinances');
    }

    /** GET /admin/ordinances/{id}/edit */
    public function edit(array $params): void
    {
        $ordinance = Ordinance::find((int) ($params['id'] ?? 0));
        if (!$ordinance) {
            flash('error', 'Ordinance not found.');
            redirect('/admin/ordinances');
        }
        $pageTitle    = t('admin_ordinances.edit_title');
        $pendingCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
        view('admin/ordinances/edit', compact('ordinance', 'pageTitle', 'pendingCount'));
    }

    /** POST /admin/ordinances/{id} */
    public function update(array $params): void
    {
        check_csrf();

        $id        = (int) ($params['id'] ?? 0);
        $ordinance = Ordinance::find($id);
        if (!$ordinance) {
            flash('error', 'Ordinance not found.');
            redirect('/admin/ordinances');
        }

        $title       = \trim($_POST['title']        ?? '');
        $ordinanceNo = \trim($_POST['ordinance_no']  ?? '');
        $description = \trim($_POST['description']   ?? '');
        $category    = \trim($_POST['category']      ?? '');
        $enactedDate = \trim($_POST['enacted_date']  ?? '');
        $status      = \trim($_POST['status']        ?? 'active');

        if (!$title || !$ordinanceNo) {
            flash('error', 'Title and ordinance number are required.');
            redirect("/admin/ordinances/{$id}/edit");
        }

        // Replace PDF only if a new file is uploaded
        $fileUrl = $ordinance['file_url'];
        if (!empty($_FILES['file']['tmp_name'])) {
            try {
                $fileUrl = (new FileService())->upload($_FILES['file'], 'ordinances');
            } catch (\Throwable $e) {
                flash('error', 'PDF upload failed: ' . $e->getMessage());
                redirect("/admin/ordinances/{$id}/edit");
            }
        }

        Ordinance::updateRecord($id, [
            'title'        => $title,
            'ordinance_no' => $ordinanceNo,
            'description'  => $description,
            'category'     => $category,
            'file_url'     => $fileUrl,
            'enacted_date' => $enactedDate ?: null,
            'status'       => $status,
        ]);

        // ── Manobo audio: delete, replace, or keep existing ──────────
        if (!empty($_POST['delete_audio_manobo'])) {
            $oldAudio = $ordinance['audio_manobo_path'] ?? null;
            if ($oldAudio) {
                try { (new FileService())->delete($oldAudio); } catch (\Throwable) {}
            }
            Ordinance::updateAudio($id, null);
        } else {
            $audioPath = $this->uploadAudio();
            if ($audioPath !== null) {
                Ordinance::updateAudio($id, $audioPath);
            }
        }

        // ── English text: manual input wins over AI translation ────────
        // Same rule as Manobo below. Residents who pick EN see the Filipino
        // original until one of these two produces an English copy.
        $titleEn = \trim($_POST['title_en'] ?? '');
        $bodyEn  = \trim($_POST['description_en'] ?? '');

        $sourceLang = TranslationService::resolveSourceLang(
            $_POST['source_lang'] ?? null,
            $title,
            $description,
            (string) ($ordinance['source_lang'] ?? 'fil')
        );
        Ordinance::setSourceLang($id, $sourceLang);
        $manualOther = $titleEn !== '' || $bodyEn !== '';

        if ($manualOther) {
            $sourceLang === 'fil'
                ? TranslationService::storeEnglish('ordinance', $id, $titleEn, $bodyEn, false)
                : TranslationService::storeFilipino('ordinance', $id, $titleEn, $bodyEn, false);
        }

        $titleManobo = \trim($_POST['title_manobo']       ?? '');
        $descManobo  = \trim($_POST['description_manobo'] ?? '');
        $manualManobo = $titleManobo !== '' || $descManobo !== '';

        if ($manualManobo) {
            Ordinance::updateManobo($id, $titleManobo, $descManobo);
            TranslationService::flagAuto('ordinance', $id, 'manobo_is_auto', false);
        }

        $auto = TranslationService::autoTranslateFrom(
            'ordinance', $id, $title, $description, $sourceLang, !$manualOther, !$manualManobo
        );

        // Regenerate whatever the edit made stale. ensure() compares the script
        // hash per language, so an unchanged language costs nothing.
        SourceLink::store('ordinance', $id, (string) ($_POST['source_url'] ?? ''));

        PostAudioService::refresh('ordinance', $id, (int) $_SESSION['user_id']);

        if ($manualManobo) {
            $successMsg = t('flash.ord_updated') . t('flash.manual_manobo_saved');
        } else {
            $successMsg = t('flash.ord_updated') . ($auto['manobo'] ? t('flash.auto_translated') : '');
            if (!$auto['manobo']) {
                flash('warning', t('flash.not_translated_update'));
            }
        }

        if ($status === 'active') {
            TranslationService::autoTranslate(
                'ordinance', $id,
                $ordinanceNo . ': ' . $title . '. ' . $description,
                (int) $_SESSION['user_id']
            );
        }

        AuditLog::record((int) $_SESSION['user_id'], 'ordinance.update', 'Updated ordinance: ' . $title);
        flash('success', $successMsg);
        redirect('/admin/ordinances');
    }

    /** Upload Manobo audio from $_FILES['audio_manobo']; returns relative URL or null. */
    private function uploadAudio(): ?string
    {
        if (empty($_FILES['audio_manobo']['tmp_name'])) {
            return null;
        }
        try {
            return (new FileService())->uploadAudio($_FILES['audio_manobo'], 'manobo-audio');
        } catch (\Throwable $e) {
            flash('warning', t('flash.audio_failed') . $e->getMessage());
            return null;
        }
    }

    /** POST /admin/ordinances/{id}/delete */
    public function destroy(array $params): void
    {
        check_csrf();

        $id        = (int) ($params['id'] ?? 0);
        $ordinance = Ordinance::find($id);
        if (!$ordinance) {
            flash('error', 'Ordinance not found.');
            redirect('/admin/ordinances');
        }

        // Before the row goes: the generated MP3s are files on disk that
        // nothing else would ever clean up.
        PostAudioService::purgeFor('ordinance', $id);

        Ordinance::delete($id);

        AuditLog::record(
            (int) $_SESSION['user_id'],
            'ordinance.delete',
            'Deleted ordinance: ' . ($ordinance['title'] ?? "#{$id}")
        );
        flash('success', t('flash.ord_deleted'));
        redirect('/admin/ordinances');
    }

    /**
     * Text every verified resident who has a phone number about a new ordinance.
     *
     * Kept short on purpose. A single SMS segment is 160 characters and the
     * ordinance number plus title can already be long, so the message is a
     * pointer to the portal rather than an attempt to summarise a policy —
     * paying for three segments to half-explain an ordinance helps nobody.
     *
     * Runs inside a try/catch so an SMS failure never breaks the upload: the
     * ordinance is saved either way, and a lost text is recoverable from the
     * SMS page while a lost upload is not.
     */
    /**
     * Build the ordinance SMS so that it always fits one billed segment.
     *
     * Semaphore charges per 160-character segment, and a barangay blast is one
     * message per resident — so a message that quietly runs to 161 characters
     * doubles the cost of every send. Hand-picked truncation limits are not
     * enough: a long ordinance number combined with a long title pushed an
     * earlier version of this to 171 characters, which a test caught before it
     * ever reached the network.
     *
     * So the budget is computed rather than guessed. The fixed wording is
     * measured at runtime and whatever room is left is split between the
     * number and the title — the number first, since "Ordinance No. 2026-014"
     * is what a resident needs to look the policy up. Change the wording and
     * the arithmetic still holds.
     *
     * Public and static so it can be tested without sending anything.
     */
    public static function buildOrdinanceSms(string $ordinanceNo, string $title, int $limit = 160): string
    {
        // The arithmetic moved to PostSms, which now owns the wording for all
        // three post types — auto-send and the SMS page's manual send can no
        // longer drift apart. Kept as a named method because the rest of this
        // controller and its tests call it by name.
        return PostSms::build('ordinance', [
            'ordinance_no' => $ordinanceNo,
            'title'        => $title,
        ]);
    }

    private function sendOrdinanceSms(int $id, string $ordinanceNo, string $title): void
    {
        try {
            $phones = SmsController::getVerifiedPhones();
            if (empty($phones)) {
                return;
            }

            $message = self::buildOrdinanceSms($ordinanceNo, $title);

            (new SemaphoreSmsService())->sendBulk($phones, $message, 'ordinance', $id);
        } catch (\Throwable $e) {
            error_log('OrdinanceController: SMS failed for ordinance #' . $id . ' — ' . $e->getMessage());
        }
    }
}
