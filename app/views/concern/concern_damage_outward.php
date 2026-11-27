<div class="container-fluid px-0">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-truck text-danger me-2"></i>Damage Outward</h4>
            <p class="text-secondary small mb-0">Scan available damaged stock only and transfer it to another concern without restoring its damaged status.</p>
        </div>
        <a href="<?= \App\Core\Controller::url('/m/concern_damage' . ($isSuperAdmin && $selectedCid ? '?concern_id=' . (int)$selectedCid : '')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Damage List
        </a>
    </div>

    <?php if ($isSuperAdmin): ?>
        <form method="GET" action="<?= \App\Core\Controller::url('/m/concern_damage/outward') ?>" id="damageOutwardSourceForm" class="d-none"></form>
    <?php endif; ?>

    <form method="POST"
          action="<?= \App\Core\Controller::url('/m/concern_damage/outward/store') ?>"
          id="damageOutwardForm"
          data-scan-url="<?= htmlspecialchars(\App\Core\Controller::url('/m/concern_damage/outward/scan'), ENT_QUOTES, 'UTF-8') ?>"
          data-csrf-token="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
          data-concern-id="<?= (int)$selectedCid ?>">
        <?php if (!empty($selectedCid)): ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <?php if ($isSuperAdmin): ?>
                <input type="hidden" name="concern_id" value="<?= (int)$selectedCid ?>">
            <?php endif; ?>
            <input type="hidden" name="scanned_items_json" id="damageOutwardItems">
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <?php if ($isSuperAdmin): ?>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold" for="damageOutwardSource">Source concern *</label>
                            <select class="form-select" name="concern_id" id="damageOutwardSource" form="damageOutwardSourceForm" required>
                                <option value="">Select a concern to load its damaged stock</option>
                                <?php foreach ($allConcerns as $concern): ?>
                                    <option value="<?= (int)$concern['id'] ?>" <?= (int)$selectedCid === (int)$concern['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($concern['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <noscript><button class="btn btn-primary mt-2" type="submit" form="damageOutwardSourceForm">Load concern</button></noscript>
                        </div>
                    <?php endif; ?>
                    <div class="<?= $isSuperAdmin ? 'col-md-6' : 'col-12' ?>">
                        <label class="form-label small fw-semibold" for="damageOutwardDestination">Destination concern *</label>
                        <select class="form-select" name="destination_concern_id" id="damageOutwardDestination" <?= empty($selectedCid) ? 'disabled' : 'required' ?>>
                            <option value=""><?= empty($selectedCid) ? 'Select a source concern first' : 'Select destination concern' ?></option>
                            <?php if (!empty($selectedCid)): ?>
                                <?php foreach ($allConcerns as $concern): ?>
                                    <?php if ((int)$concern['id'] === (int)$selectedCid) continue; ?>
                                    <option value="<?= (int)$concern['id'] ?>"><?= htmlspecialchars($concern['name']) ?> (<?= htmlspecialchars($concern['code']) ?>)</option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

    <?php if (empty($selectedCid)): ?>
        </form>
        <div class="alert alert-info border-0 shadow-sm">Select a source concern before scanning damaged stock.</div>
    <?php else: ?>

            <div class="row g-2 align-items-center mb-2">
                <div class="col-md-7">
                    <div class="input-group">
                        <span class="input-group-text bg-white text-danger"><i class="bi bi-upc-scan"></i></span>
                        <input type="text" id="damageOutwardTag" class="form-control font-monospace" autocomplete="off" placeholder="Scan damaged-stock QR or enter Product Code..." aria-label="Scan damaged stock label">
                        <button type="button" class="btn btn-danger fw-bold" id="damageOutwardScanButton"><i class="bi bi-plus-lg me-1"></i> Scan / Add</button>
                    </div>
                </div>
                <div class="col-md-5">
                    <select id="damageOutwardStockSelect" class="form-select" aria-label="Select available damaged stock">
                        <option value="">-- Select Available Damaged Stock --</option>
                        <?php foreach (($availableDamagedStocks ?? []) as $dmgStock): ?>
                            <option value="<?= (int)$dmgStock['variant_id'] ?>"
                                    data-variant-id="<?= (int)$dmgStock['variant_id'] ?>"
                                    data-product-code="<?= htmlspecialchars((string)($dmgStock['product_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-variant-name="<?= htmlspecialchars((string)($dmgStock['variant_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-size="<?= htmlspecialchars((string)($dmgStock['size'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-available-qty="<?= (float)$dmgStock['available_qty'] ?>">
                                <?= htmlspecialchars((string)($dmgStock['product_code'] ?: ('#' . $dmgStock['variant_id']))) ?> — <?= htmlspecialchars((string)$dmgStock['variant_name']) ?><?= !empty($dmgStock['size']) ? ' (' . htmlspecialchars((string)$dmgStock['size']) . ')' : '' ?> [<?= number_format((float)$dmgStock['available_qty'], 0) ?> pcs]
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div id="damageOutwardFeedback" class="small text-muted mb-3" role="status" aria-live="polite"></div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">Scanned Damaged Stock</h6>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge rounded-pill bg-light text-danger border" id="damageOutwardCount">0 pcs scanned</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="clearDamageOutward">Clear Scans</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product Code / QR</th>
                                <th>Product / Variant</th>
                                <th>Size</th>
                                <th>Available Damaged Qty</th>
                                <th style="width: 130px;">Dispatch Qty</th>
                                <th class="text-end">Remove</th>
                            </tr>
                        </thead>
                        <tbody id="damageOutwardRows">
                            <tr id="emptyDamageOutwardRow">
                                <td colspan="6" class="text-center py-4 text-muted">Scan a damaged-stock label to add it to this dispatch.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top">
                    <div class="row g-2">
                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-semibold" for="damageOutwardBoxes">Boxes / Cartons *</label>
                            <input type="number" min="1" max="99999" step="1" name="total_boxes" id="damageOutwardBoxes" class="form-control" value="1" required>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-semibold" for="damageOutwardVehicle">Vehicle Number *</label>
                            <input type="text" name="vehicle_number" id="damageOutwardVehicle" class="form-control text-uppercase" maxlength="30" placeholder="TN 12 AB 1234" required>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-semibold" for="damageOutwardDriver">Driver Name</label>
                            <input type="text" name="driver_name" id="damageOutwardDriver" class="form-control" maxlength="100">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-semibold" for="damageOutwardContact">Driver Contact</label>
                            <input type="tel" name="driver_contact" id="damageOutwardContact" class="form-control" maxlength="10" inputmode="numeric">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-semibold" for="damageOutwardNotes">Notes</label>
                            <input type="text" name="notes" id="damageOutwardNotes" class="form-control" maxlength="2000" placeholder="Optional notes">
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <small class="text-muted">Damaged quantity is verified again before dispatch; receiving concern will keep it quarantined.</small>
                    <button type="submit" class="btn btn-danger fw-semibold px-4" id="saveDamageOutward" disabled>
                        <i class="bi bi-truck me-1"></i> Dispatch Damage Stock
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <?php if (!empty($outwards)): ?>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white"><h6 class="mb-0 fw-bold">Recent Damage Outward Manifests</h6></div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Manifest</th><th>Destination</th><th class="text-end">Pieces</th><th>Status</th><th>Date</th><th class="text-end">QR</th></tr></thead>
                    <tbody>
                        <?php foreach ($outwards as $outward): ?>
                            <tr>
                                <td class="font-monospace fw-semibold"><?= htmlspecialchars($outward['outward_no']) ?></td>
                                <td><?= htmlspecialchars($outward['destination_name'] ?? 'Unknown concern') ?></td>
                                <td class="text-end"><?= number_format((float)$outward['total_qty'], 0) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars(strtoupper((string)$outward['status'])) ?></span></td>
                                <td><?= htmlspecialchars(date('d M Y', strtotime($outward['created_at']))) ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-dark py-0 px-2" title="View / Print Outward QR"
                                            onclick='openDamageOutwardQrModal(<?= json_encode((string)$outward["outward_no"]) ?>, <?= json_encode((string)($outward["destination_name"] ?? "Unknown concern")) ?>, <?= (int)round((float)$outward["total_qty"]) ?>)'>
                                        <i class="bi bi-qr-code"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    .damage-outward-scanner { border-left: 4px solid #dc3545 !important; background: linear-gradient(110deg, #fff5f6 0%, #fff 75%); }
</style>

<div class="modal fade" id="modalDamageOutwardQr" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-qr-code me-1 text-danger"></i> Outward Master QR</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="damageOutwardQrCanvas" class="d-inline-block p-2 border rounded bg-white mb-2"></div>
                <div id="damageOutwardQrCodeText" class="font-monospace fw-bold text-dark"></div>
                <div id="damageOutwardQrMeta" class="small text-muted mt-1"></div>
            </div>
            <div class="modal-footer py-2 justify-content-between">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="printDamageOutwardQr()"><i class="bi bi-printer me-1"></i>Print QR</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= \App\Core\Controller::url('/assets/js/qrcode.min.js') ?>"></script>
<script>
function openDamageOutwardQrModal(outwardNo, destination, pieces) {
    const box = document.getElementById('damageOutwardQrCanvas');
    if (box && typeof QRCode !== 'undefined') {
        box.innerHTML = '';
        new QRCode(box, {
            text: String(outwardNo || ''),
            width: 150,
            height: 150,
            correctLevel: QRCode.CorrectLevel ? QRCode.CorrectLevel.M : 0
        });
    }
    document.getElementById('damageOutwardQrCodeText').textContent = outwardNo || '';
    document.getElementById('damageOutwardQrMeta').textContent = `${destination || '-'} • ${pieces || 0} pcs`;
    new bootstrap.Modal(document.getElementById('modalDamageOutwardQr')).show();
}

function printDamageOutwardQr() {
    const qrSource = document.querySelector('#damageOutwardQrCanvas img, #damageOutwardQrCanvas canvas');
    const qrDataUrl = qrSource ? (qrSource.tagName === 'IMG' ? qrSource.src : qrSource.toDataURL('image/png')) : '';
    const code = document.getElementById('damageOutwardQrCodeText').textContent || '';
    const meta = document.getElementById('damageOutwardQrMeta').textContent || '';
    const win = window.open('', '_blank', 'width=420,height=480');
    if (!win) return;
    win.document.write(`<!doctype html><html><head><title>Outward QR - ${code}</title>
        <style>body{font-family:Arial,sans-serif;text-align:center;padding:24px;} .box{border:1px solid #cbd5e1;border-radius:10px;padding:18px;display:inline-block;} .code{font-family:monospace;font-size:18px;font-weight:bold;margin-top:10px;} .meta{font-size:12px;color:#475569;margin-top:6px;}</style>
        </head><body><div class="box">
        ${qrDataUrl ? `<img src="${qrDataUrl}" style="width:180px;height:180px;" alt="Outward QR">` : ''}
        <div class="code">${code}</div>
        <div class="meta">${meta}</div>
        </div><script>window.onload=function(){window.print();};<\/script></body></html>`);
    win.document.close();
}

(() => {
    const form = document.getElementById('damageOutwardForm');
    const sourceSelect = document.getElementById('damageOutwardSource');
    if (sourceSelect) sourceSelect.addEventListener('change', () => sourceSelect.form.submit());
    if (!form || !form.dataset.concernId || Number(form.dataset.concernId) <= 0) return;

    const tagInput = document.getElementById('damageOutwardTag');
    const scanButton = document.getElementById('damageOutwardScanButton');
    const feedback = document.getElementById('damageOutwardFeedback');
    const rows = document.getElementById('damageOutwardRows');
    const emptyRow = document.getElementById('emptyDamageOutwardRow');
    const saveButton = document.getElementById('saveDamageOutward');
    const queued = new Map();
    let scanInProgress = false;

    const formatQty = value => Number(value || 0).toLocaleString('en-IN', {maximumFractionDigits: 0});

    function renderQueue() {
        rows.querySelectorAll('tr:not(#emptyDamageOutwardRow)').forEach(row => row.remove());
        let totalQty = 0;
        queued.forEach(item => {
            totalQty += item.qty;
            const row = document.createElement('tr');
            [item.product_code, item.variant_name, item.size || '—', `${formatQty(item.available_qty)} pcs`].forEach((value, index) => {
                const cell = document.createElement('td');
                cell.textContent = value;
                if (index === 0) cell.className = 'font-monospace small';
                if (index === 1) cell.className = 'fw-semibold';
                row.appendChild(cell);
            });
            const maxQty = Math.max(1, Math.floor(Number(item.available_qty) || 1));
            item.qty = Math.max(1, Math.min(Math.floor(Number(item.qty) || 1), maxQty));
            const qtyCell = document.createElement('td');
            const qtyInput = document.createElement('input');
            qtyInput.type = 'number';
            qtyInput.min = '1';
            qtyInput.step = '1';
            qtyInput.max = String(maxQty);
            qtyInput.inputMode = 'numeric';
            qtyInput.value = String(item.qty);
            qtyInput.className = 'form-control form-control-sm text-end';
            qtyInput.setAttribute('aria-label', `Dispatch quantity for ${item.variant_name}`);
            qtyInput.addEventListener('keydown', event => {
                if (['.', ',', 'e', 'E', '-', '+'].includes(event.key)) {
                    event.preventDefault();
                }
            });
            const updateCountBadge = () => {
                let sum = 0;
                queued.forEach(q => { sum += Number(q.qty) || 0; });
                document.getElementById('damageOutwardCount').textContent = `${formatQty(sum)} ${sum === 1 ? 'piece' : 'pieces'} scanned`;
            };
            qtyInput.addEventListener('input', () => {
                const raw = qtyInput.value.trim();
                if (raw === '') return;
                let parsed = Math.floor(Number(raw));
                if (!Number.isFinite(parsed) || parsed < 1) {
                    parsed = 1;
                    qtyInput.value = '1';
                } else if (parsed > maxQty) {
                    parsed = maxQty;
                    qtyInput.value = String(maxQty);
                    feedback.textContent = `Maximum available damaged qty for ${item.variant_name} is ${formatQty(maxQty)} pcs.`;
                }
                item.qty = parsed;
                updateCountBadge();
            });
            qtyInput.addEventListener('change', () => {
                let parsed = Math.floor(Number(qtyInput.value));
                if (!Number.isFinite(parsed) || parsed < 1) parsed = 1;
                if (parsed > maxQty) {
                    parsed = maxQty;
                    feedback.textContent = `Maximum available damaged qty for ${item.variant_name} is ${formatQty(maxQty)} pcs.`;
                }
                item.qty = parsed;
                qtyInput.value = String(parsed);
                renderQueue();
            });
            qtyCell.appendChild(qtyInput);
            row.appendChild(qtyCell);
            const removeCell = document.createElement('td');
            removeCell.className = 'text-end';
            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'btn btn-sm btn-outline-danger';
            removeButton.textContent = 'Remove';
            removeButton.addEventListener('click', () => {
                queued.delete(item.variant_id);
                renderQueue();
            });
            removeCell.appendChild(removeButton);
            row.appendChild(removeCell);
            rows.appendChild(row);
        });
        emptyRow.classList.toggle('d-none', queued.size > 0);
        document.getElementById('damageOutwardCount').textContent = `${formatQty(totalQty)} ${totalQty === 1 ? 'piece' : 'pieces'} scanned`;
        saveButton.disabled = queued.size === 0;
    }

    async function scanTag() {
        if (scanInProgress) return;
        const tagCode = tagInput.value.trim();
        if (!tagCode) {
            feedback.textContent = 'Scan or enter a damaged-stock label first.';
            tagInput.focus();
            return;
        }
        scanInProgress = true;
        scanButton.disabled = true;
        feedback.textContent = 'Checking available damaged stock...';
        try {
            const body = new URLSearchParams({
                csrf_token: form.dataset.csrfToken,
                concern_id: form.dataset.concernId,
                tag_code: tagCode,
            });
            const response = await fetch(form.dataset.scanUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
                body: body.toString(),
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'This label cannot be dispatched.');
            const item = result.item;
            const existing = queued.get(item.variant_id);
            if (existing) {
                if (existing.qty >= item.available_qty) throw new Error(`Only ${formatQty(item.available_qty)} damaged piece(s) are available.`);
                existing.qty++;
                existing.available_qty = item.available_qty;
            } else {
                queued.set(item.variant_id, {...item, qty: 1});
            }
            feedback.textContent = `Added ${item.variant_name}; ${formatQty(item.available_qty)} damaged piece(s) available.`;
            renderQueue();
            tagInput.value = '';
        } catch (error) {
            feedback.textContent = error.message || 'Could not add this scan.';
        } finally {
            scanInProgress = false;
            scanButton.disabled = false;
            tagInput.focus();
        }
    }

    scanButton.addEventListener('click', scanTag);
    const stockSelect = document.getElementById('damageOutwardStockSelect');
    if (stockSelect) {
        stockSelect.addEventListener('change', () => {
            const opt = stockSelect.options[stockSelect.selectedIndex];
            if (!opt || !opt.value) return;
            const variantId = Number(opt.dataset.variantId || opt.value);
            const availableQty = Math.max(0, Math.floor(Number(opt.dataset.availableQty) || 0));
            const item = {
                variant_id: variantId,
                product_code: opt.dataset.productCode || '',
                variant_name: opt.dataset.variantName || '',
                size: opt.dataset.size || '',
                available_qty: availableQty
            };
            const existing = queued.get(variantId);
            if (existing) {
                if (existing.qty >= availableQty) {
                    feedback.textContent = `Only ${formatQty(availableQty)} damaged piece(s) are available for ${item.variant_name}.`;
                } else {
                    existing.qty++;
                    existing.available_qty = availableQty;
                    feedback.textContent = `Added ${item.variant_name}; ${formatQty(availableQty)} damaged piece(s) available.`;
                }
            } else if (availableQty > 0) {
                queued.set(variantId, {...item, qty: 1});
                feedback.textContent = `Added ${item.variant_name}; ${formatQty(availableQty)} damaged piece(s) available.`;
            }
            renderQueue();
            stockSelect.value = '';
        });
    }
    tagInput.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            scanTag();
        }
    });
    document.getElementById('clearDamageOutward').addEventListener('click', () => {
        queued.clear();
        feedback.textContent = 'Scans cleared.';
        renderQueue();
        tagInput.focus();
    });
    form.addEventListener('submit', event => {
        if (!queued.size) {
            event.preventDefault();
            feedback.textContent = 'Scan at least one damaged-stock item before dispatching.';
            return;
        }
        document.getElementById('damageOutwardItems').value = JSON.stringify(
            Array.from(queued.values(), item => ({
                variant_id: item.variant_id,
                qty: Math.max(1, Math.min(Math.floor(Number(item.qty) || 1), Math.max(1, Math.floor(Number(item.available_qty) || 1))))
            }))
        );
    });
    renderQueue();
    tagInput.focus();
})();
</script>
