@if($brands->hasPages())
    <div class="pagination">
        @if($brands->onFirstPage())
            <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
        @else
            <a href="{{ $brands->previousPageUrl() }}" class="pagination-link" data-page="{{ $brands->currentPage() - 1 }}">
                <i class="fas fa-chevron-left"></i>
            </a>
        @endif

        @foreach($brands->getUrlRange(1, $brands->lastPage()) as $page => $url)
            <a
                href="{{ $url }}"
                class="pagination-link {{ $page == $brands->currentPage() ? 'active' : '' }}"
                data-page="{{ $page }}"
            >
                {{ $page }}
            </a>
        @endforeach

        @if($brands->hasMorePages())
            <a href="{{ $brands->nextPageUrl() }}" class="pagination-link" data-page="{{ $brands->currentPage() + 1 }}">
                <i class="fas fa-chevron-right"></i>
            </a>
        @else
            <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
        @endif
    </div>
@endif
