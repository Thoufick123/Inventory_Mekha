<?php

$routes = file_get_contents(__DIR__ . '/../app/routes.php');
$overviewView = file_get_contents(__DIR__ . '/../app/views/concern/billing_overview.php');
$customerView = file_get_contents(__DIR__ . '/../app/views/concern/customers.php');
$terminalView = file_get_contents(__DIR__ . '/../app/views/concern/billing.php');
$salesController = file_get_contents(__DIR__ . '/../app/controllers/SalesController.php');
$completeSaleStart = strpos($salesController, 'public function completePosSale(): void {');
$completeSaleEnd = strpos($salesController, 'public function storeInvoice(): void {', $completeSaleStart);
$completeSaleMethod = substr($salesController, $completeSaleStart, $completeSaleEnd - $completeSaleStart);

$checks = [
    'POS Billing module route opens the bill overview' =>
        strpos($routes, "get('/m/billing', [SalesController::class, 'billingOverview']") !== false,
    'Create Bill opens the POS terminal through its own route' =>
        strpos($routes, "get('/m/billing/create', [SalesController::class, 'billing']") !== false
        && strpos($overviewView, "Controller::url('/m/billing/create')") !== false,
    'Bill overview includes summary cards, filters, and invoice actions' =>
        strpos($overviewView, 'Billed Revenue') !== false
        && strpos($overviewView, 'Collection Rate') !== false
        && strpos($overviewView, 'name="q"') !== false
        && strpos($overviewView, 'POS Invoices &amp; Bills') !== false
        && strpos($overviewView, '/m/billing/create?print_id=') !== false,
    'Bill overview provides channel, date, category, payment, and GST filters' =>
        strpos($overviewView, 'name="channel"') !== false
        && strpos($overviewView, 'name="from"') !== false
        && strpos($overviewView, 'name="to"') !== false
        && strpos($overviewView, 'name="category_id"') !== false
        && strpos($overviewView, 'name="payment"') !== false
        && strpos($overviewView, 'name="gst"') !== false
        && strpos($salesController, 'si.invoice_date >= ?') !== false
        && strpos($salesController, 'category_v.category_id = ?') !== false
        && strpos($salesController, "si.notes LIKE '%[Online Channel]%'") !== false
        && strpos($salesController, 'si.notes LIKE ?') !== false,
    'All includes every invoice once and direct bills remain separately filterable' =>
        strpos($salesController, "['all', 'cash', 'online', 'direct']") !== false
        && strpos($salesController, 'COUNT(DISTINCT id) AS all_invoice_count') !== false
        && strpos($salesController, 'COALESCE(SUM(net_amount), 0) AS all_revenue') !== false
        && strpos($salesController, "if (\$channel === 'direct')") !== false
        && strpos($salesController, "elseif (in_array(\$channel, ['cash', 'online'], true))") !== false
        && strpos($salesController, 'SELECT COUNT(DISTINCT si.id)') !== false
        && strpos($salesController, 'AS direct_revenue') !== false
        && strpos($overviewView, "'direct' => ['Direct Bills'") !== false
        && strpos($overviewView, 'Direct Bills') !== false
        && strpos($overviewView, 'Unique Bills') !== false
        && strpos($overviewView, "\$summary['all_invoice_count']") !== false
        && strpos($overviewView, "\$summary['all_revenue']") !== false,
    'Customer profile lists POS invoices with direct-bill type and bill preview action' =>
        strpos($salesController, 'si.is_direct_bill,') !== false
        && strpos($customerView, 'POS Invoices &amp; Bills') !== false
        && strpos($customerView, 'POS Bill</th>') !== false
        && strpos($customerView, 'View POS bill') !== false
        && strpos($customerView, '/m/billing/create?print_id=') !== false,
    'Cash and non-cash payment methods are classified into the intended channels' =>
        strpos($salesController, "si.payment_method = 'cash'") !== false
        && strpos($salesController, "si.payment_method <> 'cash' OR si.notes LIKE '%[Online Channel]%'") !== false
        && strpos($completeSaleMethod, "\$payMethod !== 'cash' || !empty(\$_POST['is_online'])") !== false
        && strpos($terminalView, 'id="online_classification_wrap"') !== false
        && strpos($terminalView, 'Show this cash sale in the Online section') !== false
        && strpos($terminalView, 'updateOnlineClassificationVisibility()') !== false,
    'Invoice list shows distinct labels for each payment method' =>
        strpos($overviewView, "'upi' => 'UPI / QR'") !== false
        && strpos($overviewView, "'card' => 'CARD / POS'") !== false
        && strpos($overviewView, "'split' => 'CASH + UPI'") !== false
        && strpos($overviewView, '$formatPaymentMethod($invoice[\'payment_method\'])') !== false,
    'POS terminal back button returns to the bill overview' =>
        strpos($terminalView, "Controller::url('/m/billing')") !== false
        && strpos($terminalView, 'title="Back to POS Bills"') !== false,
    'Completing a bill preserves the printable invoice preview' =>
        strpos($salesController, '"/m/billing/create?print_id={$invoiceId}"') !== false,
    'POS audit logging resolves the audit service namespace' =>
        strpos($salesController, 'use App\\Services\\AuditLog;') !== false
        && strpos($completeSaleMethod, 'AuditLog::log(\'POS_SALE\'') !== false,
    'POS sale uses a valid session concern instead of a posted or default concern ID' =>
        $completeSaleStart !== false
        && $completeSaleEnd !== false
        && strpos($completeSaleMethod, '$_POST[\'concern_id\']') === false
        && strpos($completeSaleMethod, "WHERE id = ? AND status = 'active'") !== false
        && strpos($completeSaleMethod, "No active concern is available for POS billing") !== false
        && strpos($terminalView, 'name="concern_id"') === false,
    'POS deducts each variant billed quantity across all eligible stock locations' =>
        strpos($completeSaleMethod, '$requestedQtyByVariant as $variantId => $requestedQty') !== false
        && strpos($completeSaleMethod, 'SELECT location, qty') !== false
        && strpos($completeSaleMethod, "WHEN location = 'Main Store' THEN 0") !== false
        && strpos($completeSaleMethod, "WHEN location = 'Main Store (WH-01)' THEN 1") !== false
        && strpos($completeSaleMethod, 'StockService::move(') !== false
        && strpos($completeSaleMethod, '$remainingQty -= $deductQty') !== false
        && strpos($completeSaleMethod, 'Remaining quantity to deduct') !== false,
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
