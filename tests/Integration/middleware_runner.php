<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

$mode = $argv[1] ?? '';
$state = [
    'mode' => $mode,
    'status' => 'pending',
    'headers' => [],
    'code' => http_response_code(),
];

register_shutdown_function(function () use (&$state): void {
    $state['headers'] = headers_list();
    $state['code'] = http_response_code();
    echo json_encode($state);
});

try {
    if ($mode === 'auth') {
        $_SESSION = [];
        (new App\Middleware\AuthMiddleware())->handle();
        $state['status'] = 'allowed';
    } elseif ($mode === 'role-deny') {
        $_SESSION['role'] = 'resident';
        (new App\Middleware\RoleMiddleware())->handle(['admin', 'staff']);
        $state['status'] = 'allowed';
    } elseif ($mode === 'role-allow') {
        $_SESSION['role'] = 'staff';
        (new App\Middleware\RoleMiddleware())->handle(['admin', 'staff']);
        $state['status'] = 'allowed';
    } else {
        $state['status'] = 'invalid-mode';
    }
} catch (Throwable $e) {
    $state['status'] = 'exception';
    $state['message'] = $e->getMessage();
}
