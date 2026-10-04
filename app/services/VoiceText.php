<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The one text normaliser for the voice pipeline.
 *
 * Dictionary matching, missing-word detection, recording lookup, the Resident
 * Voice Reader and content scanning all call this, so "Madjow no masim!" typed
 * by a recorder and "madjow no  masim" inside an announcement land on the same
 * key. Two modules with two slightly different normalisers is how a word ends
 * up "recorded" on one screen and "missing" on another.
 *
 * Manobo spelling is kept: letters (including accented ones), digits, and
 * apostrophes / hyphens *inside* a word survive. Only case, surrounding
 * punctuation and whitespace runs are folded.
 */
final class VoiceText
{
    /**
     * Normalise a word or phrase to its lookup key.
     */
    public static function normalize(string $text): string
    {
        return implode(' ', self::tokens($text));
    }

    /**
     * Split text into normalised word tokens, in reading order.
     *
     * @return list<string>
     */
    public static function tokens(string $text): array
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        }
        // Curly apostrophes and the various dashes fold to their ASCII forms.
        $text = strtr($text, ["\u{2019}" => "'", "\u{2018}" => "'", "\u{02BC}" => "'", "\u{2010}" => '-', "\u{2011}" => '-']);
        // Words glued together when HTML was stripped ("ngayonLahat") are two words.
        $text = SpokenText::repairJoins($text);
        $text = mb_strtolower($text, 'UTF-8');

        // Everything that is not a letter, digit, apostrophe or hyphen is a
        // word break. Apostrophes/hyphens at a word's edge are trimmed below.
        $parts = preg_split("/[^\\p{L}\\p{M}\\p{N}'\\-]+/u", $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = [];
        foreach ($parts as $part) {
            $part = trim($part, "'-");
            if ($part !== '') {
                $tokens[] = $part;
            }
        }
        return $tokens;
    }

    /**
     * Whether a token is worth tracking as a vocabulary word: it must contain
     * a letter (numbers and stray symbols are not pronunciations to record).
     */
    public static function isWord(string $token): bool
    {
        return (bool) preg_match('/\p{L}/u', $token);
    }
}
