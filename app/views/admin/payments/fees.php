<?php
/**
 * Admin — Document types & fees.
 *
 * The single source of what each document costs. New requests copy the fee
 * from here when they are filed; residents pay it in cash at the Barangay
 * Hall when they pick the document up. Changes apply to NEW requests only.
 */

$fees = $fees ?? [];

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
        <a href="<?= e(route('admin/documents')) ?>" class="small text-decoration-none" style="color:var(--brand-primary);"><i class="bi bi-arrow-left" aria-hidden="true"></i> <?= e(t('admin_documents.title')) ?></a>
        <h2 class="mt-1 mb-1" style="font-family:var(--font-heading);font-weight:800;font-size:1.45rem;color:var(--text-primary);"><?= e(__('Document Fees')) ?></h2>
        <p class="mb-0 small" style="color:var(--text-muted);"><?= e(__('What residents see when they request a document. Fees are paid in cash at the Barangay Hall on pickup. Changes apply to new requests only.')) ?></p>
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
                    <option value="1" <?= $paid ? 'selected' : '' ?>><?= e(__('Yes — paid at the Barangay Hall')) ?></option>
                    <option value="0" <?= $paid ? '' : 'selected' ?>><?= e(__('No — free document')) ?></option>
                </select>
                <label class="form-label small fw-semibold" for="<?= $id ?>_amount"><?= e(__('Processing fee')) ?></label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">₱</span>
                    <input type="number" step="0.01" min="0" max="10000" class="form-control fw-bold" id="<?= $id ?>_amount"
                           name="fees[<?= e($type) ?>][amount]" value="<?= number_format((float) $f['amount'], 2, '.', '') ?>"
                           <?= $paid ? '' : 'disabled' ?>>
                </div>
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
        <span class="small" style="color:var(--text-muted);"><i class="bi bi-info-circle me-1" aria-hidden="true"></i><?= e(__('Every change is recorded in the audit log.')) ?></span>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1" aria-hidden="true"></i><?= e(__('Save document fees')) ?></button>
    </div>
</form>

<script>
(function () {
    var input = document.getElementById('gcQr');
    if (!input) { return; }
    input.addEventListener('change', function () {
        var out = document.getElementById('gcQrPayload'), msg = document.getElementById('gcQrRead');
        out.value = ''; msg.textContent = '';
        var file = input.files && input.files[0];
        if (!file || typeof jsQR !== 'function') { return; }
        var img = new Image();
        img.onload = function () {
            var text = null;
            [1, 0.5, 0.75, 1.5].some(function (scale) {
                var c = document.createElement('canvas');
                c.width = Math.round(img.width * scale); c.height = Math.round(img.height * scale);
                var ctx = c.getContext('2d'); ctx.drawImage(img, 0, 0, c.width, c.height);
                var r = jsQR(ctx.getImageData(0, 0, c.width, c.height).data, c.width, c.height, { inversionAttempts: 'attemptBoth' });
                if (r) { text = r.data; }
                return !!r;
            });
            out.value = text || '';
            msg.textContent = text && text.indexOf('000201') === 0
                ? 'QR read. Residents will get this QR with the fee already filled in.'
                : 'Could not read this QR. Residents will see it as is and type the amount themselves.';
            msg.style.color = text ? '#166534' : '#92400e';
        };
        img.src = URL.createObjectURL(file);
    });
})();
</script>
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
