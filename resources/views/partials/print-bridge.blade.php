{{-- Print bridge (see App\Http\Controllers\PrintBridgeController). On the
     deployed site, where direct thermal printing is off, the receipt's
     printer lines are sent to the local copy of this app on the cashier PC,
     which prints them exactly like a local sale. Resolves once printed;
     rejects with a message when the printer reported a problem, or with
     null when no bridge is running (the caller then falls back to the
     browser print). --}}
<script>
    window.PRINT_BRIDGE_URL = @json(\App\Services\ThermalReceiptPrinter::isEnabled() ? '' : (string) config('services.receipt_printer.bridge_url'));

    window.printViaBridge = function (receiptNumber, isNewSale) {
        if (!window.PRINT_BRIDGE_URL) {
            return Promise.reject(null);
        }

        return fetch('{{ url('cashier/receipt') }}/' + encodeURIComponent(receiptNumber) + '/print-lines', {
            headers: { 'Accept': 'application/json' },
        })
            .then(function (r) {
                if (!r.ok) throw null;
                return r.json();
            })
            .then(function (receipt) {
                // Long enough for Chrome's one-time "access devices on your
                // local network" prompt; an unreachable bridge fails at once.
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
            });
    };
</script>
