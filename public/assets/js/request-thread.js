/**
 * Message thread on a document request — resident <-> barangay staff.
 *
 * Mount on any element with data-thread-url (GET lists, POST sends):
 *   BG_openThread(el)   loads the messages, shows the reply box, and checks
 *                       for new messages every 20 s while the thread is open.
 * Buttons with data-thread-toggle="<element id>" open and close a thread.
 *
 * Needs window.BG_THREAD = { csrf, words: { empty, placeholder, send, sending, error, you } }.
 * Times are <time class="js-live-time"> so live-time.js keeps their age current.
 */
(function () {
    'use strict';

    var cfg   = window.BG_THREAD || {};
    var words = cfg.words || {};
    var timers = new WeakMap();

    function injectStyle() {
        if (document.getElementById('bg-thread-style')) { return; }
        var css = ''
            + '.bg-thread{margin-top:.6rem;border:1px solid var(--border,#dfe7e1);border-radius:14px;padding:.75rem;background:var(--surface-muted,#f6faf7)}'
            + '.bg-thread__list{display:flex;flex-direction:column;gap:.5rem;max-height:320px;overflow-y:auto;padding:.15rem}'
            + '.bg-thread__msg{max-width:85%;padding:.5rem .7rem;border-radius:12px;font-size:.85rem;line-height:1.45;white-space:pre-wrap;word-break:break-word;'
            + 'background:var(--surface-card,#fff);border:1px solid var(--border,#dfe7e1);color:var(--text-primary,#1c2b1e)}'
            + '.bg-thread__msg.is-mine{align-self:flex-end;background:var(--action-solid,#1a6b3a);border-color:var(--action-solid,#1a6b3a);color:var(--text-on-action,#fff)}'
            + '.bg-thread__meta{display:block;font-size:.7rem;font-weight:700;opacity:.8;margin-bottom:.15rem}'
            + '.bg-thread__empty{font-size:.8rem;color:var(--text-muted,#5b6b60);text-align:center;padding:.5rem}'
            + '.bg-thread__form{display:flex;gap:.5rem;margin-top:.6rem;align-items:flex-end}'
            + '.bg-thread__form textarea{flex:1;min-height:42px;max-height:140px;resize:vertical;border:1px solid var(--border,#c9d6cc);border-radius:10px;'
            + 'padding:.5rem .65rem;font:inherit;font-size:.85rem;background:var(--surface-card,#fff);color:var(--text-primary,#1c2b1e)}'
            + '.bg-thread__form button{border:0;border-radius:10px;padding:.55rem .9rem;font-weight:700;font-size:.85rem;cursor:pointer;'
            + 'background:var(--action-solid,#1a6b3a);color:var(--text-on-action,#fff)}'
            + '.bg-thread__form button[disabled]{opacity:.6;cursor:wait}'
            + '.bg-thread__error{font-size:.78rem;color:var(--status-danger,#b42318);margin-top:.35rem}';
        var style = document.createElement('style');
        style.id = 'bg-thread-style';
        style.textContent = css;
        document.head.appendChild(style);
    }

    function build(el) {
        if (el.dataset.threadReady) { return; }
        el.dataset.threadReady = '1';
        el.classList.add('bg-thread');
        el.innerHTML = '<div class="bg-thread__list" role="log" aria-live="polite"></div>'
            + '<form class="bg-thread__form"><textarea maxlength="1000" required></textarea><button type="submit"></button></form>'
            + '<div class="bg-thread__error" hidden></div>';
        var ta  = el.querySelector('textarea');
        var btn = el.querySelector('button');
        ta.placeholder  = words.placeholder || '';
        ta.setAttribute('aria-label', words.placeholder || 'Message');
        btn.textContent = words.send || 'Send';

        el.querySelector('form').addEventListener('submit', function (e) {
            e.preventDefault();
            var text = ta.value.trim();
            if (!text) { return; }
            btn.disabled = true;
            btn.textContent = words.sending || '…';
            var body = new FormData();
            body.append('csrf_token', cfg.csrf || '');
            body.append('message', text);
            fetch(el.dataset.threadUrl, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
                .then(function (d) {
                    if (d && d.ok) { ta.value = ''; showError(el, ''); load(el, true); }
                    else { showError(el, (d && d.error) || words.error || 'Error'); }
                })
                .catch(function () { showError(el, words.error || 'Error'); })
                .then(function () { btn.disabled = false; btn.textContent = words.send || 'Send'; });
        });
        // Enter sends, Shift+Enter makes a new line.
        ta.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); el.querySelector('form').requestSubmit(); }
        });
    }

    function showError(el, msg) {
        var box = el.querySelector('.bg-thread__error');
        box.textContent = msg;
        box.hidden = !msg;
    }

    function render(el, messages, scroll) {
        var list = el.querySelector('.bg-thread__list');
        var atBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 40;
        list.textContent = '';
        if (!messages.length) {
            var empty = document.createElement('div');
            empty.className = 'bg-thread__empty';
            empty.textContent = words.empty || '';
            list.appendChild(empty);
            return;
        }
        messages.forEach(function (m) {
            var item = document.createElement('div');
            item.className = 'bg-thread__msg' + (m.mine ? ' is-mine' : '');
            var meta = document.createElement('span');
            meta.className = 'bg-thread__meta';
            meta.appendChild(document.createTextNode((m.mine ? (words.you || 'You') : m.name) + ' · '));
            var t = document.createElement('time');
            t.className = 'js-live-time';
            t.dateTime = m.time_iso;
            t.title = m.time;
            t.innerHTML = '<span data-rel></span>';
            t.firstChild.textContent = m.time;
            meta.appendChild(t);
            item.appendChild(meta);
            item.appendChild(document.createTextNode(m.message));
            list.appendChild(item);
        });
        if (window.BG_liveTimeRefresh) { window.BG_liveTimeRefresh(list); }
        if (scroll || atBottom) { list.scrollTop = list.scrollHeight; }
    }

    function load(el, scroll) {
        return fetch(el.dataset.threadUrl, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (d && d.ok) { render(el, d.messages || [], scroll); } })
            .catch(function () {});
    }

    function open(el) {
        injectStyle();
        build(el);
        el.hidden = false;
        load(el, true);
        clearInterval(timers.get(el));
        timers.set(el, setInterval(function () {
            if (!el.hidden && !document.hidden && el.offsetParent !== null) { load(el, false); }
        }, 20000));
    }

    function close(el) {
        el.hidden = true;
        clearInterval(timers.get(el));
    }

    window.BG_openThread  = open;
    window.BG_closeThread = close;

    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('[data-thread-toggle]');
        if (!btn) { return; }
        var el = document.getElementById(btn.getAttribute('data-thread-toggle'));
        if (!el) { return; }
        if (el.hidden) { open(el); btn.setAttribute('aria-expanded', 'true'); }
        else { close(el); btn.setAttribute('aria-expanded', 'false'); }
    });

    // ?thread=<id> (from a notification) opens that thread.
    var want = new URLSearchParams(location.search).get('thread');
    if (want) {
        document.addEventListener('DOMContentLoaded', function () {
            var el = document.getElementById('thread-' + want.replace(/\D/g, ''));
            if (el) { open(el); el.scrollIntoView({ block: 'center' }); }
        });
    }
})();
