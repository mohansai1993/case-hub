@extends('layouts.admin')

@section('title', 'CaseHub ' . $member->name)

@section('content')
@php
  $active = $member->isActive();
@endphp

<div class="page-head page-head-row">
  <div>
    <h1 class="title-back">
      <a href="{{ route('admin.staff') }}" class="back-btn" aria-label="Back to staff">
        <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
      </a>
      Staff Details
    </h1>
    <p>View and manage user account details and access credentials.</p>
  </div>
  <a href="{{ route('admin.staff.edit', $member) }}" class="btn-sm btn-sm-lg">
    <svg viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
    Edit User
  </a>
</div>

<section class="card role-card">
  <div class="client-main">
    <div class="client-avatar client-avatar-solid">{{ $member->initials() }}</div>
    <div>
      <h2 class="client-name">
        {{ $member->name }}
        <span class="verify {{ $active ? 'verify-verified' : 'verify-pending' }}">{{ $active ? 'Active User' : 'Inactive User' }}</span>
      </h2>
      <p class="client-sub">
        @if ($active)
          Account active since {{ $member->created_at->format('F Y') }}
        @else
          Account currently inactive
        @endif
      </p>
    </div>
  </div>

  <div class="info-grid info-grid-3 staff-info">
    <div class="info-item">
      <span>Full Name</span>
      <strong>{{ $member->name }}</strong>
    </div>
    <div class="info-item">
      <span>Email Address</span>
      <a href="mailto:{{ $member->email }}" class="info-link">
        {{ $member->email }}
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></svg>
      </a>
    </div>
    <div class="info-item">
      <span>Mobile Number</span>
      <strong>{{ $member->mobile ?? '—' }}</strong>
    </div>
    <div class="info-item">
      <span>Account Status</span>
      <strong><span class="status {{ $active ? 'status-active' : 'status-inactive' }}">{{ $member->status->label() }}</span></strong>
    </div>
    <div class="info-item">
      <span>Created Date</span>
      <strong>{{ $member->created_at->format('d M Y') }}</strong>
    </div>
    <div class="info-item">
      <span>Last Login Session</span>
      <strong>{{ $member->last_login_at?->diffForHumans() ?? 'Never' }}</strong>
      @if ($member->last_login_ip)
        <small class="info-sub">from IP {{ $member->last_login_ip }}</small>
      @endif
    </div>
  </div>
</section>

<section class="card list-card">
  <h2>Access &amp; Account Settings</h2>
  <p class="card-sub">Configure status, assigned organizational role, and credential resets.</p>

  <div class="access-row">
    <div class="setting-text">
      <strong>Account Status <span class="section-label {{ $active ? 'text-green' : '' }}">{{ $active ? 'Active' : 'Inactive' }}</span></strong>
      <small>
        @if ($active)
          Account has active access to the CaseHub platform and internal operational cases.
        @else
          Account is suspended. The user cannot sign in until it is activated.
        @endif
      </small>
    </div>
    <label class="switch">
      <input
        type="checkbox"
        aria-label="Account status"
        {{ $active ? 'checked' : '' }}
        data-moderate="{{ $active ? 'deactivate-staff' : 'activate-staff' }}"
        data-url="{{ route('admin.staff.toggle', $member) }}"
        data-name="{{ $member->name }}"
      />
      <span class="switch-track"></span>
    </label>
  </div>

  <div class="form-row access-role">
    <div class="form-label-row"><label for="role-select">Assigned Role</label></div>
    <select class="input" id="role-select" data-url="{{ route('admin.staff.update-role', $member) }}">
      @foreach (\App\Models\Role::where('is_system', false)->orderBy('name')->get() as $role)
        <option value="{{ $role->id }}" @selected($role->id === $member->role_id) @disabled(! $role->is_active && $role->id !== $member->role_id)>
          {{ $role->name }}@unless ($role->is_active) (Disabled) @endunless
        </option>
      @endforeach
    </select>
    <small class="hint">
      Changing the role updates user access based on the configured permissions matrix for that role.
      @if ($member->role)
        <a href="{{ route('admin.role-details', $member->role) }}" class="text-link">View {{ $member->role->name }} permissions &rsaquo;</a>
      @endif
    </small>
  </div>

  <div class="security-box">
    <div class="setting-text">
      <strong class="card-title">
        <svg viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4Z"/></svg>
        Security &amp; Authentication
      </strong>
      <small>Sends a password reset code to <b>{{ $member->email }}</b>.</small>
    </div>
    <button type="button" class="btn-sm btn-sm-lg" id="reset-btn" data-url="{{ route('admin.staff.reset-password', $member) }}">
      <svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7L21 8M21 3v5h-5"/></svg>
      Reset Password
    </button>
  </div>
</section>

<section class="card danger-card danger-card-end">
  <button
    type="button"
    class="btn-inline {{ $active ? 'btn-danger-soft' : 'btn-block-primary' }}"
    data-moderate="{{ $active ? 'deactivate-staff' : 'activate-staff' }}"
    data-url="{{ route('admin.staff.toggle', $member) }}"
    data-name="{{ $member->name }}"
  >
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/></svg>
    {{ $active ? 'Deactivate User' : 'Activate User' }}
  </button>
</section>
@endsection

@push('scripts')
@include('admin.partials.moderation-scripts')
<script>
(function () {
  "use strict";

  var CSRF = document.querySelector('meta[name="csrf-token"]').content;

  document.getElementById("role-select").addEventListener("change", function (e) {
    var select = e.target;
    var roleId = select.value;

    fetch(select.dataset.url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
        "X-CSRF-TOKEN": CSRF,
        "X-Requested-With": "XMLHttpRequest"
      },
      body: JSON.stringify({ role_id: roleId })
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (body) { return { ok: res.ok, body: body }; });
    }).then(function (result) {
      if (!result.ok) {
        showToast(result.body.message || "Could not change the role.", "error");
        return;
      }
      showToast(result.body.message, "success");
    });
  });

  document.getElementById("reset-btn").addEventListener("click", function (e) {
    var btn = e.target.closest("button");

    fetch(btn.dataset.url, {
      method: "POST",
      headers: {
        "Accept": "application/json",
        "X-CSRF-TOKEN": CSRF,
        "X-Requested-With": "XMLHttpRequest"
      }
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (body) { return { ok: res.ok, body: body }; });
    }).then(function (result) {
      showToast(result.body.message || "Request sent.", result.ok ? "success" : "error");
    });
  });
})();
</script>
@endpush
