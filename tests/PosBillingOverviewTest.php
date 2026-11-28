<?php

$routes = file_get_contents(__DIR__ . '/../app/routes.php');
$overviewView = file_get_contents(__DIR__ . '/../app/views/concern/billing_overview.php');
$customerView = file_get_contents(__DIR__ . '/../app/views/concern/customers.php');
$terminalView = file_get_contents(__DIR__ . '/../app/views/concern/billing.php');
$salesController = file_get_contents(__DIR__ . '/../app/controllers/SalesController.php');
$numberSeries = file_get_contents(__DIR__ . '/../app/services/NumberSeries.php');
$invoiceMigration = file_get_contents(__DIR__ . '/../database/migrate_all_tables.php');
$invoiceSchema = file_get_contents(__DIR__ . '/../database/schema.sql');
$salesInvoiceSchema = file_get_contents(__DIR__ . '/../app/services/SalesInvoiceSchema.php');
$completeSaleStart = strpos($salesController, 'public function completePosSale(): void {');
$completeSaleEnd = strpos($salesController, 'public function storeInvoice(): void {', $completeSaleStart);
$completeSaleMethod = substr($salesController, $completeSaleStart, $completeSaleEnd - $completeSaleStart);
$scannerCardStart = strpos($terminalView, 'id="pos_scanner_card"');
$scannerCardEnd = strpos($terminalView, '<!-- Billing Cart Table', $scannerCardStart);
$inlineErrorPosition = strpos($terminalView, 'id="pos_inline_error"');
$customerColumnStart = strpos($terminalView, '<div class="col-lg-5">');
$rightColumnStart = strpos($terminalView, '<div class="col-lg-7">', $customerColumnStart);
$customerCardPosition = strpos($terminalView, 'Customer & Billed By', $customerColumnStart);
$couponsPanelPosition = strpos($terminalView, 'id="pos_coupons_panel"', $customerColumnStart);
$paymentMethodPosition = strpos($terminalView, '<!-- Payment Method Section -->', $customerColumnStart);
$scannerPositionInRightColumn = strpos($terminalView, 'id="pos_scanner_card"', $rightColumnStart);
$cartTableSectionStart = strpos($terminalView, '<!-- Billing Cart Table');
$cartTableSectionEnd = strpos($terminalView, '<!-- Two Summary Cards', $cartTableSectionStart);
$cartTableSection = substr($terminalView, $cartTableSectionStart, $cartTableSectionEnd - $cartTableSectionStart);
$a4InvoiceStart = strpos($terminalView, '<div class="d-none" id="a4_invoice_print_area">');
$a4InvoiceView = $a4InvoiceStart === false ? '' : substr($terminalView, $a4InvoiceStart);
$thermalReceiptStart = strpos($terminalView, 'id="thermal_receipt_print_area"');
$thermalReceiptView = $thermalReceiptStart === false || $a4InvoiceStart === false
    ? ''
    : substr($terminalView, $thermalReceiptStart, $a4InvoiceStart - $thermalReceiptStart);

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
    'Invoice numbering uses concern prefixes and renumbers existing invoices' =>
        strpos($numberSeries, "SELECT code FROM concerns WHERE id = ?") !== false
        && strpos($numberSeries, 'sprintf("%s%02d", $prefix, $nextNo)') !== false
        && strpos($invoiceMigration, 'ORDER BY invoice_date ASC, id ASC') !== false
        && strpos($invoiceMigration, "VALUES (?, 'INVOICE', ?, ?)") !== false,
    'POS invoices support full edits and retained void history' =>
        strpos($routes, "'/m/billing/update'") !== false
        && strpos($routes, "'/m/billing/void'") !== false
        && strpos($overviewView, '?edit_id=') !== false
        && strpos($overviewView, '/m/billing/void') !== false
        && strpos($salesController, 'restorePosInvoiceStock') !== false
        && strpos($salesController, 'reconcilePosInvoiceCash') !== false
        && strpos($salesController, 'releasePosCoupon') !== false
        && strpos($invoiceSchema, '`voided_at` datetime DEFAULT NULL') !== false
        && strpos($invoiceMigration, 'Renumbered ') !== false,
    'Voided invoices are excluded from active billing totals' =>
        strpos($salesController, "\$scopeSql = ' WHERE voided_at IS NULL'") !== false
        && strpos($salesController, "\$where = ['si.voided_at IS NULL']") !== false
        && strpos($salesController, 'si.voided_at IS NOT NULL') !== false,
    'Sales controller ensures void-history schema before querying invoices' =>
        strpos($salesController, 'SalesInvoiceSchema::ensure();') !== false
        && strpos($salesInvoiceSchema, 'ADD COLUMN `voided_at` DATETIME NULL') !== false
        && strpos($salesInvoiceSchema, 'ADD COLUMN `voided_by` INT NULL') !== false
        && strpos($salesInvoiceSchema, 'ADD COLUMN `void_reason` VARCHAR(255) NULL') !== false,
    'POS receipt price columns have fixed widths and clear labels' =>
        strpos($thermalReceiptView, 'class="w-100 thermal-receipt-items"') !== false
        && strpos($thermalReceiptView, '<col style="width: 48%;">') !== false
        && strpos($thermalReceiptView, 'MRP<br>Price') !== false
        && strpos($thermalReceiptView, 'Bill<br>Amount') !== false
        && strpos($thermalReceiptView, "\$it['mrp_price']") !== false
        && strpos($salesController, "COALESCE(NULLIF(v.mrp, 0), sii.unit_price) AS mrp_price") !== false,
    'A4 invoice places Bill To before invoice details in one row' =>
        strpos($terminalView, 'class="a4-invoice-parties"') !== false
        && strpos($terminalView, 'class="a4-invoice-customer"') < strpos($terminalView, 'class="a4-invoice-meta"')
        && strpos($terminalView, '<h3>Bill To</h3>') !== false
        && strpos($terminalView, '<h3>Invoice Details</h3>') !== false
        && strpos($terminalView, '.a4-invoice-parties{display:flex;') !== false,
    'A4 invoice item price columns use clear labels' =>
        strpos($terminalView, '<th class="number">Unit Price</th>') !== false
        && strpos($terminalView, '<th class="number">Taxable Amount</th>') !== false,
    'A4 invoice includes the concern logo and omits the redundant variant name' =>
        strpos($a4InvoiceView, "CloudinaryService::url(\$printInvoice['concern_logo'])") !== false
        && strpos($a4InvoiceView, 'class="a4-invoice-brand"') !== false
        && strpos($a4InvoiceView, 'class="a4-invoice-company-details"') !== false
        && strpos($a4InvoiceView, '.a4-invoice-brand{display:flex;align-items:flex-start;gap:10px;') !== false
        && strpos($a4InvoiceView, '<small>Variant:') === false,
    'A4 invoice removes product code and details the GST split' =>
        $a4InvoiceStart !== false
        && strpos($a4InvoiceView, '<th>Product Code</th>') === false
        && strpos($a4InvoiceView, '$it[\'product_code\']') === false
        && strpos($a4InvoiceView, 'CGST') !== false
        && strpos($a4InvoiceView, 'SGST') !== false
        && strpos($a4InvoiceView, 'IGST') !== false
        && strpos($a4InvoiceView, 'Total GST') !== false,
    'POS receipt puts customer beside right-aligned invoice details and omits production code' =>
        strpos($thermalReceiptView, 'thermal-receipt-customer-invoice') !== false
        && strpos($thermalReceiptView, 'class="text-end"') !== false
        && strpos($thermalReceiptView, '<strong>Inv No:</strong>') !== false
        && strpos($thermalReceiptView, '<strong>Date:</strong>') !== false
        && strpos($thermalReceiptView, 'product_code') === false
        && strpos($thermalReceiptView, 'Billing For:</strong>') === false
        && strpos($thermalReceiptView, 'GST Pricing:</strong>') === false,
    'Receipt discount details do not count coupons twice' =>
        strpos($terminalView, '$printItemDiscountAmount = max(0, (float)$printInvoice[\'discount_amount\'] - $printCouponDiscountAmount)') !== false
        && strpos($terminalView, 'Discount Given:</span>') === false
        && strpos($terminalView, 'Item Discount (included in subtotal):') !== false
        && strpos($terminalView, '<span>Coupon (') !== false
        && strpos($terminalView, '<tr><th>Coupon Discount</th><td>-₹') !== false,
    'POS audit logging resolves the audit service namespace' =>
        strpos($salesController, 'use App\\Services\\AuditLog;') !== false
        && strpos($completeSaleMethod, "AuditLog::log(\$existingInvoice ? 'POS_INVOICE_EDIT' : 'POS_SALE'") !== false,
    'POS sale uses a valid session concern instead of a posted or default concern ID' =>
        $completeSaleStart !== false
        && $completeSaleEnd !== false
        && strpos($completeSaleMethod, '$_POST[\'concern_id\']') === false
        && strpos($completeSaleMethod, "WHERE id = ? AND status = 'active'") !== false
        && strpos($completeSaleMethod, "No active concern is available for POS billing") !== false
        && strpos($terminalView, 'name="concern_id"') === false,
    'POS prevents combining products from different concerns' =>
        strpos($salesController, "'concern_id'    => (int)\$variant['concern_id']") !== false
        && strpos($terminalView, 'Number(cart[0].concern_id) !== Number(v.concern_id)') !== false
        && strpos($completeSaleMethod, 'Products from different concerns cannot be combined in one POS invoice.') !== false,
    'POS scanner errors render inside the scanner card' =>
        $scannerCardStart !== false
        && $scannerCardEnd !== false
        && $inlineErrorPosition > $scannerCardStart
        && $inlineErrorPosition < $scannerCardEnd,
    'POS customer details and coupons are on the left of scanner and sale details' =>
        $customerColumnStart !== false
        && $rightColumnStart !== false
        && $customerCardPosition > $customerColumnStart
        && $couponsPanelPosition > $customerCardPosition
        && $paymentMethodPosition > $couponsPanelPosition
        && $rightColumnStart > $paymentMethodPosition
        && $scannerPositionInRightColumn > $rightColumnStart,
    'Coupons are hidden and cleared for company bills' =>
        strpos($terminalView, "posCouponsPanel.classList.toggle('d-none', isCompany)") !== false
        && strpos($terminalView, "document.getElementById('pos_coupon_select').value = '';") !== false
        && strpos($completeSaleMethod, "Coupons can only be applied to customer bills.") !== false,
    'Customer channel/source is hidden and ignored for company bills' =>
        strpos($terminalView, 'id="customer_channel_wrap"') !== false
        && strpos($terminalView, "customerChannelWrap.classList.toggle('d-none', isCompany)") !== false
        && strpos($terminalView, "channelSelect.value = 'direct';") !== false
        && strpos($completeSaleMethod, "\$billingPartyType === 'company'") !== false
        && strpos($completeSaleMethod, "? 'direct'") !== false,
    'POS cart table fits the panel without horizontal scrolling' =>
        $cartTableSectionStart !== false
        && $cartTableSectionEnd !== false
        && strpos($cartTableSection, 'class="table-responsive"') === false
        && strpos($cartTableSection, 'table-layout: fixed') !== false
        && strpos($cartTableSection, '@media (max-width: 575.98px)') !== false,
    'Billed price keeps the currency symbol and input on one line' =>
        strpos($terminalView, 'style="width: 100%; min-width: 0; flex-wrap: nowrap;"') !== false
        && strpos($terminalView, 'style="width: 1%; flex: 1 1 0; min-width: 0;') !== false,
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
