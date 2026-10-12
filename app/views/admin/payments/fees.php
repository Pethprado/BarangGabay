<?php
/**
 * Admin — Document types & fees.
 *
 * The single source of what each document costs. New requests copy the fee
 * from here when they are filed, and PayMongo is charged from that copy, so a
 * resident can never change the amount. Changes apply to NEW requests only.
 */
use App\Services\PayMongoService;

$fees = $fees ?? [];
$minPesos = PayMongoService::MIN_AMOUNT_CENTAVOS / 100;

ob_start();
?>
<style>
.fee-card { border:1px solid var(--border); border-radius:14px; background:var(--surface-card); padding:1.1rem 1.25rem; margin-bottom:1rem; }
.fee-card.is-off { opacity:.75; }
.fee-card h3 { margin:0; font-size:1rem; font-weight:800; color:var(--text-primary); }
.fee-grid { display:grid; gap:1rem; grid-template-columns:1fr; margin-top:.9rem; }
@media (min-width:992px) { .fee-grid { grid-template-columns:220px 1fr 1fr; } }
.fee-meta { font-size:.75rem; color:var(--text-muted); }
</style>

<div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
    <div>
        <a href="<?= e(route('admin/payments')) ?>" class="small text-decoration-none" style="color:var(--brand-primary);"><i class="bi bi-arrow-left" aria-hidden="true"></i> Payment Management</a>
        <h2 class="mt-1 mb-1" style="font-family:var(--font-heading);font-weight:800;font-size:1.45rem;color:var(--text-primary);">Document Fees</h2>
        <p class="mb-0 small" style="color:var(--text-muted);">What residents see and pay when they request a document. Changes apply to new requests only.</p>
    </div>
</div>

<form method="post" action="<?= e(route('admin/payments/fees')) ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <?php foreach ($fees as $type => $f):
        $id = 'fee_' . preg_replace('/[^a-z0-9_]/', '', (string) $type);
        $paid = !$f['is_free'];
    ?>
    <fieldset class="fee-card<?= $f['is_active'] ? '' : ' is-off' ?>">
        <legend class="visually-hidden"><?= e($f['label']) ?></legend>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h3><?= e($f['label']) ?></h3>
                <div class="fee-meta">Last updated: <?= !empty($f['updated_at']) ? e(format_datetime((string) $f['updated_at'])) : 'never (default fee)' ?></div>
            </div>
            <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" role="switch" id="<?= $id ?>_active" name="fees[<?= e($type) ?>][is_active]" value="1" <?= $f['is_active'] ? 'checked' : '' ?>>
                <label class="form-check-label small fw-semibold" for="<?= $id ?>_active">Residents can request this</label>
            </div>
        </div>

        <div class="fee-grid">
            <div>
                <label class="form-label small fw-semibold" for="<?= $id ?>_req">Payment required?</label>
                <select class="form-select form-select-sm mb-2" id="<?= $id ?>_req" name="fees[<?= e($type) ?>][payment_required]" data-fee-toggle="<?= $id ?>_amount">
                    <option value="1" <?= $paid ? 'selected' : '' ?>>Yes — pay online with GCash</option>
                    <option value="0" <?= $paid ? '' : 'selected' ?>>No — free document</option>
                </select>
                <label class="form-label small fw-semibold" for="<?= $id ?>_amount">Processing fee</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">₱</span>
                    <input type="number" step="0.01" min="0" max="10000" class="form-control fw-bold" id="<?= $id ?>_amount"
                           name="fees[<?= e($type) ?>][amount]" value="<?= number_format((float) $f['amount'], 2, '.', '') ?>"
                           aria-describedby="<?= $id ?>_hint" <?= $paid ? '' : 'disabled' ?>>
                </div>
                <div class="fee-meta mt-1" id="<?= $id ?>_hint">GCash via PayMongo needs at least ₱<?= number_format($minPesos, 2) ?>.</div>
            </div>
            <div>
                <label class="form-label small fw-semibold" for="<?= $id ?>_desc">Description shown to residents</label>
                <textarea class="form-control form-control-sm" rows="4" maxlength="1000" id="<?= $id ?>_desc" name="fees[<?= e($type) ?>][description]"
                          placeholder="What this document is for"><?= e($f['description']) ?></textarea>
            </div>
            <div>
                <label class="form-label small fw-semibold" for="<?= $id ?>_reqs">Requirements (one per line)</label>
                <textarea class="form-control form-control-sm" rows="4" maxlength="2000" id="<?= $id ?>_reqs" name="fees[<?= e($type) ?>][requirements]"
                          placeholder="Valid ID&#10;Proof of residency"><?= e($f['requirements']) ?></textarea>
            </div>
        </div>
    </fieldset>
    <?php endforeach; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="small" style="color:var(--text-muted);"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Every change is recorded in the payment audit history.</span>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1" aria-hidden="true"></i>Save document fees</button>
    </div>
</form>

<script>
document.querySelectorAll('[data-fee-toggle]').forEach(function (sel) {
    sel.addEventListener('change', function () {
        var amount = document.getElementById(sel.dataset.feeToggle);
        var paid = sel.value === '1';
        amount.disabled = !paid;
        if (paid && (amount.value === '' || parseFloat(amount.value) <= 0)) { amount.value = '50.00'; }
    });
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
