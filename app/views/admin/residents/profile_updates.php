<?php
/**
 * Admin view for Resident Profile Update Requests.
 * Variables: $requests (array), $status (string), $search (string), $pendingCount (int)
 */
$pageTitle = 'Resident Profile Update Requests';
ob_start();
?>

<div class="container-fluid py-3">

    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-xs">
                    <li class="breadcrumb-item"><a href="/admin/dashboard" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="/admin/residents" class="text-decoration-none">Residents</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Profile Updates</li>
                </ol>
            </nav>
            <h1 class="h4 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-pencil-square text-success"></i> Resident Profile Update Requests
                <?php if ($pendingCount > 0): ?>
                <span class="badge bg-danger rounded-pill fs-7"><?= $pendingCount ?> Pending</span>
                <?php endif; ?>
            </h1>
            <p class="text-muted text-xs mb-0 mt-1">Review, approve, or reject resident requests to update locked verified personal information.</p>
        </div>

        <div class="d-flex gap-2">
            <a href="/admin/residents" class="btn btn-outline-secondary btn-sm rounded-3">
                <i class="bi bi-arrow-left me-1"></i> Back to Residents
            </a>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="get" action="/admin/profile-updates" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm rounded-3" onchange="this.form.submit()">
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved Updates</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected Updates</option>
                        <option value="" <?= $status === '' ? 'selected' : '' ?>>All Requests</option>
                    </select>
                </div>
                <div class="col-md-7">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by resident name, email, or reason..." class="form-control border-start-0 rounded-end-3">
                    </div>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-sm btn-dark rounded-3">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Requests Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-sm">
                <thead class="table-light text-xs text-uppercase text-muted border-bottom">
                    <tr>
                        <th class="ps-4">Resident</th>
                        <th>Proposed Changes vs Current</th>
                        <th>Reason</th>
                        <th>Supporting Doc</th>
                        <th>Status</th>
                        <th>Date Submitted</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
                            Walang profile update request sa kasalukuyan.
                        </td>
                    </tr>
                    <?php endif; ?>

                    <?php foreach ($requests as $r): ?>
                    <tr>
                        <!-- Resident Info -->
                        <td class="ps-4">
                            <div class="fw-bold text-dark"><?= e($r['resident_name']) ?></div>
                            <div class="text-muted text-xs">ID #<?= $r['user_id'] ?> | <?= e($r['resident_email']) ?></div>
                            <div class="text-muted text-xs"><?= e($r['resident_phone'] ?: 'No phone') ?></div>
                        </td>

                        <!-- Changes Comparison -->
                        <td style="max-width: 320px;">
                            <div class="bg-light p-2 rounded-3 border text-xs">
                                <?php foreach ($r['requested_changes_arr'] as $k => $newVal): ?>
                                <?php $oldVal = $r['current_values_arr'][$k] ?? '—'; ?>
                                <div class="mb-1">
                                    <span class="badge bg-secondary-subtle text-secondary me-1"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($k))) ?></span>
                                    <span class="text-danger text-decoration-line-through me-1"><?= htmlspecialchars((string) $oldVal) ?></span>
                                    <i class="bi bi-arrow-right text-muted"></i>
                                    <span class="text-success fw-bold ms-1"><?= htmlspecialchars((string) $newVal) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </td>

                        <!-- Reason -->
                        <td style="max-width: 220px;">
                            <div class="text-xs text-dark" style="white-space: pre-wrap;"><?= e($r['reason']) ?></div>
                        </td>

                        <!-- Supporting Document -->
                        <td>
                            <?php if (!empty($r['supporting_doc_name'])): ?>
                            <a href="/admin/profile-updates/<?= $r['id'] ?>/document" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill text-xs py-1 px-2.5">
                                <i class="bi bi-paperclip"></i> View Proof
                            </a>
                            <?php else: ?>
                            <span class="text-muted text-xs">None</span>
                            <?php endif; ?>
                        </td>

                        <!-- Status Badge -->
                        <td>
                            <?php
                            $st = (string) $r['status'];
                            $badge = match ($st) {
                                'approved' => '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Approved</span>',
                                'rejected' => '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">Rejected</span>',
                                default    => '<span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill">Pending</span>',
                            };
                            echo $badge;
                            ?>
                        </td>

                        <!-- Date Submitted -->
                        <td class="text-xs text-muted">
                            <?= date('M d, Y', strtotime((string) $r['created_at'])) ?><br>
                            <span class="text-[11px]"><?= date('g:i A', strtotime((string) $r['created_at'])) ?></span>
                        </td>

                        <!-- Actions -->
                        <td class="text-end pe-4">
                            <?php if ($r['status'] === 'pending'): ?>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-success rounded-start-3"
                                        data-bs-toggle="modal"
                                        data-bs-target="#approveModal_<?= $r['id'] ?>">
                                    <i class="bi bi-check-lg"></i> Approve
                                </button>
                                <button type="button" class="btn btn-danger rounded-end-3"
                                        data-bs-toggle="modal"
                                        data-bs-target="#rejectModal_<?= $r['id'] ?>">
                                    <i class="bi bi-x-lg"></i> Reject
                                </button>
                            </div>

                            <!-- Modal: Approve Request -->
                            <div class="modal fade text-start" id="approveModal_<?= $r['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content rounded-4 border-0 shadow">
                                        <div class="modal-header bg-success-subtle border-0">
                                            <h6 class="modal-title fw-bold text-success">Approve Profile Update #<?= $r['id'] ?></h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="post" action="/admin/profile-updates/<?= $r['id'] ?>/approve">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <div class="modal-body p-4 text-xs">
                                                <p class="mb-2">Are you sure you want to approve this profile update for <strong><?= e($r['resident_name']) ?></strong>?</p>
                                                <div class="alert alert-info py-2 mb-3">
                                                    The resident's database record will be updated automatically with all proposed changes.
                                                </div>
                                                <label class="form-label fw-bold">Admin Remarks (Optional):</label>
                                                <textarea name="admin_notes" class="form-control form-control-sm rounded-3" rows="2" placeholder="e.g. Verified against submitted proof of residency."></textarea>
                                            </div>
                                            <div class="modal-footer border-0 p-3 bg-light">
                                                <button type="button" class="btn btn-sm btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-sm btn-success rounded-3 fw-bold">Confirm & Update DB</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal: Reject Request -->
                            <div class="modal fade text-start" id="rejectModal_<?= $r['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content rounded-4 border-0 shadow">
                                        <div class="modal-header bg-danger-subtle border-0">
                                            <h6 class="modal-title fw-bold text-danger">Reject Profile Update #<?= $r['id'] ?></h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="post" action="/admin/profile-updates/<?= $r['id'] ?>/reject">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <div class="modal-body p-4 text-xs">
                                                <p class="mb-2">Please state the reason for rejecting this update request. This will be sent directly to the resident.</p>
                                                <label class="form-label fw-bold text-danger">Rejection Reason (Required) *</label>
                                                <textarea name="rejection_reason" required class="form-control form-control-sm rounded-3 mb-2" rows="3" placeholder="e.g. Submitted proof of residency is illegible. Please submit a valid billing statement or brgy clearance."></textarea>
                                            </div>
                                            <div class="modal-footer border-0 p-3 bg-light">
                                                <button type="button" class="btn btn-sm btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-sm btn-danger rounded-3 fw-bold">Confirm Rejection</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <?php else: ?>
                            <span class="text-muted text-xs">Reviewed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
