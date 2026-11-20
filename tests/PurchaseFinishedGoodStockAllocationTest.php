<?php

require_once __DIR__ . '/../app/core/Autoloader.php';

$allocator = new ReflectionMethod(
    \App\Controllers\LabelController::class,
    'allocatePurchaseStockQuantity'
);
$allocator->setAccessible(true);

$variants = [
    ['id' => 6, 'attributes' => json_encode(['allocated_qty' => 2])],
    ['id' => 7, 'attributes' => json_encode(['allocated_qty' => 2])],
    ['id' => 8, 'attributes' => json_encode(['allocated_qty' => 6])],
];

$allocations = $allocator->invoke(null, 10.0, $variants, 6);
$expected = [6 => 2, 7 => 2, 8 => 6];
if ($allocations != $expected) {
    fwrite(STDERR, 'Expected 10 units to allocate 2/2/6 across the mapped variants.' . PHP_EOL);
    exit(1);
}

$scaledAllocations = $allocator->invoke(null, 5.0, $variants, 6);
$scaledExpected = [6 => 1, 7 => 1, 8 => 3];
if ($scaledAllocations != $scaledExpected || abs(array_sum($scaledAllocations) - 5.0) > 0.00001) {
    fwrite(STDERR, 'Allocation should scale proportionally and preserve the purchase total.' . PHP_EOL);
    exit(1);
}

$legacyAllocation = $allocator->invoke(
    null,
    4.5,
    [['id' => 6, 'attributes' => null]],
    6
);
if ($legacyAllocation != [6 => 4.5]) {
    fwrite(STDERR, 'Legacy mappings without allocations should fall back to the primary variant.' . PHP_EOL);
    exit(1);
}

echo "Purchase finished-goods allocation tests passed." . PHP_EOL;
