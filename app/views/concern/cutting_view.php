<?php
/**
 * View: Production Cutting Batch Details
 * Location: inventory_mekha/app/views/concern/cutting_view.php
 */
?>
<div class="container-fluid px-4 py-3">

    <!-- BREADCRUMBS -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/dashboard') ?>" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/catalog') ?>" class="text-decoration-none text-muted">Production</a></li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/cutting') ?>" class="text-decoration-none text-muted">Cutting</a></li>
            <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page"><?= htmlspecialchars($batch['batch_no'] ?: $batch['log_no']) ?></li>
        </ol>
    </nav>

    <!-- HEADER BAR -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary font-monospace px-3 py-1.5 fs-6">
                    <?= htmlspecialchars($batch['batch_no'] ?: $batch['log_no']) ?>
                </span>
                <h4 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($batch['cutting_name']) ?></h4>
            </div>
            <p class="text-secondary small mb-0">
                Program: <strong><?= htmlspecialchars($batch['program_no'] ?? '-') ?></strong> &bull; <?= htmlspecialchars($batch['program_name'] ?? '') ?>
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= \App\Core\Controller::url('/production/cutting') ?>" class="btn btn-light border shadow-sm fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Back to Batches
            </a>
            <?php 
                $availScrap = max(0.00, (float)$batch['wastage_qty'] - (float)$batch['sold_wastage_qty']);
                if ($availScrap > 0.01 && \App\Core\Auth::can('cutting_wastage', 'add')): 
            ?>
                <a href="<?= \App\Core\Controller::url('/production/cutting?tab=wastage') ?>" class="btn btn-outline-danger shadow-sm fw-semibold">
                    <i class="bi bi-recycle me-1"></i> Sell Scrap (<?= number_format($availScrap, 2) ?> kg avail)
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 6 ANALYTICS METRICS CARDS MATCHING IMAGE 2 -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center">
                <small class="text-muted d-block small mb-1">Fabric Rate</small>
                <span class="fw-bold fs-6 text-dark">₹<?= number_format($batch['fabric_rate_per_kg'], 2) ?> / kg</span>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center">
                <small class="text-muted d-block small mb-1">Wastage Cost</small>
                <span class="fw-bold fs-6 text-danger font-monospace">₹<?= number_format($batch['wastage_cost'], 2) ?></span>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center">
                <small class="text-muted d-block small mb-1">Wastage %</small>
                <span class="fw-bold fs-6 text-warning"><?= number_format($batch['wastage_percent'], 1) ?>%</span>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center">
                <small class="text-muted d-block small mb-1">Used Fabric Weight</small>
                <span class="fw-bold fs-6 text-primary"><?= number_format($batch['used_weight'], 2) ?> kg</span>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center">
                <small class="text-muted d-block small mb-1">Total Cut Output</small>
                <span class="fw-bold fs-6 text-success"><?= number_format($batch['cut_pieces']) ?> pcs</span>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center">
                <small class="text-muted d-block small mb-1">Fabric Cost / Pc</small>
                <span class="fw-bold fs-6 text-info font-monospace">₹<?= number_format($batch['fabric_cost_per_piece'], 2) ?></span>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- LEFT: BATCH DETAILS & WASTAGE BREAKDOWN -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-info-circle text-primary me-1"></i> Batch Overview</h6>
                </div>
                <div class="card-body p-3">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted small" style="width: 40%;">Cutting Name:</td>
                            <td class="fw-bold text-dark small"><?= htmlspecialchars($batch['cutting_name']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Batch Number:</td>
                            <td class="fw-bold font-monospace text-primary small"><?= htmlspecialchars($batch['batch_no'] ?: $batch['log_no']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Status:</td>
                            <td>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <?= ucfirst(str_replace('_', ' ', $batch['status'])) ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Cutting Master:</td>
                            <td class="fw-semibold text-dark small"><?= htmlspecialchars($batch['master_name'] ?: 'Not Assigned') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Total Roll Weight:</td>
                            <td class="fw-bold text-dark small"><?= number_format($batch['total_roll_weight'], 2) ?> kg</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Total Roll Value:</td>
                            <td class="fw-bold font-monospace text-success small">₹<?= number_format($batch['total_roll_price'], 2) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Wastage Scrap:</td>
                            <td class="fw-bold text-danger small"><?= number_format($batch['wastage_qty'], 2) ?> kg</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Scrap Sold:</td>
                            <td class="fw-bold text-secondary small"><?= number_format($batch['sold_wastage_qty'], 2) ?> kg</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Scrap Available:</td>
                            <td class="fw-bold text-success small"><?= number_format($availScrap, 2) ?> kg</td>
                        </tr>
                        <?php if (!empty($batch['notes'])): ?>
                            <tr>
                                <td class="text-muted small">Notes:</td>
                                <td class="text-secondary small"><?= nl2br(htmlspecialchars($batch['notes'])) ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- Scrap Sales against this batch -->
            <?php if (!empty($sales)): ?>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-recycle text-danger me-1"></i> Recorded Scrap Sales</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Sale No</th>
                                    <th>Buyer</th>
                                    <th class="text-end">Weight</th>
                                    <th class="text-end">Total Price</th>
                                </tr>
                            </thead>
                            <tbody class="small font-monospace">
                                <?php foreach ($sales as $s): ?>
                                    <tr>
                                        <td><span class="badge bg-dark"><?= htmlspecialchars($s['sale_no']) ?></span></td>
                                        <td class="font-sans-serif"><?= htmlspecialchars($s['buyer_name']) ?></td>
                                        <td class="text-end text-danger"><?= number_format($s['wastage_weight'], 2) ?> kg</td>
                                        <td class="text-end text-success fw-bold">₹<?= number_format($s['total_price'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT: DYNAMIC VARIANT CUT BREAKDOWN -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-grid-3x3-gap-fill text-primary"></i> Cut Output by Variant / Size
                    </h6>
                    <span class="badge bg-success px-3 py-1.5 fw-bold fs-7"><?= number_format($batch['cut_pieces']) ?> Cut pcs</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th class="ps-3">#</th>
                                <th>Roll Tag / Material</th>
                                <th>Variant / Size</th>
                                <th>Product Code</th>
                                <th class="text-end pe-3">Cut Output (pcs)</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php if (empty($items)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No variant breakdowns recorded.</td></tr>
                            <?php else: ?>
                                <?php foreach ($items as $idx => $it): ?>
                                    <tr>
                                        <td class="ps-3 text-muted"><?= $idx + 1 ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark border font-monospace">
                                                <?= htmlspecialchars($it['roll_piece_tag'] ?: 'Roll') ?>
                                            </span>
                                            <small class="text-muted ms-1"><?= htmlspecialchars($it['fabric_name'] ?? '') ?></small>
                                        </td>
                                        <td>
                                            <strong class="text-dark"><?= htmlspecialchars($it['size'] ?: '-') ?></strong>
                                            <small class="text-muted d-block"><?= htmlspecialchars($it['variant_name'] ?? '') ?></small>
                                        </td>
                                        <td class="font-monospace text-muted small">
                                            <?= htmlspecialchars($it['product_code'] ?: '-') ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold fs-7 px-2.5 py-1">
                                                <?= number_format($it['cut_pieces']) ?> pcs
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-light fw-bold small">
                            <tr>
                                <td colspan="4" class="text-end ps-3">Total Batch Output:</td>
                                <td class="text-end pe-3 text-success fs-6 font-monospace">
                                    <?= number_format($batch['cut_pieces']) ?> pcs
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
