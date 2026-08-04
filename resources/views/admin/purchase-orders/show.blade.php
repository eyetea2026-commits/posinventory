@extends('admin.layout')

@section('header')
    <div class="header-title">
        <h1>Purchase Order Details</h1>
        <p>{{ $purchaseOrder->PONumber }}</p>
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
        max-width: 900px;
        margin: 0 auto 24px;
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

    .badge-completed { background: rgba(16, 185, 129, 0.15); color: #6ee7b7; }
    .badge-pending { background: rgba(245, 158, 11, 0.15); color: #fcd34d; }
    .badge-other { background: rgba(100, 116, 139, 0.2); color: #cbd5e1; }
    .badge-draft { background: rgba(100, 116, 139, 0.2); color: #cbd5e1; }
    .badge-approved { background: rgba(59, 130, 246, 0.15); color: #93c5fd; }
    .badge-cancelled { background: rgba(239, 68, 68, 0.15); color: #fca5a5; }

    .items-table {
        width: 100%;
        border-collapse: collapse;
    }

    .items-table th {
        text-align: left;
        padding: 10px 12px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #94a3b8;
        border-bottom: 1px solid rgba(148, 163, 184, 0.15);
    }

    .items-table td {
        padding: 12px;
        color: #f8fafc;
        font-size: 0.9rem;
        border-bottom: 1px solid rgba(148, 163, 184, 0.08);
    }

    .items-table tbody tr:last-child td {
        border-bottom: none;
    }

    .items-total {
        text-align: right;
        margin-top: 16px;
        color: #f8fafc;
        font-size: 1.05rem;
        font-weight: 700;
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
</style>

<div class="product-detail-card">
    @include('admin.purchase-orders.partials.purchase-order-details', ['purchaseOrder' => $purchaseOrder, 'showStatus' => true])

    <div class="detail-actions">
        <a href="{{ route('admin.purchase-orders.print', $purchaseOrder) }}" target="_blank" class="btn btn-secondary">
            <i class="fas fa-print"></i> Print
        </a>
        <a href="{{ route('admin.purchase-orders.export', $purchaseOrder) }}" class="btn btn-secondary">
            <i class="fas fa-file-pdf"></i> Export PDF
        </a>
        @if($purchaseOrder->Status === \App\Models\PurchaseOrder::STATUS_DRAFT)
            <form method="POST" action="{{ route('admin.purchase-orders.submit', $purchaseOrder) }}" class="js-confirm-submit" data-confirm-title="Submit Purchase Order" data-confirm-text="Submit this purchase order for approval?" data-confirm-icon="question" data-confirm-color="#10b981">
                @csrf
                <button type="submit" class="btn btn-secondary"><i class="fas fa-paper-plane"></i> Submit</button>
            </form>
        @endif
        @if(in_array($purchaseOrder->Status, [\App\Models\PurchaseOrder::STATUS_DRAFT, \App\Models\PurchaseOrder::STATUS_PENDING]))
            <form method="POST" action="{{ route('admin.purchase-orders.approve', $purchaseOrder) }}" class="js-confirm-submit" data-confirm-title="Approve Purchase Order" data-confirm-text="Approve this purchase order? It will become eligible to receive stock against." data-confirm-icon="question" data-confirm-color="#10b981">
                @csrf
                <button type="submit" class="btn btn-secondary"><i class="fas fa-check"></i> Approve</button>
            </form>
        @endif
        @if(in_array($purchaseOrder->Status, [\App\Models\PurchaseOrder::STATUS_DRAFT, \App\Models\PurchaseOrder::STATUS_PENDING, \App\Models\PurchaseOrder::STATUS_APPROVED]) && ! $purchaseOrder->hasAnyReceivedQuantity())
            <form method="POST" action="{{ route('admin.purchase-orders.cancel', $purchaseOrder) }}" class="js-confirm-submit" data-confirm-title="Cancel Purchase Order" data-confirm-text="Cancel this purchase order?" data-confirm-icon="warning" data-confirm-color="#ef4444">
                @csrf
                <button type="submit" class="btn btn-secondary"><i class="fas fa-ban"></i> Cancel</button>
            </form>
        @endif
        @if($purchaseOrder->Status === \App\Models\PurchaseOrder::STATUS_APPROVED || $purchaseOrder->Status === \App\Models\PurchaseOrder::STATUS_PARTIALLY_RECEIVED)
            <a href="{{ route('admin.stock-receivings.create', ['purchase_order_id' => $purchaseOrder->PurchaseOrderID]) }}" class="btn btn-primary">
                <i class="fas fa-truck-loading"></i> Receive Stock
            </a>
        @endif
        <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Purchase Orders
        </a>
    </div>
</div>
@endsection
