<?php
/**
 * Resident GCash Payment Checkout Page
 * Modern e-commerce-style payment experience for BarangGabay document requests.
 */
$pageTitle = $pageTitle ?? 'GCash Payment Checkout — BarangGabay';
$request   = $request ?? [];
$payment   = $payment ?? [];
$gcash     = $gcash ?? [];

$reqId     = (int) ($request['id'] ?? 0);
$docType   = (string) ($request['document_type'] ?? 'clearance');
$docLabel  = \App\Models\DocumentRequest::label($docType);
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

// Active GCash Account Details
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
        <div class="flex items-center gap-1.5 text-xs text-slate-400">
            <i class="bi bi-shield-lock-fill text-emerald-600"></i>
            <span>Ligtas at Opisyal na Transaksyon</span>
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
                <div class="flex h-8 w-8 items-center justify-center rounded-full <?= in_array($docStatus, ['processing', 'ready', 'released'], true) ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-400 dark:bg-slate-800' ?> mb-1.5">
                    <i class="bi bi-gear text-xs"></i>
                </div>
                <span class="<?= in_array($docStatus, ['processing', 'ready', 'released'], true) ? 'text-blue-700 font-bold' : 'text-slate-400' ?> hidden sm:inline">4. Paghahanda</span>
                <span class="text-[10px] text-slate-400 sm:hidden">Gawa</span>
            </div>

            <!-- Step 5: Ready -->
            <div class="flex flex-col items-center">
                <div class="flex h-8 w-8 items-center justify-center rounded-full <?= in_array($docStatus, ['ready', 'released'], true) ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-400 dark:bg-slate-800' ?> mb-1.5">
                    <i class="bi bi-check-circle text-xs"></i>
                </div>
                <span class="<?= in_array($docStatus, ['ready', 'released'], true) ? 'text-emerald-700 font-bold' : 'text-slate-400' ?> hidden sm:inline">5. Handa na</span>
                <span class="text-[10px] text-slate-400 sm:hidden">Tapos</span>
            </div>
        </div>
    </div>

    <!-- Main Grid: Invoice Summary + Payment Actions -->
    <div class="grid gap-6 lg:grid-cols-12">

        <!-- Left Column: Order / Request Summary (4 cols) -->
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
                        <span class="text-slate-400">Paraan ng Bayad:</span>
                        <span class="inline-flex items-center gap-1 font-bold text-blue-600 dark:text-blue-400">
                            <span class="inline-block h-2 w-2 rounded-full bg-blue-600"></span> GCash Online
                        </span>
                    </div>
                    <div class="pt-4 flex items-baseline justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Halagang Babayaran:</span>
                        <div class="text-2xl font-black text-slate-900 dark:text-white">
                            ₱<?= number_format($fee, 2) ?>
                        </div>
                    </div>
                </div>

                <!-- Status Badge Banner -->
                <div class="mt-4 rounded-xl p-3 text-xs <?= $isVerified ? 'bg-emerald-50 text-emerald-800 border border-emerald-200 dark:bg-emerald-950 dark:text-emerald-200' : ($isProofSub ? 'bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-950 dark:text-amber-200' : ($isRejected ? 'bg-rose-50 text-rose-800 border border-rose-200 dark:bg-rose-950 dark:text-rose-200' : 'bg-blue-50 text-blue-800 border border-blue-200 dark:bg-blue-950 dark:text-blue-200')) ?>">
                    <div class="flex items-center gap-2">
                        <?php if ($isVerified): ?>
                        <i class="bi bi-check-circle-fill text-emerald-600 text-base"></i>
                        <span class="font-bold">BERIPIKADO NA ANG BAYAD (PAID)</span>
                        <?php elseif ($isProofSub): ?>
                        <i class="bi bi-hourglass-split text-amber-600 text-base animate-pulse"></i>
                        <span class="font-bold">NAISUMITE ANG PATUNAY (Awaiting Review)</span>
                        <?php elseif ($isRejected): ?>
                        <i class="bi bi-exclamation-triangle-fill text-rose-600 text-base"></i>
                        <span class="font-bold">TINANGGIHAN ANG PATUNAY</span>
                        <?php else: ?>
                        <i class="bi bi-wallet2 text-blue-600 text-base"></i>
                        <span class="font-bold">KAILANGAN NG PAGBABAYAD (Unpaid)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Indigency / Payment Note -->
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                <i class="bi bi-info-circle me-1 text-blue-600"></i>
                Ang opisyal na dokumento ay ipoproseso at ilalabas lamang kapag naberipika na ng kawani ng barangay ang inyong pagbabayad.
            </div>
        </div>

        <!-- Right Column: GCash Checkout & Proof Upload (7 cols) -->
        <div class="lg:col-span-7 space-y-5">

            <!-- CASE 1: PAID VERIFIED ALREADY -->
            <?php if ($isVerified): ?>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-6 text-center dark:border-emerald-900 dark:bg-emerald-950/40">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-500 text-white shadow-md mb-4">
                    <i class="bi bi-check-circle-fill text-3xl"></i>
                </div>
                <h3 class="text-lg font-bold text-emerald-950 dark:text-emerald-100">Matagumpay na Naberipika ang Bayad!</h3>
                <p class="mt-1 text-xs text-emerald-800 dark:text-emerald-300">
                    Nabayaran na ang <strong>₱<?= number_format($fee, 2) ?></strong> para sa iyong <strong><?= e($docLabel) ?></strong> (#<?= e($reqRef) ?>).
                </p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <a href="<?= e(route('documents/' . $reqId . '/acknowledgement')) ?>" target="_blank"
                       class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition">
                        <i class="bi bi-printer"></i>
                        <span>Katibayan ng Bayad (Acknowledgement)</span>
                    </a>
                    <a href="<?= e(route('documents')) ?>"
                       class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        <span>Bumalik sa Aking Mga Kahilingan</span>
                    </a>
                </div>
            </div>

            <!-- CASE 2: PAYMENT PROOF SUBMITTED (UNDER REVIEW) -->
            <?php elseif ($isProofSub): ?>
            <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-6 dark:border-amber-900 dark:bg-amber-950/40">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white shadow-sm">
                        <i class="bi bi-hourglass-split text-2xl animate-spin" style="animation-duration: 4s;"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-base font-bold text-amber-950 dark:text-amber-100">Naisumite na ang Patunay ng Bayad</h3>
                        <p class="mt-1 text-xs text-amber-900 dark:text-amber-200">
                            Kasalukuyang sinusuri ng kawani ng Barangay Bayogo ang inyong resibo. Awtomatikong magiging aktibo ang inyong kahilingan kapag naberipika na ang bayad.
                        </p>

                        <div class="mt-4 rounded-xl border border-amber-200 bg-white p-3 text-xs dark:border-amber-900 dark:bg-slate-900">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <span class="text-slate-400 text-[11px] block">GCash Reference No:</span>
                                    <strong class="font-mono text-slate-800 dark:text-slate-200"><?= e((string) ($payment['gcash_reference_no'] ?? 'N/A')) ?></strong>
                                </div>
                                <div>
                                    <span class="text-slate-400 text-[11px] block">Halagang Naiulat:</span>
                                    <strong class="text-slate-800 dark:text-slate-200">₱<?= number_format((float) ($payment['amount_reported'] ?? $fee), 2) ?></strong>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-wrap gap-2.5">
                            <a href="<?= e(route('documents')) ?>"
                               class="inline-flex items-center gap-1.5 rounded-xl bg-amber-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-amber-700 transition">
                                <i class="bi bi-folder-check"></i>
                                <span>Tingnan ang Aking Mga Dokumento</span>
                            </a>
                            <button type="button" onclick="document.getElementById('reuploadSection').classList.toggle('hidden')"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                <i class="bi bi-arrow-repeat"></i>
                                <span>Maling resibo? Mag-upload Muli</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- GCash Checkout Box (Always shown if unpaid/rejected, or in toggle if proof submitted) -->
            <div id="reuploadSection" class="<?= $isProofSub ? 'hidden' : '' ?> space-y-5">

                <!-- Rejection Banner if rejected -->
                <?php if ($isRejected): ?>
                <div class="rounded-2xl border-2 border-rose-300 bg-rose-50 p-4 text-xs text-rose-950 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-200">
                    <div class="flex items-start gap-2.5">
                        <i class="bi bi-exclamation-octagon-fill text-rose-600 text-lg mt-0.5"></i>
                        <div>
                            <strong class="font-bold text-sm block text-rose-950 dark:text-rose-100">Kailangang Palitan ang Resibo:</strong>
                            <p class="mt-0.5 font-semibold text-rose-900 dark:text-rose-200">
                                Dahilan: <?= e((string) ($payment['rejection_reason'] ?: 'Hindi naberipika ang reference o resibo.')) ?>
                            </p>
                            <?php if (!empty($payment['notes'])): ?>
                            <p class="mt-1 text-slate-600 dark:text-slate-300">Puna ng Staff: <?= e((string) $payment['notes']) ?></p>
                            <?php endif; ?>
                            <p class="mt-2 text-[11px] font-medium text-rose-800 dark:text-rose-300">
                                Mangyaring i-upload ang tamang screenshot ng GCash receipt na may malinaw na Reference Number.
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- GCash Payment Info Card -->
                <div class="rounded-2xl border border-blue-200 bg-gradient-to-br from-blue-50/50 via-white to-indigo-50/30 p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b border-blue-100 pb-3 mb-4 dark:border-slate-800">
                        <div class="flex items-center gap-2">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-600 text-white font-black text-xs">
                                G
                            </span>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">GCash Payment Details</h3>
                        </div>
                        <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-[10px] font-bold text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                            Official Merchant
                        </span>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2 items-center">
                        <!-- Left: Account & Instructions -->
                        <div class="space-y-3 text-xs">
                            <div>
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Pangalan ng GCash Account:</span>
                                <div class="text-sm font-bold text-slate-800 dark:text-slate-100 mt-0.5"><?= e($accountName) ?></div>
                            </div>

                            <div>
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">GCash Mobile Number:</span>
                                <div class="mt-1 flex items-center gap-2">
                                    <span class="font-mono text-base font-black text-slate-900 dark:text-white bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700">
                                        <?= e($mobileNumber) ?>
                                    </span>
                                    <button type="button"
                                            onclick="copyToClipboard('<?= e($cleanMobile) ?>', this, 'Copied!')"
                                            class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-2.5 py-1 text-xs font-bold text-white shadow-sm hover:bg-blue-700 active:scale-95 transition">
                                        <i class="bi bi-copy"></i>
                                        <span>Copy Number</span>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Eksaktong Halaga:</span>
                                <div class="mt-1 flex items-center gap-2">
                                    <span class="text-lg font-black text-blue-600 dark:text-blue-400">
                                        ₱<?= number_format($fee, 2) ?>
                                    </span>
                                    <button type="button"
                                            onclick="copyToClipboard('<?= number_format($fee, 2, '.', '') ?>', this, 'Copied!')"
                                            class="rounded bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-200 transition">
                                        Copy Amount
                                    </button>
                                </div>
                            </div>

                            <div class="rounded-xl border border-blue-100 bg-white p-3 text-[11px] text-slate-600 leading-relaxed shadow-sm dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-300">
                                <strong class="text-slate-800 dark:text-slate-100 block mb-1">Mga Hakbang sa Pagbayad:</strong>
                                1. Buksan ang <strong>GCash app</strong> sa inyong telepono.<br>
                                2. I-scan ang QR code sa kanan o mag-Send Money sa numero sa itaas.<br>
                                3. Ipadala ang eksaktong <strong>₱<?= number_format($fee, 2) ?></strong>.<br>
                                4. I-save ang screenshot ng resibo at kopyahin ang <strong>Reference No</strong>.
                            </div>
                        </div>

                        <!-- Right: Sharp QR Code Preview Card -->
                        <div class="flex flex-col items-center justify-center p-4 rounded-2xl bg-white border border-slate-200 shadow-sm text-center">
                            <?php if ($hasQr): ?>
                            <div class="relative group cursor-pointer" onclick="openQrModal()">
                                <img src="<?= $qrSrc ?>" alt="GCash QR Code"
                                     class="h-44 w-44 object-contain rounded-xl border border-slate-100 p-1.5 transition group-hover:scale-105 shadow-sm">
                                <div class="absolute inset-0 bg-black/10 rounded-xl opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white text-xs font-bold gap-1 bg-black/30">
                                    <i class="bi bi-zoom-in text-base"></i> Palakihin
                                </div>
                            </div>
                            <button type="button" onclick="openQrModal()"
                                    class="mt-2.5 inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:underline">
                                <i class="bi bi-arrows-fullscreen"></i>
                                <span>Palakihin ang QR Code</span>
                            </button>
                            <?php else: ?>
                            <div class="flex h-36 w-36 items-center justify-center rounded-xl bg-slate-50 text-slate-300 mb-2">
                                <i class="bi bi-qr-code text-5xl"></i>
                            </div>
                            <span class="text-xs text-slate-400">Gamitin ang GCash Mobile Number sa kaliwa</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Proof of Payment Upload Form -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-100 pb-3 mb-4 dark:border-slate-800">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <i class="bi bi-upload text-blue-600"></i>
                            <span>Isumite ang Patunay ng Bayad (Payment Proof)</span>
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Ilagay ang GCash Reference Number at i-upload ang resibo mula sa GCash.
                        </p>
                    </div>

                    <form id="paymentProofForm" method="post" action="<?= e(route('documents/' . $reqId . '/payment')) ?>" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="amount_reported" value="<?= number_format($fee, 2, '.', '') ?>">

                        <div class="grid gap-4 sm:grid-cols-2">
                            <!-- GCash Reference Number -->
                            <div>
                                <label for="gcash_reference_no" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">
                                    GCash Reference Number <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" id="gcash_reference_no" name="gcash_reference_no" required
                                       value="<?= e((string) ($payment['gcash_reference_no'] ?? '')) ?>"
                                       placeholder="Hal: 1234 567 89012"
                                       class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-xs font-mono font-bold text-slate-900 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                <span class="text-[11px] text-slate-400 mt-1 block">Makikita sa GCash transaction receipt.</span>
                            </div>

                            <!-- Payment Date -->
                            <div>
                                <label for="payment_date" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">
                                    Petsa ng Pagbayad
                                </label>
                                <input type="date" id="payment_date" name="payment_date"
                                       value="<?= date('Y-m-d') ?>"
                                       class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-xs text-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                            </div>
                        </div>

                        <!-- Receipt Upload with Live Client Preview -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">
                                Upload ng Screenshot / Resibo <span class="text-rose-500">*</span>
                            </label>

                            <!-- Hidden Real File Input -->
                            <input type="file" id="receiptFileInput" name="receipt_file"
                                   accept="image/jpeg,image/png,image/jpg,application/pdf" required
                                   onchange="handleReceiptFileSelect(this)" class="hidden">

                            <!-- Dropzone / Picker Card -->
                            <div id="uploadDropzone" onclick="document.getElementById('receiptFileInput').click()"
                                 class="cursor-pointer rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50/70 p-5 text-center transition hover:border-blue-500 hover:bg-blue-50/30 dark:border-slate-700 dark:bg-slate-800/40">
                                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 text-blue-600 dark:bg-blue-900/60 dark:text-blue-300 mb-2">
                                    <i class="bi bi-cloud-arrow-up text-xl"></i>
                                </div>
                                <div class="text-xs font-bold text-slate-700 dark:text-slate-200">
                                    I-click para pumili ng resibo o i-drag dito
                                </div>
                                <p class="text-[11px] text-slate-400 mt-0.5">
                                    Tumatanggap ng JPG, PNG, o PDF (Hanggang 10 MB)
                                </p>
                            </div>

                            <!-- Live Client-Side Preview Box (Hidden by default) -->
                            <div id="receiptPreviewBox" class="hidden mt-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <!-- Thumbnail -->
                                        <div id="receiptThumbContainer" class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-xl bg-white border border-slate-200 overflow-hidden shadow-sm dark:bg-slate-900">
                                            <img id="receiptImageThumb" src="" alt="Receipt Preview" class="h-full w-full object-cover hidden">
                                            <i id="receiptPdfIcon" class="bi bi-file-earmark-pdf-fill text-rose-500 text-2xl hidden"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <div id="receiptFileName" class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate max-w-xs">
                                                receipt.png
                                            </div>
                                            <div id="receiptFileSize" class="text-[11px] text-slate-400 mt-0.5">
                                                0 KB
                                            </div>
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-600 mt-0.5">
                                                <i class="bi bi-check-circle-fill"></i> Handa nang isumite
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button" onclick="document.getElementById('receiptFileInput').click()"
                                                class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                            Palitan
                                        </button>
                                        <button type="button" onclick="removeReceiptFile()"
                                                class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-700 hover:bg-rose-100 transition dark:bg-rose-950 dark:border-rose-900 dark:text-rose-300">
                                            Tanggalin
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Optional Notes -->
                        <div>
                            <label for="notes" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">
                                Karagdagang Tala (Opsyonal)
                            </label>
                            <input type="text" id="notes" name="notes" placeholder="Hal: Nagbayad bandang 10:30 AM gamit ang aking personal na GCash..."
                                   class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-xs text-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                        </div>

                        <!-- Submit Button with Loading State -->
                        <div class="pt-2">
                            <button type="submit" id="submitProofBtn"
                                    class="w-full flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-3 text-sm font-bold text-white shadow-md hover:from-blue-700 hover:to-indigo-700 active:scale-[0.99] transition">
                                <i class="bi bi-shield-check text-base"></i>
                                <span id="submitBtnText">Isumite ang Patunay ng Bayad</span>
                            </button>
                            <p class="text-center text-[11px] text-slate-400 mt-2">
                                Sa pagsumite, kinukumpirma mo na totoo at tama ang naitalang GCash Reference Number at Resibo.
                            </p>
                        </div>
                    </form>
                </div>

            </div>

        </div>
    </div>
</div>

<!-- ================= LARGER QR LIGHTBOX MODAL ================= -->
<div id="largerQrModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm hidden"
     onclick="closeQrModal(event)">
    <div class="relative max-w-sm w-full rounded-3xl bg-white p-6 shadow-2xl text-center" onclick="event.stopPropagation()">
        <button type="button" onclick="closeQrModal()"
                class="absolute right-4 top-4 rounded-full bg-slate-100 p-2 text-slate-500 hover:bg-slate-200 hover:text-slate-800 transition">
            <i class="bi bi-x-lg"></i>
        </button>

        <div class="mb-4">
            <h3 class="text-base font-bold text-slate-900"><?= e($accountName) ?></h3>
            <p class="text-xs text-slate-500">I-scan gamit ang GCash App</p>
        </div>

        <?php if ($hasQr): ?>
        <div class="mx-auto rounded-2xl border-2 border-slate-100 bg-white p-3 shadow-inner">
            <img src="<?= $qrSrc ?>" alt="GCash QR Large" class="h-64 w-64 mx-auto object-contain">
        </div>
        <?php endif; ?>

        <div class="mt-4 rounded-xl bg-blue-50 p-3 text-xs text-blue-950 font-mono font-bold">
            <?= e($mobileNumber) ?> &bull; ₱<?= number_format($fee, 2) ?>
        </div>

        <button type="button" onclick="closeQrModal()"
                class="mt-4 w-full rounded-xl bg-slate-900 py-2.5 text-xs font-bold text-white hover:bg-slate-800 transition">
            Isara
        </button>
    </div>
</div>

<script>
// Clipboard copy with feedback
function copyToClipboard(text, btnElement, feedbackText) {
    if (!navigator.clipboard) {
        var dummy = document.createElement("textarea");
        document.body.appendChild(dummy);
        dummy.value = text;
        dummy.select();
        document.execCommand("copy");
        document.body.removeChild(dummy);
    } else {
        navigator.clipboard.writeText(text);
    }

    var originalHtml = btnElement.innerHTML;
    btnElement.innerHTML = '<i class="bi bi-check2"></i> ' + feedbackText;
    btnElement.classList.add('bg-emerald-600');
    setTimeout(function() {
        btnElement.innerHTML = originalHtml;
        btnElement.classList.remove('bg-emerald-600');
    }, 2000);
}

// Larger QR Modal
function openQrModal() {
    document.getElementById('largerQrModal').classList.remove('hidden');
}
function closeQrModal() {
    document.getElementById('largerQrModal').classList.add('hidden');
}

// Client-side file preview & validation
function handleReceiptFileSelect(input) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];

    // Max 10MB
    if (file.size > 10 * 1024 * 1024) {
        alert('Masyadong malaki ang file. Hanggang 10 MB lamang ang pinapayagan.');
        input.value = '';
        return;
    }

    var dropzone = document.getElementById('uploadDropzone');
    var previewBox = document.getElementById('receiptPreviewBox');
    var imgThumb = document.getElementById('receiptImageThumb');
    var pdfIcon = document.getElementById('receiptPdfIcon');
    var nameEl = document.getElementById('receiptFileName');
    var sizeEl = document.getElementById('receiptFileSize');

    nameEl.innerText = file.name;
    sizeEl.innerText = formatBytes(file.size);

    if (file.type.startsWith('image/')) {
        var reader = new FileReader();
        reader.onload = function(e) {
            imgThumb.src = e.target.result;
            imgThumb.classList.remove('hidden');
            pdfIcon.classList.add('hidden');
        };
        reader.readAsDataURL(file);
    } else {
        imgThumb.classList.add('hidden');
        pdfIcon.classList.remove('hidden');
    }

    dropzone.classList.add('hidden');
    previewBox.classList.remove('hidden');
}

function removeReceiptFile() {
    var input = document.getElementById('receiptFileInput');
    input.value = '';
    document.getElementById('receiptPreviewBox').classList.add('hidden');
    document.getElementById('uploadDropzone').classList.remove('hidden');
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    var k = 1024;
    var sizes = ['B', 'KB', 'MB', 'GB'];
    var i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

// Prevent double submission / loading state
document.getElementById('paymentProofForm')?.addEventListener('submit', function(e) {
    var btn = document.getElementById('submitProofBtn');
    var txt = document.getElementById('submitBtnText');
    if (btn) {
        btn.disabled = true;
        btn.style.opacity = '0.75';
        txt.innerHTML = '<span class="inline-block animate-spin mr-2"><i class="bi bi-arrow-repeat"></i></span> Ipinapasa ang patunay...';
    }
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';
