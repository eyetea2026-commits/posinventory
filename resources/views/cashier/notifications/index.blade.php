@extends('cashier.layout')

@section('title', 'Notifications - CCTV Express')

@section('content')
<style>
    .content-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
    .content-header h1 { margin: 0; }
    .card { background: #1a1d2d; border-radius: 12px; padding: 20px; }
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #2d3748; }
    .table th { color: #94a3b8; font-weight: 500; }
    .table tbody tr:hover { background: #2d3748; }
    .table tbody tr.unread { font-weight: 600; }
    .table tbody tr.read { opacity: 0.7; }
    .unread-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; }
    .dot-success { background: #10b981; }
    .dot-danger { background: #ef4444; }
    .dot-warning { background: #f59e0b; }
    .dot-info { background: #60a5fa; }
    .btn-mark-all { padding: 10px 20px; background: #2d3748; border: 1px solid #4a5568; color: #e2e8f0; border-radius: 6px; cursor: pointer; }
    .btn-view { padding: 6px 12px; background: #3b82f6; border: none; color: white; border-radius: 4px; cursor: pointer; }
    .empty-state { text-align: center; padding: 40px 20px; color: #94a3b8; }
    .pagination { display: flex; gap: 6px; justify-content: center; margin-top: 16px; }
    .pagination-link { padding: 8px 12px; border-radius: 6px; background: #2d3748; color: #cbd5e1; text-decoration: none; }
    .pagination-link.active { background: #3b82f6; color: #fff; }
    .pagination-link.disabled { opacity: 0.4; }
</style>

<div class="content-header">
    <h1>Notifications</h1>
    <form method="POST" action="{{ route('cashier.notifications.read-all') }}">
        @csrf
        <button type="submit" class="btn-mark-all"><i class="fas fa-check-double"></i> Mark all as read</button>
    </form>
</div>

@if(session('status'))
    <div style="margin-bottom: 16px; padding: 12px 16px; background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; border-radius: 8px; color: #10b981;">
        <i class="fas fa-check-circle"></i> {{ session('status') }}
    </div>
@endif

<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th style="width: 30px;"></th>
                <th>Title</th>
                <th>Description</th>
                <th>Received</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($notifications as $notification)
                @php
                    $__isUnread = is_null($notification->read_at);
                    $__dot = match($notification->data['color'] ?? 'info') {
                        'danger' => 'dot-danger',
                        'warning' => 'dot-warning',
                        'success' => 'dot-success',
                        default => 'dot-info',
                    };
                @endphp
                <tr class="{{ $__isUnread ? 'unread' : 'read' }}">
                    <td>
                        @if($__isUnread)
                            <span class="unread-dot {{ $__dot }}" title="Unread"></span>
                        @endif
                    </td>
                    <td>{{ $notification->data['title'] ?? 'Notification' }}</td>
                    <td>{{ $notification->data['description'] ?? '' }}</td>
                    <td>{{ $notification->created_at->format('M d, Y g:i A') }}</td>
                    <td>
                        <form method="POST" action="{{ route('cashier.notifications.read', $notification->id) }}">
                            @csrf
                            <button type="submit" class="btn-view" title="View">
                                <i class="fas fa-arrow-up-right-from-square"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <i class="fas fa-bell-slash" style="font-size: 1.8rem; margin-bottom: 8px; display: block;"></i>
                            No notifications yet.
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($notifications->hasPages())
        <div class="pagination">
            @if($notifications->onFirstPage())
                <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
            @else
                <a href="{{ $notifications->previousPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
            @endif

            @foreach($notifications->getUrlRange(1, $notifications->lastPage()) as $page => $url)
                <a href="{{ $url }}" class="pagination-link {{ $page === $notifications->currentPage() ? 'active' : '' }}">{{ $page }}</a>
            @endforeach

            @if($notifications->hasMorePages())
                <a href="{{ $notifications->nextPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
            @else
                <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
            @endif
        </div>
    @endif
</div>
@endsection
