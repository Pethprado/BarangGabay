<?php
/**
 * Admin curation screen for the Manobo dataset.
 *
 * Deliberately keeps the coverage gaps visible at the top: whoever sits down
 * with a Manobo speaker should be able to see at a glance what still needs
 * collecting. See data/manobo/README.md.
 */
$entries           = $entries           ?? [];
$totalEntries      = $totalEntries      ?? count($entries);
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
$needsVerification = $needsVerification ?? [];
$trash             = $trash             ?? [];
$canRestore        = $canRestore        ?? false;

// Sort entries alphabetically by manobo headword
usort($entries, static fn (array $a, array $b): int => strcasecmp($a['manobo'] ?? '', $b['manobo'] ?? ''));

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

<!-- ── Main Dictionary Curation Module with Alpine.js ────────────────── -->
<div x-data="manoboAdminDict(<?= e(json_encode(array_values($entries))) ?>, <?= e(json_encode($search)) ?>, <?= e(json_encode($filterCategory)) ?>)">

    <!-- Search & Filter Controls Bar -->
    <div class="admin-card mb-3">
        <form @submit.prevent="onSearchChange()" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('admin_manobo.search_label')) ?></label>
                <input type="text" x-model="query" @input.debounce.250ms="onSearchChange()" class="form-control"
                       placeholder="<?= e(t('admin_manobo.search_placeholder')) ?>" style="border-radius:8px;">
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:.78rem;font-weight:600;"><?= e(t('admin_manobo.col_category')) ?></label>
                <select x-model="category" @change="onSearchChange()" class="form-select" style="border-radius:8px;">
                    <option value=""><?= e(t('admin_manobo.all_categories')) ?></option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" style="font-size:.78rem;font-weight:600;">Status</label>
                <select x-model="statusFilter" @change="onSearchChange()" class="form-select" style="border-radius:8px;">
                    <option value="">Lahat ng Status</option>
                    <option value="approved">Approved</option>
                    <option value="pending_review">Pending Review</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="button" @click="onSearchChange()" class="btn btn-barangay flex-grow-1" style="border-radius:8px;font-weight:600;">
                    <?= e(t('admin_manobo.filter')) ?>
                </button>
                <button type="button" x-show="query !== '' || category !== '' || statusFilter !== '' || selectedLetter !== defaultLetter"
                        @click="resetFilters()" x-cloak class="btn btn-outline-secondary" style="border-radius:8px;">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </form>
    </div>

    <!-- Alphabet Filter Navigation Bar & Next/Prev Controls -->
    <div class="admin-card mb-3" style="padding:14px 18px;">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
            <button type="button" @click="prevLetter()"
                    :disabled="isPrevLetterDisabled"
                    :class="isPrevLetterDisabled ? 'btn-outline-secondary opacity-50' : 'btn-outline-primary'"
                    class="btn btn-sm" style="border-radius:8px;font-weight:600;">
                <i class="bi bi-chevron-left me-1"></i>Nakalipas na Letra
            </button>

            <div class="text-center">
                <span style="font-size:.8rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);">
                    <template x-if="selectedLetter !== 'ALL'">
                        <span>Mga Salita sa Letrang <strong style="font-size:1.05rem;color:var(--brand-primary);" x-text="selectedLetter"></strong></span>
                    </template>
                    <template x-if="selectedLetter === 'ALL'">
                        <span>Lahat ng Letra</span>
                    </template>
                </span>
            </div>

            <button type="button" @click="nextLetter()"
                    :disabled="isNextLetterDisabled"
                    :class="isNextLetterDisabled ? 'btn-outline-secondary opacity-50' : 'btn-outline-primary'"
                    class="btn btn-sm" style="border-radius:8px;font-weight:600;">
                Susunod na Letra<i class="bi bi-chevron-right ms-1"></i>
            </button>
        </div>

        <!-- A-Z Alphabet Buttons -->
        <div class="d-flex flex-wrap align-items-center justify-content-center gap-1 pt-2" style="border-top:1px solid var(--border);">
            <button type="button"
                    @click="selectLetter('ALL')"
                    :aria-pressed="selectedLetter === 'ALL' ? 'true' : 'false'"
                    :class="selectedLetter === 'ALL' ? 'btn-primary' : 'btn-light'"
                    class="btn btn-sm" style="border-radius:6px;font-weight:700;padding:2px 10px;font-size:.78rem;">
                Lahat
            </button>

            <template x-for="l in alphabet" :key="l">
                <button type="button"
                        @click="selectLetter(l)"
                        :aria-pressed="selectedLetter === l ? 'true' : 'false'"
                        :disabled="!availableSet[l]"
                        :class="{
                            'btn-primary': selectedLetter === l,
                            'btn-outline-primary': selectedLetter !== l && availableSet[l],
                            'btn-light text-muted opacity-50': !availableSet[l]
                        }"
                        class="btn btn-sm"
                        style="border-radius:6px;width:32px;height:32px;padding:0;font-weight:700;font-size:.78rem;">
                    <span x-text="l"></span>
                </button>
            </template>
        </div>
    </div>

    <!-- Entry Table -->
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
                    <template x-if="paginatedEntries.length === 0">
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4" style="font-size:.85rem;">
                                <span x-show="selectedLetter !== 'ALL'">Walang nahanap na salitang Manobo sa letrang <strong class="text-primary" x-text="selectedLetter"></strong>.</span>
                                <span x-show="selectedLetter === 'ALL'">Walang nahanap na salitang tumutugma sa mga ibinigay na filter.</span>
                            </td>
                        </tr>
                    </template>

                    <template x-for="(entry, idx) in paginatedEntries" :key="entry.manobo + '_' + idx">
                        <tr x-show="editing !== entry.manobo">
                            <td style="font-weight:700;color:var(--brand-primary);white-space:nowrap;">
                                <span x-text="entry.manobo"></span>
                                <span x-show="entry.source_page" class="badge text-secondary" style="font-size:.65rem;" x-text="'p.' + entry.source_page"></span>
                            </td>
                            <td x-text="entry.english || '—'"></td>
                            <td x-text="entry.tagalog || '—'"></td>
                            <td style="color:#0369a1;font-weight:500;" x-text="entry.bisaya || '—'"></td>
                            <td>
                                <span style="font-size:.72rem;font-weight:600;background:var(--surface-muted,#f1f5f9);
                                             color:var(--text-muted);border-radius:20px;padding:2px 10px;"
                                      x-text="entry.category || 'other'">
                                </span>
                            </td>
                            <td>
                                <template x-if="entry.review_status === 'approved' && !entry.needs_review">
                                    <span class="badge bg-success" style="font-size:.68rem;">Approved</span>
                                </template>
                                <template x-if="entry.review_status !== 'approved' || entry.needs_review">
                                    <span class="badge bg-warning text-dark" style="font-size:.68rem;">Pending Review</span>
                                </template>
                            </td>
                            <td style="max-width:240px;color:var(--text-muted);font-size:.78rem;line-height:1.5;" x-text="entry.notes || ''"></td>
                            <td class="text-end" style="white-space:nowrap;">
                                <button type="button" class="btn btn-sm btn-outline-secondary" style="border-radius:6px;" @click="editing = entry.manobo">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if ($canDelete): ?>
                                <form method="POST" action="<?= e(route('admin/manobo/delete')) ?>" class="d-inline"
                                      onsubmit="return confirm('Sigurado ka bang gusto mong idelete ang salitang ito?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="manobo" :value="entry.manobo">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius:6px;">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="d-flex align-items-center justify-between p-3 border-top" style="border-color:var(--border);">
            <div style="font-size:.78rem;color:var(--text-muted);">
                Ipinapakita: <strong x-text="filteredEntries.length"></strong> kabuuang nahanap na entri
                <span x-show="selectedLetter !== 'ALL'">(Letra <strong x-text="selectedLetter"></strong>)</span>
            </div>

            <div x-show="totalPages > 1" class="d-flex align-items-center gap-2">
                <button type="button" @click="prevPage()" :disabled="currentPage === 1"
                        class="btn btn-sm btn-outline-secondary" style="border-radius:6px;">
                    <i class="bi bi-chevron-left me-1"></i>Nakaraan
                </button>

                <span style="font-size:.78rem;font-weight:600;">
                    <span x-text="currentPage"></span> / <span x-text="totalPages"></span>
                </span>

                <button type="button" @click="nextPage()" :disabled="currentPage >= totalPages"
                        class="btn btn-sm btn-outline-secondary" style="border-radius:6px;">
                    Susunod<i class="bi bi-chevron-right ms-1"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function manoboAdminDict(rawEntries, initialQuery, initialCat) {
        var alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
        var availableSet = {};

        (rawEntries || []).forEach(function(e) {
            var word = (e.manobo || '').trim();
            if (word.length > 0) {
                var first = word.charAt(0).toUpperCase();
                availableSet[first] = (availableSet[first] || 0) + 1;
            }
        });

        var firstAvailable = 'A';
        for (var i = 0; i < alphabet.length; i++) {
            if (availableSet[alphabet[i]]) {
                firstAvailable = alphabet[i];
                break;
            }
        }

        var defaultLetter = initialQuery ? 'ALL' : firstAvailable;

        return {
            allEntries: rawEntries || [],
            query: (initialQuery || '').trim(),
            category: (initialCat || '').trim(),
            statusFilter: '',
            selectedLetter: defaultLetter,
            defaultLetter: defaultLetter,
            currentPage: 1,
            pageSize: 20,
            alphabet: alphabet,
            availableSet: availableSet,

            get filteredEntries() {
                var q = (this.query || '').trim().toLowerCase();
                var cat = (this.category || '').trim().toLowerCase();
                var stat = this.statusFilter;
                var letter = this.selectedLetter;

                return this.allEntries.filter(function(e) {
                    var mWord = (e.manobo || '').trim();
                    var mWordLower = mWord.toLowerCase();

                    // Letter filter
                    if (letter !== 'ALL' && letter !== '') {
                        if (!mWordLower.startsWith(letter.toLowerCase())) {
                            return false;
                        }
                    }

                    // Category filter
                    if (cat !== '' && (e.category || '').trim().toLowerCase() !== cat) {
                        return false;
                    }

                    // Status filter
                    if (stat !== '') {
                        var isApproved = (e.review_status || '') === 'approved' && !e.needs_review;
                        if (stat === 'approved' && !isApproved) return false;
                        if (stat === 'pending_review' && isApproved) return false;
                    }

                    // Search query filter (prefix prioritized)
                    if (q !== '') {
                        var inManobo = mWordLower.indexOf(q) !== -1;
                        var inEng = (e.english || '').toLowerCase().indexOf(q) !== -1;
                        var inTag = (e.tagalog || '').toLowerCase().indexOf(q) !== -1;
                        var inBis = (e.bisaya || '').toLowerCase().indexOf(q) !== -1;
                        if (!inManobo && !inEng && !inTag && !inBis) {
                            return false;
                        }
                    }

                    return true;
                }).sort(function(a, b) {
                    var aM = (a.manobo || '').trim().toLowerCase();
                    var bM = (b.manobo || '').trim().toLowerCase();
                    if (q !== '') {
                        var aStartsWith = aM.startsWith(q);
                        var bStartsWith = bM.startsWith(q);
                        if (aStartsWith && !bStartsWith) return -1;
                        if (!aStartsWith && bStartsWith) return 1;
                    }
                    return aM.localeCompare(bM);
                });
            },

            get paginatedEntries() {
                var start = (this.currentPage - 1) * this.pageSize;
                return this.filteredEntries.slice(start, start + this.pageSize);
            },

            get totalPages() {
                return Math.max(1, Math.ceil(this.filteredEntries.length / this.pageSize));
            },

            get availableLettersList() {
                var self = this;
                return this.alphabet.filter(function(l) { return !!self.availableSet[l]; });
            },

            get isPrevLetterDisabled() {
                if (this.selectedLetter === 'ALL') return true;
                var list = this.availableLettersList;
                if (list.length === 0) return true;
                var idx = list.indexOf(this.selectedLetter);
                return idx <= 0;
            },

            get isNextLetterDisabled() {
                if (this.selectedLetter === 'ALL') return true;
                var list = this.availableLettersList;
                if (list.length === 0) return true;
                var idx = list.indexOf(this.selectedLetter);
                return idx < 0 || idx >= list.length - 1;
            },

            selectLetter(letter) {
                this.selectedLetter = letter;
                this.currentPage = 1;
            },

            prevLetter() {
                var list = this.availableLettersList;
                if (list.length === 0) return;
                var idx = list.indexOf(this.selectedLetter);
                if (idx > 0) {
                    this.selectLetter(list[idx - 1]);
                }
            },

            nextLetter() {
                var list = this.availableLettersList;
                if (list.length === 0) return;
                var idx = list.indexOf(this.selectedLetter);
                if (idx >= 0 && idx < list.length - 1) {
                    this.selectLetter(list[idx + 1]);
                }
            },

            onSearchChange() {
                this.currentPage = 1;
            },

            resetFilters() {
                this.query = '';
                this.category = '';
                this.statusFilter = '';
                this.selectedLetter = this.defaultLetter;
                this.currentPage = 1;
            },

            prevPage() {
                if (this.currentPage > 1) this.currentPage--;
            },

            nextPage() {
                if (this.currentPage < this.totalPages) this.currentPage++;
            }
        };
    }
</script>

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
