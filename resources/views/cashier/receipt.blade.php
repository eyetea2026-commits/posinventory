<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt - {{ $receiptNumber }}</title>
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
        .items { border-bottom: 1px dashed #ccc; padding-bottom: 10px; margin-bottom: 10px; }
        .item { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .totals { margin-top: 10px; }
        .total-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
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
    <button class="print-btn no-print" onclick="window.print()">
        <i class="fas fa-print"></i> Print Receipt
    </button>

    <div class="receipt">
        <div class="header">
            <h1>CCTV Express</h1>
            <p>Your Trusted Security Partner</p>
            <p>{{ $date }}</p>
            <p>Receipt #: {{ $receiptNumber }}</p>
            <p>Cashier: {{ $cashierName }}</p>
            @if($customerName)
            <p>Customer: {{ $customerName }}</p>
            @endif
        </div>

        <div class="items">
            @foreach($items as $item)
            <div class="item">
                <span>{{ $item['name'] }} x{{ $item['qty'] }}</span>
                <span>₱{{ number_format($item['price'] * $item['qty'], 2) }}</span>
            </div>
            @endforeach
        </div>

        <div class="totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>₱{{ number_format($subtotal, 2) }}</span>
            </div>
            <div class="total-row">
                <span>Discount ({{ $discountRate }}%):</span>
                <span>-₱{{ number_format($discountAmount, 2) }}</span>
            </div>
            <div class="total-row">
                <span>VAT (12%):</span>
                <span>₱{{ number_format($vatAmount, 2) }}</span>
            </div>
            <div class="total-row grand-total">
                <span>TOTAL:</span>
                <span>₱{{ number_format($total, 2) }}</span>
            </div>
        </div>

        <div class="footer">
            <p>Payment Method: {{ ucfirst($paymentMethod) }}</p>
            @if($paymentAmount > 0)
            <div class="total-row">
                <span>Cash Tendered:</span>
                <span>₱{{ number_format($paymentAmount, 2) }}</span>
            </div>
            <div class="total-row">
                <span>Change:</span>
                <span>₱{{ number_format($change, 2) }}</span>
            </div>
            @endif
            <p>Thank you for your purchase!</p>
            <p>Please come again</p>
        </div>
    </div>

    <script>
        // Auto-print after 1 second
        setTimeout(() => {
            // Uncomment below to auto-print
            // window.print();
        }, 1000);
    </script>
</body>
</html>