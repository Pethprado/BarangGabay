<?php
/**
 * Import Preview and Curation Page for Manobo Vocabulary Documents.
 * Allows administrators to preview, correct, and map extracted entries before confirming import.
 */
$filename     = $filename     ?? 'Document';
$entries      = $entries      ?? [];
$unclearCount = $unclearCount ?? 0;
$needsReview  = $needsReview  ?? false;
$categories   = $categories   ?? ['general', 'greetings', 'numbers', 'family', 'requests', 'farming', 'health', 'civic'];
$partsOfSpeech= $partsOfSpeech?? ['noun', 'verb', 'adjective', 'adverb', 'phrase', 'pronoun'];

ob_start();
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <p class="mb-0" style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);">
            <?= e(t('admin_nav.management')) ?> &raquo; Manobo Dictionary
        </p>
        <h1 class="mb-0 mt-1" style="font-size:1.55rem;font-weight:800;color:var(--text-primary);line-height:1.1;">
            <i class="bi bi-file-earmark-text text-primary me-2"></i>Preview at Suriin ang In-upload na Dokumento
        </h1>
        <p class="text-muted mt-1 mb-0" style="font-size:.85rem;">
            Dokumento: <strong><?= e($filename) ?></strong> &bull; Nabasa: <strong><?= count($entries) ?></strong> entri.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(route('admin/manobo')) ?>" class="btn btn-outline-secondary btn-sm" style="border-radius:8px;font-weight:600;">
            <i class="bi bi-x-circle me-1"></i>Kanselahin (Cancel)
        </a>
    </div>
</div>

<?php if ($needsReview || $unclearCount > 0): ?>
<div class="alert alert-warning border-0 shadow-sm d-flex align-items-start gap-3 mb-4" style="border-radius:12px;background-color:#fffbe0;border-left:4px solid #f59e0b !important;">
    <i class="bi bi-exclamation-triangle-fill text-warning fs-4 flex-shrink-0"></i>
    <div>
        <h6 class="fw-bold mb-1 text-dark">Kailangan ng Manu-manong Pagsusuri (Review Required)</h6>
        <p class="mb-0 text-muted" style="font-size:.85rem;">
            Mayroong <strong><?= (int)$unclearCount ?></strong> entri na may hindi tiyak na pagkakabasa mula sa scanned/PDF document. Mangyaring suriin at iwasto ang spelling, kahulugan, o hanay bago kumpirmahin ang pag-import. Hindi kami kailanman nag-imbento ng kulang na salita.
        </p>
    </div>
</div>
<?php endif; ?>

<form action="<?= e(route('admin/manobo/import-confirm')) ?>" method="POST" id="import-confirm-form">
    <?= csrf_field() ?>

    <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold" style="font-size:1rem;color:var(--text-primary);">
                <i class="bi bi-list-check me-2 text-primary"></i>Mga Na-extract na Bokabularyo (Extracted Entries)
            </h5>
            <span class="badge bg-primary-subtle text-primary" style="font-size:.8rem;padding:.4em .8em;">
                Kabuuan: <?= count($entries) ?>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0" style="font-size:.85rem;">
                <thead style="background-color:var(--bg-secondary, #f8fafc);">
                    <tr style="border-bottom:2px solid var(--border, #e2e8f0);">
                        <th style="width:40px;text-align:center;">#</th>
                        <th style="width:160px;">Manobo Word / Phrase</th>
                        <th style="width:160px;">English Meaning</th>
                        <th style="width:160px;">Tagalog / Filipino</th>
                        <th style="width:140px;">Bisaya / Cebuano</th>
                        <th style="width:110px;">Category</th>
                        <th style="width:100px;">POS</th>
                        <th style="width:140px;">Status / Duplicate</th>
                        <th style="width:150px;text-align:center;">Aksyon (Action)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $i => $item): ?>
                    <?php
                        $status = $item['status'] ?? 'new';
                        $rowClass = match($status) {
                            'exact_duplicate' => 'table-light text-muted',
                            'conflicting_meaning' => 'table-warning-subtle',
                            default => '',
                        };
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td style="text-align:center;font-weight:600;color:var(--text-muted);">
                            <?= $i + 1 ?>
                            <?php if (!empty($item['source_page'])): ?>
                                <br><span class="badge bg-light text-secondary border" style="font-size:.65rem;">P.<?= (int)$item['source_page'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <input type="text" name="entries[<?= $i ?>][manobo]" value="<?= e($item['manobo']) ?>" 
                                   class="form-control form-control-sm fw-bold" style="border-radius:6px;color:#0284c7;" required>
                        </td>
                        <td>
                            <input type="text" name="entries[<?= $i ?>][english]" value="<?= e($item['english']) ?>" 
                                   class="form-control form-control-sm" style="border-radius:6px;">
                        </td>
                        <td>
                            <input type="text" name="entries[<?= $i ?>][tagalog]" value="<?= e($item['tagalog']) ?>" 
                                   class="form-control form-control-sm" style="border-radius:6px;">
                        </td>
                        <td>
                            <input type="text" name="entries[<?= $i ?>][bisaya]" value="<?= e($item['bisaya']) ?>" 
                                   class="form-control form-control-sm" style="border-radius:6px;" placeholder="(Optional)">
                        </td>
                        <td>
                            <select name="entries[<?= $i ?>][category]" class="form-select form-select-sm" style="border-radius:6px;">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= e($cat) ?>" <?= ($item['category'] ?? 'general') === $cat ? 'selected' : '' ?>>
                                        <?= e(ucfirst($cat)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="entries[<?= $i ?>][part_of_speech]" class="form-select form-select-sm" style="border-radius:6px;">
                                <?php foreach ($partsOfSpeech as $pos): ?>
                                    <option value="<?= e($pos) ?>" <?= ($item['part_of_speech'] ?? 'noun') === $pos ? 'selected' : '' ?>>
                                        <?= e(ucfirst($pos)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <?php if ($status === 'exact_duplicate'): ?>
                                <span class="badge bg-secondary mb-1" style="font-size:.7rem;"><i class="bi bi-files me-1"></i>May Katulad na (Duplicate)</span>
                                <div style="font-size:.72rem;" class="text-muted">ID #<?= (int)$item['existing_id'] ?></div>
                            <?php elseif ($status === 'conflicting_meaning'): ?>
                                <span class="badge bg-warning text-dark mb-1" style="font-size:.7rem;"><i class="bi bi-exclamation-circle me-1"></i>Ibang Kahulugan</span>
                                <div style="font-size:.72rem;" class="text-muted" title="Mayroon nang salita ngunit magkaiba ang ibig sabihin">
                                    May umiiral na salita
                                </div>
                            <?php else: ?>
                                <span class="badge bg-success" style="font-size:.7rem;"><i class="bi bi-plus-circle me-1"></i>Bagong Salita</span>
                            <?php endif; ?>
                            
                            <input type="hidden" name="entries[<?= $i ?>][notes]" value="<?= e($item['notes']) ?>">
                            <input type="hidden" name="entries[<?= $i ?>][existing_id]" value="<?= e((string)($item['existing_id'] ?? '')) ?>">
                            <input type="hidden" name="entries[<?= $i ?>][source_page]" value="<?= e((string)($item['source_page'] ?? '')) ?>">
                        </td>
                        <td>
                            <select name="entries[<?= $i ?>][action]" class="form-select form-select-sm fw-semibold" style="border-radius:6px;">
                                <?php if ($status === 'exact_duplicate'): ?>
                                    <option value="skip" selected>Laktawan (Skip)</option>
                                    <option value="separate">Idagdag bilang hiwalay</option>
                                    <option value="overwrite">I-update ang umiiral</option>
                                <?php elseif ($status === 'conflicting_meaning'): ?>
                                    <option value="separate" selected>Hiwalay na Kahulugan</option>
                                    <option value="overwrite">I-update ang umiiral</option>
                                    <option value="skip">Laktawan (Skip)</option>
                                <?php else: ?>
                                    <option value="import" selected>I-import (Add)</option>
                                    <option value="skip">Laktawan (Skip)</option>
                                <?php endif; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white border-top py-3 d-flex justify-content-between align-items-center">
            <a href="<?= e(route('admin/manobo')) ?>" class="btn btn-outline-secondary btn-sm" style="border-radius:8px;font-weight:600;">
                <i class="bi bi-arrow-left me-1"></i>Bumalik
            </a>
            <button type="submit" class="btn btn-primary btn-sm px-4" style="border-radius:8px;font-weight:700;">
                <i class="bi bi-check-circle-fill me-1"></i>Kumpirmahin at I-import ang Bokabularyo
            </button>
        </div>
    </div>
</form>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
