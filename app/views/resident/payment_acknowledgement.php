<?php
/**
 * Resident Payment Acknowledgement Slip
 */
$request = $request ?? [];
$payment = $payment ?? [];

$amount    = (float) ($payment['amount_due'] ?? 0);
$docType   = (string) ($request['document_type'] ?? '');
$docLabel  = \App\Models\DocumentRequest::label($docType);
$payStatus = (string) ($payment['payment_status'] ?? '');
$isPaid    = in_array($payStatus, ['PAID_VERIFIED', 'PAID_AT_PICKUP'], true);
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katibayan ng Pagbabayad — <?= e((string) $payment['payment_ref']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8fafc;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            color: #1e293b;
        }
        .receipt-card {
            max-width: 580px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        @media print {
            body { background: #fff; }
            .receipt-card { box-shadow: none; border: 1px solid #ccc; margin: 0 auto; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="py-4">

<div class="receipt-card p-4 p-md-5">
    <!-- Header -->
    <div class="text-center pb-4 border-bottom">
        <div class="d-flex justify-content-center align-items-center gap-2 mb-2">
            <span class="d-inline-flex p-2 rounded-3 bg-primary text-white">
                <i class="bi bi-wallet2 fs-4"></i>
            </span>
            <span class="fs-4 fw-bold text-dark tracking-tight">BARANGGABAY</span>
        </div>
        <div class="small text-muted text-uppercase tracking-wider fw-semibold">
            Barangay Bayogo, Madrid, Surigao del Sur
        </div>
        <h2 class="h5 fw-bold text-dark mt-2 mb-0">KATIBAYAN NG PAGBABAYAD</h2>
        <span class="text-muted" style="font-size:.78rem;">(Electronic Payment Acknowledgement)</span>
    </div>

    <!-- Status Banner -->
    <div class="my-4 p-3 rounded-3 text-center <?= $isPaid ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning border border-warning-subtle' ?>">
        <div class="d-flex align-items-center justify-content-center gap-2">
            <i class="bi <?= $isPaid ? 'bi-patch-check-fill fs-5' : 'bi-clock-history fs-5' ?>"></i>
            <span class="fw-bold text-uppercase tracking-wider"><?= e(\App\Models\DocumentPayment::statusLabel($payStatus)) ?></span>
        </div>
        <div class="small mt-1" style="font-size:.75rem;">
            <?= $isPaid ? 'Matagumpay na natanggap at naberipika ang inyong bayad.' : 'Kasalukuyan pang pinoproseso ang pagbabayad na ito.' ?>
        </div>
    </div>

    <!-- Details Grid -->
    <div class="row g-3 py-2 small border-bottom mb-4">
        <div class="col-6">
            <span class="text-muted text-uppercase" style="font-size:.7rem;">Payment Reference:</span>
            <div class="fw-bold font-monospace text-primary fs-6"><?= e((string) $payment['payment_ref']) ?></div>
        </div>
        <div class="col-6 text-end">
            <span class="text-muted text-uppercase" style="font-size:.7rem;">Request Reference:</span>
            <div class="fw-bold font-monospace text-dark fs-6"><?= e((string) $request['reference_no']) ?></div>
        </div>

        <div class="col-6">
            <span class="text-muted text-uppercase" style="font-size:.7rem;">Pangalan ng Residente:</span>
            <div class="fw-bold text-dark"><?= e((string) $request['full_name']) ?></div>
        </div>
        <div class="col-6 text-end">
            <span class="text-muted text-uppercase" style="font-size:.7rem;">Uri ng Dokumento:</span>
            <div class="fw-bold text-dark"><?= e($docLabel) ?></div>
        </div>

        <div class="col-6">
            <span class="text-muted text-uppercase" style="font-size:.7rem;">Paraan ng Pagbayad:</span>
            <div class="fw-bold text-dark"><?= strtoupper((string) $payment['payment_method']) ?></div>
        </div>
        <div class="col-6 text-end">
            <span class="text-muted text-uppercase" style="font-size:.7rem;">GCash Reference:</span>
            <div class="fw-bold font-monospace text-dark"><?= e((string) ($payment['gcash_reference_no'] ?: 'N/A (Counter)')) ?></div>
        </div>

        <div class="col-6">
            <span class="text-muted text-uppercase" style="font-size:.7rem;">Petsa ng Transaksyon:</span>
            <div class="fw-semibold text-secondary"><?= e(format_datetime((string) $payment['created_at'])) ?></div>
        </div>
        <div class="col-6 text-end">
            <span class="text-muted text-uppercase" style="font-size:.7rem;">Petsa ng Beripikasyon:</span>
            <div class="fw-semibold text-secondary"><?= !empty($payment['verified_at']) ? e(format_datetime((string) $payment['verified_at'])) : 'Pending' ?></div>
        </div>
    </div>

    <!-- Amount Summary Box -->
    <div class="p-3 bg-light rounded-3 d-flex justify-content-between align-items-center mb-4 border">
        <div>
            <span class="text-muted text-uppercase fw-semibold" style="font-size:.72rem;">Kabuuang Halaga</span>
            <div class="small text-muted">Document Fee</div>
        </div>
        <div class="h3 fw-bold text-success mb-0">
            ₱<?= number_format($amount, 2) ?>
        </div>
    </div>

    <!-- Notice -->
    <div class="p-3 rounded-2 bg-light text-muted" style="font-size:.72rem;line-height:1.5;">
        <i class="bi bi-info-circle me-1 text-primary"></i>
        <strong>Paalala:</strong> Ito ay isang digital system payment acknowledgement mula sa BarangGabay. Hindi ito pamalit sa opisyal na Official Receipt (OR) ng pamahalaan maliban kung may hiwalay na itinalagang opisyal na resibo ang Barangay Treasurer.
    </div>

    <!-- Print / Close buttons -->
    <div class="text-center mt-4 no-print d-flex gap-2 justify-content-center">
        <button type="button" class="btn btn-primary px-4 fw-semibold" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>I-print ang Katibayan
        </button>
        <button type="button" class="btn btn-outline-secondary px-4 fw-semibold" onclick="window.close()">
            Isara
        </button>
    </div>
</div>

</body>
</html>
