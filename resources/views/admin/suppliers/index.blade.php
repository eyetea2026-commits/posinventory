@extends('admin.layout')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Administrator/Suppliers.css') }}">
@endpush

@section('header')
    <div class="header-title">
        <h1>Supplier Profile Management</h1>
        <p>Manage supplier profiles - REQ079 to REQ083</p>
    </div>
@endsection

@section('content')
    <!-- REQ081: Search existing supplier profile -->
    <div class="card">
        <div class="toolbar">
            <div class="search-box">
                <i class="search-icon fas fa-search"></i>
                <form method="GET" action="{{ route('admin.suppliers.index') }}" class="w-full">
                    <input type="text" name="search" value="{{ $search }}" class="search-input" placeholder="Search suppliers by name or contact..." />
                </form>
            </div>
            <!-- REQ080: Create supplier profile -->
            <a href="{{ route('admin.suppliers.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Supplier
            </a>
        </div>

        @if(session('status'))
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                {{ session('status') }}
            </div>
        @endif

        <!-- REQ082: View supplier profile details -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Supplier Name</th>
                        <th>Contact Number</th>
                        <th>Email</th>
                        <th>Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($suppliers as $supplier)
                        <tr>
                            <td>
                                <strong>{{ $supplier->SupplierName }}</strong>
                            </td>
                            <td>{{ $supplier->ContactNumber }}</td>
                            <td>{{ $supplier->Email }}</td>
                            <td>{{ $supplier->Address }}</td>
                            <td>
                                <div class="actions-group">
                                    <!-- REQ083: Update supplier profile details -->
                                    <a href="{{ route('admin.suppliers.edit', $supplier) }}" class="action-btn edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <div class="empty-icon"><i class="fas fa-truck"></i></div>
                                    <p class="empty-title">No Suppliers Found</p>
                                    <p class="empty-text">Add your first supplier to get started.</p>
                                    <a href="{{ route('admin.suppliers.create') }}" class="btn btn-primary">Add Supplier</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($suppliers->hasPages())
            <div class="pagination">
                @if($suppliers->onFirstPage())
                    <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                @else
                    <a href="{{ $suppliers->previousPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                @endif

                @foreach($suppliers->getUrlRange(1, $suppliers->lastPage()) as $page => $url)
                    <a href="{{ $url }}" class="pagination-link {{ $page == $suppliers->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                @endforeach

                @if($suppliers->hasMorePages())
                    <a href="{{ $suppliers->nextPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                @else
                    <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                @endif
            </div>
        @endif
    </div>
@endsection