<script>
    (function () {
        if (window.__errorReporter) return;
        window.__errorReporter = true;

        var url = @json(route('client-errors.store'));
        var token = @json(csrf_token());
        var sent = {};
        var count = 0;

        function report(message, source, line, column, stack) {
            message = String(message || '').slice(0, 1000);
            source = String(source || '');
            if (!message || count >= 5 || sent[message]) return;
            if (/^(chrome|moz|safari)-extension:/.test(source)) return;
            if (message === 'Script error.' && !source) return;
            sent[message] = true;
            count++;
            try {
                fetch(url, {
                    method: 'POST',
                    keepalive: true,
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({
                        message: message,
                        page: String(location.href).slice(0, 500),
                        source: source.slice(0, 500) || null,
                        line: line || null,
                        column: column || null,
                        stack: stack ? String(stack).slice(0, 4000) : null,
                    }),
                }).catch(function () { });
            } catch (e) { }
        }

        window.addEventListener('error', function (e) {
            if (!e.message) return;
            report(e.message, e.filename, e.lineno, e.colno, e.error && e.error.stack);
        });

        window.addEventListener('unhandledrejection', function (e) {
            var reason = e.reason;
            report('Unhandled promise rejection: ' + (reason && reason.message ? reason.message : String(reason)), '', 0, 0, reason && reason.stack);
        });
    })();
</script>
