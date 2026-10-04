<?php
/**
 * Voice Training & Dataset Management Dashboard
 * BARANGGABAY Admin Interface
 * 
 * Fully responsive, high-contrast, theme-aware layout for both Light Mode
 * and Dark Mode (WCAG AA compliant). Prevents text overflow via min-width: 0
 * and safe wrap rules.
 */
$title = $title ?? 'Voice Training & AI Dataset Hub';
ob_start();
?>

<style>
/* ==========================================================================
   Voice Training & AI Dataset Management — Scoped Design System & Themes
   Ensures 100% text containment, zero overflow, and WCAG AA contrast
   in both Light Mode and Dark Mode.
   ========================================================================== */

#voice-training-app,
#voice-training-app *,
.vt-modal,
.vt-modal * {
    box-sizing: border-box;
}

#voice-training-app {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    overflow-x: hidden;
    color: var(--text-primary, #1a1512);
}

/* ── Typography & Wrapping Helpers ───────────────────────────────────── */
.vt-text-primary {
    color: var(--text-primary, #1a1512) !important;
}
.vt-text-secondary {
    color: var(--text-secondary, #4a3f38) !important;
}
.vt-text-muted {
    color: var(--text-muted, #6b5d52) !important;
}
.vt-wrap {
    min-width: 0;
    max-width: 100%;
    overflow-wrap: anywhere;
    word-break: break-word;
    white-space: normal;
}

/* ── Common Card Component ───────────────────────────────────────────── */
.vt-card {
    background-color: var(--surface-card, #fffdf8);
    border: 1px solid var(--border, #e4d7c2);
    border-radius: 16px;
    padding: 22px;
    min-width: 0;
    max-width: 100%;
    display: flex;
    flex-direction: column;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    position: relative;
    transition: box-shadow 0.2s ease, border-color 0.2s ease;
}

@media (max-width: 640px) {
    .vt-card {
        padding: 16px;
    }
}

/* ── Badges with High Contrast in Both Themes ────────────────────────── */
.vt-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    padding: 3px 9px;
    border-radius: 999px;
    line-height: 1.25;
    min-width: 0;
    white-space: normal;
    text-align: center;
}
.vt-badge-gold {
    background-color: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}
.vt-badge-green {
    background-color: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}
.vt-badge-blue {
    background-color: #dbeafe;
    color: #1e40af;
    border: 1px solid #bfdbfe;
}
.vt-badge-neutral {
    background-color: #f1f5f9;
    color: #334155;
    border: 1px solid #e2e8f0;
}
.vt-badge-red {
    background-color: #ffe4e6;
    color: #9f1239;
    border: 1px solid #fecdd3;
}

/* Dark mode badges */
:root[data-theme="dark"] .vt-badge-gold {
    background-color: rgba(245, 158, 11, 0.25);
    color: #fcd34d;
    border-color: rgba(245, 158, 11, 0.5);
}
:root[data-theme="dark"] .vt-badge-green {
    background-color: rgba(34, 197, 94, 0.25);
    color: #86efac;
    border-color: rgba(34, 197, 94, 0.5);
}
:root[data-theme="dark"] .vt-badge-blue {
    background-color: rgba(59, 130, 246, 0.25);
    color: #93c5fd;
    border-color: rgba(59, 130, 246, 0.5);
}
:root[data-theme="dark"] .vt-badge-neutral {
    background-color: rgba(148, 163, 184, 0.25);
    color: #cbd5e1;
    border-color: rgba(148, 163, 184, 0.45);
}
:root[data-theme="dark"] .vt-badge-red {
    background-color: rgba(244, 63, 94, 0.25);
    color: #fda4af;
    border-color: rgba(244, 63, 94, 0.5);
}

/* ── Button Styles ───────────────────────────────────────────────────── */
.btn-vt-primary {
    background-color: #1b6a38 !important;
    color: #ffffff !important;
    border: 1px solid #14532d !important;
    font-weight: 700;
    font-size: 0.825rem;
    padding: 8px 16px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
    transition: background-color 0.15s ease, filter 0.15s ease;
    text-decoration: none;
    white-space: normal;
    min-width: 0;
}
.btn-vt-primary:hover,
.btn-vt-primary:focus {
    background-color: #14532d !important;
    color: #ffffff !important;
}
.btn-vt-secondary {
    background-color: var(--surface-muted, #f1e6d2);
    color: var(--text-primary, #1a1512) !important;
    border: 1px solid var(--border, #e4d7c2);
    font-weight: 700;
    font-size: 0.825rem;
    padding: 8px 14px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    cursor: pointer;
    transition: all 0.15s ease;
    text-decoration: none;
    white-space: normal;
    min-width: 0;
}
.btn-vt-secondary:hover,
.btn-vt-secondary:focus {
    background-color: var(--border, #e4d7c2);
    color: var(--text-primary, #1a1512) !important;
}
.btn-vt-outline {
    background-color: var(--surface-card, #fffdf8);
    color: var(--text-primary, #1a1512);
    border: 1px solid var(--border, #e4d7c2);
    font-weight: 600;
    font-size: 0.825rem;
    padding: 8px 14px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: normal;
    min-width: 0;
}
.btn-vt-outline:hover,
.btn-vt-outline:focus {
    background-color: var(--surface-muted, #f1e6d2);
    color: var(--text-primary, #1a1512);
}
.btn-vt-action {
    background-color: transparent;
    color: var(--brand-secondary, #2f5d3a) !important;
    border: 1.5px solid var(--brand-secondary, #2f5d3a) !important;
    font-weight: 700;
    font-size: 0.825rem;
    padding: 8px 12px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: normal;
    min-width: 0;
}
.btn-vt-action:hover,
.btn-vt-action:focus {
    background-color: var(--brand-secondary, #2f5d3a) !important;
    color: #ffffff !important;
}
:root[data-theme="dark"] .btn-vt-action {
    color: #86efac !important;
    border-color: #86efac !important;
}
:root[data-theme="dark"] .btn-vt-action:hover,
:root[data-theme="dark"] .btn-vt-action:focus {
    background-color: #86efac !important;
    color: #14532d !important;
}
.btn-vt-gold {
    background-color: #b45309 !important;
    color: #ffffff !important;
    border: 1px solid #92400e !important;
    font-weight: 700;
    font-size: 0.75rem;
    padding: 5px 10px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    cursor: pointer;
    text-decoration: none;
}
.btn-vt-gold:hover {
    background-color: #92400e !important;
    color: #ffffff !important;
}

/* ── Top Summary Grid ────────────────────────────────────────────────── */
.vt-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 1024px) {
    .vt-stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 576px) {
    .vt-stats-grid {
        grid-template-columns: 1fr;
    }
}

.vt-stat-card {
    background-color: var(--surface-card, #fffdf8);
    border: 1px solid var(--border, #e4d7c2);
    border-radius: 14px;
    padding: 16px 18px;
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 112px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}
.vt-stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 6px;
    min-width: 0;
}
.vt-stat-title {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted, #6b5d52);
    min-width: 0;
    overflow-wrap: anywhere;
}
.vt-stat-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1rem;
}
.vt-stat-value-row {
    display: flex;
    align-items: baseline;
    gap: 8px;
    flex-wrap: wrap;
    min-width: 0;
    margin-top: 2px;
}
.vt-stat-value {
    font-size: 1.55rem;
    font-weight: 800;
    line-height: 1.15;
    color: var(--text-primary, #1a1512);
    min-width: 0;
    overflow-wrap: anywhere;
}
.vt-stat-sub {
    font-size: 0.72rem;
    color: var(--text-muted, #6b5d52);
    margin-top: 4px;
    line-height: 1.35;
    min-width: 0;
    overflow-wrap: anywhere;
}

/* ── 3-Column Language Profile Cards ─────────────────────────────────── */
.vt-profiles-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1.25rem;
    margin-bottom: 1.5rem;
    align-items: stretch;
}
@media (max-width: 992px) {
    .vt-profiles-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 640px) {
    .vt-profiles-grid {
        grid-template-columns: 1fr;
    }
}

.vt-profile-card {
    background-color: var(--surface-card, #fffdf8);
    border: 1px solid var(--border, #e4d7c2);
    border-radius: 16px;
    padding: 22px;
    display: flex;
    flex-direction: column;
    min-width: 0;
    position: relative;
    overflow: hidden;
    height: 100%;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.vt-profile-card.vt-featured-manobo {
    border-color: #c8992e;
}
.vt-accent-bar {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}
.vt-accent-bar-manobo {
    background: linear-gradient(90deg, #c8992e, #1a6b3a);
}
.vt-accent-bar-filipino {
    background: #1a6b3a;
}
.vt-accent-bar-english {
    background: #475569;
}

.vt-profile-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 14px;
    min-width: 0;
}
.vt-lang-title {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--text-primary, #1a1512);
    margin: 4px 0 0 0;
    line-height: 1.2;
    min-width: 0;
    overflow-wrap: anywhere;
}
.vt-lang-icon-box {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.2rem;
}

.vt-active-box {
    background-color: var(--surface-muted, #f1e6d2);
    border: 1px solid var(--border, #e4d7c2);
    border-radius: 12px;
    padding: 12px 14px;
    margin-bottom: 14px;
    min-width: 0;
}
.vt-active-label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted, #6b5d52);
    display: block;
    margin-bottom: 4px;
    min-width: 0;
    overflow-wrap: anywhere;
}
.vt-active-name {
    font-size: 0.92rem;
    font-weight: 700;
    line-height: 1.35;
    color: var(--text-primary, #1a1512);
    display: flex;
    align-items: flex-start;
    gap: 8px;
    min-width: 0;
    overflow-wrap: anywhere;
    word-break: break-word;
}
.vt-active-provider {
    font-size: 0.72rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    color: var(--text-secondary, #4a3f38);
    display: block;
    margin-top: 6px;
    min-width: 0;
    overflow-wrap: anywhere;
    word-break: break-all;
}

.vt-stat-pills {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    margin-bottom: 14px;
    min-width: 0;
}
.vt-stat-pills-2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.vt-stat-pill {
    background-color: var(--surface-card, #fffdf8);
    border: 1px solid var(--border, #e4d7c2);
    border-radius: 10px;
    padding: 8px 4px;
    text-align: center;
    min-width: 0;
}
.vt-stat-pill-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text-muted, #6b5d52);
    display: block;
    min-width: 0;
    overflow-wrap: anywhere;
}
.vt-stat-pill-val {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--text-primary, #1a1512);
    display: block;
    line-height: 1.2;
    margin-top: 2px;
}
.vt-stat-pill-val-success {
    color: #15803d;
}
:root[data-theme="dark"] .vt-stat-pill-val-success {
    color: #86efac;
}
.vt-stat-pill-val-warning {
    color: #b45309;
}
:root[data-theme="dark"] .vt-stat-pill-val-warning {
    color: #fcd34d;
}

.vt-coverage-block {
    margin-bottom: 14px;
    min-width: 0;
}
.vt-coverage-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--text-primary, #1a1512);
    margin-bottom: 4px;
    min-width: 0;
}
.vt-progress-track {
    height: 8px;
    background-color: var(--surface-muted, #f1e6d2);
    border-radius: 999px;
    overflow: hidden;
    position: relative;
    width: 100%;
}
.vt-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #c8992e, #1a6b3a);
    border-radius: 999px;
    transition: width 0.3s ease;
}
.vt-coverage-sub {
    font-size: 0.72rem;
    color: var(--text-muted, #6b5d52);
    margin-top: 4px;
    display: block;
    min-width: 0;
    overflow-wrap: anywhere;
}

.vt-card-actions {
    display: flex;
    gap: 8px;
    margin-top: auto;
    padding-top: 10px;
    flex-wrap: wrap;
    min-width: 0;
}

/* ── 2-Column Split: Tester & Queue ──────────────────────────────────── */
.vt-split-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 992px) {
    .vt-split-grid {
        grid-template-columns: 1fr;
    }
}

/* ── Interactive Voice Tester Controls ───────────────────────────────── */
.vt-lang-selector {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 6px;
    margin-bottom: 12px;
    min-width: 0;
}
.vt-lang-btn {
    padding: 7px 8px;
    font-size: 0.75rem;
    font-weight: 700;
    border-radius: 8px;
    border: 1px solid var(--border, #e4d7c2);
    background-color: var(--surface-card, #fffdf8);
    color: var(--text-secondary, #4a3f38);
    text-align: center;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: normal;
    min-width: 0;
}
.btn-check:checked + .vt-lang-btn {
    background-color: #1a6b3a !important;
    color: #ffffff !important;
    border-color: #14532d !important;
}
:root[data-theme="dark"] .btn-check:checked + .vt-lang-btn {
    background-color: #22c55e !important;
    color: #052e16 !important;
    border-color: #22c55e !important;
}

.vt-input {
    background-color: var(--surface-input, #fdf9ef) !important;
    color: var(--text-primary, #1a1512) !important;
    border: 1px solid var(--border, #e4d7c2) !important;
    border-radius: 10px !important;
    padding: 8px 12px !important;
    font-size: 0.875rem !important;
    width: 100%;
}
.vt-input:focus {
    border-color: #c8992e !important;
    box-shadow: 0 0 0 3px rgba(200, 153, 46, 0.25) !important;
    outline: none !important;
}
.vt-select {
    background-color: var(--surface-input, #fdf9ef) !important;
    color: var(--text-primary, #1a1512) !important;
    border: 1px solid var(--border, #e4d7c2) !important;
    border-radius: 10px !important;
    padding: 7px 10px !important;
    font-size: 0.825rem !important;
    font-weight: 600;
}
:root[data-theme="dark"] .vt-select option {
    background-color: var(--surface-card, #241b18);
    color: var(--text-primary, #fdf8ee);
}

.vt-chip {
    background-color: var(--surface-muted, #f1e6d2);
    color: var(--text-secondary, #4a3f38);
    border: 1px solid var(--border, #e4d7c2);
    border-radius: 6px;
    padding: 2px 8px;
    font-size: 0.72rem;
    cursor: pointer;
    transition: all 0.15s ease;
    font-weight: 500;
}
.vt-chip:hover {
    background-color: #c8992e;
    color: #ffffff;
}

.vt-result-box {
    background-color: var(--surface-muted, #f1e6d2);
    border: 1px solid var(--border, #e4d7c2);
    border-radius: 12px;
    padding: 14px;
    margin-top: 14px;
    min-width: 0;
}

/* ── Queue Table ─────────────────────────────────────────────────────── */
.vt-table {
    width: 100%;
    margin-bottom: 0;
    color: var(--text-primary, #1a1512);
    vertical-align: middle;
}
.vt-table th {
    background-color: var(--surface-muted, #f1e6d2);
    color: var(--text-muted, #6b5d52);
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 10px 12px;
    border-bottom: 1px solid var(--border, #e4d7c2);
}
.vt-table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--border, #e4d7c2);
    color: var(--text-primary, #1a1512);
    font-size: 0.825rem;
    overflow-wrap: anywhere;
    word-break: break-word;
}
.vt-table tr:hover td {
    background-color: var(--surface-muted, #f1e6d2);
}

/* ── Dataset Explorer Table Card ─────────────────────────────────────── */
.vt-explorer-card {
    background-color: var(--surface-card, #fffdf8);
    border: 1px solid var(--border, #e4d7c2);
    border-radius: 16px;
    margin-bottom: 1.5rem;
    overflow: hidden;
    min-width: 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.vt-explorer-header {
    padding: 20px 22px 14px 22px;
    border-bottom: 1px solid var(--border, #e4d7c2);
    min-width: 0;
}

/* ── Form Controls & Modals ──────────────────────────────────────────── */
.vt-modal-content {
    background-color: var(--surface-card, #fffdf8) !important;
    color: var(--text-primary, #1a1512) !important;
    border: 1px solid var(--border, #e4d7c2) !important;
    border-radius: 16px !important;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.1) !important;
}
.vt-modal-header {
    border-bottom: 1px solid var(--border, #e4d7c2) !important;
    padding: 18px 22px !important;
}
.vt-modal-body {
    padding: 22px !important;
}
.vt-modal-footer {
    border-top: 1px solid var(--border, #e4d7c2) !important;
    padding: 14px 22px !important;
}

/* Dark mode overrides for modal and cards */
:root[data-theme="dark"] .vt-modal-content {
    background-color: var(--surface-card, #241b18) !important;
    color: var(--text-primary, #fdf8ee) !important;
    border-color: var(--border, #3a2a22) !important;
}
:root[data-theme="dark"] .vt-modal-header,
:root[data-theme="dark"] .vt-modal-footer {
    border-color: var(--border, #3a2a22) !important;
}
</style>

<?php
/** @var array $stats @var array $activeProfiles @var int $activeProfileCount @var array $coverage
 *  @var array $usage @var array $health @var array $filters @var array $samples @var array $pagination
 *  @var array $missingPronunciations @var int $missingTotal @var array $dictionaryEntries
 *  @var array $recentPosts @var bool $isSuperadmin */
$langLabels   = ['msm' => 'Manobo', 'en' => 'English', 'fil' => 'Filipino', 'ceb' => 'Bisaya'];
$langBadge    = ['msm' => 'vt-badge-gold', 'en' => 'vt-badge-neutral', 'fil' => 'vt-badge-blue', 'ceb' => 'vt-badge-green'];
$statusBadge  = ['approved' => 'vt-badge-green', 'pending' => 'vt-badge-gold', 'draft' => 'vt-badge-gold', 'rejected' => 'vt-badge-red'];
$totalAll     = array_sum(array_column($stats, 'total'));
$approvedAll  = array_sum(array_column($stats, 'approved'));
$pendingAll   = array_sum(array_column($stats, 'pending'));
// Missing words from published content, top 25 per language, most-used first.
$usageMissing   = [];
$usageTruncated = false;
foreach ($reports as $rLang => $rep) {
    $langMissing = array_values(array_filter($rep['words'], static fn (array $w): bool => !$w['recorded']));
    $usageTruncated = $usageTruncated || count($langMissing) > 25;
    array_push($usageMissing, ...array_slice($langMissing, 0, 25));
}
usort($usageMissing, static fn (array $a, array $b): int => $b['priority'] <=> $a['priority']);
$returnTo     = 'admin/voice-training' . (($q = http_build_query(array_filter([
    'language' => $filters['language'], 'status' => $filters['status'], 'search' => $filters['search'],
    'sort' => $filters['sort'] !== 'newest' ? $filters['sort'] : '', 'page' => $pagination['page'] > 1 ? $pagination['page'] : '',
]))) !== '' ? '?' . $q : '');
// Quick-test chips come from the dictionary (real Manobo phrases), not invented text.
$chipPhrases = [];
foreach ($dictionaryEntries as $entry) {
    if (str_contains(trim($entry['manobo']), ' ')) {
        $chipPhrases[] = $entry;
    }
    if (count($chipPhrases) >= 3) {
        break;
    }
}
$pageUrl = static fn (array $extra): string => route('admin/voice-training?' . http_build_query(array_filter(array_merge([
    'language' => $filters['language'], 'status' => $filters['status'], 'search' => $filters['search'],
    'sort' => $filters['sort'] !== 'newest' ? $filters['sort'] : '',
], $extra), static fn ($v) => $v !== '' && $v !== null)));
?>
<div class="w-100" id="voice-training-app">
    <!-- ── 1. Page Header ─────────────────────────────────────────────────── -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div class="vt-wrap" style="max-width: 820px;">
            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                <span class="vt-badge vt-badge-gold"><i class="bi bi-cpu-fill me-1"></i>Language AI & Voice</span>
                <span class="vt-badge vt-badge-green"><i class="bi bi-circle-fill me-1" style="font-size: 7px;"></i><?= $approvedAll ?> approved recording<?= $approvedAll === 1 ? '' : 's' ?> live</span>
            </div>
            <h1 class="h3 font-extrabold vt-text-primary mb-1 d-flex align-items-center gap-2 flex-wrap">
                <i class="bi bi-mic-fill" style="color: #c8992e;"></i>
                <span>Voice Training & AI Dataset Management</span>
            </h1>
            <p class="vt-text-secondary mb-0 text-sm vt-wrap" style="line-height: 1.45;">
                Record native pronunciations, approve them, and the Resident Voice Reader uses them automatically. Manobo first.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="dropdown">
                <button class="btn-vt-outline dropdown-toggle font-semibold" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-download me-1"></i> Export Dataset
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg rounded-xl border-0 p-2">
                    <li><a class="dropdown-item rounded-lg py-1.5 text-xs font-medium" href="<?= e(route('admin/voice-training/export?format=csv')) ?>"><i class="bi bi-filetype-csv me-2 text-success fs-6"></i>All samples — CSV</a></li>
                    <li><a class="dropdown-item rounded-lg py-1.5 text-xs font-medium" href="<?= e(route('admin/voice-training/export?format=json')) ?>"><i class="bi bi-filetype-json me-2 text-info fs-6"></i>All samples — JSON</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item rounded-lg py-1.5 text-xs font-medium" href="<?= e(route('admin/voice-training/export?format=csv&language=msm&status=approved')) ?>"><i class="bi bi-check2-circle me-2 text-success fs-6"></i>Approved Manobo — CSV</a></li>
                </ul>
            </div>
            <button type="button" class="btn-vt-primary" onclick="openRecorder({})">
                <i class="bi bi-plus-circle-fill fs-6"></i> Add Voice Sample
            </button>
        </div>
    </div>

    <div id="vtPageAlert" class="alert d-none" role="status" aria-live="polite"></div>

    <!-- ── 2. Summary cards (all from the database) ───────────────────────── -->
    <div class="vt-stats-grid">
        <div class="vt-stat-card">
            <div class="vt-stat-top">
                <span class="vt-stat-title">Active Profiles</span>
                <div class="vt-stat-icon" style="background-color: rgba(200, 153, 46, 0.15); color: #c8992e;"><i class="bi bi-soundwave"></i></div>
            </div>
            <div class="vt-stat-value-row">
                <span class="vt-stat-value"><?= (int) $activeProfileCount ?> of <?= count($langLabels) ?></span>
                <?php if ($activeProfileCount === count($langLabels)): ?>
                    <span class="vt-badge vt-badge-green"><i class="bi bi-check-circle-fill me-1"></i>Ready</span>
                <?php else: ?>
                    <span class="vt-badge vt-badge-red">Check profiles</span>
                <?php endif; ?>
            </div>
            <span class="vt-stat-sub">Manobo, English, Filipino, Bisaya</span>
        </div>

        <div class="vt-stat-card">
            <div class="vt-stat-top">
                <span class="vt-stat-title">Manobo Coverage</span>
                <div class="vt-stat-icon" style="background-color: rgba(22, 101, 52, 0.15); color: #166534;"><i class="bi bi-journal-check"></i></div>
            </div>
            <div class="vt-stat-value-row">
                <span class="vt-stat-value"><?= number_format((float) $coverage['coverage_percentage'], 1) ?>%</span>
                <span class="vt-stat-sub mb-0"><?= (int) $coverage['words_with_audio'] ?>/<?= (int) $coverage['total_dictionary_words'] ?> dictionary entries</span>
            </div>
            <div class="vt-progress-track mt-2" role="progressbar" aria-label="Manobo dictionary coverage" aria-valuenow="<?= (float) $coverage['coverage_percentage'] ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="vt-progress-fill" style="width: <?= (float) $coverage['coverage_percentage'] ?>%;"></div>
            </div>
        </div>

        <div class="vt-stat-card">
            <div class="vt-stat-top">
                <span class="vt-stat-title">Total Samples</span>
                <div class="vt-stat-icon" style="background-color: rgba(30, 64, 175, 0.15); color: #1e40af;"><i class="bi bi-database-fill-check"></i></div>
            </div>
            <div class="vt-stat-value-row">
                <span class="vt-stat-value"><?= (int) $totalAll ?></span>
                <span class="vt-badge vt-badge-green"><?= (int) $approvedAll ?> Approved</span>
                <?php if ($pendingAll > 0): ?><span class="vt-badge vt-badge-gold"><?= (int) $pendingAll ?> Pending</span><?php endif; ?>
            </div>
            <span class="vt-stat-sub">Recordings in the dataset, all languages</span>
        </div>

        <div class="vt-stat-card">
            <div class="vt-stat-top">
                <span class="vt-stat-title">Missing Samples</span>
                <div class="vt-stat-icon" style="background-color: rgba(159, 18, 57, 0.15); color: #9f1239;"><i class="bi bi-mic-mute-fill"></i></div>
            </div>
            <div class="vt-stat-value-row">
                <span class="vt-stat-value"><?= (int) $coverage['missing_audio'] ?></span>
                <span class="vt-badge vt-badge-red">Dictionary entries</span>
            </div>
            <span class="vt-stat-sub"><?= (int) $usage['used_missing'] ?> of them<?= $usage['used_missing'] === 1 ? ' is' : ' are' ?> words used in posts residents can see</span>
        </div>
    </div>

    <!-- ── 3. Language profile cards ──────────────────────────────────────── -->
    <div class="vt-profiles-grid">
        <?php foreach (['msm' => ['Manobo (MN)', 'PRIMARY LANGUAGE', 'vt-badge-gold', 'bi-mic-fill', 'manobo'],
                        'fil' => ['Filipino (FIL)', 'NATIONAL', 'vt-badge-blue', 'bi-translate', 'filipino'],
                        'en'  => ['English (EN)', 'INTERNATIONAL', 'vt-badge-neutral', 'bi-globe', 'english'],
                        'ceb' => ['Bisaya (CEB)', 'REGIONAL', 'vt-badge-green', 'bi-chat-quote', 'filipino']] as $lk => [$lTitle, $lTag, $lTagClass, $lIcon, $lAccent]):
            $prof = $activeProfiles[$lk];
            $profActive = !empty($prof['id']) && (int) $prof['is_active'] === 1;
        ?>
        <div class="vt-profile-card <?= $lk === 'msm' ? 'vt-featured-manobo' : '' ?>">
            <div class="vt-accent-bar vt-accent-bar-<?= $lAccent ?>"></div>
            <div class="vt-profile-header">
                <div class="vt-wrap">
                    <div class="d-flex align-items-center gap-1.5 mb-1.5 flex-wrap">
                        <span class="vt-badge <?= $lTagClass ?>"><?= $lTag ?></span>
                        <?php if ($profActive): ?>
                            <span class="vt-badge vt-badge-green"><i class="bi bi-check-circle-fill me-1"></i>ACTIVE</span>
                        <?php else: ?>
                            <span class="vt-badge vt-badge-red">INACTIVE</span>
                        <?php endif; ?>
                    </div>
                    <h2 class="vt-lang-title"><?= $lTitle ?></h2>
                </div>
                <div class="vt-lang-icon-box" style="<?= $lk === 'msm' ? 'background-color:#c8992e;color:#fff;' : 'background-color: rgba(26,107,58,.15); color:#1a6b3a;' ?>"><i class="bi <?= $lIcon ?>"></i></div>
            </div>

            <div class="vt-active-box">
                <span class="vt-active-label">Active Profile Name</span>
                <div class="vt-active-name">
                    <i class="bi bi-person-bounding-box" style="color: #c8992e; font-size: 1.1rem; flex-shrink: 0; margin-top: 2px;"></i>
                    <span class="vt-wrap"><?= e((string) ($prof['profile_name'] ?? $prof['name'] ?? '')) ?></span>
                </div>
                <span class="vt-active-provider">
                    Plays: approved recordings<?= $fallbackMode === 'recorded_tts' ? ', device voice for unrecorded words' : ' only' ?><?= $lk === 'ceb' ? ' (Bisaya words inside Manobo text)' : '' ?>
                    · rate <?= e((string) ($prof['speaking_rate'] ?? '0.95')) ?>x
                </span>
            </div>

            <div class="vt-stat-pills">
                <div class="vt-stat-pill"><span class="vt-stat-pill-label">Samples</span><span class="vt-stat-pill-val"><?= (int) $stats[$lk]['total'] ?></span></div>
                <div class="vt-stat-pill"><span class="vt-stat-pill-label">Approved</span><span class="vt-stat-pill-val vt-stat-pill-val-success"><?= (int) $stats[$lk]['approved'] ?></span></div>
                <div class="vt-stat-pill"><span class="vt-stat-pill-label">Pending</span><span class="vt-stat-pill-val vt-stat-pill-val-warning"><?= (int) $stats[$lk]['pending'] ?></span></div>
            </div>

            <?php if ($lk === 'msm'): ?>
            <div class="vt-coverage-block">
                <div class="vt-coverage-top">
                    <span>Used-in-posts coverage</span>
                    <span style="color: #c8992e;"><?= number_format((float) $usage['usage_coverage'], 1) ?>%</span>
                </div>
                <div class="vt-progress-track"><div class="vt-progress-fill" style="width: <?= (float) $usage['usage_coverage'] ?>%;"></div></div>
                <span class="vt-coverage-sub"><?= (int) $usage['used_recorded'] ?> of <?= (int) $usage['used_words'] ?> Manobo words in visible posts have a recording</span>
            </div>
            <?php endif; ?>

            <div class="vt-card-actions">
                <button type="button" class="btn-vt-secondary" data-bs-toggle="modal" data-bs-target="#profileModal_<?= $lk ?>" style="flex: 1 1 auto;">
                    <i class="bi bi-gear-fill me-1"></i> Configure Profile
                </button>
                <a class="btn-vt-action" href="<?= e($pageUrl(['language' => $lk, 'status' => '', 'page' => ''])) ?>#samples" style="flex: 0 1 auto;">
                    <i class="bi bi-collection-play me-1"></i> Samples
                </a>
                <button type="button" onclick="quickTestLanguage('<?= $lk ?>')" class="btn-vt-action" style="flex: 0 1 auto;">
                    <i class="bi bi-volume-up-fill me-1"></i> Test
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── 4. Tester + missing dictionary entries ─────────────────────────── -->
    <div class="vt-split-grid">
        <div class="vt-card" id="testVoiceCard">
            <div class="d-flex align-items-center justify-content-between mb-1 gap-2 flex-wrap">
                <h3 class="h5 font-extrabold vt-text-primary mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-play-circle-fill" style="color: #c8992e;"></i><span>Interactive Voice Tester</span>
                </h3>
                <span class="vt-badge vt-badge-gold">Same output residents hear</span>
            </div>
            <p class="vt-text-muted text-xs mb-3">Uses the Resident Voice Reader pipeline: approved recordings (longest phrase first), device voice for the rest.</p>

            <form id="testVoiceForm" onsubmit="runVoiceTest(event)">
                <fieldset class="mb-3">
                    <legend class="form-label text-xs font-bold vt-text-primary mb-1.5" style="font-size:.75rem;">Select Language</legend>
                    <div class="vt-lang-selector">
                        <?php foreach ($langLabels as $lk => $ll): ?>
                            <input type="radio" class="btn-check" name="test_language" id="lang_<?= $lk ?>" value="<?= $lk ?>" <?= $lk === 'msm' ? 'checked' : '' ?>>
                            <label class="vt-lang-btn" for="lang_<?= $lk ?>"><?= $ll ?> (<?= strtoupper($lk === 'msm' ? 'mn' : $lk) ?>)</label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-1">
                        <label for="test_text" class="form-label text-xs font-bold vt-text-primary mb-0">Text to Speak</label>
                        <div class="d-flex gap-1 flex-wrap">
                            <?php foreach ($chipPhrases as $chip): ?>
                                <button type="button" class="vt-chip" title="<?= e($chip['translation']) ?>" onclick="setTestText('msm', <?= e(json_encode($chip['manobo'])) ?>)">+ <?= e($chip['manobo']) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <input type="text" id="test_text" class="vt-input" placeholder="Type a word, phrase, or sentence" maxlength="2000">
                    <div id="testTextValidation" class="text-xs text-danger mt-1 d-none" role="alert">Type some text to preview.</div>
                </div>

                <div class="d-flex align-items-center gap-2 mt-auto flex-wrap">
                    <button type="submit" id="btnTestVoice" class="btn-vt-primary"><i class="bi bi-volume-up-fill fs-6"></i> Play Voice Preview</button>
                    <button type="button" id="btnTestStop" class="btn-vt-outline d-none" onclick="stopVoiceTest()"><i class="bi bi-stop-fill"></i> Stop</button>
                    <span id="testVoiceSpinner" class="spinner-border spinner-border-sm text-success d-none" role="status"><span class="visually-hidden">Loading</span></span>
                </div>
            </form>

            <div id="testResultBox" class="vt-result-box d-none" aria-live="polite">
                <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                    <span id="testSourceBadge" class="vt-badge vt-badge-green"></span>
                    <span id="testProfileLabel" class="text-xs font-mono vt-text-secondary"></span>
                </div>
                <p id="testMessage" class="text-xs vt-text-primary mb-2 font-medium vt-wrap"></p>
                <div id="testSegments" class="d-flex flex-wrap gap-1"></div>
            </div>
        </div>

        <div class="vt-card" id="missingCard">
            <div class="d-flex align-items-center justify-content-between mb-1 gap-2 flex-wrap">
                <h3 class="h5 font-extrabold vt-text-primary mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-mic-mute-fill" style="color: #9f1239;"></i><span>Missing Pronunciations</span>
                </h3>
                <span class="vt-badge vt-badge-red" id="missingCountBadge"></span>
            </div>
            <div class="vt-lang-selector mb-2" role="tablist" aria-label="Missing pronunciations language">
                <?php foreach ($langLabels as $lk => $ll): ?>
                    <input type="radio" class="btn-check" name="missing_tab" id="mtab_<?= $lk ?>" value="<?= $lk ?>" <?= $lk === 'msm' ? 'checked' : '' ?> onchange="renderMissing()">
                    <label class="vt-lang-btn" for="mtab_<?= $lk ?>" role="tab"><?= $ll ?> <span class="vt-text-muted">(<?= count($queues[$lk]) ?>)</span></label>
                <?php endforeach; ?>
            </div>
            <p class="vt-text-muted text-xs mb-2" id="missingHelp"></p>
            <div class="d-flex gap-2 mb-2 flex-wrap">
                <label for="missingSearch" class="visually-hidden">Search missing words</label>
                <input type="search" id="missingSearch" class="vt-input flex-fill" style="min-width:140px;" placeholder="Search…" oninput="renderMissing()">
                <button type="button" class="btn-vt-primary" onclick="startBatch()" id="btnBatch"><i class="bi bi-collection-play me-1"></i> Batch Record</button>
            </div>
            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                <table class="vt-table">
                    <thead><tr><th class="ps-2">Word / phrase</th><th>Meaning</th><th class="text-center" title="Uses in visible posts + times residents' reader needed it">Used</th><th class="text-end pe-2"><span class="visually-hidden">Action</span></th></tr></thead>
                    <tbody id="missingRows"></tbody>
                </table>
            </div>
            <p id="missingEmpty" class="text-center py-4 vt-text-muted text-xs d-none"></p>
        </div>
    </div>

    <!-- ── 4b. Published-content coverage + resident fallback setting ─────── -->
    <div class="vt-split-grid mb-4">
        <div class="vt-card">
            <h3 class="h6 font-extrabold vt-text-primary mb-1"><i class="bi bi-bar-chart-fill me-1" style="color:#c8992e;"></i>Published Content Voice Coverage</h3>
            <p class="text-xs vt-text-muted mb-3">Unique words residents meet in visible posts that have an approved recording.</p>
            <?php foreach ($langLabels as $lk => $ll): $r = $reports[$lk]; ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between text-xs font-bold vt-text-primary">
                        <span><?= $ll ?></span>
                        <span><?= number_format((float) $r['usage_coverage'], 1) ?>% <span class="vt-text-muted font-normal">(<?= (int) $r['used_recorded'] ?> / <?= (int) $r['used_words'] ?>)</span></span>
                    </div>
                    <div class="vt-progress-track" role="progressbar" aria-label="<?= $ll ?> content coverage" aria-valuenow="<?= (float) $r['usage_coverage'] ?>" aria-valuemin="0" aria-valuemax="100"><div class="vt-progress-fill" style="width: <?= (float) $r['usage_coverage'] ?>%;"></div></div>
                </div>
            <?php endforeach; ?>
            <p class="text-xs vt-text-muted mb-0 mt-2">Bisaya = Bisaya dictionary words that appear inside Manobo text (the translator's fallback).</p>
        </div>
        <div class="vt-card">
            <h3 class="h6 font-extrabold vt-text-primary mb-1"><i class="bi bi-sliders me-1" style="color:#c8992e;"></i>Voice Fallback for Residents</h3>
            <p class="text-xs vt-text-muted mb-3">What the Resident Voice Reader does with words that have no approved recording.</p>
            <form method="post" action="<?= e(route('admin/voice-training/settings')) ?>" onsubmit="return ajaxForm(event, this)">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="voice_fallback" id="fb_none" value="recorded_only" <?= $fallbackMode === 'recorded_only' ? 'checked' : '' ?>>
                    <label class="form-check-label text-sm vt-text-primary" for="fb_none"><strong>Recorded voice only</strong> <span class="vt-text-muted text-xs d-block">Residents hear only your approved recordings. Unrecorded words are skipped (and counted in the missing lists). A post with no recordings at all cannot be played yet.</span></label>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="voice_fallback" id="fb_tts" value="recorded_tts" <?= $fallbackMode === 'recorded_tts' ? 'checked' : '' ?>>
                    <label class="form-check-label text-sm vt-text-primary" for="fb_tts"><strong>Recorded voice + device voice fallback</strong> <span class="vt-text-muted text-xs d-block">Your recordings play first; the phone or computer's own voice reads the rest, labelled as an approximation.</span></label>
                </div>
                <button type="submit" class="btn-vt-primary">Save setting</button>
            </form>
        </div>
    </div>

    <!-- ── 5. Missing voice from published content + dataset health ───────── -->
    <div class="vt-explorer-card mb-4" id="usage">
        <div class="vt-explorer-header">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <h3 class="h5 font-extrabold vt-text-primary mb-1 d-flex align-items-center gap-2">
                        <i class="bi bi-broadcast" style="color:#c8992e;"></i><span>Missing Voice From Published Content</span>
                    </h3>
                    <p class="text-xs vt-text-muted mb-0">Words in announcements, events and ordinances residents can see — in the text the Voice Reader reads for each language — plus words the reader met without a recording. Updated automatically whenever a post or translation is saved.</p>
                </div>
                <div class="d-flex gap-2 align-items-start flex-wrap">
                    <form method="post" action="<?= e(route('admin/voice-training/rescan')) ?>" onsubmit="return ajaxForm(event, this)" class="d-flex gap-1">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <label for="rescanLang" class="visually-hidden">Language to rescan</label>
                        <select name="language" id="rescanLang" class="form-select form-select-sm vt-select" style="width:auto;">
                            <option value="">Rescan all</option>
                            <?php foreach ($langLabels as $lk => $ll): ?><option value="<?= $lk ?>">Rescan <?= $ll ?></option><?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-vt-outline"><i class="bi bi-arrow-repeat me-1"></i> Rescan</button>
                    </form>
                    <?php if ($isSuperadmin): ?>
                        <button type="button" class="btn-vt-outline" onclick="runDiagnostics()"><i class="bi bi-clipboard2-pulse me-1"></i> Diagnostics</button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-2 mt-2">
                <?php foreach ([
                    ['Pending approval', $health['pending'], $health['pending'] ? 'text-warning' : ''],
                    ['Rejected', $health['rejected'], ''],
                    ['Broken audio', $health['broken'], $health['broken'] ? 'text-danger' : ''],
                    ['Duplicate approved', $health['duplicates'], ''],
                ] as [$hLabel, $hVal, $hClass]): ?>
                    <div class="col-6 col-md-3">
                        <div class="vt-stat-pill h-100">
                            <span class="vt-stat-pill-label"><?= e($hLabel) ?></span>
                            <span class="vt-stat-pill-val <?= $hClass ?>"><?= e((string) $hVal) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div id="diagnosticsBox" class="mt-3 d-none" aria-live="polite"></div>

            <div class="vt-lang-selector mt-3" role="tablist" aria-label="Filter by language">
                <input type="radio" class="btn-check" name="usage_tab" id="utab_all" value="" checked onchange="filterUsage()">
                <label class="vt-lang-btn" for="utab_all">All</label>
                <?php foreach ($langLabels as $lk => $ll): ?>
                    <input type="radio" class="btn-check" name="usage_tab" id="utab_<?= $lk ?>" value="<?= $lk ?>" onchange="filterUsage()">
                    <label class="vt-lang-btn" for="utab_<?= $lk ?>"><?= $ll ?> <span class="vt-text-muted">(<?= (int) $reports[$lk]['used_missing'] ?>)</span></label>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!$usageMissing): ?>
            <div class="text-center py-4 vt-text-muted text-xs">
                <i class="bi bi-check-circle-fill text-success me-1"></i>Every word used in visible posts has an approved recording (or no posts have text yet — click <strong>Rescan</strong>).
            </div>
        <?php else: ?>
            <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                <table class="vt-table">
                    <thead><tr>
                        <th class="ps-4">Word</th><th>Language</th><th>Meaning</th><th class="text-center">In posts</th><th class="text-center" title="Times the Voice Reader needed this word">Reader</th><th>Used in</th><th>Last used</th><th class="pe-4 text-end"><span class="visually-hidden">Action</span></th>
                    </tr></thead>
                    <tbody id="usageRows">
                    <?php foreach ($usageMissing as $w): ?>
                        <tr data-lang="<?= e($w['language']) ?>">
                            <td class="ps-4 font-bold vt-text-primary"><?= e($w['word']) ?></td>
                            <td><span class="vt-badge <?= $langBadge[$w['language']] ?>"><?= e($langLabels[$w['language']]) ?></span></td>
                            <td class="vt-text-secondary text-xs"><?= $w['translation'] !== '' ? e($w['translation']) : '<span class="vt-text-muted">—</span>' ?></td>
                            <td class="text-center"><?= (int) $w['occurrences'] ?></td>
                            <td class="text-center"><?= (int) $w['reader_requests'] ?></td>
                            <td class="text-xs vt-wrap" style="max-width:240px;">
                                <?php foreach (array_slice($w['sources'], 0, 2) as $src): ?>
                                    <span class="vt-badge vt-badge-blue d-inline-block mb-1" title="<?= e($src['title']) ?>"><?= e(ucfirst($src['type'])) ?>: <?= e(mb_strimwidth($src['title'], 0, 26, '…')) ?></span>
                                <?php endforeach; ?>
                                <?php if (count($w['sources']) > 2): ?><span class="vt-text-muted">+<?= count($w['sources']) - 2 ?> more</span><?php endif; ?>
                                <?php if (!$w['sources']): ?><span class="vt-text-muted">Voice Reader only</span><?php endif; ?>
                            </td>
                            <td class="text-xs vt-text-muted"><?= $w['last_seen_at'] ? e(date('M j, Y', strtotime((string) $w['last_seen_at']))) : '—' ?></td>
                            <td class="pe-4 text-end">
                                <button type="button" class="btn-vt-gold" onclick='recordFromQueue(<?= e(json_encode($w['language'])) ?>, <?= e(json_encode($w['word'])) ?>)' aria-label="Record <?= e($w['word']) ?> now">
                                    <i class="bi bi-mic-fill me-1"></i> Record Now
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($usageTruncated): ?><p class="text-xs vt-text-muted p-3 mb-0">Showing the 25 most-used missing words per language. The full lists are in Missing Pronunciations above (search and Batch Record).</p><?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- ── 6. Samples management ──────────────────────────────────────────── -->
    <div class="vt-explorer-card" id="samples">
        <div class="vt-explorer-header">
            <div class="row g-3 align-items-center">
                <div class="col-12 col-lg-4">
                    <h3 class="h5 font-extrabold vt-text-primary mb-1 d-flex align-items-center gap-2">
                        <i class="bi bi-collection-play-fill" style="color: #c8992e;"></i><span>Voice Dataset Samples</span>
                    </h3>
                    <p class="text-xs vt-text-muted mb-0">Play, approve, reject, re-record, edit or delete. Only approved recordings reach residents.</p>
                </div>
                <div class="col-12 col-lg-8">
                    <form method="get" action="<?= e(route('admin/voice-training')) ?>#samples" class="row g-2" role="search">
                        <div class="col-12 col-sm-4">
                            <label class="visually-hidden" for="f_search">Search</label>
                            <input id="f_search" type="search" name="search" class="form-control form-control-sm vt-input" placeholder="Search word, meaning, speaker…" value="<?= e($filters['search']) ?>">
                        </div>
                        <div class="col-6 col-sm-2">
                            <label class="visually-hidden" for="f_lang">Language</label>
                            <select id="f_lang" name="language" class="form-select form-select-sm vt-select" onchange="this.form.submit()">
                                <option value="">All languages</option>
                                <?php foreach ($langLabels as $lk => $ll): ?><option value="<?= $lk ?>" <?= $filters['language'] === $lk ? 'selected' : '' ?>><?= $ll ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-sm-2">
                            <label class="visually-hidden" for="f_status">Status</label>
                            <select id="f_status" name="status" class="form-select form-select-sm vt-select" onchange="this.form.submit()">
                                <option value="">All statuses</option>
                                <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $sk => $sl): ?><option value="<?= $sk ?>" <?= $filters['status'] === $sk ? 'selected' : '' ?>><?= $sl ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-sm-2">
                            <label class="visually-hidden" for="f_sort">Sort</label>
                            <select id="f_sort" name="sort" class="form-select form-select-sm vt-select" onchange="this.form.submit()">
                                <option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>>Newest</option>
                                <option value="oldest" <?= $filters['sort'] === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                                <option value="text" <?= $filters['sort'] === 'text' ? 'selected' : '' ?>>A–Z</option>
                            </select>
                        </div>
                        <div class="col-6 col-sm-2 d-flex gap-1">
                            <button type="submit" class="btn-vt-outline flex-fill justify-content-center" style="padding:6px;" title="Search"><i class="bi bi-search"></i><span class="visually-hidden">Search</span></button>
                            <a href="<?= e(route('admin/voice-training')) ?>#samples" class="btn-vt-outline flex-fill justify-content-center" style="padding:6px;" title="Reset filters and refresh"><i class="bi bi-arrow-counterclockwise"></i><span class="visually-hidden">Reset</span></a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php if (!$samples): ?>
            <div class="text-center py-5 vt-text-muted">
                <i class="bi bi-mic-mute fs-1 d-block mb-2"></i>
                <span class="font-bold vt-text-primary d-block">No voice samples match.</span>
                <span class="text-xs">Record a word from the missing lists above to start the dataset.</span>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="vt-table">
                    <thead><tr>
                        <th class="ps-4">Text</th><th>Language</th><th class="d-none d-md-table-cell">Speaker</th><th>Audio</th><th class="d-none d-lg-table-cell">Linked</th><th>Status</th><th class="pe-4 text-end">Actions</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($samples as $s):
                        $sStatus  = strtolower((string) $s['status']);
                        $sPlay    = $s['playable_url'];
                        $sEditPayload = [
                            'id' => (int) $s['id'], 'text' => $s['text'], 'translation' => (string) ($s['translation'] ?? ''),
                            'dictionary_entry_id' => $s['dictionary_entry_id'], 'speaker_label' => (string) $s['speaker_label'],
                            'voice_type' => (string) $s['voice_type'], 'notes' => (string) ($s['notes'] ?? ''), 'language' => $s['language'],
                        ];
                    ?>
                        <tr>
                            <td class="ps-4 vt-wrap" style="max-width: 220px;">
                                <span class="font-bold vt-text-primary d-block"><?= e($s['text']) ?></span>
                                <?php if (!empty($s['translation']) || !empty($s['tagalog'])): ?>
                                    <span class="text-xs vt-text-muted d-block"><?= e((string) ($s['translation'] ?: $s['tagalog'])) ?></span>
                                <?php endif; ?>
                                <span class="text-xs vt-text-muted">#<?= (int) $s['id'] ?> · <?= e(date('M j, Y', strtotime((string) $s['created_at']))) ?><?= $s['duration'] ? ' · ' . number_format((float) $s['duration'], 1) . 's' : '' ?></span>
                            </td>
                            <td><span class="vt-badge <?= $langBadge[$s['language']] ?? 'vt-badge-neutral' ?>"><?= e($langLabels[$s['language']] ?? $s['language']) ?></span></td>
                            <td class="vt-text-secondary text-xs d-none d-md-table-cell">
                                <span class="font-semibold vt-text-primary d-block"><?= e((string) ($s['speaker_label'] ?: 'Community')) ?></span>
                                <span class="vt-text-muted text-uppercase font-mono" style="font-size:10px;"><?= e((string) $s['voice_type']) ?></span>
                            </td>
                            <td style="min-width: 170px;">
                                <?php if ($sPlay): ?>
                                    <audio controls preload="none" style="max-width: 190px; height: 32px;" aria-label="Play recording of <?= e($s['text']) ?>">
                                        <source src="<?= e($sPlay) ?>" type="<?= e((string) ($s['mime_type'] ?: 'audio/webm')) ?>">
                                    </audio>
                                <?php else: ?>
                                    <span class="vt-badge vt-badge-red" title="The audio file no longer exists (it was stored on the server disk before a restart). Re-record it.">BROKEN</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-xs d-none d-lg-table-cell">
                                <?php if (!empty($s['manobo'])): ?><span class="vt-badge vt-badge-green d-inline-block mb-1" title="Dictionary entry"><i class="bi bi-book me-1"></i><?= e($s['manobo']) ?></span><?php endif; ?>
                                <?php if (!empty($s['content_type']) && !empty($s['content_id'])): ?><span class="vt-badge vt-badge-blue d-inline-block" title="Full narration for this post"><i class="bi bi-broadcast me-1"></i><?= e(ucfirst((string) $s['content_type'])) ?> #<?= (int) $s['content_id'] ?></span><?php endif; ?>
                                <?php if (empty($s['manobo']) && empty($s['content_type'])): ?><span class="vt-text-muted">—</span><?php endif; ?>
                            </td>
                            <td><span class="vt-badge <?= $statusBadge[$sStatus] ?? 'vt-badge-neutral' ?>"><?= e(ucfirst($sStatus)) ?></span></td>
                            <td class="pe-4 text-end">
                                <div class="d-inline-flex gap-1 align-items-center flex-wrap justify-content-end">
                                    <?php if ($sStatus !== 'approved' && $sPlay): ?>
                                        <button type="button" class="btn-vt-primary" style="padding: 4px 8px; font-size: 0.72rem; border-radius: 6px;" onclick="sampleAction(<?= (int) $s['id'] ?>, 'approve')"><i class="bi bi-check-lg me-1"></i>Approve</button>
                                    <?php endif; ?>
                                    <?php if ($sStatus !== 'rejected'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-lg px-2 py-1" style="font-size:.72rem;" onclick="sampleAction(<?= (int) $s['id'] ?>, 'reject')">Reject</button>
                                    <?php endif; ?>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary rounded-lg px-2 py-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More actions for <?= e($s['text']) ?>"><i class="bi bi-three-dots"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end text-xs">
                                            <li><button class="dropdown-item" type="button" onclick='openRecorder(<?= e(json_encode($sEditPayload + ['rerecord' => true])) ?>)'><i class="bi bi-mic me-2"></i>Re-record</button></li>
                                            <li><button class="dropdown-item" type="button" onclick='openEditSample(<?= e(json_encode($sEditPayload)) ?>)'><i class="bi bi-pencil me-2"></i>Edit details</button></li>
                                            <?php if ($sStatus === 'approved' || $sStatus === 'rejected'): ?>
                                                <li><button class="dropdown-item" type="button" onclick="sampleAction(<?= (int) $s['id'] ?>, 'pending')"><i class="bi bi-hourglass me-2"></i>Move to pending</button></li>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><button class="dropdown-item text-danger" type="button" onclick="sampleAction(<?= (int) $s['id'] ?>, 'delete', <?= e(json_encode($s['text'])) ?>)"><i class="bi bi-trash me-2"></i>Delete</button></li>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="p-3 border-top d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 text-xs">
                <span class="vt-text-muted font-medium">Showing <?= count($samples) ?> of <?= (int) $pagination['total'] ?> (page <?= (int) $pagination['page'] ?> of <?= (int) $pagination['total_pages'] ?>)</span>
                <?php if ($pagination['total_pages'] > 1): ?>
                <nav aria-label="Samples pages" class="d-flex gap-1 flex-wrap">
                    <?php for ($i = max(1, $pagination['page'] - 3); $i <= min($pagination['total_pages'], $pagination['page'] + 3); $i++): ?>
                        <a href="<?= e($pageUrl(['page' => $i > 1 ? $i : ''])) ?>#samples" class="<?= $i === $pagination['page'] ? 'btn-vt-primary' : 'btn-vt-outline' ?>" style="padding: 4px 10px; border-radius: 6px;" <?= $i === $pagination['page'] ? 'aria-current="page"' : '' ?>><?= $i ?></a>
                    <?php endfor; ?>
                </nav>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Recorder modal (new sample and re-record) ───────────────────────── -->
<div class="modal fade vt-modal" id="addSampleModal" tabindex="-1" aria-labelledby="addSampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-sm-down">
        <div class="modal-content vt-modal-content">
            <div class="modal-header vt-modal-header">
                <h4 class="modal-title font-extrabold vt-text-primary d-flex align-items-center gap-2 h5 mb-0" id="addSampleModalLabel">
                    <i class="bi bi-mic-fill" style="color: #c8992e;"></i><span id="recModalTitle">Add Voice Sample</span>
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addVoiceSampleForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" id="rec_sample_id" value="">
                <div class="modal-body vt-modal-body">
                    <div id="recAlert" class="alert d-none" role="alert"></div>
                    <div id="queueInfo" class="d-none mb-3 p-2 rounded-xl text-xs vt-text-primary" style="background-color: var(--surface-muted, #f1e6d2); border: 1px solid var(--border, #e4d7c2);" aria-live="polite"></div>
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label for="sample_language" class="form-label text-xs font-bold vt-text-primary">Language <span class="text-danger">*</span></label>
                            <select name="language" id="sample_language" class="form-select form-select-sm vt-select" onchange="onSampleLangChange()" required>
                                <?php foreach ($langLabels as $lk => $ll): ?><option value="<?= $lk ?>"><?= $ll ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-8">
                            <label for="sample_text" class="form-label text-xs font-bold vt-text-primary">Word or phrase spoken <span class="text-danger">*</span></label>
                            <input type="text" name="text" id="sample_text" class="vt-input" maxlength="500" required placeholder="Exactly what the speaker says">
                        </div>
                        <div class="col-12 col-md-6" id="dictWrap">
                            <label for="sample_dictionary_entry_id" class="form-label text-xs font-bold vt-text-primary">Manobo dictionary entry</label>
                            <select name="dictionary_entry_id" id="sample_dictionary_entry_id" class="form-select form-select-sm vt-select" onchange="onDictionaryPick(this)">
                                <option value="">— Not linked (matched automatically by spelling) —</option>
                                <optgroup label="Missing a recording">
                                    <?php foreach ($dictionaryEntries as $d): if ($d['recorded']) { continue; } ?>
                                        <option value="<?= (int) $d['id'] ?>" data-word="<?= e($d['manobo']) ?>" data-meaning="<?= e($d['translation']) ?>"><?= e($d['manobo']) ?> — <?= e($d['translation']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Already recorded">
                                    <?php foreach ($dictionaryEntries as $d): if (!$d['recorded']) { continue; } ?>
                                        <option value="<?= (int) $d['id'] ?>" data-word="<?= e($d['manobo']) ?>" data-meaning="<?= e($d['translation']) ?>"><?= e($d['manobo']) ?> — <?= e($d['translation']) ?> ✓</option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="sample_translation" class="form-label text-xs font-bold vt-text-primary">Meaning / translation</label>
                            <input type="text" name="translation" id="sample_translation" class="vt-input" maxlength="500" placeholder="e.g. Magandang umaga">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="sample_speaker" class="form-label text-xs font-bold vt-text-primary">Speaker name / label</label>
                            <input type="text" name="speaker_label" id="sample_speaker" class="vt-input" maxlength="100" placeholder="e.g. Native speaker, Purok 3">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="sample_voice_type" class="form-label text-xs font-bold vt-text-primary">Voice type</label>
                            <select name="voice_type" id="sample_voice_type" class="form-select form-select-sm vt-select">
                                <option value="community">Community voice</option><option value="male">Male</option><option value="female">Female</option><option value="neutral">Neutral</option>
                            </select>
                        </div>
                        <div class="col-12" id="postLinkWrap">
                            <label for="sample_post_link" class="form-label text-xs font-bold vt-text-primary">Full narration of a post? <span class="vt-text-muted font-normal">(only if this recording reads the whole post)</span></label>
                            <select name="post_link" id="sample_post_link" class="form-select form-select-sm vt-select">
                                <option value="">— No, this is a word or phrase —</option>
                                <?php foreach (['announcements' => 'announcement', 'events' => 'event', 'ordinances' => 'ordinance'] as $group => $ct): if (empty($recentPosts[$group])) { continue; } ?>
                                    <optgroup label="<?= e(ucfirst($group)) ?>">
                                        <?php foreach ($recentPosts[$group] as $p): ?>
                                            <option value="<?= $ct ?>:<?= (int) $p['id'] ?>">#<?= (int) $p['id'] ?> <?= e(mb_strimwidth((string) $p['title'], 0, 60, '…')) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Recorder -->
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <span class="form-label text-xs font-bold vt-text-primary mb-0">Audio <span class="text-danger">*</span></span>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Audio source">
                                    <input type="radio" class="btn-check" name="audio_source" id="src_mic" value="mic" checked onchange="onAudioSourceChange()">
                                    <label class="btn btn-outline-secondary" for="src_mic"><i class="bi bi-mic-fill me-1"></i>Record</label>
                                    <input type="radio" class="btn-check" name="audio_source" id="src_file" value="file" onchange="onAudioSourceChange()">
                                    <label class="btn btn-outline-secondary" for="src_file"><i class="bi bi-upload me-1"></i>Upload file</label>
                                </div>
                            </div>
                            <div id="micPanel" class="p-3 rounded-2xl text-center" style="background-color: var(--surface-muted, #f1e6d2); border: 1px solid var(--border, #e4d7c2);">
                                <div class="d-flex justify-content-center align-items-center gap-2 flex-wrap">
                                    <button type="button" id="btnStartRec" onclick="startMicRecording()" class="btn-vt-gold px-4 py-2"><i class="bi bi-record-fill me-1"></i> <span>Start Recording</span></button>
                                    <button type="button" id="btnStopRec" onclick="stopMicRecording()" class="btn btn-dark rounded-xl font-bold px-4 py-2 d-none"><i class="bi bi-stop-fill me-1"></i> Stop</button>
                                    <span id="recStatus" class="font-mono text-sm font-extrabold text-danger d-none" role="status" aria-live="polite"></span>
                                </div>
                                <div id="recLevelWrap" class="mt-2 d-none" aria-hidden="true">
                                    <div class="vt-progress-track"><div id="recLevel" class="vt-progress-fill" style="width:0%; transition: width .08s linear;"></div></div>
                                </div>
                                <div id="micPreview" class="mt-3 d-none">
                                    <audio id="recAudioPlayer" controls class="w-100 rounded-lg mb-2" style="height: 38px;" aria-label="Preview of your recording"></audio>
                                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                                        <button type="button" onclick="resetMicRecording()" class="btn-vt-outline" style="padding: 4px 10px; font-size: 0.75rem;"><i class="bi bi-arrow-counterclockwise me-1"></i> Re-record</button>
                                    </div>
                                    <p id="recWarning" class="text-xs text-warning-emphasis mt-2 mb-0 d-none"></p>
                                </div>
                            </div>
                            <div id="filePanel" class="p-3 rounded-2xl d-none" style="background-color: var(--surface-muted, #f1e6d2); border: 1px solid var(--border, #e4d7c2);">
                                <label for="audio_file_input" class="visually-hidden">Audio file</label>
                                <input type="file" id="audio_file_input" class="form-control form-control-sm" accept="audio/*,.mp3,.wav,.m4a,.ogg,.webm" onchange="onFilePicked(this)">
                                <span class="vt-text-muted mt-1 d-block" style="font-size:11px;">WebM, OGG, M4A/MP4, MP3 or WAV — up to 5 MB.</span>
                            </div>
                        </div>

                        <div class="col-12">
                            <label for="sample_notes" class="form-label text-xs font-bold vt-text-primary">Notes</label>
                            <textarea name="notes" id="sample_notes" class="vt-input" rows="2" maxlength="2000" placeholder="Dialect, pronunciation or cultural notes (optional)"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check p-2 rounded-xl" style="background-color: var(--surface-muted, #f1e6d2); border: 1px solid var(--border, #e4d7c2);">
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="consent_confirmed" value="1" id="consent_confirmed">
                                <label class="form-check-label text-xs font-medium vt-text-primary" for="consent_confirmed">The speaker agreed to this recording being used in the BarangGabay voice dataset.</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer vt-modal-footer">
                    <button type="button" class="btn-vt-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="btnSavePending" class="btn-vt-secondary" onclick="submitSample('pending')">Save as Pending</button>
                    <button type="button" id="btnSaveApprove" class="btn-vt-primary" onclick="submitSample('approved')"><i class="bi bi-check-circle-fill me-1"></i> Save &amp; Approve</button>
                    <button type="button" id="btnSaveNext" class="btn-vt-primary d-none" onclick="submitSample('approved', '', true)"><i class="bi bi-skip-forward-fill me-1"></i> Save &amp; Record Next</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Duplicate decision ──────────────────────────────────────────────── -->
<div class="modal fade vt-modal" id="duplicateModal" tabindex="-1" aria-labelledby="duplicateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content vt-modal-content">
            <div class="modal-header vt-modal-header"><h4 class="modal-title h6 mb-0 font-extrabold" id="duplicateModalLabel">Already recorded</h4></div>
            <div class="modal-body vt-modal-body text-sm"><p id="duplicateText" class="mb-0"></p></div>
            <div class="modal-footer vt-modal-footer">
                <button type="button" class="btn-vt-outline" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-vt-secondary" onclick="resolveDuplicate('version')" title="Keep both; the newest approved recording is the one residents hear">Add New Version</button>
                <button type="button" class="btn-vt-primary" onclick="resolveDuplicate('replace')" title="Approve this one and retire the old recording">Replace</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Edit sample details ─────────────────────────────────────────────── -->
<div class="modal fade vt-modal" id="editSampleModal" tabindex="-1" aria-labelledby="editSampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content vt-modal-content">
            <div class="modal-header vt-modal-header">
                <h4 class="modal-title h5 mb-0 font-extrabold vt-text-primary" id="editSampleModalLabel">Edit sample details</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?= e(route('admin/voice-training/update')) ?>" onsubmit="return ajaxForm(event, this)">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body vt-modal-body">
                    <div class="mb-2"><label class="form-label text-xs font-bold" for="edit_text">Word or phrase</label><input class="vt-input" name="text" id="edit_text" maxlength="500" required></div>
                    <div class="mb-2"><label class="form-label text-xs font-bold" for="edit_translation">Meaning / translation</label><input class="vt-input" name="translation" id="edit_translation" maxlength="500"></div>
                    <div class="mb-2">
                        <label class="form-label text-xs font-bold" for="edit_dict">Manobo dictionary entry</label>
                        <select class="form-select form-select-sm vt-select" name="dictionary_entry_id" id="edit_dict">
                            <option value="">— Not linked —</option>
                            <?php foreach ($dictionaryEntries as $d): ?><option value="<?= (int) $d['id'] ?>"><?= e($d['manobo']) ?> — <?= e($d['translation']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label text-xs font-bold" for="edit_speaker">Speaker</label><input class="vt-input" name="speaker_label" id="edit_speaker" maxlength="100"></div>
                        <div class="col-6"><label class="form-label text-xs font-bold" for="edit_voice_type">Voice type</label>
                            <select class="form-select form-select-sm vt-select" name="voice_type" id="edit_voice_type"><option value="community">Community</option><option value="male">Male</option><option value="female">Female</option><option value="neutral">Neutral</option></select>
                        </div>
                    </div>
                    <div class="mt-2"><label class="form-label text-xs font-bold" for="edit_notes">Notes</label><textarea class="vt-input" name="notes" id="edit_notes" rows="2" maxlength="2000"></textarea></div>
                </div>
                <div class="modal-footer vt-modal-footer">
                    <button type="button" class="btn-vt-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-vt-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Voice profile modals ────────────────────────────────────────────── -->
<?php foreach ($langLabels as $langKey => $langLabel): $p = $activeProfiles[$langKey] ?? []; ?>
<div class="modal fade vt-modal" id="profileModal_<?= $langKey ?>" tabindex="-1" aria-labelledby="profileModalLabel_<?= $langKey ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content vt-modal-content">
            <div class="modal-header vt-modal-header">
                <h4 class="modal-title font-extrabold vt-text-primary d-flex align-items-center gap-2 h5 mb-0" id="profileModalLabel_<?= $langKey ?>">
                    <i class="bi bi-sliders" style="color: #c8992e;"></i><?= $langLabel ?> voice profile
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?= e(route('admin/voice-training/profile')) ?>" onsubmit="return ajaxForm(event, this)">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="language" value="<?= $langKey ?>">
                <div class="modal-body vt-modal-body">
                    <p class="text-xs vt-text-muted">One profile per language. It sets the name shown to staff, the reading speed residents start at, and the fallback voice used for words with no recording.</p>
                    <div class="mb-3">
                        <label class="form-label text-xs font-bold vt-text-primary" for="pname_<?= $langKey ?>">Profile name</label>
                        <input type="text" name="profile_name" id="pname_<?= $langKey ?>" class="vt-input" maxlength="100" value="<?= e((string) ($p['profile_name'] ?? "{$langLabel} Default Voice")) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-xs font-bold vt-text-primary" for="pprov_<?= $langKey ?>">Fallback for unrecorded words</label>
                        <select name="provider" id="pprov_<?= $langKey ?>" class="form-select form-select-sm vt-select">
                            <option value="dataset_hybrid" <?= ($p['provider'] ?? '') === 'dataset_hybrid' ? 'selected' : '' ?>>Dataset recordings + device voice</option>
                            <option value="system" <?= ($p['provider'] ?? '') === 'system' ? 'selected' : '' ?>>Device voice (browser speech)</option>
                            <option value="google_tts" <?= ($p['provider'] ?? '') === 'google_tts' ? 'selected' : '' ?>>Server TTS narration when generated (TTS_PROVIDER)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-xs font-bold vt-text-primary" for="pvoice_<?= $langKey ?>">Preferred device voice language tag</label>
                        <input type="text" name="provider_voice_id" id="pvoice_<?= $langKey ?>" class="vt-input" maxlength="100" value="<?= e((string) ($p['provider_voice_id'] ?? '')) ?>" placeholder="e.g. fil-PH, en-PH">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-xs font-bold vt-text-primary" for="pdesc_<?= $langKey ?>">Description</label>
                        <textarea name="description" id="pdesc_<?= $langKey ?>" class="vt-input" rows="2" maxlength="1000"><?= e((string) ($p['description'] ?? '')) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-xs font-bold vt-text-primary" for="rate_range_<?= $langKey ?>">Starting speaking rate</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="range" name="speaking_rate" class="form-range" min="0.6" max="1.4" step="0.05" value="<?= e((string) ($p['speaking_rate'] ?? 0.95)) ?>" id="rate_range_<?= $langKey ?>" oninput="document.getElementById('rate_val_<?= $langKey ?>').textContent = this.value + 'x'">
                            <span class="text-xs font-mono font-bold vt-text-primary" id="rate_val_<?= $langKey ?>"><?= e((string) ($p['speaking_rate'] ?? 0.95)) ?>x</span>
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="pactive_<?= $langKey ?>" <?= !isset($p['is_active']) || (int) $p['is_active'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label text-xs font-medium vt-text-primary" for="pactive_<?= $langKey ?>">Active (used by the Resident Voice Reader)</label>
                    </div>
                </div>
                <div class="modal-footer vt-modal-footer">
                    <button type="button" class="btn-vt-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-vt-primary">Save Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script src="<?= e(asset_v('assets/js/voice-reader.js')) ?>"></script>
<script>
(function () {
    'use strict';

    var URLS = {
        store:  <?= json_encode(route('admin/voice-training/samples')) ?>,
        update: <?= json_encode(route('admin/voice-training/update')) ?>,
        test:   <?= json_encode(route('admin/voice-training/test')) ?>,
        diag:   <?= json_encode(route('admin/voice-training/diagnostics')) ?>
    };
    var CSRF = <?= json_encode(csrf_token()) ?>;
    var QUEUES = <?= json_encode($queues, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var LANG_NAMES = <?= json_encode($langLabels) ?>;
    var MAX_SECONDS = 120;

    /* ── Helpers ─────────────────────────────────────────────────────── */

    function $(id) { return document.getElementById(id); }

    function showAlert(el, kind, message) {
        el.className = 'alert alert-' + kind;
        el.textContent = message;
        el.classList.remove('d-none');
    }

    /** POST FormData as AJAX; always resolves to {ok, status, data}. Never fakes success. */
    function post(url, formData) {
        formData.set('csrf_token', CSRF);
        return fetch(url, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (res) {
                var type = res.headers.get('Content-Type') || '';
                if (type.indexOf('application/json') === -1) {
                    return { ok: false, status: res.status, data: { error: res.status === 403
                        ? 'Your session expired or the security token is stale. Reload the page and try again.'
                        : res.status === 413 ? 'The file is larger than the server accepts.'
                        : 'Unexpected server response (HTTP ' + res.status + ').' } };
                }
                return res.json().then(function (data) { return { ok: res.ok && data.success, status: res.status, data: data }; });
            })
            .catch(function () { return { ok: false, status: 0, data: { error: 'Network error — check your connection and try again.' } }; });
    }

    function reloadWithMessage(message) {
        try { sessionStorage.setItem('vt_flash', message); } catch (e) {}
        window.location.reload();
    }

    document.addEventListener('DOMContentLoaded', function () {
        var msg = null;
        try { msg = sessionStorage.getItem('vt_flash'); sessionStorage.removeItem('vt_flash'); } catch (e) {}
        if (msg) { showAlert($('vtPageAlert'), 'success', msg); }
    });

    /** Submit a normal form via AJAX and reload on success (rescan, edit, profile). */
    window.ajaxForm = function (event, form) {
        event.preventDefault();
        var btn = form.querySelector('[type=submit]');
        if (btn) { btn.disabled = true; }
        post(form.action, new FormData(form)).then(function (r) {
            if (btn) { btn.disabled = false; }
            if (r.ok) { reloadWithMessage(r.data.message || 'Saved.'); return; }
            alert(r.data.error || 'The request failed.');
        });
        return false;
    };

    /** Approve / reject / pending / delete one sample. */
    window.sampleAction = function (id, action, text) {
        if (action === 'delete' && !confirm('Delete the recording "' + text + '"? This cannot be undone.')) { return; }
        var fd = new FormData();
        fd.set('id', id);
        fd.set('action', action);
        post(URLS.update, fd).then(function (r) {
            if (r.ok) { reloadWithMessage(r.data.message); return; }
            showAlert($('vtPageAlert'), 'danger', r.data.error || 'The change failed.');
            $('vtPageAlert').scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    };

    window.openEditSample = function (s) {
        $('edit_id').value = s.id;
        $('edit_text').value = s.text || '';
        $('edit_translation').value = s.translation || '';
        $('edit_dict').value = s.dictionary_entry_id || '';
        $('edit_speaker').value = s.speaker_label || '';
        $('edit_voice_type').value = s.voice_type || 'community';
        $('edit_notes').value = s.notes || '';
        bootstrap.Modal.getOrCreateInstance($('editSampleModal')).show();
    };

    /* ── Recorder ────────────────────────────────────────────────────── */

    var rec = { recorder: null, stream: null, chunks: [], blob: null, mime: '', seconds: 0, timer: null,
                started: 0, duration: null, file: null, audioCtx: null, analyser: null, raf: null, peak: 0, rerecordId: null };

    /** First container this browser can record (Chrome/Firefox: WebM/Opus; Safari: MP4/AAC). */
    function pickRecorderMime() {
        if (typeof MediaRecorder === 'undefined' || !MediaRecorder.isTypeSupported) { return ''; }
        var options = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4;codecs=mp4a.40.2', 'audio/mp4', 'audio/ogg;codecs=opus', 'audio/ogg'];
        for (var i = 0; i < options.length; i++) {
            if (MediaRecorder.isTypeSupported(options[i])) { return options[i]; }
        }
        return '';
    }

    function extFor(mime) {
        if (mime.indexOf('mp4') !== -1) { return 'm4a'; }
        if (mime.indexOf('ogg') !== -1) { return 'ogg'; }
        if (mime.indexOf('mpeg') !== -1) { return 'mp3'; }
        if (mime.indexOf('wav') !== -1) { return 'wav'; }
        return 'webm';
    }

    function micError(err) {
        if (!window.isSecureContext) { return 'Recording needs a secure (https://) page. Open the site over HTTPS.'; }
        switch (err && err.name) {
            case 'NotAllowedError':
            case 'SecurityError':   return 'Microphone permission was denied. Allow microphone access in your browser settings (the lock icon beside the address) and try again.';
            case 'NotFoundError':
            case 'OverconstrainedError': return 'No microphone was found. Connect a microphone and try again.';
            case 'NotReadableError':
            case 'AbortError':      return 'The microphone is being used by another app or tab. Close it and try again.';
            default:                return 'Could not start the microphone' + (err && err.message ? ': ' + err.message : '.');
        }
    }

    function setRecStatus(text) {
        var el = $('recStatus');
        el.textContent = text;
        el.classList.toggle('d-none', !text);
    }

    function fmt(sec) {
        return String(Math.floor(sec / 60)).padStart(2, '0') + ':' + String(sec % 60).padStart(2, '0');
    }

    window.startMicRecording = function () {
        var alertEl = $('recAlert');
        alertEl.classList.add('d-none');

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || typeof MediaRecorder === 'undefined') {
            showAlert(alertEl, 'warning', window.isSecureContext
                ? 'This browser cannot record audio. Use a recent Chrome, Edge, Firefox or Safari — or switch to "Upload file".'
                : 'Recording needs a secure (https://) page. Open the site over HTTPS.');
            return;
        }

        $('btnStartRec').disabled = true;
        navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } })
            .then(function (stream) {
                rec.stream = stream;
                rec.chunks = [];
                rec.blob = null;
                rec.peak = 0;
                rec.metered = false;
                var mime = pickRecorderMime();
                try {
                    rec.recorder = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
                } catch (e) {
                    rec.recorder = new MediaRecorder(stream);
                }
                rec.mime = rec.recorder.mimeType || mime || 'audio/webm';

                rec.recorder.ondataavailable = function (ev) { if (ev.data && ev.data.size > 0) { rec.chunks.push(ev.data); } };
                rec.recorder.onstop = onRecordingStopped;
                rec.recorder.start(250);
                rec.started = performance.now();
                startLevelMeter(stream);

                $('btnStartRec').classList.add('d-none');
                $('btnStartRec').disabled = false;
                $('btnStopRec').classList.remove('d-none');
                $('btnStopRec').focus();
                $('micPreview').classList.add('d-none');
                rec.seconds = 0;
                setRecStatus('● Recording… 00:00');
                rec.timer = setInterval(function () {
                    rec.seconds++;
                    setRecStatus('● Recording… ' + fmt(rec.seconds));
                    if (rec.seconds >= MAX_SECONDS) { window.stopMicRecording(); }
                }, 1000);
            })
            .catch(function (err) {
                $('btnStartRec').disabled = false;
                showAlert(alertEl, 'danger', micError(err));
            });
    };

    function startLevelMeter(stream) {
        try {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) { return; }
            rec.audioCtx = new Ctx();
            rec.analyser = rec.audioCtx.createAnalyser();
            rec.analyser.fftSize = 512;
            rec.metered = true;
            rec.audioCtx.createMediaStreamSource(stream).connect(rec.analyser);
            var data = new Uint8Array(rec.analyser.fftSize);
            $('recLevelWrap').classList.remove('d-none');
            (function tick() {
                rec.analyser.getByteTimeDomainData(data);
                var max = 0;
                for (var i = 0; i < data.length; i++) { max = Math.max(max, Math.abs(data[i] - 128)); }
                rec.peak = Math.max(rec.peak, max);
                $('recLevel').style.width = Math.min(100, max / 1.28) + '%';
                rec.raf = requestAnimationFrame(tick);
            })();
        } catch (e) { /* level meter is a nicety */ }
    }

    function stopLevelMeter() {
        if (rec.raf) { cancelAnimationFrame(rec.raf); rec.raf = null; }
        if (rec.audioCtx) { try { rec.audioCtx.close(); } catch (e) {} rec.audioCtx = null; }
        $('recLevelWrap').classList.add('d-none');
    }

    window.stopMicRecording = function () {
        if (rec.recorder && rec.recorder.state !== 'inactive') { rec.recorder.stop(); }
        clearInterval(rec.timer);
        $('btnStopRec').classList.add('d-none');
        $('btnStartRec').classList.remove('d-none');
        $('btnStartRec').querySelector('span').textContent = 'Record again';
    };

    function onRecordingStopped() {
        rec.duration = (performance.now() - rec.started) / 1000;
        stopLevelMeter();
        if (rec.stream) { rec.stream.getTracks().forEach(function (t) { t.stop(); }); rec.stream = null; }

        rec.blob = new Blob(rec.chunks, { type: rec.mime.split(';')[0] });
        if (!rec.blob.size) {
            rec.blob = null;
            setRecStatus('');
            showAlert($('recAlert'), 'danger', 'The microphone produced no audio. Check that the right microphone is selected and not muted, then record again.');
            return;
        }

        setRecStatus('Recorded ' + rec.duration.toFixed(1) + ' s');
        var player = $('recAudioPlayer');
        if (player.dataset.url) { URL.revokeObjectURL(player.dataset.url); }
        player.dataset.url = URL.createObjectURL(rec.blob);
        player.src = player.dataset.url;
        $('micPreview').classList.remove('d-none');

        var warn = $('recWarning');
        var warnings = [];
        if (rec.duration < 0.5) { warnings.push('Very short recording — make sure the whole word was captured.'); }
        if (rec.metered && rec.peak < 4) { warnings.push('The recording sounds almost silent. Speak closer to the microphone.'); }
        warn.textContent = warnings.join(' ');
        warn.classList.toggle('d-none', !warnings.length);
    }

    window.resetMicRecording = function () {
        rec.blob = null;
        rec.duration = null;
        $('micPreview').classList.add('d-none');
        setRecStatus('');
        window.startMicRecording();
    };

    window.onFilePicked = function (input) {
        rec.file = input.files && input.files[0] ? input.files[0] : null;
        rec.duration = null;
        if (rec.file) {
            var probe = new Audio();
            probe.preload = 'metadata';
            probe.onloadedmetadata = function () { if (isFinite(probe.duration)) { rec.duration = probe.duration; } URL.revokeObjectURL(probe.src); };
            probe.src = URL.createObjectURL(rec.file);
        }
    };

    window.onAudioSourceChange = function () {
        var file = $('src_file').checked;
        $('micPanel').classList.toggle('d-none', file);
        $('filePanel').classList.toggle('d-none', !file);
        if (file) { window.stopMicRecording(); }
    };

    window.onSampleLangChange = function () {
        var msm = $('sample_language').value === 'msm';
        $('dictWrap').classList.toggle('d-none', !msm);
        if (!msm) { $('sample_dictionary_entry_id').value = ''; }
    };

    window.onDictionaryPick = function (sel) {
        var opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.dataset.word) { return; }
        $('sample_text').value = opt.dataset.word;
        if (!$('sample_translation').value) { $('sample_translation').value = opt.dataset.meaning || ''; }
    };

    /* ── Missing pronunciations tabs, queue and batch recording ─────── */

    var queue = null;        // {lang, items, pos, done, total} while recording through a list
    var datasetDirty = false;

    function currentTab() {
        var c = document.querySelector('input[name="missing_tab"]:checked');
        return c ? c.value : 'msm';
    }

    window.renderMissing = function () {
        var lang  = currentTab();
        var items = QUEUES[lang] || [];
        var term  = ($('missingSearch').value || '').trim().toLowerCase();
        var shown = term ? items.filter(function (it) {
            return it.text.toLowerCase().indexOf(term) !== -1 || (it.translation || '').toLowerCase().indexOf(term) !== -1;
        }) : items;

        $('missingCountBadge').textContent = items.length + ' missing';
        $('missingHelp').textContent = lang === 'msm'
            ? 'Manobo dictionary entries with no approved recording, most-used first.'
            : (lang === 'ceb'
                ? 'Bisaya words used inside Manobo posts (translator fallback) with no recording, most-used first.'
                : LANG_NAMES[lang] + ' words in visible posts with no approved recording, most-used first.');
        $('btnBatch').disabled = !items.length;

        var body = $('missingRows');
        body.innerHTML = '';
        shown.slice(0, 200).forEach(function (it) {
            var tr = document.createElement('tr');
            var td1 = document.createElement('td'); td1.className = 'ps-2 font-bold vt-text-primary'; td1.textContent = it.text;
            var td2 = document.createElement('td'); td2.className = 'vt-text-secondary text-xs'; td2.textContent = it.translation || '—';
            var td3 = document.createElement('td'); td3.className = 'text-center';
            td3.innerHTML = it.used > 0 ? '<span class="vt-badge vt-badge-gold"></span>' : '<span class="vt-text-muted">—</span>';
            if (it.used > 0) { td3.firstChild.textContent = it.used; }
            var td4 = document.createElement('td'); td4.className = 'text-end pe-2';
            var btn = document.createElement('button');
            btn.type = 'button'; btn.className = 'btn-vt-gold';
            btn.innerHTML = '<i class="bi bi-mic-fill me-1"></i> Record';
            btn.setAttribute('aria-label', 'Record ' + it.text);
            btn.onclick = function () { window.recordFromQueue(lang, it.text); };
            td4.appendChild(btn);
            tr.append(td1, td2, td3, td4);
            body.appendChild(tr);
        });
        var empty = $('missingEmpty');
        empty.textContent = items.length ? 'No match for "' + term + '".' : 'Nothing missing — every ' + LANG_NAMES[lang] + ' word residents meet has an approved recording.';
        empty.classList.toggle('d-none', shown.length > 0);
    };

    /** Record one word, continuing through that language's list with "Save & Record Next". */
    window.recordFromQueue = function (lang, text) {
        var items = QUEUES[lang] || [];
        var pos = items.findIndex(function (it) { return it.text === text; });
        if (pos < 0) { window.openRecorder({ language: lang, text: text }); return; }
        queue = { lang: lang, items: items, pos: pos, done: 0, total: items.length };
        loadQueueItem();
    };

    window.startBatch = function () {
        var lang = currentTab();
        var items = QUEUES[lang] || [];
        if (!items.length) { return; }
        queue = { lang: lang, items: items, pos: 0, done: 0, total: items.length };
        loadQueueItem();
    };

    function loadQueueItem() {
        var it = queue.items[queue.pos];
        window.openRecorder({ language: queue.lang, text: it.text, translation: it.translation, dictionary_entry_id: it.dictionary_entry_id }, true);
        var info = $('queueInfo');
        info.innerHTML = '';
        var strong = document.createElement('strong');
        strong.textContent = (queue.done + 1) + ' of ' + queue.total;
        info.append(strong, document.createTextNode(' · Language: ' + LANG_NAMES[queue.lang] + ' · Text: ' + it.text + ' · Status: Missing recording'));
        info.classList.remove('d-none');
        $('btnSaveNext').classList.remove('d-none');
    }

    function advanceQueue() {
        queue.items.splice(queue.pos, 1);   // recorded — no longer missing
        queue.done++;
        if (queue.pos >= queue.items.length) { queue.pos = 0; }
        if (!queue.items.length) {
            showAlert($('recAlert'), 'success', 'Done — every word in this ' + LANG_NAMES[queue.lang] + ' list now has a recording.');
            $('queueInfo').classList.add('d-none');
            $('btnSaveNext').classList.add('d-none');
            return false;
        }
        loadQueueItem();
        return true;
    }

    window.filterUsage = function () {
        var c = document.querySelector('input[name="usage_tab"]:checked');
        var lang = c ? c.value : '';
        document.querySelectorAll('#usageRows tr').forEach(function (tr) {
            tr.classList.toggle('d-none', !!lang && tr.dataset.lang !== lang);
        });
    };

    document.addEventListener('DOMContentLoaded', function () { window.renderMissing(); });

    /** Open the recorder, optionally pre-filled (Record buttons) or for re-recording a sample. */
    window.openRecorder = function (preset, fromQueue) {
        preset = preset || {};
        if (!fromQueue) {
            queue = null;
            $('queueInfo').classList.add('d-none');
            $('btnSaveNext').classList.add('d-none');
        }
        var keepConsent = fromQueue && $('consent_confirmed').checked;
        var keepSpeaker = fromQueue ? $('sample_speaker').value : '';
        var keepVoice   = fromQueue ? $('sample_voice_type').value : '';
        var form = $('addVoiceSampleForm');
        form.reset();
        rec.blob = null; rec.file = null; rec.duration = null;
        rec.rerecordId = preset.rerecord ? preset.id : null;
        $('recAlert').classList.add('d-none');
        $('micPreview').classList.add('d-none');
        $('btnStartRec').querySelector('span').textContent = 'Start Recording';
        setRecStatus('');
        $('src_mic').checked = true;
        window.onAudioSourceChange();

        $('sample_language').value = preset.language || 'msm';
        window.onSampleLangChange();
        $('sample_text').value = preset.text || '';
        $('sample_translation').value = preset.translation || '';
        if (preset.dictionary_entry_id) { $('sample_dictionary_entry_id').value = String(preset.dictionary_entry_id); }
        if (preset.speaker_label) { $('sample_speaker').value = preset.speaker_label; }
        if (preset.voice_type) { $('sample_voice_type').value = preset.voice_type; }
        // Batch recording keeps the speaker and consent between words.
        if (fromQueue) {
            $('consent_confirmed').checked = keepConsent;
            if (keepSpeaker) { $('sample_speaker').value = keepSpeaker; }
            if (keepVoice) { $('sample_voice_type').value = keepVoice; }
        }

        var re = !!rec.rerecordId;
        $('rec_sample_id').value = re ? preset.id : '';
        $('recModalTitle').textContent = re ? 'Re-record "' + preset.text + '"' : (preset.text ? 'Record "' + preset.text + '"' : 'Add Voice Sample');
        ['sample_language', 'sample_text', 'sample_translation', 'sample_dictionary_entry_id', 'sample_speaker', 'sample_voice_type', 'sample_post_link', 'sample_notes']
            .forEach(function (id) { $(id).disabled = re; });

        bootstrap.Modal.getOrCreateInstance($('addSampleModal')).show();
        setTimeout(function () { (preset.text ? $('btnStartRec') : $('sample_text')).focus(); }, fromQueue ? 50 : 400);
    };

    $('addSampleModal').addEventListener('hide.bs.modal', function () {
        if (rec.recorder && rec.recorder.state !== 'inactive') { window.stopMicRecording(); }
        var player = $('recAudioPlayer');
        player.pause();
    });
    $('addSampleModal').addEventListener('hidden.bs.modal', function () {
        // After recording several words without reloading, refresh the counts once.
        if (datasetDirty) { reloadWithMessage('Recordings saved. Counts and lists are updated.'); }
    });

    var pendingSave = null;
    var pendingNext = false;

    window.submitSample = function (saveAs, duplicateMode, next) {
        var alertEl = $('recAlert');
        var useFile = $('src_file').checked;
        var audio   = useFile ? rec.file : rec.blob;

        if (rec.recorder && rec.recorder.state === 'recording') {
            showAlert(alertEl, 'warning', 'Stop the recording first.');
            return;
        }
        if (!rec.rerecordId && !$('sample_text').value.trim()) {
            showAlert(alertEl, 'warning', 'Type the word or phrase that was recorded.');
            $('sample_text').focus();
            return;
        }
        if (!audio) {
            showAlert(alertEl, 'warning', useFile ? 'Choose an audio file to upload.' : 'Record the pronunciation first.');
            return;
        }
        if (!rec.rerecordId && !$('consent_confirmed').checked) {
            showAlert(alertEl, 'warning', 'Confirm that the speaker agreed to this recording being used.');
            $('consent_confirmed').focus();
            return;
        }

        var fd = new FormData($('addVoiceSampleForm'));
        var name = useFile ? audio.name : ('recording.' + extFor(rec.mime));
        fd.set('audio_file', audio, name);
        fd.set('save_as', saveAs);
        if (rec.duration) { fd.set('duration', rec.duration.toFixed(2)); }
        if (duplicateMode) { fd.set('duplicate_mode', duplicateMode); }

        var url = URLS.store;
        if (rec.rerecordId) {
            url = URLS.update;
            fd.set('action', 'rerecord');
            fd.set('id', rec.rerecordId);
        }

        var buttons = [$('btnSavePending'), $('btnSaveApprove'), $('btnSaveNext')];
        buttons.forEach(function (b) { b.disabled = true; });
        showAlert(alertEl, 'info', 'Uploading…');

        post(url, fd).then(function (r) {
            buttons.forEach(function (b) { b.disabled = false; });
            if (r.ok) {
                if (next && queue) {
                    datasetDirty = true;
                    var msg = r.data.message;
                    if (advanceQueue()) { showAlert(alertEl, 'success', msg + ' Next word loaded.'); }
                    return;
                }
                alertEl.classList.add('d-none');
                datasetDirty = false;
                bootstrap.Modal.getInstance($('addSampleModal')).hide();
                reloadWithMessage(r.data.message);
                return;
            }
            if (r.status === 409 && r.data.duplicate) {
                alertEl.classList.add('d-none');
                pendingSave = saveAs;
                pendingNext = !!next;
                $('duplicateText').textContent = r.data.error + ' Replace it with this new recording, or keep both as versions? (The newest approved version is the one residents hear.)';
                bootstrap.Modal.getOrCreateInstance($('duplicateModal')).show();
                return;
            }
            showAlert(alertEl, 'danger', r.data.error || 'The recording could not be saved.');
        });
    };

    window.resolveDuplicate = function (mode) {
        bootstrap.Modal.getInstance($('duplicateModal')).hide();
        window.submitSample(pendingSave || 'approved', mode, pendingNext);
    };

    /* ── Interactive Voice Tester (same resolver + player as residents) ─ */

    var tester = new window.VoiceSegmentPlayer();

    window.setTestText = function (lang, text) {
        var radio = $('lang_' + lang);
        if (radio) { radio.checked = true; }
        $('test_text').value = text;
        $('test_text').focus();
    };

    window.quickTestLanguage = function (lang) {
        var radio = $('lang_' + lang);
        if (radio) { radio.checked = true; }
        $('testVoiceCard').scrollIntoView({ behavior: 'smooth' });
        $('test_text').focus();
    };

    window.stopVoiceTest = function () {
        tester.stop();
        $('btnTestStop').classList.add('d-none');
        document.querySelectorAll('#testSegments .vt-badge').forEach(function (b) { b.style.outline = ''; });
    };

    window.runVoiceTest = function (event) {
        event.preventDefault();
        var checked = document.querySelector('input[name="test_language"]:checked');
        var lang = checked ? checked.value : 'msm';
        var text = $('test_text').value.trim();
        $('testTextValidation').classList.toggle('d-none', !!text);
        if (!text) { $('test_text').focus(); return; }

        tester.unlock();   // inside the click, for iOS
        $('testVoiceSpinner').classList.remove('d-none');

        var fd = new FormData();
        fd.set('language', lang);
        fd.set('text', text);
        post(URLS.test, fd).then(function (r) {
            $('testVoiceSpinner').classList.add('d-none');
            var box = $('testResultBox');
            box.classList.remove('d-none');
            var badge = $('testSourceBadge');
            var segBox = $('testSegments');
            segBox.innerHTML = '';

            if (!r.ok) {
                badge.className = 'vt-badge vt-badge-red';
                badge.textContent = 'Error';
                $('testMessage').textContent = r.data.error || 'Preview failed.';
                return;
            }

            var d = r.data;
            var recOnly = d.fallback === 'recorded_only';
            badge.className = 'vt-badge ' + (d.missing === 0 ? 'vt-badge-green' : (d.recorded ? 'vt-badge-gold' : 'vt-badge-blue'));
            badge.textContent = d.missing === 0 ? 'All recorded' : (d.recorded ? (recOnly ? 'Partly recorded' : 'Recordings + device voice') : (recOnly ? 'Nothing recorded' : 'Device voice only'));
            $('testProfileLabel').textContent = d.profile_name ? 'Profile: ' + d.profile_name : '';
            if (recOnly) {
                $('testMessage').textContent = d.recorded
                    ? d.recorded + ' recorded segment(s) play; ' + d.missing + ' unrecorded part(s) are skipped (Recorded voice only).'
                    : 'No approved recording matches this text yet, so residents would hear nothing (Recorded voice only). Record the words below.';
            } else {
                $('testMessage').textContent = d.recorded
                    ? d.recorded + ' recorded segment(s), ' + d.missing + ' read by the device voice' + (d.approximate && d.missing ? ' (an approximation)' : '') + '.'
                    : 'No approved recording matches this text yet — it is read by the device voice' + (d.approximate ? ', which is only an approximation' : '') + '.';
            }

            d.segments.forEach(function (seg) {
                var chip = document.createElement('span');
                chip.className = 'vt-badge ' + (seg.type === 'recorded' ? 'vt-badge-green' : 'vt-badge-neutral');
                chip.title = seg.type === 'recorded'
                    ? 'Recording #' + seg.sample_id + (seg.language === 'ceb' ? ' (Bisaya)' : '') + (seg.speaker ? ' — ' + seg.speaker : '')
                    : (recOnly ? 'No recording — skipped' : 'No recording — device voice');
                chip.innerHTML = '<i class="bi ' + (seg.type === 'recorded' ? 'bi-mic-fill' : 'bi-robot') + ' me-1"></i>';
                chip.appendChild(document.createTextNode(seg.text));
                segBox.appendChild(chip);
            });

            var voice = null;
            if (window.voicePickVoice) { voice = window.voicePickVoice(d.voices || []); }
            $('btnTestStop').classList.remove('d-none');
            var chips = segBox.querySelectorAll('.vt-badge');
            tester.play(d.segments, {
                fallback: d.fallback,
                lang: d.speech_lang || 'fil-PH',
                voice: voice,
                rate: d.speaking_rate || 1,
                onSegment: function (i) {
                    chips.forEach(function (c, k) { c.style.outline = k === i ? '2px solid #c8992e' : ''; });
                },
                onDone: function () { window.stopVoiceTest(); },
            });
        });
    };

    /* ── Diagnostics (Super Admin) ───────────────────────────────────── */

    window.runDiagnostics = function () {
        var box = $('diagnosticsBox');
        box.classList.remove('d-none');
        box.textContent = 'Running diagnostics…';
        post(URLS.diag, new FormData()).then(function (r) {
            if (!r.ok) { box.textContent = r.data.error || 'Diagnostics failed.'; return; }
            var list = document.createElement('ul');
            list.className = 'list-unstyled mb-0 text-xs';
            r.data.results.forEach(function (t) {
                var li = document.createElement('li');
                li.className = 'py-1 border-bottom';
                var label = t.status === 'passed' ? '✅ Passed' : (t.status === 'config_missing' ? '⚠️ Configuration missing' : '❌ Failed');
                var strong = document.createElement('strong');
                strong.textContent = t.name + ': ';
                li.appendChild(strong);
                li.appendChild(document.createTextNode(label + ' — ' + t.detail));
                list.appendChild(li);
            });
            box.innerHTML = '';
            box.appendChild(list);
        });
    };
})();
</script>

<?php
$content   = ob_get_clean();
$pageTitle = 'Voice Training & AI Dataset Hub';
require __DIR__ . '/../../layouts/admin.php';
