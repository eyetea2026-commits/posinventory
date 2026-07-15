@extends('admin.layout')

@section('title', 'Notification Center - CCTV Express')

@section('header')
    <div class="header-title">
        <h1>Notification Center</h1>
        <p>Low stock alerts, stock adjustments, product receiving, and refund requests</p>
    </div>
@endsection

@section('header-actions')
    <form method="POST" action="{{ route('admin.notifications.read-all') }}">
        @csrf
        <button type="submit" class="btn btn-secondary"><i class="fas fa-check-double"></i> Mark all as read</button>
    </form>
@endsection

@section('content')
    @if(session('status'))
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            {{ session('status') }}
        </div>
    @endif

    <div class="card">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40px;"></th>
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
                            $__color = match($notification->data['color'] ?? 'info') {
                                'danger' => 'badge-danger',
                                'warning' => 'badge-warning',
                                'success' => 'badge-success',
                                default => 'badge-info',
                            };
                        @endphp
                        <tr style="{{ $__isUnread ? 'font-weight: 600;' : 'opacity: 0.7;' }}">
                            <td>
                                @if($__isUnread)
                                    <span class="badge {{ $__color }}" title="Unread" style="display:inline-block;width:8px;height:8px;padding:0;border-radius:50%;"></span>
                                @endif
                            </td>
                            <td>{{ $notification->data['title'] ?? 'Notification' }}</td>
                            <td>{{ $notification->data['description'] ?? '' }}</td>
                            <td>{{ $notification->created_at->format('M d, Y g:i A') }}</td>
                            <td>
                                <div class="actions-group">
                                    <form method="POST" action="{{ route('admin.notifications.read', $notification->id) }}">
                                        @csrf
                                        <button type="submit" class="action-btn" title="View Details">
                                            <i class="fas fa-arrow-up-right-from-square"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <div class="empty-icon"><i class="fas fa-bell-slash"></i></div>
                                    <p class="empty-title">No Notifications</p>
                                    <p class="empty-text">System notifications will appear here.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

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
