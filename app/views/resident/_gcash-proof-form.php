<?php
/**
 * GCash proof upload form (used by payment_checkout.php).
 * Expects $reqId; $resend (bool) only changes the button label; $fromQr (the
 * document QR token, optional) brings the resident back to that QR page.
 */
$__fid = !empty($resend) ? 'resend' : 'proof';
?>
<form method="post" action="<?= e(route('documents/' . $reqId . '/payment')) ?>" enctype="multipart/form-data" class="mt-3 space-y-4"
      onsubmit="var b=this.querySelector('button[type=submit]'); b.disabled=true; b.innerHTML='<i class=&quot;bi bi-hourglass-split&quot;></i> Sending…';">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <?php if (!empty($fromQr)): ?><input type="hidden" name="from_qr" value="<?= e((string) $fromQr) ?>"><?php endif; ?>
    <div>
        <label class="gc-label" for="<?= $__fid ?>Ref"><?= e(__('GCash reference number')) ?></label>
        <input class="gc-input font-mono" id="<?= $__fid ?>Ref" name="gcash_reference_no" required inputmode="numeric"
               pattern="[0-9\s\-]{8,25}" maxlength="25" placeholder="e.g. 1012 345 678901" autocomplete="off"
               aria-describedby="<?= $__fid ?>RefHelp">
        <p class="gc-muted mt-1" id="<?= $__fid ?>RefHelp"><?= e(__('The 13-digit "Ref. No." on your GCash receipt.')) ?></p>
    </div>
    <div>
        <label class="gc-label" for="<?= $__fid ?>File"><?= e(__('Screenshot of the GCash receipt')) ?></label>
        <input class="gc-input" id="<?= $__fid ?>File" name="receipt_file" type="file" required accept="image/jpeg,image/png,image/webp,application/pdf">
        <p class="gc-muted mt-1"><?= e(__('JPG, PNG, WEBP or PDF · up to 10 MB.')) ?></p>
    </div>
    <div>
        <label class="gc-label" for="<?= $__fid ?>Note"><?= e(__('Note for the barangay')) ?> <span class="gc-muted"><?= e(__('(optional)')) ?></span></label>
        <input class="gc-input" id="<?= $__fid ?>Note" name="notes" maxlength="500" placeholder="<?= e(__('e.g. Sent from my mother\'s GCash')) ?>">
    </div>
    <button type="submit" class="gc-btn" style="background:#087a4b;color:#fff;">
        <i class="bi bi-send-check" aria-hidden="true"></i> <?= !empty($resend) ? __('Send new proof') : __('Submit proof of payment') ?>
    </button>
</form>
