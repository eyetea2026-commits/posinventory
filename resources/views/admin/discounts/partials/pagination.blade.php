@if($discounts->hasPages())
    <div class="pagination">
        @if($discounts->onFirstPage())
            <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
        @else
            <a href="{{ $discounts->previousPageUrl() }}" class="pagination-link" data-page="{{ $discounts->currentPage() - 1 }}"><i class="fas fa-chevron-left"></i></a>
        @endif

        @foreach($discounts->getUrlRange(1, $discounts->lastPage()) as $page => $url)
            <a href="{{ $url }}" class="pagination-link {{ $page == $discounts->currentPage() ? 'active' : '' }}" data-page="{{ $page }}">{{ $page }}</a>
        @endforeach

        @if($discounts->hasMorePages())
            <a href="{{ $discounts->nextPageUrl() }}" class="pagination-link" data-page="{{ $discounts->currentPage() + 1 }}"><i class="fas fa-chevron-right"></i></a>
        @else
            <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
        @endif
    </div>
@endif
