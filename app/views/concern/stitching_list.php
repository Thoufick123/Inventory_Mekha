<?php
/**
 * View: Garment Stitching & Wastage Tracking + Wastage Selling Register
 * Location: inventory_mekha/app/views/concern/stitching_list.php
 * Matches Image 2 (Stitching Wastage Selling Modal & Registry)
 */
?>
<div class="container-fluid px-4 py-3">

    <!-- BREADCRUMBS -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/dashboard') ?>" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/catalog') ?>" class="text-decoration-none text-muted">Production</a></li>
            <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Stitching & Wastage</li>
        </ol>
    </nav>

    <!-- HEADER & ACTIONS BAR -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-stack text-primary"></i> Garment Stitching & Wastage Tracking
            </h4>
            <p class="text-secondary small mb-0">
                Manage stitching production batches, tailor assignments, dynamic weights & pricing, and scrap liquidation.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if (\App\Core\Auth::can('stitching_wastage', 'add')): ?>
            <button class="btn btn-outline-danger shadow-sm d-inline-flex align-items-center gap-1.5 fw-semibold" 
                    data-bs-toggle="modal" data-bs-target="#modalSellStitchingWastage">
                <i class="bi bi-recycle"></i> Record Scrap Sale
            </button>
            <a href="<?= \App\Core\Controller::url('/production/stitching/create') ?>" 
               class="btn btn-primary shadow-sm d-inline-flex align-items-center gap-1.5 fw-semibold">
                <i class="bi bi-plus-circle"></i> New Stitching Batch
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- SUMMARY METRICS CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 bg-white">
                <div class="text-muted small fw-semibold text-uppercase">Total Batches</div>
                <div class="fs-4 fw-bold text-dark mt-1"><?= number_format($totalBatches) ?></div>
                <div class="small text-muted mt-1"><i class="bi bi-layers text-primary"></i> Production Lots</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 bg-white">
                <div class="text-muted small fw-semibold text-uppercase">Cut Pieces Received</div>
                <div class="fs-4 fw-bold text-primary mt-1"><?= number_format($totalCutPcs) ?></div>
                <div class="small text-muted mt-1">From cutting batches</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 bg-white">
                <div class="text-muted small fw-semibold text-uppercase">Stitched Units</div>
                <div class="fs-4 fw-bold text-success mt-1"><?= number_format($totalStitchedPcs) ?></div>
                <div class="small text-muted mt-1">Finished garments</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 bg-white">
                <div class="text-muted small fw-semibold text-uppercase">Stitch Fabric Weight</div>
                <div class="fs-4 fw-bold text-info-emphasis mt-1"><?= number_format($totalStitchWeight, 2) ?> <span class="fs-6 fw-normal text-muted">kg</span></div>
                <div class="small text-muted mt-1">Finished stock wt</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 bg-white">
                <div class="text-muted small fw-semibold text-uppercase">Scrap Sold</div>
                <div class="fs-4 fw-bold text-danger mt-1"><?= number_format($totalScrapSold, 2) ?></div>
                <div class="small text-muted mt-1">Thread & strip waste</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 bg-white">
                <div class="text-muted small fw-semibold text-uppercase">Scrap Revenue</div>
                <div class="fs-4 fw-bold text-dark mt-1">₹<?= number_format($totalScrapRevenue, 2) ?></div>
                <div class="small text-success mt-1"><i class="bi bi-cash-stack"></i> Liquidated</div>
            </div>
        </div>
    </div>

    <!-- TABS NAVIGATION -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-bottom px-4 pt-3 pb-0">
            <ul class="nav nav-tabs card-header-tabs" id="stitchingTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= ($tab !== 'wastage') ? 'active fw-bold text-primary' : 'text-secondary' ?> py-3 px-3 d-flex align-items-center gap-2" 
                            id="batches-tab" data-bs-toggle="tab" data-bs-target="#tab-batches" type="button" role="tab">
                        <i class="bi bi-stack"></i> Stitching Batches
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill"><?= count($batches) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= ($tab === 'wastage') ? 'active fw-bold text-danger' : 'text-secondary' ?> py-3 px-3 d-flex align-items-center gap-2" 
                            id="wastage-tab" data-bs-toggle="tab" data-bs-target="#tab-wastage" type="button" role="tab">
                        <i class="bi bi-recycle"></i> Wastage Selling Register
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill"><?= count($wastageSales) ?></span>
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body p-4">
            <div class="tab-content" id="stitchingTabsContent">

                <!-- TAB 1: STITCHING BATCHES -->
                <div class="tab-pane fade <?= ($tab !== 'wastage') ? 'show active' : '' ?>" id="tab-batches" role="tabpanel">
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="input-group" style="max-width: 320px;">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="filterBatchesInput" class="form-control border-start-0" placeholder="Search batch, name, tailor...">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="batchesTable">
                            <thead class="table-light text-uppercase small text-secondary">
                                <tr>
                                    <th class="ps-3 py-3">Batch Number</th>
                                    <th class="py-3">Stitching Name</th>
                                    <th class="py-3">Source Cutting Batch</th>
                                    <th class="py-3">Tailor / Master</th>
                                    <th class="text-center py-3">Cut Pcs</th>
                                    <th class="text-center py-3">Stitched Pcs</th>
                                    <th class="text-center py-3">Cut Wt</th>
                                    <th class="text-center py-3">Stitch Wt</th>
                                    <th class="text-center py-3">Status</th>
                                    <th class="text-center py-3">Created</th>
                                    <th class="text-end pe-3 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($batches)): ?>
                                    <tr>
                                        <td colspan="11" class="text-center py-5 text-muted">
                                            <i class="bi bi-stack fs-1 d-block mb-2 text-secondary"></i>
                                            No stitching batches found. Click <strong>"New Stitching Batch"</strong> to start production.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($batches as $b): ?>
                                        <tr>
                                            <td class="ps-3 py-3 fw-bold font-monospace text-dark">
                                                <a href="<?= \App\Core\Controller::url(\App\Core\Auth::can('stitching_wastage', 'edit') ? ('/production/stitching/edit?id=' . $b['id']) : ('/production/stitching/view?id=' . $b['id'])) ?>" class="text-decoration-none text-primary">
                                                    <?= htmlspecialchars($b['display_batch_no']) ?>
                                                </a>
                                            </td>
                                            <td class="py-3 fw-semibold text-dark">
                                                <?= htmlspecialchars($b['stitching_name'] ?: 'Stitching Batch') ?>
                                                <?php if ($b['variant_count'] > 0): ?>
                                                    <span class="badge bg-light text-muted border ms-1"><?= (int)$b['variant_count'] ?> variants</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3">
                                                <div class="fw-semibold text-dark small">
                                                    <?= htmlspecialchars($b['cutting_name'] ?: ($b['cutting_batch_no'] ?: 'Direct Entry')) ?>
                                                </div>
                                                <?php if ($b['cutting_batch_no']): ?>
                                                    <span class="badge bg-light text-secondary font-monospace border" style="font-size: 0.7rem;">
                                                        <?= htmlspecialchars($b['cutting_batch_no']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 small text-secondary">
                                                <?= htmlspecialchars($b['tailor_display_name'] ?: 'Not Assigned') ?>
                                            </td>
                                            <td class="text-center py-3">
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 font-monospace">
                                                    <?= (int)$b['cut_pieces'] ?>
                                                </span>
                                            </td>
                                            <td class="text-center py-3">
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 font-monospace">
                                                    <?= (int)$b['stitched_pieces'] ?>
                                                </span>
                                            </td>
                                            <td class="text-center py-3 font-monospace small">
                                                <?= number_format((float)$b['cut_weight'], 2) ?> kg
                                            </td>
                                            <td class="text-center py-3 font-monospace small fw-bold text-dark">
                                                <?= number_format((float)$b['stitch_weight'], 2) ?> kg
                                            </td>
                                            <td class="text-center py-3">
                                                <?php if ($b['status'] === 'completed'): ?>
                                                    <span class="badge bg-success text-white px-2 py-1">Completed</span>
                                                <?php elseif ($b['status'] === 'planned'): ?>
                                                    <span class="badge bg-secondary text-white px-2 py-1">Planned</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary text-white px-2 py-1">In Progress</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center py-3 small text-muted">
                                                <?= date('d M Y', strtotime($b['created_at'])) ?>
                                            </td>
                                            <td class="text-end pe-3 py-3">
                                                <div class="btn-group btn-group-sm">
                                                    <?php if (\App\Core\Auth::can('stitching_wastage', 'edit')): ?>
                                                    <a href="<?= \App\Core\Controller::url('/production/stitching/edit?id=' . $b['id']) ?>" 
                                                       class="btn btn-outline-secondary" title="Edit Batch">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    <?php if (\App\Core\Auth::can('stitching_wastage', 'delete')): ?>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            title="Delete Batch"
                                                            onclick="confirmDeleteBatch(<?= $b['id'] ?>, '<?= htmlspecialchars($b['display_batch_no']) ?>')">
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

                <!-- TAB 2: WASTAGE SELLING REGISTER -->
                <div class="tab-pane fade <?= ($tab === 'wastage') ? 'show active' : '' ?>" id="tab-wastage" role="tabpanel">
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="input-group" style="max-width: 320px;">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="filterSalesInput" class="form-control border-start-0" placeholder="Search sale #, buyer, phone...">
                        </div>
                        <?php if (\App\Core\Auth::can('stitching_wastage', 'add')): ?>
                        <button class="btn btn-danger btn-sm shadow-sm d-inline-flex align-items-center gap-1.5 fw-semibold" 
                                data-bs-toggle="modal" data-bs-target="#modalSellStitchingWastage">
                            <i class="bi bi-recycle"></i> Record Scrap Sale
                        </button>
                        <?php endif; ?>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="salesTable">
                            <thead class="table-light text-uppercase small text-secondary">
                                <tr>
                                    <th class="ps-3 py-3">Sale #</th>
                                    <th class="py-3">Stitching Batch</th>
                                    <th class="py-3">Buyer Details</th>
                                    <th class="py-3">Wastage Category</th>
                                    <th class="text-center py-3">Qty Sold</th>
                                    <th class="text-center py-3">Rate</th>
                                    <th class="text-center py-3">Total Value</th>
                                    <th class="text-center py-3">Amount Received</th>
                                    <th class="text-center py-3">Sale Date</th>
                                    <th class="text-center py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($wastageSales)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center py-5 text-muted">
                                            <i class="bi bi-recycle fs-1 d-block mb-2 text-danger opacity-50"></i>
                                            No stitching scrap sales recorded yet. Liquidate trimmings and thread waste via <strong>"Record Scrap Sale"</strong>.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($wastageSales as $s): ?>
                                        <tr>
                                            <td class="ps-3 py-3 fw-bold font-monospace text-danger">
                                                <?= htmlspecialchars($s['sale_no']) ?>
                                            </td>
                                            <td class="py-3">
                                                <div class="fw-semibold text-dark">
                                                    <?= htmlspecialchars($s['stitching_name'] ?: $s['stitching_batch_no']) ?>
                                                </div>
                                                <span class="badge bg-light text-secondary font-monospace border" style="font-size: 0.7rem;">
                                                    <?= htmlspecialchars($s['stitching_batch_no']) ?>
                                                </span>
                                            </td>
                                            <td class="py-3">
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($s['buyer_name']) ?></div>
                                                <div class="small text-muted font-monospace"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($s['buyer_phone']) ?></div>
                                                <?php if ($s['buyer_company']): ?>
                                                    <div class="small text-muted"><?= htmlspecialchars($s['buyer_company']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3">
                                                <span class="badge bg-light text-dark border">
                                                    <?= htmlspecialchars($s['wastage_category']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center py-3 fw-bold font-monospace">
                                                <?= number_format((float)$s['quantity'], 2) ?> <?= htmlspecialchars($s['unit_type'] ?: 'KG') ?>
                                            </td>
                                            <td class="text-center py-3 font-monospace small">
                                                ₹<?= number_format((float)$s['selling_rate'], 2) ?>/<?= htmlspecialchars($s['unit_type'] ?: 'kg') ?>
                                            </td>
                                            <td class="text-center py-3 font-monospace fw-bold text-dark">
                                                ₹<?= number_format((float)$s['total_price'], 2) ?>
                                            </td>
                                            <td class="text-center py-3 font-monospace fw-bold text-success">
                                                ₹<?= number_format((float)$s['amount_received'], 2) ?>
                                            </td>
                                            <td class="text-center py-3 small text-muted">
                                                <?= date('d M Y', strtotime($s['sale_date'])) ?>
                                            </td>
                                            <td class="text-center py-3">
                                                <?php if ($s['payment_status'] === 'paid'): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Paid</span>
                                                <?php elseif ($s['payment_status'] === 'partial'): ?>
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">Partial</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">Pending</span>
                                                <?php endif; ?>
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
</div>

<!-- ============================================================== -->
<!-- MODAL: STITCHING WASTAGE SELLING (MATCHING IMAGE 2)            -->
<!-- ============================================================== -->
<div class="modal fade" id="modalSellStitchingWastage" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom py-3">
                <div>
                    <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2 mb-0">
                        <i class="bi bi-recycle text-danger"></i> Stitching Wastage Selling
                    </h5>
                    <div class="text-muted small">
                        Liquidate and invoice thread waste, fabric trimmings, rejected panels & production scrap generated from stitching.
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= \App\Core\Controller::url('/production/stitching/wastage/sell') ?>" method="POST" id="formSellStitchingWastage">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                
                <div class="modal-body p-4">
                    
                    <!-- 1. SELECT STITCHING BATCH -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark mb-1">
                            Select Stitching Module Batch / Program <span class="text-danger">*</span>
                        </label>
                        <select name="stitching_id" id="scrapStitchingBatch" class="form-select" required>
                            <option value="">-- Select Completed / In-Progress Stitching Batch --</option>
                            <?php foreach ($saleEligibleBatches as $sb): ?>
                                <option value="<?= $sb['id'] ?>"
                                        data-name="<?= htmlspecialchars($sb['stitching_name'] ?: 'Stitching Batch') ?>"
                                        data-batch="<?= htmlspecialchars($sb['display_batch_no']) ?>"
                                        data-cut="<?= (int)$sb['cut_pieces'] ?>"
                                        data-stitched="<?= (int)$sb['stitched_pieces'] ?>"
                                        data-weight="<?= (float)$sb['stitch_weight'] ?>"
                                        data-sold="<?= (float)$sb['sold_wastage_qty'] ?>">
                                    <?= htmlspecialchars($sb['display_batch_no'] . ' - ' . ($sb['stitching_name'] ?: 'Lot') . ' (Cut: ' . (int)$sb['cut_pieces'] . ' pcs | Stitched: ' . (int)$sb['stitched_pieces'] . ' pcs)') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Selected Batch Chip -->
                        <div id="scrapBatchChip" class="badge bg-light text-primary border p-2 mt-2" style="display:none; font-size: 0.85rem;">
                            <i class="bi bi-check2-circle me-1"></i> <span id="scrapBatchChipText">Batch info</span>
                        </div>

                        <!-- Batch Info Box (Matching Image 2) -->
                        <div id="scrapBatchInfoBox" class="alert alert-light border rounded-3 py-2 px-3 small mt-2 mb-0" style="display:none;">
                            <div class="d-flex flex-wrap justify-content-between text-secondary">
                                <span>Received: <strong class="text-dark" id="scrapInfoReceived">0 pcs</strong></span>
                                <span>Stitched: <strong class="text-dark" id="scrapInfoStitched">0 pcs</strong></span>
                                <span>Batch Weight: <strong class="text-dark" id="scrapInfoWeight">0.00 kg</strong></span>
                                <span>Past Wastage Sold: <strong class="text-danger" id="scrapInfoSold">0.00 kg</strong></span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. BUYER INFORMATION -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-dark mb-1">
                                Scrap Buyer Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="buyer_name" class="form-control" placeholder="e.g. City Scrap Traders" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-dark mb-1">
                                Buyer Phone (10 digits) <span class="text-danger">*</span>
                            </label>
                            <input type="tel" name="buyer_phone" id="buyerPhone" class="form-control font-monospace" 
                                   placeholder="e.g. 9876543210" pattern="[0-9]{10}" maxlength="10" required>
                            <div class="form-text small text-muted">Must be exactly 10 numeric digits.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-dark mb-1">
                                Buyer Company / Organization
                            </label>
                            <input type="text" name="buyer_company" class="form-control" placeholder="e.g. City Scrap & Recycling Co.">
                        </div>
                    </div>

                    <!-- 3. WASTAGE CATEGORY & UNIT -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold text-dark mb-1">
                                Stitching Waste Category <span class="text-danger">*</span>
                            </label>
                            <select name="wastage_category" class="form-select" required>
                                <option value="Thread & Yarn Waste">Thread & Yarn Waste</option>
                                <option value="Fabric Trimming / Strips">Fabric Trimming / Strips</option>
                                <option value="Rejected Cut Pieces / Defective Garments">Rejected Cut Pieces / Defective Garments</option>
                                <option value="Bale Scrap">Bale Scrap</option>
                                <option value="General Production Waste">General Production Waste</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-dark mb-1">
                                Unit Type <span class="text-danger">*</span>
                            </label>
                            <select name="unit_type" id="scrapUnitType" class="form-select fw-semibold" required>
                                <option value="KG" selected>Kilograms (KG)</option>
                                <option value="Pcs">Pieces (Pcs)</option>
                            </select>
                        </div>
                    </div>

                    <!-- 4. QUANTITY, RATE & DYNAMIC TOTAL PRICE (MATCHING IMAGE 2) -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-dark mb-1">
                                Quantity <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="quantity" id="scrapQuantity" class="form-control font-monospace fw-bold" placeholder="0.00" min="0.01" required>
                                <span class="input-group-text font-monospace small" id="lblScrapUnit">KG</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-dark mb-1">
                                Selling Rate (₹ per unit) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" name="selling_rate" id="scrapSellingRate" class="form-control font-monospace fw-bold" placeholder="0.00" min="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-dark mb-1">
                                Total Sale Price (₹) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" name="total_price" id="scrapTotalPrice" class="form-control font-monospace fw-bold text-success" placeholder="0.00" min="0.01" required>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic calculation label (Matching Image 2) -->
                    <div class="p-2 mb-3 bg-light rounded-3 text-center small text-secondary font-monospace" id="scrapCalcDisplay">
                        0.00 KG × ₹0.00/KG = ₹0.00
                    </div>

                    <!-- 5. AMOUNT RECEIVED & SALE DATE -->
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark mb-1">
                                Amount Received (₹) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" name="amount_received" id="scrapAmountReceived" class="form-control font-monospace fw-bold" placeholder="0.00" required>
                            </div>
                            <div class="form-text small text-muted">Directly inwards cash into Concern financial cash account.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark mb-1">
                                Sale Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="sale_date" class="form-control font-monospace" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                </div>
                <div class="modal-footer border-top bg-light py-3 px-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary fw-semibold" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-danger fw-semibold shadow-sm" id="btnSubmitScrapSale">
                        <i class="bi bi-check2-circle me-1"></i> Record Wastage Sale & Inward Cash
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DELETE CONFIRMATION FORM -->
<form id="formDeleteBatch" action="<?= \App\Core\Controller::url('/production/stitching/delete') ?>" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="id" id="deleteBatchId">
</form>

<script>
function confirmDeleteBatch(id, batchNo) {
    if (confirm(`Are you sure you want to delete Stitching Batch "${batchNo}"?\nAll associated variant items and finished stock entries will be reversed.`)) {
        document.getElementById('deleteBatchId').value = id;
        document.getElementById('formDeleteBatch').submit();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // Quick search filters
    const filterBatches = document.getElementById('filterBatchesInput');
    if (filterBatches) {
        filterBatches.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('#batchesTable tbody tr').forEach(tr => {
                tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    const filterSales = document.getElementById('filterSalesInput');
    if (filterSales) {
        filterSales.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('#salesTable tbody tr').forEach(tr => {
                tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    // Modal scrap calculation logic matching Image 2
    const batchSelect = document.getElementById('scrapStitchingBatch');
    const chipDiv = document.getElementById('scrapBatchChip');
    const chipText = document.getElementById('scrapBatchChipText');
    const infoBox = document.getElementById('scrapBatchInfoBox');
    const infoReceived = document.getElementById('scrapInfoReceived');
    const infoStitched = document.getElementById('scrapInfoStitched');
    const infoWeight = document.getElementById('scrapInfoWeight');
    const infoSold = document.getElementById('scrapInfoSold');

    const unitSelect = document.getElementById('scrapUnitType');
    const lblUnit = document.getElementById('lblScrapUnit');
    const inputQty = document.getElementById('scrapQuantity');
    const inputRate = document.getElementById('scrapSellingRate');
    const inputTotal = document.getElementById('scrapTotalPrice');
    const inputReceived = document.getElementById('scrapAmountReceived');
    const calcDisplay = document.getElementById('scrapCalcDisplay');

    batchSelect.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        if (!opt || !this.value) {
            chipDiv.style.display = 'none';
            infoBox.style.display = 'none';
            return;
        }

        const name = opt.dataset.name;
        const batch = opt.dataset.batch;
        const cut = opt.dataset.cut;
        const stitched = opt.dataset.stitched;
        const weight = parseFloat(opt.dataset.weight || 0).toFixed(2);
        const sold = parseFloat(opt.dataset.sold || 0).toFixed(2);

        chipDiv.style.display = 'inline-flex';
        chipText.textContent = `${batch} - ${name} (Cut: ${cut} pcs | Stitched: ${stitched} pcs)`;

        infoBox.style.display = 'block';
        infoReceived.textContent = `${cut} pcs`;
        infoStitched.textContent = `${stitched} pcs`;
        infoWeight.textContent = `${weight} kg`;
        infoSold.textContent = `${sold} kg`;
    });

    unitSelect.addEventListener('change', function () {
        lblUnit.textContent = this.value;
        updateCalculation();
    });

    function updateCalculation() {
        const qty = parseFloat(inputQty.value) || 0;
        const rate = parseFloat(inputRate.value) || 0;
        const unit = unitSelect.value;
        const tot = (qty * rate).toFixed(2);

        inputTotal.value = tot;
        inputReceived.value = tot;
        calcDisplay.textContent = `${qty.toFixed(2)} ${unit} × ₹${rate.toFixed(2)}/${unit} = ₹${tot}`;
    }

    inputQty.addEventListener('input', updateCalculation);
    inputRate.addEventListener('input', updateCalculation);

    // If user edits total manually, update calc display and amount received
    inputTotal.addEventListener('input', function () {
        const tot = parseFloat(this.value) || 0;
        inputReceived.value = tot.toFixed(2);
        calcDisplay.textContent = `Custom Sale Total: ₹${tot.toFixed(2)}`;
    });

    // Form validation for 10-digit phone
    const formSell = document.getElementById('formSellStitchingWastage');
    formSell.addEventListener('submit', function (e) {
        const phone = document.getElementById('buyerPhone').value.trim();
        const cleanPhone = phone.replace(/[^0-9]/g, '');
        if (cleanPhone.length !== 10) {
            e.preventDefault();
            alert('Buyer Phone number must be exactly 10 numeric digits (e.g. 9876543210).');
            document.getElementById('buyerPhone').focus();
        }
    });
});
</script>
