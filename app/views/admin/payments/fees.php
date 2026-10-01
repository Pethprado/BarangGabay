<?php
/**
 * Admin Document Fees Configuration
 */
$pageTitle = $pageTitle ?? 'Presyo ng mga Dokumento (Document Fees)';
$fees      = $fees ?? [];

ob_start();
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="<?= e(route('admin/payments')) ?>">Pagbabayad</a></li>
                <li class="breadcrumb-item active" aria-current="page">Presyo ng Dokumento</li>
            </ol>
        </nav>
        <h1 class="h3 fw-bold mb-1" style="color:var(--text-primary);letter-spacing:-.02em;">
            <i class="bi bi-tags text-primary me-2"></i>Presyo ng mga Dokumento
        </h1>
        <p class="text-muted small mb-0">I-set ang kaukulang bayarin sa bawat uri ng barangay certificate o clearance. Kung libre, lagyan ng tsek ang "Libre".</p>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-3 overflow-hidden" style="background:var(--card-bg, #fff);max-width:850px;">
    <form method="post" action="<?= e(route('admin/payments/fees')) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:.875rem;">
                <thead class="table-light text-muted small text-uppercase tracking-wider">
                    <tr>
                        <th style="padding-left:20px;">Uri ng Dokumento</th>
                        <th style="width:200px;">Halaga (PHP)</th>
                        <th style="width:160px;text-align:center;">Libre (Free)</th>
                        <th style="width:140px;text-align:right;padding-right:20px;">Huling Na-update</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fees as $type => $f):
                        $amount = (float) $f['amount'];
                        $isFree = !empty($f['is_free']);
                    ?>
                    <tr>
                        <td style="padding-left:20px;">
                            <div class="fw-bold text-dark"><?= e((string) $f['label']) ?></div>
                            <div class="text-muted font-monospace" style="font-size:.72rem;"><?= e((string) $type) ?></div>
                        </td>
                        <td>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" min="0" 
                                       name="fees[<?= e($type) ?>][amount]" 
                                       id="amount_<?= e($type) ?>"
                                       value="<?= number_format($amount, 2, '.', '') ?>" 
                                       class="form-control fw-bold" 
                                       <?= $isFree ? 'disabled' : '' ?>>
                            </div>
                        </td>
                        <td style="text-align:center;">
                            <div class="form-check form-switch d-inline-block">
                                <input class="form-check-input" type="checkbox" 
                                       name="fees[<?= e($type) ?>][is_free]" 
                                       value="1" 
                                       id="free_<?= e($type) ?>"
                                       <?= $isFree ? 'checked' : '' ?>
                                       onchange="toggleFree('<?= e($type) ?>', this.checked)">
                                <label class="form-check-label small fw-semibold" for="free_<?= e($type) ?>">
                                    <?= $isFree ? '<span class="badge bg-success-subtle text-success">LIBRE</span>' : 'May Bayad' ?>
                                </label>
                            </div>
                        </td>
                        <td style="text-align:right;padding-right:20px;" class="text-muted small">
                            <?= !empty($f['updated_at']) ? e(format_datetime((string) $f['updated_at'])) : 'Default' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="p-3 bg-light border-top d-flex justify-content-between align-items-center">
            <span class="text-muted small">
                <i class="bi bi-info-circle me-1"></i>Ang mga binagong presyo ay mag-aaplay lamang sa mga susunod na bagong kahilingan.
            </span>
            <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold shadow-sm">
                <i class="bi bi-save me-1"></i>I-save ang mga Presyo
            </button>
        </div>
    </form>
</div>

<script>
function toggleFree(type, isChecked) {
    const input = document.getElementById('amount_' + type);
    if (isChecked) {
        input.value = '0.00';
        input.disabled = true;
    } else {
        if (input.value === '0.00') {
            input.value = '50.00';
        }
        input.disabled = false;
    }
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
