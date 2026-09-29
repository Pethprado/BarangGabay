<?php
/**
 * The document-request queue — Personal Pickup & Digital Soft Copy.
 *
 * Variables: $requests (list), $status (string filter), $delivery (string filter), $search (string), $pageTitle, $pendingCount
 */
$requests = $requests ?? [];
$status   = (string) ($status ?? '');
$delivery = (string) ($delivery ?? '');
$search   = (string) ($search ?? '');

$statusClass = [
    'pending'    => 'background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;',
    'processing' => 'background:#fef3c7;color:#92400e;border:1px solid #fde68a;',
    'ready'      => 'background:#dcfce7;color:#166534;border:1px solid #bbf7d0;',
    'released'   => 'background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;',
    'rejected'   => 'background:#fee2e2;color:#991b1b;border:1px solid #fecaca;',
];

$totalCount   = count($requests);
$pickupCount  = count(array_filter($requests, fn($r) => ($r['delivery_method'] ?? 'pickup') === 'pickup'));
$digitalCount = count(array_filter($requests, fn($r) => ($r['delivery_method'] ?? 'pickup') === 'digital'));
$openCount    = count(array_filter($requests, fn($r) => in_array($r['status'] ?? '', ['pending', 'processing'], true)));

ob_start();
?>

<!-- Header Card with Summary & Navigation -->
<div class="admin-card mb-4">
    <div class="admin-card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div>
                <h1 style="font-size:1.25rem;font-weight:800;margin:0 0 .25rem;color:var(--text-primary);">
                    <i class="bi bi-file-earmark-ruled me-1 text-primary"></i> <?= e(t('admin_documents.title')) ?>
                </h1>
                <p class="text-muted mb-0" style="font-size:.84rem;">
                    <?= e(t('admin_documents.subtitle')) ?>
                </p>
            </div>

            <!-- Stats badges -->
            <div class="d-flex flex-wrap gap-2">
                <span class="status-badge" style="background:var(--surface-muted);color:var(--text-secondary);font-size:.8rem;">
                    <strong><?= $totalCount ?></strong> Total
                </span>
                <span class="status-badge" style="background:#eef2ff;color:#4338ca;font-size:.8rem;border:1px solid #c7d2fe;">
                    <i class="bi bi-file-earmark-arrow-down me-1"></i> <strong><?= $digitalCount ?></strong> Digital
                </span>
                <span class="status-badge" style="background:#f8fafc;color:#334155;font-size:.8rem;border:1px solid #e2e8f0;">
                    <i class="bi bi-building me-1"></i> <strong><?= $pickupCount ?></strong> Pickup
                </span>
                <?php if ($openCount > 0): ?>
                <span class="status-badge" style="background:#fef3c7;color:#b45309;font-size:.8rem;border:1px solid #fde68a;">
                    <i class="bi bi-hourglass-split me-1"></i> <strong><?= $openCount ?></strong> Waiting Staff
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="row g-2 align-items-center pt-2 border-top">
            <!-- Status Pills -->
            <div class="col-12 col-xl-7 d-flex flex-wrap gap-1.5 align-items-center">
                <span class="text-muted me-1" style="font-size:.78rem;font-weight:600;">Status:</span>
                <?php
                $statusFilters = ['' => t('admin_documents.filter_all')];
                foreach (array_keys(\App\Models\DocumentRequest::TRANSITIONS) as $s) {
                    $statusFilters[$s] = t('documents.status_' . $s);
                }
                foreach ($statusFilters as $k => $lbl):
                    $active = $status === $k;
                    $url = route('admin/documents' . '?' . http_build_query(array_filter([
                        'status'   => $k !== '' ? $k : null,
                        'delivery' => $delivery !== '' ? $delivery : null,
                        'search'   => $search !== '' ? $search : null,
                    ])));
                ?>
                <a href="<?= e($url) ?>"
                   class="status-badge"
                   style="text-decoration:none;font-size:.78rem;padding:4px 10px;border-radius:20px;<?= $active ? 'background:var(--brand-primary);color:#fff;font-weight:700;' : 'background:var(--surface-muted);color:var(--text-secondary);' ?>">
                    <?= e($lbl) ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Delivery Method Filter & Search Form -->
            <div class="col-12 col-xl-5">
                <form method="get" action="<?= e(route('admin/documents')) ?>" class="d-flex gap-2">
                    <?php if ($status !== ''): ?>
                    <input type="hidden" name="status" value="<?= e($status) ?>">
                    <?php endif; ?>

                    <select name="delivery" class="form-select form-select-sm" style="font-size:.8rem;min-width:130px;max-width:160px;" onchange="this.form.submit()">
                        <option value=""><?= e(t('admin_documents.filter_all')) ?> Delivery</option>
                        <option value="pickup" <?= $delivery === 'pickup' ? 'selected' : '' ?>><?= e(t('admin_documents.filter_pickup')) ?></option>
                        <option value="digital" <?= $delivery === 'digital' ? 'selected' : '' ?>><?= e(t('admin_documents.filter_digital')) ?></option>
                    </select>

                    <div class="input-group input-group-sm">
                        <input type="text" name="search" value="<?= e($search) ?>"
                               placeholder="<?= e(t('admin_documents.search_ph')) ?>"
                               class="form-control" style="font-size:.8rem;">
                        <button type="submit" class="btn btn-outline-secondary" title="Search">
                            <i class="bi bi-search"></i>
                        </button>
                        <?php if ($search !== '' || $delivery !== '' || $status !== ''): ?>
                        <a href="<?= e(route('admin/documents')) ?>" class="btn btn-outline-danger" title="Clear Filters">
                            <i class="bi bi-x-lg"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($requests === []): ?>
<div class="admin-card">
    <div class="admin-card-body text-center" style="padding:3.5rem 1rem;">
        <i class="bi bi-folder2-open" style="font-size:2.8rem;color:var(--border);"></i>
        <h3 class="mt-3 mb-1" style="font-size:1rem;font-weight:700;color:var(--text-primary);"><?= e(t('admin_documents.empty')) ?></h3>
        <p class="text-muted mb-3" style="font-size:.84rem;">Subukang baguhin ang mga filter o maghanap ng ibang reference number.</p>
        <a href="<?= e(route('admin/documents')) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-counterclockwise me-1"></i> I-reset ang Filters
        </a>
    </div>
</div>
<?php else: ?>

<!-- Main Requests Queue Table -->
<div class="admin-card">
    <div class="table-responsive">
        <table class="table admin-table mb-0 align-middle">
            <thead>
                <tr>
                    <th style="padding-left:16px;"><?= e(t('admin_documents.col_reference')) ?></th>
                    <th><?= e(t('admin_documents.col_resident')) ?></th>
                    <th><?= e(t('admin_documents.col_delivery')) ?></th>
                    <th><?= e(t('admin_documents.col_document')) ?> & <?= e(t('admin_documents.col_purpose')) ?></th>
                    <th><?= e(t('residents.col_status')) ?></th>
                    <th><?= e(t('admin_documents.col_attachment')) ?></th>
                    <th style="width:280px;text-align:right;padding-right:16px;"><?= e(t('admin_documents.col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($requests as $r):
                $id       = (int) $r['id'];
                $st       = (string) $r['status'];
                $deliv    = (string) ($r['delivery_method'] ?? 'pickup');
                $isDigital= $deliv === 'digital';
                $hasFile  = !empty($r['document_file_name']);
                $next     = \App\Models\DocumentRequest::TRANSITIONS[$st] ?? [];

                // Tailored status display text
                $displayStatus = ($isDigital && $st === 'ready')
                    ? t('documents.status_ready_digital')
                    : ($isDigital && $st === 'released' ? t('documents.status_released_digital') : t('documents.status_' . $st));
            ?>
            <tr>
                <!-- Reference No & Date -->
                <td style="padding-left:16px;white-space:nowrap;">
                    <div class="d-flex align-items-center gap-1.5">
                        <span style="font-family:ui-monospace,monospace;font-size:.82rem;font-weight:700;color:var(--brand-primary);">
                            <?= e((string) $r['reference_no']) ?>
                        </span>
                    </div>
                    <div class="text-muted" style="font-size:.72rem;">
                        <i class="bi bi-calendar3 me-1"></i><?= e(format_datetime((string) $r['requested_at'])) ?>
                    </div>
                </td>

                <!-- Resident Details -->
                <td>
                    <div style="font-weight:700;font-size:.86rem;color:var(--text-primary);">
                        <?= e((string) $r['full_name']) ?>
                    </div>
                    <div class="text-muted" style="font-size:.74rem;">
                        <i class="bi bi-geo-alt"></i> <?= e((string) ($r['zone'] ?: t('admin_documents.no_purok'))) ?>
                        <?php if (!empty($r['phone'])): ?>
                        &bull; <a href="tel:<?= e((string) $r['phone']) ?>" class="text-muted text-decoration-none"><i class="bi bi-telephone"></i> <?= e((string) $r['phone']) ?></a>
                        <?php endif; ?>
                    </div>
                </td>

                <!-- Delivery Method Badge -->
                <td>
                    <?php if ($isDigital): ?>
                    <span class="status-badge" style="background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;font-weight:700;font-size:.74rem;" title="Resident requested digital copy online">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i> <?= e(t('documents.badge_digital')) ?>
                    </span>
                    <?php else: ?>
                    <span class="status-badge" style="background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;font-size:.74rem;" title="Resident will pick up at Barangay Hall">
                        <i class="bi bi-building me-1"></i> <?= e(t('documents.badge_pickup')) ?>
                    </span>
                    <?php endif; ?>
                </td>

                <!-- Document Type & Purpose -->
                <td style="max-width:240px;">
                    <div style="font-size:.85rem;font-weight:600;color:var(--text-primary);">
                        <?= e(\App\Models\DocumentRequest::label((string) $r['document_type'])) ?>
                    </div>
                    <div class="text-muted text-truncate" style="font-size:.78rem;" title="<?= e((string) $r['purpose']) ?>">
                        <?= e((string) $r['purpose']) ?>
                    </div>
                    <?php if (!empty($r['staff_note'])): ?>
                    <div class="mt-1" style="font-size:.72rem;color:#b45309;background:#fef3c7;padding:2px 6px;border-radius:4px;display:inline-block;">
                        <i class="bi bi-chat-left-text me-1"></i> <?= e(mb_substr((string) $r['staff_note'], 0, 45)) ?>...
                    </div>
                    <?php endif; ?>
                </td>

                <!-- Status Badge -->
                <td>
                    <span class="status-badge" style="<?= $statusClass[$st] ?? '' ?>;font-weight:700;font-size:.76rem;">
                        <?php if ($st === 'ready'): ?>
                        <i class="bi bi-check-circle-fill me-1"></i>
                        <?php elseif ($st === 'processing'): ?>
                        <i class="bi bi-gear-fill me-1"></i>
                        <?php elseif ($st === 'pending'): ?>
                        <i class="bi bi-hourglass-split me-1"></i>
                        <?php endif; ?>
                        <?= e($displayStatus) ?>
                    </span>
                </td>

                <!-- Soft Copy Attachment Column -->
                <td style="min-width:180px;">
                    <?php if ($hasFile): ?>
                    <div class="p-1.5 rounded" style="background:#f8fafc;border:1px solid #e2e8f0;font-size:.75rem;">
                        <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
                            <span class="text-truncate" style="max-width:120px;font-weight:600;" title="<?= e((string) $r['document_file_name']) ?>">
                                <i class="bi bi-file-earmark-check text-primary"></i> <?= e((string) $r['document_file_name']) ?>
                            </span>
                            <span class="text-muted" style="font-size:.7rem;">
                                <?= \App\Models\DocumentRequest::formatFileSize((int) $r['document_file_size']) ?>
                            </span>
                        </div>
                        <div class="d-flex gap-1">
                            <!-- Preview button -->
                            <a href="<?= e(route('admin/documents/' . $id . '/preview')) ?>"
                               target="_blank"
                               class="btn btn-xs btn-outline-secondary py-0 px-1"
                               style="font-size:.7rem;"
                               title="Preview">
                                <i class="bi bi-eye"></i> Tingnan
                            </a>
                            <!-- Download button -->
                            <a href="<?= e(route('admin/documents/' . $id . '/download')) ?>"
                               class="btn btn-xs btn-outline-primary py-0 px-1"
                               style="font-size:.7rem;"
                               title="Download">
                                <i class="bi bi-download"></i>
                            </a>
                            <!-- Replace file modal trigger -->
                            <button type="button"
                                    class="btn btn-xs btn-outline-warning py-0 px-1"
                                    style="font-size:.7rem;"
                                    title="<?= e(t('admin_documents.btn_replace_file')) ?>"
                                    onclick="openUploadModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>, true)">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                            <!-- Remove file button -->
                            <form method="post"
                                  action="<?= e(route('admin/documents/' . $id . '/remove-file')) ?>"
                                  style="display:inline;"
                                  onsubmit="return confirm('<?= e(t('admin_documents.confirm_remove_file')) ?>');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button type="submit"
                                        class="btn btn-xs btn-outline-danger py-0 px-1"
                                        style="font-size:.7rem;"
                                        title="<?= e(t('admin_documents.btn_remove_file')) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- No file attached yet -->
                    <button type="button"
                            class="btn btn-sm <?= $isDigital ? 'btn-primary' : 'btn-outline-secondary' ?> py-1 px-2"
                            style="font-size:.76rem;font-weight:600;"
                            onclick="openUploadModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>, false)">
                        <i class="bi bi-cloud-arrow-up-fill me-1"></i>
                        <?= $isDigital ? 'Attach Soft Copy' : 'Attach File' ?>
                    </button>
                    <?php endif; ?>
                </td>

                <!-- Status Update & Details Actions -->
                <td style="text-align:right;padding-right:16px;">
                    <div class="d-flex gap-1 justify-content-end align-items-center flex-wrap">
                        <?php if ($next !== []): ?>
                        <form method="post"
                              action="<?= e(route('admin/documents/' . $id . '/status')) ?>"
                              class="d-flex gap-1 align-items-center"
                              onsubmit="return confirm('<?= e(t('admin_documents.confirm_change_status')) ?>');">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="return_status" value="<?= e($status) ?>">
                            <input type="hidden" name="return_delivery" value="<?= e($delivery) ?>">
                            <input type="hidden" name="return_search" value="<?= e($search) ?>">

                            <input type="text" name="staff_note" maxlength="2000"
                                   placeholder="<?= e(t('admin_documents.note_ph')) ?>"
                                   class="form-control form-control-sm"
                                   style="max-width:115px;font-size:.74rem;">

                            <select name="status" class="form-select form-select-sm"
                                    style="max-width:115px;font-size:.74rem;font-weight:600;">
                                <?php foreach ($next as $to):
                                    $toLabel = ($isDigital && $to === 'ready')
                                        ? t('documents.status_ready_digital')
                                        : ($isDigital && $to === 'released' ? t('documents.status_released_digital') : t('documents.status_' . $to));
                                ?>
                                <option value="<?= e($to) ?>"><?= e($toLabel) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <button type="submit" class="btn-action" title="<?= e(t('admin_documents.apply')) ?>">
                                <i class="bi bi-check2"></i>
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="text-muted me-2" style="font-size:.74rem;font-weight:600;">
                            <i class="bi bi-lock-fill"></i> <?= e(t('admin_documents.final')) ?>
                        </span>
                        <?php endif; ?>

                        <!-- Details & Audit Trail modal trigger -->
                        <button type="button"
                                class="btn-action"
                                title="<?= e(t('admin_documents.btn_view_logs')) ?>"
                                onclick="openDetailsModal(<?= $id ?>, '<?= e((string) $r['reference_no']) ?>')">
                            <i class="bi bi-clock-history"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ========================================================================= -->
<!-- 1. UPLOAD / REPLACE DOCUMENT MODAL                                         -->
<!-- ========================================================================= -->
<div class="modal fade" id="uploadDocumentModal" tabindex="-1" aria-labelledby="uploadModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none;box-shadow:0 20px 40px rgba(0,0,0,0.15);">
            <form method="post" id="uploadDocForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="return_status" value="<?= e($status) ?>">
                <input type="hidden" name="return_delivery" value="<?= e($delivery) ?>">
                <input type="hidden" name="return_search" value="<?= e($search) ?>">

                <div class="modal-header" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;border-radius:16px 16px 0 0;padding:16px 20px;">
                    <div>
                        <h5 class="modal-title mb-0" id="uploadModalTitle" style="font-size:1.05rem;font-weight:800;color:var(--text-primary);">
                            <i class="bi bi-cloud-arrow-up text-primary me-1"></i> <?= e(t('admin_documents.upload_modal_title')) ?>
                        </h5>
                        <div class="text-muted" id="modalReqSubtitle" style="font-size:.78rem;"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body" style="padding:20px;">
                    <p class="text-muted mb-3" style="font-size:.82rem;">
                        <?= e(t('admin_documents.upload_modal_desc')) ?>
                    </p>

                    <!-- File Picker Area -->
                    <div class="mb-3">
                        <label for="document_file" class="form-label mb-1" style="font-size:.8rem;font-weight:700;">
                            <?= e(t('admin_documents.upload_file_label')) ?> <span class="text-danger">*</span>
                        </label>
                        <input type="file"
                               id="document_file"
                               name="document_file"
                               required
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,image/jpeg,image/png"
                               class="form-control"
                               style="font-size:.82rem;"
                               onchange="validateSelectedFile(this)">
                        <div class="form-text" style="font-size:.74rem;">
                            Tinatanggap: PDF, DOC, DOCX, JPG, PNG (Max 10MB).
                        </div>
                    </div>

                    <!-- Note / Instructions to Resident -->
                    <div class="mb-3">
                        <label for="modal_staff_note" class="form-label mb-1" style="font-size:.8rem;font-weight:700;">
                            <?= e(t('admin_documents.upload_note_label')) ?>
                        </label>
                        <textarea id="modal_staff_note"
                                  name="staff_note"
                                  rows="3"
                                  maxlength="2000"
                                  placeholder="<?= e(t('admin_documents.upload_note_ph')) ?>"
                                  class="form-control"
                                  style="font-size:.82rem;"></textarea>
                    </div>

                    <!-- Checkbox: Mark as Ready -->
                    <div class="form-check p-3 rounded" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                        <input class="form-check-input" type="checkbox" name="mark_ready" id="mark_ready" value="1" checked>
                        <label class="form-check-label" for="mark_ready" style="font-size:.8rem;font-weight:600;color:#166534;">
                            <i class="bi bi-bell-fill me-1"></i> I-mark agad bilang "Ready for download" at i-text ang residente
                        </label>
                    </div>
                </div>

                <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;border-radius:0 0 16px 16px;padding:12px 20px;">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Kanselahin</button>
                    <button type="submit" id="submitUploadBtn" class="btn btn-sm btn-primary font-weight-bold">
                        <i class="bi bi-cloud-arrow-up-fill me-1"></i> <?= e(t('admin_documents.upload_btn_submit')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 2. DETAILS & AUDIT TRAIL MODAL                                            -->
<!-- ========================================================================= -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:16px;border:none;box-shadow:0 20px 40px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;border-radius:16px 16px 0 0;padding:16px 20px;">
                <div>
                    <h5 class="modal-title mb-0" id="detailsModalTitle" style="font-size:1.05rem;font-weight:800;color:var(--text-primary);">
                        <i class="bi bi-clock-history text-primary me-1"></i> <?= e(t('admin_documents.details_modal_title')) ?>
                    </h5>
                    <div class="text-muted" id="detailsModalSubtitle" style="font-size:.78rem;"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body" style="padding:20px;max-height:75vh;overflow-y:auto;">
                <!-- Loading Spinner -->
                <div id="detailsLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted mt-2" style="font-size:.82rem;">Kinukuha ang buong kasaysayan ng request...</p>
                </div>

                <!-- Content Container -->
                <div id="detailsContent" style="display:none;">
                    <!-- Request Summary Cards -->
                    <div class="row g-2 mb-4">
                        <div class="col-md-6">
                            <div class="p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0;">
                                <div class="text-muted mb-1" style="font-size:.72rem;text-transform:uppercase;font-weight:700;">Residente</div>
                                <div id="dtResidentName" style="font-weight:700;font-size:.9rem;color:var(--text-primary);"></div>
                                <div id="dtResidentContact" class="text-muted" style="font-size:.78rem;"></div>
                                <div id="dtResidentAddress" class="text-muted" style="font-size:.78rem;"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0;">
                                <div class="text-muted mb-1" style="font-size:.72rem;text-transform:uppercase;font-weight:700;">Detalye ng Dokumento</div>
                                <div id="dtDocType" style="font-weight:700;font-size:.9rem;color:var(--brand-primary);"></div>
                                <div id="dtDeliveryMethod" class="mt-0.5" style="font-size:.78rem;"></div>
                                <div id="dtPurpose" class="text-muted mt-1" style="font-size:.78rem;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Attached File Info (if any) -->
                    <div id="dtFileSection" class="mb-4 p-3 rounded" style="display:none;background:#eef2ff;border:1px solid #c7d2fe;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-file-earmark-check-fill text-primary" style="font-size:1.4rem;"></i>
                                <div>
                                    <div id="dtFileName" style="font-weight:700;font-size:.85rem;color:#1e1b4b;"></div>
                                    <div id="dtFileMeta" class="text-muted" style="font-size:.74rem;"></div>
                                </div>
                            </div>
                            <div class="d-flex gap-1">
                                <a id="dtFilePreviewBtn" href="#" target="_blank" class="btn btn-sm btn-outline-primary" style="font-size:.75rem;">
                                    <i class="bi bi-eye"></i> Preview
                                </a>
                                <a id="dtFileDownloadBtn" href="#" class="btn btn-sm btn-primary" style="font-size:.75rem;">
                                    <i class="bi bi-download"></i> Download
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Audit Trail Timeline -->
                    <h6 style="font-size:.86rem;font-weight:800;color:var(--text-primary);margin-bottom:12px;">
                        <i class="bi bi-journal-text me-1"></i> Audit Trail & Timeline
                    </h6>

                    <div id="logsTimeline" class="position-relative ps-4" style="border-left:2px solid #e2e8f0;">
                        <!-- Timeline entries inserted via JS -->
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;border-radius:0 0 16px 16px;padding:12px 20px;">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Isara</button>
            </div>
        </div>
    </div>
</div>

<script>
// Open Upload/Replace Modal
function openUploadModal(request, isReplace) {
    const modalEl = document.getElementById('uploadDocumentModal');
    const form = document.getElementById('uploadDocForm');
    const title = document.getElementById('uploadModalTitle');
    const subtitle = document.getElementById('modalReqSubtitle');
    const note = document.getElementById('modal_staff_note');
    const fileInput = document.getElementById('document_file');

    fileInput.value = '';
    const routeAction = isReplace
        ? '<?= e(route('admin/documents/')) ?>' + request.id + '/replace'
        : '<?= e(route('admin/documents/')) ?>' + request.id + '/upload';

    form.action = routeAction;
    title.innerHTML = isReplace
        ? '<i class="bi bi-arrow-repeat text-warning me-1"></i> Palitan ang Soft Copy'
        : '<i class="bi bi-cloud-arrow-up text-primary me-1"></i> Mag-upload ng Soft Copy';

    subtitle.innerHTML = 'Reference: <strong>' + request.reference_no + '</strong> &bull; ' + request.full_name;

    if (request.staff_note) {
        note.value = request.staff_note;
    } else {
        note.value = 'Opisyal na pinirmahan ng Punong Barangay. Handa nang i-print.';
    }

    form.onsubmit = function() {
        if (!fileInput.files || fileInput.files.length === 0) {
            alert('Pumili muna ng dokumento bago mag-upload.');
            return false;
        }
        return confirm('<?= e(t('admin_documents.confirm_upload')) ?>');
    };

    const modal = new bootstrap.Modal(modalEl);
    modal.show();
}

// Client-side file validation (10MB limit and format)
function validateSelectedFile(input) {
    if (!input.files || input.files.length === 0) return;
    const file = input.files[0];
    const maxBytes = 10 * 1024 * 1024; // 10MB
    const allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
    const ext = file.name.split('.').pop().toLowerCase();

    if (!allowed.includes(ext)) {
        alert('Di-wastong format (' + ext + '). Ang tinatanggap lamang ay PDF, DOC, DOCX, JPG, o PNG.');
        input.value = '';
        return;
    }

    if (file.size > maxBytes) {
        alert('Masyadong malaki ang file (' + (file.size / 1024 / 1024).toFixed(1) + ' MB). Ang limitasyon ay 10MB.');
        input.value = '';
        return;
    }
}

// Open Details & Audit Trail Modal via AJAX
function openDetailsModal(requestId, refNo) {
    const modalEl = document.getElementById('detailsModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    const subtitle = document.getElementById('detailsModalSubtitle');
    subtitle.innerHTML = 'Reference: <strong>' + refNo + '</strong>';

    const loading = document.getElementById('detailsLoading');
    const content = document.getElementById('detailsContent');
    loading.style.display = 'block';
    content.style.display = 'none';

    fetch('<?= e(route('admin/documents/')) ?>' + requestId + '/details')
        .then(r => {
            if (!r.ok) throw new Error('Failed to load');
            return r.json();
        })
        .then(data => {
            loading.style.display = 'none';
            content.style.display = 'block';

            const req = data.request;
            const logs = data.logs;

            // Resident Details
            document.getElementById('dtResidentName').textContent = req.full_name;
            document.getElementById('dtResidentContact').textContent = (req.phone ? req.phone + ' • ' : '') + (req.email || '');
            document.getElementById('dtResidentAddress').textContent = 'Purok/Zone: ' + (req.zone || 'None') + (req.address ? ' • ' + req.address : '');

            // Document Details
            document.getElementById('dtDocType').textContent = req.document_type;
            const isDig = req.delivery_method === 'digital';
            document.getElementById('dtDeliveryMethod').innerHTML = isDig
                ? '<span class="badge bg-primary">📄 Digital Soft Copy (Online)</span>'
                : '<span class="badge bg-secondary">🏢 Personal Pickup (Counter)</span>';
            document.getElementById('dtPurpose').textContent = 'Layunin: ' + req.purpose;

            // File section
            const fileSec = document.getElementById('dtFileSection');
            if (req.document_file_name) {
                fileSec.style.display = 'block';
                document.getElementById('dtFileName').textContent = req.document_file_name;
                document.getElementById('dtFileMeta').textContent = (req.document_file_type || '') + (req.document_uploaded_at ? ' • Na-upload: ' + req.document_uploaded_at : '');
                document.getElementById('dtFilePreviewBtn').href = '<?= e(route('admin/documents/')) ?>' + req.id + '/preview';
                document.getElementById('dtFileDownloadBtn').href = '<?= e(route('admin/documents/')) ?>' + req.id + '/download';
            } else {
                fileSec.style.display = 'none';
            }

            // Timeline logs
            const timeline = document.getElementById('logsTimeline');
            timeline.innerHTML = '';

            if (!logs || logs.length === 0) {
                timeline.innerHTML = '<p class="text-muted" style="font-size:.8rem;">Walang naitalang audit logs para sa request na ito.</p>';
            } else {
                logs.forEach(log => {
                    const item = document.createElement('div');
                    item.className = 'mb-3 position-relative';
                    const iconColor = log.action.includes('file') ? '#4f46e5' : (log.action.includes('status') ? '#059669' : '#0284c7');
                    item.innerHTML = `
                        <div style="position:absolute;left:-23px;top:2px;width:12px;height:12px;border-radius:50%;background:${iconColor};border:2px solid #fff;box-shadow:0 0 0 2px #e2e8f0;"></div>
                        <div class="d-flex align-items-center justify-content-between">
                            <strong style="font-size:.8rem;color:var(--text-primary);text-transform:capitalize;">${log.action.replace('_', ' ')}</strong>
                            <span class="text-muted" style="font-size:.72rem;">${log.created_at}</span>
                        </div>
                        <div class="text-muted" style="font-size:.76rem;">
                            Actor: <strong>${log.actor_name || 'System / Resident'}</strong> ${log.actor_role ? '(' + log.actor_role + ')' : ''}
                        </div>
                        <div style="font-size:.78rem;color:var(--text-secondary);margin-top:2px;">
                            ${log.details || ''}
                        </div>
                    `;
                    timeline.appendChild(item);
                });
            }
        })
        .catch(err => {
            loading.innerHTML = '<div class="text-danger py-4"><i class="bi bi-exclamation-triangle"></i> Hindi maikarga ang detalye: ' + err.message + '</div>';
        });
}
</script>

<?php
$content   = ob_get_clean();
$pageTitle = $pageTitle ?? t('admin_documents.title');
require __DIR__ . '/../../layouts/admin.php';
