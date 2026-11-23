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
        }
        var errorRow = form.querySelector('[data-role="name-duplicate-error-row"]');
        if (errorRow) {
            errorRow.style.display = message ? 'block' : 'none';
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
            body: JSON.stringify({ first_name: first, middle_name: middle, last_name: last, exclude_id: form.dataset.excludeId || null })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (requestId !== nameCheckInFlight) return;
                nameDuplicate = !!(data && data.duplicate);
                setNameError(nameDuplicate ? 'A user with this name already exists. Duplicate names are not allowed.' : '');
            })
            .catch(function () {
            });
    }

    function resetSubmitButton() {
        if (!submitBtn) return;
        submitBtn.disabled = false;
        submitBtn.innerHTML = idleLabel;
    }

    function confirmSave() {
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        var passwordInput = form.querySelector('input[name="password"]');
        var passwordConfirmInput = form.querySelector('input[name="password_confirmation"]');
        var password = passwordInput ? passwordInput.value : null;
        var passwordConfirm = passwordConfirmInput ? passwordConfirmInput.value : null;

        if (passwordInput && password !== passwordConfirm) {
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

        var confirmTitle = typeof options.confirmTitle === 'function'
            ? options.confirmTitle()
            : (options.confirmTitle || 'Add User Confirmation');
        var confirmHtml = options.confirmHtml
            ? options.confirmHtml(fullName)
            : 'Are you sure you want to add <strong>' + fullName + '</strong> as a new user?<br><br>This account will be created and can be used to log in immediately.';
        var confirmButtonText = options.confirmButtonText || 'Yes, Add User';
        var submittingLabel = options.submittingLabel || '<span class="spinner"></span> Saving...';

        Swal.fire({
            title: confirmTitle,
            html: confirmHtml,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: confirmButtonText,
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
                submitBtn.innerHTML = submittingLabel;
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

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.password-toggle-btn').forEach(function (btn) {
        var input = btn.previousElementSibling;
        if (!input || btn.dataset.toggleWired) return;
        btn.addEventListener('click', function () {
            var showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            btn.innerHTML = showing ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        });
    });

    var passwordRules = {
        length: function (v) { return v.length >= 8; },
        uppercase: function (v) { return /[A-Z]/.test(v); },
        number: function (v) { return /[0-9]/.test(v); },
        special: function (v) { return /[^A-Za-z0-9]/.test(v); }
    };

    function wireRequirementsList(inputId, listId) {
        var input = document.getElementById(inputId);
        var list = document.getElementById(listId);
        if (!input || !list) return;
        input.addEventListener('input', function () {
            Object.keys(passwordRules).forEach(function (rule) {
                var li = list.querySelector('[data-rule="' + rule + '"]');
                if (li) li.classList.toggle('met', passwordRules[rule](input.value));
            });
        });
    }

    wireRequirementsList('password', 'password-requirements');
    wireRequirementsList('resetPasswordNew', 'reset-password-requirements');
});
</script>
