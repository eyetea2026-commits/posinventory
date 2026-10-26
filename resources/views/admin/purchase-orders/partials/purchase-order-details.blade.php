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
<div class="ui-kv-grid">
    <div class="detail-row">
        <span class="detail-label">Order Date</span>
        <span class="detail-value">{{ \Illuminate\Support\Carbon::parse($purchaseOrder->PurchaseDate)->format('m/d/Y') }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Supplier</span>
        <span class="detail-value">{{ $purchaseOrder->supplier?->SupplierName ?? 'Unknown' }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Expected Delivery</span>
        <span class="detail-value">{{ $purchaseOrder->ExpectedDeliveryDate ? \Illuminate\Support\Carbon::parse($purchaseOrder->ExpectedDeliveryDate)->format('m/d/Y') : 'Not set' }}</span>
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
                        \App\Models\PurchaseOrder::STATUS_CANCELLED, \App\Models\PurchaseOrder::STATUS_CLOSED => 'badge-cancelled',
                        \App\Models\PurchaseOrder::STATUS_DRAFT => 'badge-draft',
                        default => 'badge-other',
                    };
                @endphp
                <span class="badge {{ $statusClass }}">{{ \App\Models\PurchaseOrder::STATUS_LABELS[$purchaseOrder->Status] ?? ucfirst($purchaseOrder->Status) }}</span>
            </span>
        </div>
@endif
@if($purchaseOrder->RemainderCancelReason)
        <div class="detail-row ui-kv-wide">
            <span class="detail-label">Remaining Items Cancelled</span>
            <span class="detail-value">{{ $purchaseOrder->RemainderCancelReason }} — {{ $purchaseOrder->remainderCancelledByUser?->full_name ?? 'N/A' }}, {{ $purchaseOrder->RemainderCancelledAt?->format('m/d/Y g:i A') }}</span>
        </div>
@endif
@if($purchaseOrder->Notes)
        <div class="detail-row ui-kv-wide">
            <span class="detail-label">Notes</span>
            <span class="detail-value">{{ $purchaseOrder->Notes }}</span>
        </div>
@endif
</div>

<div class="section-title">Order Items</div>
<div style="overflow-x: auto;">
    <table class="items-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Model</th>
                <th>SKU</th>
                <th>Ordered</th>
                <th>Received</th>
                @if($purchaseOrder->hasAnyCancelledQuantity())
                    <th>Cancelled</th>
                @endif
                <th>Remaining</th>
                <th>Cost Price</th>
                <th>Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchaseOrder->items as $item)
                <tr>
                    <td>{{ $item->product?->ProductName ?? 'Unknown' }}</td>
                    <td>{{ $item->product?->Model ?: '—' }}</td>
                    <td style="white-space: nowrap;">{{ $item->product?->SKU ?: '—' }}</td>
                    {{-- In the unit it was ordered in: "2 rolls (10,000 meters)". --}}
                    <td>{{ $item->tableQuantity() }}</td>
                    <td>{{ $item->tableQuantity($item->ReceivedQuantity) }}</td>
                    @if($purchaseOrder->hasAnyCancelledQuantity())
                        <td>{{ (int) $item->CancelledQuantity ? $item->tableQuantity($item->CancelledQuantity) : '—' }}</td>
                    @endif
                    <td>{{ $item->tableQuantity($item->remaining_quantity) }}</td>
                    <td>{{ $item->unitCostLabel() }}</td>
                    <td>₱{{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="items-total">Total Amount: ₱{{ number_format($purchaseOrder->items->sum('line_total'), 2) }}</div>
