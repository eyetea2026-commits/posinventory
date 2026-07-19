{{-- Shared money-formatting helpers used across every admin and cashier
     view: consistent ₱ + comma-grouped, always-2-decimal display formatting,
     plus live thousands-separator formatting for money <input> fields (which
     use type="text" instead of type="number" so commas can be displayed
     while typing), with the caret position preserved as the user types. --}}
<script>
    window.parseMoney = function (value) {
        var n = parseFloat(String(value == null ? '' : value).replace(/[^0-9.\-]/g, ''));
        return isNaN(n) ? 0 : n;
    };

    window.formatMoneyPlain = function (value) {
        return window.parseMoney(value).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    };

    window.formatPeso = function (value) {
        return '₱' + window.formatMoneyPlain(value);
    };

    // Binds live comma-formatting to a text <input> representing a money
    // amount. Read the raw number back with parseMoney(input.value).
    window.attachMoneyInput = function (input) {
        if (!input || input.dataset.moneyBound) return;
        input.dataset.moneyBound = '1';
        input.setAttribute('inputmode', 'decimal');
        input.setAttribute('autocomplete', 'off');

        function group(raw) {
            var dot = raw.indexOf('.');
            var intPart = (dot === -1 ? raw : raw.slice(0, dot)).replace(/^0+(?=\d)/, '');
            var decPart = dot === -1 ? '' : '.' + raw.slice(dot + 1).slice(0, 2);
            return intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + decPart;
        }

        function reformat() {
            var caret = input.selectionStart;
            var digitsBeforeCaret = input.value.slice(0, caret).replace(/[^0-9.]/g, '').length;

            var cleaned = input.value.replace(/[^0-9.]/g, '');
            var firstDot = cleaned.indexOf('.');
            if (firstDot !== -1) {
                cleaned = cleaned.slice(0, firstDot + 1) + cleaned.slice(firstDot + 1).replace(/\./g, '');
            }

            input.value = group(cleaned);

            var seen = 0, pos = 0;
            for (; pos < input.value.length && seen < digitsBeforeCaret; pos++) {
                if (/[0-9.]/.test(input.value[pos])) seen++;
            }
            input.setSelectionRange(pos, pos);
        }

        if (input.value) input.value = group(input.value.replace(/[^0-9.]/g, ''));
        input.addEventListener('input', reformat);
    };
</script>
