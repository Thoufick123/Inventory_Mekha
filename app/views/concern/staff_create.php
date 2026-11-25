<?php
// Group operational modules to match Images 1 & 2 hierarchy exactly
$groupOrder = ['Purchase', 'Production', 'Stock', 'Sales', 'Shop', 'Finance', 'Staff'];
$groupedModules = [];
foreach ($modules as $m) {
    if ($m['code'] === 'cash') continue; // Unified under 'Cash & Cash Flow' (cash_flow)
    if ($m['code'] === 'concern_dashboard' || $m['group_name'] === 'Dashboard') continue;
    if ($m['code'] === 'variants' || $m['group_name'] === 'Masters') continue;
    $grp = $m['group_name'] ?: 'General';
    $groupedModules[$grp][] = $m;
}
?>
<div class="container-fluid px-0">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/') ?>" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item text-muted">Staff</li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/m/staff') ?>" class="text-decoration-none text-muted">Staff Register</a></li>
            <li class="breadcrumb-item active fw-semibold text-dark" aria-current="page">Add New Staff</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <div style="width: 46px; height: 46px; background: #eff6ff; color: #2563eb; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; border: 1px solid #bfdbfe;">
                <i class="bi bi-person-plus-fill"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-0 text-dark">Create New Staff Member</h4>
                <p class="text-secondary small mb-0">Add a team member and assign View, Create, Edit, or Delete access.</p>
            </div>
        </div>
        <div>
            <a href="<?= \App\Core\Controller::url('/m/staff' . ($selectedCid ? '?concern_id=' . $selectedCid : '')) ?>" class="btn btn-outline-secondary rounded-3 d-inline-flex align-items-center gap-1.5 shadow-sm fw-semibold">
                <i class="bi bi-arrow-left"></i> Back to Staff Directory
            </a>
        </div>
    </div>

    <!-- Main Registration Form Card -->
    <div class="row justify-content-center">
        <div class="col-12 col-lg-11 col-xl-10">
            <div class="card border-0 shadow-sm rounded-4 bg-white" style="border: 1px solid #e2e8f0 !important;">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-person-fill-gear text-primary fs-5"></i> Personal Details & Credentials
                    </h6>
                </div>

                <form action="<?= \App\Core\Controller::url('/m/staff/store') ?>" method="POST" class="card-body p-4" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

                    <!-- ROW 1: Full Name & Assign System Role -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary mb-1">
                                Full Name <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 8px 0 0 8px;">
                                    <i class="bi bi-person"></i>
                                </span>
                                <input type="text" name="name" id="staff_create_name" class="form-control border-start-0" placeholder="e.g. Rahul Sharma" value="<?= htmlspecialchars($oldInput['name'] ?? '') ?>" required autocomplete="off" style="border-radius: 0 8px 8px 0;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary mb-1">
                                Assign System Role <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 8px 0 0 8px;">
                                    <i class="bi bi-shield-check"></i>
                                </span>
                                <input type="text" name="role_name" id="staff_role_input" class="form-control border-start-0 border-end-0" placeholder="Type or select role..." list="role_datalist" value="<?php
                                    $curRoleVal = '';
                                    if (!empty($oldInput['role_name'])) {
                                        $curRoleVal = $oldInput['role_name'];
                                    } elseif (!empty($oldInput['role_id'])) {
                                        foreach ($roles as $r) {
                                            if ((int)$r['id'] === (int)$oldInput['role_id']) {
                                                $curRoleVal = $r['name'];
                                                break;
                                            }
                                        }
                                    }
                                    echo htmlspecialchars($curRoleVal);
                                ?>" required autocomplete="off" oninput="onRoleInputChanged(this.value)">
                                <input type="hidden" name="role_id" id="staff_create_role_id" value="<?= htmlspecialchars($oldInput['role_id'] ?? '') ?>">
                                <button class="btn btn-outline-secondary dropdown-toggle border-start-0" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 0 8px 8px 0;">
                                    <span class="visually-hidden">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="max-height: 250px; overflow-y: auto;">
                                    <li><h6 class="dropdown-header text-uppercase small" style="font-size: 0.68rem;">Existing System Roles</h6></li>
                                    <?php foreach ($roles as $r): ?>
                                        <li>
                                            <button class="dropdown-item d-flex justify-content-between align-items-center" type="button" onclick="selectRoleCombobox('<?= (int)$r['id'] ?>', '<?= htmlspecialchars(addslashes($r['name'])) ?>')">
                                                <span><?= htmlspecialchars($r['name']) ?></span>
                                                <span class="badge bg-light text-muted border font-monospace ms-2" style="font-size: 0.68rem;">Role #<?= $r['id'] ?></span>
                                            </button>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <datalist id="role_datalist">
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?= htmlspecialchars($r['name']) ?>" data-id="<?= (int)$r['id'] ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>
                    </div>

                    <!-- ROW 2: Email Address & Password -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary mb-1">
                                Email Address <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 8px 0 0 8px;">
                                    <i class="bi bi-envelope"></i>
                                </span>
                                <input type="email" name="email" id="staff_create_email" class="form-control border-start-0" placeholder="rahul@example.com" value="<?= htmlspecialchars($oldInput['email'] ?? '') ?>" required autocomplete="off" style="border-radius: 0 8px 8px 0;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary mb-1">
                                Password <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 8px 0 0 8px;">
                                    <i class="bi bi-key"></i>
                                </span>
                                <input type="password" name="password" id="staff_create_password" class="form-control border-start-0 border-end-0" placeholder="••••••••" value="<?= htmlspecialchars($oldInput['password'] ?? '') ?>" required autocomplete="new-password">
                                <button class="btn btn-outline-secondary border-start-0" type="button" onclick="toggleCreatePassword(this)" style="border-radius: 0 8px 8px 0;">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ROW 3: Phone Number & Employment Status -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-bold text-secondary mb-0">
                                    <i class="bi bi-telephone text-primary me-1"></i> Phone Number
                                </label>
                                <span id="phone_digit_counter" class="badge bg-light text-muted border font-monospace" style="font-size: 0.72rem;">0 / 10 digits</span>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted font-semibold" style="border-radius: 8px 0 0 8px;">+91</span>
                                <input type="tel" name="phone" id="staff_create_phone" maxlength="10" class="form-control border-start-0 border-end-0 font-monospace" placeholder="10-digit mobile number" value="<?= htmlspecialchars($oldInput['phone'] ?? '') ?>" oninput="updatePhoneCounter(this)" autocomplete="off">
                                <span class="input-group-text bg-white border-start-0 text-muted" style="border-radius: 0 8px 8px 0;">#</span>
                            </div>
                            <div class="form-text text-muted small mt-1" style="font-size: 0.73rem;">
                                Only 10-digit numeric mobile numbers (starting with 6-9). Letters and duplicate numbers are rejected.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary mb-1">
                                Employment Status
                            </label>
                            <select name="status" class="form-select" style="border-radius: 8px;">
                                <option value="active" <?= ($oldInput['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active (Can login & operate)</option>
                                <option value="inactive" <?= ($oldInput['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive (Disabled)</option>
                            </select>
                        </div>
                    </div>

                    <!-- ROW 4: Select Department / Operational Concern -->
                    <div class="mb-4 p-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                        <input type="hidden" name="concern_id" id="staff_concern_id" value="">
                        <input type="hidden" name="designation" id="staff_create_designation" value="">
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                            <label class="small fw-bold text-dark text-uppercase mb-0 d-flex align-items-center gap-1.5" style="font-size: 0.78rem; letter-spacing: 0.05em;">
                                <i class="bi bi-buildings-fill text-primary"></i> Select Department / Operational Concern *
                            </label>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="text-muted small d-inline-flex align-items-center gap-1" style="font-size: 0.75rem;">
                                    <i class="bi bi-info-circle text-primary"></i> Auto-scoped based on assigned role
                                </span>
                                <div class="input-group input-group-sm" style="max-width: 200px;">
                                    <span class="input-group-text bg-white border-end-0 py-0.5 px-2 text-secondary" style="border-radius: 6px 0 0 6px;">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" id="search_concerns_input" class="form-control form-control-sm border-start-0 py-0.5 px-2" placeholder="Search concern..." oninput="filterConcernCards(this.value)" autocomplete="off">
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0.5 px-2" onclick="clearConcernSearch()" title="Clear search" style="border-radius: 0 6px 6px 0;">
                                        <i class="bi bi-x-lg" style="font-size: 0.7rem;"></i>
                                    </button>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary btn-xs py-0.5 px-2" style="font-size: 0.72rem;" onclick="selectAllCreateConcerns(true)">Select All</button>
                                    <button type="button" class="btn btn-outline-secondary btn-xs py-0.5 px-2" style="font-size: 0.72rem;" onclick="selectAllCreateConcerns(false)">Deselect All</button>
                                </div>
                            </div>
                        </div>
                        <p class="text-secondary small mb-2" style="font-size: 0.73rem;">
                            Choose which business units or retail outlets this staff member can access and operate:
                        </p>
                        <div class="row g-2 mb-1" id="create_concern_checkboxes_grid">
                            <?php foreach ($allConcerns as $c): ?>
                                <?php 
                                    $isChecked = !empty($oldInput['concern_ids']) 
                                        && in_array((int)$c['id'], array_map('intval', (array)$oldInput['concern_ids']), true);
                                ?>
                                <div class="col-md-6" data-name="<?= htmlspecialchars($c['name']) ?>" data-code="<?= htmlspecialchars($c['code']) ?>">
                                    <div class="p-2.5 border rounded-3 bg-white d-flex align-items-center gap-2.5 concern-check-card" id="box_cid_<?= $c['id'] ?>" style="transition: all 0.18s ease; cursor: pointer;" onclick="if(event.target.tagName !== 'INPUT') { const cb = document.getElementById('create_cid_<?= $c['id'] ?>'); if(cb) { cb.checked = !cb.checked; cb.dispatchEvent(new Event('change')); } }">
                                        <input class="form-check-input create-concern-check m-0" type="checkbox" name="concern_ids[]" value="<?= (int)$c['id'] ?>" id="create_cid_<?= $c['id'] ?>" <?= $isChecked ? 'checked' : '' ?> style="width: 17px; height: 17px; cursor: pointer;">
                                        <label class="form-check-label flex-grow-1 cursor-pointer mb-0 small d-flex justify-content-between align-items-center" for="create_cid_<?= $c['id'] ?>" style="cursor: pointer;">
                                            <span class="fw-bold text-dark" style="font-size: 0.85rem;"><i class="bi bi-building text-primary me-1.5"></i><?= htmlspecialchars($c['name']) ?></span>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 font-monospace" style="font-size: 0.72rem;"><?= htmlspecialchars($c['code']) ?></span>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div id="no_concerns_match_msg" class="text-muted small text-center py-2" style="display: none;">
                            <i class="bi bi-search me-1"></i> No matching concerns found.
                        </div>
                    </div>

                    <hr class="my-4 opacity-50">

<style>
/* Module CRUD Permissions Matrix - Neatly Aligned & Separated Checkboxes */
.perm-matrix-table th {
    font-size: 0.72rem;
    letter-spacing: 0.04em;
    padding: 10px 8px;
    vertical-align: middle;
}
.perm-matrix-table td {
    padding: 7px 8px;
    vertical-align: middle;
}
.perm-matrix-col-view {
    background: rgba(59, 130, 246, 0.025);
    border-left: 1px solid #f1f5f9;
    border-right: 1px solid #f1f5f9;
}
.perm-matrix-col-add {
    background: rgba(16, 185, 129, 0.025);
    border-right: 1px solid #f1f5f9;
}
.perm-matrix-col-edit {
    background: rgba(245, 158, 11, 0.025);
    border-right: 1px solid #f1f5f9;
}
.perm-matrix-col-delete {
    background: rgba(239, 68, 68, 0.025);
    border-right: 1px solid #f1f5f9;
}

/* Clean Standalone Checkboxes */
.matrix-perm-check {
    width: 20px !important;
    height: 20px !important;
    cursor: pointer !important;
    border: 1.5px solid #94a3b8 !important;
    border-radius: 5px !important;
    margin: 0 !important;
}
.matrix-perm-view:checked {
    background-color: #2563eb !important;
    border-color: #2563eb !important;
}
.matrix-perm-add:checked {
    background-color: #059669 !important;
    border-color: #059669 !important;
}
.matrix-perm-edit:checked {
    background-color: #d97706 !important;
    border-color: #d97706 !important;
}
.matrix-perm-delete:checked {
    background-color: #dc2626 !important;
    border-color: #dc2626 !important;
}

/* Row Hover */
.matrix-mod-row:hover {
    background-color: #f8fafc !important;
}

/* Concern Selection Card Hover & Active */
.concern-check-card:hover {
    border-color: #93c5fd !important;
    background-color: #f8fafc !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
}
.concern-check-card:has(input:checked) {
    border-color: #3b82f6 !important;
    background-color: #eff6ff !important;
}
</style>

                    <!-- 6. MODULE ACCESS & CRUD PERMISSIONS MATRIX -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                            <div>
                                <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                    <i class="bi bi-shield-check text-primary fs-5"></i> 6. Module Access & CRUD Permissions (View, Create, Edit, Delete)
                                </h6>
                                <small class="text-secondary">Configure specific View, Create, Edit, and Delete operational rights across selected concern modules.</small>
                            </div>
                        </div>

                        <!-- DYNAMIC CONCERN TABS (Shown separately for each selected concern) -->
                        <div class="mb-3" id="concern_matrix_tabs_wrapper">
                            <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                                <span class="small fw-bold text-secondary text-uppercase d-flex align-items-center gap-1" style="font-size: 0.72rem;">
                                    <i class="bi bi-buildings text-primary"></i> Selected Concerns (<span id="concern_selected_count_badge">1</span>) - Click a concern to configure its modules:
                                </span>
                                <span class="badge bg-white text-secondary border px-2.5 py-1" id="active_tab_module_count_badge" style="font-size: 0.74rem;">
                                    Loading modules...
                                </span>
                            </div>
                            <ul class="nav nav-pills gap-1.5 p-1.5 bg-light rounded-3 border" id="concern_matrix_pills" role="tablist">
                                <!-- Dynamically populated concern tabs -->
                            </ul>
                        </div>

                        <!-- Container for dynamic serialized permissions inputs -->
                        <div id="dynamic_perms_inputs"></div>

                        <!-- Matrix Table with Separated Checkboxes -->
                        <div class="table-responsive rounded-3 border">
                            <table class="table table-bordered table-sm align-middle mb-0 perm-matrix-table" id="createStaffMatrixTable" style="font-size: 0.82rem;">
                                <thead class="bg-light text-secondary text-uppercase" style="font-size: 0.72rem;">
                                    <tr>
                                        <th style="width: 40%; vertical-align: middle;" class="ps-3">
                                            <span class="fw-bold">Module / Feature</span>
                                        </th>
                                        <th style="width: 15%;" class="text-center text-primary bg-primary bg-opacity-10 border-start border-end py-2">
                                            <div class="d-flex flex-column align-items-center justify-content-center gap-1">
                                                <div class="fw-bold">
                                                    <i class="bi bi-eye-fill me-1"></i> View
                                                </div>
                                                <input type="checkbox" id="header_check_view" class="form-check-input m-0 header-col-check" style="width: 18px; height: 18px; cursor: pointer;" onchange="toggleHeaderColumn('view', this.checked)" title="Select all View">
                                            </div>
                                        </th>
                                        <th style="width: 15%;" class="text-center text-success bg-success bg-opacity-10 border-end py-2">
                                            <div class="d-flex flex-column align-items-center justify-content-center gap-1">
                                                <div class="fw-bold">
                                                    <i class="bi bi-plus-circle-fill me-1"></i> Create (Add)
                                                </div>
                                                <input type="checkbox" id="header_check_add" class="form-check-input m-0 header-col-check" style="width: 18px; height: 18px; cursor: pointer;" onchange="toggleHeaderColumn('add', this.checked)" title="Select all Create">
                                            </div>
                                        </th>
                                        <th style="width: 15%;" class="text-center text-warning bg-warning bg-opacity-10 text-dark border-end py-2">
                                            <div class="d-flex flex-column align-items-center justify-content-center gap-1">
                                                <div class="fw-bold">
                                                    <i class="bi bi-pencil-fill me-1"></i> Edit
                                                </div>
                                                <input type="checkbox" id="header_check_edit" class="form-check-input m-0 header-col-check" style="width: 18px; height: 18px; cursor: pointer;" onchange="toggleHeaderColumn('edit', this.checked)" title="Select all Edit">
                                            </div>
                                        </th>
                                        <th style="width: 15%;" class="text-center text-danger bg-danger bg-opacity-10 border-end py-2">
                                            <div class="d-flex flex-column align-items-center justify-content-center gap-1">
                                                <div class="fw-bold">
                                                    <i class="bi bi-trash-fill me-1"></i> Delete
                                                </div>
                                                <input type="checkbox" id="header_check_delete" class="form-check-input m-0 header-col-check" style="width: 18px; height: 18px; cursor: pointer;" onchange="toggleHeaderColumn('delete', this.checked)" title="Select all Delete">
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($groupOrder as $grpName): ?>
                                        <?php if (empty($groupedModules[$grpName])) continue; ?>
                                        <tr class="table-secondary matrix-group-header" data-group="<?= htmlspecialchars($grpName) ?>">
                                            <td colspan="5" class="fw-bold text-dark py-1.5 px-3 text-uppercase" style="font-size: 0.74rem; letter-spacing: 0.05em; background: #e2e8f0;">
                                                <i class="bi bi-folder2-open me-1.5 text-primary"></i> <?= htmlspecialchars($grpName) ?>
                                            </td>
                                        </tr>
                                        <?php foreach ($groupedModules[$grpName] as $m): $mid = (int)$m['id']; ?>
                                            <tr id="matrix_row_<?= $mid ?>" class="matrix-mod-row" data-mid="<?= $mid ?>" data-group="<?= htmlspecialchars($grpName) ?>">
                                                <td class="ps-3 py-2">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <i class="bi <?= htmlspecialchars($m['icon'] ?: 'bi-app') ?> text-primary fs-6"></i>
                                                        <span class="fw-semibold text-dark" style="font-size: 0.84rem;"><?= htmlspecialchars($m['name']) ?></span>
                                                    </div>
                                                </td>
                                                <!-- View -->
                                                <td class="text-center perm-matrix-col-view">
                                                    <div class="d-flex justify-content-center align-items-center m-0">
                                                        <input class="form-check-input matrix-perm-check matrix-perm-view matrix-row-<?= $mid ?>" 
                                                               type="checkbox" value="1" id="m_view_<?= $mid ?>"
                                                               onchange="onPermCheckboxChange(<?= $mid ?>, 'view', this.checked)">
                                                    </div>
                                                </td>
                                                <!-- Create -->
                                                <td class="text-center perm-matrix-col-add">
                                                    <div class="d-flex justify-content-center align-items-center m-0">
                                                        <input class="form-check-input matrix-perm-check matrix-perm-add matrix-row-<?= $mid ?>" 
                                                               type="checkbox" value="1" id="m_add_<?= $mid ?>"
                                                               onchange="onPermCheckboxChange(<?= $mid ?>, 'add', this.checked)">
                                                    </div>
                                                </td>
                                                <!-- Edit -->
                                                <td class="text-center perm-matrix-col-edit">
                                                    <div class="d-flex justify-content-center align-items-center m-0">
                                                        <input class="form-check-input matrix-perm-check matrix-perm-edit matrix-row-<?= $mid ?>" 
                                                               type="checkbox" value="1" id="m_edit_<?= $mid ?>"
                                                               onchange="onPermCheckboxChange(<?= $mid ?>, 'edit', this.checked)">
                                                    </div>
                                                </td>
                                                <!-- Delete -->
                                                <td class="text-center perm-matrix-col-delete">
                                                    <div class="d-flex justify-content-center align-items-center m-0">
                                                        <input class="form-check-input matrix-perm-check matrix-perm-delete matrix-row-<?= $mid ?>" 
                                                               type="checkbox" value="1" id="m_delete_<?= $mid ?>"
                                                               onchange="onPermCheckboxChange(<?= $mid ?>, 'delete', this.checked)">
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <a href="<?= \App\Core\Controller::url('/m/staff' . ($selectedCid ? '?concern_id=' . $selectedCid : '')) ?>" class="btn btn-light rounded-pill px-4 text-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" style="background: #4f46e5; border-color: #4f46e5;">
                            <i class="bi bi-check-circle-fill me-1"></i> Register Staff Member
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
window.allConcerns = <?= json_encode($allConcerns ?? []) ?>;
window.allRolePerms = <?= json_encode($allRolePerms ?? []) ?>;
window.concernModules = <?= json_encode($concernModules ?? []) ?>;
window.activeConcernId = null;
window.concernPerms = <?= json_encode(!empty($oldInput['perms']) ? $oldInput['perms'] : new stdClass()) ?>;

function getSelectedConcernIds() {
    const cids = [];
    document.querySelectorAll('.create-concern-check:checked').forEach(cb => {
        const val = parseInt(cb.value);
        if (val && !cids.includes(val)) {
            cids.push(val);
        }
    });
    const hiddenCid = document.getElementById('staff_concern_id');
    if (hiddenCid && cids.length > 0) {
        hiddenCid.value = cids[0];
    }
    return cids;
}

function renderConcernTabs() {
    const cids = getSelectedConcernIds();
    const countBadge = document.getElementById('concern_selected_count_badge');
    if (countBadge) {
        countBadge.textContent = cids.length;
    }

    if (!window.activeConcernId || !cids.includes(window.activeConcernId)) {
        window.activeConcernId = cids.length > 0 ? cids[0] : null;
    }

    const pillsUl = document.getElementById('concern_matrix_pills');
    if (!pillsUl) return;
    pillsUl.innerHTML = '';

    if (cids.length === 0) {
        pillsUl.innerHTML = '<li class="nav-item p-2 text-muted small"><i class="bi bi-info-circle me-1"></i> Please check at least one concern in Section 1 above to configure permissions.</li>';
        const badge = document.getElementById('active_tab_module_count_badge');
        if (badge) badge.textContent = 'No concern selected';
        
        // Hide module rows and group headers when no concern is selected
        document.querySelectorAll('.matrix-mod-row').forEach(row => row.style.display = 'none');
        document.querySelectorAll('.matrix-group-header').forEach(header => header.style.display = 'none');
        return;
    }

    cids.forEach(cid => {
        const c = (window.allConcerns || []).find(item => parseInt(item.id) === cid);
        const name = c ? c.name : 'Concern #' + cid;
        const code = c ? c.code : 'CON';
        const modCount = (window.concernModules[cid] || []).length;
        const isActive = (cid === window.activeConcernId);

        const li = document.createElement('li');
        li.className = 'nav-item';
        li.innerHTML = `
            <button type="button" class="btn btn-sm ${isActive ? 'btn-primary text-white shadow-sm' : 'btn-outline-secondary bg-white text-dark'} rounded-pill px-3 py-1.5 d-flex align-items-center gap-1.5 fw-semibold" style="font-size: 0.78rem;" onclick="switchConcernTab(${cid})">
                <i class="bi bi-building"></i>
                <span>${escapeHtml(name)}</span>
                <span class="badge ${isActive ? 'bg-white text-primary' : 'bg-light text-secondary border'} font-monospace ms-1" style="font-size: 0.68rem;">${escapeHtml(code)} &bull; ${modCount} Modules</span>
            </button>
        `;
        pillsUl.appendChild(li);
    });

    applyConcernView(window.activeConcernId);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function saveActiveTabPerms() {
    if (!window.activeConcernId) return;
    if (!window.concernPerms[window.activeConcernId]) {
        window.concernPerms[window.activeConcernId] = {};
    }
    const allowed = window.concernModules[window.activeConcernId] || [];
    allowed.forEach(mid => {
        window.concernPerms[window.activeConcernId][mid] = {
            view: document.getElementById('m_view_' + mid)?.checked ? 1 : 0,
            add: document.getElementById('m_add_' + mid)?.checked ? 1 : 0,
            edit: document.getElementById('m_edit_' + mid)?.checked ? 1 : 0,
            delete: document.getElementById('m_delete_' + mid)?.checked ? 1 : 0,
        };
    });
}

function switchConcernTab(newCid) {
    saveActiveTabPerms();
    window.activeConcernId = newCid;
    renderConcernTabs();
}

function applyConcernView(cid) {
    if (!cid) return;
    const c = (window.allConcerns || []).find(item => parseInt(item.id) === cid);
    const name = c ? c.name : 'Concern #' + cid;
    const allowed = window.concernModules[cid] || [];

    const badge = document.getElementById('active_tab_module_count_badge');
    if (badge) {
        badge.innerHTML = `<i class="bi bi-shield-check text-success me-1"></i> Showing <strong>${allowed.length}</strong> modules provisioned for <strong>${escapeHtml(name)}</strong>`;
    }

    const groupCount = {};
    document.querySelectorAll('.matrix-mod-row').forEach(row => {
        const mid = parseInt(row.getAttribute('data-mid'));
        const grp = row.getAttribute('data-group');
        if (allowed.includes(mid)) {
            row.style.display = '';
            groupCount[grp] = (groupCount[grp] || 0) + 1;
        } else {
            row.style.display = 'none';
        }
    });

    document.querySelectorAll('.matrix-group-header').forEach(header => {
        const grp = header.getAttribute('data-group');
        header.style.display = (groupCount[grp] && groupCount[grp] > 0) ? '' : 'none';
    });

    // Populate checkboxes from memory for this concern
    const perms = window.concernPerms[cid] || {};
    allowed.forEach(mid => {
        const p = perms[mid] || {};
        const v = document.getElementById('m_view_' + mid);
        const a = document.getElementById('m_add_' + mid);
        const e = document.getElementById('m_edit_' + mid);
        const d = document.getElementById('m_delete_' + mid);
        if (v) v.checked = (p.view === 1);
        if (a) a.checked = (p.add === 1);
        if (e) e.checked = (p.edit === 1);
        if (d) d.checked = (p.delete === 1);
    });

    updateHeaderCheckboxesState();
}

function onPermCheckboxChange(mid, act, checked) {
    if (!window.activeConcernId) return;
    if (!window.concernPerms[window.activeConcernId]) {
        window.concernPerms[window.activeConcernId] = {};
    }
    if (!window.concernPerms[window.activeConcernId][mid]) {
        window.concernPerms[window.activeConcernId][mid] = {};
    }
    window.concernPerms[window.activeConcernId][mid][act] = checked ? 1 : 0;
    updateHeaderCheckboxesState();
}

function onConcernSelectionChanged() {
    saveActiveTabPerms();
    renderConcernTabs();
}

document.addEventListener('DOMContentLoaded', function() {
    const roleId = document.getElementById('staff_role_id')?.value;
    if (roleId) {
        onRoleChangedInCreate(roleId);
    }

    // Attach listeners to concern checkboxes
    document.querySelectorAll('.create-concern-check').forEach(cb => {
        cb.addEventListener('change', onConcernSelectionChanged);
    });

    renderConcernTabs();
    updateHeaderCheckboxesState();
});

function toggleHeaderColumn(action, checked) {
    if (!window.activeConcernId) {
        alert('Please select at least one Business Concern in Section 1 (e.g., GRM GARMENTS or GRM ELITE WEAR) before configuring permissions.');
        return;
    }
    const allowed = window.concernModules[window.activeConcernId] || [];
    document.querySelectorAll('.matrix-perm-' + action).forEach(cb => {
        const row = cb.closest('tr');
        if (row && row.style.display !== 'none') {
            cb.checked = checked;
            const mid = parseInt(row.getAttribute('data-mid'));
            if (mid && allowed.includes(mid)) {
                if (!window.concernPerms[window.activeConcernId]) window.concernPerms[window.activeConcernId] = {};
                if (!window.concernPerms[window.activeConcernId][mid]) window.concernPerms[window.activeConcernId][mid] = {};
                window.concernPerms[window.activeConcernId][mid][action] = checked ? 1 : 0;
            }
        }
    });
    updateHeaderCheckboxesState();
}

function toggleMasterSelectAll(checked) {
    setAllMatrixPerms(checked);
}

function updateHeaderCheckboxesState() {
    const actions = ['view', 'add', 'edit', 'delete'];
    let allCheckedCount = 0;
    let totalAllCount = 0;

    actions.forEach(act => {
        const visibleCbs = Array.from(document.querySelectorAll('.matrix-perm-' + act)).filter(cb => {
            const row = cb.closest('tr');
            return row && row.style.display !== 'none';
        });
        const headerCb = document.getElementById('header_check_' + act);
        if (!visibleCbs.length || !headerCb) return;

        let checkedCount = 0;
        visibleCbs.forEach(cb => {
            if (cb.checked) checkedCount++;
        });

        totalAllCount += visibleCbs.length;
        allCheckedCount += checkedCount;

        if (checkedCount === visibleCbs.length && visibleCbs.length > 0) {
            headerCb.checked = true;
            headerCb.indeterminate = false;
        } else if (checkedCount > 0) {
            headerCb.checked = false;
            headerCb.indeterminate = true;
        } else {
            headerCb.checked = false;
            headerCb.indeterminate = false;
        }
    });

    const masterCb = document.getElementById('header_master_check_all');
    if (masterCb && totalAllCount > 0) {
        masterCb.checked = (allCheckedCount === totalAllCount && totalAllCount > 0);
        masterCb.indeterminate = (allCheckedCount > 0 && allCheckedCount < totalAllCount);
    }
}

function onRoleChangedInCreate(roleId) {
    if (!roleId || !window.allRolePerms) return;
    const rolePerms = window.allRolePerms[roleId] || {};
    const textNotice = document.getElementById('perm_sync_role_text');
    if (textNotice) {
        textNotice.innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i> Permissions auto-populated from selected role.';
    }

    if (window.activeConcernId) {
        if (!window.concernPerms[window.activeConcernId]) {
            window.concernPerms[window.activeConcernId] = {};
        }
        Object.keys(rolePerms).forEach(mid => {
            const rp = rolePerms[mid];
            window.concernPerms[window.activeConcernId][mid] = {
                view: rp.view === 1 ? 1 : 0,
                add: rp.add === 1 ? 1 : 0,
                edit: rp.edit === 1 ? 1 : 0,
                delete: rp.delete === 1 ? 1 : 0,
            };
        });
        applyConcernView(window.activeConcernId);
    }
}

function setAllMatrixPerms(checked) {
    if (!window.activeConcernId) {
        alert('Please select at least one Business Concern in Section 1 (e.g., GRM GARMENTS or GRM ELITE WEAR) before configuring permissions.');
        return;
    }
    const allowed = window.concernModules[window.activeConcernId] || [];
    document.querySelectorAll('.matrix-perm-check').forEach(cb => {
        const row = cb.closest('tr');
        if (row && row.style.display !== 'none') {
            cb.checked = checked;
            const mid = parseInt(row.getAttribute('data-mid'));
            if (mid && allowed.includes(mid)) {
                if (!window.concernPerms[window.activeConcernId]) window.concernPerms[window.activeConcernId] = {};
                if (!window.concernPerms[window.activeConcernId][mid]) window.concernPerms[window.activeConcernId][mid] = {};
                ['view', 'add', 'edit', 'delete'].forEach(act => {
                    if (cb.classList.contains('matrix-perm-' + act)) {
                        window.concernPerms[window.activeConcernId][mid][act] = checked ? 1 : 0;
                    }
                });
            }
        }
    });
    updateHeaderCheckboxesState();
}

function setAllMatrixReadOnly() {
    if (!window.activeConcernId) {
        alert('Please select at least one Business Concern in Section 1 (e.g., GRM GARMENTS or GRM ELITE WEAR) before configuring permissions.');
        return;
    }
    const allowed = window.concernModules[window.activeConcernId] || [];
    document.querySelectorAll('.matrix-perm-check').forEach(cb => {
        const row = cb.closest('tr');
        if (row && row.style.display !== 'none') {
            const isView = cb.classList.contains('matrix-perm-view');
            cb.checked = isView;
            const mid = parseInt(row.getAttribute('data-mid'));
            if (mid && allowed.includes(mid)) {
                if (!window.concernPerms[window.activeConcernId]) window.concernPerms[window.activeConcernId] = {};
                if (!window.concernPerms[window.activeConcernId][mid]) window.concernPerms[window.activeConcernId][mid] = {};
                ['view', 'add', 'edit', 'delete'].forEach(act => {
                    if (cb.classList.contains('matrix-perm-' + act)) {
                        window.concernPerms[window.activeConcernId][mid][act] = (act === 'view') ? 1 : 0;
                    }
                });
            }
        }
    });
    updateHeaderCheckboxesState();
}

function setColumnMatrixPerm(action, checked) {
    toggleHeaderColumn(action, checked);
}

function selectAllCreateConcerns(check) {
    document.querySelectorAll('.create-concern-check').forEach(cb => {
        const col = cb.closest('.col-md-6');
        if (col && col.style.display !== 'none') {
            cb.checked = check;
        }
    });
    onConcernSelectionChanged();
}

function filterConcernCards(query) {
    const term = (query || '').trim().toLowerCase();
    let visibleCount = 0;
    document.querySelectorAll('#create_concern_checkboxes_grid .col-md-6').forEach(col => {
        const name = (col.getAttribute('data-name') || '').toLowerCase();
        const code = (col.getAttribute('data-code') || '').toLowerCase();
        if (!term || name.includes(term) || code.includes(term)) {
            col.style.display = '';
            visibleCount++;
        } else {
            col.style.display = 'none';
        }
    });

    const noMatchesEl = document.getElementById('no_concerns_match_msg');
    if (noMatchesEl) {
        noMatchesEl.style.display = (visibleCount === 0) ? 'block' : 'none';
    }
}

function clearConcernSearch() {
    const input = document.getElementById('search_concerns_input');
    if (input) {
        input.value = '';
        filterConcernCards('');
        input.focus();
    }
}

function toggleCreatePassword(btn) {
    const input = document.getElementById('staff_create_password');
    if (!input) return;
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
            icon.className = 'bi bi-eye-slash';
        }
    } else {
        input.type = 'password';
        if (icon) {
            icon.className = 'bi bi-eye';
        }
    }
}

function updatePhoneCounter(input) {
    if (!input) return;
    input.value = input.value.replace(/[^0-9]/g, '');
    const len = input.value.length;
    const badge = document.getElementById('phone_digit_counter');
    if (badge) {
        badge.textContent = len + ' / 10 digits';
        if (len === 10) {
            badge.className = 'badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 font-monospace';
        } else {
            badge.className = 'badge bg-light text-muted border font-monospace';
        }
    }
}

function selectRoleCombobox(roleId, roleName) {
    const roleInput = document.getElementById('staff_role_input');
    const roleIdInput = document.getElementById('staff_create_role_id');
    if (roleInput) roleInput.value = roleName;
    if (roleIdInput) roleIdInput.value = roleId;
    applyRolePermissions(roleId);
}

function onRoleInputChanged(val) {
    const roleIdInput = document.getElementById('staff_create_role_id');
    const term = (val || '').trim().toLowerCase();
    
    // Check if entered text matches an existing role in the datalist
    let foundId = '';
    const datalist = document.getElementById('role_datalist');
    if (datalist) {
        for (const opt of datalist.options) {
            if (opt.value.trim().toLowerCase() === term) {
                foundId = opt.getAttribute('data-id') || '';
                break;
            }
        }
    }
    if (roleIdInput) {
        roleIdInput.value = foundId;
    }
    if (foundId) {
        applyRolePermissions(foundId);
    }
}

function applyRolePermissions(roleId) {
    if (!roleId || !window.allRolePerms) return;
    const roleIdInt = parseInt(roleId);
    const rPerms = window.allRolePerms[roleIdInt] || {};
    
    // Auto populate permissions for active concern
    const selectedCids = getSelectedConcernIds();
    selectedCids.forEach(cid => {
        if (!window.concernPerms[cid]) window.concernPerms[cid] = {};
        const allowed = window.concernModules[cid] || [];
        allowed.forEach(mid => {
            if (rPerms[mid]) {
                window.concernPerms[cid][mid] = { ...rPerms[mid] };
            }
        });
    });

    if (window.activeConcernId) {
        loadConcernPermsToMatrix(window.activeConcernId);
        updateHeaderCheckboxesState();
    }
}

// Prepare structured per-concern permissions on form submit
function preparePermsOnSubmit() {
    saveActiveTabPerms();
    const container = document.getElementById('dynamic_perms_inputs');
    if (!container) return;
    container.innerHTML = '';

    const selectedCids = getSelectedConcernIds();
    selectedCids.forEach(cid => {
        const allowed = window.concernModules[cid] || [];
        const perms = window.concernPerms[cid] || {};
        allowed.forEach(mid => {
            const p = perms[mid] || {};
            ['view', 'add', 'edit', 'delete'].forEach(act => {
                if (p[act] === 1) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `perms[${cid}][${mid}][${act}]`;
                    input.value = '1';
                    container.appendChild(input);
                }
            });
        });
    });

    // Remove name attribute from visible table checkboxes so they don't overwrite perms array
    document.querySelectorAll('.matrix-perm-check').forEach(cb => {
        cb.removeAttribute('name');
    });
}

// Prevent browser password manager from automatically filling saved admin credentials
document.addEventListener('DOMContentLoaded', () => {
    const clearAutofill = () => {
        const hasOld = <?= !empty($oldInput['email']) || !empty($oldInput['name']) ? 'true' : 'false' ?>;
        if (hasOld) return; // Retain user's entered form data when returning from validation error
        const emailEl = document.getElementById('staff_create_email');
        const passEl = document.getElementById('staff_create_password');
        if (emailEl) emailEl.value = '';
        if (passEl) passEl.value = '';
    };
    clearAutofill();
    setTimeout(clearAutofill, 50);
    setTimeout(clearAutofill, 200);

    const staffForm = document.querySelector('form[action*="/m/staff/store"]');
    if (staffForm) {
        staffForm.addEventListener('submit', (e) => {
            const selectedCids = getSelectedConcernIds();
            if (selectedCids.length === 0) {
                e.preventDefault();
                alert('Please select at least one business concern for this staff member.');
                return false;
            }
            preparePermsOnSubmit();
            staffForm.querySelectorAll('[readonly]').forEach(el => el.removeAttribute('readonly'));
        });
    }
});
</script>
