@extends('admin.layout')

@push('styles')
    <style>
        .activity-filters { display: grid; grid-template-columns: 2fr repeat(3, 1fr); gap: 12px; flex: 1; }
        .activity-filters .search-box { max-width: none; min-width: 0; }
        .activity-date-row { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-top: 12px; }
        .activity-date-row label { color: var(--text-secondary, #94a3b8); font-size: 0.85rem; font-weight: 600; }
        .activity-date-row .form-input { width: auto; padding: 10px 14px; }
        .activity-time { white-space: nowrap; color: var(--text-secondary, #94a3b8); font-size: 0.85rem; }
        .activity-user-name { font-weight: 600; }
        .activity-action { font-weight: 600; }
        .activity-description { color: var(--text-secondary, #cbd5e1); }
        .activity-results-meta { color: var(--text-secondary, #94a3b8); font-size: 0.85rem; margin-bottom: 12px; }
        @media (max-width: 900px) {
            .activity-filters { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 560px) {
            .activity-filters { grid-template-columns: 1fr; }
        }
    </style>
@endpush

@section('header')
    <div class="header-title">
        <p style="margin: 0 0 4px; font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em;">User Management</p>
        <h1>Activity Log</h1>
        <p>Monitor every recorded action by admins and cashiers across the system</p>
    </div>
@endsection

@section('content')
    @php
        $controller = \App\Http\Controllers\Admin\ActivityLogController::class;
        $hasFilters = collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty();
    @endphp

    <div class="card">
        <form method="GET" action="{{ route('admin.activity-logs.index') }}" id="activityFilterForm">
            <div class="toolbar" style="margin-bottom: 0;">
                <div class="activity-filters">
                    <div class="search-box">
                        <i class="search-icon fas fa-search"></i>
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="search-input" placeholder="Search descriptions..." maxlength="100">
                    </div>
                    <select name="module" class="form-select" aria-label="Module">
                        <option value="">All Modules</option>
                        @foreach($modules as $prefix => $label)
                            <option value="{{ $prefix }}" {{ ($filters['module'] ?? '') === $prefix ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="role" class="form-select" aria-label="Role">
                        <option value="">All Roles</option>
                        <option value="admin" {{ ($filters['role'] ?? '') === 'admin' ? 'selected' : '' }}>Admin</option>
                        <option value="cashier" {{ ($filters['role'] ?? '') === 'cashier' ? 'selected' : '' }}>Cashier</option>
                    </select>
                    <select name="user_id" class="form-select" aria-label="User">
                        <option value="">All Users</option>
                        @foreach($users as $logUser)
                            <option value="{{ $logUser->id }}" {{ (string) ($filters['user_id'] ?? '') === (string) $logUser->id ? 'selected' : '' }}>{{ $logUser->full_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="activity-date-row">
                <label for="activityDateFrom">From</label>
                <input type="date" id="activityDateFrom" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-input" max="{{ now()->toDateString() }}">
                <label for="activityDateTo">To</label>
                <input type="date" id="activityDateTo" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-input" max="{{ now()->toDateString() }}">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                @if($hasFilters)
                    <a href="{{ route('admin.activity-logs.index') }}" class="btn btn-secondary" style="text-decoration: none;"><i class="fas fa-times"></i> Clear</a>
                @endif
            </div>
        </form>

        @if($filterError)
            <div class="alert alert-danger" style="margin-top: 16px;">
                <i class="fas fa-circle-exclamation"></i>
                {{ $filterError }}
            </div>
        @endif

        <hr style="border: none; border-top: 1px solid rgba(148, 163, 184, 0.15); margin: 20px 0 16px;">

        <p class="activity-results-meta">
            @if($logs->total() > 0)
                Showing {{ number_format($logs->firstItem()) }}–{{ number_format($logs->lastItem()) }} of {{ number_format($logs->total()) }} recorded {{ \Illuminate\Support\Str::plural('action', $logs->total()) }}
            @else
                No recorded actions
            @endif
        </p>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        @php
                            $roleName = $log->user?->role?->role_name;
                        @endphp
                        <tr>
                            <td class="activity-time">{{ $log->DateRecorded?->format('M d, Y g:i A') ?? 'N/A' }}</td>
                            <td class="activity-user-name">{{ $log->user?->full_name ?? 'System / Unknown' }}</td>
                            <td>
                                @if($roleName === 'admin')
                                    <span class="badge badge-primary">Admin</span>
                                @elseif($roleName === 'cashier')
                                    <span class="badge badge-info">Cashier</span>
                                @else
                                    <span class="badge badge-secondary">—</span>
                                @endif
                            </td>
                            <td><span class="badge badge-secondary">{{ $controller::moduleLabel(\Illuminate\Support\Str::before($log->Action, '.')) }}</span></td>
                            <td class="activity-action">{{ $controller::actionLabel($log->Action) }}</td>
                            <td class="activity-description">{{ $log->Description }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div class="empty-icon"><i class="fas fa-clock-rotate-left"></i></div>
                                    <p class="empty-title">{{ $hasFilters ? 'No Matching Activity' : 'No Activity Yet' }}</p>
                                    <p class="empty-text">{{ $hasFilters ? 'Try different filters or clear them.' : 'Actions taken across the system will be recorded here.' }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            @php
                $windowStart = max(1, $logs->currentPage() - 2);
                $windowEnd = min($logs->lastPage(), $logs->currentPage() + 2);
            @endphp
            <div class="pagination">
                @if($logs->onFirstPage())
                    <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                @else
                    <a href="{{ $logs->previousPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                @endif

                @if($windowStart > 1)
                    <a href="{{ $logs->url(1) }}" class="pagination-link">1</a>
                    @if($windowStart > 2)
                        <span class="pagination-link disabled">&hellip;</span>
                    @endif
                @endif

                @foreach($logs->getUrlRange($windowStart, $windowEnd) as $page => $url)
                    <a href="{{ $url }}" class="pagination-link {{ $page == $logs->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                @endforeach

                @if($windowEnd < $logs->lastPage())
                    @if($windowEnd < $logs->lastPage() - 1)
                        <span class="pagination-link disabled">&hellip;</span>
                    @endif
                    <a href="{{ $logs->url($logs->lastPage()) }}" class="pagination-link">{{ $logs->lastPage() }}</a>
                @endif

                @if($logs->hasMorePages())
                    <a href="{{ $logs->nextPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                @else
                    <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                @endif
            </div>
        @endif
    </div>

    <script>
        // Dropdown filters apply as soon as they change; the search box and
        // date range use the Apply button (or Enter) so a half-typed value
        // doesn't fire a request on every keystroke.
        document.querySelectorAll('#activityFilterForm select').forEach(function (select) {
            select.addEventListener('change', function () {
                document.getElementById('activityFilterForm').submit();
            });
        });
    </script>
@endsection
