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
                <h4 class="fw-bold mb-0 text-dark">Add New Staff Member</h4>
                <p class="text-secondary small mb-0">Register employee profile, business concern assignment, role permissions & system login credentials.</p>
            </div>
        </div>
        <div>
            <a href="<?= \App\Core\Controller::url('/m/staff' . ($selectedCid ? '?concern_id=' . $selectedCid : '')) ?>" class="btn btn-outline-secondary rounded-3 d-inline-flex align-items-center gap-1.5 shadow-sm fw-semibold">
                <i class="bi bi-arrow-left"></i> Back to All Staff
            </a>
        </div>
    </div>

    <!-- Main Registration Form Card -->
    <div class="row justify-content-center">
        <div class="col-12 col-lg-11 col-xl-10">
            <div class="card border-0 shadow-sm rounded-4 bg-white" style="border: 1px solid #e2e8f0 !important;">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-person-vcard text-primary fs-5"></i> Employee Details & Account Credentials
                    </h6>
                </div>

                <form action="<?= \App\Core\Controller::url('/m/staff/store') ?>" method="POST" class="card-body p-4" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

                    <!-- 1. BUSINESS CONCERN ASSIGNMENT -->
                    <div class="mb-4 p-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                        <input type="hidden" name="concern_id" id="staff_concern_id" value="<?= (int)($selectedCid ?: ($allConcerns[0]['id'] ?? 0)) ?>">
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                            <label class="small fw-bold text-dark text-uppercase mb-0 d-flex align-items-center gap-1.5" style="font-size: 0.78rem; letter-spacing: 0.05em;">
                                <i class="bi bi-buildings-fill text-primary"></i> 1. Authorized Business Concerns *
                            </label>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <div class="input-group input-group-sm" style="max-width: 220px;">
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
                                    $isPrimary = ($selectedCid ? ($selectedCid == $c['id']) : false); 
                                    $isChecked = !empty($oldInput['concern_ids']) 
                                        ? in_array((int)$c['id'], array_map('intval', (array)$oldInput['concern_ids']), true) 
                                        : $isPrimary;
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

                    <!-- 2. FULL NAME & DESIGNATION -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.74rem;">
                                <i class="bi bi-person me-1 text-primary"></i> Full Name *
                            </label>
                            <input type="text" name="name" id="staff_create_name" class="form-control" value="<?= htmlspecialchars($oldInput['name'] ?? '') ?>" required autocomplete="one-time-code" readonly onfocus="this.removeAttribute('readonly');" onmouseover="this.removeAttribute('readonly');" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.74rem;">
                                <i class="bi bi-briefcase me-1 text-primary"></i> Designation / Title *
                            </label>
                            <input type="text" name="designation" id="staff_create_designation" class="form-control" value="<?= htmlspecialchars($oldInput['designation'] ?? '') ?>" required autocomplete="off" style="border-radius: 8px;">
                        </div>
                    </div>



                    <!-- 4. EMAIL & LOGIN PASSWORD -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.74rem;">
                                <i class="bi bi-envelope me-1 text-primary"></i> Email Address (Login ID) *
                            </label>
                            <input type="email" name="email" id="staff_create_email" class="form-control" value="<?= htmlspecialchars($oldInput['email'] ?? '') ?>" required autocomplete="one-time-code" readonly onfocus="this.removeAttribute('readonly');" onmouseover="this.removeAttribute('readonly');" style="border-radius: 8px;">
                            <small class="text-muted" style="font-size: 0.72rem;">Used for user authentication into the ERP.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.74rem;">
                                <i class="bi bi-lock me-1 text-primary"></i> Password *
                            </label>
                            <div class="input-group">
                                <input type="password" name="password" id="staff_create_password" class="form-control border-end-0" value="<?= htmlspecialchars($oldInput['password'] ?? '') ?>" required autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly');" onmouseover="this.removeAttribute('readonly');" style="border-radius: 8px 0 0 8px;">
                                <button class="btn btn-outline-secondary border-start-0" type="button" onclick="toggleCreatePassword(this)" style="border-radius: 0 8px 8px 0;">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted" style="font-size: 0.72rem;">Minimum 6 characters recommended.</small>
                        </div>
                    </div>

                    <!-- 5. PHONE NUMBER & STATUS -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.74rem;">
                                <i class="bi bi-telephone me-1 text-primary"></i> Mobile / Phone Number *
                            </label>
                            <input type="tel" name="phone" id="staff_create_phone" maxlength="10" class="form-control font-monospace" value="<?= htmlspecialchars($oldInput['phone'] ?? '') ?>" required autocomplete="one-time-code" readonly onfocus="this.removeAttribute('readonly');" onmouseover="this.removeAttribute('readonly');" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.74rem;">
                                <i class="bi bi-toggle-on me-1 text-primary"></i> Employment Status *
                            </label>
                            <select name="status" class="form-select" required style="border-radius: 8px;">
                                <option value="active" <?= ($oldInput['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active (Can login & operate)</option>
                                <option value="inactive" <?= ($oldInput['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive (Disabled)</option>
                            </select>
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

function selectAllCreateConcerns(check) {
    document.querySelectorAll('.create-concern-check').forEach(cb => {
        const col = cb.closest('.col-md-6');
        if (col && col.style.display !== 'none') {
            cb.checked = check;
        }
    });
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
            staffForm.querySelectorAll('[readonly]').forEach(el => el.removeAttribute('readonly'));
        });
    }
});
</script>
