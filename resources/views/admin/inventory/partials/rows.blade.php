@forelse($products as $product)
    @php
        $quantity = (int) ($product->inventory?->Quantity ?? 0);
        $threshold = (int) ($product->inventory?->ReorderThreshold ?? 50);
        $stock = \App\Http\Controllers\Admin\InventoryController::resolveStockStatus($quantity, $threshold);
        $units30 = (int) ($product->sales_items_sum_quantity ?? 0);
        $velocityClass = match (true) {
            $units30 >= \App\Http\Controllers\Admin\InventoryController::FAST_MOVING_THRESHOLD => 'velocity-fast',
            $units30 < \App\Http\Controllers\Admin\InventoryController::SLOW_MOVING_THRESHOLD => 'velocity-slow',
            default => 'velocity-normal',
        };
    @endphp
    <tr>
        <td class="col-product">
            <div class="product-info">
                <span class="product-name">{{ $product->ProductName }}</span>
                <span class="product-model">{{ $product->Model ?? 'N/A' }}</span>
            </div>
        </td>
        <td class="col-category">
            <span class="category-chip">
                <i class="fas fa-tag"></i>
                {{ $product->category?->CategoryName ?? 'Uncategorized' }}
            </span>
        </td>
        <td class="col-stock">
            <div class="stock-info">
                <span class="stock-quantity">{{ $quantity }}</span>
                <span class="badge {{ $stock['class'] }}">
                    <i class="fas {{ $stock['icon'] }}"></i>
                    {{ $stock['label'] }}
                </span>
            </div>
        </td>
        <td class="col-threshold">
            <span class="threshold-value">{{ $threshold }} <span class="threshold-unit">units</span></span>
        </td>
        <td class="col-velocity">
            <div class="velocity-info">
                <span class="velocity-number {{ $velocityClass }}">{{ $units30 }}</span>
                <span class="velocity-label">units / 30d</span>
            </div>
        </td>
        <td class="col-actions">
            <div class="actions-group">
                <a
                    href="{{ route('admin.inventory.show', $product) }}"
                    class="action-btn view"
                    data-bs-toggle="tooltip"
                    data-bs-placement="top"
                    title="View Details"
                    aria-label="View Details"
                >
                    <i class="fas fa-eye"></i>
                </a>
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="6">
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-warehouse"></i></div>
                <p class="empty-title">No Inventory Records Found</p>
                <p class="empty-text">
                    @if(request('status'))
                        No products match the selected status filter.
                    @elseif(request('search'))
                        No products match your search.
                    @else
                        Start by adding products in the Product Management module.
                    @endif
                </p>
            </div>
        </td>
    </tr>
@endforelse
