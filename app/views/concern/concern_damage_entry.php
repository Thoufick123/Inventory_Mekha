<div class="container-fluid px-0">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-qr-code text-danger me-2"></i>Log Damaged Stock &amp; Returns Entry</h4>
            <p class="text-secondary small mb-0">Scan a stock label, confirm available pieces, then quarantine damaged units or send them for rework.</p>
        </div>
        <a href="<?= \App\Core\Controller::url('/m/concern_damage' . ($isSuperAdmin && $selectedCid ? '?concern_id=' . (int)$selectedCid : '')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Damage List
        </a>
    </div>

    <?php if ($isSuperAdmin): ?>
        <form method="GET" action="<?= \App\Core\Controller::url('/m/concern_damage/new') ?>" class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <label class="form-label small fw-semibold" for="entryConcernId">Concern *</label>
                <select class="form-select" name="concern_id" id="entryConcernId" required>
                    <option value="">Select a concern to load its stock</option>
                    <?php foreach ($allConcerns as $concern): ?>
                        <option value="<?= (int)$concern['id'] ?>" <?= (int)$selectedCid === (int)$concern['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($concern['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-text text-muted">Choose a concern to load its locations and scan stock on this page.</small>
                <noscript><button class="btn btn-primary mt-2" type="submit">Load concern</button></noscript>
            </div>
        </form>
    <?php elseif (!empty($selectedCid)): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                <div class="small text-muted">Concern</div>
                <div class="fw-semibold"><?= htmlspecialchars($concernName ?: 'Assigned concern') ?></div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($selectedCid)): ?>
        <div class="alert alert-info border-0 shadow-sm">Select a concern before scanning stock labels.</div>
    <?php elseif (empty($locations)): ?>
        <div class="alert alert-warning border-0 shadow-sm">No available stock locations were found for this concern.</div>
    <?php else: ?>
        <form method="POST"
              action="<?= \App\Core\Controller::url('/m/concern_damage/store') ?>"
              id="concernDamageForm"
              data-scan-url="<?= htmlspecialchars(\App\Core\Controller::url('/m/concern_damage/scan'), ENT_QUOTES, 'UTF-8') ?>"
              data-csrf-token="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
              data-concern-id="<?= (int)$selectedCid ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <?php if ($isSuperAdmin): ?>
                <input type="hidden" name="concern_id" value="<?= (int)$selectedCid ?>">
            <?php endif; ?>
            <input type="hidden" name="scanned_items" id="scannedDamageItems">

            <div class="card border-0 shadow-sm damage-scanner-card mb-3">
                <div class="card-body p-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="damage-scanner-icon"><i class="bi bi-qr-code-scan"></i></span>
                            <div>
                                <div class="fw-bold">Live Concern QR Scanner</div>
                                <small class="text-muted">Scan the QR code on the stock label, or enter its printed product code (for example, GRMAPSHRL01).</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <label class="form-check form-switch mb-0 d-flex align-items-center gap-2">
                                <input class="form-check-input mt-0" type="checkbox" id="continuousDamageFocus" checked>
                                <span class="small text-muted">Continuous Auto-Focus</span>
                            </label>
                            <span class="badge rounded-pill bg-white text-success border" id="damageScanCount">0 pieces scanned</span>
                        </div>
                    </div>
                    <div class="input-group input-group-lg damage-scanner-input">
                        <span class="input-group-text bg-white text-danger"><i class="bi bi-upc-scan"></i></span>
                        <input type="text" id="damageTagInput" class="form-control font-monospace" autocomplete="off" placeholder="Scan QR or enter product code (e.g. GRMAPSHRL01)..." aria-label="Scan product QR code or enter its printed product code">
                        <button type="button" class="btn btn-danger fw-bold px-4" id="scanDamageTagButton">
                            <i class="bi bi-lightning-charge-fill me-1"></i> Scan / Add
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="clearDamageTagButton" title="Clear scan input" aria-label="Clear scan input">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="small text-muted mt-2">Each accepted scan adds one piece. Products and available stock are checked within the selected concern.</div>
                    <div class="alert alert-danger py-2 mt-3 mb-0 d-none" id="damageScanError" role="alert" aria-live="polite"></div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-box-seam text-danger me-2"></i>Damaged / Rework Items Queue</h6>
                    <span class="badge bg-danger rounded-pill" id="damageQueueCount">0 items</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 damage-queue-table">
                        <thead class="table-light">
                            <tr>
                                <th>QR Tag</th>
                                <th>Category</th>
                                <th>Product Name / Variant</th>
                                <th>Size</th>
                                <th>Available</th>
                                <th>Damage Reason *</th>
                                <th>Qty (pcs) *</th>
                                <th>Unit (₹)</th>
                                <th>Loss (₹)</th>
                                <th>Remove</th>
                            </tr>
                        </thead>
                        <tbody id="scannedConcernDamageRows">
                            <tr id="emptyConcernDamageRow">
                                <td colspan="10" class="text-center py-5">
                                    <div class="damage-empty-icon"><i class="bi bi-upc-scan"></i></div>
                                    <div class="fw-semibold mt-2">No items scanned in queue</div>
                                    <small class="text-muted">Scan a QR tag above to add a damaged or rework item.</small>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <small class="text-muted">Available quantity is checked again before stock moves to quarantine.</small>
                    <button type="submit" class="btn btn-danger fw-semibold px-4" id="saveConcernDamageButton" disabled>
                        <i class="bi bi-shield-check me-1"></i> Log Damage &amp; Quarantine
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<style>
    .damage-scanner-card { border-left: 4px solid #dc3545 !important; background: linear-gradient(110deg, #fff5f6 0%, #fff 75%); }
    .damage-scanner-icon { width: 40px; height: 40px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #dc3545; background: #fee2e2; font-size: 1.15rem; }
    .damage-scanner-input { box-shadow: 0 2px 6px rgba(220, 53, 69, .08); }
    .damage-scanner-input .form-control { font-size: 1rem; }
    .damage-queue-table { min-width: 1120px; }
    .damage-empty-icon { width: 64px; height: 64px; border-radius: 50%; margin: 0 auto; display: flex; align-items: center; justify-content: center; background: #f8f9fa; color: #dc3545; font-size: 1.5rem; }
</style>

<script>
(() => {
    const concernSelect = document.getElementById('entryConcernId');
    if (concernSelect) {
        concernSelect.addEventListener('change', () => concernSelect.form.submit());
    }

    const form = document.getElementById('concernDamageForm');
    if (!form) return;

    const tagInput = document.getElementById('damageTagInput');
    const scanButton = document.getElementById('scanDamageTagButton');
    const errorBox = document.getElementById('damageScanError');
    const rows = document.getElementById('scannedConcernDamageRows');
    const emptyRow = document.getElementById('emptyConcernDamageRow');
    const saveButton = document.getElementById('saveConcernDamageButton');
    const queuedItems = new Map();
    let scanInProgress = false;

    const formatNumber = value => Number(value || 0).toLocaleString('en-IN', {maximumFractionDigits: 0});
    const formatMoney = value => Number(value || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.toggle('d-none', !message);
    }

    function updateSummary() {
        const totalPieces = Array.from(queuedItems.values()).reduce((sum, item) => sum + item.qty, 0);
        document.getElementById('damageScanCount').textContent = `${formatNumber(totalPieces)} ${totalPieces === 1 ? 'piece' : 'pieces'} scanned`;
        document.getElementById('damageQueueCount').textContent = `${queuedItems.size} ${queuedItems.size === 1 ? 'item' : 'items'}`;
        saveButton.disabled = queuedItems.size === 0;
        emptyRow.classList.toggle('d-none', queuedItems.size > 0);
    }

    function buildRow(item) {
        const row = document.createElement('tr');
        const values = [item.tag_code, item.category_name, item.variant_name, item.size || '—', `${formatNumber(item.available_qty)} pcs`];
        values.forEach((value, index) => {
            const cell = document.createElement('td');
            cell.textContent = value;
            if (index === 0) cell.className = 'font-monospace small text-danger fw-semibold';
            if (index === 2) cell.className = 'fw-semibold';
            row.appendChild(cell);
        });

        const reasonCell = document.createElement('td');
        const reasonInput = document.createElement('input');
        reasonInput.type = 'text';
        reasonInput.className = 'form-control form-control-sm';
        reasonInput.maxLength = 255;
        reasonInput.required = true;
        reasonInput.placeholder = 'Leak, tear, handling fall...';
        reasonInput.value = item.reason || '';
        reasonInput.setAttribute('aria-label', `Damage reason for ${item.variant_name}`);
        reasonInput.addEventListener('input', () => { item.reason = reasonInput.value; });
        reasonCell.appendChild(reasonInput);
        row.appendChild(reasonCell);

        const qtyCell = document.createElement('td');
        const qtyInput = document.createElement('input');
        qtyInput.type = 'number';
        qtyInput.className = 'form-control form-control-sm';
        qtyInput.min = '1';
        qtyInput.step = '1';
        qtyInput.max = String(Math.floor(item.available_qty));
        qtyInput.value = String(item.qty);
        qtyInput.required = true;
        qtyInput.setAttribute('aria-label', `Damage quantity for ${item.variant_name}`);
        qtyInput.addEventListener('change', () => {
            const requested = Math.floor(Number(qtyInput.value));
            item.qty = Math.max(1, Math.min(requested || 1, Math.floor(item.available_qty)));
            qtyInput.value = String(item.qty);
            updateRowLoss(row, item);
            updateSummary();
        });
        qtyCell.appendChild(qtyInput);
        row.appendChild(qtyCell);

        const unitCell = document.createElement('td');
        unitCell.textContent = `₹${formatMoney(item.unit_price)}`;
        row.appendChild(unitCell);
        const lossCell = document.createElement('td');
        lossCell.className = 'fw-semibold text-danger';
        lossCell.textContent = `₹${formatMoney(item.unit_price * item.qty)}`;
        row.appendChild(lossCell);
        const removeCell = document.createElement('td');
        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'btn btn-sm btn-outline-danger';
        removeButton.title = 'Remove from queue';
        removeButton.setAttribute('aria-label', `Remove ${item.variant_name}`);
        removeButton.innerHTML = '<i class="bi bi-trash"></i>';
        removeButton.addEventListener('click', () => {
            queuedItems.delete(item.variant_id);
            row.remove();
            updateSummary();
        });
        removeCell.appendChild(removeButton);
        row.appendChild(removeCell);
        return row;
    }

    function updateRowLoss(row, item) {
        const lossCell = row.children[8];
        lossCell.textContent = `₹${formatMoney(item.unit_price * item.qty)}`;
    }

    function renderQueue() {
        rows.querySelectorAll('tr:not(#emptyConcernDamageRow)').forEach(row => row.remove());
        queuedItems.forEach(item => rows.appendChild(buildRow(item)));
        updateSummary();
    }

    async function scanTag() {
        if (scanInProgress) return;
        const tagCode = tagInput.value.trim();
        if (!tagCode) {
            showError('Scan or enter a QR tag first.');
            tagInput.focus();
            return;
        }
        scanInProgress = true;
        scanButton.disabled = true;
        showError('');
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
            if (!response.ok || !result.success) throw new Error(result.message || 'Could not resolve this QR tag.');

            const item = result.item;
            const queued = queuedItems.get(item.variant_id);
            if (queued) {
                if (queued.qty >= Math.floor(item.available_qty)) {
                    throw new Error(`Only ${formatNumber(item.available_qty)} piece(s) are available at this location.`);
                }
                queued.qty += 1;
                queued.available_qty = item.available_qty;
                queued.tag_code = item.tag_code;
            } else {
                queuedItems.set(item.variant_id, {...item, qty: 1, reason: ''});
            }
            renderQueue();
            tagInput.value = '';
        } catch (error) {
            showError(error.message || 'Could not add this scan.');
        } finally {
            scanInProgress = false;
            scanButton.disabled = false;
            if (document.getElementById('continuousDamageFocus').checked) tagInput.focus();
        }
    }

    scanButton.addEventListener('click', scanTag);
    tagInput.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            scanTag();
        }
    });
    document.getElementById('clearDamageTagButton').addEventListener('click', () => {
        tagInput.value = '';
        showError('');
        tagInput.focus();
    });
    document.getElementById('continuousDamageFocus').addEventListener('change', event => {
        if (event.target.checked) tagInput.focus();
    });
    form.addEventListener('submit', event => {
        const items = Array.from(queuedItems.values());
        if (!items.length || items.some(item => !item.reason.trim())) {
            event.preventDefault();
            showError(items.length ? 'Enter a damage reason for every queued product.' : 'Scan at least one product label.');
            return;
        }
        document.getElementById('scannedDamageItems').value = JSON.stringify(items.map(item => ({
            variant_id: item.variant_id,
            qty: item.qty,
            reason: item.reason.trim(),
        })));
    });
    updateSummary();
    if (document.getElementById('continuousDamageFocus').checked) tagInput.focus();
})();
</script>
