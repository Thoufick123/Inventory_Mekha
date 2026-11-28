<!-- Choose Label Counts Modal (Stitching) -->
<div class="modal fade" id="modalStitchingLabelCounts" tabindex="-1" aria-labelledby="modalStitchingLabelCountsLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom bg-white px-4 py-3">
                <h5 class="modal-title fw-bold text-dark" id="modalStitchingLabelCountsLabel">Choose label counts</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-3 p-3 border rounded-3 bg-light">
                    <label for="stitchingBrandInput" class="form-label small fw-bold text-uppercase text-secondary mb-2">Brand Name (Optional)</label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" id="stitchingBrandInput" placeholder="Enter or search brand name..." autocomplete="off" maxlength="100">
                        <input type="hidden" id="stitchingBrandEditId" value="">
                        <button type="button" class="btn btn-outline-primary fw-semibold px-3" id="stitchingBrandSaveBtn" title="Save Brand">
                            <i class="bi bi-plus-lg me-1"></i>Save
                        </button>
                        <button type="button" class="btn btn-outline-secondary d-none" id="stitchingBrandCancelEditBtn" title="Cancel Edit">
                            Cancel
                        </button>
                    </div>
                    <div id="stitchingBrandFeedback" class="small mt-2 d-none"></div>
                    <div id="stitchingBrandList" class="mt-2 d-flex flex-wrap gap-2" style="max-height: 110px; overflow-y: auto;"></div>
                </div>
                <div class="table-responsive border rounded-3">
                    <table class="table table-bordered table-striped table-hover table-sm align-middle mb-0" style="font-size: 0.84rem;">
                        <thead class="text-uppercase small text-secondary" style="background-color: #f1f5f9; font-size: 0.72rem;">
                            <tr>
                                <th class="text-center py-2" style="width: 40px;">
                                    <input type="checkbox" class="form-check-input" id="stitchingLabelSelectAll" title="Select / Deselect All" style="cursor: pointer; margin: 0; position: static;" checked>
                                </th>
                                <th class="text-center py-2" style="width: 45px;">#</th>
                                <th class="py-2">Selected Line</th>
                                <th class="text-center py-2" style="width: 120px;">Stitched Qty</th>
                                <th class="text-center py-2" style="width: 120px;">Copies</th>
                            </tr>
                        </thead>
                        <tbody id="stitchingLabelCountsBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top bg-light px-4 py-2.5 gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary px-3 fw-semibold" id="btnConfirmStitchingLabels">
                    <i class="bi bi-printer-fill me-1"></i> Generate labels
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const LABEL_GENERATE_URL = <?= json_encode(\App\Core\Controller::url('/m/label_generation/generate')) ?>;
    const LABEL_CSRF_TOKEN = <?= json_encode($csrfToken ?? \App\Core\Auth::csrfToken()) ?>;
    const STITCH_BRAND_LIST_URL = <?= json_encode(\App\Core\Controller::url('/m/stitching/brands')) ?>;
    const STITCH_BRAND_STORE_URL = <?= json_encode(\App\Core\Controller::url('/m/stitching/brands/store')) ?>;
    const STITCH_BRAND_UPDATE_URL = <?= json_encode(\App\Core\Controller::url('/m/stitching/brands/update')) ?>;
    const STITCH_BRAND_DELETE_URL = <?= json_encode(\App\Core\Controller::url('/m/stitching/brands/delete')) ?>;
    let cachedStitchBrands = [];
    let activeLabelConfig = null;

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, function(ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    }

    function submitLabelPrintForm(fields) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = LABEL_GENERATE_URL;
        form.target = '_blank';
        form.style.display = 'none';

        const payload = Object.assign({
            csrf_token: LABEL_CSRF_TOKEN,
            label_type: 'production'
        }, fields || {});

        Object.keys(payload).forEach(function(key) {
            const val = payload[key];
            if (val === undefined || val === null || val === '') return;
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = String(val);
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    function openCountModal(config) {
        activeLabelConfig = config;
        const tbody = document.getElementById('stitchingLabelCountsBody');
        if (!tbody) return;

        tbody.innerHTML = (config.rows || []).map(function(row, idx) {
            const qtyVal = Math.max(1, parseInt(row.qty, 10) || 1);
            return `
                <tr>
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input stitching-label-row-check" style="cursor: pointer; margin: 0; position: static;" checked>
                    </td>
                    <td class="text-center text-muted small fw-semibold">${idx + 1}</td>
                    <td class="fw-semibold text-dark text-nowrap">${escapeHtml(row.name)}</td>
                    <td class="text-center text-dark small text-nowrap font-monospace">${escapeHtml(row.subtitle)}</td>
                    <td class="text-center">
                        <input type="number"
                               min="${config.mode === 'batch' ? 0 : 1}"
                               max="2000"
                               step="1"
                               class="form-control form-control-sm text-center fw-semibold stitching-label-copy-input mx-auto"
                               style="width: 90px;"
                               data-item-id="${escapeHtml(row.itemId)}"
                               value="${qtyVal}">
                    </td>
                </tr>
            `;
        }).join('');

        const selectAllEl = document.getElementById('stitchingLabelSelectAll');
        if (selectAllEl) {
            selectAllEl.checked = true;
            selectAllEl.indeterminate = false;
            selectAllEl.disabled = false;
        }
        tbody.querySelectorAll('.stitching-label-row-check').forEach(function(cb) {
            cb.addEventListener('change', function() {
                const tr = cb.closest('tr');
                const inp = tr ? tr.querySelector('.stitching-label-copy-input') : null;
                if (inp) {
                    inp.disabled = !cb.checked;
                }
                if (selectAllEl) {
                    const allCbs = Array.from(tbody.querySelectorAll('.stitching-label-row-check'));
                    const checkedCount = allCbs.filter(function(c) { return c.checked; }).length;
                    selectAllEl.checked = checkedCount === allCbs.length;
                    selectAllEl.indeterminate = checkedCount > 0 && checkedCount < allCbs.length;
                }
            });
        });

        const brandInput = document.getElementById('stitchingBrandInput');
        if (brandInput) {
            fetchStitchBrands(brandInput.value.trim());
        } else {
            fetchStitchBrands('');
        }

        const modalEl = document.getElementById('modalStitchingLabelCounts');
        if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    function showStitchBrandFeedback(msg, isError) {
        const el = document.getElementById('stitchingBrandFeedback');
        if (!el) return;
        if (!msg) {
            el.className = 'small mt-1 d-none';
            el.textContent = '';
            return;
        }
        el.className = 'small mt-1 ' + (isError ? 'text-danger' : 'text-success');
        el.textContent = msg;
    }

    function resetStitchBrandEditMode() {
        const editId = document.getElementById('stitchingBrandEditId');
        const saveBtn = document.getElementById('stitchingBrandSaveBtn');
        const cancelBtn = document.getElementById('stitchingBrandCancelEditBtn');
        if (editId) editId.value = '';
        if (saveBtn) saveBtn.innerHTML = '<i class="bi bi-plus-lg me-1"></i>Save';
        if (cancelBtn) cancelBtn.classList.add('d-none');
    }

    function renderStitchBrandList(filterText) {
        const listEl = document.getElementById('stitchingBrandList');
        if (!listEl) return;
        const q = (filterText || '').trim().toLowerCase();
        const filtered = (Array.isArray(cachedStitchBrands) ? cachedStitchBrands : []).filter(function(b) {
            return !q || String(b.name || '').toLowerCase().includes(q);
        });
        if (!filtered.length) {
            listEl.innerHTML = '<span class="small text-muted">No saved brands match. Type a name and click Save or Generate labels.</span>';
            return;
        }
        listEl.innerHTML = filtered.map(function(b) {
            const bName = escapeHtml(b.name || '');
            return `
                <div class="badge bg-white text-dark border d-inline-flex align-items-center gap-1 px-2 py-1">
                    <button type="button" class="btn btn-link btn-sm p-0 text-dark text-decoration-none fw-semibold stitch-brand-select-btn" data-brand-name="${bName}" style="font-size: 0.78rem;">
                        ${bName}
                    </button>
                    <button type="button" class="btn btn-link btn-sm p-0 text-primary stitch-brand-edit-btn ms-1" data-brand-id="${b.id}" data-brand-name="${bName}" title="Edit Brand" style="font-size: 0.75rem;">
                        <i class="bi bi-pencil-square"></i>
                    </button>
                    <button type="button" class="btn btn-link btn-sm p-0 text-danger stitch-brand-delete-btn" data-brand-id="${b.id}" data-brand-name="${bName}" title="Delete Brand" style="font-size: 0.75rem;">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>`;
        }).join('');
    }

    function fetchStitchBrands(query) {
        const url = new URL(STITCH_BRAND_LIST_URL, window.location.origin);
        if (query) url.searchParams.set('q', query);
        fetch(url.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data && data.success && Array.isArray(data.brands)) {
                    if (!query) cachedStitchBrands = data.brands;
                    if (query) {
                        const tmp = cachedStitchBrands;
                        cachedStitchBrands = data.brands;
                        renderStitchBrandList('');
                        cachedStitchBrands = tmp;
                    } else {
                        const inp = document.getElementById('stitchingBrandInput');
                        renderStitchBrandList(inp ? inp.value : '');
                    }
                }
            })
            .catch(function() {});
    }

    document.addEventListener('DOMContentLoaded', function() {
        const brandInput = document.getElementById('stitchingBrandInput');
        const brandEditIdInput = document.getElementById('stitchingBrandEditId');
        const brandSaveBtn = document.getElementById('stitchingBrandSaveBtn');
        const brandCancelEditBtn = document.getElementById('stitchingBrandCancelEditBtn');
        const brandListEl = document.getElementById('stitchingBrandList');

        if (brandInput) {
            let brandSearchTimer = null;
            brandInput.addEventListener('input', function() {
                showStitchBrandFeedback('', false);
                renderStitchBrandList(brandInput.value);
                clearTimeout(brandSearchTimer);
                brandSearchTimer = setTimeout(function() {
                    fetchStitchBrands(brandInput.value.trim());
                }, 200);
            });
        }

        if (brandCancelEditBtn) {
            brandCancelEditBtn.addEventListener('click', function() {
                resetStitchBrandEditMode();
                if (brandInput) brandInput.value = '';
                renderStitchBrandList('');
                showStitchBrandFeedback('', false);
            });
        }

        if (brandSaveBtn && brandInput) {
            brandSaveBtn.addEventListener('click', function() {
                const name = brandInput.value.trim();
                const editId = brandEditIdInput ? brandEditIdInput.value.trim() : '';
                if (!name) {
                    showStitchBrandFeedback('Please enter a brand name.', true);
                    brandInput.focus();
                    return;
                }
                const fd = new FormData();
                fd.append('csrf_token', LABEL_CSRF_TOKEN);
                fd.append('name', name);
                if (editId) fd.append('id', editId);
                brandSaveBtn.disabled = true;
                fetch(editId ? STITCH_BRAND_UPDATE_URL : STITCH_BRAND_STORE_URL, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data || !data.success) {
                        showStitchBrandFeedback((data && data.message) || 'Could not save brand.', true);
                        return;
                    }
                    if (Array.isArray(data.brands)) cachedStitchBrands = data.brands;
                    showStitchBrandFeedback(editId ? 'Brand updated.' : 'Brand saved.', false);
                    resetStitchBrandEditMode();
                    renderStitchBrandList('');
                })
                .catch(function() { showStitchBrandFeedback('Error saving brand.', true); })
                .finally(function() { brandSaveBtn.disabled = false; });
            });
        }

        if (brandListEl) {
            brandListEl.addEventListener('click', function(e) {
                const selectBtn = e.target.closest('.stitch-brand-select-btn');
                if (selectBtn && brandInput) {
                    brandInput.value = selectBtn.dataset.brandName || '';
                    resetStitchBrandEditMode();
                    showStitchBrandFeedback('', false);
                    return;
                }
                const editBtn = e.target.closest('.stitch-brand-edit-btn');
                if (editBtn && brandInput && brandEditIdInput) {
                    brandEditIdInput.value = editBtn.dataset.brandId || '';
                    brandInput.value = editBtn.dataset.brandName || '';
                    brandInput.focus();
                    if (brandSaveBtn) brandSaveBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Update';
                    if (brandCancelEditBtn) brandCancelEditBtn.classList.remove('d-none');
                    return;
                }
                const delBtn = e.target.closest('.stitch-brand-delete-btn');
                if (delBtn) {
                    const id = delBtn.dataset.brandId || '';
                    if (!id) return;
                    const fd = new FormData();
                    fd.append('csrf_token', LABEL_CSRF_TOKEN);
                    fd.append('id', id);
                    fetch(STITCH_BRAND_DELETE_URL, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: fd
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!data || !data.success) {
                            showStitchBrandFeedback((data && data.message) || 'Could not delete brand.', true);
                            return;
                        }
                        if (Array.isArray(data.brands)) cachedStitchBrands = data.brands;
                        if (brandInput && brandInput.value.trim() === (delBtn.dataset.brandName || '')) {
                            brandInput.value = '';
                        }
                        resetStitchBrandEditMode();
                        renderStitchBrandList('');
                        showStitchBrandFeedback('Brand deleted.', false);
                    })
                    .catch(function() { showStitchBrandFeedback('Error deleting brand.', true); });
                }
            });
        }

        const selectAllEl = document.getElementById('stitchingLabelSelectAll');
        if (selectAllEl) {
            selectAllEl.addEventListener('change', function() {
                const checked = selectAllEl.checked;
                document.querySelectorAll('#stitchingLabelCountsBody .stitching-label-row-check').forEach(function(cb) {
                    cb.checked = checked;
                    const tr = cb.closest('tr');
                    const inp = tr ? tr.querySelector('.stitching-label-copy-input') : null;
                    if (inp) inp.disabled = !checked;
                });
                selectAllEl.indeterminate = false;
            });
        }

        const btn = document.getElementById('btnConfirmStitchingLabels');
        if (!btn) return;
        btn.addEventListener('click', function() {
            if (!activeLabelConfig) return;
            const inputs = document.querySelectorAll('#stitchingLabelCountsBody .stitching-label-copy-input');
            const modalEl = document.getElementById('modalStitchingLabelCounts');
            const brandVal = brandInput ? brandInput.value.trim() : '';

            if (activeLabelConfig.mode === 'variant') {
                const inp = inputs[0];
                const tr = inp ? inp.closest('tr') : null;
                const cb = tr ? tr.querySelector('.stitching-label-row-check') : null;
                if (cb && !cb.checked) {
                    alert('Please select at least 1 row to generate labels.');
                    return;
                }
                const qty = Math.max(1, parseInt(inp ? inp.value : 1, 10) || 1);
                if (modalEl && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                }
                submitLabelPrintForm({
                    prod_print_mode: 'variant',
                    item_id: activeLabelConfig.itemId,
                    qty: qty,
                    brand_name: brandVal
                });
            } else if (activeLabelConfig.mode === 'batch') {
                const fields = {
                    prod_print_mode: 'batch',
                    batch_id: activeLabelConfig.batchId,
                    brand_name: brandVal
                };
                let total = 0;
                inputs.forEach(function(inp) {
                    const tr = inp.closest('tr');
                    const cb = tr ? tr.querySelector('.stitching-label-row-check') : null;
                    const isChecked = !cb || cb.checked;
                    const itemId = parseInt(inp.getAttribute('data-item-id'), 10) || 0;
                    const count = isChecked ? Math.max(0, parseInt(inp.value, 10) || 0) : 0;
                    total += count;
                    if (itemId > 0) {
                        fields['label_counts[' + itemId + ']'] = count;
                    }
                });
                if (total <= 0) {
                    alert('Please enter at least 1 label copy to generate.');
                    return;
                }
                if (modalEl && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                }
                submitLabelPrintForm(fields);
            }
        });
    });

    window.openStitchingVariantLabelModal = function(itemId, name, subtitle, defaultQty) {
        const id = parseInt(itemId, 10) || 0;
        if (id <= 0) return;
        const q = Math.max(1, parseInt(defaultQty, 10) || 1);
        openCountModal({
            mode: 'variant',
            itemId: id,
            rows: [{
                itemId: id,
                name: name || 'Variant',
                subtitle: q + ' pcs',
                qty: q
            }]
        });
    };

    window.openStitchingBatchLabelModal = function(batchId, items) {
        const id = parseInt(batchId, 10) || 0;
        if (id <= 0) return;
        const rows = (items || []).map(function(it) {
            const q = Math.max(1, parseInt(it.qty, 10) || 1);
            return {
                itemId: it.id,
                name: it.name || 'Variant',
                subtitle: q + ' pcs',
                qty: q
            };
        });
        if (rows.length === 0) {
            submitLabelPrintForm({ prod_print_mode: 'batch', batch_id: id });
            return;
        }
        openCountModal({
            mode: 'batch',
            batchId: id,
            rows: rows
        });
    };
})();
</script>
