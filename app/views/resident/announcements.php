<?php
/**
 * Resident announcements list.
 * Variables: $announcements, $total, $page, $perPage, $filters
 */
$filters   = $filters   ?? ['category' => '', 'urgency' => '', 'search' => ''];
$total     = $total     ?? 0;
$page      = $page      ?? 1;
$perPage   = $perPage   ?? 10;
$pageTitle = t('nav.announcements');

// Category labels come from the shared categories.* keys so the list filter and
// the cards can never disagree about what a category is called.
$categoryOptions = [
    ''               => t('res_announcements.all_categories'),
    'general'        => t('categories.general'),
    'health'         => t('categories.health'),
    'safety'         => t('categories.safety'),
    'government'     => t('categories.government'),
    'infrastructure' => t('categories.infrastructure'),
    'social'         => t('categories.social'),
];
$urgencyOptions = [
    ''          => t('res_announcements.all_urgency'),
    'normal'    => t('res_announcements.urgency_normal'),
    'important' => t('res_announcements.urgency_important'),
    'urgent'    => t('res_announcements.urgency_urgent'),
];
// Badge styling lives in helpers.php — see category_badge_class().

ob_start();
?>

<!-- ── Page header ────────────────────────────────────────────── -->
<div class="mb-6">
    <p class="text-xs font-bold uppercase tracking-widest text-blue-700"><?= e(t('nav.announcements')) ?></p>
    <div class="mt-1 flex flex-wrap items-baseline gap-3">
        <h1 class="text-2xl font-bold text-slate-900"><?= e(t('res_announcements.title')) ?></h1>
        <span class="rounded-full bg-blue-100 px-3 py-0.5 text-xs font-semibold text-blue-700">
            <?= number_format($total) ?> <?= e(t('res_announcements.results')) ?>
        </span>
    </div>
</div>

<!-- ── Filter bar ─────────────────────────────────────────────── -->
<div x-data="announcementSearch()" x-init="init()"
     class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">

    <!-- Server-side category + urgency (GET form reload) -->
    <form method="get" action="<?= e(route('announcements')) ?>"
          class="flex flex-wrap gap-3 items-end">

        <!-- Category -->
        <div class="flex-1 min-w-[140px]">
            <label class="mb-1 block text-xs font-semibold text-slate-600"><?= e(t('res_announcements.category_label')) ?></label>
            <select name="category"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"
                    onchange="this.form.submit()">
                <?php foreach ($categoryOptions as $val => $lbl): ?>
                <option value="<?= e($val) ?>" <?= $filters['category'] === $val ? 'selected' : '' ?>>
                    <?= e($lbl) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Urgency -->
        <div class="flex-1 min-w-[140px]">
            <label class="mb-1 block text-xs font-semibold text-slate-600"><?= e(t('res_announcements.urgency_label')) ?></label>
            <select name="urgency"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"
                    onchange="this.form.submit()">
                <?php foreach ($urgencyOptions as $val => $lbl): ?>
                <option value="<?= e($val) ?>" <?= $filters['urgency'] === $val ? 'selected' : '' ?>>
                    <?= e($lbl) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Preserve page filters as hidden inputs when JS submits -->
        <input type="hidden" name="search" :value="query">

        <!-- Live search box (Alpine handles AJAX) -->
        <div class="flex-[2] min-w-[200px]">
            <label class="mb-1 block text-xs font-semibold text-slate-600"><?= e(t('common.search')) ?></label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                    <i class="bi bi-search text-sm"></i>
                </span>
                <input type="text"
                       x-model.debounce.400ms="query"
                       @input="onSearch()"
                       placeholder="<?= e(t('res_announcements.search_placeholder')) ?>"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pl-9 pr-10 text-sm text-slate-800 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"
                       value="<?= e($filters['search']) ?>">
                <!-- Spinner -->
                <span x-show="loading"
                      class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                    <svg class="h-4 w-4 animate-spin text-blue-500" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                </span>
                <!-- Clear button -->
                <button type="button" x-show="query"
                        @click="query=''; clearSearch()"
                        class="absolute inset-y-0 right-3 flex items-center text-slate-400 hover:text-slate-600">
                    <i class="bi bi-x-lg text-xs"></i>
                </button>
            </div>
        </div>

        <!-- Reset link -->
        <?php if ($filters['category'] || $filters['urgency'] || $filters['search']): ?>
        <a href="<?= e(route('announcements')) ?>"
           class="flex-shrink-0 self-end rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">
            <i class="bi bi-x me-1"></i><?= e(t('res_announcements.reset')) ?>
        </a>
        <?php endif; ?>
    </form>

    <!-- Active filter chips -->
    <?php if ($filters['category'] || $filters['urgency']): ?>
    <div class="mt-3 flex flex-wrap gap-2">
        <?php if ($filters['category']): ?>
        <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 border border-blue-200 px-3 py-0.5 text-xs font-semibold text-blue-700">
            <?= e($categoryOptions[$filters['category']] ?? $filters['category']) ?>
            <a href="<?= e(route('announcements') . '?' . http_build_query(['urgency' => $filters['urgency'], 'search' => $filters['search']])) ?>"
               class="hover:text-red-500">&times;</a>
        </span>
        <?php endif; ?>
        <?php if ($filters['urgency']): ?>
        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 border border-amber-200 px-3 py-0.5 text-xs font-semibold text-amber-700">
            <?= e($urgencyOptions[$filters['urgency']] ?? $filters['urgency']) ?>
            <a href="<?= e(route('announcements') . '?' . http_build_query(['category' => $filters['category'], 'search' => $filters['search']])) ?>"
               class="hover:text-red-500">&times;</a>
        </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ── Announcement grid ──────────────────────────────────────── -->

<!-- AJAX search results (shown when search query is active) -->
<div x-show="query" x-cloak>
    <!-- Results found -->
    <div x-show="results.length > 0 && !loading">
        <p class="mb-4 text-sm text-slate-500">
            <span x-text="results.length"></span> <?= e(t('res_announcements.results_for')) ?> "<span x-text="query" class="font-semibold text-slate-700"></span>"
        </p>
        <div class="grid gap-4 sm:grid-cols-2">
            <template x-for="(ann, idx) in results" :key="ann.id">
                <article class="card-pop-in flex flex-col overflow-hidden rounded-2xl border border-slate-200 border-l-4 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                         :style="'animation-delay:' + Math.min(idx * 60, 360) + 'ms'"
                         :class="{
                             'border-l-red-500':    ann.urgency === 'urgent',
                             'border-l-amber-400':  ann.urgency === 'important',
                             'border-l-slate-300':  ann.urgency === 'normal' || !ann.urgency
                         }">
                    <template x-if="ann.cover_image_url">
                        <div class="aspect-video w-full overflow-hidden bg-slate-100">
                            <img :src="baseUrl + ann.cover_image_url" :alt="ann.title" class="h-full w-full object-cover">
                        </div>
                    </template>
                    <div class="flex flex-1 flex-col p-5">
                        <div class="mb-2 flex flex-wrap gap-2">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-blue-100 text-blue-700"
                                  x-text="ann.category"></span>
                            <span x-show="ann.urgency === 'urgent'"
                                  class="badge-urgent-pulse inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">
                                <i class="bi bi-exclamation-circle"></i> <?= e(t('res_announcements.urgent_badge')) ?>
                            </span>
                        </div>
                        <h3 class="line-clamp-2 text-base font-bold text-slate-900" x-text="ann.title"></h3>
                        <p class="mt-1 text-xs text-slate-400">
                            <span x-text="formatDate(ann.published_at)"></span>
                            &bull; <span x-text="ann.author_name"></span>
                        </p>
                        <p class="mt-2 line-clamp-2 text-sm text-slate-600" x-text="ann.excerpt"></p>
                        <div class="mt-auto pt-4">
                            <a :href="baseUrl + '/announcements/' + ann.slug"
                               class="inline-flex items-center gap-1 text-sm font-semibold text-blue-700 hover:text-blue-900">
                                <?= e(t('resident_home.read_more')) ?> <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </article>
            </template>
        </div>
    </div>
    <!-- Skeleton loaders while the AJAX search request is in flight -->
    <div x-show="loading" x-cloak class="grid gap-4 sm:grid-cols-2">
        <template x-for="n in 4" :key="n">
            <div class="skeleton-card">
                <div class="skeleton skeleton-image"></div>
                <div class="skeleton skeleton-badge mb-2"></div>
                <div class="skeleton skeleton-title"></div>
                <div class="skeleton skeleton-line"></div>
                <div class="skeleton skeleton-line-sm"></div>
            </div>
        </template>
    </div>
    <!-- No results -->
    <div x-show="results.length === 0 && !loading" class="py-16 text-center">
        <i class="bi bi-search text-4xl text-slate-300"></i>
        <p class="mt-3 text-base font-semibold text-slate-500">
        <p class="mt-1 text-sm text-slate-400">
    </div>
</div>

<!-- Server-rendered paginated results (shown when no search query) -->
<div x-show="!query">
    <?php if (empty($announcements)): ?>
    <div class="py-16 text-center">
        <i class="bi bi-megaphone text-5xl text-slate-200"></i>
        <p class="mt-4 text-base font-semibold text-slate-400">
        <?php if ($filters['category'] || $filters['urgency']): ?>
        <a href="<?= e(route('announcements')) ?>"
           class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-blue-700 hover:underline">
            <i class="bi bi-x-circle"></i> <?= e(t('res_announcements.clear_filters')) ?>
        </a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="grid gap-4 sm:grid-cols-2">
        <?php foreach ($announcements as $__i => $ann):
            $cat    = $ann['category'] ?? 'general';
            $urg    = $ann['urgency']  ?? 'normal';
            $catLbl = $categoryOptions[$cat] ?? ucfirst($cat);
            $catCls = category_badge_class($cat);
            $bdr    = urgency_border_class($urg);
            $excerpt = strip_tags(localised_text($ann, 'body'));
            $excerpt = mb_strlen($excerpt) > 150 ? mb_substr($excerpt, 0, 150) . '…' : $excerpt;
            $fadeDelay = 'fade-up-delay-' . (($__i % 4) + 1);
        ?>
        <article class="fade-up <?= $fadeDelay ?> flex flex-col overflow-hidden rounded-2xl border border-slate-200 border-l-4 <?= $bdr ?> bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">

            <?php if (!empty($ann['cover_image_url'])): ?>
            <div class="aspect-video w-full overflow-hidden bg-slate-100">
                <img src="<?= e(asset($ann['cover_image_url'])) ?>" alt="<?= e(localised_text($ann, 'title')) ?>"
                     class="h-full w-full object-cover">
            </div>
            <?php endif; ?>

            <div class="flex flex-1 flex-col p-5">
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $catCls ?>">
                        <?= e($catLbl) ?>
                    </span>
                    <?php if ($urg === 'urgent'): ?>
                    <span class="badge-urgent-pulse inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">
                        <i class="bi bi-exclamation-circle"></i> <?= e(t('res_announcements.urgent_badge')) ?>
                    </span>
                    <?php elseif ($urg === 'important'): ?>
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                        <i class="bi bi-info-circle"></i> <?= e(t('res_announcements.urgency_important')) ?>
                    </span>
                    <?php endif; ?>
                </div>

                <h3 class="line-clamp-2 text-base font-bold leading-snug text-slate-900">
                    <a href="<?= e(route('announcements/' . $ann['slug'])) ?>"
                       class="transition-colors hover:text-blue-700">
                        <?= e(localised_text($ann, 'title')) ?>
                    </a>
                </h3>
                <p class="mt-1 text-xs text-slate-400">
                    <?= e(date('M j, Y', strtotime($ann['published_at'] ?? 'now'))) ?>
                    &bull; <?= e($ann['author_name'] ?? '') ?>
                </p>
                <p class="mt-2 line-clamp-2 text-sm text-slate-600"><?= e($excerpt) ?></p>

                <div class="mt-auto pt-4">
                    <a href="<?= e(route('announcements/' . $ann['slug'])) ?>"
                       class="inline-flex items-center gap-1 text-sm font-semibold text-blue-700 hover:text-blue-900">
                        <?= e(t('resident_home.read_more')) ?> <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php
    $query = ['category' => $filters['category'], 'urgency' => $filters['urgency'], 'search' => $filters['search']];
    include __DIR__ . '/../shared/_pagination.php';
    ?>
    <?php endif; ?>
</div>

<script>
function announcementSearch() {
    return {
        query:   <?= json_encode($filters['search']) ?>,
        results: [],
        loading: false,
        baseUrl: <?= json_encode(rtrim(base_url(), '/')) ?>,

        init() {
            // If the page loaded with a server-side search, clear x-show logic so
            // the server grid shows; live search only triggers on user typing.
            this.query = '';
        },

        onSearch() {
            if (this.query.length < 2) {
                this.results = [];
                return;
            }
            this.loading = true;
            const params = new URLSearchParams({ q: this.query });
            fetch(this.baseUrl + '/api/announcements/search?' + params, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => { this.results = data; this.loading = false; })
            .catch(() => { this.loading = false; });
        },

        clearSearch() {
            this.results = [];
            this.loading = false;
        },

        formatDate(dateStr) {
            if (!dateStr) return '';
            const d = new Date(dateStr.replace(' ', 'T'));
            return d.toLocaleDateString(<?= json_encode(current_locale() === 'en' ? 'en-PH' : 'fil-PH') ?>, { year: 'numeric', month: 'short', day: 'numeric' });
        }
    };
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
