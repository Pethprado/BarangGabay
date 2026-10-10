<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\TranslationAttempt;
use App\Models\User;
use App\Services\FileService;
use App\Services\MailService;
use App\Services\NotificationService;
use App\Services\SemaphoreSmsService;
use App\Services\ScheduledPublisher;
use App\Services\PostAudioService;
use App\Services\PostHtml;
use App\Services\PostSms;
use App\Services\SourceLink;
use App\Services\TranslationService;

class AnnouncementController
{
    /**
     * How far ahead a post may be scheduled.
     *
     * A year is generous for a barangay calendar (fiestas, election notices,
     * the start of a school year) while still catching the realistic mistake:
     * a mistyped year that would park a notice in 2035 and quietly never
     * publish it. Staff get told, rather than the post vanishing.
     */
    private const MAX_SCHEDULE_DAYS = 365;

    /**
     * Does this rich-text body actually contain anything?
     *
     * An "empty" Quill editor still posts markup — `<p><br></p>`, and often a
     * non-breaking space — so a plain empty() check would happily save a blank
     * announcement. Tags come off, entities are decoded, and what is left has
     * to have a visible character in it.
     */
    private static function hasContent(string $html): bool
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // \x{00A0} is the decoded &nbsp;, which trim() does not consider space.
        return preg_replace('/[\s\x{00A0}]+/u', '', $text) !== '';
    }

    /**
     * Read the publish-timing choice off the form.
     *
     * The form offers three timings, but the database only has `status` and
     * `published_at`, so this maps one onto the other:
     *
     *   draft            → status draft,     no go-live time
     *   publish now      → status published, go-live = this moment
     *   schedule         → status published, go-live = the chosen future time
     *
     * "Scheduled" is not a fourth status on purpose. A scheduled post IS a
     * published post — it just has not reached its moment yet — and giving it
     * its own status would mean every existing `status = 'published'` query in
     * the app silently stopped seeing it once it went live.
     *
     * A time in the past is treated as "now" rather than rejected: staff who
     * pick 8am and save at 8:05 mean "put it out", not "throw an error".
     *
     * @return array{status:string, published_at:?string, scheduled:bool, error:?string}
     */
    private function resolvePublishTiming(string $status, string $rawWhen): array
    {
        if ($status !== 'published') {
            // Drafts and archived posts have no go-live moment.
            return ['status' => $status, 'published_at' => null, 'scheduled' => false, 'error' => null];
        }

        $rawWhen = trim($rawWhen);
        $now     = time();

        if ($rawWhen === '') {
            return ['status' => 'published', 'published_at' => date('Y-m-d H:i:s', $now), 'scheduled' => false, 'error' => null];
        }

        // <input type="datetime-local"> posts "2026-09-20T08:00" — local time,
        // no zone. date_default_timezone is already Asia/Manila app-wide, so
        // strtotime reads it in exactly the timezone staff typed it in.
        $when = strtotime(str_replace('T', ' ', $rawWhen));

        if ($when === false) {
            return ['status' => 'published', 'published_at' => date('Y-m-d H:i:s', $now), 'scheduled' => false,
                    'error'  => t('flash.ann_schedule_invalid')];
        }

        if ($when > $now + (self::MAX_SCHEDULE_DAYS * 86400)) {
            return ['status' => 'published', 'published_at' => date('Y-m-d H:i:s', $now), 'scheduled' => false,
                    'error'  => t('flash.ann_schedule_too_far')];
        }

        if ($when <= $now) {
            return ['status' => 'published', 'published_at' => date('Y-m-d H:i:s', $now), 'scheduled' => false, 'error' => null];
        }

        return ['status' => 'published', 'published_at' => date('Y-m-d H:i:s', $when), 'scheduled' => true, 'error' => null];
    }

    /** Is the current session a back-office account (staff and above)? */
    private function isBackOfficeUser(): bool
    {
        return \in_array($_SESSION['role'] ?? 'guest', ['staff', 'admin', 'superadmin'], true);
    }

    // ── Resident views ────────────────────────────────────────

    public function index(): void
    {
        $page     = max(1, (int) ($_GET['page']     ?? 1));
        $category = trim($_GET['category'] ?? '');
        $urgency  = trim($_GET['urgency']  ?? '');
        $search   = trim($_GET['search']   ?? '');
        $perPage  = 10;

        $result = Announcement::paginateFiltered($page, $perPage, $category, $urgency, $search);

        view('resident/announcements', [
            'announcements' => $result['items'],
            'total'         => $result['total'],
            'page'          => $page,
            'perPage'       => $perPage,
            'filters'       => compact('category', 'urgency', 'search'),
        ]);
    }

    public function show(array $params): void
    {
        $announcement = Announcement::findBySlug($params['slug'] ?? '');
        if (!$announcement) {
            http_response_code(404);
            echo '<h1>' . e(t('flash.not_found')) . '</h1>';
            return;
        }

        // The list queries hide unpublished posts, but this page is reachable
        // by URL, and a slug is guessable from the title. Without this check a
        // resident could read a draft — or, now that posts can be written days
        // ahead, read a scheduled notice before it was meant to go out, which
        // would defeat the point of scheduling it.
        //
        // Back-office accounts are let through deliberately: staff need to
        // open their own scheduled post to proofread it before it publishes.
        if (!Announcement::isVisibleNow($announcement) && !$this->isBackOfficeUser()) {
            http_response_code(404);
            echo '<h1>' . e(t('flash.not_found')) . '</h1>';
            return;
        }

        $related = Announcement::getRelated(
            $announcement['category'],
            (int) $announcement['id']
        );

        view('resident/announcement-detail', compact('announcement', 'related'));
    }

    // ── AJAX live search (GET /api/announcements/search) ─────

    public function search(): void
    {
        header('Content-Type: application/json');
        $q = trim($_GET['q'] ?? '');
        if (\strlen($q) < 2) {
            echo json_encode([]);
            return;
        }
        // The dropdown is rendered by Alpine from this JSON, so the language
        // choice has to be made here — a view template cannot call
        // localised_content(). Sending the raw row instead was what made a
        // resident reading in English see a card in Filipino while the
        // server-rendered grid two inches below it was in English.
        $rows = \array_map(static function (array $row): array {
            $excerpt = \strip_tags(\html_entity_decode(
                localised_text($row, 'body'),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            ));
            $excerpt = \trim(\preg_replace('/\s+/u', ' ', $excerpt) ?? '');

            return [
                'id'              => (int) $row['id'],
                'slug'            => (string) $row['slug'],
                'title'           => localised_text($row, 'title'),
                'excerpt'         => \mb_strlen($excerpt) > 200 ? \mb_substr($excerpt, 0, 200) . '…' : $excerpt,
                'category'        => $row['category'] ?? 'general',
                'urgency'         => $row['urgency'] ?? 'normal',
                'cover_image_url' => $row['cover_image_url'] ?? null,
                'published_at'    => $row['published_at'] ?? null,
                'author_name'     => $row['author_name'] ?? '',
            ];
        }, Announcement::search($q));

        echo json_encode($rows);
    }

    // ── Admin views ───────────────────────────────────────────

    /**
     * GET /admin/announcements — optionally ?status=published|scheduled|draft|archived
     *
     * The filter exists so the dashboard's stat cards can lead somewhere that
     * actually shows the posts they counted. A card reading "3 published" that
     * opens a list of eleven rows, three of them published, has told the truth
     * and still made the reader do the work again.
     *
     * "Published" here means visible to residents right now — a scheduled post
     * shares the status column but not the audience, so it gets its own filter
     * rather than being quietly folded in.
     *
     * Both lists are passed to the view: the filtered one fills the table, the
     * full one keeps the summary counts above it honest. Filtering to drafts
     * must not make the page claim there are no published posts.
     */
    public function adminIndex(): void
    {
        $status           = trim($_GET['status'] ?? '');
        $allAnnouncements = Announcement::allAdmin();

        // ?mine=1 — the staff sidebar's "My Announcements". Applied to the
        // full list too, so the summary counts describe the same scope.
        $mine = ($_GET['mine'] ?? '') === '1';
        if ($mine) {
            $me = (int) ($_SESSION['user_id'] ?? 0);
            $allAnnouncements = array_values(array_filter(
                $allAnnouncements,
                static fn (array $a): bool => (int) ($a['author_id'] ?? 0) === $me
            ));
        }

        $announcements = match ($status) {
            'published' => array_values(array_filter($allAnnouncements, [Announcement::class, 'isVisibleNow'])),
            'scheduled' => array_values(array_filter($allAnnouncements, [Announcement::class, 'isScheduled'])),
            'draft',
            'archived'  => array_values(array_filter(
                $allAnnouncements,
                static fn (array $a): bool => ($a['status'] ?? '') === $status
            )),
            default     => $allAnnouncements,      // anything unrecognised shows everything
        };

        // Never echo the raw parameter back into the page.
        $status = in_array($status, ['published', 'scheduled', 'draft', 'archived'], true) ? $status : '';

        $pendingCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();

        /* Why a language is missing, for every post on this page, in ONE
           query. The badges need it per row; fetching per row would be
           twenty queries for a page of twenty. */
        $annAttempts = TranslationAttempt::forMany(
            'announcement',
            array_map(static fn (array $a): int => (int) $a['id'], $announcements)
        );

        view('admin/announcements/index', compact(
            'announcements',
            'allAnnouncements',
            'status',
            'pendingCount',
            'annAttempts',
            'mine'
        ));
    }

    public function create(): void
    {
        $pendingCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
        view('admin/announcements/create', compact('pendingCount'));
    }

    public function store(): void
    {
        check_csrf();

        $title    = trim($_POST['title']    ?? '');
        $body     = trim($_POST['body']     ?? '');
        $category = trim($_POST['category'] ?? 'general');
        $urgency  = trim($_POST['urgency']  ?? 'normal');
        $status   = trim($_POST['status']   ?? 'draft');

        $validCategories = ['general','health','safety','government','infrastructure','social'];
        $validUrgencies  = ['normal','important','urgent'];
        $validStatuses   = ['draft','published','archived'];

        $errors = [];
        if (!$title) { $errors[] = t('flash.ann_title_required'); }

        /*
         * The body must exist. It has no minimum length.
         *
         * There used to be a 50-character floor, and it was wrong for the
         * shortest and most urgent notices this system exists to carry:
         * "Walang pasok bukas." is nineteen characters and is exactly the kind
         * of thing a barangay needs to publish in a hurry. A rule that blocks
         * the urgent case to discourage the lazy one is the wrong trade.
         *
         * There is no maximum either — the column is LONGTEXT and nothing in
         * the save path truncates.
         */
        if (!self::hasContent($body)) { $errors[] = t('flash.ann_body_required'); }

        // The one length rule left, and it exists so an over-long headline is
        // reported rather than truncated by the column (or, in strict mode,
        // thrown as a database error at the staff member).
        if (mb_strlen($title) > post_title_limit()) {
            $errors[] = t('flash.ann_title_too_long', ['max' => post_title_limit()]);
        }
        if (!\in_array($category, $validCategories, true)) { $category = 'general'; }
        if (!\in_array($urgency,  $validUrgencies,  true)) { $urgency  = 'normal'; }
        if (!\in_array($status,   $validStatuses,   true)) { $status   = 'draft'; }

        if ($errors) {
            flash('error', \implode(' ', $errors));
            redirect('/admin/announcements/create');
        }

        $body     = $this->purifyHtml($body);
        $slug     = Announcement::uniqueSlug(generate_slug($title));
        $coverUrl = $this->uploadCover();

        $timing = $this->resolvePublishTiming($status, (string) ($_POST['publish_at'] ?? ''));
        if ($timing['error'] !== null) {
            flash('warning', $timing['error']);
        }

        // An unchecked checkbox posts nothing at all, so absence means "no".
        $sendSms = !empty($_POST['send_sms']);

        $id = Announcement::create([
            'title'           => $title,
            'slug'            => $slug,
            'body'            => $body,
            'category'        => $category,
            'urgency'         => $urgency,
            'author_id'       => (int) $_SESSION['user_id'],
            'cover_image_url' => $coverUrl,
            'status'          => $timing['status'],
            'published_at'    => $timing['published_at'],
        ]);

        // Stored, not just used: a scheduled post is dispatched by the sweep
        // long after this request has finished, and it needs to know whether
        // an SMS blast was asked for.
        Announcement::setNotifySms($id, $sendSms);

        // Which purok this is for, and whether it asks for a safety check-in.
        // Stored for the same reason: the sweep reads them, not this request.
        $targetPurok = self::resolveTargetPurok($_POST['target_purok'] ?? '');
        Announcement::setPurokTargeting($id, $targetPurok, !empty($_POST['asks_safety_checkin']));

        // ── Manobo audio upload ───────────────────────────────────────
        $audioPath = $this->uploadAudio();
        if ($audioPath !== null) {
            Announcement::updateAudio($id, $audioPath);
        }

        // ── Translations ───────────────────────────────────────────────
        // Staff write in whichever language they are comfortable with, so the
        // post is translated INTO the two languages it was not written in. A
        // post typed in English gets a Filipino version, not the reverse.
        $sourceLang = TranslationService::resolveSourceLang(
            $_POST['source_lang'] ?? null,
            $title,
            $body
        );
        Announcement::setSourceLang($id, $sourceLang);

        // The "other written language" panel: Filipino when the post is in
        // English, English when the post is in Filipino. One panel, one pair
        // of field names, whichever way round it is.
        $titleEn = \trim($_POST['title_en'] ?? '');
        $bodyEn  = \trim($_POST['body_en']  ?? '');
        $manualOther = $titleEn !== '' || $bodyEn !== '';

        if ($manualOther) {
            $sourceLang === 'fil'
                ? TranslationService::storeEnglish('announcement', $id, $titleEn, $bodyEn, false)
                : TranslationService::storeFilipino('announcement', $id, $titleEn, $bodyEn, false);
        }

        $titleManobo = \trim($_POST['title_manobo'] ?? '');
        $bodyManobo  = \trim($_POST['body_manobo']  ?? '');
        $manualManobo = $titleManobo !== '' || $bodyManobo !== '';

        if ($manualManobo) {
            Announcement::updateManobo($id, $titleManobo, $bodyManobo);
            TranslationService::flagAuto('announcement', $id, 'manobo_is_auto', false);
        }

        // Fill in only what nobody typed by hand.
        $auto = TranslationService::autoTranslateFrom(
            'announcement',
            $id,
            $title,
            $body,
            $sourceLang,
            !$manualOther,
            !$manualManobo
        );

        // Cache the narration residents will hear, in every language this post
        // now has text for. After the translations, never before: the audio has
        // to be generated from the final wording. Non-fatal by design — the
        // post is published either way and the reader falls back to the
        // device's own voice for anything that failed.
        // Where this came from, if it came from somewhere. Stored whether or
        // not the import panel was used — staff may simply paste the Facebook
        // link of a post they retyped by hand, and that is still attribution.
        SourceLink::store('announcement', $id, (string) ($_POST['source_url'] ?? ''));

        PostAudioService::refresh('announcement', $id, (int) $_SESSION['user_id']);

        if ($manualManobo) {
            $successMsg = t('flash.ann_saved') . t('flash.manual_manobo_saved');
        } else {
            $successMsg = t('flash.ann_saved') . ($auto['manobo'] ? t('flash.auto_translated') : '');
            if (!$auto['manobo']) {
                flash('warning', t('flash.not_translated_create'));
            }
        }

        // Notify residents only if the post is live *now*. A scheduled post is
        // already status=published, so this cannot key off status alone — the
        // whole point of scheduling is that nobody hears about it until its
        // moment arrives, and the sweep sends it then.
        if ($timing['status'] === 'published' && !$timing['scheduled']) {
            (new ScheduledPublisher())->dispatch([
                'id'         => $id,
                'title'      => $title,
                'slug'       => $slug,
                'body'       => $body,
                'urgency'    => $urgency,
                'author_id'  => (int) $_SESSION['user_id'],
                'notify_sms' => $sendSms ? 1 : 0,
                'target_purok' => $targetPurok,
            ], (int) $_SESSION['user_id']);
        }

        if ($timing['scheduled']) {
            $successMsg = t('flash.ann_scheduled', [
                'when' => format_datetime($timing['published_at']),
            ]);
        }

        AuditLog::record(
            (int) $_SESSION['user_id'],
            'announcement.create',
            $timing['scheduled']
                ? "Created (scheduled for {$timing['published_at']}): {$title}"
                : "Created: {$title}"
        );
        flash('success', $successMsg);
        redirect('/admin/announcements');
    }

    public function edit(array $params): void
    {
        $announcement = Announcement::find((int) ($params['id'] ?? 0));
        if (!$announcement) {
            http_response_code(404);
            echo '<h1>' . e(t('flash.not_found')) . '</h1>';
            return;
        }
        $pendingCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
        $pageTitle    = t('admin_announcements.breadcrumb_edit_prefix') . ': ' . $announcement['title'];
        view('admin/announcements/edit', compact('announcement', 'pendingCount', 'pageTitle'));
    }

    public function update(array $params): void
    {
        check_csrf();

        $id       = (int) ($params['id'] ?? 0);
        $existing = Announcement::find($id);
        if (!$existing) {
            http_response_code(404);
            echo '<h1>' . e(t('flash.not_found')) . '</h1>';
            return;
        }

        $title    = trim($_POST['title']    ?? '');
        $body     = trim($_POST['body']     ?? '');
        $category = trim($_POST['category'] ?? 'general');
        $urgency  = trim($_POST['urgency']  ?? 'normal');
        $status   = trim($_POST['status']   ?? 'draft');

        // Same rule as create: both must be present, neither has a length
        // floor. See the note in store().
        if (!$title || !self::hasContent($body)) {
            flash('error', t('flash.ann_title_body_required'));
            redirect("/admin/announcements/{$id}/edit");
        }

        if (mb_strlen($title) > post_title_limit()) {
            flash('error', t('flash.ann_title_too_long', ['max' => post_title_limit()]));
            redirect("/admin/announcements/{$id}/edit");
        }

        $body = $this->purifyHtml($body);

        // Publish timing on edit.
        //
        // An already-live post keeps its original timestamp: that date is the
        // historical record of when residents were told, and a typo fix three
        // weeks later must not rewrite it. Only a post that has not gone out
        // yet — a draft, or one still sitting in the schedule queue — can have
        // its moment set or moved.
        $wasLive = Announcement::isVisibleNow($existing);

        // An unchecked checkbox posts nothing, so absence means "no".
        $sendSms = !empty($_POST['send_sms']);
        Announcement::setNotifySms($id, $sendSms);

        if ($wasLive && $status === 'published') {
            $timing = ['status' => 'published', 'published_at' => $existing['published_at'], 'scheduled' => false, 'error' => null];
        } else {
            $timing = $this->resolvePublishTiming($status, (string) ($_POST['publish_at'] ?? ''));
        }

        if ($timing['error'] !== null) {
            flash('warning', $timing['error']);
        }

        $status      = $timing['status'];
        $publishedAt = $timing['published_at'];

        $coverUrl = $existing['cover_image_url'];
        $newCover = $this->uploadCover();
        if ($newCover !== null) { $coverUrl = $newCover; }

        Announcement::update($id, [
            'title'           => $title,
            'body'            => $body,
            'category'        => $category,
            'urgency'         => $urgency,
            'status'          => $status,
            'published_at'    => $publishedAt,
            'cover_image_url' => $coverUrl,
        ]);

        Announcement::setPurokTargeting(
            $id,
            self::resolveTargetPurok($_POST['target_purok'] ?? ''),
            !empty($_POST['asks_safety_checkin'])
        );

        // ── Manobo audio: delete, replace, or keep existing ──────────
        if (!empty($_POST['delete_audio_manobo'])) {
            $oldAudio = $existing['audio_manobo_path'] ?? null;
            if ($oldAudio) {
                try { (new FileService())->delete($oldAudio); } catch (\Throwable) {}
            }
            Announcement::updateAudio($id, null);
        } else {
            $audioPath = $this->uploadAudio();
            if ($audioPath !== null) {
                Announcement::updateAudio($id, $audioPath);
            }
        }

        // ── Translations ───────────────────────────────────────────────
        // Same rule as on create: translate into the languages this was not
        // written in, and never overwrite text a person typed.
        $sourceLang = TranslationService::resolveSourceLang(
            $_POST['source_lang'] ?? null,
            $title,
            $body,
            (string) ($existing['source_lang'] ?? 'fil')
        );
        Announcement::setSourceLang($id, $sourceLang);

        $titleEn = \trim($_POST['title_en'] ?? '');
        $bodyEn  = \trim($_POST['body_en']  ?? '');
        $manualOther = $titleEn !== '' || $bodyEn !== '';

        if ($manualOther) {
            $sourceLang === 'fil'
                ? TranslationService::storeEnglish('announcement', $id, $titleEn, $bodyEn, false)
                : TranslationService::storeFilipino('announcement', $id, $titleEn, $bodyEn, false);
        }

        $titleManobo = \trim($_POST['title_manobo'] ?? '');
        $bodyManobo  = \trim($_POST['body_manobo']  ?? '');
        $manualManobo = $titleManobo !== '' || $bodyManobo !== '';

        if ($manualManobo) {
            Announcement::updateManobo($id, $titleManobo, $bodyManobo);
            TranslationService::flagAuto('announcement', $id, 'manobo_is_auto', false);
        }

        $auto = TranslationService::autoTranslateFrom(
            'announcement',
            $id,
            $title,
            $body,
            $sourceLang,
            !$manualOther,
            !$manualManobo
        );

        // Regenerate whatever the edit made stale. ensure() compares the script
        // hash per language, so an unchanged language costs nothing.
        // Where this came from, if it came from somewhere. Stored whether or
        // not the import panel was used — staff may simply paste the Facebook
        // link of a post they retyped by hand, and that is still attribution.
        SourceLink::store('announcement', $id, (string) ($_POST['source_url'] ?? ''));

        PostAudioService::refresh('announcement', $id, (int) $_SESSION['user_id']);

        if ($manualManobo) {
            $successMsg = t('flash.ann_updated') . t('flash.manual_manobo_saved');
        } else {
            $successMsg = t('flash.ann_updated') . ($auto['manobo'] ? t('flash.auto_translated') : '');
            if (!$auto['manobo']) {
                flash('warning', t('flash.not_translated_update'));
            }
        }

        if ($status === 'published') {
            TranslationService::autoTranslate(
                'announcement', $id,
                $title . '. ' . \strip_tags($body),
                (int) $_SESSION['user_id']
            );

            // Notify only when the post is live right now and has not been
            // announced before. dispatch() claims on `notified_at`, so an edit
            // to an already-announced post is a no-op here rather than a
            // second SMS blast — and a post still waiting in the queue is left
            // alone for the sweep to pick up at its proper moment.
            if (!$timing['scheduled']) {
                (new ScheduledPublisher())->dispatch([
                    'id'         => $id,
                    'title'      => $title,
                    'slug'       => $existing['slug'],
                    'body'       => $body,
                    'urgency'    => $urgency,
                    'author_id'  => (int) $_SESSION['user_id'],
                    'notify_sms' => $sendSms ? 1 : 0,
                ], (int) $_SESSION['user_id']);
            }
        }

        if ($timing['scheduled']) {
            $successMsg = t('flash.ann_scheduled', [
                'when' => format_datetime($timing['published_at']),
            ]);
        }

        AuditLog::record(
            (int) $_SESSION['user_id'],
            'announcement.update',
            $timing['scheduled']
                ? "Updated ID {$id} (scheduled for {$timing['published_at']}): {$title}"
                : "Updated ID {$id}: {$title}"
        );
        flash('success', $successMsg);
        redirect('/admin/announcements');
    }

    public function destroy(array $params): void
    {
        check_csrf();
        $id = (int) ($params['id'] ?? 0);

        // Before the row goes: the generated MP3s are files on disk that
        // nothing else would ever clean up.
        PostAudioService::purgeFor('announcement', $id);

        db()->prepare('DELETE FROM announcements WHERE id = ?')->execute([$id]);
        AuditLog::record((int) $_SESSION['user_id'], 'announcement.delete', "Deleted ID {$id}");
        flash('success', t('flash.ann_deleted'));
        redirect('/admin/announcements');
    }

    /**
     * POST /admin/announcements/{id}/translate — try the machine translation
     * again for one post.
     *
     * Two reasons this has to exist as its own action rather than being folded
     * into a re-save:
     *
     *   The free translation service has a daily per-IP allowance. When it is
     *   spent, a post saves perfectly well with no translation attached, and
     *   nothing says so. Re-saving the whole post to retry means going back
     *   through validation, the cover image and the notification dispatch —
     *   and it re-notifies every resident. A retry must cost nothing but the
     *   translation.
     *
     *   It re-settles the source language from the text. A post filed under
     *   the wrong language is never translated into the one it is missing, and
     *   before this the only repair was to edit the post and change the radio
     *   by hand — which staff have no way of knowing they need to do.
     *
     * Text a person typed is never overwritten; only machine output and empty
     * fields are regenerated.
     */
    public function retranslate(array $params): void
    {
        check_csrf();

        $id  = (int) ($params['id'] ?? 0);
        $ann = Announcement::find($id);

        if (!$ann) {
            flash('error', t('flash.not_found'));
            redirect('/admin/announcements');
        }

        $title = (string) ($ann['title'] ?? '');
        $body  = (string) ($ann['body']  ?? '');

        $sourceLang = TranslationService::resolveSourceLang(
            null,                                   // no form choice: read the post itself
            $title,
            $body,
            (string) ($ann['source_lang'] ?? 'fil')
        );
        Announcement::setSourceLang($id, $sourceLang);

        $allowed = TranslationService::regenerable($ann);

        $auto = TranslationService::autoTranslateFrom(
            'announcement',
            $id,
            $title,
            $body,
            $sourceLang,
            $allowed['other'],
            $allowed['manobo']
        );

        AuditLog::record(
            (int) $_SESSION['user_id'],
            'announcement.translate',
            "Re-ran translation for ID {$id} (source: {$sourceLang})"
        );

        // Reported per language rather than as one "done": the two paths fail
        // independently, and "it worked" over a half-filled post is how this
        // went unnoticed in the first place.
        $locales = available_locales();
        $other   = $sourceLang === 'fil' ? $locales['en'] : $locales['fil'];

        if ($auto['other'] || $auto['manobo']) {
            flash('success', t('flash.ann_translated', [
                'other'  => $other,
                'status' => $auto['other']  ? '✓' : '✗',
                'manobo' => $auto['manobo'] ? '✓' : '✗',
            ]));
        } else {
            flash('error', t('flash.ann_translate_failed'));
        }

        redirect('/admin/announcements');
    }

    // ── Private helpers ───────────────────────────────────────

    /**
     * Settle which purok a notice targets.
     *
     * Returns '' for "the whole barangay", which is the default and the safe
     * direction for this to fail in: an unrecognised value reaches everyone
     * rather than silently reaching nobody. A typo in a purok name must never
     * turn an urgent advisory into a message with no recipients.
     *
     * Checked against barangay_subdivisions() because a <select> constrains
     * the browser, not the request.
     */
    private static function resolveTargetPurok(mixed $posted): string
    {
        $purok = trim((string) $posted);
        if ($purok === '') {
            return '';
        }

        return \in_array($purok, barangay_subdivisions(), true) ? $purok : '';
    }

    /**
     * Sanitize Quill HTML.
     *
     * The configuration lives in PostHtml so the admin form, the import path
     * and the sample-content seeder all sanitise identically — a second copy
     * of that whitelist would be a hole that only shows up on one route.
     */
    private function purifyHtml(string $html): string
    {
        return PostHtml::purify($html);
    }

    /** Upload cover image from $_FILES; returns relative URL or null. */
    private function uploadCover(): ?string
    {
        if (empty($_FILES['cover_image']['tmp_name'])) {
            // Nothing was chosen in the file picker — but the import panel may
            // have already fetched and stored a cover from a pasted link. The
            // path comes back through a hidden field, so it is re-checked
            // against the names this app actually writes before it is trusted.
            return (new FileService())->acceptImported(
                (string) ($_POST['imported_cover'] ?? ''),
                'announcements',
                ['.jpg', '.png']
            );
        }
        try {
            return (new FileService())->upload($_FILES['cover_image'], 'announcements');
        } catch (\Throwable) {
            return null;
        }
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

    /**
     * Send an SMS to every verified resident who has a phone number.
     * Urgent announcements get a distinct URGENT prefix in the message.
     * Runs inside a try/catch so an SMS failure never breaks the save flow.
     */
    private function sendAnnouncementSms(int $id, string $title, string $urgency): void
    {
        try {
            $phones = \App\Controllers\SmsController::getVerifiedPhones();
            if (empty($phones)) {
                return;
            }

            // One builder for auto-send and for the SMS page's manual send, so
            // the two can never produce different text for the same post.
            $message = PostSms::build('announcement', [
                'title'   => $title,
                'urgency' => $urgency,
            ]);

            (new SemaphoreSmsService())->sendBulk($phones, $message, 'announcement', $id);
        } catch (\Throwable $e) {
            error_log('AnnouncementController: SMS failed for announcement #' . $id . ' — ' . $e->getMessage());
        }
    }

    /**
     * Email every verified resident that a new announcement was published.
     * Each send is isolated in its own try/catch so one bad address (or an
     * unconfigured/unreachable SMTP server) never blocks the rest of the batch.
     */
    private function sendAnnouncementEmails(array $announcement): void
    {
        try {
            $residents = User::allVerifiedResidentEmails();
        } catch (\Throwable $e) {
            error_log('AnnouncementController: could not load resident emails — ' . $e->getMessage());
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
                error_log('AnnouncementController: email failed for user #' . $resident['id'] . ' — ' . $e->getMessage());
            }
        }
    }
}