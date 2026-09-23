@extends('admin.layout')

@section('title', 'Edit Category - CCTV Express')

@section('header')
    <div class="header-title">
        <h1>Edit Category</h1>
        <p>Update this category's name or description</p>
    </div>
@endsection

@section('header-actions')
    <a href="{{ route('admin.categories.index') }}" class="btn btn-secondary">
        <i class="fa-solid fa-arrow-left"></i> Back to Categories
    </a>
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
        max-width: 600px;
        margin: 0 auto;
        padding: 32px;
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

    .btn-primary:hover {
        transform: translateY(-2px);
    }

    .btn-secondary {
        background: rgba(148, 163, 184, 0.15);
        color: var(--text-secondary);
        border: 1px solid rgba(148, 163, 184, 0.2);
    }

    .form-group {
        margin-bottom: 24px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #cbd5e1;
    }

    .form-group label .required {
        color: #ef4444;
    }

    .form-control {
        width: 100%;
        padding: 14px 16px;
        background: rgba(30, 41, 59, 0.8);
        border: 1px solid rgba(59, 130, 246, 0.2);
        border-radius: 12px;
        color: #f8fafc;
        font-size: 1rem;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary);
    }

    textarea.form-control {
        min-height: 120px;
        resize: vertical;
    }

    .error {
        display: block;
        margin-top: 8px;
        color: #fca5a5;
        font-size: 0.85rem;
    }

    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 32px;
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

    /* Brands panel, inline in this form */
    .brand-chip-list {
        display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px;
        min-height: 32px;
    }
    .brand-chip {
        display: inline-flex; align-items: center; gap: 6px;
        background: rgba(59, 130, 246, 0.15); color: #93c5fd;
        border: 1px solid rgba(59, 130, 246, 0.3);
        border-radius: 999px; padding: 5px 8px 5px 12px; font-size: 0.82rem;
    }
    .brand-chip-remove {
        border: none; background: rgba(148, 163, 184, 0.15); color: #cbd5e1;
        width: 18px; height: 18px; border-radius: 50%; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 0.9rem; line-height: 1; padding: 0;
    }
    .brand-chip-remove:hover { background: rgba(239, 68, 68, 0.3); color: #fca5a5; }
    .brand-chip-empty { color: var(--text-secondary); font-size: 0.82rem; }
    .brand-add-row { display: flex; gap: 8px; }
    .brand-add-row .form-control { flex: 1; }
    .brand-add-row .btn { white-space: nowrap; padding: 10px 16px; font-size: 0.85rem; }
</style>

@if($errors->any())
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation"></i>
        Please fix the errors below.
    </div>
@endif

<div class="card glass-card">
    <form method="POST" action="{{ route('admin.categories.update', $category->CategoryID) }}" id="categoryForm">
        @csrf
        @method('PUT')

        @include('admin.categories.partials.category-form-fields')

        <div class="form-actions">
            <a href="{{ route('admin.categories.index') }}" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="button" class="btn btn-primary" onclick="confirmUpdate()">
                <i class="fas fa-save"></i> Update Category
            </button>
        </div>
    </form>
</div>

<script>
    function confirmUpdate() {
        const form = document.getElementById('categoryForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        Swal.fire({
            title: 'Confirm Update',
            text: 'Are you sure you want to save the changes to this category?',
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
@include('admin.categories.partials.category-brands-behavior')
@endsection