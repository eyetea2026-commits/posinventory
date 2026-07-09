@extends('admin.layout')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Administrator/StockAdjustment.css') }}">
@endpush

@section('header')
    <div class="header-title">
        <h1>Stock Adjustments</h1>
        <p>Adjust product quantities - REQ041 to REQ043</p>
    </div>
@endsection

@section('content')
    <div class="card">
        <div class="toolbar">
            <div class="search-box">
                <i class="search-icon fas fa-search"></i>
                <form method="GET" action="{{ route('admin.stock-adjustments.index') }}" class="w-full">
                    <input type="text" name="search" value="{{ $search }}" class="search-input" placeholder="Search adjustments..." />
                </form>
            </div>
            <!-- REQ041: Adjust product for adjustment -->
            <a href="{{ route('admin.stock-adjustments.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Adjustment
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
                        <th>Date</th>
                        <th>Product</th>
                        <!-- REQ042: Select existing product for adjustment -->
                        <!-- REQ043: Increase or decrease quantity -->
                        <th>Adjustment</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($adjustments as $adjustment)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($adjustment->Date)->format('M d, Y') }}</td>
                            <td><strong>{{ $adjustment->product?->ProductName ?? 'Unknown' }}</strong></td>
                            <td>
                                @if($adjustment->QuantityAdjust > 0)
                                    <span class="badge badge-success">+{{ $adjustment->QuantityAdjust }}</span>
                                @else
                                    <span class="badge badge-danger">{{ $adjustment->QuantityAdjust }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-info">{{ $adjustment->Reason }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="empty-state">
                                    <div class="empty-icon"><i class="fas fa-sliders-h"></i></div>
                                    <p class="empty-title">No Stock Adjustments</p>
                                    <p class="empty-text">Create your first stock adjustment to get started.</p>
                                    <a href="{{ route('admin.stock-adjustments.create') }}" class="btn btn-primary">New Adjustment</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($adjustments->hasPages())
            <div class="pagination">
                @if($adjustments->onFirstPage())
                    <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                @else
                    <a href="{{ $adjustments->previousPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                @endif

                @foreach($adjustments->getUrlRange(1, $adjustments->lastPage()) as $page => $url)
                    <a href="{{ $url }}" class="pagination-link {{ $page == $adjustments->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                @endforeach

                @if($adjustments->hasMorePages())
                    <a href="{{ $adjustments->nextPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                @else
                    <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                @endif
            </div>
        @endif
    </div>
@endsection