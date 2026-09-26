/**
 * admin.js — BarangGabay Admin Panel Scripts
 *
 * Responsibilities:
 *  1. Form submit → button loading state
 *  2. File input → image preview
 *  3. Delete confirmation via inline confirm()
 *  4. Alert auto-dismiss after 5 s
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        /* ── 1. Button loading state on form submit ── */
        document.addEventListener('submit', function (e) {
            const form = e.target;
            if (form.dataset.noloading !== undefined) return;

            const btn = form.querySelector(
                'button[type="submit"]:not([data-noloading]), input[type="submit"]:not([data-noloading])'
            );
            if (!btn) return;

            btn.classList.add('btn-loading');
            btn.setAttribute('disabled', 'disabled');

            // Safety timeout: re-enable after 30 s in case the page never reloads
            setTimeout(function () {
                btn.classList.remove('btn-loading');
                btn.removeAttribute('disabled');
            }, 30000);
        });

        /* ── 2. File input → image / cover preview ── */
        document.querySelectorAll('[data-preview]').forEach(function (input) {
            const targetId = input.dataset.preview;
            const target   = document.getElementById(targetId);
            if (!target) return;

            input.addEventListener('change', function () {
                const file = this.files && this.files[0];
                if (!file || !file.type.startsWith('image/')) return;

                const reader = new FileReader();
                reader.onload = function (ev) {
                    // If the target is an <img>, set its src
                    if (target.tagName === 'IMG') {
                        target.src = ev.target.result;
                        target.style.display = 'block';
                    } else {
                        // Otherwise create/update an img inside the container
                        let img = target.querySelector('img.upload-preview-img');
                        if (!img) {
                            img = document.createElement('img');
                            img.className = 'upload-preview-img';
                            img.alt       = 'Preview';
                            target.appendChild(img);
                        }
                        img.src = ev.target.result;
                    }
                    target.classList.add('has-file');
                };
                reader.readAsDataURL(file);
            });
        });

        /* ── 3. Drag-and-drop zone highlighting ── */
        document.querySelectorAll('.drop-zone').forEach(function (zone) {
            const input = zone.querySelector('input[type="file"]');

            ['dragenter', 'dragover'].forEach(function (ev) {
                zone.addEventListener(ev, function (e) {
                    e.preventDefault();
                    zone.classList.add('drag-active');
                });
            });

            ['dragleave', 'drop'].forEach(function (ev) {
                zone.addEventListener(ev, function (e) {
                    e.preventDefault();
                    zone.classList.remove('drag-active');
                });
            });

            zone.addEventListener('drop', function (e) {
                if (!input) return;
                const dt = e.dataTransfer;
                if (dt && dt.files.length) {
                    // Transfer dropped files to the hidden file input
                    const transfer = new DataTransfer();
                    Array.from(dt.files).forEach(f => transfer.items.add(f));
                    input.files = transfer.files;
                    input.dispatchEvent(new Event('change'));
                }
            });
        });

        /* ── 4. Flash / alert auto-dismiss ── */
        document.querySelectorAll('.admin-flash .alert').forEach(function (alert) {
            setTimeout(function () {
                alert.style.transition = 'opacity .4s ease, max-height .4s ease';
                alert.style.opacity    = '0';
                alert.style.maxHeight  = '0';
                alert.style.overflow   = 'hidden';
                setTimeout(function () { alert.remove(); }, 420);
            }, 5000);
        });

        /* ── 5. Bulk-select "select all" helper ── */
        const selectAllBox = document.getElementById('select-all');
        if (selectAllBox) {
            selectAllBox.addEventListener('change', function () {
                document.querySelectorAll('.row-select-box').forEach(function (cb) {
                    cb.checked = selectAllBox.checked;
                });
            });
        }

        /* ── 6. Glass topbar on scroll (mirrors resident navbar-premium) ── */
        const topbar = document.querySelector('.admin-topbar');
        if (topbar) {
            const toggleGlass = function () {
                topbar.classList.toggle('scrolled', window.scrollY > 12);
            };
            toggleGlass();
            window.addEventListener('scroll', toggleGlass, { passive: true });
        }

        /* ── 7. Scroll-reveal for .fade-up elements ── */
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const fadeEls = document.querySelectorAll('.fade-up');
        if (fadeEls.length) {
            if (reduceMotion || typeof IntersectionObserver === 'undefined') {
                fadeEls.forEach(function (el) { el.classList.add('visible'); });
            } else {
                const revealObserver = new IntersectionObserver(function (entries, obs) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('visible');
                            obs.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.08 });
                fadeEls.forEach(function (el) { revealObserver.observe(el); });
            }
        }

        /* ── 8. Animated count-up for [data-countup] stat numbers ── */
        document.querySelectorAll('[data-countup]').forEach(function (el) {
            const target = parseInt(el.getAttribute('data-countup'), 10);
            if (isNaN(target)) return;

            if (reduceMotion) {
                el.textContent = target.toLocaleString('en-US');
                return;
            }

            const duration = 900;
            const start    = performance.now();

            function tick(now) {
                const progress = Math.min((now - start) / duration, 1);
                const eased    = 1 - Math.pow(1 - progress, 3); // ease-out-cubic
                el.textContent = Math.round(target * eased).toLocaleString('en-US');
                if (progress < 1) {
                    requestAnimationFrame(tick);
                } else {
                    el.textContent = target.toLocaleString('en-US');
                }
            }
            requestAnimationFrame(tick);
        });

    }); /* DOMContentLoaded */

}());
