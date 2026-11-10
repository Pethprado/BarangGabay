<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\DocumentRequest;
use App\Models\DocumentTemplate;
use App\Models\Setting;
use App\Services\CertificateGeneratorService;
use App\Services\DocumentFields;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';

/**
 * Barangay Bayogo document templates: values come from the profile, the
 * wording is safe to edit, every type renders, and nothing is released
 * without the Punong Barangay's authorized e-signature.
 */
class DocumentTemplateTest extends TestCase
{
    private const RESIDENT = [
        'full_name' => 'Silva Santos Biro', 'first_name' => 'Silva', 'middle_name' => 'Santos', 'last_name' => 'Biro',
        'sex' => 'female', 'civil_status' => 'married', 'zone' => 'Purok 3', 'date_of_birth' => '1980-05-02',
    ];

    public function testFieldsComeFromTheProfileInTheBarangaysStyle(): void
    {
        $v = DocumentFields::for(['purpose' => 'Employment', 'issued_at' => '2026-10-03 09:00:00', 'fee_amount' => 30, 'payment_status' => 'PAID_VERIFIED'], self::RESIDENT);

        $this->assertSame('SILVA S. BIRO', $v['RESIDENT_FULL_NAME']);
        $this->assertSame('MRS.', $v['HONORIFIC']);
        $this->assertSame('her', $v['HIS_HER']);
        $this->assertSame('Purok-3', $v['PUROK']);
        $this->assertSame('3', $v['ISSUE_DAY']);
        $this->assertSame('rd', $v['ISSUE_DAY_SUFFIX']);
        $this->assertSame('October', $v['ISSUE_MONTH']);
        $this->assertSame('Paid', $v['DOCUMENTARY_STAMP']);
    }

    public function testHonorificAndOrdinals(): void
    {
        $this->assertSame('MR.', DocumentFields::honorific(['sex' => 'Male']));
        $this->assertSame('MS.', DocumentFields::honorific(['sex' => 'female', 'civil_status' => 'single']));
        $this->assertSame('', DocumentFields::honorific([]), 'unknown sex: no guessed title');
        foreach ([1 => 'st', 2 => 'nd', 3 => 'rd', 4 => 'th', 11 => 'th', 12 => 'th', 13 => 'th', 21 => 'st', 22 => 'nd', 31 => 'st'] as $day => $suffix) {
            $this->assertSame($suffix, DocumentFields::ordinalSuffix($day), (string) $day);
        }
    }

    public function testFillKeepsSpacingWhenTheHonorificIsEmpty(): void
    {
        $text = 'certify that **{{HONORIFIC}} {{RESIDENT_FULL_NAME}}** a resident';
        $this->assertSame('certify that **JUAN CRUZ** a resident', DocumentFields::fill($text, ['HONORIFIC' => '', 'RESIDENT_FULL_NAME' => 'JUAN CRUZ']));
        $this->assertSame('certify that **MR. JUAN CRUZ** a resident', DocumentFields::fill($text, ['HONORIFIC' => 'MR.', 'RESIDENT_FULL_NAME' => 'JUAN CRUZ']));
    }

    public function testUnknownPlaceholdersAreRefused(): void
    {
        $this->assertSame(['RESIDNT_NAME'], DocumentTemplate::unknownPlaceholders('Hello {{RESIDNT_NAME}} of {{PUROK}}'));
        $this->assertNotNull(DocumentTemplate::save('residency', 'T', '', ['Typo {{RESIDNT_NAME}}'], 0));
    }

    public function testEditedWordingIsUsedAndCanBeReset(): void
    {
        try {
            $this->assertNull(DocumentTemplate::save('indigency', 'CERTIFICATE OF INDIGENCY', 'OFFICE', ['Edited {{RESIDENT_FULL_NAME}}.'], 0));
            $this->assertSame(['Edited {{RESIDENT_FULL_NAME}}.'], DocumentTemplate::get('indigency')['paragraphs']);
            $this->assertTrue(DocumentTemplate::get('indigency')['customized']);
        } finally {
            DocumentTemplate::reset('indigency', 0);
        }
        $this->assertSame(DocumentTemplate::defaults('indigency')['paragraphs'], DocumentTemplate::get('indigency')['paragraphs']);
    }

    public function testDefaultTemplatesUseOnlyKnownPlaceholders(): void
    {
        foreach (DocumentTemplate::TYPES as $type) {
            $t = DocumentTemplate::defaults($type);
            $this->assertSame([], DocumentTemplate::unknownPlaceholders(implode("\n", array_merge([$t['title'], $t['office']], $t['paragraphs']))), $type);
        }
    }

    public function testEveryDocumentTypeRendersAsPdf(): void
    {
        foreach (DocumentTemplate::TYPES as $type) {
            $request = ['document_type' => $type, 'purpose' => 'Employment', 'fee_amount' => 50, 'payment_status' => 'PAID_VERIFIED',
                        'certificate_no' => 'TST-2026-000001', 'issued_at' => '2026-10-08 10:00:00', 'or_number' => '2513826',
                        'business_name' => 'Biro Store', 'business_type' => 'Sari-sari store', 'permit_no' => '2026-014'];
            foreach ([false, true] as $preview) {
                $pdf = CertificateGeneratorService::generatePdf($request, self::RESIDENT, $preview);
                $this->assertStringStartsWith('%PDF', $pdf, $type . ($preview ? ' preview' : ''));
                $this->assertGreaterThan(5000, strlen($pdf));
            }
        }
    }

    /**
     * A downloadable copy needs the uploaded e-signature; a pickup copy is
     * signed by hand at the hall. Pure check — this test must never approve
     * a real request (an earlier version did, against whatever row was last).
     */
    public function testOnlyDigitalCopiesNeedTheUploadedSignature(): void
    {
        $this->assertTrue(DocumentRequest::needsESignature(['delivery_method' => 'digital'], false));
        $this->assertFalse(DocumentRequest::needsESignature(['delivery_method' => 'digital'], true));
        $this->assertFalse(DocumentRequest::needsESignature(['delivery_method' => 'pickup'], false));
        $this->assertFalse(DocumentRequest::needsESignature([], false), 'pickup is the default');
    }

    public function testSignatureMustBeARealImage(): void
    {
        $this->assertNotNull(DocumentTemplate::saveSignature('captain', 'not an image', 'image/png', 0));
        $this->assertNotNull(DocumentTemplate::saveSignature('nobody', 'x', 'image/png', 0));
    }
}
