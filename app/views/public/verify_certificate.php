<?php
/**
 * Public document verification (opened by the QR code on every document).
 *
 * Shows only what is needed to check a document: its type, control number,
 * date issued, who it was issued to (and the business, for a permit) and
 * whether it is VALID or CANCELLED. No address, purpose, phone or other
 * private detail is shown. Only issued documents are found.
 *
 * Variables: $doc (array|null), $ref (string)
 */
use App\Models\DocumentRequest;
use App\Models\DocumentTemplate;

$doc       = $doc ?? null;
$ref       = $ref ?? '';
$found     = !empty($doc);
$cancelled = $found && !empty($doc['revoked_at']);
$expired   = $found && !$cancelled && !empty($doc['valid_until']) && strtotime((string) $doc['valid_until']) < strtotime('today');
$barangay  = DocumentTemplate::setting('doc_barangay_name');

// "Silva S. Biro" → "Silva S. B." is not needed: the name is printed on the
// paper the verifier is holding; showing it lets them compare the two.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= e(__('Document Verification')) ?> — BarangGabay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-slate-100 min-h-screen p-4 flex items-center justify-center" style="font-family:system-ui,-apple-system,'Segoe UI',sans-serif;">

<main class="w-full max-w-md">
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl">
        <header class="text-center pb-4 border-b border-slate-100">
            <img src="<?= e(asset('assets/images/documents/seal.png')) ?>" alt="<?= e($barangay) ?> seal" class="mx-auto mb-2 h-16 w-16 rounded-full object-cover">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500"><?= e(__('Republic of the Philippines')) ?></div>
            <div class="text-xs text-slate-600"><?= e(DocumentTemplate::setting('doc_municipality')) ?>, <?= e(preg_replace('/^Province of\s+/i', '', DocumentTemplate::setting('doc_province'))) ?></div>
            <div class="text-lg font-black text-slate-900"><?= e(mb_strtoupper($barangay)) ?></div>
            <div class="mt-1 text-[11px] font-semibold text-slate-500"><i class="bi bi-qr-code-scan"></i> <?= e(__('Document Verification')) ?></div>
        </header>

        <?php if ($found): ?>
            <?php if ($cancelled): ?>
            <div class="mt-5 rounded-2xl border-2 border-rose-300 bg-rose-50 p-4 text-center" role="status">
                <i class="bi bi-x-octagon-fill text-3xl text-rose-600"></i>
                <h1 class="mt-1 text-lg font-black text-rose-900"><?= e(__('CANCELLED')) ?></h1>
                <p class="text-xs text-rose-800"><?= e(__('This document was issued by :barangay but has since been cancelled. It is no longer valid.', ['barangay' => $barangay])) ?></p>
            </div>
            <?php elseif ($expired): ?>
            <div class="mt-5 rounded-2xl border-2 border-amber-300 bg-amber-50 p-4 text-center" role="status">
                <i class="bi bi-calendar-x-fill text-3xl text-amber-600"></i>
                <h1 class="mt-1 text-lg font-black text-amber-900"><?= e(__('EXPIRED')) ?></h1>
                <p class="text-xs text-amber-800"><?= e(__('This permit was genuine but its validity has ended.')) ?></p>
            </div>
            <?php else: ?>
            <div class="mt-5 rounded-2xl border-2 border-emerald-300 bg-emerald-50 p-4 text-center" role="status">
                <i class="bi bi-patch-check-fill text-3xl text-emerald-600"></i>
                <h1 class="mt-1 text-lg font-black text-emerald-900"><?= e(__('VALID')) ?></h1>
                <p class="text-xs text-emerald-800"><?= e(__('This is a genuine document issued by :barangay.', ['barangay' => $barangay])) ?></p>
            </div>
            <?php endif; ?>

            <dl class="mt-5 text-sm">
                <?php
                $rows = [
                    __('Document')     => DocumentRequest::label((string) $doc['document_type']),
                    __('Control No.')  => (string) $doc['certificate_no'],
                    __('Date issued')  => !empty($doc['issued_at']) ? date('F j, Y', strtotime((string) $doc['issued_at'])) : '—',
                    __('Issued to')    => (string) $doc['full_name'],
                ];
                if (!empty($doc['business_name'])) {
                    $rows[__('Business')] = trim((string) $doc['business_name'] . (!empty($doc['business_type']) ? ' (' . $doc['business_type'] . ')' : ''));
                }
                if (!empty($doc['permit_no'])) {
                    $rows[__('Permit No.')] = (string) $doc['permit_no'];
                }
                if (!empty($doc['valid_until'])) {
                    $rows[__('Valid until')] = date('F j, Y', strtotime((string) $doc['valid_until']));
                }
                $rows[__('Status')] = $cancelled ? __('Cancelled') : ($expired ? __('Expired') : __('Valid'));
                ?>
                <?php foreach ($rows as $label => $value): ?>
                <div class="flex justify-between gap-3 border-b border-slate-100 py-2">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-500"><?= e($label) ?></dt>
                    <dd class="text-right font-bold text-slate-900"><?= e($value) ?></dd>
                </div>
                <?php endforeach; ?>
            </dl>
            <p class="mt-4 text-center text-[11px] text-slate-500"><?= e(__('Compare these details with the paper or PDF you were given. If they differ, the document may have been altered.')) ?></p>
        <?php else: ?>
            <div class="mt-5 rounded-2xl border-2 border-rose-300 bg-rose-50 p-5 text-center" role="status">
                <i class="bi bi-question-octagon-fill text-3xl text-rose-600"></i>
                <h1 class="mt-1 text-lg font-black text-rose-900"><?= e(__('NOT FOUND')) ?></h1>
                <p class="text-xs text-rose-800"><?= e(__('No issued document has the number :ref.', ['ref' => $ref])) ?></p>
                <p class="mt-2 text-[11px] text-rose-700"><?= e(__('It may be fake or mistyped. Please check with the Barangay Hall.')) ?></p>
            </div>
        <?php endif; ?>

        <footer class="mt-6 border-t border-slate-100 pt-3 text-center text-[11px] text-slate-400">
            <a href="<?= e(route('')) ?>" class="font-semibold text-emerald-700"><?= e(__('BarangGabay portal')) ?></a> · &copy; <?= date('Y') ?> <?= e($barangay) ?>
        </footer>
    </div>
</main>
</body>
</html>
