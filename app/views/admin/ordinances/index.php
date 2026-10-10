<?php
$ordinances = $ordinances ?? [];

$total    = count($ordinances);
$active   = count(array_filter($ordinances, static fn($o) => $o['status'] === 'active'));
$draft    = count(array_filter($ordinances, static fn($o) => $o['status'] === 'draft'));
$repealed = count(array_filter($ordinances, static fn($o) => $o['status'] === 'repealed'));

ob_start();
?>

<!-- Page header -->
<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <p class="mb-0" style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);"><?= e(t('admin_announcements.eyebrow')) ?></p>
        <h1 class="mb-0 mt-1" style="font-size:1.55rem;font-weight:800;color:var(--text-primary);line-height:1.1;"><?= e(t('admin_ordinances.title')) ?></h1>
        <p class="text-muted mt-1 mb-0" style="font-size:.82rem;">
            <?= e(t('admin_ordinances.summary', ['total' => $total, 'active' => $active, 'draft' => $draft])) ?>
        </p>
    </div>
    <a href="<?= e(route('admin/ordinances/upload')) ?>" class="btn-barangay">
        <i class="bi bi-cloud-upload me-1"></i> <?= e(t('admin_ordinances.upload_new')) ?>
    </a>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-sm-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <p class="stat-card-label mb-0"><?= e(t('admin_announcements.stat_total')) ?></p>
                <div class="stat-card-icon stat-icon-blue"><i class="bi bi-journal-text"></i></div>
            </div>
            <p class="stat-card-value"><?= $total ?></p>
        </div>
    </div>
    <div class="col-6 col-sm-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <p class="stat-card-label mb-0"><?= e(t('admin_ordinances.stat_active')) ?></p>
                <div class="stat-card-icon stat-icon-green"><i class="bi bi-check-circle-fill"></i></div>
            </div>
            <p class="stat-card-value"><?= $active ?></p>
        </div>
    </div>
    <div class="col-6 col-sm-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <p class="stat-card-label mb-0"><?= e(t('admin_announcements.stat_draft')) ?></p>
                <div class="stat-card-icon stat-icon-yellow"><i class="bi bi-file-earmark-text-fill"></i></div>
            </div>
            <p class="stat-card-value"><?= $draft ?></p>
        </div>
    </div>
    <div class="col-6 col-sm-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <p class="stat-card-label mb-0"><?= e(t('admin_ordinances.stat_repealed')) ?></p>
                <div class="stat-card-icon stat-icon-red"><i class="bi bi-x-circle-fill"></i></div>
            </div>
            <p class="stat-card-value"><?= $repealed ?></p>
        </div>
    </div>
</div>

<!-- Empty state -->
<?php if (empty($ordinances)): ?>
<div class="admin-card text-center py-5">
    <i class="bi bi-journal-text" style="font-size:2.5rem;color:#e4ece6;display:block;margin-bottom:.75rem;"></i>
    <p class="fw-semibold text-dark mb-1" style="font-size:.9rem;"><?= e(t('admin_ordinances.empty_title')) ?></p>
    <p class="text-muted mb-3" style="font-size:.82rem;"><?= e(t('admin_ordinances.empty_desc')) ?></p>
    <a href="<?= e(route('admin/ordinances/upload')) ?>" class="btn-barangay">
        <i class="bi bi-cloud-upload me-1"></i> <?= e(t('admin_ordinances.upload_btn')) ?>
    </a>
</div>

<!-- Table -->
<?php else: ?>
<div class="admin-card p-0">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="white-space:nowrap;"><?= e(t('admin_ordinances.col_ordinance_no')) ?></th>
                    <th><?= e(t('admin_announcements.col_title')) ?></th>
                    <th><?= e(t('admin_announcements.col_category')) ?></th>
                    <th><?= e(t('residents.col_status')) ?></th>
                    <th style="white-space:nowrap;"><?= e(t('admin_ordinances.col_enacted')) ?></th>
                    <th style="white-space:nowrap;"><?= e(t('admin_ordinances.col_uploaded')) ?></th>
                    <th style="width:80px;text-align:right;padding-right:16px;"><?= e(t('residents.col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php
            /*
             * Which ordinances are still leaning on the generated Manobo voice.
             * One query for the whole page rather than one per row — the
             * barangay's worklist for getting a real speaker to record them.
             */
            $__approxManobo = \App\Models\PostAudio::approximateManoboIndex('ordinance');
            ?>
            <?php foreach ($ordinances as $ord):
                $statusCls = match($ord['status']) {
                    'active'   => 'status-active',
                    'repealed' => 'status-repealed',
                    default    => 'status-draft',
                };
                $hasSummary = !empty($ord['ai_summary']);
                $hasPdf     = !empty($ord['file_url']);
            ?>
            <tr>
                <!-- Ordinance number -->
                <td style="white-space:nowrap;">
                    <span class="fw-semibold" style="font-size:.82rem;color:var(--brand-green);">
                        <?= e($ord['ordinance_no']) ?>
                    </span>
                </td>

                <!-- Title + description -->
                <td style="max-width:280px;">
                    <span class="fw-semibold d-block text-dark"
                          style="font-size:.875rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                          title="<?= e($ord['title']) ?>">
                        <?= e($ord['title']) ?>
                    </span>
                    <?php if (!empty($ord['description'])): ?>
                    <span class="text-muted d-block"
                          style="font-size:.75rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:260px;"
                          title="<?= e($ord['description']) ?>">
                        <?= e($ord['description']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($hasSummary): ?>
                    <span style="font-size:.7rem;color:var(--brand-green);font-weight:600;">
                        <i class="bi bi-stars"></i> <?= e(t('admin_ordinances.ai_summary_available')) ?>
                    </span>
                    <?php endif; ?>
                    <?php
                    /* Manobo text state, not just "has some". Missing is the
                       case staff need to see: that ordinance's MN Listen
                       button does nothing for residents. */
                    $__mnState = manobo_text_state($ord, 'description');
                    ?>
                    <span class="manobo-state manobo-state-<?= e($__mnState) ?>"
                          title="<?= e(t('manobo_state.' . $__mnState . '_help')) ?>">
                        🌿 <?= e(t('manobo_state.' . $__mnState)) ?>
                    </span>
                    <?php if (isset($__approxManobo['ordinance:' . $ord['id']])): ?>
                    <span class="voice-approx-flag"
                          title="<?= e(t('voice_reader.approx_flag_title')) ?>">
                        <i class="bi bi-robot"></i> <?= e(t('voice_reader.approx_flag')) ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($ord['audio_manobo_path'])): ?>
                    <span class="badge rounded-pill"
                          style="background:#e0f2fe;color:#075985;font-size:.6rem;font-weight:600;"
                          title="<?= e(t('admin_announcements.audio_badge')) ?>">
                        🎙 Audio
                    </span>
                    <?php endif; ?>

                    <?php
                    /* All three languages, each with its reason and its own
                       Translate now button. This column showed only the
                       Manobo state, so an ordinance with no English version
                       looked entirely healthy here. */
                    $lbRow       = $ord;
                    $lbType      = 'ordinance';
                    $lbBodyField = 'description';
                    $lbAttempts  = $ordAttempts[(int) $ord['id']] ?? [];
                    $lbRedirect  = '/admin/ordinances';
                    require __DIR__ . '/../../shared/_language-badges.php';
                    ?>
                </td>

                <!-- Category -->
                <td>
                    <?php if (!empty($ord['category'])): ?>
                    <span class="status-badge" style="background:#f3e8ff;color:#5b21b6;">
                        <?= e($ord['category']) ?>
                    </span>
                    <?php else: ?>
                    <span style="color:#cbd5e1;font-size:.82rem;">—</span>
                    <?php endif; ?>
                </td>

                <!-- Status -->
                <td>
                    <span class="status-badge <?= $statusCls ?>">
                        <?= ucfirst(e($ord['status'])) ?>
                    </span>
                </td>

                <!-- Enacted date -->
                <td class="text-muted" style="font-size:.82rem;white-space:nowrap;">
                    <?php if (!empty($ord['enacted_date'])): ?>
                        <?= e(date('M j, Y', strtotime($ord['enacted_date']))) ?>
                    <?php else: ?>
                        <span style="color:#cbd5e1;">—</span>
                    <?php endif; ?>
                </td>

                <!-- Upload date -->
                <td class="text-muted" style="font-size:.82rem;white-space:nowrap;">
                    <?= e(date('M j, Y', strtotime($ord['created_at']))) ?>
                </td>

                <!-- Actions -->
                <td style="text-align:right;padding-right:14px;">
                    <div class="btn-group-action">
                        <?php if ($hasPdf): ?>
                        <a href="<?= e(asset(ltrim($ord['file_url'], '/'))) ?>"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="btn-action"
                           title="<?= e(t('admin_ordinances.view_pdf_title')) ?>">
                            <i class="bi bi-eye-fill"></i>
                        </a>
                        <?php endif; ?>
                        <a href="<?= e(route('admin/ordinances/' . (int) $ord['id'] . '/edit')) ?>"
                           class="btn-action"
                           title="<?= e(t('admin_announcements.edit_title')) ?>">
                            <i class="bi bi-pencil-fill"></i>
                        </a>
                        <?php /* Opens the SMS page with this ordinance chosen.
                                 Nothing is sent from here. */ ?>
                        <a href="<?= e(route('admin/sms?post_type=ordinance&post_id=' . (int) $ord['id'])) ?>"
                           class="btn-action"
                           title="<?= e(t('sms_post.from_post_row')) ?>">
                            <i class="bi bi-chat-dots-fill"></i>
                        </a>
                        <form method="post"
                              action="<?= e(route('admin/ordinances/' . (int) $ord['id'] . '/delete')) ?>"
                              style="display:inline;"
                              onsubmit="return confirm(<?= e(json_encode(t('admin_ordinances.confirm_delete', ['title' => $ord['title']]))) ?>)">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <button type="submit" class="btn-action btn-action-danger" title="<?= e(t('admin_announcements.delete_title')) ?>">
                                <i class="bi bi-trash3-fill"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
// One detail panel for the whole page — the chips above open it.
$lbpRedirect = '/admin/ordinances';
require __DIR__ . '/../../shared/_language-badge-panel.php';
?>

<?php
$content   = ob_get_clean();
$pageTitle = $pageTitle ?? t('admin_ordinances.title');
require __DIR__ . '/../../layouts/admin.php';