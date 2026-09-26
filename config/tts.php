<?php
declare(strict_types=1);

/**
 * Text-to-speech configuration.
 *
 * Deliberately separate from config/ai.php. Anthropic has no text-to-speech
 * API, so the Claude key cannot produce audio and must never be sent here —
 * these are different vendors with different billing and different failure
 * modes, and collapsing them into one "AI" config invites someone to try.
 *
 * Two providers are supported, both chosen because their free tiers are far
 * larger than a barangay's posting volume and both have a real Philippine
 * Filipino voice:
 *
 *   google — Cloud Text-to-Speech. 1 million Neural2/WaveNet characters free
 *            per month. Key is an API key with the TTS API enabled.
 *   azure  — Azure AI Speech. 500,000 neural characters free per month.
 *            Key is a Speech resource key; TTS_REGION must match the resource.
 *
 * With no key configured everything still works: the voice reader falls back
 * to the device's own speech engine, exactly as it did before.
 */

$provider = strtolower(trim((string) ($_ENV['TTS_PROVIDER'] ?? '')));
if (!in_array($provider, ['google', 'azure'], true)) {
    $provider = '';
}

return [
    'provider' => $provider,
    'api_key'  => (string) ($_ENV['TTS_API_KEY'] ?? ''),

    // Azure only — the region its Speech resource lives in, e.g. 'southeastasia'.
    'region'   => (string) ($_ENV['TTS_REGION'] ?? 'southeastasia'),

    /*
     * Voice per language.
     *
     * Manobo has no entry of its own anywhere in the world: no provider offers
     * an Agusan Manobo voice. It is read by the Filipino voice instead, which
     * is an approximation and is labelled as one everywhere it plays — see
     * SpokenText::isApproximateVoice(). Manobo's orthography is Latin and
     * largely phonemic, and it shares most of its vowel and consonant
     * inventory with Filipino, so the result is broadly intelligible; the
     * schwa written <e>, the stress pattern and the glottal stops are what it
     * gets wrong. A recording by a Manobo speaker always wins over this.
     */
    'voices' => [
        'google' => [
            'en'  => ['language_code' => 'en-US',  'name' => $_ENV['TTS_VOICE_EN']  ?? 'en-US-Neural2-F'],
            'fil' => ['language_code' => 'fil-PH', 'name' => $_ENV['TTS_VOICE_FIL'] ?? 'fil-PH-Wavenet-A'],
            'msm' => ['language_code' => 'fil-PH', 'name' => $_ENV['TTS_VOICE_FIL'] ?? 'fil-PH-Wavenet-A'],
        ],
        'azure' => [
            'en'  => ['language_code' => 'en-US',  'name' => $_ENV['TTS_VOICE_EN']  ?? 'en-US-AriaNeural'],
            'fil' => ['language_code' => 'fil-PH', 'name' => $_ENV['TTS_VOICE_FIL'] ?? 'fil-PH-BlessicaNeural'],
            'msm' => ['language_code' => 'fil-PH', 'name' => $_ENV['TTS_VOICE_FIL'] ?? 'fil-PH-BlessicaNeural'],
        ],
    ],

    /*
     * Slightly under natural pace. These are public notices read to people who
     * may be hearing an unfamiliar word for the first time, and the player's
     * speed control multiplies this rather than replacing it.
     */
    'speaking_rate' => (float) ($_ENV['TTS_SPEAKING_RATE'] ?? 0.95),

    // Seconds. Generation happens inside a staff member's save request, so a
    // provider having a bad day must not hold that request open for a minute.
    'timeout' => (int) ($_ENV['TTS_TIMEOUT'] ?? 25),

    /*
     * Hard ceiling on characters synthesised for one post, across all its
     * languages, per generation run. A guard against a pasted PDF quietly
     * spending a month's free tier in one save.
     */
    'max_chars_per_post' => (int) ($_ENV['TTS_MAX_CHARS_PER_POST'] ?? 20000),
];
