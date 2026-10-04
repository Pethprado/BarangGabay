<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use PDO;

/**
 * Manobo and Bisaya Hybrid Translator for BarangGabay.
 *
 * Priority: APPROVED MANOBO DATASET → BISAYA FALLBACK → ORIGINAL ONLY FOR PROTECTED CONTENT.
 *
 * Requirements:
 * 1. Longest approved Manobo phrase/word match first from the approved dictionary.
 * 2. Unmatched content translated into natural Bisaya/Cebuano (via local dictionary and MyMemory fallback).
 * 3. Never invent Manobo vocabulary.
 * 4. Strictly preserve protected entities: names, dates, times, currencies, ordinance numbers,
 *    official barangay references, phone numbers, emails, URLs.
 * 5. Track missing concepts in `manobo_missing_concepts`.
 * 6. Cache translations in `translation_cache` keyed by source hash and dictionary version.
 * 7. Support provenance metadata for admin/debug review.
 */
class ManoboHybridTranslator
{
    private PDO $db;
    private static ?array $phraseCache = null;
    private static ?array $bisayaIndex = null;
    private static ?int $cachedVersion = null;

    /**
     * Normalised source words the last translateToBisaya() call had to leave
     * as they were (no Manobo, no Bisaya). In MN output these are NOT Bisaya —
     * they are the source language (usually Filipino) showing through, so they
     * are labelled 'unresolved' and queued as translation gaps instead of
     * being passed off as a Bisaya fallback.
     *
     * @var array<string,true>
     */
    private array $lastUnresolved = [];

    /**
     * Wall-clock time (microtime) after which no more machine-translation
     * calls are made; the local dictionaries still apply. Long batch jobs set
     * this so a request cannot outlive PHP's execution limit.
     */
    public static ?float $machineDeadline = null;

    /** @var array<string,true>|null Normalised Bisaya headwords. */
    private static ?array $bisayaWords = null;

    /** Words that are legitimately identical in Filipino and Bisaya. */
    private const SHARED_FUNCTION_WORDS = ['sa', 'ang', 'mga', 'ug', 'o', 'kung', 'para', 'na', 'ka', 'si', 'ni', 'kay',
        // lowercase particles inside place names ("Surigao del Sur")
        'del', 'de', 'la', 'los', 'las'];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /**
     * Get the current dictionary version.
     */
    public function getDictionaryVersion(): int
    {
        return (int) Setting::get('manobo_dictionary_version', 1);
    }

    /**
     * Increment the dictionary version and clear runtime cache.
     */
    public static function incrementDictionaryVersion(): int
    {
        $newVersion = (int) Setting::get('manobo_dictionary_version', 1) + 1;
        Setting::set('manobo_dictionary_version', $newVersion);
        self::$phraseCache = null;
        self::$bisayaIndex = null;
        self::$cachedVersion = null;
        return $newVersion;
    }

    /**
     * Main translation entry point.
     *
     * @param string $text Plain text or HTML.
     * @param string $sourceLang 'fil' | 'en' | 'auto'
     * @param array $options ['force_refresh' => bool, 'is_html' => bool]
     * @return array{
     *     success: bool,
     *     translation: string,
     *     language: string,
     *     source_lang: string,
     *     manoboMatches: int,
     *     bisayaFallbacks: int,
     *     provenance: list<array<string,mixed>>,
     *     cached: bool
     * }
     */
    public function translate(string $text, string $sourceLang = 'auto', array $options = []): array
    {
        $raw = trim($text);
        if ($raw === '') {
            return [
                'success'         => true,
                'translation'     => '',
                'language'        => 'mn',
                'source_lang'     => $sourceLang === 'auto' ? 'fil' : $sourceLang,
                'manoboMatches'   => 0,
                'bisayaFallbacks' => 0,
                'provenance'      => [],
                'cached'          => false,
            ];
        }

        if ($sourceLang === 'auto' || !in_array($sourceLang, ['fil', 'en', 'ceb'], true)) {
            $detected = LanguageGuess::detectOrNull($raw);
            $sourceLang = in_array($detected, ['fil', 'en'], true) ? $detected : 'fil';
        }

        $dictVersion = $this->getDictionaryVersion();
        $sourceHash  = sha1($sourceLang . '|mn|' . $raw);

        // 1. Check persistent translation cache
        if (empty($options['force_refresh'])) {
            $cached = $this->getCachedTranslation($sourceHash, $dictVersion);
            if ($cached !== null) {
                $cached['unresolved'] = self::countUnresolved($cached['provenance'] ?? []);
                return $cached;
            }
        }

        // 2. Identify & mask protected entities & HTML markup
        [$maskedText, $protectedMap] = $this->maskProtectedEntities($raw);

        // 3. Process text sentences / paragraphs
        $result = $this->processHybridTranslation($maskedText, $sourceLang);

        // 4. Restore protected entities
        $finalTranslation = $this->unmaskProtectedEntities($result['translation'], $protectedMap);

        // Update provenance with restored values
        $provenance = [];
        foreach ($result['provenance'] as $item) {
            $item['translated'] = $this->unmaskProtectedEntities($item['translated'], $protectedMap);
            $provenance[] = $item;
        }

        // 5. Store in translation_cache
        $this->storeCachedTranslation(
            $sourceHash,
            $sourceLang,
            'mn',
            $dictVersion,
            $finalTranslation,
            $provenance,
            $result['manoboMatches'],
            $result['bisayaFallbacks']
        );

        return [
            'success'         => true,
            'translation'     => $finalTranslation,
            'language'        => 'mn',
            'source_lang'     => $sourceLang,
            'manoboMatches'   => $result['manoboMatches'],
            'bisayaFallbacks' => $result['bisayaFallbacks'],
            'unresolved'      => self::countUnresolved($provenance),
            'provenance'      => $provenance,
            'cached'          => false,
        ];
    }

    /**
     * Segments of an MN translation that are neither Manobo nor Bisaya — the
     * source language left in place because no dictionary had the word.
     * Should trend to 0 as the dictionaries grow; never silently hidden.
     *
     * @param list<array<string,mixed>> $provenance
     */
    public static function countUnresolved(array $provenance): int
    {
        $n = 0;
        foreach ($provenance as $p) {
            if (($p['source'] ?? '') === 'unresolved') {
                $n++;
            }
        }
        return $n;
    }

    /**
     * Identify and mask protected entities.
     */
    public function maskProtectedEntities(string $text): array
    {
        $map = [];
        $index = 0;

        $patterns = [
            // HTML tags
            '/<[^>]+>/s',
            // URLs
            '/https?:\/\/[^\s<>"\'`)]+/iu',
            // Emails
            '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/u',
            // Phone numbers
            '/(?:\+?63|0)9\d{9}\b/u',
            '/\b\d{3}[-.\s]\d{3}[-.\s]\d{4}\b/u',
            // Legal & Ordinance references (e.g. Ordinance No. 25-2026, Resolution No. 04-2024, RA 7160)
            '/\b(?:Ordinance|Resolution|Republic\s+Act|RA|Executive\s+Order|EO)\s+(?:No\.\s*|#\s*)?[A-Za-z0-9\-\/.]+\b/iu',
            // Official entity & facility names
            '/\bBarangay\s+[A-Z][a-z0-9]+(?:\s+[A-Z][a-z0-9]+)*\b/u',
            '/\b(?:Barangay\s+Hall|Rural\s+Health\s+Unit|RHU|DSWD|DOH|PNP|BFP|NDRRMC|MDRRMO|LGU|Sangguniang\s+Barangay)\b/iu',
            // Dates (e.g. September 30, 2026 or Sept 30, 2026 or September 30)
            '/\b(?:January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)\.?\s+\d{1,2}(?:st|nd|rd|th)?,?\s+\d{4}\b/iu',
            '/\b(?:January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)\.?\s+\d{1,2}(?:st|nd|rd|th)?\b/iu',
            // Times (e.g. 8:00 AM, 8:00am, 8 AM, 8am)
            '/\b\d{1,2}(?::\d{2})?\s*(?:AM|PM|am|pm)\b/u',
            // Currency (e.g. ₱500, Php 500, PHP 500, ₱ 500)
            '/(?:₱|Php|PHP)\s*\d+(?:,\d{3})*(?:\.\d{1,2})?/iu',
            '/\b\d+(?:,\d{3})*(?:\.\d{1,2})?\s*(?:pesos|pesos?|PHP)\b/iu',
            // Codes & hashtags (e.g. EVT-2026-001, #StaySafe)
            '/\b[A-Z]{2,5}-\d{3,}\b/u',
            '/#[a-zA-Z0-9_]+/u',
            // String placeholders (e.g. :when, :email, :n, :name)
            '/(?<!\w):[a-zA-Z_][a-zA-Z0-9_]*/u',
        ];

        $masked = $text;
        foreach ($patterns as $pattern) {
            $masked = preg_replace_callback($pattern, function ($matches) use (&$map, &$index) {
                $matchedText = $matches[0];
                $token = "[[PROTECTED_{$index}]]";
                $map[$token] = $matchedText;
                $index++;
                return $token;
            }, $masked);
        }

        return [$masked, $map];
    }

    /**
     * Restore protected entities from the map.
     */
    public function unmaskProtectedEntities(string $text, array $map): string
    {
        if (empty($map)) {
            return $text;
        }
        return strtr($text, $map);
    }

    /**
     * Core hybrid translation logic.
     */
    private function processHybridTranslation(string $text, string $sourceLang): array
    {
        $manoboDictionary = $this->loadManoboIndex();
        $paragraphs = preg_split('/(\r\n|\r|\n)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        $translatedSegments = [];
        $totalManoboMatches = 0;
        $totalBisayaFallbacks = 0;
        $provenance = [];

        foreach ($paragraphs as $para) {
            if ($para === "\r\n" || $para === "\n" || $para === "\r" || trim($para) === '') {
                $translatedSegments[] = $para;
                continue;
            }

            // Segment sentences inside paragraph
            $sentenceParts = preg_split('/([.!?]+[\s]+)/u', $para, -1, PREG_SPLIT_DELIM_CAPTURE);
            $paraTranslated = '';

            for ($i = 0; $i < count($sentenceParts); $i++) {
                $segment = $sentenceParts[$i];
                if ($segment === '') continue;

                // Delimiter / punctuation
                if ($i % 2 === 1) {
                    $paraTranslated .= $segment;
                    continue;
                }

                // Process sentence for hybrid translation
                $sentenceResult = $this->translateSentence($segment, $sourceLang, $manoboDictionary);
                $paraTranslated .= $sentenceResult['text'];
                $totalManoboMatches += $sentenceResult['manoboMatches'];
                $totalBisayaFallbacks += $sentenceResult['bisayaFallbacks'];

                foreach ($sentenceResult['provenance'] as $p) {
                    $provenance[] = $p;
                }
            }

            $translatedSegments[] = $paraTranslated;
        }

        return [
            'translation'     => implode('', $translatedSegments),
            'manoboMatches'   => $totalManoboMatches,
            'bisayaFallbacks' => $totalBisayaFallbacks,
            'provenance'      => $provenance,
        ];
    }

    /**
     * Translate a single sentence using hybrid Manobo-first + Bisaya fallback.
     */
    private function translateSentence(string $sentence, string $sourceLang, array $manoboIndex): array
    {
        $trimmedSentence = trim($sentence);

        // 1. Check if the ENTIRE sentence has an exact/normalized approved Manobo phrase match
        $normalizedFull = $this->normalise($trimmedSentence);
        if (isset($manoboIndex[$normalizedFull])) {
            $entry = $manoboIndex[$normalizedFull];
            if ($this->isContextCompatible($entry, $trimmedSentence, 0)) {
                $manoboText = $this->applyCase($trimmedSentence, $entry['manobo']);
                // Keep any leading/trailing whitespace from the original sentence
                $leading = '';
                $trailing = '';
                if (preg_match('/^(\s+)/u', $sentence, $m)) $leading = $m[1];
                if (preg_match('/(\s+)$/u', $sentence, $m)) $trailing = $m[1];

                return [
                    'text'            => $leading . $manoboText . $trailing,
                    'manoboMatches'   => 1,
                    'bisayaFallbacks' => 0,
                    'provenance'      => [[
                        'text'       => $trimmedSentence,
                        'translated' => $manoboText,
                        'source'     => 'manobo',
                        'entry_id'   => $entry['id'] ?? null,
                    ]],
                ];
            }
        }

        // 2. Tokenize sentence into word tokens and delimiter tokens
        // Keeping [[PROTECTED_X]] intact as single tokens
        $parts = preg_split('/(\[\[PROTECTED_\d+\]\]|[^\p{L}\p{N}\x27\x{2019}\-\[\]]+)/u', $sentence, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$sentence];

        $tokens = [];
        foreach ($parts as $p) {
            if ($p !== '') {
                $isProtected = (bool) preg_match('/^\[\[PROTECTED_\d+\]\]$/', $p);
                $isWord = !$isProtected && (bool) preg_match('/[\p{L}\p{N}]/u', $p);
                $tokens[] = [
                    'text'        => $p,
                    'is_word'     => $isWord,
                    'is_protected'=> $isProtected,
                ];
            }
        }

        // 3. Scan with longest-matching approved Manobo phrase window
        $spans = [];
        $idx = 0;
        $count = count($tokens);
        $maxWindow = 8;

        while ($idx < $count) {
            $token = $tokens[$idx];

            if (!$token['is_word']) {
                $spans[] = [
                    'type'       => $token['is_protected'] ? 'protected' : 'delimiter',
                    'text'       => $token['text'],
                    'translated' => $token['text'],
                    'entry'      => null,
                ];
                $idx++;
                continue;
            }

            // Try longest window of words
            $matched = false;
            $bestLen = 0;
            $bestEntry = null;

            for ($w = $maxWindow; $w >= 1; $w--) {
                $lookaheadTokens = [];
                $tempIdx = $idx;
                $wordsCollected = 0;

                while ($tempIdx < $count && $wordsCollected < $w) {
                    if ($tokens[$tempIdx]['is_protected']) {
                        break; // cannot cross protected entity
                    }
                    if ($tokens[$tempIdx]['is_word']) {
                        $wordsCollected++;
                    }
                    $lookaheadTokens[] = $tokens[$tempIdx]['text'];
                    $tempIdx++;
                }

                if ($wordsCollected < $w) {
                    continue;
                }

                $combinedText = implode('', $lookaheadTokens);
                $normalizedKey = $this->normalise($combinedText);

                if (isset($manoboIndex[$normalizedKey])) {
                    $candidateEntry = $manoboIndex[$normalizedKey];
                    if ($this->isContextCompatible($candidateEntry, $combinedText, $idx, $tokens)) {
                        $matched = true;
                        $bestLen = count($lookaheadTokens);
                        $bestEntry = $candidateEntry;
                        break;
                    }
                }
            }

            if ($matched && $bestEntry !== null) {
                $consumedText = '';
                for ($k = 0; $k < $bestLen; $k++) {
                    $consumedText .= $tokens[$idx + $k]['text'];
                }
                $translatedManobo = $this->applyCase($consumedText, $bestEntry['manobo']);
                $spans[] = [
                    'type'       => 'manobo',
                    'text'       => $consumedText,
                    'translated' => $translatedManobo,
                    'entry'      => $bestEntry,
                ];
                $idx += $bestLen;
            } else {
                // Check if this token connects to an approved Manobo entry before treating as unmatched
                $connected = $this->stemAndFindManobo($token['text'], $manoboIndex, $sourceLang);
                if ($connected !== null && $this->isContextCompatible($connected['entry'], $token['text'], $idx, $tokens)) {
                    $translatedManobo = $this->applyCase($token['text'], $connected['replacement']);
                    $spans[] = [
                        'type'       => 'manobo',
                        'text'       => $token['text'],
                        'translated' => $translatedManobo,
                        'entry'      => $connected['entry'],
                    ];
                    $idx++;
                } else {
                    // Single unmatched word token
                    $spans[] = [
                        'type'       => 'unmatched',
                        'text'       => $token['text'],
                        'translated' => $token['text'],
                        'entry'      => null,
                    ];
                    $idx++;
                }
            }
        }

        // 4. Assemble translated spans while strictly preserving delimiters and spacing
        $assembledText = '';
        $manoboMatches = 0;
        $bisayaFallbacks = 0;
        $provenance = [];

        $sIdx = 0;
        $spanCount = count($spans);

        while ($sIdx < $spanCount) {
            $span = $spans[$sIdx];

            if ($span['type'] === 'manobo') {
                $assembledText .= $span['translated'];
                $manoboMatches++;
                $provenance[] = [
                    'text'       => $span['text'],
                    'translated' => $span['translated'],
                    'source'     => 'manobo',
                    'entry_id'   => $span['entry']['id'] ?? null,
                ];
                $sIdx++;
            } elseif ($span['type'] === 'protected') {
                $assembledText .= $span['translated'];
                $provenance[] = [
                    'text'       => $span['text'],
                    'translated' => $span['translated'],
                    'source'     => 'protected',
                ];
                $sIdx++;
            } elseif ($span['type'] === 'delimiter') {
                $assembledText .= $span['translated'];
                $sIdx++;
            } elseif ($span['type'] === 'unmatched') {
                // Collect contiguous run of unmatched words
                $unmatchedRunWords = [];
                $unmatchedRunTokens = [];

                while ($sIdx < $spanCount && $spans[$sIdx]['type'] === 'unmatched') {
                    $unmatchedRunWords[] = $spans[$sIdx]['text'];
                    $unmatchedRunTokens[] = $spans[$sIdx]['text'];
                    $sIdx++;
                    // If next is a space delimiter followed by another unmatched word, include the space in this phrase
                    if ($sIdx < $spanCount && $spans[$sIdx]['type'] === 'delimiter' 
                        && preg_match('/^\s+$/u', $spans[$sIdx]['text']) 
                        && ($sIdx + 1 < $spanCount && $spans[$sIdx + 1]['type'] === 'unmatched')) {
                        $unmatchedRunTokens[] = $spans[$sIdx]['text'];
                        $sIdx++;
                    }
                }

                $unmatchedRaw = implode('', $unmatchedRunTokens);
                $unmatchedCore = trim($unmatchedRaw);

                if ($unmatchedCore !== '') {
                    $bisayaTranslation = $this->translateToBisaya($unmatchedCore, $sourceLang);
                    $unresolvedWords   = $this->lastUnresolved;
                    
                    // Prioritize Manobo: Scan Bisaya translation to ensure NO word connected to Manobo is left in Bisaya/Cebuano
                    // Words the Bisaya step could not translate are tagged 'unresolved', not 'bisaya'.
                    $refined = $this->refineBisayaWithManobo($bisayaTranslation, $manoboIndex, $unresolvedWords);
                    $finalSegment = $refined['text'];
                    $manoboMatches += $refined['manoboCount'];
                    $bisayaFallbacks += $refined['bisayaCount'];

                    // Match spacing from original
                    $prefix = '';
                    $suffix = '';
                    if (preg_match('/^(\s+)/u', $unmatchedRaw, $m)) $prefix = $m[1];
                    if (preg_match('/(\s+)$/u', $unmatchedRaw, $m)) $suffix = $m[1];

                    $assembledText .= $prefix . $finalSegment . $suffix;

                    foreach ($refined['provenance'] as $rp) {
                        $provenance[] = $rp;
                    }

                    // Queue every gap: the whole run (with its Bisaya fallback when
                    // there was one) and each word nothing could translate.
                    $this->trackMissingConcept($unmatchedCore, $sourceLang, $unresolvedWords ? null : $bisayaTranslation);
                    foreach (array_keys($unresolvedWords) as $gapWord) {
                        if ($this->normalise($unmatchedCore) !== $gapWord) {
                            $this->trackMissingConcept((string) $gapWord, $sourceLang, null);
                        }
                    }
                } else {
                    $assembledText .= $unmatchedRaw;
                }
            }
        }

        return [
            'text'            => $assembledText,
            'manoboMatches'   => $manoboMatches,
            'bisayaFallbacks' => $bisayaFallbacks,
            'provenance'      => $provenance,
        ];
    }

    /**
     * Check if a dictionary entry is context-compatible with its position and surrounding tokens.
     */
    private function isContextCompatible(array $entry, string $matchedSpan, int $tokenIdx = 0, array $tokens = []): bool
    {
        $notes = mb_strtolower((string)($entry['notes'] ?? ''));
        if (str_contains($notes, 'unclear in pdf')) {
            return false;
        }

        $normMatch = $this->normalise($matchedSpan);

        // Ambiguity 1: "center" in "health center" / "evacuation center"
        if ($normMatch === 'center') {
            if ($tokenIdx > 0 && isset($tokens[$tokenIdx - 1])) {
                $prevText = mb_strtolower(trim($tokens[$tokenIdx - 1]['text'] ?? ''));
                if (in_array($prevText, ['health', 'evacuation', 'community', 'day care', 'senior'], true)) {
                    return false;
                }
            }
            if ($tokenIdx > 1 && isset($tokens[$tokenIdx - 2])) {
                $prevPrev = mb_strtolower(trim($tokens[$tokenIdx - 2]['text'] ?? ''));
                if (in_array($prevPrev, ['health', 'evacuation', 'community', 'day care', 'senior'], true)) {
                    return false;
                }
            }
        }

        // Ambiguity 2: "will" (auxiliary verb) vs "will" (noun: kabubut-on / gusto)
        if ($normMatch === 'will') {
            // Almost always auxiliary verb in notices ("will take", "will open", "will attend")
            return false;
        }

        // Ambiguity 3: "there" (existential in "there is / will be") vs "diya" (doon / spatial direction)
        if ($normMatch === 'there') {
            // Look ahead for "is", "are", "was", "were", "will", "have"
            for ($k = 1; $k <= 3; $k++) {
                if (isset($tokens[$tokenIdx + $k])) {
                    $nextWord = mb_strtolower(trim($tokens[$tokenIdx + $k]['text']));
                    if (in_array($nextWord, ['is', 'are', 'was', 'were', 'will', 'have', 'has'], true)) {
                        return false;
                    }
                }
            }
        }

        // Ambiguity 4: "take" in "take effect", "take care", "take place"
        if ($normMatch === 'take') {
            for ($k = 1; $k <= 3; $k++) {
                if (isset($tokens[$tokenIdx + $k])) {
                    $nextWord = mb_strtolower(trim($tokens[$tokenIdx + $k]['text']));
                    if (in_array($nextWord, ['effect', 'care', 'place', 'part', 'action'], true)) {
                        return false;
                    }
                }
            }
        }

        // Ambiguity 5: "light" (weight vs illumination)
        if ($normMatch === 'light') {
            // Default to weight only if "weight" / "heavy" mentioned in surrounding tokens
            $hasWeightContext = false;
            foreach ($tokens as $t) {
                $tw = mb_strtolower(trim($t['text']));
                if (in_array($tw, ['weight', 'heavy', 'mabigat', 'magaan', 'carry', 'load'], true)) {
                    $hasWeightContext = true;
                    break;
                }
            }
            if (!$hasWeightContext) {
                return false;
            }
        }

        return true;
    }

    /**
     * Fallback translation to natural Bisaya/Cebuano.
     */
    public function translateToBisaya(string $text, string $sourceLang): string
    {
        $this->lastUnresolved = [];
        $trimmed = trim($text);
        if ($trimmed === '') {
            return $text;
        }

        // 1. Check local Bisaya dictionary first for single words / short terms
        $norm = $this->normalise($trimmed);
        $bisayaLocal = $this->lookupLocalBisaya($norm, $sourceLang);
        if ($bisayaLocal !== null) {
            return $this->applyCase($text, $bisayaLocal);
        }

        // 2. Machine translation fallback via MyMemory. Machine translation
        //    echoes words it does not know, so any source word that comes back
        //    unchanged (and is not also a Bisaya word) is still untranslated.
        $targetPairLang = $sourceLang === 'en' ? 'en' : 'tl';
        $ceb = $this->queryMyMemoryBisaya($trimmed, $targetPairLang);
        if ($ceb !== null && $ceb !== '') {
            $this->lastUnresolved = $this->echoedSourceWords($trimmed, $ceb);
            return $this->applyCase($text, $ceb);
        }

        // 3. Word-by-word local Bisaya dictionary lookup if phrase failed
        $words = preg_split('/([^\p{L}\p{N}\x27\-]+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($words !== false && count($words) > 1) {
            $out = '';
            $anyWordFound = false;
            foreach ($words as $i => $part) {
                if ($i % 2 === 1 || $part === '') {
                    $out .= $part;
                    continue;
                }
                $wordNorm = $this->normalise($part);
                $wBisaya = $this->lookupLocalBisaya($wordNorm, $sourceLang);
                if ($wBisaya !== null) {
                    $out .= $this->applyCase($part, $wBisaya);
                    $anyWordFound = true;
                } else {
                    $out .= $part;
                    if ($this->isGapWord($part, $i === 0)) {
                        $this->lastUnresolved[$wordNorm] = true;
                    }
                }
            }
            if ($anyWordFound) {
                return $out;
            }
        }

        // Nothing translated: every real word of the run is a gap.
        $this->lastUnresolved = [];
        foreach (preg_split('/[^\p{L}\p{N}\x27\-]+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $k => $word) {
            if ($this->isGapWord($word, $k === 0)) {
                $this->lastUnresolved[$this->normalise($word)] = true;
            }
        }
        return $text;
    }

    /**
     * Source words that survived machine translation unchanged.
     *
     * @return array<string,true>
     */
    private function echoedSourceWords(string $source, string $translated): array
    {
        $srcWords = [];
        foreach (preg_split('/[^\p{L}\p{N}\x27\-]+/u', $source, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $k => $word) {
            if ($this->isGapWord($word, $k === 0)) {
                $srcWords[$this->normalise($word)] = true;
            }
        }
        $echoed = [];
        foreach (preg_split('/[^\p{L}\p{N}\x27\-]+/u', $translated, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $n = $this->normalise($word);
            if (isset($srcWords[$n])) {
                $echoed[$n] = true;
            }
        }
        return $echoed;
    }

    /**
     * Whether a word left in place counts as an untranslated gap. Numbers,
     * capitalised names inside a sentence, and words that are also Bisaya
     * (or shared function words like "sa", "ang") do not.
     */
    private function isGapWord(string $word, bool $runInitial): bool
    {
        if (!preg_match('/\p{L}/u', $word) || mb_strlen($word) < 2) {
            return false;
        }
        $norm = $this->normalise($word);
        if (in_array($norm, self::SHARED_FUNCTION_WORDS, true)) {
            return false;
        }
        if (!$runInitial && preg_match('/^\p{Lu}/u', $word)) {
            return false;   // a name: "Bayogo", "Surigao"
        }
        if (preg_match('/^\p{Lu}{2,}$/u', $word)) {
            return false;   // an acronym: "PAGASA"
        }
        $this->lookupLocalBisaya('', 'ceb');   // ensure the Bisaya sets are loaded
        return !isset(self::$bisayaWords[$norm]);
    }

    /**
     * Lookup a term in the local bisaya_dictionary.
     */
    private function lookupLocalBisaya(string $normalizedTerm, string $sourceLang): ?string
    {
        static $englishFunctional = [
            'in'   => 'sa',
            'at'   => 'sa',
            'on'   => 'sa',
            'to'   => 'sa',
            'into' => 'sa',
            'from' => 'gikan sa',
            'with' => 'uban sa',
            'for'  => 'para sa',
            'of'   => 'sa',
            'the'  => 'ang',
            'a'    => 'usa ka',
            'an'   => 'usa ka',
            'and'  => 'ug',
            'or'   => 'o',
            'is'   => 'mao ang',
            'are'  => 'ang',
            'be'   => 'mahimo',
        ];

        static $filipinoFunctional = [
            'sa'    => 'sa',
            'ang'   => 'ang',
            'mga'   => 'mga',
            'para'  => 'para sa',
            'at'    => 'ug',
            'ay'    => 'kay',
            'nang'  => 'sa',
            'ng'    => 'sa',
            'kung'  => 'kung',
            'o'     => 'o',
        ];

        if ($sourceLang === 'en' && isset($englishFunctional[$normalizedTerm])) {
            return $englishFunctional[$normalizedTerm];
        }
        if (($sourceLang === 'tl' || $sourceLang === 'fil') && isset($filipinoFunctional[$normalizedTerm])) {
            return $filipinoFunctional[$normalizedTerm];
        }

        if (self::$bisayaIndex === null) {
            $map = [];
            self::$bisayaWords = [];
            try {
                $stmt = $this->db->query("SELECT bisaya, tagalog, english FROM bisaya_dictionary WHERE deleted_at IS NULL LIMIT 2500");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $b = trim((string)$row['bisaya']);
                    if ($b === '') continue;
                    foreach (preg_split('/\s+/u', $this->normalise($b)) ?: [] as $bw) {
                        if ($bw !== '') {
                            self::$bisayaWords[$bw] = true;
                        }
                    }

                    foreach (['tagalog', 'english'] as $f) {
                        $val = (string)($row[$f] ?? '');
                        foreach (preg_split('~[,/]~u', $val) ?: [] as $s) {
                            $k = $this->normalise($s);
                            if ($k !== '' && !isset($map[$k])) {
                                $map[$k] = $b;
                            }
                            if ($f === 'tagalog') {
                                foreach ($this->stemTagalogAffixes($k) as $st) {
                                    if ($st !== '' && !isset($map[$st])) {
                                        $map[$st] = $b;
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                error_log('[ManoboHybridTranslator] lookupLocalBisaya failed: ' . $e->getMessage());
            }
            self::$bisayaIndex = $map;
        }

        if (isset(self::$bisayaIndex[$normalizedTerm])) {
            return self::$bisayaIndex[$normalizedTerm];
        }

        // Stemmed lookup in Bisaya index
        $stems = $this->stemTagalogAffixes($normalizedTerm);
        foreach ($stems as $st) {
            if (isset(self::$bisayaIndex[$st])) {
                return self::$bisayaIndex[$st];
            }
        }

        return null;
    }

    /**
     * Query MyMemory for Cebuano (Bisaya) translation.
     */
    private function queryMyMemoryBisaya(string $text, string $sourceCode): ?string
    {
        if (mb_strlen($text) < 2 || is_numeric($text)) {
            return null;
        }
        if (self::$machineDeadline !== null && microtime(true) > self::$machineDeadline) {
            return null;   // time budget spent: local dictionaries only
        }

        $langPair = ($sourceCode === 'en' ? 'en' : 'tl') . '|ceb';

        try {
            $client = new \GuzzleHttp\Client([
                'timeout'         => 2,
                'connect_timeout' => 2,
                'http_errors'     => false,
                'headers'         => ['User-Agent' => 'BarangGabay/1.0 (LGU Hybrid Translator)'],
            ]);

            $res = $client->get('https://api.mymemory.translated.net/get', [
                'query' => ['q' => $text, 'langpair' => $langPair, 'mt' => '1'],
            ]);

            if ($res->getStatusCode() !== 200) {
                return null;
            }

            $data = json_decode((string)$res->getBody(), true);
            if (!is_array($data) || (int)($data['responseStatus'] ?? 0) !== 200) {
                return null;
            }

            $translated = trim((string)($data['responseData']['translatedText'] ?? ''));
            if ($translated === '') {
                return null;
            }

            $upper = mb_strtoupper($translated);
            foreach (['QUERY LENGTH LIMIT', 'MYMEMORY WARNING', 'INVALID LANGUAGE', 'DAILY LIMIT'] as $marker) {
                if (str_contains($upper, $marker)) {
                    return null;
                }
            }

            // Reject non-Latin characters (Cyrillic, Russian, Arabic, Chinese, etc.)
            if (preg_match('/[\p{Cyrillic}\p{Arabic}\p{Han}\p{Devanagari}]/u', $translated)) {
                return null;
            }

            // Anti-spam guard: reject crowd-sourced translation memory spam
            if (mb_strlen($text) <= 6 && mb_strlen($translated) > 18) {
                return null;
            }
            if (mb_strlen($translated) > (mb_strlen($text) * 4 + 20)) {
                return null;
            }
            if (str_contains($translated, "\n") && !str_contains($text, "\n")) {
                return null;
            }

            return $translated;
        } catch (\Throwable $e) {
            error_log('[ManoboHybridTranslator] MyMemory Ceb failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Track missing concepts in `manobo_missing_concepts` table.
     */
    public function trackMissingConcept(string $concept, string $sourceLang, ?string $bisayaFallback = null): void
    {
        $clean = trim(mb_strtolower($concept));
        if (mb_strlen($clean) < 3 || is_numeric($clean) || str_contains($clean, '[[protected')) {
            return;
        }

        try {
            $stmt = $this->db->prepare("SELECT id, usage_count FROM manobo_missing_concepts WHERE concept = ? LIMIT 1");
            $stmt->execute([$clean]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $upd = $this->db->prepare("UPDATE manobo_missing_concepts SET usage_count = usage_count + 1, last_seen_at = NOW(), bisaya_fallback = COALESCE(?, bisaya_fallback) WHERE id = ?");
                $upd->execute([$bisayaFallback, $existing['id']]);
            } else {
                $ins = $this->db->prepare("INSERT INTO manobo_missing_concepts (concept, source_lang, bisaya_fallback, usage_count, first_seen_at, last_seen_at, review_status) VALUES (?, ?, ?, 1, NOW(), NOW(), 'pending')");
                $ins->execute([$clean, $sourceLang, $bisayaFallback]);
            }
        } catch (\Throwable $e) {
            error_log('[ManoboHybridTranslator] trackMissingConcept failed: ' . $e->getMessage());
        }
    }

    /**
     * Load approved Manobo entries indexed by normalized Tagalog, English, Bisaya, and aliases.
     */
    public function loadManoboIndex(): array
    {
        $version = $this->getDictionaryVersion();
        if (self::$phraseCache !== null && self::$cachedVersion === $version) {
            return self::$phraseCache;
        }

        $index = [];

        try {
            $sql = "SELECT id, manobo, tagalog, english, bisaya, aliases, type, priority, review_status, notes 
                    FROM manobo_dictionary 
                    WHERE deleted_at IS NULL 
                      AND archived_at IS NULL 
                      AND (review_status = 'approved' OR review_status = 'pending_review' OR review_status IS NULL)
                    ORDER BY priority DESC, id ASC";

            $stmt = $this->db->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $manobo = trim((string)($row['manobo'] ?? ''));
                if ($manobo === '') continue;

                $searchKeys = [];

                // 1. Tagalog senses & alternatives
                $tagalog = (string)($row['tagalog'] ?? '');
                foreach (preg_split('~[,/]~u', $tagalog) ?: [] as $sense) {
                    $k = $this->normalise($sense);
                    if ($k !== '') $searchKeys[] = $k;
                }

                // 2. English senses & alternatives
                $english = (string)($row['english'] ?? '');
                foreach (preg_split('~[,/]~u', $english) ?: [] as $sense) {
                    $k = $this->normalise($sense);
                    if ($k !== '') $searchKeys[] = $k;
                }

                // 3. Bisaya senses & alternatives (enables matching concepts that connect via Bisaya)
                $bisaya = (string)($row['bisaya'] ?? '');
                foreach (preg_split('~[,/]~u', $bisaya) ?: [] as $sense) {
                    $k = $this->normalise($sense);
                    if ($k !== '') $searchKeys[] = $k;
                }

                // 4. Aliases
                $aliases = (string)($row['aliases'] ?? '');
                if ($aliases !== '') {
                    $decoded = json_decode($aliases, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $aliasGroup) {
                            if (is_array($aliasGroup)) {
                                foreach ($aliasGroup as $a) {
                                    $k = $this->normalise((string)$a);
                                    if ($k !== '') $searchKeys[] = $k;
                                }
                            } elseif (is_string($aliasGroup)) {
                                $k = $this->normalise($aliasGroup);
                                if ($k !== '') $searchKeys[] = $k;
                            }
                        }
                    } else {
                        foreach (preg_split('~[,/]~u', $aliases) ?: [] as $a) {
                            $k = $this->normalise($a);
                            if ($k !== '') $searchKeys[] = $k;
                        }
                    }
                }

                foreach (array_unique($searchKeys) as $key) {
                    if (!isset($index[$key])) {
                        $index[$key] = [
                            'id'            => $row['id'],
                            'manobo'        => $manobo,
                            'english'       => $row['english'],
                            'tagalog'       => $row['tagalog'],
                            'bisaya'        => $row['bisaya'],
                            'notes'         => $row['notes'],
                            'review_status' => $row['review_status'],
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[ManoboHybridTranslator] loadManoboIndex failed: ' . $e->getMessage());
        }

        self::$phraseCache = $index;
        self::$cachedVersion = $version;

        return self::$phraseCache;
    }

    /**
     * Retrieve cached translation from `translation_cache`.
     */
    private function getCachedTranslation(string $sourceHash, int $dictVersion): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT translated_text, provenance_json, manobo_matches, bisaya_fallbacks FROM translation_cache WHERE source_text_hash = ? AND dictionary_version = ? LIMIT 1");
            $stmt->execute([$sourceHash, $dictVersion]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                return [
                    'success'         => true,
                    'translation'     => (string)$row['translated_text'],
                    'language'        => 'mn',
                    'source_lang'     => 'cached',
                    'manoboMatches'   => (int)$row['manobo_matches'],
                    'bisayaFallbacks' => (int)$row['bisaya_fallbacks'],
                    'provenance'      => json_decode((string)$row['provenance_json'], true) ?: [],
                    'cached'          => true,
                ];
            }
        } catch (\Throwable $e) {
            error_log('[ManoboHybridTranslator] getCachedTranslation failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Store translated result in `translation_cache`.
     */
    private function storeCachedTranslation(
        string $sourceHash,
        string $sourceLang,
        string $targetLang,
        int $dictVersion,
        string $translatedText,
        array $provenance,
        int $manoboMatches,
        int $bisayaFallbacks
    ): void {
        try {
            $sql = "INSERT INTO translation_cache (source_text_hash, source_lang, target_lang, dictionary_version, translated_text, provenance_json, manobo_matches, bisaya_fallbacks, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE translated_text = VALUES(translated_text), provenance_json = VALUES(provenance_json), manobo_matches = VALUES(manobo_matches), bisaya_fallbacks = VALUES(bisaya_fallbacks), updated_at = NOW()";

            if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
                $sql = "INSERT INTO translation_cache (source_text_hash, source_lang, target_lang, dictionary_version, translated_text, provenance_json, manobo_matches, bisaya_fallbacks, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                        ON CONFLICT (source_text_hash, source_lang, target_lang, dictionary_version) 
                        DO UPDATE SET translated_text = EXCLUDED.translated_text, provenance_json = EXCLUDED.provenance_json, manobo_matches = EXCLUDED.manobo_matches, bisaya_fallbacks = EXCLUDED.bisaya_fallbacks, updated_at = NOW()";
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $sourceHash,
                $sourceLang,
                $targetLang,
                $dictVersion,
                $translatedText,
                json_encode($provenance, JSON_UNESCAPED_UNICODE),
                $manoboMatches,
                $bisayaFallbacks,
            ]);
        } catch (\Throwable $e) {
            error_log('[ManoboHybridTranslator] storeCachedTranslation failed: ' . $e->getMessage());
        }
    }

    /**
     * Normalise text for dictionary matching.
     */
    public function normalise(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = preg_replace('/\s*\([^)]*\)/u', '', $text) ?? $text;
        $text = trim($text, " \t\n\r\0\x0B.,;:!?\"'“”‘’()[]{}");
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return $text;
    }

    /**
     * Match case of replacement to source text.
     */
    public function applyCase(string $source, string $replacement): string
    {
        $trimmedSource = trim($source);
        if ($trimmedSource === '') {
            return $replacement;
        }

        if (mb_strtoupper($trimmedSource, 'UTF-8') === $trimmedSource && preg_match('/[\p{L}]/u', $trimmedSource)) {
            return mb_strtoupper($replacement, 'UTF-8');
        }

        $first = mb_substr($trimmedSource, 0, 1, 'UTF-8');
        if (mb_strtoupper($first, 'UTF-8') === $first && mb_strtolower($first, 'UTF-8') !== $first) {
            return mb_strtoupper(mb_substr($replacement, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($replacement, 1, null, 'UTF-8');
        }

        return $replacement;
    }

    /**
     * Check if a word is morphologically or semantically connected to an approved Manobo entry
     * BEFORE falling back to Bisaya/Cebuano.
     */
    public function stemAndFindManobo(string $wordText, array $manoboIndex, string $sourceLang = 'fil'): ?array
    {
        $norm = $this->normalise($wordText);
        if ($norm === '' || mb_strlen($norm) < 2) {
            return null;
        }

        // 1. Direct normalized check
        if (isset($manoboIndex[$norm])) {
            return [
                'entry'       => $manoboIndex[$norm],
                'replacement' => $manoboIndex[$norm]['manobo'],
            ];
        }

        // 2. Tagalog / Bisaya Linker -ng (e.g. walang -> wada no, magandang -> magwapa no, maraming -> madaog no)
        if (str_ends_with($norm, 'ng') && mb_strlen($norm) > 3) {
            $base = mb_substr($norm, 0, -2, 'UTF-8');
            if (isset($manoboIndex[$base])) {
                $entry = $manoboIndex[$base];
                return [
                    'entry'       => $entry,
                    'replacement' => $entry['manobo'] . ' no',
                ];
            }
        }

        // 3. English plural -s, -es, -ies or participle -ing, -ed
        $singular = $this->stemEnglishSingular($norm);
        if ($singular !== null && isset($manoboIndex[$singular])) {
            $entry = $manoboIndex[$singular];
            return [
                'entry'       => $entry,
                'replacement' => $entry['manobo'],
            ];
        }

        // 4. Tagalog verb affixes: -um-, mag-, nag-, pag-, ma-, ka-, etc.
        $tagalogStems = $this->stemTagalogAffixes($norm);
        foreach ($tagalogStems as $stem) {
            if (isset($manoboIndex[$stem])) {
                $entry = $manoboIndex[$stem];
                return [
                    'entry'       => $entry,
                    'replacement' => $entry['manobo'],
                ];
            }
        }

        // 5. Bisaya Bridge: If the term has a known Bisaya translation, check if that Bisaya word connects to Manobo
        $bisayaWord = $this->lookupLocalBisaya($norm, $sourceLang);
        if ($bisayaWord !== null) {
            $normBisaya = $this->normalise($bisayaWord);
            if (isset($manoboIndex[$normBisaya])) {
                $entry = $manoboIndex[$normBisaya];
                return [
                    'entry'       => $entry,
                    'replacement' => $entry['manobo'],
                ];
            }
            foreach ($this->stemTagalogAffixes($normBisaya) as $bStem) {
                if (isset($manoboIndex[$bStem])) {
                    $entry = $manoboIndex[$bStem];
                    return [
                        'entry'       => $entry,
                        'replacement' => $entry['manobo'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Decompose Tagalog affixes, infixes, and reduplications to identify the base root.
     */
    public function stemTagalogAffixes(string $word): array
    {
        $stems = [];
        $len = mb_strlen($word, 'UTF-8');

        // Linker -ng (walang -> wala, magandang -> maganda)
        if (str_ends_with($word, 'ng') && $len > 3) {
            $stems[] = mb_substr($word, 0, -2, 'UTF-8');
        }

        // Initial CV or V reduplication for future/aspect (e.g. lilikas -> likas, tatakbo -> takbo, kakain -> kain, aalis -> alis)
        if (preg_match('/^([b-df-hj-np-tv-z])([aeiou])\1\2(.*)$/u', $word, $rm)) {
            $stems[] = $rm[1] . $rm[2] . $rm[3];
        } elseif (preg_match('/^([aeiou])\1(.*)$/u', $word, $rm)) {
            $stems[] = $rm[1] . $rm[2];
        }

        // Infix -um- (kumain -> kain, pumasok -> pasok, lumakad -> lakad, tumakbo -> takbo)
        if (preg_match('/^([b-df-hj-np-tv-z])um([aeiou].*)$/u', $word, $m)) {
            $stems[] = $m[1] . $m[2];
            if (preg_match('/^([b-df-hj-np-tv-z])um[aeiou]\1([aeiou].*)$/u', $word, $m2)) {
                $stems[] = $m2[1] . $m2[2];
            }
        }

        // Infix -in- (niluto -> luto, ginawa -> gawa)
        if (preg_match('/^([b-df-hj-np-tv-z])in([aeiou].*)$/u', $word, $m)) {
            $stems[] = $m[1] . $m[2];
        }
        if (str_starts_with($word, 'ni') && $len > 4) {
            $stems[] = mb_substr($word, 2, null, 'UTF-8');
        }

        // Prefixes: nag-, mag-, pag-, um-, ma-, ka-
        foreach (['nagpa', 'magpa', 'ipag', 'nag', 'mag', 'pag', 'um', 'ma', 'ka'] as $pre) {
            if (str_starts_with($word, $pre) && $len > strlen($pre) + 2) {
                $rem = mb_substr($word, mb_strlen($pre, 'UTF-8'), null, 'UTF-8');
                $rem = ltrim($rem, '-');
                $stems[] = $rem;
                // Reduplication check (nag-iisip -> isip, magluluto -> luto)
                if (preg_match('/^([b-df-hj-np-tv-z]?[aeiou])\1(.*)$/u', $rem, $rm)) {
                    $stems[] = $rm[1] . $rm[2];
                }
            }
        }

        // Suffixes: -an, -in, -han, -hin
        foreach (['han', 'hin', 'an', 'in'] as $suf) {
            if (str_ends_with($word, $suf) && $len > strlen($suf) + 3) {
                $stems[] = mb_substr($word, 0, -mb_strlen($suf, 'UTF-8'), 'UTF-8');
            }
        }

        return array_unique(array_filter($stems));
    }

    /**
     * Reduce English inflected forms (plurals, participles) to base singular forms.
     */
    public function stemEnglishSingular(string $word): ?string
    {
        $length = mb_strlen($word, 'UTF-8');
        if ($length < 4) {
            return null;
        }

        if (str_ends_with($word, 'ies') && $length > 4) {
            return mb_substr($word, 0, -3, 'UTF-8') . 'y';
        }
        foreach (['sses', 'shes', 'ches', 'xes', 'zes'] as $ending) {
            if (str_ends_with($word, $ending)) {
                return mb_substr($word, 0, -2, 'UTF-8');
            }
        }
        foreach (['ss', 'us', 'is'] as $ending) {
            if (str_ends_with($word, $ending)) {
                return null;
            }
        }
        if (str_ends_with($word, 's')) {
            return mb_substr($word, 0, -1, 'UTF-8');
        }
        if (str_ends_with($word, 'ing') && $length > 5) {
            return mb_substr($word, 0, -3, 'UTF-8');
        }
        if (str_ends_with($word, 'ed') && $length > 4) {
            return mb_substr($word, 0, -2, 'UTF-8');
        }

        return null;
    }

    /**
     * Scan Bisaya fallback text and replace ANY word or phrase that connects to an approved
     * Manobo dictionary term with the Manobo term before displaying Bisaya.
     */
    public function refineBisayaWithManobo(string $bisayaText, array $manoboIndex, array $unresolved = []): array
    {
        $parts = preg_split('/([^\p{L}\p{N}\x27\-]+)/u', $bisayaText, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false || empty($parts)) {
            return [
                'text'        => $bisayaText,
                'manoboCount' => 0,
                'bisayaCount' => 1,
                'provenance'  => [['text' => $bisayaText, 'translated' => $bisayaText, 'source' => 'bisaya']],
            ];
        }

        $out = '';
        $manoboCount = 0;
        $bisayaCount = 0;
        $provenance = [];

        foreach ($parts as $i => $part) {
            // Delimiter or whitespace
            if ($i % 2 === 1 || $part === '') {
                $out .= $part;
                continue;
            }

            // Check if this Bisaya token connects to Manobo
            $norm = $this->normalise($part);
            $hit = null;

            if (isset($manoboIndex[$norm])) {
                $hit = $manoboIndex[$norm];
            } else {
                // Check Bisaya linker -y (e.g. walay -> wala -> wada)
                if (str_ends_with($norm, 'y') && mb_strlen($norm) > 3) {
                    $base = mb_substr($norm, 0, -1, 'UTF-8');
                    if (isset($manoboIndex[$base])) {
                        $hit = $manoboIndex[$base];
                    }
                }
                // Check Bisaya linker -g / -ng (e.g. karong -> karon -> kuntoon)
                if ($hit === null && str_ends_with($norm, 'ng') && mb_strlen($norm) > 3) {
                    $base = mb_substr($norm, 0, -2, 'UTF-8');
                    if (isset($manoboIndex[$base])) {
                        $hit = $manoboIndex[$base];
                    }
                }
                if ($hit === null) {
                    // Try general stemming
                    $connected = $this->stemAndFindManobo($part, $manoboIndex, 'ceb');
                    if ($connected !== null) {
                        $hit = $connected['entry'];
                    }
                }
            }

            if ($hit !== null && $this->isContextCompatible($hit, $part)) {
                $replacement = $this->applyCase($part, $hit['manobo']);
                $out .= $replacement;
                $manoboCount++;
                $provenance[] = [
                    'text'       => $part,
                    'translated' => $replacement,
                    'source'     => 'manobo',
                    'entry_id'   => $hit['id'] ?? null,
                ];
            } elseif (isset($unresolved[$norm])) {
                // Neither Manobo nor Bisaya: the source word is showing through.
                $out .= $part;
                $provenance[] = [
                    'text'       => $part,
                    'translated' => $part,
                    'source'     => 'unresolved',
                ];
            } else {
                $out .= $part;
                $bisayaCount++;
                $provenance[] = [
                    'text'       => $part,
                    'translated' => $part,
                    'source'     => 'bisaya',
                ];
            }
        }

        return [
            'text'        => $out,
            'manoboCount' => $manoboCount,
            'bisayaCount' => $bisayaCount > 0 ? 1 : 0,
            'provenance'  => $provenance,
        ];
    }
}

