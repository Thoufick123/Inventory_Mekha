<?php
// app/routes.php

use App\Core\Router;
use App\Core\Auth;
use App\Controllers\AuthController;
use App\Controllers\AdminController;
use App\Controllers\ConcernDashboardController;
use App\Controllers\VariantController;
use App\Controllers\LabelController;
use App\Controllers\StockController;
use App\Controllers\InwardController;
use App\Controllers\PurchaseController;
use App\Controllers\PurchaseStockController;
use App\Controllers\SalesController;
use App\Controllers\ProductionController;
use App\Controllers\CatalogMappingController;
use App\Controllers\FinanceController;
use App\Controllers\DamageController;
use App\Controllers\StaffController;

/** @var Router $router */

// Home redirection
$router->get('/', function() {
    if (!Auth::check()) {
        header('Location: ' . \App\Core\Controller::url('/login'));
        exit;
    }
    if (Auth::isSuperAdmin()) {
        header('Location: ' . \App\Core\Controller::url('/admin/dashboard'));
    } else {
        header('Location: ' . \App\Core\Controller::url('/dashboard'));
    }
    exit;
});

// Authentication
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);
$router->get('/select-concern', [AuthController::class, 'selectConcern']);
$router->post('/select-concern', [AuthController::class, 'setConcern']);
$router->get('/switch-concern', [AuthController::class, 'switchConcernAction']);

// ==========================================
// SUPER ADMIN MODULES (guarded 'super_admin')
// ==========================================
$router->get('/admin/dashboard', [AdminController::class, 'dashboard'], 'super_admin');
$router->get('/admin/dashboard/export', [AdminController::class, 'exportReports'], 'super_admin');
$router->get('/admin/switch-concern', [AdminController::class, 'switchConcern'], 'super_admin');
$router->get('/admin/concerns', [AdminController::class, 'concerns'], 'super_admin');
$router->get('/admin/concerns/export', [AdminController::class, 'exportConcerns'], 'super_admin');
$router->get('/admin/concerns/download', [AdminController::class, 'downloadConcernDossier'], 'super_admin');
$router->get('/admin/concerns/template', [AdminController::class, 'downloadConcernTemplate'], 'super_admin');
$router->post('/admin/concerns/store', [AdminController::class, 'storeConcern'], 'super_admin');
$router->post('/admin/concerns/update', [AdminController::class, 'updateConcern'], 'super_admin');
$router->post('/admin/concerns/delete', [AdminController::class, 'deleteConcern'], 'super_admin');
$router->post('/admin/concerns/toggle-module', [AdminController::class, 'toggleModule'], 'super_admin');
$router->get('/admin/assets', [AdminController::class, 'assets'], 'super_admin');
$router->get('/admin/assets/export', [AdminController::class, 'exportAssets'], 'super_admin');
$router->post('/admin/assets/store', [AdminController::class, 'storeAsset'], 'super_admin');
$router->post('/admin/assets/update', [AdminController::class, 'updateAsset'], 'super_admin');
$router->post('/admin/assets/delete', [AdminController::class, 'deleteAsset'], 'super_admin');
$router->get('/admin/reports', [AdminController::class, 'reports'], 'super_admin');
$router->get('/admin/reports/export', [AdminController::class, 'exportReports'], 'super_admin');

// ==========================================
// CONCERN MODULES (Strictly Guarded by concern_modules)
// ==========================================

// Dashboard
$router->get('/dashboard', [ConcernDashboardController::class, 'index']);
$router->get('/dashboard/export', [ConcernDashboardController::class, 'exportDashboard']);
$router->get('/m/concern_dashboard', [ConcernDashboardController::class, 'index']);
$router->get('/m/concern_dashboard/export', [ConcernDashboardController::class, 'exportDashboard']);

// Variants Catalog
$router->get('/m/variants', [VariantController::class, 'index'], 'stocks', 'view');
$router->get('/m/variants/export', [VariantController::class, 'exportVariants'], 'stocks', 'view');
$router->post('/m/variants/store', [VariantController::class, 'store'], 'stocks', 'add');
$router->post('/m/variants/update', [VariantController::class, 'update'], 'stocks', 'edit');
$router->post('/m/variants/delete', [VariantController::class, 'delete'], 'stocks', 'delete');

// QR Label Generation & Barcode Scanning
$router->get('/m/label_generation', [LabelController::class, 'index'], 'label_generation', 'view');
$router->get('/m/label_generation/export', [LabelController::class, 'exportJobs'], 'label_generation', 'view');
$router->post('/m/label_generation/generate', [LabelController::class, 'generate'], 'label_generation', 'add');
$router->post('/m/label_generation/map_variant', [LabelController::class, 'mapVariant'], 'label_generation', 'edit');
$router->post('/m/label/add_to_stock', [LabelController::class, 'addToStock'], 'label_generation', 'edit');
$router->post('/m/label_generation/add_to_stock', [LabelController::class, 'addToStock'], 'label_generation', 'edit');
$router->get('/scan', [LabelController::class, 'scanPage'], 'stocks', 'view');
$router->get('/api/qr/lookup', [LabelController::class, 'lookup'], 'stocks', 'view');
$router->post('/api/qr/lookup', [LabelController::class, 'lookup'], 'stocks', 'view');

// Stock & Ledger
$router->get('/m/stocks', [StockController::class, 'index'], 'stocks', 'view');
$router->get('/m/stocks/export', [StockController::class, 'exportStocks'], 'stocks', 'view');
$router->get('/m/stock_ledger', [StockController::class, 'ledger'], 'stocks', 'view');
$router->get('/m/stock_ledger/export', [StockController::class, 'exportLedger'], 'stocks', 'view');

// Inward & Warehouse Inward Operations
$router->get('/m/inward', [InwardController::class, 'index'], 'inward', 'view');
$router->get('/warehouse/inward', [InwardController::class, 'index'], 'inward', 'view');
$router->get('/m/inward/scan/{id}', [InwardController::class, 'scanView'], 'inward', 'view');
$router->get('/warehouse/inward/view/{id}', [InwardController::class, 'scanView'], 'inward', 'view');
$router->post('/m/inward/credit', [InwardController::class, 'creditStock'], 'inward', 'add');
$router->post('/warehouse/inward/credit', [InwardController::class, 'creditStock'], 'inward', 'add');
$router->get('/m/inward/pieces/{id}', [InwardController::class, 'piecesJson'], 'inward', 'view');
$router->get('/warehouse/inward/pieces/{id}', [InwardController::class, 'piecesJson'], 'inward', 'view');
$router->get('/m/inward/export', [InwardController::class, 'exportInward'], 'inward', 'view');
$router->post('/m/inward/store', [InwardController::class, 'store'], 'inward', 'add');
$router->post('/m/inward/update', [InwardController::class, 'update'], 'inward', 'edit');
$router->post('/m/inward/delete', [InwardController::class, 'delete'], 'inward', 'delete');

// Purchase
$router->get('/m/suppliers', [PurchaseController::class, 'suppliers'], 'suppliers', 'view');
$router->get('/m/suppliers/export', [PurchaseController::class, 'exportSuppliers'], 'suppliers', 'view');
$router->post('/m/suppliers/store', [PurchaseController::class, 'storeSupplier'], 'suppliers', 'add');
$router->post('/m/suppliers/update', [PurchaseController::class, 'updateSupplier'], 'suppliers', 'edit');
$router->post('/m/suppliers/delete', [PurchaseController::class, 'deleteSupplier'], 'suppliers', 'delete');
$router->get('/m/purchase_billing', [PurchaseController::class, 'billing'], 'purchase_billing', 'view');
$router->get('/m/purchase_billing/export', [PurchaseController::class, 'exportBills'], 'purchase_billing', 'view');
$router->get('/m/purchase_billing/create', [PurchaseController::class, 'createBill'], 'purchase_billing', 'add');
$router->post('/m/purchase_billing/store', [PurchaseController::class, 'storeBill'], 'purchase_billing', 'add');
$router->get('/m/purchase_billing/edit', [PurchaseController::class, 'editBill'], 'purchase_billing', 'edit');
$router->post('/m/purchase_billing/update', [PurchaseController::class, 'updateBill'], 'purchase_billing', 'edit');
$router->post('/m/purchase_billing/update_status', [PurchaseController::class, 'updateBillStatus'], 'purchase_billing', 'edit');
$router->post('/m/purchase_billing/delete', [PurchaseController::class, 'deleteBill'], 'purchase_billing', 'delete');
$router->post('/m/purchase_billing/save_hsn', [PurchaseController::class, 'saveHsnAjax'], 'purchase_billing', 'add');
$router->post('/m/purchase_billing/save_roll_spec', [PurchaseController::class, 'saveRollSpecAjax'], 'purchase_billing', 'add');
$router->get('/m/purchase_billing/view', [PurchaseController::class, 'viewBill'], 'purchase_billing', 'view');
$router->get('/m/purchase_billing/download_items', [PurchaseController::class, 'downloadItems'], 'purchase_billing', 'view');

// Purchase Stocks (3 Sections: Rolls, Trims/Other Garments, Finished Goods)
$router->get('/m/purchase_stocks', [PurchaseStockController::class, 'index'], 'purchase_stocks', 'view');
$router->post('/m/purchase_stocks/update_consolidated_roll', [PurchaseStockController::class, 'updateConsolidatedRoll'], 'purchase_stocks', 'edit');
$router->post('/m/purchase_stocks/update_item', [PurchaseStockController::class, 'updateStockItem'], 'purchase_stocks', 'edit');
$router->get('/m/purchase_stocks/export', [PurchaseStockController::class, 'export'], 'purchase_stocks', 'view');

$router->get('/m/purchase_damage', [PurchaseController::class, 'damage'], 'purchase_damage', 'view');
$router->post('/m/purchase_damage/scan', [PurchaseController::class, 'scanPurchaseDamageTag'], 'purchase_damage', 'add');
$router->post('/m/purchase_damage/reasons/add', [PurchaseController::class, 'addDamageReason'], 'purchase_damage', 'add');
$router->post('/m/purchase_damage/store', [PurchaseController::class, 'storeDamage'], 'purchase_damage', 'add');
$router->post('/m/purchase_damage/update', [PurchaseController::class, 'updateDamageRecord'], 'purchase_damage', 'edit');
$router->post('/m/purchase_damage/delete', [PurchaseController::class, 'deleteDamageRecord'], 'purchase_damage', 'delete');

// Concern Damage
$router->get('/m/concern_damage', [DamageController::class, 'index'], 'concern_damage', 'view');
$router->get('/m/concern_damage/export', [DamageController::class, 'exportConcernDamage'], 'concern_damage', 'view');
$router->post('/m/concern_damage/store', [DamageController::class, 'store'], 'concern_damage', 'add');
$router->post('/m/concern_damage/update', [DamageController::class, 'update'], 'concern_damage', 'edit');
$router->post('/m/concern_damage/delete', [DamageController::class, 'delete'], 'concern_damage', 'delete');

// Sales & Distribution / Shop
$router->get('/m/customers', [SalesController::class, 'customers'], 'customers', 'view');
$router->get('/shop/customers', [SalesController::class, 'customers'], 'customers', 'view');
$router->post('/m/customers/store', [SalesController::class, 'storeCustomer'], 'customers', 'add');
$router->post('/shop/customers/store', [SalesController::class, 'storeCustomer'], 'customers', 'add');
$router->post('/m/customers/update', [SalesController::class, 'updateCustomer'], 'customers', 'add');
$router->post('/shop/customers/update', [SalesController::class, 'updateCustomer'], 'customers', 'add');
$router->post('/m/customers/delete', [SalesController::class, 'deleteCustomer'], 'customers', 'delete');
$router->post('/shop/customers/delete', [SalesController::class, 'deleteCustomer'], 'customers', 'delete');
$router->get('/m/customers/export', [SalesController::class, 'exportCustomers'], 'customers', 'view');
$router->get('/api/customers/details', [SalesController::class, 'customerProfile'], 'customers', 'view');
$router->get('/m/request', [SalesController::class, 'request'], 'request', 'view');
$router->get('/m/request/export', [SalesController::class, 'exportRequests'], 'request', 'view');
$router->post('/m/request/store', [SalesController::class, 'storeRequest'], 'request', 'add');
$router->post('/m/request/update', [SalesController::class, 'updateRequest'], 'request', 'edit');
$router->post('/m/request/delete', [SalesController::class, 'deleteRequest'], 'request', 'delete');
$router->get('/m/batch', [SalesController::class, 'batch'], 'batch', 'view');
$router->get('/m/batch/export', [SalesController::class, 'exportBatches'], 'batch', 'view');
$router->post('/m/batch/store', [SalesController::class, 'storeBatch'], 'batch', 'add');
$router->post('/m/batch/approve', [SalesController::class, 'approveBatch'], 'batch', 'edit');
$router->post('/m/batch/update', [SalesController::class, 'updateBatch'], 'batch', 'edit');
$router->post('/m/batch/delete', [SalesController::class, 'deleteBatch'], 'batch', 'delete');
$router->get('/m/billing', [SalesController::class, 'billing'], 'billing', 'view');
$router->get('/m/billing/export', [SalesController::class, 'exportBilling'], 'billing', 'view');
$router->post('/m/billing/store', [SalesController::class, 'storeInvoice'], 'billing', 'add');
$router->get('/shop/pos', [SalesController::class, 'billing'], 'billing', 'view');
$router->post('/shop/pos/complete', [SalesController::class, 'completePosSale'], 'billing', 'add');
$router->post('/m/billing/complete', [SalesController::class, 'completePosSale'], 'billing', 'add');
$router->get('/api/pos/variant-lookup', [SalesController::class, 'variantLookup'], 'billing', 'view');
$router->get('/api/pos/customer-lookup', [SalesController::class, 'customerLookup'], 'billing', 'view');
$router->get('/m/outward', [SalesController::class, 'outward'], 'outward', 'view');
$router->get('/m/outward/export', [SalesController::class, 'exportOutward'], 'outward', 'view');
$router->post('/m/outward/store', [SalesController::class, 'storeOutward'], 'outward', 'add');
$router->post('/m/outward/dispatch', [SalesController::class, 'dispatchOutward'], 'outward', 'edit');
$router->post('/m/outward/receive', [SalesController::class, 'receiveOutward'], 'outward', 'edit');
$router->post('/m/outward/update', [SalesController::class, 'updateOutward'], 'outward', 'edit');
$router->post('/m/outward/delete', [SalesController::class, 'deleteOutward'], 'outward', 'delete');
$router->get('/m/dispatch', [SalesController::class, 'dispatch'], 'dispatch', 'view');
$router->get('/m/dispatch/export', [SalesController::class, 'exportDispatch'], 'dispatch', 'view');
$router->post('/m/dispatch/store', [SalesController::class, 'storeDispatch'], 'dispatch', 'add');
$router->post('/m/dispatch/update', [SalesController::class, 'updateDispatch'], 'dispatch', 'edit');
$router->post('/m/dispatch/delete', [SalesController::class, 'deleteDispatch'], 'dispatch', 'delete');
$router->get('/m/return_exchange', [SalesController::class, 'returnExchange'], 'return_exchange', 'view');
$router->get('/m/return_exchange/export', [SalesController::class, 'exportReturnExchange'], 'return_exchange', 'view');
$router->post('/m/return_exchange/store', [SalesController::class, 'storeReturn'], 'return_exchange', 'add');
$router->post('/m/return_exchange/update', [SalesController::class, 'updateReturn'], 'return_exchange', 'edit');
$router->post('/m/return_exchange/delete', [SalesController::class, 'deleteReturn'], 'return_exchange', 'delete');

// Production Line
$router->get('/m/catalog_program', [ProductionController::class, 'catalogProgram'], 'catalog_program', 'view');
$router->get('/m/catalog_program/export', [ProductionController::class, 'exportCatalogProgram'], 'catalog_program', 'view');
$router->post('/m/catalog_program/store', [ProductionController::class, 'storeProgram'], 'catalog_program', 'add');
$router->post('/m/catalog_program/update', [ProductionController::class, 'updateProgram'], 'catalog_program', 'edit');
$router->post('/m/catalog_program/delete', [ProductionController::class, 'deleteProgram'], 'catalog_program', 'delete');
$router->get('/production/catalog', [ProductionController::class, 'catalogProgram'], 'catalog_program', 'view');
$router->get('/m/catalog', [ProductionController::class, 'catalogProgram'], 'catalog_program', 'view');
$router->get('/production/catalog/create', [ProductionController::class, 'createStyle'], 'catalog_program', 'add');
$router->get('/m/catalog/create', [ProductionController::class, 'createStyle'], 'catalog_program', 'add');
$router->post('/production/catalog/store', [ProductionController::class, 'storeStyle'], 'catalog_program', 'add');
$router->post('/m/catalog/store', [ProductionController::class, 'storeStyle'], 'catalog_program', 'add');
$router->get('/production/catalog/edit', [ProductionController::class, 'editStyle'], 'catalog_program', 'edit');
$router->get('/m/catalog/edit', [ProductionController::class, 'editStyle'], 'catalog_program', 'edit');
$router->post('/production/catalog/update', [ProductionController::class, 'updateStyle'], 'catalog_program', 'edit');
$router->post('/m/catalog/update', [ProductionController::class, 'updateStyle'], 'catalog_program', 'edit');
$router->post('/production/catalog/delete', [ProductionController::class, 'deleteStyle'], 'catalog_program', 'delete');
$router->post('/m/catalog/delete', [ProductionController::class, 'deleteStyle'], 'catalog_program', 'delete');
$router->get('/production/programs', [ProductionController::class, 'programsList'], 'catalog_program', 'view');
$router->get('/m/programs', [ProductionController::class, 'programsList'], 'catalog_program', 'view');
$router->get('/production/programs/create', [ProductionController::class, 'createProgram'], 'catalog_program', 'add');
$router->get('/m/programs/create', [ProductionController::class, 'createProgram'], 'catalog_program', 'add');
$router->post('/production/programs/store', [ProductionController::class, 'storeProgramV2'], 'catalog_program', 'add');
$router->post('/m/programs/store', [ProductionController::class, 'storeProgramV2'], 'catalog_program', 'add');
$router->get('/production/programs/view', [ProductionController::class, 'viewProgram'], 'catalog_program', 'view');
$router->get('/m/programs/view', [ProductionController::class, 'viewProgram'], 'catalog_program', 'view');
$router->get('/production/programs/edit', [ProductionController::class, 'editProgram'], 'catalog_program', 'edit');
$router->get('/m/programs/edit', [ProductionController::class, 'editProgram'], 'catalog_program', 'edit');
$router->post('/production/programs/update', [ProductionController::class, 'updateProgram'], 'catalog_program', 'edit');
$router->post('/m/programs/update', [ProductionController::class, 'updateProgram'], 'catalog_program', 'edit');
$router->post('/production/programs/delete', [ProductionController::class, 'deleteProgram'], 'catalog_program', 'delete');
$router->post('/m/programs/delete', [ProductionController::class, 'deleteProgram'], 'catalog_program', 'delete');
$router->post('/production/programs/status', [ProductionController::class, 'updateProgramStatus'], 'catalog_program', 'edit');
$router->post('/m/programs/status', [ProductionController::class, 'updateProgramStatus'], 'catalog_program', 'edit');
$router->get('/api/programs/available-rolls', [ProductionController::class, 'apiAvailableRolls'], 'catalog_program', 'view');
$router->get('/api/programs/roll-lookup', [ProductionController::class, 'apiRollLookup'], 'catalog_program', 'view');
$router->get('/api/programs/details', [ProductionController::class, 'apiProgramDetails'], 'catalog_program', 'view');

// Catalog Mapping Module (Purchased Finished Goods -> 3-Tier Hierarchy & Attributes)
$router->get('/production/catalog-mapping', [CatalogMappingController::class, 'index'], 'catalog_mapping', 'view');
$router->get('/m/catalog_mapping', [CatalogMappingController::class, 'index'], 'catalog_mapping', 'view');
$router->get('/production/catalog-mapping/map', [CatalogMappingController::class, 'mapItemForm'], 'catalog_mapping', 'add');
$router->get('/m/catalog_mapping/map', [CatalogMappingController::class, 'mapItemForm'], 'catalog_mapping', 'add');
$router->post('/production/catalog-mapping/save', [CatalogMappingController::class, 'saveMapping'], 'catalog_mapping', 'add');
$router->post('/m/catalog_mapping/save', [CatalogMappingController::class, 'saveMapping'], 'catalog_mapping', 'add');
$router->post('/production/catalog-mapping/unmap', [CatalogMappingController::class, 'unmapItem'], 'catalog_mapping', 'delete');
$router->post('/m/catalog_mapping/unmap', [CatalogMappingController::class, 'unmapItem'], 'catalog_mapping', 'delete');
$router->post('/production/catalog-mapping/quick-category', [CatalogMappingController::class, 'quickCreateCategory'], 'catalog_mapping', 'add');
$router->post('/production/catalog-mapping/quick-attribute', [CatalogMappingController::class, 'quickCreateAttribute'], 'catalog_mapping', 'add');
$router->post('/production/catalog-mapping/delete-hierarchy', [CatalogMappingController::class, 'deleteHierarchy'], 'catalog_mapping', 'view');
$router->get('/api/catalog-mapping/item-details', [CatalogMappingController::class, 'apiItemDetails'], 'catalog_mapping', 'view');

// APIs for 3-Tier Hierarchy & HSN Lookups
$router->get('/api/categories/subcategories', [ProductionController::class, 'getSubcategoriesAjax'], 'catalog_program', 'view');
$router->get('/api/categories/subsubcategories', [ProductionController::class, 'getSubSubcategoriesAjax'], 'catalog_program', 'view');
$router->post('/api/categories/quick-add', [ProductionController::class, 'quickAddCategoryAjax'], 'catalog_program', 'add');
$router->post('/api/subcategories/quick-add', [ProductionController::class, 'quickAddSubcategoryAjax'], 'catalog_program', 'add');
$router->post('/api/subsubcategories/quick-add', [ProductionController::class, 'quickAddSubSubcategoryAjax'], 'catalog_program', 'add');
$router->post('/api/attributes/quick-add', [ProductionController::class, 'quickAddAttributeAjax'], 'catalog_program', 'add');
$router->post('/api/attributes/delete', [ProductionController::class, 'deleteAttributeAjax'], 'catalog_program', 'delete');
$router->get('/api/hsn/search', [ProductionController::class, 'searchHsnAjax'], 'catalog_program', 'view');
$router->post('/api/hsn/quick-add', [ProductionController::class, 'quickAddHsnAjax'], 'catalog_program', 'add');
$router->get('/api/catalog/style-details', [ProductionController::class, 'styleDetailsAjax'], 'catalog_program', 'view');
// Cutting & Wastage Flow (Matching UI Images 1, 2, 3)
$router->get('/production/cutting', [ProductionController::class, 'cuttingIndex'], 'cutting_wastage', 'view');
$router->get('/m/cutting_wastage', [ProductionController::class, 'cuttingIndex'], 'cutting_wastage', 'view');
$router->get('/production/cutting/create', [ProductionController::class, 'createCutting'], 'cutting_wastage', 'add');
$router->post('/production/cutting/store', [ProductionController::class, 'storeCuttingBatch'], 'cutting_wastage', 'add');
$router->get('/production/cutting/view', [ProductionController::class, 'viewCuttingBatch'], 'cutting_wastage', 'view');
$router->post('/production/cutting/delete', [ProductionController::class, 'deleteCuttingBatch'], 'cutting_wastage', 'delete');

// Cutting API Endpoints
$router->get('/api/cutting/program-details', [ProductionController::class, 'apiCuttingProgramDetails'], 'cutting_wastage', 'view');
$router->post('/api/cutting/quick-master', [ProductionController::class, 'apiQuickAddCuttingMaster'], 'cutting_wastage', 'add');

// Wastage Selling (Image 3)
$router->get('/production/cutting/wastage', [ProductionController::class, 'cuttingWastageIndex'], 'cutting_wastage', 'view');
$router->get('/production/wastage-selling', [ProductionController::class, 'cuttingWastageIndex'], 'cutting_wastage', 'view');
$router->post('/production/cutting/wastage/sell', [ProductionController::class, 'storeWastageSale'], 'cutting_wastage', 'add');
$router->post('/production/wastage-selling/store', [ProductionController::class, 'storeWastageSale'], 'cutting_wastage', 'add');
$router->post('/production/cutting/wastage/update', [ProductionController::class, 'updateWastageSale'], 'cutting_wastage', 'edit');
$router->post('/production/wastage-selling/update', [ProductionController::class, 'updateWastageSale'], 'cutting_wastage', 'edit');
$router->post('/production/cutting/wastage/delete', [ProductionController::class, 'deleteWastageSale'], 'cutting_wastage', 'delete');
$router->post('/production/wastage-selling/delete', [ProductionController::class, 'deleteWastageSale'], 'cutting_wastage', 'delete');
$router->get('/api/cutting/wastage/details', [ProductionController::class, 'apiWastageSaleDetails'], 'cutting_wastage', 'view');

// Stitching & Wastage Flow (Matching UI Images 1, 2, 3)
$router->get('/production/stitching', [ProductionController::class, 'stitchingIndex'], 'stitching_wastage', 'view');
$router->get('/m/stitching_wastage', [ProductionController::class, 'stitchingIndex'], 'stitching_wastage', 'view');
$router->get('/production/stitching/create', [ProductionController::class, 'createStitching'], 'stitching_wastage', 'add');
$router->post('/production/stitching/store', [ProductionController::class, 'storeStitchingBatch'], 'stitching_wastage', 'add');
$router->get('/production/stitching/edit', [ProductionController::class, 'editStitchingBatch'], 'stitching_wastage', 'edit');
$router->post('/production/stitching/update', [ProductionController::class, 'updateStitchingBatch'], 'stitching_wastage', 'edit');
$router->get('/production/stitching/view', [ProductionController::class, 'viewStitchingBatch'], 'stitching_wastage', 'view');
$router->post('/production/stitching/delete', [ProductionController::class, 'deleteStitchingBatch'], 'stitching_wastage', 'delete');

// Stitching API Endpoints
$router->get('/api/stitching/cutting-details', [ProductionController::class, 'apiCuttingDetailsForStitching'], 'stitching_wastage', 'view');
$router->post('/api/stitching/quick-tailor', [ProductionController::class, 'apiQuickAddTailor'], 'stitching_wastage', 'add');

// Stitching Wastage Selling (Image 2)
$router->get('/production/stitching/wastage', [ProductionController::class, 'stitchingWastageIndex'], 'stitching_wastage', 'view');
$router->post('/production/stitching/wastage/sell', [ProductionController::class, 'storeStitchingWastageSale'], 'stitching_wastage', 'add');

// Legacy routes fallback
$router->get('/m/stitching_wastage/export', [ProductionController::class, 'exportStitching'], 'stitching_wastage', 'view');
$router->post('/m/stitching_wastage/store', [ProductionController::class, 'storeStitchingBatch'], 'stitching_wastage', 'add');
$router->post('/m/stitching_wastage/update', [ProductionController::class, 'updateStitchingBatch'], 'stitching_wastage', 'edit');
$router->post('/m/stitching_wastage/delete', [ProductionController::class, 'deleteStitchingBatch'], 'stitching_wastage', 'delete');
$router->get('/m/qc_stocks', [ProductionController::class, 'qc'], 'qc_stocks', 'view');
$router->get('/m/qc_stocks/export', [ProductionController::class, 'exportQc'], 'qc_stocks', 'view');
$router->post('/m/qc_stocks/store', [ProductionController::class, 'storeQc'], 'qc_stocks', 'add');
$router->post('/m/qc_stocks/update', [ProductionController::class, 'updateQc'], 'qc_stocks', 'edit');
$router->post('/m/qc_stocks/delete', [ProductionController::class, 'deleteQc'], 'qc_stocks', 'delete');

// Finance & Accounts
$router->get('/m/expense', [FinanceController::class, 'expense'], 'expense', 'view');
$router->get('/m/expense/export', [FinanceController::class, 'exportExpenses'], 'expense', 'view');
$router->post('/m/expense/store', [FinanceController::class, 'storeExpense'], 'expense', 'add');
$router->post('/m/expense/update', [FinanceController::class, 'updateExpense'], 'expense', 'edit');
$router->post('/m/expense/delete', [FinanceController::class, 'deleteExpense'], 'expense', 'delete');
$router->post('/m/expense/category/store', [FinanceController::class, 'storeCategory'], 'expense', 'add');
$router->get('/m/cash', [FinanceController::class, 'cash'], 'cash', 'view');
$router->post('/m/cash/store', [FinanceController::class, 'storeAccount'], 'cash', 'add');
$router->get('/m/cash_flow', [FinanceController::class, 'cashFlow'], 'cash_flow', 'view');
$router->get('/m/cash_flow/export', [FinanceController::class, 'exportCashFlow'], 'cash_flow', 'view');
$router->get('/m/cash/export', [FinanceController::class, 'exportCashFlow'], 'cash', 'view');
$router->post('/m/cash_flow/store_account', [FinanceController::class, 'storeAccount'], 'cash_flow', 'add');
$router->post('/m/cash_flow/account/update', [FinanceController::class, 'updateAccount'], 'cash_flow', 'edit');
$router->post('/m/cash_flow/account/delete', [FinanceController::class, 'deleteAccount'], 'cash_flow', 'delete');
$router->post('/m/cash_flow/shift/open', [FinanceController::class, 'openShift'], 'cash_flow', 'add');
$router->post('/m/cash_flow/shift/close', [FinanceController::class, 'closeShift'], 'cash_flow', 'add');
$router->post('/m/cash/shift/open', [FinanceController::class, 'openShift'], 'cash', 'add');
$router->post('/m/cash/shift/close', [FinanceController::class, 'closeShift'], 'cash', 'add');

// Staff Management
$router->get('/m/staff', [StaffController::class, 'index'], 'staff', 'view');
$router->get('/m/staff/create', [StaffController::class, 'create'], 'staff', 'add');
$router->get('/m/staff/roles', [StaffController::class, 'roles'], 'staff', 'view');
$router->post('/m/staff/assign-role', [StaffController::class, 'assignRole'], 'staff', 'edit');
$router->post('/m/staff/save-role-permissions', [StaffController::class, 'saveRolePermissions'], 'staff', 'edit');
$router->post('/m/staff/save-user-permissions', [StaffController::class, 'saveUserPermissions'], 'staff', 'edit');
$router->post('/m/staff/toggle-status', [StaffController::class, 'toggleStatus'], 'staff', 'edit');
$router->post('/m/staff/create-role', [StaffController::class, 'createRole'], 'staff', 'add');
$router->get('/api/staff/role-permissions', [StaffController::class, 'apiGetRolePermissions'], 'staff', 'view');
$router->get('/api/staff/user-permissions', [StaffController::class, 'apiGetUserPermissions'], 'staff', 'view');
$router->get('/m/staff/export', [StaffController::class, 'export'], 'staff', 'view');
$router->post('/m/staff/store', [StaffController::class, 'store'], 'staff', 'add');
$router->post('/m/staff/update', [StaffController::class, 'update'], 'staff', 'edit');
$router->post('/m/staff/delete', [StaffController::class, 'delete'], 'staff', 'delete');
