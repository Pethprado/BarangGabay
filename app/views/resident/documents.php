<?php
/**
 * Resident document requests — Personal Pickup or Digital Soft Copy.
 *
 * Variables: $requests (list), $types (array<string,string>)
 */
$requests = $requests ?? [];
$types    = $types    ?? [];

$statusClass = [
    'pending'    => 'bg-slate-100 text-slate-700 border-slate-200',
    'processing' => 'bg-amber-50 text-amber-800 border-amber-200',
    'ready'      => 'bg-emerald-50 text-emerald-800 border-emerald-200',
    'released'   => 'bg-blue-50 text-blue-800 border-blue-200',
    'rejected'   => 'bg-rose-50 text-rose-800 border-rose-200',
];

ob_start();
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900"><?= e(t('documents.title')) ?></h1>
        <p class="mt-1 text-sm text-slate-500"><?= e(t('documents.subtitle')) ?></p>
    </div>
    <div class="flex items-center gap-2">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
            <i class="bi bi-clock-history"></i>
            <?= count($requests) ?> <?= count($requests) === 1 ? 'Request' : 'Requests' ?>
        </span>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-[380px_1fr]">

    <!-- ── Request form ──────────────────────────────────────────── -->
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:sticky lg:top-4 lg:self-start">
        <div class="mb-4 flex items-center gap-3">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                <i class="bi bi-file-earmark-plus" style="font-size: 1.15rem;"></i>
            </span>
            <div>
                <h2 class="text-base font-bold text-slate-900"><?= e(t('documents.form_title')) ?></h2>
                <p class="text-xs text-slate-500"><?= e(t('documents.form_help')) ?></p>
            </div>
        </div>

        <form method="post" action="<?= e(route('documents')) ?>" id="docRequestForm">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <!-- Document Type Selection -->
            <div class="mb-4">
                <label for="document_type" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-700">
                    <?= e(t('documents.field_type')) ?> <span class="text-red-500">*</span>
                </label>
                <select id="document_type" name="document_type" required
                        class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-medium text-slate-800 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <?php foreach ($types as $key => $label): ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Delivery Method Selector (Cards) -->
            <div class="mb-4">
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-700">
                    <?= e(t('documents.delivery_label')) ?> <span class="text-red-500">*</span>
                </label>
                <p class="mb-2 text-xs text-slate-400"><?= e(t('documents.delivery_help')) ?></p>

                <div class="grid gap-2.5">
                    <!-- Pickup Option -->
                    <label class="delivery-option-card relative flex cursor-pointer items-start gap-3 rounded-xl border-2 border-slate-200 bg-slate-50/50 p-3 transition hover:border-slate-300 hover:bg-white">
                        <input type="radio" name="delivery_method" value="pickup" checked
                               class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <span class="text-sm font-bold text-slate-900"><?= e(t('documents.delivery_pickup')) ?></span>
                                <span class="rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-bold text-slate-700">Counter</span>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-500 leading-normal">
                                <?= e(t('documents.delivery_pickup_desc')) ?>
                            </p>
                        </div>
                    </label>

                    <!-- Digital Option -->
                    <label class="delivery-option-card relative flex cursor-pointer items-start gap-3 rounded-xl border-2 border-slate-200 bg-slate-50/50 p-3 transition hover:border-blue-300 hover:bg-white">
                        <input type="radio" name="delivery_method" value="digital"
                               class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <span class="text-sm font-bold text-slate-900"><?= e(t('documents.delivery_digital')) ?></span>
                                <span class="rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700">Online Soft Copy</span>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-500 leading-normal">
                                <?= e(t('documents.delivery_digital_desc')) ?>
                            </p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Purpose -->
            <div class="mb-4">
                <label for="purpose" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-700">
                    <?= e(t('documents.field_purpose')) ?> <span class="text-red-500">*</span>
                </label>
                <input type="text" id="purpose" name="purpose" required maxlength="255"
                       placeholder="<?= e(t('documents.purpose_ph')) ?>"
                       class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-800 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100">
                <p class="mt-1 text-xs text-slate-400"><?= e(t('documents.purpose_help')) ?></p>
            </div>

            <!-- Additional Notes -->
            <div class="mb-5">
                <label for="notes" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-700">
                    <?= e(t('documents.field_notes')) ?>
                </label>
                <textarea id="notes" name="notes" rows="3" maxlength="2000"
                          placeholder="<?= e(t('documents.notes_ph')) ?>"
                          class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-800 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100"></textarea>
            </div>

            <button type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-700 to-indigo-700 px-4 py-3 text-sm font-bold text-white shadow-md transition hover:from-blue-800 hover:to-indigo-800 hover:shadow-lg active:scale-[0.99]">
                <i class="bi bi-send-fill"></i>
                <span><?= e(t('documents.submit')) ?></span>
            </button>
        </form>

        <div class="mt-5 border-t border-slate-100 pt-4">
            <a href="<?= e(route('feedback')) ?>"
               class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 hover:text-slate-900">
                <i class="bi bi-chat-dots"></i>
                <span><?= e(t('documents.ask_staff')) ?></span>
            </a>
            <p class="mt-1.5 text-center text-[11px] text-slate-400"><?= e(t('documents.ask_staff_help')) ?></p>
        </div>
    </div>

    <!-- ── My requests ───────────────────────────────────────────── -->
    <div>
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900"><?= e(t('documents.mine_title')) ?></h2>
            <?php if ($requests !== []): ?>
            <span class="text-xs text-slate-400"><?= count($requests) ?> na-itala</span>
            <?php endif; ?>
        </div>

        <?php if ($requests === []): ?>
        <div class="rounded-2xl border-2 border-dashed border-slate-200 bg-white p-12 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400">
                <i class="bi bi-file-earmark-text" style="font-size: 1.8rem;"></i>
            </div>
            <h3 class="mt-3 text-sm font-bold text-slate-700"><?= e(t('documents.none')) ?></h3>
            <p class="mt-1 text-xs text-slate-400">Piliin ang kailangang sertipiko sa kaliwang bahagi upang magsimula.</p>
        </div>
        <?php else: ?>

        <div class="space-y-4">
            <?php foreach ($requests as $r):
                $st       = (string) $r['status'];
                $delivery = (string) ($r['delivery_method'] ?? 'pickup');
                $isDigital= $delivery === 'digital';
                $hasFile  = !empty($r['document_file_name']);

                // Status label
                $statusLabel = $isDigital && $st === 'ready'
                    ? t('documents.status_ready_digital')
                    : ($isDigital && $st === 'released' ? t('documents.status_released_digital') : t('documents.status_' . $st));

                $borderHighlight = match ($st) {
                    'ready'    => 'border-emerald-300 ring-1 ring-emerald-100',
                    'released' => 'border-blue-200',
                    'processing' => 'border-amber-200',
                    default    => 'border-slate-200',
                };
            ?>
            <article class="rounded-2xl border bg-white p-5 shadow-sm transition hover:shadow-md <?= $borderHighlight ?>">
                <!-- Header: Title, Reference, Badges -->
                <div class="flex flex-wrap items-start justify-between gap-2.5">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base font-bold text-slate-900">
                                <?= e(\App\Models\DocumentRequest::label((string) $r['document_type'])) ?>
                            </h3>
                            <!-- Delivery Badge -->
                            <?php if ($isDigital): ?>
                            <span class="inline-flex items-center gap-1 rounded-md bg-indigo-50 border border-indigo-200 px-2 py-0.5 text-[11px] font-bold text-indigo-700">
                                <i class="bi bi-file-earmark-arrow-down"></i> <?= e(t('documents.badge_digital')) ?>
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 border border-slate-200 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                                <i class="bi bi-building"></i> <?= e(t('documents.badge_pickup')) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <p class="mt-1 font-mono text-xs text-slate-400">
                            Reference: <strong class="text-slate-600"><?= e((string) $r['reference_no']) ?></strong>
                        </p>
                    </div>

                    <!-- Status Pill -->
                    <div>
                        <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-bold <?= $statusClass[$st] ?? 'bg-slate-100 text-slate-700' ?>">
                            <?php if ($st === 'ready'): ?>
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                            </span>
                            <?php endif; ?>
                            <?= e($statusLabel) ?>
                        </span>
                    </div>
                </div>

                <!-- Purpose & Details -->
                <div class="mt-3 text-sm text-slate-700">
                    <span class="font-semibold text-slate-500 text-xs uppercase tracking-wider"><?= e(t('documents.field_purpose')) ?>:</span>
                    <span class="font-medium text-slate-800"><?= e((string) $r['purpose']) ?></span>
                </div>

                <?php if (!empty($r['notes'])): ?>
                <div class="mt-1 text-xs text-slate-500">
                    <span class="font-semibold text-slate-400">Iyong paalala:</span> <?= e((string) $r['notes']) ?>
                </div>
                <?php endif; ?>

                <!-- Staff Note Box -->
                <?php if (!empty($r['staff_note'])): ?>
                <div class="mt-3.5 rounded-xl border border-amber-200 bg-amber-50/70 p-3 text-xs leading-relaxed text-amber-900">
                    <div class="flex items-start gap-2">
                        <i class="bi bi-chat-quote-fill mt-0.5 text-amber-600"></i>
                        <div>
                            <span class="font-bold text-amber-900">Paalala mula sa Barangay Hall:</span>
                            <p class="mt-0.5 text-amber-800"><?= e((string) $r['staff_note']) ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- SOFT COPY ATTACHMENT CARD (If uploaded by Admin) -->
                <?php if ($hasFile): ?>
                <div class="mt-4 rounded-xl border-2 border-indigo-100 bg-gradient-to-br from-indigo-50/60 to-blue-50/40 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-sm">
                                <?php
                                $ft = strtolower((string) $r['document_file_type']);
                                if (str_contains($ft, 'pdf')) {
                                    echo '<i class="bi bi-file-earmark-pdf" style="font-size:1.35rem;"></i>';
                                } elseif (str_contains($ft, 'image')) {
                                    echo '<i class="bi bi-file-earmark-image" style="font-size:1.35rem;"></i>';
                                } else {
                                    echo '<i class="bi bi-file-earmark-word" style="font-size:1.35rem;"></i>';
                                }
                                ?>
                            </span>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h4 class="truncate text-sm font-bold text-slate-900" title="<?= e((string) $r['document_file_name']) ?>">
                                        <?= e((string) $r['document_file_name']) ?>
                                    </h4>
                                    <span class="rounded bg-indigo-200/80 px-1.5 py-0.2 text-[10px] font-bold uppercase tracking-wider text-indigo-800">Opisyal</span>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    <?= \App\Models\DocumentRequest::formatFileSize((int) $r['document_file_size']) ?>
                                    <?php if (!empty($r['document_uploaded_at'])): ?>
                                    &bull; <?= e(format_datetime((string) $r['document_uploaded_at'])) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <!-- Action Buttons: Preview, Download, Print -->
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Preview button -->
                            <a href="<?= e(route('documents/' . (int) $r['id'] . '/preview')) ?>"
                               target="_blank"
                               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:text-slate-900"
                               title="<?= e(t('documents.preview_btn')) ?>">
                                <i class="bi bi-eye"></i>
                                <span><?= e(t('documents.preview_btn')) ?></span>
                            </a>

                            <!-- Download button -->
                            <a href="<?= e(route('documents/' . (int) $r['id'] . '/download')) ?>"
                               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3.5 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-indigo-700 active:scale-95">
                                <i class="bi bi-download"></i>
                                <span><?= e(t('documents.download_btn')) ?></span>
                            </a>

                            <!-- Print button -->
                            <button type="button"
                                    onclick="printDocument('<?= e(route('documents/' . (int) $r['id'] . '/preview')) ?>')"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-300 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 shadow-sm transition hover:bg-indigo-50">
                                <i class="bi bi-printer"></i>
                                <span><?= e(t('documents.print_btn')) ?></span>
                            </button>
                        </div>
                    </div>
                </div>
                <?php elseif ($isDigital && $st !== 'rejected'): ?>
                <!-- Digital but file not yet uploaded -->
                <div class="mt-3.5 flex items-center gap-2 rounded-xl bg-slate-50 border border-dashed border-slate-200 p-3 text-xs text-slate-500">
                    <i class="bi bi-info-circle text-slate-400"></i>
                    <span>Inihahanda ng kawani ng barangay ang opisyal na digital file. Makakatanggap kayo ng abiso at lalabas dito ang download button kapag handa na.</span>
                </div>
                <?php elseif (!$isDigital && $st === 'ready'): ?>
                <!-- Pickup Ready Instructions -->
                <div class="mt-3.5 rounded-xl border border-emerald-200 bg-emerald-50/70 p-3.5 text-xs leading-relaxed text-emerald-900">
                    <div class="flex items-start gap-2.5">
                        <i class="bi bi-check-circle-fill text-emerald-600 text-base mt-0.5"></i>
                        <div>
                            <strong class="font-bold text-emerald-900">Handa na para sa personal na pagkuha!</strong>
                            <p class="mt-0.5 text-emerald-800">
                                Maaari na ninyong kunin ang inyong dokumento sa Barangay Hall Counter. Magdala ng 1 valid ID at ibigay ang Reference No. <code class="font-bold font-mono text-emerald-950 bg-emerald-100/80 px-1 py-0.5 rounded"><?= e((string) $r['reference_no']) ?></code>.
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Footer Timestamps -->
                <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 text-[11px] text-slate-400">
                    <div>
                        <?= e(t('documents.filed_on')) ?>: <strong><?= e(format_datetime((string) $r['requested_at'])) ?></strong>
                    </div>
                    <?php if (!empty($r['ready_at'])): ?>
                    <div>
                        <?= e(t('documents.ready_on')) ?>: <strong class="text-emerald-700"><?= e(format_datetime((string) $r['ready_at'])) ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($r['released_at'])): ?>
                    <div>
                        Natapos: <strong class="text-blue-700"><?= e(format_datetime((string) $r['released_at'])) ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Hidden iframe for instant printing -->
<iframe id="printFrame" style="display:none;width:0;height:0;border:0;"></iframe>

<script>
function printDocument(url) {
    const frame = document.getElementById('printFrame');
    if (!frame) return;
    frame.src = url;
    frame.onload = function() {
        try {
            frame.contentWindow.focus();
            frame.contentWindow.print();
        } catch (e) {
            // Fallback for popup blockers or cross-origin restrictions
            const win = window.open(url, '_blank');
            if (win) {
                win.focus();
                win.print();
            }
        }
    };
}

// Highlight selected delivery card
document.querySelectorAll('input[name="delivery_method"]').forEach(radio => {
    function updateCardStyles() {
        document.querySelectorAll('.delivery-option-card').forEach(card => {
            const r = card.querySelector('input[type="radio"]');
            if (r && r.checked) {
                card.classList.add('border-blue-600', 'bg-blue-50/40', 'shadow-sm');
                card.classList.remove('border-slate-200', 'bg-slate-50/50');
            } else {
                card.classList.remove('border-blue-600', 'bg-blue-50/40', 'shadow-sm');
                card.classList.add('border-slate-200', 'bg-slate-50/50');
            }
        });
    }
    radio.addEventListener('change', updateCardStyles);
    if (radio.checked) updateCardStyles();
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
