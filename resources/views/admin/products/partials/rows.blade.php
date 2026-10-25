@forelse($products as $product)
    @php
        $cost = (float) ($product->CostPrice ?? 0);
        $price = (float) $product->Price;
        $profit = $price - $cost;
        $margin = $price > 0 ? ($profit / $price) * 100 : 0;
    @endphp
    <tr class="{{ $product->IsActive ? '' : 'is-inactive' }}">
        <td class="col-product">
            <div class="product-cell">
            @include('partials.product-thumb', ['product' => $product])
            <div class="product-info">
                <span class="product-name">
                    {{ $product->ProductName }}
                    @unless($product->IsActive)
                        <span class="product-status-badge">Inactive</span>
                    @endunless
                    @unless($product->IsReturnable)
                        <span class="product-status-badge non-returnable-badge" title="Can't be returned or refunded once sold">Not returnable</span>
                    @endunless
                </span>
                <span class="product-model">{{ $product->Model ?? 'N/A' }}</span>
                @if(!empty($product->Description))
                    <span class="product-desc">{{ $product->Description }}</span>
                @endif
            </div>
            </div>
        </td>
        <td class="col-category">
            <span class="category-chip">
                <i class="fas fa-tag"></i>
                {{ $product->category?->CategoryName ?? 'Uncategorized' }}
            </span>
        </td>
        <td class="col-pricing">
            <div class="pricing-info">
                <span class="pricing-sell">₱{{ number_format($price, 2) }}</span>
                <div class="pricing-row">
                    <span class="pricing-label">Cost:</span>
                    <span class="pricing-value">₱{{ number_format($cost, 2) }}</span>
                </div>
                <div class="pricing-row">
                    <span class="pricing-label">Profit:</span>
                    <span class="pricing-value">₱{{ number_format($profit, 2) }}</span>
                </div>
                <div class="pricing-row">
                    <span class="pricing-label">Margin:</span>
                    <span class="pricing-value pricing-profit {{ $profit < 0 ? 'negative' : 'positive' }}">
                        {{ number_format($margin, 1) }}%
                    </span>
                </div>
            </div>
        </td>
        <td class="col-actions">
            <div class="actions-group">
                <a
                    href="{{ route('admin.products.show', $product) }}"
                    class="action-btn view"
                    data-bs-toggle="tooltip"
                    data-bs-placement="top"
                    title="View Details"
                    aria-label="View Details"
                    onclick="openViewProductModal(event, {{ $product->ProductID }})"
                >
                    <i class="fas fa-eye"></i>
                </a>
                <form
                    action="{{ route('admin.products.toggle-status', $product) }}"
                    method="POST"
                    style="display:inline;"
                    id="statusForm{{ $product->ProductID }}"
                >
                    @csrf
                    @method('PATCH')
                    <button
                        type="button"
                        class="action-btn {{ $product->IsActive ? 'deactivate' : 'activate' }}"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="{{ $product->IsActive ? 'Deactivate' : 'Activate' }}"
                        aria-label="{{ $product->IsActive ? 'Deactivate' : 'Activate' }}"
                        onclick="confirmToggleProductStatus({{ $product->ProductID }}, {{ $product->IsActive ? 'true' : 'false' }})"
                    >
                        <i class="fas {{ $product->IsActive ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                    </button>
                </form>
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="4">
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-boxes"></i>
                </div>
                <p class="empty-title">No Products Found</p>
                <p class="empty-text">
                    @if(request('category_id'))
                        No products are available under this category.
                    @elseif(request('search'))
                        No products match your search.
                    @else
                        Get started by adding your first product.
                    @endif
                </p>
                <a href="{{ route('admin.products.create') }}" class="btn btn-primary" onclick="openAddProductModal(event)">
                    <i class="fas fa-plus"></i> Add Product
                </a>
            </div>
        </td>
    </tr>
@endforelse
