<div class="container-fluid px-0">

    <?php
    $metricCards = [
        ['title' => 'Damaged Units (Quarantine)', 'value' => number_format((float)($metrics['damaged_qty'] ?? 0), 0), 'unit' => 'pcs', 'hint' => 'Click to view logged damage', 'icon' => 'bi-slash-circle', 'tone' => 'danger'],
        ['title' => 'Factory Rework Units', 'value' => number_format((float)($metrics['rework_qty'] ?? 0), 0), 'unit' => 'pcs', 'hint' => 'Sent to factory', 'icon' => 'bi-arrow-repeat', 'tone' => 'warning'],
        ['title' => 'Estimated Loss Valuation', 'value' => '₹' . number_format((float)($metrics['estimated_loss'] ?? 0), 2), 'unit' => '', 'hint' => 'Pending write-off value', 'icon' => 'bi-currency-rupee', 'tone' => 'dark'],
        ['title' => 'Salvaged / Repaired', 'value' => number_format((float)($metrics['recovered_qty'] ?? 0), 0), 'unit' => 'pcs', 'hint' => '₹' . number_format((float)($metrics['recovered_value'] ?? 0), 2) . ' recovered value', 'icon' => 'bi-recycle', 'tone' => 'success'],
    ];
    ?>
    <div class="row g-3 mb-4">
        <?php foreach ($metricCards as $card): ?>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100 damage-metric damage-metric-<?= htmlspecialchars($card['tone']) ?>">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <div class="small fw-semibold text-uppercase text-secondary"><?= htmlspecialchars($card['title']) ?></div>
                            <div class="d-flex align-items-baseline gap-1 mt-1">
                                <span class="fs-4 fw-bold text-dark"><?= htmlspecialchars($card['value']) ?></span>
                                <?php if ($card['unit'] !== ''): ?><span class="small text-muted"><?= htmlspecialchars($card['unit']) ?></span><?php endif; ?>
                            </div>
                            <small class="text-muted"><?= htmlspecialchars($card['hint']) ?></small>
                        </div>
                        <span class="damage-metric-icon"><i class="bi <?= htmlspecialchars($card['icon']) ?>"></i></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Export Toolbar Card -->
    <?php
    $exportTitle = 'Export Concern Damage & Rework Register (Excel, PDF, CSV)';
    $exportSubtitle = 'Download damaged stock tags, factory rework items, loss valuations & scrap resolutions';
    $exportUrl = \App\Core\Controller::url('/m/concern_damage/export');
    $currentModule = 'concern_damage';
    $prefix = 'dmg_exp';
    include dirname(__DIR__) . '/partials/export_card.php';
    ?>

    <style>
        .damage-metric { border-left: 3px solid #dc3545 !important; background: #fff; }
        .damage-metric-warning { border-left-color: #ffc107 !important; background: #fffaf0; }
        .damage-metric-dark { border-left-color: #343a40 !important; background: #f1f3f5; }
        .damage-metric-success { border-left-color: #198754 !important; background: #effaf4; }
        .damage-metric-icon { width: 42px; height: 42px; display: inline-flex; align-items: center; justify-content: center; border-radius: 9px; background: #dc3545; color: #fff; font-size: 1.15rem; }
        .damage-metric-warning .damage-metric-icon { background: #ffc107; color: #212529; }
        .damage-metric-dark .damage-metric-icon { background: #212529; }
        .damage-metric-success .damage-metric-icon { background: #198754; }
        .damage-filter-card { background: #fff; }
        .damage-table-format {
            border-collapse: collapse;
            font-size: 0.84rem;
        }
        .damage-table-format thead th {
            background-color: #f1f5f9 !important;
            color: #475569 !important;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 0.65rem 0.75rem;
            border-bottom: 2px solid #cbd5e1 !important;
            white-space: nowrap;
        }
        .damage-table-format tbody td {
            padding: 0.55rem 0.75rem;
            border-color: #e2e8f0 !important;
            vertical-align: middle;
            white-space: nowrap;
        }
        .damage-table-format tbody tr:hover {
            background-color: #f8fafc;
        }
    </style>

    <div class="card border-0 shadow-sm rounded-3 mb-3 damage-filter-card">
        <div class="card-body p-3">
            <form method="GET" action="<?= \App\Core\Controller::url('/m/concern_damage') ?>" class="row g-2 align-items-end">
                <?php if ($isSuperAdmin): ?>
                    <div class="col-12 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="damageConcernFilter">Concern</label>
                        <select id="damageConcernFilter" name="concern_id" class="form-select form-select-sm">
                            <option value="">All Concerns</option>
                            <?php foreach ($allConcerns as $concern): ?>
                                <option value="<?= (int)$concern['id'] ?>" <?= (int)$selectedCid === (int)$concern['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($concern['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="col-12 col-md">
                    <label class="form-label small fw-semibold mb-1" for="damageSearch">Search Records</label>
                    <input id="damageSearch" type="search" name="search" class="form-control form-control-sm" value="<?= htmlspecialchars($search) ?>" placeholder="Damage #, batch, tag, Product Code...">
                </div>
                <div class="col-6 col-md-auto d-grid">
                    <button type="submit" class="btn btn-sm btn-primary px-4"><i class="bi bi-funnel me-1"></i>Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2.5 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-check text-danger me-2"></i>Damage &amp; Rework Cases</h6>
                <span class="badge bg-light text-dark border"><?= count($damages) ?> Records</span>
            </div>
            <?php if ($canAddDamage): ?>
            <div class="d-flex align-items-center gap-2 flex-nowrap">
                <a href="<?= \App\Core\Controller::url('/m/concern_damage/outward' . ($isSuperAdmin && $selectedCid ? '?concern_id=' . (int)$selectedCid : '')) ?>" class="btn btn-sm btn-outline-danger shadow-xs fw-semibold text-nowrap">
                    <i class="bi bi-truck me-1"></i> Damage Outward
                </a>
                <a href="<?= \App\Core\Controller::url('/m/concern_damage/new' . ($isSuperAdmin && $selectedCid ? '?concern_id=' . (int)$selectedCid : '')) ?>" class="btn btn-sm btn-danger shadow-xs fw-semibold text-nowrap">
                    <i class="bi bi-qr-code-scan me-1"></i> Scan &amp; Log Damage / Rework
                </a>
            </div>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover align-middle mb-0 damage-table-format">
                <thead>
                    <tr>
                        <th>Case</th>
                        <th>Product &amp; Variant</th>
                        <th>Source &amp; Origin Batch</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Loss / Valuation</th>
                        <th class="text-center">Status</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($damages)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No damage or rework records match these filters.</td></tr>
                    <?php else: ?>
                        <?php foreach ($damages as $d):
                            $rowStatus = strtoupper((string)($d['current_status'] ?? 'LOGGED'));
                            $statusClass = match ($rowStatus) {
                                'RESOLVED' => 'bg-success-subtle text-success',
                                'CANCELLED' => 'bg-secondary-subtle text-secondary',
                                'INSPECTED' => 'bg-info-subtle text-info-emphasis',
                                default => 'bg-danger-subtle text-danger',
                            };
                            $originModule = (string)($d['origin_module'] ?? '');
                            $sourceLabel = str_starts_with($originModule, 'Purchase') ? 'PURCHASE STOCK' : (str_starts_with($originModule, 'Production') ? 'OWN PRODUCT' : 'INTERNAL STOCK');
                            $unitPrice = (float)($d['unit_price'] ?: $d['mrp']);
                            $lossValue = (float)($d['loss_amount'] ?? 0) > 0 ? (float)$d['loss_amount'] : (float)$d['qty'] * $unitPrice;
                            $displayProductCode = \App\Services\QrService::formatBrandVariantCode((int)($d['variant_id'] ?? 0)) ?: (string)($d['product_code'] ?? '');
                            $srcRef = !empty($d['origin_ref_id']) ? 'Batch / Ref #' . (int)$d['origin_ref_id'] : 'Concern stock';
                            $reasonStr = (string)($d['reason'] ?: 'No damage reason recorded');
                        ?>
                            <tr>
                                <td><span class="badge bg-danger-subtle text-danger border border-danger-subtle font-monospace"><?= htmlspecialchars($d['damage_no']) ?></span></td>
                                <td>
                                    <div class="text-truncate" style="max-width: 240px;" title="<?= htmlspecialchars($d['variant_name'] . ' | ' . $displayProductCode . ' • ' . ($d['size'] ?: 'Standard')) ?>">
                                        <span class="fw-semibold text-dark"><?= htmlspecialchars($d['variant_name']) ?></span>
                                        <span class="text-muted small font-monospace ms-1">(<?= htmlspecialchars($displayProductCode) ?>)</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 230px;" title="<?= htmlspecialchars($sourceLabel . ' | ' . $srcRef . ' | ' . $reasonStr) ?>">
                                        <span class="badge bg-secondary-subtle text-secondary me-1"><?= htmlspecialchars($sourceLabel) ?></span>
                                        <span class="text-muted small"><?= htmlspecialchars($reasonStr) ?></span>
                                    </div>
                                </td>
                                <td class="text-center"><span class="badge bg-danger-subtle text-danger font-monospace"><?= number_format((float)$d['qty'], 0) ?> pcs</span></td>
                                <td class="text-end font-monospace" title="@ ₹<?= number_format($unitPrice, 2) ?>/pc">
                                    <span class="fw-bold text-dark">₹<?= number_format($lossValue, 2) ?></span>
                                </td>
                                <td class="text-center"><span class="badge rounded-pill <?= $statusClass ?>"><?= htmlspecialchars($rowStatus) ?></span></td>
                                <td title="<?= htmlspecialchars($d['user_name'] ?? 'Staff') ?>">
                                    <span class="small fw-semibold text-dark"><?= date('d M Y', strtotime($d['created_at'])) ?></span>
                                    <span class="text-muted small ms-1">• <?= htmlspecialchars($d['user_name'] ?? 'Staff') ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex flex-nowrap gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick='openViewConcernDamageModal(<?= json_encode($d, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View case">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if ($canEditDamage && in_array($rowStatus, ['LOGGED', 'INSPECTED'], true)): ?>
                                            <?php if (!empty($d['has_quarantine'])): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success" onclick='openResolveConcernDamageModal(<?= json_encode($d, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Resolve / rework">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick='openEditConcernDamageModal(<?= json_encode($d, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit reason">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($canDeleteDamage && in_array($rowStatus, ['LOGGED', 'INSPECTED'], true)): ?>
                                            <form action="<?= \App\Core\Controller::url('/m/concern_damage/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Cancel this case and return its units to available stock?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel and restore stock"><i class="bi bi-x-lg"></i></button>
                                            </form>
                                            <form action="<?= \App\Core\Controller::url('/m/concern_damage/destroy') ?>" method="POST" class="d-inline" onsubmit="return confirm('Permanently delete this damage record? The <?= (float)$d['qty'] ?> unit(s) will be completely deleted from inventory and cannot be restored.');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete completely (discard stock)"><i class="bi bi-trash"></i></button>
                                            </form>
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

<?php if ($canEditDamage): ?>
<div class="modal fade" id="modalEditConcernDamage" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= \App\Core\Controller::url('/m/concern_damage/update') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" id="edit_cdamage_id">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Damage Reason</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small fw-semibold" for="edit_cdamage_reason">Damage reason *</label>
                <textarea name="reason" id="edit_cdamage_reason" class="form-control" rows="3" maxlength="255" required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Reason</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="modalViewConcernDamage" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title fw-bold mb-1">Damage Case Details</h5>
                    <span class="badge bg-danger font-monospace" id="view_cdamage_no"></span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted">Product</dt><dd class="col-7 fw-semibold" id="view_cdamage_variant">-</dd>
                    <dt class="col-5 text-muted">SKU</dt><dd class="col-7 font-monospace" id="view_cdamage_sku">-</dd>
                    <dt class="col-5 text-muted">Location</dt><dd class="col-7" id="view_cdamage_loc">-</dd>
                    <dt class="col-5 text-muted">Quantity</dt><dd class="col-7 text-danger fw-bold" id="view_cdamage_qty">-</dd>
                    <dt class="col-5 text-muted">Logged by</dt><dd class="col-7" id="view_cdamage_user">-</dd>
                    <dt class="col-5 text-muted">Date</dt><dd class="col-7" id="view_cdamage_date">-</dd>
                </dl>
                <hr>
                <div class="small fw-semibold text-muted mb-1">Damage reason</div>
                <div class="p-2 bg-light rounded" id="view_cdamage_reason">-</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php if ($canEditDamage): ?>
<div class="modal fade" id="modalResolveConcernDamage" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= \App\Core\Controller::url('/m/concern_damage/resolve') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" id="resolve_cdamage_id">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-check2-circle me-1"></i> Resolve / Rework Damage</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="resolve_cdamage_action">Resolution action *</label>
                    <select name="resolution_action" id="resolve_cdamage_action" class="form-select" required>
                        <option value="SCRAP">Scrap / write off</option>
                        <option value="SALVAGE">Salvage / clean</option>
                        <option value="REPAIR">Repair</option>
                        <option value="REWORK">Send to factory rework</option>
                        <option value="SUPPLIER_REPLACEMENT">Supplier replacement</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="resolve_cdamage_reason">Resolution notes *</label>
                    <input type="text" name="resolution_reason" id="resolve_cdamage_reason" class="form-control" maxlength="255" required>
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-semibold" for="resolve_cdamage_loss">Loss amount (₹)</label>
                    <input type="number" name="loss_amount" id="resolve_cdamage_loss" class="form-control" min="0" step="0.01" value="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Confirm Resolution</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function openViewConcernDamageModal(d) {
    if (!d) return;
    document.getElementById('view_cdamage_no').textContent = d.damage_no || '';
    document.getElementById('view_cdamage_variant').textContent = d.variant_name || '-';
    document.getElementById('view_cdamage_sku').textContent = d.product_code || '-';
    document.getElementById('view_cdamage_loc').textContent = d.location || '-';
    document.getElementById('view_cdamage_qty').textContent = `${Number(d.qty || 0).toLocaleString('en-IN')} pcs`;
    document.getElementById('view_cdamage_user').textContent = d.user_name || 'Staff';
    document.getElementById('view_cdamage_date').textContent = d.created_at || '-';
    document.getElementById('view_cdamage_reason').textContent = d.reason || '-';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalViewConcernDamage')).show();
}

function openEditConcernDamageModal(d) {
    if (!d) return;
    document.getElementById('edit_cdamage_id').value = d.id || '';
    document.getElementById('edit_cdamage_reason').value = d.reason || '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditConcernDamage')).show();
}

function openResolveConcernDamageModal(d) {
    if (!d) return;
    document.getElementById('resolve_cdamage_id').value = d.id || '';
    document.getElementById('resolve_cdamage_reason').value = d.reason || '';
    document.getElementById('resolve_cdamage_loss').value = Number(d.loss_amount || 0).toFixed(2);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalResolveConcernDamage')).show();
}
</script>
