{{-- Fallback full-page view for direct navigation to this URL. The real
     Inventory-module workflow no longer links here — it opens the same
     reorder-form-fields partial inside a glassmorphism modal instead (see
     admin/inventory/partials/reorder-modal.blade.php) so the admin never
     leaves the Inventory page. --}}
@extends('admin.layout')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Administrator/PurchaseOrder.css') }}">
@endpush

@section('header')
    <div class="header-title">
        <h1>Create Purchase Order</h1>
        <p>Reordering {{ $product->ProductName }} from a low-stock alert</p>
    </div>
@endsection

@section('content')
    <div class="card" style="max-width: 900px; margin: 0 auto;">
        <div class="card-header">
            <div>
                <h2 class="card-title">Order Details</h2>
            </div>
            <a href="{{ route('admin.inventory.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        <form method="POST" action="{{ route('admin.purchase-orders.store-from-reorder', $product) }}" id="reorderPurchaseOrderForm">
            @csrf

            @include('admin.purchase-orders.partials.reorder-form-fields', [
                'product' => $product,
                'quantity' => $quantity,
                'threshold' => $threshold,
                'suggestedQuantity' => $suggestedQuantity,
                'resolvedSupplier' => $resolvedSupplier,
                'knownSuppliers' => $knownSuppliers,
                'supplierState' => $supplierState,
                'allSuppliers' => $allSuppliers,
            ])

            <div class="modal-footer" style="border-top: 1px solid var(--border); margin-top: 24px;">
                <a href="{{ route('admin.inventory.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Purchase Order
                </button>
            </div>
        </form>
    </div>

    @include('admin.purchase-orders.partials.reorder-supplier-preview-script')
@endsection
