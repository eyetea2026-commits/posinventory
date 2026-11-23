@auth
<style>
    .live-toast-stack { position: fixed; top: 16px; right: 16px; z-index: 10000; display: flex; flex-direction: column; gap: 10px; width: 340px; max-width: calc(100vw - 32px); pointer-events: none; }
    .live-toast {
        pointer-events: auto; display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px;
        background: rgba(15, 23, 42, 0.92); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(148, 163, 184, 0.2); border-left: 4px solid #3b82f6; border-radius: 12px;
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.45); color: #e2e8f0; font-size: 14px; line-height: 1.4;
        cursor: pointer; transform: translateX(120%); opacity: 0; transition: transform 0.3s ease, opacity 0.3s ease;
    }
    .live-toast.show { transform: translateX(0); opacity: 1; }
    .live-toast-icon { flex-shrink: 0; width: 32px; height: 32px; border-radius: 50%; background: rgba(59, 130, 246, 0.18); color: #60a5fa; display: flex; align-items: center; justify-content: center; font-size: 14px; }
    .live-toast-body { flex: 1; min-width: 0; }
    .live-toast-title { font-weight: 700; color: #f8fafc; overflow-wrap: anywhere; }
    .live-toast-text { margin-top: 2px; color: #94a3b8; font-size: 13px; overflow-wrap: anywhere; }
    .live-toast-close { flex-shrink: 0; background: none; border: none; color: #94a3b8; font-size: 18px; line-height: 1; cursor: pointer; padding: 0 2px; }
    .live-toast-close:hover { color: #f8fafc; }
</style>
<script>
(function () {
    const signatureUrl = @json(route('live.signature'));
    const notificationsUrl = @json(auth()->user()->isAdmin() ? route('admin.notifications.index') : route('cashier.notifications.index'));
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

    function refreshRegions(changed, url, signal) {
        const current = regionsByName(document, changed);
        if (!Object.keys(current).length) return Promise.resolve([]);

        return fetch(url || window.location.href, {
            headers: { 'Accept': 'text/html' },
            cache: 'no-store',
            credentials: 'same-origin',
            signal: signal,
        }).then(function (r) {
            if (!r.ok || r.redirected) return null;
            return r.text().then(function (html) {
                const fresh = regionsByName(new DOMParser().parseFromString(html, 'text/html'), null);
                const applied = [];

                Object.keys(current).forEach(function (name) {
                    current[name].forEach(function (el, i) {
                        const replacement = (fresh[name] || [])[i];
                        if (!replacement || el.innerHTML === replacement.innerHTML) return;
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

    function showToast(title, text) {
        let stack = document.querySelector('.live-toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'live-toast-stack';
            stack.setAttribute('aria-live', 'polite');
            document.body.appendChild(stack);
        }

        const toast = document.createElement('div');
        toast.className = 'live-toast';
        toast.setAttribute('role', 'status');
        toast.innerHTML = '<div class="live-toast-icon"><i class="fas fa-bell"></i></div>' +
            '<div class="live-toast-body"><div class="live-toast-title"></div><div class="live-toast-text"></div></div>' +
            '<button type="button" class="live-toast-close" aria-label="Close">&times;</button>';
        toast.querySelector('.live-toast-title').textContent = title;
        const textEl = toast.querySelector('.live-toast-text');
        if (text) { textEl.textContent = text; } else { textEl.remove(); }

        let timer = null;
        const close = function () {
            clearTimeout(timer);
            toast.classList.remove('show');
            setTimeout(function () { toast.remove(); }, 300);
        };
        toast.querySelector('.live-toast-close').addEventListener('click', function (e) { e.stopPropagation(); close(); });
        toast.addEventListener('click', function () { window.location.href = notificationsUrl; });
        toast.addEventListener('mouseenter', function () { clearTimeout(timer); });
        toast.addEventListener('mouseleave', function () { timer = setTimeout(close, 3000); });

        stack.prepend(toast);
        while (stack.children.length > 3) stack.lastElementChild.remove();
        requestAnimationFrame(function () { toast.classList.add('show'); });
        timer = setTimeout(close, 6000);
    }

    function announce(state) {
        if (!(state.unread > unread) || !state.latest_title) return;
        showToast(state.latest_title, state.latest_description);
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
                    if (applied === null) return;

                    watchers.forEach(function (w) {
                        if (!overlaps(w.topics, changed)) return;
                        try { w.callback(changed); } catch (e) { }
                    });
                    if (applied.length) {
                        document.dispatchEvent(new CustomEvent('live-updates:applied', { detail: { regions: applied, topics: changed } }));
                    }
                    if (changed.indexOf('notifications') !== -1) announce(state);

                    changed.forEach(function (t) { known[t] = state.signatures[t]; });
                    unread = state.unread;
                });
            })
            .catch(function () { })
            .finally(function () { busy = false; });
    }

    setInterval(poll, POLL_MS);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') poll();
    });

    const SEARCH_DELAY_MS = 300;
    let searchController = null;

    function liveSearch(form) {
        const params = new URLSearchParams();
        new FormData(form).forEach(function (value, key) {
            if (String(value).trim() !== '') params.set(key, String(value).trim());
        });
        const url = form.action.split('?')[0] + (params.toString() ? '?' + params.toString() : '');

        if (searchController) searchController.abort();
        searchController = new AbortController();

        window.history.replaceState({}, '', url);
        refreshRegions(null, url, searchController.signal).then(function (applied) {
            if (applied && applied.length) {
                document.dispatchEvent(new CustomEvent('live-updates:applied', { detail: { regions: applied, topics: [] } }));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-live-search]').forEach(function (form) {
            let timer = null;

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                clearTimeout(timer);
                liveSearch(form);
            });
            form.addEventListener('input', function (e) {
                const field = e.target;
                if (!(field instanceof HTMLInputElement) || !['text', 'search'].includes(field.type)) return;
                clearTimeout(timer);
                timer = setTimeout(function () { liveSearch(form); }, SEARCH_DELAY_MS);
            });
            form.addEventListener('change', function (e) {
                const field = e.target;
                if (field instanceof HTMLInputElement && ['text', 'search'].includes(field.type)) return;
                clearTimeout(timer);
                liveSearch(form);
            });
        });
    });
})();
</script>
@endauth
