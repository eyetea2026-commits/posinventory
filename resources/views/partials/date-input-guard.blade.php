{{-- Lets people TYPE into date fields on every admin and cashier page.
     While a date is typed, the browser fires "change" after every digit
     (e.g. the year passes through 0002, 0020, 0202 before 2026). Filters
     that react to "change" -- the Purchase Orders list reloads the page,
     Reports and the Activity Log refetch -- would fire on that half-typed
     date and, in the reload case, throw away what was being typed.

     This holds back "change" events that come from typing and sends a
     single one once the date is settled: after a short pause, on Enter, or
     when the field loses focus. Picking a date from the calendar (no
     typing) still applies straight away. The field's own value is never
     touched. --}}
<script>
    (function () {
        if (window.__dateInputGuard) return;
        window.__dateInputGuard = true;

        const SETTLE_MS = 900;      // pause after the last keystroke before applying
        const TYPED_WINDOW_MS = 1200; // a change this soon after a key press counts as typing

        const isDateInput = el => el instanceof HTMLInputElement
            && ['date', 'datetime-local', 'month', 'week', 'time'].includes(el.type);

        // A year still being typed reads as 0002 / 0020 / 0202; a pause
        // alone never applies one of those (Enter or leaving the field does).
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

        // Remember when each date field last received a key press.
        document.addEventListener('keydown', function (e) {
            const input = e.target;
            if (!isDateInput(input)) return;
            const state = input.__dateGuard || (input.__dateGuard = {});
            state.lastKey = Date.now();
            if (e.key === 'Enter') release(input);
        }, true);

        // Hold back typing-driven changes until the date is settled.
        document.addEventListener('change', function (e) {
            const input = e.target;
            if (!isDateInput(input)) return;
            const state = input.__dateGuard || (input.__dateGuard = {});
            if (state.passing) return;                                   // our own settled event
            if (!state.lastKey || Date.now() - state.lastKey > TYPED_WINDOW_MS) return; // calendar pick
            e.stopImmediatePropagation();
            state.pending = true;
            clearTimeout(state.timer);
            state.timer = setTimeout(function () { if (looksComplete(input)) release(input); }, SETTLE_MS);
        }, true);

        // Leaving the field applies whatever was typed.
        document.addEventListener('blur', function (e) {
            if (isDateInput(e.target)) release(e.target);
        }, true);
    })();
</script>
