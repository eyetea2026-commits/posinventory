@forelse($transactions as $transaction)
    <tr>
        <td><span class="receipt-number">RCT-{{ str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT) }}</span></td>
        <td>{{ \Carbon\Carbon::parse($transaction->SalesTransactionDate)->format('M d, Y h:i A') }}</td>
        <td>{{ $transaction->CustomerName ?? 'Walk-in Customer' }}</td>
        <td>{{ $transaction->items->sum('Quantity') ?? 0 }} items</td>
        <td class="amount">₱{{ number_format($transaction->billing?->BillingAmount ?? 0, 2) }}</td>
        <td>
            <a href="{{ route('cashier.receipt', 'RCT-' . str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT)) }}" class="btn btn-primary btn-sm" target="_blank">
                <i class="fas fa-print"></i> Print
            </a>
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
