@extends('admin.layout')

@section('title', 'Promo Details - CCTV Express')

@section('header')
    <div class="header-title">
        <h1>Promo Code Details</h1>
        <p>{{ $discount->PromoCode ?? 'N/A' }}</p>
    </div>
@endsection

@section('header-actions')
    <a href="{{ route('admin.discounts.index') }}" class="btn btn-secondary">
        <i class="fa-solid fa-arrow-left"></i> Back to Promo Codes
    </a>
@endsection

@section('content')
<style>
    .btn {
        display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 10px;
        font-weight: 600; text-decoration: none; transition: all 0.2s ease; border: none; cursor: pointer; font-size: 0.95rem;
    }
    .btn-secondary { background: rgba(148, 163, 184, 0.15); color: var(--text-secondary); border: 1px solid rgba(148, 163, 184, 0.2); }
    .btn-secondary:hover { background: rgba(148, 163, 184, 0.25); }
    .detail-card {
        background: rgba(15, 23, 42, 0.75);
        border: 1px solid rgba(148, 163, 184, 0.12);
        border-radius: 20px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(12px);
        padding: 28px;
        max-width: 900px;
        margin: 0 auto 24px;
    }
    .promo-header { display: flex; align-items: center; gap: 20px; margin-bottom: 24px; }
    .product-image-placeholder {
        width: 84px; height: 84px; border-radius: 16px; flex-shrink: 0;
        background: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.2);
        display: flex; align-items: center; justify-content: center;
        font-size: 2rem; color: #60a5fa;
    }
    .promo-header h2 { margin: 0 0 4px; font-size: 1.3rem; color: #f8fafc; }
    .promo-header p { margin: 0; color: #94a3b8; }
    .section-title {
        color: #cbd5e1; font-size: 0.95rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
        margin: 24px 0 12px; padding-bottom: 8px; border-bottom: 1px solid rgba(148, 163, 184, 0.15);
    }
    .section-title:first-of-type { margin-top: 0; }
    .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
    .detail-item {
        padding: 14px; background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(148, 163, 184, 0.12); border-radius: 12px;
    }
    .detail-item label { display: block; font-size: 0.72rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
    .detail-item span { font-weight: 600; color: #f8fafc; font-size: 0.98rem; }
    .badge { display: inline-flex; align-items: center; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
    .badge-success { background: rgba(16, 185, 129, 0.15); color: #6ee7b7; }
    .badge-warning { background: rgba(245, 158, 11, 0.15); color: #fcd34d; }
    .badge-secondary { background: rgba(148, 163, 184, 0.15); color: #cbd5e1; }
    .discount-highlight { color: #6ee7b7; }
    .promo-description { color: #cbd5e1; line-height: 1.6; }
</style>

@php
    $statusClass = match($discount->effective_status) {
        \App\Models\Discount::STATUS_ACTIVE => 'badge-success',
        \App\Models\Discount::STATUS_EXPIRED => 'badge-secondary',
        default => 'badge-warning',
    };
@endphp

<div class="detail-card">
    <div class="promo-header">
        <div class="product-image-placeholder"><i class="fa-solid fa-percent"></i></div>
        <div>
            <h2>{{ $discount->Name ?? 'N/A' }}</h2>
            <p>{{ $discount->products->count() }} product{{ $discount->products->count() === 1 ? '' : 's' }} assigned</p>
        </div>
        <div style="margin-left:auto;">
            <span class="badge {{ $statusClass }}">{{ $discount->effective_status_label }}</span>
        </div>
    </div>

    <h3 class="section-title">Assigned Products</h3>
    @if($discount->products->isEmpty())
        <p class="promo-description">No products have been assigned to this promo yet — use the "Apply Discount/Promo" tab to assign it.</p>
    @else
        <div class="detail-grid">
            @foreach($discount->products as $product)
                <div class="detail-item">
                    <label>{{ $product->ProductName }} ({{ $product->category?->CategoryName ?? 'Uncategorized' }})</label>
                    <span>₱{{ number_format($product->Price, 2) }} &rarr; <span class="discount-highlight">₱{{ number_format($discount->discountedPriceFor($product), 2) }}</span></span>
                </div>
            @endforeach
        </div>
    @endif

    <h3 class="section-title">Discount Value</h3>
    <div class="detail-grid">
        <div class="detail-item">
            <label>Type</label>
            <span>{{ $discount->DiscountType === 'fixed' ? 'Fixed Amount' : 'Percentage' }}</span>
        </div>
        <div class="detail-item">
            <label>Value</label>
            <span>{{ $discount->DiscountType === 'fixed' ? '₱' . number_format($discount->DiscountRate, 2) : number_format($discount->DiscountRate, 2) . '%' }}</span>
        </div>
    </div>

    <h3 class="section-title">Promo Information</h3>
    <div class="detail-grid">
        <div class="detail-item">
            <label>Promo Code</label>
            <span><code>{{ $discount->PromoCode ?? 'N/A' }}</code></span>
        </div>
        <div class="detail-item">
            <label>Promo Name</label>
            <span>{{ $discount->Name ?? 'N/A' }}</span>
        </div>
        <div class="detail-item">
            <label>Start Date</label>
            <span>{{ $discount->StartDate?->format('F j, Y') ?? 'N/A' }}</span>
        </div>
        <div class="detail-item">
            <label>End Date</label>
            <span>{{ $discount->EndDate?->format('F j, Y') ?? 'N/A' }}</span>
        </div>
        <div class="detail-item">
            <label>Remaining Days</label>
            <span>{{ $discount->remaining_days !== null ? $discount->remaining_days . ' day(s)' : 'N/A' }}</span>
        </div>
    </div>

    @if($discount->Description)
        <h3 class="section-title">Description</h3>
        <p class="promo-description">{{ $discount->Description }}</p>
    @endif

    <h3 class="section-title">Audit Information</h3>
    <div class="detail-grid">
        <div class="detail-item">
            <label>Created By</label>
            <span>{{ $discount->createdByUser?->full_name ?? 'Unknown User' }}</span>
        </div>
        <div class="detail-item">
            <label>Created Date</label>
            <span>{{ $discount->created_at?->format('F j, Y g:i A') ?? 'N/A' }}</span>
        </div>
        <div class="detail-item">
            <label>Last Updated</label>
            <span>{{ $discount->updated_at?->format('F j, Y g:i A') ?? 'N/A' }}</span>
        </div>
    </div>
</div>
@endsection
