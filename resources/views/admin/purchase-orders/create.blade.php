@extends('admin.layout')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Administrator/PurchaseOrder.css') }}">
@endpush

@section('header')
    <div class="header-title">
        <h1>Create Purchase Order</h1>
        <p>Order stock from a supplier ahead of receiving it</p>
    </div>
@endsection

@section('content')
    <div class="card" style="max-width: 900px; margin: 0 auto;">
        <div class="card-header">
            <div>
                <h2 class="card-title">Order Details</h2>
                <p class="card-subtitle">Fields marked with an asterisk are required</p>
            </div>
            <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        <form method="POST" action="{{ route('admin.purchase-orders.store') }}" id="purchaseOrderForm">
            @csrf

            @include('admin.purchase-orders.partials.purchase-order-form-fields')

            <div class="modal-footer" style="border-top: 1px solid var(--border); margin-top: 24px;">
                <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Order
                </button>
            </div>
        </form>
    </div>
@endsection
