<?php
/**
 * Document QR page (/d/{token}) — mobile first.
 *
 * Scan → details and fee → pay to the barangay GCash / InstaPay QR → submit
 * proof → staff check the money arrived and approve → the document unlocks.
 *
 * Variables: $request, $payment, $fee, $stage, $hasFile, $qrSvg, $qrUrl,
 *            $gcash, $isStaff
 *
 * $stage: pay | verifying | rejected | pay_at_hall | unlocked | closed
 */
use App\Models\DocumentRequest;

$reqId    = (int) $request['id'];
$token    = substr((string) $qrUrl, strrpos((string) $qrUrl, '/') + 1);
$docLabel = DocumentRequest::label((string) $request['document_type']);
$feeText  = '₱' . number_format((float) $fee, 2);
$status   = (string) $request['status'];
$delivery = (string) ($request['delivery_method'] ?? 'pickup');
$number   = (string) $gcash['number'];
$numberFmt = strlen($number) === 11 ? substr($number, 0, 4) . ' ' . substr($number, 4, 3) . ' ' . substr($number, 7) : $number;

// Progress: 0 scan, 1 pay, 2 verify, 3 unlocked
$step = match ($stage) {
    'unlocked'  => 3,
    'verifying' => 2,
    default     => 1,
};
$steps = [__('Scan'), __('Pay'), __('Verify'), __('Unlocked')];

ob_start();
?>
<style>
.dq-wrap { max-width: 560px; margin: 0 auto; }
.dq-card { border-radius: 18px; border: 1px solid var(--border); background: var(--surface-card); padding: 1.15rem; margin-bottom: 1rem; }
.dq-title { font-weight: 800; font-size: 1rem; color: var(--text-primary); display: flex; align-items: center; gap: .5rem; margin-bottom: .75rem; }
.dq-row { display: flex; justify-content: space-between; gap: 1rem; padding: .45rem 0; border-bottom: 1px dashed var(--border); font-size: .9rem; }
.dq-row:last-child { border-bottom: 0; }
.dq-row span:first-child { color: var(--text-muted); }
.dq-row span:last-child { font-weight: 700; color: var(--text-primary); text-align: right; }
.dq-fee { font-size: 2rem; font-weight: 900; color: var(--text-primary); font-variant-numeric: tabular-nums; }
.dq-steps { display: grid; grid-template-columns: repeat(4, 1fr); gap: .35rem; margin-bottom: 1rem; }
.dq-steps li { list-style: none; text-align: center; font-size: .72rem; font-weight: 700; color: var(--text-muted); }
.dq-steps li::before { content: ''; display: block; height: 6px; border-radius: 99px; background: var(--border); margin-bottom: .35rem; }
.dq-steps li.is-done::before, .dq-steps li.is-now::before { background: #087a4b; }
.dq-steps li.is-now { color: var(--text-primary); }
.dq-pay-qr { width: 100%; max-width: 320px; display: block; margin: .5rem auto; border-radius: 16px; border: 1px solid var(--border); background: #fff; }
.dq-btn { display: flex; width: 100%; align-items: center; justify-content: center; gap: .5rem; border: 0; border-radius: 14px; padding: .95rem; font-weight: 800; font-size: 1rem; text-decoration: none; }
.dq-btn:focus-visible, .dq-copy:focus-visible { outline: 3px solid var(--focus-ring); outline-offset: 2px; }
.dq-copy { border: 1px solid var(--border-strong); background: var(--surface-card); color: var(--text-primary); border-radius: 10px; padding: .35rem .7rem; font-weight: 700; font-size: .8rem; }
.dq-note { border-radius: 14px; padding: .9rem 1rem; font-size: .9rem; }
.dq-muted { color: var(--text-muted); font-size: .85rem; }
.dq-doc-qr svg { width: 200px; height: 200px; display: block; margin: 0 auto; background: #fff; border-radius: 12px; padding: 6px; }
/* reuse of the shared proof form */
.gc-label { display: block; font-size: .85rem; font-weight: 700; color: var(--text-primary); margin-bottom: .35rem; }
.gc-input { width: 100%; border: 1px solid var(--border-strong); border-radius: 12px; padding: .75rem .9rem; font-size: 1rem; background: var(--surface-input); color: var(--text-primary); }
.gc-muted { color: var(--text-muted); font-size: .85rem; }
.gc-btn { display: flex; width: 100%; align-items: center; justify-content: center; gap: .5rem; border: 0; border-radius: 14px; padding: 1rem; font-weight: 800; font-size: 1rem; }
.aic-root { display: none !important; }
</style>

<div class="dq-wrap">

    <?php if ($isStaff): ?>
    <div class="dq-note mb-3" style="background:#eff6ff;color:#1e3a8a;border:1px solid #bfdbfe;">
        <i class="bi bi-person-badge mr-1" aria-hidden="true"></i><?= e(__('Staff view of a resident\'s document QR.')) ?>
        <a href="<?= e(route('admin/payments')) ?>" class="font-bold underline"><?= e(__('Verify payments')) ?></a> ·
        <a href="<?= e(route('admin/documents') . '?search=' . rawurlencode((string) $request['reference_no'])) ?>" class="font-bold underline"><?= e(__('Open request')) ?></a>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <p class="dq-muted mb-0"><i class="bi bi-qr-code-scan mr-1" aria-hidden="true"></i><?= e(__('Document QR')) ?></p>
    <h1 class="mt-1 mb-1" style="font-size:1.4rem;font-weight:900;color:var(--text-primary);line-height:1.2;"><?= e($docLabel) ?></h1>
    <p class="dq-muted mb-3">
        <span class="font-mono font-bold"><?= e((string) $request['reference_no']) ?></span> ·
        <?= e(DocumentRequest::statusLabel($status)) ?>
    </p>

    <?php if ($stage !== 'closed' && $fee > 0): ?>
    <ol class="dq-steps p-0" aria-label="<?= e(__('Progress')) ?>">
        <?php foreach ($steps as $i => $label): ?>
            <li class="<?= $i < $step ? 'is-done' : ($i === $step ? 'is-now' : '') ?>"<?= $i === $step ? ' aria-current="step"' : '' ?>><?= e($label) ?></li>
        <?php endforeach; ?>
    </ol>
    <?php endif; ?>

    <!-- 1. Details and fee -->
    <section class="dq-card" aria-labelledby="dq-details">
        <h2 class="dq-title" id="dq-details"><i class="bi bi-file-earmark-text" aria-hidden="true"></i><?= e(__('Document details')) ?></h2>
        <div class="dq-row"><span><?= e(__('Document')) ?></span><span><?= e($docLabel) ?></span></div>
        <div class="dq-row"><span><?= e(__('Requested by')) ?></span><span><?= e((string) $request['full_name']) ?></span></div>
        <?php if (trim((string) ($request['purpose'] ?? '')) !== ''): ?>
        <div class="dq-row"><span><?= e(__('Purpose')) ?></span><span><?= e((string) $request['purpose']) ?></span></div>
        <?php endif; ?>
        <div class="dq-row"><span><?= e(__('Requested on')) ?></span><span><?= e(date('M d, Y', strtotime((string) $request['requested_at']))) ?></span></div>
        <div class="dq-row"><span><?= e(__('How you get it')) ?></span><span><?= e($delivery === 'digital' ? __('Digital copy (download)') : ($delivery === 'delivery' ? __('Delivery') : __('Pickup at the Barangay Hall'))) ?></span></div>
        <div class="dq-row"><span><?= e(__('Fee')) ?></span><span><?= $fee > 0 ? e($feeText) : e(__('Free')) ?></span></div>
    </section>

    <!-- 2. The next step -->
    <?php if ($stage === 'pay' || $stage === 'rejected'): ?>
    <section class="dq-card" aria-labelledby="dq-pay">
        <h2 class="dq-title" id="dq-pay"><i class="bi bi-wallet2" aria-hidden="true"></i><?= e(__('Pay the fee')) ?></h2>

        <?php if ($stage === 'rejected'): ?>
        <div class="dq-note mb-3" style="background:#fef2f2;color:#991b1b;border:1px solid #fecaca;" role="alert">
            <strong><?= e(__('Your last payment proof was not approved.')) ?></strong>
            <?php if (!empty($payment['rejection_reason'])): ?><br><?= e(__('Reason:')) ?> <?= e((string) $payment['rejection_reason']) ?><?php endif; ?>
            <br><?= e(__('Check the amount and reference number, then send a new proof below.')) ?>
        </div>
        <?php endif; ?>

        <p class="dq-muted mb-1"><?= e(__('Amount to send')) ?></p>
        <div class="flex items-center justify-between gap-2">
            <span class="dq-fee"><?= e($feeText) ?></span>
            <button type="button" class="dq-copy" data-copy="<?= e(number_format((float) $fee, 2, '.', '')) ?>"><i class="bi bi-copy" aria-hidden="true"></i> <?= e(__('Copy amount')) ?></button>
        </div>

        <p class="mt-3 mb-1 font-bold" style="color:var(--text-primary);"><?= e(__('Scan with GCash or any InstaPay bank app')) ?></p>
        <?php if ($gcash['has_qr']): ?>
            <img src="<?= e(route('payments/gcash-qr')) ?>" alt="<?= e(__('GCash / InstaPay QR of :name', ['name' => $gcash['name']])) ?>" class="dq-pay-qr">
            <a href="<?= e(route('payments/gcash-qr') . '?download=1') ?>" class="dq-btn mt-2" style="background:#007dfe;color:#fff;">
                <i class="bi bi-download" aria-hidden="true"></i> <?= e(__('Save the QR to your phone')) ?>
            </a>
        <?php endif; ?>
        <p class="dq-muted mt-2 mb-0">
            <?= e(__('Account name:')) ?> <strong><?= e((string) $gcash['name']) ?></strong>
            <?php if ($number !== ''): ?> · <?= e(__('GCash number:')) ?> <strong><?= e($numberFmt) ?></strong>
            <button type="button" class="dq-copy ml-1" data-copy="<?= e($number) ?>"><?= e(__('Copy')) ?></button><?php endif; ?>
        </p>
        <ol class="dq-muted mt-3 mb-0 pl-5 list-decimal space-y-1">
            <li><?= e(__('Open GCash → QR → Upload QR, and choose the saved QR (or scan it from another phone).')) ?></li>
            <li><?= e(__('Send exactly :amount.', ['amount' => $feeText])) ?></li>
            <li><?= e(__('Take a screenshot of the receipt and note the Ref. No.')) ?></li>
        </ol>
    </section>

    <section class="dq-card" aria-labelledby="dq-proof">
        <h2 class="dq-title" id="dq-proof"><i class="bi bi-send-check" aria-hidden="true"></i><?= e(__('Submit your payment for verification')) ?></h2>
        <p class="dq-muted mb-0"><?= e(__('Barangay staff check that the money really arrived before approving. You will get a notification.')) ?></p>
        <?php $resend = $stage === 'rejected'; $fromQr = $token; require __DIR__ . '/_gcash-proof-form.php'; ?>
    </section>

    <?php elseif ($stage === 'verifying'): ?>
    <section class="dq-card" aria-labelledby="dq-wait">
        <h2 class="dq-title" id="dq-wait"><i class="bi bi-hourglass-split" aria-hidden="true"></i><?= e(__('Payment submitted — waiting for verification')) ?></h2>
        <p class="mb-2" style="color:var(--text-primary);"><?= e(__('Barangay staff are checking that your payment was received. Your document unlocks as soon as they approve it.')) ?></p>
        <?php if (!empty($payment['gcash_reference_no'])): ?>
        <p class="dq-muted mb-0"><?= e(__('Your Ref. No.:')) ?> <span class="font-mono font-bold"><?= e((string) $payment['gcash_reference_no']) ?></span></p>
        <?php endif; ?>
        <p class="dq-muted mt-2 mb-0"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> <?= e(__('This page refreshes by itself.')) ?></p>
    </section>

    <?php elseif ($stage === 'pay_at_hall'): ?>
    <section class="dq-card" aria-labelledby="dq-hall">
        <h2 class="dq-title" id="dq-hall"><i class="bi bi-building" aria-hidden="true"></i><?= e(__('Pay at the Barangay Hall')) ?></h2>
        <p class="mb-0" style="color:var(--text-primary);"><?= e(__('You chose to pay :amount at the Barangay Hall. Show this page to the staff; the document unlocks after they record your payment.', ['amount' => $feeText])) ?></p>
    </section>

    <?php elseif ($stage === 'unlocked'): ?>
    <section class="dq-card" aria-labelledby="dq-open" style="border-color:#86efac;">
        <h2 class="dq-title" id="dq-open"><i class="bi bi-unlock-fill" style="color:#087a4b;" aria-hidden="true"></i><?= e($fee > 0 ? __('Payment approved — your document is unlocked') : __('Your document')) ?></h2>
        <?php if ($hasFile): ?>
            <a href="<?= e(route('documents/' . $reqId . '/preview')) ?>" target="_blank" rel="noopener" class="dq-btn mb-2" style="background:#087a4b;color:#fff;">
                <i class="bi bi-eye" aria-hidden="true"></i> <?= e(__('View document')) ?>
            </a>
            <a href="<?= e(route('documents/' . $reqId . '/download')) ?>" class="dq-btn" style="background:var(--surface-card);color:var(--text-primary);border:1px solid var(--border-strong);">
                <i class="bi bi-download" aria-hidden="true"></i> <?= e(__('Download PDF')) ?>
            </a>
            <?php if (!empty($request['certificate_no'])): ?>
            <div class="mt-3 pt-3" style="border-top:1px dashed var(--border);">
                <?php if (!empty($request['printed_at'])): ?>
                    <p class="mb-0" style="color:var(--text-primary);"><i class="bi bi-printer-fill mr-1" aria-hidden="true"></i><?= e(__('Your printed copy is ready. Claim it at the Barangay Hall and show this QR.')) ?></p>
                <?php elseif (!empty($request['print_requested_at'])): ?>
                    <p class="mb-0" style="color:var(--text-primary);"><i class="bi bi-hourglass-split mr-1" aria-hidden="true"></i><?= e(__('Printed copy requested. It is in the barangay\'s print queue; you will be notified when it is ready.')) ?></p>
                <?php else: ?>
                    <form method="post" action="<?= e(route('documents/' . $reqId . '/print-request')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="back" value="<?= e($token) ?>">
                        <button type="submit" class="dq-btn" style="background:var(--surface-card);color:var(--text-primary);border:1px solid var(--border-strong);">
                            <i class="bi bi-printer" aria-hidden="true"></i> <?= e(__('Request Printed Copy')) ?>
                        </button>
                        <p class="dq-muted mt-1 mb-0"><?= e(__('The barangay prints this same approved document for you to claim at the hall.')) ?></p>
                    </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php elseif (in_array($status, ['released', 'completed'], true)): ?>
            <p class="mb-0" style="color:var(--text-primary);"><?= e(__('This document has been released to you.')) ?></p>
        <?php else: ?>
            <p class="mb-0" style="color:var(--text-primary);"><?= e($delivery === 'digital'
                ? __('The barangay is preparing your document. It will appear here to view and download; you will get a notification.')
                : __('The barangay is preparing your document. When it is ready, show this QR at the Barangay Hall to claim it.')) ?></p>
        <?php endif; ?>
    </section>

    <?php else: ?>
    <section class="dq-card" aria-labelledby="dq-closed">
        <?php if (!empty($request['revoked_at'])): ?>
        <h2 class="dq-title" id="dq-closed"><i class="bi bi-x-octagon" aria-hidden="true"></i><?= e(__('This document was cancelled by the barangay')) ?></h2>
        <?php if (!empty($request['revoke_reason'])): ?><p class="mb-0" style="color:var(--text-primary);"><?= e(__('Reason:')) ?> <?= e((string) $request['revoke_reason']) ?></p><?php endif; ?>
        <?php else: ?>
        <h2 class="dq-title" id="dq-closed"><i class="bi bi-x-circle" aria-hidden="true"></i><?= e($status === 'rejected' ? __('This request was rejected') : __('This request was cancelled')) ?></h2>
        <?php if (!empty($request['rejection_reason'])): ?><p class="mb-0" style="color:var(--text-primary);"><?= e((string) $request['rejection_reason']) ?></p><?php endif; ?>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- 3. The document's own QR -->
    <section class="dq-card text-center" aria-labelledby="dq-qr">
        <h2 class="dq-title justify-center" id="dq-qr"><i class="bi bi-qr-code" aria-hidden="true"></i><?= e(__('This document\'s QR')) ?></h2>
        <div class="dq-doc-qr" role="img" aria-label="<?= e(__('QR code for request :ref', ['ref' => $request['reference_no']])) ?>"><?= $qrSvg ?></div>
        <p class="dq-muted mt-2 mb-2"><?= e(__('Scan it anytime to come back to this page. Only your account can open it.')) ?></p>
        <a href="<?= e(route('documents/' . $reqId . '/qr') . '?download=1') ?>" class="dq-copy inline-block no-underline"><i class="bi bi-download" aria-hidden="true"></i> <?= e(__('Save QR')) ?></a>
    </section>

    <a href="<?= e(route($isStaff ? 'admin/documents' : 'documents')) ?>" class="dq-muted inline-block mb-4"><i class="bi bi-arrow-left" aria-hidden="true"></i> <?= e(__('All my requests')) ?></a>
</div>

<script>
(function () {
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var text = btn.getAttribute('data-copy'), done = function () { var old = btn.innerHTML; btn.textContent = <?= json_encode(__('Copied')) ?>; setTimeout(function () { btn.innerHTML = old; }, 1500); };
            if (navigator.clipboard) { navigator.clipboard.writeText(text).then(done, done); return; }
            var t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select();
            try { document.execCommand('copy'); } catch (e) {} document.body.removeChild(t); done();
        });
    });
    <?php if ($stage === 'verifying'): ?>
    setTimeout(function () { location.reload(); }, 30000);
    <?php endif; ?>
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
