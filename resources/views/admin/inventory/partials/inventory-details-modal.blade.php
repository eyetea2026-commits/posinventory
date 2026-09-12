{{-- "View Details" modal, opened from the Inventory list. Keeps the admin on
     the Inventory page instead of navigating to a separate page — the same
     content InventoryController::show() renders for direct navigation is
     fetched over AJAX and injected into the modal body. --}}
@include('admin.partials.modal-styles')

<div id="inventoryDetailsModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="inventoryDetailsModalTitle" aria-hidden="true">
    <div class="modal-content modal-content-wide">
        <div class="modal-header">
            <h2 id="inventoryDetailsModalTitle"><i class="fas fa-box"></i> Inventory Details</h2>
            <button type="button" class="modal-close" onclick="closeInventoryDetailsModal()" aria-label="Close">&times;</button>
        </div>

        <div id="inventoryDetailsBody" style="min-height:160px;">
            <div style="display:flex; align-items:center; justify-content:center; min-height:160px; color:#94a3b8;">
                <i class="fas fa-spinner fa-spin"></i>
            </div>
        </div>

        <div class="modal-actions" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" id="inventoryDetailsCloseBtn">Close</button>
        </div>
    </div>
</div>

<script>
    const INVENTORY_DETAILS_FETCH_URL_TEMPLATE = '{{ route('admin.inventory.show', ['product' => '__PRODUCT_ID__']) }}';
    let inventoryDetailsLastFocused = null;

    window.openInventoryDetailsModal = function (productId) {
        const modal = document.getElementById('inventoryDetailsModal');
        const body = document.getElementById('inventoryDetailsBody');

        inventoryDetailsLastFocused = document.activeElement;
        body.innerHTML = '<div style="display:flex; align-items:center; justify-content:center; min-height:160px; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i></div>';

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        void modal.offsetHeight;
        requestAnimationFrame(function () { modal.classList.add('active'); });
        document.addEventListener('keydown', handleInventoryDetailsModalKeydown);

        fetch(INVENTORY_DETAILS_FETCH_URL_TEMPLATE.replace('__PRODUCT_ID__', productId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                body.innerHTML = data.html;
                const titleEl = document.getElementById('inventoryDetailsModalTitle');
                if (titleEl) {
                    titleEl.innerHTML = '<i class="fas fa-box"></i> Inventory Details &mdash; ' + data.productName;
                }
            })
            .catch(function () {
                body.innerHTML = '<p style="color:#fca5a5;padding:20px;text-align:center;">Failed to load product details. Please try again.</p>';
            });
    };

    window.closeInventoryDetailsModal = function () {
        const modal = document.getElementById('inventoryDetailsModal');
        modal.classList.remove('active');
        document.removeEventListener('keydown', handleInventoryDetailsModalKeydown);
        setTimeout(function () { modal.style.display = 'none'; }, 250);
        document.body.style.overflow = '';
        if (inventoryDetailsLastFocused && typeof inventoryDetailsLastFocused.focus === 'function') {
            inventoryDetailsLastFocused.focus();
        }
    };

    function handleInventoryDetailsModalKeydown(e) {
        const modal = document.getElementById('inventoryDetailsModal');
        if (!modal.classList.contains('active')) return;
        if (e.key === 'Escape') window.closeInventoryDetailsModal();
    }

    document.getElementById('inventoryDetailsModal').addEventListener('mousedown', function (e) {
        if (e.target === this) window.closeInventoryDetailsModal();
    });
    document.getElementById('inventoryDetailsCloseBtn').addEventListener('click', function () {
        window.closeInventoryDetailsModal();
    });
</script>
