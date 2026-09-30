<?php

class VoiceProfile
{
    private static function getDb()
    {
        return Database::getInstance();
    }

    /**
     * Get all voice profiles for a language or all languages
     */
    public static function getAll($language = null)
    {
        $db = self::getDb();
        if ($language) {
            return $db->fetchAll(
                "SELECT * FROM voice_profiles WHERE language = ? ORDER BY is_active DESC, profile_name ASC",
                [$language]
            );
        }
        return $db->fetchAll("SELECT * FROM voice_profiles ORDER BY language ASC, is_active DESC, profile_name ASC");
    }

    /**
     * Get profile by ID
     */
    public static function findById($id)
    {
        $db = self::getDb();
        return $db->fetchOne("SELECT * FROM voice_profiles WHERE id = ?", [(int)$id]);
    }

    /**
     * Get active profile for a given language
     */
    public static function getActiveProfile($language)
    {
        $db = self::getDb();
        $profile = $db->fetchOne(
            "SELECT * FROM voice_profiles WHERE language = ? AND is_active = 1 LIMIT 1",
            [$language]
        );

        if (!$profile) {
            // Return default profile structure if none active
            return [
                'id' => null,
                'language' => $language,
                'profile_name' => ucfirst($language) . ' Default Voice',
                'provider' => 'system',
                'provider_voice_id' => null,
                'description' => 'System default synthesized voice',
                'is_active' => 1,
                'fallback_voice' => 'en-US',
                'settings_json' => json_encode([])
            ];
        }

        return $profile;
    }

    /**
     * Set active profile for a language
     */
    public static function setActive($id, $language)
    {
        $db = self::getDb();
        // Deactivate all for language
        $db->query("UPDATE voice_profiles SET is_active = 0 WHERE language = ?", [$language]);
        // Activate specified
        return $db->query(
            "UPDATE voice_profiles SET is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND language = ?",
            [(int)$id, $language]
        );
    }

    /**
     * Create a new profile
     */
    public static function create($data)
    {
        $db = self::getDb();

        // If this is set as active, deactivate existing active profiles for this language first
        if (!empty($data['is_active'])) {
            $db->query("UPDATE voice_profiles SET is_active = 0 WHERE language = ?", [$data['language']]);
        }

        $sql = "INSERT INTO voice_profiles (
                    language, profile_name, provider, provider_voice_id, description, 
                    is_active, fallback_voice, consent_confirmed, settings_json, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $db->query($sql, [
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

        return $db->lastInsertId();
    }

    /**
     * Update an existing profile
     */
    public static function update($id, $data)
    {
        $db = self::getDb();

        if (!empty($data['is_active'])) {
            $existing = self::findById($id);
            if ($existing) {
                $db->query("UPDATE voice_profiles SET is_active = 0 WHERE language = ?", [$existing['language']]);
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
                    updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?";

        return $db->query($sql, [
            $data['profile_name'],
            $data['provider'] ?? 'system',
            $data['provider_voice_id'] ?? null,
            $data['description'] ?? null,
            !empty($data['is_active']) ? 1 : 0,
            $data['fallback_voice'] ?? null,
            !empty($data['consent_confirmed']) ? 1 : 0,
            is_array($data['settings_json'] ?? null) ? json_encode($data['settings_json']) : ($data['settings_json'] ?? '{}'),
            (int)$id
        ]);
    }

    /**
     * Delete profile
     */
    public static function delete($id)
    {
        $db = self::getDb();
        return $db->query("DELETE FROM voice_profiles WHERE id = ?", [(int)$id]);
    }

    /**
     * Ensure default seed profiles exist for all 3 languages
     */
    public static function seedDefaultProfiles()
    {
        $db = self::getDb();
        $defaults = [
            [
                'language' => 'msm',
                'profile_name' => 'Manobo Community Voice',
                'provider' => 'dataset_hybrid',
                'provider_voice_id' => 'mn-community-v1',
                'description' => 'Native Manobo recorded pronunciation dataset with fallback to localized audio synthesis',
                'is_active' => 1,
                'fallback_voice' => 'ceb-PH',
                'consent_confirmed' => 1
            ],
            [
                'language' => 'fil',
                'profile_name' => 'Filipino Default Voice',
                'provider' => 'system',
                'provider_voice_id' => 'fil-PH-Standard-A',
                'description' => 'Standard Filipino synthesized voice profile',
                'is_active' => 1,
                'fallback_voice' => 'fil-PH',
                'consent_confirmed' => 1
            ],
            [
                'language' => 'en',
                'profile_name' => 'English Default Voice',
                'provider' => 'system',
                'provider_voice_id' => 'en-US-Standard-C',
                'description' => 'Standard English synthesized voice profile',
                'is_active' => 1,
                'fallback_voice' => 'en-US',
                'consent_confirmed' => 1
            ]
        ];

        foreach ($defaults as $def) {
            $exists = $db->fetchOne("SELECT id FROM voice_profiles WHERE language = ? LIMIT 1", [$def['language']]);
            if (!$exists) {
                self::create($def);
            }
        }
    }
}
