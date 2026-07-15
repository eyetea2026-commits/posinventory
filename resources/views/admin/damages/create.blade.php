@extends('admin.layout')

@section('title', 'Record Damage - CCTV Express')

@section('header')
    <div class="header-title">
        <h1>Record Damaged Product</h1>
        <p>Log a product damaged in transit or storage</p>
    </div>
@endsection

@section('header-actions')
    <a href="{{ route('admin.damages.index') }}" class="btn btn-secondary">
        <i class="fa-solid fa-arrow-left"></i> Back to Damage Records
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
        max-width: 700px;
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

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #cbd5e1;
        font-size: 0.9rem;
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
        font-size: 0.95rem;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    select.form-control {
        cursor: pointer;
    }

    textarea.form-control {
        resize: vertical;
    }

    .error {
        display: block;
        margin-top: 6px;
        font-size: 0.8rem;
        color: #fca5a5;
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

@if($errors->any())
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation"></i>
        Please fix the errors below.
    </div>
@endif

<div class="card glass-card">
    <form method="POST" action="{{ route('admin.damages.store') }}" id="damageForm">
        @csrf

        <div class="form-grid">
            <div class="form-group full-width">
                <label for="ProductID">Product <span class="required">*</span></label>
                <select id="ProductID" name="ProductID" class="form-control" required>
                    <option value="">Select Product</option>
                    @foreach($products as $product)
                        <option value="{{ $product->ProductID }}" {{ old('ProductID') == $product->ProductID ? 'selected' : '' }}>
                            {{ $product->ProductName }} - {{ $product->Model }}
                            @if($product->inventory)
                                (Stock: {{ $product->inventory->Quantity }})
                            @else
                                (No Stock)
                            @endif
                        </option>
                    @endforeach
                </select>
                @error('ProductID')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group full-width">
                <label for="SupplierID">Supplier <span class="required">*</span></label>
                <select id="SupplierID" name="SupplierID" class="form-control" required>
                    <option value="">Select Supplier</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->SupplierID }}" {{ old('SupplierID') == $supplier->SupplierID ? 'selected' : '' }}>
                            {{ $supplier->SupplierName }}
                        </option>
                    @endforeach
                </select>
                @error('SupplierID')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group full-width">
                <label for="PurchaseOrderID">Purchase Order <span style="color: var(--text-muted); font-weight: 400;">(optional)</span></label>
                <select id="PurchaseOrderID" name="PurchaseOrderID" class="form-control">
                    <option value="">None / Not linked to a PO</option>
                    @foreach($purchaseOrders as $po)
                        <option value="{{ $po->PurchaseOrderID }}" {{ old('PurchaseOrderID') == $po->PurchaseOrderID ? 'selected' : '' }}>
                            PO #{{ $po->PurchaseOrderID }} &mdash; {{ $po->supplier?->SupplierName }}
                        </option>
                    @endforeach
                </select>
                @error('PurchaseOrderID')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="Quantity">Quantity Damaged <span class="required">*</span></label>
                <input type="number" id="Quantity" name="Quantity" class="form-control"
                       value="{{ old('Quantity') }}" required min="1">
                @error('Quantity')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="DateRecorded">Date Recorded <span class="required">*</span></label>
                <input type="date" id="DateRecorded" name="DateRecorded" class="form-control"
                       value="{{ old('DateRecorded', date('Y-m-d')) }}" required>
                @error('DateRecorded')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group full-width">
                <label for="DamageType">Damage Type / Reason <span class="required">*</span></label>
                <select id="DamageType" name="DamageType" class="form-control" required>
                    <option value="">Select Damage Type</option>
                    @foreach(\App\Models\DamagedProduct::DAMAGE_TYPES as $value => $label)
                        <option value="{{ $value }}" {{ old('DamageType') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('DamageType')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group full-width">
                <label for="Description">Damage Description <span class="required">*</span></label>
                <textarea id="Description" name="Description" class="form-control" rows="3"
                          required maxlength="500" placeholder="Describe the damage...">{{ old('Description') }}</textarea>
                @error('Description')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group full-width">
                <label for="InspectionNotes">Inspection Notes</label>
                <textarea id="InspectionNotes" name="InspectionNotes" class="form-control" rows="3"
                          maxlength="1000" placeholder="Optional inspection findings...">{{ old('InspectionNotes') }}</textarea>
                @error('InspectionNotes')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="WarehouseLocation">Warehouse Location</label>
                <input type="text" id="WarehouseLocation" name="WarehouseLocation" class="form-control"
                       value="{{ old('WarehouseLocation') }}" maxlength="100" placeholder="e.g. Aisle 3, Shelf B">
                @error('WarehouseLocation')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group full-width">
                <label for="Remarks">Remarks</label>
                <textarea id="Remarks" name="Remarks" class="form-control" rows="2"
                          maxlength="500" placeholder="Optional remarks...">{{ old('Remarks') }}</textarea>
                @error('Remarks')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="confirmCancel()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-primary" id="submitBtn" onclick="confirmSave()">
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
