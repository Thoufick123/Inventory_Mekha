<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-plus-circle text-warning me-2"></i>Initiate Return / Exchange RMA</h4>
            <p class="text-secondary small mb-0">Record customer returned garments, trigger double-entry restock / rework movements, and dispatch exchange items.</p>
        </div>
        <a href="<?= \App\Core\Controller::url('/production/returns') ?>" class="btn btn-outline-secondary shadow-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to RMA Registry
        </a>
    </div>

    <form method="POST" action="<?= \App\Core\Controller::url('/production/returns/store') ?>" id="rmaForm" class="needs-validation">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\CSRF::token()) ?>">
        <input type="hidden" name="in_items" id="inItemsJson" value="[]">
        <input type="hidden" name="out_items" id="outItemsJson" value="[]">

        <div class="row g-4">
            <!-- Left Panel: Transaction Settings & Customer -->
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-gear me-2 text-primary"></i>RMA Configuration</h6>
                    </div>
                    <div class="card-body">
                        <!-- RMA Type Tabs -->
                        <label class="form-label small fw-bold text-muted text-uppercase mb-2">Transaction Type</label>
                        <div class="btn-group w-100 mb-3" role="group">
                            <input type="radio" class="btn-check" name="type" id="typeReturn" value="RETURN" checked onchange="handleTypeChange()">
                            <label class="btn btn-outline-warning fw-semibold" for="typeReturn"><i class="bi bi-box-arrow-in-left me-1"></i> Refund</label>

                            <input type="radio" class="btn-check" name="type" id="typeExchange" value="EXCHANGE" onchange="handleTypeChange()">
                            <label class="btn btn-outline-primary fw-semibold" for="typeExchange"><i class="bi bi-arrow-left-right me-1"></i> Exchange</label>

                            <input type="radio" class="btn-check" name="type" id="typeCredit" value="CREDIT_NOTE" onchange="handleTypeChange()">
                            <label class="btn btn-outline-dark fw-semibold" for="typeCredit"><i class="bi bi-receipt me-1"></i> Credit Note</label>
                        </div>

                        <!-- Bill / Invoice Quick Lookup -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Sales Invoice Lookup (Optional)</label>
                            <div class="input-group input-group-sm">
                                <input type="text" id="lookupInvoiceNo" class="form-control font-monospace" placeholder="e.g. INV-2026-0001">
                                <button type="button" class="btn btn-primary" onclick="lookupInvoice()"><i class="bi bi-search me-1"></i> Load</button>
                            </div>
                            <input type="hidden" name="sales_invoice_id" id="salesInvoiceId">
                            <small class="text-muted d-block mt-1" id="lookupInvoiceStatus">Fetch purchased items directly from invoice.</small>
                        </div>

                        <!-- Customer Selection -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Customer</label>
                            <select name="customer_id" id="customerId" class="form-select form-select-sm">
                                <option value="">-- Retail Walk-in Customer --</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['phone'] ?? 'No phone') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted text-uppercase">Return Date</label>
                                <input type="date" name="return_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted text-uppercase">Settlement Mode</label>
                                <select name="settlement_mode" class="form-select form-select-sm">
                                    <option value="Credit Note">Credit Note</option>
                                    <option value="Cash">Cash Refund</option>
                                    <option value="Bank Transfer">Bank Transfer / UPI</option>
                                    <option value="Exchange Adjustment">Exchange Adjustment</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Settlement Reference</label>
                            <input type="text" name="settlement_reference" class="form-control form-control-sm font-monospace" placeholder="Receipt / UTR / Credit Note Ref...">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Return Reason</label>
                            <textarea name="reason" rows="2" class="form-control form-control-sm" placeholder="Defective stitching, wrong size, customer preference..." required></textarea>
                        </div>

                        <div class="mb-0">
                            <label class="form-label small fw-bold text-muted text-uppercase">Photo URL (Cloudinary / Inspection)</label>
                            <input type="url" name="photo_url" class="form-control form-control-sm" placeholder="https://res.cloudinary.com/...">
                        </div>
                    </div>
                </div>

                <!-- Financial Balance Summary Card -->
                <div class="card border-0 shadow-sm rounded-3 bg-light">
                    <div class="card-body p-3">
                        <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-calculator me-2 text-success"></i>Settlement Calculation</h6>
                        <div class="d-flex justify-content-between mb-2 small">
                            <span class="text-secondary">Total Returned (IN):</span>
                            <span class="fw-bold text-danger" id="summaryTotalIn">₹0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 small" id="summaryOutRow" style="display: none !important;">
                            <span class="text-secondary">Total Exchanged (OUT):</span>
                            <span class="fw-bold text-success" id="summaryTotalOut">₹0.00</span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark" id="summaryNetLabel">Net Refund Due:</span>
                            <span class="fs-5 fw-bold text-primary" id="summaryNetAmount">₹0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Line Items Management -->
            <div class="col-12 col-lg-8">
                <!-- Section 1: Returned Items (Direction IN) -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-danger"><i class="bi bi-box-arrow-in-left me-2"></i>1. Returned Items (Restock / Rework / Scrap)</h6>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="addInRow()">
                            <i class="bi bi-plus-lg me-1"></i> Add Returned Item
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="tableInItems">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th style="width: 38%;">Variant Style *</th>
                                    <th style="width: 14%;">Qty *</th>
                                    <th style="width: 16%;">Price (₹)</th>
                                    <th style="width: 20%;">Action *</th>
                                    <th style="width: 12%;" class="text-end">Remove</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyInItems">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Section 2: Exchange Items (Direction OUT) - Visible only for EXCHANGE -->
                <div class="card border-0 shadow-sm rounded-3 mb-4" id="cardExchangeItems" style="display: none;">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-success"><i class="bi bi-box-arrow-up-right me-2"></i>2. Exchange Replacement Items (Deduct Live Stock)</h6>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="addOutRow()">
                            <i class="bi bi-plus-lg me-1"></i> Add Replacement Item
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="tableOutItems">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th style="width: 45%;">Exchange Variant Style *</th>
                                    <th style="width: 18%;">Qty *</th>
                                    <th style="width: 25%;">Selling Price (₹)</th>
                                    <th style="width: 12%;" class="text-end">Remove</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyOutItems">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Submit Action Footer -->
                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= \App\Core\Controller::url('/production/returns') ?>" class="btn btn-light border px-4">Cancel</a>
                    <button type="submit" class="btn btn-warning px-5 fw-bold shadow-sm" id="btnSubmitRma">
                        <i class="bi bi-check2-circle me-1"></i> Process RMA & Update Stocks
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Variant Options Template for JS -->
<template id="variantOptionsTemplate">
    <option value="">-- Choose Variant Style --</option>
    <?php foreach ($variants as $v): ?>
        <option value="<?= $v['id'] ?>" data-price="<?= $v['price'] ?>" data-sku="<?= htmlspecialchars($v['sku_code']) ?>">
            <?= htmlspecialchars($v['name']) ?> (<?= htmlspecialchars($v['sku_code']) ?> - <?= htmlspecialchars($v['size'] ?? 'Standard') ?>) - ₹<?= number_format((float)$v['price'], 2) ?>
        </option>
    <?php endforeach; ?>
</template>

<script>
const variantOptionsHtml = document.getElementById('variantOptionsTemplate').innerHTML;

function handleTypeChange() {
    const isExchange = document.getElementById('typeExchange').checked;
    const cardExchange = document.getElementById('cardExchangeItems');
    const summaryOutRow = document.getElementById('summaryOutRow');

    if (isExchange) {
        cardExchange.style.display = 'block';
        summaryOutRow.style.removeProperty('display');
        if (document.querySelectorAll('#tbodyOutItems tr').length === 0) {
            addOutRow();
        }
    } else {
        cardExchange.style.display = 'none';
        summaryOutRow.style.setProperty('display', 'none', 'important');
    }
    recalculateTotals();
}

function addInRow(variantId = '', qty = 1, price = 0, action = 'RESTOCK') {
    const tbody = document.getElementById('tbodyInItems');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select class="form-select form-select-sm in-variant-select" required onchange="handleInVariantChange(this)">
                ${variantOptionsHtml}
            </select>
        </td>
        <td>
            <input type="number" step="0.01" min="0.01" class="form-control form-control-sm in-qty" value="${qty}" required oninput="recalculateTotals()">
        </td>
        <td>
            <input type="number" step="0.01" min="0" class="form-control form-control-sm in-price" value="${price}" oninput="recalculateTotals()">
        </td>
        <td>
            <select class="form-select form-select-sm in-action">
                <option value="RESTOCK" ${action === 'RESTOCK' ? 'selected' : ''}>Restock (Store)</option>
                <option value="REWORK" ${action === 'REWORK' ? 'selected' : ''}>Rework Unit</option>
                <option value="SCRAP" ${action === 'SCRAP' ? 'selected' : ''}>Scrap / Damaged</option>
            </select>
        </td>
        <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); recalculateTotals();">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);

    if (variantId) {
        tr.querySelector('.in-variant-select').value = variantId;
    }
    recalculateTotals();
}

function addOutRow(variantId = '', qty = 1, price = 0) {
    const tbody = document.getElementById('tbodyOutItems');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select class="form-select form-select-sm out-variant-select" required onchange="handleOutVariantChange(this)">
                ${variantOptionsHtml}
            </select>
        </td>
        <td>
            <input type="number" step="0.01" min="0.01" class="form-control form-control-sm out-qty" value="${qty}" required oninput="recalculateTotals()">
        </td>
        <td>
            <input type="number" step="0.01" min="0" class="form-control form-control-sm out-price" value="${price}" oninput="recalculateTotals()">
        </td>
        <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); recalculateTotals();">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);

    if (variantId) {
        tr.querySelector('.out-variant-select').value = variantId;
    }
    recalculateTotals();
}

function handleInVariantChange(select) {
    const opt = select.options[select.selectedIndex];
    const price = opt ? (opt.dataset.price || 0) : 0;
    const tr = select.closest('tr');
    tr.querySelector('.in-price').value = price;
    recalculateTotals();
}

function handleOutVariantChange(select) {
    const opt = select.options[select.selectedIndex];
    const price = opt ? (opt.dataset.price || 0) : 0;
    const tr = select.closest('tr');
    tr.querySelector('.out-price').value = price;
    recalculateTotals();
}

function recalculateTotals() {
    let totIn = 0;
    const inItems = [];
    document.querySelectorAll('#tbodyInItems tr').forEach(tr => {
        const vId = tr.querySelector('.in-variant-select').value;
        const q = parseFloat(tr.querySelector('.in-qty').value) || 0;
        const p = parseFloat(tr.querySelector('.in-price').value) || 0;
        const a = tr.querySelector('.in-action').value;
        if (vId && q > 0) {
            inItems.push({ variant_id: vId, qty: q, unit_price: p, action: a });
            totIn += (q * p);
        }
    });

    let totOut = 0;
    const outItems = [];
    const isExchange = document.getElementById('typeExchange').checked;
    if (isExchange) {
        document.querySelectorAll('#tbodyOutItems tr').forEach(tr => {
            const vId = tr.querySelector('.out-variant-select').value;
            const q = parseFloat(tr.querySelector('.out-qty').value) || 0;
            const p = parseFloat(tr.querySelector('.out-price').value) || 0;
            if (vId && q > 0) {
                outItems.push({ variant_id: vId, qty: q, unit_price: p });
                totOut += (q * p);
            }
        });
    }

    document.getElementById('inItemsJson').value = JSON.stringify(inItems);
    document.getElementById('outItemsJson').value = JSON.stringify(outItems);

    document.getElementById('summaryTotalIn').textContent = '₹' + totIn.toFixed(2);
    document.getElementById('summaryTotalOut').textContent = '₹' + totOut.toFixed(2);

    const diff = totOut - totIn;
    const label = document.getElementById('summaryNetLabel');
    const netEl = document.getElementById('summaryNetAmount');

    if (diff < 0) {
        label.textContent = 'Refund Due to Customer:';
        netEl.textContent = '₹' + Math.abs(diff).toFixed(2);
        netEl.className = 'fs-5 fw-bold text-danger';
    } else if (diff > 0) {
        label.textContent = 'Extra Due from Customer:';
        netEl.textContent = '₹' + diff.toFixed(2);
        netEl.className = 'fs-5 fw-bold text-success';
    } else {
        label.textContent = 'Net Balance:';
        netEl.textContent = '₹0.00 (Even)';
        netEl.className = 'fs-5 fw-bold text-muted';
    }
}

function lookupInvoice() {
    const invNo = document.getElementById('lookupInvoiceNo').value.trim();
    if (!invNo) return;

    const statusEl = document.getElementById('lookupInvoiceStatus');
    statusEl.innerHTML = '<span class="text-primary"><i class="bi bi-arrow-repeat spin"></i> Searching invoice...</span>';

    fetch(`<?= \App\Core\Controller::url('/api/returns/lookup-bill') ?>?invoice_no=${encodeURIComponent(invNo)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                statusEl.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle me-1"></i>${data.message}</span>`;
                return;
            }

            document.getElementById('salesInvoiceId').value = data.invoice.id;
            if (data.invoice.customer_id) {
                document.getElementById('customerId').value = data.invoice.customer_id;
            }

            const tbody = document.getElementById('tbodyInItems');
            tbody.innerHTML = '';
            (data.items || []).forEach(item => {
                addInRow(item.variant_id, item.qty, item.unit_price, 'RESTOCK');
            });

            statusEl.innerHTML = `<span class="text-success"><i class="bi bi-check-circle me-1"></i> Loaded ${data.items.length} item(s) from invoice ${data.invoice.invoice_no}.</span>`;
        })
        .catch(err => {
            statusEl.innerHTML = `<span class="text-danger">Failed to lookup invoice: ${err.message}</span>`;
        });
}

// Initial state
document.addEventListener('DOMContentLoaded', () => {
    addInRow();
});
</script>
