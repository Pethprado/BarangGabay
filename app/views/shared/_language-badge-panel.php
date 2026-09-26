<?php
/**
 * The detail panel the language chips open. Include ONCE per page.
 *
 * One panel for the whole page rather than one per chip. A list of twenty
 * posts carries sixty chips; sixty hidden popovers is sixty copies of the
 * same markup, and a popover anchored inside a scrolling table clips
 * against its container. A single dialog has neither problem.
 *
 * Expects:
 *   $lbpRedirect  string  Where the retry button returns to.
 */

$lbpRedirect = $lbpRedirect ?? '/admin/announcements';
?>
<div x-data="{
        open: false,
        d: {},
        show(detail) { this.d = detail; this.open = true; },
     }"
     @lang-detail.window="show($event.detail)"
     @keydown.escape.window="open = false">

    <div class="lang-panel-backdrop" x-show="open" x-cloak
         @click="open = false" aria-hidden="true"></div>

    <div class="lang-panel" x-show="open" x-cloak
         role="dialog" aria-modal="true" :aria-label="d.label">

        <div class="lang-panel__head">
            <span class="lang-panel__chip" :class="'lang-chip--' + d.state" x-text="d.label"></span>
            <span class="lang-panel__state" x-text="d.stateText"></span>
            <button type="button" class="lang-panel__close" @click="open = false"
                    aria-label="<?= e(t('common.close')) ?>">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <p class="lang-panel__post" x-text="d.title"></p>

        <p class="lang-panel__label"><?= e(t('translation_health.what_happened')) ?></p>
        <p class="lang-panel__reason" x-text="d.reason"></p>

        <p class="lang-panel__label"><?= e(t('translation_health.what_to_do')) ?></p>
        <p class="lang-panel__fix" x-text="d.fix"></p>

        <?php /* The literal variable name, in a box, because that is the
                 string someone editing .env searches for. */ ?>
        <p class="lang-panel__env" x-show="d.envKey" x-cloak>
            <code x-text="d.envKey"></code>
        </p>

        <?php /* The provider's own sentence. Staff only — this page is
                 already behind role:admin,staff — and folded away because
                 it is evidence, not instruction. */ ?>
        <details class="lang-panel__raw" x-show="d.detail" x-cloak>
            <summary><?= e(t('translation_health.technical_detail')) ?></summary>
            <p x-text="d.detail"></p>
            <p class="lang-panel__tries" x-show="d.attempts > 0">
                <span x-text="d.attempts"></span> <?= e(t('translation_health.attempts_so_far')) ?>
                <template x-if="d.retryAt">
                    <span> · <?= e(t('translation_health.next_try')) ?> <span x-text="d.retryAt"></span></span>
                </template>
            </p>
        </details>

        <form method="post" action="<?= e(route('admin/retranslate')) ?>" class="lang-panel__act">
            <input type="hidden" name="csrf_token"   value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="content_type" :value="d.type">
            <input type="hidden" name="content_id"   :value="d.id">
            <input type="hidden" name="lang"         :value="d.lang">
            <input type="hidden" name="redirect"     value="<?= e($lbpRedirect) ?>">

            <button type="submit" class="btn-barangay">
                <i class="bi bi-translate me-1"></i><?= e(t('translation_health.translate_now')) ?>
            </button>
            <button type="button" class="lang-panel__cancel" @click="open = false">
                <?= e(t('common.close')) ?>
            </button>
        </form>
    </div>
</div>
