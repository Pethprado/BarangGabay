<?php
/**
 * Admin curation screen for the Bisaya fallback dictionary — the second-tier
 * dataset behind Manobo for the "MN" auto-translate (see
 * data/bisaya/README.md and App\Services\ManoboAutoTranslator).
 *
 * Deliberately mirrors admin/manobo/index.php's layout and class names so the
 * two feel like one feature; skips the Manobo-only "coverage gaps" and
 * "words the interface needs" panels, which do not apply here.
 */
$entries           = $entries           ?? [];
$totalEntries      = $totalEntries      ?? 0;
$categories        = $categories        ?? [];
$partsOfSpeech     = $partsOfSpeech     ?? [];
$search            = $search            ?? '';
$filterCategory    = $filterCategory    ?? '';
$tryTerm           = $tryTerm           ?? '';
$tryTo             = $tryTo             ?? 'english';
$tryResult         = $tryResult         ?? null;
$needsVerification = $needsVerification ?? [];
$trash             = $trash             ?? [];
$canRestore        = $canRestore        ?? false;
$canDelete         = $canDelete         ?? false;

ob_start();
?>

<!-- ── Page header ─────────────────────────────────────────────────────── -->
<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <p class="mb-0" style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);">
            <?= e(t('admin_nav.management')) ?>
        </p>
        <h1 class="mb-0 mt-1" style="font-size:1.55rem;font-weight:800;color:var(--text-primary);line-height:1.1;">
            <?= e(t('admin_bisaya.title')) ?>
        </h1>
        <p class="text-muted mt-1 mb-0" style="font-size:.82rem;">
            <?= e(t('admin_bisaya.subtitle', ['count' => $totalEntries])) ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= e(route('admin/manobo')) ?>" class="btn btn-outline-secondary btn-sm" style="border-radius:8px;font-weight:600;">
            <i class="bi bi-translate me-1"></i><?= e(t('admin_bisaya.manage_manobo')) ?>
        </a>
        <a href="<?= e(route('admin/bisaya/export')) ?>" class="btn btn-outline-secondary btn-sm" style="border-radius:8px;font-weight:600;">
            <i class="bi bi-download me-1"></i><?= e(t('admin_bisaya.export')) ?>
        </a>
    </div>
</div>

<!-- ── Provenance notice — this dataset is not yet locally verified ──────── -->
<div style="border-radius:10px;padding:16px 20px;margin-bottom:1.5rem;display:flex;gap:14px;align-items:flex-start;
            background:var(--status-warning-bg);border:1px solid var(--status-warning);">
    <i class="bi bi-info-circle-fill" style="font-size:1.15rem;flex-shrink:0;margin-top:2px;color:var(--status-warning);"></i>
    <div style="min-width:0;">
        <p style="margin:0 0 6px;font-weight:700;font-size:.92rem;color:var(--text-primary);">
            <?= e(t('admin_bisaya.provenance_title')) ?>
        </p>
        <p style="margin:0;font-size:.83rem;line-height:1.7;color:var(--text-secondary);">
            <?= e(t('admin_bisaya.provenance_body')) ?>
        </p>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- ── Try a lookup ────────────────────────────────────────────────── -->
    <div class="col-lg-5">
        <div class="admin-card h-100">
            <h2 style="font-size:.95rem;font-weight:700;color:var(--text-primary);margin:0 0 14px;">
                <i class="bi bi-translate me-1" style="color:var(--brand-primary);"></i>
                <?= e(t('admin_manobo.try_title')) ?>
            </h2>
            <form method="GET" action="<?= e(route('admin/bisaya')) ?>">
                <input type="text" name="try" value="<?= e($tryTerm) ?>" class="form-control mb-2"
                       placeholder="<?= e(t('admin_manobo.try_placeholder')) ?>" style="border-radius:8px;">
                <div class="d-flex gap-2">
                    <select name="to" class="form-select" style="border-radius:8px;">
                        <option value="english" <?= $tryTo === 'english' ? 'selected' : '' ?>><?= e(t('admin_manobo.col_english')) ?></option>
                        <option value="tagalog" <?= $tryTo === 'tagalog' ? 'selected' : '' ?>><?= e(t('admin_manobo.col_tagalog')) ?></option>
                        <option value="bisaya"  <?= $tryTo === 'bisaya'  ? 'selected' : '' ?>><?= e(t('admin_bisaya.col_bisaya')) ?></option>
                    </select>
                    <button type="submit" class="btn btn-barangay" style="border-radius:8px;font-weight:600;white-space:nowrap;">
                        <?= e(t('admin_manobo.try_button')) ?>
                    </button>
                </div>
            </form>

            <?php if ($tryResult !== null): ?>
                <?php if ($tryResult['found']): ?>
                <div class="ai-summary-box mt-3" style="padding:14px 16px;">
                    <p style="margin:0;font-size:1.05rem;font-weight:700;color:var(--text-primary);">
                        <?= e((string) $tryResult['text']) ?>
                    </p>
                    <p style="margin:6px 0 0;font-size:.75rem;color:var(--text-muted);">
                        <?= e($tryResult['match_type']) ?>
                        <?php if (!empty($tryResult['missing'])): ?>
                            · <?= e(implode(', ', $tryResult['missing'])) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <?php else: ?>
                <p class="mt-3 mb-0" style="font-size:.82rem;color:var(--text-muted);">
                    <i class="bi bi-x-circle me-1"></i><?= e(t('admin_manobo.try_no_result')) ?>
                </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Add a word ──────────────────────────────────────────────────── -->
    <div class="col-lg-7">
        <div class="admin-card h-100">
            <h2 style="font-size:.95rem;font-weight:700;color:var(--text-primary);margin:0 0 14px;">
                <i class="bi bi-plus-circle me-1" style="color:var(--brand-primary);"></i>
                <?= e(t('admin_manobo.add_title')) ?>
            </h2>
            <form method="POST" action="<?= e(route('admin/bisaya')) ?>" id="bisaya-add-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('admin_bisaya.col_bisaya')) ?> *</label>
                        <input type="text" name="bisaya" class="form-control" required style="border-radius:8px;">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('admin_manobo.col_english')) ?> *</label>
                        <input type="text" name="english" class="form-control" required style="border-radius:8px;">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('admin_manobo.col_tagalog')) ?> *</label>
                        <input type="text" name="tagalog" class="form-control" required style="border-radius:8px;">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('admin_manobo.col_pos')) ?></label>
                        <input type="text" name="part_of_speech" class="form-control" list="pos-list" style="border-radius:8px;">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('admin_manobo.col_category')) ?></label>
                        <input type="text" name="category" class="form-control" list="cat-list" style="border-radius:8px;">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('admin_manobo.col_source')) ?></label>
                        <input type="text" name="source" class="form-control" placeholder="LOCAL" style="border-radius:8px;">
                    </div>
                    <div class="col-12">
                        <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('admin_manobo.col_notes')) ?></label>
                        <input type="text" name="notes" class="form-control" style="border-radius:8px;">
                    </div>
                </div>
                <button type="submit" class="btn btn-barangay mt-3" style="border-radius:8px;font-weight:600;">
                    <i class="bi bi-check-lg me-1"></i><?= e(t('admin_manobo.add_button')) ?>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ── Import from CSV ──────────────────────────────────────────────────── -->
<div class="admin-card mb-4">
    <h2 style="font-size:.95rem;font-weight:700;color:var(--text-primary);margin:0 0 6px;">
        <i class="bi bi-upload me-1" style="color:var(--brand-primary);"></i>
        <?= e(t('admin_manobo.import_title')) ?>
    </h2>
    <p class="text-muted mb-3" style="font-size:.82rem;line-height:1.7;">
        <?= e(t('admin_bisaya.import_body')) ?>
    </p>
    <form method="POST" action="<?= e(route('admin/bisaya/import')) ?>" enctype="multipart/form-data"
          class="d-flex flex-wrap align-items-center gap-2">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="file" name="csv_file" accept=".csv,text/csv" required class="form-control" style="max-width:320px;border-radius:8px;">
        <button type="submit" class="btn btn-barangay" style="border-radius:8px;font-weight:600;">
            <i class="bi bi-upload me-1"></i><?= e(t('admin_manobo.import_button')) ?>
        </button>
    </form>
</div>

<!-- ── Needs verification ───────────────────────────────────────────────── -->
<?php if ($needsVerification !== []): ?>
<div class="admin-card mb-4" style="border-color:var(--status-warning);">
    <div class="admin-card-header" style="background:var(--status-warning-bg);border-bottom-color:var(--status-warning);">
        <h2 class="admin-card-title mb-0" style="font-size:1rem;">
            <i class="bi bi-flag-fill me-2" style="color:var(--status-warning);"></i>
            <?= e(t('admin_manobo.verify_title')) ?>
            <span class="ms-1" style="font-weight:700;color:var(--status-warning);">(<?= count($needsVerification) ?>)</span>
        </h2>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th><?= e(t('admin_bisaya.col_bisaya')) ?></th>
                    <th><?= e(t('admin_manobo.col_english')) ?></th>
                    <th><?= e(t('admin_manobo.col_tagalog')) ?></th>
                    <th><?= e(t('admin_manobo.col_notes')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($needsVerification as $entry): ?>
                <tr>
                    <td style="font-weight:700;color:var(--brand-primary);white-space:nowrap;"><?= e($entry['bisaya']) ?></td>
                    <td><?= e($entry['english']) ?></td>
                    <td><?= e($entry['tagalog']) ?></td>
                    <td style="max-width:420px;font-size:.8rem;color:var(--text-secondary);"><?= e($entry['notes']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<datalist id="pos-list">
    <?php foreach ($partsOfSpeech as $pos): ?><option value="<?= e($pos) ?>"><?php endforeach; ?>
</datalist>
<datalist id="cat-list">
    <?php foreach ($categories as $cat): ?><option value="<?= e($cat) ?>"><?php endforeach; ?>
</datalist>

<!-- ── Search / filter ─────────────────────────────────────────────────── -->
<div class="admin-card mb-3">
    <form method="GET" action="<?= e(route('admin/bisaya')) ?>" class="row g-2 align-items-end">
        <div class="col-md-6">
            <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('admin_manobo.search_label')) ?></label>
            <input type="text" name="q" value="<?= e($search) ?>" class="form-control"
                   placeholder="<?= e(t('admin_manobo.search_placeholder')) ?>" style="border-radius:8px;">
        </div>
        <div class="col-md-4">
            <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('admin_manobo.col_category')) ?></label>
            <select name="category" class="form-select" style="border-radius:8px;">
                <option value=""><?= e(t('admin_manobo.all_categories')) ?></option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= e($cat) ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-barangay flex-grow-1" style="border-radius:8px;font-weight:600;">
                <?= e(t('admin_manobo.filter')) ?>
            </button>
            <?php if ($search !== '' || $filterCategory !== ''): ?>
            <a href="<?= e(route('admin/bisaya')) ?>" class="btn btn-outline-secondary" style="border-radius:8px;">
                <i class="bi bi-x-lg"></i>
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ── Entry table ─────────────────────────────────────────────────────── -->
<div class="admin-card p-0" x-data="{ editing: null }">
    <div class="table-responsive">
        <table class="table align-middle mb-0" style="font-size:.85rem;">
            <thead>
                <tr style="border-bottom:2px solid var(--border);">
                    <th style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);"><?= e(t('admin_bisaya.col_bisaya')) ?></th>
                    <th style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);"><?= e(t('admin_manobo.col_english')) ?></th>
                    <th style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);"><?= e(t('admin_manobo.col_tagalog')) ?></th>
                    <th style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);"><?= e(t('admin_manobo.col_category')) ?></th>
                    <th class="text-end" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);"><?= e(t('admin_manobo.col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($entries === []): ?>
                <tr><td colspan="5" class="text-center text-muted py-4"><?= e(t('admin_manobo.no_entries')) ?></td></tr>
            <?php endif; ?>

            <?php foreach ($entries as $idx => $entry): ?>
                <?php $key = $entry['bisaya'] . '-' . $idx; // headwords can repeat in this dataset — index by position too ?>

                <tr x-show="editing !== <?= e(json_encode($key)) ?>">
                    <td style="font-weight:700;color:var(--brand-primary);white-space:nowrap;"><?= e($entry['bisaya']) ?></td>
                    <td><?= e($entry['english']) ?></td>
                    <td><?= e($entry['tagalog']) ?></td>
                    <td>
                        <span style="font-size:.72rem;font-weight:600;background:var(--surface-muted,#f1f5f9);
                                     color:var(--text-muted);border-radius:20px;padding:2px 10px;">
                            <?= e($entry['category']) ?>
                        </span>
                    </td>
                    <td class="text-end" style="white-space:nowrap;">
                        <button type="button" class="btn btn-sm btn-outline-secondary" style="border-radius:6px;"
                                @click="editing = <?= e(json_encode($key)) ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php if ($canDelete): ?>
                        <form method="POST" action="<?= e(route('admin/bisaya/delete')) ?>" class="d-inline"
                              onsubmit="return confirm(<?= e(json_encode(t('admin_manobo.delete_confirm'))) ?>);">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="bisaya" value="<?= e($entry['bisaya']) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius:6px;">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr x-show="editing === <?= e(json_encode($key)) ?>" x-cloak style="background:var(--surface-muted,#f8fafc);">
                    <td colspan="5">
                        <form method="POST" action="<?= e(route('admin/bisaya/update')) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="original_bisaya" value="<?= e($entry['bisaya']) ?>">
                            <p style="font-size:.78rem;font-weight:700;color:var(--text-primary);margin:0 0 10px;">
                                <?= e(t('admin_manobo.edit_title')) ?>: <?= e($entry['bisaya']) ?>
                            </p>
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <input type="text" name="bisaya" value="<?= e($entry['bisaya']) ?>" required
                                           class="form-control form-control-sm" style="border-radius:6px;">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" name="english" value="<?= e($entry['english']) ?>" required
                                           class="form-control form-control-sm" style="border-radius:6px;">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" name="tagalog" value="<?= e($entry['tagalog']) ?>" required
                                           class="form-control form-control-sm" style="border-radius:6px;">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" name="category" value="<?= e($entry['category']) ?>"
                                           class="form-control form-control-sm" list="cat-list" style="border-radius:6px;">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" name="part_of_speech" value="<?= e($entry['part_of_speech']) ?>"
                                           class="form-control form-control-sm" list="pos-list" style="border-radius:6px;">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" name="source" value="<?= e($entry['source']) ?>"
                                           class="form-control form-control-sm" style="border-radius:6px;">
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="notes" value="<?= e($entry['notes']) ?>"
                                           class="form-control form-control-sm" style="border-radius:6px;">
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-2">
                                <button type="submit" class="btn btn-sm btn-barangay" style="border-radius:6px;font-weight:600;">
                                    <i class="bi bi-check-lg me-1"></i><?= e(t('admin_manobo.save_button')) ?>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" style="border-radius:6px;"
                                        @click="editing = null">
                                    <?= e(t('admin_manobo.cancel')) ?>
                                </button>
                            </div>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div style="padding:12px 18px;border-top:1px solid var(--border);">
        <p class="mb-0 text-muted" style="font-size:.78rem;">
            <?= e(t('admin_manobo.showing', ['shown' => count($entries), 'total' => $totalEntries])) ?>
        </p>
    </div>
</div>

<!-- ── Trash ────────────────────────────────────────────────────────────── -->
<div class="admin-card mt-4" id="trash">
    <div class="admin-card-header">
        <h2 class="admin-card-title mb-0" style="font-size:1rem;">
            <i class="bi bi-trash3 me-2" style="color:var(--text-muted);"></i>
            <?= e(t('admin_manobo.trash_title')) ?>
            <span class="ms-1" style="font-weight:700;color:var(--text-muted);">(<?= count($trash) ?>)</span>
        </h2>
        <p class="text-muted mb-0 mt-1" style="font-size:.78rem;"><?= e(t('admin_manobo.trash_body')) ?></p>
    </div>
    <?php if ($trash === []): ?>
    <div class="admin-card-body text-center text-muted py-4" style="font-size:.85rem;">
        <?= e(t('admin_manobo.trash_empty')) ?>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th><?= e(t('admin_bisaya.col_bisaya')) ?></th>
                    <th><?= e(t('admin_manobo.col_english')) ?></th>
                    <th><?= e(t('admin_manobo.col_tagalog')) ?></th>
                    <th><?= e(t('admin_manobo.trash_deleted_at')) ?></th>
                    <?php if ($canRestore): ?>
                    <th class="text-end"><?= e(t('admin_manobo.col_actions')) ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trash as $entry): ?>
                <tr>
                    <td style="font-weight:700;color:var(--text-muted);white-space:nowrap;text-decoration:line-through;"><?= e($entry['bisaya']) ?></td>
                    <td style="color:var(--text-muted);"><?= e($entry['english']) ?></td>
                    <td style="color:var(--text-muted);"><?= e($entry['tagalog']) ?></td>
                    <td style="color:var(--text-muted);font-size:.78rem;"><?= e($entry['deleted_at']) ?></td>
                    <?php if ($canRestore): ?>
                    <td class="text-end" style="white-space:nowrap;">
                        <form method="POST" action="<?= e(route('admin/bisaya/trash/restore')) ?>" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int) $entry['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary" style="border-radius:6px;"
                                    title="<?= e(t('admin_manobo.restore')) ?>">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                        </form>
                        <form method="POST" action="<?= e(route('admin/bisaya/trash/delete')) ?>" class="d-inline"
                              onsubmit="return confirm(<?= e(json_encode(t('admin_manobo.purge_confirm'))) ?>);">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int) $entry['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius:6px;"
                                    title="<?= e(t('admin_manobo.purge')) ?>">
                                <i class="bi bi-trash3-fill"></i>
                            </button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
