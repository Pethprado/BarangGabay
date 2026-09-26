<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

$mode = $argv[1] ?? '';
$state = [
    'mode' => $mode,
    'status' => 'unknown',
    'code' => http_response_code(),
    'output' => '',
];

register_shutdown_function(function () use (&$state): void {
    $state['code'] = http_response_code();
    echo json_encode($state);
});

$_SERVER['REQUEST_METHOD'] = 'POST';

if ($mode === 'valid') {
    $_POST['csrf_token'] = csrf_token();
    $_POST['other'] = 'data';
    check_csrf();
    $state['status'] = 'passed';
} elseif ($mode === 'invalid') {
    $_POST['csrf_token'] = 'invalid-token';
    check_csrf();
    $state['status'] = 'should-not-get-here';
} else {
    $state['status'] = 'invalid-mode';
}
