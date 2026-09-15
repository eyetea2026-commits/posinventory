@extends('cashier.layout')

@section('title', 'Transactions - CCTV Express')

@section('content')
<style>
    .content-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
    .content-header h1 { margin: 0; font-size: 1.5rem; }
    .card { background: #1a1d2d; border-radius: 16px; padding: 20px; }
    .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
    .search-form { display: flex; gap: 10px; flex-wrap: wrap; }
    .search-form input, .search-form select { padding: 12px 16px; background: #2d3748; border: 1px solid #4a5568; color: #e2e8f0; border-radius: 10px; font-size: 0.9rem; }
    .search-form input:focus { outline: none; border-color: #3b82f6; }
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { padding: 14px 12px; text-align: left; border-bottom: 1px solid #2d3748; }
    .table th { color: #94a3b8; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; }
    .table tbody tr:hover { background: #2d3748; }
    .receipt-number { font-family: monospace; font-weight: 600; color: #60a5fa; }
    .amount { font-weight: 600; color: #10b981; }
    .btn { padding: 8px 16px; border: none; border-radius: 8px; cursor: pointer; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 6px; }
    .btn-primary { background: #3b82f6; color: white; }
    .btn-primary:hover { background: #2563eb; }
    .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
    .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; }
    .alert-success { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
    .empty-state { text-align: center; padding: 40px; color: #64748b; }
    .empty-state i { font-size: 3rem; margin-bottom: 16px; }
    .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 20px; }
    .pagination a, .pagination span { padding: 8px 14px; background: #2d3748; color: #e2e8f0; border-radius: 8px; text-decoration: none; }
    .pagination a:hover { background: #3b82f6; }
    .pagination .active { background: #3b82f6; }
    @media (max-width: 768px) {
        .toolbar { flex-direction: column; align-items: stretch; }
        .table { font-size: 0.85rem; }
    }
</style>

<div class="content-header">
    <div>
        <h1><i class="fas fa-history"></i> Recent Transactions</h1>
        <p style="margin:4px 0 0; color:#94a3b8; font-size:0.85rem;">Today's transactions only, for your account.</p>
    </div>
    @include('cashier.partials.notification-bell')
</div>

@if(session('success'))
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
@endif

<div class="card">
    <div class="toolbar">
        <form method="GET" action="{{ route('cashier.transactions') }}" class="search-form" id="transactionsSearchForm">
            <input type="text" name="search" id="transactionsSearchInput" placeholder="Search today's transactions by customer..." value="{{ $search ?? '' }}" autocomplete="off">
        </form>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Receipt #</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Items</th>
                <th>Total</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="transactionsTbody">
            @include('cashier.partials.transactions-rows')
        </tbody>
    </table>

    <div id="transactionsPagination">
        @include('cashier.partials.transactions-pagination')
    </div>
</div>

<script>
    (function () {
        const searchInput = document.getElementById('transactionsSearchInput');
        const tbody = document.getElementById('transactionsTbody');
        const paginationWrapper = document.getElementById('transactionsPagination');
        const form = document.getElementById('transactionsSearchForm');

        let debounceTimer = null;
        let currentController = null;

        function buildQuery() {
            const params = new URLSearchParams();
            if (searchInput.value.trim()) params.set('search', searchInput.value.trim());
            return params.toString();
        }

        async function applyFilters() {
            const query = buildQuery();
            const url = `{{ route('cashier.transactions') }}${query ? '?' + query : ''}`;
            window.history.replaceState({}, '', url);

            if (currentController) currentController.abort();
            currentController = new AbortController();

            try {
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    signal: currentController.signal,
                });
                if (!response.ok) throw new Error('Request failed');
                const data = await response.json();
                tbody.innerHTML = data.rows;
                paginationWrapper.innerHTML = data.pagination;
            } catch (err) {
                if (err.name === 'AbortError') return;
            }
        }

        // Live/instant: every keystroke re-filters, no Search button needed.
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(applyFilters, 300);
        });

        // Search is already live — Enter shouldn't trigger a full page reload.
        form.addEventListener('submit', function (e) { e.preventDefault(); });
    })();
</script>
@endsection
