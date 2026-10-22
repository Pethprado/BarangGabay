<?php
/**
 * Resident Document Requests — BarangGabay
 *
 * Request online, pick up at the Barangay Hall. There is no online payment:
 *   1. The resident files the request here.
 *   2. Barangay staff review it and approve it when the document is ready.
 *   3. The resident is notified "Ready for Pickup" and claims it at the hall,
 *      paying any fee there in cash.
 *
 * Two tabs: NEW REQUEST (the form) and MY REQUESTS (status of each request).
 */
use App\Models\DocumentRequest;
use App\Models\User;

$requests = $requests ?? [];
$types    = $types    ?? [];
$docFees  = $docFees  ?? \App\Models\DocumentFee::getAll();
$user     = $user     ?? [];

$isVerifiedResident = (($user['status'] ?? '') === 'verified');
$activeTab = $_GET['tab'] ?? (empty($requests) ? 'new' : 'requests');
if (!in_array($activeTab, ['new', 'requests'], true)) {
    $activeTab = 'new';
}
$highlightRef = (string) ($_GET['ref'] ?? '');

$pageTitle = __('Mga Dokumento at Kahilingan — BarangGabay');
ob_start();
?>
<style>
.doc-timeline { display: flex; gap: .25rem; list-style: none; margin: 0; padding: 0; overflow-x: auto; }
.doc-timeline__step { flex: 1 1 0; min-width: 72px; display: flex; flex-direction: column; align-items: center; gap: .25rem; position: relative; text-align: center; }
.doc-timeline__step:not(:last-child)::after { content: ''; position: absolute; top: .65rem; left: calc(50% + .8rem); right: calc(-50% + .8rem); height: 2px; background: var(--border); }
.doc-timeline__step.is-done:not(:last-child)::after { background: var(--action-solid); }
.doc-timeline__dot { width: 1.3rem; height: 1.3rem; border-radius: 999px; display: grid; place-items: center; font-size: .7rem; font-weight: 800; background: var(--surface-muted); border: 2px solid var(--border); color: var(--text-on-action); }
.doc-timeline__step.is-done .doc-timeline__dot { background: var(--action-solid); border-color: var(--action-solid); }
.doc-timeline__step.is-current .doc-timeline__dot { border-color: var(--action-solid); background: var(--brand-primary-light); box-shadow: 0 0 0 3px var(--brand-primary-light); }
.doc-timeline__label { font-size: .68rem; font-weight: 700; color: var(--text-muted); line-height: 1.2; }
.doc-timeline__step.is-current .doc-timeline__label { color: var(--brand-primary); }
.doc-step-num { display: inline-grid; place-items: center; width: 1.6rem; height: 1.6rem; border-radius: 999px; font-size: .75rem; font-weight: 900; background: var(--action-solid); color: var(--text-on-action, #fff); flex-shrink: 0; }
</style>

<div class="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8">

    <!-- Page Header -->
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white"><?= e(__('Mga Kahilingan ng Dokumento')) ?></h1>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                <?= e(__('Request barangay clearances and certificates online, then pick them up at the Barangay Hall.')) ?>
            </p>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
            <i class="bi bi-folder2"></i> <?= count($requests) ?> <?= count($requests) === 1 ? __('Kahilingan') : __('Mga Kahilingan') ?>
        </span>
    </div>

    <!-- Verification Required Alert (if resident is not verified) -->
    <?php if (!$isVerifiedResident): ?>
    <div class="mb-6 rounded-2xl border-2 border-amber-300 bg-amber-50/90 p-5 shadow-sm dark:border-amber-900 dark:bg-amber-950/40">
        <div class="flex items-start gap-4">
            <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white font-bold text-xl shadow-sm">
                <i class="bi bi-shield-exclamation"></i>
            </div>
            <div class="flex-1">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-bold text-amber-950 dark:text-amber-200"><?= e(__('Kailangan ng Beripikasyon ng Residente (Account Verification Required)')) ?></h3>
                    <span class="rounded-full bg-amber-200 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-900">
                        <?= e(__('Estado:')) ?> <?= e(strtoupper($user['status'] ?? 'UNVERIFIED')) ?>
                    </span>
                </div>
                <p class="text-xs text-amber-800 dark:text-amber-300 mt-1 leading-relaxed">
                    <?= e(__('Ang mga ganap na')) ?> <strong><?= e(__('beripikadong residente')) ?></strong> <?= e(__('lamang ang pinahihintulutang humiling ng mga opisyal na sertipiko at clearance. Mangyaring kumpletuhin ang inyong impormasyon sa inyong profile.')) ?>
                </p>
                <div class="mt-3">
                    <a href="<?= e(route('profile')) ?>"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-amber-600 px-3.5 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-amber-700 transition">
                        <i class="bi bi-person-badge-fill"></i> <?= e(__('Pumunta sa Profile & Magsumite ng Valid ID')) ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabs: NEW REQUEST vs MY REQUESTS -->
    <div class="mb-6 flex rounded-2xl bg-slate-100 p-1.5 dark:bg-slate-800" role="tablist">
        <button type="button" id="tabBtnNew" onclick="switchMainTab('new')" role="tab" aria-selected="<?= $activeTab === 'new' ? 'true' : 'false' ?>"
                class="flex-1 inline-flex items-center justify-center gap-2 py-3 px-4 rounded-xl text-xs sm:text-sm font-bold transition-all <?= $activeTab === 'new' ? 'bg-white text-blue-600 shadow-sm dark:bg-slate-900 dark:text-blue-400' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' ?>">
            <i class="bi bi-file-earmark-plus text-base"></i>
            <span><?= e(__('HUMILING NG DOKUMENTO (NEW REQUEST)')) ?></span>
        </button>
        <button type="button" id="tabBtnRequests" onclick="switchMainTab('requests')" role="tab" aria-selected="<?= $activeTab === 'requests' ? 'true' : 'false' ?>"
                class="flex-1 inline-flex items-center justify-center gap-2 py-3 px-4 rounded-xl text-xs sm:text-sm font-bold transition-all <?= $activeTab === 'requests' ? 'bg-white text-blue-600 shadow-sm dark:bg-slate-900 dark:text-blue-400' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' ?>">
            <i class="bi bi-clock-history text-base"></i>
            <span><?= e(__('AKING MGA KAHILINGAN (MY REQUESTS)')) ?></span>
            <?php if (!empty($requests)): ?>
            <span class="rounded-full bg-blue-100 text-blue-800 px-2 py-0.5 text-xs font-black dark:bg-blue-950 dark:text-blue-300">
                <?= count($requests) ?>
            </span>
            <?php endif; ?>
        </button>
    </div>

    <!-- ===================================================================== -->
    <!-- NEW REQUEST                                                           -->
    <!-- ===================================================================== -->
    <div id="sectionNewRequest" class="<?= $activeTab === 'new' ? '' : 'hidden' ?>">

        <?php if (!$isVerifiedResident): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center dark:border-slate-800 dark:bg-slate-900">
            <i class="bi bi-shield-lock text-4xl text-slate-400 mb-2 block"></i>
            <h3 class="text-base font-bold text-slate-800 dark:text-white"><?= e(__('Naka-lock ang Paghiling ng Dokumento')) ?></h3>
            <p class="text-xs text-slate-500 mt-1 mb-4 max-w-md mx-auto">
                <?= e(__('Kailangan munang beripikahin ng kawani ng barangay ang inyong resident profile bago magsimulang humiling.')) ?>
            </p>
            <a href="<?= e(route('profile')) ?>" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-blue-700">
                <?= e(__('Kumpletuhin ang Beripikasyon')) ?>
            </a>
        </div>
        <?php else: ?>

        <!-- How it works -->
        <ol class="mb-5 grid grid-cols-1 gap-2 sm:grid-cols-3" aria-label="<?= e(__('How it works')) ?>">
            <?php foreach ([
                ['bi-send', __('Submit your request online')],
                ['bi-person-check', __('Barangay staff review and approve it')],
                ['bi-building-check', __('Pick it up at the Barangay Hall when it is ready')],
            ] as $__i => [$__icon, $__text]): ?>
            <li class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3 text-xs font-semibold text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200">
                <span class="doc-step-num" aria-hidden="true"><?= $__i + 1 ?></span>
                <i class="bi <?= $__icon ?> text-base text-blue-600 dark:text-blue-400" aria-hidden="true"></i>
                <span><?= e($__text) ?></span>
            </li>
            <?php endforeach; ?>
        </ol>

        <form method="post" action="<?= e(route('documents')) ?>" id="docRequestForm"
              class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-md sm:p-8 dark:border-slate-800 dark:bg-slate-900">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="delivery_method" value="pickup">

            <!-- 1. Document -->
            <section aria-labelledby="docStep1">
                <h2 id="docStep1" class="mb-3 flex items-center gap-2 text-base font-black text-slate-900 dark:text-white">
                    <span class="doc-step-num" aria-hidden="true">1</span> <?= e(__('Piliin ang Dokumento (Choose Document)')) ?>
                </h2>
                <label for="document_type" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                    <?= e(__('Uri ng Dokumento')) ?> <span class="text-rose-500">*</span>
                </label>
                <select id="document_type" name="document_type" required onchange="onDocumentTypeChange()"
                        class="w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-900 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                    <?php foreach ($types as $key => $label):
                        $f = $docFees[$key] ?? null;
                        if ($f === null || empty($f['is_active'])) { continue; } // disabled by the admin
                        $isFree = $f['is_free'] || (float) $f['amount'] <= 0;
                    ?>
                    <option value="<?= e($key) ?>"
                            data-amount="<?= $isFree ? '0' : number_format((float) $f['amount'], 2, '.', '') ?>"
                            data-requirements="<?= e((string) ($f['requirements'] ?? '')) ?>"
                            data-description="<?= e((string) ($f['description'] ?? '')) ?>">
                        <?= e($label) ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <div class="mt-3 rounded-2xl border border-blue-100 bg-blue-50/50 p-4 dark:border-blue-900/60 dark:bg-blue-950/30">
                    <p class="mb-3 hidden text-sm text-slate-700 dark:text-slate-300" id="docDescription"></p>
                    <div class="grid grid-cols-1 gap-3 text-xs sm:grid-cols-3">
                        <div class="rounded-xl border border-blue-100/80 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                            <span class="mb-0.5 block text-[10px] font-bold uppercase text-slate-400"><?= e(__('Bayad sa Dokumento')) ?></span>
                            <span class="text-lg font-black text-blue-600 dark:text-blue-400" id="docFee"></span>
                            <span class="mt-0.5 block text-[11px] font-semibold text-slate-500" id="docFeeNote"></span>
                        </div>
                        <div class="rounded-xl border border-blue-100/80 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                            <span class="mb-0.5 block text-[10px] font-bold uppercase text-slate-400"><?= e(__('Mga Kinakailangan')) ?></span>
                            <ul class="list-disc ps-4 font-bold text-slate-800 dark:text-slate-200" id="docRequirements"></ul>
                        </div>
                        <div class="rounded-xl border border-blue-100/80 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                            <span class="mb-0.5 block text-[10px] font-bold uppercase text-slate-400"><?= e(__('Processing Time')) ?></span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-400"><?= e(__('1–2 business days')) ?></span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 2. Resident information (read-only, from the verified profile) -->
            <?php
                $userArr      = is_array($user) ? $user : [];
                $userFullName = !empty($userArr) ? User::formatFullName($userArr) : __('Residente');
                $userAddress  = !empty($userArr) ? User::formatAddress($userArr) : 'Barangay Bayogo';
            ?>
            <section aria-labelledby="docStep2">
                <h2 id="docStep2" class="mb-3 flex items-center gap-2 text-base font-black text-slate-900 dark:text-white">
                    <span class="doc-step-num" aria-hidden="true">2</span> <?= e(__('Impormasyon ng Residente (Resident Information)')) ?>
                </h2>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4 dark:border-emerald-900 dark:bg-emerald-950/20">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-emerald-200/60 pb-3 dark:border-emerald-800">
                        <span class="flex items-center gap-2 text-sm font-black text-slate-900 dark:text-white">
                            <i class="bi bi-person-check-fill text-xl text-emerald-600 dark:text-emerald-400"></i> <?= e($userFullName) ?>
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1 text-xs font-black text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
                            <i class="bi bi-check-circle-fill"></i> <?= e(__('Verified ✓')) ?>
                        </span>
                    </div>
                    <div class="grid grid-cols-1 gap-3 text-xs sm:grid-cols-2">
                        <div>
                            <span class="block text-[10px] font-bold uppercase text-slate-400"><?= e(__('Contact Number:')) ?></span>
                            <strong class="text-slate-800 dark:text-slate-200"><?= e($user['phone'] ?? 'N/A') ?></strong>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold uppercase text-slate-400"><?= e(__('Tirahan (Address):')) ?></span>
                            <strong class="text-slate-800 dark:text-slate-200"><?= e($userAddress) ?></strong>
                        </div>
                    </div>
                    <span class="mt-3 block rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-[12px] text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
                        <?= e(__('Printed on your document as:')) ?>
                        <strong><?= e(trim(\App\Services\DocumentFields::honorific($userArr) . ' ' . mb_strtoupper(\App\Services\DocumentFields::name($userArr)))) ?></strong>
                        <?php if (\App\Services\DocumentFields::purok($userArr) !== ''): ?> · <?= e(\App\Services\DocumentFields::purok($userArr)) ?><?php endif; ?>
                        <span class="block opacity-80"><?= e(__('Something wrong? Update your profile before requesting.')) ?> <a href="<?= e(route('profile')) ?>" class="font-bold underline"><?= e(__('Profile')) ?></a></span>
                    </span>
                </div>
            </section>

            <!-- 3. Purpose -->
            <section aria-labelledby="docStep3" class="space-y-4">
                <h2 id="docStep3" class="flex items-center gap-2 text-base font-black text-slate-900 dark:text-white">
                    <span class="doc-step-num" aria-hidden="true">3</span> <?= e(__('Layunin (Purpose)')) ?>
                </h2>
                <div>
                    <label for="purpose" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        <?= e(__('Layunin (Purpose)')) ?> <span class="text-rose-500">*</span>
                    </label>
                    <select id="purpose" name="purpose" required onchange="onPurposeChange(this.value)"
                            class="w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-900 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                        <option value="Employment"><?= e(__('Employment (Paghahanapbuhay / Trabaho)')) ?></option>
                        <option value="School"><?= e(__('School (Eskwela / Scholarship / Enrollment)')) ?></option>
                        <option value="Business"><?= e(__('Business (Negosyo / Permit)')) ?></option>
                        <option value="Government Requirement"><?= e(__('Government Requirement (SSS, PhilHealth, Pag-IBIG, ID)')) ?></option>
                        <option value="Bank Requirement"><?= e(__('Bank Requirement (Pagbubukas ng Account / Loan)')) ?></option>
                        <option value="Travel"><?= e(__('Travel (Lokal o Pang-ibayong Dagat)')) ?></option>
                        <option value="Other"><?= e(__('Other (Iba pang layunin...)')) ?></option>
                    </select>
                    <div id="otherPurposeWrapper" class="mt-2.5 hidden">
                        <label for="purpose_other" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">
                            <?= e(__('Tukuyin ang ibang layunin:')) ?> <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="purpose_other" name="purpose_other" maxlength="150" minlength="3"
                               placeholder="<?= e(__('Halimbawa: Police clearance requirement, scholarship application, atbp.')) ?>"
                               class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-xs text-slate-900 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                    </div>
                </div>

                <!-- Business Permit: what the permit is for -->
                <div id="businessFields" class="hidden rounded-2xl border border-slate-200 p-4 dark:border-slate-700">
                    <p class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300"><?= e(__('Business details')) ?></p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label for="business_type" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1"><?= e(__('Kind of business')) ?> <span class="text-rose-500">*</span></label>
                            <input type="text" id="business_type" name="business_type" maxlength="120" minlength="2" placeholder="<?= e(__('e.g. Sari-sari store')) ?>"
                                   class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                        </div>
                        <div>
                            <label for="business_name" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1"><?= e(__('Business name')) ?> <span class="text-rose-500">*</span></label>
                            <input type="text" id="business_name" name="business_name" maxlength="200" minlength="2" placeholder="<?= e(__('e.g. Tagra Store')) ?>"
                                   class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="business_address" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1"><?= e(__('Business location (optional)')) ?></label>
                            <input type="text" id="business_address" name="business_address" maxlength="255" placeholder="<?= e(__('e.g. Purok 2, near the chapel')) ?>"
                                   class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                        </div>
                    </div>
                </div>

                <div>
                    <label for="notes" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">
                        <?= e(__('Karagdagang Paalala (Opsyonal):')) ?>
                    </label>
                    <textarea id="notes" name="notes" rows="2" maxlength="300"
                              placeholder="<?= e(__('Maaaring maglagay ng karagdagang impormasyon para sa kawani ng barangay...')) ?>"
                              class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs text-slate-900 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"></textarea>
                </div>
            </section>

            <!-- Pickup notice + submit -->
            <div class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-800/40 dark:text-slate-300">
                <i class="bi bi-building text-lg text-blue-600 dark:text-blue-400" aria-hidden="true"></i>
                <p class="m-0 leading-relaxed">
                    <strong><?= e(__('Pickup at Barangay Hall')) ?>.</strong>
                    <?= e(__('You will get a notification when your document is ready. Bring a valid ID; any fee is paid at the Barangay Hall when you pick it up. No online payment is needed.')) ?>
                </p>
            </div>

            <button type="submit" id="docSubmitBtn"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-6 py-3.5 text-sm font-black text-white shadow-md hover:bg-blue-700 active:scale-[0.99] transition disabled:opacity-60">
                <i class="bi bi-send-fill" aria-hidden="true"></i>
                <span><?= e(__('Submit Request')) ?></span>
            </button>
        </form>
        <?php endif; ?>
    </div>

    <!-- ===================================================================== -->
    <!-- MY REQUESTS                                                           -->
    <!-- ===================================================================== -->
    <div id="sectionMyRequests" class="<?= $activeTab === 'requests' ? '' : 'hidden' ?>">

        <?php if (empty($requests)): ?>
        <div class="rounded-3xl border border-slate-200 bg-white p-12 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-400">
                <i class="bi bi-folder2 text-3xl"></i>
            </div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white"><?= e(__('Wala pang nakatalang kahilingan')) ?></h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 mb-5 max-w-sm mx-auto">
                <?= e(__('Wala ka pang isinusumiteng kahilingan ng dokumento sa Barangay Bayogo.')) ?>
            </p>
            <button type="button" onclick="switchMainTab('new')"
                    class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-blue-700 transition">
                <i class="bi bi-plus-lg"></i> <?= e(__('Gumawa ng Bagong Kahilingan')) ?>
            </button>
        </div>
        <?php else: ?>

        <div class="space-y-3">
            <?php foreach ($requests as $r):
                $reqId     = (int) $r['id'];
                $refNo     = (string) $r['reference_no'];
                $statusKey = strtolower((string) $r['status']);
                $isDigital = ($r['delivery_method'] ?? 'pickup') === 'digital';
                $fee       = (float) ($r['fee_amount'] ?? 0);
                $closed    = in_array($statusKey, ['rejected', 'cancelled'], true);
                $isReady   = in_array($statusKey, ['ready', 'ready_for_pickup', 'available_for_download'], true);
                $isDone    = in_array($statusKey, ['released', 'completed'], true);
                $canOpen   = $isDigital && !empty($r['document_file_name']) && empty($r['revoked_at'])
                             && DocumentRequest::isPaymentVerified($r);

                if ($isReady) {
                    $statusDisplay = $isDigital ? __('Available for Download') : __('Ready for Pickup');
                } elseif ($isDone) {
                    $statusDisplay = __('Completed');
                } else {
                    $statusDisplay = DocumentRequest::statusLabel($statusKey);
                }

                // Submitted → Under review → Ready for pickup → Claimed
                $stage = match (true) {
                    $isDone  => 3,
                    $isReady => 2,
                    in_array($statusKey, ['under_review', 'processing', 'approved', 'needs_information'], true) => 1,
                    default  => 0,
                };
                $steps = [__('Submitted'), __('Under Review'), $isDigital ? __('Ready to download') : __('Ready for pickup'), __('Completed')];
            ?>
            <div id="req-<?= e($refNo) ?>"
                 class="rounded-2xl border bg-white p-4 shadow-xs sm:p-5 dark:bg-slate-900 <?= $highlightRef === $refNo ? 'border-blue-400 ring-2 ring-blue-100 dark:border-blue-600 dark:ring-blue-950' : 'border-slate-200 dark:border-slate-800' ?>">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1">
                        <div class="mb-1 flex flex-wrap items-center gap-2">
                            <h3 class="truncate text-sm font-bold text-slate-900 dark:text-white"><?= e(DocumentRequest::label((string) $r['document_type'])) ?></h3>
                            <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300"><?= e($refNo) ?></span>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                            <span><i class="bi bi-calendar3 me-1"></i><?= e(date('M d, Y', strtotime((string) $r['requested_at']))) ?></span>
                            <span>&bull;</span>
                            <span><i class="bi bi-chat-left-text me-1"></i><?= e((string) $r['purpose']) ?></span>
                            <span>&bull;</span>
                            <span class="font-bold text-slate-700 dark:text-slate-300"><?= $fee > 0 ? '₱' . number_format($fee, 2) : e(__('LIBRE')) ?></span>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <?= status_badge($isReady ? ($isDigital ? 'available_for_download' : 'ready_for_pickup') : $statusKey, $statusDisplay) ?>
                        <?php if ($canOpen): ?>
                        <a href="<?= e(route('documents/' . $reqId . '/preview')) ?>" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            <i class="bi bi-eye" aria-hidden="true"></i> <?= e(__('Preview')) ?>
                        </a>
                        <a href="<?= e(route('documents/' . $reqId . '/download')) ?>"
                           class="inline-flex items-center gap-1 rounded-xl bg-blue-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-blue-700">
                            <i class="bi bi-download" aria-hidden="true"></i> <?= e(__('Download')) ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($isReady && !$isDigital): ?>
                <p class="mt-3 mb-0 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs font-semibold text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300">
                    <i class="bi bi-building-check me-1" aria-hidden="true"></i>
                    <?= e(__('Your document is ready. Pick it up at the Barangay Hall and bring a valid ID.')) ?>
                    <?php if ($fee > 0): ?><?= e(__('Fee to pay at the hall:')) ?> <strong>₱<?= number_format($fee, 2) ?></strong><?php endif; ?>
                </p>
                <?php endif; ?>

                <?php if (!empty($r['staff_note']) && ($closed || $statusKey === 'needs_information')): ?>
                <p class="mt-3 mb-0 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    <strong><?= e(__('Message from the barangay:')) ?></strong> <?= e((string) $r['staff_note']) ?>
                </p>
                <?php endif; ?>

                <?php if (!$closed): ?>
                <ol class="doc-timeline mt-3" aria-label="<?= e(__('Request progress')) ?>">
                    <?php foreach ($steps as $i => $label):
                        $state = $i < $stage || ($i === $stage && $isDone) ? 'done' : ($i === $stage ? 'current' : 'todo'); ?>
                    <li class="doc-timeline__step is-<?= $state ?>"<?= $state === 'current' ? ' aria-current="step"' : '' ?>>
                        <span class="doc-timeline__dot" aria-hidden="true"><?= $state === 'done' ? '✓' : '' ?></span>
                        <span class="doc-timeline__label"><?= e($label) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ol>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function switchMainTab(tab) {
    var on  = 'flex-1 inline-flex items-center justify-center gap-2 py-3 px-4 rounded-xl text-xs sm:text-sm font-bold transition-all bg-white text-blue-600 shadow-sm dark:bg-slate-900 dark:text-blue-400';
    var off = 'flex-1 inline-flex items-center justify-center gap-2 py-3 px-4 rounded-xl text-xs sm:text-sm font-bold transition-all text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white';
    var isNew = tab === 'new';
    var btnNew = document.getElementById('tabBtnNew'), btnReq = document.getElementById('tabBtnRequests');
    btnNew.className = isNew ? on : off;
    btnReq.className = isNew ? off : on;
    btnNew.setAttribute('aria-selected', isNew ? 'true' : 'false');
    btnReq.setAttribute('aria-selected', isNew ? 'false' : 'true');
    document.getElementById('sectionNewRequest').classList.toggle('hidden', !isNew);
    document.getElementById('sectionMyRequests').classList.toggle('hidden', isNew);
}

function onDocumentTypeChange() {
    var sel = document.getElementById('document_type');
    if (!sel || sel.selectedIndex < 0) { return; }
    var opt = sel.options[sel.selectedIndex];
    var amount = parseFloat(opt.getAttribute('data-amount') || '0');

    var isBiz = sel.value === 'business';
    document.getElementById('businessFields').classList.toggle('hidden', !isBiz);
    document.getElementById('business_type').required = isBiz;
    document.getElementById('business_name').required = isBiz;

    document.getElementById('docFee').textContent = amount > 0 ? '₱' + amount.toFixed(2) : <?= json_encode(__('LIBRE')) ?>;
    document.getElementById('docFeeNote').textContent = amount > 0
        ? <?= json_encode(__('Paid at the Barangay Hall on pickup')) ?>
        : <?= json_encode(__('No payment required')) ?>;

    // Description + requirements come from the admin's Document Fees page.
    var reqs = (opt.getAttribute('data-requirements') || '').split(/\r?\n/).map(function (x) { return x.trim(); }).filter(Boolean);
    if (!reqs.length) { reqs = [<?= json_encode(__('Valid Government ID')) ?>]; }
    var list = document.getElementById('docRequirements');
    list.textContent = '';
    reqs.forEach(function (x) { var li = document.createElement('li'); li.textContent = x; list.appendChild(li); });
    var desc = opt.getAttribute('data-description') || '';
    var descEl = document.getElementById('docDescription');
    descEl.textContent = desc;
    descEl.classList.toggle('hidden', desc === '');
}

function onPurposeChange(val) {
    var isOther = val === 'Other';
    var input = document.getElementById('purpose_other');
    document.getElementById('otherPurposeWrapper').classList.toggle('hidden', !isOther);
    input.required = isOther;
    if (isOther) { input.focus(); }
}

(function () {
    var form = document.getElementById('docRequestForm');
    if (form) {
        // One request per click: a double tap must not file it twice.
        form.addEventListener('submit', function () {
            document.getElementById('docSubmitBtn').disabled = true;
        });
        onDocumentTypeChange();
    }
    var ref = <?= json_encode($highlightRef) ?>;
    var card = ref ? document.getElementById('req-' + ref) : null;
    if (card) { card.scrollIntoView({ block: 'center' }); }
})();
</script>

<?php require __DIR__ . '/../shared/_live-refresh.php'; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
