<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Encrypts small server-side secrets (payment gateway keys) for storage in the
 * settings table, so they can be managed from the superadmin page without
 * redeploying.
 *
 * AES-256-GCM. The key is derived from a secret that exists only in the server
 * environment (JWT_SECRET, else DATABASE_URL / DB_PASS) — never from anything
 * stored in the database — so a database dump alone does not reveal the keys.
 * If that environment secret changes, stored values stop decrypting and must
 * be entered again (decrypt() then returns '').
 */
final class SecretBox
{
    private const PREFIX = 'sbx1:';

    private static function key(): ?string
    {
        foreach (['JWT_SECRET', 'DATABASE_URL', 'DB_PASS'] as $name) {
            $value = $_ENV[$name] ?? getenv($name);
            if (is_string($value) && trim($value) !== '') {
                return hash('sha256', 'barangabay-secretbox|' . trim($value), true);
            }
        }
        return null;
    }

    public static function available(): bool
    {
        return self::key() !== null && function_exists('openssl_encrypt');
    }

    public static function encrypt(string $plain): string
    {
        $key = self::key();
        if ($key === null || $plain === '') {
            return '';
        }
        $iv     = random_bytes(12);
        $tag    = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $cipher === false ? '' : self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $stored): string
    {
        $key = self::key();
        if ($key === null || !str_starts_with($stored, self::PREFIX)) {
            return '';
        }
        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? '' : $plain;
    }
}
