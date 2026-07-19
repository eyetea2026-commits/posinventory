{{-- Shared "Create Purchase Order" field markup. Included by both the
     standalone create page and the Create Purchase Order modal. --}}
<style>
    .order-item-row {
        display: grid;
        grid-template-columns: 2fr 1fr auto;
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

    <div class="form-group">
        <label class="form-label" for="Status">Status <span style="color: var(--danger);">*</span></label>
        <select id="Status" name="Status" class="form-select" required>
            <option value="pending" {{ old('Status') === 'pending' ? 'selected' : '' }}>Pending</option>
            <option value="approved" {{ old('Status') === 'approved' ? 'selected' : '' }}>Approved</option>
        </select>
        <span class="form-error" id="error-Status">@error('Status'){{ $message }}@enderror</span>
    </div>
</div>

<div class="order-items-section">
    <h3>Order Items</h3>
    <span class="form-error" id="error-products" style="display: block; margin-bottom: 12px;">@error('products'){{ $message }}@enderror</span>

    <div id="order-items"></div>

    <template id="order-item-template">
        <div class="order-item-row">
            <div class="form-group">
                <label class="form-label">Product</label>
                <select name="products[][product_id]" class="form-select" required>
                    <option value="">Select Product</option>
                    @foreach($products as $product)
                        <option value="{{ $product->ProductID }}">{{ $product->ProductName }} ({{ $product->Model }})</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Quantity Ordered</label>
                <input type="number" name="products[][quantity]" min="1" class="form-input" required>
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

    document.addEventListener('DOMContentLoaded', function () {
        if (document.querySelectorAll('.order-item-row').length === 0) {
            addOrderItem();
        }
    });
</script>
