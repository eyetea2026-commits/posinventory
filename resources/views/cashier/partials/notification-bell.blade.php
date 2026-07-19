<div class="notif-bell-wrap">
    <button type="button" class="notif-bell-btn" onclick="toggleNotifDropdown(event)">
        <i class="fas fa-bell"></i>
        @if(($headerUnreadCount ?? 0) > 0)
            <span class="notif-badge">{{ $headerUnreadCount > 99 ? '99+' : $headerUnreadCount }}</span>
        @endif
    </button>
    {{-- This partial is included more than once per page (mobile header +
         page header), so nothing in here may rely on a fixed id — duplicate
         ids would make getElementById() silently grab the wrong instance
         (e.g. the hidden mobile-header copy). Dropdown/form lookups below
         use DOM relationships (closest/nextElementSibling) instead. --}}
    <div class="notif-dropdown">
        <div class="notif-dropdown-header">
            <span>Notifications</span>
            @if(($headerUnreadCount ?? 0) > 0)
                <form method="POST" action="{{ route('cashier.notifications.read-all') }}">
                    @csrf
                    <button type="submit">Mark all read</button>
                </form>
            @endif
        </div>
        @forelse(($headerUnreadNotifications ?? collect()) as $notification)
            <a href="{{ route('cashier.notifications.read', $notification->id) }}"
               onclick="event.preventDefault(); this.nextElementSibling.submit();"
               class="notif-item">
                {{ $notification->data['title'] ?? 'Notification' }}
                <br><small>{{ Str::limit($notification->data['description'] ?? '', 60) }}</small>
            </a>
            <form method="POST" action="{{ route('cashier.notifications.read', $notification->id) }}" style="display:none;">
                @csrf
            </form>
        @empty
            <div class="notif-empty">You're all caught up.</div>
        @endforelse
        <a href="{{ route('cashier.notifications.index') }}" class="notif-view-all">View all notifications</a>
    </div>
</div>
