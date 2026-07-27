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
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-peso-sign"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value">₱{{ number_format($sales->total_revenue ?? 0, 2) }}</div>
            </div>
        </div>
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
                        <a href="#" id="exportPdfLink" style="display:flex; align-items:center; gap:8px; padding:10px 14px; color:#f8fafc; text-decoration:none;"><i class="fas fa-file-pdf"></i> Export as PDF</a>
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
            const excelLink = document.getElementById('exportExcelLink');
            const downloadMenuBtn = document.getElementById('downloadMenuBtn');
            const downloadMenu = document.getElementById('downloadMenu');
            const previewUrl = '{{ route('admin.reports.preview') }}';
            const exportBaseUrl = '{{ route('admin.reports.export') }}';

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
                [['pdf', pdfLink], ['excel', excelLink]].forEach(([format, el]) => {
                    const p = new URLSearchParams(params);
                    p.set('format', format);
                    el.href = exportBaseUrl + '?' + p.toString();
                });
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
            }));

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
