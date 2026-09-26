<?php
/**
 * Admin — events management list.
 * Variables: $events (array, possibly filtered), $allEvents (array), $status (string)
 */
// The table shows $events, which ?status= may have narrowed. The chips count
// from the unfiltered list, so filtering to "cancelled" does not make the page
// claim there are no upcoming events.
$allEvents = $allEvents ?? $events;
$status    = $status    ?? '';

$statusChips = [
    'upcoming'  => ['label' => t('detail_ui.status_upcoming'),  'icon' => 'bi-calendar-event'],
    'ongoing'   => ['label' => t('detail_ui.status_ongoing'),   'icon' => 'bi-broadcast'],
    'completed' => ['label' => t('detail_ui.status_completed'), 'icon' => 'bi-check2-circle'],
    'cancelled' => ['label' => t('detail_ui.status_cancelled'), 'icon' => 'bi-x-circle'],
];

$statusCounts = [];
foreach (array_keys($statusChips) as $__s) {
    $statusCounts[$__s] = count(array_filter(
        $allEvents,
        static fn (array $e): bool => ($e['status'] ?? '') === $__s
    ));
}

/** Clicking the active chip again clears the filter. */
$chipUrl = static function (string $want) use ($status): string {
    return route('admin/events') . ($status === $want ? '' : '?status=' . $want);
};

ob_start();
?>

<!-- Header row -->
<div class="d-flex align-items-center justify-content-between mb-3 gap-3 flex-wrap">
    <div>
        <p class="text-muted small mb-0">
            <?= count($allEvents) ?> event<?= count($allEvents) !== 1 ? 's' : '' ?> total
        </p>
    </div>
    <a href="<?= e(route('admin/events/create')) ?>" class="btn btn-barangay btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Create Event
    </a>
</div>

<!-- Status filter. The dashboard's "Upcoming events" card lands here with
     ?status=upcoming already applied, so these chips are how you widen or
     change that view rather than a separate control you have to find. -->
<?php if (!empty($allEvents)): ?>
<div class="d-flex align-items-center flex-wrap gap-2 mb-4">
    <a href="<?= e(route('admin/events')) ?>"
       class="filter-chip<?= $status === '' ? ' is-active' : '' ?>"
       <?= $status === '' ? 'aria-current="true"' : '' ?>>
        <?= e(t('common.all')) ?> <span class="filter-chip-count"><?= count($allEvents) ?></span>
    </a>
    <?php foreach ($statusChips as $__key => $__chip): ?>
    <a href="<?= e($chipUrl($__key)) ?>"
       class="filter-chip<?= $status === $__key ? ' is-active' : '' ?>"
       <?= $status === $__key ? 'aria-current="true"' : '' ?>>
        <i class="bi <?= e($__chip['icon']) ?>" aria-hidden="true"></i>
        <?= e($__chip['label']) ?>
        <span class="filter-chip-count"><?= (int) $statusCounts[$__key] ?></span>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (empty($events)): ?>

<div class="admin-card text-center py-5">
    <?php if ($status !== ''): ?>
    <?php /* An empty filter is not an empty system — do not invite them to
             create the "first" event when several already exist. */ ?>
    <i class="bi bi-funnel text-secondary opacity-25" style="font-size:3rem;"></i>
    <p class="mt-3 mb-3 text-muted">
        <?= e(t('admin_events.empty_filtered', ['status' => $statusChips[$status]['label'] ?? $status])) ?>
    </p>
    <a href="<?= e(route('admin/events')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> <?= e(t('admin_announcements.filter_clear')) ?>
    </a>
    <?php else: ?>
    <i class="bi bi-calendar-x text-secondary opacity-25" style="font-size:3rem;"></i>
    <p class="mt-3 mb-0 text-muted">No events yet.</p>
    <a href="<?= e(route('admin/events/create')) ?>" class="btn btn-barangay btn-sm mt-3">
        <i class="bi bi-plus-lg me-1"></i> Create the first event
    </a>
    <?php endif; ?>
</div>

<?php else: ?>

<div class="admin-card p-0">
    <div class="table-responsive">
        <table class="table admin-table mb-0">
            <thead>
                <tr>
                    <th style="width:42%">Event</th>
                    <th>Date</th>
                    <th>Venue</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
            /*
             * Which events are still leaning on the generated Manobo voice.
             *
             * One query for the whole page rather than one per row. This is the
             * barangay's worklist: every flagged event is a notice a Manobo
             * speaker could improve by recording it properly, and without
             * surfacing it here the approximation quietly becomes permanent.
             */
            $__approxManobo = \App\Models\PostAudio::approximateManoboIndex('event');
            ?>
            <?php foreach ($events as $ev):
                $evTs   = strtotime($ev['event_date'] ?? 'now');
                $status = $ev['status'] ?? 'upcoming';
                $statusBadge = match($status) {
                    'upcoming'  => 'background:#dbeafe;border:1px solid #bfdbfe;color:#1e40af;',
                    'ongoing'   => 'background:#d4edda;border:1px solid #c3e6cb;color:#155724;',
                    'completed' => 'background:#f1f5f9;border:1px solid #cbd5e1;color:#475569;',
                    'cancelled' => 'background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;',
                    default     => 'background:#f1f5f9;border:1px solid #cbd5e1;color:#475569;',
                };
                $statusLabel = match($status) {
                    'upcoming'  => 'Upcoming',
                    'ongoing'   => 'Ongoing',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                    default     => ucfirst($status),
                };
            ?>
            <tr>
                <!-- Event title + organizer -->
                <td>
                    <div class="d-flex align-items-center gap-3">
                        <?php if (!empty($ev['cover_image_url'])): ?>
                        <img src="<?= e(asset($ev['cover_image_url'])) ?>"
                             alt=""
                             class="rounded-3 flex-shrink-0"
                             style="width:44px;height:44px;object-fit:cover;">
                        <?php else: ?>
                        <div class="rounded-3 flex-shrink-0 bg-light d-flex align-items-center justify-content-center"
                             style="width:44px;height:44px;">
                            <i class="bi bi-calendar-event text-secondary"></i>
                        </div>
                        <?php endif; ?>
                        <div class="min-w-0">
                            <p class="fw-semibold mb-0 text-truncate" style="max-width:220px;">
                                <?= e($ev['title']) ?>
                            </p>
                            <p class="text-muted small mb-0">
                                by <?= e($ev['organizer_name'] ?? 'Staff') ?>
                            </p>
                            <?php
                            /* Manobo text state, not just "has some". Missing
                               is the case staff need to see: that post's MN
                               Listen button does nothing for residents. */
                            $__mnState = manobo_text_state($ev, 'description');
                            ?>
                            <span class="manobo-state manobo-state-<?= e($__mnState) ?>"
                                  title="<?= e(t('manobo_state.' . $__mnState . '_help')) ?>">
                                🌿 <?= e(t('manobo_state.' . $__mnState)) ?>
                            </span>
                            <?php if (isset($__approxManobo['event:' . $ev['id']])): ?>
                            <span class="voice-approx-flag"
                                  title="<?= e(t('voice_reader.approx_flag_title')) ?>">
                                <i class="bi bi-robot"></i> <?= e(t('voice_reader.approx_flag')) ?>
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($ev['audio_manobo_path'])): ?>
                            <span class="badge rounded-pill"
                                  style="background:#e0f2fe;color:#075985;font-size:.6rem;font-weight:600;"
                                  title="May Manobo audio recording">
                                🎙 Audio
                            </span>
                            <?php endif; ?>

                            <?php
                            /* All three languages, each saying why it is
                               missing and offering to fix itself. This list
                               showed only the Manobo state before, so an
                               event with no English version looked
                               completely fine here. */
                            $lbRow       = $ev;
                            $lbType      = 'event';
                            $lbBodyField = 'description';
                            $lbAttempts  = $evAttempts[(int) $ev['id']] ?? [];
                            $lbRedirect  = '/admin/events';
                            require __DIR__ . '/../../shared/_language-badges.php';
                            ?>
                        </div>
                    </div>
                </td>

                <!-- Date -->
                <td class="align-middle">
                    <p class="mb-0 small fw-semibold"><?= e(date('M j, Y', $evTs)) ?></p>
                    <p class="mb-0 text-muted small"><?= e(date('g:i A', $evTs)) ?></p>
                </td>

                <!-- Venue -->
                <td class="align-middle">
                    <?php if (!empty($ev['venue'])): ?>
                    <span class="d-flex align-items-center gap-1 small text-muted"
                          title="<?= e($ev['venue']) ?>">
                        <i class="bi bi-geo-alt flex-shrink-0"></i>
                        <span class="text-truncate" style="max-width:140px;"><?= e($ev['venue']) ?></span>
                    </span>
                    <?php else: ?>
                    <span class="text-muted small">—</span>
                    <?php endif; ?>
                </td>

                <!-- Status badge -->
                <td class="align-middle">
                    <span class="badge rounded-pill px-3 py-1" style="<?= $statusBadge ?>">
                        <?= $statusLabel ?>
                    </span>
                </td>

                <!-- Actions -->
                <td class="align-middle text-end">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= e(route('events/' . $ev['slug'])) ?>"
                           target="_blank"
                           class="btn btn-sm btn-outline-secondary"
                           title="View on site">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="<?= e(route('admin/events/' . $ev['id'] . '/edit')) ?>"
                           class="btn btn-sm btn-outline-primary"
                           title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php /* Opens the SMS page with this event chosen.
                                 Nothing is sent from here — the preview, the
                                 recipient choice and the cost are all there. */ ?>
                        <a href="<?= e(route('admin/sms?post_type=event&post_id=' . (int) $ev['id'])) ?>"
                           class="btn btn-sm btn-outline-secondary"
                           title="<?= e(t('sms_post.from_post_row')) ?>">
                            <i class="bi bi-chat-dots"></i>
                        </a>
                        <form method="post"
                              action="<?= e(route('admin/events/' . $ev['id'] . '/delete')) ?>"
                              class="d-inline"
                              onsubmit="return confirm('Delete event &quot;<?= e(addslashes($ev['title'])) ?>&quot;? This cannot be undone.');">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <button type="submit"
                                    class="btn btn-sm btn-outline-danger"
                                    title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php
// One detail panel for the whole page — the chips above open it.
$lbpRedirect = '/admin/events';
require __DIR__ . '/../../shared/_language-badge-panel.php';
?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
