/**
 * Live timestamps: keeps every <time class="js-live-time"> current.
 *
 * The server renders the exact date and time and a first "x minutes ago";
 * this keeps the "ago" part counting while the page stays open. Ages are
 * measured against the SERVER's clock (window.BG_LIVE_TIME.serverNow), so a
 * phone or PC with the wrong time still shows the right age. Elements added
 * later (the notification bell list, live-refreshed rows) are picked up too.
 */
(function () {
    'use strict';

    var cfg   = window.BG_LIVE_TIME || {};
    var words = cfg.words || {};
    // How far this device's clock is from the server's, measured once on load.
    var skew  = typeof cfg.serverNow === 'number' ? cfg.serverNow - Date.now() : 0;

    function say(key, n) {
        return String(words[key] || '').replace(':n', n);
    }

    /** Same thresholds and wording as relative_time() in app/helpers.php. */
    function ago(then) {
        var seconds = Math.floor((Date.now() + skew - then) / 1000);
        if (seconds < 60)     { return say('just_now'); }
        if (seconds < 3600)   { var m = Math.floor(seconds / 60);    return say(m === 1 ? 'minute_ago' : 'minutes_ago', m); }
        if (seconds < 86400)  { var h = Math.floor(seconds / 3600);  return say(h === 1 ? 'hour_ago' : 'hours_ago', h); }
        if (seconds < 604800) { var d = Math.floor(seconds / 86400); return say(d === 1 ? 'day_ago' : 'days_ago', d); }
        return '';   // older than a week: the date itself says more
    }

    function update(el) {
        var then = Date.parse(el.getAttribute('datetime') || '');
        if (isNaN(then)) { return; }
        var rel  = el.querySelector('[data-rel]');
        var wrap = el.querySelector('[data-rel-wrap]');
        var text = ago(then);
        if (wrap) {
            wrap.hidden = text === '';
        }
        if (rel) {
            // "relative" mode with nothing to say: show the exact date instead.
            rel.textContent = text !== '' ? text : (wrap ? '' : (el.getAttribute('title') || '').split(' · ')[0]);
        }
    }

    function updateAll(root) {
        var list = (root || document).querySelectorAll('time.js-live-time');
        for (var i = 0; i < list.length; i++) { update(list[i]); }
    }

    function start() {
        updateAll();
        // Every 30 s, and immediately when the tab comes back into view.
        setInterval(updateAll, 30000);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) { updateAll(); }
        });
        if ('MutationObserver' in window) {
            new MutationObserver(function (records) {
                for (var i = 0; i < records.length; i++) {
                    var added = records[i].addedNodes;
                    for (var j = 0; j < added.length; j++) {
                        var node = added[j];
                        if (node.nodeType !== 1) { continue; }
                        if (node.matches && node.matches('time.js-live-time')) { update(node); }
                        else if (node.querySelectorAll) { updateAll(node); }
                    }
                    if (records[i].type === 'attributes' && records[i].target.matches && records[i].target.matches('time.js-live-time')) {
                        update(records[i].target);
                    }
                }
            }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['datetime'] });
        }
    }

    window.BG_liveTimeRefresh = updateAll;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
