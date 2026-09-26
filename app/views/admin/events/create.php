<?php
/**
 * Admin — create event form.
 * Variables: $pageTitle (string), $mapsKey (string), $errors (array)
 */
$mapsKey = $mapsKey ?? '';
$errors  = $errors  ?? [];

// Default map centre: Madrid, Surigao del Sur (approximate town centre)
$defaultLat = 9.2647;
$defaultLng = 125.9633;

ob_start();
?>

<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<div class="d-flex align-items-center justify-content-between mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item">
                <a href="<?= e(route('admin/events')) ?>"><?= e(t('admin_nav.events')) ?></a>
            </li>
            <li class="breadcrumb-item active"><?= e(t('admin_events.breadcrumb_create')) ?></li>
        </ol>
    </nav>
</div>

<form method="post"
      action="<?= e(route('admin/events')) ?>"
      enctype="multipart/form-data"
      novalidate>

    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="row g-4">

        <!-- ── Left column: main fields ─────────────────────────────────── -->
        <div class="col-lg-8">

            <!-- Title -->
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
                           class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                           value="<?= e(old('title')) ?>"
                           placeholder="<?= e(t('admin_events.field_title_ph')) ?>"
                           maxlength="500"
                           required>
                    <?php if (isset($errors['title'])): ?>
                    <div class="invalid-feedback"><?= e($errors['title']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-0">
                    <label for="description" class="form-label fw-semibold">
                        <?= e(t('admin_events.field_description')) ?> <span class="text-danger">*</span>
                    </label>
                    <textarea id="description"
                              name="description"
                              class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>"
                              rows="7"
                              placeholder="<?= e(t('admin_events.field_description_ph')) ?>"
                              required><?= e(old('description')) ?></textarea>
                    <?php if (isset($errors['description'])): ?>
                    <div class="invalid-feedback"><?= e($errors['description']) ?></div>
                    <?php endif; ?>
                    <div class="form-text"><?= e(t('admin_events.field_description_help')) ?></div>
                </div>
            </div>

            <!-- Manobo translation widget -->
            <?php
            $__mTitleVal  = '';
            $__mBodyVal   = '';
            $__mBodyName  = 'description_manobo';
            $__mBodyLabel = t('manobo_fields.label_description');
            $__mAudioPath = null;
            $__eTitleVal  = '';
            $__eBodyVal   = '';
            $__eBodyName  = 'description_en';
            $__eBodyLabel = t('manobo_fields.label_description');
            // Staff hear the post before a resident does — the only reliable
            // way to catch an abbreviation or a missing full stop that reads
            // fine but sounds wrong. Above the translation panels because it
            // is about the content just typed, not a translation of it.
            // See the note in the announcement create form.
            $__ipType   = 'event';
            $__ipSource = null;
            require __DIR__ . '/../../shared/_import-panel.php';

            $__vpSourceLang = $__eSourceLang ?? 'fil';
            $__vpType       = 'event';
            $__vpRow        = null;          // nothing saved yet — preview only
            require __DIR__ . '/../../shared/_voice-preview.php';

            require __DIR__ . '/../../shared/_english-fields.php';

            /* On the create form this is the only chance to catch a
               too-long post or a missing provider before the first save. */
            $tpTitleField = 'title';
            $tpBodyField  = 'description';
            $tpExisting   = 'fil';
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
                                   class="form-control flatpickr-datetime <?= isset($errors['event_date']) ? 'is-invalid' : '' ?>"
                                   placeholder="<?= e(t('admin_events.start_date_ph')) ?>"
                                   autocomplete="off"
                                   required>
                            <?php if (isset($errors['event_date'])): ?>
                            <div class="invalid-feedback"><?= e($errors['event_date']) ?></div>
                            <?php endif; ?>
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
                                   placeholder="<?= e(t('admin_events.end_date_ph')) ?>"
                                   autocomplete="off">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Venue -->
            <div class="admin-card mb-4">
                <h6 class="fw-semibold mb-3 text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.08em;">
                    <?= e(t('admin_events.section_venue')) ?>
                </h6>

                <div class="mb-0">
                    <label for="venue" class="form-label fw-semibold">
                        <?= e(t('admin_events.field_venue_name')) ?> <span class="text-muted fw-normal"><?= e(t('common.optional')) ?></span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                        <input type="text"
                               id="venue"
                               name="venue"
                               class="form-control"
                               value="<?= e(old('venue')) ?>"
                               placeholder="<?= e(t('admin_events.venue_ph')) ?>"
                               maxlength="255">
                    </div>
                    <div class="form-text"><?= e(t('admin_events.venue_help')) ?></div>
                </div>

                <!-- Hidden coords passed to controller -->
                <input type="hidden" name="latitude"  value="">
                <input type="hidden" name="longitude" value="">
            </div>

        </div><!-- /left -->

        <!-- ── Right column: status, cover, submit ───────────────────────── -->
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
                        $sel = (old('status', 'upcoming') === $val) ? 'selected' : '';
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
                <div x-data="coverPreview()" class="position-relative">
                    <label for="cover_image"
                           class="d-flex flex-column align-items-center justify-content-center gap-2
                                  border border-2 border-dashed rounded-3 p-4 text-center
                                  cursor-pointer text-secondary"
                           style="min-height:130px;background:#f8f9fa;cursor:pointer;"
                           @dragover.prevent
                           @drop.prevent="handleDrop($event)">
                        <template x-if="!preview">
                            <div>
                                <i class="bi bi-image fs-2 text-secondary opacity-50"></i>
                                <p class="small mb-0 mt-1"><?= e(t('admin_events.dropzone_text_simple')) ?></p>
                                <p class="small text-muted mb-0"><?= e(t('admin_events.dropzone_hint_jpg')) ?></p>
                            </div>
                        </template>
                        <template x-if="preview">
                            <img :src="preview" alt="Preview"
                                 class="rounded-2 w-100" style="max-height:120px;object-fit:cover;">
                        </template>
                        <input type="file"
                               id="cover_image"
                               name="cover_image"
                               accept="image/jpeg,image/png"
                               class="d-none"
                               @change="handleChange($event)">
                    </label>
                    <button x-show="preview" type="button"
                            @click="preview = null"
                            class="btn btn-sm btn-outline-danger position-absolute"
                            style="top:.5rem;right:.5rem;">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <?php require __DIR__ . '/../../shared/_sms-notify.php'; ?>

            <!-- Submit -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-barangay">
                    <i class="bi bi-calendar-plus me-1"></i> <?= e(t('admin_events.create_event_btn')) ?>
                </button>
                <a href="<?= e(route('admin/events')) ?>" class="btn btn-outline-secondary">
                    <?= e(t('common.cancel')) ?>
                </a>
            </div>

        </div><!-- /right -->

    </div><!-- /row -->

</form>

<!-- ── Page scripts ─────────────────────────────────────────────────────── -->

<!-- Flatpickr -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    flatpickr('.flatpickr-datetime', {
        enableTime:  true,
        dateFormat:  'Y-m-d H:i:00',
        altInput:    true,
        altFormat:   'F j, Y h:i K',
        time_24hr:   false,
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
    const defaultCenter = { lat: <?= $defaultLat ?>, lng: <?= $defaultLng ?> };
    const savedLat = parseFloat(document.getElementById('latitude').value);
    const savedLng = parseFloat(document.getElementById('longitude').value);
    const center = (savedLat && savedLng) ? { lat: savedLat, lng: savedLng } : defaultCenter;

    mapPicker = new google.maps.Map(document.getElementById('map-picker'), {
        center: center,
        zoom: 15,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: true,
    });

    if (savedLat && savedLng) {
        mapMarker = new google.maps.Marker({ position: center, map: mapPicker });
    }

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
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
