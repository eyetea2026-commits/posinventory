{{-- Checkbox product picker for the Apply tab — shared by the initial page
     load and the live-search AJAX response. Which rows start out checked is
     handled client-side (applyPromoJs below), since that depends on which
     promo is selected in the dropdown, not on anything the server knows
     about this particular request. --}}
@forelse($products as $product)
    <tr data-product-row data-product-id="{{ $product->ProductID }}" data-product-name="{{ $product->ProductName }}">
        <td>
            <input type="checkbox" class="apply-product-checkbox" value="{{ $product->ProductID }}" onchange="window.onApplyProductCheckboxChange()">
        </td>
        <td>{{ $product->ProductName }}</td>
        <td>{{ $product->SKU ?? '—' }}</td>
        <td>{{ $product->category?->CategoryName ?? '—' }}</td>
        <td>₱{{ number_format($product->Price, 2) }}</td>
    </tr>
@empty
    <tr>
        <td colspan="5">
            <div class="empty-state">
                <p class="empty-text">No products match your search.</p>
            </div>
        </td>
    </tr>
@endforelse
