{{-- Shared "View Details" content. Included by both the standalone show page
     and the View Details modal. Expects $product (with brand/category/inventory
     loaded), $profit, $margin, $status in scope. --}}
{{-- Label above value, three per row in the pop-up (shared .ui-kv-grid in
     partials/responsive-ui); the photo fills the first two rows' first cell. --}}
<div class="ui-kv-grid">
    <div class="ui-kv-photo">
        @include('partials.product-thumb', ['product' => $product, 'class' => 'thumb-large'])
    </div>
    <div><span>Product Name</span><strong>{{ $product->ProductName }}</strong></div>
    <div><span>Model</span><span>{{ $product->Model ?? 'N/A' }}</span></div>
    <div><span>SKU</span><code>{{ $product->SKU ?? '-' }}</code></div>
    <div><span>Barcode</span><code>{{ $product->Barcode ?? '-' }}</code></div>
    <div><span>Category</span><span>{{ $product->category?->CategoryName ?? 'Uncategorized' }}</span></div>
    <div><span>Brand</span><span>{{ $product->brand?->BrandName ?? 'N/A' }}</span></div>
    <div><span>Cost Price</span><span>₱{{ number_format($product->CostPrice ?? 0, 2) }}</span></div>
    <div><span>Selling Price</span><span>₱{{ number_format($product->Price, 2) }}</span></div>
    <div><span>Profit</span><span>₱{{ number_format($profit, 2) }} ({{ number_format($margin, 1) }}%)</span></div>
    <div><span>Quantity</span><span>{{ $product->inventory?->Quantity ?? 0 }}</span></div>
    <div><span>Reorder Threshold</span><span>{{ $product->inventory?->ReorderThreshold ?? 0 }}</span></div>
    <div>
        <span>Stock Status</span>
        <span class="badge {{ $status['class'] }}"><i class="fas {{ $status['icon'] }}"></i> {{ $status['label'] }}</span>
    </div>
    <div>
        <span>Returns</span>
        @if($product->IsReturnable)
            <span>Returnable</span>
        @else
            <span style="color:#fcd34d;"><i class="fas fa-ban"></i> Not returnable / refundable</span>
        @endif
    </div>
    <div class="ui-kv-wide"><span>Description</span><span>{{ $product->Description ?? 'No description provided.' }}</span></div>
</div>

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
