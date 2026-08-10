<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ ucfirst($type) }} Report</title>
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: sans-serif; font-size: 11px; color: #1a1a1a; }
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
        th, td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; }
        th { background: #f0f0f0; }
        .col-date, td.col-date { text-align: center; }
        .col-qty, td.col-qty { text-align: center; }
        .col-money, td.col-money { text-align: right; }
        .col-total, td.col-total { text-align: right; font-weight: bold; }
        .no-records { text-align: center; padding: 30px 15px; color: #555; }
        .no-records strong { display: block; font-size: 12px; color: #222; margin-bottom: 3px; }
        p.grand-total { text-align: right; font-weight: bold; margin-top: 10px; }
        .report-summary { margin-top: 12px; border-top: 1px solid #1a1a1a; padding-top: 6px; }
        .report-summary-title { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: bold; margin-bottom: 4px; }
        .report-summary .summary-row { display: flex; justify-content: space-between; padding: 2px 0; width: 320px; margin-left: auto; }
        .signature-area { display: flex; justify-content: space-between; margin-top: 50px; gap: 16px; }
        .signature-block { flex: 1; text-align: center; }
        .signature-line { border-top: 1px solid #1a1a1a; padding-top: 5px; margin-top: 40px; }
        .signature-line small { display: block; color: #333; }
        .signature-line small.sig-caption { color: #777; margin-top: 2px; }
        .doc-footer-inline { text-align: center; margin-top: 14px; font-size: 9px; color: #777; }
        .pagefooter { position: fixed; bottom: -25px; left: 0; right: 0; text-align: center; font-size: 9px; color: #777; }
        .pagenum:before { content: counter(page) " of " counter(pages); }
    </style>
</head>
<body>
    <div class="pagefooter">Page <span class="pagenum"></span></div>

    @include('admin.reports.partials.report-header', ['type' => $type, 'dateFrom' => $dateFrom, 'dateTo' => $dateTo])

    @include('admin.reports.partials.print-table', ['type' => $type, 'rows' => $rows])

    @include('admin.reports.partials.report-footer', ['pdf' => true])
</body>
</html>
