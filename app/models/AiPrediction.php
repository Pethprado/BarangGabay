<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * AI decisions paired with the human outcome that later confirmed or
 * contradicted them.
 *
 * Currently tracks ID verification: the AI judges an uploaded ID at
 * registration, staff verify or suspend the resident afterwards, and the
 * staff decision is treated as ground truth.
 *
 * Writes are best-effort — accuracy bookkeeping must never block a
 * registration or a staff approval.
 */
class AiPrediction
{
    public const TYPE_ID_VERIFICATION = 'id_verification';

    /** AI statuses that represent a real call, and so can be scored. */
    private const SCORABLE = ['ai_passed', 'ai_flagged'];

    /**
     * Record a fresh AI decision, awaiting a human outcome.
     *
     * @param array<string,mixed> $aiResult The array from IdVerificationService.
     */
    public static function record(int $userId, array $aiResult, string $type = self::TYPE_ID_VERIFICATION): void
    {
        $status = (string) ($aiResult['id_ai_status'] ?? 'unknown');

        try {
            db()->prepare(
                'INSERT INTO ai_predictions
                    (prediction_type, reference_id, predicted_status, predicted_label,
                     confidence, model, detail, outcome, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                $type,
                $userId,
                $status,
                self::labelFor($status),
                (string) ($aiResult['confidence'] ?? 'unknown'),
                $aiResult['model'] ?? null,
                json_encode($aiResult, JSON_UNESCAPED_UNICODE) ?: null,
                in_array($status, self::SCORABLE, true) ? 'pending' : 'not_scored',
            ]);
        } catch (\Throwable $e) {
            error_log('[AiPrediction::record] ' . $e->getMessage());
        }
    }

    /**
     * Resolve a pending prediction with what staff actually decided.
     *
     * @param string $actualStatus 'verified' or 'suspended'
     */
    public static function resolve(
        int    $userId,
        string $actualStatus,
        ?int   $resolvedBy = null,
        string $type = self::TYPE_ID_VERIFICATION
    ): void {
        $actualLabel = $actualStatus === 'verified' ? 'valid' : 'invalid';

        try {
            // Only rows the AI genuinely judged get scored; 'not_scored' rows
            // still get their actual outcome recorded for the audit trail.
            db()->prepare(
                "UPDATE ai_predictions
                    SET actual_status = ?,
                        actual_label  = ?,
                        outcome = CASE
                            WHEN outcome = 'not_scored' THEN 'not_scored'
                            WHEN predicted_label = ?     THEN 'correct'
                            ELSE 'incorrect'
                        END,
                        resolved_by = ?,
                        resolved_at = NOW()
                  WHERE prediction_type = ?
                    AND reference_id    = ?
                    AND resolved_at IS NULL"
            )->execute([$actualStatus, $actualLabel, $actualLabel, $resolvedBy, $type, $userId]);
        } catch (\Throwable $e) {
            error_log('[AiPrediction::resolve] ' . $e->getMessage());
        }
    }

    /**
     * Headline accuracy metrics.
     *
     * false_approvals are AI-passed IDs that staff later rejected — the
     * expensive kind of mistake, so they are reported separately rather than
     * folded into one accuracy number.
     *
     * @return array{
     *     total:int, scored:int, correct:int, incorrect:int, pending:int,
     *     not_scored:int, accuracy:float, false_approvals:int, false_rejections:int
     * }
     */
    public static function stats(string $type = self::TYPE_ID_VERIFICATION): array
    {
        $blank = [
            'total' => 0, 'scored' => 0, 'correct' => 0, 'incorrect' => 0,
            'pending' => 0, 'not_scored' => 0, 'accuracy' => 0.0,
            'false_approvals' => 0, 'false_rejections' => 0,
        ];

        try {
            $stmt = db()->prepare(
                "SELECT
                    COUNT(*)                                                        AS total,
                    SUM(outcome = 'correct')                                        AS correct,
                    SUM(outcome = 'incorrect')                                      AS incorrect,
                    SUM(outcome = 'pending')                                        AS pending,
                    SUM(outcome = 'not_scored')                                     AS not_scored,
                    SUM(outcome = 'incorrect' AND predicted_label = 'valid')        AS false_approvals,
                    SUM(outcome = 'incorrect' AND predicted_label = 'invalid')      AS false_rejections
                 FROM ai_predictions WHERE prediction_type = ?"
            );
            $stmt->execute([$type]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $correct   = (int) ($row['correct']   ?? 0);
            $incorrect = (int) ($row['incorrect'] ?? 0);
            $scored    = $correct + $incorrect;

            return [
                'total'            => (int) ($row['total'] ?? 0),
                'scored'           => $scored,
                'correct'          => $correct,
                'incorrect'        => $incorrect,
                'pending'          => (int) ($row['pending']    ?? 0),
                'not_scored'       => (int) ($row['not_scored'] ?? 0),
                'accuracy'         => $scored > 0 ? round($correct / $scored * 100, 1) : 0.0,
                'false_approvals'  => (int) ($row['false_approvals']  ?? 0),
                'false_rejections' => (int) ($row['false_rejections'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return $blank;
        }
    }

    /**
     * Accuracy split by the confidence the AI reported — shows whether its
     * own confidence is worth trusting.
     *
     * @return list<array<string,mixed>>
     */
    public static function byConfidence(string $type = self::TYPE_ID_VERIFICATION): array
    {
        try {
            $stmt = db()->prepare(
                "SELECT COALESCE(confidence, 'unknown') AS confidence,
                        COUNT(*)                   AS total,
                        SUM(outcome = 'correct')   AS correct,
                        SUM(outcome = 'incorrect') AS incorrect
                 FROM ai_predictions
                 WHERE prediction_type = ? AND outcome IN ('correct','incorrect')
                 GROUP BY COALESCE(confidence, 'unknown')
                 ORDER BY total DESC"
            );
            $stmt->execute([$type]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Monthly accuracy trend, oldest first — feeds the chart.
     *
     * @return list<array<string,mixed>>
     */
    public static function trend(int $months = 6, string $type = self::TYPE_ID_VERIFICATION): array
    {
        try {
            $isPgsql = (db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql');
            $fmtExpr = $isPgsql ? "to_char(created_at, 'YYYY-MM')" : "DATE_FORMAT(created_at, '%Y-%m')";
            $dateCond = $isPgsql ? "created_at >= NOW() - (? || ' months')::interval" : "created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)";

            $stmt = db()->prepare(
                "SELECT {$fmtExpr} AS period,
                        COUNT(*) AS total,
                        SUM(CASE WHEN outcome = 'correct' THEN 1 ELSE 0 END) AS correct,
                        SUM(CASE WHEN outcome = 'incorrect' THEN 1 ELSE 0 END) AS incorrect
                 FROM ai_predictions
                 WHERE prediction_type = ?
                   AND outcome IN ('correct','incorrect')
                   AND {$dateCond}
                 GROUP BY 1
                 ORDER BY 1 ASC"
            );
            $stmt->execute([$type, max(1, $months)]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Recent predictions with the resident they refer to.
     *
     * @param array{outcome?:string} $filters
     * @return array{items: array[], total: int}
     */
    public static function paginate(int $page, int $perPage, array $filters = [], string $type = self::TYPE_ID_VERIFICATION): array
    {
        $where  = ['p.prediction_type = ?'];
        $params = [$type];

        $outcome = (string) ($filters['outcome'] ?? '');
        if (in_array($outcome, ['correct', 'incorrect', 'pending', 'not_scored'], true)) {
            $where[]  = 'p.outcome = ?';
            $params[] = $outcome;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        try {
            $countStmt = db()->prepare("SELECT COUNT(*) FROM ai_predictions p {$whereClause}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $offset = max(0, ($page - 1) * $perPage);
            $stmt   = db()->prepare(
                "SELECT p.*, u.full_name, u.email, r.full_name AS resolver_name
                 FROM ai_predictions p
                 LEFT JOIN users u ON u.id = p.reference_id
                 LEFT JOIN users r ON r.id = p.resolved_by
                 {$whereClause}
                 ORDER BY p.created_at DESC, p.id DESC
                 LIMIT ? OFFSET ?"
            );
            $stmt->execute(array_merge($params, [$perPage, $offset]));

            return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
        } catch (\Throwable $e) {
            return ['items' => [], 'total' => 0];
        }
    }

    /** Map an AI status onto the binary label used for scoring. */
    private static function labelFor(string $status): string
    {
        return match ($status) {
            'ai_passed'  => 'valid',
            'ai_flagged' => 'invalid',
            default      => 'unsure',
        };
    }
}
