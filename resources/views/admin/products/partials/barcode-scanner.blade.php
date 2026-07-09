{{-- Shared barcode scanner. Included once by both the standalone create page
     and the Add Product modal (`admin.products.index`).

     Capture path: keyboard-emulation passthrough. A USB scanner, Bluetooth
     scanner, or an Android scanning app (e.g. "Barcode to PC") all just
     "type" into whatever field has focus. Since #Barcode is a plain text
     input, that already works on its own — clicking "Scan Barcode" simply
     moves focus there so the next scan lands directly in the field. --}}
<script>
(function () {
    // formId: the <form> containing #Barcode and #scanBarcodeBtn for this
    // page (there are two possible instances — the standalone create page's
    // form and the Add Product modal's form — but only one is ever present
    // in a given page load).
    window.initBarcodeScanner = function (formId) {
        var form = document.getElementById(formId);
        if (!form) return;

        var barcodeInput = form.querySelector('#Barcode');
        var scanBtn = form.querySelector('#scanBarcodeBtn');
        if (!barcodeInput || !scanBtn) return;

        // Scanners that emulate a keyboard type fast and often pad the value
        // with a leading/trailing space; trim once the field loses focus so
        // the stored value (and the duplicate check) always sees a clean one.
        barcodeInput.addEventListener('blur', function () {
            var trimmed = barcodeInput.value.trim();
            if (trimmed !== barcodeInput.value) {
                barcodeInput.value = trimmed;
            }
        });

        scanBtn.addEventListener('click', function () {
            barcodeInput.focus();
        });
    };
})();
</script>
