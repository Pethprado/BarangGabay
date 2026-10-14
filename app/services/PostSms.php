<?php
declare(strict_types=1);

namespace App\Services;

/**
 * The one place a post's SMS text is written.
 *
 * Three controllers each had their own copy of this, and the copies had
 * drifted: the announcement builder had no length arithmetic at all, the event
 * builder could produce 171 characters — two credits for a message that should
 * cost one — and only the ordinance builder budgeted properly. A staff member
 * sending the same post by hand would have got different text again.
 *
 * Pulling them together means auto-send-on-publish and manual send produce
 * byte-identical messages, and there is exactly one thing to fix when the
 * wording changes.
 *
 * ── The length rule ─────────────────────────────────────────────────────
 *
 * 160 is one SMS credit. Over that, the carrier splits the message and the
 * barangay pays twice for every recipient — on a 500-resident blast that is
 * 500 credits of real money for a wording accident.
 *
 * So the fixed parts are measured first and whatever is left goes to the
 * title. Only the title is ever cut: the sign-off identifies the sender and
 * the date is the fact the resident needs, and a message that loses either to
 * make room for a long headline has lost the wrong part.
 *
 * The cap is applied in BOTH characters and bytes. SMS counts characters, but
 * SemaphoreSmsService trims with substr(), which counts bytes — so a message
 * of 160 characters containing an em-dash would be cut mid-character on its
 * way out, leaving mojibake on the handset. Budgeting for the stricter of the
 * two costs a few characters of title and cannot produce that.
 */
final class PostSms
{
    /** One SMS credit. Auto-built post messages are fitted to this. */
    public const LIMIT = 160;

    /**
     * Longest message staff may type: 10 joined parts. Longer than one part is
     * allowed (the phone shows it as one message); every part is one credit
     * per recipient, so the screens show the cost before sending.
     */
    public const MAX_LENGTH = 1530;

    /**
     * How many SMS parts (credits per recipient) a message uses, counted the
     * way carriers do: 160 / 153 per part in plain text, 70 / 67 once it has
     * a character outside the GSM alphabet (emoji, curly quotes, em-dash...).
     */
    public static function segments(string $message): int
    {
        $length = mb_strlen($message);
        if ($length === 0) {
            return 1;
        }
        [$single, $part] = self::isGsm($message) ? [160, 153] : [70, 67];

        return $length <= $single ? 1 : (int) ceil($length / $part);
    }

    /** True when every character fits the plain GSM 7-bit SMS alphabet. */
    public static function isGsm(string $message): bool
    {
        return preg_match('/^[A-Za-z0-9 \r\n@£$¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ!"#¤%&\'()*+,\-.\/:;<=>?¡ÄÖÑÜ§¿äöñüà^{}\\\[~\]|€]*$/u', $message) === 1;
    }

    /** What mb_strimwidth appends when it cuts a title. */
    private const ELLIPSIS = '…';

    /**
     * The message for a post.
     *
     * @param string              $type  'announcement' | 'event' | 'ordinance'
     * @param array<string,mixed> $post  The row, as its model returns it.
     * @param string              $scope Name the message signs off with.
     *                                   Empty uses the barangay's own name.
     *                                   A purok-targeted notice passes the
     *                                   purok instead, so the recipient can
     *                                   see at a glance the message IS about
     *                                   them — which is the entire point of
     *                                   having narrowed the send.
     */
    public static function build(string $type, array $post, string $scope = ''): string
    {
        return match ($type) {
            'event'     => self::event($post, $scope),
            'ordinance' => self::ordinance($post, $scope),
            default     => self::announcement($post, $scope),
        };
    }

    /**
     * The whole post as one SMS: title, every detail residents see on the
     * post page (category, dates, venue, number), the full content, and a
     * link to the post on BarangGabay. Used by every post SMS: the SMS page
     * and the automatic send when a post is published.
     *
     * Capped at MAX_LENGTH; only the content is ever shortened (with "..."),
     * so the title, the details and the link always arrive. Curly quotes and
     * dashes become plain ones so the text stays in the cheaper GSM parts.
     *
     * @param string              $type 'announcement' | 'event' | 'ordinance'
     * @param array<string,mixed> $post The full row.
     */
    public static function full(string $type, array $post, string $scope = ''): string
    {
        $title = self::smsText((string) ($post['title'] ?? ''));
        $link  = self::reference($type, $post);
        $lines = [];

        switch ($type) {
            case 'event':
                $status = (string) ($post['status'] ?? 'upcoming');
                $head   = '[BarangGabay] ' . ($status === 'cancelled' ? 'KANSELADONG KAGANAPAN: ' : 'KAGANAPAN: ') . $title;
                $when   = trim((string) ($post['event_date'] ?? ''));
                if ($when !== '') {
                    $date = 'Petsa: ' . date('M d, Y g:i A', strtotime($when));
                    $end  = trim((string) ($post['end_date'] ?? ''));
                    if ($end !== '') {
                        $date .= ' - ' . date(substr($end, 0, 10) === substr($when, 0, 10) ? 'g:i A' : 'M d, Y g:i A', strtotime($end));
                    }
                    $lines[] = $date;
                }
                $venue   = self::smsText((string) ($post['venue'] ?? ''));
                $lines[] = 'Lugar: ' . ($venue !== '' ? $venue : 'Barangay Hall');
                if (isset(self::EVENT_STATUS[$status]) && $status !== 'upcoming') {
                    $lines[] = 'Katayuan: ' . self::EVENT_STATUS[$status];
                }
                $body = self::smsText((string) ($post['description'] ?? ''));
                break;

            case 'ordinance':
                $number = self::smsText((string) ($post['ordinance_no'] ?? ''));
                $head   = '[BarangGabay] ORDINANSA: ' . ($number !== '' ? $number . ' - ' : '') . $title;
                if (trim((string) ($post['category'] ?? '')) !== '') {
                    $lines[] = 'Kategorya: ' . self::smsText((string) $post['category']);
                }
                if (!empty($post['enacted_date'])) {
                    $lines[] = 'Pinagtibay: ' . date('M d, Y', strtotime((string) $post['enacted_date']));
                }
                if (($post['status'] ?? '') === 'repealed') {
                    $lines[] = 'Katayuan: Pinawalang-bisa na';
                }
                // The ordinance itself is a PDF: send what we hold as text, the
                // description AND the plain-language summary, then the link.
                $description = self::smsText((string) ($post['description'] ?? ''));
                $summary     = self::smsText((string) ($post['ai_summary'] ?? ''));
                $body        = $description;
                if ($summary !== '' && $summary !== $description) {
                    $body .= ($body !== '' ? "\n\n" : '') . 'Buod: ' . $summary;
                }
                break;

            default:
                $urgency  = (string) ($post['urgency'] ?? 'normal');
                $label    = ['urgent' => 'URGENT', 'important' => 'MAHALAGANG ANUNSYO'][$urgency] ?? 'ANUNSYO';
                $head     = '[BarangGabay] ' . $label . ': ' . $title;
                $category = (string) ($post['category'] ?? '');
                if (isset(self::CATEGORY[$category])) {
                    $lines[] = 'Kategorya: ' . self::CATEGORY[$category];
                }
                $posted = (string) ($post['published_at'] ?? '');
                if ($posted !== '') {
                    $lines[] = 'Petsa: ' . date('M d, Y g:i A', strtotime($posted));
                }
                if (trim((string) ($post['target_purok'] ?? '')) !== '') {
                    $lines[] = 'Para sa: ' . self::smsText((string) $post['target_purok']);
                }
                $body = self::smsText((string) ($post['body'] ?? ''));
        }

        if ($lines !== []) {
            $head .= "\n" . implode("\n", $lines);
        }
        $tail = ($link !== null ? "\n\nTingnan sa BarangGabay: " . $link : '') . "\n- " . self::place($scope);

        $room = self::MAX_LENGTH - mb_strlen($head) - mb_strlen($tail) - 2;
        if ($body !== '' && $room > 20) {
            if (mb_strlen($body) > $room) {
                $cut  = mb_substr($body, 0, $room - 3);
                $at   = mb_strrpos($cut, ' ');
                $body = rtrim($at !== false && $at > $room * 0.7 ? mb_substr($cut, 0, $at) : $cut) . '...';
            }
            return $head . "\n\n" . $body . $tail;
        }

        return mb_substr($head, 0, self::MAX_LENGTH - mb_strlen($tail)) . $tail;
    }

    /**
     * full() for a post loaded by id, for the automatic sends on publish.
     * Falls back to the short build() text if the row cannot be read, so a
     * database hiccup never stops the SMS.
     *
     * @param array<string,mixed> $fallback Fields for build() if the load fails.
     */
    public static function fullById(string $type, int $id, array $fallback = [], string $scope = ''): string
    {
        $table = ['announcement' => 'announcements', 'event' => 'events', 'ordinance' => 'ordinances'][$type] ?? null;
        try {
            if ($table !== null && $id > 0) {
                $stmt = db()->prepare("SELECT * FROM {$table} WHERE id = ?");
                $stmt->execute([$id]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    return self::full($type, $row, $scope);
                }
            }
        } catch (\Throwable $e) {
            error_log('[PostSms::fullById] ' . $e->getMessage());
        }

        return self::build($type, $fallback, $scope);
    }

    /** Announcement categories as residents read them. */
    private const CATEGORY = [
        'general' => 'Pangkalahatan', 'health' => 'Kalusugan', 'safety' => 'Kaligtasan',
        'government' => 'Pamahalaan', 'infrastructure' => 'Imprastraktura', 'social' => 'Panlipunan',
    ];

    /** Event statuses as residents read them. */
    private const EVENT_STATUS = [
        'upcoming' => 'Paparating', 'ongoing' => 'Nagaganap ngayon',
        'completed' => 'Natapos na', 'cancelled' => 'Kanselado',
    ];

    /**
     * Rich text (Quill HTML) as plain SMS text: paragraphs and list items on
     * their own lines, entities decoded, spaces tidied, and typographic
     * characters swapped for plain ones (a single "—" would otherwise make
     * every part hold 67 characters instead of 153).
     */
    public static function smsText(string $html): string
    {
        $text = preg_replace('~<\s*br\s*/?>~i', "\n", $html) ?? $html;
        $text = preg_replace('~<\s*li[^>]*>~i', "\n- ", $text) ?? $text;
        $text = preg_replace('~</\s*(p|div|h[1-6]|li|ul|ol|blockquote)\s*>~i', "\n", $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strtr($text, [
            "\u{2018}" => "'", "\u{2019}" => "'", "\u{201C}" => '"', "\u{201D}" => '"',
            "\u{2013}" => '-', "\u{2014}" => '-', "\u{2026}" => '...', "\u{00A0}" => ' ',
            "\u{2022}" => '-', "\u{00B7}" => '-', "\t" => ' ',
        ]);
        $text = preg_replace('/[ ]{2,}/', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */', "\n", $text) ?? $text;
        $text = preg_replace('/\n{2,}/', "\n", $text) ?? $text;   // one line per paragraph/item: blank lines cost credits

        return trim($text);
    }

    /**
     * A short portal link for the post, or null when there is nothing to link.
     *
     * Kept separate from build() rather than folded into it: a link costs
     * 20-40 characters that would come straight out of the title, and whether
     * that trade is worth making depends on the post. The caller decides.
     */
    public static function reference(string $type, array $post): ?string
    {
        $slug = trim((string) ($post['slug'] ?? ''));
        $id   = (int) ($post['id'] ?? 0);

        $path = match ($type) {
            'announcement' => $slug !== '' ? 'announcements/' . $slug : null,
            'event'        => $slug !== '' ? 'events/' . $slug        : null,
            'ordinance'    => $id > 0      ? 'ordinances/' . $id      : null,
            default        => null,
        };

        return $path !== null ? app_url($path) : null;
    }

    // ── Per type ─────────────────────────────────────────────────────────

    /** @param array<string,mixed> $post */
    private static function announcement(array $post, string $scope = ''): string
    {
        $title   = trim((string) ($post['title'] ?? ''));
        $urgent  = ($post['urgency'] ?? '') === 'urgent';
        $place   = self::place($scope);

        // Wording unchanged from AnnouncementController::sendAnnouncementSms();
        // only the barangay name now comes from the setting instead of being
        // written into the string.
        if ($urgent) {
            $prefix = '[BarangGabay URGENT!] ';
            $tail   = ' - ' . $place . '. Pakibasa kaagad ang inyong portal.';
        } else {
            $prefix = '[BarangGabay] BAGONG ANUNSYO: ';
            $tail   = "\nBisitahin ang portal para sa buong detalye. " . $place;
        }

        return $prefix . self::fitTitle($title, $prefix, $tail) . $tail;
    }

    /** @param array<string,mixed> $post */
    private static function event(array $post, string $scope = ''): string
    {
        $title = trim((string) ($post['title'] ?? ''));
        $when  = trim((string) ($post['event_date'] ?? ''));
        $venue = trim((string) ($post['venue'] ?? ''));

        $prefix = '[BarangGabay] BAGONG KAGANAPAN: ';

        // Date and venue are facts a resident acts on, so they sit in the
        // fixed part and the title yields to them — the reverse of what the
        // old builder did, which is how it could overrun 160.
        $dateStr  = $when !== '' ? date('M d, Y', strtotime($when)) : '';
        $venueStr = $venue !== '' ? mb_substr($venue, 0, 40) : 'Barangay Hall';

        $tail = ($dateStr !== '' ? "\nPetsa: " . $dateStr : '')
              . "\nLugar: " . $venueStr
              . "\n" . self::place($scope);

        return $prefix . self::fitTitle($title, $prefix, $tail) . $tail;
    }

    /** @param array<string,mixed> $post */
    private static function ordinance(array $post, string $scope = ''): string
    {
        $number = trim((string) ($post['ordinance_no'] ?? ''));
        $title  = trim((string) ($post['title'] ?? ''));

        $prefix = '[BarangGabay] BAGONG ORDINANSA: ';
        $tail   = "\n" . 'Basahin ang detalye sa portal. ' . self::place($scope);
        $sep    = ' - ';

        // The number gets first call on the space: "Ordinance No. 2026-014" is
        // what a resident needs in order to look the policy up. Capped at 34 so
        // a freakishly long one cannot crowd the title out entirely.
        $budget = self::budget($prefix, $tail);
        $noPart = mb_substr($number, 0, max(0, min(34, $budget)));

        $left = $budget - mb_strlen($noPart);
        if ($title === '' || $left <= mb_strlen($sep)) {
            return $prefix . $noPart . $tail;
        }

        return $prefix . $noPart . $sep
             . self::trim($title, $left - mb_strlen($sep))
             . $tail;
    }

    // ── Internals ────────────────────────────────────────────────────────

    /**
     * Who the message signs off as.
     *
     * A purok, when the send was narrowed to one — the recipient should be
     * able to tell from the text alone that it concerns them. Otherwise the
     * barangay's own name, from settings and never written into a string, so
     * a relocation or a rename does not leave stale text in outgoing SMS.
     */
    private static function place(string $scope = ''): string
    {
        $scope = trim($scope);
        if ($scope !== '') {
            return $scope;
        }

        $name = trim((string) setting('location_name', 'BarangGabay'));

        return $name !== '' ? $name : 'BarangGabay';
    }

    /** How many characters are left for the title once the fixed parts are counted. */
    private static function budget(string $prefix, string $tail): int
    {
        return max(0, self::LIMIT - mb_strlen($prefix) - mb_strlen($tail));
    }

    /** The title, cut to whatever the fixed parts left for it. */
    private static function fitTitle(string $title, string $prefix, string $tail): string
    {
        return self::trim($title, self::budget($prefix, $tail));
    }

    /**
     * Cut a title to fit, in characters AND bytes.
     *
     * mb_strimwidth handles the character limit and appends the ellipsis only
     * when it actually cuts. The byte loop afterwards is for the multibyte
     * case: SemaphoreSmsService trims with substr(), so a title that is 60
     * characters but 70 bytes could still be sliced mid-character downstream.
     * Dropping a character at a time until it fits both is cheap and exact.
     */
    private static function trim(string $title, int $limit): string
    {
        if ($limit <= 0 || $title === '') {
            return '';
        }

        $out = mb_strimwidth($title, 0, $limit, self::ELLIPSIS);

        // Re-trim at a narrower width rather than chopping characters off the
        // end. Chopping would eat the ellipsis mb_strimwidth just added, and
        // the reader would have no sign the title was cut.
        $width = $limit;
        while ($out !== '' && strlen($out) > $limit && $width > 1) {
            $width--;
            $out = mb_strimwidth($title, 0, $width, self::ELLIPSIS);
        }

        return $out;
    }
}
