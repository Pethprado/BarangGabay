<?php
/**
 * Live updates for request / payment lists.
 *
 * Polls /api/documents/changes every 10 s. When something changed (a new
 * request, a confirmed GCash payment, a status update) the page reloads —
 * unless the user is typing or has a dialog open, in which case a small
 * "New updates" button appears instead, so no one loses what they were doing.
 */
?>
<div id="liveRefreshBar" role="status" aria-live="polite" hidden
     style="position:fixed;left:50%;bottom:22px;transform:translateX(-50%);z-index:2000;">
    <button type="button" onclick="location.reload()"
            style="border:0;border-radius:999px;padding:.6rem 1.1rem;font-weight:700;font-size:.85rem;color:#fff;background:#087a4b;box-shadow:0 8px 24px rgba(0,0,0,.2);cursor:pointer;">
        <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> New updates — refresh
    </button>
</div>
<script>
(function () {
    var url = <?= json_encode(route('api/documents/changes')) ?>;
    var sig = null;
    function busy() {
        var a = document.activeElement;
        var typing = a && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName) && a.type !== 'button' && a.type !== 'submit';
        var dialogOpen = document.querySelector('.modal.show, [aria-modal="true"]:not([hidden]), details[open] form');
        var wizardStarted = document.getElementById('sectionNewRequest') && !document.getElementById('sectionNewRequest').classList.contains('hidden')
            && document.querySelector('#wizardStep2:not(.hidden), #wizardStep3:not(.hidden), #wizardStep4:not(.hidden)');
        return typing || dialogOpen || wizardStarted;
    }
    function check() {
        if (document.hidden) { return; }
        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' }, cache: 'no-store' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d || !d.sig) { return; }
                if (sig === null) { sig = d.sig; return; }
                if (d.sig !== sig) {
                    if (busy()) { document.getElementById('liveRefreshBar').hidden = false; }
                    else { location.reload(); }
                }
            })
            .catch(function () {});
    }
    check();
    setInterval(check, 10000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) { check(); } });
})();
</script>
