<script>
    (function () {
        if (window.__dateInputGuard) return;
        window.__dateInputGuard = true;

        const SETTLE_MS = 900;
        const TYPED_WINDOW_MS = 1200;

        const isDateInput = el => el instanceof HTMLInputElement
            && ['date', 'datetime-local', 'month', 'week', 'time'].includes(el.type);

        function looksComplete(input) {
            if (input.value === '') return true;
            const year = parseInt(input.value.slice(0, 4), 10);
            return input.type === 'time' || year >= 1000;
        }

        function release(input) {
            const state = input.__dateGuard;
            if (!state || !state.pending) return;
            clearTimeout(state.timer);
            state.pending = false;
            state.passing = true;
            input.dispatchEvent(new Event('change', { bubbles: true }));
            state.passing = false;
        }

        document.addEventListener('keydown', function (e) {
            const input = e.target;
            if (!isDateInput(input)) return;
            const state = input.__dateGuard || (input.__dateGuard = {});
            state.lastKey = Date.now();
            if (e.key === 'Enter') release(input);
        }, true);

        document.addEventListener('change', function (e) {
            const input = e.target;
            if (!isDateInput(input)) return;
            const state = input.__dateGuard || (input.__dateGuard = {});
            if (state.passing) return;
            if (!state.lastKey || Date.now() - state.lastKey > TYPED_WINDOW_MS) return;
            e.stopImmediatePropagation();
            state.pending = true;
            clearTimeout(state.timer);
            state.timer = setTimeout(function () { if (looksComplete(input)) release(input); }, SETTLE_MS);
        }, true);

        document.addEventListener('blur', function (e) {
            if (isDateInput(e.target)) release(e.target);
        }, true);
    })();
</script>
