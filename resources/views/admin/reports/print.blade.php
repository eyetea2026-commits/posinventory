<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ ucfirst($type) }} Report</title>
    <style>
        @page { size: A4 {{ $landscape ?? false ? 'landscape' : 'portrait' }}; margin: 16mm; }
        body { font-family: sans-serif; font-size: 13px; color: #1a1a1a; max-width: {{ $landscape ?? false ? '1150px' : '900px' }}; margin: 0 auto; padding: 30px; }
        .company-header { text-align: center; border-bottom: 2px solid #1a1a1a; padding-bottom: 14px; margin-bottom: 12px; }
        .company-logo { width: 56px; height: 56px; object-fit: contain; margin: 0 auto 8px; display: block; }
        .company-header h1 { margin: 0; font-size: 22px; letter-spacing: 0.04em; }
        .company-header p { margin: 4px 0 0; color: #555; font-size: 12px; }
        h2.report-title { font-size: 16px; margin: 12px 0 8px; text-transform: capitalize; text-align: center; }
        .report-meta { text-align: center; color: #444; margin: 0 0 10px; }
        .report-meta p { margin: 2px 0; }
        .report-filters { border: 1px solid #ccc; background: #f7f7f7; padding: 8px 12px; margin: 0 0 14px; border-radius: 4px; }
        .report-filters-title { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #666; font-weight: bold; margin-bottom: 4px; }
        .report-filters p { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #ccc; padding: 7px 9px; text-align: left; }
        th { background: #f0f0f0; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; }
        .no-records { text-align: center; padding: 40px 20px; color: #666; }
        .no-records strong { display: block; font-size: 14px; color: #333; margin-bottom: 4px; }
        p.grand-total { text-align: right; font-weight: bold; margin-top: 12px; }
        .report-summary { margin-top: 16px; border-top: 1px solid #1a1a1a; padding-top: 8px; }
        .report-summary-title { font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: bold; margin-bottom: 6px; }
        .report-summary .summary-row { display: flex; justify-content: space-between; padding: 3px 0; width: 320px; margin-left: auto; }
        .signature-area { display: flex; justify-content: space-between; margin-top: 60px; gap: 20px; }
        .signature-block { flex: 1; text-align: center; }
        .signature-line { border-top: 1px solid #1a1a1a; padding-top: 6px; margin-top: 50px; }
        .signature-line small { display: block; color: #333; }
        .signature-line small.sig-caption { color: #777; margin-top: 2px; }
        .doc-footer-inline { text-align: center; margin-top: 16px; padding-top: 10px; border-top: 1px solid #eee; font-size: 10.5px; color: #888; }
        .print-btn { position: fixed; top: 20px; right: 20px; padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; }
        .col-date, td.col-date { text-align: center; }
        .col-qty, td.col-qty { text-align: center; }
        .col-money, td.col-money { text-align: right; }
        .col-total, td.col-total { text-align: right; font-weight: bold; }
        @media print { .no-print { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">Print</button>

    @include('admin.reports.partials.report-header', ['type' => $type, 'dateFrom' => $dateFrom, 'dateTo' => $dateTo])

    @include('admin.reports.partials.print-table', ['type' => $type, 'rows' => $rows])

    @include('admin.reports.partials.report-footer', ['pdf' => false])
</body>
</html>
