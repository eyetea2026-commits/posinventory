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
                    if (!state.authenticated || state.user_id !== expectedUserId) {
                        window.location.replace(loginUrl);
                    }
                } else if (state.authenticated) {
                    window.location.reload();
                }
            })
            .catch(function () { })
            .finally(function () { checking = false; });
    }

    function tellOtherTabs() {
        if (channel) channel.postMessage('auth-changed');
        try { localStorage.setItem('cctv-session-ping', String(Date.now())); } catch (e) { }
    }

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

    tellOtherTabs();

    if (expectedUserId !== null) {
        const aliveUrl = @json(route('session.alive'));
        const checkIn = function () {
            fetch(aliveUrl, { cache: 'no-store', credentials: 'same-origin' }).catch(function () { });
        };
        checkIn();
        setInterval(checkIn, 30000);
    }
})();
</script>
