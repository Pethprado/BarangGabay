<?php
/**
 * Resident Payment Checkout Page — BarangGabay
 *
 * Requirements 7, 10, 18, 24, 37, 38, 39, 40, 41:
 * - GCash through PayMongo ONLY (no PayPal, no cards, no manual QR screenshots)
 * - Single clean summary card
 * - Double-click protection displaying "CREATING SECURE PAYMENT..."
 * - Handling of cancelled, failed, and already paid states
 */
use App\Models\DocumentRequest;
use App\Models\DocumentPayment;
use App\Services\PayMongoService;

$pageTitle = $pageTitle ?? 'Payment Checkout — BarangGabay';
$request   = $request ?? [];
$payment   = $payment ?? [];

$reqId     = (int) ($request['id'] ?? 0);
$docType   = (string) ($request['document_type'] ?? 'clearance');
$docLabel  = DocumentRequest::label($docType);
$fee       = (float) ($payment['amount_due'] ?? ($request['fee_amount'] ?? 50.0));
$reqRef    = (string) ($request['reference_no'] ?? '');
$payRef    = (string) ($payment['payment_ref'] ?? ('PAY-' . date('Y') . '-' . sprintf('%06d', $reqId)));
$receiving = (string) ($request['delivery_method'] ?? 'digital') === 'digital' ? 'Digital Copy' : 'Pickup at Barangay Hall';
$payStatus = (string) ($payment['payment_status'] ?? ($request['payment_status'] ?? 'PENDING'));

$isPaid = in_array($payStatus, [
    DocumentPayment::STATUS_PAID,
    DocumentPayment::STATUS_PAID_VERIFIED,
    DocumentPayment::STATUS_PAID_AT_PICKUP,
    DocumentPayment::STATUS_FREE,
    DocumentPayment::STATUS_NOT_REQUIRED,
    DocumentPayment::STATUS_WAIVED
], true);

$isCancelled = ($payStatus === DocumentPayment::STATUS_CANCELLED) || (isset($_GET['status']) && $_GET['status'] === 'cancelled');
$isFailed    = ($payStatus === DocumentPayment::STATUS_FAILED) || (isset($_GET['status']) && $_GET['status'] === 'failed');
$isExpired   = ($payStatus === DocumentPayment::STATUS_EXPIRED) || (isset($_GET['status']) && $_GET['status'] === 'expired');

$paymongoMode = PayMongoService::getMode();

ob_start();
?>

<div class="mx-auto max-w-xl px-4 py-8 sm:px-6">

    <!-- Top Navigation -->
    <div class="mb-5 flex items-center justify-between">
        <a href="<?= e(route('documents?tab=requests')) ?>"
           class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-800 transition dark:text-slate-400 dark:hover:text-white">
            <i class="bi bi-arrow-left"></i>
            <span>Bumalik sa Aking Mga Kahilingan</span>
        </a>
        <div class="flex items-center gap-2 text-xs">
            <span class="inline-flex items-center gap-1 font-semibold text-emerald-600 dark:text-emerald-400">
                <i class="bi bi-shield-check"></i>
                <span>SSL Encrypted</span>
            </span>
            <span class="text-slate-300 dark:text-slate-700">&bull;</span>
            <span class="inline-flex items-center gap-1 text-slate-500 font-medium dark:text-slate-400">
                <i class="bi bi-wallet2 text-blue-600"></i>
                <span>PayMongo GCash</span>
            </span>
        </div>
    </div>

    <!-- Main Payment Card Container -->
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl sm:p-8 dark:border-slate-800 dark:bg-slate-900 transition-all">

        <!-- Header -->
        <div class="mb-5 pb-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-blue-600 dark:text-blue-400 block mb-0.5">ONLINE CHECKOUT</span>
                <h1 class="text-xl font-black text-slate-900 dark:text-white">Magbayad gamit ang GCash</h1>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-600 text-white font-black text-xl shadow-md shadow-blue-500/20">
                G
            </div>
        </div>

        <!-- Alerts: Cancelled, Failed, or Expired (Requirements 39, 40, 41) -->
        <?php if ($isCancelled): ?>
        <div class="mb-5 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-xs font-semibold text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
            <div class="flex items-start gap-2.5">
                <i class="bi bi-exclamation-triangle-fill text-amber-600 text-base shrink-0 mt-0.5"></i>
                <div>
                    <strong class="block text-sm font-bold">PAYMENT CANCELLED</strong>
                    <span>Kinansela mo ang transaksyon. Naka-save pa rin ang inyong kahilingan at maaari itong bayaran muli sa ibaba.</span>
                </div>
            </div>
        </div>
        <?php elseif ($isFailed): ?>
        <div class="mb-5 rounded-2xl border border-rose-300 bg-rose-50 p-4 text-xs font-semibold text-rose-900 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
            <div class="flex items-start gap-2.5">
                <i class="bi bi-x-circle-fill text-rose-600 text-base shrink-0 mt-0.5"></i>
                <div>
                    <strong class="block text-sm font-bold">PAYMENT UNSUCCESSFUL</strong>
                    <span>Hindi natapos ang inyong pagbabayad sa GCash. Mangyaring subukang muli.</span>
                </div>
            </div>
        </div>
        <?php elseif ($isExpired): ?>
        <div class="mb-5 rounded-2xl border border-slate-300 bg-slate-50 p-4 text-xs font-semibold text-slate-700 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-300">
            <div class="flex items-start gap-2.5">
                <i class="bi bi-clock-history text-slate-500 text-base shrink-0 mt-0.5"></i>
                <div>
                    <strong class="block text-sm font-bold">PAYMENT EXPIRED</strong>
                    <span>Nag-expire na ang nakaraang checkout session. Maaari kang magsimula ng bagong payment session sa parehong kahilingan.</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Clean Order Summary (Requirement 24) -->
        <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-800/40 mb-6 space-y-3 text-xs">
            <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">REQUEST SUMMARY</div>

            <div class="flex items-center justify-between border-b border-slate-200/60 pb-2.5 dark:border-slate-700">
                <span class="text-slate-500 dark:text-slate-400">Dokumento (Document):</span>
                <strong class="text-slate-900 dark:text-white"><?= e($docLabel) ?></strong>
            </div>

            <div class="flex items-center justify-between border-b border-slate-200/60 pb-2.5 dark:border-slate-700">
                <span class="text-slate-500 dark:text-slate-400">Reference Number:</span>
                <span class="font-mono font-bold text-slate-900 dark:text-white"><?= e($reqRef) ?></span>
            </div>

            <div class="flex items-center justify-between border-b border-slate-200/60 pb-2.5 dark:border-slate-700">
                <span class="text-slate-500 dark:text-slate-400">Paraan ng Pagtanggap:</span>
                <strong class="text-slate-900 dark:text-white"><?= e($receiving) ?></strong>
            </div>

            <div class="flex items-center justify-between border-b border-slate-200/60 pb-2.5 dark:border-slate-700">
                <span class="text-slate-500 dark:text-slate-400">Paraan ng Pagbabayad:</span>
                <span class="inline-flex items-center gap-1.5 font-bold text-blue-700 dark:text-blue-300">
                    <i class="bi bi-check-circle-fill text-blue-600"></i> GCash via PayMongo
                </span>
            </div>

            <div class="flex items-center justify-between border-b border-slate-200/60 pb-2.5 dark:border-slate-700">
                <span class="text-slate-500 dark:text-slate-400">Bayad sa Dokumento (Fee):</span>
                <span class="font-bold text-slate-900 dark:text-white">₱<?= number_format($fee, 2) ?></span>
            </div>

            <div class="flex items-center justify-between pt-1">
                <span class="text-sm font-black text-slate-900 dark:text-white">KABUUAN (TOTAL):</span>
                <span class="text-2xl font-black text-blue-600 dark:text-blue-400">₱<?= number_format($fee, 2) ?></span>
            </div>
        </div>

        <!-- Dynamic Alert Container -->
        <div id="checkoutAlert" class="hidden mb-4 rounded-xl p-3.5 text-xs font-semibold"></div>

        <!-- Action: Pay or Already Paid -->
        <?php if ($isPaid): ?>
        <div class="text-center py-4">
            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400">
                <i class="bi bi-check-lg text-3xl"></i>
            </div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white mb-1">NABAYARAN NA ANG KAHILINGANG ITO</h3>
            <p class="text-xs text-slate-500 mb-5">
                Naberipika na ang inyong bayad at kasalukuyan nang sinusuri ng barangay.
            </p>
            <a href="<?= e(route('documents?tab=requests')) ?>"
               class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-blue-700">
                Tingnan sa Aking Mga Kahilingan
            </a>
        </div>
        <?php else: ?>
        <div>
            <!-- Pay Button with double click protection (Requirement 38) -->
            <button type="button" id="payGcashBtn" onclick="initiatePayMongoGCash()"
                    class="w-full inline-flex items-center justify-center gap-2.5 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 py-4 px-6 text-sm font-black text-white shadow-lg shadow-blue-500/25 hover:from-blue-700 hover:to-indigo-700 active:scale-[0.99] transition">
                <i class="bi bi-lock-fill text-base"></i>
                <span id="payGcashBtnText">
                    <?= ($isCancelled || $isFailed || $isExpired) ? 'SUBUKANG MULI ANG PAGBABAYAD (TRY AGAIN)' : 'PAY ₱' . number_format($fee, 2) . ' WITH GCASH' ?>
                </span>
            </button>

            <!-- Loading Spinner (hidden by default) -->
            <div id="payGcashSpinner" class="hidden text-center py-4">
                <div class="h-8 w-8 animate-spin rounded-full border-3 border-blue-600 border-t-transparent mx-auto mb-2"></div>
                <span class="text-xs font-bold text-slate-600 dark:text-slate-400">Inililipat sa opisyal na PayMongo GCash checkout...</span>
            </div>

            <p class="mt-4 text-center text-[11px] text-slate-400 dark:text-slate-500">
                <i class="bi bi-lock-fill me-1"></i> Ligtas na pinoproseso ng PayMongo. Awtomatikong mabe-verify ang inyong bayad nang walang screenshot.
            </p>
        </div>
        <?php endif; ?>

    </div>

</div>

<script>
function initiatePayMongoGCash() {
    var btn = document.getElementById('payGcashBtn');
    var btnText = document.getElementById('payGcashBtnText');
    var spinner = document.getElementById('payGcashSpinner');
    var alertBox = document.getElementById('checkoutAlert');

    // Requirement 38: Immediately disable and show CREATING SECURE PAYMENT...
    btn.disabled = true;
    btn.classList.add('opacity-75', 'cursor-not-allowed');
    btnText.innerText = 'CREATING SECURE PAYMENT...';
    spinner.classList.remove('hidden');
    alertBox.classList.add('hidden');

    fetch('<?= e(route('api/payments/paymongo/create-checkout')) ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            request_id: <?= (int) $reqId ?>,
            payment_id: <?= (int) ($payment['id'] ?? 0) ?>
        })
    })
    .then(function(res) {
        return res.json();
    })
    .then(function(data) {
        if (!data || !data.success || !data.checkout_url) {
            btn.disabled = false;
            btn.classList.remove('opacity-75', 'cursor-not-allowed');
            btnText.innerText = 'PAY ₱<?= number_format($fee, 2) ?> WITH GCASH';
            spinner.classList.add('hidden');

            alertBox.className = "mb-4 rounded-xl p-3.5 text-xs font-semibold bg-rose-50 text-rose-800 border border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-900";
            alertBox.innerText = data.error || 'Hindi mabuo ang checkout session. Pakisubukan muli.';
            alertBox.classList.remove('hidden');
            return;
        }

        // Direct resident to official PayMongo GCash hosted checkout
        btnText.innerText = 'REDIRECTING TO GCASH...';
        window.location.href = data.checkout_url;
    })
    .catch(function(err) {
        console.error('PayMongo Checkout Error:', err);
        btn.disabled = false;
        btn.classList.remove('opacity-75', 'cursor-not-allowed');
        btnText.innerText = 'PAY ₱<?= number_format($fee, 2) ?> WITH GCASH';
        spinner.classList.add('hidden');

        alertBox.className = "mb-4 rounded-xl p-3.5 text-xs font-semibold bg-rose-50 text-rose-800 border border-rose-200";
        alertBox.innerText = 'May naganap na aberya sa koneksyon sa PayMongo. Pakisubukan muli.';
        alertBox.classList.remove('hidden');
    });
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
