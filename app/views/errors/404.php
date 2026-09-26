<?php
ob_start();
?>

<div class="flex min-h-[400px] flex-col items-center justify-center py-16 text-center">
    <div class="mb-6 text-6xl leading-none opacity-30">🔍</div>
    <h1 class="mb-2 text-3xl font-bold text-slate-800">404</h1>
    <p class="mb-2 text-lg font-semibold text-slate-700">Hindi Nahanap ang Pahina</p>
    <p class="mb-8 max-w-sm text-sm leading-relaxed text-slate-500">
        Paumanhin! Ang pahinang iyong hinahanap ay wala, inilipat na, o hindi available.
    </p>
    <div class="flex flex-wrap gap-3 justify-center">
        <a href="<?= e(route('')) ?>"
           class="inline-flex items-center gap-2 rounded-full bg-blue-700 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800">
            <i class="bi bi-house"></i> Bumalik sa Home
        </a>
        <button onclick="history.back()"
                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-6 py-2.5 text-sm font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50">
            <i class="bi bi-arrow-left"></i> Bumalik
        </button>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';