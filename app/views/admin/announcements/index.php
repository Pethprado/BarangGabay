<?php
$announcements = $announcements ?? [];
// The table shows $announcements, which may be filtered by ?status=. The
// counts below are deliberately taken from the UNFILTERED list: narrowing to
// drafts must not make this page announce that there are no published posts.
$allAnnouncements = $allAnnouncements ?? $announcements;
$status           = $status ?? '';

$total = count($allAnnouncements);

// "Published" means residents can read it right now. A scheduled post shares
// the `published` status but not that property, so it is counted separately —
// otherwise the stat card would claim an audience the post does not have yet.
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

ob_start();
?>

<!-- Page header -->
<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <p class="mb-0" style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8;"><?= e(t('admin_announcements.eyebrow')) ?></p>
        <h1 class="mb-0 mt-1" style="font-size:1.55rem;font-weight:800;color:var(--text-primary);line-height:1.1;"><?= e(t('admin_announcements.title')) ?></h1>
        <p class="text-muted mt-1 mb-0" style="font-size:.82rem;">
            <?= e(t('admin_announcements.summary', ['total' => $total, 'published' => $published, 'draft' => $draft])) ?><?php
// Scheduled posts belong to none of the three counts above — they are not
// live, not drafts, and not archived — so without this the totals visibly
// fail to add up ("1 total · 0 published · 0 draft").
if ($scheduled > 0): ?> &middot; <?= $scheduled ?> <?= e(strtolower(t('admin_announcements.stat_scheduled'))) ?><?php endif; ?>
        </p>
    </div>
    <a href="<?= e(route('admin/announcements/create')) ?>" class="btn-barangay">
        <i class="bi bi-plus-lg"></i> <?= e(t('admin_announcements.create_new')) ?>
    </a>
</div>

<!-- Stat cards — also the filter control for the table below.
     Arriving from the dashboard lands here with one already active, so the
     same cards that got you here are how you change or clear the view.
     Clicking the active one again returns to the full list. -->
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
    <?php /* Only a card while there is something in it. Scheduled posts are a
             real, separately filterable state — published status, no audience
             yet — but a permanent "0 scheduled" card would be clutter. */ ?>
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

<?php if ($status !== ''): ?>
<!-- Says which subset is on screen and how to leave it. Without this, a page
     showing three of eleven rows looks like the other eight were deleted. -->
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

<!-- Empty state. An empty FILTER is a different situation from an empty
     system: offering "create your first announcement" to someone who just
     clicked "Archived" would be answering a question they did not ask. -->
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
        <i class="bi bi-plus-lg"></i> <?= e(t('admin_announcements.create_btn')) ?>
    </a>
    <?php endif; ?>
</div>

<!-- Table -->
<?php else: ?>
<div class="admin-card p-0 fade-up">
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
                    <th style="width:130px;text-align:right;padding-right:16px;"><?= e(t('residents.col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php
            /*
             * Which announcements are still leaning on the generated Manobo
             * voice. One query for the whole page rather than one per row.
             * This is the barangay's worklist: every flagged post is a notice
             * a Manobo speaker could improve by recording it properly, and
             * without surfacing it the approximation quietly becomes permanent.
             */
            $__approxManobo = \App\Models\PostAudio::approximateManoboIndex('announcement');
            ?>
            <?php foreach ($announcements as $ann):
                [$catLabel, $catCss] = $categoryMeta[$ann['category']] ?? [ucfirst($ann['category']), 'background:#f1f5f9;color:#475569;'];
                [$urgLabel, $urgCss] = $urgencyMeta[$ann['urgency']]   ?? [ucfirst($ann['urgency']),  'background:#f1f5f9;color:#475569;'];
                // A scheduled post is stored as status=published, so the raw
                // status column would label it "Published" while residents
                // still cannot see it — the single most confusing thing this
                // list could tell staff. Split the two apart for display.
                $annScheduled = \App\Models\Announcement::isScheduled($ann);
                $statusCls = match(true) {
                    $annScheduled                  => 'status-draft',
                    $ann['status'] === 'published' => 'status-published',
                    $ann['status'] === 'archived'  => 'status-archived',
                    default                        => 'status-draft',
                };
            ?>
            <tr>
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
                    /* Manobo text state, not just "has some". Missing is the
                       case staff need to see: that post's MN Listen button
                       does nothing for residents. */
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
                    <!-- The English translation is held back. Linked, so the
                         reviewer is one click from clearing it rather than
                         having to go looking for the queue. -->
                    <a href="<?= e(route('admin/translations/' . (int) $ann['id'])) ?>"
                       class="status-badge d-inline-flex align-items-center gap-1 mt-1"
                       style="background:#fef3c7;color:#92400e;text-decoration:none;white-space:nowrap;"
                       title="<?= e(t('translation_review.awaiting_badge')) ?>">
                        <i class="bi bi-translate"></i><?= e(t('translation_review.awaiting_badge')) ?>
                    </a>
                    <?php endif; ?>
                </td>

                <!-- Which languages a resident can actually read this in.
                     Shown because it is routinely incomplete without anything
                     being broken: the free translation service has a daily
                     allowance, so a post saved after it runs out is saved
                     correctly with nothing attached. Silence there reads as
                     success, and staff only found out when a resident told
                     them. Manobo is usually ✗ — it needs either Anthropic
                     credits or hand-typed fields. -->
                <td style="white-space:nowrap;">
                    <?php
                    /* The chips used to say ✓ or ✗ and stop there, which
                       left a red MN meaning any of four different problems
                       with four different fixes. They now carry the reason
                       and a per-language Translate now button — see
                       _language-badges.php. */
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

                <!-- Published date (or the moment it is queued for) -->
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
                        <a href="<?= e(route('admin/announcements/' . (int) $ann['id'] . '/edit')) ?>"
                           class="btn-action"
                           title="<?= e(t('admin_announcements.edit_title')) ?>">
                            <i class="bi bi-pencil-fill"></i>
                        </a>
                        <?php /* Straight to the SMS page with this post already
                                 chosen — the preview and the cost are there, so
                                 nothing is sent by pressing this. */ ?>
                        <a href="<?= e(route('admin/sms?post_type=announcement&post_id=' . (int) $ann['id'])) ?>"
                           class="btn-action"
                           title="<?= e(t('sms_post.from_post_row')) ?>">
                            <i class="bi bi-chat-dots-fill"></i>
                        </a>

                        <!-- Retry the machine translation. Its own action
                             rather than a re-save: re-saving the post would
                             notify every resident again just to try a
                             translation that failed because of a daily quota. -->
                        <form method="post"
                              action="<?= e(route('admin/announcements/' . (int) $ann['id'] . '/translate')) ?>"
                              style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <button type="submit" class="btn-action"
                                    title="<?= e(t('admin_announcements.translate_title')) ?>">
                                <i class="bi bi-translate"></i>
                            </button>
                        </form>
                        <form method="post"
                              action="<?= e(route('admin/announcements/' . (int) $ann['id'] . '/delete')) ?>"
                              style="display:inline;"
                              onsubmit="return confirm(<?= e(json_encode(t('admin_announcements.confirm_delete', ['title' => $ann['title']]))) ?>)">
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
/* One detail panel for the whole page — the language chips above open it.
   Per-chip popovers would be sixty copies of this on a page of twenty
   posts, and would clip inside the scrolling table. */
$lbpRedirect = '/admin/announcements';
require __DIR__ . '/../../shared/_language-badge-panel.php';
?>

<?php
$content   = ob_get_clean();
$pageTitle = t('admin_announcements.title');
require __DIR__ . '/../../layouts/admin.php';