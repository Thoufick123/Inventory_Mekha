<?php
// app/views/concern/catalog_create.php - Add / Edit Product Style Form
$isEdit = !empty($isEdit);
$selectedSizes = [];
if ($isEdit && !empty($style['sizes'])) {
    $decoded = json_decode($style['sizes'], true);
    $selectedSizes = is_array($decoded) ? $decoded : explode(',', $style['sizes']);
}
if (!$isEdit) {
    // Default initial selections (e.g. XXL or S, M, L)
    $selectedSizes = ['S', 'M', 'L', 'XS', 'XL', 'XXL'];
}
?>
<div class="container-fluid px-0">
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/') ?>" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item text-muted">Production</li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/catalog') ?>" class="text-decoration-none text-muted">Catalog</a></li>
            <li class="breadcrumb-item active fw-semibold text-dark" aria-current="page"><?= $isEdit ? 'Edit Style Form' : 'Add Style Form' ?></li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <div class="p-2.5 rounded-3 bg-primary-subtle text-primary border border-primary-subtle shadow-xs">
                <i class="bi bi-folder-plus fs-4"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-0 text-dark"><?= $isEdit ? 'Edit Product Style' : 'Add Product Style' ?></h4>
            </div>
        </div>
        <div>
            <a href="<?= \App\Core\Controller::url('/production/catalog') ?>" class="btn btn-outline-secondary rounded-3 d-inline-flex align-items-center gap-1.5 shadow-sm fw-semibold">
                <i class="bi bi-arrow-left"></i> Back to Catalog
            </a>
        </div>
    </div>

    <!-- Main Form Card -->
    <form action="<?= \App\Core\Controller::url($isEdit ? '/production/catalog/update' : '/production/catalog/store') ?>" method="POST" id="formProductStyle" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int)$style['id'] ?>">
        <?php endif; ?>

        <!-- SECTION 1: CATEGORY HIERARCHY (3-TIER) -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-diagram-3-fill text-primary"></i> 1. Category Hierarchy (3-Tier)
                </h6>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1">Required</span>
            </div>
            <div class="card-body p-4">
                <!-- Tier Dropdowns -->
                <div class="row g-3 mb-3">
                    <!-- Main Category -->
                    <div class="col-md-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-semibold text-dark mb-0">Main Category <span class="text-danger">*</span></label>
                            <a href="#" class="text-primary text-decoration-none small fw-semibold" data-bs-toggle="modal" data-bs-target="#modalAddCategory">
                                <i class="bi bi-plus-circle me-1"></i>New
                            </a>
                        </div>
                        <select name="category_id" id="selectMainCategory" class="form-select" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): 
                                $isSel = ($isEdit && $style['category_id'] == $cat['id']) || (!$isEdit && $cat['name'] === 'KIDS');
                            ?>
                                <option value="<?= $cat['id'] ?>" 
                                        data-name="<?= htmlspecialchars($cat['name']) ?>"
                                        data-code="<?= htmlspecialchars($cat['code'] ?? '') ?>"
                                        data-hsn="<?= htmlspecialchars($cat['default_hsn'] ?? '') ?>"
                                        data-gst="<?= htmlspecialchars((string)($cat['default_gst_rate'] ?? '')) ?>"
                                        <?= $isSel ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please choose a Main Category.</div>
                    </div>

                    <!-- Sub Category -->
                    <div class="col-md-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-semibold text-dark mb-0">Sub Category</label>
                            <a href="#" class="text-primary text-decoration-none small fw-semibold" id="btnOpenNewSubcategory">
                                <i class="bi bi-plus-circle me-1"></i>New
                            </a>
                        </div>
                        <select name="subcategory_id" id="selectSubCategory" class="form-select">
                            <option value="">-- Select Category First --</option>
                            <?php if ($isEdit && !empty($subcategories)): ?>
                                <?php foreach ($subcategories as $sub): ?>
                                    <option value="<?= $sub['id'] ?>" 
                                            data-name="<?= htmlspecialchars($sub['name']) ?>"
                                            data-code="<?= htmlspecialchars($sub['code'] ?? '') ?>"
                                            <?= ($style['subcategory_id'] == $sub['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sub['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Sub Sub Category -->
                    <div class="col-md-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-semibold text-dark mb-0">Sub Sub Category</label>
                            <a href="#" class="text-primary text-decoration-none small fw-semibold" id="btnOpenNewSubSubcategory">
                                <i class="bi bi-plus-circle me-1"></i>New
                            </a>
                        </div>
                        <select name="sub_sub_category_id" id="selectSubSubCategory" class="form-select">
                            <option value="">-- Select Sub Category First --</option>
                            <?php if ($isEdit && !empty($subSubcategories)): ?>
                                <?php foreach ($subSubcategories as $subsub): ?>
                                    <option value="<?= $subsub['id'] ?>" 
                                            data-name="<?= htmlspecialchars($subsub['name']) ?>"
                                            data-code="<?= htmlspecialchars($subsub['code'] ?? '') ?>"
                                            <?= ($style['sub_sub_category_id'] == $subsub['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($subsub['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <!-- Hierarchy Path Display -->
                <div class="p-2.5 rounded-3 bg-light border text-muted small d-flex align-items-center gap-2 mb-4">
                    <i class="bi bi-folder2-open text-primary fs-6"></i>
                    <span class="fw-semibold text-secondary">Hierarchy Path:</span>
                    <span id="hierarchyPathText" class="text-dark fw-semibold">
                        <?= $isEdit ? htmlspecialchars($style['hierarchy_path'] ?? 'Select a category above...') : 'Select a category above...' ?>
                    </span>
                </div>

                <!-- Category Short Codes & Whole Code for QR Code -->
                <div class="border-top pt-3 mt-3">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-qr-code text-primary"></i>
                        <span class="fw-bold small text-dark">Category Short Codes & Whole Code for QR Code</span>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-secondary mb-1">Main Category Code</label>
                            <input type="text" name="main_category_code" id="inputMainCatCode" class="form-control text-uppercase font-monospace" placeholder="E.G. W" maxlength="10" value="<?= htmlspecialchars($style['main_category_code'] ?? '') ?>" required>
                            <div class="invalid-feedback">Main code required.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-secondary mb-1">Sub Category Code</label>
                            <input type="text" name="sub_category_code" id="inputSubCatCode" class="form-control text-uppercase font-monospace" placeholder="E.G. M" maxlength="10" value="<?= htmlspecialchars($style['sub_category_code'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-secondary mb-1">Sub Sub Category Code</label>
                            <input type="text" name="sub_sub_category_code" id="inputSubSubCatCode" class="form-control text-uppercase font-monospace" placeholder="E.G. F" maxlength="10" value="<?= htmlspecialchars($style['sub_sub_category_code'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-primary mb-1">Whole Code for QR Code <span class="text-danger">*</span></label>
                            <input type="text" name="whole_code" id="inputWholeCode" class="form-control text-uppercase font-monospace border-primary bg-primary-subtle text-primary fw-bold" placeholder="E.G. WMF" maxlength="30" value="<?= htmlspecialchars($style['short_code'] ?? '') ?>" required>
                            <div class="invalid-feedback">Whole QR code is required.</div>
                        </div>
                    </div>
                </div>

                <!-- HSN Code & GST Tax Classification -->
                <div class="border-top pt-3 mt-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-receipt text-primary"></i>
                        <span class="fw-bold small text-dark">HSN Code & GST Tax Classification</span>
                    </div>

                    <!-- Category Default Banner Matching Image 2 -->
                    <div class="alert alert-info py-2 px-3 rounded-3 d-flex align-items-center justify-content-between mb-3 border-0 bg-primary-subtle text-primary flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <i class="bi bi-info-circle-fill fs-5"></i>
                            <span>Category Default: <strong id="catHsnDisplay">No default HSN/SAC</strong></span>
                            <button type="button" class="btn btn-sm btn-primary py-0.5 px-2 ms-2 fw-semibold shadow-xs" id="btnApplyCategoryHsn">
                                <i class="bi bi-check-lg me-1"></i> Apply Category HSN
                            </button>
                        </div>
                        <div>
                            <span class="badge bg-white text-secondary border px-2 py-1 small">Dual HSN: Category Default & Product-Specific Override</span>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-semibold text-dark mb-0">HSN Code <span class="text-danger">*</span></label>
                                <a href="#" class="text-primary text-decoration-none small fw-semibold" data-bs-toggle="modal" data-bs-target="#modalAddHsn">
                                    <i class="bi bi-plus-circle me-1"></i>Add New HSN
                                </a>
                            </div>
                            <div class="input-group">
                                <input type="text" name="hsn_code" id="inputHsnCode" class="form-control font-monospace fw-semibold" placeholder="Type an HSN code or description" value="<?= htmlspecialchars($style['hsn_code'] ?? '') ?>" data-hsn-autocomplete data-hsn-type="HSN" data-hsn-gst-target="#inputGstRate" data-hsn-note-target="#hsnRateNote" autocomplete="off" required>
                                <button class="btn btn-outline-secondary" type="button" id="btnClearHsn" title="Clear HSN"><i class="bi bi-x-lg"></i></button>
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">Search and select a textile classification from the shared HSN master.</small>
                            <div class="invalid-feedback">HSN code is required.</div>
                        </div>

                        <!-- GST Rate -->
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-dark mb-1">Default GST Rate (%) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" max="100" name="gst_rate" id="inputGstRate" class="form-control fw-semibold" placeholder="Enter applicable rate" value="<?= htmlspecialchars($style['gst_rate'] ?? '') ?>" required>
                                <span class="input-group-text bg-white text-muted">%</span>
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">Confirm any rate conditions shown for the selected HSN.</small>
                            <div class="invalid-feedback">Valid GST rate is required.</div>
                            <small class="text-muted d-block mt-1" id="hsnRateNote"></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: PRODUCT ATTRIBUTES (SIZES) -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-aspect-ratio text-primary"></i> 2. Product Attributes
                </h6>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-3 fw-semibold" id="btnOpenAddSize">
                        <i class="bi bi-plus-circle me-1"></i> Add Size
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-3" id="btnSelectAllSizes">Select All</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-3" id="btnClearSizes">Clear</button>
                </div>
            </div>
            <div class="card-body p-4">
                <label class="form-label small fw-bold text-dark mb-2 d-flex align-items-center gap-1.5">
                    <i class="bi bi-tag text-secondary"></i> Size Attributes:
                </label>

                <!-- Interactive Size Chips Container Matching Image 2 -->
                <div class="d-flex align-items-center gap-2 flex-wrap" id="sizeChipsContainer">
                    <?php
                    $availableSizes = ['S', 'M', 'L', 'XS', 'XL', 'XXL'];
                    if (!empty($attributeSizes)) {
                        foreach ($attributeSizes as $as) {
                            $val = is_array($as) ? ($as['attribute_value'] ?? '') : (string)$as;
                            if ($val !== '' && !in_array($val, $availableSizes)) {
                                $availableSizes[] = $val;
                            }
                        }
                    }
                    foreach ($selectedSizes as $ss) {
                        if (!in_array($ss, $availableSizes)) {
                            $availableSizes[] = $ss;
                        }
                    }

                    foreach ($availableSizes as $sz):
                        $isChecked = in_array($sz, $selectedSizes);
                    ?>
                        <div class="size-chip-wrapper" data-size="<?= htmlspecialchars($sz) ?>">
                            <label class="size-chip <?= $isChecked ? 'active' : '' ?>">
                                <input type="checkbox" name="sizes[]" value="<?= htmlspecialchars($sz) ?>" class="size-checkbox" <?= $isChecked ? 'checked' : '' ?>>
                                <span class="size-label"><?= htmlspecialchars($sz) ?></span>
                                <button type="button" class="btn-remove-size" title="Remove size"><i class="bi bi-trash"></i></button>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="small text-danger mt-2 d-none" id="sizeValidationMsg">
                    <i class="bi bi-exclamation-circle me-1"></i> Please select at least one size attribute.
                </div>
            </div>
        </div>

        <!-- Optional Custom Name Field -->
        <input type="hidden" name="name" id="hiddenStyleName" value="<?= htmlspecialchars($style['name'] ?? '') ?>">

        <!-- Form Actions Bar -->
        <div class="d-flex justify-content-between align-items-center p-3 bg-white rounded-3 shadow-sm border">
            <a href="<?= \App\Core\Controller::url('/production/catalog') ?>" class="btn btn-light border px-4 py-2 fw-semibold text-secondary">
                Cancel
            </a>
            <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold shadow-sm d-inline-flex align-items-center gap-2" id="btnSaveStyle">
                <i class="bi bi-check2-circle fs-5"></i> 
                <?= $isEdit ? 'Update Product Style' : 'Save Product Style to Catalog' ?>
            </button>
        </div>
    </form>
</div>

<!-- ======================================================== -->
<!-- MODALS FOR QUICK-ADD (CATEGORY, SUBCAT, SUBSUBCAT, HSN, SIZE) -->
<!-- ======================================================== -->

<!-- MODAL 1: ADD CATEGORY -->
<div class="modal fade" id="modalAddCategory" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow" id="formQuickAddCategory">
            <div class="modal-header bg-primary text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-folder-plus me-1.5"></i> Quick Add Main Category</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Category Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. MEN, WOMEN, KIDS" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Short Code (1-5 chars) *</label>
                    <input type="text" name="code" class="form-control text-uppercase font-monospace" placeholder="e.g. K, M, W" maxlength="5" required>
                </div>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Default HSN</label>
                        <input type="text" name="default_hsn" id="catalogCategoryDefaultHsn" class="form-control font-monospace" placeholder="Search HSN" data-hsn-autocomplete data-hsn-type="HSN" data-hsn-gst-target="#catalogCategoryDefaultGstRate" autocomplete="off">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Default GST (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="default_gst_rate" id="catalogCategoryDefaultGstRate" class="form-control" placeholder="Optional">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-3">Save Category</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 2: ADD SUBCATEGORY -->
<div class="modal fade" id="modalAddSubcategory" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow" id="formQuickAddSubcategory">
            <div class="modal-header bg-primary text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-folder-plus me-1.5"></i> Quick Add Sub Category</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" name="category_id" id="quickAddSubcatParentId" value="">
                <div class="mb-2">
                    <span class="small text-muted">Parent Category:</span>
                    <strong class="text-dark d-block" id="quickAddSubcatParentName">-</strong>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Sub Category Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. BABY, TEEN, ADULT" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Short Code (1-5 chars)</label>
                    <input type="text" name="code" class="form-control text-uppercase font-monospace" placeholder="e.g. B, T, A" maxlength="5">
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-3">Save Subcategory</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 3: ADD SUB-SUB-CATEGORY -->
<div class="modal fade" id="modalAddSubSubcategory" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow" id="formQuickAddSubSubcategory">
            <div class="modal-header bg-primary text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-folder-plus me-1.5"></i> Quick Add Sub-Sub Category</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" name="subcategory_id" id="quickAddSubSubcatParentId" value="">
                <div class="mb-2">
                    <span class="small text-muted">Parent Subcategory:</span>
                    <strong class="text-dark d-block" id="quickAddSubSubcatParentName">-</strong>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Sub-Sub Category Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. DRESS, SHIRT, FROCK" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Short Code (1-5 chars)</label>
                    <input type="text" name="code" class="form-control text-uppercase font-monospace" placeholder="e.g. D, S, F" maxlength="5">
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-3">Save Sub-Subcategory</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 4: ADD HSN MASTER -->
<div class="modal fade" id="modalAddHsn" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow" id="formQuickAddHsn">
            <div class="modal-header bg-primary text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-receipt me-1.5"></i> Quick Add HSN Code Master</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">HSN Code *</label>
                    <input type="text" name="hsn_code" class="form-control font-monospace" placeholder="e.g. 6109" data-hsn-autocomplete data-hsn-type="HSN" data-hsn-gst-target="#catalogHsnMasterGstRate" autocomplete="off" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Commodity Description</label>
                    <input type="text" name="description" class="form-control" placeholder="e.g. Cotton T-shirts, knitted garments">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">GST Rate (%) (Optional)</label>
                    <input type="number" step="0.01" min="0" max="100" name="gst_rate" id="catalogHsnMasterGstRate" class="form-control" placeholder="Leave blank if conditional">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">GST Rate Conditions / Notes</label>
                    <input type="text" name="rate_note" class="form-control" maxlength="500" placeholder="e.g. Verify based on product composition and value">
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-3">Save HSN</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 5: ADD CUSTOM SIZE -->
<div class="modal fade" id="modalAddSize" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-2">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-plus-circle text-primary me-1"></i> Add Custom Size</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <label class="form-label small fw-semibold">Size Label *</label>
                <input type="text" id="customSizeInput" class="form-control text-uppercase font-monospace" placeholder="e.g. 3XL, 38, 40">
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnConfirmAddSize">Add Size</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom styling for size chips matching Image 2 */
.size-chip-wrapper {
    display: inline-block;
}
.size-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.65rem;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #334155;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    user-select: none;
    transition: all 0.15s ease-in-out;
}
.size-chip:hover {
    border-color: #94a3b8;
    background: #f8fafc;
}
.size-chip.active {
    background: #1e293b !important;
    border-color: #0f172a !important;
    color: #ffffff !important;
    box-shadow: 0 2px 4px rgba(15, 23, 42, 0.2);
}
.size-chip .size-checkbox {
    margin: 0;
    cursor: pointer;
    accent-color: #2563eb;
}
.size-chip .btn-remove-size {
    border: none;
    background: transparent;
    color: #ef4444;
    padding: 0;
    line-height: 1;
    font-size: 0.82rem;
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    opacity: 0.7;
    transition: opacity 0.15s;
}
.size-chip .btn-remove-size:hover {
    opacity: 1;
}
.size-chip.active .btn-remove-size {
    color: #fca5a5;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mainCatSelect = document.getElementById('selectMainCategory');
    const subCatSelect = document.getElementById('selectSubCategory');
    const subSubCatSelect = document.getElementById('selectSubSubCategory');

    const mainCodeInput = document.getElementById('inputMainCatCode');
    const subCodeInput = document.getElementById('inputSubCatCode');
    const subSubCodeInput = document.getElementById('inputSubSubCatCode');
    const wholeCodeInput = document.getElementById('inputWholeCode');

    const hierarchyPathText = document.getElementById('hierarchyPathText');
    const catHsnDisplay = document.getElementById('catHsnDisplay');
    const btnApplyCategoryHsn = document.getElementById('btnApplyCategoryHsn');

    const inputHsnCode = document.getElementById('inputHsnCode');
    const inputGstRate = document.getElementById('inputGstRate');
    const btnClearHsn = document.getElementById('btnClearHsn');

    const hsnRateNote = document.getElementById('hsnRateNote');

    let currentCatDefaultHsn = '';
    let currentCatDefaultGst = '';

    // Helper: update Hierarchy Path & Whole Code
    function updateHierarchyAndCodes() {
        const catOpt = mainCatSelect.selectedOptions[0];
        const subOpt = subCatSelect.selectedOptions[0];
        const subSubOpt = subSubCatSelect.selectedOptions[0];

        const catName = catOpt && catOpt.value ? catOpt.getAttribute('data-name') || catOpt.text.trim() : '';
        const subName = subOpt && subOpt.value ? subOpt.getAttribute('data-name') || subOpt.text.trim() : '';
        const subSubName = subSubOpt && subSubOpt.value ? subSubOpt.getAttribute('data-name') || subSubOpt.text.trim() : '';

        // Update hierarchy text
        const parts = [catName, subName, subSubName].filter(Boolean);
        if (parts.length > 0) {
            hierarchyPathText.innerHTML = parts.map((p, i) => `
                <span class="badge bg-white text-dark border px-2 py-0.5">${p}</span>
                ${i < parts.length - 1 ? '<i class="bi bi-chevron-right text-muted small mx-1"></i>' : ''}
            `).join('');
            document.getElementById('hiddenStyleName').value = parts.join(' - ');
        } else {
            hierarchyPathText.textContent = 'Select a category above...';
            document.getElementById('hiddenStyleName').value = '';
        }

        // Auto combine Whole Code if user hasn't explicitly customized it, or keep it updated
        const mCode = (mainCodeInput.value || '').trim().toUpperCase();
        const sCode = (subCodeInput.value || '').trim().toUpperCase();
        const ssCode = (subSubCodeInput.value || '').trim().toUpperCase();
        
        wholeCodeInput.value = (mCode + sCode + ssCode).toUpperCase();
    }

    // Main Category Change
    mainCatSelect.addEventListener('change', function() {
        const opt = this.selectedOptions[0];
        if (!opt || !opt.value) {
            subCatSelect.innerHTML = '<option value="">-- Select Category First --</option>';
            subSubCatSelect.innerHTML = '<option value="">-- Select Sub Category First --</option>';
            mainCodeInput.value = '';
            catHsnDisplay.textContent = '-';
            updateHierarchyAndCodes();
            return;
        }

        const code = opt.getAttribute('data-code') || opt.text.trim().substring(0, 1);
        mainCodeInput.value = code.toUpperCase();

        currentCatDefaultHsn = opt.getAttribute('data-hsn') || '';
        currentCatDefaultGst = opt.getAttribute('data-gst') || '';
        catHsnDisplay.textContent = currentCatDefaultHsn
            ? `${currentCatDefaultHsn} (${currentCatDefaultGst ? `${parseFloat(currentCatDefaultGst).toFixed(2)}% GST` : 'verify GST rate'})`
            : 'No default HSN/SAC';

        // Load Subcategories via AJAX
        subCatSelect.innerHTML = '<option value="">Loading subcategories...</option>';
        subSubCatSelect.innerHTML = '<option value="">-- Select Sub Category First --</option>';
        subCodeInput.value = '';
        subSubCodeInput.value = '';

        fetch('<?= \App\Core\Controller::url('/api/categories/subcategories?category_id=') ?>' + opt.value)
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data.length > 0) {
                    let html = '<option value="">-- Select Subcategory --</option>';
                    res.data.forEach(item => {
                        html += `<option value="${item.id}" data-name="${item.name}" data-code="${item.code || ''}">${item.name}</option>`;
                    });
                    subCatSelect.innerHTML = html;
                } else {
                    subCatSelect.innerHTML = '<option value="">-- No Subcategories (Add New) --</option>';
                }
                updateHierarchyAndCodes();
            })
            .catch(() => {
                subCatSelect.innerHTML = '<option value="">-- Error loading --</option>';
            });
    });

    // Subcategory Change
    subCatSelect.addEventListener('change', function() {
        const opt = this.selectedOptions[0];
        if (!opt || !opt.value) {
            subSubCatSelect.innerHTML = '<option value="">-- Select Sub Category First --</option>';
            subCodeInput.value = '';
            subSubCodeInput.value = '';
            updateHierarchyAndCodes();
            return;
        }

        const code = opt.getAttribute('data-code') || opt.text.trim().substring(0, 1);
        subCodeInput.value = code.toUpperCase();

        // Load Sub-Subcategories via AJAX
        subSubCatSelect.innerHTML = '<option value="">Loading sub-subcategories...</option>';
        subSubCodeInput.value = '';

        fetch('<?= \App\Core\Controller::url('/api/categories/subsubcategories?subcategory_id=') ?>' + opt.value)
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data.length > 0) {
                    let html = '<option value="">-- Select Sub-Subcategory --</option>';
                    res.data.forEach(item => {
                        html += `<option value="${item.id}" data-name="${item.name}" data-code="${item.code || ''}">${item.name}</option>`;
                    });
                    subSubCatSelect.innerHTML = html;
                } else {
                    subSubCatSelect.innerHTML = '<option value="">-- No Sub-Subcategories (Add New) --</option>';
                }
                updateHierarchyAndCodes();
            })
            .catch(() => {
                subSubCatSelect.innerHTML = '<option value="">-- Error loading --</option>';
            });
    });

    // Sub-Subcategory Change
    subSubCatSelect.addEventListener('change', function() {
        const opt = this.selectedOptions[0];
        if (opt && opt.value) {
            const code = opt.getAttribute('data-code') || opt.text.trim().substring(0, 1);
            subSubCodeInput.value = code.toUpperCase();
        } else {
            subSubCodeInput.value = '';
        }
        updateHierarchyAndCodes();
    });

    // Code Inputs change event
    [mainCodeInput, subCodeInput, subSubCodeInput].forEach(inp => {
        inp.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
            wholeCodeInput.value = ((mainCodeInput.value || '') + (subCodeInput.value || '') + (subSubCodeInput.value || '')).toUpperCase();
        });
    });

    wholeCodeInput.addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });

    // Apply Category HSN button
    btnApplyCategoryHsn.addEventListener('click', function() {
        inputHsnCode.value = currentCatDefaultHsn;
        inputGstRate.value = currentCatDefaultGst === '' ? '' : parseFloat(currentCatDefaultGst).toFixed(2);
        hsnRateNote.textContent = currentCatDefaultGst === '' ? 'Verify the applicable GST rate for this classification.' : '';
        btnApplyCategoryHsn.innerHTML = '<i class="bi bi-check2-all me-1"></i> Applied!';
        setTimeout(() => {
            btnApplyCategoryHsn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Apply Category HSN';
        }, 1500);
    });

    // Clear HSN
    btnClearHsn.addEventListener('click', function() {
        inputHsnCode.value = '';
        inputGstRate.value = '';
        hsnRateNote.textContent = '';
        inputHsnCode.focus();
    });

    // ========================================================
    // SIZE CHIPS LOGIC
    // ========================================================
    const chipsContainer = document.getElementById('sizeChipsContainer');
    const sizeValidationMsg = document.getElementById('sizeValidationMsg');

    function bindChipEvents() {
        chipsContainer.querySelectorAll('.size-chip-wrapper').forEach(wrapper => {
            const chip = wrapper.querySelector('.size-chip');
            const checkbox = wrapper.querySelector('.size-checkbox');
            const removeBtn = wrapper.querySelector('.btn-remove-size');

            // Toggle chip
            checkbox.onchange = function() {
                if (this.checked) {
                    chip.classList.add('active');
                } else {
                    chip.classList.remove('active');
                }
                validateSizes(false);
            };

            // Remove button
            removeBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                const sizeVal = (wrapper.getAttribute('data-size') || '').trim();
                wrapper.remove();
                validateSizes(false);

                // Delete from product_attributes in database via AJAX
                if (sizeVal) {
                    const fd = new FormData();
                    fd.append('attribute_type', 'size');
                    fd.append('attribute_value', sizeVal);
                    fetch('<?= \App\Core\Controller::url('/api/attributes/delete') ?>', {
                        method: 'POST',
                        body: fd
                    }).catch(err => console.error('Failed to delete attribute:', err));
                }
            };
        });
    }
    bindChipEvents();

    function validateSizes(showError = true) {
        const checked = chipsContainer.querySelectorAll('.size-checkbox:checked').length;
        if (checked === 0) {
            if (showError) sizeValidationMsg.classList.remove('d-none');
            return false;
        } else {
            sizeValidationMsg.classList.add('d-none');
            return true;
        }
    }

    // Select All Sizes
    document.getElementById('btnSelectAllSizes').addEventListener('click', function() {
        chipsContainer.querySelectorAll('.size-checkbox').forEach(cb => {
            cb.checked = true;
            cb.closest('.size-chip').classList.add('active');
        });
        validateSizes(false);
    });

    // Clear Sizes
    document.getElementById('btnClearSizes').addEventListener('click', function() {
        chipsContainer.querySelectorAll('.size-checkbox').forEach(cb => {
            cb.checked = false;
            cb.closest('.size-chip').classList.remove('active');
        });
        validateSizes(false);
    });

    // Add Custom Size Modal
    const modalAddSize = new bootstrap.Modal(document.getElementById('modalAddSize'));
    const customSizeInput = document.getElementById('customSizeInput');

    document.getElementById('btnOpenAddSize').addEventListener('click', function() {
        customSizeInput.value = '';
        modalAddSize.show();
        setTimeout(() => customSizeInput.focus(), 300);
    });

    document.getElementById('btnConfirmAddSize').addEventListener('click', function() {
        const val = customSizeInput.value.trim().toUpperCase();
        if (!val) return;

        // Check if already exists in existing chips (case-insensitive)
        let alreadyExistsInChips = false;
        chipsContainer.querySelectorAll('.size-chip-wrapper').forEach(wrap => {
            const sz = (wrap.getAttribute('data-size') || '').trim();
            if (sz.toUpperCase() === val) {
                alreadyExistsInChips = true;
                const cb = wrap.querySelector('.size-checkbox');
                if (cb) cb.checked = true;
                const chip = wrap.querySelector('.size-chip');
                if (chip) chip.classList.add('active');
            }
        });

        if (alreadyExistsInChips) {
            alert("Attribute '" + val + "' already exists.");
            modalAddSize.hide();
            validateSizes(false);
            return;
        }

        // Call server to validate uniqueness & persist in product_attributes
        const fd = new FormData();
        fd.append('attribute_type', 'size');
        fd.append('attribute_value', val);
        fd.append('attribute_name', 'Size');
        fd.append('action_mode', 'style_builder');

        fetch('<?= \App\Core\Controller::url('/api/attributes/quick-add') ?>', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const chipVal = res.attribute_value || val;
                    const div = document.createElement('div');
                    div.className = 'size-chip-wrapper';
                    div.setAttribute('data-size', chipVal);
                    div.innerHTML = `
                        <label class="size-chip active">
                            <input type="checkbox" name="sizes[]" value="${chipVal}" class="size-checkbox" checked>
                            <span class="size-label">${chipVal}</span>
                            <button type="button" class="btn-remove-size" title="Remove size"><i class="bi bi-trash"></i></button>
                        </label>
                    `;
                    chipsContainer.appendChild(div);
                    bindChipEvents();
                    modalAddSize.hide();
                    validateSizes(false);
                } else {
                    alert(res.message || ("Attribute '" + val + "' already exists."));
                }
            })
            .catch(() => {
                // Fallback client append if offline
                const div = document.createElement('div');
                div.className = 'size-chip-wrapper';
                div.setAttribute('data-size', val);
                div.innerHTML = `
                    <label class="size-chip active">
                        <input type="checkbox" name="sizes[]" value="${val}" class="size-checkbox" checked>
                        <span class="size-label">${val}</span>
                        <button type="button" class="btn-remove-size" title="Remove size"><i class="bi bi-trash"></i></button>
                    </label>
                `;
                chipsContainer.appendChild(div);
                bindChipEvents();
                modalAddSize.hide();
                validateSizes(false);
            });
    });

    // ========================================================
    // QUICK-ADD MODAL TRIGGERS & AJAX SUBMISSIONS
    // ========================================================
    
    // Subcategory trigger check
    document.getElementById('btnOpenNewSubcategory').addEventListener('click', function(e) {
        e.preventDefault();
        const catId = mainCatSelect.value;
        if (!catId) {
            alert('Please select a Main Category first before adding a Subcategory.');
            mainCatSelect.focus();
            return;
        }
        document.getElementById('quickAddSubcatParentId').value = catId;
        document.getElementById('quickAddSubcatParentName').textContent = mainCatSelect.selectedOptions[0].text;
        new bootstrap.Modal(document.getElementById('modalAddSubcategory')).show();
    });

    // Sub-Subcategory trigger check
    document.getElementById('btnOpenNewSubSubcategory').addEventListener('click', function(e) {
        e.preventDefault();
        const subId = subCatSelect.value;
        if (!subId) {
            alert('Please select a Subcategory first before adding a Sub-Subcategory.');
            subCatSelect.focus();
            return;
        }
        document.getElementById('quickAddSubSubcatParentId').value = subId;
        document.getElementById('quickAddSubSubcatParentName').textContent = subCatSelect.selectedOptions[0].text;
        new bootstrap.Modal(document.getElementById('modalAddSubSubcategory')).show();
    });

    // Submit Quick Add Category
    document.getElementById('formQuickAddCategory').addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fetch('<?= \App\Core\Controller::url('/api/categories/quick-add') ?>', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const opt = new Option(res.name, res.id, true, true);
                    opt.setAttribute('data-name', res.name);
                    opt.setAttribute('data-code', res.code);
                    opt.setAttribute('data-hsn', res.default_hsn);
                    opt.setAttribute('data-gst', res.default_gst_rate);
                    mainCatSelect.add(opt);
                    mainCatSelect.dispatchEvent(new Event('change'));
                    bootstrap.Modal.getInstance(document.getElementById('modalAddCategory')).hide();
                    this.reset();
                } else {
                    alert(res.message || 'Failed to add category');
                }
            });
    });

    // Submit Quick Add Subcategory
    document.getElementById('formQuickAddSubcategory').addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fetch('<?= \App\Core\Controller::url('/api/subcategories/quick-add') ?>', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const opt = new Option(res.name, res.id, true, true);
                    opt.setAttribute('data-name', res.name);
                    opt.setAttribute('data-code', res.code);
                    subCatSelect.add(opt);
                    subCatSelect.dispatchEvent(new Event('change'));
                    bootstrap.Modal.getInstance(document.getElementById('modalAddSubcategory')).hide();
                    this.reset();
                } else {
                    alert(res.message || 'Failed to add subcategory');
                }
            });
    });

    // Submit Quick Add Sub-Subcategory
    document.getElementById('formQuickAddSubSubcategory').addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fetch('<?= \App\Core\Controller::url('/api/subsubcategories/quick-add') ?>', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const opt = new Option(res.name, res.id, true, true);
                    opt.setAttribute('data-name', res.name);
                    opt.setAttribute('data-code', res.code);
                    subSubCatSelect.add(opt);
                    subSubCatSelect.dispatchEvent(new Event('change'));
                    bootstrap.Modal.getInstance(document.getElementById('modalAddSubSubcategory')).hide();
                    this.reset();
                } else {
                    alert(res.message || 'Failed to add sub-subcategory');
                }
            });
    });

    // Submit Quick Add HSN
    document.getElementById('formQuickAddHsn').addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fd.set('code_type', 'HSN');
        fetch('<?= \App\Core\Controller::url('/api/hsn/quick-add') ?>', { method: 'POST', body: fd })
            .then(r => {
                if (!r.ok) throw new Error('Unable to save HSN code.');
                return r.json();
            })
            .then(res => {
                if (res.success) {
                    inputHsnCode.value = res.hsn_code;
                    inputGstRate.value = res.gst_rate === null ? '' : parseFloat(res.gst_rate).toFixed(2);
                    hsnRateNote.textContent = res.rate_note || (res.gst_rate === null ? 'This classification needs a manually verified GST rate.' : '');
                    bootstrap.Modal.getInstance(document.getElementById('modalAddHsn')).hide();
                    this.reset();
                } else {
                    throw new Error(res.message || 'Failed to add HSN');
                }
            })
            .catch(error => alert(error.message || 'Failed to add HSN'));
    });

    // ========================================================
    // FINAL FORM VALIDATION BEFORE SUBMIT
    // ========================================================
    document.getElementById('formProductStyle').addEventListener('submit', function(e) {
        let valid = true;

        // 1. Check Main Category
        if (!mainCatSelect.value) {
            mainCatSelect.classList.add('is-invalid');
            valid = false;
        } else {
            mainCatSelect.classList.remove('is-invalid');
        }

        // 2. Check Main Code
        if (!mainCodeInput.value.trim()) {
            mainCodeInput.classList.add('is-invalid');
            valid = false;
        } else {
            mainCodeInput.classList.remove('is-invalid');
        }

        // 3. Check Whole QR Code
        if (!wholeCodeInput.value.trim()) {
            wholeCodeInput.classList.add('is-invalid');
            valid = false;
        } else {
            wholeCodeInput.classList.remove('is-invalid');
        }

        // 4. Check HSN Code
        if (!inputHsnCode.value.trim()) {
            inputHsnCode.classList.add('is-invalid');
            valid = false;
        } else {
            inputHsnCode.classList.remove('is-invalid');
        }

        // 5. Check GST Rate
        const gstVal = parseFloat(inputGstRate.value);
        if (isNaN(gstVal) || gstVal < 0 || gstVal > 100) {
            inputGstRate.classList.add('is-invalid');
            valid = false;
        } else {
            inputGstRate.classList.remove('is-invalid');
        }

        // 6. Check Sizes
        if (!validateSizes(true)) {
            valid = false;
        }

        if (!valid) {
            e.preventDefault();
            e.stopPropagation();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });

    // Initial Trigger on load
    if (mainCatSelect.value) {
        mainCatSelect.dispatchEvent(new Event('change'));
    }
});
</script>
