<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-file-earmark-plus text-primary me-2"></i>New Stock Requisition</h4>
            <p class="text-secondary small mb-0">Initiate internal stock requisition across central warehouse, retail shops, or production units.</p>
        </div>
        <a href="<?= \App\Core\Controller::url('/production/stock-requests') ?>" class="btn btn-outline-secondary shadow-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Requisitions
        </a>
    </div>

    <form method="POST" action="<?= \App\Core\Controller::url('/production/stock-requests/store') ?>" id="stockRequestForm" class="needs-validation">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <input type="hidden" name="items" id="itemsJson" value="[]">

        <div class="row g-4">
            <!-- Left Config Card -->
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-geo-alt me-2 text-primary"></i>Requisition Logistics</h6>
                    </div>
                    <div class="card-body">
                        <!-- Source Location -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Source Location (From)</label>
                            <div class="row g-2">
                                <div class="col-7">
                                    <select name="from_location_type" id="fromLocationType" class="form-select form-select-sm" required>
                                        <option value="WAREHOUSE">Warehouse</option>
                                        <option value="PRODUCTION_UNIT">Production Unit</option>
                                        <option value="SHOP">Retail Shop</option>
                                    </select>
                                </div>
                                <div class="col-5">
                                    <input type="number" name="from_location_id" id="fromLocationId" class="form-control form-control-sm" value="1" min="1" required>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-1">Stock will be debited from this location.</small>
                        </div>

                        <!-- Destination Location -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Destination Location (To)</label>
                            <div class="row g-2">
                                <div class="col-7">
                                    <select name="to_location_type" id="toLocationType" class="form-select form-select-sm" required>
                                        <option value="SHOP">Retail Shop</option>
                                        <option value="WAREHOUSE">Warehouse</option>
                                        <option value="PRODUCTION_UNIT">Production Unit</option>
                                    </select>
                                </div>
                                <div class="col-5">
                                    <input type="number" name="to_location_id" id="toLocationId" class="form-control form-control-sm" value="1" min="1" required>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-1">Stock will be credited here upon receipt.</small>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted text-uppercase">Priority</label>
                                <select name="priority" class="form-select form-select-sm">
                                    <option value="NORMAL">Normal</option>
                                    <option value="HIGH">High Priority</option>
                                    <option value="URGENT">Urgent (Express)</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted text-uppercase">Expected Date</label>
                                <input type="date" name="expected_date" class="form-control form-control-sm" value="<?= date('Y-m-d', strtotime('+3 days')) ?>" required>
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label small fw-bold text-muted text-uppercase">Requisition Notes</label>
                            <textarea name="notes" rows="3" class="form-control form-control-sm" placeholder="Store replenishment, festive demand, custom order..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-3 bg-light p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small fw-bold text-muted text-uppercase">Total Requested Units:</span>
                        <span class="fs-4 fw-bold text-primary" id="totalRequestedUnits">0.00</span>
                    </div>
                </div>
            </div>

            <!-- Right Line Items Card -->
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-boxes me-2 text-primary"></i>Requisition Line Items</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRequestRow()">
                            <i class="bi bi-plus-lg me-1"></i> Add Garment Variant
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="tableRequestItems">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th style="width: 60%;">Variant Style *</th>
                                    <th style="width: 25%;">Requested Quantity *</th>
                                    <th style="width: 15%;" class="text-end">Remove</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyRequestItems">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= \App\Core\Controller::url('/production/stock-requests') ?>" class="btn btn-light border px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm" id="btnSubmitRequisition">
                        <i class="bi bi-send me-1"></i> Submit Requisition
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Variant Options Template for JS -->
<template id="variantOptionsTemplate">
    <option value="">-- Choose Garment Variant --</option>
    <?php foreach ($variants as $v): ?>
        <option value="<?= $v['id'] ?>" data-sku="<?= htmlspecialchars($v['sku_code']) ?>">
            <?= htmlspecialchars($v['name']) ?> (<?= htmlspecialchars($v['sku_code']) ?> - <?= htmlspecialchars($v['size'] ?? 'Standard') ?>)
        </option>
    <?php endforeach; ?>
</template>

<script>
const variantOptionsHtml = document.getElementById('variantOptionsTemplate').innerHTML;

function addRequestRow(variantId = '', qty = 10) {
    const tbody = document.getElementById('tbodyRequestItems');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select class="form-select form-select-sm req-variant-select" required onchange="recalculateTotals()">
                ${variantOptionsHtml}
            </select>
        </td>
        <td>
            <input type="number" step="1" min="1" class="form-control form-control-sm req-qty" value="${qty}" required oninput="recalculateTotals()">
        </td>
        <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); recalculateTotals();">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);

    if (variantId) {
        tr.querySelector('.req-variant-select').value = variantId;
    }
    recalculateTotals();
}

function recalculateTotals() {
    let totQty = 0;
    const items = [];
    document.querySelectorAll('#tbodyRequestItems tr').forEach(tr => {
        const vId = tr.querySelector('.req-variant-select').value;
        const q = parseFloat(tr.querySelector('.req-qty').value) || 0;
        if (vId && q > 0) {
            items.push({ variant_id: vId, requested_qty: q });
            totQty += q;
        }
    });

    document.getElementById('itemsJson').value = JSON.stringify(items);
    document.getElementById('totalRequestedUnits').textContent = totQty.toFixed(2);
}

document.addEventListener('DOMContentLoaded', () => {
    addRequestRow();
});
</script>
