<?php
declare(strict_types=1);

namespace App\Models;

/**
 * The four Barangay Bayogo documents as editable templates.
 *
 * Each template is a title, an office line and paragraphs with
 * {{PLACEHOLDERS}} that DocumentFields fills from the resident's verified
 * profile and the request. The defaults follow the barangay's own paper
 * documents; an admin can change the wording on Admin → Document Templates
 * (stored in settings as JSON, so it survives redeploys) and reset it.
 *
 * Markup inside paragraphs:
 *   **text**   bold          ^^text^^   superscript (the "rd" in 3rd)
 *
 * Officials, contact lines, signatures and numbering are settings too.
 * Signatures are images an authorized officer uploads; nothing is ever
 * copied from a scanned document.
 */
final class DocumentTemplate
{
    public const TYPES = ['indigency', 'clearance', 'residency', 'business'];

    /** Signature images (PNG/JPEG, base64 in settings). Captain's is required to release a document. */
    public const SIGNERS = [
        'captain'   => 'Punong Barangay',
        'secretary' => 'Barangay Secretary',
        'treasurer' => 'Barangay Treasurer',
    ];

    /** Officials and contact lines, with the barangay's current values as defaults. */
    public const SETTINGS = [
        'doc_punong_barangay'    => ['Punong Barangay', 'HON. ESTEBAN T. BANGUIS'],
        'doc_barangay_secretary' => ['Barangay Secretary', 'ANNALIZA S. MALAYAO'],
        'doc_barangay_treasurer' => ['Barangay Treasurer', 'EUNICE ANN M. LIMBOY'],
        'doc_barangay_name'      => ['Barangay', 'Barangay Bayogo'],
        'doc_municipality'       => ['Municipality', 'Municipality of Madrid'],
        'doc_province'           => ['Province', 'Province of Surigao del Sur'],
        'doc_footer_address'     => ['Footer: address', 'Purok 2 Bayogo, Madrid, Surigao del Sur'],
        'doc_footer_mobile'      => ['Footer: mobile no.', '09708612215'],
        'doc_footer_email'       => ['Footer: email', 'brgybayogo@gmail.com'],
        'doc_or_start'           => ['First O.R. number', '2513827'],
        'doc_permit_start'       => ['First business permit number this year', '1'],
    ];

    /** Every placeholder a template may use, with what it becomes. */
    public const PLACEHOLDERS = [
        'RESIDENT_FULL_NAME'  => 'Resident\'s name in capitals, e.g. SARAH S. ELCANTO',
        'HONORIFIC'           => 'MR. / MS. / MRS. from sex and civil status',
        'HIS_HER'             => 'his / her',
        'HE_SHE'              => 'he / she',
        'HE_SHE_CAPITAL'      => 'He / She (start of a sentence)',
        'CIVIL_STATUS'        => 'Single, Married, …',
        'AGE'                 => 'Age in years, or "legal age"',
        'PUROK'               => 'e.g. Purok-3',
        'COMPLETE_ADDRESS'    => 'Full address from the profile',
        'PURPOSE'             => 'Purpose of the request',
        'ISSUE_DAY'           => 'e.g. 8',
        'ISSUE_DAY_SUFFIX'    => 'st / nd / rd / th',
        'ISSUE_MONTH'         => 'e.g. October',
        'ISSUE_YEAR'          => 'e.g. 2026',
        'ISSUE_DATE_LONG'     => 'e.g. October 8, 2026',
        'OR_NUMBER'           => 'Official receipt number',
        'DOCUMENTARY_STAMP'   => 'Paid / Not required',
        'REFERENCE_NO'        => 'Document control number, e.g. RES-2026-000012',
        'BUSINESS_NAME'       => 'Business name (Business Permit)',
        'BUSINESS_TYPE'       => 'Kind of business, e.g. SARI-SARI STORE',
        'PERMIT_NUMBER'       => 'e.g. 2026-014',
        'VALID_FROM'          => 'e.g. September 22, 2026',
        'VALID_UNTIL'         => 'e.g. September 22, 2027',
        'BARANGAY'            => 'Barangay Bayogo',
        'MUNICIPALITY'        => 'Madrid',
        'PROVINCE'            => 'Surigao del Sur',
    ];

    /**
     * The barangay's own wording, from its paper documents.
     *
     * @return array{title:string, office:string, paragraphs:list<string>}
     */
    public static function defaults(string $type): array
    {
        return match ($type) {
            'indigency' => [
                'title'  => 'CERTIFICATE OF INDIGENCY',
                'office' => 'OFFICE OF THE BARANGAY COUNCIL',
                'paragraphs' => [
                    'This is to certify that **{{HONORIFIC}} {{RESIDENT_FULL_NAME}}** a resident of {{PUROK}} {{BARANGAY}}, {{MUNICIPALITY}}, {{PROVINCE}}, is indigent.',
                    'This is to certify further that the above mentioned name has no permanent source of income and belongs to the poverty line.',
                    'This certification is being issued upon the request of **{{HONORIFIC}} {{RESIDENT_FULL_NAME}}** for {{PURPOSE}} and for whatever legal purpose it may serve {{HIS_HER}} best.',
                    'Issued this {{ISSUE_DAY}}^^{{ISSUE_DAY_SUFFIX}}^^ day of {{ISSUE_MONTH}}, {{ISSUE_YEAR}} at {{BARANGAY}}, {{MUNICIPALITY}}, {{PROVINCE}}, Philippines.',
                ],
            ],
            'clearance' => [
                'title'  => 'BARANGAY CLEARANCE',
                'office' => 'OFFICE OF THE BARANGAY COUNCIL',
                'paragraphs' => [
                    'THIS IS TO CERTIFY that **{{HONORIFIC}} {{RESIDENT_FULL_NAME}}** Filipino of legal age, {{CIVIL_STATUS}} with postal address at {{PUROK}} {{BARANGAY}}, {{MUNICIPALITY}}, {{PROVINCE}}. {{HE_SHE_CAPITAL}} is a law abiding citizen of the community and has a good moral character.',
                    'This certifies further that {{HE_SHE}} has no derogatory records filed against in this office as to date.',
                    'This certification is being issued upon the request of the above interested person for {{PURPOSE}} and for whatever legal purpose that may serve {{HIS_HER}} best.',
                    'Issued this {{ISSUE_DAY}}^^{{ISSUE_DAY_SUFFIX}}^^ day of {{ISSUE_MONTH}}, {{ISSUE_YEAR}} at {{BARANGAY}}, {{MUNICIPALITY}}, {{PROVINCE}}.',
                ],
            ],
            'residency' => [
                'title'  => 'CERTIFICATE OF RESIDENCY',
                'office' => 'OFFICE OF THE PUNONG BARANGAY',
                'paragraphs' => [
                    'This is to certify that **{{HONORIFIC}} {{RESIDENT_FULL_NAME}}** of legal age, Filipino citizen is a bonafide resident of {{PUROK}} {{BARANGAY}}, {{MUNICIPALITY}}, {{PROVINCE}}.',
                    'CERTIFYING FURTHER, that the above-named person is a person of good moral character and has no derogatory and/or criminal records in the barangay.',
                    'This certification is issued upon the request of **{{HONORIFIC}} {{RESIDENT_FULL_NAME}}** for {{PURPOSE}} and for whatever legal purpose it may serve {{HIS_HER}} best.',
                    'Issued this {{ISSUE_DAY}}^^{{ISSUE_DAY_SUFFIX}}^^ day of {{ISSUE_MONTH}}, {{ISSUE_YEAR}} at {{BARANGAY}}, {{MUNICIPALITY}}, {{PROVINCE}}, Philippines.',
                ],
            ],
            'business' => [
                'title'  => 'BUSINESS PERMIT',
                'office' => 'OFFICE OF THE PUNONG BARANGAY',
                'paragraphs' => [
                    'THIS IS TO CERTIFY that **{{RESIDENT_FULL_NAME}}** having complied with existing regulations and paid the corresponding fees as required by law and ordinance is hereby granted.',
                    'Authorizing engaged **{{BUSINESS_TYPE}}** ({{BUSINESS_NAME}}) within the jurisdiction of {{BARANGAY}}, {{MUNICIPALITY}}, {{PROVINCE}} for the year indicated as attested by the Barangay Treasurer or her duly authorized unless sooner revoked or cancelled for cause which would hereby subject the fees paid therefore to be forfeited, {{VALID_FROM}} **valid until {{VALID_UNTIL}}.**',
                ],
            ],
            default => throw new \InvalidArgumentException('Unknown document template: ' . $type),
        };
    }

    /** The template in use: the admin's saved wording, else the default. */
    public static function get(string $type): array
    {
        $default = self::defaults($type);
        $saved   = json_decode((string) Setting::get('doc_template_' . $type, ''), true);
        if (!is_array($saved)) {
            return $default + ['customized' => false];
        }

        $paragraphs = array_values(array_filter(array_map('strval', (array) ($saved['paragraphs'] ?? [])), static fn (string $p): bool => trim($p) !== ''));

        return [
            'title'      => trim((string) ($saved['title'] ?? '')) ?: $default['title'],
            'office'     => trim((string) ($saved['office'] ?? '')) ?: $default['office'],
            'paragraphs' => $paragraphs ?: $default['paragraphs'],
            'customized' => true,
        ];
    }

    /**
     * Save an admin's wording. Unknown placeholders are refused so a typo can
     * never print "{{RESIDNT_NAME}}" on an official document.
     *
     * @param list<string> $paragraphs
     * @return string|null Error message, or null when saved.
     */
    public static function save(string $type, string $title, string $office, array $paragraphs, int $by): ?string
    {
        self::defaults($type);   // throws on an unknown type
        $paragraphs = array_values(array_filter(array_map('trim', $paragraphs), static fn (string $p): bool => $p !== ''));
        if (trim($title) === '' || $paragraphs === []) {
            return 'A template needs a title and at least one paragraph.';
        }
        $unknown = self::unknownPlaceholders(implode("\n", array_merge([$title, $office], $paragraphs)));
        if ($unknown !== []) {
            return 'Unknown placeholder(s): {{' . implode('}}, {{', $unknown) . '}}. Use only the ones listed on this page.';
        }

        Setting::set('doc_template_' . $type, json_encode([
            'title' => trim($title), 'office' => trim($office), 'paragraphs' => $paragraphs,
        ], JSON_UNESCAPED_UNICODE), $by > 0 ? $by : null);
        Setting::flush();

        return null;
    }

    public static function reset(string $type, int $by): void
    {
        self::defaults($type);
        Setting::set('doc_template_' . $type, '', $by > 0 ? $by : null);
        Setting::flush();
    }

    /** @return list<string> */
    public static function unknownPlaceholders(string $text): array
    {
        preg_match_all('/\{\{\s*([A-Z0-9_]+)\s*\}\}/', $text, $m);

        return array_values(array_unique(array_diff($m[1], array_keys(self::PLACEHOLDERS))));
    }

    /** An official's name or a contact line. */
    public static function setting(string $key): string
    {
        $value = trim((string) Setting::get($key, ''));

        return $value !== '' ? $value : (self::SETTINGS[$key][1] ?? '');
    }

    // ── Signatures ──────────────────────────────────────────────────────

    /** @return array{data:string, mime:string}|null Raw image bytes of an uploaded signature. */
    public static function signature(string $signer): ?array
    {
        if (!isset(self::SIGNERS[$signer])) {
            return null;
        }
        $data = (string) Setting::get('doc_sig_' . $signer, '');
        $mime = (string) Setting::get('doc_sig_' . $signer . '_mime', '');
        $raw  = $data !== '' ? base64_decode($data, true) : false;

        return $raw !== false && $raw !== '' && in_array($mime, ['image/png', 'image/jpeg'], true)
            ? ['data' => $raw, 'mime' => $mime] : null;
    }

    public static function hasSignature(string $signer): bool
    {
        return self::signature($signer) !== null;
    }

    /**
     * Store an authorized e-signature image (PNG with a transparent
     * background is best). Only admins reach this; every change is logged.
     */
    public static function saveSignature(string $signer, string $bytes, string $mime, int $by): ?string
    {
        if (!isset(self::SIGNERS[$signer])) {
            return 'Unknown signer.';
        }
        if (!in_array($mime, ['image/png', 'image/jpeg'], true)) {
            return 'The signature must be a PNG or JPG image.';
        }
        if (strlen($bytes) > 1024 * 1024 || @getimagesizefromstring($bytes) === false) {
            return 'The signature image is not valid or is larger than 1 MB.';
        }
        Setting::setMany([
            'doc_sig_' . $signer           => base64_encode($bytes),
            'doc_sig_' . $signer . '_mime' => $mime,
            'doc_sig_' . $signer . '_at'   => date('Y-m-d H:i:s'),
        ], $by > 0 ? $by : null);
        Setting::flush();

        return null;
    }

    public static function removeSignature(string $signer, int $by): void
    {
        if (!isset(self::SIGNERS[$signer])) {
            return;
        }
        Setting::setMany(['doc_sig_' . $signer => '', 'doc_sig_' . $signer . '_mime' => '', 'doc_sig_' . $signer . '_at' => ''], $by > 0 ? $by : null);
        Setting::flush();
    }
}
