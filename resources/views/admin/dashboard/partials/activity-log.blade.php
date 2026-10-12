{{-- Activity Log card table (dashboard → Operational Activity). Rendered by
     ActivityLogController::index() and swapped in over AJAX by the card's
     filters and page links. --}}
@php
    $controller = \App\Http\Controllers\Admin\ActivityLogController::class;
@endphp
<div class="table-container table-scroll">
    <table class="table">
        <thead>
            <tr>
                <th>Date &amp; Time</th>
                <th>User</th>
                <th>Role</th>
                <th>Module</th>
                <th>Action</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                @php
                    $roleName = $log->user?->role?->role_name;
                @endphp
                <tr>
                    <td style="white-space: nowrap;">{{ $log->DateRecorded?->format('m/d/Y h:i A') ?? 'N/A' }}</td>
                    <td>{{ $log->user?->full_name ?? 'System / Unknown' }}</td>
                    <td>
                        @if($roleName === 'admin')
                            <span class="badge badge-primary">Admin</span>
                        @elseif($roleName === 'cashier')
                            <span class="badge badge-info">Cashier</span>
                        @else
                            <span class="badge badge-secondary">—</span>
                        @endif
                    </td>
                    <td><span class="badge badge-secondary">{{ $controller::moduleLabel(\Illuminate\Support\Str::before($log->Action, '.')) }}</span></td>
                    <td>{{ $controller::actionLabel($log->Action) }}</td>
                    <td class="text-muted">{{ $log->Description }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted">{{ $hasFilters ? 'No activity matches your filters.' : 'No activity recorded yet.' }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@if($logs->hasPages())
    @php
        $windowStart = max(1, $logs->currentPage() - 2);
        $windowEnd = min($logs->lastPage(), $logs->currentPage() + 2);
    @endphp
    <div class="pagination">
        @if($logs->onFirstPage())
            <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
        @else
            <a href="{{ $logs->previousPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
        @endif

        @if($windowStart > 1)
            <a href="{{ $logs->url(1) }}" class="pagination-link">1</a>
            @if($windowStart > 2)
                <span class="pagination-link disabled">&hellip;</span>
            @endif
        @endif

        @foreach($logs->getUrlRange($windowStart, $windowEnd) as $page => $url)
            <a href="{{ $url }}" class="pagination-link {{ $page == $logs->currentPage() ? 'active' : '' }}">{{ $page }}</a>
        @endforeach

        @if($windowEnd < $logs->lastPage())
            @if($windowEnd < $logs->lastPage() - 1)
                <span class="pagination-link disabled">&hellip;</span>
            @endif
            <a href="{{ $logs->url($logs->lastPage()) }}" class="pagination-link">{{ $logs->lastPage() }}</a>
        @endif

        @if($logs->hasMorePages())
            <a href="{{ $logs->nextPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
        @else
            <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
        @endif
    </div>
@endif
