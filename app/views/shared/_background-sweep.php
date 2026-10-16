<?php
/**
 * _background-sweep.php — asks the server to retry unfinished translations,
 * AFTER this page has loaded, so no one waits for it.
 *
 * Rendered only when public/index.php found a sweep due for this session.
 * See MaintenanceController::translationSweep().
 */
$__hazardDue = !empty($GLOBALS['bg_hazard_sweep_due']);
if ($__hazardDue): ?>
<script>
window.addEventListener('load', function () {
    setTimeout(function () {
        var bg = window.BarangGabay || {};
        fetch((bg.baseUrl || '').replace(/\/$/, '') + '/api/hazards/sweep', {
            method: 'POST', body: new URLSearchParams({ csrf_token: bg.csrfToken || '' }),
            credentials: 'same-origin', keepalive: true
        }).catch(function () {});
    }, 2500);
});
</script>
<?php endif;

if (empty($GLOBALS['bg_translation_sweep_due'])) {
    return;
}
?>
<script>
window.addEventListener('load', function () {
    setTimeout(function () {
        var bg   = window.BarangGabay || {};
        var base = (bg.baseUrl || '').replace(/\/$/, '');
        fetch(base + '/api/translations/sweep', {
            method: 'POST',
            body: new URLSearchParams({ csrf_token: bg.csrfToken || '' }),
            credentials: 'same-origin',
            keepalive: true
        }).catch(function () {});
    }, 1500);
});
</script>
