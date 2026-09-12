{{-- Full-page fallback for direct navigation to this URL. The real
     Inventory list's "View Details" now opens the same
     inventory-details.blade.php partial inside a modal instead (see
     admin/inventory/partials/inventory-details-modal.blade.php) so the
     admin never leaves the Inventory page. --}}
@extends('admin.layout')

@section('header')
    <div class="header-title">
        <h1>Inventory Details</h1>
        <p>{{ $product->ProductName }}</p>
    </div>
@endsection

@section('content')
<style>
    .product-detail-card {
        background: rgba(15, 23, 42, 0.75);
        border: 1px solid rgba(148, 163, 184, 0.12);
        border-radius: 20px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(12px);
        padding: 28px;
        max-width: 800px;
        margin: 0 auto;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        border-radius: 12px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
        border: none;
        cursor: pointer;
        font-size: 0.95rem;
    }

    .btn-secondary {
        background: rgba(100, 116, 139, 0.2);
        color: #e2e8f0;
    }

    .btn-secondary:hover {
        background: rgba(100, 116, 139, 0.35);
    }
</style>

<div class="product-detail-card">
    @include('admin.inventory.partials.inventory-details', [
        'product' => $product,
        'velocity' => $velocity,
        'velocityLabel' => $velocityLabel,
        'stock' => $stock,
    ])

    <div style="display:flex; gap:12px; margin-top:24px; justify-content:flex-end;">
        <a href="{{ route('admin.inventory.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Inventory
        </a>
    </div>
</div>

@include('admin.inventory.partials.reorder-modal')
@endsection
