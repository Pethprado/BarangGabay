<?php
declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Verifies that a resident's uploaded file is a genuine Philippine government ID
 * using Claude's vision capability. Designed to fail-open (allow registration)
 * when the API is unavailable, so a transient outage never blocks real residents.
 *
 * Returned array always contains:
 *   is_valid    bool   — whether to allow registration
 *   id_ai_status string — 'ai_passed' | 'ai_flagged' | 'manual_review' | 'skipped'
 *   confidence  string — 'high' | 'medium' | 'low' | 'unknown'
 *   id_type     string — name of detected ID, or 'unknown'
 *   reason      string — short explanation (always in English)
 *   has_photo   bool
 *   has_name    bool
 *   issues      array  — list of detected problems
 */
class IdVerificationService
{
    private ?Client $client = null;

    public function __construct()
    {
        $apiKey = env('ANTHROPIC_API_KEY', '');
        if (empty($apiKey)) {
            return;
        }
        $this->client = new Client([
            'base_uri' => 'https://api.anthropic.com',
            'headers'  => [
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ],
            'timeout' => 45,
        ]);
    }

    /**
     * Verify whether a PHP tmp-file path contains a valid Philippine government ID.
     *
     * @param  string $tmpPath  Value from $_FILES['id_photo']['tmp_name']
     * @param  string $mimeType MIME type detected via finfo (must be image/*)
     * @return array            See class docblock for structure
     */
    public function verifyId(string $tmpPath, string $mimeType): array
    {
        // ── No API key: fail-open, mark for manual review ─────────────────
        if ($this->client === null) {
            return [
                'is_valid'      => true,
                'id_ai_status'  => 'skipped',
                'confidence'    => 'unknown',
                'id_type'       => 'unknown',
                'reason'        => 'AI verification skipped — no API key configured. Manual review required.',
                'has_photo'     => true,
                'has_name'      => true,
                'issues'        => [],
            ];
        }

        // ── File sanity check ─────────────────────────────────────────────
        if (!file_exists($tmpPath) || !is_readable($tmpPath)) {
            return [
                'is_valid'      => false,
                'id_ai_status'  => 'ai_flagged',
                'confidence'    => 'high',
                'id_type'       => 'unknown',
                'reason'        => 'The uploaded file could not be found.',
                'has_photo'     => false,
                'has_name'      => false,
                'issues'        => ['File not found after upload'],
            ];
        }

        // ── Base64-encode the image ───────────────────────────────────────
        $imageData = base64_encode((string) file_get_contents($tmpPath));

        $prompt = <<<'PROMPT'
Analyze this image and determine if it is a REAL Philippine government-issued Valid ID or identification document.

VALID Philippine IDs to look for:
• PhilSys / National ID
• SSS (Social Security System) ID
• GSIS ID
• PhilHealth ID
• Pag-IBIG / HDMF ID
• Voter's ID / COMELEC ID
• Driver's License (LTO)
• Philippine Passport
• PRC ID (Professional Regulation Commission)
• Senior Citizen ID
• PWD (Persons with Disability) ID
• Postal ID
• NBI Clearance with photo
• Police Clearance with photo
• Barangay ID with photo
• School / Student ID with official seal and photo
• Company ID with official logo and photo
• TIN ID (BIR)

CHECK THESE CAREFULLY:
1. Does the image contain an actual ID card or document (not just a person's selfie)?
2. Is there a visible PHOTO of a person on the ID?
3. Is there a FULL NAME visible on the ID?
4. Does the ID have official markings (logo, seal, government agency name, ID number)?
5. Is the ID clearly visible and readable (not extremely blurry, very dark, or mostly covered)?
6. Does it appear to be a physical card/document rather than a drawing or digital mockup?

REJECTION CRITERIA (clearly invalid):
✗ Random selfie with no ID card visible
✗ Blank paper, screenshot of nothing, or cartoon
✗ Hand-drawn or obviously digitally fabricated ID
✗ ID where the face is completely covered/hidden
✗ Pure text screenshot with no ID card structure

LENIENT CRITERIA (allow with flag):
~ Slightly blurry but recognizable real ID → allow
~ ID partially cut off but visible enough → allow
~ Low-quality photo of a genuine ID → allow

Respond with ONLY a JSON object — no markdown fences, no extra text:
{"is_valid":true_or_false,"confidence":"high_or_medium_or_low","id_type":"detected_id_name_or_unknown","has_photo":true_or_false,"has_name":true_or_false,"is_readable":true_or_false,"reason":"brief_explanation_in_English","issues":["list_of_issues_in_English"]}
PROMPT;

        try {
            $response = $this->client->post('/v1/messages', [
                'json' => [
                    'model'      => 'claude-haiku-4-5-20251001',
                    'max_tokens' => 400,
                    'messages'   => [[
                        'role'    => 'user',
                        'content' => [
                            [
                                'type'   => 'image',
                                'source' => [
                                    'type'       => 'base64',
                                    'media_type' => $mimeType,
                                    'data'       => $imageData,
                                ],
                            ],
                            ['type' => 'text', 'text' => $prompt],
                        ],
                    ]],
                ],
            ]);
        } catch (GuzzleException $e) {
            error_log('[IdVerification] API call failed: ' . $e->getMessage());
            return $this->failOpen('API call failed: ' . $e->getMessage());
        }

        $body    = (string) $response->getBody();
        $decoded = json_decode($body, true);
        $rawText = trim($decoded['content'][0]['text'] ?? '');

        // Strip accidental markdown fences Claude sometimes wraps around JSON
        $rawText = preg_replace('/^```(?:json)?\s*/i', '', $rawText);
        $rawText = preg_replace('/\s*```$/', '', trim($rawText));

        $result = json_decode($rawText, true);

        if (!is_array($result) || !isset($result['is_valid'])) {
            error_log('[IdVerification] JSON parse failed. Raw: ' . $rawText);
            return $this->failOpen('AI response could not be parsed — manual review required.');
        }

        // Normalise boolean (Claude sometimes returns strings)
        $result['is_valid']   = filter_var($result['is_valid'],   FILTER_VALIDATE_BOOLEAN);
        $result['has_photo']  = filter_var($result['has_photo']  ?? true, FILTER_VALIDATE_BOOLEAN);
        $result['has_name']   = filter_var($result['has_name']   ?? true, FILTER_VALIDATE_BOOLEAN);
        $result['is_readable']= filter_var($result['is_readable'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $result['issues']     = is_array($result['issues'] ?? null) ? $result['issues'] : [];
        $result['confidence'] = $result['confidence'] ?? 'medium';
        $result['id_type']    = $result['id_type']    ?? 'unknown';
        $result['reason']     = $result['reason']     ?? '';

        // Decide the quick-status field
        if ($result['is_valid']) {
            $result['id_ai_status'] = 'ai_passed';
        } elseif ($result['confidence'] === 'low') {
            // Low-confidence rejection → flag but don't hard-block
            $result['is_valid']     = true;
            $result['id_ai_status'] = 'manual_review';
        } else {
            $result['id_ai_status'] = 'ai_flagged';
        }

        error_log('[IdVerification] Result: '
            . 'valid=' . ($result['is_valid'] ? 'true' : 'false')
            . ' status=' . $result['id_ai_status']
            . ' type='   . $result['id_type']
            . ' conf='   . $result['confidence']);

        return $result;
    }

    // ── Private helpers ────────────────────────────────────────────────────

    /**
     * Return a fail-open result (allow registration but flag for manual review).
     * Used when the API is unreachable or returns an unparseable response.
     */
    private function failOpen(string $reason): array
    {
        return [
            'is_valid'      => true,
            'id_ai_status'  => 'manual_review',
            'confidence'    => 'unknown',
            'id_type'       => 'unknown',
            'reason'        => $reason,
            'has_photo'     => true,
            'has_name'      => true,
            'is_readable'   => true,
            'issues'        => [],
        ];
    }
}