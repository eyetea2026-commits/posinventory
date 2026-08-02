@extends('admin.layout')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Administrator/Reports.css') }}">
@endpush

@section('header')
    <div class="header-title">
        <h1>Reports & Analytics</h1>
        <p>Select a report type and date range — the list below updates immediately</p>
    </div>
@endsection

@section('content')
    <!-- Summary Stats -->
    <div class="stats-grid">
        <div class="stat-card" id="filteredRevenueCard">
            <div class="stat-icon green">
                <i class="fas fa-peso-sign"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Revenue <span class="text-muted" style="font-weight:400;">(Selected Range)</span></div>
                <div class="stat-value" id="filteredRevenueValue">₱{{ number_format($sales->total_revenue ?? 0, 2) }}</div>
            </div>
        </div>
    </div>

    <p class="text-muted mt-4 mb-2" style="font-size:0.82rem;">
        <i class="fas fa-circle-info"></i> The cards below always show today/this-week/this-month regardless of the filter below — they're quick reference points, not affected by the Report Type / Date Range picker.
    </p>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Today's Sales</div>
                <div class="stat-value">₱{{ number_format($todaySales->total ?? 0, 2) }}</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon cyan">
                <i class="fas fa-calendar-week"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">This Week</div>
                <div class="stat-value">₱{{ number_format($weekSales->total ?? 0, 2) }}</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">This Month</div>
                <div class="stat-value">₱{{ number_format($monthSales->total ?? 0, 2) }}</div>
            </div>
        </div>
    </div>

    <div id="dateRangeErrorBanner" class="alert alert-danger mt-4" style="{{ $dateRangeError ? '' : 'display:none;' }}">
        <i class="fas fa-circle-exclamation"></i>
        <span id="dateRangeErrorText">{{ $dateRangeError }}</span>
    </div>

    <!-- Report Type / Date Range -->
    <div class="card mt-4">
        <div class="card-header">
            <div>
                <h2 class="card-title">Report</h2>
                <p class="card-subtitle">Choose a type and (optionally) a date range — the list updates as you change either</p>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <div class="dropdown" style="position:relative;">
                    <button type="button" id="downloadMenuBtn" class="btn btn-secondary" title="Download">
                        <i class="fas fa-download"></i> Download
                    </button>
                    <div id="downloadMenu" style="display:none; position:absolute; right:0; top:calc(100% + 4px); background:#0f172a; border:1px solid #334155; border-radius:10px; min-width:180px; z-index:20; box-shadow: 0 12px 28px rgba(0,0,0,0.4);">
                        <a href="#" id="printPreviewLink" target="_blank" style="display:flex; align-items:center; gap:8px; padding:10px 14px; color:#f8fafc; text-decoration:none;"><i class="fas fa-print"></i> Print Preview</a>
                        <a href="#" id="exportPdfLink" style="display:flex; align-items:center; gap:8px; padding:10px 14px; color:#f8fafc; text-decoration:none;"><i class="fas fa-file-pdf"></i> Export as PDF</a>
                        <a href="#" id="exportCsvLink" style="display:flex; align-items:center; gap:8px; padding:10px 14px; color:#f8fafc; text-decoration:none;"><i class="fas fa-file-csv"></i> Export as CSV</a>
                        <a href="#" id="exportExcelLink" style="display:flex; align-items:center; gap:8px; padding:10px 14px; color:#f8fafc; text-decoration:none;"><i class="fas fa-file-excel"></i> Export as Excel</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
            <div class="form-group">
                <label class="form-label">Report Type</label>
                <select id="reportTypeSelect" class="form-select">
                    <option value="sales" {{ $reportType === 'sales' ? 'selected' : '' }}>Sales Report</option>
                    <option value="inventory" {{ $reportType === 'inventory' ? 'selected' : '' }}>Inventory Report</option>
                    <option value="orders" {{ $reportType === 'orders' ? 'selected' : '' }}>Purchase Report</option>
                    <option value="damage" {{ $reportType === 'damage' ? 'selected' : '' }}>Damage Report</option>
                    <option value="returns" {{ $reportType === 'returns' ? 'selected' : '' }}>Return Report</option>
                    <option value="supplier" {{ $reportType === 'supplier' ? 'selected' : '' }}>Supplier Report</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Start Date</label>
                <input type="date" id="reportDateFrom" class="form-input" value="{{ $dateFrom }}" @if($dateTo) max="{{ $dateTo }}" @endif>
                <span class="form-error" id="dateFromError"></span>
            </div>
            <div class="form-group">
                <label class="form-label">End Date</label>
                <input type="date" id="reportDateTo" class="form-input" value="{{ $dateTo }}" @if($dateFrom) min="{{ $dateFrom }}" @endif>
                <span class="form-error" id="dateToError"></span>
            </div>
        </div>

        <div class="form-group" style="margin-top:4px;">
            <label class="form-label">Quick Range</label>
            <div id="datePresetGroup" style="display:flex; flex-wrap:wrap; gap:8px;">
                <button type="button" class="btn btn-sm btn-secondary date-preset-btn" data-preset="today">Today</button>
                <button type="button" class="btn btn-sm btn-secondary date-preset-btn" data-preset="yesterday">Yesterday</button>
                <button type="button" class="btn btn-sm btn-secondary date-preset-btn" data-preset="this_week">This Week</button>
                <button type="button" class="btn btn-sm btn-secondary date-preset-btn" data-preset="last_week">Last Week</button>
                <button type="button" class="btn btn-sm btn-secondary date-preset-btn" data-preset="this_month">This Month</button>
                <button type="button" class="btn btn-sm btn-secondary date-preset-btn" data-preset="last_month">Last Month</button>
                <button type="button" class="btn btn-sm btn-secondary date-preset-btn" data-preset="this_year">This Year</button>
                <button type="button" class="btn btn-sm btn-secondary date-preset-btn" data-preset="custom">Custom</button>
            </div>
        </div>
    </div>

    <div id="reportBodyContainer">
        @include('admin.reports.partials.report-body', [
            'reportType' => $reportType,
            'salesRows' => $salesRows,
            'inventoryRows' => $inventoryRows,
            'stockAdjustmentRows' => $stockAdjustmentRows,
            'orderRows' => $orderRows,
            'returnRows' => $returnRows,
            'damageRows' => $damageRows,
            'supplierRows' => $supplierRows,
        ])
    </div>

    {{-- Report Details modal: shared by every Report Type's "View Details"
         row action. Lives outside #reportBodyContainer (which gets wholesale
         replaced on every type/date change) so it survives those refreshes.
         The body is rendered generically from whatever {sections} the
         admin.reports.details endpoint returns for the given type/id, so a
         future report type needs no new modal markup or JS — only a new
         section builder server-side. --}}
    <div id="reportDetailsModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="reportDetailsTitle" aria-hidden="true">
        <div class="modal modal-report-details">
            <div class="modal-header">
                <h2 class="modal-title" id="reportDetailsTitle"><i class="fas fa-file-lines"></i> Report Details</h2>
                <button type="button" class="modal-close no-print" onclick="closeReportDetailsModal()" aria-label="Close">&times;</button>
            </div>
            <div id="reportDetailsBody">
                <p class="text-muted">Loading...</p>
            </div>
            <div class="modal-footer no-print">
                <button type="button" class="btn btn-secondary" onclick="printReportDetails()"><i class="fas fa-print"></i> Print Report</button>
                <button type="button" class="btn btn-primary" onclick="closeReportDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    <style>
        .modal-report-details { max-width: 820px; }
        .report-section-heading { margin: 0 0 14px; font-size: 1rem; font-weight: 600; color: var(--text-primary, #f8fafc); }
        .report-section-divider { border: none; border-top: 1px solid var(--border, #334155); margin: 20px 0; }
        .report-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
        .report-detail-item {
            padding: 12px;
            background: var(--bg-hover, rgba(30, 41, 59, 0.6));
            border: 1px solid var(--border, rgba(148, 163, 184, 0.2));
            border-radius: 10px;
        }
        .report-detail-item label {
            display: block;
            font-size: 0.7rem;
            color: var(--text-secondary, #94a3b8);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }
        .report-detail-item span { font-weight: 600; color: var(--text-primary, #f8fafc); font-size: 0.92rem; word-break: break-word; }

        @media print {
            body.printing-report-details * { visibility: hidden !important; }
            body.printing-report-details #reportDetailsModal,
            body.printing-report-details #reportDetailsModal * { visibility: visible !important; }
            body.printing-report-details #reportDetailsModal {
                position: absolute !important;
                inset: auto !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                background: #fff !important;
                display: block !important;
                padding: 0 !important;
            }
            body.printing-report-details #reportDetailsModal .modal {
                max-width: 100% !important;
                width: 100% !important;
                max-height: none !important;
                overflow: visible !important;
                box-shadow: none !important;
                transform: none !important;
                background: #fff !important;
                color: #000 !important;
                border: none !important;
                padding: 0 !important;
            }
            body.printing-report-details .no-print { display: none !important; }
            body.printing-report-details .modal-title,
            body.printing-report-details .report-section-heading { color: #0f172a !important; }
            body.printing-report-details .report-detail-item { background: #f8fafc !important; border: 1px solid #cbd5e1 !important; }
            body.printing-report-details .report-detail-item label { color: #475569 !important; }
            body.printing-report-details .report-detail-item span { color: #0f172a !important; }
            body.printing-report-details table.table th,
            body.printing-report-details table.table td { color: #0f172a !important; border-color: #cbd5e1 !important; }
        }
    </style>

    <script>
        function escapeReportDetailHtml(value) {
            if (value === null || value === undefined || value === '') return 'N/A';
            return String(value)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function renderReportSections(sections) {
            return (sections || []).map(function (section) {
                let html = `<h4 class="report-section-heading">${escapeReportDetailHtml(section.heading)}</h4>`;

                if (section.fields && section.fields.length) {
                    html += '<div class="report-detail-grid">';
                    section.fields.forEach(function (f) {
                        html += `<div class="report-detail-item"><label>${escapeReportDetailHtml(f.label)}</label><span>${escapeReportDetailHtml(f.value)}</span></div>`;
                    });
                    html += '</div>';
                }

                if (section.table) {
                    if (!section.table.rows || !section.table.rows.length) {
                        html += '<p class="text-muted" style="font-size:0.85rem;">No records.</p>';
                    } else {
                        html += '<div class="table-container"><table class="table"><thead><tr>';
                        section.table.columns.forEach(function (c) { html += `<th>${escapeReportDetailHtml(c)}</th>`; });
                        html += '</tr></thead><tbody>';
                        section.table.rows.forEach(function (row) {
                            html += '<tr>' + row.map(function (cell) { return `<td>${escapeReportDetailHtml(cell)}</td>`; }).join('') + '</tr>';
                        });
                        html += '</tbody></table></div>';
                    }
                }

                return html;
            }).join('<hr class="report-section-divider">');
        }

        function viewReportDetails(type, id) {
            const modal = document.getElementById('reportDetailsModal');
            const body = document.getElementById('reportDetailsBody');
            const title = document.getElementById('reportDetailsTitle');

            body.innerHTML = '<p class="text-muted">Loading...</p>';
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');

            fetch(`{{ route('admin.reports.details') }}?type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then((r) => r.json())
                .then((data) => {
                    if (data.error) {
                        body.innerHTML = `<p class="text-muted">${escapeReportDetailHtml(data.error)}</p>`;
                        return;
                    }
                    title.innerHTML = `<i class="fas fa-file-lines"></i> ${escapeReportDetailHtml(data.title)}`;
                    body.innerHTML = renderReportSections(data.sections);
                })
                .catch(() => {
                    body.innerHTML = '<p class="text-muted">Failed to load report details.</p>';
                });
        }

        function closeReportDetailsModal() {
            const modal = document.getElementById('reportDetailsModal');
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
        }

        function printReportDetails() {
            document.body.classList.add('printing-report-details');
            window.print();
        }

        window.addEventListener('afterprint', function () {
            document.body.classList.remove('printing-report-details');
        });

        document.getElementById('reportDetailsModal').addEventListener('mousedown', function (e) {
            if (e.target === this) closeReportDetailsModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeReportDetailsModal();
        });
    </script>

    <script>
        (function () {
            const typeSelect = document.getElementById('reportTypeSelect');
            const dateFrom = document.getElementById('reportDateFrom');
            const dateTo = document.getElementById('reportDateTo');
            const dateFromError = document.getElementById('dateFromError');
            const dateToError = document.getElementById('dateToError');
            const bodyContainer = document.getElementById('reportBodyContainer');
            const errorBanner = document.getElementById('dateRangeErrorBanner');
            const errorText = document.getElementById('dateRangeErrorText');
            const pdfLink = document.getElementById('exportPdfLink');
            const csvLink = document.getElementById('exportCsvLink');
            const excelLink = document.getElementById('exportExcelLink');
            const printPreviewLink = document.getElementById('printPreviewLink');
            const downloadMenuBtn = document.getElementById('downloadMenuBtn');
            const downloadMenu = document.getElementById('downloadMenu');
            const filteredRevenueValue = document.getElementById('filteredRevenueValue');
            const presetButtons = document.querySelectorAll('.date-preset-btn');
            const previewUrl = '{{ route('admin.reports.preview') }}';
            const exportBaseUrl = '{{ route('admin.reports.export') }}';
            const printBaseUrl = '{{ route('admin.reports.print') }}';

            // Keeps each date input's HTML5 min/max in sync with the other
            // field's current value, so the browser itself blocks picking an
            // End Date before Start Date (rather than only catching it after
            // a round-trip to the server).
            function syncDateConstraints() {
                dateTo.min = dateFrom.value || '';
                dateFrom.max = dateTo.value || '';
            }

            function currentParams() {
                const params = new URLSearchParams({ type: typeSelect.value });
                if (dateFrom.value) params.set('date_from', dateFrom.value);
                if (dateTo.value) params.set('date_to', dateTo.value);
                return params;
            }

            // The visible report list is always current for whatever's
            // selected, so downloads always target exactly that — no
            // separate "preview then unlock" step needed.
            function refreshDownloadTargets() {
                const params = currentParams();
                [['pdf', pdfLink], ['csv', csvLink], ['excel', excelLink]].forEach(([format, el]) => {
                    const p = new URLSearchParams(params);
                    p.set('format', format);
                    el.href = exportBaseUrl + '?' + p.toString();
                });
                printPreviewLink.href = printBaseUrl + '?' + params.toString();
            }

            // Guards against out-of-order responses: if the admin changes
            // the type/dates again before an in-flight fetch resolves, only
            // the response matching the LATEST request is allowed to paint
            // the page — otherwise a slower, stale response could overwrite
            // newer, correct results.
            let requestSequence = 0;

            function refreshReportBody() {
                dateFromError.textContent = '';
                dateToError.textContent = '';

                if (dateFrom.value && dateTo.value && dateTo.value < dateFrom.value) {
                    dateToError.textContent = 'End Date cannot be earlier than Start Date.';
                    return;
                }

                const thisRequest = ++requestSequence;

                fetch(previewUrl + '?' + currentParams().toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                })
                    .then((r) => r.json())
                    .then((data) => {
                        if (thisRequest !== requestSequence) return;
                        bodyContainer.innerHTML = data.html;
                        if (data.dateRangeError) {
                            errorText.textContent = data.dateRangeError;
                            errorBanner.style.display = '';
                        } else {
                            errorBanner.style.display = 'none';
                        }
                        if (typeof data.totalRevenue === 'number') {
                            filteredRevenueValue.textContent = '₱' + data.totalRevenue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                        refreshDownloadTargets();
                    })
                    .catch(() => {
                        if (thisRequest !== requestSequence) return;
                        bodyContainer.innerHTML = '<p style="color:#fca5a5;padding:20px;text-align:center;">Failed to load report. Please try again.</p>';
                    });
            }

            [typeSelect, dateFrom, dateTo].forEach((el) => el.addEventListener('change', function () {
                syncDateConstraints();
                refreshReportBody();
                updateActivePreset();
            }));

            // Presets just fill the same two date inputs the manual pickers
            // use, then run through the exact same syncDateConstraints() +
            // refreshReportBody() path — no separate request logic to keep
            // in sync with the manual-entry flow.
            function pad(n) { return String(n).padStart(2, '0'); }
            function toDateInputValue(d) { return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`; }

            function presetRange(preset) {
                const now = new Date();
                const startOfDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate());

                switch (preset) {
                    case 'today': {
                        const d = startOfDay(now);
                        return [d, d];
                    }
                    case 'yesterday': {
                        const d = new Date(now); d.setDate(d.getDate() - 1);
                        const y = startOfDay(d);
                        return [y, y];
                    }
                    case 'this_week': {
                        const d = startOfDay(now);
                        const day = d.getDay(); // 0=Sun..6=Sat
                        const monday = new Date(d); monday.setDate(d.getDate() - ((day + 6) % 7));
                        const sunday = new Date(monday); sunday.setDate(monday.getDate() + 6);
                        return [monday, sunday];
                    }
                    case 'last_week': {
                        const d = startOfDay(now);
                        const day = d.getDay();
                        const thisMonday = new Date(d); thisMonday.setDate(d.getDate() - ((day + 6) % 7));
                        const lastMonday = new Date(thisMonday); lastMonday.setDate(thisMonday.getDate() - 7);
                        const lastSunday = new Date(lastMonday); lastSunday.setDate(lastMonday.getDate() + 6);
                        return [lastMonday, lastSunday];
                    }
                    case 'this_month': {
                        const start = new Date(now.getFullYear(), now.getMonth(), 1);
                        const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                        return [start, end];
                    }
                    case 'last_month': {
                        const start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                        const end = new Date(now.getFullYear(), now.getMonth(), 0);
                        return [start, end];
                    }
                    case 'this_year': {
                        const start = new Date(now.getFullYear(), 0, 1);
                        const end = new Date(now.getFullYear(), 11, 31);
                        return [start, end];
                    }
                    default:
                        return null;
                }
            }

            function updateActivePreset() {
                presetButtons.forEach((btn) => btn.classList.remove('btn-primary'));
            }

            presetButtons.forEach((btn) => {
                btn.addEventListener('click', function () {
                    updateActivePreset();

                    if (btn.dataset.preset === 'custom') {
                        btn.classList.add('btn-primary');
                        dateFrom.focus();
                        return;
                    }

                    const range = presetRange(btn.dataset.preset);
                    if (!range) return;
                    const [start, end] = range;

                    dateFrom.value = toDateInputValue(start);
                    dateTo.value = toDateInputValue(end);
                    btn.classList.add('btn-primary');
                    syncDateConstraints();
                    refreshReportBody();
                });
            });

            downloadMenuBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                downloadMenu.style.display = downloadMenu.style.display === 'none' ? 'block' : 'none';
            });
            document.addEventListener('click', function () { downloadMenu.style.display = 'none'; });

            syncDateConstraints();
            refreshDownloadTargets();
        })();

        @if(session('success'))
            Swal.fire({
                title: 'Success',
                text: '{{ session('success') }}',
                icon: 'success',
                confirmButtonColor: '#10b981',
                timer: 3000,
                timerProgressBar: true
            });
        @endif
    </script>
@endsection
