<?php
/**
 * Resident document requests — Personal Pickup or Digital Soft Copy with Payment System.
 *
 * Variables: $requests (list), $types (array<string,string>), $docFees (array), $defaultGcash (array|null)
 */
$requests     = $requests ?? [];
$types        = $types    ?? [];
$docFees      = $docFees  ?? \App\Models\DocumentFee::getAll();
$defaultGcash = $defaultGcash ?? \App\Models\GcashAccount::getDefault();

$statusClass = [
    'pending'    => 'bg-slate-100 text-slate-700 border-slate-200',
    'processing' => 'bg-amber-50 text-amber-800 border-amber-200',
    'ready'      => 'bg-emerald-50 text-emerald-800 border-emerald-200',
    'released'   => 'bg-blue-50 text-blue-800 border-blue-200',
    'rejected'   => 'bg-rose-50 text-rose-800 border-rose-200',
];

$paymentBadgeStyles = [
    'FREE'                     => 'bg-slate-100 text-slate-700 border-slate-200',
    'UNPAID'                   => 'bg-rose-50 text-rose-700 border-rose-200',
    'PAYMENT_PROOF_SUBMITTED'  => 'bg-amber-50 text-amber-800 border-amber-200',
    'UNDER_REVIEW'             => 'bg-blue-50 text-blue-800 border-blue-200',
    'PAID_VERIFIED'            => 'bg-emerald-50 text-emerald-800 border-emerald-200',
    'PAYMENT_REJECTED'         => 'bg-rose-50 text-rose-800 border-rose-200',
    'PAY_AT_PICKUP'            => 'bg-purple-50 text-purple-800 border-purple-200',
    'PAID_AT_PICKUP'           => 'bg-emerald-50 text-emerald-800 border-emerald-200',
    'WAIVED'                   => 'bg-slate-100 text-slate-600 border-slate-200',
    'REFUNDED'                 => 'bg-orange-50 text-orange-800 border-orange-200',
];

ob_start();
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900"><?= e(t('documents.title')) ?></h1>
        <p class="mt-1 text-sm text-slate-500"><?= e(t('documents.subtitle')) ?></p>
    </div>
    <div class="flex items-center gap-2">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
            <i class="bi bi-clock-history"></i>
            <?= count($requests) ?> <?= count($requests) === 1 ? 'Request' : 'Requests' ?>
        </span>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-[400px_1fr]">

    <!-- ── Request form ──────────────────────────────────────────── -->
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:sticky lg:top-4 lg:self-start">
        <div class="mb-4 flex items-center gap-3">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                <i class="bi bi-file-earmark-plus" style="font-size: 1.15rem;"></i>
            </span>
            <div>
                <h2 class="text-base font-bold text-slate-900"><?= e(t('documents.form_title')) ?></h2>
                <p class="text-xs text-slate-500"><?= e(t('documents.form_help')) ?></p>
            </div>
        </div>

        <form method="post" action="<?= e(route('documents')) ?>" id="docRequestForm">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <!-- Document Type Selection -->
            <div class="mb-4">
                <div class="flex items-center justify-between mb-1">
                    <label for="document_type" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                        <?= e(t('documents.field_type')) ?> <span class="text-red-500">*</span>
                    </label>
                    <span id="feeBadge" class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-bold text-blue-700 border border-blue-100">
                        ₱50.00
                    </span>
                </div>
                <select id="document_type" name="document_type" required onchange="handleDocTypeChange()"
                        class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-medium text-slate-800 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <?php foreach ($types as $key => $label): 
                        $f = $docFees[$key] ?? ['amount' => 50.0, 'is_free' => false];
                        $feeTxt = $f['is_free'] || (float)$f['amount'] <= 0 ? ' (LIBRE)' : sprintf(' (₱%.2f)', (float)$f['amount']);
                    ?>
                    <option value="<?= e($key) ?>" data-amount="<?= (float)$f['amount'] ?>" data-free="<?= $f['is_free'] ? '1' : '0' ?>">
                        <?= e($label) . $feeTxt ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Delivery Method Selector (Cards) -->
            <div class="mb-4">
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-700">
                    <?= e(t('documents.delivery_label')) ?> <span class="text-red-500">*</span>
                </label>
                <p class="mb-2 text-xs text-slate-400"><?= e(t('documents.delivery_help')) ?></p>

                <div class="grid gap-2.5">
                    <!-- Pickup Option -->
                    <label class="delivery-option-card relative flex cursor-pointer items-start gap-3 rounded-xl border-2 border-slate-200 bg-slate-50/50 p-3 transition hover:border-slate-300 hover:bg-white">
                        <input type="radio" name="delivery_method" value="pickup" checked onchange="handleDeliveryChange()"
                               class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <span class="text-sm font-bold text-slate-900"><?= e(t('documents.delivery_pickup')) ?></span>
                                <span class="rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-bold text-slate-700">Counter</span>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-500 leading-normal">
                                <?= e(t('documents.delivery_pickup_desc')) ?>
                            </p>
                        </div>
                    </label>

                    <!-- Digital Option -->
                    <label class="delivery-option-card relative flex cursor-pointer items-start gap-3 rounded-xl border-2 border-slate-200 bg-slate-50/50 p-3 transition hover:border-blue-300 hover:bg-white">
                        <input type="radio" name="delivery_method" value="digital" onchange="handleDeliveryChange()"
                               class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <span class="text-sm font-bold text-slate-900"><?= e(t('documents.delivery_digital')) ?></span>
                                <span class="rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700">Online Soft Copy</span>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-500 leading-normal">
                                <?= e(t('documents.delivery_digital_desc')) ?>
                            </p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Payment Method Selector (Shown only when document has fee) -->
            <div id="paymentMethodSection" class="mb-4 rounded-xl border border-blue-100 bg-blue-50/50 p-3.5">
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-blue-950">
                        <i class="bi bi-wallet2 text-blue-600 me-1"></i>Paraan ng Pagbabayad <span class="text-red-500">*</span>
                    </label>
                    <span id="paymentRequiredNotice" class="text-[10px] font-semibold text-blue-700"></span>
                </div>

                <div class="grid gap-2 mt-2">
                    <!-- Online Payment (GCash) -->
                    <label id="optGcashWrapper" class="payment-method-card relative flex cursor-pointer items-start gap-3 rounded-xl border-2 border-blue-600 bg-white p-2.5 transition">
                        <input type="radio" name="payment_method" value="gcash" checked
                               class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs font-bold text-slate-900">Magbayad Online gamit ang GCash</span>
                                <span class="rounded bg-blue-100 text-blue-800 px-1.5 py-0.2 text-[9px] font-bold">Online</span>
                            </div>
                            <p class="mt-0.5 text-[11px] text-slate-500 leading-tight">
                                I-scan ang QR code o mag-send sa GCash number, saka i-upload ang resibo.
                            </p>
                        </div>
                    </label>

                    <!-- Pay Upon Pickup (Counter) -->
                    <label id="optPickupWrapper" class="payment-method-card relative flex cursor-pointer items-start gap-3 rounded-xl border-2 border-slate-200 bg-white p-2.5 transition hover:border-slate-300">
                        <input type="radio" name="payment_method" value="pickup"
                               class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs font-bold text-slate-900">Magbayad sa Counter (Pay Upon Pickup)</span>
                                <span class="rounded bg-slate-100 text-slate-700 px-1.5 py-0.2 text-[9px] font-bold">Cash</span>
                            </div>
                            <p class="mt-0.5 text-[11px] text-slate-500 leading-tight">
                                Ibayad ang kaukulang halaga sa Barangay Hall pagkuha ng inyong dokumento.
                            </p>
                        </div>
                    </label>
                </div>

                <!-- Digital Lock Notice -->
                <div id="digitalPaymentNotice" class="mt-2 text-[11px] text-indigo-700 font-medium hidden">
                    <i class="bi bi-shield-lock me-1"></i>Para sa <strong>Digital Soft Copy</strong>, kailangang online GCash payment upang mai-release ang digital na kopya online.
                </div>
            </div>

            <!-- Free Document Banner -->
            <div id="freeDocumentBanner" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50/70 p-3 text-xs text-emerald-800 hidden">
                <i class="bi bi-gift-fill text-emerald-600 me-1"></i>
                Ang dokumentong ito ay <strong>LIBRE</strong> (walang kailangang bayaran). Direktang ipoproseso ng barangay.
            </div>

            <!-- Purpose -->
            <div class="mb-4">
                <label for="purpose" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-700">
                    <?= e(t('documents.field_purpose')) ?> <span class="text-red-500">*</span>
                </label>
                <input type="text" id="purpose" name="purpose" required maxlength="255"
                       placeholder="<?= e(t('documents.purpose_ph')) ?>"
                       class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-800 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100">
                <p class="mt-1 text-xs text-slate-400"><?= e(t('documents.purpose_help')) ?></p>
            </div>

            <!-- Additional Notes -->
            <div class="mb-5">
                <label for="notes" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-700">
                    <?= e(t('documents.field_notes')) ?>
                </label>
                <textarea id="notes" name="notes" rows="3" maxlength="2000"
                          placeholder="<?= e(t('documents.notes_ph')) ?>"
                          class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-800 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100"></textarea>
            </div>

            <button type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-700 to-indigo-700 px-4 py-3 text-sm font-bold text-white shadow-md transition hover:from-blue-800 hover:to-indigo-800 hover:shadow-lg active:scale-[0.99]">
                <i class="bi bi-send-fill"></i>
                <span><?= e(t('documents.submit')) ?></span>
            </button>
        </form>

        <div class="mt-5 border-t border-slate-100 pt-4">
            <a href="<?= e(route('feedback')) ?>"
               class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 hover:text-slate-900">
                <i class="bi bi-chat-dots"></i>
                <span><?= e(t('documents.ask_staff')) ?></span>
            </a>
            <p class="mt-1.5 text-center text-[11px] text-slate-400"><?= e(t('documents.ask_staff_help')) ?></p>
        </div>
    </div>

    <!-- ── My requests ───────────────────────────────────────────── -->
    <div>
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900"><?= e(t('documents.mine_title')) ?></h2>
            <?php if ($requests !== []): ?>
            <span class="text-xs text-slate-400"><?= count($requests) ?> na-itala</span>
            <?php endif; ?>
        </div>

        <?php if ($requests === []): ?>
        <div class="rounded-2xl border-2 border-dashed border-slate-200 bg-white p-12 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400">
                <i class="bi bi-file-earmark-text" style="font-size: 1.8rem;"></i>
            </div>
            <h3 class="mt-3 text-sm font-bold text-slate-700"><?= e(t('documents.none')) ?></h3>
            <p class="mt-1 text-xs text-slate-400">Piliin ang kailangang sertipiko sa kaliwang bahagi upang magsimula.</p>
        </div>
        <?php else: ?>

        <div class="space-y-4">
            <?php foreach ($requests as $r):
                $reqId    = (int) $r['id'];
                $st       = (string) $r['status'];
                $delivery = (string) ($r['delivery_method'] ?? 'pickup');
                $isDigital= $delivery === 'digital';
                $hasFile  = !empty($r['document_file_name']);

                // Payment fields
                $fee       = (float) ($r['fee_amount'] ?? 0);
                $payStatus = (string) ($r['payment_status'] ?? 'FREE');
                $isFree    = $fee <= 0.0 || $payStatus === 'FREE';
                $isVerified= in_array($payStatus, ['PAID_VERIFIED', 'PAID_AT_PICKUP', 'FREE', 'WAIVED'], true);
                $isProofSub= $payStatus === 'PAYMENT_PROOF_SUBMITTED';
                $isRejected= $payStatus === 'PAYMENT_REJECTED';
                $isUnpaid  = $payStatus === 'UNPAID';
                $isAtPickup= $payStatus === 'PAY_AT_PICKUP';

                // Status label
                $statusLabel = $isDigital && $st === 'ready'
                    ? t('documents.status_ready_digital')
                    : ($isDigital && $st === 'released' ? t('documents.status_released_digital') : t('documents.status_' . $st));

                $borderHighlight = match ($st) {
                    'ready'    => 'border-emerald-300 ring-1 ring-emerald-100',
                    'released' => 'border-blue-200',
                    'processing' => 'border-amber-200',
                    default    => 'border-slate-200',
                };
            ?>
            <article class="rounded-2xl border bg-white p-5 shadow-sm transition hover:shadow-md <?= $borderHighlight ?>">
                <!-- Header: Title, Reference, Badges -->
                <div class="flex flex-wrap items-start justify-between gap-2.5">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base font-bold text-slate-900">
                                <?= e(\App\Models\DocumentRequest::label((string) $r['document_type'])) ?>
                            </h3>
                            <!-- Delivery Badge -->
                            <?php if ($isDigital): ?>
                            <span class="inline-flex items-center gap-1 rounded-md bg-indigo-50 border border-indigo-200 px-2 py-0.5 text-[11px] font-bold text-indigo-700">
                                <i class="bi bi-file-earmark-arrow-down"></i> <?= e(t('documents.badge_digital')) ?>
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 border border-slate-200 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                                <i class="bi bi-building"></i> <?= e(t('documents.badge_pickup')) ?>
                            </span>
                            <?php endif; ?>

                            <!-- Payment Badge -->
                            <span class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-bold <?= $paymentBadgeStyles[$payStatus] ?? 'bg-slate-100 text-slate-700' ?>">
                                <i class="bi bi-wallet2"></i>
                                <?php if ($isFree): ?>
                                Libre (Free)
                                <?php elseif ($isVerified): ?>
                                Bayad na ✓ (₱<?= number_format($fee, 2) ?>)
                                <?php elseif ($isProofSub): ?>
                                Naisumite ang Patunay (Awaiting Review)
                                <?php elseif ($isRejected): ?>
                                Tinanggihan ang Patunay
                                <?php elseif ($isAtPickup): ?>
                                Magbabayad sa Counter (₱<?= number_format($fee, 2) ?>)
                                <?php else: ?>
                                Kailangang Bayaran (₱<?= number_format($fee, 2) ?>)
                                <?php endif; ?>
                            </span>
                        </div>
                        <p class="mt-1 font-mono text-xs text-slate-400">
                            Reference: <strong class="text-slate-600"><?= e((string) $r['reference_no']) ?></strong>
                            <?php if (!empty($r['payment_ref'])): ?>
                            &bull; Payment Ref: <strong class="text-blue-600"><?= e((string) $r['payment_ref']) ?></strong>
                            <?php endif; ?>
                        </p>
                    </div>

                    <!-- Status Pill -->
                    <div>
                        <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-bold <?= $statusClass[$st] ?? 'bg-slate-100 text-slate-700' ?>">
                            <?php if ($st === 'ready'): ?>
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                            </span>
                            <?php endif; ?>
                            <?= e($statusLabel) ?>
                        </span>
                    </div>
                </div>

                <!-- Purpose & Details -->
                <div class="mt-3 text-sm text-slate-700">
                    <span class="font-semibold text-slate-500 text-xs uppercase tracking-wider"><?= e(t('documents.field_purpose')) ?>:</span>
                    <span class="font-medium text-slate-800"><?= e((string) $r['purpose']) ?></span>
                </div>

                <?php if (!empty($r['notes'])): ?>
                <div class="mt-1 text-xs text-slate-500">
                    <span class="font-semibold text-slate-400">Iyong paalala:</span> <?= e((string) $r['notes']) ?>
                </div>
                <?php endif; ?>

                <!-- Staff Note Box -->
                <?php if (!empty($r['staff_note'])): ?>
                <div class="mt-3.5 rounded-xl border border-amber-200 bg-amber-50/70 p-3 text-xs leading-relaxed text-amber-900">
                    <div class="flex items-start gap-2">
                        <i class="bi bi-chat-quote-fill mt-0.5 text-amber-600"></i>
                        <div>
                            <span class="font-bold text-amber-900">Paalala mula sa Barangay Hall:</span>
                            <p class="mt-0.5 text-amber-800"><?= e((string) $r['staff_note']) ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ================= PAYMENT SECTION FOR RESIDENT ================= -->
                <?php if (!$isFree): ?>

                    <!-- Case 1: Unpaid or Rejected (Needs Receipt Upload) -->
                    <?php if ($isUnpaid || $isRejected): ?>
                    <div class="mt-4 rounded-xl border-2 border-rose-100 bg-gradient-to-br from-rose-50/40 to-amber-50/30 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-rose-100/80 pb-3 mb-3">
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-600 text-white font-bold text-sm">
                                    <i class="bi bi-wallet2"></i>
                                </span>
                                <div>
                                    <h4 class="text-sm font-bold text-slate-900">Kailangan ng Pagbabayad (Payment Required)</h4>
                                    <p class="text-xs text-slate-500">Ibayad ang eksaktong halaga sa GCash bago ilabas ang dokumento.</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] uppercase font-bold text-slate-400">Halagang Babayaran:</span>
                                <div class="text-lg font-black text-rose-600">₱<?= number_format($fee, 2) ?></div>
                            </div>
                        </div>

                        <!-- If rejected: show reason banner -->
                        <?php if ($isRejected): ?>
                        <div class="mb-3.5 rounded-xl border border-rose-300 bg-rose-100/70 p-3 text-xs text-rose-950">
                            <div class="flex items-start gap-2">
                                <i class="bi bi-exclamation-octagon-fill text-rose-600 text-base mt-0.5"></i>
                                <div>
                                    <strong class="font-bold">Tinanggihan ang Iyong Naunang Patunay ng Bayad:</strong>
                                    <p class="mt-0.5 font-medium text-rose-900">Dahilan: <?= e((string) ($r['rejection_reason'] ?: 'Kailangang suriin muli ang resibo.')) ?></p>
                                    <?php if (!empty($r['rejection_note'])): ?>
                                    <p class="mt-0.5 text-rose-800 text-[11px]">Paliwanag ng Staff: <?= e((string) $r['rejection_note']) ?></p>
                                    <?php endif; ?>
                                    <p class="mt-1 text-[11px] text-rose-900">Mangyaring mag-upload muli ng malinaw at wastong resibo sa ibaba.</p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- GCash Account Details Card -->
                        <div class="grid gap-3 sm:grid-cols-2 bg-white rounded-xl p-3 border border-slate-200/80 mb-3.5">
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">GCash Account Name:</span>
                                <div class="font-bold text-slate-800 text-sm mt-0.5"><?= e((string) ($r['gcash_account_name'] ?: 'Barangay Bayogo Official')) ?></div>

                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mt-2">GCash Mobile Number:</span>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="font-mono font-bold text-slate-900 text-sm"><?= e((string) ($r['gcash_mobile_number'] ?: '0917 123 4567')) ?></span>
                                    <button type="button" 
                                            onclick="navigator.clipboard.writeText('<?= e((string) ($r['gcash_mobile_number'] ?: '09171234567')) ?>');this.innerText='Copied!';setTimeout(()=>this.innerText='Copy',1500)"
                                            class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-700 hover:bg-slate-200">
                                        Copy
                                    </button>
                                </div>

                                <div class="mt-3 text-[11px] text-slate-500 leading-tight">
                                    1. Mag-send ng eksaktong <strong>₱<?= number_format($fee, 2) ?></strong> sa GCash number o i-scan ang QR.<br>
                                    2. I-save ang screenshot / resibo.<br>
                                    3. I-upload ang resibo at ilagay ang GCash Ref# sa tabi.
                                </div>
                            </div>

                            <!-- QR Code Preview -->
                            <div class="flex flex-col items-center justify-center p-2 rounded-lg bg-slate-50 border border-slate-100 text-center">
                                <?php if (!empty($r['qr_image_data'])):
                                    $qrSrc = 'data:' . ($r['qr_mime_type'] ?: 'image/png') . ';base64,' . $r['qr_image_data'];
                                ?>
                                <img src="<?= $qrSrc ?>" alt="GCash QR" class="h-28 w-28 object-contain rounded border bg-white p-1 cursor-pointer shadow-sm hover:scale-105 transition"
                                     onclick="viewLargerQr('<?= $qrSrc ?>', '<?= e((string)$r['gcash_account_name']) ?>')">
                                <button type="button" onclick="viewLargerQr('<?= $qrSrc ?>', '<?= e((string)$r['gcash_account_name']) ?>')"
                                        class="mt-1.5 text-[11px] font-bold text-blue-600 hover:underline">
                                    <i class="bi bi-zoom-in me-1"></i>Palakihin ang QR
                                </button>
                                <?php else: ?>
                                <i class="bi bi-qr-code text-slate-300 text-4xl mb-1"></i>
                                <span class="text-[11px] text-slate-400">Gamitin ang GCash Number sa kaliwa</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Receipt Upload Form -->
                        <form method="post" action="<?= e(route('documents/' . $reqId . '/payment')) ?>" enctype="multipart/form-data" class="space-y-3">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="amount_reported" value="<?= number_format($fee, 2, '.', '') ?>">

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">
                                        GCash Reference Number <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="gcash_reference_no" required placeholder="Hal: 1234 567 89012"
                                           class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-mono text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-200">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">
                                        Upload ng Resibo / Screenshot <span class="text-red-500">*</span>
                                    </label>
                                    <input type="file" name="receipt_file" accept="image/jpeg,image/png,image/jpg,application/pdf" required
                                           class="w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-700 file:mr-2 file:rounded-lg file:border-0 file:bg-blue-50 file:px-2.5 file:py-1 file:text-xs file:font-semibold file:text-blue-700">
                                </div>
                            </div>

                            <div>
                                <input type="text" name="notes" placeholder="Opsyonal na tala o detalhe..."
                                       class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700 focus:border-blue-500 focus:ring-1 focus:ring-blue-200">
                            </div>

                            <button type="submit"
                                    class="w-full flex items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-blue-700 active:scale-[0.99]">
                                <i class="bi bi-upload"></i>
                                <span>Isumite ang Patunay ng Bayad</span>
                            </button>
                        </form>
                    </div>

                    <!-- Case 2: Payment Proof Submitted (Under Review) -->
                    <?php elseif ($isProofSub): ?>
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50/60 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white shadow-sm">
                                    <i class="bi bi-hourglass-split text-lg"></i>
                                </span>
                                <div>
                                    <h4 class="text-sm font-bold text-amber-950">Naisumite na ang Patunay ng Bayad</h4>
                                    <p class="text-xs text-amber-800 mt-0.5">
                                        GCash Reference: <strong class="font-mono text-amber-950"><?= e((string) $r['gcash_reference_no']) ?></strong> &bull; Halaga: <strong>₱<?= number_format($fee, 2) ?></strong>
                                    </p>
                                    <p class="text-[11px] text-amber-700 mt-1">
                                        Kasalukuyan nang sinusuri ng kawani ng barangay ang inyong resibo. Awtomatikong magiging handa ang inyong dokumento pagkatapos beripikahin.
                                    </p>
                                </div>
                            </div>
                            <?php if (!empty($r['receipt_file_name'])): ?>
                            <a href="<?= e(route('admin/payments/' . (int) $r['payment_id'] . '/receipt')) ?>" target="_blank"
                               class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-900 shadow-sm hover:bg-amber-50">
                                <i class="bi bi-receipt"></i>
                                <span>Tingnan ang Na-upload na Resibo</span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Case 3: Paid & Verified -->
                    <?php elseif ($isVerified): ?>
                    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50/50 p-3.5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-600 text-white font-bold text-sm">
                                    <i class="bi bi-patch-check-fill"></i>
                                </span>
                                <div>
                                    <span class="text-xs font-bold text-emerald-950">
                                        <?= $payStatus === 'PAID_AT_PICKUP' ? 'Nabayaran sa Counter' : 'Naberipika na ang Bayad' ?> (₱<?= number_format($fee, 2) ?>)
                                    </span>
                                    <div class="text-[11px] text-emerald-700">
                                        Ref: <?= e((string) ($r['payment_ref'] ?? 'PAID')) ?>
                                        <?php if (!empty($r['verified_at'])): ?>
                                        &bull; <?= e(format_datetime((string) $r['verified_at'])) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <a href="<?= e(route('documents/' . $reqId . '/acknowledgement')) ?>" target="_blank"
                                   class="inline-flex items-center gap-1 rounded-lg border border-emerald-300 bg-white px-2.5 py-1 text-xs font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50">
                                    <i class="bi bi-printer"></i> Katibayan ng Bayad
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Case 4: Pay at Pickup -->
                    <?php elseif ($isAtPickup): ?>
                    <div class="mt-4 rounded-xl border border-purple-200 bg-purple-50/60 p-3.5">
                        <div class="flex items-start gap-2.5">
                            <i class="bi bi-cash-coin text-purple-600 text-lg mt-0.5"></i>
                            <div>
                                <span class="text-xs font-bold text-purple-950">Magbabayad sa Counter pagkuha (₱<?= number_format($fee, 2) ?>)</span>
                                <p class="text-[11px] text-purple-800 mt-0.5">
                                    Pakidala ang eksaktong halaga sa Barangay Hall pagkuha ng inyong opisyal na dokumento.
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php endif; ?>

                <!-- SOFT COPY ATTACHMENT CARD (If uploaded by Admin) -->
                <?php if ($hasFile): ?>
                    <?php if ($isDigital && !$isVerified): ?>
                    <!-- Digital Soft copy uploaded but payment locked -->
                    <div class="mt-4 rounded-xl border-2 border-amber-200 bg-amber-50/60 p-4">
                        <div class="flex items-start gap-3">
                            <i class="bi bi-lock-fill text-amber-600 text-xl mt-0.5"></i>
                            <div>
                                <h4 class="text-sm font-bold text-amber-950">Handa na ang Digital Soft Copy ngunit naka-kandado</h4>
                                <p class="text-xs text-amber-800 mt-0.5">
                                    Nai-upload na ng kawani ang opisyal na kopya, ngunit kailangan munang maberipika ang inyong bayad (₱<?= number_format($fee, 2) ?>) bago ma-download.
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Fully unlocked and downloadable -->
                    <div class="mt-4 rounded-xl border-2 border-indigo-100 bg-gradient-to-br from-indigo-50/60 to-blue-50/40 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-sm">
                                    <?php
                                    $ft = strtolower((string) $r['document_file_type']);
                                    if (str_contains($ft, 'pdf')) {
                                        echo '<i class="bi bi-file-earmark-pdf" style="font-size:1.35rem;"></i>';
                                    } elseif (str_contains($ft, 'image')) {
                                        echo '<i class="bi bi-file-earmark-image" style="font-size:1.35rem;"></i>';
                                    } else {
                                        echo '<i class="bi bi-file-earmark-word" style="font-size:1.35rem;"></i>';
                                    }
                                    ?>
                                </span>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h4 class="truncate text-sm font-bold text-slate-900" title="<?= e((string) $r['document_file_name']) ?>">
                                            <?= e((string) $r['document_file_name']) ?>
                                        </h4>
                                        <span class="rounded bg-indigo-200/80 px-1.5 py-0.2 text-[10px] font-bold uppercase tracking-wider text-indigo-800">Opisyal</span>
                                    </div>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        <?= \App\Models\DocumentRequest::formatFileSize((int) $r['document_file_size']) ?>
                                        <?php if (!empty($r['document_uploaded_at'])): ?>
                                        &bull; <?= e(format_datetime((string) $r['document_uploaded_at'])) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Action Buttons: Preview, Download, Print -->
                            <div class="flex flex-wrap items-center gap-2">
                                <!-- Preview button -->
                                <a href="<?= e(route('documents/' . $reqId . '/preview')) ?>"
                                   target="_blank"
                                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:text-slate-900"
                                   title="<?= e(t('documents.preview_btn')) ?>">
                                    <i class="bi bi-eye"></i>
                                    <span><?= e(t('documents.preview_btn')) ?></span>
                                </a>

                                <!-- Download button -->
                                <a href="<?= e(route('documents/' . $reqId . '/download')) ?>"
                                   class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3.5 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-indigo-700 active:scale-95">
                                    <i class="bi bi-download"></i>
                                    <span><?= e(t('documents.download_btn')) ?></span>
                                </a>

                                <!-- Print button -->
                                <button type="button"
                                        onclick="printDocument('<?= e(route('documents/' . $reqId . '/preview')) ?>')"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-300 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 shadow-sm transition hover:bg-indigo-50">
                                    <i class="bi bi-printer"></i>
                                    <span><?= e(t('documents.print_btn')) ?></span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php elseif ($isDigital && $st !== 'rejected'): ?>
                <!-- Digital but file not yet uploaded -->
                <div class="mt-3.5 flex items-center gap-2 rounded-xl bg-slate-50 border border-dashed border-slate-200 p-3 text-xs text-slate-500">
                    <i class="bi bi-info-circle text-slate-400"></i>
                    <span>Inihahanda ng kawani ng barangay ang opisyal na digital file. Makakatanggap kayo ng abiso at lalabas dito ang download button kapag handa na.</span>
                </div>
                <?php elseif (!$isDigital && $st === 'ready'): ?>
                <!-- Pickup Ready Instructions -->
                <div class="mt-3.5 rounded-xl border border-emerald-200 bg-emerald-50/70 p-3.5 text-xs leading-relaxed text-emerald-900">
                    <div class="flex items-start gap-2.5">
                        <i class="bi bi-check-circle-fill text-emerald-600 text-base mt-0.5"></i>
                        <div>
                            <strong class="font-bold text-emerald-900">Handa na para sa personal na pagkuha!</strong>
                            <p class="mt-0.5 text-emerald-800">
                                Maaari na ninyong kunin ang inyong dokumento sa Barangay Hall Counter. Magdala ng 1 valid ID at ibigay ang Reference No. <code class="font-bold font-mono text-emerald-950 bg-emerald-100/80 px-1 py-0.5 rounded"><?= e((string) $r['reference_no']) ?></code>.
                                <?php if ($isAtPickup): ?>
                                Ihanda rin ang <strong>₱<?= number_format($fee, 2) ?></strong> para sa bayarin.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Footer Timestamps -->
                <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 text-[11px] text-slate-400">
                    <div>
                        <?= e(t('documents.filed_on')) ?>: <strong><?= e(format_datetime((string) $r['requested_at'])) ?></strong>
                    </div>
                    <?php if (!empty($r['ready_at'])): ?>
                    <div>
                        <?= e(t('documents.ready_on')) ?>: <strong class="text-emerald-700"><?= e(format_datetime((string) $r['ready_at'])) ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($r['released_at'])): ?>
                    <div>
                        Natapos: <strong class="text-blue-700"><?= e(format_datetime((string) $r['released_at'])) ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal to View Larger QR -->
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 hidden" id="qrModal">
    <div class="relative w-full max-w-sm rounded-2xl bg-white p-5 text-center shadow-2xl">
        <h3 class="text-base font-bold text-slate-900 mb-1" id="qrModalTitle">GCash QR Code</h3>
        <p class="text-xs text-slate-500 mb-3">I-scan gamit ang GCash App para sa eksaktong bayad.</p>
        <img id="qrModalImg" src="" alt="Enlarged QR" class="mx-auto max-h-72 rounded-xl border border-slate-200 shadow-sm">
        <button type="button" onclick="closeQrModal()" class="mt-4 w-full rounded-xl bg-slate-100 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-200">
            Isara
        </button>
    </div>
</div>

<!-- Hidden iframe for instant printing -->
<iframe id="printFrame" style="display:none;width:0;height:0;border:0;"></iframe>

<script>
function printDocument(url) {
    const frame = document.getElementById('printFrame');
    if (!frame) return;
    frame.src = url;
    frame.onload = function() {
        try {
            frame.contentWindow.focus();
            frame.contentWindow.print();
        } catch (e) {
            const win = window.open(url, '_blank');
            if (win) {
                win.focus();
                win.print();
            }
        }
    };
}

function viewLargerQr(src, name) {
    document.getElementById('qrModalImg').src = src;
    document.getElementById('qrModalTitle').innerText = name + ' - GCash QR';
    document.getElementById('qrModal').classList.remove('hidden');
}

function closeQrModal() {
    document.getElementById('qrModal').classList.add('hidden');
}

// Dynamic Fee and Payment Option Handler
function handleDocTypeChange() {
    const select = document.getElementById('document_type');
    const selectedOpt = select.options[select.selectedIndex];
    const amount = parseFloat(selectedOpt.getAttribute('data-amount') || '0');
    const isFree = selectedOpt.getAttribute('data-free') === '1' || amount <= 0;

    const badge = document.getElementById('feeBadge');
    const paySec = document.getElementById('paymentMethodSection');
    const freeBanner = document.getElementById('freeDocumentBanner');

    if (isFree) {
        badge.innerText = 'LIBRE';
        badge.className = 'rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700 border border-emerald-100';
        paySec.classList.add('hidden');
        freeBanner.classList.remove('hidden');
    } else {
        badge.innerText = '₱' + amount.toFixed(2);
        badge.className = 'rounded-full bg-blue-50 px-2 py-0.5 text-xs font-bold text-blue-700 border border-blue-100';
        paySec.classList.remove('hidden');
        freeBanner.classList.add('hidden');
    }

    handleDeliveryChange();
}

function handleDeliveryChange() {
    const delRadio = document.querySelector('input[name="delivery_method"]:checked');
    const delMethod = delRadio ? delRadio.value : 'pickup';

    const select = document.getElementById('document_type');
    const selectedOpt = select.options[select.selectedIndex];
    const amount = parseFloat(selectedOpt.getAttribute('data-amount') || '0');
    const isFree = selectedOpt.getAttribute('data-free') === '1' || amount <= 0;

    const optPickup = document.getElementById('optPickupWrapper');
    const optGcash = document.getElementById('optGcashWrapper');
    const digitalNotice = document.getElementById('digitalPaymentNotice');
    const pickupRadio = document.querySelector('input[name="payment_method"][value="pickup"]');
    const gcashRadio = document.querySelector('input[name="payment_method"][value="gcash"]');

    if (delMethod === 'digital' && !isFree) {
        // Digital soft copy requires Online GCash
        if (optPickup) {
            optPickup.classList.add('hidden');
        }
        if (gcashRadio) {
            gcashRadio.checked = true;
        }
        if (digitalNotice) {
            digitalNotice.classList.remove('hidden');
        }
    } else {
        if (optPickup) {
            optPickup.classList.remove('hidden');
        }
        if (digitalNotice) {
            digitalNotice.classList.add('hidden');
        }
    }

    updateDeliveryCardStyles();
    updatePaymentCardStyles();
}

function updateDeliveryCardStyles() {
    document.querySelectorAll('.delivery-option-card').forEach(card => {
        const r = card.querySelector('input[type="radio"]');
        if (r && r.checked) {
            card.classList.add('border-blue-600', 'bg-blue-50/40', 'shadow-sm');
            card.classList.remove('border-slate-200', 'bg-slate-50/50');
        } else {
            card.classList.remove('border-blue-600', 'bg-blue-50/40', 'shadow-sm');
            card.classList.add('border-slate-200', 'bg-slate-50/50');
        }
    });
}

function updatePaymentCardStyles() {
    document.querySelectorAll('.payment-method-card').forEach(card => {
        const r = card.querySelector('input[type="radio"]');
        if (r && r.checked) {
            card.classList.add('border-blue-600', 'bg-blue-50/40', 'shadow-sm');
            card.classList.remove('border-slate-200', 'bg-white');
        } else {
            card.classList.remove('border-blue-600', 'bg-blue-50/40', 'shadow-sm');
            card.classList.add('border-slate-200', 'bg-white');
        }
    });
}

document.querySelectorAll('input[name="delivery_method"]').forEach(r => {
    r.addEventListener('change', handleDeliveryChange);
});
document.querySelectorAll('input[name="payment_method"]').forEach(r => {
    r.addEventListener('change', updatePaymentCardStyles);
});

// Run on load
document.addEventListener('DOMContentLoaded', () => {
    handleDocTypeChange();
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
