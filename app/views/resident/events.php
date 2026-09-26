<?php
/**
 * Resident events listing — list view + FullCalendar calendar view.
 * Variables: $events (array)
 */

$statusMeta = [
    'upcoming'  => ['cls' => 'bg-blue-100 text-blue-700',   'label' => t('res_events.status_upcoming')],
    'ongoing'   => ['cls' => 'bg-green-100 text-green-700', 'label' => t('res_events.status_ongoing')],
    'completed' => ['cls' => 'bg-slate-100 text-slate-500', 'label' => t('res_events.status_completed')],
    'cancelled' => ['cls' => 'bg-red-100 text-red-600',     'label' => t('res_events.status_cancelled')],
];

$counts = [
    'all'       => count($events),
    'upcoming'  => count(array_filter($events, fn($e) => $e['status'] === 'upcoming')),
    'ongoing'   => count(array_filter($events, fn($e) => $e['status'] === 'ongoing')),
    'completed' => count(array_filter($events, fn($e) => $e['status'] === 'completed')),
];

$tabs = [
    'all'       => t('res_events.tab_all'),
    'upcoming'  => t('res_events.tab_upcoming'),
    'ongoing'   => t('res_events.tab_ongoing'),
    'completed' => t('res_events.tab_completed'),
];

ob_start();
?>

<!-- FullCalendar v6 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">

<!-- Page header -->
<div class="mb-6">
    <p class="text-xs font-bold uppercase tracking-widest text-blue-600"><?= e(t('nav.events')) ?></p>
    <h1 class="mt-0.5 text-2xl font-bold text-slate-900 sm:text-3xl"><?= e(t('res_events.title')) ?></h1>
    <p class="mt-1 text-sm text-slate-500">
        <?= e(t('res_events.subtitle')) ?>
    </p>
</div>

<!-- View toggle + tabs (Alpine.js) -->
<div x-data="eventsPage()" x-init="init()">

    <!-- ── Top bar: view-mode toggle ───────────────────────────────────────── -->
    <div class="mb-5 flex items-center justify-between gap-3 flex-wrap">

        <!-- Status tabs (list mode only) -->
        <div class="flex flex-wrap gap-0 border-b border-slate-200" x-show="view === 'list'">
            <?php foreach ($tabs as $key => $label): ?>
            <button type="button"
                    @click="tab = '<?= $key ?>'"
                    :class="tab === '<?= $key ?>'
                        ? 'border-b-2 border-blue-600 text-blue-700 font-semibold'
                        : 'border-b-2 border-transparent text-slate-500 hover:text-slate-700'"
                    class="flex items-center gap-1.5 px-4 pb-3 pt-1.5 text-sm transition-colors focus:outline-none">
                <?= $label ?>
                <span class="inline-flex min-w-[1.35rem] items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-bold leading-none transition-colors"
                      :class="tab === '<?= $key ?>'
                        ? 'bg-blue-100 text-blue-700'
                        : 'bg-slate-100 text-slate-500'">
                    <?= $counts[$key] ?>
                </span>
            </button>
            <?php endforeach; ?>
        </div>
        <div x-show="view === 'calendar'" class="text-sm font-semibold text-slate-700 pb-3">
            <i class="bi bi-calendar3 me-1"></i> <?= e(t('res_events.calendar_view')) ?>
        </div>

        <!-- Toggle buttons -->
        <div class="flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1 self-start">
            <button @click="switchView('list')"
                    :class="view === 'list' ? 'bg-white shadow text-slate-900' : 'text-slate-500 hover:text-slate-700'"
                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition">
                <i class="bi bi-list-ul"></i> <?= e(t('res_events.view_list')) ?>
            </button>
            <button @click="switchView('calendar')"
                    :class="view === 'calendar' ? 'bg-white shadow text-slate-900' : 'text-slate-500 hover:text-slate-700'"
                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition">
                <i class="bi bi-calendar3"></i> <?= e(t('res_events.view_calendar')) ?>
            </button>
        </div>

    </div>

    <!-- ── LIST VIEW ──────────────────────────────────────────────────────── -->
    <div x-show="view === 'list'">

        <!-- Global empty state -->
        <?php if (empty($events)): ?>
        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 py-16 text-center">
            <i class="bi bi-calendar-x text-4xl text-slate-300"></i>
            <p class="mt-3 text-sm font-medium text-slate-500"><?= e(t('res_events.empty_all')) ?></p>
            <p class="mt-1 text-xs text-slate-400"><?= e(t('res_events.empty_hint')) ?></p>
        </div>

        <?php else: ?>

        <!-- Per-tab empty state -->
        <?php foreach (['upcoming', 'ongoing', 'completed'] as $emptyKey): ?>
        <?php if ($counts[$emptyKey] === 0): ?>
        <div x-show="tab === '<?= $emptyKey ?>'"
             class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 py-16 text-center">
            <i class="bi bi-calendar-x text-4xl text-slate-300"></i>
            <p class="mt-3 text-sm text-slate-400"><?= e(t('res_events.empty_tab')) ?></p>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>

        <!-- Event grid -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($events as $ev):
                $evTs   = strtotime($ev['event_date'] ?? 'now');
                $status = $ev['status'] ?? 'upcoming';
                $sCls   = $statusMeta[$status]['cls']   ?? 'bg-slate-100 text-slate-500';
                $sLbl   = $statusMeta[$status]['label'] ?? ucfirst($status);
            ?>
            <article x-show="tab === 'all' || tab === '<?= e($status) ?>'"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">

                <!-- Cover image -->
                <?php if (!empty($ev['cover_image_url'])): ?>
                <div class="relative aspect-video w-full overflow-hidden bg-slate-100">
                    <img src="<?= e(asset($ev['cover_image_url'])) ?>"
                         alt="<?= e(localised_text($ev, 'title')) ?>"
                         class="h-full w-full object-cover">
                    <span class="absolute right-3 top-3 inline-flex items-center rounded-full <?= $sCls ?> px-2.5 py-0.5 text-xs font-semibold shadow-sm backdrop-blur-sm">
                        <?= $sLbl ?>
                    </span>
                </div>
                <?php else: ?>
                <div class="flex aspect-video w-full items-center justify-center bg-gradient-to-br from-blue-50 to-blue-100">
                    <i class="bi bi-calendar-event text-5xl text-blue-200"></i>
                </div>
                <?php endif; ?>

                <div class="flex flex-1 flex-col p-5">
                    <div class="mb-3 flex items-center gap-2.5">
                        <div class="inline-flex min-w-[3rem] flex-col items-center justify-center rounded-xl bg-blue-700 px-3 py-1.5 text-white shadow-sm">
                            <span class="text-[10px] font-bold uppercase leading-none"><?= date('M', $evTs) ?></span>
                            <span class="text-xl font-black leading-tight"><?= date('j', $evTs) ?></span>
                        </div>
                        <?php if (empty($ev['cover_image_url'])): ?>
                        <span class="inline-flex items-center rounded-full <?= $sCls ?> px-2.5 py-0.5 text-xs font-semibold">
                            <?= $sLbl ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <h2 class="line-clamp-2 text-base font-bold leading-snug text-slate-900">
                        <a href="<?= e(route('events/' . $ev['slug'])) ?>"
                           class="transition-colors hover:text-blue-700">
                            <?= e(localised_text($ev, 'title')) ?>
                        </a>
                    </h2>

                    <p class="mt-1 text-xs font-medium text-slate-400">
                        <?= date('g:i A', $evTs) ?>
                        <?php if (!empty($ev['end_date'])): ?>
                        &ndash; <?= date('g:i A', strtotime($ev['end_date'])) ?>
                        <?php endif; ?>
                    </p>

                    <?php if (!empty($ev['venue'])): ?>
                    <p class="mt-1.5 flex items-center gap-1 text-xs text-slate-400">
                        <i class="bi bi-geo-alt flex-shrink-0"></i>
                        <span class="truncate"><?= e($ev['venue']) ?></span>
                    </p>
                    <?php endif; ?>

                    <?php $excerpt = mb_substr(strip_tags(localised_text($ev, 'description')), 0, 100);
                    if ($excerpt): ?>
                    <p class="mt-2 line-clamp-2 text-sm text-slate-500"><?= e($excerpt) ?>…</p>
                    <?php endif; ?>

                    <div class="mt-auto pt-4">
                        <a href="<?= e(route('events/' . $ev['slug'])) ?>"
                           class="inline-flex items-center gap-1 text-sm font-semibold text-blue-700 transition-colors hover:text-blue-900">
                            <?= e(t('resident_home.details_link')) ?> <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>

    </div><!-- /list view -->

    <!-- ── CALENDAR VIEW ──────────────────────────────────────────────────── -->
    <div x-show="view === 'calendar'" style="display:none;">

        <!-- Legend -->
        <div class="mb-4 flex flex-wrap items-center gap-4 text-xs font-medium text-slate-600">
            <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-full bg-blue-500"></span> <?= e(t('res_events.status_upcoming')) ?></span>
            <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-full bg-green-500"></span> <?= e(t('res_events.status_ongoing')) ?></span>
            <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-full bg-slate-400"></span> <?= e(t('res_events.status_completed')) ?></span>
        </div>

        <!-- FullCalendar mount point -->
        <div id="barangay-calendar"
             class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
             style="min-height:560px;"></div>

    </div><!-- /calendar view -->

</div><!-- /Alpine -->

<!-- FullCalendar v6 JS (load after DOM) -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<script>
let _bgCalendar = null;

function initCalendar() {
    const el = document.getElementById('barangay-calendar');
    if (!el || _bgCalendar) return;

    const base = (window.BarangGabay?.baseUrl || '').replace(/\/$/, '');

    _bgCalendar = new FullCalendar.Calendar(el, {
        initialView:   'dayGridMonth',
        // FullCalendar's own locale bundles are not loaded (and it has none for
        // Manobo), so its built-in chrome stays English. Every label we
        // control is translated via buttonText / noEventsContent below.
        locale:        'en',
        height:        'auto',
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth,listMonth',
        },
        buttonText: {
            today:     <?= json_encode(t('res_events.cal_today')) ?>,
            month:     <?= json_encode(t('res_events.cal_month')) ?>,
            listMonth: <?= json_encode(t('res_events.cal_list')) ?>,
        },
        events: base + '/api/events/calendar',
        eventClick(info) {
            info.jsEvent.preventDefault();
            if (info.event.url) window.location.href = info.event.url;
        },
        eventDidMount(info) {
            const venue = info.event.extendedProps.venue;
            if (venue) info.el.title = venue;
        },
        noEventsContent: <?= json_encode(t('res_events.cal_no_events')) ?>,
    });

    _bgCalendar.render();
}

function eventsPage() {
    return {
        view: 'list',
        tab:  'all',

        init() {
            // Restore last view from sessionStorage
            const saved = sessionStorage.getItem('bg_events_view');
            if (saved === 'calendar') {
                this.view = 'calendar';
                this.$nextTick(() => initCalendar());
            }
        },

        switchView(v) {
            this.view = v;
            sessionStorage.setItem('bg_events_view', v);
            if (v === 'calendar') {
                this.$nextTick(() => initCalendar());
            }
        },
    };
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';