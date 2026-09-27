<?php
declare(strict_types=1);

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PDO;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;

/**
 * TOTP two-factor authentication.
 *
 * Laravel Fortify was the obvious choice but it is framework-bound; this
 * wraps pragmarx/google2fa directly, which is the same TOTP implementation
 * Fortify uses underneath.
 *
 * Two storage decisions matter here:
 *   - The TOTP secret is encrypted at rest (AES-256-GCM, key derived from
 *     JWT_SECRET). A raw secret generates valid codes forever, so a database
 *     dump on its own must not defeat 2FA.
 *   - Backup codes are bcrypt-hashed like passwords, and shown in plaintext
 *     exactly once at generation.
 */
final class TwoFactorService
{
    /** How many backup codes are issued at a time. */
    public const BACKUP_CODE_COUNT = 8;

    /** Codes are accepted within +/- this many 30-second windows, for clock drift. */
    private const WINDOW = 2;

    private Google2FA $engine;

    public function __construct()
    {
        $this->engine = new Google2FA();
    }

    // ── Enrolment ────────────────────────────────────────────────────

    /** A fresh base32 TOTP secret. */
    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey(32);
    }

    /**
     * The otpauth:// URI an authenticator app scans.
     *
     * The issuer is the configured system name, so the entry is recognisable
     * even after the app is re-branded for another barangay.
     */
    public function provisioningUri(string $accountEmail, string $secret): string
    {
        return $this->engine->getQRCodeUrl(
            system_name(),
            $accountEmail,
            $secret
        );
    }

    /** Inline SVG QR code for the provisioning URI (no external image service). */
    public function qrCodeSvg(string $provisioningUri, int $size = 220): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($provisioningUri);
    }

    // ── Verification ─────────────────────────────────────────────────

    /**
     * Check a 6-digit TOTP code against a secret.
     *
     * Non-numeric or wrong-length input is rejected before reaching the
     * library, which throws on malformed input.
     */
    public function verifyCode(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== 6 || $secret === '') {
            return false;
        }

        try {
            return (bool) $this->engine->verifyKey($secret, $code, self::WINDOW);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Verify a code for a user, reading and decrypting their stored secret. */
    public function verifyForUser(int $userId, string $code): bool
    {
        $secret = $this->secretFor($userId);

        return $secret !== null && $this->verifyCode($secret, $code);
    }

    // ── Persistence ──────────────────────────────────────────────────

    /** Store an (unconfirmed) secret for a user. 2FA is not active until confirmed. */
    public function storeSecret(int $userId, string $secret): void
    {
        db()->prepare(
            'UPDATE users SET totp_secret = ?, totp_enabled = 0, totp_confirmed_at = NULL WHERE id = ?'
        )->execute([$this->encrypt($secret), $userId]);
    }

    /** Decrypted secret for a user, or null if none is stored. */
    public function secretFor(int $userId): ?string
    {
        $stmt = db()->prepare('SELECT totp_secret FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $stored = $stmt->fetchColumn();

        if ($stored === false || $stored === null || $stored === '') {
            return null;
        }

        return $this->decrypt((string) $stored);
    }

    /** Turn 2FA on after the user has proved they can generate a valid code. */
    public function enable(int $userId): void
    {
        db()->prepare(
            'UPDATE users SET totp_enabled = 1, totp_confirmed_at = NOW() WHERE id = ?'
        )->execute([$userId]);
    }

    /** Turn 2FA off and destroy the secret and every backup code. */
    public function disable(int $userId): void
    {
        db()->prepare(
            'UPDATE users SET totp_secret = NULL, totp_enabled = 0, totp_confirmed_at = NULL WHERE id = ?'
        )->execute([$userId]);

        db()->prepare('DELETE FROM two_factor_backup_codes WHERE user_id = ?')->execute([$userId]);
    }

    /** Whether 2FA is active for a user. */
    public function isEnabled(int $userId): bool
    {
        $stmt = db()->prepare('SELECT totp_enabled FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);

        return (bool) $stmt->fetchColumn();
    }

    // ── Backup codes ─────────────────────────────────────────────────

    /**
     * Replace a user's backup codes and return the new plaintext set.
     *
     * This is the only moment the plaintext exists — only hashes are stored,
     * so a lost code cannot be recovered, only regenerated.
     *
     * @return list<string>
     */
    public function regenerateBackupCodes(int $userId): array
    {
        db()->prepare('DELETE FROM two_factor_backup_codes WHERE user_id = ?')->execute([$userId]);

        $codes = [];
        $stmt  = db()->prepare(
            'INSERT INTO two_factor_backup_codes (user_id, code_hash, created_at) VALUES (?, ?, NOW())'
        );

        for ($i = 0; $i < self::BACKUP_CODE_COUNT; $i++) {
            // Unambiguous alphabet: no 0/O or 1/I, since these get written down.
            $code    = $this->randomCode();
            $codes[] = $code;

            // Hash the NORMALISED form so a user may type the code with or
            // without its dash, in any case, and still match.
            $stmt->execute([$userId, password_hash($this->normaliseCode($code), PASSWORD_BCRYPT)]);
        }

        return $codes;
    }

    /**
     * Spend one backup code. Returns true if it matched an unused code, which
     * is then marked used immediately so it cannot be replayed.
     */
    public function consumeBackupCode(int $userId, string $code): bool
    {
        $code = $this->normaliseCode($code);
        if ($code === '') {
            return false;
        }

        $stmt = db()->prepare(
            'SELECT id, code_hash FROM two_factor_backup_codes
             WHERE user_id = ? AND used_at IS NULL'
        );
        $stmt->execute([$userId]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (password_verify($code, $row['code_hash'])) {
                db()->prepare('UPDATE two_factor_backup_codes SET used_at = NOW() WHERE id = ?')
                    ->execute([$row['id']]);
                return true;
            }
        }

        return false;
    }

    /** How many backup codes remain unused. */
    public function remainingBackupCodes(int $userId): int
    {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM two_factor_backup_codes WHERE user_id = ? AND used_at IS NULL'
        );
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    // ── Policy ───────────────────────────────────────────────────────

    /** Roles that must have 2FA, from Settings. */
    public static function requiredRoles(): array
    {
        $raw = (string) setting('twofa_required_roles', 'superadmin');

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /** Whether 2FA is switched on system-wide. */
    public static function featureEnabled(): bool
    {
        return (bool) setting('twofa_enabled', true);
    }

    /** Whether this role is obliged to set 2FA up. */
    public static function isRequiredFor(string $role): bool
    {
        return self::featureEnabled() && in_array($role, self::requiredRoles(), true);
    }

    // ── Private helpers ──────────────────────────────────────────────

    /**
     * Canonical form of a backup code: uppercase, no spaces or dashes.
     *
     * Both hashing and verification go through here — if they ever disagree,
     * no backup code can match, so this must stay the single definition.
     */
    private function normaliseCode(string $code): string
    {
        return strtoupper(trim(str_replace([' ', '-'], '', $code)));
    }

    /** A readable backup code such as "K7F2-QW9M". */
    private function randomCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // no O/0, no I/1
        $out      = '';

        for ($i = 0; $i < 8; $i++) {
            if ($i === 4) {
                $out .= '-';
            }
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }

    /** 32-byte key derived from the app secret. */
    private function key(): string
    {
        $secret = (string) (env('JWT_SECRET') ?: env('APP_KEY') ?: 'baranggabay-jwt-secret-fallback-key-2026-safe');

        return hash('sha256', 'totp:' . $secret, true);
    }

    /** AES-256-GCM encrypt, returning base64(iv|tag|ciphertext). */
    private function encrypt(string $plaintext): string
    {
        $iv  = random_bytes(12);
        $tag = '';

        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Could not encrypt the two-factor secret.');
        }

        return base64_encode($iv . $tag . $cipher);
    }

    /** Reverse of encrypt(). Returns null if the payload is unusable. */
    private function decrypt(string $payload): ?string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }

        $iv     = substr($raw, 0, 12);
        $tag    = substr($raw, 12, 16);
        $cipher = substr($raw, 28);

        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag);

        return $plain === false ? null : $plain;
    }
}
