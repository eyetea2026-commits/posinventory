@forelse($transactions as $transaction)
    @php
        $isOwnSale = $currentStaffId !== null && (int) $transaction->StaffID === (int) $currentStaffId;
        $cashierName = $transaction->staff?->user?->full_name
            ?? trim(($transaction->staff?->FirstName ?? '').' '.($transaction->staff?->LastName ?? ''))
            ?: 'Unknown';
    @endphp
    <tr>
        <td><span class="receipt-number">RCT-{{ str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT) }}</span></td>
        <td>{{ \Carbon\Carbon::parse($transaction->SalesTransactionDate)->format('M d, Y h:i A') }}</td>
        <td>{{ $transaction->CustomerName ?? 'Walk-in Customer' }}</td>
        <td>
            {{ $cashierName }}
            @if($isOwnSale)
                <span class="cashier-you">You</span>
            @endif
        </td>
        <td>{{ $transaction->items->sum('Quantity') ?? 0 }} items</td>
        <td class="amount">₱{{ number_format($transaction->billing?->BillingAmount ?? 0, 2) }}</td>
        <td>
            {{-- Reprinting is only offered on the cashier's own sales;
                 CashierAuthController::viewReceipt() enforces the same rule
                 server-side. --}}
            @if($isOwnSale)
                @php($receiptNo = 'RCT-' . str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT))
                <a href="{{ route('cashier.receipt', $receiptNo) }}" class="btn btn-primary btn-sm" target="_blank"
                   @if(\App\Services\ThermalReceiptPrinter::isEnabled()) onclick="return reprintToThermal(event, '{{ $receiptNo }}', this.href);" @endif>
                    <i class="fas fa-print"></i> Print
                </a>
            @else
                <span class="view-only-note">View only</span>
            @endif
        </td>
    </tr>
@empty
    <tr>
        <td colspan="7">
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <p>No transactions found for today.</p>
            </div>
        </td>
    </tr>
@endforelse
