<?php
/**
 * Resident Payment Checkout Page — BarangGabay
 * Primary Online Payment: PayPal Checkout (Orders API v2 + PayPal JS SDK)
 * Secondary / Counter: Manual GCash & Pay at Pickup
 */
use App\Services\PayPalService;
use App\Models\DocumentRequest;

$pageTitle          = $pageTitle ?? 'Payment Checkout — BarangGabay';
$request            = $request ?? [];
$payment            = $payment ?? [];
$gcash              = $gcash ?? [];
$paypalClientId     = $paypalClientId ?? PayPalService::getClientId();
$paypalMode         = $paypalMode ?? PayPalService::getMode();
$isPaypalConfigured = $isPaypalConfigured ?? PayPalService::isConfigured();
$currency           = $currency ?? 'PHP';

$reqId     = (int) ($request['id'] ?? 0);
$docType   = (string) ($request['document_type'] ?? 'clearance');
$docLabel  = DocumentRequest::label($docType);
$fee       = (float) ($request['fee_amount'] ?? 0);
$reqRef    = (string) ($request['reference_no'] ?? '');
$payRef    = (string) ($payment['payment_ref'] ?? ('PAY-' . date('Y') . '-' . sprintf('%06d', $reqId)));
$delivery  = (string) ($request['delivery_method'] ?? 'digital');
$isDigital = $delivery === 'digital';
$payStatus = (string) ($payment['payment_status'] ?? ($request['payment_status'] ?? 'UNPAID'));
$docStatus = (string) ($request['status'] ?? 'awaiting_payment');

$isUnpaid   = in_array($payStatus, ['UNPAID', 'PAYMENT_PENDING'], true);
$isProofSub = in_array($payStatus, ['PAYMENT_PROOF_SUBMITTED', 'UNDER_REVIEW'], true);
$isVerified = in_array($payStatus, ['PAID_VERIFIED', 'PAID_AT_PICKUP', 'FREE', 'WAIVED'], true);
$isRejected = $payStatus === 'PAYMENT_REJECTED';
$isFailed   = in_array($payStatus, ['FAILED'], true) || (isset($_GET['status']) && $_GET['status'] === 'failed');
$isCancelled= in_array($payStatus, ['CANCELLED'], true) || (isset($_GET['status']) && $_GET['status'] === 'cancelled');

// Active GCash Account Details (as secondary option)
$accountName  = (string) ($gcash['account_name'] ?? 'Barangay Bayogo Official');
$mobileNumber = (string) ($gcash['mobile_number'] ?? '0917 123 4567');
$cleanMobile  = preg_replace('/[^0-9]/', '', $mobileNumber);
$qrImageBase64= (string) ($gcash['qr_image_data'] ?? '');
$qrMimeType   = (string) ($gcash['qr_mime_type'] ?? 'image/png');
$hasQr        = !empty($qrImageBase64);
$qrSrc        = $hasQr ? ('data:' . $qrMimeType . ';base64,' . $qrImageBase64) : '';

ob_start();
?>

<div class="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8">

    <!-- Top Back Navigation -->
    <div class="mb-5 flex items-center justify-between">
        <a href="<?= e(route('documents')) ?>"
           class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 hover:text-slate-800 transition dark:text-slate-400 dark:hover:text-slate-200">
            <i class="bi bi-arrow-left text-sm"></i>
            <span>Bumalik sa Aking Mga Kahilingan</span>
        </a>
        <div class="flex items-center gap-2 text-xs">
            <span class="inline-flex items-center gap-1 font-semibold text-emerald-600 dark:text-emerald-400">
                <i class="bi bi-shield-check"></i>
                <span>SSL Encrypted</span>
            </span>
            <span class="text-slate-300 dark:text-slate-700">&bull;</span>
            <span class="inline-flex items-center gap-1 text-slate-500 dark:text-slate-400 font-medium">
                <i class="bi bi-paypal text-blue-600 dark:text-blue-400"></i>
                <span>PayPal Official Partner</span>
            </span>
        </div>
    </div>

    <!-- Stepper (5 Steps) -->
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="grid grid-cols-5 text-center text-xs font-semibold">
            <!-- Step 1: Request (Done) -->
            <div class="flex flex-col items-center">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500 text-white shadow-sm mb-1.5">
                    <i class="bi bi-check-lg text-sm"></i>
                </div>
                <span class="text-emerald-700 dark:text-emerald-400 font-bold hidden sm:inline">1. Kahilingan</span>
                <span class="text-[10px] text-slate-400 sm:hidden">Form</span>
            </div>

            <!-- Step 2: Payment (Active or Done) -->
            <div class="flex flex-col items-center">
                <div class="flex h-8 w-8 items-center justify-center rounded-full <?= $isVerified ? 'bg-emerald-500 text-white' : ($isProofSub ? 'bg-amber-500 text-white ring-4 ring-amber-100 dark:ring-amber-950' : 'bg-blue-600 text-white ring-4 ring-blue-100 dark:ring-blue-950') ?> shadow-sm mb-1.5">
                    <?php if ($isVerified): ?>
                    <i class="bi bi-check-lg text-sm"></i>
                    <?php else: ?>
                    <i class="bi bi-credit-card text-xs"></i>
                    <?php endif; ?>
                </div>
                <span class="<?= $isVerified ? 'text-emerald-700 dark:text-emerald-400 font-bold' : 'text-blue-700 dark:text-blue-400 font-bold' ?> hidden sm:inline">2. Pagbabayad</span>
                <span class="text-[10px] text-slate-400 sm:hidden">Bayad</span>
            </div>

            <!-- Step 3: Verification -->
            <div class="flex flex-col items-center">
                <div class="flex h-8 w-8 items-center justify-center rounded-full <?= $isVerified ? 'bg-emerald-500 text-white' : ($isProofSub ? 'bg-amber-500 text-white animate-pulse' : 'bg-slate-100 text-slate-400 dark:bg-slate-800') ?> mb-1.5">
                    <?php if ($isVerified): ?>
                    <i class="bi bi-check-lg text-sm"></i>
                    <?php else: ?>
                    <i class="bi bi-patch-check text-xs"></i>
                    <?php endif; ?>
                </div>
                <span class="<?= $isVerified ? 'text-emerald-700 dark:text-emerald-400 font-bold' : ($isProofSub ? 'text-amber-700 dark:text-amber-400 font-bold' : 'text-slate-400') ?> hidden sm:inline">3. Beripikasyon</span>
                <span class="text-[10px] text-slate-400 sm:hidden">Suri</span>
            </div>

            <!-- Step 4: Processing -->
            <div class="flex flex-col items-center">
                <div class="flex h-8 w-8 items-center justify-center rounded-full <?= in_array($docStatus, ['pending', 'processing', 'ready', 'released', 'completed'], true) && $isVerified ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-400 dark:bg-slate-800' ?> mb-1.5">
                    <i class="bi bi-gear text-xs"></i>
                </div>
                <span class="<?= in_array($docStatus, ['pending', 'processing', 'ready', 'released', 'completed'], true) && $isVerified ? 'text-blue-700 font-bold' : 'text-slate-400' ?> hidden sm:inline">4. Paghahanda</span>
                <span class="text-[10px] text-slate-400 sm:hidden">Gawa</span>
            </div>

            <!-- Step 5: Ready -->
            <div class="flex flex-col items-center">
                <div class="flex h-8 w-8 items-center justify-center rounded-full <?= in_array($docStatus, ['ready', 'released', 'completed'], true) && $isVerified ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-400 dark:bg-slate-800' ?> mb-1.5">
                    <i class="bi bi-check-circle text-xs"></i>
                </div>
                <span class="<?= in_array($docStatus, ['ready', 'released', 'completed'], true) && $isVerified ? 'text-emerald-700 font-bold' : 'text-slate-400' ?> hidden sm:inline">5. Handa na</span>
                <span class="text-[10px] text-slate-400 sm:hidden">Tapos</span>
            </div>
        </div>
    </div>

    <!-- Main Grid: Invoice Summary + Payment Actions -->
    <div class="grid gap-6 lg:grid-cols-12">

        <!-- Left Column: Order / Request Summary (5 cols) -->
        <div class="lg:col-span-5 space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4 dark:border-slate-800">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-400">
                        <i class="bi bi-receipt text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Buod ng Transaksyon</h2>
                        <p class="text-xs text-slate-400">BARANGGABAY Online Payment</p>
                    </div>
                </div>

                <div class="divide-y divide-slate-100 text-xs dark:divide-slate-800">
                    <div class="py-3 flex justify-between">
                        <span class="text-slate-400">Dokumento:</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200"><?= e($docLabel) ?></span>
                    </div>
                    <div class="py-3 flex justify-between">
                        <span class="text-slate-400">Paraan ng Pagtanggap:</span>
                        <span class="font-semibold text-slate-700 dark:text-slate-300">
                            <?= $isDigital ? '📱 Digital Soft Copy' : '🏢 Personal Pickup' ?>
                        </span>
                    </div>
                    <div class="py-3 flex justify-between">
                        <span class="text-slate-400">Request Ref#:</span>
                        <span class="font-mono font-bold text-slate-900 dark:text-white"><?= e($reqRef) ?></span>
                    </div>
                    <div class="py-3 flex justify-between">
                        <span class="text-slate-400">Payment Ref#:</span>
                        <span class="font-mono font-bold text-blue-600 dark:text-blue-400"><?= e($payRef) ?></span>
                    </div>
                    <div class="py-3 flex justify-between">
                        <span class="text-slate-400">Primary Provider:</span>
                        <span class="inline-flex items-center gap-1 font-bold text-blue-600 dark:text-blue-400">
                            <i class="bi bi-paypal text-blue-500"></i> PayPal Checkout
                        </span>
                    </div>
                    <div class="py-3 flex justify-between">
                        <span class="text-slate-400">Currency:</span>
                        <span class="font-bold text-slate-700 dark:text-slate-300">PHP (Philippine Peso)</span>
                    </div>
                    <div class="pt-4 flex items-baseline justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Halagang Babayaran:</span>
                        <div class="text-2xl font-black text-slate-900 dark:text-white">
                            ₱<?= number_format($fee, 2) ?>
                        </div>
                    </div>
                </div>

                <!-- Status Badge Banner -->
                <div class="mt-4 rounded-xl p-3 text-xs <?= $isVerified ? 'bg-emerald-50 text-emerald-800 border border-emerald-200 dark:bg-emerald-950 dark:text-emerald-200' : ($isProofSub ? 'bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-950 dark:text-amber-200' : ($isRejected ? 'bg-rose-50 text-rose-800 border border-rose-200 dark:bg-rose-950 dark:text-rose-200' : ($isFailed ? 'bg-rose-50 text-rose-800 border border-rose-200 dark:bg-rose-950 dark:text-rose-200' : 'bg-blue-50 text-blue-800 border border-blue-200 dark:bg-blue-950 dark:text-blue-200'))) ?>">
                    <div class="flex items-center gap-2">
                        <?php if ($isVerified): ?>
                        <i class="bi bi-check-circle-fill text-emerald-600 text-base"></i>
                        <span class="font-bold">BERIPIKADO NA ANG BAYAD (PAID VERIFIED)</span>
                        <?php elseif ($isProofSub): ?>
                        <i class="bi bi-hourglass-split text-amber-600 text-base animate-pulse"></i>
                        <span class="font-bold">NAISUMITE ANG PATUNAY (Awaiting Review)</span>
                        <?php elseif ($isRejected): ?>
                        <i class="bi bi-exclamation-triangle-fill text-rose-600 text-base"></i>
                        <span class="font-bold">TINANGGIHAN ANG PATUNAY</span>
                        <?php elseif ($isFailed): ?>
                        <i class="bi bi-x-circle-fill text-rose-600 text-base"></i>
                        <span class="font-bold">BIGONG PAGBABAYAD (PAYMENT FAILED)</span>
                        <?php else: ?>
                        <i class="bi bi-wallet2 text-blue-600 text-base"></i>
                        <span class="font-bold">NAGHIHINTAY NG BAYAD (PAYMENT PENDING)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Mode & Security Info -->
                <div class="mt-4 flex items-center justify-between text-[11px] text-slate-400">
                    <span class="inline-flex items-center gap-1">
                        <span class="inline-block h-2 w-2 rounded-full <?= $paypalMode === 'live' ? 'bg-emerald-500' : 'bg-amber-500' ?>"></span>
                        <span>Mode: <?= strtoupper($paypalMode) ?></span>
                    </span>
                    <span class="text-slate-400">Orders API v2</span>
                </div>
            </div>

            <!-- Security Notice -->
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                <i class="bi bi-shield-lock-fill text-blue-600 me-1"></i>
                Hindi humihingi o nag-iimbak ang BARANGGABAY ng inyong PayPal password o credit card numbers. Ang transaksyon ay ligtas na pinoproseso sa opisyal na PayPal server.
            </div>
        </div>

        <!-- Right Column: Interactive Payment Checkout (7 cols) -->
        <div class="lg:col-span-7 space-y-5">

            <!-- ================= CASE 1: PAYMENT SUCCESS SCREEN ================= -->
            <?php if ($isVerified): ?>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-6 text-center dark:border-emerald-900 dark:bg-emerald-950/40">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-500 text-white shadow-md mb-4">
                    <i class="bi bi-check-circle-fill text-3xl"></i>
                </div>
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-[11px] font-black text-emerald-800 uppercase tracking-wider dark:bg-emerald-900 dark:text-emerald-200">
                    PAYMENT SUCCESSFUL
                </span>
                <h3 class="mt-3 text-lg font-bold text-emerald-950 dark:text-emerald-100">
                    Your PayPal payment has been confirmed.
                </h3>
                <p class="mt-1 text-xs text-emerald-800 dark:text-emerald-300">
                    Your request is now being processed by the Barangay Bayogo staff.
                </p>

                <!-- Receipt Card -->
                <div class="mx-auto mt-5 max-w-sm rounded-xl border border-emerald-200 bg-white p-4 text-left text-xs shadow-sm dark:border-emerald-900 dark:bg-slate-900">
                    <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-400">Document:</span>
                        <strong class="text-slate-800 dark:text-slate-100"><?= e($docLabel) ?></strong>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-400">Amount:</span>
                        <strong class="text-slate-800 dark:text-slate-100">₱<?= number_format($fee, 2) ?> PHP</strong>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-400">Payment Reference:</span>
                        <strong class="font-mono text-blue-600 dark:text-blue-400"><?= e($payRef) ?></strong>
                    </div>
                    <?php if (!empty($payment['paypal_order_id'])): ?>
                    <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-400">PayPal Order ID:</span>
                        <span class="font-mono text-slate-700 dark:text-slate-300"><?= e((string) $payment['paypal_order_id']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($payment['paypal_capture_id'])): ?>
                    <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-400">PayPal Capture ID:</span>
                        <span class="font-mono text-slate-700 dark:text-slate-300"><?= e((string) $payment['paypal_capture_id']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between py-1.5">
                        <span class="text-slate-400">Status:</span>
                        <span class="inline-flex items-center gap-1 font-bold text-emerald-600">
                            <i class="bi bi-patch-check-fill"></i> PAID VERIFIED
                        </span>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <a href="<?= e(route('documents')) ?>"
                       class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition">
                        <i class="bi bi-folder-check"></i>
                        <span>View My Request</span>
                    </a>
                    <a href="<?= e(route('documents/' . $reqId . '/acknowledgement')) ?>" target="_blank"
                       class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        <i class="bi bi-printer"></i>
                        <span>Print Acknowledgement</span>
                    </a>
                </div>
            </div>

            <!-- ================= CASE 2: PAYMENT FAILURE / CANCELLED NOTICES ================= -->
            <?php else: ?>

                <!-- Cancellation Banner -->
                <?php if ($isCancelled): ?>
                <div class="rounded-2xl border-2 border-amber-300 bg-amber-50 p-4 text-xs text-amber-950 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-200">
                    <div class="flex items-start gap-2.5">
                        <i class="bi bi-info-circle-fill text-amber-600 text-lg mt-0.5"></i>
                        <div>
                            <strong class="font-bold text-sm block">Payment Required</strong>
                            <p class="mt-0.5">
                                Hindi nakumpleto ang pagbabayad via PayPal. Naka-save pa rin ang inyong kahilingan (#<?= e($reqRef) ?>). I-click ang PayPal button sa ibaba upang magpatuloy.
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Failure Banner -->
                <?php if ($isFailed): ?>
                <div class="rounded-2xl border-2 border-rose-300 bg-rose-50 p-4 text-xs text-rose-950 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-200">
                    <div class="flex items-start gap-2.5">
                        <i class="bi bi-exclamation-octagon-fill text-rose-600 text-lg mt-0.5"></i>
                        <div>
                            <strong class="font-bold text-sm block">Payment was not completed.</strong>
                            <p class="mt-0.5 font-medium">
                                May naganap na problema sa transaksyon. Huwag mag-alala, hindi nabura ang inyong kahilingan.
                            </p>
                            <div class="mt-2.5">
                                <button type="button" onclick="location.reload()"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-rose-700 transition">
                                    <i class="bi bi-arrow-clockwise"></i> Try Again
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Client-Side Dynamic Alert Box -->
                <div id="dynamicAlertBox" class="hidden rounded-2xl border p-4 text-xs transition"></div>

                <!-- ================= PRIMARY: PAYPAL CHECKOUT CARD ================= -->
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-5 dark:border-slate-800">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">PAYMENT</span>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <span><?= e($docLabel) ?></span>
                            </h3>
                            <div class="mt-1 text-xs text-slate-500">
                                Amount: <strong class="text-slate-900 dark:text-white font-bold">₱<?= number_format($fee, 2) ?> PHP</strong>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                                Online Payment
                            </span>
                        </div>
                    </div>

                    <!-- PayPal Checkout UI Container -->
                    <div class="space-y-4">
                        <div class="text-xs text-slate-600 dark:text-slate-300">
                            Magbayad nang direkta gamit ang inyong <strong>PayPal account</strong>, o magbayad bilang guest gamit ang anumang <strong>Debit o Credit Card</strong>:
                        </div>

                        <!-- PayPal Button Render Target -->
                        <div id="paypal-button-container" class="relative min-h-[140px] z-10">
                            <!-- Loading Skeleton while SDK initializes -->
                            <div id="paypalLoadingPlaceholder" class="flex flex-col items-center justify-center py-8 text-center text-xs text-slate-400">
                                <div class="h-8 w-8 animate-spin rounded-full border-2 border-blue-600 border-t-transparent mb-2"></div>
                                <span>Inihahanda ang secure PayPal Checkout...</span>
                            </div>
                        </div>

                        <!-- Sandbox Test Fast-Forward Button (Development / Testing Mode) -->
                        <div id="sandboxSimulationSection" class="pt-2 border-t border-slate-100 dark:border-slate-800">
                            <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-3 text-xs dark:border-amber-900/60 dark:bg-amber-950/30">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-amber-900 dark:text-amber-200 font-semibold">
                                        <i class="bi bi-flask text-amber-600"></i>
                                        <span>Sandbox / Demo Environment</span>
                                    </div>
                                    <span class="text-[10px] text-amber-700 dark:text-amber-400 font-mono">PAYPAL_MODE=<?= e($paypalMode) ?></span>
                                </div>
                                <p class="mt-1 text-[11px] text-amber-800 dark:text-amber-300">
                                    Maaaring gamitin ang official PayPal sandbox button sa itaas, o i-test ang complete server-side order & capture workflow gamit ang simulation tester:
                                </p>
                                <button type="button" id="simulateSandboxPaymentBtn" onclick="runSandboxSimulation()"
                                        class="mt-2.5 inline-flex items-center gap-2 rounded-xl bg-amber-600 px-3.5 py-2 text-xs font-bold text-white shadow-sm hover:bg-amber-700 active:scale-95 transition">
                                    <i class="bi bi-play-circle-fill"></i>
                                    <span>Test Sandbox Verified Payment</span>
                                </button>
                            </div>
                        </div>

                        <div class="text-center text-[11px] text-slate-400">
                            <i class="bi bi-lock-fill me-1 text-slate-400"></i> Secure payment processed by PayPal. Do not ask residents for PayPal passwords inside BARANGGABAY.
                        </div>
                    </div>
                </div>

                <!-- ================= SECONDARY OPTION: MANUAL GCASH ACCORDION ================= -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <button type="button" onclick="toggleGcashAccordion()"
                            class="w-full flex items-center justify-between text-left text-xs font-bold text-slate-700 dark:text-slate-200 hover:text-blue-600 transition">
                        <span class="flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-blue-600 text-white font-black text-[10px]">G</span>
                            <span>Alternatibo: Magbayad sa pamamagitan ng GCash Reference at Resibo</span>
                        </span>
                        <i id="gcashAccordionIcon" class="bi bi-chevron-down transition"></i>
                    </button>

                    <div id="gcashAccordionBody" class="hidden mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 space-y-4">
                        <div class="rounded-xl bg-blue-50/60 p-3.5 text-xs text-blue-900 dark:bg-blue-950/40 dark:text-blue-200">
                            <strong>GCash Account:</strong> <?= e($accountName) ?> (<?= e($mobileNumber) ?>)<br>
                            Halaga: <strong>₱<?= number_format($fee, 2) ?></strong>
                        </div>

                        <!-- Manual Upload Form -->
                        <form method="post" action="<?= e(route('documents/' . $reqId . '/payment')) ?>" enctype="multipart/form-data" class="space-y-3">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="amount_reported" value="<?= number_format($fee, 2, '.', '') ?>">

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">
                                    GCash Reference Number <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" name="gcash_reference_no" required
                                       placeholder="Hal: 1234 567 89012"
                                       class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-xs font-mono font-bold text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">
                                    Screenshot ng GCash Resibo <span class="text-rose-500">*</span>
                                </label>
                                <input type="file" name="receipt_file" accept="image/jpeg,image/png,image/jpg,application/pdf" required
                                       class="w-full rounded-xl border border-slate-300 bg-slate-50 p-2 text-xs text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                            </div>

                            <button type="submit"
                                    class="w-full rounded-xl bg-slate-800 py-2.5 text-xs font-bold text-white hover:bg-slate-900 transition">
                                Isumite ang GCash Receipt
                            </button>
                        </form>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<!-- ================= OFFICIAL PAYPAL JS SDK ================= -->
<?php if ($isUnpaid): ?>
<script src="https://www.paypal.com/sdk/js?client-id=<?= urlencode($paypalClientId) ?>&currency=PHP&intent=capture&components=buttons"></script>

<script>
// Dynamic Alert helper
function showDynamicAlert(type, title, message) {
    var box = document.getElementById('dynamicAlertBox');
    if (!box) return;

    box.className = 'rounded-2xl border-2 p-4 text-xs mb-4 ' + 
        (type === 'success' ? 'border-emerald-300 bg-emerald-50 text-emerald-950 dark:border-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-200' :
         type === 'error' ? 'border-rose-300 bg-rose-50 text-rose-950 dark:border-rose-900 dark:bg-rose-950/60 dark:text-rose-200' :
         'border-blue-300 bg-blue-50 text-blue-950 dark:border-blue-900 dark:bg-blue-950/60 dark:text-blue-200');

    box.innerHTML = '<div class="flex items-start gap-2.5">' +
        '<i class="bi ' + (type === 'success' ? 'bi-check-circle-fill text-emerald-600' : type === 'error' ? 'bi-x-circle-fill text-rose-600' : 'bi-info-circle-fill text-blue-600') + ' text-lg mt-0.5"></i>' +
        '<div><strong class="font-bold block text-sm">' + title + '</strong><p class="mt-0.5">' + message + '</p></div>' +
        '</div>';
    box.classList.remove('hidden');
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function toggleGcashAccordion() {
    var body = document.getElementById('gcashAccordionBody');
    var icon = document.getElementById('gcashAccordionIcon');
    if (!body) return;
    if (body.classList.contains('hidden')) {
        body.classList.remove('hidden');
        if (icon) icon.className = 'bi bi-chevron-up transition';
    } else {
        body.classList.add('hidden');
        if (icon) icon.className = 'bi bi-chevron-down transition';
    }
}

// Initialize PayPal Buttons
document.addEventListener('DOMContentLoaded', function() {
    var placeholder = document.getElementById('paypalLoadingPlaceholder');

    if (typeof paypal !== 'undefined' && paypal.Buttons) {
        try {
            paypal.Buttons({
                style: {
                    layout: 'vertical',
                    color: 'gold',
                    shape: 'rect',
                    label: 'paypal',
                    tagline: false
                },

                // 1. Create order on backend (DO NOT trust client amounts)
                createOrder: function(data, actions) {
                    return fetch('<?= e(route('api/payments/paypal/create-order')) ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
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
                    .then(function(orderData) {
                        if (!orderData.success || !orderData.orderID) {
                            throw new Error(orderData.error || 'Hindi mabuo ang PayPal order.');
                        }
                        return orderData.orderID;
                    })
                    .catch(function(err) {
                        showDynamicAlert('error', 'Order Creation Error', err.message || 'Nabigo sa paggawa ng PayPal Order.');
                        throw err;
                    });
                },

                // 2. Capture and verify on backend after resident approves in PayPal modal
                onApprove: function(data, actions) {
                    showDynamicAlert('info', 'Processing Payment...', 'Kinukumpirma ang inyong bayad sa PayPal server. Mangyaring maghintay saglit...');

                    return fetch('<?= e(route('api/payments/paypal/capture-order/')) ?>' + encodeURIComponent(data.orderID), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            orderID: data.orderID,
                            request_id: <?= (int) $reqId ?>
                        })
                    })
                    .then(function(res) {
                        return res.json();
                    })
                    .then(function(captureData) {
                        if (captureData.success) {
                            showDynamicAlert('success', 'PAYMENT SUCCESSFUL', 'Your PayPal payment has been confirmed! Nireredirect ka na...');
                            setTimeout(function() {
                                window.location.href = captureData.redirect_url || '<?= e(route('documents/' . $reqId . '/acknowledgement')) ?>';
                            }, 1200);
                        } else {
                            showDynamicAlert('error', 'Payment was not completed', captureData.error || 'Nabigo ang pagkumpirma ng bayad.');
                        }
                    })
                    .catch(function(err) {
                        showDynamicAlert('error', 'Capture Error', err.message || 'May naganap na problema sa pag-capture ng bayad.');
                    });
                },

                onCancel: function(data) {
                    showDynamicAlert('error', 'Payment Required', 'Kinansela mo ang PayPal checkout. Naka-save pa rin ang inyong kahilingan.');
                },

                onError: function(err) {
                    console.error('PayPal Buttons Error:', err);
                    showDynamicAlert('error', 'Payment was not completed', 'May naganap na error sa pagproseso ng PayPal checkout.');
                }
            }).render('#paypal-button-container').then(function() {
                if (placeholder) placeholder.style.display = 'none';
            }).catch(function(err) {
                console.warn('PayPal button render error:', err);
                if (placeholder) {
                    placeholder.innerHTML = '<span class="text-amber-600 font-semibold">PayPal SDK initialized in testing sandbox mode.</span>';
                }
            });
        } catch (e) {
            console.error('PayPal init exception:', e);
            if (placeholder) {
                placeholder.innerHTML = '<span class="text-slate-400">Gamitin ang Sandbox Simulation button sa ibaba upang i-test ang verification.</span>';
            }
        }
    } else {
        if (placeholder) {
            placeholder.innerHTML = '<div class="text-amber-600 font-semibold">Testing mode: PayPal sandbox credentials ready.</div>';
        }
    }
});

// Sandbox Simulator Function (Complete end-to-end server verification test)
function runSandboxSimulation() {
    var btn = document.getElementById('simulateSandboxPaymentBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split animate-spin"></i> Processing Server Capture...';
    }

    showDynamicAlert('info', 'Sandbox Simulation', 'Gumagawa ng PayPal order at nagpapatupad ng server-side capture at verification...');

    // 1. Create order
    fetch('<?= e(route('api/payments/paypal/create-order')) ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            request_id: <?= (int) $reqId ?>,
            payment_id: <?= (int) ($payment['id'] ?? 0) ?>
        })
    })
    .then(function(res) { return res.json(); })
    .then(function(orderData) {
        if (!orderData.success || !orderData.orderID) {
            throw new Error(orderData.error || 'Failed to create order');
        }

        // 2. Capture order
        return fetch('<?= e(route('api/payments/paypal/capture-order/')) ?>' + encodeURIComponent(orderData.orderID), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                orderID: orderData.orderID,
                request_id: <?= (int) $reqId ?>
            })
        });
    })
    .then(function(res) { return res.json(); })
    .then(function(captureData) {
        if (captureData.success) {
            showDynamicAlert('success', 'PAYMENT SUCCESSFUL', 'Your PayPal payment has been confirmed! Loading verified state...');
            setTimeout(function() {
                location.reload();
            }, 1000);
        } else {
            showDynamicAlert('error', 'Payment was not completed', captureData.error || 'Failed capture');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-play-circle-fill"></i> Test Sandbox Verified Payment';
            }
        }
    })
    .catch(function(err) {
        showDynamicAlert('error', 'Simulation Error', err.message || 'Failed simulation');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-play-circle-fill"></i> Test Sandbox Verified Payment';
        }
    });
}
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
