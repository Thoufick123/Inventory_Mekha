<?php
// app/views/production/stock_requests/index.php - Send & Receive Stock Requisitions
$activeTab = $activeTab ?? 'receive';
$receivedCount = count($receivedRequests ?? []);
$sentCount = count($sentRequests ?? []);
$concernStocksJson = json_encode($concernStocksMap ?? []);
?>
<div class="container-fluid px-0">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1 text-dark">
                <i class="bi bi-arrow-left-right text-primary me-2"></i>Stock Requisitions & Transfer
            </h4>
            <p class="text-secondary small mb-0">
                Inter-concern and warehouse-to-store requisitions. Track outgoing indents and receive incoming dispatches.
            </p>
        </div>
        <div class="d-flex gap-2">
            <?php if (\App\Core\Auth::can('request', 'add')): ?>
            <button type="button" class="btn btn-primary shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalIndentStock">
                <i class="bi bi-send me-1"></i> Indent Stock
            </button>
            <a href="<?= \App\Core\Controller::url('/production/stock-requests/create') ?>" class="btn btn-outline-secondary shadow-sm">
                <i class="bi bi-sliders me-1"></i> Advanced Form
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Navigation Pills: Send and Receive Methods -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-2">
            <ul class="nav nav-pills nav-fill gap-2" id="stockRequestPills" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= $activeTab === 'receive' ? 'active' : '' ?> fw-semibold py-2" 
                       id="pills-receive-tab" 
                       href="<?= \App\Core\Controller::url('/production/stock-requests?tab=receive' . (!empty($search) ? '&search=' . urlencode($search) : '')) ?>">
                        <i class="bi bi-inbox-fill me-2"></i> Receive Section
                        <span class="badge rounded-pill <?= $activeTab === 'receive' ? 'bg-white text-primary' : 'bg-light text-dark border' ?> ms-2"><?= $receivedCount ?></span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= $activeTab === 'send' ? 'active' : '' ?> fw-semibold py-2" 
                       id="pills-send-tab" 
                       href="<?= \App\Core\Controller::url('/production/stock-requests?tab=send' . (!empty($search) ? '&search=' . urlencode($search) : '')) ?>">
                        <i class="bi bi-send-fill me-2"></i> Send Section
                        <span class="badge rounded-pill <?= $activeTab === 'send' ? 'bg-white text-primary' : 'bg-light text-dark border' ?> ms-2"><?= $sentCount ?></span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= \App\Core\Controller::url('/production/stock-requests') ?>" class="row g-2 align-items-center">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
                <div class="col-12 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search Request #, Notes, Challan..." value="<?= htmlspecialchars($search ?? '') ?>">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- All Statuses --</option>
                        <option value="PENDING" <?= ($status ?? '') === 'PENDING' ? 'selected' : '' ?>>Pending Approval</option>
                        <option value="APPROVED" <?= ($status ?? '') === 'APPROVED' ? 'selected' : '' ?>>Approved</option>
                        <option value="DISPATCHED" <?= ($status ?? '') === 'DISPATCHED' ? 'selected' : '' ?>>Dispatched (In-Transit)</option>
                        <option value="IN_TRANSIT" <?= ($status ?? '') === 'IN_TRANSIT' ? 'selected' : '' ?>>In-Transit</option>
                        <option value="RECEIVED" <?= ($status ?? '') === 'RECEIVED' ? 'selected' : '' ?>>Received & Fulfilled</option>
                        <option value="SHORTAGE" <?= ($status ?? '') === 'SHORTAGE' ? 'selected' : '' ?>>Received with Shortage</option>
                        <option value="REJECTED" <?= ($status ?? '') === 'REJECTED' ? 'selected' : '' ?>>Rejected</option>
                        <option value="CANCELLED" <?= ($status ?? '') === 'CANCELLED' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($fromDate ?? '') ?>">
                </div>
                <div class="col-6 col-md-3 d-flex gap-1">
                    <input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($toDate ?? '') ?>">
                    <button type="submit" class="btn btn-sm btn-primary px-3" title="Filter"><i class="bi bi-funnel"></i></button>
                    <?php if (!empty($search) || !empty($status) || !empty($fromDate) || !empty($toDate)): ?>
                        <a href="<?= \App\Core\Controller::url('/production/stock-requests?tab=' . $activeTab) ?>" class="btn btn-sm btn-light border" title="Clear Filters"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <?php if ($activeTab === 'receive'): ?>
        <!-- ========================================== -->
        <!-- RECEIVE SECTION (IMAGE 1)                  -->
        <!-- ========================================== -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="bi bi-inbox text-primary fs-5 me-2"></i>
                    <h6 class="mb-0 fw-bold text-dark">Stock Requisitions List</h6>
                </div>
                <span class="badge bg-light text-secondary border px-2 py-1"><?= count($receivedRequests) ?> records</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-custom">
                    <thead class="table-light">
                        <tr class="text-uppercase small text-secondary">
                            <th style="min-width: 140px;">REQUEST #</th>
                            <th style="min-width: 180px;">DESTINATION RETAIL STORE</th>
                            <th style="min-width: 180px;">SOURCE WAREHOUSE</th>
                            <th style="min-width: 120px;" class="text-center">REQUESTED QTY</th>
                            <th style="min-width: 120px;" class="text-center">DISPATCHED QTY</th>
                            <th style="min-width: 140px;">REQUESTED BY</th>
                            <th style="min-width: 110px;" class="text-center">STATUS</th>
                            <th style="min-width: 120px;" class="text-end">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($receivedRequests)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="my-4">
                                        <i class="bi bi-shop text-muted" style="font-size: 3.5rem; opacity: 0.4;"></i>
                                        <h5 class="fw-bold text-secondary mt-3 mb-1">No Shop Requests Found</h5>
                                        <p class="text-muted small mb-0">No store stock requisitions match the selected criteria.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($receivedRequests as $req): 
                                $badgeClass = match ($req['status']) {
                                    'RECEIVED'   => 'bg-success',
                                    'DISPATCHED', 'IN_TRANSIT' => 'bg-info text-dark',
                                    'SHORTAGE'   => 'bg-warning text-dark',
                                    'APPROVED'   => 'bg-primary',
                                    'REJECTED'   => 'bg-secondary',
                                    'CANCELLED'  => 'bg-danger',
                                    default      => 'bg-dark'
                                };
                            ?>
                                <tr>
                                    <td>
                                        <a href="<?= \App\Core\Controller::url('/production/stock-requests/view/' . $req['id']) ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                            <?= htmlspecialchars($req['request_no']) ?>
                                        </a>
                                        <small class="d-block text-muted"><?= htmlspecialchars($req['priority'] ?? 'NORMAL') ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($req['destination_concern_name'] ?? 'Main Retail Store') ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($req['to_location_type'] ?? 'SHOP') ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($req['source_concern_name'] ?? 'Central Warehouse') ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($req['from_location_type'] ?? 'WAREHOUSE') ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-dark"><?= number_format((float)$req['requested_qty'], 2) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-primary fw-semibold"><?= number_format((float)$req['dispatched_qty'], 2) ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($req['creator_name'] ?? 'Master Super Admin') ?></div>
                                        <small class="text-muted"><?= date('d M Y', strtotime($req['created_at'])) ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= $badgeClass ?> px-2 py-1"><?= htmlspecialchars($req['status']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= \App\Core\Controller::url('/production/stock-requests/view/' . $req['id']) ?>" class="btn btn-outline-primary" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if (\App\Core\Auth::can('request', 'edit') && in_array($req['status'], ['DISPATCHED', 'IN_TRANSIT'], true)): ?>
                                                <button type="button" class="btn btn-success" title="Confirm Receipt" 
                                                        onclick="openQuickReceive(<?= $req['id'] ?>, '<?= htmlspecialchars($req['request_no']) ?>', <?= (float)$req['in_transit_qty'] ?: (float)$req['dispatched_qty'] ?>)">
                                                    <i class="bi bi-box-arrow-in-down"></i> Receive
                                                </button>
                                            <?php endif; ?>
                                            <?php if (!empty($req['dispatch_challan_no'])): ?>
                                                <a href="<?= \App\Core\Controller::url('/production/stock-requests/print/' . $req['id']) ?>" target="_blank" class="btn btn-outline-secondary" title="Print Challan">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <!-- ========================================== -->
        <!-- SEND SECTION                               -->
        <!-- ========================================== -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="bi bi-send text-primary fs-5 me-2"></i>
                    <h6 class="mb-0 fw-bold text-dark">Sent Stock Requisitions List</h6>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge bg-light text-secondary border px-2 py-1"><?= count($sentRequests) ?> records</span>
                    <?php if (\App\Core\Auth::can('request', 'add')): ?>
                    <button type="button" class="btn btn-sm btn-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#modalIndentStock">
                        <i class="bi bi-plus-lg me-1"></i> New Indent
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-custom">
                    <thead class="table-light">
                        <tr class="text-uppercase small text-secondary">
                            <th style="min-width: 140px;">REQUEST #</th>
                            <th style="min-width: 180px;">DESTINATION CONCERN</th>
                            <th style="min-width: 180px;">SOURCE CONCERN</th>
                            <th style="min-width: 120px;" class="text-center">REQUESTED QTY</th>
                            <th style="min-width: 120px;" class="text-center">DISPATCHED QTY</th>
                            <th style="min-width: 140px;">REQUESTED BY</th>
                            <th style="min-width: 110px;" class="text-center">STATUS</th>
                            <th style="min-width: 120px;" class="text-end">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sentRequests)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="my-4">
                                        <i class="bi bi-send text-muted" style="font-size: 3.5rem; opacity: 0.4;"></i>
                                        <h5 class="fw-bold text-secondary mt-3 mb-1">No Sent Requisitions Found</h5>
                                        <p class="text-muted small mb-3">No stock requisitions have been sent from this concern yet.</p>
                                        <?php if (\App\Core\Auth::can('request', 'add')): ?>
                                        <button type="button" class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalIndentStock">
                                            <i class="bi bi-plus-lg me-1"></i> Indent Stock Now
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sentRequests as $req): 
                                $badgeClass = match ($req['status']) {
                                    'RECEIVED'   => 'bg-success',
                                    'DISPATCHED', 'IN_TRANSIT' => 'bg-info text-dark',
                                    'SHORTAGE'   => 'bg-warning text-dark',
                                    'APPROVED'   => 'bg-primary',
                                    'REJECTED'   => 'bg-secondary',
                                    'CANCELLED'  => 'bg-danger',
                                    default      => 'bg-dark'
                                };
                            ?>
                                <tr>
                                    <td>
                                        <a href="<?= \App\Core\Controller::url('/production/stock-requests/view/' . $req['id']) ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                            <?= htmlspecialchars($req['request_no']) ?>
                                        </a>
                                        <small class="d-block text-muted"><?= htmlspecialchars($req['priority'] ?? 'NORMAL') ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($req['destination_concern_name'] ?? 'Main Retail Store') ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($req['to_location_type'] ?? 'SHOP') ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($req['source_concern_name'] ?? 'Central Warehouse') ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($req['from_location_type'] ?? 'WAREHOUSE') ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-dark"><?= number_format((float)$req['requested_qty'], 2) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-primary fw-semibold"><?= number_format((float)$req['dispatched_qty'], 2) ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($req['creator_name'] ?? 'Master Super Admin') ?></div>
                                        <small class="text-muted"><?= date('d M Y', strtotime($req['created_at'])) ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= $badgeClass ?> px-2 py-1"><?= htmlspecialchars($req['status']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= \App\Core\Controller::url('/production/stock-requests/view/' . $req['id']) ?>" class="btn btn-outline-primary" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if (!empty($req['dispatch_challan_no'])): ?>
                                                <a href="<?= \App\Core\Controller::url('/production/stock-requests/print/' . $req['id']) ?>" target="_blank" class="btn btn-outline-secondary" title="Print Challan">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if (\App\Core\Auth::can('request', 'add')): ?>
<!-- ======================================================= -->
<!-- INDENT STOCK MODAL (IMAGE 2)                            -->
<!-- ======================================================= -->
<div class="modal fade" id="modalIndentStock" tabindex="-1" aria-labelledby="modalIndentStockLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" action="<?= \App\Core\Controller::url('/production/stock-requests/store') ?>" id="formIndentStock">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">

                <div class="modal-header border-0 pb-0 pt-4 px-4 d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="modal-title fw-bold text-dark d-flex align-items-center" id="modalIndentStockLabel">
                            <i class="bi bi-box-arrow-in-down-right text-primary me-2 fs-4"></i>
                            <span>Indent Stock from <span id="indentTargetConcernName" class="text-primary">Central Warehouse</span></span>
                        </h5>
                        <p class="text-secondary small mb-0 mt-1">Select items and enter the quantity you wish to request from the central warehouse.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body px-4 pt-3 pb-2">
                    <!-- 1. Destination Concern Selection Option -->
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-secondary">
                            Destination Concern <span class="text-danger">*</span>
                        </label>
                        <select name="destination_concern_id" id="modalDestConcern" class="form-select form-select-md fw-semibold" required onchange="handleDestinationConcernChange(this)">
                            <?php foreach ($concerns as $con): ?>
                                <option value="<?= $con['id'] ?>" <?= $con['id'] == 5 ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($con['name']) ?> (<?= htmlspecialchars($con['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Stock availability below will dynamically show stock present in this concern.</small>
                    </div>

                    <!-- 2. Search / Select Item to Indent -->
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-secondary">Search / Select Item to Indent</label>
                        <select id="modalVariantPicker" class="form-select form-select-md" onchange="handleVariantPicked(this)">
                            <option value="">-- Choose Product Variant --</option>
                            <?php foreach ($variants as $v): ?>
                                <option value="<?= $v['id'] ?>" 
                                        data-name="<?= htmlspecialchars($v['name']) ?>" 
                                        data-sku="<?= htmlspecialchars($v['sku_code']) ?>"
                                        data-size="<?= htmlspecialchars($v['size'] ?? '') ?>">
                                    <?= htmlspecialchars($v['name']) ?> - <?= htmlspecialchars($v['sku_code']) ?> (Size: <?= htmlspecialchars($v['size'] ?? '-') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 3. Selected Items Table with Concern Stock -->
                    <div class="table-responsive border rounded-3 mb-3 bg-white">
                        <table class="table align-middle mb-0" id="indentItemsTable">
                            <thead class="table-light">
                                <tr class="text-uppercase small text-secondary">
                                    <th style="width: 45%;">ITEM</th>
                                    <th style="width: 25%;" class="text-center">CONCERN STOCK</th>
                                    <th style="width: 20%;" class="text-center">REQUEST QTY</th>
                                    <th style="width: 10%;" class="text-end"></th>
                                </tr>
                            </thead>
                            <tbody id="indentItemsTableBody">
                                <!-- Selected items are added here -->
                            </tbody>
                        </table>
                        <div id="noIndentItemsNotice" class="text-center py-4 text-muted small">
                            <i class="bi bi-box-seam me-1"></i> No items selected yet. Choose a variant from above to add.
                        </div>
                    </div>

                    <!-- 4. Notes / Purpose -->
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-secondary">Notes / Purpose</label>
                        <input type="text" name="notes" class="form-control" placeholder="e.g. Weekend retail replenishment">
                    </div>
                </div>

                <div class="modal-footer border-0 px-4 pb-4 pt-2 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm" id="btnSubmitIndent">
                        <i class="bi bi-send me-1"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ======================================================= -->
<!-- QUICK RECEIVE MODAL                                     -->
<!-- ======================================================= -->
<?php if (\App\Core\Auth::can('request', 'edit')): ?>
<div class="modal fade" id="modalQuickReceive" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" action="<?= \App\Core\Controller::url('/production/stock-requests/receive') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                <input type="hidden" name="request_id" id="receiveRequestId" value="">

                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-dark d-flex align-items-center">
                        <i class="bi bi-box-arrow-in-down text-success me-2 fs-4"></i>
                        Confirm Stock Receipt
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <p class="text-secondary small mb-3">
                        Confirming receipt moves goods from <strong>IN_TRANSIT</strong> directly into this retail store's <strong>AVAILABLE</strong> stock.
                    </p>
                    <div class="bg-light p-3 rounded-3 mb-3 border">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Request No:</span>
                            <span class="fw-bold font-monospace" id="receiveReqNoText">-</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">In-Transit Qty:</span>
                            <span class="fw-bold text-primary" id="receiveTransitQtyText">0.00</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-secondary">Verified Received Qty</label>
                        <input type="number" name="received_qty_total" id="receiveVerifiedQtyInput" class="form-control form-control-lg fw-bold text-success" step="0.01" min="0" required>
                        <small class="text-muted">Any difference from in-transit qty will be recorded as shortage.</small>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-1">
                    <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4 fw-semibold">
                        <i class="bi bi-check2-circle me-1"></i> Confirm & Accept Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Precomputed concern stocks map: { concernId: { variantId: qty } }
const concernStocksMap = <?= $concernStocksJson ?>;

// Get available stock for a given concern & variant
function getConcernStock(concernId, variantId) {
    concernId = parseInt(concernId);
    variantId = parseInt(variantId);
    if (concernStocksMap[concernId] && concernStocksMap[concernId][variantId] !== undefined) {
        return parseFloat(concernStocksMap[concernId][variantId]);
    }
    return 0.00;
}

// Handle Destination Concern Change
function handleDestinationConcernChange(select) {
    const concernId = select.value;
    const selectedOption = select.options[select.selectedIndex];
    const concernName = selectedOption ? selectedOption.text.split('(')[0].trim() : 'Central Warehouse';
    
    // Update modal title
    const titleSpan = document.getElementById('indentTargetConcernName');
    if (titleSpan) {
        titleSpan.textContent = concernName;
    }

    // Refresh all concern stock badges in the table
    document.querySelectorAll('.concern-stock-badge').forEach(badge => {
        const variantId = badge.getAttribute('data-variant-id');
        const stock = getConcernStock(concernId, variantId);
        badge.textContent = stock.toFixed(2) + ' pcs';
        if (stock > 0) {
            badge.className = 'badge bg-light text-dark border px-2 py-1 concern-stock-badge';
        } else {
            badge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 concern-stock-badge';
        }
    });
}

// Handle Variant Selection from Dropdown
function handleVariantPicked(select) {
    const variantId = select.value;
    if (!variantId) return;

    const opt = select.options[select.selectedIndex];
    const name = opt.getAttribute('data-name');
    const sku = opt.getAttribute('data-sku');
    const size = opt.getAttribute('data-size') || '';

    addIndentItemRow(variantId, name, sku, size);
    select.value = '';
}

// Add row to Indent Items Table
function addIndentItemRow(variantId, name, sku, size, defaultQty = 10) {
    const tbody = document.getElementById('indentItemsTableBody');
    const notice = document.getElementById('noIndentItemsNotice');

    // Check if variant already exists in table
    const existingRow = tbody.querySelector(`tr[data-variant-id="${variantId}"]`);
    if (existingRow) {
        const qtyInput = existingRow.querySelector('input[type="number"]');
        if (qtyInput) {
            qtyInput.value = parseFloat(qtyInput.value || 0) + 1;
            qtyInput.focus();
        }
        return;
    }

    const destConcernSelect = document.getElementById('modalDestConcern');
    const concernId = destConcernSelect ? destConcernSelect.value : 0;
    const stock = getConcernStock(concernId, variantId);
    const stockBadgeClass = stock > 0 ? 'bg-light text-dark border' : 'bg-danger-subtle text-danger border border-danger-subtle';

    const row = document.createElement('tr');
    row.setAttribute('data-variant-id', variantId);
    row.innerHTML = `
        <td>
            <div class="fw-bold text-dark text-uppercase">${escapeHtml(name)}</div>
            <small class="text-muted font-monospace">${escapeHtml(sku)}${size ? ' &bull; ' + escapeHtml(size) : ''}</small>
            <input type="hidden" name="items[${variantId}][variant_id]" value="${variantId}">
        </td>
        <td class="text-center">
            <span class="badge ${stockBadgeClass} px-2 py-1 concern-stock-badge" data-variant-id="${variantId}">
                ${stock.toFixed(2)} pcs
            </span>
        </td>
        <td class="text-center">
            <input type="number" name="items[${variantId}][requested_qty]" class="form-control form-control-sm text-center mx-auto" style="max-width: 90px;" min="1" step="1" value="${defaultQty}" required>
        </td>
        <td class="text-end">
            <button type="button" class="btn btn-sm btn-link text-danger p-0 border-0" onclick="removeIndentRow(this)" title="Delete Row">
                <i class="bi bi-trash fs-5"></i>
            </button>
        </td>
    `;
    tbody.appendChild(row);
    if (notice) notice.style.display = 'none';
}

// Remove item row
function removeIndentRow(btn) {
    const row = btn.closest('tr');
    if (row) {
        row.remove();
    }
    const tbody = document.getElementById('indentItemsTableBody');
    const notice = document.getElementById('noIndentItemsNotice');
    if (tbody && tbody.children.length === 0 && notice) {
        notice.style.display = 'block';
    }
}

// Quick Receive handler
function openQuickReceive(id, reqNo, transitQty) {
    document.getElementById('receiveRequestId').value = id;
    document.getElementById('receiveReqNoText').textContent = reqNo;
    document.getElementById('receiveTransitQtyText').textContent = parseFloat(transitQty).toFixed(2);
    document.getElementById('receiveVerifiedQtyInput').value = parseFloat(transitQty);
    new bootstrap.Modal(document.getElementById('modalQuickReceive')).show();
}

// Utility: HTML escape
function escapeHtml(str) {
    return (str || '').replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const destSelect = document.getElementById('modalDestConcern');
    if (destSelect) {
        handleDestinationConcernChange(destSelect);
    }
});
</script>
