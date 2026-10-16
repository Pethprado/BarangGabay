<?php
/**
 * Resident — Pay with GCash (direct transfer + proof).
 *
 * 1. Send the exact fee to the barangay GCash (number, QR, "Open GCash").
 * 2. Upload the GCash reference number and a screenshot.
 * 3. Staff verify it; this page then shows "Payment successful" by itself.
 *
 * GCash does not let a website pre-fill the recipient or amount, so the page
 * copies the number for the resident and opens the app; they paste or scan.
 * PayMongo hosted checkout is offered as an extra option only when an admin
 * turns it on.
 */
use App\Models\DocumentPayment;
use App\Models\DocumentRequest;

$request = $request ?? [];
$payment = $payment ?? [];
$gcash   = $gcash ?? ['name' => '', 'number' => '', 'has_qr' => false, 'amount_qr' => false];
$amountQr = !empty($gcash['amount_qr']);
$paymongoEnabled = $paymongoEnabled ?? false;

$reqId     = (int) ($request['id'] ?? 0);
$docLabel  = DocumentRequest::label((string) ($request['document_type'] ?? ''));
$fee       = (float) ($payment['amount_due'] ?? 0);
$feeText   = number_format($fee, 2);
$reqRef    = (string) ($request['reference_no'] ?? '');
$payRef    = (string) ($payment['payment_ref'] ?? '');
$payStatus = (string) ($payment['payment_status'] ?? DocumentPayment::STATUS_PENDING);
$isPaid    = in_array($payStatus, DocumentPayment::SETTLED_STATES, true);
$inReview  = in_array($payStatus, DocumentPayment::FILTER_GROUPS['manual_review'], true);
$rejected  = $payStatus === DocumentPayment::STATUS_PAYMENT_REJECTED;
$number    = (string) $gcash['number'];
$numberFmt = strlen($number) === 11 ? substr($number, 0, 4) . ' ' . substr($number, 4, 3) . ' ' . substr($number, 7) : $number;
$verifiedBy = match ((string) ($payment['verification_type'] ?? '')) {
    'paymongo' => __('Automatically verified through PayMongo'),
    'manual'   => __('Verified by barangay staff'),
    'counter'  => __('Paid in cash at the Barangay Hall'),
    'waiver'   => __('Fee waived by the barangay'),
    default    => __('Verified'),
};

ob_start();
?>
<style>
.gc-card { border-radius:18px; border:1px solid var(--border); background:var(--surface-card); padding:1.25rem; }
.gc-step { display:inline-grid; place-items:center; width:28px; height:28px; border-radius:999px; background:#007dfe; color:#fff; font-weight:800; font-size:.85rem; flex-shrink:0; }
.gc-blue { background:#007dfe; color:#fff; }
.gc-num { font-size:1.6rem; font-weight:800; letter-spacing:.04em; color:var(--text-primary); font-variant-numeric:tabular-nums; }
.gc-copy { border:1px solid var(--border-strong); background:var(--surface-card); color:var(--text-primary); border-radius:10px; padding:.4rem .75rem; font-weight:700; font-size:.82rem; }
.gc-copy:focus-visible, .gc-btn:focus-visible { outline:3px solid var(--focus-ring); outline-offset:2px; }
.gc-btn { display:flex; width:100%; align-items:center; justify-content:center; gap:.5rem; border:0; border-radius:14px; padding:1rem; font-weight:800; font-size:1rem; text-decoration:none; }
.gc-label { display:block; font-size:.85rem; font-weight:700; color:var(--text-primary); margin-bottom:.35rem; }
.gc-input { width:100%; border:1px solid var(--border-strong); border-radius:12px; padding:.75rem .9rem; font-size:1rem; background:var(--surface-input); color:var(--text-primary); }
.gc-input:focus { outline:none; border-color:#007dfe; box-shadow:0 0 0 4px rgba(0,125,254,.18); }
.gc-muted { color:var(--text-muted); font-size:.85rem; }
/* The floating AI button covered the GCash steps on phones; not needed while paying. */
.aic-root { display:none !important; }
.gc-qr { width:220px; max-width:100%; border-radius:14px; border:1px solid var(--border); background:#fff; padding:8px; }
</style>

<div class="mx-auto max-w-xl px-4 py-6 sm:px-6">
    <a href="<?= e(route('documents?tab=requests')) ?>" class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold" style="color:var(--text-secondary);">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= e(__('My requests')) ?>
    </a>

    <div class="gc-card mb-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider" style="color:#007dfe;"><?= e(__('Document payment')) ?></p>
                <h1 class="text-2xl font-extrabold" style="color:var(--text-primary);"><?= e(__('Pay with GCash')) ?></h1>
                <p class="gc-muted mt-1"><?= e($docLabel) ?> · <span class="font-mono"><?= e($reqRef) ?></span></p>
            </div>
            <div class="text-right">
                <p class="gc-muted"><?= e(__('Amount')) ?></p>
                <p class="text-3xl font-extrabold" style="color:var(--text-primary);">₱<?= $feeText ?></p>
            </div>
        </div>
    </div>

<?php if ($isPaid): ?>
    <div class="gc-card text-center" role="status">
        <i class="bi bi-check-circle-fill" style="font-size:3rem;color:#16a36d;" aria-hidden="true"></i>
        <h2 class="mt-2 text-xl font-extrabold" style="color:var(--text-primary);"><?= e(__('Payment successful')) ?></h2>
        <p class="gc-muted mt-1"><?= e($verifiedBy) ?><?= !empty($payment['paid_at']) ? ' · ' . e(format_datetime((string) $payment['paid_at'])) : '' ?></p>
        <dl class="mt-4 grid grid-cols-2 gap-2 text-left text-sm" style="color:var(--text-secondary);">
            <dt><?= e(__('Amount')) ?></dt><dd class="text-right font-bold" style="color:var(--text-primary);">₱<?= $feeText ?></dd>
            <dt><?= e(__('Payment ref.')) ?></dt><dd class="text-right font-mono"><?= e($payRef) ?></dd>
            <?php if (!empty($payment['gcash_reference_no'])): ?><dt><?= e(__('GCash Ref. No.')) ?></dt><dd class="text-right font-mono"><?= e((string) $payment['gcash_reference_no']) ?></dd><?php endif; ?>
        </dl>
        <p class="gc-muted mt-4"><?= e(__('Your document is now being processed. You will be notified when it is ready.')) ?></p>
        <a href="<?= e(route('documents?tab=requests')) ?>" class="gc-btn gc-blue mt-4"><i class="bi bi-folder2-open" aria-hidden="true"></i> <?= e(__('View my requests')) ?></a>
    </div>

<?php elseif ($inReview): ?>
    <div class="gc-card text-center" role="status" aria-live="polite">
        <div class="mx-auto mb-2 h-12 w-12 animate-spin rounded-full border-4 border-t-transparent" style="border-color:#007dfe;border-top-color:transparent;" aria-hidden="true"></div>
        <h2 class="text-xl font-extrabold" style="color:var(--text-primary);"><?= e(__('Waiting for verification')) ?></h2>
        <p class="gc-muted mt-1"><?= e(__('We received your proof. Barangay staff are checking the GCash payment. This page updates by itself — you can also leave and come back.')) ?></p>
        <dl class="mt-4 grid grid-cols-2 gap-2 text-left text-sm" style="color:var(--text-secondary);">
            <dt><?= e(__('GCash Ref. No.')) ?></dt><dd class="text-right font-mono" style="color:var(--text-primary);"><?= e((string) ($payment['gcash_reference_no'] ?? '')) ?></dd>
            <dt><?= e(__('Amount')) ?></dt><dd class="text-right font-bold" style="color:var(--text-primary);">₱<?= $feeText ?></dd>
        </dl>
        <details class="mt-4 text-left">
            <summary class="cursor-pointer text-sm font-semibold" style="color:var(--text-secondary);"><?= e(__('Sent the wrong screenshot? Send it again')) ?></summary>
            <?php $resend = true; require __DIR__ . '/_gcash-proof-form.php'; ?>
        </details>
    </div>

<?php else: ?>
    <?php if ($paymongoEnabled): ?>
    <section class="gc-card mb-4 text-center" aria-labelledby="autoPay">
        <h2 id="autoPay" class="text-lg font-extrabold" style="color:var(--text-primary);"><i class="bi bi-lightning-charge-fill" style="color:#007dfe;" aria-hidden="true"></i> <?= e(__('Pay automatically')) ?></h2>
        <p class="gc-muted mt-1"><?= e(__('Opens GCash\'s payment page with ₱:amount already filled in. Approve it in GCash and your payment is confirmed automatically, with no screenshot needed.', ['amount' => $feeText])) ?></p>
        <button type="button" id="payBtnTop" class="gc-btn gc-blue mt-3"><i class="bi bi-wallet2" aria-hidden="true"></i> <?= e(__('Pay ₱:amount with GCash', ['amount' => $feeText])) ?></button>
        <p class="gc-muted mt-3"><?= e(__('Or pay by QR / Express Send below and upload your receipt.')) ?></p>
    </section>
    <?php endif; ?>
    <?php if ($rejected): ?>
    <div class="mb-4 rounded-xl border p-3 text-sm" role="alert" style="border-color:var(--status-danger);background:var(--status-danger-bg);color:var(--status-danger);">
        <strong><?= e(__('Your proof was not accepted:')) ?></strong> <?= e((string) ($payment['rejection_reason'] ?? '')) ?><?= !empty($payment['rejection_note']) ? ' — ' . e((string) $payment['rejection_note']) : '' ?><?= e(__('. Please check your GCash transaction and send the correct proof below.')) ?>
    </div>
    <?php endif; ?>

    <!-- Step 1: send the payment -->
    <section class="gc-card mb-4" aria-labelledby="step1">
        <h2 id="step1" class="mb-3 flex items-center gap-2 text-lg font-extrabold" style="color:var(--text-primary);"><span class="gc-step">1</span> <?= e(__('Send ₱:amount via GCash', ['amount' => $feeText])) ?></h2>

        <?php if ($amountQr): ?>
        <div class="mb-4 rounded-2xl p-4 text-center" style="background:#eaf3ff;border:1px solid #b9d8ff;">
            <p class="text-sm font-extrabold" style="color:#0b4ea2;"><i class="bi bi-lightning-charge-fill" aria-hidden="true"></i> <?= e(__('Fastest: the amount is already in this QR')) ?></p>
            <img src="<?= e(route('documents/' . $reqId . '/gcash-qr')) ?>" alt="<?= e(__('GCash QR with ₱:amount filled in', ['amount' => $feeText])) ?>" class="gc-qr mx-auto mt-2" width="220" height="220">
            <p class="mt-1 text-sm font-bold" style="color:var(--text-primary);">₱<?= $feeText ?> → <?= e((string) $gcash['name']) ?></p>
            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                <a href="<?= e(route('documents/' . $reqId . '/gcash-qr') . '?download=1') ?>" class="gc-btn" style="background:#fff;color:#0b4ea2;border:2px solid #007dfe;"><i class="bi bi-download" aria-hidden="true"></i> <?= e(__('1. Save QR')) ?></a>
                <a href="#" class="gc-btn gc-blue" data-open-gcash><i class="bi bi-phone" aria-hidden="true"></i> <?= e(__('2. Open GCash')) ?></a>
            </div>
            <p class="gc-muted mt-2"><?= e(__('In GCash tap')) ?> <strong><?= e(__('QR → Upload QR')) ?></strong><?= e(__(', choose the saved QR, check that it shows ₱:amount, then tap Pay. Or scan this QR from another phone.', ['amount' => $feeText])) ?></p>
        </div>
        <p class="gc-muted mb-2 text-center"><?= e(__('Or send it yourself:')) ?></p>
        <?php endif; ?>

        <div class="rounded-2xl p-4" style="background:var(--surface-muted);">
            <p class="gc-muted"><?= e(__('Send to')) ?></p>
            <p class="text-lg font-bold" style="color:var(--text-primary);"><?= e((string) $gcash['name']) ?></p>
            <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
                <span class="gc-num" id="gcNumber"><?= e($numberFmt) ?></span>
                <button type="button" class="gc-copy" data-copy="<?= e($number) ?>"><i class="bi bi-copy" aria-hidden="true"></i> <?= e(__('Copy number')) ?></button>
            </div>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t pt-3" style="border-color:var(--border);">
                <span><?= e(__('Exact amount:')) ?> <strong style="color:var(--text-primary);">₱<?= $feeText ?></strong></span>
                <button type="button" class="gc-copy" data-copy="<?= e(number_format($fee, 2, '.', '')) ?>"><i class="bi bi-copy" aria-hidden="true"></i> <?= e(__('Copy amount')) ?></button>
            </div>
        </div>

        <a href="#" id="openGcash" class="gc-btn gc-blue mt-4" data-open-gcash><i class="bi bi-phone" aria-hidden="true"></i> <?= e(__('Open GCash')) ?></a>
        <p class="gc-muted mt-2 text-center" id="openGcashHint"><?= e(__('Copies the number, then opens the GCash app. In GCash tap')) ?> <strong><?= e(__('Send → Express Send')) ?></strong><?= e(__(', paste the number and enter ₱:amount.', ['amount' => $feeText])) ?></p>

        <?php if ($gcash['has_qr']): ?>
        <div class="mt-5 text-center">
            <p class="text-sm font-bold" style="color:var(--text-primary);"><?= e($amountQr ? __('The barangay\'s plain QR (type the amount yourself)') : __('Or scan this QR in GCash')) ?></p>
            <img src="<?= e(route('payments/gcash-qr')) ?>" alt="Barangay GCash QR code for <?= e((string) $gcash['name']) ?>" class="gc-qr mx-auto mt-2" loading="lazy">
            <p class="gc-muted mt-2"><?= e(__('On a phone?')) ?> <a href="<?= e(route('payments/gcash-qr') . '?download=1') ?>" class="font-semibold underline"><?= e(__('Save the QR')) ?></a><?= e(__(', then in GCash tap')) ?> <strong><?= e(__('QR → Upload QR')) ?></strong>.</p>
        </div>
        <?php endif; ?>
    </section>

    <!-- Step 2: send the proof -->
    <section class="gc-card mb-4" aria-labelledby="step2">
        <h2 id="step2" class="mb-1 flex items-center gap-2 text-lg font-extrabold" style="color:var(--text-primary);"><span class="gc-step">2</span> <?= e(__('Send your proof of payment')) ?></h2>
        <p class="gc-muted mb-3"><?= e(__('After paying, come back here and upload the GCash receipt. The barangay verifies it, then your request is processed.')) ?></p>
        <?php $resend = false; require __DIR__ . '/_gcash-proof-form.php'; ?>
    </section>

    <?php if ($paymongoEnabled): ?>
    <div class="text-center">
        <button type="button" id="payBtn" class="text-sm font-semibold underline" style="color:var(--text-secondary);"><?= e(__('Or pay online through PayMongo instead')) ?></button>
        <p id="payAlert" class="mt-2 hidden text-sm" role="alert" style="color:var(--status-danger);"></p>
    </div>
    <?php endif; ?>
<?php endif; ?>
</div>

<script>
(function () {
    function copy(text, btn) {
        var done = function () { if (!btn) { return; } var old = btn.innerHTML; btn.innerHTML = '<i class="bi bi-check2"></i> Copied'; setTimeout(function () { btn.innerHTML = old; }, 1800); };
        if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done, function () {}); return; }
        var t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select();
        try { document.execCommand('copy'); done(); } catch (e) {} document.body.removeChild(t);
    }
    document.querySelectorAll('[data-copy]').forEach(function (b) { b.addEventListener('click', function () { copy(b.dataset.copy, b); }); });

    // The link that opens the GCash app for this phone, or null on a computer.
    window.gcashAppUrl = function (ua) {
        if (/android/i.test(ua)) {
            // Opens GCash; falls back to the Play Store when it is not installed.
            return 'intent://#Intent;scheme=gcash;package=com.globe.gcash.android;S.browser_fallback_url='
                + encodeURIComponent('https://play.google.com/store/apps/details?id=com.globe.gcash.android') + ';end';
        }
        if (/iphone|ipad|ipod/i.test(ua)) { return 'gcash://'; }
        return null;
    };

    document.querySelectorAll('[data-open-gcash]').forEach(function (open) {
        open.addEventListener('click', function (ev) {
            ev.preventDefault();
            copy(<?= json_encode($number) ?>, null);
            var ua = navigator.userAgent || '';
            var hint = document.getElementById('openGcashHint');
            var appUrl = window.gcashAppUrl(ua);
            if (appUrl && /android/i.test(ua)) {
                window.location.href = appUrl;
            } else if (appUrl) {
                window.location.href = appUrl;
                setTimeout(function () {
                    if (!document.hidden) { hint.innerHTML = <?= json_encode(__('If GCash did not open, open the GCash app yourself. The number :number is copied.')) ?>.replace(':number', '<strong>' + <?= json_encode($numberFmt) ?> + '</strong>'); }
                }, 1500);
            } else {
                hint.innerHTML = <?= json_encode($gcash['has_qr']
                    ? __('Open GCash on your phone and send ₱:amount to :number, or scan the QR below with GCash. The number is copied.', ['amount' => $feeText])
                    : __('Open GCash on your phone and send ₱:amount to :number. The number is copied.', ['amount' => $feeText])) ?>
                    .replace(':number', '<strong>' + <?= json_encode($numberFmt) ?> + '</strong>');
            }
        });
    });

<?php if ($inReview): ?>
    // Switch to <?= json_encode(__('Payment successful')) ?> (or show the rejection) as soon as staff decide.
    var statusUrl = <?= json_encode(route('api/payments/status/' . $reqId)) ?>;
    setInterval(function () {
        if (document.hidden) { return; }
        fetch(statusUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' }, cache: 'no-store' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) { if (d && d.payment_status && d.payment_status !== <?= json_encode($payStatus) ?>) { location.reload(); } })
            .catch(function () {});
    }, 5000);
<?php endif; ?>

<?php if ($paymongoEnabled && !$isPaid && !$inReview): ?>
    var payBtn = document.getElementById('payBtn');
    var payTop = document.getElementById('payBtnTop');
    if (payTop) { payTop.addEventListener('click', function () { payBtn.click(); payTop.disabled = true; payTop.textContent = <?= json_encode(__('Opening secure checkout…')) ?>; }); }
    payBtn.addEventListener('click', function () {
        payBtn.disabled = true; payBtn.textContent = <?= json_encode(__('Opening secure checkout…')) ?>;
        fetch(<?= json_encode(route('api/payments/paymongo/create-checkout')) ?>, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ request_id: <?= $reqId ?>, csrf_token: <?= json_encode(csrf_token()) ?> })
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (d.checkout_url) { window.location.href = d.checkout_url; return; }
            if (d.already_paid) { location.reload(); return; }
            throw new Error(d.error || <?= json_encode(__('Could not start the online checkout.')) ?>);
        }).catch(function (e) {
            payBtn.disabled = false; payBtn.textContent = <?= json_encode(__('Or pay online through PayMongo instead')) ?>;
            var a = document.getElementById('payAlert'); a.textContent = e.message; a.classList.remove('hidden');
        });
    });
<?php endif; ?>
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
