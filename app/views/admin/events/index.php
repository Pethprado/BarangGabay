<?php
/**
 * Admin — events management list.
 * Variables: $events (array, possibly filtered), $allEvents (array), $status (string)
 */
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
$mine = (bool) ($mine ?? false);
$chipUrl = static function (string $want) use ($status, $mine): string {
    $query = array_filter(['status' => $status === $want ? '' : $want, 'mine' => $mine ? '1' : '']);
    return route('admin/events') . ($query ? '?' . http_build_query($query) : '');
};

$userRole = $_SESSION['role'] ?? 'staff';
$canDelete = in_array($userRole, ['admin', 'superadmin'], true);

ob_start();
?>

<!-- Page header -->
<div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="mb-0" style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);">CONTENT MANAGEMENT</p>
        <h1 class="mb-0 mt-1" style="font-size:1.65rem;font-weight:800;color:var(--text-primary);line-height:1.1;">Events</h1>
        <p class="text-muted mt-1 mb-0" style="font-size:.85rem;">
            Manage barangay events and schedules.
        </p>
    </div>
    <div>
        <a href="<?= e(route('admin/events/create')) ?>" class="btn-barangay d-inline-flex align-items-center gap-2 px-3 py-2 fw-semibold" style="box-shadow: 0 4px 12px rgba(26,107,58,0.2);">
            <i class="bi bi-plus-lg"></i> Create Event
        </a>
    </div>
</div>

<!-- Status Filter Chips -->
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

<!-- Toolbar: Search Bar & View Mode Toggle -->
<div class="admin-card p-3 mb-4">
    <div class="row g-3 align-items-center">
        <div class="col-md-6 col-lg-5">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: .5rem 0 0 .5rem;">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text"
                       id="eventSearchInput"
                       class="form-control border-start-0 ps-0"
                       placeholder="Search events by title, venue, or details..."
                       style="border-radius: 0 .5rem .5rem 0; font-size:.875rem;"
                       onkeyup="filterEvents()">
            </div>
        </div>
        <div class="col-md-6 col-lg-7 d-flex justify-content-md-end align-items-center gap-2">
            <div class="btn-group" role="group" aria-label="View toggle">
                <button type="button" class="btn btn-outline-secondary btn-sm active" id="btnEventViewTable" onclick="toggleEventView('table')">
                    <i class="bi bi-list-ul me-1"></i> Table View
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnEventViewCards" onclick="toggleEventView('cards')">
                    <i class="bi bi-grid-fill me-1"></i> Cards View
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Empty state -->
<?php if (empty($events)): ?>

<div class="admin-card text-center py-5">
    <?php if ($status !== ''): ?>
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
    <a href="<?= e(route('admin/events/create')) ?>" class="btn-barangay btn-sm mt-3 d-inline-flex align-items-center gap-1">
        <i class="bi bi-plus-lg me-1"></i> Create Event
    </a>
    <?php endif; ?>
</div>

<?php else: ?>

<?php
$__approxManobo = \App\Models\PostAudio::approximateManoboIndex('event');
?>

<!-- Table View -->
<div id="eventTableView" class="admin-card p-0 fade-up">
    <div class="table-responsive">
        <table class="table admin-table mb-0">
            <thead>
                <tr>
                    <th style="width:40%">Event</th>
                    <th>Date & Time</th>
                    <th>Venue</th>
                    <th>Status</th>
                    <th class="text-end" style="width:140px;padding-right:16px;">Actions</th>
                </tr>
            </thead>
            <tbody id="eventTableBody">
            <?php foreach ($events as $ev):
                $evTs   = strtotime($ev['event_date'] ?? 'now');
                $evStatus = $ev['status'] ?? 'upcoming';
                $statusBadge = match($evStatus) {
                    'upcoming'  => 'background:#dbeafe;border:1px solid #bfdbfe;color:#1e40af;',
                    'ongoing'   => 'background:#d4edda;border:1px solid #c3e6cb;color:#155724;',
                    'completed' => 'background:#f1f5f9;border:1px solid #cbd5e1;color:#475569;',
                    'cancelled' => 'background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;',
                    default     => 'background:#f1f5f9;border:1px solid #cbd5e1;color:#475569;',
                };
                $statusLabel = match($evStatus) {
                    'upcoming'  => 'Upcoming',
                    'ongoing'   => 'Ongoing',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                    default     => ucfirst($evStatus),
                };
                $searchContent = strtolower($ev['title'] . ' ' . ($ev['venue'] ?? '') . ' ' . strip_tags($ev['description'] ?? ''));
            ?>
            <tr class="event-item-row" data-search="<?= e($searchContent) ?>">
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
                            <p class="fw-semibold mb-0 text-truncate" style="max-width:240px;">
                                <?= e($ev['title']) ?>
                            </p>
                            <p class="text-muted small mb-0">
                                by <?= e($ev['organizer_name'] ?? 'Staff') ?>
                            </p>
                            <?php
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
                <td class="align-middle text-end" style="padding-right:16px;">
                    <div class="d-flex justify-content-end gap-1">
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
                        <a href="<?= e(route('admin/sms?post_type=event&post_id=' . (int) $ev['id'])) ?>"
                           class="btn btn-sm btn-outline-secondary"
                           title="<?= e(t('sms_post.from_post_row')) ?>">
                            <i class="bi bi-chat-dots"></i>
                        </a>
                        <?php if ($canDelete): ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-danger"
                                onclick="openDeleteEventModal(<?= (int) $ev['id'] ?>, '<?= e(addslashes($ev['title'])) ?>')"
                                title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Cards View -->
<div id="eventCardsView" class="row g-3 d-none">
    <?php foreach ($events as $ev):
        $evTs   = strtotime($ev['event_date'] ?? 'now');
        $evStatus = $ev['status'] ?? 'upcoming';
        $statusBadge = match($evStatus) {
            'upcoming'  => 'background:#dbeafe;border:1px solid #bfdbfe;color:#1e40af;',
            'ongoing'   => 'background:#d4edda;border:1px solid #c3e6cb;color:#155724;',
            'completed' => 'background:#f1f5f9;border:1px solid #cbd5e1;color:#475569;',
            'cancelled' => 'background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;',
            default     => 'background:#f1f5f9;border:1px solid #cbd5e1;color:#475569;',
        };
        $statusLabel = match($evStatus) {
            'upcoming'  => 'Upcoming',
            'ongoing'   => 'Ongoing',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default     => ucfirst($evStatus),
        };
        $desc = strip_tags(html_entity_decode($ev['description'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $desc = mb_strlen($desc) > 120 ? mb_substr($desc, 0, 120) . '…' : $desc;
        $searchContent = strtolower($ev['title'] . ' ' . ($ev['venue'] ?? '') . ' ' . $desc);
    ?>
    <div class="col-md-6 col-lg-4 event-item-card" data-search="<?= e($searchContent) ?>">
        <div class="admin-card h-100 d-flex flex-column p-0 overflow-hidden">
            <!-- Cover image / Header -->
            <div style="height: 140px; background: #f8fafc; position: relative; overflow: hidden;" class="border-bottom">
                <?php if (!empty($ev['cover_image_url'])): ?>
                <img src="<?= e(asset($ev['cover_image_url'])) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                <div class="d-flex align-items-center justify-content-center h-100 text-muted opacity-50">
                    <i class="bi bi-calendar-event" style="font-size: 2.5rem;"></i>
                </div>
                <?php endif; ?>
                <div style="position: absolute; top: 10px; right: 10px;">
                    <span class="badge rounded-pill px-3 py-1 fw-semibold" style="<?= $statusBadge ?>"><?= $statusLabel ?></span>
                </div>
            </div>

            <!-- Content Body -->
            <div class="p-3 d-flex flex-column flex-grow-1">
                <div class="d-flex align-items-center gap-2 text-muted mb-2" style="font-size: .78rem;">
                    <i class="bi bi-calendar-check text-primary"></i>
                    <span><?= e(date('M j, Y • g:i A', $evTs)) ?></span>
                </div>

                <h5 class="fw-bold text-dark mb-2" style="font-size: 1rem; line-height: 1.3;">
                    <a href="<?= e(route('admin/events/' . $ev['id'] . '/edit')) ?>" class="text-dark text-decoration-none">
                        <?= e($ev['title']) ?>
                    </a>
                </h5>

                <?php if (!empty($ev['venue'])): ?>
                <p class="text-muted small mb-2 d-flex align-items-center gap-1" style="font-size: .8rem;">
                    <i class="bi bi-geo-alt text-danger"></i>
                    <span class="text-truncate"><?= e($ev['venue']) ?></span>
                </p>
                <?php endif; ?>

                <p class="text-muted small mb-3 flex-grow-1" style="line-height: 1.5; font-size: .82rem;">
                    <?= e($desc) ?>
                </p>

                <!-- Footer Actions -->
                <div class="pt-2 border-top d-flex align-items-center justify-content-between mt-auto">
                    <a href="<?= e(route('events/' . $ev['slug'])) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-eye"></i> View
                    </a>
                    <div class="d-flex gap-1">
                        <a href="<?= e(route('admin/events/' . $ev['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <?php if ($canDelete): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="openDeleteEventModal(<?= (int) $ev['id'] ?>, '<?= e(addslashes($ev['title'])) ?>')">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteEventModal" tabindex="-1" aria-labelledby="deleteEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 1rem; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2" id="deleteEventModalLabel">
                    <i class="bi bi-exclamation-triangle-fill"></i> Delete Event?
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <p class="text-muted mb-0" style="font-size: .92rem;">
                    Are you sure you want to delete <strong id="deleteEventTargetTitle" class="text-dark">this event</strong>? This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-3 px-3" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteEventForm" method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button type="submit" class="btn btn-danger rounded-3 px-4 fw-semibold">Delete Event</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$lbpRedirect = '/admin/events';
require __DIR__ . '/../../shared/_language-badge-panel.php';
?>

<script>
function filterEvents() {
    const query = document.getElementById('eventSearchInput').value.toLowerCase().trim();
    
    // Filter Table rows
    const rows = document.querySelectorAll('.event-item-row');
    rows.forEach(row => {
        const text = row.getAttribute('data-search') || '';
        if (text.includes(query)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });

    // Filter Cards
    const cards = document.querySelectorAll('.event-item-card');
    cards.forEach(card => {
        const text = card.getAttribute('data-search') || '';
        if (text.includes(query)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}

function toggleEventView(mode) {
    const tableView = document.getElementById('eventTableView');
    const cardsView = document.getElementById('eventCardsView');
    const btnTable = document.getElementById('btnEventViewTable');
    const btnCards = document.getElementById('btnEventViewCards');

    if (mode === 'cards') {
        if (tableView) tableView.classList.add('d-none');
        if (cardsView) cardsView.classList.remove('d-none');
        btnCards.classList.add('active');
        btnTable.classList.remove('active');
    } else {
        if (cardsView) cardsView.classList.add('d-none');
        if (tableView) tableView.classList.remove('d-none');
        btnTable.classList.add('active');
        btnCards.classList.remove('active');
    }
}

function openDeleteEventModal(id, title) {
    document.getElementById('deleteEventTargetTitle').innerText = title;
    const form = document.getElementById('deleteEventForm');
    form.action = "<?= e(route('admin/events/')) ?>" + id + "/delete";
    const modal = new bootstrap.Modal(document.getElementById('deleteEventModal'));
    modal.show();
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
