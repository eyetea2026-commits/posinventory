<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Order {{ $purchaseOrder->PONumber }}</title>
    <style>
        body { font-family: sans-serif; font-size: 13px; color: #1a1a1a; max-width: 800px; margin: 0 auto; padding: 30px; }
        .company-header { text-align: center; border-bottom: 2px solid #1a1a1a; padding-bottom: 14px; margin-bottom: 18px; }
        .company-header h1 { margin: 0; font-size: 22px; letter-spacing: 0.04em; }
        .company-header p { margin: 4px 0 0; color: #555; font-size: 12px; }
        .doc-title { text-align: center; margin: 0 0 20px; }
        .doc-title h2 { margin: 0; font-size: 16px; text-transform: uppercase; letter-spacing: 0.06em; }
        .doc-title p { margin: 2px 0 0; color: #555; }
        .meta-grid { display: flex; justify-content: space-between; gap: 24px; margin-bottom: 20px; }
        .meta-col { flex: 1; }
        .meta-col h3 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #666; margin: 0 0 6px; }
        .meta-col p { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 7px 9px; text-align: left; }
        th { background: #f0f0f0; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; }
        td.num, th.num { text-align: right; }
        .total-row td { font-weight: bold; }
        .signature-area { display: flex; justify-content: space-between; margin-top: 60px; }
        .signature-block { width: 45%; text-align: center; }
        .signature-line { border-top: 1px solid #1a1a1a; padding-top: 6px; margin-top: 50px; }
        .signature-line small { color: #666; }
        .print-btn { position: fixed; top: 20px; right: 20px; padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; }
        @media print { .no-print { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">Print</button>

    <div class="company-header">
        <h1>CCTV Express</h1>
        <p>Your Trusted Security Partner</p>
    </div>

    <div class="doc-title">
        <h2>Purchase Order</h2>
        <p>PO Number: <strong>{{ $purchaseOrder->PONumber }}</strong></p>
    </div>

    <div class="meta-grid">
        <div class="meta-col">
            <h3>Supplier Information</h3>
            <p><strong>{{ $purchaseOrder->supplier?->SupplierName ?? 'N/A' }}</strong></p>
            <p>{{ $purchaseOrder->supplier?->ContactPerson ?? '' }}</p>
            <p>{{ $purchaseOrder->supplier?->ContactNumber ?? 'N/A' }}</p>
            <p>{{ $purchaseOrder->supplier?->Email ?? 'N/A' }}</p>
            <p>{{ $purchaseOrder->supplier?->Address ?? 'N/A' }}</p>
        </div>
        <div class="meta-col">
            <h3>Order Details</h3>
            <p>Date Created: {{ \Illuminate\Support\Carbon::parse($purchaseOrder->PurchaseDate)->format('F j, Y') }}</p>
            <p>Expected Delivery: {{ $purchaseOrder->ExpectedDeliveryDate ? \Illuminate\Support\Carbon::parse($purchaseOrder->ExpectedDeliveryDate)->format('F j, Y') : 'Not set' }}</p>
            <p>Status: {{ \App\Models\PurchaseOrder::STATUS_LABELS[$purchaseOrder->Status] ?? ucfirst($purchaseOrder->Status) }}</p>
        </div>
    </div>

    @if($purchaseOrder->Notes)
        <p><strong>Notes:</strong> {{ $purchaseOrder->Notes }}</p>
    @endif

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th class="num">Quantity</th>
                <th class="num">Unit Cost</th>
                <th class="num">Total Cost</th>
            </tr>
        </thead>
        <tbody>
            @php $grandTotal = 0; @endphp
            @foreach($purchaseOrder->items as $item)
                @php $lineTotal = $item->Quantity * $item->CostPriceAtOrder; $grandTotal += $lineTotal; @endphp
                <tr>
                    <td>{{ $item->product?->ProductName ?? 'N/A' }}</td>
                    <td class="num">{{ $item->Quantity }}</td>
                    <td class="num">₱{{ number_format($item->CostPriceAtOrder, 2) }}</td>
                    <td class="num">₱{{ number_format($lineTotal, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="3" style="text-align:right;">Grand Total</td>
                <td class="num">₱{{ number_format($grandTotal, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="signature-area">
        <div class="signature-block">
            <div class="signature-line">
                {{ $purchaseOrder->createdByUser?->full_name ?? 'N/A' }}
                <br><small>Prepared By</small>
            </div>
        </div>
        <div class="signature-block">
            <div class="signature-line">
                {{ $purchaseOrder->approvedByUser?->full_name ?? 'Pending Approval' }}
                <br><small>Approved By</small>
            </div>
        </div>
    </div>
</body>
</html>
