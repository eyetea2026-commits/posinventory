@extends('admin.layout')

@section('title', 'Record Stock Receiving - CCTV Express')

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
        max-width: 700px;
        margin: 0 auto;
        padding: 32px;
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

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
        border: none;
        cursor: pointer;
        font-size: 0.95rem;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--success));
        color: white;
    }

    .btn-primary:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
    }

    .btn-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .btn-secondary {
        background: rgba(148, 163, 184, 0.15);
        color: var(--text-secondary);
        border: 1px solid rgba(148, 163, 184, 0.2);
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    .form-group {
        margin-bottom: 0;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 28px;
        padding-top: 20px;
        border-top: 1px solid var(--glass-border);
    }

    .form-actions .btn {
        padding: 14px 28px;
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
    }

    @media (max-width: 600px) {
        .form-grid { grid-template-columns: 1fr; }
    }
</style>

@include('admin.partials.modal-styles')

<div class="content-header">
    <h1>Record Stock Receiving</h1>
    <a href="{{ route('admin.stock-receivings.index') }}" class="btn btn-secondary">
        <i class="fa-solid fa-arrow-left"></i> Back to Stock Receivings
    </a>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation"></i>
        Please fix the errors below.
    </div>
@endif

<div class="card glass-card">
    <form method="POST" action="{{ route('admin.stock-receivings.store') }}" id="receivingForm">
        @csrf

        @include('admin.stock-receivings.partials.stock-receiving-form-fields')

        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="confirmCancel()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-primary" id="submitBtn" onclick="confirmSave()">
                <i class="fas fa-save"></i> Record Receipt
            </button>
        </div>
    </form>
</div>

<script>
    const form = document.getElementById('receivingForm');
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
            text: 'Are you sure you want to record this stock receiving?',
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
            window.location.href = '{{ route("admin.stock-receivings.index") }}';
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
                window.location.href = '{{ route("admin.stock-receivings.index") }}';
            }
        });
    }

    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

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
</script>
@endsection
