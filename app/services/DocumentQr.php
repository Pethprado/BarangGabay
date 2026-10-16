<?php
declare(strict_types=1);

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * The unique QR of one document request.
 *
 * Scanning it opens /d/{token}: the request's details, fee, the GCash QR to
 * pay, the proof upload and, once staff approve the payment, the document.
 *
 * The token is "<request id>-<signature>". The signature (HMAC of the id and
 * reference number) makes tokens impossible to guess or to walk through by
 * changing the number. It is a locator only: the page still requires the
 * owner's own sign-in (or a staff account) before showing anything.
 */
final class DocumentQr
{
    /** @param array<string,mixed> $request A document_requests row (id, reference_no). */
    public static function token(array $request): string
    {
        $id = (int) ($request['id'] ?? 0);

        return $id . '-' . self::sign($id, (string) ($request['reference_no'] ?? ''));
    }

    /** The request id inside a token, or null when it is malformed. The caller re-checks the signature with matches(). */
    public static function idFrom(string $token): ?int
    {
        return preg_match('/^(\d{1,10})-[a-f0-9]{16}$/', $token, $m) === 1 ? (int) $m[1] : null;
    }

    /** @param array<string,mixed> $request */
    public static function matches(string $token, array $request): bool
    {
        return hash_equals(self::token($request), $token);
    }

    /** @param array<string,mixed> $request */
    public static function url(array $request): string
    {
        return app_url('d/' . self::token($request));
    }

    /** The QR as an SVG image of the request's page link. */
    public static function svg(array $request, int $size = 280): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, 2), new SvgImageBackEnd());

        return (new Writer($renderer))->writeString(self::url($request));
    }

    private static function sign(int $id, string $reference): string
    {
        return substr(hash_hmac('sha256', 'document-qr|' . $id . '|' . $reference, self::secret()), 0, 16);
    }

    /** Same server-only secrets SecretBox uses; never sent to the browser. */
    private static function secret(): string
    {
        foreach (['JWT_SECRET', 'DATABASE_URL', 'DB_PASS'] as $name) {
            $value = (string) ($_ENV[$name] ?? getenv($name) ?: '');
            if ($value !== '') {
                return $value;
            }
        }

        return 'baranggabay-document-qr';
    }
}
