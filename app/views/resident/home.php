<?php
/**
 * Resident dashboard.
 *
 * This page is deliberately NOT the public bulletin board. Everything above
 * the "Around the barangay" divider is about the signed-in resident: what they
 * have not read, who has replied to them, what is new since they were last
 * here. Barangay-wide totals are kept, but demoted to context — "there are 24
 * announcements" tells Juan nothing about Juan.
 *
 * The generic marketing hero is intentionally off here (it is still shown to
 * guests on public/landing). On a dashboard it pushed everything personal —
 * including the urgent-notice banner — below the fold.
 *
 * Order of the page, and why:
 *   1. Hazard advisory  — standing conditions set by staff. Nothing outranks it.
 *   2. Urgent notices   — one-off urgent posts, still above the fold.
 *   3. Who you are      — greeting, verification state.
 *   4. Needs attention  — only what is actually outstanding, or "all caught up",
 *                         or a getting-started panel on a brand-new account.
 *   5. Today / this week— content, before navigation.
 *   6. Quick actions    — navigation, after content.
 *   7. Notifications + conversations, then barangay-wide context.
 *
 * Variables from ResidentController::home():
 *   array $user, string $advisoryText, string $advisoryLevel, string $zone,
 *   ?int $zoneNeighbours, int $accountAgeDays, int $newAnnouncements,
 *   int $unreadNotifications, array $recentNotifications,
 *   array $feedbackThreads, int $openFeedbackCount, int $unreadFeedbackCount,
 *   array $urgentAnnouncements, array $latestAnnouncements,
 *   array $todayEvents, array $weekEvents, array $laterEvents,
 *   int $totalAnnouncements, int $upcomingEventsCount, int $activeOrdinances
 *
 * CHANGED (theme rollout): all markup, PHP logic, routes and Filipino labels
 * are unchanged. Every stock Tailwind colour utility this page uses
 * (bg-blue-100, text-amber-700, bg-slate-50, ...) is now retinted to the
 * Manobo-inspired earth palette by public/assets/css/theme.css's "TAILWIND
 * CDN UTILITY RETINT" section, so no class names below needed to change. The
 * few literal inline hex gradients (avatar fallback, quick-action tiles, the
 * advisory colour map) were swapped for theme tokens directly, marked below.
 */
$showHero = false;
$bareContent = true;

$user                = $user                ?? [];
$newAnnouncements    = (int) ($newAnnouncements    ?? 0);
$unreadNotifications = (int) ($unreadNotifications ?? 0);
$recentNotifications = $recentNotifications ?? [];
$feedbackThreads     = $feedbackThreads     ?? [];
$openFeedbackCount   = (int) ($openFeedbackCount   ?? 0);
$unreadFeedbackCount = (int) ($unreadFeedbackCount ?? 0);
$urgentAnnouncements = $urgentAnnouncements ?? [];
$todayEvents         = $todayEvents         ?? [];
$weekEvents          = $weekEvents          ?? [];
$laterEvents         = $laterEvents         ?? [];
$advisoryText        = trim((string) ($advisoryText ?? ''));
$advisoryLevel       = (string) ($advisoryLevel ?? 'warning');
$zone                = (string) ($zone ?? '');
$zoneNeighbours      = $zoneNeighbours ?? null;
$accountAgeDays      = (int) ($accountAgeDays ?? 999);
$featuredOrdinance   = $featuredOrdinance ?? null;

// Category and urgency styling comes from helpers.php so every page agrees.
// This file used to keep its own copy, in which government and infrastructure
// were both blue — the two were indistinguishable here and government was a
// different colour on every other page.
$categoryLabels = category_labels();

$hour     = (int) date('G');
$greeting = $hour < 12 ? t('resident_home.greet_morning')
          : ($hour < 18 ? t('resident_home.greet_afternoon') : t('resident_home.greet_evening'));
$firstName     = explode(' ', trim((string) ($user['full_name'] ?? '')))[0] ?: '';
$initial       = mb_strtoupper(mb_substr((string) ($user['full_name'] ?? '?'), 0, 1, 'UTF-8'), 'UTF-8');
$isVerified    = ($user['status'] ?? '') === 'verified';
$emailVerified = (int) ($user['email_verified'] ?? 0) === 1;

/** Short, locale-neutral timestamp for list rows. */
$shortWhen = static fn (?string $ts): string => $ts ? date('M j, g:i A', strtotime($ts)) : '';

/** One event card body, reused by the today / week / later strips. */
$eventMeta = static function (array $ev): array {
    $start = strtotime((string) $ev['event_date']);
    $end   = !empty($ev['end_date']) ? strtotime((string) $ev['end_date']) : null;
    $now   = time();
    return [
        'start'  => $start,
        'isNow'  => $start <= $now && ($end === null ? date('Y-m-d') === date('Y-m-d', $start) : $end >= $now),
        'manobo' => !empty($ev['title_manobo']) || !empty($ev['description_manobo']),
    ];
};

ob_start();
?>
<style>
.rd-welcome { position: relative; overflow: hidden; min-height: 150px; padding: 1.6rem 1.75rem; border-radius: 1.1rem; display: flex; flex-direction: column; justify-content: center;
    background: linear-gradient(90deg, rgba(14,58,34,.92) 0%, rgba(14,58,34,.70) 45%, rgba(14,58,34,.05) 80%), url('<?= e(asset('assets/images/ui/welcome-banner.jpg')) ?>') right center / cover no-repeat; box-shadow: var(--shadow-card); }
.rd-welcome__name { margin: 0; font-weight: 900; font-size: clamp(1.5rem, 3.2vw, 2rem); line-height: 1.15; color: #fff; }
.rd-welcome__tag { margin: .35rem 0 0; font-size: .95rem; font-weight: 500; color: rgba(255,255,255,.92); }
.rd-chip { display: inline-flex; align-items: center; gap: .3rem; padding: .15rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 700; background: rgba(255,255,255,.18); color: #fff; }
.rd-quick { display: grid; gap: .9rem; grid-template-columns: repeat(2, minmax(0,1fr)); }
@media (min-width: 900px) { .rd-quick { grid-template-columns: repeat(4, minmax(0,1fr)); } }
.rd-quick__item { display: flex; flex-direction: column; align-items: center; text-align: center; gap: .2rem; padding: 1.1rem .8rem; text-decoration: none; transition: transform .15s, box-shadow .15s; }
.rd-quick__item:hover { transform: translateY(-2px); box-shadow: var(--shadow-lift); }
.rd-quick__item strong { font-size: .95rem; color: var(--text-primary); }
.rd-quick__item span:last-child { font-size: .76rem; color: var(--text-muted); }
.rd-quick__icon { width: 46px; height: 46px; margin-bottom: .4rem; display: grid; place-items: center; border-radius: 999px; font-size: 1.2rem; background: var(--brand-primary-light); color: var(--action-solid); }
.rd-quick__icon--orange { background: rgba(232,89,12,.12); color: #e8590c; }
.rd-quick__icon--blue { background: rgba(37,99,235,.12); color: #2563eb; }
.rd-quick__icon--red { background: var(--status-danger-bg); color: var(--status-danger); }
.rd-posts { display: grid; gap: 1rem; }
@media (min-width: 768px) { .rd-posts { grid-template-columns: 1fr 1fr; } .rd-posts > .rd-post:first-child { grid-column: 1 / -1; flex-direction: row; } .rd-posts > .rd-post:first-child .rd-post__img { width: 42%; aspect-ratio: auto; min-height: 210px; } }
.rd-tags { display: flex; gap: .35rem; }
.rd-tags span { padding: .05rem .45rem; border-radius: .35rem; font-size: .66rem; font-weight: 800; background: var(--surface-muted); color: var(--text-secondary); border: 1px solid var(--border); }
/* Alert cards in the mockup's red. */
.urgent-banner { border-radius: 1rem; overflow: hidden; }
.rd-card { background: var(--surface-card); border: 1px solid var(--border); border-radius: 1rem; box-shadow: var(--shadow-card); }
.rd-lang { display: grid; gap: 1rem; padding: 1rem; align-items: center; }
@media (min-width: 768px) { .rd-lang { grid-template-columns: auto 1fr; } }
.rd-lang__opts { display: flex; gap: .5rem; }
.rd-lang__opt { min-width: 84px; min-height: 56px; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: .5rem .9rem; border-radius: .8rem; border: 1px solid var(--border); background: var(--surface-muted); color: var(--text-secondary); text-decoration: none; }
.rd-lang__opt strong { font-size: 1rem; color: var(--text-primary); }
.rd-lang__opt span { font-size: .75rem; }
.rd-lang__opt.is-active { background: var(--action-solid); border-color: var(--action-solid); color: var(--text-on-action); }
.rd-lang__opt.is-active strong { color: var(--text-on-action); }
.rd-btn { display: inline-flex; align-items: center; gap: .5rem; min-height: 44px; padding: .55rem 1.1rem; border-radius: .75rem; background: var(--action-solid); color: var(--text-on-action); font-weight: 700; font-size: .9rem; text-decoration: none; }
.rd-btn:hover { background: var(--action-solid-hover); color: var(--text-on-action); }
.rd-post { overflow: hidden; display: flex; flex-direction: column; transition: transform .15s ease, box-shadow .15s ease; }
.rd-post:hover { transform: translateY(-2px); box-shadow: var(--shadow-lift); }
.rd-post__img { aspect-ratio: 16 / 9; background-color: var(--surface-muted); background-size: cover; background-position: center; }
.rd-post__img img { width: 100%; height: 100%; object-fit: cover; display: block; }
.rd-post__body { padding: .9rem 1rem 1rem; display: flex; flex-direction: column; gap: .4rem; flex: 1; }
.rd-badge { display: inline-flex; align-items: center; gap: .25rem; padding: .1rem .5rem; border-radius: 999px; font-size: .7rem; font-weight: 800; }
.rd-badge--urgent { background: var(--status-danger-bg); color: var(--status-danger); }
.rd-badge--important { background: var(--status-warning-bg); color: var(--status-warning); }
@media (prefers-reduced-motion: reduce) { .rd-post { transition: none; } .rd-post:hover { transform: none; } }
</style>


<!-- ══ A. PERSONAL STATUS STRIP ═══════════════════════════════════════════ -->
<section class="fade-up mb-6">
    <?php // surface-card-gradient, not Tailwind's from-white/to-slate-50: a baked-in
          // white gradient stays white in dark mode while the text turns near-white. ?>
    <div class="rd-welcome">
        <h1 class="rd-welcome__name"><?= e(t('resident_home.welcome_name', ['name' => $firstName !== '' ? $firstName : (string) ($user['full_name'] ?? '')])) ?></h1>
        <p class="rd-welcome__tag"><?= e(t('resident_home.welcome_tagline')) ?></p>
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <?php if ($isVerified): ?>
            <span class="rd-chip"><i class="bi bi-patch-check-fill"></i> <?= e(t('resident_home.status_verified')) ?></span>
            <?php endif; ?>
            <?php if (!empty($user['zone'])): ?>
            <span class="rd-chip"><i class="bi bi-geo-alt-fill"></i> <?= e((string) $user['zone']) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Email verification prompt — an unverified resident never receives
         announcement or emergency email, so this is worth interrupting for. -->
    <?php if (!$emailVerified): ?>
    <div class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:p-5">
        <div class="flex flex-wrap items-start gap-3">
            <i class="bi bi-envelope-exclamation-fill mt-0.5 flex-shrink-0 text-lg text-amber-600"></i>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-amber-900"><?= e(t('resident_home.verify_title')) ?></p>
                <p class="mt-1 text-sm leading-relaxed text-amber-800">
                    <?= e(t('resident_home.verify_body', ['email' => (string) ($user['email'] ?? '')])) ?>
                </p>
                <form method="post" action="<?= e(route('resend-verification')) ?>" class="mt-3">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button type="submit"
                            class="inline-flex min-h-[44px] items-center gap-2 rounded-xl bg-amber-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-amber-800">
                        <i class="bi bi-send"></i> <?= e(t('resident_home.verify_cta')) ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</section>

<!-- ══ HAZARD ADVISORY ════════════════════════════════════════════════════
     A STANDING state set by barangay staff (/admin), distinct from an urgent
     announcement, which is a one-off post. Styled as a hard-edged advisory
     strip rather than another rounded card so it reads as "conditions right
     now", not "another item in the feed". Deliberately NOT dismissible: it
     goes away when the barangay takes it down, not when a resident taps ✕. -->
<?php if ($advisoryText !== ''):
    // CHANGED: blue/amber/red -> the theme's earthy info/warning/danger tones.
    $advisoryStyles = [
        'info'    => ['bar' => '#5b5240', 'bg' => '#ece7dd', 'ink' => '#3f382c', 'icon' => 'bi-info-circle-fill'],
        'warning' => ['bar' => '#8a5a12', 'bg' => '#f5e8d0', 'ink' => '#5a3a0c', 'icon' => 'bi-exclamation-triangle-fill'],
        'danger'  => ['bar' => '#a3311c', 'bg' => '#f6ded7', 'ink' => '#6b1f11', 'icon' => 'bi-exclamation-octagon-fill'],
    ];
    $aStyle = $advisoryStyles[$advisoryLevel] ?? $advisoryStyles['warning'];
?>
<section class="mb-4" aria-label="<?= e(t('resident_home.advisory_label')) ?>">
    <div class="advisory-strip" role="status"
         style="--advisory-bar:<?= $aStyle['bar'] ?>;--advisory-bg:<?= $aStyle['bg'] ?>;--advisory-ink:<?= $aStyle['ink'] ?>;">
        <i class="bi <?= $aStyle['icon'] ?> advisory-strip-icon" aria-hidden="true"></i>
        <div class="min-w-0">
            <p class="advisory-strip-label"><?= e(t('resident_home.advisory_label')) ?></p>
            <p class="advisory-strip-text"><?= e($advisoryText) ?></p>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ══ C. URGENT NOTICE ═══════════════════════════════════════════════════
     Pinned above everything. Full-width, high-contrast, and never hidden on a
     timer — in a coastal barangay "urgent" means typhoon signal, evacuation or
     water interruption. Dismissal is per-announcement and per-device only.
     Rendered visible by default so a resident with JS disabled still sees it. -->
<?php if (!empty($urgentAnnouncements)): ?>
<section class="mb-6 space-y-3" aria-label="<?= e(t('resident_home.urgent_label')) ?>">
    <?php foreach ($urgentAnnouncements as $u): ?>
    <article class="urgent-banner" role="alert" data-urgent-id="<?= (int) $u['id'] ?>">
        <div class="urgent-banner-bar" aria-hidden="true"></div>
        <div class="flex items-start gap-3 p-4 sm:p-5">
            <span class="urgent-banner-icon" aria-hidden="true">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </span>
            <div class="min-w-0 flex-1">
                <?php // Fixed white-on-red via .urgent-banner-* — the red-50/red-100
                      // tints sat just under 4.5:1 on this background. ?>
                <p class="urgent-banner-eyebrow text-[11px] font-black uppercase tracking-widest">
                    <?= e(t('resident_home.urgent_label')) ?>
                    <span class="font-semibold">&middot; <?= e(date('M j, Y', strtotime((string) $u['published_at']))) ?></span>
                </p>
                <h2 class="urgent-banner-title mt-1 text-lg font-black leading-tight sm:text-xl">
                    <?= e(localised_text($u, 'title')) ?>
                </h2>
                <?php $snippet = trim(strip_tags((string) ($u['excerpt'] ?? ''))); ?>
                <?php if ($snippet !== ''): ?>
                <p class="urgent-banner-body mt-1.5 line-clamp-3 text-sm leading-relaxed">
                    <?= e(mb_strimwidth($snippet, 0, 180, '…', 'UTF-8')) ?>
                </p>
                <?php endif; ?>
                <?php // urgent-banner-cta pins this pill to white-on-red in BOTH themes —
                      // the banner behind it is always dark red, so the usual dark-mode
                      // remapping of bg-white / text-red-700 would make it dark-on-dark. ?>
                <a href="<?= e(route('announcements/' . $u['slug'])) ?>"
                   class="urgent-banner-cta mt-3 inline-flex min-h-[44px] items-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-bold shadow-sm transition">
                    <?= e(t('resident_home.urgent_read')) ?> <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <button type="button"
                    class="urgent-dismiss"
                    data-dismiss-urgent="<?= (int) $u['id'] ?>"
                    aria-label="<?= e(t('resident_home.urgent_dismiss')) ?>"
                    title="<?= e(t('resident_home.urgent_dismiss')) ?>">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </article>
    <?php endforeach; ?>
</section>
<script>
    /* Runs immediately (not deferred) so an already-dismissed notice never
       flashes on screen. Fail-safe by design: if this script does not run, the
       banner simply stays visible. */
    (function () {
        var KEY = 'bg-urgent-dismissed';
        function read() {
            try { return JSON.parse(localStorage.getItem(KEY) || '[]') || []; } catch (e) { return []; }
        }
        var dismissed = read();
        document.querySelectorAll('[data-urgent-id]').forEach(function (el) {
            if (dismissed.indexOf(String(el.getAttribute('data-urgent-id'))) !== -1) {
                el.hidden = true;
            }
        });
        document.querySelectorAll('[data-dismiss-urgent]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = String(btn.getAttribute('data-dismiss-urgent'));
                var list = read();
                if (list.indexOf(id) === -1) { list.push(id); }
                try { localStorage.setItem(KEY, JSON.stringify(list.slice(-50))); } catch (e) {}
                var card = btn.closest('[data-urgent-id]');
                if (card) { card.hidden = true; }
            });
        });
    })();
</script>
<?php endif; ?>

<!-- ══ QUICK CARDS (mockup row) ═══════════════════════════════════════════ -->
<section class="fade-up mb-6 rd-quick" aria-label="<?= e(t('resident_home.quick_label')) ?>">
    <?php foreach ([
        [route('announcements'), 'bi-megaphone-fill',        t('nav.announcements'), t('resident_home.quick_ann_d'),  ''],
        [route('events'),        'bi-calendar-event-fill',   t('nav.events'),        t('resident_home.quick_evt_d'),  'orange'],
        [route('ordinances'),    'bi-journal-text',          t('nav.ordinances'),    t('resident_home.quick_ord_d'),  'blue'],
        [route('evacuation'),    'bi-house-heart-fill',      t('resident_home.quick_evac'), t('resident_home.quick_evac_d'), 'red'],
    ] as [$qHref, $qIcon, $qTitle, $qDesc, $qTone]): ?>
        <a href="<?= e($qHref) ?>" class="rd-card rd-quick__item">
            <span class="rd-quick__icon<?= $qTone ? ' rd-quick__icon--' . $qTone : '' ?>" aria-hidden="true"><i class="bi <?= $qIcon ?>"></i></span>
            <strong><?= e($qTitle) ?></strong>
            <span><?= e($qDesc) ?></span>
        </a>
    <?php endforeach; ?>
</section>

<!-- ══ LANGUAGE & VOICE ════════════════════════════════════════════════════
     The header's EN / FIL / MN switch, repeated here as the mockup's card.
     Every post's Voice Reader reads the selected language using the
     barangay's approved recordings. -->
<?php $curLocale = current_locale(); ?>
<section class="fade-up mb-8" aria-labelledby="rd-lang-title">
    <h2 id="rd-lang-title" class="mb-3 text-sm font-bold uppercase tracking-widest text-slate-500"><?= e(t('resident_home.lang_voice_title')) ?></h2>
    <div class="rd-card rd-lang">
        <div class="rd-lang__opts" role="group" aria-label="<?= e(t('resident_home.lang_voice_title')) ?>">
            <?php foreach (['en' => ['EN', 'English'], 'fil' => ['FIL', 'Filipino'], 'msm' => ['MN', 'Manobo']] as $lc => [$lcShort, $lcName]): ?>
                <a href="<?= e(route('set-locale/' . $lc)) ?>" class="rd-lang__opt<?= $curLocale === $lc ? ' is-active' : '' ?>" aria-current="<?= $curLocale === $lc ? 'true' : 'false' ?>">
                    <strong><?= $lcShort ?></strong><span><?= $lcName ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="rd-lang__voice">
            <p class="text-sm text-slate-600 mb-2"><?= e(t('resident_home.lang_voice_hint')) ?></p>
            <?php if (!empty($latestPosts[0])): ?>
                <a href="<?= e(route('announcements/' . $latestPosts[0]['slug'])) ?>" class="rd-btn">
                    <i class="bi bi-volume-up-fill" aria-hidden="true"></i> <?= e(t('resident_home.listen_page')) ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ══ LATEST POSTS ════════════════════════════════════════════════════════ -->
<?php if (!empty($latestPosts)): ?>
<section class="fade-up mb-8" aria-labelledby="rd-latest-title">
    <div class="mb-3 flex items-baseline justify-between gap-3">
        <h2 id="rd-latest-title" class="text-sm font-bold uppercase tracking-widest text-slate-500"><?= e(t('resident_home.latest_posts')) ?></h2>
        <a href="<?= e(route('announcements')) ?>" class="text-sm font-bold text-blue-700"><?= e(t('resident_home.view_all')) ?> <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="rd-posts">
        <?php foreach ($latestPosts as $lp):
            $lpTitle = localised_text($lp, 'title');
            $lpBody  = \App\Services\SpokenText::repairJoins(\App\Services\SpokenText::plain(localised_text($lp, 'body')));
            $lpUrg   = (string) ($lp['urgency'] ?? 'normal');
            $lpImg   = trim((string) ($lp['cover_image_url'] ?? ''));
            $lpUrl   = route('announcements/' . $lp['slug']);
        ?>
        <article class="rd-card rd-post">
            <div class="rd-post__img" style="background-image:<?= $lpImg !== '' ? "url('" . e(asset($lpImg)) . "'), " : '' ?>url('<?= e(post_placeholder_image($lp['category'] ?? '', $lpUrg)) ?>');" role="presentation"></div>
            <div class="rd-post__body">
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <?php if ($lpUrg === 'urgent'): ?><span class="rd-badge rd-badge--urgent"><i class="bi bi-exclamation-triangle-fill"></i> Urgent</span><?php endif; ?>
                    <?php if ($lpUrg === 'important'): ?><span class="rd-badge rd-badge--important"><?= e(t('landing.important')) ?></span><?php endif; ?>
                    <time datetime="<?= e((string) $lp['published_at']) ?>"><?= e($shortWhen($lp['published_at'] ?? null)) ?></time>
                </div>
                <h3 class="text-base font-bold leading-snug text-slate-900"><a href="<?= e($lpUrl) ?>" class="hover:underline"><?= e($lpTitle) ?></a></h3>
                <p class="text-sm text-slate-600"><?= e(mb_strimwidth($lpBody, 0, 120, '…')) ?></p>
                <div class="rd-tags" aria-label="Languages"><span>EN</span><span>FIL</span><span>MN</span></div>
                <a href="<?= e($lpUrl) ?>" class="mt-auto text-sm font-bold text-blue-700" aria-label="<?= e(t('resident_home.read_more') . ': ' . $lpTitle) ?>"><?= e(t('resident_home.read_more')) ?> <i class="bi bi-arrow-right"></i></a>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ══ C2. SAFETY CHECK-IN ════════════════════════════════════════════════
     Renders only while an advisory is actually asking (7 days — see
     SafetyCheckin::ASKS_FOR_DAYS). Most days this is nothing at all.

     It posts to the same POST /safety-checkin/{id} the announcement page
     uses: same route, same csrf_token, same status/note fields. Nothing new
     was added to the form contract, so a resident can answer from either
     place and the second answer simply updates the first.

     Placed above the personal strip and below the urgent banners: during a
     storm this is the most actionable thing on the page, but it is still
     less urgent than the notice telling them to evacuate. -->
<?php
/*
 * QUIET STATE — no advisory is asking.
 *
 * The card used to render nothing here, which hid the feature entirely
 * until a storm. So on an ordinary day it answers the question a resident
 * actually has — where would I go — and shows what will appear when the
 * barangay does ask. Meeting the button for the first time during a typhoon
 * is the one thing this must not require.
 */
if (empty($safetyAdvisory)):
?>
<section class="fade-up mb-6" aria-labelledby="safety-quiet-title">
    <div class="safety-card safety-card--quiet">
        <div class="safety-card__head">
            <span class="safety-card__icon" aria-hidden="true">
                <i class="bi bi-shield-check"></i>
            </span>
            <div class="min-w-0">
                <p class="safety-card__eyebrow"><?= e(t('safety.card_eyebrow')) ?></p>
                <h2 class="safety-card__title" id="safety-quiet-title">
                    <?= e(t('safety.quiet_title')) ?>
                </h2>
            </div>
        </div>

        <p class="safety-card__help"><?= e(t('safety.quiet_help')) ?></p>

        <?php if (!empty($safetyCentre)): ?>
        <?php /* The answer, not a link to the answer. During a storm the
                 phone may not load a second page. */ ?>
        <div class="safety-quiet__centre">
            <p class="safety-quiet__label"><?= e(t('safety.quiet_centre_label')) ?></p>
            <p class="safety-quiet__name">
                <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                <?= e((string) $safetyCentre['name']) ?>
            </p>
            <?php if (trim((string) ($safetyCentre['address'] ?? '')) !== ''): ?>
            <p class="safety-quiet__addr"><?= e((string) $safetyCentre['address']) ?></p>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <p class="safety-quiet__none"><?= e(t('safety.quiet_no_centre')) ?></p>
        <?php endif; ?>

        <div class="safety-card__links">
            <a href="<?= e(route('evacuation')) ?>" class="safety-card__link">
                <i class="bi bi-geo-alt-fill" aria-hidden="true"></i><?= e(t('safety.where_do_i_go')) ?>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($safetyAdvisory)):
    $__sa     = $safetyAdvisory;
    $__sState = $__sa['my_status'];          // 'safe' | 'needs_help' | null
    $__sAsked = $__sState === null;
?>
<section class="fade-up mb-6" aria-labelledby="safety-card-title">
    <div class="safety-card<?= $__sAsked ? '' : ' safety-card--answered' ?>">

        <div class="safety-card__head">
            <span class="safety-card__icon" aria-hidden="true">
                <i class="bi <?= $__sAsked ? 'bi-shield-exclamation' : 'bi-shield-check' ?>"></i>
            </span>
            <div class="min-w-0">
                <p class="safety-card__eyebrow"><?= e(t('safety.card_eyebrow')) ?></p>
                <h2 class="safety-card__title" id="safety-card-title">
                    <?= e($__sAsked ? t('safety.prompt_title') : t('safety.already_' . $__sState, [
                        'when' => format_datetime((string) $__sa['my_checked_in_at']),
                    ])) ?>
                </h2>
            </div>
        </div>

        <?php if ($__sAsked): ?>
        <p class="safety-card__help"><?= e(t('safety.prompt_help')) ?></p>
        <?php elseif (($__sa['my_note'] ?? '') !== ''): ?>
        <p class="safety-card__help">
            <?= e(t('safety.note_saved', ['note' => (string) $__sa['my_note']])) ?>
        </p>
        <?php endif; ?>

        <?php /* Unanswered: the two buttons are the whole card. Answered: the
                 form collapses behind one link, so the card stops shouting but
                 a correction is still one tap away — someone who said they were
                 safe and then was not must never have to hunt for this. */ ?>
        <div x-data="{ open: <?= $__sAsked ? 'true' : 'false' ?> }">

            <?php if (!$__sAsked): ?>
            <button type="button" class="safety-card__change" @click="open = !open"
                    :aria-expanded="open ? 'true' : 'false'">
                <i class="bi bi-pencil" aria-hidden="true"></i>
                <?= e(t('safety.change_answer')) ?>
            </button>
            <?php endif; ?>

            <form method="post"
                  action="<?= e(route('safety-checkin/' . (int) $__sa['id'])) ?>"
                  class="safety-card__form"
                  x-show="open"
                  <?= $__sAsked ? '' : 'x-cloak' ?>>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <label class="sr-only" for="safety-note"><?= e(t('safety.note_ph')) ?></label>
                <input type="text" id="safety-note" name="note" maxlength="255"
                       value="<?= e((string) ($__sa['my_note'] ?? '')) ?>"
                       placeholder="<?= e(t('safety.note_ph')) ?>"
                       class="safety-card__note">

                <div class="safety-card__actions">
                    <button type="submit" name="status" value="safe" class="safety-btn safety-btn--safe">
                        <i class="bi bi-check-lg" aria-hidden="true"></i><?= e(t('safety.btn_safe')) ?>
                    </button>
                    <button type="submit" name="status" value="needs_help" class="safety-btn safety-btn--help">
                        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i><?= e(t('safety.btn_help')) ?>
                    </button>
                </div>
            </form>
        </div>

        <?php
        /*
         * THE PUROK PULSE.
         *
         * Counts only — see SafetyCheckin::purokPulse(). A resident learns how
         * many neighbours are still unaccounted for, which is enough to make
         * someone knock on a door. Who those neighbours are stays with the
         * barangay, on /admin/safety, where somebody is about to walk there.
         */
        if (!empty($safetyPulse)):
            $__p = $safetyPulse;
        ?>
        <div class="safety-pulse">
            <p class="safety-pulse__title">
                <i class="bi bi-people-fill" aria-hidden="true"></i>
                <?= e(t('safety.pulse_title')) ?>
            </p>

            <?php if ($__p['answered'] === 0): ?>
            <p class="safety-pulse__line"><?= e(t('safety.pulse_none', ['purok' => $__p['purok']])) ?></p>
            <?php elseif ($__p['silent'] === 0): ?>
            <p class="safety-pulse__line"><?= e(t('safety.pulse_all_in', ['purok' => $__p['purok']])) ?></p>
            <?php else: ?>
            <p class="safety-pulse__line">
                <?= e(t('safety.pulse_counts', [
                    'answered'  => (string) $__p['answered'],
                    'residents' => (string) $__p['residents'],
                    'purok'     => $__p['purok'],
                ])) ?>
            </p>
            <?php endif; ?>

            <?php
            // A bar, not a chart: it has to be readable at a glance on a phone
            // in bad light. aria-hidden because the sentence above already
            // says the same thing in words.
            $__pct = $__p['residents'] > 0
                ? (int) round(($__p['answered'] / $__p['residents']) * 100)
                : 0;
            ?>
            <div class="safety-pulse__bar" aria-hidden="true">
                <span class="safety-pulse__fill" style="width:<?= $__pct ?>%"></span>
            </div>

            <div class="safety-pulse__chips">
                <?php if ($__p['silent'] > 0): ?>
                <span class="safety-chip safety-chip--silent">
                    <?= e(t('safety.pulse_silent', ['count' => (string) $__p['silent']])) ?>
                </span>
                <?php endif; ?>
                <?php if ($__p['needs_help'] > 0): ?>
                <span class="safety-chip safety-chip--help">
                    <?= e(t('safety.pulse_help', ['count' => (string) $__p['needs_help']])) ?>
                </span>
                <?php endif; ?>
            </div>

            <?php if ($__p['silent'] > 0): ?>
            <p class="safety-pulse__nudge"><?= e(t('safety.pulse_knock')) ?></p>
            <?php endif; ?>

            <p class="safety-pulse__private">
                <i class="bi bi-lock-fill" aria-hidden="true"></i>
                <?= e(t('safety.pulse_private')) ?>
            </p>
        </div>
        <?php elseif (trim((string) ($user['zone'] ?? '')) === ''): ?>
        <?php /* No purok recorded, so there is no "your neighbours" to show.
                 Say why and offer the fix rather than rendering an empty box. */ ?>
        <div class="safety-pulse">
            <p class="safety-pulse__line"><?= e(t('safety.pulse_no_purok')) ?></p>
            <a href="<?= e(route('profile')) ?>" class="safety-pulse__link">
                <i class="bi bi-geo-alt-fill" aria-hidden="true"></i><?= e(t('safety.pulse_set')) ?>
            </a>
        </div>
        <?php endif; ?>

        <div class="safety-card__links">
            <a href="<?= e(route('evacuation')) ?>" class="safety-card__link">
                <i class="bi bi-geo-alt-fill" aria-hidden="true"></i><?= e(t('safety.where_do_i_go')) ?>
            </a>
            <a href="<?= e(route('announcements/' . $__sa['slug'])) ?>" class="safety-card__link">
                <i class="bi bi-file-text" aria-hidden="true"></i><?= e(t('safety.card_read')) ?>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ══ B. NEEDS YOUR ATTENTION ════════════════════════════════════════════
     This replaced three count tiles (unread / messages / new-since-last-visit).
     Those tiles restated numbers that the panels further down this same page
     already show in full, and two of them read "0" most of the time — a row of
     zeros is noise, not a dashboard.

     Instead: one block listing only the things that are actually outstanding,
     each a link straight to where it is dealt with. When nothing is
     outstanding it collapses to a single calm line, and a brand-new resident
     gets a getting-started panel in the same slot rather than meeting three
     separate empty states. -->
<?php
// Build the list first so the "is there anything?" question is answered once.
$attention = [];

if (!$emailVerified) {
    $attention[] = [
        'icon'  => 'bi-envelope-exclamation-fill',
        'tone'  => 'amber',
        'text'  => t('resident_home.attention_email'),
        'href'  => route('profile'),
    ];
}
if ($unreadFeedbackCount > 0) {
    $attention[] = [
        'icon' => 'bi-chat-heart-fill',
        'tone' => 'green',
        'text' => $unreadFeedbackCount === 1
            ? t('resident_home.attention_reply_one')
            : t('resident_home.attention_replies', ['n' => $unreadFeedbackCount]),
        'href' => route('feedback'),
    ];
}
if (!empty($todayEvents)) {
    $n = count($todayEvents);
    $attention[] = [
        'icon' => 'bi-calendar-check-fill',
        'tone' => 'red',
        'text' => $n === 1
            ? t('resident_home.attention_today', ['n' => $n])
            : t('resident_home.attention_today_many', ['n' => $n]),
        'href' => route('events'),
    ];
}
if ($unreadNotifications > 0) {
    $attention[] = [
        'icon' => 'bi-bell-fill',
        'tone' => 'blue',
        'text' => $unreadNotifications === 1
            ? t('resident_home.attention_unread_one')
            : t('resident_home.attention_unread', ['n' => $unreadNotifications]),
        'href' => route('notifications'),
    ];
}
if ($newAnnouncements > 0) {
    $attention[] = [
        'icon' => 'bi-stars',
        'tone' => 'amber',
        'text' => $newAnnouncements === 1
            ? t('resident_home.attention_new_ann', ['n' => $newAnnouncements])
            : t('resident_home.attention_new_ann_many', ['n' => $newAnnouncements]),
        'href' => route('announcements'),
    ];
}

$attentionTones = [
    'amber' => ['bg-amber-100', 'text-amber-700'],
    'green' => ['bg-green-100', 'text-green-700'],
    'red'   => ['bg-red-100',   'text-red-700'],
    'blue'  => ['bg-blue-100',  'text-blue-700'],
];

// A genuinely quiet dashboard: nothing outstanding anywhere, and the account
// is new enough that the emptiness means "not started yet" rather than
// "nothing happening today".
$isFirstVisit = empty($attention)
    && empty($urgentAnnouncements)
    && empty($recentNotifications)
    && empty($feedbackThreads)
    && empty($todayEvents)
    && empty($weekEvents)
    && $accountAgeDays < 3;
?>

<?php if ($isFirstVisit): ?>
<!-- First-visit getting started — one panel instead of three empty states. -->
<section class="fade-up mb-8">
    <div class="rounded-2xl border border-blue-200 bg-blue-50 p-6">
        <h2 class="text-lg font-black text-slate-900">
            <?= e(t('resident_home.welcome_title', ['name' => $firstName])) ?> 👋
        </h2>
        <p class="mt-1.5 text-sm leading-relaxed text-slate-600"><?= e(t('resident_home.welcome_body')) ?></p>
        <div class="mt-4 grid gap-2 sm:grid-cols-3">
            <?php
            $welcomeSteps = [
                [route('announcements'), 'bi-megaphone-fill', t('resident_home.welcome_step_ann')],
                [route('ordinances'),    'bi-journal-text',   t('resident_home.welcome_step_ord')],
                [route('feedback'),      'bi-chat-dots-fill', t('resident_home.welcome_step_msg')],
            ];
            foreach ($welcomeSteps as [$href, $icon, $label]):
            ?>
            <a href="<?= e($href) ?>"
               class="flex min-h-[64px] items-center gap-3 rounded-xl border border-blue-200 bg-white p-3 transition hover:shadow-md">
                <i class="bi <?= $icon ?> text-lg text-blue-700"></i>
                <span class="text-xs font-semibold leading-snug text-slate-700"><?= e($label) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php elseif (!empty($attention)): ?>
<section class="fade-up mb-8">
    <h2 class="mb-3 text-sm font-bold uppercase tracking-widest text-slate-500">
        <?= e(t('resident_home.attention_title')) ?>
    </h2>
    <div class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <?php foreach ($attention as $item):
            [$toneBg, $toneInk] = $attentionTones[$item['tone']] ?? $attentionTones['blue'];
        ?>
        <a href="<?= e($item['href']) ?>"
           class="flex min-h-[60px] items-center gap-3 px-4 py-3 transition hover:bg-slate-50">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl <?= $toneBg ?> <?= $toneInk ?>">
                <i class="bi <?= $item['icon'] ?>"></i>
            </span>
            <span class="min-w-0 flex-1 text-sm font-semibold text-slate-700"><?= e($item['text']) ?></span>
            <i class="bi bi-chevron-right flex-shrink-0 text-xs text-slate-500" aria-hidden="true"></i>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<?php else: ?>
<section class="fade-up mb-8">
    <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-green-100 text-green-700">
            <i class="bi bi-check2-circle"></i>
        </span>
        <div class="min-w-0">
            <p class="text-sm font-bold text-slate-900"><?= e(t('resident_home.attention_caught_up')) ?></p>
            <p class="text-xs text-slate-500"><?= e(t('resident_home.attention_caught_up_hint')) ?></p>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ══ F. HAPPENING TODAY / THIS WEEK ═════════════════════════════════════
     An event today and an event in March must not look identical. -->
<?php if (!empty($todayEvents) || !empty($weekEvents)): ?>
<section class="fade-up mb-8">
    <?php
    $strips = [
        ['items' => $todayEvents, 'title' => t('resident_home.today_title'), 'accent' => 'red',  'badge' => t('resident_home.today_badge')],
        ['items' => $weekEvents,  'title' => t('resident_home.week_title'),  'accent' => 'blue', 'badge' => ''],
    ];
    foreach ($strips as $strip):
        if (empty($strip['items'])) { continue; }
        $isToday = $strip['accent'] === 'red';
    ?>
    <div class="mb-4 last:mb-0">
        <h2 class="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-widest <?= $isToday ? 'text-red-600' : 'text-blue-700' ?>">
            <i class="bi <?= $isToday ? 'bi-calendar-check-fill' : 'bi-calendar-week' ?>"></i>
            <?= e(localised_text($strip, 'title')) ?>
        </h2>
        <div class="grid gap-3 sm:grid-cols-2">
            <?php foreach ($strip['items'] as $ev): $m = $eventMeta($ev); ?>
            <?php // Plain bg-red-50 / bg-white (no opacity modifier, never both):
                  // Tailwind's `bg-red-50/40` compiles to its own class that the
                  // dark-mode remap cannot reach, and pairing it with bg-white made
                  // which colour won depend on stylesheet order. ?>
            <article class="flex items-start gap-3 rounded-2xl border p-4 shadow-sm transition hover:shadow-md <?= $isToday ? 'border-red-200 bg-red-50' : 'border-slate-200 bg-white' ?>">
                <div class="inline-flex min-w-[3.25rem] flex-col items-center justify-center rounded-xl px-2 py-1.5 text-center text-white <?= $isToday ? 'bg-red-600' : 'bg-blue-700' ?>">
                    <span class="text-[10px] font-bold uppercase leading-none"><?= date('M', $m['start']) ?></span>
                    <span class="text-xl font-black leading-tight"><?= date('j', $m['start']) ?></span>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="mb-1 flex flex-wrap items-center gap-1.5">
                        <?php if ($m['isNow']): ?>
                        <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-bold text-green-700">
                            <span class="live-dot" aria-hidden="true"></span> <?= e(t('resident_home.happening_now')) ?>
                        </span>
                        <?php elseif ($isToday): ?>
                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-bold text-red-700"><?= e($strip['badge']) ?></span>
                        <?php endif; ?>
                        <?php // slate-600, not 500: the today card sits on a red-50 tint
                              // where slate-500 only reaches 4.35:1. ?>
                        <span class="text-[11px] font-semibold text-slate-600"><?= date('g:i A', $m['start']) ?></span>
                        <?php if ($m['manobo']): ?>
                        <span class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2 py-0.5 text-[11px] font-semibold text-purple-700"
                              title="<?= e(t('resident_home.manobo_available')) ?>">
                            <i class="bi bi-translate"></i> MB
                        </span>
                        <?php endif; ?>
                    </div>
                    <h3 class="line-clamp-2 text-sm font-bold leading-snug text-slate-900">
                        <a href="<?= e(route('events/' . $ev['slug'])) ?>" class="hover:text-blue-700"><?= e(localised_text($ev, 'title')) ?></a>
                    </h3>
                    <?php if (!empty($ev['venue'])): ?>
                    <p class="mt-1 flex items-center gap-1 text-xs text-slate-600">
                        <i class="bi bi-geo-alt flex-shrink-0"></i><span class="truncate"><?= e($ev['venue']) ?></span>
                    </p>
                    <?php endif; ?>
                    <a href="<?= e(route('events/' . $ev['slug'] . '/calendar')) ?>"
                       class="mt-2 inline-flex min-h-[36px] items-center gap-1.5 text-xs font-bold text-blue-700 hover:text-blue-900 hover:underline">
                        <i class="bi bi-calendar-plus"></i> <?= e(t('resident_home.add_to_calendar')) ?>
                    </a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>


<!-- ══ ORDINANCE, EXPLAINED ═══════════════════════════════════════════════
     One already-summarised ordinance, as a teaser into the policy library.
     Reads a CACHED summary — this never triggers an AI call, so it costs the
     dashboard nothing. Omitted entirely when nothing has been summarised yet,
     rather than showing an empty promise. -->
<?php if ($featuredOrdinance !== null):
    $ordSummary = trim(strip_tags((string) ($featuredOrdinance['ai_summary'] ?? '')));
?>
<section class="fade-up mb-8">
    <a href="<?= e(route('ordinances/' . (int) $featuredOrdinance['id'])) ?>"
       class="block rounded-2xl border border-amber-200 bg-amber-50 p-5 transition hover:shadow-md">
        <div class="flex items-start gap-3">
            <span class="inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl text-white"
                  style="background:var(--brand-gradient-gold);">
                <i class="bi bi-stars"></i>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-black uppercase tracking-widest text-amber-700">
                    <?= e(t('resident_home.featured_ordinance_eyebrow')) ?>
                </p>
                <h2 class="mt-1 line-clamp-2 text-base font-bold leading-snug text-slate-900">
                    <?php if (!empty($featuredOrdinance['ordinance_no'])): ?>
                    <span class="text-amber-700"><?= e($featuredOrdinance['ordinance_no']) ?></span> &middot;
                    <?php endif; ?>
                    <?= e(localised_text($featuredOrdinance, 'title')) ?>
                </h2>
                <?php if ($ordSummary !== ''): ?>
                <p class="mt-1.5 line-clamp-2 text-sm leading-relaxed text-slate-700">
                    <?= e(mb_strimwidth($ordSummary, 0, 200, '…', 'UTF-8')) ?>
                </p>
                <?php endif; ?>
                <p class="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-amber-700">
                    <?= e(t('resident_home.featured_ordinance_cta')) ?> <i class="bi bi-arrow-right"></i>
                </p>
                <p class="mt-1 text-[11px] text-slate-500">
                    <i class="bi bi-info-circle"></i> <?= e(t('resident_home.featured_ordinance_note')) ?>
                </p>
            </div>
        </div>
    </a>
</section>
<?php endif; ?>

<!-- ══ F2. DOCUMENT REQUESTS ══════════════════════════════════════════════
     The barangay counter, on the dashboard.

     Two states, and the difference matters. A resident with a document WAITING
     TO BE COLLECTED needs to be told loudly — that is an errand they can run
     today. Everyone else gets a quiet invitation, because most days this is
     not what they came to the portal for.

     The status is shown here rather than only on /documents, because "is it
     ready yet" is the single question this whole feature exists to answer, and
     making someone navigate to read one word gives back part of the trip the
     feature just saved them. -->
<?php
$docOpen  = $openDocRequest ?? null;
$docReady = (int) ($readyDocCount ?? 0);
$docState = (string) ($docOpen['status'] ?? '');
?>
<section class="fade-up mb-8">
    <?php if ($docReady > 0): ?>
    <!-- Ready to collect — the only state worth shouting about. -->
    <a href="<?= e(route('documents')) ?>"
       class="flex items-center gap-4 rounded-2xl border border-green-200 bg-green-50 p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
        <span class="inline-flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl text-white"
              style="background:linear-gradient(135deg,var(--status-success),var(--brand-secondary));">
            <i class="bi bi-file-earmark-check-fill" style="font-size:1.35rem;"></i>
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-bold text-green-900">
                <?= e(t('resident_home.doc_ready_title', ['n' => (string) $docReady])) ?>
            </p>
            <p class="mt-0.5 text-xs leading-relaxed text-green-800">
                <?php if (($docOpen['delivery_method'] ?? 'pickup') === 'digital'): ?>
                <i class="bi bi-file-earmark-arrow-down me-1"></i> <?= e(t('documents.status_ready_digital')) ?> &bull; I-download at i-print mula sa portal.
                <?php else: ?>
                <?= e(t('resident_home.doc_ready_help')) ?>
                <?php endif; ?>
            </p>
        </div>
        <i class="bi bi-chevron-right flex-shrink-0 text-green-700"></i>
    </a>

    <?php elseif ($docOpen !== null): ?>
    <!-- In the queue — reassurance, not a call to action. -->
    <a href="<?= e(route('documents')) ?>"
       class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
        <span class="inline-flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl text-white"
              style="background:var(--brand-gradient-gold);">
            <i class="bi bi-hourglass-split" style="font-size:1.35rem;"></i>
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-bold text-slate-900">
                <?= e(\App\Models\DocumentRequest::label((string) $docOpen['document_type'])) ?>
                — <?= e(t('documents.status_' . $docState)) ?>
            </p>
            <p class="mt-0.5 font-mono text-xs text-slate-400"><?= e((string) $docOpen['reference_no']) ?></p>
        </div>
        <i class="bi bi-chevron-right flex-shrink-0 text-slate-400"></i>
    </a>

    <?php else: ?>
    <!-- Nothing pending. An invitation, and a way to ask a person. -->
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-start gap-4">
            <span class="inline-flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl text-white"
                  style="background:var(--brand-gradient);">
                <i class="bi bi-file-earmark-text-fill" style="font-size:1.35rem;"></i>
            </span>
            <div class="min-w-0 flex-1">
                <h2 class="text-sm font-bold text-slate-900"><?= e(t('resident_home.doc_cta_title')) ?></h2>
                <p class="mt-1 text-xs leading-relaxed text-slate-600"><?= e(t('resident_home.doc_cta_help')) ?></p>

                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="<?= e(route('documents')) ?>"
                       class="inline-flex items-center gap-1 rounded-lg bg-blue-700 px-3 py-2 text-xs font-bold text-white hover:bg-blue-800">
                        <i class="bi bi-send"></i> <?= e(t('resident_home.doc_cta_button')) ?>
                    </a>
                    <?php /* The existing two-way feedback thread — the way to
                             reach a person when the form does not cover it. */ ?>
                    <a href="<?= e(route('feedback')) ?>"
                       class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                        <i class="bi bi-chat-dots"></i> <?= e(t('resident_home.doc_ask_staff')) ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</section>

<!-- ══ G. QUICK ACTIONS ═══════════════════════════════════════════════════
     Thumb-sized targets — most residents here are on a mid-range phone. -->
<section class="fade-up mb-8">
    <h2 class="mb-3 text-sm font-bold uppercase tracking-widest text-slate-500">
        <?= e(t('resident_home.actions_title')) ?>
    </h2>
    <?php
    // min-h-[104px] keeps every target comfortably above the 44px touch
    // minimum even on a 390px-wide screen, where these sit two-up.
    $tileClass = 'flex min-h-[104px] w-full flex-col items-center justify-center gap-2 rounded-2xl '
               . 'border border-slate-200 bg-white p-4 text-center shadow-sm transition '
               . 'hover:-translate-y-0.5 hover:shadow-md';
    $quickActions = [
        // Documents first: it is the errand that otherwise costs a trip to the
        // hall, which is the most expensive thing on this list for a resident
        // in a far purok.
        // CHANGED: every gradient pair below was a stock Tailwind blue/green/
        // red/amber/purple hex pair — retinted to the earthy theme palette.
        ['href' => route('documents'),  'icon' => 'bi-file-earmark-text-fill', 'label' => t('resident_home.action_documents'), 'grad' => '#17603a'],
        ['href' => route('feedback'),   'icon' => 'bi-chat-heart-fill', 'label' => t('resident_home.action_feedback'),   'grad' => '#2f5d3a'],
        ['href' => route('evacuation'), 'icon' => 'bi-house-exclamation-fill', 'label' => t('resident_home.action_evacuation'), 'grad' => '#b42318'],
        ['href' => route('ordinances'), 'icon' => 'bi-journal-text',    'label' => t('resident_home.action_ordinances'), 'grad' => '#7a5c11'],
        ['href' => route('manobo'),     'icon' => 'bi-book-half',       'label' => t('nav.dictionary'),                  'grad' => '#1f6f8b'],
        ['ai'   => true,                'icon' => 'bi-robot',           'label' => t('resident_home.action_ai'),         'grad' => '#4b5563'],
        ['href' => route('profile'),    'icon' => 'bi-person-fill',     'label' => t('resident_home.action_profile'),    'grad' => '#17603a'],
    ];
    ?>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <?php foreach ($quickActions as $qa): ?>
            <?php if (!empty($qa['ai'])): ?>
            <button type="button" data-open-ai-chat class="<?= $tileClass ?>">
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-white"
                      style="background:<?= $qa['grad'] ?>;">
                    <i class="bi <?= $qa['icon'] ?>" style="font-size:1.2rem;"></i>
                </span>
                <span class="text-xs font-bold leading-snug text-slate-700"><?= e($qa['label']) ?></span>
            </button>
            <?php else: ?>
            <a href="<?= e($qa['href']) ?>" class="<?= $tileClass ?>">
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-white"
                      style="background:<?= $qa['grad'] ?>;">
                    <i class="bi <?= $qa['icon'] ?>" style="font-size:1.2rem;"></i>
                </span>
                <span class="text-xs font-bold leading-snug text-slate-700"><?= e($qa['label']) ?></span>
            </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <p class="mt-2 text-center text-xs text-slate-500 lg:text-right">
        <i class="bi bi-robot me-1"></i><?= e(t('resident_home.action_ai_hint')) ?>
    </p>
</section>
<script>
    /* The AI tile drives the existing floating chat widget rather than
       duplicating it — the widget owns all the chat state. */
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest && e.target.closest('[data-open-ai-chat]');
        if (!trigger) { return; }
        var fab = document.querySelector('.aic-fab');
        if (!fab) { return; }
        if (fab.getAttribute('aria-expanded') !== 'true') { fab.click(); }
    });
</script>

<!-- ══ D + E. NOTIFICATIONS & MY CONVERSATIONS ════════════════════════════ -->
<section class="mb-10 grid gap-4 lg:grid-cols-2">

    <!-- D. Unread notifications -->
    <div class="fade-up flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
         id="notif-panel">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="flex items-center gap-2 text-base font-bold text-slate-900">
                <i class="bi bi-bell-fill text-blue-700"></i> <?= e(t('resident_home.notifs_title')) ?>
                <?php if ($unreadNotifications > 0): ?>
                <span class="rounded-full bg-red-600 px-2 py-0.5 text-[11px] font-black text-white"
                      data-notif-badge><?= $unreadNotifications ?></span>
                <?php endif; ?>
            </h2>

            <?php if ($unreadNotifications > 0): ?>
            <?php // A real <form>, so this works with JavaScript off — the
                  // controller content-negotiates and redirects a browser post
                  // back here. The script below upgrades it to a fetch() that
                  // clears the panel in place. ?>
            <form method="post" action="<?= e(route('notifications/mark-read')) ?>" data-mark-all-read>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit"
                        class="inline-flex min-h-[32px] flex-shrink-0 items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-blue-700 transition hover:bg-blue-50">
                    <i class="bi bi-check2-all"></i> <?= e(t('resident_home.notifs_mark_all')) ?>
                </button>
            </form>
            <?php endif; ?>
        </div>

        <?php if (empty($recentNotifications)): ?>
        <div class="flex flex-1 flex-col items-center justify-center py-8 text-center">
            <i class="bi bi-check2-circle text-3xl text-green-300"></i>
            <p class="mt-2 text-sm text-slate-500"><?= e(t('resident_home.notifs_empty')) ?></p>
        </div>
        <?php else: ?>
        <?php // Rows link to the notifications list rather than deep-linking to
              // the related post: resolving each one needs a per-row lookup, and
              // this page is deliberately free of N+1 queries. The list page
              // already resolves and links them properly. ?>
        <ul class="divide-y divide-slate-100" data-notif-list>
            <?php foreach ($recentNotifications as $n): ?>
            <li class="py-3 first:pt-0">
                <a href="<?= e(route('notifications')) ?>"
                   data-notif-read="<?= (int) $n['id'] ?>"
                   class="-mx-2 flex items-start gap-2.5 rounded-xl px-2 py-1.5 transition hover:bg-slate-50">
                    <span class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full bg-blue-500" aria-hidden="true"></span>
                    <div class="min-w-0 flex-1">
                        <p class="line-clamp-1 text-sm font-semibold text-slate-900"><?= e((string) $n['title']) ?></p>
                        <p class="mt-0.5 line-clamp-2 text-xs leading-relaxed text-slate-500"><?= e((string) $n['message']) ?></p>
                        <p class="mt-1 text-[11px] text-slate-500"><?= e($shortWhen($n['created_at'] ?? null)) ?></p>
                    </div>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <p class="mt-3 hidden text-sm text-slate-500" data-notif-cleared>
            <i class="bi bi-check2-circle text-green-600"></i> <?= e(t('resident_home.notifs_empty')) ?>
        </p>
        <?php endif; ?>

        <a href="<?= e(route('notifications')) ?>"
           class="mt-4 inline-flex min-h-[44px] items-center justify-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-blue-700 transition hover:bg-blue-50">
            <?= e(t('resident_home.notifs_view_all')) ?> <i class="bi bi-arrow-right"></i>
        </a>
    </div>

    <!-- E. My feedback conversations -->
    <div class="fade-up fade-up-delay-1 flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="flex items-center gap-2 text-base font-bold text-slate-900">
                <i class="bi bi-chat-dots-fill text-green-600"></i> <?= e(t('resident_home.feedback_title')) ?>
            </h2>
        </div>

        <?php if (empty($feedbackThreads)): ?>
        <div class="flex flex-1 flex-col items-center justify-center py-8 text-center">
            <i class="bi bi-chat-square-text text-3xl text-slate-300"></i>
            <p class="mt-2 text-sm text-slate-500"><?= e(t('resident_home.feedback_empty')) ?></p>
            <a href="<?= e(route('feedback')) ?>"
               class="mt-3 inline-flex min-h-[44px] items-center gap-2 rounded-xl bg-green-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-green-700">
                <i class="bi bi-plus-lg"></i> <?= e(t('resident_home.feedback_start')) ?>
            </a>
        </div>
        <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($feedbackThreads as $fb):
                $unread   = (int) ($fb['unread_reply_count'] ?? 0);
                $fromMe   = ($fb['last_sender_role'] ?? 'resident') === 'resident';
                // A staff member's designation is more meaningful to a resident
                // than the generic role word — same rule as the thread view.
                $who      = $fromMe
                    ? t('resident_home.feedback_you')
                    : (trim((string) ($fb['last_sender_designation'] ?? '')) !== ''
                        ? (string) $fb['last_sender_designation']
                        : (string) ($fb['last_sender_name'] ?? ''));
            ?>
            <li class="py-3 first:pt-0">
                <a href="<?= e(route('feedback')) ?>#thread-<?= (int) $fb['id'] ?>"
                   class="-mx-2 block rounded-xl px-2 py-1.5 transition hover:bg-slate-50">
                    <div class="flex items-start justify-between gap-2">
                        <p class="truncate text-xs font-bold <?= $unread > 0 ? 'text-green-700' : 'text-slate-500' ?>">
                            <?= e($who) ?>
                        </p>
                        <?php if ($unread > 0): ?>
                        <?php // green-700, not green-600: white on green-600 is only
                              // 3.3:1, too low for a 10px label in either theme. ?>
                        <span class="flex-shrink-0 rounded-full bg-green-700 px-2 py-0.5 text-[10px] font-black text-white">
                            <?= e(t('resident_home.feedback_new')) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <p class="mt-0.5 line-clamp-2 text-sm leading-relaxed <?= $unread > 0 ? 'font-semibold text-slate-900' : 'text-slate-600' ?>">
                        <?= e((string) ($fb['last_message'] ?? '')) ?>
                    </p>
                    <p class="mt-1 text-[11px] text-slate-500"><?= e($shortWhen($fb['last_message_at'] ?? null)) ?></p>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>

        <a href="<?= e(route('feedback')) ?>"
           class="mt-4 inline-flex min-h-[44px] items-center justify-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-green-700 transition hover:bg-green-50">
            <?= e(t('resident_home.feedback_view_all')) ?> <i class="bi bi-arrow-right"></i>
        </a>
        <?php endif; ?>
    </div>

</section>

<script>
    /* Progressive enhancement for the notifications panel. Everything here is
       an upgrade on markup that already works without it: the "mark all read"
       control is a real form, and each row is a real link. If this script does
       not run, both still do the right thing — just with a page load. */
    (function () {
        var base = (window.BarangGabay && window.BarangGabay.baseUrl ? window.BarangGabay.baseUrl : '').replace(/\/$/, '');
        var csrf = (window.BarangGabay && window.BarangGabay.csrfToken) || '';

        function post(url) {
            return fetch(base + url, {
                method:  'POST',
                headers: {
                    'Content-Type':     'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'csrf_token=' + encodeURIComponent(csrf)
            });
        }

        // Mark all read, in place.
        var form = document.querySelector('[data-mark-all-read]');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                post('/api/notifications/mark-read').then(function () {
                    var list = document.querySelector('[data-notif-list]');
                    if (list) { list.remove(); }
                    var done = document.querySelector('[data-notif-cleared]');
                    if (done) { done.classList.remove('hidden'); }
                    document.querySelectorAll('[data-notif-badge]').forEach(function (b) { b.remove(); });
                    form.remove();
                    var bell = document.getElementById('notif-badge');
                    if (bell) { bell.style.display = 'none'; }
                }).catch(function () {
                    form.submit();   // network trouble → fall back to the real post
                });
            });
        }

        // Mark one read, then follow the link. The navigation is deferred only
        // briefly: a notification that fails to clear is a far smaller problem
        // than a link that feels broken, so we never block on the request.
        document.querySelectorAll('[data-notif-read]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                var id = link.getAttribute('data-notif-read');
                if (!id || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) { return; }
                e.preventDefault();
                var go = function () { window.location.href = link.href; };
                post('/api/notifications/' + encodeURIComponent(id) + '/read').then(go, go);
                setTimeout(go, 600);
            });
        });
    })();
</script>

<!-- ══ COMMUNITY CONTEXT ══════════════════════════════════════════════════
     Barangay-wide numbers kept, but demoted: context, not the headline. -->
<section class="fade-up mb-8">
    <h2 class="mb-3 text-sm font-bold uppercase tracking-widest text-slate-500">
        <?= e(t('resident_home.community_totals')) ?>
    </h2>
    <?php
    // Announcements, events and ordinances are published barangay-wide — there
    // is no per-zone content in this system, so "filtering" them by zone would
    // show the same number under a different label, which is worse than not
    // splitting at all. The one figure that really is zone-specific is how many
    // verified neighbours share the resident's zone, so that is added as a
    // fourth, explicitly-labelled tile rather than pretending the others are
    // zone-scoped.
    $showZone = $zone !== '' && $zoneNeighbours !== null;

    // Only the zone tile carries a scope label. The section heading above
    // already says "whole barangay", so repeating it under three of the four
    // tiles would be noise — the one that differs is the one worth marking.
    $communityTotals = [
        [route('announcements'), 'bi-megaphone',      (int) ($totalAnnouncements ?? 0),  t('nav.announcements'), null],
        [route('events'),        'bi-calendar-event', (int) ($upcomingEventsCount ?? 0), t('nav.events'),        null],
        [route('ordinances'),    'bi-journal-text',   (int) ($activeOrdinances ?? 0),    t('nav.ordinances'),    null],
    ];
    if ($showZone) {
        $communityTotals[] = [
            null, 'bi-people-fill', (int) $zoneNeighbours,
            t('resident_home.totals_residents'),
            t('resident_home.totals_zone', ['zone' => $zone]),
        ];
    }
    ?>
    <div class="grid <?= $showZone ? 'grid-cols-2 sm:grid-cols-4' : 'grid-cols-3' ?> gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:gap-4">
        <?php foreach ($communityTotals as [$href, $icon, $value, $label, $scope]):
            $tileClasses = 'flex flex-col items-center gap-0.5 text-center'
                         . ($href !== null ? ' transition hover:opacity-80' : '');
        ?>
        <?php if ($href !== null): ?>
        <a href="<?= e($href) ?>" class="<?= $tileClasses ?>">
        <?php else: ?>
        <div class="<?= $tileClasses ?>">
        <?php endif; ?>
            <i class="bi <?= $icon ?> text-slate-500"></i>
            <span class="text-lg font-bold text-slate-700"><span data-countup="<?= $value ?>"><?= $value ?></span></span>
            <span class="text-[11px] leading-tight text-slate-500"><?= e($label) ?></span>
            <?php if ($scope !== null): ?>
            <span class="mt-0.5 rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-700"><?= e($scope) ?></span>
            <?php endif; ?>
        <?php if ($href !== null): ?>
        </a>
        <?php else: ?>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>

<!-- ── Latest announcements ──────────────────────────────────── -->
<section class="mb-10">
    <div class="fade-up mb-5 flex items-end justify-between gap-3">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-blue-700"><?= e(t('nav.announcements')) ?></p>
            <h2 class="mt-0.5 text-xl font-bold text-slate-900"><?= e(t('resident_home.latest_announcements_title')) ?></h2>
        </div>
        <a href="<?= e(route('announcements')) ?>"
           class="flex-shrink-0 text-sm font-semibold text-blue-700 hover:text-blue-900 hover:underline">
            <?= e(t('resident_home.view_all')) ?> &rarr;
        </a>
    </div>

    <?php if (empty($latestAnnouncements)): ?>
    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 py-12 text-center">
        <i class="bi bi-megaphone text-3xl text-slate-300"></i>
        <p class="mt-3 text-sm text-slate-500"><?= e(t('resident_home.no_announcements')) ?></p>
    </div>
    <?php else: ?>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($latestAnnouncements as $annIdx => $ann):
            $cat    = $ann['category'] ?? 'general';
            $urg    = $ann['urgency']  ?? 'normal';
            $catLbl = $categoryLabels[$cat] ?? ucfirst($cat);
            $catCls = category_badge_class($cat);
            $bdr    = urgency_border_class($urg);
            $fadeDelay = 'fade-up-delay-' . min($annIdx + 1, 4);
            $hasManobo = !empty($ann['title_manobo']) || !empty($ann['body_manobo']);
        ?>
        <article class="fade-up <?= $fadeDelay ?> flex flex-col overflow-hidden rounded-2xl border border-slate-200 border-l-4 <?= $bdr ?> bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">

            <?php if (!empty($ann['cover_image_url'])): ?>
            <div class="aspect-video w-full overflow-hidden bg-slate-100">
                <img src="<?= e(asset($ann['cover_image_url'])) ?>" onerror="this.remove()" loading="lazy"
                     alt="<?= e(localised_text($ann, 'title')) ?>"
                     class="h-full w-full object-cover">
            </div>
            <?php endif; ?>

            <div class="flex flex-1 flex-col p-5">
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $catCls ?>">
                        <?= e($catLbl) ?>
                    </span>
                    <?php if ($urg === 'urgent'): ?>
                    <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">
                        <i class="bi bi-exclamation-circle"></i> <?= e(t('resident_home.urgent_label')) ?>
                    </span>
                    <?php elseif ($urg === 'important'): ?>
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                        <i class="bi bi-info-circle"></i> <?= e(t('resident_home.important_badge')) ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($hasManobo): ?>
                    <span class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-semibold text-purple-700"
                          title="<?= e(t('resident_home.manobo_available')) ?>">
                        <i class="bi bi-translate"></i> MB
                    </span>
                    <?php endif; ?>
                </div>

                <h3 class="line-clamp-2 text-base font-bold leading-snug text-slate-900">
                    <a href="<?= e(route('announcements/' . $ann['slug'])) ?>"
                       class="transition-colors hover:text-blue-700">
                        <?= e(localised_text($ann, 'title')) ?>
                    </a>
                </h3>
                <p class="mt-1.5 text-xs text-slate-500">
                    <?= e(date('F j, Y', strtotime($ann['published_at'] ?? 'now'))) ?>
                    &bull; <?= e($ann['author_name'] ?? '') ?>
                </p>

                <div class="mt-auto pt-4">
                    <a href="<?= e(route('announcements/' . $ann['slug'])) ?>"
                       class="inline-flex items-center gap-1 text-sm font-semibold text-blue-700 transition-colors hover:text-blue-900">
                        <?= e(t('resident_home.read_more')) ?> <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<!-- ── Later on (events beyond this week) ─────────────────────── -->
<section>
    <div class="fade-up mb-5 flex items-end justify-between gap-3">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-blue-600"><?= e(t('nav.events')) ?></p>
            <h2 class="mt-0.5 text-xl font-bold text-slate-900">
                <?= e(!empty($laterEvents) ? t('resident_home.later_title') : t('resident_home.upcoming_events_title')) ?>
            </h2>
        </div>
        <a href="<?= e(route('events')) ?>"
           class="flex-shrink-0 text-sm font-semibold text-blue-600 hover:text-blue-800 hover:underline">
            <?= e(t('resident_home.view_all')) ?> &rarr;
        </a>
    </div>

    <?php if (empty($laterEvents)): ?>
    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 py-12 text-center">
        <i class="bi bi-calendar-x text-3xl text-slate-300"></i>
        <p class="mt-3 text-sm text-slate-500"><?= e(t('resident_home.no_upcoming_events')) ?></p>
    </div>
    <?php else: ?>
    <div class="flex gap-4 overflow-x-auto pb-2 snap-x snap-mandatory lg:grid lg:grid-cols-3 lg:overflow-visible lg:pb-0">
        <?php foreach (array_slice($laterEvents, 0, 3) as $evIdx => $ev):
            $evTs     = strtotime($ev['event_date'] ?? 'now');
            $statusCls = match($ev['status'] ?? 'upcoming') {
                'ongoing'   => 'bg-green-100 text-green-700',
                'completed' => 'bg-slate-100 text-slate-500',
                'cancelled' => 'bg-red-100 text-red-600',
                default     => 'bg-blue-100 text-blue-700',
            };
            $evFadeDelay = 'fade-up-delay-' . min($evIdx + 1, 4);
            $evManobo    = !empty($ev['title_manobo']) || !empty($ev['description_manobo']);
        ?>
        <article class="fade-up <?= $evFadeDelay ?> snap-start w-72 flex-shrink-0 flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md lg:w-auto">

            <?php if (!empty($ev['cover_image_url'])): ?>
            <div class="aspect-video w-full overflow-hidden bg-slate-100">
                <img src="<?= e(asset($ev['cover_image_url'])) ?>" onerror="this.remove()" loading="lazy" alt="<?= e(localised_text($ev, 'title')) ?>"
                     class="h-full w-full object-cover">
            </div>
            <?php else: ?>
            <div class="flex aspect-video w-full items-center justify-center bg-gradient-to-br from-blue-50 to-blue-100">
                <i class="bi bi-calendar-event text-4xl text-blue-300"></i>
            </div>
            <?php endif; ?>

            <div class="flex flex-1 flex-col p-5">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <div class="inline-flex min-w-[3rem] flex-col items-center justify-center rounded-xl bg-blue-700 px-3 py-1.5 text-center text-white">
                        <span class="text-xs font-bold uppercase leading-none"><?= date('M', $evTs) ?></span>
                        <span class="text-xl font-black leading-tight"><?= date('j', $evTs) ?></span>
                    </div>
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold <?= $statusCls ?>">
                        <?= e(t('admin_events.status_' . ($ev['status'] ?? 'upcoming'))) ?>
                    </span>
                    <?php if ($evManobo): ?>
                    <span class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2 py-0.5 text-xs font-semibold text-purple-700"
                          title="<?= e(t('resident_home.manobo_available')) ?>">
                        <i class="bi bi-translate"></i> MB
                    </span>
                    <?php endif; ?>
                </div>

                <h3 class="line-clamp-2 text-base font-bold leading-snug text-slate-900">
                    <a href="<?= e(route('events/' . $ev['slug'])) ?>"
                       class="transition-colors hover:text-blue-700">
                        <?= e(localised_text($ev, 'title')) ?>
                    </a>
                </h3>

                <?php if (!empty($ev['venue'])): ?>
                <p class="mt-1.5 flex items-center gap-1 text-xs text-slate-500">
                    <i class="bi bi-geo-alt flex-shrink-0"></i>
                    <span class="truncate"><?= e($ev['venue']) ?></span>
                </p>
                <?php endif; ?>

                <div class="mt-auto flex flex-wrap items-center gap-3 pt-4">
                    <a href="<?= e(route('events/' . $ev['slug'])) ?>"
                       class="inline-flex items-center gap-1 text-sm font-semibold text-blue-700 hover:text-blue-900">
                        <?= e(t('resident_home.details_link')) ?> <i class="bi bi-arrow-right"></i>
                    </a>
                    <a href="<?= e(route('events/' . $ev['slug'] . '/calendar')) ?>"
                       class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-blue-700">
                        <i class="bi bi-calendar-plus"></i> <?= e(t('resident_home.add_to_calendar')) ?>
                    </a>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
