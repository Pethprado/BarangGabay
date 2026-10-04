<?php
/**
 * Ordinance detail — PDF.js viewer + AI summarize.
 * Variables: $ordinance (array), $pdfUrl (string — absolute URL for PDF.js)
 */

$pdfUrl     = $pdfUrl ?? '';
$hasSummary = !empty($ordinance['ai_summary']);

$statusMeta = [
    'active'   => ['cls' => 'bg-blue-100 text-blue-700', 'label' => t('detail_ui.status_active')],
    'repealed' => ['cls' => 'bg-red-100 text-red-600',         'label' => t('detail_ui.status_repealed')],
    'draft'    => ['cls' => 'bg-amber-100 text-amber-700',     'label' => t('detail_ui.status_draft')],
];
$status = $ordinance['status'] ?? 'active';
$sMeta  = $statusMeta[$status] ?? $statusMeta['active'];

// Escape AI summary for safe JS interpolation
$cachedSummaryJson = json_encode($ordinance['ai_summary'] ?? null, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

ob_start();
?>

<!-- Breadcrumb -->
<nav class="mb-5 flex items-center gap-2 text-xs text-slate-400" aria-label="Breadcrumb">
    <a href="<?= e(route('')) ?>" class="hover:text-amber-700 transition-colors">Home</a>
    <i class="bi bi-chevron-right" style="font-size:.65rem;"></i>
    <a href="<?= e(route('ordinances')) ?>" class="hover:text-amber-700 transition-colors"><?= e(t('nav.ordinances')) ?></a>
    <i class="bi bi-chevron-right" style="font-size:.65rem;"></i>
    <span class="line-clamp-1 max-w-[200px] text-slate-600"><?= e($ordinance['ordinance_no']) ?></span>
</nav>

<!-- Title row -->
<?php
// Title and description follow the header's FIL / EN / MN switch. The
// ordinance number itself is an identifier, never translated.
$ordTitlePick = localised_content($ordinance, 'title');
$ordDescPick  = localised_content($ordinance, 'description');
?>
<?php
/*
 * Author row + headline, shared with events and announcements.
 *
 * The ordinance number takes the eyebrow slot: it is how residents and staff
 * actually refer to an ordinance ("Ordinance 2026-014"), so it belongs above
 * the title as a label rather than beside it as another badge. It is an
 * identifier, so it is never translated.
 */
$__pRow     = $ordinance;
$__pTitle   = $ordTitlePick['text'];
$__pEyebrow = $ordinance['ordinance_no'] ?? null;
$__pImage   = null;                       // the PDF viewer below is the media
$__pStamp   = null;
$__pBadges  = [['label' => $sMeta['label'], 'class' => $sMeta['cls']]];
require __DIR__ . '/../shared/_post-header.php';
?>

<?php
/*
 * Voice reader.
 *
 * It reads the plain-language summary, never the PDF. Reading twenty pages of
 * legal text aloud helps nobody and would cost a fortune to synthesise, so the
 * reader says plainly at the end that it was a summary and the full document
 * is on this page. PostScript owns that rule, and the rule that the summary is
 * only spoken in Filipino — the language AIService writes it in.
 */
$__vrDesc = trim((string) $ordDescPick['text']);
$__vrType = 'ordinance';
$__vrRow  = $ordinance;
require __DIR__ . '/../shared/_voice-reader.php';
?>

<?php $__tnPicks = [$ordTitlePick, $ordDescPick]; $__tnType = 'ordinance'; $__tnId = (int) $ordinance['id']; require __DIR__ . '/../shared/_translation-notice.php'; ?>

<?php if ($__vrDesc !== ''): ?>
<?php /* data-voice-body marks what the voice reader highlights as it reads.
         When the summary is what gets spoken its sentences are simply not
         found here, and nothing is highlighted — which is correct. */ ?>
<div class="post-body" style="margin-bottom:1.5rem;" data-voice-body>
    <p class="whitespace-pre-line"><?= e($ordDescPick['text']) ?></p>
</div>
<?php endif; ?>

<!-- ── Two-column layout ─────────────────────────────────────────────────── -->
<div class="grid gap-6 lg:grid-cols-[1fr_340px]">

    <!-- ── LEFT: PDF.js viewer ──────────────────────────────────────── -->
    <div class="min-w-0"
         x-data="pdfViewer(<?= json_encode($pdfUrl, JSON_HEX_TAG) ?>)"
         x-init="init()">

        <!-- Toolbar -->
        <div class="mb-3 flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-2 shadow-sm">
            <!-- Page navigation -->
            <div class="flex items-center gap-2">
                <button type="button"
                        @click="prevPage()"
                        :disabled="currentPage <= 1 || !loaded"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">
                    <i class="bi bi-chevron-left" style="font-size:.7rem;"></i>
                </button>

                <span class="text-xs font-semibold text-slate-600" x-show="loaded">
                    <span x-text="<?= e(json_encode(t('detail_ui.page_of'))) ?>.replace(':current', currentPage).replace(':total', totalPages)"></span>
                </span>
                <span class="text-xs text-slate-400" x-show="!loaded && !error"><?= e(t('detail_ui.loading_short')) ?></span>
                <span class="text-xs text-red-500" x-show="error" x-text="error"></span>

                <button type="button"
                        @click="nextPage()"
                        :disabled="currentPage >= totalPages || !loaded"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">
                    <i class="bi bi-chevron-right" style="font-size:.7rem;"></i>
                </button>
            </div>

            <!-- Zoom + download -->
            <div class="flex items-center gap-2">
                <button type="button"
                        @click="changeScale(-0.2)"
                        :disabled="!loaded"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50 disabled:opacity-40"
                        title="<?= e(t('detail_ui.zoom_out')) ?>">
                    <i class="bi bi-zoom-out" style="font-size:.75rem;"></i>
                </button>
                <span class="text-xs text-slate-500" x-text="Math.round(scale * 100) + '%'" x-show="loaded"></span>
                <button type="button"
                        @click="changeScale(0.2)"
                        :disabled="!loaded"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50 disabled:opacity-40"
                        title="<?= e(t('detail_ui.zoom_in')) ?>">
                    <i class="bi bi-zoom-in" style="font-size:.75rem;"></i>
                </button>
                <a href="<?= e($pdfUrl) ?>"
                   download
                   class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
                   title="<?= e(t('res_ordinances.download_pdf')) ?>">
                    <i class="bi bi-download"></i>
                </a>
            </div>
        </div>

        <!-- PDF canvas container -->
        <div id="pdf-container"
             class="overflow-auto rounded-2xl border border-slate-200 shadow-sm"
             style="height:600px;background:#525659;">

            <!-- Loading skeleton -->
            <div x-show="!loaded && !error"
                 class="flex h-full flex-col items-center justify-center gap-3">
                <div class="h-10 w-10 animate-spin rounded-full border-4 border-white/20 border-t-white"></div>
                <p class="text-sm font-medium text-white/70"><?= e(t('res_ordinances.loading_document')) ?></p>
            </div>

            <!-- Error state -->
            <div x-show="error && !loaded"
                 class="flex h-full flex-col items-center justify-center gap-3 p-6 text-center">
                <i class="bi bi-file-earmark-x text-4xl text-white/50"></i>
                <p class="text-sm font-medium text-white/70" x-text="error"></p>
                <a href="<?= e($pdfUrl) ?>" target="_blank"
                   class="mt-2 inline-flex items-center gap-1.5 rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">
                    <i class="bi bi-box-arrow-up-right"></i> <?= e(t('detail_ui.open_new_tab')) ?>
                </a>
            </div>

            <!-- The actual canvas (PDF.js renders here) -->
            <canvas id="pdf-canvas"
                    x-show="loaded"
                    class="mx-auto block shadow-lg"
                    style="display:block;margin:1rem auto;"></canvas>
        </div>

    </div><!-- /PDF viewer -->

    <!-- ── RIGHT: metadata + AI summarize ───────────────────────────── -->
    <aside class="space-y-4">

        <!-- Metadata card -->
        <div class="post-card">

            <div class="border-b px-4 py-3" style="border-color:#e8dfc5;background:rgba(232,223,197,.18);">
                <p class="text-xs font-bold uppercase tracking-widest text-amber-800">
                    <?= e(t('detail_ui.document_details')) ?>
                </p>
            </div>

            <ul class="divide-y px-4 py-1" style="divide-color:#e8dfc5;">
                <li class="flex items-start gap-3 py-3">
                    <i class="bi bi-hash mt-0.5 flex-shrink-0 text-amber-600"></i>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Numero</p>
                        <p class="font-mono text-sm font-semibold text-slate-800"><?= e($ordinance['ordinance_no']) ?></p>
                    </div>
                </li>
                <?php if (!empty($ordinance['category'])): ?>
                <li class="flex items-start gap-3 py-3">
                    <i class="bi bi-tag mt-0.5 flex-shrink-0 text-amber-600"></i>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400"><?= e(t('res_ordinances.category_label')) ?></p>
                        <p class="text-sm text-slate-700"><?= e($ordinance['category']) ?></p>
                    </div>
                </li>
                <?php endif; ?>
                <?php if (!empty($ordinance['enacted_date'])): ?>
                <li class="flex items-start gap-3 py-3">
                    <i class="bi bi-calendar2-check mt-0.5 flex-shrink-0 text-amber-600"></i>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400"><?= e(t('res_ordinances.enacted_label')) ?></p>
                        <p class="text-sm text-slate-700"><?= e(date('F j, Y', strtotime($ordinance['enacted_date']))) ?></p>
                    </div>
                </li>
                <?php endif; ?>
                <li class="flex items-start gap-3 py-3">
                    <i class="bi bi-check-circle mt-0.5 flex-shrink-0 text-amber-600"></i>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400"><?= e(t('detail_ui.status_label')) ?></p>
                        <span class="inline-flex items-center rounded-full <?= $sMeta['cls'] ?> px-2.5 py-0.5 text-xs font-semibold">
                            <?= $sMeta['label'] ?>
                        </span>
                    </div>
                </li>
                <?php if (!empty($ordinance['uploader_name'])): ?>
                <li class="flex items-start gap-3 py-3">
                    <i class="bi bi-person mt-0.5 flex-shrink-0 text-amber-600"></i>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400"><?= e(t('res_ordinances.uploaded_by')) ?></p>
                        <p class="text-sm text-slate-700"><?= e($ordinance['uploader_name']) ?></p>
                    </div>
                </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- ── AI Summarize component ────────────────────────────────── -->
        <div x-data="ordinanceSummarize(<?= (int) $ordinance['id'] ?>, <?= $hasSummary ? 'true' : 'false' ?>, <?= $cachedSummaryJson ?>)">

            <!-- Button (hidden once summary is shown) -->
            <button type="button"
                    x-show="!summary && !loading"
                    @click="summarize()"
                    class="flex w-full items-center justify-center gap-2 rounded-2xl border border-blue-300 bg-blue-700 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-300">
                <span style="font-size:1.1rem;">✦</span>
                <?= e(t('detail_ui.summarize_ai')) ?>
            </button>

            <!-- Loading state -->
            <div x-show="loading"
                 class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-6">
                <div class="h-8 w-8 animate-spin rounded-full border-3 border-blue-200 border-t-blue-700"
                     style="border-width:3px;"></div>
                <p class="text-sm font-medium text-blue-800"><?= e(t('res_ordinances.summarising')) ?></p>
                <p class="text-xs text-blue-600"><?= e(t('res_ordinances.summarising_hint')) ?></p>
            </div>

            <!-- Error state -->
            <div x-show="errorMsg && !loading"
                 class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <p class="font-semibold"><?= e(t('res_ordinances.ai_failed')) ?></p>
                <p class="mt-1 text-xs" x-text="errorMsg"></p>
                <?php /* Hidden when the server says another attempt cannot
                         succeed. The label was also a hardcoded Filipino
                         string, so an English or Manobo reader got one
                         untranslated word in the middle of the message. */ ?>
                <button type="button"
                        @click="errorMsg = null"
                        x-show="retryable"
                        class="mt-2 text-xs font-semibold text-red-700 underline hover:no-underline">
                    <?= e(t('res_ordinances.try_again')) ?>
                </button>
            </div>

            <!-- Summary callout (fade in) -->
            <div x-show="summary"
                 x-transition:enter="transition ease-out duration-400"
                 x-transition:enter-start="opacity-0 translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="rounded-2xl border border-blue-200 bg-blue-50 p-4 shadow-sm">

                <!-- Header -->
                <div class="mb-3 flex items-center gap-2">
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-blue-700 text-white text-sm">✦</span>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-blue-800"><?= e(t('res_ordinances.ai_summary_title')) ?></p>
                        <p class="text-[10px] text-blue-600"
                           x-text="cached ? 'Nakaraang buod (cached)' : 'Bagong na-generate'"></p>
                    </div>
                </div>

                <!-- Summary text — nl2br-style rendering -->
                <div class="text-sm leading-relaxed text-slate-700"
                     x-html="formattedSummary()"></div>

                <!-- Regenerate link (small) -->
                <button type="button"
                        x-show="cached"
                        @click="summary = null; cached = false; summarize()"
                        class="mt-3 inline-flex items-center gap-1 text-[10px] font-semibold text-blue-700 hover:underline">
                    <i class="bi bi-arrow-clockwise"></i> I-refresh
                </button>
            </div>

            <!-- "Mayroon ka bang tanong?" link -->
            <button type="button"
                    x-show="summary"
                    x-transition:enter="transition ease-out duration-300 delay-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    @click="openChat()"
                    class="flex w-full items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50 hover:border-slate-300">
                <i class="bi bi-chat-dots"></i>
                Mayroon ka bang tanong?
            </button>

        </div><!-- /AI summarize -->

        <?php
        // Where the PDF came from, when it was pulled from a link. See the
        // note in the announcement detail view.
        $__seRow = $ordinance;
        require __DIR__ . '/../shared/_source-embed.php';
        ?>


        <!-- Back link -->
        <a href="<?= e(route('ordinances')) ?>"
           class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50">
            <i class="bi bi-arrow-left"></i> <?= e(t('detail_ui.back_to_list')) ?>
        </a>

    </aside>

</div><!-- /grid -->

<!-- ── PDF.js from CDN ───────────────────────────────────────────────────── -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
// ── PDF viewer Alpine component ──────────────────────────────────────────
function pdfViewer(pdfUrl) {
    return {
        pdfUrl:      pdfUrl,
        pdf:         null,
        currentPage: 1,
        totalPages:  0,
        scale:       1.4,
        loaded:      false,
        error:       null,
        rendering:   false,

        async init() {
            if (!pdfUrl) {
                this.error = 'Hindi available ang PDF file.';
                return;
            }
            try {
                pdfjsLib.GlobalWorkerOptions.workerSrc =
                    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

                const loadingTask = pdfjsLib.getDocument({ url: pdfUrl });
                this.pdf        = await loadingTask.promise;
                this.totalPages = this.pdf.numPages;
                await this.renderPage(1);
                this.loaded = true;
            } catch (e) {
                this.error = <?= json_encode(t('detail_ui.pdf_load_failed')) ?>;
            }
        },

        async renderPage(num) {
            if (this.rendering || !this.pdf) return;
            this.rendering = true;
            try {
                const page     = await this.pdf.getPage(num);
                // Fit to container width
                const container    = document.getElementById('pdf-container');
                const containerW   = (container?.offsetWidth || 700) - 32;
                const vpRaw        = page.getViewport({ scale: 1 });
                const autoScale    = containerW / vpRaw.width;
                const finalScale   = Math.min(Math.max(this.scale * autoScale / 1.4, 0.5), 3.0);
                const viewport     = page.getViewport({ scale: finalScale });

                const canvas  = document.getElementById('pdf-canvas');
                const ctx     = canvas.getContext('2d');
                canvas.height = viewport.height;
                canvas.width  = viewport.width;

                await page.render({ canvasContext: ctx, viewport }).promise;
            } finally {
                this.rendering = false;
            }
        },

        async prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                await this.renderPage(this.currentPage);
                document.getElementById('pdf-container').scrollTop = 0;
            }
        },

        async nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                await this.renderPage(this.currentPage);
                document.getElementById('pdf-container').scrollTop = 0;
            }
        },

        async changeScale(delta) {
            this.scale = Math.min(Math.max(this.scale + delta, 0.5), 3.0);
            await this.renderPage(this.currentPage);
        },
    };
}

// ── AI summarize Alpine component ────────────────────────────────────────
function ordinanceSummarize(ordinanceId, hasCachedSummary, cachedText) {
    const summarizeUrl = '<?= e(route("api/ai/summarize")) ?>';
    return {
        loading:   false,
        summary:   hasCachedSummary ? cachedText : null,
        cached:    hasCachedSummary,
        errorMsg:  null,
        retryable: true,

        async summarize() {
            if (this.loading) return;
            this.loading  = true;
            this.errorMsg = null;

            try {
                const fd = new FormData();
                fd.append('ordinance_id', ordinanceId);
                fd.append('csrf_token',   window.BarangGabay.csrfToken);

                const res  = await fetch(summarizeUrl, { method: 'POST', body: fd });
                const data = await res.json();

                if (data.summary) {
                    this.summary = data.summary;
                    this.cached  = data.cached || false;

                    /* There was no summary when this page rendered, so the
                       voice reader was set up to read the description. Hand it
                       the summary now — it re-prepares the text through the
                       same server-side code the page used, rather than this
                       view growing its own copy of that logic.

                       Filipino only: that is the language the summary is
                       written in, and no cached MP3 can exist for text that was
                       generated seconds ago, so this updates the device-speech
                       track. The provider version arrives next time staff save
                       the ordinance. */
                    document.dispatchEvent(new CustomEvent('voice-reader:body', {
                        detail: { text: this.summary, locale: 'fil' },
                    }));
                } else {
                    this.errorMsg = data.error
                        || <?= json_encode(t('ai_summary.err_unknown')) ?>;
                    /* The server says whether another attempt can succeed.
                       A zero credit balance cannot be retried away, so the
                       button is hidden rather than left there failing. */
                    this.retryable = data.retryable !== false;
                }
            } catch {
                this.errorMsg  = 'May problema sa koneksyon. Pakisuriin ang internet at subukan muli.';
                this.retryable = true;
            } finally {
                this.loading = false;
            }
        },

        /** Convert newlines to <br> tags for safe HTML display. */
        formattedSummary() {
            if (!this.summary) return '';
            return this.summary
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\n/g, '<br>');
        },

        openChat() {
            const title   = <?= json_encode($ordinance['title'], JSON_HEX_TAG) ?>;
            const prefill = 'Tungkol sa ordinansa na "' + title + '": ';
            document.dispatchEvent(new CustomEvent('ai-chat:open', { detail: { prefill } }));
        },
    };
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
