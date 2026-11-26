<script>
(function() {
    const LABEL_GENERATE_URL = <?= json_encode(\App\Core\Controller::url('/m/label_generation/generate')) ?>;
    const LABEL_CSRF_TOKEN = <?= json_encode($csrfToken ?? \App\Core\Auth::csrfToken()) ?>;

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

    window.printStitchingBatchLabels = function(batchId) {
        const id = parseInt(batchId, 10) || 0;
        if (id <= 0) return;
        submitLabelPrintForm({ prod_print_mode: 'batch', batch_id: id });
    };

    window.printStitchingVariantLabel = function(itemId, qty) {
        const id = parseInt(itemId, 10) || 0;
        if (id <= 0) return;
        const count = Math.max(1, parseInt(qty, 10) || 1);
        submitLabelPrintForm({ prod_print_mode: 'variant', item_id: id, qty: count });
    };
})();
</script>
