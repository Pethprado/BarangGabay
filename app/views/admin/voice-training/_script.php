<?php
/**
 * Voice Training — Recording Script.
 *
 * Every announcement, event and ordinance residents can see, sentence by
 * sentence and word by word (numbers included), exactly as the Voice Reader
 * speaks it, with what is already recorded. Loaded on demand from
 * GET /admin/voice-training/script (App\Services\VoiceScript), so the hub
 * page stays fast. Record buttons open the hub's own recorder; "Record this
 * post" walks its missing sentences and words with Save & Record Next.
 *
 * Back office: English only.
 */
?>
<div class="vt-explorer-card" id="script">
    <div class="vt-explorer-header">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <h3 class="h5 font-extrabold vt-text-primary mb-1 d-flex align-items-center gap-2">
                    <i class="bi bi-journal-text" style="color:#c8992e;"></i><span>Recording Script — every word and sentence in your posts</span>
                </h3>
                <p class="text-xs vt-text-muted mb-0">
                    All announcements, events and ordinances residents can see, exactly as the Voice Reader says them, numbers included.
                    Record a whole <strong>sentence</strong> for the clearest reading (it plays as one clip);
                    record the single <strong>words</strong> so any sentence can be read. Green = recorded.
                </p>
            </div>
            <div class="d-flex gap-2 align-items-start flex-wrap">
                <label for="scriptLang" class="visually-hidden">Language</label>
                <select id="scriptLang" class="form-select form-select-sm vt-select" style="width:auto;">
                    <option value="msm">Manobo (+ Bisaya words)</option>
                    <option value="fil">Filipino</option>
                    <option value="en">English</option>
                </select>
                <button type="button" class="btn-vt-gold" id="scriptLoad"><i class="bi bi-arrow-repeat me-1"></i> Load script</button>
            </div>
        </div>
    </div>

    <div id="scriptBody" class="p-3">
        <p class="text-xs vt-text-muted mb-0"><span class="spinner-border spinner-border-sm me-1"></span> Reading every post…</p>
    </div>
</div>

<style>
.vs-chip { display:inline-flex; align-items:center; gap:4px; padding:3px 9px; margin:0 6px 6px 0; border-radius:999px; font-size:.78rem; font-weight:600; border:1px solid var(--border); background:var(--surface-card); color:var(--text-primary); cursor:pointer; }
.vs-chip.is-rec { background:var(--status-success-bg); color:var(--status-success); border-color:transparent; cursor:default; }
.vs-chip .vs-lang { font-size:.62rem; text-transform:uppercase; opacity:.75; }
.vs-chip.is-num { font-family:ui-monospace,monospace; }
.vs-sent { display:flex; gap:10px; align-items:flex-start; padding:8px 0; border-bottom:1px dashed var(--border); }
.vs-sent:last-child { border-bottom:0; }
.vs-sent-text { flex:1; font-size:.86rem; color:var(--text-primary); }
.vs-badge { font-size:.68rem; font-weight:700; padding:2px 8px; border-radius:999px; white-space:nowrap; }
.vs-ok { background:var(--status-success-bg); color:var(--status-success); }
.vs-part { background:var(--status-warning-bg); color:var(--status-warning); }
.vs-post { border:1px solid var(--border); border-radius:12px; margin-bottom:12px; background:var(--surface-card); }
.vs-post > summary { cursor:pointer; padding:10px 14px; display:flex; gap:10px; align-items:center; flex-wrap:wrap; list-style:none; }
.vs-post > summary::-webkit-details-marker { display:none; }
.vs-post-body { padding:4px 14px 14px; }
</style>

<script>
(function () {
    var URL_ = <?= json_encode(route('admin/voice-training/script')) ?>;
    var NAMES = { msm: 'Manobo', ceb: 'Bisaya', fil: 'Filipino', en: 'English' };
    var TYPE_ICON = { announcement: 'bi-megaphone', event: 'bi-calendar-event', ordinance: 'bi-file-earmark-text' };
    var state = { data: null, missingOnly: true, view: 'posts' };

    function el(tag, cls, text) { var n = document.createElement(tag); if (cls) { n.className = cls; } if (text != null) { n.textContent = text; } return n; }

    function record(lang, text) {
        if (window.recordFromQueue) { window.recordFromQueue(lang, text); }
        else if (window.openRecorder) { window.openRecorder({ language: lang, text: text }); }
    }

    function chip(w) {
        var c = el('button', 'vs-chip' + (w.recorded ? ' is-rec' : '') + (w.is_number ? ' is-num' : ''));
        c.type = 'button';
        c.appendChild(el('span', null, w.word));
        if (w.language === 'ceb') { c.appendChild(el('span', 'vs-lang', 'Bisaya')); }
        if (w.recorded) { c.title = 'Recorded'; c.disabled = true; c.insertBefore(el('i', 'bi bi-check2'), c.firstChild); }
        else { c.title = 'Record "' + w.word + '"'; c.insertBefore(el('i', 'bi bi-mic'), c.firstChild); c.onclick = function () { record(w.language, w.word); }; }
        return c;
    }

    function missingItems(post, lang) {
        var items = [];
        post.sentences.forEach(function (s) { if (!s.covered) { items.push({ text: s.text, language: lang }); } });
        post.words.forEach(function (w) { if (!w.recorded) { items.push({ text: w.word, language: w.language }); } });
        return items;
    }

    function renderPosts(box, d) {
        d.posts.forEach(function (post) {
            var det = el('details', 'vs-post');
            var sum = el('summary');
            sum.appendChild(el('i', 'bi ' + (TYPE_ICON[post.type] || 'bi-file-text')));
            sum.appendChild(el('strong', 'vt-text-primary', post.title));
            sum.appendChild(el('span', 'text-xs vt-text-muted', post.type + ' · ' + post.sentences.length + ' sentences · ' + post.words.length + ' words'));
            sum.appendChild(el('span', 'vs-badge ' + (post.missing ? 'vs-part' : 'vs-ok'), post.missing ? post.missing + ' words missing' : 'All words recorded'));
            det.appendChild(sum);

            var body = el('div', 'vs-post-body');
            var items = missingItems(post, d.locale);
            if (items.length) {
                var all = el('button', 'btn-vt-gold mb-2');
                all.type = 'button';
                all.innerHTML = '<i class="bi bi-mic-fill me-1"></i> Record this post one by one (' + items.length + ')';
                all.onclick = function () { window.recordScriptQueue(d.locale, items); };
                body.appendChild(all);
            }

            body.appendChild(el('div', 'text-xs font-bold vt-text-muted mt-1 mb-1', 'SENTENCES'));
            post.sentences.forEach(function (s) {
                if (state.missingOnly && s.covered) { return; }
                var row = el('div', 'vs-sent');
                row.appendChild(el('div', 'vs-sent-text', s.text));
                row.appendChild(el('span', 'vs-badge ' + (s.whole ? 'vs-ok' : (s.covered ? 'vs-ok' : 'vs-part')),
                    s.whole ? 'Recorded as sentence' : (s.covered ? 'All words recorded' : s.recorded + '/' + s.words + ' words')));
                if (!s.whole) {
                    var b = el('button', 'btn-vt-outline');
                    b.type = 'button'; b.style.padding = '3px 10px';
                    b.innerHTML = '<i class="bi bi-mic"></i> Sentence';
                    b.title = 'Record this whole sentence as one clip';
                    b.onclick = function () { record(d.locale, s.text); };
                    row.appendChild(b);
                }
                body.appendChild(row);
            });

            body.appendChild(el('div', 'text-xs font-bold vt-text-muted mt-3 mb-1', 'WORDS AND NUMBERS'));
            var wrap = el('div');
            post.words.forEach(function (w) { if (!(state.missingOnly && w.recorded)) { wrap.appendChild(chip(w)); } });
            if (!wrap.childNodes.length) { wrap.appendChild(el('span', 'text-xs vt-text-muted', 'Every word in this post is recorded.')); }
            body.appendChild(wrap);
            det.appendChild(body);
            box.appendChild(det);
        });
    }

    function renderWords(box, d) {
        var list = d.words.filter(function (w) { return !(state.missingOnly && w.recorded); });
        var miss = d.words.filter(function (w) { return !w.recorded; });
        if (miss.length) {
            var all = el('button', 'btn-vt-gold mb-2');
            all.type = 'button';
            all.innerHTML = '<i class="bi bi-mic-fill me-1"></i> Record all missing words one by one (' + miss.length + ')';
            all.onclick = function () { window.recordScriptQueue(d.locale, miss.map(function (w) { return { text: w.word, language: w.language }; })); };
            box.appendChild(all);
        }
        box.appendChild(el('p', 'text-xs vt-text-muted mb-2', 'Most used first. The number after a word is how many times residents meet it.'));
        var wrap = el('div');
        list.forEach(function (w) { var c = chip(w); c.appendChild(el('span', 'vs-lang', '×' + w.count)); wrap.appendChild(c); });
        if (!list.length) { wrap.appendChild(el('span', 'text-xs vt-text-muted', 'Every word is recorded.')); }
        box.appendChild(wrap);
    }

    function render() {
        var d = state.data, box = document.getElementById('scriptBody');
        box.innerHTML = '';
        if (!d) { return; }
        var t = d.totals;
        var stats = el('div', 'd-flex flex-wrap gap-2 mb-3');
        [['Posts', t.posts], ['Sentences', t.sentences], ['Sentences ready', t.sentences_covered + ' / ' + t.sentences],
         ['Words', t.words], ['Words recorded', t.words_recorded + ' / ' + t.words], ['Numbers', t.numbers]].forEach(function (s) {
            var c = el('span', 'vt-badge vt-badge-neutral'); c.textContent = s[0] + ': ' + s[1]; stats.appendChild(c);
        });
        box.appendChild(stats);

        var bar = el('div', 'd-flex flex-wrap gap-2 align-items-center mb-3');
        [['posts', 'By post'], ['words', 'All words (' + t.words + ')']].forEach(function (v) {
            var b = el('button', state.view === v[0] ? 'btn-vt-gold' : 'btn-vt-outline', v[1]);
            b.type = 'button'; b.onclick = function () { state.view = v[0]; render(); };
            bar.appendChild(b);
        });
        var lab = el('label', 'text-xs vt-text-secondary d-inline-flex align-items-center gap-1 ms-2');
        var cb = document.createElement('input'); cb.type = 'checkbox'; cb.checked = state.missingOnly;
        cb.onchange = function () { state.missingOnly = cb.checked; render(); };
        lab.append(cb, document.createTextNode('Show missing only'));
        bar.appendChild(lab);
        box.appendChild(bar);

        if (!d.posts.length) { box.appendChild(el('p', 'text-xs vt-text-muted', 'No published posts have ' + NAMES[d.locale] + ' text yet.')); return; }
        (state.view === 'words' ? renderWords : renderPosts)(box, d);
    }

    function load() {
        var lang = document.getElementById('scriptLang').value;
        try { sessionStorage.setItem('vt_script_lang', lang); } catch (e) {}
        var box = document.getElementById('scriptBody');
        box.innerHTML = '<p class="text-xs vt-text-muted mb-0"><span class="spinner-border spinner-border-sm me-1"></span> Reading every post…</p>';
        fetch(URL_ + '?lang=' + encodeURIComponent(lang), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); })
            .then(function (d) { state.data = d; render(); })
            .catch(function () { box.innerHTML = '<p class="text-xs mb-0" style="color:var(--status-danger);">Could not load the script. Please try again.</p>'; });
    }

    document.getElementById('scriptLoad').addEventListener('click', load);
    document.getElementById('scriptLang').addEventListener('change', load);
    /** Card buttons: open the script in a language and scroll to it. */
    window.openScript = function (lang) {
        document.getElementById('scriptLang').value = lang;
        load();
        document.getElementById('script').scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    // Opens by itself (Manobo first); after a save it reopens where it was.
    var saved = null;
    try { saved = sessionStorage.getItem('vt_script_lang'); } catch (e) {}
    document.getElementById('scriptLang').value = saved || 'msm';
    load();
})();
</script>
