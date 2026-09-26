<?php
/**
 * Resident ordinances (policy library) listing.
 * Variables: $ordinances (array), $categories (array), $search (string), $category (string)
 */
$ordinances = $ordinances ?? [];
$categories = $categories ?? [];
$search     = $search     ?? '';
$category   = $category   ?? '';

$statusMeta = [
    'active'   => ['cls' => 'bg-blue-100 text-blue-700', 'label' => 'Aktibo'],
    'repealed' => ['cls' => 'bg-red-100 text-red-600',         'label' => 'Na-repeal'],
    'draft'    => ['cls' => 'bg-amber-100 text-amber-700',     'label' => 'Draft'],
];

ob_start();
?>

<!-- Page header -->
<div class="mb-6">
    <p class="text-xs font-bold uppercase tracking-widest text-amber-700"><?= e(t('nav.ordinances')) ?></p>
    <h1 class="mt-0.5 text-2xl font-bold text-slate-900 sm:text-3xl"><?= e(t('res_ordinances.title')) ?></h1>
    <p class="mt-1 text-sm text-slate-500">
        <?= e(t('res_ordinances.subtitle')) ?>
    </p>
</div>

<!-- ── Search + filter bar ───────────────────────────────────────────────── -->
<form method="get" action="<?= e(route('ordinances')) ?>" class="mb-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">

        <!-- Search input -->
        <div class="flex-1">
            <label for="search" class="mb-1 block text-xs font-semibold text-slate-500"><?= e(t('res_ordinances.search_label')) ?></label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                    <i class="bi bi-search" style="font-size:.8rem;"></i>
                </span>
                <input type="text"
                       id="search"
                       name="search"
                       value="<?= e($search) ?>"
                       placeholder="<?= e(t('res_ordinances.search_placeholder')) ?>"
                       class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-9 pr-4 text-sm text-slate-700 shadow-sm placeholder-slate-400 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100">
            </div>
        </div>

        <!-- Category filter -->
        <?php if ($categories): ?>
        <div class="sm:w-52">
            <label for="category" class="mb-1 block text-xs font-semibold text-slate-500"><?= e(t('res_ordinances.category_label')) ?></label>
            <select id="category"
                    name="category"
                    onchange="this.form.submit()"
                    class="w-full rounded-xl border border-slate-200 bg-white py-2.5 px-3 text-sm text-slate-700 shadow-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100">
                <option value=""><?= e(t('res_ordinances.all_categories')) ?></option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                    <?= e($cat) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <!-- Search button -->
        <button type="submit"
                class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-300">
            <i class="bi bi-search"></i> Hanapin
        </button>

        <!-- Clear filter -->
        <?php if ($search || $category): ?>
        <a href="<?= e(route('ordinances')) ?>"
           class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-500 shadow-sm transition hover:bg-slate-50">
            <i class="bi bi-x-circle"></i> I-clear
        </a>
        <?php endif; ?>

    </div>
</form>

<!-- Result count / active filter chips -->
<div class="mb-5 flex flex-wrap items-center gap-2 text-sm text-slate-500">
    <span>
        <strong class="text-slate-700"><?= count($ordinances) ?></strong>
        <?= e(t('res_ordinances.found_suffix')) ?><?= ($search || $category) ? ' ' . e(t('res_ordinances.found_for')) : '' ?>
    </span>
    <?php if ($search): ?>
    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-0.5 text-xs font-semibold text-amber-700">
        "<?= e($search) ?>"
        <a href="<?= e(route('ordinances') . ($category ? '?category=' . urlencode($category) : '')) ?>"
           class="ml-0.5 hover:text-amber-900">×</a>
    </span>
    <?php endif; ?>
    <?php if ($category): ?>
    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-0.5 text-xs font-semibold text-slate-600">
        <?= e($category) ?>
        <a href="<?= e(route('ordinances') . ($search ? '?search=' . urlencode($search) : '')) ?>"
           class="ml-0.5 hover:text-slate-900">×</a>
    </span>
    <?php endif; ?>
</div>

<!-- ── Ordinance cards + AI Simplify modal (Alpine.js) ───────────────────── -->
<div x-data="ordinanceAI()" @keydown.escape.window="closeModal()">

<?php if (empty($ordinances)): ?>

<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 py-16 text-center">
    <i class="bi bi-file-earmark-x text-4xl text-slate-300"></i>
    <p class="mt-3 text-sm font-medium text-slate-500"><?= e(t('res_ordinances.empty')) ?></p>
    <?php if ($search || $category): ?>
    <a href="<?= e(route('ordinances')) ?>"
       class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-amber-700 hover:underline">
        <i class="bi bi-arrow-left"></i> <?= e(t('res_ordinances.view_all')) ?>
    </a>
    <?php endif; ?>
</div>

<?php else: ?>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach ($ordinances as $ord):
        $status  = $ord['status'] ?? 'active';
        $sMeta   = $statusMeta[$status] ?? $statusMeta['active'];
        $hasSumm = !empty($ord['ai_summary']);
        $ordTitle = addslashes((string) ($ord['title'] ?? ''));
    ?>
    <!-- Document card -->
    <article class="group relative flex flex-col overflow-hidden rounded-2xl border shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
             style="background:linear-gradient(160deg,#fffef7 0%,#fef9ec 100%);border-color:#e8dfc5;">

        <!-- Top strip: status + AI badge -->
        <div class="flex items-center justify-between border-b px-4 py-2.5" style="border-color:#e8dfc5;background:rgba(232,223,197,.18);">
            <span class="inline-flex items-center gap-1 rounded-full <?= $sMeta['cls'] ?> px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider">
                <?= $sMeta['label'] ?>
            </span>
            <?php if ($hasSumm): ?>
            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 border border-blue-200 px-2 py-0.5 text-[10px] font-semibold text-blue-600"
                  title="<?= e(t('res_ordinances.has_ai_summary')) ?>">
                ✦ AI Buod
            </span>
            <?php endif; ?>
        </div>

        <div class="flex flex-1 gap-3 p-4">
            <!-- Document icon column -->
            <div class="flex-shrink-0">
                <div class="flex h-12 w-10 flex-col items-center justify-between overflow-hidden rounded-lg border shadow-sm"
                     style="background:#fff8e7;border-color:#d4c48a;">
                    <div class="flex flex-1 items-center justify-center pt-2">
                        <i class="bi bi-file-earmark-text" style="font-size:1.3rem;color:var(--tw-amber-700);"></i>
                    </div>
                    <div class="w-full py-0.5 text-center text-[8px] font-bold uppercase tracking-widest"
                         style="background:#d4c48a;color:#78350f;">
                        PDF
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex flex-1 flex-col min-w-0">
                <!-- Ordinance number stamp -->
                <p class="mb-1.5 inline-block self-start rounded border px-1.5 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-wider"
                   style="background:#fef3c7;border-color:#d4c48a;color:#92400e;">
                    <?= e($ord['ordinance_no']) ?>
                </p>

                <!-- Title -->
                <h2 class="line-clamp-2 text-sm font-bold leading-snug text-slate-900">
                    <a href="<?= e(route('ordinances/' . $ord['id'])) ?>"
                       class="transition-colors hover:text-amber-700">
                        <?= e(localised_text($ord, 'title')) ?>
                    </a>
                </h2>

                <!-- Meta row: category + enacted date -->
                <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1">
                    <?php if (!empty($ord['category'])): ?>
                    <span class="inline-flex items-center gap-1 text-xs text-slate-500">
                        <i class="bi bi-tag" style="font-size:.65rem;"></i>
                        <?= e($ord['category']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($ord['enacted_date'])): ?>
                    <span class="inline-flex items-center gap-1 text-xs text-slate-500">
                        <i class="bi bi-calendar2" style="font-size:.65rem;"></i>
                        <?= e(date('M j, Y', strtotime($ord['enacted_date']))) ?>
                    </span>
                    <?php endif; ?>
                </div>

                <!-- Description excerpt -->
                <?php if (!empty($ord['description'])): ?>
                <p class="mt-2 line-clamp-2 text-xs leading-relaxed text-slate-500">
                    <?= e(mb_substr(strip_tags(localised_text($ord, 'description')), 0, 100)) ?>…
                </p>
                <?php endif; ?>

                <!-- CTA buttons -->
                <div class="mt-auto pt-3 flex flex-wrap gap-2">
                    <!-- View full detail -->
                    <a href="<?= e(route('ordinances/' . $ord['id'])) ?>"
                       class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition"
                       style="background:#fef3c7;color:#92400e;border:1px solid #d4c48a;">
                        <i class="bi bi-eye"></i> <?= e(t('res_ordinances.view')) ?>
                    </a>

                    <!-- ✦ I-simplify — opens the AI modal -->
                    <button type="button"
                            @click="openModal(<?= (int) $ord['id'] ?>, '<?= e($ordTitle) ?>')"
                            class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition"
                            style="background:#ecfdf5;color:#065f46;border:1px solid #6ee7b7;">
                        <i class="bi bi-stars"></i>
                        <?= e($hasSumm ? t('res_ordinances.view_summary') : t('res_ordinances.simplify')) ?>
                    </button>
                </div>
            </div>
        </div>
    </article>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<!-- ── AI Simplify Modal ───────────────────────────────────────────────────── -->
<div x-show="showModal"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
     @click.self="closeModal()"
     style="display:none;">

    <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100">

        <!-- Modal header -->
        <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
            <div>
                <h3 class="text-base font-bold text-slate-900">Simpleng Paliwanag:</h3>
                <p class="mt-0.5 text-xs text-slate-500 line-clamp-1" x-text="ordinanceTitle"></p>
            </div>
            <button @click="closeModal()"
                    class="ml-3 flex-shrink-0 rounded-full p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        <!-- Modal body -->
        <div class="px-6 py-5">

            <!-- Loading state -->
            <div x-show="loading" class="flex flex-col items-center justify-center py-10 gap-3">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-blue-200 border-t-blue-600"></div>
                <p class="text-sm text-slate-500"><?= e(t('res_ordinances.ai_working')) ?></p>
            </div>

            <!-- Error state -->
            <div x-show="error && !loading" class="rounded-xl bg-red-50 border border-red-200 p-4">
                <p class="text-sm font-medium text-red-700 flex items-center gap-2">
                    <i class="bi bi-exclamation-circle"></i>
                    <span x-text="error"></span>
                </p>

                <?php /* Shown only when retrying can actually work. It used
                         to appear for every failure, including the one that
                         had been failing for nine days straight. */ ?>
                <button @click="fetchSummary()" x-show="retryable"
                        class="mt-2 text-xs font-semibold text-red-700 hover:underline">
                    <?= e(t('res_ordinances.try_again')) ?>
                </button>

                <?php /* Staff only — the real API message, so whoever can
                         act on it does not have to open a log file. */ ?>
                <?php if (in_array($_SESSION['role'] ?? '', ['staff', 'admin', 'superadmin'], true)): ?>
                <p x-show="detail" class="mt-2 text-[11px] leading-relaxed text-red-800" x-text="detail"></p>
                <?php endif; ?>
            </div>

            <?php /* The ordinance's own words, when the AI cannot restate
                     them. Not a summary and never labelled as one — the
                     reader opened this to understand the ordinance, and the
                     barangay's own description is still the better answer
                     than an empty box. */ ?>
            <div x-show="fallback && !loading" class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-500"
                   x-text="fallbackLabel"></p>
                <p class="whitespace-pre-wrap text-sm leading-relaxed text-slate-700" x-text="fallback"></p>
            </div>

            <!-- Summary -->
            <div x-show="summary && !loading" class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-blue-700">
                        <i class="bi bi-stars"></i> BarangGabay AI
                    </span>
                    <span x-show="cached" class="text-[10px] text-slate-400 font-medium"><?= e(t('res_ordinances.from_cache')) ?></span>
                </div>
                <p class="whitespace-pre-wrap text-sm leading-relaxed text-slate-700" x-text="summary"></p>
            </div>

        </div>

        <!-- Modal footer -->
        <div x-show="summary && !loading" class="flex items-center justify-between border-t border-slate-100 px-6 py-3">
            <a :href="detailUrl" class="text-xs font-semibold text-amber-700 hover:underline">
                <i class="bi bi-eye me-1"></i> <?= e(t('res_ordinances.view_full')) ?>
            </a>
            <button @click="copy()"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-blue-300 bg-blue-50 px-4 py-2 text-xs font-bold text-blue-800 transition hover:bg-blue-100">
                <i class="bi" :class="copied ? 'bi-check-lg' : 'bi-copy'"></i>
                <span x-text="copied ? copiedLabel : copyLabel"></span>
            </button>
        </div>

    </div>
</div>

</div><!-- /Alpine root -->

<script>
function ordinanceAI() {
    return {
        showModal:      false,
        loading:        false,
        summary:        '',
        error:          '',
        cached:         false,
        copied:         false,
        // Seeded from PHP so the copy button follows the active locale.
        copyLabel:      <?= json_encode(t('res_ordinances.copy')) ?>,
        copiedLabel:    <?= json_encode(t('res_ordinances.copied')) ?>,
        ordinanceTitle: '',
        ordinanceId:    0,
        detailUrl:      '',

        openModal(id, title) {
            this.ordinanceId    = id;
            this.ordinanceTitle = title;
            this.summary        = '';
            this.error          = '';
            this.retryable      = true;
            this.fallback       = '';
            this.fallbackLabel  = '';
            this.detail         = '';
            this.cached         = false;
            this.copied         = false;
            this.detailUrl      = (window.BarangGabay?.baseUrl || '').replace(/\/$/, '') + '/ordinances/' + id;
            this.showModal      = true;
            this.fetchSummary();
        },

        closeModal() {
            this.showModal = false;
        },

        async fetchSummary() {
            this.loading = true;
            this.error   = '';
            try {
                const base = (window.BarangGabay?.baseUrl || '').replace(/\/$/, '');
                const res  = await fetch(base + '/api/ai/summarize', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body:    'csrf_token=' + encodeURIComponent(window.BarangGabay?.csrfToken || '')
                           + '&ordinance_id=' + this.ordinanceId,
                });
                const data = await res.json();
                if (data.summary) {
                    this.summary = data.summary;
                    this.cached  = !!data.cached;
                } else {
                    this.error = data.error || <?= json_encode(t('res_ordinances.err_summary')) ?>;
                    /* Only offer a retry the server says can succeed. An
                       empty credit balance is not fixed by tapping again,
                       and a button that promises it will be is worse than
                       no button. */
                    this.retryable     = data.retryable !== false;
                    this.fallback      = data.fallback || '';
                    this.fallbackLabel = data.fallback_label || '';
                    this.detail        = data.detail || '';
                }
            } catch (_) {
                // The fetch itself failed — that IS worth retrying.
                this.error     = <?= json_encode(t('res_ordinances.err_network')) ?>;
                this.retryable = true;
            }
            this.loading = false;
        },

        async copy() {
            if (!this.summary) return;
            try {
                await navigator.clipboard.writeText(this.summary);
            } catch (_) {
                const ta = document.createElement('textarea');
                ta.value = this.summary;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            }
            this.copied = true;
            setTimeout(() => { this.copied = false; }, 2000);
        },
    };
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';