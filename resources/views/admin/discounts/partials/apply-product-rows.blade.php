{{-- Compact product picker rows for the Apply tab — shared by the initial
     page load and the live-search AJAX response. Which rows start out
     checked/disabled is handled client-side (index.blade.php's applyCheckedState),
     since that depends on which promo is selected in the dropdown, not on
     anything the server knows about this particular request. Each row is a
     <label> so clicking anywhere on it toggles the checkbox natively. --}}
@forelse($products as $product)
    <label class="apply-product-row" data-product-row data-product-id="{{ $product->ProductID }}" data-product-name="{{ $product->ProductName }}">
        <input type="checkbox" class="apply-product-checkbox" value="{{ $product->ProductID }}" onchange="window.onApplyProductCheckboxChange()">
        <span class="apply-product-info">
            <span class="apply-product-name">{{ $product->ProductName }}</span>
            <span class="apply-product-meta">SKU: {{ $product->SKU ?? '—' }} &middot; {{ $product->category?->CategoryName ?? '—' }}</span>
        </span>
        <span class="apply-product-price">₱{{ number_format($product->Price, 2) }}</span>
    </label>
@empty
    <div class="empty-state">
        <p class="empty-text">No products match your search.</p>
    </div>
@endforelse
