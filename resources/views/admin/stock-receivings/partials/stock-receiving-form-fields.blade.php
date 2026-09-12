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
        @php
            // For re-showing the typed product name if the form re-renders
            // after a validation error (old('ProductID') is still the ID).
            $oldProduct = old('ProductID') ? $products->firstWhere('ProductID', (int) old('ProductID')) : null;
        @endphp
        <div class="form-group full-width">
            <label class="form-label" for="ProductSearch">Product <span class="required">*</span></label>
            {{-- A single searchable field, not a plain <select>: typing
                 filters the browser's own native datalist suggestions
                 immediately, no separate search box alongside it. The
                 datalist only carries display names (HTML datalists can't
                 carry a separate id), so the matching ProductID is looked
                 up client-side and kept in the hidden input actually
                 submitted. --}}
            <input type="text" id="ProductSearch" class="form-input" list="productOptions" autocomplete="off"
                   placeholder="Type to search product…" required
                   value="{{ old('_ProductSearch', $oldProduct ? $oldProduct->ProductName . ' - ' . $oldProduct->Model : '') }}">
            <datalist id="productOptions">
                @foreach($products as $product)
                    <option data-product-id="{{ $product->ProductID }}" value="{{ $product->ProductName }} - {{ $product->Model }}"></option>
                @endforeach
            </datalist>
            <input type="hidden" id="ProductID" name="ProductID" value="{{ old('ProductID', $oldProduct->ProductID ?? '') }}">
            <span class="form-error" id="error-ProductID">@error('ProductID'){{ $message }}@enderror</span>
        </div>

        <script>
            (function () {
                var searchInput = document.getElementById('ProductSearch');
                var hiddenId = document.getElementById('ProductID');
                var datalist = document.getElementById('productOptions');
                if (!searchInput || !hiddenId || !datalist) return;

                searchInput.addEventListener('input', function () {
                    var typed = searchInput.value;
                    var options = datalist.querySelectorAll('option');
                    var matched = null;
                    for (var i = 0; i < options.length; i++) {
                        if (options[i].value === typed) { matched = options[i]; break; }
                    }
                    // Cleared, not left stale, when the typed text no longer
                    // matches an actual product exactly — prevents
                    // submitting whatever the last valid selection was.
                    hiddenId.value = matched ? matched.dataset.productId : '';
                });
            })();
        </script>

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
