<?php
/**
 * "Saan ako pupunta?" — the evacuation centre for this resident's purok.
 *
 * Written to be read on a phone, in a hurry, possibly on a bad signal. The
 * resident's own purok comes first and is visually the loudest thing on the
 * page; barangay-wide centres follow as a fallback rather than competing with
 * it. No map is loaded until the reader asks — during a storm, data and
 * battery are both scarce.
 *
 * Variables: $centers (list), $purok (string|null), $mapsKey (string)
 */
$centers = $centers ?? [];
$purok   = $purok   ?? null;
?>
<?php ob_start(); ?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(t('evacuation.title')) ?></h1>
    <p class="mt-1 text-sm text-slate-500">
        <?= $purok !== null
            ? e(t('evacuation.subtitle_purok', ['purok' => $purok]))
            : e(t('evacuation.subtitle_all')) ?>
    </p>
</div>

<?php if ($purok === null): ?>
<div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
    <p class="text-sm font-semibold text-amber-900">
        <i class="bi bi-exclamation-triangle me-1"></i><?= e(t('evacuation.no_purok_title')) ?>
    </p>
    <p class="mt-1 text-xs leading-relaxed text-amber-800"><?= e(t('evacuation.no_purok_help')) ?></p>
    <a href="<?= e(route('profile')) ?>"
       class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-amber-900 hover:underline">
        <?= e(t('evacuation.set_purok')) ?> <i class="bi bi-arrow-right"></i>
    </a>
</div>
<?php endif; ?>

<?php if ($centers === []): ?>
<div class="rounded-2xl border border-dashed border-slate-300 py-14 text-center">
    <i class="bi bi-house-exclamation text-4xl text-slate-300"></i>
    <p class="mt-3 text-sm font-semibold text-slate-500"><?= e(t('evacuation.none')) ?></p>
    <p class="mt-1 text-xs text-slate-400"><?= e(t('evacuation.none_help')) ?></p>
</div>
<?php else: ?>

<div class="space-y-4">
    <?php foreach ($centers as $c):
        $isMine = $purok !== null && (string) ($c['purok'] ?? '') === $purok;
        $hasPin = !empty($c['latitude']) && !empty($c['longitude']);
    ?>
    <article class="rounded-2xl border <?= $isMine ? 'border-l-4 border-l-red-500 border-slate-200' : 'border-slate-200' ?> bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-slate-900"><?= e((string) $c['name']) ?></h2>
                <?php if (!empty($c['address'])): ?>
                <p class="mt-0.5 text-sm text-slate-600"><?= e((string) $c['address']) ?></p>
                <?php endif; ?>
            </div>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                         <?= $isMine ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700' ?>">
                <?= $c['purok'] !== null && $c['purok'] !== ''
                    ? e((string) $c['purok'])
                    : e(t('evacuation.whole_barangay')) ?>
            </span>
        </div>

        <?php if ($isMine): ?>
        <p class="mt-2 text-sm font-semibold text-red-700">
            <i class="bi bi-geo-alt-fill me-1"></i><?= e(t('evacuation.this_is_yours')) ?>
        </p>
        <?php endif; ?>

        <dl class="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
            <?php if (!empty($c['capacity'])): ?>
            <div class="flex gap-2">
                <dt class="font-semibold text-slate-500"><?= e(t('evacuation.capacity')) ?>:</dt>
                <dd class="text-slate-700"><?= e((string) $c['capacity']) ?></dd>
            </div>
            <?php endif; ?>
            <?php if (!empty($c['contact_person'])): ?>
            <div class="flex gap-2">
                <dt class="font-semibold text-slate-500"><?= e(t('evacuation.contact')) ?>:</dt>
                <dd class="text-slate-700"><?= e((string) $c['contact_person']) ?></dd>
            </div>
            <?php endif; ?>
        </dl>

        <div class="mt-4 flex flex-wrap gap-2">
            <?php if (!empty($c['contact_phone'])): ?>
            <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $c['contact_phone']) ?? '') ?>"
               class="inline-flex items-center gap-1 rounded-lg bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-700">
                <i class="bi bi-telephone-fill"></i> <?= e((string) $c['contact_phone']) ?>
            </a>
            <?php endif; ?>

            <?php if ($hasPin): ?>
            <?php /* A link, not an embedded map. During a storm the reader may
                     be on a weak signal and a low battery; loading a map they
                     did not ask for spends both. */ ?>
            <a href="https://www.google.com/maps/search/?api=1&query=<?= e((string) $c['latitude']) ?>,<?= e((string) $c['longitude']) ?>"
               target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-slate-50">
                <i class="bi bi-map"></i> <?= e(t('evacuation.open_map')) ?>
            </a>
            <?php endif; ?>
        </div>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
