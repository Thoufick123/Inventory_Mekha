<?php
// app/views/concern/catalog_mapping.php - 3-Tier Category Hierarchy, Product Attributes & HSN Master
?>
<div class="container-fluid px-0">
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/') ?>" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item text-muted">Purchase</li>
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
                <h4 class="fw-bold mb-0 text-dark">3-Tier Hierarchy &amp; Attributes</h4>
                <p class="text-secondary small mb-0">Manage 3-tier category hierarchies, reusable product attributes, and HSN master codes.</p>
            </div>
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

    <?php
        $hierarchyPermissionModule = 'catalog_mapping';
        $hierarchyReturnUrl = '/production/catalog-mapping';
        $hierarchyRoutePrefix = '/production/catalog-mapping';
        require __DIR__ . '/partials/hierarchy_attributes_hsn.php';
    ?>
</div>
