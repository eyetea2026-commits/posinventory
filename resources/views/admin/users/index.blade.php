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
        --glass-bg: rgba(15, 23, 42, 0.75);
        --glass-border: rgba(148, 163, 184, 0.12);
        --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        --primary: #3b82f6;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
    }

    .glass-card {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        box-shadow: var(--glass-shadow);
        backdrop-filter: blur(12px);
    }

    .toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 24px;
        padding: 20px 24px;
        border-bottom: 1px solid var(--glass-border);
    }

    .search-wrapper {
        flex: 1;
        max-width: 480px;
        position: relative;
    }

    .search-wrapper i {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: #64748b;
    }

    .search-wrapper input {
        width: 100%;
        padding: 14px 20px 14px 48px;
        background: rgba(30, 41, 59, 0.6);
        border: 1px solid rgba(59, 130, 246, 0.15);
        border-radius: 12px;
        color: #f8fafc;
        font-size: 0.9rem;
    }

    .search-wrapper input:focus {
        outline: none;
        border-color: var(--primary);
    }

    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
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
        color: #e2e8f0;
    }

    .btn-warning {
        background: rgba(245, 158, 11, 0.15);
        color: #fbbf24;
    }

    .table-container { overflow-x: auto; }
    .table { width: 100%; border-collapse: collapse; }

    .table thead th {
        position: sticky;
        top: 0;
        background: rgba(15, 23, 42, 0.95);
        padding: 16px;
        text-align: left;
        color: #94a3b8;
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
    }

    .table td {
        padding: 16px;
        border-bottom: 1px solid var(--glass-border);
    }

    .table tbody tr:hover {
        background: rgba(59, 130, 246, 0.06);
    }

    .user-info { display: flex; align-items: center; gap: 14px; }

    .user-avatar {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--primary), var(--success));
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: white;
        font-size: 0.9rem;
    }

    .user-details h4 { margin: 0; font-size: 0.95rem; font-weight: 600; }
    .user-details p { margin: 4px 0 0; font-size: 0.8rem; color: #64748b; }

    .badge {
        display: inline-flex;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge-admin { background: rgba(239, 68, 68, 0.15); color: #fca5a5; }
    .badge-cashier { background: rgba(59, 130, 246, 0.15); color: #93c5fd; }

    .toggle-switch {
        display: inline-block;
        position: relative;
        width: 34px;
        height: 19px;
        flex-shrink: 0;
        vertical-align: middle;
    }

    .toggle-switch input { opacity: 0; width: 0; height: 0; }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(100, 116, 139, 0.45);
        border: 1px solid rgba(148, 163, 184, 0.5);
        border-radius: 19px;
        transition: 0.3s;
    }

    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 13px;
        width: 13px;
        left: 2px;
        bottom: 2px;
        background: #f1f5f9;
        border-radius: 50%;
        transition: 0.3s;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);
    }

    .toggle-switch input:checked + .toggle-slider {
        background: rgba(16, 185, 129, 0.45);
        border-color: rgba(16, 185, 129, 0.7);
    }

    .toggle-switch input:checked + .toggle-slider:before {
        transform: translateX(15px);
        background: var(--success);
    }

    .actions-group { display: flex; gap: 8px; }

    .action-btn {
        padding: 8px 16px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
        font-size: 0.85rem;
        font-weight: 500;
        background: rgba(59, 130, 246, 0.15);
        color: #60a5fa;
    }

    .action-btn:hover {
        background: rgba(59, 130, 246, 0.3);
    }

    .action-btn:active {
        transform: scale(0.95);
    }

    .action-btn.icon-only {
        width: 40px;
        height: 40px;
        padding: 0;
        justify-content: center;
    }

    .action-btn.icon-only i {
        font-size: 0.95rem;
    }

    .empty-state { text-align: center; padding: 60px 20px; }
    .empty-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 20px;
        background: rgba(148, 163, 184, 0.1);
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .empty-icon i { font-size: 2.5rem; color: #64748b; }
    .empty-state h3 { margin: 0 0 8px; color: #e2e8f0; }
    .empty-state p { margin: 0 0 24px; color: #64748b; }

    .pagination { display: flex; justify-content: center; gap: 6px; margin-top: 24px; padding: 16px; }
    .pagination a, .pagination span {
        padding: 10px 14px;
        background: rgba(30, 41, 59, 0.6);
        color: #e2e8f0;
        border-radius: 10px;
        text-decoration: none;
        font-size: 0.9rem;
    }
    .pagination a:hover { background: var(--primary); }

    .protected-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: rgba(239, 68, 68, 0.15);
        color: #fca5a5;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    /* Modal Styles */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.85);
        z-index: 10000;
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        background: #0f172a;
        border: 1px solid #334155;
        border-radius: 20px;
        padding: 24px;
        max-width: 550px;
        width: 92%;
        max-height: 85vh;
        overflow-y: auto;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid #334155;
    }

    .modal-header h2 { margin: 0; font-size: 1.2rem; color: #f8fafc; }

    .modal-close {
        width: 32px;
        height: 32px;
        background: #1e293b;
        border: none;
        border-radius: 8px;
        color: #94a3b8;
        font-size: 1.2rem;
        cursor: pointer;
    }

    .modal-close:hover { background: #334155; color: #fff; }

    .detail-section { margin-bottom: 16px; }
    .detail-section-title {
        font-size: 0.7rem;
        color: #64748b;
        text-transform: uppercase;
        letter: 0.05em;
        margin-bottom: 10px;
    }

    .detail-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }

    .detail-item {
        padding: 12px;
        background: #1e293b;
        border-radius: 10px;
    }

    .detail-item.full-width { grid-column: span 2; }
    .detail-item label {
        display: block;
        font-size: 0.65rem;
        color: #64748b;
        margin-bottom: 4px;
        text-transform: uppercase;
    }

    .detail-item span { font-weight: 500; color: #e2e8f0; font-size: 0.85rem; }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .status-active { background: rgba(16, 185, 129, 0.2); color: #34d399; }
    .status-inactive { background: rgba(100, 116, 139, 0.2); color: #94a3b8; }

    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
        padding-top: 16px;
        border-top: 1px solid #334155;
    }

    .modal-loading {
        text-align: center;
        padding: 40px;
        color: #64748b;
    }

    .modal-loading i {
        font-size: 1.5rem;
        animation: spin 1s linear infinite;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    @media (max-width: 768px) {
        .detail-grid { grid-template-columns: 1fr; }
        .detail-item.full-width { grid-column: span 1; }
    }

    /* Add User Modal */
    .form-modal-overlay {
        /* Below SweetAlert2's z-index (~1060) since the confirm-before-save
           and validation dialogs are shown while this modal is still open. */
        z-index: 900;
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
    }

    .form-modal-content {
        max-width: 600px;
        width: 92%;
        max-height: 88vh;
        padding: 18px 22px;
        transform: scale(0.95) translateY(12px);
        opacity: 0;
        transition: transform 0.25s ease, opacity 0.25s ease;
    }

    .form-modal-overlay.active .form-modal-content {
        transform: scale(1) translateY(0);
        opacity: 1;
    }

    /* Compact overrides — the modal packs the same fields into a tighter
       space than the spacious standalone create page, which keeps its own
       (unscoped) spacing from user-form-styles.blade.php untouched. */
    .form-modal-content .modal-header {
        margin-bottom: 12px;
        padding-bottom: 12px;
    }

    .form-modal-content .modal-header h2 {
        font-size: 1.05rem;
    }

    .form-modal-content .form-grid {
        gap: 12px;
    }

    .form-modal-content .form-label {
        margin-bottom: 4px;
        font-size: 0.82rem;
    }

    .form-modal-content .form-input,
    .form-modal-content .form-select {
        padding: 9px 12px;
        font-size: 0.85rem;
        border-radius: 9px;
    }

    .form-modal-content .form-error {
        margin-top: 3px;
        font-size: 0.72rem;
    }

    .form-error-banner {
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(239, 68, 68, 0.12);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #fca5a5;
        padding: 10px 14px;
        border-radius: 10px;
        margin-bottom: 14px;
        font-size: 0.82rem;
    }

    .add-user-modal-actions {
        justify-content: space-between;
        margin-top: 14px;
        padding-top: 12px;
    }

    .add-user-modal-actions .btn {
        padding: 10px 18px;
        font-size: 0.85rem;
    }

    @media (max-width: 900px) {
        .form-modal-content { max-width: 90%; }
    }

    @media (max-width: 600px) {
        .form-modal-content {
            width: calc(100% - 24px);
            max-width: calc(100% - 24px);
            margin: 12px;
            max-height: calc(100vh - 24px);
        }
    }
</style>

<div class="card glass-card">
    <div class="toolbar">
        <div class="search-wrapper">
            <input type="text" id="searchInput" value="{{ $search }}" placeholder="Search by name or username..." />
            <i class="fas fa-search"></i>
        </div>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary" onclick="openAddUserModal(event)">
            <i class="fas fa-plus"></i> Add User
        </a>
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar">{{ $user->initials }}</div>
                                <div class="user-details">
                                    <h4>{{ $user->full_name }}</h4>
                                    <p>{{ $user->contact_number }}</p>
                                </div>
                            </div>
                        </td>
                        <td>{{ $user->email }}</td>
                        <td><code style="background: rgba(59,130,246,0.15); padding: 4px 8px; border-radius: 6px; font-size: 0.85rem;">{{ $user->name }}</code></td>
                        <td>
                            <span class="badge {{ $user->isAdmin() ? 'badge-admin' : 'badge-cashier' }}">
                                {{ $user->role?->role_name ?? 'N/A' }}
                            </span>
                        </td>
                        <td>
                            @if($user->isProtected())
                                <span class="protected-badge">
                                    <i class="fas fa-shield-alt"></i> Protected
                                </span>
                            @else
                                <label class="toggle-switch">
                                    <input type="checkbox" {{ $user->is_active ? 'checked' : '' }} onchange="handleStatusChange({{ $user->id }}, this)">
                                    <span class="toggle-slider"></span>
                                </label>
                            @endif
                        </td>
                        <td>
                            <div class="actions-group">
                                <button type="button" class="action-btn icon-only view-user-btn" data-user-id="{{ $user->id }}" title="View details" aria-label="View details for {{ $user->full_name }}" onclick="openUserDetails({{ $user->id }})">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-user-slash"></i></div>
                                <h3>No Result Found</h3>
                                <p>No users matched your search.</p>
                                <a href="{{ route('admin.users.create') }}" class="btn btn-primary" onclick="openAddUserModal(event)">
                                    <i class="fas fa-plus"></i> Add User
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($users->hasPages())
        <div class="pagination">
            @if($users->onFirstPage())
                <span><i class="fas fa-chevron-left"></i></span>
            @else
                <a href="{{ $users->previousPageUrl() }}"><i class="fas fa-chevron-left"></i></a>
            @endif

            @foreach($users->getUrlRange(1, min(5, $users->lastPage())) as $page => $url)
                <a href="{{ $url }}" class="{{ $page == $users->currentPage() ? 'active' : '' }}" style="{{ $page == $users->currentPage() ? 'background: var(--primary);' : '' }}">{{ $page }}</a>
            @endforeach

            @if($users->hasMorePages())
                <a href="{{ $users->nextPageUrl() }}"><i class="fas fa-chevron-right"></i></a>
            @else
                <span><i class="fas fa-chevron-right"></i></span>
            @endif
        </div>
    @endif
</div>

<!-- View User Modal -->
<div id="viewModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-circle"></i> User Details</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div id="userDetails"></div>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="closeModal()">
                <i class="fas fa-times"></i> Close
            </button>
            <a id="updateUserBtn" href="#" class="btn btn-warning">
                <i class="fas fa-edit"></i> Update User
            </a>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal-overlay form-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="addUserModalTitle" aria-hidden="true">
    <div class="modal-content form-modal-content">
        <div class="modal-header">
            <h2 id="addUserModalTitle"><i class="fas fa-user-plus"></i> Add New User</h2>
            <button type="button" class="modal-close" onclick="closeAddUserModal()" aria-label="Close">&times;</button>
        </div>

        <div id="addUserGeneralError" class="form-error-banner" style="display:none;" role="alert"></div>

        <form id="addUserForm">
            @include('admin.users.partials.user-form-fields', ['roles' => \App\Models\Role::where('role_name', 'cashier')->get()])
        </form>

        <div class="modal-actions add-user-modal-actions">
            <button type="button" class="btn btn-secondary" id="addUserCancelBtn">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-primary" id="addUserSubmitBtn">
                <i class="fas fa-save"></i> Save User
            </button>
        </div>
    </div>
</div>

@include('admin.users.partials.user-form-behavior')

<script>
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    let searchTimeout;

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                const url = new URL('{{ route("admin.users.index") }}', window.location.origin);
                if (searchInput.value) {
                    url.searchParams.set('search', searchInput.value);
                }
                window.location.href = url.toString();
            }, 300);
        });
    }

    // Handle status toggle with SweetAlert
    function handleStatusChange(userId, checkbox) {
        const isActive = checkbox.checked;

        if (isActive) {
            Swal.fire({
                title: 'Confirm Activation',
                text: 'Are you sure you want to activate this user? They will be able to log in to the system.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'No'
            }).then((result) => {
                if (result.isConfirmed) {
                    toggleStatus(userId, true);
                } else {
                    checkbox.checked = false;
                }
            });
        } else {
            Swal.fire({
                title: 'Confirm Deactivation',
                text: 'Are you sure you want to deactivate this user? They will no longer be able to log in to the system.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'No'
            }).then((result) => {
                if (result.isConfirmed) {
                    toggleStatus(userId, false);
                } else {
                    checkbox.checked = true;
                }
            });
        }
    }

    function toggleStatus(userId, isActive) {
        const url = isActive
            ? `/admin/users/${userId}/activate`
            : `/admin/users/${userId}/deactivate`;

        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status) {
                Swal.fire({
                    title: 'Success',
                    text: isActive ? 'User activated successfully.' : 'User deactivated successfully.',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else if (data.error) {
                Swal.fire({
                    title: 'Error',
                    text: data.error,
                    icon: 'error'
                });
                const checkbox = document.querySelector(`input[type="checkbox"][onchange*="${userId}"]`);
                if (checkbox) checkbox.checked = !isActive;
            }
        })
        .catch(error => {
            Swal.fire({
                title: 'Error',
                text: 'An unexpected error occurred.',
                icon: 'error'
            });
            const checkbox = document.querySelector(`input[type="checkbox"][onchange*="${userId}"]`);
            if (checkbox) checkbox.checked = !isActive;
        });
    }

    // View User Details
    window.openUserDetails = function(userId) {
        const modal = document.getElementById('viewModal');
        const detailsContainer = document.getElementById('userDetails');
        const updateBtn = document.getElementById('updateUserBtn');

        if (!modal || !detailsContainer || !updateBtn) {
            console.error('User details modal elements are missing.');
            return;
        }

        detailsContainer.innerHTML = '<div class="modal-loading"><i class="fas fa-spinner"></i><p>Loading user details...</p></div>';
        modal.style.display = 'flex';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';

        fetch('/admin/users/' + userId, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        })
        .then(async response => {
            const contentType = response.headers.get('content-type') || '';

            if (!response.ok) {
                throw new Error('Unable to load user details.');
            }

            if (!contentType.includes('application/json')) {
                const text = await response.text();
                throw new Error(text || 'Unexpected response format.');
            }

            return response.json();
        })
        .then(data => {
            if (!data.user) {
                detailsContainer.innerHTML = '<p style="text-align:center;padding:20px;color:#ef4444;">User not found!</p>';
                return;
            }

            const user = data.user;
            const fullName = [user.first_name, user.middle_name, user.last_name].filter(Boolean).join(' ') || 'N/A';
            const createdAt = new Date(user.created_at).toLocaleString('en-PH');
            const updatedAt = new Date(user.updated_at).toLocaleString('en-PH');

            updateBtn.href = '/admin/users/' + userId + '/edit';

            const protectedNotice = user.role_name === 'admin' ?
                '<div class="detail-item full-width" style="background: rgba(239,68,68,0.15);">' +
                    '<span class="protected-badge"><i class="fas fa-shield-alt"></i> Protected Administrator</span>' +
                '</div>' : '';

            detailsContainer.innerHTML = `
                ${protectedNotice}
                <div class="detail-section">
                    <div class="detail-section-title">Personal Information</div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>First Name</label>
                            <span>${user.first_name || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Last Name</label>
                            <span>${user.last_name || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Age</label>
                            <span>${user.age || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Gender</label>
                            <span>${user.gender || 'N/A'}</span>
                        </div>
                        <div class="detail-item full-width">
                            <label>Address</label>
                            <span>${user.address || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Contact</label>
                            <span>${user.contact_number || 'N/A'}</span>
                        </div>
                    </div>
                </div>

                <div class="detail-section">
                    <div class="detail-section-title">Account Information</div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Username</label>
                            <span>${user.name || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Email</label>
                            <span>${user.email || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Role</label>
                            <span class="badge ${user.role_name === 'admin' ? 'badge-admin' : 'badge-cashier'}">${user.role_name || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Status</label>
                            <span class="status-badge ${user.is_active ? 'status-active' : 'status-inactive'}">
                                ${user.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="detail-section">
                    <div class="detail-section-title">System Information</div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Date Created</label>
                            <span>${createdAt}</span>
                        </div>
                        <div class="detail-item">
                            <label>Last Updated</label>
                            <span>${updatedAt}</span>
                        </div>
                    </div>
                </div>
            `;
        })
        .catch(error => {
            console.error('Error loading user:', error);
            detailsContainer.innerHTML = '<p style="text-align:center;padding:20px;color:#ef4444;">Error loading user!</p>';
            Swal.fire({
                title: 'Connection Error',
                text: 'Unable to load user details. Please check your network connection and try again.',
                icon: 'error',
                confirmButtonColor: '#ef4444'
            });
        });
    }

    window.viewUser = window.openUserDetails;

    function closeModal() {
        const modal = document.getElementById('viewModal');
        modal.classList.remove('active');
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    // Close modal on outside click
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('viewModal');
        if (e.target === modal) {
            closeModal();
        }
    });

    // Close modal on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

    // ---- Add User modal ----
    const ADD_USER_FIELD_IDS = ['first_name', 'middle_name', 'last_name', 'age', 'address', 'contact_number', 'gender', 'email', 'role_id', 'name', 'password'];
    let addUserLastFocused = null;

    function addUserIsSubmitting() {
        const btn = document.getElementById('addUserSubmitBtn');
        return btn ? btn.disabled : false;
    }

    function clearAddUserFieldErrors() {
        const form = document.getElementById('addUserForm');
        ADD_USER_FIELD_IDS.forEach(function (field) {
            const span = document.getElementById('error-' + field);
            if (span) span.textContent = '';
            const input = form.querySelector('[name="' + field + '"]');
            if (input) input.classList.remove('error');
        });
    }

    function showAddUserFieldErrors(errors) {
        const form = document.getElementById('addUserForm');
        clearAddUserFieldErrors();
        let firstInvalid = null;
        Object.keys(errors).forEach(function (field) {
            const span = document.getElementById('error-' + field);
            if (span) span.textContent = errors[field][0];
            const input = form.querySelector('[name="' + field + '"]');
            if (input) {
                input.classList.add('error');
                if (!firstInvalid) firstInvalid = input;
            }
        });
        if (firstInvalid) firstInvalid.focus();
    }

    function showAddUserGeneralError(message) {
        const banner = document.getElementById('addUserGeneralError');
        banner.textContent = message;
        banner.style.display = 'flex';
    }

    function hideAddUserGeneralError() {
        const banner = document.getElementById('addUserGeneralError');
        banner.style.display = 'none';
        banner.textContent = '';
    }

    // Swaps in the freshly rendered table + pagination from the index page
    // fetched after a successful save, without a real page navigation.
    function refreshUsersTable(html) {
        const parsed = new DOMParser().parseFromString(html, 'text/html');

        const newTableContainer = parsed.querySelector('.table-container');
        const currentTableContainer = document.querySelector('.table-container');
        if (newTableContainer && currentTableContainer) {
            currentTableContainer.innerHTML = newTableContainer.innerHTML;
        }

        const newPagination = parsed.querySelector('.pagination');
        const currentPagination = document.querySelector('.pagination');
        if (newPagination) {
            if (currentPagination) {
                currentPagination.outerHTML = newPagination.outerHTML;
            } else if (currentTableContainer) {
                currentTableContainer.insertAdjacentHTML('afterend', newPagination.outerHTML);
            }
        } else if (currentPagination) {
            currentPagination.remove();
        }
    }

    function submitAddUserFormViaAjax(resetSubmitButton) {
        const form = document.getElementById('addUserForm');
        clearAddUserFieldErrors();
        hideAddUserGeneralError();

        fetch('{{ route('admin.users.store') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: new FormData(form)
        })
        .then(async function (response) {
            if (response.status === 422) {
                const data = await response.json();
                showAddUserFieldErrors(data.errors || {});
                resetSubmitButton();
                return;
            }

            // The store route still redirects (unchanged backend behavior) —
            // fetch follows it and hands back the re-rendered index page.
            // Both a successful save and the server-side duplicate-name guard
            // redirect back to this same page with a flashed 'status' or
            // 'error' message, so we read whichever one actually rendered
            // rather than guessing from the HTTP status alone.
            const html = await response.text();
            const marker = 'Auto-show session messages';
            const tail = html.slice(html.indexOf(marker));
            const successMatch = tail.match(/title:\s*'Success',\s*text:\s*'([^']*)'/);
            const errorMatch = tail.match(/title:\s*'Error',\s*text:\s*'([^']*)'/);

            if (successMatch) {
                refreshUsersTable(html);
                closeAddUserModal();
                Swal.fire({
                    title: 'Success',
                    text: successMatch[1],
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else if (errorMatch) {
                showAddUserGeneralError(errorMatch[1]);
                resetSubmitButton();
            } else {
                showAddUserGeneralError('Something went wrong. Please try again.');
                resetSubmitButton();
            }
        })
        .catch(function () {
            showAddUserGeneralError('A network error occurred. Please try again.');
            resetSubmitButton();
        });
    }

    const addUserModalForm = window.initUserAddForm('addUserForm', {
        submitBtn: document.getElementById('addUserSubmitBtn'),
        onConfirmedSubmit: submitAddUserFormViaAjax,
        onCancel: function () {
            closeAddUserModal();
        }
    });

    document.getElementById('addUserSubmitBtn').addEventListener('click', () => addUserModalForm.confirmSave());
    document.getElementById('addUserCancelBtn').addEventListener('click', () => addUserModalForm.confirmCancel());

    function handleAddUserModalKeydown(e) {
        const modal = document.getElementById('addUserModal');
        if (!modal.classList.contains('active')) return;

        if (e.key === 'Escape') {
            if (!addUserIsSubmitting()) closeAddUserModal();
            return;
        }

        if (e.key === 'Tab') {
            const focusable = modal.querySelectorAll('input, select, button, [href]');
            if (!focusable.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    }

    window.openAddUserModal = function (event) {
        if (event) event.preventDefault();
        const modal = document.getElementById('addUserModal');
        const form = document.getElementById('addUserForm');

        addUserLastFocused = document.activeElement;
        form.reset();
        clearAddUserFieldErrors();
        hideAddUserGeneralError();
        addUserModalForm.markUnchanged();
        addUserModalForm.resetSubmitButton();

        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        // Force a reflow so the opacity/transform transition below actually
        // animates from its resting state instead of snapping straight in.
        void modal.offsetHeight;
        requestAnimationFrame(function () {
            modal.classList.add('active');
        });

        const firstField = form.querySelector('input, select');
        if (firstField) firstField.focus();

        document.addEventListener('keydown', handleAddUserModalKeydown);
    };

    window.closeAddUserModal = function () {
        const modal = document.getElementById('addUserModal');
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
        document.removeEventListener('keydown', handleAddUserModalKeydown);

        setTimeout(function () {
            modal.style.display = 'none';
        }, 250);

        document.body.style.overflow = '';
        if (addUserLastFocused && typeof addUserLastFocused.focus === 'function') {
            addUserLastFocused.focus();
        }
    };

    document.getElementById('addUserModal').addEventListener('mousedown', function (e) {
        if (e.target === this && !addUserIsSubmitting()) {
            closeAddUserModal();
        }
    });

    // Auto-show session messages
    @if(session('status'))
        Swal.fire({
            title: 'Success',
            text: '{{ session('status') }}',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
        });
    @endif
    @if(session('error'))
        Swal.fire({
            title: 'Error',
            text: '{{ session('error') }}',
            icon: 'error'
        });
    @endif
</script>
@endsection