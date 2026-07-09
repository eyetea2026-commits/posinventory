@extends('admin.layout')

@section('header')
    <div class="header-title">
        <h1>User Details</h1>
        <p>{{ $user->full_name }}</p>
    </div>
@endsection

@section('content')
<div class="card glass-card" style="padding: 24px;">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:24px; flex-wrap:wrap;">
        <div>
            <h2 style="margin:0; color:#f8fafc;">{{ $user->full_name }}</h2>
            <p style="margin:4px 0 0; color:#64748b;">{{ $user->email }}</p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-warning">
                <i class="fas fa-edit"></i> Update User
            </a>
        </div>
    </div>

    <div style="display:grid; gap:16px;">
        <div style="background:#1e293b; border:1px solid #334155; border-radius:16px; padding:16px;">
            <h3 style="margin:0 0 12px; color:#e2e8f0;">Personal Information</h3>
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px;">
                <div><label style="display:block; font-size:0.7rem; text-transform:uppercase; color:#64748b;">First Name</label><span style="color:#f8fafc;">{{ $user->first_name ?? 'N/A' }}</span></div>
                <div><label style="display:block; font-size:0.7rem; text-transform:uppercase; color:#64748b;">Last Name</label><span style="color:#f8fafc;">{{ $user->last_name ?? 'N/A' }}</span></div>
                <div><label style="display:block; font-size:0.7rem; text-transform:uppercase; color:#64748b;">Age</label><span style="color:#f8fafc;">{{ $user->age ?? 'N/A' }}</span></div>
                <div><label style="display:block; font-size:0.7rem; text-transform:uppercase; color:#64748b;">Gender</label><span style="color:#f8fafc;">{{ $user->gender ?? 'N/A' }}</span></div>
                <div style="grid-column:1 / -1;"><label style="display:block; font-size:0.7rem; text-transform:uppercase; color:#64748b;">Address</label><span style="color:#f8fafc;">{{ $user->address ?? 'N/A' }}</span></div>
            </div>
        </div>

        <div style="background:#1e293b; border:1px solid #334155; border-radius:16px; padding:16px;">
            <h3 style="margin:0 0 12px; color:#e2e8f0;">Account Information</h3>
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px;">
                <div><label style="display:block; font-size:0.7rem; text-transform:uppercase; color:#64748b;">Username</label><span style="color:#f8fafc;">{{ $user->name }}</span></div>
                <div><label style="display:block; font-size:0.7rem; text-transform:uppercase; color:#64748b;">Email</label><span style="color:#f8fafc;">{{ $user->email }}</span></div>
                <div><label style="display:block; font-size:0.7rem; text-transform:uppercase; color:#64748b;">Role</label><span class="badge {{ $user->isAdmin() ? 'badge-admin' : 'badge-cashier' }}">{{ $user->role?->role_name ?? 'N/A' }}</span></div>
                <div><label style="display:block; font-size:0.7rem; text-transform:uppercase; color:#64748b;">Status</label><span class="status-badge {{ $user->is_active ? 'status-active' : 'status-inactive' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span></div>
            </div>
        </div>

        <div style="background:#1e293b; border:1px solid #334155; border-radius:16px; padding:16px;">
            <h3 style="margin:0 0 12px; color:#e2e8f0;">System Information</h3>
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px;">
                <div><label style="display:block; font-size:0.7rem; text-transform:uppercase; color:#64748b;">Date Created</label><span style="color:#f8fafc;">{{ $user->created_at->format('F j, Y h:i A') }}</span></div>
                <div><label style="display:block; font-size:0.7rem; text-transform:uppercase; color:#64748b;">Last Updated</label><span style="color:#f8fafc;">{{ $user->updated_at->format('F j, Y h:i A') }}</span></div>
            </div>
        </div>
    </div>
</div>
@endsection
