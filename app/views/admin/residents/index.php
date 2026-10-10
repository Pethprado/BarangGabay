<?php
/**
 * Admin resident management table.
 * Variables: $residents (array), $counts (array), $status (string), $search (string)
 */
$residents = $residents ?? [];
$counts    = $counts    ?? ['all' => 0, 'pending' => 0, 'verified' => 0, 'suspended' => 0];
$status    = $status    ?? '';
$search    = $search    ?? '';

$statusMeta = [
    'pending'   => ['cls' => 'background:#fff3cd;color:#856404;border:1px solid #ffc107;',  'label' => t('residents.status_pending')],
    'verified'  => ['cls' => 'background:#d4edda;color:#155724;border:1px solid #c3e6cb;',  'label' => t('residents.status_verified')],
    'suspended' => ['cls' => 'background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;',  'label' => t('residents.status_suspended')],
];

/** Build a filter URL preserving the other active param. */
$tabUrl = static function (string $tabStatus, string $search): string {
    $p = [];
    if ($tabStatus !== '') $p['status'] = $tabStatus;
    if ($search !== '')    $p['search'] = $search;
    return route('admin/residents') . ($p ? '?' . http_build_query($p) : '');
};

ob_start();
?>

<style>
/* ── Residents page styles ─────────────────────────────────── */
.tab-pill { display:inline-flex; align-items:center; gap:6px; padding:7px 15px; border-radius:20px; text-decoration:none; font-size:.82rem; font-weight:600; border:1px solid var(--tb-border); background:var(--surface-card); color:var(--text-secondary); transition:all .15s; }
.tab-pill:hover { background:var(--brand-primary-light); border-color:var(--brand-primary); color:var(--brand-primary); text-decoration:none; }
.tab-pill.active { background:var(--action-solid); border-color:var(--action-solid); color:#fff; }
.tab-pill .tab-count { font-size:.72rem; opacity:.85; }
.tab-pill.active .tab-count { opacity:.9; }
.tab-pill.tab-pending.active, .tab-pill.tab-pending:hover { background:#d97706; border-color:#d97706; color:#fff; }
.avatar-circle { width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.78rem; font-weight:700; flex-shrink:0; }
.row-pending td { background:rgba(255,193,7,.055) !important; }
.row-pending:hover td { background:rgba(255,193,7,.10) !important; }
/* Overlay modals */
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.55); backdrop-filter:blur(3px); z-index:9050; display:flex; align-items:center; justify-content:center; padding:1rem; }
.modal-box { background:var(--surface-card); border-radius:14px; box-shadow:0 24px 64px rgba(0,0,0,.22); width:100%; overflow:hidden; color:var(--text-primary); }
/* ID photo zoom */
.id-photo-wrap { cursor:zoom-in; overflow:hidden; border-radius:8px; background:var(--surface-muted); }
.id-photo-wrap.zoomed { cursor:zoom-out; }
.id-photo-wrap img { display:block; width:100%; transition:transform .3s ease; }
.id-photo-wrap.zoomed img { transform:scale(2.2); transform-origin:center center; }
</style>

<div x-data="residentManager()" x-init="init()">

<!-- ── Page header ──────────────────────────────────────────────── -->
<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <p class="mb-0" style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);"><?= e(t('residents.eyebrow')) ?></p>
        <h1 class="mb-0 mt-1" style="font-size:1.55rem;font-weight:800;color:var(--text-primary);line-height:1.1;"><?= e(t('residents.title')) ?></h1>
        <p class="text-muted mt-1 mb-0" style="font-size:.82rem;">
            <?= e(t('residents.summary', ['all' => number_format($counts['all']), 'pending' => number_format($counts['pending'])])) ?>
        </p>
    </div>
    <!-- Create Staff button — visible to admin/superadmin only -->
    <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'], true)): ?>
    <a href="<?= e(route('admin/staff/create')) ?>"
       style="display:inline-flex;align-items:center;gap:8px;padding:9px 18px;background:linear-gradient(135deg,#1652f0,#1041c4);color:#fff;border-radius:8px;font-size:.845rem;font-weight:700;text-decoration:none;box-shadow:0 2px 8px rgba(22,82,240,.25);transition:all .2s;white-space:nowrap;"
       onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 6px 20px rgba(22,82,240,.35)';"
       onmouseout="this.style.transform='';this.style.boxShadow='0 2px 8px rgba(22,82,240,.25)';">
        <i class="bi bi-person-plus-fill"></i>
        <?= e(t('residents.create_staff_btn')) ?>
    </a>
    <?php endif; ?>
</div>

<!-- ── Tab filter ────────────────────────────────────────────────── -->
<div class="d-flex flex-wrap gap-2 mb-4">
    <?php
    $tabs = [
        ''          => [t('residents.tab_all'),       $counts['all'],       'tab-all'],
        'pending'   => [t('residents.tab_pending'),   $counts['pending'],   'tab-pending'],
        'verified'  => [t('residents.tab_verified'),  $counts['verified'],  'tab-verified'],
        'suspended' => [t('residents.tab_suspended'), $counts['suspended'], 'tab-suspended'],
    ];
    foreach ($tabs as $tabStatus => [$tabLabel, $tabCount, $tabCls]):
        $isActive = ($status === $tabStatus);
        $url = $tabUrl($tabStatus, $search);
    ?>
    <a href="<?= e($url) ?>"
       class="tab-pill <?= $tabCls ?><?= $isActive ? ' active' : '' ?>">
        <?= e($tabLabel) ?>
        <span class="tab-count">(<?= number_format($tabCount) ?>)</span>
    </a>
    <?php endforeach; ?>
</div>

<!-- ── Search bar + bulk action ─────────────────────────────────── -->
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">

    <!-- Search form -->
    <form method="get" action="<?= e(route('admin/residents')) ?>" class="d-flex gap-2 flex-grow-1 flex-wrap" style="min-width:200px;">
        <?php if ($status): ?>
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <?php endif; ?>
        <div class="input-group" style="max-width:360px;">
            <span class="input-group-text" style="background:#f8fbf9;border-color:#dee2e6;">
                <i class="bi bi-search" style="font-size:.8rem;color:var(--text-muted);"></i>
            </span>
            <input type="text" name="search"
                   value="<?= e($search) ?>"
                   placeholder="<?= e(t('residents.search_placeholder')) ?>"
                   class="form-control" style="border-left:none;font-size:.875rem;">
            <button type="submit" class="btn-barangay" style="border-radius:0 6px 6px 0;padding:0 14px;">
                <?= e(t('residents.search_btn')) ?>
            </button>
        </div>
        <?php if ($search || $status): ?>
        <a href="<?= e(route('admin/residents')) ?>"
           class="d-inline-flex align-items-center gap-1 text-muted text-decoration-none"
           style="font-size:.82rem;padding:6px 10px;">
            <i class="bi bi-x-circle"></i> <?= e(t('residents.clear')) ?>
        </a>
        <?php endif; ?>
    </form>

    <!-- Bulk action bar (shown only when rows are selected) -->
    <div x-show="selected.length > 0"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-x-2"
         x-transition:enter-end="opacity-100 translate-x-0"
         class="d-flex flex-wrap align-items-center gap-2 ms-auto">
        <span class="text-muted" style="font-size:.8rem;white-space:nowrap;">
            <span x-text="selected.length"></span> <?= e(t('residents.selected_suffix')) ?>
        </span>
        <select x-model="bulkAction"
                class="form-select form-select-sm" style="font-size:.8rem;width:auto;min-width:180px;">
            <option value=""><?= e(t('residents.choose_action')) ?></option>
            <option value="verify"><?= e(t('residents.bulk_verify')) ?></option>
            <option value="suspend"><?= e(t('residents.bulk_suspend')) ?></option>
        </select>
        <button type="button"
                @click="submitBulk()"
                :disabled="!bulkAction"
                class="btn-barangay"
                style="padding:7px 14px;font-size:.8rem;white-space:nowrap;">
            <?= e(t('residents.apply')) ?>
        </button>
        <button type="button"
                @click="clearSelection()"
                class="btn btn-sm btn-outline-secondary" style="font-size:.8rem;">
            <?= e(t('residents.clear')) ?>
        </button>
    </div>

</div>

<!-- ── Results count ─────────────────────────────────────────────── -->
<p class="text-muted mb-2" style="font-size:.8rem;">
    <strong class="text-dark"><?= count($residents) ?></strong>
    <?= e(t('residents.results_found_suffix')) ?>
    <?= ($search ? e(t('residents.results_for', ['query' => $search])) : '') ?>
</p>

<!-- ── Data table ────────────────────────────────────────────────── -->
<?php if (empty($residents)): ?>
<div class="admin-card text-center py-5">
    <i class="bi bi-people" style="font-size:2.5rem;color:#e4ece6;"></i>
    <p class="mt-3 mb-0 text-muted" style="font-size:.875rem;"><?= e(t('residents.no_residents')) ?></p>
    <?php if ($search || $status): ?>
    <a href="<?= e(route('admin/residents')) ?>" class="text-success text-decoration-none fw-semibold mt-2 d-inline-block" style="font-size:.82rem;">
        <i class="bi bi-arrow-left-short"></i> <?= e(t('residents.view_all')) ?>
    </a>
    <?php endif; ?>
</div>

<?php else: ?>
<div class="admin-card p-0">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width:44px;padding-left:16px;">
                        <input type="checkbox"
                               class="form-check-input"
                               @change="toggleAll($event.target.checked)"
                               :checked="selected.length > 0 && selected.length === rowCount">
                    </th>
                    <th><?= e(t('residents.col_resident')) ?></th>
                    <th><?= e(t('residents.col_phone')) ?></th>
                    <th><?= e(t('residents.col_zone')) ?></th>
                    <th><?= e(t('residents.col_registered')) ?></th>
                    <th><?= e(t('residents.col_status')) ?></th>
                    <th style="width:190px;text-align:right;padding-right:16px;"><?= e(t('residents.col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($residents as $res):
                    $resStatus = $res['status'] ?? 'pending';
                    $sMeta     = $statusMeta[$resStatus] ?? $statusMeta['pending'];
                    $initial   = mb_strtoupper(mb_substr($res['full_name'], 0, 1, 'UTF-8'), 'UTF-8');
                    $avatarBg  = match($resStatus) {
                        'verified'  => 'background:linear-gradient(135deg,#1a6b3a,#4a9e6b);',
                        'suspended' => 'background:linear-gradient(135deg,#721c24,#c0392b);',
                        default     => 'background:linear-gradient(135deg,#856404,#e8a020);',
                    };
                ?>
                <tr <?= $resStatus === 'pending' ? 'class="row-pending"' : '' ?>>

                    <!-- Checkbox -->
                    <td style="padding-left:16px;width:44px;">
                        <input type="checkbox"
                               class="form-check-input row-check"
                               value="<?= (int) $res['id'] ?>"
                               @change="toggle(<?= (int) $res['id'] ?>, $event.target.checked)"
                               :checked="selected.includes(<?= (int) $res['id'] ?>)">
                    </td>

                    <!-- Avatar + name + email -->
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php if (!empty($res['avatar_url'])): ?>
                            <img src="<?= e(asset($res['avatar_url'])) ?>"
                                 alt="" style="width:34px;height:34px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                            <?php else: ?>
                            <div class="avatar-circle" style="<?= $avatarBg ?>color:#fff;">
                                <?= e($initial) ?>
                            </div>
                            <?php endif; ?>
                            <div style="min-width:0;">
                                <a href="<?= e(route('admin/residents/' . (int) $res['id'])) ?>"
                                   class="d-block fw-semibold text-decoration-none text-dark"
                                   style="font-size:.875rem;line-height:1.3;"><?= e($res['full_name']) ?></a>
                                <span class="text-muted" style="font-size:.78rem;"><?= e($res['email']) ?></span>
                            </div>
                        </div>
                    </td>

                    <!-- Phone -->
                    <td class="text-muted" style="font-size:.845rem;">
                        <?= $res['phone'] ? e($res['phone']) : '<span style="color:#cbd5e1;">—</span>' ?>
                    </td>

                    <!-- Zone / Address -->
                    <td style="font-size:.845rem;max-width:160px;">
                        <?php if (!empty($res['zone'])): ?>
                        <span class="fw-semibold text-dark"><?= e($res['zone']) ?></span><br>
                        <?php endif; ?>
                        <?php if (!empty($res['address'])): ?>
                        <span class="text-muted" style="font-size:.78rem;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:140px;" title="<?= e($res['address']) ?>">
                            <?= e($res['address']) ?>
                        </span>
                        <?php else: ?>
                        <span style="color:#cbd5e1;">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- Registered date -->
                    <td class="text-muted" style="font-size:.845rem;white-space:nowrap;">
                        <?= e(date('M j, Y', strtotime($res['created_at']))) ?>
                        <?php if ($resStatus === 'pending'): ?>
                        <br><span style="font-size:.72rem;color:#d97706;font-weight:600;"><?= e(t('residents.waiting')) ?></span>
                        <?php endif; ?>
                    </td>

                    <!-- Status + AI badge -->
                    <td>
                        <span class="badge" style="<?= $sMeta['cls'] ?>font-size:.72rem;border-radius:20px;padding:4px 10px;">
                            <?= $sMeta['label'] ?>
                        </span>
                        <?php
                        // AI verification badge
                        $aiStatus = $res['id_ai_status'] ?? null;
                        $aiStyles = [
                            'ai_passed'     => ['bg'=>'#d4edda','color'=>'#155724','icon'=>'bi-robot',       'label'=>t('residents.ai_valid')],
                            'ai_flagged'    => ['bg'=>'#f8d7da','color'=>'#721c24','icon'=>'bi-robot',       'label'=>t('residents.ai_flagged')],
                            'manual_review' => ['bg'=>'#fff3cd','color'=>'#856404','icon'=>'bi-eye',         'label'=>t('residents.manual_review')],
                            'pdf_manual'    => ['bg'=>'#d1ecf1','color'=>'#0c5460','icon'=>'bi-file-earmark','label'=>t('residents.pdf_manual')],
                            'skipped'       => ['bg'=>'#f1f5f9','color'=>'#475569','icon'=>'bi-skip-forward','label'=>t('residents.ai_skipped')],
                        ];
                        if ($aiStatus && isset($aiStyles[$aiStatus])):
                            $s = $aiStyles[$aiStatus];
                        ?>
                        <br><span style="display:inline-flex;align-items:center;gap:3px;margin-top:3px;background:<?= $s['bg'] ?>;color:<?= $s['color'] ?>;font-size:.64rem;font-weight:700;padding:2px 7px;border-radius:12px;">
                            <i class="bi <?= $s['icon'] ?>"></i> <?= $s['label'] ?>
                        </span>
                        <?php elseif (!empty($res['id_photo_url'])): ?>
                        <br><span style="font-size:.68rem;color:var(--text-muted);margin-top:2px;display:inline-block;">
                            <i class="bi bi-card-image"></i> <?= e(t('residents.has_id')) ?>
                        </span>
                        <?php endif; ?>
                    </td>

                    <!-- Actions -->
                    <td style="text-align:right;padding-right:16px;white-space:nowrap;">
                        <div class="d-flex align-items-center justify-content-end gap-1 flex-wrap">

                            <!-- View ID photo -->
                            <?php if (!empty($res['id_photo_url'])): ?>
                            <button type="button"
                                    @click="openIdModal('<?= e(asset(ltrim($res['id_photo_url'], '/'))) ?>', '<?= e(htmlspecialchars(addslashes($res['full_name']), ENT_QUOTES, 'UTF-8')) ?>')"
                                    title="<?= e(t('residents.view_id')) ?>"
                                    style="padding:4px 9px;font-size:.75rem;background:#f8fbf9;border:1px solid #e4ece6;border-radius:6px;cursor:pointer;color:var(--text-secondary);display:inline-flex;align-items:center;gap:4px;transition:all .15s;"
                                    onmouseover="this.style.background='#d1ecf1';this.style.borderColor='#bee5eb';this.style.color='#0c5460';"
                                    onmouseout="this.style.background='#f8fbf9';this.style.borderColor='#e4ece6';this.style.color='#4a5568';">
                                <i class="bi bi-card-image"></i> ID
                            </button>
                            <?php endif; ?>

                            <!-- Detail link -->
                            <a href="<?= e(route('admin/residents/' . (int) $res['id'])) ?>"
                               title="<?= e(t('residents.view_details')) ?>"
                               style="padding:4px 9px;font-size:.75rem;background:#f8fbf9;border:1px solid #e4ece6;border-radius:6px;color:var(--text-secondary);text-decoration:none;display:inline-flex;align-items:center;gap:4px;transition:all .15s;"
                               onmouseover="this.style.background='#f0faf4';this.style.borderColor='#a8d5b5';this.style.color='#155724';"
                               onmouseout="this.style.background='#f8fbf9';this.style.borderColor='#e4ece6';this.style.color='#4a5568';">
                                <i class="bi bi-eye"></i>
                            </a>

                            <!-- Verify (pending or suspended) -->
                            <?php if (in_array($resStatus, ['pending', 'suspended'], true)): ?>
                            <form method="post"
                                  action="<?= e(route('admin/residents/' . (int) $res['id'] . '/verify')) ?>"
                                  style="display:inline;"
                                  onsubmit="return confirm('<?= e(t('residents.confirm_verify', ['name' => addslashes($res['full_name'])])) ?>')">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button type="submit"
                                        title="<?= e(t('residents.verify')) ?>"
                                        style="padding:4px 9px;font-size:.75rem;background:#d4edda;border:1px solid #c3e6cb;border-radius:6px;cursor:pointer;color:#155724;display:inline-flex;align-items:center;gap:4px;transition:all .15s;font-weight:700;"
                                        onmouseover="this.style.background='#1a6b3a';this.style.borderColor='#1a6b3a';this.style.color='#fff';"
                                        onmouseout="this.style.background='#d4edda';this.style.borderColor='#c3e6cb';this.style.color='#155724';">
                                    <i class="bi bi-check-circle-fill"></i> <?= e(t('residents.verify')) ?>
                                </button>
                            </form>
                            <?php endif; ?>

                            <!-- Suspend (pending or verified) -->
                            <?php if (in_array($resStatus, ['pending', 'verified'], true)): ?>
                            <button type="button"
                                    @click="openSuspendModal(<?= (int) $res['id'] ?>, '<?= e(htmlspecialchars(addslashes($res['full_name']), ENT_QUOTES, 'UTF-8')) ?>')"
                                    title="<?= e(t('residents.suspend')) ?>"
                                    style="padding:4px 9px;font-size:.75rem;background:#f8d7da;border:1px solid #f5c6cb;border-radius:6px;cursor:pointer;color:#721c24;display:inline-flex;align-items:center;gap:4px;transition:all .15s;font-weight:700;"
                                    onmouseover="this.style.background='#dc3545';this.style.borderColor='#dc3545';this.style.color='#fff';"
                                    onmouseout="this.style.background='#f8d7da';this.style.borderColor='#f5c6cb';this.style.color='#721c24';">
                                <i class="bi bi-x-circle-fill"></i> <?= e(t('residents.suspend')) ?>
                            </button>
                            <?php endif; ?>

                        </div>
                    </td>

                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════
     ID PHOTO MODAL
     ══════════════════════════════════════════════════════════════ -->
<div class="modal-overlay"
     x-show="idModal"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @click.self="idModal = false"
     @keydown.escape.window="idModal = false"
     style="display:none;">
    <div class="modal-box" style="max-width:520px;">
        <!-- Header -->
        <div style="background:#1652f0;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;">
            <div>
                <p style="color:#fff;font-weight:700;margin:0;font-size:.9rem;"><?= e(t('residents.id_modal_title')) ?></p>
                <p style="color:rgba(255,255,255,.7);margin:0;font-size:.78rem;" x-text="idName"></p>
            </div>
            <button type="button" @click="idModal = false"
                    style="background:rgba(255,255,255,.15);border:none;border-radius:6px;color:#fff;padding:4px 9px;cursor:pointer;font-size:.875rem;line-height:1;">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <!-- Photo -->
        <div class="id-photo-wrap"
             :class="{ zoomed: idZoomed }"
             @click="idZoomed = !idZoomed"
             style="margin:0;">
            <img :src="idPhoto" :alt="idName" style="">
        </div>
        <!-- Footer hint -->
        <div style="padding:10px 16px;background:#f8fbf9;border-top:1px solid #e4ece6;text-align:center;">
            <p style="margin:0;font-size:.75rem;color:var(--text-muted);">
                <i class="bi bi-zoom-in me-1"></i><?= e(t('residents.zoom_hint')) ?>
                &nbsp;&middot;&nbsp;
                <a :href="idPhoto" target="_blank" rel="noopener"
                   style="color:var(--brand-primary);text-decoration:none;font-weight:600;">
                    <i class="bi bi-box-arrow-up-right"></i> <?= e(t('residents.open_new_tab')) ?>
                </a>
            </p>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     SUSPEND MODAL
     ══════════════════════════════════════════════════════════════ -->
<div class="modal-overlay"
     x-show="suspendModal"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 scale-95"
     x-transition:enter-end="opacity-100 scale-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100 scale-100"
     x-transition:leave-end="opacity-0 scale-95"
     @click.self="suspendModal = false"
     @keydown.escape.window="suspendModal = false"
     style="display:none;">
    <div class="modal-box" style="max-width:440px;">
        <!-- Header -->
        <div style="background:#dc3545;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;">
            <div>
                <p style="color:#fff;font-weight:700;margin:0;font-size:.9rem;"><?= e(t('residents.suspend_modal_title')) ?></p>
                <p style="color:rgba(255,255,255,.8);margin:0;font-size:.78rem;" x-text="suspendName"></p>
            </div>
            <button type="button" @click="suspendModal = false"
                    style="background:rgba(255,255,255,.15);border:none;border-radius:6px;color:#fff;padding:4px 9px;cursor:pointer;font-size:.875rem;line-height:1;">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <!-- Form -->
        <form method="post"
              :action="'<?= e(rtrim(route('admin/residents/'), '/')) ?>/' + suspendId + '/suspend'">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div style="padding:20px;">
                <label style="font-size:.845rem;font-weight:600;color:var(--text-primary);display:block;margin-bottom:6px;">
                    <?= e(t('residents.suspend_reason_label')) ?> <span style="color:var(--text-muted);font-weight:400;"><?= e(t('common.optional')) ?></span>
                </label>
                <textarea name="reason"
                          x-model="suspendReason"
                          rows="3"
                          placeholder="<?= e(t('residents.suspend_reason_ph')) ?>"
                          style="width:100%;border:1px solid #dee2e6;border-radius:8px;padding:9px 12px;font-size:.845rem;resize:vertical;outline:none;font-family:inherit;"
                          onfocus="this.style.borderColor='#dc3545';"
                          onblur="this.style.borderColor='#dee2e6';"></textarea>
                <p style="font-size:.75rem;color:var(--text-muted);margin:6px 0 0;">
                    <?= e(t('residents.suspend_notice')) ?>
                </p>
            </div>
            <div style="padding:14px 20px;background:#f8fbf9;border-top:1px solid #e4ece6;display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" @click="suspendModal = false"
                        style="padding:8px 18px;font-size:.845rem;background:#fff;border:1px solid #dee2e6;border-radius:7px;cursor:pointer;color:var(--text-secondary);font-weight:600;">
                    <?= e(t('common.cancel')) ?>
                </button>
                <button type="submit"
                        style="padding:8px 18px;font-size:.845rem;background:#dc3545;border:1px solid #dc3545;border-radius:7px;cursor:pointer;color:#fff;font-weight:700;">
                    <i class="bi bi-x-circle-fill me-1"></i><?= e(t('residents.confirm_suspend')) ?>
                </button>
            </div>
        </form>
    </div>
</div>

</div><!-- /x-data residentManager -->

<script>
function residentManager() {
    return {
        /* Bulk selection */
        selected:    [],
        bulkAction:  '',
        rowCount:    <?= count($residents) ?>,

        /* ID modal */
        idModal:     false,
        idPhoto:     '',
        idName:      '',
        idZoomed:    false,

        /* Suspend modal */
        suspendModal:  false,
        suspendId:     null,
        suspendName:   '',
        suspendReason: '',

        init() {
            /* Sync header checkbox indeterminate state */
        },

        /* ── Bulk ── */
        toggleAll(checked) {
            if (checked) {
                this.selected = Array.from(document.querySelectorAll('.row-check')).map(el => parseInt(el.value));
            } else {
                this.selected = [];
                document.querySelectorAll('.row-check').forEach(el => el.checked = false);
            }
        },
        toggle(id, checked) {
            if (checked) {
                if (!this.selected.includes(id)) this.selected.push(id);
            } else {
                this.selected = this.selected.filter(s => s !== id);
            }
        },
        clearSelection() {
            this.selected = [];
            this.bulkAction = '';
            document.querySelectorAll('.row-check').forEach(el => el.checked = false);
            const headerCheck = document.querySelector('thead input[type=checkbox]');
            if (headerCheck) headerCheck.checked = false;
        },
        submitBulk() {
            if (!this.bulkAction) return;
            if (!this.selected.length) return;
            const template = this.bulkAction === 'verify'
                ? <?= json_encode(t('residents.confirm_bulk_verify')) ?>
                : <?= json_encode(t('residents.confirm_bulk_suspend')) ?>;
            if (!confirm(template.replace(':count', this.selected.length))) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.BarangGabay.baseUrl + 'admin/residents/bulk';

            const add = (name, val) => {
                const inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = name; inp.value = val;
                form.appendChild(inp);
            };

            add('csrf_token',  window.BarangGabay.csrfToken);
            add('bulk_action', this.bulkAction);
            this.selected.forEach(id => add('selected_ids[]', id));

            document.body.appendChild(form);
            form.submit();
        },

        /* ── ID modal ── */
        openIdModal(photo, name) {
            this.idPhoto   = photo;
            this.idName    = name;
            this.idZoomed  = false;
            this.idModal   = true;
        },

        /* ── Suspend modal ── */
        openSuspendModal(id, name) {
            this.suspendId     = id;
            this.suspendName   = name;
            this.suspendReason = '';
            this.suspendModal  = true;
        },
    };
}
</script>

<?php
$content   = ob_get_clean();
$pageTitle = $pageTitle ?? 'Resident Management';
require __DIR__ . '/../../layouts/admin.php';
