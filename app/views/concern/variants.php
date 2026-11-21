<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Product Variants Catalog</h4>
            <p class="text-secondary small mb-0">Master catalog holding all attributes (size, weight, fabric, GSM, prices) with strict concern isolation.</p>
        </div>
        <?php if (\App\Core\Auth::can('variants', 'add')): ?>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddVariant">
            <i class="bi bi-plus-circle me-1"></i> Add New Variant
        </button>
        <?php endif; ?>
    </div>

    <!-- Export Intelligence Toolbar -->
    <?php
    $exportTitle = 'Export Product Variants Catalog (Excel & PDF)';
    $exportSubtitle = 'Download master SKU catalog, size specifications, pricing benchmarks & product hierarchy';
    $exportUrl = \App\Core\Controller::url('/m/variants/export');
    $prefix = 'var_exp';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-tags me-2 text-primary"></i> Master Variants Register</h6>
            <span class="badge bg-light text-dark border"><?= count($variants) ?> Variants</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead>
                    <tr>
                        <th>SKU Code</th>
                        <?php if ($isSuperAdmin && empty($selectedCid)): ?>
                            <th>Concern</th>
                        <?php endif; ?>
                        <th>Variant Details</th>
                        <th>Category / Subcategory</th>
                        <th>Brand / Size</th>
                        <th>Standard Price / MRP</th>
                        <th>Attributes (JSON)</th>
                        <th>Live Stock</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($variants)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">No variants created yet. Click "Add New Variant" above.</td></tr>
                    <?php else: ?>
                        <?php foreach ($variants as $v): 
                            $attrs = !empty($v['attributes']) ? json_decode($v['attributes'], true) : [];
                            $qrPayload = \App\Services\QrService::generatePayload($v['category_id'], $v['subcategory_id'], $v['id']);
                        ?>
                            <tr>
                                <td>
                                    <span class="badge bg-dark font-monospace"><?= htmlspecialchars($v['sku_code']) ?></span>
                                    <div class="text-muted small font-monospace mt-1" style="font-size: 0.68rem;">ID: #<?= (int)$v['id'] ?></div>
                                </td>
                                <?php if ($isSuperAdmin && empty($selectedCid)): ?>
                                    <td>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
                                            <?= htmlspecialchars($v['concern_name'] ?? 'Concern #' . $v['concern_id']) ?>
                                        </span>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($v['name']) ?></div>
                                    <small class="text-muted"><?= number_format($v['weight'], 3) ?> <?= htmlspecialchars($v['weight_unit']) ?></small>
                                </td>
                                <td>
                                    <div class="text-dark small fw-medium"><?= htmlspecialchars($v['category_name'] ?? 'Uncategorized') ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($v['subcategory_name'] ?? '-') ?></div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($v['brand_name'] ?? '-') ?></div>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($v['size'] ?: 'N/A') ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">₹<?= number_format($v['price'], 2) ?></div>
                                    <small class="text-muted text-decoration-line-through">₹<?= number_format($v['mrp'], 2) ?></small>
                                </td>
                                <td>
                                    <?php if (!empty($attrs)): ?>
                                        <?php foreach ($attrs as $k => $val): ?>
                                            <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.7rem;">
                                                <?= htmlspecialchars($k) ?>: <?= htmlspecialchars($val) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-6 px-2 py-1">
                                        <?= number_format($v['total_stock'], 2) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-info" onclick='openViewVariantModal(<?= json_encode($v, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Variant Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <a href="<?= \App\Core\Controller::url('/m/label_generation?variant_id=' . $v['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Print Labels">
                                            <i class="bi bi-qr-code"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick='openEditVariantModal(<?= json_encode($v, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Variant">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="<?= \App\Core\Controller::url('/m/variants/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete variant <?= htmlspecialchars(addslashes($v['name'])) ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Variant">
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

<!-- Modal Add Variant -->
<div class="modal fade" id="modalAddVariant" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="<?= \App\Core\Controller::url('/m/variants/store') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-1 text-primary"></i> Create Master Product Variant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if ($isSuperAdmin && empty($selectedCid)): ?>
                    <div class="mb-3 p-2 bg-light border rounded">
                        <label class="form-label small fw-bold text-primary">Assign To Concern *</label>
                        <select name="concern_id" class="form-select" required>
                            <option value="">-- Choose Concern --</option>
                            <?php foreach ($allConcerns as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">SKU Code *</label>
                        <input type="text" name="sku_code" class="form-control text-uppercase" placeholder="e.g. SKU-TSH-001" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold">Product Variant Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Bio-Washed Combed Cotton T-Shirt" required>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Category *</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Subcategory *</label>
                        <select name="subcategory_id" class="form-select" required>
                            <option value="">Select Subcategory</option>
                            <?php foreach ($subcategories as $sc): ?>
                                <option value="<?= $sc['id'] ?>"><?= htmlspecialchars($sc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Brand</label>
                        <select name="brand_id" class="form-select">
                            <option value="">No Brand</option>
                            <?php foreach ($brands as $br): ?>
                                <option value="<?= $br['id'] ?>"><?= htmlspecialchars($br['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Size</label>
                        <input type="text" name="size" class="form-control" placeholder="M, L, XL, Free">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Unit Weight</label>
                        <input type="number" step="0.001" name="weight" class="form-control" placeholder="0.000">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Weight Unit</label>
                        <select name="weight_unit" class="form-select">
                            <option value="pcs">Pieces (pcs)</option>
                            <option value="kg">Kilograms (kg)</option>
                            <option value="meter">Meters</option>
                            <option value="roll">Rolls</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Creation Date</label>
                        <input type="date" name="creation_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Base Price (₹) *</label>
                        <input type="number" step="0.01" name="price" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">MRP (₹) *</label>
                        <input type="number" step="0.01" name="mrp" class="form-control" placeholder="0.00" required>
                    </div>
                </div>

                <h6 class="fw-bold small text-secondary mt-3 mb-2">Dynamic JSON Attributes:</h6>
                <div class="row g-2 p-3 bg-light rounded border">
                    <div class="col-md-4">
                        <label class="form-label small">Color</label>
                        <input type="text" name="attr_color" class="form-control form-control-sm" placeholder="e.g. Navy Blue">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Fabric / Material</label>
                        <input type="text" name="attr_fabric" class="form-control form-control-sm" placeholder="e.g. 100% Cotton">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">GSM / Density</label>
                        <input type="text" name="attr_gsm" class="form-control form-control-sm" placeholder="e.g. 180 GSM">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Variant</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Variant -->
<div class="modal fade" id="modalEditVariant" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="<?= \App\Core\Controller::url('/m/variants/update') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" id="edit_variant_id" value="">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-1 text-primary"></i> Edit Product Variant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">SKU Code *</label>
                        <input type="text" name="sku_code" id="edit_variant_sku" class="form-control text-uppercase" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold">Variant / Item Name *</label>
                        <input type="text" name="name" id="edit_variant_name" class="form-control" required>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Category</label>
                        <select name="category_id" id="edit_variant_category" class="form-select">
                            <option value="">Uncategorized</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Brand</label>
                        <select name="brand_id" id="edit_variant_brand" class="form-select">
                            <option value="">No Brand</option>
                            <?php foreach ($brands as $br): ?>
                                <option value="<?= $br['id'] ?>"><?= htmlspecialchars($br['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Size</label>
                        <input type="text" name="size" id="edit_variant_size" class="form-control" placeholder="e.g. XL, 32, Free Size">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Base Price (₹) *</label>
                        <input type="number" step="0.01" name="price" id="edit_variant_price" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">MRP (₹) *</label>
                        <input type="number" step="0.01" name="mrp" id="edit_variant_mrp" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Status *</label>
                        <select name="status" id="edit_variant_status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Unit Weight</label>
                        <input type="number" step="0.001" name="weight" id="edit_variant_weight" class="form-control" placeholder="0.000">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Weight Unit</label>
                        <select name="weight_unit" id="edit_variant_weight_unit" class="form-select">
                            <option value="pcs">Pieces (pcs)</option>
                            <option value="kg">Kilograms (kg)</option>
                            <option value="mtr">Meters (mtr)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Variant</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal View Variant -->
<div class="modal fade" id="modalViewVariant" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom px-4 py-3 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span style="width: 36px; height: 36px; background: #e0f2fe; color: #0284c7; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                        <i class="bi bi-tag"></i>
                    </span>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0" id="view_variant_name">Variant Details</h6>
                        <small class="badge bg-dark font-monospace" id="view_variant_sku"></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center p-3 rounded-3 mb-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                    <div>
                        <span class="text-secondary small d-block">Base Price</span>
                        <span class="fw-bold fs-5 text-primary font-monospace" id="view_variant_price">₹0.00</span>
                    </div>
                    <div class="text-center">
                        <span class="text-secondary small d-block">MRP</span>
                        <span class="fw-bold text-muted font-monospace" id="view_variant_mrp">₹0.00</span>
                    </div>
                    <div class="text-end">
                        <span class="text-secondary small d-block">Live Stock</span>
                        <span class="badge bg-success bg-opacity-10 text-success border fs-6" id="view_variant_stock">0 Pcs</span>
                    </div>
                </div>

                <ul class="list-group list-group-flush small">
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Category:</span>
                        <strong class="text-dark" id="view_variant_category">-</strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Brand:</span>
                        <strong class="text-dark" id="view_variant_brand">-</strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Size:</span>
                        <span class="badge bg-light text-dark border" id="view_variant_size">-</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Unit Weight:</span>
                        <span class="text-dark" id="view_variant_weight">-</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Status:</span>
                        <span class="badge" id="view_variant_status">Active</span>
                    </li>
                </ul>
            </div>
            <div class="modal-footer border-top px-4 py-2 bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="view_variant_label_link" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-qr-code me-1"></i> Print Barcode
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function openViewVariantModal(v) {
    if (!v) return;
    document.getElementById('view_variant_name').innerText = v.name || 'Variant';
    document.getElementById('view_variant_sku').innerText = v.sku_code || '';
    document.getElementById('view_variant_category').innerText = v.category_name || 'Uncategorized';
    document.getElementById('view_variant_brand').innerText = v.brand_name || 'No Brand';
    document.getElementById('view_variant_size').innerText = v.size || 'N/A';
    document.getElementById('view_variant_price').innerText = '₹' + parseFloat(v.price || 0).toLocaleString('en-IN', {minimumFractionDigits: 2});
    document.getElementById('view_variant_mrp').innerText = '₹' + parseFloat(v.mrp || 0).toLocaleString('en-IN', {minimumFractionDigits: 2});
    document.getElementById('view_variant_stock').innerText = parseFloat(v.total_stock || 0).toLocaleString('en-IN') + ' ' + (v.weight_unit || 'pcs');
    document.getElementById('view_variant_weight').innerText = parseFloat(v.weight || 0).toFixed(3) + ' ' + (v.weight_unit || 'pcs');
    document.getElementById('view_variant_label_link').href = '<?= \App\Core\Controller::url('/m/label_generation?variant_id=') ?>' + v.id;

    const statusBadge = document.getElementById('view_variant_status');
    if (v.status === 'active') {
        statusBadge.className = 'badge bg-success';
        statusBadge.innerText = 'Active';
    } else {
        statusBadge.className = 'badge bg-secondary';
        statusBadge.innerText = 'Inactive';
    }

    const modal = new bootstrap.Modal(document.getElementById('modalViewVariant'));
    modal.show();
}
function openEditVariantModal(v) {
    if (!v) return;
    document.getElementById('edit_variant_id').value = v.id || '';
    document.getElementById('edit_variant_sku').value = v.sku_code || '';
    document.getElementById('edit_variant_name').value = v.name || '';
    document.getElementById('edit_variant_category').value = v.category_id || '';
    document.getElementById('edit_variant_brand').value = v.brand_id || '';
    document.getElementById('edit_variant_size').value = v.size || '';
    document.getElementById('edit_variant_price').value = parseFloat(v.price || 0).toFixed(2);
    document.getElementById('edit_variant_mrp').value = parseFloat(v.mrp || 0).toFixed(2);
    document.getElementById('edit_variant_weight').value = parseFloat(v.weight || 0).toFixed(3);
    document.getElementById('edit_variant_weight_unit').value = v.weight_unit || 'pcs';
    document.getElementById('edit_variant_status').value = v.status || 'active';

    const modal = new bootstrap.Modal(document.getElementById('modalEditVariant'));
    modal.show();
}
</script>
