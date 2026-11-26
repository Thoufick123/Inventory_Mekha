<div class="container-fluid px-0">
    <?php if (empty($hideDamageHeader)): ?>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Purchase Damage Quarantine</h4>
            <p class="text-secondary small mb-0">Scan inwarded Purchase Stock labels to quarantine damaged items and update available stock.</p>
        </div>
        <?php if ($isSuperAdmin): ?>
            <form method="GET" action="<?= \App\Core\Controller::url('/m/purchase_damage') ?>" class="d-flex gap-2 align-items-center">
                <label for="damageConcernFilter" class="small fw-semibold text-nowrap">Concern</label>
                <select id="damageConcernFilter" name="concern_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All concerns</option>
                    <?php foreach ($allConcerns as $concern): ?>
                        <option value="<?= (int)$concern['id'] ?>" <?= (int)$selectedCid === (int)$concern['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($concern['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($selectedCid)): ?>
        <div class="alert alert-info border-0 shadow-sm">Select a concern above before scanning purchase labels.</div>
    <?php elseif (!empty($canAddDamage)): ?>
        <form action="<?= \App\Core\Controller::url('/m/purchase_damage/store') ?>" method="POST" id="purchaseDamageForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <?php if ($isSuperAdmin): ?>
                <input type="hidden" name="concern_id" value="<?= (int)$selectedCid ?>">
            <?php endif; ?>
            <div id="scannedTagInputs"></div>

            <div class="row g-3 mb-4">
                <div class="col-xl-8">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-upc-scan me-2 text-primary"></i>Scan Purchase Stock Label</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-6">
                                    <label for="purchaseDamageTag" class="form-label small fw-semibold">Label code</label>
                                    <input type="text" id="purchaseDamageTag" class="form-control form-control-lg" autocomplete="off" placeholder="Scan or enter the QR label code">
                                </div>
                                <div class="col-12 col-md-6 d-grid">
                                    <button type="button" class="btn btn-primary btn-lg" id="scanPurchaseDamageButton">
                                        <i class="bi bi-upc-scan me-1"></i> Scan
                                    </button>
                                </div>
                            </div>
                            <div class="form-text">Each scan adds one whole unit. Enter whole quantities only; the damage quantity cannot exceed available stock.</div>
                            <div class="alert alert-danger py-2 mt-3 mb-0 d-none" id="purchaseDamageScanError" role="alert"></div>

                            <div class="table-responsive mt-4">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Fabric / Product</th>
                                            <th>Label</th>
                                            <th>Damage qty</th>
                                            <th>Available</th>
                                            <th>Weight</th>
                                            <th>Est. loss</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="scannedPurchaseDamageRows">
                                        <tr id="emptyScannedDamageRow">
                                            <td colspan="7" class="text-center text-muted py-4">No labels scanned yet.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-shield-exclamation me-2 text-danger"></i>Damage details</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-2 mb-3">
                                <div class="col-4">
                                    <div class="bg-light rounded p-3 h-100">
                                        <div class="small text-muted">Damage qty</div>
                                        <div class="fs-4 fw-bold" id="scannedDamageCount">0</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="bg-light rounded p-3 h-100">
                                        <div class="small text-muted">Damaged weight</div>
                                        <div class="fs-5 fw-bold" id="scannedDamageWeight">0.00</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="bg-light rounded p-3 h-100">
                                        <div class="small text-muted">Est. loss</div>
                                        <div class="fs-5 fw-bold" id="scannedDamageLoss">0.00</div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="damageReason" class="form-label small fw-semibold">Damage reason <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select name="reason" id="damageReason" class="form-select" required>
                                        <option value="">Select a reason</option>
                                        <?php foreach ($damageReasons as $damageReason): ?>
                                            <option value="<?= htmlspecialchars($damageReason['reason'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($damageReason['reason']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($canAddDamage): ?>
                                        <button type="button" class="btn btn-outline-primary" id="addDamageReasonButton">
                                            <i class="bi bi-plus-lg me-1"></i>Add reason
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div id="damageReasonStatus" class="form-text" aria-live="polite"></div>
                            </div>
                            <div class="mb-3">
                                <label for="damageNotes" class="form-label small fw-semibold">Notes</label>
                                <textarea name="notes" id="damageNotes" class="form-control" rows="3" maxlength="2000" placeholder="Add damage or quarantine notes"></textarea>
                            </div>
                            <button type="submit" class="btn btn-danger w-100" id="savePurchaseDamageButton" disabled>
                                <i class="bi bi-shield-check me-1"></i> Save Damage &amp; Quarantine
                            </button>
                            <div class="small text-muted mt-2">Available stock is rechecked when you save.</div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <?php if (!empty($selectedCid) && $canAddDamage): ?>
        <div class="modal fade" id="addDamageReasonModal" tabindex="-1" aria-labelledby="addDamageReasonModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form id="addDamageReasonForm" action="<?= \App\Core\Controller::url('/m/purchase_damage/reasons/add') ?>" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="concern_id" value="<?= (int)$selectedCid ?>">
                        <div class="modal-header py-2">
                            <h5 class="modal-title fs-6 fw-bold" id="addDamageReasonModalLabel">Add Damage Reason</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <label for="newDamageReason" class="form-label small fw-semibold">Reason</label>
                            <input type="text" class="form-control" id="newDamageReason" name="reason" maxlength="255" required>
                            <div id="addDamageReasonError" class="alert alert-danger py-2 mt-3 mb-0 d-none" role="alert"></div>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-primary" id="saveDamageReasonButton">
                                <i class="bi bi-check2 me-1"></i>Save reason
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <!-- Export Intelligence Toolbar -->
    <?php
    $exportTitle = 'Export Purchase Damage Records (Excel & PDF)';
    $exportSubtitle = 'Download damaged material arrivals, supplier rejections, stock write-downs & claim audits';
    $exportQuery = !empty($selectedCid) ? '?concern_id=' . (int)$selectedCid : '';
    $exportUrl = \App\Core\Controller::url('/m/purchase_damage/export' . $exportQuery);
    $prefix = 'pdamage_exp';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-archive me-2 text-danger"></i>Quarantine history</h6>
            <span class="badge bg-light text-dark border"><?= count($damages) ?> records</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Damage No.</th>
                        <th>QR Tag</th>
                        <th>Concern</th>
                        <th>Supplier</th>
                        <th>Fabric / Product</th>
                        <th>Qty</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($damages)): ?>
                        <tr><td colspan="10" class="text-center py-4 text-muted">No purchase damage records.</td></tr>
                    <?php else: ?>
                        <?php foreach ($damages as $damage): ?>
                            <tr>
                                <td><span class="badge bg-danger font-monospace"><?= htmlspecialchars($damage['damage_no']) ?></span></td>
                                <td>
                                    <?php if (!empty($damage['tag_code'])): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2"
                                            data-qr-tag="<?= htmlspecialchars($damage['tag_code'], ENT_QUOTES, 'UTF-8') ?>"
                                            onclick="showDamageQr(this)" title="View scanned purchase QR tag">
                                            <i class="bi bi-qr-code me-1"></i><span class="font-monospace"><?= htmlspecialchars($damage['tag_code']) ?></span>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($damage['concern_name'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($damage['supplier_name'] ?: '—') ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($damage['item_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($damage['item_code'] ?: '—') ?></small>
                                </td>
                                <td class="fw-bold text-danger">-<?= number_format((float)$damage['qty'], 2) ?></td>
                                <td class="small"><?= htmlspecialchars($damage['reason'] ?: 'Unspecified defect') ?></td>
                                <td><span class="badge bg-warning-subtle text-warning-emphasis"><?= htmlspecialchars($damage['action_taken'] ?: 'Quarantined') ?></span></td>
                                <td class="small text-muted"><?= date('d M Y', strtotime($damage['created_at'])) ?></td>
                                <td>
                                    <?php if (
                                        ($damage['history_source'] ?? '') === 'quarantine' &&
                                        ($damage['action_taken'] ?? '') === 'Quarantined'
                                    ): ?>
                                        <div class="d-flex justify-content-center gap-1">
                                            <?php if ($canEditDamage): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary p-1"
                                                    data-damage-id="<?= (int)$damage['record_id'] ?>"
                                                    data-concern-id="<?= (int)$damage['concern_id'] ?>"
                                                    data-qty="<?= htmlspecialchars((string)$damage['qty'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-reason="<?= htmlspecialchars($damage['reason'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                    data-notes="<?= htmlspecialchars($damage['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                    onclick="editQuarantineRecord(this)" title="Edit quarantine record">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($canDeleteDamage): ?>
                                                <form action="<?= \App\Core\Controller::url('/m/purchase_damage/delete') ?>" method="POST" class="d-inline"
                                                    onsubmit="return confirm('Delete this quarantine record and restore its stock?')">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                    <input type="hidden" name="damage_id" value="<?= (int)$damage['record_id'] ?>">
                                                    <input type="hidden" name="concern_id" value="<?= (int)$damage['concern_id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger p-1" title="Delete quarantine record">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif (($damage['history_source'] ?? '') === 'quarantine'): ?>
                                        <span class="text-muted">Read-only</span>
                                    <?php else: ?>
                                        <span class="text-muted">Legacy</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($canEditDamage): ?>
        <div class="modal fade" id="editQuarantineRecordModal" tabindex="-1" aria-labelledby="editQuarantineRecordModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="<?= \App\Core\Controller::url('/m/purchase_damage/update') ?>" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="damage_id" id="edit_damage_id">
                        <input type="hidden" name="concern_id" id="edit_damage_concern_id">
                        <div class="modal-header py-2">
                            <h5 class="modal-title fs-6 fw-bold" id="editQuarantineRecordModalLabel">Edit Quarantine Record</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="edit_damage_qty" class="form-label small fw-semibold">Quarantined Quantity</label>
                                <input type="number" class="form-control" id="edit_damage_qty" name="qty" min="0.01" max="500" step="0.01" required>
                                <div class="form-text">Stock balances are adjusted when this quantity changes.</div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_damage_reason" class="form-label small fw-semibold">Reason</label>
                                <input type="text" class="form-control" id="edit_damage_reason" name="reason" maxlength="255" required>
                            </div>
                            <div>
                                <label for="edit_damage_notes" class="form-label small fw-semibold">Notes</label>
                                <textarea class="form-control" id="edit_damage_notes" name="notes" rows="3" maxlength="2000"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check2 me-1"></i>Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="modal fade" id="damageQrModal" tabindex="-1" aria-labelledby="damageQrModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-bold" id="damageQrModalLabel">Quarantine QR Tag</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body d-flex flex-column align-items-center gap-2">
                    <div id="damageQrCode" aria-label="QR code for scanned purchase tag"></div>
                    <span class="font-monospace fw-semibold" id="damageQrTagText"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showDamageQr(button) {
    const tag = button && button.dataset ? button.dataset.qrTag || '' : '';
    const qrBox = document.getElementById('damageQrCode');
    const modal = document.getElementById('damageQrModal');
    if (!tag || !qrBox || !modal) return;
    if (typeof QRCode === 'undefined') {
        alert('QR display is unavailable. Refresh the page and try again.');
        return;
    }

    qrBox.replaceChildren();
    document.getElementById('damageQrTagText').textContent = tag;
    new QRCode(qrBox, {
        text: tag,
        width: 180,
        height: 180,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });
    bootstrap.Modal.getOrCreateInstance(modal).show();
}

function editQuarantineRecord(button) {
    const modal = document.getElementById('editQuarantineRecordModal');
    if (!modal || !button) return;
    document.getElementById('edit_damage_id').value = button.dataset.damageId || '';
    document.getElementById('edit_damage_concern_id').value = button.dataset.concernId || '';
    document.getElementById('edit_damage_qty').value = button.dataset.qty || '1';
    document.getElementById('edit_damage_reason').value = button.dataset.reason || '';
    document.getElementById('edit_damage_notes').value = button.dataset.notes || '';
    bootstrap.Modal.getOrCreateInstance(modal).show();
}
</script>

<?php if (!empty($selectedCid)): ?>
<script>
(() => {
    const scanUrl = <?= json_encode(\App\Core\Controller::url('/m/purchase_damage/scan')) ?>;
    const input = document.getElementById('purchaseDamageTag');
    const scanButton = document.getElementById('scanPurchaseDamageButton');
    const errorBox = document.getElementById('purchaseDamageScanError');
    const rows = document.getElementById('scannedPurchaseDamageRows');
    const hiddenInputs = document.getElementById('scannedTagInputs');
    const saveButton = document.getElementById('savePurchaseDamageButton');
    const scanned = [];

    const number = value => Number(value || 0);
    const display = value => number(value).toFixed(2);
    const displayQuantity = value => String(Math.round(number(value)));
    const esc = value => String(value || '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[char]));

    function renderScanned() {
        rows.innerHTML = '';
        if (!scanned.length) {
            rows.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No labels scanned yet.</td></tr>';
        } else {
            scanned.forEach((entry, index) => {
                const availableQty = number(entry.item.available_qty);
                const enteredQty = entry.inputQty === undefined ? displayQuantity(entry.qty) : entry.inputQty;
                const wholeAvailableQty = Math.floor(availableQty);
                rows.insertAdjacentHTML('beforeend', `<tr>
                    <td><div class="fw-semibold">${esc(entry.item.item_name)}</div><small class="text-muted">${esc(entry.item.item_type.replace('_', ' '))}</small></td>
                    <td><span class="font-monospace">${esc(entry.item.tag_code)}</span></td>
                    <td><input type="number" class="form-control form-control-sm damage-qty-input${entry.invalid ? ' is-invalid' : ''}" style="width: 100px" min="1" max="${wholeAvailableQty}" step="1" value="${esc(enteredQty)}" data-qty="${index}" aria-label="Damage quantity for ${esc(entry.item.item_name)}" required></td>
                    <td data-available="${index}">${display(Math.max(0, availableQty - entry.qty))} / ${display(availableQty)}</td>
                    <td data-weight="${index}">${display(number(entry.item.unit_weight) * entry.qty)}</td>
                    <td data-loss="${index}">${display(number(entry.item.unit_loss) * entry.qty)}</td>
                    <td><button type="button" class="btn btn-sm btn-outline-secondary" data-remove="${index}" aria-label="Remove scanned quantity"><i class="bi bi-x-lg"></i></button></td>
                </tr>`);
            });
        }

        updateScannedSummary();
    }

    function updateScannedSummary() {
        hiddenInputs.innerHTML = '';
        const validItems = scanned.filter(entry => !entry.invalid);
        if (validItems.length) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'scanned_items';
            hidden.value = JSON.stringify(validItems.map(entry => ({
                tag_code: entry.item.tag_code,
                qty: entry.qty
            })));
            hiddenInputs.appendChild(hidden);
        }

        saveButton.disabled = !scanned.length || scanned.some(entry => entry.invalid);
        const totalQty = validItems.reduce((sum, entry) => sum + entry.qty, 0);
        const totalWeight = validItems.reduce((sum, entry) => sum + number(entry.item.unit_weight) * entry.qty, 0);
        const totalLoss = validItems.reduce((sum, entry) => sum + number(entry.item.unit_loss) * entry.qty, 0);
        document.getElementById('scannedDamageCount').textContent = displayQuantity(totalQty);
        document.getElementById('scannedDamageWeight').textContent = display(totalWeight);
        document.getElementById('scannedDamageLoss').textContent = display(totalLoss);
    }

    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
    }

    async function scan() {
        const tagCode = input.value.trim().toUpperCase();
        if (!tagCode) {
            showError('Enter or scan a purchase label code.');
            return;
        }
        if (scanned.some(entry => entry.invalid)) {
            showError('Correct or remove invalid damage quantities before scanning another label.');
            const invalidField = rows.querySelector('.damage-qty-input.is-invalid');
            if (invalidField) invalidField.focus();
            return;
        }
        errorBox.classList.add('d-none');
        scanButton.disabled = true;
        try {
            const body = new URLSearchParams({
                csrf_token: <?= json_encode($csrfToken) ?>,
                tag_code: tagCode,
                concern_id: <?= json_encode((int)$selectedCid) ?>
            });
            const response = await fetch(scanUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Could not resolve this label.');
            }

            const item = result.item;
            let entry = scanned.find(row => row.item.key === item.key);
            const availableQty = number(item.available_qty);
            const wholeAvailableQty = Math.floor(availableQty);
            if (!entry && wholeAvailableQty < 1) {
                throw new Error(`Available stock for ${item.item_name} is below one whole unit.`);
            }
            const nextQty = entry ? entry.qty + 1 : 1;
            if (nextQty > wholeAvailableQty) {
                throw new Error(`Only ${Math.max(0, wholeAvailableQty - (entry ? entry.qty : 0))} whole unit(s) remain for ${item.item_name}.`);
            }
            if (!entry) {
                entry = { item, qty: 0, invalid: false };
                scanned.push(entry);
            }
            entry.item = item;
            entry.qty = nextQty;
            entry.inputQty = displayQuantity(entry.qty);
            entry.invalid = false;
            renderScanned();
            input.value = '';
            input.focus();
        } catch (error) {
            showError(error.message || 'Could not resolve this label.');
        } finally {
            scanButton.disabled = false;
        }
    }

    scanButton.addEventListener('click', scan);
    input.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            scan();
        }
    });
    rows.addEventListener('input', event => {
        const field = event.target.closest('[data-qty]');
        if (!field) return;
        const index = Number(field.dataset.qty);
        const entry = scanned[index];
        if (!entry) return;

        const rawQty = field.value.trim();
        const qty = Number(rawQty);
        const availableQty = number(entry.item.available_qty);
        const wholeAvailableQty = Math.floor(availableQty);
        const valid = rawQty !== ''
            && Number.isFinite(qty)
            && Number.isInteger(qty)
            && qty >= 1
            && qty <= wholeAvailableQty;
        entry.inputQty = field.value;
        entry.invalid = !valid;
        if (valid) {
            entry.qty = qty;
        }
        field.classList.toggle('is-invalid', !valid);
        field.setCustomValidity(valid ? '' : `Enter a whole quantity from 1 to ${wholeAvailableQty}.`);

        const displayedQty = Number.isFinite(qty) && qty > 0 ? qty : entry.qty;
        const availableCell = rows.querySelector(`[data-available="${index}"]`);
        const weightCell = rows.querySelector(`[data-weight="${index}"]`);
        const lossCell = rows.querySelector(`[data-loss="${index}"]`);
        if (availableCell) {
            availableCell.textContent = `${display(Math.max(0, availableQty - displayedQty))} / ${display(availableQty)}`;
        }
        if (weightCell) weightCell.textContent = display(number(entry.item.unit_weight) * displayedQty);
        if (lossCell) lossCell.textContent = display(number(entry.item.unit_loss) * displayedQty);

        updateScannedSummary();
        if (scanned.some(row => row.invalid)) {
            showError(`Damage quantity must be a whole number from 1 to ${wholeAvailableQty}.`);
        } else {
            errorBox.classList.add('d-none');
        }
    });
    rows.addEventListener('click', event => {
        const button = event.target.closest('[data-remove]');
        if (!button) return;
        const index = Number(button.dataset.remove);
        scanned.splice(index, 1);
        renderScanned();
    });
    document.getElementById('purchaseDamageForm').addEventListener('submit', event => {
        if (!scanned.length || scanned.some(entry => entry.invalid)) {
            event.preventDefault();
            showError(scanned.length
                ? 'Correct or remove invalid damage quantities before saving.'
                : 'Scan at least one label before saving damage.');
        }
    });

    <?php if ($canAddDamage): ?>
    const reasonSelect = document.getElementById('damageReason');
    const addReasonButton = document.getElementById('addDamageReasonButton');
    const addReasonForm = document.getElementById('addDamageReasonForm');
    const addReasonModal = document.getElementById('addDamageReasonModal');
    const addReasonError = document.getElementById('addDamageReasonError');
    const addReasonSubmit = document.getElementById('saveDamageReasonButton');
    const reasonStatus = document.getElementById('damageReasonStatus');
    const addReasonUrl = <?= json_encode(\App\Core\Controller::url('/m/purchase_damage/reasons/add')) ?>;

    addReasonButton.addEventListener('click', () => {
        addReasonError.classList.add('d-none');
        addReasonError.textContent = '';
        bootstrap.Modal.getOrCreateInstance(addReasonModal).show();
        document.getElementById('newDamageReason').focus();
    });

    addReasonForm.addEventListener('submit', async event => {
        event.preventDefault();
        addReasonSubmit.disabled = true;
        addReasonError.classList.add('d-none');
        addReasonError.textContent = '';
        try {
            const body = new URLSearchParams();
            for (const [key, value] of new FormData(addReasonForm).entries()) {
                body.append(key, String(value));
            }
            const response = await fetch(addReasonUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Could not save this damage reason.');
            }

            let option = Array.from(reasonSelect.options).find(item => item.value === result.reason);
            if (!option) {
                option = new Option(result.reason, result.reason);
                reasonSelect.add(option);
            }
            reasonSelect.value = result.reason;
            reasonStatus.textContent = result.message;
            reasonStatus.className = 'form-text text-success';
            bootstrap.Modal.getOrCreateInstance(addReasonModal).hide();
            addReasonForm.reset();
        } catch (error) {
            addReasonError.textContent = error.message || 'Could not save this damage reason.';
            addReasonError.classList.remove('d-none');
        } finally {
            addReasonSubmit.disabled = false;
        }
    });
    <?php endif; ?>

    renderScanned();
})();
</script>
<?php endif; ?>
