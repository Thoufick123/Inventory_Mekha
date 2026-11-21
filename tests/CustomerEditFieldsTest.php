<?php

$customerView = file_get_contents(__DIR__ . '/../app/views/concern/customers.php');
$salesController = file_get_contents(__DIR__ . '/../app/controllers/SalesController.php');

$editModalStart = strpos($customerView, 'id="modalEditCustomer"');
$editModalEnd = strpos($customerView, '<!-- MODAL: VIEW CUSTOMER', $editModalStart);
$editModal = substr($customerView, $editModalStart, $editModalEnd - $editModalStart);

$editHandlerStart = strpos($customerView, '// 3. Edit Customer Modal Population');
$editHandlerEnd = strpos($customerView, '// 4. View Customer 360 CRM Modal', $editHandlerStart);
$editHandler = substr($customerView, $editHandlerStart, $editHandlerEnd - $editHandlerStart);

$updateStart = strpos($salesController, 'public function updateCustomer(): void {');
$updateEnd = strpos($salesController, 'public function deleteCustomer(): void {', $updateStart);
$updateMethod = substr($salesController, $updateStart, $updateEnd - $updateStart);

$checks = [
    'Customer edit modal omits GSTIN and city fields' =>
        $editModalStart !== false
        && $editModalEnd !== false
        && strpos($editModal, 'name="gstin"') === false
        && strpos($editModal, 'name="city"') === false,
    'Customer edit handler no longer targets removed fields' =>
        strpos($editHandler, 'edit_cust_gstin') === false
        && strpos($editHandler, 'edit_cust_city') === false,
    'Updating other customer details preserves stored GSTIN and city' =>
        strpos($updateMethod, 'UPDATE customers SET name = ?, phone = ?, email = ?, address = ?, status = ?') !== false,
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
