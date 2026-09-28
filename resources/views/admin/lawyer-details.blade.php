@extends('layouts.admin')

@section('title', 'CaseHub Lawyer Details')

@section('content')
@php
  $admin = auth('admin')->user();
  $canModerate = $admin->hasPermission('lawyers.update');
  $profile = $lawyer->lawyerProfile;
  $status = $lawyer->status;
@endphp

<div class="page-head">
  <h1 class="title-back">
    <a href="{{ route('admin.lawyers') }}" class="back-btn" aria-label="Back to lawyers">
      <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
    </a>
    Lawyer details
  </h1>
  <p>Manage all registered lawyer accounts.</p>
</div>

<!-- LAWYER HEADER -->
<section class="card client-card">
  <div class="client-main">
    <div class="client-avatar avatar-online">{{ $lawyer->initials() }}</div>
    <div>
      <h2 class="client-name">
        {{ $lawyer->name }}
        <span class="status status-{{ $status->value }}">{{ ucfirst($status->value) }}</span>
      </h2>
      <p class="client-sub">Lawyer ID: #{{ $lawyer->reference() }} &bull; {{ $profile->location }} &bull; {{ $profile->years_of_experience }} {{ \Illuminate\Support\Str::plural('year', $profile->years_of_experience) }} experience</p>
    </div>
  </div>
</section>

<div class="detail-stack">

  <!-- LAWYER INFORMATION -->
  <section class="card">
    <div class="card-head">
      <div>
        <span class="overline">Profile &amp; Metadata</span>
        <h2>Lawyer Information</h2>
      </div>
      <svg class="head-icon" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 13h18"/></svg>
    </div>

    <div class="info-grid info-grid-3">
      <div class="info-item">
        <span>Full Name</span>
        <strong>{{ $lawyer->name }}</strong>
      </div>
      <div class="info-item">
        <span>Email</span>
        <a href="mailto:{{ $lawyer->email }}" class="info-link">{{ $lawyer->email }}</a>{{ $lawyer->hasVerifiedEmail() ? '' : ' (not verified)' }}
      </div>
      <div class="info-item">
        <span>Phone Number</span>
        <strong>+91 {{ $lawyer->mobile }}</strong>
      </div>
      <div class="info-item">
        <span>Specialization</span>
        <strong>{{ $lawyer->practiceAreas->pluck('name')->join(', ') ?: '—' }}</strong>
      </div>
      <div class="info-item">
        <span>Location / Jurisdiction</span>
        <strong>{{ $profile->location }}</strong>
      </div>
      <div class="info-item">
        <span>Registration Date</span>
        <strong>{{ $lawyer->created_at->format('d M Y') }}</strong>
      </div>
      <div class="info-item">
        <span>Account Status</span>
        <strong><span class="status status-{{ $status->value }}">{{ ucfirst($status->value) }}</span></strong>
      </div>
    </div>

    @if ($profile->bio)
      <div class="info-item" style="margin-top: 20px;">
        <span>Professional Bio</span>
        <strong style="font-weight: 500; white-space: pre-line;">{{ $profile->bio }}</strong>
      </div>
    @endif
  </section>

  <!-- ACCOUNT MANAGEMENT -->
  <section class="card">
    <div class="card-head">
      <div>
        <span class="overline">Administrative Controls</span>
        <h2>Account Management</h2>
      </div>
      <svg class="head-icon" viewBox="0 0 24 24"><circle cx="11" cy="8" r="4"/><path d="M3 21a8 8 0 0 1 11-7.4"/><circle cx="18" cy="17" r="3"/></svg>
    </div>

    @if ($canModerate)
      <div class="inline-btns">
        @if ($status !== \App\Enums\UserStatus::Suspended)
          <button type="button" class="btn-inline btn-danger-soft" data-moderate="suspend"
                  data-url="{{ route('admin.lawyers.suspend', $lawyer->user_id) }}" data-name="{{ $lawyer->name }}">
            <svg viewBox="0 0 24 24"><path d="M12 3l9 16H3L12 3Z"/><path d="M12 10v4M12 17h.01"/></svg>
            Suspend Account
          </button>
        @endif
        @if ($status !== \App\Enums\UserStatus::Active)
          <button type="button" class="btn-inline btn-block-primary" data-moderate="activate"
                  data-url="{{ route('admin.lawyers.activate', $lawyer->user_id) }}" data-name="{{ $lawyer->name }}">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></svg>
            Activate Account
          </button>
        @endif
      </div>
    @else
      <p class="empty-msg">You do not have permission to change this account.</p>
    @endif
  </section>

  @include('admin.partials.account-history', ['user' => $lawyer])
</div>
@endsection

@push('scripts')
@include('admin.partials.moderation-scripts')
@endpush
