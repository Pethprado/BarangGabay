<?php
/**
 * Resident profile page.
 * Variables: $user (array), $updateRequests (array)
 */
$user           = $user ?? [];
$updateRequests = $updateRequests ?? [];
$status         = $user['status'] ?? 'pending';
$role           = $user['role']   ?? 'resident';
$createdAt      = $user['created_at'] ?? null;
$initial        = strtoupper(mb_substr($user['full_name'] ?? '?', 0, 1, 'UTF-8'));
$isVerified     = ($status === 'verified');

$dob     = $user['date_of_birth'] ?? null;
$dynAge  = \App\Models\User::getAge($dob);
$address = \App\Models\User::formatAddress($user);

$statusMeta = [
    'verified'  => ['cls' => 'bg-emerald-100 text-emerald-800 border-emerald-200', 'label' => 'Verified Resident', 'icon' => 'bi-patch-check-fill'],
    'pending'   => ['cls' => 'bg-amber-100 text-amber-800 border-amber-200',       'label' => 'Pending Verification', 'icon' => 'bi-hourglass-split'],
    'suspended' => ['cls' => 'bg-rose-100 text-rose-800 border-rose-200',           'label' => 'Suspended',            'icon' => 'bi-slash-circle'],
];
$sMeta = $statusMeta[$status] ?? $statusMeta['pending'];

ob_start();
?>

<!-- Page header -->
<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <p class="text-xs font-bold uppercase tracking-widest text-emerald-800"><?= e(t('res_profile.eyebrow')) ?></p>
        <h1 class="mt-0.5 text-2xl font-bold text-slate-900 sm:text-3xl"><?= e(t('res_profile.title')) ?></h1>
    </div>
    <?php if ($isVerified): ?>
    <div>
        <button type="button"
                data-bs-toggle="modal"
                data-bs-target="#profileUpdateModal"
                class="inline-flex items-center gap-2 rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-900 transition focus:outline-none focus:ring-2 focus:ring-emerald-300">
            <i class="bi bi-pencil-square"></i> Request Profile Update
        </button>
    </div>
    <?php endif; ?>
</div>

<div class="grid gap-6 lg:grid-cols-3">

    <!-- ── LEFT: Avatar card ─────────────────────────────────────────────── -->
    <div class="lg:col-span-1 flex flex-col gap-6">

        <!-- Avatar + info card -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm">

            <!-- Avatar -->
            <?php if (!empty($user['avatar_url'])): ?>
            <img src="<?= e(asset($user['avatar_url'])) ?>"
                 alt="Avatar"
                 class="mx-auto h-24 w-24 rounded-full object-cover ring-4 ring-emerald-100">
            <?php else: ?>
            <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-emerald-800 text-3xl font-bold text-white ring-4 ring-emerald-100">
                <?= e($initial) ?>
            </div>
            <?php endif; ?>

            <!-- Upload avatar form -->
            <form method="post" action="<?= e(route('profile')) ?>" enctype="multipart/form-data" class="mt-3">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="_action" value="upload_avatar">
                <label class="cursor-pointer text-xs font-semibold text-slate-500 hover:text-emerald-800 transition-colors">
                    <i class="bi bi-camera me-1"></i> <?= e(t('res_profile.change_photo')) ?>
                    <input type="file" name="avatar" accept="image/jpeg,image/png" class="hidden"
                           onchange="this.form.submit()">
                </label>
            </form>

            <!-- Name -->
            <h2 class="mt-4 text-lg font-bold text-slate-900"><?= e($user['full_name'] ?? '') ?></h2>
            <p class="text-sm text-slate-500"><?= e($user['email'] ?? '') ?></p>

            <!-- Status badge -->
            <div class="mt-3 flex justify-center">
                <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-bold <?= $sMeta['cls'] ?>">
                    <i class="bi <?= $sMeta['icon'] ?>"></i>
                    <?= $sMeta['label'] ?>
                </span>
            </div>

            <!-- Meta info -->
            <div class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-100 bg-slate-50 text-left text-sm">
                <div class="flex items-center gap-2 px-4 py-2.5">
                    <i class="bi bi-geo-alt text-slate-400 flex-shrink-0"></i>
                    <span class="text-slate-600"><?= e($user['purok'] ? 'Purok ' . $user['purok'] : ($user['zone'] ?? 'Barangay Bayogo')) ?></span>
                </div>
                <?php if (!empty($user['phone'])): ?>
                <div class="flex items-center gap-2 px-4 py-2.5">
                    <i class="bi bi-telephone text-slate-400 flex-shrink-0"></i>
                    <span class="text-slate-600"><?= e($user['phone']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($createdAt): ?>
                <div class="flex items-center gap-2 px-4 py-2.5">
                    <i class="bi bi-calendar-check text-slate-400 flex-shrink-0"></i>
                    <span class="text-slate-500 text-xs"><?= e(t('res_profile.member_since', ['date' => date('F Y', strtotime($createdAt))])) ?></span>
                </div>
                <?php endif; ?>
                <div class="flex items-center gap-2 px-4 py-2.5">
                    <i class="bi bi-person-badge text-slate-400 flex-shrink-0"></i>
                    <span class="text-slate-500 text-xs capitalize"><?= e($role) ?></span>
                </div>
            </div>
        </div>

        <!-- Verified Protection Card -->
        <?php if ($isVerified): ?>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-5 shadow-sm">
            <div class="flex items-start gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-800 flex-shrink-0">
                    <i class="bi bi-shield-lock-fill text-lg"></i>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-emerald-950">Protektadong Impormasyon</h4>
                    <p class="mt-1 text-xs text-emerald-800 leading-relaxed">
                        Dahil beripikado na ang inyong resident account, naka-lock ang inyong personal na impormasyon para sa inyong seguridad. Kung may pagbabago tulad ng paglipat ng tirahan o pag-aasawa, i-click ang <strong>Request Profile Update</strong>.
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /left -->

    <!-- ── RIGHT: Profile details & forms ──────────────────────────────── -->
    <div class="lg:col-span-2 flex flex-col gap-6">

        <!-- Official Resident Record Card -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i class="bi bi-person-vcard text-emerald-700"></i> Opisyal na Talaan ng Residente
                </h3>
                <?php if ($isVerified): ?>
                <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 border border-emerald-200">
                    <i class="bi bi-lock-fill"></i> 🔒 Verified Information
                </span>
                <?php endif; ?>
            </div>

            <!-- Profile Details Grid -->
            <div class="grid gap-4 sm:grid-cols-2">

                <!-- Full Name -->
                <div class="sm:col-span-2 p-3 rounded-xl bg-slate-50 border border-slate-100">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-semibold text-slate-500 uppercase">Buong Pangalan (Full Name)</span>
                        <?php if ($isVerified): ?><span class="text-[11px] font-bold text-emerald-700">🔒 Naka-lock</span><?php endif; ?>
                    </div>
                    <p class="text-sm font-bold text-slate-900"><?= e($user['full_name'] ?? '—') ?></p>
                </div>

                <!-- Date of Birth -->
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-semibold text-slate-500 uppercase">Petsa ng Kapanganakan (DOB)</span>
                        <?php if ($isVerified): ?><span class="text-[11px] font-bold text-emerald-700">🔒 Naka-lock</span><?php endif; ?>
                    </div>
                    <p class="text-sm font-semibold text-slate-800">
                        <?= $dob ? date('F d, Y', strtotime((string) $dob)) : 'Hindi nakasaad' ?>
                    </p>
                </div>

                <!-- Dynamic Calculated Age (Requirement 9) -->
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-semibold text-slate-500 uppercase">Edad (Dynamic Calculated Age)</span>
                        <span class="text-[11px] font-medium text-slate-400">Awtomatikong kinalkula</span>
                    </div>
                    <p class="text-sm font-bold text-emerald-800">
                        <?= $dynAge !== null ? e($dynAge) . ' taong gulang' : '—' ?>
                    </p>
                </div>

                <!-- Sex -->
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-semibold text-slate-500 uppercase">Kasarian (Sex)</span>
                        <?php if ($isVerified): ?><span class="text-[11px] font-bold text-emerald-700">🔒 Naka-lock</span><?php endif; ?>
                    </div>
                    <p class="text-sm font-semibold text-slate-800"><?= e($user['sex'] ?? '—') ?></p>
                </div>

                <!-- Civil Status -->
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-semibold text-slate-500 uppercase">Civil Status</span>
                        <?php if ($isVerified): ?><span class="text-[11px] font-bold text-emerald-700">🔒 Naka-lock</span><?php endif; ?>
                    </div>
                    <p class="text-sm font-semibold text-slate-800"><?= e($user['civil_status'] ?? '—') ?></p>
                </div>

                <!-- Household Number -->
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-semibold text-slate-500 uppercase">Household Number</span>
                        <?php if ($isVerified): ?><span class="text-[11px] font-bold text-emerald-700">🔒 Naka-lock</span><?php endif; ?>
                    </div>
                    <p class="text-sm font-semibold text-slate-800"><?= e($user['household_no'] ?: '—') ?></p>
                </div>

                <!-- Household Head -->
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-semibold text-slate-500 uppercase">Punong Sambahayan</span>
                    </div>
                    <p class="text-sm font-semibold text-slate-800">
                        <?= !empty($user['is_household_head']) ? 'Oo (Head of Household)' : 'Hindi' ?>
                        <?= !empty($user['head_relationship']) ? ' (' . e($user['head_relationship']) . ')' : '' ?>
                    </p>
                </div>

                <!-- Complete Address -->
                <div class="sm:col-span-2 p-3 rounded-xl bg-slate-50 border border-slate-100">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-semibold text-slate-500 uppercase">Kumpletong Tirahan (Address)</span>
                        <?php if ($isVerified): ?><span class="text-[11px] font-bold text-emerald-700">🔒 Naka-lock</span><?php endif; ?>
                    </div>
                    <p class="text-sm font-semibold text-slate-800"><?= e($address) ?></p>
                </div>

            </div>

            <!-- Editable Contact Number Form -->
            <form method="post" action="<?= e(route('profile')) ?>" class="mt-5 pt-4 border-t border-slate-100">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="_action" value="update_profile">

                <div class="grid gap-4 sm:grid-cols-2 items-end">
                    <div>
                        <label for="phone" class="mb-1 block text-xs font-semibold text-slate-700">Mobile Phone Number (Maaaring i-update)</label>
                        <input type="text"
                               id="phone"
                               name="phone"
                               value="<?= e($user['phone'] ?? '') ?>"
                               placeholder="e.g. 09XX-XXX-XXXX"
                               class="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-sm text-slate-700 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                    </div>
                    <div>
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-800 px-5 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-900 transition">
                            <i class="bi bi-save"></i> I-save ang Contact Number
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Recent Profile Update Requests Timeline -->
        <?php if (!empty($updateRequests)): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-base font-bold text-slate-900 flex items-center gap-2">
                <i class="bi bi-clock-history text-indigo-600"></i> Kasaysayan ng Profile Update Requests
            </h3>
            <div class="divide-y divide-slate-100">
                <?php foreach ($updateRequests as $req): ?>
                <div class="py-3.5 first:pt-0 last:pb-0">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-xs font-bold text-slate-700">
                            Kahilingan noong <?= date('M d, Y g:i A', strtotime((string) $req['created_at'])) ?>
                        </span>
                        <?php
                        $reqStatus = (string) $req['status'];
                        $bCls = match ($reqStatus) {
                            'approved' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                            'rejected' => 'bg-rose-100 text-rose-800 border-rose-200',
                            default    => 'bg-amber-100 text-amber-800 border-amber-200',
                        };
                        ?>
                        <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-bold <?= $bCls ?>">
                            <?= ucfirst($reqStatus) ?>
                        </span>
                    </div>
                    <p class="text-xs text-slate-600 mb-1"><strong>Dahilan:</strong> <?= e($req['reason']) ?></p>
                    <?php if (!empty($req['rejection_reason'])): ?>
                    <p class="text-xs text-rose-700 bg-rose-50 border border-rose-100 rounded-lg p-2 mt-1">
                        <strong>Dahilan ng Pagtanggi:</strong> <?= e($req['rejection_reason']) ?>
                    </p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Password Change Card -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="mb-5 text-base font-bold text-slate-900 flex items-center gap-2">
                <i class="bi bi-shield-lock text-amber-600"></i> Baguhin ang Password
            </h3>

            <form method="post" action="<?= e(route('profile')) ?>" x-data="{ showPw: false }">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="_action" value="change_password">

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="current_password" class="mb-1.5 block text-xs font-semibold text-slate-700">
                            Kasalukuyang Password <span class="text-red-500">*</span>
                        </label>
                        <input :type="showPw ? 'text' : 'password'"
                               id="current_password"
                               name="current_password"
                               required
                               class="w-full rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-700 shadow-sm focus:border-emerald-500 focus:outline-none">
                    </div>

                    <div>
                        <label for="new_password" class="mb-1.5 block text-xs font-semibold text-slate-700">
                            Bagong Password <span class="text-red-500">*</span>
                        </label>
                        <input :type="showPw ? 'text' : 'password'"
                               id="new_password"
                               name="new_password"
                               required
                               placeholder="Min. 8 karakter"
                               class="w-full rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-700 shadow-sm focus:border-emerald-500 focus:outline-none">
                    </div>

                    <div>
                        <label for="confirm_password" class="mb-1.5 block text-xs font-semibold text-slate-700">
                            Ulitin ang Bagong Password <span class="text-red-500">*</span>
                        </label>
                        <input :type="showPw ? 'text' : 'password'"
                               id="confirm_password"
                               name="confirm_password"
                               required
                               class="w-full rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-700 shadow-sm focus:border-emerald-500 focus:outline-none">
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between">
                    <label class="inline-flex items-center gap-2 cursor-pointer text-xs text-slate-600">
                        <input type="checkbox" @change="showPw = !showPw" class="rounded border-slate-300 text-emerald-800">
                        Ipakita ang password
                    </label>
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-slate-800 px-5 py-2 text-sm font-bold text-white shadow-sm hover:bg-slate-900 transition">
                        <i class="bi bi-key-fill"></i> Baguhin ang Password
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<!-- Modal: Request Profile Update (Requirement 13) -->
<div class="modal fade" id="profileUpdateModal" tabindex="-1" aria-labelledby="profileUpdateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-2xl border-0 shadow-2xl">
            <div class="modal-header border-b border-slate-100 bg-emerald-50/50 p-4">
                <h5 class="modal-title font-bold text-emerald-950 flex items-center gap-2" id="profileUpdateModalLabel">
                    <i class="bi bi-pencil-square text-emerald-700"></i> Kahilingan sa Pagbabago ng Profile (Request Profile Update)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="/profile/request-update" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <div class="modal-body p-6 space-y-4">
                    <p class="text-xs text-slate-500">
                        Dahil protektado ang inyong impormasyon sa talaan ng Barangay, kailangang dumaan sa pagsusuri ng Administrator ang anumang pagbabago sa inyong pangalan, tirahan, civil status, o sambahayan.
                    </p>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <!-- Civil Status -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Civil Status</label>
                            <select name="civil_status" class="form-select text-sm rounded-xl">
                                <option value="<?= e($user['civil_status'] ?? '') ?>">Kasalukuyan: <?= e($user['civil_status'] ?? 'Hindi nakasaad') ?></option>
                                <option value="Single">Single (Walang Asawa)</option>
                                <option value="Married">Married (May Asawa)</option>
                                <option value="Widowed">Widowed (Biyudo/a)</option>
                                <option value="Separated">Separated (Hiwalay)</option>
                            </select>
                        </div>

                        <!-- Household Number -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Household Number</label>
                            <input type="text" name="household_no" value="<?= e($user['household_no'] ?? '') ?>" placeholder="e.g. HH-0042" class="form-control text-sm rounded-xl">
                        </div>

                        <!-- House No -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">House / Block / Lot</label>
                            <input type="text" name="house_no" value="<?= e($user['house_no'] ?? '') ?>" placeholder="e.g. Blk 4 Lot 12" class="form-control text-sm rounded-xl">
                        </div>

                        <!-- Street -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Street / Kalye</label>
                            <input type="text" name="street" value="<?= e($user['street'] ?? '') ?>" placeholder="e.g. Mabini St." class="form-control text-sm rounded-xl">
                        </div>

                        <!-- Purok -->
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Purok / Sitio</label>
                            <?php $subdivs = barangay_subdivisions(); ?>
                            <select name="purok" class="form-select text-sm rounded-xl">
                                <option value="<?= e($user['purok'] ?? ($user['zone'] ?? '')) ?>">Kasalukuyan: <?= e($user['purok'] ? 'Purok ' . $user['purok'] : ($user['zone'] ?? 'Pumili')) ?></option>
                                <?php foreach ($subdivs as $p): ?>
                                <option value="<?= e($p) ?>"><?= e($p) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Reason (Required) -->
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-800 mb-1">
                                Dahilan ng Pagbabago (Reason for Update) <span class="text-rose-500">*</span>
                            </label>
                            <textarea name="reason" rows="2" required placeholder="Hal. Lumipat ng bagong bahay sa Purok 2 noong nakaraang linggo / Nag-asawa noong..." class="form-control text-sm rounded-xl"></textarea>
                        </div>

                        <!-- Supporting Document Upload -->
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-800 mb-1">
                                Kalakip na Katibayan (Supporting Document) <span class="text-xs font-normal text-slate-400">(PDF, JPG, PNG - max 10MB)</span>
                            </label>
                            <input type="file" name="supporting_doc" accept="application/pdf,image/jpeg,image/png" class="form-control text-sm rounded-xl">
                            <p class="text-[11px] text-slate-500 mt-1">Maaaring mag-attach ng proof of billing, kasulatan ng kasal, o valid ID na nagpapatunay sa pagbabago.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-t border-slate-100 p-4 bg-slate-50">
                    <button type="button" class="btn btn-sm btn-secondary rounded-xl" data-bs-dismiss="modal">Kanselahin</button>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-emerald-800 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-900 transition">
                        <i class="bi bi-send-check"></i> Isumite ang Kahilingan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$title   = t('res_profile.title');
require __DIR__ . '/../layouts/main.php';