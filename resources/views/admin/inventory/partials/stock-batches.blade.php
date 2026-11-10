{{-- FIFO stock batches: every lot of this product that came in, oldest
     first (the order sales take from them), with its own unit cost,
     selling price, margin, what is left of it and what its sold pieces
     made. Expects $product. --}}
@php
    $batches = \App\Models\StockBatch::with(['supplier', 'purchaseOrder', 'deliveryItem.purchaseOrderItem', 'allocations.salesItem'])
        ->where('ProductID', $product->ProductID)
        ->fifo()
        ->get();
    $nextBatchId = $batches->first(fn ($batch) => (float) $batch->QuantityRemaining > 0)?->StockBatchID;
    $remainingTotal = \App\Casts\Quantity::normalize($batches->sum(fn ($batch) => (float) $batch->QuantityRemaining));
    $stockValue = round($batches->sum(fn ($batch) => $batch->remainingValue()), 2);
    $profitTotal = round($batches->sum(fn ($batch) => $batch->realizedProfit()), 2);
@endphp
<h2 class="section-title">Stock Batches (FIFO)</h2>
@if($batches->isEmpty())
    <p class="text-muted" style="color:#94a3b8;">No stock batches yet.</p>
@else
    <div class="stock-batch-summary">
        <div><span>On Hand</span><strong>{{ $product->formatQuantity($remainingTotal) }}</strong></div>
        <div><span>Stock Value (FIFO)</span><strong>₱{{ number_format($stockValue, 2) }}</strong></div>
        <div><span>Average Cost</span><strong>{{ (float) $remainingTotal > 0 ? '₱'.number_format($stockValue / (float) $remainingTotal, 2) : 'N/A' }}</strong></div>
        <div><span>Profit from Sales</span><strong>₱{{ number_format($profitTotal, 2) }}</strong></div>
    </div>
    <div style="overflow-x:auto;">
        <table class="items-table stock-batches-table">
            <thead>
                <tr>
                    <th>Batch</th>
                    <th>Date Received</th>
                    <th>Source</th>
                    <th>Supplier</th>
                    <th>Received</th>
                    <th>Damaged</th>
                    <th>Sold</th>
                    <th>Remaining</th>
                    <th>Unit Cost</th>
                    <th>Selling Price</th>
                    <th>Margin</th>
                    <th>Profit</th>
                </tr>
            </thead>
            <tbody>
                @foreach($batches as $batch)
                    @php
                        $margin = $batch->marginPercent();
                        $deliveryItem = $batch->deliveryItem;
                        $damaged = $deliveryItem && (int) $deliveryItem->DamagedQuantity > 0
                            ? ($deliveryItem->purchaseOrderItem ? $deliveryItem->purchaseOrderItem->toBaseQuantity($deliveryItem->DamagedQuantity) : $deliveryItem->DamagedQuantity)
                            : null;
                    @endphp
                    <tr @class(['batch-used-up' => (float) $batch->QuantityRemaining <= 0])>
                        <td style="white-space:nowrap;">
                            <code>{{ $batch->BatchNumber }}</code>
                            @if($batch->StockBatchID === $nextBatchId)<br><small class="batch-next">Sells next</small>@endif
                        </td>
                        <td style="white-space:nowrap;">{{ $batch->ReceivedAt?->format('m/d/Y') ?? 'N/A' }}</td>
                        <td>{{ $batch->Source }}@if($batch->purchaseOrder)<br><small>{{ $batch->purchaseOrder->PONumber }}</small>@endif @if($batch->ReceiptNumber)<br><small>{{ $batch->ReceiptNumber }}</small>@endif</td>
                        <td>{{ $batch->supplier?->SupplierName ?? '—' }}</td>
                        <td>{{ $product->formatQuantity($batch->QuantityReceived) }}</td>
                        <td>{{ $damaged !== null ? $product->formatQuantity($damaged) : '—' }}</td>
                        <td>{{ $product->formatQuantity($batch->soldQuantity()) }}</td>
                        <td><strong>{{ $product->formatQuantity($batch->QuantityRemaining) }}</strong></td>
                        <td style="white-space:nowrap;">₱{{ number_format((float) $batch->UnitCost, 2) }}</td>
                        <td style="white-space:nowrap;">{{ $batch->SellingPrice !== null ? '₱'.number_format((float) $batch->SellingPrice, 2) : 'N/A' }}</td>
                        <td>{{ $margin !== null ? number_format($margin, 1).'%' : 'N/A' }}</td>
                        <td style="white-space:nowrap;">₱{{ number_format($batch->realizedProfit(), 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <small style="display:block;margin-top:6px;color:#94a3b8;">First In, First Out: sales take from the oldest batch first, each piece at its own batch's cost. Prices are per {{ $product->baseUnit() }}. Margin = (Selling Price − Unit Cost) ÷ Selling Price. Profit = what the batch's sold pieces were paid (after discounts) − their cost.</small>
    <style>
        .stock-batch-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 10px; }
        .stock-batch-summary div { background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(148, 163, 184, 0.2); border-radius: 10px; padding: 8px 12px; display: flex; flex-direction: column; gap: 2px; }
        .stock-batch-summary span { font-size: 0.72rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.03em; }
        .stock-batch-summary strong { color: #e2e8f0; }
        .stock-batches-table { font-size: 0.82rem; min-width: 980px; }
        .stock-batches-table th, .stock-batches-table td { white-space: nowrap; padding: 8px 10px; vertical-align: top; }
        .stock-batches-table small { color: #94a3b8; }
        .stock-batches-table .batch-used-up td { opacity: 0.55; }
        .stock-batches-table .batch-next { color: #34d399; font-weight: 600; }
    </style>
@endif
