{{-- A product's photo, or a box icon when it has none. Pass $product and an
     optional $class for the size (styled by the page). --}}
<span class="product-thumb {{ $class ?? '' }}">
    @if($product->image_url)
        <img src="{{ $product->image_url }}" alt="{{ $product->ProductName }}" loading="lazy">
    @else
        <i class="fas fa-box" aria-hidden="true"></i>
    @endif
</span>
