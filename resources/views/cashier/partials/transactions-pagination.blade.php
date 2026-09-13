@if($transactions->hasPages())
    <div class="pagination">
        @if($transactions->onFirstPage())
            <span><i class="fas fa-chevron-left"></i></span>
        @else
            <a href="{{ $transactions->previousPageUrl() }}"><i class="fas fa-chevron-left"></i></a>
        @endif

        @foreach($transactions->getUrlRange(1, min(5, $transactions->lastPage())) as $page => $url)
            <a href="{{ $url }}" class="{{ $page == $transactions->currentPage() ? 'active' : '' }}">{{ $page }}</a>
        @endforeach

        @if($transactions->hasMorePages())
            <a href="{{ $transactions->nextPageUrl() }}"><i class="fas fa-chevron-right"></i></a>
        @else
            <span><i class="fas fa-chevron-right"></i></span>
        @endif
    </div>
@endif
