@extends('layouts.admin')

@section('title', 'CaseHub ' . $role->name)

@section('content')
<div class="page-head page-head-row">
  <div>
    <h1 class="title-back">
      <a href="{{ route('admin.roles') }}" class="back-btn" aria-label="Back to roles">
        <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
      </a>
      Role Details
    </h1>
    <p>View and manage role information and permissions.</p>
  </div>
  <a href="{{ route('admin.roles.edit', $role) }}" class="btn-primary">
    <svg class="btn-icon" viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
    Edit Role
  </a>
</div>

@php
  $granted = $role->permissionKeys();
  $groups = collect(config('permissions.modules'))
    ->map(fn ($module) => [
      'label' => $module['label'],
      'granted' => collect($module['permissions'])->filter(fn ($label, $key) => in_array($key, $granted, true)),
    ])
    ->filter(fn ($group) => $group['granted']->isNotEmpty());
@endphp

<section class="card role-card">
  <div class="role-top">
    <div class="client-main">
      <span class="role-icon role-icon-lg"><svg viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4Z"/><path d="M9 12l2 2 4-4"/></svg></span>
      <div>
        <h2 class="client-name">{{ $role->name }} <span class="tier-pill">{{ $role->tag ?? 'Custom Role' }}</span></h2>
        <p class="client-sub">{{ $role->description ?: 'No description added.' }}</p>
      </div>
    </div>
    <span class="status {{ $role->is_active ? 'status-active' : 'status-suspended' }}">{{ $role->is_active ? 'Active' : 'Disabled' }}</span>
  </div>

  <div class="role-meta">
    <div class="info-item">
      <span>Status</span>
      <strong class="{{ $role->is_active ? 'text-green' : 'text-red' }}"><span class="dot {{ $role->is_active ? 'dot-green' : 'dot-red' }}"></span> {{ $role->is_active ? 'Active' : 'Disabled' }}</strong>
    </div>
    <div class="info-item">
      <span>Users Assigned</span>
      <strong><span class="users-pill"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M16 3.1a4 4 0 0 1 0 7.8M22 21a7 7 0 0 0-4-6.3"/></svg>{{ $role->admins_count }} User{{ $role->admins_count === 1 ? '' : 's' }}</span></strong>
    </div>
    <div class="info-item">
      <span>Created Date</span>
      <strong><svg class="inline-icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/></svg> {{ $role->created_at->format('d M Y') }}</strong>
    </div>
  </div>
</section>

<section class="card list-card">
  <h2 class="card-title">
    <svg viewBox="0 0 24 24"><circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3M15 8l2 2"/></svg>
    Assigned Permissions
  </h2>
  <p class="card-sub">Configured operational access granted to this role.</p>

  @if ($groups->isNotEmpty())
    <div class="perm-groups">
      @foreach ($groups as $group)
        <div class="perm-group">
          <div class="perm-group-head">
            <strong>{{ $group['label'] }}</strong>
            <span>{{ $group['granted']->count() }} permission{{ $group['granted']->count() === 1 ? '' : 's' }}</span>
          </div>
          <ul>
            @foreach ($group['granted'] as $label)
              <li><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></svg>{{ $label }}</li>
            @endforeach
          </ul>
        </div>
      @endforeach
    </div>
  @else
    <p class="empty-msg">No permissions assigned to this role.</p>
  @endif
</section>

<section class="card list-card">
  <div class="card-head">
    <h2 class="card-title">
      <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M16 3.1a4 4 0 0 1 0 7.8M22 21a7 7 0 0 0-4-6.3"/></svg>
      Assigned Users <span class="count-pill">{{ str_pad((string) $admins->count(), 2, '0', STR_PAD_LEFT) }}</span>
    </h2>
    <span class="card-note">Staff members holding this role</span>
  </div>

  @if ($admins->isNotEmpty())
    <div class="table-wrap">
      <table class="clients-table users-table">
        <thead>
          <tr><th>User Name</th><th>Email</th><th>Status</th><th class="col-actions">Action</th></tr>
        </thead>
        <tbody>
          @foreach ($admins as $member)
            <tr>
              <td><div class="person"><span class="mini-avatar">{{ $member->initials() }}</span><strong>{{ $member->name }}</strong></div></td>
              <td>{{ $member->email }}</td>
              <td><span class="status {{ $member->isActive() ? 'status-active' : 'status-inactive' }}">{{ $member->status->label() }}</span></td>
              <td class="col-actions"><a href="{{ route('admin.staff-details', $member) }}" class="text-link">View &rsaquo;</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @else
    <p class="empty-msg">No users are assigned to this role yet.</p>
  @endif
</section>

<section class="card danger-card">
  <div>
    <strong class="danger-title">
      <svg viewBox="0 0 24 24"><path d="M12 3l9 16H3L12 3Z"/><path d="M12 10v4M12 17h.01"/></svg>
      {{ $role->is_active ? 'Disable Role' : 'Enable Role' }}
    </strong>
    <p>
      @if ($role->is_active)
        Temporarily revokes all active permissions for the {{ $admins->count() }} assigned user{{ $admins->count() === 1 ? '' : 's' }} without deleting the role definition.
      @else
        Restores all permissions of this role for its assigned users.
      @endif
    </p>
  </div>
  <button
    type="button"
    class="btn-inline {{ $role->is_active ? 'btn-outline-danger' : 'btn-block-primary' }}"
    data-moderate="{{ $role->is_active ? 'disable-role' : 'enable-role' }}"
    data-url="{{ route('admin.roles.toggle', $role) }}"
    data-name="{{ $role->name }}"
  >
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/></svg>
    {{ $role->is_active ? 'Disable Role' : 'Enable Role' }}
  </button>
</section>
@endsection

@push('scripts')
@include('admin.partials.moderation-scripts')
@endpush
