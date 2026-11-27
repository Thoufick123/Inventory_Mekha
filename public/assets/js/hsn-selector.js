/**
 * HSN / SAC Autocomplete & Dynamic GST Selector
 * Supports both instantaneous in-memory search (window.hsnSearchData) and AJAX fallback
 */
(function () {
    const requestIds = new WeakMap();
    const dropdownsByInput = new WeakMap();
    const inputsByDropdown = new WeakMap();
    let debounceTimer = null;
    let activeIndex = -1;

    function getEndpoint() {
        return window.hsnSearchEndpoint || '/api/hsn/search';
    }

    function getDropdown(input) {
        let results = dropdownsByInput.get(input);
        if (results && results.isConnected) return results;

        results = document.createElement('div');
        results.className = 'hsn-autocomplete-results shadow-lg border rounded-3';
        results.style.cssText = 'display:none;position:fixed;z-index:2050;background:#ffffff;border:1px solid #cbd5e1;min-width:340px;max-width:520px;max-height:280px;overflow-y:auto;box-shadow:0 12px 28px rgba(15,23,42,0.18);padding:4px 0;';
        document.body.appendChild(results);
        dropdownsByInput.set(input, results);
        inputsByDropdown.set(results, input);
        return results;
    }

    function positionDropdown(input, results) {
        if (!input || !results) return;
        const rect = input.getBoundingClientRect();
        const minW = Math.max(rect.width, 340);
        const maxLeft = Math.max(8, window.innerWidth - minW - 12);
        results.style.top = (rect.bottom + 3) + 'px';
        results.style.left = Math.max(8, Math.min(rect.left, maxLeft)) + 'px';
        results.style.minWidth = minW + 'px';
    }

    function closeDropdown(input, results) {
        if (results) results.style.display = 'none';
        activeIndex = -1;
        if (input && input.parentElement) {
            input.parentElement.style.zIndex = '';
        }
    }

    function updateGstTarget(input, row) {
        const targetSelector = input.dataset.hsnGstTarget;
        if (!targetSelector) return;

        const target = document.querySelector(targetSelector);
        if (!target) return;

        const hasRate = row.gst_rate !== null && row.gst_rate !== undefined && row.gst_rate !== '';
        const rate = hasRate ? Number(row.gst_rate) : null;
        target.value = rate !== null && Number.isFinite(rate) ? rate.toFixed(2) : '';
        target.dispatchEvent(new Event('input', { bubbles: true }));
        target.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function applySelection(input, row) {
        if (!input || !row) return;

        input.value = row.hsn_code || '';
        input.dataset.hsnSelectedCode = String(row.hsn_code || '').trim().toUpperCase();
        input.dataset.hsnGstRate = (row.gst_rate !== null && row.gst_rate !== undefined && row.gst_rate !== '') ? row.gst_rate : '';
        input.dataset.hsnRateNote = row.rate_note || '';

        const descriptionTarget = input.dataset.hsnDescriptionTarget;
        if (descriptionTarget) {
            const target = document.querySelector(descriptionTarget);
            if (target) target.value = row.description || '';
        }

        const valueTarget = input.dataset.hsnValueTarget;
        if (valueTarget) {
            const target = document.querySelector(valueTarget);
            if (target) target.value = row.hsn_code || '';
        }

        updateGstTarget(input, row);

        const noteTarget = input.dataset.hsnNoteTarget;
        if (noteTarget) {
            const target = document.querySelector(noteTarget);
            if (target) {
                target.textContent = row.rate_note || (row.gst_rate === null || row.gst_rate === undefined ? 'Verify the applicable GST rate for this classification.' : '');
            }
        }

        // Cancel any in-flight search & trigger input/change events without re-opening dropdown
        clearTimeout(debounceTimer);
        requestIds.set(input, (requestIds.get(input) || 0) + 1);
        input.dataset.hsnSuppressSearch = '1';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        delete input.dataset.hsnSuppressSearch;

        const results = getDropdown(input);
        closeDropdown(input, results);
        if (document.activeElement === input) {
            input.blur();
        }
    }

    function renderItems(input, results, rows, query) {
        results.replaceChildren();
        activeIndex = -1;

        if (!rows || rows.length === 0) {
            if (query) {
                const empty = document.createElement('div');
                empty.className = 'px-3 py-2 text-muted small fst-italic';
                empty.textContent = 'No matching HSN codes found.';
                results.append(empty);
                positionDropdown(input, results);
                results.style.display = 'block';
            } else {
                closeDropdown(input, results);
            }
            return;
        }

        rows.forEach((row, idx) => {
            const item = document.createElement('div');
            item.className = 'px-3 py-2 border-bottom border-light text-start hsn-item-result';
            item.style.cursor = 'pointer';
            item.style.transition = 'background-color 0.12s ease-in-out';
            item.dataset.index = idx;

            const header = document.createElement('div');
            header.className = 'd-flex justify-content-between align-items-center mb-1';

            const codeSpan = document.createElement('strong');
            codeSpan.className = 'font-monospace text-dark';
            codeSpan.style.fontSize = '0.9rem';
            codeSpan.textContent = `${row.code_type ? row.code_type + ' ' : ''}${row.hsn_code}`;

            const rateBadge = document.createElement('span');
            const hasRate = row.gst_rate !== null && row.gst_rate !== undefined && row.gst_rate !== '';
            if (hasRate) {
                rateBadge.className = 'badge bg-success-subtle text-success border border-success-subtle fw-semibold px-2 py-1';
                rateBadge.textContent = `${Number(row.gst_rate).toFixed(2)}% GST`;
            } else {
                rateBadge.className = 'badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1';
                rateBadge.textContent = 'Verify Rate';
            }
            header.append(codeSpan, rateBadge);
            item.append(header);

            if (row.description) {
                const desc = document.createElement('div');
                desc.className = 'small text-secondary text-truncate';
                desc.style.maxWidth = '460px';
                desc.title = row.description;
                desc.textContent = row.description;
                item.append(desc);
            }

            if (row.rate_note) {
                const note = document.createElement('div');
                note.className = 'small text-warning-emphasis mt-1';
                note.style.fontSize = '0.75rem';
                note.textContent = row.rate_note;
                item.append(note);
            }

            item.addEventListener('mouseenter', () => {
                setActiveItem(results, idx);
            });

            item.addEventListener('mousedown', (e) => {
                e.preventDefault();
                applySelection(input, row);
            });

            results.append(item);
        });

        positionDropdown(input, results);
        results.style.display = 'block';
    }

    function setActiveItem(results, index) {
        const items = results.querySelectorAll('.hsn-item-result');
        if (items.length === 0) return;
        items.forEach((it, i) => {
            if (i === index) {
                it.style.backgroundColor = '#f1f5f9';
                it.classList.add('active-result');
                it.scrollIntoView({ block: 'nearest' });
            } else {
                it.style.backgroundColor = '';
                it.classList.remove('active-result');
            }
        });
        activeIndex = index;
    }

    function searchInMemory(query, type) {
        if (!Array.isArray(window.hsnSearchData) || window.hsnSearchData.length === 0) {
            return null;
        }
        const q = (query || '').trim().toLowerCase();
        const t = (type || 'HSN').toUpperCase();

        return window.hsnSearchData.filter(row => {
            if (row.code_type && row.code_type.toUpperCase() !== t) return false;
            if (!q) return true;
            const code = String(row.hsn_code || '').toLowerCase();
            const desc = String(row.description || '').toLowerCase();
            const cat = String(row.category || '').toLowerCase();
            return code.includes(q) || desc.includes(q) || cat.includes(q);
        }).slice(0, 15);
    }

    async function search(input) {
        const results = getDropdown(input);
        const query = (input.value || '').trim();
        const type = input.dataset.hsnType || 'HSN';

        // 1. Try instantaneous in-memory search first if window.hsnSearchData exists
        const localMatches = searchInMemory(query, type);
        if (localMatches !== null) {
            renderItems(input, results, localMatches, query);
            return;
        }

        // 2. Fallback to AJAX endpoint
        const endpoint = getEndpoint();
        if (!endpoint) return;

        const requestId = (requestIds.get(input) || 0) + 1;
        requestIds.set(input, requestId);

        try {
            const sep = endpoint.includes('?') ? '&' : '?';
            const res = await fetch(`${endpoint}${sep}q=${encodeURIComponent(query)}&type=${encodeURIComponent(type)}`, {
                headers: { Accept: 'application/json' }
            });
            if (!res.ok) throw new Error('Failed to query HSN master');
            const data = await res.json();
            if (requestIds.get(input) !== requestId) return;

            if (data.success && Array.isArray(data.data)) {
                renderItems(input, results, data.data, query);
            } else {
                renderItems(input, results, [], query);
            }
        } catch (e) {
            if (requestIds.get(input) !== requestId) return;
            results.replaceChildren();
            const errDiv = document.createElement('div');
            errDiv.className = 'px-3 py-2 text-danger small';
            errDiv.textContent = 'Unable to load HSN codes.';
            results.append(errDiv);
            positionDropdown(input, results);
            results.style.display = 'block';
        }
    }

    // Auto-resolve exact typed match from in-memory list
    function autoResolveExactMatch(input, fillGstTarget = false) {
        if (!input || !Array.isArray(window.hsnSearchData)) return;
        const val = input.value.trim().toUpperCase();
        if (!val) {
            input.dataset.hsnGstRate = '';
            input.dataset.hsnRateNote = '';
            return;
        }
        const type = (input.dataset.hsnType || 'HSN').toUpperCase();
        const exact = window.hsnSearchData.find(item =>
            String(item.hsn_code || '').toUpperCase() === val &&
            (item.code_type || 'HSN').toUpperCase() === type
        );
        if (exact) {
            input.dataset.hsnSelectedCode = val;
            input.dataset.hsnGstRate = (exact.gst_rate !== null && exact.gst_rate !== undefined && exact.gst_rate !== '') ? exact.gst_rate : '';
            input.dataset.hsnRateNote = exact.rate_note || '';
            if (fillGstTarget) updateGstTarget(input, exact);
        }
    }

    // Event listeners
    document.addEventListener('focusin', event => {
        const input = event.target.closest('[data-hsn-autocomplete]');
        if (input) {
            if (input.dataset.hsnSelectedCode === undefined) {
                input.dataset.hsnSelectedCode = input.value.trim().toUpperCase();
            }
            autoResolveExactMatch(input);
            search(input);
        }
    });

    document.addEventListener('input', event => {
        const input = event.target.closest('[data-hsn-autocomplete]');
        if (!input) return;
        if (input.dataset.hsnSuppressSearch === '1') {
            return;
        }

        const value = input.value.trim().toUpperCase();
        if (value !== (input.dataset.hsnSelectedCode || '')) {
            input.dataset.hsnSelectedCode = '';
            input.dataset.hsnGstRate = '';
            input.dataset.hsnRateNote = '';
            updateGstTarget(input, { gst_rate: null });
        }

        autoResolveExactMatch(input, true);

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            search(input);
        }, 120);
    });

    document.addEventListener('keydown', event => {
        const input = event.target.closest('[data-hsn-autocomplete]');
        if (!input) return;

        const results = dropdownsByInput.get(input);
        if (!results || results.style.display === 'none') return;

        const items = results.querySelectorAll('.hsn-item-result');
        if (items.length === 0) return;

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            const next = (activeIndex + 1) >= items.length ? 0 : activeIndex + 1;
            setActiveItem(results, next);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            const prev = (activeIndex - 1) < 0 ? items.length - 1 : activeIndex - 1;
            setActiveItem(results, prev);
        } else if (event.key === 'Enter') {
            if (activeIndex >= 0 && activeIndex < items.length) {
                event.preventDefault();
                items[activeIndex].dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
            }
        } else if (event.key === 'Escape') {
            closeDropdown(input, results);
        }
    });

    document.addEventListener('focusout', event => {
        const input = event.target.closest('[data-hsn-autocomplete]');
        if (!input) return;
        setTimeout(() => {
            if (document.activeElement !== input) {
                const results = dropdownsByInput.get(input);
                if (results) closeDropdown(input, results);
            }
        }, 150);
    });

    document.addEventListener('click', event => {
        if (event.target.closest('[data-hsn-autocomplete], .hsn-autocomplete-results')) return;
        document.querySelectorAll('.hsn-autocomplete-results').forEach(results => {
            const input = inputsByDropdown.get(results) || (results.parentElement ? results.parentElement.querySelector('[data-hsn-autocomplete]') : null);
            closeDropdown(input, results);
        });
    });
})();
