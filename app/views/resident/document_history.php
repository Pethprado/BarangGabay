<?php
/**
 * Resident Document History
 *
 * Variables: $requests (array of document requests for this resident)
 */
$requests = $requests ?? [];

$statusBadges = [
    'pending'                 => 'bg-slate-100 text-slate-700 border-slate-200',
    'under_review'            => 'bg-blue-50 text-blue-800 border-blue-200',
    'processing'              => 'bg-amber-50 text-amber-800 border-amber-200',
    'needs_information'       => 'bg-purple-50 text-purple-800 border-purple-200',
    'approved'                => 'bg-emerald-50 text-emerald-800 border-emerald-200',
    'ready'                   => 'bg-emerald-50 text-emerald-800 border-emerald-200',
    'ready_for_pickup'        => 'bg-emerald-50 text-emerald-800 border-emerald-200',
    'available_for_download'  => 'bg-indigo-50 text-indigo-800 border-indigo-200',
    'out_for_delivery'        => 'bg-sky-50 text-sky-800 border-sky-200',
    'released'                => 'bg-blue-50 text-blue-800 border-blue-200',
    'completed'               => 'bg-emerald-50 text-emerald-800 border-emerald-200',
    'rejected'                => 'bg-rose-50 text-rose-800 border-rose-200',
    'cancelled'               => 'bg-slate-100 text-slate-500 border-slate-200',
];

ob_start();
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <div class="flex items-center gap-2">
            <a href="<?= e(route('documents')) ?>" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-800">
                <i class="bi bi-arrow-left"></i> Bumalik sa Kahilingan (Back to Requests)
            </a>
        </div>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Kasaysayan ng mga Dokumento (Document History)</h1>
        <p class="text-xs text-slate-500">Talaan ng lahat ng inyong hiniling at inisyung opisyal na sertipiko ng Barangay.</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= e(route('documents')) ?>"
           class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-blue-700">
            <i class="bi bi-file-earmark-plus"></i>
            <span>Bagong Kahilingan (New Request)</span>
        </a>
    </div>
</div>

<?php if (empty($requests)): ?>
<div class="rounded-2xl border-2 border-dashed border-slate-200 bg-white p-12 text-center shadow-sm">
    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-50 text-blue-500">
        <i class="bi bi-folder2-open text-3xl"></i>
    </div>
    <h3 class="mt-4 text-base font-bold text-slate-800">Wala pang naitalang dokumento</h3>
    <p class="mt-1 text-xs text-slate-500 max-w-sm mx-auto">
        Hindi pa kayo nakakahiling ng anumang opisyal na sertipiko. Mag-file ng kahilingan sa pamamagitan ng pag-click sa button sa itaas.
    </p>
    <a href="<?= e(route('documents')) ?>" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700">
        Mag-request ng Dokumento
    </a>
</div>
<?php else: ?>

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-600">
            <thead class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-bold uppercase tracking-wider text-slate-700">
                <tr>
                    <th scope="col" class="py-3.5 px-4">Dokumento (Document)</th>
                    <th scope="col" class="py-3.5 px-4">Reference / Cert #</th>
                    <th scope="col" class="py-3.5 px-4">Layunin (Purpose)</th>
                    <th scope="col" class="py-3.5 px-4">Paraan (Method)</th>
                    <th scope="col" class="py-3.5 px-4">Petsa (Date)</th>
                    <th scope="col" class="py-3.5 px-4">Estado (Status)</th>
                    <th scope="col" class="py-3.5 px-4 text-right">Aksyon (Action)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($requests as $r):
                    $reqId    = (int) $r['id'];
                    $st       = (string) $r['status'];
                    $delivery = (string) ($r['delivery_method'] ?? 'pickup');
                    $certNo   = (string) ($r['certificate_no'] ?? '');
                    $hasFile  = !empty($r['document_file_name']);
                    $isVerified = in_array((string)($r['payment_status'] ?? ''), ['PAID_VERIFIED', 'PAID_AT_PICKUP', 'FREE', 'WAIVED'], true);
                ?>
                <tr class="hover:bg-slate-50/60 transition">
                    <td class="py-3.5 px-4">
                        <div class="font-bold text-slate-900">
                            <?= e(\App\Models\DocumentRequest::label((string) $r['document_type'])) ?>
                        </div>
                        <?php if (!empty($r['fee_amount']) && (float)$r['fee_amount'] > 0): ?>
                        <div class="text-[10px] text-slate-400">Bayarin: ₱<?= number_format((float)$r['fee_amount'], 2) ?></div>
                        <?php else: ?>
                        <div class="text-[10px] text-emerald-600 font-semibold">LIBRE</div>
                        <?php endif; ?>
                    </td>
                    <td class="py-3.5 px-4 font-mono">
                        <div class="text-slate-700 font-semibold"><?= e((string) $r['reference_no']) ?></div>
                        <?php if ($certNo !== ''): ?>
                        <div class="text-indigo-600 font-bold flex items-center gap-1 mt-0.5">
                            <i class="bi bi-patch-check-fill text-[11px]"></i>
                            <?= e($certNo) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="py-3.5 px-4 max-w-xs truncate" title="<?= e((string) $r['purpose']) ?>">
                        <?= e((string) $r['purpose']) ?>
                    </td>
                    <td class="py-3.5 px-4">
                        <?php if ($delivery === 'digital'): ?>
                        <span class="inline-flex items-center gap-1 rounded bg-indigo-50 border border-indigo-200 px-2 py-0.5 text-[10px] font-bold text-indigo-700">
                            <i class="bi bi-file-earmark-arrow-down"></i> Digital Soft Copy
                        </span>
                        <?php elseif ($delivery === 'delivery'): ?>
                        <span class="inline-flex items-center gap-1 rounded bg-sky-50 border border-sky-200 px-2 py-0.5 text-[10px] font-bold text-sky-700">
                            <i class="bi bi-truck"></i> Delivery
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1 rounded bg-slate-100 border border-slate-200 px-2 py-0.5 text-[10px] font-semibold text-slate-700">
                            <i class="bi bi-building"></i> Hall Pickup
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="py-3.5 px-4 whitespace-nowrap">
                        <div class="text-slate-700"><?= e(date('M d, Y', strtotime((string)$r['requested_at']))) ?></div>
                        <?php if (!empty($r['issued_at'])): ?>
                        <div class="text-[10px] text-emerald-600">Inisyu: <?= e(date('M d, Y', strtotime((string)$r['issued_at']))) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="py-3.5 px-4 whitespace-nowrap">
                        <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[11px] font-bold <?= $statusBadges[$st] ?? 'bg-slate-100 text-slate-700 border-slate-200' ?>">
                            <?= e(\App\Models\DocumentRequest::statusLabel($st)) ?>
                        </span>
                    </td>
                    <td class="py-3.5 px-4 text-right whitespace-nowrap">
                        <div class="flex items-center justify-end gap-1.5">
                            <?php if ($hasFile && ($delivery !== 'digital' || $isVerified)): ?>
                            <a href="<?= e(route('documents/' . $reqId . '/preview')) ?>" target="_blank"
                               class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50"
                               title="Tingnan (Preview)">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="<?= e(route('documents/' . $reqId . '/download')) ?>"
                               class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-2.5 py-1 text-[11px] font-bold text-white hover:bg-indigo-700"
                               title="I-download">
                                <i class="bi bi-download"></i> Download
                            </a>
                            <?php elseif ($certNo !== ''): ?>
                            <a href="<?= e(route('documents/' . urlencode($certNo) . '/verify')) ?>" target="_blank"
                               class="inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-[11px] font-semibold text-indigo-700 hover:bg-indigo-100"
                               title="QR Beripikasyon">
                                <i class="bi bi-qr-code"></i> Beripikahin
                            </a>
                            <?php else: ?>
                            <span class="text-slate-400 text-[11px]">—</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
