@extends('admin.layout')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Administrator/StockReceiving.css') }}">
@endpush

@section('header')
    <div class="header-title">
        <h1>Stock Receiving</h1>
        <p>Restock products when new supplies are received - REQ037 to REQ040</p>
    </div>
@endsection

@section('content')
    <div class="card">
        <div class="toolbar">
            <div class="search-box">
                <i class="search-icon fas fa-search"></i>
                <form method="GET" action="{{ route('admin.stock-receivings.index') }}" class="w-full">
                    <input type="text" name="search" value="{{ $search }}" class="search-input" placeholder="Search received products or suppliers..." />
                </form>
            </div>
            <!-- REQ037: Restock product -->
            <a href="{{ route('admin.stock-receivings.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Record Receipt
            </a>
        </div>

        @if(session('status'))
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                {{ session('status') }}
            </div>
        @endif

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Receipt Number</th>
                        <th>Product</th>
                        <!-- REQ038 & REQ039: Select existing product and supplier -->
                        <th>Supplier</th>
                        <th>Quantity</th>
                        <th>Date Received</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($receivings as $receipt)
                        <tr>
                            <td>
                                <span class="badge badge-info">{{ $receipt->ReceiptNumber }}</span>
                            </td>
                            <td><strong>{{ $receipt->product?->ProductName ?? 'Unknown' }}</strong></td>
                            <td>{{ $receipt->supplier?->SupplierName ?? 'Unknown' }}</td>
                            <td>{{ number_format($receipt->Quantity) }} units</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($receipt->DateReceived)->format('M d, Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <div class="empty-icon"><i class="fas fa-clipboard-list"></i></div>
                                    <p class="empty-title">No Stock Receivings</p>
                                    <p class="empty-text">Record your first stock receipt to get started.</p>
                                    <a href="{{ route('admin.stock-receivings.create') }}" class="btn btn-primary">Record Receipt</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($receivings->hasPages())
            <div class="pagination">
                @if($receivings->onFirstPage())
                    <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                @else
                    <a href="{{ $receivings->previousPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                @endif

                @foreach($receivings->getUrlRange(1, $receivings->lastPage()) as $page => $url)
                    <a href="{{ $url }}" class="pagination-link {{ $page == $receivings->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                @endforeach

                @if($receivings->hasMorePages())
                    <a href="{{ $receivings->nextPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                @else
                    <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                @endif
            </div>
        @endif
    </div>
@endsection