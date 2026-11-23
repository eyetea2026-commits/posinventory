<div style="overflow-x:auto;">
    <table class="table">
        <thead>
            <tr>
                <th>Promo Name</th>
                <th>Promo Code</th>
                <th>Products (current assignment)</th>
                <th>Discount Amount</th>
                <th>Date Applied</th>
                <th>Receipt No.</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($used as $sale)
                @php $discount = $sale->discount; @endphp
                <tr>
                    <td>{{ $discount->Name ?? '—' }}</td>
                    <td><code>{{ $sale->PromoCode ?? '—' }}</code></td>
                    <td>{{ $discount && $discount->products->isNotEmpty() ? $discount->products->pluck('ProductName')->implode(', ') : '—' }}</td>
                    <td>₱{{ number_format($sale->DiscountAmount, 2) }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($sale->SalesTransactionDate)->format('m/d/Y') }}</td>
                    <td>RCT-{{ str_pad($sale->SalesTransactionID, 6, '0', STR_PAD_LEFT) }}</td>
                    <td><span class="badge badge-success">Used</span></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state"><p class="empty-text">No promos have been used in a sale yet.</p></div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if($used->hasPages())
    <div class="pagination">
        @if($used->onFirstPage())
            <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
        @else
            <a href="#" class="pagination-link" data-history-page="used" data-page="{{ $used->currentPage() - 1 }}"><i class="fas fa-chevron-left"></i></a>
        @endif
        @foreach($used->getUrlRange(1, $used->lastPage()) as $page => $url)
            <a href="#" class="pagination-link {{ $page == $used->currentPage() ? 'active' : '' }}" data-history-page="used" data-page="{{ $page }}">{{ $page }}</a>
        @endforeach
        @if($used->hasMorePages())
            <a href="#" class="pagination-link" data-history-page="used" data-page="{{ $used->currentPage() + 1 }}"><i class="fas fa-chevron-right"></i></a>
        @else
            <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
        @endif
    </div>
@endif
