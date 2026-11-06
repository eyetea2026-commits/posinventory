{{-- Sends JavaScript errors to the server, which writes them to
     laravel.log as "Browser error" (ClientErrorController). Included at the
     top of <head> so errors in every script after it are caught. At most 5
     per page and each message once; errors from browser extensions and the
     detail-less "Script error." of other sites' scripts are skipped. --}}
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
                }).catch(function () { /* nothing more to do if this fails too */ });
            } catch (e) { /* never let reporting break the page */ }
        }

        window.addEventListener('error', function (e) {
            // A failed image or stylesheet fires here too, without a message.
            if (!e.message) return;
            report(e.message, e.filename, e.lineno, e.colno, e.error && e.error.stack);
        });

        window.addEventListener('unhandledrejection', function (e) {
            var reason = e.reason;
            report('Unhandled promise rejection: ' + (reason && reason.message ? reason.message : String(reason)), '', 0, 0, reason && reason.stack);
        });
    })();
</script>
