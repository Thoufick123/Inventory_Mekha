<?php
// app/views/concern/qc_inspect.php
// 2D QR Garment Scanner Inspection View (Matches Screenshot 4)
?>
<div class="container-fluid px-3 py-2">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small text-muted mb-0">
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/dashboard') ?>" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/catalog') ?>" class="text-decoration-none text-muted">Production</a></li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/qc') ?>" class="text-decoration-none text-muted">QC Inspections</a></li>
            <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Inspect Batch <?= htmlspecialchars($qc['qc_no']) ?></li>
        </ol>
    </nav>

    <!-- Top Info Bar -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h5 class="fw-bold mb-0 text-dark">
                Dynamic 2D QR Piece Inspection for <?= htmlspecialchars($qc['stitching_batch_no'] ?: $qc['qc_no']) ?>
            </h5>
            <div class="text-muted small">Program: <?= htmlspecialchars(($qc['program_no'] ?? 'PRG') . ' — ' . ($qc['program_name'] ?? 'Production Program')) ?> &bull; Inspector: <?= htmlspecialchars($qc['inspector_name'] ?? 'System Administrator') ?></div>
        </div>
        <div>
            <a href="<?= \App\Core\Controller::url('/production/qc') ?>" class="btn btn-outline-secondary btn-sm px-3 fw-semibold">
                <i class="bi bi-x-circle me-1"></i> Exit Inspection
            </a>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 2D QR GARMENT SCANNER CARD (Matches Image 4) -->
    <!-- ========================================== -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="border: 1px solid #bfdbfe !important;">
        <div class="card-header text-white py-3 px-4 d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #2563eb, #1d4ed8);">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-qr-code fs-5"></i>
                <h6 class="mb-0 fw-bold">2D QR Garment Scanner</h6>
            </div>
            <span class="badge bg-white text-primary fw-bold py-1 px-2 font-monospace">
                <i class="bi bi-broadcast me-1"></i> Scanner Ready
            </span>
        </div>
        <div class="card-body p-4 bg-light bg-opacity-50">
            <div class="row g-2 align-items-center mb-2">
                <div class="col-md-9 col-sm-8">
                    <div class="input-group input-group-lg shadow-sm">
                        <span class="input-group-text bg-white border-end-0 text-primary">
                            <i class="bi bi-upc-scan fs-4"></i>
                        </span>
                        <input type="text" id="inputQrScanner" class="form-control border-start-0 font-monospace fw-bold fs-5" 
                               placeholder="Scan QR sticker tag (e.g. <?= htmlspecialchars($pieces[0]['tag_code'] ?? 'TTT112601') ?>)..." 
                               autofocus autocomplete="off">
                    </div>
                </div>
                <div class="col-md-3 col-sm-4">
                    <button type="button" class="btn btn-primary btn-lg w-100 fw-semibold shadow-sm d-flex align-items-center justify-content-center gap-2" onclick="onScanLocate()">
                        <i class="bi bi-search"></i> Scan & Locate
                    </button>
                </div>
            </div>
            <div class="small text-muted d-flex align-items-center gap-1">
                <i class="bi bi-info-circle text-primary"></i>
                <span>Scan product tag code e.g. <strong class="text-danger font-monospace"><?= htmlspecialchars($pieces[0]['tag_code'] ?? 'GAR112601') ?></strong> to locate and highlight the garment piece.</span>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- PIECE TABLE & FILTER TABS (Matches Image 4) -->
    <!-- ========================================== -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-grid-3x3 text-primary"></i>
                <h6 class="mb-0 fw-bold text-dark">Dynamically Fetched Products &amp; Inspection</h6>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace" style="font-size:0.75rem;">
                    <?= count(array_unique(array_filter(array_column($pieces, 'catalog_name')))) ?: 1 ?> Catalog(s)
                </span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-success fw-bold px-3 shadow-sm" onclick="passAllPieces()">
                    <i class="bi bi-check2-all me-1"></i> Pass All
                </button>
            </div>
        </div>

        <!-- Filter Tabs (Matches Image 4) -->
        <div class="px-3 pt-3 pb-2 border-bottom bg-light bg-opacity-25">
            <ul class="nav nav-pills gap-1" id="filterTabs">
                <li class="nav-item">
                    <button class="nav-link active px-3 py-1.5 fw-semibold small" onclick="filterPieces('all', this)" id="tabAll">
                        All (<span id="cntAll"><?= count($pieces) ?></span>)
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link px-3 py-1.5 fw-semibold small text-success" onclick="filterPieces('PASSED', this)" id="tabPassed">
                        <i class="bi bi-check-circle me-1"></i> Passed (<span id="cntPassed"><?= count(array_filter($pieces, fn($p) => $p['verdict'] === 'PASSED')) ?></span>)
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link px-3 py-1.5 fw-semibold small text-secondary" onclick="filterPieces('PENDING', this)" id="tabPending">
                        <i class="bi bi-clock-history me-1"></i> Pending (<span id="cntPending"><?= count(array_filter($pieces, fn($p) => $p['verdict'] === 'PENDING')) ?></span>)
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link px-3 py-1.5 fw-semibold small text-danger" onclick="filterPieces('REJECTED', this)" id="tabRejected">
                        <i class="bi bi-x-circle me-1"></i> Rejected (<span id="cntRejected"><?= count(array_filter($pieces, fn($p) => $p['verdict'] === 'REJECTED')) ?></span>)
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link px-3 py-1.5 fw-semibold small text-warning" onclick="filterPieces('REWORK', this)" id="tabRework">
                        <i class="bi bi-arrow-repeat me-1"></i> Rework (<span id="cntRework"><?= count(array_filter($pieces, fn($p) => $p['verdict'] === 'REWORK')) ?></span>)
                    </button>
                </li>
            </ul>
        </div>

        <!-- Pieces Table -->
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 540px; overflow-y: auto;">
                <table class="table table-hover align-middle mb-0" id="tablePieces">
                    <thead class="table-light text-uppercase small text-secondary sticky-top" style="font-size:0.75rem; z-index: 5;">
                        <tr>
                            <th class="ps-4 py-2.5">Garment Tag Code &amp; Variant</th>
                            <th class="text-center py-2.5" style="width: 140px;">QC Verdict</th>
                            <th class="py-2.5" style="width: 320px;">Defect / Rework Reason &amp; Notes</th>
                            <th class="text-center pe-4 py-2.5" style="width: 240px;">Piece QC Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyPieces">
                        <?php foreach ($pieces as $idx => $p): ?>
                            <tr id="row_piece_<?= $p['id'] ?>" data-tag="<?= htmlspecialchars($p['tag_code']) ?>" data-verdict="<?= htmlspecialchars($p['verdict']) ?>">
                                <td class="ps-4 py-2.5">
                                    <div class="d-flex flex-column gap-1">
                                        <span class="badge bg-light text-primary border align-self-start font-monospace text-truncate" style="max-width: 300px; font-size:0.75rem;">
                                            <i class="bi bi-tag text-primary me-1"></i> <?= htmlspecialchars($p['catalog_name'] ?: 'Garment Product') ?>
                                        </span>
                                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                            <span class="badge bg-dark font-monospace px-2 py-0.5" style="font-size:0.8rem;">
                                                <?= htmlspecialchars($p['tag_code']) ?>
                                            </span>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-1.5 py-0.5" style="font-size:0.72rem;">
                                                <?= htmlspecialchars($p['size'] ?: 'Free') ?>
                                            </span>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-1.5 py-0.5" style="font-size:0.72rem;">
                                                <?= htmlspecialchars($p['color'] ?: 'STD') ?>
                                            </span>
                                            <span class="text-muted small" style="font-size:0.75rem;">
                                                <?= htmlspecialchars($p['size'] . '-' . $p['color']) ?> &bull; Garment #<?= $p['piece_number'] ?> of <?= $p['total_variant_pieces'] ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center py-2.5">
                                    <?php
                                    $vClass = 'bg-secondary';
                                    if ($p['verdict'] === 'PASSED') $vClass = 'bg-success';
                                    elseif ($p['verdict'] === 'REJECTED') $vClass = 'bg-danger';
                                    elseif ($p['verdict'] === 'REWORK') $vClass = 'bg-warning text-dark';
                                    ?>
                                    <span class="badge <?= $vClass ?> px-3 py-1.5 font-monospace fw-bold verdict-badge" id="badge_v_<?= $p['id'] ?>">
                                        <?= htmlspecialchars($p['verdict']) ?>
                                    </span>
                                </td>
                                <td class="py-2.5">
                                    <input type="text" class="form-control form-control-sm input-defect-note" 
                                           id="note_<?= $p['id'] ?>"
                                           value="<?= htmlspecialchars($p['defect_reason'] ?? '') ?>" 
                                           placeholder="Specific defect / rework notes...">
                                </td>
                                <td class="text-center pe-4 py-2.5">
                                    <div class="btn-group btn-group-sm shadow-sm" role="group">
                                        <button type="button" class="btn btn-outline-success fw-semibold px-2.5 py-1" onclick="setPieceVerdict(<?= $p['id'] ?>, 'PASSED')" title="Pass Garment">
                                            <i class="bi bi-check-lg"></i> Pass
                                        </button>
                                        <button type="button" class="btn btn-outline-danger fw-semibold px-2.5 py-1" onclick="setPieceVerdict(<?= $p['id'] ?>, 'REJECTED')" title="Reject / Damage">
                                            <i class="bi bi-x-lg"></i> Reject
                                        </button>
                                        <button type="button" class="btn btn-outline-warning fw-semibold px-2.5 py-1 text-dark" onclick="setPieceVerdict(<?= $p['id'] ?>, 'REWORK')" title="Send to Rework">
                                            <i class="bi bi-arrow-repeat"></i> Rework
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bottom Action Bar -->
        <div class="card-footer bg-white border-top py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <a href="<?= \App\Core\Controller::url('/production/qc') ?>" class="btn btn-outline-secondary px-4 fw-semibold">
                Cancel
            </a>

            <form action="<?= \App\Core\Controller::url('/production/qc/complete-inspection') ?>" method="POST" id="formCompleteInspection" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="qc_id" value="<?= (int)$qc['id'] ?>">
                <button type="submit" class="btn btn-success px-4 py-2 fw-semibold shadow-sm fs-6">
                    <i class="bi bi-box-arrow-in-down me-1"></i> Complete QC &amp; Inward Passed Stock
                </button>
            </form>
        </div>
    </div>
</div>

<!-- JAVASCRIPT LOGIC -->
<script>
let currentFilter = 'all';

function filterPieces(verdict, btnElem) {
    currentFilter = verdict;
    document.querySelectorAll('#filterTabs .nav-link').forEach(el => el.classList.remove('active'));
    if (btnElem) btnElem.classList.add('active');

    const rows = document.querySelectorAll('#tbodyPieces tr');
    rows.forEach(tr => {
        const v = tr.dataset.verdict;
        if (verdict === 'all' || v === verdict) {
            tr.style.display = '';
        } else {
            tr.style.display = 'none';
        }
    });
}

function onScanLocate() {
    const input = document.getElementById('inputQrScanner');
    const val = input.value.trim().toUpperCase();
    if (!val) return;

    let matchedRow = null;
    document.querySelectorAll('#tbodyPieces tr').forEach(tr => {
        const tag = (tr.dataset.tag || '').toUpperCase();
        if (tag === val || tag.includes(val)) {
            matchedRow = tr;
        }
    });

    if (matchedRow) {
        matchedRow.style.display = '';
        matchedRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
        matchedRow.classList.add('table-warning');
        setTimeout(() => matchedRow.classList.remove('table-warning'), 2500);

        // Focus defect note or automatically ask to pass
        const noteInput = matchedRow.querySelector('.input-defect-note');
        if (noteInput) noteInput.focus();
        input.value = '';
    } else {
        alert('QR Sticker Tag "' + val + '" not found in this QC inspection batch.');
        input.select();
    }
}

// Scanner enter key
document.getElementById('inputQrScanner').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        onScanLocate();
    }
});

function setPieceVerdict(pieceId, verdict) {
    const note = document.getElementById('note_' + pieceId)?.value || '';

    const formData = new FormData();
    formData.append('piece_id', pieceId);
    formData.append('verdict', verdict);
    formData.append('defect_reason', note);

    fetch('<?= \App\Core\Controller::url('/production/qc/update-piece') ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const tr = document.getElementById('row_piece_' + pieceId);
            tr.dataset.verdict = verdict;

            const badge = document.getElementById('badge_v_' + pieceId);
            badge.textContent = verdict;
            badge.className = 'badge px-3 py-1.5 font-monospace fw-bold verdict-badge ' +
                (verdict === 'PASSED' ? 'bg-success' : (verdict === 'REJECTED' ? 'bg-danger' : (verdict === 'REWORK' ? 'bg-warning text-dark' : 'bg-secondary')));

            if (data.counts) {
                updateBadgeCounts(data.counts);
            }

            // If a filter is currently active, hide row if not matched
            if (currentFilter !== 'all' && currentFilter !== verdict) {
                tr.style.display = 'none';
            }
        } else {
            alert(data.message || 'Error updating piece verdict.');
        }
    })
    .catch(err => console.error(err));
}

function passAllPieces() {
    if (!confirm('Mark all pending pieces as PASSED?')) return;

    const formData = new FormData();
    formData.append('qc_id', '<?= (int)$qc['id'] ?>');

    fetch('<?= \App\Core\Controller::url('/production/qc/pass-all') ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.querySelectorAll('#tbodyPieces tr').forEach(tr => {
                if (tr.dataset.verdict === 'PENDING') {
                    tr.dataset.verdict = 'PASSED';
                    const badge = tr.querySelector('.verdict-badge');
                    if (badge) {
                        badge.textContent = 'PASSED';
                        badge.className = 'badge bg-success px-3 py-1.5 font-monospace fw-bold verdict-badge';
                    }
                }
            });
            if (data.counts) updateBadgeCounts(data.counts);
        } else {
            alert(data.message || 'Error updating pieces.');
        }
    })
    .catch(err => console.error(err));
}

function updateBadgeCounts(counts) {
    if (!counts) return;
    document.getElementById('cntAll').textContent = counts.total || 0;
    document.getElementById('cntPassed').textContent = counts.passed || 0;
    document.getElementById('cntPending').textContent = counts.pending || 0;
    document.getElementById('cntRejected').textContent = counts.rejected || 0;
    document.getElementById('cntRework').textContent = counts.rework || 0;
}
</script>
