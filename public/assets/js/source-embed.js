/**
 * source-embed.js — draws the original social media post under a notice.
 *
 * Paired with app/views/shared/_source-embed.php. Three jobs:
 *
 *   1. Facebook. Loads Facebook's SDK — only on a page that actually has a
 *      Facebook embed — and asks it to draw each post at the width of the
 *      column, so the post looks the way it does on Facebook and its videos
 *      play here.
 *   2. Height. Instagram and X embeds report their own height by postMessage;
 *      this fits their frames to it, so a post is neither cropped nor sitting
 *      in a tall empty box.
 *   3. Late arrivals. The admin form drops a preview into the page after a
 *      link is pasted; SourceEmbed.mount(element) draws it.
 *
 * Messages are only believed from the platform that sent them, and only for a
 * frame on this page that came from that platform: a message can change a
 * frame's height, nothing else.
 */
(function () {
    'use strict';

    if (window.SourceEmbed) { return; }

    var script   = document.currentScript;
    var fbLocale = (script && script.getAttribute('data-fb-locale')) || 'en_US';
    var fbState  = 'idle';          // idle → loading → ready
    var fbQueue  = [];

    /** Load the Facebook SDK once, then run everything that waited for it. */
    function withFacebook(callback) {
        if (fbState === 'ready' && window.FB) { callback(); return; }
        fbQueue.push(callback);
        if (fbState === 'loading') { return; }
        fbState = 'loading';

        if (!document.getElementById('fb-root')) {
            var root = document.createElement('div');
            root.id = 'fb-root';
            document.body.insertBefore(root, document.body.firstChild);
        }

        window.fbAsyncInit = function () {
            window.FB.init({ xfbml: false, version: 'v21.0' });
            fbState = 'ready';
            var waiting = fbQueue.splice(0);
            waiting.forEach(function (fn) { try { fn(); } catch (e) { /* one bad embed must not stop the rest */ } });
        };

        var sdk = document.createElement('script');
        sdk.async = true;
        sdk.defer = true;
        sdk.crossOrigin = 'anonymous';
        sdk.src = 'https://connect.facebook.net/' + fbLocale + '/sdk.js';
        // Blocked by a tracker blocker or no network: leave the "view original"
        // card as the way through, and say why the box is empty.
        sdk.onerror = function () {
            fbState = 'idle';
            document.querySelectorAll('.source-embed-fb').forEach(function (box) {
                box.classList.add('is-failed');
            });
        };
        document.head.appendChild(sdk);
    }

    /**
     * Facebook's post plugin accepts 350–750px. Narrower than 350 (a small
     * phone) it is drawn at 350 and the box scrolls rather than cropping.
     */
    function sizeFacebook(el) {
        var box   = el.closest('.source-embed-fb') || el.parentElement;
        var width = Math.floor((box && box.clientWidth) || 500);
        var isVideo = el.classList.contains('fb-video');
        var min   = isVideo ? 220 : 260;
        el.setAttribute('data-width', String(Math.max(min, Math.min(750, width))));
    }

    /** Draw every embed inside `root` (default: the whole page). */
    function mount(root) {
        root = root || document;

        var posts = root.querySelectorAll('.fb-post[data-href], .fb-video[data-href]');
        if (posts.length) {
            posts.forEach(sizeFacebook);
            withFacebook(function () {
                if (root === document) {
                    window.FB.XFBML.parse();
                } else {
                    window.FB.XFBML.parse(root);
                }
            });
        }
    }

    // ── Heights reported by the platforms ────────────────────────────────

    function frameFor(source, platform) {
        var frames = document.querySelectorAll('iframe[data-autosize="' + platform + '"]');
        for (var i = 0; i < frames.length; i++) {
            if (frames[i].contentWindow === source) { return frames[i]; }
        }
        return null;
    }

    function setHeight(frame, height) {
        height = Math.round(Number(height));
        if (!frame || !isFinite(height) || height < 80 || height > 4000) { return; }
        frame.style.height = height + 'px';
        if (frame.parentElement) { frame.parentElement.classList.add('is-sized'); }
    }

    window.addEventListener('message', function (event) {
        var data = event.data;

        if (event.origin === 'https://www.instagram.com') {
            if (typeof data === 'string') {
                try { data = JSON.parse(data); } catch (e) { return; }
            }
            if (data && data.type === 'MEASURE' && data.details) {
                setHeight(frameFor(event.source, 'instagram'), data.details.height);
            }
            return;
        }

        if (event.origin === 'https://platform.twitter.com') {
            if (typeof data === 'string') {
                try { data = JSON.parse(data); } catch (e) { return; }
            }
            var embed = data && data['twttr.embed'];
            if (embed && embed.method === 'twttr.private.resize' && embed.params && embed.params[0]) {
                setHeight(frameFor(event.source, 'x'), embed.params[0].height);
            }
        }
    });

    window.SourceEmbed = { mount: mount };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { mount(document); });
    } else {
        mount(document);
    }
})();
