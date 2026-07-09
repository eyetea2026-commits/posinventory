@if($products->hasPages())
    <div class="pagination">
        @if($products->onFirstPage())
            <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
        @else
            <a href="{{ $products->previousPageUrl() }}" class="pagination-link" data-page="{{ $products->currentPage() - 1 }}">
                <i class="fas fa-chevron-left"></i>
            </a>
        @endif

        @foreach($products->getUrlRange(1, $products->lastPage()) as $page => $url)
            <a
                href="{{ $url }}"
                class="pagination-link {{ $page == $products->currentPage() ? 'active' : '' }}"
                data-page="{{ $page }}"
            >
                {{ $page }}
            </a>
        @endforeach

        @if($products->hasMorePages())
            <a href="{{ $products->nextPageUrl() }}" class="pagination-link" data-page="{{ $products->currentPage() + 1 }}">
                <i class="fas fa-chevron-right"></i>
            </a>
        @else
            <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
        @endif
    </div>
@endif
