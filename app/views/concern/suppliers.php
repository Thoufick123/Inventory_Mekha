<?php
$canAdd = \App\Core\Auth::can('suppliers', 'add');
$canEdit = \App\Core\Auth::can('suppliers', 'edit');
$canDelete = \App\Core\Auth::can('suppliers', 'delete');
?>
<div class="container-fluid px-0">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Supplier Directory</h4>
        </div>
        <?php if ($canAdd): ?>
        <button class="btn btn-primary shadow-sm rounded-2 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalAddSupplier">
            <i class="bi bi-plus-circle fs-6"></i> Add Supplier
        </button>
        <?php endif; ?>
    </div>

    <!-- Export Intelligence Toolbar -->
    <?php
    $exportTitle = '';
    $exportSubtitle = '';
    $exportUrl = \App\Core\Controller::url('/m/suppliers/export');
    $prefix = 'sup_exp';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <!-- Quick Stats Summary Cards -->
    <?php
    $totalCount = count($suppliers);
    $activeCount = 0;
    $tnCount = 0;
    $otherStateCount = 0;
    foreach ($suppliers as $s) {
        if (($s['status'] ?? 'active') === 'active') $activeCount++;
        if (stripos($s['state'] ?? '', 'Tamil') !== false) {
            $tnCount++;
        } else {
            $otherStateCount++;
        }
    }
    ?>
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Total Suppliers</span>
                        <h4 class="fw-bold mb-0 text-dark mt-1"><?= $totalCount ?></h4>
                    </div>
                    <div class="rounded-circle p-3 bg-primary-subtle text-primary">
                        <i class="bi bi-truck fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Active Vendors</span>
                        <h4 class="fw-bold mb-0 text-success mt-1"><?= $activeCount ?></h4>
                    </div>
                    <div class="rounded-circle p-3 bg-success-subtle text-success">
                        <i class="bi bi-check-circle fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">In-State (TN)</span>
                        <h4 class="fw-bold mb-0 text-primary mt-1"><?= $tnCount ?></h4>
                    </div>
                    <div class="rounded-circle p-3 bg-info-subtle text-info">
                        <i class="bi bi-geo-alt fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Inter-State</span>
                        <h4 class="fw-bold mb-0 text-secondary mt-1"><?= $otherStateCount ?></h4>
                    </div>
                    <div class="rounded-circle p-3 bg-secondary-subtle text-secondary">
                        <i class="bi bi-globe fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Suppliers Table Card with Live Filter -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-building text-primary fs-5"></i>
                <h6 class="mb-0 fw-bold text-dark">Registered Suppliers Directory</h6>
                <span class="badge bg-light text-dark border ms-1" id="supplierBadgeCount"><?= $totalCount ?> Suppliers</span>
            </div>
            <!-- Search & Filter Controls -->
            <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="width: 250px;">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="supplierSearchInput" class="form-control border-start-0" placeholder="Search suppliers, phone, GST...">
                </div>
                <select id="supplierDistrictFilter" class="form-select form-select-sm" style="width: 150px;">
                    <option value="">All Districts</option>
                    <?php
                    $districts = array_unique(array_filter(array_column($suppliers, 'district')));
                    sort($districts);
                    foreach ($districts as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom" id="tableSuppliers">
                <thead class="table-light text-muted small">
                    <tr>
                        <th style="width: 45px;" class="ps-3">#</th>
                        <th>Supplier & Mill</th>
                        <th>Contact Details</th>
                        <th>GSTIN</th>
                        <th>Location & Address</th>
                        <th class="text-center" style="width: 100px;">Status</th>
                        <th class="text-end pe-3" style="width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="supplierTableBody">
                    <?php if (empty($suppliers)): ?>
                        <tr id="noSuppliersRow"><td colspan="7" class="text-center py-4 text-muted">No suppliers registered.<?= $canAdd ? ' Click "Add Supplier" to register one.' : '' ?></td></tr>
                    <?php else: ?>
                        <?php $idx = 1; foreach ($suppliers as $sup): ?>
                            <tr class="supplier-row"
                                data-name="<?= strtolower(htmlspecialchars($sup['name'])) ?>"
                                data-company="<?= strtolower(htmlspecialchars($sup['company_name'] ?? '')) ?>"
                                data-code="<?= strtolower(htmlspecialchars($sup['code'] ?? '')) ?>"
                                data-phone="<?= htmlspecialchars($sup['phone'] ?? '') ?>"
                                data-email="<?= strtolower(htmlspecialchars($sup['email'] ?? '')) ?>"
                                data-gstin="<?= strtolower(htmlspecialchars($sup['gstin'] ?? '')) ?>"
                                data-district="<?= strtolower(htmlspecialchars($sup['district'] ?? ($sup['city'] ?? ''))) ?>"
                                data-state="<?= strtolower(htmlspecialchars($sup['state'] ?? '')) ?>"
                                data-address="<?= strtolower(htmlspecialchars($sup['address'] ?? '')) ?>">
                                <td class="text-muted small ps-3"><?= $idx++ ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle p-2 bg-primary-subtle text-primary flex-shrink-0" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                                            <i class="bi bi-building fs-6"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark d-flex align-items-center gap-2 flex-wrap">
                                                <span><?= htmlspecialchars($sup['company_name'] ?: $sup['name']) ?></span>
                                                <span class="badge bg-dark font-monospace" style="font-size: 0.72rem;"><?= htmlspecialchars($sup['code'] ?: 'SUP') ?></span>
                                            </div>
                                            <div class="small text-muted d-flex align-items-center gap-2 mt-1 flex-wrap">
                                                <span><i class="bi bi-person text-secondary me-1"></i><?= htmlspecialchars($sup['name']) ?></span>
                                                <?php if (empty($selectedCid)): ?>
                                                    <span class="badge bg-secondary-subtle text-secondary border py-0 px-1" style="font-size: 0.7rem;">
                                                        <?= htmlspecialchars($sup['concern_name'] ?? ('Concern #' . $sup['concern_id'])) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($sup['phone'])): ?>
                                        <a href="tel:<?= htmlspecialchars($sup['phone']) ?>" class="text-decoration-none text-dark fw-semibold small d-flex align-items-center gap-1">
                                            <i class="bi bi-telephone-fill text-primary" style="font-size: 0.8rem;"></i>
                                            <span><?= htmlspecialchars($sup['phone']) ?></span>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>

                                    <?php if (!empty($sup['email'])): ?>
                                        <a href="mailto:<?= htmlspecialchars($sup['email']) ?>" class="text-decoration-none text-secondary small d-flex align-items-center gap-1 mt-1 text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($sup['email']) ?>">
                                            <i class="bi bi-envelope text-muted" style="font-size: 0.85rem;"></i>
                                            <span class="text-truncate"><?= htmlspecialchars($sup['email']) ?></span>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($sup['gstin'])): ?>
                                        <span class="badge bg-light text-dark border font-monospace px-2 py-1" style="font-size: 0.8rem; letter-spacing: 0.5px;">
                                            <?= htmlspecialchars($sup['gstin']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic">Unregistered</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark small d-flex align-items-center gap-1">
                                        <i class="bi bi-geo-alt-fill text-danger" style="font-size: 0.8rem;"></i>
                                        <span><?= htmlspecialchars($sup['district'] ?: ($sup['city'] ?: '-')) ?></span>
                                        <span class="badge bg-light text-secondary border ms-1 py-0 px-1" style="font-size: 0.7rem; font-weight: normal;"><?= htmlspecialchars($sup['state'] ?: '-') ?></span>
                                    </div>
                                    <div class="small text-muted text-truncate mt-1" style="max-width: 220px;" title="<?= htmlspecialchars($sup['address'] ?: '') ?>">
                                        <?= htmlspecialchars($sup['address'] ?: '-') ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if (($sup['status'] ?? 'active') === 'active'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success px-2 py-1">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border px-2 py-1">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <!-- CRUD & View Action Buttons -->
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-info py-1 px-2"
                                                title="View Supplier Details"
                                                onclick="openViewSupplierModal(<?= htmlspecialchars(json_encode($sup)) ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if ($canEdit): ?>
                                        <button type="button" class="btn btn-outline-primary py-1 px-2"
                                                title="Edit Supplier"
                                                onclick="openEditSupplierModal(<?= htmlspecialchars(json_encode($sup)) ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($canDelete): ?>
                                        <button type="button" class="btn btn-outline-danger py-1 px-2"
                                                title="Delete Supplier"
                                                onclick="openDeleteSupplierModal(<?= (int)$sup['id'] ?>, '<?= htmlspecialchars(addslashes($sup['name'])) ?>', '<?= htmlspecialchars(addslashes($sup['company_name'] ?? '')) ?>')">
                                            <i class="bi bi-trash3"></i>
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

<?php if ($canAdd): ?>
<!-- Modal 1: Register New Supplier (Create) -->
<div class="modal fade" id="modalAddSupplier" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= \App\Core\Controller::url('/m/suppliers/store') ?>" method="POST" class="modal-content needs-validation" id="formAddSupplier" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold text-dark mb-0">Register New Supplier</h5>
                    <small class="text-muted">Fill in all required details to onboard a new vendor</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (empty($selectedCid)): ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Target Concern <span class="text-danger">*</span></label>
                        <select name="concern_id" id="supplierConcernId" class="form-select" required>
                            <option value="">-- Choose Target Concern --</option>
                            <?php foreach ($allConcerns ?? [] as $con): ?>
                                <option value="<?= $con['id'] ?>"><?= htmlspecialchars($con['name']) ?> (<?= htmlspecialchars($con['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please choose the target business concern.</div>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Contact Person / Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="supplierName" class="form-control" required minlength="2" maxlength="150" placeholder="e.g. Ramesh Kumar">
                    <div class="invalid-feedback">Contact person name is required (at least 2 letters).</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Company / Mill Name <span class="text-danger">*</span></label>
                    <input type="text" name="company_name" id="supplierCompany" class="form-control" required minlength="2" maxlength="150" placeholder="e.g. VIJA ENTERPRISES / Sri Balaji Textiles">
                    <div class="invalid-feedback">Company or Mill name is required.</div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Phone <span class="text-danger">*</span></label>
                        <div class="input-group has-validation">
                            <span class="input-group-text small bg-light text-muted">+91</span>
                            <input type="tel" name="phone" id="supplierPhone" class="form-control" required pattern="^[6-9][0-9]{9}$" maxlength="10" placeholder="9842100000">
                            <div class="invalid-feedback">10-digit Indian mobile (starts with 6-9).</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">GSTIN</label>
                        <input type="text" name="gstin" id="supplierGstin" class="form-control text-uppercase font-monospace" pattern="^[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}[1-9A-Za-z]{1}[Zz][0-9A-Za-z]{1}$" maxlength="15" placeholder="33AAAAA0000A1Z5">
                        <div class="invalid-feedback">15-char GST format (e.g. 33AAAAA0000A1Z5).</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Email Address <span class="text-muted small fw-normal">(Optional)</span></label>
                    <input type="email" name="email" id="supplierEmail" class="form-control" placeholder="vendor@mill.com">
                    <div class="invalid-feedback">Please enter a valid email address if provided.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Address / Street <span class="text-danger">*</span></label>
                    <textarea name="address" id="supplierAddress" class="form-control" rows="2" required minlength="3" placeholder="Door No, Street Name, Area (e.g. Valvarmangalam)"></textarea>
                    <div class="invalid-feedback">Address is required (at least 3 characters).</div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">District <span class="text-danger">*</span></label>
                        <input type="text" name="district" id="supplierDistrict" class="form-control" required minlength="2" placeholder="e.g. Karur / Tirupur">
                        <div class="invalid-feedback">District name is required.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">State <span class="text-danger">*</span></label>
                        <input type="text" name="state" id="supplierState" class="form-control" required minlength="2" placeholder="Enter state">
                        <div class="invalid-feedback">Please enter the state.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4 fw-semibold" id="btnSubmitSupplier">
                    <i class="bi bi-save me-1"></i> Save Supplier
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canEdit): ?>
<!-- Modal 2: Edit Supplier (Update) -->
<div class="modal fade" id="modalEditSupplier" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= \App\Core\Controller::url('/m/suppliers/update') ?>" method="POST" class="modal-content needs-validation" id="formEditSupplier" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="supplier_id" id="editSupplierId" value="">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title fw-bold text-dark mb-0">
                        <i class="bi bi-pencil-square text-primary me-1"></i> Edit Supplier Details
                    </h5>
                    <small class="text-muted" id="editSupplierCodeBadge"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Contact Person / Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="editSupplierName" class="form-control" required minlength="2" maxlength="150">
                    <div class="invalid-feedback">Contact person name is required.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Company / Mill Name <span class="text-danger">*</span></label>
                    <input type="text" name="company_name" id="editSupplierCompany" class="form-control" required minlength="2" maxlength="150">
                    <div class="invalid-feedback">Company or Mill name is required.</div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Phone <span class="text-danger">*</span></label>
                        <div class="input-group has-validation">
                            <span class="input-group-text small bg-light text-muted">+91</span>
                            <input type="tel" name="phone" id="editSupplierPhone" class="form-control" required pattern="^[6-9][0-9]{9}$" maxlength="10">
                            <div class="invalid-feedback">10-digit Indian mobile (starts with 6-9).</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">GSTIN</label>
                        <input type="text" name="gstin" id="editSupplierGstin" class="form-control text-uppercase font-monospace" pattern="^[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}[1-9A-Za-z]{1}[Zz][0-9A-Za-z]{1}$" maxlength="15">
                        <div class="invalid-feedback">15-char GST format.</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Email Address <span class="text-muted small fw-normal">(Optional)</span></label>
                    <input type="email" name="email" id="editSupplierEmail" class="form-control" placeholder="vendor@mill.com">
                    <div class="invalid-feedback">Please enter a valid email address if provided.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Address / Street <span class="text-danger">*</span></label>
                    <textarea name="address" id="editSupplierAddress" class="form-control" rows="2" required minlength="3"></textarea>
                    <div class="invalid-feedback">Address is required.</div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">District <span class="text-danger">*</span></label>
                        <input type="text" name="district" id="editSupplierDistrict" class="form-control" required minlength="2">
                        <div class="invalid-feedback">District is required.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">State <span class="text-danger">*</span></label>
                        <input type="text" name="state" id="editSupplierState" class="form-control" required minlength="2" placeholder="Enter state">
                        <div class="invalid-feedback">State is required.</div>
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label small fw-semibold">Status</label>
                    <select name="status" id="editSupplierStatus" class="form-select">
                        <option value="active">Active (Available for Purchase Billing)</option>
                        <option value="inactive">Inactive (Archived)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4 fw-semibold">
                    <i class="bi bi-check-circle me-1"></i> Update Supplier
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canDelete): ?>
<!-- Modal 3: Delete Supplier Confirmation (Delete) -->
<div class="modal fade" id="modalDeleteSupplier" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= \App\Core\Controller::url('/m/suppliers/delete') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="supplier_id" id="deleteSupplierId" value="">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i> Delete Supplier
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <p class="mb-1 text-dark">Are you sure you want to delete supplier <strong id="deleteSupplierName" class="text-dark"></strong>?</p>
                <small class="text-muted d-block">
                    If this supplier has recorded purchase bills, their status will be safely changed to <strong>Inactive</strong> to preserve financial audit history without deleting accounting records.
                </small>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger px-4 fw-semibold">
                    <i class="bi bi-trash3 me-1"></i> Confirm Delete
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Modal 4: View Supplier Details -->
<div class="modal fade" id="modalViewSupplier" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom bg-light">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle p-2 bg-primary-subtle text-primary" style="width: 44px; height: 44px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-building fs-4"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h5 class="modal-title fw-bold text-dark mb-0" id="viewSupplierCompany"></h5>
                            <span class="badge" id="viewSupplierStatusBadge"></span>
                        </div>
                        <small class="text-muted" id="viewSupplierSubtitle"></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <!-- Column 1: Primary Vendor & Contact Info -->
                    <div class="col-md-6">
                        <div class="card h-100 border border-light-subtle rounded-3 shadow-none bg-light-subtle p-3">
                            <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                <i class="bi bi-person-lines-fill text-primary"></i> Contact & Representative
                            </h6>
                            <div class="mb-2">
                                <label class="text-muted small fw-semibold d-block">Contact Person</label>
                                <span class="fw-bold text-dark" id="viewSupplierContact"></span>
                            </div>
                            <div class="mb-2">
                                <label class="text-muted small fw-semibold d-block">Phone Number</label>
                                <a href="#" id="viewSupplierPhoneLink" class="text-decoration-none fw-semibold text-primary d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-telephone-fill"></i>
                                    <span id="viewSupplierPhone"></span>
                                </a>
                            </div>
                            <div class="mb-2">
                                <label class="text-muted small fw-semibold d-block">Email Address</label>
                                <a href="#" id="viewSupplierEmailLink" class="text-decoration-none text-dark d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-envelope-fill text-secondary"></i>
                                    <span id="viewSupplierEmail"></span>
                                </a>
                            </div>
                            <div id="viewSupplierConcernWrapper" class="mt-2 pt-2 border-top">
                                <label class="text-muted small fw-semibold d-block">Operating Business Concern</label>
                                <span class="badge bg-secondary-subtle text-secondary border mt-1" id="viewSupplierConcern"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Column 2: Tax & Statutory Details -->
                    <div class="col-md-6">
                        <div class="card h-100 border border-light-subtle rounded-3 shadow-none bg-light-subtle p-3">
                            <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                <i class="bi bi-shield-check text-success"></i> Tax & GST Registration
                            </h6>
                            <div class="mb-2">
                                <label class="text-muted small fw-semibold d-block">GSTIN (15-Character)</label>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <span class="badge bg-white text-dark border font-monospace px-2 py-1 fs-6" id="viewSupplierGstin"></span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Copy GSTIN" onclick="copyViewGstin()">
                                        <i class="bi bi-clipboard"></i> Copy
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Column 3: Full Address Details (Full Width) -->
                    <div class="col-12">
                        <div class="card border border-light-subtle rounded-3 shadow-none bg-light-subtle p-3">
                            <h6 class="fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                                <i class="bi bi-geo-alt-fill text-danger"></i> Factory / Mill & Billing Address
                            </h6>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="text-muted small fw-semibold d-block">Street / Door No / Area</label>
                                    <div class="text-dark fw-medium" id="viewSupplierAddress"></div>
                                </div>
                                <div class="col-md-3">
                                    <label class="text-muted small fw-semibold d-block">District / City</label>
                                    <div class="text-dark fw-bold" id="viewSupplierDistrict"></div>
                                </div>
                                <div class="col-md-3">
                                    <label class="text-muted small fw-semibold d-block">State</label>
                                    <div class="text-dark fw-bold" id="viewSupplierState"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light justify-content-end">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <?php if ($canEdit): ?>
                    <button type="button" class="btn btn-primary fw-semibold" id="btnEditFromView">
                        <i class="bi bi-pencil me-1"></i> Edit Supplier
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Filter Suppliers Table
document.addEventListener('DOMContentLoaded', function () {
    ['formAddSupplier', 'formEditSupplier'].forEach(function (formId) {
        const form = document.getElementById(formId);
        if (!form) return;

        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    const searchInput = document.getElementById('supplierSearchInput');
    const districtFilter = document.getElementById('supplierDistrictFilter');
    const rows = document.querySelectorAll('.supplier-row');
    const badgeCount = document.getElementById('supplierBadgeCount');

    function filterTable() {
        const query = (searchInput.value || '').trim().toLowerCase();
        const district = (districtFilter.value || '').trim().toLowerCase();
        let visibleCount = 0;

        rows.forEach(function (row) {
            const name = row.dataset.name || '';
            const company = row.dataset.company || '';
            const code = row.dataset.code || '';
            const phone = row.dataset.phone || '';
            const email = row.dataset.email || '';
            const gstin = row.dataset.gstin || '';
            const rowDistrict = row.dataset.district || '';
            const address = row.dataset.address || '';

            const matchesQuery = !query ||
                name.includes(query) ||
                company.includes(query) ||
                code.includes(query) ||
                phone.includes(query) ||
                email.includes(query) ||
                gstin.includes(query) ||
                rowDistrict.includes(query) ||
                address.includes(query);

            const matchesDistrict = !district || rowDistrict === district;

            if (matchesQuery && matchesDistrict) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (badgeCount) {
            badgeCount.textContent = visibleCount + ' Suppliers';
        }
    }

    if (searchInput) searchInput.addEventListener('input', filterTable);
    if (districtFilter) districtFilter.addEventListener('change', filterTable);

    // Enforce phone and GSTIN input masks in Add Form
    const phoneInput = document.getElementById('supplierPhone');
    const gstinInput = document.getElementById('supplierGstin');
    if (phoneInput) {
        phoneInput.addEventListener('input', function (e) {
            e.target.value = e.target.value.replace(/\D/g, '').slice(0, 10);
        });
    }
    if (gstinInput) {
        gstinInput.addEventListener('input', function (e) {
            e.target.value = e.target.value.toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 15);
        });
    }

    // Enforce phone and GSTIN input masks in Edit Form
    const editPhone = document.getElementById('editSupplierPhone');
    const editGstin = document.getElementById('editSupplierGstin');
    if (editPhone) {
        editPhone.addEventListener('input', function (e) {
            e.target.value = e.target.value.replace(/\D/g, '').slice(0, 10);
        });
    }
    if (editGstin) {
        editGstin.addEventListener('input', function (e) {
            e.target.value = e.target.value.toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 15);
        });
    }

    // Edit button from inside view modal
    const btnEditFromView = document.getElementById('btnEditFromView');
    if (btnEditFromView) {
        btnEditFromView.addEventListener('click', function() {
            const viewModalEl = document.getElementById('modalViewSupplier');
            const viewModalInstance = bootstrap.Modal.getInstance(viewModalEl);
            if (viewModalInstance) {
                viewModalInstance.hide();
            }
            if (currentViewSupplier) {
                setTimeout(function() {
                    openEditSupplierModal(currentViewSupplier);
                }, 300);
            }
        });
    }
});

let currentViewSupplier = null;

// View Supplier Modal Handler
function openViewSupplierModal(sup) {
    currentViewSupplier = sup;

    const company = sup.company_name || sup.name || 'Unnamed Supplier';
    const code = sup.code || 'SUP';
    const contact = sup.name || '-';
    const phone = sup.phone || '';
    const email = sup.email || '';
    const gstin = sup.gstin || '';
    const address = sup.address || '-';
    const district = sup.district || sup.city || '-';
    const state = sup.state || '-';
    const status = (sup.status || 'active').toLowerCase();
    const concernName = sup.concern_name || (sup.concern_id ? 'Concern #' + sup.concern_id : '');

    document.getElementById('viewSupplierCompany').textContent = company;
    const codeBadge = document.getElementById('viewSupplierCodeBadge');
    if (codeBadge) codeBadge.textContent = code;
    document.getElementById('viewSupplierSubtitle').textContent = 'Contact: ' + contact + (concernName ? ' • ' + concernName : '');
    document.getElementById('viewSupplierContact').textContent = contact;

    // Status badge
    const statusBadge = document.getElementById('viewSupplierStatusBadge');
    const statusDesc = document.getElementById('viewSupplierStatusDesc');
    if (status === 'active') {
        if (statusBadge) {
            statusBadge.className = 'badge bg-success-subtle text-success border border-success';
            statusBadge.textContent = 'Active';
        }
        if (statusDesc) {
            statusDesc.className = 'badge bg-success-subtle text-success border border-success';
            statusDesc.textContent = 'Active Vendor (Eligible for Purchase Orders & Bills)';
        }
    } else {
        if (statusBadge) {
            statusBadge.className = 'badge bg-secondary-subtle text-secondary border';
            statusBadge.textContent = 'Inactive';
        }
        if (statusDesc) {
            statusDesc.className = 'badge bg-secondary-subtle text-secondary border';
            statusDesc.textContent = 'Archived / Inactive (Disabled from new purchases)';
        }
    }

    // Phone
    const phoneLink = document.getElementById('viewSupplierPhoneLink');
    if (phone) {
        phoneLink.href = 'tel:' + phone;
        document.getElementById('viewSupplierPhone').textContent = phone;
        phoneLink.style.display = 'inline-flex';
    } else {
        document.getElementById('viewSupplierPhone').textContent = 'Not Provided';
        phoneLink.href = '#';
    }

    // Email
    const emailLink = document.getElementById('viewSupplierEmailLink');
    if (email) {
        emailLink.href = 'mailto:' + email;
        document.getElementById('viewSupplierEmail').textContent = email;
        emailLink.style.display = 'inline-flex';
    } else {
        document.getElementById('viewSupplierEmail').textContent = 'Not Provided';
        emailLink.href = '#';
    }

    // Concern
    const concernWrapper = document.getElementById('viewSupplierConcernWrapper');
    if (concernName) {
        document.getElementById('viewSupplierConcern').textContent = concernName;
        concernWrapper.style.display = 'block';
    } else {
        concernWrapper.style.display = 'none';
    }

    // GSTIN
    const gstinEl = document.getElementById('viewSupplierGstin');
    const gstCatEl = document.getElementById('viewSupplierGstCategory');
    if (gstin) {
        if (gstinEl) gstinEl.textContent = gstin;
        if (gstCatEl) {
            const isTn = state.toLowerCase().includes('tamil') || gstin.startsWith('33');
            if (isTn) {
                gstCatEl.className = 'badge bg-primary-subtle text-primary border';
                gstCatEl.textContent = 'Intra-State GST (CGST + SGST @ State Rate)';
            } else {
                gstCatEl.className = 'badge bg-info-subtle text-info border';
                gstCatEl.textContent = 'Inter-State GST (IGST applicable)';
            }
        }
    } else {
        if (gstinEl) gstinEl.textContent = 'Not Registered';
        if (gstCatEl) {
            gstCatEl.className = 'badge bg-light text-muted border';
            gstCatEl.textContent = 'Unregistered Vendor (No Input Tax Credit)';
        }
    }

    // Address
    document.getElementById('viewSupplierAddress').textContent = address;
    document.getElementById('viewSupplierDistrict').textContent = district;
    document.getElementById('viewSupplierState').textContent = state;

    const modal = new bootstrap.Modal(document.getElementById('modalViewSupplier'));
    modal.show();
}

function copyViewGstin() {
    if (!currentViewSupplier || !currentViewSupplier.gstin) {
        alert('No GSTIN registered for this supplier.');
        return;
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(currentViewSupplier.gstin).then(function() {
            alert('GSTIN copied to clipboard: ' + currentViewSupplier.gstin);
        }).catch(function() {
            fallbackCopy(currentViewSupplier.gstin);
        });
    } else {
        fallbackCopy(currentViewSupplier.gstin);
    }
}

function fallbackCopy(text) {
    const temp = document.createElement('textarea');
    temp.value = text;
    document.body.appendChild(temp);
    temp.select();
    document.execCommand('copy');
    document.body.removeChild(temp);
    alert('GSTIN copied to clipboard: ' + text);
}

// Edit Supplier Modal Handler
function openEditSupplierModal(sup) {
    const modalEl = document.getElementById('modalEditSupplier');
    if (!modalEl) return;
    const elId = document.getElementById('editSupplierId'); if (elId) elId.value = sup.id || '';
    const elCode = document.getElementById('editSupplierCodeBadge'); if (elCode) elCode.textContent = 'Code: ' + (sup.code || 'SUP');
    const elName = document.getElementById('editSupplierName'); if (elName) elName.value = sup.name || '';
    const elCompany = document.getElementById('editSupplierCompany'); if (elCompany) elCompany.value = sup.company_name || '';
    const elPhone = document.getElementById('editSupplierPhone'); if (elPhone) elPhone.value = (sup.phone || '').replace(/\D/g, '');
    const elGstin = document.getElementById('editSupplierGstin'); if (elGstin) elGstin.value = sup.gstin || '';
    const elEmail = document.getElementById('editSupplierEmail'); if (elEmail) elEmail.value = sup.email || '';
    const elAddr = document.getElementById('editSupplierAddress'); if (elAddr) elAddr.value = sup.address || '';
    const elDist = document.getElementById('editSupplierDistrict'); if (elDist) elDist.value = sup.district || sup.city || '';
    const elState = document.getElementById('editSupplierState'); if (elState) elState.value = sup.state || '';
    const elStat = document.getElementById('editSupplierStatus'); if (elStat) elStat.value = sup.status || 'active';

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

// Delete Supplier Modal Handler
function openDeleteSupplierModal(id, name, company) {
    const modalEl = document.getElementById('modalDeleteSupplier');
    if (!modalEl) return;
    const elId = document.getElementById('deleteSupplierId'); if (elId) elId.value = id;
    const elName = document.getElementById('deleteSupplierName'); if (elName) elName.textContent = name + (company ? ' (' + company + ')' : '');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}
</script>
