<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ \App\Http\Controllers\Admin\ReportController::typeLabel($type) }} Report</title>
    <style>
        /* A4 landscape (setPaper in ReportController::export()). */
        @page { margin: 15mm 20mm 18mm; }
        /* DejaVu Sans ships with dompdf and has the ₱ glyph; the generic
           sans-serif maps to Helvetica, which printed every ₱ as "?". */
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1a1a1a; }
        .company-header { text-align: center; border-bottom: 2px solid #1a1a1a; padding-bottom: 8px; margin-bottom: 10px; }
        .company-logo { width: 46px; height: 46px; margin: 0 auto 6px; display: block; }
        .company-header h1 { margin: 0; font-size: 18px; letter-spacing: 0.04em; }
        .company-header p { margin: 2px 0 0; color: #555; font-size: 10px; }
        h2.report-title { font-size: 15px; margin: 10px 0 8px; text-transform: capitalize; text-align: center; }
        .report-meta { text-align: center; color: #444; margin: 0 0 8px; }
        .report-meta p { margin: 2px 0; }
        .report-filters { border: 1px solid #ccc; background: #f7f7f7; padding: 6px 10px; margin: 0 0 10px; }
        .report-filters-title { font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #666; font-weight: bold; margin-bottom: 3px; }
        .report-filters p { margin: 1px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #ccc; padding: 5px 6px; text-align: left; word-wrap: break-word; }
        td.col-money, td.col-total, td.col-date, td.col-qty { white-space: nowrap; word-wrap: normal; }
        th { background: #f0f0f0; }
        .col-date, td.col-date { text-align: center; }
        .col-qty, td.col-qty { text-align: center; }
        .col-money, td.col-money { text-align: right; }
        .col-total, td.col-total { text-align: right; font-weight: bold; }
        /* Status: yellow waiting, green approved/settled, red declined/written off. */
        td.status-warning { color: #b45309; font-weight: bold; }
        td.status-success { color: #047857; font-weight: bold; }
        td.status-danger { color: #dc2626; font-weight: bold; }
        .no-records { text-align: center; padding: 30px 15px; color: #555; }
        .no-records strong { display: block; font-size: 12px; color: #222; margin-bottom: 3px; }
        p.grand-total { text-align: right; font-weight: bold; margin-top: 10px; }
        .report-summary { margin-top: 12px; border-top: 1px solid #1a1a1a; padding-top: 6px; page-break-inside: avoid; }
        .report-summary-title { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: bold; margin-bottom: 4px; }
        /* dompdf has no flexbox: the label and value are placed at the row's two ends. */
        .report-summary .summary-row { position: relative; width: 320px; height: 15px; margin-left: auto; }
        .report-summary .summary-row span:first-child { position: absolute; left: 0; top: 1px; }
        .report-summary .summary-row span:last-child { position: absolute; right: 0; top: 1px; }
        .signature-area { display: flex; justify-content: center; margin-top: 50px; gap: 16px; page-break-inside: avoid; break-inside: avoid; }
        .signature-block { flex: 0 0 32%; width: 32%; margin: 0 auto; text-align: center; page-break-inside: avoid; break-inside: avoid; }
        .signature-line { border-top: 1px solid #1a1a1a; padding-top: 5px; margin-top: 40px; }
        .signature-line small { display: block; color: #333; }
        .signature-line small.sig-caption { color: #777; margin-top: 2px; }
        .doc-footer-inline { text-align: center; margin-top: 14px; font-size: 9px; color: #777; }
    </style>
</head>
<body>
    {{-- "Page X of Y" at the foot of every page is drawn by
         ReportController::export() once the page count is known. --}}
    @include('admin.reports.partials.report-header', ['type' => $type, 'dateFrom' => $dateFrom, 'dateTo' => $dateTo])

    @include('admin.reports.partials.print-table', ['type' => $type, 'rows' => $rows])

    @include('admin.reports.partials.report-footer', ['pdf' => true])
</body>
</html>
