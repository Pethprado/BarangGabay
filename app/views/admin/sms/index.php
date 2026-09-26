<?php
$logs           = $logs           ?? [];
$stats          = $stats          ?? ['total' => 0, 'sent' => 0, 'failed' => 0, 'monthly' => 0];
$recipientCount = $recipientCount ?? 0;
$total          = $total          ?? 0;
$page           = $page           ?? 1;
$perPage        = $perPage        ?? 20;
$filterType     = $filterType     ?? '';
$filterStatus   = $filterStatus   ?? '';
$smsConfigured  = $smsConfigured  ?? false;
$testMode       = $testMode       ?? false;
$totalPages     = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

$typeMeta = [
    'announcement'  => [t('admin_sms.type_announcement'),  'background:#dbeafe;color:#1d4ed8;'],
    'event'         => [t('admin_sms.type_event'),          'background:#dcfce7;color:#166534;'],
    'verification'  => [t('admin_sms.type_verification'),   'background:#fef9c3;color:#92400e;'],
    'broadcast'     => [t('admin_sms.type_broadcast'),      'background:#f3e8ff;color:#5b21b6;'],
    'manual'        => [t('admin_sms.type_manual'),         'background:#f1f5f9;color:#475569;'],
    'emergency'     => [t('admin_sms.type_emergency'),      'background:#fee2e2;color:#991b1b;'],
    'general'       => [t('admin_sms.type_general'),        'background:#f1f5f9;color:#475569;'],
];
$statusMeta = [
    'sent'    => [t('admin_sms.status_sent'),    'background:#dcfce7;color:#166534;'],
    'failed'  => [t('admin_sms.status_failed'),  'background:#fee2e2;color:#991b1b;'],
    'pending' => [t('admin_sms.status_pending'), 'background:#fef9c3;color:#92400e;'],
];

ob_start();
?>

<!-- ── Page header ─────────────────────────────────────────────────────── -->
<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <p class="mb-0" style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8;"><?= e(t('admin_nav.management')) ?></p>
        <h1 class="mb-0 mt-1" style="font-size:1.55rem;font-weight:800;color:var(--text-primary);line-height:1.1;"><?= e(t('admin_nav.sms')) ?></h1>
        <p class="text-muted mt-1 mb-0" style="font-size:.82rem;">
            <?= e(t('admin_sms.subtitle', ['count' => $recipientCount])) ?>
        </p>
    </div>
</div>

<!-- ── Test-mode / account notice ────────────────────────────────────── -->
<?php if ($testMode): ?>
<div style="background:#fefce8;border:1px solid #fde047;border-radius:10px;padding:14px 18px;margin-bottom:1.25rem;display:flex;gap:12px;align-items:flex-start;">
    <i class="bi bi-flask-fill" style="color:#d97706;font-size:1.1rem;flex-shrink:0;margin-top:2px;"></i>
    <div>
        <p style="margin:0 0 4px;font-weight:700;color:#92400e;font-size:.9rem;">
            <?= e(t('admin_sms.test_mode_title')) ?>
        </p>
        <p style="margin:0;color:#78350f;font-size:.82rem;line-height:1.6;">
            <?= t('admin_sms.test_mode_body') ?>
        </p>
    </div>
</div>
<?php elseif (!$smsConfigured): ?>
<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:10px;padding:14px 18px;margin-bottom:1.25rem;display:flex;gap:12px;align-items:flex-start;">
    <i class="bi bi-exclamation-triangle-fill" style="color:#dc2626;font-size:1.1rem;flex-shrink:0;margin-top:2px;"></i>
    <div>
        <p style="margin:0 0 4px;font-weight:700;color:#991b1b;font-size:.9rem;">
            <?= e(t('admin_sms.api_key_missing_title')) ?>
        </p>
        <p style="margin:0;color:#7f1d1d;font-size:.82rem;">
            <?= e(t('admin_sms.api_key_missing_body')) ?>
        </p>
    </div>
</div>
<?php else: ?>
<div style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:10px;padding:12px 18px;margin-bottom:1.25rem;display:flex;gap:10px;align-items:center;">
    <i class="bi bi-check-circle-fill" style="color:#059669;font-size:1rem;"></i>
    <p style="margin:0;color:#065f46;font-size:.85rem;font-weight:600;">
        <?= e(t('admin_sms.live_mode_notice')) ?>
    </p>
</div>
<?php endif; ?>

<!-- ── Stat cards ─────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <p class="stat-card-label mb-0"><?= e(t('admin_sms.stat_total_sent')) ?></p>
                <div class="stat-card-icon" style="background:#dbeafe;color:#1d4ed8;"><i class="bi bi-envelope-fill"></i></div>
            </div>
            <p class="stat-card-value"><?= number_format($stats['sent']) ?></p>
            <p class="stat-card-sub mb-0"><?= number_format($stats['total']) ?> <?= e(t('admin_sms.stat_total_logged_suffix')) ?></p>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <p class="stat-card-label mb-0"><?= e(t('admin_sms.stat_failed')) ?></p>
                <div class="stat-card-icon" style="background:#fee2e2;color:#991b1b;"><i class="bi bi-x-circle-fill"></i></div>
            </div>
            <p class="stat-card-value"><?= number_format($stats['failed']) ?></p>
            <p class="stat-card-sub mb-0"><?= e(t('admin_sms.stat_delivery_failures')) ?></p>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <p class="stat-card-label mb-0"><?= e(t('admin_sms.stat_this_month')) ?></p>
                <div class="stat-card-icon" style="background:#dcfce7;color:#166534;"><i class="bi bi-calendar-check-fill"></i></div>
            </div>
            <p class="stat-card-value"><?= number_format($stats['monthly']) ?></p>
            <p class="stat-card-sub mb-0"><?= e(t('admin_sms.stat_sms_this_month')) ?></p>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <p class="stat-card-label mb-0"><?= e(t('admin_sms.stat_recipients')) ?></p>
                <div class="stat-card-icon" style="background:#f3e8ff;color:#5b21b6;"><i class="bi bi-people-fill"></i></div>
            </div>
            <p class="stat-card-value"><?= number_format($recipientCount) ?></p>
            <p class="stat-card-sub mb-0"><?= e(t('admin_sms.stat_verified_w_phone')) ?></p>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">

    <!-- ── Manual SMS form ─────────────────────────────────────────────── -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-body p-4">
                <h5 class="fw-700 mb-1" style="color:var(--text-primary);font-size:1rem;">
                    <i class="bi bi-send me-2" style="color:var(--brand-primary);"></i><?= e(t('admin_sms.manual_sms_title')) ?>
                </h5>
                <p class="text-muted mb-3" style="font-size:.8rem;"><?= e(t('admin_sms.manual_sms_desc')) ?></p>

                <form method="post" action="<?= e(route('admin/sms/send')) ?>" x-data="charCounter('manual_msg', 160)">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                    <!-- ── Recipient picker ──────────────────────────────────
                         Was a blank box you had to type a number into from
                         memory: slow, and the easiest possible way to text the
                         wrong person. Now you search by name, or open it empty
                         to browse who is registered. Typing a raw number still
                         works — the same input accepts both. -->
                    <div class="mb-3" x-data="smsRecipients()" @click.outside="open = false">
                        <label for="sms_phone" class="form-label fw-600" style="font-size:.85rem;">
                            <?= e(t('admin_sms.field_phone')) ?>
                        </label>

                        <div class="position-relative">
                            <input type="text"
                                   id="sms_phone"
                                   name="phone"
                                   class="form-control"
                                   autocomplete="off"
                                   placeholder="<?= e(t('admin_sms.recipient_ph')) ?>"
                                   x-model="term"
                                   @focus="open = true; search()"
                                   <?php /* Reopen on typing: after picking someone the list closes,
                                            and editing the field again has to bring it back or the
                                            picker silently becomes a plain text box. */ ?>
                                   @input.debounce.250ms="open = true; search()"
                                   required
                                   value="<?= e($_POST['phone'] ?? '') ?>"
                                   <?= $smsConfigured ? '' : 'disabled' ?>>

                            <div x-show="open" x-cloak
                                 class="position-absolute w-100 shadow"
                                 style="z-index:20;top:calc(100% + 4px);max-height:16rem;overflow-y:auto;background:var(--surface-card);border:1px solid var(--tb-border);border-radius:10px;">

                                <template x-if="loading">
                                    <p class="text-muted m-0 px-3 py-2" style="font-size:.8rem;">
                                        <?= e(t('admin_sms.recipient_loading')) ?>
                                    </p>
                                </template>

                                <template x-if="!loading && results.length === 0">
                                    <p class="text-muted m-0 px-3 py-2" style="font-size:.8rem;">
                                        <?= e(t('admin_sms.recipient_none')) ?>
                                    </p>
                                </template>

                                <template x-for="r in results" :key="r.id">
                                    <button type="button"
                                            @click="choose(r)"
                                            class="sms-recipient-row d-flex w-100 align-items-center gap-2 border-0 text-start px-3 py-2">
                                        <span class="d-inline-flex align-items-center justify-content-center flex-shrink-0"
                                              style="width:1.9rem;height:1.9rem;border-radius:999px;background:var(--brand-primary);color:#fff;font-size:.75rem;font-weight:700;"
                                              x-text="r.name.charAt(0).toUpperCase()"></span>
                                        <span class="flex-grow-1" style="min-width:0;">
                                            <span class="d-block text-truncate" style="font-size:.85rem;font-weight:600;color:var(--text-primary);" x-text="r.name"></span>
                                            <span class="d-block" style="font-size:.76rem;color:var(--text-secondary);" x-text="r.phone + (r.zone ? ' · ' + r.zone : '')"></span>
                                        </span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <!-- Who this is actually going to, once picked. -->
                        <p x-show="picked" x-cloak class="form-text mb-0 mt-1" style="color:var(--brand-primary);font-weight:600;">
                            <i class="bi bi-person-check me-1"></i><span x-text="picked"></span>
                        </p>

                        <div class="form-text">
                            <?= e(t('admin_sms.recipient_help', ['n' => (int) $recipientCount])) ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-600" style="font-size:.85rem;"><?= e(t('admin_sms.field_message')) ?></label>
                        <textarea name="message" id="manual_msg" class="form-control" rows="4"
                                  maxlength="160" placeholder="<?= e(t('admin_sms.message_ph')) ?>"
                                  @input="update()"
                                  required <?= $smsConfigured ? '' : 'disabled' ?>><?= e($_POST['message'] ?? '') ?></textarea>
                        <div class="d-flex justify-content-between mt-1">
                            <div class="form-text"><?= e(t('admin_sms.max_chars_help')) ?></div>
                            <span class="form-text" :class="remaining < 20 ? 'text-danger fw-bold' : ''">
                                <span x-text="remaining"></span> <?= e(t('admin_sms.remaining_suffix')) ?>
                            </span>
                        </div>
                    </div>

                    <button type="submit" class="btn-barangay w-100" <?= $smsConfigured ? '' : 'disabled' ?>>
                        <i class="bi bi-send-fill me-2"></i><?= e(t('admin_sms.send_sms_btn')) ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Broadcast form ─────────────────────────────────────────────── -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-body p-4">
                <h5 class="fw-700 mb-1" style="color:var(--text-primary);font-size:1rem;">
                    <i class="bi bi-broadcast me-2" style="color:var(--brand-primary);"></i><?= e(t('admin_sms.broadcast_title')) ?>
                </h5>
                <p class="text-muted mb-3" style="font-size:.8rem;">
                    <?= t('admin_sms.broadcast_desc', ['count' => '<strong>' . (int) $recipientCount . '</strong>']) ?>
                </p>

                <form method="post" action="<?= e(route('admin/sms/broadcast')) ?>"
                      x-data="charCounter('broadcast_msg', 160)"
                      @submit.prevent="
                          if (!confirm(<?= e(json_encode(t('admin_sms.broadcast_confirm', ['count' => $recipientCount]))) ?>)) return;
                          $el.submit();
                      ">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                    <div class="mb-3">
                        <label class="form-label fw-600" style="font-size:.85rem;"><?= e(t('admin_sms.broadcast_field_label')) ?></label>
                        <textarea name="message" id="broadcast_msg" class="form-control" rows="5"
                                  maxlength="160" placeholder="<?= e(t('admin_sms.broadcast_message_ph')) ?>"
                                  @input="update()"
                                  required <?= ($smsConfigured && $recipientCount > 0) ? '' : 'disabled' ?>></textarea>
                        <div class="d-flex justify-content-between mt-1">
                            <div class="form-text"><?= t('admin_sms.broadcast_credit_help', ['count' => $recipientCount]) ?></div>
                            <span class="form-text" :class="remaining < 20 ? 'text-danger fw-bold' : ''">
                                <span x-text="remaining"></span> <?= e(t('admin_sms.remaining_suffix')) ?>
                            </span>
                        </div>
                    </div>

                    <button type="submit" class="btn w-100 fw-600"
                            style="background:#dc2626;color:#fff;border-radius:8px;padding:.625rem 1.25rem;"
                            <?= ($smsConfigured && $recipientCount > 0) ? '' : 'disabled' ?>>
                        <i class="bi bi-broadcast-pin me-2"></i>
                        <?= e(t('admin_sms.broadcast_btn', ['count' => $recipientCount])) ?>
                    </button>
                </form>

                <!-- Balance checker -->
                <hr class="my-3">
                <div x-data="balanceChecker()" class="text-center">
                    <button @click="check()" :disabled="loading" class="btn btn-outline-secondary btn-sm">
                        <span x-show="!loading"><i class="bi bi-wallet2 me-1"></i><?= e(t('admin_sms.check_balance_btn')) ?></span>
                        <span x-show="loading"><i class="bi bi-hourglass-split me-1"></i><?= e(t('admin_sms.checking_label')) ?></span>
                    </button>
                    <div x-show="result !== null" class="mt-2 p-2 rounded" style="background:var(--surface-muted);color:var(--text-primary);font-size:.82rem;">
                        <template x-if="result && result.credit_balance !== undefined">
                            <div>
                                <i class="bi bi-check-circle-fill text-success me-1"></i>
                                <?= e(t('admin_sms.remaining_credits_label')) ?> <strong x-text="result.credit_balance"></strong>
                            </div>
                        </template>
                        <template x-if="result && result.error">
                            <div class="text-danger">
                                <i class="bi bi-x-circle-fill me-1"></i>
                                <span x-text="result.error"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ── Send a Post by SMS ──────────────────────────────────────────────────
     The third way to send, and the one staff actually want most days: take
     something already published and text it, rather than retyping it into the
     broadcast box and getting the wording slightly wrong.

     The message shown here is built server-side by PostSms — the same service
     auto-send-on-publish uses — so the preview is the real thing and not a
     JavaScript approximation that could differ from what goes out. -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;"
     x-data="postSms(<?= e(json_encode([
         'preType'   => $preselectType ?? '',
         'preId'     => (int) ($preselectId ?? 0),
         'allCount'  => (int) $recipientCount,
         'puroks'    => $purokReach ?? [],
         'limit'     => \App\Services\PostSms::LIMIT,
         'canSend'   => (bool) $smsConfigured,
     ])) ?>)" x-init="init()">
    <div class="card-body p-4">

        <h5 class="fw-700 mb-1" style="color:var(--text-primary);font-size:1rem;">
            <i class="bi bi-megaphone me-2" style="color:var(--brand-primary);"></i><?= e(t('sms_post.title')) ?>
        </h5>
        <p class="text-muted mb-3" style="font-size:.8rem;"><?= e(t('sms_post.subtitle')) ?></p>

        <?php if (!$smsConfigured): ?>
        <div class="alert alert-warning py-2 px-3" style="font-size:.82rem;">
            <i class="bi bi-exclamation-triangle me-1"></i><?= e(t('sms_post.not_configured')) ?>
        </div>
        <?php elseif ($testMode): ?>
        <div class="alert alert-info py-2 px-3" style="font-size:.82rem;">
            <i class="bi bi-flask me-1"></i><?= e(t('sms_post.test_mode_note')) ?>
        </div>
        <?php endif; ?>

        <form method="post" action="<?= e(route('admin/sms/post')) ?>" @submit.prevent="submit($el)">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="post_type" :value="type">
            <input type="hidden" name="post_id"   :value="post ? post.id : ''">

            <div class="row g-3">

                <!-- ── Left: pick the post ─────────────────────────────── -->
                <div class="col-lg-6">
                    <label class="form-label fw-600" style="font-size:.85rem;"><?= e(t('sms_post.type_label')) ?></label>
                    <div class="btn-group w-100 mb-3" role="group">
                        <?php foreach (['announcement', 'event', 'ordinance'] as $ptype): ?>
                        <button type="button" class="btn btn-sm"
                                :class="type === '<?= $ptype ?>' ? 'btn-primary' : 'btn-outline-secondary'"
                                @click="setType('<?= $ptype ?>')">
                            <?= e(t('sms_post.type_' . $ptype)) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>

                    <label for="post-search" class="form-label fw-600" style="font-size:.85rem;"><?= e(t('sms_post.search_label')) ?></label>
                    <input type="text" id="post-search" class="form-control mb-2"
                           placeholder="<?= e(t('sms_post.search_ph')) ?>"
                           x-model="query" @input.debounce.350ms="search()">

                    <div style="max-height:240px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;">
                        <template x-if="loading">
                            <div class="p-3 text-muted" style="font-size:.82rem;"><?= e(t('sms_post.searching')) ?></div>
                        </template>
                        <template x-if="!loading && results.length === 0">
                            <div class="p-3 text-muted" style="font-size:.82rem;"><?= e(t('sms_post.no_posts')) ?></div>
                        </template>
                        <template x-for="p in results" :key="p.id">
                            <button type="button"
                                    class="w-100 text-start border-0 p-2 d-flex justify-content-between align-items-start gap-2"
                                    :style="post && post.id === p.id
                                        ? 'background:var(--brand-primary);color:#fff;'
                                        : 'background:transparent;color:var(--text-primary);'"
                                    @click="choose(p)">
                                <span style="min-width:0;">
                                    <span style="font-size:.82rem;font-weight:600;display:block;" x-text="p.title"></span>
                                    <span style="font-size:.72rem;opacity:.75;" x-text="p.date"></span>
                                </span>
                                <span class="d-flex gap-1 flex-shrink-0">
                                    <template x-if="p.is_sample">
                                        <span class="badge" style="background:#fef3c7;color:#92400e;font-size:.62rem;"><?= e(t('sms_post.sample_badge')) ?></span>
                                    </template>
                                    <template x-if="p.last_sent">
                                        <span class="badge" style="background:#fee2e2;color:#991b1b;font-size:.62rem;"><i class="bi bi-arrow-repeat"></i></span>
                                    </template>
                                </span>
                            </button>
                        </template>
                    </div>

                    <!-- Warnings that must be read before spending credits -->
                    <template x-if="post && post.is_sample">
                        <div class="alert alert-warning mt-2 py-2 px-3" style="font-size:.8rem;">
                            <i class="bi bi-exclamation-triangle me-1"></i><?= e(t('sms_post.sample_warning')) ?>
                        </div>
                    </template>
                    <template x-if="post && post.last_sent">
                        <div class="alert alert-danger mt-2 py-2 px-3" style="font-size:.8rem;">
                            <i class="bi bi-clock-history me-1"></i>
                            <span x-text="alreadySentText()"></span>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="resend-ok" x-model="resendOk">
                                <label class="form-check-label fw-600" for="resend-ok" style="font-size:.8rem;">
                                    <?= e(t('sms_post.already_sent_confirm')) ?>
                                </label>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- ── Right: message + recipients ─────────────────────── -->
                <div class="col-lg-6">
                    <label for="post-message" class="form-label fw-600" style="font-size:.85rem;"><?= e(t('sms_post.preview_label')) ?></label>
                    <textarea id="post-message" name="message" class="form-control" rows="5"
                              x-model="message" :disabled="!post"></textarea>
                    <div class="d-flex justify-content-between mt-1 mb-3">
                        <span class="form-text"><?= e(t('sms_post.preview_help', ['limit' => \App\Services\PostSms::LIMIT])) ?></span>
                        <span class="form-text" :class="message.length > limit ? 'text-danger fw-bold' : ''">
                            <span x-text="message.length"></span>/<span x-text="limit"></span>
                        </span>
                    </div>

                    <label class="form-label fw-600" style="font-size:.85rem;"><?= e(t('sms_post.recipients_label')) ?></label>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="rm-all" value="all" name="recipient_mode" x-model="mode">
                        <label class="form-check-label" for="rm-all" style="font-size:.84rem;">
                            <?= e(t('sms_post.to_all', ['n' => (string) $recipientCount])) ?>
                        </label>
                    </div>

                    <?php if (($purokReach ?? []) !== []): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="rm-purok" value="purok" name="recipient_mode" x-model="mode">
                        <label class="form-check-label" for="rm-purok" style="font-size:.84rem;"><?= e(t('sms_post.to_purok')) ?></label>
                    </div>
                    <div x-show="mode === 'purok'" x-cloak class="ms-4 mb-2">
                        <select name="purok" class="form-select form-select-sm" x-model="purok">
                            <?php foreach ($purokReach as $purokName => $purokN): ?>
                            <option value="<?= e($purokName) ?>">
                                <?= e(t('sms_post.purok_option', ['purok' => $purokName, 'n' => (string) $purokN])) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="rm-sel" value="selected" name="recipient_mode" x-model="mode">
                        <label class="form-check-label" for="rm-sel" style="font-size:.84rem;"><?= e(t('sms_post.to_selected')) ?></label>
                    </div>
                    <div x-show="mode === 'selected'" x-cloak class="ms-4">
                        <input type="text" class="form-control form-control-sm mb-1"
                               placeholder="<?= e(t('sms_post.pick_ph')) ?>"
                               x-model="pickQuery" @input.debounce.350ms="findPeople()">
                        <div x-show="people.length" style="max-height:110px;overflow-y:auto;" class="mb-1">
                            <template x-for="u in people" :key="u.id">
                                <button type="button" class="btn btn-sm btn-outline-secondary w-100 text-start mb-1"
                                        style="font-size:.76rem;" @click="addPerson(u)">
                                    <span x-text="u.name"></span> — <span x-text="u.phone"></span>
                                </button>
                            </template>
                        </div>
                        <div class="mb-1" style="font-size:.78rem;">
                            <template x-if="picked.length === 0">
                                <span class="text-muted"><?= e(t('sms_post.picked_none')) ?></span>
                            </template>
                            <template x-for="u in picked" :key="u.id">
                                <span class="badge me-1 mb-1" style="background:var(--surface-muted);color:var(--text-primary);">
                                    <span x-text="u.name"></span>
                                    <a href="#" @click.prevent="removePerson(u)" class="ms-1">&times;</a>
                                    <input type="hidden" name="user_ids[]" :value="u.id">
                                </span>
                            </template>
                        </div>
                        <label class="form-label fw-600 mt-1" style="font-size:.78rem;"><?= e(t('sms_post.extra_label')) ?></label>
                        <input type="text" name="extra_numbers" class="form-control form-control-sm"
                               placeholder="<?= e(t('sms_post.extra_ph')) ?>" x-model="extra" @input="recount()">
                    </div>

                    <!-- What this will cost, before the button is pressed -->
                    <div class="mt-3 p-2 rounded" style="background:var(--surface-muted);font-size:.8rem;">
                        <i class="bi bi-cash-coin me-1"></i>
                        <span x-text="costLine()"></span>
                    </div>

                    <button type="submit" class="btn w-100 fw-600 mt-3"
                            style="background:#dc2626;color:#fff;border-radius:8px;padding:.625rem 1.25rem;"
                            :disabled="!canSubmit()">
                        <i class="bi bi-send-fill me-2"></i>
                        <span x-text="buttonLabel()"></span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ── SMS Logs table ──────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm" style="border-radius:12px;">
    <div class="card-body p-0">

        <!-- Table header + filters -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 p-3 border-bottom">
            <h5 class="fw-700 mb-0" style="color:var(--text-primary);font-size:1rem;">
                <i class="bi bi-journal-text me-2" style="color:var(--brand-primary);"></i><?= e(t('admin_sms.logs_title')) ?>
                <span class="badge ms-1" style="background:#f1f5f9;color:#475569;font-size:.7rem;"><?= number_format($total) ?></span>
            </h5>
            <form method="get" action="<?= e(route('admin/sms')) ?>" class="d-flex gap-2 flex-wrap">
                <select name="type" class="form-select form-select-sm" style="min-width:130px;">
                    <option value=""><?= e(t('admin_sms.filter_all_types')) ?></option>
                    <?php foreach ($typeMeta as $val => [$label, $_]): ?>
                    <option value="<?= e($val) ?>" <?= $filterType === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="form-select form-select-sm" style="min-width:110px;">
                    <option value=""><?= e(t('admin_sms.filter_all_status')) ?></option>
                    <?php foreach ($statusMeta as $val => [$label, $_]): ?>
                    <option value="<?= e($val) ?>" <?= $filterStatus === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline-secondary btn-sm"><?= e(t('admin_sms.filter_btn')) ?></button>
                <?php if ($filterType !== '' || $filterStatus !== ''): ?>
                <a href="<?= e(route('admin/sms')) ?>" class="btn btn-link btn-sm text-muted text-decoration-none"><?= e(t('residents.clear')) ?></a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($logs)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-inbox" style="font-size:2.5rem;opacity:.4;"></i>
            <p class="mt-2 mb-0" style="font-size:.9rem;"><?= e(t('admin_sms.empty_logs')) ?></p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:.83rem;">
                <thead style="background:var(--surface-muted);">
                    <tr>
                        <th class="ps-3 fw-600"><?= e(t('admin_sms.col_datetime')) ?></th>
                        <th class="fw-600"><?= e(t('admin_sms.col_phone')) ?></th>
                        <th class="fw-600"><?= e(t('admin_sms.col_type')) ?></th>
                        <th class="fw-600"><?= e(t('residents.col_status')) ?></th>
                        <th class="fw-600" style="max-width:340px;"><?= e(t('admin_sms.col_message')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                <?php
                    [$typeLabel, $typeStyle] = $typeMeta[$log['type']] ?? [t('admin_sms.type_unknown'), 'background:#f1f5f9;color:#475569;'];
                    [$statusLabel, $statusStyle] = $statusMeta[$log['status']] ?? ['?', 'background:#f1f5f9;color:#475569;'];
                ?>
                <tr>
                    <td class="ps-3 text-nowrap">
                        <?= e(date('M d, Y', strtotime($log['created_at']))) ?><br>
                        <span class="text-muted" style="font-size:.75rem;"><?= e(date('h:i A', strtotime($log['created_at']))) ?></span>
                    </td>
                    <td class="text-nowrap fw-600"><?= e($log['phone']) ?></td>
                    <td>
                        <span class="badge" style="<?= $typeStyle ?>font-size:.7rem;"><?= $typeLabel ?></span>
                    </td>
                    <td>
                        <span class="badge" style="<?= $statusStyle ?>font-size:.7rem;"><?= $statusLabel ?></span>
                    </td>
                    <td style="max-width:340px;">
                        <span title="<?= e($log['message']) ?>">
                            <?= e(mb_strimwidth($log['message'], 0, 80, '…')) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top">
            <p class="text-muted mb-0" style="font-size:.8rem;">
                <?= e(t('admin_sms.showing_range', [
                    'from'  => (($page - 1) * $perPage) + 1,
                    'to'    => min($page * $perPage, $total),
                    'total' => number_format($total),
                ])) ?>
            </p>
            <div class="d-flex gap-1">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&type=<?= urlencode($filterType) ?>&status=<?= urlencode($filterStatus) ?>"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <?php endif; ?>
                <span class="btn btn-sm" style="background:#f0faf4;color:var(--brand-primary);font-weight:600;cursor:default;">
                    <?= $page ?> / <?= $totalPages ?>
                </span>
                <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&type=<?= urlencode($filterType) ?>&status=<?= urlencode($filterStatus) ?>"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<!-- Alpine.js component scripts for this page -->
<style>
    /* Result rows: transparent until hovered, so the list reads as one
       surface rather than a stack of buttons. Tokens, so it themes itself. */
    .sms-recipient-row { background: transparent; }
    .sms-recipient-row:hover,
    .sms-recipient-row:focus-visible { background: var(--surface-muted); }
</style>

<script>
/*
 * Recipient picker for the manual SMS form.
 *
 * The input is the real `phone` field the form posts, so choosing someone
 * simply writes their number into it. That keeps the server side unchanged and
 * means a staff member who already knows a number can still just type it —
 * there is no separate "pick or type" mode to get stuck in.
 *
 * NOTE: strings below use bare json_encode(), not e(json_encode(...)). A
 * <script> element does not HTML-decode its contents, so an escaped quote
 * would reach the JS parser as "&quot;" and break the whole block.
 */
/*
 * "Send a Post by SMS".
 *
 * Everything that decides the outgoing message comes from the server: the
 * picker returns each post's real text, built by the same PostSms the
 * auto-send uses. This component never assembles a message itself, so the
 * preview cannot drift from what is actually sent.
 *
 * The recipient count is computed here only to show the cost. The server
 * re-resolves the list from the chosen mode before sending — a form that
 * carried its own list of numbers would be a form that could be edited to
 * text anyone.
 */
function postSms(cfg) {
    return {
        type:      cfg.preType || 'announcement',
        query:     '',
        results:   [],
        loading:   false,
        post:      null,
        message:   '',
        limit:     cfg.limit,
        mode:      'all',
        purok:     Object.keys(cfg.puroks || {})[0] || '',
        pickQuery: '',
        people:    [],
        picked:    [],
        extra:     '',
        resendOk:  false,
        cfg:       cfg,

        init() {
            this.search().then(() => {
                // Arrived from a "Send by SMS" row action: select that post.
                if (cfg.preId) {
                    const hit = this.results.find(p => p.id === cfg.preId);
                    if (hit) { this.choose(hit); }
                }
            });
        },

        setType(t) {
            if (this.type === t) { return; }
            this.type    = t;
            this.post    = null;
            this.message = '';
            this.search();
        },

        async search() {
            this.loading = true;
            try {
                const base = (window.BarangGabay?.baseUrl || '').replace(/\/$/, '');
                const res  = await fetch(
                    base + '/api/admin/sms/posts?type=' + encodeURIComponent(this.type)
                         + '&q=' + encodeURIComponent(this.query),
                    { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
                );
                const data = await res.json();
                this.results = data.results || [];
            } catch (_) {
                this.results = [];
            } finally {
                this.loading = false;
            }
        },

        choose(p) {
            this.post     = p;
            this.message  = p.message;      // the server's text, verbatim
            this.resendOk = false;          // a new post needs its own confirm
        },

        alreadySentText() {
            if (!this.post || !this.post.last_sent) { return ''; }
            return <?= json_encode(t('sms_post.already_sent')) ?>
                .replace(':date', this.post.last_sent.sent_at)
                .replace(':n', this.post.last_sent.recipients);
        },

        async findPeople() {
            try {
                const base = (window.BarangGabay?.baseUrl || '').replace(/\/$/, '');
                const res  = await fetch(base + '/api/admin/sms/recipients?q=' + encodeURIComponent(this.pickQuery), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                const have = this.picked.map(u => u.id);
                this.people = (data.results || []).filter(u => !have.includes(u.id));
            } catch (_) {
                this.people = [];
            }
        },

        addPerson(u) {
            if (!this.picked.some(p => p.id === u.id)) { this.picked.push(u); }
            this.people    = this.people.filter(p => p.id !== u.id);
            this.pickQuery = '';
        },

        removePerson(u) { this.picked = this.picked.filter(p => p.id !== u.id); },

        /** Hand-typed numbers, counted the same way the server counts them. */
        extraCount() {
            return this.extra.split(/[\s,;]+/)
                .map(s => s.replace(/[^0-9+]/g, ''))
                .filter(s => s.length >= 10).length;
        },

        recount() { /* x-model already re-renders; here for @input clarity */ },

        recipients() {
            let n = 0;
            if (this.mode === 'all')      { n = this.cfg.allCount; }
            if (this.mode === 'purok')    { n = this.cfg.puroks[this.purok] || 0; }
            if (this.mode === 'selected') { n = this.picked.length; }
            return n + this.extraCount();
        },

        segments() { return Math.max(1, Math.ceil(this.message.length / this.limit)); },
        credits()  { return this.recipients() * this.segments(); },

        costLine() {
            return <?= json_encode(t('sms_post.cost_line')) ?>
                .replace(':recipients', this.recipients())
                .replace(':segments', this.segments())
                .replace(':credits', this.credits());
        },

        canSubmit() {
            if (!this.cfg.canSend) { return false; }
            if (!this.post) { return false; }
            if (this.message.length === 0 || this.message.length > this.limit) { return false; }
            if (this.recipients() === 0) { return false; }
            // A post that already went out needs the box ticked.
            if (this.post.last_sent && !this.resendOk) { return false; }
            return true;
        },

        buttonLabel() {
            if (!this.post || this.recipients() === 0) {
                return <?= json_encode(t('sms_post.send_disabled')) ?>;
            }
            return <?= json_encode(t('sms_post.send_button')) ?>
                .replace(':n', this.recipients())
                .replace(':credits', this.credits());
        },

        /* Credits are real money and a send cannot be undone, so the last word
           is a confirm that states the count and the cost. */
        submit(form) {
            if (!this.canSubmit()) { return; }
            const msg = <?= json_encode(t('sms_post.confirm')) ?>
                .replace(':n', this.recipients())
                .replace(':credits', this.credits());
            if (window.confirm(msg)) { form.submit(); }
        },
    };
}

function smsRecipients() {
    return {
        term:    '',
        results: [],
        loading: false,
        open:    false,
        picked:  '',

        async search() {
            this.loading = true;
            try {
                const base = (window.BarangGabay?.baseUrl || '').replace(/\/$/, '');
                const res  = await fetch(base + '/api/admin/sms/recipients?q=' + encodeURIComponent(this.term), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                this.results = data.results || [];
            } catch (_) {
                this.results = [];
            } finally {
                this.loading = false;
            }
        },

        choose(r) {
            this.term   = r.phone;          // this IS the posted field
            this.picked = <?= json_encode(t('admin_sms.recipient_chosen')) ?>.replace(':name', r.name);
            this.open   = false;
        },
    };
}

function charCounter(textareaId, max) {
    return {
        remaining: max,
        update() {
            const el = document.getElementById(textareaId);
            this.remaining = max - (el ? el.value.length : 0);
        },
        init() { this.update(); }
    };
}

function balanceChecker() {
    return {
        loading: false,
        result:  null,
        check() {
            this.loading = true;
            this.result  = null;
            fetch(window.BarangGabay.baseUrl + '/api/admin/sms/balance', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => { this.result = data; })
            .catch(() => { this.result = { error: <?= json_encode(t('admin_sms.request_failed')) ?> }; })
            .finally(() => { this.loading = false; });
        }
    };
}
</script>

<?php
$content   = ob_get_clean();
$pageTitle = t('admin_nav.sms');
require __DIR__ . '/../../layouts/admin.php';
