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
        <a href="<?= e(route('admin/payments')) ?>" class="small text-decoration-none" style="color:var(--brand-primary);"><i class="bi bi-arrow-left" aria-hidden="true"></i> <?= e(__('Payment Management')) ?></a>
        <h2 class="mt-1 mb-1" style="font-family:var(--font-heading);font-weight:800;font-size:1.45rem;color:var(--text-primary);"><?= e(__('Document Fees')) ?></h2>
        <p class="mb-0 small" style="color:var(--text-muted);"><?= e(__('What residents see and pay when they request a document. Changes apply to new requests only.')) ?></p>
    </div>
</div>

<?php
$__gcName   = (string) \App\Models\Setting::get('gcash_receive_name', '');
$__gcNumber = (string) \App\Models\Setting::get('gcash_receive_number', '');
$__gcHasQr  = (string) \App\Models\Setting::get('gcash_receive_qr', '') !== '';
$__pmOn     = (bool) \App\Models\Setting::get('paymongo_checkout_enabled', false);
?>
<form method="post" action="<?= e(route('admin/payments/gcash-account')) ?>" enctype="multipart/form-data" class="fee-card" id="gcash-account" style="border-color:#007dfe;">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <h3><i class="bi bi-wallet2 me-1" style="color:#007dfe;" aria-hidden="true"></i><?= e(__('GCash Receiving Account')) ?></h3>
    <p class="fee-meta mb-3"><?= e(__('Residents send document fees here, then upload their GCash receipt for you to verify in Payment Management.')) ?></p>
    <div class="row g-3 align-items-start">
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold" for="gcName"><?= e(__('GCash account name')) ?></label>
            <input class="form-control form-control-sm" id="gcName" name="gcash_name" required maxlength="100" value="<?= e($__gcName) ?>">
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold" for="gcNumber"><?= e(__('GCash mobile number')) ?></label>
            <input class="form-control form-control-sm" id="gcNumber" name="gcash_number" required inputmode="numeric" pattern="0?9[0-9\s]{9,12}" value="<?= e($__gcNumber) ?>">
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold" for="gcQr"><?= e(__('GCash QR code image')) ?></label>
            <input class="form-control form-control-sm" id="gcQr" name="gcash_qr" type="file" accept="image/png,image/jpeg,image/webp" aria-describedby="gcQrHelp">
            <div class="fee-meta mt-1" id="gcQrHelp"><?= e(__('In GCash tap')) ?> <strong><?= e(__('QR → Receive money / My QR → Save')) ?></strong><?= e(__(', then upload that image here.')) ?></div>
            <?php if ($__gcHasQr): ?>
                <div class="d-flex align-items-center gap-2 mt-2">
                    <img src="<?= e(route('payments/gcash-qr')) ?>" alt="<?= e(__('Current GCash QR')) ?>" style="width:72px;height:72px;object-fit:contain;border:1px solid var(--border);border-radius:8px;background:#fff;">
                    <label class="small"><input type="checkbox" name="remove_qr" value="1"> <?= e(__('Remove QR')) ?></label>
                </div>
            <?php else: ?>
                <div class="fee-meta mt-1" style="color:var(--status-warning);"><?= e(__('No QR uploaded yet — residents will see the number only.')) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-12">
            <label class="small"><input type="checkbox" name="paymongo_checkout_enabled" value="1" <?= $__pmOn ? 'checked' : '' ?>> <?= e(__('Also offer PayMongo online checkout (needs PayMongo keys in System Settings)')) ?></label>
        </div>
    </div>
    <button type="submit" class="btn btn-primary btn-sm mt-3"><i class="bi bi-save me-1" aria-hidden="true"></i><?= e(__('Save GCash account')) ?></button>
</form>

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
                <div class="fee-meta"><?= e(__('Last updated:')) ?> <?= !empty($f['updated_at']) ? e(format_datetime((string) $f['updated_at'])) : __('never (default fee)') ?></div>
            </div>
            <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" role="switch" id="<?= $id ?>_active" name="fees[<?= e($type) ?>][is_active]" value="1" <?= $f['is_active'] ? 'checked' : '' ?>>
                <label class="form-check-label small fw-semibold" for="<?= $id ?>_active"><?= e(__('Residents can request this')) ?></label>
            </div>
        </div>

        <div class="fee-grid">
            <div>
                <label class="form-label small fw-semibold" for="<?= $id ?>_req"><?= e(__('Payment required?')) ?></label>
                <select class="form-select form-select-sm mb-2" id="<?= $id ?>_req" name="fees[<?= e($type) ?>][payment_required]" data-fee-toggle="<?= $id ?>_amount">
                    <option value="1" <?= $paid ? 'selected' : '' ?>><?= e(__('Yes — pay with GCash')) ?></option>
                    <option value="0" <?= $paid ? '' : 'selected' ?>><?= e(__('No — free document')) ?></option>
                </select>
                <label class="form-label small fw-semibold" for="<?= $id ?>_amount"><?= e(__('Processing fee')) ?></label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">₱</span>
                    <input type="number" step="0.01" min="0" max="10000" class="form-control fw-bold" id="<?= $id ?>_amount"
                           name="fees[<?= e($type) ?>][amount]" value="<?= number_format((float) $f['amount'], 2, '.', '') ?>"
                           aria-describedby="<?= $id ?>_hint" <?= $paid ? '' : 'disabled' ?>>
                </div>
                <div class="fee-meta mt-1" id="<?= $id ?>_hint"><?= e(__('Online payment needs at least ₱')) ?><?= number_format($minPesos, 2) ?>.</div>
            </div>
            <div>
                <label class="form-label small fw-semibold" for="<?= $id ?>_desc"><?= e(__('Description shown to residents')) ?></label>
                <textarea class="form-control form-control-sm" rows="4" maxlength="1000" id="<?= $id ?>_desc" name="fees[<?= e($type) ?>][description]"
                          placeholder="<?= e(__('What this document is for')) ?>"><?= e($f['description']) ?></textarea>
            </div>
            <div>
                <label class="form-label small fw-semibold" for="<?= $id ?>_reqs"><?= e(__('Requirements (one per line)')) ?></label>
                <textarea class="form-control form-control-sm" rows="4" maxlength="2000" id="<?= $id ?>_reqs" name="fees[<?= e($type) ?>][requirements]"
                          placeholder="<?= e(__('Valid ID Proof of residency')) ?>"><?= e($f['requirements']) ?></textarea>
            </div>
        </div>
    </fieldset>
    <?php endforeach; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="small" style="color:var(--text-muted);"><i class="bi bi-info-circle me-1" aria-hidden="true"></i><?= e(__('Every change is recorded in the payment audit history.')) ?></span>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1" aria-hidden="true"></i><?= e(__('Save document fees')) ?></button>
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
