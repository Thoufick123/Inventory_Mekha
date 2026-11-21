<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Stock Movement Ledger</h4>
            <p class="text-secondary small mb-0">Immutable, append-only transaction ledger guaranteeing zero drift between balance and transaction history.</p>
        </div>
        <a href="<?= \App\Core\Controller::url('/m/stocks') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Live Stock
        </a>
    </div>

    <!-- Export Intelligence Toolbar -->
    <?php
    $exportTitle = 'Export Stock Movement Ledger (Excel & PDF)';
    $exportSubtitle = 'Download immutable stock movements, transaction types, reference documents & audit journals';
    $exportUrl = \App\Core\Controller::url('/m/stock_ledger/export');
    $prefix = 'ledger_exp';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-journal-text me-2 text-primary"></i> Complete Movement Audit Trail</h6>
            <span class="badge bg-light text-dark border"><?= count($ledger) ?> Ledger Entries</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead>
                    <tr>
                        <th>Ledger ID</th>
                        <?php if ($isSuperAdmin && empty($selectedCid)): ?>
                            <th>Concern</th>
                        <?php endif; ?>
                        <th>Timestamp</th>
                        <th>Variant & SKU</th>
                        <th>Location</th>
                        <th>Source Module</th>
                        <th>Quantity In (+)</th>
                        <th>Quantity Out (-)</th>
                        <th>Transaction Note</th>
                        <th>Operator</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ledger)): ?>
                        <tr><td colspan="10" class="text-center py-4 text-muted">No stock ledger entries recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($ledger as $l): ?>
                            <tr>
                                <td><span class="badge bg-light text-dark font-monospace">#<?= (int)$l['id'] ?></span></td>
                                <?php if ($isSuperAdmin && empty($selectedCid)): ?>
                                    <td>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
                                            <?= htmlspecialchars($l['concern_name'] ?? 'Concern #' . $l['concern_id']) ?>
                                        </span>
                                    </td>
                                <?php endif; ?>
                                <td class="small text-muted font-monospace"><?= date('d M Y H:i:s', strtotime($l['created_at'])) ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($l['variant_name']) ?></div>
                                    <span class="badge bg-dark font-monospace" style="font-size: 0.7rem;"><?= htmlspecialchars($l['sku_code']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($l['location']) ?></td>
                                <td>
                                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1">
                                        <?= htmlspecialchars($l['ref_module']) ?> #<?= (int)$l['ref_id'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($l['qty_in'] > 0): ?>
                                        <span class="text-success fw-bold fs-6">+<?= ((float)$l['qty_in'] == (int)$l['qty_in']) ? number_format((float)$l['qty_in'], 0) : number_format((float)$l['qty_in'], 2) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($l['qty_out'] > 0): ?>
                                        <span class="text-danger fw-bold fs-6">-<?= ((float)$l['qty_out'] == (int)$l['qty_out']) ? number_format((float)$l['qty_out'], 0) : number_format((float)$l['qty_out'], 2) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($l['note'] ?: '-') ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($l['user_name'] ?? 'System') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
