<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';

$reflection = new ReflectionClass(\App\Controllers\OutwardController::class);
$controller = $reflection->newInstanceWithoutConstructor();
$validator = $reflection->getMethod('validateOutwardDetails');
$validator->setAccessible(true);

$validCases = [
    ['TN 12 N1234', 'Anand Kumar', '9943557998', 'GRM Logistics', 'Priority shipment', '2'],
    ['TN-38-BK-4421', "A. O'Neil", '', '', '', ''],
    ['22 BH 1234 AA', '', '9876543210', '', '', '99999'],
];

foreach ($validCases as $case) {
    $validator->invoke($controller, ...$case);
}

$invalidCases = [
    ['vehicle number' => ['rtt31232', '', '', '', '', '']],
    ['driver name' => ['TN 12 AB 1234', 'Anand123', '', '', '', '']],
    ['driver contact' => ['TN 12 AB 1234', '', '788888888888888', '', '', '']],
    ['box quantity' => ['TN 12 AB 1234', '', '', '', '', '1.5']],
    ['transporter length' => ['TN 12 AB 1234', '', '', str_repeat('T', 151), '', '']],
];

foreach ($invalidCases as $case) {
    $label = array_key_first($case);
    $rejected = false;
    try {
        $validator->invoke($controller, ...$case[$label]);
    } catch (Exception $e) {
        if (get_class($e) !== Exception::class) {
            throw $e;
        }
        $rejected = true;
    }
    if (!$rejected) {
        fwrite(STDERR, "Expected {$label} validation to fail." . PHP_EOL);
        exit(1);
    }
}

echo "Outward details validation tests passed." . PHP_EOL;
