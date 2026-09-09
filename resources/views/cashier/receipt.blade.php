<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt - {{ $receiptNumber }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            max-width: 300px;
            margin: 0 auto;
            padding: 20px 12px;
            font-size: 12px;
            color: #000;
            background: #f1f5f9;
        }
        .receipt { background: #fff; border: 1px solid #ccc; padding: 16px 14px; }

        .center { text-align: center; }
        .bold { font-weight: bold; }
        .rule { border: none; border-top: 1px dashed #000; margin: 8px 0; }

        .store-name { margin: 0; font-size: 16px; font-weight: bold; letter-spacing: 0.5px; }
        .store-line { margin: 2px 0 0; font-size: 11px; }

        .doc-title { margin: 10px 0 8px; font-size: 13px; font-weight: bold; letter-spacing: 2px; }

        .meta-row { display: flex; justify-content: space-between; margin: 2px 0; }

        .items-header { display: flex; justify-content: space-between; font-weight: bold; margin-bottom: 4px; }
        .item-row { margin-bottom: 6px; }
        .item-name { word-break: break-word; }
        .item-line2 { display: flex; justify-content: space-between; color: #333; }

        .total-row { display: flex; justify-content: space-between; margin: 2px 0; }
        .grand-total { font-weight: bold; font-size: 14px; }

        .footer p { margin: 3px 0; }

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

        @media print {
            body { margin: 0; padding: 0; max-width: 100%; background: #fff; }
            .receipt { border: none; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">
        <i class="fas fa-print"></i> Print Receipt
    </button>

    <div class="receipt">
        {{-- Header: business identity, matching the reference's centered
             name/address block above the document title. --}}
        <div class="center">
            <p class="store-name">CCTV Express</p>
            <p class="store-line">Your Trusted Security Partner</p>
        </div>

        <hr class="rule">

        <p class="doc-title center">SALES RECEIPT</p>

        <div class="meta-row">
            <span>Receipt #:</span>
            <span>{{ $receiptNumber }}</span>
        </div>
        <div class="meta-row">
            <span>Date/Time:</span>
            <span>{{ $date }}</span>
        </div>
        <div class="meta-row">
            <span>Cashier:</span>
            <span>{{ $cashierName }}</span>
        </div>
        @if($customerName)
            <div class="meta-row">
                <span>Customer:</span>
                <span>{{ $customerName }}</span>
            </div>
        @endif

        <hr class="rule">

        {{-- Item table: product name on its own line (so a long name wraps
             instead of overlapping the amount column), quantity/unit price/
             line amount on the line below it — same structure the
             reference receipt uses per line item. --}}
        <div class="items-header">
            <span>Description</span>
            <span>Amount</span>
        </div>
        @foreach($items as $item)
            <div class="item-row">
                <div class="item-name">{{ $item['name'] }}</div>
                <div class="item-line2">
                    <span>{{ $item['qty'] }} x ₱{{ number_format($item['price'], 2) }}</span>
                    <span>₱{{ number_format($item['price'] * $item['qty'], 2) }}</span>
                </div>
            </div>
        @endforeach

        <hr class="rule">

        @if($hasDiscount)
            @if($promoCode)
                <div class="total-row">
                    <span>Promo Code:</span>
                    <span>{{ $promoCode }}</span>
                </div>
            @endif
            @if($promoProductName)
                <div class="total-row">
                    <span>Applied To:</span>
                    <span>{{ $promoProductName }}</span>
                </div>
            @endif
            <div class="total-row">
                <span>Discount ({{ $discountRate }}%):</span>
                <span>-₱{{ number_format($discountAmount, 2) }}</span>
            </div>
            <hr class="rule">
        @endif

        {{-- VAT breakdown, styled after the reference's BIR-style summary
             block. Prices are VAT-inclusive throughout this system, so
             "Total" IS the amount charged — VATable Sales/VAT Amount are
             only the breakdown of that same figure, not additional charges
             (see CashierAuthController::processSale()). Zero Rated/VAT
             Exempt are always ₱0.00 here since this system has no such
             sale category — shown for the same standard-receipt shape as
             the reference, not fabricated data. --}}
        <div class="total-row bold">
            <span>Total (incl. VAT):</span>
            <span>₱{{ number_format($total, 2) }}</span>
        </div>
        <div class="total-row">
            <span>Zero Rated Sale:</span>
            <span>₱0.00</span>
        </div>
        <div class="total-row">
            <span>VAT Exempt Sale:</span>
            <span>₱0.00</span>
        </div>
        <div class="total-row">
            <span>VATable Sales:</span>
            <span>₱{{ number_format($total - $vatAmount, 2) }}</span>
        </div>
        <div class="total-row">
            <span>VAT Amount:</span>
            <span>₱{{ number_format($vatAmount, 2) }}</span>
        </div>

        <hr class="rule">

        <div class="total-row grand-total">
            <span>TOTAL:</span>
            <span>₱{{ number_format($total, 2) }}</span>
        </div>

        <hr class="rule">

        <div class="total-row">
            <span>Payment Method:</span>
            <span>{{ ucfirst($paymentMethod) }}</span>
        </div>
        @if($referenceNumber ?? null)
            <div class="total-row">
                <span>{{ $paymentMethod === 'cheque' ? 'Cheque No.' : 'Reference No.' }}:</span>
                <span>{{ $referenceNumber }}</span>
            </div>
        @endif
        @if($bankName ?? null)
            <div class="total-row">
                <span>Bank:</span>
                <span>{{ $bankName }}</span>
            </div>
        @endif
        @if($accountName ?? null)
            <div class="total-row">
                <span>{{ $paymentMethod === 'cheque' ? 'Issuer' : 'Sender' }}:</span>
                <span>{{ $accountName }}</span>
            </div>
        @endif
        @if($paymentDate ?? null)
            <div class="total-row">
                <span>{{ $paymentMethod === 'cheque' ? 'Cheque Date' : 'Transfer Date' }}:</span>
                <span>{{ \Illuminate\Support\Carbon::parse($paymentDate)->format('M d, Y') }}</span>
            </div>
        @endif
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

        <hr class="rule">

        <div class="footer center">
            <p>Thank you for your purchase!</p>
            <p>Please come again</p>
        </div>
    </div>
</body>
</html>
