<?php
// Field-level errors and old input are injected by AuthController::showRegister()
// via view('auth/register', ['errors' => ..., 'oldInput' => ...])
$errors   = $errors   ?? [];
$flashErr = flash('error');
$flashOk  = flash('success');
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mag-register — BarangGabay</title>
    <base href="<?= e(base_url()) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            /* System-wide maroon/gold earth palette with proper contrast tokens */
            --green:  #7b1e22;   /* maroon brand primary */
            --green-hover: #5e161a;
            --darker: #241b18;
            --gold:   #7a5c11;   /* gold-ink: safe for white text/icons on it */
            --radius: .75rem;
            --text-primary: #1a1512;
            --text-secondary: #4a3f38;
            --text-muted: #6b5d52;
            --border: #d1d5db;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background:
                radial-gradient(ellipse at 20% 30%, rgba(200,153,46,.16) 0%, transparent 55%),
                linear-gradient(145deg, #241b18 0%, #7b1e22 60%, #3a2a22 100%);
        }

        /* ── Outer wrapper ──────────────────────────────────── */
        .reg-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        .reg-card {
            display: flex;
            width: 100%;
            max-width: 1020px;
            min-height: 600px;
            background: #fff;
            border-radius: 1.5rem;
            overflow: hidden;
            box-shadow: 0 28px 80px rgba(0,0,0,.45), 0 4px 20px rgba(0,0,0,.25);
        }

        /* ── Left panel ─────────────────────────────────────── */
        .reg-left {
            width: 360px;
            flex-shrink: 0;
            background: linear-gradient(180deg, #241b18 0%, #5e161a 100%);
            padding: 2.5rem 2rem;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        .reg-left .baranggabay-logo-img {
            max-height: 52px;
            width: auto;
            max-width: 100%;
            object-fit: contain;
        }

        /* decorative circle */
        .reg-left::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            border: 40px solid rgba(255,255,255,.04);
            bottom: -80px;
            right: -80px;
            pointer-events: none;
        }

        .reg-brand-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: var(--gold);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: #fff;
            box-shadow: 0 4px 12px rgba(122,92,17,.4);
            margin-bottom: 1.25rem;
        }

        .reg-left h2 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #fff;
            margin: 0 0 .5rem;
            line-height: 1.25;
        }

        .reg-left .sub {
            font-size: .8rem;
            color: rgba(159,201,175,.7);
            letter-spacing: .07em;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }

        .reg-left p {
            font-size: .875rem;
            color: rgba(255,255,255,.7);
            line-height: 1.65;
            margin-bottom: 2rem;
        }

        /* Step list */
        .step-list { list-style: none; padding: 0; margin: 0; }

        .step-list li {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            padding: .6rem 0;
            border-bottom: 1px solid rgba(255,255,255,.07);
            font-size: .845rem;
            color: rgba(255,255,255,.75);
        }
        .step-list li:last-child { border-bottom: none; }

        .step-num {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--green);
            border: 2px solid rgba(255,255,255,.18);
            color: #fff;
            font-size: .75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: .1rem;
        }

        .step-content strong { display: block; color: #fff; font-size: .875rem; }
        .step-content span   { font-size: .78rem; color: rgba(255,255,255,.55); }

        .reg-left-footer {
            margin-top: auto;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255,255,255,.08);
            font-size: .75rem;
            color: rgba(159,201,175,.5);
        }

        /* ── Right panel ────────────────────────────────────── */
        .reg-right {
            flex: 1;
            background: #fff;
            padding: 2.5rem 2.25rem;
            overflow-y: auto;
        }

        /* ── Form controls ──────────────────────────────────── */
        .form-label {
            font-weight: 600;
            font-size: .82rem;
            color: #374151;
            margin-bottom: .3rem;
        }
        .form-label .req { color: #dc3545; }

        .form-control, .form-select {
            background: #f6faf7;
            border-color: #d1d5db;
            border-radius: var(--radius) !important;
            font-size: .875rem;
            padding: .6rem .9rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--green);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(123,30,34,.14);
        }
        .form-control.is-invalid {
            border-color: #dc3545;
            background: #fff8f8;
        }
        .form-control.is-invalid:focus {
            box-shadow: 0 0 0 3px rgba(220,53,69,.12);
        }
        .invalid-feedback { font-size: .8rem; }

        /* input-group rounded */
        .auth-ig { border-radius: var(--radius); overflow: hidden; }
        .auth-ig .input-group-text {
            background: #f0f7f2;
            border-color: #d1d5db;
            border-right: none;
            color: #6b7280;
        }
        .auth-ig .form-control { border-left: none; border-radius: 0 var(--radius) var(--radius) 0 !important; }
        .auth-ig .form-control.has-btn { border-right: none; border-radius: 0 !important; }
        .auth-ig .toggle-pw {
            background: #f6faf7;
            border: 1px solid #d1d5db;
            border-left: none;
            border-radius: 0 var(--radius) var(--radius) 0;
            padding: 0 .8rem;
            color: #6b7280;
            cursor: pointer;
            font-size: .85rem;
            transition: color .15s;
        }
        .auth-ig .toggle-pw:hover { color: var(--green); }

        /* Password strength bar */
        .strength-bar-track {
            height: 4px;
            border-radius: 4px;
            background: #e5e7eb;
            overflow: hidden;
            margin-top: 6px;
        }
        .strength-bar-fill {
            height: 100%;
            border-radius: 4px;
            transition: width .3s ease, background-color .3s ease;
        }

        /* File upload zone */
        .file-zone {
            border: 2px dashed #d1d5db;
            border-radius: var(--radius);
            background: #f6faf7;
            padding: 1.25rem 1rem;
            text-align: center;
            cursor: pointer;
            transition: border-color .2s, background .2s;
        }
        .file-zone:hover, .file-zone.drag-over {
            border-color: var(--green);
            background: #f0faf4;
        }
        .file-zone.has-file { border-style: solid; border-color: var(--green); }
        .file-zone input[type="file"] { display: none; }

        /* ID preview */
        .id-preview-img {
            max-height: 100px;
            max-width: 100%;
            border-radius: .5rem;
            object-fit: contain;
            margin-top: .5rem;
        }

        /* Submit button */
        .btn-register {
            background: var(--green);
            color: #fff;
            border: none;
            border-radius: var(--radius);
            padding: .78rem 1.25rem;
            font-weight: 700;
            font-size: .95rem;
            transition: background .2s, box-shadow .2s, transform .1s;
            width: 100%;
        }
        .btn-register:hover {
            background: #5e161a;
            color: #fff;
            box-shadow: 0 6px 18px rgba(123,30,34,.4);
            transform: translateY(-1px);
        }
        .btn-register:active { transform: none; }

        .form-check-input:checked { background-color: var(--green); border-color: var(--green); }
        .alert { border-radius: var(--radius); font-size: .875rem; }

        /* ── Responsive: collapse left panel on small screens ── */
        @media (max-width: 767.98px) {
            .reg-card { flex-direction: column; border-radius: 1.25rem; max-width: 520px; }
            .reg-left { width: 100%; padding: 1.75rem 1.5rem; }
            .reg-left p { display: none; }             /* hide long copy on mobile */
            .step-list { display: none; }              /* hide stepper on mobile */
            .reg-left-footer { display: none; }
            .reg-right { padding: 1.75rem 1.5rem; }
        }
    </style>
</head>
<body>

<div class="reg-wrapper">
<div class="reg-card">

    <!-- ── Left: welcome panel ────────────────────────────── -->
    <div class="reg-left">
        <div class="mb-3">
            <?= baranggabay_logo('dark', ['size' => 'large', 'href' => route('')]) ?>
        </div>

        <h2>Sumali sa aming komunidad</h2>
        <p>
            Ang BarangGabay ang opisyal na portal ng Barangay Bayogo, Madrid para sa mga anunsyo, kaganapan, at lokal na ordinansa. I-register ang iyong account para manatiling updated.
        </p>

        <!-- 3-step registration process -->
        <ul class="step-list">
            <li>
                <div class="step-num">1</div>
                <div class="step-content">
                    <strong>Mag-register</strong>
                    <span>Punan ang form at i-upload ang valid ID.</span>
                </div>
            </li>
            <li>
                <div class="step-num">2</div>
                <div class="step-content">
                    <strong>I-verify ang Email</strong>
                    <span>I-click ang link na padadalhin sa iyong email.</span>
                </div>
            </li>
            <li>
                <div class="step-num">3</div>
                <div class="step-content">
                    <strong>Admin Approval</strong>
                    <span>Susuriin ng barangay staff ang iyong ID at iko-confirm ang access.</span>
                </div>
            </li>
        </ul>

        <div class="reg-left-footer">
            <i class="bi bi-shield-check me-1"></i>
            Ang iyong impormasyon ay ligtas at ginagamit lamang para sa pagbe-beryipika ng iyong pagkakakilanlan.
        </div>
    </div><!-- /reg-left -->

    <!-- ── Right: registration form ──────────────────────── -->
    <div class="reg-right" x-data="registerForm()">

        <h2 class="h5 fw-bold text-dark mb-1">Gumawa ng Account</h2>
        <p class="text-secondary mb-3" style="font-size:.85rem;">
            May account na?
            <a href="<?= e(route('login')) ?>" style="color:var(--green);font-weight:600;text-decoration:none;">Mag-login dito</a>
        </p>

        <!-- Flash alerts -->
        <?php if ($flashOk): ?>
        <div class="alert alert-success d-flex gap-2 py-2 mb-3" role="alert">
            <i class="bi bi-check-circle-fill mt-1 flex-shrink-0"></i>
            <span><?= e($flashOk) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($flashErr): ?>
        <div class="alert alert-danger d-flex gap-2 py-2 mb-3" role="alert">
            <i class="bi bi-exclamation-circle-fill mt-1 flex-shrink-0"></i>
            <span><?= e($flashErr) ?></span>
        </div>
        <?php endif; ?>

        <form method="post" action="<?= e(route('register')) ?>"
              enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="row g-3">

                <!-- Section 1: Personal Information -->
                <div class="col-12 mt-1">
                    <h6 class="fw-bold text-dark border-bottom pb-1 mb-2 d-flex align-items-center gap-1" style="font-size:.9rem; color:var(--green)!important;">
                        <i class="bi bi-person-badge"></i> 1. Impormasyon ng Residente (Personal Info)
                    </h6>
                </div>

                <div class="col-sm-6">
                    <label for="first_name" class="form-label">Pangalan (First Name) <span class="req">*</span></label>
                    <input type="text" id="first_name" name="first_name"
                           class="form-control <?= !empty($errors['first_name']) ? 'is-invalid' : '' ?>"
                           placeholder="Hal. Juan"
                           value="<?= e(old('first_name')) ?>" required autocomplete="given-name">
                    <?php if (!empty($errors['first_name'])): ?>
                    <div class="invalid-feedback"><?= e($errors['first_name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-sm-6">
                    <label for="middle_name" class="form-label">Gitnang Pangalan (Middle Name)</label>
                    <input type="text" id="middle_name" name="middle_name"
                           class="form-control"
                           placeholder="Hal. Protacio (opsyonal)"
                           value="<?= e(old('middle_name')) ?>" autocomplete="additional-name">
                </div>

                <div class="col-sm-8">
                    <label for="last_name" class="form-label">Apelyido (Last Name) <span class="req">*</span></label>
                    <input type="text" id="last_name" name="last_name"
                           class="form-control <?= !empty($errors['last_name']) ? 'is-invalid' : '' ?>"
                           placeholder="Hal. Dela Cruz"
                           value="<?= e(old('last_name')) ?>" required autocomplete="family-name">
                    <?php if (!empty($errors['last_name'])): ?>
                    <div class="invalid-feedback"><?= e($errors['last_name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-sm-4">
                    <label for="suffix" class="form-label">Suffix</label>
                    <input type="text" id="suffix" name="suffix"
                           class="form-control"
                           placeholder="Jr., Sr., III"
                           value="<?= e(old('suffix')) ?>">
                </div>

                <!-- Date of Birth & Dynamic Age Display -->
                <div class="col-sm-4">
                    <label for="date_of_birth" class="form-label">Petsa ng Kapanganakan (DOB) <span class="req">*</span></label>
                    <input type="date" id="date_of_birth" name="date_of_birth"
                           class="form-control <?= !empty($errors['date_of_birth']) ? 'is-invalid' : '' ?>"
                           value="<?= e(old('date_of_birth')) ?>" required>
                    <?php if (!empty($errors['date_of_birth'])): ?>
                    <div class="invalid-feedback"><?= e($errors['date_of_birth']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-sm-4">
                    <label for="sex" class="form-label">Kasarian (Sex) <span class="req">*</span></label>
                    <select id="sex" name="sex" class="form-select <?= !empty($errors['sex']) ? 'is-invalid' : '' ?>" required>
                        <option value="">— Pumili —</option>
                        <option value="Male" <?= old('sex') === 'Male' ? 'selected' : '' ?>>Lalaki (Male)</option>
                        <option value="Female" <?= old('sex') === 'Female' ? 'selected' : '' ?>>Babae (Female)</option>
                        <option value="Other" <?= old('sex') === 'Other' ? 'selected' : '' ?>>Iba pa (Other)</option>
                    </select>
                </div>

                <div class="col-sm-4">
                    <label for="civil_status" class="form-label">Civil Status <span class="req">*</span></label>
                    <select id="civil_status" name="civil_status" class="form-select <?= !empty($errors['civil_status']) ? 'is-invalid' : '' ?>" required>
                        <option value="">— Pumili —</option>
                        <option value="Single" <?= old('civil_status') === 'Single' ? 'selected' : '' ?>>Walang Asawa (Single)</option>
                        <option value="Married" <?= old('civil_status') === 'Married' ? 'selected' : '' ?>>May Asawa (Married)</option>
                        <option value="Widowed" <?= old('civil_status') === 'Widowed' ? 'selected' : '' ?>>Biyudo / Biyuda (Widowed)</option>
                        <option value="Separated" <?= old('civil_status') === 'Separated' ? 'selected' : '' ?>>Hiwalay (Separated)</option>
                    </select>
                </div>

                <!-- Section 2: Address & Household Information -->
                <div class="col-12 mt-3">
                    <h6 class="fw-bold text-dark border-bottom pb-1 mb-2 d-flex align-items-center gap-1" style="font-size:.9rem; color:var(--green)!important;">
                        <i class="bi bi-house-door"></i> 2. Tirahan at Sambahayan (Address & Household)
                    </h6>
                </div>

                <div class="col-sm-6">
                    <label for="house_no" class="form-label">House / Block / Lot No.</label>
                    <input type="text" id="house_no" name="house_no"
                           class="form-control"
                           placeholder="Hal. Blk 2 Lot 14"
                           value="<?= e(old('house_no')) ?>">
                </div>

                <div class="col-sm-6">
                    <label for="street" class="form-label">Street / Kalye</label>
                    <input type="text" id="street" name="street"
                           class="form-control"
                           placeholder="Hal. Rizal Street"
                           value="<?= e(old('street')) ?>">
                </div>

                <?php
                $puroks  = barangay_subdivisions();
                $selZone = old('zone', '');
                ?>
                <div class="col-sm-6">
                    <label for="zone" class="form-label">Purok / Sitio <span class="req">*</span></label>
                    <?php if ($puroks !== []): ?>
                    <select id="zone" name="zone" class="form-select" required>
                        <option value="">— Pumili ng Purok —</option>
                        <?php foreach ($puroks as $z): ?>
                        <option value="<?= e($z) ?>" <?= $selZone === $z ? 'selected' : '' ?>>
                            <?= e($z) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <input type="text" id="zone" name="zone" class="form-control"
                           maxlength="50"
                           value="<?= e($selZone) ?>"
                           placeholder="Hal. Purok 1" required>
                    <?php endif; ?>
                </div>

                <div class="col-sm-6">
                    <label for="household_no" class="form-label">Household Number</label>
                    <input type="text" id="household_no" name="household_no"
                           class="form-control"
                           placeholder="Hal. HH-0042 (opsyonal)"
                           value="<?= e(old('household_no')) ?>">
                </div>

                <div class="col-sm-6">
                    <label class="form-label d-block">Ikaw ba ang Punong Sambahayan (Head of Household)?</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="is_household_head" id="head_yes" value="1" <?= old('is_household_head') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="head_yes">Oo (Yes)</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="is_household_head" id="head_no" value="0" <?= old('is_household_head') !== '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="head_no">Hindi (No)</label>
                    </div>
                </div>

                <div class="col-sm-6">
                    <label for="head_relationship" class="form-label">Relasyon sa Head (kung Hindi)</label>
                    <input type="text" id="head_relationship" name="head_relationship"
                           class="form-control"
                           placeholder="Hal. Asawa, Anak, Kapatid"
                           value="<?= e(old('head_relationship')) ?>">
                </div>

                <!-- Section 3: Contact & Account Security -->
                <div class="col-12 mt-3">
                    <h6 class="fw-bold text-dark border-bottom pb-1 mb-2 d-flex align-items-center gap-1" style="font-size:.9rem; color:var(--green)!important;">
                        <i class="bi bi-shield-lock"></i> 3. Contact at Seguridad (Account Details)
                    </h6>
                </div>

                <!-- Email -->
                <div class="col-sm-6">
                    <label for="reg_email" class="form-label">
                        Email Address <span class="req">*</span>
                    </label>
                    <input type="email" id="reg_email" name="email"
                           class="form-control <?= !empty($errors['email']) ? 'is-invalid' : '' ?>"
                           placeholder="you@example.com"
                           value="<?= e(old('email')) ?>"
                           required autocomplete="email">
                    <?php if (!empty($errors['email'])): ?>
                    <div class="invalid-feedback"><?= e($errors['email']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Phone -->
                <div class="col-sm-6">
                    <label for="phone" class="form-label">
                        Numero ng Telepono (Mobile) <span class="req">*</span>
                    </label>
                    <div class="input-group auth-ig">
                        <span class="input-group-text"><i class="bi bi-phone" style="font-size:.9rem;"></i></span>
                        <input type="tel" id="phone" name="phone"
                               class="form-control <?= !empty($errors['phone']) ? 'is-invalid' : '' ?>"
                               placeholder="09XX XXX XXXX"
                               value="<?= e(old('phone')) ?>"
                               required autocomplete="tel">
                    </div>
                    <?php if (!empty($errors['phone'])): ?>
                    <div class="text-danger mt-1" style="font-size:.8rem;"><?= e($errors['phone']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Password -->
                <div class="col-sm-6">
                    <label for="reg_password" class="form-label">
                        Password <span class="req">*</span>
                    </label>
                    <div class="input-group auth-ig">
                        <input :type="showPw ? 'text' : 'password'"
                               id="reg_password" name="password"
                               class="form-control has-btn <?= !empty($errors['password']) ? 'is-invalid' : '' ?>"
                               placeholder="Min. 8 karakter"
                               @input="checkStrength($event.target.value)"
                               required autocomplete="new-password">
                        <button type="button" class="toggle-pw"
                                @click="showPw = !showPw">
                            <i :class="showPw ? 'bi-eye-slash' : 'bi-eye'" class="bi"></i>
                        </button>
                    </div>
                    <!-- Strength indicator -->
                    <div class="strength-bar-track">
                        <div class="strength-bar-fill"
                             :style="{ width: strengthPct + '%', backgroundColor: strengthColor }"></div>
                    </div>
                    <div x-show="strengthLabel" class="mt-1"
                         style="font-size:.75rem;" :style="{ color: strengthColor }">
                        <span x-text="strengthLabel"></span>
                    </div>
                    <?php if (!empty($errors['password'])): ?>
                    <div class="text-danger mt-1" style="font-size:.8rem;"><?= e($errors['password']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Confirm Password -->
                <div class="col-sm-6">
                    <label for="reg_password_confirm" class="form-label">
                        Ulitin ang Password <span class="req">*</span>
                    </label>
                    <input :type="showPw ? 'text' : 'password'"
                           id="reg_password_confirm" name="password_confirmation"
                           class="form-control <?= !empty($errors['password_confirmation']) ? 'is-invalid' : '' ?>"
                           placeholder="Ulitin ang password"
                           required autocomplete="new-password">
                    <?php if (!empty($errors['password_confirmation'])): ?>
                    <div class="invalid-feedback"><?= e($errors['password_confirmation']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Valid ID upload -->
                <div class="col-12">
                    <label class="form-label">
                        Valid ID <span class="req">*</span>
                        <span class="text-muted fw-normal ms-1" style="font-size:.75rem;">(JPG, PNG, o PDF — max 10MB)</span>
                    </label>

                    <!-- AI Verification notice -->
                    <div style="background:#f0faf4;border:1px solid #b7dfc8;border-radius:.75rem;padding:10px 14px;margin-bottom:.75rem;display:flex;gap:10px;align-items:flex-start;">
                        <span style="font-size:1rem;flex-shrink:0;margin-top:1px;">🤖</span>
                        <div>
                            <?php /* --tw-green-600/700 live in main.css, which this
                                     standalone page does not load, so both lines
                                     rendered with no colour at all. */ ?>
                            <p style="margin:0 0 3px;font-size:.78rem;font-weight:700;color:#15803d;">AI-Powered ID Verification</p>
                            <p style="margin:0;font-size:.73rem;color:#166534;line-height:1.5;">
                                Ang inyong ID ay awtomatikong sususuriin ng AI. Tiyaking:
                                <strong>malinaw ang larawan</strong>, <strong>nakikita ang mukha at pangalan</strong>,
                                at <strong>tunay na Philippine government ID</strong> ang inyong ia-upload.
                            </p>
                        </div>
                    </div>

                    <!-- Accepted ID types -->
                    <details style="margin-bottom:.75rem;">
                        <summary style="font-size:.75rem;font-weight:600;color:#374151;cursor:pointer;user-select:none;padding:6px 10px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:.5rem;list-style:none;display:flex;align-items:center;gap:6px;">
                            <i class="bi bi-card-list" style="color:var(--green);"></i>
                            Mga katanggap-tanggap na Valid ID <span style="margin-left:auto;font-size:.7rem;color:#9ca3af;">(i-click para makita)</span>
                        </summary>
                        <div style="padding:10px 12px;background:#f9fafb;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 .5rem .5rem;">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:2px 12px;">
                                <?php
                                $acceptedIds = [
                                    'PhilSys / National ID', 'Passport',
                                    'SSS / GSIS ID',         'PRC ID',
                                    'PhilHealth ID',         'Senior Citizen ID',
                                    "Voter's / COMELEC ID",  'PWD ID',
                                    "Driver's License (LTO)",'Postal ID',
                                    'Pag-IBIG / HDMF ID',   'Barangay ID',
                                    'NBI / Police Clearance','TIN ID (BIR)',
                                    'Student ID (with seal)','Company ID (official)',
                                ];
                                foreach ($acceptedIds as $idName): ?>
                                <p style="margin:0;font-size:.72rem;color:var(--text-secondary);">✓ <?= e($idName) ?></p>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </details>

                    <div class="file-zone" :class="{ 'has-file': fileName }"
                         @click="$refs.idInput.click()"
                         @dragover.prevent="dragOver = true"
                         @dragleave="dragOver = false"
                         @drop.prevent="handleDrop($event)"
                         :class="{ 'drag-over': dragOver, 'has-file': fileName }">

                        <input type="file" name="id_photo" x-ref="idInput"
                               accept=".jpg,.jpeg,.png,.pdf"
                               @change="handleFileChange($event)"
                               required>

                        <!-- Empty state -->
                        <div x-show="!fileName">
                            <i class="bi bi-cloud-arrow-up text-secondary" style="font-size:1.75rem;"></i>
                            <p class="mb-0 mt-2" style="font-size:.875rem; font-weight:600; color:var(--text-secondary);">
                                I-drag dito o <span style="color:var(--green);">pumili ng file</span>
                            </p>
                            <p class="mb-0 text-muted" style="font-size:.75rem;">JPEG, PNG, PDF &bull; max 10MB</p>
                        </div>

                        <!-- File selected state -->
                        <div x-show="fileName" @click.stop>
                            <!-- Image preview -->
                            <template x-if="previewUrl">
                                <img :src="previewUrl" class="id-preview-img" alt="ID preview">
                            </template>
                            <!-- PDF / no-preview -->
                            <template x-if="!previewUrl && fileName">
                                <i class="bi bi-file-earmark-pdf text-danger" style="font-size:2rem;"></i>
                            </template>
                            <p class="mb-0 mt-2" style="font-size:.8rem; font-weight:600; color:var(--green);"
                               x-text="fileName"></p>
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary mt-2"
                                    style="font-size:.75rem; border-radius:.5rem;"
                                    @click.stop="clearFile()">
                                <i class="bi bi-x me-1"></i>Palitan
                            </button>
                        </div>
                    </div>

                    <?php if (!empty($errors['id_photo'])): ?>
                    <div class="text-danger mt-1" style="font-size:.8rem;">
                        <i class="bi bi-exclamation-circle me-1"></i><?= e($errors['id_photo']) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Terms -->
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="terms"
                               name="terms" value="1" required>
                        <label class="form-check-label" for="terms"
                               style="font-size:.82rem; color:var(--text-secondary);">
                            Sumasang-ayon ako na ang aking impormasyon ay susuriin ng barangay staff
                            para sa pagbe-beryipika ng aking pagkakakilanlan. Ang datos ay gagamitin
                            lamang para sa mga serbisyo ng Barangay Bayogo, Madrid.
                        </label>
                    </div>
                </div>

                <!-- Submit -->
                <div class="col-12 mt-1">
                    <button type="submit" class="btn-register">
                        <i class="bi bi-person-check me-2"></i>Mag-register
                    </button>
                </div>

            </div><!-- /row -->
        </form>

    </div><!-- /reg-right -->

</div><!-- /reg-card -->
</div><!-- /reg-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
    function registerForm() {
        return {
            showPw:       false,
            fileName:     null,
            previewUrl:   null,
            dragOver:     false,
            strengthPct:  0,
            strengthColor:'#d1d5db',
            strengthLabel:'',

            /** Password strength checker */
            checkStrength(pw) {
                if (!pw) { this.strengthPct = 0; this.strengthLabel = ''; return; }
                let score = 0;
                if (pw.length >= 8)              score++;
                if (pw.length >= 12)             score++;
                if (/[A-Z]/.test(pw))            score++;
                if (/[0-9]/.test(pw))            score++;
                if (/[^A-Za-z0-9]/.test(pw))     score++;

                const levels = [
                    { pct: 20,  color: '#dc3545', label: 'Napakahina' },
                    { pct: 40,  color: '#fd7e14', label: 'Mahina'     },
                    { pct: 60,  color: '#ffc107', label: 'Katamtaman' },
                    { pct: 80,  color: '#20c997', label: 'Malakas'    },
                    { pct: 100, color: '#198754', label: 'Napakalakas'},
                ];
                const idx = Math.min(score, levels.length) - 1;
                const lvl = levels[Math.max(idx, 0)];
                this.strengthPct   = lvl.pct;
                this.strengthColor = lvl.color;
                this.strengthLabel = lvl.label;
            },

            /** Handle file input change */
            handleFileChange(event) {
                const file = event.target.files[0];
                if (file) this.setFile(file);
            },

            /** Handle drag-and-drop */
            handleDrop(event) {
                this.dragOver = false;
                const file = event.dataTransfer.files[0];
                if (file) {
                    // Manually assign to the real file input
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    this.$refs.idInput.files = dt.files;
                    this.setFile(file);
                }
            },

            setFile(file) {
                this.fileName   = file.name;
                this.previewUrl = null;
                if (file.type.startsWith('image/')) {
                    const reader  = new FileReader();
                    reader.onload = e => { this.previewUrl = e.target.result; };
                    reader.readAsDataURL(file);
                }
            },

            clearFile() {
                this.fileName   = null;
                this.previewUrl = null;
                this.$refs.idInput.value = '';
            }
        };
    }
</script>
</body>
</html>