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
            <label for="batchReceiptNumberInput" class="detail-label">Receipt Number <span class="batch-receipt-hint">(one for this whole delivery)</span></label>
            <input type="text" id="batchReceiptNumberInput" class="form-input receipt-number-input" name="receipt_number" data-batch-id="{{ $batch->StockReceivingBatchID }}" maxlength="50" value="{{ $receiptNumber }}" placeholder="e.g., SUP-2026-0001">
            <span class="form-error receipt-number-error"></span>
        </div>
    @endif
    <div class="section-title">Ordered Items</div>
    <div style="overflow-x: auto;">
        <table class="items-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Model</th>
                    <th>SKU</th>
                    <th>Purchase Order Quantity</th>
                    <th>Quantity Received</th>
                </tr>
            </thead>
            <tbody>
                @foreach($po->items as $item)
                    <tr>
                        <td>{{ $item->product?->ProductName ?? 'Unknown' }}</td>
                        <td>{{ $item->product?->Model ?: '—' }}</td>
                        <td style="white-space: nowrap;">{{ $item->product?->SKU ?: '—' }}</td>
                        <td>{{ $item->Quantity }}</td>
                        <td>
                            <input type="hidden" name="items[{{ $loop->index }}][purchase_order_item_id]" value="{{ $item->PurchaseOrderItemID }}">
                            @if($isPending)
                                <input type="number" class="form-input" style="max-width:110px;" name="items[{{ $loop->index }}][quantity_received]" min="0" max="{{ $item->Quantity }}" value="{{ $item->ReceivedQuantity ?: $item->Quantity }}" required>
                            @else
                                {{ $item->ReceivedQuantity }}
                                <input type="hidden" name="items[{{ $loop->index }}][quantity_received]" value="{{ $item->ReceivedQuantity }}">
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</form>
