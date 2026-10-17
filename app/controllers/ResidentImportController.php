<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\ResidentImport;

/**
 * Residents → Import: upload a CSV, Excel or PDF list of residents (whole
 * barangay or one purok), check the preview, then import the ticked rows.
 * Nothing is saved until "Import" on the preview. Admin and staff.
 */
class ResidentImportController
{
    private const SESSION = 'resident_import';

    /** GET /admin/residents/import — upload form, or the preview of the last upload */
    public function show(): void
    {
        $pending = $_SESSION[self::SESSION] ?? null;
        if (is_array($pending) && time() - (int) ($pending['at'] ?? 0) > 3600) {
            unset($_SESSION[self::SESSION]);       // a preview is kept for one hour
            $pending = null;
        }

        view('admin/residents/import', [
            'pending'      => is_array($pending) ? $pending : null,
            'puroks'       => barangay_subdivisions(),
            'pendingCount' => User::countByStatus('pending'),
            'pageTitle'    => t('residents.title'),
        ]);
    }

    /** POST /admin/residents/import — read the file and show the preview */
    public function upload(): void
    {
        check_csrf();
        $file = $_FILES['list'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_INI_SIZE
                ? 'The file is too large. Split the list into smaller files (up to 10 MB).'
                : 'Choose the CSV, Excel or PDF list to upload.');
            redirect('/admin/residents/import');
        }
        if ((int) $file['size'] > ResidentImport::MAX_BYTES) {
            flash('error', 'The file is larger than 10 MB. Split the list into smaller files.');
            redirect('/admin/residents/import');
        }
        $name = basename((string) $file['name']);
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = (string) finfo_file($finfo, (string) $file['tmp_name']);
        finfo_close($finfo);
        $allowed = [
            'csv' => ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel', 'text/x-csv'],
            'txt' => ['text/plain'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
            'pdf' => ['application/pdf'],
            'xls' => ['application/vnd.ms-excel', 'application/octet-stream', 'application/CDFV2'],
        ];
        if (!isset($allowed[$ext]) || !in_array($mime, $allowed[$ext], true)) {
            flash('error', 'Upload a CSV, Excel (.xlsx) or PDF file.');
            redirect('/admin/residents/import');
        }

        $default = trim((string) ($_POST['default_purok'] ?? ''));
        $default = ResidentImport::normalisePurok($default);

        try {
            $rows     = ResidentImport::read((string) $file['tmp_name'], $name);
            $analysed = ResidentImport::analyse($rows, $default);
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
            redirect('/admin/residents/import');
        }
        if ($analysed['rows'] === []) {
            flash('error', 'No residents were found in this file. Check that it has a "Name" column (or one name per line).');
            redirect('/admin/residents/import');
        }

        $_SESSION[self::SESSION] = [
            'file'      => $name,
            'at'        => time(),
            'default'   => $default,
            'header'    => $analysed['header'],
            'truncated' => $analysed['truncated'],
            'rows'      => $analysed['rows'],
        ];
        redirect('/admin/residents/import#preview');
    }

    /** POST /admin/residents/import/confirm — import the ticked rows */
    public function confirm(): void
    {
        check_csrf();
        $pending = $_SESSION[self::SESSION] ?? null;
        if (!is_array($pending)) {
            flash('error', 'The preview has expired. Upload the file again.');
            redirect('/admin/residents/import');
        }

        $chosen = [];
        foreach ((array) ($_POST['include'] ?? []) as $i => $on) {
            $i = (int) $i;
            if (isset($pending['rows'][$i])) {
                $chosen[$i] = (string) ($_POST['purok'][$i] ?? $pending['rows'][$i]['purok'] ?? '');
            }
        }
        if ($chosen === []) {
            flash('error', 'Tick at least one resident to import.');
            redirect('/admin/residents/import#preview');
        }

        $me = (int) ($_SESSION['user_id'] ?? 0);
        try {
            $result = ResidentImport::import($pending['rows'], $chosen, $me);
        } catch (\Throwable $e) {
            error_log('[ResidentImport] ' . $e->getMessage());
            flash('error', 'The import stopped: ' . $e->getMessage());
            redirect('/admin/residents/import#preview');
        }
        unset($_SESSION[self::SESSION]);

        AuditLog::record($me ?: null, 'residents.imported', sprintf('Imported %d resident(s) from %s (%d skipped)',
            $result['created'], $pending['file'], $result['skipped']));
        flash('success', sprintf('%d resident(s) imported from %s.%s', $result['created'], $pending['file'],
            $result['skipped'] > 0 ? ' ' . $result['skipped'] . ' skipped (already registered).' : ''));

        $puroks = array_unique(array_filter(array_values($chosen)));
        redirect('/admin/residents' . (count($puroks) === 1 ? '?purok=' . rawurlencode((string) reset($puroks)) : ''));
    }

    /** POST /admin/residents/import/cancel */
    public function cancel(): void
    {
        check_csrf();
        unset($_SESSION[self::SESSION]);
        redirect('/admin/residents/import');
    }

    /** GET /admin/residents/import/template — CSV with the recognised columns */
    public function template(): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="residents-import-template.csv"');
        echo ResidentImport::templateCsv();
        exit;
    }
}
