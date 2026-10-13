@forelse($transactions as $transaction)
    @php
        $isOwnSale = $currentStaffId !== null && (int) $transaction->StaffID === (int) $currentStaffId;
        $cashierName = $transaction->staff?->user?->full_name
            ?? trim(($transaction->staff?->FirstName ?? '').' '.($transaction->staff?->LastName ?? ''))
            ?: 'Unknown';
    @endphp
    <tr>
        <td><span class="receipt-number">RCT-{{ str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT) }}</span></td>
        <td>{{ \Carbon\Carbon::parse($transaction->SalesTransactionDate)->format('m/d/Y h:i A') }}</td>
        {{-- No name given is saved as "N/A"; older sales saved
             "Walk-in Customer", which shows as N/A too. --}}
        <td>{{ in_array($transaction->CustomerName, [null, '', 'Walk-in Customer'], true) ? 'N/A' : $transaction->CustomerName }}</td>
        <td>
            {{ $cashierName }}
            @if($isOwnSale)
                <span class="cashier-you">You</span>
            @endif
        </td>
        <td>{{ $transaction->items->sum('Quantity') ?? 0 }} items</td>
        <td>
            {{-- Reprinting is only offered on the cashier's own sales;
                 CashierAuthController::viewReceipt() enforces the same rule
                 server-side. --}}
            @if($isOwnSale)
                @php($receiptNo = 'RCT-' . str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT))
                <a href="{{ route('cashier.receipt', $receiptNo) }}" class="btn btn-primary btn-sm" target="_blank"
                   onclick="return confirmPrintReceipt(event, '{{ $receiptNo }}', this.href);">
                    <i class="fas fa-print"></i> Print
                </a>
            @else
                <span class="view-only-note">View only</span>
            @endif
        </td>
    </tr>
@empty
    <tr>
        <td colspan="6">
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <p>No transactions found for today.</p>
            </div>
        </td>
    </tr>
@endforelse
