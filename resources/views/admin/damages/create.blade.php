@extends('admin.layout')

@section('title', 'Record Damage - CCTV Express')

@section('header')
    <div class="header-title">
        <h1>Damage Management</h1>
    </div>
@endsection

@section('content')
<style>
    :root {
        --glass-bg: rgba(15, 23, 42, 0.7);
        --glass-border: rgba(148, 163, 184, 0.1);
        --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        --primary: #3b82f6;
        --success: #10b981;
    }

    .glass-card {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        box-shadow: var(--glass-shadow);
        backdrop-filter: blur(10px);
        padding: 32px;
        max-width: 800px;
        margin: 0 auto;
    }

    .content-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }

    .content-header h1 {
        margin: 0;
        font-size: 1.75rem;
        color: var(--text-primary);
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 24px;
    }

    .form-group {
        margin-bottom: 0;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #cbd5e1;
        font-size: 0.9rem;
    }

    .form-label .required {
        color: #ef4444;
    }

    .form-input, .form-select {
        width: 100%;
        padding: 14px 16px;
        background: rgba(30, 41, 59, 0.8);
        border: 1px solid rgba(59, 130, 246, 0.2);
        border-radius: 12px;
        color: #f8fafc;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .form-input:focus, .form-select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }

    .form-select {
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        background-size: 20px;
        padding-right: 40px;
    }

    textarea.form-input {
        min-height: 90px;
        resize: vertical;
    }

    .form-error {
        display: block;
        margin-top: 8px;
        color: #fca5a5;
        font-size: 0.85rem;
    }

    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 32px;
        padding-top: 24px;
        border-top: 1px solid var(--glass-border);
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 14px 28px;
        border-radius: 12px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        font-size: 0.95rem;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--success));
        color: white;
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
    }

    .btn-primary:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
    }

    .btn-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .btn-secondary {
        background: rgba(148, 163, 184, 0.15);
        color: #e2e8f0;
        border: 1px solid rgba(148, 163, 184, 0.2);
    }

    .btn-secondary:hover {
        background: rgba(148, 163, 184, 0.25);
    }

    .alert {
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .alert-danger {
        background: rgba(239, 68, 68, 0.15);
        color: #fca5a5;
        border: 1px solid rgba(239, 68, 68, 0.3);
    }

    .btn-spinner-sm {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid transparent;
        border-top-color: currentColor;
        border-radius: 50%;
        animation: btn-spin-sm 0.8s linear infinite;
    }

    @keyframes btn-spin-sm {
        to { transform: rotate(360deg); }
    }

    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }

        .form-actions {
            flex-direction: column;
        }

        .form-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="content-header">
    <h1>Record Damage</h1>
    <a href="{{ route('admin.damages.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Damage Records
    </a>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <i class="fas fa-circle-exclamation"></i>
        Please fix the errors below.
    </div>
@endif

@if(session('success'))
    <div class="alert" style="background: rgba(16, 185, 129, 0.15); color: #6ee7b7;">
        <i class="fas fa-circle-check"></i> {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i> {{ session('error') }}</div>
@endif

<div class="card glass-card">
    <form method="POST" action="{{ route('admin.damages.store') }}" id="damageForm">
        @csrf

        @include('admin.damages.partials.damage-form-fields')

        <div class="form-actions">
            <button type="button" class="btn btn-secondary" id="damageFormCancelBtn">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-primary" id="submitBtn">
                <i class="fas fa-save"></i> Record Damage
            </button>
        </div>
    </form>
</div>

<script>
    const form = document.getElementById('damageForm');
    const submitBtn = document.getElementById('submitBtn');
    let formChanged = false;

    form.querySelectorAll('input, textarea, select').forEach(input => {
        input.addEventListener('change', () => formChanged = true);
        input.addEventListener('input', () => formChanged = true);
    });

    function confirmSave() {
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        Swal.fire({
            title: 'Confirm Save',
            text: 'Are you sure you want to record this damaged product?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b'
        }).then((result) => {
            if (result.isConfirmed) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                formChanged = false;
                form.submit();
            }
        });
    }

    function confirmCancel() {
        if (!formChanged) {
            window.location.href = '{{ route("admin.damages.index") }}';
            return;
        }

        Swal.fire({
            title: 'Discard Changes',
            text: 'You have unsaved changes. Are you sure you want to cancel? Any unsaved information will be lost.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '{{ route("admin.damages.index") }}';
            }
        });
    }

    submitBtn.addEventListener('click', confirmSave);
    document.getElementById('damageFormCancelBtn').addEventListener('click', confirmCancel);

    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    @if(session('success'))
        Swal.fire({
            title: 'Success',
            text: '{{ session('success') }}',
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
