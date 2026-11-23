<script>
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
