<script>
function flashText(match) {
    if (!match) return null;
    const literal = match[1];
    if (literal.charAt(0) === '"') {
        try { return JSON.parse(literal); } catch (e) { return literal.slice(1, -1); }
    }
    const decoder = document.createElement('textarea');
    decoder.innerHTML = literal.slice(1, -1);
    return decoder.value;
}

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
        const successText = flashText(tail.match(/title:\s*'Success',\s*text:\s*("(?:[^"\\]|\\.)*"|'[^']*')/));
        const errorText = flashText(tail.match(/title:\s*'Error',\s*text:\s*("(?:[^"\\]|\\.)*"|'[^']*')/));

        if (successText !== null) {
            if (callbacks.onSuccess) callbacks.onSuccess(html, successText);
        } else if (errorText !== null) {
            if (callbacks.onOtherError) callbacks.onOtherError(errorText);
        } else {
            if (callbacks.onOtherError) callbacks.onOtherError('Unable to save. The page may be out of date — please refresh and try again.');
        }
    }).catch(function () {
        if (callbacks.onOtherError) callbacks.onOtherError('A network error occurred. Please try again.');
    });
};
</script>
