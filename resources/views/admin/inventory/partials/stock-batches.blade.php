{{-- Each delivery of the product is a stock batch: when, from whom, how
     many (and how many arrived damaged), and its cost, selling price and
     margin as they were when it arrived. Expects $product. --}}
@php
    $batches = \App\Models\StockReceivingDeliveryItem::with(['delivery.purchaseOrder.supplier', 'purchaseOrderItem'])
        ->where('ProductID', $product->ProductID)
        ->orderByDesc('StockReceivingDeliveryItemID')
        ->limit(20)
        ->get();
@endphp
<h2 class="section-title">Stock Batches</h2>
@if($batches->isEmpty())
    <p class="text-muted" style="color:#94a3b8;">No deliveries received yet.</p>
@else
    <div style="overflow-x:auto;">
        <table class="items-table stock-batches-table">
            <thead>
                <tr>
                    <th>Date Received</th>
                    <th>PO / Receipt</th>
                    <th>Supplier</th>
                    <th>Added to Stock</th>
                    <th>Damaged</th>
                    <th>Unit Cost</th>
                    <th>Selling Price</th>
                    <th>Margin</th>
                </tr>
            </thead>
            <tbody>
                @foreach($batches as $batch)
                    @php($margin = $batch->marginPercent())
                    <tr>
                        <td style="white-space:nowrap;">{{ $batch->delivery?->ReceivedAt?->format('m/d/Y') ?? 'N/A' }}</td>
                        <td>{{ $batch->delivery?->purchaseOrder?->PONumber ?? 'N/A' }}<br><small>{{ $batch->delivery?->ReceiptNumber ?: 'N/A' }}</small></td>
                        <td>{{ $batch->delivery?->purchaseOrder?->supplier?->SupplierName ?? 'N/A' }}</td>
                        <td>{{ $product->formatQuantity($batch->BaseQuantity ?? $batch->Quantity) }}</td>
                        <td>{{ (int) $batch->DamagedQuantity > 0 ? $product->formatQuantity($batch->purchaseOrderItem ? $batch->purchaseOrderItem->toBaseQuantity($batch->DamagedQuantity) : $batch->DamagedQuantity) : '—' }}</td>
                        <td style="white-space:nowrap;">{{ $batch->UnitCost !== null ? '₱'.number_format((float) $batch->UnitCost, 2) : 'N/A' }}</td>
                        <td style="white-space:nowrap;">{{ $batch->SellingPrice !== null ? '₱'.number_format((float) $batch->SellingPrice, 2) : 'N/A' }}</td>
                        <td>{{ $margin !== null ? number_format($margin, 1).'%' : 'N/A' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <small style="display:block;margin-top:6px;color:#94a3b8;">Prices are per {{ $product->baseUnit() }}, as they were when the batch arrived. Margin = (Selling Price − Unit Cost) ÷ Selling Price.</small>
@endif
