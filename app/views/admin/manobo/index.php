<?php
/**
 * Admin curation screen for the Manobo dataset.
 *
 * Deliberately keeps the coverage gaps visible at the top: whoever sits down
 * with a Manobo speaker should be able to see at a glance what still needs
 * collecting. See data/manobo/README.md.
 */
$entries           = $entries           ?? [];
$totalEntries      = $totalEntries      ?? 0;
$categories        = $categories        ?? [];
$partsOfSpeech     = $partsOfSpeech     ?? [];
$coverage          = $coverage          ?? [];
$neededWords       = $neededWords       ?? [];
$search            = $search            ?? '';
$filterCategory    = $filterCategory    ?? '';
$tryTerm           = $tryTerm           ?? '';
$tryTo             = $tryTo             ?? 'english';
$tryResult         = $tryResult         ?? null;
$canDelete         = $canDelete         ?? false;
// ADDED
$needsVerification = $needsVerification ?? [];
$trash             = $trash             ?? [];
$canRestore        = $canRestore        ?? false;

$emptyCategories = array_keys(array_filter($coverage, static fn (int $n): bool => $n === 0));

ob_start();
?>

<!-- ── Page header ─────────────────────────────────────────────────────── -->
<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <p class="mb-0" style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8;">
            <?= e(t('admin_nav.management')) ?>
        </p>
        <h1 class="mb-0 mt-1" style="font-size:1.55rem;font-weight:800;color:var(--text-primary);line-height:1.1;">
            <?= e(t('admin_manobo.title')) ?>
        </h1>
        <p class="text-muted mt-1 mb-0" style="font-size:.82rem;">
            <?= e(t('admin_manobo.subtitle', ['count' => $totalEntries])) ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <form method="POST" action="<?= e(route('admin/manobo/regenerate-posts')) ?>" class="d-inline" onsubmit="return confirm('Sigurado ka bang gusto mong i-regenerate ang lahat ng MN post gamit ang pinakabagong diksyunaryo?');">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="btn btn-outline-primary btn-sm" style="border-radius:8px;font-weight:600;">
                <i class="bi bi-arrow-repeat me-1"></i>Regenerate MN Posts
            </button>
        </form>
        <!-- ADDED: manage the Bisaya fallback dictionary from the same place. -->
        <a href="<?= e(route('admin/bisaya')) ?>" class="btn btn-outline-secondary btn-sm"
           style="border-radius:8px;font-weight:600;">
            <i class="bi bi-translate me-1"></i><?= e(t('admin_manobo.manage_bisaya')) ?>
        </a>
        <a href="<?= e(route('admin/manobo/export')) ?>" class="btn btn-outline-secondary btn-sm"
           style="border-radius:8px;font-weight:600;">
            <i class="bi bi-download me-1"></i><?= e(t('admin_manobo.export')) ?>
        </a>
    </div>
</div>

<!-- ── Coverage warning ─────────────────────────────────────────────────
     Colours are declared as tokens rather than inline hex so the panel stays
     readable in dark mode — amber-on-cream inverts to amber-on-navy. -->
<style>
    .manobo-notice        { background:#fefce8; border:1px solid #fde047; }
    .manobo-notice-icon   { color:#d97706; }
    .manobo-notice-title  { color:#92400e; }
    .manobo-notice-body   { color:#78350f; }
    .manobo-chip-empty    { background:#fee2e2; color:#991b1b; }
    .manobo-chip-filled   { background:#dcfce7; color:#166534; }
    .manobo-flag-unverified { background:#fee2e2; color:#991b1b; }

    :root[data-theme="dark"] .manobo-notice        { background:rgba(217,119,6,.12); border-color:rgba(253,224,71,.32); }
    :root[data-theme="dark"] .manobo-notice-icon   { color:#fbbf24; }
    :root[data-theme="dark"] .manobo-notice-title  { color:#fcd34d; }
    :root[data-theme="dark"] .manobo-notice-body   { color:var(--text-primary); }
    :root[data-theme="dark"] .manobo-chip-empty    { background:rgba(220,38,38,.22); color:#fca5a5; }
    :root[data-theme="dark"] .manobo-chip-filled   { background:rgba(22,163,74,.22); color:#86efac; }
    :root[data-theme="dark"] .manobo-flag-unverified { background:rgba(220,38,38,.22); color:#fca5a5; }

    @media (prefers-color-scheme: dark) {
        :root:not([data-theme="light"]) .manobo-notice        { background:rgba(217,119,6,.12); border-color:rgba(253,224,71,.32); }
        :root:not([data-theme="light"]) .manobo-notice-icon   { color:#fbbf24; }
        :root:not([data-theme="light"]) .manobo-notice-title  { color:#fcd34d; }
        :root:not([data-theme="light"]) .manobo-notice-body   { color:var(--text-primary); }
        :root:not([data-theme="light"]) .manobo-chip-empty    { background:rgba(220,38,38,.22); color:#fca5a5; }
        :root:not([data-theme="light"]) .manobo-chip-filled   { background:rgba(22,163,74,.22); color:#86efac; }
        :root:not([data-theme="light"]) .manobo-flag-unverified { background:rgba(220,38,38,.22); color:#fca5a5; }
    }
</style>

<div class="manobo-notice" style="border-radius:10px;padding:16px 20px;margin-bottom:1.5rem;
            display:flex;gap:14px;align-items:flex-start;">
    <i class="bi bi-info-circle-fill manobo-notice-icon" style="font-size:1.15rem;flex-shrink:0;margin-top:2px;"></i>
    <div style="min-width:0;">
        <p class="manobo-notice-title" style="margin:0 0 6px;font-weight:700;font-size:.92rem;">
            <?= e(t('admin_manobo.gaps_title')) ?>
        </p>
        <p class="manobo-notice-body" style="margin:0 0 12px;font-size:.83rem;line-height:1.7;">
            <?= e(t('admin_manobo.gaps_body')) ?>
        </p>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($coverage as $name => $count): ?>
            <span class="<?= $count === 0 ? 'manobo-chip-empty' : 'manobo-chip-filled' ?>"
                  style="font-size:.75rem;font-weight:600;border-radius:20px;padding:3px 12px;">
                <?= e($name) ?>:
                <?= $count === 0 ? e(t('admin_manobo.gaps_empty')) : (int) $count ?>
            </span>
            <?php endforeach; ?>
        </div>
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
            <form method="GET" action="<?= e(route('admin/manobo')) ?>">
                <input type="text" name="try" value="<?= e($tryTerm) ?>" class="form-control mb-2"
                       placeholder="<?= e(t('admin_manobo.try_placeholder')) ?>" style="border-radius:8px;">
                <div class="d-flex gap-2">
                    <select name="to" class="form-select" style="border-radius:8px;">
                        <option value="english" <?= $tryTo === 'english' ? 'selected' : '' ?>><?= e(t('admin_manobo.col_english')) ?></option>
                        <option value="tagalog" <?= $tryTo === 'tagalog' ? 'selected' : '' ?>><?= e(t('admin_manobo.col_tagalog')) ?></option>
                        <option value="manobo"  <?= $tryTo === 'manobo'  ? 'selected' : '' ?>><?= e(t('admin_manobo.col_manobo')) ?></option>
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
                    <?php if (!empty($tryResult['ambiguous'])): ?>
                    <p class="mt-2 mb-0" style="font-size:.78rem;color:#92400e;">
                        <i class="bi bi-exclamation-triangle me-1"></i><?= e(t('admin_manobo.try_ambiguous')) ?>
                    </p>
                    <?php endif; ?>
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
            <form method="POST" action="<?= e(route('admin/manobo')) ?>" id="manobo-add-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('admin_manobo.col_manobo')) ?> *</label>
                        <input type="text" name="manobo" class="form-control" required style="border-radius:8px;">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('admin_manobo.col_english')) ?> *</label>
                        <input type="text" name="english" class="form-control" required style="border-radius:8px;">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('admin_manobo.col_tagalog')) ?> *</label>
                        <input type="text" name="tagalog" class="form-control" required style="border-radius:8px;">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" style="font-size:.78rem;font-weight:600;">Bisaya</label>
                        <input type="text" name="bisaya" class="form-control" style="border-radius:8px;" placeholder="Katumbas sa Bisaya">
                    </div>
                    <div class="col-12">
                        <p class="form-text mb-0" style="font-size:.74rem;"><?= e(t('admin_manobo.hint_manobo')) ?></p>
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
                        <p class="form-text mb-0" style="font-size:.74rem;"><?= e(t('admin_manobo.hint_notes')) ?></p>
                    </div>
                </div>
                <button type="submit" class="btn btn-barangay mt-3" style="border-radius:8px;font-weight:600;">
                    <i class="bi bi-check-lg me-1"></i><?= e(t('admin_manobo.add_button')) ?>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ── Document Import (.docx / .pdf / .csv) ───────────────────────── -->
<div class="admin-card mb-4">
    <h2 style="font-size:.95rem;font-weight:700;color:var(--text-primary);margin:0 0 6px;">
        <i class="bi bi-file-earmark-arrow-up me-1 text-primary"></i>
        Mag-upload ng Dokumento ng Bokabularyo (.docx / .pdf / .csv)
    </h2>
    <p class="text-muted mb-3" style="font-size:.82rem;line-height:1.7;">
        Mag-upload ng Word document (`.docx`), PDF (`.pdf`), o CSV file. Ang sistema ay mag-eextract ng mga salita, kahulugan, at talahanayan para sa iyong preview at pagsusuri bago ito mai-save sa opisyal na diksyunaryo.
    </p>
    <form method="POST" action="<?= e(route('admin/manobo/import-doc')) ?>" enctype="multipart/form-data"
          class="d-flex flex-wrap align-items-center gap-2">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="file" name="doc_file" accept=".docx,.pdf,.csv,.txt" required class="form-control" style="max-width:380px;border-radius:8px;">
        <button type="submit" class="btn btn-barangay" style="border-radius:8px;font-weight:600;">
            <i class="bi bi-search me-1"></i>Suriin at I-preview ang Dokumento
        </button>
    </form>
</div>

<!-- ── Import History & Undo ───────────────────────────────────────── -->
<?php if (!empty($importHistory)): ?>
<div class="admin-card mb-4">
    <div class="admin-card-header">
        <h2 class="admin-card-title mb-0" style="font-size:1rem;">
            <i class="bi bi-clock-history me-2 text-secondary"></i>
            Kasaysayan ng Import (Import History)
        </h2>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Batch ID</th>
                    <th>Dokumento</th>
                    <th>Kabuuan</th>
                    <th>Naidagdag</th>
                    <th>Na-update</th>
                    <th>Petsa</th>
                    <th class="text-end">Aksyon</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($importHistory as $batch): ?>
                <tr>
                    <td class="font-monospace" style="font-size:.78rem;"><?= e($batch['batch_id']) ?></td>
                    <td style="font-weight:600;"><?= e($batch['filename']) ?></td>
                    <td><span class="badge bg-primary-subtle text-primary"><?= (int)$batch['entry_count'] ?></span></td>
                    <td><span class="badge bg-success-subtle text-success"><?= (int)$batch['approved_count'] ?></span></td>
                    <td><span class="badge bg-info-subtle text-info"><?= (int)$batch['updated_count'] ?></span></td>
                    <td style="font-size:.78rem;color:var(--text-muted);"><?= e($batch['created_at']) ?></td>
                    <td class="text-end">
                        <?php if ($canRestore): ?>
                        <form method="POST" action="<?= e(route('admin/manobo/import-undo')) ?>" class="d-inline" onsubmit="return confirm('Sigurado ka bang gusto mong i-undo ang import na ito? Matatanggal ang mga bagong entri mula sa batch na ito.');">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="batch_id" value="<?= e($batch['batch_id']) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:.75rem;">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Undo Import
                            </button>
                        </form>
                        <?php else: ?>
                            <span class="text-muted" style="font-size:.75rem;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ── ADDED: "Kailangang i-verify" — every entry carrying a note ────────── -->
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
                    <th><?= e(t('admin_manobo.col_manobo')) ?></th>
                    <th><?= e(t('admin_manobo.col_english')) ?></th>
                    <th><?= e(t('admin_manobo.col_tagalog')) ?></th>
                    <th><?= e(t('admin_manobo.col_notes')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($needsVerification as $entry): ?>
                <tr>
                    <td style="font-weight:700;color:var(--brand-primary);white-space:nowrap;"><?= e($entry['manobo']) ?></td>
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

<!-- ── Words the interface still needs ─────────────────────────────────
     The worklist to bring to a Manobo speaker. Each label filled in here
     makes that much more of the UI render in Manobo automatically. -->
<?php if ($neededWords !== []): ?>
<div class="admin-card mb-4">
    <h2 style="font-size:.95rem;font-weight:700;color:var(--text-primary);margin:0 0 6px;">
        <i class="bi bi-list-check me-1" style="color:var(--brand-primary);"></i>
        <?= e(t('admin_manobo.needed_title')) ?>
    </h2>
    <p class="text-muted mb-3" style="font-size:.82rem;line-height:1.7;">
        <?= e(t('admin_manobo.needed_body')) ?>
    </p>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach ($neededWords as $word): ?>
        <button type="button" class="manobo-needed-chip" data-word="<?= e($word) ?>">
            <?= e($word) ?>
        </button>
        <?php endforeach; ?>
    </div>
</div>

<style>
    /* Explicit light values first, then a dark override — a bare
       var(--surface-muted) on a <button> loses to the UA button style. */
    .manobo-needed-chip {
        font-size:.78rem; font-weight:600; border-radius:20px; padding:4px 12px;
        background:#f1f5f9; color:#475569; border:1px solid #e2e8f0;
        cursor:pointer; transition:all .12s;
    }
    .manobo-needed-chip:hover {
        background:var(--brand-primary); color:#fff; border-color:var(--brand-primary);
    }

    :root[data-theme="dark"] .manobo-needed-chip {
        background:#1b2436; color:#a9b4c4; border-color:#263145;
    }
    @media (prefers-color-scheme: dark) {
        :root:not([data-theme="light"]) .manobo-needed-chip {
            background:#1b2436; color:#a9b4c4; border-color:#263145;
        }
    }
</style>

<script>
    // Clicking a needed label drops it into the add form's English field and
    // puts the cursor in Manobo, so a speaker can work straight down the list.
    document.querySelectorAll('.manobo-needed-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var form = document.getElementById('manobo-add-form');
            if<!-- ── Search / filter ─────────────────────────────────────────────────── -->
<div class="admin-card mb-3">
    <form method="GET" action="<?= e(route('admin/manobo')) ?>" class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('admin_manobo.search_label')) ?></label>
            <input type="text" name="q" value="<?= e($search) ?>" class="form-control"
                   placeholder="<?= e(t('admin_manobo.search_placeholder')) ?>" style="border-radius:8px;">
        </div>
        <div class="col-md-3">
            <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('admin_manobo.col_category')) ?></label>
            <select name="category" class="form-select" style="border-radius:8px;">
                <option value=""><?= e(t('admin_manobo.all_categories')) ?></option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= e($cat) ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" style="font-size:.78rem;font-weight:600;">Status</label>
            <select name="status" class="form-select" style="border-radius:8px;">
                <option value="">Lahat ng Status</option>
                <option value="approved" <?= ($filterStatus ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="pending_review" <?= ($filterStatus ?? '') === 'pending_review' ? 'selected' : '' ?>>Pending Review</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-barangay flex-grow-1" style="border-radius:8px;font-weight:600;">
                <?= e(t('admin_manobo.filter')) ?>
            </button>
            <?php if ($search !== '' || $filterCategory !== '' || ($filterStatus ?? '') !== ''): ?>
            <a href="<?= e(route('admin/manobo')) ?>" class="btn btn-outline-secondary" style="border-radius:8px;">
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
                    <th style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);"><?= e(t('admin_manobo.col_manobo')) ?></th>
                    <th style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);"><?= e(t('admin_manobo.col_english')) ?></th>
                    <th style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);"><?= e(t('admin_manobo.col_tagalog')) ?></th>
                    <th style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);">Bisaya</th>
                    <th style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);"><?= e(t('admin_manobo.col_category')) ?></th>
                    <th style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);">Review Status</th>
                    <th style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);"><?= e(t('admin_manobo.col_notes')) ?></th>
                    <th class="text-end" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);"><?= e(t('admin_manobo.col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($entries === []): ?>
                <tr><td colspan="8" class="text-center text-muted py-4"><?= e(t('admin_manobo.no_entries')) ?></td></tr>
            <?php endif; ?>

            <?php foreach ($entries as $entry): ?>
                <?php $key = $entry['manobo']; ?>
                <?php $isApproved = ($entry['review_status'] ?? '') === 'approved' && empty($entry['needs_review']); ?>

                <!-- display row -->
                <tr x-show="editing !== <?= e(json_encode($key)) ?>">
                    <td style="font-weight:700;color:var(--brand-primary);white-space:nowrap;">
                        <?= e($entry['manobo']) ?>
                        <?php if (!empty($entry['source_page'])): ?>
                            <span class="badge text-secondary" style="font-size:.65rem;">p.<?= e($entry['source_page']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($entry['english']) ?></td>
                    <td><?= e($entry['tagalog']) ?></td>
                    <td style="color:#0369a1;font-weight:500;"><?= e($entry['bisaya'] ?? '') ?></td>
                    <td>
                        <span style="font-size:.72rem;font-weight:600;background:var(--surface-muted,#f1f5f9);
                                     color:var(--text-muted);border-radius:20px;padding:2px 10px;">
                            <?= e($entry['category']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($isApproved): ?>
                            <span class="badge bg-success" style="font-size:.68rem;">Approved</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark" style="font-size:.68rem;">Pending Review</span>
                        <?php endif; ?>
                    </td>
                    <td style="max-width:240px;color:var(--text-muted);font-size:.78rem;line-height:1.5;">
                        <?= e($entry['notes']) ?>
                    </td>
                    <td class="text-end" style="white-space:nowrap;">
                        <?php if (!$isApproved): ?>
                        <form method="POST" action="<?= e(route('admin/manobo/approve')) ?>" class="d-inline" title="Approve this entry">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= e($entry['id']) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-success" style="border-radius:6px;">
                                <i class="bi bi-check-lg"></i> Approve
                            </button>
                        </form>
                        <?php else: ?>
                        <form method="POST" action="<?= e(route('admin/manobo/archive')) ?>" class="d-inline" title="Archive this entry">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= e($entry['id']) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-warning" style="border-radius:6px;" title="Archive">
                                <i class="bi bi-archive"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                style="border-radius:6px;"
                                @click="editing = <?= e(json_encode($key)) ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php if ($canDelete): ?>
                        <form method="POST" action="<?= e(route('admin/manobo/delete')) ?>" class="d-inline"
                              onsubmit="return confirm(<?= e(json_encode(t('admin_manobo.delete_confirm'))) ?>);">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="manobo" value="<?= e($entry['manobo']) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius:6px;">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>

                <!-- edit row -->
                <tr x-show="editing === <?= e(json_encode($key)) ?>" x-cloak
                    style="background:var(--surface-muted,#f8fafc);">
                    <td colspan="8">
                        <form method="POST" action="<?= e(route('admin/manobo/update')) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="original_manobo" value="<?= e($entry['manobo']) ?>">
                            <p style="font-size:.78rem;font-weight:700;color:var(--text-primary);margin:0 0 10px;">
                                <?= e(t('admin_manobo.edit_title')) ?>: <?= e($entry['manobo']) ?>
                            </p>
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-1">Manobo</label>
                                    <input type="text" name="manobo" value="<?= e($entry['manobo']) ?>" required
                                           class="form-control form-control-sm" style="border-radius:6px;">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-1">English</label>
                                    <input type="text" name="english" value="<?= e($entry['english']) ?>" required
                                           class="form-control form-control-sm" style="border-radius:6px;">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-1">Tagalog</label>
                                    <input type="text" name="tagalog" value="<?= e($entry['tagalog']) ?>" required
                                           class="form-control form-control-sm" style="border-radius:6px;">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-1">Bisaya</label>
                                    <input type="text" name="bisaya" value="<?= e($entry['bisaya'] ?? '') ?>"
                                           class="form-control form-control-sm" style="border-radius:6px;">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-1">Bahagi ng Pananalita</label>
                                    <input type="text" name="part_of_speech" value="<?= e($entry['part_of_speech']) ?>"
                                           class="form-control form-control-sm" list="pos-list" style="border-radius:6px;">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-1">Kategorya</label>
                                    <input type="text" name="category" value="<?= e($entry['category']) ?>"
                                           class="form-control form-control-sm" list="cat-list" style="border-radius:6px;">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-1">Source / Page</label>
                                    <input type="text" name="source" value="<?= e($entry['source']) ?>"
                                           class="form-control form-control-sm" style="border-radius:6px;">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-1">Mga Tala (Notes)</label>
                                    <input type="text" name="notes" value="<?= e($entry['notes']) ?>"
                                           class="form-control form-control-sm" style="border-radius:6px;">
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-2">
                                <button type="submit" class="btn btn-sm btn-barangay" style="border-radius:6px;font-weight:600;">
                                    <i class="bi bi-check-lg me-1"></i><?= e(t('admin_manobo.save_button')) ?>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        style="border-radius:6px;" @click="editing = null">
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

<!-- ── ADDED: Trash ────────────────────────────────────────────────────── -->
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
                    <th><?= e(t('admin_manobo.col_manobo')) ?></th>
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
                    <td style="font-weight:700;color:var(--text-muted);white-space:nowrap;text-decoration:line-through;"><?= e($entry['manobo']) ?></td>
                    <td style="color:var(--text-muted);"><?= e($entry['english']) ?></td>
                    <td style="color:var(--text-muted);"><?= e($entry['tagalog']) ?></td>
                    <td style="color:var(--text-muted);font-size:.78rem;"><?= e($entry['deleted_at']) ?></td>
                    <?php if ($canRestore): ?>
                    <td class="text-end" style="white-space:nowrap;">
                        <form method="POST" action="<?= e(route('admin/manobo/trash/restore')) ?>" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int) $entry['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary" style="border-radius:6px;"
                                    title="<?= e(t('admin_manobo.restore')) ?>">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                        </form>
                        <form method="POST" action="<?= e(route('admin/manobo/trash/delete')) ?>" class="d-inline"
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

<!-- ── Requirement 25: Track Missing Manobo Concepts ────────────────────── -->
<div class="admin-card mt-4" id="missing-concepts">
    <div class="admin-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2 class="admin-card-title mb-0" style="font-size:1rem;">
                <i class="bi bi-journal-text me-2" style="color:var(--brand-primary);"></i>
                Mga Konseptong Wala Pa sa Manobo (Missing Concepts)
                <span class="ms-1" style="font-weight:700;color:var(--text-muted);">(<?= count($missingConcepts ?? []) ?>)</span>
            </h2>
            <p class="text-muted mb-0 mt-1" style="font-size:.78rem;">
                Awtomatikong natutukoy ang mga salita/parirala mula sa mga anunsyo at ordinansa na pansamantalang ginamitan ng Bisaya fallback.
            </p>
        </div>
        <div>
            <span class="badge bg-secondary" style="font-size:.75rem;">
                Dictionary v<?= e($dictionaryVersion ?? 1) ?>
            </span>
        </div>
    </div>
    <?php if (empty($missingConcepts)): ?>
    <div class="admin-card-body text-center text-muted py-4" style="font-size:.85rem;">
        Walang naitalang nawawalang konsepto sa kasalukuyan.
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle mb-0" style="font-size:.85rem;">
            <thead>
                <tr style="border-bottom:2px solid var(--border);">
                    <th style="font-size:.72rem;text-transform:uppercase;color:var(--text-muted);">Konsepto (Concept)</th>
                    <th style="font-size:.72rem;text-transform:uppercase;color:var(--text-muted);">Source Language</th>
                    <th style="font-size:.72rem;text-transform:uppercase;color:var(--text-muted);">Bisaya Fallback</th>
                    <th style="font-size:.72rem;text-transform:uppercase;color:var(--text-muted);text-align:center;">Dalas (Usage)</th>
                    <th style="font-size:.72rem;text-transform:uppercase;color:var(--text-muted);">Huling Nakita</th>
                    <th class="text-end" style="font-size:.72rem;text-transform:uppercase;color:var(--text-muted);">Aksyon</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($missingConcepts as $mc): ?>
                <tr>
                    <td style="font-weight:600;color:var(--text-primary);"><?= e($mc['concept']) ?></td>
                    <td><span class="badge bg-light text-dark border"><?= e(strtoupper($mc['source_lang'])) ?></span></td>
                    <td style="color:#0284c7;font-weight:500;"><?= e($mc['bisaya_fallback'] ?? '—') ?></td>
                    <td style="text-align:center;"><span class="badge bg-primary-subtle text-primary"><?= (int) $mc['usage_count'] ?></span></td>
                    <td style="font-size:.78rem;color:var(--text-muted);"><?= e($mc['last_seen_at'] ?? '—') ?></td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-primary" style="border-radius:6px;"
                                onclick="document.querySelector('#manobo-add-form [name=\'english\']').value = <?= e(json_encode($mc['concept'])) ?>; document.querySelector('#manobo-add-form [name=\'bisaya\']').value = <?= e(json_encode($mc['bisaya_fallback'] ?? '')) ?>; document.getElementById('manobo-add-form').scrollIntoView({behavior:'smooth'});">
                            <i class="bi bi-plus-circle me-1"></i> Idagdag sa Manobo
                        </button>
                    </td>
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
