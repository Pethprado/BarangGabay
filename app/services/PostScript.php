<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Turns one content row into the script for one language.
 *
 * This is the single place that decides what a post sounds like. It used to
 * live inline in the three resident detail views, which was fine while the
 * reader spoke only the language on screen. It no longer is: the same script
 * now has to be produced from three different places that must agree exactly,
 * or the cached audio stops matching the text it claims to be —
 *
 *   the detail page,   building the browser-speech fallback for the reader;
 *   the generator,     synthesising an MP3 at publish time;
 *   the staff preview, playing back what residents will hear.
 *
 * It also answers a question the old code never had to ask: is there anything
 * in this language at all? A post with no English translation must offer no
 * English audio. Falling back to the Filipino text under an "English" label is
 * exactly the confusion the language switch exists to prevent, so
 * available=false is returned instead and the UI greys that language out.
 *
 * Everything here is pure: no API calls, no database writes, no session state.
 */
final class PostScript
{
    /** Which column holds each language's copy of a field. */
    private const SUFFIX = ['en' => '_en', 'fil' => '_fil', 'msm' => '_manobo'];

    /**
     * Build the script for one post in one language.
     *
     * @param string              $type   'announcement' | 'event' | 'ordinance'
     * @param array<string,mixed> $row    The content row, as the detail
     *                                    queries return it.
     * @param string              $locale 'en' | 'fil' | 'msm'
     *
     * @return array{
     *     available:bool,
     *     locale:string,
     *     title:string,
     *     chunks:list<array{say:string,find:string|null,kind:string}>,
     *     chars:int,
     *     truncated:bool,
     *     text:string,
     *     hash:string
     * }  text is everything spoken, joined — what gets synthesised and hashed.
     */
    public static function build(string $type, array $row, string $locale): array
    {
        $empty = [
            'available' => false,
            'locale'    => $locale,
            'title'     => '',
            'chunks'    => [],
            'chars'     => 0,
            'truncated' => false,
            'text'      => '',
            'hash'      => '',
        ];

        if (!isset(self::SUFFIX[$locale])) {
            return $empty;
        }

        $bodyField = $type === 'announcement' ? 'body' : 'description';
        $title     = self::inLanguage($row, 'title', $locale);
        $body      = self::inLanguage($row, $bodyField, $locale);

        // The source-language copy is the Quill HTML original; every
        // translation is stored as plain text.
        $isOriginal = $locale === (string) ($row['source_lang'] ?? 'fil');
        $bodyIsHtml = $isOriginal && $type === 'announcement';

        $parts = match ($type) {
            'event'     => self::eventParts($row, $locale, $title, $body),
            'ordinance' => self::ordinanceParts($row, $locale, $title, $body),
            default     => self::announcementParts($row, $locale, $title, $body),
        };

        if (trim($parts['title']) === '' && trim((string) $parts['body']) === '') {
            return $empty;                       // nothing written in this language
        }

        $script = SpokenText::script([
            'locale'       => $locale,
            'title'        => $parts['title'],
            'lead'         => $parts['lead'],
            'body'         => $parts['body'],
            'body_is_html' => $parts['body_is_html'] ?? $bodyIsHtml,
            'tail'         => $parts['tail'],
            'label'        => $type . ' #' . ($row['id'] ?? '?') . ' (' . $locale . ')',
        ]);

        // A capped reading has to say so out loud: a listener who cannot see
        // the screen has no other way to know the notice did not finish.
        if ($script['truncated']) {
            $script['chunks'][] = [
                'say'  => t('voice_reader.truncated_note', [], $locale),
                'find' => null,
                'kind' => 'tail',
            ];
        }

        $text = implode(' ', array_column($script['chunks'], 'say'));

        return [
            'available' => $script['chunks'] !== [],
            'locale'    => $locale,
            'title'     => $parts['title'],
            'chunks'    => $script['chunks'],
            'chars'     => $script['chars'],
            'truncated' => $script['truncated'],
            'text'      => $text,
            'hash'      => self::hash($text),
        ];
    }

    /**
     * Build every language at once.
     *
     * @return array<string,array<string,mixed>> keyed by locale
     */
    public static function buildAll(string $type, array $row): array
    {
        $out = [];
        foreach (array_keys(self::SUFFIX) as $locale) {
            $out[$locale] = self::build($type, $row, $locale);
        }

        return $out;
    }

    /**
     * Fingerprint of the exact words that were spoken.
     *
     * Stored beside the generated MP3. When staff edit a post the hash stops
     * matching and the audio is stale — which has to mean "do not serve this",
     * not "serve it anyway until someone notices", because the difference
     * between the old and new wording is the whole reason they edited it.
     */
    public static function hash(string $text): string
    {
        return hash('sha256', trim($text));
    }

    // ── Per type ─────────────────────────────────────────────────────────────

    /**
     * @return array{title:string, lead:list<string>, body:string, tail:list<string>, body_is_html?:bool}
     */
    private static function announcementParts(array $row, string $locale, string $title, string $body): array
    {
        $lead    = [];
        $urgency = (string) ($row['urgency'] ?? 'normal');

        // Said first: it is the reason the notice matters at all.
        if ($urgency === 'urgent') {
            $lead[] = t('voice_reader.lead_urgent', [], $locale);
        } elseif ($urgency === 'important') {
            $lead[] = t('voice_reader.lead_important', [], $locale);
        }

        $category = (string) ($row['category'] ?? 'general');
        $lead[]   = t('voice_reader.lead_category', [
            'category' => t('categories.' . $category, [], $locale),
        ], $locale);

        $stamp = $row['published_at'] ?? $row['created_at'] ?? null;
        if ($stamp) {
            $lead[] = t('voice_reader.lead_posted', [
                'date' => date('F j, Y', strtotime((string) $stamp)),
            ], $locale);
        }

        return ['title' => $title, 'lead' => $lead, 'body' => $body, 'tail' => []];
    }

    /**
     * When, where, then what.
     *
     * A listener who cannot see the sidebar needs the date, time and venue
     * before the description, not four sentences into it. The date is written
     * out in full because that is how it has to be heard — the sidebar's
     * compact format is for eyes.
     *
     * @return array{title:string, lead:list<string>, body:string, tail:list<string>, body_is_html:bool}
     */
    private static function eventParts(array $row, string $locale, string $title, string $body): array
    {
        $start = strtotime((string) ($row['event_date'] ?? 'now'));
        $when  = date('F j, Y, g:i A', $start);

        if (!empty($row['end_date'])) {
            $end   = strtotime((string) $row['end_date']);
            $when .= date('Y-m-d', $start) === date('Y-m-d', $end)
                ? ' - ' . date('g:i A', $end)              // same day: just the end time
                : ' - ' . date('F j, Y, g:i A', $end);
        }

        $lead = [t('voice_reader.lead_when', ['when' => $when], $locale)];

        if (!empty($row['venue'])) {
            $lead[] = t('voice_reader.lead_venue', ['venue' => (string) $row['venue']], $locale);
        }

        $status = (string) ($row['status'] ?? 'upcoming');
        $lead[] = t('voice_reader.lead_status', [
            'status' => t('detail_ui.status_' . $status, [], $locale),
        ], $locale);

        // Event descriptions are plain text in every language, including the
        // original — the detail view renders them with nl2br().
        return ['title' => $title, 'lead' => $lead, 'body' => $body, 'tail' => [], 'body_is_html' => false];
    }

    /**
     * Ordinances speak the plain-language summary, never the PDF.
     *
     * Reading twenty pages of legal text aloud helps nobody and would cost a
     * fortune to synthesise, so the reader says plainly at the end that it was
     * a summary and the document itself is on the page.
     *
     * The summary is only spoken in Filipino, because that is the language
     * AIService generates it in. An English or Manobo listener gets the
     * translated description instead — a Filipino summary read by an English
     * voice under an "English" label is the exact thing this feature exists to
     * avoid.
     *
     * @return array{title:string, lead:list<string>, body:string, tail:list<string>, body_is_html:bool}
     */
    private static function ordinanceParts(array $row, string $locale, string $title, string $body): array
    {
        $lead = [t('voice_reader.lead_ordinance_no', [
            'number' => (string) ($row['ordinance_no'] ?? ''),
        ], $locale)];

        if (!empty($row['enacted_date'])) {
            $lead[] = t('voice_reader.lead_enacted', [
                'date' => date('F j, Y', strtotime((string) $row['enacted_date'])),
            ], $locale);
        }

        $status = (string) ($row['status'] ?? 'active');
        $lead[] = t('voice_reader.lead_status', [
            'status' => t('detail_ui.status_' . $status, [], $locale),
        ], $locale);

        $summary   = trim((string) ($row['ai_summary'] ?? ''));
        $useSummary = $locale === 'fil' && $summary !== '';

        return [
            'title'        => $title,
            'lead'         => $lead,
            'body'         => $useSummary ? $summary : $body,
            'tail'         => [t($useSummary ? 'voice_reader.tail_summary' : 'voice_reader.tail_no_summary', [], $locale)],
            'body_is_html' => false,
        ];
    }

    // ── Strict language lookup ───────────────────────────────────────────────

    /**
     * The text in exactly this language, or an empty string.
     *
     * Deliberately not localised_content(): that helper falls back to the
     * original when a translation is missing, which is right for a page (better
     * to read Filipino than nothing) and wrong for a voice (an English button
     * that speaks Filipino is a bug, not a fallback). The one behaviour that IS
     * shared is the urgent-translation review gate — machine English held back
     * from the screen must be held back from the speaker too.
     */
    private static function inLanguage(array $row, string $field, string $locale): string
    {
        $sourceLang = (string) ($row['source_lang'] ?? 'fil');

        if ($locale === $sourceLang) {
            return trim((string) ($row[$field] ?? ''));
        }

        $gatedLocale = $sourceLang === 'fil' ? 'en' : 'fil';
        if ($locale === $gatedLocale && ($row['en_review_state'] ?? 'none') === 'pending') {
            return '';                           // awaiting a person's check
        }

        return trim((string) ($row[$field . self::SUFFIX[$locale]] ?? ''));
    }
}
