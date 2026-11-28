<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Enterprise Asset Management</h4>
            <p class="text-secondary small mb-0">Track fixed equipment, machinery, factory fixtures, and company assets across all concerns.</p>
        </div>
        <?php if (\App\Core\Auth::can('admin_assets', 'add')): ?>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddAsset">
            <i class="bi bi-plus-circle me-1"></i> Add Company Asset
        </button>
        <?php endif; ?>
    </div>

    <!-- Export Intelligence Toolbar -->
    <?php
    $exportTitle = 'Export Asset Register (Excel & PDF)';
    $exportSubtitle = 'Download capital machinery, plant fixtures, equipment valuation & asset audits by date period';
    $exportUrl = \App\Core\Controller::url('/admin/assets/export');
    $prefix = 'assets_exp';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-building-gear me-2 text-primary"></i> Fixed Assets Register</h6>
            <span class="badge bg-light text-dark border"><?= count($assets) ?> Assets Listed</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead>
                    <tr>
                        <th>Asset Code</th>
                        <th>Name & Description</th>
                        <th>Category</th>
                        <th>Assigned Concern</th>
                        <th>Purchase Cost</th>
                        <th>Current Valuation</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assets)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">No company assets registered yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($assets as $a): ?>
                            <tr>
                                <td><span class="badge bg-dark font-monospace"><?= htmlspecialchars($a['asset_code']) ?></span></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($a['name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($a['notes'] ?: '-') ?></small>
                                </td>
                                <td><?= htmlspecialchars($a['category'] ?: 'General') ?></td>
                                <td>
                                    <?php if ($a['concern_name']): ?>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
                                            <?= htmlspecialchars($a['concern_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border">Not a Particular Concern</span>
                                    <?php endif; ?>
                                </td>
                                <td>₹<?= number_format($a['purchase_cost'], 2) ?></td>
                                <td class="fw-bold text-dark">₹<?= number_format($a['current_value'], 2) ?></td>
                                <td><?= htmlspecialchars($a['location'] ?: '-') ?></td>
                                <td>
                                    <span class="badge <?= $a['status'] === 'active' ? 'bg-success' : 'bg-warning' ?>">
                                        <?= ucfirst($a['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <?php if (!empty($a['transfer_pending'])): ?>
                                        <span class="badge bg-warning text-dark" title="Waiting for destination concern to receive this asset">Inward Pending</span>
                                        <?php elseif ($a['status'] === 'active' && \App\Core\Auth::can('outward', 'add')): ?>
                                        <button type="button" class="btn btn-sm btn-outline-success" onclick='openAssetTransferModal(<?= json_encode($a, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Outward Asset">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if (\App\Core\Auth::can('admin_assets', 'view')): ?>
                                        <button type="button" class="btn btn-sm btn-outline-dark" onclick='printAssetLabel(<?= json_encode($a, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Print Asset Label">
                                            <i class="bi bi-printer"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if (\App\Core\Auth::can('admin_assets', 'view')): ?>
                                        <button type="button" class="btn btn-sm btn-outline-info" onclick='openViewAssetModal(<?= json_encode($a, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Asset Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if (\App\Core\Auth::can('admin_assets', 'edit')): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick='openEditAssetModal(<?= json_encode($a, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Asset">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if (\App\Core\Auth::can('admin_assets', 'delete')): ?>
                                        <form action="<?= \App\Core\Controller::url('/admin/assets/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete asset <?= htmlspecialchars(addslashes($a['name'])) ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Asset">
                                                <i class="bi bi-trash"></i>
                                            </button>
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

<!-- Modal: Transfer Asset -->
<div class="modal fade" id="modalAssetTransfer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/admin/assets/outward') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="asset_id" id="asset_transfer_id">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-up-right me-2 text-success"></i>Outward Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border small mb-3">
                    <strong id="asset_transfer_name"></strong>
                    <div class="text-muted" id="asset_transfer_code"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Dispatching Concern *</label>
                    <select class="form-select" name="source_concern_id" id="asset_transfer_source" required>
                        <option value="">Select source concern</option>
                        <?php foreach ($assetTransferSourceConcerns as $con): ?>
                            <option value="<?= (int)$con['id'] ?>"><?= htmlspecialchars($con['name']) ?> (<?= htmlspecialchars($con['code'] ?? '') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">For an unassigned asset, choose the physical concern dispatching it.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Destination Concern *</label>
                    <select class="form-select" name="destination_concern_id" id="asset_transfer_destination" required>
                        <option value="">Select destination concern</option>
                        <?php foreach ($assetTransferConcerns as $con): ?>
                            <option value="<?= (int)$con['id'] ?>"><?= htmlspecialchars($con['name']) ?> (<?= htmlspecialchars($con['code'] ?? '') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Vehicle Number</label>
                    <input type="text" name="vehicle_number" class="form-control" maxlength="100">
                </div>
                <div>
                    <label class="form-label small fw-semibold">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="bi bi-truck me-1"></i>Dispatch Asset</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Add Asset -->
<div class="modal fade" id="modalAddAsset" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/admin/assets/store') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-lg me-1"></i> Register Company Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Asset Code *</label>
                    <input type="text" name="asset_code" id="add_asset_code" class="form-control text-uppercase" value="AST-MCH-<?= str_pad((string)$nextAssetId, 3, '0', STR_PAD_LEFT) ?>" data-next-id="<?= (int)$nextAssetId ?>" readonly required>
                    <small class="text-muted">Generated automatically from the category.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Asset Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Brother Automatic Overlock Machine" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Category</label>
                        <select id="add_asset_category_select" class="form-select">
                            <?php foreach ($assetCategories as $category): ?>
                                <?php $categoryLabel = $category === 'Building' ? 'Building & Fixtures' : $category; ?>
                                <option value="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>" <?= $category === 'Machinery' ? 'selected' : '' ?>><?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                            <option value="__add_new__">+ Add New Category</option>
                        </select>
                        <input type="hidden" name="category" id="add_asset_category" value="Machinery">
                        <div id="add_asset_new_category_wrap" class="mt-2 d-none">
                            <input type="text" id="add_asset_new_category" class="form-control" maxlength="100" placeholder="Enter new category">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Assign Concern</label>
                        <select name="concern_id" class="form-select">
                            <option value="">Not a Particular Concern</option>
                            <?php foreach ($concerns as $con): ?>
                                <option value="<?= $con['id'] ?>"><?= htmlspecialchars($con['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Select this when the asset is not assigned to a specific concern. Other options are active concerns.</small>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Purchase Cost (₹)</label>
                        <input type="number" step="0.01" name="purchase_cost" class="form-control" required placeholder="0.00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Current Value (₹)</label>
                        <input type="number" step="0.01" name="current_value" class="form-control" required placeholder="0.00">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Purchase Date</label>
                        <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Location / Room</label>
                        <input type="text" name="location" class="form-control" placeholder="Floor 1, Bay B">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Maintenance Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Warranty or AMC details"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Asset</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Asset -->
<div class="modal fade" id="modalEditAsset" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/admin/assets/update') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" id="edit_asset_id" value="">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-1 text-primary"></i> Edit Company Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Asset Code *</label>
                    <input type="text" name="asset_code" id="edit_asset_code" class="form-control text-uppercase" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Asset Name *</label>
                    <input type="text" name="name" id="edit_asset_name" class="form-control" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Category</label>
                        <select id="edit_asset_category_select" class="form-select">
                            <?php foreach ($assetCategories as $category): ?>
                                <?php $categoryLabel = $category === 'Building' ? 'Building & Fixtures' : $category; ?>
                                <option value="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                            <option value="__add_new__">+ Add New Category</option>
                        </select>
                        <input type="hidden" name="category" id="edit_asset_category">
                        <div id="edit_asset_new_category_wrap" class="mt-2 d-none">
                            <input type="text" id="edit_asset_new_category" class="form-control" maxlength="100" placeholder="Enter new category">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Assign Concern</label>
                        <select name="concern_id" id="edit_asset_concern_id" class="form-select">
                            <option value="">Not a Particular Concern</option>
                            <?php foreach ($concerns as $con): ?>
                                <option value="<?= $con['id'] ?>"><?= htmlspecialchars($con['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Purchase Cost (₹)</label>
                        <input type="number" step="0.01" name="purchase_cost" id="edit_asset_purchase_cost" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Current Value (₹)</label>
                        <input type="number" step="0.01" name="current_value" id="edit_asset_current_value" class="form-control" required>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Purchase Date</label>
                        <input type="date" name="purchase_date" id="edit_asset_purchase_date" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Location / Room</label>
                        <input type="text" name="location" id="edit_asset_location" class="form-control">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Status *</label>
                        <select name="status" id="edit_asset_status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="disposed">Disposed</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Maintenance Notes</label>
                        <input type="text" name="notes" id="edit_asset_notes" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Asset</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal View Asset -->
<div class="modal fade" id="modalViewAsset" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom px-4 py-3 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span style="width: 36px; height: 36px; background: #e0f2fe; color: #0284c7; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                        <i class="bi bi-box-seam"></i>
                    </span>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0" id="view_asset_name">Asset Details</h6>
                        <small class="badge bg-dark font-monospace" id="view_asset_code"></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center p-3 rounded-3 mb-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                    <div>
                        <span class="text-secondary small d-block">Current Valuation</span>
                        <span class="fw-bold fs-5 text-dark font-monospace" id="view_asset_current_val">₹0.00</span>
                    </div>
                    <div class="text-end">
                        <span class="text-secondary small d-block">Purchase Cost</span>
                        <span class="fw-bold text-secondary font-monospace" id="view_asset_cost">₹0.00</span>
                    </div>
                </div>

                <ul class="list-group list-group-flush small">
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-tag text-primary me-2"></i>Category:</span>
                        <strong class="text-dark" id="view_asset_category">-</strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-building text-primary me-2"></i>Assigned Concern:</span>
                        <span class="badge bg-primary bg-opacity-10 text-primary border" id="view_asset_concern">Not a Particular Concern</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-geo-alt text-primary me-2"></i>Location / Room:</span>
                        <span class="text-dark fw-semibold" id="view_asset_location">-</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-calendar-event text-primary me-2"></i>Purchase Date:</span>
                        <span class="font-monospace text-dark" id="view_asset_date">-</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-shield-check text-primary me-2"></i>Status:</span>
                        <span class="badge" id="view_asset_status">Active</span>
                    </li>
                    <li class="list-group-item px-0">
                        <span class="text-muted d-block mb-1"><i class="bi bi-chat-left-text text-primary me-2"></i>Maintenance Notes:</span>
                        <div class="text-dark p-2 bg-light rounded" id="view_asset_notes">-</div>
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
function openAssetTransferModal(asset) {
    const source = document.getElementById('asset_transfer_source');
    const destination = document.getElementById('asset_transfer_destination');
    document.getElementById('asset_transfer_id').value = asset.id;
    document.getElementById('asset_transfer_name').textContent = asset.name || 'Asset';
    document.getElementById('asset_transfer_code').textContent = asset.asset_code || '';
    source.value = asset.concern_id || '';
    destination.value = '';
    Array.from(destination.options).forEach(option => {
        option.disabled = Boolean(source.value) && option.value === source.value;
    });
    source.onchange = () => {
        Array.from(destination.options).forEach(option => {
            option.disabled = Boolean(source.value) && option.value === source.value;
            if (option.disabled && option.selected) destination.value = '';
        });
    };
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAssetTransfer')).show();
}

function printAssetLabel(asset) {
    if (!asset) return;

    const printWindow = window.open('', '_blank', 'width=420,height=300');
    if (!printWindow) {
        window.alert('Allow pop-ups to print the asset label.');
        return;
    }

    printWindow.document.open();
    printWindow.document.write('<!doctype html><html><head><title>Asset Label</title><style>@page{size:60mm 40mm;margin:0}*{box-sizing:border-box}body{width:60mm;height:40mm;font-family:Arial,sans-serif;margin:0;color:#111}.asset-label{width:60mm;height:40mm;border:1px solid #222;padding:3mm;display:grid;grid-template-columns:22mm minmax(0,1fr);align-items:center;gap:3mm}.asset-qr{width:22mm;height:22mm;display:flex;align-items:center;justify-content:center}.asset-qr canvas,.asset-qr img{width:22mm!important;height:22mm!important}.asset-label-details{min-width:0;display:flex;flex-direction:column;justify-content:center;gap:1.5mm}.asset-code{font-family:monospace;font-size:12pt;font-weight:700;letter-spacing:.2px;border-bottom:1px solid #888;padding-bottom:1mm;overflow-wrap:anywhere}.asset-name{font-size:9pt;font-weight:700;line-height:1.15;overflow-wrap:anywhere}.asset-detail{font-size:7pt;line-height:1.15;overflow-wrap:anywhere}@media screen{body{padding:10px;background:#eee}.asset-label{background:#fff}}</style></head><body></body></html>');
    printWindow.document.close();

    const label = printWindow.document.createElement('main');
    label.className = 'asset-label';
    const details = printWindow.document.createElement('section');
    details.className = 'asset-label-details';
    const addLine = (className, text) => {
        const element = printWindow.document.createElement('div');
        element.className = className;
        element.textContent = text;
        details.appendChild(element);
    };
    addLine('asset-code', asset.asset_code || '');
    addLine('asset-name', asset.name || '');
    addLine('asset-detail', asset.category || 'General');
    addLine('asset-detail', [asset.concern_name || 'Not a Particular Concern', asset.location || ''].filter(Boolean).join(' | '));
    const qr = printWindow.document.createElement('div');
    qr.className = 'asset-qr';
    qr.id = 'asset-label-qr';
    label.append(qr, details);
    printWindow.document.body.appendChild(label);

    const qrScript = printWindow.document.createElement('script');
    qrScript.src = <?= json_encode(\App\Core\Controller::url('/assets/js/qrcode.min.js'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    qrScript.onload = () => {
        if (typeof printWindow.QRCode !== 'function') {
            printWindow.close();
            window.alert('The QR code generator could not be loaded.');
            return;
        }
        new printWindow.QRCode(qr, {
            text: String(asset.id || ''),
            width: 96,
            height: 96,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: printWindow.QRCode.CorrectLevel.M
        });
        window.setTimeout(() => {
            printWindow.focus();
            printWindow.onafterprint = () => printWindow.close();
            printWindow.print();
        }, 100);
    };
    qrScript.onerror = () => {
        printWindow.close();
        window.alert('Could not load the local QR code generator.');
    };
    printWindow.document.head.appendChild(qrScript);
}

function assetCategoryPrefix(category) {
    const knownPrefixes = {
        'MACHINERY': 'MCH',
        'IT EQUIPMENT': 'ITE',
        'VEHICLES': 'VEH',
        'FURNITURE': 'FUR',
        'BUILDING': 'BLD',
        'BUILDING & FIXTURES': 'BLD'
    };
    const normalized = String(category || '').trim().toUpperCase();
    if (knownPrefixes[normalized]) return knownPrefixes[normalized];
    return normalized.replace(/[^A-Z0-9]/g, '').slice(0, 3) || 'GEN';
}

function bindAssetCategorySelect(selectId, hiddenId, wrapId, inputId, onCategoryChange) {
    const select = document.getElementById(selectId);
    const hidden = document.getElementById(hiddenId);
    const wrap = document.getElementById(wrapId);
    const input = document.getElementById(inputId);
    const sync = () => {
        const addingCategory = select.value === '__add_new__';
        wrap.classList.toggle('d-none', !addingCategory);
        input.required = addingCategory;
        hidden.value = addingCategory ? input.value.trim() : select.value;
        if (typeof onCategoryChange === 'function') onCategoryChange(hidden.value);
    };
    select.addEventListener('change', sync);
    input.addEventListener('input', sync);
    sync();
    return sync;
}

function updateAssetCodePreview(category) {
    const input = document.getElementById('add_asset_code');
    const nextId = Number(input.dataset.nextId || 1);
    input.value = 'AST-' + assetCategoryPrefix(category) + '-' + String(nextId).padStart(3, '0');
}

bindAssetCategorySelect(
    'add_asset_category_select',
    'add_asset_category',
    'add_asset_new_category_wrap',
    'add_asset_new_category',
    updateAssetCodePreview
);
bindAssetCategorySelect(
    'edit_asset_category_select',
    'edit_asset_category',
    'edit_asset_new_category_wrap',
    'edit_asset_new_category'
);

function openViewAssetModal(a) {
    if (!a) return;
    document.getElementById('view_asset_name').innerText = a.name || 'Asset';
    document.getElementById('view_asset_code').innerText = a.asset_code || '';
    document.getElementById('view_asset_category').innerText = a.category || 'General';
    document.getElementById('view_asset_concern').innerText = a.concern_name || 'Not a Particular Concern';
    document.getElementById('view_asset_location').innerText = a.location || '-';
    document.getElementById('view_asset_date').innerText = a.purchase_date || '-';
    document.getElementById('view_asset_notes').innerText = a.notes || 'None';
    document.getElementById('view_asset_cost').innerText = '₹' + parseFloat(a.purchase_cost || 0).toLocaleString('en-IN', {minimumFractionDigits: 2});
    document.getElementById('view_asset_current_val').innerText = '₹' + parseFloat(a.current_value || 0).toLocaleString('en-IN', {minimumFractionDigits: 2});

    const statusBadge = document.getElementById('view_asset_status');
    if (a.status === 'active') {
        statusBadge.className = 'badge bg-success';
        statusBadge.innerText = 'Active';
    } else if (a.status === 'maintenance') {
        statusBadge.className = 'badge bg-warning text-dark';
        statusBadge.innerText = 'Maintenance';
    } else {
        statusBadge.className = 'badge bg-secondary';
        statusBadge.innerText = 'Disposed';
    }

    const modal = new bootstrap.Modal(document.getElementById('modalViewAsset'));
    modal.show();
}
function openEditAssetModal(a) {
    if (!a) return;
    document.getElementById('edit_asset_id').value = a.id || '';
    document.getElementById('edit_asset_code').value = a.asset_code || '';
    document.getElementById('edit_asset_name').value = a.name || '';
    const categorySelect = document.getElementById('edit_asset_category_select');
    const category = a.category || 'Machinery';
    if (![...categorySelect.options].some(option => option.value === category)) {
        categorySelect.insertBefore(new Option(category, category), categorySelect.options[categorySelect.options.length - 1]);
    }
    categorySelect.value = category;
    document.getElementById('edit_asset_new_category').value = '';
    categorySelect.dispatchEvent(new Event('change'));
    document.getElementById('edit_asset_concern_id').value = a.concern_id || '';
    document.getElementById('edit_asset_purchase_cost').value = parseFloat(a.purchase_cost || 0).toFixed(2);
    document.getElementById('edit_asset_current_value').value = parseFloat(a.current_value || 0).toFixed(2);
    document.getElementById('edit_asset_purchase_date').value = a.purchase_date || '';
    document.getElementById('edit_asset_location').value = a.location || '';
    document.getElementById('edit_asset_status').value = a.status || 'active';
    document.getElementById('edit_asset_notes').value = a.notes || '';

    const modal = new bootstrap.Modal(document.getElementById('modalEditAsset'));
    modal.show();
}
</script>
