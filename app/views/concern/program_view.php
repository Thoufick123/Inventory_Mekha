<?php
// app/views/concern/program_view.php - Full-page Standalone Production Program View
?>
<div class="container-fluid px-0">
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/') ?>" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item text-muted">Production</li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/catalog?tab=programs') ?>" class="text-decoration-none text-muted">Programs</a></li>
            <li class="breadcrumb-item active fw-semibold text-dark" aria-current="page"><?= htmlspecialchars($program['program_no']) ?></li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <div class="p-2.5 rounded-3 bg-info-subtle text-info border border-info-subtle shadow-xs">
                <i class="bi bi-file-earmark-text fs-4"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h4 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($program['program_name'] ?? 'Production Program') ?></h4>
                    <span class="badge bg-dark font-monospace"><?= htmlspecialchars($program['program_no']) ?></span>
                    <span class="badge <?= $program['status'] === 'completed' ? 'bg-success' : ($program['status'] === 'cancelled' ? 'bg-secondary' : 'bg-primary') ?>">
                        <?= ucfirst(str_replace('_', ' ', $program['status'])) ?>
                    </span>
                </div>
                <p class="text-secondary small mb-0">Planner: <strong><?= htmlspecialchars($program['planner_name'] ?? 'Planner') ?></strong> &bull; Schedule: <?= date('d M Y', strtotime($program['start_date'])) ?> &rarr; <?= date('d M Y', strtotime($program['target_date'])) ?></p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= \App\Core\Controller::url('/production/catalog?tab=programs') ?>" class="btn btn-outline-secondary rounded-3 d-inline-flex align-items-center gap-1.5 shadow-sm fw-semibold">
                <i class="bi bi-arrow-left"></i> Back to Register
            </a>
            <?php if (\App\Core\Auth::can('catalog_program', 'edit')): ?>
            <a href="<?= \App\Core\Controller::url('/production/programs/edit?id=' . $program['id']) ?>" class="btn btn-primary rounded-3 d-inline-flex align-items-center gap-1.5 shadow-sm fw-semibold">
                <i class="bi bi-pencil"></i> Edit Program
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 text-center bg-white">
                <div class="text-secondary small fw-semibold">Total Planned Quantity</div>
                <div class="fs-4 fw-bold text-primary font-monospace"><?= number_format((float)($program['planned_qty'] ?? 0), 2) ?> Units</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 text-center bg-white">
                <div class="text-secondary small fw-semibold">Fabric Rolls Allocated</div>
                <div class="fs-4 fw-bold text-dark font-monospace"><?= count($rolls) ?> Roll(s)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 text-center bg-white">
                <div class="text-secondary small fw-semibold">Cutting Lots Executed</div>
                <div class="fs-4 fw-bold text-success font-monospace"><?= (int)($cutting['total_lots'] ?? 0) ?> Lot(s)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 text-center bg-white">
                <div class="text-secondary small fw-semibold">Finished Pieces Cut</div>
                <div class="fs-4 fw-bold text-info font-monospace"><?= (int)($cutting['total_cut_pieces'] ?? 0) ?> pcs</div>
            </div>
        </div>
    </div>

    <!-- Rolls Table Card -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white py-3 border-bottom">
            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-layers-fill text-primary"></i> Allocated Raw Material Rolls & 3-Tier Categorization
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width: 50px;">#</th>
                        <th>Piece Tag & Material</th>
                        <th>Allocated Qty (Out of Total)</th>
                        <th>Target Pieces</th>
                        <th>3-Tier Category Hierarchy</th>
                        <th>Catalog Style</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rolls)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No rolls assigned to this program.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rolls as $idx => $r): ?>
                            <tr>
                                <td class="text-center font-monospace"><?= $idx + 1 ?></td>
                                <td>
                                    <div class="fw-bold font-monospace text-dark"><?= htmlspecialchars($r['roll_piece_tag'] ?: '-') ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($r['fabric_name'] ?? 'Fabric Roll') ?></div>
                                    <?php if (!empty($r['color'])): ?>
                                        <span class="badge bg-light text-secondary border mt-1" style="font-size: 0.72rem;"><?= htmlspecialchars($r['color']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-bold text-primary font-monospace"><?= number_format((float)$r['allocated_qty'], 2) ?> <?= htmlspecialchars($r['unit']) ?></span>
                                    <div class="small text-muted" style="font-size: 0.75rem;">out of <?= number_format((float)$r['total_roll_qty'], 2) ?> <?= htmlspecialchars($r['unit']) ?> total</div>
                                </td>
                                <td>
                                    <span class="fw-bold text-success font-monospace"><?= (int)$r['target_pieces'] ?></span> <span class="small text-muted">pcs</span>
                                </td>
                                <td>
                                    <?php 
                                        $hier = array_filter([$r['category_name'] ?? '', $r['subcategory_name'] ?? '', $r['subsub_name'] ?? '']);
                                    ?>
                                    <span class="small text-dark fw-semibold"><?= !empty($hier) ? htmlspecialchars(implode(' > ', $hier)) : '-' ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($r['catalog_style_name'])): ?>
                                        <span class="badge bg-light text-dark border">
                                            <?= !empty($r['catalog_style_code']) ? '[' . htmlspecialchars($r['catalog_style_code']) . '] ' : '' ?><?= htmlspecialchars($r['catalog_style_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">General / Per-Roll</span>
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
