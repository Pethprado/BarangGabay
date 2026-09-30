<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;

class VoiceProfile
{
    /**
     * Get all voice profiles for a language or all languages
     */
    public static function getAll(?string $language = null): array
    {
        try {
            if ($language) {
                $stmt = db()->prepare("SELECT * FROM voice_profiles WHERE language = ? ORDER BY is_active DESC, profile_name ASC");
                $stmt->execute([$language]);
            } else {
                $stmt = db()->query("SELECT * FROM voice_profiles ORDER BY language ASC, is_active DESC, profile_name ASC");
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('[VoiceProfile::getAll] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get profile by ID
     */
    public static function findById(int $id): ?array
    {
        try {
            $stmt = db()->prepare("SELECT * FROM voice_profiles WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('[VoiceProfile::findById] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get active profile for a given language
     */
    public static function getActiveProfile(string $language): array
    {
        try {
            $stmt = db()->prepare("SELECT * FROM voice_profiles WHERE language = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$language]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($profile) {
                return $profile;
            }
        } catch (PDOException $e) {
            error_log('[VoiceProfile::getActiveProfile] ' . $e->getMessage());
        }

        // Return default profile structure if none active
        return [
            'id'                => null,
            'language'          => $language,
            'profile_name'      => ucfirst($language) . ' Default Voice',
            'provider'          => 'system',
            'provider_voice_id' => null,
            'description'       => 'System default synthesized voice',
            'is_active'         => 1,
            'fallback_voice'    => $language === 'msm' ? 'ceb-PH' : ($language === 'fil' ? 'fil-PH' : 'en-US'),
            'settings_json'     => json_encode([])
        ];
    }

    /**
     * Set active profile for a language
     */
    public static function setActive(int $id, string $language): bool
    {
        try {
            // Deactivate all for language
            $stmt1 = db()->prepare("UPDATE voice_profiles SET is_active = 0 WHERE language = ?");
            $stmt1->execute([$language]);

            // Activate specified
            $stmt2 = db()->prepare("UPDATE voice_profiles SET is_active = 1, updated_at = NOW() WHERE id = ? AND language = ?");
            return $stmt2->execute([$id, $language]);
        } catch (PDOException $e) {
            error_log('[VoiceProfile::setActive] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new profile
     */
    public static function create(array $data): int
    {
        try {
            // If this is set as active, deactivate existing active profiles for this language first
            if (!empty($data['is_active'])) {
                $stmt = db()->prepare("UPDATE voice_profiles SET is_active = 0 WHERE language = ?");
                $stmt->execute([$data['language']]);
            }

            $sql = "INSERT INTO voice_profiles (
                        language, profile_name, provider, provider_voice_id, description, 
                        is_active, fallback_voice, consent_confirmed, settings_json, created_by,
                        created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, 
                        ?, ?, ?, ?, ?,
                        NOW(), NOW()
                    )";

            $stmt = db()->prepare($sql);
            $stmt->execute([
                $data['language'],
                $data['profile_name'],
                $data['provider'] ?? 'system',
                $data['provider_voice_id'] ?? null,
                $data['description'] ?? null,
                !empty($data['is_active']) ? 1 : 0,
                $data['fallback_voice'] ?? null,
                !empty($data['consent_confirmed']) ? 1 : 0,
                is_array($data['settings_json'] ?? null) ? json_encode($data['settings_json']) : ($data['settings_json'] ?? '{}'),
                $data['created_by'] ?? null
            ]);

            return (int) db()->lastInsertId();
        } catch (PDOException $e) {
            error_log('[VoiceProfile::create] ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Update an existing profile
     */
    public static function update(int $id, array $data): bool
    {
        try {
            if (!empty($data['is_active'])) {
                $existing = self::findById($id);
                if ($existing) {
                    $stmt = db()->prepare("UPDATE voice_profiles SET is_active = 0 WHERE language = ?");
                    $stmt->execute([$existing['language']]);
                }
            }

            $sql = "UPDATE voice_profiles SET 
                        profile_name = ?, 
                        provider = ?, 
                        provider_voice_id = ?, 
                        description = ?, 
                        is_active = ?, 
                        fallback_voice = ?, 
                        consent_confirmed = ?, 
                        settings_json = ?, 
                        updated_at = NOW() 
                    WHERE id = ?";

            $stmt = db()->prepare($sql);
            return $stmt->execute([
                $data['profile_name'],
                $data['provider'] ?? 'system',
                $data['provider_voice_id'] ?? null,
                $data['description'] ?? null,
                !empty($data['is_active']) ? 1 : 0,
                $data['fallback_voice'] ?? null,
                !empty($data['consent_confirmed']) ? 1 : 0,
                is_array($data['settings_json'] ?? null) ? json_encode($data['settings_json']) : ($data['settings_json'] ?? '{}'),
                $id
            ]);
        } catch (PDOException $e) {
            error_log('[VoiceProfile::update] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete profile
     */
    public static function delete(int $id): bool
    {
        try {
            $stmt = db()->prepare("DELETE FROM voice_profiles WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log('[VoiceProfile::delete] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Ensure default seed profiles exist for all 3 languages
     */
    public static function seedDefaultProfiles(): void
    {
        try {
            $defaults = [
                [
                    'language'          => 'msm',
                    'profile_name'      => 'Manobo Community Voice',
                    'provider'          => 'dataset_hybrid',
                    'provider_voice_id' => 'mn-community-v1',
                    'description'       => 'Native Manobo recorded pronunciation dataset with fallback to localized audio synthesis',
                    'is_active'         => 1,
                    'fallback_voice'    => 'ceb-PH',
                    'consent_confirmed' => 1
                ],
                [
                    'language'          => 'fil',
                    'profile_name'      => 'Filipino Default Voice',
                    'provider'          => 'system',
                    'provider_voice_id' => 'fil-PH-Standard-A',
                    'description'       => 'Standard Filipino synthesized voice profile',
                    'is_active'         => 1,
                    'fallback_voice'    => 'fil-PH',
                    'consent_confirmed' => 1
                ],
                [
                    'language'          => 'en',
                    'profile_name'      => 'English Default Voice',
                    'provider'          => 'system',
                    'provider_voice_id' => 'en-US-Standard-C',
                    'description'       => 'Standard English synthesized voice profile',
                    'is_active'         => 1,
                    'fallback_voice'    => 'en-US',
                    'consent_confirmed' => 1
                ]
            ];

            foreach ($defaults as $def) {
                $stmt = db()->prepare("SELECT id FROM voice_profiles WHERE language = ? LIMIT 1");
                $stmt->execute([$def['language']]);
                if (!$stmt->fetch()) {
                    self::create($def);
                }
            }
        } catch (PDOException $e) {
            error_log('[VoiceProfile::seedDefaultProfiles] ' . $e->getMessage());
        }
    }
}
