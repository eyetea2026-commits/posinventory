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

    <div class="form-group">
        <label class="form-label" for="Status">Status <span style="color: var(--danger);">*</span></label>
        <select id="Status" name="Status" class="form-select" required>
            <option value="draft" {{ old('Status', 'draft') === 'draft' ? 'selected' : '' }}>Draft</option>
            <option value="pending" {{ old('Status') === 'pending' ? 'selected' : '' }}>Pending</option>
        </select>
        <span class="form-error" id="error-Status">@error('Status'){{ $message }}@enderror</span>
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
        <div class="form-group" style="margin:0; min-width:220px;">
            <label class="form-label" for="order-category-filter">Filter Products by Category</label>
            <select id="order-category-filter" class="form-select" onchange="filterOrderItemProducts(this.value)">
                <option value="">All Categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->CategoryID }}">{{ $category->CategoryName }}</option>
                @endforeach
            </select>
        </div>
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
                <label class="form-label">Cost Price</label>
                <input type="number" name="products[][cost_price]" min="0" step="0.01" class="form-input order-item-cost">
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
        const categoryFilter = document.getElementById('order-category-filter');
        if (categoryFilter) categoryFilter.value = '';
    }

    // Pre-fills Cost Price from the product's current CostPrice when a
    // product is picked — still fully editable by the admin afterward.
    function onOrderItemProductChange(select) {
        const option = select.options[select.selectedIndex];
        const row = select.closest('.order-item-row');
        const costInput = row?.querySelector('.order-item-cost');
        if (costInput && option && option.dataset.cost) {
            costInput.value = parseFloat(option.dataset.cost).toFixed(2);
        }
    }

    // Filters every product <select> currently in the Order Items list down
    // to the chosen category — purely a picking aid, doesn't affect what's
    // already selected in a row.
    function filterOrderItemProducts(categoryId) {
        document.querySelectorAll('.order-item-product').forEach(function (select) {
            Array.from(select.options).forEach(function (option) {
                if (!option.value) return;
                option.hidden = categoryId !== '' && option.dataset.category !== categoryId;
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (document.querySelectorAll('.order-item-row').length === 0) {
            addOrderItem();
        }
    });
</script>
