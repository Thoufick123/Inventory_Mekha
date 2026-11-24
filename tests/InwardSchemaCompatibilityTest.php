<?php
declare(strict_types=1);

$controllerSource = file_get_contents(__DIR__ . '/../app/controllers/InwardController.php');
$migrationSource = file_get_contents(__DIR__ . '/../database/migrate_all_tables.php');

if ($controllerSource === false || $migrationSource === false) {
    fwrite(STDERR, "Unable to read inward schema compatibility sources.\n");
    exit(1);
}

$checks = [
    'Credit action runs schema repair before processing' =>
        preg_match('/public function creditStock\(\): void \{\s*\$this->validateCsrf\(\);\s*\$this->ensureSchema\(\);/s', $controllerSource) === 1,
    'Runtime repair adds the inward item reference column' =>
        strpos($controllerSource, "ALTER TABLE `inward_items` ADD COLUMN `inward_id` INT NULL") !== false,
    'Runtime repair adds the warehouse damage inward reference' =>
        strpos($controllerSource, "ALTER TABLE `warehouse_damages` ADD COLUMN `inward_id` INT NULL") !== false,
    'Migration script upgrades the inward item reference column' =>
        strpos($migrationSource, "ALTER TABLE `inward_items` ADD COLUMN `inward_id` INT NULL") !== false,
];

$failed = false;
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . "\n";
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
