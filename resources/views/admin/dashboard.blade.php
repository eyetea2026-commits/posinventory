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

        /* Sales Analytics / Product Performance side-by-side row */
        .analytics-performance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 24px;
            align-items: stretch;
        }

        .analytics-col {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .analytics-col .chart-card {
            flex: 1;
        }

        .chart-legend {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .chart-legend-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #94a3b8;
        }

        .chart-legend-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .chart-legend-dot.high { background: #34d399; box-shadow: 0 0 8px rgba(52, 211, 153, 0.6); }
        .chart-legend-dot.medium { background: #fbbf24; box-shadow: 0 0 8px rgba(251, 191, 36, 0.6); }
        .chart-legend-dot.low { background: #f87171; box-shadow: 0 0 8px rgba(248, 113, 113, 0.6); }

        /* Product Performance ranked bar list — rank, name, units, and % all
           live inside the bar itself; the fill's width is the only thing
           that encodes relative performance, so the text row is a full-width
           overlay independent of how far the fill actually reaches. */
        .perf-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
            flex: 1;
            overflow-y: auto;
        }

        .perf-item {
            position: relative;
            height: 52px;
            border-radius: 14px;
            background: rgba(148, 163, 184, 0.08);
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.35), 0 3px 8px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .perf-item:hover {
            transform: translateY(-2px);
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.35), 0 10px 20px rgba(0, 0, 0, 0.35);
        }

        .perf-bar-fill {
            position: absolute;
            inset: 0;
            width: 0;
            border-radius: 14px;
            background: linear-gradient(90deg, #3b82f6, #60a5fa);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.25), 0 2px 6px rgba(59, 130, 246, 0.45);
            transition: width 0.6s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .perf-item:nth-child(1) .perf-bar-fill { background: linear-gradient(90deg, #d97706, #fbbf24); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.25), 0 2px 6px rgba(251, 191, 36, 0.45); }
        .perf-item:nth-child(2) .perf-bar-fill { background: linear-gradient(90deg, #475569, #94a3b8); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.25), 0 2px 6px rgba(148, 163, 184, 0.4); }
        .perf-item:nth-child(3) .perf-bar-fill { background: linear-gradient(90deg, #c2410c, #fb923c); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.25), 0 2px 6px rgba(251, 146, 60, 0.45); }

        .perf-bar-content {
            position: relative;
            z-index: 1;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 0 16px;
        }

        .perf-bar-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .perf-rank {
            width: 24px;
            height: 24px;
            border-radius: 8px;
            background: rgba(15, 23, 42, 0.35);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            font-weight: 800;
            flex-shrink: 0;
        }

        .perf-name {
            font-size: 0.85rem;
            font-weight: 700;
            color: #fff;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.6);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .perf-bar-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .perf-units {
            font-size: 0.75rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.85);
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.6);
            white-space: nowrap;
        }

        .perf-pct {
            font-size: 0.85rem;
            font-weight: 800;
            color: #fff;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.6);
            min-width: 36px;
            text-align: right;
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

        /* Loading skeleton shown over a chart's canvas until Chart.js has
           finished its first render (covers the CDN script-load window and
           gives the "loading" requirement real meaning instead of a
           decorative flash) — shared by the Inventory Status and Sales by
           Category charts. */
        .chart-skeleton {
            position: absolute;
            inset: 0;
            border-radius: 12px;
            background: linear-gradient(90deg, rgba(148, 163, 184, 0.06) 25%, rgba(148, 163, 184, 0.16) 37%, rgba(148, 163, 184, 0.06) 63%);
            background-size: 400% 100%;
            animation: chart-skeleton-shimmer 1.4s ease infinite;
            z-index: 2;
            opacity: 1;
            transition: opacity 0.3s ease;
        }

        .chart-skeleton.is-hidden {
            opacity: 0;
            pointer-events: none;
        }

        @keyframes chart-skeleton-shimmer {
            0% { background-position: 100% 50%; }
            100% { background-position: 0 50%; }
        }

        @media (prefers-reduced-motion: reduce) {
            .chart-skeleton { animation: none; }
        }

        /* Sales by Category — interactive ring chart + synced legend */
        .ring-chart-layout {
            display: flex;
            align-items: center;
            gap: 28px;
            flex: 1;
            flex-wrap: wrap;
        }

        .ring-canvas-wrap {
            width: 180px;
            height: 180px;
            flex: 0 0 auto;
            margin: 0 auto;
        }

        .ring-center-label {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            text-align: center;
            padding: 0 20px;
        }

        .ring-center-value {
            font-size: 1rem;
            font-weight: 700;
            color: #f8fafc;
            line-height: 1.2;
        }

        .ring-center-caption {
            font-size: 0.68rem;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 3px;
        }

        .ring-legend-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
            min-width: 200px;
        }

        .ring-legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            background: transparent;
            border: 1px solid transparent;
            border-radius: 10px;
            padding: 7px 8px;
            cursor: pointer;
            text-align: left;
            width: 100%;
            font: inherit;
            color: inherit;
            transition: background 0.15s ease, border-color 0.15s ease, opacity 0.2s ease;
        }

        .ring-legend-item:hover,
        .ring-legend-item.is-active,
        .ring-legend-item:focus-visible {
            background: rgba(148, 163, 184, 0.08);
            border-color: rgba(148, 163, 184, 0.16);
        }

        .ring-legend-item:focus-visible {
            outline: 2px solid #60a5fa;
            outline-offset: 1px;
        }

        .ring-legend-item.is-dimmed {
            opacity: 0.4;
        }

        .ring-legend-marker {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .ring-legend-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
            flex: 1;
        }

        .ring-legend-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: #e2e8f0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .ring-legend-value {
            font-size: 0.72rem;
            color: #94a3b8;
        }

        .ring-legend-progress-track {
            height: 4px;
            border-radius: 4px;
            background: rgba(148, 163, 184, 0.12);
            overflow: hidden;
        }

        .ring-legend-progress-fill {
            display: block;
            height: 100%;
            width: 0;
            border-radius: 4px;
            transition: width 0.6s cubic-bezier(0.22, 1, 0.36, 1);
        }

        @media (max-width: 640px) {
            .ring-chart-layout { flex-direction: column; }
            .ring-canvas-wrap { width: 160px; height: 160px; }
            .ring-legend-list { width: 100%; }
        }

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

        @media (max-width: 640px) {
            .analytics-performance-grid { grid-template-columns: 1fr; }
            .chart-legend { justify-content: flex-start; }
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
                <div class="stat-value" data-counter data-value="{{ $salesToday }}" data-prefix="₱" data-decimals="2">₱0.00</div>
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
                <div class="stat-value" id="statInventoryValue" data-counter data-value="{{ $inventoryValue }}" data-prefix="₱" data-decimals="2">₱0.00</div>
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

    {{-- Section 1: Sales Analytics + Product Performance, side by side --}}
    <div class="analytics-performance-grid mt-4">
        <div class="analytics-col">
            <h2 class="dashboard-section-title">Sales Analytics</h2>
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
                <div class="chart-legend">
                    <span class="chart-legend-item"><span class="chart-legend-dot high"></span> High Sales</span>
                    <span class="chart-legend-item"><span class="chart-legend-dot medium"></span> Medium Sales</span>
                    <span class="chart-legend-item"><span class="chart-legend-dot low"></span> Low Sales</span>
                </div>
                <div class="chart-canvas-wrap">
                    <canvas id="salesTrendChart"></canvas>
                </div>
            </div>
        </div>
        <div class="analytics-col">
            <h2 class="dashboard-section-title">Product Performance</h2>
            <div class="chart-card">
                <div class="chart-card-header">
                    <h3 class="chart-title">Product Ranking</h3>
                    <div class="chart-toggle-group" data-toggle="performance">
                        <button type="button" class="chart-toggle-btn active" data-tab="top">Top Selling</button>
                        <button type="button" class="chart-toggle-btn" data-tab="least">Least Selling</button>
                    </div>
                </div>
                @if($topSelling->isEmpty() && $leastSelling->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-ranking-star"></i></div>
                        <p class="empty-title">No Sales Data Yet</p>
                        <p class="empty-text">Product rankings will appear once sales are recorded.</p>
                    </div>
                @else
                    <div id="productPerformanceList" class="perf-list"></div>
                @endif
            </div>
        </div>
    </div>

    {{-- Section 2: Inventory Analytics --}}
    <h2 class="dashboard-section-title mt-4">Inventory Analytics</h2>
    <div class="charts-grid">
        <div class="chart-card">
            <div class="chart-card-header">
                <h3 class="chart-title">Inventory Status</h3>
                <div class="chart-toggle-group" data-toggle="inventory-view">
                    <button type="button" class="chart-toggle-btn active" data-view="category">By Category</button>
                    <button type="button" class="chart-toggle-btn" data-view="product">By Product</button>
                </div>
            </div>
            @if(empty($inventoryStatusChart['byCategory']['data']) && empty($inventoryStatusChart['byProduct']['data']))
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-boxes-stacked"></i></div>
                    <p class="empty-title">No Inventory Data Available</p>
                    <p class="empty-text">Inventory quantities will appear once products are stocked.</p>
                </div>
            @else
                <div class="chart-canvas-wrap">
                    <div class="chart-skeleton" id="inventoryChartSkeleton"></div>
                    <canvas id="inventoryStatusChart" role="img" aria-label="Inventory quantity by category or product"></canvas>
                </div>
            @endif
        </div>
        <div class="chart-card">
            <div class="chart-card-header">
                <h3 class="chart-title">Stock Alerts</h3>
            </div>
            <div id="stockAlertsContainer">
                @include('admin.dashboard.partials.stock-alerts', ['stockAlerts' => $stockAlerts])
            </div>
        </div>
        <div class="chart-card">
            <div class="chart-card-header">
                <h3 class="chart-title">Sales by Category</h3>
            </div>
            @if(empty($categoryChart['data']))
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-chart-pie"></i></div>
                    <p class="empty-title">No Sales Data Available</p>
                    <p class="empty-text">Category breakdown will appear once sales are recorded.</p>
                </div>
            @else
                <div class="ring-chart-layout">
                    <div class="chart-canvas-wrap ring-canvas-wrap">
                        <div class="chart-skeleton" id="categoryChartSkeleton"></div>
                        <canvas id="categoryChart" role="img" aria-label="Sales by category ring chart"></canvas>
                        <div class="ring-center-label">
                            <span class="ring-center-value" id="categoryRingCenterValue">—</span>
                            <span class="ring-center-caption" id="categoryRingCenterCaption">Sales</span>
                        </div>
                    </div>
                    <div class="ring-legend-list" id="categoryLegendList"></div>
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
                                <td>{{ $transaction->staff?->user?->full_name ?? 'N/A' }}</td>
                                <td class="text-success">₱{{ number_format($transaction->billing?->BillingAmount ?? 0, 2) }}</td>
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

        const PALETTE = ['#60a5fa', '#34d399', '#fbbf24', '#f87171', '#a78bfa', '#22d3ee', '#fb923c', '#94a3b8'];
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.borderColor = 'rgba(148, 163, 184, 0.1)';
        Chart.defaults.font.family = "'Public Sans', Inter, sans-serif";

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str == null ? '' : String(str);
            return div.innerHTML;
        }

        // Lightens (positive percent) or darkens (negative) a "#rrggbb" color
        // — used by the 3D bar plugin to derive a bar's top/side face shades
        // from its single flat fill color.
        function shadeColor(hex, percent) {
            const num = parseInt(hex.replace('#', ''), 16);
            let r = (num >> 16) + percent;
            let g = ((num >> 8) & 0x00ff) + percent;
            let b = (num & 0x0000ff) + percent;
            r = Math.max(Math.min(255, r), 0);
            g = Math.max(Math.min(255, g), 0);
            b = Math.max(Math.min(255, b), 0);
            return '#' + (0x1000000 + r * 0x10000 + g * 0x100 + b).toString(16).slice(1);
        }

        // Fakes a 3D "box" look for a vertical bar chart with no extra chart
        // library: after Chart.js draws each bar's flat front face, this
        // draws a lighter parallelogram "top" cap and a darker parallelogram
        // "side" face along the right edge, matching the depth/perspective
        // effect the brief asks for.
        const bar3dDepthPlugin = {
            id: 'bar3dDepth',
            afterDatasetsDraw(chart) {
                const depth = 7;
                chart.data.datasets.forEach((dataset, datasetIndex) => {
                    const meta = chart.getDatasetMeta(datasetIndex);
                    if (meta.hidden) return;

                    const { ctx } = chart;
                    meta.data.forEach((bar, i) => {
                        const props = bar.getProps(['x', 'y', 'base', 'width'], true);
                        if (props.y === props.base) return; // zero-height bar, nothing to extrude

                        const color = Array.isArray(dataset.backgroundColor)
                            ? dataset.backgroundColor[i % dataset.backgroundColor.length]
                            : dataset.backgroundColor;
                        if (typeof color !== 'string' || !color.startsWith('#')) return;

                        const halfWidth = props.width / 2;
                        const top = Math.min(props.y, props.base);
                        const bottom = Math.max(props.y, props.base);

                        ctx.save();

                        // Side face (right edge)
                        ctx.beginPath();
                        ctx.moveTo(props.x + halfWidth, top);
                        ctx.lineTo(props.x + halfWidth + depth, top - depth);
                        ctx.lineTo(props.x + halfWidth + depth, bottom - depth);
                        ctx.lineTo(props.x + halfWidth, bottom);
                        ctx.closePath();
                        ctx.fillStyle = shadeColor(color, -35);
                        ctx.fill();

                        // Top face
                        ctx.beginPath();
                        ctx.moveTo(props.x - halfWidth, top);
                        ctx.lineTo(props.x - halfWidth + depth, top - depth);
                        ctx.lineTo(props.x + halfWidth + depth, top - depth);
                        ctx.lineTo(props.x + halfWidth, top);
                        ctx.closePath();
                        ctx.fillStyle = shadeColor(color, 25);
                        ctx.fill();

                        ctx.restore();
                    });
                });
            },
        };

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

        // Sales Trend (line, daily/weekly/monthly/yearly toggle) — smooth
        // curve, gradient fill, and per-point dots colored by how each value
        // ranks against the strongest period currently on screen (>=66% of
        // that max = high/green, >=33% = medium/orange, else low/red). The
        // thresholds are relative to the visible range rather than a fixed
        // peso amount so the coloring stays meaningful across daily vs.
        // yearly totals, which differ by orders of magnitude.
        const PERFORMANCE_COLORS = { high: '#34d399', medium: '#fbbf24', low: '#f87171' };

        function performanceColorsFor(values) {
            const max = Math.max(0, ...values);
            if (max <= 0) return values.map(() => PERFORMANCE_COLORS.low);
            return values.map((v) => {
                const ratio = v / max;
                if (ratio >= 0.66) return PERFORMANCE_COLORS.high;
                if (ratio >= 0.33) return PERFORMANCE_COLORS.medium;
                return PERFORMANCE_COLORS.low;
            });
        }

        const trendCanvas = document.getElementById('salesTrendChart');
        if (trendCanvas) {
            const trendCtx = trendCanvas.getContext('2d');
            const trendGradient = trendCtx.createLinearGradient(0, 0, 0, trendCanvas.clientHeight || 300);
            trendGradient.addColorStop(0, 'rgba(96, 165, 250, 0.32)');
            trendGradient.addColorStop(1, 'rgba(96, 165, 250, 0)');

            const trendChart = new Chart(trendCanvas, {
                type: 'line',
                data: {
                    labels: trendData.daily.labels,
                    datasets: [{
                        label: 'Revenue',
                        data: trendData.daily.data,
                        borderColor: '#60a5fa',
                        backgroundColor: trendGradient,
                        borderWidth: 2.5,
                        tension: 0.4,
                        fill: true,
                        cubicInterpolationMode: 'monotone',
                        pointStyle: 'circle',
                        pointRadius: 4,
                        pointHoverRadius: 7,
                        pointBorderWidth: 2,
                        pointBorderColor: '#0f172a',
                        pointBackgroundColor: performanceColorsFor(trendData.daily.data),
                        pointHoverBorderWidth: 2,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 600, easing: 'easeOutQuart' },
                    interaction: { mode: 'nearest', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            borderColor: 'rgba(148, 163, 184, 0.2)',
                            borderWidth: 1,
                            padding: 10,
                            titleColor: '#f8fafc',
                            bodyColor: '#cbd5e1',
                            displayColors: false,
                            callbacks: {
                                title: (items) => items[0]?.label ?? '',
                                label: (ctx) => 'Sales: ' + window.formatPeso(ctx.raw),
                            },
                        },
                    },
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
                    trendChart.data.datasets[0].pointBackgroundColor = performanceColorsFor(range.data);
                    trendChart.update();
                });
            });
        }

        // Sales by Category — interactive ring (doughnut) chart with a
        // custom hover-synced legend and a center label, replacing the
        // built-in Chart.js legend entirely so the legend can show
        // value + percentage + a progress bar per category and stay in
        // sync with which ring segment is highlighted.
        const categoryCanvas = document.getElementById('categoryChart');
        const categorySkeleton = document.getElementById('categoryChartSkeleton');
        const categoryLegendList = document.getElementById('categoryLegendList');
        const ringCenterValueEl = document.getElementById('categoryRingCenterValue');
        const ringCenterCaptionEl = document.getElementById('categoryRingCenterCaption');
        let categoryChartInstance = null;

        function categoryTotal() {
            return categoryChartData.data.reduce((sum, v) => sum + v, 0);
        }

        function setRingCenter(index) {
            if (!ringCenterValueEl || !ringCenterCaptionEl) return;
            const total = categoryTotal();
            if (index === null) {
                ringCenterValueEl.textContent = window.formatPeso(total);
                ringCenterCaptionEl.textContent = 'Sales';
            } else {
                ringCenterValueEl.textContent = window.formatPeso(categoryChartData.data[index]);
                ringCenterCaptionEl.textContent = categoryChartData.labels[index];
            }
        }

        function highlightCategory(index) {
            if (categoryChartInstance) {
                categoryChartInstance.setActiveElements(index === null ? [] : [{ datasetIndex: 0, index }]);
                categoryChartInstance.update();
            }
            if (categoryLegendList) {
                categoryLegendList.querySelectorAll('.ring-legend-item').forEach((el) => {
                    const idx = Number(el.dataset.index);
                    el.classList.toggle('is-active', idx === index);
                    el.classList.toggle('is-dimmed', index !== null && idx !== index);
                });
            }
            setRingCenter(index);
        }

        function renderCategoryLegend() {
            if (!categoryLegendList) return;
            const total = categoryTotal();

            categoryLegendList.innerHTML = categoryChartData.labels.map((label, i) => {
                const value = categoryChartData.data[i];
                const pct = total > 0 ? (value / total) * 100 : 0;
                const color = PALETTE[i % PALETTE.length];
                return `
                    <button type="button" class="ring-legend-item" data-index="${i}">
                        <span class="ring-legend-marker" style="background:${color}"></span>
                        <span class="ring-legend-info">
                            <span class="ring-legend-label" title="${escapeHtml(label)}">${escapeHtml(label)}</span>
                            <span class="ring-legend-value">${window.formatPeso(value)} &middot; ${pct.toFixed(1)}%</span>
                            <span class="ring-legend-progress-track">
                                <span class="ring-legend-progress-fill" data-target-width="${pct}%" style="background:${color}"></span>
                            </span>
                        </span>
                    </button>
                `;
            }).join('');

            requestAnimationFrame(() => {
                categoryLegendList.querySelectorAll('.ring-legend-progress-fill').forEach((el) => {
                    el.style.width = el.dataset.targetWidth;
                });
            });
        }

        if (categoryCanvas) {
            categoryChartInstance = new Chart(categoryCanvas, {
                type: 'doughnut',
                data: {
                    labels: categoryChartData.labels,
                    datasets: [{
                        data: categoryChartData.data,
                        backgroundColor: PALETTE,
                        borderColor: '#0f172a',
                        borderWidth: 2,
                        hoverOffset: 10,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    animation: {
                        duration: 700,
                        easing: 'easeOutQuart',
                        onComplete: () => { if (categorySkeleton) categorySkeleton.classList.add('is-hidden'); },
                    },
                    onHover: (evt, elements) => highlightCategory(elements.length ? elements[0].index : null),
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            borderColor: 'rgba(148, 163, 184, 0.2)',
                            borderWidth: 1,
                            padding: 10,
                            titleColor: '#f8fafc',
                            bodyColor: '#cbd5e1',
                            callbacks: {
                                label: (ctx) => {
                                    const total = categoryTotal();
                                    const pct = total > 0 ? ((ctx.raw / total) * 100).toFixed(1) : '0.0';
                                    return `${ctx.label}: ${window.formatPeso(ctx.raw)} (${pct}%)`;
                                },
                            },
                        },
                    },
                },
            });

            renderCategoryLegend();
            setRingCenter(null);

            if (categoryLegendList) {
                categoryLegendList.addEventListener('mouseover', (e) => {
                    const item = e.target.closest('.ring-legend-item');
                    if (item) highlightCategory(Number(item.dataset.index));
                });
                categoryLegendList.addEventListener('mouseout', (e) => {
                    if (e.target.closest('.ring-legend-item')) highlightCategory(null);
                });
                categoryLegendList.addEventListener('focusin', (e) => {
                    const item = e.target.closest('.ring-legend-item');
                    if (item) highlightCategory(Number(item.dataset.index));
                });
                categoryLegendList.addEventListener('focusout', (e) => {
                    if (e.target.closest('.ring-legend-item')) highlightCategory(null);
                });
            }
        }

        // Inventory Status — 3D-effect vertical bar chart, toggled between
        // quantity-by-category and quantity-by-product views. Both views'
        // data are already on hand (server sent both), so switching is an
        // instant client-side `.update()`, matching the Sales Trend
        // chart's daily/weekly/monthly/yearly toggle elsewhere on this page.
        const inventoryViewData = { category: inventoryStatusData.byCategory, product: inventoryStatusData.byProduct };
        let currentInventoryView = 'category';
        const inventoryCanvas = document.getElementById('inventoryStatusChart');
        const inventorySkeleton = document.getElementById('inventoryChartSkeleton');
        let inventoryStatusChartInstance = null;

        if (inventoryCanvas) {
            const initialView = inventoryViewData.category;
            inventoryStatusChartInstance = new Chart(inventoryCanvas, {
                type: 'bar',
                data: {
                    labels: initialView.labels,
                    datasets: [{
                        data: initialView.data,
                        backgroundColor: PALETTE,
                        borderRadius: 4,
                        borderSkipped: false,
                        maxBarThickness: 46,
                    }],
                },
                plugins: [bar3dDepthPlugin],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 10, right: 10 } },
                    animation: {
                        duration: 800,
                        easing: 'easeOutQuart',
                        delay: (ctx) => (ctx.type === 'data' ? ctx.dataIndex * 80 : 0),
                        onComplete: () => { if (inventorySkeleton) inventorySkeleton.classList.add('is-hidden'); },
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            borderColor: 'rgba(148, 163, 184, 0.2)',
                            borderWidth: 1,
                            padding: 10,
                            titleColor: '#f8fafc',
                            bodyColor: '#cbd5e1',
                            displayColors: false,
                            callbacks: {
                                label: (ctx) => `${ctx.raw.toLocaleString('en-US')} unit(s)`,
                            },
                        },
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.08)' } },
                        x: { grid: { display: false }, ticks: { maxRotation: 40, minRotation: 0, autoSkip: true } },
                    },
                },
            });

            document.querySelectorAll('[data-toggle="inventory-view"] .chart-toggle-btn').forEach((btn) => {
                btn.addEventListener('click', () => {
                    if (!inventoryStatusChartInstance) return;
                    document.querySelectorAll('[data-toggle="inventory-view"] .chart-toggle-btn').forEach((b) => b.classList.remove('active'));
                    btn.classList.add('active');
                    currentInventoryView = btn.dataset.view;
                    const view = inventoryViewData[currentInventoryView];
                    inventoryStatusChartInstance.data.labels = view.labels;
                    inventoryStatusChartInstance.data.datasets[0].data = view.data;
                    inventoryStatusChartInstance.update();
                });
            });
        }

        // Product Performance — ranked list with horizontal bars. Both bar
        // width and the displayed percentage are each product's quantity
        // relative to the all-time #1 top seller (never the max within
        // whichever tab is currently showing) — Top Selling is always
        // sorted descending, so its first row is guaranteed to be that
        // top seller regardless of how many rows are actually displayed.
        // Using a single shared baseline for both tabs means Least
        // Selling's percentages stay genuinely low (reflecting how far
        // below the top performer they are) instead of being renormalized
        // to 100% for whichever item happens to be the best of a small,
        // non-overlapping "least selling" slice.
        const perfListEl = document.getElementById('productPerformanceList');
        const globalMaxQty = Math.max(1, topSellingRaw[0]?.quantity || 0);

        function renderPerformanceList(rows) {
            if (!perfListEl) return;

            if (rows.length === 0) {
                perfListEl.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-ranking-star"></i></div>
                        <p class="empty-title">Not Enough Data Yet</p>
                        <p class="empty-text">Once more distinct products have sales, this tab will show its own ranking.</p>
                    </div>
                `;
                return;
            }

            perfListEl.innerHTML = rows.map((r, i) => {
                const pct = Math.round((r.quantity / globalMaxQty) * 100);
                const safeName = escapeHtml(r.label);
                return `
                    <div class="perf-item">
                        <div class="perf-bar-fill" data-target-width="${pct}%"></div>
                        <div class="perf-bar-content">
                            <div class="perf-bar-left">
                                <span class="perf-rank">#${i + 1}</span>
                                <span class="perf-name" title="${safeName}">${safeName}</span>
                            </div>
                            <div class="perf-bar-right">
                                <span class="perf-units">${r.quantity.toLocaleString('en-US')} Sold</span>
                                <span class="perf-pct">${pct}%</span>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            // Animate bars growing in from 0 on every render (including tab
            // switches) for the "smooth transition between tabs" requirement.
            requestAnimationFrame(() => {
                perfListEl.querySelectorAll('.perf-bar-fill').forEach((el) => {
                    el.style.width = el.dataset.targetWidth;
                });
            });
        }

        if (perfListEl) {
            renderPerformanceList(topSellingRaw);

            document.querySelectorAll('[data-toggle="performance"] .chart-toggle-btn').forEach((btn) => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('[data-toggle="performance"] .chart-toggle-btn').forEach((b) => b.classList.remove('active'));
                    btn.classList.add('active');
                    renderPerformanceList(btn.dataset.tab === 'least' ? leastSellingRaw : topSellingRaw);
                });
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
                    if (valueEl) valueEl.textContent = window.formatPeso(data.inventoryValue);

                    if (inventoryStatusChartInstance && data.inventoryStatusChart) {
                        inventoryViewData.category = data.inventoryStatusChart.byCategory;
                        inventoryViewData.product = data.inventoryStatusChart.byProduct;
                        const view = inventoryViewData[currentInventoryView];
                        inventoryStatusChartInstance.data.labels = view.labels;
                        inventoryStatusChartInstance.data.datasets[0].data = view.data;
                        inventoryStatusChartInstance.update();
                    }

                    if (categoryChartInstance && data.categoryChart) {
                        categoryChartData.labels = data.categoryChart.labels;
                        categoryChartData.data = data.categoryChart.data;
                        categoryChartInstance.data.labels = categoryChartData.labels;
                        categoryChartInstance.data.datasets[0].data = categoryChartData.data;
                        categoryChartInstance.update();
                        renderCategoryLegend();
                        setRingCenter(null);
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
