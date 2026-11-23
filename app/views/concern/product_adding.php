<div class="container-fluid px-0">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/dashboard') ?>" class="text-decoration-none text-muted">Dashboard</a></li>
            <li class="breadcrumb-item active fw-semibold text-dark">Product Adding</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1">Add Product</h4>
            <p class="text-muted mb-0 small">Create a catalog style, size variants, opening stock, and printable QR labels.</p>
        </div>
        <a href="<?= \App\Core\Controller::url('/m/stocks') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-box-seam me-1"></i> View Stock
        </a>
    </div>

    <?php
    $createdVariantId = !empty($successVariants) ? (int)$successVariants[0]['id'] : 0;
    $createdLabelQty = (int)($_GET['qty'] ?? 0);
    ?>
    <?php if ($createdVariantId > 0): ?>
        <div class="card border-success-subtle shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h6 class="fw-bold text-success mb-1"><i class="bi bi-check-circle me-1"></i> Product saved</h6>
                    <div class="small text-muted">Opening stock and product variants are ready.</div>
                </div>
                <form action="<?= \App\Core\Controller::url('/m/label_generation/generate') ?>" method="POST" class="d-flex align-items-end gap-2">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                    <input type="hidden" name="label_type" value="variant">
                    <input type="hidden" name="item_id" value="0">
                    <div>
                        <label class="form-label small fw-semibold mb-1">Variant</label>
                        <select name="variant_id" class="form-select" required>
                            <?php foreach ($successVariants as $variant): ?>
                                <option value="<?= (int)$variant['id'] ?>">
                                    <?= htmlspecialchars($variant['sku_code'] . ' - ' . $variant['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small fw-semibold mb-1">Label copies</label>
                        <input type="number" class="form-control" name="qty" min="1" max="500" value="<?= max(1, $createdLabelQty) ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-qr-code me-1"></i> Generate Labels
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <form action="<?= \App\Core\Controller::url('/m/product_adding/store') ?>" method="POST" id="productAddingForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-diagram-3 text-primary me-2"></i>Catalog Details</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Style Code <span class="text-danger">*</span></label>
                        <input type="text" name="style_code" class="form-control text-uppercase" maxlength="50" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" maxlength="150" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Color</label>
                        <input type="text" name="color" class="form-control" maxlength="100" placeholder="For example: Blue">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Main Category <span class="text-danger">*</span></label>
                        <select name="category_id" id="mainCategory" class="form-select" required>
                            <option value="">Select category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int)$category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Sub Category</label>
                        <select name="subcategory_id" id="subCategory" class="form-select">
                            <option value="">Select sub category</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Sub Sub Category</label>
                        <select name="sub_sub_category_id" id="subSubCategory" class="form-select">
                            <option value="">Select sub sub category</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">HSN Code <span class="text-danger">*</span></label>
                        <input type="text" name="hsn_code" class="form-control" list="hsnList" pattern="[0-9]{4,8}" required>
                        <datalist id="hsnList">
                            <?php foreach ($hsnCodes as $hsn): ?>
                                <option value="<?= htmlspecialchars($hsn['hsn_code']) ?>"><?= htmlspecialchars($hsn['description']) ?></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">GST % <span class="text-danger">*</span></label>
                        <input type="number" name="gst_rate" class="form-control" min="0" max="100" step="0.01" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Cost Price</label>
                        <input type="number" name="cost_price" class="form-control" min="0" step="0.01" value="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">MRP</label>
                        <input type="number" name="mrp" class="form-control" min="0" step="0.01" value="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Selling Price</label>
                        <input type="number" name="price" class="form-control" min="0" step="0.01" value="0">
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-rulers text-primary me-2"></i>Size Variants and Opening Stock</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Stock Location</label>
                        <input type="text" name="location" class="form-control" value="Main Store">
                    </div>
                </div>
                <div class="table-responsive mt-3">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th style="width:70px;">Create</th>
                                <th>Size</th>
                                <th style="width:180px;">Opening Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($sizes)): ?>
                                <?php foreach ($sizes as $index => $size): ?>
                                    <?php $value = htmlspecialchars((string)$size['attribute_value']); ?>
                                    <tr>
                                        <td><input type="checkbox" name="sizes[]" value="<?= $value ?>" class="form-check-input size-check"></td>
                                        <td><?= $value ?></td>
                                        <td><input type="number" name="quantities[<?= $size['attribute_value'] ?>]" class="form-control qty-input" min="0" step="0.01" value="0"></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach (['XS','S','M','L','XL','XXL','XXXL'] as $index => $size): ?>
                                    <tr>
                                        <td><input type="checkbox" name="sizes[]" value="<?= $size ?>" class="form-check-input size-check"></td>
                                        <td><?= $size ?></td>
                                        <td><input type="number" name="quantities[<?= $size ?>]" class="form-control qty-input" min="0" step="0.01" value="0"></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <button type="reset" class="btn btn-outline-secondary">Reset</button>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check2-circle me-1"></i> Save Product and Stock
            </button>
        </div>
    </form>
</div>

<script>
(function () {
    const category = document.getElementById('mainCategory');
    const subcategory = document.getElementById('subCategory');
    const subSubcategory = document.getElementById('subSubCategory');

    async function loadSubcategories() {
        subcategory.innerHTML = '<option value="">Select sub category</option>';
        subSubcategory.innerHTML = '<option value="">Select sub sub category</option>';
        if (!category.value) return;
        const response = await fetch('<?= \App\Core\Controller::url('/api/product-adding/subcategories') ?>?category_id=' + encodeURIComponent(category.value));
        const data = await response.json();
        (data.items || []).forEach(item => {
            subcategory.insertAdjacentHTML('beforeend', '<option value="' + item.id + '">' + item.name + '</option>');
        });
    }

    async function loadSubSubcategories() {
        subSubcategory.innerHTML = '<option value="">Select sub sub category</option>';
        if (!subcategory.value) return;
        const response = await fetch('<?= \App\Core\Controller::url('/api/product-adding/sub-subcategories') ?>?subcategory_id=' + encodeURIComponent(subcategory.value));
        const data = await response.json();
        (data.items || []).forEach(item => {
            subSubcategory.insertAdjacentHTML('beforeend', '<option value="' + item.id + '">' + item.name + '</option>');
        });
    }

    category.addEventListener('change', loadSubcategories);
    subcategory.addEventListener('change', loadSubSubcategories);

    document.querySelectorAll('.size-check').forEach((checkbox) => {
        checkbox.addEventListener('change', function () {
            const qty = this.closest('tr').querySelector('.qty-input');
            if (!this.checked) qty.value = 0;
        });
    });
})();
</script>
