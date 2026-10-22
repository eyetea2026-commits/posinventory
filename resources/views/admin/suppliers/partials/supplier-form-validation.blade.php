{{-- Supplier form checks, run when Save is clicked (Add/Edit Supplier popups
     and the standalone pages). Each problem is shown under its field in red
     and the form isn't sent. The Contact Number box also formats itself as
     it's typed: digits only (letters are dropped), shown as 0912-345-6789.
     The server checks the same rules (SupplierController). Include once per
     page. --}}
<style>
    .supplier-field-invalid { border-color: #ef4444 !important; background: rgba(239, 68, 68, 0.12) !important; }
</style>
<script>
    (function () {
        const SUPPLIER_FORMS = ['addSupplierForm', 'editSupplierForm', 'supplierForm'];
        const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

        // Keeps digits only (at most 11) and adds the dashes: 0912-345-6789.
        window.formatSupplierContactNumber = function (value) {
            const digits = String(value || '').replace(/\D/g, '').slice(0, 11);
            if (digits.length <= 4) return digits;
            if (digits.length <= 7) return digits.slice(0, 4) + '-' + digits.slice(4);
            return digits.slice(0, 4) + '-' + digits.slice(4, 7) + '-' + digits.slice(7);
        };

        function messageFor(name, value) {
            const v = value.trim();
            switch (name) {
                case 'SupplierName':
                    return v === '' ? 'Supplier Name is required.' : '';
                case 'ContactNumber':
                    if (v === '') return 'Contact Number is required.';
                    return /^09\d{2}-\d{3}-\d{4}$/.test(v) ? '' : 'Enter the contact number as 0912-345-6789 (11 digits starting with 09).';
                case 'Email':
                    if (v === '') return 'Email is required.';
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
                field.value = window.formatSupplierContactNumber(field.value);
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
