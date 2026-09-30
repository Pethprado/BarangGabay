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

<div class="w-100" id="voice-training-app">
    <!-- ── 1. Page Header ─────────────────────────────────────────────────── -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div class="vt-wrap" style="max-width: 820px;">
            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                <span class="vt-badge vt-badge-gold">
                    <i class="bi bi-cpu-fill me-1"></i>Language AI & Voice
                </span>
                <span class="vt-badge vt-badge-green">
                    <i class="bi bi-circle-fill me-1" style="font-size: 7px;"></i>Live Dataset Active
                </span>
            </div>
            <h1 class="h3 font-extrabold vt-text-primary mb-1 d-flex align-items-center gap-2 flex-wrap">
                <i class="bi bi-mic-fill" style="color: #c8992e;"></i>
                <span>Voice Training & AI Dataset Management</span>
            </h1>
            <p class="vt-text-secondary mb-0 text-sm vt-wrap" style="line-height: 1.45;">
                Train native voice pronunciations, manage Manobo word audio mappings, and configure language voice profiles for resident readers.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="dropdown">
                <button class="btn-vt-outline dropdown-toggle font-semibold" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-download me-1"></i> Export Dataset
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg rounded-xl border-0 p-2">
                    <li><a class="dropdown-item rounded-lg py-1.5 text-xs font-medium" href="<?= e(route('admin/voice-training/export?format=csv')) ?>"><i class="bi bi-filetype-csv me-2 text-success fs-6"></i>Export as CSV</a></li>
                    <li><a class="dropdown-item rounded-lg py-1.5 text-xs font-medium" href="<?= e(route('admin/voice-training/export?format=json')) ?>"><i class="bi bi-filetype-json me-2 text-info fs-6"></i>Export as JSON</a></li>
                </ul>
            </div>
            <button type="button" class="btn-vt-primary" data-bs-toggle="modal" data-bs-target="#addSampleModal">
                <i class="bi bi-plus-circle-fill fs-6"></i> + Add Voice Sample
            </button>
        </div>
    </div>

    <!-- ── 2. Top Summary Metric Cards Bar ───────────────────────────────── -->
    <?php
        $totalAllSamples = (int)($stats['msm']['total'] ?? 0) + (int)($stats['fil']['total'] ?? 0) + (int)($stats['en']['total'] ?? 0);
        $approvedAllSamples = (int)($stats['msm']['approved'] ?? 0) + (int)($stats['fil']['approved'] ?? 0) + (int)($stats['en']['approved'] ?? 0);
    ?>
    <div class="vt-stats-grid">
        <!-- Card 1: Active Profiles -->
        <div class="vt-stat-card">
            <div class="vt-stat-top">
                <span class="vt-stat-title">Active Profiles</span>
                <div class="vt-stat-icon" style="background-color: rgba(200, 153, 46, 0.15); color: #c8992e;">
                    <i class="bi bi-soundwave"></i>
                </div>
            </div>
            <div class="vt-stat-value-row">
                <span class="vt-stat-value">3 Profiles</span>
                <span class="vt-badge vt-badge-green"><i class="bi bi-check-circle-fill me-1"></i>Ready</span>
            </div>
            <span class="vt-stat-sub">Manobo, Filipino, English</span>
        </div>

        <!-- Card 2: Manobo Coverage -->
        <div class="vt-stat-card">
            <div class="vt-stat-top">
                <span class="vt-stat-title">Manobo Coverage</span>
                <div class="vt-stat-icon" style="background-color: rgba(22, 101, 52, 0.15); color: #166534;">
                    <i class="bi bi-journal-check"></i>
                </div>
            </div>
            <div class="vt-stat-value-row">
                <span class="vt-stat-value"><?= number_format((float)($coverage['coverage_percentage'] ?? 0), 1) ?>%</span>
                <span class="vt-stat-sub mb-0"><?= (int)($coverage['words_with_audio'] ?? 0) ?>/<?= (int)($coverage['total_dictionary_words'] ?? 0) ?> words</span>
            </div>
            <div class="vt-progress-track mt-2">
                <div class="vt-progress-fill" style="width: <?= (float)($coverage['coverage_percentage'] ?? 0) ?>%;"></div>
            </div>
        </div>

        <!-- Card 3: Total Audio Samples -->
        <div class="vt-stat-card">
            <div class="vt-stat-top">
                <span class="vt-stat-title">Total Samples</span>
                <div class="vt-stat-icon" style="background-color: rgba(30, 64, 175, 0.15); color: #1e40af;">
                    <i class="bi bi-database-fill-check"></i>
                </div>
            </div>
            <div class="vt-stat-value-row">
                <span class="vt-stat-value"><?= $totalAllSamples ?></span>
                <span class="vt-badge vt-badge-green"><?= $approvedAllSamples ?> Approved</span>
            </div>
            <span class="vt-stat-sub">Audio dataset files recorded</span>
        </div>

        <!-- Card 4: Missing Pronunciations -->
        <div class="vt-stat-card">
            <div class="vt-stat-top">
                <span class="vt-stat-title">Missing Samples</span>
                <div class="vt-stat-icon" style="background-color: rgba(159, 18, 57, 0.15); color: #9f1239;">
                    <i class="bi bi-mic-mute-fill"></i>
                </div>
            </div>
            <div class="vt-stat-value-row">
                <span class="vt-stat-value"><?= (int)($coverage['missing_audio'] ?? 0) ?></span>
                <span class="vt-badge vt-badge-red">Dictionary Words</span>
            </div>
            <span class="vt-stat-sub">Awaiting native speaker recording</span>
        </div>
    </div>

    <!-- ── 3. Language Profile Cards (Responsive 3-Column Desktop Grid) ──── -->
    <div class="vt-profiles-grid">
        <!-- Manobo Voice Card (Primary Focus) -->
        <div class="vt-profile-card vt-featured-manobo">
            <div class="vt-accent-bar vt-accent-bar-manobo"></div>
            <div class="vt-profile-header">
                <div class="vt-wrap">
                    <div class="d-flex align-items-center gap-1.5 mb-1.5 flex-wrap">
                        <span class="vt-badge vt-badge-gold">PRIMARY LANGUAGE</span>
                        <span class="vt-badge vt-badge-green"><i class="bi bi-check-circle-fill me-1"></i>ACTIVE</span>
                    </div>
                    <h2 class="vt-lang-title">Manobo (MN)</h2>
                </div>
                <div class="vt-lang-icon-box" style="background-color: #c8992e; color: #ffffff;">
                    <i class="bi bi-mic-fill"></i>
                </div>
            </div>

            <div class="vt-active-box">
                <span class="vt-active-label">Active Profile Name</span>
                <div class="vt-active-name">
                    <i class="bi bi-person-bounding-box" style="color: #c8992e; font-size: 1.1rem; flex-shrink: 0; margin-top: 2px;"></i>
                    <span class="vt-wrap"><?= e($activeProfiles['msm']['profile_name'] ?? 'Manobo Community Voice') ?></span>
                </div>
                <span class="vt-active-provider">Provider: <?= e($activeProfiles['msm']['provider'] ?? 'dataset_hybrid') ?></span>
            </div>

            <div class="vt-stat-pills">
                <div class="vt-stat-pill">
                    <span class="vt-stat-pill-label">Samples</span>
                    <span class="vt-stat-pill-val"><?= (int)($stats['msm']['total'] ?? 0) ?></span>
                </div>
                <div class="vt-stat-pill">
                    <span class="vt-stat-pill-label">Approved</span>
                    <span class="vt-stat-pill-val vt-stat-pill-val-success"><?= (int)($stats['msm']['approved'] ?? 0) ?></span>
                </div>
                <div class="vt-stat-pill">
                    <span class="vt-stat-pill-label">Pending</span>
                    <span class="vt-stat-pill-val vt-stat-pill-val-warning"><?= (int)($stats['msm']['pending'] ?? 0) ?></span>
                </div>
            </div>

            <!-- Manobo Voice Coverage Bar -->
            <div class="vt-coverage-block">
                <div class="vt-coverage-top">
                    <span>Manobo Voice Coverage</span>
                    <span style="color: #c8992e;"><?= number_format((float)($coverage['coverage_percentage'] ?? 0), 1) ?>%</span>
                </div>
                <div class="vt-progress-track">
                    <div class="vt-progress-fill" style="width: <?= (float)($coverage['coverage_percentage'] ?? 0) ?>%;"></div>
                </div>
                <span class="vt-coverage-sub">
                    <?= (int)($coverage['words_with_audio'] ?? 0) ?> of <?= (int)($coverage['total_dictionary_words'] ?? 0) ?> dictionary words recorded
                </span>
            </div>

            <div class="vt-card-actions">
                <button type="button" class="btn-vt-secondary" data-bs-toggle="modal" data-bs-target="#profileModal_msm" style="flex: 1 1 auto;">
                    <i class="bi bi-gear-fill me-1"></i> Configure Profile
                </button>
                <button type="button" onclick="quickTestLanguage('msm')" class="btn-vt-action" title="Test Manobo Voice" style="flex: 0 1 auto;">
                    <i class="bi bi-volume-up-fill me-1"></i> Quick Test
                </button>
            </div>
        </div>

        <!-- Filipino Voice Card -->
        <div class="vt-profile-card">
            <div class="vt-accent-bar vt-accent-bar-filipino"></div>
            <div class="vt-profile-header">
                <div class="vt-wrap">
                    <div class="d-flex align-items-center gap-1.5 mb-1.5 flex-wrap">
                        <span class="vt-badge vt-badge-blue">NATIONAL</span>
                        <span class="vt-badge vt-badge-green"><i class="bi bi-check-circle-fill me-1"></i>ACTIVE</span>
                    </div>
                    <h2 class="vt-lang-title">Filipino (FIL)</h2>
                </div>
                <div class="vt-lang-icon-box" style="background-color: rgba(26, 107, 58, 0.15); color: #1a6b3a;">
                    <i class="bi bi-translate"></i>
                </div>
            </div>

            <div class="vt-active-box">
                <span class="vt-active-label">Active Profile Name</span>
                <div class="vt-active-name">
                    <i class="bi bi-person-check" style="color: #1a6b3a; font-size: 1.1rem; flex-shrink: 0; margin-top: 2px;"></i>
                    <span class="vt-wrap"><?= e($activeProfiles['fil']['profile_name'] ?? 'Filipino Default Voice') ?></span>
                </div>
                <span class="vt-active-provider">Provider: <?= e($activeProfiles['fil']['provider'] ?? 'system') ?></span>
            </div>

            <div class="vt-stat-pills vt-stat-pills-2 mb-4">
                <div class="vt-stat-pill">
                    <span class="vt-stat-pill-label">Total Samples</span>
                    <span class="vt-stat-pill-val"><?= (int)($stats['fil']['total'] ?? 0) ?></span>
                </div>
                <div class="vt-stat-pill">
                    <span class="vt-stat-pill-label">Approved</span>
                    <span class="vt-stat-pill-val vt-stat-pill-val-success"><?= (int)($stats['fil']['approved'] ?? 0) ?></span>
                </div>
            </div>

            <div class="vt-card-actions">
                <button type="button" class="btn-vt-secondary" data-bs-toggle="modal" data-bs-target="#profileModal_fil" style="flex: 1 1 auto;">
                    <i class="bi bi-gear-fill me-1"></i> Configure Profile
                </button>
                <button type="button" onclick="quickTestLanguage('fil')" class="btn-vt-action" title="Test Filipino Voice" style="flex: 0 1 auto;">
                    <i class="bi bi-volume-up-fill me-1"></i> Quick Test
                </button>
            </div>
        </div>

        <!-- English Voice Card -->
        <div class="vt-profile-card">
            <div class="vt-accent-bar vt-accent-bar-english"></div>
            <div class="vt-profile-header">
                <div class="vt-wrap">
                    <div class="d-flex align-items-center gap-1.5 mb-1.5 flex-wrap">
                        <span class="vt-badge vt-badge-neutral">INTERNATIONAL</span>
                        <span class="vt-badge vt-badge-green"><i class="bi bi-check-circle-fill me-1"></i>ACTIVE</span>
                    </div>
                    <h2 class="vt-lang-title">English (EN)</h2>
                </div>
                <div class="vt-lang-icon-box" style="background-color: rgba(71, 85, 105, 0.15); color: #475569;">
                    <i class="bi bi-globe"></i>
                </div>
            </div>

            <div class="vt-active-box">
                <span class="vt-active-label">Active Profile Name</span>
                <div class="vt-active-name">
                    <i class="bi bi-person-check" style="color: #475569; font-size: 1.1rem; flex-shrink: 0; margin-top: 2px;"></i>
                    <span class="vt-wrap"><?= e($activeProfiles['en']['profile_name'] ?? 'English Default Voice') ?></span>
                </div>
                <span class="vt-active-provider">Provider: <?= e($activeProfiles['en']['provider'] ?? 'system') ?></span>
            </div>

            <div class="vt-stat-pills vt-stat-pills-2 mb-4">
                <div class="vt-stat-pill">
                    <span class="vt-stat-pill-label">Total Samples</span>
                    <span class="vt-stat-pill-val"><?= (int)($stats['en']['total'] ?? 0) ?></span>
                </div>
                <div class="vt-stat-pill">
                    <span class="vt-stat-pill-label">Approved</span>
                    <span class="vt-stat-pill-val vt-stat-pill-val-success"><?= (int)($stats['en']['approved'] ?? 0) ?></span>
                </div>
            </div>

            <div class="vt-card-actions">
                <button type="button" class="btn-vt-secondary" data-bs-toggle="modal" data-bs-target="#profileModal_en" style="flex: 1 1 auto;">
                    <i class="bi bi-gear-fill me-1"></i> Configure Profile
                </button>
                <button type="button" onclick="quickTestLanguage('en')" class="btn-vt-action" title="Test English Voice" style="flex: 0 1 auto;">
                    <i class="bi bi-volume-up-fill me-1"></i> Quick Test
                </button>
            </div>
        </div>
    </div>

    <!-- ── 4. Main 2-Column Split: Test Voice Player & Missing Queue ──────── -->
    <div class="vt-split-grid">
        <!-- Interactive Test Voice Player Card -->
        <div class="vt-card" id="testVoiceCard">
            <div class="d-flex align-items-center justify-content-between mb-1 gap-2 flex-wrap">
                <h3 class="h5 font-extrabold vt-text-primary mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-play-circle-fill" style="color: #c8992e;"></i>
                    <span>Interactive Voice Tester</span>
                </h3>
                <span class="vt-badge vt-badge-gold">Live Voice Preview</span>
            </div>
            <p class="vt-text-muted text-xs mb-3">Test dataset recordings and active profile synthesis live before resident release.</p>

            <form id="testVoiceForm" onsubmit="runVoiceTest(event)">
                <div class="mb-3">
                    <label class="form-label text-xs font-bold vt-text-primary mb-1.5">Select Language</label>
                    <div class="vt-lang-selector">
                        <input type="radio" class="btn-check" name="test_language_radio" id="lang_msm" value="msm" checked onchange="document.getElementById('test_language').value='msm'">
                        <label class="vt-lang-btn" for="lang_msm">Manobo (MN)</label>

                        <input type="radio" class="btn-check" name="test_language_radio" id="lang_fil" value="fil" onchange="document.getElementById('test_language').value='fil'">
                        <label class="vt-lang-btn" for="lang_fil">Filipino (FIL)</label>

                        <input type="radio" class="btn-check" name="test_language_radio" id="lang_en" value="en" onchange="document.getElementById('test_language').value='en'">
                        <label class="vt-lang-btn" for="lang_en">English (EN)</label>
                    </div>
                    <input type="hidden" id="test_language" value="msm">
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-1">
                        <label class="form-label text-xs font-bold vt-text-primary mb-0">Text to Speak</label>
                        <div class="d-flex gap-1 flex-wrap">
                            <button type="button" class="vt-chip" onclick="document.getElementById('test_text').value='Maayong adlaw abaga'">+ Maayong adlaw</button>
                            <button type="button" class="vt-chip" onclick="document.getElementById('test_text').value='Barangay Notice'">+ Notice</button>
                        </div>
                    </div>
                    <input type="text" id="test_text" class="vt-input" placeholder="e.g. Maayong adlaw / abaga / barangay announcement" required>
                    <div id="testTextValidation" class="text-xs text-danger mt-1 d-none">Pakilagay ang text na gustong patugtugin.</div>
                </div>

                <div class="d-flex align-items-center gap-2 mt-auto flex-wrap">
                    <button type="submit" id="btnTestVoice" class="btn-vt-primary">
                        <i class="bi bi-volume-up-fill fs-6"></i> Play Voice Preview
                    </button>
                    <span id="testVoiceSpinner" class="spinner-border spinner-border-sm text-success d-none" role="status"></span>
                </div>
            </form>

            <!-- Interactive Voice Output Box -->
            <div id="testResultBox" class="vt-result-box d-none">
                <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                    <span id="testSourceBadge" class="vt-badge vt-badge-green">Dataset Recording</span>
                    <span id="testProfileLabel" class="text-xs font-mono vt-text-secondary">Profile</span>
                </div>
                <p id="testMessage" class="text-xs vt-text-primary mb-2 font-medium vt-wrap"></p>
                <audio id="testAudioPlayer" controls class="w-100 rounded-lg d-none mt-1"></audio>
            </div>
        </div>

        <!-- Missing Manobo Pronunciations Queue -->
        <div class="vt-card">
            <div class="d-flex align-items-center justify-content-between mb-1 gap-2 flex-wrap">
                <h3 class="h5 font-extrabold vt-text-primary mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-mic-mute-fill" style="color: #9f1239;"></i>
                    <span>Missing Manobo Pronunciations</span>
                </h3>
                <span class="vt-badge vt-badge-red">
                    <?= count($missingPronunciations) ?> Pending Audio
                </span>
            </div>
            <p class="vt-text-muted text-xs mb-3">Dictionary entries awaiting native speaker voice recordings.</p>

            <?php if (empty($missingPronunciations)): ?>
                <div class="text-center py-5 vt-text-muted text-xs my-auto">
                    <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-2"></i>
                    <span class="font-bold vt-text-primary d-block text-sm">100% Manobo Dictionary Audio Coverage!</span>
                    All dictionary entries currently have approved native pronunciations.
                </div>
            <?php else: ?>
                <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                    <table class="vt-table">
                        <thead>
                            <tr>
                                <th class="ps-2">Manobo Word</th>
                                <th>Tagalog / English</th>
                                <th class="text-end pe-2">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($missingPronunciations as $m): 
                                $mWord = $m['manobo'] ?? $m['manobo_word'] ?? '';
                                $mTranslation = $m['tagalog'] ?? $m['tagalog_word'] ?? $m['english'] ?? $m['english_word'] ?? '';
                            ?>
                                <tr>
                                    <td class="ps-2 font-bold vt-text-primary"><?= e($mWord) ?></td>
                                    <td class="vt-text-secondary"><?= e($mTranslation) ?></td>
                                    <td class="text-end pe-2">
                                        <button type="button" class="btn-vt-gold" onclick="quickRecordForWord(<?= (int)$m['id'] ?>, '<?= e(addslashes($mWord)) ?>')">
                                            <i class="bi bi-mic-fill me-1"></i> Record
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-auto pt-3 border-top text-end">
                    <a href="<?= e(route('admin/voice-training?language=msm&status=PENDING')) ?>" class="text-xs font-bold text-decoration-none" style="color: #b45309;">
                        View All Pending Manobo Words <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── 5. Voice Dataset Explorer & Samples Table ─────────────────────── -->
    <div class="vt-explorer-card">
        <div class="vt-explorer-header">
            <div class="row g-3 align-items-center">
                <div class="col-12 col-lg-5">
                    <h3 class="h5 font-extrabold vt-text-primary mb-1 d-flex align-items-center gap-2">
                        <i class="bi bi-collection-play-fill" style="color: #c8992e;"></i>
                        <span>Voice Dataset Samples Management</span>
                    </h3>
                    <p class="text-xs vt-text-muted mb-0">Search, review, play, and approve recorded voice samples across all languages.</p>
                </div>
                <div class="col-12 col-lg-7">
                    <form method="get" action="<?= e(route('admin/voice-training')) ?>" class="row g-2">
                        <div class="col-12 col-sm-5">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text border-end-0 vt-text-muted" style="background-color: var(--surface-muted, #f1e6d2); border-color: var(--border, #e4d7c2);">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" name="search" class="form-control form-control-sm border-start-0 vt-input" style="border-top-left-radius: 0 !important; border-bottom-left-radius: 0 !important;" placeholder="Search word, phrase..." value="<?= e($filters['search']) ?>">
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <select name="language" class="form-select form-select-sm vt-select" onchange="this.form.submit()">
                                <option value="">All Languages</option>
                                <option value="msm" <?= $filters['language'] === 'msm' ? 'selected' : '' ?>>Manobo (MN)</option>
                                <option value="fil" <?= $filters['language'] === 'fil' ? 'selected' : '' ?>>Filipino (FIL)</option>
                                <option value="en" <?= $filters['language'] === 'en' ? 'selected' : '' ?>>English (EN)</option>
                            </select>
                        </div>
                        <div class="col-6 col-sm-3">
                            <select name="status" class="form-select form-select-sm vt-select" onchange="this.form.submit()">
                                <option value="">All Statuses</option>
                                <option value="APPROVED" <?= $filters['status'] === 'APPROVED' ? 'selected' : '' ?>>Approved</option>
                                <option value="PENDING" <?= $filters['status'] === 'PENDING' ? 'selected' : '' ?>>Pending</option>
                                <option value="DRAFT" <?= $filters['status'] === 'DRAFT' ? 'selected' : '' ?>>Draft</option>
                                <option value="REJECTED" <?= $filters['status'] === 'REJECTED' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-1">
                            <a href="<?= e(route('admin/voice-training')) ?>" class="btn-vt-outline w-100 justify-content-center" style="padding: 6px;" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="p-0">
            <?php if (empty($samples)): ?>
                <div class="text-center py-5 vt-text-muted">
                    <i class="bi bi-mic-mute fs-1 d-block mb-2"></i>
                    <span class="font-bold vt-text-primary d-block">Walang Nahanap na Voice Samples</span>
                    <span class="text-xs">Mag-record o mag-upload ng bagong boses upang masimulan ang dataset.</span>
                </div>
            <?php else: ?>
                <!-- Desktop Table View -->
                <div class="table-responsive d-none d-md-block">
                    <table class="vt-table">
                        <thead>
                            <tr>
                                <th class="ps-4">Text / Word</th>
                                <th>Language</th>
                                <th>Speaker Label</th>
                                <th>Audio Preview</th>
                                <th>Dictionary Match</th>
                                <th>Status</th>
                                <th class="pe-4 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($samples as $s): 
                                $sManobo = $s['manobo'] ?? $s['manobo_word'] ?? '';
                            ?>
                                <tr>
                                    <td class="ps-4 font-bold vt-text-primary vt-wrap" style="max-width: 200px;">
                                        <?= e($s['text']) ?>
                                        <?php if (!empty($s['notes'])): ?>
                                            <span class="d-block text-xs font-normal vt-text-muted vt-wrap" title="<?= e($s['notes']) ?>">
                                                <i class="bi bi-info-circle me-1"></i><?= e(mb_strimwidth($s['notes'], 0, 36, '...')) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($s['language'] === 'msm'): ?>
                                            <span class="vt-badge vt-badge-gold">Manobo</span>
                                        <?php elseif ($s['language'] === 'fil'): ?>
                                            <span class="vt-badge vt-badge-blue">Filipino</span>
                                        <?php else: ?>
                                            <span class="vt-badge vt-badge-neutral">English</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="vt-text-secondary text-xs">
                                        <span class="font-semibold vt-text-primary d-block"><?= e($s['speaker_label'] ?: 'Community') ?></span>
                                        <span class="vt-text-muted text-[10px] uppercase font-mono"><?= e($s['voice_type']) ?></span>
                                    </td>
                                    <td style="min-width: 180px;">
                                        <?php if (!empty($s['audio_url'])): ?>
                                            <audio controls preload="none" class="rounded-lg" style="max-width: 190px; height: 32px;">
                                                <source src="<?= e($s['audio_url']) ?>" type="<?= e($s['mime_type'] ?: 'audio/webm') ?>">
                                                Playback unavailable.
                                            </audio>
                                        <?php else: ?>
                                            <span class="text-xs vt-text-muted italic">No audio recorded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-xs">
                                        <?php if (!empty($sManobo)): ?>
                                            <span class="vt-badge vt-badge-green">
                                                <i class="bi bi-book me-1"></i><?= e($sManobo) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="vt-text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($s['status'] === 'APPROVED'): ?>
                                            <span class="vt-badge vt-badge-green">Approved</span>
                                        <?php elseif ($s['status'] === 'PENDING'): ?>
                                            <span class="vt-badge vt-badge-gold">Pending</span>
                                        <?php elseif ($s['status'] === 'REJECTED'): ?>
                                            <span class="vt-badge vt-badge-red">Rejected</span>
                                        <?php else: ?>
                                            <span class="vt-badge vt-badge-neutral">Draft</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <div class="d-inline-flex gap-1.5 align-items-center">
                                            <?php if ($s['status'] !== 'APPROVED'): ?>
                                                <form method="post" action="<?= e(route('admin/voice-training/update')) ?>" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn-vt-primary" style="padding: 4px 8px; font-size: 0.72rem; border-radius: 6px;" title="Approve Sample">
                                                        <i class="bi bi-check-lg me-1"></i> Approve
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="post" action="<?= e(route('admin/voice-training/update')) ?>" class="d-inline" onsubmit="return confirm('Sigurado ka bang gustong idelete ang voice sample na ito?');">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-xs btn-outline-danger rounded-lg px-2 py-1" title="Delete Sample">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card List View -->
                <div class="d-block d-md-none p-3">
                    <div class="row g-3">
                        <?php foreach ($samples as $s): ?>
                            <div class="col-12">
                                <div class="vt-card p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
                                        <div class="vt-wrap">
                                            <h4 class="h6 font-bold vt-text-primary mb-0 vt-wrap"><?= e($s['text']) ?></h4>
                                            <span class="text-xs vt-text-muted d-block"><?= e($s['speaker_label'] ?: 'Community') ?></span>
                                        </div>
                                        <?php if ($s['status'] === 'APPROVED'): ?>
                                            <span class="vt-badge vt-badge-green">APPROVED</span>
                                        <?php else: ?>
                                            <span class="vt-badge vt-badge-gold"><?= e($s['status']) ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($s['audio_url'])): ?>
                                        <div class="my-2">
                                            <audio controls preload="none" class="w-100 rounded-lg" style="height: 36px;">
                                                <source src="<?= e($s['audio_url']) ?>" type="<?= e($s['mime_type'] ?: 'audio/webm') ?>">
                                            </audio>
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top flex-wrap gap-2">
                                        <span class="vt-badge vt-badge-neutral"><?= e($s['language']) ?></span>
                                        <div class="d-flex gap-1.5 align-items-center">
                                            <?php if ($s['status'] !== 'APPROVED'): ?>
                                                <form method="post" action="<?= e(route('admin/voice-training/update')) ?>" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn-vt-primary" style="padding: 4px 8px; font-size: 0.72rem; border-radius: 6px;">Approve</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="post" action="<?= e(route('admin/voice-training/update')) ?>" class="d-inline" onsubmit="return confirm('Sigurado ka bang gustong idelete ang voice sample na ito?');">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-xs btn-outline-danger rounded-md px-2 py-1"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="p-3 border-top d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 text-xs">
                        <span class="vt-text-muted font-medium">Showing <?= count($samples) ?> of <?= $pagination['total'] ?> voice dataset entries (Page <?= $pagination['page'] ?> of <?= $pagination['total_pages'] ?>)</span>
                        <div class="btn-group btn-group-sm">
                            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                                <a href="<?= e(route("admin/voice-training?page={$i}&language={$filters['language']}&status={$filters['status']}&search={$filters['search']}")) ?>" 
                                   class="btn <?= $i === $pagination['page'] ? 'btn-vt-primary font-bold' : 'btn-vt-outline' ?>" style="padding: 4px 10px; border-radius: 6px; margin: 0 1px;">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── 6. Modal: Add Voice Sample (Microphone Recording + File Upload) ────── -->
<div class="modal fade vt-modal" id="addSampleModal" tabindex="-1" aria-labelledby="addSampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content vt-modal-content">
            <div class="modal-header vt-modal-header">
                <h4 class="modal-title font-extrabold vt-text-primary d-flex align-items-center gap-2 h5 mb-0" id="addSampleModalLabel">
                    <i class="bi bi-mic-fill" style="color: #c8992e;"></i>Add Voice Sample
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addVoiceSampleForm" method="post" action="<?= e(route('admin/voice-training/samples')) ?>" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="modal-body vt-modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label text-xs font-bold vt-text-primary">Language <span class="text-danger">*</span></label>
                            <select name="language" id="sample_language" class="form-select form-select-sm vt-select" required>
                                <option value="msm" selected>Manobo (MN)</option>
                                <option value="fil">Filipino (FIL)</option>
                                <option value="en">English (EN)</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-8">
                            <label class="form-label text-xs font-bold vt-text-primary">Word or Phrase <span class="text-danger">*</span></label>
                            <input type="text" name="text" id="sample_text" class="vt-input" placeholder="e.g. abaga / Maayong adlaw sa inyong tanan" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label text-xs font-bold vt-text-primary">Link to Dictionary Entry (Optional)</label>
                            <select name="dictionary_entry_id" id="sample_dictionary_entry_id" class="form-select form-select-sm vt-select">
                                <option value="">-- Select Manobo Word --</option>
                                <?php foreach ($dictionaryWords as $dw): 
                                    $dwHead = $dw['manobo'] ?? $dw['manobo_word'] ?? '';
                                    $dwTrans = $dw['tagalog'] ?? $dw['tagalog_word'] ?? $dw['english'] ?? $dw['english_word'] ?? '';
                                ?>
                                    <option value="<?= (int)$dw['id'] ?>"><?= e($dwHead) ?> (<?= e($dwTrans) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label text-xs font-bold vt-text-primary">Speaker Name / Label</label>
                            <input type="text" name="speaker_label" class="vt-input" placeholder="e.g. Community Speaker">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label text-xs font-bold vt-text-primary">Voice Type</label>
                            <select name="voice_type" class="form-select form-select-sm vt-select">
                                <option value="community" selected>Community Voice</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="neutral">Neutral</option>
                            </select>
                        </div>

                        <!-- Audio Source Tabs -->
                        <div class="col-12">
                            <label class="form-label text-xs font-bold vt-text-primary">Audio Recording or File <span class="text-danger">*</span></label>
                            <ul class="nav nav-pills nav-fill p-1 rounded-xl mb-3" style="background-color: var(--surface-muted, #f1e6d2);" id="audioTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active rounded-lg text-xs font-bold py-2" id="record-tab" data-bs-toggle="tab" data-bs-target="#record-panel" type="button" role="tab">
                                        <i class="bi bi-mic-fill me-1"></i> Option A: Record Microphone
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link rounded-lg text-xs font-bold py-2" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload-panel" type="button" role="tab">
                                        <i class="bi bi-upload me-1"></i> Option B: Upload Audio File
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content" id="audioTabContent">
                                <!-- Record Panel -->
                                <div class="tab-pane fade show active p-4 rounded-2xl text-center" style="background-color: var(--surface-muted, #f1e6d2); border: 1px solid var(--border, #e4d7c2);" id="record-panel" role="tabpanel">
                                    <div id="micControls">
                                        <button type="button" id="btnStartRec" onclick="startMicRecording()" class="btn-vt-gold px-4 py-2 me-2">
                                            <i class="bi bi-record-fill me-1"></i> Start Recording
                                        </button>
                                        <button type="button" id="btnStopRec" onclick="stopMicRecording()" class="btn btn-dark btn-md rounded-xl font-bold px-4 py-2 me-2 d-none">
                                            <i class="bi bi-stop-fill me-1"></i> Stop Recording
                                        </button>
                                        <span id="recTimer" class="font-mono text-sm text-danger font-extrabold d-none">00:00</span>
                                    </div>

                                    <div id="micPreview" class="mt-3 d-none">
                                        <audio id="recAudioPlayer" controls class="w-100 rounded-lg mb-2" style="height: 38px;"></audio>
                                        <button type="button" onclick="resetMicRecording()" class="btn-vt-outline" style="padding: 4px 10px; font-size: 0.75rem;">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i> Re-record
                                        </button>
                                    </div>
                                    <input type="hidden" name="audio_base64" id="audio_base64">
                                </div>

                                <!-- Upload Panel -->
                                <div class="tab-pane fade p-4 rounded-2xl" style="background-color: var(--surface-muted, #f1e6d2); border: 1px solid var(--border, #e4d7c2);" id="upload-panel" role="tabpanel">
                                    <input type="file" name="audio_file" id="audio_file" class="form-control form-control-sm vt-input py-2" accept="audio/*,.mp3,.wav,.m4a,.ogg,.webm">
                                    <span class="text-[11px] vt-text-muted mt-1 d-block font-medium">Supported audio formats: MP3, WAV, M4A, OGG, WebM (Max 10 MB file size).</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label text-xs font-bold vt-text-primary">Notes / Context</label>
                            <textarea name="notes" class="vt-input" rows="2" placeholder="Optional notes regarding dialect pronunciation or cultural context..."></textarea>
                        </div>

                        <!-- Consent Checkbox -->
                        <div class="col-12">
                            <div class="form-check p-2.5 rounded-xl" style="background-color: var(--surface-muted, #f1e6d2); border: 1px solid var(--border, #e4d7c2);">
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="consent_confirmed" value="1" id="consent_confirmed" checked required>
                                <label class="form-check-label text-xs font-medium vt-text-primary" for="consent_confirmed">
                                    I confirm that the native speaker has authorized this voice recording for use in BarangGabay voice dataset.
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer vt-modal-footer">
                    <input type="hidden" name="status" id="sample_save_status" value="APPROVED">
                    <button type="button" class="btn-vt-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" onclick="document.getElementById('sample_save_status').value='DRAFT'" class="btn-vt-secondary">Save Draft</button>
                    <button type="submit" onclick="document.getElementById('sample_save_status').value='APPROVED'" class="btn-vt-primary">
                        <i class="bi bi-check-circle-fill me-1"></i> Save & Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── 7. Modals: Configure Voice Profiles (MSM, FIL, EN) ──────────────── -->
<?php foreach (['msm' => 'Manobo', 'fil' => 'Filipino', 'en' => 'English'] as $langKey => $langLabel): 
    $p = $activeProfiles[$langKey] ?? [];
?>
<div class="modal fade vt-modal" id="profileModal_<?= $langKey ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content vt-modal-content">
            <div class="modal-header vt-modal-header">
                <h4 class="modal-title font-extrabold vt-text-primary d-flex align-items-center gap-2 h5 mb-0">
                    <i class="bi bi-sliders" style="color: #c8992e;"></i><?= $langLabel ?> Active Voice Profile
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="<?= e(route('admin/voice-training/profile')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="language" value="<?= $langKey ?>">
                <input type="hidden" name="is_active" value="1">
                <div class="modal-body vt-modal-body">
                    <div class="mb-3">
                        <label class="form-label text-xs font-bold vt-text-primary">Profile Name</label>
                        <input type="text" name="profile_name" class="vt-input" value="<?= e($p['profile_name'] ?? "{$langLabel} Default Voice") ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-xs font-bold vt-text-primary">Voice Provider Strategy</label>
                        <select name="provider" class="form-select form-select-sm vt-select">
                            <option value="dataset_hybrid" <?= ($p['provider'] ?? '') === 'dataset_hybrid' ? 'selected' : '' ?>>Dataset Recordings + SpeechSynthesis Hybrid</option>
                            <option value="system" <?= ($p['provider'] ?? '') === 'system' ? 'selected' : '' ?>>Browser Native SpeechSynthesis</option>
                            <option value="google_tts" <?= ($p['provider'] ?? '') === 'google_tts' ? 'selected' : '' ?>>Google Text-to-Speech API</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-xs font-bold vt-text-primary">Provider Voice Identifier</label>
                        <input type="text" name="provider_voice_id" class="vt-input" value="<?= e($p['provider_voice_id'] ?? '') ?>" placeholder="e.g. mn-PH-Community / fil-PH-Standard">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-xs font-bold vt-text-primary">Description</label>
                        <textarea name="description" class="vt-input" rows="2"><?= e($p['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-check p-2.5 rounded-xl" style="background-color: var(--surface-muted, #f1e6d2); border: 1px solid var(--border, #e4d7c2);">
                        <input class="form-check-input ms-0 me-2" type="checkbox" name="consent_confirmed" value="1" id="consent_<?= $langKey ?>" checked>
                        <label class="form-check-label text-xs font-medium vt-text-primary" for="consent_<?= $langKey ?>">
                            Authorized for BARANGGABAY Resident Reader use
                        </label>
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

<!-- ── 8. JavaScript Functions ────────────────────────────────────────── -->
<script>
let mediaRecorder = null;
let audioChunks = [];
let recTimerInterval = null;
let recSeconds = 0;

function startMicRecording() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert('Ang iyong browser ay hindi sumusuporta sa microphone recording.');
        return;
    }

    navigator.mediaDevices.getUserMedia({ audio: true })
        .then(stream => {
            mediaRecorder = new MediaRecorder(stream);
            audioChunks = [];

            mediaRecorder.ondataavailable = event => {
                if (event.data.size > 0) {
                    audioChunks.push(event.data);
                }
            };

            mediaRecorder.onstop = () => {
                const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                const audioUrl = URL.createObjectURL(audioBlob);
                
                const player = document.getElementById('recAudioPlayer');
                player.src = audioUrl;
                document.getElementById('micPreview').classList.remove('d-none');

                // Convert blob to base64
                const reader = new FileReader();
                reader.readAsDataURL(audioBlob);
                reader.onloadend = () => {
                    document.getElementById('audio_base64').value = reader.result;
                };

                // Stop tracks
                stream.getTracks().forEach(track => track.stop());
            };

            mediaRecorder.start();
            document.getElementById('btnStartRec').classList.add('d-none');
            document.getElementById('btnStopRec').classList.remove('d-none');
            document.getElementById('recTimer').classList.remove('d-none');

            recSeconds = 0;
            recTimerInterval = setInterval(() => {
                recSeconds++;
                const mins = String(Math.floor(recSeconds / 60)).padStart(2, '0');
                const secs = String(recSeconds % 60).padStart(2, '0');
                document.getElementById('recTimer').textContent = `${mins}:${secs}`;
            }, 1000);
        })
        .catch(err => {
            console.error('Microphone access denied:', err);
            alert('Hindi ma-access ang microphone. Pakipahintulutan ang browser permission.');
        });
}

function stopMicRecording() {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
        clearInterval(recTimerInterval);
        document.getElementById('btnStopRec').classList.add('d-none');
        document.getElementById('btnStartRec').classList.remove('d-none');
    }
}

function resetMicRecording() {
    audioChunks = [];
    document.getElementById('audio_base64').value = '';
    document.getElementById('micPreview').classList.add('d-none');
    document.getElementById('recTimer').classList.add('d-none');
    document.getElementById('btnStartRec').classList.remove('d-none');
    document.getElementById('btnStopRec').classList.add('d-none');
    clearInterval(recTimerInterval);
}

function quickRecordForWord(dictId, word) {
    document.getElementById('sample_language').value = 'msm';
    document.getElementById('sample_text').value = word;
    document.getElementById('sample_dictionary_entry_id').value = dictId;
    const modal = new bootstrap.Modal(document.getElementById('addSampleModal'));
    modal.show();
}

function quickTestLanguage(lang) {
    const radioEl = document.getElementById('lang_' + lang);
    if (radioEl) {
        radioEl.checked = true;
    }
    document.getElementById('test_language').value = lang;

    const textEl = document.getElementById('test_text');
    if (textEl && !textEl.value) {
        textEl.value = (lang === 'msm') ? 'Maayong adlaw abaga' : (lang === 'fil' ? 'Magandang araw sa ating barangay' : 'Welcome to BarangGabay voice reader');
    }

    const testCard = document.getElementById('testVoiceCard');
    if (testCard) {
        testCard.scrollIntoView({ behavior: 'smooth' });
    }
}

function runVoiceTest(e) {
    e.preventDefault();
    const lang = document.getElementById('test_language').value;
    const textEl = document.getElementById('test_text');
    const text = textEl.value.trim();
    const validationEl = document.getElementById('testTextValidation');
    const spinner = document.getElementById('testVoiceSpinner');
    const resultBox = document.getElementById('testResultBox');
    const sourceBadge = document.getElementById('testSourceBadge');
    const profileLabel = document.getElementById('testProfileLabel');
    const messageEl = document.getElementById('testMessage');
    const audioPlayer = document.getElementById('testAudioPlayer');

    if (!text) {
        if (validationEl) validationEl.classList.remove('d-none');
        return;
    }
    if (validationEl) validationEl.classList.add('d-none');

    spinner.classList.remove('d-none');
    resultBox.classList.add('d-none');

    const formData = new FormData();
    formData.append('language', lang);
    formData.append('text', text);

    fetch('<?= e(route('admin/voice-training/test')) ?>', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '<?= csrf_token() ?>',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        spinner.classList.add('d-none');
        resultBox.classList.remove('d-none');

        if (data.success) {
            if (data.source === 'dataset_recording' && data.audio_url) {
                sourceBadge.className = 'vt-badge vt-badge-green';
                sourceBadge.textContent = 'Approved Dataset Audio';
                profileLabel.textContent = `Speaker: ${data.speaker}`;
                messageEl.textContent = `Matching recording found for "${data.matched_text}". Playing audio sample.`;
                audioPlayer.src = data.audio_url;
                audioPlayer.classList.remove('d-none');
                audioPlayer.play();
            } else {
                sourceBadge.className = 'vt-badge vt-badge-blue';
                sourceBadge.textContent = 'Voice Profile Synthesis';
                profileLabel.textContent = data.profile ? data.profile.profile_name : 'Active Profile';
                messageEl.textContent = data.message || 'Playing synthesized audio preview.';
                audioPlayer.classList.add('d-none');

                // Speak using browser SpeechSynthesis
                if ('speechSynthesis' in window) {
                    const u = new SpeechSynthesisUtterance(text);
                    u.lang = (lang === 'msm') ? 'ceb-PH' : (lang === 'fil' ? 'fil-PH' : 'en-US');
                    window.speechSynthesis.speak(u);
                }
            }
        } else {
            sourceBadge.className = 'vt-badge vt-badge-red';
            sourceBadge.textContent = 'Notice';
            messageEl.textContent = data.error || 'Failed to test voice.';
            audioPlayer.classList.add('d-none');
        }
    })
    .catch(err => {
        console.error('Test error:', err);
        spinner.classList.add('d-none');
        resultBox.classList.remove('d-none');
        sourceBadge.className = 'vt-badge vt-badge-red';
        sourceBadge.textContent = 'Error';
        messageEl.textContent = 'Network error testing voice preview.';
    });
}
</script>

<?php
$content   = ob_get_clean();
$pageTitle = 'Voice Training & AI Dataset Hub';
require __DIR__ . '/../../layouts/admin.php';
