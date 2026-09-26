<?php
/**
 * "Ask residents to check in" — the switch that starts a roll call.
 *
 * This used to be a bare checkbox at the foot of the purok-targeting block,
 * which is where it could not be found. Two reasons it did not belong there:
 *
 *   - it is not a targeting setting. Purok targeting decides who gets texted;
 *     this decides whether the barangay is running a head count. Putting them
 *     under one heading made the more consequential of the two look like a
 *     sub-option of the other.
 *   - it is used perhaps twice a year, during a storm, by someone under
 *     pressure. A control like that has to announce itself, not reward
 *     familiarity with the form.
 *
 * So it is now its own callout: framed, iconed, and saying plainly what
 * happens when it is ticked.
 *
 * The field name, value and semantics are unchanged — this is the same
 * checkbox in a different frame, so nothing downstream had to move.
 *
 * Expects (optional):
 *   $ptCheckin  bool  Whether this advisory already asks for a check-in.
 */

$ptCheckin = (bool) ($ptCheckin ?? false);
?>

<div class="safety-toggle<?= $ptCheckin ? ' safety-toggle--on' : '' ?>">
    <div class="safety-toggle__head">
        <span class="safety-toggle__icon" aria-hidden="true">
            <i class="bi bi-shield-check"></i>
        </span>
        <div class="min-w-0">
            <p class="safety-toggle__title"><?= e(t('purok_target.checkin_heading')) ?></p>
            <p class="safety-toggle__sub"><?= e(t('purok_target.checkin_when')) ?></p>
        </div>
    </div>

    <div class="form-check safety-toggle__check">
        <input class="form-check-input" type="checkbox"
               id="asks-safety-checkin" name="asks_safety_checkin" value="1"
               <?= $ptCheckin ? 'checked' : '' ?>>
        <label class="form-check-label" for="asks-safety-checkin">
            <?= e(t('purok_target.checkin_label')) ?>
        </label>
    </div>

    <p class="safety-toggle__help"><?= e(t('purok_target.checkin_help')) ?></p>

    <p class="safety-toggle__where">
        <i class="bi bi-arrow-right-short" aria-hidden="true"></i>
        <?= e(t('purok_target.checkin_where')) ?>
        <a href="<?= e(route('admin/safety')) ?>"><?= e(t('admin_nav.safety')) ?></a>
    </p>
</div>
