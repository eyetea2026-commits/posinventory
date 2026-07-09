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
    .search-form button { padding: 12px 20px; background: #3b82f6; border: none; color: white; border-radius: 10px; cursor: pointer; font-weight: 600; }
    .search-form button:hover { background: #2563eb; }
    .btn-reset { padding: 12px 20px; background: #4b5563; border: none; color: white; border-radius: 10px; cursor: pointer; }
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
    <h1><i class="fas fa-history"></i> Sales Transactions</h1>
</div>

@if(session('success'))
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
@endif

<div class="card">
    <div class="toolbar">
        <form method="GET" action="{{ route('cashier.transactions') }}" class="search-form">
            <input type="text" name="search" placeholder="Search by customer..." value="{{ $search ?? '' }}">
            <input type="date" name="date_from" value="{{ $dateFrom ?? '' }}">
            <input type="date" name="date_to" value="{{ $dateTo ?? '' }}">
            <button type="submit"><i class="fas fa-search"></i> Filter</button>
            <a href="{{ route('cashier.transactions') }}" class="btn-reset"><i class="fas fa-redo"></i> Reset</a>
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
        <tbody>
            @forelse($transactions as $transaction)
                <tr>
                    <td><span class="receipt-number">RCT-{{ str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT) }}</span></td>
                    <td>{{ \Carbon\Carbon::parse($transaction->SalesTransactionDate)->format('M d, Y h:i A') }}</td>
                    <td>{{ $transaction->CustomerName ?? 'Walk-in Customer' }}</td>
                    <td>{{ $transaction->items->sum('Quantity') ?? 0 }} items</td>
                    <td class="amount">₱{{ number_format($transaction->billing?->BillingAmount ?? 0, 2) }}</td>
                    <td>
                        <a href="{{ route('cashier.transactions') }}?print={{ $transaction->SalesTransactionID }}" class="btn btn-primary btn-sm" target="_blank">
                            <i class="fas fa-print"></i> Print
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <p>No transactions found</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($transactions->hasPages())
        <div class="pagination">
            @if($transactions->onFirstPage())
                <span><i class="fas fa-chevron-left"></i></span>
            @else
                <a href="{{ $transactions->previousPageUrl() }}"><i class="fas fa-chevron-left"></i></a>
            @endif

            @foreach($transactions->getUrlRange(1, min(5, $transactions->lastPage())) as $page => $url)
                <a href="{{ $url }}" class="{{ $page == $transactions->currentPage() ? 'active' : '' }}">{{ $page }}</a>
            @endforeach

            @if($transactions->hasMorePages())
                <a href="{{ $transactions->nextPageUrl() }}"><i class="fas fa-chevron-right"></i></a>
            @else
                <span><i class="fas fa-chevron-right"></i></span>
            @endif
        </div>
    @endif
</div>
@endsection