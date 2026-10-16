<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\DocumentTemplate;
use App\Models\User;

/**
 * The values that fill a document template's {{PLACEHOLDERS}}.
 *
 * Everything comes from records the barangay already holds: the resident's
 * verified profile, the request they submitted, and what approval assigned
 * (control number, O.R. number, permit number, issue date). Staff never
 * retype any of it.
 */
final class DocumentFields
{
    /**
     * @param array<string,mixed> $request  document_requests row
     * @param array<string,mixed> $resident users row
     * @return array<string,string>
     */
    public static function for(array $request, array $resident, ?string $issuedAt = null): array
    {
        $issued = strtotime($issuedAt ?? (string) ($request['issued_at'] ?? '')) ?: time();
        $day    = (int) date('j', $issued);
        $female = self::sex($resident) === 'female';
        $male   = self::sex($resident) === 'male';
        $age    = User::getAge($resident['date_of_birth'] ?? null);
        $fee    = (float) ($request['fee_amount'] ?? 0);
        $validFrom  = strtotime((string) ($request['issued_at'] ?? '')) ?: $issued;
        $validUntil = !empty($request['valid_until']) ? strtotime((string) $request['valid_until']) : strtotime('+1 year', $validFrom);

        $values = [
            'RESIDENT_FULL_NAME' => mb_strtoupper(self::name($resident)),
            'HONORIFIC'          => self::honorific($resident),
            'HIS_HER'            => $female ? 'her' : ($male ? 'his' : 'his/her'),
            'HE_SHE'             => $female ? 'she' : ($male ? 'he' : 'he/she'),
            'HE_SHE_CAPITAL'     => $female ? 'She' : ($male ? 'He' : 'He/She'),
            'CIVIL_STATUS'       => ucfirst(strtolower(trim((string) ($resident['civil_status'] ?? '')))) ?: 'Filipino',
            'AGE'                => $age !== null ? (string) $age : 'legal age',
            'PUROK'              => self::purok($resident),
            'COMPLETE_ADDRESS'   => User::formatAddress($resident),
            'PURPOSE'            => trim((string) ($request['purpose'] ?? '')) ?: 'whatever legal purpose',
            'ISSUE_DAY'          => (string) $day,
            'ISSUE_DAY_SUFFIX'   => self::ordinalSuffix($day),
            'ISSUE_MONTH'        => date('F', $issued),
            'ISSUE_YEAR'         => date('Y', $issued),
            'ISSUE_DATE_LONG'    => date('F j, Y', $issued),
            'OR_NUMBER'          => (string) ($request['or_number'] ?? ''),
            'DOCUMENTARY_STAMP'  => $fee > 0 ? (DocumentRequest::isPaymentVerified($request) ? 'Paid' : 'Unpaid') : 'Not required',
            'REFERENCE_NO'       => (string) ($request['certificate_no'] ?? $request['reference_no'] ?? ''),
            'BUSINESS_NAME'      => trim((string) ($request['business_name'] ?? '')),
            'BUSINESS_TYPE'      => mb_strtoupper(trim((string) ($request['business_type'] ?? ''))),
            'PERMIT_NUMBER'      => (string) ($request['permit_no'] ?? ''),
            'VALID_FROM'         => date('F j, Y', $validFrom),
            'VALID_UNTIL'        => date('F j, Y', $validUntil ?: strtotime('+1 year', $validFrom)),
            'BARANGAY'           => DocumentTemplate::setting('doc_barangay_name'),
            'MUNICIPALITY'       => preg_replace('/^Municipality of\s+/i', '', DocumentTemplate::setting('doc_municipality')) ?? '',
            'PROVINCE'           => preg_replace('/^Province of\s+/i', '', DocumentTemplate::setting('doc_province')) ?? '',
        ];

        return $values;
    }

    /** Replace {{PLACEHOLDERS}}; tidy the gaps an empty one leaves ("MS.  NAME", "( )"). */
    public static function fill(string $text, array $values): string
    {
        // An empty honorific takes its following space with it ("MS. NAME" or "NAME").
        $honorific = trim((string) ($values['HONORIFIC'] ?? ''));
        $out = preg_replace('/\{\{\s*HONORIFIC\s*\}\}\s*/', $honorific !== '' ? $honorific . ' ' : '', $text) ?? $text;
        $out = preg_replace_callback('/\{\{\s*([A-Z0-9_]+)\s*\}\}/', static fn (array $m): string => $values[$m[1]] ?? '', $out) ?? $out;
        $out = preg_replace('/\(\s*\)\s*/', '', $out) ?? $out;

        return trim(preg_replace('/[ ]{2,}/', ' ', $out) ?? $out);
    }

    /** "Sarah Santos Elcanto" → "Sarah S. Elcanto", as on the barangay's documents. */
    public static function name(array $resident): string
    {
        $first = trim((string) ($resident['first_name'] ?? ''));
        $last  = trim((string) ($resident['last_name'] ?? ''));
        if ($first !== '' || $last !== '') {
            $middle = trim((string) ($resident['middle_name'] ?? ''));
            $suffix = trim((string) ($resident['suffix'] ?? ''));

            return trim($first . ($middle !== '' ? ' ' . mb_substr($middle, 0, 1) . '.' : '') . ' ' . $last . ($suffix !== '' ? ' ' . $suffix : ''));
        }

        return trim((string) ($resident['full_name'] ?? ''));
    }

    public static function honorific(array $resident): string
    {
        $status = strtolower(trim((string) ($resident['civil_status'] ?? '')));

        return match (self::sex($resident)) {
            'male'   => 'MR.',
            'female' => in_array($status, ['married', 'widowed', 'widow', 'separated'], true) ? 'MRS.' : 'MS.',
            default  => '',
        };
    }

    /** "Purok 3" / "3" → "Purok-3", the barangay's spelling. */
    public static function purok(array $resident): string
    {
        $zone = trim((string) ($resident['zone'] ?? $resident['purok'] ?? ''));
        if ($zone === '') {
            return '';
        }
        if (preg_match('/^(?:purok)?\s*-?\s*(\d+[A-Za-z]?)$/i', $zone, $m) === 1) {
            return 'Purok-' . $m[1];
        }

        return $zone;
    }

    public static function ordinalSuffix(int $day): string
    {
        if (in_array($day % 100, [11, 12, 13], true)) {
            return 'th';
        }

        return match ($day % 10) {
            1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th',
        };
    }

    private static function sex(array $resident): string
    {
        $sex = strtolower(trim((string) ($resident['sex'] ?? '')));

        return match (true) {
            in_array($sex, ['male', 'm', 'lalaki'], true)   => 'male',
            in_array($sex, ['female', 'f', 'babae'], true)  => 'female',
            default                                          => '',
        };
    }
}
