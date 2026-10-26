{{-- Stock Receiving "View Details" — read-only PO header info, then an
     editable Ordered Items table: Purchase Order Quantity stays read-only
     (the original order reference, never overwritten), Quantity Received
     is the only editable field per item, and one Receipt Number covers the
     whole delivery (every product on this Purchase Order). "Add to
     Inventory" posts every row here in one request to
     StockReceivingController::addToInventory().
     Expects $batch (StockReceivingBatch) with purchaseOrder.supplier and
     purchaseOrder.items.product loaded. --}}
@php
    $po = $batch->purchaseOrder;
    $isPending = $batch->Status === \App\Models\StockReceivingBatch::STATUS_PENDING;
    // Deliveries recorded before Receipt Numbers moved to the delivery as a
    // whole kept them on the product lines.
    $receiptNumber = $batch->ReceiptNumber ?? $po->items->pluck('ReceiptNumber')->filter()->first();
@endphp
<div class="ui-kv-grid">
    <div class="detail-row">
        <span class="detail-label">PO Number</span>
        <span class="detail-value">{{ $po->PONumber }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Supplier</span>
        <span class="detail-value">{{ $po->supplier?->SupplierName ?? 'Unknown' }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Order Date</span>
        <span class="detail-value">{{ \Illuminate\Support\Carbon::parse($po->PurchaseDate)->format('m/d/Y') }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Expected Delivery</span>
        <span class="detail-value">{{ $po->ExpectedDeliveryDate ? \Illuminate\Support\Carbon::parse($po->ExpectedDeliveryDate)->format('m/d/Y') : 'Not set' }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Order Status</span>
        <span class="detail-value">{{ \App\Models\PurchaseOrder::STATUS_LABELS[$po->Status] ?? ucfirst($po->Status) }}</span>
    </div>
@if(!$isPending)
        <div class="detail-row">
            <span class="detail-label">Completed</span>
            <span class="detail-value">{{ $batch->CompletedAt?->format('m/d/Y g:i A') ?? 'N/A' }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Receipt Number</span>
            <span class="detail-value">{{ $receiptNumber ?? 'N/A' }}</span>
        </div>
@endif
</div>

<form id="batchReceivingForm" method="POST" action="{{ route('admin.stock-receivings.batches.add-to-inventory', $batch) }}">
    @csrf
    @if($isPending)
        <div class="batch-receipt-number">
            <label for="batchReceiptNumberInput" class="detail-label">Receipt Number <span class="batch-receipt-hint">(one for this delivery)</span></label>
            <input type="text" id="batchReceiptNumberInput" class="form-input receipt-number-input" name="receipt_number" data-batch-id="{{ $batch->StockReceivingBatchID }}" maxlength="50" value="" placeholder="e.g., SUP-2026-0001">
            <span class="form-error receipt-number-error"></span>
        </div>
    @endif
    @php($anyCancelled = $po->items->contains(fn ($item) => (int) $item->CancelledQuantity > 0))
    <div class="section-title">Ordered Items</div>
    <div style="overflow-x: auto;">
        <table class="items-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Model</th>
                    <th>SKU</th>
                    <th>Ordered</th>
                    <th>Received</th>
                    @if($anyCancelled)
                        <th>Cancelled</th>
                    @endif
                    @if($isPending)
                        <th>Still Owed</th>
                        <th>Receiving Now</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($po->items as $item)
                    <tr>
                        <td>{{ $item->product?->ProductName ?? 'Unknown' }}</td>
                        <td>{{ $item->product?->Model ?: '—' }}</td>
                        <td style="white-space: nowrap;">{{ $item->product?->SKU ?: '—' }}</td>
                        <td>{{ $item->Quantity }}</td>
                        <td>{{ $item->ReceivedQuantity }}</td>
                        @if($anyCancelled)
                            <td>{{ (int) $item->CancelledQuantity ?: '—' }}</td>
                        @endif
                        @if($isPending)
                            <td><strong>{{ $item->remaining_quantity }}</strong></td>
                            <td>
                                <input type="hidden" name="items[{{ $loop->index }}][purchase_order_item_id]" value="{{ $item->PurchaseOrderItemID }}">
                                {{-- This delivery only; defaults to everything still owed. --}}
                                <input type="number" class="form-input receiving-now-input" style="max-width:110px;" name="items[{{ $loop->index }}][quantity_received]" min="0" max="{{ $item->remaining_quantity }}" value="{{ $item->remaining_quantity }}" @if($item->remaining_quantity <= 0) readonly @endif required>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</form>

@php($deliveries = $po->deliveries()->with(['items.product', 'receivedByUser'])->orderBy('ReceivedAt')->get())
@if($deliveries->isNotEmpty())
    {{-- Every delivery received against this order so far. --}}
    <div class="section-title">Delivery History</div>
    <div style="overflow-x: auto;">
        <table class="items-table">
            <thead>
                <tr>
                    <th>Date Received</th>
                    <th>Receipt Number</th>
                    <th>Received By</th>
                    <th>Products</th>
                </tr>
            </thead>
            <tbody>
                @foreach($deliveries as $delivery)
                    <tr>
                        <td style="white-space: nowrap;">{{ $delivery->ReceivedAt?->format('m/d/Y g:i A') }}</td>
                        <td>{{ $delivery->ReceiptNumber ?: 'N/A' }}</td>
                        <td>{{ $delivery->receivedByUser?->full_name ?? 'N/A' }}</td>
                        <td>{{ $delivery->items->map(fn ($line) => $line->Quantity.' x '.($line->product?->ProductName ?? 'Unknown'))->implode(', ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if($po->RemainderCancelReason)
    <div class="ui-kv-grid" style="margin-top:12px;">
        <div class="ui-kv-wide">
            <span>Remaining Items Cancelled</span>
            <span>{{ $po->RemainderCancelReason }} — {{ $po->remainderCancelledByUser?->full_name ?? 'N/A' }}, {{ $po->RemainderCancelledAt?->format('m/d/Y g:i A') }}</span>
        </div>
    </div>
@endif

@include('admin.purchase-orders.partials.cancel-remaining-form', ['purchaseOrder' => $po])
