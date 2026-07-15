@if($stockAlerts->isEmpty())
    <div class="empty-state">
        <div class="empty-icon"><i class="fas fa-circle-check"></i></div>
        <p class="empty-title">All Stocked Up</p>
        <p class="empty-text">No products are low or out of stock right now.</p>
    </div>
@else
    <div class="stock-alert-list">
        @foreach($stockAlerts as $alert)
            <div class="stock-alert-item">
                <div class="stat-icon {{ $alert['quantity'] <= 0 ? 'red' : 'yellow' }}" style="width:40px;height:40px;font-size:1rem;">
                    <i class="fas {{ $alert['status']['icon'] }}"></i>
                </div>
                <div class="stat-content">
                    <div class="stock-alert-name">{{ $alert['product']?->ProductName ?? 'Unknown product' }}</div>
                    <div class="stock-alert-qty">{{ $alert['quantity'] }} units remaining</div>
                </div>
                <span class="badge {{ $alert['status']['class'] === 'badge-out-of-stock' ? 'badge-danger' : ($alert['status']['class'] === 'badge-replenish' ? 'badge-replenish' : 'badge-warning') }}">{{ $alert['status']['label'] }}</span>
                @if($alert['product'])
                    <a href="{{ route('admin.inventory.show', $alert['product']) }}" class="stock-alert-view" title="View Details">
                        <i class="fas fa-eye"></i>
                    </a>
                @endif
            </div>
        @endforeach
    </div>
@endif
