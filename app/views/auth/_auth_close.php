<?php
/**
 * Split-screen authentication shell — closing half. See _auth_open.php.
 *
 * Shared behaviour for every auth form, so each page does not carry its own copy:
 *   [data-pw-toggle="inputId"]  show / hide a password field
 *   [data-theme-toggle]         dark / light switch (same 'bg-theme' key as the app)
 *   form[data-auth-form]        client-side checks + a loading, disabled submit button
 *
 * The client-side checks are UX only — every rule is enforced again on the server.
 */
?>
        </div><!-- /auth-panel__body -->
    </div><!-- /auth-panel -->
</main>
<script>
(function () {
    var i18n = {
        email:    <?= json_encode(t('auth_hero.err_email')) ?>,
        required: <?= json_encode(t('auth_hero.err_required')) ?>,
        show:     <?= json_encode(t('login.show_password')) ?>,
        hide:     <?= json_encode(t('login.hide_password')) ?>
    };

    // ── Theme toggle ────────────────────────────────────────────────
    function currentTheme() {
        var explicit = document.documentElement.getAttribute('data-theme');
        if (explicit) { return explicit; }
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    function paintThemeIcon() {
        document.querySelectorAll('[data-theme-toggle] i').forEach(function (icon) {
            icon.className = 'bi ' + (currentTheme() === 'dark' ? 'bi-sun-fill' : 'bi-moon-stars-fill');
        });
    }
    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var next = currentTheme() === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            try { localStorage.setItem('bg-theme', next); } catch (e) {}
            paintThemeIcon();
        });
    });
    paintThemeIcon();

    // ── Password visibility ─────────────────────────────────────────
    document.querySelectorAll('[data-pw-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-pw-toggle'));
            if (!input) { return; }
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.querySelector('i').className = 'bi ' + (show ? 'bi-eye-slash' : 'bi-eye');
            btn.setAttribute('aria-label', show ? i18n.hide : i18n.show);
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        });
    });

    // ── Validation + loading state ──────────────────────────────────
    var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

    function fieldError(input) {
        var value = (input.value || '').trim();
        if (input.type === 'checkbox') { return input.required && !input.checked ? (input.dataset.msgRequired || i18n.required) : ''; }
        if (input.required && value === '') { return input.dataset.msgRequired || i18n.required; }
        if (input.type === 'email' && value !== '' && !emailRe.test(value)) { return i18n.email; }
        return '';
    }
    function showError(input, message) {
        var id = input.id + '-error';
        var holder = document.getElementById(id);
        if (!holder) {
            holder = document.createElement('p');
            holder.id = id;
            holder.className = 'auth-error';
            holder.setAttribute('role', 'alert');
            var anchor = input.closest('.auth-input-wrap, .auth-check, .input-group') || input;
            anchor.insertAdjacentElement('afterend', holder);
        }
        holder.textContent = message;
        holder.hidden = message === '';
        input.setAttribute('aria-invalid', message ? 'true' : 'false');
        var described = (input.getAttribute('aria-describedby') || '').split(' ').filter(Boolean);
        if (message && described.indexOf(id) === -1) { described.push(id); }
        input.setAttribute('aria-describedby', described.join(' '));
        input.classList.toggle('is-invalid', message !== '');
    }

    document.querySelectorAll('form[data-auth-form]').forEach(function (form) {
        var checked = form.querySelectorAll('[data-validate]');
        checked.forEach(function (input) {
            input.addEventListener('blur', function () { if (input.value !== '') { showError(input, fieldError(input)); } });
            input.addEventListener('input', function () { if (input.getAttribute('aria-invalid') === 'true') { showError(input, fieldError(input)); } });
            input.addEventListener('change', function () { if (input.type === 'checkbox') { showError(input, fieldError(input)); } });
        });

        form.addEventListener('submit', function (event) {
            var firstBad = null;
            checked.forEach(function (input) {
                var message = fieldError(input);
                showError(input, message);
                if (message && !firstBad) { firstBad = input; }
            });
            var extra = typeof form.authExtraCheck === 'function' ? form.authExtraCheck() : null;
            if (extra && !firstBad) { firstBad = extra; }
            if (firstBad) {
                event.preventDefault();
                firstBad.focus();
                return;
            }
            var btn = form.querySelector('button[type="submit"]');
            if (btn && btn.dataset.loadingText) {
                // Deferred so the browser still sends the form before the button is disabled.
                setTimeout(function () {
                    btn.disabled = true;
                    btn.setAttribute('aria-busy', 'true');
                    btn.innerHTML = '<span class="auth-spinner" aria-hidden="true"></span>' + btn.dataset.loadingText;
                }, 0);
            }
        });
    });

    // A page restored from the back/forward cache must not keep a spinning, disabled button.
    window.addEventListener('pageshow', function (event) {
        if (!event.persisted) { return; }
        document.querySelectorAll('button[aria-busy="true"]').forEach(function (btn) { window.location.reload(); });
    });
})();
</script>
</body>
</html>
