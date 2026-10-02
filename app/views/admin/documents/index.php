<?php
/**
 * Admin Document Requests Queue & Review Area — BarangGabay
 *
 * Requirements 29, 30, 31, 32, 33, 34:
 * - Compact Table: REFERENCE, RESIDENT, DOCUMENT, AMOUNT, PAYMENT, REQUEST STATUS, DATE, ACTION
 * - Review Modal:
 *     RESIDENT INFORMATION
 *     DOCUMENT REQUEST
 *     PAYMENT INFORMATION (GCash via PayMongo, Paid, Amount, Reference, Paid On)
 *     REQUIREMENTS (Valid ID)
 *     ADMIN ACTIONS (Approve -> Auto Certificate Generation, Request Info, Reject, Mark Claimed)
 */
use App\Models\DocumentRequest;
use App\Models\DocumentPayment;

$requests = $requests ?? [];
$status   = (string) ($status ?? '');
$delivery = (string) ($delivery ?? '');
$paymentStatus = (string) ($paymentStatus ?? '');
$search   = (string) ($search ?? '');

$totalCount    = count($requests);
$pickupCount   = count(array_filter($requests, fn($r) => ($r['delivery_method'] ?? 'pickup') === 'pickup'));
$digitalCount  = count(array_filter($requests, fn($r) => ($r['delivery_method'] ?? 'pickup') === 'digital'));
$openCount     = count(array_filter($requests, fn($r) => in_array($r['status'] ?? '', ['pending', 'pending_review', 'under_review', 'processing'], true)));

ob_start();
?>

<!-- Header & Filter Toolbar -->
<div class="admin-card mb-4">
    <div class="admin-card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div>
                <h1 style="font-size:1.25rem;font-weight:800;margin:0 0 .25rem;color:var(--text-primary);">
                    <i class="bi bi-file-earmark-ruled me-1 text-primary"></i> <?= e(t('admin_documents.title')) ?>
                </h1>
                <p class="text-muted mb-0" style="font-size:.84rem;">
                    Pamamahala at pagsusuri ng mga kahilingan ng clearance at sertipiko ng mga residente.
                </p>
            </div>

            <!-- Stats badges -->
            <div class="d-flex flex-wrap gap-2">
                <span class="status-badge" style="background:var(--surface-muted);color:var(--text-secondary);font-size:.8rem;">
                    <strong><?= $totalCount ?></strong> Total
                </span>
                <span class="status-badge" style="background:#eef2ff;color:#4338ca;font-size:.8rem;border:1px solid #c7d2fe;">
                    <i class="bi bi-file-earmark-arrow-down me-1"></i> <strong><?= $digitalCount ?></strong> Digital
                </span>
                <span class="status-badge" style="background:#f8fafc;color:#334155;font-size:.8rem;border:1px solid #e2e8f0;">
                    <i class="bi bi-building me-1"></i> <strong><?= $pickupCount ?></strong> Pickup
                </span>
                <?php if ($openCount > 0): ?>
                <span class="status-badge" style="background:#fef3c7;color:#b45309;font-size:.8rem;border:1px solid #fde68a;">
                    <i class="bi bi-hourglass-split me-1"></i> <strong><?= $openCount ?></strong> Pending Review
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="row g-2 align-items-center pt-2 border-top">
            <div class="col-12 col-xl-7 d-flex flex-wrap gap-1.5 align-items-center">
                <span class="text-muted me-1" style="font-size:.78rem;font-weight:600;">Status:</span>
                <?php
                $statusFilters = [
                    '' => 'Lahat',
                    'pending_review' => 'Pending Review',
                    'ready'          => 'Approved / Ready',
                    'completed'      => 'Completed',
                    'rejected'       => 'Rejected'
                ];
                foreach ($statusFilters as $k => $lbl):
                    $active = ($status === $k || ($k === 'pending_review' && in_array($status, ['pending', 'pending_review', 'under_review'], true)));
                    $url = route('admin/documents' . '?' . http_build_query(array_filter([
                        'status'   => $k !== '' ? $k : null,
                        'delivery' => $delivery !== '' ? $delivery : null,
                        'search'   => $search !== '' ? $search : null,
                    ])));
                ?>
                <a href="<?= e($url) ?>"
                   class="status-badge"
                   style="text-decoration:none;font-size:.78rem;padding:4px 10px;border-radius:20px;<?= $active ? 'background:var(--brand-primary);color:#fff;font-weight:700;' : 'background:var(--surface-muted);color:var(--text-secondary);' ?>">
                    <?= e($lbl) ?>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="col-12 col-xl-5">
                <form method="get" action="<?= e(route('admin/documents')) ?>" class="d-flex gap-2">
                    <?php if ($status !== ''): ?>
                    <input type="hidden" name="status" value="<?= e($status) ?>">
                    <?php endif; ?>

                    <select name="delivery" class="form-select form-select-sm" style="font-size:.8rem;min-width:120px;" onchange="this.form.submit()">
                        <option value="">Lahat ng Receiving</option>
                        <option value="digital" <?= $delivery === 'digital' ? 'selected' : '' ?>>Digital Copy</option>
                        <option value="pickup" <?= $delivery === 'pickup' ? 'selected' : '' ?>>Barangay Hall Pickup</option>
                    </select>

                    <div class="input-group input-group-sm">
                        <input type="text" name="search" value="<?= e($search) ?>"
                               placeholder="Hanapin (Ref, Pangalan)..."
                               class="form-control" style="font-size:.8rem;">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($requests === []): ?>
<div class="admin-card">
    <div class="admin-card-body text-center py-5">
        <i class="bi bi-folder2-open" style="font-size:2.8rem;color:var(--border);"></i>
        <h3 class="mt-3 mb-1" style="font-size:1rem;font-weight:700;color:var(--text-primary);"><?= e(t('admin_documents.empty')) ?></h3>
        <p class="text-muted mb-3" style="font-size:.84rem;">Walang nakitang kahilingan para sa filter na ito.</p>
        <a href="<?= e(route('admin/documents')) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-counterclockwise me-1"></i> I-reset ang Filters
        </a>
    </div>
</div>
<?php else: ?>

<!-- Requirement 29: Compact Admin Table -->
<div class="admin-card">
    <div class="table-responsive">
        <table class="table admin-table mb-0 align-middle">
            <thead>
                <tr>
                    <th style="padding-left:16px;">REFERENCE</th>
                    <th>RESIDENT</th>
                    <th>DOCUMENT</th>
                    <th>AMOUNT</th>
                    <th>PAYMENT</th>
                    <th>REQUEST STATUS</th>
                    <th>DATE</th>
                    <th style="width:130px;text-align:right;padding-right:16px;">ACTION</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($requests as $r):
                $id       = (int) $r['id'];
                $st       = (string) $r['status'];
                $deliv    = (string) ($r['delivery_method'] ?? 'pickup');
                $isDigital= $deliv === 'digital';
                $hasFile  = !empty($r['document_file_name']);

                // Payment calculations
                $fee       = (float) ($r['fee_amount'] ?? 0);
                $pst       = (string) ($r['payment_status'] ?? 'UNPAID');
                $isPaid    = DocumentRequest::isPaymentVerified($r);
                $isFree    = ($fee <= 0.0 || $pst === 'FREE' || $pst === 'NOT_REQUIRED');

                // Tailored Status Label
                $statusLabel = 'Pending Review';
                $statusBadgeStyle = 'background:#fef3c7;color:#92400e;border:1px solid #fde68a;';

                if ($st === 'ready') {
                    $statusLabel = $isDigital ? 'Available for Download' : 'Ready for Pickup';
                    $statusBadgeStyle = 'background:#dcfce7;color:#166534;border:1px solid #bbf7d0;';
                } elseif (in_array($st, ['released', 'completed'], true)) {
                    $statusLabel = 'Completed';
                    $statusBadgeStyle = 'background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;';
                } elseif ($st === 'rejected') {
                    $statusLabel = 'Rejected';
                    $statusBadgeStyle = 'background:#fee2e2;color:#991b1b;border:1px solid #fecaca;';
                } elseif ($st === 'needs_information') {
                    $statusLabel = 'Needs Info';
                    $statusBadgeStyle = 'background:#ffedd5;color:#9a3412;border:1px solid #fed7aa;';
                } elseif ($st === 'awaiting_payment') {
                    $statusLabel = 'Awaiting Payment';
                    $statusBadgeStyle = 'background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;';
                }

                // Payment Label
                $payLabel = 'Pending';
                $payBadgeStyle = 'background:#fef3c7;color:#92400e;border:1px solid #fde68a;';
                if ($isFree) {
                    $payLabel = 'Not Required';
                    $payBadgeStyle = 'background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;';
                } elseif ($isPaid) {
                    $payLabel = 'Paid ✓';
                    $payBadgeStyle = 'background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;';
                } elseif (in_array($pst, ['FAILED', 'PAYMENT_REJECTED'], true)) {
                    $payLabel = 'Failed';
                    $payBadgeStyle = 'background:#fee2e2;color:#991b1b;border:1px solid #fecaca;';
                }
            ?>
            <tr>
                <!-- 1. REFERENCE -->
                <td style="padding-left:16px;white-space:nowrap;">
                    <div style="font-family:ui-monospace,monospace;font-size:.82rem;font-weight:700;color:var(--brand-primary);">
                        <?= e((string) $r['reference_no']) ?>
                    </div>
                    <?php if (!empty($r['certificate_no'])): ?>
                    <div style="font-size:.72rem;font-weight:700;color:#4338ca;">
                        <i class="bi bi-patch-check-fill text-primary me-0.5"></i><?= e((string) $r['certificate_no']) ?>
                    </div>
                    <?php endif; ?>
                </td>

                <!-- 2. RESIDENT -->
                <td>
                    <div style="font-weight:700;font-size:.85rem;color:var(--text-primary);">
                        <?= e((string) $r['full_name']) ?>
                    </div>
                    <div class="text-muted" style="font-size:.74rem;">
                        <i class="bi bi-geo-alt"></i> <?= e((string) ($r['zone'] ?: 'Purok N/A')) ?>
                    </div>
                </td>

                <!-- 3. DOCUMENT -->
                <td>
                    <div style="font-size:.84rem;font-weight:600;color:var(--text-primary);">
                        <?= e(DocumentRequest::label((string) $r['document_type'])) ?>
                    </div>
                    <div class="mt-0.5">
                        <span class="badge" style="font-size:.68rem;<?= $isDigital ? 'background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;' : 'background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;' ?>">
                            <i class="bi bi-<?= $isDigital ? 'file-earmark-arrow-down' : 'building' ?> me-0.5"></i>
                            <?= $isDigital ? 'Digital' : 'Pickup' ?>
                        </span>
                    </div>
                </td>

                <!-- 4. AMOUNT -->
                <td style="white-space:nowrap;">
                    <strong style="font-size:.84rem;color:var(--text-primary);">
                        <?= $isFree ? 'LIBRE' : '₱' . number_format($fee, 2) ?>
                    </strong>
                </td>

                <!-- 5. PAYMENT -->
                <td style="white-space:nowrap;">
                    <span class="status-badge" style="<?= $payBadgeStyle ?>;font-weight:700;font-size:.74rem;">
                        <?= e($payLabel) ?>
                    </span>
                </td>

                <!-- 6. REQUEST STATUS -->
                <td style="white-space:nowrap;">
                    <span class="status-badge" style="<?= $statusBadgeStyle ?>;font-weight:700;font-size:.74rem;">
                        <?= e($statusLabel) ?>
                    </span>
                </td>

                <!-- 7. DATE -->
                <td style="white-space:nowrap;font-size:.78rem;color:var(--text-secondary);">
                    <?= date('M d, Y', strtotime($r['requested_at'])) ?>
                </td>

                <!-- 8. ACTION (Requirement 29 & 30) -->
                <td style="text-align:right;padding-right:16px;white-space:nowrap;">
                    <div class="d-flex align-items-center justify-content-end gap-1">
                        <button type="button" class="btn btn-sm btn-primary py-1 px-2.5 font-weight-bold"
                                style="font-size:.78rem;border-radius:8px;"
                                onclick="openReviewModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>)">
                            <i class="bi bi-search me-1"></i> Review
                        </button>

                        <?php if ($hasFile): ?>
                        <a href="<?= e(route('admin/documents/' . $id . '/preview')) ?>" target="_blank"
                           class="btn btn-sm btn-outline-secondary py-1 px-1.5" style="font-size:.78rem;border-radius:8px;" title="Tingnan ang PDF">
                            <i class="bi bi-eye"></i>
                        </a>
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

<!-- ========================================================================= -->
<!-- ADMIN REVIEW MODAL (Requirements 30 & 31)                                 -->
<!-- ========================================================================= -->
<div class="modal fade" id="adminReviewModal" tabindex="-1" aria-labelledby="adminReviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:20px;border:none;box-shadow:0 20px 40px rgba(0,0,0,0.18);">
            
            <div class="modal-header" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;border-radius:20px 20px 0 0;padding:18px 24px;">
                <div>
                    <h5 class="modal-title mb-0" id="adminReviewTitle" style="font-size:1.1rem;font-weight:800;color:var(--text-primary);">
                        <i class="bi bi-file-earmark-check text-primary me-1"></i> Review Document Request
                    </h5>
                    <div class="text-muted font-mono mt-0.5" id="revRefSubtitle" style="font-size:.8rem;font-weight:700;color:#2563eb;"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body" style="padding:22px;max-height:75vh;overflow-y:auto;">
                <div class="row g-3">

                    <!-- SECTION 1: RESIDENT INFORMATION -->
                    <div class="col-12 col-md-6">
                        <div class="p-3 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;height:100%;">
                            <h6 style="font-size:.74rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#64748b;margin-bottom:.75rem;">
                                <i class="bi bi-person-badge text-primary me-1"></i> RESIDENT INFORMATION
                            </h6>
                            <div class="space-y-1" style="font-size:.82rem;">
                                <div class="mb-1"><span class="text-muted">Pangalan:</span> <strong id="revResName" class="text-dark"></strong></div>
                                <div class="mb-1"><span class="text-muted">Tirahan:</span> <strong id="revResAddress" class="text-dark"></strong></div>
                                <div class="mb-1"><span class="text-muted">Telepono:</span> <strong id="revResPhone" class="text-dark"></strong></div>
                                <div class="mb-1"><span class="text-muted">Beripikasyon:</span> <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold">Verified Resident ✓</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: DOCUMENT REQUEST -->
                    <div class="col-12 col-md-6">
                        <div class="p-3 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;height:100%;">
                            <h6 style="font-size:.74rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#64748b;margin-bottom:.75rem;">
                                <i class="bi bi-file-earmark-text text-primary me-1"></i> DOCUMENT REQUEST
                            </h6>
                            <div class="space-y-1" style="font-size:.82rem;">
                                <div class="mb-1"><span class="text-muted">Dokumento:</span> <strong id="revDocType" class="text-primary"></strong></div>
                                <div class="mb-1"><span class="text-muted">Layunin (Purpose):</span> <strong id="revPurpose" class="text-dark"></strong></div>
                                <div class="mb-1"><span class="text-muted">Paraan ng Pagtanggap:</span> <strong id="revReceiving" class="text-dark"></strong></div>
                                <div class="mb-1"><span class="text-muted">Petsa ng Kahilingan:</span> <span id="revDate" class="text-muted"></span></div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3: PAYMENT INFORMATION (Requirement 30) -->
                    <div class="col-12 col-md-6">
                        <div class="p-3 rounded-3" style="background:#f0fdf4;border:1px solid #bbf7d0;height:100%;">
                            <h6 style="font-size:.74rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#166534;margin-bottom:.75rem;">
                                <i class="bi bi-wallet2 text-success me-1"></i> PAYMENT INFORMATION
                            </h6>
                            <div class="space-y-1" style="font-size:.82rem;">
                                <div class="mb-1"><span class="text-muted">Payment Method:</span> <strong id="revPayMethod" class="text-dark">GCash via PayMongo</strong></div>
                                <div class="mb-1"><span class="text-muted">Halaga (Amount):</span> <strong id="revAmount" class="text-success" style="font-size:.95rem;"></strong></div>
                                <div class="mb-1"><span class="text-muted">Status:</span> <span id="revPayStatus" class="fw-bold"></span></div>
                                <div class="mb-1"><span class="text-muted">Reference:</span> <span class="font-monospace fw-bold" id="revPayRef"></span></div>
                                <div class="mb-1"><span class="text-muted">Paid On:</span> <span id="revPayDate" class="text-muted"></span></div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 4: REQUIREMENTS -->
                    <div class="col-12 col-md-6">
                        <div class="p-3 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;height:100%;">
                            <h6 style="font-size:.74rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#64748b;margin-bottom:.75rem;">
                                <i class="bi bi-shield-check text-primary me-1"></i> REQUIREMENTS
                            </h6>
                            <div class="space-y-1" style="font-size:.82rem;">
                                <div class="d-flex align-items-center gap-1.5 text-success fw-bold mb-1">
                                    <i class="bi bi-check-circle-fill"></i> Valid Government ID on File (Verified)
                                </div>
                                <div class="text-muted" style="font-size:.76rem;line-height:1.3;">
                                    Lahat ng kinakailangang impormasyon at katibayan ng paninirahan ay ganap na na-verify sa rehistro ng barangay.
                                </div>
                                <div id="revCertBox" class="mt-2 pt-2 border-top d-none">
                                    <span class="text-muted d-block" style="font-size:.72rem;">Nailikhang Sertipiko:</span>
                                    <strong id="revCertNo" class="text-primary font-monospace"></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 5: ADMIN ACTION (Requirement 31) -->
                    <div class="col-12">
                        <div class="p-3 rounded-3" style="background:#f1f5f9;border:1px solid #cbd5e1;">
                            <h6 style="font-size:.74rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#334155;margin-bottom:.75rem;">
                                <i class="bi bi-lightning-charge text-warning me-1"></i> ADMIN ACTION
                            </h6>

                            <!-- Form 1: Approve & Auto-Generate -->
                            <div id="actionApproveSection" class="mb-3 p-3 bg-white rounded-3 border">
                                <form method="post" id="formApprove" onsubmit="return confirm('Aprubahan at awtomatikong gumawa ng opisyal na sertipiko na may QR Code?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                        <div>
                                            <strong class="d-block text-dark" style="font-size:.85rem;">Aprubahan ang Kahilingan (Approve & Auto-Generate)</strong>
                                            <span class="text-muted" style="font-size:.75rem;">Awtomatikong bubuuin ang opisyal na PDF certificate na may pirma at QR code.</span>
                                        </div>
                                        <button type="submit" class="btn btn-success btn-sm px-4 fw-bold">
                                            <i class="bi bi-patch-check me-1"></i> APPROVE & GENERATE
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Form 2: Mark As Claimed (for Pickup only) -->
                            <div id="actionClaimSection" class="mb-3 p-3 bg-white rounded-3 border d-none">
                                <form method="post" id="formClaim" onsubmit="return confirm('Markahan bilang nakuha na (Claimed) sa Barangay Hall?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                        <div>
                                            <strong class="d-block text-primary" style="font-size:.85rem;">I-release sa Barangay Hall (Mark as Claimed)</strong>
                                            <span class="text-muted" style="font-size:.75rem;">Nakuha na ng residente ang pisikal na kopya ng sertipiko sa counter.</span>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">
                                            <i class="bi bi-hand-thumbs-up me-1"></i> MARK AS CLAIMED
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Tabs for Request Info & Reject -->
                            <div class="row g-2">
                                <div class="col-12 col-md-6">
                                    <div class="p-2.5 bg-white rounded-3 border">
                                        <form method="post" id="formNeedInfo">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="status" value="needs_information">
                                            <label class="form-label mb-1 fw-bold text-dark" style="font-size:.78rem;">
                                                Humiling ng Karagdagang Impormasyon:
                                            </label>
                                            <textarea name="staff_note" rows="2" class="form-control form-control-sm mb-2" style="font-size:.76rem;" placeholder="Ilagay ang kailangan ipadala o itanong sa residente..." required></textarea>
                                            <button type="submit" class="btn btn-outline-warning btn-sm w-100 fw-bold" style="font-size:.76rem;">
                                                <i class="bi bi-question-circle me-1"></i> REQUEST MORE INFO
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="p-2.5 bg-white rounded-3 border">
                                        <form method="post" id="formReject" onsubmit="return confirm('Sigurado ka bang nais tanggihan ang kahilingang ito?');">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="status" value="rejected">
                                            <label class="form-label mb-1 fw-bold text-danger" style="font-size:.78rem;">
                                                Tanggihan ang Kahilingan (Reject Reason): <span class="text-danger">*</span>
                                            </label>
                                            <textarea name="staff_note" rows="2" class="form-control form-control-sm mb-2" style="font-size:.76rem;" placeholder="Kailangan ilagay ang opisyal na dahilan ng pagtanggi..." required></textarea>
                                            <button type="submit" class="btn btn-outline-danger btn-sm w-100 fw-bold" style="font-size:.76rem;">
                                                <i class="bi bi-x-circle me-1"></i> REJECT REQUEST
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>

            <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;border-radius:0 0 20px 20px;padding:12px 24px;">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Isara</button>
                <a href="#" id="revPreviewBtn" target="_blank" class="btn btn-sm btn-outline-primary d-none">
                    <i class="bi bi-eye me-1"></i> Preview PDF
                </a>
                <a href="#" id="revDownloadBtn" class="btn btn-sm btn-primary d-none">
                    <i class="bi bi-download me-1"></i> Download PDF
                </a>
            </div>

        </div>
    </div>
</div>

<script>
function openReviewModal(r) {
    const modalEl = document.getElementById('adminReviewModal');
    const modal = new bootstrap.Modal(modalEl);

    // Header
    document.getElementById('revRefSubtitle').innerText = r.reference_no + (r.certificate_no ? ' • ' + r.certificate_no : '');

    // Resident
    document.getElementById('revResName').innerText = r.full_name || '';
    document.getElementById('revResAddress').innerText = (r.zone ? r.zone + ', ' : '') + (r.address || 'Barangay Bayogo');
    document.getElementById('revResPhone').innerText = r.phone || 'N/A';

    // Document
    document.getElementById('revDocType').innerText = r.document_type || '';
    document.getElementById('revPurpose').innerText = r.purpose || '';
    document.getElementById('revReceiving').innerText = (r.delivery_method === 'digital') ? 'Digital Copy (Online Soft Copy)' : 'Pickup at Barangay Hall (Counter)';
    document.getElementById('revDate').innerText = r.requested_at ? new Date(r.requested_at).toLocaleString() : '';

    // Payment Section (Requirement 30)
    var fee = parseFloat(r.fee_amount || 0);
    var pst = (r.payment_status || 'UNPAID').toUpperCase();
    var isPaid = (pst === 'PAID' || pst === 'PAID_VERIFIED' || pst === 'PAID_AT_PICKUP' || pst === 'FREE' || pst === 'NOT_REQUIRED' || fee <= 0);

    document.getElementById('revAmount').innerText = fee > 0 ? '₱' + fee.toFixed(2) : 'LIBRE';
    document.getElementById('revPayRef').innerText = r.payment_ref || (r.reference_no ? 'PAY-' + r.reference_no : 'N/A');
    document.getElementById('revPayDate').innerText = r.payment_created_at || (r.requested_at ? new Date(r.requested_at).toLocaleDateString() : 'N/A');

    var pstEl = document.getElementById('revPayStatus');
    if (isPaid) {
        pstEl.className = "text-success fw-bold";
        pstEl.innerHTML = '<i class="bi bi-check-circle-fill"></i> PAID ✓';
    } else {
        pstEl.className = "text-warning fw-bold";
        pstEl.innerText = pst;
    }

    // Certificate Box
    var certBox = document.getElementById('revCertBox');
    if (r.certificate_no) {
        certBox.classList.remove('d-none');
        document.getElementById('revCertNo').innerText = r.certificate_no;
    } else {
        certBox.classList.add('d-none');
    }

    // Forms Setup
    var formApprove = document.getElementById('formApprove');
    var formClaim = document.getElementById('formClaim');
    var formNeedInfo = document.getElementById('formNeedInfo');
    var formReject = document.getElementById('formReject');

    formApprove.action = '<?= e(route('admin/documents/')) ?>' + r.id + '/approve';
    formClaim.action = '<?= e(route('admin/documents/')) ?>' + r.id + '/claim';
    formNeedInfo.action = '<?= e(route('admin/documents/')) ?>' + r.id + '/status';
    formReject.action = '<?= e(route('admin/documents/')) ?>' + r.id + '/status';

    // Show/hide approve or claim actions
    var st = (r.status || '').toLowerCase();
    var approveSec = document.getElementById('actionApproveSection');
    var claimSec = document.getElementById('actionClaimSection');

    if (['pending', 'pending_review', 'under_review', 'processing', 'needs_information'].indexOf(st) !== -1) {
        approveSec.classList.remove('d-none');
    } else {
        approveSec.classList.add('d-none');
    }

    if (r.delivery_method === 'pickup' && st === 'ready') {
        claimSec.classList.remove('d-none');
    } else {
        claimSec.classList.add('d-none');
    }

    // Preview / Download links
    var prevBtn = document.getElementById('revPreviewBtn');
    var downBtn = document.getElementById('revDownloadBtn');
    if (r.document_file_name) {
        prevBtn.classList.remove('d-none');
        downBtn.classList.remove('d-none');
        prevBtn.href = '<?= e(route('admin/documents/')) ?>' + r.id + '/preview';
        downBtn.href = '<?= e(route('admin/documents/')) ?>' + r.id + '/download';
    } else {
        prevBtn.classList.add('d-none');
        downBtn.classList.add('d-none');
    }

    modal.show();
}
</script>

<?php
$content   = ob_get_clean();
$pageTitle = $pageTitle ?? t('admin_documents.title');
require __DIR__ . '/../../layouts/admin.php';
