<?php
/**
 * Resident profile page.
 * Variables: $user (array)
 */
$user      = $user ?? [];
$status    = $user['status'] ?? 'pending';
$role      = $user['role']   ?? 'resident';
$createdAt = $user['created_at'] ?? null;
$initial   = strtoupper(mb_substr($user['full_name'] ?? '?', 0, 1, 'UTF-8'));

$statusMeta = [
    'verified'  => ['cls' => 'bg-blue-100 text-blue-700 border-blue-200', 'label' => 'Verified',  'icon' => 'bi-patch-check-fill'],
    'pending'   => ['cls' => 'bg-amber-100 text-amber-700 border-amber-200',       'label' => 'Pending',   'icon' => 'bi-hourglass-split'],
    'suspended' => ['cls' => 'bg-red-100 text-red-700 border-red-200',             'label' => 'Suspended', 'icon' => 'bi-slash-circle'],
];
$sMeta = $statusMeta[$status] ?? $statusMeta['pending'];

ob_start();
?>

<!-- Page header -->
<div class="mb-6">
    <p class="text-xs font-bold uppercase tracking-widest text-blue-700"><?= e(t('res_profile.eyebrow')) ?></p>
    <h1 class="mt-0.5 text-2xl font-bold text-slate-900 sm:text-3xl"><?= e(t('res_profile.title')) ?></h1>
</div>

<div class="grid gap-6 lg:grid-cols-3">

    <!-- ── LEFT: Avatar card ─────────────────────────────────────────────── -->
    <div class="lg:col-span-1">

        <!-- Avatar + info card -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm">

            <!-- Avatar -->
            <?php if (!empty($user['avatar_url'])): ?>
            <img src="<?= e(asset($user['avatar_url'])) ?>"
                 alt="Avatar"
                 class="mx-auto h-24 w-24 rounded-full object-cover ring-4 ring-blue-100">
            <?php else: ?>
            <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-blue-600 text-3xl font-bold text-white ring-4 ring-blue-100">
                <?= e($initial) ?>
            </div>
            <?php endif; ?>

            <!-- Upload avatar form -->
            <form method="post" action="<?= e(route('profile')) ?>" enctype="multipart/form-data" class="mt-3">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="_action" value="upload_avatar">
                <label class="cursor-pointer text-xs font-semibold text-slate-500 hover:text-blue-700 transition-colors">
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
                <?php if (!empty($user['zone'])): ?>
                <div class="flex items-center gap-2 px-4 py-2.5">
                    <i class="bi bi-geo-alt text-slate-400 flex-shrink-0"></i>
                    <span class="text-slate-600"><?= e($user['zone']) ?></span>
                </div>
                <?php endif; ?>
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

    </div><!-- /left -->

    <!-- ── RIGHT: Edit forms ─────────────────────────────────────────────── -->
    <div class="lg:col-span-2 flex flex-col gap-6">

        <!-- Profile update form -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="mb-5 text-base font-bold text-slate-900 flex items-center gap-2">
                <i class="bi bi-person-lines-fill text-blue-600"></i> <?= e(t('res_profile.edit_profile')) ?>
            </h3>

            <form method="post" action="<?= e(route('profile')) ?>">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="_action" value="update_profile">

                <div class="grid gap-4 sm:grid-cols-2">

                    <div class="sm:col-span-2">
                        <label for="full_name" class="mb-1.5 block text-sm font-semibold text-slate-700">
                            <?= e(t('common.full_name')) ?> <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               id="full_name"
                               name="full_name"
                               value="<?= e($user['full_name'] ?? '') ?>"
                               required
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>

                    <div>
                        <label for="phone" class="mb-1.5 block text-sm font-semibold text-slate-700"><?= e(t('common.phone')) ?></label>
                        <input type="text"
                               id="phone"
                               name="phone"
                               value="<?= e($user['phone'] ?? '') ?>"
                               placeholder="e.g. 09XX-XXX-XXXX"
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-700">Email</label>
                        <input type="email"
                               value="<?= e($user['email'] ?? '') ?>"
                               disabled
                               class="w-full rounded-xl border border-slate-100 bg-slate-50 px-4 py-2.5 text-sm text-slate-400 cursor-not-allowed">
                        <p class="mt-1 text-xs text-slate-400"><?= e(t('res_profile.email_locked')) ?></p>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="address" class="mb-1.5 block text-sm font-semibold text-slate-700">Address</label>
                        <textarea id="address"
                                  name="address"
                                  rows="2"
                                  placeholder="<?= e(t('res_profile.address_placeholder')) ?>"
                                  class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"><?= e($user['address'] ?? '') ?></textarea>
                    </div>

                </div>

                <div class="mt-5">
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-300">
                        <i class="bi bi-check-lg"></i> <?= e(t('res_profile.save_changes')) ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Password change form -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="mb-5 text-base font-bold text-slate-900 flex items-center gap-2">
                <i class="bi bi-lock-fill text-amber-600"></i> <?= e(t('res_profile.change_password')) ?>
            </h3>

            <form method="post" action="<?= e(route('profile')) ?>" x-data="{ showPw: false }">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="_action" value="change_password">

                <div class="grid gap-4 sm:grid-cols-2">

                    <div class="sm:col-span-2">
                        <label for="current_password" class="mb-1.5 block text-sm font-semibold text-slate-700">
                            <?= e(t('res_profile.current_password')) ?> <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input :type="showPw ? 'text' : 'password'"
                                   id="current_password"
                                   name="current_password"
                                   required
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 pr-10 text-sm text-slate-700 shadow-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100">
                            <button type="button" @click="showPw = !showPw"
                                    class="absolute inset-y-0 right-3 flex items-center text-slate-400 hover:text-slate-600">
                                <i class="bi" :class="showPw ? 'bi-eye-slash' : 'bi-eye'"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label for="new_password" class="mb-1.5 block text-sm font-semibold text-slate-700">
                            <?= e(t('res_profile.new_password')) ?> <span class="text-red-500">*</span>
                        </label>
                        <input :type="showPw ? 'text' : 'password'"
                               id="new_password"
                               name="new_password"
                               required
                               minlength="8"
                               placeholder="<?= e(t('res_profile.min_chars')) ?>"
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100">
                    </div>

                    <div>
                        <label for="confirm_password" class="mb-1.5 block text-sm font-semibold text-slate-700">
                            <?= e(t('res_profile.confirm_password')) ?> <span class="text-red-500">*</span>
                        </label>
                        <input :type="showPw ? 'text' : 'password'"
                               id="confirm_password"
                               name="confirm_password"
                               required
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100">
                    </div>

                </div>

                <div class="mt-5">
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-300">
                        <i class="bi bi-shield-lock"></i> <?= e(t('res_profile.change_password')) ?>
                    </button>
                </div>
            </form>
        </div>

    </div><!-- /right -->

</div><!-- /grid -->

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';