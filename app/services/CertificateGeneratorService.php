<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\Setting;
use App\Models\User;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Fpdf\Fpdf;
use PDO;

class CertificateGeneratorService
{
    /**
     * Concurrency-safe sequential reference number generator.
     * Generates numbers like BGC-2026-000001, RES-2026-000001, IND-2026-000001.
     */
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
        $pdo  = db();

        // Concurrency-safe atomic counter
        for ($retry = 0; $retry < 5; $retry++) {
            try {
                $pdo->beginTransaction();

                // Select or insert row
                $stmt = $pdo->prepare('SELECT last_number FROM document_sequences WHERE doc_prefix = ? AND doc_year = ? FOR UPDATE');
                $stmt->execute([$prefix, $year]);
                $current = $stmt->fetchColumn();

                if ($current === false) {
                    $insert = $pdo->prepare('INSERT INTO document_sequences (doc_prefix, doc_year, last_number, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())');
                    $insert->execute([$prefix, $year]);
                    $nextNumber = 1;
                } else {
                    $nextNumber = (int) $current + 1;
                    $update = $pdo->prepare('UPDATE document_sequences SET last_number = ?, updated_at = NOW() WHERE doc_prefix = ? AND doc_year = ?');
                    $update->execute([$nextNumber, $prefix, $year]);
                }

                $pdo->commit();
                return sprintf('%s-%d-%06d', $prefix, $year, $nextNumber);
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                usleep(50000); // 50ms wait before retry
            }
        }

        // Fallback if sequence table fails
        return sprintf('%s-%d-%06d', $prefix, $year, random_int(100000, 999999));
    }

    /**
     * Generate the official, printable certificate PDF binary.
     */
    public static function generatePdf(array $request, array $resident): string
    {
        $docType       = (string) ($request['document_type'] ?? 'clearance');
        $purpose       = (string) ($request['purpose'] ?? 'General Requirements');
        $certNo        = (string) ($request['certificate_no'] ?? self::generateCertificateNumber($docType));
        $referenceNo   = (string) ($request['reference_no'] ?? $certNo);

        $fullName      = User::formatFullName($resident);
        $dob           = $resident['date_of_birth'] ?? null;
        $age           = User::getAge($dob);
        $ageText       = $age !== null ? (string) $age : 'legal age';
        $sex           = ucfirst((string) ($resident['sex'] ?? 'Unspecified'));
        $civilStatus   = ucfirst((string) ($resident['civil_status'] ?? 'Single'));
        $address       = User::formatAddress($resident);
        $householdNo   = (string) ($resident['household_no'] ?? 'N/A');

        $barangayName  = Setting::get('doc_barangay_name', 'Barangay Bayogo');
        $municipality  = Setting::get('doc_municipality', 'Municipality of Madrid');
        $province      = Setting::get('doc_province', 'Province of Surigao del Sur');
        $captainName   = Setting::get('doc_punong_barangay', 'Hon. Nelson P. Prado');
        $secretaryName = Setting::get('doc_barangay_secretary', 'Sec. Maria Elena Santos');

        $issuedDay     = date('jS');
        $issuedMonthYear = date('F Y');
        $dateFormatted = date('F d, Y');

        // Document Titles
        $titles = [
            'clearance' => 'BARANGAY CLEARANCE',
            'residency' => 'CERTIFICATE OF RESIDENCY',
            'indigency' => 'CERTIFICATE OF INDIGENCY',
            'business'  => 'BARANGAY BUSINESS CLEARANCE',
            'other'     => 'BARANGAY CERTIFICATION',
        ];
        $documentTitle = $titles[$docType] ?? 'BARANGAY CERTIFICATION';

        // Initialize A4 Portrait
        $pdf = new Fpdf('P', 'mm', 'A4');
        $pdf->SetMargins(15, 12, 15);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        // ── Outer Decorative Border ──────────────────────────────────────────
        $pdf->SetDrawColor(24, 76, 42); // Barangay Dark Green
        $pdf->SetLineWidth(0.8);
        $pdf->Rect(10, 10, 190, 277);
        $pdf->SetLineWidth(0.2);
        $pdf->Rect(11.5, 11.5, 187, 274);

        // ── Official Header ──────────────────────────────────────────────────
        $logoPath = dirname(__DIR__, 2) . '/public/images/logo-lockup-light.png';
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 16, 14, 26);
        }

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->SetY(14);
        $pdf->Cell(0, 4.5, 'Republic of the Philippines', 0, 1, 'C');
        $pdf->Cell(0, 4.5, (string) $province, 0, 1, 'C');
        $pdf->Cell(0, 4.5, (string) $municipality, 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(18, 70, 36);
        $pdf->Cell(0, 5.5, strtoupper((string) $barangayName), 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(100, 30, 22);
        $pdf->Cell(0, 5, 'OFFICE OF THE PUNONG BARANGAY', 0, 1, 'C');

        // Header Divider Line
        $pdf->SetY(41);
        $pdf->SetDrawColor(18, 70, 36);
        $pdf->SetLineWidth(0.6);
        $pdf->Line(15, 41, 195, 41);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(15, 42, 195, 42);

        // ── Left Sidebar (Officials) & Right Body Layout ─────────────────────
        $leftW = 54;
        $rightX = 72;
        $rightW = 123;

        // Left Sidebar Background
        $pdf->SetFillColor(246, 250, 246);
        $pdf->SetDrawColor(215, 230, 218);
        $pdf->Rect(14, 45, $leftW, 235, 'DF');

        // Left Sidebar: Barangay Officials
        $pdf->SetXY(14, 48);
        $pdf->SetFont('Arial', 'B', 8.5);
        $pdf->SetTextColor(18, 70, 36);
        $pdf->Cell($leftW, 5, 'BARANGAY OFFICIALS', 0, 1, 'C');
        $pdf->SetDrawColor(18, 70, 36);
        $pdf->Line(20, 54, 14 + $leftW - 6, 54);

        $pdf->Ln(2);
        $officials = [
            ['name' => $captainName, 'title' => 'Punong Barangay', 'bold' => true],
            ['name' => 'Hon. Roberto M. Gomez', 'title' => 'Barangay Kagawad', 'bold' => false],
            ['name' => 'Hon. Alicia D. Santos', 'title' => 'Barangay Kagawad', 'bold' => false],
            ['name' => 'Hon. Danilo C. Perez', 'title' => 'Barangay Kagawad', 'bold' => false],
            ['name' => 'Hon. Gloria F. Reyes', 'title' => 'Barangay Kagawad', 'bold' => false],
            ['name' => 'Hon. Ernesto S. Cruz', 'title' => 'Barangay Kagawad', 'bold' => false],
            ['name' => 'Hon. Marites L. Lim', 'title' => 'Barangay Kagawad', 'bold' => false],
            ['name' => 'Hon. Jason K. Tan', 'title' => 'Barangay Kagawad', 'bold' => false],
            ['name' => 'Hon. Joshua P. Ramos', 'title' => 'SK Chairperson', 'bold' => false],
            ['name' => $secretaryName, 'title' => 'Barangay Secretary', 'bold' => true],
            ['name' => 'Trea. Gina V. Dizon', 'title' => 'Barangay Treasurer', 'bold' => false],
        ];

        foreach ($officials as $off) {
            $pdf->SetX(16);
            $pdf->SetFont('Arial', $off['bold'] ? 'B' : '', 7.5);
            $pdf->SetTextColor(20, 20, 20);
            $pdf->Cell($leftW - 4, 3.8, $off['name'], 0, 1, 'C');

            $pdf->SetX(16);
            $pdf->SetFont('Arial', 'I', 6.8);
            $pdf->SetTextColor(90, 90, 90);
            $pdf->Cell($leftW - 4, 3.4, $off['title'], 0, 1, 'C');
            $pdf->Ln(1.2);
        }

        // Left Sidebar: Resident Photo / Specimen Signature Box
        $pdf->SetY(198);
        $pdf->SetX(17);
        $pdf->SetDrawColor(180, 190, 180);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect(18, 200, 46, 32, 'DF');

        // If resident has an ID photo or avatar, attempt to render it
        $photoRendered = false;
        if (!empty($resident['avatar_url'])) {
            $avatarFile = dirname(__DIR__, 2) . '/public/' . ltrim((string) $resident['avatar_url'], '/');
            if (file_exists($avatarFile) && in_array(strtolower(pathinfo($avatarFile, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true)) {
                try {
                    $pdf->Image($avatarFile, 20, 202, 42, 28);
                    $photoRendered = true;
                } catch (\Throwable $e) {}
            }
        }
        if (!$photoRendered) {
            $pdf->SetXY(18, 212);
            $pdf->SetFont('Arial', 'I', 7.5);
            $pdf->SetTextColor(140, 140, 140);
            $pdf->Cell(46, 4, '2x2 Photo / Seal', 0, 1, 'C');
        }

        // Thumbmark / Signature line
        $pdf->SetY(236);
        $pdf->SetX(18);
        $pdf->SetDrawColor(160, 160, 160);
        $pdf->Line(20, 254, 62, 254);
        $pdf->SetY(255);
        $pdf->SetX(18);
        $pdf->SetFont('Arial', '', 6.8);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(46, 3, "Resident's Signature", 0, 1, 'C');

        // ── Main Content Area ────────────────────────────────────────────────
        $pdf->SetXY($rightX, 47);
        $pdf->SetFont('Arial', 'B', 15);
        $pdf->SetTextColor(18, 70, 36);
        $pdf->Cell($rightW, 7, $documentTitle, 0, 1, 'C');

        // Document Number & Date Issued
        $pdf->SetXY($rightX, 55);
        $pdf->SetFont('Arial', 'B', 8.5);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->Cell($rightW / 2, 4, 'Control No: ' . $certNo, 0, 0, 'L');
        $pdf->Cell($rightW / 2, 4, 'Date: ' . $dateFormatted, 0, 1, 'R');

        $pdf->SetXY($rightX, 60);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell($rightW, 4, 'Ref. Track ID: ' . $referenceNo, 0, 1, 'L');

        // Divider
        $pdf->SetDrawColor(200, 215, 200);
        $pdf->Line($rightX, 66, $rightX + $rightW, 66);

        // Salutation
        $pdf->SetXY($rightX, 71);
        $pdf->SetFont('Arial', 'B', 10.5);
        $pdf->SetTextColor(20, 20, 20);
        $pdf->Cell($rightW, 6, 'TO WHOM IT MAY CONCERN:', 0, 1, 'L');

        // Paragraph 1
        $pdf->SetXY($rightX, 80);
        $pdf->SetFont('Arial', '', 9.5);
        $pdf->SetTextColor(30, 30, 30);

        if ($docType === 'clearance') {
            $p1 = "THIS IS TO CERTIFY that " . strtoupper($fullName) . ", {$ageText} years old, {$civilStatus}, Filipino citizen, whose specimen signature appears below, is a bona fide resident of " . $address . ", " . $barangayName . ", " . $municipality . ", " . $province . ".";
            $p2 = "FURTHER CERTIFIES that based on the records and blotter of this office, the aforementioned individual is a person of GOOD MORAL CHARACTER, a peaceful and law-abiding citizen, and has NO DEROGATORY RECORD or criminal case filed against him/her in this Barangay as of this date.";
            $p3 = "This Barangay Clearance is being issued upon the request of the interested party for the purpose of " . strtoupper($purpose) . " and for whatever legal intent it may serve.";
        } elseif ($docType === 'residency') {
            $p1 = "THIS IS TO CERTIFY that " . strtoupper($fullName) . ", {$ageText} years old, {$civilStatus}, Filipino citizen, is a permanent bona fide resident of " . $address . ", " . $barangayName . ", " . $municipality . ", " . $province . ".";
            $p2 = "FURTHER CERTIFIES that the subject person has been continuously residing in this Barangay and is known to be of good community standing with a recognized household registration.";
            $p3 = "This Certificate of Residency is being issued upon the request of the interested party for the purpose of " . strtoupper($purpose) . " and for whatever legal intent it may serve.";
        } elseif ($docType === 'indigency') {
            $p1 = "THIS IS TO CERTIFY that " . strtoupper($fullName) . ", {$ageText} years old, {$civilStatus}, Filipino citizen, is a bona fide resident of " . $address . ", " . $barangayName . ", " . $municipality . ", " . $province . ".";
            $p2 = "FURTHER CERTIFIES that according to the assessment and socio-economic records of this Barangay, the above-named person belongs to an INDIGENT FAMILY whose household income is below poverty threshold and requires public welfare support.";
            $p3 = "This Certificate of Indigency is being issued upon the request of the interested party for " . strtoupper($purpose) . " (e.g. Educational Assistance, Medical Aid, Social Welfare Support, Public Attorney's Office) and for whatever legal purpose it may serve.";
        } else {
            $p1 = "THIS IS TO CERTIFY that " . strtoupper($fullName) . ", {$ageText} years old, {$civilStatus}, Filipino citizen, is a resident of " . $address . ", " . $barangayName . ", " . $municipality . ", " . $province . ".";
            $p2 = "FURTHER CERTIFIES that the above-named resident has complied with the standard documentary and verification requirements of the Barangay.";
            $p3 = "This certification is issued upon the request of the above-named person for the purpose of " . strtoupper($purpose) . " and for whatever lawful intent it may serve.";
        }

        $pdf->MultiCell($rightW, 5.2, $p1, 0, 'J');
        $pdf->Ln(3.5);
        $pdf->SetX($rightX);
        $pdf->MultiCell($rightW, 5.2, $p2, 0, 'J');
        $pdf->Ln(3.5);
        $pdf->SetX($rightX);
        $pdf->MultiCell($rightW, 5.2, $p3, 0, 'J');

        // Issuance statement
        $pdf->Ln(4);
        $pdf->SetX($rightX);
        $pIssue = "ISSUED this {$issuedDay} day of {$issuedMonthYear} at the Office of the Punong Barangay, {$barangayName}, {$municipality}, {$province}, Philippines.";
        $pdf->MultiCell($rightW, 5.2, $pIssue, 0, 'J');

        // ── Resident Particulars Table ───────────────────────────────────────
        $pdf->Ln(4);
        $pdf->SetX($rightX);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(240, 245, 240);
        $pdf->SetDrawColor(210, 225, 210);
        $pdf->Cell($rightW, 4.5, 'VERIFIED RESIDENT RECORD', 1, 1, 'L', true);

        $pdf->SetFont('Arial', '', 7.5);
        $pdf->SetX($rightX);
        $pdf->Cell(25, 4, 'Full Name:', 'L', 0, 'L');
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell(50, 4, $fullName, 0, 0, 'L');
        $pdf->SetFont('Arial', '', 7.5);
        $pdf->Cell(22, 4, 'Civil Status:', 0, 0, 'L');
        $pdf->Cell(26, 4, $civilStatus, 'R', 1, 'L');

        $pdf->SetX($rightX);
        $pdf->Cell(25, 4, 'Date of Birth:', 'L', 0, 'L');
        $pdf->Cell(50, 4, $dob ? date('F d, Y', strtotime((string) $dob)) : 'N/A', 0, 0, 'L');
        $pdf->Cell(22, 4, 'Dynamic Age:', 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell(26, 4, $ageText . ($age !== null ? ' yrs old' : ''), 'R', 1, 'L');

        $pdf->SetFont('Arial', '', 7.5);
        $pdf->SetX($rightX);
        $pdf->Cell(25, 4, 'Household No:', 'LB', 0, 'L');
        $pdf->Cell(50, 4, $householdNo, 'B', 0, 'L');
        $pdf->Cell(22, 4, 'Sex:', 'B', 0, 'L');
        $pdf->Cell(26, 4, $sex, 'RB', 1, 'L');

        // ── Signatory Section ────────────────────────────────────────────────
        $pdf->SetY(206);
        $sigW = ($rightW / 2) - 4;

        // Certified by (Secretary)
        $pdf->SetX($rightX);
        $pdf->SetFont('Arial', '', 7.8);
        $pdf->SetTextColor(70, 70, 70);
        $pdf->Cell($sigW, 4, 'Prepared & Certified by:', 0, 0, 'L');

        // Approved by (Punong Barangay)
        $pdf->SetX($rightX + $sigW + 8);
        $pdf->Cell($sigW, 4, 'Approved & Issued by:', 0, 1, 'L');

        $pdf->SetY(224);
        $pdf->SetX($rightX);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(20, 20, 20);
        $pdf->Cell($sigW, 4.5, $secretaryName, 0, 0, 'L');

        $pdf->SetX($rightX + $sigW + 8);
        $pdf->Cell($sigW, 4.5, $captainName, 0, 1, 'L');

        $pdf->SetX($rightX);
        $pdf->SetFont('Arial', 'I', 7.5);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->Cell($sigW, 4, 'Barangay Secretary', 0, 0, 'L');

        $pdf->SetX($rightX + $sigW + 8);
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->SetTextColor(18, 70, 36);
        $pdf->Cell($sigW, 4, 'Punong Barangay', 0, 1, 'L');

        // ── Native Vector QR Code & Footer Security ──────────────────────────
        $verifyUrl = 'https://baranggabay.onrender.com/documents/' . urlencode($certNo) . '/verify';
        self::drawVectorQr($pdf, $verifyUrl, $rightX, 238, 22);

        $pdf->SetXY($rightX + 25, 239);
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->SetTextColor(18, 70, 36);
        $pdf->Cell($rightW - 25, 4, 'BARANGGABAY DIGITAL VERIFICATION QR', 0, 1, 'L');

        $pdf->SetXY($rightX + 25, 243);
        $pdf->SetFont('Arial', '', 6.8);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->MultiCell($rightW - 25, 3.4, "Scan this QR code using any smartphone camera to verify the genuine authenticity of this official Barangay document on the secure portal.", 0, 'L');

        $pdf->SetXY($rightX + 25, 253);
        $pdf->SetFont('Arial', 'I', 6.8);
        $pdf->SetTextColor(160, 40, 40);
        $pdf->Cell($rightW - 25, 3.5, 'SECURITY NOTICE: NOT VALID WITHOUT OFFICIAL SEAL & SIGNATURE.', 0, 1, 'L');

        // Bottom Footer Bar
        $pdf->SetY(268);
        $pdf->SetX(15);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line(15, 268, 195, 268);

        $pdf->SetY(270);
        $pdf->SetFont('Arial', '', 6.8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 3.5, "Barangay Bayogo Hall, Purok 1, Bayogo, Madrid, Surigao del Sur | Official Portal: baranggabay.onrender.com", 0, 1, 'C');

        return $pdf->Output('S');
    }

    /**
     * Draw native vector QR code directly onto FPDF canvas.
     */
    private static function drawVectorQr(Fpdf $pdf, string $text, float $startX, float $startY, float $size): void
    {
        try {
            $qrCode = Encoder::encode($text, ErrorCorrectionLevel::M());
            $matrix = $qrCode->getMatrix();
            $width  = $matrix->getWidth();
            $height = $matrix->getHeight();

            $cellSize = $size / $width;

            // Background white box
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect($startX - 1, $startY - 1, $size + 2, $size + 2, 'F');

            // Draw QR modules
            $pdf->SetFillColor(18, 70, 36);
            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    if ($matrix->get($x, $y) === 1) {
                        $pdf->Rect($startX + ($x * $cellSize), $startY + ($y * $cellSize), $cellSize, $cellSize, 'F');
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[drawVectorQr] ' . $e->getMessage());
        }
    }
}
