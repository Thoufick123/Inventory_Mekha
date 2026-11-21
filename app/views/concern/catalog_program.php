<?php
// app/views/concern/catalog_program.php - Production Catalog & Programs Register
$isProgramsTab = (isset($activeTab) && $activeTab === 'programs') || (isset($_GET['tab']) && $_GET['tab'] === 'programs');
?>
<div class="container-fluid px-0">
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/') ?>" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item text-muted">Production</li>
            <li class="breadcrumb-item active fw-semibold text-dark" aria-current="page">Catalog</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <div class="p-2.5 rounded-3 bg-primary-subtle text-primary border border-primary-subtle shadow-xs">
                <i class="bi bi-journal-bookmark fs-4"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-0 text-dark">Production Catalog</h4>
                <p class="text-secondary small mb-0">Master catalog of manufactured garment styles, category hierarchies, and production programs.</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <ul class="nav nav-pills bg-light p-1 rounded-3 border me-2" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-1.5 px-3 rounded-2 fw-semibold small <?= !$isProgramsTab ? 'active shadow-sm' : 'text-secondary' ?>" id="tab-catalog-btn" data-bs-toggle="pill" data-bs-target="#tab-catalog" type="button" role="tab">
                        <i class="bi bi-tags me-1.5"></i> Garment Styles
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-1.5 px-3 rounded-2 fw-semibold small <?= $isProgramsTab ? 'active shadow-sm' : 'text-secondary' ?>" id="tab-programs-btn" data-bs-toggle="pill" data-bs-target="#tab-programs" type="button" role="tab">
                        <i class="bi bi-calendar-check me-1.5"></i> Production Programs (<?= count($programs) ?>)
                    </button>
                </li>
            </ul>

            <?php if (\App\Core\Auth::can('catalog_program', 'add')): ?>
            <a href="<?= \App\Core\Controller::url('/production/catalog/create') ?>" class="btn btn-primary d-inline-flex align-items-center gap-2 shadow-sm rounded-3 py-2 px-3 fw-semibold">
                <i class="bi bi-plus-circle"></i> Add Product Style
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Export Intelligence Toolbar -->
    <?php
    $exportTitle = 'Export Production Programs (Excel & PDF)';
    $exportSubtitle = 'Download seasonal styles, production schedules, planned quantities & fulfillment status';
    $exportUrl = \App\Core\Controller::url('/m/catalog_program/export');
    $prefix = 'prog_exp';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <!-- Tab Contents -->
    <div class="tab-content">
        <!-- TAB 1: GARMENT STYLES CATALOG (MATCHING IMAGE 1) -->
        <div class="tab-pane fade <?= !$isProgramsTab ? 'show active' : '' ?>" id="tab-catalog" role="tabpanel">
            
            <!-- Filters Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-3">
                    <form action="<?= \App\Core\Controller::url('/production/catalog') ?>" method="GET" class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold text-secondary mb-1">Search Product Styles</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by style code, product name, or category..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-secondary mb-1">Category</label>
                            <select name="category_id" class="form-select" id="filterCategory">
                                <option value="">-- All Categories --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= (isset($filters['category_id']) && $filters['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold text-secondary mb-1">Subcategory</label>
                            <select name="subcategory_id" class="form-select" id="filterSubcategory">
                                <option value="">-- All Subcategories --</option>
                                <?php foreach ($subcategories as $sub): ?>
                                    <option value="<?= $sub['id'] ?>" <?= (isset($filters['subcategory_id']) && $filters['subcategory_id'] == $sub['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sub['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100 d-inline-flex align-items-center justify-content-center gap-1.5 fw-semibold shadow-xs">
                                <i class="bi bi-funnel-fill"></i> Filter
                            </button>
                            <a href="<?= \App\Core\Controller::url('/production/catalog') ?>" class="btn btn-outline-secondary" title="Reset Filters">
                                <i class="bi bi-arrow-clockwise"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Styles Table Card -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-list-task text-primary"></i> Manufactured Garment Styles
                    </h6>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1.5 fw-semibold font-monospace">
                        <?= count($styles) ?> Product Styles
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-custom">
                        <thead>
                            <tr>
                                <th style="width: 45px;">#</th>
                                <th style="min-width: 150px;">STYLE CODE</th>
                                <th style="min-width: 200px;">PRODUCT STYLE NAME</th>
                                <th style="min-width: 220px;">CATEGORY HIERARCHY</th>
                                <th style="min-width: 160px;">HSN & GST</th>
                                <th style="min-width: 110px;">VARIANTS</th>
                                <th style="min-width: 100px;">STATUS</th>
                                <th style="min-width: 110px; text-align: center;">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($styles)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <div class="text-muted mb-3"><i class="bi bi-folder-x fs-1 text-secondary opacity-50"></i></div>
                                        <h6 class="fw-bold text-dark">No Product Styles Found</h6>
                                        <p class="text-secondary small mb-3">Get started by creating your first manufactured garment style.</p>
                                        <?php if (\App\Core\Auth::can('catalog_program', 'add')): ?>
                                        <a href="<?= \App\Core\Controller::url('/production/catalog/create') ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-plus-circle me-1"></i> Add Product Style
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $idx = 1; foreach ($styles as $s): ?>
                                    <tr>
                                        <td class="text-muted small fw-semibold"><?= $idx++ ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                                <span class="badge bg-dark font-monospace text-white px-2 py-1 shadow-xs">
                                                    <?= htmlspecialchars($s['style_code'] ?: $s['code']) ?>
                                                </span>
                                                <?php if (!empty($s['short_code'])): ?>
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-1.5 py-0.5">
                                                        <?= htmlspecialchars($s['short_code']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark text-uppercase"><?= htmlspecialchars($s['name']) ?></div>
                                            <?php if (!empty($s['description'])): ?>
                                                <small class="text-muted text-truncate d-block" style="max-width: 240px;"><?= htmlspecialchars($s['description']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $path = $s['hierarchy_path'];
                                            if (empty($path)) {
                                                $parts = array_filter([$s['category_name'], $s['subcategory_name'], $s['subsub_name']]);
                                                $path = implode(' > ', $parts);
                                            }
                                            $tags = explode('>', $path);
                                            ?>
                                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                                <?php foreach ($tags as $tIndex => $t): $t = trim($t); if (empty($t)) continue; ?>
                                                    <?php if ($tIndex > 0): ?>
                                                        <i class="bi bi-chevron-right text-muted opacity-50" style="font-size: 0.65rem;"></i>
                                                    <?php endif; ?>
                                                    <span class="badge bg-light text-secondary border px-2 py-0.5 small fw-normal">
                                                        <?= htmlspecialchars($t) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle font-monospace">
                                                    HSN <?= htmlspecialchars($s['hsn_code'] ?: 'Not set') ?>
                                                </span>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace">
                                                    <?= $s['gst_rate'] === null ? 'Verify rate' : number_format((float)$s['gst_rate'], 2) . '% GST' ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border px-2 py-1 shadow-xs">
                                                <i class="bi bi-layers text-secondary me-1"></i><?= (int)($s['active_variants_count'] ?? 0) ?> active
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                <?= ($s['status'] === 'in_house' || empty($s['status'])) ? 'In-House' : ucfirst($s['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-inline-flex align-items-center gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-info rounded-3 p-1.5" title="Quick View" onclick="showStyleDetails(<?= $s['id'] ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <?php if (\App\Core\Auth::can('catalog_program', 'edit')): ?>
                                                <a href="<?= \App\Core\Controller::url('/production/catalog/edit?id=' . $s['id']) ?>" class="btn btn-sm btn-outline-primary rounded-3 p-1.5" title="Edit Style">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php endif; ?>
                                                <?php if (\App\Core\Auth::can('catalog_program', 'delete')): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger rounded-3 p-1.5" title="Delete Style" onclick="confirmDeleteStyle(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>', '<?= htmlspecialchars($s['style_code']) ?>')">
                                                    <i class="bi bi-trash"></i>
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

                <!-- Footer Pagination Matching Image 1 -->
                <div class="card-footer bg-white py-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="text-muted small">
                        Showing 1 to <?= count($styles) ?> of <?= count($styles) ?> product styles
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center gap-2 small text-muted">
                            <span>Show:</span>
                            <select class="form-select form-select-sm" style="width: auto;">
                                <option value="15" selected>15 per page</option>
                                <option value="25">25 per page</option>
                                <option value="50">50 per page</option>
                            </select>
                        </div>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item disabled"><a class="page-link" href="#"><i class="bi bi-chevron-left"></i> Prev</a></li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item disabled"><a class="page-link" href="#">Next <i class="bi bi-chevron-right"></i></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: PRODUCTION PROGRAMS REGISTER -->
        <div class="tab-pane fade <?= $isProgramsTab ? 'show active' : '' ?>" id="tab-programs" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-calendar-range text-primary me-2"></i> Scheduled Production Orders</h6>
                    <?php if (\App\Core\Auth::can('catalog_program', 'add')): ?>
                    <a href="<?= \App\Core\Controller::url('/production/programs/create') ?>" class="btn btn-sm btn-primary shadow-sm">
                        <i class="bi bi-calendar-plus me-1"></i> New Production Program
                    </a>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-custom">
                        <thead>
                            <tr>
                                <th>Program Code & Name</th>
                                <th>Planner</th>
                                <th>Rolls Assigned</th>
                                <th>Catalog Style</th>
                                <th>Planned Qty</th>
                                <th>Start Date</th>
                                <th>Target Date</th>
                                <th>Status</th>
                                <th class="text-center" style="width: 130px;">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($programs)): ?>
                                <tr><td colspan="9" class="text-center py-4 text-muted">No scheduled production programs.</td></tr>
                            <?php else: ?>
                                <?php foreach ($programs as $p): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="badge bg-dark font-monospace mb-1 align-self-start"><?= htmlspecialchars($p['program_no']) ?></span>
                                                <span class="fw-semibold text-dark small"><?= htmlspecialchars($p['program_name'] ?? 'Production Run') ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small text-muted"><i class="bi bi-person me-1"></i><?= htmlspecialchars($p['planner_name'] ?? 'Planner') ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                                <i class="bi bi-diagram-3 me-1"></i><?= (int)($p['rolls_count'] ?? 0) ?> Roll(s)
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($p['catalog_name'] ?? 'General / Per-Roll') ?></span>
                                            <?php if (!empty($p['roll_catalog_names']) && $p['roll_catalog_names'] !== ($p['catalog_name'] ?? '')): ?>
                                                <div class="small text-muted mt-0.5">Roll styles: <?= htmlspecialchars($p['roll_catalog_names']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($p['target_variant_name'])): ?>
                                                <div class="small text-muted mt-0.5"><?= htmlspecialchars($p['target_variant_name']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="fw-bold text-primary"><?= number_format((float)($p['planned_qty'] ?? 0), 2) ?></span> <span class="small text-muted">Units</span></td>
                                        <td><?= date('d M Y', strtotime($p['start_date'])) ?></td>
                                        <td><?= date('d M Y', strtotime($p['target_date'])) ?></td>
                                        <td>
                                            <span class="badge <?= $p['status'] === 'completed' ? 'bg-success' : ($p['status'] === 'cancelled' ? 'bg-secondary' : 'bg-primary') ?>">
                                                <?= ucfirst(str_replace('_', ' ', $p['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-inline-flex align-items-center gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-info rounded-3 p-1.5" title="Quick View Program" onclick="showProgramDetails(<?= $p['id'] ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <?php if (\App\Core\Auth::can('catalog_program', 'edit')): ?>
                                                <a href="<?= \App\Core\Controller::url('/production/programs/edit?id=' . $p['id']) ?>" class="btn btn-sm btn-outline-primary rounded-3 p-1.5" title="Edit Program">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php endif; ?>
                                                <?php if (\App\Core\Auth::can('catalog_program', 'delete')): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger rounded-3 p-1.5" title="Delete Program" onclick="confirmDeleteProgram(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['program_no'])) ?>', '<?= htmlspecialchars(addslashes($p['program_name'] ?? '')) ?>')">
                                                    <i class="bi bi-trash"></i>
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
    </div>
</div>

<!-- QUICK VIEW STYLE MODAL -->
<div class="modal fade" id="modalViewStyle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h6 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-tag-fill text-primary"></i> Product Style Details
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="viewStyleContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- CONFIRM DELETE STYLE MODAL -->
<div class="modal fade" id="modalDeleteStyle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= \App\Core\Controller::url('/production/catalog/delete') ?>" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
            <input type="hidden" name="id" id="deleteStyleId" value="">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> Delete Product Style</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="bi bi-trash3 text-danger fs-1 mb-3 d-block"></i>
                <h5 class="fw-bold text-dark mb-2" id="deleteStyleName">Are you sure?</h5>
                <p class="text-secondary small mb-0">
                    This will delete style <strong id="deleteStyleCode" class="text-dark"></strong> and its size variants. If stock movements exist, the style will be archived as Inactive to protect ledger consistency.
                </p>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm px-3"><i class="bi bi-trash me-1"></i> Delete Style</button>
            </div>
        </form>
    </div>
</div>

<!-- QUICK VIEW PROGRAM MODAL -->
<div class="modal fade" id="modalViewProgram" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h6 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-calendar-range-fill text-primary"></i> Production Program Details
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="viewProgramContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 justify-content-between">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <div id="viewProgramActions"></div>
            </div>
        </div>
    </div>
</div>

<!-- CONFIRM DELETE PROGRAM MODAL -->
<div class="modal fade" id="modalDeleteProgram" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= \App\Core\Controller::url('/production/programs/delete') ?>" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
            <input type="hidden" name="id" id="deleteProgramId" value="">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> Delete Production Program</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="bi bi-trash3 text-danger fs-1 mb-3 d-block"></i>
                <h5 class="fw-bold text-dark mb-2" id="deleteProgramTitle">Are you sure?</h5>
                <div class="badge bg-dark font-monospace fs-6 mb-2" id="deleteProgramCode"></div>
                <p class="text-secondary small mb-0">
                    This will delete this production program and <strong>release its allocated raw material fabric rolls back into available stock</strong>. If active cutting logs exist, the program will be archived as Cancelled to protect ledger consistency.
                </p>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm px-3"><i class="bi bi-trash me-1"></i> Confirm Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- NEW PRODUCTION PROGRAM MODAL -->
<div class="modal fade" id="modalAddProgram" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/m/catalog_program/store') ?>" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Schedule Production Program</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Target Finished Variant *</label>
                    <select name="target_variant_id" class="form-select" required>
                        <option value="">Select Variant to Produce</option>
                        <?php foreach ($variants as $v): ?>
                            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['sku_code']) ?> &bull; <?= htmlspecialchars($v['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Catalog Style</label>
                    <select name="catalog_id" class="form-select">
                        <option value="">-- General / No Specific Style --</option>
                        <?php foreach ($catalogs as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['style_code'] ?? '') ?> - <?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Planned Quantity (Units) *</label>
                    <input type="number" step="0.01" name="planned_qty" class="form-control" required placeholder="500.00">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Target Completion Date</label>
                        <input type="date" name="target_date" class="form-control" value="<?= date('Y-m-d', strtotime('+14 days')) ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-3">Schedule Program</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Program -->
<div class="modal fade" id="modalEditProgram" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/m/catalog_program/update') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" id="edit_program_id" value="">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-1 text-primary"></i> Edit Production Program</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Program No</label>
                    <input type="text" id="edit_program_no" class="form-control font-monospace" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Planned Production Qty *</label>
                    <input type="number" step="0.01" name="planned_qty" id="edit_program_qty" class="form-control" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Start Date *</label>
                        <input type="date" name="start_date" id="edit_program_start" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Target Completion *</label>
                        <input type="date" name="target_date" id="edit_program_target" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Status *</label>
                    <select name="status" id="edit_program_status" class="form-select" required>
                        <option value="planned">Planned</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Program</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditProgramModal(p) {
    if (!p) return;
    document.getElementById('edit_program_id').value = p.id || '';
    document.getElementById('edit_program_no').value = p.program_no || '';
    document.getElementById('edit_program_qty').value = parseFloat(p.planned_qty || 0).toFixed(2);
    document.getElementById('edit_program_start').value = p.start_date ? p.start_date.substring(0, 10) : '';
    document.getElementById('edit_program_target').value = p.target_date ? p.target_date.substring(0, 10) : '';
    document.getElementById('edit_program_status').value = p.status || 'draft';

    const modal = new bootstrap.Modal(document.getElementById('modalEditProgram'));
    modal.show();
}

function showStyleDetails(id) {
    const modal = new bootstrap.Modal(document.getElementById('modalViewStyle'));
    const container = document.getElementById('viewStyleContent');
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    modal.show();

    fetch('<?= \App\Core\Controller::url('/api/catalog/style-details?id=') ?>' + id)
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                container.innerHTML = `<div class="alert alert-danger">${res.message || 'Error loading details'}</div>`;
                return;
            }
            const s = res.style;
            const vars = res.variants || [];
            let varListHtml = vars.map(v => `
                <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                    <div>
                        <strong class="font-monospace text-dark">${v.sku_code}</strong>
                        <div class="small text-muted">${v.name}</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-light text-dark border">Size: ${v.size || '-'}</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">${v.status}</span>
                    </div>
                </li>
            `).join('');

            container.innerHTML = `
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="small text-muted">Style Code</div>
                        <div class="fw-bold font-monospace fs-5 text-dark">${s.style_code || s.code} <span class="badge bg-primary-subtle text-primary border">${s.short_code || ''}</span></div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Status</div>
                        <span class="badge bg-success-subtle text-success border border-success-subtle fs-6">${s.status === 'in_house' ? 'In-House' : s.status}</span>
                    </div>
                    <div class="col-12">
                        <div class="small text-muted">Product Style Name</div>
                        <div class="fw-bold text-dark fs-6">${s.name}</div>
                    </div>
                    <div class="col-12">
                        <div class="small text-muted">Category Hierarchy</div>
                        <div class="p-2 rounded bg-light border small text-dark">${s.hierarchy_path || '-'}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">HSN Code</div>
                        <div class="fw-semibold font-monospace">${s.hsn_code || 'Not set'}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">GST Tax Rate</div>
                        <div class="fw-semibold text-primary">${s.gst_rate === null ? 'Verify rate' : parseFloat(s.gst_rate).toFixed(2) + '% GST'}</div>
                    </div>
                    <div class="col-12">
                        <div class="small text-muted mb-2 fw-semibold">Active Size Variants (${vars.length}):</div>
                        <ul class="list-group rounded-3 small">
                            ${varListHtml || '<li class="list-group-item text-muted">No variants registered</li>'}
                        </ul>
                    </div>
                </div>
            `;
        })
        .catch(err => {
            container.innerHTML = `<div class="alert alert-danger">Network error loading details.</div>`;
        });
}

function confirmDeleteStyle(id, name, code) {
    document.getElementById('deleteStyleId').value = id;
    document.getElementById('deleteStyleName').textContent = name;
    document.getElementById('deleteStyleCode').textContent = code;
    new bootstrap.Modal(document.getElementById('modalDeleteStyle')).show();
}

function showProgramDetails(id) {
    const modal = new bootstrap.Modal(document.getElementById('modalViewProgram'));
    const container = document.getElementById('viewProgramContent');
    const actionsContainer = document.getElementById('viewProgramActions');
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    actionsContainer.innerHTML = '';
    modal.show();

    fetch('<?= \App\Core\Controller::url('/api/programs/details?id=') ?>' + id)
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                container.innerHTML = `<div class="alert alert-danger">${res.message || 'Error loading program details'}</div>`;
                return;
            }
            const p = res.program;
            const rolls = res.rolls || [];
            const cutting = res.cutting || {};

            let statusBadge = '';
            if (p.status === 'completed') {
                statusBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle fs-6">Completed</span>';
            } else if (p.status === 'in_progress') {
                statusBadge = '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fs-6">In Progress</span>';
            } else if (p.status === 'cancelled') {
                statusBadge = '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-6">Cancelled</span>';
            } else {
                statusBadge = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-6">Planned</span>';
            }

            let rollRows = rolls.map((r, idx) => {
                const hier = [r.category_name, r.subcategory_name, r.subsub_name].filter(Boolean).join(' > ') || '-';
                const styleLabel = r.catalog_style_name ? `<span class="badge bg-light text-dark border">${r.catalog_style_code ? '[' + r.catalog_style_code + '] ' : ''}${r.catalog_style_name}</span>` : '<span class="text-muted small">General</span>';
                return `
                    <tr>
                        <td class="text-center font-monospace">${idx + 1}</td>
                        <td>
                            <div class="fw-bold font-monospace text-dark">${r.roll_piece_tag || '-'}</div>
                            <div class="small text-muted">${r.fabric_name || 'Fabric Roll'}</div>
                            ${r.color ? `<span class="badge bg-light text-secondary border mt-1" style="font-size: 0.72rem;">${r.color}</span>` : ''}
                        </td>
                        <td>
                            <span class="fw-bold text-primary">${parseFloat(r.allocated_qty || 0).toFixed(2)} ${r.unit || 'kg'}</span>
                            <div class="small text-muted" style="font-size: 0.75rem;">out of ${parseFloat(r.total_roll_qty || 0).toFixed(2)} ${r.unit || 'kg'} total</div>
                        </td>
                        <td>
                            <span class="fw-bold text-success">${parseInt(r.target_pieces || 0)}</span> <span class="small text-muted">pcs</span>
                        </td>
                        <td>
                            <div class="small text-secondary mb-1">${hier}</div>
                            ${styleLabel}
                        </td>
                    </tr>
                `;
            }).join('');

            container.innerHTML = `
                <div class="row g-3 mb-4">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-dark font-monospace fs-6">${p.program_no}</span>
                            ${statusBadge}
                        </div>
                        <h5 class="fw-bold text-dark mb-1">${p.program_name || 'Production Program'}</h5>
                        <div class="small text-muted"><i class="bi bi-person me-1"></i>Planner: <strong>${p.planner_name || 'Planner'}</strong></div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="small text-muted mb-1">Manufacturing Schedule</div>
                        <div class="fw-semibold text-dark"><i class="bi bi-calendar-event me-1 text-primary"></i>${p.start_date || '-'} &rarr; ${p.target_date || '-'}</div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="p-3 rounded-3 bg-light border text-center">
                            <div class="text-secondary small fw-semibold">Allocated Fabric Weight</div>
                            <div class="fs-4 fw-bold text-primary font-monospace">${parseFloat(p.planned_qty || 0).toFixed(2)} Units</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded-3 bg-light border text-center">
                            <div class="text-secondary small fw-semibold">Assigned Fabric Rolls</div>
                            <div class="fs-4 fw-bold text-dark font-monospace">${rolls.length} Roll(s)</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded-3 bg-light border text-center">
                            <div class="text-secondary small fw-semibold">Cutting History</div>
                            <div class="fs-4 fw-bold text-success font-monospace">${cutting.total_lots || 0} Lot(s)</div>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold text-dark mb-2 d-flex align-items-center gap-1.5">
                    <i class="bi bi-layers-fill text-primary"></i> Assigned Fabric Rolls & 3-Tier Categorization
                </h6>
                <div class="table-responsive border rounded-3 mb-3">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 40px;">#</th>
                                <th>Roll Tag & Material</th>
                                <th>Allocated (Out of Total)</th>
                                <th>Target Pieces</th>
                                <th>3-Tier Category & Style</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rollRows || '<tr><td colspan="5" class="text-center py-3 text-muted">No rolls assigned.</td></tr>'}
                        </tbody>
                    </table>
                </div>
            `;

            actionsContainer.innerHTML = `
                <?php if (\App\Core\Auth::can('catalog_program', 'edit')): ?>
                <a href="<?= \App\Core\Controller::url('/production/programs/edit?id=') ?>${p.id}" class="btn btn-primary btn-sm px-3 shadow-sm">
                    <i class="bi bi-pencil me-1"></i> Edit Program
                </a>
                <?php endif; ?>
            `;
        })
        .catch(err => {
            container.innerHTML = `<div class="alert alert-danger">Network error loading program details.</div>`;
        });
}

function confirmDeleteProgram(id, no, name) {
    document.getElementById('deleteProgramId').value = id;
    document.getElementById('deleteProgramCode').textContent = no;
    document.getElementById('deleteProgramTitle').textContent = name ? `Delete "${name}"?` : 'Delete Program?';
    new bootstrap.Modal(document.getElementById('modalDeleteProgram')).show();
}
</script>
