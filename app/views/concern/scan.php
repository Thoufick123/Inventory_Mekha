<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Live QR Code Scanner & Stock Lookup</h4>
            <p class="text-secondary small mb-0">
                Scan or input format <code>C{category}-S{subcategory}-V{variant}</code> to fetch real-time variant details and live warehouse balance.
            </p>
        </div>
        <a href="<?= \App\Core\Controller::url('/m/label_generation') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-printer me-1"></i> Label Generation
        </a>
    </div>

    <div class="row g-4">
        <!-- Scanner Input Card -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-camera me-2 text-primary"></i> Scan or Enter Barcode</h6>
                </div>
                <div class="card-body p-4">
                    <!-- Manual / USB Gun Input -->
                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Barcode / QR String Input</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-upc-scan"></i></span>
                            <input type="text" id="manual_code" class="form-control font-monospace" placeholder="e.g. C1-S1-V1 or C2-S3-V2" autofocus>
                            <button class="btn btn-primary" type="button" onclick="performLookup()">
                                <i class="bi bi-search"></i> Lookup
                            </button>
                        </div>
                        <small class="text-muted">Type code and press Enter or use handheld USB scanner.</small>
                    </div>

                    <!-- Camera Stream Box -->
                    <div class="border rounded p-2 bg-light text-center">
                        <div id="reader" style="width: 100%; min-height: 240px; display: none;"></div>
                        <button id="btn_toggle_cam" class="btn btn-sm btn-outline-primary mt-2" onclick="toggleCamera()">
                            <i class="bi bi-camera-video me-1"></i> Start Device Camera
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Result Details Card -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-box-seam me-2 text-success"></i> Scanned Variant Master Details</h6>
                    <span id="scan_status" class="badge bg-light text-secondary border">Waiting for scan</span>
                </div>
                <div class="card-body p-4" id="result_container">
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-qr-code fs-1 d-block mb-2 text-secondary"></i>
                        <h5>No Item Scanned Yet</h5>
                        <p class="small text-muted mb-0">Use camera or type a barcode above to query the tenant inventory registry.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= \App\Core\Controller::url('/assets/js/html5-qrcode.min.js') ?>"></script>
<script>
let html5QrcodeScanner = null;
let cameraActive = false;

function toggleCamera() {
    const readerDiv = document.getElementById('reader');
    const btn = document.getElementById('btn_toggle_cam');

    if (!cameraActive) {
        readerDiv.style.display = 'block';
        html5QrcodeScanner = new Html5Qrcode("reader");
        html5QrcodeScanner.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            (decodedText) => {
                document.getElementById('manual_code').value = decodedText;
                performLookup(decodedText);
            },
            (error) => {}
        ).catch(err => {
            alert("Camera access denied or unsupported on this device: " + err);
        });
        btn.innerHTML = '<i class="bi bi-stop-circle me-1"></i> Stop Camera';
        btn.className = 'btn btn-sm btn-danger mt-2';
        cameraActive = true;
    } else {
        if (html5QrcodeScanner) {
            html5QrcodeScanner.stop().then(() => {
                readerDiv.style.display = 'none';
                btn.innerHTML = '<i class="bi bi-camera-video me-1"></i> Start Device Camera';
                btn.className = 'btn btn-sm btn-outline-primary mt-2';
                cameraActive = false;
            });
        }
    }
}

document.getElementById('manual_code').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        performLookup();
    }
});

function performLookup(codeToSearch = null) {
    const code = codeToSearch || document.getElementById('manual_code').value.trim();
    if (!code) return;

    const statusBadge = document.getElementById('scan_status');
    const container = document.getElementById('result_container');

    statusBadge.className = 'badge bg-warning text-dark';
    statusBadge.innerText = 'Searching...';

    fetch('<?= \App\Core\Controller::url('/api/qr/lookup?code=') ?>' + encodeURIComponent(code))
        .then(res => res.json())
        .then(res => {
            if (res.success && res.data) {
                const item = res.data;
                statusBadge.className = 'badge bg-success';
                statusBadge.innerText = 'Variant Identified';

                let attrHtml = '';
                if (item.attributes_parsed) {
                    for (const [k, v] of Object.entries(item.attributes_parsed)) {
                        attrHtml += `<span class="badge bg-light text-dark border me-1">${k}: ${v}</span>`;
                    }
                }

                container.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                        <div>
                            <span class="badge bg-dark font-monospace mb-1">${item.product_code}</span>
                            <h4 class="fw-bold text-dark mb-0">${item.name}</h4>
                            <div class="text-muted small mt-1">
                                Category: <strong>${item.category_name || '-'}</strong> &bull; Subcategory: <strong>${item.subcategory_name || '-'}</strong>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fs-6 px-3 py-2">
                                <i class="bi bi-boxes me-1"></i> Live Stock: <strong>${item.current_stock}</strong> ${item.weight_unit || 'units'}
                            </span>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <div class="p-2 border rounded bg-light">
                                <div class="text-muted small">Brand</div>
                                <div class="fw-bold">${item.brand_name || 'Generic / In-house'}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-2 border rounded bg-light">
                                <div class="text-muted small">Size / Specs</div>
                                <div class="fw-bold">${item.size || 'Free Size'}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-2 border rounded bg-light">
                                <div class="text-muted small">Standard Cost</div>
                                <div class="fw-bold text-primary">₹${parseFloat(item.price).toFixed(2)}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-2 border rounded bg-light">
                                <div class="text-muted small">Consumer MRP</div>
                                <div class="fw-bold text-danger">₹${parseFloat(item.mrp).toFixed(2)}</div>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded border mb-3">
                        <div class="text-secondary small fw-bold mb-2">Variant Attributes:</div>
                        <div>${attrHtml || '<span class="text-muted small">Standard specifications</span>'}</div>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="<?= \App\Core\Controller::url('/m/label_generation?variant_id=') ?>${item.id}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-printer me-1"></i> Print Labels
                        </a>
                        <a href="<?= \App\Core\Controller::url('/m/inward') ?>" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-box-arrow-in-down me-1"></i> Inward Restock
                        </a>
                        <a href="<?= \App\Core\Controller::url('/m/outward') ?>" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Outward Delivery
                        </a>
                    </div>
                `;
            } else {
                statusBadge.className = 'badge bg-danger';
                statusBadge.innerText = 'Not Found';
                container.innerHTML = `
                    <div class="alert alert-danger mb-0">
                        <h6 class="fw-bold"><i class="bi bi-shield-x me-1"></i> Lookup Error</h6>
                        <p class="mb-0 small">${res.message || 'Variant not found in current concern.'}</p>
                    </div>
                `;
            }
        })
        .catch(err => {
            statusBadge.className = 'badge bg-danger';
            statusBadge.innerText = 'Error';
            container.innerHTML = `
                <div class="alert alert-danger mb-0">
                    <h6 class="fw-bold">Lookup Failed</h6>
                    <p class="mb-0 small">${err.message}</p>
                </div>
            `;
        });
}
</script>
