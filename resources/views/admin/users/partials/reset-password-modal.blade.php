{{-- Two things share this partial:
       1. #adminCredentialsModal ("Enter Your Credentials") — a GENERIC
          "confirm it's you" gate. window.openAdminCredentialsGate(onVerified,
          options) re-enters the ACTING admin's own password, verifies it
          against admin.users.verify-password, then calls onVerified(password)
          and closes. Used by both:
            a) Reset Password (window.openResetPasswordModal below), which
               chains into step 2 (#resetPasswordModal) on success.
            b) Update User for an Administrator target (see index.blade.php /
               edit.blade.php), which submits the Edit User form with the
               verified password attached as current_password.
       2. #resetPasswordModal (New Password / Confirm Password) — only
          reachable after the gate succeeds via the Reset Password path.
          Its Save posts the already-verified admin password back to
          admin.users.reset-password, which re-checks it server-side
          (defense in depth — a client that skips the gate and posts
          straight to reset-password still can't get through without the
          real admin password).

     Kept entirely separate from the Edit User form/modal on purpose: this
     partial is included directly in the page (index.blade.php /
     edit.blade.php), never injected via .innerHTML like the Edit User form
     fields are — a <script> tag inside HTML set via .innerHTML never
     executes, which was the actual bug behind "Reset Password doesn't
     work" before this. --}}
@include('admin.partials.modal-styles')

<div id="adminCredentialsModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="adminCredentialsModalTitle" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="adminCredentialsModalTitle"><i class="fas fa-shield-halved"></i> Enter Your Credentials</h2>
            <button type="button" class="modal-close" onclick="closeAdminCredentialsModal()" aria-label="Close">&times;</button>
        </div>

        <div id="adminCredentialsGeneralError" class="form-error-banner" style="display:none;" role="alert"></div>

        <form id="adminCredentialsForm" onsubmit="return false;">
            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="form-label">Your Password <span class="required">*</span></label>
                    <input type="password" id="adminCredentialsPassword" class="form-input" autocomplete="current-password" required>
                    <span class="form-hint" id="adminCredentialsHint" style="display:block; margin-top:6px; color: var(--text-secondary); font-size:0.85rem;">For security, confirm it's you before continuing.</span>
                    <span class="form-error" id="error-admin-credentials"></span>
                </div>
            </div>
        </form>

        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" id="adminCredentialsCloseBtn">Close</button>
            <button type="button" class="btn btn-primary" id="adminCredentialsConfirmBtn">
                <i class="fas fa-check"></i> Confirm
            </button>
        </div>
    </div>
</div>

<div id="resetPasswordModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="resetPasswordModalTitle" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="resetPasswordModalTitle"><i class="fas fa-key"></i> Reset Password</h2>
            <button type="button" class="modal-close" onclick="closeResetPasswordModal()" aria-label="Close">&times;</button>
        </div>

        <div id="resetPasswordGeneralError" class="form-error-banner" style="display:none;" role="alert"></div>

        <form id="resetPasswordForm" onsubmit="return false;">
            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="form-label">New Password <span class="required">*</span></label>
                    <input type="password" id="resetPasswordNew" class="form-input" required>
                    <span class="form-error" id="error-reset-password"></span>
                </div>
                <div class="form-group full-width">
                    <label class="form-label">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="resetPasswordConfirm" class="form-input" required>
                </div>
            </div>
        </form>

        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" id="resetPasswordCancelBtn">Cancel</button>
            <button type="button" class="btn btn-primary" id="resetPasswordSaveBtn">
                <i class="fas fa-save"></i> Save
            </button>
        </div>
    </div>
</div>

<script>
    let resetPasswordUserId = null;
    let resetPasswordLastFocused = null;
    let verifiedAdminPassword = null;

    // ---- Generic "Enter Your Credentials" gate ----

    let adminCredentialsLastFocused = null;
    let adminCredentialsOnVerified = null;
    let adminCredentialsOnCancel = null;

    // Opens the "Enter Your Credentials" popup, verifies the ACTING admin's
    // own password against admin.users.verify-password, then calls
    // onVerified(password) — used by both Reset Password (chains into step
    // 2 below) and Update User for an Administrator target (see
    // index.blade.php / edit.blade.php). options.hint customizes the
    // shown reason; options.onCancel fires if the admin closes the popup
    // without confirming (e.g. to re-enable a disabled Save button).
    window.openAdminCredentialsGate = function (onVerified, options) {
        options = options || {};
        adminCredentialsOnVerified = onVerified;
        adminCredentialsOnCancel = options.onCancel || null;
        adminCredentialsLastFocused = document.activeElement;

        document.getElementById('adminCredentialsPassword').value = '';
        document.getElementById('adminCredentialsPassword').classList.remove('error');
        document.getElementById('error-admin-credentials').textContent = '';
        document.getElementById('adminCredentialsHint').textContent = options.hint || 'For security, confirm it\'s you before continuing.';
        hideAdminCredentialsGeneralError();
        resetAdminCredentialsConfirmBtn();

        const modal = document.getElementById('adminCredentialsModal');
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        void modal.offsetHeight;
        requestAnimationFrame(function () { modal.classList.add('active'); });
        document.addEventListener('keydown', handleAdminCredentialsModalKeydown);
        document.getElementById('adminCredentialsPassword').focus();
    };

    // Reset Password's own entry point — just chains into step 2 as its
    // "verified" callback.
    window.openResetPasswordModal = function (userId) {
        resetPasswordUserId = userId;
        window.openAdminCredentialsGate(function (verifiedPassword) {
            openResetPasswordFormModal(userId, verifiedPassword);
        }, {
            hint: 'For security, confirm it\'s you before resetting another user\'s password.',
        });
    };

    // Pure UI teardown, used both after a successful verification (the
    // confirm handler below calls this itself before invoking onVerified)
    // and by an explicit cancel — cancelAdminCredentialsGate() is what
    // actually fires onCancel, so a successful close never double-fires it.
    window.closeAdminCredentialsModal = function () {
        const modal = document.getElementById('adminCredentialsModal');
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
        document.removeEventListener('keydown', handleAdminCredentialsModalKeydown);
        setTimeout(function () { modal.style.display = 'none'; }, 250);
        document.body.style.overflow = '';
        resetPasswordUserId = null;
        verifiedAdminPassword = null;
        adminCredentialsOnVerified = null;
        adminCredentialsOnCancel = null;
        if (adminCredentialsLastFocused && typeof adminCredentialsLastFocused.focus === 'function') {
            adminCredentialsLastFocused.focus();
        }
    };

    function cancelAdminCredentialsGate() {
        const onCancel = adminCredentialsOnCancel;
        window.closeAdminCredentialsModal();
        if (onCancel) onCancel();
    }

    function handleAdminCredentialsModalKeydown(e) {
        if (e.key === 'Escape') cancelAdminCredentialsGate();
    }

    document.getElementById('adminCredentialsModal').addEventListener('mousedown', function (e) {
        if (e.target === this) cancelAdminCredentialsGate();
    });
    document.getElementById('adminCredentialsCloseBtn').addEventListener('click', function () {
        cancelAdminCredentialsGate();
    });

    function showAdminCredentialsGeneralError(message) {
        const banner = document.getElementById('adminCredentialsGeneralError');
        banner.textContent = message;
        banner.style.display = 'flex';
    }

    function hideAdminCredentialsGeneralError() {
        const banner = document.getElementById('adminCredentialsGeneralError');
        banner.style.display = 'none';
        banner.textContent = '';
    }

    function resetAdminCredentialsConfirmBtn() {
        const btn = document.getElementById('adminCredentialsConfirmBtn');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Confirm';
    }

    document.getElementById('adminCredentialsConfirmBtn').addEventListener('click', function () {
        const passwordInput = document.getElementById('adminCredentialsPassword');
        const password = passwordInput.value;

        passwordInput.classList.remove('error');
        document.getElementById('error-admin-credentials').textContent = '';
        hideAdminCredentialsGeneralError();

        if (!password) {
            passwordInput.classList.add('error');
            document.getElementById('error-admin-credentials').textContent = 'Please enter your password.';
            passwordInput.focus();
            return;
        }

        const btn = document.getElementById('adminCredentialsConfirmBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Verifying...';

        fetch('{{ route('admin.users.verify-password') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ password: password }),
        })
            .then(async function (response) {
                if (response.status === 422) {
                    const data = await response.json();
                    const message = data.errors?.password?.[0] || 'Your password is incorrect.';
                    document.getElementById('error-admin-credentials').textContent = message;
                    passwordInput.classList.add('error');
                    passwordInput.focus();
                    resetAdminCredentialsConfirmBtn();
                    return;
                }

                if (!response.ok) {
                    showAdminCredentialsGeneralError('Something went wrong. Please try again.');
                    resetAdminCredentialsConfirmBtn();
                    return;
                }

                resetAdminCredentialsConfirmBtn();
                // Capture BEFORE closing -- closeAdminCredentialsModal()
                // clears adminCredentialsOnVerified as part of its normal
                // teardown, and this deliberately bypasses
                // cancelAdminCredentialsGate() so onCancel never fires here.
                const callback = adminCredentialsOnVerified;
                const verified = password;
                window.closeAdminCredentialsModal();
                if (callback) callback(verified);
            })
            .catch(function () {
                showAdminCredentialsGeneralError('A network error occurred. Please try again.');
                resetAdminCredentialsConfirmBtn();
            });
    });

    // ---- Step 2: New Password / Confirm Password ----

    function openResetPasswordFormModal(userId, verifiedPassword) {
        resetPasswordUserId = userId;
        verifiedAdminPassword = verifiedPassword;
        resetPasswordLastFocused = document.activeElement;

        document.getElementById('resetPasswordNew').value = '';
        document.getElementById('resetPasswordConfirm').value = '';
        document.getElementById('resetPasswordNew').classList.remove('error');
        document.getElementById('resetPasswordConfirm').classList.remove('error');
        document.getElementById('error-reset-password').textContent = '';
        hideResetPasswordGeneralError();
        resetResetPasswordSaveBtn();

        const modal = document.getElementById('resetPasswordModal');
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        void modal.offsetHeight;
        requestAnimationFrame(function () { modal.classList.add('active'); });
        document.addEventListener('keydown', handleResetPasswordModalKeydown);
        document.getElementById('resetPasswordNew').focus();
    }

    window.closeResetPasswordModal = function () {
        const modal = document.getElementById('resetPasswordModal');
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
        document.removeEventListener('keydown', handleResetPasswordModalKeydown);
        setTimeout(function () { modal.style.display = 'none'; }, 250);
        document.body.style.overflow = '';
        resetPasswordUserId = null;
        verifiedAdminPassword = null;
        if (resetPasswordLastFocused && typeof resetPasswordLastFocused.focus === 'function') {
            resetPasswordLastFocused.focus();
        }
    };

    function handleResetPasswordModalKeydown(e) {
        if (e.key === 'Escape') window.closeResetPasswordModal();
    }

    document.getElementById('resetPasswordModal').addEventListener('mousedown', function (e) {
        if (e.target === this) window.closeResetPasswordModal();
    });
    document.getElementById('resetPasswordCancelBtn').addEventListener('click', function () {
        window.closeResetPasswordModal();
    });

    function showResetPasswordGeneralError(message) {
        const banner = document.getElementById('resetPasswordGeneralError');
        banner.textContent = message;
        banner.style.display = 'flex';
    }

    function hideResetPasswordGeneralError() {
        const banner = document.getElementById('resetPasswordGeneralError');
        banner.style.display = 'none';
        banner.textContent = '';
    }

    function resetResetPasswordSaveBtn() {
        const btn = document.getElementById('resetPasswordSaveBtn');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save';
    }

    document.getElementById('resetPasswordSaveBtn').addEventListener('click', function () {
        const newPasswordInput = document.getElementById('resetPasswordNew');
        const confirmInput = document.getElementById('resetPasswordConfirm');
        const newPassword = newPasswordInput.value;
        const confirmPassword = confirmInput.value;

        newPasswordInput.classList.remove('error');
        confirmInput.classList.remove('error');
        document.getElementById('error-reset-password').textContent = '';
        hideResetPasswordGeneralError();

        if (!newPassword) {
            newPasswordInput.classList.add('error');
            document.getElementById('error-reset-password').textContent = 'Please enter a new password.';
            newPasswordInput.focus();
            return;
        }

        if (newPassword !== confirmPassword) {
            confirmInput.classList.add('error');
            showResetPasswordGeneralError('New Password and Confirm Password must match.');
            confirmInput.focus();
            return;
        }

        window.confirmAction({
            title: 'Confirm Password Reset',
            text: 'Are you sure you want to reset this user\'s password?',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            const btn = document.getElementById('resetPasswordSaveBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Saving...';

            fetch('{{ url('admin/users') }}/' + resetPasswordUserId + '/reset-password', {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    current_password: verifiedAdminPassword,
                    password: newPassword,
                    password_confirmation: confirmPassword,
                }),
            })
                .then(async function (response) {
                    if (response.status === 422) {
                        const data = await response.json();
                        const currentPasswordError = data.errors?.current_password?.[0];
                        if (currentPasswordError) {
                            // The verified admin password was rejected on
                            // the authoritative re-check (e.g. it changed
                            // mid-flow) -- send the admin back to step 1
                            // rather than silently failing here. Capture the
                            // user id BEFORE closing -- closeResetPasswordModal()
                            // clears resetPasswordUserId as part of its
                            // normal teardown.
                            const userId = resetPasswordUserId;
                            window.closeResetPasswordModal();
                            window.openResetPasswordModal(userId);
                            showAdminCredentialsGeneralError(currentPasswordError);
                            return;
                        }
                        const message = data.errors?.password?.[0] || 'Please check the password requirements.';
                        document.getElementById('error-reset-password').textContent = message;
                        newPasswordInput.classList.add('error');
                        resetResetPasswordSaveBtn();
                        return;
                    }

                    if (!response.ok) {
                        showResetPasswordGeneralError('Something went wrong. Please try again.');
                        resetResetPasswordSaveBtn();
                        return;
                    }

                    window.closeResetPasswordModal();
                    Swal.fire({
                        title: 'Success',
                        text: 'Password reset successfully.',
                        icon: 'success',
                        confirmButtonColor: '#10b981',
                        timer: 2500,
                        showConfirmButton: false,
                    });
                })
                .catch(function () {
                    showResetPasswordGeneralError('A network error occurred. Please try again.');
                    resetResetPasswordSaveBtn();
                });
        });
    });
</script>
