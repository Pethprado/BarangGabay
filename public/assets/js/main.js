/**
 * main.js — BarangGabay resident layout scripts
 *
 * Responsibilities:
 *  1. Notification bell badge — poll /api/notifications/unread every 60 s
 *  2. Toast trigger          — dispatch 'bg:toast' when new notifications arrive
 *
 * Depends on window.BarangGabay.{baseUrl, csrfToken} set by the layout.
 */

(function () {
    'use strict';

    /* Guard: only run when the user is logged in (BarangGabay global is present). */
    if (typeof window.BarangGabay === 'undefined') return;

    const POLL_INTERVAL_MS = 60_000;

    let lastCount   = -1;   // -1 = not yet initialised
    let initialized = false;

    /* ── Badge helpers ─────────────────────────────────────────── */

    function updateBadge(count) {
        /* Desktop bell badge */
        const badge = document.getElementById('notif-badge');
        if (badge) {
            if (count > 0) {
                badge.textContent  = count > 99 ? '99+' : count;
                badge.style.display = 'inline-flex';
            } else {
                badge.style.display = 'none';
            }
        }

        /* Mobile nav badge */
        const badgeMobile = document.getElementById('notif-badge-mobile');
        if (badgeMobile) {
            badgeMobile.textContent  = count > 0 ? ' (' + count + ')' : '';
        }
    }

    /* ── Toast helper ───────────────────────────────────────────── */

    function showToast(message) {
        /*
         * The toastManager() Alpine component in main.php listens for 'bg:toast'.
         * We wait until Alpine has likely booted (polling only fires after 60 s
         * on first real interval, so this race doesn't occur in practice).
         */
        document.dispatchEvent(
            new CustomEvent('bg:toast', { detail: { message: message } })
        );
    }

    /* ── Polling ────────────────────────────────────────────────── */

    async function pollUnread() {
        try {
            const base = (window.BarangGabay.baseUrl || '').replace(/\/$/, '');
            const res  = await fetch(base + '/api/notifications/unread', {
                cache: 'no-store',
            });
            if (!res.ok) return;

            const data  = await res.json();
            const count = parseInt(data.count, 10) || 0;

            updateBadge(count);

            /* Only show the toast on subsequent polls when the count grows. */
            if (initialized && count > lastCount && lastCount >= 0) {
                showToast('🔔 May bagong abiso ka!');
            }

            lastCount   = count;
            initialized = true;
        } catch (_) {
            /* Swallow network errors — polling is non-critical. */
        }
    }

    /* ── Boot ──────────────────────────────────────────────────── */

    document.addEventListener('DOMContentLoaded', function () {
        pollUnread();
        setInterval(pollUnread, POLL_INTERVAL_MS);
        wireFormLoadingStates();
        wireImagePreviews();
    });

    /* ── Button loading state on form submit ── */
    function wireFormLoadingStates() {
        document.addEventListener('submit', function (e) {
            const form = e.target;
            if (form.dataset.noloading !== undefined) return;
            const btn = form.querySelector('button[type="submit"]:not([data-noloading])');
            if (!btn) return;
            btn.classList.add('btn-loading');
            btn.setAttribute('disabled', 'disabled');
            setTimeout(function () {
                btn.classList.remove('btn-loading');
                btn.removeAttribute('disabled');
            }, 30000);
        });
    }

    /* ── File input → image preview ── */
    function wireImagePreviews() {
        document.querySelectorAll('[data-preview]').forEach(function (input) {
            const target = document.getElementById(input.dataset.preview);
            if (!target) return;
            input.addEventListener('change', function () {
                const file = this.files && this.files[0];
                if (!file || !file.type.startsWith('image/')) return;
                const reader = new FileReader();
                reader.onload = function (ev) {
                    if (target.tagName === 'IMG') {
                        target.src = ev.target.result;
                        target.style.display = 'block';
                    } else {
                        let img = target.querySelector('img.upload-preview');
                        if (!img) {
                            img = document.createElement('img');
                            img.className = 'upload-preview';
                            img.alt       = 'Preview';
                            target.appendChild(img);
                        }
                        img.src = ev.target.result;
                    }
                };
                reader.readAsDataURL(file);
            });
        });
    }

}());
