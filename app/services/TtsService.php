<?php
declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;

/**
 * Speaks text with a real provider voice and hands back MP3 bytes.
 *
 * Thin on purpose: it knows how to talk to Google Cloud Text-to-Speech and to
 * Azure AI Speech, and nothing else. Which post the audio belongs to, where it
 * is stored and when it goes stale are PostAudioService's problem.
 *
 * Anthropic is not and cannot be one of the providers here — it has no
 * text-to-speech API. The key in config/ai.php must never reach this class.
 *
 * Every method either returns audio or throws. There is no "half worked":
 * a truncated MP3 that stops mid-sentence is worse for a listener than the
 * device's own voice reading the whole thing, which is what the caller falls
 * back to when this throws.
 */
class TtsService
{
    /**
     * Characters per request.
     *
     * Google's hard limit is 5000 bytes of input including the SSML wrapper;
     * Azure's is generous but its per-request audio length is not. Staying
     * well under both means a long announcement is synthesised as several
     * requests whose MP3s are concatenated — which works because MP3 is a
     * frame stream with no global header to reconcile.
     */
    private const CHUNK_CHARS = 3000;

    private array $config;
    private ?Client $client = null;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? require \dirname(__DIR__, 2) . '/config/tts.php';
    }

    /** Whether a provider and key are configured at all. */
    public function isConfigured(): bool
    {
        return $this->config['provider'] !== '' && trim((string) $this->config['api_key']) !== '';
    }

    /** Which provider is in use, or '' when the browser engine is all there is. */
    public function provider(): string
    {
        return (string) $this->config['provider'];
    }

    /**
     * The provider voice used for a language.
     *
     * For Manobo this is the Filipino voice. That is an approximation, and
     * SpokenText::isApproximateVoice() is what the UI uses to label it — the
     * name returned here is genuinely the voice that spoke, so it is stored
     * as-is and a Manobo track will honestly read "fil-PH-…".
     *
     * @return array{language_code:string, name:string}|null
     */
    public function voiceFor(string $locale): ?array
    {
        $voices = $this->config['voices'][$this->config['provider']] ?? [];

        return $voices[$locale] ?? null;
    }

    /**
     * Synthesise one script into MP3 bytes.
     *
     * @param string $text   Plain text — already expanded and normalised by
     *                       SpokenText. No SSML markup is accepted; anything
     *                       that looks like a tag is escaped, because this text
     *                       comes from a Quill editor and a stray angle bracket
     *                       must not become a speech instruction.
     * @param string $locale 'en' | 'fil' | 'msm'
     *
     * @return array{bytes:string, voice_name:string, chars:int}
     *
     * @throws \RuntimeException when unconfigured, unsupported, or the provider fails
     */
    public function synthesize(string $text, string $locale): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('No text-to-speech provider is configured.');
        }

        $text = trim($text);
        if ($text === '') {
            throw new \RuntimeException('Nothing to synthesise.');
        }

        $voice = $this->voiceFor($locale);
        if ($voice === null) {
            throw new \RuntimeException("No voice configured for '{$locale}'.");
        }

        $audio = '';
        foreach ($this->split($text) as $piece) {
            $audio .= $this->config['provider'] === 'azure'
                ? $this->azure($piece, $voice)
                : $this->google($piece, $voice);
        }

        if ($audio === '') {
            throw new \RuntimeException('The provider returned no audio.');
        }

        return [
            'bytes'      => $audio,
            'voice_name' => (string) $voice['name'],
            'chars'      => mb_strlen($text),
        ];
    }

    // ── Providers ────────────────────────────────────────────────────────────

    /** Google Cloud Text-to-Speech. Returns raw MP3 bytes. */
    private function google(string $text, array $voice): string
    {
        $response = $this->client()->post(
            'https://texttospeech.googleapis.com/v1/text:synthesize?key=' . urlencode((string) $this->config['api_key']),
            [
                'json' => [
                    // 'text', never 'ssml' — see the note on $text above.
                    'input' => ['text' => $text],
                    'voice' => [
                        'languageCode' => $voice['language_code'],
                        'name'         => $voice['name'],
                    ],
                    'audioConfig' => [
                        'audioEncoding' => 'MP3',
                        'speakingRate'  => (float) $this->config['speaking_rate'],
                    ],
                ],
            ]
        );

        $data  = json_decode((string) $response->getBody(), true);
        $bytes = base64_decode((string) ($data['audioContent'] ?? ''), true);

        if ($bytes === false || $bytes === '') {
            throw new \RuntimeException('Google Text-to-Speech returned no audio content.');
        }

        return $bytes;
    }

    /** Azure AI Speech. Returns raw MP3 bytes. */
    private function azure(string $text, array $voice): string
    {
        $region = trim((string) $this->config['region']);
        if ($region === '') {
            throw new \RuntimeException('TTS_REGION is required for the Azure provider.');
        }

        // Azure speaks SSML only, so the text is escaped into it rather than
        // interpolated — a "<" in an announcement body must stay a "<".
        $ssml = \sprintf(
            '<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="%s">'
            . '<voice name="%s"><prosody rate="%s">%s</prosody></voice></speak>',
            htmlspecialchars($voice['language_code'], ENT_QUOTES | ENT_XML1, 'UTF-8'),
            htmlspecialchars($voice['name'], ENT_QUOTES | ENT_XML1, 'UTF-8'),
            $this->azureRate(),
            htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8')
        );

        $response = $this->client()->post(
            "https://{$region}.tts.speech.microsoft.com/cognitiveservices/v1",
            [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => (string) $this->config['api_key'],
                    'Content-Type'              => 'application/ssml+xml',
                    'X-Microsoft-OutputFormat'  => 'audio-24khz-48kbitrate-mono-mp3',
                    'User-Agent'                => 'BarangGabay',
                ],
                'body' => $ssml,
            ]
        );

        $bytes = (string) $response->getBody();
        if ($bytes === '') {
            throw new \RuntimeException('Azure Speech returned no audio content.');
        }

        return $bytes;
    }

    /** Azure expresses rate as a percentage offset from normal. */
    private function azureRate(): string
    {
        $percent = (int) round(((float) $this->config['speaking_rate'] - 1.0) * 100);

        return ($percent >= 0 ? '+' : '') . $percent . '%';
    }

    // ── Internals ────────────────────────────────────────────────────────────

    private function client(): Client
    {
        return $this->client ??= new Client([
            'timeout'     => (int) $this->config['timeout'],
            'http_errors' => true,
        ]);
    }

    /**
     * Split a long script at sentence boundaries, never mid-word.
     *
     * Each piece becomes its own request and its own run of MP3 frames; a
     * break at a full stop is inaudible because the voice pauses there anyway.
     *
     * @return list<string>
     */
    private function split(string $text): array
    {
        if (mb_strlen($text) <= self::CHUNK_CHARS) {
            return [$text];
        }

        $out    = [];
        $buffer = '';

        foreach (SpokenText::sentences($text) as $sentence) {
            $candidate = $buffer === '' ? $sentence : $buffer . ' ' . $sentence;

            if ($buffer !== '' && mb_strlen($candidate) > self::CHUNK_CHARS) {
                $out[]  = $buffer;
                $buffer = $sentence;
            } else {
                $buffer = $candidate;
            }
        }

        if ($buffer !== '') {
            $out[] = $buffer;
        }

        return $out;
    }
}
