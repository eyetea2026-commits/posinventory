<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $purchaseOrder->PONumber }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1a1a1a; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        p.meta { color: #555; margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; }
        th { background: #f0f0f0; }
        .total-row td { font-weight: bold; }
    </style>
</head>
<body>
    <h1>Purchase Order {{ $purchaseOrder->PONumber }}</h1>
    <p class="meta">Supplier: {{ $purchaseOrder->supplier?->SupplierName ?? 'N/A' }}</p>
    <p class="meta">Order Date: {{ \Illuminate\Support\Carbon::parse($purchaseOrder->PurchaseDate)->format('Y-m-d') }}</p>
    <p class="meta">Expected Delivery: {{ $purchaseOrder->ExpectedDeliveryDate ? \Illuminate\Support\Carbon::parse($purchaseOrder->ExpectedDeliveryDate)->format('Y-m-d') : 'N/A' }}</p>
    <p class="meta">Status: {{ \App\Models\PurchaseOrder::STATUS_LABELS[$purchaseOrder->Status] ?? $purchaseOrder->Status }}</p>
    @if($purchaseOrder->Notes)
        <p class="meta">Notes: {{ $purchaseOrder->Notes }}</p>
    @endif

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Ordered Qty</th>
                <th>Received Qty</th>
                <th>Cost Price</th>
                <th>Line Total</th>
            </tr>
        </thead>
        <tbody>
            @php $grandTotal = 0; @endphp
            @foreach($purchaseOrder->items as $item)
                @php $grandTotal += $item->line_total; @endphp
                <tr>
                    <td>{{ $item->product?->ProductName ?? 'N/A' }}</td>
                    <td>{{ $item->Quantity }}</td>
                    <td>{{ $item->ReceivedQuantity }}</td>
                    <td>{{ number_format($item->CostPriceAtOrder, 2) }}</td>
                    <td>{{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="4">Total (received)</td>
                <td>{{ number_format($grandTotal, 2) }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
