<?php
/**
 * Staff / admin notifications — notifications only, in the back-office layout.
 *
 * The resident page (resident/notifications.php) brought the resident menu
 * with it — Home, Events, Announcements, Ordinances, Documents, Feedback —
 * so a staff member clicking the bell landed in the resident site. This page
 * stays inside the staff panel and links each item to its back-office screen
 * (see NotificationController::resolveUrl()).
 *
 * Variables: $notifications (list, each with 'url'), $pageTitle
 */
$notifications = $notifications ?? [];
$unread = count(array_filter($notifications, static fn (array $n): bool => (int) ($n['is_read'] ?? 0) === 0));
$icons = [
    'announcement' => 'bi-megaphone',
    'event'        => 'bi-calendar-event',
    'ordinance'    => 'bi-journal-text',
    'verification' => 'bi-person-check',
    'system'       => 'bi-bell',
];

ob_start();
?>
<div class="admin-card" x-data="staffNotifications()">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-0">Notifications</h2>
            <p class="text-muted small mb-0">
                <span x-text="unread"><?= (int) $unread ?></span> unread of <?= count($notifications) ?>
            </p>
        </div>
        <?php if ($unread > 0): ?>
        <button type="button" class="btn btn-sm btn-outline-success" @click="markAll()" x-show="unread > 0">
            <i class="bi bi-check2-all me-1" aria-hidden="true"></i>Mark all as read
        </button>
        <?php endif; ?>
    </div>

    <?php if ($notifications === []): ?>
    <div class="text-center text-muted py-5">
        <i class="bi bi-bell-slash fs-1 d-block mb-2" aria-hidden="true"></i>
        You have no notifications.
    </div>
    <?php else: ?>
    <ul class="list-group list-group-flush">
        <?php foreach ($notifications as $n):
            $id     = (int) $n['id'];
            $isNew  = (int) ($n['is_read'] ?? 0) === 0;
            $icon   = $icons[(string) ($n['type'] ?? 'system')] ?? 'bi-bell';
            $url    = (string) ($n['url'] ?? route('notifications'));
            $plain  = trim(strip_tags((string) ($n['message'] ?? ''))); ?>
        <li class="list-group-item px-0">
            <a href="<?= e($url) ?>" class="d-flex gap-3 text-decoration-none text-reset"
               @click.prevent="open(<?= $id ?>, <?= e(json_encode($url)) ?>)">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
                      style="width:2.5rem;height:2.5rem;background:var(--bs-success-bg-subtle, #e8f5ee);color:#15803d;">
                    <i class="bi <?= e($icon) ?>" aria-hidden="true"></i>
                </span>
                <span class="flex-grow-1 min-w-0">
                    <span class="d-flex align-items-center gap-2">
                        <strong class="text-truncate"><?= e((string) ($n['title'] ?? '')) ?></strong>
                        <?php if ($isNew): ?>
                        <span class="badge bg-success" x-show="!read.includes(<?= $id ?>)">New</span>
                        <?php endif; ?>
                    </span>
                    <?php if ($plain !== ''): ?>
                    <span class="d-block small text-muted text-truncate"><?= e($plain) ?></span>
                    <?php endif; ?>
                    <span class="d-block small text-muted"><?= e(relative_time((string) ($n['created_at'] ?? ''))) ?></span>
                </span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<script>
function staffNotifications() {
    return {
        unread: <?= (int) $unread ?>,
        read: [],
        base: <?= json_encode(rtrim(base_url(), '/')) ?>,
        csrf: <?= json_encode(csrf_token()) ?>,
        post(path) {
            const fd = new FormData();
            fd.append('csrf_token', this.csrf);
            return fetch(this.base + path, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(() => {});
        },
        open(id, url) {
            this.read.push(id);
            this.post('/api/notifications/' + id + '/read').finally(() => { window.location.href = url; });
        },
        markAll() {
            this.post('/api/notifications/mark-read');
            this.unread = 0;
            document.querySelectorAll('.badge.bg-success').forEach(b => b.remove());
            const badge = document.querySelector('.topbar-badge');
            if (badge) { badge.style.display = 'none'; }
        },
    };
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/admin.php';
