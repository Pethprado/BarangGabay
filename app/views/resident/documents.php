<?php
/**
 * Resident document requests — ask the barangay to prepare a clearance or
 * certificate, and see where each request has got to.
 *
 * Variables: $requests (list), $types (array<string,string>)
 *
 * The status list is shown as a trail rather than a single word, because the
 * question a resident actually has is "can I go and collect it yet" — and
 * "processing" only answers that in relation to what comes next.
 */
$requests = $requests ?? [];
$types    = $types    ?? [];

$statusClass = [
    'pending'    => 'bg-slate-100 text-slate-700',
    'processing' => 'bg-amber-100 text-amber-700',
    'ready'      => 'bg-green-100 text-green-700',
    'released'   => 'bg-blue-100 text-blue-700',
    'rejected'   => 'bg-red-100 text-red-700',
];

ob_start();
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(t('documents.title')) ?></h1>
    <p class="mt-1 text-sm text-slate-500"><?= e(t('documents.subtitle')) ?></p>
</div>

<div class="grid gap-6 lg:grid-cols-[360px_1fr]">

    <!-- ── Request form ──────────────────────────────────────────── -->
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-1 text-base font-bold text-slate-900"><?= e(t('documents.form_title')) ?></h2>
        <p class="mb-4 text-xs leading-relaxed text-slate-500"><?= e(t('documents.form_help')) ?></p>

        <form method="post" action="<?= e(route('documents')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <div class="mb-3">
                <label for="document_type" class="mb-1 block text-xs font-semibold text-slate-700">
                    <?= e(t('documents.field_type')) ?>
                </label>
                <select id="document_type" name="document_type" required
                        class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-800">
                    <?php foreach ($types as $key => $label): ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="purpose" class="mb-1 block text-xs font-semibold text-slate-700">
                    <?= e(t('documents.field_purpose')) ?>
                </label>
                <input type="text" id="purpose" name="purpose" required maxlength="255"
                       placeholder="<?= e(t('documents.purpose_ph')) ?>"
                       class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-800">
                <p class="mt-1 text-xs text-slate-400"><?= e(t('documents.purpose_help')) ?></p>
            </div>

            <div class="mb-4">
                <label for="notes" class="mb-1 block text-xs font-semibold text-slate-700">
                    <?= e(t('documents.field_notes')) ?>
                </label>
                <textarea id="notes" name="notes" rows="3" maxlength="2000"
                          placeholder="<?= e(t('documents.notes_ph')) ?>"
                          class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-800"></textarea>
            </div>

            <button type="submit"
                    class="w-full rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">
                <i class="bi bi-send me-1"></i><?= e(t('documents.submit')) ?>
            </button>
        </form>

        <p class="mt-4 border-t border-slate-100 pt-3 text-xs leading-relaxed text-slate-400">
            <i class="bi bi-info-circle me-1"></i><?= e(t('documents.pickup_note')) ?>
        </p>

        <?php /* A form cannot cover every case — an unusual document, a
                 correction, a question about requirements. The existing
                 two-way feedback thread is where a resident reaches a person,
                 so it is offered here rather than leaving them to find it. */ ?>
        <a href="<?= e(route('feedback')) ?>"
           class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            <i class="bi bi-chat-dots"></i> <?= e(t('documents.ask_staff')) ?>
        </a>
        <p class="mt-1 text-center text-xs text-slate-400"><?= e(t('documents.ask_staff_help')) ?></p>
    </div>

    <!-- ── My requests ───────────────────────────────────────────── -->
    <div>
        <h2 class="mb-3 text-base font-bold text-slate-900"><?= e(t('documents.mine_title')) ?></h2>

        <?php if ($requests === []): ?>
        <div class="rounded-2xl border border-dashed border-slate-300 py-14 text-center">
            <i class="bi bi-file-earmark-text text-4xl text-slate-300"></i>
            <p class="mt-3 text-sm font-semibold text-slate-500"><?= e(t('documents.none')) ?></p>
        </div>
        <?php else: ?>

        <div class="space-y-3">
            <?php foreach ($requests as $r):
                $st  = (string) $r['status'];
                $cls = $statusClass[$st] ?? 'bg-slate-100 text-slate-700';
            ?>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-slate-900">
                            <?= e(\App\Models\DocumentRequest::label((string) $r['document_type'])) ?>
                        </h3>
                        <p class="mt-0.5 font-mono text-xs text-slate-400"><?= e((string) $r['reference_no']) ?></p>
                    </div>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $cls ?>">
                        <?= e(t('documents.status_' . $st)) ?>
                    </span>
                </div>

                <p class="mt-2 text-sm text-slate-600">
                    <span class="font-semibold text-slate-500"><?= e(t('documents.field_purpose')) ?>:</span>
                    <?= e((string) $r['purpose']) ?>
                </p>

                <?php if (!empty($r['staff_note'])): ?>
                <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs leading-relaxed text-amber-800">
                    <i class="bi bi-chat-left-text me-1"></i><?= e((string) $r['staff_note']) ?>
                </p>
                <?php endif; ?>

                <p class="mt-3 text-xs text-slate-400">
                    <?= e(t('documents.filed_on')) ?>
                    <?= e(format_datetime((string) $r['requested_at'])) ?>
                    <?php if (!empty($r['ready_at'])): ?>
                    &bull; <?= e(t('documents.ready_on')) ?> <?= e(format_datetime((string) $r['ready_at'])) ?>
                    <?php endif; ?>
                </p>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
