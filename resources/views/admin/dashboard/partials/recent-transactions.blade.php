<div class="table-container table-scroll">
    <table class="table">
        <thead>
            <tr>
                <th>Receipt No.</th>
                <th>Cashier</th>
                <th>
                    <a href="{{ request()->fullUrlWithQuery(['txn_sort' => $txnSort === 'amount_desc' ? 'amount_asc' : 'amount_desc', 'txn_page' => 1]) }}">
                        Amount <i class="fas fa-sort"></i>
                    </a>
                </th>
                <th>Payment Method</th>
                <th>Status</th>
                <th>
                    <a href="{{ request()->fullUrlWithQuery(['txn_sort' => $txnSort === 'date_asc' ? 'date_desc' : 'date_asc', 'txn_page' => 1]) }}">
                        Date &amp; Time <i class="fas fa-sort"></i>
                    </a>
                </th>
            </tr>
        </thead>
        <tbody>
            @forelse($recentTransactions as $transaction)
                <tr>
                    <td>RCT-{{ str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $transaction->staff?->user?->full_name ?? 'N/A' }}</td>
                    <td class="text-success">₱{{ number_format($transaction->billing?->BillingAmount ?? 0, 2) }}</td>
                    <td>{{ strtoupper($transaction->billing?->payment?->PaymentMethod ?? 'N/A') }}</td>
                    <td><span class="badge badge-success">Completed</span></td>
                    <td>{{ \Illuminate\Support\Carbon::parse($transaction->SalesTransactionDate)->format('M d, Y h:i A') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted">No transactions match your search.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@if($recentTransactions->hasPages())
    <div class="pagination">
        @if($recentTransactions->onFirstPage())
            <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
        @else
            <a href="{{ $recentTransactions->previousPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
        @endif

        @foreach($recentTransactions->getUrlRange(1, $recentTransactions->lastPage()) as $page => $url)
            <a href="{{ $url }}" class="pagination-link {{ $page == $recentTransactions->currentPage() ? 'active' : '' }}">{{ $page }}</a>
        @endforeach

        @if($recentTransactions->hasMorePages())
            <a href="{{ $recentTransactions->nextPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
        @else
            <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
        @endif
    </div>
@endif
