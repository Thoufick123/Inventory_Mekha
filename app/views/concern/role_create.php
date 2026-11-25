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
                <div class="row g-3 mb-3">
                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-secondary mb-1">
                            Role Name <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 8px 0 0 8px;">
                                <i class="bi bi-tag"></i>
                            </span>
                            <input type="text" name="name" id="role_input_name" class="form-control border-start-0" placeholder="e.g. Warehouse Manager or Cutting Master" value="<?= htmlspecialchars($oldInput['name'] ?? '') ?>" required oninput="generateRoleSlug(this.value)" style="border-radius: 0 8px 8px 0;">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-secondary mb-1">
                            Role Identifier (Slug) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted font-monospace" style="border-radius: 8px 0 0 8px;">#</span>
                            <input type="text" name="slug" id="role_input_slug" class="form-control border-start-0 font-monospace" placeholder="e.g. warehouse-manager" value="<?= htmlspecialchars($oldInput['slug'] ?? '') ?>" required style="border-radius: 0 8px 8px 0;">
                        </div>
                        <div class="form-text text-muted small mt-1" style="font-size: 0.73rem;">
                            Unique slug identifier used for routing & permissions.
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-secondary mb-1">
                            Status <span class="text-danger">*</span>
                        </label>
                        <select name="status" class="form-select" required style="border-radius: 8px;">
                            <option value="active" <?= ($oldInput['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($oldInput['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="mb-0">
                    <label class="form-label small fw-bold text-secondary mb-1">
                        Role Description & Scope
                    </label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Briefly describe the operational responsibilities and authority of this role..." style="border-radius: 8px;"><?= htmlspecialchars($oldInput['description'] ?? '') ?></textarea>
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
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 6px;">
                            <i class="bi bi-sliders me-1"></i> Column Tools
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.8rem;">
                            <li><h6 class="dropdown-header text-uppercase" style="font-size: 0.68rem;">Toggle All By Column</h6></li>
                            <li><button class="dropdown-item" type="button" onclick="toggleColumnAll('view', true)"><i class="bi bi-check2 text-primary me-2"></i>Enable All View</button></li>
                            <li><button class="dropdown-item" type="button" onclick="toggleColumnAll('create', true)"><i class="bi bi-check2 text-success me-2"></i>Enable All Create</button></li>
                            <li><button class="dropdown-item" type="button" onclick="toggleColumnAll('edit', true)"><i class="bi bi-check2 text-warning me-2"></i>Enable All Edit</button></li>
                            <li><button class="dropdown-item" type="button" onclick="toggleColumnAll('delete', true)"><i class="bi bi-check2 text-danger me-2"></i>Enable All Delete</button></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><button class="dropdown-item text-danger" type="button" onclick="toggleColumnAll('all', false)"><i class="bi bi-x-circle me-2"></i>Clear All Permissions</button></li>
                        </ul>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="setMatrixAll(true)" style="border-radius: 6px;">
                        <i class="bi bi-check-all me-1"></i> Select All
                    </button>
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
                </style>

                <div class="table-responsive">
                    <table class="table role-matrix-table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th style="width: 36%;">SUBMODULE</th>
                                <th style="width: 10%;" class="text-center text-primary">VIEW</th>
                                <th style="width: 10%;" class="text-center text-success">CREATE</th>
                                <th style="width: 10%;" class="text-center text-warning text-dark">EDIT</th>
                                <th style="width: 10%;" class="text-center text-danger">DELETE</th>
                                <th style="width: 12%;" class="text-center text-secondary">ADJUST</th>
                                <th style="width: 12%;" class="text-center text-secondary">ROW ALL</th>
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
                            <?php foreach ($grouped as $groupName => $grpMods): ?>
                                <!-- GROUP HEADER -->
                                <tr class="role-group-header" data-group="<?= htmlspecialchars(strtolower($groupName)) ?>">
                                    <td colspan="5" class="py-2.5 px-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-folder-fill text-primary"></i>
                                            <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($groupName) ?></span>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 font-monospace" style="font-size: 0.7rem;">
                                                <?= count($grpMods) ?> submodules
                                            </span>
                                        </div>
                                    </td>
                                    <td colspan="2" class="text-end py-2.5 px-3">
                                        <div class="form-check form-switch d-inline-flex align-items-center gap-2 mb-0">
                                            <label class="form-check-label small fw-semibold text-secondary mb-0 cursor-pointer" for="toggle_grp_<?= md5($groupName) ?>" style="font-size: 0.75rem;">Toggle Module</label>
                                            <input class="form-check-input cursor-pointer" type="checkbox" id="toggle_grp_<?= md5($groupName) ?>" onchange="toggleModuleGroup('<?= md5($groupName) ?>', this.checked)">
                                        </div>
                                    </td>
                                </tr>

                                <!-- SUBMODULE ROWS -->
                                <?php foreach ($grpMods as $m): $mid = (int)$m['id']; ?>
                                    <tr class="module-row group-child-<?= md5($groupName) ?>" data-mid="<?= $mid ?>" data-name="<?= htmlspecialchars(strtolower($m['name'])) ?>" data-code="<?= htmlspecialchars(strtolower($m['code'])) ?>">
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-2.5">
                                                <div style="width: 28px; height: 28px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 0.85rem;" class="text-secondary">
                                                    <i class="bi <?= htmlspecialchars($m['icon'] ?? 'bi-grid') ?>"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold text-dark" style="font-size: 0.84rem;"><?= htmlspecialchars($m['name']) ?></div>
                                                    <div class="text-muted" style="font-size: 0.7rem; font-family: monospace;"><?= htmlspecialchars($m['code']) ?></div>
                                                </div>
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
                                        <td class="text-center">
                                            <span class="text-muted small">&mdash;</span>
                                        </td>
                                        <td class="text-center">
                                            <input type="checkbox" class="form-check-input matrix-check matrix-check-row" id="row_all_<?= $mid ?>" onchange="toggleRowAll(<?= $mid ?>, this.checked)">
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
function generateRoleSlug(name) {
    const slugInput = document.getElementById('role_input_slug');
    if (!slugInput) return;
    if (slugInput.dataset.manualEdited === 'true') return;
    slugInput.value = (name || '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

document.addEventListener('DOMContentLoaded', () => {
    const slugInput = document.getElementById('role_input_slug');
    if (slugInput) {
        slugInput.addEventListener('input', () => {
            slugInput.dataset.manualEdited = 'true';
        });
    }
    updatePermCounter();
});

function onPermChanged(mid) {
    const rowCheck = document.getElementById('row_all_' + mid);
    if (rowCheck) {
        const cells = document.querySelectorAll('.perm-cell-' + mid);
        let allChecked = true;
        let anyChecked = false;
        cells.forEach(c => {
            if (c.checked) anyChecked = true;
            else allChecked = false;
        });
        rowCheck.checked = allChecked;
        rowCheck.indeterminate = (!allChecked && anyChecked);
    }
    updatePermCounter();
}

function toggleRowAll(mid, check) {
    document.querySelectorAll('.perm-cell-' + mid).forEach(cb => {
        cb.checked = check;
    });
    const rowCheck = document.getElementById('row_all_' + mid);
    if (rowCheck) rowCheck.indeterminate = false;
    updatePermCounter();
}

function toggleModuleGroup(grpHash, check) {
    document.querySelectorAll('.group-child-' + grpHash).forEach(row => {
        const mid = row.getAttribute('data-mid');
        if (mid) {
            toggleRowAll(mid, check);
        }
    });
}

function toggleColumnAll(type, check) {
    if (type === 'all') {
        setMatrixAll(false);
        return;
    }
    const className = type === 'create' ? 'matrix-check-create' : ('matrix-check-' + type);
    document.querySelectorAll('.' + className).forEach(cb => {
        const tr = cb.closest('tr');
        if (tr && tr.style.display !== 'none') {
            cb.checked = check;
            const mid = tr.getAttribute('data-mid');
            if (mid) onPermChanged(mid);
        }
    });
    updatePermCounter();
}

function setMatrixAll(check) {
    document.querySelectorAll('.matrix-check-view, .matrix-check-create, .matrix-check-edit, .matrix-check-delete').forEach(cb => {
        const tr = cb.closest('tr');
        if (tr && tr.style.display !== 'none') {
            cb.checked = check;
        }
    });
    document.querySelectorAll('.matrix-check-row').forEach(cb => {
        cb.checked = check;
        cb.indeterminate = false;
    });
    updatePermCounter();
}

function updatePermCounter() {
    const checked = document.querySelectorAll('.matrix-check-view:checked, .matrix-check-create:checked, .matrix-check-edit:checked, .matrix-check-delete:checked').length;
    const badge = document.getElementById('active_perm_counter');
    if (badge) {
        const total = <?= count($modules) * 4 ?>;
        badge.textContent = checked + ' / ' + total + ' Active Permissions';
    }
}

function filterRoleModules(term) {
    const q = (term || '').trim().toLowerCase();
    document.querySelectorAll('.module-row').forEach(row => {
        const name = row.getAttribute('data-name') || '';
        const code = row.getAttribute('data-code') || '';
        if (!q || name.includes(q) || code.includes(q)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>
