<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Concern & Tenant Management</h4>
            <p class="text-secondary small mb-0">Create new business concerns, toggle licensed modules, and configure tenant isolation.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCreateConcern">
                <i class="bi bi-plus-circle me-1"></i> Create New Concern
            </button>
        </div>
    </div>

    <!-- Export Intelligence Toolbar -->
    <?php
    $exportTitle = 'Export Concern Directory (Excel & PDF)';
    $exportSubtitle = 'Download registered enterprise business units, tenant configurations & licensed modules';
    $exportUrl = \App\Core\Controller::url('/admin/concerns/export');
    $prefix = 'concerns_exp';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <!-- Concerns List Card -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-buildings me-2 text-primary"></i> Active Concerns Registry</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Concern Information</th>
                        <th>Contact / GSTIN</th>
                        <th>Active Modules</th>
                        <th>Catalog Items</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($concerns as $c): ?>
                        <tr>
                            <td>
                                <span class="badge bg-dark font-monospace fs-6"><?= htmlspecialchars($c['code']) ?></span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($c['name']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($c['address'] ?: 'No address specified') ?></div>
                            </td>
                            <td>
                                <div><i class="bi bi-telephone text-muted me-1"></i> <?= htmlspecialchars($c['phone'] ?: '-') ?></div>
                                <div class="small text-muted"><i class="bi bi-receipt text-muted me-1"></i> <?= htmlspecialchars($c['gstin'] ?: 'Unregistered') ?></div>
                            </td>
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                    <i class="bi bi-check2-circle me-1"></i> <?= (int)$c['active_modules_count'] ?> Active
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-secondary border">
                                    <?= (int)$c['variants_count'] ?> Variants
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $c['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= ucfirst($c['status']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick='openViewConcernModal(<?= json_encode($c, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Concern Details">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick='openEditConcernModal(<?= json_encode($c, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Concern Profile">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="<?= \App\Core\Controller::url('/admin/concerns/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Permanently delete concern <?= htmlspecialchars(addslashes($c['name'])) ?> and all its associated data?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Concern">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        <!-- Module Toggle Modal for this concern -->
                        <div class="modal fade" id="modalModules<?= $c['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title fw-bold">Manage Modules: <?= htmlspecialchars($c['name']) ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="text-secondary small mb-3">
                                            Toggle modules on/off for this concern. Disabled modules will immediately return 403 Forbidden to concern users and disappear from their sidebar.
                                        </p>
                                        
                                        <?php
                                        $cmRows = \App\Core\DB::fetchAll(
                                            "SELECT m.id, m.code, m.name, m.group_name, m.icon, COALESCE(cm.enabled, 0) as is_enabled
                                             FROM modules m
                                             LEFT JOIN concern_modules cm ON m.id = cm.module_id AND cm.concern_id = ?
                                             WHERE m.is_super_admin_only = 0
                                             ORDER BY m.sort_order ASC",
                                            [$c['id']]
                                        );
                                        $groupedCM = [];
                                        foreach ($cmRows as $r) {
                                            $groupedCM[$r['group_name']][] = $r;
                                        }
                                        ?>

                                        <div class="row g-3">
                                            <?php foreach ($groupedCM as $grp => $items): ?>
                                                <div class="col-md-6">
                                                    <div class="p-3 border rounded bg-light">
                                                        <h6 class="fw-bold text-dark mb-2"><?= htmlspecialchars($grp) ?></h6>
                                                        <?php foreach ($items as $item): ?>
                                                            <div class="form-check form-switch mb-2">
                                                                <input class="form-check-input module-toggle" type="checkbox" 
                                                                       id="mod_<?= $c['id'] ?>_<?= $item['id'] ?>"
                                                                       data-concern-id="<?= $c['id'] ?>"
                                                                       data-module-id="<?= $item['id'] ?>"
                                                                       <?= $item['is_enabled'] ? 'checked' : '' ?>>
                                                                <label class="form-check-label small fw-medium" for="mod_<?= $c['id'] ?>_<?= $item['id'] ?>">
                                                                    <i class="bi <?= htmlspecialchars($item['icon']) ?> me-1 text-primary"></i>
                                                                    <?= htmlspecialchars($item['name']) ?>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Done</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- CREATE CONCERN GUIDED MODAL -->
<div class="modal fade" id="modalCreateConcern" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <form action="<?= \App\Core\Controller::url('/admin/concerns/store') ?>" method="POST" class="modal-content" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-building-add me-2"></i> Create New Concern</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <div class="row g-4">
                    <!-- Column 1: Details & Initial Admin -->
                    <div class="col-lg-5 border-end">
                        <h6 class="fw-bold text-primary mb-3"><i class="bi bi-info-circle me-1"></i> Step 1: Concern Profile</h6>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Concern Name *</label>
                            <input type="text" name="name" id="newConcernName" class="form-control" minlength="2" maxlength="150" autocomplete="off" required>
                            <div class="invalid-feedback" id="newConcernNameFeedback">A business concern with this name already exists. Concern names must be unique.</div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Concern Code *</label>
                                <input type="text" name="code" id="newConcernCode" class="form-control text-uppercase" readonly required aria-describedby="newConcernCodeHelp">
                                <div class="form-text" id="newConcernCodeHelp">Generated automatically from the concern name.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">GSTIN <span class="text-muted fw-normal">(Optional)</span></label>
                                <input type="text" name="gstin" id="newConcernGstin" class="form-control text-uppercase font-monospace" maxlength="15" placeholder="15-character GSTIN (optional)" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 15);" autocomplete="off" title="Optional 15-character alphanumeric GSTIN">
                                <div class="invalid-feedback" id="newConcernGstinFeedback"></div>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Phone *</label>
                                <input type="tel" name="phone" id="newConcernPhone" class="form-control" maxlength="13" pattern="^(\+?91)?[6-9][0-9]{9}$" placeholder="10 digits (e.g. 9876543210 or +91...)" oninput="this.value = this.value.replace(/[^0-9+]/g, '').slice(0, 13);" autocomplete="off" title="Phone number must be 10 digits starting with 6, 7, 8, or 9 (or prefixed with +91/91)" required>
                                <div class="invalid-feedback" id="newConcernPhoneFeedback">Phone must be 10 digits starting with 6, 7, 8, or 9 (or +91).</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Registered Address *</label>
                            <textarea name="address" id="newConcernAddress" class="form-control" rows="2" maxlength="1000" minlength="5" placeholder="Complete registered business address" autocomplete="off" required></textarea>
                            <div class="invalid-feedback" id="newConcernAddressFeedback">Registered physical address is required (minimum 5 characters).</div>
                        </div>
                    </div>

                    <!-- Column 2: Module Picker -->
                    <div class="col-lg-7">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold text-primary mb-0"><i class="bi bi-grid-3x3-gap me-1"></i> Step 2: Module Picker Checklist</h6>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllModules(true)">Select All</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAllModules(false)">Deselect All</button>
                            </div>
                        </div>
                        <div class="alert alert-danger py-2 d-none" id="newConcernModuleError" role="alert">Select at least one module.</div>

                        <?php
                        $groupedAll = [];
                        foreach ($allModules as $mod) {
                            if ($mod['code'] === 'concern_dashboard' || $mod['group_name'] === 'Dashboard' || $mod['code'] === 'cash') {
                                continue;
                            }
                            $groupName = $mod['code'] === 'catalog_mapping' ? 'Purchase' : $mod['group_name'];
                            $groupedAll[$groupName][] = $mod;
                        }
                        ?>

                        <div class="row g-3" style="max-height: 520px; overflow-y: auto;">
                            <?php foreach ($groupedAll as $grp => $mods): 
                                $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $grp));
                            ?>
                                <div class="col-md-6">
                                    <div class="card border h-100 shadow-sm">
                                        <div class="card-header bg-light py-2 px-3 d-flex align-items-center justify-content-between">
                                            <div class="form-check mb-0 d-flex align-items-center">
                                                <input class="form-check-input group-master-check me-2" type="checkbox" id="grp_chk_<?= $slug ?>" onchange="toggleGroupCheck('<?= $slug ?>', this.checked)">
                                                <label class="form-check-label fw-bold small text-dark user-select-none" for="grp_chk_<?= $slug ?>" style="cursor: pointer;">
                                                    <?= htmlspecialchars($grp) ?>
                                                </label>
                                            </div>
                                            <span class="badge bg-white text-secondary border small fw-normal px-2"><?= count($mods) ?></span>
                                        </div>
                                        <div class="card-body p-2 ps-3">
                                            <?php foreach ($mods as $m): ?>
                                                <div class="form-check mb-1">
                                                    <input class="form-check-input module-check group-mod-<?= $slug ?>" type="checkbox" name="modules[]" value="<?= $m['id'] ?>" id="new_mod_<?= $m['id'] ?>" data-group="<?= $slug ?>" onchange="syncGroupMasterCheck('<?= $slug ?>')">
                                                    <label class="form-check-label small" for="new_mod_<?= $m['id'] ?>">
                                                        <i class="bi <?= htmlspecialchars($m['icon']) ?> text-muted me-1"></i>
                                                        <?= htmlspecialchars($m['name']) ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4 fw-semibold">
                    <i class="bi bi-check-lg me-1"></i> Create Concern & Provision Workspace
                </button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT CONCERN MODAL -->
<div class="modal fade" id="modalEditConcern" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <form action="<?= \App\Core\Controller::url('/admin/concerns/update') ?>" method="POST" class="modal-content" id="editConcernForm" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" id="edit_concern_id" value="">
            <input type="hidden" name="modules_present" value="1">

            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2 text-warning"></i> Edit Business Concern</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <div class="row g-4">
                    <!-- Column 1: Concern Profile -->
                    <div class="col-lg-5 border-end pe-lg-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="bi bi-info-circle me-1"></i> Step 1: Concern Profile</h6>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Concern Name *</label>
                            <input type="text" name="name" id="edit_concern_name" class="form-control" required maxlength="150" autocomplete="off">
                            <div class="invalid-feedback" id="editConcernNameFeedback">A business concern with this name already exists. Concern names must be unique.</div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Concern Code *</label>
                                <input type="text" name="code" id="edit_concern_code" class="form-control text-uppercase font-monospace" required maxlength="20" autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">GSTIN <span class="text-muted fw-normal">(Optional)</span></label>
                                <input type="text" name="gstin" id="edit_concern_gstin" class="form-control text-uppercase font-monospace" maxlength="15" placeholder="15-character GSTIN (optional)" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 15);" autocomplete="off" title="Optional 15-character alphanumeric GSTIN">
                                <div class="invalid-feedback" id="editConcernGstinFeedback"></div>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Phone *</label>
                                <input type="tel" name="phone" id="edit_concern_phone" class="form-control" maxlength="13" pattern="^(\+?91)?[6-9][0-9]{9}$" placeholder="10 digits (e.g. 9876543210 or +91...)" oninput="this.value = this.value.replace(/[^0-9+]/g, '').slice(0, 13);" autocomplete="off" title="Phone number must be 10 digits starting with 6, 7, 8, or 9 (or prefixed with +91/91)" required>
                                <div class="invalid-feedback" id="editConcernPhoneFeedback">Phone must be 10 digits starting with 6, 7, 8, or 9 (or +91).</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Operating Status *</label>
                            <select name="status" id="edit_concern_status" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Registered Physical Address *</label>
                            <textarea name="address" id="edit_concern_address" class="form-control" rows="3" maxlength="1000" minlength="5" autocomplete="off" placeholder="Complete registered address" required></textarea>
                            <div class="invalid-feedback" id="editConcernAddressFeedback">Registered physical address is required (minimum 5 characters).</div>
                        </div>
                    </div>

                    <!-- Column 2: Module Picker Checklist -->
                    <div class="col-lg-7">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold text-primary mb-0"><i class="bi bi-grid-3x3-gap me-1"></i> Step 2: Module Picker Checklist</h6>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllEditModules(true)">Select All</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAllEditModules(false)">Deselect All</button>
                            </div>
                        </div>
                        <div class="alert alert-danger py-2 d-none" id="editConcernModuleError" role="alert">Select at least one module.</div>

                        <div class="row g-3" style="max-height: 520px; overflow-y: auto;">
                            <?php foreach ($groupedAll as $grp => $mods): 
                                $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $grp));
                            ?>
                                <div class="col-md-6">
                                    <div class="card border h-100 shadow-sm">
                                        <div class="card-header bg-light py-2 px-3 d-flex align-items-center justify-content-between">
                                            <div class="form-check mb-0 d-flex align-items-center">
                                                <input class="form-check-input edit-group-master-check me-2" type="checkbox" id="edit_grp_chk_<?= $slug ?>" data-group="<?= $slug ?>" onchange="toggleEditGroupCheck('<?= $slug ?>', this.checked)">
                                                <label class="form-check-label fw-bold small text-dark user-select-none" for="edit_grp_chk_<?= $slug ?>" style="cursor: pointer;">
                                                    <?= htmlspecialchars($grp) ?>
                                                </label>
                                            </div>
                                            <span class="badge bg-white text-secondary border small fw-normal px-2"><?= count($mods) ?></span>
                                        </div>
                                        <div class="card-body p-2 ps-3">
                                            <?php foreach ($mods as $m): ?>
                                                <div class="form-check mb-1">
                                                    <input class="form-check-input edit-module-check edit-group-mod-<?= $slug ?>" type="checkbox" name="modules[]" value="<?= $m['id'] ?>" id="edit_mod_<?= $m['id'] ?>" data-group="<?= $slug ?>" onchange="syncEditGroupMasterCheck('<?= $slug ?>')">
                                                    <label class="form-check-label small" for="edit_mod_<?= $m['id'] ?>">
                                                        <i class="bi <?= htmlspecialchars($m['icon']) ?> text-muted me-1"></i>
                                                        <?= htmlspecialchars($m['name']) ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4 fw-semibold">
                    <i class="bi bi-check-lg me-1"></i> Update Concern & Modules
                </button>
            </div>
        </form>
    </div>
</div>

<!-- VIEW CONCERN PROFILE MODAL -->
<div class="modal fade" id="modalViewConcern" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom px-4 py-3 bg-light d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span style="width: 40px; height: 40px; background: #e0f2fe; color: #0284c7; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                        <i class="bi bi-building"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="view_concern_name">Concern Details</h5>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <span class="badge bg-dark font-monospace" id="view_concern_code"></span>
                            <span class="badge" id="view_concern_status_badge">Active</span>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <div class="row g-4">
                    <!-- Left: Profile & Statistics -->
                    <div class="col-lg-5 border-end pe-lg-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="bi bi-info-circle me-1"></i> Concern Business Profile</h6>
                        
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="p-3 rounded-3 bg-light border text-center">
                                    <span class="text-secondary small d-block">Catalog SKUs</span>
                                    <span class="fw-bold fs-5 text-dark font-monospace" id="view_concern_variants">0</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 rounded-3 bg-light border text-center">
                                    <span class="text-secondary small d-block">Assigned Users</span>
                                    <span class="fw-bold fs-5 text-dark font-monospace" id="view_concern_users">0</span>
                                </div>
                            </div>
                        </div>

                        <ul class="list-group list-group-flush small mb-3">
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span class="text-muted"><i class="bi bi-receipt text-primary me-2"></i>GSTIN / Tax ID:</span>
                                <span class="badge bg-light text-dark border font-monospace fs-7" id="view_concern_gstin">Unregistered</span>
                            </li>
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span class="text-muted"><i class="bi bi-telephone text-primary me-2"></i>Primary Phone:</span>
                                <strong class="text-dark font-monospace" id="view_concern_phone">-</strong>
                            </li>
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span class="text-muted"><i class="bi bi-check2-circle text-primary me-2"></i>Active Modules:</span>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" id="view_concern_modules">0 Modules</span>
                            </li>
                            <li class="list-group-item px-0 pt-2">
                                <span class="text-muted d-block mb-1"><i class="bi bi-geo-alt text-primary me-2"></i>Registered Physical Address:</span>
                                <div class="text-dark p-2 bg-light rounded border small" id="view_concern_address">-</div>
                            </li>
                        </ul>
                    </div>

                    <!-- Right: Provisioned Modules -->
                    <div class="col-lg-7">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold text-primary mb-0"><i class="bi bi-grid-3x3-gap me-1"></i> Provisioned Modules</h6>
                            <span class="text-muted small">Status for each module</span>
                        </div>

                        <div class="row g-3" style="max-height: 480px; overflow-y: auto;">
                            <?php foreach ($groupedAll as $grp => $mods): ?>
                                <div class="col-md-6">
                                    <div class="card border h-100 shadow-sm">
                                        <div class="card-header bg-light py-2 px-3 fw-bold small text-dark d-flex justify-content-between align-items-center">
                                            <span><?= htmlspecialchars($grp) ?></span>
                                            <span class="badge bg-white text-secondary border small fw-normal"><?= count($mods) ?></span>
                                        </div>
                                        <div class="card-body p-2">
                                            <?php foreach ($mods as $m): ?>
                                                <div class="d-flex align-items-center justify-content-between p-1 px-2 rounded mb-1 view-module-item" data-module-id="<?= $m['id'] ?>">
                                                    <span class="small">
                                                        <i class="bi <?= htmlspecialchars($m['icon']) ?> me-1"></i>
                                                        <?= htmlspecialchars($m['name']) ?>
                                                    </span>
                                                    <span class="view-mod-badge badge bg-light text-muted border">Off</span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-top px-4 py-2 bg-light d-flex justify-content-end align-items-center">
                <button type="button" class="btn btn-sm btn-secondary px-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
const existingConcerns = <?= json_encode(array_map(function($c) {
    return [
        'id' => (int)$c['id'],
        'name' => trim((string)$c['name'])
    ];
}, $concerns)) ?>;

const createConcernModal = document.getElementById('modalCreateConcern');
const createConcernForm = createConcernModal ? createConcernModal.querySelector('form') : null;
const newConcernName = document.getElementById('newConcernName');
const newConcernNameFb = document.getElementById('newConcernNameFeedback');
const newConcernCode = document.getElementById('newConcernCode');
const moduleSelectionError = document.getElementById('newConcernModuleError');

function validateConcernName(inputEl, feedbackEl, excludeId = null) {
    if (!inputEl) return true;
    const rawVal = inputEl.value.trim();
    if (!rawVal) {
        inputEl.classList.remove('is-invalid');
        inputEl.setCustomValidity('');
        if (feedbackEl) feedbackEl.textContent = '';
        return true;
    }
    const checkVal = rawVal.toLowerCase();
    const isDuplicate = existingConcerns.some(c => {
        if (excludeId !== null && Number(c.id) === Number(excludeId)) {
            return false;
        }
        return (c.name || '').trim().toLowerCase() === checkVal;
    });

    if (isDuplicate) {
        const errorMsg = 'A business concern named "' + rawVal + '" already exists. Concern names must be unique.';
        inputEl.classList.add('is-invalid');
        inputEl.setCustomValidity(errorMsg);
        if (feedbackEl) feedbackEl.textContent = errorMsg;
        return false;
    }

    inputEl.classList.remove('is-invalid');
    inputEl.setCustomValidity('');
    if (feedbackEl) feedbackEl.textContent = '';
    return true;
}

function concernCodePreview(name) {
    const words = String(name).toUpperCase().match(/[A-Z0-9]+/g) || [];
    let token = words.slice(0, 3).map(word => word.charAt(0)).join('');
    if (token.length < 2) token = words.join('').slice(0, 3);
    if (!token && String(name).trim()) token = 'NEW';
    return token ? `CON-${token}` : '';
}

if (newConcernName && newConcernCode) {
    newConcernName.addEventListener('input', () => {
        newConcernCode.value = concernCodePreview(newConcernName.value);
        validateConcernName(newConcernName, newConcernNameFb);
    });
    newConcernName.addEventListener('blur', () => {
        validateConcernName(newConcernName, newConcernNameFb);
    });
}

function validateConcernPhone(inputEl, feedbackEl, isFinalCheck = false) {
    if (!inputEl) return true;
    const val = inputEl.value.trim();
    if (!val) {
        if (isFinalCheck) {
            const errorMsg = 'Phone number is required (10 digits starting with 6, 7, 8, or 9, or prefixed with +91).';
            inputEl.classList.add('is-invalid');
            inputEl.setCustomValidity(errorMsg);
            if (feedbackEl) feedbackEl.textContent = errorMsg;
            return false;
        }
        inputEl.classList.remove('is-invalid');
        inputEl.setCustomValidity('');
        if (feedbackEl) feedbackEl.textContent = '';
        return true;
    }

    const clean = val.replace(/^\+/, '');
    let mobileDigits = clean;
    if (clean.length > 10 && clean.startsWith('91')) {
        mobileDigits = clean.substring(2);
    }
    const firstDigit = mobileDigits.charAt(0);

    // Rule 1: Mobile part must start with 6, 7, 8, or 9
    if (firstDigit && !['6', '7', '8', '9'].includes(firstDigit)) {
        const errorMsg = 'Phone number must start with 6, 7, 8, or 9. Numbers starting with ' + firstDigit + ' are not allowed.';
        inputEl.classList.add('is-invalid');
        inputEl.setCustomValidity(errorMsg);
        if (feedbackEl) feedbackEl.textContent = errorMsg;
        return false;
    }

    // Rule 2: Must be exactly 10 digits (or 12 digits only if prefixed with 91)
    if (isFinalCheck || clean.length >= 10) {
        const isValid = (clean.length === 10) || (clean.length === 12 && clean.startsWith('91'));
        if (!isValid) {
            let errorMsg;
            if (clean.length === 12 && !clean.startsWith('91')) {
                errorMsg = '12-digit phone numbers must begin with country code 91 or +91.';
            } else {
                errorMsg = 'Phone number must be exactly 10 digits (or prefixed with +91). Currently ' + clean.length + ' digits.';
            }
            inputEl.classList.add('is-invalid');
            inputEl.setCustomValidity(errorMsg);
            if (feedbackEl) feedbackEl.textContent = errorMsg;
            return false;
        }
    }

    inputEl.classList.remove('is-invalid');
    inputEl.setCustomValidity('');
    if (feedbackEl) feedbackEl.textContent = '';
    return true;
}

function validateConcernGstin(inputEl, feedbackEl, isFinalCheck = false) {
    if (!inputEl) return true;
    const val = inputEl.value.trim().toUpperCase();
    if (!val) {
        inputEl.classList.remove('is-invalid');
        inputEl.setCustomValidity('');
        if (feedbackEl) feedbackEl.textContent = '';
        return true;
    }
    if (!/^[0-9A-Z]{15}$/.test(val)) {
        const errorMsg = 'GSTIN must be exactly 15 alphanumeric characters (e.g. 22AAAAA0000A1Z5).';
        inputEl.classList.add('is-invalid');
        inputEl.setCustomValidity(errorMsg);
        if (feedbackEl) feedbackEl.textContent = errorMsg;
        return false;
    }
    inputEl.classList.remove('is-invalid');
    inputEl.setCustomValidity('');
    if (feedbackEl) feedbackEl.textContent = '';
    return true;
}

function validateConcernAddress(inputEl, feedbackEl, isFinalCheck = false) {
    if (!inputEl) return true;
    const val = inputEl.value.trim();
    if (val.length < 5) {
        if (isFinalCheck || val.length > 0) {
            const errorMsg = 'Registered physical address is required (minimum 5 characters).';
            inputEl.classList.add('is-invalid');
            inputEl.setCustomValidity(errorMsg);
            if (feedbackEl) feedbackEl.textContent = errorMsg;
            return false;
        }
    }
    inputEl.classList.remove('is-invalid');
    inputEl.setCustomValidity('');
    if (feedbackEl) feedbackEl.textContent = '';
    return true;
}

const newConcernPhone = document.getElementById('newConcernPhone');
const newConcernPhoneFb = document.getElementById('newConcernPhoneFeedback');
if (newConcernPhone) {
    newConcernPhone.addEventListener('input', () => validateConcernPhone(newConcernPhone, newConcernPhoneFb, false));
    newConcernPhone.addEventListener('blur', () => validateConcernPhone(newConcernPhone, newConcernPhoneFb, true));
}

const newConcernGstin = document.getElementById('newConcernGstin');
const newConcernGstinFb = document.getElementById('newConcernGstinFeedback');
if (newConcernGstin) {
    newConcernGstin.addEventListener('input', () => validateConcernGstin(newConcernGstin, newConcernGstinFb, false));
    newConcernGstin.addEventListener('blur', () => validateConcernGstin(newConcernGstin, newConcernGstinFb, true));
}

const newConcernAddress = document.getElementById('newConcernAddress');
const newConcernAddressFb = document.getElementById('newConcernAddressFeedback');
if (newConcernAddress) {
    newConcernAddress.addEventListener('input', () => validateConcernAddress(newConcernAddress, newConcernAddressFb, false));
    newConcernAddress.addEventListener('blur', () => validateConcernAddress(newConcernAddress, newConcernAddressFb, true));
}

if (createConcernModal && createConcernForm) {
    createConcernModal.addEventListener('show.bs.modal', () => {
        createConcernForm.reset();
        createConcernForm.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"])').forEach(field => {
            field.value = '';
            field.classList.remove('is-invalid');
            field.setCustomValidity('');
        });
        if (newConcernNameFb) newConcernNameFb.textContent = '';
        if (newConcernPhoneFb) newConcernPhoneFb.textContent = '';
        if (newConcernGstinFb) newConcernGstinFb.textContent = '';
        if (newConcernAddressFb) newConcernAddressFb.textContent = '';
        createConcernForm.querySelectorAll('textarea').forEach(field => {
            field.value = '';
            field.classList.remove('is-invalid');
            field.setCustomValidity('');
        });
        document.querySelectorAll('.group-master-check').forEach(cb => {
            cb.checked = false;
            cb.indeterminate = false;
        });
        if (moduleSelectionError) moduleSelectionError.classList.add('d-none');
    });

    createConcernForm.addEventListener('submit', event => {
        let isValid = true;

        if (!validateConcernName(newConcernName, newConcernNameFb)) {
            isValid = false;
            newConcernName.focus();
        }

        if (!validateConcernGstin(newConcernGstin, newConcernGstinFb, true)) {
            if (isValid) newConcernGstin.focus();
            isValid = false;
        }

        if (!validateConcernPhone(newConcernPhone, newConcernPhoneFb, true)) {
            if (isValid) newConcernPhone.focus();
            isValid = false;
        }

        if (!validateConcernAddress(newConcernAddress, newConcernAddressFb, true)) {
            if (isValid) newConcernAddress.focus();
            isValid = false;
        }

        const hasSelectedModule = createConcernForm.querySelector('.module-check:checked');
        if (!hasSelectedModule) {
            isValid = false;
            if (moduleSelectionError) {
                moduleSelectionError.classList.remove('d-none');
                moduleSelectionError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        if (!isValid) {
            event.preventDefault();
        }
    });

    createConcernForm.querySelectorAll('.module-check').forEach(checkbox => {
        checkbox.addEventListener('change', () => {
            if (createConcernForm.querySelector('.module-check:checked')) {
                if (moduleSelectionError) moduleSelectionError.classList.add('d-none');
            }
            if (checkbox.dataset.group) {
                syncGroupMasterCheck(checkbox.dataset.group);
            }
        });
    });
}

function toggleGroupCheck(groupSlug, isChecked) {
    const checkboxes = document.querySelectorAll('.group-mod-' + groupSlug);
    checkboxes.forEach(cb => {
        cb.checked = isChecked;
    });
    if (moduleSelectionError && isChecked) {
        moduleSelectionError.classList.add('d-none');
    }
}

function syncGroupMasterCheck(groupSlug) {
    const groupMaster = document.getElementById('grp_chk_' + groupSlug);
    if (!groupMaster) return;
    const checkboxes = document.querySelectorAll('.group-mod-' + groupSlug);
    if (!checkboxes.length) return;
    
    let checkedCount = 0;
    checkboxes.forEach(cb => {
        if (cb.checked) checkedCount++;
    });
    
    if (checkedCount === 0) {
        groupMaster.checked = false;
        groupMaster.indeterminate = false;
    } else if (checkedCount === checkboxes.length) {
        groupMaster.checked = true;
        groupMaster.indeterminate = false;
    } else {
        groupMaster.checked = false;
        groupMaster.indeterminate = true;
    }
}

function selectAllModules(check) {
    document.querySelectorAll('.module-check').forEach(cb => cb.checked = check);
    document.querySelectorAll('.group-master-check').forEach(cb => {
        cb.checked = check;
        cb.indeterminate = false;
    });
    if (moduleSelectionError) {
        moduleSelectionError.classList.toggle('d-none', check);
    }
}

function openViewConcernModal(con) {
    if (!con) return;
    document.getElementById('view_concern_name').innerText = con.name || 'Concern';
    document.getElementById('view_concern_code').innerText = con.code || '';
    document.getElementById('view_concern_phone').innerText = con.phone || '-';
    document.getElementById('view_concern_gstin').innerText = con.gstin || 'Unregistered';
    document.getElementById('view_concern_address').innerText = con.address || 'No address specified';
    document.getElementById('view_concern_variants').innerText = (con.variants_count || 0) + ' SKUs';
    document.getElementById('view_concern_modules').innerText = (con.active_modules_count || 0) + ' Active Modules';
    const usersEl = document.getElementById('view_concern_users');
    if (usersEl) usersEl.innerText = (con.users_count || 0);

    const statusBadge = document.getElementById('view_concern_status_badge');
    if (con.status === 'active') {
        statusBadge.className = 'badge bg-success';
        statusBadge.innerText = 'Active';
    } else {
        statusBadge.className = 'badge bg-secondary';
        statusBadge.innerText = 'Inactive';
    }

    // Provisioned modules checklist in View Modal
    const enabledIds = Array.isArray(con.enabled_module_ids) ? con.enabled_module_ids.map(Number) : [];
    document.querySelectorAll('.view-module-item').forEach(item => {
        const mid = Number(item.dataset.moduleId);
        const isEnabled = enabledIds.includes(mid);
        const iconBadge = item.querySelector('.view-mod-badge');
        if (isEnabled) {
            item.classList.remove('opacity-50', 'text-muted');
            item.classList.add('text-dark', 'fw-semibold', 'bg-light');
            if (iconBadge) {
                iconBadge.className = 'view-mod-badge badge bg-success-subtle text-success border border-success-subtle';
                iconBadge.innerHTML = '<i class="bi bi-check-lg"></i> Enabled';
            }
        } else {
            item.classList.add('opacity-50', 'text-muted');
            item.classList.remove('text-dark', 'fw-semibold', 'bg-light');
            if (iconBadge) {
                iconBadge.className = 'view-mod-badge badge bg-light text-muted border';
                iconBadge.innerHTML = '<i class="bi bi-x"></i> Off';
            }
        }
    });

    const modal = new bootstrap.Modal(document.getElementById('modalViewConcern'));
    modal.show();
}

function openEditConcernModal(con) {
    if (!con) return;
    document.getElementById('edit_concern_id').value = con.id || '';
    document.getElementById('edit_concern_name').value = con.name || '';
    document.getElementById('edit_concern_code').value = con.code || '';
    document.getElementById('edit_concern_phone').value = con.phone || '';
    document.getElementById('edit_concern_gstin').value = con.gstin || '';
    document.getElementById('edit_concern_address').value = con.address || '';
    document.getElementById('edit_concern_status').value = con.status || 'active';

    const editNameEl = document.getElementById('edit_concern_name');
    const editNameFb = document.getElementById('editConcernNameFeedback');
    if (editNameEl) {
        editNameEl.classList.remove('is-invalid');
        editNameEl.setCustomValidity('');
    }
    if (editNameFb) editNameFb.textContent = '';

    const editPhoneEl = document.getElementById('edit_concern_phone');
    const editPhoneFb = document.getElementById('editConcernPhoneFeedback');
    if (editPhoneEl) {
        editPhoneEl.classList.remove('is-invalid');
        editPhoneEl.setCustomValidity('');
    }
    if (editPhoneFb) editPhoneFb.textContent = '';

    const editGstinEl = document.getElementById('edit_concern_gstin');
    const editGstinFb = document.getElementById('editConcernGstinFeedback');
    if (editGstinEl) {
        editGstinEl.classList.remove('is-invalid');
        editGstinEl.setCustomValidity('');
    }
    if (editGstinFb) editGstinFb.textContent = '';

    const editAddressEl = document.getElementById('edit_concern_address');
    const editAddressFb = document.getElementById('editConcernAddressFeedback');
    if (editAddressEl) {
        editAddressEl.classList.remove('is-invalid');
        editAddressEl.setCustomValidity('');
    }
    if (editAddressFb) editAddressFb.textContent = '';

    const editModErr = document.getElementById('editConcernModuleError');
    if (editModErr) editModErr.classList.add('d-none');

    // Populate and check modules
    const enabledIds = Array.isArray(con.enabled_module_ids) ? con.enabled_module_ids.map(Number) : [];
    document.querySelectorAll('.edit-module-check').forEach(cb => {
        cb.checked = enabledIds.includes(Number(cb.value));
    });

    // Synchronize all edit group master checkboxes
    document.querySelectorAll('.edit-group-master-check').forEach(master => {
        const slug = master.dataset.group;
        if (slug) syncEditGroupMasterCheck(slug);
    });

    const modal = new bootstrap.Modal(document.getElementById('modalEditConcern'));
    modal.show();
}

function toggleEditGroupCheck(groupSlug, isChecked) {
    const checkboxes = document.querySelectorAll('.edit-group-mod-' + groupSlug);
    checkboxes.forEach(cb => {
        cb.checked = isChecked;
    });
    const editModErr = document.getElementById('editConcernModuleError');
    if (editModErr && isChecked) {
        editModErr.classList.add('d-none');
    }
}

function syncEditGroupMasterCheck(groupSlug) {
    const groupMaster = document.getElementById('edit_grp_chk_' + groupSlug);
    if (!groupMaster) return;
    const checkboxes = document.querySelectorAll('.edit-group-mod-' + groupSlug);
    if (!checkboxes.length) return;
    
    let checkedCount = 0;
    checkboxes.forEach(cb => {
        if (cb.checked) checkedCount++;
    });
    
    if (checkedCount === 0) {
        groupMaster.checked = false;
        groupMaster.indeterminate = false;
    } else if (checkedCount === checkboxes.length) {
        groupMaster.checked = true;
        groupMaster.indeterminate = false;
    } else {
        groupMaster.checked = false;
        groupMaster.indeterminate = true;
    }
}

function selectAllEditModules(check) {
    document.querySelectorAll('.edit-module-check').forEach(cb => cb.checked = check);
    document.querySelectorAll('.edit-group-master-check').forEach(cb => {
        cb.checked = check;
        cb.indeterminate = false;
    });
    const editModErr = document.getElementById('editConcernModuleError');
    if (editModErr) {
        editModErr.classList.toggle('d-none', check);
    }
}

const editConcernForm = document.getElementById('editConcernForm');
const editConcernModuleError = document.getElementById('editConcernModuleError');
const editConcernName = document.getElementById('edit_concern_name');
const editConcernNameFb = document.getElementById('editConcernNameFeedback');
const editConcernId = document.getElementById('edit_concern_id');
const editConcernPhone = document.getElementById('edit_concern_phone');
const editConcernPhoneFb = document.getElementById('editConcernPhoneFeedback');
const editConcernGstin = document.getElementById('edit_concern_gstin');
const editConcernGstinFb = document.getElementById('editConcernGstinFeedback');
const editConcernAddress = document.getElementById('edit_concern_address');
const editConcernAddressFb = document.getElementById('editConcernAddressFeedback');

if (editConcernName) {
    editConcernName.addEventListener('input', () => {
        validateConcernName(editConcernName, editConcernNameFb, editConcernId ? editConcernId.value : null);
    });
    editConcernName.addEventListener('blur', () => {
        validateConcernName(editConcernName, editConcernNameFb, editConcernId ? editConcernId.value : null);
    });
}

if (editConcernGstin) {
    editConcernGstin.addEventListener('input', () => validateConcernGstin(editConcernGstin, editConcernGstinFb, false));
    editConcernGstin.addEventListener('blur', () => validateConcernGstin(editConcernGstin, editConcernGstinFb, true));
}

if (editConcernPhone) {
    editConcernPhone.addEventListener('input', () => validateConcernPhone(editConcernPhone, editConcernPhoneFb, false));
    editConcernPhone.addEventListener('blur', () => validateConcernPhone(editConcernPhone, editConcernPhoneFb, true));
}

if (editConcernAddress) {
    editConcernAddress.addEventListener('input', () => validateConcernAddress(editConcernAddress, editConcernAddressFb, false));
    editConcernAddress.addEventListener('blur', () => validateConcernAddress(editConcernAddress, editConcernAddressFb, true));
}

if (editConcernForm) {
    editConcernForm.addEventListener('submit', event => {
        let isValid = true;

        if (!validateConcernName(editConcernName, editConcernNameFb, editConcernId ? editConcernId.value : null)) {
            isValid = false;
            editConcernName.focus();
        }

        if (!validateConcernGstin(editConcernGstin, editConcernGstinFb, true)) {
            if (isValid) editConcernGstin.focus();
            isValid = false;
        }

        if (!validateConcernPhone(editConcernPhone, editConcernPhoneFb, true)) {
            if (isValid) editConcernPhone.focus();
            isValid = false;
        }

        if (!validateConcernAddress(editConcernAddress, editConcernAddressFb, true)) {
            if (isValid) editConcernAddress.focus();
            isValid = false;
        }

        const hasSelectedModule = editConcernForm.querySelector('.edit-module-check:checked');
        if (!hasSelectedModule) {
            isValid = false;
            if (editConcernModuleError) {
                editConcernModuleError.classList.remove('d-none');
                editConcernModuleError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        if (!isValid) {
            event.preventDefault();
        }
    });

    editConcernForm.querySelectorAll('.edit-module-check').forEach(checkbox => {
        checkbox.addEventListener('change', () => {
            if (editConcernForm.querySelector('.edit-module-check:checked')) {
                if (editConcernModuleError) editConcernModuleError.classList.add('d-none');
            }
            if (checkbox.dataset.group) {
                syncEditGroupMasterCheck(checkbox.dataset.group);
            }
        });
    });
}

// Live module toggle handler
document.querySelectorAll('.module-toggle').forEach(el => {
    el.addEventListener('change', function() {
        const concernId = this.dataset.concernId;
        const moduleId  = this.dataset.moduleId;
        const enabled   = this.checked ? 1 : 0;

        const formData = new FormData();
        formData.append('concern_id', concernId);
        formData.append('module_id', moduleId);
        formData.append('enabled', enabled);
        formData.append('csrf_token', '<?= htmlspecialchars($csrfToken) ?>');

        fetch('<?= \App\Core\Controller::url('/admin/concerns/toggle-module') ?>', {
            method: 'POST',
            body: formData
        }).then(r => r.json()).then(res => {
            if (!res.success) {
                alert('Failed to update module status: ' + res.message);
            }
        }).catch(err => {
            alert('Error updating module status.');
        });
    });
});
</script>
