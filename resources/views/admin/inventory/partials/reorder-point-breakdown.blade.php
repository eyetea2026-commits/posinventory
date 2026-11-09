{{-- How the product's Re-Order Point was forecast (App\Services\ReorderPoint).
     Expects $product. --}}
@php
    $rop = \App\Services\ReorderPoint::forProduct($product->ProductID);
    $minimum = \App\Services\ReorderPoint::MINIMUM;
    $window = \App\Services\ReorderPoint::SALES_WINDOW_DAYS;
    $unit = \App\Services\UnitOfMeasure::plural($product->baseUnit());
@endphp
<h2 class="section-title">Re-Order Point (Forecast)</h2>
<div class="ui-kv-grid reorder-point-breakdown">
    <div class="detail-row">
        <span class="detail-label">Average Daily Sales</span>
        <span class="detail-value">{{ number_format($rop['average_daily_sales'], 2) }} {{ $unit }}/day <small>({{ $product->formatQuantity($rop['units_sold']) }} sold in {{ $window }} days)</small></span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Max Daily Sales</span>
        <span class="detail-value">{{ number_format($rop['max_daily_sales'], 2) }} {{ $unit }}/day</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Average Lead Time</span>
        <span class="detail-value">{{ number_format($rop['average_lead_time'], 1) }} days <small>@if($rop['lead_time_is_default'])(not received on a PO yet: {{ \App\Services\ReorderPoint::DEFAULT_LEAD_TIME_DAYS }} days assumed)@else(from {{ $rop['lead_time_orders'] }} purchase order{{ $rop['lead_time_orders'] === 1 ? '' : 's' }})@endif</small></span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Max Lead Time</span>
        <span class="detail-value">{{ $rop['max_lead_time'] }} days</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Safety Stock</span>
        <span class="detail-value">({{ number_format($rop['max_daily_sales'], 2) }} × {{ $rop['max_lead_time'] }}) − ({{ number_format($rop['average_daily_sales'], 2) }} × {{ number_format($rop['average_lead_time'], 1) }}) = {{ number_format($rop['safety_stock'], 2) }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Re-Order Point</span>
        <span class="detail-value">
            ({{ number_format($rop['average_daily_sales'], 2) }} × {{ number_format($rop['average_lead_time'], 1) }}) + {{ number_format($rop['safety_stock'], 2) }} = {{ $rop['computed'] }}
            @if($rop['computed'] < $minimum)<small>(below the minimum of {{ $minimum }})</small>@endif
            → <strong>{{ $product->formatQuantity($rop['reorder_point']) }}</strong>
        </span>
    </div>
</div>
<small class="reorder-point-formula" style="display:block;margin-top:6px;color:#94a3b8;">Re-Order Point = (Average Daily Sales × Average Lead Time) + Safety Stock, never below {{ $minimum }}. Recomputed after every sale and delivery, and daily.</small>
