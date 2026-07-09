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

    .section-title {
        color: #cbd5e1;
        font-size: 1rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin: 24px 0 12px;
        padding-bottom: 8px;
        border-bottom: 1px solid rgba(148, 163, 184, 0.15);
    }

    .section-title:first-of-type {
        margin-top: 0;
    }

    .detail-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 0;
        border-bottom: 1px solid rgba(148, 163, 184, 0.1);
        gap: 16px;
    }

    .detail-row:last-child {
        border-bottom: none;
    }

    .detail-label {
        color: #94a3b8;
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        flex: 0 0 180px;
    }

    .detail-value {
        color: #f8fafc;
        font-size: 0.95rem;
        font-weight: 500;
        text-align: right;
        flex: 1;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .badge-in-stock { background: rgba(16, 185, 129, 0.15); color: #6ee7b7; }
    .badge-low-stock { background: rgba(245, 158, 11, 0.15); color: #fcd34d; }
    .badge-replenish { background: rgba(249, 115, 22, 0.15); color: #fb923c; }
    .badge-out-of-stock { background: rgba(239, 68, 68, 0.15); color: #fca5a5; }

    .velocity-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .velocity-tag.fast-moving { background: rgba(16, 185, 129, 0.15); color: #6ee7b7; }
    .velocity-tag.slow-moving { background: rgba(249, 115, 22, 0.15); color: #fb923c; }
    .velocity-tag.normal { background: rgba(59, 130, 246, 0.15); color: #93c5fd; }

    .detail-actions {
        display: flex;
        gap: 12px;
        margin-top: 24px;
        justify-content: flex-end;
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
    <h2 class="section-title">Product Information</h2>
    <div class="detail-row">
        <span class="detail-label">Product Name</span>
        <span class="detail-value"><strong>{{ $product->ProductName }}</strong></span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Model</span>
        <span class="detail-value">{{ $product->Model ?? 'N/A' }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">SKU</span>
        <span class="detail-value"><code>{{ $product->SKU ?? '-' }}</code></span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Barcode</span>
        <span class="detail-value"><code>{{ $product->Barcode ?? '-' }}</code></span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Category</span>
        <span class="detail-value">{{ $product->category?->CategoryName ?? 'Uncategorized' }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Brand</span>
        <span class="detail-value">{{ $product->brand?->BrandName ?? 'N/A' }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Description</span>
        <span class="detail-value">{{ $product->Description ?? 'No description provided.' }}</span>
    </div>

    <h2 class="section-title">Pricing</h2>
    <div class="detail-row">
        <span class="detail-label">Cost Price</span>
        <span class="detail-value">₱{{ number_format($product->CostPrice ?? 0, 2) }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Selling Price</span>
        <span class="detail-value">₱{{ number_format($product->Price, 2) }}</span>
    </div>

    <h2 class="section-title">Inventory</h2>
    <div class="detail-row">
        <span class="detail-label">Current Stock</span>
        <span class="detail-value">{{ $product->inventory?->Quantity ?? 0 }} units</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Reorder Threshold</span>
        <span class="detail-value">{{ $product->inventory?->ReorderThreshold ?? 0 }} units</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Stock Status</span>
        <span class="detail-value">
            <span class="badge {{ $stock['class'] }}">
                <i class="fas {{ $stock['icon'] }}"></i> {{ $stock['label'] }}
            </span>
        </span>
    </div>
    <div class="detail-row">
        <span class="detail-label">30-Day Sales</span>
        <span class="detail-value">{{ $velocity }} units</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Sales Velocity</span>
        <span class="detail-value">
            @if($velocityLabel === 'fast-moving')
                <span class="velocity-tag fast-moving"><i class="fas fa-bolt"></i> Fast-Moving</span>
            @elseif($velocityLabel === 'slow-moving')
                <span class="velocity-tag slow-moving"><i class="fas fa-hourglass-half"></i> Slow-Moving</span>
            @else
                <span class="velocity-tag normal"><i class="fas fa-minus-circle"></i> Normal</span>
            @endif
        </span>
    </div>

    <div class="detail-actions">
        <a href="{{ route('admin.inventory.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Inventory
        </a>
    </div>
</div>
@endsection
