{{-- Shared "View Details" content. Included by both the standalone show page
     and the View Details modal. Expects $product (with brand/category/inventory
     loaded), $profit, $margin, $status in scope. --}}
<div class="detail-row">
    <span class="detail-label">Product Name</span>
    <span class="detail-value"><strong>{{ $product->ProductName }}</strong></span>
</div>
<div class="detail-row">
    <span class="detail-label">Model</span>
    <span class="detail-value">{{ $product->Model ?? 'N/A' }}</span>
</div>
<div class="detail-row">
    <span class="detail-label">SKU</span>
    <span class="detail-value"><code>{{ $product->SKU ?? '-' }}</code></span>
</div>
<div class="detail-row">
    <span class="detail-label">Barcode</span>
    <span class="detail-value"><code>{{ $product->Barcode ?? '-' }}</code></span>
</div>
<div class="detail-row">
    <span class="detail-label">Category</span>
    <span class="detail-value">{{ $product->category?->CategoryName ?? 'Uncategorized' }}</span>
</div>
<div class="detail-row">
    <span class="detail-label">Brand</span>
    <span class="detail-value">{{ $product->brand?->BrandName ?? 'N/A' }}</span>
</div>
<div class="detail-row">
    <span class="detail-label">Description</span>
    <span class="detail-value">{{ $product->Description ?? 'No description provided.' }}</span>
</div>
<div class="detail-row">
    <span class="detail-label">Cost Price</span>
    <span class="detail-value">₱{{ number_format($product->CostPrice ?? 0, 2) }}</span>
</div>
<div class="detail-row">
    <span class="detail-label">Selling Price</span>
    <span class="detail-value">₱{{ number_format($product->Price, 2) }}</span>
</div>
<div class="detail-row">
    <span class="detail-label">Profit</span>
    <span class="detail-value">₱{{ number_format($profit, 2) }} ({{ number_format($margin, 1) }}%)</span>
</div>
<div class="detail-row">
    <span class="detail-label">Quantity</span>
    <span class="detail-value">{{ $product->inventory?->Quantity ?? 0 }}</span>
</div>
<div class="detail-row">
    <span class="detail-label">Reorder Threshold</span>
    <span class="detail-value">{{ $product->inventory?->ReorderThreshold ?? 0 }}</span>
</div>
<div class="detail-row">
    <span class="detail-label">Stock Status</span>
    <span class="detail-value">
        <span class="badge {{ $status['class'] }}">
            <i class="fas {{ $status['icon'] }}"></i> {{ $status['label'] }}
        </span>
    </span>
</div>
