<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Services\CertificateGeneratorService;

/**
 * Digital barangay documents:
 *  - Admin → Document Templates: wording, officials, contact lines,
 *    numbering, authorized e-signatures, sample PDFs and the audit log.
 *  - Staff: the auto-generated preview of a request before approving, the
 *    Print Queue, and (admin) cancelling an issued document.
 *  - Resident: asking for a printed copy of an approved document.
 */
class DocumentTemplateController
{
    // ── Admin: templates ────────────────────────────────────────────────

    /** GET /admin/document-templates */
    public function index(): void
    {
        $templates = [];
        foreach (DocumentTemplate::TYPES as $type) {
            $templates[$type] = DocumentTemplate::get($type);
        }
        $signatures = [];
        foreach (DocumentTemplate::SIGNERS as $signer => $role) {
            $signatures[$signer] = [
                'role' => $role,
                'has'  => DocumentTemplate::hasSignature($signer),
                'at'   => (string) \App\Models\Setting::get('doc_sig_' . $signer . '_at', ''),
            ];
        }
        $settings = [];
        foreach (DocumentTemplate::SETTINGS as $key => [$label, $default]) {
            $settings[$key] = ['label' => $label, 'value' => DocumentTemplate::setting($key), 'default' => $default];
        }

        view('admin/document_templates/index', [
            'templates'    => $templates,
            'signatures'   => $signatures,
            'settings'     => $settings,
            'placeholders' => DocumentTemplate::PLACEHOLDERS,
            'log'          => DocumentRequest::generationLog(25),
            'pendingCount' => User::countByStatus('pending'),
            'pageTitle'    => 'Document Templates',
        ]);
    }

    /** POST /admin/document-templates/settings — officials, contact lines, numbering */
    public function saveSettings(): void
    {
        check_csrf();
        $values = [];
        foreach (DocumentTemplate::SETTINGS as $key => [$label]) {
            $value = mb_substr(trim((string) ($_POST[$key] ?? '')), 0, 150);
            if (in_array($key, ['doc_or_start', 'doc_permit_start'], true) && $value !== '' && !ctype_digit($value)) {
                flash('error', $label . ' must be a whole number.');
                redirect('/admin/document-templates#settings');
            }
            $values[$key] = $value;
        }
        \App\Models\Setting::setMany($values, $this->me());
        \App\Models\Setting::flush();
        AuditLog::record($this->me(), 'documents.settings', 'Document officials / contact lines / numbering updated');
        flash('success', 'Saved. New documents use these details.');
        redirect('/admin/document-templates#settings');
    }

    /** POST /admin/document-templates/signature — upload an authorized e-signature */
    public function saveSignature(): void
    {
        check_csrf();
        $signer = (string) ($_POST['signer'] ?? '');
        $file   = $_FILES['signature'] ?? null;
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if (!isset(DocumentTemplate::SIGNERS[$signer]) || $uploadError !== UPLOAD_ERR_OK) {
            flash('error', match ($uploadError) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The signature image is too large to upload. Use a file under 10 MB.',
                UPLOAD_ERR_PARTIAL                        => 'The upload was interrupted. Please try again.',
                UPLOAD_ERR_NO_FILE                        => 'Choose the signature image to upload.',
                default                                   => 'The signature could not be uploaded (error ' . $uploadError . '). Please try again.',
            });
            redirect('/admin/document-templates#signatures');
        }
        if (empty($_POST['authorized'])) {
            flash('error', 'Confirm that this e-signature is authorized by the officer for barangay documents.');
            redirect('/admin/document-templates#signatures');
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = (string) finfo_file($finfo, (string) $file['tmp_name']);
        finfo_close($finfo);

        $error = DocumentTemplate::saveSignature($signer, (string) file_get_contents((string) $file['tmp_name']), $mime, $this->me());
        if ($error !== null) {
            flash('error', $error);
        } else {
            AuditLog::record($this->me(), 'documents.signature_uploaded', DocumentTemplate::SIGNERS[$signer] . ' e-signature uploaded (authorized)');
            flash('success', DocumentTemplate::SIGNERS[$signer] . ' e-signature saved. It is applied to documents when they are approved.');
        }
        redirect('/admin/document-templates#signatures');
    }

    /** POST /admin/document-templates/signature/remove */
    public function removeSignature(): void
    {
        check_csrf();
        $signer = (string) ($_POST['signer'] ?? '');
        if (isset(DocumentTemplate::SIGNERS[$signer])) {
            DocumentTemplate::removeSignature($signer, $this->me());
            AuditLog::record($this->me(), 'documents.signature_removed', DocumentTemplate::SIGNERS[$signer] . ' e-signature removed');
            flash('success', DocumentTemplate::SIGNERS[$signer] . ' e-signature removed.');
        }
        redirect('/admin/document-templates#signatures');
    }

    /** GET /admin/document-templates/signature/{signer} — the image, for the admin's own preview */
    public function signatureImage(array $params): void
    {
        $sig = DocumentTemplate::signature((string) ($params['signer'] ?? ''));
        if ($sig === null) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: ' . $sig['mime']);
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo $sig['data'];
        exit;
    }

    /** POST /admin/document-templates/{type} — save wording */
    public function saveTemplate(array $params): void
    {
        check_csrf();
        $type = (string) ($params['type'] ?? '');
        if (!in_array($type, DocumentTemplate::TYPES, true)) {
            flash('error', 'Unknown template.');
            redirect('/admin/document-templates');
        }
        $paragraphs = preg_split('/\R\s*\R/', str_replace("\r\n", "\n", (string) ($_POST['paragraphs'] ?? ''))) ?: [];
        $error = DocumentTemplate::save($type, (string) ($_POST['title'] ?? ''), (string) ($_POST['office'] ?? ''),
            array_map(static fn (string $p): string => preg_replace('/\s*\n\s*/', ' ', trim($p)) ?? $p, $paragraphs), $this->me());
        if ($error !== null) {
            flash('error', $error);
        } else {
            AuditLog::record($this->me(), 'documents.template_saved', DocumentRequest::label($type) . ' template wording updated');
            flash('success', DocumentRequest::label($type) . ' template saved.');
        }
        redirect('/admin/document-templates#tpl-' . $type);
    }

    /** POST /admin/document-templates/{type}/reset */
    public function resetTemplate(array $params): void
    {
        check_csrf();
        $type = (string) ($params['type'] ?? '');
        if (in_array($type, DocumentTemplate::TYPES, true)) {
            DocumentTemplate::reset($type, $this->me());
            AuditLog::record($this->me(), 'documents.template_reset', DocumentRequest::label($type) . ' template reset to the barangay default');
            flash('success', DocumentRequest::label($type) . ' template reset to the default wording.');
        }
        redirect('/admin/document-templates#tpl-' . $type);
    }

    /** GET /admin/document-templates/{type}/sample — the template with sample data and the authorized e-signature */
    public function sample(array $params): void
    {
        $type = (string) ($params['type'] ?? '');
        if (!in_array($type, DocumentTemplate::TYPES, true)) {
            http_response_code(404);
            exit;
        }
        $resident = ['full_name' => 'Juan Santos Dela Cruz', 'first_name' => 'Juan', 'middle_name' => 'Santos', 'last_name' => 'Dela Cruz',
                     'sex' => 'male', 'civil_status' => 'married', 'zone' => 'Purok 2', 'date_of_birth' => '1985-03-14'];
        $request  = ['document_type' => $type, 'purpose' => 'Employment', 'fee_amount' => $type === 'indigency' ? 0 : 50,
                     'payment_status' => 'PAID_VERIFIED', 'business_name' => 'Dela Cruz Store', 'business_type' => 'Sari-sari store'];
        $this->pdf(CertificateGeneratorService::generatePdf($request, $resident, true), 'sample-' . $type . '.pdf');
    }

    // ── Staff: preview, print queue, cancel ─────────────────────────────

    /** GET /admin/documents/{id}/draft — the auto-generated document before approval */
    public function draft(array $params): void
    {
        $request  = DocumentRequest::find((int) ($params['id'] ?? 0));
        $resident = $request ? User::find((int) $request['user_id']) : null;
        if ($request === null || $resident === null) {
            http_response_code(404);
            exit('Request not found.');
        }
        $this->pdf(CertificateGeneratorService::generatePdf($request, $resident, true), 'preview-' . $request['reference_no'] . '.pdf');
    }

    /** GET /admin/documents/print-queue */
    public function printQueue(): void
    {
        view('admin/documents/print_queue', [
            'queue'        => DocumentRequest::printQueue(),
            'pendingCount' => User::countByStatus('pending'),
            'pageTitle'    => 'Print Queue',
        ]);
    }

    /** POST /admin/documents/{id}/printed */
    public function markPrinted(array $params): void
    {
        check_csrf();
        $id = (int) ($params['id'] ?? 0);
        if (DocumentRequest::markPrinted($id, $this->me())) {
            AuditLog::record($this->me(), 'documents.printed', 'Printed copy made for request #' . $id);
            flash('success', 'Marked as printed. The resident was told to claim it at the hall.');
        } else {
            flash('error', 'That request is not waiting in the print queue.');
        }
        redirect('/admin/documents/print-queue');
    }

    /** POST /admin/documents/{id}/revoke (admin) */
    public function revoke(array $params): void
    {
        check_csrf();
        $id     = (int) ($params['id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if (mb_strlen($reason) < 5) {
            flash('error', 'Give the reason for cancelling the document.');
        } elseif (DocumentRequest::revoke($id, $this->me(), $reason)) {
            AuditLog::record($this->me(), 'documents.revoked', 'Document for request #' . $id . ' cancelled: ' . $reason);
            flash('success', 'Document cancelled. Its QR verification now shows CANCELLED.');
        } else {
            flash('error', 'Only an issued document that is not already cancelled can be cancelled.');
        }
        redirect('/admin/documents');
    }

    // ── Resident ────────────────────────────────────────────────────────

    /** POST /documents/{id}/print-request */
    public function requestPrint(array $params): void
    {
        check_csrf();
        $id    = (int) ($params['id'] ?? 0);
        $error = DocumentRequest::requestPrint($id, (int) ($_SESSION['user_id'] ?? 0));
        flash($error === null ? 'success' : 'error', $error === null
            ? __('Printed copy requested. You will be notified when it is ready to claim at the Barangay Hall.')
            : __($error));

        $request = DocumentRequest::find($id);
        $back    = (string) ($_POST['back'] ?? '');
        if ($request !== null && $back !== '' && \App\Services\DocumentQr::matches($back, $request)) {
            redirect('/d/' . $back);
        }
        redirect('/documents');
    }

    private function pdf(string $bytes, string $name): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '', $name) . '"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo $bytes;
        exit;
    }

    private function me(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }
}
