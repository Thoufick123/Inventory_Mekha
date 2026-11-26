<?php
// app/views/concern/staff_roles.php - Staff Role & Module CRUD Permissions Matrix
$totalStaff = count($staffMembers);
$activeUsers = count(array_filter($staffMembers, fn($s) => !empty($s['user_id'])));
$multiConcernUsers = 0;
foreach ($staffMembers as $s) {
    if (!empty($s['user_id']) && isset($userConcernsMap[$s['user_id']]) && count($userConcernsMap[$s['user_id']]) > 1) {
        $multiConcernUsers++;
    }
}
$totalRoles = count($allRoles);

// Group modules by category
$groupedModules = [];
foreach ($modules as $m) {
    if ($m['code'] === 'cash') continue; // Unified under 'Cash & Cash Flow'
    $grp = $m['group_name'] ?: 'General';
    $groupedModules[$grp][] = $m;
}

$activeTab = $activeTab ?? 'staff';
?>

<div class="container-fluid px-0">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2.5 py-1">
                    <i class="bi bi-shield-lock-fill me-1"></i> Access Control & Security
                </span>
                <h4 class="fw-bold mb-0 text-dark">Staff Access & Module CRUD Permissions</h4>
            </div>
            <p class="text-secondary small mb-0">
                Configure module permissions (View, Create, Edit, Delete) and authorized business locations for personnel and system roles.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= \App\Core\Controller::url('/m/staff') ?>" class="btn btn-outline-secondary shadow-sm">
                <i class="bi bi-people-fill me-1"></i> View All Staff
            </a>
            <?php if (\App\Core\Auth::can('staff', 'add')): ?>
            <a href="<?= \App\Core\Controller::url('/m/staff/roles/create') ?>" class="btn btn-primary shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Add New Role
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Total Staff</small>
                        <h3 class="fw-bold text-dark mb-0 mt-1"><?= $totalStaff ?></h3>
                    </div>
                    <div style="width: 44px; height: 44px; background: #eff6ff; color: #2563eb; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Logins Active</small>
                        <h3 class="fw-bold text-success mb-0 mt-1"><?= $activeUsers ?></h3>
                    </div>
                    <div style="width: 44px; height: 44px; background: #ecfdf5; color: #059669; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="bi bi-person-check-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Multi-Concern Authorized</small>
                        <h3 class="fw-bold text-primary mb-0 mt-1"><?= $multiConcernUsers ?></h3>
                    </div>
                    <div style="width: 44px; height: 44px; background: #f5f3ff; color: #7c3aed; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="bi bi-buildings-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Configured Roles</small>
                        <h3 class="fw-bold text-dark mb-0 mt-1"><?= $totalRoles ?></h3>
                    </div>
                    <div style="width: 44px; height: 44px; background: #fffbeb; color: #d97706; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="bi bi-shield-check"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Toolbar Card -->
    <?php
    $exportTitle = 'Export Staff Roles & Module CRUD Permissions (Excel, PDF, CSV)';
    $exportSubtitle = 'Download staff login assignments, system roles, authorized concerns & access controls';
    $exportUrl = \App\Core\Controller::url('/m/staff/export_roles');
    $currentModule = 'staff';
    $prefix = 'stf_role_exp';
    include dirname(__DIR__) . '/partials/export_card.php';
    ?>

    <!-- Mode Navigation Tabs -->
    <ul class="nav nav-pills mb-4 bg-white p-2 rounded-3 shadow-xs border" id="pills-tab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'staff' ? 'active' : '' ?> fw-semibold d-flex align-items-center gap-2" 
                    id="pills-staff-tab" data-bs-toggle="pill" data-bs-target="#pills-staff" type="button" role="tab">
                <i class="bi bi-people"></i> Staff Directory & Role Assignment
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'matrix' ? 'active' : '' ?> fw-semibold d-flex align-items-center gap-2" 
                    id="pills-matrix-tab" data-bs-toggle="pill" data-bs-target="#pills-matrix" type="button" role="tab">
                <i class="bi bi-ui-checks-grid"></i> Module CRUD Permissions Matrix
            </button>
        </li>
    </ul>

    <div class="tab-content" id="pills-tabContent">
        <!-- ======================================================== -->
        <!-- TAB 1: STAFF DIRECTORY & ASSIGNED ROLES -->
        <!-- ======================================================== -->
        <div class="tab-pane fade <?= $activeTab === 'staff' ? 'show active' : '' ?>" id="pills-staff" role="tabpanel">
            <!-- Filter & Search Toolbar -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="<?= \App\Core\Controller::url('/m/staff/roles') ?>" class="row g-2 align-items-center">
                        <input type="hidden" name="tab" value="staff">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search staff name, code, phone, designation..." value="<?= htmlspecialchars($search ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select name="concern_id" class="form-select" onchange="this.form.submit()">
                                <option value="">-- Filter by Home Concern (All) --</option>
                                <?php foreach ($allConcerns as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($concernFilter == $c['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary px-3">
                                <i class="bi bi-funnel me-1"></i> Filter
                            </button>
                            <?php if ($concernFilter || $search): ?>
                                <a href="<?= \App\Core\Controller::url('/m/staff/roles?tab=staff') ?>" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle me-1"></i> Reset
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Staff List Table -->
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-uppercase text-secondary" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                            <tr>
                                <th class="ps-4 py-3">Staff Personnel</th>
                                <th class="py-3">Home Concern</th>
                                <th class="py-3">Designation / Dept</th>
                                <th class="py-3">Login Account</th>
                                <th class="py-3">Assigned Role</th>
                                <th class="py-3">Authorized Locations</th>
                                <th class="text-end pe-4 py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody class="border-top-0">
                            <?php if (empty($staffMembers)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                        No staff members found matching criteria.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($staffMembers as $stf): 
                                    $userId = (int)($stf['user_id'] ?? 0);
                                    $assignedConcerns = $userId && isset($userConcernsMap[$userId]) ? $userConcernsMap[$userId] : [];
                                    $assignedRoleName = $stf['role_name'] ?? null;
                                    $hasLogin = !empty($stf['user_id']) && !empty($stf['user_email']);
                                    $isMulti = count($assignedConcerns) > 1;
                                ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold shadow-xs" 
                                                     style="width: 38px; height: 38px; background: #3b82f6; font-size: 0.85rem; flex-shrink: 0;">
                                                    <?= strtoupper(substr($stf['name'] ?? 'S', 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($stf['name']) ?></div>
                                                    <small class="text-muted font-monospace"><?= htmlspecialchars($stf['staff_code']) ?></small>
                                                    <?php if (!empty($stf['phone'])): ?>
                                                        <small class="text-secondary">&bull; <?= htmlspecialchars($stf['phone']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border font-monospace">
                                                <?= htmlspecialchars($stf['concern_code']) ?>
                                            </span>
                                            <small class="d-block text-secondary mt-0.5"><?= htmlspecialchars($stf['concern_name']) ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($stf['designation'] ?: 'Staff') ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($stf['department'] ?: 'General') ?></small>
                                        </td>
                                        <td>
                                            <?php if ($hasLogin): ?>
                                                <div class="d-flex align-items-center gap-1.5">
                                                    <i class="bi bi-check-circle-fill text-success small"></i>
                                                    <span class="small fw-semibold text-dark"><?= htmlspecialchars($stf['user_email']) ?></span>
                                                </div>
                                                <small class="text-success fw-semibold">Login Active</small>
                                            <?php else: ?>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary border">No Login Account</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($assignedRoleName): ?>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2.5 py-1.5 fw-semibold">
                                                    <i class="bi bi-shield-check me-1"></i><?= htmlspecialchars($assignedRoleName) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border px-2.5 py-1.5">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($assignedConcerns)): ?>
                                                <div class="d-flex flex-wrap gap-1 align-items-center" style="max-width: 260px;">
                                                    <?php foreach ($assignedConcerns as $ac): ?>
                                                        <span class="badge <?= $ac['id'] == $stf['concern_id'] ? 'bg-primary' : 'bg-dark' ?> font-monospace" style="font-size: 0.72rem;" title="<?= htmlspecialchars($ac['name']) ?>">
                                                            <?= htmlspecialchars($ac['code']) ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                    <?php if ($isMulti): ?>
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size: 0.72rem;">
                                                            <i class="bi bi-check-all me-0.5"></i> <?= count($assignedConcerns) ?> Units
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.72rem;">
                                                    <?= htmlspecialchars($stf['concern_code']) ?> (Home Only)
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="d-inline-flex align-items-center gap-1.5 flex-wrap justify-content-end">
                                                <!-- 1. Quick Status Toggle -->
                                                <?php if (\App\Core\Auth::can('staff', 'edit')): ?>
                                                <form action="<?= \App\Core\Controller::url('/m/staff/toggle-status') ?>" method="POST" class="d-inline" onsubmit="return confirm('<?= $stf['status'] === 'active' ? 'Deactivate' : 'Activate' ?> <?= htmlspecialchars($stf['name']) ?>?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                                                    <input type="hidden" name="id" value="<?= $stf['id'] ?>">
                                                    <?php if ($stf['status'] === 'active'): ?>
                                                        <button type="submit" class="btn btn-xs btn-outline-warning" title="Deactivate Staff Account">
                                                            <i class="bi bi-person-slash"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="submit" class="btn btn-xs btn-outline-success" title="Activate Staff Account">
                                                            <i class="bi bi-person-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                                <?php endif; ?>

                                                <!-- 2. Assign Role & Locations -->
                                                <button type="button" class="btn btn-xs btn-primary shadow-xs" 
                                                        title="Assign Role & Locations"
                                                        onclick="openRoleAssignModal(<?= htmlspecialchars(json_encode([
                                                            'id'            => $stf['id'],
                                                            'name'          => $stf['name'],
                                                            'staff_code'    => $stf['staff_code'],
                                                            'concern_id'    => $stf['concern_id'],
                                                            'concern_name'  => $stf['concern_name'],
                                                            'concern_code'  => $stf['concern_code'],
                                                            'email'         => $stf['user_email'] ?: $stf['email'],
                                                            'role_id'       => $stf['role_id'] ?? 0,
                                                            'assigned_cids' => array_column($assignedConcerns, 'id')
                                                        ])) ?>)">
                                                    <i class="bi bi-person-gear me-1"></i> Role
                                                </button>

                                                <!-- 3. Granular Access Matrix (Overrides) -->
                                                <?php if ($hasLogin): ?>
                                                    <button type="button" class="btn btn-xs btn-outline-dark shadow-xs" 
                                                            title="Configure Individual Custom Permissions"
                                                            onclick="openCustomUserPermissionsModal(<?= (int)$stf['id'] ?>, <?= (int)$stf['user_id'] ?>, '<?= htmlspecialchars(addslashes($stf['name'])) ?>')">
                                                        <i class="bi bi-shield-lock me-1"></i> Permissions
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-xs btn-outline-secondary opacity-75" 
                                                            title="Assign a login account/role first"
                                                            onclick="openRoleAssignModal(<?= htmlspecialchars(json_encode([
                                                                'id'            => $stf['id'],
                                                                'name'          => $stf['name'],
                                                                'staff_code'    => $stf['staff_code'],
                                                                'concern_id'    => $stf['concern_id'],
                                                                'concern_name'  => $stf['concern_name'],
                                                                'concern_code'  => $stf['concern_code'],
                                                                'email'         => $stf['user_email'] ?: $stf['email'],
                                                                'role_id'       => $stf['role_id'] ?? 0,
                                                                'assigned_cids' => array_column($assignedConcerns, 'id')
                                                            ])) ?>)">
                                                        <i class="bi bi-plus me-1"></i> Set Access
                                                    </button>
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

        <!-- ======================================================== -->
        <!-- TAB 2: MODULE CRUD PERMISSIONS MATRIX (VIEW, CREATE, EDIT, DELETE) -->
        <!-- ======================================================== -->
        <div class="tab-pane fade <?= $activeTab === 'matrix' ? 'show active' : '' ?>" id="pills-matrix" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-4">
                    <!-- Role Selector & Header -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 pb-3 border-bottom mb-4">
                        <div>
                            <h5 class="fw-bold text-dark mb-1">
                                <i class="bi bi-shield-check text-primary me-1.5"></i> Module CRUD Permissions Matrix
                            </h5>
                            <p class="text-secondary small mb-0">
                                Check or uncheck View, Create, Edit, and Delete operations for each module to enforce granular security across all business units.
                            </p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <label class="small fw-semibold text-secondary text-nowrap">Select Role to Configure:</label>
                            <select class="form-select form-select-sm" style="min-width: 250px;" onchange="window.location.href = '<?= \App\Core\Controller::url('/m/staff/roles?tab=matrix&matrix_role_id=') ?>' + this.value">
                                <?php foreach ($allRoles as $r): ?>
                                    <option value="<?= $r['id'] ?>" <?= ($selectedRoleId == $r['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['name']) ?> <?= $r['concern_id'] ? '(Concern Specific)' : '(Global)' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Bulk Actions Toolbar -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-2.5 bg-light rounded-3 mb-4 border">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="small fw-bold text-secondary text-uppercase" style="font-size: 0.72rem;">Quick Toggles:</span>
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="setColumnPerm('view', true)">
                                <i class="bi bi-check-all me-1"></i> Check All View
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-success" onclick="setColumnPerm('add', true)">
                                <i class="bi bi-check-all me-1"></i> Check All Create
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-warning" onclick="setColumnPerm('edit', true)">
                                <i class="bi bi-check-all me-1"></i> Check All Edit
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-danger" onclick="setColumnPerm('delete', true)">
                                <i class="bi bi-check-all me-1"></i> Check All Delete
                            </button>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-xs btn-primary" onclick="setAllPerms(true)">
                                <i class="bi bi-check-square me-1"></i> Select All (Full Access)
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="setAllPerms(false)">
                                <i class="bi bi-square me-1"></i> Clear All
                            </button>
                        </div>
                    </div>

                    <!-- Permissions Form -->
                    <form method="POST" action="<?= \App\Core\Controller::url('/m/staff/save-role-permissions') ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                        <input type="hidden" name="role_id" value="<?= $selectedRoleId ?>">

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0" id="matrixTable">
                                <thead class="bg-light text-uppercase text-secondary" style="font-size: 0.75rem;">
                                    <tr>
                                        <th style="width: 32%;">Module / Feature</th>
                                        <th style="width: 14%;" class="text-center bg-primary bg-opacity-10 text-primary">
                                            <i class="bi bi-eye-fill me-1"></i> View
                                        </th>
                                        <th style="width: 14%;" class="text-center bg-success bg-opacity-10 text-success">
                                            <i class="bi bi-plus-circle-fill me-1"></i> Create (Add)
                                        </th>
                                        <th style="width: 14%;" class="text-center bg-warning bg-opacity-10 text-warning text-dark">
                                            <i class="bi bi-pencil-fill me-1"></i> Edit (Update)
                                        </th>
                                        <th style="width: 14%;" class="text-center bg-danger bg-opacity-10 text-danger">
                                            <i class="bi bi-trash-fill me-1"></i> Delete
                                        </th>
                                        <th style="width: 12%;" class="text-center">Row Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($groupedModules as $grpName => $grpMods): ?>
                                        <tr class="table-secondary">
                                            <td colspan="6" class="fw-bold text-dark py-2 px-3 text-uppercase" style="font-size: 0.78rem; letter-spacing: 0.05em;">
                                                <i class="bi bi-folder2-open me-1.5 text-primary"></i> <?= htmlspecialchars($grpName) ?> Group
                                            </td>
                                        </tr>
                                        <?php foreach ($grpMods as $m): 
                                            $mid = (int)$m['id'];
                                            $p = $matrixPerms[$mid] ?? ['can_view' => 1, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0];
                                        ?>
                                            <tr id="row_mod_<?= $mid ?>">
                                                <td class="ps-4">
                                                    <div class="d-flex align-items-center gap-2.5">
                                                        <i class="bi <?= htmlspecialchars($m['icon'] ?: 'bi-app') ?> text-primary fs-5"></i>
                                                        <span class="fw-semibold text-dark"><?= htmlspecialchars($m['name']) ?></span>
                                                    </div>
                                                </td>
                                                <!-- View Checkbox -->
                                                <td class="text-center">
                                                    <div class="form-check d-flex justify-content-center m-0">
                                                        <input class="form-check-input perm-view perm-check-<?= $mid ?>" type="checkbox" 
                                                               name="perms[<?= $mid ?>][view]" value="1" <?= !empty($p['can_view']) ? 'checked' : '' ?>
                                                               style="width: 20px; height: 20px; cursor: pointer;">
                                                    </div>
                                                </td>
                                                <!-- Create Checkbox -->
                                                <td class="text-center">
                                                    <div class="form-check d-flex justify-content-center m-0">
                                                        <input class="form-check-input perm-add perm-check-<?= $mid ?>" type="checkbox" 
                                                               name="perms[<?= $mid ?>][add]" value="1" <?= !empty($p['can_add']) ? 'checked' : '' ?>
                                                               style="width: 20px; height: 20px; cursor: pointer;">
                                                    </div>
                                                </td>
                                                <!-- Edit Checkbox -->
                                                <td class="text-center">
                                                    <div class="form-check d-flex justify-content-center m-0">
                                                        <input class="form-check-input perm-edit perm-check-<?= $mid ?>" type="checkbox" 
                                                               name="perms[<?= $mid ?>][edit]" value="1" <?= !empty($p['can_edit']) ? 'checked' : '' ?>
                                                               style="width: 20px; height: 20px; cursor: pointer;">
                                                    </div>
                                                </td>
                                                <!-- Delete Checkbox -->
                                                <td class="text-center">
                                                    <div class="form-check d-flex justify-content-center m-0">
                                                        <input class="form-check-input perm-delete perm-check-<?= $mid ?>" type="checkbox" 
                                                               name="perms[<?= $mid ?>][delete]" value="1" <?= !empty($p['can_delete']) ? 'checked' : '' ?>
                                                               style="width: 20px; height: 20px; cursor: pointer;">
                                                    </div>
                                                </td>
                                                <!-- Row Quick Action -->
                                                <td class="text-center">
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-secondary btn-xs" title="Full Access (All Checked)" onclick="setRowPerm(<?= $mid ?>, true)">All</button>
                                                        <button type="button" class="btn btn-outline-secondary btn-xs" title="Read Only (View Only)" onclick="setRowReadOnly(<?= $mid ?>)">View</button>
                                                        <button type="button" class="btn btn-outline-secondary btn-xs" title="Clear Row" onclick="setRowPerm(<?= $mid ?>, false)">None</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Sticky Submit Bar -->
                        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top bg-white">
                            <span class="text-secondary small">
                                <i class="bi bi-info-circle me-1"></i> Permissions update immediately in real-time upon saving.
                            </span>
                            <?php if (\App\Core\Auth::can('staff', 'edit')): ?>
                            <button type="submit" class="btn btn-primary px-4 py-2 shadow-sm">
                                <i class="bi bi-check2-circle me-1.5"></i> Save CRUD Permissions for Selected Role
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: ASSIGN ROLE & CONCERN ACCESS (FOR INDIVIDUAL STAFF) -->
<!-- ======================================================== -->
<div class="modal fade" id="modalAssignRole" tabindex="-1" aria-labelledby="modalAssignRoleLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom px-4 py-3 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span style="width: 38px; height: 38px; background: #eff6ff; color: #2563eb; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="bi bi-shield-lock-fill"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0">Assign Role & Location Access</h5>
                        <small class="text-secondary" id="modal_staff_title_info">Select permissions and authorized business concerns.</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form method="POST" action="<?= \App\Core\Controller::url('/m/staff/assign-role') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                <input type="hidden" name="staff_id" id="assign_staff_id" value="">
                
                <div class="modal-body p-4" style="max-height: 75vh; overflow-y: auto;">
                    <!-- Staff Overview Banner -->
                    <div class="alert alert-light border rounded-3 p-3 mb-4 d-flex justify-content-between align-items-center" style="background: #f8fafc;">
                        <div>
                            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.7rem;">Staff Member</small>
                            <span class="fw-bold text-dark fs-6" id="assign_staff_name">-</span>
                            <span class="badge bg-secondary font-monospace ms-2" id="assign_staff_code">-</span>
                        </div>
                        <div class="text-end">
                            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.7rem;">Home Concern</small>
                            <span class="badge bg-primary font-monospace" id="assign_home_concern">-</span>
                        </div>
                    </div>

                    <!-- 1. System Login Account -->
                    <h6 class="fw-bold text-dark mb-3">
                        <i class="bi bi-person-badge me-1.5 text-primary"></i> 1. System Login Account
                    </h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Login Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="assign_email" class="form-control" required placeholder="user@invento.com">
                            <small class="text-muted">Username used to sign in to GRM Inventory.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Password</label>
                            <div class="input-group">
                                <input type="password" name="password" id="assign_password" class="form-control" placeholder="Enter new or leave blank">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('assign_password', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted">Leave blank to keep existing password.</small>
                        </div>
                    </div>

                    <hr class="my-3 opacity-25">

                    <!-- 2. Assign System Role -->
                    <h6 class="fw-bold text-dark mb-3">
                        <i class="bi bi-award me-1.5 text-primary"></i> 2. Assign System Role
                    </h6>
                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-secondary">Choose Role <span class="text-danger">*</span></label>
                        <select name="role_id" id="assign_role_id" class="form-select" required onchange="loadRolePermissionsForStaff(this.value)">
                            <option value="">-- Select Role --</option>
                            <?php foreach ($allRoles as $r): ?>
                                <option value="<?= $r['id'] ?>">
                                    <?= htmlspecialchars($r['name']) ?> <?= $r['concern_id'] ? '(Concern Specific)' : '(Global Role)' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-1">This role governs the default CRUD permissions and modules this staff member can access.</small>
                    </div>

                    <hr class="my-3 opacity-25">

                    <!-- 3. Authorized Locations / Concerns (Multi-Concern Access) -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="bi bi-buildings me-1.5 text-primary"></i> 3. Authorized Business Concerns
                        </h6>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectAllConcerns(true)">Select All</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectAllConcerns(false)">Clear</button>
                        </div>
                    </div>
                    <p class="small text-muted mb-3">
                        Select which concerns this personnel can switch between upon login or via the top navigation switcher:
                    </p>

                    <div class="row g-2 mb-4" id="concern_checkboxes_container">
                        <?php foreach ($allConcerns as $c): ?>
                            <div class="col-md-6">
                                <div class="border rounded-3 p-2.5 d-flex align-items-center gap-2 hover-shadow">
                                    <input class="form-check-input concern-check m-0" type="checkbox" name="concern_ids[]" value="<?= $c['id'] ?>" id="cid_check_<?= $c['id'] ?>">
                                    <label class="form-check-label flex-grow-1 cursor-pointer" for="cid_check_<?= $c['id'] ?>" style="cursor: pointer;">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-semibold text-dark small"><?= htmlspecialchars($c['name']) ?></span>
                                            <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.7rem;"><?= htmlspecialchars($c['code']) ?></span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <hr class="my-3 opacity-25">

                    <!-- 4. Optional Custom Granular CRUD Checkbox Matrix for this staff member -->
                    <div class="accordion" id="accordionCustomPerms">
                        <div class="accordion-item border rounded-3 overflow-hidden">
                            <h2 class="accordion-header" id="headingCustom">
                                <button class="accordion-button collapsed py-2.5 px-3 bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCustom">
                                    <div class="d-flex align-items-center gap-2">
                                        <input class="form-check-input m-0" type="checkbox" name="is_custom_perms" id="is_custom_perms" value="1" onclick="event.stopPropagation();">
                                        <label class="fw-bold text-dark small mb-0" for="is_custom_perms">
                                            <i class="bi bi-sliders me-1 text-primary"></i> Customize Granular CRUD Permissions for this Staff Member
                                        </label>
                                    </div>
                                </button>
                            </h2>
                            <div id="collapseCustom" class="accordion-collapse collapse" data-bs-parent="#accordionCustomPerms">
                                <div class="accordion-body p-3">
                                    <p class="small text-muted mb-3">
                                        Check or uncheck View, Create, Edit, and Delete operations for this specific staff member:
                                    </p>
                                    <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                                        <table class="table table-sm table-bordered align-middle mb-0" style="font-size: 0.8rem;">
                                            <thead class="bg-light text-secondary text-uppercase" style="font-size: 0.7rem; position: sticky; top: 0; z-index: 1;">
                                                <tr>
                                                    <th>Module</th>
                                                    <th class="text-center text-primary">View</th>
                                                    <th class="text-center text-success">Create</th>
                                                    <th class="text-center text-warning text-dark">Edit</th>
                                                    <th class="text-center text-danger">Delete</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($modules as $m): $mid = (int)$m['id']; ?>
                                                    <tr>
                                                        <td class="fw-semibold text-dark">
                                                            <i class="bi <?= htmlspecialchars($m['icon']) ?> me-1 text-secondary"></i>
                                                            <?= htmlspecialchars($m['name']) ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <input type="checkbox" name="custom_perms[<?= $mid ?>][view]" value="1" class="custom-perm-view custom-perm-<?= $mid ?>">
                                                        </td>
                                                        <td class="text-center">
                                                            <input type="checkbox" name="custom_perms[<?= $mid ?>][add]" value="1" class="custom-perm-add custom-perm-<?= $mid ?>">
                                                        </td>
                                                        <td class="text-center">
                                                            <input type="checkbox" name="custom_perms[<?= $mid ?>][edit]" value="1" class="custom-perm-edit custom-perm-<?= $mid ?>">
                                                        </td>
                                                        <td class="text-center">
                                                            <input type="checkbox" name="custom_perms[<?= $mid ?>][delete]" value="1" class="custom-perm-delete custom-perm-<?= $mid ?>">
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top px-4 py-3 bg-light d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">
                        <i class="bi bi-check2-circle me-1"></i> Save Role & Access
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: CREATE NEW ROLE -->
<!-- ======================================================== -->
<div class="modal fade" id="modalCreateRole" tabindex="-1" aria-labelledby="modalCreateRoleLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom px-4 py-3 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span style="width: 38px; height: 38px; background: #eff6ff; color: #2563eb; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="bi bi-shield-plus"></i>
                    </span>
                    <h5 class="modal-title fw-bold text-dark mb-0">Create New System Role</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form method="POST" action="<?= \App\Core\Controller::url('/m/staff/create-role') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Role Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Inventory Supervisor, POS Cashier, Senior Stitcher">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Brief summary of duties and access permissions"></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-secondary">Scope</label>
                        <select name="concern_id" class="form-select">
                            <option value="">Global Role (Available across all concerns)</option>
                            <?php foreach ($allConcerns as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> Only</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top px-4 py-2 bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">Create Role & Open Matrix</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: INDIVIDUAL USER GRANULAR PERMISSIONS MATRIX -->
<!-- ======================================================== -->
<div class="modal fade" id="modalUserCustomPermissions" tabindex="-1" aria-labelledby="modalUserCustomPermissionsLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom px-4 py-3 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span style="width: 40px; height: 40px; background: #e0f2fe; color: #0284c7; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                        <i class="bi bi-shield-lock-fill"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0">Custom Module Access & Overrides</h5>
                        <small class="text-secondary" id="ucp_header_subtitle">Configure granular overrides for this specific staff account.</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="<?= \App\Core\Controller::url('/m/staff/save-user-permissions') ?>" id="formUserCustomPerms">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                <input type="hidden" name="user_id" id="ucp_user_id" value="">
                <input type="hidden" name="staff_id" id="ucp_staff_id" value="">

                <div class="modal-body p-4">
                    <!-- Staff & Role Overview Banner -->
                    <div class="alert alert-light border rounded-3 p-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2" style="background: #f8fafc;">
                        <div>
                            <span class="fw-bold text-dark fs-6" id="ucp_staff_name">-</span>
                            <span class="badge bg-secondary font-monospace ms-2" id="ucp_staff_email">-</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small">Assigned Base Role:</span>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2.5 py-1" id="ucp_role_name">-</span>
                        </div>
                    </div>

                    <!-- Visual Legend & Explanation -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-2.5 bg-light rounded-3 mb-3 border small">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <span class="fw-bold text-secondary text-uppercase" style="font-size: 0.72rem;">Legend:</span>
                            <span class="badge bg-light text-dark border">
                                <i class="bi bi-circle me-1 text-secondary"></i> Inherit (Role Default)
                            </span>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                <i class="bi bi-check-circle-fill me-1"></i> Explicit Grant (Allow)
                            </span>
                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">
                                <i class="bi bi-x-circle-fill me-1"></i> Explicit Deny (Block)
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-1.5">
                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="setAllUserOverrides('inherit')">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset All to Inherit
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-success" onclick="setAllUserOverrides('grant')">
                                <i class="bi bi-check-all me-1"></i> Grant All
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-danger" onclick="setAllUserOverrides('deny')">
                                <i class="bi bi-dash-circle me-1"></i> Deny All
                            </button>
                        </div>
                    </div>

                    <!-- Loading Spinner -->
                    <div id="ucp_loading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <div class="text-muted small mt-2">Loading permissions matrix...</div>
                    </div>

                    <!-- Table Container -->
                    <div id="ucp_table_container" class="table-responsive" style="display: none; max-height: 55vh; overflow-y: auto;">
                        <table class="table table-bordered table-sm align-middle mb-0" id="ucp_table" style="font-size: 0.82rem;">
                            <thead class="bg-light text-uppercase text-secondary" style="font-size: 0.72rem; position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th style="width: 28%;">Module / Feature</th>
                                    <th style="width: 16%;" class="text-center text-primary">View</th>
                                    <th style="width: 16%;" class="text-center text-success">Create (Add)</th>
                                    <th style="width: 16%;" class="text-center text-warning text-dark">Edit</th>
                                    <th style="width: 16%;" class="text-center text-danger">Delete</th>
                                    <th style="width: 8%;" class="text-center">Row Preset</th>
                                </tr>
                            </thead>
                            <tbody id="ucp_tbody">
                                <!-- Populated dynamically by openCustomUserPermissionsModal() -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer border-top px-4 py-3 bg-light d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">
                        <i class="bi bi-check2-circle me-1"></i> Save Custom Permissions
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openRoleAssignModal(data) {
    if (!data) return;

    document.getElementById('assign_staff_id').value = data.id;
    document.getElementById('assign_staff_name').innerText = data.name;
    document.getElementById('assign_staff_code').innerText = data.staff_code;
    document.getElementById('assign_home_concern').innerText = data.concern_code + ' - ' + data.concern_name;
    document.getElementById('assign_email').value = data.email || '';
    document.getElementById('assign_password').value = '';

    const roleSelect = document.getElementById('assign_role_id');
    if (data.role_id) {
        roleSelect.value = data.role_id;
    } else {
        roleSelect.selectedIndex = 1;
    }

    // Reset all concern checkboxes
    document.querySelectorAll('.concern-check').forEach(cb => cb.checked = false);

    let assigned = data.assigned_cids || [];
    if (assigned.length === 0 && data.concern_id) {
        assigned = [parseInt(data.concern_id)];
    }

    assigned.forEach(cid => {
        const cb = document.getElementById('cid_check_' + cid);
        if (cb) cb.checked = true;
    });

    // Uncheck custom perms accordion by default
    const customToggle = document.getElementById('is_custom_perms');
    if (customToggle) customToggle.checked = false;
    const collapseEl = document.getElementById('collapseCustom');
    if (collapseEl && collapseEl.classList.contains('show')) {
        bootstrap.Collapse.getInstance(collapseEl)?.hide();
    }

    loadRolePermissionsForStaff(roleSelect.value);

    const modalEl = document.getElementById('modalAssignRole');
    if (modalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
}

function loadRolePermissionsForStaff(roleId) {
    if (!roleId) return;
    fetch('<?= \App\Core\Controller::url('/api/staff/role-permissions?role_id=') ?>' + roleId)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.permissions) {
                Object.keys(data.permissions).forEach(mid => {
                    const p = data.permissions[mid];
                    const v = document.querySelector('.custom-perm-view.custom-perm-' + mid);
                    const a = document.querySelector('.custom-perm-add.custom-perm-' + mid);
                    const e = document.querySelector('.custom-perm-edit.custom-perm-' + mid);
                    const d = document.querySelector('.custom-perm-delete.custom-perm-' + mid);
                    if (v) v.checked = !!p.view;
                    if (a) a.checked = !!p.add;
                    if (e) e.checked = !!p.edit;
                    if (d) d.checked = !!p.delete;
                });
            }
        })
        .catch(err => console.error('Error loading role permissions:', err));
}

function selectAllConcerns(check) {
    document.querySelectorAll('.concern-check').forEach(cb => cb.checked = check);
}

function setColumnPerm(action, checked) {
    document.querySelectorAll('.perm-' + action).forEach(cb => cb.checked = checked);
}

function setAllPerms(checked) {
    ['view', 'add', 'edit', 'delete'].forEach(act => {
        document.querySelectorAll('.perm-' + act).forEach(cb => cb.checked = checked);
    });
}

function setRowPerm(mid, checked) {
    document.querySelectorAll('.perm-check-' + mid).forEach(cb => cb.checked = checked);
}

function setRowReadOnly(mid) {
    document.querySelectorAll('.perm-check-' + mid).forEach(cb => {
        if (cb.classList.contains('perm-view')) {
            cb.checked = true;
        } else {
            cb.checked = false;
        }
    });
}

function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        }
    } else {
        input.type = 'password';
        if (icon) {
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
}

// =========================================================
// INDIVIDUAL USER GRANULAR PERMISSIONS MATRIX LOGIC
// =========================================================
let currentUcpData = null;

function openCustomUserPermissionsModal(staffId, userId, staffName) {
    const modalEl = document.getElementById('modalUserCustomPermissions');
    if (!modalEl) return;

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    document.getElementById('ucp_loading').style.display = 'block';
    document.getElementById('ucp_table_container').style.display = 'none';
    document.getElementById('ucp_staff_id').value = staffId || '';
    document.getElementById('ucp_user_id').value = userId || '';
    document.getElementById('ucp_staff_name').innerText = staffName || 'Staff Member';
    document.getElementById('ucp_staff_email').innerText = 'Loading...';
    document.getElementById('ucp_role_name').innerText = '...';

    const param = staffId ? ('staff_id=' + staffId) : ('user_id=' + userId);
    fetch('<?= \App\Core\Controller::url('/api/staff/user-permissions?') ?>' + param)
        .then(res => res.json())
        .then(data => {
            document.getElementById('ucp_loading').style.display = 'none';
            if (!data.success) {
                alert(data.error || 'Failed to fetch user permissions.');
                modal.hide();
                return;
            }

            if (!data.has_user_account) {
                alert(data.message || 'Staff member does not have an active login account.');
                modal.hide();
                return;
            }

            currentUcpData = data;
            document.getElementById('ucp_user_id').value = data.user.id;
            document.getElementById('ucp_staff_email').innerText = data.user.email;
            document.getElementById('ucp_role_name').innerText = data.user.role_name || 'Assigned Role';

            renderUserCustomPermsTable(data);
            document.getElementById('ucp_table_container').style.display = 'block';
        })
        .catch(err => {
            document.getElementById('ucp_loading').style.display = 'none';
            alert('Network error loading permissions: ' + err.message);
        });
}

function renderUserCustomPermsTable(data) {
    const tbody = document.getElementById('ucp_tbody');
    tbody.innerHTML = '';

    const modules = data.modules || [];
    const rolePerms = data.role_permissions || {};
    const userOverrides = data.user_overrides || {};

    // Group modules by category
    const grouped = {};
    modules.forEach(m => {
        const grp = m.group_name || 'General';
        if (!grouped[grp]) grouped[grp] = [];
        grouped[grp].push(m);
    });

    const actions = ['view', 'add', 'edit', 'delete'];

    Object.keys(grouped).forEach(grpName => {
        // Group Header Row
        const grpTr = document.createElement('tr');
        grpTr.className = 'table-secondary';
        grpTr.innerHTML = `
            <td colspan="6" class="fw-bold text-dark py-2 px-3 text-uppercase" style="font-size: 0.74rem; letter-spacing: 0.05em;">
                <i class="bi bi-folder2-open me-1.5 text-primary"></i> ${escapeHtml(grpName)} Group
            </td>
        `;
        tbody.appendChild(grpTr);

        grouped[grpName].forEach(m => {
            const mid = m.id;
            const rp = rolePerms[mid] || { view: 0, add: 0, edit: 0, delete: 0 };
            const uo = userOverrides[mid] || { view: null, add: null, edit: null, delete: null };

            const tr = document.createElement('tr');
            tr.id = 'ucp_row_' + mid;

            let actionsHtml = '';
            actions.forEach(act => {
                const roleDefault = rp[act] ? 1 : 0;
                const userVal = uo[act]; // null, 1, or 0
                const currentSetting = userVal === 1 ? 'grant' : (userVal === 0 ? 'deny' : 'inherit');

                const roleBadge = roleDefault === 1 
                    ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-0 px-1" style="font-size: 0.65rem;">Role: Allow</span>'
                    : '<span class="badge bg-secondary bg-opacity-10 text-secondary border py-0 px-1" style="font-size: 0.65rem;">Role: Deny</span>';

                actionsHtml += `
                    <td class="text-center p-1.5" style="vertical-align: middle;">
                        <div class="d-flex flex-column align-items-center gap-1">
                            <select name="overrides[${mid}][${act}]" 
                                    class="form-select form-select-xs ucp-select ucp-mid-${mid} ucp-act-${act}" 
                                    onchange="updateUcpRowVisual(this)"
                                    style="width: 110px; font-size: 0.75rem; border-radius: 6px;">
                                <option value="inherit" ${currentSetting === 'inherit' ? 'selected' : ''}>⚪ Inherit</option>
                                <option value="grant" ${currentSetting === 'grant' ? 'selected' : ''} class="text-success fw-bold">🟢 Grant</option>
                                <option value="deny" ${currentSetting === 'deny' ? 'selected' : ''} class="text-danger fw-bold">🔴 Deny</option>
                            </select>
                            <div class="mt-0.5">${roleBadge}</div>
                        </div>
                    </td>
                `;
            });

            tr.innerHTML = `
                <td class="ps-3 py-2">
                    <div class="fw-semibold text-dark">${escapeHtml(m.name)}</div>
                    <small class="text-muted font-monospace" style="font-size: 0.7rem;">${escapeHtml(m.code)}</small>
                </td>
                ${actionsHtml}
                <td class="text-center p-1" style="vertical-align: middle;">
                    <div class="btn-group btn-group-xs">
                        <button type="button" class="btn btn-outline-secondary btn-xs" title="Reset Row to Inherit" onclick="setRowOverride(${mid}, 'inherit')">Inh</button>
                        <button type="button" class="btn btn-outline-success btn-xs" title="Grant All 4 Operations" onclick="setRowOverride(${mid}, 'grant')">All</button>
                        <button type="button" class="btn btn-outline-danger btn-xs" title="Deny All 4 Operations" onclick="setRowOverride(${mid}, 'deny')">Block</button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    });

    // Apply visual styling to all selects
    document.querySelectorAll('.ucp-select').forEach(sel => updateUcpRowVisual(sel));
}

function updateUcpRowVisual(selectEl) {
    if (!selectEl) return;
    selectEl.classList.remove('border-success', 'bg-success', 'bg-opacity-10', 'border-danger', 'bg-danger', 'border-secondary');
    if (selectEl.value === 'grant') {
        selectEl.classList.add('border-success', 'bg-success', 'bg-opacity-10');
    } else if (selectEl.value === 'deny') {
        selectEl.classList.add('border-danger', 'bg-danger', 'bg-opacity-10');
    }
}

function setRowOverride(mid, val) {
    document.querySelectorAll('.ucp-mid-' + mid).forEach(sel => {
        sel.value = val;
        updateUcpRowVisual(sel);
    });
}

function setAllUserOverrides(val) {
    document.querySelectorAll('.ucp-select').forEach(sel => {
        sel.value = val;
        updateUcpRowVisual(sel);
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
</script>
