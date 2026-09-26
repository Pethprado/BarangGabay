<?php
/**
 * Announcement detail view.
 * Variables: $announcement (array), $related (array)
 */
$related = $related ?? [];

// Labels and badge styling come from helpers.php. Both were hardcoded here:
// the labels in Filipino, so a resident reading in English got an English post
// under a badge saying "Pamahalaan"; the colours as a third copy of a map that
// the home page disagreed with.
$cat    = $announcement['category'] ?? 'general';
$urg    = $announcement['urgency']  ?? 'normal';
$catLbl = category_labels()[$cat] ?? ucfirst($cat);
$catCls = category_badge_class($cat);

$urgBadgeClass = urgency_badge_class($urg);
$urgLabel      = match ($urg) {
    'urgent'    => t('res_announcements.urgency_urgent'),
    'important' => t('res_announcements.urgency_important'),
    default     => '',
};

$pageUrl   = app_url('announcements/' . ($announcement['slug'] ?? ''));
$fbUrl     = 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($pageUrl);

ob_start();
?>

<!-- ── Breadcrumb ─────────────────────────────────────────────── -->
<nav class="mb-5 flex items-center gap-2 text-xs text-slate-400" aria-label="Breadcrumb">
    <a href="<?= e(route('')) ?>" class="hover:text-blue-700 transition-colors"><?= e(t('nav.home')) ?></a>
    <i class="bi bi-chevron-right" style="font-size:.65rem;"></i>
    <a href="<?= e(route('announcements')) ?>" class="hover:text-blue-700 transition-colors"><?= e(t('nav.announcements')) ?></a>
    <i class="bi bi-chevron-right" style="font-size:.65rem;"></i>
    <span class="text-slate-600 line-clamp-1 max-w-[200px]"><?= e(localised_text($announcement, 'title')) ?></span>
</nav>

<div class="grid gap-8 lg:grid-cols-[1fr_300px]">

    <!-- ── Main column ──────────────────────────────────────── -->
    <div class="min-w-0">

        <?php
        /*
         * Content language follows the FIL / EN / MN switch in the header —
         * there is no separate per-post toggle any more. Two controls for the
         * same thing meant a resident could set the page to English and still
         * be reading Filipino because the post's own toggle said otherwise.
         *
         * The body is HTML only when it is the source-language original (Quill
         * output, purified on save). Translations are stored as plain text, so
         * they are escaped and rendered with line breaks preserved instead.
         *
         * This asks localised_content() which of the two it handed back. It
         * used to guess — "translated and not Filipino" — which was true only
         * while every post was written in Filipino. A post authored in English
         * then had its own original escaped, and residents read the literal
         * <p> tags instead of the paragraphs.
         */
        $titlePick  = localised_content($announcement, 'title');
        $bodyPick   = localised_content($announcement, 'body');
        $bodyIsHtml = $bodyPick['is_original'] ?? false;

        /*
         * Author row + headline + hero, shared with events and ordinances.
         * The category and urgency badges moved into the author row: they were
         * previously burned into a gradient over the cover image, which meant
         * a post without a cover showed them somewhere else entirely.
         */
        $__pRow    = $announcement;
        $__pTitle  = $titlePick['text'];
        $__pImage  = $announcement['cover_image_url'] ?? null;
        $__pStamp  = null;
        $__pBadges = [['label' => $catLbl, 'class' => $catCls]];
        if ($urgLabel) {
            $__pBadges[] = [
                'label' => $urgLabel,
                'class' => $urgBadgeClass . ($urg === 'urgent' ? ' badge-urgent-pulse' : ''),
            ];
        }

        /*
         * A targeted notice says so, to everyone.
         *
         * Only the residents of that purok were notified and texted, but the
         * post is public and anyone may read it — so the badge explains why a
         * reader from another purok never got an alert about it, instead of
         * leaving them to wonder whether their notifications are broken.
         */
        $targetPurok = trim((string) ($announcement['target_purok'] ?? ''));
        if ($targetPurok !== '') {
            $__pBadges[] = [
                'label' => t('purok_target.badge', ['purok' => $targetPurok]),
                'class' => 'bg-blue-100 text-blue-700',
            ];
        }
        require __DIR__ . '/../shared/_post-header.php';
        ?>

        <?php
        /*
         * Voice reader — directly under the headline, before the article and
         * well before the share row. A resident who needs this post read to
         * them must not have to read their way down the page to find the
         * button that reads it.
         *
         * All three language tracks are built from the row by PostScript, so
         * the words spoken and the words cached as audio can never drift apart.
         * The picker inside the player starts on the page's own language.
         */
        $__vrType = 'announcement';
        $__vrRow  = $announcement;
        require __DIR__ . '/../shared/_voice-reader.php';
        ?>

        <?php
        /*
         * "Ligtas ako" — only on advisories that asked for it.
         *
         * Placed directly under the headline, above the article. A resident
         * reading this during or after a storm should not have to scroll
         * through evacuation instructions to reach the button that tells the
         * barangay they are alive.
         *
         * Their existing answer is shown back to them, and can be changed:
         * someone who tapped "safe" and then needed help must be able to say
         * so, and that correction is the most important tap this page takes.
         */
        if ((int) ($announcement['asks_safety_checkin'] ?? 0) === 1):
            $__scMine = \App\Models\SafetyCheckin::forUser(
                (int) $announcement['id'],
                (int) ($_SESSION['user_id'] ?? 0)
            );
            $__scState = $__scMine['status'] ?? null;
        ?>
        <div class="my-4 rounded-2xl border border-red-200 bg-red-50 p-4">
            <p class="text-sm font-bold text-red-900">
                <i class="bi bi-shield-check me-1"></i><?= e(t('safety.prompt_title')) ?>
            </p>
            <p class="mt-1 text-xs leading-relaxed text-red-800"><?= e(t('safety.prompt_help')) ?></p>

            <?php if ($__scState !== null): ?>
            <p class="mt-2 text-xs font-semibold text-red-900">
                <i class="bi bi-check2-circle me-1"></i>
                <?= e(t('safety.already_' . $__scState, [
                    'when' => format_datetime((string) $__scMine['checked_in_at']),
                ])) ?>
            </p>
            <?php endif; ?>

            <form method="post"
                  action="<?= e(route('safety-checkin/' . (int) $announcement['id'])) ?>"
                  class="mt-3 flex flex-wrap items-center gap-2">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="text" name="note" maxlength="255"
                       placeholder="<?= e(t('safety.note_ph')) ?>"
                       class="min-w-0 flex-1 rounded-lg border border-red-200 bg-white px-3 py-2 text-sm">
                <button type="submit" name="status" value="safe"
                        class="rounded-lg bg-green-600 px-4 py-2 text-sm font-bold text-white hover:bg-green-700">
                    <i class="bi bi-check-lg me-1"></i><?= e(t('safety.btn_safe')) ?>
                </button>
                <button type="submit" name="status" value="needs_help"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700">
                    <i class="bi bi-exclamation-triangle me-1"></i><?= e(t('safety.btn_help')) ?>
                </button>
            </form>

            <a href="<?= e(route('evacuation')) ?>"
               class="mt-3 inline-flex items-center gap-1 text-xs font-bold text-red-900 hover:underline">
                <i class="bi bi-geo-alt-fill"></i> <?= e(t('safety.where_do_i_go')) ?>
            </a>
        </div>
        <?php endif; ?>

        <hr class="post-rule">

        <?php /* Title and body together: the headline can fall back to another
                 language while the body is translated, and a reader is owed the
                 truth about the page they are looking at, not just its body. */ ?>
        <?php $__tnPicks = [$bodyPick, $titlePick]; require __DIR__ . '/../shared/_translation-notice.php'; ?>

        <?php /* data-voice-body marks the element the voice reader highlights
                 sentence by sentence as it reads. */ ?>
        <?php if ($bodyIsHtml): ?>
        <!-- Filipino original: stored HTML from Quill, purified on save. -->
        <div class="post-body" data-voice-body>
            <?= $bodyPick['text'] ?>
        </div>
        <?php else: ?>
        <!-- Translation: plain text, escaped, line breaks preserved. -->
        <div class="post-body" data-voice-body>
            <p class="whitespace-pre-line">
                <?= e($bodyPick['text']) ?>
            </p>
        </div>
        <?php endif; ?>

        <?php
        /*
         * The original post, when this notice came from one. Renders nothing
         * for a post written here, which is the ordinary case. Placed after the
         * article and before the share row: it is attribution, not the content.
         */
        $__seRow = $announcement;
        require __DIR__ . '/../shared/_source-embed.php';
        ?>

        <!-- Share buttons -->
        <div class="mt-8 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-6">
            <span class="text-sm font-semibold text-slate-500"><?= e(t('detail_ui.share')) ?></span>

            <a href="<?= e($fbUrl) ?>" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-2 rounded-xl bg-[#1877f2] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#0d6edc] hover:shadow-md">
                <i class="bi bi-facebook"></i> Facebook
            </a>

            <button type="button" id="copyLinkBtn"
                    data-url="<?= e($pageUrl) ?>"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 hover:border-slate-300">
                <i class="bi bi-link-45deg"></i>
                <span id="copyLinkText"><?= e(t('res_announcement_detail.copy_link')) ?></span>
            </button>
        </div>

<!-- ── Manobo Translator ─────────────────────────────────── -->
        <?php
        $__mText = $announcement['title'] . '. ' . ($announcement['body'] ?? '');
        $__mAudio = $announcement['audio_manobo_path'] ?? null;
        $__mType = 'announcement';
        $__mId   = (int) $announcement['id'];
        // The Manobo already stored on the row — what staff or a Manobo
        // speaker typed. Shown straight away instead of asking an API that
        // needs credits for something the barangay has already written.
        $__mManual = trim(
            trim((string) ($announcement['title_manobo'] ?? ''))
            . "\n\n" . trim((string) ($announcement['body_manobo'] ?? ''))
        );
        $__mIsAuto = (int) ($announcement['manobo_is_auto'] ?? 0) === 1;
        require __DIR__ . '/../shared/_manobo-translator.php';
        ?>

    </div><!-- /main column -->

    <!-- ── Sidebar: related announcements ────────────────────── -->
    <aside class="fade-up fade-up-delay-1 space-y-4">

        <div class="post-card">
            <div class="post-card-head">
                <h2 class="post-card-title"><?= e(t('res_announcement_detail.related_title')) ?></h2>
            </div>
            <div class="post-card-body">

            <?php if (empty($related)): ?>
            <p class="text-sm text-slate-400"><?= e(t('res_announcement_detail.related_empty')) ?></p>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($related as $rel):
                    $relCat = $rel['category'] ?? 'general';
                    $relBdr = match($rel['urgency'] ?? 'normal') {
                        'urgent'    => 'border-l-red-400',
                        'important' => 'border-l-amber-400',
                        default     => 'border-l-slate-200',
                    };
                ?>
                <a href="<?= e(route('announcements/' . $rel['slug'])) ?>"
                   class="group flex gap-3 rounded-xl border border-slate-100 border-l-4 <?= $relBdr ?> p-3 transition hover:bg-blue-50 hover:border-blue-100">
                    <?php if (!empty($rel['cover_image_url'])): ?>
                    <img src="<?= e(asset($rel['cover_image_url'])) ?>" alt=""
                         class="h-14 w-14 flex-shrink-0 rounded-lg object-cover">
                    <?php else: ?>
                    <div class="h-14 w-14 flex-shrink-0 rounded-lg bg-blue-50 flex items-center justify-center">
                        <i class="bi bi-megaphone text-blue-300"></i>
                    </div>
                    <?php endif; ?>
                    <div class="min-w-0">
                        <p class="line-clamp-2 text-sm font-semibold leading-snug text-slate-800 group-hover:text-blue-700 transition-colors">
                            <?= e(localised_text($rel, 'title')) ?>
                        </p>
                        <p class="mt-1 text-xs text-slate-400">
                            <?= e(date('M j, Y', strtotime($rel['published_at'] ?? 'now'))) ?>
                        </p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <a href="<?= e(route('announcements') . '?category=' . urlencode($cat)) ?>"
               class="mt-4 flex items-center gap-1 text-xs font-semibold text-blue-700 hover:underline">
                <?= e(t('res_announcement_detail.all_in_category', ['category' => $catLbl])) ?> <i class="bi bi-arrow-right"></i>
            </a>
            </div><!-- /post-card-body -->
        </div><!-- /post-card -->

        <!-- Back link -->
        <a href="<?= e(route('announcements')) ?>"
           class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50 hover:border-slate-300">
            <i class="bi bi-arrow-left"></i> <?= e(t('res_announcement_detail.back_to_list')) ?>
        </a>

    </aside>

</div><!-- /grid -->

<script>
(function () {
    const btn = document.getElementById('copyLinkBtn');
    if (!btn) return;
    btn.addEventListener('click', () => {
        navigator.clipboard.writeText(btn.dataset.url).then(() => {
            const txt = document.getElementById('copyLinkText');
            txt.textContent = 'Nakopya!';
            btn.classList.add('text-blue-700', 'border-blue-300');
            setTimeout(() => {
                txt.textContent = <?= json_encode(t('res_announcement_detail.copy_link')) ?>;
                btn.classList.remove('text-blue-700', 'border-blue-300');
            }, 2000);
        });
    });
}());
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
