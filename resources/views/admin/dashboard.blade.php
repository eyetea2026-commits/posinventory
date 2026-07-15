@extends('admin.layout')

@section('title', 'Administrator Dashboard - CCTV Express TACURONG')

@push('styles')
    <style>
        .header-title .greeting {
            font-size: 0.8rem;
            color: #60a5fa;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .stat-card {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 20px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
            opacity: 0;
            animation: fadeInUp 0.4s ease forwards;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stat-card:hover {
            transform: translateY(-4px);
            border-color: rgba(59, 130, 246, 0.3);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        .stat-icon.blue { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
        .stat-icon.green { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .stat-icon.yellow { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
        .stat-icon.red { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .stat-icon.purple { background: rgba(168, 85, 247, 0.15); color: #a78bfa; }
        .stat-icon.cyan { background: rgba(6, 182, 212, 0.15); color: #22d3ee; }
        .stat-icon.orange { background: rgba(249, 115, 22, 0.15); color: #fb923c; }

        .stat-content { flex: 1; min-width: 0; }

        .stat-label {
            font-size: 0.82rem;
            color: #94a3b8;
            margin-bottom: 4px;
            font-weight: 500;
        }

        .stat-value {
            font-size: 1.7rem;
            font-weight: 700;
            color: #f8fafc;
            letter-spacing: -0.02em;
        }

        .stat-subtitle {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 4px;
        }

        .stat-trend {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.75rem;
            font-weight: 700;
            margin-top: 4px;
        }

        .stat-trend.up { color: #34d399; }
        .stat-trend.down { color: #f87171; }
        .stat-trend.new { color: #60a5fa; }

        .dashboard-section-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #f8fafc;
            margin: 0 0 16px;
            letter-spacing: -0.01em;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
            gap: 24px;
            align-items: stretch;
        }

        .chart-card {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 20px;
            padding: 24px;
            display: flex;
            flex-direction: column;
        }

        .chart-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .chart-title {
            font-size: 1rem;
            font-weight: 600;
            color: #f8fafc;
            margin: 0;
        }

        .chart-toggle-group {
            display: inline-flex;
            background: rgba(148, 163, 184, 0.08);
            border-radius: 10px;
            padding: 3px;
            gap: 2px;
        }

        .chart-toggle-btn {
            border: none;
            background: transparent;
            color: #94a3b8;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .chart-toggle-btn.active {
            background: rgba(59, 130, 246, 0.2);
            color: #93c5fd;
        }

        .chart-toggle-btn:hover:not(.active) {
            color: #cbd5e1;
        }

        .chart-canvas-wrap {
            position: relative;
            height: 300px;
            flex: 1;
        }

        .card {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 20px;
            overflow: hidden;
        }

        .card-header {
            padding: 24px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .card-title { margin: 0; font-size: 1.25rem; font-weight: 600; color: #f8fafc; }
        .card-subtitle { margin: 4px 0 0; font-size: 0.875rem; color: #94a3b8; }
        .card-body { padding: 24px; }

        .toolbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 20px 24px 0; flex-wrap: wrap; }

        .table-container { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 14px 16px; text-align: left; }
        .table thead th {
            position: sticky;
            top: 0;
            background: rgba(15, 23, 42, 0.95);
            color: #94a3b8;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            z-index: 1;
        }
        .table thead th a { color: inherit; text-decoration: none; }
        .table thead th a:hover { color: #60a5fa; }
        .table tbody tr { border-bottom: 1px solid rgba(148, 163, 184, 0.08); transition: background 0.2s ease; }
        .table tbody tr:hover { background: rgba(59, 130, 246, 0.05); }
        .table td { color: #e2e8f0; font-size: 0.9rem; }
        .table-scroll { max-height: 420px; overflow-y: auto; }

        .text-success { color: #34d399; }
        .text-warning { color: #fbbf24; }
        .text-danger { color: #f87171; }
        .text-muted { color: #64748b; }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .badge-warning { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .badge-info { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
        .badge-replenish { background: rgba(249, 115, 22, 0.15); color: #fb923c; }

        .stock-alert-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 300px;
            overflow-y: auto;
        }

        .stock-alert-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 14px;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 12px;
            transition: background 0.2s ease;
        }

        .stock-alert-item:hover { background: rgba(59, 130, 246, 0.08); }

        .stock-alert-name { font-weight: 600; color: #f8fafc; font-size: 0.9rem; }
        .stock-alert-qty { font-size: 0.78rem; color: #94a3b8; }

        .stock-alert-view {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            border: none;
            background: rgba(148, 163, 184, 0.1);
            color: #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .stock-alert-view:hover { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }

        .activity-list { display: flex; flex-direction: column; gap: 14px; max-height: 420px; overflow-y: auto; }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 14px;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 12px;
            transition: all 0.2s ease;
        }

        .activity-item:hover { background: rgba(59, 130, 246, 0.08); }

        .activity-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            flex-shrink: 0;
        }

        .activity-content { flex: 1; min-width: 0; }
        .activity-title { font-weight: 600; color: #f8fafc; margin-bottom: 2px; font-size: 0.9rem; }
        .activity-meta { font-size: 0.78rem; color: #94a3b8; }

        .empty-state { text-align: center; padding: 40px 24px; }
        .empty-icon {
            width: 56px; height: 56px; border-radius: 16px;
            background: rgba(148, 163, 184, 0.1);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; color: #64748b; margin: 0 auto 14px;
        }
        .empty-title { font-size: 1rem; font-weight: 600; color: #e2e8f0; margin: 0 0 6px; }
        .empty-text { color: #64748b; font-size: 0.85rem; margin: 0; }

        .mt-4 { margin-top: 24px; }
        .mb-4 { margin-bottom: 24px; }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .charts-grid { grid-template-columns: 1fr; }
            .stat-card { padding: 16px; }
            .stat-icon { width: 46px; height: 46px; font-size: 1.15rem; }
            .stat-value { font-size: 1.35rem; }
            .chart-canvas-wrap { height: 240px; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endpush

@php
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good Morning' : ($hour < 18 ? 'Good Afternoon' : 'Good Evening');
@endphp

@section('header')
    <div class="greeting">{{ $greeting }}</div>
    <h1>Welcome Back, {{ auth()->user()->name ?? 'Administrator' }}!</h1>
    <p><span id="dashboardCurrentDate">{{ now()->format('l, F j, Y') }}</span> &middot; <span id="dashboardCurrentTime">{{ now()->format('h:i A') }}</span></p>
@endsection

@section('content')
    {{-- KPI Summary Cards --}}
    <div class="stats-grid mb-4">
        <div class="stat-card" style="animation-delay: 0.02s">
            <div class="stat-icon green"><i class="fas fa-peso-sign"></i></div>
            <div class="stat-content">
                <div class="stat-label">Sales Today</div>
                <div class="stat-value" data-counter data-value="{{ $salesToday }}" data-prefix="&#8369;" data-decimals="2">&#8369;0.00</div>
                @if(is_null($salesChangePct))
                    <div class="stat-trend new"><i class="fas fa-sparkles"></i> New</div>
                @elseif($salesChangePct >= 0)
                    <div class="stat-trend up"><i class="fas fa-arrow-up"></i> {{ $salesChangePct }}% vs yesterday</div>
                @else
                    <div class="stat-trend down"><i class="fas fa-arrow-down"></i> {{ abs($salesChangePct) }}% vs yesterday</div>
                @endif
            </div>
        </div>
        <div class="stat-card" style="animation-delay: 0.06s">
            <div class="stat-icon blue"><i class="fas fa-receipt"></i></div>
            <div class="stat-content">
                <div class="stat-label">Transactions</div>
                <div class="stat-value" data-counter data-value="{{ $transactionsToday }}">0</div>
                <div class="stat-subtitle">Today</div>
            </div>
        </div>
        <div class="stat-card" style="animation-delay: 0.1s">
            <div class="stat-icon purple"><i class="fas fa-boxes"></i></div>
            <div class="stat-content">
                <div class="stat-label">Products</div>
                <div class="stat-value" id="statTotalProducts" data-counter data-value="{{ $totalProducts }}">0</div>
                <div class="stat-subtitle">Total registered</div>
            </div>
        </div>
        <div class="stat-card" style="animation-delay: 0.14s">
            <div class="stat-icon cyan"><i class="fas fa-warehouse"></i></div>
            <div class="stat-content">
                <div class="stat-label">Inventory Value</div>
                <div class="stat-value" id="statInventoryValue" data-counter data-value="{{ $inventoryValue }}" data-prefix="&#8369;" data-decimals="0">&#8369;0</div>
                <div class="stat-subtitle">At cost price</div>
            </div>
        </div>
        <div class="stat-card" style="animation-delay: 0.18s">
            <div class="stat-icon orange"><i class="fas fa-truck"></i></div>
            <div class="stat-content">
                <div class="stat-label">Suppliers</div>
                <div class="stat-value" data-counter data-value="{{ $totalSuppliers }}">0</div>
                <div class="stat-subtitle">Registered</div>
            </div>
        </div>
    </div>

    {{-- Section 1: Sales Analytics --}}
    <h2 class="dashboard-section-title mt-4">Sales Analytics</h2>
    <div class="charts-grid">
        <div class="chart-card">
            <div class="chart-card-header">
                <h3 class="chart-title">Sales Trend</h3>
                <div class="chart-toggle-group" data-toggle="trend">
                    <button type="button" class="chart-toggle-btn active" data-range="daily">Daily</button>
                    <button type="button" class="chart-toggle-btn" data-range="weekly">Weekly</button>
                    <button type="button" class="chart-toggle-btn" data-range="monthly">Monthly</button>
                    <button type="button" class="chart-toggle-btn" data-range="yearly">Yearly</button>
                </div>
            </div>
            <div class="chart-canvas-wrap">
                <canvas id="salesTrendChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <div class="chart-card-header">
                <h3 class="chart-title">Sales by Category</h3>
            </div>
            @if(empty($categoryChart['data']))
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-chart-pie"></i></div>
                    <p class="empty-title">No Sales Yet</p>
                    <p class="empty-text">Category breakdown will appear once sales are recorded.</p>
                </div>
            @else
                <div class="chart-canvas-wrap">
                    <canvas id="categoryChart"></canvas>
                </div>
            @endif
        </div>
    </div>

    {{-- Section 2: Inventory Analytics --}}
    <h2 class="dashboard-section-title mt-4">Inventory Analytics</h2>
    <div class="charts-grid">
        <div class="chart-card">
            <div class="chart-card-header">
                <h3 class="chart-title">Inventory Status</h3>
            </div>
            <div class="chart-canvas-wrap">
                <canvas id="inventoryStatusChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <div class="chart-card-header">
                <h3 class="chart-title">Stock Alerts</h3>
            </div>
            <div id="stockAlertsContainer">
                @include('admin.dashboard.partials.stock-alerts', ['stockAlerts' => $stockAlerts])
            </div>
        </div>
    </div>

    {{-- Section 3: Product Performance --}}
    <h2 class="dashboard-section-title mt-4">Product Performance</h2>
    <div class="charts-grid">
        <div class="chart-card">
            <div class="chart-card-header">
                <h3 class="chart-title">Top Selling Products</h3>
                <div class="chart-toggle-group" data-toggle="top">
                    <button type="button" class="chart-toggle-btn active" data-metric="quantity">Quantity</button>
                    <button type="button" class="chart-toggle-btn" data-metric="revenue">Revenue</button>
                </div>
            </div>
            @if($topSelling->isEmpty())
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-chart-bar"></i></div>
                    <p class="empty-title">No Sales Data Yet</p>
                    <p class="empty-text">Top sellers will appear once sales are recorded.</p>
                </div>
            @else
                <div class="chart-canvas-wrap">
                    <canvas id="topSellingChart"></canvas>
                </div>
            @endif
        </div>
        <div class="chart-card">
            <div class="chart-card-header">
                <h3 class="chart-title">Least Selling Products</h3>
            </div>
            @if($leastSelling->isEmpty())
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-chart-bar"></i></div>
                    <p class="empty-title">No Sales Data Yet</p>
                    <p class="empty-text">Slow-moving products will appear here once sales exist.</p>
                </div>
            @else
                <div class="chart-canvas-wrap">
                    <canvas id="leastSellingChart"></canvas>
                </div>
            @endif
        </div>
    </div>

    {{-- Section 4: Sales Analysis --}}
    <h2 class="dashboard-section-title mt-4">Sales Analysis</h2>
    <div class="charts-grid">
        <div class="chart-card">
            <div class="chart-card-header">
                <h3 class="chart-title">Payment Methods</h3>
            </div>
            @if($paymentMethods->isEmpty())
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-credit-card"></i></div>
                    <p class="empty-title">No Payments Yet</p>
                    <p class="empty-text">Payment method distribution will appear here once sales exist.</p>
                </div>
            @else
                <div class="chart-canvas-wrap">
                    <canvas id="paymentMethodsChart"></canvas>
                </div>
            @endif
        </div>
    </div>

    {{-- Section 5: Operational Activity --}}
    <h2 class="dashboard-section-title mt-4">Operational Activity</h2>
    <div class="charts-grid">
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Recent Transactions</h2>
                    <p class="card-subtitle">Search, sort, and page through the latest sales</p>
                </div>
            </div>
            <div class="toolbar">
                <div class="search-box">
                    <i class="search-icon fas fa-search"></i>
                    <form method="GET" action="{{ route('admin.dashboard') }}" class="w-full">
                        <input type="hidden" name="txn_sort" value="{{ $txnSort }}">
                        <input type="text" name="txn_search" value="{{ $txnSearch }}" class="search-input" placeholder="Search receipt #, customer, or cashier...">
                    </form>
                </div>
            </div>
            <div class="table-container table-scroll">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Receipt No.</th>
                            <th>Cashier</th>
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['txn_sort' => $txnSort === 'amount_desc' ? 'amount_asc' : 'amount_desc', 'txn_page' => 1]) }}">
                                    Amount <i class="fas fa-sort"></i>
                                </a>
                            </th>
                            <th>Payment Method</th>
                            <th>Status</th>
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['txn_sort' => $txnSort === 'date_asc' ? 'date_desc' : 'date_asc', 'txn_page' => 1]) }}">
                                    Date &amp; Time <i class="fas fa-sort"></i>
                                </a>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentTransactions as $transaction)
                            <tr>
                                <td>RCT-{{ str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT) }}</td>
                                <td>{{ trim(($transaction->staff?->FirstName ?? '') . ' ' . ($transaction->staff?->LastName ?? '')) ?: 'N/A' }}</td>
                                <td class="text-success">&#8369;{{ number_format($transaction->billing?->BillingAmount ?? 0, 2) }}</td>
                                <td>{{ strtoupper($transaction->billing?->payment?->PaymentMethod ?? 'N/A') }}</td>
                                <td><span class="badge badge-success">Completed</span></td>
                                <td>{{ \Illuminate\Support\Carbon::parse($transaction->SalesTransactionDate)->format('M d, Y h:i A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">No transactions match your search.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($recentTransactions->hasPages())
                <div class="pagination">
                    @if($recentTransactions->onFirstPage())
                        <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                    @else
                        <a href="{{ $recentTransactions->previousPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                    @endif

                    @foreach($recentTransactions->getUrlRange(1, $recentTransactions->lastPage()) as $page => $url)
                        <a href="{{ $url }}" class="pagination-link {{ $page == $recentTransactions->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach

                    @if($recentTransactions->hasMorePages())
                        <a href="{{ $recentTransactions->nextPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                    @else
                        <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                    @endif
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
    (function () {
        const trendData = @json($salesTrend);
        const categoryChartData = @json($categoryChart);
        const inventoryStatusData = @json($inventoryStatusChart);
        const topSellingRaw = @json($topSelling->map(fn($i) => ['label' => $i->product?->ProductName ?? 'Unknown', 'quantity' => (float) $i->total_quantity, 'revenue' => (float) $i->total_revenue]));
        const leastSellingRaw = @json($leastSelling->map(fn($i) => ['label' => $i->product?->ProductName ?? 'Unknown', 'quantity' => (float) $i->total_quantity, 'revenue' => (float) $i->total_revenue]));
        const paymentMethodsRaw = @json($paymentMethods->map(fn($p) => ['label' => strtoupper($p->method ?? 'N/A'), 'value' => (float) $p->total]));

        const PALETTE = ['#60a5fa', '#34d399', '#fbbf24', '#f87171', '#a78bfa', '#22d3ee', '#fb923c', '#94a3b8'];
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.borderColor = 'rgba(148, 163, 184, 0.1)';
        Chart.defaults.font.family = "'Public Sans', Inter, sans-serif";

        // Live clock — ticks every second so the header date/time never goes
        // stale while the dashboard is left open, without needing a reload.
        (function () {
            const dateEl = document.getElementById('dashboardCurrentDate');
            const timeEl = document.getElementById('dashboardCurrentTime');
            if (!dateEl && !timeEl) return;

            function tick() {
                const now = new Date();
                if (dateEl) {
                    dateEl.textContent = now.toLocaleDateString('en-US', {
                        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
                    });
                }
                if (timeEl) {
                    timeEl.textContent = now.toLocaleTimeString('en-US', {
                        hour: '2-digit', minute: '2-digit', hour12: true,
                    });
                }
            }

            tick();
            setInterval(tick, 1000);
        })();

        // Animated KPI counters
        document.querySelectorAll('[data-counter]').forEach((el) => {
            const target = parseFloat(el.dataset.value || '0');
            const decimals = parseInt(el.dataset.decimals || '0', 10);
            const prefix = el.dataset.prefix || '';
            const duration = 700;
            const start = performance.now();

            function tick(now) {
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                const value = target * eased;
                el.textContent = prefix + value.toLocaleString('en-US', {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals,
                });
                if (progress < 1) requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
        });

        // Sales Trend (line, daily/weekly/monthly/yearly toggle)
        const trendCanvas = document.getElementById('salesTrendChart');
        if (trendCanvas) {
            const trendChart = new Chart(trendCanvas, {
                type: 'line',
                data: {
                    labels: trendData.daily.labels,
                    datasets: [{
                        label: 'Revenue',
                        data: trendData.daily.data,
                        borderColor: '#60a5fa',
                        backgroundColor: 'rgba(96, 165, 250, 0.12)',
                        tension: 0.35,
                        fill: true,
                        pointRadius: 2,
                        pointHoverRadius: 5,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 500 },
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.08)' } },
                        x: { grid: { display: false } },
                    },
                },
            });

            document.querySelectorAll('[data-toggle="trend"] .chart-toggle-btn').forEach((btn) => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('[data-toggle="trend"] .chart-toggle-btn').forEach((b) => b.classList.remove('active'));
                    btn.classList.add('active');
                    const range = trendData[btn.dataset.range];
                    trendChart.data.labels = range.labels;
                    trendChart.data.datasets[0].data = range.data;
                    trendChart.update();
                });
            });
        }

        // Sales by Category (doughnut)
        const categoryCanvas = document.getElementById('categoryChart');
        if (categoryCanvas) {
            new Chart(categoryCanvas, {
                type: 'doughnut',
                data: {
                    labels: categoryChartData.labels,
                    datasets: [{
                        data: categoryChartData.data,
                        backgroundColor: PALETTE,
                        borderColor: '#0f172a',
                        borderWidth: 2,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 500 },
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14 } },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => `${ctx.label}: ₱${Number(ctx.raw).toLocaleString('en-US', { minimumFractionDigits: 2 })}`,
                            },
                        },
                    },
                },
            });
        }

        // Inventory Status (horizontal bar)
        const inventoryCanvas = document.getElementById('inventoryStatusChart');
        let inventoryStatusChartInstance = null;
        if (inventoryCanvas) {
            inventoryStatusChartInstance = new Chart(inventoryCanvas, {
                type: 'bar',
                data: {
                    labels: inventoryStatusData.labels,
                    datasets: [{
                        data: inventoryStatusData.data,
                        backgroundColor: ['#34d399', '#fbbf24', '#f87171', '#a78bfa'],
                        borderRadius: 6,
                    }],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 500 },
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.08)' } },
                        y: { grid: { display: false } },
                    },
                },
            });
        }

        // Top / Least Selling Products (horizontal bar, quantity/revenue toggle for Top)
        function buildSellingChart(canvasId, rows, metric) {
            const canvas = document.getElementById(canvasId);
            if (!canvas || !rows.length) return null;
            return new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: rows.map((r) => r.label),
                    datasets: [{
                        data: rows.map((r) => r[metric]),
                        backgroundColor: '#60a5fa',
                        borderRadius: 6,
                    }],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 500 },
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.08)' } },
                        y: { grid: { display: false } },
                    },
                },
            });
        }

        let topSellingChart = buildSellingChart('topSellingChart', topSellingRaw, 'quantity');
        buildSellingChart('leastSellingChart', leastSellingRaw, 'quantity');

        document.querySelectorAll('[data-toggle="top"] .chart-toggle-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('[data-toggle="top"] .chart-toggle-btn').forEach((b) => b.classList.remove('active'));
                btn.classList.add('active');
                if (!topSellingChart) return;
                topSellingChart.data.datasets[0].data = topSellingRaw.map((r) => r[btn.dataset.metric]);
                topSellingChart.update();
            });
        });

        // Payment Methods (doughnut)
        const paymentCanvas = document.getElementById('paymentMethodsChart');
        if (paymentCanvas) {
            new Chart(paymentCanvas, {
                type: 'doughnut',
                data: {
                    labels: paymentMethodsRaw.map((p) => p.label),
                    datasets: [{
                        data: paymentMethodsRaw.map((p) => p.value),
                        backgroundColor: PALETTE,
                        borderColor: '#0f172a',
                        borderWidth: 2,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 500 },
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14 } },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => `${ctx.label}: ₱${Number(ctx.raw).toLocaleString('en-US', { minimumFractionDigits: 2 })}`,
                            },
                        },
                    },
                },
            });
        }

        // Poll inventory-derived widgets (Products, Inventory Value, Inventory
        // Status chart, Stock Alerts) so sales/refunds/receiving/adjustments/
        // damage recording made elsewhere show up here without a page reload.
        // Pauses while the tab is hidden to avoid pointless requests.
        function refreshInventoryWidgets() {
            if (document.hidden) return;

            fetch('{{ route('admin.dashboard.live-inventory') }}', { headers: { 'Accept': 'application/json' } })
                .then((response) => response.json())
                .then((data) => {
                    const productsEl = document.getElementById('statTotalProducts');
                    if (productsEl) productsEl.textContent = Number(data.totalProducts).toLocaleString('en-US');

                    const valueEl = document.getElementById('statInventoryValue');
                    if (valueEl) valueEl.textContent = '₱' + Number(data.inventoryValue).toLocaleString('en-US', { maximumFractionDigits: 0 });

                    if (inventoryStatusChartInstance) {
                        inventoryStatusChartInstance.data.labels = data.inventoryStatusChart.labels;
                        inventoryStatusChartInstance.data.datasets[0].data = data.inventoryStatusChart.data;
                        inventoryStatusChartInstance.update();
                    }

                    const alertsContainer = document.getElementById('stockAlertsContainer');
                    if (alertsContainer) alertsContainer.innerHTML = data.stockAlertsHtml;
                })
                .catch(() => {
                    // Non-fatal — keep showing the last known values.
                });
        }
        setInterval(refreshInventoryWidgets, 10000);
    })();
    </script>
@endpush
