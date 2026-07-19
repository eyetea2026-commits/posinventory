<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Refund Receipt - {{ $receiptNumber }}</title>
    <style>
        @media print {
            body { margin: 0; padding: 20px; }
            .no-print { display: none; }
        }
        body {
            font-family: 'Courier New', monospace;
            max-width: 300px;
            margin: 0 auto;
            padding: 20px;
            font-size: 12px;
        }
        .receipt { border: 1px solid #ccc; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; }
        .header p { margin: 5px 0; color: #666; }
        .row { display: flex; justify-content: space-between; margin-bottom: 6px; }
        .section { border-bottom: 1px dashed #ccc; padding-bottom: 10px; margin-bottom: 10px; }
        .grand-total { font-weight: bold; font-size: 14px; border-top: 1px solid #000; padding-top: 5px; }
        .footer { text-align: center; margin-top: 20px; color: #666; }
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">Print Receipt</button>

    <div class="receipt">
        <div class="header">
            <h1>CCTV Express</h1>
            <p>Refund Receipt</p>
            <p>Receipt #: {{ $receiptNumber }}</p>
            <p>{{ \Carbon\Carbon::parse($salesReturn->RefundDate)->format('M d, Y') }}</p>
            <p>Cashier: {{ $salesReturn->processedByUser?->name ?? 'N/A' }}</p>
        </div>

        <div class="section">
            <div class="row"><span>Original Transaction:</span><span>RCT-{{ str_pad($salesReturn->SalesTransactionID, 6, '0', STR_PAD_LEFT) }}</span></div>
            <div class="row"><span>Customer:</span><span>{{ $salesReturn->CustomerName ?? $salesReturn->salesTransaction?->CustomerName ?? 'N/A' }}</span></div>
        </div>

        <div class="section">
            <p><strong>Returned Item</strong></p>
            <div class="row"><span>{{ $salesReturn->product?->ProductName ?? 'N/A' }} x{{ $salesReturn->Quantity }}</span></div>
            <div class="row"><span>Reason:</span><span>{{ $salesReturn->Reason }}</span></div>
        </div>

        <div class="section">
            <div class="row grand-total">
                <span>Refund Amount:</span>
                <span>₱{{ number_format($salesReturn->RefundAmount, 2) }}</span>
            </div>
            <div class="row"><span>Refund Method:</span><span>{{ ucfirst($salesReturn->RefundMethod) }}</span></div>
            @if($salesReturn->RefundAccountNumber)
                <div class="row"><span>Reference #:</span><span>{{ $salesReturn->RefundAccountNumber }}</span></div>
            @endif
        </div>

        <div class="footer">
            <p>Thank you for your purchase!</p>
        </div>
    </div>
</body>
</html>
