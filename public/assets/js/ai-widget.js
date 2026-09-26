/**
 * ai-widget.js — BarangGabay AI Chat Widget
 *
 * Alpine.js component factory. Expects window.BarangGabay.{csrfToken, baseUrl}
 * to be set by the layout before Alpine bootstraps.
 */

function aiChatWidget() {
    return {
        // ── State ───────────────────────────────────────────────────
        open:      false,
        question:  '',
        messages:  [],   // { id, role: 'ai'|'user', html }
        typing:    false,
        hasNewMsg: false,
        _id:          0,
        _initialized: false,

        // ── Lifecycle ────────────────────────────────────────────────

        init() {
            // Guard against Alpine invoking x-init more than once for this
            // component (observed: mutating the reactive `messages` array
            // during init's own synchronous run can make Alpine think a
            // dependency it read changed, re-firing x-init). Without this,
            // residents would see the greeting doubled and duplicate
            // 'ai-chat:open' listeners registered.
            if (this._initialized) return;
            this._initialized = true;

            // Seed the greeting as the first AI message
            this._push('ai', this._i18n().greeting);

            // External trigger: other pages (e.g. ordinance detail) can fire
            // new CustomEvent('ai-chat:open', { detail: { prefill: '...' } })
            // to open the widget with pre-filled text.
            document.addEventListener('ai-chat:open', (e) => {
                this.open      = true;
                this.hasNewMsg = false;
                if (e.detail && e.detail.prefill) {
                    this.question = e.detail.prefill;
                }
                this.$nextTick(() => {
                    this.$refs.input && this.$refs.input.focus();
                });
            });
        },

        // ── Toggle panel ─────────────────────────────────────────────

        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.hasNewMsg = false;
                this.$nextTick(() => {
                    this._scrollBottom();
                    this.$refs.input && this.$refs.input.focus();
                });
            }
        },

        // ── Send message ─────────────────────────────────────────────

        async sendMessage() {
            const q = this.question.trim();
            if (!q || this.typing || q.length > 500) return;

            this._push('user', q);
            this.question = '';
            this.typing   = true;
            this._scrollBottom();

            try {
                const BG   = window.BarangGabay || {};
                const base = (BG.baseUrl || '').replace(/\/$/, '');
                const fd   = new FormData();
                fd.append('question',   q);
                fd.append('csrf_token', BG.csrfToken || '');

                const res  = await fetch(base + '/api/ai/chat', { method: 'POST', body: fd });
                const data = await res.json();

                if (res.status === 429) {
                    this._push('ai', this._i18n().rateLimited);
                } else if (data.answer) {
                    this._push('ai', data.answer);
                } else {
                    this._push('ai', data.error || this._i18n().genericError);
                }
            } catch (err) {
                console.error('[AI widget]', err);
                this._push('ai', this._i18n().connectionError);
            } finally {
                this.typing = false;
                this._scrollBottom();
                // Show notification dot if panel is closed
                if (!this.open) this.hasNewMsg = true;
            }
        },

        // ── Helpers ──────────────────────────────────────────────────

        /** Locale-aware strings injected by the PHP widget partial. */
        _i18n() {
            return (window.BarangGabay && window.BarangGabay.aiChatI18n) || {
                greeting:        "Hi! I'm BarangGabay AI. How can I help you?",
                rateLimited:     "You've reached the hourly question limit. Please try again later.",
                genericError:    "Sorry, I couldn't answer that right now. Please try again.",
                connectionError: 'There was a connection problem. Please try again.',
            };
        },

        /** Sanitise text, convert newlines to <br>, push to messages[]. */
        _push(role, text) {
            const html = text
                .replace(/&/g,  '&amp;')
                .replace(/</g,  '&lt;')
                .replace(/>/g,  '&gt;')
                .replace(/\n/g, '<br>');
            this.messages.push({ id: ++this._id, role, html });
        },

        /** Scroll message container to the bottom after next DOM tick. */
        _scrollBottom() {
            this.$nextTick(() => {
                const el = this.$refs.messages;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        // ── Computed ─────────────────────────────────────────────────

        get charCount() {
            return this.question.length;
        },

        /** Inline colour style for the char counter. */
        get charColor() {
            const n = this.question.length;
            if (n > 480) return 'color:#dc3545;font-weight:700;';
            if (n > 400) return 'color:#d97706;';
            return 'color:#94a3b8;';
        },
    };
}
