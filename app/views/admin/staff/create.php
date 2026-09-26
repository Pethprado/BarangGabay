<?php
/**
 * Admin: Create Staff / Admin Account
 * Variables: $errors (array), $oldInput (array)
 *
 * NOTE: Do NOT use .field-group / .field-label / .field-input here.
 * Those class names are claimed by admin.css as a floating-label system
 * (position:absolute) which causes label + placeholder overlap.
 * This view uses Bootstrap 5.3 native form classes instead.
 */
$errors   ??= [];
$oldInput ??= [];
$old = static fn(string $k, string $default = ''): string =>
    htmlspecialchars($oldInput[$k] ?? $default, ENT_QUOTES, 'UTF-8');

ob_start();
?>

<style>
/* ── Staff-create-only styles. NO .field-group/.field-label here. ── */
.sc-card {
    background: var(--surface-card);
    border: 1px solid var(--tb-border);
    border-radius: 14px;
    overflow: hidden;
    max-width: 660px;
}
.sc-card-head {
    background: linear-gradient(135deg, #0a1230, #1652f0);
    padding: 20px 24px;
    display: flex;
    align-items: center;
    gap: 14px;
}
.sc-card-head-icon {
    width: 44px; height: 44px;
    border-radius: 10px;
    background: rgba(255,255,255,.15);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; flex-shrink: 0;
}
.sc-card-body { padding: 28px 24px; }
.sc-back-btn {
    width: 34px; height: 34px; border-radius: 8px;
    border: 1px solid var(--tb-border); background: var(--surface-card); color: var(--text-secondary);
    text-decoration: none; font-size: .9rem; display: inline-flex;
    align-items: center; justify-content: center; transition: all .15s;
}
.sc-back-btn:hover { background: var(--brand-primary-light); border-color: var(--brand-primary); color: var(--brand-primary); }
.sc-eyebrow { font-size: .68rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--text-muted); margin: 0; }

/* Role radio cards */
.sc-role-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.sc-role-label { cursor: pointer; display: block; }
.sc-role-label input[type=radio] { display: none; }
.sc-role-box {
    border: 2px solid var(--tb-border);
    border-radius: 10px;
    padding: 16px;
    background: var(--surface-muted);
    transition: border-color .2s, background .2s;
}
.sc-role-label input:checked + .sc-role-box {
    border-color: var(--brand-primary);
    background: var(--brand-primary-light);
}
.sc-role-label:hover .sc-role-box { border-color: var(--brand-primary); }
.sc-role-box .sc-role-icon { font-size: 1.6rem; margin-bottom: 8px; }
.sc-role-box .sc-role-name { font-size: .9rem; font-weight: 700; color: var(--text-primary); }
.sc-role-label input:checked + .sc-role-box .sc-role-name { color: var(--brand-primary); }
.sc-role-box .sc-role-desc { font-size: .75rem; color: var(--text-secondary); margin-top: 3px; line-height: 1.4; }

/* Strength bar */
.sc-pw-bar { height: 4px; border-radius: 2px; background: var(--tb-border); margin-top: 6px; }
.sc-pw-fill { height: 100%; border-radius: 2px; transition: width .3s, background .3s; width: 0; }

/* Submit button */
.sc-btn-submit {
    background: linear-gradient(135deg, #1652f0, #1041c4);
    color: #fff; border: none; border-radius: 8px;
    padding: 11px 28px; font-size: .9rem; font-weight: 700;
    cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
    transition: transform .2s, box-shadow .2s;
}
.sc-btn-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(22,82,240,.35); }
.sc-cancel-link { font-size: .875rem; color: var(--text-secondary); text-decoration: none; font-weight: 500; transition: color .15s; }
.sc-cancel-link:hover { color: var(--status-danger); }
.sc-info-banner {
    background: var(--status-success-bg); border: 1px solid #c3e6cb; border-left: 4px solid var(--status-success);
    border-radius: 8px; padding: 12px 16px; display: flex; gap: 10px; align-items: flex-start; margin-bottom: 1.5rem;
}
.sc-info-banner p { margin: 0; font-size: .82rem; color: var(--text-primary); line-height: 1.5; }
.sc-info-banner i { color: var(--status-success); flex-shrink: 0; margin-top: 2px; font-size: .95rem; }
</style>

<!-- ── Page header ─────────────────────────────────────────────── -->
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?= e(route('admin/residents')) ?>" class="sc-back-btn">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <p class="sc-eyebrow"><?= e(t('staff_create.eyebrow')) ?></p>
        <h1 style="font-size:1.45rem;font-weight:800;color:var(--text-primary);line-height:1.1;margin:2px 0 0;"><?= e(t('staff_create.title')) ?></h1>
    </div>
</div>

<!-- ── Form card ───────────────────────────────────────────────── -->
<div class="sc-card" x-data="scForm()">

    <!-- Card header -->
    <div class="sc-card-head">
        <div class="sc-card-head-icon">👤</div>
        <div>
            <p style="color:#fff;font-weight:700;margin:0;font-size:.95rem;"><?= e(t('staff_create.header_title')) ?></p>
            <p style="color:rgba(255,255,255,.65);margin:0;font-size:.78rem;">
                <?= e(t('staff_create.header_desc')) ?>
            </p>
        </div>
    </div>

    <!-- Form body -->
    <form method="post" action="<?= e(route('admin/staff')) ?>" novalidate class="sc-card-body">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <!-- ── ROLE CARDS ──────────────────────────────────────── -->
        <div class="mb-4">
            <p class="sc-eyebrow" style="margin-bottom:10px;"><?= e(t('staff_create.choose_role')) ?></p>
            <div class="sc-role-grid">
                <label class="sc-role-label">
                    <input type="radio" name="role" value="staff"
                           <?= ($old('role', 'staff') === 'staff') ? 'checked' : '' ?>>
                    <div class="sc-role-box">
                        <div class="sc-role-icon">🛡️</div>
                        <div class="sc-role-name"><?= e(t('staff_create.role_staff')) ?></div>
                        <div class="sc-role-desc"><?= e(t('staff_create.role_staff_desc')) ?></div>
                    </div>
                </label>
                <label class="sc-role-label">
                    <input type="radio" name="role" value="admin"
                           <?= ($old('role', 'staff') === 'admin') ? 'checked' : '' ?>>
                    <div class="sc-role-box">
                        <div class="sc-role-icon">⚙️</div>
                        <div class="sc-role-name"><?= e(t('staff_create.role_admin')) ?></div>
                        <div class="sc-role-desc"><?= e(t('staff_create.role_admin_desc')) ?></div>
                    </div>
                </label>
            </div>
            <?php if (!empty($errors['role'])): ?>
            <div class="text-danger mt-1" style="font-size:.78rem;"><?= e($errors['role']) ?></div>
            <?php endif; ?>
        </div>

        <hr style="border-color:var(--tb-border);margin-bottom:1.5rem;">

        <!-- ── FULL NAME ──────────────────────────────────────── -->
        <!-- Bootstrap .form-label is NOT position:absolute — safe to use -->
        <div class="mb-3">
            <label for="sc_name" class="form-label fw-semibold" style="font-size:.845rem;color:var(--text-primary);">
                <?= e(t('common.full_name')) ?> <span class="text-danger">*</span>
            </label>
            <input type="text"
                   id="sc_name"
                   name="full_name"
                   value="<?= $old('full_name', '') ?>"
                   placeholder="<?= e(t('staff_create.name_ph')) ?>"
                   class="form-control <?= !empty($errors['full_name']) ? 'is-invalid' : '' ?>"
                   style="border-radius:8px;font-size:.9rem;"
                   required>
            <?php if (!empty($errors['full_name'])): ?>
            <div class="invalid-feedback"><?= e($errors['full_name']) ?></div>
            <?php endif; ?>
        </div>

        <!-- ── EMAIL ─────────────────────────────────────────── -->
        <div class="mb-3">
            <label for="sc_email" class="form-label fw-semibold" style="font-size:.845rem;color:var(--text-primary);">
                <?= e(t('common.email_address')) ?> <span class="text-danger">*</span>
            </label>
            <input type="email"
                   id="sc_email"
                   name="email"
                   value="<?= $old('email', '') ?>"
                   placeholder="<?= e(t('staff_create.email_ph')) ?>"
                   class="form-control <?= !empty($errors['email']) ? 'is-invalid' : '' ?>"
                   style="border-radius:8px;font-size:.9rem;"
                   required>
            <?php if (!empty($errors['email'])): ?>
            <div class="invalid-feedback"><?= e($errors['email']) ?></div>
            <?php endif; ?>
        </div>

        <!-- ── PASSWORD + CONFIRM ─────────────────────────────── -->
        <div class="row g-3 mb-3">
            <div class="col-sm-6">
                <label for="sc_pw" class="form-label fw-semibold" style="font-size:.845rem;color:var(--text-primary);">
                    <?= e(t('staff_create.password_label')) ?> <span class="text-danger">*</span>
                </label>
                <div style="position:relative;">
                    <input id="sc_pw"
                           name="password"
                           type="password"
                           placeholder="<?= e(t('staff_create.password_ph')) ?>"
                           class="form-control <?= !empty($errors['password']) ? 'is-invalid' : '' ?>"
                           style="border-radius:8px;font-size:.9rem;padding-right:2.5rem;"
                           @input="checkStrength($event.target.value)"
                           required>
                    <button type="button"
                            @click="togglePw('sc_pw')"
                            style="position:absolute;top:50%;right:10px;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;padding:0;font-size:.85rem;line-height:1;z-index:5;">
                        <i :class="showPw ? 'bi-eye-slash' : 'bi-eye'" class="bi"></i>
                    </button>
                </div>
                <!-- Strength bar -->
                <div class="sc-pw-bar" x-show="pwScore > 0">
                    <div class="sc-pw-fill" :style="{ width: (pwScore * 25) + '%', background: pwColor }"></div>
                </div>
                <small x-show="pwScore > 0" :style="{ color: pwColor }" x-text="pwLabel" style="font-size:.72rem;"></small>
                <?php if (!empty($errors['password'])): ?>
                <div class="text-danger mt-1" style="font-size:.78rem;"><?= e($errors['password']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-sm-6">
                <label for="sc_pw2" class="form-label fw-semibold" style="font-size:.845rem;color:var(--text-primary);">
                    <?= e(t('staff_create.confirm_password')) ?> <span class="text-danger">*</span>
                </label>
                <div style="position:relative;">
                    <input id="sc_pw2"
                           name="password_confirmation"
                           type="password"
                           placeholder="<?= e(t('staff_create.confirm_pw_ph')) ?>"
                           class="form-control <?= !empty($errors['password_confirmation']) ? 'is-invalid' : '' ?>"
                           style="border-radius:8px;font-size:.9rem;padding-right:2.5rem;"
                           required>
                    <button type="button"
                            @click="togglePw('sc_pw2')"
                            style="position:absolute;top:50%;right:10px;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;padding:0;font-size:.85rem;line-height:1;z-index:5;">
                        <i :class="showPw ? 'bi-eye-slash' : 'bi-eye'" class="bi"></i>
                    </button>
                </div>
                <?php if (!empty($errors['password_confirmation'])): ?>
                <div class="text-danger mt-1" style="font-size:.78rem;"><?= e($errors['password_confirmation']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <hr style="border-color:var(--tb-border);margin-bottom:1.25rem;">

        <!-- ── OPTIONAL ───────────────────────────────────────── -->
        <p class="sc-eyebrow" style="margin-bottom:12px;">
            <?= e(t('staff_create.additional_info')) ?> <span style="font-weight:400;"><?= e(t('common.optional')) ?></span>
        </p>
        <div class="row g-3 mb-4">
            <div class="col-sm-6">
                <label for="sc_phone" class="form-label fw-semibold" style="font-size:.845rem;color:var(--text-primary);"><?= e(t('staff_create.phone_number')) ?></label>
                <input type="tel"
                       id="sc_phone"
                       name="phone"
                       value="<?= $old('phone', '') ?>"
                       placeholder="<?= e(t('staff_create.phone_ph')) ?>"
                       class="form-control"
                       style="border-radius:8px;font-size:.9rem;">
            </div>
            <div class="col-sm-6">
                <label for="sc_address" class="form-label fw-semibold" style="font-size:.845rem;color:var(--text-primary);"><?= e(t('common.address')) ?></label>
                <input type="text"
                       id="sc_address"
                       name="address"
                       value="<?= $old('address', '') ?>"
                       placeholder="<?= e(t('staff_create.address_ph')) ?>"
                       class="form-control"
                       style="border-radius:8px;font-size:.9rem;">
            </div>
        </div>

        <!-- ── INFO BANNER ────────────────────────────────────── -->
        <div class="sc-info-banner">
            <i class="bi bi-info-circle-fill"></i>
            <p>
                <?= e(t('staff_create.info_banner')) ?>
            </p>
        </div>

        <!-- ── VALIDATION ERRORS ──────────────────────────────── -->
        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger d-flex align-items-start gap-2 py-2 mb-3" style="border-radius:8px;font-size:.845rem;">
            <i class="bi bi-exclamation-circle-fill mt-1 flex-shrink-0"></i>
            <div><?= e(t('staff_create.validation_error')) ?></div>
        </div>
        <?php endif; ?>

        <!-- ── SUBMIT ─────────────────────────────────────────── -->
        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="sc-btn-submit">
                <i class="bi bi-person-plus-fill"></i>
                <?= e(t('staff_create.submit_btn')) ?>
            </button>
            <a href="<?= e(route('admin/residents')) ?>" class="sc-cancel-link">
                <?= e(t('common.cancel')) ?>
            </a>
        </div>

    </form>
</div><!-- /sc-card -->

<script>
function scForm() {
    return {
        showPw:  false,
        pwScore: 0,
        pwColor: '#94a3b8',
        pwLabel: '',

        togglePw() {
            this.showPw = !this.showPw;
            ['sc_pw', 'sc_pw2'].forEach(id => {
                const f = document.getElementById(id);
                if (f) f.type = this.showPw ? 'text' : 'password';
            });
        },

        checkStrength(pw) {
            let s = 0;
            if (pw.length >= 8)          s++;
            if (/[A-Z]/.test(pw))        s++;
            if (/[0-9]/.test(pw))        s++;
            if (/[^A-Za-z0-9]/.test(pw)) s++;
            this.pwScore = s;
            const lvls = [
                { c: '#ef4444', l: <?= json_encode(t('staff_create.pw_weak')) ?> },
                { c: '#f97316', l: <?= json_encode(t('staff_create.pw_fair')) ?> },
                { c: '#eab308', l: <?= json_encode(t('staff_create.pw_strong')) ?> },
                { c: '#22c55e', l: <?= json_encode(t('staff_create.pw_very_strong')) ?> },
            ];
            const lvl = lvls[s - 1] || { c: '#94a3b8', l: '' };
            this.pwColor = lvl.c;
            this.pwLabel = lvl.l;
        },
    };
}
</script>

<?php
$content   = ob_get_clean();
$pageTitle ??= t('staff_create.title');
require __DIR__ . '/../../layouts/admin.php';
?>