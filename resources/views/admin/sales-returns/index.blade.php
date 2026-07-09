@extends('admin.layout')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Administrator/SalesReturns.css') }}">
@endpush

@section('header')
    <div class="header-title">
        <h1>Return Approval</h1>
        <p>View and process warranty claims - REQ062 to REQ065</p>
    </div>
@endsection

@section('content')
    <div class="card">
        <div class="toolbar">
            <div class="search-box">
                <i class="search-icon fas fa-search"></i>
                <form method="GET" action="{{ route('admin.sales-returns.index') }}" class="w-full">
                    <input type="text" name="search" value="{{ $search }}" class="search-input" placeholder="Search returns..." />
                </form>
            </div>
            <a href="{{ route('admin.sales-returns.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Return
            </a>
        </div>

        @if(session('status'))
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                {{ session('status') }}
            </div>
        @endif

        <!-- REQ063: View Return request details -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Return Date</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($returns as $return)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($return->ReturnDate)->format('M d, Y') }}</td>
                            <td><strong>{{ $return->product?->ProductName ?? 'Unknown' }}</strong></td>
                            <td>{{ number_format($return->Quantity) }}</td>
                            <td>{{ $return->Reason }}</td>
                            <td>
                                @if($return->Status === 'approved')
                                    <span class="badge badge-success">Approved</span>
                                @elseif($return->Status === 'rejected')
                                    <span class="badge badge-danger">Rejected</span>
                                @else
                                    <span class="badge badge-warning">Pending</span>
                                @endif
                            </td>
                            <td>
                                <div class="actions-group">
                                    @if($return->Status === 'pending')
                                        <!-- REQ064: Approve warranty claim request -->
                                        <form method="POST" action="{{ route('admin.sales-returns.approve', $return) }}">
                                            @csrf
                                            <button type="submit" class="action-btn" style="background: var(--success-light); color: var(--success);" title="Approve">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                        <!-- REQ065: Decline warranty claim request -->
                                        <form method="POST" action="{{ route('admin.sales-returns.reject', $return) }}">
                                            @csrf
                                            <button type="submit" class="action-btn delete" title="Reject">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div class="empty-icon"><i class="fas fa-undo-alt"></i></div>
                                    <p class="empty-title">No Return Requests</p>
                                    <p class="empty-text">Return requests will appear here.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($returns->hasPages())
            <div class="pagination">
                @if($returns->onFirstPage())
                    <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                @else
                    <a href="{{ $returns->previousPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                @endif

                @foreach($returns->getUrlRange(1, $returns->lastPage()) as $page => $url)
                    <a href="{{ $url }}" class="pagination-link {{ $page == $returns->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                @endforeach

                @if($returns->hasMorePages())
                    <a href="{{ $returns->nextPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                @else
                    <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                @endif
            </div>
        @endif
    </div>
@endsection