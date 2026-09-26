<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Event;
use App\Models\AuditLog;
use App\Models\TranslationAttempt;
use App\Services\FileService;
use App\Services\NotificationService;
use App\Services\PostAudioService;
use App\Services\PostSms;
use App\Services\SourceLink;
use App\Services\SemaphoreSmsService;
use App\Services\TranslationService;

class EventController
{
    // ── Resident ─────────────────────────────────────────────────────────────

    /**
     * GET /events
     * Lists all non-cancelled events with compound sort (ongoing → upcoming → completed).
     */
    public function index(): void
    {
        $events = Event::allForListing();
        view('resident/events', ['events' => $events]);
    }

    /**
     * GET /events/{slug}
     * Shows full event detail with Google Maps embed.
     */
    public function show(array $params): void
    {
        $event = Event::findBySlug($params['slug']);
        if (!$event) {
            http_response_code(404);
            view('errors/404');
            return;
        }
        $mapsKey = $_ENV['GOOGLE_MAPS_API_KEY'] ?? '';
        view('resident/event-detail', compact('event', 'mapsKey'));
    }

    /**
     * GET /events/{slug}/calendar
     * Streams a .ics file download so residents can add the event to their calendar.
     */
    public function downloadCalendar(array $params): void
    {
        $event = Event::findBySlug($params['slug']);
        if (!$event) {
            http_response_code(404);
            return;
        }

        $dtStart = gmdate('Ymd\THis\Z', strtotime($event['event_date']));
        $dtEnd   = !empty($event['end_date'])
            ? gmdate('Ymd\THis\Z', strtotime($event['end_date']))
            : gmdate('Ymd\THis\Z', strtotime($event['event_date']) + 3600);

        $uid      = uniqid('baranggabay-', true) . '@baranggabay.ph';
        $now      = gmdate('Ymd\THis\Z');
        $summary  = $this->icsEscape($event['title']);
        $desc     = $this->icsEscape(\strip_tags($event['description']));
        $location = $this->icsEscape($event['venue'] ?? '');

        $ics = "BEGIN:VCALENDAR\r\n"
             . "VERSION:2.0\r\n"
             . "PRODID:-//BarangGabay//Barangay Bayogo//EN\r\n"
             . "CALSCALE:GREGORIAN\r\n"
             . "METHOD:PUBLISH\r\n"
             . "BEGIN:VEVENT\r\n"
             . "UID:{$uid}\r\n"
             . "DTSTAMP:{$now}\r\n"
             . "DTSTART:{$dtStart}\r\n"
             . "DTEND:{$dtEnd}\r\n"
             . "SUMMARY:{$summary}\r\n"
             . "DESCRIPTION:{$desc}\r\n"
             . "LOCATION:{$location}\r\n"
             . "END:VEVENT\r\n"
             . "END:VCALENDAR\r\n";

        $filename = 'event-'
            . preg_replace('/[^a-z0-9-]/', '', \str_replace(' ', '-', \strtolower($event['title'])))
            . '.ics';

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        echo $ics;
    }

    /**
     * GET /api/events/calendar
     * Returns all non-cancelled events as a FullCalendar-compatible JSON array.
     */
    public function calendarJson(): void
    {
        header('Content-Type: application/json');

        $colorMap = [
            'upcoming'  => '#3b82f6',
            'ongoing'   => '#22c55e',
            'completed' => '#94a3b8',
            'cancelled' => '#ef4444',
        ];

        $formatted = array_map(function (array $e) use ($colorMap): array {
            $color = $colorMap[$e['status']] ?? '#3b82f6';
            $end   = !empty($e['end_date']) ? $e['end_date'] : $e['event_date'];
            return [
                'id'              => (string) $e['id'],
                'title'           => $e['title'],
                'start'           => $e['event_date'],
                'end'             => $end,
                'url'             => route('events/' . $e['slug']),
                'backgroundColor' => $color,
                'borderColor'     => $color,
                'extendedProps'   => [
                    'venue'  => $e['venue'] ?? '',
                    'status' => $e['status'],
                ],
            ];
        }, Event::allForCalendar());

        echo json_encode($formatted);
    }

    // ── Admin ─────────────────────────────────────────────────────────────────

    /**
     * GET /admin/events
     */
    /**
     * GET /admin/events — optionally ?status=upcoming|ongoing|completed|cancelled
     *
     * Exists so the dashboard's "Upcoming events" card can open the events it
     * counted rather than the whole archive. See the note on
     * AnnouncementController::adminIndex().
     */
    public function adminIndex(): void
    {
        $status    = trim($_GET['status'] ?? '');
        $allEvents = Event::allAdmin();

        $valid  = ['upcoming', 'ongoing', 'completed', 'cancelled'];
        $status = \in_array($status, $valid, true) ? $status : '';

        $events = $status === ''
            ? $allEvents
            : array_values(array_filter(
                $allEvents,
                static fn (array $e): bool => ($e['status'] ?? '') === $status
            ));

        $pageTitle    = t('admin_nav.events');
        $pendingCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();

        /* Why a language is missing, for every event on this page, in ONE
           query — the badges need it per row. */
        $evAttempts = TranslationAttempt::forMany(
            'event',
            array_map(static fn (array $e): int => (int) $e['id'], $events)
        );

        view('admin/events/index', compact(
            'events', 'allEvents', 'status', 'pageTitle', 'pendingCount', 'evAttempts'
        ));
    }

    /**
     * GET /admin/events/create
     */
    public function create(): void
    {
        $pageTitle    = t('admin_events.create_event_btn');
        $mapsKey      = $_ENV['GOOGLE_MAPS_API_KEY'] ?? '';
        $errors       = [];
        $pendingCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
        view('admin/events/create', compact('pageTitle', 'mapsKey', 'errors', 'pendingCount'));
    }

    /**
     * POST /admin/events
     */
    public function store(): void
    {
        check_csrf();

        $errors = $this->validate($_POST);
        if ($errors) {
            flash('error', implode('<br>', $errors));
            redirect('/admin/events/create');
        }

        $slug     = Event::uniqueSlug(generate_slug(\trim($_POST['title'])));
        $coverUrl = $this->uploadCover();
        $lat      = \trim($_POST['latitude']  ?? '');
        $lng      = \trim($_POST['longitude'] ?? '');

        $id = Event::create([
            'title'           => \trim($_POST['title']),
            'slug'            => $slug,
            'description'     => \trim($_POST['description']),
            'venue'           => \trim($_POST['venue'] ?? ''),
            'latitude'        => $lat  !== '' ? (float) $lat  : null,
            'longitude'       => $lng  !== '' ? (float) $lng  : null,
            'event_date'      => \trim($_POST['event_date']),
            'end_date'        => \trim($_POST['end_date'] ?? '') ?: null,
            'cover_image_url' => $coverUrl,
            'created_by'      => (int) $_SESSION['user_id'],
            'status'          => \trim($_POST['status']),
        ]);

        $evTitle = \trim($_POST['title']);
        $evDesc  = \trim($_POST['description']);

        // ── Manobo audio upload ───────────────────────────────────────
        $audioPath = $this->uploadAudio();
        if ($audioPath !== null) {
            Event::updateAudio($id, $audioPath);
        }

        // ── Translations ───────────────────────────────────────────────
        // Translate into the languages this event was not written in.
        $sourceLang = TranslationService::resolveSourceLang(
            $_POST['source_lang'] ?? null,
            $evTitle,
            $evDesc
        );
        Event::setSourceLang($id, $sourceLang);

        $titleEn = \trim($_POST['title_en'] ?? '');
        $bodyEn  = \trim($_POST['description_en'] ?? '');
        $manualOther = $titleEn !== '' || $bodyEn !== '';

        if ($manualOther) {
            $sourceLang === 'fil'
                ? TranslationService::storeEnglish('event', $id, $titleEn, $bodyEn, false)
                : TranslationService::storeFilipino('event', $id, $titleEn, $bodyEn, false);
        }

        $titleManobo = \trim($_POST['title_manobo']       ?? '');
        $descManobo  = \trim($_POST['description_manobo'] ?? '');
        $manualManobo = $titleManobo !== '' || $descManobo !== '';

        if ($manualManobo) {
            Event::updateManobo($id, $titleManobo, $descManobo);
            TranslationService::flagAuto('event', $id, 'manobo_is_auto', false);
        }

        $auto = TranslationService::autoTranslateFrom(
            'event', $id, $evTitle, $evDesc, $sourceLang, !$manualOther, !$manualManobo
        );

        // Cache the narration residents will hear, in every language this event
        // now has text for. After the translations, never before: the audio has
        // to be generated from the final wording. Non-fatal by design — the
        // event is saved either way and the reader falls back to the device's
        // own voice for anything that failed.
        // Where this came from, if it came from somewhere. See the note in
        // AnnouncementController.
        SourceLink::store('event', $id, (string) ($_POST['source_url'] ?? ''));

        PostAudioService::refresh('event', $id, (int) $_SESSION['user_id']);

        if ($manualManobo) {
            $successMsg = t('flash.event_created') . t('flash.manual_manobo_saved');
        } else {
            $successMsg = t('flash.event_created') . ($auto['manobo'] ? t('flash.auto_translated') : '');
            if (!$auto['manobo']) {
                flash('warning', t('flash.not_translated_create'));
            }
        }

        $eventStatus = \trim($_POST['status']);
        if ($eventStatus === 'upcoming' || $eventStatus === 'ongoing') {
            (new NotificationService())->broadcast(
                'event',
                'Bagong Kaganapan',
                'May bagong event: ' . $evTitle,
                $id,
                'event'
            );
            // Back-office colleagues who want content alerts (the author is skipped).
            (new NotificationService())->notifyBackOffice(
                'notify_content',
                'event',
                'Bagong kaganapan na-publish',
                'Na-publish ang kaganapan: ' . $evTitle,
                $id,
                'event',
                (int) $_SESSION['user_id']
            );
            TranslationService::autoTranslate(
                'event', $id,
                $evTitle . '. '
                    . (!empty(\trim($_POST['venue'] ?? '')) ? 'Lugar: ' . \trim($_POST['venue']) . '. ' : '')
                    . $evDesc,
                (int) $_SESSION['user_id']
            );
            // SMS only when asked. This used to fire on every single save with
            // no way to decline and no sign it had happened — each blast is one
            // paid message per resident, so the choice belongs to whoever is
            // writing the post. An unchecked box posts nothing, hence empty().
            if (!empty($_POST['send_sms'])) {
                $this->sendEventSms($id, $evTitle, \trim($_POST['event_date']), \trim($_POST['venue'] ?? ''));
            }
        }

        AuditLog::record((int) $_SESSION['user_id'], 'event.create', 'Created event: ' . $evTitle);
        flash('success', $successMsg);
        redirect('/admin/events');
    }

    /**
     * GET /admin/events/{id}/edit
     */
    public function edit(array $params): void
    {
        $event = Event::find((int) $params['id']);
        if (!$event) {
            flash('error', 'Event not found.');
            redirect('/admin/events');
        }
        $pageTitle    = t('admin_events.breadcrumb_edit');
        $mapsKey      = $_ENV['GOOGLE_MAPS_API_KEY'] ?? '';
        $pendingCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
        view('admin/events/edit', compact('event', 'pageTitle', 'mapsKey', 'pendingCount'));
    }

    /**
     * POST /admin/events/{id}
     */
    public function update(array $params): void
    {
        check_csrf();

        $id    = (int) $params['id'];
        $event = Event::find($id);
        if (!$event) {
            flash('error', 'Event not found.');
            redirect('/admin/events');
        }

        $errors = $this->validate($_POST);
        if ($errors) {
            flash('error', implode('<br>', $errors));
            redirect('/admin/events/' . $id . '/edit');
        }

        $slug = Event::uniqueSlug(generate_slug(\trim($_POST['title'])), $id);
        $lat  = \trim($_POST['latitude']  ?? '');
        $lng  = \trim($_POST['longitude'] ?? '');

        Event::update($id, [
            'title'       => \trim($_POST['title']),
            'slug'        => $slug,
            'description' => \trim($_POST['description']),
            'venue'       => \trim($_POST['venue'] ?? ''),
            'latitude'    => $lat !== '' ? (float) $lat  : null,
            'longitude'   => $lng !== '' ? (float) $lng  : null,
            'event_date'  => \trim($_POST['event_date']),
            'end_date'    => \trim($_POST['end_date'] ?? '') ?: null,
            'status'      => \trim($_POST['status']),
        ]);

        $coverUrl = $this->uploadCover();
        if ($coverUrl) {
            Event::updateCover($id, $coverUrl);
        }

        $upTitle = \trim($_POST['title']);
        $upDesc  = \trim($_POST['description']);

        // ── Manobo audio: delete, replace, or keep existing ──────────
        if (!empty($_POST['delete_audio_manobo'])) {
            $oldAudio = $event['audio_manobo_path'] ?? null;
            if ($oldAudio) {
                try { (new FileService())->delete($oldAudio); } catch (\Throwable) {}
            }
            Event::updateAudio($id, null);
        } else {
            $audioPath = $this->uploadAudio();
            if ($audioPath !== null) {
                Event::updateAudio($id, $audioPath);
            }
        }

        // ── English text: manual input wins over AI translation ────────
        // Same rule as Manobo below. Residents who pick EN see the Filipino
        // original until one of these two produces an English copy.
        $titleEn = \trim($_POST['title_en'] ?? '');
        $bodyEn  = \trim($_POST['description_en'] ?? '');

        $sourceLang = TranslationService::resolveSourceLang(
            $_POST['source_lang'] ?? null,
            $upTitle,
            $upDesc,
            (string) ($event['source_lang'] ?? 'fil')
        );
        Event::setSourceLang($id, $sourceLang);
        $manualOther = $titleEn !== '' || $bodyEn !== '';

        if ($manualOther) {
            $sourceLang === 'fil'
                ? TranslationService::storeEnglish('event', $id, $titleEn, $bodyEn, false)
                : TranslationService::storeFilipino('event', $id, $titleEn, $bodyEn, false);
        }

        $titleManobo = \trim($_POST['title_manobo']       ?? '');
        $descManobo  = \trim($_POST['description_manobo'] ?? '');
        $manualManobo = $titleManobo !== '' || $descManobo !== '';

        if ($manualManobo) {
            Event::updateManobo($id, $titleManobo, $descManobo);
            TranslationService::flagAuto('event', $id, 'manobo_is_auto', false);
        }

        $auto = TranslationService::autoTranslateFrom(
            'event', $id, $upTitle, $upDesc, $sourceLang, !$manualOther, !$manualManobo
        );

        // Regenerate whatever the edit made stale. ensure() compares the script
        // hash per language, so an unchanged language costs nothing.
        // Where this came from, if it came from somewhere. See the note in
        // AnnouncementController.
        SourceLink::store('event', $id, (string) ($_POST['source_url'] ?? ''));

        PostAudioService::refresh('event', $id, (int) $_SESSION['user_id']);

        if ($manualManobo) {
            $successMsg = t('flash.event_updated') . t('flash.manual_manobo_saved');
        } else {
            $successMsg = t('flash.event_updated') . ($auto['manobo'] ? t('flash.auto_translated') : '');
            if (!$auto['manobo']) {
                flash('warning', t('flash.not_translated_update'));
            }
        }

        $updatedStatus = \trim($_POST['status']);
        if ($updatedStatus === 'upcoming' || $updatedStatus === 'ongoing') {
            TranslationService::autoTranslate(
                'event', $id,
                $upTitle . '. '
                    . (!empty(\trim($_POST['venue'] ?? '')) ? 'Lugar: ' . \trim($_POST['venue']) . '. ' : '')
                    . $upDesc,
                (int) $_SESSION['user_id']
            );
        }

        AuditLog::record((int) $_SESSION['user_id'], 'event.update', "Updated event #{$id}: {$upTitle}");
        flash('success', $successMsg);
        redirect('/admin/events');
    }

    /**
     * POST /admin/events/{id}/delete
     */
    public function destroy(array $params): void
    {
        check_csrf();
        $id = (int) $params['id'];

        // Before the row goes: the generated MP3s are files on disk that
        // nothing else would ever clean up.
        PostAudioService::purgeFor('event', $id);

        Event::delete($id);
        AuditLog::record(
            (int) $_SESSION['user_id'],
            'event.delete',
            'Deleted event #' . $id
        );
        flash('success', 'Event deleted successfully.');
        redirect('/admin/events');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /** Validate POSTed event fields; returns array of error strings. */
    private function validate(array $post): array
    {
        $errors   = [];
        $statuses = ['upcoming', 'ongoing', 'completed', 'cancelled'];

        if (empty(\trim($post['title'] ?? ''))) {
            $errors[] = 'Title is required.';
        }
        // Reported rather than left to the column, which would either truncate
        // the headline silently or throw a database error at the staff member.
        // The description below has no length rule at all, in either direction.
        if (mb_strlen(\trim($post['title'] ?? '')) > post_title_limit()) {
            $errors[] = t('flash.ann_title_too_long', ['max' => post_title_limit()]);
        }
        if (empty(\trim($post['description'] ?? ''))) {
            $errors[] = 'Description is required.';
        }
        if (empty(\trim($post['event_date'] ?? ''))) {
            $errors[] = 'Event date is required.';
        }
        if (!\in_array(\trim($post['status'] ?? ''), $statuses, true)) {
            $errors[] = 'Invalid status.';
        }

        return $errors;
    }

    /** Upload cover image if present; returns public-relative URL or null. */
    private function uploadCover(): ?string
    {
        if (empty($_FILES['cover_image']['tmp_name'])) {
            // See the note in AnnouncementController::uploadCover(): a cover
            // the import panel fetched arrives as a path, re-checked here.
            return (new FileService())->acceptImported(
                (string) ($_POST['imported_cover'] ?? ''),
                'events',
                ['.jpg', '.png']
            );
        }
        try {
            return (new FileService())->upload($_FILES['cover_image'], 'events');
        } catch (\Exception) {
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

    /** Escape a string for RFC 5545 iCalendar property values. */
    private function icsEscape(string $value): string
    {
        return \str_replace(
            ['\\',  ';',  ',',  "\n",  "\r"],
            ['\\\\', '\\;', '\\,', '\\n', ''],
            $value
        );
    }

    /**
     * Send an event SMS to every verified resident who has a phone number.
     * Runs inside a try/catch so an SMS failure never breaks the save flow.
     */
    private function sendEventSms(int $id, string $title, string $eventDate, string $venue): void
    {
        try {
            $phones = SmsController::getVerifiedPhones();
            if (empty($phones)) {
                return;
            }

            // One builder, shared with the SMS page's manual send. This also
            // fixed a real overrun: the old inline version could reach 171
            // characters with a long title and venue — two credits per
            // recipient for a message that should cost one.
            $message = PostSms::build('event', [
                'title'      => $title,
                'event_date' => $eventDate,
                'venue'      => $venue,
            ]);

            (new SemaphoreSmsService())->sendBulk($phones, $message, 'event', $id);
        } catch (\Throwable $e) {
            error_log('EventController: SMS failed for event #' . $id . ' — ' . $e->getMessage());
        }
    }
}
