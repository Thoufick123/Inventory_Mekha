<!-- Choose Label Counts Modal (Stitching) -->
<div class="modal fade" id="modalStitchingLabelCounts" tabindex="-1" aria-labelledby="modalStitchingLabelCountsLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom bg-white px-4 py-3">
                <h5 class="modal-title fw-bold text-dark" id="modalStitchingLabelCountsLabel">Choose label counts</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="table-responsive border rounded-3">
                    <table class="table table-bordered table-striped table-hover table-sm align-middle mb-0" style="font-size: 0.84rem;">
                        <thead class="text-uppercase small text-secondary" style="background-color: #f1f5f9; font-size: 0.72rem;">
                            <tr>
                                <th class="text-center py-2" style="width: 45px;">#</th>
                                <th class="py-2">Selected Line</th>
                                <th class="py-2">Product Code &amp; Qty</th>
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
                    <td class="text-center text-muted small fw-semibold">${idx + 1}</td>
                    <td class="fw-semibold text-dark text-nowrap">${escapeHtml(row.name)}</td>
                    <td class="text-muted small text-nowrap font-monospace">${escapeHtml(row.subtitle)}</td>
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

        const modalEl = document.getElementById('modalStitchingLabelCounts');
        if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const btn = document.getElementById('btnConfirmStitchingLabels');
        if (!btn) return;
        btn.addEventListener('click', function() {
            if (!activeLabelConfig) return;
            const inputs = document.querySelectorAll('#stitchingLabelCountsBody .stitching-label-copy-input');
            const modalEl = document.getElementById('modalStitchingLabelCounts');

            if (activeLabelConfig.mode === 'variant') {
                const inp = inputs[0];
                const qty = Math.max(1, parseInt(inp ? inp.value : 1, 10) || 1);
                if (modalEl && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                }
                submitLabelPrintForm({
                    prod_print_mode: 'variant',
                    item_id: activeLabelConfig.itemId,
                    qty: qty
                });
            } else if (activeLabelConfig.mode === 'batch') {
                const fields = {
                    prod_print_mode: 'batch',
                    batch_id: activeLabelConfig.batchId
                };
                let total = 0;
                inputs.forEach(function(inp) {
                    const itemId = parseInt(inp.getAttribute('data-item-id'), 10) || 0;
                    const count = Math.max(0, parseInt(inp.value, 10) || 0);
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
        openCountModal({
            mode: 'variant',
            itemId: id,
            rows: [{
                itemId: id,
                name: name || 'Variant',
                subtitle: subtitle || ('print count / stitched qty ' + (parseInt(defaultQty, 10) || 1)),
                qty: Math.max(1, parseInt(defaultQty, 10) || 1)
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
                subtitle: (it.product_code ? ('Product Code: ' + it.product_code + ' • ') : '') + 'stitched qty ' + q,
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
