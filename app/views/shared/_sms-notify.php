<?php
/**
 * "Also send this by SMS" — shared by the announcement, event and ordinance
 * forms so the three behave identically and read identically.
 *
 * Why a checkbox at all, rather than always sending:
 *
 *   Every SMS costs Semaphore credits, and a barangay blast is one message per
 *   resident. Announcements and events used to fire a blast on every save with
 *   no way to decline and no indication it had happened, so a typo fix on a
 *   published post could quietly cost another full round of credits. Making it
 *   an explicit choice, with the reach printed next to it, means staff know
 *   what a save will spend before they spend it.
 *
 * It is checked by default for announcements and events because that matches
 * what those forms already did — this adds the ability to say no, it does not
 * silently switch notifications off for anyone relying on them.
 *
 * Expects (all optional):
 *   $smsChecked  bool  Initial state. Default true.
 *   $smsNote     string  Extra line shown under the label (e.g. the scheduling caveat).
 */

$smsChecked = $smsChecked ?? true;
$smsNote    = $smsNote    ?? '';

// How many people this would actually reach. Verified residents who gave a
// phone number — the same set SmsController::getVerifiedPhones() sends to.
try {
    $smsReach = \App\Models\User::countReachableBySms();
} catch (\Throwable $e) {
    $smsReach = null;   // never let a count failure break the form
}

$smsConfigured = (new \App\Services\SemaphoreSmsService())->isConfigured();
$smsTestMode   = (new \App\Services\SemaphoreSmsService())->isTestMode();
?>

<div class="mb-4">
    <label class="form-label fw-semibold" style="font-size:.845rem;">
        <i class="bi bi-chat-dots me-1" style="color:var(--brand-green);"></i>
        <?= e(t('sms_notify.label')) ?>
    </label>

    <div class="form-check">
        <input class="form-check-input" type="checkbox"
               id="send-sms" name="send_sms" value="1"
               <?= $smsChecked ? 'checked' : '' ?>
               <?= $smsConfigured ? '' : 'disabled' ?>>
        <label class="form-check-label" for="send-sms" style="font-size:.845rem;">
            <?= e(t('sms_notify.checkbox')) ?>
        </label>
    </div>

    <?php if (!$smsConfigured): ?>
        <!-- No API key: say so plainly rather than offering a control that
             silently does nothing. -->
        <p class="form-text mt-1" style="font-size:.77rem;color:var(--status-warning);">
            <i class="bi bi-exclamation-triangle me-1"></i><?= e(t('sms_notify.not_configured')) ?>
        </p>
    <?php else: ?>
        <p class="form-text text-muted mt-1 mb-0" style="font-size:.77rem;">
            <?php if ($smsReach === null): ?>
                <?= e(t('sms_notify.reach_unknown')) ?>
            <?php elseif ($smsReach === 0): ?>
                <i class="bi bi-info-circle me-1"></i><?= e(t('sms_notify.reach_none')) ?>
            <?php else: ?>
                <i class="bi bi-people me-1"></i><?= e(t('sms_notify.reach', ['count' => $smsReach])) ?>
            <?php endif; ?>
        </p>

        <?php if ($smsTestMode): ?>
        <!-- Test mode logs the message but never hands it to the network. Staff
             must be told, or they will believe a blast went out that did not. -->
        <p class="form-text mt-1 mb-0" style="font-size:.77rem;color:var(--status-info);">
            <i class="bi bi-flask me-1"></i><?= e(t('sms_notify.test_mode')) ?>
        </p>
        <?php endif; ?>

        <?php if ($smsNote !== ''): ?>
        <p class="form-text text-muted mt-1 mb-0" style="font-size:.77rem;">
            <i class="bi bi-clock me-1"></i><?= e($smsNote) ?>
        </p>
        <?php endif; ?>
    <?php endif; ?>
</div>
