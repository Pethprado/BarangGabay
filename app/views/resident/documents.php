<?php
/**
 * Resident Document Request & History Page — BarangGabay
 *
 * Requirements 2, 3, 4, 5, 6, 7, 10, 24, 25, 26, 27, 28, 49, 50, 51:
 * - Top tabs: [ NEW REQUEST ] and [ MY REQUESTS ] (never display side-by-side)
 * - 4-Step clean wizard for New Request:
 *     Step 1: Choose Document (with fee, requirements, processing time)
 *     Step 2: Review Resident Information (auto-loaded from DB profile, Verified badge)
 *     Step 3: Purpose (with 'Other' text field) and Receiving Method (Digital Copy vs Pickup at Barangay Hall)
 *     Step 4: Clean Order Summary & Pay with GCash button (double-click protected)
 * - My Requests tab: compact table/cards + View Details modal with real-time status tracker.
 */
use App\Models\DocumentRequest;
use App\Models\DocumentPayment;
use App\Models\User;

$requests     = $requests ?? [];
$types        = $types    ?? [];
$docFees      = $docFees  ?? \App\Models\DocumentFee::getAll();
$user         = $user     ?? [];

$isVerifiedResident = (($user['status'] ?? '') === 'verified');
$activeTab = $_GET['tab'] ?? (empty($requests) ? 'new' : 'requests');
if (!in_array($activeTab, ['new', 'requests'], true)) {
    $activeTab = 'new';
}

$pageTitle = 'Mga Dokumento at Kahilingan — BarangGabay';
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
</style>


<div class="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8">

    <!-- Page Header -->
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">Mga Kahilingan ng Dokumento</h1>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Opisyal na serbisyo sa paghiling at pagbabayad ng mga clearance at sertipiko ng Barangay Bayogo.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-100 dark:border-blue-900">
                <i class="bi bi-wallet2"></i> GCash via PayMongo
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                <i class="bi bi-folder2"></i> <?= count($requests) ?> <?= count($requests) === 1 ? 'Kahilingan' : 'Mga Kahilingan' ?>
            </span>
        </div>
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
                    <h3 class="text-sm font-bold text-amber-950 dark:text-amber-200">Kailangan ng Beripikasyon ng Residente (Account Verification Required)</h3>
                    <span class="rounded-full bg-amber-200 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-900">
                        Estado: <?= e(strtoupper($user['status'] ?? 'UNVERIFIED')) ?>
                    </span>
                </div>
                <p class="text-xs text-amber-800 dark:text-amber-300 mt-1 leading-relaxed">
                    Ang mga ganap na <strong>beripikadong residente</strong> lamang ang pinahihintulutang humiling ng mga opisyal na sertipiko at clearance. Mangyaring kumpletuhin ang inyong impormasyon sa inyong profile.
                </p>
                <div class="mt-3">
                    <a href="<?= e(route('profile')) ?>"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-amber-600 px-3.5 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-amber-700 transition">
                        <i class="bi bi-person-badge-fill"></i> Pumunta sa Profile & Magsumite ng Valid ID
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Top Tabs: NEW REQUEST vs MY REQUESTS (Requirement 50) -->
    <div class="mb-6 flex rounded-2xl bg-slate-100 p-1.5 dark:bg-slate-800">
        <button type="button" id="tabBtnNew" onclick="switchMainTab('new')"
                class="flex-1 inline-flex items-center justify-center gap-2 py-3 px-4 rounded-xl text-xs sm:text-sm font-bold transition-all <?= $activeTab === 'new' ? 'bg-white text-blue-600 shadow-sm dark:bg-slate-900 dark:text-blue-400' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' ?>">
            <i class="bi bi-file-earmark-plus text-base"></i>
            <span>HUMILING NG DOKUMENTO (NEW REQUEST)</span>
        </button>
        <button type="button" id="tabBtnRequests" onclick="switchMainTab('requests')"
                class="flex-1 inline-flex items-center justify-center gap-2 py-3 px-4 rounded-xl text-xs sm:text-sm font-bold transition-all <?= $activeTab === 'requests' ? 'bg-white text-blue-600 shadow-sm dark:bg-slate-900 dark:text-blue-400' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' ?>">
            <i class="bi bi-clock-history text-base"></i>
            <span>AKING MGA KAHILINGAN (MY REQUESTS)</span>
            <?php if (!empty($requests)): ?>
            <span class="rounded-full bg-blue-100 text-blue-800 px-2 py-0.5 text-xs font-black dark:bg-blue-950 dark:text-blue-300">
                <?= count($requests) ?>
            </span>
            <?php endif; ?>
        </button>
    </div>

    <!-- ========================================================================= -->
    <!-- SECTION 1: NEW REQUEST (STEP-BY-STEP WIZARD)                              -->
    <!-- ========================================================================= -->
    <div id="sectionNewRequest" class="<?= $activeTab === 'new' ? '' : 'hidden' ?>">
        
        <?php if (!$isVerifiedResident): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center dark:border-slate-800 dark:bg-slate-900">
            <i class="bi bi-shield-lock text-4xl text-slate-400 mb-2 block"></i>
            <h3 class="text-base font-bold text-slate-800 dark:text-white">Naka-lock ang Paghiling ng Dokumento</h3>
            <p class="text-xs text-slate-500 mt-1 mb-4 max-w-md mx-auto">
                Kailangan munang beripikahin ng kawani ng barangay ang inyong resident profile bago magsimulang humiling.
            </p>
            <a href="<?= e(route('profile')) ?>" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-blue-700">
                Kumpletuhin ang Beripikasyon
            </a>
        </div>
        <?php else: ?>

        <!-- Wizard Stepper Indicators -->
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid grid-cols-4 text-center text-xs font-bold">
                <div id="stepIndicator1" class="flex flex-col items-center text-blue-600 dark:text-blue-400">
                    <div class="step-circle flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-white font-black text-xs shadow-sm mb-1">
                        1
                    </div>
                    <span class="hidden sm:inline">Piliin ang Dokumento</span>
                    <span class="sm:hidden text-[11px]">Dokumento</span>
                </div>
                <div id="stepIndicator2" class="flex flex-col items-center text-slate-400">
                    <div class="step-circle flex h-8 w-8 items-center justify-center rounded-full bg-slate-200 text-slate-600 font-black text-xs mb-1 dark:bg-slate-800 dark:text-slate-400">
                        2
                    </div>
                    <span class="hidden sm:inline">Impormasyon ng Residente</span>
                    <span class="sm:hidden text-[11px]">Profile</span>
                </div>
                <div id="stepIndicator3" class="flex flex-col items-center text-slate-400">
                    <div class="step-circle flex h-8 w-8 items-center justify-center rounded-full bg-slate-200 text-slate-600 font-black text-xs mb-1 dark:bg-slate-800 dark:text-slate-400">
                        3
                    </div>
                    <span class="hidden sm:inline">Layunin & Pagtanggap</span>
                    <span class="sm:hidden text-[11px]">Layunin</span>
                </div>
                <div id="stepIndicator4" class="flex flex-col items-center text-slate-400">
                    <div class="step-circle flex h-8 w-8 items-center justify-center rounded-full bg-slate-200 text-slate-600 font-black text-xs mb-1 dark:bg-slate-800 dark:text-slate-400">
                        4
                    </div>
                    <span class="hidden sm:inline">Bayaran gamit ang GCash</span>
                    <span class="sm:hidden text-[11px]">Bayad</span>
                </div>
            </div>
        </div>

        <!-- Wizard Card Form -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-md sm:p-8 dark:border-slate-800 dark:bg-slate-900">
            <form id="wizardDocForm" onsubmit="event.preventDefault(); submitWizardForm();">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="is_ajax" value="1">
                <input type="hidden" name="payment_method" value="gcash">

                <!-- ── STEP 1: CHOOSE DOCUMENT ──────────────────────────── -->
                <div id="wizardStep1" class="wizard-step">
                    <div class="mb-5 pb-3 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] font-black uppercase tracking-wider text-blue-600 dark:text-blue-400 block mb-1">HAKBANG 1 SA 4</span>
                        <h2 class="text-xl font-black text-slate-900 dark:text-white">Piliin ang Dokumento (Choose Document)</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Pumili ng opisyal na sertipiko o clearance na nais hilingin sa Barangay Bayogo.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label for="wizard_document_type" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                                Uri ng Dokumento <span class="text-rose-500">*</span>
                            </label>
                            <select id="wizard_document_type" name="document_type" onchange="onDocumentTypeChange()"
                                    class="w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-900 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                <?php foreach ($types as $key => $label): 
                                    $f = $docFees[$key] ?? ['amount' => 50.0, 'is_free' => false];
                                    $isFree = $f['is_free'] || (float)$f['amount'] <= 0;
                                    $feeTxt = $isFree ? ' (LIBRE)' : sprintf(' (₱%.2f)', (float)$f['amount']);
                                ?>
                                <option value="<?= e($key) ?>"
                                        data-amount="<?= (float)$f['amount'] ?>"
                                        data-free="<?= $isFree ? '1' : '0' ?>"
                                        data-label="<?= e($label) ?>">
                                    <?= e($label) . $feeTxt ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Document Details Preview (Requirement 3) -->
                        <div class="rounded-2xl border border-blue-100 bg-blue-50/50 p-4 dark:border-blue-900/60 dark:bg-blue-950/30">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                                <div class="rounded-xl bg-white p-3 border border-blue-100/80 shadow-xs dark:bg-slate-900 dark:border-slate-800">
                                    <span class="text-[10px] font-bold uppercase text-slate-400 block mb-0.5">Bayad sa Dokumento</span>
                                    <span class="text-lg font-black text-blue-600 dark:text-blue-400" id="step1FeeDisplay">₱50.00</span>
                                </div>
                                <div class="rounded-xl bg-white p-3 border border-blue-100/80 shadow-xs dark:bg-slate-900 dark:border-slate-800">
                                    <span class="text-[10px] font-bold uppercase text-slate-400 block mb-0.5">Mga Kinakailangan</span>
                                    <span class="font-bold text-slate-800 dark:text-slate-200" id="step1ReqsDisplay">Valid Government ID</span>
                                </div>
                                <div class="rounded-xl bg-white p-3 border border-blue-100/80 shadow-xs dark:bg-slate-900 dark:border-slate-800">
                                    <span class="text-[10px] font-bold uppercase text-slate-400 block mb-0.5">Processing Time</span>
                                    <span class="font-bold text-emerald-600 dark:text-emerald-400" id="step1TimeDisplay">1–2 business days</span>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 flex justify-end">
                            <button type="button" onclick="goToStep(2)"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-6 py-3 text-xs sm:text-sm font-bold text-white shadow-md hover:bg-blue-700 active:scale-[0.99] transition">
                                <span>Magpatuloy (Continue)</span>
                                <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── STEP 2: RESIDENT INFORMATION ─────────────────────── -->
                <div id="wizardStep2" class="wizard-step hidden">
                    <div class="mb-5 pb-3 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] font-black uppercase tracking-wider text-blue-600 dark:text-blue-400 block mb-1">HAKBANG 2 SA 4</span>
                        <h2 class="text-xl font-black text-slate-900 dark:text-white">Impormasyon ng Residente (Resident Information)</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Awtomatikong kinuha mula sa inyong opisyal na rehistradong profile sa database.
                        </p>
                    </div>

                    <!-- Read-only verified profile summary (Requirement 4) -->
                    <?php 
                        $userArr = is_array($user) ? $user : [];
                        $userFullName = !empty($userArr) ? User::formatFullName($userArr) : ($user['name'] ?? 'Residente');
                        $userAddress  = !empty($userArr) ? User::formatAddress($userArr) : 'Barangay Bayogo';
                    ?>
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-5 dark:border-emerald-900 dark:bg-emerald-950/20">
                        <div class="flex items-center justify-between border-b border-emerald-200/60 pb-3 mb-3.5 dark:border-emerald-800">
                            <div class="flex items-center gap-2">
                                <i class="bi bi-person-check-fill text-xl text-emerald-600 dark:text-emerald-400"></i>
                                <span class="text-sm font-black text-slate-900 dark:text-white">
                                    <?= e($userFullName) ?>
                                </span>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1 text-xs font-black text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
                                <i class="bi bi-check-circle-fill"></i> Verified ✓
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div>
                                <span class="text-slate-400 block text-[10px] font-bold uppercase">Buong Pangalan:</span>
                                <strong class="text-slate-800 dark:text-slate-200"><?= e($userFullName) ?></strong>
                            </div>
                            <div>
                                <span class="text-slate-400 block text-[10px] font-bold uppercase">Kaarawan & Edad:</span>
                                <strong class="text-slate-800 dark:text-slate-200">
                                    <?= !empty($user['date_of_birth']) ? date('F d, Y', strtotime($user['date_of_birth'])) : 'N/A' ?>
                                    (<?= User::getAge($user['date_of_birth'] ?? null) !== null ? User::getAge($user['date_of_birth']) . ' anyos' : 'N/A' ?>)
                                </strong>
                            </div>
                            <div>
                                <span class="text-slate-400 block text-[10px] font-bold uppercase">Katayuang Sibil / Kasarian:</span>
                                <strong class="text-slate-800 dark:text-slate-200">
                                    <?= e(ucfirst($user['civil_status'] ?? 'Single')) ?> &bull; <?= e(ucfirst($user['sex'] ?? 'N/A')) ?>
                                </strong>
                            </div>
                            <div>
                                <span class="text-slate-400 block text-[10px] font-bold uppercase">Contact Number:</span>
                                <strong class="text-slate-800 dark:text-slate-200"><?= e($user['phone'] ?? 'N/A') ?></strong>
                            </div>
                            <div class="sm:col-span-2">
                                <span class="text-slate-400 block text-[10px] font-bold uppercase">Tirahan (Address):</span>
                                <strong class="text-slate-800 dark:text-slate-200"><?= e($userAddress) ?></strong>
                            </div>
                            <?php if (!empty($user['household_no'])): ?>
                            <div class="sm:col-span-2">
                                <span class="text-slate-400 block text-[10px] font-bold uppercase">Household Number:</span>
                                <strong class="font-mono text-indigo-700 dark:text-indigo-400"><?= e($user['household_no']) ?></strong>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="mt-4 border-t border-emerald-200/60 pt-2 text-[11px] text-emerald-800 dark:text-emerald-300">
                            * Ang mga impormasyong ito ay awtomatikong ilalagay sa opisyal na sertipiko. Hindi mo na kailangang mag-type muli.
                        </div>
                    </div>

                    <div class="pt-5 flex items-center justify-between">
                        <button type="button" onclick="goToStep(1)"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            <i class="bi bi-arrow-left"></i>
                            <span>Bumalik</span>
                        </button>
                        <button type="button" onclick="goToStep(3)"
                                class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-3 text-xs sm:text-sm font-bold text-white shadow-md hover:bg-blue-700 active:scale-[0.99] transition">
                            <span>Magpatuloy (Continue)</span>
                            <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- ── STEP 3: PURPOSE & RECEIVING METHOD ───────────────── -->
                <div id="wizardStep3" class="wizard-step hidden">
                    <div class="mb-5 pb-3 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] font-black uppercase tracking-wider text-blue-600 dark:text-blue-400 block mb-1">HAKBANG 3 SA 4</span>
                        <h2 class="text-xl font-black text-slate-900 dark:text-white">Layunin at Paraan ng Pagtanggap</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Piliin kung para saan ang dokumento at kung paano mo ito nais matanggap.
                        </p>
                    </div>

                    <div class="space-y-5">
                        <!-- Purpose (Requirement 5) -->
                        <div>
                            <label for="wizard_purpose" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                                Layunin (Purpose) <span class="text-rose-500">*</span>
                            </label>
                            <select id="wizard_purpose" name="purpose" required onchange="onPurposeChange(this.value)"
                                    class="w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-900 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                <option value="Employment">Employment (Paghahanapbuhay / Trabaho)</option>
                                <option value="School">School (Eskwela / Scholarship / Enrollment)</option>
                                <option value="Business">Business (Negosyo / Permit)</option>
                                <option value="Government Requirement">Government Requirement (SSS, PhilHealth, Pag-IBIG, ID)</option>
                                <option value="Bank Requirement">Bank Requirement (Pagbubukas ng Account / Loan)</option>
                                <option value="Travel">Travel (Lokal o Pang-ibayong Dagat)</option>
                                <option value="Other">Other (Iba pang layunin...)</option>
                            </select>

                            <!-- Other Purpose Text Field -->
                            <div id="otherPurposeWrapper" class="mt-2.5 hidden">
                                <label for="wizard_purpose_other" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">
                                    Tukuyin ang ibang layunin: <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" id="wizard_purpose_other" name="purpose_other" maxlength="150"
                                       placeholder="Halimbawa: Police clearance requirement, scholarship application, atbp."
                                       class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-xs text-slate-900 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                            </div>
                        </div>

                        <!-- Receiving Method (Requirement 5 & 6: Digital Copy vs Pickup at Barangay Hall ONLY) -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-2">
                                Paano mo nais matanggap ang dokumento? <span class="text-rose-500">*</span>
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <!-- Digital Option -->
                                <label class="receiving-card relative flex cursor-pointer items-start gap-3 rounded-2xl border-2 border-blue-600 bg-blue-50/30 p-4 transition dark:border-blue-500 dark:bg-blue-950/20">
                                    <input type="radio" name="delivery_method" value="digital" checked onchange="updateReceivingMethod('digital')"
                                           class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-sm font-bold text-slate-900 dark:text-white">DIGITAL COPY</span>
                                            <span class="rounded bg-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300">PDF Soft Copy</span>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                            Awtomatikong mai-download ang opisyal na PDF na may QR Code verification kapag naaprubahan.
                                        </p>
                                    </div>
                                </label>

                                <!-- Pickup Option -->
                                <label class="receiving-card relative flex cursor-pointer items-start gap-3 rounded-2xl border-2 border-slate-200 bg-white p-4 transition hover:border-slate-300 dark:border-slate-800 dark:bg-slate-800/40">
                                    <input type="radio" name="delivery_method" value="pickup" onchange="updateReceivingMethod('pickup')"
                                           class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-sm font-bold text-slate-900 dark:text-white">PICKUP AT BARANGAY HALL</span>
                                            <span class="rounded bg-slate-200 px-2 py-0.5 text-[10px] font-bold text-slate-700 dark:bg-slate-700 dark:text-slate-300">Counter</span>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                            Kukunin ang pisikal na orihinal na dokumento sa Barangay Hall kapag handa na.
                                        </p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Notes (Optional) -->
                        <div>
                            <label for="wizard_notes" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">
                                Karagdagang Paalala (Opsyonal):
                            </label>
                            <textarea id="wizard_notes" name="notes" rows="2" maxlength="300"
                                      placeholder="Maaaring maglagay ng karagdagang impormasyon para sa kawani ng barangay..."
                                      class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs text-slate-900 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"></textarea>
                        </div>
                    </div>

                    <div class="pt-5 flex items-center justify-between">
                        <button type="button" onclick="goToStep(2)"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            <i class="bi bi-arrow-left"></i>
                            <span>Bumalik</span>
                        </button>
                        <button type="button" onclick="goToStep(4)"
                                class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-3 text-xs sm:text-sm font-bold text-white shadow-md hover:bg-blue-700 active:scale-[0.99] transition">
                            <span>Suriin ang Bayad (Review Fee)</span>
                            <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- ── STEP 4: ORDER SUMMARY & PAY WITH GCASH ───────────── -->
                <div id="wizardStep4" class="wizard-step hidden">
                    <div class="mb-5 pb-3 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] font-black uppercase tracking-wider text-blue-600 dark:text-blue-400 block mb-1">HAKBANG 4 SA 4</span>
                        <h2 class="text-xl font-black text-slate-900 dark:text-white">Kumpirmasyon at Pagbabayad</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Suriin ang kabuuang detalye at magbayad gamit ang GCash via PayMongo.
                        </p>
                    </div>

                    <!-- Clean Request Summary (Requirement 24) -->
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-800/40 mb-5">
                        <div class="text-xs font-black uppercase tracking-wider text-slate-400 mb-3">REQUEST SUMMARY</div>

                        <div class="space-y-3 text-xs">
                            <div class="flex items-center justify-between border-b border-slate-200/60 pb-2 dark:border-slate-700">
                                <span class="text-slate-500 dark:text-slate-400">Dokumento (Document):</span>
                                <strong class="text-slate-900 dark:text-white" id="summaryDocName">Barangay Clearance</strong>
                            </div>
                            <div class="flex items-center justify-between border-b border-slate-200/60 pb-2 dark:border-slate-700">
                                <span class="text-slate-500 dark:text-slate-400">Paraan ng Pagtanggap:</span>
                                <strong class="text-slate-900 dark:text-white" id="summaryReceiving">Digital Copy</strong>
                            </div>
                            <div class="flex items-center justify-between border-b border-slate-200/60 pb-2 dark:border-slate-700">
                                <span class="text-slate-500 dark:text-slate-400">Layunin (Purpose):</span>
                                <strong class="text-slate-900 dark:text-white" id="summaryPurpose">Employment</strong>
                            </div>
                            <div class="flex items-center justify-between border-b border-slate-200/60 pb-2 dark:border-slate-700">
                                <span class="text-slate-500 dark:text-slate-400">Bayad sa Dokumento:</span>
                                <span class="font-bold text-slate-900 dark:text-white" id="summaryDocFee">₱50.00</span>
                            </div>
                            <div class="flex items-center justify-between pt-1">
                                <span class="text-sm font-black text-slate-900 dark:text-white">KABUUAN (TOTAL):</span>
                                <span class="text-xl font-black text-blue-600 dark:text-blue-400" id="summaryTotal">₱50.00</span>
                            </div>
                        </div>

                        <!-- Payment Method Box (Requirement 7: GCash via PayMongo Only) -->
                        <div id="summaryPaymentMethodBox" class="mt-4 pt-3 border-t border-slate-200/80 dark:border-slate-700 flex items-center justify-between">
                            <span class="text-xs text-slate-500 dark:text-slate-400">Paraan ng Pagbabayad:</span>
                            <span class="inline-flex items-center gap-1.5 font-bold text-xs text-blue-700 dark:text-blue-300">
                                <i class="bi bi-check-circle-fill text-blue-600"></i> GCash via PayMongo
                            </span>
                        </div>
                    </div>

                    <!-- Alert message container -->
                    <div id="wizardAlertBox" class="hidden mb-4 rounded-xl p-3 text-xs font-semibold"></div>

                    <!-- Action Buttons -->
                    <div class="pt-2 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <button type="button" onclick="goToStep(3)"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            <i class="bi bi-arrow-left"></i>
                            <span>Bumalik</span>
                        </button>

                        <!-- Pay Button with double click protection (Requirement 38) -->
                        <button type="submit" id="wizardSubmitBtn"
                                class="w-full sm:w-auto flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-3.5 text-sm font-black text-white shadow-lg shadow-blue-500/25 hover:from-blue-700 hover:to-indigo-700 active:scale-[0.99] transition">
                            <i class="bi bi-lock-fill"></i>
                            <span id="wizardSubmitBtnText">BAYARAN ANG ₱50.00 GAMIT ANG GCASH</span>
                        </button>
                    </div>
                </div>

            </form>
        </div>
        <?php endif; ?>

    </div>

    <!-- ========================================================================= -->
    <!-- SECTION 2: MY REQUESTS (COMPACT LIST & DETAIL MODAL)                      -->
    <!-- ========================================================================= -->
    <div id="sectionMyRequests" class="<?= $activeTab === 'requests' ? '' : 'hidden' ?>">

        <?php if (empty($requests)): ?>
        <div class="rounded-3xl border border-slate-200 bg-white p-12 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-400">
                <i class="bi bi-folder2 text-3xl"></i>
            </div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white">Wala pang nakatalang kahilingan</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 mb-5 max-w-sm mx-auto">
                Wala ka pang isinusumiteng kahilingan ng dokumento sa Barangay Bayogo.
            </p>
            <button type="button" onclick="switchMainTab('new')"
                    class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-blue-700 transition">
                <i class="bi bi-plus-lg"></i> Gumawa ng Bagong Kahilingan
            </button>
        </div>
        <?php else: ?>

        <!-- Compact Requests Container (Requirement 25) -->
        <div class="space-y-3">
            <?php foreach ($requests as $r):
                $reqId      = (int) $r['id'];
                $docLabel   = DocumentRequest::label((string) $r['document_type']);
                $refNo      = (string) $r['reference_no'];
                $statusKey  = (string) $r['status'];
                $deliv      = (string) ($r['delivery_method'] ?? 'pickup');
                $isDigital  = ($deliv === 'digital');
                $fee        = (float) ($r['fee_amount'] ?? 0);
                $payStatus  = (string) ($r['payment_status'] ?? 'UNPAID');
                $isPaid     = DocumentRequest::isPaymentVerified($r);
                $hasFile    = !empty($r['document_file_name']);

                // Status Labels
                $statusDisplay = DocumentRequest::statusLabel($statusKey);
                if ($isDigital && $statusKey === 'ready') {
                    $statusDisplay = 'Available for Download';
                } elseif (!$isDigital && $statusKey === 'ready') {
                    $statusDisplay = 'Ready for Pickup';
                }

                // Payment Status Label
                $payDisplay = 'Awaiting Payment';
                $payBadgeClass = 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300';
                if ($fee <= 0.0 || $payStatus === DocumentPayment::STATUS_FREE || $payStatus === DocumentPayment::STATUS_NOT_REQUIRED) {
                    $payDisplay = 'Not Required';
                    $payBadgeClass = 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
                } elseif ($isPaid) {
                    $payDisplay = 'Paid ✓';
                    $payBadgeClass = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300';
                } elseif (in_array($payStatus, ['FAILED', 'PAYMENT_REJECTED'], true)) {
                    $payDisplay = 'Failed';
                    $payBadgeClass = 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300';
                } elseif ($payStatus === 'CANCELLED') {
                    $payDisplay = 'Cancelled';
                    $payBadgeClass = 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400';
                }
            ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs transition hover:border-slate-300 sm:p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    
                    <!-- Document Info -->
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white truncate">
                                <?= e($docLabel) ?>
                            </h3>
                            <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                <?= e($refNo) ?>
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                            <span><i class="bi bi-calendar3 me-1"></i><?= date('M d, Y', strtotime($r['requested_at'])) ?></span>
                            <span>&bull;</span>
                            <span>
                                <i class="bi bi-box-seam me-1"></i><?= $isDigital ? 'Digital Copy' : 'Pickup at Hall' ?>
                            </span>
                            <?php if ($fee > 0): ?>
                            <span>&bull;</span>
                            <span class="font-bold text-slate-700 dark:text-slate-300">₱<?= number_format($fee, 2) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Statuses & Action -->
                    <div class="flex flex-wrap items-center gap-2 justify-between sm:justify-end">
                        <div class="flex items-center gap-1.5">
                            <!-- Payment + request status: shared colours (status_tone) -->
                            <?= status_badge($isPaid ? 'paid' : (in_array($payStatus, ['FAILED', 'PAYMENT_REJECTED'], true) ? 'failed' : ($fee <= 0 ? 'draft' : ($payStatus === 'CANCELLED' ? 'cancelled' : 'awaiting_payment'))), $payDisplay) ?>
                            <?= status_badge($statusKey === 'ready' ? ($isDigital ? 'available_for_download' : 'ready_for_pickup') : $statusKey, $statusDisplay) ?>
                        </div>

                        <!-- Action: View Details or Pay -->
                        <div class="flex items-center gap-1.5">
                            <?php if (!$isPaid && $fee > 0): ?>
                            <a href="<?= e(route('documents/' . $reqId . '/payment')) ?>"
                               class="inline-flex items-center gap-1 rounded-xl bg-blue-600 px-3 py-1.5 text-xs font-bold text-white shadow-xs hover:bg-blue-700 transition">
                                <i class="bi bi-credit-card-2-front"></i> Pay GCash
                            </a>
                            <?php endif; ?>

                            <button type="button" onclick="openDetailsModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>)"
                                    class="inline-flex items-center gap-1 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                <i class="bi bi-eye"></i> View Details
                            </button>
                        </div>
                    </div>

                </div>
                <?php
                // Progress timeline: where this request is in the workflow.
                $__stage = match (strtolower($statusKey)) {
                    'awaiting_payment'                                   => 1,
                    'under_review', 'processing', 'approved', 'needs_information' => 2,
                    'ready', 'ready_for_pickup', 'available_for_download' => 3,
                    'released', 'completed'                              => 4,
                    default                                               => 0,
                };
                if ($__stage < 2 && $isPaid && $fee > 0) { $__stage = max($__stage, 2); }
                $__steps = ['Submitted', 'Payment', 'Processing', $isDigital ? 'Ready to download' : 'Ready for pickup', 'Completed'];
                if ($fee <= 0) { unset($__steps[1]); }
                $__closed = in_array(strtolower($statusKey), ['rejected', 'cancelled'], true);
                ?>
                <?php if (!$__closed): ?>
                <ol class="doc-timeline mt-3" aria-label="Request progress">
                    <?php foreach ($__steps as $__i => $__label):
                        $__state = $__i < $__stage ? 'done' : ($__i === $__stage ? 'current' : 'todo'); ?>
                        <li class="doc-timeline__step is-<?= $__state ?>"<?= $__state === 'current' ? ' aria-current="step"' : '' ?>>
                            <span class="doc-timeline__dot" aria-hidden="true"><?= $__state === 'done' ? '✓' : '' ?></span>
                            <span class="doc-timeline__label"><?= e($__label) ?></span>
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

<!-- ========================================================================= -->
<!-- REQUEST DETAILS MODAL (Requirement 26)                                     -->
<!-- ========================================================================= -->
<div id="detailsModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-900/60 p-4 sm:p-6 flex items-center justify-center backdrop-blur-xs transition-opacity">
    <div class="relative w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl dark:bg-slate-900 dark:border dark:border-slate-800 transition-all">
        
        <!-- Modal Header -->
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4 dark:border-slate-800">
            <div>
                <h3 class="text-base font-black text-slate-900 dark:text-white" id="modalDocTitle">REQUEST DETAILS</h3>
                <span class="font-mono text-xs font-bold text-blue-600 dark:text-blue-400" id="modalRefNo"></span>
            </div>
            <button type="button" onclick="closeDetailsModal()" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800">
                <i class="bi bi-x-lg text-lg"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="space-y-4 text-xs">

            <!-- Request Details Box -->
            <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-3.5 space-y-2 dark:border-slate-800 dark:bg-slate-800/40">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">DETALYE NG KAHILINGAN</div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Dokumento:</span>
                    <strong class="text-slate-900 dark:text-white" id="modalDocName"></strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Layunin (Purpose):</span>
                    <strong class="text-slate-900 dark:text-white" id="modalPurpose"></strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Petsa ng Paghiling:</span>
                    <strong class="text-slate-900 dark:text-white" id="modalDate"></strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Paraan ng Pagtanggap:</span>
                    <strong class="text-slate-900 dark:text-white" id="modalReceiving"></strong>
                </div>
            </div>

            <!-- Payment Details Box -->
            <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-3.5 space-y-2 dark:border-slate-800 dark:bg-slate-800/40">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">PAGBABAYAD (PAYMENT)</div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Halaga:</span>
                    <strong class="text-slate-900 dark:text-white" id="modalAmount"></strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Paraan ng Bayad:</span>
                    <strong class="text-slate-900 dark:text-white" id="modalPaymentMethod">GCash via PayMongo</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Estado ng Bayad:</span>
                    <span id="modalPaymentStatus" class="font-bold"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Payment Reference:</span>
                    <span class="font-mono font-bold text-slate-900 dark:text-white" id="modalPayRef"></span>
                </div>
            </div>

            <!-- Process Timeline Tracker (Requirement 26) -->
            <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-3.5 dark:border-slate-800 dark:bg-slate-800/40">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2.5">PROSESO (TIMELINE)</div>
                <div class="space-y-2 text-xs" id="modalTimeline">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>

            <!-- Pickup or Download Message Alert -->
            <div id="modalNoticeBox" class="hidden rounded-xl p-3 text-xs font-semibold"></div>

            <!-- Action Buttons inside Modal -->
            <div class="pt-2 flex flex-col sm:flex-row gap-2 justify-end" id="modalActionButtons">
                <!-- Populated dynamically via JS -->
            </div>

        </div>

    </div>
</div>

<script>
var docFeesData = <?= json_encode($docFees) ?>;
var currentStep = 1;
var selectedFee = 50.0;
var isSelectedFree = false;

function switchMainTab(tab) {
    var btnNew = document.getElementById('tabBtnNew');
    var btnReq = document.getElementById('tabBtnRequests');
    var secNew = document.getElementById('sectionNewRequest');
    var secReq = document.getElementById('sectionMyRequests');

    if (tab === 'new') {
        btnNew.className = "flex-1 inline-flex items-center justify-center gap-2 py-3 px-4 rounded-xl text-xs sm:text-sm font-bold transition-all bg-white text-blue-600 shadow-sm dark:bg-slate-900 dark:text-blue-400";
        btnReq.className = "flex-1 inline-flex items-center justify-center gap-2 py-3 px-4 rounded-xl text-xs sm:text-sm font-bold transition-all text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white";
        secNew.classList.remove('hidden');
        secReq.classList.add('hidden');
    } else {
        btnReq.className = "flex-1 inline-flex items-center justify-center gap-2 py-3 px-4 rounded-xl text-xs sm:text-sm font-bold transition-all bg-white text-blue-600 shadow-sm dark:bg-slate-900 dark:text-blue-400";
        btnNew.className = "flex-1 inline-flex items-center justify-center gap-2 py-3 px-4 rounded-xl text-xs sm:text-sm font-bold transition-all text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white";
        secReq.classList.remove('hidden');
        secNew.classList.add('hidden');
    }
}

function onDocumentTypeChange() {
    var sel = document.getElementById('wizard_document_type');
    var opt = sel.options[sel.selectedIndex];
    var amt = parseFloat(opt.getAttribute('data-amount') || '50');
    var isFree = (opt.getAttribute('data-free') === '1' || amt <= 0);

    selectedFee = amt;
    isSelectedFree = isFree;

    var feeText = isFree ? 'LIBRE (₱0.00)' : '₱' + amt.toFixed(2);
    document.getElementById('step1FeeDisplay').innerText = feeText;
    document.getElementById('summaryDocFee').innerText = feeText;
    document.getElementById('summaryTotal').innerText = isFree ? '₱0.00' : '₱' + amt.toFixed(2);
    document.getElementById('summaryDocName').innerText = opt.getAttribute('data-label') || opt.text;

    var submitBtnText = document.getElementById('wizardSubmitBtnText');
    var paymentBox = document.getElementById('summaryPaymentMethodBox');
    if (isFree) {
        submitBtnText.innerText = 'ISUMITE ANG KAHILINGAN (LIBRE)';
        paymentBox.classList.add('hidden');
    } else {
        submitBtnText.innerText = 'BAYARAN ANG ₱' + amt.toFixed(2) + ' GAMIT ANG GCASH';
        paymentBox.classList.remove('hidden');
    }
}

function onPurposeChange(val) {
    var otherWrap = document.getElementById('otherPurposeWrapper');
    var otherInput = document.getElementById('wizard_purpose_other');
    if (val === 'Other') {
        otherWrap.classList.remove('hidden');
        otherInput.required = true;
        otherInput.focus();
    } else {
        otherWrap.classList.add('hidden');
        otherInput.required = false;
    }
}

function updateReceivingMethod(method) {
    document.getElementById('summaryReceiving').innerText = (method === 'digital') ? 'Digital Copy (Online)' : 'Pickup at Barangay Hall';
}

function goToStep(step) {
    // Validate step transition
    if (step === 4) {
        var pur = document.getElementById('wizard_purpose').value;
        if (pur === 'Other') {
            var oth = document.getElementById('wizard_purpose_other').value.trim();
            if (oth.length < 3) {
                alert('Mangyaring ilagay ang layunin sa text field.');
                document.getElementById('wizard_purpose_other').focus();
                return;
            }
            document.getElementById('summaryPurpose').innerText = oth;
        } else {
            document.getElementById('summaryPurpose').innerText = pur;
        }
    }

    currentStep = step;
    for (var i = 1; i <= 4; i++) {
        var el = document.getElementById('wizardStep' + i);
        var ind = document.getElementById('stepIndicator' + i);
        var circle = ind.querySelector('.step-circle');

        if (i === step) {
            el.classList.remove('hidden');
            ind.className = "flex flex-col items-center text-blue-600 dark:text-blue-400";
            circle.className = "step-circle flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-white font-black text-xs shadow-sm mb-1";
        } else if (i < step) {
            el.classList.add('hidden');
            ind.className = "flex flex-col items-center text-emerald-600 dark:text-emerald-400";
            circle.className = "step-circle flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500 text-white font-black text-xs mb-1";
            circle.innerHTML = '<i class="bi bi-check-lg"></i>';
        } else {
            el.classList.add('hidden');
            ind.className = "flex flex-col items-center text-slate-400";
            circle.className = "step-circle flex h-8 w-8 items-center justify-center rounded-full bg-slate-200 text-slate-600 font-black text-xs mb-1 dark:bg-slate-800 dark:text-slate-400";
            circle.innerText = i;
        }
    }

    window.scrollTo({ top: 100, behavior: 'smooth' });
}

function submitWizardForm() {
    var btn = document.getElementById('wizardSubmitBtn');
    var btnText = document.getElementById('wizardSubmitBtnText');
    var alertBox = document.getElementById('wizardAlertBox');

    // Requirement 38: Prevent double click
    btn.disabled = true;
    btnText.innerText = 'CREATING SECURE PAYMENT...';

    var form = document.getElementById('wizardDocForm');
    var formData = new FormData(form);

    fetch('<?= e(route('documents')) ?>', {
        method: 'POST',
        body: formData,
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(res) {
        return res.json();
    })
    .then(function(data) {
        if (!data || !data.success) {
            btn.disabled = false;
            btnText.innerText = isSelectedFree ? 'ISUMITE ANG KAHILINGAN (LIBRE)' : 'BAYARAN ANG ₱' + selectedFee.toFixed(2) + ' GAMIT ANG GCASH';
            alertBox.className = "mb-4 rounded-xl p-3.5 text-xs font-semibold bg-rose-50 text-rose-800 border border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-900";
            alertBox.innerText = data.error || 'Nabigong likhain ang kahilingan.';
            alertBox.classList.remove('hidden');
            return;
        }

        // If free document, redirect to requests list
        if (data.is_free) {
            window.location.href = data.redirect_url || '<?= e(route('documents?tab=requests')) ?>';
            return;
        }

        // If paid document with PayMongo checkout URL, redirect immediately
        if (data.checkout_url) {
            btnText.innerText = 'REDIRECTING TO GCASH VIA PAYMONGO...';
            window.location.href = data.checkout_url;
            return;
        }

        // Fallback
        if (data.fallback_url) {
            window.location.href = data.fallback_url;
        }
    })
    .catch(function(err) {
        console.error('Submission error:', err);
        btn.disabled = false;
        btnText.innerText = isSelectedFree ? 'ISUMITE ANG KAHILINGAN (LIBRE)' : 'BAYARAN ANG ₱' + selectedFee.toFixed(2) + ' GAMIT ANG GCASH';
        alertBox.className = "mb-4 rounded-xl p-3.5 text-xs font-semibold bg-rose-50 text-rose-800 border border-rose-200";
        alertBox.innerText = 'May naganap na aberya sa koneksyon. Pakisubukan muli.';
        alertBox.classList.remove('hidden');
    });
}

function openDetailsModal(r) {
    document.getElementById('modalRefNo').innerText = r.reference_no || '';
    document.getElementById('modalDocName').innerText = r.document_type || '';
    document.getElementById('modalPurpose').innerText = r.purpose || '';
    document.getElementById('modalDate').innerText = r.requested_at ? new Date(r.requested_at).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' }) : '';
    document.getElementById('modalReceiving').innerText = (r.delivery_method === 'digital') ? 'Digital Copy' : 'Pickup at Barangay Hall';

    var fee = parseFloat(r.fee_amount || 0);
    document.getElementById('modalAmount').innerText = fee > 0 ? '₱' + fee.toFixed(2) : 'LIBRE';
    document.getElementById('modalPayRef').innerText = r.payment_ref || (r.reference_no ? 'PAY-' + r.reference_no : 'N/A');

    var pst = (r.payment_status || 'UNPAID').toUpperCase();
    var isPaid = (pst === 'PAID' || pst === 'PAID_VERIFIED' || pst === 'PAID_AT_PICKUP' || pst === 'FREE' || pst === 'NOT_REQUIRED' || fee <= 0);
    var pstEl = document.getElementById('modalPaymentStatus');
    if (isPaid) {
        pstEl.className = "font-bold text-emerald-600 dark:text-emerald-400";
        pstEl.innerText = "PAID ✓";
    } else {
        pstEl.className = "font-bold text-amber-600 dark:text-amber-400";
        pstEl.innerText = pst;
    }

    // Process Timeline (Requirement 26)
    var st = (r.status || 'pending').toLowerCase();
    var timelineHtml = '';

    // Step 1: Request Submitted
    timelineHtml += '<div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400"><i class="bi bi-check-circle-fill"></i> <span>✓ Request Submitted</span></div>';

    // Step 2: Payment Confirmed
    if (isPaid) {
        timelineHtml += '<div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400"><i class="bi bi-check-circle-fill"></i> <span>✓ Payment Confirmed</span></div>';
    } else {
        timelineHtml += '<div class="flex items-center gap-2 text-amber-600 dark:text-amber-400"><i class="bi bi-circle"></i> <span>○ Awaiting GCash Payment</span></div>';
    }

    // Step 3: Barangay Review
    if (['approved', 'ready', 'released', 'completed'].indexOf(st) !== -1) {
        timelineHtml += '<div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400"><i class="bi bi-check-circle-fill"></i> <span>✓ Barangay Review Completed</span></div>';
    } else if (st === 'rejected') {
        timelineHtml += '<div class="flex items-center gap-2 text-rose-600 dark:text-rose-400"><i class="bi bi-x-circle-fill"></i> <span>✗ Disapproved by Barangay</span></div>';
    } else {
        timelineHtml += '<div class="flex items-center gap-2 text-blue-600 dark:text-blue-400 font-bold"><i class="bi bi-record-circle-fill"></i> <span>● Barangay Review Ongoing</span></div>';
    }

    // Step 4: Approved & Released
    if (['ready', 'released', 'completed'].indexOf(st) !== -1) {
        timelineHtml += '<div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400"><i class="bi bi-check-circle-fill"></i> <span>✓ Approved & Document Ready</span></div>';
    } else {
        timelineHtml += '<div class="flex items-center gap-2 text-slate-400"><i class="bi bi-circle"></i> <span>○ Document Released / Ready for Pickup</span></div>';
    }

    document.getElementById('modalTimeline').innerHTML = timelineHtml;

    // Notice & Action buttons
    var noticeBox = document.getElementById('modalNoticeBox');
    var actionBox = document.getElementById('modalActionButtons');
    noticeBox.classList.add('hidden');
    actionBox.innerHTML = '';

    if (r.delivery_method === 'digital' && ['ready', 'released', 'completed'].indexOf(st) !== -1 && isPaid) {
        noticeBox.className = "rounded-xl p-3 text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300";
        noticeBox.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Handa na para i-download ang inyong opisyal na sertipiko.';
        noticeBox.classList.remove('hidden');

        actionBox.innerHTML = `
            <a href="<?= e(route('documents/')) ?>${r.id}/preview" target="_blank"
               class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">
                <i class="bi bi-eye"></i> Preview Document
            </a>
            <a href="<?= e(route('documents/')) ?>${r.id}/download"
               class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-blue-700 transition">
                <i class="bi bi-download"></i> Download PDF
            </a>
        `;
    } else if (r.delivery_method === 'pickup' && ['ready', 'released'].indexOf(st) !== -1) {
        noticeBox.className = "rounded-xl p-3 text-xs font-semibold bg-blue-50 text-blue-800 border border-blue-200 dark:bg-blue-950/50 dark:text-blue-300";
        noticeBox.innerHTML = '<i class="bi bi-building-check me-1"></i> Ang inyong dokumento ay handa na para kunin sa Barangay Hall.';
        noticeBox.classList.remove('hidden');
    }

    if (!isPaid && fee > 0) {
        actionBox.innerHTML += `
            <a href="<?= e(route('documents/')) ?>${r.id}/payment"
               class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-2 text-xs font-bold text-white shadow-md hover:from-blue-700 hover:to-indigo-700">
                <i class="bi bi-wallet2"></i> Magbayad gamit ang GCash (₱${fee.toFixed(2)})
            </a>
        `;
    }

    document.getElementById('detailsModal').classList.remove('hidden');
}

function closeDetailsModal() {
    document.getElementById('detailsModal').classList.add('hidden');
}

// Initial trigger
onDocumentTypeChange();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
