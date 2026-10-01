<?php
/**
 * Admin GCash Accounts Management
 */
$pageTitle = $pageTitle ?? 'GCash Accounts Management';
$accounts  = $accounts ?? [];

ob_start();
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="<?= e(route('admin/payments')) ?>">Pagbabayad</a></li>
                <li class="breadcrumb-item active" aria-current="page">GCash Accounts</li>
            </ol>
        </nav>
        <h1 class="h3 fw-bold mb-1" style="color:var(--text-primary);letter-spacing:-.02em;">
            <i class="bi bi-qr-code text-primary me-2"></i>Pamamahala ng GCash Accounts
        </h1>
        <p class="text-muted small mb-0">I-configure ang mga opisyal na GCash account at QR code na gagamitin ng mga residente para sa online payment.</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addGcashModal">
            <i class="bi bi-plus-lg me-1"></i>Magdagdag ng GCash Account
        </button>
    </div>
</div>

<div class="row g-4">
    <?php if ($accounts === []): ?>
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-3 p-5 text-center" style="background:var(--card-bg, #fff);">
            <i class="bi bi-qr-code" style="font-size:2.5rem;color:#cbd5e1;display:block;margin-bottom:1rem;"></i>
            <h5 class="fw-bold text-muted">Walang Naka-configure na GCash Account</h5>
            <p class="text-muted small mb-3">Magdagdag ng opisyal na GCash number at QR code para makapagbayad online ang mga residente.</p>
            <div>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addGcashModal">
                    <i class="bi bi-plus-lg me-1"></i>Magdagdag Ngayon
                </button>
            </div>
        </div>
    </div>
    <?php else: ?>
    <?php foreach ($accounts as $acc):
        $accId = (int) $acc['id'];
        $isDef = ((int) ($acc['is_default'] ?? 0)) === 1;
        $isAct = ((int) ($acc['is_active'] ?? 1)) === 1;
        $hasQr = !empty($acc['qr_image_data']);
    ?>
    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm rounded-3 h-100 overflow-hidden <?= $isDef ? 'ring-2' : '' ?>" 
             style="background:var(--card-bg, #fff);<?= $isDef ? 'border:2px solid #0284c7 !important;' : '' ?>">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-0"><?= e((string) $acc['account_name']) ?></h5>
                        <p class="text-muted small mb-0"><?= e((string) ($acc['description'] ?: 'GCash Receiving Account')) ?></p>
                    </div>
                    <div>
                        <?php if ($isDef): ?>
                        <span class="badge bg-primary px-2 py-1"><i class="bi bi-star-fill me-1"></i>Default</span>
                        <?php endif; ?>
                        <?php if (!$isAct): ?>
                        <span class="badge bg-secondary px-2 py-1">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- GCash Number Box -->
                <div class="p-3 rounded-2 bg-light border mb-3">
                    <div class="text-muted small text-uppercase fw-semibold" style="font-size:.7rem;">GCash Mobile Number</div>
                    <div class="d-flex align-items-center justify-content-between mt-1">
                        <span class="h5 fw-bold text-dark font-monospace mb-0"><?= e((string) $acc['mobile_number']) ?></span>
                        <button type="button" class="btn btn-outline-secondary btn-xs py-1 px-2" onclick="navigator.clipboard.writeText('<?= e((string) $acc['mobile_number']) ?>');this.innerText='Copied!';setTimeout(()=>this.innerText='Copy', 1500)">
                            Copy
                        </button>
                    </div>
                </div>

                <!-- QR Code Box -->
                <div class="text-center p-3 rounded-2 bg-light border mb-3">
                    <?php
                        $cleanMob = preg_replace('/[^0-9]/', '', (string)$acc['mobile_number']);
                        if ($cleanMob === '') $cleanMob = '09542968658';
                        $src = $hasQr ? ('data:' . ($acc['qr_mime_type'] ?: 'image/png') . ';base64,' . $acc['qr_image_data']) : ('https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($cleanMob));
                    ?>
                    <img src="<?= $src ?>" alt="GCash QR Code" class="img-fluid rounded border shadow-sm mb-2" style="max-height:160px;cursor:pointer;" onclick="openQrModal('<?= $src ?>', '<?= e((string) $acc['account_name']) ?>')">
                    <div class="d-flex justify-content-center gap-1">
                        <button type="button" class="btn btn-outline-primary btn-xs py-1 px-2 fw-semibold" style="font-size:.72rem;" onclick="openQrModal('<?= $src ?>', '<?= e((string) $acc['account_name']) ?>')">
                            <i class="bi bi-zoom-in me-1"></i>Palakihin ang QR
                        </button>
                        <button type="button" class="btn btn-outline-info btn-xs py-1 px-2 fw-semibold" style="font-size:.72rem;" onclick="navigator.clipboard.writeText('<?= e((string) $acc['mobile_number']) ?>');window.location.href='gcash://';">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Buksan ang GCash
                        </button>
                    </div>
                </div>

                <!-- Card Actions -->
                <div class="d-flex flex-wrap gap-2 pt-2 border-top">
                    <?php if (!$isDef && $isAct): ?>
                    <form method="post" action="<?= e(route('admin/payments/gcash/' . $accId . '/default')) ?>" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-star me-1"></i>Gawing Default
                        </button>
                    </form>
                    <?php endif; ?>

                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($acc), ENT_QUOTES, 'UTF-8') ?>)">
                        <i class="bi bi-pencil me-1"></i>I-edit
                    </button>

                    <?php if (!$isDef): ?>
                    <form method="post" action="<?= e(route('admin/payments/gcash/' . $accId . '/toggle')) ?>" class="d-inline ms-auto">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <button type="submit" class="btn btn-sm <?= $isAct ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                            <?= $isAct ? 'I-deactivate' : 'I-activate' ?>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ================= MODALS ================= -->

<!-- Add GCash Account Modal -->
<div class="modal fade" id="addGcashModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="post" action="<?= e(route('admin/payments/gcash')) ?>" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-qr-code me-2"></i>Magdagdag ng GCash Account</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Pangalan ng Account <span class="text-danger">*</span></label>
                        <input type="text" name="account_name" required placeholder="Hal: Barangay Bayogo Official o Juan Dela Cruz" class="form-control form-control-sm">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">GCash Mobile Number <span class="text-danger">*</span></label>
                        <input type="text" name="mobile_number" required placeholder="0917 123 4567" class="form-control form-control-sm font-monospace">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">QR Code Image (PNG / JPG / JPEG)</label>
                        <input type="file" name="qr_image" accept="image/png,image/jpeg,image/jpg" class="form-control form-control-sm">
                        <div class="form-text" style="font-size:.72rem;">I-upload ang opisyal na QR code mula sa GCash app para madaling ma-scan ng residente.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Paglalarawan (Description)</label>
                        <input type="text" name="description" placeholder="Hal: Barangay Treasurer Receiving Account" class="form-control form-control-sm">
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_default" value="1" id="addDefaultSwitch">
                        <label class="form-check-label small fw-semibold" for="addDefaultSwitch">Gawing Pangunahing (Default) GCash Account</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Kanselahin</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold">
                        <i class="bi bi-plus-lg me-1"></i>I-save ang Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit GCash Account Modal -->
<div class="modal fade" id="editGcashModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="editGcashForm" method="post" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil me-2"></i>I-edit ang GCash Account</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Pangalan ng Account <span class="text-danger">*</span></label>
                        <input type="text" name="account_name" id="editAccountName" required class="form-control form-control-sm">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">GCash Mobile Number <span class="text-danger">*</span></label>
                        <input type="text" name="mobile_number" id="editMobileNumber" required class="form-control form-control-sm font-monospace">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Baguhin ang QR Code Image (Opsyonal)</label>
                        <input type="file" name="qr_image" accept="image/png,image/jpeg,image/jpg" class="form-control form-control-sm">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Paglalarawan</label>
                        <input type="text" name="description" id="editDescription" class="form-control form-control-sm">
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_default" value="1" id="editDefaultSwitch">
                        <label class="form-check-label small fw-semibold" for="editDefaultSwitch">Default Account</label>
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="editActiveSwitch">
                        <label class="form-check-label small fw-semibold" for="editActiveSwitch">Aktibo (Active)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Kanselahin</button>
                    <button type="submit" class="btn btn-dark btn-sm fw-bold">
                        <i class="bi bi-check-lg me-1"></i>I-update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Enlarge QR Modal -->
<div class="modal fade" id="enlargeQrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="eqmTitle">GCash QR Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <img id="eqmImg" src="" alt="Enlarged QR" class="img-fluid rounded border shadow-sm" style="max-height:420px;width:auto;">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Isara</button>
            </div>
        </div>
    </div>
</div>

<script>
function openQrModal(src, title) {
    document.getElementById('eqmImg').src = src;
    document.getElementById('eqmTitle').innerText = title + ' - GCash QR';
    new bootstrap.Modal(document.getElementById('enlargeQrModal')).show();
}

function openEditModal(acc) {
    document.getElementById('editGcashForm').action = '<?= e(route('admin/payments/gcash/')) ?>' + acc.id;
    document.getElementById('editAccountName').value = acc.account_name || '';
    document.getElementById('editMobileNumber').value = acc.mobile_number || '';
    document.getElementById('editDescription').value = acc.description || '';
    document.getElementById('editDefaultSwitch').checked = (parseInt(acc.is_default) === 1);
    document.getElementById('editActiveSwitch').checked = (parseInt(acc.is_active) === 1);

    new bootstrap.Modal(document.getElementById('editGcashModal')).show();
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
