@extends('admin.layout')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Administrator/PurchaseOrder.css') }}">
@endpush

@section('header')
    <div class="header-title">
        <h1>Purchase Orders</h1>
        <p>Manage purchase orders - REQ052 to REQ060</p>
    </div>
@endsection

@section('content')
    <div class="card">
        <div class="toolbar">
            <div class="search-box">
                <i class="search-icon fas fa-search"></i>
                <form method="GET" action="{{ route('admin.purchase-orders.index') }}" class="w-full">
                    <input type="text" name="search" value="{{ $search }}" class="search-input" placeholder="Search by purchase number or keyword..." />
                </form>
            </div>
            <!-- REQ053: Create purchase order -->
            <a href="{{ route('admin.purchase-orders.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Order
            </a>
        </div>

        @if(session('status'))
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                {{ session('status') }}
            </div>
        @endif

        <!-- REQ058: Search purchase order & REQ059: View purchase order details -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order Number</th>
                        <th>Supplier</th>
                        <!-- REQ054: Select existing supplier profile -->
                        <th>Order Date</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchaseOrders as $order)
                        <tr>
                            <td>
                                <span class="badge badge-primary">#{{ $order->id }}</span>
                            </td>
                            <td><strong>{{ $order->supplier?->SupplierName ?? 'Unknown' }}</strong></td>
                            <td>{{ \Illuminate\Support\Carbon::parse($order->PurchaseDate)->format('M d, Y') }}</td>
                            <td>{{ $order->items->count() }} items</td>
                            <td>
                                @if($order->Status === 'completed')
                                    <span class="badge badge-success">Completed</span>
                                @elseif($order->Status === 'pending')
                                    <span class="badge badge-warning">Pending</span>
                                @else
                                    <span class="badge badge-secondary">{{ ucfirst($order->Status) }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="actions-group">
                                    <!-- REQ059: View purchase order details -->
                                    <a href="{{ route('admin.purchase-orders.show', $order) }}" class="action-btn view" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div class="empty-icon"><i class="fas fa-shopping-cart"></i></div>
                                    <p class="empty-title">No Purchase Orders</p>
                                    <p class="empty-text">Create your first purchase order to get started.</p>
                                    <a href="{{ route('admin.purchase-orders.create') }}" class="btn btn-primary">New Order</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($purchaseOrders->hasPages())
            <div class="pagination">
                @if($purchaseOrders->onFirstPage())
                    <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                @else
                    <a href="{{ $purchaseOrders->previousPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                @endif

                @foreach($purchaseOrders->getUrlRange(1, $purchaseOrders->lastPage()) as $page => $url)
                    <a href="{{ $url }}" class="pagination-link {{ $page == $purchaseOrders->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                @endforeach

                @if($purchaseOrders->hasMorePages())
                    <a href="{{ $purchaseOrders->nextPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                @else
                    <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                @endif
            </div>
        @endif
    </div>
@endsection