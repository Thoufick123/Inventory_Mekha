<?php
// app/views/concern/_staff_crud_modals.php - Shared Edit & View Staff Modals (with Module CRUD Permissions Matrix)
$staffReturnTo = $staffReturnTo ?? '';
$staffReturnConcernId = $staffReturnConcernId ?? '';
$defaultStaffCid = (int)($selectedCid ?? $concernFilter ?? 0);
?>
<!-- ========================================== -->
<!-- EDIT STAFF MODAL -->
<!-- ========================================== -->
<div class="modal fade" id="modalEditStaff" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <form action="<?= \App\Core\Controller::url('/m/staff/update') ?>" method="POST" class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
            <input type="hidden" name="id" id="edit_staff_id" value="">
            <input type="hidden" name="role_id" id="edit_staff_role_id" value="">
            <input type="hidden" name="has_perms_matrix" value="1">
            <?php if ($staffReturnTo !== ''): ?>
                <input type="hidden" name="return_to" value="<?= htmlspecialchars($staffReturnTo) ?>">
                <input type="hidden" name="return_concern_id" value="<?= htmlspecialchars((string)$staffReturnConcernId) ?>">
            <?php endif; ?>

            <div class="modal-header border-bottom px-4 py-3 bg-primary text-white">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-pencil-square fs-5"></i>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0">Edit Staff Member</h5>
                        <small class="text-white-50 font-monospace" id="edit_staff_code_display"></small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- 1. WHICH CONCERN -->
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                        <i class="bi bi-building me-1 text-primary"></i> Which Concern *
                    </label>
                    <select name="concern_id" id="edit_staff_concern_id" class="form-select" required>
                        <?php foreach ($allConcerns as $c): ?>
                            <option value="<?= $c['id'] ?>">
                                <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 2. FULL NAME & DESIGNATION -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                            <i class="bi bi-person me-1 text-primary"></i> Full Name *
                        </label>
                        <input type="text" name="name" id="edit_staff_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                            <i class="bi bi-briefcase me-1 text-primary"></i> Role Assign *
                        </label>
                        <input type="text" name="designation" id="edit_staff_designation" class="form-control" required>
                    </div>
                </div>

                <!-- 3. EMAIL & PASSWORD -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                            <i class="bi bi-envelope me-1 text-primary"></i> Email Address *
                        </label>
                        <input type="email" name="email" id="edit_staff_email" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                            <i class="bi bi-lock me-1 text-primary"></i> Password
                        </label>
                        <div class="input-group">
                            <input type="password" name="password" id="edit_staff_password" class="form-control border-end-0" placeholder="Leave blank to keep current">
                            <button class="btn btn-outline-secondary border-start-0" type="button" onclick="togglePasswordVisibility('edit_staff_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;">Current password is saved and active. Enter only if changing it.</small>
                    </div>
                </div>

                <!-- 4. PHONE NUMBER & ACTIVE / INACTIVE STATUS -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                            <i class="bi bi-telephone me-1 text-primary"></i> Phone Number *
                        </label>
                        <input type="text" name="phone" id="edit_staff_phone" class="form-control font-monospace" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                            <i class="bi bi-toggle-on me-1 text-primary"></i> Active / Inactive Status *
                        </label>
                        <select name="status" id="edit_staff_status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <!-- 5. MODULE ACCESS & GRANULAR PERMISSIONS MATRIX -->
                <div class="border-top pt-3 mt-4">
                    <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                <i class="bi bi-shield-lock-fill text-primary"></i> Module Access & Granular Permissions
                            </h6>
                            <small class="text-secondary">Configure View, Create (Add), Edit, and Delete access for each module.</small>
                        </div>
                        <span class="badge bg-white text-secondary border px-2.5 py-1" id="edit_matrix_status_badge" style="font-size: 0.74rem;">
                            <i class="bi bi-shield-check me-1 text-primary"></i> Module access loaded
                        </span>
                    </div>

                    <!-- Permissions Table -->
                    <div class="table-responsive rounded-3 border">
                        <table class="table table-bordered table-sm align-middle mb-0 perm-matrix-table" id="editStaffMatrixTable" style="font-size: 0.82rem;">
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
                                            <input type="checkbox" id="edit_header_check_view" class="form-check-input m-0" style="width: 18px; height: 18px; cursor: pointer;" onchange="toggleEditHeaderColumn('view', this.checked)" title="Select all View">
                                        </div>
                                    </th>
                                    <th style="width: 15%;" class="text-center text-success bg-success bg-opacity-10 border-end py-2">
                                        <div class="d-flex flex-column align-items-center justify-content-center gap-1">
                                            <div class="fw-bold">
                                                <i class="bi bi-plus-circle-fill me-1"></i> Create (Add)
                                            </div>
                                            <input type="checkbox" id="edit_header_check_add" class="form-check-input m-0" style="width: 18px; height: 18px; cursor: pointer;" onchange="toggleEditHeaderColumn('add', this.checked)" title="Select all Create">
                                        </div>
                                    </th>
                                    <th style="width: 15%;" class="text-center text-warning bg-warning bg-opacity-10 text-dark border-end py-2">
                                        <div class="d-flex flex-column align-items-center justify-content-center gap-1">
                                            <div class="fw-bold">
                                                <i class="bi bi-pencil-fill me-1"></i> Edit
                                            </div>
                                            <input type="checkbox" id="edit_header_check_edit" class="form-check-input m-0" style="width: 18px; height: 18px; cursor: pointer;" onchange="toggleEditHeaderColumn('edit', this.checked)" title="Select all Edit">
                                        </div>
                                    </th>
                                    <th style="width: 15%;" class="text-center text-danger bg-danger bg-opacity-10 border-end py-2">
                                        <div class="d-flex flex-column align-items-center justify-content-center gap-1">
                                            <div class="fw-bold">
                                                <i class="bi bi-trash-fill me-1"></i> Delete
                                            </div>
                                            <input type="checkbox" id="edit_header_check_delete" class="form-check-input m-0" style="width: 18px; height: 18px; cursor: pointer;" onchange="toggleEditHeaderColumn('delete', this.checked)" title="Select all Delete">
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($groupedModules)): 
                                    $modalGroupOrder = $groupOrder ?? ['Super Admin', 'Purchase', 'Production', 'Stock', 'Sales', 'Shop', 'Finance', 'Staff'];
                                    if (!in_array('Super Admin', $modalGroupOrder, true)) {
                                        array_unshift($modalGroupOrder, 'Super Admin');
                                    }
                                    foreach (array_keys($groupedModules) as $grpKey) {
                                        if (!in_array($grpKey, $modalGroupOrder, true)) {
                                            $modalGroupOrder[] = $grpKey;
                                        }
                                    }
                                    foreach ($modalGroupOrder as $grpName):
                                ?>
                                        <?php if (empty($groupedModules[$grpName])) continue; ?>
                                        <tr class="table-secondary matrix-group-header" data-group="<?= htmlspecialchars($grpName) ?>">
                                            <td colspan="5" class="fw-bold text-dark py-1.5 px-3 text-uppercase" style="font-size: 0.74rem; letter-spacing: 0.05em; background: #e2e8f0;">
                                                <i class="bi bi-folder2-open me-1.5 text-primary"></i> <?= htmlspecialchars($grpName) ?>
                                            </td>
                                        </tr>
                                        <?php foreach ($groupedModules[$grpName] as $m): $mid = (int)$m['id']; ?>
                                            <tr id="edit_matrix_row_<?= $mid ?>" class="matrix-mod-row" data-mid="<?= $mid ?>" data-group="<?= htmlspecialchars($grpName) ?>">
                                                <td class="ps-3 py-2">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <i class="bi <?= htmlspecialchars($m['icon'] ?: 'bi-app') ?> text-primary fs-6"></i>
                                                        <span class="fw-semibold text-dark" style="font-size: 0.84rem;"><?= htmlspecialchars($m['name']) ?></span>
                                                    </div>
                                                </td>
                                                <td class="text-center perm-matrix-col-view">
                                                    <div class="d-flex justify-content-center align-items-center m-0">
                                                        <input class="form-check-input edit-matrix-perm-check edit-matrix-perm-view edit-matrix-row-<?= $mid ?>" 
                                                               type="checkbox" name="perms[<?= $mid ?>][view]" value="1" id="edit_m_view_<?= $mid ?>"
                                                               onchange="onEditPermCheckboxChange(<?= $mid ?>, 'view', this.checked)">
                                                    </div>
                                                </td>
                                                <td class="text-center perm-matrix-col-add">
                                                    <div class="d-flex justify-content-center align-items-center m-0">
                                                        <input class="form-check-input edit-matrix-perm-check edit-matrix-perm-add edit-matrix-row-<?= $mid ?>" 
                                                               type="checkbox" name="perms[<?= $mid ?>][add]" value="1" id="edit_m_add_<?= $mid ?>"
                                                               onchange="onEditPermCheckboxChange(<?= $mid ?>, 'add', this.checked)">
                                                    </div>
                                                </td>
                                                <td class="text-center perm-matrix-col-edit">
                                                    <div class="d-flex justify-content-center align-items-center m-0">
                                                        <input class="form-check-input edit-matrix-perm-check edit-matrix-perm-edit edit-matrix-row-<?= $mid ?>" 
                                                               type="checkbox" name="perms[<?= $mid ?>][edit]" value="1" id="edit_m_edit_<?= $mid ?>"
                                                               onchange="onEditPermCheckboxChange(<?= $mid ?>, 'edit', this.checked)">
                                                    </div>
                                                </td>
                                                <td class="text-center perm-matrix-col-delete">
                                                    <div class="d-flex justify-content-center align-items-center m-0">
                                                        <input class="form-check-input edit-matrix-perm-check edit-matrix-perm-delete edit-matrix-row-<?= $mid ?>" 
                                                               type="checkbox" name="perms[<?= $mid ?>][delete]" value="1" id="edit_m_delete_<?= $mid ?>"
                                                               onchange="onEditPermCheckboxChange(<?= $mid ?>, 'delete', this.checked)">
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top px-4 py-3 bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold">
                    <i class="bi bi-check-circle me-1"></i> Update Staff Member
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- VIEW STAFF MODAL -->
<!-- ========================================== -->
<div class="modal fade" id="modalViewStaff" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom px-4 py-3 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span style="width: 38px; height: 38px; background: #e0f2fe; color: #0284c7; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="bi bi-person-badge"></i>
                    </span>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0" id="view_staff_name">-</h6>
                        <small class="badge bg-dark font-monospace" id="view_staff_code">-</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center p-3 rounded-3 mb-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                    <div>
                        <span class="text-secondary small d-block">Account Status</span>
                        <span class="badge" id="view_staff_status_badge">Active</span>
                    </div>
                    <div class="text-end">
                        <span class="text-secondary small d-block">Login Security</span>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                            <i class="bi bi-shield-check me-1"></i> Active Password
                        </span>
                    </div>
                </div>

                <ul class="list-group list-group-flush small">
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-building text-primary me-2"></i>Concern:</span>
                        <strong class="text-dark" id="view_staff_concern">-</strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-briefcase text-primary me-2"></i>Designation:</span>
                        <strong class="text-dark" id="view_staff_designation">-</strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-shield-check text-primary me-2"></i>Assigned Role:</span>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fw-semibold" id="view_staff_role">-</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-telephone text-primary me-2"></i>Mobile / Phone:</span>
                        <span class="font-monospace text-dark fw-semibold" id="view_staff_phone">-</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-envelope text-primary me-2"></i>Email:</span>
                        <span class="text-dark" id="view_staff_email">-</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-key text-primary me-2"></i>Password:</span>
                        <span class="text-secondary font-monospace">•••••••• (Encrypted in DB)</span>
                    </li>
                </ul>
            </div>
            <div class="modal-footer border-top px-4 py-2 bg-light d-flex justify-content-end">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
window.concernModules = window.concernModules || <?= json_encode($concernModules ?? []) ?>;

if (typeof window.togglePasswordVisibility === 'undefined') {
    window.togglePasswordVisibility = function(inputId, btn) {
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
    };
}

function openViewStaffModal(stf) {
    if (!stf) return;
    const setTxt = (id, val) => { const el = document.getElementById(id); if (el) el.innerText = val; };
    setTxt('view_staff_name', stf.name || '');
    setTxt('view_staff_code', stf.staff_code || '');
    setTxt('view_staff_concern', (stf.concern_name || 'Concern') + (stf.concern_code ? ' (' + stf.concern_code + ')' : ''));
    setTxt('view_staff_designation', stf.designation || '-');
    setTxt('view_staff_role', stf.role_name || 'None (Staff Only)');
    setTxt('view_staff_phone', stf.phone || '-');
    setTxt('view_staff_email', stf.email || stf.user_email || 'None');

    const badge = document.getElementById('view_staff_status_badge');
    if (badge) {
        if (stf.status === 'active') {
            badge.className = 'badge bg-success';
            badge.innerText = 'Active';
        } else {
            badge.className = 'badge bg-secondary';
            badge.innerText = 'Inactive';
        }
    }

    const modalEl = document.getElementById('modalViewStaff');
    if (modalEl) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
}

function onEditPermCheckboxChange(mid, act, checked) {
    if (checked && (act === 'add' || act === 'edit' || act === 'delete')) {
        const viewCb = document.getElementById('edit_m_view_' + mid);
        if (viewCb) viewCb.checked = true;
    }
    if (!checked && act === 'view') {
        ['add', 'edit', 'delete'].forEach(a => {
            const cb = document.getElementById('edit_m_' + a + '_' + mid);
            if (cb) cb.checked = false;
        });
    }
    updateEditHeaderCheckboxesState();
}

function toggleEditHeaderColumn(action, checked) {
    document.querySelectorAll('.edit-matrix-perm-' + action).forEach(cb => {
        const row = cb.closest('tr');
        if (row && row.style.display === 'none') return;
        cb.checked = checked;
        const mid = row ? row.getAttribute('data-mid') : null;
        if (checked && action !== 'view' && mid) {
            const vCb = document.getElementById('edit_m_view_' + mid);
            if (vCb) vCb.checked = true;
        }
    });
    if (!checked && action === 'view') {
        ['add', 'edit', 'delete'].forEach(act => {
            document.querySelectorAll('.edit-matrix-perm-' + act).forEach(cb => {
                const row = cb.closest('tr');
                if (row && row.style.display === 'none') return;
                cb.checked = false;
            });
            const h = document.getElementById('edit_header_check_' + act);
            if (h) { h.checked = false; h.indeterminate = false; }
        });
    }
    updateEditHeaderCheckboxesState();
}

function updateEditHeaderCheckboxesState() {
    ['view', 'add', 'edit', 'delete'].forEach(act => {
        const cbs = Array.from(document.querySelectorAll('.edit-matrix-perm-' + act)).filter(cb => {
            const row = cb.closest('tr');
            return row && row.style.display !== 'none';
        });
        const headerCb = document.getElementById('edit_header_check_' + act);
        if (!cbs.length || !headerCb) return;
        const checkedCount = cbs.filter(c => c.checked).length;
        if (checkedCount === cbs.length && cbs.length > 0) {
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
}

function openEditStaffModal(stf) {
    if (!stf) return;
    const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
    const setTxt = (id, val) => { const el = document.getElementById(id); if (el) el.innerText = val; };

    setVal('edit_staff_id', stf.id || '');
    setVal('edit_staff_role_id', stf.role_id || '');
    setTxt('edit_staff_code_display', stf.staff_code || '');
    setVal('edit_staff_concern_id', stf.concern_id || '');
    setVal('edit_staff_name', stf.name || '');
    setVal('edit_staff_email', stf.email || stf.user_email || '');
    setVal('edit_staff_password', '');
    setVal('edit_staff_phone', stf.phone || '');
    setVal('edit_staff_designation', stf.designation || '');
    setVal('edit_staff_status', stf.status === 'inactive' ? 'inactive' : 'active');

    const staffCid = parseInt(stf.concern_id) || <?= $defaultStaffCid ?>;
    const allowedMods = (window.concernModules && window.concernModules[staffCid]) ? window.concernModules[staffCid] : [];

    const groupCount = {};
    document.querySelectorAll('.matrix-mod-row').forEach(row => {
        const mid = parseInt(row.getAttribute('data-mid'));
        const grp = row.getAttribute('data-group');
        if (allowedMods.length > 0) {
            if (allowedMods.includes(mid)) {
                row.style.display = '';
                groupCount[grp] = (groupCount[grp] || 0) + 1;
            } else {
                row.style.display = 'none';
            }
        } else {
            row.style.display = '';
            groupCount[grp] = (groupCount[grp] || 0) + 1;
        }
    });

    document.querySelectorAll('.matrix-group-header').forEach(header => {
        const grp = header.getAttribute('data-group');
        header.style.display = (groupCount[grp] && groupCount[grp] > 0) ? '' : 'none';
    });

    document.querySelectorAll('.edit-matrix-perm-check').forEach(cb => cb.checked = false);
    updateEditHeaderCheckboxesState();

    const statusBadge = document.getElementById('edit_matrix_status_badge');
    if (statusBadge) {
        statusBadge.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" style="width: 0.8rem; height: 0.8rem;"></span> Loading permissions...';
    }

    const modalEl = document.getElementById('modalEditStaff');
    if (modalEl) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    if (stf.id) {
        fetch('<?= \App\Core\Controller::url('/api/staff/user-permissions') ?>?staff_id=' + encodeURIComponent(stf.id))
            .then(res => res.json())
            .then(data => {
                if (data && data.success && data.effective) {
                    Object.keys(data.effective).forEach(mid => {
                        const eff = data.effective[mid];
                        const vCb = document.getElementById('edit_m_view_' + mid);
                        const aCb = document.getElementById('edit_m_add_' + mid);
                        const eCb = document.getElementById('edit_m_edit_' + mid);
                        const dCb = document.getElementById('edit_m_delete_' + mid);

                        if (vCb) vCb.checked = !!(eff.view && eff.view.allowed);
                        if (aCb) aCb.checked = !!(eff.add && eff.add.allowed);
                        if (eCb) eCb.checked = !!(eff.edit && eff.edit.allowed);
                        if (dCb) dCb.checked = !!(eff.delete && eff.delete.allowed);
                    });
                    updateEditHeaderCheckboxesState();
                    if (statusBadge) {
                        statusBadge.innerHTML = '<i class="bi bi-shield-check me-1 text-success"></i> Permissions loaded for ' + (stf.name || 'staff');
                    }
                } else if (statusBadge) {
                    statusBadge.innerHTML = '<i class="bi bi-info-circle me-1 text-secondary"></i> Default role permissions active';
                }
            })
            .catch(err => {
                console.error('Failed to load staff permissions:', err);
                if (statusBadge) {
                    statusBadge.innerHTML = '<i class="bi bi-exclamation-triangle me-1 text-warning"></i> Error loading permissions';
                }
            });
    }
}
</script>
