<?php
// app/views/concern/catalog_mapping.php - Catalog Mapping for Purchased Finished Goods, 3-Tier Hierarchy & Attributes
?>
<div class="container-fluid px-0">
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/') ?>" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item text-muted">Production</li>
            <li class="breadcrumb-item active fw-semibold text-dark" aria-current="page">Catalog Mapping</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <div class="p-2.5 rounded-3 bg-primary-subtle text-primary border border-primary-subtle shadow-xs">
                <i class="bi bi-diagram-3-fill fs-4"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-0 text-dark">Catalog Mapping</h4>
                <p class="text-secondary small mb-0">Map purchased finished goods apparel items to master catalog styles, 3-tier category hierarchies, and size attributes.</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <ul class="nav nav-pills bg-light p-1 rounded-3 border" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-1.5 px-3 rounded-2 fw-semibold small <?= ($activeTab === 'mappings') ? 'active shadow-sm' : 'text-secondary' ?>" id="tab-mappings-btn" data-bs-toggle="pill" data-bs-target="#tab-mappings" type="button" role="tab">
                        <i class="bi bi-link-45deg me-1"></i> Finished Goods Mapping (<?= $metrics['total'] ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-1.5 px-3 rounded-2 fw-semibold small <?= ($activeTab === 'hierarchy') ? 'active shadow-sm' : 'text-secondary' ?>" id="tab-hierarchy-btn" data-bs-toggle="pill" data-bs-target="#tab-hierarchy" type="button" role="tab">
                        <i class="bi bi-diagram-3 me-1"></i> 3-Tier Hierarchy & Attributes
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-xs d-flex align-items-center mb-3" role="alert">
            <i class="bi bi-check-circle-fill fs-5 me-2"></i>
            <div><?= htmlspecialchars($flashSuccess) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-xs d-flex align-items-center mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
            <div><?= htmlspecialchars($flashError) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Metrics Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-secondary small fw-semibold">Purchased FG Items</div>
                        <div class="fs-4 fw-bold text-dark font-monospace"><?= $metrics['total'] ?></div>
                    </div>
                    <div class="p-2.5 bg-light rounded-3 text-secondary border">
                        <i class="bi bi-boxes fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-secondary small fw-semibold">Unmapped Products</div>
                        <div class="fs-4 fw-bold text-warning-emphasis font-monospace"><?= $metrics['unmapped'] ?></div>
                    </div>
                    <div class="p-2.5 bg-warning-subtle text-warning-emphasis rounded-3 border border-warning-subtle">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-secondary small fw-semibold">Mapped to Catalog</div>
                        <div class="fs-4 fw-bold text-success font-monospace"><?= $metrics['mapped'] ?></div>
                    </div>
                    <div class="p-2.5 bg-success-subtle text-success rounded-3 border border-success-subtle">
                        <i class="bi bi-check2-circle fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-secondary small fw-semibold">Total FG Pieces</div>
                        <div class="fs-4 fw-bold text-primary font-monospace"><?= number_format($metrics['pieces']) ?> pcs</div>
                    </div>
                    <div class="p-2.5 bg-primary-subtle text-primary rounded-3 border border-primary-subtle">
                        <i class="bi bi-tag-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Contents -->
    <div class="tab-content">

        <!-- ========================================== -->
        <!-- TAB 1: FINISHED GOODS MAPPING REGISTER     -->
        <!-- ========================================== -->
        <div class="tab-pane fade <?= ($activeTab === 'mappings') ? 'show active' : '' ?>" id="tab-mappings" role="tabpanel">
            
            <!-- Filters Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="<?= \App\Core\Controller::url('/production/catalog-mapping') ?>" class="row g-2 align-items-center">
                        <input type="hidden" name="tab" value="mappings">
                        
                        <div class="col-md-4">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" name="search" class="form-control border-start-0" placeholder="Search by product name, bill #, supplier, or SKU..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="btn-group btn-group-sm w-100" role="group">
                                <a href="<?= \App\Core\Controller::url('/production/catalog-mapping?status=all&tab=mappings') ?>" class="btn <?= ($filters['status'] === 'all') ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
                                <a href="<?= \App\Core\Controller::url('/production/catalog-mapping?status=unmapped&tab=mappings') ?>" class="btn <?= ($filters['status'] === 'unmapped') ? 'btn-warning' : 'btn-outline-secondary' ?>">Unmapped (<?= $metrics['unmapped'] ?>)</a>
                                <a href="<?= \App\Core\Controller::url('/production/catalog-mapping?status=mapped&tab=mappings') ?>" class="btn <?= ($filters['status'] === 'mapped') ? 'btn-success' : 'btn-outline-secondary' ?>">Mapped (<?= $metrics['mapped'] ?>)</a>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <select name="category_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">-- All Categories --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($filters['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2 d-flex gap-1">
                            <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                            <a href="<?= \App\Core\Controller::url('/production/catalog-mapping') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Items Table -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-bag-check-fill text-primary"></i> Purchased Finished Goods Register (From Purchase Billing)
                    </h6>
                    <span class="badge bg-light text-dark border">
                        Showing <?= count($items) ?> line items
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-custom">
                        <thead>
                            <tr>
                                <th style="width: 40px;" class="text-center">#</th>
                                <th>Purchased Finished Good</th>
                                <th>Source Bill & Supplier</th>
                                <th>Quantity & Cost</th>
                                <th>Tax & Total</th>
                                <th>Mapping Status</th>
                                <th>Mapped Catalog Style & 3-Tier Hierarchy</th>
                                <th class="text-center" style="width: 140px;">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <div class="text-muted mb-2"><i class="bi bi-inbox fs-2"></i></div>
                                        <h6 class="fw-semibold text-secondary">No Purchased Finished Goods Found</h6>
                                        <p class="small text-muted mb-3">Record purchases under the "Finished Goods" tab in Purchase Billing, and they will appear here for catalog mapping.</p>
                                        <?php if (\App\Core\Auth::can('purchase_billing', 'add')): ?>
                                        <a href="<?= \App\Core\Controller::url('/m/purchase_billing/create') ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-plus-circle me-1"></i> Record Purchase Bill
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $idx => $it): ?>
                                    <tr>
                                        <td class="text-center font-monospace small text-muted"><?= $idx + 1 ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($it['item_name']) ?></div>
                                            <div class="d-flex align-items-center gap-1 mt-0.5">
                                                <?php if (!empty($it['item_code'])): ?>
                                                    <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.72rem;"><?= htmlspecialchars($it['item_code']) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($it['specification'])): ?>
                                                    <span class="small text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($it['specification']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="font-monospace fw-semibold text-dark small">
                                                <i class="bi bi-receipt me-1 text-muted"></i><?= htmlspecialchars($it['bill_no']) ?>
                                            </div>
                                            <div class="small text-muted" style="font-size: 0.75rem;">
                                                <i class="bi bi-building me-1"></i><?= htmlspecialchars($it['supplier_name'] ?? 'Supplier') ?> &bull; <?= date('d M Y', strtotime($it['bill_date'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-primary font-monospace"><?= number_format((float)$it['qty']) ?> <span class="small fw-normal text-muted"><?= htmlspecialchars($it['unit'] ?: 'Pcs') ?></span></div>
                                            <div class="small text-muted" style="font-size: 0.75rem;">@ ₹<?= number_format((float)$it['unit_price'], 2) ?>/pc</div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark font-monospace">₹<?= number_format((float)$it['total_price'] + (float)$it['tax_amount'], 2) ?></div>
                                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle" style="font-size: 0.7rem;">HSN <?= htmlspecialchars($it['hsn_code'] ?: 'Not set') ?></span>
                                        </td>
                                        <td>
                                            <?php if ($it['is_mapped']): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                    <i class="bi bi-check2-circle me-1"></i> Mapped
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                                                    <i class="bi bi-clock-history me-1"></i> Unmapped
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($it['is_mapped'] && !empty($it['catalog_name'])): ?>
                                                <div class="fw-semibold text-dark small">
                                                    <?= htmlspecialchars($it['catalog_name']) ?>
                                                    <?php if (!empty($it['catalog_style_code'])): ?>
                                                        <span class="badge bg-light text-dark border font-monospace ms-1"><?= htmlspecialchars($it['catalog_style_code']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="small text-secondary" style="font-size: 0.75rem;">
                                                    <i class="bi bi-folder2-open me-1 text-primary"></i><?= htmlspecialchars($it['hierarchy_path'] ?: 'General') ?>
                                                </div>
                                            <?php elseif ($it['is_mapped'] && !empty($it['variant_name'])): ?>
                                                <div class="fw-semibold text-dark small"><?= htmlspecialchars($it['variant_name']) ?></div>
                                                <small class="badge bg-light text-dark border"><?= htmlspecialchars($it['variant_sku']) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted small fst-italic">No catalog style assigned yet</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-inline-flex align-items-center gap-1">
                                                <?php if (\App\Core\Auth::can('catalog_mapping', 'add') || \App\Core\Auth::can('catalog_mapping', 'edit')): ?>
                                                    <?php if (!$it['is_mapped']): ?>
                                                        <a href="<?= \App\Core\Controller::url('/production/catalog-mapping/map?id=' . $it['id']) ?>" class="btn btn-sm btn-primary rounded-3 px-2 py-1 shadow-xs fw-semibold" title="Map to Catalog Style & 3-Tier Hierarchy">
                                                            <i class="bi bi-diagram-3 me-1"></i> Map
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="<?= \App\Core\Controller::url('/production/catalog-mapping/map?id=' . $it['id']) ?>" class="btn btn-sm btn-outline-primary rounded-3 p-1.5" title="Edit Catalog Mapping">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <?php if (\App\Core\Auth::can('catalog_mapping', 'delete')): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-3 p-1.5" title="Unmap from Catalog" onclick="confirmUnmap(<?= $it['id'] ?>, '<?= htmlspecialchars(addslashes($it['item_name'])) ?>')">
                                                            <i class="bi bi-link-45deg"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted small fst-italic">Read-only</span>
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

        <!-- ========================================== -->
        <!-- TAB 2: 3-TIER HIERARCHY & ATTRIBUTES MASTER -->
        <!-- ========================================== -->
        <div class="tab-pane fade <?= ($activeTab === 'hierarchy') ? 'show active' : '' ?>" id="tab-hierarchy" role="tabpanel">
            
            <div class="row g-4">
                
                <!-- 1. MAIN CATEGORIES (TIER-1) -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-folder-fill text-primary me-1.5"></i> 1. Main Categories</h6>
                                <small class="text-muted" style="font-size: 0.75rem;">Tier-1 Department Level</small>
                            </div>
                            <?php if (\App\Core\Auth::can('catalog_mapping', 'add')): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-3 fw-semibold shadow-xs" data-bs-toggle="modal" data-bs-target="#modalAddCategory">
                                <i class="bi bi-plus-circle me-1"></i> Add Tier-1
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush small">
                                <?php if (empty($categories)): ?>
                                    <li class="list-group-item text-center py-4 text-muted">No categories created.</li>
                                <?php else: ?>
                                    <?php foreach ($categories as $c): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center py-2.5">
                                            <div>
                                                <strong class="text-dark"><?= htmlspecialchars($c['name']) ?></strong>
                                                <div class="small text-muted font-monospace">Code: [<?= htmlspecialchars($c['code'] ?: 'CAT') ?>] &bull; HSN <?= htmlspecialchars($c['default_hsn'] ?: 'Not set') ?></div>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace">
                                                    <?= $c['default_gst_rate'] === null ? 'Rate not set' : number_format((float)$c['default_gst_rate'], 2) . '% GST' ?>
                                                </span>
                                                <?php if (\App\Core\Auth::can('catalog_mapping', 'delete')): ?>
                                                <button type="button" class="btn btn-outline-danger btn-sm py-0.5 px-1.5 border-0 shadow-none" title="Delete Main Category" onclick="confirmDeleteHierarchy('category', <?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['name'])) ?>')">
                                                    <i class="bi bi-trash fs-6"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- 2. SUBCATEGORIES (TIER-2) -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-diagram-2 text-info me-1.5"></i> 2. Subcategories</h6>
                                <small class="text-muted" style="font-size: 0.75rem;">Tier-2 Product Group</small>
                            </div>
                            <?php if (\App\Core\Auth::can('catalog_mapping', 'add')): ?>
                            <button type="button" class="btn btn-sm btn-outline-info rounded-3 fw-semibold shadow-xs" data-bs-toggle="modal" data-bs-target="#modalAddSubcategory">
                                <i class="bi bi-plus-circle me-1"></i> Add Tier-2
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush small">
                                <?php if (empty($subcategories)): ?>
                                    <li class="list-group-item text-center py-4 text-muted">No subcategories created.</li>
                                <?php else: ?>
                                    <?php foreach ($subcategories as $sc): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center py-2.5">
                                            <div>
                                                <strong class="text-dark"><?= htmlspecialchars($sc['name']) ?></strong>
                                                <div class="small text-muted">Parent: <span class="badge bg-light text-dark border"><?= htmlspecialchars($sc['category_name']) ?></span></div>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-light text-secondary border font-monospace">
                                                    [<?= htmlspecialchars($sc['code'] ?: 'SUB') ?>]
                                                </span>
                                                <?php if (\App\Core\Auth::can('catalog_mapping', 'delete')): ?>
                                                <button type="button" class="btn btn-outline-danger btn-sm py-0.5 px-1.5 border-0 shadow-none" title="Delete Subcategory" onclick="confirmDeleteHierarchy('subcategory', <?= $sc['id'] ?>, '<?= htmlspecialchars(addslashes($sc['name'])) ?>')">
                                                    <i class="bi bi-trash fs-6"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- 3. SUB-SUBCATEGORIES (TIER-3) -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-diagram-3 text-success me-1.5"></i> 3. Sub-Subcategories</h6>
                                <small class="text-muted" style="font-size: 0.75rem;">Tier-3 Garment Style Specification</small>
                            </div>
                            <?php if (\App\Core\Auth::can('catalog_mapping', 'add')): ?>
                            <button type="button" class="btn btn-sm btn-outline-success rounded-3 fw-semibold shadow-xs" data-bs-toggle="modal" data-bs-target="#modalAddSubSubcategory">
                                <i class="bi bi-plus-circle me-1"></i> Add Tier-3
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush small">
                                <?php if (empty($subSubcategories)): ?>
                                    <li class="list-group-item text-center py-4 text-muted">No Tier-3 categories created.</li>
                                <?php else: ?>
                                    <?php foreach ($subSubcategories as $ssc): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center py-2.5">
                                            <div>
                                                <strong class="text-dark"><?= htmlspecialchars($ssc['name']) ?></strong>
                                                <div class="small text-muted"><?= htmlspecialchars($ssc['category_name']) ?> &rsaquo; <?= htmlspecialchars($ssc['subcategory_name'] ?? 'General') ?></div>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace">
                                                    [<?= htmlspecialchars($ssc['code'] ?: 'SS') ?>]
                                                </span>
                                                <?php if (\App\Core\Auth::can('catalog_mapping', 'delete')): ?>
                                                <button type="button" class="btn btn-outline-danger btn-sm py-0.5 px-1.5 border-0 shadow-none" title="Delete Tier-3 Sub-Subcategory" onclick="confirmDeleteHierarchy('subsubcategory', <?= $ssc['id'] ?>, '<?= htmlspecialchars(addslashes($ssc['name'])) ?>')">
                                                    <i class="bi bi-trash fs-6"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- 4. PRODUCT ATTRIBUTES (SIZES, COLORS, FITS) -->
                <div class="col-12">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-tags-fill text-warning-emphasis me-1.5"></i> Product Attributes Master (Sizes, Colors, Fits)</h6>
                                <small class="text-muted" style="font-size: 0.75rem;">Reusable variant attributes automatically suggested during finished goods catalog mapping</small>
                            </div>
                            <?php if (\App\Core\Auth::can('catalog_mapping', 'add')): ?>
                            <button type="button" class="btn btn-sm btn-outline-warning text-dark rounded-3 fw-semibold shadow-xs" data-bs-toggle="modal" data-bs-target="#modalAddAttribute">
                                <i class="bi bi-plus-circle me-1"></i> Add Attribute
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-4">
                            <?php 
                                $groupedAttrs = [];
                                foreach ($attributes as $a) {
                                    $t = strtolower($a['attribute_type'] ?? 'size');
                                    $groupedAttrs[$t][] = $a;
                                }
                            ?>
                            <?php if (empty($groupedAttrs)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-tag fs-2 text-secondary d-block mb-2"></i>
                                    <p class="small mb-0">No attributes created yet. Add them here or save size, color, and other values from a catalog mapping to add them to this master.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($groupedAttrs as $type => $typeAttrs): ?>
                                    <div class="mb-3">
                                        <strong class="small text-secondary d-block mb-2">Available <?= htmlspecialchars(ucfirst($type)) ?> Attributes:</strong>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($typeAttrs as $sa): 
                                                $splitVals = preg_split('/[,;|]+/', (string)$sa['attribute_value']);
                                                foreach ($splitVals as $sv):
                                                    $sv = trim($sv);
                                                    if ($sv === '') continue;
                                            ?>
                                                <span class="badge bg-white text-dark border px-3 py-2 fs-6 shadow-xs font-monospace d-inline-flex align-items-center gap-1.5">
                                                    <span><?= htmlspecialchars($sv) ?></span>
                                                    <button type="button" class="btn btn-link text-danger p-0 ms-1 text-decoration-none shadow-none" style="font-size: 0.8rem; line-height: 1;" title="Delete Attribute <?= htmlspecialchars($sv) ?>" onclick="confirmDeleteHierarchy('attribute', <?= $sa['id'] ?>, '<?= htmlspecialchars(addslashes($sv)) ?>', '<?= htmlspecialchars(addslashes($sv)) ?>')">
                                                        <i class="bi bi-x-circle-fill"></i>
                                                    </button>
                                                </span>
                                            <?php endforeach; endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 5. HSN MASTER -->
                <div class="col-12">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-upc-scan text-primary me-1.5"></i> HSN Master</h6>
                                <small class="text-muted" style="font-size: 0.75rem;">Active goods HSN codes available for catalog mapping, with configured GST rates and conditions</small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace" id="catalogHsnCount">
                                    <?= count($hsnCodes) ?> HSN Codes
                                </span>
                                <div class="input-group input-group-sm" style="width: min(100%, 300px);">
                                    <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                                    <input type="search" class="form-control" id="catalogHsnSearch" placeholder="Search HSN, description, or category" aria-label="Search HSN master">
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($hsnCodes)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-upc fs-2 text-secondary d-block mb-2"></i>
                                    <p class="small mb-0">No active HSN codes are available for this concern.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive" style="max-height: 480px;">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light position-sticky top-0" style="z-index: 1;">
                                            <tr>
                                                <th class="ps-3 text-nowrap">HSN Code</th>
                                                <th>Description</th>
                                                <th>Category</th>
                                                <th class="text-nowrap">GST Rate</th>
                                                <th>Rate Conditions</th>
                                                <th class="pe-3 text-nowrap">Scope</th>
                                            </tr>
                                        </thead>
                                        <tbody id="catalogHsnTableBody">
                                            <?php foreach ($hsnCodes as $hsn): ?>
                                                <tr class="catalog-hsn-row" data-search="<?= htmlspecialchars(strtolower(implode(' ', [
                                                    $hsn['hsn_code'] ?? '',
                                                    $hsn['description'] ?? '',
                                                    $hsn['category'] ?? '',
                                                    $hsn['rate_note'] ?? '',
                                                    $hsn['concern_name'] ?? '',
                                                ])), ENT_QUOTES, 'UTF-8') ?>">
                                                    <td class="ps-3">
                                                        <span class="font-monospace fw-semibold text-primary"><?= htmlspecialchars($hsn['hsn_code']) ?></span>
                                                    </td>
                                                    <td><?= htmlspecialchars($hsn['description'] ?: '—') ?></td>
                                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($hsn['category'] ?: 'Uncategorized') ?></span></td>
                                                    <td class="text-nowrap">
                                                        <?php if ($hsn['gst_rate'] !== null && $hsn['gst_rate'] !== ''): ?>
                                                            <span class="fw-semibold"><?= number_format((float) $hsn['gst_rate'], 2) ?>%</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Verify rate</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><small class="text-muted"><?= htmlspecialchars($hsn['rate_note'] ?: '—') ?></small></td>
                                                    <td class="pe-3"><small class="text-muted"><?= htmlspecialchars($hsn['concern_name']) ?></small></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-none text-center text-muted small py-4" id="catalogHsnNoResults">No HSN codes match your search.</div>
                                <div class="border-top px-3 py-2 text-muted small" id="catalogHsnVisibleCount" aria-live="polite">
                                    Showing <?= count($hsnCodes) ?> of <?= count($hsnCodes) ?> active HSN codes
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>
</div>

<!-- MODAL: ADD TIER-1 CATEGORY -->
<div class="modal fade" id="modalAddCategory" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= \App\Core\Controller::url('/production/catalog-mapping/quick-category') ?>" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
            <input type="hidden" name="tier" value="category">
            <input type="hidden" name="return_url" value="/production/catalog-mapping?tab=hierarchy">
            <div class="modal-header bg-light">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-folder-plus text-primary me-1.5"></i> Add Tier-1 Main Category</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Category Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Menswear, Kids, Maternity" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Short Code</label>
                        <input type="text" name="code" class="form-control font-monospace" placeholder="e.g. M, K, W" maxlength="10">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Default HSN</label>
                        <input type="text" name="default_hsn" id="categoryDefaultHsn" class="form-control font-monospace" placeholder="Search HSN" data-hsn-autocomplete data-hsn-type="HSN" data-hsn-gst-target="#categoryDefaultGstRate" autocomplete="off">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">GST Rate (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="default_gst_rate" id="categoryDefaultGstRate" class="form-control font-monospace" placeholder="Optional">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-3"><i class="bi bi-check-circle me-1"></i> Save Category</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: ADD TIER-2 SUBCATEGORY -->
<div class="modal fade" id="modalAddSubcategory" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= \App\Core\Controller::url('/production/catalog-mapping/quick-category') ?>" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
            <input type="hidden" name="tier" value="subcategory">
            <input type="hidden" name="return_url" value="/production/catalog-mapping?tab=hierarchy">
            <div class="modal-header bg-light">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-diagram-2 text-info me-1.5"></i> Add Tier-2 Subcategory</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Parent Main Category <span class="text-danger">*</span></label>
                    <select name="category_id" class="form-select" required>
                        <option value="">-- Select Parent Category --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['code'] ?: 'CAT') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Subcategory Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Shirts, Baby Dress, T-Shirts" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Short Code</label>
                    <input type="text" name="code" class="form-control font-monospace" placeholder="e.g. SH, BD, TS" maxlength="10">
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-info text-white btn-sm px-3"><i class="bi bi-check-circle me-1"></i> Save Subcategory</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: ADD TIER-3 SUB-SUBCATEGORY -->
<div class="modal fade" id="modalAddSubSubcategory" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= \App\Core\Controller::url('/production/catalog-mapping/quick-category') ?>" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
            <input type="hidden" name="tier" value="subsubcategory">
            <input type="hidden" name="return_url" value="/production/catalog-mapping?tab=hierarchy">
            <div class="modal-header bg-light">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-diagram-3 text-success me-1.5"></i> Add Tier-3 Sub-Subcategory</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Parent Main Category <span class="text-danger">*</span></label>
                    <select name="category_id" id="modalTier3CatSelect" class="form-select" required onchange="loadSubcatsForModal(this.value)">
                        <option value="">-- Select Parent Category --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Parent Subcategory <span class="text-danger">*</span></label>
                    <select name="subcategory_id" id="modalTier3SubcatSelect" class="form-select" required>
                        <option value="">-- Select Subcategory First --</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Sub-Subcategory Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Formal, Sleeveless, Printed Frock" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Short Code</label>
                    <input type="text" name="code" class="form-control font-monospace" placeholder="e.g. F, SL, PF" maxlength="10">
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success btn-sm px-3"><i class="bi bi-check-circle me-1"></i> Save Tier-3</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: ADD PRODUCT ATTRIBUTE -->
<div class="modal fade" id="modalAddAttribute" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= \App\Core\Controller::url('/production/catalog-mapping/quick-attribute') ?>" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
            <input type="hidden" name="return_url" value="/production/catalog-mapping?tab=hierarchy">
            <div class="modal-header bg-light">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-tag-fill text-warning me-1.5"></i> Add Product Attribute</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <?php if (\App\Core\Auth::isSuperAdmin() && empty($selectedCid)): ?>
                    <div class="mb-3">
                        <label for="attributeConcernId" class="form-label small fw-semibold">Concern <span class="text-danger">*</span></label>
                        <select name="concern_id" id="attributeConcernId" class="form-select" required>
                            <option value="">-- Choose Concern --</option>
                            <?php foreach (($allConcerns ?? []) as $concern): ?>
                                <option value="<?= (int) $concern['id'] ?>">
                                    <?= htmlspecialchars($concern['name']) ?> (<?= htmlspecialchars($concern['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Choose which concern will own this attribute.</small>
                    </div>
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Attribute Type <span class="text-danger">*</span></label>
                    <select name="attribute_type" id="selectAttributeType" class="form-select" required onchange="handleAttributeTypeChange(this.value)">
                        <option value="">-- Choose Attribute Type --</option>
                        <?php foreach (($attributeTypes ?? []) as $at): ?>
                            <option value="<?= htmlspecialchars($at) ?>"><?= htmlspecialchars(ucfirst($at)) ?></option>
                        <?php endforeach; ?>
                        <option value="__create_new__" class="fw-bold text-primary">+ Create New Attribute Type...</option>
                    </select>
                </div>
                <div class="mb-3 d-none" id="containerNewAttributeType">
                    <label class="form-label small fw-semibold text-primary">New Attribute Type Name <span class="text-danger">*</span></label>
                    <input type="text" name="new_attribute_type" id="inputNewAttributeType" class="form-control" placeholder="e.g. Fabric Material, Sleeve, Neck Type">
                    <small class="text-muted">Enter a custom attribute type name to add to the dropdown.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Attribute Value <span class="text-danger">*</span></label>
                    <input type="text" name="attribute_value" class="form-control" placeholder="e.g. 4XL, Olive Green, Comfort Fit" required>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning text-dark btn-sm px-3 fw-semibold"><i class="bi bi-plus-circle me-1"></i> Add Attribute</button>
            </div>
        </form>
    </div>
</div>

<!-- CONFIRM UNMAP MODAL -->
<div class="modal fade" id="modalConfirmUnmap" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= \App\Core\Controller::url('/production/catalog-mapping/unmap') ?>" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(!empty($csrfToken) ? $csrfToken : \App\Core\Auth::csrfToken()) ?>">
            <input type="hidden" name="purchase_bill_item_id" id="unmapItemId" value="">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> Unmap Finished Good Item</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="bi bi-link-45deg text-danger fs-1 mb-2 d-block"></i>
                <h5 class="fw-bold text-dark mb-2" id="unmapItemName">Unmap Product?</h5>
                <p class="text-secondary small mb-0">
                    This will remove the catalog style mapping for this purchased line item. The item will return to <strong>Unmapped</strong> status, allowing you to remap it to a different category or style.
                </p>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm px-3"><i class="bi bi-x-circle me-1"></i> Confirm Unmap</button>
            </div>
        </form>
    </div>
</div>

<!-- CONFIRM DELETE HIERARCHY / ATTRIBUTE MODAL -->
<div class="modal fade" id="modalConfirmDeleteHierarchy" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= \App\Core\Controller::url('/production/catalog-mapping/delete-hierarchy') ?>" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(!empty($csrfToken) ? $csrfToken : \App\Core\Auth::csrfToken()) ?>">
            <input type="hidden" name="type" id="deleteHierarchyType" value="">
            <input type="hidden" name="id" id="deleteHierarchyId" value="">
            <input type="hidden" name="value" id="deleteHierarchyValue" value="">
            <input type="hidden" name="return_url" value="/production/catalog-mapping?tab=hierarchy">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title fw-bold" id="deleteHierarchyModalTitle"><i class="bi bi-trash-fill me-2"></i> Confirm Deletion</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="bi bi-exclamation-triangle-fill text-danger fs-1 mb-2 d-block"></i>
                <h5 class="fw-bold text-dark mb-2" id="deleteHierarchyName">Delete Item?</h5>
                <p class="text-secondary small mb-0" id="deleteHierarchyWarning">
                    Are you sure you want to delete this item? This action will permanently remove it from the system.
                </p>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm px-3 fw-bold"><i class="bi bi-trash me-1"></i> Yes, Delete Permanently</button>
            </div>
        </form>
    </div>
</div>

<script>
function handleAttributeTypeChange(val) {
    const box = document.getElementById('containerNewAttributeType');
    const input = document.getElementById('inputNewAttributeType');
    if (!box || !input) return;
    if (val === '__create_new__') {
        box.classList.remove('d-none');
        input.setAttribute('required', 'required');
        input.focus();
    } else {
        box.classList.add('d-none');
        input.removeAttribute('required');
    }
}

function confirmUnmap(id, name) {
    document.getElementById('unmapItemId').value = id;
    document.getElementById('unmapItemName').textContent = `Unmap "${name}"?`;
    new bootstrap.Modal(document.getElementById('modalConfirmUnmap')).show();
}

function confirmDeleteHierarchy(type, id, name, value = '') {
    document.getElementById('deleteHierarchyType').value = type;
    document.getElementById('deleteHierarchyId').value = id;
    document.getElementById('deleteHierarchyValue').value = value;
    const typeTitles = {
        'category': 'Main Category',
        'subcategory': 'Subcategory',
        'subsubcategory': 'Tier-3 Sub-Subcategory',
        'attribute': 'Attribute'
    };
    const titleLabel = typeTitles[type] || 'Item';
    document.getElementById('deleteHierarchyModalTitle').innerHTML = `<i class="bi bi-trash-fill me-2"></i> Delete ${titleLabel}`;
    document.getElementById('deleteHierarchyName').textContent = `Delete "${name}"?`;
    document.getElementById('deleteHierarchyWarning').textContent = type === 'category' 
        ? `Are you sure you want to delete category "${name}"? All associated subcategories will also be removed.` 
        : `Are you sure you want to delete ${titleLabel.toLowerCase()} "${name}"? This action cannot be undone.`;
    new bootstrap.Modal(document.getElementById('modalConfirmDeleteHierarchy')).show();
}

function loadSubcatsForModal(catId) {
    const subSelect = document.getElementById('modalTier3SubcatSelect');
    if (!subSelect) return;
    subSelect.innerHTML = '<option value="">-- Loading... --</option>';
    if (!catId) {
        subSelect.innerHTML = '<option value="">-- Select Category First --</option>';
        return;
    }

    fetch('<?= \App\Core\Controller::url('/api/categories/subcategories?category_id=') ?>' + encodeURIComponent(catId))
        .then(r => r.json())
        .then(data => {
            const items = Array.isArray(data) ? data : (data.data || []);
            if (items.length === 0) {
                subSelect.innerHTML = '<option value="">-- No Subcategories Available --</option>';
            } else {
                subSelect.innerHTML = '<option value="">-- Select Subcategory (Optional) --</option>';
                items.forEach(item => {
                    subSelect.innerHTML += `<option value="${item.id}">${item.name}</option>`;
                });
            }
        })
        .catch(err => {
            console.error('Failed to load subcategories for modal:', err);
            subSelect.innerHTML = '<option value="">-- Error Loading Subcategories --</option>';
        });
}

document.addEventListener('DOMContentLoaded', function() {
    const hsnSearch = document.getElementById('catalogHsnSearch');
    const hsnRows = Array.from(document.querySelectorAll('.catalog-hsn-row'));
    const hsnNoResults = document.getElementById('catalogHsnNoResults');
    const hsnVisibleCount = document.getElementById('catalogHsnVisibleCount');
    if (hsnSearch) {
        hsnSearch.addEventListener('input', function() {
            const query = hsnSearch.value.trim().toLowerCase();
            let visibleCount = 0;
            hsnRows.forEach(function(row) {
                const matches = (row.dataset.search || '').includes(query);
                row.classList.toggle('d-none', !matches);
                if (matches) visibleCount++;
            });
            if (hsnNoResults) hsnNoResults.classList.toggle('d-none', visibleCount > 0);
            if (hsnVisibleCount) {
                hsnVisibleCount.textContent = `Showing ${visibleCount} of ${hsnRows.length} active HSN codes`;
            }
        });
    }

    const modalSubSub = document.getElementById('modalAddSubSubcategory');
    if (modalSubSub) {
        modalSubSub.addEventListener('show.bs.modal', function() {
            const catSelect = document.getElementById('modalTier3CatSelect');
            if (catSelect && catSelect.value) {
                loadSubcatsForModal(catSelect.value);
            }
        });
    }
});
</script>
