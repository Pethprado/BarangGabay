<!-- ── BarangGabay AI Chat Widget ──────────────────────────────────────── -->
<style>
/* ── Root ── */
.aic-root {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    z-index: 9000;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: .75rem;
}

/* ── FAB toggle button ── */
.aic-fab {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    background: var(--brand-primary);
    color: #fff;
    border: none;
    border-radius: 9999px;
    padding: .7rem 1.125rem .7rem .8rem;
    font-size: .875rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 4px 24px rgba(22,82,240,.4), 0 1px 4px rgba(0,0,0,.12);
    transition: background .18s, box-shadow .18s, transform .15s;
    position: relative;
    user-select: none;
    letter-spacing: .01em;
}
.aic-fab:hover  { background: var(--brand-primary-dark); box-shadow: 0 6px 32px rgba(22,82,240,.5); transform: translateY(-1px); }
.aic-fab:active { transform: scale(.97); }
.aic-fab:focus-visible { outline: 3px solid var(--brand-secondary); outline-offset: 3px; }

.aic-fab-icon {
    width: 1.875rem;
    height: 1.875rem;
    background: var(--brand-secondary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .95rem;
    flex-shrink: 0;
    transition: transform .3s cubic-bezier(.4,0,.2,1);
}
.aic-fab:hover .aic-fab-icon { transform: rotate(8deg) scale(1.08); }

.aic-fab-label { line-height: 1; }

.aic-fab-chevron {
    font-size: .65rem;
    opacity: .7;
    margin-left: .125rem;
    transition: transform .2s;
}
.aic-fab[aria-expanded="true"] .aic-fab-chevron { transform: rotate(180deg); }

/* Notification dot (pulses when new AI reply arrives while closed) */
.aic-dot {
    position: absolute;
    top: -3px;
    right: -3px;
    width: 13px;
    height: 13px;
    background: var(--brand-secondary);
    border-radius: 50%;
    border: 2.5px solid var(--surface-card);
    animation: aic-dot-pulse 1.6s ease-in-out infinite;
}
@keyframes aic-dot-pulse {
    0%, 100% { transform: scale(1);    opacity: 1; }
    50%       { transform: scale(1.3); opacity: .7; }
}

/* ── Chat panel ── */
.aic-panel {
    width: 380px;
    height: 520px;
    max-width: calc(100vw - 2rem);
    max-height: calc(100vh - 7rem);
    background: var(--surface-card);
    border-radius: 1.125rem;
    box-shadow: 0 24px 80px rgba(15,40,90,.18), 0 4px 16px rgba(15,40,90,.07);
    border: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transform-origin: bottom right;
}

/* ── Header ── */
.aic-header {
    flex-shrink: 0;
    background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-primary-dark) 100%);
    padding: .875rem 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
}
.aic-header-left { display: flex; align-items: center; gap: .625rem; min-width: 0; }

.aic-header-avatar {
    position: relative;
    width: 2.25rem;
    height: 2.25rem;
    background: var(--brand-secondary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
    box-shadow: 0 2px 10px rgba(0,0,0,.22);
}
/* Soft glow ring around header avatar */
.aic-header-avatar::after {
    content: '';
    position: absolute;
    inset: -3px;
    border-radius: 50%;
    border: 1.5px solid rgba(13,148,136,.55);
    animation: aic-ring 2.4s ease-in-out infinite;
}
@keyframes aic-ring { 0%,100%{opacity:.8} 50%{opacity:.2} }

.aic-header-info { min-width: 0; }
.aic-header-title {
    margin: 0;
    font-size: .9rem;
    font-weight: 700;
    color: #fff;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.aic-header-sub {
    margin: 2px 0 0;
    font-size: .7rem;
    color: rgba(255,255,255,.7);
    display: flex;
    align-items: center;
    gap: 4px;
    line-height: 1;
}
.aic-online-dot {
    width: 7px;
    height: 7px;
    background: #4ade80;
    border-radius: 50%;
    flex-shrink: 0;
    box-shadow: 0 0 6px rgba(74,222,128,.9);
    animation: aic-online 2.5s ease-in-out infinite;
}
@keyframes aic-online { 0%,100%{opacity:1} 60%{opacity:.5} }

.aic-close {
    background: none;
    border: none;
    color: rgba(255,255,255,.7);
    cursor: pointer;
    padding: .3rem .35rem;
    border-radius: .4rem;
    line-height: 1;
    font-size: 1rem;
    transition: all .15s;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.aic-close:hover { color: #fff; background: rgba(255,255,255,.16); }

/* ── Messages ── */
.aic-messages {
    flex: 1;
    overflow-y: auto;
    padding: .875rem 1rem;
    display: flex;
    flex-direction: column;
    gap: .625rem;
    background: var(--surface-muted);
    scroll-behavior: smooth;
}
.aic-messages::-webkit-scrollbar       { width: 4px; }
.aic-messages::-webkit-scrollbar-track { background: transparent; }
.aic-messages::-webkit-scrollbar-thumb { background: rgba(22,82,240,.22); border-radius: 4px; }
.aic-messages::-webkit-scrollbar-thumb:hover { background: rgba(22,82,240,.38); }

/* ── Message rows ── */
.aic-msg        { display: flex; gap: .5rem; align-items: flex-end; max-width: 90%; }
.aic-msg-ai     { align-self: flex-start; }
.aic-msg-user   { align-self: flex-end;   flex-direction: row-reverse; }

.aic-avatar {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .75rem;
    flex-shrink: 0;
    line-height: 1;
}
.aic-msg-ai   .aic-avatar { background: var(--brand-secondary-light); color: var(--brand-secondary); }
.aic-msg-user .aic-avatar { background: var(--brand-primary); color: #fff; }

.aic-bubble {
    padding: .6rem .9rem;
    border-radius: 1.125rem;
    font-size: .82rem;
    line-height: 1.58;
    word-break: break-word;
}
.aic-msg-ai   .aic-bubble {
    background: var(--surface-card);
    border: 1px solid var(--border);
    border-bottom-left-radius: .3rem;
    color: var(--text-primary);
    box-shadow: 0 1px 4px rgba(0,0,0,.07);
}
.aic-msg-user .aic-bubble {
    background: var(--brand-primary);
    color: #fff;
    border-bottom-right-radius: .3rem;
}

/* ── Typing indicator ── */
.aic-typing-dots {
    display: flex;
    gap: .3rem;
    align-items: center;
    padding: .15rem 0;
}
.aic-typing-dots span {
    width: .42rem;
    height: .42rem;
    background: var(--text-muted);
    border-radius: 50%;
    animation: aic-bounce .9s ease-in-out infinite;
}
.aic-typing-dots span:nth-child(2) { animation-delay: .18s; }
.aic-typing-dots span:nth-child(3) { animation-delay: .36s; }
@keyframes aic-bounce {
    0%, 60%, 100% { transform: translateY(0);       opacity: .6; }
    30%            { transform: translateY(-.38rem); opacity: 1;  }
}

/* ── Input area ── */
.aic-input-wrap {
    flex-shrink: 0;
    background: var(--surface-card);
    border-top: 1px solid var(--border);
    padding: .625rem .75rem .5rem;
}
.aic-input-row {
    display: flex;
    gap: .5rem;
    align-items: flex-end;
}
.aic-textarea {
    flex: 1;
    border: 1.5px solid var(--border);
    border-radius: .75rem;
    padding: .525rem .8rem;
    font-size: .82rem;
    resize: none;
    outline: none;
    line-height: 1.5;
    color: var(--text-primary);
    background: var(--surface-input);
    transition: border-color .15s, box-shadow .15s, background .15s;
    font-family: inherit;
    max-height: 90px;
    overflow-y: auto;
    display: block;
    width: 100%;
}
.aic-textarea:focus         { border-color:var(--brand-primary); box-shadow: 0 0 0 3px rgba(22,82,240,.1); background: var(--surface-card); }
.aic-textarea:disabled      { opacity: .6; cursor: not-allowed; background: var(--surface-muted); }
.aic-textarea::placeholder  { color: var(--text-muted); }

.aic-send-btn {
    background: var(--brand-primary);
    color: #fff;
    border: none;
    border-radius: .75rem;
    padding: .525rem .9rem;
    font-size: .875rem;
    cursor: pointer;
    align-self: flex-end;
    transition: background .15s, transform .12s, box-shadow .15s;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 2.5rem;
    height: 2.2rem;
}
.aic-send-btn:hover    { background: var(--brand-primary-dark); box-shadow: 0 3px 10px rgba(22,82,240,.35); }
.aic-send-btn:active   { transform: scale(.94); }
.aic-send-btn:disabled { background: var(--text-muted); cursor: not-allowed; box-shadow: none; }

.aic-input-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: .3rem;
    padding: 0 .1rem;
}
.aic-char-count { font-size: .7rem; transition: color .2s; }
.aic-input-hint { font-size: .68rem; color: var(--text-muted); }
</style>

<div class="aic-root"
     x-data="aiChatWidget()"
     x-init="init()">

    <!-- ══════════════════════════════════
         CHAT PANEL
         ══════════════════════════════════ -->
    <div class="aic-panel"
         x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-3 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-3 scale-95"
         role="dialog"
         aria-label="BarangGabay AI Chat"
         style="display:none;">

        <!-- Header ─────────────────────── -->
        <div class="aic-header">
            <div class="aic-header-left">
                <div class="aic-header-avatar" aria-hidden="true">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="aic-header-info">
                    <h4 class="aic-header-title"><?= e(t('ai_chat.panel_title')) ?></h4>
                    <p class="aic-header-sub">
                        <span class="aic-online-dot" aria-hidden="true"></span>
                        <?= e(t('ai_chat.panel_subtitle')) ?>
                    </p>
                </div>
            </div>
            <button type="button"
                    class="aic-close"
                    @click="toggle()"
                    aria-label="<?= e(t('ai_chat.close_chat')) ?>">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <!-- Messages ────────────────────── -->
        <div class="aic-messages"
             x-ref="messages"
             role="log"
             aria-live="polite"
             aria-atomic="false">

            <!-- Conversation bubbles -->
            <template x-for="msg in messages" :key="msg.id">
                <div class="aic-msg"
                     :class="msg.role === 'user' ? 'aic-msg-user' : 'aic-msg-ai'">

                    <!-- Avatar -->
                    <div class="aic-avatar" aria-hidden="true">
                        <template x-if="msg.role === 'ai'">
                            <i class="bi bi-robot"></i>
                        </template>
                        <template x-if="msg.role === 'user'">
                            <i class="bi bi-person-fill"></i>
                        </template>
                    </div>

                    <!-- Bubble -->
                    <div class="aic-bubble" x-html="msg.html"></div>
                </div>
            </template>

            <!-- Typing indicator (three bouncing dots) -->
            <div class="aic-msg aic-msg-ai" x-show="typing" aria-label="<?= e(t('ai_chat.typing')) ?>">
                <div class="aic-avatar" aria-hidden="true">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="aic-bubble" style="padding:.55rem .875rem;">
                    <div class="aic-typing-dots" aria-hidden="true">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            </div>

        </div><!-- /aic-messages -->

        <!-- Input ──────────────────────── -->
        <div class="aic-input-wrap">
            <div class="aic-input-row">
                <textarea
                    class="aic-textarea"
                    x-ref="input"
                    x-model="question"
                    @keydown.enter.prevent="!$event.shiftKey && sendMessage()"
                    placeholder="<?= e(t('ai_chat.input_placeholder')) ?>"
                    rows="2"
                    :disabled="typing"
                    maxlength="500"
                    aria-label="<?= e(t('ai_chat.input_aria')) ?>"></textarea>

                <button type="button"
                        class="aic-send-btn"
                        @click="sendMessage()"
                        :disabled="!question.trim() || typing || question.length > 500"
                        title="<?= e(t('ai_chat.send_title')) ?>">
                    <i class="bi bi-send-fill" style="font-size:.8rem;"></i>
                </button>
            </div>

            <div class="aic-input-footer">
                <span class="aic-input-hint"><?= e(t('ai_chat.newline_hint')) ?></span>
                <span class="aic-char-count"
                      :style="charColor"
                      x-text="charCount + ' / 500'"></span>
            </div>
        </div>

    </div><!-- /aic-panel -->

    <!-- ══════════════════════════════════
         FAB TOGGLE BUTTON
         ══════════════════════════════════ -->
    <button type="button"
            class="aic-fab"
            @click="toggle()"
            :aria-expanded="open.toString()"
            :aria-label="open ? <?= e(json_encode(t('ai_chat.close_chat_aria'))) ?> : <?= e(json_encode(t('ai_chat.open_chat'))) ?>">

        <!-- Notification dot — shown when a reply arrived while panel was closed -->
        <span class="aic-dot"
              x-show="hasNewMsg && !open"
              x-transition:enter="transition ease-out duration-200"
              x-transition:enter-start="opacity-0 scale-50"
              x-transition:enter-end="opacity-100 scale-100"
              aria-hidden="true"
              style="display:none;"></span>

        <!-- Icon circle -->
        <div class="aic-fab-icon" aria-hidden="true">
            <i class="bi" :class="open ? 'bi-chevron-down' : 'bi-robot'"></i>
        </div>

        <!-- Label -->
        <span class="aic-fab-label"><?= e(t('ai_chat.fab_label')) ?></span>

        <!-- Chevron (only visible when open, replaced by robot icon when closed) -->
        <i class="bi bi-chevron-up aic-fab-chevron"
           x-show="!open"
           aria-hidden="true"
           style="display:none;"></i>
    </button>

</div><!-- /aic-root -->

<!-- Widget logic lives in ai-widget.js (no inline function — keeps this file CSS+HTML only) -->
<script>
    window.BarangGabay = window.BarangGabay || {};
    window.BarangGabay.aiChatI18n = <?= json_encode([
        'greeting'        => t('ai_chat.greeting'),
        'rateLimited'     => t('ai_chat.rate_limited'),
        'genericError'    => t('ai_chat.generic_error'),
        'connectionError' => t('ai_chat.connection_error'),
    ]) ?>;
</script>
<script src="<?= e(asset_v('assets/js/ai-widget.js')) ?>"></script>
