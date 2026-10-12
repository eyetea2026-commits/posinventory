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
    .btn { padding: 8px 16px; border: none; border-radius: 8px; cursor: pointer; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 6px; }
    .btn-primary { background: #3b82f6; color: white; }
    .btn-primary:hover { background: #2563eb; }
    .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
    .cashier-you { display: inline-block; margin-left: 6px; padding: 1px 7px; border-radius: 999px; font-size: 0.7rem; font-weight: 700; background: rgba(16, 185, 129, 0.15); color: #6ee7b7; }
    .view-only-note { color: #94a3b8; font-size: 0.8rem; font-style: italic; }
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
            <input type="text" name="search" id="transactionsSearchInput" placeholder="Search by receipt # or customer..." value="{{ $search ?? '' }}" autocomplete="off">
        </form>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Receipt #</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Cashier</th>
                <th>Items</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="transactionsTbody" data-live-region="transactions-rows" data-live-topics="sales">
            @include('cashier.partials.transactions-rows')
        </tbody>
    </table>

    <div id="transactionsPagination" data-live-region="transactions-pagination" data-live-topics="sales">
        @include('cashier.partials.transactions-pagination')
    </div>
</div>

@include('partials.print-bridge')

<script>
    // Local-only direct printing (see App\Services\ThermalReceiptPrinter).
    const RECEIPT_THERMAL_PRINTING = @json(\App\Services\ThermalReceiptPrinter::isEnabled());

    // Print button: always asks first, then prints to the thermal printer
    // (when enabled locally) or opens the on-screen receipt. window.open runs
    // inside the confirm button's own click, so it isn't popup-blocked.
    window.confirmPrintReceipt = function (event, receiptNumber, receiptUrl) {
        event.preventDefault();
        const button = event.currentTarget;
        if (button.dataset.printing === '1') return false;

        window.confirmAction({
            title: 'Print Receipt',
            text: 'Print receipt ' + receiptNumber + '?',
            icon: 'question',
            confirmText: 'Print',
            cancelText: 'Cancel',
        }).then(function (result) {
            if (!result.isConfirmed) return;
            if (RECEIPT_THERMAL_PRINTING) {
                reprintToThermal(button, receiptNumber, receiptUrl);
            } else {
                // Deployed site: print through the cashier PC's local app
                // (exactly like a local reprint); without it, open the
                // on-screen receipt.
                window.printViaBridge(receiptNumber, false)
                    .then(function () { toastSuccess('Receipt sent to the printer.'); })
                    .catch(function (message) {
                        if (message) toastWarning(message);
                        window.open(receiptUrl, '_blank');
                    });
            }
        });

        return false;
    };

    // Reprint straight to the local thermal printer; if it can't be
    // reached, open the normal on-screen receipt instead so the cashier is
    // never stuck.
    function reprintToThermal(button, receiptNumber, receiptUrl) {
        if (button.dataset.printing === '1') return;
        button.dataset.printing = '1';
        const idleLabel = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Printing...';

        fetch('{{ url('cashier/receipt') }}/' + encodeURIComponent(receiptNumber) + '/thermal', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ new_sale: false }),
        })
            .then(function (r) { return r.json().catch(function () { return {}; }); })
            .then(function (result) {
                if (result.success) {
                    toastSuccess('Receipt sent to the printer.');
                } else {
                    toastWarning((result.message || 'Receipt printer not reachable.') + ' Opening the on-screen receipt instead.');
                    window.open(receiptUrl, '_blank');
                }
            })
            .catch(function () {
                toastWarning('Receipt printer not reachable. Opening the on-screen receipt instead.');
                window.open(receiptUrl, '_blank');
            })
            .finally(function () {
                button.dataset.printing = '';
                button.innerHTML = idleLabel;
            });
    }

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
