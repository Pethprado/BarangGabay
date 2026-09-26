<?php
declare(strict_types=1);

namespace App\Middleware;

use function db;

class RateLimitMiddleware
{
    public function handle(int $userId, int $maxPerHour = 10): void
    {
        if ($userId <= 0) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $pdo = db();
        // ai_logs is the table populated by AIController for both summarize and chat.
        // ai_chat_logs is a separate table and is never written to by the AI endpoints.
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM ai_logs WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)');
        $stmt->execute([$userId]);
        $count = (int) $stmt->fetchColumn();

        if ($count >= $maxPerHour) {
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Napalampas mo na ang limitasyon ng AI. Subukan muli pagkatapos ng isang oras.']);
            exit;
        }
    }
}
