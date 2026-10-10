"""Regenerates the LEGIBILITY LAYER at the end of theme.css.

One rule list -> two identical dark blocks (explicit toggle + OS preference),
so the two can never drift apart.
"""
p = r'C:\xampp\htdocs\BarangGabay\public\assets\css\theme.css'
s = open(p, encoding='utf-8').read()
marker = '\n\n/* ═══════════════════════════════════════════════════════════════════════\n   LEGIBILITY LAYER'
if marker in s:
    s = s[:s.index(marker)]

def bg(*hexes):
    out = []
    for h in hexes:
        out += [f'[style*="background:{h}"]', f'[style*="background: {h}"]', f'[style*="background-color:{h}"]']
    return out

white = ['.bg-white', '.bg-light', '.card', '.list-group-item',
         '[style*="background:#fff;"]', '[style*="background: #fff;"]', '[style$="background:#fff"]',
         '[style*="background:#ffffff"]', '[style*="background: #ffffff"]', '[style*="background:white"]',
         '[style*="background-color:#fff;"]', '[style*="background:#fcfdfc"]', '[style*="background:#fffdf8"]']
grey  = bg('#f8f9fa', '#f9fafb', '#f1f5f9', '#e9ecef', '#f8fafc', '#e2e8f0', '#f8fbf9', '#f3f4f6', '#f5f5f5', '#f0f0f0')
green = bg('#f0faf4', '#dcfce7', '#d1fae5', '#e3ede4', '#ecfdf5', '#f0fdf4', '#e6f4ea', '#e8f6ee', '#e2f2e8') + ['.bg-success-subtle']
amber = bg('#fef3c7', '#fffbeb', '#fff3cd', '#fefce8', '#fef9c3', '#fdf1d8', '#fff7ed', '#ffedd5', '#fcebd9', '#f8eed6')
blue  = bg('#e0f2fe', '#eef2ff', '#cfe2ff', '#dbeafe', '#eff6ff', '#e0e7ff', '#e3ecfb', '#e5f0fd') + ['.bg-primary-subtle', '.bg-info-subtle']
red   = bg('#fee2e2', '#fef2f2', '#fce7e3', '#ffe4e6', '#f8e1e1', '#fde8e8') + ['.bg-danger-subtle']
ink_dark  = [f'[style*="color:{h}"]' for h in ('#374151', '#1f2937', '#111827', '#2d3748', '#334155', '#475569', '#1e293b', '#212529')]
ink_muted = [f'[style*="color:{h}"]' for h in ('#6b7280', '#64748b', '#6c757d')]

rules = [
    ('.text-muted, .text-secondary, .form-text', 'color: var(--text-muted) !important;'),
    ('.text-dark:not(.badge), .text-body, .text-black', 'color: var(--text-primary) !important;'),
    ('.text-primary', 'color: var(--brand-primary) !important;'),
    ('.text-success', 'color: var(--status-success) !important;'),
    ('.text-danger', 'color: var(--status-danger) !important;'),
    ('.text-info', 'color: var(--status-info) !important;'),
    (', '.join(white), 'background-color: var(--surface-card) !important; color: var(--text-primary); border-color: var(--border) !important;'),
    (', '.join(grey),  'background: var(--surface-muted) !important; color: var(--text-secondary) !important; border-color: var(--border) !important;'),
    (', '.join(green), 'background: var(--status-success-bg) !important; color: var(--status-success) !important; border-color: rgba(22,163,109,.3) !important;'),
    (', '.join(amber), 'background: var(--status-warning-bg) !important; color: var(--status-warning) !important; border-color: rgba(224,178,92,.3) !important;'),
    (', '.join(blue),  'background: var(--status-info-bg) !important; color: var(--status-info) !important; border-color: rgba(140,192,245,.3) !important;'),
    (', '.join(red),   'background: var(--status-danger-bg) !important; color: var(--status-danger) !important; border-color: rgba(243,160,151,.3) !important;'),
    (', '.join(ink_dark),  'color: var(--text-primary) !important;'),
    (', '.join(ink_muted), 'color: var(--text-muted) !important;'),
    ('[style*="color:#b45309"], [style*="color:#c8992e"], [style*="color:#f59e0b"], [style*="color:#856404"], [style*="color:#92400e"], [style*="color:#a16207"], [style*="color:#78350f"], [style*="color:#713f12"]', 'color: var(--status-warning) !important;'),
    ('[style*="color:#15803d"], [style*="color:#166534"], [style*="color:#047857"], [style*="color:#0f6b3a"]', 'color: var(--status-success) !important;'),
    ('[style*="color:#b91c1c"], [style*="color:#991b1b"], [style*="color:#dc2626"], [style*="color:#7b1e22"]', 'color: var(--status-danger) !important;'),
    ('[style*="color:#4338ca"], [style*="color:#1d4ed8"], [style*="color:#2563eb"], [style*="color:#1e40af"]', 'color: var(--status-info) !important;'),
    # Yellow stays yellow in dark mode, so its ink stays dark.
    ('.badge.bg-warning, .badge.bg-warning.text-dark, .badge.text-bg-warning', 'color: #2a1d00 !important;'),
    # --brand-primary is a LIGHT green in dark mode (a text colour); fills under white text use the fixed action green.
    ('.aic-fab, .tab-pill.active, .btn-primary, .btn-success, .btn-barangay, .btn-admin-primary', 'background: var(--action-solid) !important; border-color: var(--action-solid) !important; color: #fff !important;'),
    ('.btn-outline-primary, .btn-outline-success, .btn-outline-secondary, .btn-light', 'color: var(--text-primary) !important; border-color: var(--border-strong) !important; background: transparent !important;'),
    ('.page-link', 'background: var(--surface-card) !important; border-color: var(--border) !important; color: var(--brand-primary) !important;'),
    ('.page-item.active .page-link', 'background: var(--action-solid) !important; color: #fff !important;'),
    ('.modal-content, .dropdown-menu', 'background: var(--surface-card) !important; color: var(--text-primary) !important; border-color: var(--border) !important;'),
    ('.dropdown-item', 'color: var(--text-primary) !important;'),
    ('.dropdown-item:hover, .dropdown-item:focus', 'background: var(--surface-muted) !important;'),
    ('.form-control, .form-select', 'background-color: var(--surface-input) !important; color: var(--text-primary) !important; border-color: var(--border-strong) !important;'),
    ('.form-control::placeholder', 'color: var(--text-muted) !important; opacity: 1;'),
    ('.form-label, .form-check-label', 'color: var(--text-primary);'),
    ('.input-group-text', 'background: var(--surface-muted) !important; color: var(--text-secondary) !important; border-color: var(--border-strong) !important;'),
    ('.table', '--bs-table-color: var(--text-primary); --bs-table-bg: transparent; --bs-table-striped-color: var(--text-primary); --bs-table-hover-color: var(--text-primary); color: var(--text-primary);'),
    ('input[type="date"], input[type="time"], input[type="datetime-local"], select', 'color-scheme: dark;'),
]

def block(prefix, indent=''):
    lines = []
    for sel, decl in rules:
        parts = [x.strip() for x in sel.split(', ')]
        joined = (',\n' + indent).join(f'{prefix} {x}' for x in parts)
        lines.append(f'{indent}{joined} {{ {decl} }}')
    return '\n'.join(lines)

layer = marker + ''' — every word readable in both themes
   ═══════════════════════════════════════════════════════════════════════
   Found by an automated contrast sweep of every page in light and dark mode
   (text under 3:1 against its real background), and grouped by CAUSE so a
   new view using the same classes or inline colours is covered too:

     • Bootstrap utilities (.text-muted, .text-dark, .bg-white …) are light-only.
     • Older views paint pastel chips inline (background:#fef3c7; color:#b45309).
       In dark mode each pastel FAMILY maps to its dark tint AND its matching
       text colour together — recolouring one without the other is what broke
       chips before.
     • --brand-primary turns light in dark mode; fills under white text use
       --action-solid, which never changes.

   Generated from one list (tools/gen-legibility-css.py) so the two dark
   blocks below stay identical.
   ═══════════════════════════════════════════════════════════════════════ */

/* Both themes: table headers sit on the green header fill; some views set an
   inline muted colour meant for a plain header. On green, white always wins. */
table.table thead th[style*="color:var(--text-muted)"],
table.table thead th[style*="color: var(--text-muted)"],
table.table thead th.text-muted { color: #ffffff !important; }

/* Both themes: a gold chip with white text measures 2.2:1 — dark ink instead. */
.badge[style*="#e8a020"], .badge[style*="232,160,32"], .badge.bg-warning, .badge.text-bg-warning { color: #2a1d00 !important; }

/* Both themes: a muted count inside an active (green) pill inherits the pill's white. */
.active .vt-text-muted, input:checked + .vt-lang-btn .vt-text-muted, [aria-pressed="true"] .vt-text-muted, .btn-success .vt-text-muted { color: inherit !important; opacity: .85; }

/* ── DARK MODE (explicit toggle) ─────────────────────────────────────── */
''' + block(':root[data-theme="dark"]') + '''

/* ── DARK MODE (OS preference, no explicit choice) — identical rules ── */
@media (prefers-color-scheme: dark) {
''' + block(':root:not([data-theme="light"])', '    ') + '''
}
'''
open(p, 'w', encoding='utf-8', newline='').write(s + layer)
print('ok', len(rules))
