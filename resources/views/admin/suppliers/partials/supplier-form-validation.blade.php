{{-- Supplier form checks, run when Save is clicked (Add/Edit Supplier popups
     and the standalone pages). Each problem is shown under its field in red
     and the form isn't sent. The Contact Number box drops letters as it's
     typed; on Save each number (split on "/") is written as 0912-345-6789,
     (02) 8123-4567 or (082) 295-7595. The server checks the same rules
     (SupplierController). Include once per page. --}}
<style>
    .supplier-field-invalid { border-color: #ef4444 !important; background: rgba(239, 68, 68, 0.12) !important; }
</style>
<script>
    (function () {
        const SUPPLIER_FORMS = ['addSupplierForm', 'editSupplierForm', 'supplierForm'];
        const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

        const PHONE = '(09\\d{2}-\\d{3}-\\d{4}|\\(02\\) \\d{4}-\\d{4}|\\(0[1-8]\\d\\) \\d{3}-\\d{4})';
        const CONTACT_NUMBER_PATTERN = new RegExp('^' + PHONE + '( / ' + PHONE + '){0,2}$');

        // While typing: letters are dropped, everything else stays as typed.
        window.cleanSupplierContactNumber = function (value) {
            return String(value || '').replace(/[^0-9+()\-\/ ]/g, '');
        };

        // On Save: each number written as 0912-345-6789, (02) 8123-4567 or
        // (082) 295-7595, joined with " / " (same as the server).
        window.formatSupplierContactNumber = function (value) {
            return String(value || '').split(/[\/,;]/).map(function (part) {
                let digits = part.replace(/\D/g, '');
                if (digits.length === 12 && digits.indexOf('63') === 0) digits = '0' + digits.slice(2);
                if (digits.length === 11 && digits.indexOf('09') === 0) return digits.slice(0, 4) + '-' + digits.slice(4, 7) + '-' + digits.slice(7);
                if (digits.length === 10 && digits.indexOf('02') === 0) return '(02) ' + digits.slice(2, 6) + '-' + digits.slice(6);
                if (digits.length === 10 && digits[0] === '0' && digits.indexOf('09') !== 0) return '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
                return digits;
            }).filter(Boolean).join(' / ');
        };

        function messageFor(name, value) {
            const v = value.trim();
            switch (name) {
                case 'SupplierName':
                    return v === '' ? 'Supplier Name is required.' : '';
                case 'ContactNumber':
                    if (v === '') return 'Contact Number is required.';
                    return CONTACT_NUMBER_PATTERN.test(v) ? '' : 'Enter a mobile number as 0912-345-6789 or a landline as (02) 8123-4567. Separate two numbers with "/".';
                case 'Email':
                    // Optional; checked only when filled in.
                    if (v === '') return '';
                    return EMAIL_PATTERN.test(v) ? '' : 'Please enter a valid email address.';
                case 'Address':
                    return v === '' ? 'Address is required.' : '';
                default:
                    return '';
            }
        }

        function errorSpan(form, name) {
            return form.querySelector('#error-' + name) || (function () {
                const field = form.querySelector('[name="' + name + '"]');
                let span = field && field.parentElement.querySelector('.form-error');
                if (!span && field) {
                    span = document.createElement('span');
                    span.className = 'form-error';
                    field.insertAdjacentElement('afterend', span);
                }
                return span;
            })();
        }

        function showFieldMessage(form, field, message) {
            field.classList.toggle('supplier-field-invalid', message !== '');
            field.classList.toggle('error', message !== '');
            const span = errorSpan(form, field.name);
            if (span) span.textContent = message;
        }

        // Checks every field; shows each problem under its field. Returns
        // true when the form can be saved.
        window.validateSupplierForm = function (form) {
            let firstInvalid = null;
            ['SupplierName', 'ContactNumber', 'Email', 'Address'].forEach(function (name) {
                const field = form.querySelector('[name="' + name + '"]');
                if (!field) return;
                if (name === 'ContactNumber') field.value = window.formatSupplierContactNumber(field.value);
                const message = messageFor(name, field.value);
                showFieldMessage(form, field, message);
                if (message && !firstInvalid) firstInvalid = field;
            });
            if (firstInvalid) firstInvalid.focus();

            return firstInvalid === null;
        };

        function isSupplierForm(form) {
            return form && SUPPLIER_FORMS.includes(form.id);
        }

        // Format the number as it's typed; clear a field's message once it's fixed.
        document.addEventListener('input', function (e) {
            const field = e.target;
            if (!field.form || !isSupplierForm(field.form)) return;
            if (field.name === 'ContactNumber') {
                field.value = window.cleanSupplierContactNumber(field.value);
            }
            if (field.classList.contains('supplier-field-invalid')) {
                showFieldMessage(field.form, field, messageFor(field.name, field.value));
            }
        });

        // The standalone Add/Edit pages submit a normal form.
        document.addEventListener('submit', function (e) {
            if (e.target.id === 'supplierForm' && !window.validateSupplierForm(e.target)) {
                e.preventDefault();
            }
        }, true);
    })();
</script>
