<?php
// app/views/concern/coupons.php - POS Coupons Management
$csrfVal = htmlspecialchars($csrfToken ?? \App\Core\Auth::csrfToken());
$canAdd = \App\Core\Auth::isSuperAdmin() || \App\Core\Auth::can('coupons', 'add');
$canEdit = \App\Core\Auth::isSuperAdmin() || \App\Core\Auth::can('coupons', 'edit');
$canDelete = \App\Core\Auth::isSuperAdmin() || \App\Core\Auth::can('coupons', 'delete');
?>
<div class="container-fluid px-0">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/') ?>" class="text-decoration-none text-muted">Home</a></li>
                    <li class="breadcrumb-item"><span class="text-muted">Shop</span></li>
                    <li class="breadcrumb-item active text-dark fw-medium" aria-current="page">Coupons</li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-ticket-perforated text-primary"></i> POS Coupons &amp; Offers
            </h4>
        </div>
        <div class="d-flex gap-2">
            <?php if ($canAdd): ?>
            <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCreateCoupon">
                <i class="bi bi-plus-circle me-1"></i> Create Coupon
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white" style="border-left: 4px solid #3b82f6 !important;">
                <div class="small text-uppercase text-muted fw-semibold">Total Coupons</div>
                <div class="fs-3 fw-bold text-dark mt-1"><?= (int) ($summary['total_count'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white" style="border-left: 4px solid #10b981 !important;">
                <div class="small text-uppercase text-muted fw-semibold">Active</div>
                <div class="fs-3 fw-bold text-success mt-1"><?= (int) ($summary['active_count'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white" style="border-left: 4px solid #8b5cf6 !important;">
                <div class="small text-uppercase text-muted fw-semibold">Redeemed</div>
                <div class="fs-3 fw-bold text-primary mt-1"><?= (int) ($summary['redeemed_count'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white" style="border-left: 4px solid #64748b !important;">
                <div class="small text-uppercase text-muted fw-semibold">Inactive / Closed</div>
                <div class="fs-3 fw-bold text-secondary mt-1"><?= (int) ($summary['closed_count'] ?? 0) ?></div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="<?= \App\Core\Controller::url('/m/coupons') ?>" class="card border-0 shadow-sm rounded-3 mb-4 p-3 bg-white">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1 fw-semibold">Search</label>
                <input type="text" name="q" class="form-control form-control-sm" value="<?= htmlspecialchars($search) ?>" placeholder="Search code, title, customer phone or name...">
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1 fw-semibold">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <?php foreach (['ACTIVE', 'INACTIVE', 'REDEEMED', 'EXPIRED', 'CANCELLED'] as $st): ?>
                        <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary px-3"><i class="bi bi-funnel-fill me-1"></i> Filter</button>
                <a href="<?= \App\Core\Controller::url('/m/coupons') ?>" class="btn btn-sm btn-outline-secondary px-3"><i class="bi bi-arrow-clockwise"></i></a>
            </div>
        </div>
    </form>

    <!-- Coupons Table -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden bg-white">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-ul text-primary me-1"></i> Coupon Registry</h6>
            <span class="badge bg-light text-dark border font-monospace"><?= count($coupons) ?> Coupons</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light text-uppercase" style="font-size: 0.72rem;">
                    <tr>
                        <th class="ps-3">Code</th>
                        <th>Title &amp; Customer</th>
                        <?php if (empty($selectedCid)): ?><th>Concern</th><?php endif; ?>
                        <th>Discount</th>
                        <th>Min Bill</th>
                        <th>Usage</th>
                        <th>Expires</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($coupons)): ?>
                        <tr>
                            <td colspan="<?= empty($selectedCid) ? 9 : 8 ?>" class="text-center py-5 text-muted">
                                <i class="bi bi-ticket-perforated fs-1 d-block mb-2"></i>
                                No coupons found. Create your first POS coupon to get started.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($coupons as $cp):
                            $st = strtoupper((string) ($cp['status'] ?? 'ACTIVE'));
                            $badgeMap = [
                                'ACTIVE' => 'bg-success-subtle text-success border-success-subtle',
                                'INACTIVE' => 'bg-secondary-subtle text-secondary border-secondary-subtle',
                                'REDEEMED' => 'bg-primary-subtle text-primary border-primary-subtle',
                                'EXPIRED' => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                                'CANCELLED' => 'bg-danger-subtle text-danger border-danger-subtle',
                            ];
                            $badgeCls = $badgeMap[$st] ?? 'bg-light text-dark';
                        ?>
                        <tr>
                            <td class="ps-3">
                                <span class="badge bg-dark font-monospace fs-6"><?= htmlspecialchars($cp['code']) ?></span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($cp['title']) ?></div>
                                <?php if (!empty($cp['issued_invoice_no'])): ?>
                                    <div class="text-info" style="font-size: 0.72rem;">Issued on Bill #<?= htmlspecialchars($cp['issued_invoice_no']) ?></div>
                                <?php endif; ?>
                            </td>
                            <?php if (empty($selectedCid)): ?>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($cp['concern_code'] ?: ($cp['concern_name'] ?: '-')) ?></span></td>
                            <?php endif; ?>
                            <td class="fw-bold text-primary font-monospace">
                                <?= $cp['discount_type'] === 'PERCENT'
                                    ? number_format((float) $cp['discount_value'], 2) . '%'
                                    : '₹' . number_format((float) $cp['discount_value'], 2) ?>
                            </td>
                            <td class="font-monospace">₹<?= number_format((float) $cp['min_redemption_amount'], 2) ?></td>
                            <td class="font-monospace">
                                <?= (int) $cp['used_count'] ?> / <?= (int) $cp['max_uses'] === 0 ? '∞' : (int) $cp['max_uses'] ?>
                                <?php if (!empty($cp['redeemed_invoice_no'])): ?>
                                    <div class="text-muted" style="font-size: 0.7rem;">Bill #<?= htmlspecialchars($cp['redeemed_invoice_no']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="font-monospace"><?= !empty($cp['expires_at']) ? date('d M Y', strtotime($cp['expires_at'])) : 'No Expiry' ?></td>
                            <td><span class="badge border <?= $badgeCls ?>"><?= $st ?></span></td>
                            <td class="text-end pe-3">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?= \App\Core\Controller::url('/m/coupons/print?id=' . (int) $cp['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print Coupon">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <?php if ($canEdit): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary" title="Edit Coupon"
                                            onclick='openEditCouponModal(<?= json_encode($cp, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($canDelete): ?>
                                    <form action="<?= \App\Core\Controller::url('/m/coupons/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete coupon <?= htmlspecialchars(addslashes($cp['code'])) ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfVal ?>">
                                        <input type="hidden" name="id" value="<?= (int) $cp['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Coupon">
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
    </div>
</div>

<?php if ($canAdd): ?>
<div class="modal fade" id="modalCreateCoupon" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= \App\Core\Controller::url('/m/coupons/store') ?>" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= $csrfVal ?>">
            <div class="modal-header bg-dark text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-ticket-perforated me-2"></i> Create POS Coupon</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <?php if (empty($selectedCid) && !empty($allConcerns)): ?>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Target Concern *</label>
                    <select name="concern_id" class="form-select" required>
                        <option value="">-- Choose Concern --</option>
                        <?php foreach ($allConcerns as $con): ?>
                            <option value="<?= (int) $con['id'] ?>"><?= htmlspecialchars($con['name']) ?> (<?= htmlspecialchars($con['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="row g-2 mb-3">
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Coupon Code *</label>
                        <input type="text" name="code" class="form-control text-uppercase font-monospace" placeholder="e.g. TEST001" maxlength="50" required>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small fw-semibold">Offer Title *</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Festival Offer" maxlength="150" required>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Discount Type *</label>
                        <select name="discount_type" class="form-select" required>
                            <option value="FIXED">FIXED (₹)</option>
                            <option value="PERCENT">PERCENT (%)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Discount Value *</label>
                        <input type="number" step="0.01" min="0.01" name="discount_value" class="form-control font-monospace" placeholder="100.00" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Min Bill Amount (₹)</label>
                        <input type="number" step="0.01" min="0" name="min_redemption_amount" class="form-control font-monospace" value="0.00">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Max Uses (0=Unlim.)</label>
                        <input type="number" min="0" step="1" name="max_uses" class="form-control font-monospace" value="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Expiry Date</label>
                        <input type="date" name="expires_at" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="ACTIVE">ACTIVE</option>
                            <option value="INACTIVE">INACTIVE</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-3"><i class="bi bi-check-circle me-1"></i> Create Coupon</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canEdit): ?>
<div class="modal fade" id="modalEditCoupon" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= \App\Core\Controller::url('/m/coupons/update') ?>" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= $csrfVal ?>">
            <input type="hidden" name="id" id="editCouponId" value="">
            <div class="modal-header bg-dark text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2 text-warning"></i> Edit POS Coupon</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-2 mb-3">
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Coupon Code *</label>
                        <input type="text" name="code" id="editCouponCode" class="form-control text-uppercase font-monospace" maxlength="50" required>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small fw-semibold">Offer Title *</label>
                        <input type="text" name="title" id="editCouponTitle" class="form-control" maxlength="150" required>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Discount Type *</label>
                        <select name="discount_type" id="editCouponType" class="form-select" required>
                            <option value="FIXED">FIXED (₹)</option>
                            <option value="PERCENT">PERCENT (%)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Discount Value *</label>
                        <input type="number" step="0.01" min="0.01" name="discount_value" id="editCouponValue" class="form-control font-monospace" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Min Bill Amount (₹)</label>
                        <input type="number" step="0.01" min="0" name="min_redemption_amount" id="editCouponMin" class="form-control font-monospace">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Max Uses (0=Unlim.)</label>
                        <input type="number" min="0" step="1" name="max_uses" id="editCouponMaxUses" class="form-control font-monospace">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Expiry Date</label>
                        <input type="date" name="expires_at" id="editCouponExpires" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" id="editCouponStatus" class="form-select">
                            <option value="ACTIVE">ACTIVE</option>
                            <option value="INACTIVE">INACTIVE</option>
                            <option value="REDEEMED">REDEEMED</option>
                            <option value="EXPIRED">EXPIRED</option>
                            <option value="CANCELLED">CANCELLED</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-3"><i class="bi bi-check-circle me-1"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditCouponModal(cp) {
    if (!cp) return;
    document.getElementById('editCouponId').value = cp.id || '';
    document.getElementById('editCouponCode').value = cp.code || '';
    document.getElementById('editCouponTitle').value = cp.title || '';
    document.getElementById('editCouponType').value = cp.discount_type || 'FIXED';
    document.getElementById('editCouponValue').value = cp.discount_value || '0';
    document.getElementById('editCouponMin').value = cp.min_redemption_amount || '0';
    document.getElementById('editCouponMaxUses').value = cp.max_uses ?? 1;
    document.getElementById('editCouponExpires').value = cp.expires_at || '';
    document.getElementById('editCouponStatus').value = cp.status || 'ACTIVE';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditCoupon')).show();
}
</script>
<?php endif; ?>
