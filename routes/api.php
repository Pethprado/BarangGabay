<?php
declare(strict_types=1);

return [
    // AI endpoints
    ['POST', '/api/ai/summarize',  'AIController@summarize',      ['auth', 'verified', 'rate-limit']],
    ['POST', '/api/ai/chat',       'AIController@chat',           ['auth', 'verified', 'rate-limit']],
    ['POST', '/api/ai/translate',  'AIController@translateManobo',['auth', 'verified', 'rate-limit']],

    // Word-by-word Manobo draft for the admin translation fields. Dictionary
    // only — no AI call, so it works with no API credits. Staff-level because
    // staff write the announcements these fields belong to.
    ['POST', '/api/manobo/draft', 'ManoboController@draft', ['auth', 'role:admin,staff']],

    // Same idea for English: a word-by-word first pass from the same
    // dictionaries, so staff are not typing every translation by hand while
    // the AI translator has no credits.
    ['POST', '/api/english/draft', 'ManoboController@draftEnglish', ['auth', 'role:admin,staff']],

    // The resident-facing "MN" auto-translate: phrase-first longest match
    // over the Manobo dictionary, falling back to Bisaya, then leaving a word
    // unchanged. Dictionary-only (see App\Services\ManoboAutoTranslator), so
    // it costs nothing and needs no API key — same reasoning as the drafts
    // above, just open to any verified resident rather than staff only.
    ['POST', '/api/manobo/translate-block', 'ManoboController@translateBlock', ['auth', 'verified', 'rate-limit']],

    // Requirement 27: Expose reusable Manobo & Bisaya hybrid translation endpoint
    ['POST', '/api/translate/manobo', 'ManoboController@apiTranslateManobo', []],

    // A detail page whose language has no stored translation asks for one:
    // generated once (MyMemory / Manobo hybrid), stored for every reader.
    // Throttled per session in the controller; never bypasses the urgent-post
    // review gate.
    ['POST', '/api/content/translate', 'VoiceController@ensureTranslation', ['auth', 'verified']],


    // Background retry of unfinished translations, requested by the page after
    // it loads (shared/_background-sweep.php). Any visitor's traffic drives it.
    ['POST', '/api/translations/sweep', 'MaintenanceController@translationSweep', []],
    // Automatic hazard monitoring, asked for by pages in the background.
    ['POST', '/api/hazards/sweep', 'MaintenanceController@hazardSweep', []],
    // For an outside scheduler (cron-job.org etc.): keeps checks running while nobody visits.
    ['GET',  '/api/hazards/cron',  'MaintenanceController@hazardCron', []],

    // Content import. These make outbound HTTP requests to an address the
    // caller supplies, so they are staff-only, rate-limited in the controller,
    // and every fetch is written to the audit log. LinkImporter is where the
    // SSRF guards live — read that before changing any of this.
    ['POST', '/api/admin/import/link',  'ImportController@fromLink',  ['auth', 'role:admin,staff']],
    ['POST', '/api/admin/import/file',  'ImportController@fromFile',  ['auth', 'role:admin,staff']],
    // Preview of a pasted social media link, drawn as residents will see it.
    ['POST', '/api/admin/import/preview', 'ImportController@preview', ['auth', 'role:admin,staff']],
    // Paste cleanup makes no network call at all.
    ['POST', '/api/admin/import/paste', 'ImportController@fromPaste', ['auth', 'role:admin,staff']],

    // Announcement live search
    ['GET',  '/api/announcements/search', 'AnnouncementController@search', ['auth', 'verified']],

    // Status polling (used by the pending page to detect admin approval)
    ['GET', '/api/check-status', 'AuthController@checkStatus', ['auth']],

    // Notifications
    ['GET',  '/api/notifications/unread',       'NotificationController@unreadCount', ['auth']],
    ['POST', '/api/notifications/mark-read',    'NotificationController@markRead',    ['auth']],
    ['POST', '/api/notifications/{id}/read',    'NotificationController@markOneRead', ['auth']],

    // Admin chart data
    ['GET', '/api/admin/charts/registrations',           'AdminController@chartRegistrations',          ['auth', 'role:admin,staff']],
    ['GET', '/api/admin/charts/announcement-categories', 'AdminController@chartAnnouncementCategories', ['auth', 'role:admin,staff']],

    // Event calendar feed (FullCalendar JSON)
    ['GET', '/api/events/calendar', 'EventController@calendarJson', ['auth', 'verified']],

    // Recent notifications for bell dropdown
    ['GET', '/api/notifications/recent', 'NotificationController@recent', ['auth']],

    // SMS balance check (AJAX — admin panel balance widget)
    // Recipient picker on the SMS page: verified residents who have a phone
    // number, searchable by name. Staff-level, capped, read-only.
    ['GET', '/api/admin/sms/recipients', 'SmsController@recipients', ['auth', 'role:admin,staff']],
    // Post picker on the SMS page. Returns each post's real outgoing message
    // and its length, built by the same service the send uses.
    ['GET', '/api/admin/sms/posts', 'SmsController@posts', ['auth', 'role:admin,staff']],
    ['GET', '/api/admin/sms/balance', 'SmsController@balance', ['auth', 'role:admin,staff']],
];