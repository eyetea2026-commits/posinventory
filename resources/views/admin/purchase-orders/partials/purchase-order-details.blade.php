{{-- Shared "View Details" content. Included by both the standalone show page
     (with $showStatus=true — the page keeps its own Status badge and its
     separate action toolbar untouched) and the View Details modal fragment
     (status/actions omitted — the modal is a read-only quick-look surface;
     its own "Print" button lives in the modal shell, not in this partial, so
     it isn't duplicated on the full page which already has one).
     Expects $purchaseOrder with supplier/items.product loaded. --}}
@php
    $showStatus = $showStatus ?? false;
@endphp
<div class="section-title" style="margin-top:0;">Order Information</div>
<div class="detail-row">
    <span class="detail-label">Order Date</span>
    <span class="detail-value">{{ \Illuminate\Support\Carbon::parse($purchaseOrder->PurchaseDate)->format('M d, Y') }}</span>
</div>
<div class="detail-row">
    <span class="detail-label">Supplier</span>
    <span class="detail-value">{{ $purchaseOrder->supplier?->SupplierName ?? 'Unknown' }}</span>
</div>
<div class="detail-row">
    <span class="detail-label">Expected Delivery</span>
    <span class="detail-value">{{ $purchaseOrder->ExpectedDeliveryDate ? \Illuminate\Support\Carbon::parse($purchaseOrder->ExpectedDeliveryDate)->format('M d, Y') : 'Not set' }}</span>
</div>
@if($showStatus)
    <div class="detail-row">
        <span class="detail-label">Status</span>
        <span class="detail-value">
            @php
                $statusClass = match($purchaseOrder->Status) {
                    \App\Models\PurchaseOrder::STATUS_FULLY_RECEIVED => 'badge-completed',
                    \App\Models\PurchaseOrder::STATUS_PARTIALLY_RECEIVED => 'badge-pending',
                    \App\Models\PurchaseOrder::STATUS_APPROVED => 'badge-approved',
                    \App\Models\PurchaseOrder::STATUS_CANCELLED => 'badge-cancelled',
                    \App\Models\PurchaseOrder::STATUS_DRAFT => 'badge-draft',
                    default => 'badge-other',
                };
            @endphp
            <span class="badge {{ $statusClass }}">{{ \App\Models\PurchaseOrder::STATUS_LABELS[$purchaseOrder->Status] ?? ucfirst($purchaseOrder->Status) }}</span>
        </span>
    </div>
@endif
@if($purchaseOrder->Notes)
    <div class="detail-row">
        <span class="detail-label">Notes</span>
        <span class="detail-value">{{ $purchaseOrder->Notes }}</span>
    </div>
@endif

<div class="section-title">Order Items</div>
<div style="overflow-x: auto;">
    <table class="items-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Ordered</th>
                <th>Received</th>
                <th>Remaining</th>
                <th>Cost Price</th>
                <th>Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchaseOrder->items as $item)
                <tr>
                    <td>{{ $item->product?->ProductName ?? 'Unknown' }}</td>
                    <td>{{ $item->Quantity }}</td>
                    <td>{{ $item->ReceivedQuantity }}</td>
                    <td>{{ $item->remaining_quantity }}</td>
                    <td>₱{{ number_format($item->CostPriceAtOrder, 2) }}</td>
                    <td>₱{{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="items-total">Total (received): ₱{{ number_format($purchaseOrder->items->sum('line_total'), 2) }}</div>
