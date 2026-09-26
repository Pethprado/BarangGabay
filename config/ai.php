<?php
declare(strict_types=1);

return [
    'anthropic_api_key' => $_ENV['ANTHROPIC_API_KEY'] ?? '',
    'model' => $_ENV['AI_MODEL'] ?? 'claude-sonnet-4-20250514',
    'max_tokens' => (int) ($_ENV['AI_MAX_TOKENS'] ?? 1024),
    'timeout' => (int) ($_ENV['AI_TIMEOUT'] ?? 30),
];
