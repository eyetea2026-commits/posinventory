<script>
(function () {
    window.initBarcodeScanner = function (formId) {
        var form = document.getElementById(formId);
        if (!form) return;

        var barcodeInput = form.querySelector('#Barcode');
        var scanBtn = form.querySelector('#scanBarcodeBtn');
        if (!barcodeInput || !scanBtn) return;

        barcodeInput.addEventListener('blur', function () {
            var trimmed = barcodeInput.value.trim();
            if (trimmed !== barcodeInput.value) {
                barcodeInput.value = trimmed;
            }
        });

        barcodeInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === 'NumpadEnter') {
                event.preventDefault();
                var trimmed = barcodeInput.value.trim();
                if (trimmed !== barcodeInput.value) {
                    barcodeInput.value = trimmed;
                }
            }
        });

        scanBtn.addEventListener('click', function () {
            barcodeInput.focus();
        });
    };
})();
</script>
