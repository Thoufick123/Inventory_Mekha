<style>
.roll-picker-wrap {
    position: relative;
}
.roll-picker-btn {
    width: 100%;
    min-height: 44px;
    padding: 0.5rem 0.75rem;
    background: #ffffff;
    border: 2px solid var(--border, #e2e8f0);
    border-radius: 8px;
    text-align: left;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.roll-picker-btn:hover {
    border-color: #cbd5e1;
}
.roll-picker-btn:focus,
.roll-picker-btn.is-open {
    outline: none;
    border-color: var(--primary, #4f46e5);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
}
.roll-picker-btn.is-invalid {
    border-color: #dc3545;
}
.roll-picker-btn-main {
    min-width: 0;
    flex: 1 1 auto;
}
.roll-picker-menu {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    width: 100%;
    z-index: 1060;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.14);
    overflow: hidden;
}
.roll-picker-search-bar {
    padding: 0.55rem 0.65rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}
.roll-picker-list {
    max-height: 310px;
    overflow-y: auto;
    padding: 0.45rem;
}
.roll-picker-group-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.35rem 0.55rem;
    margin-top: 0.35rem;
    margin-bottom: 0.2rem;
    background: #f1f5f9;
    border-radius: 6px;
    font-size: 0.74rem;
    font-weight: 700;
    color: #334155;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}
.roll-picker-group-header:first-child {
    margin-top: 0;
}
.roll-picker-option {
    width: 100%;
    border: 1px solid transparent;
    background: #ffffff;
    border-radius: 8px;
    padding: 0.5rem 0.65rem;
    margin-bottom: 0.25rem;
    text-align: left;
    cursor: pointer;
    display: block;
    transition: background-color 0.12s ease, border-color 0.12s ease;
}
.roll-picker-option:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}
.roll-picker-option.is-selected {
    background: #eff6ff;
    border-color: #93c5fd;
}
.roll-picker-option.is-empty {
    opacity: 0.68;
}
.roll-selected-summary {
    margin-top: 0.5rem;
    padding: 0.55rem 0.75rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.78rem;
}
</style>
<script>
window.ProgramRollPicker = (function() {
    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function(ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    }

    function formatInr(value) {
        const num = parseFloat(value || 0) || 0;
        return '₹' + num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function getRollSpecTitle(roll) {
        const name = (roll.design_name || roll.pattern || roll.item_name || 'Fabric Roll').toUpperCase();
        const color = (roll.color || 'STANDARD').toUpperCase();
        return `${name} • ${color}`;
    }

    function getRollCode(roll) {
        return roll.tag_code || roll.item_code || `Roll #${roll.id}`;
    }

    function getSupplierLabel(roll) {
        return roll.supplier_display || roll.supplier_name || roll.supplier_company || 'Direct Supplier';
    }

    function buildGroupedRolls(rollsData) {
        const groups = [];
        const map = new Map();
        (rollsData || []).forEach(function(r) {
            const title = getRollSpecTitle(r);
            if (!map.has(title)) {
                const entry = {
                    title: title,
                    totalStock: parseFloat(r.design_accumulated_stock ?? r.remaining_qty ?? 0) || 0,
                    unit: r.unit || 'Rolls',
                    rolls: []
                };
                map.set(title, entry);
                groups.push(entry);
            }
            map.get(title).rolls.push(r);
        });
        return groups;
    }

    function buildSelectOptionsHtml(rollsData, preselectedId) {
        const groups = buildGroupedRolls(rollsData);
        let html = '<option value="">-- Select Fabric Roll --</option>';
        groups.forEach(function(grp) {
            const optgroupLabel = `${grp.title} (Total: ${grp.totalStock.toFixed(2)} ${grp.unit})`;
            html += `<optgroup label="${escapeHtml(optgroupLabel)}">`;
            grp.rolls.forEach(function(r) {
                const isSel = preselectedId && String(preselectedId) === String(r.id) ? 'selected' : '';
                const code = getRollCode(r);
                const supplier = getSupplierLabel(r);
                const bill = r.bill_no ? ` • Bill #${r.bill_no}` : '';
                const rem = (parseFloat(r.remaining_qty || 0) || 0).toFixed(2);
                html += `<option value="${escapeHtml(r.id)}" ${isSel}
                    data-id="${escapeHtml(r.id)}"
                    data-code="${escapeHtml(code)}"
                    data-name="${escapeHtml(r.item_name || '')}"
                    data-pattern="${escapeHtml(r.pattern || '')}"
                    data-design="${escapeHtml(r.design_name || r.pattern || r.item_name || '')}"
                    data-color="${escapeHtml(r.color || '')}"
                    data-unit="${escapeHtml(r.unit || 'kg')}"
                    data-total="${escapeHtml(r.total_qty ?? 0)}"
                    data-allocated="${escapeHtml(r.allocated_qty ?? 0)}"
                    data-remaining="${escapeHtml(r.remaining_qty ?? 0)}"
                    data-stock="${escapeHtml(r.remaining_qty ?? 0)}"
                    data-accumulated="${escapeHtml(r.design_accumulated_stock || r.remaining_qty || 0)}"
                    data-weight="${escapeHtml(r.weight || 0)}"
                    data-price="${escapeHtml(r.unit_price || 0)}"
                    data-supplier="${escapeHtml(supplier)}"
                    data-bill="${escapeHtml(r.bill_no || '')}"
                    data-catalog-id="${escapeHtml(r.catalog_id || '')}"
                    data-catalog-code="${escapeHtml(r.catalog_style_code || '')}"
                    data-catalog-name="${escapeHtml(r.catalog_name || '')}"
                    data-category-id="${escapeHtml(r.category_id || '')}"
                    data-subcategory-id="${escapeHtml(r.subcategory_id || '')}"
                    data-subsubcategory-id="${escapeHtml(r.sub_sub_category_id || '')}"
                    data-hierarchy="${escapeHtml(r.hierarchy_path || '')}">${escapeHtml(grp.title)} • ${escapeHtml(supplier)}${escapeHtml(bill)} • Stock: ${rem} ${escapeHtml(r.unit || 'kg')}</option>`;
            });
            html += '</optgroup>';
        });
        return html;
    }

    function renderMenuItems(card, rollsData, getEffectiveStock, queryText) {
        const listEl = card.querySelector('.roll-picker-list');
        const sel = card.querySelector('.select-roll-item');
        if (!listEl || !sel) return;

        const selectedId = String(sel.value || '');
        const needle = String(queryText || '').trim().toLowerCase();
        const groups = buildGroupedRolls(rollsData);

        let html = '';
        let matchCount = 0;

        groups.forEach(function(grp) {
            const matchingRolls = grp.rolls.filter(function(r) {
                if (!needle) return true;
                const haystack = [
                    r.design_name, r.pattern, r.item_name, r.color,
                    r.tag_code, r.item_code, r.bill_no,
                    getSupplierLabel(r), grp.title
                ].join(' ').toLowerCase();
                return haystack.includes(needle);
            });

            if (matchingRolls.length === 0) return;

            html += `
                <div class="roll-picker-group-header">
                    <span class="text-truncate">${escapeHtml(grp.title)}</span>
                    <span class="badge bg-white text-dark border fw-semibold">${grp.totalStock.toFixed(2)} ${escapeHtml(grp.unit)} total</span>
                </div>
            `;

            matchingRolls.forEach(function(r, idx) {
                matchCount++;
                const isSelected = selectedId !== '' && selectedId === String(r.id);
                const info = getEffectiveStock(r.id);
                const rem = parseFloat(info.remainingForCard || 0) || 0;
                const unit = info.unit || r.unit || 'kg';
                const supplier = getSupplierLabel(r);
                const code = getRollCode(r);
                const rate = parseFloat(r.unit_price || 0) || 0;
                const itemName = (r.item_name || '').trim();
                const showFabricName = itemName && itemName.toUpperCase() !== (r.design_name || r.pattern || '').toUpperCase();
                const batchNote = grp.rolls.length > 1 ? `Batch #${idx + 1}` : '';

                const stockPill = rem > 0
                    ? `<span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold">${rem.toFixed(2)} ${escapeHtml(unit)} avail</span>`
                    : `<span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-semibold">0.00 ${escapeHtml(unit)}</span>`;

                const secondaryParts = [];
                if (showFabricName) secondaryParts.push(escapeHtml(itemName));
                if (r.bill_no) secondaryParts.push(`Bill #${escapeHtml(r.bill_no)}`);
                if (rate > 0) secondaryParts.push(`${formatInr(rate)}/${escapeHtml(unit)}`);
                if (info.allocatedInOthers > 0) {
                    secondaryParts.push(`${info.allocatedInOthers.toFixed(2)} ${escapeHtml(unit)} used in other roll`);
                }

                html += `
                    <button type="button"
                            class="roll-picker-option${isSelected ? ' is-selected' : ''}${rem <= 0 && !isSelected ? ' is-empty' : ''}"
                            data-roll-id="${escapeHtml(r.id)}">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center gap-1 flex-wrap" style="min-width: 0;">
                                <i class="bi ${isSelected ? 'bi-check-circle-fill text-primary' : 'bi-person-badge text-primary'} flex-shrink-0"></i>
                                <span class="fw-bold text-dark small">${escapeHtml(supplier)}</span>
                                <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.68rem;">${escapeHtml(code)}</span>
                                ${batchNote ? `<span class="badge bg-light text-muted border" style="font-size: 0.66rem;">${escapeHtml(batchNote)}</span>` : ''}
                            </div>
                            <div class="flex-shrink-0">${stockPill}</div>
                        </div>
                        <div class="text-muted mt-1 text-truncate" style="font-size: 0.74rem;">
                            ${secondaryParts.join(' &bull; ')}
                        </div>
                    </button>
                `;
            });
        });

        if (matchCount === 0) {
            html = `<div class="text-center text-muted small py-3">${rollsData && rollsData.length ? 'No matching fabric rolls or suppliers found.' : 'No fabric rolls available in purchase stock.'}</div>`;
        }

        listEl.innerHTML = html;
    }

    function syncCardDisplay(card, rollsData, getEffectiveStock) {
        const sel = card.querySelector('.select-roll-item');
        const btn = card.querySelector('.roll-picker-btn');
        const btnMain = card.querySelector('.roll-picker-btn-main');
        const summaryEl = card.querySelector('.roll-selected-summary');
        if (!sel || !btn || !btnMain || !summaryEl) return;

        const selectedId = String(sel.value || '');
        const roll = (rollsData || []).find(function(r) {
            return String(r.id) === selectedId;
        });

        if (!roll) {
            btnMain.innerHTML = `<span class="text-muted small"><i class="bi bi-layers me-2 text-primary"></i>Choose fabric roll &amp; supplier...</span>`;
            summaryEl.innerHTML = '';
            summaryEl.classList.add('d-none');
            return;
        }

        btn.classList.remove('is-invalid');
        const info = getEffectiveStock(roll.id);
        const rem = parseFloat(info.remainingForCard || 0) || 0;
        const unit = info.unit || roll.unit || 'kg';
        const supplier = getSupplierLabel(roll);
        const code = getRollCode(roll);
        const specTitle = getRollSpecTitle(roll);
        const rate = parseFloat(roll.unit_price || 0) || 0;

        btnMain.innerHTML = `
            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                <div class="d-flex align-items-center gap-2" style="min-width: 0;">
                    <span class="fw-bold text-dark text-truncate">${escapeHtml(specTitle)}</span>
                    <span class="badge bg-light text-secondary border font-monospace flex-shrink-0" style="font-size: 0.7rem;">${escapeHtml(code)}</span>
                </div>
                <span class="small fw-semibold text-primary flex-shrink-0">
                    <i class="bi bi-building me-1"></i>${escapeHtml(supplier)}
                </span>
            </div>
        `;

        summaryEl.innerHTML = `
            <div class="row g-2 align-items-center">
                <div class="col-sm-4">
                    <div class="text-muted" style="font-size: 0.69rem;">Selected Fabric Roll</div>
                    <div class="fw-bold text-dark text-truncate" title="${escapeHtml(specTitle)}">${escapeHtml(specTitle)}</div>
                </div>
                <div class="col-sm-4">
                    <div class="text-muted" style="font-size: 0.69rem;">Supplier &amp; Purchase Bill</div>
                    <div class="fw-semibold text-primary text-truncate" title="${escapeHtml(supplier)}">
                        ${escapeHtml(supplier)}${roll.bill_no ? ` <span class="text-muted font-monospace">&bull; ${escapeHtml(roll.bill_no)}</span>` : ''}
                    </div>
                </div>
                <div class="col-sm-4 text-sm-end">
                    <div class="text-muted" style="font-size: 0.69rem;">Available &amp; Rate</div>
                    <div class="fw-semibold">
                        <span class="${rem > 0 ? 'text-success' : 'text-danger'}">${rem.toFixed(2)} ${escapeHtml(unit)}</span>
                        ${rate > 0 ? `<span class="text-muted"> &bull; ${formatInr(rate)}</span>` : ''}
                    </div>
                </div>
            </div>
        `;
        summaryEl.classList.remove('d-none');
    }

    function closeAllMenus(exceptMenu = null) {
        document.querySelectorAll('.roll-picker-menu').forEach(function(menu) {
            if (exceptMenu && menu === exceptMenu) return;
            menu.classList.add('d-none');
            const btn = menu.closest('.roll-picker-wrap')?.querySelector('.roll-picker-btn');
            if (btn) {
                btn.classList.remove('is-open');
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    let docListenerBound = false;
    function ensureDocumentCloseListener() {
        if (docListenerBound) return;
        docListenerBound = true;
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.roll-picker-wrap')) {
                closeAllMenus();
            }
        });
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAllMenus();
            }
        });
    }

    function attach(card, rollsData, getEffectiveStock) {
        ensureDocumentCloseListener();
        const wrap = card.querySelector('.roll-picker-wrap');
        const btn = card.querySelector('.roll-picker-btn');
        const menu = card.querySelector('.roll-picker-menu');
        const searchInput = card.querySelector('.roll-picker-search');
        const listEl = card.querySelector('.roll-picker-list');
        const sel = card.querySelector('.select-roll-item');
        if (!wrap || !btn || !menu || !listEl || !sel) return;

        syncCardDisplay(card, rollsData, getEffectiveStock);

        btn.addEventListener('click', function() {
            const isHidden = menu.classList.contains('d-none');
            closeAllMenus(isHidden ? menu : null);
            if (isHidden) {
                renderMenuItems(card, rollsData, getEffectiveStock, searchInput ? searchInput.value : '');
                menu.classList.remove('d-none');
                btn.classList.add('is-open');
                btn.setAttribute('aria-expanded', 'true');
                if (searchInput) {
                    searchInput.focus();
                }
            } else {
                menu.classList.add('d-none');
                btn.classList.remove('is-open');
                btn.setAttribute('aria-expanded', 'false');
            }
        });

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                renderMenuItems(card, rollsData, getEffectiveStock, this.value);
            });
            searchInput.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeAllMenus();
                    btn.focus();
                }
            });
        }

        listEl.addEventListener('click', function(event) {
            const optionBtn = event.target.closest('.roll-picker-option');
            if (!optionBtn) return;
            const rollId = optionBtn.getAttribute('data-roll-id') || '';
            sel.value = rollId;
            closeAllMenus();
            sel.dispatchEvent(new Event('change'));
        });
    }

    function refreshCard(card, rollsData, getEffectiveStock) {
        syncCardDisplay(card, rollsData, getEffectiveStock);
        const menu = card.querySelector('.roll-picker-menu');
        if (menu && !menu.classList.contains('d-none')) {
            const searchInput = card.querySelector('.roll-picker-search');
            renderMenuItems(card, rollsData, getEffectiveStock, searchInput ? searchInput.value : '');
        }
    }

    return {
        buildSelectOptionsHtml: buildSelectOptionsHtml,
        attach: attach,
        refreshCard: refreshCard,
        syncCardDisplay: syncCardDisplay,
        closeAllMenus: closeAllMenus
    };
})();
</script>
