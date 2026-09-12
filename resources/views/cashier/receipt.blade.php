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

        /* Real <table> (not flex rows) so Description/Quantity/Amount stay
           in fixed, vertically-aligned columns no matter how a long
           product name wraps — each cell holds exactly one kind of value,
           never combined with other text or labels inside a cell. */
        .items-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .items-table th, .items-table td { padding: 3px 2px; vertical-align: top; }
        .items-table thead th { font-weight: bold; border-bottom: 1px dashed #000; padding-bottom: 4px; }
        .items-table .col-desc { text-align: left; width: 52%; word-break: break-word; }
        .items-table .col-qty { text-align: right; width: 18%; white-space: nowrap; }
        .items-table .col-amt { text-align: right; width: 30%; white-space: nowrap; }

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
            @if(config('app.business_address'))
                <p class="store-line">{{ config('app.business_address') }}</p>
            @endif
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
                {{-- Display only — the stored value is still "Walk-in
                     Customer" (unchanged everywhere else: transaction
                     history, reports, database). Only this receipt line
                     shows "N/A" for it. --}}
                <span>{{ $customerName === 'Walk-in Customer' ? 'N/A' : $customerName }}</span>
            </div>
        @endif

        <hr class="rule">

        {{-- Description | Quantity | Amount — each cell holds exactly one
             value (product name only, a bare integer, a bare line-total
             number), never combined with other text, so a long product
             name wrapping to a second line can't push Quantity/Amount out
             of column alignment (a real <table> keeps every row's cells
             lined up regardless of how tall the Description cell gets). --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th class="col-desc">Description</th>
                    <th class="col-qty">Quantity</th>
                    <th class="col-amt">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        <td class="col-desc">{{ $item['name'] }}</td>
                        <td class="col-qty">{{ $item['qty'] }}</td>
                        <td class="col-amt">{{ number_format($item['price'] * $item['qty'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

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
                <span>Amount Received:</span>
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
