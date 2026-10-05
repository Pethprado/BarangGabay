<?php
/**
 * Translation health — the page to open before a defence.
 *
 * Ordered by what a reader can act on, not by what is easiest to query:
 *
 *   1. The three providers. Most lines further down trace back to one of
 *      them being unset, and each is a two-minute fix that nobody made for
 *      weeks only because nothing said it out loud.
 *   2. The headline counts, so "is anything wrong" is answered without
 *      reading a table.
 *   3. The posts themselves, each with its reason and its fix.
 *
 * Variables: $providers, $overview, $rows, $lang, $pageTitle, $pendingCount
 */

use App\Services\TranslationOutcome;

$providers = $providers ?? [];
$overview  = $overview  ?? ['posts' => 0, 'missingText' => 0, 'missingAudio' => 0,
                            'byLang' => [], 'retryable' => 0];
$rows      = $rows      ?? [];
$lang      = $lang      ?? '';

ob_start();
?>

<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <p class="mb-0" style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);">
            <?= e(t('admin_nav.translations')) ?>
        </p>
        <h1 class="mb-0 mt-1" style="font-size:1.55rem;font-weight:800;color:var(--text-primary);line-height:1.1;">
            <?= e(t('translation_health.page_title')) ?>
        </h1>
        <p class="text-muted mt-1 mb-0" style="font-size:.82rem;">
            <?= e(t('translation_health.page_sub')) ?>
        </p>
    </div>
</div>

<?php /* ── 1. Providers ─────────────────────────────────────────────── */ ?>
<div class="admin-card mb-4">
    <div class="admin-card-body">
        <p class="fw-bold mb-3" style="font-size:.78rem;letter-spacing:.06em;text-transform:uppercase;color:var(--text-secondary);">
            <?= e(t('translation_health.providers_title')) ?>
        </p>

        <div class="th-providers">
            <?php foreach ($providers as $p): ?>
            <div class="th-provider<?= $p['ready'] ? '' : ' th-provider--down' ?>">
                <span class="th-provider__dot" aria-hidden="true"></span>
                <div class="min-w-0">
                    <p class="th-provider__name"><?= e($p['label']) ?></p>
                    <?php if ($p['ready']): ?>
                    <p class="th-provider__state"><?= e(t('translation_health.provider_ready')) ?></p>
                    <?php else: ?>
                    <p class="th-provider__state"><?= e(t(TranslationOutcome::messageKey((string) $p['reason']))) ?></p>
                    <p class="th-provider__fix"><?= e(t(TranslationOutcome::fixKey((string) $p['reason']))) ?></p>
                    <?php if ($p['envKey'] !== null): ?>
                    <p class="th-provider__env"><code><?= e($p['envKey']) ?></code></p>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php /* ── 2. The headline ──────────────────────────────────────────── */ ?>
<div class="th-counts mb-4">
    <div class="th-count">
        <span class="th-count__n"><?= (int) $overview['posts'] ?></span>
        <span class="th-count__l"><?= e(t('translation_health.count_posts')) ?></span>
    </div>
    <div class="th-count">
        <span class="th-count__n"><?= (int) $overview['missingText'] ?></span>
        <span class="th-count__l"><?= e(t('translation_health.count_text')) ?></span>
    </div>
    <div class="th-count">
        <span class="th-count__n"><?= (int) $overview['missingAudio'] ?></span>
        <span class="th-count__l"><?= e(t('translation_health.count_audio')) ?></span>
    </div>
    <div class="th-count">
        <span class="th-count__n"><?= (int) $overview['retryable'] ?></span>
        <span class="th-count__l"><?= e(t('translation_health.count_queued')) ?></span>
    </div>
</div>

<?php /* ── Filter + bulk retry ──────────────────────────────────────── */ ?>
<div class="admin-card mb-4">
    <div class="admin-card-body d-flex flex-wrap align-items-center gap-2">
        <span style="font-size:.8rem;font-weight:700;color:var(--text-secondary);">
            <?= e(t('translation_health.filter_label')) ?>
        </span>

        <a href="<?= e(route('admin/translation-health')) ?>"
           class="filter-chip<?= $lang === '' ? ' is-active' : '' ?>">
            <?= e(t('translation_health.filter_all')) ?>
        </a>
        <?php foreach (['fil', 'en', 'msm'] as $thLang): ?>
        <a href="<?= e(route('admin/translation-health') . '?lang=' . $thLang) ?>"
           class="filter-chip<?= $lang === $thLang ? ' is-active' : '' ?>">
            <?= e(strtoupper(locale_short_code($thLang))) ?>
        </a>
        <?php endforeach; ?>

        <?php /* Only offered with a language chosen: "retry everything in
                 every language" would spend the whole daily allowance in
                 one press and is never what anybody means. */ ?>
        <?php if ($lang !== ''): ?>
        <form method="post" action="<?= e(route('admin/retranslate-all')) ?>" class="ms-auto">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="lang" value="<?= e($lang) ?>">
            <button type="submit" class="btn-barangay">
                <i class="bi bi-arrow-repeat me-1"></i>
                <?= e(t('translation_health.retry_all', [
                    'lang' => strtoupper(locale_short_code($lang)),
                ])) ?>
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php /* ── Translation dashboard summary ─────────────────────────────── */
$__mn = $mnStats ?? ['posts' => 0, 'with_mn' => 0, 'gaps' => 0, 'bisaya_fallback' => 0, 'unresolved' => 0, 'top' => []];
$__mnPct = $__mn['posts'] > 0 ? round($__mn['with_mn'] / $__mn['posts'] * 100) : 0; ?>
<div class="row g-3 mb-4">
    <?php foreach ([
        ['Manobo coverage', $__mnPct . '%', $__mn['with_mn'] . ' of ' . $__mn['posts'] . ' posts have Manobo', 'bi-translate'],
        ['Bisaya fallback', (string) $__mn['bisaya_fallback'], 'missing Manobo words filled with Bisaya', 'bi-chat-quote'],
        ['Unresolved words', (string) $__mn['unresolved'], 'in neither dictionary — add them', 'bi-question-diamond'],
        ['Posts needing work', (string) (int) ($overview['posts'] ?? 0), 'missing a translation or audio', 'bi-clipboard-x'],
    ] as [$__l, $__v, $__s, $__i]): ?>
    <div class="col-6 col-lg-3">
        <div class="stat-card h-100">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                    <p class="stat-card-label"><?= e($__l) ?></p>
                    <p class="stat-card-value"><?= e($__v) ?></p>
                    <p class="mb-0" style="font-size:.75rem;color:var(--text-muted);"><?= e($__s) ?></p>
                </div>
                <div class="stat-card-icon flex-shrink-0"><i class="bi <?= $__i ?>" aria-hidden="true"></i></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (!empty($__mn['top'])): ?>
<div class="admin-card mb-4" style="padding:1.25rem;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h2 class="h6 mb-0" style="font-weight:800;color:var(--text-primary);">Translation gaps — most used</h2>
        <a href="<?= e(route('admin/manobo')) ?>" class="btn-barangay"><i class="bi bi-plus-circle me-1"></i>Add translation in Dictionary</a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Source word / phrase</th><th>Bisaya fallback used</th><th class="text-end">Times used</th></tr></thead>
            <tbody>
            <?php foreach ($__mn['top'] as $__g): ?>
                <tr>
                    <td class="fw-semibold"><?= e((string) $__g['concept']) ?></td>
                    <td><?= $__g['bisaya_fallback'] !== null ? e((string) $__g['bisaya_fallback']) : status_badge('failed', 'Unresolved') ?></td>
                    <td class="text-end"><?= (int) $__g['usage_count'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php /* ── MN rebuild: Manobo first, Bisaya fallback, gaps flagged ───── */ ?>
<div class="admin-card mb-4" id="mnRebuild">
    <div class="d-flex flex-wrap align-items-start gap-3">
        <div style="flex:1 1 320px;">
            <h2 class="h6 mb-1" style="font-weight:800;color:var(--text-primary);">Rebuild MN translations</h2>
            <p class="mb-0" style="font-size:.85rem;color:var(--text-secondary);">
                Re-translates machine-made and sample Manobo with the current dictionaries:
                Manobo first, Bisaya only where Manobo is missing. Words neither dictionary has are
                flagged as <strong>unresolved</strong> and added to the Manobo gaps list in the Dictionary
                admin, never passed off as Bisaya. Hand-written Manobo is not changed.
            </p>
        </div>
        <button type="button" class="btn-barangay" id="mnRebuildBtn" onclick="mnRebuild()">
            <i class="bi bi-arrow-repeat me-1"></i> Rebuild MN translations
        </button>
    </div>
    <div id="mnRebuildStatus" class="mt-3 d-none" style="font-size:.85rem;" aria-live="polite"></div>
    <ul id="mnRebuildLog" class="mt-2 mb-0 ps-3" style="font-size:.8rem;color:var(--text-secondary);max-height:220px;overflow:auto;"></ul>
</div>
<script>
async function mnRebuild() {
    var btn = document.getElementById('mnRebuildBtn');
    var status = document.getElementById('mnRebuildStatus');
    var log = document.getElementById('mnRebuildLog');
    if (!confirm('Re-translate machine-made Manobo for all posts? This can take several minutes.')) { return; }
    btn.disabled = true; log.innerHTML = ''; status.classList.remove('d-none');
    var offset = 0, unresolved = 0, failed = 0;
    try {
        while (true) {
            status.textContent = 'Working... ' + offset + ' done. Keep this page open.';
            var fd = new FormData();
            fd.set('csrf_token', <?= json_encode(csrf_token()) ?>);
            fd.set('offset', offset);
            var res = await fetch(<?= json_encode(route('admin/translation-health/rebuild-manobo')) ?>, { method: 'POST', body: fd, credentials: 'same-origin' });
            var data = await res.json();
            if (!data.success) { throw new Error(data.error || 'failed'); }
            data.posts.forEach(function (p) {
                unresolved += p.unresolved; if (!p.ok) { failed++; }
                var li = document.createElement('li');
                li.textContent = (p.ok ? '✔ ' : '✖ ') + p.type + ' #' + p.id + ' — ' + p.title + ' · ' + p.unresolved + ' unresolved word(s)';
                log.appendChild(li);
            });
            offset = data.next;
            if (data.done) {
                status.textContent = 'Done: ' + data.total + ' post(s) rebuilt, ' + failed + ' failed, ' + unresolved + ' unresolved word(s) left in MN text (fill them in Dictionary → missing concepts).';
                break;
            }
        }
    } catch (e) {
        status.textContent = 'Stopped at ' + offset + ': ' + e.message + '. Click again to restart.';
    }
    btn.disabled = false;
}
</script>

<?php /* ── 3. The posts ─────────────────────────────────────────────── */ ?>
<?php if ($rows === []): ?>
<div class="admin-card">
    <div class="admin-card-body text-center" style="padding:2.5rem 1rem;">
        <i class="bi bi-check2-circle" style="font-size:2rem;color:var(--status-success);"></i>
        <p class="mt-2 mb-0" style="font-size:.9rem;font-weight:700;color:var(--text-primary);">
            <?= e(t('translation_health.all_clear')) ?>
        </p>
        <p class="text-muted mb-0" style="font-size:.8rem;">
            <?= e(t('translation_health.all_clear_sub')) ?>
        </p>
    </div>
</div>
<?php else: ?>
<div class="admin-card">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
            <tr>
                <th><?= e(t('translation_health.col_post')) ?></th>
                <th><?= e(t('translation_health.col_missing')) ?></th>
                <th><?= e(t('translation_health.col_why')) ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td>
                    <p class="mb-0 fw-semibold" style="font-size:.84rem;">
                        <?= e(mb_strimwidth((string) $r['title'], 0, 70, '…', 'UTF-8')) ?>
                    </p>
                    <p class="text-muted mb-0" style="font-size:.72rem;">
                        <?= e(t('translation_health.type_' . $r['type'])) ?>
                        &middot; #<?= (int) $r['id'] ?>
                        &middot; <?= e(t('translation_health.written_in_short')) ?>
                        <?= e(strtoupper(locale_short_code($r['source']))) ?>
                    </p>
                </td>

                <td style="white-space:nowrap;">
                    <?php foreach ($r['missing'] as $mLang): ?>
                    <span class="lang-chip lang-chip--missing">
                        <?= e(strtoupper(locale_short_code($mLang))) ?>
                    </span>
                    <?php endforeach; ?>
                    <?php foreach ($r['audioMissing'] as $aLang): ?>
                    <span class="lang-chip lang-chip--machine" title="<?= e(t('translation_health.audio_only')) ?>">
                        <i class="bi bi-volume-up"></i>
                        <?= e(strtoupper(locale_short_code($aLang))) ?>
                    </span>
                    <?php endforeach; ?>
                </td>

                <td>
                    <?php if ($r['reasons'] === []): ?>
                    <p class="text-muted mb-0" style="font-size:.78rem;">
                        <?= e(t('translation_health.never_tried')) ?>
                    </p>
                    <?php else: ?>
                    <?php foreach ($r['reasons'] as $rLang => $why): ?>
                    <p class="mb-1" style="font-size:.78rem;line-height:1.5;">
                        <strong><?= e(strtoupper(locale_short_code((string) $rLang))) ?></strong>
                        <?= e($why['message']) ?>
                        <span class="d-block text-muted"><?= e($why['fix']) ?></span>
                        <?php if ($why['envKey'] !== null): ?>
                        <code style="font-size:.72rem;"><?= e($why['envKey']) ?></code>
                        <?php endif; ?>
                    </p>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </td>

                <td style="white-space:nowrap;">
                    <a href="<?= e(route('admin/' . $r['type'] . 's/' . $r['id'] . '/edit')) ?>"
                       class="btn btn-sm btn-outline-secondary" style="font-size:.74rem;">
                        <?= e(t('common.edit')) ?>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
