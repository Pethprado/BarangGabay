<?php
/**
 * Real-time Payment Confirmation Page — BarangGabay
 *
 * Requirements 16, 18, 43:
 * - Server-side webhook is payment source of truth.
 * - Shows "VERIFYING PAYMENT..." then polls backend status.
 * - When verified, shows "PAYMENT SUCCESSFUL ✓", Amount, GCash, Reference, Document, Status.
 */
use App\Models\DocumentRequest;
use App\Models\DocumentPayment;

$pageTitle = $pageTitle ?? 'Katayuan ng Pagbabayad — BarangGabay';
$request   = $request ?? [];
$payment   = $payment ?? [];

$reqId     = (int) ($request['id'] ?? 0);
$docType   = (string) ($request['document_type'] ?? 'clearance');
$docLabel  = DocumentRequest::label($docType);
$reqRef    = (string) ($request['reference_no'] ?? '');
$payRef    = (string) ($payment['payment_ref'] ?? ('PAY-' . date('Y') . '-' . sprintf('%06d', $reqId)));
$fee       = (float) ($payment['amount_due'] ?? ($request['fee_amount'] ?? 0));
$receiving = (string) ($request['delivery_method'] ?? 'digital') === 'digital' ? 'Digital Copy' : 'Pickup at Barangay Hall';

$isInitialPaid = in_array($payment['payment_status'] ?? '', [
    DocumentPayment::STATUS_PAID,
    DocumentPayment::STATUS_PAID_VERIFIED,
    DocumentPayment::STATUS_PAID_AT_PICKUP,
    DocumentPayment::STATUS_NOT_REQUIRED,
    DocumentPayment::STATUS_FREE,
    DocumentPayment::STATUS_WAIVED
], true);

ob_start();
?>

<div class="mx-auto max-w-xl px-4 py-10 sm:px-6">

    <!-- Card Container -->
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl sm:p-8 dark:border-slate-800 dark:bg-slate-900 transition-all">

        <!-- ================= STATE 1: VERIFYING PAYMENT ================= -->
        <div id="verifyingState" class="<?= $isInitialPaid ? 'hidden' : '' ?> text-center py-6">
            <div class="relative mx-auto mb-5 h-20 w-20 flex items-center justify-center">
                <div class="absolute inset-0 rounded-full border-4 border-blue-100 dark:border-blue-950"></div>
                <div class="h-20 w-20 animate-spin rounded-full border-4 border-blue-600 border-t-transparent dark:border-blue-400"></div>
                <i class="bi bi-wallet2 text-2xl text-blue-600 dark:text-blue-400"></i>
            </div>

            <h2 class="text-xl font-black text-slate-900 dark:text-white mb-2">VERIFYING PAYMENT...</h2>
            <p class="text-sm text-slate-600 dark:text-slate-400 max-w-md mx-auto mb-4">
                Kinukumpirma ang inyong transaksyon sa PayMongo at GCash server. Mangyaring maghintay ng ilang sandali...
            </p>

            <div class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-3.5 py-1.5 text-xs font-semibold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-600"></span>
                </span>
                <span>PAYMENT IS BEING CONFIRMED</span>
            </div>

            <div class="mt-6 border-t border-slate-100 pt-5 text-xs text-slate-400 dark:border-slate-800">
                Hindi kailangang i-refresh ang page; awtomatikong mag-a-update ito sa sandaling matanggap ang kumpirmasyon.
            </div>
        </div>

        <!-- ================= STATE 2: PAYMENT SUCCESSFUL ================= -->
        <div id="successState" class="<?= $isInitialPaid ? '' : 'hidden' ?> text-center py-2">
            <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400 shadow-sm">
                <i class="bi bi-check-circle-fill text-4xl"></i>
            </div>

            <h2 class="text-2xl font-black text-slate-900 dark:text-white mb-1">PAYMENT SUCCESSFUL ✓</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-6">
                Matagumpay na natanggap at naberipika ang inyong bayad.
            </p>

            <!-- Clean Transaction Summary -->
            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-5 text-left text-xs space-y-3 dark:border-slate-800 dark:bg-slate-800/40 mb-6">
                <div class="flex items-center justify-between border-b border-slate-200/60 pb-2.5 dark:border-slate-700">
                    <span class="text-slate-500 dark:text-slate-400">Halaga (Amount):</span>
                    <span class="text-base font-black text-emerald-600 dark:text-emerald-400" id="resAmount">
                        ₱<?= number_format($fee, 2) ?>
                    </span>
                </div>

                <div class="flex items-center justify-between border-b border-slate-200/60 pb-2.5 dark:border-slate-700">
                    <span class="text-slate-500 dark:text-slate-400">Paraan ng Bayad (Method):</span>
                    <span class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5" id="resMethod">
                        <span class="rounded bg-blue-600 text-white px-1.5 py-0.2 text-[10px] font-black">G</span> GCash via PayMongo
                    </span>
                </div>

                <div class="flex items-center justify-between border-b border-slate-200/60 pb-2.5 dark:border-slate-700">
                    <span class="text-slate-500 dark:text-slate-400">Payment Reference:</span>
                    <span class="font-mono font-bold text-slate-900 dark:text-white" id="resPayRef">
                        <?= e($payRef) ?>
                    </span>
                </div>

                <div class="flex items-center justify-between border-b border-slate-200/60 pb-2.5 dark:border-slate-700">
                    <span class="text-slate-500 dark:text-slate-400">Dokumento (Document):</span>
                    <span class="font-bold text-slate-900 dark:text-white" id="resDocLabel">
                        <?= e($docLabel) ?> (<?= e($reqRef) ?>)
                    </span>
                </div>

                <div class="flex items-center justify-between border-b border-slate-200/60 pb-2.5 dark:border-slate-700">
                    <span class="text-slate-500 dark:text-slate-400">Paraan ng Pagtanggap:</span>
                    <span class="font-bold text-slate-900 dark:text-white">
                        <?= e($receiving) ?>
                    </span>
                </div>

                <div class="flex items-center justify-between pt-1">
                    <span class="text-slate-500 dark:text-slate-400">Estado ng Kahilingan:</span>
                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-[11px] font-bold text-blue-800 dark:bg-blue-900/60 dark:text-blue-300" id="resStatus">
                        ● Pending Barangay Review
                    </span>
                </div>
            </div>

            <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed mb-6">
                Ang inyong kahilingan ay nasa ilalim na ng pagsusuri ng kawani ng Barangay. Makatatanggap kayo ng abiso sa sandaling maaprubahan at mai-release ang inyong dokumento.
            </p>

            <a href="<?= e(route('documents?tab=requests&ref=' . urlencode($reqRef))) ?>"
               class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white shadow-md hover:bg-blue-700 active:scale-[0.99] transition">
                <i class="bi bi-folder2-open"></i>
                <span>Tingnan ang Aking Kahilingan (VIEW MY REQUEST)</span>
            </a>
        </div>

        <!-- ================= STATE 3: PAYMENT UNSUCCESSFUL / CANCELLED ================= -->
        <div id="failedState" class="hidden text-center py-4">
            <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-rose-100 text-rose-600 dark:bg-rose-950 dark:text-rose-400 shadow-sm">
                <i class="bi bi-x-circle-fill text-4xl"></i>
            </div>

            <h2 class="text-2xl font-black text-rose-600 dark:text-rose-400 mb-1" id="failTitle">PAYMENT UNSUCCESSFUL</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-6" id="failDesc">
                Hindi natapos ang inyong online payment. Naka-save pa rin ang inyong kahilingan at maaari itong subukang muli.
            </p>

            <div class="flex flex-col sm:flex-row gap-3">
                <a href="<?= e(route('documents/' . $reqId . '/payment')) ?>"
                   class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-3 text-xs font-bold text-white shadow-md hover:bg-blue-700 transition">
                    <i class="bi bi-arrow-repeat"></i>
                    <span>Subukang Muli (TRY AGAIN)</span>
                </a>
                <a href="<?= e(route('documents?tab=requests')) ?>"
                   class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-3 text-xs font-bold text-slate-700 hover:bg-slate-50 transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                    <span>Aking Mga Kahilingan</span>
                </a>
            </div>
        </div>

    </div>

</div>

<script>
(function() {
    var requestId = <?= (int) $reqId ?>;
    var isInitiallyPaid = <?= $isInitialPaid ? 'true' : 'false' ?>;

    if (isInitiallyPaid) {
        return;
    }

    var pollInterval = null;
    var pollCount = 0;
    var maxPolls = 30; // Poll for up to 60 seconds (every 2 seconds)

    function checkPaymentStatus() {
        pollCount++;
        fetch('<?= e(route('api/payments/status/')) ?>' + requestId, {
            headers: { 'Accept': 'application/json' }
        })
        .then(function(res) {
            return res.json();
        })
        .then(function(data) {
            if (!data || !data.success) {
                return;
            }

            if (data.is_paid) {
                clearInterval(pollInterval);
                document.getElementById('verifyingState').classList.add('hidden');
                document.getElementById('failedState').classList.add('hidden');
                document.getElementById('successState').classList.remove('hidden');

                if (data.amount_due) document.getElementById('resAmount').innerText = '₱' + data.amount_due;
                if (data.payment_ref) document.getElementById('resPayRef').innerText = data.payment_ref;
                if (data.document_label) document.getElementById('resDocLabel').innerText = data.document_label;
                return;
            }

            var st = (data.payment_status || '').toUpperCase();
            if (st === 'FAILED' || st === 'CANCELLED') {
                clearInterval(pollInterval);
                document.getElementById('verifyingState').classList.add('hidden');
                document.getElementById('successState').classList.add('hidden');
                document.getElementById('failedState').classList.remove('hidden');
                if (st === 'CANCELLED') {
                    document.getElementById('failTitle').innerText = 'PAYMENT CANCELLED';
                    document.getElementById('failDesc').innerText = 'Kinansela mo ang online payment. Naka-save pa rin ang inyong kahilingan.';
                }
                return;
            }

            if (pollCount >= maxPolls) {
                clearInterval(pollInterval);
                // Even if timeout, give user option to view requests or retry
                document.getElementById('verifyingState').innerHTML = `
                    <div class="text-center py-4">
                        <i class="bi bi-clock-history text-4xl text-amber-500 mb-3 block"></i>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-1">Sinusuri pa ang transaksyon</h3>
                        <p class="text-xs text-slate-500 mb-5">
                            Maaaring abutin ng ilang sandali ang pagdating ng opisyal na kumpirmasyon mula sa GCash. Awtomatiko itong magre-reflect sa inyong dashboard.
                        </p>
                        <a href="<?= e(route('documents?tab=requests')) ?>" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-blue-700">
                            Pumunta sa Aking Mga Kahilingan
                        </a>
                    </div>
                `;
            }
        })
        .catch(function(err) {
            console.error('Status check error:', err);
        });
    }

    // Start short polling every 2 seconds
    pollInterval = setInterval(checkPaymentStatus, 2000);
    // Immediate first check
    checkPaymentStatus();
})();
</script>

<?php
$content = ob_get_clean();
view('layouts/app', compact('content', 'pageTitle'));
