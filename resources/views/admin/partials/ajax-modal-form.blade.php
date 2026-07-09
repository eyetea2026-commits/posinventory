{{-- Generic AJAX submit + response classification, shared by every "Add X"
     modal (Category, Discount, Product, ...). The backend controllers are
     unchanged: they still validate with $request->validate() (422 JSON,
     handled automatically by Laravel when the request expects JSON) and
     still redirect on success (`with('status'|'success', ...)`) or on a
     custom business-rule rejection (`with('error', ...)` / `withErrors()`).

     Since fetch() follows that redirect, we get back the re-rendered index
     page's HTML. Both the success and error flash messages are rendered
     there as a literal `Swal.fire({title:'Success'|'Error', text:'...'})`
     call (every admin index page ends with this same "auto-show session
     messages" block) — we read whichever one actually fired instead of
     guessing from the HTTP status alone. --}}
<script>
window.submitAjaxForm = function (form, url, callbacks) {
    callbacks = callbacks || {};

    return fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: new FormData(form)
    }).then(async function (response) {
        if (response.status === 422) {
            const data = await response.json();
            if (callbacks.onFieldErrors) callbacks.onFieldErrors(data.errors || {});
            return;
        }

        const html = await response.text();
        const marker = 'Auto-show session messages';
        const idx = html.indexOf(marker);
        const tail = idx >= 0 ? html.slice(idx) : html;
        const successMatch = tail.match(/title:\s*'Success',\s*text:\s*'([^']*)'/);
        const errorMatch = tail.match(/title:\s*'Error',\s*text:\s*'([^']*)'/);

        if (successMatch) {
            if (callbacks.onSuccess) callbacks.onSuccess(html, successMatch[1]);
        } else if (errorMatch) {
            if (callbacks.onOtherError) callbacks.onOtherError(errorMatch[1]);
        } else {
            if (callbacks.onOtherError) callbacks.onOtherError('Something went wrong. Please try again.');
        }
    }).catch(function () {
        if (callbacks.onOtherError) callbacks.onOtherError('A network error occurred. Please try again.');
    });
};
</script>
