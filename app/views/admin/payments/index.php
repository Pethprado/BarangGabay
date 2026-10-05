<?php
/**
 * Admin Payment Management — Dashboard, Transactions, Verification
 */
$pageTitle     = $pageTitle ?? 'Payment Management';
$stats         = $stats ?? [];
$transactions  = $transactions ?? [];
$gcashAccounts = $gcashAccounts ?? [];
$docFees       = $docFees ?? [];
$status        = $status ?? '';
$method        = $method ?? '';
$docType       = $docType ?? '';
$search        = $search ?? '';

$role = (string) ($_SESSION['user_role'] ?? 'staff');
$isAdmin = in_array($role, ['admin', 'superadmin'], true);

$paymentBadges = [
    'FREE'                     => 'background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;',
    'UNPAID'                   => 'background:#fef2f2;color:#991b1b;border:1px solid #fecaca;',
    'PAYMENT_PROOF_SUBMITTED'  => 'background:#fef3c7;color:#92400e;border:1px solid #fde68a;',
    'UNDER_REVIEW'             => 'background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;',
    'PAID_VERIFIED'            => 'background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;',
    'PAYMENT_REJECTED'         => 'background:#fff1f2;color:#be123c;border:1px solid #fecdd3;',
    'PAY_AT_PICKUP'            => 'background:#f5f3ff;color:#5b21b6;border:1px solid #ddd6fe;',
    'PAID_AT_PICKUP'           => 'background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;',
    'WAIVED'                   => 'background:#f8fafc;color:#334155;border:1px solid #e2e8f0;',
    'REFUNDED'                 => 'background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;',
];

ob_start();
?>

<?php
$paypalMode = \App\Services\PayPalService::getMode();
$paypalConfigured = \App\Services\PayPalService::isConfigured();
$paymongoMode = \App\Services\PayMongoService::getMode();
$paymongoConfigured = \App\Services\PayMongoService::isConfigured();
?>

<!-- Header & Subnav -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1" style="color:var(--text-primary);letter-spacing:-.02em;">
            <i class="bi bi-wallet2 text-primary me-2"></i>Pamamahala ng Pagbabayad
        </h1>
        <p class="text-muted small mb-0">Subaybayan at beripikahin ang mga pagbabayad sa PayPal, GCash, at Counter bago ilabas ang dokumento.</p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <a href="<?= e(route('admin/payments/gcash')) ?>" class="btn btn-outline-primary btn-sm fw-semibold">
            <i class="bi bi-qr-code me-1"></i>GCash Accounts
        </a>
        <?php if ($isAdmin): ?>
        <a href="<?= e(route('admin/payments/fees')) ?>" class="btn btn-outline-secondary btn-sm fw-semibold">
            <i class="bi bi-tags me-1"></i>Presyo ng Dokumento
        </a>
        <a href="<?= e(route('admin/payments/export')) ?>" class="btn btn-outline-success btn-sm fw-semibold">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>I-export sa CSV
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Primary Payment Gateway: PayMongo Status Card -->
<div class="card border-0 shadow-sm rounded-3 p-3 mb-3" style="background:var(--card-bg, #fff);border-left:4px solid #10b981 !important;">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-2 d-flex align-items-center justify-content-center" style="background:#ecfdf5;color:#059669;width:42px;height:42px;">
                <i class="bi bi-credit-card-2-front fs-4"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-dark" style="font-size:.9rem;">Primary Gateway: PayMongo</span>
                    <span class="badge rounded-pill bg-<?= $paymongoMode === 'live' ? 'success' : 'warning text-dark' ?>" style="font-size:.7rem;">
                        Mode: <?= strtoupper($paymongoMode) ?>
                    </span>
                    <span class="badge rounded-pill bg-<?= $paymongoConfigured ? 'success-subtle text-success border border-success-subtle' : 'danger-subtle text-danger border border-danger-subtle' ?>" style="font-size:.7rem;">
                        API: <?= $paymongoConfigured ? 'Configured' : 'Missing Keys' ?>
                    </span>
                    <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle" style="font-size:.7rem;">
                        GCash &bull; Maya &bull; Cards &bull; QR PH
                    </span>
                </div>
                <div class="text-muted mt-1" style="font-size:.75rem;">
                    Hosted Checkout: <strong>Active</strong> &bull; Checkout Sessions API: <strong>v2</strong> &bull; Currency: <strong>PHP</strong> &bull; Webhook Verification: <strong>HMAC-SHA256</strong>
                </div>
            </div>
        </div>
        <div>
            <span class="badge bg-light text-secondary border font-monospace" style="font-size:.75rem;">
                Endpoint: /api/payments/paymongo/webhook
            </span>
        </div>
    </div>
</div>

<!-- Secondary Payment Provider: PayPal Status Card -->
<div class="card border-0 shadow-sm rounded-3 p-3 mb-4" style="background:var(--card-bg, #fff);border-left:4px solid #0284c7 !important;">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-2 d-flex align-items-center justify-content-center" style="background:#eff6ff;color:#0284c7;width:42px;height:42px;">
                <i class="bi bi-paypal fs-4"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-dark" style="font-size:.9rem;">Secondary Provider: PayPal</span>
                    <span class="badge rounded-pill bg-<?= $paypalMode === 'live' ? 'success' : 'warning text-dark' ?>" style="font-size:.7rem;">
                        Mode: <?= strtoupper($paypalMode) ?>
                    </span>
                    <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle" style="font-size:.7rem;">
                        API: Connected
                    </span>
                    <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle" style="font-size:.7rem;">
                        Webhook: Ready
                    </span>
                </div>
                <div class="text-muted mt-1" style="font-size:.75rem;">
                    Online Checkout: <strong>Available</strong> &bull; Orders API: <strong>v2</strong> &bull; Currency: <strong>PHP</strong> &bull; Webhook Signature Verification: <strong>Active</strong>
                </div>
            </div>
        </div>
        <div>
            <span class="badge bg-light text-secondary border font-monospace" style="font-size:.75rem;">
                Endpoint: /api/payments/paypal/webhook
            </span>
        </div>
    </div>
</div>

<!-- Dashboard Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm rounded-3 p-3 h-100" style="background:var(--card-bg, #fff);">
            <div class="text-muted small fw-semibold text-uppercase tracking-wider">Koleksyon Ngayon</div>
            <div class="h4 fw-bold mb-0 text-success mt-1">₱<?= number_format((float) ($stats['today_collected'] ?? 0), 2) ?></div>
            <span class="text-muted" style="font-size:.72rem;">Nabayaran ngayong araw</span>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm rounded-3 p-3 h-100" style="background:var(--card-bg, #fff);">
            <div class="text-muted small fw-semibold text-uppercase tracking-wider">Naghihintay ng Beripikasyon</div>
            <div class="h4 fw-bold mb-0 text-warning mt-1"><?= (int) ($stats['pending_count'] ?? 0) ?></div>
            <span class="text-muted" style="font-size:.72rem;">Naisumiteng resibo</span>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm rounded-3 p-3 h-100" style="background:var(--card-bg, #fff);">
            <div class="text-muted small fw-semibold text-uppercase tracking-wider">Na-verify Ngayon</div>
            <div class="h4 fw-bold mb-0 text-primary mt-1"><?= (int) ($stats['verified_today'] ?? 0) ?></div>
            <span class="text-muted" style="font-size:.72rem;">Aprubadong bayad</span>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm rounded-3 p-3 h-100" style="background:var(--card-bg, #fff);">
            <div class="text-muted small fw-semibold text-uppercase tracking-wider">Pay at Pickup</div>
            <div class="h4 fw-bold mb-0 text-purple mt-1" style="color:#7c3aed;"><?= (int) ($stats['pay_at_pickup_count'] ?? 0) ?></div>
            <span class="text-muted" style="font-size:.72rem;">Kukunin sa Hall</span>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm rounded-3 p-3 h-100" style="background:var(--card-bg, #fff);">
            <div class="text-muted small fw-semibold text-uppercase tracking-wider">Tinanggihan</div>
            <div class="h4 fw-bold mb-0 text-danger mt-1"><?= (int) ($stats['rejected_count'] ?? 0) ?></div>
            <span class="text-muted" style="font-size:.72rem;">Maling resibo/ref</span>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm rounded-3 p-3 h-100" style="background:var(--card-bg, #fff);">
            <div class="text-muted small fw-semibold text-uppercase tracking-wider">Kabuuang Naipon</div>
            <div class="h4 fw-bold mb-0 text-dark mt-1">₱<?= number_format((float) ($stats['total_collected'] ?? 0), 2) ?></div>
            <span class="text-muted" style="font-size:.72rem;">Lahat ng bayad</span>
        </div>
    </div>
</div>

<!-- Filters Bar -->
<div class="card border-0 shadow-sm rounded-3 p-3 mb-4" style="background:var(--card-bg, #fff);">
    <form method="get" action="<?= e(route('admin/payments')) ?>" class="row g-2 align-items-center">
        <!-- Status Filter -->
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Lahat ng Estado ng Bayad</option>
                <option value="PAYMENT_PROOF_SUBMITTED" <?= $status === 'PAYMENT_PROOF_SUBMITTED' ? 'selected' : '' ?>>Naghihintay ng Beripikasyon</option>
                <option value="PAID_VERIFIED" <?= $status === 'PAID_VERIFIED' ? 'selected' : '' ?>>Bayad na (Verified)</option>
                <option value="PAY_AT_PICKUP" <?= $status === 'PAY_AT_PICKUP' ? 'selected' : '' ?>>Magbabayad sa Counter</option>
                <option value="PAID_AT_PICKUP" <?= $status === 'PAID_AT_PICKUP' ? 'selected' : '' ?>>Nabayaran sa Counter</option>
                <option value="UNPAID" <?= $status === 'UNPAID' ? 'selected' : '' ?>>Unpaid (Wala pang resibo)</option>
                <option value="PAYMENT_REJECTED" <?= $status === 'PAYMENT_REJECTED' ? 'selected' : '' ?>>Tinanggihan</option>
                <option value="WAIVED" <?= $status === 'WAIVED' ? 'selected' : '' ?>>Libre / Waived</option>
                <option value="REFUNDED" <?= $status === 'REFUNDED' ? 'selected' : '' ?>>Refunded</option>
            </select>
        </div>

        <!-- Method Filter -->
        <div class="col-md-2">
            <select name="method" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Lahat ng Paraan</option>
                <option value="paymongo" <?= $method === 'paymongo' ? 'selected' : '' ?>>PayMongo (GCash/Cards/QR)</option>
                <option value="paypal" <?= $method === 'paypal' ? 'selected' : '' ?>>PayPal Online</option>
                <option value="gcash" <?= $method === 'gcash' ? 'selected' : '' ?>>Online GCash</option>
                <option value="pickup" <?= $method === 'pickup' ? 'selected' : '' ?>>Counter / Pickup</option>
                <option value="free" <?= $method === 'free' ? 'selected' : '' ?>>Libre (Free)</option>
            </select>
        </div>

        <!-- Document Type Filter -->
        <div class="col-md-3">
            <select name="doc_type" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Lahat ng Uri ng Dokumento</option>
                <?php foreach (\App\Models\DocumentRequest::TYPES as $k => $lbl): ?>
                <option value="<?= e($k) ?>" <?= $docType === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Search Input -->
        <div class="col-md-3">
            <div class="input-group input-group-sm">
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Pangalan, Ref#, GCash Ref..." class="form-control">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
            </div>
        </div>

        <div class="col-md-1">
            <a href="<?= e(route('admin/payments')) ?>" class="btn btn-outline-secondary btn-sm w-100" title="I-reset ang filter">
                <i class="bi bi-x-circle"></i>
            </a>
        </div>
    </form>
</div>

<!-- Transactions Table -->
<div class="card border-0 shadow-sm rounded-3 overflow-hidden" style="background:var(--card-bg, #fff);">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:.85rem;">
            <thead class="table-light text-muted small text-uppercase tracking-wider">
                <tr>
                    <th style="padding-left:18px;">Payment Ref</th>
                    <th>Kahilingan / Residente</th>
                    <th>Dokumento & Paraan</th>
                    <th>Halaga</th>
                    <th>Estado ng Bayad</th>
                    <th>Resibo / PayPal Ref</th>
                    <th style="padding-right:18px;text-align:right;">Aksyon</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($transactions === []): ?>
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-wallet2" style="font-size:2rem;color:#cbd5e1;display:block;margin-bottom:.5rem;"></i>
                        Walang natagpuang transaksyon ng pagbabayad.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($transactions as $t):
                    $pId       = (int) $t['id'];
                    $pst       = (string) $t['payment_status'];
                    $amount    = (float) $t['amount_due'];
                    $reported  = $t['amount_reported'] !== null ? (float) $t['amount_reported'] : null;
                    $hasProof  = !empty($t['receipt_file_name']);
                    $hasRef    = !empty($t['gcash_reference_no']);
                    $mismatch  = $reported !== null && abs($reported - $amount) > 0.01;
                    $badgeStyle= $paymentBadges[$pst] ?? 'background:#f1f5f9;color:#334155;';
                ?>
                <tr>
                    <!-- Payment Reference & Date -->
                    <td style="padding-left:18px;white-space:nowrap;">
                        <span class="fw-bold font-monospace" style="color:var(--brand-primary, #0284c7);">
                            <?= e((string) $t['payment_ref']) ?>
                        </span>
                        <div class="text-muted" style="font-size:.72rem;">
                            <?= e(format_datetime((string) $t['created_at'])) ?>
                        </div>
                    </td>

                    <!-- Request Ref & Resident -->
                    <td>
                        <div class="fw-bold text-dark"><?= e((string) $t['full_name']) ?></div>
                        <div class="text-muted small">
                            <span class="font-monospace text-secondary fw-semibold"><?= e((string) $t['request_ref']) ?></span>
                            <?php if (!empty($t['phone'])): ?>
                            &bull; <a href="tel:<?= e((string) $t['phone']) ?>" class="text-muted text-decoration-none"><?= e((string) $t['phone']) ?></a>
                            <?php endif; ?>
                        </div>
                    </td>

                    <!-- Document & Delivery -->
                    <td>
                        <div class="fw-semibold text-dark">
                            <?= e(\App\Models\DocumentRequest::label((string) $t['document_type'])) ?>
                        </div>
                        <div class="d-flex align-items-center gap-1 mt-0.5">
                            <?php if ($t['delivery_method'] === 'digital'): ?>
                            <span class="badge bg-indigo-50 text-indigo-700 border border-indigo-200" style="font-size:.68rem;">
                                <i class="bi bi-file-earmark-arrow-down me-1"></i>Digital Soft Copy
                            </span>
                            <?php else: ?>
                            <span class="badge bg-light text-secondary border" style="font-size:.68rem;">
                                <i class="bi bi-building me-1"></i>Personal Pickup
                            </span>
                            <?php endif; ?>

                            <span class="badge bg-light text-dark border" style="font-size:.68rem;">
                                <?= strtoupper((string) $t['payment_method']) ?>
                            </span>
                        </div>
                    </td>

                    <!-- Amount & Mismatch Flag -->
                    <td>
                        <div class="fw-bold text-dark">₱<?= number_format($amount, 2) ?></div>
                        <?php if ($reported !== null): ?>
                        <div class="small" style="font-size:.72rem;">
                            Naiulat: <span class="<?= $mismatch ? 'text-danger fw-bold' : 'text-muted' ?>">₱<?= number_format($reported, 2) ?></span>
                        </div>
                        <?php if ($mismatch): ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size:.65rem;">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>Mismatch
                        </span>
                        <?php endif; ?>
                        <?php endif; ?>
                    </td>

                    <!-- Payment Status -->
                    <td>
                        <?= status_badge($pst, \App\Models\DocumentPayment::statusLabel($pst)) ?>
                        <?php if ($pst === 'PAID_VERIFIED' && !empty($t['verified_by_name'])): ?>
                        <div class="text-muted mt-0.5" style="font-size:.68rem;">
                            ni <?= e((string) $t['verified_by_name']) ?>
                        </div>
                        <?php endif; ?>
                    </td>

                    <!-- Proof & Reference / PayMongo / PayPal IDs -->
                    <td>
                        <?php if (!empty($t['paymongo_checkout_id']) || ($t['provider'] ?? '') === 'paymongo'): ?>
                        <div class="d-flex align-items-center gap-1">
                            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:.68rem;">
                                <i class="bi bi-credit-card-2-front me-1"></i>PayMongo (<?= e(strtoupper((string)($t['paymongo_source_type'] ?: 'Online'))) ?>)
                            </span>
                            <?php if ($pst === 'PAID_VERIFIED'): ?>
                            <span class="badge bg-success text-white" style="font-size:.65rem;">Verified</span>
                            <?php endif; ?>
                        </div>
                        <div class="font-monospace text-muted mt-1" style="font-size:.7rem;" title="PayMongo Checkout ID">
                            CS: <strong class="text-dark"><?= e((string) $t['paymongo_checkout_id']) ?></strong>
                        </div>
                        <?php if (!empty($t['paymongo_payment_id'])): ?>
                        <div class="font-monospace text-muted" style="font-size:.68rem;" title="Payment ID">
                            PayID: <span class="text-secondary"><?= e((string) $t['paymongo_payment_id']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($t['verified_at'])): ?>
                        <div class="text-muted" style="font-size:.68rem;">
                            Paid At: <?= e(format_datetime((string) $t['verified_at'])) ?>
                        </div>
                        <?php endif; ?>
                        <?php elseif (!empty($t['paypal_order_id']) || ($t['provider'] ?? '') === 'paypal'): ?>
                        <div class="d-flex align-items-center gap-1">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size:.68rem;">
                                <i class="bi bi-paypal me-1"></i>PayPal
                            </span>
                            <?php if (!empty($t['paypal_capture_id'])): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:.65rem;">Captured</span>
                            <?php endif; ?>
                        </div>
                        <div class="font-monospace text-muted mt-1" style="font-size:.7rem;" title="PayPal Order ID">
                            Order: <strong class="text-dark"><?= e((string) $t['paypal_order_id']) ?></strong>
                        </div>
                        <?php if (!empty($t['paypal_capture_id'])): ?>
                        <div class="font-monospace text-muted" style="font-size:.68rem;" title="Capture ID">
                            Cap: <span class="text-secondary"><?= e((string) $t['paypal_capture_id']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($t['verified_at'])): ?>
                        <div class="text-muted" style="font-size:.68rem;">
                            Paid At: <?= e(format_datetime((string) $t['verified_at'])) ?>
                        </div>
                        <?php endif; ?>
                        <?php elseif ($hasProof): ?>
                        <button type="button" class="btn btn-outline-info btn-xs py-0 px-2 fw-semibold" style="font-size:.74rem;"
                                onclick="openReceiptModal('<?= e(route('admin/payments/' . $pId . '/receipt')) ?>', '<?= e((string) $t['payment_ref']) ?>', '<?= e((string) $t['full_name']) ?>', '<?= e((string) $t['gcash_reference_no']) ?>', '₱<?= number_format($amount, 2) ?>')">
                            <i class="bi bi-receipt me-1"></i>Tingnan ang Resibo
                        </button>
                        <div class="font-monospace text-muted mt-0.5" style="font-size:.7rem;">
                            Ref: <strong><?= e((string) $t['gcash_reference_no']) ?></strong>
                        </div>
                        <?php elseif ($pst === 'PAY_AT_PICKUP'): ?>
                        <span class="text-muted small"><i class="bi bi-cash me-1"></i>Magbabayad sa Counter</span>
                        <?php elseif ($amount <= 0.0): ?>
                        <span class="text-muted small">Libre</span>
                        <?php else: ?>
                        <span class="text-muted small">Wala pang resibo</span>
                        <?php endif; ?>
                    </td>

                    <!-- Actions -->
                    <td style="padding-right:18px;text-align:right;">
                        <div class="d-inline-flex gap-1">
                            <?php if ($pst === 'PAYMENT_PROOF_SUBMITTED' || $pst === 'UNDER_REVIEW'): ?>
                            <!-- Review & Verify Shortcut -->
                            <button type="button" class="btn btn-success btn-sm py-1 px-2.5 fw-semibold"
                                    onclick="openVerifyModal(<?= $pId ?>, '<?= e((string) $t['payment_ref']) ?>', '<?= e((string) $t['full_name']) ?>', '₱<?= number_format($amount, 2) ?>', '<?= e((string) $t['gcash_reference_no']) ?>', '<?= e(route('admin/payments/' . $pId . '/receipt')) ?>')">
                                <i class="bi bi-check-circle me-1"></i>Beripikahin
                            </button>
                            <!-- Reject Button -->
                            <button type="button" class="btn btn-outline-danger btn-sm py-1 px-2 fw-semibold"
                                    onclick="openRejectModal(<?= $pId ?>, '<?= e((string) $t['payment_ref']) ?>', '<?= e((string) $t['full_name']) ?>')">
                                <i class="bi bi-x-circle me-1"></i>Tanggihan
                            </button>
                            <?php elseif ($pst === 'PAY_AT_PICKUP'): ?>
                            <!-- Mark Paid at Pickup -->
                            <button type="button" class="btn btn-primary btn-sm py-1 px-2.5 fw-semibold"
                                    onclick="openPickupConfirmModal(<?= $pId ?>, '<?= e((string) $t['payment_ref']) ?>', '<?= e((string) $t['full_name']) ?>', '₱<?= number_format($amount, 2) ?>')">
                                <i class="bi bi-cash-stack me-1"></i>Mark Paid at Pickup
                            </button>
                            <?php elseif ($pst === 'PAID_VERIFIED'): ?>
                            <a href="<?= e(route('documents/' . (int) $t['request_id'] . '/acknowledgement')) ?>" target="_blank"
                               class="btn btn-outline-secondary btn-sm py-1 px-2" title="Tingnan ang Acknowledgement">
                                <i class="bi bi-printer me-1"></i>Resibo
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= MODALS ================= -->

<!-- 1. Receipt Preview Modal -->
<div class="modal fade" id="receiptPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="rpmTitle"><i class="bi bi-receipt me-2"></i>Patunay ng Bayad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-3">
                <div class="d-flex justify-content-between text-start small mb-3 p-2 rounded bg-light border">
                    <div>Residente: <strong id="rpmResident"></strong></div>
                    <div>Ref: <strong id="rpmRef" class="font-monospace"></strong></div>
                    <div>Halaga: <strong id="rpmAmount" class="text-success"></strong></div>
                </div>
                <div id="rpmContent" class="d-flex justify-content-center align-items-center" style="min-height:350px;">
                    <img id="rpmImg" src="" alt="Receipt" class="img-fluid rounded border shadow-sm" style="max-height:550px;object-fit:contain;display:none;">
                    <iframe id="rpmIframe" src="" style="width:100%;height:500px;border:none;display:none;"></iframe>
                </div>
            </div>
            <div class="modal-footer">
                <a id="rpmOpenNew" href="" target="_blank" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Buksan sa Bagong Tab
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Isara</button>
            </div>
        </div>
    </div>
</div>

<!-- 2. Verify Payment Confirmation Modal -->
<div class="modal fade" id="verifyPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="verifyPaymentForm" method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? '/admin/payments') ?>">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-check2-circle me-2"></i>Beripikahin ang Pagbabayad</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="mb-3">Kumpirmahin na natanggap ang tamang halaga sa opisyal na GCash account:</p>
                    <div class="p-3 bg-light rounded border mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Residente:</span>
                            <strong id="vpmResident"></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Payment Ref:</span>
                            <strong id="vpmRef" class="font-monospace text-primary"></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">GCash Reference No:</span>
                            <strong id="vpmGcashRef" class="font-monospace text-dark"></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Halagang Babayaran:</span>
                            <strong id="vpmAmount" class="text-success h5 mb-0"></strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pansariling Tala / Staff Note (Opsiyonal):</label>
                        <input type="text" name="note" class="form-control form-control-sm" placeholder="Hal: Tugma sa GCash SMS confirmation">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Kanselahin</button>
                    <button type="submit" class="btn btn-success btn-sm fw-bold">
                        <i class="bi bi-check-lg me-1"></i>Kumpirmahin at I-verify
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 3. Reject Payment Modal -->
<div class="modal fade" id="rejectPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="rejectPaymentForm" method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? '/admin/payments') ?>">

                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-x-circle me-2"></i>Tanggihan ang Patunay ng Bayad</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">Makakatanggap ang residente ng abiso ukol sa dahilan ng pagtanggi upang makapag-upload sila ng bagong resibo.</p>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Dahilan ng Pagtanggi <span class="text-danger">*</span></label>
                        <select name="rejection_reason" class="form-select form-select-sm" required>
                            <option value="">-- Piliin ang Dahilan --</option>
                            <option value="Hindi Tugma ang Halaga (Incorrect Amount)">Hindi Tugma ang Halaga (Incorrect Amount)</option>
                            <option value="Hindi Mabasa o Malabo ang Resibo (Blurry/Unreadable)">Hindi Mabasa o Malabo ang Resibo (Blurry/Unreadable)</option>
                            <option value="Maling Reference Number (Invalid Reference Number)">Maling Reference Number (Invalid Reference Number)</option>
                            <option value="Duplicate na Resibo o Reference (Duplicate Receipt)">Duplicate na Resibo o Reference (Duplicate Receipt)</option>
                            <option value="Maling GCash Account ang Pinagbayaran (Wrong Account)">Maling GCash Account ang Pinagbayaran (Wrong Account)</option>
                            <option value="Iba pa (Pakilagay sa ibaba)">Iba pa (Pakilagay sa ibaba)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Karagdagang Paliwanag para sa Residente:</label>
                        <textarea name="rejection_note" rows="3" class="form-control form-control-sm" placeholder="Hal: Kulang ng ₱20 ang naipadala o malabo ang petsa sa screenshot..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Kanselahin</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold">
                        <i class="bi bi-x-lg me-1"></i>Isumite ang Pagtanggi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 4. Mark Paid at Pickup Modal -->
<div class="modal fade" id="pickupConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="pickupConfirmForm" method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? '/admin/payments') ?>">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-cash-stack me-2"></i>Kumpirmahin ang Bayad sa Counter</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="mb-3">Kumpirmahin na personal na ibinayad ng residente ang kaukulang halaga sa Barangay Hall counter:</p>
                    <div class="p-3 bg-light rounded border mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Residente:</span>
                            <strong id="pcmResident"></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Payment Ref:</span>
                            <strong id="pcmRef" class="font-monospace"></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Halagang Tinanggap:</span>
                            <strong id="pcmAmount" class="text-success h5 mb-0"></strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Opsyonal na Tala:</label>
                        <input type="text" name="note" class="form-control form-control-sm" placeholder="Hal: Eksaktong barya ang ibinayad">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Kanselahin</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold">
                        <i class="bi bi-check2 me-1"></i>Kumpirmahin ang Bayad
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openReceiptModal(url, ref, resident, gcashRef, amount) {
    document.getElementById('rpmRef').innerText = ref;
    document.getElementById('rpmResident').innerText = resident;
    document.getElementById('rpmAmount').innerText = amount;
    document.getElementById('rpmOpenNew').href = url;

    const img = document.getElementById('rpmImg');
    const iframe = document.getElementById('rpmIframe');

    if (url.toLowerCase().includes('.pdf')) {
        img.style.display = 'none';
        iframe.style.display = 'block';
        iframe.src = url;
    } else {
        iframe.style.display = 'none';
        img.style.display = 'block';
        img.src = url;
    }

    new bootstrap.Modal(document.getElementById('receiptPreviewModal')).show();
}

function openVerifyModal(id, ref, resident, amount, gcashRef, receiptUrl) {
    document.getElementById('vpmRef').innerText = ref;
    document.getElementById('vpmResident').innerText = resident;
    document.getElementById('vpmAmount').innerText = amount;
    document.getElementById('vpmGcashRef').innerText = gcashRef || '(Walang ref na nailagay)';
    document.getElementById('verifyPaymentForm').action = '<?= e(route('admin/payments/')) ?>' + id + '/verify';

    new bootstrap.Modal(document.getElementById('verifyPaymentModal')).show();
}

function openRejectModal(id, ref, resident) {
    document.getElementById('rejectPaymentForm').action = '<?= e(route('admin/payments/')) ?>' + id + '/reject';
    new bootstrap.Modal(document.getElementById('rejectPaymentModal')).show();
}

function openPickupConfirmModal(id, ref, resident, amount) {
    document.getElementById('pcmRef').innerText = ref;
    document.getElementById('pcmResident').innerText = resident;
    document.getElementById('pcmAmount').innerText = amount;
    document.getElementById('pickupConfirmForm').action = '<?= e(route('admin/payments/')) ?>' + id + '/mark-pickup';
    new bootstrap.Modal(document.getElementById('pickupConfirmModal')).show();
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
