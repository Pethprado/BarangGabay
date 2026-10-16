<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * QR Ph (InstaPay / EMVCo merchant-presented QR) with the amount filled in.
 *
 * The barangay's GCash QR carries the receiving account but no amount, so a
 * resident had to type the fee. This builds a copy of that same QR with the
 * request's exact fee in the EMVCo amount field (tag 54) and a fresh CRC: in
 * GCash (QR → Upload QR, or scan) the recipient AND the amount are filled
 * and the resident only taps Pay. The money still goes to the barangay's own
 * account; staff still verify the payment.
 *
 * The plain QR's text is stored in the setting gcash_qr_payload; the bundled
 * Mary Jane P. QR's text is the default. When an admin uploads another QR,
 * the Document Fees page reads its text in the browser and sends it along.
 */
final class QrPh
{
    /** Text inside public/assets/images/payments/gcash-mary-jane-p.jpg (InstaPay P2P, GCash). */
    public const BUNDLED_PAYLOAD = '00020101021127830012com.p2pqrpay0111GXCHPHM2XXX02089996440303152170200000006560417DWQM4TK3JDO69V8HJ5204601653036085802PH5912MA*Y JA*E P.6009Calagdaan610412346304089C';

    /** The barangay QR's text, or null when it is not a valid QR Ph code. */
    public static function payload(): ?string
    {
        $stored = trim((string) Setting::get('gcash_qr_payload', ''));
        if ($stored === 'none') {
            return null;            // an uploaded QR that could not be read: plain QR only
        }
        if ($stored === '') {
            // No text stored: only safe when the active QR IS the bundled
            // image (an older upload of another QR must never get its fee
            // QR pointed at the bundled account).
            $active = GcashQr::current();
            if ($active === null || !is_file(GcashQr::BUNDLED) || $active['data'] !== file_get_contents(GcashQr::BUNDLED)) {
                return null;
            }
        }
        $payload = $stored !== '' ? $stored : self::BUNDLED_PAYLOAD;

        return self::isValid($payload) ? $payload : null;
    }

    /** A well-formed EMVCo QR with a correct CRC and the PHP currency. */
    public static function isValid(string $payload): bool
    {
        if (strlen($payload) < 30 || !str_starts_with($payload, '000201') || !preg_match('/6304[0-9A-F]{4}$/', $payload)) {
            return false;
        }
        if (self::crc(substr($payload, 0, -4)) !== substr($payload, -4)) {
            return false;
        }
        $fields = self::parse($payload);

        return $fields !== null && ($fields['53'] ?? '') === '608';
    }

    /** The same QR with the amount set (and marked one-time / dynamic). */
    public static function withAmount(string $payload, float $amount): ?string
    {
        $fields = self::parse($payload);
        if ($fields === null || $amount <= 0 || $amount > 999999) {
            return null;
        }
        unset($fields['63']);
        $fields['01'] = '12';                                   // point of initiation: dynamic (this payment)
        $fields['54'] = number_format($amount, 2, '.', '');      // transaction amount
        ksort($fields, SORT_STRING);

        $body = '';
        foreach ($fields as $tag => $value) {
            $body .= $tag . sprintf('%02d', strlen($value)) . $value;
        }
        $body .= '6304';

        return $body . self::crc($body);
    }

    /** PNG bytes of a QR (white quiet zone; GCash's "Upload QR" needs an image file, not SVG). */
    public static function png(string $text, int $module = 10): string
    {
        $matrix = Encoder::encode($text, ErrorCorrectionLevel::M(), 'UTF-8')->getMatrix();
        $n      = $matrix->getWidth();
        $quiet  = 4;
        $size   = ($n + 2 * $quiet) * $module;
        $im     = imagecreatetruecolor($size, $size);
        $white  = imagecolorallocate($im, 255, 255, 255);
        $black  = imagecolorallocate($im, 0, 0, 0);
        imagefill($im, 0, 0, $white);
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $px = ($x + $quiet) * $module;
                    $py = ($y + $quiet) * $module;
                    imagefilledrectangle($im, $px, $py, $px + $module - 1, $py + $module - 1, $black);
                }
            }
        }
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    /** CRC-16/CCITT-FALSE as EMVCo QR requires, 4 uppercase hex digits. */
    public static function crc(string $data): string
    {
        $crc = 0xFFFF;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return sprintf('%04X', $crc);
    }

    /** @return array<string,string>|null top-level tag => value */
    private static function parse(string $payload): ?array
    {
        $fields = [];
        $i = 0;
        $len = strlen($payload);
        while ($i < $len) {
            if ($i + 4 > $len) {
                return null;
            }
            $tag  = substr($payload, $i, 2);
            $size = substr($payload, $i + 2, 2);
            if (!ctype_digit($tag) || !ctype_digit($size) || $i + 4 + (int) $size > $len) {
                return null;
            }
            $fields[$tag] = substr($payload, $i + 4, (int) $size);
            $i += 4 + (int) $size;
        }

        return $fields;
    }
}
