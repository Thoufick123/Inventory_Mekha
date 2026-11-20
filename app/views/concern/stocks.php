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
            <a href="<?= \App\Core\Controller::url('/m/inward') ?>" class="btn btn-primary">
                <i class="bi bi-box-arrow-in-down me-1"></i> Inward Entry
            </a>
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
                    <span class="text-secondary small fw-semibold text-uppercase">Total Stock Valuation</span>
                    <h3 class="fw-bold text-success mt-1 mb-0">₹<?= number_format($totalValuation, 2) ?></h3>
                </div>
                <div class="stat-icon-box bg-success bg-opacity-10 text-success">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
        </div>
    </div>

    <?php
    $showConcernColumn = $isSuperAdmin && empty($selectedCid);
    $stockColumnCount = $showConcernColumn ? 8 : 7;
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
    ];
    foreach ($stocks as $stock) {
        $source = $stock['stock_source'] ?? 'other';
        if (!isset($stockGroups[$source])) {
            continue;
        }
        $stockGroups[$source]['items'][] = $stock;
    }
    $activeStockSource = !empty($stockGroups['own']['items']) ? 'own' : 'purchase';
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
                        <i class="bi <?= $source === 'own' ? 'bi-boxes' : 'bi-bag-check' ?> me-1"></i>
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
                        <th>SKU Code</th>
                        <?php if ($showConcernColumn): ?>
                            <th>Concern</th>
                        <?php endif; ?>
                        <th>Variant Description</th>
                        <th>Category / Specs</th>
                        <th>Qty</th>
                        <th>Unit Rate</th>
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
                                        <td><span class="badge bg-dark font-monospace"><?= htmlspecialchars($s['sku_code']) ?></span></td>
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
                                        </td>
                                        <td>
                                            <div class="small fw-semibold"><?= htmlspecialchars($s['category_name'] ?? 'General') ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($s['subcategory_name'] ?? '-') ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fs-6 px-2 py-1">
                                                <?= number_format((float)$s['qty'], 0) ?> <?= htmlspecialchars($stockUnit) ?>
                                            </span>
                                        </td>
                                        <td>
                                            ₹<?= number_format((float)$s['price'], 2) ?>
                                            <small class="d-block text-muted">MRP ₹<?= number_format((float)$s['mrp'], 2) ?></small>
                                        </td>
                                        <td class="fw-bold text-dark">₹<?= number_format($s['qty'] * $s['price'], 2) ?></td>
                                        <td class="text-end">
                                            <div class="d-inline-flex flex-wrap justify-content-end gap-1">
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
                                                            <i class="bi bi-eye"></i> View Label
                                                        </a>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Purchase label details are unavailable">
                                                            <i class="bi bi-eye-slash"></i> Label Unavailable
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
                                                            data-bs-target="#stockQuantityModal"
                                                            data-concern-id="<?= (int)$s['concern_id'] ?>"
                                                            data-variant-id="<?= (int)$s['variant_id'] ?>"
                                                            data-location="<?= htmlspecialchars((string)$s['location'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-stock-name="<?= htmlspecialchars((string)$s['variant_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-quantity="<?= (int)round((float)$s['qty']) ?>"
                                                            aria-label="Edit quantity for <?= htmlspecialchars((string)$s['variant_name'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <i class="bi bi-pencil-square"></i> Edit Qty
                                                    </button>
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

<?php if ($canEditStock): ?>
<div class="modal fade" id="stockQuantityModal" tabindex="-1" aria-labelledby="stockQuantityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= \App\Core\Controller::url('/m/stocks/update-quantity') ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="stockQuantityModalLabel">Edit Stock Quantity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1 fw-semibold" id="stockQuantityVariantName"></p>
                    <p class="text-muted small mb-3">Location: <span id="stockQuantityLocation"></span>. Changes are recorded in the stock ledger.</p>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="concern_id" id="stockQuantityConcernId">
                    <input type="hidden" name="variant_id" id="stockQuantityVariantId">
                    <input type="hidden" name="location" id="stockQuantityLocationInput">
                    <label class="form-label fw-semibold" for="stockQuantityInput">Qty (pcs)</label>
                    <input type="number" class="form-control" id="stockQuantityInput" name="qty" min="0" max="9999999999" step="1" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Quantity</button>
                </div>
            </form>
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
            emptyRow.querySelector('td').textContent = `No ${source === 'own' ? 'own' : 'purchase'} product stock available.`;
        }
    });
});

document.querySelectorAll('[data-stock-edit]').forEach(button => {
    button.addEventListener('click', () => {
        document.getElementById('stockQuantityVariantName').textContent = button.dataset.stockName || '';
        document.getElementById('stockQuantityLocation').textContent = button.dataset.location || '';
        document.getElementById('stockQuantityConcernId').value = button.dataset.concernId || '';
        document.getElementById('stockQuantityVariantId').value = button.dataset.variantId || '';
        document.getElementById('stockQuantityLocationInput').value = button.dataset.location || '';
        document.getElementById('stockQuantityInput').value = button.dataset.quantity || '0';
    });
});

document.querySelectorAll('[data-stock-delete-form]').forEach(form => {
    form.addEventListener('submit', event => {
        if (!window.confirm(form.dataset.confirmMessage || 'Set this stock balance to zero?')) {
            event.preventDefault();
        }
    });
});

</script>
