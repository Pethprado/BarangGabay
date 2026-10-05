<?php
/** @var array $items */
$typeMeta = [
    'announcement' => ['Announcement', 'bi-megaphone-fill'],
    'event'        => ['Event', 'bi-calendar-event-fill'],
    'ordinance'    => ['Ordinance', 'bi-journal-text'],
];
ob_start();
?>
<div class="ds-page-head">
    <p class="ds-page-head__eyebrow">Saved</p>
    <h1 class="ds-page-head__title">My Bookmarks</h1>
    <p class="ds-page-head__sub">Announcements, events and ordinances you saved for later.</p>
</div>

<?php if (!$items): ?>
    <div class="ds-card ds-empty"><i class="bi bi-bookmark" aria-hidden="true"></i><strong>Nothing saved yet</strong>Tap the bookmark button on any announcement, event or ordinance to keep it here.</div>
<?php else: ?>
    <ul class="ds-card divide-y divide-slate-100 overflow-hidden" style="list-style:none;margin:0;padding:0;">
    <?php foreach ($items as $it):
        $r = $it['row'];
        [$label, $icon] = $typeMeta[$it['type']];
        $url = match ($it['type']) {
            'announcement' => route('announcements/' . $r['slug']),
            'event'        => route('events/' . $r['slug']),
            default        => route('ordinances/' . $r['id']),
        };
    ?>
        <li class="flex items-center gap-3 px-4 py-3">
            <span class="inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-blue-100 text-blue-700"><i class="bi <?= $icon ?>" aria-hidden="true"></i></span>
            <a href="<?= e($url) ?>" class="min-w-0 flex-1">
                <span class="block truncate font-bold text-slate-900"><?= e(localised_text($r, 'title')) ?></span>
                <span class="block text-xs text-slate-500"><?= e($label) ?> · saved <?= e(date('M j, Y', strtotime((string) $it['saved_at']))) ?></span>
            </a>
            <button type="button" class="ds-chip" data-bookmark-toggle data-type="<?= e($it['type']) ?>" data-id="<?= (int) $r['id'] ?>" aria-pressed="true" aria-label="Remove bookmark">
                <i class="bi bi-bookmark-fill" aria-hidden="true"></i>
            </button>
        </li>
    <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
