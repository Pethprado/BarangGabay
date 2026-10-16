<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\DocumentTemplate;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * Barangay Bayogo documents as PDF, from DocumentTemplate + DocumentFields.
 *
 * Layouts follow the barangay's paper documents: seal and government header,
 * office line, title, "TO WHOM IT MAY CONCERN:", the paragraphs, the
 * signatories, the O.R. block, and the footer with the barangay's contact
 * lines and logos. Every document also carries its control number and a
 * QR code to BarangGabay's verification page.
 *
 * $preview = true draws the same document for staff to check before
 * approving (and for the admin's Sample PDF), with the Punong Barangay's
 * authorized e-signature. It has no control number or verification QR yet;
 * those, the O.R. number and the record of who approved it are added when the
 * request is approved (approveAndGenerate refuses without the signature).
 */
class CertificateGeneratorService
{
    private const ASSETS = __DIR__ . '/../../public/assets/images/documents/';

    /** Next control number, e.g. RES-2026-000012 (concurrency-safe). */
    public static function generateCertificateNumber(string $docType): string
    {
        $prefix = match ($docType) {
            'clearance' => 'BGC',
            'residency' => 'RES',
            'indigency' => 'IND',
            'business'  => 'BUS',
            default     => 'DOC',
        };
        $year = (int) date('Y');

        return sprintf('%s-%d-%06d', $prefix, $year, self::nextSequence($prefix, $year));
    }

    /** Next official receipt number: the configured first number, then +1 each time. */
    public static function nextOrNumber(): string
    {
        $start = max(1, (int) DocumentTemplate::setting('doc_or_start'));

        return (string) ($start + self::nextSequence('OR', 0) - 1);
    }

    /** Next business permit number for the year, e.g. 2026-014. */
    public static function nextPermitNumber(): string
    {
        $year  = (int) date('Y');
        $start = max(1, (int) DocumentTemplate::setting('doc_permit_start'));

        return sprintf('%d-%03d', $year, $start + self::nextSequence('BP', $year) - 1);
    }

    /** Atomic counter per (prefix, year) in document_sequences. */
    public static function nextSequence(string $prefix, int $year): int
    {
        $pdo = db();
        for ($retry = 0; $retry < 5; $retry++) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('SELECT last_number FROM document_sequences WHERE doc_prefix = ? AND doc_year = ? FOR UPDATE');
                $stmt->execute([$prefix, $year]);
                $current = $stmt->fetchColumn();
                if ($current === false) {
                    $pdo->prepare('INSERT INTO document_sequences (doc_prefix, doc_year, last_number, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())')
                        ->execute([$prefix, $year]);
                    $next = 1;
                } else {
                    $next = (int) $current + 1;
                    $pdo->prepare('UPDATE document_sequences SET last_number = ?, updated_at = NOW() WHERE doc_prefix = ? AND doc_year = ?')
                        ->execute([$next, $prefix, $year]);
                }
                $pdo->commit();

                return $next;
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                usleep(50000);
            }
        }

        throw new \RuntimeException('Could not reserve the next ' . $prefix . ' number. Please try again.');
    }

    /**
     * The document as PDF bytes.
     *
     * @param array<string,mixed> $request  document_requests row (with certificate_no, or_number, permit_no… when final)
     * @param array<string,mixed> $resident users row
     */
    public static function generatePdf(array $request, array $resident, bool $preview = false): string
    {
        $type     = (string) ($request['document_type'] ?? 'clearance');
        $template = DocumentTemplate::get(in_array($type, DocumentTemplate::TYPES, true) ? $type : 'clearance');
        $values   = DocumentFields::for($request, $resident, $preview ? date('Y-m-d H:i:s') : null);

        $pdf = $type === 'business'
            ? self::businessPermit($template, $values, $request, $preview)
            : self::certificate($type, $template, $values, $request, $preview);

        return $pdf->Output('S');
    }

    // ── Portrait certificates (Indigency, Clearance, Residency) ─────────

    private static function certificate(string $type, array $tpl, array $v, array $request, bool $preview): BarangayPdf
    {
        $mono   = $type === 'indigency';               // the barangay types this one in Courier
        $family = $mono ? 'Courier' : 'Times';
        $size   = $mono ? 11.5 : 12.0;

        $pdf = new BarangayPdf('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(20, 15, 20);
        $pdf->AddPage();
        self::header($pdf, 20, 190, 16, $family, $mono);

        // Office line and title
        $pdf->SetTextColor(20, 20, 20);
        $pdf->SetFont($family, '', $mono ? 12.5 : 13);
        $pdf->SetXY(20, 58);
        $pdf->Cell(170, 7, BarangayPdf::enc(DocumentFields::fill($tpl['office'], $v)), 0, 1, 'C');

        $title = DocumentFields::fill($tpl['title'], $v);
        $pdf->SetFont($family, 'B', $mono ? 13 : 14);
        if ($type === 'clearance') {
            $pdf->SetTextColor(31, 56, 140);
        }
        $pdf->SetXY(20, 70);
        $pdf->Cell(170, 7, BarangayPdf::enc($title), 0, 1, 'C');
        $pdf->SetTextColor(20, 20, 20);
        if ($type === 'residency') {
            $pdf->SetLineWidth(0.5);
            $pdf->Line(25, 78.5, 185, 78.5);
            $pdf->SetLineWidth(0.2);
        }

        $pdf->SetFont($family, 'B', $size);
        $pdf->SetXY(20, 86);
        $pdf->Cell(170, 6, 'TO WHOM IT MAY CONCERN:', 0, 1, 'L');

        $y = 100;
        foreach ($tpl['paragraphs'] as $paragraph) {
            $y = $pdf->richParagraph(DocumentFields::fill($paragraph, $v), 20, $y, 170, $family, $size, 6.4, 14);
            $y += 3.5;
        }

        // Signatories, laid out from the bottom up so nothing reaches the
        // O.R. block (y 234) or the footer (y 266), however long the text.
        $base = $y + 6;
        if ($type === 'clearance') {
            $base = min($base, 168);
            $pdf->SetFont($family, '', $size);
            $pdf->SetXY(20, $base);
            $pdf->Cell(60, 6, 'Prepared by:', 0, 1, 'L');
            self::signatory($pdf, 'secretary', DocumentTemplate::setting('doc_barangay_secretary'), 'Brgy. Secretary', 22, $base + 18, 70, $family, $preview, 'C');
            $captainY = min($base + 40, 216);
        } else {
            $captainY = min(max($base + 24, 196), 216);
        }
        self::signatory($pdf, 'captain', DocumentTemplate::setting('doc_punong_barangay'),
            $type === 'indigency' ? 'Barangay Captain' : 'Punong Barangay', 112, $captainY, 78, $family, $preview, 'C');

        // O.R. block (paid documents)
        if ($type !== 'indigency' && ((float) ($request['fee_amount'] ?? 0) > 0 || $v['OR_NUMBER'] !== '')) {
            $pdf->SetFont($family, '', 11);
            $oy = 236;
            foreach ([
                'O.R No: ' . ($v['OR_NUMBER'] !== '' ? $v['OR_NUMBER'] : ($preview ? '(assigned on approval)' : '')),
                'Issued on: ' . $v['ISSUE_DATE_LONG'],
                'Issued at: Brgy. ' . preg_replace('/^Barangay\s+/i', '', $v['BARANGAY']) . ', ' . $v['MUNICIPALITY'] . ', SDS',
                'Documentary Stamp: ' . $v['DOCUMENTARY_STAMP'],
            ] as $i => $line) {
                $pdf->SetXY(20, $oy + $i * 5.6);
                $pdf->Cell(100, 5.6, BarangayPdf::enc($line), 0, 0, 'L');
            }
        }

        self::verification($pdf, $request, 164, 232, 24, $preview);
        self::footer($pdf, 20, 266, 190);

        return $pdf;
    }

    // ── Business Permit (landscape, bordered) ───────────────────────────

    private static function businessPermit(array $tpl, array $v, array $request, bool $preview): BarangayPdf
    {
        $pdf = new BarangayPdf('L', 'mm', 'A4');   // 297 x 210
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();
        // Decorative double border with corner ornaments
        $pdf->SetDrawColor(30, 30, 30);
        $pdf->SetLineWidth(1.4);
        $pdf->Rect(10, 10, 277, 190);
        $pdf->SetLineWidth(0.35);
        $pdf->Rect(14, 14, 269, 182);
        // The scroll ornaments of the barangay's permit, one per corner
        // (mirrored copies of the same corner). Simple diamonds if missing.
        $size = 34.0;
        foreach (['tl' => [5, 5], 'tr' => [297 - 5 - $size, 5], 'bl' => [5, 210 - 5 - $size], 'br' => [297 - 5 - $size, 210 - 5 - $size]] as $corner => [$cx, $cy]) {
            if (is_file(self::ASSETS . 'corner-' . $corner . '.png')) {
                $pdf->Image(self::ASSETS . 'corner-' . $corner . '.png', $cx, $cy, $size, $size);
                continue;
            }
            $pdf->SetFillColor(30, 30, 30);
            $pdf->diamond($cx + $size / 2, $cy + $size / 2, 3);
        }
        $pdf->SetLineWidth(0.35);

        self::header($pdf, 60, 237, 20, 'Times', false);

        $pdf->SetTextColor(20, 20, 20);
        $pdf->SetFont('Times', 'B', 15);
        $pdf->SetXY(20, 56);
        $pdf->Cell(257, 7, BarangayPdf::enc(DocumentFields::fill($tpl['office'], $v)), 0, 1, 'C');
        $pdf->SetFont('Times', '', 11);
        $pdf->SetXY(30, 62);
        $pdf->Cell(80, 6, 'TO WHOM IT MAY CONCERN:', 0, 1, 'L');

        $paragraphs = $tpl['paragraphs'];
        $y = $pdf->richParagraph(DocumentFields::fill((string) array_shift($paragraphs), $v), 30, 75, 237, 'Times', 12.5, 6.2, 0);

        // Big title
        $pdf->SetFont('Times', 'B', 40);
        $pdf->SetTextColor(70, 100, 180);
        $pdf->SetXY(20, $y + 4);
        $pdf->Cell(257, 18, BarangayPdf::enc(DocumentFields::fill($tpl['title'], $v)), 0, 1, 'C');
        $pdf->SetTextColor(20, 20, 20);

        $y += 30;
        foreach ($paragraphs as $paragraph) {
            $y = $pdf->richParagraph(DocumentFields::fill($paragraph, $v), 30, $y, 237, 'Times', 11, 5.4, 0);
            $y += 2;
        }

        // Prepared / Noted / Approved
        $sy = min(max($y + 8, 146), 150);
        $cols = [
            ['Prepared BY:', 'secretary', DocumentTemplate::setting('doc_barangay_secretary'), 'Brgy. Secretary', 32],
            ['Noted BY:',    'treasurer', DocumentTemplate::setting('doc_barangay_treasurer'), 'Brgy. Treasurer', 113],
            ['Approved BY:', 'captain',   DocumentTemplate::setting('doc_punong_barangay'),   'Brgy. Captain',   194],
        ];
        foreach ($cols as [$label, $signer, $name, $role, $x]) {
            $pdf->SetFont('Times', '', 11);
            $pdf->SetXY($x, $sy);
            $pdf->Cell(72, 5, $label, 0, 1, 'C');
            self::signatory($pdf, $signer, $name, $role, $x, $sy + 17, 72, 'Times', $preview, 'C', $signer === 'captain');
        }

        $pdf->SetFont('Times', 'B', 14);
        $pdf->SetXY(20, 184);
        $pdf->Cell(257, 7, BarangayPdf::enc($v['PERMIT_NUMBER'] !== '' ? $v['PERMIT_NUMBER'] : ($preview ? '(permit no. on approval)' : '')), 0, 1, 'C');

        self::verification($pdf, $request, 240, 179, 13, $preview, true);

        return $pdf;
    }

    // ── Shared pieces ───────────────────────────────────────────────────

    /** Seal at the left, the four government lines centered. */
    private static function header(BarangayPdf $pdf, float $left, float $right, float $top, string $family, bool $mono): void
    {
        if (is_file(self::ASSETS . 'seal.png')) {
            $pdf->Image(self::ASSETS . 'seal.png', $left + 2, $top, 30);
        }
        $lines = [
            ['REPUBLIC OF THE PHILIPPINES', ''],
            [mb_strtoupper(DocumentTemplate::setting('doc_province')), ''],
            [mb_strtoupper(DocumentTemplate::setting('doc_municipality')), ''],
            [mb_strtoupper(DocumentTemplate::setting('doc_barangay_name')), 'B'],
        ];
        $pdf->SetTextColor(20, 20, 20);
        foreach ($lines as $i => [$text, $style]) {
            $pdf->SetFont($family, $style, $mono ? 12 : 12.5);
            $pdf->SetXY($left, $top + 2 + $i * 5.6);
            $pdf->Cell($right - $left, 5.6, BarangayPdf::enc($text), 0, 0, 'C');
        }
    }

    /**
     * Name (bold, underlined) over the role, with the officer's authorized
     * e-signature above the name (the Punong Barangay authorized its use on
     * the barangay's documents, previews included). When the captain's
     * signature is missing a preview says so, since approval needs it.
     */
    private static function signatory(BarangayPdf $pdf, string $signer, string $name, string $role, float $x, float $nameY, float $width, string $family, bool $preview, string $align, bool $markMissing = true): void
    {
        $sig = DocumentTemplate::signature($signer);
        if ($sig !== null) {
            $sig  = self::trimSignature($sig);
            $size = @getimagesizefromstring($sig['data']) ?: [400, 150];
            $ratio = $size[0] / max(1, $size[1]);
            $h    = 18.0;
            $w    = $h * $ratio;
            if ($w > $width * 0.8) {        // very wide signature: keep its shape
                $w = $width * 0.8;
                $h = $w / $ratio;
            }
            $pdf->imageFromBytes($sig['data'], $sig['mime'], $x + ($width - $w) / 2, $nameY - $h + 3, $w, $h);
        } elseif ($preview && $markMissing) {
            $pdf->SetFont('Helvetica', 'B', 8);
            $pdf->SetTextColor(190, 30, 30);
            $pdf->SetXY($x, $nameY - 8);
            $pdf->Cell($width, 4, 'SIGNATURE REQUIRED', 0, 0, 'C');
            $pdf->SetTextColor(20, 20, 20);
        }

        $pdf->SetFont($family, 'B', 12);
        $label = BarangayPdf::enc(mb_strtoupper($name));
        $pdf->SetXY($x, $nameY);
        $pdf->Cell($width, 6, $label, 0, 1, $align);
        $textW = $pdf->GetStringWidth($label);
        $lx    = $x + ($width - $textW) / 2;
        $pdf->SetLineWidth(0.3);
        $pdf->Line($lx, $nameY + 5.4, $lx + $textW, $nameY + 5.4);
        $pdf->SetFont($family, '', 11.5);
        $pdf->SetXY($x, $nameY + 6);
        $pdf->Cell($width, 5.5, BarangayPdf::enc($role), 0, 1, $align);
    }

    /** Control number and QR to the public verification page. */
    private static function verification(BarangayPdf $pdf, array $request, float $x, float $y, float $size, bool $preview, bool $compact = false): void
    {
        $control = (string) ($request['certificate_no'] ?? '');
        if ($control === '' || $preview) {
            $pdf->SetFont('Helvetica', '', 6.5);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->SetXY($x - 6, $y + $size / 2 - 2);
            $pdf->MultiCell($size + 12, 3, 'Verification QR is added on approval', 0, 'C');
            $pdf->SetTextColor(20, 20, 20);
            return;
        }
        self::qr($pdf, app_url('documents/' . rawurlencode($control) . '/verify'), $x, $y, $size);
        $pdf->SetFont('Helvetica', '', $compact ? 5.5 : 6.5);
        $pdf->SetTextColor(70, 70, 70);
        $pdf->SetXY($x - 8, $y + $size + 0.8);
        $pdf->Cell($size + 16, 3, BarangayPdf::enc($control), 0, 1, 'C');
        if (!$compact) {
            $pdf->SetX($x - 8);
            $pdf->Cell($size + 16, 3, 'Scan to verify', 0, 1, 'C');
        }
        $pdf->SetTextColor(20, 20, 20);
    }

    /** Footer: contact lines (italic typewriter) and the two logos. */
    private static function footer(BarangayPdf $pdf, float $left, float $top, float $right): void
    {
        $pdf->SetFont('Courier', 'I', 9.5);
        $pdf->SetTextColor(30, 30, 30);
        foreach ([
            'Address: ' . DocumentTemplate::setting('doc_footer_address'),
            'Mobile No.: ' . DocumentTemplate::setting('doc_footer_mobile'),
            'Email Address: ' . DocumentTemplate::setting('doc_footer_email'),
        ] as $i => $line) {
            $pdf->SetXY($left, $top + $i * 4.6);
            $pdf->Cell(120, 4.6, BarangayPdf::enc($line), 0, 0, 'L');
        }
        if (is_file(self::ASSETS . 'bagong-pilipinas.png')) {
            $pdf->Image(self::ASSETS . 'bagong-pilipinas.png', $right - 50, $top - 4, 18);
        }
        if (is_file(self::ASSETS . 'madrid-ato-palamboon.png')) {
            $pdf->Image(self::ASSETS . 'madrid-ato-palamboon.png', $right - 30, $top - 1, 30);
        }
    }

    /**
     * The signature image without its empty margins, so it prints at full
     * size however much blank canvas the uploaded file had.
     *
     * @param array{data:string, mime:string} $sig
     * @return array{data:string, mime:string}
     */
    private static function trimSignature(array $sig): array
    {
        if (!function_exists('imagecreatefromstring')) {
            return $sig;
        }
        $im = @imagecreatefromstring($sig['data']);
        if ($im === false) {
            return $sig;
        }
        imagesavealpha($im, true);
        $cropped = @imagecropauto($im, IMG_CROP_TRANSPARENT);
        if ($cropped === false || (imagesx($cropped) === imagesx($im) && imagesy($cropped) === imagesy($im))) {
            $cropped = @imagecropauto($im, IMG_CROP_THRESHOLD, 0.3, 0xFFFFFF);   // white background
        }
        if ($cropped === false || imagesx($cropped) < 10 || imagesy($cropped) < 10) {
            $cropped = $im;
        }

        // Paper-white background becomes transparent, so only the ink prints
        // and nothing under the signature (like "Approved BY:") is covered.
        $w   = imagesx($cropped);
        $h   = imagesy($cropped);
        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $clear = imagecolorallocatealpha($out, 255, 255, 255, 127);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorsforindex($cropped, imagecolorat($cropped, $x, $y));
                if ($rgba['alpha'] >= 120 || ($rgba['red'] > 225 && $rgba['green'] > 225 && $rgba['blue'] > 225)) {
                    imagesetpixel($out, $x, $y, $clear);
                } else {
                    imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $rgba['red'], $rgba['green'], $rgba['blue'], $rgba['alpha']));
                }
            }
        }
        $cropped = $out;
        ob_start();
        imagepng($cropped);
        $png = (string) ob_get_clean();

        return $png !== '' ? ['data' => $png, 'mime' => 'image/png'] : $sig;
    }

    /** QR code drawn as vector squares (crisp when printed). */
    private static function qr(BarangayPdf $pdf, string $text, float $x, float $y, float $size): void
    {
        try {
            $matrix = Encoder::encode($text, ErrorCorrectionLevel::M())->getMatrix();
            $n      = $matrix->getWidth();
            $cell   = $size / $n;
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect($x - 1, $y - 1, $size + 2, $size + 2, 'F');
            $pdf->SetFillColor(0, 0, 0);
            for ($row = 0; $row < $n; $row++) {
                for ($col = 0; $col < $n; $col++) {
                    if ($matrix->get($col, $row) === 1) {
                        $pdf->Rect($x + $col * $cell, $y + $row * $cell, $cell, $cell, 'F');
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[CertificateGenerator::qr] ' . $e->getMessage());
        }
    }
}
