<?php
/**
 * Admin & staff: Puroks & Residents.
 *
 * Variables: $totals, $puroks, $selected, $search, $find, $members,
 *            $candidates, $newLogin, $pendingCount
 */
$totals     ??= ['total' => 0, 'verified' => 0, 'pending' => 0, 'suspended' => 0, 'no_purok' => 0, 'with_phone' => 0];
$puroks     ??= [];
$members    ??= [];
$candidates ??= [];
$selected   ??= '';
$none       = \App\Controllers\PurokController::NONE;
$isPurok    = $selected !== '' && $selected !== $none;
$biggest    = max(1, ...array_map(static fn (array $p): int => $p['total'], $puroks ?: [['total' => 1]]));
$statusStyle = [
    'verified'  => 'background:#dcfce7;color:#166534;',
    'pending'   => 'background:#fef9c3;color:#92400e;',
    'suspended' => 'background:#fee2e2;color:#991b1b;',
];
$purokUrl = static fn (string $name): string => route('admin/puroks') . '?purok=' . rawurlencode($name);

ob_start();
?>

<!-- ── Header ──────────────────────────────────────────────────────────── -->
<div class="mb-4">
    <p class="mb-0" style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);">Management</p>
    <h1 class="mb-0 mt-1" style="font-size:1.55rem;font-weight:800;color:var(--text-primary);line-height:1.1;">Puroks &amp; Residents</h1>
    <p class="text-muted mt-1 mb-0" style="font-size:.82rem;max-width:760px;">
        See how many residents are registered in Barangay Bayogo and in each purok. Open a purok to see its members,
        add residents who are already registered, or register a new resident directly into that purok.
    </p>
</div>

<?php if (is_array($newLogin)): ?>
<div class="alert alert-warning" role="alert">
    <strong>Give these sign-in details to <?= e($newLogin['name']) ?>.</strong> They are shown only once.<br>
    Email: <code><?= e($newLogin['email']) ?></code> &nbsp;·&nbsp; Temporary password: <code><?= e($newLogin['password']) ?></code><br>
    <span class="small">Ask the resident to change the password after their first sign-in (Profile → Change password).</span>
</div>
<?php endif; ?>

<!-- ── Barangay totals ─────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ['Registered residents', $totals['total'],     'bi-people-fill',       'All resident accounts in Barangay Bayogo'],
        ['Verified',             $totals['verified'],  'bi-patch-check-fill',  'Can use the portal and receive SMS'],
        ['Waiting for approval', $totals['pending'],   'bi-hourglass-split',   'Registered online, not yet verified'],
        ['No purok yet',         $totals['no_purok'],  'bi-question-circle',   'Residents not assigned to a purok'],
    ] as [$label, $value, $icon, $help]): ?>
    <div class="col-6 col-lg-3">
        <div class="stat-card h-100">
            <p class="stat-card-label mb-0"><i class="bi <?= $icon ?> me-1" aria-hidden="true"></i><?= e($label) ?></p>
            <p class="stat-card-value"><?= number_format((int) $value) ?></p>
            <p class="text-muted mb-0" style="font-size:.72rem;"><?= e($help) ?></p>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Per purok ───────────────────────────────────────────────────────── -->
<div class="admin-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="h6 fw-bold mb-0"><i class="bi bi-houses me-1" aria-hidden="true"></i>Residents per purok</h2>
        <span class="small text-muted"><?= number_format($totals['with_phone']) ?> resident(s) have a mobile number for SMS</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:.86rem;">
            <thead>
                <tr>
                    <th>Purok</th>
                    <th style="min-width:160px;">Members</th>
                    <th class="text-center">Verified</th>
                    <th class="text-center">Pending</th>
                    <th class="text-center">With mobile</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($puroks as $p): ?>
                <tr class="<?= $selected === $p['name'] ? 'table-success' : '' ?>">
                    <td class="fw-semibold"><?= e($p['name']) ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <strong style="min-width:2ch;"><?= (int) $p['total'] ?></strong>
                            <div class="progress flex-grow-1" style="height:6px;" aria-hidden="true">
                                <div class="progress-bar" style="width:<?= round($p['total'] / $biggest * 100) ?>%;background:var(--brand-primary,#1a6b3a);"></div>
                            </div>
                        </div>
                    </td>
                    <td class="text-center"><?= (int) $p['verified'] ?></td>
                    <td class="text-center"><?= (int) $p['pending'] ?></td>
                    <td class="text-center"><?= (int) $p['with_phone'] ?></td>
                    <td class="text-end">
                        <a href="<?= e($purokUrl($p['name'])) ?>#members" class="btn btn-sm btn-outline-success">Open</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($totals['no_purok'] > 0): ?>
                <tr class="<?= $selected === $none ? 'table-warning' : '' ?>">
                    <td class="fw-semibold text-muted">No purok yet</td>
                    <td><strong><?= (int) $totals['no_purok'] ?></strong></td>
                    <td colspan="3" class="small text-muted">Open a purok and add them from its "Add residents" list.</td>
                    <td class="text-end"><a href="<?= e($purokUrl($none)) ?>#members" class="btn btn-sm btn-outline-secondary">View</a></td>
                </tr>
            <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td>Barangay Bayogo</td>
                    <td><?= number_format($totals['total']) ?></td>
                    <td class="text-center"><?= number_format($totals['verified']) ?></td>
                    <td class="text-center"><?= number_format($totals['pending']) ?></td>
                    <td class="text-center"><?= number_format($totals['with_phone']) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php if ($selected !== ''): ?>
<!-- ── Selected purok ──────────────────────────────────────────────────── -->
<div class="row g-4" id="members">
    <div class="<?= $isPurok ? 'col-xl-7' : 'col-12' ?>">
        <div class="admin-card h-100">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="h6 fw-bold mb-0">
                    <i class="bi bi-people me-1" aria-hidden="true"></i>
                    <?= $isPurok ? e($selected) . ' members' : 'Residents with no purok' ?>
                    <span class="badge bg-secondary ms-1"><?= count($members) ?></span>
                </h2>
                <form method="get" action="<?= e(route('admin/puroks')) ?>" class="d-flex gap-2">
                    <input type="hidden" name="purok" value="<?= e($selected) ?>">
                    <input type="search" name="q" value="<?= e($search) ?>" class="form-control form-control-sm" placeholder="Search name, email or phone" aria-label="Search members">
                    <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">Search</span></button>
                </form>
            </div>

            <?php if ($members === []): ?>
                <p class="text-muted small mb-0">
                    <?= $search !== '' ? 'No member matches your search.' : ($isPurok ? 'No residents in this purok yet. Add them using the forms on the right.' : 'Every resident has a purok.') ?>
                </p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" style="font-size:.84rem;">
                    <thead><tr><th>Name</th><th>Mobile</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($members as $m): ?>
                        <tr>
                            <td>
                                <a href="<?= e(route('admin/residents/' . (int) $m['id'])) ?>" class="fw-semibold text-decoration-none"><?= e($m['full_name']) ?></a>
                                <div class="text-muted" style="font-size:.74rem;">
                                    <?= \App\Models\Purok::isPlaceholderEmail((string) $m['email']) ? 'No email (added by staff)' : e($m['email']) ?>
                                </div>
                            </td>
                            <td class="text-nowrap"><?= $m['phone'] ? e($m['phone']) : '<span class="text-muted">—</span>' ?></td>
                            <td><span class="badge" style="<?= $statusStyle[$m['status']] ?? '' ?>"><?= e(ucfirst((string) $m['status'])) ?></span></td>
                            <td class="text-end">
                                <?php if ($isPurok): ?>
                                <form method="post" action="<?= e(route('admin/puroks/remove')) ?>"
                                      onsubmit="return confirm(<?= e(json_encode('Remove ' . $m['full_name'] . ' from ' . $selected . '? The account is kept.')) ?>);">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                                    <input type="hidden" name="purok" value="<?= e($selected) ?>">
                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0">Remove</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isPurok): ?>
    <div class="col-xl-5">
        <!-- Add existing residents -->
        <div class="admin-card mb-4">
            <h2 class="h6 fw-bold mb-1"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Add registered residents to <?= e($selected) ?></h2>
            <p class="text-muted small mb-2">Tick residents who already have an account. Residents in another purok are moved here.</p>
            <form method="get" action="<?= e(route('admin/puroks')) ?>" class="d-flex gap-2 mb-2">
                <input type="hidden" name="purok" value="<?= e($selected) ?>">
                <input type="search" name="find" value="<?= e($find) ?>" class="form-control form-control-sm" placeholder="Find a resident by name, email or phone" aria-label="Find a resident">
                <button class="btn btn-sm btn-outline-secondary" type="submit">Find</button>
            </form>
            <?php if ($candidates === []): ?>
                <p class="text-muted small mb-0"><?= $find !== '' ? 'No resident matches. Register them below.' : 'Every registered resident is already in this purok.' ?></p>
            <?php else: ?>
            <form method="post" action="<?= e(route('admin/puroks/assign')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="purok" value="<?= e($selected) ?>">
                <div class="border rounded p-2 mb-2" style="max-height:260px;overflow-y:auto;">
                    <?php foreach ($candidates as $c): ?>
                    <label class="d-flex align-items-start gap-2 py-1" style="font-size:.84rem;cursor:pointer;">
                        <input type="checkbox" class="form-check-input mt-1" name="user_ids[]" value="<?= (int) $c['id'] ?>">
                        <span>
                            <span class="fw-semibold"><?= e($c['full_name']) ?></span>
                            <span class="text-muted d-block" style="font-size:.74rem;">
                                <?= $c['zone'] ? 'Now in ' . e($c['zone']) : 'No purok yet' ?>
                                <?= $c['phone'] ? ' · ' . e($c['phone']) : '' ?>
                                · <?= e(ucfirst((string) $c['status'])) ?>
                            </span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Add ticked residents to <?= e($selected) ?></button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Register a new resident -->
        <div class="admin-card">
            <h2 class="h6 fw-bold mb-1"><i class="bi bi-person-vcard me-1" aria-hidden="true"></i>Register a new resident in <?= e($selected) ?></h2>
            <p class="text-muted small mb-3">For residents who come to the barangay hall in person. The account is verified right away and counted in this purok. With a mobile number they also receive barangay SMS.</p>
            <form method="post" action="<?= e(route('admin/puroks/residents')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="purok" value="<?= e($selected) ?>">
                <div class="mb-2">
                    <label class="form-label small fw-semibold" for="pr-name">Full name <span class="text-danger">*</span></label>
                    <input id="pr-name" type="text" name="full_name" class="form-control form-control-sm" maxlength="150" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold" for="pr-phone">Mobile number</label>
                    <input id="pr-phone" type="tel" name="phone" class="form-control form-control-sm" placeholder="09171234567" maxlength="20">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold" for="pr-address">House / street</label>
                    <input id="pr-address" type="text" name="address" class="form-control form-control-sm" maxlength="500" placeholder="e.g. House 12, near the chapel">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="pr-email">Email (optional)</label>
                    <input id="pr-email" type="email" name="email" class="form-control form-control-sm" maxlength="191">
                    <div class="form-text">With an email, a temporary password is shown once so the resident can sign in. Without one, the resident is counted and can receive SMS but cannot sign in.</div>
                </div>
                <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-person-plus-fill me-1" aria-hidden="true"></i>Register resident</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
$content   = ob_get_clean();
$pageTitle ??= 'Puroks & Residents';
require __DIR__ . '/../../layouts/admin.php';
?>
