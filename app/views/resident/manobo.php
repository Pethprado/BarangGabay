<?php
/**
 * Resident-facing Manobo dictionary — a read-only lookup tool.
 *
 * Aimed at residents who do NOT fully read Manobo: search in any of the three
 * languages and see the other two side by side, plus the note and cited source
 * for the entry. Curation lives at /admin/manobo; nothing here can write.
 *
 * Filtering is server-side (a plain GET form, so it works without JavaScript);
 * Alpine only adds instant client-side narrowing on top of the rendered rows,
 * which is affordable because the whole dataset is small enough to ship.
 *
 * Variables from ManoboController::residentIndex():
 *   list<array<string,string>> $entries, list<string> $categories,
 *   string $search, string $category, int $totalWords
 */
$entries    = $entries    ?? [];
$categories = $categories ?? [];
$search     = (string) ($search   ?? '');
$category   = (string) ($category ?? '');
$totalWords = (int)    ($totalWords ?? 0);

// Category chips reuse the announcement palette so the page belongs to the
// same visual system rather than introducing a second one.
// CHANGED: the 2026 dataset (migration 030) added phrase/time/pronoun/verb/
// adjective/noun/emotion/direction/connector/question/object/other, which
// this map did not cover yet — every category now gets a colour.
$catColors = [
    'body'      => 'bg-rose-100 text-rose-700',
    'animal'    => 'bg-orange-100 text-orange-700',
    'animals'   => 'bg-orange-100 text-orange-700',
    'numbers'   => 'bg-blue-100 text-blue-700',
    'family'    => 'bg-purple-100 text-purple-700',
    'health'    => 'bg-green-100 text-green-700',
    'nature'    => 'bg-green-100 text-green-700',
    'food'      => 'bg-amber-100 text-amber-700',
    'phrase'    => 'bg-indigo-100 text-indigo-700',
    'time'      => 'bg-teal-100 text-teal-700',
    'pronoun'   => 'bg-rose-100 text-rose-700',
    'verb'      => 'bg-blue-100 text-blue-700',
    'adjective' => 'bg-purple-100 text-purple-700',
    'noun'      => 'bg-orange-100 text-orange-700',
    'emotion'   => 'bg-amber-100 text-amber-700',
    'direction' => 'bg-teal-100 text-teal-700',
    'connector' => 'bg-indigo-100 text-indigo-700',
    'question'  => 'bg-green-100 text-green-700',
    'object'    => 'bg-amber-100 text-amber-700',
    'other'     => 'bg-slate-100 text-slate-700',
];

// ADDED: sorted alphabetically by headword so the A-Z jump bar below means
// something — the dictionary itself keeps insertion order (see
// ManoboDictionary::load()), which is right for curation but not for browsing.
usort($entries, static fn (array $a, array $b): int => strcasecmp($a['manobo'] ?? '', $b['manobo'] ?? ''));

// One anchor id per first letter actually present, on its FIRST entry only.
$letterAnchors  = [];
$availableLetters = [];
foreach ($entries as $entry) {
    $letter = mb_strtoupper(mb_substr((string) ($entry['manobo'] ?? ''), 0, 1, 'UTF-8'), 'UTF-8');
    if ($letter === '' || isset($letterAnchors[$letter])) {
        continue;
    }
    $letterAnchors[$letter]    = ($entry['manobo'] ?? '') . '|' . ($entry['tagalog'] ?? '');
    $availableLetters[$letter] = true;
}
$allLetters = str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ');

ob_start();
?>

<!-- ── Page header ────────────────────────────────────────────── -->
<div class="mb-6">
    <p class="text-xs font-bold uppercase tracking-widest text-purple-700"><?= e(t('res_manobo.eyebrow')) ?></p>
    <div class="mt-1 flex flex-wrap items-baseline gap-3">
        <h1 class="text-2xl font-bold text-slate-900 sm:text-3xl"><?= e(t('res_manobo.title')) ?></h1>
        <span class="rounded-full bg-purple-100 px-3 py-0.5 text-xs font-semibold text-purple-700">
            <span data-countup="<?= $totalWords ?>"><?= $totalWords ?></span>
        </span>
    </div>
    <p class="mt-1 max-w-3xl text-sm leading-relaxed text-slate-500"><?= e(t('res_manobo.subtitle')) ?></p>
</div>

<?php if ($totalWords === 0): ?>
<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 py-16 text-center">
    <i class="bi bi-book text-4xl text-slate-300"></i>
    <p class="mt-3 text-sm font-medium text-slate-500"><?= e(t('res_manobo.dataset_empty')) ?></p>
</div>

<?php else: ?>
<div x-data="manoboDict()">

    <!-- ── Search + category filter (server-side GET; JS narrows further) ── -->
    <div class="fade-up mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="get" action="<?= e(route('manobo')) ?>" class="flex flex-wrap items-end gap-3">

            <div class="min-w-[220px] flex-[2]">
                <label for="q" class="mb-1 block text-xs font-semibold text-slate-600">
                    <?= e(t('res_manobo.search_label')) ?>
                </label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-500">
                        <i class="bi bi-search text-sm"></i>
                    </span>
                    <input type="text" id="q" name="q"
                           x-model="query"
                           value="<?= e($search) ?>"
                           placeholder="<?= e(t('res_manobo.placeholder')) ?>"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-sm text-slate-800 focus:border-purple-400 focus:outline-none focus:ring-2 focus:ring-purple-100">
                </div>
            </div>

            <div class="min-w-[150px] flex-1">
                <label for="category" class="mb-1 block text-xs font-semibold text-slate-600">
                    <?= e(t('res_announcements.category_label')) ?>
                </label>
                <select id="category" name="category" onchange="this.form.submit()"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800 focus:border-purple-400 focus:outline-none focus:ring-2 focus:ring-purple-100">
                    <option value=""><?= e(t('res_manobo.all_categories')) ?></option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e(ucfirst($cat)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="flex-shrink-0 rounded-xl bg-purple-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-purple-800">
                <i class="bi bi-search me-1"></i><?= e(t('res_manobo.filter')) ?>
            </button>

            <?php if ($search !== '' || $category !== ''): ?>
            <a href="<?= e(route('manobo')) ?>"
               class="flex-shrink-0 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">
                <i class="bi bi-x me-1"></i><?= e(t('res_manobo.reset')) ?>
            </a>
            <?php endif; ?>
        </form>

        <p class="mt-3 text-xs text-slate-500">
            <?= e(t('res_manobo.count', ['shown' => count($entries), 'total' => $totalWords])) ?>
            <span x-show="query" x-cloak>&middot; <span x-text="visible"></span></span>
        </p>
    </div>

    <!-- ── ADDED: A-Z letter jump bar ─────────────────────────────────── -->
    <?php if (!empty($entries)): ?>
    <div class="fade-up mb-4 flex flex-wrap gap-1.5 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
        <?php foreach ($allLetters as $letter): ?>
            <?php if (isset($availableLetters[$letter])): ?>
            <a href="#letter-<?= e($letter) ?>"
               class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg bg-purple-100 text-xs font-bold text-purple-700 transition hover:bg-purple-700 hover:text-white">
                <?= e($letter) ?>
            </a>
            <?php else: ?>
            <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg text-xs font-semibold text-slate-300">
                <?= e($letter) ?>
            </span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── Entries ────────────────────────────────────────────────── -->
    <?php if (empty($entries)): ?>
    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 py-16 text-center">
        <i class="bi bi-search text-4xl text-slate-300"></i>
        <p class="mt-3 text-base font-semibold text-slate-500"><?= e(t('res_manobo.empty')) ?></p>
        <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
            <?= e(t('res_manobo.empty_hint', ['total' => $totalWords])) ?>
        </p>
    </div>

    <?php else: ?>
    <div class="grid gap-3 sm:grid-cols-2">
        <?php foreach ($entries as $idx => $entry):
            $cat     = (string) ($entry['category'] ?? '');
            $catCls  = $catColors[$cat] ?? 'bg-slate-100 text-slate-700';
            $haystack = mb_strtolower(implode(' ', [
                $entry['manobo'] ?? '', $entry['english'] ?? '', $entry['tagalog'] ?? '', $cat,
            ]), 'UTF-8');
            $fade   = 'fade-up-delay-' . (($idx % 4) + 1);
            $letter = mb_strtoupper(mb_substr((string) ($entry['manobo'] ?? ''), 0, 1, 'UTF-8'), 'UTF-8');
            // ADDED: the A-Z bar's anchor target, once per letter (the sorted
            // order guarantees this is that letter's first card).
            $isFirstOfLetter = isset($letterAnchors[$letter])
                && $letterAnchors[$letter] === ($entry['manobo'] ?? '') . '|' . ($entry['tagalog'] ?? '');
            if ($isFirstOfLetter) {
                unset($letterAnchors[$letter]); // only the very first match keeps the anchor
            }
        ?>
        <!-- ADDED: clicking the card opens the full entry in a modal (Alpine,
             shared $store so only one instance renders at page scope). -->
        <article class="fade-up <?= $fade ?> cursor-pointer rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                 <?= $isFirstOfLetter ? 'id="letter-' . e($letter) . '"' : '' ?>
                 data-term="<?= e($haystack) ?>"
                 x-show="matches($el)" x-cloak
                 @click="openEntry(<?= e(json_encode($entry)) ?>)">

            <div class="flex items-start justify-between gap-3">
                <p class="text-lg font-black leading-tight text-purple-700"><?= e($entry['manobo'] ?? '') ?></p>
                <?php if ($cat !== ''): ?>
                <span class="flex-shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-semibold <?= $catCls ?>">
                    <?= e($cat) ?>
                </span>
                <?php endif; ?>
            </div>

            <?php if (!empty($entry['part_of_speech'])): ?>
            <p class="mt-0.5 text-xs italic text-slate-500"><?= e($entry['part_of_speech']) ?></p>
            <?php endif; ?>

            <dl class="mt-3 space-y-1.5">
                <div class="flex gap-2">
                    <dt class="w-16 flex-shrink-0 text-[11px] font-bold uppercase tracking-wide text-slate-500">
                        <?= e(t('res_manobo.col_english')) ?>
                    </dt>
                    <dd class="text-sm font-semibold text-slate-700"><?= e($entry['english'] ?? '—') ?></dd>
                </div>
                <div class="flex gap-2">
                    <dt class="w-16 flex-shrink-0 text-[11px] font-bold uppercase tracking-wide text-slate-500">
                        <?= e(t('res_manobo.col_tagalog')) ?>
                    </dt>
                    <dd class="text-sm font-semibold text-slate-700"><?= e($entry['tagalog'] ?? '—') ?></dd>
                </div>
            </dl>

            <?php if (!empty($entry['notes'])): ?>
            <!-- CHANGED: was an inline <details> disclosure — the notes and
                 source now show in the click-to-open modal instead, so this
                 is just a hint that there is more to see there. -->
            <p class="mt-3 text-xs font-semibold text-purple-700">
                <i class="bi bi-info-circle me-1"></i><?= e(t('res_manobo.notes')) ?>
            </p>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
    </div>

    <!-- ── ADDED: click-to-open detail modal ─────────────────────────── -->
    <div x-show="activeEntry" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
         @click.self="activeEntry = null">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" @click.stop x-show="activeEntry" x-cloak>
            <template x-if="activeEntry">
                <div>
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="text-2xl font-black text-purple-700" x-text="activeEntry.manobo"></h2>
                        <button type="button" class="flex-shrink-0 rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                                @click="activeEntry = null" aria-label="<?= e(t('res_manobo.close')) ?>">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <p class="mt-0.5 text-xs italic text-slate-500" x-show="activeEntry.part_of_speech" x-text="activeEntry.part_of_speech"></p>
                    <span class="mt-2 inline-block rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-700" x-text="activeEntry.category"></span>

                    <dl class="mt-4 space-y-2 border-t border-slate-100 pt-4">
                        <div>
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500"><?= e(t('res_manobo.col_english')) ?></dt>
                            <dd class="text-base font-semibold text-slate-800" x-text="activeEntry.english"></dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500"><?= e(t('res_manobo.col_tagalog')) ?></dt>
                            <dd class="text-base font-semibold text-slate-800" x-text="activeEntry.tagalog"></dd>
                        </div>
                    </dl>

                    <div class="mt-4 border-t border-slate-100 pt-4" x-show="activeEntry.notes">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500"><?= e(t('res_manobo.notes')) ?></p>
                        <p class="mt-1 text-sm leading-relaxed text-slate-600" x-text="activeEntry.notes"></p>
                        <p class="mt-2 text-[11px] text-slate-500" x-show="activeEntry.source">
                            <?= e(t('res_manobo.source')) ?>: <span class="font-mono" x-text="activeEntry.source"></span>
                        </p>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Client-side "nothing matches" state, shown only while filtering. -->
    <div x-show="query && visible === 0" x-cloak
         class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 py-12 text-center">
        <i class="bi bi-search text-3xl text-slate-300"></i>
        <p class="mt-3 text-sm font-semibold text-slate-500"><?= e(t('res_manobo.empty')) ?></p>
    </div>
    <?php endif; ?>

    <p class="mt-6 flex items-start gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs leading-relaxed text-slate-600">
        <i class="bi bi-info-circle mt-0.5 flex-shrink-0 text-slate-500"></i>
        <span><?= e(t('res_manobo.growing_note')) ?></span>
    </p>
</div>

<script>
    /* Instant narrowing over the already-rendered rows. The GET form above is
       the real filter and works with JavaScript off; this only saves a round
       trip, which is affordable because the whole dataset fits on one page. */
    function manoboDict() {
        return {
            query:   <?= json_encode($search) ?>,
            visible: 0,
            activeEntry: null,   // ADDED: the click-to-open modal's current word

            matches(el) {
                var q = (this.query || '').trim().toLowerCase();
                var hit = q === '' || (el.dataset.term || '').indexOf(q) !== -1;
                // Recount on each pass so the "nothing matches" state is accurate.
                this.$nextTick(function () {
                    this.visible = document.querySelectorAll('[data-term]:not([style*="display: none"])').length;
                }.bind(this));
                return hit;
            },

            openEntry(entry) {
                this.activeEntry = entry;
            },
        };
    }

</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
