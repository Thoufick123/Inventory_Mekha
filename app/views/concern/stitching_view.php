<?php
/**
 * View: Stitching Batch Details
 * Location: inventory_mekha/app/views/concern/stitching_view.php
 */
?>
<style>
.stitching-view-table {
    border-collapse: collapse;
    font-size: 0.84rem;
}
.stitching-view-table thead th {
    background-color: #f1f5f9 !important;
    color: #475569 !important;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 0.65rem 0.75rem;
    border-bottom: 2px solid #cbd5e1 !important;
    white-space: nowrap;
}
.stitching-view-table tbody td {
    padding: 0.55rem 0.75rem;
    border-color: #e2e8f0 !important;
    vertical-align: middle;
    white-space: nowrap;
}
.stitching-view-table tbody tr:hover {
    background-color: #f8fafc;
}
</style>
<div class="container-fluid px-3 pt-1 pb-3">

    <!-- HEADER -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <div>
            <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-stack text-primary"></i> <?= htmlspecialchars($batch['stitching_name'] ?: $batch['display_batch_no']) ?>
                <span class="badge bg-light text-primary font-monospace border fs-6"><?= htmlspecialchars($batch['display_batch_no']) ?></span>
            </h4>
            <p class="text-secondary small mb-0">
                Created on <?= date('d M Y, h:i A', strtotime($batch['created_at'])) ?> <?= $batch['created_by_name'] ? 'by ' . htmlspecialchars($batch['created_by_name']) : '' ?>
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if (\App\Core\Auth::can('label_generation', 'add') || \App\Core\Auth::can('stitching_wastage', 'view')): ?>
            <?php
                $batchLabelItems = array_map(static fn($it) => [
                    'id'           => (int)$it['id'],
                    'name'         => $it['variant_name'] ?: trim(($it['size'] ?? '') . ' - ' . ($it['color'] ?? '')),
                    'product_code' => $it['product_code'] ?: '-',
                    'qty'          => max(1, (int)$it['stitching_qty']),
                ], $items ?? []);
            ?>
            <button type="button" class="btn btn-dark shadow-sm fw-semibold" onclick='openStitchingBatchLabelModal(<?= (int)$batch['id'] ?>, <?= htmlspecialchars(json_encode($batchLabelItems), ENT_QUOTES) ?>)'>
                <i class="bi bi-printer-fill me-1"></i> Print All Labels
            </button>
            <?php endif; ?>
            <a href="<?= \App\Core\Controller::url('/production/stitching') ?>" 
               class="btn btn-outline-secondary shadow-sm fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>

    <!-- DETAILS ROW -->
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white">
                <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Batch & Source Information</h6>
                <div class="mb-2 d-flex justify-content-between">
                    <span class="text-muted small">Concern:</span>
                    <span class="fw-semibold text-dark small"><?= htmlspecialchars($batch['concern_name'] ?: 'All Concerns') ?></span>
                </div>
                <div class="mb-2 d-flex justify-content-between">
                    <span class="text-muted small">Source Cutting Batch:</span>
                    <span class="fw-semibold text-dark small font-monospace"><?= htmlspecialchars($batch['cutting_batch_no'] ?: 'Direct') ?></span>
                </div>
                <div class="mb-2 d-flex justify-content-between">
                    <span class="text-muted small">Cutting Lot Name:</span>
                    <span class="fw-semibold text-dark small"><?= htmlspecialchars($batch['cutting_name'] ?: '-') ?></span>
                </div>
                <div class="mb-2 d-flex justify-content-between">
                    <span class="text-muted small">Program Code:</span>
                    <span class="fw-semibold text-dark small font-monospace"><?= htmlspecialchars($batch['program_no'] ?: '-') ?></span>
                </div>
                <div class="mb-2 d-flex justify-content-between">
                    <span class="text-muted small">Tailor / Master:</span>
                    <span class="fw-semibold text-dark small"><?= htmlspecialchars($batch['tailor_display_name'] ?: 'Unassigned') ?></span>
                </div>
                <?php if ($batch['notes']): ?>
                    <div class="mt-3 pt-3 border-top">
                        <span class="text-muted small d-block mb-1">Notes & Remarks:</span>
                        <p class="small text-dark mb-0 bg-light p-2 rounded"><?= nl2br(htmlspecialchars($batch['notes'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="row g-3 mb-3">
                <div class="col-sm-3 col-6">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center">
                        <div class="text-muted small text-uppercase fw-semibold">Cut Pieces</div>
                        <div class="fs-4 fw-bold text-primary mt-1"><?= (int)$batch['cut_pieces'] ?></div>
                    </div>
                </div>
                <div class="col-sm-3 col-6">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center">
                        <div class="text-muted small text-uppercase fw-semibold">Stitched Units</div>
                        <div class="fs-4 fw-bold text-success mt-1"><?= (int)$batch['stitched_pieces'] ?></div>
                    </div>
                </div>
                <div class="col-sm-3 col-6">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center">
                        <div class="text-muted small text-uppercase fw-semibold">Cut Fabric Wt</div>
                        <div class="fs-4 fw-bold text-secondary mt-1"><?= number_format((float)$batch['cut_weight'], 2) ?> <span class="fs-6 fw-normal">kg</span></div>
                    </div>
                </div>
                <div class="col-sm-3 col-6">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center">
                        <div class="text-muted small text-uppercase fw-semibold">Stitch Fabric Wt</div>
                        <div class="fs-4 fw-bold text-dark mt-1"><?= number_format((float)$batch['stitch_weight'], 2) ?> <span class="fs-6 fw-normal">kg</span></div>
                    </div>
                </div>
            </div>

            <!-- ITEMS TABLE -->
            <div class="card border-0 shadow-sm rounded-4 bg-white">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-dark">Variant Assembly Breakdown</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle mb-0 stitching-view-table">
                        <thead>
                            <tr>
                                <th>Catalog &amp; Variant</th>
                                <th class="text-center">Cut Qty</th>
                                <th class="text-center">Stitched Qty</th>
                                <th class="text-end">Per Pc Wt</th>
                                <th class="text-end">Total Wt</th>
                                <th class="text-end">Base Price</th>
                                <th class="text-end">Discount Price</th>
                                <?php if (\App\Core\Auth::can('label_generation', 'add') || \App\Core\Auth::can('stitching_wastage', 'view')): ?>
                                <th class="text-center">Labels</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $it):
                                $varLabel = $it['variant_name'] ?: ($it['size'] . ' - ' . $it['color']);
                                $varTitle = $varLabel . ' | ' . ($it['catalog_name'] ?: 'Catalog') . ' | ' . ($it['product_code'] ?: '-');
                            ?>
                                <tr>
                                    <td>
                                        <div class="text-truncate fw-semibold text-dark" style="max-width: 240px;" title="<?= htmlspecialchars($varTitle) ?>">
                                            <?= htmlspecialchars($varLabel) ?>
                                        </div>
                                    </td>
                                    <td class="text-center font-monospace"><?= (int)$it['cut_qty'] ?></td>
                                    <td class="text-center font-monospace fw-bold text-success"><?= (int)$it['stitching_qty'] ?></td>
                                    <td class="text-end font-monospace small"><?= number_format((float)$it['per_pc_weight'], 3) ?> kg</td>
                                    <td class="text-end font-monospace small fw-bold"><?= number_format((float)$it['total_weight'], 2) ?> kg</td>
                                    <td class="text-end font-monospace">₹<?= number_format((float)$it['base_price'], 2) ?></td>
                                    <td class="text-end font-monospace fw-bold text-primary">₹<?= number_format((float)$it['discount_price'], 2) ?></td>
                                    <?php if (\App\Core\Auth::can('label_generation', 'add') || \App\Core\Auth::can('stitching_wastage', 'view')): ?>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-dark text-nowrap"
                                                title="Print Labels for this Variant"
                                                onclick='openStitchingVariantLabelModal(<?= (int)$it['id'] ?>, <?= htmlspecialchars(json_encode($varLabel), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode('Product Code: ' . ($it['product_code'] ?: '-') . ' • stitched qty ' . max(1, (int)$it['stitching_qty'])), ENT_QUOTES) ?>, <?= max(1, (int)$it['stitching_qty']) ?>)'>
                                            <i class="bi bi-printer me-1"></i>Print
                                        </button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/partials/stitching_label_print_js.php'; ?>
