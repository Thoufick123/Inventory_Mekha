<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Inward Stock Consignments</h4>
            <p class="text-secondary small mb-0">Record incoming raw materials or vendor receipts. Immediately credits live inventory balance via <code>StockService::move()</code>.</p>
        </div>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddInward">
            <i class="bi bi-box-arrow-in-down me-1"></i> Receive Inward Consignment
        </button>
    </div>

    <!-- Export Intelligence Toolbar -->
    <?php
    $exportTitle = 'Export Inward Stock Receipts (Excel & PDF)';
    $exportSubtitle = 'Download warehouse arrivals, supplier delivery challans, item quantities & receiver audit trails';
    $exportUrl = \App\Core\Controller::url('/m/inward/export');
    $prefix = 'inw_exp';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <!-- Incoming Inter-Unit Transfers Alert & Action Card -->
    <?php if (!empty($pendingTransfers)): ?>
        <div class="card border-0 shadow-sm rounded-3 mb-4" style="background: #f0f7ff; border-left: 5px solid #2563eb !important;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold text-primary mb-0">
                        <i class="bi bi-box-arrow-in-down-right me-2 fs-5"></i> Incoming Transfers from Other Concerns / Production Units
                    </h6>
                    <span class="badge bg-primary px-2.5 py-1.5 fs-7"><?= count($pendingTransfers) ?> Consignment(s) Ready to Inward</span>
                </div>
                <p class="small text-secondary mb-3">
                    Outward products sent specifically to this concern from sister production units. Inwarding creates/maintains the product under this concern only, and credits live stock with strict tenant isolation.
                </p>
                <div class="table-responsive bg-white rounded-3 border">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light small text-uppercase">
                            <tr>
                                <th class="ps-3">Gate Pass No</th>
                                <th>Sent From Unit</th>
                                <th>Product Variant</th>
                                <th>Quantity Sent</th>
                                <th>Dispatch Date</th>
                                <th class="text-end pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingTransfers as $pt): ?>
                                <tr>
                                    <td class="ps-3"><span class="badge bg-dark font-monospace"><?= htmlspecialchars($pt['outward_no']) ?></span></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($pt['from_concern_name']) ?></div>
                                        <small class="text-muted font-monospace"><?= htmlspecialchars($pt['from_concern_code']) ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($pt['variant_name']) ?></div>
                                        <span class="badge bg-light text-muted border"><?= htmlspecialchars($pt['sku_code']) ?></span>
                                        <?php if (!empty($pt['size'])): ?>
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($pt['size']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-6 px-2 py-1">
                                            +<?= number_format($pt['transfer_qty'], 2) ?> <?= htmlspecialchars($pt['weight_unit'] ?: 'units') ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted font-monospace"><?= date('d M Y', strtotime($pt['outward_date'])) ?></td>
                                    <td class="text-end pe-3">
                                        <button type="button" class="btn btn-sm btn-primary px-3 shadow-sm" onclick='receiveTransferModal(<?= json_encode($pt, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                            <i class="bi bi-box-arrow-in-down me-1"></i> Receive Inward
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-box-arrow-in-down me-2 text-primary"></i> Inward Consignments Log</h6>
            <span class="badge bg-light text-dark border"><?= count($inwards) ?> Records</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead>
                    <tr>
                        <th>Inward No & GRN</th>
                        <?php if ($isSuperAdmin && empty($selectedCid)): ?>
                            <th>Concern</th>
                        <?php endif; ?>
                        <th>Source / Unit</th>
                        <th>Inward Date</th>
                        <th>Warehouse Bay</th>
                        <th>Items Received</th>
                        <th>Accepted / Rejected</th>
                        <th>Received By</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inwards)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">No inward consignments received yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($inwards as $inw): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-dark font-monospace"><?= htmlspecialchars($inw['inward_no']) ?></span>
                                    <?php if (!empty($inw['grn_number'])): ?>
                                        <div class="mt-1"><span class="badge bg-info bg-opacity-10 text-primary border border-info border-opacity-25 font-monospace"><i class="bi bi-receipt me-1"></i><?= htmlspecialchars($inw['grn_number']) ?></span></div>
                                    <?php endif; ?>
                                    <?php if (!empty($inw['vehicle_number'])): ?>
                                        <small class="text-muted d-block mt-0.5"><i class="bi bi-truck me-1"></i><?= htmlspecialchars($inw['vehicle_number']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <?php if ($isSuperAdmin && empty($selectedCid)): ?>
                                    <td>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
                                            <?= htmlspecialchars($inw['concern_name'] ?? 'Concern #' . $inw['concern_id']) ?>
                                        </span>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <?php if (($inw['inward_type'] ?? '') === 'transfer' || !empty($inw['from_concern_id'])): ?>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 mb-1 d-inline-block">
                                            <i class="bi bi-building me-1"></i> Unit Transfer
                                        </span>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($inw['from_concern_name'] ?? 'Sister Unit') ?></div>
                                        <small class="text-muted font-monospace">Pass #<?= htmlspecialchars($inw['outward_no'] ?? '') ?></small>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 mb-1 d-inline-block">
                                            <i class="bi bi-cart me-1"></i> Supplier / Purchase
                                        </span>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($inw['supplier_name'] ?? 'Direct Receipt') ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($inw['remarks'] ?: '-') ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($inw['production_unit_name'])): ?>
                                        <div class="small text-muted mt-1"><i class="bi bi-gear-wide-connected me-1"></i><?= htmlspecialchars($inw['production_unit_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d M Y', strtotime($inw['inward_date'])) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($inw['location']) ?></span></td>
                                <td><?= (int)$inw['items_count'] ?> Line Items</td>
                                <td>
                                    <?php 
                                        $accQty = (float)($inw['total_accepted_qty'] ?? $inw['total_qty']);
                                        $rejQty = (float)($inw['total_rejected_qty'] ?? 0);
                                    ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-6 px-2 py-1">
                                        +<?= number_format($accQty, 2) ?>
                                    </span>
                                    <?php if ($rejQty > 0): ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 ms-1">
                                            <?= number_format($rejQty, 2) ?> Rej
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($inw['user_name'] ?? 'Staff') ?></td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-info" onclick='openViewInwardModal(<?= json_encode($inw, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Inward Consignment">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick='openEditInwardModal(<?= json_encode($inw, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Inward Remarks">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="<?= \App\Core\Controller::url('/m/inward/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete inward consignment <?= htmlspecialchars($inw['inward_no']) ?>? This will reverse stock additions.');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= $inw['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Inward">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Add Inward -->
<div class="modal fade" id="modalAddInward" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/m/inward/store') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-in-down me-1 text-primary"></i> Receive Stock Inward</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if ($isSuperAdmin && empty($selectedCid)): ?>
                    <div class="mb-3 p-2 bg-light border rounded">
                        <label class="form-label small fw-bold text-primary text-uppercase mb-1">Inward To Concern *</label>
                        <select name="concern_id" id="inward_concern_id" class="form-select form-select-sm" required>
                            <option value="">-- Choose Receiving Concern --</option>
                            <?php foreach ($allConcerns as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- Inward Source Selector -->
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary text-uppercase mb-1">Inward Source *</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="inward_type" id="it_purchase" value="purchase" checked onchange="toggleInwardSourceType()">
                        <label class="btn btn-outline-primary btn-sm" for="it_purchase"><i class="bi bi-cart me-1"></i> Supplier / Direct</label>

                        <input type="radio" class="btn-check" name="inward_type" id="it_transfer" value="transfer" onchange="toggleInwardSourceType()">
                        <label class="btn btn-outline-primary btn-sm" for="it_transfer">
                            <i class="bi bi-building me-1"></i> Inter-Unit Transfer 
                            <?php if (!empty($pendingTransfers)): ?>
                                <span class="badge bg-danger ms-1"><?= count($pendingTransfers) ?></span>
                            <?php endif; ?>
                        </label>
                    </div>
                </div>

                <!-- Inter-Unit Transfer Selector (shown when transfer selected) -->
                <div class="mb-3 d-none" id="div_transfer_source">
                    <label class="form-label small fw-semibold text-primary"><i class="bi bi-box-arrow-in-down-right me-1"></i> Select Incoming Outward Consignment *</label>
                    <select name="outward_id" id="inward_outward_id" class="form-select" onchange="onSelectTransferOutward(this)">
                        <option value="">-- Choose Outward Consignment --</option>
                        <?php foreach ($pendingTransfers as $pt): ?>
                            <option value="<?= $pt['id'] ?>"
                                    data-from="<?= htmlspecialchars($pt['from_concern_name']) ?> (<?= htmlspecialchars($pt['from_concern_code']) ?>)"
                                    data-varid="<?= $pt['variant_id'] ?>"
                                    data-varname="<?= htmlspecialchars($pt['variant_name']) ?>"
                                    data-varsku="<?= htmlspecialchars($pt['sku_code']) ?>"
                                    data-varsize="<?= htmlspecialchars($pt['size'] ?: 'Standard') ?>"
                                    data-qty="<?= $pt['transfer_qty'] ?>"
                                    data-outno="<?= htmlspecialchars($pt['outward_no']) ?>"
                                    data-date="<?= $pt['outward_date'] ?>">
                                <?= htmlspecialchars($pt['outward_no']) ?> &bull; From <?= htmlspecialchars($pt['from_concern_name']) ?> &bull; <?= htmlspecialchars($pt['variant_name']) ?> (<?= number_format($pt['transfer_qty'], 2) ?> units)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div id="transfer_details_card" class="p-2.5 mt-2 rounded bg-light border d-none small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Source Unit:</span>
                            <strong class="text-dark" id="td_from">-</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Product / Variant:</span>
                            <strong class="text-primary" id="td_product">-</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Transfer Quantity:</span>
                            <strong class="text-success fs-6 font-monospace" id="td_qty">-</strong>
                        </div>
                        <small class="text-muted d-block mt-2 border-top pt-1">
                            <i class="bi bi-shield-check text-success me-1"></i> This product will be maintained strictly under this concern and live stock credited.
                        </small>
                    </div>
                </div>

                <!-- Supplier and Variant Selection (shown for standard purchase) -->
                <div id="div_supplier_source">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Supplier</label>
                        <select name="supplier_id" id="inward_supplier_id" class="form-select">
                            <option value="">Direct Internal Consignment (No Supplier)</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Product Variant *</label>
                        <select name="variant_id" id="inward_variant_id" class="form-select" required>
                            <option value="">-- Choose Variant to Inward --</option>
                            <?php foreach ($variants as $v): ?>
                                <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['sku_code']) ?> &bull; <?= htmlspecialchars($v['name']) ?> (<?= htmlspecialchars($v['size'] ?: 'Standard') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <?php if (!empty($productionUnits)): ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Target Production Unit / Plant</label>
                        <select name="production_unit_id" id="inward_production_unit_id" class="form-select">
                            <option value="">-- Main Facility / General --</option>
                            <?php foreach ($productionUnits as $pu): ?>
                                <option value="<?= $pu['id'] ?>"><?= htmlspecialchars($pu['name']) ?> (<?= htmlspecialchars($pu['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Total Received Qty *</label>
                        <input type="number" step="0.01" name="qty" id="inward_qty" class="form-control" placeholder="0.00" required oninput="calcAcceptedInward()">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-success">Accepted Qty *</label>
                        <input type="number" step="0.01" name="accepted_qty" id="inward_accepted_qty" class="form-control border-success" placeholder="0.00" oninput="calcRejectedInward()">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-danger">Rejected / Damaged</label>
                        <input type="number" step="0.01" name="rejected_qty" id="inward_rejected_qty" class="form-control border-danger" placeholder="0.00" readonly>
                    </div>
                </div>
                <div class="mb-3 d-none" id="div_rejection_reason">
                    <label class="form-label small fw-semibold text-danger">Reason for Rejection</label>
                    <input type="text" name="rejection_reason" id="inward_rejection_reason" class="form-control form-control-sm" placeholder="e.g. Broken seams, torn packaging, wrong shade">
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Unit Cost Rate (₹)</label>
                        <input type="number" step="0.01" name="unit_cost" id="inward_unit_cost" class="form-control" placeholder="0.00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Inward Date</label>
                        <input type="date" name="inward_date" id="inward_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Vehicle / Truck No</label>
                        <input type="text" name="vehicle_number" id="inward_vehicle_number" class="form-control" placeholder="TN-01-AB-1234">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Location / Godown Bay</label>
                        <input type="text" name="location" id="inward_location" class="form-control" value="Main Store">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Remarks / Gate Entry Ref</label>
                    <textarea name="remarks" id="inward_remarks" class="form-control" rows="2" placeholder="Delivery challan or truck ref"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Process Inward & Credit Stock</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Inward -->
<div class="modal fade" id="modalEditInward" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/m/inward/update') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" id="edit_inward_id" value="">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-1 text-primary"></i> Edit Stock Inward Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Inward No</label>
                    <input type="text" id="edit_inward_no" class="form-control font-monospace" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Inward Date *</label>
                    <input type="date" name="inward_date" id="edit_inward_date" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Remarks / Gate Entry Notes</label>
                    <textarea name="remarks" id="edit_inward_remarks" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Inward</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal View Inward -->
<div class="modal fade" id="modalViewInward" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom px-4 py-3 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span style="width: 36px; height: 36px; background: #e0f2fe; color: #0284c7; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                        <i class="bi bi-box-arrow-in-down"></i>
                    </span>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0">Stock Inward Consignment</h6>
                        <small class="badge bg-dark font-monospace" id="view_inward_no"></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center p-3 rounded-3 mb-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                    <div>
                        <span class="text-secondary small d-block">Total Units Credited</span>
                        <span class="fw-bold fs-5 text-success font-monospace" id="view_inward_qty">+0.00</span>
                    </div>
                    <div class="text-end">
                        <span class="text-secondary small d-block">Items Included</span>
                        <span class="badge bg-primary bg-opacity-10 text-primary border fs-6" id="view_inward_items_count">0 Lines</span>
                    </div>
                </div>

                <ul class="list-group list-group-flush small">
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-building text-primary me-2"></i>Supplier:</span>
                        <strong class="text-dark" id="view_inward_supplier">-</strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-geo-alt text-primary me-2"></i>Storage Location:</span>
                        <span class="badge bg-light text-dark border" id="view_inward_loc">Main Store</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-calendar-check text-primary me-2"></i>Inward Date:</span>
                        <span class="font-monospace text-dark" id="view_inward_date">-</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-person text-primary me-2"></i>Received By:</span>
                        <span class="text-dark" id="view_inward_user">Staff</span>
                    </li>
                    <li class="list-group-item px-0">
                        <span class="text-muted d-block mb-1"><i class="bi bi-card-text text-primary me-2"></i>Remarks / Gate Entry Notes:</span>
                        <div class="text-dark p-2 bg-light rounded" id="view_inward_remarks">None</div>
                    </li>
                </ul>
            </div>
            <div class="modal-footer border-top px-4 py-2 bg-light">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function toggleInwardSourceType() {
    const isTransfer = document.getElementById('it_transfer').checked;
    const transferDiv = document.getElementById('div_transfer_source');
    const transferSelect = document.getElementById('inward_outward_id');
    const supplierDiv = document.getElementById('div_supplier_source');
    const variantSelect = document.getElementById('inward_variant_id');

    if (isTransfer) {
        supplierDiv.classList.add('d-none');
        variantSelect.removeAttribute('required');
        transferDiv.classList.remove('d-none');
        transferSelect.setAttribute('required', 'required');
    } else {
        transferDiv.classList.add('d-none');
        transferSelect.removeAttribute('required');
        supplierDiv.classList.remove('d-none');
        variantSelect.setAttribute('required', 'required');
        document.getElementById('transfer_details_card').classList.add('d-none');
    }
}

function onSelectTransferOutward(selectEl) {
    const opt = selectEl.options[selectEl.selectedIndex];
    const detailsCard = document.getElementById('transfer_details_card');
    if (!opt || !opt.value) {
        detailsCard.classList.add('d-none');
        return;
    }

    const from = opt.getAttribute('data-from');
    const varName = opt.getAttribute('data-varname');
    const varSku = opt.getAttribute('data-varsku');
    const varSize = opt.getAttribute('data-varsize');
    const qty = opt.getAttribute('data-qty');
    const outNo = opt.getAttribute('data-outno');
    const date = opt.getAttribute('data-date');

    document.getElementById('td_from').innerText = from || '-';
    document.getElementById('td_product').innerText = varName + ' [' + varSku + '] (' + varSize + ')';
    document.getElementById('td_qty').innerText = parseFloat(qty || 0).toFixed(2) + ' units';
    detailsCard.classList.remove('d-none');

    document.getElementById('inward_qty').value = parseFloat(qty || 0).toFixed(2);
    document.getElementById('inward_remarks').value = 'Inter-unit transfer from ' + from + ' (Pass #' + outNo + ')';
}

function receiveTransferModal(pt) {
    if (!pt) return;
    const itTransfer = document.getElementById('it_transfer');
    if (itTransfer) {
        itTransfer.checked = true;
        toggleInwardSourceType();
    }

    const outSelect = document.getElementById('inward_outward_id');
    if (outSelect) {
        outSelect.value = pt.id;
        onSelectTransferOutward(outSelect);
    }

    const modalEl = document.getElementById('modalAddInward');
    if (modalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
}

function openViewInwardModal(inw) {
    if (!inw) return;
    const setTxt = (id, val) => { const el = document.getElementById(id); if (el) el.innerText = val; };
    setTxt('view_inward_no', inw.inward_no || '');
    const supplierText = (inw.inward_type === 'transfer' || inw.from_concern_id)
        ? 'Unit Transfer: ' + (inw.from_concern_name || 'Sister Unit') + (inw.outward_no ? ' (Pass #' + inw.outward_no + ')' : '')
        : (inw.supplier_name || 'Direct Receipt');
    setTxt('view_inward_supplier', supplierText);
    setTxt('view_inward_loc', inw.location || 'Main Store');
    setTxt('view_inward_date', inw.inward_date ? inw.inward_date.substring(0, 10) : '-');
    setTxt('view_inward_user', inw.user_name || 'Staff');
    setTxt('view_inward_qty', '+' + parseFloat(inw.total_qty || 0).toLocaleString('en-IN', {minimumFractionDigits: 2}));
    setTxt('view_inward_items_count', (inw.items_count || 1) + ' Items');
    setTxt('view_inward_remarks', inw.remarks || 'None specified');

    const modalEl = document.getElementById('modalViewInward');
    if (modalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
}

function openEditInwardModal(inw) {
    if (!inw) return;
    const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
    setVal('edit_inward_id', inw.id || '');
    setVal('edit_inward_no', inw.inward_no || '');
    setVal('edit_inward_date', inw.inward_date ? inw.inward_date.substring(0, 10) : '');
    setVal('edit_inward_remarks', inw.remarks || '');

    const modalEl = document.getElementById('modalEditInward');
    if (modalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
}

function calcAcceptedInward() {
    const total = parseFloat(document.getElementById('inward_qty').value) || 0;
    const acceptedInput = document.getElementById('inward_accepted_qty');
    if (!acceptedInput.value || parseFloat(acceptedInput.value) > total) {
        acceptedInput.value = total > 0 ? total : '';
    }
    calcRejectedInward();
}

function calcRejectedInward() {
    const total = parseFloat(document.getElementById('inward_qty').value) || 0;
    const accepted = parseFloat(document.getElementById('inward_accepted_qty').value) || 0;
    const rejected = Math.max(0, total - accepted);
    document.getElementById('inward_rejected_qty').value = rejected.toFixed(2);
    
    const reasonDiv = document.getElementById('div_rejection_reason');
    if (reasonDiv) {
        if (rejected > 0) {
            reasonDiv.classList.remove('d-none');
        } else {
            reasonDiv.classList.add('d-none');
        }
    }
}
</script>
