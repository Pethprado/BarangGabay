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
                $stmt = db()->prepare("SELECT *, name AS profile_name FROM voice_profiles WHERE language = ? ORDER BY is_active DESC, name ASC");
                $stmt->execute([$language]);
            } else {
                $stmt = db()->query("SELECT *, name AS profile_name FROM voice_profiles ORDER BY language ASC, is_active DESC, name ASC");
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
            $stmt = db()->prepare("SELECT *, name AS profile_name FROM voice_profiles WHERE id = ? LIMIT 1");
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
            $stmt = db()->prepare("SELECT *, name AS profile_name FROM voice_profiles WHERE language = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$language]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($profile) {
                return $profile;
            }
        } catch (PDOException $e) {
            error_log('[VoiceProfile::getActiveProfile] ' . $e->getMessage());
        }

        // Return default profile structure if none active
        $defaultName = match ($language) {
            'msm'   => 'Manobo Community Voice',
            'fil'   => 'Filipino Default Voice',
            'en'    => 'English Default Voice',
            'ceb'   => 'Bisaya Default Voice',
            default => ucfirst($language) . ' Default Voice',
        };

        $fallback = match ($language) {
            'msm'   => 'ceb-PH',
            'fil'   => 'fil-PH',
            'ceb'   => 'ceb-PH',
            default => 'en-US',
        };

        return [
            'id'                => null,
            'language'          => $language,
            'name'              => $defaultName,
            'profile_name'      => $defaultName,
            'provider'          => $language === 'msm' ? 'dataset_hybrid' : 'system',
            'provider_voice_id' => $language === 'msm' ? 'mn-community-v1' : ($language === 'fil' ? 'fil-PH-Standard-A' : 'en-US-Standard-C'),
            'description'       => 'Default synthesized voice profile',
            'is_active'         => 1,
            'fallback_voice'    => $fallback,
            'speaking_rate'     => 0.95,
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
     * Create or update a profile (language is unique)
     */
    public static function create(array $data): int
    {
        try {
            $lang = trim((string) ($data['language'] ?? 'msm'));
            $name = trim((string) ($data['name'] ?? $data['profile_name'] ?? 'Default Voice'));
            $provider = trim((string) ($data['provider'] ?? 'system'));
            $providerVoiceId = !empty($data['provider_voice_id']) ? trim((string) $data['provider_voice_id']) : null;
            $fallback = !empty($data['fallback_voice']) ? trim((string) $data['fallback_voice']) : ($lang === 'msm' ? 'ceb-PH' : ($lang === 'fil' ? 'fil-PH' : 'en-US'));
            $rate = isset($data['speaking_rate']) ? (float) $data['speaking_rate'] : 0.95;
            $desc = !empty($data['description']) ? trim((string) $data['description']) : null;
            $isActive = !empty($data['is_active']) ? 1 : 0;
            $userId = isset($data['updated_by']) ? (int) $data['updated_by'] : (isset($data['created_by']) ? (int) $data['created_by'] : null);

            // Check if profile exists for this language
            $existing = db()->prepare("SELECT id FROM voice_profiles WHERE language = ? LIMIT 1");
            $existing->execute([$lang]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $id = (int) $row['id'];
                self::update($id, [
                    'name'              => $name,
                    'profile_name'      => $name,
                    'provider'          => $provider,
                    'provider_voice_id' => $providerVoiceId,
                    'fallback_voice'    => $fallback,
                    'speaking_rate'     => $rate,
                    'description'       => $desc,
                    'is_active'         => $isActive,
                    'updated_by'        => $userId,
                ]);
                return $id;
            }

            // Insert new profile
            $sql = "INSERT INTO voice_profiles (
                        language, name, provider, provider_voice_id, fallback_voice,
                        speaking_rate, is_active, description, updated_by, created_at, updated_at
                    ) VALUES (
                        :language, :name, :provider, :provider_voice_id, :fallback_voice,
                        :speaking_rate, :is_active, :description, :updated_by, NOW(), NOW()
                    )";

            $stmt = db()->prepare($sql);
            $stmt->execute([
                'language'          => $lang,
                'name'              => $name,
                'provider'          => $provider,
                'provider_voice_id' => $providerVoiceId,
                'fallback_voice'    => $fallback,
                'speaking_rate'     => $rate,
                'is_active'         => $isActive,
                'description'       => $desc,
                'updated_by'        => $userId,
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
            $name = trim((string) ($data['name'] ?? $data['profile_name'] ?? ''));
            $provider = trim((string) ($data['provider'] ?? 'system'));
            $providerVoiceId = !empty($data['provider_voice_id']) ? trim((string) $data['provider_voice_id']) : null;
            $fallback = !empty($data['fallback_voice']) ? trim((string) $data['fallback_voice']) : null;
            $rate = isset($data['speaking_rate']) ? (float) $data['speaking_rate'] : 0.95;
            $desc = isset($data['description']) ? trim((string) $data['description']) : null;
            $isActive = !empty($data['is_active']) ? 1 : 0;
            $userId = isset($data['updated_by']) ? (int) $data['updated_by'] : (isset($data['created_by']) ? (int) $data['created_by'] : null);

            $sql = "UPDATE voice_profiles SET 
                        name = :name, 
                        provider = :provider, 
                        provider_voice_id = :provider_voice_id, 
                        fallback_voice = COALESCE(:fallback_voice, fallback_voice), 
                        speaking_rate = :speaking_rate, 
                        is_active = :is_active, 
                        description = :description, 
                        updated_by = :updated_by, 
                        updated_at = NOW() 
                    WHERE id = :id";

            $stmt = db()->prepare($sql);
            return $stmt->execute([
                'name'              => $name,
                'provider'          => $provider,
                'provider_voice_id' => $providerVoiceId,
                'fallback_voice'    => $fallback,
                'speaking_rate'     => $rate,
                'is_active'         => $isActive,
                'description'       => $desc,
                'updated_by'        => $userId,
                'id'                => $id,
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
                    'name'              => 'Manobo Community Voice',
                    'profile_name'      => 'Manobo Community Voice',
                    'provider'          => 'dataset_hybrid',
                    'provider_voice_id' => 'mn-community-v1',
                    'description'       => 'Native Manobo recorded pronunciation dataset with fallback to localized audio synthesis',
                    'is_active'         => 1,
                    'fallback_voice'    => 'ceb-PH',
                    'speaking_rate'     => 0.92,
                ],
                [
                    'language'          => 'fil',
                    'name'              => 'Filipino Default Voice',
                    'profile_name'      => 'Filipino Default Voice',
                    'provider'          => 'system',
                    'provider_voice_id' => 'fil-PH-Standard-A',
                    'description'       => 'Standard Filipino synthesized voice profile',
                    'is_active'         => 1,
                    'fallback_voice'    => 'fil-PH',
                    'speaking_rate'     => 0.95,
                ],
                [
                    'language'          => 'en',
                    'name'              => 'English Default Voice',
                    'profile_name'      => 'English Default Voice',
                    'provider'          => 'system',
                    'provider_voice_id' => 'en-US-Standard-C',
                    'description'       => 'Standard English synthesized voice profile',
                    'is_active'         => 1,
                    'fallback_voice'    => 'en-US',
                    'speaking_rate'     => 1.0,
                ],
                [
                    'language'          => 'ceb',
                    'name'              => 'Bisaya Default Voice',
                    'profile_name'      => 'Bisaya Default Voice',
                    'provider'          => 'dataset_hybrid',
                    'provider_voice_id' => 'ceb-PH',
                    'description'       => 'Recorded Bisaya/Cebuano pronunciations, used for Bisaya words inside Manobo text',
                    'is_active'         => 1,
                    'fallback_voice'    => 'ceb-PH',
                    'speaking_rate'     => 0.95,
                ],
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
