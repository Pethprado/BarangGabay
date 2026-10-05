<?php
/**
 * Resident-facing Manobo dictionary — a read-only lookup tool.
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
$lang       = ($lang ?? 'msm') === 'ceb' ? 'ceb' : 'msm';

// Sort entries alphabetically by manobo headword
usort($entries, static fn (array $a, array $b): int => strcasecmp($a['manobo'] ?? '', $b['manobo'] ?? ''));

$catColors = [
    'body'      => 'bg-rose-100 text-rose-700',
    'animal'    => 'bg-orange-100 text-orange-700',
    'animals'   => 'bg-orange-100 text-orange-700',
    'numbers'   => 'bg-blue-100 text-blue-700',
    'family'    => 'bg-blue-100 text-blue-700',
    'health'    => 'bg-green-100 text-green-700',
    'nature'    => 'bg-green-100 text-green-700',
    'food'      => 'bg-amber-100 text-amber-700',
    'phrase'    => 'bg-indigo-100 text-indigo-700',
    'time'      => 'bg-teal-100 text-teal-700',
    'pronoun'   => 'bg-rose-100 text-rose-700',
    'verb'      => 'bg-blue-100 text-blue-700',
    'adjective' => 'bg-blue-100 text-blue-700',
    'noun'      => 'bg-orange-100 text-orange-700',
    'emotion'   => 'bg-amber-100 text-amber-700',
    'direction' => 'bg-teal-100 text-teal-700',
    'connector' => 'bg-indigo-100 text-indigo-700',
    'question'  => 'bg-green-100 text-green-700',
    'object'    => 'bg-amber-100 text-amber-700',
    'other'     => 'bg-slate-100 text-slate-700',
];

ob_start();
?>

<!-- ── Page header ────────────────────────────────────────────── -->
<div class="mb-6">
    <p class="text-xs font-bold uppercase tracking-widest text-blue-700"><?= e(t('res_manobo.eyebrow')) ?></p>
    <div class="mt-1 flex flex-wrap items-baseline gap-3">
        <h1 class="text-2xl font-bold text-slate-900 sm:text-3xl"><?= e(t('res_manobo.title')) ?></h1>
        <span class="rounded-full bg-blue-100 px-3 py-0.5 text-xs font-semibold text-blue-700">
            <span><?= $totalWords ?></span> <?= e(t('admin_manobo.words_label') ?? 'mga salita') ?>
        </span>
    </div>
    <p class="mt-1 max-w-3xl text-sm leading-relaxed text-slate-500"><?= e(t('res_manobo.subtitle')) ?></p>
    <nav class="ds-chips mt-3" aria-label="Dictionary language">
        <a href="<?= e(route('dictionary')) ?>" class="ds-chip<?= $lang === 'msm' ? ' is-active' : '' ?>" aria-current="<?= $lang === 'msm' ? 'true' : 'false' ?>">Manobo</a>
        <a href="<?= e(route('dictionary') . '?lang=ceb') ?>" class="ds-chip<?= $lang === 'ceb' ? ' is-active' : '' ?>" aria-current="<?= $lang === 'ceb' ? 'true' : 'false' ?>">Bisaya</a>
    </nav>
</div>

<?php if ($totalWords === 0): ?>
<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 py-16 text-center">
    <i class="bi bi-book text-4xl text-slate-300"></i>
    <p class="mt-3 text-sm font-medium text-slate-500"><?= e(t('res_manobo.dataset_empty')) ?></p>
</div>

<?php else: ?>
<div x-data="manoboDict(<?= e(json_encode(array_values($entries))) ?>, <?= e(json_encode($search)) ?>, <?= e(json_encode($category)) ?>)">

    <!-- ── Search & Category Filter Bar ────────────────────────────── -->
    <div class="fade-up mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form @submit.prevent="onSearchChange()" class="flex flex-wrap items-end gap-3">

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
                           @input.debounce.250ms="onSearchChange()"
                           placeholder="<?= e(t('res_manobo.placeholder')) ?>"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-sm text-slate-800 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
            </div>

            <div class="min-w-[150px] flex-1">
                <label for="category" class="mb-1 block text-xs font-semibold text-slate-600">
                    <?= e(t('res_announcements.category_label')) ?>
                </label>
                <select id="category" name="category" x-model="category" @change="onSearchChange()"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <option value=""><?= e(t('res_manobo.all_categories')) ?></option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat) ?>"><?= e(ucfirst($cat)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="button" @click="onSearchChange()" class="flex-shrink-0 rounded-xl bg-blue-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-800">
                <i class="bi bi-search me-1"></i><?= e(t('res_manobo.filter')) ?>
            </button>

            <button type="button" x-show="query !== '' || category !== '' || selectedLetter !== defaultLetter"
                    @click="resetFilters()" x-cloak
                    class="flex-shrink-0 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">
                <i class="bi bi-x me-1"></i><?= e(t('res_manobo.reset')) ?>
            </button>
        </form>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 text-xs text-slate-500">
            <div>
                <span><?= e(t('admin_manobo.total_label') ?? 'Kabuuan:') ?> <strong><?= $totalWords ?></strong> <?= e(t('admin_manobo.words_label') ?? 'mga salita') ?></span>
                <span x-show="selectedLetter !== 'ALL'" class="ms-2">
                    &middot; Letra <strong class="text-blue-700" x-text="selectedLetter"></strong>: <span class="font-bold text-slate-800" x-text="filteredEntries.length"></span> entri
                </span>
                <span x-show="selectedLetter === 'ALL'" class="ms-2">
                    &middot; Na-filter: <span class="font-bold text-slate-800" x-text="filteredEntries.length"></span> entri
                </span>
            </div>
            <div x-show="totalPages > 1" class="font-semibold text-slate-600">
                Pahina <span x-text="currentPage"></span> sa <span x-text="totalPages"></span>
            </div>
        </div>
    </div>

    <!-- ── Alphabet Navigation Bar & Next/Prev Controls ────────────── -->
    <div class="fade-up mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between gap-2 mb-3">
            <button type="button" @click="prevLetter()"
                    :disabled="isPrevLetterDisabled"
                    :class="isPrevLetterDisabled ? 'opacity-40 cursor-not-allowed bg-slate-100 text-slate-400' : 'bg-blue-50 text-blue-700 hover:bg-blue-100'"
                    class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition">
                <i class="bi bi-chevron-left"></i>
                <span class="hidden sm:inline">Nakalipas na Letra</span>
                <span class="sm:hidden">Nakaraan</span>
            </button>

            <div class="text-center">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-600">
                    <span x-show="selectedLetter !== 'ALL'">Mga Salitang Nagsisimula sa <span class="text-base font-black text-blue-700" x-text="selectedLetter"></span></span>
                    <span x-show="selectedLetter === 'ALL'">Lahat ng Letra</span>
                </span>
            </div>

            <button type="button" @click="nextLetter()"
                    :disabled="isNextLetterDisabled"
                    :class="isNextLetterDisabled ? 'opacity-40 cursor-not-allowed bg-slate-100 text-slate-400' : 'bg-blue-50 text-blue-700 hover:bg-blue-100'"
                    class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition">
                <span class="hidden sm:inline">Susunod na Letra</span>
                <span class="sm:hidden">Susunod</span>
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>

        <!-- A-Z Alphabet Buttons -->
        <div class="flex flex-wrap items-center justify-center gap-1.5 pt-1 border-t border-slate-100">
            <button type="button"
                    @click="selectLetter('ALL')"
                    :aria-pressed="selectedLetter === 'ALL' ? 'true' : 'false'"
                    :class="selectedLetter === 'ALL' ? 'bg-blue-700 text-white font-black shadow-sm ring-2 ring-blue-300' : 'bg-slate-100 text-slate-700 hover:bg-blue-100 hover:text-blue-700'"
                    class="flex h-8 px-2.5 items-center justify-center rounded-lg text-xs font-bold transition">
                Lahat
            </button>

            <template x-for="l in alphabet" :key="l">
                <button type="button"
                        @click="selectLetter(l)"
                        :aria-pressed="selectedLetter === l ? 'true' : 'false'"
                        :disabled="!availableSet[l]"
                        :class="{
                            'bg-blue-700 text-white font-black shadow-sm ring-2 ring-blue-300': selectedLetter === l,
                            'bg-blue-100 text-blue-700 font-bold hover:bg-blue-700 hover:text-white': selectedLetter !== l && availableSet[l],
                            'bg-slate-100 text-slate-300 font-normal cursor-not-allowed opacity-50': !availableSet[l]
                        }"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-xs transition">
                    <span x-text="l"></span>
                </button>
            </template>
        </div>
    </div>

    <!-- ── Entries Grid ────────────────────────────────────────────── -->
    <template x-if="paginatedEntries.length > 0">
        <div class="grid gap-3 sm:grid-cols-2">
            <template x-for="(entry, idx) in paginatedEntries" :key="entry.manobo + '_' + idx">
                <article class="cursor-pointer rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                         @click="openEntry(entry)">

                    <div class="flex items-start justify-between gap-3">
                        <p class="flex items-center gap-2 text-lg font-black leading-tight text-blue-700">
                            <span x-text="entry.manobo"></span>
                            <button type="button" x-show="entry.audio" x-cloak
                                    @click.stop="(window.__dictAudio = window.__dictAudio || new Audio()).src = entry.audio; window.__dictAudio.play()"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-blue-700"
                                    :aria-label="'Play recording of ' + entry.manobo" title="Approved recording">
                                <i class="bi bi-volume-up-fill" aria-hidden="true"></i>
                            </button>
                        </p>
                        <span x-show="entry.category"
                              class="flex-shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-semibold bg-blue-100 text-blue-700"
                              x-text="entry.category">
                        </span>
                    </div>

                    <p class="mt-0.5 text-xs italic text-slate-500" x-show="entry.part_of_speech" x-text="entry.part_of_speech"></p>

                    <dl class="mt-3 space-y-1.5">
                        <div class="flex gap-2">
                            <dt class="w-16 flex-shrink-0 text-[11px] font-bold uppercase tracking-wide text-slate-500">
                                <?= e(t('res_manobo.col_english')) ?>
                            </dt>
                            <dd class="text-sm font-semibold text-slate-700" x-text="entry.english || '—'"></dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-16 flex-shrink-0 text-[11px] font-bold uppercase tracking-wide text-slate-500">
                                <?= e(t('res_manobo.col_tagalog')) ?>
                            </dt>
                            <dd class="text-sm font-semibold text-slate-700" x-text="entry.tagalog || '—'"></dd>
                        </div>
                        <div class="flex gap-2" x-show="entry.bisaya">
                            <dt class="w-16 flex-shrink-0 text-[11px] font-bold uppercase tracking-wide text-slate-500">
                                Bisaya
                            </dt>
                            <dd class="text-sm font-semibold text-slate-700" x-text="entry.bisaya || '—'"></dd>
                        </div>
                    </dl>

                    <p x-show="entry.notes" class="mt-3 text-xs font-semibold text-blue-700">
                        <i class="bi bi-info-circle me-1"></i><?= e(t('res_manobo.notes')) ?>
                    </p>
                </article>
            </template>
        </div>
    </template>

    <!-- ── Empty State Notice ──────────────────────────────────────── -->
    <template x-if="paginatedEntries.length === 0">
        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 py-12 text-center">
            <i class="bi bi-search text-3xl text-slate-300"></i>
            <p class="mt-3 text-base font-bold text-slate-700">
                <span x-show="selectedLetter !== 'ALL'">Walang nahanap na salitang Manobo para sa letrang <span class="text-blue-700" x-text="selectedLetter"></span>.</span>
                <span x-show="selectedLetter === 'ALL'">Walang nahanap na salitang tumutugma sa iyong paghahanap.</span>
            </p>
            <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                Subukang pumili ng ibang letra o palitan ang iyong ginamit na keyword.
            </p>
            <button type="button" @click="resetFilters()" class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-blue-700 px-4 py-2 text-xs font-bold text-white transition hover:bg-blue-800">
                <i class="bi bi-arrow-counterclockwise"></i>I-reset ang mga Filter
            </button>
        </div>
    </template>

    <!-- ── Pagination Controls within active letter ────────────────── -->
    <div x-show="totalPages > 1" class="mt-6 flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
        <button type="button" @click="prevPage()" :disabled="currentPage === 1"
                :class="currentPage === 1 ? 'opacity-40 cursor-not-allowed bg-slate-100 text-slate-400' : 'bg-slate-100 text-slate-700 hover:bg-blue-100 hover:text-blue-700'"
                class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition">
            <i class="bi bi-arrow-left me-1"></i>Nakalipas na Pahina
        </button>

        <span class="text-xs font-semibold text-slate-600">
            Pahina <strong x-text="currentPage"></strong> sa <strong x-text="totalPages"></strong>
        </span>

        <button type="button" @click="nextPage()" :disabled="currentPage >= totalPages"
                :class="currentPage >= totalPages ? 'opacity-40 cursor-not-allowed bg-slate-100 text-slate-400' : 'bg-slate-100 text-slate-700 hover:bg-blue-100 hover:text-blue-700'"
                class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition">
            Susunod na Pahina<i class="bi bi-arrow-right ms-1"></i>
        </button>
    </div>

    <!-- ── Detail Modal ────────────────────────────────────────────── -->
    <div x-show="activeEntry" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
         @click.self="activeEntry = null">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" @click.stop x-show="activeEntry" x-cloak>
            <template x-if="activeEntry">
                <div>
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="text-2xl font-black text-blue-700" x-text="activeEntry.manobo"></h2>
                        <button type="button" class="flex-shrink-0 rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                                @click="activeEntry = null" aria-label="<?= e(t('res_manobo.close')) ?>">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <p class="mt-0.5 text-xs italic text-slate-500" x-show="activeEntry.part_of_speech" x-text="activeEntry.part_of_speech"></p>
                    <span class="mt-2 inline-block rounded-full bg-blue-100 px-2.5 py-0.5 text-[11px] font-semibold text-blue-700" x-text="activeEntry.category"></span>

                    <dl class="mt-4 space-y-2 border-t border-slate-100 pt-4">
                        <div>
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500"><?= e(t('res_manobo.col_english')) ?></dt>
                            <dd class="text-base font-semibold text-slate-800" x-text="activeEntry.english || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500"><?= e(t('res_manobo.col_tagalog')) ?></dt>
                            <dd class="text-base font-semibold text-slate-800" x-text="activeEntry.tagalog || '—'"></dd>
                        </div>
                        <div x-show="activeEntry.bisaya">
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Bisaya</dt>
                            <dd class="text-base font-semibold text-slate-800" x-text="activeEntry.bisaya || '—'"></dd>
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

    <p class="mt-6 flex items-start gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs leading-relaxed text-slate-600">
        <i class="bi bi-info-circle mt-0.5 flex-shrink-0 text-slate-500"></i>
        <span><?= e(t('res_manobo.growing_note')) ?></span>
    </p>
</div>

<script>
    function manoboDict(rawEntries, initialQuery, initialCat) {
        var alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
        var availableSet = {};

        (rawEntries || []).forEach(function(e) {
            var word = (e.manobo || '').trim();
            if (word.length > 0) {
                var first = word.charAt(0).toUpperCase();
                availableSet[first] = (availableSet[first] || 0) + 1;
            }
        });

        var firstAvailable = 'A';
        for (var i = 0; i < alphabet.length; i++) {
            if (availableSet[alphabet[i]]) {
                firstAvailable = alphabet[i];
                break;
            }
        }

        var defaultLetter = initialQuery ? 'ALL' : firstAvailable;

        return {
            allEntries: rawEntries || [],
            query: (initialQuery || '').trim(),
            category: (initialCat || '').trim(),
            selectedLetter: defaultLetter,
            defaultLetter: defaultLetter,
            currentPage: 1,
            pageSize: 20,
            activeEntry: null,
            alphabet: alphabet,
            availableSet: availableSet,

            get filteredEntries() {
                var q = (this.query || '').trim().toLowerCase();
                var cat = (this.category || '').trim().toLowerCase();
                var letter = this.selectedLetter;

                return this.allEntries.filter(function(e) {
                    var mWord = (e.manobo || '').trim();
                    var mWordLower = mWord.toLowerCase();

                    // Letter filter
                    if (letter !== 'ALL' && letter !== '') {
                        if (!mWordLower.startsWith(letter.toLowerCase())) {
                            return false;
                        }
                    }

                    // Category filter
                    if (cat !== '' && (e.category || '').trim().toLowerCase() !== cat) {
                        return false;
                    }

                    // Search query filter (prefix prioritized, matches anywhere)
                    if (q !== '') {
                        var inManobo = mWordLower.indexOf(q) !== -1;
                        var inEng = (e.english || '').toLowerCase().indexOf(q) !== -1;
                        var inTag = (e.tagalog || '').toLowerCase().indexOf(q) !== -1;
                        var inBis = (e.bisaya || '').toLowerCase().indexOf(q) !== -1;
                        if (!inManobo && !inEng && !inTag && !inBis) {
                            return false;
                        }
                    }

                    return true;
                }).sort(function(a, b) {
                    var aM = (a.manobo || '').trim().toLowerCase();
                    var bM = (b.manobo || '').trim().toLowerCase();
                    if (q !== '') {
                        var aStartsWith = aM.startsWith(q);
                        var bStartsWith = bM.startsWith(q);
                        if (aStartsWith && !bStartsWith) return -1;
                        if (!aStartsWith && bStartsWith) return 1;
                    }
                    return aM.localeCompare(bM);
                });
            },

            get paginatedEntries() {
                var start = (this.currentPage - 1) * this.pageSize;
                return this.filteredEntries.slice(start, start + this.pageSize);
            },

            get totalPages() {
                return Math.max(1, Math.ceil(this.filteredEntries.length / this.pageSize));
            },

            get availableLettersList() {
                var self = this;
                return this.alphabet.filter(function(l) { return !!self.availableSet[l]; });
            },

            get isPrevLetterDisabled() {
                if (this.selectedLetter === 'ALL') return true;
                var list = this.availableLettersList;
                if (list.length === 0) return true;
                var idx = list.indexOf(this.selectedLetter);
                return idx <= 0;
            },

            get isNextLetterDisabled() {
                if (this.selectedLetter === 'ALL') return true;
                var list = this.availableLettersList;
                if (list.length === 0) return true;
                var idx = list.indexOf(this.selectedLetter);
                return idx < 0 || idx >= list.length - 1;
            },

            selectLetter(letter) {
                this.selectedLetter = letter;
                this.currentPage = 1;
            },

            prevLetter() {
                var list = this.availableLettersList;
                if (list.length === 0) return;
                var idx = list.indexOf(this.selectedLetter);
                if (idx > 0) {
                    this.selectLetter(list[idx - 1]);
                } else if (idx === -1 && list.length > 0) {
                    this.selectLetter(list[0]);
                }
            },

            nextLetter() {
                var list = this.availableLettersList;
                if (list.length === 0) return;
                var idx = list.indexOf(this.selectedLetter);
                if (idx >= 0 && idx < list.length - 1) {
                    this.selectLetter(list[idx + 1]);
                } else if (idx === -1 && list.length > 0) {
                    this.selectLetter(list[0]);
                }
            },

            onSearchChange() {
                this.currentPage = 1;
            },

            resetFilters() {
                this.query = '';
                this.category = '';
                this.selectedLetter = this.defaultLetter;
                this.currentPage = 1;
            },

            prevPage() {
                if (this.currentPage > 1) {
                    this.currentPage--;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            },

            nextPage() {
                if (this.currentPage < this.totalPages) {
                    this.currentPage++;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            },

            openEntry(entry) {
                this.activeEntry = entry;
            }
        };
    }
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
