<?php
// Shared JS + CSS for selecting & adding Size Attributes per roll in Program Create & Edit
?>
<style>
.prog-attr-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.65rem;
    border-radius: 999px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #334155;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    user-select: none;
    transition: all 0.15s ease;
}
.prog-attr-chip:hover {
    border-color: #3b82f6;
    background: #eff6ff;
}
.prog-attr-chip.active {
    background: #2563eb;
    border-color: #2563eb;
    color: #ffffff;
    box-shadow: 0 1px 3px rgba(37, 99, 235, 0.25);
}
.prog-attr-chip input[type="checkbox"] {
    accent-color: #ffffff;
    margin: 0;
    cursor: pointer;
}
</style>

<script>
window.ProgramCatalogAttributes = (function() {
    let availableSizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
    let catalogsList = [];
    let quickAddUrl = '<?= \App\Core\Controller::url('/api/attributes/quick-add') ?>';

    function esc(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function parseCatalogSizes(catalog) {
        if (!catalog || !catalog.sizes) return [];
        if (Array.isArray(catalog.sizes)) return catalog.sizes;
        try {
            const parsed = JSON.parse(catalog.sizes);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function init(attrSizesFromServer, catalogsFromServer) {
        catalogsList = Array.isArray(catalogsFromServer) ? catalogsFromServer : [];
        if (Array.isArray(attrSizesFromServer)) {
            attrSizesFromServer.forEach(item => {
                const val = typeof item === 'object' ? (item.attribute_value || '') : String(item || '');
                const clean = val.trim();
                if (clean && !availableSizes.some(s => s.toUpperCase() === clean.toUpperCase())) {
                    availableSizes.push(clean);
                }
            });
        }
    }

    function buildSectionHtml(index) {
        return `
            <div class="mt-2 pt-2 border-top roll-attr-section d-none" data-roll-index="${index}">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <span class="small fw-semibold text-dark d-flex align-items-center gap-1">
                        <i class="bi bi-tags text-primary"></i> Size Attributes (Select &amp; Add)
                    </span>
                    <div class="input-group input-group-sm" style="max-width: 240px;">
                        <input type="text" class="form-control form-control-sm input-new-attr" placeholder="New size (e.g. 3XL)...">
                        <button type="button" class="btn btn-outline-primary btn-add-attr fw-semibold">
                            <i class="bi bi-plus-lg"></i> Add
                        </button>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 roll-attr-chips"></div>
                <div class="small text-muted mt-1" style="font-size: 0.72rem;">
                    Select sizes for this roll or add a new size attribute.
                </div>
            </div>
        `;
    }

    function renderChipsForCard(card, selectedList) {
        const index = card.getAttribute('data-index');
        const chipsContainer = card.querySelector('.roll-attr-chips');
        if (!chipsContainer) return;

        const selectedUpper = (selectedList || []).map(s => String(s).trim().toUpperCase());
        const mergedSizes = [...availableSizes];
        (selectedList || []).forEach(sz => {
            const clean = String(sz || '').trim();
            if (clean && !mergedSizes.some(s => s.toUpperCase() === clean.toUpperCase())) {
                mergedSizes.push(clean);
            }
        });

        let html = '';
        mergedSizes.forEach(sz => {
            const isChecked = selectedUpper.includes(sz.toUpperCase());
            html += `
                <label class="prog-attr-chip ${isChecked ? 'active' : ''}">
                    <input type="checkbox" name="rolls[${index}][sizes][]" value="${esc(sz)}" class="prog-attr-checkbox" ${isChecked ? 'checked' : ''}>
                    <span>${esc(sz)}</span>
                </label>
            `;
        });
        chipsContainer.innerHTML = html;
        bindChipToggleEvents(card);
    }

    function bindChipToggleEvents(card) {
        card.querySelectorAll('.prog-attr-checkbox').forEach(cb => {
            cb.onchange = function() {
                const label = this.closest('.prog-attr-chip');
                if (label) {
                    label.classList.toggle('active', this.checked);
                }
            };
        });
    }

    function bindCardEvents(card) {
        const section = card.querySelector('.roll-attr-section');
        if (!section) return;
        const input = section.querySelector('.input-new-attr');
        const btn = section.querySelector('.btn-add-attr');

        if (!input || !btn) return;

        function handleAdd() {
            const val = input.value.trim();
            if (!val) {
                input.focus();
                return;
            }

            // If chip already exists in this card, just check it
            const existingCb = Array.from(card.querySelectorAll('.prog-attr-checkbox')).find(
                cb => cb.value.trim().toUpperCase() === val.toUpperCase()
            );
            if (existingCb) {
                existingCb.checked = true;
                existingCb.dispatchEvent(new Event('change'));
                input.value = '';
                return;
            }

            btn.disabled = true;
            const fd = new FormData();
            fd.append('attribute_type', 'size');
            fd.append('attribute_value', val);
            fd.append('attribute_name', 'Size');
            fd.append('action_mode', 'style_builder');

            fetch(quickAddUrl, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    btn.disabled = false;
                    if (!res.success && !res.attribute_value) {
                        alert(res.message || 'Failed to add size attribute.');
                        return;
                    }
                    const chipVal = (res.attribute_value || val).trim();
                    if (!availableSizes.some(s => s.toUpperCase() === chipVal.toUpperCase())) {
                        availableSizes.push(chipVal);
                    }

                    const index = card.getAttribute('data-index');
                    const chipsContainer = card.querySelector('.roll-attr-chips');
                    if (chipsContainer) {
                        const label = document.createElement('label');
                        label.className = 'prog-attr-chip active';
                        label.innerHTML = `
                            <input type="checkbox" name="rolls[${index}][sizes][]" value="${esc(chipVal)}" class="prog-attr-checkbox" checked>
                            <span>${esc(chipVal)}</span>
                        `;
                        chipsContainer.appendChild(label);
                        bindChipToggleEvents(card);
                    }

                    // Also add unchecked chip to other visible attribute sections
                    document.querySelectorAll('.roll-card').forEach(otherCard => {
                        if (otherCard === card) return;
                        const otherSec = otherCard.querySelector('.roll-attr-section');
                        const otherChips = otherCard.querySelector('.roll-attr-chips');
                        if (!otherSec || otherSec.classList.contains('d-none') || !otherChips) return;
                        const already = Array.from(otherChips.querySelectorAll('.prog-attr-checkbox')).some(
                            cb => cb.value.trim().toUpperCase() === chipVal.toUpperCase()
                        );
                        if (!already) {
                            const oIdx = otherCard.getAttribute('data-index');
                            const label = document.createElement('label');
                            label.className = 'prog-attr-chip';
                            label.innerHTML = `
                                <input type="checkbox" name="rolls[${oIdx}][sizes][]" value="${esc(chipVal)}" class="prog-attr-checkbox">
                                <span>${esc(chipVal)}</span>
                            `;
                            otherChips.appendChild(label);
                            bindChipToggleEvents(otherCard);
                        }
                    });

                    input.value = '';
                })
                .catch(() => {
                    btn.disabled = false;
                    alert('Network error while adding size attribute.');
                });
        }

        btn.addEventListener('click', handleAdd);
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleAdd();
            }
        });
    }

    function syncForCard(card, catalogId, preselectedSizes = null, hasHierarchySelection = false) {
        if (!card) return;
        const section = card.querySelector('.roll-attr-section');
        if (!section) return;

        if (!catalogId && !hasHierarchySelection) {
            section.classList.add('d-none');
            const chips = section.querySelector('.roll-attr-chips');
            if (chips) chips.innerHTML = '';
            return;
        }

        section.classList.remove('d-none');

        let catalogSizes = [];
        if (catalogId) {
            const catObj = catalogsList.find(c => String(c.id) === String(catalogId));
            catalogSizes = parseCatalogSizes(catObj);
            catalogSizes.forEach(sz => {
                const clean = String(sz || '').trim();
                if (clean && !availableSizes.some(s => s.toUpperCase() === clean.toUpperCase())) {
                    availableSizes.push(clean);
                }
            });
        }

        let selectedList = [];
        if (Array.isArray(preselectedSizes) && preselectedSizes.length > 0) {
            selectedList = preselectedSizes;
        } else if (catalogSizes.length > 0) {
            selectedList = catalogSizes;
        }

        renderChipsForCard(card, selectedList);
    }

    return {
        init,
        buildSectionHtml,
        bindCardEvents,
        syncForCard
    };
})();
</script>
