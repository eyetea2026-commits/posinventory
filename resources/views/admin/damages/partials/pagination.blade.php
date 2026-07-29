@if($damagedProducts->hasPages())
    <div class="pagination">
        @if($damagedProducts->onFirstPage())
            <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
        @else
            <a href="{{ $damagedProducts->previousPageUrl() }}" class="pagination-link" data-page="{{ $damagedProducts->currentPage() - 1 }}"><i class="fas fa-chevron-left"></i></a>
        @endif

        @foreach($damagedProducts->getUrlRange(1, $damagedProducts->lastPage()) as $page => $url)
            <a href="{{ $url }}" class="pagination-link {{ $page == $damagedProducts->currentPage() ? 'active' : '' }}" data-page="{{ $page }}">{{ $page }}</a>
        @endforeach

        @if($damagedProducts->hasMorePages())
            <a href="{{ $damagedProducts->nextPageUrl() }}" class="pagination-link" data-page="{{ $damagedProducts->currentPage() + 1 }}"><i class="fas fa-chevron-right"></i></a>
        @else
            <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
        @endif
    </div>
@endif
