<?php
/**
 * Admin — edit event form.
 * Variables: $event (array), $pageTitle (string), $mapsKey (string)
 */
$mapsKey = $mapsKey ?? '';

// Default map centre falls back to existing coords or Madrid town centre
$initLat = !empty($event['latitude'])  ? (float) $event['latitude']  : 9.2647;
$initLng = !empty($event['longitude']) ? (float) $event['longitude'] : 125.9633;
$hasPin  = !empty($event['latitude']) && !empty($event['longitude']);

ob_start();
?>

<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<!-- Print styles -->
<style>
@media print {
    .admin-sidebar, .admin-topbar, .pending-banner,
    .admin-flash, .no-print { display: none !important; }
    .admin-main    { margin-left: 0 !important; }
    .admin-content { padding: 0 !important; }
    .print-only    { display: block !important; }
    body { background: #fff !important; }
}
</style>

<!-- Print preview -->
<div class="print-only" style="display:none; padding:2rem; font-family:Georgia,serif;">
    <div style="text-align:center; border-bottom:2px solid #000; padding-bottom:1rem; margin-bottom:1.5rem;">
        <h2 style="margin:0; font-size:1.1rem; text-transform:uppercase; letter-spacing:.05em;">
            BARANGAY BAYOGO, MADRID, SURIGAO DEL SUR
        </h2>
        <p style="margin:.25rem 0 0; font-size:.85rem;">Official Event Announcement</p>
    </div>
    <h1 style="font-size:1.4rem; margin-bottom:.5rem;"><?= e($event['title']) ?></h1>
    <p style="font-size:.85rem; color:#555; margin-bottom:.5rem;">
        <strong>Date:</strong> <?= date('F j, Y \a\t g:i A', strtotime($event['event_date'])) ?>
        <?php if (!empty($event['end_date'])): ?>
        &mdash; <?= date('g:i A', strtotime($event['end_date'])) ?>
        <?php endif; ?>
    </p>
    <?php if (!empty($event['venue'])): ?>
    <p style="font-size:.85rem; color:#555; margin-bottom:1.5rem;">
        <strong>Venue:</strong> <?= e($event['venue']) ?>
    </p>
    <?php endif; ?>
    <div style="line-height:1.7; font-size:.95rem;"><?= e(strip_tags($event['description'])) ?></div>
    <div style="margin-top:3rem; border-top:1px solid #000; padding-top:1rem; font-size:.75rem; color:#555; text-align:center;">
        Barangay Bayogo, Madrid, Surigao del Sur &mdash; Official Event Notice
    </div>
</div>

<div class="no-print d-flex align-items-center justify-content-between mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item">
                <a href="<?= e(route('admin/events')) ?>"><?= e(t('admin_nav.events')) ?></a>
            </li>
            <li class="breadcrumb-item active"><?= e(t('admin_announcements.breadcrumb_edit_prefix')) ?>: <?= e($event['title']) ?></li>
        </ol>
    </nav>
    <button type="button" onclick="window.print()"
            class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
        <i class="bi bi-printer"></i> <?= e(t('admin_announcements.print_btn')) ?>
    </button>
</div>

<form method="post"
      action="<?= e(route('admin/events/' . $event['id'])) ?>"
      enctype="multipart/form-data"
      novalidate>

    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="row g-4">

        <!-- ── Left column ───────────────────────────────────────────────── -->
        <div class="col-lg-8">

            <!-- Title + Description -->
            <div class="admin-card mb-4">
                <h6 class="fw-semibold mb-3 text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.08em;">
                    <?= e(t('admin_events.section_event_info')) ?>
                </h6>

                <div class="mb-3">
                    <label for="title" class="form-label fw-semibold">
                        <?= e(t('admin_announcements.field_title')) ?> <span class="text-danger">*</span>
                    </label>
                    <input type="text"
                           id="title"
                           name="title"
                           class="form-control"
                           value="<?= e($event['title']) ?>"
                           maxlength="500"
                           required>
                </div>

                <div class="mb-0">
                    <label for="description" class="form-label fw-semibold">
                        <?= e(t('admin_events.field_description')) ?> <span class="text-danger">*</span>
                    </label>
                    <textarea id="description"
                              name="description"
                              class="form-control"
                              rows="7"
                              required><?= e($event['description']) ?></textarea>
                </div>
            </div>

            <!-- Manobo translation widget -->
            <?php
            $__mTitleVal  = $event['title_manobo']       ?? '';
            $__mBodyVal   = $event['description_manobo'] ?? '';
            $__mBodyName  = 'description_manobo';
            $__mBodyLabel = t('manobo_fields.label_description');
            $__mAudioPath = $event['audio_manobo_path']  ?? null;
            $__eSourceLang = $event['source_lang'] ?? 'fil';
            $__eTitleVal  = $event['title_en'] ?? '';
            $__eBodyVal   = $event['description_en'] ?? '';
            $__eBodyName  = 'description_en';
            $__eBodyLabel = t('manobo_fields.label_description');
            // Staff hear the post before a resident does — the only reliable
            // way to catch an abbreviation or a missing full stop that reads
            // fine but sounds wrong. Above the translation panels because it
            // is about the content just typed, not a translation of it.
            // See the note in the announcement create form.
            $__ipType   = 'event';
            $__ipSource = $event['source_url'] ?? null;
            require __DIR__ . '/../../shared/_import-panel.php';

            $__vpSourceLang = $__eSourceLang ?? 'fil';
            $__vpType       = 'event';
            $__vpRow        = $event;
            require __DIR__ . '/../../shared/_voice-preview.php';

            /* Above the hand-entry fields: someone who can see that English
               is missing because the allowance ran out will wait for
               tonight rather than typing it in. */
            $lbRow       = $event;
            $lbType      = 'event';
            $lbBodyField = 'description';
            $lbAttempts  = \App\Models\TranslationAttempt::forContent('event', (int) $event['id']);
            $lbRedirect  = '/admin/events';
            require __DIR__ . '/../../shared/_language-status-card.php';

            require __DIR__ . '/../../shared/_english-fields.php';

            /* Reports on the source-language choice made just above,
               and on whether hand-typing the other language is necessary. */
            $tpTitleField = 'title';
            $tpBodyField  = 'description';
            $tpExisting   = (string) ($event['source_lang'] ?? 'fil');
            require __DIR__ . '/../../shared/_translation-plan.php';

            require __DIR__ . '/../../shared/_manobo-fields.php';
            ?>

            <!-- Dates -->
            <div class="admin-card mb-4">
                <h6 class="fw-semibold mb-3 text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.08em;">
                    <?= e(t('admin_events.section_schedule')) ?>
                </h6>

                <div class="row g-3">
                    <div class="col-sm-6">
                        <label for="event_date" class="form-label fw-semibold">
                            <?= e(t('admin_events.field_start_date')) ?> <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                            <input type="text"
                                   id="event_date"
                                   name="event_date"
                                   class="form-control flatpickr-datetime"
                                   value="<?= e($event['event_date']) ?>"
                                   autocomplete="off"
                                   required>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <label for="end_date" class="form-label fw-semibold">
                            <?= e(t('admin_events.field_end_date')) ?>
                            <span class="text-muted fw-normal"><?= e(t('common.optional')) ?></span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-calendar3-range"></i></span>
                            <input type="text"
                                   id="end_date"
                                   name="end_date"
                                   class="form-control flatpickr-datetime"
                                   value="<?= e($event['end_date'] ?? '') ?>"
                                   autocomplete="off">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Venue + Map -->
            <div class="admin-card mb-4">
                <h6 class="fw-semibold mb-3 text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.08em;">
                    <?= e(t('admin_events.section_venue')) ?>
                </h6>

                <div class="mb-3">
                    <label for="venue" class="form-label fw-semibold"><?= e(t('admin_events.field_venue_name')) ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                        <input type="text"
                               id="venue"
                               name="venue"
                               class="form-control"
                               value="<?= e($event['venue'] ?? '') ?>"
                               maxlength="255">
                    </div>
                </div>

                <?php if ($mapsKey): ?>
                <p class="mb-2 small text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    <?= e(t('admin_events.map_click_hint')) ?>
                </p>
                <div id="map-picker"
                     style="height:320px;border-radius:12px;overflow:hidden;border:1px solid #dee2e6;margin-bottom:.75rem;">
                </div>
                <?php else: ?>
                <p class="mb-2 small text-warning">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Set <code>GOOGLE_MAPS_API_KEY</code> in .env to enable the map picker.
                </p>
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-sm-6">
                        <label for="latitude" class="form-label fw-semibold small">Latitude</label>
                        <input type="text"
                               id="latitude"
                               name="latitude"
                               class="form-control form-control-sm"
                               value="<?= e($event['latitude'] ?? '') ?>"
                               placeholder="e.g. 9.2647">
                    </div>
                    <div class="col-sm-6">
                        <label for="longitude" class="form-label fw-semibold small">Longitude</label>
                        <input type="text"
                               id="longitude"
                               name="longitude"
                               class="form-control form-control-sm"
                               value="<?= e($event['longitude'] ?? '') ?>"
                               placeholder="e.g. 125.9633">
                    </div>
                </div>
            </div>

        </div><!-- /left -->

        <!-- ── Right column ───────────────────────────────────────────────── -->
        <div class="col-lg-4">

            <!-- Status -->
            <div class="admin-card mb-4">
                <h6 class="fw-semibold mb-3 text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.08em;">
                    <?= e(t('admin_announcements.field_status')) ?>
                </h6>
                <select id="status" name="status" class="form-select">
                    <?php
                    $statuses = [
                        'upcoming'  => t('admin_events.status_upcoming'),
                        'ongoing'   => t('admin_events.status_ongoing'),
                        'completed' => t('admin_events.status_completed'),
                        'cancelled' => t('admin_events.status_cancelled'),
                    ];
                    foreach ($statuses as $val => $lbl):
                        $sel = ($event['status'] === $val) ? 'selected' : '';
                    ?>
                    <option value="<?= $val ?>" <?= $sel ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Cover image -->
            <div class="admin-card mb-4">
                <h6 class="fw-semibold mb-3 text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.08em;">
                    <?= e(t('admin_announcements.cover_image_title')) ?>
                </h6>

                <?php if (!empty($event['cover_image_url'])): ?>
                <div class="mb-3 rounded-3 overflow-hidden border">
                    <img src="<?= e(asset($event['cover_image_url'])) ?>"
                         alt="<?= e(t('admin_announcements.current_cover')) ?>"
                         class="w-100"
                         style="max-height:140px;object-fit:cover;">
                </div>
                <p class="small text-muted mb-2"><?= e(t('admin_events.upload_replace_hint')) ?></p>
                <?php endif; ?>

                <div x-data="coverPreview()">
                    <label for="cover_image"
                           class="d-flex flex-column align-items-center justify-content-center gap-2
                                  border border-2 border-dashed rounded-3 p-3 text-center"
                           style="min-height:100px;background:#f8f9fa;cursor:pointer;"
                           @dragover.prevent
                           @drop.prevent="handleDrop($event)">
                        <template x-if="!preview">
                            <div>
                                <i class="bi bi-upload text-secondary opacity-50"></i>
                                <p class="small mb-0 mt-1"><?= e(t('admin_events.dropzone_text_simple')) ?></p>
                            </div>
                        </template>
                        <template x-if="preview">
                            <img :src="preview" alt="Preview"
                                 class="rounded-2 w-100"
                                 style="max-height:100px;object-fit:cover;">
                        </template>
                        <input type="file"
                               id="cover_image"
                               name="cover_image"
                               accept="image/jpeg,image/png"
                               class="d-none"
                               @change="handleChange($event)">
                    </label>
                </div>
            </div>

            <!-- Actions -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-barangay">
                    <i class="bi bi-check-lg me-1"></i> <?= e(t('admin_events.save_changes_btn')) ?>
                </button>
                <a href="<?= e(route('admin/events')) ?>" class="btn btn-outline-secondary">
                    <?= e(t('common.cancel')) ?>
                </a>
            </div>

            <!-- Danger zone: delete -->
            <div class="admin-card mt-4 border-danger border-opacity-25">
                <h6 class="fw-semibold mb-2 text-danger text-uppercase" style="font-size:.7rem;letter-spacing:.08em;">
                    <?= e(t('admin_announcements.danger_zone')) ?>
                </h6>
                <p class="small text-muted mb-3">
                    <?= e(t('admin_events.danger_zone_event_desc')) ?>
                </p>
                <?php /* The button targets #deleteEventForm below via form="",
                         rather than sitting in a <form> of its own here.

                         This WAS a nested form, and nested forms are invalid
                         HTML: the browser drops the inner opening tag while
                         parsing, so this button submitted the EDIT form to the
                         edit action. Pressing "Delete event" saved the event
                         instead of deleting it, and looked like nothing
                         happened. The announcements page already fixed this the
                         same way; the events page was missed. */ ?>
                <button type="submit"
                        form="deleteEventForm"
                        class="btn btn-outline-danger btn-sm w-100">
                    <i class="bi bi-trash me-1"></i> <?= e(t('admin_events.delete_event_btn')) ?>
                </button>
            </div>

        </div><!-- /right -->

    </div><!-- /row -->

</form>

<?php /* The delete form, out here where it is legal HTML. It used to sit
         inside the edit form above, which the browser silently unnests —
         so the Delete button posted to the EDIT action and the event was
         saved instead of deleted. The button references this by id. */ ?>
<form id="deleteEventForm"
      method="post"
      action="<?= e(route('admin/events/' . $event['id'] . '/delete')) ?>"
      onsubmit="return confirm(<?= e(json_encode(t('admin_events.confirm_delete_event'))) ?>);">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
</form>

<!-- ── Page scripts ─────────────────────────────────────────────────────── -->

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    flatpickr('.flatpickr-datetime', {
        enableTime:      true,
        dateFormat:      'Y-m-d H:i:00',
        altInput:        true,
        altFormat:       'F j, Y h:i K',
        time_24hr:       false,
        minuteIncrement: 15,
    });
});

function coverPreview() {
    return {
        preview: null,
        handleChange(e) {
            const file = e.target.files[0];
            if (file) this.setFile(file);
        },
        handleDrop(e) {
            const file = e.dataTransfer.files[0];
            if (file && file.type.startsWith('image/')) {
                document.getElementById('cover_image').files = e.dataTransfer.files;
                this.setFile(file);
            }
        },
        setFile(file) {
            const reader = new FileReader();
            reader.onload = (ev) => { this.preview = ev.target.result; };
            reader.readAsDataURL(file);
        },
    };
}
</script>

<?php if ($mapsKey): ?>
<script>
let mapPicker, mapMarker;

function initMap() {
    const center = { lat: <?= $initLat ?>, lng: <?= $initLng ?> };

    mapPicker = new google.maps.Map(document.getElementById('map-picker'), {
        center: center,
        zoom: 15,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: true,
    });

    <?php if ($hasPin): ?>
    mapMarker = new google.maps.Marker({ position: center, map: mapPicker });
    <?php endif; ?>

    mapPicker.addListener('click', function (e) {
        const lat = e.latLng.lat().toFixed(7);
        const lng = e.latLng.lng().toFixed(7);
        document.getElementById('latitude').value  = lat;
        document.getElementById('longitude').value = lng;

        if (mapMarker) mapMarker.setMap(null);
        mapMarker = new google.maps.Marker({ position: e.latLng, map: mapPicker });
    });
}
</script>
<script async defer
    src="https://maps.googleapis.com/maps/api/js?key=<?= e($mapsKey) ?>&callback=initMap">
</script>
<?php endif; ?>

<?php
// See the note in _language-retry-forms.php — these cannot live inside the
// edit form, and the status card is inside it.
require __DIR__ . '/../../shared/_language-retry-forms.php';
?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
