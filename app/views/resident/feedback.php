<?php
/**
 * Resident feedback / messaging page — real two-way threads.
 * Variables: $feedbacks (array), each with an embedded 'messages' array
 *            (see FeedbackController::index()).
 */
$feedbacks = $feedbacks ?? [];
$myUserId  = (int) ($_SESSION['user_id'] ?? 0);

ob_start();
?>

<!-- Page header -->
<div class="mb-6">
    <p class="text-xs font-bold uppercase tracking-widest text-blue-700"><?= e(t('res_feedback.eyebrow')) ?></p>
    <h1 class="mt-0.5 text-2xl font-bold text-slate-900 sm:text-3xl"><?= e(t('res_feedback.title')) ?></h1>
    <p class="mt-1 text-sm text-slate-500">
        <?= e(t('res_feedback.subtitle')) ?>
    </p>
</div>

<!-- ── Start a new thread ────────────────────────────────────────────────── -->
<div class="mb-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="mb-4 text-base font-bold text-slate-900 flex items-center gap-2">
        <i class="bi bi-send text-blue-600"></i> <?= e(t('res_feedback.new_title')) ?>
    </h2>

    <form method="post" action="<?= e(route('feedback')) ?>" x-data="{ count: 0, max: 1000 }">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="mb-3">
            <label for="message" class="mb-1.5 block text-sm font-semibold text-slate-700">
                <?= e(t('res_feedback.message_label')) ?> <span class="text-red-500">*</span>
            </label>
            <textarea id="message"
                      name="message"
                      rows="4"
                      required
                      minlength="5"
                      maxlength="1000"
                      @input="count = $el.value.length"
                      placeholder="<?= e(t('res_feedback.placeholder')) ?>"
                      class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm placeholder-slate-400 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100 resize-none"></textarea>
            <p class="mt-1 text-right text-xs text-slate-400">
                <span x-text="count"></span> / <span x-text="max"></span>
            </p>
        </div>

        <button type="submit"
                class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-300">
            <i class="bi bi-send"></i> <?= e(t('res_feedback.send')) ?>
        </button>
    </form>
</div>

<!-- ── Threads ────────────────────────────────────────────────────────────── -->
<div>
    <h2 class="mb-4 text-base font-bold text-slate-900 flex items-center gap-2">
        <i class="bi bi-chat-dots text-slate-500"></i>
        <?= e(t('res_feedback.history_title')) ?>
        <span class="ml-1 inline-flex items-center justify-center min-w-[1.5rem] h-6 rounded-full bg-slate-100 text-xs font-bold text-slate-600">
            <?= count($feedbacks) ?>
        </span>
    </h2>

    <?php if (empty($feedbacks)): ?>
    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 py-14 text-center">
        <i class="bi bi-chat-square text-4xl text-slate-300"></i>
        <p class="mt-3 text-sm font-medium text-slate-500"><?= e(t('res_feedback.empty')) ?></p>
        <p class="mt-1 text-xs text-slate-400"><?= e(t('res_feedback.empty_hint')) ?></p>
    </div>
    <?php else: ?>

    <div class="flex flex-col gap-4">
        <?php foreach ($feedbacks as $__i => $fb):
            $messages   = $fb['messages'] ?? [];
            $firstMsg   = $messages[0] ?? null;
            $hasReply   = (int) ($fb['message_count'] ?? 0) > 1;
        ?>
        <?php // The dashboard widget deep-links here as #thread-{id}. scroll-margin
              // keeps the sticky navbar off the thread it jumps to, and a linked
              // thread opens itself instead of arriving collapsed. ?>
        <div id="thread-<?= (int) $fb['id'] ?>"
             style="scroll-margin-top:6rem;"
             class="rounded-2xl border <?= $hasReply ? 'border-blue-200' : 'border-slate-200' ?> bg-white shadow-sm overflow-hidden"
             x-data="{ open: <?= $__i === 0 ? 'true' : 'false' ?> }"
             x-init="if (window.location.hash === '#thread-<?= (int) $fb['id'] ?>') { open = true; $el.scrollIntoView({ block: 'start' }); }">

            <!-- Thread header (click to expand/collapse) -->
            <button type="button" @click="open = !open"
                    class="w-full flex items-center justify-between gap-3 p-5 text-left hover:bg-slate-50 transition-colors">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-800 line-clamp-1">
                        <?= e($firstMsg ? mb_strimwidth($firstMsg['message'], 0, 90, '…') : '') ?>
                    </p>
                    <p class="mt-1 text-xs text-slate-400">
                        <i class="bi bi-clock me-1"></i>
                        <?= e(t('res_feedback.started', ['date' => date('M j, Y', strtotime($fb['created_at']))])) ?>
                        &bull; <?= e(t('res_feedback.messages_count', ['n' => (int) ($fb['message_count'] ?? 0)])) ?>
                    </p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <?php if ((int) ($fb['unread_reply_count'] ?? 0) > 0): ?>
                    <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-1 text-xs font-bold text-red-700">
                        <i class="bi bi-envelope-fill"></i> <?= e(t('res_feedback.new_reply')) ?>
                    </span>
                    <?php elseif (!$hasReply): ?>
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">
                        <i class="bi bi-hourglass-split"></i> <?= e(t('res_feedback.awaiting')) ?>
                    </span>
                    <?php endif; ?>
                    <i class="bi bi-chevron-down text-slate-400 transition-transform" :class="{ 'rotate-180': open }"></i>
                </div>
            </button>

            <!-- Conversation body -->
            <div x-show="open" x-transition x-cloak class="border-t border-slate-100">

                <div class="flex flex-col gap-1 p-5">
                    <?php foreach ($messages as $msg):
                        $isMine = $msg['sender_role'] === 'resident';
                        // A staff member's own designation ("Barangay Secretary")
                        // is far more useful to a resident than the generic role
                        // label, so it wins when they have set one.
                        $roleLabel = trim((string) ($msg['sender_designation'] ?? ''));
                        if ($roleLabel === '' || $isMine) {
                            $roleLabel = match ($msg['sender_role']) {
                                'staff'      => t('res_feedback.role_staff'),
                                'admin'      => t('res_feedback.role_admin'),
                                'superadmin' => t('res_feedback.role_superadmin'),
                                default      => t('res_feedback.role_resident'),
                            };
                        }
                    ?>
                    <div class="flex gap-3 py-3 <?= $isMine ? '' : '-mx-5 px-5 rounded-xl' ?>"
                         <?= $isMine ? '' : 'style="background:var(--brand-primary-light);"' ?>>
                        <div class="flex-shrink-0 mt-0.5 flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold
                                    <?= $isMine ? 'bg-slate-100 text-slate-500' : 'bg-blue-600 text-white' ?>">
                            <?= $isMine ? 'Ako' : '<i class="bi bi-building"></i>' ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-bold <?= $isMine ? 'text-slate-500' : 'text-blue-700' ?> mb-1">
                                <?= $isMine ? e(t('res_feedback.you')) : e($msg['sender_name']) . ' <span class="font-normal text-blue-500">(' . e($roleLabel) . ')</span>' ?>
                            </p>
                            <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line"><?= e($msg['message']) ?></p>
                            <p class="mt-1.5 text-xs text-slate-400">
                                <i class="bi bi-clock me-1"></i>
                                <?= date('F j, Y \a\t g:i A', strtotime($msg['created_at'])) ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Reply box -->
                <div class="border-t border-slate-100 bg-slate-50 p-4">
                    <form method="post" action="<?= e(route('feedback/' . (int) $fb['id'] . '/reply')) ?>" class="flex items-end gap-2">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <textarea name="message"
                                  rows="2"
                                  required
                                  minlength="5"
                                  maxlength="1000"
                                  placeholder="<?= e(t('res_feedback.reply_placeholder')) ?>"
                                  class="flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 placeholder-slate-400 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100 resize-none"></textarea>
                        <button type="submit"
                                class="flex-shrink-0 inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-300">
                            <i class="bi bi-send"></i>
                        </button>
                    </form>
                </div>
            </div>

        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';