<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Live Inventory Stocks</h4>
            <p class="text-secondary small mb-0">Consolidated live balance by warehouse location, backed by append-only ledger audit trail.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= \App\Core\Controller::url('/m/stock_ledger') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-clock-history me-1"></i> Stock Ledger
            </a>
            <?php if (\App\Core\Auth::can('inward', 'add')): ?>
            <a href="<?= \App\Core\Controller::url('/m/inward') ?>" class="btn btn-primary">
                <i class="bi bi-box-arrow-in-down me-1"></i> Inward Entry
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Export Intelligence Toolbar -->
    <?php
    $exportTitle = 'Export Live Inventory Stocks (Excel & PDF)';
    $exportSubtitle = 'Download warehouse stock quantities, valuations, SKU balances & location storage breakdown';
    $exportUrl = \App\Core\Controller::url('/m/stocks/export');
    $prefix = 'stock_exp';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <!-- Summary Statistics -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card-stat d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-secondary small fw-semibold text-uppercase">Total Physical Inventory Units</span>
                    <h3 class="fw-bold text-dark mt-1 mb-0"><?= number_format((float)$totalUnits, 0) ?></h3>
                </div>
                <div class="stat-icon-box bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-boxes"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card-stat d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-secondary small fw-semibold text-uppercase">Total Cost Valuation</span>
                    <h3 class="fw-bold text-success mt-1 mb-0">₹<?= number_format($totalValuation, 2) ?></h3>
                </div>
                <div class="stat-icon-box bg-success bg-opacity-10 text-success">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
        </div>
    </div>

    <?php
    $effectiveCanDelete = !empty($canEditStock) && (!isset($canDeleteStock) || !empty($canDeleteStock));
    $showConcernColumn = $isSuperAdmin && empty($selectedCid);
    $stockColumnCount = $showConcernColumn ? 9 : 8;
    $stockGroups = [
        'own' => [
            'title' => 'Own Products',
            'description' => 'Production stitched finished goods',
            'items' => [],
        ],
        'purchase' => [
            'title' => 'Purchase Products',
            'description' => 'Mapped finished goods from purchase inward',
            'items' => [],
        ],
        'other' => [
            'title' => 'Other Sources',
            'description' => 'Stock with no matching production or purchase source entry',
            'items' => [],
        ],
    ];
    foreach ($stocks as $stock) {
        $source = $stock['stock_source'] ?? 'other';
        if (!isset($stockGroups[$source])) {
            continue;
        }
        $stockGroups[$source]['items'][] = $stock;
    }
    $activeStockSource = !empty($stockGroups['own']['items'])
        ? 'own'
        : (!empty($stockGroups['purchase']['items']) ? 'purchase' : 'other');
    ?>
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-box-seam me-2 text-primary"></i> Current Stock by Location</h6>
            <span class="badge bg-light text-dark border"><?= count($stocks) ?> Stock Lines</span>
        </div>
        <div class="px-3 pt-3 border-bottom">
            <div class="nav nav-pills flex-nowrap gap-2 overflow-auto" role="tablist" aria-label="Stock source">
                <?php foreach ($stockGroups as $source => $group): ?>
                    <button type="button"
                            class="nav-link text-nowrap <?= $activeStockSource === $source ? 'active' : '' ?>"
                            data-stock-source-tab="<?= htmlspecialchars($source) ?>"
                            role="tab"
                            aria-controls="stock-table-body"
                            aria-selected="<?= $activeStockSource === $source ? 'true' : 'false' ?>">
                        <i class="bi <?= $source === 'own' ? 'bi-boxes' : ($source === 'purchase' ? 'bi-bag-check' : 'bi-arrow-left-right') ?> me-1"></i>
                        <?= htmlspecialchars($group['title']) ?>
                        <span class="badge <?= $activeStockSource === $source ? 'bg-light text-primary' : 'bg-white text-dark border' ?> ms-1"><?= count($group['items']) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead>
                    <tr>
                        <th style="width:40px;">
                            <input type="checkbox" class="form-check-input" id="stockSelectAll" title="Select All">
                        </th>
                        <th>SKU Code</th>
                        <?php if ($showConcernColumn): ?>
                            <th>Concern</th>
                        <?php endif; ?>
                        <th>Variant Description</th>
                        <th>Category / Specs</th>
                        <th>Qty</th>
                        <th>Cost / Selling Price</th>
                        <th>Net Value</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="stock-table-body">
                    <?php if (empty($stocks)): ?>
                        <tr><td colspan="<?= $stockColumnCount ?>" class="text-center py-4 text-muted">No label-generated own or purchase product stock is available.</td></tr>
                    <?php else: ?>
                        <?php foreach ($stockGroups as $group): ?>
                            <?php foreach ($group['items'] as $s):
                                    $variantAttributes = json_decode((string)($s['variant_attributes'] ?? ''), true);
                                    $variantAttributes = is_array($variantAttributes) ? $variantAttributes : [];
                                    $displayAttributes = [];
                                    $color = trim((string)($variantAttributes['color'] ?? ''));
                                    if ($color === '' && preg_match('/\(([^)]+)\)/', (string)$s['variant_name'], $cm)) {
                                        $color = trim($cm[1]);
                                    }
                                    $hsn = trim((string)($variantAttributes['hsn'] ?? ($s['catalog_hsn'] ?? '')));
                                    $qrPayload = \App\Services\QrService::generateFinishedGoodsCode((int)$s['variant_id']) ?: $s['sku_code'];
                                    $subcatDisplay = $s['subcategory_name'] ?? '-';
                                    if (($subcatDisplay === '-' || $subcatDisplay === '') && !empty($s['hierarchy_path'])) {
                                        $hpParts = array_map('trim', explode('>', $s['hierarchy_path']));
                                        if (!empty($hpParts[1])) {
                                            $subcatDisplay = $hpParts[1];
                                            if (!empty($hpParts[2])) {
                                                $subcatDisplay .= ' / ' . $hpParts[2];
                                            }
                                        }
                                    } elseif (!empty($s['sub_subcategory_name']) && $s['sub_subcategory_name'] !== '-') {
                                        $subcatDisplay .= ' / ' . $s['sub_subcategory_name'];
                                    }
                                    if ($color !== '') {
                                        $displayAttributes[] = 'Color: ' . $color;
                                    }
                                    foreach ($variantAttributes as $attributeName => $attributeValue) {
                                        $attributeType = strtolower(trim((string)$attributeName));
                                        if (
                                            $attributeType === '' ||
                                            in_array($attributeType, ['color', 'size', 'hsn', 'weight', 'allocated_qty', 'custom_attributes'], true) ||
                                            !is_scalar($attributeValue) ||
                                            trim((string)$attributeValue) === ''
                                        ) {
                                            continue;
                                        }
                                        $displayAttributes[] = ucwords(str_replace(['_', '-'], ' ', $attributeType)) . ': ' . trim((string)$attributeValue);
                                    }
                                    $customAttributes = $variantAttributes['custom_attributes'] ?? [];
                                    if (is_array($customAttributes)) {
                                        foreach ($customAttributes as $attributeName => $attributeValue) {
                                            if (!is_scalar($attributeValue) || trim((string)$attributeValue) === '') {
                                                continue;
                                            }
                                            $displayAttributes[] = ucwords(str_replace(['_', '-'], ' ', (string)$attributeName)) . ': ' . trim((string)$attributeValue);
                                        }
                                    }
                                    $displayAttributes = array_values(array_unique($displayAttributes));
                                    $isFinishedGoods = in_array($s['stock_source'] ?? '', ['own', 'purchase'], true);
                                    $stockUnit = $isFinishedGoods ? 'pcs' : ($s['weight_unit'] ?: 'pcs');
                                ?>
                                    <tr data-stock-source="<?= htmlspecialchars($s['stock_source']) ?>" class="<?= $s['stock_source'] === $activeStockSource ? '' : 'd-none' ?>">
                                        <td>
                                            <input type="checkbox" class="form-check-input stock-row-checkbox"
                                                   data-variant-id="<?= (int)$s['variant_id'] ?>"
                                                   data-concern-id="<?= (int)$s['concern_id'] ?>"
                                                   data-variant-name="<?= htmlspecialchars((string)$s['variant_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                   data-price="<?= number_format((float)$s['price'], 2) ?>"
                                                   data-mrp="<?= number_format((float)$s['mrp'], 2) ?>">
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1">
                                                <span class="badge bg-dark font-monospace"><?= htmlspecialchars($s['sku_code']) ?></span>
                                                <button type="button"
                                                        class="btn btn-sm btn-light border py-0 px-1 text-primary stock-qr-trigger"
                                                        data-qr-payload="<?= htmlspecialchars($qrPayload, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-sku-code="<?= htmlspecialchars((string)$s['sku_code'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-variant-name="<?= htmlspecialchars((string)$s['variant_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-size="<?= htmlspecialchars((string)($s['size'] ?: 'Free'), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-color="<?= htmlspecialchars((string)$color, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-price="<?= number_format((float)$s['price'], 2) ?>"
                                                        data-mrp="<?= number_format((float)$s['mrp'], 2) ?>"
                                                        data-print-url="<?= \App\Core\Controller::url('/m/label_generation?variant_id=' . $s['variant_id']) ?>"
                                                        title="Quick View QR Code">
                                                    <i class="bi bi-qr-code"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <?php if ($showConcernColumn): ?>
                                            <td>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
                                                    <?= htmlspecialchars($s['concern_name'] ?? 'Concern #' . $s['concern_id']) ?>
                                                </span>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($s['variant_name']) ?></div>
                                            <small class="text-muted">
                                                Size: <?= htmlspecialchars($s['size'] ?: 'Free') ?>
                                                <?php if ($displayAttributes): ?>&bull; <?= htmlspecialchars(implode(' / ', $displayAttributes)) ?><?php endif; ?>
                                                <?php if ((float)$s['weight'] > 0): ?>&bull; Unit weight: <?= number_format((float)$s['weight'], 3) ?> <?= htmlspecialchars($s['weight_unit']) ?><?php endif; ?>
                                            </small>
                                            <?php if (!empty($s['is_complementary'])): ?>
                                                <span class="badge bg-warning text-dark ms-1" style="font-size:0.68rem;"><i class="bi bi-gift me-1"></i>Complementary</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold"><?= htmlspecialchars($s['category_name'] ?? 'General') ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($subcatDisplay) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fs-6 px-2 py-1">
                                                <?= number_format((float)$s['qty'], 0) ?> <?= htmlspecialchars($stockUnit) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="d-block text-success">Cost ₹<?= number_format((float)$s['cost_price'], 2) ?></span>
                                            <small class="d-block text-muted">Selling ₹<?= number_format((float)$s['price'], 2) ?> · MRP ₹<?= number_format((float)$s['mrp'], 2) ?></small>
                                        </td>
                                        <td class="fw-bold text-dark">₹<?= number_format($s['qty'] * $s['cost_price'], 2) ?></td>
                                        <td class="text-end">
                                            <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-info"
                                                        data-stock-view
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#stockDetailsModal"
                                                        data-concern-id="<?= (int)$s['concern_id'] ?>"
                                                        data-variant-id="<?= (int)$s['variant_id'] ?>"
                                                        data-location="<?= htmlspecialchars((string)$s['location'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-stock-name="<?= htmlspecialchars((string)$s['variant_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-sku-code="<?= htmlspecialchars((string)$s['sku_code'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-qr-payload="<?= htmlspecialchars($qrPayload, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-category="<?= htmlspecialchars((string)($s['category_name'] ?? 'General'), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-subcategory="<?= htmlspecialchars((string)$subcatDisplay, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-sub-subcategory="<?= htmlspecialchars((string)($s['sub_subcategory_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-style-code="<?= htmlspecialchars((string)($s['style_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-whole-code="<?= htmlspecialchars((string)($s['whole_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-hierarchy="<?= htmlspecialchars((string)($s['hierarchy_path'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-size="<?= htmlspecialchars((string)($s['size'] ?: 'Free'), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-color="<?= htmlspecialchars((string)$color, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-hsn="<?= htmlspecialchars((string)$hsn, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-weight="<?= htmlspecialchars((string)((float)$s['weight'] > 0 ? number_format((float)$s['weight'], 3) . ' ' . $s['weight_unit'] : '-'), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-quantity="<?= (int)round((float)$s['qty']) ?>"
                                                        data-unit="<?= htmlspecialchars($stockUnit, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-cost-price="<?= number_format((float)$s['cost_price'], 2) ?>"
                                                        data-price="<?= number_format((float)$s['price'], 2) ?>"
                                                        data-mrp="<?= number_format((float)$s['mrp'], 2) ?>"
                                                        data-source="<?= htmlspecialchars((string)($s['stock_source'] ?? 'other'), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-concern-name="<?= htmlspecialchars((string)($s['concern_name'] ?? 'Concern #' . $s['concern_id']), ENT_QUOTES, 'UTF-8') ?>"
                                                        title="View Product Specs & Manufacturing/Purchase Audit Details">
                                                    <i class="bi bi-eye"></i> View Details
                                                </button>
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-dark stock-qr-trigger"
                                                        data-qr-payload="<?= htmlspecialchars($qrPayload, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-sku-code="<?= htmlspecialchars((string)$s['sku_code'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-variant-name="<?= htmlspecialchars((string)$s['variant_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-size="<?= htmlspecialchars((string)($s['size'] ?: 'Free'), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-color="<?= htmlspecialchars((string)$color, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-price="<?= number_format((float)$s['price'], 2) ?>"
                                                        data-mrp="<?= number_format((float)$s['mrp'], 2) ?>"
                                                        data-print-url="<?= \App\Core\Controller::url('/m/label_generation?variant_id=' . $s['variant_id']) ?>"
                                                        title="Quick View QR Code">
                                                    <i class="bi bi-qr-code"></i> QR
                                                </button>
                                                <?php if (($s['stock_source'] ?? '') === 'purchase'): ?>
                                                    <?php if (!empty($s['purchase_item_id'])): ?>
                                                        <?php
                                                        $labelPreviewUrl = '/m/stocks/view-label?' . http_build_query([
                                                            'item_id' => (int)$s['purchase_item_id'],
                                                            'variant_id' => (int)$s['variant_id'],
                                                            'concern_id' => (int)$s['concern_id'],
                                                        ]);
                                                        ?>
                                                        <a href="<?= htmlspecialchars(\App\Core\Controller::url($labelPreviewUrl), ENT_QUOTES, 'UTF-8') ?>"
                                                           class="btn btn-sm btn-outline-primary"
                                                           target="_blank"
                                                           rel="noopener"
                                                           aria-label="View label for <?= htmlspecialchars((string)$s['variant_name'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="bi bi-tag"></i> View Label
                                                        </a>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Purchase label details are unavailable">
                                                            <i class="bi bi-tag-fill"></i> Label Unavailable
                                                        </button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <a href="<?= \App\Core\Controller::url('/m/label_generation?variant_id=' . $s['variant_id']) ?>" class="btn btn-sm btn-outline-primary" title="Print QR Stickers">
                                                        <i class="bi bi-qr-code"></i> Print Labels
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($canEditStock): ?>
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-secondary"
                                                            data-stock-edit
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#stockEditModal"
                                                            data-concern-id="<?= (int)$s['concern_id'] ?>"
                                                            data-variant-id="<?= (int)$s['variant_id'] ?>"
                                                            data-location="<?= htmlspecialchars((string)$s['location'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-stock-name="<?= htmlspecialchars((string)$s['variant_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-sku-code="<?= htmlspecialchars((string)$s['sku_code'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-category-id="<?= (int)($s['category_id'] ?? 0) ?>"
                                                            data-subcategory-id="<?= (int)($s['subcategory_id'] ?? 0) ?>"
                                                            data-size="<?= htmlspecialchars((string)($s['size'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-color="<?= htmlspecialchars((string)$color, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-hsn="<?= htmlspecialchars((string)($variantAttributes['hsn'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-weight="<?= (float)($s['weight'] ?? 0) ?>"
                                                            data-weight-unit="<?= htmlspecialchars((string)($s['weight_unit'] ?: 'kg'), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-cost-price="<?= (float)($s['cost_price'] ?? 0) ?>"
                                                            data-price="<?= (float)($s['price'] ?? 0) ?>"
                                                            data-mrp="<?= (float)($s['mrp'] ?? 0) ?>"
                                                            data-quantity="<?= (int)round((float)$s['qty']) ?>"
                                                            aria-label="Edit details for <?= htmlspecialchars((string)$s['variant_name'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <i class="bi bi-pencil-square"></i> Edit
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (!empty($effectiveCanDelete)): ?>
                                                    <form method="post"
                                                          action="<?= \App\Core\Controller::url('/m/stocks/delete-line') ?>"
                                                          data-stock-delete-form
                                                          data-confirm-message="<?= htmlspecialchars('Set ' . $s['variant_name'] . ' at ' . $s['location'] . ' to zero? The stock ledger history will be preserved.', ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                        <input type="hidden" name="concern_id" value="<?= (int)$s['concern_id'] ?>">
                                                        <input type="hidden" name="variant_id" value="<?= (int)$s['variant_id'] ?>">
                                                        <input type="hidden" name="location" value="<?= htmlspecialchars((string)$s['location'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Zero this stock balance">
                                                            <i class="bi bi-trash"></i> Remove
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <tr id="stock-source-empty-row" class="<?= !empty($stockGroups[$activeStockSource]['items']) ? 'd-none' : '' ?>">
                            <td colspan="<?= $stockColumnCount ?>" class="text-center py-4 text-muted">No <?= strtolower(htmlspecialchars($stockGroups[$activeStockSource]['title'])) ?> stock available.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Bulk Action Bar -->
<div id="bulkActionBar" class="d-none" style="position:sticky; bottom:0; z-index:1030; background:#fff; border-top:2px solid #0d6efd; padding:12px 20px; box-shadow:0 -4px 12px rgba(0,0,0,0.1);">
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary fs-6" id="bulkSelectedCount">0</span>
            <span class="text-dark fw-semibold">items selected</span>
        </div>
        <div class="d-flex gap-2 ms-auto flex-wrap">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="bulkClearSelection">
                <i class="bi bi-x-circle me-1"></i>Clear
            </button>
            <?php if ($canEditStock): ?>
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bulkPriceModal">
                <i class="bi bi-currency-rupee me-1"></i>Change Price
            </button>
            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#bulkComplementaryModal">
                <i class="bi bi-gift me-1"></i>Mark as Complementary
            </button>
            <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#bulkUndoComplementaryModal">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Undo Complementary
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Bulk Price Update -->
<?php if ($canEditStock): ?>
<div class="modal fade" id="bulkPriceModal" tabindex="-1" aria-labelledby="bulkPriceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <form method="post" action="<?= \App\Core\Controller::url('/m/stocks/bulk-update-price') ?>">
                <div class="modal-header bg-light py-3 border-bottom">
                    <h5 class="modal-title fw-bold text-dark" id="bulkPriceModalLabel">
                        <i class="bi bi-currency-rupee me-2 text-primary"></i>Bulk Change Price
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="concern_id" id="bulkPriceConcernId" value="<?= (int)($selectedCid ?? 0) ?>">
                    <div id="bulkPriceVariantInputs"><!-- filled by JS --></div>
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="bi bi-info-circle me-1"></i> Updating price for <strong id="bulkPriceCount">0</strong> selected product(s).
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="bulkNewPrice">New Selling Price (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" class="form-control" id="bulkNewPrice" name="price" step="0.01" min="0" required placeholder="e.g. 350.00">
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold" for="bulkNewMrp">New MRP (₹) <span class="text-muted small">(optional — leave blank to keep current)</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" class="form-control" id="bulkNewMrp" name="mrp" step="0.01" min="0" placeholder="Leave blank to keep current MRP">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Update Price</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Bulk Mark as Complementary -->
<div class="modal fade" id="bulkComplementaryModal" tabindex="-1" aria-labelledby="bulkComplementaryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <form method="post" action="<?= \App\Core\Controller::url('/m/stocks/bulk-assign-complementary') ?>">
                <div class="modal-header bg-warning bg-opacity-10 py-3 border-bottom">
                    <h5 class="modal-title fw-bold text-dark" id="bulkComplementaryModalLabel">
                        <i class="bi bi-gift me-2 text-warning"></i>Mark as Complementary
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="concern_id" id="bulkCompConcernId" value="<?= (int)($selectedCid ?? 0) ?>">
                    <div id="bulkCompVariantInputs"><!-- filled by JS --></div>
                    <div class="alert alert-warning py-2 small mb-3">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Confirm:</strong> The following <span id="bulkCompCount">0</span> product(s) will be flagged as <strong>Complementary</strong>:
                    </div>
                    <ul class="list-group list-group-flush small mb-0" id="bulkCompProductList" style="max-height:200px; overflow-y:auto;"></ul>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark"><i class="bi bi-gift me-1"></i>Confirm Complementary</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Undo Complementary -->
<?php if ($canEditStock): ?>
<div class="modal fade" id="bulkUndoComplementaryModal" tabindex="-1" aria-labelledby="bulkUndoComplementaryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <form method="post" action="<?= \App\Core\Controller::url('/m/stocks/bulk-unassign-complementary') ?>">
                <div class="modal-header bg-secondary bg-opacity-10 py-3 border-bottom">
                    <h5 class="modal-title fw-bold text-dark" id="bulkUndoComplementaryModalLabel">
                        <i class="bi bi-arrow-counterclockwise me-2 text-secondary"></i>Undo Complementary
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="concern_id" id="bulkUndoConcernId" value="<?= (int)($selectedCid ?? 0) ?>">
                    <div id="bulkUndoVariantInputs"><!-- filled by JS --></div>
                    <div class="alert alert-secondary py-2 small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Confirm:</strong> The following <span id="bulkUndoCount">0</span> product(s) will be restored to <strong>Normal</strong> (complementary flag removed):
                    </div>
                    <ul class="list-group list-group-flush small mb-0" id="bulkUndoProductList" style="max-height:200px; overflow-y:auto;"></ul>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Confirm Undo</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: View Product & Invoice Details -->
<div class="modal fade" id="stockDetailsModal" tabindex="-1" aria-labelledby="stockDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-light py-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="stat-icon-box bg-primary bg-opacity-10 text-primary p-2 rounded">
                        <i class="bi bi-card-checklist fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="stockDetailsModalLabel">Product & Purchase Invoice Details</h5>
                        <small class="text-muted" id="detailModalSubtitle">Product specification and purchase billing invoice audit</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Top Summary Banner -->
                <div class="p-3 mb-3 bg-light rounded-3 border d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-dark font-monospace fs-6" id="detailSkuBadge">-</span>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" id="detailSourceBadge">Stock Line</span>
                        </div>
                        <h5 class="fw-bold text-dark mb-0" id="detailVariantName">-</h5>
                        <div class="text-muted small mt-1">
                            Location: <strong id="detailLocationText" class="text-dark">-</strong> &bull; Concern: <strong id="detailConcernText" class="text-dark">-</strong>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="text-secondary small fw-semibold text-uppercase d-block">Current Stock Balance</span>
                        <h3 class="fw-bold text-primary mb-0" id="detailStockQty">-</h3>
                        <small class="text-muted">Total across locations: <span id="detailTotalStockQty" class="fw-semibold text-dark">-</span></small>
                    </div>
                </div>

                <!-- Product QR Code Card -->
                <div class="card border mb-3 shadow-none bg-light bg-opacity-25" id="productQrCard">
                    <div class="card-header bg-white py-2 fw-semibold text-dark d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-qr-code-scan me-2 text-primary fs-5"></i>
                            <span class="fw-bold">Product QR Code & Identification</span>
                        </div>
                        <span class="badge bg-primary bg-opacity-10 text-primary border" id="detailQrBadge">QR Active</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <div class="p-2 bg-white rounded border shadow-sm d-flex align-items-center justify-content-center" style="min-width: 106px; min-height: 106px;">
                                <div id="detailProductQrBox"></div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="row g-2 small">
                                    <div class="col-sm-6 col-lg-3">
                                        <span class="text-muted d-block">QR Tag / Payload:</span>
                                        <strong id="detailQrTagCode" class="text-primary font-monospace fs-6">-</strong>
                                    </div>
                                    <div class="col-sm-6 col-lg-3">
                                        <span class="text-muted d-block">SKU Code:</span>
                                        <strong id="detailQrSkuCode" class="text-dark font-monospace">-</strong>
                                    </div>
                                    <div class="col-sm-6 col-lg-3">
                                        <span class="text-muted d-block">Style Code:</span>
                                        <strong id="detailQrStyleCode" class="text-dark font-monospace">-</strong>
                                    </div>
                                    <div class="col-sm-6 col-lg-3">
                                        <span class="text-muted d-block">Whole Code (Label QR):</span>
                                        <strong id="detailQrWholeCode" class="text-secondary font-monospace">-</strong>
                                    </div>
                                </div>
                                <div class="mt-2 pt-1 d-flex flex-wrap gap-2">
                                    <a id="detailBtnPrintLabel" href="#" target="_blank" class="btn btn-sm btn-primary">
                                        <i class="bi bi-printer me-1"></i> Print Label
                                    </a>
                                    <button type="button" id="detailBtnCopyQr" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-clipboard me-1"></i> Copy Code
                                    </button>
                                    <button type="button" id="detailBtnDownloadQr" class="btn btn-sm btn-outline-dark">
                                        <i class="bi bi-download me-1"></i> Download QR
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product Specifications Card -->
                <div class="card border mb-3 shadow-none">
                    <div class="card-header bg-white py-2 fw-semibold text-dark d-flex align-items-center">
                        <i class="bi bi-info-circle me-2 text-primary"></i> Product Specifications
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-3 small">
                            <div class="col-sm-6 col-md-4">
                                <span class="text-muted d-block">Category</span>
                                <strong id="detailCategory" class="text-dark">-</strong>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <span class="text-muted d-block">Subcategory</span>
                                <strong id="detailSubcategory" class="text-dark">-</strong>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <span class="text-muted d-block">Sub-Subcategory</span>
                                <strong id="detailSubSubcategory" class="text-dark">-</strong>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <span class="text-muted d-block">Size</span>
                                <strong id="detailSize" class="text-dark">-</strong>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <span class="text-muted d-block">Color / Shade</span>
                                <strong id="detailColor" class="text-dark">-</strong>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <span class="text-muted d-block">HSN Code</span>
                                <strong id="detailHsn" class="text-dark font-monospace">-</strong>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <span class="text-muted d-block">GST Rate</span>
                                <strong id="detailGstRate" class="text-dark">-</strong>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <span class="text-muted d-block">Product Style Code</span>
                                <strong id="detailSpecStyleCode" class="text-dark font-monospace">-</strong>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <span class="text-muted d-block">Unit Weight</span>
                                <strong id="detailWeight" class="text-dark">-</strong>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <span class="text-muted d-block">Cost Price</span>
                                <strong id="detailCostPrice" class="text-dark text-success">-</strong>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <span class="text-muted d-block">Selling Price</span>
                                <strong id="detailSellingPrice" class="text-dark">-</strong>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <span class="text-muted d-block">Selling MRP</span>
                                <strong id="detailMrp" class="text-dark">-</strong>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <span class="text-muted d-block">Stock Valuation at Cost</span>
                                <strong id="detailValuation" class="text-dark text-primary">-</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Production & Manufacturing Audit Trail Card (for Own Products) -->
                <div class="card border mb-3 shadow-none d-none" id="productionJourneyCard">
                    <div class="card-header bg-white py-2 fw-semibold text-dark d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-diagram-3-fill me-2 text-primary"></i>
                            <span>Production & Manufacturing Audit Trail</span>
                        </div>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" id="detailProductionBadge">In-House Manufacturing</span>
                    </div>
                    <div class="card-body p-3">
                        <div id="productionLoadingSpinner" class="text-center py-4 text-muted d-none">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                            Loading manufacturing audit trail...
                        </div>
                        <div id="productionDetailsContent">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded border h-100">
                                        <h6 class="fw-bold text-dark mb-2 small text-uppercase d-flex align-items-center">
                                            <i class="bi bi-palette me-1 text-primary"></i> 1. Product Style & Hierarchy
                                        </h6>
                                        <div class="d-flex justify-content-between small py-1 border-bottom">
                                            <span class="text-muted">Style Code:</span>
                                            <span class="fw-bold text-dark font-monospace" id="detailProdStyleCode">-</span>
                                        </div>
                                        <div class="d-flex justify-content-between small py-1 border-bottom">
                                            <span class="text-muted">Style Name:</span>
                                            <span class="fw-semibold text-dark" id="detailProdStyleName">-</span>
                                        </div>
                                        <div class="d-flex justify-content-between small py-1 border-bottom">
                                            <span class="text-muted">Hierarchy:</span>
                                            <span class="text-dark" id="detailProdHierarchy">-</span>
                                        </div>
                                        <div class="d-flex justify-content-between small py-1">
                                            <span class="text-muted">Production Program:</span>
                                            <span class="fw-semibold text-primary" id="detailProdProgram">-</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded border h-100">
                                        <h6 class="fw-bold text-dark mb-2 small text-uppercase d-flex align-items-center">
                                            <i class="bi bi-scissors me-1 text-danger"></i> 2. Fabric Cutting Stage
                                        </h6>
                                        <div class="d-flex justify-content-between small py-1 border-bottom">
                                            <span class="text-muted">Cutting Batch #:</span>
                                            <span class="fw-bold text-dark font-monospace" id="detailProdCuttingBatch">-</span>
                                        </div>
                                        <div class="d-flex justify-content-between small py-1 border-bottom">
                                            <span class="text-muted">Cutting Master:</span>
                                            <span class="fw-semibold text-dark" id="detailProdCuttingMaster">-</span>
                                        </div>
                                        <div class="d-flex justify-content-between small py-1 border-bottom">
                                            <span class="text-muted">Cut Quantity:</span>
                                            <span class="fw-bold text-primary" id="detailProdCuttingOutput">-</span>
                                        </div>
                                        <div class="d-flex justify-content-between small py-1">
                                            <span class="text-muted">Fabric Consumed:</span>
                                            <span class="text-dark" id="detailProdCuttingFabric">-</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded border h-100">
                                        <h6 class="fw-bold text-dark mb-2 small text-uppercase d-flex align-items-center">
                                            <i class="bi bi-layers me-1 text-info"></i> 3. Stitching & Tailoring Stage
                                        </h6>
                                        <div class="d-flex justify-content-between small py-1 border-bottom">
                                            <span class="text-muted">Stitching Batch #:</span>
                                            <span class="fw-bold text-dark font-monospace" id="detailProdStitchingBatch">-</span>
                                        </div>
                                        <div class="d-flex justify-content-between small py-1 border-bottom">
                                            <span class="text-muted">Assigned Tailor:</span>
                                            <span class="fw-semibold text-dark" id="detailProdTailor">-</span>
                                        </div>
                                        <div class="d-flex justify-content-between small py-1 border-bottom">
                                            <span class="text-muted">Stitched Units:</span>
                                            <span class="fw-bold text-success" id="detailProdStitchingOutput">-</span>
                                        </div>
                                        <div class="d-flex justify-content-between small py-1">
                                            <span class="text-muted">Garment Weight:</span>
                                            <span class="text-dark" id="detailProdStitchingWeight">-</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded border h-100">
                                        <h6 class="fw-bold text-dark mb-2 small text-uppercase d-flex align-items-center">
                                            <i class="bi bi-shield-check me-1 text-success"></i> 4. Quality Control & Stock Inward
                                        </h6>
                                        <div class="d-flex justify-content-between small py-1 border-bottom">
                                            <span class="text-muted">QC Inspection #:</span>
                                            <span class="fw-bold text-dark font-monospace" id="detailProdQcNo">-</span>
                                        </div>
                                        <div class="d-flex justify-content-between small py-1 border-bottom">
                                            <span class="text-muted">Inspector & Verdict:</span>
                                            <span><span class="fw-semibold text-dark" id="detailProdQcInspector">-</span> <span class="badge bg-success ms-1" id="detailProdQcStatus">PASSED</span></span>
                                        </div>
                                        <div class="d-flex justify-content-between small py-1 border-bottom">
                                            <span class="text-muted">Passed / Rework / Rejected:</span>
                                            <span>
                                                <span class="text-success fw-bold" id="detailProdQcPassed">0</span> passed &bull; 
                                                <span class="text-warning fw-bold" id="detailProdQcRework">0</span> rework &bull; 
                                                <span class="text-danger fw-bold" id="detailProdQcRejected">0</span> rejected
                                            </span>
                                        </div>
                                        <div class="d-flex justify-content-between small py-1">
                                            <span class="text-muted">Inward Batch / Storage:</span>
                                            <span class="fw-semibold text-dark font-monospace" id="detailProdBatch">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Purchase Billing & Invoice Details Card -->
                <div class="card border shadow-none" id="purchaseInvoiceCard">
                    <div class="card-header bg-white py-2 fw-semibold text-dark d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-receipt me-2 text-success"></i> Purchase Billing & Invoice Information
                        </div>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" id="detailInvoiceStatusBadge">Purchase Bill</span>
                    </div>
                    <div class="card-body p-3">
                        <div id="invoiceLoadingSpinner" class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                            Loading invoice and purchase billing details...
                        </div>
                        <div id="invoiceDetailsContent" class="d-none">
                            <!-- Invoice & Bill Numbers Banner -->
                            <div class="p-3 bg-success bg-opacity-10 rounded border border-success border-opacity-25 mb-3">
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-4">
                                        <span class="text-muted small d-block">Invoice / Bill Number</span>
                                        <span class="fw-bold text-success fs-5 font-monospace" id="detailSupplierInvoiceNo">-</span>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="text-muted small d-block">PO / Bill No</span>
                                        <span class="badge bg-white text-dark border font-monospace fs-6" id="detailBillNo">-</span>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="text-muted small d-block">Bill Date</span>
                                        <span class="fw-semibold text-dark" id="detailBillDate">-</span>
                                    </div>
                                    <div class="col-md-2 text-md-end">
                                        <span class="text-muted small d-block">Payment</span>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border" id="detailPaymentStatus">-</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Supplier Details & Financials -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded border h-100">
                                        <h6 class="fw-bold text-dark mb-2 small text-uppercase"><i class="bi bi-building me-1 text-primary"></i> Supplier / Vendor</h6>
                                        <div class="fw-semibold text-dark" id="detailSupplierName">-</div>
                                        <div class="small text-muted mb-1" id="detailSupplierCompany">-</div>
                                        <div class="small text-muted">Phone: <span id="detailSupplierPhone" class="text-dark">-</span></div>
                                        <div class="small text-muted">Email: <span id="detailSupplierEmail" class="text-dark">-</span></div>
                                        <div class="small text-muted">GSTIN: <span id="detailSupplierGstin" class="text-dark font-monospace">-</span></div>
                                        <div class="small text-muted mt-1">Address: <span id="detailSupplierAddress" class="text-dark">-</span></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded border h-100">
                                        <h6 class="fw-bold text-dark mb-2 small text-uppercase"><i class="bi bi-calculator me-1 text-success"></i> Billing Financials</h6>
                                        <div class="d-flex justify-content-between small py-1 border-bottom">
                                            <span class="text-muted">Billed Item Rate:</span>
                                            <span class="fw-semibold text-dark" id="detailItemUnitPrice">-</span>
                                        </div>
                                        <div class="d-flex justify-content-between small py-1 border-bottom">
                                            <span class="text-muted">GST Rate & Type:</span>
                                            <span class="fw-semibold text-dark" id="detailItemGst">-</span>
                                        </div>
                                        <div class="d-flex justify-content-between small py-1 border-bottom">
                                            <span class="text-muted">Tax Amount:</span>
                                            <span class="fw-semibold text-dark" id="detailItemTaxAmount">-</span>
                                        </div>
                                        <div class="d-flex justify-content-between small py-1 border-bottom">
                                            <span class="text-muted">Item Line Total:</span>
                                            <span class="fw-semibold text-dark" id="detailItemTotalPrice">-</span>
                                        </div>
                                        <div class="d-flex justify-content-between small py-1 mt-1">
                                            <span class="fw-bold text-dark">Overall Bill Net:</span>
                                            <span class="fw-bold text-success fs-6" id="detailBillNet">-</span>
                                        </div>
                                        <div class="small text-muted mt-2 pt-2 border-top" id="detailBillNotesRow">
                                            Notes: <span id="detailBillNotes" class="fst-italic text-dark">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Invoice Attachments Section -->
                            <div class="p-3 bg-light rounded border">
                                <h6 class="fw-bold text-dark mb-2 small text-uppercase d-flex align-items-center">
                                    <i class="bi bi-paperclip me-1 text-danger"></i> Tax Invoice & Bill Attachments
                                </h6>
                                <div id="detailAttachmentContainer" class="d-flex flex-wrap gap-2 pt-1">
                                    <span class="text-muted small">No attachments uploaded with this bill.</span>
                                </div>
                            </div>
                        </div>

                        <!-- Production Source Notice (if not purchase) -->
                        <div id="invoiceNotApplicableNotice" class="d-none text-center py-4 text-muted">
                            <i class="bi bi-scissors fs-2 text-secondary d-block mb-2"></i>
                            <h6 class="fw-bold text-dark">Production Manufactured Product</h6>
                            <p class="small mb-0 text-muted">This product was manufactured internally via production cutting & stitching workflows. No vendor purchase invoice is associated with this finished good.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php if ($canEditStock): ?>
<!-- Modal: Edit All Product & Stock Details -->
<div class="modal fade" id="stockEditModal" tabindex="-1" aria-labelledby="stockEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <form method="post" action="<?= \App\Core\Controller::url('/m/stocks/update-details') ?>">
                <div class="modal-header bg-light py-3 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <div class="stat-icon-box bg-secondary bg-opacity-10 text-dark p-2 rounded">
                            <i class="bi bi-pencil-square fs-5"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="stockEditModalLabel">Edit Product & Stock Details</h5>
                            <small class="text-muted">Modify product specifications, attributes, pricing, and live inventory balance</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="concern_id" id="stockEditConcernId">
                    <input type="hidden" name="variant_id" id="stockEditVariantId">
                    <input type="hidden" name="location" id="stockEditLocation">

                    <div class="alert alert-info py-2 px-3 small mb-3 d-flex align-items-center">
                        <i class="bi bi-geo-alt-fill me-2 fs-5"></i>
                        <div>
                            Editing stock line at location: <strong id="stockEditLocationBadge">Main Store</strong>. 
                            Any quantity change will be automatically logged to the stock ledger audit trail.
                        </div>
                    </div>

                    <!-- 1. Product Identity -->
                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-1"><i class="bi bi-tag me-1 text-primary"></i> 1. Product Identification</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="form-label fw-semibold small text-muted" for="stockEditName">Variant / Product Name *</label>
                            <input type="text" class="form-control" id="stockEditName" name="variant_name" required maxlength="190">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small text-muted" for="stockEditSku">SKU Code *</label>
                            <input type="text" class="form-control font-monospace" id="stockEditSku" name="sku_code" required maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted" for="stockEditCategory">Category</label>
                            <select class="form-select" id="stockEditCategory" name="category_id">
                                <option value="">-- Select Category --</option>
                                <?php if (!empty($categories)): ?>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= (int)$cat['id'] ?>" data-concern-id="<?= (int)$cat['concern_id'] ?>">
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted" for="stockEditSubcategory">Subcategory</label>
                            <select class="form-select" id="stockEditSubcategory" name="subcategory_id">
                                <option value="">-- Select Subcategory --</option>
                                <?php if (!empty($subcategories)): ?>
                                    <?php foreach ($subcategories as $sub): ?>
                                        <option value="<?= (int)$sub['id'] ?>" data-category-id="<?= (int)$sub['category_id'] ?>" data-concern-id="<?= (int)$sub['concern_id'] ?>">
                                            <?= htmlspecialchars($sub['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <!-- 2. Specifications & Attributes -->
                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-1"><i class="bi bi-sliders me-1 text-primary"></i> 2. Specifications & Attributes</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted" for="stockEditSize">Size</label>
                            <input type="text" class="form-control" id="stockEditSize" name="size" placeholder="e.g. S, M, L, Free" maxlength="50">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted" for="stockEditColor">Color / Shade</label>
                            <input type="text" class="form-control" id="stockEditColor" name="color" placeholder="e.g. Red, Black" maxlength="50">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted" for="stockEditHsn">HSN Code</label>
                            <input type="text" class="form-control font-monospace" id="stockEditHsn" name="hsn_code" placeholder="e.g. 6109" maxlength="50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted" for="stockEditWeight">Unit Weight</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="stockEditWeight" name="weight" step="0.001" min="0" placeholder="0.000">
                                <select class="form-select" style="max-width: 100px;" id="stockEditWeightUnit" name="weight_unit">
                                    <option value="kg">kg</option>
                                    <option value="g">g</option>
                                    <option value="pcs">pcs</option>
                                    <option value="m">m</option>
                                    <option value="roll">roll</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted" for="stockEditQuantity">Live Stock Quantity (pcs) *</label>
                            <input type="number" class="form-control fw-bold text-primary" id="stockEditQuantity" name="qty" min="0" max="9999999999" step="1" required>
                        </div>
                    </div>

                    <!-- 3. Pricing & Valuation -->
                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-1"><i class="bi bi-cash-stack me-1 text-primary"></i> 3. Pricing & Valuation</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted" for="stockEditCostPrice">Cost Price (₹) *</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" id="stockEditCostPrice" name="cost_price" step="0.01" min="0" max="9999999999.99" required placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted" for="stockEditPrice">Selling Price (₹) *</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" id="stockEditPrice" name="price" step="0.01" min="0" max="9999999999.99" required placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted" for="stockEditMrp">Selling MRP (₹) *</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" id="stockEditMrp" name="mrp" step="0.01" min="0" max="9999999999.99" required placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-12">
                            <div id="stockEditPriceValidation" class="small text-danger" aria-live="polite"></div>
                            <small class="form-text text-muted">Enter prices in this order: Cost Price &lt; Selling Price &lt; MRP.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Fallback / Backward Compatibility Modal for Quantity Only -->
<div class="modal fade d-none" id="stockQuantityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <input type="hidden" id="stockQuantityVariantName">
            <span id="stockQuantityLocation"></span>
            <input type="hidden" id="stockQuantityConcernId">
            <input type="hidden" id="stockQuantityVariantId">
            <input type="hidden" id="stockQuantityLocationInput">
            <input type="hidden" id="stockQuantityInput">
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('[data-stock-source-tab]').forEach(button => {
    button.addEventListener('click', () => {
        const source = button.dataset.stockSourceTab;
        document.querySelectorAll('[data-stock-source-tab]').forEach(tab => {
            const active = tab === button;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            const count = tab.querySelector('.badge');
            if (count) {
                count.classList.toggle('bg-light', active);
                count.classList.toggle('text-primary', active);
                count.classList.toggle('bg-white', !active);
                count.classList.toggle('text-dark', !active);
                count.classList.toggle('border', !active);
            }
        });
        const rows = document.querySelectorAll('tr[data-stock-source]');
        let visibleCount = 0;
        rows.forEach(row => {
            const visible = row.dataset.stockSource === source;
            row.classList.toggle('d-none', !visible);
            if (visible) visibleCount++;
        });
        const emptyRow = document.getElementById('stock-source-empty-row');
        if (emptyRow) {
            emptyRow.classList.toggle('d-none', visibleCount > 0);
            emptyRow.querySelector('td').textContent = `No ${source === 'own' ? 'own' : (source === 'purchase' ? 'purchase' : 'other')} product stock available.`;
        }
    });
});

// View Details Modal Handler
document.querySelectorAll('[data-stock-view]').forEach(button => {
    button.addEventListener('click', () => {
        const d = button.dataset;
        const variantId = d.variantId || '';
        const concernId = d.concernId || '';
        const location = d.location || '';
        const source = d.source || 'other';

        // Prepopulate basic info immediately from dataset
        document.getElementById('detailSkuBadge').textContent = d.skuCode || '-';
        document.getElementById('detailVariantName').textContent = d.stockName || '-';
        document.getElementById('detailLocationText').textContent = location || '-';
        document.getElementById('detailConcernText').textContent = d.concernName || ('Concern #' + concernId);
        document.getElementById('detailSourceBadge').textContent = source === 'purchase' ? 'Purchase Product' : (source === 'own' ? 'Own Product' : 'Stock Line');
        document.getElementById('detailStockQty').textContent = (d.quantity || '0') + ' ' + (d.unit || 'pcs');
        document.getElementById('detailTotalStockQty').textContent = (d.quantity || '0') + ' ' + (d.unit || 'pcs');
        document.getElementById('detailCategory').textContent = d.category || '-';
        document.getElementById('detailSubcategory').textContent = d.subcategory || '-';
        document.getElementById('detailSize').textContent = d.size || 'Free';
        document.getElementById('detailColor').textContent = d.color || '-';
        document.getElementById('detailHsn').textContent = d.hsn || '-';
        document.getElementById('detailWeight').textContent = d.weight || '-';
        document.getElementById('detailCostPrice').textContent = '₹' + (d.costPrice || '0.00');
        document.getElementById('detailSellingPrice').textContent = '₹' + (d.price || '0.00');
        document.getElementById('detailMrp').textContent = '₹' + (d.mrp || '0.00');
        const valuation = (parseFloat(d.quantity || 0) * parseFloat(d.costPrice || 0)).toFixed(2);
        document.getElementById('detailValuation').textContent = '₹' + valuation;

        // Reset Purchase Billing Card state
        const spinner = document.getElementById('invoiceLoadingSpinner');
        const content = document.getElementById('invoiceDetailsContent');
        const notice = document.getElementById('invoiceNotApplicableNotice');
        spinner.classList.remove('d-none');
        content.classList.add('d-none');
        notice.classList.add('d-none');

        // Fetch rich live details from AJAX endpoint
        const url = '<?= \App\Core\Controller::url('/m/stocks/details') ?>?variant_id=' + encodeURIComponent(variantId) + 
                    '&concern_id=' + encodeURIComponent(concernId) + 
                    '&location=' + encodeURIComponent(location);
        
        fetch(url)
            .then(res => res.json())
            .then(data => {
                spinner.classList.add('d-none');
                if (!data || !data.success) {
                    if (source === 'purchase') {
                        notice.classList.remove('d-none');
                        notice.querySelector('h6').textContent = 'Purchase Invoice Details Not Linked';
                        notice.querySelector('p').textContent = (data && data.message) ? data.message : 'No purchase billing entry was linked to this variant.';
                    } else {
                        notice.classList.remove('d-none');
                    }
                    return;
                }

                // Update with full server values
                if (data.variant) {
                    const v = data.variant;
                    document.getElementById('detailSkuBadge').textContent = v.sku_code || '-';
                    document.getElementById('detailVariantName').textContent = v.name || '-';
                    document.getElementById('detailCategory').textContent = v.category_name || '-';
                    document.getElementById('detailSubcategory').textContent = v.subcategory_name || '-';
                    document.getElementById('detailSize').textContent = v.size || 'Free';
                    document.getElementById('detailColor').textContent = v.color || '-';
                    document.getElementById('detailHsn').textContent = v.hsn || '-';
                    document.getElementById('detailWeight').textContent = v.weight > 0 ? (v.weight + ' ' + v.weight_unit) : '-';
                    document.getElementById('detailCostPrice').textContent = '₹' + Number(v.cost_price).toFixed(2);
                    document.getElementById('detailSellingPrice').textContent = '₹' + Number(v.price).toFixed(2);
                    document.getElementById('detailMrp').textContent = '₹' + Number(v.mrp).toFixed(2);
                }
                if (data.stock) {
                    document.getElementById('detailStockQty').textContent = Number(data.stock.qty) + ' pcs';
                    document.getElementById('detailValuation').textContent = '₹' + Number(data.stock.net_value).toFixed(2);
                }
                if (data.total_stock_qty !== undefined) {
                    document.getElementById('detailTotalStockQty').textContent = Number(data.total_stock_qty) + ' pcs';
                }

                // Render Purchase Bill details if present
                if (data.purchase_bill) {
                    const pb = data.purchase_bill;
                    content.classList.remove('d-none');
                    notice.classList.add('d-none');

                    document.getElementById('detailSupplierInvoiceNo').textContent = pb.supplier_invoice_no || '-';
                    document.getElementById('detailBillNo').textContent = pb.bill_no || '-';
                    document.getElementById('detailBillDate').textContent = pb.bill_date ? new Date(pb.bill_date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
                    
                    const payStatusEl = document.getElementById('detailPaymentStatus');
                    payStatusEl.textContent = (pb.payment_status || 'Pending').toUpperCase() + (pb.payment_method ? ' (' + pb.payment_method + ')' : '');
                    if ((pb.payment_status || '').toLowerCase() === 'paid') {
                        payStatusEl.className = 'badge bg-success bg-opacity-10 text-success border border-success border-opacity-25';
                    } else {
                        payStatusEl.className = 'badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25';
                    }

                    // Supplier
                    if (pb.supplier) {
                        const sup = pb.supplier;
                        document.getElementById('detailSupplierName').textContent = sup.name || '-';
                        document.getElementById('detailSupplierCompany').textContent = sup.company_name || '-';
                        document.getElementById('detailSupplierPhone').textContent = sup.phone || '-';
                        document.getElementById('detailSupplierEmail').textContent = sup.email || '-';
                        document.getElementById('detailSupplierGstin').textContent = sup.gstin || '-';
                        const addressParts = [sup.address, sup.city, sup.district, sup.state].filter(x => x && x !== '-').join(', ');
                        document.getElementById('detailSupplierAddress').textContent = addressParts || '-';
                    }

                    // Financials
                    document.getElementById('detailItemUnitPrice').textContent = '₹' + Number(pb.item_unit_price || 0).toFixed(2);
                    document.getElementById('detailItemGst').textContent = Number(pb.item_gst_rate || 0) + '% (' + (pb.item_gst_type || 'GST') + ' - ' + (pb.item_tax_mode || '') + ')';
                    document.getElementById('detailItemTaxAmount').textContent = '₹' + Number(pb.item_tax_amount || 0).toFixed(2);
                    document.getElementById('detailItemTotalPrice').textContent = '₹' + Number(pb.item_total_price || 0).toFixed(2);
                    document.getElementById('detailBillNet').textContent = '₹' + Number(pb.net_amount || 0).toFixed(2);
                    
                    const notesRow = document.getElementById('detailBillNotesRow');
                    if (pb.notes) {
                        notesRow.classList.remove('d-none');
                        document.getElementById('detailBillNotes').textContent = pb.notes;
                    } else {
                        notesRow.classList.add('d-none');
                    }

                    // Attachments
                    const attachContainer = document.getElementById('detailAttachmentContainer');
                    attachContainer.innerHTML = '';
                    if (pb.attachments && pb.attachments.length > 0) {
                        pb.attachments.forEach((url, idx) => {
                            const isImg = /\.(jpg|jpeg|png|webp|gif)($|\?)/i.test(url);
                            const card = document.createElement('div');
                            card.className = 'card border p-1 text-center bg-white shadow-sm';
                            card.style.width = '140px';
                            if (isImg) {
                                card.innerHTML = `
                                    <a href="${url}" target="_blank" rel="noopener" class="d-block mb-1">
                                        <img src="${url}" alt="Invoice ${idx + 1}" style="width:100%; height:90px; object-fit:cover; border-radius:4px;">
                                    </a>
                                    <a href="${url}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-primary py-0 small" style="font-size:0.75rem;">
                                        <i class="bi bi-eye"></i> View File
                                    </a>
                                `;
                            } else {
                                card.innerHTML = `
                                    <div class="py-3 text-danger"><i class="bi bi-file-earmark-pdf fs-2"></i></div>
                                    <a href="${url}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-danger py-0 small text-truncate" style="font-size:0.75rem;">
                                        <i class="bi bi-download"></i> View Document
                                    </a>
                                `;
                            }
                            attachContainer.appendChild(card);
                        });
                    } else {
                        attachContainer.innerHTML = '<span class="text-muted small"><i class="bi bi-info-circle me-1"></i> No invoice attachment was uploaded during purchase billing.</span>';
                    }
                } else {
                    content.classList.add('d-none');
                    notice.classList.remove('d-none');
                }
            })
            .catch(err => {
                spinner.classList.add('d-none');
                notice.classList.remove('d-none');
                notice.querySelector('h6').textContent = 'Unable to Load Invoice';
                notice.querySelector('p').textContent = 'An error occurred while fetching billing details: ' + err.message;
            });
    });
});

// Edit Details Modal Handler
const categorySelect = document.getElementById('stockEditCategory');
const subcategorySelect = document.getElementById('stockEditSubcategory');
const stockEditForm = document.querySelector('#stockEditModal form');
const stockEditCostPrice = document.getElementById('stockEditCostPrice');
const stockEditSellingPrice = document.getElementById('stockEditPrice');
const stockEditMrp = document.getElementById('stockEditMrp');
const stockEditPriceValidation = document.getElementById('stockEditPriceValidation');

function validateStockEditPrices() {
    if (!stockEditCostPrice || !stockEditSellingPrice || !stockEditMrp) return true;

    [stockEditCostPrice, stockEditSellingPrice, stockEditMrp].forEach(input => input.setCustomValidity(''));
    const costPrice = stockEditCostPrice.valueAsNumber;
    const sellingPrice = stockEditSellingPrice.valueAsNumber;
    const mrp = stockEditMrp.valueAsNumber;
    if (![costPrice, sellingPrice, mrp].every(Number.isFinite)) {
        if (stockEditPriceValidation) stockEditPriceValidation.textContent = '';
        return true;
    }

    let message = '';
    if (costPrice >= sellingPrice) {
        message = 'Cost Price must be less than Selling Price.';
        stockEditCostPrice.setCustomValidity(message);
        stockEditSellingPrice.setCustomValidity('Selling Price must be greater than Cost Price.');
    } else if (sellingPrice >= mrp) {
        message = 'Selling Price must be less than MRP.';
        stockEditSellingPrice.setCustomValidity(message);
        stockEditMrp.setCustomValidity('MRP must be greater than Selling Price.');
    }
    if (stockEditPriceValidation) stockEditPriceValidation.textContent = message;
    return message === '';
}

[stockEditCostPrice, stockEditSellingPrice, stockEditMrp].forEach(input => {
    if (input) input.addEventListener('input', validateStockEditPrices);
});
if (stockEditForm) {
    stockEditForm.addEventListener('submit', event => {
        if (!validateStockEditPrices()) {
            event.preventDefault();
            stockEditForm.reportValidity();
        }
    });
}

function filterSubcategories(catId) {
    if (!subcategorySelect) return;
    const catIdStr = String(catId || '');
    let matchedCount = 0;
    Array.from(subcategorySelect.options).forEach(opt => {
        if (!opt.value) {
            opt.hidden = false;
            return;
        }
        const optCatId = opt.dataset.categoryId || '';
        const match = !catIdStr || optCatId === catIdStr;
        opt.hidden = !match;
        if (match) matchedCount++;
    });
}

if (categorySelect) {
    categorySelect.addEventListener('change', () => {
        filterSubcategories(categorySelect.value);
    });
}

document.querySelectorAll('[data-stock-edit]').forEach(button => {
    button.addEventListener('click', () => {
        const d = button.dataset;
        // Populate inputs
        if (document.getElementById('stockEditConcernId')) {
            document.getElementById('stockEditConcernId').value = d.concernId || '';
            document.getElementById('stockEditVariantId').value = d.variantId || '';
            document.getElementById('stockEditLocation').value = d.location || '';
            document.getElementById('stockEditLocationBadge').textContent = d.location || 'Main Store';
            document.getElementById('stockEditName').value = d.stockName || '';
            document.getElementById('stockEditSku').value = d.skuCode || '';
            
            const catId = d.categoryId || '';
            document.getElementById('stockEditCategory').value = catId;
            filterSubcategories(catId);
            document.getElementById('stockEditSubcategory').value = d.subcategoryId || '';

            document.getElementById('stockEditSize').value = d.size || '';
            document.getElementById('stockEditColor').value = d.color || '';
            document.getElementById('stockEditHsn').value = d.hsn || '';
            document.getElementById('stockEditWeight').value = d.weight || '0';
            document.getElementById('stockEditWeightUnit').value = d.weightUnit || 'kg';
            document.getElementById('stockEditCostPrice').value = d.costPrice || '0.00';
            document.getElementById('stockEditPrice').value = d.price || '0.00';
            document.getElementById('stockEditMrp').value = d.mrp || '0.00';
            document.getElementById('stockEditQuantity').value = d.quantity || '0';
            validateStockEditPrices();
        }

        // Backward compatibility support for old modal ids
        if (document.getElementById('stockQuantityVariantName')) {
            document.getElementById('stockQuantityVariantName').textContent = d.stockName || '';
            document.getElementById('stockQuantityLocation').textContent = d.location || '';
            document.getElementById('stockQuantityConcernId').value = d.concernId || '';
            document.getElementById('stockQuantityVariantId').value = d.variantId || '';
            document.getElementById('stockQuantityLocationInput').value = d.location || '';
            document.getElementById('stockQuantityInput').value = d.quantity || '0';
        }
    });
});

document.querySelectorAll('[data-stock-delete-form]').forEach(form => {
    form.addEventListener('submit', event => {
        if (!window.confirm(form.dataset.confirmMessage || 'Set this stock balance to zero?')) {
            event.preventDefault();
        }
    });
});

// ============ BULK SELECT MULTI-SELECT LOGIC ============
let selectedStockItems = [];

function updateBulkBar() {
    const bar = document.getElementById('bulkActionBar');
    const countBadge = document.getElementById('bulkSelectedCount');
    if (!bar) return;
    if (selectedStockItems.length > 0) {
        bar.classList.remove('d-none');
        countBadge.textContent = selectedStockItems.length;
    } else {
        bar.classList.add('d-none');
    }
}

const selectAllCheckbox = document.getElementById('stockSelectAll');
if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener('change', () => {
        const checked = selectAllCheckbox.checked;
        const visibleCheckboxes = document.querySelectorAll('tr:not(.d-none) .stock-row-checkbox');
        if (!checked) {
            selectedStockItems = [];
        }
        visibleCheckboxes.forEach(cb => {
            cb.checked = checked;
            if (checked) {
                const item = { variantId: cb.dataset.variantId, concernId: cb.dataset.concernId, name: cb.dataset.variantName, price: cb.dataset.price, mrp: cb.dataset.mrp };
                if (!selectedStockItems.find(i => i.variantId === item.variantId)) {
                    selectedStockItems.push(item);
                }
            }
        });
        updateBulkBar();
    });
}

document.querySelectorAll('.stock-row-checkbox').forEach(cb => {
    cb.addEventListener('change', () => {
        const item = { variantId: cb.dataset.variantId, concernId: cb.dataset.concernId, name: cb.dataset.variantName, price: cb.dataset.price, mrp: cb.dataset.mrp };
        if (cb.checked) {
            if (!selectedStockItems.find(i => i.variantId === item.variantId)) {
                selectedStockItems.push(item);
            }
        } else {
            selectedStockItems = selectedStockItems.filter(i => i.variantId !== item.variantId);
            if (selectAllCheckbox) selectAllCheckbox.checked = false;
        }
        updateBulkBar();
    });
});

const bulkClearBtn = document.getElementById('bulkClearSelection');
if (bulkClearBtn) {
    bulkClearBtn.addEventListener('click', () => {
        selectedStockItems = [];
        document.querySelectorAll('.stock-row-checkbox').forEach(cb => cb.checked = false);
        if (selectAllCheckbox) selectAllCheckbox.checked = false;
        updateBulkBar();
    });
}

// Populate bulk price modal
const bulkPriceModal = document.getElementById('bulkPriceModal');
if (bulkPriceModal) {
    bulkPriceModal.addEventListener('show.bs.modal', () => {
        const uniqueItems = [];
        const seen = new Set();
        selectedStockItems.forEach(i => { if (!seen.has(i.variantId)) { seen.add(i.variantId); uniqueItems.push(i); } });
        document.getElementById('bulkPriceCount').textContent = uniqueItems.length;
        if (uniqueItems.length > 0) {
            document.getElementById('bulkPriceConcernId').value = uniqueItems[0].concernId;
        }
        const container = document.getElementById('bulkPriceVariantInputs');
        container.innerHTML = '';
        uniqueItems.forEach(item => {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'variant_ids[]'; inp.value = item.variantId;
            container.appendChild(inp);
        });
    });
}

// Populate bulk complementary modal
const bulkCompModal = document.getElementById('bulkComplementaryModal');
if (bulkCompModal) {
    bulkCompModal.addEventListener('show.bs.modal', () => {
        const uniqueItems = [];
        const seen = new Set();
        selectedStockItems.forEach(i => { if (!seen.has(i.variantId)) { seen.add(i.variantId); uniqueItems.push(i); } });
        document.getElementById('bulkCompCount').textContent = uniqueItems.length;
        if (uniqueItems.length > 0) {
            document.getElementById('bulkCompConcernId').value = uniqueItems[0].concernId;
        }
        const inputContainer = document.getElementById('bulkCompVariantInputs');
        inputContainer.innerHTML = '';
        uniqueItems.forEach(item => {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'variant_ids[]'; inp.value = item.variantId;
            inputContainer.appendChild(inp);
        });
        const list = document.getElementById('bulkCompProductList');
        list.innerHTML = '';
        uniqueItems.forEach(item => {
            const li = document.createElement('li');
            li.className = 'list-group-item py-1 px-2';
            li.innerHTML = `<i class="bi bi-box me-1 text-warning"></i><strong>${item.name}</strong> <span class="text-muted small ms-1">— ₹${item.price}</span>`;
            list.appendChild(li);
        });
    });
}
// Populate undo complementary modal
const bulkUndoModal = document.getElementById('bulkUndoComplementaryModal');
if (bulkUndoModal) {
    bulkUndoModal.addEventListener('show.bs.modal', () => {
        const uniqueItems = [];
        const seen = new Set();
        selectedStockItems.forEach(i => { if (!seen.has(i.variantId)) { seen.add(i.variantId); uniqueItems.push(i); } });
        document.getElementById('bulkUndoCount').textContent = uniqueItems.length;
        if (uniqueItems.length > 0) {
            document.getElementById('bulkUndoConcernId').value = uniqueItems[0].concernId;
        }
        const inputContainer = document.getElementById('bulkUndoVariantInputs');
        inputContainer.innerHTML = '';
        uniqueItems.forEach(item => {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'variant_ids[]'; inp.value = item.variantId;
            inputContainer.appendChild(inp);
        });
        const list = document.getElementById('bulkUndoProductList');
        list.innerHTML = '';
        uniqueItems.forEach(item => {
            const li = document.createElement('li');
            li.className = 'list-group-item py-1 px-2';
            li.innerHTML = `<i class="bi bi-box me-1 text-secondary"></i><strong>${item.name}</strong> <span class="text-muted small ms-1">— ₹${item.price}</span>`;
            list.appendChild(li);
        });
    });
}
// ============ END BULK SELECT ============
</script>
