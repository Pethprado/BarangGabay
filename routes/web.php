<?php
declare(strict_types=1);

return [
    ['GET', '/health', 'HealthController@check', []],
    ['GET', '/', 'ResidentController@home', []],
    ['GET', '/login', 'AuthController@showLogin', []],
    ['POST', '/login', 'AuthController@login', []],
    ['GET', '/register', 'AuthController@showRegister', []],
    ['POST', '/register', 'AuthController@register', []],
    ['GET', '/logout', 'AuthController@logout', ['auth']],
    ['GET', '/pending', 'AuthController@pending', ['auth']],
    ['GET', '/announcements', 'AnnouncementController@index', ['auth', 'verified']],
    ['GET', '/announcements/{slug}', 'AnnouncementController@show', ['auth', 'verified']],
    ['GET', '/events', 'EventController@index', ['auth', 'verified']],
    ['GET', '/events/{slug}/calendar', 'EventController@downloadCalendar', ['auth', 'verified']],
    ['GET', '/events/{slug}', 'EventController@show', ['auth', 'verified']],
    ['GET', '/ordinances', 'OrdinanceController@index', ['auth', 'verified']],
    ['GET', '/ordinances/{id}', 'OrdinanceController@show', ['auth', 'verified']],
    // Read-only Manobo dictionary for residents. Curation stays at
    // /admin/manobo; this one only looks words up.
    ['GET', '/manobo', 'ManoboController@residentIndex', ['auth', 'verified']],
    ['GET', '/dictionary', 'ManoboController@residentIndex', ['auth', 'verified']],
    // Global resident search — public content only (posts, ordinances, document types, dictionary).
    ['GET', '/search', 'SearchController@index', ['auth', 'verified']],
    ['GET', '/notifications', 'NotificationController@index', ['auth', 'verified']],
    ['POST', '/notifications/mark-read', 'NotificationController@markRead', ['auth', 'verified']],
    ['GET', '/profile', 'ResidentController@profile', ['auth', 'verified']],
    ['POST', '/profile', 'ResidentController@updateProfile', ['auth', 'verified']],
    ['GET', '/admin', 'AdminController@dashboard', ['auth', 'role:admin,staff']],
    // Standing hazard advisory shown on every resident dashboard. Staff-level
    // on purpose — whoever is on duty when a signal is raised must be able to
    // post it without hunting down an admin.
    ['POST', '/admin/advisory', 'AdminController@updateAdvisory', ['auth', 'role:admin,staff']],
    ['GET', '/admin/announcements', 'AnnouncementController@adminIndex', ['auth', 'role:admin,staff']],
    ['GET', '/admin/announcements/create', 'AnnouncementController@create', ['auth', 'role:admin,staff']],
    ['POST', '/admin/announcements', 'AnnouncementController@store', ['auth', 'role:admin,staff']],
    ['GET', '/admin/announcements/{id}/edit', 'AnnouncementController@edit', ['auth', 'role:admin,staff']],
    // Re-run the machine translation for one post. Needed because the free
    // translation service has a daily per-IP allowance: when it is spent, a
    // save succeeds with no translation attached, and without this the only
    // way to try again is to re-save the post.
    ['POST', '/admin/announcements/{id}/translate', 'AnnouncementController@retranslate', ['auth', 'role:admin,staff']],
    ['POST', '/admin/announcements/{id}', 'AnnouncementController@update', ['auth', 'role:admin,staff']],
    ['POST', '/admin/announcements/{id}/delete', 'AnnouncementController@destroy', ['auth', 'role:admin']],
    ['GET', '/admin/events', 'EventController@adminIndex', ['auth', 'role:admin,staff']],
    ['GET', '/admin/events/create', 'EventController@create', ['auth', 'role:admin,staff']],
    ['POST', '/admin/events', 'EventController@store', ['auth', 'role:admin,staff']],
    ['GET', '/admin/events/{id}/edit', 'EventController@edit', ['auth', 'role:admin,staff']],
    ['POST', '/admin/events/{id}', 'EventController@update', ['auth', 'role:admin,staff']],
    ['POST', '/admin/events/{id}/delete', 'EventController@destroy', ['auth', 'role:admin']],
    ['GET', '/admin/ordinances', 'OrdinanceController@adminIndex', ['auth', 'role:admin,staff']],
    ['GET', '/admin/ordinances/upload', 'OrdinanceController@create', ['auth', 'role:admin,staff']],
    ['POST', '/admin/ordinances', 'OrdinanceController@store', ['auth', 'role:admin,staff']],
    ['GET', '/admin/ordinances/{id}/edit', 'OrdinanceController@edit', ['auth', 'role:admin,staff']],
    ['POST', '/admin/ordinances/{id}', 'OrdinanceController@update', ['auth', 'role:admin,staff']],
    ['POST', '/admin/ordinances/{id}/delete', 'OrdinanceController@destroy', ['auth', 'role:admin']],
    // Back-office account list. Staff could be created but never listed again,
// which made a forgotten staff password unrecoverable.
// Review gate for machine-translated URGENT announcements. Staff-level on
// purpose: a staff member can already write and publish the urgent
// announcement itself, so requiring a higher role to approve its translation
// would be incoherent — and would strand translations when no admin is around.
['GET',  '/admin/translations',        'TranslationReviewController@index',   ['auth', 'role:admin,staff']],
['GET',  '/admin/translations/{id}',   'TranslationReviewController@show',    ['auth', 'role:admin,staff']],
['POST', '/admin/translations/{id}',   'TranslationReviewController@confirm', ['auth', 'role:admin,staff']],

// "Translate this one language, now." Its own path rather than another verb
// on /admin/translations/{id}, which already means "confirm this machine
// translation" — two different actions sharing a route is how the wrong one
// eventually fires.
['POST', '/admin/retranslate',         'TranslationHealthController@retry',   ['auth', 'role:admin,staff']],

// What will happen to this post's languages when Save is pressed. Called
// as the create/edit form is typed in — see TranslationPlanner for why the
// answer cannot honestly be computed in the browser.
['POST', '/admin/translation-plan',    'TranslationHealthController@plan',    ['auth', 'role:admin,staff']],

// The page to open before a defence: every post with a language gap, the
// reason, and the provider state at the top.
['GET',  '/admin/translation-health',  'TranslationHealthController@index',    ['auth', 'role:admin,staff']],
['POST', '/admin/retranslate-all',     'TranslationHealthController@retryAll', ['auth', 'role:admin,staff']],
['POST', '/admin/translation-health/rebuild-manobo', 'TranslationHealthController@rebuildManobo', ['auth', 'role:admin,staff']],

// Voice Training & Voice Dataset Management
['GET',  '/admin/voice-training',           'VoiceTrainingController@index',         ['auth', 'role:admin,superadmin']],
['POST', '/admin/voice-training/samples',   'VoiceTrainingController@storeSample',   ['auth', 'role:admin,superadmin']],
['POST', '/admin/voice-training/update',    'VoiceTrainingController@updateSample',  ['auth', 'role:admin,superadmin']],
['POST', '/admin/voice-training/profile',   'VoiceTrainingController@updateProfile', ['auth', 'role:admin,superadmin']],
['POST', '/admin/voice-training/test',      'VoiceTrainingController@testVoice',     ['auth', 'role:admin,superadmin']],
['GET',  '/admin/voice-training/export',    'VoiceTrainingController@exportDataset', ['auth', 'role:admin,superadmin']],
['POST', '/admin/voice-training/rescan',    'VoiceTrainingController@rescan',        ['auth', 'role:admin,superadmin']],
['POST', '/admin/voice-training/settings',  'VoiceTrainingController@settings',      ['auth', 'role:admin,superadmin']],
['POST', '/admin/voice-training/diagnostics', 'VoiceTrainingController@diagnostics', ['auth', 'role:superadmin']],
// Stored dataset recordings (bytes live in the database — Render's disk is
// ephemeral). Approved clips are what the resident reader plays; the
// controller restricts unreviewed clips to staff.
['GET',  '/voice/audio/{id}',               'VoiceTrainingController@audio',         ['auth']],

['GET',  '/admin/staff',        'ResidentController@staffIndex',  ['auth', 'role:admin,superadmin']],
['GET',  '/admin/staff/create', 'ResidentController@createStaff', ['auth', 'role:admin,superadmin']],
    ['POST', '/admin/staff',        'ResidentController@storeStaff',  ['auth', 'role:admin,superadmin']],
    ['GET', '/admin/residents', 'ResidentController@adminIndex', ['auth', 'role:admin,staff']],
    ['POST', '/admin/residents/bulk', 'ResidentController@bulk', ['auth', 'role:admin,staff']],
    ['GET', '/admin/residents/{id}', 'ResidentController@show', ['auth', 'role:admin,staff']],
    ['POST', '/admin/residents/{id}/verify', 'ResidentController@verify', ['auth', 'role:admin,staff']],
    ['POST', '/admin/residents/{id}/suspend', 'ResidentController@suspend', ['auth', 'role:admin,staff']],
// Password reset for an account that has lost its own password — the system
// has no forgot-password flow, so without this such an account is gone for
// good. Deliberately NOT staff-level: a staff account that could reset
// passwords could reset an admin's and take the account over.
['POST', '/admin/residents/{id}/reset-password', 'ResidentController@resetPassword', ['auth', 'role:admin,superadmin']],
    // Staff see the same reports as admins: the page is aggregate counts and
    // charts over content they already manage, with no admin-only data on it.
    ['GET',  '/admin/reports',                   'AdminController@reports',         ['auth', 'role:admin,staff']],

    // ── My Account (back-office self-service) ─────────────────────────
    // One page for every back-office role: an admin needs to change their own
    // password exactly as much as a staff member does. Every action acts on
    // $_SESSION['user_id'] only — there is no user id in any of these paths.
    ['GET',  '/admin/account',                   'AccountController@index',           ['auth', 'role:admin,staff,superadmin']],
    ['POST', '/admin/account/profile',           'AccountController@updateProfile',   ['auth', 'role:admin,staff,superadmin']],
    ['POST', '/admin/account/email',             'AccountController@updateEmail',     ['auth', 'role:admin,staff,superadmin']],
    ['POST', '/admin/account/password',          'AccountController@updatePassword',  ['auth', 'role:admin,staff,superadmin']],
    ['POST', '/admin/account/preferences',       'AccountController@updatePreferences',['auth', 'role:admin,staff,superadmin']],
    ['POST', '/admin/account/sessions/revoke',   'AccountController@revokeSessions',  ['auth', 'role:admin,staff,superadmin']],

    // SMS management
    ['GET',  '/admin/sms',                       'SmsController@index',             ['auth', 'role:admin,staff']],
    ['POST', '/admin/sms/send',                  'SmsController@send',              ['auth', 'role:admin,staff']],
    ['POST', '/admin/sms/broadcast',             'SmsController@broadcast',         ['auth', 'role:admin,staff']],
    // Send an already-published post. Separate from broadcast because the
    // message is derived from a post and every row it writes to sms_logs
    // points back at that post.
    ['POST', '/admin/sms/post',                  'SmsController@sendPost',          ['auth', 'role:admin,staff']],
    // Manobo dictionary curation (see data/manobo/README.md and migration 030
    // — the dataset moved from a CSV file to the manobo_dictionary table).
    // CHANGED: store/update/delete widened to admin+staff (explicitly asked
    // for — staff should be able to curate words, not just admins). Restoring
    // from Trash and purging permanently stay admin-only: irreversible.
    ['GET',  '/admin/manobo',                    'ManoboController@adminIndex',     ['auth', 'role:admin,staff']],
    ['GET',  '/admin/dictionary',                'ManoboController@adminIndex',     ['auth', 'role:admin,staff']],
    ['GET',  '/admin/manobo/export',             'ManoboController@export',         ['auth', 'role:admin,staff']],
    ['POST', '/admin/manobo',                    'ManoboController@store',          ['auth', 'role:admin,staff']],
    ['POST', '/admin/manobo/update',             'ManoboController@update',         ['auth', 'role:admin,staff']],
    ['POST', '/admin/manobo/approve',            'ManoboController@approve',        ['auth', 'role:admin,staff']],
    ['POST', '/admin/manobo/archive',            'ManoboController@archive',        ['auth', 'role:admin,staff']],
    ['POST', '/admin/manobo/delete',             'ManoboController@destroy',        ['auth', 'role:admin,staff']],
    ['POST', '/admin/manobo/import',             'ManoboController@importCsv',      ['auth', 'role:admin,staff']],
    ['POST', '/admin/manobo/import-doc',         'ManoboController@importDoc',      ['auth', 'role:admin,staff']],
    ['GET',  '/admin/manobo/import-preview',     'ManoboController@importPreview',  ['auth', 'role:admin,staff']],
    ['POST', '/admin/manobo/import-confirm',     'ManoboController@importConfirm',  ['auth', 'role:admin,staff']],
    ['POST', '/admin/manobo/import-undo',        'ManoboController@undoImport',     ['auth', 'role:admin']],
    ['POST', '/admin/manobo/regenerate-posts',   'ManoboController@regeneratePosts',['auth', 'role:admin,staff']],
    ['GET',  '/admin/manobo/trash',              'ManoboController@trashIndex',     ['auth', 'role:admin']],
    ['POST', '/admin/manobo/trash/restore',      'ManoboController@restore',        ['auth', 'role:admin']],
    ['POST', '/admin/manobo/trash/delete',       'ManoboController@forceDelete',    ['auth', 'role:admin']],

    // Bisaya dictionary curation — the second-tier fallback behind Manobo for
    // the MN button (see data/bisaya/README.md). Same permission shape.
    ['GET',  '/admin/bisaya',                    'ManoboController@bisayaAdminIndex',  ['auth', 'role:admin,staff']],
    ['GET',  '/admin/bisaya/export',             'ManoboController@bisayaExport',      ['auth', 'role:admin,staff']],
    ['POST', '/admin/bisaya',                    'ManoboController@bisayaStore',       ['auth', 'role:admin,staff']],
    ['POST', '/admin/bisaya/update',             'ManoboController@bisayaUpdate',      ['auth', 'role:admin,staff']],
    ['POST', '/admin/bisaya/delete',             'ManoboController@bisayaDestroy',     ['auth', 'role:admin,staff']],
    ['POST', '/admin/bisaya/import',             'ManoboController@bisayaImportCsv',   ['auth', 'role:admin,staff']],
    ['GET',  '/admin/bisaya/trash',              'ManoboController@bisayaTrashIndex',  ['auth', 'role:admin']],
    ['POST', '/admin/bisaya/trash/restore',      'ManoboController@bisayaRestore',     ['auth', 'role:admin']],
    ['POST', '/admin/bisaya/trash/delete',       'ManoboController@bisayaForceDelete', ['auth', 'role:admin']],

    ['GET',  '/admin/feedback',                  'FeedbackController@adminIndex',   ['auth', 'role:admin,staff']],
    ['GET',  '/admin/feedback/{id}/messages',    'FeedbackController@adminThread',  ['auth', 'role:admin,staff']],
    ['POST', '/admin/feedback/{id}/reply',       'FeedbackController@adminReply',   ['auth', 'role:admin,staff']],
    // Document requests: Personal Pickup & Digital Soft Copy delivery
    ['GET',  '/documents',                        'DocumentRequestController@index',        ['auth', 'verified']],
    ['POST', '/documents',                        'DocumentRequestController@store',        ['auth', 'verified']],
    ['GET',  '/documents/history',                'DocumentRequestController@history',      ['auth', 'verified']],
    ['GET',  '/documents/{id}/payment',           'PaymentController@checkout',             ['auth', 'verified']],
    ['GET',  '/documents/{id}/checkout',          'PaymentController@checkout',             ['auth', 'verified']],
    ['POST', '/documents/{id}/payment',           'PaymentController@residentUploadProof',  ['auth', 'verified']],
    ['GET',  '/documents/{id}/acknowledgement',   'PaymentController@acknowledgement',      ['auth', 'verified']],
    ['GET',  '/documents/{id}/download',          'DocumentRequestController@download',     ['auth', 'verified']],
    ['GET',  '/documents/{id}/preview',           'DocumentRequestController@preview',      ['auth', 'verified']],
    ['GET',  '/documents/{ref}/verify',           'DocumentRequestController@verifyPublic', []],
    ['GET',  '/admin/documents',                  'DocumentRequestController@adminIndex',   ['auth', 'role:admin,staff']],
    ['POST', '/admin/documents/{id}/status',      'DocumentRequestController@updateStatus', ['auth', 'role:admin,staff']],
    ['POST', '/admin/documents/{id}/approve',     'DocumentRequestController@approve',      ['auth', 'role:admin,staff']],
    ['POST', '/admin/documents/{id}/claim',       'DocumentRequestController@claim',        ['auth', 'role:admin,staff']],
    ['POST', '/admin/documents/{id}/delivery-status','DocumentRequestController@updateDelivery',['auth', 'role:admin,staff']],
    ['POST', '/admin/documents/{id}/upload',      'DocumentRequestController@uploadFile',   ['auth', 'role:admin,staff']],
    ['POST', '/admin/documents/{id}/replace',     'DocumentRequestController@replaceFile',  ['auth', 'role:admin,staff']],
    ['POST', '/admin/documents/{id}/remove-file', 'DocumentRequestController@removeFile',   ['auth', 'role:admin,staff']],
    ['GET',  '/admin/documents/{id}/download',    'DocumentRequestController@adminDownload',['auth', 'role:admin,staff']],
    ['GET',  '/admin/documents/{id}/preview',     'DocumentRequestController@adminPreview', ['auth', 'role:admin,staff']],
    ['GET',  '/admin/documents/{id}/details',     'DocumentRequestController@details',      ['auth', 'role:admin,staff']],
    ['POST', '/admin/documents/{id}/pickup-payment','PaymentController@markRequestPaidAtPickup',['auth', 'role:admin,staff']],

    // Profile Updates Management
    ['POST', '/profile/request-update',           'ResidentController@requestProfileUpdate', ['auth', 'verified']],
    ['GET',  '/admin/profile-updates',            'ResidentController@profileUpdatesIndex',  ['auth', 'role:admin,staff']],
    ['POST', '/admin/profile-updates/{id}/approve','ResidentController@approveProfileUpdate', ['auth', 'role:admin,staff']],
    ['POST', '/admin/profile-updates/{id}/reject', 'ResidentController@rejectProfileUpdate',  ['auth', 'role:admin,staff']],
    ['GET',  '/admin/profile-updates/{id}/document','ResidentController@downloadProfileDoc', ['auth', 'role:admin,staff']],

    // Payment Management System
    ['GET',  '/admin/payments',                   'PaymentController@adminIndex',       ['auth', 'role:admin,staff']],
    ['POST', '/admin/payments/{id}/verify',       'PaymentController@verify',           ['auth', 'role:admin,staff']],
    ['POST', '/admin/payments/{id}/reject',       'PaymentController@reject',           ['auth', 'role:admin,staff']],
    ['POST', '/admin/payments/{id}/mark-pickup',  'PaymentController@markPaidAtPickup', ['auth', 'role:admin,staff']],
    ['POST', '/admin/payments/{id}/waive',        'PaymentController@waive',            ['auth', 'role:admin']],
    ['POST', '/admin/payments/{id}/refund',       'PaymentController@refund',           ['auth', 'role:admin']],
    ['GET',  '/admin/payments/{id}/receipt',      'PaymentController@receiptPreview',   ['auth', 'role:admin,staff']],
    ['GET',  '/admin/payments/fees',              'PaymentController@feesIndex',        ['auth', 'role:admin']],
    ['POST', '/admin/payments/fees',              'PaymentController@feesUpdate',       ['auth', 'role:admin']],
    ['GET',  '/admin/payments/gcash',             'PaymentController@gcashIndex',       ['auth', 'role:admin,staff']],
    ['POST', '/admin/payments/gcash',             'PaymentController@gcashStore',       ['auth', 'role:admin,staff']],
    ['POST', '/admin/payments/gcash/{id}',        'PaymentController@gcashUpdate',      ['auth', 'role:admin,staff']],
    ['POST', '/admin/payments/gcash/{id}/default','PaymentController@gcashSetDefault',  ['auth', 'role:admin,staff']],
    ['POST', '/admin/payments/gcash/{id}/toggle', 'PaymentController@gcashToggle',      ['auth', 'role:admin,staff']],
    ['GET',  '/admin/payments/export',            'PaymentController@exportCsv',        ['auth', 'role:admin']],

    // PayMongo GCash Online Payment Endpoints
    ['POST', '/api/payments/paymongo/create-checkout',      'PaymentController@paymongoCreateCheckout', ['auth', 'verified']],
    ['GET',  '/payments/paymongo/return',                   'PaymentController@paymongoReturn',         ['auth']],
    ['GET',  '/documents/payment/success',                  'PaymentController@paymentSuccess',         ['auth', 'verified']],
    ['GET',  '/documents/payment/cancel',                   'PaymentController@paymentCancel',          ['auth', 'verified']],
    ['GET',  '/api/payments/status/{id}',                   'PaymentController@apiPaymentStatus',       ['auth', 'verified']],
    ['POST', '/api/payments/paymongo/webhook',              'PaymentController@paymongoWebhook',        []],
    ['POST', '/payments/paymongo/webhook',                  'PaymentController@paymongoWebhook',        []],

    // Emergency: where this resident's purok evacuates to, and the one-tap
    // "Ligtas ako" answer after a storm.
    ['GET',  '/evacuation',                      'EmergencyController@centers',            ['auth', 'verified']],
    ['POST', '/safety-checkin/{id}',             'EmergencyController@checkIn',            ['auth', 'verified']],
    ['GET',  '/admin/safety',                    'EmergencyController@adminRollUp',        ['auth', 'role:admin,staff']],
    ['GET',  '/admin/evacuation',                'EmergencyController@adminCenters',       ['auth', 'role:admin,staff']],
    ['POST', '/admin/evacuation',                'EmergencyController@storeCenter',        ['auth', 'role:admin,staff']],
    ['POST', '/admin/evacuation/{id}',           'EmergencyController@updateCenter',       ['auth', 'role:admin,staff']],
    ['POST', '/admin/evacuation/{id}/delete',    'EmergencyController@deleteCenter',       ['auth', 'role:admin']],

    ['GET',  '/feedback',                        'FeedbackController@index',        ['auth', 'verified']],
    ['POST', '/feedback',                        'FeedbackController@store',        ['auth', 'verified']],
    ['POST', '/feedback/{id}/reply',             'FeedbackController@residentReply',['auth', 'verified']],
    // ── Super Admin system module (superadmin role only) ──────────────
    ['GET',  '/superadmin/errors',               'SuperAdminController@errorLogs',   ['auth', 'role:superadmin']],
    ['POST', '/superadmin/errors/resolve',       'SuperAdminController@resolveError',['auth', 'role:superadmin']],
    // Diagnose one error and repair it where that is safe. Superadmin only:
    // the remedies replay migrations and touch the uploads folder.
    ['POST', '/superadmin/errors/fix',           'SuperAdminController@fixError',    ['auth', 'role:superadmin']],
    ['POST', '/superadmin/errors/purge',         'SuperAdminController@purgeErrors', ['auth', 'role:superadmin']],
    ['GET',  '/superadmin/sessions',             'SuperAdminController@sessions',      ['auth', 'role:superadmin']],
    ['POST', '/superadmin/sessions/revoke',      'SuperAdminController@revokeSession', ['auth', 'role:superadmin']],
    ['POST', '/superadmin/sessions/unlock',      'SuperAdminController@unlockAccount', ['auth', 'role:superadmin']],
    ['POST', '/superadmin/sessions/unlock-ip',   'SuperAdminController@unlockIp',      ['auth', 'role:superadmin']],
    ['GET',  '/superadmin/backups',              'SuperAdminController@backups',        ['auth', 'role:superadmin']],
    ['GET',  '/superadmin/backups/download',     'SuperAdminController@downloadBackup', ['auth', 'role:superadmin']],
    ['POST', '/superadmin/backups',              'SuperAdminController@createBackup',   ['auth', 'role:superadmin']],
    ['POST', '/superadmin/backups/delete',       'SuperAdminController@deleteBackup',   ['auth', 'role:superadmin']],
    ['GET',  '/superadmin/settings',             'SuperAdminController@settings',       ['auth', 'role:superadmin']],
    ['POST', '/superadmin/settings',             'SuperAdminController@updateSettings', ['auth', 'role:superadmin']],
    ['POST', '/superadmin/settings/logo',        'SuperAdminController@uploadLogo',     ['auth', 'role:superadmin']],
    ['POST', '/superadmin/settings/logo/reset',  'SuperAdminController@resetLogo',      ['auth', 'role:superadmin']],
    ['GET',  '/superadmin/ai-accuracy',          'SuperAdminController@aiAccuracy',     ['auth', 'role:superadmin']],
    ['GET',  '/admin/system/seed-demo',          'SuperAdminController@seedDemo',       ['auth', 'role:admin,superadmin']],
    ['POST', '/admin/system/seed-demo',          'SuperAdminController@seedDemo',       ['auth', 'role:admin,superadmin']],


    // ── Two-factor authentication ─────────────────────────────────────
    // The challenge routes carry no 'auth' middleware on purpose: the user is
    // half-authenticated there. TwoFactorController gates them on the
    // 2fa_pending_user_id session key instead.
    ['GET',  '/two-factor/challenge',  'TwoFactorController@challenge',       []],
    ['POST', '/two-factor/challenge',  'TwoFactorController@verifyChallenge', []],
    ['GET',  '/two-factor/cancel',     'TwoFactorController@cancelChallenge', []],

    ['GET',  '/two-factor',               'TwoFactorController@index',           ['auth']],
    ['POST', '/two-factor/enable',        'TwoFactorController@enable',          ['auth']],
    ['POST', '/two-factor/disable',       'TwoFactorController@disable',         ['auth']],
    ['POST', '/two-factor/backup-codes',  'TwoFactorController@regenerateCodes', ['auth']],

    ['GET',  '/verify-email', 'AuthController@verifyEmail', []],
    // Re-send the link to the signed-in user's OWN address (dashboard prompt).
    ['POST', '/resend-verification', 'AuthController@resendVerification', ['auth']],

    // UI language switch (English / Filipino / Manobo chrome strings — see app/helpers.php::t())
    ['GET', '/set-locale/{locale}', 'LocaleController@set', []],
];
