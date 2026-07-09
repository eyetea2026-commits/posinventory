@extends('admin.layout')

@section('header')
    <div class="header-title">
        <h1>User Management</h1>
    </div>
@endsection

@section('content')
<style>
    :root {
        --glass-bg: rgba(15, 23, 42, 0.7);
        --glass-border: rgba(148, 163, 184, 0.1);
        --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    }

    .glass-card {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        box-shadow: var(--glass-shadow);
        backdrop-filter: blur(10px);
        max-width: 700px;
        margin: 0 auto;
    }

    .protected-notice {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 18px;
        background: rgba(239, 68, 68, 0.15);
        border: 1px solid rgba(239, 68, 68, 0.3);
        border-radius: 12px;
        color: #fca5a5;
        margin-bottom: 24px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    .form-group { margin-bottom: 0; }
    .form-group.full-width { grid-column: 1 / -1; }

    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #cbd5e1;
        font-size: 0.9rem;
    }

    .form-label .required { color: #ef4444; }

    .form-input, .form-select {
        width: 100%;
        padding: 14px 16px;
        background: rgba(30, 41, 59, 0.8);
        border: 1px solid rgba(59, 130, 246, 0.2);
        border-radius: 12px;
        color: #f8fafc;
        font-size: 0.95rem;
    }

    .form-input:focus, .form-select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .form-input:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .form-error {
        display: block;
        margin-top: 6px;
        font-size: 0.8rem;
        color: #fca5a5;
    }

    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid var(--glass-border);
    }

    .btn {
        padding: 14px 28px;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 0.95rem;
    }

    .btn-primary {
        background: linear-gradient(135deg, #3b82f6, #10b981);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
    }

    .btn-secondary {
        background: rgba(148, 163, 184, 0.15);
        color: #e2e8f0;
        border: 1px solid rgba(148, 163, 184, 0.2);
    }

    .form-header { margin-bottom: 24px; }
    .form-header h2 { margin: 0 0 8px; font-size: 1.5rem; }
    .form-header p { margin: 0; color: #64748b; }

    @media (max-width: 600px) {
        .form-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="card glass-card">
    @if($user->isProtected())
    <div class="protected-notice">
        <i class="fas fa-shield-alt"></i>
        <span>This is a protected administrator account. Some fields cannot be modified.</span>
    </div>
    @endif

    <div class="form-header">
        <h2>Edit User</h2>
        <p>Update user information</p>
    </div>

    <form method="POST" action="{{ route('admin.users.update', $user) }}" id="userForm">
        @csrf
        @method('PUT')

        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">First Name <span class="required">*</span></label>
                <input type="text" name="first_name" class="form-input" value="{{ old('first_name', $user->first_name) }}" {{ $user->isProtected() ? 'disabled' : '' }} required>
                @error('first_name') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Middle Name</label>
                <input type="text" name="middle_name" class="form-input" value="{{ old('middle_name', $user->middle_name) }}" {{ $user->isProtected() ? 'disabled' : '' }}>
            </div>

            <div class="form-group">
                <label class="form-label">Last Name <span class="required">*</span></label>
                <input type="text" name="last_name" class="form-input" value="{{ old('last_name', $user->last_name) }}" {{ $user->isProtected() ? 'disabled' : '' }} required>
                @error('last_name') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Age</label>
                <input type="number" name="age" class="form-input" value="{{ old('age', $user->age) }}" min="1" max="150" {{ $user->isProtected() ? 'disabled' : '' }}>
            </div>

            <div class="form-group full-width">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-input" value="{{ old('address', $user->address) }}" {{ $user->isProtected() ? 'disabled' : '' }}>
            </div>

            <div class="form-group">
                <label class="form-label">Contact Number <span class="required">*</span></label>
                <input type="text" name="contact_number" class="form-input" value="{{ old('contact_number', $user->contact_number) }}" {{ $user->isProtected() ? 'disabled' : '' }} required>
                @error('contact_number') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Gender</label>
                <select name="gender" class="form-select" {{ $user->isProtected() ? 'disabled' : '' }}>
                    <option value="">Select Gender</option>
                    <option value="Male" {{ old('gender', $user->gender) == 'Male' ? 'selected' : '' }}>Male</option>
                    <option value="Female" {{ old('gender', $user->gender) == 'Female' ? 'selected' : '' }}>Female</option>
                    <option value="Other" {{ old('gender', $user->gender) == 'Other' ? 'selected' : '' }}>Other</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Email Address <span class="required">*</span></label>
                <input type="email" name="email" class="form-input" value="{{ old('email', $user->email) }}" {{ $user->isProtected() ? 'disabled' : '' }} required>
                @error('email') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Role <span class="required">*</span></label>
                <select name="role_id" class="form-select" {{ $user->isProtected() ? 'disabled' : '' }} required>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" {{ old('role_id', $user->role_id) == $role->id ? 'selected' : '' }}>{{ ucfirst($role->role_name) }}</option>
                    @endforeach
                </select>
                @error('role_id') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-group full-width">
                <label class="form-label">Username <span class="required">*</span></label>
                <input type="text" name="name" class="form-input" value="{{ old('name', $user->name) }}" {{ $user->isProtected() ? 'disabled' : '' }} required>
                @error('name') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" name="password" class="form-input" {{ $user->isProtected() ? 'disabled' : '' }}>
                @error('password') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="password_confirmation" class="form-input" {{ $user->isProtected() ? 'disabled' : '' }}>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="button" class="btn btn-primary" onclick="confirmUpdate()">
                <i class="fas fa-save"></i> Update User
            </button>
        </div>
    </form>
</div>

<script>
    const form = document.getElementById('userForm');
    let formChanged = false;

    form.querySelectorAll('input, select').forEach(input => {
        input.addEventListener('change', () => formChanged = true);
        input.addEventListener('input', () => formChanged = true);
    });

    function confirmUpdate() {
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        Swal.fire({
            title: 'Confirm Update',
            text: 'Are you sure you want to save the changes made to this user?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    }

    @if(session('status'))
        Swal.fire({
            title: 'Success',
            text: '{{ session('status') }}',
            icon: 'success',
            confirmButtonColor: '#10b981',
            timer: 3000,
            timerProgressBar: true
        });
    @endif
    @if(session('error'))
        Swal.fire({
            title: 'Error',
            text: '{{ session('error') }}',
            icon: 'error',
            confirmButtonColor: '#ef4444'
        });
    @endif
</script>
@endsection