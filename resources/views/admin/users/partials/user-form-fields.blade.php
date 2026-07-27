{{-- Shared User field markup. Included by the standalone create/edit pages
     and the Add/Edit User modals. Expects a $roles collection in scope; pass
     an optional $user to pre-fill for editing (its absence means "Add" mode).
     Each error <span> carries a predictable id="error-{field}" so the modal's
     JS can inject a 422 validation message into the same slot the @error
     directive would otherwise fill on a real (non-AJAX) page submission. --}}
@php
    // "Protected" now means "the last active Administrator account" — its
    // Role can't be changed (that would leave zero admins), but every other
    // field, including this account's own name/contact/password, stays
    // editable. This lets an admin safely update their own profile, and lets
    // a second admin account (if one exists) be managed like any other user.
    $isProtected = isset($user) && $user->isProtected();
    $roleDisabledAttr = $isProtected ? 'disabled' : '';
@endphp
<div class="form-grid">
    @if($isProtected)
        <div class="form-group full-width">
            <div class="protected-notice">
                <i class="fas fa-shield-alt"></i>
                <span>This is the last active administrator account. Its role cannot be changed, to avoid removing all admin access.</span>
            </div>
        </div>
    @endif

    <div class="form-group">
        <label class="form-label">First Name <span class="required">*</span></label>
        <input type="text" name="first_name" class="form-input" value="{{ old('first_name', $user->first_name ?? null) }}" required>
        <span class="form-error" id="error-first_name">@error('first_name'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Middle Name</label>
        <input type="text" name="middle_name" class="form-input" value="{{ old('middle_name', $user->middle_name ?? null) }}">
        <span class="form-error" id="error-middle_name">@error('middle_name'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Last Name <span class="required">*</span></label>
        <input type="text" name="last_name" class="form-input" value="{{ old('last_name', $user->last_name ?? null) }}" required>
        <span class="form-error" id="error-last_name">@error('last_name'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Age</label>
        <input type="number" name="age" class="form-input" value="{{ old('age', $user->age ?? null) }}" min="1" max="150">
        <span class="form-error" id="error-age">@error('age'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <span data-role="name-duplicate-error" class="form-error" style="display: none;"></span>
    </div>

    <div class="form-group">
        <label class="form-label">Address</label>
        <input type="text" name="address" class="form-input" value="{{ old('address', $user->address ?? null) }}">
        <span class="form-error" id="error-address">@error('address'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Contact Number <span class="required">*</span></label>
        <input type="text" name="contact_number" class="form-input" value="{{ old('contact_number', $user->contact_number ?? null) }}" required>
        <span class="form-error" id="error-contact_number">@error('contact_number'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Gender</label>
        <select name="gender" class="form-select">
            <option value="">Select Gender</option>
            <option value="Male" {{ old('gender', $user->gender ?? null) == 'Male' ? 'selected' : '' }}>Male</option>
            <option value="Female" {{ old('gender', $user->gender ?? null) == 'Female' ? 'selected' : '' }}>Female</option>
            <option value="Other" {{ old('gender', $user->gender ?? null) == 'Other' ? 'selected' : '' }}>Other</option>
        </select>
        <span class="form-error" id="error-gender">@error('gender'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Role <span class="required">*</span></label>
        <select name="role_id" class="form-select" {{ $roleDisabledAttr }} required>
            <option value="">Select Role</option>
            @foreach($roles as $role)
                <option value="{{ $role->id }}" {{ old('role_id', $user->role_id ?? null) == $role->id ? 'selected' : '' }}>{{ ucfirst($role->role_name) }}</option>
            @endforeach
        </select>
        <span class="form-error" id="error-role_id">@error('role_id'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <label class="form-label">Username <span class="required">*</span></label>
        <input type="text" name="name" class="form-input" value="{{ old('name', $user->name ?? null) }}" required>
        <span class="form-error" id="error-name">@error('name'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">{{ isset($user) ? 'New Password' : 'Password' }} @unless(isset($user))<span class="required">*</span>@endunless</label>
        <input type="password" name="password" class="form-input" {{ isset($user) ? '' : 'required' }}>
        <span class="form-error" id="error-password">@error('password'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Confirm Password @unless(isset($user))<span class="required">*</span>@endunless</label>
        <input type="password" name="password_confirmation" class="form-input" {{ isset($user) ? '' : 'required' }}>
    </div>
</div>
