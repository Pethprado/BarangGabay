<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Key/value system settings.
 *
 * Loaded once per request and cached in memory, so the dozens of setting()
 * calls a page makes cost a single query.
 *
 * Reads never throw. This is deliberate: settings are consulted during
 * bootstrap (timezone) and in layouts, so an unmigrated or unreachable
 * database must degrade to the caller's default rather than white-screen the
 * whole site.
 */
class Setting
{
    /** @var array<string,mixed>|null Cached settings for this request. */
    private static ?array $cache = null;

    /** Fallbacks used when the table is missing or a key was never seeded. */
    private const DEFAULTS = [
        'system_name'         => 'BarangGabay',
        'system_tagline'      => 'Connecting Residents. Simplifying Governance.',
        'system_logo'         => '',
        'location_name'       => 'Barangay Bayogo',
        'location_full'       => 'Barangay Bayogo, Madrid, Surigao del Sur',
        'timezone'            => 'Asia/Manila',
        'date_format'         => 'M j, Y',
        'time_format'         => 'g:i A',
        'maintenance_mode'    => false,
        'maintenance_message' => 'The system is temporarily unavailable for maintenance. Please try again shortly.',
        'login_max_attempts'  => 5,
        'login_lockout_min'   => 15,
    ];

    /** One setting, cast to its declared type. */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        return $default ?? self::DEFAULTS[$key] ?? null;
    }

    /**
     * Every setting, keyed by name and cast to its declared type.
     *
     * @return array<string,mixed>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $values = self::DEFAULTS;

        try {
            $rows = db()->query('SELECT setting_key, setting_value, value_type FROM settings')
                        ->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $values[$row['setting_key']] = self::cast($row['setting_value'], $row['value_type']);
            }
        } catch (\Throwable $e) {
            // Table not migrated, or DB unreachable — defaults stand.
        }

        return self::$cache = $values;
    }

    /**
     * Write one setting. Creates the row if the key is new.
     *
     * @throws \PDOException if the settings table is missing — callers in the
     *                       admin UI should surface that, unlike reads.
     */
    public static function set(string $key, mixed $value, ?int $updatedBy = null): void
    {
        $type = self::typeOf($key, $value);

        $stored = match ($type) {
            'bool' => $value ? '1' : '0',
            'int'  => (string) (int) $value,
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE) ?: '',
            default => (string) $value,
        };

        db()->prepare(
            'INSERT INTO settings (setting_key, setting_value, value_type, updated_by, updated_at)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                value_type    = VALUES(value_type),
                updated_by    = VALUES(updated_by),
                updated_at    = NOW()'
        )->execute([$key, $stored, $type, $updatedBy]);

        self::$cache = null;   // force a reload on the next read
    }

    /**
     * Write several settings at once.
     *
     * @param array<string,mixed> $values
     */
    public static function setMany(array $values, ?int $updatedBy = null): void
    {
        foreach ($values as $key => $value) {
            self::set($key, $value, $updatedBy);
        }
    }

    /** Drop the in-memory cache — used after a bulk write or in tests. */
    public static function flush(): void
    {
        self::$cache = null;
    }

    /** When any setting was last changed, or null. */
    public static function lastUpdatedAt(): ?string
    {
        try {
            $value = db()->query('SELECT MAX(updated_at) FROM settings')->fetchColumn();
            return $value === false || $value === null ? null : (string) $value;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ── Private helpers ──────────────────────────────────────────────

    /** Cast a stored string back to its declared PHP type. */
    private static function cast(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'bool' => $value === '1' || strtolower($value) === 'true',
            'int'  => (int) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /** Pick the storage type for a key, preferring the declared default. */
    private static function typeOf(string $key, mixed $value): string
    {
        if (array_key_exists($key, self::DEFAULTS)) {
            $default = self::DEFAULTS[$key];
            if (is_bool($default)) { return 'bool'; }
            if (is_int($default))  { return 'int'; }
            if (is_array($default)) { return 'json'; }
            return 'string';
        }

        if (is_bool($value))  { return 'bool'; }
        if (is_int($value))   { return 'int'; }
        if (is_array($value)) { return 'json'; }

        return 'string';
    }
}
