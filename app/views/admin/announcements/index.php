<?php
$announcements = $announcements ?? [];
// The list shows $announcements, which may be filtered by ?status=. The
// counts below are deliberately taken from the UNFILTERED list.
$allAnnouncements = $allAnnouncements ?? $announcements;
$status           = $status ?? '';

$total = count($allAnnouncements);

$scheduled = count(array_filter($allAnnouncements, static fn($a) => \App\Models\Announcement::isScheduled($a)));
$published = count(array_filter($allAnnouncements, static fn($a) => \App\Models\Announcement::isVisibleNow($a)));
$draft     = count(array_filter($allAnnouncements, static fn($a) => $a['status'] === 'draft'));
$archived  = count(array_filter($allAnnouncements, static fn($a) => $a['status'] === 'archived'));

/** A card's URL: clicking the active one again clears the filter. */
$filterUrl = static function (string $want) use ($status): string {
    return route('admin/announcements') . ($status === $want ? '' : '?status=' . $want);
};

$categoryMeta = [
    'general'        => ['General',        'background:#e0f2fe;color:#0369a1;'],
    'health'         => ['Health',          'background:#ccfbf1;color:#065f46;'],
    'safety'         => ['Safety',          'background:#fef9c3;color:#92400e;'],
    'government'     => ['Government',      'background:#d4edda;color:#155724;'],
    'infrastructure' => ['Infrastructure',  'background:#f3e8ff;color:#5b21b6;'],
    'social'         => ['Social',          'background:#ffe4e6;color:#9f1239;'],
];
$urgencyMeta = [
    'normal'    => ['Normal',    'background:#f1f5f9;color:#475569;'],
    'important' => ['Important', 'background:#fff3cd;color:#856404;'],
    'urgent'    => ['Urgent',    'background:#f8d7da;color:#721c24;'],
];

$userRole = $_SESSION['role'] ?? 'staff';
$canDelete = in_array($userRole, ['admin', 'superadmin'], true);

ob_start();
?>

<!-- Page header -->
<div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="mb-0" style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8;">CONTENT MANAGEMENT</p>
        <h1 class="mb-0 mt-1" style="font-size:1.65rem;font-weight:800;color:var(--text-primary);line-height:1.1;"><?= e(t('admin_announcements.title')) ?></h1>
        <p class="text-muted mt-1 mb-0" style="font-size:.85rem;">
            Manage barangay announcements and public notices.
        </p>
    </div>
    <div>
        <a href="<?= e(route('admin/announcements/create')) ?>" class="btn-barangay d-inline-flex align-items-center gap-2 px-3 py-2 fw-semibold" style="box-shadow: 0 4px 12px rgba(26,107,58,0.2);">
            <i class="bi bi-plus-lg"></i> Create Announcement
        </a>
    </div>
</div>

<!-- Summary & Filter Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-sm-3 fade-up">
        <a class="stat-card stat-card-link<?= $status === '' ? ' stat-card-selected' : '' ?>"
           href="<?= e(route('admin/announcements')) ?>"
           <?= $status === '' ? 'aria-current="true"' : '' ?>>
            <div class="d-flex align-items-center justify-content-between mb-2">
                <p class="stat-card-label mb-0"><?= e(t('admin_announcements.stat_total')) ?></p>
                <div class="stat-card-icon stat-icon-blue"><i class="bi bi-megaphone-fill"></i></div>
            </div>
            <p class="stat-card-value"><span data-countup="<?= $total ?>"><?= $total ?></span></p>
        </a>
    </div>
    <div class="col-6 col-sm-3 fade-up fade-up-delay-1">
        <a class="stat-card stat-card-link<?= $status === 'published' ? ' stat-card-selected' : '' ?>"
           href="<?= e($filterUrl('published')) ?>"
           <?= $status === 'published' ? 'aria-current="true"' : '' ?>>
            <div class="d-flex align-items-center justify-content-between mb-2">
                <p class="stat-card-label mb-0"><?= e(t('admin_announcements.stat_published')) ?></p>
                <div class="stat-card-icon stat-icon-green"><i class="bi bi-send-check-fill"></i></div>
            </div>
            <p class="stat-card-value"><span data-countup="<?= $published ?>"><?= $published ?></span></p>
        </a>
    </div>
    <?php if ($scheduled > 0): ?>
    <div class="col-6 col-sm-3 fade-up fade-up-delay-2">
        <a class="stat-card stat-card-link<?= $status === 'scheduled' ? ' stat-card-selected' : '' ?>"
           href="<?= e($filterUrl('scheduled')) ?>"
           <?= $status === 'scheduled' ? 'aria-current="true"' : '' ?>>
            <div class="d-flex align-items-center justify-content-between mb-2">
                <p class="stat-card-label mb-0"><?= e(t('admin_announcements.stat_scheduled')) ?></p>
                <div class="stat-card-icon stat-icon-blue"><i class="bi bi-clock-fill"></i></div>
            </div>
            <p class="stat-card-value"><span data-countup="<?= $scheduled ?>"><?= $scheduled ?></span></p>
        </a>
    </div>
    <?php endif; ?>
    <div class="col-6 col-sm-3 fade-up fade-up-delay-2">
        <a class="stat-card stat-card-link<?= $status === 'draft' ? ' stat-card-selected' : '' ?>"
           href="<?= e($filterUrl('draft')) ?>"
           <?= $status === 'draft' ? 'aria-current="true"' : '' ?>>
            <div class="d-flex align-items-center justify-content-between mb-2">
                <p class="stat-card-label mb-0"><?= e(t('admin_announcements.stat_draft')) ?></p>
                <div class="stat-card-icon stat-icon-yellow"><i class="bi bi-file-earmark-text-fill"></i></div>
            </div>
            <p class="stat-card-value"><span data-countup="<?= $draft ?>"><?= $draft ?></span></p>
        </a>
    </div>
    <div class="col-6 col-sm-3 fade-up fade-up-delay-3">
        <a class="stat-card stat-card-link<?= $status === 'archived' ? ' stat-card-selected' : '' ?>"
           href="<?= e($filterUrl('archived')) ?>"
           <?= $status === 'archived' ? 'aria-current="true"' : '' ?>>
            <div class="d-flex align-items-center justify-content-between mb-2">
                <p class="stat-card-label mb-0"><?= e(t('admin_announcements.stat_archived')) ?></p>
                <div class="stat-card-icon stat-icon-red"><i class="bi bi-archive-fill"></i></div>
            </div>
            <p class="stat-card-value"><span data-countup="<?= $archived ?>"><?= $archived ?></span></p>
        </a>
    </div>
</div>

<!-- Toolbar: Search Bar & View Mode Toggle -->
<div class="admin-card p-3 mb-4">
    <div class="row g-3 align-items-center">
        <div class="col-md-6 col-lg-5">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: .5rem 0 0 .5rem;">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text"
                       id="announcementSearchInput"
                       class="form-control border-start-0 ps-0"
                       placeholder="Search announcements..."
                       style="border-radius: 0 .5rem .5rem 0; font-size:.875rem;"
                       onkeyup="filterAnnouncements()">
            </div>
        </div>
        <div class="col-md-6 col-lg-7 d-flex justify-content-md-end align-items-center gap-2">
            <div class="btn-group" role="group" aria-label="View toggle">
                <button type="button" class="btn btn-outline-secondary btn-sm active" id="btnViewTable" onclick="toggleView('table')">
                    <i class="bi bi-list-ul me-1"></i> Table View
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnViewCards" onclick="toggleView('cards')">
                    <i class="bi bi-grid-fill me-1"></i> Cards View
                </button>
            </div>
        </div>
    </div>
</div>

<?php if ($status !== ''): ?>
<div class="d-flex align-items-center flex-wrap gap-2 mb-3">
    <span class="filter-pill">
        <i class="bi bi-funnel-fill" aria-hidden="true"></i>
        <?= e(t('admin_announcements.filter_showing', [
            'count'  => count($announcements),
            'status' => t('admin_announcements.stat_' . $status),
        ])) ?>
    </span>
    <a href="<?= e(route('admin/announcements')) ?>" class="filter-clear">
        <i class="bi bi-x-lg" aria-hidden="true"></i> <?= e(t('admin_announcements.filter_clear')) ?>
    </a>
</div>
<?php endif; ?>

<!-- Empty state -->
<?php if (empty($announcements)): ?>
<div class="admin-card text-center py-5">
    <?php if ($status !== ''): ?>
    <i class="bi bi-funnel" style="font-size:2.5rem;color:#e4ece6;display:block;margin-bottom:.75rem;"></i>
    <p class="fw-semibold text-dark mb-1" style="font-size:.9rem;">
        <?= e(t('admin_announcements.empty_filtered', [
            'status' => t('admin_announcements.stat_' . $status),
        ])) ?>
    </p>
    <p class="text-muted mb-3" style="font-size:.82rem;"><?= e(t('admin_announcements.empty_filtered_desc')) ?></p>
    <a href="<?= e(route('admin/announcements')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> <?= e(t('admin_announcements.filter_clear')) ?>
    </a>
    <?php else: ?>
    <i class="bi bi-megaphone" style="font-size:2.5rem;color:#e4ece6;display:block;margin-bottom:.75rem;"></i>
    <p class="fw-semibold text-dark mb-1" style="font-size:.9rem;"><?= e(t('admin_announcements.empty_title')) ?></p>
    <p class="text-muted mb-3" style="font-size:.82rem;"><?= e(t('admin_announcements.empty_desc')) ?></p>
    <a href="<?= e(route('admin/announcements/create')) ?>" class="btn-barangay">
        <i class="bi bi-plus-lg"></i> Create Announcement
    </a>
    <?php endif; ?>
</div>

<?php else: ?>

<?php
$__approxManobo = \App\Models\PostAudio::approximateManoboIndex('announcement');
?>

<!-- Table View -->
<div id="announcementTableView" class="admin-card p-0 fade-up">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width:58px;padding-left:14px;"><?= e(t('admin_announcements.col_cover')) ?></th>
                    <th><?= e(t('admin_announcements.col_title')) ?></th>
                    <th><?= e(t('admin_announcements.col_category')) ?></th>
                    <th><?= e(t('admin_announcements.col_urgency')) ?></th>
                    <th style="white-space:nowrap;"><?= e(t('admin_announcements.col_languages')) ?></th>
                    <th><?= e(t('residents.col_status')) ?></th>
                    <th style="white-space:nowrap;"><?= e(t('admin_announcements.col_published')) ?></th>
                    <th style="width:140px;text-align:right;padding-right:16px;"><?= e(t('residents.col_actions')) ?></th>
                </tr>
            </thead>
            <tbody id="announcementTableBody">
            <?php foreach ($announcements as $ann):
                [$catLabel, $catCss] = $categoryMeta[$ann['category']] ?? [ucfirst($ann['category']), 'background:#f1f5f9;color:#475569;'];
                [$urgLabel, $urgCss] = $urgencyMeta[$ann['urgency']]   ?? [ucfirst($ann['urgency']),  'background:#f1f5f9;color:#475569;'];
                $annScheduled = \App\Models\Announcement::isScheduled($ann);
                $statusCls = match(true) {
                    $annScheduled                  => 'status-draft',
                    $ann['status'] === 'published' => 'status-published',
                    $ann['status'] === 'archived'  => 'status-archived',
                    default                        => 'status-draft',
                };
                $searchContent = strtolower($ann['title'] . ' ' . strip_tags($ann['body'] ?? '') . ' ' . $ann['category']);
            ?>
            <tr class="announcement-item-row" data-search="<?= e($searchContent) ?>">
                <!-- Cover thumbnail -->
                <td style="padding:8px 8px 8px 14px;">
                    <?php if (!empty($ann['cover_image_url'])): ?>
                    <img src="<?= e(asset($ann['cover_image_url'])) ?>"
                          alt=""
                          style="width:46px;height:34px;object-fit:cover;border-radius:5px;border:1px solid #e4ece6;display:block;">
                    <?php else: ?>
                    <div style="width:46px;height:34px;border-radius:5px;background:#f1f5f2;border:1px solid #e4ece6;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi bi-image" style="font-size:.72rem;color:#c8d5cc;"></i>
                    </div>
                    <?php endif; ?>
                </td>

                <!-- Title + meta -->
                <td style="max-width:260px;">
                    <a href="<?= e(route('admin/announcements/' . (int) $ann['id'] . '/edit')) ?>"
                       class="fw-semibold text-dark text-decoration-none d-block"
                       style="font-size:.875rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:250px;"
                       title="<?= e($ann['title']) ?>">
                        <?= e($ann['title']) ?>
                    </a>
                    <span class="text-muted" style="font-size:.75rem;">
                        ID #<?= (int) $ann['id'] ?> &middot; <?= e(date('M j, Y', strtotime($ann['created_at']))) ?>
                    </span>
                    <?php
                    $__mnState = manobo_text_state($ann, 'body');
                    ?>
                    <span class="manobo-state manobo-state-<?= e($__mnState) ?> ms-1"
                          title="<?= e(t('manobo_state.' . $__mnState . '_help')) ?>">
                        🌿 <?= e(t('manobo_state.' . $__mnState)) ?>
                    </span>
                    <?php if (!empty($ann['audio_manobo_path'])): ?>
                    <span class="badge rounded-pill"
                          style="background:#e0f2fe;color:#075985;font-size:.65rem;font-weight:600;"
                          title="<?= e(t('admin_announcements.audio_badge')) ?>">
                        🎙 Audio
                    </span>
                    <?php endif; ?>
                    <?php if (isset($__approxManobo['announcement:' . $ann['id']])): ?>
                    <span class="voice-approx-flag"
                          title="<?= e(t('voice_reader.approx_flag_title')) ?>">
                        <i class="bi bi-robot"></i> <?= e(t('voice_reader.approx_flag')) ?>
                    </span>
                    <?php endif; ?>
                </td>

                <!-- Category -->
                <td>
                    <span class="status-badge" style="<?= $catCss ?>"><?= e($catLabel) ?></span>
                </td>

                <!-- Urgency -->
                <td>
                    <span class="status-badge <?= $ann['urgency'] === 'urgent' ? 'badge-urgent-pulse' : '' ?>" style="<?= $urgCss ?>"><?= e($urgLabel) ?></span>
                    <?php if (($ann['en_review_state'] ?? 'none') === 'pending'): ?>
                    <a href="<?= e(route('admin/translations/' . (int) $ann['id'])) ?>"
                       class="status-badge d-inline-flex align-items-center gap-1 mt-1"
                       style="background:#fef3c7;color:#92400e;text-decoration:none;white-space:nowrap;"
                       title="<?= e(t('translation_review.awaiting_badge')) ?>">
                        <i class="bi bi-translate"></i><?= e(t('translation_review.awaiting_badge')) ?>
                    </a>
                    <?php endif; ?>
                </td>

                <!-- Languages -->
                <td style="white-space:nowrap;">
                    <?php
                    $lbRow       = $ann;
                    $lbType      = 'announcement';
                    $lbBodyField = 'body';
                    $lbAttempts  = $annAttempts[(int) $ann['id']] ?? [];
                    $lbRedirect  = '/admin/announcements';
                    require __DIR__ . '/../../shared/_language-badges.php';
                    ?>
                </td>

                <!-- Status -->
                <td>
                    <?php if ($annScheduled): ?>
                    <span class="status-badge d-inline-flex align-items-center gap-1"
                          style="background:#e0e7ff;color:#3730a3;white-space:nowrap;"
                          title="<?= e(t('admin_announcements.scheduled_hint')) ?>">
                        <i class="bi bi-clock"></i><?= e(t('admin_announcements.badge_scheduled')) ?>
                    </span>
                    <?php else: ?>
                    <span class="status-badge <?= $statusCls ?>">
                        <?= ucfirst(e($ann['status'])) ?>
                    </span>
                    <?php endif; ?>
                </td>

                <!-- Published date -->
                <td class="text-muted" style="font-size:.82rem;white-space:nowrap;">
                    <?php if ($annScheduled): ?>
                        <span style="color:#4338ca;font-weight:600;">
                            <?= e(format_datetime($ann['published_at'])) ?>
                        </span>
                    <?php elseif (!empty($ann['published_at'])): ?>
                        <?= e(date('M j, Y', strtotime($ann['published_at']))) ?>
                    <?php else: ?>
                        <span style="color:#cbd5e1;">—</span>
                    <?php endif; ?>
                </td>

                <!-- Actions -->
                <td style="text-align:right;padding-right:14px;">
                    <div class="btn-group-action">
                        <a href="<?= e(route('announcements/' . $ann['slug'])) ?>"
                           target="_blank"
                           class="btn-action"
                           title="View on site">
                            <i class="bi bi-eye-fill"></i>
                        </a>
                        <a href="<?= e(route('admin/announcements/' . (int) $ann['id'] . '/edit')) ?>"
                           class="btn-action"
                           title="<?= e(t('admin_announcements.edit_title')) ?>">
                            <i class="bi bi-pencil-fill"></i>
                        </a>
                        <a href="<?= e(route('admin/sms?post_type=announcement&post_id=' . (int) $ann['id'])) ?>"
                           class="btn-action"
                           title="<?= e(t('sms_post.from_post_row')) ?>">
                            <i class="bi bi-chat-dots-fill"></i>
                        </a>
                        <?php if ($canDelete): ?>
                        <button type="button"
                                class="btn-action btn-action-danger"
                                onclick="openDeleteAnnouncementModal(<?= (int) $ann['id'] ?>, '<?= e(addslashes($ann['title'])) ?>')"
                                title="<?= e(t('admin_announcements.delete_title')) ?>">
                            <i class="bi bi-trash3-fill"></i>
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

<!-- Cards View (Hidden by default, toggled via JS) -->
<div id="announcementCardsView" class="row g-3 d-none">
    <?php foreach ($announcements as $ann):
        [$catLabel, $catCss] = $categoryMeta[$ann['category']] ?? [ucfirst($ann['category']), 'background:#f1f5f9;color:#475569;'];
        [$urgLabel, $urgCss] = $urgencyMeta[$ann['urgency']]   ?? [ucfirst($ann['urgency']),  'background:#f1f5f9;color:#475569;'];
        $annScheduled = \App\Models\Announcement::isScheduled($ann);
        $statusCls = match(true) {
            $annScheduled                  => 'status-draft',
            $ann['status'] === 'published' => 'status-published',
            $ann['status'] === 'archived'  => 'status-archived',
            default                        => 'status-draft',
        };
        $excerpt = strip_tags(html_entity_decode($ann['body'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $excerpt = mb_strlen($excerpt) > 130 ? mb_substr($excerpt, 0, 130) . '…' : $excerpt;
        $searchContent = strtolower($ann['title'] . ' ' . $excerpt . ' ' . $ann['category']);
    ?>
    <div class="col-md-6 col-lg-4 announcement-item-card" data-search="<?= e($searchContent) ?>">
        <div class="admin-card h-100 d-flex flex-column p-0 overflow-hidden">
            <!-- Card Image -->
            <div style="height: 140px; background: #f8fafc; position: relative; overflow: hidden;" class="border-bottom">
                <?php if (!empty($ann['cover_image_url'])): ?>
                <img src="<?= e(asset($ann['cover_image_url'])) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                <div class="d-flex align-items-center justify-content-center h-100 text-muted opacity-50">
                    <i class="bi bi-megaphone" style="font-size: 2.5rem;"></i>
                </div>
                <?php endif; ?>
                <div style="position: absolute; top: 10px; right: 10px;" class="d-flex gap-1">
                    <span class="status-badge" style="<?= $catCss ?>"><?= e($catLabel) ?></span>
                    <span class="status-badge <?= $ann['urgency'] === 'urgent' ? 'badge-urgent-pulse' : '' ?>" style="<?= $urgCss ?>"><?= e($urgLabel) ?></span>
                </div>
            </div>

            <!-- Card Body -->
            <div class="p-3 d-flex flex-column flex-grow-1">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted" style="font-size: .75rem;">
                        ID #<?= (int) $ann['id'] ?> &middot; <?= e(date('M j, Y', strtotime($ann['created_at']))) ?>
                    </span>
                    <?php if ($annScheduled): ?>
                    <span class="status-badge" style="background:#e0e7ff;color:#3730a3;"><i class="bi bi-clock"></i> Scheduled</span>
                    <?php else: ?>
                    <span class="status-badge <?= $statusCls ?>"><?= ucfirst(e($ann['status'])) ?></span>
                    <?php endif; ?>
                </div>

                <h5 class="fw-bold text-dark mb-2 style-title" style="font-size: 1rem; line-height: 1.3;">
                    <a href="<?= e(route('admin/announcements/' . (int) $ann['id'] . '/edit')) ?>" class="text-dark text-decoration-none">
                        <?= e($ann['title']) ?>
                    </a>
                </h5>

                <p class="text-muted small mb-3 flex-grow-1" style="line-height: 1.5; font-size: .82rem;">
                    <?= e($excerpt) ?>
                </p>

                <!-- Footer / Action Buttons -->
                <div class="pt-2 border-top d-flex align-items-center justify-content-between mt-auto">
                    <a href="<?= e(route('announcements/' . $ann['slug'])) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-eye"></i> View
                    </a>
                    <div class="d-flex gap-1">
                        <a href="<?= e(route('admin/announcements/' . (int) $ann['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <?php if ($canDelete): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="openDeleteAnnouncementModal(<?= (int) $ann['id'] ?>, '<?= e(addslashes($ann['title'])) ?>')">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteAnnouncementModal" tabindex="-1" aria-labelledby="deleteAnnouncementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 1rem; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2" id="deleteAnnouncementModalLabel">
                    <i class="bi bi-exclamation-triangle-fill"></i> Delete Announcement?
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <p class="text-muted mb-0" style="font-size: .92rem;">
                    Are you sure you want to delete <strong id="deleteTargetTitle" class="text-dark">this announcement</strong>? This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-3 px-3" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteAnnouncementForm" method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button type="submit" class="btn btn-danger rounded-3 px-4 fw-semibold">Delete Announcement</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$lbpRedirect = '/admin/announcements';
require __DIR__ . '/../../shared/_language-badge-panel.php';
?>

<script>
function filterAnnouncements() {
    const query = document.getElementById('announcementSearchInput').value.toLowerCase().trim();
    
    // Filter Table rows
    const rows = document.querySelectorAll('.announcement-item-row');
    rows.forEach(row => {
        const text = row.getAttribute('data-search') || '';
        if (text.includes(query)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });

    // Filter Cards
    const cards = document.querySelectorAll('.announcement-item-card');
    cards.forEach(card => {
        const text = card.getAttribute('data-search') || '';
        if (text.includes(query)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}

function toggleView(mode) {
    const tableView = document.getElementById('announcementTableView');
    const cardsView = document.getElementById('announcementCardsView');
    const btnTable = document.getElementById('btnViewTable');
    const btnCards = document.getElementById('btnViewCards');

    if (mode === 'cards') {
        if (tableView) tableView.classList.add('d-none');
        if (cardsView) cardsView.classList.remove('d-none');
        btnCards.classList.add('active');
        btnTable.classList.remove('active');
    } else {
        if (cardsView) cardsView.classList.add('d-none');
        if (tableView) tableView.classList.remove('d-none');
        btnTable.classList.add('active');
        btnCards.classList.remove('active');
    }
}

function openDeleteAnnouncementModal(id, title) {
    document.getElementById('deleteTargetTitle').innerText = title;
    const form = document.getElementById('deleteAnnouncementForm');
    form.action = "<?= e(route('admin/announcements/')) ?>" + id + "/delete";
    const modal = new bootstrap.Modal(document.getElementById('deleteAnnouncementModal'));
    modal.show();
}
</script>

<?php
$content   = ob_get_clean();
$pageTitle = t('admin_announcements.title');
require __DIR__ . '/../../layouts/admin.php';