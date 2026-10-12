<?php
/**
 * Resident — Pay with GCash (PayMongo hosted checkout).
 *
 * The amount shown and charged is the server-side fee stored on the request.
 * The button only asks the server to start (or reuse) a checkout; PayMongo,
 * not this page, decides whether the payment succeeded.
 */
use App\Models\DocumentPayment;
use App\Models\DocumentRequest;
use App\Services\PayMongoService;

$request = $request ?? [];
$payment = $payment ?? [];

$reqId     = (int) ($request['id'] ?? 0);
$docLabel  = DocumentRequest::label((string) ($request['document_type'] ?? ''));
$fee       = (float) ($payment['amount_due'] ?? 0);
$reqRef    = (string) ($request['reference_no'] ?? '');
$payRef    = (string) ($payment['payment_ref'] ?? '');
$receiving = DocumentRequest::deliveryLabel((string) ($request['delivery_method'] ?? 'pickup'));
$payStatus = (string) ($payment['payment_status'] ?? DocumentPayment::STATUS_PENDING);

$isPaid      = in_array($payStatus, DocumentPayment::SETTLED_STATES, true);
$inReview    = in_array($payStatus, DocumentPayment::FILTER_GROUPS['manual_review'], true);
$isRetry     = in_array($payStatus, [DocumentPayment::STATUS_CANCELLED, DocumentPayment::STATUS_FAILED, DocumentPayment::STATUS_EXPIRED, DocumentPayment::STATUS_PAYMENT_REJECTED], true);
$hasCheckout = !empty($payment['paymongo_checkout_id']);
$configured  = $isPaymongoConfigured ?? PayMongoService::isConfigured();
$testMode    = PayMongoService::getMode() === 'test';

$notice = match ($payStatus) {
    DocumentPayment::STATUS_CANCELLED        => ['amber', 'Payment not completed', 'Hindi natapos ang pagbabayad. Walang nabawas — maaari kayong magbayad muli.'],
    DocumentPayment::STATUS_FAILED           => ['rose',  'Payment failed', 'Hindi natuloy ang GCash payment. Walang nabawas — subukang muli.'],
    DocumentPayment::STATUS_EXPIRED          => ['slate', 'Checkout expired', 'Nag-expire ang nakaraang checkout. Magsimula ng bago sa ibaba.'],
    DocumentPayment::STATUS_PAYMENT_REJECTED => ['rose',  'Proof not accepted', 'Hindi tinanggap ang ipinadalang patunay: ' . (string) ($payment['rejection_reason'] ?? '') . '. Magbayad muli gamit ang GCash.'],
    default                                  => null,
};

ob_start();
?>
<div class="mx-auto max-w-xl px-4 py-8 sm:px-6">
    <a href="<?= e(route('documents?tab=requests')) ?>" class="mb-5 inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-slate-900 dark:text-slate-300">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Aking Mga Kahilingan
    </a>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="payTitle">
        <div class="mb-5 flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Document payment</p>
                <h1 id="payTitle" class="text-2xl font-extrabold text-slate-900 dark:text-white">Pay with GCash</h1>
            </div>
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                <i class="bi bi-shield-lock-fill" aria-hidden="true"></i> Secured by PayMongo
            </span>
        </div>

        <?php if ($testMode && !$isPaid): ?>
        <p class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-3 text-xs font-semibold text-amber-900 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-200">
            <i class="bi bi-cone-striped" aria-hidden="true"></i> TEST MODE — no real money is charged. PayMongo shows a test GCash page.
        </p>
        <?php endif; ?>

        <?php if ($notice): [$tone, $title, $body] = $notice; ?>
        <div class="mb-4 rounded-xl border p-3 text-sm <?= $tone === 'rose' ? 'border-rose-300 bg-rose-50 text-rose-900 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-200' : ($tone === 'amber' ? 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-200' : 'border-slate-300 bg-slate-50 text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200') ?>" role="status">
            <strong class="block"><?= e($title) ?></strong><?= e($body) ?>
        </div>
        <?php endif; ?>

        <dl class="mb-6 divide-y divide-slate-100 rounded-xl border border-slate-100 bg-slate-50 px-4 text-sm dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-800/40">
            <div class="flex justify-between gap-4 py-2.5"><dt class="text-slate-600 dark:text-slate-400">Document</dt><dd class="font-semibold text-slate-900 dark:text-white"><?= e($docLabel) ?></dd></div>
            <div class="flex justify-between gap-4 py-2.5"><dt class="text-slate-600 dark:text-slate-400">Request no.</dt><dd class="font-mono font-semibold text-slate-900 dark:text-white"><?= e($reqRef) ?></dd></div>
            <div class="flex justify-between gap-4 py-2.5"><dt class="text-slate-600 dark:text-slate-400">Payment ref.</dt><dd class="font-mono text-slate-900 dark:text-white"><?= e($payRef) ?></dd></div>
            <div class="flex justify-between gap-4 py-2.5"><dt class="text-slate-600 dark:text-slate-400">Claiming</dt><dd class="text-slate-900 dark:text-white"><?= e($receiving) ?></dd></div>
            <div class="flex justify-between gap-4 py-2.5"><dt class="text-slate-600 dark:text-slate-400">Payment status</dt><dd class="font-semibold text-slate-900 dark:text-white"><?= e(DocumentPayment::statusLabel($payStatus)) ?></dd></div>
            <div class="flex items-center justify-between gap-4 py-3"><dt class="font-bold text-slate-900 dark:text-white">Total</dt><dd class="text-2xl font-extrabold text-emerald-700 dark:text-emerald-400">₱<?= number_format($fee, 2) ?></dd></div>
        </dl>

        <?php if ($isPaid): ?>
            <div class="rounded-xl bg-emerald-50 p-4 text-center dark:bg-emerald-950/50" role="status">
                <i class="bi bi-check-circle-fill text-3xl text-emerald-600" aria-hidden="true"></i>
                <p class="mt-1 font-bold text-slate-900 dark:text-white">Paid</p>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    <?= ($payment['verification_type'] ?? '') === 'paymongo' ? 'Automatically verified through PayMongo' : 'Recorded by the barangay' ?><?= !empty($payment['paid_at']) ? ' · ' . e(format_datetime((string) $payment['paid_at'])) : '' ?>.
                    Ipoproseso na ang inyong dokumento.
                </p>
            </div>
        <?php elseif ($inReview): ?>
            <div class="rounded-xl bg-amber-50 p-4 text-center dark:bg-amber-950/50" role="status">
                <i class="bi bi-hourglass-split text-3xl text-amber-600" aria-hidden="true"></i>
                <p class="mt-1 font-bold text-slate-900 dark:text-white">In manual review</p>
                <p class="text-sm text-slate-600 dark:text-slate-300">Sinusuri ng barangay staff ang inyong bayad. Huwag munang magbayad muli — makakatanggap kayo ng abiso.</p>
            </div>
        <?php elseif (!$configured): ?>
            <div class="rounded-xl bg-slate-100 p-4 text-sm text-slate-700 dark:bg-slate-800 dark:text-slate-200" role="status">
                Online payment is not available right now. Please pay at the Barangay Hall, or try again later.
            </div>
        <?php else: ?>
            <div id="payAlert" class="mb-3 hidden rounded-xl border border-rose-300 bg-rose-50 p-3 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-200" role="alert"></div>
            <button type="button" id="payBtn"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-700 px-6 py-4 text-base font-bold text-white shadow-sm transition hover:bg-emerald-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-emerald-300 disabled:cursor-progress disabled:opacity-80">
                <i class="bi bi-wallet2" aria-hidden="true"></i>
                <span id="payBtnText"><?= $isRetry ? 'Try again — ' : '' ?>Pay ₱<?= number_format($fee, 2) ?> with GCash</span>
            </button>
            <p class="mt-3 text-center text-xs text-slate-500 dark:text-slate-400">
                Dadalhin kayo sa opisyal na PayMongo page. Kapag tapos na, babalik kayo rito at awtomatikong kukumpirmahin ng PayMongo ang bayad — walang kailangang screenshot.
            </p>

            <?php if ($hasCheckout): ?>
            <details class="mt-6 rounded-xl border border-slate-200 p-4 text-sm dark:border-slate-700">
                <summary class="cursor-pointer font-semibold text-slate-700 dark:text-slate-200">Nagbayad na pero hindi pa nag-update ang status?</summary>
                <p class="mt-2 text-xs text-slate-600 dark:text-slate-400">
                    Ipadala ang inyong GCash reference number at screenshot. Ito ay mapupunta sa <strong>Manual Review</strong> — susuriin ito ng barangay staff laban sa PayMongo records. Hindi ito awtomatikong magiging "bayad na".
                </p>
                <form method="post" action="<?= e(route('documents/' . $reqId . '/payment')) ?>" enctype="multipart/form-data" class="mt-3 space-y-3">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div>
                        <label for="proofRef" class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">GCash reference no.</label>
                        <input id="proofRef" name="gcash_reference_no" required maxlength="100" inputmode="numeric"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                    </div>
                    <div>
                        <label for="proofFile" class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">Screenshot (JPG, PNG or PDF, max 10 MB)</label>
                        <input id="proofFile" name="receipt_file" type="file" required accept="image/jpeg,image/png,application/pdf"
                               class="w-full text-sm text-slate-700 dark:text-slate-300">
                    </div>
                    <input type="hidden" name="amount_reported" value="<?= e(number_format($fee, 2, '.', '')) ?>">
                    <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-100 dark:hover:bg-slate-800">Send for manual review</button>
                </form>
            </details>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<?php if (!$isPaid && !$inReview && $configured): ?>
<script>
(function () {
    var btn = document.getElementById('payBtn');
    var text = document.getElementById('payBtnText');
    var alertBox = document.getElementById('payAlert');
    var label = text.textContent;
    var busy = false;

    btn.addEventListener('click', function () {
        if (busy) { return; }           // double-click guard (the server also reuses an open session)
        busy = true;
        btn.disabled = true;
        text.textContent = 'Opening secure GCash checkout…';
        alertBox.classList.add('hidden');

        fetch(<?= json_encode(route('api/payments/paymongo/create-checkout')) ?>, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ request_id: <?= $reqId ?>, csrf_token: <?= json_encode(csrf_token()) ?> })
        })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (r) {
            if (r.ok && r.data.checkout_url) {
                text.textContent = 'Redirecting to GCash…';
                window.location.href = r.data.checkout_url;
                return;
            }
            if (r.data.already_paid) { window.location.reload(); return; }
            throw new Error(r.data.error || 'Could not start the payment. Please try again.');
        })
        .catch(function (err) {
            busy = false;
            btn.disabled = false;
            text.textContent = label;
            alertBox.textContent = err.message || 'Could not reach the server. Please try again.';
            alertBox.classList.remove('hidden');
        });
    });
})();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
