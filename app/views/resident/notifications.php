<?php
/**
 * Resident notification list.
 * Variables: $notifications (array) — each item has a 'url' field resolved by the controller.
 *
 * IMPORTANT: Alpine data values (CSRF token, base URL) must live in a <script>
 * function — NOT inlined into x-data="...".  json_encode() wraps strings in
 * double-quotes; placing those inside a double-quoted HTML attribute terminates
 * the attribute early and leaks the remaining JS as visible page text.
 */
$notifications = $notifications ?? [];

// IDs that were unread on page load — used to seed the reactive unread counter.
$unreadIds   = array_values(array_map(
    'intval',
    array_column(array_filter($notifications, fn($n) => !(int)$n['is_read']), 'id')
));
$unreadTotal = count($unreadIds);
$hasAny      = !empty($notifications);

/* ── Type → icon / colour config ── */
$typeConf = [
    'announcement' => ['icon' => 'bi-megaphone-fill',     'bg' => '#dcfce7', 'color' => '#15803d'],
    'event'        => ['icon' => 'bi-calendar-event-fill', 'bg' => '#dbeafe', 'color' => '#1d4ed8'],
    'ordinance'    => ['icon' => 'bi-journal-text',        'bg' => '#fef3c7', 'color' => '#92400e'],
    'verification' => ['icon' => 'bi-person-check-fill',   'bg' => '#d4edda', 'color' => '#155724'],
    'system'       => ['icon' => 'bi-info-circle-fill',    'bg' => '#f1f5f9', 'color' => '#475569'],
];

/* ── Time-ago, in the active locale ── */
$timeAgo = static function (string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff <    120) return t('res_notifications.ago_just_now');
    if ($diff <   3600) return t('res_notifications.ago_minutes', ['n' => (int) floor($diff / 60)]);
    if ($diff <  86400) return t('res_notifications.ago_hours',   ['n' => (int) floor($diff / 3600)]);
    if ($diff < 604800) return t('res_notifications.ago_days',    ['n' => (int) floor($diff / 86400)]);
    return date('M j, Y', strtotime($datetime));
};

/* ── Group by date bucket ── */
$today     = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('yesterday'));
$weekStart = date('Y-m-d', strtotime('-7 days'));

// Keyed by a STABLE internal name, with the display label resolved separately —
// previously the Tagalog label was the array key itself, so it could never be
// translated without also changing the grouping logic.
$groups = ['today' => [], 'yesterday' => [], 'week' => [], 'earlier' => []];
foreach ($notifications as $n) {
    $d = substr($n['created_at'], 0, 10);
    if ($d === $today)         $groups['today'][]     = $n;
    elseif ($d === $yesterday) $groups['yesterday'][] = $n;
    elseif ($d >= $weekStart)  $groups['week'][]      = $n;
    else                       $groups['earlier'][]   = $n;
}
$groupLabels = [
    'today'     => t('res_notifications.group_today'),
    'yesterday' => t('res_notifications.group_yesterday'),
    'week'      => t('res_notifications.group_week'),
    'earlier'   => t('res_notifications.group_earlier'),
];

// Flags for JSON_* constants — escapes all HTML-unsafe chars for <script> embedding
$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

$allIdsJson    = json_encode(array_values(array_map('intval', array_column($notifications, 'id'))), $jsonFlags);
$unreadIdsJson = json_encode($unreadIds, $jsonFlags);
$csrfJson      = json_encode(csrf_token(),             $jsonFlags);
$baseJson      = json_encode(rtrim(base_url(), '/'),   $jsonFlags);

ob_start();
?>

<style>
.notif-row {
    display:flex; align-items:flex-start; gap:13px;
    padding:14px 16px; border-radius:12px;
    cursor:pointer; transition:background .15s, box-shadow .15s;
    position:relative; border:1px solid transparent; margin-bottom:4px;
}
.notif-row.is-unread        { background:var(--brand-primary-light); border-color:var(--brand-primary); }
.notif-row.is-unread:hover  { background:var(--brand-primary-light); box-shadow:0 2px 10px rgba(22,82,240,.15); }
.notif-row.is-read          { background:var(--surface-card); border-color:var(--border); }
.notif-row.is-read:hover    { background:var(--surface-muted); }
.notif-row.marked-read      { background:var(--surface-card) !important; border-color:var(--border) !important; }
.notif-icon-wrap {
    width:40px; height:40px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    font-size:1rem; flex-shrink:0;
}
.notif-unread-dot {
    position:absolute; top:16px; right:14px;
    width:8px; height:8px; background:var(--brand-primary); border-radius:50%;
}
.notif-group-label {
    font-size:.72rem; font-weight:700; letter-spacing:.08em;
    text-transform:uppercase; color:var(--text-muted);
    padding:14px 4px 6px; display:flex; align-items:center; gap:8px;
}
.notif-group-label::after { content:''; flex:1; height:1px; background:var(--border); }
</style>

<!-- Root Alpine component — references a script function, NOT an inline object literal.
     See the <script> block at the bottom for why this matters. -->
<div x-data="notifPage()">

<!-- ── Page header ──────────────────────────────────────────────── -->
<div class="flex items-start justify-between flex-wrap gap-3 mb-6">
    <div>
        <p class="text-xs font-bold uppercase tracking-widest text-blue-700 mb-0.5">Account</p>
        <h1 class="text-2xl font-bold text-slate-900 sm:text-3xl mt-0"><?= e(t('res_notifications.title')) ?></h1>

        <!-- Reactive sub-headline driven by Alpine unreadCount getter -->
        <p class="mt-1 text-sm text-slate-500" x-show="unreadCount > 0">
            <?= e(t('res_notifications.unread_prefix')) ?>
            <strong class="text-blue-700" x-text="unreadCount"></strong>
            <?= e(t('res_notifications.unread_suffix')) ?>
        </p>
        <p class="mt-1 text-sm text-slate-500" x-show="unreadCount === 0">
            <?= e($hasAny ? t('res_notifications.all_read') : t('res_notifications.none_yet')) ?>
        </p>
    </div>

    <!-- Mark all read — hidden once unreadCount reaches 0 -->
    <?php if ($hasAny): ?>
    <button type="button"
            @click="markAll()"
            x-show="unreadCount > 0"
            class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition"
            style="background:var(--brand-primary-light);border:1px solid var(--brand-primary);color:var(--brand-primary);"
            onmouseover="this.style.background='var(--brand-primary)';this.style.color='#fff';this.style.borderColor='var(--brand-primary)';"
            onmouseout="this.style.background='var(--brand-primary-light)';this.style.color='var(--brand-primary)';this.style.borderColor='var(--brand-primary)';">
        <i class="bi bi-check2-all"></i> <?= e(t('res_notifications.mark_all_read')) ?>
    </button>
    <?php endif; ?>
</div>

<!-- ── Empty state ──────────────────────────────────────────────── -->
<?php if (!$hasAny): ?>
<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 py-16 text-center">
    <div style="font-size:3.5rem;line-height:1;margin-bottom:1rem;opacity:.35;">🔔</div>
    <p class="font-semibold text-slate-500 text-base"><?= e(t('res_notifications.none_yet')) ?></p>
    <p class="mt-1 text-sm text-slate-400 max-w-xs mx-auto">
        <?= e(t('res_notifications.empty_hint')) ?>
    </p>
</div>

<?php else: ?>

<!-- ── Grouped notification list ────────────────────────────────── -->
<?php foreach ($groups as $groupKey => $groupItems):
    if (empty($groupItems)) continue; ?>

<div class="notif-group-label"><?= e($groupLabels[$groupKey] ?? $groupKey) ?></div>

<div class="space-y-0">
    <?php foreach ($groupItems as $notif):
        $typeKey    = $notif['type'] ?? 'system';
        $conf       = $typeConf[$typeKey] ?? $typeConf['system'];
        $isUnread   = !(int)$notif['is_read'];
        $rowClass   = $isUnread ? 'is-unread' : 'is-read';
        $urlJson    = json_encode($notif['url'], $jsonFlags);
        $msgPreview = mb_substr(strip_tags($notif['message']), 0, 90, 'UTF-8');
        if (mb_strlen($notif['message'], 'UTF-8') > 90) $msgPreview .= '…';
        $fullTime   = date('F j, Y \a\t g:i A', strtotime($notif['created_at']));
    ?>
    <?php // e() around the JSON, NOT the raw value: json_encode() emits real
          // double quotes, and those close a double-quoted HTML attribute the
          // moment the browser meets them. The attribute then ended at
          //   @click="markAndGo(45, "
          // with the rest of the URL parsed as stray attributes, so Alpine got
          // a half-expression and threw "Unexpected token '}'" — every row on
          // this page was unclickable. htmlspecialchars turns the quotes into
          // &quot;, which the browser decodes back to " before Alpine reads it. ?>
    <div class="notif-row <?= $rowClass ?>"
         :class="{ 'marked-read': isMarked(<?= (int)$notif['id'] ?>) }"
         @click="markAndGo(<?= (int)$notif['id'] ?>, <?= e($urlJson) ?>)"
         role="button"
         tabindex="0"
         @keydown.enter="markAndGo(<?= (int)$notif['id'] ?>, <?= e($urlJson) ?>)"
         aria-label="<?= e($notif['title']) ?>">

        <!-- Type icon -->
        <div class="notif-icon-wrap"
             style="background:<?= $conf['bg'] ?>;color:<?= $conf['color'] ?>;">
            <i class="bi <?= $conf['icon'] ?>"></i>
        </div>

        <!-- Content -->
        <div class="flex-1 min-w-0">
            <div class="flex items-baseline justify-between gap-2 flex-wrap">
                <p class="text-sm font-semibold text-slate-900 leading-snug"
                   :class="{ 'font-normal text-slate-500': isMarked(<?= (int)$notif['id'] ?>) }">
                    <?= e($notif['title']) ?>
                </p>
                <!-- Timestamp: shows relative text; tooltip shows exact datetime -->
                <span class="text-xs text-slate-400 shrink-0 whitespace-nowrap"
                      title="<?= e($fullTime) ?>">
                    <?= e($timeAgo($notif['created_at'])) ?>
                </span>
            </div>
            <p class="mt-0.5 text-xs leading-relaxed text-slate-500 line-clamp-2">
                <?= e($msgPreview) ?>
            </p>
            <!-- Full timestamp below the message -->
            <p class="mt-1 text-[10px] text-slate-400"><?= e($fullTime) ?></p>
        </div>

        <!-- Unread dot (hidden via Alpine once the item is marked) -->
        <?php if ($isUnread): ?>
        <span class="notif-unread-dot"
              x-show="!isMarked(<?= (int)$notif['id'] ?>)"
              aria-hidden="true"></span>
        <?php endif; ?>

    </div>
    <?php endforeach; ?>
</div>

<?php endforeach; ?>
<?php endif; ?>

</div><!-- /x-data -->

<!--
╔══════════════════════════════════════════════════════════════════════════════╗
║  WHY THIS IS A <script> BLOCK AND NOT x-data="{ csrf: ..., base: ... }"    ║
╠══════════════════════════════════════════════════════════════════════════════╣
║  json_encode() produces JSON — strings are wrapped in double-quotes.        ║
║  e.g.  csrf_token()  →  json_encode()  →  "a1b2c3d4..."                    ║
║                                                                              ║
║  Placing that inside a double-quoted HTML attribute:                         ║
║    <div x-data="{ csrf: "a1b2c3...", base: "http://..." }">                 ║
║  The first embedded " closes the HTML attribute. Everything after is         ║
║  rendered as raw page text — the JS leak visible in the screenshot.          ║
║                                                                              ║
║  Inside a <script> tag there is no HTML-attribute parsing, so               ║
║  double-quoted JS string literals are valid.                                 ║
╚══════════════════════════════════════════════════════════════════════════════╝
-->
<script>
function notifPage() {
    return {
        /* Seeded from PHP — all notification IDs on this page */
        allIds:      <?= $allIdsJson ?>,
        /* IDs that were unread when the page loaded */
        unreadOnLoad: <?= $unreadIdsJson ?>,
        /* IDs the user has clicked "read" during this session */
        markedIds:   [],
        csrf:        <?= $csrfJson ?>,
        base:        <?= $baseJson ?>,

        /**
         * Reactive count of still-unread notifications.
         * Alpine v3 tracks property access in getters, so this recomputes
         * automatically whenever markedIds changes.
         */
        get unreadCount() {
            return this.unreadOnLoad.filter(id => !this.markedIds.includes(id)).length;
        },

        /** True when an unread notification has been clicked and marked this session. */
        isMarked(id) {
            return this.markedIds.includes(id);
        },

        /** Mark one notification as read on the server, then navigate to its URL. */
        markAndGo(id, url) {
            if (!this.markedIds.includes(id)) this.markedIds.push(id);
            const fd = new FormData();
            fd.append('csrf_token', this.csrf);
            fetch(this.base + '/api/notifications/' + id + '/read', { method: 'POST', body: fd })
                .catch(() => {})
                .finally(() => { window.location.href = url; });
        },

        /** Mark ALL notifications as read and update the header bell badge. */
        markAll() {
            this.markedIds = [...this.allIds];
            const fd = new FormData();
            fd.append('csrf_token', this.csrf);
            fetch(this.base + '/api/notifications/mark-read', { method: 'POST', body: fd })
                .catch(() => {});
            const badge = document.getElementById('notif-badge');
            if (badge) badge.style.display = 'none';
        },
    };
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';