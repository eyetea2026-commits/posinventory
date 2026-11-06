<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Refund Receipt - {{ $receiptNumber }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=2">
    @include('cashier.partials.receipt-styles')
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">
        <i class="fas fa-print"></i> Print Receipt
    </button>
    <script>
        // Printed from a hidden frame on the Refund Requests page, which
        // has no use for a button inside the receipt.
        if (window.self !== window.top) {
            document.querySelector('.print-btn').remove();
        }
    </script>

    {{-- Printed copy: the same 32-column lines as the thermal printout
         (ThermalReceiptPrinter::refundLines()), like the sales receipt. --}}
    <div class="print-text">
        @foreach($printLines as $line)
            <div class="pl{{ $line['bold'] ? ' pl-bold' : '' }}{{ $line['tall'] ? ' pl-tall' : '' }}">{{ $line['text'] }}</div>
        @endforeach
        <pre class="pl-tail">{{ $printTail }}</pre>
    </div>

    <div class="receipt">
        <div class="center">
            <p class="store-name">CCTV Express</p>
            <p class="store-line">Your Trusted Security Partner</p>
            @if(config('app.business_address'))
                <p class="store-line">{{ config('app.business_address') }}</p>
            @endif
        </div>

        <hr class="rule">

        <p class="doc-title center">REFUND RECEIPT</p>

        <div class="meta-row"><span>Refund #:</span><span>{{ $receiptNumber }}</span></div>
        <div class="meta-row"><span>Date:</span><span>{{ $date }}</span></div>
        <div class="meta-row"><span>Cashier:</span><span>{{ $cashierName }}</span></div>
        <div class="meta-row"><span>Original Receipt:</span><span>{{ $originalReceipt }}</span></div>
        <div class="meta-row"><span>Customer:</span><span>{{ $customerName }}</span></div>

        <hr class="rule">

        <table class="items-table">
            <thead>
                <tr>
                    <th class="col-desc">Returned Item</th>
                    <th class="col-qty">Quantity</th>
                    <th class="col-amt">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        <td class="col-desc">{{ $item['name'] }}<br><small>Reason: {{ $item['reason'] }}</small></td>
                        <td class="col-qty">{{ $item['qty_label'] }}</td>
                        <td class="col-amt">{{ number_format($item['amount'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <hr class="rule">

        <div class="total-row grand-total">
            <span>REFUND:</span>
            <span>₱{{ number_format($refundAmount, 2) }}</span>
        </div>

        <hr class="rule">

        <div class="total-row">
            <span>Refund Method:</span>
            <span>{{ match ($refundMethod) { 'gcash' => 'GCash', 'bank' => 'Bank Transfer', 'cheque' => 'Cheque', default => 'Cash' } }}</span>
        </div>
        @if($referenceNumber)
            <div class="total-row"><span>Reference No.:</span><span>{{ $referenceNumber }}</span></div>
        @endif

        <hr class="rule">

        <div class="footer center">
            <p>Refund processed.</p>
            <p>Thank you!</p>
        </div>
    </div>
</body>
</html>
