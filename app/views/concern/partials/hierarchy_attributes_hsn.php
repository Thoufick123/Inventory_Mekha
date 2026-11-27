<?php
// Shared partial: 3-Tier Category Hierarchy, Product Attributes Master, and HSN Master
$hierarchyModule = $hierarchyPermissionModule ?? 'catalog_mapping';
$hierarchyReturn = $hierarchyReturnUrl ?? '/production/catalog-mapping';
$hierarchyPrefix = $hierarchyRoutePrefix ?? '/production/catalog-mapping';
$canAddHierarchy = \App\Core\Auth::isSuperAdmin() || \App\Core\Auth::can($hierarchyModule, 'add');
$canEditHierarchy = \App\Core\Auth::isSuperAdmin() || \App\Core\Auth::can($hierarchyModule, 'edit');
$canDeleteHierarchy = \App\Core\Auth::isSuperAdmin() || \App\Core\Auth::can($hierarchyModule, 'delete');
$csrfVal = htmlspecialchars(!empty($csrfToken) ? $csrfToken : \App\Core\Auth::csrfToken());
?>
<style>
    .compact-hierarchy-table > :not(caption) > * > * {
        padding: 0.35rem 0.5rem;
        vertical-align: middle;
    }
    .compact-hierarchy-table tbody .badge {
        font-size: 0.68rem;
    }
</style>
<div class="row g-3">

    <!-- 1. MAIN CATEGORIES (TIER-1) -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-folder-fill text-primary me-1.5"></i> 1. Main Categories</h6>
                    <small class="text-muted" style="font-size: 0.72rem;">Tier-1 Department Level</small>
                </div>
                <?php if ($canAddHierarchy): ?>
                <button type="button" class="btn btn-sm btn-outline-primary rounded-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalAddCategory">
                    <i class="bi bi-plus-circle me-1"></i> Add Tier-1
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover align-middle mb-0 small compact-hierarchy-table">
                        <thead class="table-light text-uppercase text-secondary" style="font-size: 0.70rem;">
                            <tr>
                                <th>Category</th>
                                <th class="text-center">GST</th>
                                <th class="text-center">Complementary</th>
                                <?php if ($canDeleteHierarchy): ?><th class="text-center" style="width: 38px;">Del</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr><td colspan="<?= $canDeleteHierarchy ? '4' : '3' ?>" class="text-center py-3 text-muted">No categories created.</td></tr>
                            <?php else: ?>
                                <?php foreach ($categories as $c): ?>
                                    <?php $isCatComp = !empty($c['is_complementary']); ?>
                                    <tr>
                                        <td>
                                            <strong class="text-dark"><?= htmlspecialchars($c['name']) ?></strong>
                                            <div class="text-muted font-monospace" style="font-size: 0.72rem;">[<?= htmlspecialchars($c['code'] ?: 'CAT') ?>] &bull; HSN <?= htmlspecialchars($c['default_hsn'] ?: 'Not set') ?></div>
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace">
                                                <?= $c['default_gst_rate'] === null ? 'Rate not set' : number_format((float)$c['default_gst_rate'], 2) . '%' ?>
                                            </span>
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <button type="button"
                                                    class="btn btn-sm py-0 px-2 fw-semibold <?= $isCatComp ? 'btn-warning text-dark' : 'btn-outline-secondary' ?>"
                                                    style="font-size: 0.72rem;"
                                                    title="<?= $isCatComp ? 'Click to disable complementary pricing' : 'Click to mark all products in this category as complementary (₹0 billed)' ?>"
                                                    onclick="toggleHierarchyComplementary('category', <?= (int)$c['id'] ?>, <?= $isCatComp ? 0 : 1 ?>)">
                                                <i class="bi <?= $isCatComp ? 'bi-gift-fill' : 'bi-gift' ?> me-1"></i><?= $isCatComp ? 'Complementary' : 'Set Free' ?>
                                            </button>
                                        </td>
                                        <?php if ($canDeleteHierarchy): ?>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 border-0 shadow-none" title="Delete Main Category" onclick="confirmDeleteHierarchy('category', <?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['name'])) ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. SUBCATEGORIES (TIER-2) -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-diagram-2 text-info me-1.5"></i> 2. Subcategories</h6>
                    <small class="text-muted" style="font-size: 0.72rem;">Tier-2 Product Group</small>
                </div>
                <?php if ($canAddHierarchy): ?>
                <button type="button" class="btn btn-sm btn-outline-info rounded-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalAddSubcategory">
                    <i class="bi bi-plus-circle me-1"></i> Add Tier-2
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover align-middle mb-0 small compact-hierarchy-table">
                        <thead class="table-light text-uppercase text-secondary" style="font-size: 0.70rem;">
                            <tr>
                                <th>Subcategory</th>
                                <th class="text-center">Code</th>
                                <th class="text-center">Complementary</th>
                                <?php if ($canDeleteHierarchy): ?><th class="text-center" style="width: 38px;">Del</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subcategories)): ?>
                                <tr><td colspan="<?= $canDeleteHierarchy ? '4' : '3' ?>" class="text-center py-3 text-muted">No subcategories created.</td></tr>
                            <?php else: ?>
                                <?php foreach ($subcategories as $sc): ?>
                                    <?php $isSubComp = !empty($sc['is_complementary']); ?>
                                    <tr>
                                        <td>
                                            <strong class="text-dark"><?= htmlspecialchars($sc['name']) ?></strong>
                                            <div class="text-muted" style="font-size: 0.72rem;">Parent: <span class="badge bg-light text-dark border"><?= htmlspecialchars($sc['category_name']) ?></span></div>
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <span class="badge bg-light text-secondary border font-monospace">[<?= htmlspecialchars($sc['code'] ?: 'SUB') ?>]</span>
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <button type="button"
                                                    class="btn btn-sm py-0 px-2 fw-semibold <?= $isSubComp ? 'btn-warning text-dark' : 'btn-outline-secondary' ?>"
                                                    style="font-size: 0.72rem;"
                                                    title="<?= $isSubComp ? 'Click to disable complementary pricing' : 'Click to mark all products in this subcategory as complementary (₹0 billed)' ?>"
                                                    onclick="toggleHierarchyComplementary('subcategory', <?= (int)$sc['id'] ?>, <?= $isSubComp ? 0 : 1 ?>)">
                                                <i class="bi <?= $isSubComp ? 'bi-gift-fill' : 'bi-gift' ?> me-1"></i><?= $isSubComp ? 'Complementary' : 'Set Free' ?>
                                            </button>
                                        </td>
                                        <?php if ($canDeleteHierarchy): ?>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 border-0 shadow-none" title="Delete Subcategory" onclick="confirmDeleteHierarchy('subcategory', <?= $sc['id'] ?>, '<?= htmlspecialchars(addslashes($sc['name'])) ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. SUB-SUBCATEGORIES (TIER-3) -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-diagram-3 text-success me-1.5"></i> 3. Sub-Subcategories</h6>
                    <small class="text-muted" style="font-size: 0.72rem;">Tier-3 Garment Style Specification</small>
                </div>
                <?php if ($canAddHierarchy): ?>
                <button type="button" class="btn btn-sm btn-outline-success rounded-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalAddSubSubcategory">
                    <i class="bi bi-plus-circle me-1"></i> Add Tier-3
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover align-middle mb-0 small compact-hierarchy-table">
                        <thead class="table-light text-uppercase text-secondary" style="font-size: 0.70rem;">
                            <tr>
                                <th>Sub-Subcategory</th>
                                <th class="text-center">Code</th>
                                <th class="text-center">Complementary</th>
                                <?php if ($canDeleteHierarchy): ?><th class="text-center" style="width: 38px;">Del</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subSubcategories)): ?>
                                <tr><td colspan="<?= $canDeleteHierarchy ? '4' : '3' ?>" class="text-center py-3 text-muted">No Tier-3 categories created.</td></tr>
                            <?php else: ?>
                                <?php foreach ($subSubcategories as $ssc): ?>
                                    <?php $isSscComp = !empty($ssc['is_complementary']); ?>
                                    <tr>
                                        <td>
                                            <strong class="text-dark"><?= htmlspecialchars($ssc['name']) ?></strong>
                                            <div class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($ssc['category_name']) ?> &rsaquo; <?= htmlspecialchars($ssc['subcategory_name'] ?? 'General') ?></div>
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace">[<?= htmlspecialchars($ssc['code'] ?: 'SS') ?>]</span>
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <button type="button"
                                                    class="btn btn-sm py-0 px-2 fw-semibold <?= $isSscComp ? 'btn-warning text-dark' : 'btn-outline-secondary' ?>"
                                                    style="font-size: 0.72rem;"
                                                    title="<?= $isSscComp ? 'Click to disable complementary pricing' : 'Click to mark all products in this sub-subcategory as complementary (₹0 billed)' ?>"
                                                    onclick="toggleHierarchyComplementary('subsubcategory', <?= (int)$ssc['id'] ?>, <?= $isSscComp ? 0 : 1 ?>)">
                                                <i class="bi <?= $isSscComp ? 'bi-gift-fill' : 'bi-gift' ?> me-1"></i><?= $isSscComp ? 'Complementary' : 'Set Free' ?>
                                            </button>
                                        </td>
                                        <?php if ($canDeleteHierarchy): ?>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 border-0 shadow-none" title="Delete Tier-3 Sub-Subcategory" onclick="confirmDeleteHierarchy('subsubcategory', <?= $ssc['id'] ?>, '<?= htmlspecialchars(addslashes($ssc['name'])) ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. PRODUCT ATTRIBUTES (SIZES, COLORS, FITS) -->
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-tags-fill text-warning-emphasis me-1.5"></i> Product Attributes Master (Sizes, Colors, Fits)</h6>
                    <small class="text-muted" style="font-size: 0.72rem;">Reusable variant attributes automatically suggested during product creation</small>
                </div>
                <?php if ($canAddHierarchy): ?>
                <button type="button" class="btn btn-sm btn-outline-warning text-dark rounded-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalAddAttribute">
                    <i class="bi bi-plus-circle me-1"></i> Add Attribute
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
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
                        <p class="small mb-0">No attributes created yet. Add sizes, colors, fits, or custom attribute types here.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover align-middle mb-0 small compact-hierarchy-table">
                            <thead class="table-light text-uppercase text-secondary" style="font-size: 0.70rem;">
                                <tr>
                                    <th style="width: 220px;">Attribute Type</th>
                                    <th>Available Values</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupedAttrs as $type => $typeAttrs): ?>
                                    <tr>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars(ucfirst($type)) ?></td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1.5">
                                                <?php foreach ($typeAttrs as $sa):
                                                    $splitVals = preg_split('/[,;|]+/', (string)$sa['attribute_value']);
                                                    foreach ($splitVals as $sv):
                                                        $sv = trim($sv);
                                                        if ($sv === '') continue;
                                                ?>
                                                    <span class="badge bg-light text-dark border px-2 py-1 font-monospace d-inline-flex align-items-center gap-1">
                                                        <span><?= htmlspecialchars($sv) ?></span>
                                                        <?php if ($canDeleteHierarchy): ?>
                                                        <button type="button" class="btn btn-link text-danger p-0 ms-1 text-decoration-none shadow-none" style="font-size: 0.75rem; line-height: 1;" title="Delete Attribute <?= htmlspecialchars($sv) ?>" onclick="confirmDeleteHierarchy('attribute', <?= $sa['id'] ?>, '<?= htmlspecialchars(addslashes($sv)) ?>', '<?= htmlspecialchars(addslashes($sv)) ?>')">
                                                            <i class="bi bi-x-circle-fill"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endforeach; endforeach; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 5. HSN MASTER -->
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-upc-scan text-primary me-1.5"></i> HSN Master</h6>
                    <small class="text-muted" style="font-size: 0.72rem;">Active goods HSN codes available for category and product configuration</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace" id="catalogHsnCount">
                        <?= count($hsnCodes) ?> HSN Codes
                    </span>
                    <div class="input-group input-group-sm" style="width: min(100%, 260px);">
                        <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control" id="catalogHsnSearch" placeholder="Search HSN, description, or category" aria-label="Search HSN master">
                    </div>
                    <?php if ($canAddHierarchy): ?>
                    <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm d-flex align-items-center gap-1.5 text-nowrap" data-bs-toggle="modal" data-bs-target="#modalAddHsn">
                        <i class="bi bi-plus-circle"></i> Add HSN Code
                    </button>
                    <?php endif; ?>
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
                        <table class="table table-sm table-bordered table-hover align-middle mb-0 small compact-hierarchy-table">
                            <thead class="table-light text-uppercase text-secondary position-sticky top-0" style="z-index: 1; font-size: 0.70rem;">
                                <tr>
                                    <th class="ps-3 text-nowrap">HSN Code</th>
                                    <th>Description</th>
                                    <th>Category</th>
                                    <th class="text-nowrap">GST Rate</th>
                                    <th class="pe-3 text-end text-nowrap" style="width: 100px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="catalogHsnTableBody">
                                <?php foreach ($hsnCodes as $hsn): ?>
                                    <tr class="catalog-hsn-row" data-search="<?= htmlspecialchars(strtolower(implode(' ', [
                                        $hsn['hsn_code'] ?? '',
                                        $hsn['code_type'] ?? '',
                                        $hsn['description'] ?? '',
                                        $hsn['category'] ?? '',
                                    ])), ENT_QUOTES, 'UTF-8') ?>">
                                        <td class="ps-3">
                                            <div class="d-flex align-items-center gap-1.5">
                                                <span class="font-monospace fw-semibold text-primary"><?= htmlspecialchars($hsn['hsn_code']) ?></span>
                                            </div>
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
                                        <td class="pe-3 text-end text-nowrap">
                                            <?php
                                            $canEditHsn = \App\Core\Auth::isSuperAdmin() || (\App\Core\Auth::can($hierarchyModule, 'edit') && $hsn['concern_id'] !== null);
                                            $canDeleteHsn = \App\Core\Auth::isSuperAdmin() || (\App\Core\Auth::can($hierarchyModule, 'delete') && $hsn['concern_id'] !== null);
                                            ?>
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($canEditHsn): ?>
                                                <button type="button" class="btn btn-outline-primary btn-sm px-2 py-0.5" title="Edit HSN Code"
                                                        onclick='openEditHsnModal(<?= json_encode($hsn, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <?php endif; ?>
                                                <?php if ($canDeleteHsn): ?>
                                                <button type="button" class="btn btn-outline-danger btn-sm px-2 py-0.5" title="Delete HSN Code"
                                                        onclick="confirmDeleteHsn(<?= (int)$hsn['id'] ?>, '<?= htmlspecialchars($hsn['hsn_code'], ENT_QUOTES) ?>', '<?= htmlspecialchars($hsn['description'] ?? '', ENT_QUOTES) ?>')">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
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

    <!-- 6. VARIANT ID MASTER (ALL PRODUCT VARIANT DETAILS) -->
    <?php $variantDetails = $variantDetails ?? []; ?>
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-upc text-primary me-1.5"></i> Variant ID Details</h6>
                    <small class="text-muted" style="font-size: 0.75rem;">All registered product Variant IDs with 3-tier hierarchy, attributes, selling price, created date, and live stock</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace">
                        <?= count($variantDetails) ?> Variant IDs
                    </span>
                    <div class="input-group input-group-sm" style="width: min(100%, 280px);">
                        <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control" id="catalogVariantSearch" placeholder="Search Variant ID, product, category..." aria-label="Search Variant IDs">
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($variantDetails)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-boxes fs-2 text-secondary d-block mb-2"></i>
                        <p class="small mb-0">No product variants registered yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive" style="max-height: 480px;">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light position-sticky top-0" style="z-index: 1;">
                                <tr>
                                    <th class="ps-3 text-nowrap">Variant ID</th>
                                    <th>Product Name</th>
                                    <th>3-Tier Hierarchy</th>
                                    <th>Size &amp; Attributes</th>
                                    <th class="text-end text-nowrap">Selling Price</th>
                                    <th class="text-end text-nowrap">Stock Qty</th>
                                    <th class="text-nowrap">Created Date</th>
                                    <th class="pe-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody id="catalogVariantTableBody">
                                <?php foreach ($variantDetails as $vd): ?>
                                    <?php
                                        $attrSearch = implode(' ', array_map(fn($k, $v) => "$k $v", array_keys($vd['custom_attributes'] ?? []), $vd['custom_attributes'] ?? []));
                                        $searchHay = strtolower(implode(' ', [
                                            $vd['variant_id'] ?? '',
                                            $vd['product_code'] ?? '',
                                            $vd['name'] ?? '',
                                            $vd['hierarchy_path'] ?? '',
                                            $vd['size'] ?? '',
                                            $vd['color'] ?? '',
                                            $vd['hsn_code'] ?? '',
                                            $vd['concern_name'] ?? '',
                                            $attrSearch,
                                        ]));
                                    ?>
                                    <tr class="catalog-variant-row" data-search="<?= htmlspecialchars($searchHay, ENT_QUOTES, 'UTF-8') ?>">
                                        <td class="ps-3 text-nowrap">
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace fw-bold fs-6">#<?= (int)$vd['variant_id'] ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($vd['name']) ?></div>
                                            <?php if (!empty($vd['concern_name']) && empty($selectedCid)): ?>
                                                <small class="text-muted"><?= htmlspecialchars($vd['concern_name']) ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($vd['hsn_code'])): ?>
                                                <div class="small text-muted font-monospace">HSN: <?= htmlspecialchars($vd['hsn_code']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($vd['hierarchy_path'])): ?>
                                                <span class="small text-dark"><?= htmlspecialchars($vd['hierarchy_path']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted small">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php if (!empty($vd['size'])): ?>
                                                    <span class="badge bg-light text-dark border">Size: <?= htmlspecialchars($vd['size']) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($vd['color'])): ?>
                                                    <span class="badge bg-light text-dark border">Color: <?= htmlspecialchars($vd['color']) ?></span>
                                                <?php endif; ?>
                                                <?php foreach (($vd['custom_attributes'] ?? []) as $ak => $av): ?>
                                                    <span class="badge bg-light text-secondary border"><?= htmlspecialchars(ucfirst((string)$ak)) ?>: <?= htmlspecialchars((string)$av) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <div class="fw-bold text-success">₹<?= number_format((float)$vd['price'], 2) ?></div>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <span class="fw-bold <?= ((float)$vd['stock_qty'] > 0) ? 'text-primary' : 'text-muted' ?>">
                                                <?= number_format((float)$vd['stock_qty'], 0) ?> pcs
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            <small class="text-muted"><?= !empty($vd['creation_date']) ? htmlspecialchars(date('d M Y', strtotime((string)$vd['creation_date']))) : (!empty($vd['created_at']) ? htmlspecialchars(date('d M Y', strtotime((string)$vd['created_at']))) : '—') ?></small>
                                        </td>
                                        <td class="pe-3 text-center">
                                            <?php if (($vd['status'] ?? 'active') === 'active'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary border">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-none text-center text-muted small py-4" id="catalogVariantNoResults">No Variant IDs match your search.</div>
                    <div class="border-top px-3 py-2 text-muted small" id="catalogVariantVisibleCount" aria-live="polite">
                        Showing <?= count($variantDetails) ?> of <?= count($variantDetails) ?> registered Variant IDs
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php if ($canAddHierarchy): ?>
<!-- MODAL: ADD TIER-1 CATEGORY -->
<div class="modal fade" id="modalAddCategory" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= \App\Core\Controller::url($hierarchyPrefix . '/quick-category') ?>" method="POST" class="modal-content border-0 shadow hierarchy-quick-create-form">
            <input type="hidden" name="csrf_token" value="<?= $csrfVal ?>">
            <input type="hidden" name="tier" value="category">
            <input type="hidden" name="return_url" value="<?= htmlspecialchars($hierarchyReturn) ?>">
            <div class="modal-header bg-light">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-folder-plus text-primary me-1.5"></i> Add Tier-1 Main Category</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="hierarchy-form-feedback alert py-2 px-3 mb-3 small d-none" role="alert" aria-live="polite"></div>
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
        <form action="<?= \App\Core\Controller::url($hierarchyPrefix . '/quick-category') ?>" method="POST" class="modal-content border-0 shadow hierarchy-quick-create-form">
            <input type="hidden" name="csrf_token" value="<?= $csrfVal ?>">
            <input type="hidden" name="tier" value="subcategory">
            <input type="hidden" name="return_url" value="<?= htmlspecialchars($hierarchyReturn) ?>">
            <div class="modal-header bg-light">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-diagram-2 text-info me-1.5"></i> Add Tier-2 Subcategory</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="hierarchy-form-feedback alert py-2 px-3 mb-3 small d-none" role="alert" aria-live="polite"></div>
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
        <form action="<?= \App\Core\Controller::url($hierarchyPrefix . '/quick-category') ?>" method="POST" class="modal-content border-0 shadow hierarchy-quick-create-form">
            <input type="hidden" name="csrf_token" value="<?= $csrfVal ?>">
            <input type="hidden" name="tier" value="subsubcategory">
            <input type="hidden" name="return_url" value="<?= htmlspecialchars($hierarchyReturn) ?>">
            <div class="modal-header bg-light">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-diagram-3 text-success me-1.5"></i> Add Tier-3 Sub-Subcategory</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="hierarchy-form-feedback alert py-2 px-3 mb-3 small d-none" role="alert" aria-live="polite"></div>
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
        <form action="<?= \App\Core\Controller::url($hierarchyPrefix . '/quick-attribute') ?>" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= $csrfVal ?>">
            <input type="hidden" name="return_url" value="<?= htmlspecialchars($hierarchyReturn) ?>">
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
<?php endif; ?>

<?php if ($canDeleteHierarchy): ?>
<!-- CONFIRM DELETE HIERARCHY / ATTRIBUTE MODAL -->
<div class="modal fade" id="modalConfirmDeleteHierarchy" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= \App\Core\Controller::url($hierarchyPrefix . '/delete-hierarchy') ?>" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= $csrfVal ?>">
            <input type="hidden" name="type" id="deleteHierarchyType" value="">
            <input type="hidden" name="id" id="deleteHierarchyId" value="">
            <input type="hidden" name="value" id="deleteHierarchyValue" value="">
            <input type="hidden" name="return_url" value="<?= htmlspecialchars($hierarchyReturn) ?>">
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
<?php endif; ?>

<!-- DATALIST FOR HSN CATEGORIES AUTOCOMPLETE -->
<datalist id="hsnCategoriesList">
    <?php foreach (($hsnCategories ?? []) as $catName): ?>
        <option value="<?= htmlspecialchars($catName) ?>">
    <?php endforeach; ?>
</datalist>

<?php if ($canAddHierarchy): ?>
<!-- MODAL: ADD HSN MASTER CODE -->
<div class="modal fade" id="modalAddHsn" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form action="<?= \App\Core\Controller::url($hierarchyPrefix . '/create-hsn') ?>" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= $csrfVal ?>">
            <input type="hidden" name="return_url" value="<?= htmlspecialchars($hierarchyReturn) ?>">
            <div class="modal-header bg-light">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-upc-scan text-primary me-1.5"></i> Add HSN Master Code</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <input type="hidden" name="code_type" value="HSN">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">HSN Code <span class="text-danger">*</span></label>
                        <input type="text" name="hsn_code" class="form-control font-monospace fw-bold" placeholder="4, 6, or 8 digits" required maxlength="8" minlength="4" inputmode="numeric" pattern="[0-9]{4}|[0-9]{6}|[0-9]{8}" title="Enter a valid 4-, 6-, or 8-digit numeric HSN code">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Category</label>
                        <input type="text" name="category" list="hsnCategoriesList" class="form-control" placeholder="e.g. Apparel, Fabric, Packaging" maxlength="100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">GST Rate (%)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" max="100" name="gst_rate" class="form-control font-monospace" placeholder="0.00 to 100.00" required title="Enter a GST percentage from 0.00 to 100.00">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="e.g. T-shirts, singlets and other vests, knitted or crocheted" maxlength="255"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-3"><i class="bi bi-check-circle me-1"></i> Save HSN Code</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canEditHierarchy): ?>
<!-- MODAL: EDIT HSN MASTER CODE -->
<div class="modal fade" id="modalEditHsn" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form action="<?= \App\Core\Controller::url($hierarchyPrefix . '/update-hsn') ?>" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= $csrfVal ?>">
            <input type="hidden" name="return_url" value="<?= htmlspecialchars($hierarchyReturn) ?>">
            <input type="hidden" name="id" id="editHsnId" value="">
            <div class="modal-header bg-light">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-primary me-1.5"></i> Edit HSN Master Code</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <input type="hidden" name="code_type" value="HSN">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">HSN Code <span class="text-danger">*</span></label>
                        <input type="text" name="hsn_code" id="editHsnCode" class="form-control font-monospace fw-bold" required maxlength="8" minlength="4" inputmode="numeric" pattern="[0-9]{4}|[0-9]{6}|[0-9]{8}" title="Enter a valid 4-, 6-, or 8-digit numeric HSN code">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Category</label>
                        <input type="text" name="category" id="editHsnCategory" list="hsnCategoriesList" class="form-control" maxlength="100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">GST Rate (%)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" max="100" name="gst_rate" id="editHsnGstRate" class="form-control font-monospace" required title="Enter a GST percentage from 0.00 to 100.00">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="is_active" id="editHsnIsActive" class="form-select">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Description</label>
                        <textarea name="description" id="editHsnDescription" class="form-control" rows="2" maxlength="255"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-3"><i class="bi bi-check-circle me-1"></i> Update HSN Code</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canDeleteHierarchy): ?>
<!-- CONFIRM DELETE HSN MODAL -->
<div class="modal fade" id="modalConfirmDeleteHsn" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= \App\Core\Controller::url($hierarchyPrefix . '/delete-hsn') ?>" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= $csrfVal ?>">
            <input type="hidden" name="id" id="deleteHsnId" value="">
            <input type="hidden" name="return_url" value="<?= htmlspecialchars($hierarchyReturn) ?>">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-trash-fill me-2"></i> Delete HSN Code</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="bi bi-exclamation-triangle-fill text-danger fs-1 mb-2 d-block"></i>
                <h5 class="fw-bold text-dark mb-2">Delete <span id="deleteHsnCodeDisplay"></span>?</h5>
                <p class="text-secondary small mb-0">
                    Are you sure you want to delete this HSN code? If it is currently referenced in active catalog styles or categories, it will be safely deactivated to protect historical records.
                </p>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm px-3 fw-bold"><i class="bi bi-trash me-1"></i> Yes, Delete HSN</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<form id="hierarchyComplementaryForm" action="<?= \App\Core\Controller::url($hierarchyPrefix . '/toggle-hierarchy-complementary') ?>" method="POST" class="d-none">
    <input type="hidden" name="csrf_token" value="<?= $csrfVal ?>">
    <input type="hidden" name="return_url" value="<?= htmlspecialchars($hierarchyReturn) ?>">
    <input type="hidden" name="type" id="compHierarchyType" value="">
    <input type="hidden" name="id" id="compHierarchyId" value="">
    <input type="hidden" name="is_complementary" id="compHierarchyValue" value="0">
</form>

<script>
function toggleHierarchyComplementary(type, id, nextVal) {
    document.getElementById('compHierarchyType').value = type;
    document.getElementById('compHierarchyId').value = id;
    document.getElementById('compHierarchyValue').value = nextVal;
    document.getElementById('hierarchyComplementaryForm').submit();
}
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

function openEditHsnModal(data) {
    if (!data) return;
    document.getElementById('editHsnId').value = data.id || '';
    document.getElementById('editHsnCode').value = data.hsn_code || '';
    document.getElementById('editHsnCategory').value = data.category || '';
    document.getElementById('editHsnGstRate').value = (data.gst_rate !== null && data.gst_rate !== undefined) ? data.gst_rate : '';
    document.getElementById('editHsnDescription').value = data.description || '';
    if (document.getElementById('editHsnIsActive')) {
        document.getElementById('editHsnIsActive').value = (data.is_active != null) ? data.is_active : '1';
    }
    new bootstrap.Modal(document.getElementById('modalEditHsn')).show();
}

function confirmDeleteHsn(id, code, desc) {
    document.getElementById('deleteHsnId').value = id;
    document.getElementById('deleteHsnCodeDisplay').textContent = code + (desc ? ' (' + desc + ')' : '');
    new bootstrap.Modal(document.getElementById('modalConfirmDeleteHsn')).show();
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
    document.querySelectorAll('.hierarchy-quick-create-form').forEach(function(form) {
        const feedback = form.querySelector('.hierarchy-form-feedback');
        const submitButton = form.querySelector('button[type="submit"]');

        form.addEventListener('submit', async function(event) {
            event.preventDefault();
            if (!feedback || !submitButton || submitButton.disabled) return;

            feedback.classList.add('d-none');
            feedback.textContent = '';
            submitButton.disabled = true;

            try {
                const formData = new FormData(form);
                formData.append('ajax', '1');
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: formData
                });
                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Unable to save this catalog entry.');
                }

                feedback.classList.remove('d-none', 'alert-danger');
                feedback.classList.add('alert-success');
                feedback.textContent = result.message || 'Catalog entry saved successfully.';

                const returnUrl = form.querySelector('[name="return_url"]');
                window.setTimeout(function() {
                    window.location.assign(returnUrl ? returnUrl.value : window.location.href);
                }, 700);
            } catch (error) {
                feedback.classList.remove('d-none', 'alert-success');
                feedback.classList.add('alert-danger');
                feedback.textContent = error instanceof Error
                    ? error.message
                    : 'Unable to save this catalog entry. Please try again.';
                submitButton.disabled = false;
            }
        });

        form.querySelectorAll('input, select, textarea').forEach(function(field) {
            field.addEventListener('input', function() {
                feedback.classList.add('d-none');
                feedback.textContent = '';
            });
            field.addEventListener('change', function() {
                feedback.classList.add('d-none');
                feedback.textContent = '';
            });
        });
    });

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

    const variantSearch = document.getElementById('catalogVariantSearch');
    const variantRows = Array.from(document.querySelectorAll('.catalog-variant-row'));
    const variantNoResults = document.getElementById('catalogVariantNoResults');
    const variantVisibleCount = document.getElementById('catalogVariantVisibleCount');
    if (variantSearch) {
        variantSearch.addEventListener('input', function() {
            const query = variantSearch.value.trim().toLowerCase();
            let visibleCount = 0;
            variantRows.forEach(function(row) {
                const matches = (row.dataset.search || '').includes(query);
                row.classList.toggle('d-none', !matches);
                if (matches) visibleCount++;
            });
            if (variantNoResults) variantNoResults.classList.toggle('d-none', visibleCount > 0);
            if (variantVisibleCount) {
                variantVisibleCount.textContent = `Showing ${visibleCount} of ${variantRows.length} registered Variant IDs`;
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
