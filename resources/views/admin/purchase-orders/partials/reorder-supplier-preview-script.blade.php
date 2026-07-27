{{-- Defines window.onReorderSupplierChange, referenced via an inline
     onchange="" attribute inside reorder-form-fields.blade.php. Must be
     included via a real Blade @include (server-rendered into the page) —
     not injected later through innerHTML — since <script> tags added via
     innerHTML never execute. --}}
<script>
    // As the admin picks a supplier (ambiguous/none-known states), reflect
    // its contact details immediately so the auto-filled fields stay
    // accurate to what will actually be saved.
    function onReorderSupplierChange(select) {
        const option = select.options[select.selectedIndex];
        const preview = document.getElementById('reorderSupplierContactPreview');
        if (!preview) return;

        if (option && option.value) {
            document.getElementById('previewContactPerson').textContent = option.dataset.contactPerson || 'N/A';
            document.getElementById('previewContactNumber').textContent = option.dataset.contactNumber || 'N/A';
            document.getElementById('previewEmail').textContent = option.dataset.email || 'N/A';
            document.getElementById('previewAddress').textContent = option.dataset.address || 'N/A';
            preview.style.display = 'grid';
        } else {
            preview.style.display = 'none';
        }
    }
</script>
