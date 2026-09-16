{{-- Stock Receiving "View Details" — read-only PO header info, then an
     editable Ordered Items table: Purchase Order Quantity stays read-only
     (the original order reference, never overwritten), Quantity Received
     and Receipt Number are the only editable fields per item. "Add to
     Inventory" posts every row here in one request to
     StockReceivingController::addToInventory().
     Expects $batch (StockReceivingBatch) with purchaseOrder.supplier and
     purchaseOrder.items.product loaded. --}}
@php
    $po = $batch->purchaseOrder;
    $isPending = $batch->Status === \App\Models\StockReceivingBatch::STATUS_PENDING;
@endphp
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
    <span class="detail-value">{{ \Illuminate\Support\Carbon::parse($po->PurchaseDate)->format('M d, Y') }}</span>
</div>
<div class="detail-row">
    <span class="detail-label">Expected Delivery</span>
    <span class="detail-value">{{ $po->ExpectedDeliveryDate ? \Illuminate\Support\Carbon::parse($po->ExpectedDeliveryDate)->format('M d, Y') : 'Not set' }}</span>
</div>
@if(!$isPending)
    <div class="detail-row">
        <span class="detail-label">Completed</span>
        <span class="detail-value">{{ $batch->CompletedAt?->format('M d, Y g:i A') ?? 'N/A' }}</span>
    </div>
@endif

<div class="section-title">Ordered Items</div>
<form id="batchReceivingForm" method="POST" action="{{ route('admin.stock-receivings.batches.add-to-inventory', $batch) }}">
    @csrf
    <div style="overflow-x: auto;">
        <table class="items-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Purchase Order Quantity</th>
                    <th>Quantity Received</th>
                    <th>Receipt Number</th>
                </tr>
            </thead>
            <tbody>
                @foreach($po->items as $item)
                    <tr>
                        <td>{{ $item->product?->ProductName ?? 'Unknown' }}</td>
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
                        <td>
                            @if($isPending)
                                <input type="text" class="form-input" style="max-width:160px;" name="items[{{ $loop->index }}][receipt_number]" maxlength="50" value="{{ $item->ReceiptNumber }}" placeholder="Optional">
                            @else
                                {{ $item->ReceiptNumber ?? 'N/A' }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</form>

@if($isPending)
    <div class="modal-actions" style="margin-top:20px;">
        <button type="button" class="btn btn-primary" id="addToInventoryBtn" onclick="submitAddToInventory({{ $batch->StockReceivingBatchID }})">
            <i class="fas fa-boxes-stacked"></i> Add to Inventory
        </button>
    </div>
@endif
