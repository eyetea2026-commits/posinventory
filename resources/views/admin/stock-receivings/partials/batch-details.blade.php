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
            <label for="batchReceiptNumberInput" class="detail-label">Receipt Number <span class="required">*</span> <span class="batch-receipt-hint">(one for this delivery)</span></label>
            <input type="text" id="batchReceiptNumberInput" class="form-input receipt-number-input" name="receipt_number" data-batch-id="{{ $batch->StockReceivingBatchID }}" maxlength="50" value="" required placeholder="e.g., SUP-2026-0001">
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
                        <th>Remaining</th>
                        <th>Received Quantity</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($po->items as $item)
                    <tr>
                        <td>{{ $item->product?->ProductName ?? 'Unknown' }}</td>
                        <td>{{ $item->product?->Model ?: '—' }}</td>
                        <td style="white-space: nowrap;">{{ $item->product?->SKU ?: '—' }}</td>
                        {{-- In the unit it was ordered in (2 rolls); receiving adds
                             rolls x meters per roll (entered below) to stock. --}}
                        <td>{{ $item->tableQuantity() }}</td>
                        <td>{{ $item->tableQuantity($item->ReceivedQuantity) }}</td>
                        @if($anyCancelled)
                            <td>{{ (int) $item->CancelledQuantity ? $item->tableQuantity($item->CancelledQuantity) : '—' }}</td>
                        @endif
                        @if($isPending)
                            <td><strong>{{ $item->tableQuantity($item->remaining_quantity) }}</strong></td>
                            {{-- Ordered by the roll/box: how many meters/pieces each one holds
                                 is entered here, per delivery (the last size is pre-filled). --}}
                            @php($baseUnit = $item->product?->baseUnit() ?? 'piece')
                            @php($needsSize = $item->product && ($item->Unit ?: 'piece') !== $baseUnit)
                            @php($size = $needsSize ? ((float) $item->QuantityPerUnit > 1 ? $item->QuantityPerUnit : $item->product->purchaseUnitSize()) : null)
                            @php($basePlural = \App\Services\UnitOfMeasure::plural($baseUnit))
                            <td>
                                <input type="hidden" name="items[{{ $loop->index }}][purchase_order_item_id]" value="{{ $item->PurchaseOrderItemID }}">
                                {{-- This delivery only; defaults to everything remaining and
                                     stops at it as it's typed (never above Remaining or Ordered). --}}
                                <input type="number" class="form-input receiving-now-input" style="max-width:110px;" name="items[{{ $loop->index }}][quantity_received]" min="0" max="{{ $item->remaining_quantity }}" data-clamp-max value="{{ $item->remaining_quantity }}" @if($item->remaining_quantity <= 0) readonly @endif required oninput="window.updateReceivingTotal && window.updateReceivingTotal(this)">
                                @if($item->Unit && $item->Unit !== 'piece')<small style="display:block; color:#94a3b8;">{{ \App\Services\UnitOfMeasure::plural($item->Unit) }}</small>@endif
                                @if($needsSize)
                                    <label class="receiving-size">
                                        <span>{{ ucfirst($basePlural) }} per {{ $item->Unit }}</span>
                                        <input type="number" class="form-input receiving-size-input" name="items[{{ $loop->index }}][quantity_per_unit]" min="0.01" step="{{ $item->product->AllowsFractionalQuantity ? '0.01' : '1' }}"
                                               value="{{ $size ? \App\Casts\Quantity::normalize($size) : '' }}" placeholder="e.g. 1000" @if($item->remaining_quantity > 0) required @else readonly @endif oninput="window.updateReceivingTotal && window.updateReceivingTotal(this)">
                                    </label>
                                    <small class="receiving-size-total" data-base-plural="{{ $basePlural }}">{{ $size && $item->remaining_quantity > 0 ? '= '.\App\Services\UnitOfMeasure::number($item->remaining_quantity * $size).' '.$basePlural.' to stock' : '' }}</small>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</form>

{{-- The supplier has no stock: what's still owed on the ticked products is
     cancelled here and ordered from another supplier as a new Draft
     purchase order (StockReceivingController::reorderFromAnotherSupplier()).
     Opened by the modal's "Reorder from Another Supplier" button. --}}
@if($canReorder ?? false)
    <form id="reorderElsewhereForm" class="reorder-elsewhere" method="POST" action="{{ route('admin.stock-receivings.batches.reorder', $batch) }}" style="display: none;">
        @csrf
        <div class="section-title"><i class="fas fa-rotate"></i> Reorder from Another Supplier</div>
        <p class="reorder-elsewhere-hint">If {{ $po->supplier?->SupplierName ?? 'the supplier' }} has no stock, tick what should come from another supplier. What's still owed on those products is cancelled on {{ $po->PONumber }} and ordered as a new Draft purchase order, which you review and print in Purchase Orders.</p>
        <div class="reorder-elsewhere-fields">
            <label>
                <span class="reorder-elsewhere-label">New Supplier <span class="required">*</span></span>
                <select name="supplier_id" id="reorderSupplierSelect" class="form-select" required>
                    <option value="">Select Supplier</option>
                    @foreach(($reorderSuppliers ?? collect())->groupBy(fn ($row) => $row['suppliesThese'] ? 'Supplies these products' : 'Other suppliers') as $group => $rows)
                        <optgroup label="{{ $group }}">
                            @foreach($rows as $row)
                                <option value="{{ $row['supplier']->SupplierID }}">{{ $row['supplier']->SupplierName }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="reorder-elsewhere-label">Reason <span class="required">*</span></span>
                <input type="text" name="reason" class="form-input" maxlength="255" required value="Supplier has no stock">
            </label>
        </div>
        <table class="items-table">
            <thead>
                <tr><th style="width: 1%;"></th><th>Product</th><th>Remaining</th></tr>
            </thead>
            <tbody>
                @foreach($po->items->filter(fn ($item) => $item->remaining_quantity > 0) as $item)
                    <tr>
                        <td><input type="checkbox" name="items[]" value="{{ $item->PurchaseOrderItemID }}" checked aria-label="Reorder {{ $item->product?->ProductName }}"></td>
                        <td>{{ $item->product?->ProductName ?? 'Unknown' }}</td>
                        <td>{{ $item->tableQuantity($item->remaining_quantity) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </form>
@endif

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
                        {{-- "2 rolls (2,000 meters) x UTP Cable": the meters this delivery added. --}}
                        <td>{{ $delivery->items->map(function ($line) {
                            $orderLine = $line->purchaseOrderItem;
                            $baseUnit = $line->product?->baseUnit();
                            $quantity = $orderLine ? \App\Services\UnitOfMeasure::cell($line->Quantity, $orderLine->Unit) : $line->Quantity;
                            if ($orderLine && $baseUnit && ($orderLine->Unit ?: 'piece') !== $baseUnit && $line->BaseQuantity !== null) {
                                $quantity .= ' ('.\App\Services\UnitOfMeasure::format($line->BaseQuantity, $baseUnit).')';
                            }

                            return $quantity.' x '.($line->product?->ProductName ?? 'Unknown');
                        })->implode(', ') }}</td>
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
