<?php
require_once __DIR__ . '/../app/core/Autoloader.php';

use App\Core\CSRF;

$token = CSRF::token();
$checks = [];

$_POST['csrf_token'] = $token;
$checks['Form CSRF token is accepted'] = CSRF::validateRequest() === true;

unset($_POST['csrf_token']);
$_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
$checks['CSRF header token is accepted'] = CSRF::validateRequest() === true;

$failed = false;
foreach ($checks as $description => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $description . "\n";
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
