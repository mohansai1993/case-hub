@extends('layouts.admin')

@section('title', 'CaseHub Client Details')

@section('content')
@php
  $admin = auth('admin')->user();
  $canModerate = $admin->hasPermission('clients.update');
@endphp

<div class="page-head">
  <h1 class="title-back">
    <a href="{{ route('admin.clients') }}" class="back-btn" aria-label="Back to clients">
      <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
    </a>
    Client details
  </h1>
  <p>Manage all registered client accounts.</p>
</div>

<!-- CLIENT HEADER -->
<section class="card client-card">
  <div class="client-main">
    <div class="client-avatar">{{ $client->initials() }}</div>
    <div>
      <h2 class="client-name">{{ $client->name }} <span class="status status-{{ $client->status->value }}">{{ ucfirst($client->status->value) }}</span></h2>
      <p class="client-sub">Client ID: #{{ $client->reference() }}</p>
    </div>
  </div>
  @if ($client->hasVerifiedEmail())
    <span class="verified-pill">
      <svg viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4Z"/><path d="M9 12l2 2 4-4"/></svg>
      Email Verified
    </span>
  @endif
</section>

<div class="detail-grid">

  <!-- LEFT -->
  <div class="detail-col">
    <section class="card">
      <div class="card-head">
        <h2 class="card-title">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M6 16a3 3 0 0 1 6 0M14 9h4M14 13h4"/></svg>
          Client Information
        </h2>
        <span class="section-label">Account Metadata</span>
      </div>

      <div class="info-grid">
        <div class="info-item">
          <span>Full Name</span>
          <strong>{{ $client->name }}</strong>
        </div>
        <div class="info-item">
          <span>Email</span>
          <a href="mailto:{{ $client->email }}" class="info-link">{{ $client->email }}</a>
        </div>
        <div class="info-item">
          <span>Phone Number</span>
          <strong>+91 {{ $client->mobile }}</strong>
        </div>
        <div class="info-item">
          <span>Account Status</span>
          <strong class="{{ $client->isActive() ? 'text-green' : ($client->status->value === 'suspended' ? 'text-red' : '') }}">
            <span class="dot {{ $client->isActive() ? 'dot-green' : ($client->status->value === 'suspended' ? 'dot-red' : '') }}"></span>
            {{ ucfirst($client->status->value) }}
          </strong>
        </div>
        <div class="info-item">
          <span>Registration Date</span>
          <strong>{{ $client->created_at->format('d M Y') }}</strong>
        </div>
        <div class="info-item">
          <span>Email Verified</span>
          <strong>{{ $client->hasVerifiedEmail() ? $client->email_verified_at->format('d M Y') : 'Not verified' }}</strong>
        </div>
      </div>
    </section>
  </div>

  <!-- RIGHT -->
  <div class="detail-col">
    <section class="card">
      <h2 class="card-title">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
        Account Actions
      </h2>

      @if ($canModerate)
        <div class="action-btns">
          @if ($client->status !== \App\Enums\UserStatus::Suspended)
            <button type="button" class="btn-block btn-block-outline" data-moderate="suspend"
                    data-url="{{ route('admin.clients.suspend', $client->user_id) }}" data-name="{{ $client->name }}">
              <svg viewBox="0 0 24 24"><path d="M12 2v10M18.4 6.6a9 9 0 1 1-12.8 0"/></svg>
              Suspend Account
            </button>
          @endif
          @if ($client->status !== \App\Enums\UserStatus::Active)
            <button type="button" class="btn-block btn-block-primary" data-moderate="activate"
                    data-url="{{ route('admin.clients.activate', $client->user_id) }}" data-name="{{ $client->name }}">
              <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></svg>
              Activate Account
            </button>
          @endif
        </div>
      @else
        <p class="empty-msg">You do not have permission to change this account.</p>
      @endif
    </section>

    @include('admin.partials.account-history', ['user' => $client])
  </div>
</div>
@endsection

@push('scripts')
@include('admin.partials.moderation-scripts')
@endpush
