{{-- Shared "View Details" content. Included by both the standalone show page
     and the View Details modal. Expects $product (with brand/category/inventory
     loaded), $profit, $margin, $status in scope. --}}
<div class="product-photo-row">
    @include('partials.product-thumb', ['product' => $product, 'class' => 'thumb-large'])
</div>
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
            <th>SKU</th>
            <td><code>{{ $product->SKU ?? '-' }}</code></td>
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

@if($product->tracksSerialNumbers())
    {{-- One serial number per piece; each sticker is that piece's barcode at the POS. --}}
    @php($inStockSerials = $product->serials->where('Status', \App\Models\ProductSerial::STATUS_IN_STOCK))
    <form method="GET" action="{{ route('admin.products.serial-stickers', $product) }}" target="_blank"
          class="serial-section js-confirm-submit" data-confirm-title="Print Serial Stickers" data-confirm-text="Open the ticked serial number stickers as a PDF for printing?">
        <div class="serial-section-head">
            <h4><i class="fas fa-barcode"></i> Serial Numbers <span class="serial-count">{{ $inStockSerials->count() }} in stock</span></h4>
            @if($inStockSerials->isNotEmpty())
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-print"></i> Print Stickers (PDF)</button>
            @endif
        </div>
        @if($product->serials->isEmpty())
            <p class="serial-empty">No serial numbers yet. They are created when stock is received.</p>
        @else
            <div class="serial-table-wrap">
                <table class="serial-table">
                    <thead>
                        <tr>
                            <th>
                                @if($inStockSerials->isNotEmpty())
                                    <input type="checkbox" checked title="Select all in stock"
                                           onchange="this.closest('form').querySelectorAll('input[name=&quot;serials[]&quot;]').forEach(function (box) { box.checked = this.checked; }, this)">
                                @endif
                            </th>
                            <th>Serial Number</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($product->serials as $serial)
                            <tr>
                                <td>
                                    @if($serial->Status === \App\Models\ProductSerial::STATUS_IN_STOCK)
                                        <input type="checkbox" name="serials[]" value="{{ $serial->ProductSerialID }}" checked>
                                    @endif
                                </td>
                                <td><code>{{ $serial->SerialNumber }}</code></td>
                                <td>
                                    <span class="serial-status {{ $serial->Status === \App\Models\ProductSerial::STATUS_IN_STOCK ? 'in-stock' : 'out' }}">{{ $serial->Status }}</span>
                                    @if($serial->SalesTransactionID)
                                        <small>RCT-{{ str_pad((string) $serial->SalesTransactionID, 6, '0', STR_PAD_LEFT) }}</small>
                                    @endif
                                </td>
                                <td>{{ $serial->StatusDate?->format('m/d/Y h:i A') ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </form>
    <style>
        .serial-section { margin-top: 18px; }
        .serial-section-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; }
        .serial-section-head h4 { margin: 0; font-size: 0.95rem; color: #e2e8f0; }
        .serial-count { margin-left: 6px; font-size: 0.75rem; font-weight: 600; color: #34d399; }
        .serial-empty { color: #94a3b8; font-size: 0.85rem; margin: 0; }
        .serial-table-wrap { max-height: 260px; overflow-y: auto; border: 1px solid rgba(148, 163, 184, 0.2); border-radius: 10px; }
        .serial-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        .serial-table th, .serial-table td { padding: 7px 10px; text-align: left; border-bottom: 1px solid rgba(148, 163, 184, 0.12); color: #cbd5e1; }
        .serial-table th { position: sticky; top: 0; background: #1e293b; color: #94a3b8; font-weight: 600; }
        .serial-table small { display: block; color: #94a3b8; font-size: 0.72rem; }
        .serial-status { font-weight: 600; }
        .serial-status.in-stock { color: #34d399; }
        .serial-status.out { color: #94a3b8; }
    </style>
@endif
