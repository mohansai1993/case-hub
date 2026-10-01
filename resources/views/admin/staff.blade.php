@extends('layouts.admin')

@section('title', 'CaseHub Staff')

@section('content')
<div class="page-head page-head-row">
  <div>
    <h1>Staff</h1>
    <p>Manage users and their assigned roles.</p>
  </div>
  <a href="{{ route('admin.create-staff') }}" class="btn-primary">+ Create Staff</a>
</div>

<section class="card list-card">
  <form method="GET" action="{{ route('admin.staff') }}" class="toolbar">
    <label class="search-box">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search user by name or email" maxlength="100" />
    </label>

    <div class="filters">
      <select name="role" id="role-filter" aria-label="Role" data-autosubmit>
        <option value="">All Roles</option>
        @foreach ($roles as $role)
          <option value="{{ $role->id }}" @selected(($filters['role'] ?? null) == $role->id)>{{ $role->name }}</option>
        @endforeach
      </select>
      <select name="status" id="status-filter" aria-label="Status" data-autosubmit>
        <option value="">All Statuses</option>
        @foreach (\App\Enums\AdminStatus::cases() as $status)
          <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
        @endforeach
      </select>
      @if (! empty($filters['q']) || ! empty($filters['role']) || ! empty($filters['status']))
        <a href="{{ route('admin.staff') }}" class="btn-view">Clear</a>
      @endif
    </div>
  </form>

  <div class="table-wrap">
    <table class="clients-table">
      <thead>
        <tr>
          <th>Staff Name</th>
          <th>Email</th>
          <th>Assigned Role</th>
          <th>Status</th>
          <th>Created Date</th>
          <th>Last Login</th>
          <th class="col-actions">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($staff as $member)
          <tr>
            <td>
              <div class="person">
                <span class="mini-avatar">{{ $member->initials() }}</span>
                <strong>{{ $member->name }}</strong>
              </div>
            </td>
            <td>{{ $member->email }}</td>
            <td>
              @if ($member->role)
                <span class="role-pill rp-{{ $member->role_id % 5 }}">{{ $member->role->name }}</span>
              @else
                <span class="role-pill">No role</span>
              @endif
            </td>
            <td><span class="status {{ $member->isActive() ? 'status-active' : 'status-inactive' }}">{{ $member->status->label() }}</span></td>
            <td>{{ $member->created_at->format('d M Y') }}</td>
            <td>{{ $member->last_login_at?->diffForHumans() ?? 'Never' }}</td>
            <td class="col-actions">
              <div class="row-actions">
                <a href="{{ route('admin.staff-details', $member) }}" class="btn-view">
                  <svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                  View
                </a>
                <a href="{{ route('admin.staff.edit', $member) }}" class="btn-sm">
                  <svg viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                  Edit
                </a>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="7"><p class="empty-msg">{{ $total ? 'No staff members match your search.' : 'No staff members yet.' }}</p></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="table-footer">
    <span class="access-active">
      <svg class="inline-icon" viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4Z"/></svg>
      {{ $total }} total staff member{{ $total === 1 ? '' : 's' }} provisioned &bull; Access Control Active
    </span>
    @if ($staff->total())
      <span>Showing {{ $staff->firstItem() }} to {{ $staff->lastItem() }} of {{ number_format($staff->total()) }}</span>
    @endif
  </div>

  {{ $staff->onEachSide(1)->links('pagination.admin') }}
</section>
@endsection

@push('scripts')
<script>
document.querySelectorAll("[data-autosubmit]").forEach((el) => {
  el.addEventListener("change", () => el.form.submit());
});
</script>
@endpush
