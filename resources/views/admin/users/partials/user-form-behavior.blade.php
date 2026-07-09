{{-- Shared "Add User" form behavior: HTML5 validity check, live duplicate-name
     check (reuses the existing admin.users.check-name AJAX endpoint), the
     password-match check, and the "confirm before saving" dialog.

     This does NOT decide how the form is actually submitted — that's the
     caller's job via options.onConfirmedSubmit, so the exact same validation
     rules can back both the standalone create page (real form submit) and the
     Add User modal (AJAX submit) without duplicating this logic twice. --}}
<script>
window.initUserAddForm = function (formId, options) {
    options = options || {};
    var form = document.getElementById(formId);
    if (!form) return null;

    var submitBtn = options.submitBtn || null;
    var idleLabel = submitBtn ? submitBtn.innerHTML : '';
    var firstNameInput = form.querySelector('input[name="first_name"]');
    var middleNameInput = form.querySelector('input[name="middle_name"]');
    var lastNameInput = form.querySelector('input[name="last_name"]');
    var formChanged = false;
    var nameDuplicate = false;
    var nameCheckInFlight = 0;

    form.addEventListener('submit', function (e) { e.preventDefault(); });

    form.querySelectorAll('input, select').forEach(function (input) {
        input.addEventListener('change', function () { formChanged = true; });
        input.addEventListener('input', function () {
            formChanged = true;
            if (input === firstNameInput || input === middleNameInput || input === lastNameInput) {
                scheduleNameDuplicateCheck();
            }
        });
    });

    function setNameError(message) {
        var errorEl = form.querySelector('[data-role="name-duplicate-error"]');
        if (errorEl) {
            errorEl.textContent = message || '';
            errorEl.style.display = message ? 'block' : 'none';
        }
        [firstNameInput, middleNameInput, lastNameInput].forEach(function (el) {
            if (el) el.classList.toggle('error', !!message);
        });
    }

    function scheduleNameDuplicateCheck() {
        nameDuplicate = false;
        setNameError('');
        var first = firstNameInput.value.trim();
        var last = lastNameInput.value.trim();
        if (!first || !last) return;
        var requestId = ++nameCheckInFlight;
        clearTimeout(form.__nameCheckTimer);
        form.__nameCheckTimer = setTimeout(function () {
            checkNameDuplicate(requestId);
        }, 350);
    }

    function checkNameDuplicate(requestId) {
        var first = firstNameInput.value.trim();
        var middle = middleNameInput.value.trim();
        var last = lastNameInput.value.trim();
        if (!first || !last) return;
        fetch('{{ route('admin.users.check-name') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ first_name: first, middle_name: middle, last_name: last })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (requestId !== nameCheckInFlight) return;
                nameDuplicate = !!(data && data.duplicate);
                setNameError(nameDuplicate ? 'A user with this name already exists. Duplicate names are not allowed.' : '');
            })
            .catch(function () {
                // Network errors are non-fatal — the server-side check catches
                // duplicates when the form is finally submitted.
            });
    }

    function resetSubmitButton() {
        if (!submitBtn) return;
        submitBtn.disabled = false;
        submitBtn.innerHTML = idleLabel;
    }

    function confirmSave() {
        // The submit button is type="button", so HTML5 validation does not run
        // automatically — check it explicitly here.
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        var password = form.querySelector('input[name="password"]').value;
        var passwordConfirm = form.querySelector('input[name="password_confirmation"]').value;

        if (password !== passwordConfirm) {
            Swal.fire({
                title: 'Error',
                text: 'Password and Confirm Password must match.',
                icon: 'error',
                confirmButtonColor: '#ef4444'
            });
            return;
        }

        var fullName = (firstNameInput.value.trim() + ' ' + lastNameInput.value.trim()).trim() || 'this user';

        if (nameDuplicate) {
            Swal.fire({
                title: 'Duplicate Name',
                text: 'A user with the name "' + fullName + '" already exists. Please use a different name.',
                icon: 'error',
                confirmButtonColor: '#ef4444'
            });
            return;
        }

        Swal.fire({
            title: 'Add User Confirmation',
            html: 'Are you sure you want to add <strong>' + fullName + '</strong> as a new user?<br><br>This account will be created and can be used to log in immediately.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Add User',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b',
            reverseButtons: true,
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then(function (result) {
            if (!result.isConfirmed) {
                resetSubmitButton();
                return;
            }
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner"></span> Saving...';
            }
            formChanged = false;
            if (options.onConfirmedSubmit) options.onConfirmedSubmit(resetSubmitButton);
        });
    }

    function confirmCancel() {
        if (options.onCancel) options.onCancel(formChanged);
    }

    return {
        confirmSave: confirmSave,
        confirmCancel: confirmCancel,
        isChanged: function () { return formChanged; },
        markUnchanged: function () { formChanged = false; },
        resetSubmitButton: resetSubmitButton
    };
};
</script>
