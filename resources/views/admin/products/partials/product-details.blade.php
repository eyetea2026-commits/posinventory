{{-- Shared "View Details" content. Included by both the standalone show page
     and the View Details modal. Expects $product (with brand/category/inventory
     loaded), $profit, $margin, $status in scope. --}}
<table class="detail-table">
    <tbody>
        <tr>
            <th>Product Name</th>
            <td><strong>{{ $product->ProductName }}</strong></td>
        </tr>
        <tr>
            <th>Model</th>
            <td>{{ $product->Model ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Barcode</th>
            <td><code>{{ $product->Barcode ?? '-' }}</code></td>
        </tr>
        <tr>
            <th>Category</th>
            <td>{{ $product->category?->CategoryName ?? 'Uncategorized' }}</td>
        </tr>
        <tr>
            <th>Brand</th>
            <td>{{ $product->brand?->BrandName ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Description</th>
            <td>{{ $product->Description ?? 'No description provided.' }}</td>
        </tr>
        <tr>
            <th>Cost Price</th>
            <td>₱{{ number_format($product->CostPrice ?? 0, 2) }}</td>
        </tr>
        <tr>
            <th>Selling Price</th>
            <td>₱{{ number_format($product->Price, 2) }}</td>
        </tr>
        <tr>
            <th>Profit</th>
            <td>₱{{ number_format($profit, 2) }} ({{ number_format($margin, 1) }}%)</td>
        </tr>
        <tr>
            <th>Quantity</th>
            <td>{{ $product->inventory?->Quantity ?? 0 }}</td>
        </tr>
        <tr>
            <th>Reorder Threshold</th>
            <td>{{ $product->inventory?->ReorderThreshold ?? 0 }}</td>
        </tr>
        <tr>
            <th>Stock Status</th>
            <td>
                <span class="badge {{ $status['class'] }}">
                    <i class="fas {{ $status['icon'] }}"></i> {{ $status['label'] }}
                </span>
            </td>
        </tr>
    </tbody>
</table>
