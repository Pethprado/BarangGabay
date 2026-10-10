<?php
/**
 * Admin resident detail / verification page.
 * Variables: $resident (array)
 */
$resident = $resident ?? [];

$statusMeta = [
    'pending'   => ['bg' => '#fff3cd', 'border' => '#ffc107',  'text' => '#856404', 'label' => t('residents_detail.status_pending_label')],
    'verified'  => ['bg' => '#d4edda', 'border' => '#c3e6cb',  'text' => '#155724', 'label' => t('residents.status_verified')],
    'suspended' => ['bg' => '#f8d7da', 'border' => '#f5c6cb',  'text' => '#721c24', 'label' => t('residents.status_suspended')],
];
$resStatus = $resident['status'] ?? 'pending';
$sMeta     = $statusMeta[$resStatus] ?? $statusMeta['pending'];
$initial   = mb_strtoupper(mb_substr($resident['full_name'] ?? '?', 0, 1, 'UTF-8'), 'UTF-8');

ob_start();
?>

<style>
.id-photo-wrap { cursor:zoom-in; overflow:hidden; border-radius:10px; background:#f1f5f2; }
.id-photo-wrap.zoomed { cursor:zoom-out; }
.id-photo-wrap img { display:block; width:100%; transition:transform .35s ease; }
.id-photo-wrap.zoomed img { transform:scale(2.5); transform-origin:center center; }
.info-row { display:flex; gap:10px; padding:10px 0; border-bottom:1px solid #f0f4f1; font-size:.875rem; }
.info-row:last-child { border-bottom:none; padding-bottom:0; }
.info-label { color:var(--text-muted); font-weight:600; min-width:140px; flex-shrink:0; font-size:.8rem; text-transform:uppercase; letter-spacing:.04em; }
.info-value { color:var(--text-primary); word-break:break-word; }
</style>

<!-- ── Breadcrumb ────────────────────────────────────────────────── -->
<nav style="margin-bottom:20px;" aria-label="breadcrumb">
    <ol style="list-style:none;display:flex;align-items:center;gap:6px;padding:0;margin:0;font-size:.82rem;color:var(--text-muted);">
        <li><a href="<?= e(route('admin')) ?>" style="color:var(--text-muted);text-decoration:none;hover:color:var(--brand-primary);"><?= e(t('admin_nav.dashboard')) ?></a></li>
        <li style="color:#e4ece6;">/</li>
        <li><a href="<?= e(route('admin/residents')) ?>" style="color:var(--text-muted);text-decoration:none;"><?= e(t('admin_nav.residents')) ?></a></li>
        <li style="color:#e4ece6;">/</li>
        <li style="color:var(--text-primary);font-weight:600;"><?= e($resident['full_name'] ?? '') ?></li>
    </ol>
</nav>

<!-- ── Page title + status ───────────────────────────────────────── -->
<div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <div style="width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,#1652f0,#0d9488);color:#fff;font-size:1.25rem;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 12px rgba(22,82,240,.3);">
            <?= e($initial) ?>
        </div>
        <div>
            <h1 class="mb-0" style="font-size:1.4rem;font-weight:800;color:var(--text-primary);line-height:1.1;">
                <?= e($resident['full_name'] ?? '') ?>
            </h1>
            <p class="mb-0 mt-1 text-muted" style="font-size:.82rem;"><?= e($resident['email'] ?? '') ?></p>
        </div>
    </div>
    <span class="badge"
          style="background:<?= $sMeta['bg'] ?>;border:1px solid <?= $sMeta['border'] ?>;color:<?= $sMeta['text'] ?>;font-size:.82rem;padding:6px 14px;border-radius:20px;font-weight:700;">
        <?= $sMeta['label'] ?>
    </span>
</div>

<!-- ── Main grid ─────────────────────────────────────────────────── -->
<div class="row g-3">

    <!-- LEFT: registration info + actions -->
    <div class="col-lg-5 d-flex flex-column gap-3">

        <!-- Registration details card -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h2 class="admin-card-title mb-0">
                    <i class="bi bi-person-vcard me-2" style="color:var(--brand-primary);"></i><?= e(t('residents_detail.info_title')) ?>
                </h2>
            </div>
            <div class="admin-card-body">
                <div class="info-row">
                    <span class="info-label"><?= e(t('common.full_name')) ?></span>
                    <span class="info-value fw-semibold"><?= e($resident['full_name'] ?? '—') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><?= e(t('common.email_address')) ?></span>
                    <span class="info-value"><?= e($resident['email'] ?? '—') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><?= e(t('common.phone')) ?></span>
                    <span class="info-value"><?= e($resident['phone'] ?? '—') ?: '<span style="color:#cbd5e1;">' . e(t('residents_detail.not_provided')) . '</span>' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><?= e(t('common.address')) ?></span>
                    <span class="info-value"><?= e($resident['address'] ?? '—') ?: '<span style="color:#cbd5e1;">' . e(t('residents_detail.not_provided')) . '</span>' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><?= e(t('common.zone')) ?></span>
                    <span class="info-value"><?= e($resident['zone'] ?? '—') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><?= e(t('residents_detail.registered')) ?></span>
                    <span class="info-value"><?= !empty($resident['created_at']) ? e(date('F j, Y g:i A', strtotime($resident['created_at']))) : '—' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><?= e(t('residents_detail.email_verified')) ?></span>
                    <span class="info-value">
                        <?php if (!empty($resident['email_verified'])): ?>
                        <span style="color:#155724;font-weight:600;"><i class="bi bi-check-circle-fill me-1"></i><?= e(t('residents_detail.verified_label')) ?></span>
                        <?php else: ?>
                        <span style="color:#856404;"><i class="bi bi-exclamation-circle me-1"></i><?= e(t('residents_detail.not_yet_verified')) ?></span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label"><?= e(t('residents_detail.account_status')) ?></span>
                    <span class="info-value">
                        <span class="badge" style="background:<?= $sMeta['bg'] ?>;border:1px solid <?= $sMeta['border'] ?>;color:<?= $sMeta['text'] ?>;font-size:.75rem;border-radius:20px;padding:3px 10px;">
                            <?= $sMeta['label'] ?>
                        </span>
                    </span>
                </div>
                <?php if (!empty($resident['last_login_at'])): ?>
                <div class="info-row">
                    <span class="info-label"><?= e(t('residents_detail.last_login')) ?></span>
                    <span class="info-value text-muted"><?= e(date('M j, Y g:i A', strtotime($resident['last_login_at']))) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Actions card -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h2 class="admin-card-title mb-0">
                    <i class="bi bi-shield-check me-2" style="color:var(--brand-primary);"></i><?= e(t('residents_detail.admin_actions')) ?>
                </h2>
            </div>
            <div class="admin-card-body d-flex flex-column gap-3">

                <!-- Approve section -->
                <?php if (in_array($resStatus, ['pending', 'suspended'], true)): ?>
                <div style="background:#f0faf4;border:1px solid #c3e6cb;border-radius:10px;padding:16px;">
                    <p style="font-size:.845rem;font-weight:700;color:#155724;margin:0 0 6px;">
                        <i class="bi bi-check-circle-fill me-1"></i><?= e(t('residents_detail.approve_title')) ?>
                    </p>
                    <p style="font-size:.78rem;color:var(--text-secondary);margin:0 0 14px;line-height:1.5;">
                        <?= e(t('residents_detail.approve_desc')) ?>
                    </p>
                    <form method="post"
                          action="<?= e(route('admin/residents/' . (int) $resident['id'] . '/verify')) ?>"
                          onsubmit="return confirm(<?= e(json_encode(t('residents.confirm_verify', ['name' => $resident['full_name'] ?? '']))) ?>)">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <button type="submit" class="btn-barangay" style="width:100%;justify-content:center;padding:10px;">
                            <i class="bi bi-check-circle-fill me-1"></i>
                            <?= e(t('residents_detail.approve_btn')) ?>
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <div style="background:#f8fbf9;border:1px solid #e4ece6;border-radius:10px;padding:14px;text-align:center;">
                    <i class="bi bi-check-circle-fill" style="color:#28a745;font-size:1.4rem;"></i>
                    <p style="font-size:.845rem;color:#155724;font-weight:600;margin:6px 0 0;">
                        <?= e(t('residents_detail.already_verified')) ?>
                    </p>
                </div>
                <?php endif; ?>

                <!-- Suspend section -->
                <?php if (in_array($resStatus, ['pending', 'verified'], true)): ?>
                <div x-data="{ showSuspendForm: false }" style="border:1px solid #f5c6cb;border-radius:10px;overflow:hidden;">
                    <button type="button"
                            @click="showSuspendForm = !showSuspendForm"
                            style="width:100%;background:#fff8f8;border:none;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;cursor:pointer;font-size:.845rem;font-weight:700;color:#721c24;"
                            :style="showSuspendForm ? 'background:#f8d7da;' : 'background:#fff8f8;'">>
                        <span><i class="bi bi-x-circle-fill me-1"></i><?= e(t('residents_detail.suspend_account')) ?></span>
                        <i class="bi bi-chevron-down" :class="{ 'bi-chevron-up': showSuspendForm }"></i>
                    </button>
                    <div x-show="showSuspendForm" x-transition style="padding:16px;border-top:1px solid #f5c6cb;background:var(--surface-card);">
                        <form method="post"
                              action="<?= e(route('admin/residents/' . (int) $resident['id'] . '/suspend')) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <label style="font-size:.82rem;font-weight:600;color:var(--text-primary);display:block;margin-bottom:6px;">
                                <?= e(t('residents_detail.reason_label')) ?> <span style="color:var(--text-muted);font-weight:400;"><?= e(t('common.optional')) ?></span>
                            </label>
                            <textarea name="reason"
                                      rows="3"
                                      placeholder="<?= e(t('residents_detail.suspend_reason_ph2')) ?>"
                                      style="width:100%;border:1px solid #dee2e6;border-radius:8px;padding:9px 12px;font-size:.845rem;resize:vertical;outline:none;font-family:inherit;margin-bottom:12px;"
                                      onfocus="this.style.borderColor='#dc3545';"
                                      onblur="this.style.borderColor='#dee2e6';"></textarea>
                            <p style="font-size:.75rem;color:var(--text-muted);margin:0 0 12px;">
                                <?= e(t('residents_detail.suspend_notice2')) ?>
                            </p>
                            <button type="submit"
                                    onclick="return confirm(<?= e(json_encode(t('residents_detail.confirm_suspend_name', ['name' => $resident['full_name'] ?? '']))) ?>)"
                                    style="width:100%;padding:9px;background:#dc3545;border:1px solid #dc3545;border-radius:8px;color:#fff;font-size:.845rem;font-weight:700;cursor:pointer;">
                                <i class="bi bi-x-circle-fill me-1"></i><?= e(t('residents_detail.confirm_suspend')) ?>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Password reset.
                     Only admin and superadmin see this: the route rejects
                     staff, and showing a button that always 403s would be a
                     fake affordance. An admin is also not offered it against
                     a superadmin, which the controller refuses too — the
                     check here is for the UI, the one there is the guard. -->
                <?php
                $__myRole     = $_SESSION['role'] ?? '';
                $__targetRole = $resident['role'] ?? 'resident';
                $__canResetPw = in_array($__myRole, ['admin', 'superadmin'], true)
                    && (int) $resident['id'] !== (int) ($_SESSION['user_id'] ?? 0)
                    && ($__targetRole !== 'superadmin' || $__myRole === 'superadmin');
                $__freshPw    = flash('reset_password');
                ?>
                <?php if ($__canResetPw): ?>
                <div style="border:1px solid var(--tb-border);border-radius:10px;padding:16px;">
                    <p style="font-size:.845rem;font-weight:700;color:var(--text-primary);margin:0 0 6px;">
                        <i class="bi bi-key-fill me-1" style="color:#e8a020;"></i><?= e(t('residents_detail.reset_pw_title')) ?>
                    </p>

                    <?php if ($__freshPw !== null): ?>
                    <!-- Shown exactly once: flash() unsets on read, so a page
                         refresh will not reveal it again. -->
                    <div style="background:#f0faf4;border:1px solid #c3e6cb;border-radius:8px;padding:12px;margin-bottom:12px;">
                        <p style="font-size:.78rem;color:#1c2b1e;margin:0 0 6px;font-weight:600;">
                            <?= e(t('residents_detail.reset_pw_shown')) ?>
                        </p>
                        <code style="display:block;font-size:1rem;font-weight:700;letter-spacing:.04em;background:#fff;border:1px solid #c3e6cb;border-radius:6px;padding:9px 12px;word-break:break-all;color:#1a6b3a;">
                            <?= e($__freshPw) ?>
                        </code>
                        <p style="font-size:.74rem;color:var(--text-secondary);margin:8px 0 0;">
                            <?= e(t('residents_detail.reset_pw_advise')) ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <p style="font-size:.75rem;color:var(--text-secondary);margin:0 0 12px;line-height:1.6;">
                        <?= e(t('residents_detail.reset_pw_help')) ?>
                    </p>
                    <form method="post"
                          action="<?= e(route('admin/residents/' . (int) $resident['id'] . '/reset-password')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <button type="submit"
                                onclick="return confirm(<?= e(json_encode(t('residents_detail.reset_pw_confirm', ['name' => $resident['full_name'] ?? '']))) ?>)"
                                style="width:100%;padding:9px;background:var(--surface-muted);border:1px solid var(--tb-border);border-radius:8px;color:var(--text-primary);font-size:.845rem;font-weight:700;cursor:pointer;">
                            <i class="bi bi-key me-1"></i><?= e(t('residents_detail.reset_pw_btn')) ?>
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Back link -->
                <a href="<?= e(route('admin/residents')) ?>"
                   style="display:flex;align-items:center;justify-content:center;gap:6px;padding:9px;background:var(--surface-muted);border:1px solid var(--tb-border);border-radius:8px;color:var(--text-secondary);text-decoration:none;font-size:.845rem;font-weight:600;transition:all .15s;"
                   onmouseover="this.style.background='#e8effe';this.style.color='#1652f0';"
                   onmouseout="this.style.background='';this.style.color='';">
                    <i class="bi bi-arrow-left-short"></i><?= e(t('residents_detail.back_to_list')) ?>
                </a>

            </div>
        </div>

    </div><!-- /left column -->

    <!-- RIGHT: ID photo -->
    <div class="col-lg-7">
        <div class="admin-card h-100">
            <div class="admin-card-header">
                <div>
                    <h2 class="admin-card-title mb-0">
                        <i class="bi bi-card-image me-2" style="color:var(--brand-primary);"></i><?= e(t('residents_detail.uploaded_id_title')) ?>
                    </h2>
                    <p class="text-muted mb-0 mt-1" style="font-size:.78rem;">
                        <?= e(t('residents.zoom_hint')) ?> · <?= !empty($resident['id_photo_url']) ? e(t('residents_detail.has_uploaded_id')) : e(t('residents_detail.no_id')) ?>
                    </p>
                </div>
            </div>
            <div class="admin-card-body">

                <?php if (!empty($resident['id_photo_url'])): ?>

                <div x-data="{ zoomed: false }">

                    <!-- Photo viewer -->
                    <div class="id-photo-wrap"
                         :class="{ zoomed: zoomed }"
                         @click="zoomed = !zoomed"
                         style="max-height:480px;border:2px solid #e4ece6;">
                        <img src="<?= e(asset(ltrim($resident['id_photo_url'], '/'))) ?>"
                             alt="<?= e($resident['full_name'] ?? '') ?>"
                             onerror="this.parentElement.innerHTML='<div style=\'padding:48px;text-align:center;color:var(--text-muted);\'><i class=\'bi bi-image\' style=\'font-size:2rem;\'></i><p style=\'margin:8px 0 0;font-size:.845rem;\'><?= e(addslashes(t('residents_detail.image_load_error'))) ?></p></div>'">
                    </div>

                    <!-- ── AI Verification Result ───────────────────── -->
                    <?php
                    $aiStatus = $resident['id_ai_status'] ?? null;
                    $aiJson   = $resident['id_verified_by_ai'] ?? null;
                    $aiData   = $aiJson ? json_decode($aiJson, true) : [];

                    $aiBadges = [
                        'ai_passed'     => ['bg'=>'#d4edda','border'=>'#c3e6cb','text'=>'#155724','icon'=>'bi-robot',        'label'=>t('residents_detail.ai_valid_detected')],
                        'ai_flagged'    => ['bg'=>'#f8d7da','border'=>'#f5c6cb','text'=>'#721c24','icon'=>'bi-exclamation-triangle','label'=>t('residents_detail.ai_flagged2')],
                        'manual_review' => ['bg'=>'#fff3cd','border'=>'#ffc107','text'=>'#856404','icon'=>'bi-eye',           'label'=>t('residents_detail.manual_review_req')],
                        'pdf_manual'    => ['bg'=>'#d1ecf1','border'=>'#bee5eb','text'=>'#0c5460','icon'=>'bi-file-earmark-pdf','label'=>t('residents_detail.pdf_manual_review')],
                        'skipped'       => ['bg'=>'#f1f5f9','border'=>'#e2e8f0','text'=>'#475569','icon'=>'bi-skip-forward',  'label'=>t('residents_detail.ai_skipped2')],
                    ];

                    if ($aiStatus && isset($aiBadges[$aiStatus])):
                        $ab = $aiBadges[$aiStatus];
                    ?>
                    <div style="margin-top:14px;background:<?= $ab['bg'] ?>;border:1px solid <?= $ab['border'] ?>;border-radius:10px;padding:12px 16px;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                            <i class="bi <?= $ab['icon'] ?>" style="color:<?= $ab['text'] ?>;font-size:1rem;flex-shrink:0;"></i>
                            <span style="font-weight:700;font-size:.82rem;color:<?= $ab['text'] ?>;"><?= $ab['label'] ?></span>
                            <?php if (!empty($aiData['confidence']) && $aiData['confidence'] !== 'unknown'): ?>
                            <span style="margin-left:auto;font-size:.7rem;background:rgba(0,0,0,.07);color:<?= $ab['text'] ?>;padding:2px 8px;border-radius:10px;">
                                <?= e(t('residents_detail.confidence')) ?>: <?= ucfirst(e($aiData['confidence'])) ?>
                            </span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($aiData['id_type']) && $aiData['id_type'] !== 'unknown'): ?>
                        <p style="margin:0 0 4px;font-size:.78rem;color:<?= $ab['text'] ?>;">
                            <strong><?= e(t('residents_detail.id_type')) ?>:</strong> <?= e($aiData['id_type']) ?>
                        </p>
                        <?php endif; ?>

                        <?php if (!empty($aiData['reason'])): ?>
                        <p style="margin:0 0 4px;font-size:.78rem;color:<?= $ab['text'] ?>;">
                            <strong><?= e(t('residents_detail.ai_note')) ?>:</strong> <?= e($aiData['reason']) ?>
                        </p>
                        <?php endif; ?>

                        <?php if (!empty($aiData['issues'])): ?>
                        <p style="margin:0;font-size:.75rem;color:<?= $ab['text'] ?>;">
                            <strong><?= e(t('residents_detail.issues')) ?>:</strong> <?= e(implode(', ', $aiData['issues'])) ?>
                        </p>
                        <?php endif; ?>

                        <div style="display:flex;gap:12px;margin-top:8px;font-size:.72rem;color:<?= $ab['text'] ?>;opacity:.8;">
                            <?php if (isset($aiData['has_photo'])): ?>
                            <span><?= $aiData['has_photo'] ? '✓ ' . e(t('residents_detail.has_face')) : '✗ ' . e(t('residents_detail.no_face')) ?></span>
                            <?php endif; ?>
                            <?php if (isset($aiData['has_name'])): ?>
                            <span><?= $aiData['has_name'] ? '✓ ' . e(t('residents_detail.has_name')) : '✗ ' . e(t('residents_detail.no_name')) ?></span>
                            <?php endif; ?>
                            <?php if (isset($aiData['is_readable'])): ?>
                            <span><?= $aiData['is_readable'] ? '✓ ' . e(t('residents_detail.readable')) : '✗ ' . e(t('residents_detail.not_readable')) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php elseif (!$aiStatus): ?>
                    <div style="margin-top:14px;background:#f9fafb;border:1px dashed #e2e8f0;border-radius:10px;padding:10px 14px;">
                        <p style="margin:0;font-size:.775rem;color:var(--text-muted);">
                            <i class="bi bi-robot me-1"></i><?= e(t('residents_detail.no_ai_result')) ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <!-- Controls -->
                    <div class="d-flex align-items-center justify-content-between mt-3 flex-wrap gap-2">
                        <p class="mb-0 text-muted" style="font-size:.78rem;">
                            <span x-show="!zoomed"><i class="bi bi-zoom-in me-1"></i><?= e(t('residents_detail.zoom_in_hint')) ?></span>
                            <span x-show="zoomed"><i class="bi bi-zoom-out me-1"></i><?= e(t('residents_detail.zoom_out_hint')) ?></span>
                        </p>
                        <div class="d-flex gap-2">
                            <button type="button"
                                    @click="zoomed = !zoomed"
                                    style="padding:5px 12px;font-size:.78rem;background:#f8fbf9;border:1px solid #e4ece6;border-radius:6px;cursor:pointer;color:var(--text-secondary);display:inline-flex;align-items:center;gap:5px;">
                                <i class="bi" :class="zoomed ? 'bi-zoom-out' : 'bi-zoom-in'"></i>
                                <span x-text="zoomed ? <?= e(json_encode(t('residents_detail.zoom_out'))) ?> : <?= e(json_encode(t('residents_detail.zoom_in'))) ?>"></span>
                            </button>
                            <a href="<?= e(asset(ltrim($resident['id_photo_url'], '/'))) ?>"
                               target="_blank" rel="noopener"
                               style="padding:5px 12px;font-size:.78rem;background:#f8fbf9;border:1px solid #e4ece6;border-radius:6px;cursor:pointer;color:var(--text-secondary);text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
                                <i class="bi bi-box-arrow-up-right"></i> <?= e(t('common.open')) ?>
                            </a>
                            <a href="<?= e(asset(ltrim($resident['id_photo_url'], '/'))) ?>"
                               download
                               style="padding:5px 12px;font-size:.78rem;background:#f8fbf9;border:1px solid #e4ece6;border-radius:6px;cursor:pointer;color:var(--text-secondary);text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
                                <i class="bi bi-download"></i> <?= e(t('common.download')) ?>
                            </a>
                        </div>
                    </div>

                </div><!-- /x-data zoomed -->

                <?php else: ?>

                <div class="text-center py-5" style="border:2px dashed #e4ece6;border-radius:10px;">
                    <i class="bi bi-image-slash" style="font-size:3rem;color:#e4ece6;"></i>
                    <p class="mt-3 mb-1 fw-semibold text-muted" style="font-size:.875rem;">
                        <?= e(t('residents_detail.no_id_uploaded')) ?>
                    </p>
                    <p class="text-muted mb-0" style="font-size:.78rem;">
                        <?= e(t('residents_detail.no_id_desc')) ?>
                    </p>
                    <?php if ($resStatus === 'pending'): ?>
                    <div style="margin-top:16px;padding:10px 16px;background:#fff3cd;border:1px solid #ffc107;border-radius:8px;display:inline-block;">
                        <p style="color:#856404;font-size:.78rem;font-weight:600;margin:0;">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <?= e(t('residents_detail.request_id_notice')) ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>

                <?php endif; ?>

            </div>
        </div>
    </div><!-- /right column -->

</div><!-- /row -->

<?php
$content   = ob_get_clean();
$pageTitle = $pageTitle ?? t('residents_detail.info_title');
require __DIR__ . '/../../layouts/admin.php';
