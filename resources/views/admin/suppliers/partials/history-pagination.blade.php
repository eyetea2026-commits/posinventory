@if($history->hasPages())
    <div class="pagination">
        @if($history->onFirstPage())
            <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
        @else
            <a href="{{ $history->previousPageUrl() }}" class="pagination-link" data-page="{{ $history->currentPage() - 1 }}">
                <i class="fas fa-chevron-left"></i>
            </a>
        @endif

        @foreach($history->getUrlRange(1, $history->lastPage()) as $page => $url)
            <a
                href="{{ $url }}"
                class="pagination-link {{ $page == $history->currentPage() ? 'active' : '' }}"
                data-page="{{ $page }}"
            >
                {{ $page }}
            </a>
        @endforeach

        @if($history->hasMorePages())
            <a href="{{ $history->nextPageUrl() }}" class="pagination-link" data-page="{{ $history->currentPage() + 1 }}">
                <i class="fas fa-chevron-right"></i>
            </a>
        @else
            <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
        @endif
    </div>
@endif
