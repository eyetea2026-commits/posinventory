{{-- Shared "Create Purchase Order" field markup. Included by both the
     standalone create page and the Create Purchase Order modal.

     Order Items is one table of every active product (low-stock ones first
     and ticked), with Threshold, Previous Price, Current Price, Quantity and
     Supplier. Only ticked rows are sent; Save creates one Draft Purchase
     Order per supplier (PurchaseOrderController::store()). Notes sit at the
     bottom. --}}
@php
    $orderRows = \App\Http\Controllers\Admin\PurchaseOrderController::orderItemCandidates();
@endphp
<style>
    .po-items-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 10px; }
    .po-items-toolbar h3 { margin: 0; font-size: 1.05rem; font-weight: 600; color: var(--text-primary, #f8fafc); }
    .po-items-title { display: flex; align-items: center; gap: 10px; }
    .po-items-view { width: auto; min-width: 150px; padding: 6px 10px; }
    .po-items-search { flex: 1 1 220px; max-width: 320px; }
    .po-items-summary { color: #94a3b8; font-size: 0.82rem; }
    .po-items-summary strong { color: #e2e8f0; }
    .po-items-wrap { max-height: 420px; overflow: auto; border: 1px solid rgba(148, 163, 184, 0.2); border-radius: 12px; }
    .po-items-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .po-items-table th { position: sticky; top: 0; z-index: 1; background: #1e293b; color: #cbd5e1; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; text-align: center; padding: 10px 8px; border-bottom: 1px solid rgba(148, 163, 184, 0.25); white-space: nowrap; }
    .po-items-table td { padding: 8px; border-bottom: 1px solid rgba(148, 163, 184, 0.1); text-align: center; color: #e2e8f0; vertical-align: middle; }
    .po-items-table tr.is-unselected td { color: #64748b; }
    .po-items-table tr.is-hidden { display: none; }
    .po-items-table td.po-product { text-align: left; min-width: 180px; }
    .po-items-table .po-model { display: block; color: #94a3b8; font-size: 0.75rem; }
    .po-items-table .po-unit-input { display: flex; align-items: center; gap: 6px; }
    .po-items-table .po-unit-input input { min-width: 0; }
    .po-items-table .po-unit-input span { color: #94a3b8; font-size: 0.78rem; white-space: nowrap; }
    .po-items-table .po-unit-holds { display: block; color: #64748b; font-size: 0.72rem; margin-top: 2px; }
    .po-items-table .po-low { display: inline-block; margin-left: 6px; padding: 1px 7px; border-radius: 999px; background: rgba(245, 158, 11, 0.15); color: #fbbf24; font-size: 0.68rem; font-weight: 700; }
    .po-items-table input[type="number"], .po-items-table select { width: 100%; min-width: 90px; padding: 6px 8px; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: #f8fafc; font-size: 0.82rem; text-align: center; }
    .po-items-table input:disabled, .po-items-table select:disabled { opacity: 0.45; }
    .po-items-table input.error, .po-items-table select.error { border-color: #ef4444; }
    .po-items-table select.po-item-supplier { min-width: 200px; text-align: left; }
    .po-items-table input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; }
    .po-select-all { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
    .po-notes { margin-top: 16px; }
</style>

<div class="form-grid">
    <div class="form-group">
        <label class="form-label" for="PurchaseDate">Purchase Date <span style="color: var(--danger);">*</span></label>
        <input type="date" id="PurchaseDate" name="PurchaseDate" class="form-input" value="{{ old('PurchaseDate', now()->toDateString()) }}" required>
        <span class="form-error" id="error-PurchaseDate">@error('PurchaseDate'){{ $message }}@enderror</span>
    </div>
</div>

<div class="order-items-section">
    <div class="po-items-toolbar">
        <div class="po-items-title">
            <h3>Order Items</h3>
            {{-- Which products the table lists; low stock first by default. --}}
            <select id="po-items-view" class="form-select po-items-view" aria-label="Show products" onchange="applyOrderItemsView()">
                <option value="low" selected>Low Stock</option>
                <option value="all">All Products</option>
            </select>
        </div>
        <input type="text" class="form-input po-items-search" id="po-items-search" placeholder="Search products..." autocomplete="off" oninput="applyOrderItemsView()">
        <span class="po-items-summary" id="po-items-summary"></span>
    </div>
    <span class="form-error" id="error-products" style="display: block; margin-bottom: 8px;">@error('items'){{ $message }}@enderror</span>

    <div class="po-items-wrap">
        <table class="po-items-table">
            <thead>
                <tr>
                    <th><label class="po-select-all"><input type="checkbox" id="po-select-all" onchange="toggleAllOrderItems(this.checked)"> Select All</label></th>
                    <th>Product</th>
                    <th>Threshold</th>
                    <th>Previous Price</th>
                    <th>Current Price</th>
                    <th>Quantity</th>
                    <th>Supplier</th>
                </tr>
            </thead>
            <tbody id="order-items">
                @forelse($orderRows as $row)
                    @php($product = $row['product'])
                    <tr class="po-item-row" data-low-stock="{{ $row['lowStock'] ? '1' : '0' }}" data-product-name="{{ strtolower($product->ProductName.' '.$product->Model) }}" data-supplier="{{ $row['supplier']?->SupplierID }}">
                        <td>
                            <input type="checkbox" class="po-item-check" aria-label="Order {{ $product->ProductName }}" {{ $row['lowStock'] ? 'checked' : '' }} onchange="syncOrderItems()">
                            <input type="hidden" name="items[{{ $product->ProductID }}][product_id]" value="{{ $product->ProductID }}">
                        </td>
                        <td class="po-product">
                            {{ $product->ProductName }}@if($row['lowStock'])<span class="po-low">Low Stock</span>@endif
                            <span class="po-model">{{ $product->Model }} · Stock: {{ $row['stockLabel'] }}</span>
                        </td>
                        <td>{{ $row['thresholdLabel'] }}</td>
                        {{-- Prices and quantity are per purchase unit (a roll, a box). --}}
                        <td>{{ $row['previousPrice'] > 0 ? '₱'.number_format($row['previousPrice'], 2).'/'.$row['unit'] : 'N/A' }}</td>
                        <td><div class="po-unit-input"><input type="number" name="items[{{ $product->ProductID }}][cost_price]" min="0.01" max="{{ \App\Models\PurchaseOrderItem::MAX_COST_PRICE }}" step="0.01" data-max-digits="6" value="{{ $row['currentPrice'] > 0 ? number_format($row['currentPrice'], 2, '.', '') : '' }}" required aria-label="Current price per {{ $row['unit'] }}"><span>/{{ $row['unit'] }}</span></div></td>
                        <td>
                            <div class="po-unit-input"><input type="number" name="items[{{ $product->ProductID }}][quantity]" min="1" max="{{ \App\Models\PurchaseOrderItem::MAX_QUANTITY }}" step="1" data-max-digits="3" value="{{ $row['suggestedQuantity'] }}" required aria-label="Quantity in {{ $row['unitPlural'] }}"><span>{{ $row['unitPlural'] }}</span></div>
                            @if($row['unitHolds'])<small class="po-unit-holds">last {{ $row['unit'] }}: {{ $row['unitHolds'] }}</small>@endif
                        </td>
                        <td>
                            {{-- The preferred (or only) supplier is picked already; it can be changed for this order. --}}
                            <select name="items[{{ $product->ProductID }}][supplier_id]" class="po-item-supplier" required aria-label="Supplier" onchange="this.closest('tr').dataset.supplier = this.value; syncOrderItems()">
                                <option value="" {{ $row['supplier'] ? '' : 'selected' }}>Choose supplier</option>
                                @if($row['ownSuppliers']->isNotEmpty())
                                    <optgroup label="This product's suppliers" data-group-color="#34d399">
                                        @foreach($row['ownSuppliers'] as $own)
                                            <option value="{{ $own['supplier']->SupplierID }}" {{ $row['supplier']?->SupplierID === $own['supplier']->SupplierID ? 'selected' : '' }}>{{ $own['supplier']->SupplierName }}{{ $own['preferred'] ? ' ★' : '' }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                @if($row['otherSuppliers']->isNotEmpty())
                                    <optgroup label="{{ $row['ownSuppliers']->isNotEmpty() ? 'Other suppliers' : 'Suppliers' }}" data-group-color="#fbbf24">
                                        @foreach($row['otherSuppliers'] as $other)
                                            <option value="{{ $other->SupplierID }}">{{ $other->SupplierName }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">No active products to order.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="form-group full-width po-notes">
    <label class="form-label" for="Notes">Notes</label>
    <textarea id="Notes" name="Notes" class="form-textarea" placeholder="Optional notes...">{{ old('Notes') }}</textarea>
    <span class="form-error" id="error-Notes">@error('Notes'){{ $message }}@enderror</span>
</div>

<script>
    // Only ticked rows are sent: an unticked row's fields are disabled.
    function syncOrderItems() {
        const rows = Array.from(document.querySelectorAll('#order-items .po-item-row'));
        const suppliers = new Set();
        let selected = 0;
        rows.forEach(function (row) {
            const checked = row.querySelector('.po-item-check').checked;
            row.classList.toggle('is-unselected', !checked);
            row.querySelectorAll('input:not(.po-item-check), select').forEach(function (field) {
                field.disabled = !checked;
                if (!checked) field.classList.remove('error');
            });
            if (checked) {
                selected++;
                if (row.dataset.supplier) suppliers.add(row.dataset.supplier);
            }
        });

        // Select All reflects the rows currently shown.
        const shown = rows.filter(function (row) { return !row.classList.contains('is-hidden'); });
        const shownTicked = shown.filter(function (row) { return row.querySelector('.po-item-check').checked; }).length;
        const selectAll = document.getElementById('po-select-all');
        if (selectAll) {
            selectAll.checked = shown.length > 0 && shownTicked === shown.length;
            selectAll.indeterminate = shownTicked > 0 && shownTicked < shown.length;
        }
        const summary = document.getElementById('po-items-summary');
        if (summary) {
            summary.innerHTML = selected === 0
                ? 'No products ticked'
                : '<strong>' + selected + '</strong> product' + (selected === 1 ? '' : 's') + ' · '
                    + '<strong>' + suppliers.size + '</strong> supplier' + (suppliers.size === 1 ? '' : 's')
                    + ' → ' + suppliers.size + ' purchase order' + (suppliers.size === 1 ? '' : 's');
        }
    }

    function toggleAllOrderItems(checked) {
        document.querySelectorAll('#order-items .po-item-row:not(.is-hidden) .po-item-check').forEach(function (box) {
            box.checked = checked;
        });
        syncOrderItems();
    }

    // Shows Low Stock or All Products (the dropdown beside Order Items),
    // narrowed by the search box. A hidden row keeps its tick.
    function applyOrderItemsView() {
        const view = document.getElementById('po-items-view');
        const lowOnly = !view || view.value === 'low';
        const search = document.getElementById('po-items-search');
        const q = search ? search.value.trim().toLowerCase() : '';
        document.querySelectorAll('#order-items .po-item-row').forEach(function (row) {
            const hidden = (lowOnly && row.dataset.lowStock !== '1') || (q !== '' && !row.dataset.productName.includes(q));
            row.classList.toggle('is-hidden', hidden);
        });
        syncOrderItems();
    }

    // Back to the starting state (low-stock products ticked, suggested
    // quantities); called when the Create Purchase Order popup opens.
    function resetOrderItems() {
        const search = document.getElementById('po-items-search');
        if (search) search.value = '';
        const view = document.getElementById('po-items-view');
        if (view) view.value = 'low';
        applyOrderItemsView();
        document.querySelectorAll('#order-items .po-item-row').forEach(function (row) {
            const supplier = row.querySelector('.po-item-supplier');
            row.dataset.supplier = supplier ? supplier.value : row.dataset.supplier;
        });
        syncOrderItems();
    }

    // Checked before saving: at least one product, and every ticked row has
    // a quantity, a price and a supplier. Shows the first problem above the
    // table and marks the fields. Returns true when the order can be saved.
    window.validateOrderItems = function () {
        const error = document.getElementById('error-products');
        const rows = Array.from(document.querySelectorAll('#order-items .po-item-row')).filter(function (row) {
            return row.querySelector('.po-item-check').checked;
        });
        let message = rows.length === 0 ? 'Tick at least one product to order.' : '';
        let firstInvalid = null;

        rows.forEach(function (row) {
            const name = row.querySelector('.po-product').firstChild.textContent.trim();
            const price = row.querySelector('input[name$="[cost_price]"]');
            const qty = row.querySelector('input[name$="[quantity]"]');
            const supplier = row.querySelector('.po-item-supplier');
            [[price, !(parseFloat(price.value) > 0), 'Enter a Current Price for "' + name + '".'],
             [price, parseFloat(price.value) > parseFloat(price.max), 'Current Price for "' + name + '" can be at most 6 digits.'],
             [qty, !(parseInt(qty.value, 10) >= 1), 'Enter a Quantity for "' + name + '".'],
             [qty, parseInt(qty.value, 10) > parseInt(qty.max, 10), 'Quantity for "' + name + '" can be at most 3 digits.'],
             [supplier, supplier && !supplier.value, 'Choose a supplier for "' + name + '".']].forEach(function (check) {
                const field = check[0];
                if (!field) return;
                field.classList.toggle('error', check[1]);
                if (check[1] && !firstInvalid) {
                    firstInvalid = field;
                    message = check[2];
                }
            });
        });

        if (error) error.textContent = message;
        if (firstInvalid) {
            firstInvalid.scrollIntoView({ block: 'center' });
            firstInvalid.focus();
        }

        return message === '';
    };

    document.addEventListener('DOMContentLoaded', function () {
        applyOrderItemsView();
        syncOrderItems();
    });
</script>
