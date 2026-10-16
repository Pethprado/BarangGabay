<?php
/**
 * Admin → Document Templates.
 *
 * Variables: $templates (type => title/office/paragraphs/customized),
 *            $signatures (signer => role/has/at), $settings (key => label/value/default),
 *            $placeholders (NAME => description), $log (recent generations)
 */
use App\Models\DocumentRequest;

$labels = ['indigency' => 'Certificate of Indigency', 'clearance' => 'Barangay Clearance', 'residency' => 'Certificate of Residency', 'business' => 'Business Permit'];
$captainReady = $signatures['captain']['has'] ?? false;

ob_start();
?>

<div class="mb-4">
    <p class="mb-0" style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);">Documents</p>
    <h1 class="mb-0 mt-1" style="font-size:1.55rem;font-weight:800;color:var(--text-primary);line-height:1.1;">Document Templates</h1>
    <p class="text-muted mt-1 mb-0" style="font-size:.82rem;max-width:820px;">
        The four Barangay Bayogo documents are generated from these templates. When staff approve a request, the
        resident's verified profile and the request fill the <code>{{PLACEHOLDERS}}</code>, the authorized
        e-signature is applied, and the final PDF is saved to the resident's account with a QR verification code.
    </p>
</div>

<?php if (!$captainReady): ?>
<div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
    <i class="bi bi-pen-fill mt-1" aria-hidden="true"></i>
    <div><strong>Signature Required.</strong> Upload the Punong Barangay's authorized e-signature below. Until then, requests can be previewed but not approved, and no document is released.</div>
</div>
<?php endif; ?>

<!-- Signatures -->
<div class="admin-card mb-4" id="signatures">
    <h2 class="h6 fw-bold mb-1"><i class="bi bi-pen me-1" aria-hidden="true"></i>Authorized e-signatures</h2>
    <p class="text-muted small mb-3">
        Upload a signature image that the officer has authorized for digital barangay documents: a JPG, PNG or WEBP up
        to 10 MB. A clear photo or scan of the signature in dark ink on white paper is fine; the white background and
        empty edges are removed automatically. Never use a signature cut from a scanned document. The Punong Barangay's signature is
        required to release documents; the Secretary's and Treasurer's appear where their role signs (Clearance, Business Permit).
    </p>
    <div class="row g-3">
        <?php foreach ($signatures as $signer => $sig): ?>
        <div class="col-md-4">
            <div class="border rounded-3 p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong style="font-size:.88rem;"><?= e($sig['role']) ?></strong>
                    <?php if ($sig['has']): ?>
                        <span class="badge bg-success">Uploaded</span>
                    <?php else: ?>
                        <span class="badge <?= $signer === 'captain' ? 'bg-danger' : 'bg-secondary' ?>"><?= $signer === 'captain' ? 'Required' : 'Not uploaded' ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($sig['has']): ?>
                    <div class="border rounded mb-2 p-2 text-center" style="background:#fff;">
                        <img src="<?= e(route('admin/document-templates/signature/' . $signer)) ?>" alt="<?= e($sig['role']) ?> e-signature" style="max-height:60px;max-width:100%;">
                    </div>
                    <p class="text-muted mb-2" style="font-size:.72rem;">Uploaded <?= e($sig['at']) ?></p>
                    <form method="post" action="<?= e(route('admin/document-templates/signature/remove')) ?>" onsubmit="return confirm('Remove this e-signature?');" class="mb-2">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="signer" value="<?= e($signer) ?>">
                        <button class="btn btn-sm btn-outline-danger w-100" type="submit">Remove</button>
                    </form>
                <?php endif; ?>
                <form method="post" action="<?= e(route('admin/document-templates/signature')) ?>" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="signer" value="<?= e($signer) ?>">
                    <input type="file" name="signature" accept="image/png,image/jpeg,image/webp" class="form-control form-control-sm mb-2" required aria-label="<?= e($sig['role']) ?> signature image">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="authorized" value="1" id="auth-<?= e($signer) ?>" required>
                        <label class="form-check-label small" for="auth-<?= e($signer) ?>">The officer authorized this e-signature for official barangay documents.</label>
                    </div>
                    <button class="btn btn-sm btn-success w-100" type="submit"><?= $sig['has'] ? 'Replace' : 'Upload' ?> signature</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Officials, contact lines, numbering -->
<div class="admin-card mb-4" id="settings">
    <h2 class="h6 fw-bold mb-1"><i class="bi bi-people me-1" aria-hidden="true"></i>Officials, footer and numbering</h2>
    <p class="text-muted small mb-3">Printed on every document. O.R. numbers continue from the first number; business permits are numbered per year (e.g. 2026-014). Control numbers (RES-2026-000012) are automatic.</p>
    <form method="post" action="<?= e(route('admin/document-templates/settings')) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="row g-2">
            <?php foreach ($settings as $key => $s): ?>
            <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1" for="<?= e($key) ?>"><?= e($s['label']) ?></label>
                <input id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($s['value']) ?>" placeholder="<?= e($s['default']) ?>" class="form-control form-control-sm" maxlength="150"
                       <?= in_array($key, ['doc_or_start', 'doc_permit_start'], true) ? 'inputmode="numeric" pattern="[0-9]*"' : '' ?>>
            </div>
            <?php endforeach; ?>
        </div>
        <button class="btn btn-sm btn-success mt-3" type="submit"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Save details</button>
    </form>
</div>

<!-- Templates -->
<?php foreach ($templates as $type => $tpl): ?>
<div class="admin-card mb-4" id="tpl-<?= e($type) ?>">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h2 class="h6 fw-bold mb-0"><i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i><?= e($labels[$type]) ?>
            <?php if ($tpl['customized']): ?><span class="badge bg-info ms-1">Edited</span><?php endif; ?>
        </h2>
        <div class="d-flex gap-2">
            <a href="<?= e(route('admin/document-templates/' . $type . '/sample')) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1" aria-hidden="true"></i>Sample PDF</a>
            <?php if ($tpl['customized']): ?>
            <form method="post" action="<?= e(route('admin/document-templates/' . $type . '/reset')) ?>" onsubmit="return confirm('Go back to the barangay\'s default wording?');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="btn btn-sm btn-outline-secondary" type="submit">Reset to default</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <form method="post" action="<?= e(route('admin/document-templates/' . $type)) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="row g-2 mb-2">
            <div class="col-md-6">
                <label class="form-label small fw-semibold mb-1" for="title-<?= e($type) ?>">Title</label>
                <input id="title-<?= e($type) ?>" name="title" value="<?= e($tpl['title']) ?>" class="form-control form-control-sm" required maxlength="120">
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold mb-1" for="office-<?= e($type) ?>">Office line</label>
                <input id="office-<?= e($type) ?>" name="office" value="<?= e($tpl['office']) ?>" class="form-control form-control-sm" maxlength="120">
            </div>
        </div>
        <label class="form-label small fw-semibold mb-1" for="paras-<?= e($type) ?>">Paragraphs <span class="text-muted fw-normal">(leave an empty line between paragraphs · <code>**bold**</code> · <code>^^superscript^^</code>)</span></label>
        <textarea id="paras-<?= e($type) ?>" name="paragraphs" rows="9" class="form-control form-control-sm font-monospace" style="font-size:.8rem;" required><?= e(implode("\n\n", $tpl['paragraphs'])) ?></textarea>
        <button class="btn btn-sm btn-success mt-2" type="submit"><i class="bi bi-save me-1" aria-hidden="true"></i>Save template</button>
    </form>
</div>
<?php endforeach; ?>

<!-- Placeholders -->
<div class="admin-card mb-4" id="placeholders">
    <h2 class="h6 fw-bold mb-2"><i class="bi bi-braces me-1" aria-hidden="true"></i>Placeholders (filled automatically)</h2>
    <div class="row g-1" style="font-size:.8rem;">
        <?php foreach ($placeholders as $name => $what): ?>
        <div class="col-md-6"><code>{{<?= e($name) ?>}}</code> <span class="text-muted">— <?= e($what) ?></span></div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Audit -->
<div class="admin-card mb-4" id="audit">
    <h2 class="h6 fw-bold mb-2"><i class="bi bi-journal-text me-1" aria-hidden="true"></i>Recent document activity</h2>
    <?php if ($log === []): ?>
        <p class="text-muted small mb-0">No documents generated yet.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm mb-0" style="font-size:.8rem;">
            <thead><tr><th>When</th><th>Document</th><th>Action</th><th>By</th><th>Details</th></tr></thead>
            <tbody>
            <?php foreach ($log as $row): ?>
                <tr>
                    <td class="text-nowrap"><?= e(date('M d, Y g:i A', strtotime((string) $row['created_at']))) ?></td>
                    <td class="text-nowrap font-monospace"><?= e((string) ($row['certificate_no'] ?: DocumentRequest::label((string) $row['document_type']))) ?></td>
                    <td><?= e(ucwords(str_replace('_', ' ', (string) $row['action']))) ?></td>
                    <td><?= e((string) ($row['by_name'] ?? '—')) ?></td>
                    <td class="text-muted"><?= e(mb_strimwidth((string) $row['details'], 0, 140, '…')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php
$content   = ob_get_clean();
$pageTitle ??= 'Document Templates';
require __DIR__ . '/../../layouts/admin.php';
?>
