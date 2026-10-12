{{-- Real-time updates for signed-in pages (see LiveUpdatesController and
     App\Services\LiveDataVersions).

     Every few seconds, while the tab is visible, asks the server for short
     fingerprints of the data this page shows. When one changes (a sale, a
     new product, a return request, an approval, a notification...), the
     page updates itself without a refresh, in one of two ways:

     - Parts marked  data-live-region="name" data-live-topics="products,inventory"
       are swapped in place: the page quietly re-fetches its own address
       (which carries its search/filter/page) and replaces just those parts.
       A part the user is typing in is left alone; an open notification
       dropdown stays open.
     - Page code can register its own refresh:
           window.liveUpdates.watch(['sales', 'inventory'], function (topics) { ... });
       for widgets it already knows how to reload (charts, AJAX tables).

     Topics: notifications, returns, plus every key in
     LiveDataVersions::TOPICS. After swapping parts the page also gets a
     "live-updates:applied" event (detail: regions, topics). --}}
@auth
<script>
(function () {
    const signatureUrl = @json(route('live.signature'));
    const initial = @json(\App\Http\Controllers\LiveUpdatesController::stateFor(auth()->user()));
    const POLL_MS = 5000;

    const known = Object.assign({}, initial.signatures);
    let unread = initial.unread;
    let busy = false;
    const watchers = [];

    window.liveUpdates = {
        watch: function (topics, callback) {
            watchers.push({ topics: [].concat(topics), callback: callback });
        },
    };

    function topicsOf(el) {
        return (el.dataset.liveTopics || 'notifications').split(',').map(function (t) { return t.trim(); }).filter(Boolean);
    }

    function pageTopics() {
        const topics = {};
        document.querySelectorAll('[data-live-region]').forEach(function (el) {
            topicsOf(el).forEach(function (t) { topics[t] = true; });
        });
        watchers.forEach(function (w) { w.topics.forEach(function (t) { topics[t] = true; }); });
        return Object.keys(topics);
    }

    function overlaps(list, changed) {
        return list.some(function (t) { return changed.indexOf(t) !== -1; });
    }

    function regionsByName(root, changed) {
        const groups = {};
        root.querySelectorAll('[data-live-region]').forEach(function (el) {
            if (changed && !overlaps(topicsOf(el), changed)) return;
            (groups[el.dataset.liveRegion] = groups[el.dataset.liveRegion] || []).push(el);
        });
        return groups;
    }

    // Resolves to the names of the parts that were replaced, or null if the
    // page couldn't be re-fetched (retried on the next check).
    function refreshRegions(changed) {
        const current = regionsByName(document, changed);
        if (!Object.keys(current).length) return Promise.resolve([]);

        return fetch(window.location.href, {
            headers: { 'Accept': 'text/html' },
            cache: 'no-store',
            credentials: 'same-origin',
        }).then(function (r) {
            // Signed out / sent elsewhere: leave it to the session guard.
            if (!r.ok || r.redirected) return null;
            return r.text().then(function (html) {
                const fresh = regionsByName(new DOMParser().parseFromString(html, 'text/html'), null);
                const applied = [];

                Object.keys(current).forEach(function (name) {
                    current[name].forEach(function (el, i) {
                        const replacement = (fresh[name] || [])[i];
                        if (!replacement || el.innerHTML === replacement.innerHTML) return;
                        // Don't pull the rug out from under someone typing.
                        if (document.activeElement && document.activeElement !== document.body && el.contains(document.activeElement)) return;

                        const dropdownOpen = el.querySelector('.notif-dropdown.active') !== null;
                        const scrollTop = el.scrollTop;
                        el.innerHTML = replacement.innerHTML;
                        el.scrollTop = scrollTop;
                        if (dropdownOpen) {
                            const dropdown = el.querySelector('.notif-dropdown');
                            if (dropdown) dropdown.classList.add('active');
                        }
                        if (applied.indexOf(name) === -1) applied.push(name);
                    });
                });
                return applied;
            });
        }).catch(function () { return null; });
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
        const topics = pageTopics();
        if (!topics.length) return;
        busy = true;

        fetch(signatureUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store', credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (state) {
                if (!state || !state.signatures) return;
                const changed = topics.filter(function (t) { return state.signatures[t] !== undefined && state.signatures[t] !== known[t]; });
                if (!changed.length) return;

                return refreshRegions(changed).then(function (applied) {
                    // Only move on once the page caught up, so a failed
                    // refresh is retried on the next check.
                    if (applied === null) return;

                    watchers.forEach(function (w) {
                        if (!overlaps(w.topics, changed)) return;
                        try { w.callback(changed); } catch (e) { /* one widget failing mustn't stop the rest */ }
                    });
                    if (applied.length) {
                        document.dispatchEvent(new CustomEvent('live-updates:applied', { detail: { regions: applied, topics: changed } }));
                    }
                    if (changed.indexOf('notifications') !== -1) announce(state);

                    changed.forEach(function (t) { known[t] = state.signatures[t]; });
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
