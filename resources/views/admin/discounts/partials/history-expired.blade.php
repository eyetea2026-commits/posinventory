{{-- Expired promos — past their EndDate, never deleted, purely a
     read-only audit list. --}}
<div style="overflow-x:auto;">
    <table class="table">
        <thead>
            <tr>
                <th>Promo Name</th>
                <th>Promo Code</th>
                <th>Value</th>
                <th>Products (current assignment)</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($expired as $discount)
                <tr>
                    <td>{{ $discount->Name ?? '—' }}</td>
                    <td><code>{{ $discount->PromoCode ?? '—' }}</code></td>
                    <td>{{ $discount->DiscountType === 'fixed' ? '₱' . number_format($discount->DiscountRate, 2) : number_format($discount->DiscountRate, 2) . '%' }}</td>
                    <td>{{ $discount->products->isEmpty() ? '—' : $discount->products->pluck('ProductName')->implode(', ') }}</td>
                    <td>{{ $discount->StartDate?->format('M d, Y') ?? '—' }}</td>
                    <td>{{ $discount->EndDate?->format('M d, Y') ?? '—' }}</td>
                    <td><span class="badge badge-secondary">Expired</span></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state"><p class="empty-text">No expired promos yet.</p></div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if($expired->hasPages())
    <div class="pagination">
        @if($expired->onFirstPage())
            <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
        @else
            <a href="#" class="pagination-link" data-history-page="expired" data-page="{{ $expired->currentPage() - 1 }}"><i class="fas fa-chevron-left"></i></a>
        @endif
        @foreach($expired->getUrlRange(1, $expired->lastPage()) as $page => $url)
            <a href="#" class="pagination-link {{ $page == $expired->currentPage() ? 'active' : '' }}" data-history-page="expired" data-page="{{ $page }}">{{ $page }}</a>
        @endforeach
        @if($expired->hasMorePages())
            <a href="#" class="pagination-link" data-history-page="expired" data-page="{{ $expired->currentPage() + 1 }}"><i class="fas fa-chevron-right"></i></a>
        @else
            <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
        @endif
    </div>
@endif
