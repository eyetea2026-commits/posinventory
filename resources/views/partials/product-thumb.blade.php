<span class="product-thumb {{ $class ?? '' }}">
    @if($product->image_url)
        <img src="{{ $product->image_url }}" alt="{{ $product->ProductName }}" loading="lazy">
    @else
        <i class="fas fa-box" aria-hidden="true"></i>
    @endif
</span>
