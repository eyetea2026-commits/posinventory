@extends('admin.layout')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Administrator/Reports.css') }}">
@endpush

@section('header')
    <div class="header-title">
        <h1>Reports & Analytics</h1>
        <p>Generate and export operational reports - REQ105 to REQ107</p>
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

    <!-- Filter & Export -->
    <div class="card mt-4">
        <div class="card-header">
            <div>
                <h2 class="card-title">Filter & Export Reports</h2>
                <p class="card-subtitle">Select date range and export options</p>
            </div>
        </div>
        <form method="GET" action="{{ route('admin.reports.index') }}" class="filter-form">
            <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="form-group">
                    <label class="form-label">Report Type</label>
                    <select name="type" class="form-select">
                        <option value="sales" {{ $reportType === 'sales' ? 'selected' : '' }}>Sales Report</option>
                        <option value="inventory" {{ $reportType === 'inventory' ? 'selected' : '' }}>Inventory Report</option>
                        <option value="orders" {{ $reportType === 'orders' ? 'selected' : '' }}>Purchase Orders</option>
                        <option value="returns" {{ $reportType === 'returns' ? 'selected' : '' }}>Returns Report</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="date_from" class="form-input" value="{{ $dateFrom }}">
                </div>
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="date" name="date_to" class="form-input" value="{{ $dateTo }}">
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end; gap: 10px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="{{ route('admin.reports.index') }}" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>

        <div class="export-buttons" style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="{{ route('admin.reports.export', ['type' => $reportType, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'format' => 'csv']) }}" class="btn btn-success">
                <i class="fas fa-file-csv"></i> Export CSV
            </a>
        </div>
    </div>

    <!-- Inventory Status -->
    @if($reportType === 'inventory')
    <div class="card mt-4">
        <div class="card-header">
            <div>
                <h2 class="card-title">Inventory Status</h2>
                <p class="card-subtitle">Current stock levels overview</p>
            </div>
        </div>
        <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Available</div>
                    <div class="stat-value">{{ number_format($inventoryCount - $lowStock - $outOfStock) }}</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Low Stock</div>
                    <div class="stat-value">{{ number_format($lowStock) }}</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Out of Stock</div>
                    <div class="stat-value">{{ number_format($outOfStock) }}</div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Best Selling Products -->
    @if($reportType === 'sales')
    <div class="card mt-4">
        <div class="card-header">
            <div>
                <h2 class="card-title">Best Selling Products</h2>
                <p class="card-subtitle">Top performing products by quantity sold</p>
            </div>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product Name</th>
                        <th>Units Sold</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bestSelling as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $item->product?->ProductName ?? 'Unknown Product' }}</td>
                            <td>{{ number_format($item->total_sold) }}</td>
                            <td class="text-success">₱{{ number_format($item->total_revenue ?? 0, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">No sales data available</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="card mt-4">
        <div class="card-header">
            <div>
                <h2 class="card-title">Recent Transactions</h2>
                <p class="card-subtitle">Latest sales transactions</p>
            </div>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Receipt #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentSales as $transaction)
                        <tr>
                            <td><code>RCT-{{ str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT) }}</code></td>
                            <td>{{ \Carbon\Carbon::parse($transaction->SalesTransactionDate)->format('M d, Y h:i A') }}</td>
                            <td>{{ $transaction->CustomerName ?? 'Walk-in' }}</td>
                            <td class="text-success">₱{{ number_format($transaction->billing?->BillingAmount ?? 0, 2) }}</td>
                            <td><span class="badge badge-success">Completed</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">No recent transactions</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if($reportType === 'orders' || $reportType === 'returns')
    <div class="card mt-4">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ $reportType === 'orders' ? 'Purchase Orders' : 'Returns' }} Report</h2>
                <p class="card-subtitle">This report type is available as a CSV export above — the on-screen summary above (sales/inventory totals) still applies across all report types.</p>
            </div>
        </div>
    </div>
    @endif
@endsection