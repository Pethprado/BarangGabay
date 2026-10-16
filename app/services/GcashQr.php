<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

/**
 * The barangay's GCash / InstaPay QR that residents pay document fees to.
 *
 * The QR is kept in the settings table (base64), so it survives redeploys
 * on Render. The barangay's own QR (Mary Jane P.) ships with the app in
 * BUNDLED: it is installed once as the active QR on every database (also
 * replacing an older upload), and is the fallback whenever none is set.
 * An admin can still replace it later on Admin → Document Fees.
 */
final class GcashQr
{
    public const BUNDLED      = __DIR__ . '/../../public/assets/images/payments/gcash-mary-jane-p.jpg';
    public const BUNDLED_MIME = 'image/jpeg';
    public const BUNDLED_NAME = 'Mary Jane P.';

    /** Bump to install the bundled QR again on every database. */
    private const INSTALL_FLAG = 'gcash_qr_mary_jane_p_v1';

    /**
     * The QR to show, as raw image bytes.
     *
     * @return array{data:string, mime:string}|null
     */
    public static function current(): ?array
    {
        self::installOnce();

        $data = (string) Setting::get('gcash_receive_qr', '');
        $mime = (string) Setting::get('gcash_receive_qr_mime', '');
        $raw  = $data !== '' ? base64_decode($data, true) : false;
        if ($raw !== false && $raw !== '' && in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return ['data' => $raw, 'mime' => $mime];
        }

        $bundled = is_file(self::BUNDLED) ? (string) file_get_contents(self::BUNDLED) : '';

        return $bundled !== '' ? ['data' => $bundled, 'mime' => self::BUNDLED_MIME] : null;
    }

    /** True when there is a QR to show residents. */
    public static function available(): bool
    {
        return self::current() !== null;
    }

    /**
     * Make the bundled QR the active one, once per database. Never throws:
     * a settings hiccup only means the fallback is shown this time.
     */
    public static function installOnce(): void
    {
        try {
            if ((string) Setting::get(self::INSTALL_FLAG, '') === '1' || !is_file(self::BUNDLED)) {
                return;
            }
            $values = [
                'gcash_receive_qr'      => base64_encode((string) file_get_contents(self::BUNDLED)),
                'gcash_receive_qr_mime' => self::BUNDLED_MIME,
                self::INSTALL_FLAG      => '1',
            ];
            if (trim((string) Setting::get('gcash_receive_name', '')) === '') {
                $values['gcash_receive_name'] = self::BUNDLED_NAME;
            }
            Setting::setMany($values, null);
            Setting::flush();
        } catch (\Throwable $e) {
            error_log('[GcashQr::installOnce] ' . $e->getMessage());
        }
    }
}
