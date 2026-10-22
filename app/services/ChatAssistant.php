<?php
declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;

/**
 * ChatAssistant — the BarangGabay AI chat, with free providers.
 *
 * Builds one guide prompt and tries each configured AI provider in turn:
 *
 *   1. Claude (Anthropic)  — paid; used when ANTHROPIC_API_KEY has credits
 *   2. Google Gemini       — free tier (GEMINI_API_KEY from aistudio.google.com)
 *   3. Groq                — free tier (GROQ_API_KEY from console.groq.com)
 *
 * A provider without a key is skipped. A provider that fails because of
 * billing or quota is skipped for a while (see cooldown()) so every question
 * doesn't wait on a request that is sure to fail. If every provider fails,
 * the caller answers with the offline BarangayGuide instead.
 *
 * The order can be changed with AI_CHAT_PROVIDERS, e.g. "gemini,groq,claude".
 */
class ChatAssistant
{
    private const DEFAULT_ORDER = 'claude,gemini,groq';

    /** How long a provider is skipped after a billing/quota error (seconds). */
    private const COOLDOWN = 600;

    private ?Client $http = null;

    public function __construct(private AIService $claude)
    {
    }

    /**
     * Answer a resident's question.
     *
     * @param list<array{role:string, content:string}> $history Earlier turns, oldest first.
     * @return array{answer:string, provider:string}
     * @throws \RuntimeException When no provider could answer.
     */
    public function answer(string $question, string $context, array $history = [], string $locale = 'fil'): array
    {
        $system   = $this->systemPrompt($context, $locale);
        $messages = $this->messages($history, $question);
        $errors   = [];

        foreach ($this->order() as $provider) {
            if ($this->onCooldown($provider)) {
                $errors[] = "{$provider}: cooling down";
                continue;
            }
            try {
                $answer = match ($provider) {
                    'claude' => $this->claude->complete($system, $messages),
                    'gemini' => $this->gemini($system, $messages),
                    'groq'   => $this->groq($system, $messages),
                    default  => throw new \RuntimeException('unknown provider'),
                };
                return ['answer' => $answer, 'provider' => $provider];
            } catch (\Throwable $e) {
                $errors[] = "{$provider}: " . mb_substr($e->getMessage(), 0, 200);
                if ($this->isBillingOrQuota($e)) {
                    $this->cooldown($provider);
                }
            }
        }

        throw new \RuntimeException('No AI provider answered — ' . implode(' | ', $errors));
    }

    /** True when at least one provider has a key configured. */
    public static function anyConfigured(): bool
    {
        return env('ANTHROPIC_API_KEY', '') !== '' || env('GEMINI_API_KEY', '') !== '' || env('GROQ_API_KEY', '') !== '';
    }

    // ── Prompt ──────────────────────────────────────────────────────────

    private function systemPrompt(string $context, string $locale): string
    {
        $language = match ($locale) {
            'en'    => 'simple English',
            'msm'   => 'simple Bisaya (Cebuano), using Manobo words only when you are sure of them',
            default => 'simple Filipino (Taglish is fine)',
        };
        $today = date('l, F j, Y g:i A');

        return <<<PROMPT
You are BarangGabay AI, the friendly guide of the BarangGabay web app for residents of Barangay Bayogo, Madrid, Surigao del Sur.
Your job is to help residents understand barangay announcements, events and ordinances, and to guide them step by step in using the app (requesting documents, paying, checking evacuation centres, managing their account, etc.).

RULES
- Answer from the BARANGAY BRIEFING below. Never invent dates, fees, names, phone numbers, laws or events.
- If something is not in the briefing, say you don't have that information yet and suggest visiting the Barangay Hall or sending a message via Feedback (/feedback).
- When the resident is confused, explain in short simple steps, like talking to a neighbour. Give an example if it helps.
- When you point to a page, write its path in brackets exactly as given, e.g. (/documents), with no backticks. The app turns it into a link.
- When you mention a specific post, include its path, e.g. (/announcements/slug).
- For emergencies (typhoon, flood, earthquake), tell them to follow barangay officials/MDRRMO and show the evacuation centres.
- No legal, medical or financial advice beyond what the briefing says.
- Reply in {$language}, unless the resident clearly writes in another language — then use theirs.
- Keep it short: under 160 words. Use short lines or numbered steps, no markdown headings or tables.

BARANGAY BRIEFING (today is {$today}):
{$context}
PROMPT;
    }

    /**
     * Earlier turns + the new question, as alternating user/assistant
     * messages that start and end with "user" (what every provider accepts).
     *
     * @return list<array{role:string, content:string}>
     */
    private function messages(array $history, string $question): array
    {
        $messages = [];
        foreach (\array_slice($history, -8) as $turn) {
            if (!\is_array($turn)) {
                continue;
            }
            $role    = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim(mb_substr((string) ($turn['content'] ?? ''), 0, 1200));
            if ($content === '' || ($messages === [] && $role !== 'user')) {
                continue;
            }
            if ($messages !== [] && end($messages)['role'] === $role) {
                $messages[\count($messages) - 1]['content'] .= "\n" . $content;
                continue;
            }
            $messages[] = ['role' => $role, 'content' => $content];
        }
        if ($messages !== [] && end($messages)['role'] === 'user') {
            array_pop($messages);
        }
        $messages[] = ['role' => 'user', 'content' => $question];
        return $messages;
    }

    // ── Providers ───────────────────────────────────────────────────────

    /** Google Gemini generateContent (free tier on Flash models). */
    private function gemini(string $system, array $messages): string
    {
        $key = (string) env('GEMINI_API_KEY', '');
        if ($key === '') {
            throw new \RuntimeException('GEMINI_API_KEY not configured.');
        }
        // Flash-Lite answers in ~2 s on the free tier; full Flash took 16–80 s
        // with this briefing (measured 2026-10-22), too slow for a chat.
        $model = (string) (env('GEMINI_MODEL', '') ?: 'gemini-3.5-flash-lite');

        $contents = array_map(static fn(array $m) => [
            'role'  => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ], $messages);

        $response = $this->http()->post(
            'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent',
            [
                'headers' => ['x-goog-api-key' => $key],
                'json'    => [
                    'system_instruction' => ['parts' => [['text' => $system]]],
                    'contents'           => $contents,
                    // Room for the model's own "thinking" plus the answer.
                    'generationConfig'   => ['maxOutputTokens' => 2048, 'temperature' => 0.4],
                ],
            ]
        );

        $data  = json_decode((string) $response->getBody(), true);
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        $text  = '';
        foreach ($parts as $part) {
            if (empty($part['thought'])) {
                $text .= (string) ($part['text'] ?? '');
            }
        }
        $text = trim($text);
        if ($text === '') {
            throw new \RuntimeException('Empty answer from Gemini (' . ($data['candidates'][0]['finishReason'] ?? 'no candidate') . ').');
        }
        return $text;
    }

    /** Groq chat completions (OpenAI-compatible; free tier). */
    private function groq(string $system, array $messages): string
    {
        $key = (string) env('GROQ_API_KEY', '');
        if ($key === '') {
            throw new \RuntimeException('GROQ_API_KEY not configured.');
        }
        $model = (string) (env('GROQ_MODEL', '') ?: 'openai/gpt-oss-120b');

        $body = [
            'model'       => $model,
            'messages'    => array_merge([['role' => 'system', 'content' => $system]], $messages),
            'max_tokens'  => 2048,
            'temperature' => 0.4,
        ];
        if (str_starts_with($model, 'openai/gpt-oss')) {
            $body['reasoning_effort'] = 'low';   // faster, fewer tokens; plenty for Q&A
        }

        $response = $this->http()->post('https://api.groq.com/openai/v1/chat/completions', [
            'headers' => ['Authorization' => 'Bearer ' . $key],
            'json'    => $body,
        ]);

        $data = json_decode((string) $response->getBody(), true);
        $text = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
        if ($text === '') {
            throw new \RuntimeException('Empty answer from Groq.');
        }
        return $text;
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /** @return list<string> Providers to try, in order. */
    private function order(): array
    {
        $raw = (string) (env('AI_CHAT_PROVIDERS', '') ?: self::DEFAULT_ORDER);
        $out = [];
        foreach (explode(',', strtolower($raw)) as $name) {
            $name = trim($name);
            if (\in_array($name, ['claude', 'gemini', 'groq'], true) && !\in_array($name, $out, true)) {
                $out[] = $name;
            }
        }
        return $out;
    }

    private function http(): Client
    {
        return $this->http ??= new Client(['timeout' => 25, 'connect_timeout' => 8]);
    }

    /** Errors that will repeat on the next question too: no credits, quota used up, bad key. */
    private function isBillingOrQuota(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());
        foreach (['credit balance', 'quota', 'resource_exhausted', 'rate limit', '429', 'billing',
                  'api key not valid', 'invalid api key', 'invalid x-api-key', '401', '403'] as $needle) {
            if (str_contains($msg, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function cooldownFile(string $provider): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'baranggabay_ai_cooldown_' . $provider;
    }

    private function cooldown(string $provider): void
    {
        @file_put_contents($this->cooldownFile($provider), (string) (time() + self::COOLDOWN));
    }

    private function onCooldown(string $provider): bool
    {
        $until = (int) @file_get_contents($this->cooldownFile($provider));
        return $until > time();
    }
}
