<?php
/**
 * Resident event detail view.
 * Variables: $event (array), $mapsKey (string)
 */

$mapsKey = $mapsKey ?? '';

$statusMeta = [
    'upcoming'  => ['cls' => 'bg-blue-100 text-blue-700',   'label' => t('detail_ui.status_upcoming')],
    'ongoing'   => ['cls' => 'bg-green-100 text-green-700', 'label' => t('detail_ui.status_ongoing')],
    'completed' => ['cls' => 'bg-slate-100 text-slate-500', 'label' => t('detail_ui.status_completed')],
    'cancelled' => ['cls' => 'bg-red-100 text-red-600',     'label' => t('detail_ui.status_cancelled')],
];

$status = $event['status'] ?? 'upcoming';
$sCls   = $statusMeta[$status]['cls']   ?? 'bg-slate-100 text-slate-500';
$sLbl   = $statusMeta[$status]['label'] ?? ucfirst($status);

$hasMap = !empty($event['latitude']) && !empty($event['longitude']);
$evTs   = strtotime($event['event_date'] ?? 'now');

// The title in the reader's chosen language, used everywhere the event is
// named on this page. The raw $event['title'] is still used as the SOURCE text
// for the on-demand Manobo translator widget further down — that one must stay
// the original, or it would be translating an already-translated string.
$evTitle = localised_text($event, 'title');

// Build Google Maps embed URL — use Embed API if key present, plain embed fallback otherwise.
if ($hasMap) {
    $latLng = $event['latitude'] . ',' . $event['longitude'];
    $mapSrc = $mapsKey
        ? 'https://www.google.com/maps/embed/v1/place?key=' . urlencode($mapsKey) . '&q=' . urlencode($latLng) . '&zoom=16'
        : 'https://maps.google.com/maps?q=' . urlencode($latLng) . '&z=16&output=embed';
}

ob_start();
?>

<!-- ── Breadcrumb ───────────────────────────────────────────────────────── -->
<nav class="mb-5 flex items-center gap-2 text-xs text-slate-400" aria-label="Breadcrumb">
    <a href="<?= e(route('')) ?>" class="hover:text-blue-700 transition-colors"><?= e(t('nav.home')) ?></a>
    <i class="bi bi-chevron-right" style="font-size:.65rem;"></i>
    <a href="<?= e(route('events')) ?>" class="hover:text-blue-700 transition-colors"><?= e(t('nav.events')) ?></a>
    <i class="bi bi-chevron-right" style="font-size:.65rem;"></i>
    <span class="line-clamp-1 max-w-[200px] text-slate-600"><?= e($evTitle) ?></span>
</nav>

<!-- ── Two-column body ──────────────────────────────────────────────────── -->
<div class="grid gap-8 lg:grid-cols-[1fr_320px]">

    <!-- ── Left: the post itself ────────────────────────────────────────── -->
    <div class="min-w-0">

        <?php
        /*
         * Author row + headline + hero, shared with announcements and
         * ordinances so all three posts read the same way. The status badge
         * lives in the author row now rather than floating above the title.
         */
        $__pRow     = $event;
        $__pTitle   = $evTitle;
        $__pImage   = $event['cover_image_url'] ?? null;
        $__pEyebrow = null;
        $__pStamp   = null;
        $__pBadges  = [['label' => $sLbl, 'class' => $sCls]];
        require __DIR__ . '/../shared/_post-header.php';

        // Description language follows the header's FIL / EN / MN switch.
        // The stored description is plain text in every language here, so the
        // same escaped rendering works for the original and the translations.
        $descPick     = localised_content($event, 'description');
$evTitlePick  = localised_content($event, 'title');
        ?>

        <?php
        /*
         * Voice reader, directly under the headline.
         *
         * When, where, then what: PostScript puts the date, time and venue
         * ahead of the description, because a listener who cannot see the
         * sidebar needs them first rather than four sentences in.
         */
        $__vrType = 'event';
        $__vrRow  = $event;
        require __DIR__ . '/../shared/_voice-reader.php';
        ?>

        <hr class="post-rule">

        <?php $__tnPicks = [$descPick, $evTitlePick]; require __DIR__ . '/../shared/_translation-notice.php'; ?>

        <?php /* data-voice-body marks what the voice reader highlights as it reads. */ ?>
        <div class="post-body" data-voice-body>
            <?php // Some descriptions were saved with editor HTML; show paragraphs, not literal tags. ?>
            <?= nl2br(e(\App\Services\SpokenText::plain((string) $descPick['text']))) ?>
        </div>

        <?php
        // The original post, when this event came from one. See the note in
        // the announcement detail view.
        $__seRow = $event;
        require __DIR__ . '/../shared/_source-embed.php';
        ?>


        <!-- Back link -->
        <div class="mt-8 border-t border-slate-100 pt-6">
            <a href="<?= e(route('events')) ?>"
               class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-slate-700">
                <i class="bi bi-arrow-left"></i> <?= e(t('res_event_detail.back_to_list')) ?>
            </a>
        </div>
    </div>

    <!-- ── Right: info card + map + calendar ────────────────────────────── -->
    <aside class="space-y-4">

        <!-- Info card -->
        <div class="post-card">
            <div class="post-card-head">
                <h2 class="post-card-title"><?= e(t('res_event_detail.details_title')) ?></h2>
            </div>
            <div class="post-card-body">
            <ul class="post-facts">
                <!-- Date -->
                <li>
                    <span class="post-fact-icon">
                        <i class="bi bi-calendar3"></i>
                    </span>
                    <div>
                        <p class="post-fact-label"><?= e(t('res_event_detail.date_time')) ?></p>
                        <p class="post-fact-value">
                            <?= e(date('F j, Y', $evTs)) ?>
                        </p>
                        <p class="post-fact-value" style="font-size:.78rem;color:var(--text-secondary);">
                            <?= e(date('g:i A', $evTs)) ?>
                            <?php if (!empty($event['end_date'])): ?>
                            &ndash; <?= e(date('g:i A', strtotime($event['end_date']))) ?>
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($event['end_date']) && date('Y-m-d', $evTs) !== date('Y-m-d', strtotime($event['end_date']))): ?>
                        <p class="post-fact-value" style="font-size:.78rem;color:var(--text-secondary);">
                            <?= e(t('detail_ui.until')) ?> <?= e(date('F j, Y', strtotime($event['end_date']))) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </li>

                <!-- Venue -->
                <?php if (!empty($event['venue'])): ?>
                <li>
                    <span class="post-fact-icon">
                        <i class="bi bi-geo-alt"></i>
                    </span>
                    <div>
                        <p class="post-fact-label"><?= e(t('res_event_detail.venue')) ?></p>
                        <p class="post-fact-value"><?= e($event['venue']) ?></p>
                    </div>
                </li>
                <?php endif; ?>

                <?php /* Organiser deliberately not repeated here. It reads from
                         events.created_by — the same person, from the same join,
                         as the author row at the top of the post. Showing the
                         identical name twice on one screen invited the reader to
                         look for a difference that does not exist. */ ?>

                <?php /* Status deliberately not repeated here — it is already
                         the badge beside the title at the top of the page, and
                         showing it twice made readers check which one was
                         current. This sidebar holds facts (when, where, who),
                         not a second copy of the header. */ ?>
            </ul>
            </div><!-- /post-card-body -->
        </div><!-- /post-card -->

        <!-- "Idagdag sa Calendar" button -->
        <a href="<?= e(route('events/' . $event['slug'] . '/calendar')) ?>"
           class="flex w-full items-center justify-center gap-2 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-100 hover:border-blue-300">
            <i class="bi bi-calendar-plus"></i>
            <?= e(t('res_event_detail.add_to_calendar')) ?>
        </a>

        <!-- Google Maps embed -->
        <?php if ($hasMap): ?>
        <div class="post-card">
            <div class="post-card-head">
                <p class="post-card-title"><?= e(t('res_event_detail.map_title')) ?></p>
            </div>
            <iframe
                src="<?= e($mapSrc) ?>"
                width="100%"
                height="280"
                style="border:0;"
                allowfullscreen
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                title="Event venue map">
            </iframe>
        </div>
        <?php elseif (!empty($event['venue'])): ?>
        <!-- No coordinates but venue text is present -->
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-center">
            <i class="bi bi-geo-alt text-2xl text-slate-300"></i>
            <p class="mt-2 text-sm font-medium text-slate-600"><?= e($event['venue']) ?></p>
            <p class="mt-1 text-xs text-slate-400"><?= e(t('res_event_detail.map_unavailable')) ?></p>
        </div>
        <?php endif; ?>

    </aside>

</div><!-- /grid -->

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
