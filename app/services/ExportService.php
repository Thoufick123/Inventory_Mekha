<?php
// app/services/ExportService.php - Multi-Format Export Engine (Excel, PDF, CSV) with Date Scopes

namespace App\Services;

class ExportService {

    /**
     * Resolve date range based on period selection
     */
    public static function resolveDateRange(?string $period, ?string $singleDate = null, ?string $fromDate = null, ?string $toDate = null): array {
        $period = strtolower(trim($period ?? 'all'));
        $today = date('Y-m-d');

        switch ($period) {
            case 'single_day':
            case 'day':
            case 'today':
                $date = !empty($singleDate) ? $singleDate : $today;
                return [
                    'period'     => 'single_day',
                    'start_date' => $date,
                    'end_date'   => $date,
                    'label'      => 'Single Day: ' . date('d M Y', strtotime($date)),
                ];

            case 'yesterday':
                $date = date('Y-m-d', strtotime('-1 day'));
                return [
                    'period'     => 'single_day',
                    'start_date' => $date,
                    'end_date'   => $date,
                    'label'      => 'Single Day (Yesterday): ' . date('d M Y', strtotime($date)),
                ];

            case 'this_month':
            case 'month':
                $start = date('Y-m-01');
                $end   = date('Y-m-t');
                return [
                    'period'     => 'this_month',
                    'start_date' => $start,
                    'end_date'   => $end,
                    'label'      => 'This Month (' . date('F Y') . ')',
                ];

            case 'last_month':
                $start = date('Y-m-01', strtotime('first day of last month'));
                $end   = date('Y-m-t', strtotime('last day of last month'));
                return [
                    'period'     => 'last_month',
                    'start_date' => $start,
                    'end_date'   => $end,
                    'label'      => 'Last Month (' . date('F Y', strtotime('last month')) . ')',
                ];

            case '3_months':
            case 'quarter':
                $start = date('Y-m-d', strtotime('-3 months'));
                return [
                    'period'     => '3_months',
                    'start_date' => $start,
                    'end_date'   => $today,
                    'label'      => 'Last 3 Months (' . date('d M Y', strtotime($start)) . ' to ' . date('d M Y', strtotime($today)) . ')',
                ];

            case '6_months':
            case 'half_year':
                $start = date('Y-m-d', strtotime('-6 months'));
                return [
                    'period'     => '6_months',
                    'start_date' => $start,
                    'end_date'   => $today,
                    'label'      => 'Last 6 Months (' . date('d M Y', strtotime($start)) . ' to ' . date('d M Y', strtotime($today)) . ')',
                ];

            case 'custom':
                $start = !empty($fromDate) ? $fromDate : date('Y-m-01');
                $end   = !empty($toDate) ? $toDate : $today;
                return [
                    'period'     => 'custom',
                    'start_date' => $start,
                    'end_date'   => $end,
                    'label'      => 'Custom Period (' . date('d M Y', strtotime($start)) . ' to ' . date('d M Y', strtotime($end)) . ')',
                ];

            case 'all':
            default:
                return [
                    'period'     => 'all',
                    'start_date' => null,
                    'end_date'   => null,
                    'label'      => 'All Time (Full Historical Data)',
                ];
        }
    }

    /**
     * Download as Native Excel (.xls)
     */
    public static function downloadExcel(string $filename, string $title, array $metadata, array $headers, array $rows, array $totals = []): void {
        if (!str_ends_with(strtolower($filename), '.xls')) {
            $filename .= '.xls';
        }

        if (!headers_sent()) {
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
            header('Cache-Control: max-age=0');
            header('Pragma: public');
        }

        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">' . "\n";
        echo '<head>' . "\n";
        echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . "\n";
        echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Report</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->' . "\n";
        echo '<style>' . "\n";
        echo 'body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; color: #1e293b; }' . "\n";
        echo '.report-title { font-size: 16pt; font-weight: bold; color: #1e3a8a; }' . "\n";
        echo '.meta-label { font-weight: bold; color: #475569; width: 140px; }' . "\n";
        echo '.meta-val { color: #0f172a; }' . "\n";
        echo 'th { background-color: #2563eb; color: #ffffff; font-weight: bold; text-align: left; padding: 8px 10px; border: 0.5pt solid #1d4ed8; }' . "\n";
        echo 'td { padding: 6px 10px; border: 0.5pt solid #cbd5e1; }' . "\n";
        echo 'tr.even { background-color: #f8fafc; }' . "\n";
        echo 'tr.odd { background-color: #ffffff; }' . "\n";
        echo 'tr.totals td { background-color: #e2e8f0; font-weight: bold; color: #0f172a; border-top: 1.5pt solid #334155; }' . "\n";
        echo '.num { text-align: right; mso-number-format:"\#\,\#\#0"; }' . "\n";
        echo '.currency { text-align: right; mso-number-format:"₹\#\,\#\#0\.00"; }' . "\n";
        echo '.text { mso-number-format:"\@"; }' . "\n";
        echo '</style>' . "\n";
        echo '</head>' . "\n";
        echo '<body>' . "\n";

        // Title & Header Information
        echo '<table border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 15px;">' . "\n";
        echo '<tr><td colspan="' . max(1, count($headers)) . '" class="report-title" style="border:none;">' . htmlspecialchars($title) . '</td></tr>' . "\n";
        echo '<tr><td colspan="' . max(1, count($headers)) . '" style="border:none; color:#64748b; font-size:10pt;">Generated on ' . date('d M Y, h:i A') . '</td></tr>' . "\n";
        echo '<tr><td colspan="' . max(1, count($headers)) . '" style="border:none; height:8px;"></td></tr>' . "\n";

        foreach ($metadata as $lbl => $val) {
            echo '<tr><td class="meta-label" style="border:none;">' . htmlspecialchars($lbl) . ':</td><td colspan="' . (count($headers) - 1) . '" class="meta-val" style="border:none;">' . htmlspecialchars($val) . '</td></tr>' . "\n";
        }
        echo '<tr><td colspan="' . max(1, count($headers)) . '" style="border:none; height:12px;"></td></tr>' . "\n";
        echo '</table>' . "\n";

        // Main Data Table
        echo '<table border="1" cellpadding="5" cellspacing="0">' . "\n";
        echo '<thead><tr>' . "\n";
        foreach ($headers as $h) {
            $align = (str_contains(strtolower($h), 'spend') || str_contains(strtolower($h), 'amount') || str_contains(strtolower($h), 'rate') || str_contains(strtolower($h), 'orders') || str_contains(strtolower($h), 'pcs')) ? 'right' : 'left';
            echo '<th style="text-align:' . $align . ';">' . htmlspecialchars($h) . '</th>';
        }
        echo "\n</tr></thead>" . "\n";
        echo '<tbody>' . "\n";

        $i = 0;
        foreach ($rows as $row) {
            $cls = ($i++ % 2 === 0) ? 'even' : 'odd';
            echo '<tr class="' . $cls . '">' . "\n";
            foreach ($row as $cell) {
                if (is_numeric($cell) && !is_string($cell)) {
                    $clsAttr = (floor($cell) != $cell) ? 'currency' : 'num';
                    echo '<td class="' . $clsAttr . '">' . htmlspecialchars((string)$cell) . '</td>';
                } elseif (is_string($cell) && str_starts_with($cell, '₹')) {
                    $numericVal = (float)str_replace(['₹', ','], '', $cell);
                    echo '<td class="currency">' . number_format($numericVal, 2, '.', '') . '</td>';
                } else {
                    echo '<td class="text">' . htmlspecialchars((string)$cell) . '</td>';
                }
            }
            echo "\n</tr>" . "\n";
        }

        // Totals row if provided
        if (!empty($totals)) {
            echo '<tr class="totals">' . "\n";
            foreach ($totals as $tot) {
                if (is_numeric($tot)) {
                    echo '<td class="currency">' . number_format((float)$tot, 2, '.', '') . '</td>';
                } elseif (is_string($tot) && str_starts_with($tot, '₹')) {
                    $numericVal = (float)str_replace(['₹', ','], '', $tot);
                    echo '<td class="currency">' . number_format($numericVal, 2, '.', '') . '</td>';
                } else {
                    echo '<td>' . htmlspecialchars((string)$tot) . '</td>';
                }
            }
            echo "\n</tr>" . "\n";
        }

        echo '</tbody>' . "\n";
        echo '</table>' . "\n";
        echo '</body>' . "\n";
        echo '</html>' . "\n";
        if (php_sapi_name() !== 'cli') {
            exit;
        }
    }

    /**
     * Download as Clean CSV (.csv) with UTF-8 BOM
     */
    public static function downloadCsv(string $filename, array $headers, array $rows, array $totals = []): void {
        if (!str_ends_with(strtolower($filename), '.csv')) {
            $filename .= '.csv';
        }

        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
            header('Cache-Control: max-age=0');
            header('Pragma: public');
        }

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for proper Excel display
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($out, $headers);
        foreach ($rows as $r) {
            fputcsv($out, array_values($r));
        }
        if (!empty($totals)) {
            fputcsv($out, array_values($totals));
        }

        fclose($out);
        if (php_sapi_name() !== 'cli') {
            exit;
        }
    }

    /**
     * Render Printable / Downloadable PDF View
     */
    public static function renderPdfView(string $title, array $metadata, array $headers, array $rows, array $totals = []): void {
        $fallbackUrl = \App\Core\Auth::isSuperAdmin() 
            ? \App\Core\Controller::url('/admin/dashboard') 
            : \App\Core\Controller::url('/m/dashboard');
        $returnUrl = !empty($_GET['return_url']) 
            ? $_GET['return_url'] 
            : (!empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $fallbackUrl);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title><?= htmlspecialchars($title) ?> - Printable PDF Report</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
            <style>
                @page {
                    size: A4 landscape;
                    margin: 10mm 12mm 12mm 12mm;
                }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    background: #f8fafc;
                    color: #0f172a;
                    font-size: 12px;
                }
                .pdf-container {
                    max-width: 1100px;
                    margin: 20px auto;
                    background: #ffffff;
                    padding: 28px 36px;
                    border-radius: 12px;
                    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.07);
                }
                .no-print-bar {
                    background: #0f172a;
                    color: #ffffff;
                    padding: 12px 24px;
                    border-radius: 10px;
                    margin-bottom: 20px;
                }
                table th {
                    background: #1e293b !important;
                    color: #ffffff !important;
                    font-size: 11px;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    padding: 8px 10px !important;
                }
                table td {
                    padding: 6px 10px !important;
                    font-size: 11.5px;
                    border-color: #e2e8f0 !important;
                }
                @media print {
                    body {
                        background: #ffffff !important;
                    }
                    .pdf-container {
                        box-shadow: none !important;
                        padding: 0 !important;
                        margin: 0 !important;
                        max-width: 100% !important;
                    }
                    .no-print {
                        display: none !important;
                    }
                }
            </style>
        </head>
        <body>
            <div class="pdf-container">
                <!-- Action Bar for Screen View -->
                <div class="no-print no-print-bar d-flex justify-content-between align-items-center mb-4">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-pdf fs-4 text-danger"></i>
                        <div>
                            <strong>Print / Save as PDF Document</strong>
                            <div class="small text-light text-opacity-75">Click "Save as PDF" in your print dialog to download.</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-light fw-bold px-3 shadow-xs" onclick="handleReportBack()" title="Back to Previous Page">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>
                        <button type="button" class="btn btn-sm btn-primary fw-bold px-4 shadow-xs" onclick="window.print()" style="background: #4f46e5; border:none;">
                            <i class="bi bi-printer me-1"></i> Save as PDF / Print
                        </button>
                    </div>
                </div>

                <!-- Report Corporate Header -->
                <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-primary text-uppercase px-2 py-1" style="font-size: 0.75rem;">GRM Inventory</span>
                            <span class="text-muted small">&bull; Official Report</span>
                        </div>
                        <h4 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($title) ?></h4>
                        <div class="text-secondary small">Generated on <?= date('d M Y, h:i A') ?></div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold fs-6 text-dark"><?= htmlspecialchars($metadata['Concern / Outlet'] ?? 'GRM Retail Flagship Store') ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($metadata['Period Scope'] ?? 'All Time') ?></div>
                        <div class="text-muted small font-monospace">Report Ref: RPT-<?= date('Ymd-His') ?></div>
                    </div>
                </div>

                <!-- Metadata Summary Badges -->
                <div class="d-flex flex-wrap gap-2 mb-3 py-2 bg-light rounded-3 px-3">
                    <?php foreach ($metadata as $k => $v): ?>
                        <div class="me-3">
                            <span class="text-muted small fw-semibold"><?= htmlspecialchars($k) ?>:</span>
                            <strong class="text-dark small ms-1"><?= htmlspecialchars($v) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Main Data Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <?php foreach ($headers as $h): 
                                    $align = (str_contains(strtolower($h), 'spend') || str_contains(strtolower($h), 'amount') || str_contains(strtolower($h), 'rate') || str_contains(strtolower($h), 'orders') || str_contains(strtolower($h), 'pcs')) ? 'text-end' : 'text-start';
                                ?>
                                    <th class="<?= $align ?>"><?= htmlspecialchars($h) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <?php foreach ($row as $cell): 
                                        $align = (is_numeric($cell) || (is_string($cell) && str_starts_with($cell, '₹'))) ? 'text-end font-monospace' : 'text-start';
                                    ?>
                                        <td class="<?= $align ?>"><?= htmlspecialchars((string)$cell) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if (!empty($totals)): ?>
                            <tfoot class="table-dark fw-bold">
                                <tr>
                                    <?php foreach ($totals as $tot): 
                                        $align = (is_numeric($tot) || (is_string($tot) && str_starts_with($tot, '₹'))) ? 'text-end font-monospace' : 'text-start';
                                    ?>
                                        <td class="<?= $align ?>"><?= htmlspecialchars((string)$tot) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            </tfoot>
                        <?php endif; ?>
                    </table>
                </div>

                <!-- Footer Signoff -->
                <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-4 text-muted small" style="font-size: 0.72rem;">
                    <div>Confidential &bull; Generated by Invento Multi-Concern ERP System</div>
                    <div>Page 1 of 1</div>
                </div>
            </div>

            <script>
                function handleReportBack() {
                    const fallbackTarget = <?= json_encode($returnUrl, JSON_UNESCAPED_SLASHES) ?>;

                    // 1. If this window was opened as a popup or new tab (_blank), close it
                    try {
                        if (window.opener && !window.opener.closed) {
                            window.close();
                        }
                    } catch (e) {}

                    // 2. If history in this tab has no prior entries, try closing the tab
                    try {
                        if (window.history.length <= 1) {
                            window.close();
                        }
                    } catch (e) {}

                    // 3. Fallback: If still open after 120ms (e.g. browser blocked window.close), navigate
                    setTimeout(function() {
                        if (document.referrer && document.referrer !== window.location.href) {
                            window.location.href = document.referrer;
                        } else if (fallbackTarget) {
                            window.location.href = fallbackTarget;
                        } else if (window.history.length > 1) {
                            window.history.back();
                        } else {
                            window.location.href = '<?= \App\Core\Controller::url('/') ?>';
                        }
                    }, 120);
                }

                // Auto trigger print when loaded
                window.addEventListener('load', function() {
                    // Small delay to allow CSS rendering
                    setTimeout(function() {
                        if (window.location.search.includes('autoprint=1')) {
                            window.print();
                        }
                    }, 500);
                });
            </script>
        </body>
        </html>
        <?php
        if (php_sapi_name() !== 'cli') {
            exit;
        }
    }
}
