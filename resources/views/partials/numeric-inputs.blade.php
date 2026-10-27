{{-- Number fields (quantities, thresholds, prices) on admin and cashier
     pages take digits only. Browsers still let "e", "E", "+" and "-" into a
     type="number" box (as in 1e5); this blocks those and any other letter
     as it's typed or pasted, including in boxes added later by JavaScript
     (POS cart, refunds). A minus sign is only allowed where the field's min
     is below zero. data-max-digits="3" caps a box at that many whole digits
     (maxlength doesn't apply to number inputs). The text-type money boxes
     strip letters themselves (window.attachMoneyInput). --}}
<script>
    (function () {
        if (window.__numericInputs) return;
        window.__numericInputs = true;

        function isNumberInput(el) {
            return el instanceof HTMLInputElement && el.type === 'number';
        }

        function allowsNegative(input) {
            return input.min !== '' && parseFloat(input.min) < 0;
        }

        function isAllowed(input, text) {
            return (allowsNegative(input) ? /^[0-9.\-]*$/ : /^[0-9.]*$/).test(text);
        }

        function clean(input, text) {
            var cleaned = String(text).replace(allowsNegative(input) ? /[^0-9.\-]/g : /[^0-9.]/g, '');
            var firstDot = cleaned.indexOf('.');
            return firstDot === -1 ? cleaned : cleaned.slice(0, firstDot + 1) + cleaned.slice(firstDot + 1).replace(/\./g, '');
        }

        document.addEventListener('keydown', function (e) {
            var input = e.target;
            if (!isNumberInput(input) || e.ctrlKey || e.metaKey || e.altKey || e.key.length !== 1) return;
            if (!isAllowed(input, e.key)) e.preventDefault();
        }, true);

        // Typing on phones (where keydown doesn't name the key) and dropping text.
        document.addEventListener('beforeinput', function (e) {
            var input = e.target;
            if (!isNumberInput(input) || e.inputType === 'insertFromPaste' || typeof e.data !== 'string') return;
            if (!isAllowed(input, e.data)) e.preventDefault();
        }, true);

        document.addEventListener('paste', function (e) {
            var input = e.target;
            if (!isNumberInput(input)) return;
            var text = (e.clipboardData || window.clipboardData).getData('text');
            if (isAllowed(input, text)) return;
            e.preventDefault();
            input.value = clean(input, text);
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }, true);

        document.addEventListener('input', function (e) {
            var input = e.target;
            if (!isNumberInput(input) || !input.dataset.maxDigits) return;
            var maxDigits = parseInt(input.dataset.maxDigits, 10);
            var parts = String(input.value).split('.');
            if (parts[0].replace('-', '').length > maxDigits) {
                parts[0] = parts[0].slice(0, maxDigits + (parts[0].charAt(0) === '-' ? 1 : 0));
                input.value = parts.join('.');
            }
        }, true);
    })();
</script>
