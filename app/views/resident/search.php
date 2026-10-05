<?php
/** @var string $q @var array $results @var int $total */
$groups = [
    'announcements' => ['Announcements', 'bi-megaphone-fill'],
    'events'        => ['Events', 'bi-calendar-event-fill'],
    'ordinances'    => ['Ordinances', 'bi-journal-text'],
    'documents'     => ['Document requests', 'bi-file-earmark-text-fill'],
    'dictionary'    => ['Dictionary', 'bi-book-half'],
];
ob_start();
?>
<div class="ds-page-head">
    <p class="ds-page-head__eyebrow">Search</p>
    <h1 class="ds-page-head__title">Search BarangGabay</h1>
    <p class="ds-page-head__sub">Announcements, events, ordinances, document requests and the dictionary.</p>
</div>

<form method="get" action="<?= e(route('search')) ?>" class="ds-card mb-6 p-3 flex gap-2" role="search">
    <label for="globalSearch" class="visually-hidden sr-only">Search</label>
    <div class="relative flex-1">
        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-500"><i class="bi bi-search" aria-hidden="true"></i></span>
        <input id="globalSearch" type="search" name="q" value="<?= e($q) ?>" minlength="2" maxlength="100" autofocus
               placeholder="Search announcements, events, ordinances…"
               class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-3 text-sm text-slate-800">
    </div>
    <button type="submit" class="rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-bold text-white">Search</button>
</form>

<?php if ($q === ''): ?>
    <div class="ds-card ds-empty"><i class="bi bi-search" aria-hidden="true"></i><strong>What are you looking for?</strong>Type at least two letters.</div>
<?php elseif (mb_strlen($q) < 2): ?>
    <div class="ds-card ds-empty"><i class="bi bi-search" aria-hidden="true"></i><strong>Keep typing</strong>Type at least two letters.</div>
<?php elseif ($total === 0): ?>
    <div class="ds-card ds-empty"><i class="bi bi-emoji-neutral" aria-hidden="true"></i><strong>No results for “<?= e($q) ?>”</strong>Try a different word, or check the spelling.</div>
<?php else: ?>
    <p class="mb-4 text-sm text-slate-500"><?= (int) $total ?> result<?= $total === 1 ? '' : 's' ?> for “<span class="font-semibold text-slate-700"><?= e($q) ?></span>”</p>
    <div class="space-y-6">
    <?php foreach ($groups as $key => [$label, $icon]): if (empty($results[$key])) { continue; } ?>
        <section aria-labelledby="sg-<?= $key ?>">
            <h2 id="sg-<?= $key ?>" class="mb-2 flex items-center gap-2 text-sm font-bold uppercase tracking-widest text-slate-500">
                <i class="bi <?= $icon ?>" aria-hidden="true"></i><?= e($label) ?> <span class="text-slate-400">(<?= count($results[$key]) ?>)</span>
            </h2>
            <ul class="ds-card divide-y divide-slate-100 overflow-hidden" style="list-style:none;margin:0;padding:0;">
            <?php foreach ($results[$key] as $r): ?>
                <li>
                <?php if ($key === 'announcements'): ?>
                    <a href="<?= e(route('announcements/' . $r['slug'])) ?>" class="block px-4 py-3 hover:bg-slate-50">
                        <span class="flex items-center gap-2">
                            <span class="font-bold text-slate-900"><?= e(localised_text($r, 'title')) ?></span>
                            <?php if (($r['urgency'] ?? '') === 'urgent'): ?><?= status_badge('failed', 'Urgent') ?><?php endif; ?>
                        </span>
                        <span class="block text-xs text-slate-500"><?= e(date('M j, Y', strtotime((string) $r['published_at']))) ?></span>
                        <span class="mt-1 block text-sm text-slate-600"><?= e(\App\Controllers\SearchController::excerpt($r, 'body')) ?></span>
                    </a>
                <?php elseif ($key === 'events'): ?>
                    <a href="<?= e(route('events/' . $r['slug'])) ?>" class="block px-4 py-3 hover:bg-slate-50">
                        <span class="flex items-center gap-2"><span class="font-bold text-slate-900"><?= e(localised_text($r, 'title')) ?></span><?= status_badge((string) $r['status'], ucfirst((string) $r['status'])) ?></span>
                        <span class="block text-xs text-slate-500"><?= e(date('M j, Y g:i A', strtotime((string) $r['event_date']))) ?><?= !empty($r['venue']) ? ' · ' . e((string) $r['venue']) : '' ?></span>
                    </a>
                <?php elseif ($key === 'ordinances'): ?>
                    <a href="<?= e(route('ordinances/' . $r['id'])) ?>" class="block px-4 py-3 hover:bg-slate-50">
                        <span class="flex items-center gap-2"><span class="font-bold text-slate-900"><?= e((string) $r['ordinance_no']) ?>: <?= e(localised_text($r, 'title')) ?></span><?= status_badge((string) $r['status'], ucfirst((string) $r['status'])) ?></span>
                        <span class="mt-1 block text-sm text-slate-600"><?= e(\App\Controllers\SearchController::excerpt($r, 'description')) ?></span>
                    </a>
                <?php elseif ($key === 'documents'): ?>
                    <a href="<?= e(route('documents')) ?>" class="block px-4 py-3 hover:bg-slate-50">
                        <span class="font-bold text-slate-900"><?= e($r['label']) ?></span>
                        <span class="block text-xs text-slate-500">Request this document online</span>
                    </a>
                <?php else: ?>
                    <a href="<?= e(route('dictionary') . '?q=' . rawurlencode((string) ($r['manobo'] ?? ''))) ?>" class="block px-4 py-3 hover:bg-slate-50">
                        <span class="font-bold text-blue-700"><?= e((string) ($r['manobo'] ?? '')) ?></span>
                        <span class="block text-sm text-slate-600"><?= e(trim((string) ($r['english'] ?? '')) . (!empty($r['tagalog']) ? ' · ' . $r['tagalog'] : '')) ?></span>
                    </a>
                <?php endif; ?>
                </li>
            <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
