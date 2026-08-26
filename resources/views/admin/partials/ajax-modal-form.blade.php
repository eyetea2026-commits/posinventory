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
     guessing from the HTTP status alone.

     A handful of statuses never reach that HTML-scrape stage at all — a
     stale CSRF token (419, e.g. a modal left open across a session
     timeout), a logged-out session (401/403), or an actual server error
     (5xx) all redirect to a page with no such Swal call, which used to
     fall through to one indistinguishable "Something went wrong" message
     no matter which of these actually happened. Handled explicitly here
     instead, so the message actually says what went wrong — without
     leaking exception detail into the production UI. --}}
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

        if (response.status === 419) {
            if (callbacks.onOtherError) callbacks.onOtherError('Your session has expired. Please refresh the page and try again.');
            return;
        }

        if (response.status === 401 || response.status === 403) {
            if (callbacks.onOtherError) callbacks.onOtherError('You have been logged out. Please log in again.');
            return;
        }

        if (response.status >= 500) {
            if (callbacks.onOtherError) callbacks.onOtherError('A server error occurred while saving. Please try again, and contact support if the problem continues.');
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
            if (callbacks.onOtherError) callbacks.onOtherError('Unable to save. The page may be out of date — please refresh and try again.');
        }
    }).catch(function () {
        if (callbacks.onOtherError) callbacks.onOtherError('A network error occurred. Please try again.');
    });
};
</script>
