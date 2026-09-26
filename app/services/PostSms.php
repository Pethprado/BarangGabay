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
    /** One SMS credit. */
    public const LIMIT = 160;

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
