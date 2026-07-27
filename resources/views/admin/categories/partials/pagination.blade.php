@if($categories->hasPages())
    <div class="pagination">
        @if($categories->onFirstPage())
            <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
        @else
            <a href="{{ $categories->previousPageUrl() }}" class="pagination-link" data-page="{{ $categories->currentPage() - 1 }}">
                <i class="fas fa-chevron-left"></i>
            </a>
        @endif

        @foreach($categories->getUrlRange(1, $categories->lastPage()) as $page => $url)
            <a
                href="{{ $url }}"
                class="pagination-link {{ $page == $categories->currentPage() ? 'active' : '' }}"
                data-page="{{ $page }}"
            >
                {{ $page }}
            </a>
        @endforeach

        @if($categories->hasMorePages())
            <a href="{{ $categories->nextPageUrl() }}" class="pagination-link" data-page="{{ $categories->currentPage() + 1 }}">
                <i class="fas fa-chevron-right"></i>
            </a>
        @else
            <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
        @endif
    </div>
@endif
