<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Announcement;
use App\Models\DocumentRequest;
use App\Services\ManoboDictionary;
use App\Services\SpokenText;
use PDO;

/**
 * Resident global search: announcements, events, ordinances, document types
 * and the dictionary. Public content only — never users, requests, payments
 * or anything from the admin side.
 */
class SearchController
{
    private const PER_GROUP = 8;

    /**
     * GET /search?q=...
     */
    public function index(): void
    {
        $q       = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $results = ['announcements' => [], 'events' => [], 'ordinances' => [], 'documents' => [], 'dictionary' => []];

        if (mb_strlen($q) >= 2) {
            $like = '%' . mb_strtolower($q) . '%';

            $results['announcements'] = $this->query(
                'SELECT a.*, u.full_name AS author_name FROM announcements a JOIN users u ON u.id = a.author_id
                  WHERE ' . Announcement::visibleSql('a') . ' AND ('
                    . $this->anyLike(['a.title', 'a.title_en', 'a.title_fil', 'a.title_manobo', 'a.body', 'a.body_en', 'a.body_fil']) . ')
                  ORDER BY a.published_at DESC LIMIT ' . self::PER_GROUP,
                7, $like
            );
            $results['events'] = $this->query(
                "SELECT * FROM events WHERE status <> 'cancelled' AND ("
                    . $this->anyLike(['title', 'title_en', 'title_fil', 'title_manobo', 'description', 'venue']) . ')
                  ORDER BY event_date DESC LIMIT ' . self::PER_GROUP,
                6, $like
            );
            $results['ordinances'] = $this->query(
                "SELECT * FROM ordinances WHERE status IN ('active','repealed') AND ("
                    . $this->anyLike(['title', 'title_en', 'title_fil', 'title_manobo', 'ordinance_no', 'description']) . ')
                  ORDER BY enacted_date DESC LIMIT ' . self::PER_GROUP,
                6, $like
            );

            foreach (DocumentRequest::TYPES as $key => $label) {
                if (str_contains(mb_strtolower($label), mb_strtolower($q)) || str_contains((string) $key, mb_strtolower($q))) {
                    $results['documents'][] = ['key' => (string) $key, 'label' => (string) $label];
                }
            }

            try {
                $results['dictionary'] = array_slice((new ManoboDictionary())->search($q), 0, self::PER_GROUP);
            } catch (\Throwable $e) {
                error_log('[SearchController] dictionary: ' . $e->getMessage());
            }
        }

        $total = array_sum(array_map('count', $results));

        view('resident/search', [
            'pageTitle' => 'Search',
            'q'         => $q,
            'results'   => $results,
            'total'     => $total,
        ]);
    }

    /** Short plain-text excerpt of localised content. */
    public static function excerpt(array $row, string $field, int $len = 140): string
    {
        $text = SpokenText::repairJoins(SpokenText::plain(localised_text($row, $field)));
        return mb_strimwidth($text, 0, $len, '…');
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function query(string $sql, int $placeholders, string $like): array
    {
        try {
            $stmt = db()->prepare($sql);
            $stmt->execute(array_fill(0, $placeholders, $like));
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[SearchController] ' . $e->getMessage());
            return [];
        }
    }

    /** "LOWER(COALESCE(col,'')) LIKE ? OR …" — case-insensitive on MySQL and PostgreSQL alike. */
    private function anyLike(array $columns): string
    {
        return implode(' OR ', array_map(static fn (string $c): string => "LOWER(COALESCE({$c}, '')) LIKE ?", $columns));
    }
}
