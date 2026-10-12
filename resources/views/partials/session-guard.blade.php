{{-- Keeps every open page in step with the real sign-in state:
     - Back after logout: the page is never served from the browser's cache
       (PreventBackHistory middleware), and if Chrome restores it from memory
       anyway it reloads, so the server redirects to the login page.
     - Logout/login in another tab: every open tab re-checks at once.
     - Coming back to a tab: re-checks, so a session that ended elsewhere
       (logged out, password reset) is noticed immediately.
     Deliberately no timer for those checks: each one counts as activity, so
     polling would stop an unattended screen from ever timing out.
     - Signed-in pages also check in every 30 seconds at /session/alive,
       which does NOT count as activity. It tells the login screen this
       browser is still open, so once the browser is closed the account can
       be signed into again within about 2 minutes. --}}
<script>
(function () {
    const expectedUserId = @json(auth()->id());
    const statusUrl = @json(route('session.status'));
    const loginUrl = @json(route('welcome'));
    const channel = ('BroadcastChannel' in window) ? new BroadcastChannel('cctv-session') : null;
    let checking = false;
    let lastCheck = 0;

    function checkSession() {
        const now = Date.now();
        if (checking || now - lastCheck < 1000) return;
        checking = true;
        lastCheck = now;

        fetch(statusUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store', credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (state) {
                if (!state) return;
                if (expectedUserId !== null) {
                    // Signed-in page: leave if signed out or now someone else.
                    if (!state.authenticated || state.user_id !== expectedUserId) {
                        window.location.replace(loginUrl);
                    }
                } else if (state.authenticated) {
                    // Login page: someone signed in from another tab -- reload
                    // so the server sends this tab to their home screen too.
                    window.location.reload();
                }
            })
            .catch(function () { /* offline: try again on the next trigger */ })
            .finally(function () { checking = false; });
    }

    function tellOtherTabs() {
        if (channel) channel.postMessage('auth-changed');
        try { localStorage.setItem('cctv-session-ping', String(Date.now())); } catch (e) { /* storage blocked */ }
    }

    // Restored from Chrome's back/forward memory: never trust the old copy.
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) window.location.reload();
    });

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') checkSession();
    });
    window.addEventListener('focus', checkSession);

    if (channel) channel.onmessage = function () { checkSession(); };
    window.addEventListener('storage', function (e) {
        if (e.key === 'cctv-session-ping') checkSession();
    });

    // Every page load (including the login page right after a logout, or the
    // first page after a login) tells the other open tabs to re-check.
    tellOtherTabs();

    if (expectedUserId !== null) {
        const aliveUrl = @json(route('session.alive'));
        const checkIn = function () {
            fetch(aliveUrl, { cache: 'no-store', credentials: 'same-origin' }).catch(function () { /* offline */ });
        };
        checkIn();
        setInterval(checkIn, 30000);
    }
})();
</script>
