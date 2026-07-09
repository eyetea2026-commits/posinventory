{{-- Shared "Add User" field markup. Included by both the standalone create
     page and the Add User modal. Expects a $roles collection in scope.
     Each error <span> carries a predictable id="error-{field}" so the modal's
     JS can inject a 422 validation message into the same slot the @error
     directive would otherwise fill on a real (non-AJAX) page submission. --}}
<div class="form-grid">
    <div class="form-group">
        <label class="form-label">First Name <span class="required">*</span></label>
        <input type="text" name="first_name" class="form-input" value="{{ old('first_name') }}" required>
        <span class="form-error" id="error-first_name">@error('first_name'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Middle Name</label>
        <input type="text" name="middle_name" class="form-input" value="{{ old('middle_name') }}">
        <span class="form-error" id="error-middle_name">@error('middle_name'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Last Name <span class="required">*</span></label>
        <input type="text" name="last_name" class="form-input" value="{{ old('last_name') }}" required>
        <span class="form-error" id="error-last_name">@error('last_name'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <span data-role="name-duplicate-error" class="form-error" style="display: none;"></span>
    </div>

    <div class="form-group">
        <label class="form-label">Age</label>
        <input type="number" name="age" class="form-input" value="{{ old('age') }}" min="1" max="150">
        <span class="form-error" id="error-age">@error('age'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <label class="form-label">Address</label>
        <input type="text" name="address" class="form-input" value="{{ old('address') }}">
        <span class="form-error" id="error-address">@error('address'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Contact Number <span class="required">*</span></label>
        <input type="text" name="contact_number" class="form-input" value="{{ old('contact_number') }}" required>
        <span class="form-error" id="error-contact_number">@error('contact_number'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Gender</label>
        <select name="gender" class="form-select">
            <option value="">Select Gender</option>
            <option value="Male" {{ old('gender') == 'Male' ? 'selected' : '' }}>Male</option>
            <option value="Female" {{ old('gender') == 'Female' ? 'selected' : '' }}>Female</option>
            <option value="Other" {{ old('gender') == 'Other' ? 'selected' : '' }}>Other</option>
        </select>
        <span class="form-error" id="error-gender">@error('gender'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Email Address <span class="required">*</span></label>
        <input type="email" name="email" class="form-input" value="{{ old('email') }}" required>
        <span class="form-error" id="error-email">@error('email'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Role <span class="required">*</span></label>
        <select name="role_id" class="form-select" required>
            <option value="">Select Role</option>
            @foreach($roles as $role)
                <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>{{ ucfirst($role->role_name) }}</option>
            @endforeach
        </select>
        <span class="form-error" id="error-role_id">@error('role_id'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <label class="form-label">Username <span class="required">*</span></label>
        <input type="text" name="name" class="form-input" value="{{ old('name') }}" required>
        <span class="form-error" id="error-name">@error('name'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Password <span class="required">*</span></label>
        <input type="password" name="password" class="form-input" required>
        <span class="form-error" id="error-password">@error('password'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Confirm Password <span class="required">*</span></label>
        <input type="password" name="password_confirmation" class="form-input" required>
    </div>
</div>
