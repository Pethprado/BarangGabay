<?php
/**
 * Admin feedback management page — real two-way conversation threads.
 * Variables: $feedbacks (array), $unread (int), $search (string), $pageTitle (string)
 */
$feedbacks = $feedbacks ?? [];
$unread    = (int) ($unread ?? 0);
$search    = $search ?? '';

$answered   = count(array_filter($feedbacks, fn($f) => !empty($f['has_staff_reply'])));
$unanswered = count($feedbacks) - $answered;

ob_start();
?>

<!-- Stats row -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="admin-card text-center py-4">
            <p class="fs-3 fw-bold mb-0"><?= count($feedbacks) ?></p>
            <p class="small text-muted mb-0"><?= e(t('admin_feedback.stat_total_threads')) ?></p>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="admin-card text-center py-4" style="border-left:3px solid #f59e0b;">
            <p class="fs-3 fw-bold mb-0 text-warning"><?= $unread ?></p>
            <p class="small text-muted mb-0"><?= e(t('admin_feedback.stat_unread')) ?></p>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="admin-card text-center py-4" style="border-left:3px solid #10b981;">
            <p class="fs-3 fw-bold mb-0 text-success"><?= $answered ?></p>
            <p class="small text-muted mb-0"><?= e(t('admin_feedback.stat_answered')) ?></p>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="admin-card text-center py-4" style="border-left:3px solid #ef4444;">
            <p class="fs-3 fw-bold mb-0 text-danger"><?= $unanswered ?></p>
            <p class="small text-muted mb-0"><?= e(t('admin_feedback.stat_unanswered')) ?></p>
        </div>
    </div>
</div>

<!-- Search bar -->
<form method="get" action="<?= e(route('admin/feedback')) ?>" class="mb-4">
    <div class="input-group" style="max-width:440px;">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="search" class="form-control"
               placeholder="<?= e(t('admin_feedback.search_ph')) ?>"
               value="<?= e($search) ?>">
        <button type="submit" class="btn btn-barangay"><?= e(t('common.search')) ?></button>
        <?php if ($search): ?>
        <a href="<?= e(route('admin/feedback')) ?>" class="btn btn-outline-secondary"><?= e(t('residents.clear')) ?></a>
        <?php endif; ?>
    </div>
</form>

<!-- Threads table -->
<?php if (empty($feedbacks)): ?>
<div class="admin-card text-center py-5">
    <i class="bi bi-chat-square fs-1 text-muted opacity-25 d-block mb-3"></i>
    <p class="text-muted mb-0"><?= e(t('admin_feedback.empty_msg')) ?></p>
</div>
<?php else: ?>

<div class="admin-card p-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4" style="width:2rem;">#</th>
                    <th><?= e(t('residents.col_resident')) ?></th>
                    <th><?= e(t('admin_feedback.col_last_message')) ?></th>
                    <th><?= e(t('residents.col_status')) ?></th>
                    <th><?= e(t('admin_feedback.col_date')) ?></th>
                    <th class="pe-4"><?= e(t('residents.col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($feedbacks as $fb):
                    $isUnread = (int) $fb['unread_count'] > 0;
                    $isAnswered = !empty($fb['has_staff_reply']);
                ?>
                <tr class="<?= $isUnread ? 'table-warning bg-opacity-25' : '' ?>">
                    <td class="ps-4 text-muted small"><?= (int) $fb['id'] ?></td>
                    <td>
                        <p class="mb-0 fw-semibold small"><?= e($fb['resident_name'] ?? '—') ?></p>
                        <p class="mb-0 text-muted" style="font-size:.7rem;"><?= e($fb['resident_email'] ?? '') ?></p>
                    </td>
                    <td style="max-width:280px;">
                        <p class="mb-0 small text-truncate" style="max-width:260px;">
                            <?php if (($fb['last_sender_role'] ?? 'resident') !== 'resident'): ?>
                            <i class="bi bi-reply-fill text-success me-1"></i>
                            <?php endif; ?>
                            <?= e($fb['last_message'] ?? '') ?>
                        </p>
                        <p class="mb-0 text-muted" style="font-size:.7rem;">
                            <?= (int) $fb['message_count'] ?> <?= e(t('admin_feedback.messages_in_thread_suffix')) ?>
                        </p>
                    </td>
                    <td>
                        <?php if ($isUnread): ?>
                        <span class="badge" style="background:#fff3cd;border:1px solid #ffc107;color:#856404;">
                            <i class="bi bi-envelope me-1"></i><?= e(t('admin_feedback.badge_new')) ?>
                        </span>
                        <?php elseif ($isAnswered): ?>
                        <span class="badge" style="background:#d4edda;border:1px solid #c3e6cb;color:#155724;">
                            <i class="bi bi-check-circle me-1"></i><?= e(t('admin_feedback.badge_answered')) ?>
                        </span>
                        <?php else: ?>
                        <span class="badge" style="background:#f1f5f9;border:1px solid #cbd5e1;color:#475569;">
                            <i class="bi bi-clock me-1"></i><?= e(t('admin_feedback.badge_waiting')) ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted">
                        <?= date('M j, Y', strtotime($fb['last_message_at'] ?? $fb['created_at'])) ?><br>
                        <span class="text-muted opacity-75"><?= date('g:i A', strtotime($fb['last_message_at'] ?? $fb['created_at'])) ?></span>
                    </td>
                    <td class="pe-4">
                        <button type="button"
                                class="btn btn-sm btn-outline-primary"
                                onclick="openThread(<?= (int) $fb['id'] ?>, <?= htmlspecialchars(json_encode((string) $fb['resident_name']), ENT_QUOTES) ?>)">
                            <i class="bi bi-chat-dots me-1"></i><?= e(t('admin_feedback.open_thread_btn')) ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<!-- Thread / Reply Modal -->
<div class="modal fade" id="replyModal" tabindex="-1" aria-labelledby="replyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header border-bottom">
                <h5 class="modal-title" id="replyModalLabel">
                    <i class="bi bi-chat-dots me-2 text-primary"></i>
                    <span id="modalResidentName"><?= e(t('admin_feedback.modal_default_title')) ?></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <!-- Conversation history -->
                <div id="modalMessages" class="mb-4 d-flex flex-column gap-3" style="max-height:360px;overflow-y:auto;">
                    <div class="text-center text-muted py-4" id="modalLoading">
                        <div class="spinner-border spinner-border-sm me-2"></div> <?= e(t('admin_feedback.modal_loading')) ?>
                    </div>
                </div>

                <!-- Reply form -->
                <form id="replyForm" method="post">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="mb-3">
                        <label for="replyText" class="form-label fw-semibold">
                            <?= e(t('admin_feedback.reply_label')) ?> <span class="text-danger">*</span>
                        </label>
                        <textarea id="replyText"
                                  name="reply"
                                  class="form-control"
                                  rows="4"
                                  required
                                  placeholder="<?= e(t('admin_feedback.reply_ph')) ?>"></textarea>
                    </div>
                    <button type="submit" class="btn btn-barangay">
                        <i class="bi bi-send me-1"></i> <?= e(t('admin_feedback.send_reply_btn')) ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const fbI18n = <?= json_encode([
    'conversationWith' => t('admin_feedback.js_conversation_with'),
    'loading'          => t('admin_feedback.modal_loading'),
    'noMessages'       => t('admin_feedback.js_no_messages'),
    'loadError'        => t('admin_feedback.js_load_error'),
    'roleStaff'        => t('admin_feedback.js_role_staff'),
    'roleAdmin'        => t('admin_feedback.js_role_admin'),
    'roleSuperadmin'   => t('admin_feedback.js_role_superadmin'),
    'roleResident'     => t('admin_feedback.js_role_resident'),
]) ?>;

function openThread(id, residentName) {
    const base = (window.BarangGabay?.baseUrl || '').replace(/\/$/, '');

    document.getElementById('modalResidentName').textContent = fbI18n.conversationWith.replace(':name', residentName);
    document.getElementById('replyForm').action = base + '/admin/feedback/' + id + '/reply';
    document.getElementById('replyText').value = '';

    const list = document.getElementById('modalMessages');
    list.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm me-2"></div> ' + fbI18n.loading + '</div>';

    const modal = new bootstrap.Modal(document.getElementById('replyModal'));
    modal.show();

    fetch(base + '/admin/feedback/' + id + '/messages', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                list.innerHTML = '<p class="text-danger small">' + data.error + '</p>';
                return;
            }
            if (!data.messages || !data.messages.length) {
                list.innerHTML = '<p class="text-muted small">' + fbI18n.noMessages + '</p>';
                return;
            }
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark'
                || (document.documentElement.getAttribute('data-theme') !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            const roleLabels = { staff: fbI18n.roleStaff, admin: fbI18n.roleAdmin, superadmin: fbI18n.roleSuperadmin, resident: fbI18n.roleResident };
            const localeTag = <?= json_encode(current_locale() === 'fil' ? 'fil-PH' : 'en-PH') ?>;
            list.innerHTML = data.messages.map(m => {
                const isResident = m.sender_role === 'resident';
                const bubbleBg = isDark
                    ? (isResident ? '#1b2436' : '#16233f')
                    : (isResident ? '#f1f5f9' : '#eff6ff');
                const nameColor = isDark
                    ? (isResident ? '#a9b4c4' : '#5b8def')
                    : (isResident ? '#475569' : '#1d4ed8');
                // A staff member's own designation ("Barangay Secretary") beats
                // the generic role label — same rule as the resident-side view.
                const designation = (m.sender_designation || '').trim();
                const roleLabel = (!isResident && designation)
                    ? designation
                    : (roleLabels[m.sender_role] || m.sender_role);
                const when = new Date(m.created_at.replace(' ', 'T')).toLocaleString(localeTag, { dateStyle: 'medium', timeStyle: 'short' });
                return '<div style="background:' + bubbleBg + ';border-radius:.75rem;padding:.75rem 1rem;">'
                     + '<p class="mb-1 small fw-bold" style="color:' + nameColor + ';">'
                     +   escapeHtml(m.sender_name) + ' <span class="fw-normal opacity-75">(' + escapeHtml(roleLabel) + ')</span>'
                     + '</p>'
                     + '<p class="mb-1" style="white-space:pre-wrap;font-size:.875rem;">' + escapeHtml(m.message) + '</p>'
                     + '<p class="mb-0 text-muted" style="font-size:.72rem;"><i class="bi bi-clock me-1"></i>' + when + '</p>'
                     + '</div>';
            }).join('');
        })
        .catch(() => {
            list.innerHTML = '<p class="text-danger small">' + fbI18n.loadError + '</p>';
        });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';