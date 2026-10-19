@extends('layouts.admin')

@section('title', 'CaseHub Case Assignment Request')

@section('content')
@php
  $admin = auth('admin')->user();
  $canModerate = $admin->hasPermission('cases.update');
  $case = $assignmentRequest->case;
  $lawyer = $assignmentRequest->lawyer;
  $status = $assignmentRequest->status;
@endphp

<div class="page-head">
  <h1 class="title-back">
    <a href="{{ route('admin.case-assignment-requests') }}" class="back-btn" aria-label="Back to case assignment requests">
      <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
    </a>
    Case Assignment Request
  </h1>
  <p>Review which lawyer a client picked before it reaches the lawyer.</p>
</div>

<section class="card client-card">
  <div class="client-main">
    <div>
      <h2 class="client-name">
        {{ $case->caseNumber() }} &middot; {{ $case->title }}
        @php
          $statusClass = match ($status->value) {
            'approved' => 'status-active',
            'rejected' => 'status-rejected',
            default => 'status-pending',
          };
        @endphp
        <span class="status {{ $statusClass }}">{{ ucfirst($status->value) }}</span>
      </h2>
      <p class="client-sub">Requested {{ $assignmentRequest->created_at->format('d M Y, H:i') }}</p>
    </div>
  </div>
</section>

<div class="detail-stack">

  <section class="card">
    <div class="card-head">
      <div>
        <span class="overline">Case</span>
        <h2>Case Information</h2>
      </div>
      <svg class="head-icon" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 13h18"/></svg>
    </div>

    <div class="info-grid info-grid-3">
      <div class="info-item">
        <span>Case ID</span>
        <strong>{{ $case->caseNumber() }}</strong>
      </div>
      <div class="info-item">
        <span>Case Title</span>
        <strong>{{ $case->title }}</strong>
      </div>
      <div class="info-item">
        <span>Legal Issue</span>
        <strong>{{ $case->practiceArea?->name ?? '—' }}</strong>
      </div>
      <div class="info-item">
        <span>Client</span>
        <strong>{{ $case->client->name }}</strong>
      </div>
      <div class="info-item">
        <span>Location</span>
        <strong>{{ $case->location ?? '—' }}</strong>
      </div>
      <div class="info-item">
        <span>Incident Date</span>
        <strong>{{ $case->incident_date?->format('d M Y') ?? '—' }}</strong>
      </div>
    </div>

    @if ($case->description)
      <div class="info-item" style="margin-top: 20px;">
        <span>Case Description</span>
        <strong style="font-weight: 500; white-space: pre-line;">{{ $case->description }}</strong>
      </div>
    @endif
  </section>

  <section class="card">
    <div class="card-head">
      <div>
        <span class="overline">Client's pick</span>
        <h2>Requested Lawyer</h2>
      </div>
      <svg class="head-icon" viewBox="0 0 24 24"><path d="M12 3v18M7 21h10M5 7h14"/><path d="M5 7l-3 6h6L5 7Zm14 0l-3 6h6l-3-6Z"/></svg>
    </div>

    <div class="info-grid info-grid-3">
      <div class="info-item">
        <span>Full Name</span>
        <strong>{{ $lawyer->name }}</strong>
      </div>
      <div class="info-item">
        <span>Email</span>
        <a href="mailto:{{ $lawyer->email }}" class="info-link">{{ $lawyer->email }}</a>
      </div>
      <div class="info-item">
        <span>Specialization</span>
        <strong>{{ $lawyer->practiceAreas->pluck('name')->join(', ') ?: '—' }}</strong>
      </div>
      <div class="info-item">
        <span>Experience</span>
        <strong>{{ $lawyer->lawyerProfile?->years_of_experience }} {{ \Illuminate\Support\Str::plural('yr', $lawyer->lawyerProfile?->years_of_experience ?? 0) }}</strong>
      </div>
    </div>
  </section>

  <section class="card">
    <div class="card-head">
      <div>
        <span class="overline">Administrative Controls</span>
        <h2>Admin Review &amp; Decision</h2>
      </div>
      <svg class="head-icon" viewBox="0 0 24 24"><circle cx="11" cy="8" r="4"/><path d="M3 21a8 8 0 0 1 11-7.4"/><circle cx="18" cy="17" r="3"/></svg>
    </div>

    @if ($status->value !== 'pending')
      <p class="empty-msg">
        This request was {{ $status->value }} by {{ $assignmentRequest->admin?->name ?? 'a removed admin' }}
        on {{ $assignmentRequest->decided_at?->format('d M Y, H:i') }}.
        @if ($assignmentRequest->reason)
          Reason: {{ $assignmentRequest->reason }}
        @endif
      </p>
    @elseif ($canModerate)
      <div class="inline-btns">
        <button type="button" class="btn-inline btn-block-primary" data-moderate="approve-case-assignment"
                data-url="{{ route('admin.case-assignment-requests.approve', $assignmentRequest->id) }}" data-name="{{ $lawyer->name }}">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></svg>
          Approve Assignment
        </button>
        <button type="button" class="btn-inline btn-danger-soft" data-moderate="reject-case-assignment"
                data-url="{{ route('admin.case-assignment-requests.reject', $assignmentRequest->id) }}" data-name="{{ $lawyer->name }}">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
          Reject Assignment
        </button>
      </div>
    @else
      <p class="empty-msg">You do not have permission to decide this request.</p>
    @endif
  </section>
</div>
@endsection

@push('scripts')
@include('admin.partials.moderation-scripts')
@endpush
