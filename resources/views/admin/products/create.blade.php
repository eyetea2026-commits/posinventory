@extends('admin.layout')

@section('header')
    <div class="header-title">
        <h1>Product Management</h1>
    </div>
@endsection

@section('content')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

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

    .form-input.is-invalid, .form-select.is-invalid {
        border-color: rgba(239, 68, 68, 0.5);
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
        min-height: 100px;
        resize: vertical;
    }

    .form-error {
        display: block;
        margin-top: 8px;
        color: #fca5a5;
        font-size: 0.85rem;
    }

    /* Barcode scan row */
    .barcode-input-row {
        display: flex;
        gap: 10px;
        align-items: stretch;
    }

    .barcode-input-row .form-input {
        flex: 1;
        min-width: 0;
    }

    .btn-scan-barcode {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 0 20px;
        border-radius: 12px;
        border: 1px solid rgba(139, 92, 246, 0.35);
        background: rgba(139, 92, 246, 0.15);
        color: #c4b5fd;
        font-weight: 600;
        font-size: 0.9rem;
        white-space: nowrap;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-scan-barcode:hover {
        background: rgba(139, 92, 246, 0.28);
        border-color: rgba(139, 92, 246, 0.55);
        transform: translateY(-1px);
    }

    .btn-scan-barcode:active {
        transform: translateY(0);
    }

    @media (max-width: 480px) {
        .barcode-input-row { flex-direction: column; }
        .btn-scan-barcode { padding: 12px 20px; justify-content: center; }
    }

    /* Computed Fields */
    .computed-fields {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-top: 8px;
    }

    .computed-field {
        padding: 16px;
        background: rgba(59, 130, 246, 0.1);
        border: 1px solid rgba(59, 130, 246, 0.2);
        border-radius: 12px;
        text-align: center;
    }

    .computed-field label {
        display: block;
        font-size: 0.75rem;
        color: #64748b;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter: 0.05em;
    }

    .computed-field .value {
        font-size: 1.25rem;
        font-weight: 700;
        color: #10b981;
    }

    .computed-field .value.negative {
        color: #ef4444;
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

        .computed-fields {
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
    <h1>Add New Product</h1>
    <a href="{{ route('admin.products.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Products
    </a>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <i class="fas fa-circle-exclamation"></i>
        Please fix the errors below.
    </div>
@endif

<div class="card glass-card">
    <form method="POST" action="{{ route('admin.products.store') }}" id="productForm">
        @csrf

        @include('admin.products.partials.product-form-fields', ['categories' => $categories])

        <div class="form-actions">
            <button type="button" class="btn btn-secondary" id="productFormCancelBtn">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-primary" id="submitBtn">
                <i class="fas fa-save"></i> Create Product
            </button>
        </div>
    </form>
</div>

@include('admin.products.partials.product-form-behavior')
@include('admin.products.partials.barcode-scanner')

<script>
    window.initBarcodeScanner('productForm');

    const productAddForm = window.initProductAddForm('productForm', {
        submitBtn: document.getElementById('submitBtn'),
        onConfirmedSubmit: function () {
            document.getElementById('productForm').submit();
        },
        onCancel: function (changed) {
            if (!changed) {
                window.location.href = '{{ route("admin.products.index") }}';
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
                    window.location.href = '{{ route("admin.products.index") }}';
                }
            });
        }
    });

    document.getElementById('submitBtn').addEventListener('click', () => productAddForm.confirmSave());
    document.getElementById('productFormCancelBtn').addEventListener('click', () => productAddForm.confirmCancel());

    window.addEventListener('beforeunload', function (e) {
        if (productAddForm.isChanged()) {
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