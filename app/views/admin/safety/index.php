<?php
/**
 * After the storm: who has not been heard from.
 *
 * The layout puts the work list above the reassurance. The count of residents
 * who answered "safe" is comforting and not actionable; the ones who asked for
 * help, and then the ones who have said nothing, are what the tanod walk to.
 *
 * Variables: $advisories, $advisoryId, $rollUp, $attention, $pageTitle, $pendingCount
 */
$advisories = $advisories ?? [];
$rollUp     = $rollUp     ?? [];
$attention  = $attention  ?? [];
$advisoryId = (int) ($advisoryId ?? 0);

$totals = ['residents' => 0, 'safe' => 0, 'needs_help' => 0, 'silent' => 0];
foreach ($rollUp as $r) {
    foreach (array_keys($totals) as $k) {
        $totals[$k] += (int) $r[$k];
    }
}

ob_start();
?>

<div class="admin-card mb-4">
    <div class="admin-card-body">
        <h1 style="font-size:1.15rem;font-weight:800;margin:0 0 .25rem;"><?= e(t('admin_safety.title')) ?></h1>
        <p class="text-muted mb-3" style="font-size:.82rem;line-height:1.6;">
            <?= e(t('admin_safety.subtitle')) ?>
        </p>

        <?php if ($advisories === []): ?>
        <?php /* An empty state that only says "nothing here" leaves the
                 reader to work out where the switch is. It is on the
                 announcement form, two clicks away, so say so and link it. */ ?>
        <p class="mb-2" style="font-size:.85rem;"><?= e(t('admin_safety.no_advisories')) ?></p>
        <a href="<?= e(route('admin/announcements/create')) ?>" class="btn-barangay">
            <i class="bi bi-plus-lg me-1"></i><?= e(t('admin_safety.start_one')) ?>
        </a>
        <p class="text-muted mb-0 mt-2" style="font-size:.78rem;line-height:1.6;">
            <?= e(t('admin_safety.start_hint')) ?>
        </p>
        <?php else: ?>
        <form method="get" action="<?= e(route('admin/safety')) ?>" class="d-flex gap-2 align-items-center flex-wrap">
            <label for="advisory" class="form-label mb-0" style="font-size:.8rem;font-weight:600;">
                <?= e(t('admin_safety.advisory')) ?>
            </label>
            <select id="advisory" name="advisory" class="form-select form-select-sm"
                    style="max-width:420px;font-size:.82rem;" onchange="this.form.submit()">
                <?php foreach ($advisories as $a): ?>
                <option value="<?= (int) $a['id'] ?>" <?= $advisoryId === (int) $a['id'] ? 'selected' : '' ?>>
                    <?= e(mb_substr((string) $a['title'], 0, 70)) ?>
                    (<?= e(format_datetime((string) $a['published_at'])) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($advisoryId > 0): ?>

<!-- ── Headline numbers ──────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['needs_help', t('admin_safety.needs_help'), '#fee2e2', '#991b1b', 'bi-exclamation-octagon-fill'],
        ['silent',     t('admin_safety.silent'),     '#fef3c7', '#92400e', 'bi-question-circle-fill'],
        ['safe',       t('admin_safety.safe'),       '#d4edda', '#155724', 'bi-check-circle-fill'],
        ['residents',  t('admin_safety.residents'),  '#f1f5f9', '#475569', 'bi-people-fill'],
    ];
    foreach ($cards as [$key, $label, $bg, $fg, $icon]):
    ?>
    <div class="col-6 col-lg-3">
        <div class="admin-card h-100">
            <div class="admin-card-body d-flex align-items-center gap-3">
                <span style="display:grid;place-items:center;width:42px;height:42px;border-radius:.6rem;background:<?= $bg ?>;color:<?= $fg ?>;">
                    <i class="bi <?= $icon ?>" style="font-size:1.1rem;"></i>
                </span>
                <div>
                    <div style="font-size:1.5rem;font-weight:800;line-height:1;"><?= (int) $totals[$key] ?></div>
                    <div class="text-muted" style="font-size:.76rem;"><?= e($label) ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Who to go to ──────────────────────────────────────────────── -->
<div class="admin-card mb-4">
    <div class="admin-card-header">
        <h2 style="font-size:.95rem;font-weight:700;margin:0;"><?= e(t('admin_safety.attention_title')) ?></h2>
    </div>
    <?php if ($attention === []): ?>
    <div class="admin-card-body text-center" style="padding:2.5rem 1rem;">
        <i class="bi bi-check2-circle" style="font-size:2rem;color:#22c55e;"></i>
        <p class="mt-2 mb-0" style="font-size:.88rem;font-weight:600;"><?= e(t('admin_safety.all_answered')) ?></p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table admin-table mb-0">
            <thead>
                <tr>
                    <th style="padding-left:14px;"><?= e(t('admin_safety.col_resident')) ?></th>
                    <th><?= e(t('admin_safety.col_purok')) ?></th>
                    <th><?= e(t('admin_safety.col_state')) ?></th>
                    <th><?= e(t('admin_safety.col_note')) ?></th>
                    <th style="padding-right:14px;"><?= e(t('admin_safety.col_contact')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($attention as $p):
                $state = (string) $p['state'];
                $css   = $state === 'needs_help'
                    ? 'background:#fee2e2;color:#991b1b;'
                    : 'background:#fef3c7;color:#92400e;';
            ?>
            <tr>
                <td style="padding-left:14px;">
                    <div style="font-weight:600;font-size:.85rem;"><?= e((string) $p['full_name']) ?></div>
                    <?php if (!empty($p['address'])): ?>
                    <div class="text-muted" style="font-size:.74rem;"><?= e(mb_substr((string) $p['address'], 0, 60)) ?></div>
                    <?php endif; ?>
                </td>
                <td style="font-size:.82rem;">
                    <?= e((string) ($p['zone'] ?: t('admin_documents.no_purok'))) ?>
                </td>
                <td>
                    <span class="status-badge" style="<?= $css ?>">
                        <?= e(t('admin_safety.state_' . $state)) ?>
                    </span>
                </td>
                <td class="text-muted" style="font-size:.8rem;max-width:230px;">
                    <?= e((string) ($p['note'] ?? '')) ?>
                </td>
                <td style="padding-right:14px;font-size:.82rem;white-space:nowrap;">
                    <?php if (!empty($p['phone'])): ?>
                    <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $p['phone']) ?? '') ?>"
                       style="font-weight:600;"><?= e((string) $p['phone']) ?></a>
                    <?php else: ?>
                    <span class="text-muted"><?= e(t('admin_safety.no_phone')) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ── Per purok ─────────────────────────────────────────────────── -->
<div class="admin-card">
    <div class="admin-card-header">
        <h2 style="font-size:.95rem;font-weight:700;margin:0;"><?= e(t('admin_safety.per_purok')) ?></h2>
    </div>
    <div class="table-responsive">
        <table class="table admin-table mb-0">
            <thead>
                <tr>
                    <th style="padding-left:14px;"><?= e(t('admin_safety.col_purok')) ?></th>
                    <th><?= e(t('admin_safety.residents')) ?></th>
                    <th><?= e(t('admin_safety.safe')) ?></th>
                    <th><?= e(t('admin_safety.needs_help')) ?></th>
                    <th style="padding-right:14px;"><?= e(t('admin_safety.silent')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rollUp as $r): ?>
            <tr>
                <td style="padding-left:14px;font-weight:600;font-size:.85rem;"><?= e($r['purok']) ?></td>
                <td style="font-size:.85rem;"><?= (int) $r['residents'] ?></td>
                <td style="font-size:.85rem;color:#15803d;font-weight:600;"><?= (int) $r['safe'] ?></td>
                <td style="font-size:.85rem;<?= $r['needs_help'] > 0 ? 'color:#b91c1c;font-weight:700;' : '' ?>">
                    <?= (int) $r['needs_help'] ?>
                </td>
                <td style="padding-right:14px;font-size:.85rem;<?= $r['silent'] > 0 ? 'color:#b45309;font-weight:700;' : '' ?>">
                    <?= (int) $r['silent'] ?>
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
$pageTitle = $pageTitle ?? t('admin_safety.title');
require __DIR__ . '/../../layouts/admin.php';
