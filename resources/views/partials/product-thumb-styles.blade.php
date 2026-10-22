{{-- Sizes for partials/product-thumb.blade.php. Include once per page. --}}
<style>
    .product-thumb {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        overflow: hidden;
        border-radius: 8px;
        background: #ffffff;
        color: #94a3b8;
        width: 44px;
        height: 44px;
    }
    .product-thumb img { width: 100%; height: 100%; object-fit: contain; }
    .product-thumb:has(i) { background: rgba(148, 163, 184, 0.12); }
    .product-thumb i { font-size: 1.1rem; }
    .product-thumb.thumb-card { width: 64px; height: 64px; margin-bottom: 8px; }
    .product-thumb.thumb-card i { font-size: 1.4rem; }
    .product-thumb.thumb-large { width: 160px; height: 160px; border-radius: 12px; }
    .product-thumb.thumb-large i { font-size: 2.5rem; }
    .product-cell { display: flex; align-items: center; gap: 12px; }
    .product-photo-row { display: flex; justify-content: center; margin-bottom: 16px; }
</style>
