<div class="container-fluid px-0">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Staff Register & Directory</h4>
            <p class="text-secondary small mb-0">
                Manage employees, workforce rosters, and user access permissions isolated by business concern.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalSelectConcern">
                <i class="bi bi-building me-1"></i>
                <?= $activeConcern ? 'Concern: ' . htmlspecialchars($activeConcern['name']) : 'Choose Concern' ?>
                <i class="bi bi-chevron-down ms-1 small"></i>
            </button>
        </div>
    </div>

    <!-- Active Concern Status Banner -->
    <?php if ($activeConcern): ?>
        <div class="alert alert-light border shadow-xs rounded-3 d-flex flex-wrap justify-content-between align-items-center py-2 px-3 mb-4" style="background: #f8fafc;">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2.5 py-1.5 font-monospace">
                    <?= htmlspecialchars($activeConcern['code']) ?>
                </span>
                <span class="fw-bold text-dark"><?= htmlspecialchars($activeConcern['name']) ?></span>
                <span class="text-muted small">&bull; Showing <?= count($staffMembers) ?> Staff Member(s)</span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Export Toolbar Card -->
    <?php
    $exportTitle = 'Export Staff Directory & Workforce Register (Excel, PDF, CSV)';
    $exportSubtitle = '';
    $exportUrl = \App\Core\Controller::url('/m/staff/export');
    $currentModule = 'staff';
    $prefix = 'stf_exp';
    include dirname(__DIR__) . '/partials/export_card.php';
    ?>

    <!-- Search & Filter Directory Toolbar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= \App\Core\Controller::url('/m/staff') ?>" class="row g-2 align-items-center">
                <?php if ($selectedCid): ?>
                    <input type="hidden" name="concern_id" value="<?= $selectedCid ?>">
                <?php endif; ?>
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search name, code, phone, email..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="role_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- All Roles --</option>
                        <?php foreach ($allRoles as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= (($filters['role_id'] ?? null) == $r['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- All Statuses --</option>
                        <option value="active" <?= (($filters['status'] ?? '') === 'active') ? 'selected' : '' ?>>Active Only</option>
                        <option value="inactive" <?= (($filters['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive Only</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary px-3">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <?php if (!empty($filters['search']) || !empty($filters['role_id']) || !empty($filters['status'])): ?>
                        <a href="<?= \App\Core\Controller::url('/m/staff' . ($selectedCid ? '?concern_id=' . $selectedCid : '')) ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i> Reset
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Staff Roster Table Card -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark">
                <i class="bi bi-people-fill me-2 text-primary"></i> Staff Members
                <?php if ($activeConcern): ?>
                    <span class="text-muted fw-normal small">for <?= htmlspecialchars($activeConcern['name']) ?></span>
                <?php endif; ?>
            </h6>
            <span class="badge bg-light text-dark border"><?= count($staffMembers) ?> Persons</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead>
                    <tr>
                        <th class="ps-3">Staff Code</th>
                        <th>Name</th>
                        <th>Designation</th>
                        <th>Phone / Mobile</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staffMembers)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-people display-6 d-block mb-2 text-secondary opacity-50"></i>
                                <?= $activeConcern ? 'No staff members recorded for this concern yet.' : 'Please select a concern above to load staff records.' ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($staffMembers as $stf): ?>
                            <tr>
                                <td class="ps-3">
                                    <span class="badge bg-dark font-monospace"><?= htmlspecialchars($stf['staff_code']) ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($stf['name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($stf['email'] ?: 'No email') ?></small>
                                </td>
                                <td class="fw-semibold text-dark">
                                    <?= htmlspecialchars($stf['designation'] ?: '-') ?>
                                </td>
                                <td>
                                    <span class="font-monospace text-dark"><?= htmlspecialchars($stf['phone'] ?: '-') ?></span>
                                </td>
                                <td>
                                    <?php if ($stf['status'] === 'active'): ?>
                                        <span class="badge rounded-pill bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                            <i class="bi bi-check-circle me-1"></i> Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">
                                            <i class="bi bi-dash-circle me-1"></i> Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <?php if (\App\Core\Auth::can('staff', 'edit')): ?>
                                        <!-- Quick Status Toggle (Activate/Deactivate) -->
                                        <form action="<?= \App\Core\Controller::url('/m/staff/toggle-status') ?>" method="POST" class="d-inline" onsubmit="return confirm('<?= $stf['status'] === 'active' ? 'Deactivate' : 'Activate' ?> staff member <?= htmlspecialchars($stf['name']) ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= $stf['id'] ?>">
                                            <?php if ($stf['status'] === 'active'): ?>
                                                <button type="submit" class="btn btn-sm btn-outline-warning" title="Deactivate Staff Account">
                                                    <i class="bi bi-person-slash"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Activate Staff Account">
                                                    <i class="bi bi-person-check"></i>
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                        <?php endif; ?>

                                        <!-- View Profile -->
                                        <button type="button" class="btn btn-sm btn-outline-info" onclick='openViewStaffModal(<?= json_encode($stf, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Staff Profile">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <?php if (\App\Core\Auth::can('staff', 'edit')): ?>
                                        <!-- Edit Staff -->
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick='openEditStaffModal(<?= json_encode($stf, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Staff Details">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php endif; ?>

                                        <?php if (\App\Core\Auth::can('staff', 'delete')): ?>
                                        <!-- Delete Staff -->
                                        <form action="<?= \App\Core\Controller::url('/m/staff/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete staff member <?= htmlspecialchars($stf['name']) ?> (<?= htmlspecialchars($stf['staff_code']) ?>)? Note: Deactivation is usually preferred over permanent deletion.');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= $stf['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Staff">
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

<!-- ========================================== -->
<!-- 1. POPUP CONCERN SELECTION MODAL -->
<!-- ========================================== -->
<div class="modal fade" id="modalSelectConcern" tabindex="-1" aria-labelledby="modalSelectConcernLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom px-4 py-3 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span style="width: 38px; height: 38px; background: #eff6ff; color: #2563eb; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="bi bi-building"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0">Select Concern for Staff Register</h5>
                        <small class="text-secondary">Choose a concern to display and manage its staff members.</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="small text-muted mb-3">
                    <i class="bi bi-info-circle me-1 text-primary"></i> Each concern maintains its own separate staff directory. Select which concern's personnel you wish to view or update:
                </p>

                <div class="row g-3">
                    <?php foreach ($allConcerns as $c): ?>
                        <?php $isSelected = ($selectedCid == $c['id']); ?>
                        <div class="col-md-6">
                            <div class="card h-100 border p-3 rounded-3 shadow-xs position-relative <?= $isSelected ? 'border-primary bg-primary bg-opacity-10' : 'hover-shadow' ?>" 
                                 style="cursor: pointer; transition: all 0.2s;"
                                 onclick="window.location.href = '<?= \App\Core\Controller::url('/m/staff?concern_id=' . $c['id']) ?>'">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge <?= $isSelected ? 'bg-primary' : 'bg-dark' ?> font-monospace">
                                        <?= htmlspecialchars($c['code']) ?>
                                    </span>
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi bi-people me-1"></i> <?= (int)($c['staff_count'] ?? 0) ?> Staff
                                    </span>
                                </div>
                                <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($c['name']) ?></h6>
                                <small class="text-muted d-block mb-3">Status: <span class="text-success fw-semibold">Active Unit</span></small>
                                
                                <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                                    <span class="small fw-semibold <?= $isSelected ? 'text-primary' : 'text-secondary' ?>">
                                        <?= $isSelected ? '<i class="bi bi-check-circle-fill me-1"></i> Currently Viewing' : 'View Staff Roster &rarr;' ?>
                                    </span>
                                    <span class="btn btn-sm <?= $isSelected ? 'btn-primary' : 'btn-outline-primary' ?> px-2.5 py-1">
                                        Select
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer border-top px-4 py-2 bg-light">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- 2. ADD STAFF MODAL (REGISTER FORM) -->
<!-- ========================================== -->
<div class="modal fade" id="modalAddStaff" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form action="<?= \App\Core\Controller::url('/m/staff/store') ?>" method="POST" class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="modal-header border-bottom px-4 py-3 bg-primary text-white">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-person-plus-fill fs-5"></i>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0">Register New Staff Member</h5>
                        <small class="text-white-50">Create staff profile & system login access</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- 1. WHICH CONCERN -->
                <div class="mb-3 p-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                    <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                        <i class="bi bi-building me-1 text-primary"></i> Primary Home Concern *
                    </label>
                    <select name="concern_id" id="add_staff_concern_id" class="form-select mb-2" required onchange="onModalAddConcernChanged(this.value)">
                        <option value="">-- Choose Primary Concern --</option>
                        <?php foreach ($allConcerns as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($selectedCid == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="pt-2 border-top">
                        <div class="d-flex justify-content-between align-items-center mb-1.5">
                            <label class="small fw-bold text-dark mb-0" style="font-size: 0.76rem;">
                                <i class="bi bi-buildings-fill text-primary me-1"></i> Authorized Concerns Access Option
                            </label>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary btn-xs py-0.5 px-1.5" style="font-size: 0.68rem;" onclick="selectAllModalConcerns(true)">Select All</button>
                                <button type="button" class="btn btn-outline-secondary btn-xs py-0.5 px-1.5" style="font-size: 0.68rem;" onclick="selectAllModalConcerns(false)">Clear</button>
                            </div>
                        </div>
                        <div class="row g-1.5" id="modal_add_concern_grid">
                            <?php foreach ($allConcerns as $c): ?>
                                <?php $isPrimary = ($selectedCid == $c['id']); ?>
                                <div class="col-md-6">
                                    <div class="border rounded-2 p-1.5 bg-white d-flex align-items-center gap-2">
                                        <input class="form-check-input modal-concern-check m-0" type="checkbox" name="concern_ids[]" value="<?= (int)$c['id'] ?>" id="m_add_cid_<?= $c['id'] ?>" <?= $isPrimary ? 'checked' : '' ?> style="cursor: pointer;">
                                        <label class="form-check-label flex-grow-1 cursor-pointer mb-0 small text-truncate" for="m_add_cid_<?= $c['id'] ?>" style="cursor: pointer; font-size: 0.75rem;">
                                            <?= htmlspecialchars($c['name']) ?>
                                            <span class="badge bg-light text-dark border font-monospace ms-1" style="font-size: 0.65rem;"><?= htmlspecialchars($c['code']) ?></span>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- 2. FULL NAME -->
                <div class="row g-3 mb-3">
                    <div class="col-12">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                            <i class="bi bi-person me-1 text-primary"></i> Full Name *
                        </label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Ramesh Kumar">
                    </div>
                </div>

                <!-- 3. EMAIL & PASSWORD -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                            <i class="bi bi-envelope me-1 text-primary"></i> Email Address *
                        </label>
                        <input type="email" name="email" class="form-control" required placeholder="e.g. ramesh@example.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                            <i class="bi bi-lock me-1 text-primary"></i> Password *
                        </label>
                        <div class="input-group">
                            <input type="password" name="password" id="add_staff_password" class="form-control border-end-0" required placeholder="Enter login password">
                            <button class="btn btn-outline-secondary border-start-0" type="button" onclick="togglePasswordVisibility('add_staff_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 4. PHONE NUMBER & ACTIVE / INACTIVE STATUS -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                            <i class="bi bi-telephone me-1 text-primary"></i> Phone Number *
                        </label>
                        <input type="text" name="phone" class="form-control font-monospace" required placeholder="e.g. 9842100000">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                            <i class="bi bi-toggle-on me-1 text-primary"></i> Active / Inactive Status *
                        </label>
                        <select name="status" class="form-select" required>
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top px-4 py-3 bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold">
                    <i class="bi bi-check-circle me-1"></i> Save Staff Member
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$staffReturnTo = '';
$staffReturnConcernId = '';
include __DIR__ . '/_staff_crud_modals.php';
?>

<!-- Scripts -->
<script>
window.concernModules = <?= json_encode($concernModules ?? []) ?>;
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($showConcernModal): ?>
        const modalEl = document.getElementById('modalSelectConcern');
        if (modalEl) {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    <?php elseif (!empty($openAddModal)): ?>
        const addModalEl = document.getElementById('modalAddStaff');
        if (addModalEl) {
            const modal = bootstrap.Modal.getOrCreateInstance(addModalEl);
            modal.show();
        }
    <?php endif; ?>
});

function onModalAddConcernChanged(cid) {
    if (cid) {
        const cb = document.getElementById('m_add_cid_' + cid);
        if (cb) cb.checked = true;
    }
}

function selectAllModalConcerns(check) {
    document.querySelectorAll('.modal-concern-check').forEach(cb => cb.checked = check);
    const primaryId = document.getElementById('add_staff_concern_id')?.value;
    if (primaryId && !check) {
        const cb = document.getElementById('m_add_cid_' + primaryId);
        if (cb) cb.checked = true;
    }
}
</script>
