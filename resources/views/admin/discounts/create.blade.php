@extends('admin.layout')

@section('title', 'Add Promo Code - CCTV Express')

@section('header')
    <div class="header-title">
        <h1>Add Promo Code</h1>
        <p>Create a product-specific promotional discount</p>
    </div>
@endsection

@section('header-actions')
    <a href="{{ route('admin.discounts.index') }}" class="btn btn-secondary">
        <i class="fa-solid fa-arrow-left"></i> Back to Promo Codes
    </a>
@endsection

@section('content')
<style>
    .glass-card {
        background: rgba(15, 23, 42, 0.7);
        border: 1px solid rgba(148, 163, 184, 0.1);
        border-radius: 20px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(10px);
        max-width: 640px;
        margin: 0 auto;
        padding: 32px;
    }
    .btn {
        display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 10px;
        font-weight: 600; text-decoration: none; transition: all 0.2s ease; border: none; cursor: pointer; font-size: 0.95rem;
    }
    .btn-primary { background: linear-gradient(135deg, #3b82f6, #10b981); color: white; }
    .btn-primary:hover { transform: translateY(-2px); }
    .btn-secondary { background: rgba(148, 163, 184, 0.15); color: var(--text-secondary); border: 1px solid rgba(148, 163, 184, 0.2); }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #cbd5e1; }
    .form-group label .required { color: #ef4444; }
    .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .form-control {
        width: 100%; padding: 12px 14px; background: rgba(30, 41, 59, 0.8);
        border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 12px; color: #f8fafc; font-size: 0.95rem;
    }
    .form-control:focus { outline: none; border-color: var(--primary); }
    .form-control:disabled { opacity: 0.7; }
    .error { display: block; margin-top: 6px; color: #fca5a5; font-size: 0.82rem; }
    .form-actions { display: flex; gap: 12px; margin-top: 28px; }
    .form-actions .btn { padding: 14px 28px; }
    .alert { padding: 16px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
    .alert-danger { background: rgba(239, 68, 68, 0.15); color: #fca5a5; }
</style>

@if($errors->any())
    <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> Please fix the errors below.</div>
@endif

<div class="card glass-card">
    <form method="POST" action="{{ route('admin.discounts.store') }}" id="discountForm">
        @csrf
        @include('admin.discounts.partials.discount-form-fields', ['discount' => null, 'products' => $products])

        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="confirmCancel()"><i class="fas fa-times"></i> Cancel</button>
            <button type="button" class="btn btn-primary" id="submitBtn" onclick="confirmSave()"><i class="fas fa-save"></i> Create Promo</button>
        </div>
    </form>
</div>

@include('admin.discounts.partials.discount-form-behavior')

<script>
    const form = document.getElementById('discountForm');
    const submitBtn = document.getElementById('submitBtn');
    let formChanged = false;

    form.querySelectorAll('input, textarea, select').forEach(input => {
        input.addEventListener('change', () => formChanged = true);
        input.addEventListener('input', () => formChanged = true);
    });

    function confirmSave() {
        if (!form.checkValidity()) { form.reportValidity(); return; }

        window.confirmAction({ title: 'Confirm Save', text: 'Create this promo code?' }).then((result) => {
            if (result.isConfirmed) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                formChanged = false;
                form.submit();
            }
        });
    }

    function confirmCancel() {
        if (!formChanged) { window.location.href = '{{ route("admin.discounts.index") }}'; return; }

        window.confirmAction({
            title: 'Discard Changes', text: 'You have unsaved changes. Are you sure you want to cancel?',
            icon: 'warning', confirmText: 'Yes', confirmColor: '#ef4444',
        }).then((result) => {
            if (result.isConfirmed) window.location.href = '{{ route("admin.discounts.index") }}';
        });
    }

    window.addEventListener('beforeunload', function (e) {
        if (formChanged) { e.preventDefault(); e.returnValue = ''; }
    });

    @if(session('success')) toastSuccess('{{ session('success') }}'); @endif
    @if(session('error')) toastError('{{ session('error') }}'); @endif
</script>
@endsection
