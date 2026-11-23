<?php
// app/views/concern/batch.php
// Finished Production Batches Ledger & Management View (Image 1)
?>
<div class="container-fluid px-3 py-2">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small text-muted mb-0">
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/dashboard') ?>" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/catalog') ?>" class="text-decoration-none text-muted">Production</a></li>
            <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Finished Batches</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-box-seam text-success"></i> Finished Production Batches
            </h4>
            <p class="text-secondary small mb-0">Finished goods packing boxes, destination warehouse assignment & approval</p>
        </div>
        <?php if (\App\Core\Auth::can('batch', 'create')): ?>
        <div class="d-flex gap-2">
            <a href="<?= \App\Core\Controller::url('/production/batches/create') ?>" class="btn btn-outline-success fw-semibold shadow-sm px-3 py-2">
                <i class="bi bi-file-earmark-plus me-1"></i> New Production Bill
            </a>
            <a href="<?= \App\Core\Controller::url('/production/batches/create') ?>" class="btn btn-primary fw-semibold shadow-sm px-3 py-2" style="background:#4f46e5; border-color:#4f46e5;">
                <i class="bi bi-box-seam me-1"></i> Pack New Batch
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Filter Bar Card -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= \App\Core\Controller::url('/production/batches') ?>" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted fw-semibold mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="PREPARING" <?= ($statusFilter === 'PREPARING') ? 'selected' : '' ?>>PREPARING</option>
                        <option value="APPROVED" <?= ($statusFilter === 'APPROVED') ? 'selected' : '' ?>>APPROVED</option>
                        <option value="DISPATCHED" <?= ($statusFilter === 'DISPATCHED') ? 'selected' : '' ?>>DISPATCHED</option>
                        <option value="RECEIVED" <?= ($statusFilter === 'RECEIVED') ? 'selected' : '' ?>>RECEIVED</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted fw-semibold mb-1">Destination Warehouse</label>
                    <select name="warehouse" class="form-select form-select-sm">
                        <option value="">All Warehouses</option>
                        <?php foreach ($warehouses as $wh): ?>
                            <option value="<?= htmlspecialchars($wh['name']) ?>" <?= ($warehouseFilter === $wh['name']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($wh['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted fw-semibold mb-1">Search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Batch #, Program #, Product name, Warehouse..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold" style="background:#4f46e5; border-color:#4f46e5;">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <a href="<?= \App\Core\Controller::url('/production/batches') ?>" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                        <i class="bi bi-arrow-clockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                <thead class="table-light text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.05em;">
                    <tr>
                        <th class="ps-3 py-3">BATCH #</th>
                        <th>PROGRAM</th>
                        <th>PRODUCT</th>
                        <th>TOTAL PIECES</th>
                        <th>BOX COUNT</th>
                        <th>DESTINATION WAREHOUSE</th>
                        <th>PACKED BY</th>
                        <th>STATUS</th>
                        <th class="text-end pe-3">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($batches)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                                No production batches found matching the selected criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($batches as $b): ?>
                            <tr>
                                <td class="ps-3 py-3">
                                    <div class="fw-bold text-dark font-monospace"><?= htmlspecialchars($b['batch_no']) ?></div>
                                    <small class="text-muted"><?= date('d M Y', strtotime($b['mfg_date'] ?? $b['created_at'])) ?></small>
                                </td>
                                <td>
                                    <a href="#" class="text-primary fw-semibold text-decoration-none">
                                        <?= htmlspecialchars($b['program_no'] ?? 'PRG-20261113-0001') ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($b['program_name'] ?? $b['variant_name'] ?? 'KIDS - BABY - DRESS') ?></div>
                                </td>
                                <td>
                                    <span class="fw-bold text-success fs-6"><?= (int)$b['batch_qty'] ?> pcs</span>
                                </td>
                                <td>
                                    <span class="text-secondary"><?= (int)($b['total_boxes'] ?: 1) ?> boxes</span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border px-2 py-1 font-monospace" style="font-size: 0.8rem;">
                                        <?= htmlspecialchars(explode(' (', $b['warehouse_name'] ?? 'wareouse')[0]) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-dark small"><?= htmlspecialchars($b['packed_by_name'] ?? 'System Administrator') ?></span>
                                </td>
                                <td>
                                    <?php
                                    $st = strtoupper($b['status'] ?? 'PREPARING');
                                    if ($st === 'PREPARING'): ?>
                                        <span class="badge text-dark rounded-pill px-2.5 py-1 fw-bold" style="background:#fef08a; font-size: 0.75rem;">PREPARING</span>
                                    <?php elseif ($st === 'RECEIVED'): ?>
                                        <span class="badge text-white rounded-pill px-2.5 py-1 fw-bold" style="background:#06b6d4; font-size: 0.75rem;">RECEIVED</span>
                                    <?php elseif ($st === 'APPROVED'): ?>
                                        <span class="badge bg-success rounded-pill px-2.5 py-1 fw-bold" style="font-size: 0.75rem;">APPROVED</span>
                                    <?php elseif ($st === 'DISPATCHED'): ?>
                                        <span class="badge bg-primary rounded-pill px-2.5 py-1 fw-bold" style="font-size: 0.75rem;">DISPATCHED</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary rounded-pill px-2.5 py-1"><?= htmlspecialchars($st) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <!-- 1. View Details Modal -->
                                        <button type="button" class="btn btn-sm btn-outline-info p-1 px-1.5" title="View Batch Details" onclick='openViewBatchModal(<?= json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <!-- 2. Edit Batch Modal -->
                                        <?php if (\App\Core\Auth::can('batch', 'edit')): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary p-1 px-1.5" title="Edit Batch" onclick='openEditBatchModal(<?= json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php endif; ?>

                                        <!-- 3. Print Production Bill / Invoice -->
                                        <a href="<?= \App\Core\Controller::url('/production/batches/label/' . $b['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary p-1 px-1.5" title="Print Production Bill">
                                            <i class="bi bi-printer"></i>
                                        </a>

                                        <!-- 4. Carton QR Sticker Label (Image 3) -->
                                        <a href="<?= \App\Core\Controller::url('/production/batches/label/' . $b['id']) ?>" class="btn btn-sm btn-outline-success p-1 px-1.5" title="Warehouse Carton QR Sticker">
                                            <i class="bi bi-file-earmark-code"></i>
                                        </a>

                                        <!-- 5. Supervisor Approve / Gatepass -->
                                        <?php if (\App\Core\Auth::can('batch', 'edit')): ?>
                                            <?php if (strtoupper($b['status'] ?? '') === 'PREPARING' || strtoupper($b['status'] ?? '') === 'DRAFT'): ?>
                                                <form action="<?= \App\Core\Controller::url('/m/batch/approve') ?>" method="POST" class="d-inline" onsubmit="return confirm('Supervisor Approval: Approve batch <?= htmlspecialchars($b['batch_no']) ?> and credit live inventory?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? \App\Core\Auth::csrfToken()) ?>">
                                                    <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning p-1 px-1.5 text-dark" title="Supervisor Approve Batch">
                                                        <i class="bi bi-check2-circle"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="btn btn-sm btn-outline-secondary p-1 px-1.5 disabled" title="Already Processed">
                                                    <i class="bi bi-truck"></i>
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <!-- 6. View Piece QR Codes Modal -->
                                        <button type="button" class="btn btn-sm btn-outline-dark p-1 px-1.5" title="View Piece QR Codes" onclick="openPieceQrModal('<?= htmlspecialchars($b['batch_no']) ?>', <?= (int)$b['id'] ?>)">
                                            <i class="bi bi-qr-code"></i>
                                        </button>

                                        <!-- 7. Delete Batch -->
                                        <?php if (\App\Core\Auth::can('batch', 'delete')): ?>
                                        <form action="<?= \App\Core\Controller::url('/m/batch/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete batch <?= htmlspecialchars($b['batch_no']) ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? \App\Core\Auth::csrfToken()) ?>">
                                            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger p-1 px-1.5" title="Delete Batch">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <div class="card-footer bg-white border-top py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="small text-muted d-flex align-items-center gap-3">
                <span>Showing <?= count($batches) > 0 ? '1' : '0' ?> to <?= count($batches) ?> of <?= (int)$totalBatches ?> batches</span>
                <span class="text-secondary">Show: <strong>10 per page</strong></span>
            </div>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= \App\Core\Controller::url('/production/batches?page=' . max(1, $page - 1) . '&status=' . urlencode($statusFilter) . '&warehouse=' . urlencode($warehouseFilter) . '&search=' . urlencode($search)) ?>">&lt; Prev</a>
                    </li>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= ($page === $p) ? 'active' : '' ?>">
                            <a class="page-link" href="<?= \App\Core\Controller::url('/production/batches?page=' . $p . '&status=' . urlencode($statusFilter) . '&warehouse=' . urlencode($warehouseFilter) . '&search=' . urlencode($search)) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= \App\Core\Controller::url('/production/batches?page=' . min($totalPages, $page + 1) . '&status=' . urlencode($statusFilter) . '&warehouse=' . urlencode($warehouseFilter) . '&search=' . urlencode($search)) ?>">Next &gt;</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Modal 1: View Batch Details -->
<div class="modal fade" id="modalViewBatch" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3 border-bottom">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="viewBatchTitle">
                    <i class="bi bi-box-seam text-primary"></i> Batch Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="viewBatchBody">
                <!-- Dynamically populated via JS -->
            </div>
            <div class="modal-footer bg-light py-2 border-top">
                <a href="#" id="viewBatchLabelLink" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-printer me-1"></i> Print Carton QR Sticker
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php if (\App\Core\Auth::can('batch', 'edit')): ?>
<!-- Modal 2: Edit Batch Details -->
<div class="modal fade" id="modalEditBatch" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/m/batch/update') ?>" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? \App\Core\Auth::csrfToken()) ?>">
            <input type="hidden" name="id" id="editBatchId">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i> Edit Finished Batch</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Batch Number</label>
                    <input type="text" id="editBatchNo" class="form-control bg-light font-monospace" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Box / Carton Count *</label>
                    <input type="number" name="total_boxes" id="editBatchBoxes" class="form-control" min="1" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Destination Warehouse</label>
                    <select name="warehouse_name" id="editBatchWarehouse" class="form-select">
                        <?php foreach ($warehouses as $wh): ?>
                            <option value="<?= htmlspecialchars($wh['name']) ?>"><?= htmlspecialchars($wh['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Batch Lifecycle Status</label>
                    <select name="status" id="editBatchStatus" class="form-select">
                        <option value="PREPARING">PREPARING</option>
                        <option value="APPROVED">APPROVED</option>
                        <option value="DISPATCHED">DISPATCHED</option>
                        <option value="RECEIVED">RECEIVED</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-3 fw-semibold">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Modal 3: View Individual Piece QR Codes -->
<div class="modal fade" id="modalPieceQr" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-qr-code-scan text-warning"></i>
                    <span id="pieceQrTitle">Individual Piece QR Codes</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="small text-muted" id="pieceQrMeta">Loading piece codes...</div>
                    <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Print Piece Codes
                    </button>
                </div>
                <div class="row g-2" id="pieceQrGrid">
                    <!-- Populated via AJAX -->
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function openViewBatchModal(b) {
    document.getElementById('viewBatchTitle').innerHTML = '<i class="bi bi-box-seam text-primary"></i> Batch Details: ' + (b.batch_no || '');
    document.getElementById('viewBatchLabelLink').href = '<?= \App\Core\Controller::url('/production/batches/label/') ?>' + b.id;

    var html = `
        <div class="row g-3">
            <div class="col-md-6">
                <div class="p-3 bg-light rounded-3 border">
                    <small class="text-muted d-block mb-1">Batch Number</small>
                    <div class="fw-bold font-monospace fs-5 text-dark">${b.batch_no || 'N/A'}</div>
                    <small class="text-muted d-block mt-2 mb-1">Program & Product</small>
                    <div class="fw-semibold text-primary">${b.program_no || 'PRG-20261113-0001'} — ${b.program_name || b.variant_name || 'KIDS - BABY - DRESS'}</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 bg-light rounded-3 border">
                    <small class="text-muted d-block mb-1">Packaging Metrics</small>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Total Pieces:</span> <strong class="text-success">${parseInt(b.batch_qty || 0)} pcs</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Carton / Box Count:</span> <strong>${parseInt(b.total_boxes || 1)} box(es)</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Status:</span> <span class="badge bg-primary">${b.status || 'PREPARING'}</span>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="p-3 bg-light rounded-3 border">
                    <div class="row g-2 small">
                        <div class="col-md-4">
                            <span class="text-muted">Destination Warehouse:</span><br>
                            <strong>${b.warehouse_name || 'wareouse (WH-01)'}</strong>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted">Packed By:</span><br>
                            <strong>${b.packed_by_name || 'System Administrator'}</strong>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted">Production Bill #:</span><br>
                            <strong class="font-monospace">${b.bill_no || 'INV-20261113-0001'}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.getElementById('viewBatchBody').innerHTML = html;
    new bootstrap.Modal(document.getElementById('modalViewBatch')).show();
}

function openEditBatchModal(b) {
    document.getElementById('editBatchId').value = b.id;
    document.getElementById('editBatchNo').value = b.batch_no || '';
    document.getElementById('editBatchBoxes').value = b.total_boxes || 1;
    document.getElementById('editBatchWarehouse').value = b.warehouse_name || 'wareouse (WH-01)';
    document.getElementById('editBatchStatus').value = (b.status || 'PREPARING').toUpperCase();

    new bootstrap.Modal(document.getElementById('modalEditBatch')).show();
}

function openPieceQrModal(batchNo, batchId) {
    document.getElementById('pieceQrTitle').innerText = 'Piece QR Codes: ' + batchNo;
    document.getElementById('pieceQrMeta').innerText = 'Loading piece tags...';
    document.getElementById('pieceQrGrid').innerHTML = '<div class="col-12 text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div> Fetching sequential pieces...</div>';

    new bootstrap.Modal(document.getElementById('modalPieceQr')).show();

    fetch('<?= \App\Core\Controller::url('/m/batch/pieces/') ?>' + batchId)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.success) {
                document.getElementById('pieceQrGrid').innerHTML = '<div class="col-12 text-danger">' + (data.error || 'Failed to load') + '</div>';
                return;
            }

            document.getElementById('pieceQrMeta').innerText = 'Total ' + data.total_pieces + ' pieces (' + data.product_name + ' - Size ' + data.size + ')';

            var grid = '';
            data.tags.forEach(function(tag, idx) {
                var qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=' + encodeURIComponent(tag);
                grid += `
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-2 text-center bg-white shadow-sm h-100">
                            <img src="${qrUrl}" alt="${tag}" class="img-fluid mb-1" style="width: 70px; height: 70px;">
                            <div class="fw-bold font-monospace small text-dark">${tag}</div>
                            <span class="badge bg-light text-secondary border px-1" style="font-size:0.7rem;">#${idx + 1}</span>
                        </div>
                    </div>
                `;
            });
            document.getElementById('pieceQrGrid').innerHTML = grid;
        })
        .catch(function(err) {
            document.getElementById('pieceQrGrid').innerHTML = '<div class="col-12 text-danger">Error: ' + err.message + '</div>';
        });
}
</script>
