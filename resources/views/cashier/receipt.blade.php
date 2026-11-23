<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt - {{ $receiptNumber }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=2">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
    @include('cashier.partials.receipt-styles')
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">
        <i class="fas fa-print"></i> Print Receipt
    </button>
    <script>
        if (window.self !== window.top) {
            document.querySelector('.print-btn').remove();
        }
    </script>

    @isset($printLines)
        <div class="print-text">
            @foreach($printLines as $line)
                @if(! empty($line['qr']))
                    <div class="receipt-qr pl-qr" data-qr="{{ $line['qr'] }}"></div>
                @else
                    <div class="pl{{ $line['bold'] ? ' pl-bold' : '' }}{{ $line['tall'] ? ' pl-tall' : '' }}">{{ $line['text'] }}</div>
                @endif
            @endforeach
            <pre class="pl-tail">{{ $printTail }}</pre>
        </div>
    @endisset

    <div class="receipt">
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
                <span>{{ $customerName === 'Walk-in Customer' ? 'N/A' : $customerName }}</span>
            </div>
        @endif

        <hr class="rule">

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
                        <td class="col-qty">{{ $item['qty_label'] ?? $item['qty'] }}</td>
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

        @if(! empty($customDiscountPercent))
            <div class="total-row">
                <span>Discount ({{ rtrim(rtrim(number_format($customDiscountPercent, 2, '.', ''), '0'), '.') }}%):</span>
                <span>-₱{{ number_format($customDiscountAmount, 2) }}</span>
            </div>
            <hr class="rule">
        @endif

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
                <span>{{ \Illuminate\Support\Carbon::parse($paymentDate)->format('m/d/Y') }}</span>
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

        <div class="receipt-qr" data-qr="{{ $receiptNumber }}"></div>
    </div>

    <script>
        document.querySelectorAll('[data-qr]').forEach(function (el) {
            if (typeof qrcode !== 'function') return;
            const qr = qrcode(0, 'M');
            qr.addData(el.dataset.qr);
            qr.make();
            const img = document.createElement('img');
            img.src = qr.createDataURL(8, 2);
            img.alt = 'QR code: ' + el.dataset.qr;
            el.appendChild(img);
        });
    </script>
</body>
</html>
