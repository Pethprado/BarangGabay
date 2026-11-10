<?php
/**
 * Proof copy of a requested document — for the resident to check their details.
 *
 * View only. It is rendered as HTML from the same template and profile fields
 * as the real certificate, but has no signature, seal, QR or O.R. number, and a
 * watermark with the viewer's name, the time and the reference covers it.
 *
 * Blocked in the page: printing (print stylesheet + beforeprint), saving and
 * view-source shortcuts, copying, selecting, dragging and the context menu.
 * The sheet blurs when the window loses focus (screenshot/snipping tools take
 * focus), on PrintScreen, and after a few idle minutes. No web page can stop
 * every screenshot or a camera — which is why the watermark, not the blocking,
 * is what makes a copy worthless as a document.
 *
 * Standalone page (no site layout, no external files), sent with no-store.
 *
 * Variables: $request, $resident, $values, $office, $title, $paragraphs,
 *            $header, $captain, $secretary, $watermark, $isStaff, $pageTitle
 */
use App\Models\DocumentRequest;

$type      = (string) $request['document_type'];
$status    = strtolower((string) $request['status']);
$isReady   = in_array($status, DocumentRequest::READY_STATUSES, true);
$check     = (string) ($request['resident_check'] ?? '');
$checkNote = (string) ($request['resident_check_note'] ?? '');
$fee       = (float) ($request['fee_amount'] ?? 0);

// Watermark as a tiled SVG; every character escaped for both XML and the URL.
$wmText = htmlspecialchars($watermark, ENT_QUOTES | ENT_XML1, 'UTF-8');
$wmSvg  = '<svg xmlns="http://www.w3.org/2000/svg" width="420" height="240">'
        . '<g transform="rotate(-28 210 120)" font-family="Arial,sans-serif" fill="rgba(180,20,20,0.16)" text-anchor="middle">'
        . '<text x="210" y="105" font-size="22" font-weight="700">PROOF COPY · NOT VALID</text>'
        . '<text x="210" y="135" font-size="12">' . $wmText . '</text>'
        . '</g></svg>';
$wmUrl  = 'data:image/svg+xml;charset=utf-8,' . rawurlencode($wmSvg);

$printedName = trim((string) $values['HONORIFIC'] . ' ' . (string) $values['RESIDENT_FULL_NAME']);
$checks = [
    [__('Name as printed'), $printedName],
    // From the profile itself: the printed text falls back to "Filipino" when
    // it is empty, which would hide a missing detail here.
    [__('Civil status'), ucfirst(strtolower(trim((string) ($resident['civil_status'] ?? '')))) ?: __('Not set in your profile')],
    [__('Address'), trim((string) $values['PUROK'] . ' ' . $values['BARANGAY'] . ', ' . $values['MUNICIPALITY'] . ', ' . $values['PROVINCE'])],
    [__('Purpose'), (string) $values['PURPOSE']],
];
if ($type === 'business') {
    $checks[] = [__('Business'), trim((string) $values['BUSINESS_TYPE'] . ' — ' . (string) $values['BUSINESS_NAME'])];
}
?>
<!DOCTYPE html>
<html lang="<?= e(current_locale() === 'fil' ? 'fil' : 'en') ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($pageTitle) ?></title>
<style>
/* A paper document: always light, whatever the site theme (this page has no theme switch). */
:root{--ink:#1c2b1e;--muted:#5b6b60;--line:#dfe7e1;--brand:#1a6b3a;--bg:#eef4ef;--warn:#8a4b00;
      --paper-ink:#111;--note-strong:#5a3d00;--clearance-blue:#1f388c;--sig-note:#9a2020}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font-family:'Segoe UI',system-ui,-apple-system,sans-serif;line-height:1.5}
.wrap{max-width:860px;margin:0 auto;padding:16px}
.top{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:12px}
.top a{color:var(--brand);font-weight:700;text-decoration:none}
h1{font-size:1.25rem;margin:0}
.ref{font-family:ui-monospace,monospace;font-size:.8rem;color:var(--muted)}
.badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:.75rem;font-weight:700;background:#e7f4ea;color:var(--brand)}
.badge.wait{background:#fff4e5;color:var(--warn)}
.note{background:#fff8e6;border:1px solid #f3d9a4;color:#6b4a00;border-radius:12px;padding:10px 14px;font-size:.85rem;margin-bottom:14px}
.note strong{color:var(--note-strong)}
.stage{position:relative;background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 12px 40px rgba(15,64,35,.10);overflow:hidden}
.sheet{position:relative;padding:44px 48px 40px;font-family:'Times New Roman',Times,serif;color:var(--paper-ink);font-size:15px;line-height:1.75;
       user-select:none;-webkit-user-select:none;-webkit-touch-callout:none;transition:filter .2s}
.sheet.mono{font-family:'Courier New',Courier,monospace;font-size:14px}
.hdr{text-align:center;line-height:1.35;font-size:14px;letter-spacing:.02em}
.hdr div:last-child{font-weight:700}
.office{text-align:center;margin-top:18px;font-size:16px}
.title{text-align:center;font-weight:700;font-size:18px;margin:6px 0 18px;letter-spacing:.04em}
.title.clearance{color:var(--clearance-blue)}
.greet{font-weight:700;margin-bottom:10px}
.sheet p{margin:0 0 12px;text-align:justify;text-indent:2.2em}
.sign{display:flex;flex-wrap:wrap;justify-content:space-between;gap:24px;margin-top:34px}
.sig{min-width:220px;text-align:center}
.sig .space{height:34px;border-bottom:1px solid #444;margin-bottom:4px;display:flex;align-items:flex-end;justify-content:center;
            font:italic 11px Arial,sans-serif;color:var(--sig-note)}
.sig b{display:block;text-transform:uppercase}
.sig span{font-size:13px}
.wm{position:absolute;inset:0;pointer-events:none;background-image:url("<?= $wmUrl ?>");background-repeat:repeat;z-index:2}
.shield{position:absolute;inset:0;z-index:3;display:none;align-items:center;justify-content:center;text-align:center;
        background:rgba(238,244,239,.82);font-weight:700;color:var(--brand);padding:20px;cursor:pointer}
.stage.hidden .sheet{filter:blur(14px)}
.stage.hidden .shield{display:flex}
.panel{background:#fff;border:1px solid var(--line);border-radius:14px;padding:16px 18px;margin-top:14px}
.panel h2{font-size:1rem;margin:0 0 10px}
table{width:100%;border-collapse:collapse;font-size:.9rem}
td{padding:7px 4px;border-bottom:1px solid var(--line);vertical-align:top}
td:first-child{color:var(--muted);width:38%}
.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:12px}
button,.btn{border:0;border-radius:10px;padding:11px 16px;font-weight:700;font-size:.9rem;cursor:pointer;text-decoration:none;display:inline-block}
.ok{background:var(--brand);color:#fff}
.bad{background:#fff;color:#a12020;border:1.5px solid #e9b4b4}
textarea{width:100%;min-height:80px;border:1px solid #c9d6cc;border-radius:10px;padding:10px;font:inherit;font-size:.9rem}
.state{border-radius:10px;padding:10px 12px;font-size:.88rem;margin-bottom:10px}
.state.good{background:#e7f4ea;color:#14532d}
.state.issue{background:#fdecec;color:#7f1d1d}
.flash{border-radius:10px;padding:10px 12px;margin-bottom:12px;font-size:.88rem}
.flash.success{background:#e7f4ea;color:#14532d}.flash.error{background:#fdecec;color:#7f1d1d}
.muted{color:var(--muted);font-size:.8rem}
@media (max-width:600px){.sheet{padding:26px 18px;font-size:14px}.title{font-size:16px}}
/* Printing shows nothing but this line. */
@media print{body *{display:none!important}body::before{content:"Printing is disabled. This is a proof copy for checking only — claim the official document at the Barangay Hall.";display:block;font:16px Arial,sans-serif;padding:40px}}
</style>
</head>
<body oncontextmenu="return false" ondragstart="return false">
<div class="wrap">
    <div class="top">
        <div>
            <a href="<?= e($isStaff ? route('admin/documents') : route('documents') . '?tab=requests&ref=' . rawurlencode((string) $request['reference_no'])) ?>">&larr; <?= e(__('Back')) ?></a>
            <h1><?= e(__('Check your document')) ?></h1>
            <span class="ref"><?= e((string) $request['reference_no']) ?> · <?= e(DocumentRequest::label($type)) ?></span>
        </div>
        <span class="badge<?= $isReady ? '' : ' wait' ?>"><?= e($isReady ? __('Ready for Pickup') : DocumentRequest::statusLabel($status)) ?></span>
    </div>

    <?php foreach (['success', 'error'] as $__k): if ($__m = flash($__k)): ?>
        <div class="flash <?= $__k ?>"><?= e((string) $__m) ?></div>
    <?php endif; endforeach; ?>

    <div class="note">
        <strong><?= e(__('Proof copy for checking only.')) ?></strong>
        <?= e(__('It is not valid, cannot be printed or downloaded, and is watermarked with your name. The signed original is given at the Barangay Hall.')) ?>
    </div>

    <div class="stage" id="proofStage">
        <div class="sheet<?= $type === 'indigency' ? ' mono' : '' ?>" id="proofSheet" aria-label="<?= e(__('Proof copy')) ?>">
            <div class="hdr"><?php foreach ($header as $line): ?><div><?= e($line) ?></div><?php endforeach; ?></div>
            <div class="office"><?= $office ?></div>
            <div class="title <?= e($type) ?>"><?= $title ?></div>
            <?php if ($type !== 'business'): ?><div class="greet">TO WHOM IT MAY CONCERN:</div><?php endif; ?>
            <?php foreach ($paragraphs as $paragraph): ?><p><?= $paragraph ?></p><?php endforeach; ?>
            <div class="sign">
                <?php if ($type === 'clearance'): ?>
                <div class="sig"><div class="space"><?= e(__('signed on the original')) ?></div><b><?= e($secretary) ?></b><span>Brgy. Secretary</span></div>
                <?php endif; ?>
                <div class="sig" style="margin-left:auto"><div class="space"><?= e(__('signed on the original')) ?></div><b><?= e($captain) ?></b><span><?= $type === 'indigency' ? 'Barangay Captain' : 'Punong Barangay' ?></span></div>
            </div>
        </div>
        <div class="wm" aria-hidden="true"></div>
        <div class="shield" id="proofShield" role="button" tabindex="0"><?= e(__('Hidden to protect your document. Tap here to show it again.')) ?></div>
    </div>

    <div class="panel">
        <h2><?= e(__('Please check these details')) ?></h2>
        <table>
            <?php foreach ($checks as [$label, $value]): ?>
            <tr><td><?= e($label) ?></td><td><strong><?= e($value !== '' ? $value : '—') ?></strong></td></tr>
            <?php endforeach; ?>
            <tr><td><?= e(__('Fee')) ?></td><td><?= $fee > 0 ? '₱' . number_format($fee, 2) . ' — ' . e(__('paid at the Barangay Hall on pickup')) : e(__('LIBRE')) ?></td></tr>
        </table>
        <p class="muted" style="margin:10px 0 0;"><?= e(__('Name, birthday or address wrong? They come from your profile — update it there, then report it below so staff can make a new copy.')) ?>
            <?php if (!$isStaff): ?><a href="<?= e(route('profile')) ?>"><?= e(__('Open my profile')) ?></a><?php endif; ?></p>
    </div>

    <?php if (!$isStaff): ?>
    <div class="panel" id="check">
        <h2><?= e(__('Is everything correct?')) ?></h2>
        <?php if ($check === 'ok'): ?>
            <div class="state good">✓ <?= e(__('You confirmed these details are correct.')) ?></div>
        <?php elseif ($check === 'issue'): ?>
            <div class="state issue"><?= e(__('You reported a mistake:')) ?> “<?= e($checkNote) ?>” — <?= e(__('staff will correct it and notify you.')) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(route('documents/' . (int) $request['id'] . '/check')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="actions">
                <button type="submit" name="result" value="ok" class="ok">✓ <?= e(__('Yes, the details are correct')) ?></button>
                <button type="button" class="bad" id="reportBtn">✗ <?= e(__('Something is wrong')) ?></button>
            </div>
            <div id="reportBox" style="display:none;margin-top:12px;">
                <label for="note" class="muted"><?= e(__('What is wrong? Write the correct details.')) ?></label>
                <textarea id="note" name="note" maxlength="1000" placeholder="<?= e(__('e.g. My middle name is Santos, not Santo.')) ?>"></textarea>
                <div class="actions"><button type="submit" name="result" value="issue" class="bad"><?= e(__('Send to barangay staff')) ?></button></div>
            </div>
        </form>
    </div>
    <?php elseif ($check !== ''): ?>
    <div class="panel">
        <div class="state <?= $check === 'ok' ? 'good' : 'issue' ?>">
            <?= $check === 'ok' ? '✓ ' . e(__('The resident confirmed the details.')) : e(__('The resident reported:')) . ' “' . e($checkNote) . '”' ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var stage  = document.getElementById('proofStage');
    var shield = document.getElementById('proofShield');
    var idle;

    function hide() { stage.classList.add('hidden'); }
    function show() { stage.classList.remove('hidden'); restartIdle(); }
    // Auto-hide after 3 idle minutes, so an unattended screen is not left open.
    function restartIdle() { clearTimeout(idle); idle = setTimeout(hide, 180000); }

    shield.addEventListener('click', show);
    shield.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { show(); } });

    // Snipping/screenshot tools and app switching take focus away.
    window.addEventListener('blur', hide);
    document.addEventListener('visibilitychange', function () { if (document.hidden) { hide(); } });

    // No printing, saving, copying, selecting or viewing the source.
    window.addEventListener('beforeprint', hide);
    ['copy', 'cut', 'selectstart', 'dragstart', 'contextmenu'].forEach(function (ev) {
        document.addEventListener(ev, function (e) {
            if (ev === 'selectstart' && e.target.closest && e.target.closest('textarea')) { return; }
            if ((ev === 'copy' || ev === 'cut') && e.target.closest && e.target.closest('textarea')) { return; }
            e.preventDefault();
        });
    });
    document.addEventListener('keydown', function (e) {
        var k = (e.key || '').toLowerCase();
        if ((e.ctrlKey || e.metaKey) && ['p', 's', 'u', 'c', 'a', 'x'].indexOf(k) !== -1 && !(e.target.closest && e.target.closest('textarea'))) {
            e.preventDefault();
            if (k === 'p') { hide(); }
        }
        if (k === 'printscreen' || (e.metaKey && e.shiftKey)) { hide(); }
    });
    document.addEventListener('keyup', function (e) {
        if ((e.key || '').toLowerCase() === 'printscreen') {
            hide();
            try { navigator.clipboard && navigator.clipboard.writeText(''); } catch (_) {}
        }
    });

    var btn = document.getElementById('reportBtn');
    if (btn) {
        btn.addEventListener('click', function () {
            var box = document.getElementById('reportBox');
            box.style.display = 'block';
            document.getElementById('note').focus();
        });
    }

    restartIdle();
})();
</script>
</body>
</html>
