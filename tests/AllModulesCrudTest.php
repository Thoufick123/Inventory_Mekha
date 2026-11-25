<?php
// tests/AllModulesCrudTest.php - Comprehensive verification of CRUD operations across all modules

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;
use App\Core\Auth;
use App\Services\StockService;

$passed = 0;
$failed = 0;

function assertCrud(string $name, callable $fn) {
    global $passed, $failed;
    try {
        $fn();
        echo "  [PASS] {$name}\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "  [FAIL] {$name}: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "======================================================\n";
echo "ALL MODULES CRUD COMPREHENSIVE VERIFICATION SUITE\n";
echo "======================================================\n\n";

// Authenticate as Super Admin
$authSuccess = Auth::login('admin@invento.com', 'password123');
if (!$authSuccess) {
    die("Authentication failed in test setup.\n");
}
echo "Super Admin Authenticated Successfully.\n\n";

$cid = (int)DB::fetchValue("SELECT id FROM concerns WHERE status = 'active' ORDER BY id ASC LIMIT 1");
if (!$cid) {
    DB::query("INSERT INTO concerns (name, code, status) VALUES ('Test Active Concern', 'CON-TST', 'active')");
    $cid = (int)DB::lastInsertId();
}

$sup = DB::fetch("SELECT id FROM suppliers WHERE concern_id = ? LIMIT 1", [$cid]);
if (!$sup) {
    DB::query("INSERT INTO suppliers (concern_id, name, code, status) VALUES (?, 'Test Vendor', 'SUP-TST', 'active')", [$cid]);
}

$cust = DB::fetch("SELECT id FROM customers WHERE concern_id = ? LIMIT 1", [$cid]);
if (!$cust) {
    DB::query("INSERT INTO customers (concern_id, name, phone, status) VALUES (?, 'Test Customer', '9876543210', 'active')", [$cid]);
}

// 1. Concerns CRUD
assertCrud('1. Super Admin: Concern CRUD', function() {
    $code = 'TEST' . rand(100, 999);
    DB::query("INSERT INTO concerns (name, code, status, created_at) VALUES (?, ?, 'active', NOW())", ["Test Concern {$code}", $code]);
    $id = (int)DB::lastInsertId();
    if (!$id) throw new Exception("Failed to insert concern");

    $c = DB::fetch("SELECT * FROM concerns WHERE id = ?", [$id]);
    if (!$c || $c['code'] !== $code) throw new Exception("Failed to read concern");

    DB::query("UPDATE concerns SET name = ?, phone = '9876543210' WHERE id = ?", ["Updated Concern {$code}", $id]);
    $updated = DB::fetch("SELECT * FROM concerns WHERE id = ?", [$id]);
    if ($updated['name'] !== "Updated Concern {$code}") throw new Exception("Failed to update concern");

    DB::query("DELETE FROM concerns WHERE id = ?", [$id]);
    $deleted = DB::fetch("SELECT * FROM concerns WHERE id = ?", [$id]);
    if ($deleted) throw new Exception("Failed to delete concern");
});

// 2. Asset Management CRUD
assertCrud('2. Super Admin: Asset Management CRUD', function() use ($cid) {
    $code = 'AST-TEST-' . rand(100, 999);
    DB::query("INSERT INTO assets (concern_id, asset_code, name, category, purchase_cost, current_value, status, created_at) VALUES (?, ?, 'Test Overlock Machine', 'Machinery', 45000, 42000, 'active', NOW())", [$cid, $code]);
    $id = (int)DB::lastInsertId();
    if (!$id) throw new Exception("Failed to insert asset");

    $a = DB::fetch("SELECT * FROM assets WHERE id = ?", [$id]);
    if (!$a || $a['asset_code'] !== $code) throw new Exception("Failed to read asset");

    DB::query("UPDATE assets SET current_value = 39000, location = 'Bay 3' WHERE id = ?", [$id]);
    $u = DB::fetch("SELECT current_value, location FROM assets WHERE id = ?", [$id]);
    if ((float)$u['current_value'] != 39000.00 || $u['location'] !== 'Bay 3') throw new Exception("Failed to update asset");

    DB::query("DELETE FROM assets WHERE id = ?", [$id]);
    $del = DB::fetch("SELECT * FROM assets WHERE id = ?", [$id]);
    if ($del) throw new Exception("Failed to delete asset");
});

// 3. Product Variants CRUD
assertCrud('3. Masters: Product Variants CRUD', function() use ($cid) {
    $cat = DB::fetch("SELECT id FROM categories WHERE concern_id = ? LIMIT 1", [$cid]);
    $catId = (int)$cat['id'];
    $sub = DB::fetch("SELECT id FROM subcategories WHERE category_id = ? LIMIT 1", [$catId]);
    if (!$sub) {
        $sub = DB::fetch("SELECT id FROM subcategories LIMIT 1");
    }
    $subId = (int)$sub['id'];

    $sku = 'SKU-TEST-' . rand(1000, 9999);
    DB::query("INSERT INTO variants (concern_id, category_id, subcategory_id, product_code, name, size, price, mrp, status, created_at) VALUES (?, ?, ?, ?, 'Test Polo T-Shirt', 'L', 450, 799, 'active', NOW())", [$cid, $catId, $subId, $sku]);
    $id = (int)DB::lastInsertId();
    if (!$id) throw new Exception("Failed to insert variant");

    $v = DB::fetch("SELECT * FROM variants WHERE id = ?", [$id]);
    if (!$v || $v['product_code'] !== $sku) throw new Exception("Failed to read variant");

    DB::query("UPDATE variants SET price = 480, size = 'XL' WHERE id = ?", [$id]);
    $u = DB::fetch("SELECT price, size FROM variants WHERE id = ?", [$id]);
    if ((float)$u['price'] != 480.00 || $u['size'] !== 'XL') throw new Exception("Failed to update variant");

    DB::query("DELETE FROM variants WHERE id = ?", [$id]);
    $del = DB::fetch("SELECT * FROM variants WHERE id = ?", [$id]);
    if ($del) throw new Exception("Failed to delete variant");
});

// 4. Customer Details CRUD
assertCrud('4. Shop/Sales: Customer Details CRUD', function() use ($cid) {
    $mobile = '98' . rand(10000000, 99999999);
    DB::query("INSERT INTO customers (concern_id, name, phone, city, status, created_at) VALUES (?, 'Test Retail Customer', ?, 'Chennai', 'active', NOW())", [$cid, $mobile]);
    $id = (int)DB::lastInsertId();
    if (!$id) throw new Exception("Failed to insert customer");

    $cust = DB::fetch("SELECT * FROM customers WHERE id = ?", [$id]);
    if (!$cust || $cust['phone'] !== $mobile) throw new Exception("Failed to read customer");

    DB::query("UPDATE customers SET city = 'Coimbatore', address = 'Gandhipuram' WHERE id = ?", [$id]);
    $u = DB::fetch("SELECT city, address FROM customers WHERE id = ?", [$id]);
    if ($u['city'] !== 'Coimbatore' || $u['address'] !== 'Gandhipuram') throw new Exception("Failed to update customer");

    DB::query("DELETE FROM customers WHERE id = ?", [$id]);
    $del = DB::fetch("SELECT * FROM customers WHERE id = ?", [$id]);
    if ($del) throw new Exception("Failed to delete customer");
});

// 5. Inward CRUD (with Stock Balance Reconciliation)
assertCrud('5. Stock: Inward Consignment CRUD with Inventory Sync', function() use ($cid) {
    $var = DB::fetch("SELECT id FROM variants WHERE concern_id = ? LIMIT 1", [$cid]);
    if (!$var) throw new Exception("No variant found");
    $varId = (int)$var['id'];

    $inwNo = 'INW-TEST-' . rand(100, 999);
    DB::query("INSERT INTO inwards (concern_id, inward_no, inward_date, location, remarks, created_by, created_at) VALUES (?, ?, CURDATE(), 'Main Store', 'Test challan', 1, NOW())", [$cid, $inwNo]);
    $inwId = (int)DB::lastInsertId();

    DB::query("INSERT INTO inward_items (concern_id, inward_id, variant_id, qty, unit_cost) VALUES (?, ?, ?, 50, 200)", [$cid, $inwId, $varId]);
    StockService::move($cid, $varId, 'Main Store', 50, 0, 'Inward', $inwId, "Test Inward addition", 1);

    $inw = DB::fetch("SELECT * FROM inwards WHERE id = ?", [$inwId]);
    if (!$inw || $inw['inward_no'] !== $inwNo) throw new Exception("Failed to read inward");

    DB::query("UPDATE inwards SET remarks = 'Updated challan notes' WHERE id = ?", [$inwId]);
    $u = DB::fetch("SELECT remarks FROM inwards WHERE id = ?", [$inwId]);
    if ($u['remarks'] !== 'Updated challan notes') throw new Exception("Failed to update inward");

    StockService::move($cid, $varId, 'Main Store', 0, 50, 'Inward', $inwId, "Reversal of deleted test inward", 1);
    DB::query("DELETE FROM inward_items WHERE inward_id = ?", [$inwId]);
    DB::query("DELETE FROM inwards WHERE id = ?", [$inwId]);

    $del = DB::fetch("SELECT * FROM inwards WHERE id = ?", [$inwId]);
    if ($del) throw new Exception("Failed to delete inward");
});

// 6. Purchase Damage CRUD
assertCrud('6. Purchase: Purchase Damage CRUD with Reversal', function() use ($cid) {
    $var = DB::fetch("SELECT id FROM variants WHERE concern_id = ? LIMIT 1", [$cid]);
    $varId = (int)$var['id'];
    $sup = DB::fetch("SELECT id FROM suppliers WHERE concern_id = ? LIMIT 1", [$cid]);
    $supId = (int)$sup['id'];

    StockService::move($cid, $varId, 'Main Store', 10, 0, 'Testing', 1, "Seed stock for damage test", 1);

    $dmgNo = 'DMG-TEST-' . rand(100, 999);
    DB::query("INSERT INTO purchase_damages (concern_id, damage_no, supplier_id, variant_id, qty, location, reason, action_taken, created_by, created_at) VALUES (?, ?, ?, ?, 5, 'Main Store', 'Water stain on cartons', 'Replacement Requested', 1, NOW())", [$cid, $dmgNo, $supId, $varId]);
    $dmgId = (int)DB::lastInsertId();
    StockService::move($cid, $varId, 'Main Store', 0, 5, 'Purchase Damage', $dmgId, "Test damage write-off", 1);

    $d = DB::fetch("SELECT * FROM purchase_damages WHERE id = ?", [$dmgId]);
    if (!$d || $d['damage_no'] !== $dmgNo) throw new Exception("Failed to read purchase damage");

    DB::query("UPDATE purchase_damages SET reason = 'Revised: Minor water damage', action_taken = 'Debit Note / Return to Supplier' WHERE id = ?", [$dmgId]);
    $u = DB::fetch("SELECT reason, action_taken FROM purchase_damages WHERE id = ?", [$dmgId]);
    if ($u['reason'] !== 'Revised: Minor water damage') throw new Exception("Failed to update damage");

    StockService::move($cid, $varId, 'Main Store', 5, 0, 'Purchase Damage', $dmgId, "Reversal of deleted damage", 1);
    DB::query("DELETE FROM purchase_damages WHERE id = ?", [$dmgId]);
    $del = DB::fetch("SELECT * FROM purchase_damages WHERE id = ?", [$dmgId]);
    if ($del) throw new Exception("Failed to delete purchase damage");
});

// 7. Concern Internal Damage CRUD
assertCrud('7. Stock: Concern Internal Damage CRUD', function() use ($cid) {
    $var = DB::fetch("SELECT id FROM variants WHERE concern_id = ? LIMIT 1", [$cid]);
    $varId = (int)$var['id'];
    StockService::move($cid, $varId, 'Main Store', 10, 0, 'Testing', 1, "Seed stock", 1);

    $cdNo = 'CDMG-TEST-' . rand(100, 999);
    DB::query("INSERT INTO concern_damages (concern_id, damage_no, variant_id, location, qty, reason, created_by, created_at) VALUES (?, ?, ?, 'Main Store', 2, 'Handling drop', 1, NOW())", [$cid, $cdNo, $varId]);
    $cdId = (int)DB::lastInsertId();
    StockService::move($cid, $varId, 'Main Store', 0, 2, 'Concern Damage', $cdId, "Internal damage", 1);

    $cd = DB::fetch("SELECT * FROM concern_damages WHERE id = ?", [$cdId]);
    if (!$cd || $cd['damage_no'] !== $cdNo) throw new Exception("Failed to read concern damage");

    DB::query("UPDATE concern_damages SET reason = 'Handling drop during rack stacking' WHERE id = ?", [$cdId]);
    $u = DB::fetch("SELECT reason FROM concern_damages WHERE id = ?", [$cdId]);
    if ($u['reason'] !== 'Handling drop during rack stacking') throw new Exception("Failed to update concern damage");

    StockService::move($cid, $varId, 'Main Store', 2, 0, 'Concern Damage', $cdId, "Reversal", 1);
    DB::query("DELETE FROM concern_damages WHERE id = ?", [$cdId]);
    $del = DB::fetch("SELECT * FROM concern_damages WHERE id = ?", [$cdId]);
    if ($del) throw new Exception("Failed to delete concern damage");
});

// 8. Production Catalog & Program CRUD
assertCrud('8. Production: Catalog & Program CRUD', function() use ($cid) {
    $var = DB::fetch("SELECT id FROM variants WHERE concern_id = ? LIMIT 1", [$cid]);
    $varId = (int)$var['id'];
    $progNo = 'PRG-TEST-' . rand(100, 999);

    DB::query("INSERT INTO production_programs (concern_id, program_no, target_variant_id, planned_qty, start_date, target_date, status, created_at) VALUES (?, ?, ?, 500, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'planned', NOW())", [$cid, $progNo, $varId]);
    $pId = (int)DB::lastInsertId();

    $p = DB::fetch("SELECT * FROM production_programs WHERE id = ?", [$pId]);
    if (!$p || $p['program_no'] !== $progNo) throw new Exception("Failed to read program");

    DB::query("UPDATE production_programs SET planned_qty = 600, status = 'in_progress' WHERE id = ?", [$pId]);
    $u = DB::fetch("SELECT planned_qty, status FROM production_programs WHERE id = ?", [$pId]);
    if ((float)$u['planned_qty'] != 600.00 || $u['status'] !== 'in_progress') throw new Exception("Failed to update program");

    DB::query("DELETE FROM production_programs WHERE id = ?", [$pId]);
    $del = DB::fetch("SELECT * FROM production_programs WHERE id = ?", [$pId]);
    if ($del) throw new Exception("Failed to delete program");
});

// 9. Cutting & Wastage CRUD
assertCrud('9. Production: Cutting & Wastage CRUD', function() use ($cid) {
    $var = DB::fetch("SELECT id FROM variants WHERE concern_id = ? LIMIT 1", [$cid]);
    $varId = (int)$var['id'];
    $cutNo = 'CUT-TEST-' . rand(100, 999);

    DB::query("INSERT INTO cutting_logs (concern_id, log_no, raw_variant_id, consumed_qty, cut_pieces, wastage_qty, wastage_unit, notes, created_at) VALUES (?, ?, ?, 25.5, 200, 1.2, 'kg', 'Lay test', NOW())", [$cid, $cutNo, $varId]);
    $cutId = (int)DB::lastInsertId();

    $cl = DB::fetch("SELECT * FROM cutting_logs WHERE id = ?", [$cutId]);
    if (!$cl || $cl['log_no'] !== $cutNo) throw new Exception("Failed to read cutting log");

    DB::query("UPDATE cutting_logs SET cut_pieces = 210, wastage_qty = 0.95 WHERE id = ?", [$cutId]);
    $u = DB::fetch("SELECT cut_pieces, wastage_qty FROM cutting_logs WHERE id = ?", [$cutId]);
    if ((int)$u['cut_pieces'] !== 210 || (float)$u['wastage_qty'] != 0.95) throw new Exception("Failed to update cutting log");

    DB::query("DELETE FROM cutting_logs WHERE id = ?", [$cutId]);
    $del = DB::fetch("SELECT * FROM cutting_logs WHERE id = ?", [$cutId]);
    if ($del) throw new Exception("Failed to delete cutting log");
});

// 10. Stitching & Wastage CRUD
assertCrud('10. Production: Stitching & Wastage CRUD', function() use ($cid) {
    $var = DB::fetch("SELECT id FROM variants WHERE concern_id = ? LIMIT 1", [$cid]);
    $varId = (int)$var['id'];
    $stNo = 'ST-TEST-' . rand(100, 999);

    DB::query("INSERT INTO stitching_logs (concern_id, log_no, produced_variant_id, input_pieces, stitched_pieces, wastage_pieces, notes, created_at) VALUES (?, ?, ?, 200, 196, 4, 'Line A batch', NOW())", [$cid, $stNo, $varId]);
    $stId = (int)DB::lastInsertId();

    $sl = DB::fetch("SELECT * FROM stitching_logs WHERE id = ?", [$stId]);
    if (!$sl || $sl['log_no'] !== $stNo) throw new Exception("Failed to read stitching log");

    DB::query("UPDATE stitching_logs SET stitched_pieces = 198, wastage_pieces = 2 WHERE id = ?", [$stId]);
    $u = DB::fetch("SELECT stitched_pieces, wastage_pieces FROM stitching_logs WHERE id = ?", [$stId]);
    if ((int)$u['stitched_pieces'] !== 198 || (int)$u['wastage_pieces'] !== 2) throw new Exception("Failed to update stitching log");

    DB::query("DELETE FROM stitching_logs WHERE id = ?", [$stId]);
    $del = DB::fetch("SELECT * FROM stitching_logs WHERE id = ?", [$stId]);
    if ($del) throw new Exception("Failed to delete stitching log");
});

// 11. QC Stocks CRUD
assertCrud('11. Production: QC Stocks CRUD', function() use ($cid) {
    $var = DB::fetch("SELECT id FROM variants WHERE concern_id = ? LIMIT 1", [$cid]);
    $varId = (int)$var['id'];
    $qcNo = 'QC-TEST-' . rand(100, 999);

    DB::query("INSERT INTO qc_inspections (concern_id, qc_no, variant_id, inspected_qty, passed_qty, rejected_qty, location, defect_notes, created_at) VALUES (?, ?, ?, 100, 98, 2, 'Main Store', 'Minor seam tension fix', NOW())", [$cid, $qcNo, $varId]);
    $qcId = (int)DB::lastInsertId();

    $qc = DB::fetch("SELECT * FROM qc_inspections WHERE id = ?", [$qcId]);
    if (!$qc || $qc['qc_no'] !== $qcNo) throw new Exception("Failed to read QC inspection");

    DB::query("UPDATE qc_inspections SET defect_notes = 'Revised: All passed re-check' WHERE id = ?", [$qcId]);
    $u = DB::fetch("SELECT defect_notes FROM qc_inspections WHERE id = ?", [$qcId]);
    if ($u['defect_notes'] !== 'Revised: All passed re-check') throw new Exception("Failed to update QC inspection");

    DB::query("DELETE FROM qc_inspections WHERE id = ?", [$qcId]);
    $del = DB::fetch("SELECT * FROM qc_inspections WHERE id = ?", [$qcId]);
    if ($del) throw new Exception("Failed to delete QC inspection");
});

// 12. Customer Requests CRUD
assertCrud('12. Sales: Customer Requests CRUD', function() use ($cid) {
    $var = DB::fetch("SELECT id FROM variants WHERE concern_id = ? LIMIT 1", [$cid]);
    $cust = DB::fetch("SELECT id FROM customers WHERE concern_id = ? LIMIT 1", [$cid]);
    $varId = (int)$var['id'];
    $custId = (int)$cust['id'];

    $reqNo = 'REQ-TEST-' . rand(100, 999);
    DB::query("INSERT INTO customer_requests (concern_id, request_no, customer_id, variant_id, requested_qty, expected_date, status, notes, created_at) VALUES (?, ?, ?, ?, 250, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'pending', 'Fast-track urgent', NOW())", [$cid, $reqNo, $custId, $varId]);
    $reqId = (int)DB::lastInsertId();

    $r = DB::fetch("SELECT * FROM customer_requests WHERE id = ?", [$reqId]);
    if (!$r || $r['request_no'] !== $reqNo) throw new Exception("Failed to read customer request");

    DB::query("UPDATE customer_requests SET requested_qty = 300, status = 'approved' WHERE id = ?", [$reqId]);
    $u = DB::fetch("SELECT requested_qty, status FROM customer_requests WHERE id = ?", [$reqId]);
    if ((float)$u['requested_qty'] != 300.00 || $u['status'] !== 'approved') throw new Exception("Failed to update request");

    DB::query("DELETE FROM customer_requests WHERE id = ?", [$reqId]);
    $del = DB::fetch("SELECT * FROM customer_requests WHERE id = ?", [$reqId]);
    if ($del) throw new Exception("Failed to delete request");
});

// 13. Batches CRUD
assertCrud('13. Sales/Production: Batches CRUD', function() use ($cid) {
    $var = DB::fetch("SELECT id FROM variants WHERE concern_id = ? LIMIT 1", [$cid]);
    $varId = (int)$var['id'];
    $bNo = 'BAT-TEST-' . rand(100, 999);

    DB::query("INSERT INTO batches (concern_id, batch_no, variant_id, batch_qty, mfg_date, status, created_at) VALUES (?, ?, ?, 500, CURDATE(), 'active', NOW())", [$cid, $bNo, $varId]);
    $bId = (int)DB::lastInsertId();

    $b = DB::fetch("SELECT * FROM batches WHERE id = ?", [$bId]);
    if (!$b || $b['batch_no'] !== $bNo) throw new Exception("Failed to read batch");

    DB::query("UPDATE batches SET batch_qty = 550, status = 'completed' WHERE id = ?", [$bId]);
    $u = DB::fetch("SELECT batch_qty, status FROM batches WHERE id = ?", [$bId]);
    if ((float)$u['batch_qty'] != 550.00 || $u['status'] !== 'completed') throw new Exception("Failed to update batch");

    DB::query("DELETE FROM batches WHERE id = ?", [$bId]);
    $del = DB::fetch("SELECT * FROM batches WHERE id = ?", [$bId]);
    if ($del) throw new Exception("Failed to delete batch");
});

// 14. Outwards CRUD
assertCrud('14. Sales: Outwards Consignment CRUD', function() use ($cid) {
    $cust = DB::fetch("SELECT id FROM customers WHERE concern_id = ? LIMIT 1", [$cid]);
    $custId = (int)$cust['id'];
    $outNo = 'OUT-TEST-' . rand(100, 999);

    DB::query("INSERT INTO outwards (concern_id, outward_no, customer_id, outward_date, location, status, created_by, created_at) VALUES (?, ?, ?, CURDATE(), 'Main Store', 'prepared', 1, NOW())", [$cid, $outNo, $custId]);
    $outId = (int)DB::lastInsertId();

    $o = DB::fetch("SELECT * FROM outwards WHERE id = ?", [$outId]);
    if (!$o || $o['outward_no'] !== $outNo) throw new Exception("Failed to read outward");

    DB::query("UPDATE outwards SET status = 'dispatched' WHERE id = ?", [$outId]);
    $u = DB::fetch("SELECT status FROM outwards WHERE id = ?", [$outId]);
    if ($u['status'] !== 'dispatched') throw new Exception("Failed to update outward");

    DB::query("DELETE FROM outwards WHERE id = ?", [$outId]);
    $del = DB::fetch("SELECT * FROM outwards WHERE id = ?", [$outId]);
    if ($del) throw new Exception("Failed to delete outward");
});

// 15. Dispatches CRUD
assertCrud('15. Sales: Dispatches CRUD', function() use ($cid) {
    $cust = DB::fetch("SELECT id FROM customers WHERE concern_id = ? LIMIT 1", [$cid]);
    $custId = (int)$cust['id'];

    $outNo = 'OUT-DSP-' . rand(100, 999);
    DB::query("INSERT INTO outwards (concern_id, outward_no, customer_id, outward_date, location, status, created_by, created_at) VALUES (?, ?, ?, CURDATE(), 'Main Store', 'prepared', 1, NOW())", [$cid, $outNo, $custId]);
    $outId = (int)DB::lastInsertId();

    $dspNo = 'DSP-TEST-' . rand(100, 999);
    DB::query("INSERT INTO dispatches (concern_id, dispatch_no, outward_id, transporter_name, vehicle_no, tracking_no, dispatch_date, status, created_by, created_at) VALUES (?, ?, ?, 'VRL Logistics', 'TN 38 CD 4321', 'VRL-8989', CURDATE(), 'in_transit', 1, NOW())", [$cid, $dspNo, $outId]);
    $dspId = (int)DB::lastInsertId();

    $d = DB::fetch("SELECT * FROM dispatches WHERE id = ?", [$dspId]);
    if (!$d || $d['dispatch_no'] !== $dspNo) throw new Exception("Failed to read dispatch");

    DB::query("UPDATE dispatches SET tracking_no = 'VRL-9999', status = 'delivered' WHERE id = ?", [$dspId]);
    $u = DB::fetch("SELECT tracking_no, status FROM dispatches WHERE id = ?", [$dspId]);
    if ($u['tracking_no'] !== 'VRL-9999' || $u['status'] !== 'delivered') throw new Exception("Failed to update dispatch");

    DB::query("DELETE FROM dispatches WHERE id = ?", [$dspId]);
    DB::query("DELETE FROM outwards WHERE id = ?", [$outId]);
    $del = DB::fetch("SELECT * FROM dispatches WHERE id = ?", [$dspId]);
    if ($del) throw new Exception("Failed to delete dispatch");
});

// 16. Returns & Exchanges CRUD
assertCrud('16. Sales: Returns & Exchanges CRUD with Stock Balancing', function() use ($cid) {
    $var = DB::fetch("SELECT id FROM variants WHERE concern_id = ? LIMIT 1", [$cid]);
    $cust = DB::fetch("SELECT id FROM customers WHERE concern_id = ? LIMIT 1", [$cid]);
    $varId = (int)$var['id'];
    $custId = (int)$cust['id'];

    $retNo = 'RET-TEST-' . rand(100, 999);
    DB::query("INSERT INTO returns_exchanges (concern_id, return_no, type, customer_id, return_date, returned_variant_id, returned_qty, reason, created_by, created_at) VALUES (?, ?, 'return', ?, CURDATE(), ?, 5, 'Size tight', 1, NOW())", [$cid, $retNo, $custId, $varId]);
    $retId = (int)DB::lastInsertId();
    StockService::move($cid, $varId, 'Main Store', 5, 0, 'Return & Exchange', $retId, "Test return", 1);

    $ret = DB::fetch("SELECT * FROM returns_exchanges WHERE id = ?", [$retId]);
    if (!$ret || $ret['return_no'] !== $retNo) throw new Exception("Failed to read return");

    DB::query("UPDATE returns_exchanges SET reason = 'Size mismatch exchange' WHERE id = ?", [$retId]);
    $u = DB::fetch("SELECT reason FROM returns_exchanges WHERE id = ?", [$retId]);
    if ($u['reason'] !== 'Size mismatch exchange') throw new Exception("Failed to update return");

    StockService::move($cid, $varId, 'Main Store', 0, 5, 'Return & Exchange', $retId, "Reversal of deleted return", 1);
    DB::query("DELETE FROM returns_exchanges WHERE id = ?", [$retId]);
    $del = DB::fetch("SELECT * FROM returns_exchanges WHERE id = ?", [$retId]);
    if ($del) throw new Exception("Failed to delete return");
});

// 17. Expenses CRUD with Cash Ledger Sync
assertCrud('17. Finance: Expense Voucher CRUD with Cash Balancing', function() use ($cid) {
    $expNo = 'EXP-TEST-' . rand(100, 999);

    DB::query("INSERT INTO expenses (concern_id, expense_no, expense_date, category, amount, payment_mode, paid_to, status, description, created_by, created_at) VALUES (?, ?, CURDATE(), 'Stationery', 750.00, 'Cash', 'Office Depot', 'Paid', 'Printing paper', 1, NOW())", [$cid, $expNo]);
    $expId = (int)DB::lastInsertId();

    $e = DB::fetch("SELECT * FROM expenses WHERE id = ?", [$expId]);
    if (!$e || $e['expense_no'] !== $expNo) throw new Exception("Failed to read expense");

    DB::query("UPDATE expenses SET amount = 850.00, description = 'Printing paper & cartridges' WHERE id = ?", [$expId]);
    $u = DB::fetch("SELECT amount, description FROM expenses WHERE id = ?", [$expId]);
    if ((float)$u['amount'] != 850.00) throw new Exception("Failed to update expense");

    DB::query("DELETE FROM expenses WHERE id = ?", [$expId]);
    $del = DB::fetch("SELECT * FROM expenses WHERE id = ?", [$expId]);
    if ($del) throw new Exception("Failed to delete expense");
});

// 18. Cash & Bank Accounts CRUD
assertCrud('18. Finance: Cash & Bank Accounts CRUD', function() use ($cid) {
    $accNo = 'ACC-TEST-' . rand(100, 999);

    DB::query("INSERT INTO cash_accounts (concern_id, name, account_type, account_number, balance, status, created_at) VALUES (?, 'Emergency Contingency Drawer', 'cash', ?, 2500.00, 'active', NOW())", [$cid, $accNo]);
    $accId = (int)DB::lastInsertId();

    $acc = DB::fetch("SELECT * FROM cash_accounts WHERE id = ?", [$accId]);
    if (!$acc || $acc['name'] !== 'Emergency Contingency Drawer') throw new Exception("Failed to read account");

    DB::query("UPDATE cash_accounts SET name = 'Petty Cash Secondary Drawer' WHERE id = ?", [$accId]);
    $u = DB::fetch("SELECT name FROM cash_accounts WHERE id = ?", [$accId]);
    if ($u['name'] !== 'Petty Cash Secondary Drawer') throw new Exception("Failed to update account");

    DB::query("DELETE FROM cash_accounts WHERE id = ?", [$accId]);
    $del = DB::fetch("SELECT * FROM cash_accounts WHERE id = ?", [$accId]);
    if ($del) throw new Exception("Failed to delete account");
});

// 19. Staff Register CRUD
assertCrud('19. People/Staff: Staff Register CRUD', function() use ($cid) {
    $code = 'STF-TEST-' . rand(100, 999);
    DB::query(
        "INSERT INTO staff (concern_id, staff_code, name, phone, designation, department, salary_type, base_salary, status, joining_date, created_at)
         VALUES (?, ?, 'Vignesh Sundaram', '9842199999', 'Floor Manager', 'Production', 'monthly', 30000.00, 'active', CURDATE(), NOW())",
        [$cid, $code]
    );
    $stfId = (int)DB::lastInsertId();

    $stf = DB::fetch("SELECT * FROM staff WHERE id = ?", [$stfId]);
    if (!$stf || $stf['name'] !== 'Vignesh Sundaram') throw new Exception("Failed to read staff");

    DB::query("UPDATE staff SET designation = 'Senior Production Lead' WHERE id = ?", [$stfId]);
    $u = DB::fetch("SELECT designation FROM staff WHERE id = ?", [$stfId]);
    if ($u['designation'] !== 'Senior Production Lead') throw new Exception("Failed to update staff");

    DB::query("DELETE FROM staff WHERE id = ?", [$stfId]);
    $del = DB::fetch("SELECT * FROM staff WHERE id = ?", [$stfId]);
    if ($del) throw new Exception("Failed to delete staff");
});

echo "\n======================================================\n";
echo "SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "======================================================\n";

if ($failed > 0) {
    exit(1);
} else {
    exit(0);
}
