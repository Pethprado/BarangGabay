<?php
/**
 * Public Certificate Verification Page (Scanned via QR Code on Certificate)
 *
 * Variables: $doc (array|null), $ref (string)
 */
$doc = $doc ?? null;
$ref = $ref ?? '';

$isValid = !empty($doc);
$docType = $doc ? \App\Models\DocumentRequest::label((string)$doc['document_type']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sertipikasyon ng Beripikasyon — BARANGGABAY</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Cinzel:wght@600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-cinzel { font-family: 'Cinzel', serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-100 via-blue-50/40 to-slate-100 min-h-screen p-4 flex items-center justify-center">

<div class="w-full max-w-lg">
    <!-- Card Container -->
    <div class="rounded-3xl border border-slate-200/80 bg-white p-6 md:p-8 shadow-xl">
        <!-- Barangay Header -->
        <div class="text-center pb-5 border-b border-slate-100">
            <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 shadow-sm border border-blue-100">
                <i class="bi bi-shield-check text-3xl"></i>
            </div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Republika ng Pilipinas</div>
            <div class="text-xs font-semibold text-slate-600"><?= e(\App\Models\Setting::get('doc_province', 'Lalawigan ng Surigao del Sur')) ?> &bull; <?= e(\App\Models\Setting::get('doc_municipality', 'Bayan ng Madrid')) ?></div>
            <div class="mt-0.5 text-lg font-black tracking-tight text-blue-950 font-cinzel"><?= e(strtoupper(\App\Models\Setting::get('doc_barangay_name', 'BARANGAY BAYOGO'))) ?></div>
            <div class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-0.5 text-[11px] font-bold text-slate-700">
                <i class="bi bi-qr-code-scan text-blue-600"></i>
                Sistema ng Beripikasyon ng Dokumento
            </div>
        </div>

        <?php if ($isValid): ?>
        <!-- Valid Banner -->
        <div class="mt-6 rounded-2xl border-2 border-emerald-300 bg-emerald-50/80 p-4 text-center">
            <div class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500 text-white shadow-sm mb-2">
                <i class="bi bi-check-lg text-2xl font-bold"></i>
            </div>
            <h2 class="text-base font-black text-emerald-950">OPISYAL AT TOTOO NA DOKUMENTO</h2>
            <p class="text-xs text-emerald-800 mt-0.5">
                Ang sertipikong ito ay lehitimong inisyu ng <?= e(\App\Models\Setting::get('doc_barangay_name', 'Barangay Bayogo')) ?> at nakatala sa opisyal na database.
            </p>
        </div>

        <!-- Details Grid -->
        <div class="mt-6 space-y-3.5 text-xs text-slate-600">
            <div class="flex justify-between items-center py-1.5 border-b border-slate-100">
                <span class="text-slate-400 font-semibold uppercase tracking-wider text-[10px]">Uri ng Dokumento</span>
                <span class="font-bold text-slate-900 text-sm"><?= e($docType) ?></span>
            </div>

            <div class="flex justify-between items-center py-1.5 border-b border-slate-100">
                <span class="text-slate-400 font-semibold uppercase tracking-wider text-[10px]">Certificate No.</span>
                <span class="font-mono font-bold text-blue-700 text-sm"><?= e((string)($doc['certificate_no'] ?: $doc['reference_no'])) ?></span>
            </div>

            <div class="flex justify-between items-center py-1.5 border-b border-slate-100">
                <span class="text-slate-400 font-semibold uppercase tracking-wider text-[10px]">Ipinagkaloob Kay (Issued To)</span>
                <span class="font-bold text-slate-900"><?= e((string)$doc['full_name']) ?></span>
            </div>

            <div class="flex justify-between items-center py-1.5 border-b border-slate-100">
                <span class="text-slate-400 font-semibold uppercase tracking-wider text-[10px]">Tirahan (Address)</span>
                <span class="font-medium text-slate-800 text-right max-w-[240px] truncate"><?= e((string)$doc['address']) ?></span>
            </div>

            <div class="flex justify-between items-center py-1.5 border-b border-slate-100">
                <span class="text-slate-400 font-semibold uppercase tracking-wider text-[10px]">Layunin (Purpose)</span>
                <span class="font-medium text-slate-800 text-right max-w-[240px] truncate"><?= e((string)$doc['purpose']) ?></span>
            </div>

            <div class="flex justify-between items-center py-1.5 border-b border-slate-100">
                <span class="text-slate-400 font-semibold uppercase tracking-wider text-[10px]">Petsa ng Pagkaka-isyu</span>
                <span class="font-semibold text-slate-800">
                    <?= !empty($doc['issued_at']) ? date('F d, Y', strtotime((string)$doc['issued_at'])) : date('F d, Y', strtotime((string)$doc['requested_at'])) ?>
                </span>
            </div>

            <div class="flex justify-between items-center py-1.5 border-b border-slate-100">
                <span class="text-slate-400 font-semibold uppercase tracking-wider text-[10px]">Nag-isyung Opisyal</span>
                <span class="font-semibold text-slate-800"><?= e((string)($doc['issued_by_name'] ?: 'Barangay Secretary')) ?></span>
            </div>

            <div class="flex justify-between items-center py-1.5">
                <span class="text-slate-400 font-semibold uppercase tracking-wider text-[10px]">Estado (Status)</span>
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-bold text-emerald-800">
                    <i class="bi bi-patch-check-fill text-xs"></i> Aktibo / Rehistrado
                </span>
            </div>
        </div>

        <div class="mt-6 rounded-2xl bg-blue-50/60 p-3.5 text-center text-[11px] text-blue-900 border border-blue-100">
            <i class="bi bi-lock-fill text-blue-600 me-1"></i>
            Ang rekord na ito ay protektado ng cryptographic QR code at may digital security hash sa database ng BARANGGABAY.
        </div>

        <?php else: ?>
        <!-- Invalid Banner -->
        <div class="mt-6 rounded-2xl border-2 border-rose-300 bg-rose-50/80 p-5 text-center">
            <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-rose-500 text-white shadow-sm mb-3">
                <i class="bi bi-x-lg text-2xl font-bold"></i>
            </div>
            <h2 class="text-base font-black text-rose-950">HINDI NATAGPUAN ANG SERTIPIKO</h2>
            <p class="text-xs text-rose-800 mt-1">
                Ang reference number o QR code na <code class="font-mono font-bold"><?= e($ref) ?></code> ay hindi natagpuan sa opisyal na rekord ng Barangay Poblacion.
            </p>
            <p class="text-[11px] text-rose-600 mt-2">
                Maaaring peke, binawi, o mali ang na-scan na reference number. Mangyaring sumangguni sa Barangay Hall para sa opisyal na pagpapatotoo.
            </p>
        </div>
        <?php endif; ?>

        <div class="mt-6 text-center border-t border-slate-100 pt-4">
            <a href="/" class="text-xs font-semibold text-blue-600 hover:text-blue-800">
                &larr; Pumunta sa BARANGGABAY Portal
            </a>
            <div class="text-[10px] text-slate-400 mt-1">
                &copy; <?= date('Y') ?> Pamahalaang Barangay ng Poblacion. All rights reserved.
            </div>
        </div>
    </div>
</div>

</body>
</html>
