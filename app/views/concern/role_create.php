<?php
// app/views/concern/role_create.php
?>
<div class="container-fluid px-0">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/') ?>" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item text-muted">Staff</li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/m/staff/roles') ?>" class="text-decoration-none text-muted">Roles</a></li>
            <li class="breadcrumb-item active fw-semibold text-dark" aria-current="page">Create</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Add New Role</h4>
            <p class="text-secondary small mb-0">Define the role and assign View, Create, Edit, or Delete access by module.</p>
        </div>
        <div>
            <a href="<?= \App\Core\Controller::url('/m/staff/roles') ?>" class="btn btn-outline-secondary rounded-3 d-inline-flex align-items-center gap-1.5 shadow-sm fw-semibold">
                <i class="bi bi-arrow-left"></i> Back to Roles
            </a>
        </div>
    </div>

    <form action="<?= \App\Core\Controller::url('/m/staff/roles/store') ?>" method="POST" id="formCreateRole" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

        <!-- CARD 1: Role Information -->
        <div class="card border-0 shadow-sm rounded-4 bg-white mb-4" style="border: 1px solid #e2e8f0 !important;">
            <div class="card-header bg-white py-3 px-4 border-bottom">
                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-shield-check text-primary fs-5"></i> Role Information
                </h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 mb-0">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary mb-1">
                            Role Name <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 8px 0 0 8px;">
                                <i class="bi bi-tag"></i>
                            </span>
                            <input type="text" name="name" id="role_input_name" class="form-control border-start-0" placeholder="e.g. Warehouse Manager or Cutting Master" value="<?= htmlspecialchars($oldInput['name'] ?? '') ?>" required style="border-radius: 0 8px 8px 0;">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-secondary mb-1">
                            Target Concern <span class="text-danger">*</span>
                        </label>
                        <select name="concern_id" id="role_concern_id" class="form-select" required onchange="filterRoleMatrixByConcern(this.value)" style="border-radius: 8px;">
                            <?php foreach ($allConcerns as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ((int)$selectedCid === (int)$c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-muted small mt-1" style="font-size: 0.73rem;">
                            Matrix auto-scopes to this concern's provisioned modules.
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-secondary mb-1">
                            Status <span class="text-danger">*</span>
                        </label>
                        <select name="status" class="form-select" required style="border-radius: 8px;">
                            <option value="active" <?= ($oldInput['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($oldInput['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD 2: Module & Submodule Permissions Matrix -->
        <div class="card border-0 shadow-sm rounded-4 bg-white mb-4" style="border: 1px solid #e2e8f0 !important;">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-shield-lock-fill text-primary fs-5"></i>
                    <h6 class="mb-0 fw-bold text-dark">Module & Submodule Permissions Matrix</h6>
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 font-monospace ms-2" id="active_perm_counter">0 / <?= count($modules) * 4 ?> Active Permissions</span>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="input-group input-group-sm" style="max-width: 230px;">
                        <span class="input-group-text bg-white border-end-0 py-1 px-2 text-secondary" style="border-radius: 6px 0 0 6px;">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" id="search_matrix_input" class="form-control form-control-sm border-start-0 py-1 px-2" placeholder="Search modules..." oninput="filterRoleModules(this.value)" autocomplete="off">
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="setMatrixAll(true)" style="border-radius: 6px 0 0 6px;">
                            <i class="bi bi-check-all me-1"></i> Select All
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 0 6px 6px 0;" title="Select All Options">
                            <span class="visually-hidden">Toggle Dropdown</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.8rem;">
                            <li><button class="dropdown-item" type="button" onclick="setMatrixAll(true)"><i class="bi bi-check-square-fill text-primary me-2"></i>Select All (Full CRUD Access)</button></li>
                            <li><button class="dropdown-item" type="button" onclick="toggleColumnAll('view', true)"><i class="bi bi-eye-fill text-primary me-2"></i>Select All View Only</button></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><button class="dropdown-item text-secondary" type="button" onclick="toggleColumnAll('view', false)"><i class="bi bi-eye-slash me-2"></i>Clear All View</button></li>
                            <li><button class="dropdown-item text-danger" type="button" onclick="setMatrixAll(false)"><i class="bi bi-x-circle me-2"></i>Deselect All Permissions</button></li>
                        </ul>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setMatrixAll(false)" style="border-radius: 6px;">
                        <i class="bi bi-x me-1"></i> Deselect All
                    </button>
                </div>
            </div>

            <div class="card-body p-0">
                <style>
                    .role-matrix-table th {
                        font-size: 0.72rem;
                        letter-spacing: 0.05em;
                        padding: 11px 12px;
                        background: #f8fafc;
                        border-bottom: 2px solid #e2e8f0;
                        color: #64748b;
                    }
                    .role-matrix-table td {
                        padding: 9px 12px;
                        vertical-align: middle;
                        border-bottom: 1px solid #f1f5f9;
                    }
                    .role-group-header {
                        background: #f1f5f9 !important;
                        border-top: 1px solid #e2e8f0;
                        border-bottom: 1px solid #e2e8f0;
                    }
                    .matrix-check {
                        width: 19px !important;
                        height: 19px !important;
                        cursor: pointer !important;
                        border: 1.5px solid #cbd5e1 !important;
                        border-radius: 4px !important;
                        margin: 0 !important;
                    }
                    .matrix-check-view:checked { background-color: #2563eb !important; border-color: #2563eb !important; }
                    .matrix-check-create:checked { background-color: #16a34a !important; border-color: #16a34a !important; }
                    .matrix-check-edit:checked { background-color: #d97706 !important; border-color: #d97706 !important; }
                    .matrix-check-delete:checked { background-color: #dc2626 !important; border-color: #dc2626 !important; }
                    .matrix-check-row:checked { background-color: #475569 !important; border-color: #475569 !important; }
                    #header_check_view:checked { background-color: #2563eb !important; border-color: #2563eb !important; }
                    #header_check_create:checked { background-color: #16a34a !important; border-color: #16a34a !important; }
                    #header_check_edit:checked { background-color: #d97706 !important; border-color: #d97706 !important; }
                    #header_check_delete:checked { background-color: #dc2626 !important; border-color: #dc2626 !important; }
                </style>

                <div class="table-responsive">
                    <table class="table role-matrix-table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th style="width: 40%; vertical-align: middle;">SUBMODULE</th>
                                <th style="width: 15%;" class="text-center text-primary">
                                    <div class="d-flex flex-column align-items-center justify-content-center gap-1.5 py-1">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <span class="fw-bold">VIEW</span>
                                            <div class="dropdown d-inline-block">
                                                <button type="button" class="btn btn-link p-0 text-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.65rem; border: none; text-decoration: none;" title="View Column Options"></button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.78rem;">
                                                    <li><button class="dropdown-item" type="button" onclick="toggleColumnAll('view', true)"><i class="bi bi-check2-all text-primary me-2"></i>Select All View</button></li>
                                                    <li><button class="dropdown-item" type="button" onclick="toggleColumnAll('view', false)"><i class="bi bi-x text-secondary me-2"></i>Deselect All View</button></li>
                                                </ul>
                                            </div>
                                        </div>
                                        <input type="checkbox" id="header_check_view" class="form-check-input matrix-check header-check-col" onchange="toggleColumnAll('view', this.checked)" title="Select all View">
                                    </div>
                                </th>
                                <th style="width: 15%;" class="text-center text-success">
                                    <div class="d-flex flex-column align-items-center justify-content-center gap-1.5 py-1">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <span class="fw-bold">CREATE</span>
                                            <div class="dropdown d-inline-block">
                                                <button type="button" class="btn btn-link p-0 text-success dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.65rem; border: none; text-decoration: none;" title="Create Column Options"></button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.78rem;">
                                                    <li><button class="dropdown-item" type="button" onclick="toggleColumnAll('create', true)"><i class="bi bi-check2-all text-success me-2"></i>Select All Create</button></li>
                                                    <li><button class="dropdown-item" type="button" onclick="toggleColumnAll('create', false)"><i class="bi bi-x text-secondary me-2"></i>Deselect All Create</button></li>
                                                </ul>
                                            </div>
                                        </div>
                                        <input type="checkbox" id="header_check_create" class="form-check-input matrix-check header-check-col" onchange="toggleColumnAll('create', this.checked)" title="Select all Create">
                                    </div>
                                </th>
                                <th style="width: 15%;" class="text-center text-warning text-dark">
                                    <div class="d-flex flex-column align-items-center justify-content-center gap-1.5 py-1">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <span class="fw-bold">EDIT</span>
                                            <div class="dropdown d-inline-block">
                                                <button type="button" class="btn btn-link p-0 text-warning text-dark dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.65rem; border: none; text-decoration: none;" title="Edit Column Options"></button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.78rem;">
                                                    <li><button class="dropdown-item" type="button" onclick="toggleColumnAll('edit', true)"><i class="bi bi-check2-all text-warning me-2"></i>Select All Edit</button></li>
                                                    <li><button class="dropdown-item" type="button" onclick="toggleColumnAll('edit', false)"><i class="bi bi-x text-secondary me-2"></i>Deselect All Edit</button></li>
                                                </ul>
                                            </div>
                                        </div>
                                        <input type="checkbox" id="header_check_edit" class="form-check-input matrix-check header-check-col" onchange="toggleColumnAll('edit', this.checked)" title="Select all Edit">
                                    </div>
                                </th>
                                <th style="width: 15%;" class="text-center text-danger">
                                    <div class="d-flex flex-column align-items-center justify-content-center gap-1.5 py-1">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <span class="fw-bold">DELETE</span>
                                            <div class="dropdown d-inline-block">
                                                <button type="button" class="btn btn-link p-0 text-danger dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.65rem; border: none; text-decoration: none;" title="Delete Column Options"></button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.78rem;">
                                                    <li><button class="dropdown-item" type="button" onclick="toggleColumnAll('delete', true)"><i class="bi bi-check2-all text-danger me-2"></i>Select All Delete</button></li>
                                                    <li><button class="dropdown-item" type="button" onclick="toggleColumnAll('delete', false)"><i class="bi bi-x text-secondary me-2"></i>Deselect All Delete</button></li>
                                                </ul>
                                            </div>
                                        </div>
                                        <input type="checkbox" id="header_check_delete" class="form-check-input matrix-check header-check-col" onchange="toggleColumnAll('delete', this.checked)" title="Select all Delete">
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $grouped = [];
                            foreach ($modules as $m) {
                                $grp = $m['group_name'] ?: 'General';
                                $grouped[$grp][] = $m;
                            }
                            ?>
                            <?php foreach ($grouped as $groupName => $grpMods): $grpHash = md5($groupName); ?>
                                <!-- GROUP HEADER -->
                                <tr class="role-group-header" data-group="<?= htmlspecialchars(strtolower($groupName)) ?>" data-group-hash="<?= $grpHash ?>">
                                    <td colspan="5" class="py-2.5 px-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-folder-fill text-primary"></i>
                                            <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($groupName) ?></span>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 font-monospace" style="font-size: 0.7rem;">
                                                <?= count($grpMods) ?> submodules
                                            </span>
                                        </div>
                                    </td>
                                </tr>

                                <!-- SUBMODULE ROWS -->
                                <?php foreach ($grpMods as $m): $mid = (int)$m['id']; ?>
                                    <tr class="module-row group-child-<?= $grpHash ?>" data-mid="<?= $mid ?>" data-group="<?= htmlspecialchars(strtolower($groupName)) ?>" data-group-hash="<?= $grpHash ?>" data-name="<?= htmlspecialchars(strtolower($m['name'])) ?>" data-code="<?= htmlspecialchars(strtolower($m['code'])) ?>">
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-2.5">
                                                <div style="width: 28px; height: 28px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 0.85rem;" class="text-secondary">
                                                    <i class="bi <?= htmlspecialchars($m['icon'] ?? 'bi-grid') ?>"></i>
                                                </div>
                                                <span class="fw-semibold text-dark" style="font-size: 0.84rem;"><?= htmlspecialchars($m['name']) ?></span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <input type="checkbox" name="perms[<?= $mid ?>][view]" value="1" class="form-check-input matrix-check matrix-check-view perm-cell-<?= $mid ?>" onchange="onPermChanged(<?= $mid ?>)">
                                        </td>
                                        <td class="text-center">
                                            <input type="checkbox" name="perms[<?= $mid ?>][add]" value="1" class="form-check-input matrix-check matrix-check-create perm-cell-<?= $mid ?>" onchange="onPermChanged(<?= $mid ?>)">
                                        </td>
                                        <td class="text-center">
                                            <input type="checkbox" name="perms[<?= $mid ?>][edit]" value="1" class="form-check-input matrix-check matrix-check-edit perm-cell-<?= $mid ?>" onchange="onPermChanged(<?= $mid ?>)">
                                        </td>
                                        <td class="text-center">
                                            <input type="checkbox" name="perms[<?= $mid ?>][delete]" value="1" class="form-check-input matrix-check matrix-check-delete perm-cell-<?= $mid ?>" onchange="onPermChanged(<?= $mid ?>)">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer bg-white border-top p-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <a href="<?= \App\Core\Controller::url('/m/staff/roles') ?>" class="btn btn-outline-secondary px-4 rounded-3 fw-semibold">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary px-4 py-2 rounded-3 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm">
                    <i class="bi bi-check2-circle fs-6"></i> Save & Provision Role
                </button>
            </div>
        </div>
    </form>
</div>

<script>
window.concernModules = <?= json_encode($concernModules ?? []) ?>;

function filterRoleMatrixByConcern(cid) {
    const concernId = parseInt(cid) || 0;
    const allowed = (window.concernModules && window.concernModules[concernId]) ? window.concernModules[concernId] : [];
    const searchTerm = (document.getElementById('search_matrix_input')?.value || '').trim().toLowerCase();

    const groupCount = {};
    document.querySelectorAll('.module-row').forEach(row => {
        const mid = parseInt(row.getAttribute('data-mid'));
        const grp = row.getAttribute('data-group');
        const isAllowed = (!concernId || allowed.includes(mid));
        row.setAttribute('data-concern-allowed', isAllowed ? '1' : '0');

        const name = row.getAttribute('data-name') || '';
        const code = row.getAttribute('data-code') || '';
        const matchesSearch = (!searchTerm || name.includes(searchTerm) || code.includes(searchTerm));

        if (isAllowed && matchesSearch) {
            row.style.display = '';
            groupCount[grp] = (groupCount[grp] || 0) + 1;
        } else {
            row.style.display = 'none';
            if (!isAllowed) {
                row.querySelectorAll('.matrix-check').forEach(cb => {
                    cb.checked = false;
                    cb.indeterminate = false;
                });
            }
        }
    });

    document.querySelectorAll('.role-group-header').forEach(header => {
        const grp = header.getAttribute('data-group');
        header.style.display = (groupCount[grp] && groupCount[grp] > 0) ? '' : 'none';
    });

    syncAllGroupToggles();
    syncHeaderCheckboxes();
    updatePermCounter();
}

document.addEventListener('DOMContentLoaded', () => {
    const concernSelect = document.getElementById('role_concern_id');
    if (concernSelect && concernSelect.value) {
        filterRoleMatrixByConcern(concernSelect.value);
    } else {
        document.querySelectorAll('.module-row').forEach(r => r.setAttribute('data-concern-allowed', '1'));
        syncAllGroupToggles();
        syncHeaderCheckboxes();
        updatePermCounter();
    }
});

function syncRowCheck(mid) {
    const rowCheck = document.getElementById('row_all_' + mid);
    if (!rowCheck) return;
    const cells = document.querySelectorAll('.perm-cell-' + mid);
    let allChecked = cells.length > 0;
    let anyChecked = false;
    cells.forEach(c => {
        if (c.checked) anyChecked = true;
        else allChecked = false;
    });
    rowCheck.checked = allChecked;
    rowCheck.indeterminate = (!allChecked && anyChecked);
}

function syncGroupToggle(grpHash) {
    const grpSwitch = document.getElementById('toggle_grp_' + grpHash);
    if (!grpSwitch) return;
    const rows = document.querySelectorAll('.group-child-' + grpHash);
    let totalCells = 0;
    let checkedCells = 0;
    rows.forEach(row => {
        if (row.getAttribute('data-concern-allowed') === '0') return;
        const cells = row.querySelectorAll('.matrix-check-view, .matrix-check-create, .matrix-check-edit, .matrix-check-delete');
        cells.forEach(cb => {
            totalCells++;
            if (cb.checked) checkedCells++;
        });
    });
    grpSwitch.checked = (totalCells > 0 && checkedCells === totalCells);
    grpSwitch.indeterminate = (checkedCells > 0 && checkedCells < totalCells);
}

function syncAllGroupToggles() {
    document.querySelectorAll('.group-toggle-switch').forEach(sw => {
        const grpHash = sw.getAttribute('data-group-hash');
        if (grpHash) syncGroupToggle(grpHash);
    });
}

function syncHeaderCheckboxes() {
    ['view', 'create', 'edit', 'delete'].forEach(type => {
        const headerCb = document.getElementById('header_check_' + type);
        if (!headerCb) return;
        const className = type === 'create' ? 'matrix-check-create' : ('matrix-check-' + type);
        let total = 0;
        let checked = 0;
        document.querySelectorAll('.module-row .' + className).forEach(cb => {
            const tr = cb.closest('tr');
            if (tr && tr.getAttribute('data-concern-allowed') !== '0' && tr.style.display !== 'none') {
                total++;
                if (cb.checked) checked++;
            }
        });
        if (total > 0 && checked === total) {
            headerCb.checked = true;
            headerCb.indeterminate = false;
        } else if (checked > 0) {
            headerCb.checked = false;
            headerCb.indeterminate = true;
        } else {
            headerCb.checked = false;
            headerCb.indeterminate = false;
        }
    });
}

function onPermChanged(mid) {
    syncRowCheck(mid);
    const row = document.getElementById('row_all_' + mid)?.closest('tr');
    const grpHash = row ? row.getAttribute('data-group-hash') : null;
    if (grpHash) syncGroupToggle(grpHash);
    syncHeaderCheckboxes();
    updatePermCounter();
}

function toggleRowAll(mid, check, skipGroupSync = false) {
    document.querySelectorAll('.perm-cell-' + mid).forEach(cb => {
        cb.checked = check;
    });
    const rowCheck = document.getElementById('row_all_' + mid);
    if (rowCheck) {
        rowCheck.checked = check;
        rowCheck.indeterminate = false;
    }
    if (!skipGroupSync) {
        const row = rowCheck ? rowCheck.closest('tr') : null;
        const grpHash = row ? row.getAttribute('data-group-hash') : null;
        if (grpHash) syncGroupToggle(grpHash);
        syncHeaderCheckboxes();
    }
    updatePermCounter();
}

function toggleModuleGroup(grpHash, check) {
    document.querySelectorAll('.group-child-' + grpHash).forEach(row => {
        if (row.getAttribute('data-concern-allowed') === '0') return;
        const mid = row.getAttribute('data-mid');
        if (mid) {
            toggleRowAll(mid, check, true);
        }
    });
    const grpSwitch = document.getElementById('toggle_grp_' + grpHash);
    if (grpSwitch) {
        grpSwitch.checked = check;
        grpSwitch.indeterminate = false;
    }
    syncHeaderCheckboxes();
    updatePermCounter();
}

function toggleColumnAll(type, check) {
    if (type === 'all') {
        setMatrixAll(false);
        return;
    }
    const className = type === 'create' ? 'matrix-check-create' : ('matrix-check-' + type);
    document.querySelectorAll('.module-row .' + className).forEach(cb => {
        const tr = cb.closest('tr');
        if (tr && tr.getAttribute('data-concern-allowed') !== '0' && tr.style.display !== 'none') {
            cb.checked = check;
            const mid = tr.getAttribute('data-mid');
            if (mid) syncRowCheck(mid);
        }
    });
    syncAllGroupToggles();
    syncHeaderCheckboxes();
    updatePermCounter();
}

function setMatrixAll(check) {
    document.querySelectorAll('.module-row').forEach(row => {
        if (row.getAttribute('data-concern-allowed') === '0' || row.style.display === 'none') return;
        const mid = row.getAttribute('data-mid');
        if (mid) toggleRowAll(mid, check, true);
    });
    syncAllGroupToggles();
    syncHeaderCheckboxes();
    updatePermCounter();
}

function updatePermCounter() {
    let visibleTotal = 0;
    let checked = 0;
    document.querySelectorAll('.module-row').forEach(row => {
        if (row.getAttribute('data-concern-allowed') !== '0' && row.style.display !== 'none') {
            visibleTotal += 4;
            checked += row.querySelectorAll('.matrix-check-view:checked, .matrix-check-create:checked, .matrix-check-edit:checked, .matrix-check-delete:checked').length;
        }
    });
    const badge = document.getElementById('active_perm_counter');
    if (badge) {
        badge.textContent = checked + ' / ' + visibleTotal + ' Active Permissions';
    }
}

function filterRoleModules(term) {
    const q = (term || '').trim().toLowerCase();
    const groupCount = {};
    document.querySelectorAll('.module-row').forEach(row => {
        if (row.getAttribute('data-concern-allowed') === '0') {
            row.style.display = 'none';
            return;
        }
        const name = row.getAttribute('data-name') || '';
        const code = row.getAttribute('data-code') || '';
        const grp = row.getAttribute('data-group');
        if (!q || name.includes(q) || code.includes(q)) {
            row.style.display = '';
            groupCount[grp] = (groupCount[grp] || 0) + 1;
        } else {
            row.style.display = 'none';
        }
    });
    document.querySelectorAll('.role-group-header').forEach(header => {
        const grp = header.getAttribute('data-group');
        header.style.display = (groupCount[grp] && groupCount[grp] > 0) ? '' : 'none';
    });
    syncHeaderCheckboxes();
    updatePermCounter();
}
</script>
