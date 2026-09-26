<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Services\NotificationService;
use App\Services\SemaphoreSmsService;

/**
 * Residents asking the barangay to prepare a document, and staff working
 * through the queue.
 *
 * The problem this solves is a trip. The commonest reason someone walks to the
 * barangay hall is to ask for a clearance or a certificate, and from Purok 7
 * that is a journey — wasted entirely if the office is shut or the signatory
 * is out. Asking here and being told by SMS when it is ready turns two trips
 * into one.
 *
 * What this is NOT: an issuing system. No document is generated, signed or
 * released by this app. A barangay clearance is a signed instrument and must
 * stay a human act at the counter — this is only the queue in front of it.
 * That boundary is deliberate and should not be eroded later.
 */
class DocumentRequestController
{
    // ── Resident ─────────────────────────────────────────────────────────

    /** GET /documents — the request form and this resident's own history. */
    public function index(): void
    {
        $userId   = (int) ($_SESSION['user_id'] ?? 0);
        $requests = DocumentRequest::forUser($userId);
        $types    = DocumentRequest::TYPES;

        view('resident/documents', compact('requests', 'types'));
    }

    /** POST /documents — file a new request. */
    public function store(): void
    {
        check_csrf();

        $userId  = (int) ($_SESSION['user_id'] ?? 0);
        $type    = trim($_POST['document_type'] ?? '');
        $purpose = trim($_POST['purpose'] ?? '');
        $notes   = trim($_POST['notes'] ?? '');

        if (!\array_key_exists($type, DocumentRequest::TYPES)) {
            flash('error', t('documents.err_type'));
            redirect('/documents');
        }

        /*
         * The purpose is required, and not as form ceremony: a barangay
         * clearance is issued FOR something, and it is printed on the document.
         * Staff cannot prepare one without it, so collecting it now saves the
         * resident being asked at the counter.
         */
        if (mb_strlen($purpose) < 3) {
            flash('error', t('documents.err_purpose'));
            redirect('/documents');
        }

        /*
         * One open request per document type. Without this a resident who taps
         * twice — or reloads — puts two identical jobs in the queue, and staff
         * cannot tell which is real. Completed and rejected requests do not
         * block a new one, so asking again later is fine.
         */
        foreach (DocumentRequest::forUser($userId) as $existing) {
            if ($existing['document_type'] === $type
                && \in_array($existing['status'], ['pending', 'processing', 'ready'], true)) {
                flash('warning', t('documents.err_duplicate', [
                    'type'      => DocumentRequest::label($type),
                    'reference' => (string) $existing['reference_no'],
                ]));
                redirect('/documents');
            }
        }

        $reference = DocumentRequest::create($userId, $type, $purpose, $notes);

        // Tell the back office there is work waiting.
        (new NotificationService())->notifyBackOffice(
            'notify_content',
            'system',
            t('documents.notify_staff_title'),
            t('documents.notify_staff_body', [
                'type'      => DocumentRequest::label($type),
                'reference' => $reference,
            ]),
            0,
            'document_request',
            $userId
        );

        flash('success', t('documents.filed', ['reference' => $reference]));
        redirect('/documents');
    }

    // ── Admin ────────────────────────────────────────────────────────────

    /** GET /admin/documents — the queue. */
    public function adminIndex(): void
    {
        $status       = trim($_GET['status'] ?? '');
        $requests     = DocumentRequest::queue($status);
        $pageTitle    = t('admin_documents.title');
        $pendingCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();

        view('admin/documents/index', compact('requests', 'status', 'pageTitle', 'pendingCount'));
    }

    /**
     * POST /admin/documents/{id}/status — move a request along.
     *
     * The resident is told every time the state changes, because the whole
     * value of this feature is that they do not have to come and ask.
     */
    public function updateStatus(array $params): void
    {
        check_csrf();

        $id      = (int) ($params['id'] ?? 0);
        $to      = trim($_POST['status'] ?? '');
        $note    = trim($_POST['staff_note'] ?? '');
        $staffId = (int) ($_SESSION['user_id'] ?? 0);

        $request = DocumentRequest::find($id);
        if ($request === null) {
            flash('error', t('flash.not_found'));
            redirect('/admin/documents');
        }

        if (!DocumentRequest::setStatus($id, $to, $staffId, $note)) {
            // A stale tab, or a second click after someone else moved it.
            flash('error', t('admin_documents.err_transition', [
                'from' => (string) $request['status'],
                'to'   => $to,
            ]));
            redirect('/admin/documents');
        }

        $this->tellResident($request, $to, $note);

        AuditLog::record(
            $staffId,
            'document_request.status',
            sprintf('%s → %s (%s)', (string) $request['status'], $to, (string) $request['reference_no'])
        );

        flash('success', t('admin_documents.updated', [
            'reference' => (string) $request['reference_no'],
            'status'    => t('documents.status_' . $to),
        ]));
        redirect('/admin/documents' . ($_POST['return_status'] ?? '' ? '?status=' . urlencode((string) $_POST['return_status']) : ''));
    }

    // ── Internals ────────────────────────────────────────────────────────

    /**
     * Notify the resident that their request moved.
     *
     * In-app always; SMS only when the document is ready to collect. That is
     * the one update worth a text message — it is the moment the resident can
     * act, and every other state change is visible next time they open the
     * portal. Paying for a text to say "we have started" would spend credits
     * to tell someone to keep waiting.
     *
     * @param array<string,mixed> $request The row as it was BEFORE the change.
     */
    private function tellResident(array $request, string $to, string $note): void
    {
        $userId    = (int) $request['user_id'];
        $reference = (string) $request['reference_no'];
        $label     = DocumentRequest::label((string) $request['document_type']);

        $body = t('documents.notify_' . $to, ['type' => $label, 'reference' => $reference]);
        if ($note !== '') {
            $body .= ' — ' . $note;
        }

        try {
            (new NotificationService())->notifyUser(
                $userId,
                'system',
                t('documents.notify_title', ['reference' => $reference]),
                $body,
                0,
                'document_request'
            );
        } catch (\Throwable $e) {
            error_log('[DocumentRequestController] notify failed for #' . $request['id'] . ': ' . $e->getMessage());
        }

        if ($to !== 'ready') {
            return;
        }

        $phone = trim((string) ($request['phone'] ?? ''));
        if ($phone === '') {
            return;
        }

        try {
            (new SemaphoreSmsService())->send(
                $phone,
                "[BarangGabay] Handa na ang inyong {$label} ({$reference}). "
                . 'Maaari na itong kunin sa Barangay Hall. - Brgy. Bayogo, Madrid',
                'document_request',
                (int) $request['id']
            );
        } catch (\Throwable $e) {
            error_log('[DocumentRequestController] SMS failed for #' . $request['id'] . ': ' . $e->getMessage());
        }
    }
}
