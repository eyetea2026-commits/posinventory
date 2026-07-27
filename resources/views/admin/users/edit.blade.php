@extends('admin.layout')

@section('header')
    <div class="header-title">
        <h1>User Management</h1>
    </div>
@endsection

@section('content')
@include('admin.users.partials.user-form-styles')
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
        padding: 32px;
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
</style>

<div class="card glass-card">
    <div class="form-header">
        <h2>Edit User</h2>
        <p>Update user information</p>
    </div>

    <form method="POST" action="{{ route('admin.users.update', $user) }}" id="userForm">
        @csrf
        @method('PUT')

        @include('admin.users.partials.user-form-fields', ['roles' => $roles, 'user' => $user])

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
