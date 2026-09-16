{{-- Shared "Create Purchase Order" field markup. Included by both the
     standalone create page and the Create Purchase Order modal. --}}
<style>
    .order-item-row {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr auto;
        gap: 16px;
        align-items: end;
        padding: 16px;
        background: var(--bg-hover, rgba(30, 41, 59, 0.6));
        border: 1px solid var(--border, rgba(148, 163, 184, 0.2));
        border-radius: 12px;
        margin-bottom: 12px;
    }
    .order-items-section {
        border-top: 1px solid var(--border, rgba(148, 163, 184, 0.2));
        padding-top: 20px;
        margin-top: 8px;
    }
    .order-items-section h3 {
        margin: 0 0 16px;
        font-size: 1.05rem;
        font-weight: 600;
        color: var(--text-primary, #f8fafc);
    }
    @media (max-width: 700px) {
        .order-item-row { grid-template-columns: 1fr; }
    }
</style>

<div class="form-grid">
    <div class="form-group">
        <label class="form-label" for="SupplierID">Supplier <span style="color: var(--danger);">*</span></label>
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

    <div class="form-group">
        <label class="form-label" for="PurchaseDate">Purchase Date <span style="color: var(--danger);">*</span></label>
        <input type="date" id="PurchaseDate" name="PurchaseDate" class="form-input" value="{{ old('PurchaseDate', now()->toDateString()) }}" required>
        <span class="form-error" id="error-PurchaseDate">@error('PurchaseDate'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label" for="ExpectedDeliveryDate">Expected Delivery Date</label>
        <input type="date" id="ExpectedDeliveryDate" name="ExpectedDeliveryDate" class="form-input" value="{{ old('ExpectedDeliveryDate') }}">
        <span class="form-error" id="error-ExpectedDeliveryDate">@error('ExpectedDeliveryDate'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <label class="form-label" for="Notes">Notes</label>
        <textarea id="Notes" name="Notes" class="form-textarea" placeholder="Optional notes...">{{ old('Notes') }}</textarea>
        <span class="form-error" id="error-Notes">@error('Notes'){{ $message }}@enderror</span>
    </div>
</div>

<div class="order-items-section">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
        <h3 style="margin:0;">Order Items</h3>
    </div>
    <span class="form-error" id="error-products" style="display: block; margin-bottom: 12px;">@error('products'){{ $message }}@enderror</span>

    <div id="order-items"></div>

    <template id="order-item-template">
        <div class="order-item-row">
            <div class="form-group">
                <label class="form-label">Product</label>
                <select name="products[][product_id]" class="form-select order-item-product" required onchange="onOrderItemProductChange(this)">
                    <option value="">Select Product</option>
                    @foreach($products as $product)
                        <option value="{{ $product->ProductID }}" data-category="{{ $product->CategoryID }}" data-cost="{{ $product->CostPrice }}">{{ $product->ProductName }} ({{ $product->Model }})</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Quantity Ordered</label>
                <input type="number" name="products[][quantity]" min="1" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Previous Price</label>
                <div class="form-input order-item-previous-cost" style="background: var(--bg-hover, rgba(30, 41, 59, 0.6)); display:flex; align-items:center;">N/A</div>
            </div>
            <div class="form-group">
                <label class="form-label">Current Price <span style="color: var(--danger);">*</span></label>
                <input type="number" name="products[][cost_price]" min="0.01" step="0.01" class="form-input order-item-cost" required>
            </div>
            <button type="button" onclick="removeOrderItem(this)" class="btn btn-danger btn-icon" title="Remove item">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </template>

    <button type="button" onclick="addOrderItem()" class="btn btn-secondary">
        <i class="fas fa-plus"></i> Add Item
    </button>
</div>

<script>
    // Each row needs an explicit shared index — "products[][product_id]" and
    // "products[][quantity]" as two separate bare-bracket fields do NOT get
    // paired into one row by PHP's array parser; each occurrence of "[]"
    // bumps its own auto-index independently, so a row's product_id and
    // quantity silently land in two different array entries. Rewriting each
    // clone's "[]" to an explicit "[N]" keeps a row's fields together.
    let orderItemIndex = 0;

    function addOrderItem() {
        const template = document.querySelector('#order-item-template');
        const container = document.querySelector('#order-items');
        const clone = template.content.cloneNode(true);
        const idx = orderItemIndex++;
        clone.querySelectorAll('[name]').forEach(function (el) {
            el.name = el.name.replace('[]', '[' + idx + ']');
        });
        container.appendChild(clone);
    }

    function removeOrderItem(button) {
        const row = button.closest('.order-item-row');
        if (document.querySelectorAll('.order-item-row').length > 1) {
            row?.remove();
        }
    }

    function resetOrderItems() {
        const container = document.querySelector('#order-items');
        container.innerHTML = '';
        orderItemIndex = 0;
        addOrderItem();
    }

    // Shows the product's currently stored cost as the read-only "Previous
    // Price" (never editable — that's the whole point of it existing
    // alongside Current Price) and pre-fills Current Price from it, still
    // fully editable by the admin afterward. A null/0 stored cost means
    // nothing is on record yet, shown as "N/A" rather than a fake ₱0.00.
    function onOrderItemProductChange(select) {
        const option = select.options[select.selectedIndex];
        const row = select.closest('.order-item-row');
        const costInput = row?.querySelector('.order-item-cost');
        const previousCostEl = row?.querySelector('.order-item-previous-cost');
        const cost = option ? parseFloat(option.dataset.cost) : NaN;
        const hasPreviousCost = !isNaN(cost) && cost > 0;

        if (previousCostEl) {
            previousCostEl.textContent = hasPreviousCost ? '₱' + cost.toFixed(2) : 'N/A';
        }
        if (costInput && hasPreviousCost) {
            costInput.value = cost.toFixed(2);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (document.querySelectorAll('.order-item-row').length === 0) {
            addOrderItem();
        }
    });
</script>
