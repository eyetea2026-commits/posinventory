@extends('admin.layout')

@section('header')
    <div class="header-title">
        <h1>Product Details</h1>
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

    .badge-in-stock {
        background: rgba(16, 185, 129, 0.15);
        color: #6ee7b7;
    }

    .badge-low-stock {
        background: rgba(245, 158, 11, 0.15);
        color: #fcd34d;
    }

    .badge-replenish {
        background: rgba(249, 115, 22, 0.15);
        color: #fb923c;
    }

    .badge-out-of-stock {
        background: rgba(239, 68, 68, 0.15);
        color: #fca5a5;
    }

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

    .btn-primary {
        background: linear-gradient(135deg, #3b82f6, #10b981);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
    }
</style>

<div class="product-detail-card">
    @include('admin.products.partials.product-details')

    <div class="detail-actions">
        <a href="{{ route('admin.products.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-primary">
            <i class="fas fa-edit"></i> Update Details
        </a>
    </div>
</div>
@endsection
