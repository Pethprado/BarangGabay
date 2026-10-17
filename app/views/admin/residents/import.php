<?php
/**
 * Residents → Import: upload a list, check the preview, import.
 *
 * Variables: $pending (null | file, rows, default, header, truncated), $puroks
 */
$pending = $pending ?? null;
$rows    = $pending['rows'] ?? [];
$countBy = ['new' => 0, 'duplicate' => 0, 'invalid' => 0];
foreach ($rows as $r) {
    $countBy[$r['status']] = ($countBy[$r['status']] ?? 0) + 1;
}
$badge = [
    'new'       => ['New', 'background:#dcfce7;color:#166534;'],
    'duplicate' => ['Already registered', 'background:#fef9c3;color:#854d0e;'],
    'invalid'   => ['Needs fixing', 'background:#fee2e2;color:#991b1b;'],
];

ob_start();
?>

<div class="mb-3">
    <p class="mb-0" style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);"><?= e(t('residents.eyebrow')) ?></p>
    <h1 class="mb-0 mt-1" style="font-size:1.55rem;font-weight:800;color:var(--text-primary);line-height:1.1;"><?= e(t('residents.title')) ?></h1>
    <p class="text-muted mt-1 mb-0" style="font-size:.82rem;max-width:780px;">
        Add many residents at once from a list: the whole barangay or one purok. Nothing is saved until you check the preview and press Import.
    </p>
</div>
<?php $residentsView = 'import'; require __DIR__ . '/_switch.php'; ?>

<?php if ($pending === null): ?>
<!-- ── Step 1: upload ────────────────────────────────────────────────── -->
<div class="row g-4">
    <div class="col-lg-7">
        <div class="admin-card">
            <h2 class="h6 fw-bold mb-3"><span class="badge bg-success me-2">1</span>Upload the list</h2>
            <form method="post" action="<?= e(route('admin/residents/import')) ?>" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label for="list" class="form-label fw-semibold small">File (CSV, Excel .xlsx or PDF, up to 10 MB)</label>
                <input id="list" type="file" name="list" required accept=".csv,.txt,.xlsx,.pdf,text/csv,application/pdf,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="form-control mb-3">

                <label for="default_purok" class="form-label fw-semibold small">Purok of this list</label>
                <select id="default_purok" name="default_purok" class="form-select mb-1">
                    <option value="">Use the purok written in the file (or none)</option>
                    <?php foreach ($puroks as $p): ?>
                    <option value="<?= e($p) ?>">Everyone in this file is in <?= e($p) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-muted small mb-3">For a list of one purok, choose it here. A purok written on a row, or a heading line like "PUROK 3", still wins.</p>

                <button type="submit" class="btn-barangay"><i class="bi bi-eye"></i> Read the file and show the preview</button>
            </form>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="admin-card h-100">
            <h2 class="h6 fw-bold mb-2"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>How the list should look</h2>
            <ul class="small mb-3 ps-3">
                <li class="mb-1">A first row with column names. Recognised (English or Filipino): <strong>Name / Pangalan</strong> (or Last name, First name, Middle name), <strong>Purok / Zone / Sitio</strong>, <strong>Phone / Contact</strong>, <strong>Address / Tirahan</strong>, Email, Sex / Kasarian, Civil status, Birthdate / Kaarawan.</li>
                <li class="mb-1">Names like <em>DELA CRUZ, JUAN S.</em> become <em>Juan S. Dela Cruz</em>.</li>
                <li class="mb-1">A list grouped by purok can have heading lines such as <em>PUROK 3</em>; the names under it go to Purok 3.</li>
                <li class="mb-1">PDF: only PDFs with real text (made from Word or Excel). A scanned paper cannot be read; type it in Excel instead.</li>
                <li>Up to <?= \App\Services\ResidentImport::MAX_ROWS ?> residents per file.</li>
            </ul>
            <a href="<?= e(route('admin/residents/import/template')) ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1" aria-hidden="true"></i>Download a template (CSV, opens in Excel)</a>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ── Step 2: preview ───────────────────────────────────────────────── -->
<div class="admin-card mb-3" id="preview">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <h2 class="h6 fw-bold mb-1"><span class="badge bg-success me-2">2</span>Check the preview: <?= e((string) $pending['file']) ?></h2>
            <p class="text-muted small mb-0">
                Ticked rows will be added as verified residents in the purok shown. Change a purok or untick a row before importing.
                <?= !$pending['header'] ? '<strong>No column names were found, so each line was read by its look; check the names carefully.</strong>' : '' ?>
                <?= $pending['truncated'] ? '<strong class="text-danger">Only the first ' . \App\Services\ResidentImport::MAX_ROWS . ' rows were read; import the rest in another file.</strong>' : '' ?>
            </p>
        </div>
        <form method="post" action="<?= e(route('admin/residents/import/cancel')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-x-lg me-1" aria-hidden="true"></i>Cancel and upload another file</button>
        </form>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <span class="badge" style="<?= $badge['new'][1] ?>font-size:.8rem;"><?= $countBy['new'] ?> new</span>
        <span class="badge" style="<?= $badge['duplicate'][1] ?>font-size:.8rem;"><?= $countBy['duplicate'] ?> already registered (unticked)</span>
        <span class="badge" style="<?= $badge['invalid'][1] ?>font-size:.8rem;"><?= $countBy['invalid'] ?> need fixing (skipped)</span>
    </div>
</div>

<form method="post" action="<?= e(route('admin/residents/import/confirm')) ?>" x-data="{ all: true }">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="admin-card p-0 mb-3">
        <div class="table-responsive" style="max-height:60vh;">
            <table class="admin-table mb-0">
                <thead style="position:sticky;top:0;z-index:1;">
                    <tr>
                        <th style="width:40px;padding-left:14px;"><input type="checkbox" class="form-check-input" checked aria-label="Tick all new rows"
                            @change="document.querySelectorAll('input[data-new]').forEach(c => c.checked = $event.target.checked)"></th>
                        <th>Line</th>
                        <th>Name</th>
                        <th style="min-width:150px;">Purok</th>
                        <th>Phone</th>
                        <th>Address / details</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $i => $r):
                    [$label, $style] = $badge[$r['status']] ?? $badge['invalid'];
                    $isNew = $r['status'] === 'new';
                ?>
                    <tr style="<?= $r['status'] === 'invalid' ? 'opacity:.7;' : '' ?>">
                        <td style="padding-left:14px;">
                            <?php if ($r['status'] !== 'invalid'): ?>
                            <input type="checkbox" class="form-check-input" name="include[<?= $i ?>]" value="1" <?= $isNew ? 'checked data-new' : '' ?> aria-label="Import <?= e($r['full_name']) ?>">
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= (int) $r['source_line'] ?></td>
                        <td>
                            <strong><?= e($r['full_name'] !== '' ? $r['full_name'] : '—') ?></strong>
                            <?php if ($r['email'] !== ''): ?><div class="text-muted small"><?= e($r['email']) ?></div><?php endif; ?>
                        </td>
                        <td>
                            <select name="purok[<?= $i ?>]" class="form-select form-select-sm" aria-label="Purok for <?= e($r['full_name']) ?>">
                                <option value="">No purok</option>
                                <?php foreach ($puroks as $p): ?>
                                <option value="<?= e($p) ?>" <?= $r['purok'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="small text-nowrap"><?= $r['phone'] !== '' ? e($r['phone']) : '<span class="text-muted">—</span>' ?></td>
                        <td class="small">
                            <?= e(trim(implode(' · ', array_filter([$r['address'], $r['sex'] !== '' ? ucfirst($r['sex']) : '', $r['civil_status'] !== '' ? ucfirst($r['civil_status']) : '', $r['date_of_birth'] !== '' ? 'Born ' . date('M j, Y', strtotime($r['date_of_birth'])) : ''])))) ?: '<span class="text-muted">—</span>' ?>
                            <?php foreach ($r['issues'] as $issue): ?><div class="text-danger" style="font-size:.74rem;"><?= e($issue) ?></div><?php endforeach; ?>
                        </td>
                        <td>
                            <span class="badge" style="<?= $style ?>"><?= e($label) ?></span>
                            <?php if (!empty($r['duplicate_of']['name'])): ?>
                            <div class="small text-muted mt-1">
                                <?php if (!empty($r['duplicate_of']['id'])): ?><a href="<?= e(route('admin/residents/' . (int) $r['duplicate_of']['id'])) ?>" target="_blank" rel="noopener"><?= e($r['duplicate_of']['name']) ?></a><?php else: ?><?= e($r['duplicate_of']['name']) ?><?php endif; ?>
                                <div style="font-size:.7rem;">Tick it only if this is a different person.</div>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <button type="submit" class="btn-barangay" onclick="return confirm('Import the ticked residents as verified residents?');">
            <i class="bi bi-person-plus-fill"></i> Import ticked residents
        </button>
        <span class="text-muted small">Imported residents are verified and counted in their purok. Without an email they cannot sign in until they register one; with an email they can use "Forgot password" to set a password.</span>
    </div>
</form>
<?php endif; ?>

<?php
$content   = ob_get_clean();
$pageTitle ??= t('residents.title');
require __DIR__ . '/../../layouts/admin.php';
?>
