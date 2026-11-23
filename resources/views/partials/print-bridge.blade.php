<script>
    window.PRINT_BRIDGE_URL = @json(\App\Services\ThermalReceiptPrinter::isEnabled() ? '' : (string) config('services.receipt_printer.bridge_url'));

    window.printViaBridge = function (receiptNumber, isNewSale, linesUrl) {
        if (!window.PRINT_BRIDGE_URL) {
            return Promise.reject(null);
        }

        Swal.fire({
            title: 'Printing receipt...',
            allowOutsideClick: false,
            showConfirmButton: false,
            customClass: { popup: 'swal-compact' },
            didOpen: function () { Swal.showLoading(); },
        });
        const hintTimer = setTimeout(function () {
            if (Swal.isVisible()) {
                Swal.update({ text: 'If Chrome asks to "access other apps and services on this device" or your local network, click Allow (top left, by the address bar).' });
                Swal.showLoading();
            }
        }, 3000);
        const done = function () {
            clearTimeout(hintTimer);
            if (Swal.isVisible() && Swal.getTitle() && Swal.getTitle().textContent === 'Printing receipt...') {
                Swal.close();
            }
        };

        return fetch(linesUrl || ('{{ url('cashier/receipt') }}/' + encodeURIComponent(receiptNumber) + '/print-lines'), {
            headers: { 'Accept': 'application/json' },
        })
            .then(function (r) {
                if (!r.ok) throw null;
                return r.json();
            })
            .then(function (receipt) {
                const controller = new AbortController();
                const timer = setTimeout(function () { controller.abort(); }, 30000);

                return fetch(window.PRINT_BRIDGE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        lines: receipt.lines,
                        open_drawer: !!isNewSale && receipt.payment_method === 'cash',
                    }),
                    signal: controller.signal,
                }).finally(function () { clearTimeout(timer); });
            })
            .then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (result) {
                    if (!result.success) throw (r.status === 503 ? result.message : null);
                });
            }, function (error) {
                throw null;
            })
            .then(function () { done(); }, function (message) {
                done();
                throw message;
            });
    };
</script>
