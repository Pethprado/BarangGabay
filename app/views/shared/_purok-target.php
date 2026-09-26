<?php
/**
 * "Who is this notice for?" — the purok selector on the announcement form.
 *
 * Why this exists. Before it, every notice was texted to all seven puroks. A
 * water interruption affecting Purok 3 cost seven puroks' worth of SMS credits
 * for a message six of them could do nothing about — and each such message
 * teaches residents that barangay SMS is usually not about them. The notice
 * they eventually stop opening might be a storm-surge advisory.
 *
 * What it does NOT do: hide the post. A targeted notice is still readable by
 * every resident on the site, and carries a badge saying which purok it is
 * for. Narrowing who gets pushed is right; hiding a public notice from the
 * rest of the barangay is not.
 *
 * "Lahat ng purok" stays the default, so a staff member who does not engage
 * with this control gets exactly the old behaviour.
 *
 * Expects (optional):
 *   $ptSelected  string  Currently targeted purok, '' for all.
 *   $ptCheckin   bool    Whether this advisory asks for a safety check-in.
 */

$ptSelected = (string) ($ptSelected ?? '');
$ptCheckin  = (bool)   ($ptCheckin  ?? false);

$ptPuroks = barangay_subdivisions();
if ($ptPuroks === []) {
    return;   // no subdivisions configured — nothing to target
}

// The reach per purok, so the cost of a send is on screen before it is spent.
try {
    $ptReach = \App\Models\User::countReachableByPurok();
} catch (\Throwable $e) {
    $ptReach = [];
}
$ptTotal = array_sum($ptReach);
?>

<div class="mb-4">
    <label for="target-purok" class="form-label fw-semibold" style="font-size:.845rem;">
        <i class="bi bi-geo-alt me-1" style="color:var(--brand-primary);"></i><?= e(t('purok_target.label')) ?>
    </label>

    <select id="target-purok" name="target_purok" class="form-select" style="font-size:.875rem;">
        <option value="" <?= $ptSelected === '' ? 'selected' : '' ?>>
            <?= e(t('purok_target.all', ['n' => (string) $ptTotal])) ?>
        </option>
        <?php foreach ($ptPuroks as $ptOne): ?>
        <option value="<?= e($ptOne) ?>" <?= $ptSelected === $ptOne ? 'selected' : '' ?>>
            <?= e($ptOne) ?> — <?= e(t('purok_target.reach', ['n' => (string) ($ptReach[$ptOne] ?? 0)])) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <div class="form-text" style="font-size:.78rem;line-height:1.6;">
        <?= e(t('purok_target.help')) ?>
    </div>

    <?php if (!empty($ptReach['']) ): ?>
    <?php /* --tw-amber-700 was used here and is declared only in main.css.
             This partial renders inside the ADMIN layout, which loads
             admin.css, so the var resolved to nothing and the warning was
             painted in the inherited body colour — the one line on this form
             that is supposed to stand out. --status-warning is shared. */ ?>
    <div class="form-text" style="font-size:.78rem;color:var(--status-warning);">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <?= e(t('purok_target.no_purok_warning', ['n' => (string) $ptReach['']])) ?>
    </div>
    <?php endif; ?>
</div>

<?php
/*
 * The safety check-in used to be a bare checkbox here, under the purok
 * heading, where nobody could find it. It is its own callout now — see
 * _safety-checkin-toggle.php for why. Included from here so both the
 * create and edit forms keep getting it without either having to know.
 */
require __DIR__ . '/_safety-checkin-toggle.php';
