{{-- Shared "Record Stock Receiving" field markup. Included by both the
     standalone create page and the Record Receipt modal. When $purchaseOrder
     is set, receiving is against that PO's remaining line items instead of
     a free product/supplier pick — ad-hoc receiving (no PO) is unchanged. --}}
<div class="form-grid">
    @if(isset($purchaseOrder))
        <input type="hidden" name="purchase_order_id" value="{{ $purchaseOrder->PurchaseOrderID }}">
        <div class="form-group full-width">
            <p style="margin:0 0 4px; color: var(--text-secondary);">Receiving against <strong>{{ $purchaseOrder->PONumber }}</strong> — {{ $purchaseOrder->supplier?->SupplierName }}</p>
        </div>
        <div class="form-group full-width">
            <label class="form-label" for="purchase_order_item_id">Order Line <span class="required">*</span></label>
            <select id="purchase_order_item_id" name="purchase_order_item_id" class="form-select" required onchange="onReceivingLineChange(this)">
                <option value="">Select Line</option>
                @foreach($purchaseOrder->items->where('remaining_quantity', '>', 0) as $item)
                    <option value="{{ $item->PurchaseOrderItemID }}" data-remaining="{{ $item->remaining_quantity }}">
                        {{ $item->product?->ProductName ?? 'Unknown' }} — {{ $item->remaining_quantity }} remaining of {{ $item->Quantity }}
                    </option>
                @endforeach
            </select>
            <span class="form-error" id="error-purchase_order_item_id">@error('purchase_order_item_id'){{ $message }}@enderror</span>
        </div>
    @else
        <div class="form-group full-width">
            <label class="form-label" for="ProductID">Product <span class="required">*</span></label>
            <select id="ProductID" name="ProductID" class="form-select" required>
                <option value="">Select Product</option>
                @foreach($products as $product)
                    <option value="{{ $product->ProductID }}" {{ old('ProductID') == $product->ProductID ? 'selected' : '' }}>
                        {{ $product->ProductName }} - {{ $product->Model }}
                    </option>
                @endforeach
            </select>
            <span class="form-error" id="error-ProductID">@error('ProductID'){{ $message }}@enderror</span>
        </div>

        <div class="form-group full-width">
            <label class="form-label" for="SupplierID">Supplier <span class="required">*</span></label>
            <select id="SupplierID" name="SupplierID" class="form-select" required>
                <option value="">Select Supplier</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->SupplierID }}" {{ old('SupplierID') == $supplier->SupplierID ? 'selected' : '' }}>
                        {{ $supplier->SupplierName }}
                    </option>
                @endforeach
            </select>
            <span class="form-error" id="error-SupplierID">@error('SupplierID'){{ $message }}@enderror</span>
        </div>
    @endif

    <div class="form-group">
        <label class="form-label" for="Quantity">Quantity Received <span class="required">*</span></label>
        <input type="number" id="Quantity" name="Quantity" class="form-input"
               value="{{ old('Quantity') }}" required min="1">
        <span class="form-error" id="error-Quantity">@error('Quantity'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label" for="ReceiptNumber">Receipt Number <span class="required">*</span></label>
        <input type="text" id="ReceiptNumber" name="ReceiptNumber" class="form-input"
               value="{{ old('ReceiptNumber') }}" required maxlength="50" placeholder="e.g., SUP-2026-0001">
        <span class="form-error" id="error-ReceiptNumber">@error('ReceiptNumber'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <label class="form-label" for="DateReceived">Date Received <span class="required">*</span></label>
        <input type="date" id="DateReceived" name="DateReceived" class="form-input"
               value="{{ old('DateReceived', date('Y-m-d')) }}" required>
        <span class="form-error" id="error-DateReceived">@error('DateReceived'){{ $message }}@enderror</span>
    </div>
</div>

@if(isset($purchaseOrder))
<script>
    // Client-side hint only — the server re-validates the remaining
    // quantity for the selected line inside a locked transaction regardless.
    function onReceivingLineChange(select) {
        const option = select.options[select.selectedIndex];
        const qtyInput = document.getElementById('Quantity');
        if (qtyInput && option && option.dataset.remaining) {
            qtyInput.max = option.dataset.remaining;
        }
    }
</script>
@endif
