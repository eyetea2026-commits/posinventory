<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Order {{ $purchaseOrder->PONumber }}</title>
    <style>
        @page { size: A4; margin: 18mm 16mm; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 13px; color: #1a1a1a; max-width: 800px; margin: 0 auto; padding: 30px; }
        .company-header { text-align: center; border-bottom: 2px solid #1a1a1a; padding-bottom: 14px; margin-bottom: 6px; }
        .company-header h1 { margin: 0; font-size: 22px; letter-spacing: 0.04em; }
        .company-header p { margin: 4px 0 0; color: #555; font-size: 12px; }
        .doc-title { text-align: center; margin: 14px 0 4px; }
        .doc-title h2 { margin: 0; font-size: 18px; text-transform: uppercase; letter-spacing: 0.08em; }
        .doc-title .reserved-note { margin: 4px 0 0; color: #999; font-size: 10px; font-style: italic; }
        .doc-title p.po-number { margin: 6px 0 0; color: #333; }
        .section-heading {
            font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; color: #1a1a1a;
            font-weight: 700; margin: 22px 0 8px; padding-bottom: 5px; border-bottom: 1px solid #1a1a1a;
        }
        .info-grid { display: flex; justify-content: space-between; gap: 24px; }
        .info-col { flex: 1; }
        .info-col p { margin: 3px 0; }
        .info-col p strong { display: inline-block; min-width: 110px; color: #555; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 7px 9px; text-align: left; }
        th { background: #f0f0f0; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; }
        td.num, th.num { text-align: right; }
        .summary-box { margin-top: 8px; width: 260px; margin-left: auto; }
        .summary-box .summary-row { display: flex; justify-content: space-between; padding: 4px 0; }
        .summary-box .summary-row.grand { border-top: 1px solid #1a1a1a; margin-top: 4px; padding-top: 8px; font-weight: bold; font-size: 14px; }
        .remarks-text { margin: 4px 0 0; color: #333; }
        .signature-area { display: flex; justify-content: space-between; margin-top: 60px; gap: 20px; }
        .signature-block { flex: 1; text-align: center; }
        .signature-line { border-top: 1px solid #1a1a1a; padding-top: 6px; margin-top: 50px; }
        .signature-line small { color: #666; }
        .doc-footer { text-align: center; margin-top: 50px; padding-top: 10px; border-top: 1px solid #ccc; color: #888; font-size: 10.5px; }
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
        <p class="reserved-note">(Reserved for Future Use)</p>
        <p class="po-number">PO Number: <strong>{{ $purchaseOrder->PONumber }}</strong></p>
    </div>

    <div class="section-heading">Purchase Order Information</div>
    <div class="info-grid">
        <div class="info-col">
            <p><strong>Date Created:</strong> {{ \Illuminate\Support\Carbon::parse($purchaseOrder->PurchaseDate)->format('F j, Y') }}</p>
            <p><strong>Expected Delivery:</strong> {{ $purchaseOrder->ExpectedDeliveryDate ? \Illuminate\Support\Carbon::parse($purchaseOrder->ExpectedDeliveryDate)->format('F j, Y') : 'Not set' }}</p>
        </div>
    </div>

    <div class="section-heading">Supplier Information</div>
    <div class="info-grid">
        <div class="info-col">
            <p><strong>Supplier:</strong> {{ $purchaseOrder->supplier?->SupplierName ?? 'N/A' }}</p>
            <p><strong>Contact Person:</strong> {{ $purchaseOrder->supplier?->ContactPerson ?? 'N/A' }}</p>
            <p><strong>Contact Number:</strong> {{ $purchaseOrder->supplier?->ContactNumber ?? 'N/A' }}</p>
            <p><strong>Email:</strong> {{ $purchaseOrder->supplier?->Email ?? 'N/A' }}</p>
            <p><strong>Address:</strong> {{ $purchaseOrder->supplier?->Address ?? 'N/A' }}</p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th class="num">Qty</th>
                <th class="num">Unit Price</th>
                <th class="num">Amount</th>
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
        </tbody>
    </table>

    <div class="section-heading">Order Summary</div>
    <div class="summary-box">
        <div class="summary-row grand">
            <span>Total Amount</span>
            <span>₱{{ number_format($grandTotal, 2) }}</span>
        </div>
    </div>

    <div class="section-heading">Remarks</div>
    <p class="remarks-text">{{ $purchaseOrder->Notes ?: 'None' }}</p>

    <div class="signature-area">
        <div class="signature-block">
            <div class="signature-line">
                {{ $purchaseOrder->createdByUser?->full_name ?? 'N/A' }}
                <br><small>Prepared By</small>
            </div>
        </div>
        <div class="signature-block">
            <div class="signature-line">
                &nbsp;
                <br><small>Checked By</small>
            </div>
        </div>
        <div class="signature-block">
            <div class="signature-line">
                {{ $purchaseOrder->approvedByUser?->full_name ?? 'Pending Approval' }}
                <br><small>Approved By</small>
            </div>
        </div>
    </div>

    <div class="doc-footer">
        Generated by CCTV Express POS &amp; Inventory System
    </div>
</body>
</html>
