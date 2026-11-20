<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-shield-slash text-danger me-2"></i>Warehouse Damage Management</h4>
            <p class="text-secondary small mb-0">Track warehouse floor fabric and garment damages. Stock automatically moves to <code>DAMAGED</code> state until formal scrap, rework, or replacement inward.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-danger shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalLogWarehouseDamage">
                <i class="bi bi-plus-lg me-1"></i> Log Warehouse Damage
            </button>
            <a href="<?= \App\Core\Controller::url('/m/concern_damage') ?>" class="btn btn-outline-secondary shadow-sm">
                <i class="bi bi-x-octagon me-1"></i> Concern Damage
            </a>
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= \App\Core\Controller::url('/warehouse/damages') ?>" class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search Damage No, SKU, Reason..." value="<?= htmlspecialchars($search ?? '') ?>">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- All Statuses --</option>
                        <option value="LOGGED" <?= ($status ?? '') === 'LOGGED' ? 'selected' : '' ?>>Logged (Damaged)</option>
                        <option value="INSPECTED" <?= ($status ?? '') === 'INSPECTED' ? 'selected' : '' ?>>Inspected</option>
                        <option value="RESOLVED" <?= ($status ?? '') === 'RESOLVED' ? 'selected' : '' ?>>Resolved</option>
                        <option value="CANCELLED" <?= ($status ?? '') === 'CANCELLED' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="severity" class="form-select form-select-sm">
                        <option value="">-- All Severities --</option>
                        <option value="MINOR" <?= ($severity ?? '') === 'MINOR' ? 'selected' : '' ?>>Minor</option>
                        <option value="MODERATE" <?= ($severity ?? '') === 'MODERATE' ? 'selected' : '' ?>>Moderate</option>
                        <option value="SEVERE" <?= ($severity ?? '') === 'SEVERE' ? 'selected' : '' ?>>Severe</option>
                        <option value="SCRAP" <?= ($severity ?? '') === 'SCRAP' ? 'selected' : '' ?>>Total Scrap</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($fromDate ?? '') ?>">
                </div>
                <div class="col-6 col-md-2 d-flex gap-1">
                    <input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($toDate ?? '') ?>">
                    <button type="submit" class="btn btn-sm btn-primary px-3"><i class="bi bi-funnel"></i></button>
                    <?php if (!empty($search) || !empty($status) || !empty($severity) || !empty($fromDate) || !empty($toDate)): ?>
                        <a href="<?= \App\Core\Controller::url('/warehouse/damages') ?>" class="btn btn-sm btn-light border" title="Clear Filters"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Warehouse Damages Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-exclamation-triangle me-2 text-danger"></i> Warehouse Damage Records</h6>
            <span class="badge bg-light text-dark border px-2 py-1"><?= count($damages) ?> Records</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead>
                    <tr>
                        <th>Damage No</th>
                        <th>Variant Style</th>
                        <th>Qty</th>
                        <th>Severity</th>
                        <th>Status</th>
                        <th>Resolution</th>
                        <th>Replacement QR</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($damages)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted">No warehouse damage incidents recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($damages as $d): 
                            $statusBadge = match ($d['status']) {
                                'RESOLVED'   => 'bg-success',
                                'CANCELLED'  => 'bg-secondary',
                                'INSPECTED'  => 'bg-info text-dark',
                                default      => 'bg-danger'
                            };
                            $severityBadge = match ($d['severity']) {
                                'SCRAP'    => 'bg-dark',
                                'SEVERE'   => 'bg-danger',
                                'MODERATE' => 'bg-warning text-dark',
                                default    => 'bg-light text-dark border'
                            };
                        ?>
                            <tr>
                                <td>
                                    <span class="fw-bold font-monospace"><?= htmlspecialchars($d['damage_no']) ?></span>
                                    <small class="d-block text-muted"><?= htmlspecialchars($d['location']) ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($d['variant_name']) ?></div>
                                    <small class="text-muted font-monospace"><?= htmlspecialchars($d['sku_code']) ?> (<?= htmlspecialchars($d['size'] ?? 'Standard') ?>)</small>
                                </td>
                                <td><span class="text-danger fw-bold fs-6">-<?= number_format((float)$d['qty'], 2) ?></span></td>
                                <td><span class="badge <?= $severityBadge ?>"><?= htmlspecialchars($d['severity']) ?></span></td>
                                <td><span class="badge <?= $statusBadge ?>"><?= htmlspecialchars($d['status']) ?></span></td>
                                <td>
                                    <?php if ($d['status'] === 'RESOLVED'): ?>
                                        <div class="fw-semibold small text-success"><?= htmlspecialchars($d['resolution_action']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($d['resolution_reason'] ?? '') ?></small>
                                    <?php else: ?>
                                        <span class="text-muted small">Pending Resolution</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($d['replacement_qr_code'])): ?>
                                        <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($d['replacement_qr_code']) ?></span>
                                        <?php if (!empty($d['replacement_inwarded_at'])): ?>
                                            <span class="badge bg-success-subtle text-success ms-1">Inwarded</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis ms-1">Awaiting Inward</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?= date('Y-m-d', strtotime($d['created_at'])) ?></small></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= \App\Core\Controller::url('/warehouse/damages/print/' . $d['id']) ?>" target="_blank" class="btn btn-outline-secondary" title="Print Memo">
                                            <i class="bi bi-printer"></i>
                                        </a>

                                        <?php if ($d['status'] !== 'RESOLVED' && $d['status'] !== 'CANCELLED'): ?>
                                            <button type="button" class="btn btn-outline-success" title="Resolve Damage" onclick="openResolveModal(<?= htmlspecialchars(json_encode($d)) ?>)">
                                                <i class="bi bi-check2-circle"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" title="Cancel Damage" onclick="openCancelModal(<?= $d['id'] ?>, '<?= htmlspecialchars($d['damage_no']) ?>')">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($d['resolution_action'] === 'SUPPLIER_REPLACEMENT' && empty($d['replacement_inwarded_at'])): ?>
                                            <button type="button" class="btn btn-warning" title="Inward Replacement" onclick="openInwardModal(<?= $d['id'] ?>, '<?= htmlspecialchars($d['replacement_qr_code']) ?>', <?= $d['qty'] ?>)">
                                                <i class="bi bi-box-arrow-in-down"></i> Inward
                                            </button>
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
</div>

<!-- Modal: Log Warehouse Damage -->
<div class="modal fade" id="modalLogWarehouseDamage" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= \App\Core\Controller::url('/warehouse/damages/store') ?>" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\CSRF::token()) ?>">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-shield-slash me-2"></i>Log Warehouse Damage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Damaged Variant Style *</label>
                    <select name="variant_id" class="form-select form-select-sm" required>
                        <option value="">-- Choose Variant --</option>
                        <?php foreach ($variants as $v): ?>
                            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name']) ?> (<?= htmlspecialchars($v['sku_code']) ?> - <?= htmlspecialchars($v['size'] ?? 'Standard') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted text-uppercase">Quantity *</label>
                        <input type="number" step="0.01" min="0.01" name="qty" class="form-control form-control-sm" value="1" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted text-uppercase">Severity</label>
                        <select name="severity" class="form-select form-select-sm">
                            <option value="MINOR">Minor</option>
                            <option value="MODERATE" selected>Moderate</option>
                            <option value="SEVERE">Severe</option>
                            <option value="SCRAP">Total Scrap</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Specific Location on Floor</label>
                    <input type="text" name="location" class="form-control form-control-sm" value="Warehouse Floor (Aisle 1)">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Damage Reason *</label>
                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="Torn fabric during handling, water leak, dye defect..." required>
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-bold text-muted text-uppercase">Photo URL (Optional)</label>
                    <input type="url" name="photo_url" class="form-control form-control-sm" placeholder="https://res.cloudinary.com/...">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger px-4 fw-semibold">Log & Move to DAMAGED</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Resolve Damage -->
<div class="modal fade" id="modalResolveDamage" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= \App\Core\Controller::url('/warehouse/damages/resolve') ?>" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\CSRF::token()) ?>">
            <input type="hidden" name="id" id="resolveDamageId">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-success"><i class="bi bi-check2-circle me-2"></i>Resolve Warehouse Damage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Resolution Action *</label>
                    <select name="resolution_action" id="resolveActionSelect" class="form-select form-select-sm" required onchange="handleResolveActionChange(this)">
                        <option value="SCRAP">Scrap / Write-off (Deduct Permanently)</option>
                        <option value="SALVAGE">Salvage / Repair (Restore to AVAILABLE)</option>
                        <option value="REWORK">Send to Rework (Transfer to Production Unit)</option>
                        <option value="SUPPLIER_REPLACEMENT">Supplier Replacement (Claim & Inward Token)</option>
                    </select>
                </div>
                <div class="mb-3" id="resolveReplacementRow" style="display: none;">
                    <label class="form-label small fw-bold text-muted text-uppercase">Supplier Replacement QR Token</label>
                    <input type="text" name="replacement_qr" id="resolveReplacementQr" class="form-control form-control-sm font-monospace" placeholder="e.g. RPL-WH-0001">
                    <small class="text-muted d-block mt-1">Stock will be credited only when this QR is scanned upon physical receipt.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Resolution Notes / Reason</label>
                    <input type="text" name="resolution_reason" class="form-control form-control-sm" placeholder="Action taken justification..." required>
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-bold text-muted text-uppercase">Estimated Loss Amount (₹)</label>
                    <input type="number" step="0.01" min="0" name="loss_amount" class="form-control form-control-sm" value="0.00">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success px-4 fw-semibold">Confirm Resolution</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Inward Replacement -->
<div class="modal fade" id="modalInwardReplacement" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= \App\Core\Controller::url('/warehouse/damages/inward-replacement') ?>" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\CSRF::token()) ?>">
            <input type="hidden" name="id" id="inwardDamageId">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-warning"><i class="bi bi-box-arrow-in-down me-2"></i>Inward Supplier Replacement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Scanned Replacement QR Code *</label>
                    <input type="text" name="scanned_qr" id="inwardScannedQr" class="form-control font-monospace" required>
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-bold text-muted text-uppercase">Inward Quantity *</label>
                    <input type="number" step="0.01" min="0.01" name="inward_qty" id="inwardQty" class="form-control font-monospace" required>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning px-4 fw-semibold">Inward to Live Stock</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Cancel Damage -->
<div class="modal fade" id="modalCancelDamage" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= \App\Core\Controller::url('/warehouse/damages/cancel') ?>" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\CSRF::token()) ?>">
            <input type="hidden" name="id" id="cancelDamageId">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Cancel Damage Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <p class="text-secondary small mb-3">
                    Cancelling this record will automatically restore the damaged units back into <code>AVAILABLE</code> live stock.
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Cancellation Reason</label>
                    <textarea name="cancel_reason" rows="2" class="form-control form-control-sm" placeholder="Logged by mistake, unit verified good..." required></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-danger px-4 fw-semibold">Confirm Cancel & Restore</button>
            </div>
        </form>
    </div>
</div>

<script>
function openResolveModal(damage) {
    document.getElementById('resolveDamageId').value = damage.id;
    document.getElementById('resolveReplacementQr').value = 'RPL-' + damage.damage_no;
    new bootstrap.Modal(document.getElementById('modalResolveDamage')).show();
}

function handleResolveActionChange(select) {
    const isRepl = select.value === 'SUPPLIER_REPLACEMENT';
    document.getElementById('resolveReplacementRow').style.display = isRepl ? 'block' : 'none';
}

function openInwardModal(id, qr, qty) {
    document.getElementById('inwardDamageId').value = id;
    document.getElementById('inwardScannedQr').value = qr || '';
    document.getElementById('inwardQty').value = qty || 1;
    new bootstrap.Modal(document.getElementById('modalInwardReplacement')).show();
}

function openCancelModal(id, num) {
    document.getElementById('cancelDamageId').value = id;
    new bootstrap.Modal(document.getElementById('modalCancelDamage')).show();
}
</script>
