<?php
ob_start();
$userName = e($_SESSION['full_name'] ?? 'Residente');
$status   = $_SESSION['status']    ?? 'pending';
$isSuspended = $status === 'suspended';
?>

<div class="flex min-h-[360px] flex-col items-center justify-center py-10 text-center">

    <!-- Status icon -->
    <div class="mb-5 flex h-[76px] w-[76px] items-center justify-center rounded-full ring-4 <?= $isSuspended ? 'bg-red-50 ring-red-100' : 'bg-amber-50 ring-amber-100' ?>"
         style="font-size:2.4rem;line-height:1;">
        <?= $isSuspended ? '🚫' : '⏳' ?>
    </div>

    <!-- Greeting -->
    <p class="mb-0.5 text-sm font-medium text-slate-500">Kumusta,</p>
    <p class="mb-6 text-lg font-bold text-slate-800"><?= $userName ?>!</p>

    <?php if ($isSuspended): ?>

    <!-- Suspended state -->
    <h1 class="mb-2 text-2xl font-bold text-red-600">Account Suspended</h1>
    <p class="mb-6 max-w-[420px] text-sm leading-relaxed text-slate-600">
        Ang iyong account ay nasuspinde. Para sa mga katanungan o apela,
        mangyaring makipag-ugnayan sa Barangay Hall nang personal.
    </p>
    <div class="mb-8 flex w-full max-w-[420px] items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-left">
        <i class="bi bi-exclamation-triangle-fill mt-px shrink-0 text-red-500"></i>
        <p class="text-sm leading-relaxed text-red-900">
            Kung naniniwala kang ito ay pagkakamali, makipag-ugnayan sa Barangay Hall,
            Barangay Bayogo upang ma-review ang iyong account.
        </p>
    </div>

    <?php else: ?>

    <!-- Pending verification state -->
    <h1 class="mb-2 text-2xl font-bold" style="color:var(--brand-primary);">Awaiting Verification</h1>
    <p class="mb-6 max-w-[420px] text-sm leading-relaxed text-slate-600">
        Ang iyong account ay kasalukuyang naghihintay ng pag-apruba ng barangay staff.
        Mangyaring maghintay habang nire-review ang iyong impormasyon at valid ID.
    </p>

    <!-- Progress steps -->
    <div class="mb-7 flex w-full max-w-[380px] items-center gap-0">
        <!-- Step 1: done -->
        <div class="flex flex-col items-center gap-1.5">
            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-white text-xs font-bold shrink-0">
                <i class="bi bi-check2"></i>
            </div>
            <span class="text-[10px] font-semibold text-blue-700 whitespace-nowrap">Nagrehistro</span>
        </div>
        <div class="flex-1 h-[2px] mb-5" style="background:linear-gradient(90deg,#1a6b3a 50%,#e2e8f0 50%);"></div>
        <!-- Step 2: current -->
        <div class="flex flex-col items-center gap-1.5">
            <div class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-amber-400 bg-amber-50 text-amber-600 text-xs font-bold shrink-0">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <span class="text-[10px] font-semibold text-amber-600 whitespace-nowrap">Nire-review</span>
        </div>
        <div class="flex-1 h-[2px] mb-5 bg-slate-200"></div>
        <!-- Step 3: pending -->
        <div class="flex flex-col items-center gap-1.5">
            <div class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-slate-200 bg-white text-slate-300 text-xs font-bold shrink-0">
                <i class="bi bi-person-check"></i>
            </div>
            <span class="text-[10px] font-semibold text-slate-400 whitespace-nowrap">Na-verify</span>
        </div>
    </div>

    <!-- Info box -->
    <div class="mb-8 flex w-full max-w-[420px] items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-left">
        <i class="bi bi-info-circle-fill mt-px shrink-0 text-amber-500"></i>
        <p class="text-sm leading-relaxed text-amber-900 m-0">
            Karaniwang tumatagal ng <strong>1–2 araw ng trabaho</strong> ang pag-verify.
            Makakatanggap ka ng notification sa sandaling ma-approve ang iyong account.
        </p>
    </div>

    <?php endif; ?>

    <!-- Logout -->
    <a href="<?= e(route('logout')) ?>"
       class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-6 py-2.5 text-sm font-medium text-slate-600 shadow-sm transition hover:border-red-300 hover:text-red-600">
        <i class="bi bi-box-arrow-right"></i>
        Mag-logout
    </a>

</div>

<?php if (!$isSuspended): ?>
<script>
(function () {
    var CHECK_URL  = '<?= e(route('api/check-status')) ?>';
    var PORTAL_URL = '<?= e(route('announcements')) ?>';
    var INTERVAL   = 10000; // ms

    function poll() {
        fetch(CHECK_URL, { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (data && data.status === 'verified') {
                    window.location.href = PORTAL_URL;
                }
            })
            .catch(function () { /* network blip — try again next tick */ });
    }

    setInterval(poll, INTERVAL);
})();
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';