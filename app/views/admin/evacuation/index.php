<?php
/**
 * Managing the evacuation centres.
 *
 * Variables: $centers (list), $puroks (list), $pageTitle, $pendingCount
 *
 * A centre with no purok serves the whole barangay and appears to everyone —
 * that is the fallback that stops a resident from ever seeing an empty page
 * during a warning, so it is offered explicitly rather than left as "blank".
 */
$centers = $centers ?? [];
$puroks  = $puroks  ?? [];

ob_start();
?>

<div class="admin-card mb-4">
    <div class="admin-card-body">
        <h1 style="font-size:1.15rem;font-weight:800;margin:0 0 .25rem;"><?= e(t('admin_evacuation.title')) ?></h1>
        <p class="text-muted mb-0" style="font-size:.82rem;line-height:1.6;">
            <?= e(t('admin_evacuation.subtitle')) ?>
        </p>
    </div>
</div>

<!-- ── Add a centre ──────────────────────────────────────────────── -->
<div class="admin-card mb-4">
    <div class="admin-card-header">
        <h2 style="font-size:.95rem;font-weight:700;margin:0;"><?= e(t('admin_evacuation.add_title')) ?></h2>
    </div>
    <div class="admin-card-body">
        <form method="post" action="<?= e(route('admin/evacuation')) ?>" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="is_active" value="1">

            <div class="col-md-5">
                <label class="form-label" style="font-size:.8rem;"><?= e(t('admin_evacuation.f_name')) ?> *</label>
                <input type="text" name="name" required maxlength="150" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:.8rem;"><?= e(t('admin_evacuation.f_purok')) ?></label>
                <select name="purok" class="form-select form-select-sm">
                    <option value=""><?= e(t('evacuation.whole_barangay')) ?></option>
                    <?php foreach ($puroks as $p): ?>
                    <option value="<?= e($p) ?>"><?= e($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" style="font-size:.8rem;"><?= e(t('admin_evacuation.f_address')) ?></label>
                <input type="text" name="address" maxlength="255" class="form-control form-control-sm">
            </div>

            <div class="col-md-2">
                <label class="form-label" style="font-size:.8rem;"><?= e(t('admin_evacuation.f_lat')) ?></label>
                <input type="text" name="latitude" class="form-control form-control-sm" placeholder="9.2647">
            </div>
            <div class="col-md-2">
                <label class="form-label" style="font-size:.8rem;"><?= e(t('admin_evacuation.f_lng')) ?></label>
                <input type="text" name="longitude" class="form-control form-control-sm" placeholder="125.9633">
            </div>
            <div class="col-md-2">
                <label class="form-label" style="font-size:.8rem;"><?= e(t('admin_evacuation.f_capacity')) ?></label>
                <input type="number" name="capacity" min="0" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:.8rem;"><?= e(t('admin_evacuation.f_contact')) ?></label>
                <input type="text" name="contact_person" maxlength="120" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:.8rem;"><?= e(t('admin_evacuation.f_phone')) ?></label>
                <input type="text" name="contact_phone" maxlength="30" class="form-control form-control-sm">
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-sm" style="background:var(--brand-primary);color:#fff;font-weight:600;">
                    <i class="bi bi-plus-circle me-1"></i><?= e(t('admin_evacuation.add_btn')) ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Existing centres ──────────────────────────────────────────── -->
<?php if ($centers === []): ?>
<div class="admin-card">
    <div class="admin-card-body text-center" style="padding:3rem 1rem;">
        <i class="bi bi-house-exclamation" style="font-size:2.5rem;color:var(--border);"></i>
        <p class="text-muted mt-3 mb-0" style="font-size:.9rem;"><?= e(t('admin_evacuation.empty')) ?></p>
    </div>
</div>
<?php else: ?>
<div class="admin-card">
    <div class="table-responsive">
        <table class="table admin-table mb-0">
            <thead>
                <tr>
                    <th style="padding-left:14px;"><?= e(t('admin_evacuation.f_name')) ?></th>
                    <th><?= e(t('admin_evacuation.f_purok')) ?></th>
                    <th><?= e(t('admin_evacuation.f_address')) ?></th>
                    <th><?= e(t('admin_evacuation.f_capacity')) ?></th>
                    <th><?= e(t('admin_evacuation.f_contact')) ?></th>
                    <th style="text-align:right;padding-right:16px;"><?= e(t('residents.col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($centers as $c): ?>
            <tr<?= (int) $c['is_active'] === 0 ? ' style="opacity:.55;"' : '' ?>>
                <td style="padding-left:14px;font-weight:600;font-size:.85rem;">
                    <?= e((string) $c['name']) ?>
                    <?php if ((int) $c['is_active'] === 0): ?>
                    <span class="status-badge ms-1" style="background:var(--surface-muted);color:var(--text-muted);">
                        <?= e(t('admin_evacuation.inactive')) ?>
                    </span>
                    <?php endif; ?>
                </td>
                <td style="font-size:.82rem;">
                    <?= $c['purok'] !== null && $c['purok'] !== ''
                        ? e((string) $c['purok'])
                        : '<span class="text-muted">' . e(t('evacuation.whole_barangay')) . '</span>' ?>
                </td>
                <td class="text-muted" style="font-size:.8rem;max-width:230px;">
                    <?= e(mb_substr((string) ($c['address'] ?? ''), 0, 70)) ?>
                </td>
                <td style="font-size:.82rem;"><?= $c['capacity'] !== null ? (int) $c['capacity'] : '—' ?></td>
                <td style="font-size:.8rem;">
                    <?= e((string) ($c['contact_person'] ?? '')) ?>
                    <?php if (!empty($c['contact_phone'])): ?>
                    <div class="text-muted" style="font-size:.74rem;"><?= e((string) $c['contact_phone']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="text-align:right;padding-right:14px;white-space:nowrap;">
                    <form method="post" action="<?= e(route('admin/evacuation/' . (int) $c['id'])) ?>" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="name"           value="<?= e((string) $c['name']) ?>">
                        <input type="hidden" name="purok"          value="<?= e((string) ($c['purok'] ?? '')) ?>">
                        <input type="hidden" name="address"        value="<?= e((string) ($c['address'] ?? '')) ?>">
                        <input type="hidden" name="latitude"       value="<?= e((string) ($c['latitude'] ?? '')) ?>">
                        <input type="hidden" name="longitude"      value="<?= e((string) ($c['longitude'] ?? '')) ?>">
                        <input type="hidden" name="capacity"       value="<?= e((string) ($c['capacity'] ?? '')) ?>">
                        <input type="hidden" name="contact_person" value="<?= e((string) ($c['contact_person'] ?? '')) ?>">
                        <input type="hidden" name="contact_phone"  value="<?= e((string) ($c['contact_phone'] ?? '')) ?>">
                        <input type="hidden" name="is_active"      value="<?= (int) $c['is_active'] === 1 ? '' : '1' ?>">
                        <button type="submit" class="btn-action"
                                title="<?= e((int) $c['is_active'] === 1 ? t('admin_evacuation.deactivate') : t('admin_evacuation.activate')) ?>">
                            <i class="bi <?= (int) $c['is_active'] === 1 ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
                        </button>
                    </form>
                    <form method="post" action="<?= e(route('admin/evacuation/' . (int) $c['id'] . '/delete')) ?>"
                          style="display:inline;"
                          onsubmit="return confirm(<?= e(json_encode(t('admin_evacuation.confirm_delete', ['name' => (string) $c['name']]))) ?>)">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <button type="submit" class="btn-action btn-action-danger" title="<?= e(t('admin_evacuation.delete')) ?>">
                            <i class="bi bi-trash3-fill"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
$content   = ob_get_clean();
$pageTitle = $pageTitle ?? t('admin_evacuation.title');
require __DIR__ . '/../../layouts/admin.php';
