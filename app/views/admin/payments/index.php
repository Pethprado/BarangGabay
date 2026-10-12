<?php
/**
 * Admin / Staff — Payment Management.
 *
 * Real document_payments rows only. Two verification paths, kept apart:
 *   • PayMongo GCash — confirmed by PayMongo (webhook or "Check PayMongo Status");
 *     nobody can mark it paid by hand.
 *   • Manual Review — a resident-uploaded proof, verified or rejected by staff.
 * Cash at the hall and waivers are separate, recorded actions.
 *
 * Row data reaches JavaScript through data-* attributes holding
 * e(json_encode(...)), never through inline JS string literals.
 */
use App\Models\DocumentPayment;
use App\Models\DocumentRequest;
use App\Services\PayMongoService;

$stats        = $stats ?? [];
$transactions = $transactions ?? [];
$group        = $group ?? '';
$docType      = $docType ?? '';
$search       = $search ?? '';

$role    = (string) ($_SESSION['role'] ?? 'staff');
$isAdmin = in_array($role, ['admin', 'superadmin'], true);

$tabs = [
    ''              => 'All',
    'awaiting'      => 'Awaiting Payment',
    'paid'          => 'Paid',
    'failed'        => 'Failed',
    'expired'       => 'Expired / Cancelled',
    'manual_review' => 'Manual Review',
];
$tabUrl = static function (string $g) use ($search, $docType): string {
    $q = array_filter(['group' => $g, 'search' => $search, 'doc_type' => $docType], static fn ($v) => $v !== '');
    return route('admin/payments') . ($q ? '?' . http_build_query($q) : '');
};
$returnTo = '/admin/payments' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');

$verificationText = static function (array $t): string {
    return match ((string) ($t['verification_type'] ?? '')) {
        'paymongo' => 'Automatically verified through PayMongo',
        'manual'   => 'Manually verified' . (!empty($t['verified_by_name']) ? ' by ' . $t['verified_by_name'] : ''),
        'counter'  => 'Cash at Barangay Hall' . (!empty($t['verified_by_name']) ? ' — received by ' . $t['verified_by_name'] : ''),
        'waiver'   => 'Fee waived' . (!empty($t['verified_by_name']) ? ' by ' . $t['verified_by_name'] : ''),
        default    => in_array((string) $t['payment_status'], DocumentPayment::FILTER_GROUPS['manual_review'], true) ? 'Waiting for staff review' : '—',
    };
};

ob_start();
?>
<style>
.pm-head { display:flex; flex-wrap:wrap; gap:.75rem; align-items:flex-end; justify-content:space-between; margin-bottom:1.25rem; }
.pm-head h2 { margin:0; font-family:var(--font-heading); font-weight:800; font-size:1.45rem; color:var(--text-primary); }
.pm-head p { margin:.2rem 0 0; color:var(--text-muted); font-size:.9rem; }
.pm-gw { display:flex; flex-wrap:wrap; gap:.5rem 1.25rem; align-items:center; padding:.8rem 1rem; border-radius:12px; border:1px solid var(--border); background:var(--surface-card); margin-bottom:1rem; font-size:.84rem; color:var(--text-secondary); }
.pm-gw code { font-size:.78rem; }
.pm-pill { display:inline-flex; align-items:center; gap:.3rem; padding:.15rem .55rem; border-radius:999px; font-size:.72rem; font-weight:700; }
.pm-pill--ok { background:var(--status-success-bg); color:var(--status-success); }
.pm-pill--warn { background:var(--status-warning-bg); color:var(--status-warning); }
.pm-pill--bad { background:var(--status-danger-bg); color:var(--status-danger); }
.pm-stats { display:grid; gap:1rem; grid-template-columns:repeat(2,minmax(0,1fr)); margin-bottom:1rem; }
@media (min-width:1200px) { .pm-stats { grid-template-columns:repeat(4,minmax(0,1fr)); } }
.pm-stat { padding:1rem 1.1rem; border-radius:14px; border:1px solid var(--border); background:var(--surface-card); text-decoration:none; display:block; }
.pm-stat:hover { border-color:var(--brand-primary); }
.pm-stat span { display:block; font-size:.78rem; font-weight:600; color:var(--text-muted); }
.pm-stat strong { display:block; margin-top:.2rem; font-size:1.5rem; font-weight:800; color:var(--text-primary); }
.pm-stat small { color:var(--text-muted); font-size:.75rem; }
.pm-tabs { display:flex; flex-wrap:wrap; gap:.35rem; margin-bottom:.75rem; }
.pm-tabs a { padding:.4rem .85rem; border-radius:999px; font-size:.82rem; font-weight:600; text-decoration:none; color:var(--text-secondary); background:var(--surface-muted); border:1px solid var(--border); }
.pm-tabs a.is-active { background:var(--action-solid); border-color:var(--action-solid); color:#fff; }
.pm-table td, .pm-table th { vertical-align:middle; }
.pm-mono { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:.76rem; }
.pm-sub { font-size:.74rem; color:var(--text-muted); }
.pm-dl { display:grid; grid-template-columns:auto 1fr; gap:.35rem 1rem; font-size:.86rem; margin:0; }
.pm-dl dt { color:var(--text-muted); font-weight:500; }
.pm-dl dd { margin:0; color:var(--text-primary); word-break:break-word; }
.pm-audit { list-style:none; padding:0; margin:0; font-size:.8rem; }
.pm-audit li { padding:.45rem 0; border-bottom:1px solid var(--border); }
.pm-audit li:last-child { border-bottom:0; }
</style>

<div class="pm-head">
    <div>
        <h2>Payment Management</h2>
        <p>Document payments from residents. Online GCash payments are confirmed by PayMongo, never by hand.</p>
    </div>
    <?php if ($isAdmin): ?>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= e(route('admin/payments/fees')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-tags me-1" aria-hidden="true"></i>Document Fees</a>
        <a href="<?= e(route('admin/payments/export') . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1" aria-hidden="true"></i>Export CSV</a>
    </div>
    <?php endif; ?>
</div>

<?php if ($isAdmin): ?>
<div class="pm-gw" role="status">
    <strong style="color:var(--text-primary);"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i>PayMongo</strong>
    <?php if (PayMongoService::isConfigured()): ?>
        <span class="pm-pill pm-pill--ok"><i class="bi bi-check-circle-fill" aria-hidden="true"></i>API key set</span>
    <?php else: ?>
        <span class="pm-pill pm-pill--bad"><i class="bi bi-x-circle-fill" aria-hidden="true"></i>PAYMONGO_SECRET_KEY missing</span>
    <?php endif; ?>
    <span class="pm-pill <?= PayMongoService::getMode() === 'live' ? 'pm-pill--ok' : 'pm-pill--warn' ?>"><?= PayMongoService::getMode() === 'live' ? 'LIVE mode — real money' : 'TEST mode — no real money' ?></span>
    <?php if (PayMongoService::isWebhookConfigured()): ?>
        <span class="pm-pill pm-pill--ok"><i class="bi bi-check-circle-fill" aria-hidden="true"></i>Webhook secret set</span>
    <?php else: ?>
        <span class="pm-pill pm-pill--bad"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>PAYMONGO_WEBHOOK_SECRET missing — payments confirm only via "Check PayMongo Status"</span>
    <?php endif; ?>
    <span>Webhook URL: <code><?= e(route('api/webhooks/paymongo')) ?></code> · event <code>checkout_session.payment.paid</code></span>
</div>
<?php endif; ?>

<div class="pm-stats">
    <a class="pm-stat" href="<?= e($tabUrl('paid')) ?>"><span>Collected</span><strong>₱<?= number_format((float) ($stats['total_collected'] ?? 0), 2) ?></strong><small>₱<?= number_format((float) ($stats['today_collected'] ?? 0), 2) ?> today</small></a>
    <a class="pm-stat" href="<?= e($tabUrl('awaiting')) ?>"><span>Awaiting payment</span><strong><?= (int) ($stats['awaiting_count'] ?? 0) ?></strong><small>Checkout not finished</small></a>
    <a class="pm-stat" href="<?= e($tabUrl('manual_review')) ?>"><span>Manual review</span><strong><?= (int) ($stats['review_count'] ?? 0) ?></strong><small>Needs a staff decision</small></a>
    <a class="pm-stat" href="<?= e($tabUrl('failed')) ?>"><span>Failed</span><strong><?= (int) ($stats['failed_count'] ?? 0) ?></strong><small><?= (int) ($stats['paid_today_count'] ?? 0) ?> paid today</small></a>
</div>

<nav class="pm-tabs" aria-label="Payment status filter">
    <?php foreach ($tabs as $g => $label): ?>
        <a href="<?= e($tabUrl($g)) ?>" class="<?= $group === $g ? 'is-active' : '' ?>"<?= $group === $g ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<form method="get" action="<?= e(route('admin/payments')) ?>" class="row g-2 align-items-center mb-3" role="search">
    <input type="hidden" name="group" value="<?= e($group) ?>">
    <div class="col-12 col-md-6">
        <label class="visually-hidden" for="pmSearch">Search payments</label>
        <input id="pmSearch" type="search" name="search" value="<?= e($search) ?>" class="form-control form-control-sm"
               placeholder="Reference no., resident, document, or PayMongo ID (cs_…, pay_…)">
    </div>
    <div class="col-8 col-md-4">
        <label class="visually-hidden" for="pmDoc">Document type</label>
        <select id="pmDoc" name="doc_type" class="form-select form-select-sm">
            <option value="">All documents</option>
            <?php foreach (DocumentRequest::TYPES as $k => $lbl): ?>
                <option value="<?= e($k) ?>" <?= $docType === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-4 col-md-2 d-flex gap-1">
        <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">Search</span></button>
        <a href="<?= e(route('admin/payments')) ?>" class="btn btn-outline-secondary btn-sm" title="Clear filters"><i class="bi bi-x-lg" aria-hidden="true"></i><span class="visually-hidden">Clear filters</span></a>
    </div>
</form>

<div class="admin-card p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover mb-0 pm-table" style="font-size:.84rem;">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Resident</th>
                    <th>Document</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>PayMongo ID</th>
                    <th>Payment</th>
                    <th>Request</th>
                    <th>Created / Paid</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($transactions === []): ?>
                <tr><td colspan="10" class="text-center py-5 text-muted">
                    <i class="bi bi-wallet2 d-block mb-2" style="font-size:1.8rem;" aria-hidden="true"></i>
                    No payments<?= $group !== '' || $search !== '' ? ' match these filters' : ' yet. They appear here as residents request paid documents' ?>.
                </td></tr>
            <?php endif; ?>
            <?php foreach ($transactions as $t):
                $pId      = (int) $t['id'];
                $pst      = (string) $t['payment_status'];
                $settled  = in_array($pst, DocumentPayment::SETTLED_STATES, true);
                $inReview = in_array($pst, DocumentPayment::FILTER_GROUPS['manual_review'], true);
                $isOnline = !empty($t['paymongo_checkout_id']);
                $method   = $isOnline || in_array((string) $t['payment_method'], ['gcash', 'paymongo'], true) ? 'GCash (PayMongo)'
                          : ((string) $t['payment_method'] === 'pickup' ? 'Cash at hall' : ucfirst((string) $t['payment_method']));
                $details  = [
                    'id'           => $pId,
                    'payment_ref'  => (string) $t['payment_ref'],
                    'request_ref'  => (string) $t['request_ref'],
                    'resident'     => (string) $t['full_name'],
                    'document'     => DocumentRequest::label((string) $t['document_type']),
                    'amount'       => '₱' . number_format((float) $t['amount_due'], 2),
                    'reported'     => $t['amount_reported'] !== null ? '₱' . number_format((float) $t['amount_reported'], 2) : '',
                    'method'       => $method,
                    'source'       => $isOnline ? 'PayMongo' : 'Barangay',
                    'status'       => DocumentPayment::statusLabel($pst),
                    'request'      => DocumentRequest::statusLabel((string) $t['document_status']),
                    'verification' => $verificationText($t),
                    'verified_at'  => !empty($t['verified_at']) ? format_datetime((string) $t['verified_at']) : '',
                    'created'      => format_datetime((string) $t['created_at']),
                    'paid'         => !empty($t['paid_at']) ? format_datetime((string) $t['paid_at']) : '',
                    'checkout_id'  => (string) ($t['paymongo_checkout_id'] ?? ''),
                    'pm_payment'   => (string) ($t['paymongo_payment_id'] ?? ''),
                    'failure'      => (string) ($t['failure_reason'] ?? ''),
                    'proof_ref'    => (string) ($t['gcash_reference_no'] ?? ''),
                    'proof_url'    => !empty($t['receipt_file_name']) ? route('admin/payments/' . $pId . '/receipt') : '',
                    'history_url'  => route('admin/payments/' . $pId . '/history'),
                    'request_url'  => route('admin/documents') . '?search=' . rawurlencode((string) $t['request_ref']),
                    'can_waive'    => $isAdmin && !$settled,
                    'can_refund'   => $isAdmin && in_array($pst, DocumentPayment::PAID_STATES, true),
                ];
            ?>
                <tr>
                    <td><span class="pm-mono fw-bold"><?= e((string) $t['payment_ref']) ?></span><div class="pm-sub pm-mono"><?= e((string) $t['request_ref']) ?></div></td>
                    <td><?= e((string) $t['full_name']) ?></td>
                    <td><?= e(DocumentRequest::label((string) $t['document_type'])) ?></td>
                    <td class="fw-bold">₱<?= number_format((float) $t['amount_due'], 2) ?></td>
                    <td><?= e($method) ?></td>
                    <td class="pm-mono"><?= e((string) ($t['paymongo_payment_id'] ?: ($t['paymongo_checkout_id'] ?? '')) ?: '—') ?></td>
                    <td><?= status_badge($inReview ? "payment_proof_submitted" : $pst, DocumentPayment::statusLabel($pst)) ?><div class="pm-sub"><?= e($verificationText($t)) ?></div></td>
                    <td><span class="pm-sub" style="color:var(--text-secondary);"><?= e(DocumentRequest::statusLabel((string) $t['document_status'])) ?></span></td>
                    <td class="pm-sub"><?= e(format_datetime((string) $t['created_at'])) ?><?php if (!empty($t['paid_at'])): ?><br>Paid <?= e(format_datetime((string) $t['paid_at'])) ?><?php endif; ?></td>
                    <td class="text-end">
                        <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-pm-details="<?= e(json_encode($details)) ?>">
                                <i class="bi bi-eye" aria-hidden="true"></i> View
                            </button>
                            <?php if ($isOnline && !$settled): ?>
                            <form method="post" action="<?= e(route('admin/payments/' . $pId . '/check-status')) ?>" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                                <button type="submit" class="btn btn-outline-primary btn-sm" title="Ask PayMongo for the real status"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Check PayMongo</button>
                            </form>
                            <?php endif; ?>
                            <?php if ($inReview): ?>
                            <button type="button" class="btn btn-success btn-sm" data-pm-verify="<?= e(json_encode($details)) ?>"><i class="bi bi-check2" aria-hidden="true"></i> Verify</button>
                            <button type="button" class="btn btn-outline-danger btn-sm" data-pm-reject="<?= e(json_encode($details)) ?>"><i class="bi bi-x" aria-hidden="true"></i> Reject</button>
                            <?php endif; ?>
                            <?php if ($pst === DocumentPayment::STATUS_PAY_AT_PICKUP): ?>
                            <button type="button" class="btn btn-primary btn-sm" data-pm-cash="<?= e(json_encode($details)) ?>"><i class="bi bi-cash-stack" aria-hidden="true"></i> Cash received</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Details: fields, verification, audit history, admin-only waive / refund -->
<div class="modal fade" id="pmDetails" tabindex="-1" aria-labelledby="pmDetailsTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pmDetailsTitle">Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="pm-dl" id="pmDl"></dl>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    <a id="pmRequestLink" class="btn btn-outline-secondary btn-sm" href="#"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> View document request</a>
                    <a id="pmProofLink" class="btn btn-outline-secondary btn-sm d-none" href="#" target="_blank" rel="noopener"><i class="bi bi-receipt" aria-hidden="true"></i> View uploaded proof</a>
                </div>
                <h6 class="mt-4 mb-2 fw-bold">Audit history</h6>
                <ul class="pm-audit" id="pmAudit"><li class="text-muted">Loading…</li></ul>

                <?php if ($isAdmin): ?>
                <div id="pmAdminActions" class="mt-4 pt-3 border-top">
                    <form id="pmWaiveForm" method="post" class="d-none mb-3">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <label class="form-label small fw-semibold" for="pmWaiveReason">Waive this fee (admin) — reason</label>
                        <div class="input-group input-group-sm">
                            <input id="pmWaiveReason" name="waiver_reason" class="form-control" required maxlength="255" placeholder="e.g. Indigent resident, approved by Punong Barangay">
                            <button class="btn btn-outline-warning" type="submit">Waive fee</button>
                        </div>
                    </form>
                    <form id="pmRefundForm" method="post" class="d-none">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <label class="form-label small fw-semibold" for="pmRefundReason">Record a refund (admin) — refund first in the PayMongo dashboard, then record it here</label>
                        <div class="input-group input-group-sm">
                            <input name="refund_amount" type="number" step="0.01" min="0.01" class="form-control" style="max-width:120px;" required aria-label="Refund amount">
                            <input id="pmRefundReason" name="refund_reason" class="form-control" required maxlength="255" placeholder="Reason">
                            <button class="btn btn-outline-danger" type="submit">Record refund</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Verify manual proof -->
<div class="modal fade" id="pmVerify" tabindex="-1" aria-labelledby="pmVerifyTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="post" id="pmVerifyForm">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
            <div class="modal-header"><h5 class="modal-title" id="pmVerifyTitle">Verify manual payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p class="small">Only verify after you have confirmed the money arrived — for a GCash payment, find it in the PayMongo dashboard first. The uploaded screenshot alone is not proof.</p>
                <dl class="pm-dl mb-3" id="pmVerifyDl"></dl>
                <label class="form-label small fw-semibold" for="pmVerifyNote">How you confirmed it</label>
                <input id="pmVerifyNote" name="note" class="form-control form-control-sm" required maxlength="255" placeholder="e.g. Found pay_… in PayMongo dashboard, ₱50.00 paid">
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success btn-sm">Mark as manually verified</button></div>
        </form>
    </div></div>
</div>

<!-- Reject manual proof -->
<div class="modal fade" id="pmReject" tabindex="-1" aria-labelledby="pmRejectTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="post" id="pmRejectForm">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
            <div class="modal-header"><h5 class="modal-title" id="pmRejectTitle">Reject proof of payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <label class="form-label small fw-semibold" for="pmRejectReason">Reason (the resident sees this)</label>
                <select id="pmRejectReason" name="rejection_reason" class="form-select form-select-sm mb-2" required>
                    <option value="">Choose a reason</option>
                    <option>No matching payment found in PayMongo</option>
                    <option>Amount does not match the fee</option>
                    <option>Screenshot is unreadable</option>
                    <option>Reference number is invalid or already used</option>
                    <option>Other (explained below)</option>
                </select>
                <textarea name="rejection_note" class="form-control form-control-sm" rows="3" maxlength="500" placeholder="Details for the resident" aria-label="Details for the resident"></textarea>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger btn-sm">Reject proof</button></div>
        </form>
    </div></div>
</div>

<!-- Cash received at the hall -->
<div class="modal fade" id="pmCash" tabindex="-1" aria-labelledby="pmCashTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="post" id="pmCashForm">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
            <div class="modal-header"><h5 class="modal-title" id="pmCashTitle">Cash received at the hall</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p class="small">Record this only when you are holding the cash. If the resident also started a GCash checkout, PayMongo is checked first so nobody pays twice.</p>
                <dl class="pm-dl mb-3" id="pmCashDl"></dl>
                <label class="form-label small fw-semibold" for="pmCashNote">Official receipt no. / note</label>
                <input id="pmCashNote" name="note" class="form-control form-control-sm" maxlength="255">
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary btn-sm">Record cash payment</button></div>
        </form>
    </div></div>
</div>

<script>
(function () {
    var base = <?= json_encode(route('admin/payments/')) ?>;
    function fill(dl, rows) {
        dl.textContent = '';
        rows.forEach(function (r) {
            if (!r[1]) { return; }
            var dt = document.createElement('dt'); dt.textContent = r[0];
            var dd = document.createElement('dd'); dd.textContent = r[1];
            dl.appendChild(dt); dl.appendChild(dd);
        });
    }
    function open(id) { bootstrap.Modal.getOrCreateInstance(document.getElementById(id)).show(); }
    function summary(d) { return [['Reference', d.payment_ref], ['Request', d.request_ref], ['Resident', d.resident], ['Document', d.document], ['Fee', d.amount]]; }

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('[data-pm-details],[data-pm-verify],[data-pm-reject],[data-pm-cash]');
        if (!btn) { return; }
        var d;
        if (btn.dataset.pmDetails) {
            d = JSON.parse(btn.dataset.pmDetails);
            document.getElementById('pmDetailsTitle').textContent = 'Payment ' + d.payment_ref;
            fill(document.getElementById('pmDl'), summary(d).concat([
                ['Amount reported', d.reported], ['Payment source', d.source], ['Method', d.method], ['Payment status', d.status],
                ['Verification', d.verification], ['Verified on', d.verified_at], ['Request status', d.request],
                ['Created', d.created], ['Paid', d.paid], ['PayMongo checkout', d.checkout_id], ['PayMongo payment', d.pm_payment],
                ['Proof reference', d.proof_ref], ['Note', d.failure]
            ]));
            document.getElementById('pmRequestLink').href = d.request_url;
            var proof = document.getElementById('pmProofLink');
            proof.classList.toggle('d-none', !d.proof_url); proof.href = d.proof_url || '#';
            var waive = document.getElementById('pmWaiveForm'), refund = document.getElementById('pmRefundForm');
            if (waive) { waive.classList.toggle('d-none', !d.can_waive); waive.action = base + d.id + '/waive'; }
            if (refund) { refund.classList.toggle('d-none', !d.can_refund); refund.action = base + d.id + '/refund'; }
            var audit = document.getElementById('pmAudit');
            audit.innerHTML = '<li class="text-muted">Loading…</li>';
            fetch(d.history_url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    audit.textContent = '';
                    (j.entries || []).forEach(function (en) {
                        var li = document.createElement('li');
                        var head = document.createElement('strong'); head.textContent = en.action;
                        li.appendChild(head);
                        li.appendChild(document.createTextNode(' · ' + en.at + ' · ' + en.by + (en.from || en.to ? ' · ' + (en.from || '—') + ' → ' + (en.to || '—') : '')));
                        if (en.detail) { var p = document.createElement('div'); p.className = 'pm-sub'; p.textContent = en.detail; li.appendChild(p); }
                        audit.appendChild(li);
                    });
                    if (!audit.children.length) { audit.innerHTML = '<li class="text-muted">No history yet.</li>'; }
                })
                .catch(function () { audit.innerHTML = '<li class="text-muted">Could not load history.</li>'; });
            open('pmDetails');
        } else if (btn.dataset.pmVerify) {
            d = JSON.parse(btn.dataset.pmVerify);
            fill(document.getElementById('pmVerifyDl'), summary(d).concat([['Amount reported', d.reported], ['Proof reference', d.proof_ref]]));
            document.getElementById('pmVerifyForm').action = base + d.id + '/verify';
            open('pmVerify');
        } else if (btn.dataset.pmReject) {
            d = JSON.parse(btn.dataset.pmReject);
            document.getElementById('pmRejectForm').action = base + d.id + '/reject';
            open('pmReject');
        } else if (btn.dataset.pmCash) {
            d = JSON.parse(btn.dataset.pmCash);
            fill(document.getElementById('pmCashDl'), summary(d));
            document.getElementById('pmCashForm').action = base + d.id + '/mark-pickup';
            open('pmCash');
        }
    });
})();
</script>
<?php require __DIR__ . '/../../shared/_live-refresh.php'; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
