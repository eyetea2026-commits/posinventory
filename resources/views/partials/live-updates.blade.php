{{-- Real-time updates for signed-in pages (see LiveUpdatesController).
     Every few seconds, while the tab is visible, asks the server for a
     short fingerprint of this user's notifications and return/refund
     requests. When it changes (a cashier sent a request, an admin approved
     or declined one, a notification arrived or was read elsewhere), the page
     quietly re-fetches itself and swaps in only its live parts -- elements
     marked data-live-region="name" (the notification bell, request lists) --
     so nobody has to refresh. A part the user is typing in is left alone,
     and an open notification dropdown stays open. Pages that need to redo
     something after a swap listen for the "live-updates:applied" event. --}}
@auth
<script>
(function () {
    const signatureUrl = @json(route('live.signature'));
    const initial = @json(\App\Http\Controllers\LiveUpdatesController::stateFor(auth()->user()));
    const POLL_MS = 5000;

    let signature = initial.signature;
    let unread = initial.unread;
    let busy = false;

    function regionsByName(root) {
        const groups = {};
        root.querySelectorAll('[data-live-region]').forEach(function (el) {
            (groups[el.dataset.liveRegion] = groups[el.dataset.liveRegion] || []).push(el);
        });
        return groups;
    }

    function refreshRegions() {
        const current = regionsByName(document);
        if (!Object.keys(current).length) return Promise.resolve(true);

        return fetch(window.location.href, {
            headers: { 'Accept': 'text/html' },
            cache: 'no-store',
            credentials: 'same-origin',
        }).then(function (r) {
            // Signed out / sent elsewhere: leave it to the session guard.
            if (!r.ok || r.redirected) return false;
            return r.text().then(function (html) {
                const fresh = regionsByName(new DOMParser().parseFromString(html, 'text/html'));
                const applied = [];

                Object.keys(current).forEach(function (name) {
                    current[name].forEach(function (el, i) {
                        const replacement = (fresh[name] || [])[i];
                        if (!replacement || el.innerHTML === replacement.innerHTML) return;
                        // Don't pull the rug out from under someone typing.
                        if (document.activeElement && document.activeElement !== document.body && el.contains(document.activeElement)) return;

                        const dropdownOpen = el.querySelector('.notif-dropdown.active') !== null;
                        el.innerHTML = replacement.innerHTML;
                        if (dropdownOpen) {
                            const dropdown = el.querySelector('.notif-dropdown');
                            if (dropdown) dropdown.classList.add('active');
                        }
                        if (applied.indexOf(name) === -1) applied.push(name);
                    });
                });

                if (applied.length) {
                    document.dispatchEvent(new CustomEvent('live-updates:applied', { detail: { regions: applied } }));
                }
                return true;
            });
        }).catch(function () { return false; });
    }

    function announce(state) {
        if (!(state.unread > unread) || !state.latest_title || !window.Swal || Swal.isVisible()) return;
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'info',
            title: state.latest_title,
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
        });
    }

    function poll() {
        if (busy || document.visibilityState !== 'visible') return;
        busy = true;

        fetch(signatureUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store', credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (state) {
                if (!state || !state.signature || state.signature === signature) return;
                return refreshRegions().then(function (ok) {
                    // Only move on once the page caught up, so a failed
                    // refresh is retried on the next check.
                    if (!ok) return;
                    announce(state);
                    signature = state.signature;
                    unread = state.unread;
                });
            })
            .catch(function () { /* offline: the next check retries */ })
            .finally(function () { busy = false; });
    }

    setInterval(poll, POLL_MS);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') poll();
    });
})();
</script>
@endauth
