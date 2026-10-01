@extends('layouts.admin')

@section('title', 'CaseHub Subscriptions')

@section('content')
<div class="page-head">
  <h1>Subscriptions</h1>
  <p>Manage client subscriptions and subscription plans.</p>
</div>

<!-- SUBSCRIPTION PLANS -->
<section class="card plans-card">
  <div class="card-head">
    <div class="plans-title">
      <span class="stat-icon"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></span>
      <div>
        <h2>Subscription Plans</h2>
        <p>Configure legal client storage tiers, pricing models, and docket quotas.</p>
      </div>
    </div>
    <a href="{{ route('admin.create-plan') }}" class="btn-primary">+ Create Plan</a>
  </div>

  <div class="plan-grid" id="plan-grid">
    @foreach ($plans as $plan)
      <article class="plan-card{{ $plan->is_popular ? ' is-popular' : '' }}{{ $plan->is_active ? '' : ' is-inactive' }}">
        @if ($plan->is_popular)
          <span class="popular-tag">Popular Tier</span>
        @endif
        <div class="plan-card-top">
          <span class="plan-name">
            <span class="dot dot-blue"></span>{{ $plan->name }}
            @unless ($plan->is_active)
              <span class="status status-inactive">Inactive</span>
            @endunless
          </span>
          <span class="pill-soft">Monthly subscription</span>
        </div>
        <div class="plan-price">{{ config('billing.currency_symbol') }}{{ number_format($plan->price) }} <small>/ month</small></div>
        <span class="storage-chip">
          <svg viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/></svg>
          {{ $plan->storageLabel() }} Storage
        </span>
        <p class="plan-desc">{{ $plan->description }}</p>
        <div class="plan-actions">
          <a href="{{ route('admin.plans.edit', $plan) }}" class="btn-sm">
            <svg viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
            Edit
          </a>
          <button type="button" class="btn-sm btn-sm-danger" data-delete="{{ $plan->id }}" data-name="{{ $plan->name }}">
            <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
            Delete
          </button>
        </div>
      </article>
    @endforeach
  </div>
  <p class="empty-msg" id="plans-empty" @unless ($plans->isEmpty()) hidden @endunless>No plans yet. Create your first plan.</p>
</section>

<!-- SUBSCRIPTIONS LIST -->
<section class="card list-card">
  <form method="GET" action="{{ route('admin.subscriptions') }}" class="toolbar">
    <label class="search-box">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search by client name or email..." maxlength="100" />
    </label>

    <div class="filters">
      <select name="plan" aria-label="Plan" data-autosubmit>
        <option value="">All Plans</option>
        @foreach ($plans as $plan)
          <option value="{{ $plan->id }}" @selected(($filters['plan'] ?? null) == $plan->id)>{{ $plan->name }}</option>
        @endforeach
      </select>
      <select name="status" aria-label="Status" data-autosubmit>
        <option value="">All Statuses</option>
        @foreach (\App\Enums\SubscriptionStatus::cases() as $status)
          <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ ucfirst($status->value) }}</option>
        @endforeach
      </select>
      @if (! empty($filters['q']) || ! empty($filters['plan']) || ! empty($filters['status']))
        <a href="{{ route('admin.subscriptions') }}" class="btn-view">Clear</a>
      @endif
    </div>
  </form>

  <div class="table-wrap">
    <table class="clients-table">
      <thead>
        <tr>
          <th>Client Name</th>
          <th>Subscription Plan</th>
          <th>Storage</th>
          <th>Status</th>
          <th>Started</th>
          <th>Renews / Grace Ends</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($subscriptions as $subscription)
          @php
            $used = $quota->usedBytes($subscription->client);
            $limit = $subscription->plan->storageLimitBytes();
            $percent = $limit > 0 ? min(100, round($used / $limit * 100)) : 0;
          @endphp
          <tr>
            <td>
              <div class="person">
                <span class="mini-avatar">{{ $subscription->client->initials() }}</span>
                <div><strong>{{ $subscription->client->name }}</strong><small>{{ $subscription->client->email }}</small></div>
              </div>
            </td>
            <td><span class="plan">{{ $subscription->plan->name }}</span></td>
            <td>
              <div class="storage-cell">
                <span>{{ number_format($used / (1024 ** 2), 1) }} MB <small class="inline-muted">/ {{ $subscription->plan->storageLabel() }}</small></span>
                <div class="progress"><span style="width:{{ $percent }}%"></span></div>
              </div>
            </td>
            <td><span class="status {{ $subscription->isActive() ? 'status-active' : ($subscription->isRestricted() ? 'status-suspended' : 'status-inactive') }}">{{ ucfirst($subscription->status->value) }}</span></td>
            <td>{{ $subscription->created_at->format('d M Y') }}</td>
            <td>
              @if ($subscription->isActive())
                {{ $subscription->current_period_ends_at->format('d M Y') }}
              @elseif ($subscription->grace_ends_at)
                {{ $subscription->grace_ends_at->format('d M Y') }}
              @else
                &mdash;
              @endif
            </td>
            <td><a href="{{ route('admin.client-details', $subscription->client_id) }}" class="btn-view">View</a></td>
          </tr>
        @empty
          <tr><td colspan="7"><p class="empty-msg">{{ $totalSubscriptions ? 'No subscriptions match your search.' : 'No clients have subscribed to a plan yet.' }}</p></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="table-footer">
    <span>
      @if ($subscriptions->total())
        Showing {{ $subscriptions->firstItem() }} to {{ $subscriptions->lastItem() }} of {{ number_format($subscriptions->total()) }} {{ $subscriptions->total() === $totalSubscriptions ? 'subscriptions' : 'matching subscriptions' }}
      @endif
    </span>
    {{ $subscriptions->onEachSide(1)->links('pagination.admin') }}
  </div>
</section>
@endsection

@push('scripts')
@include('admin.partials.moderation-scripts')
<script>
document.querySelectorAll("[data-autosubmit]").forEach((el) => {
  el.addEventListener("change", () => el.form.submit());
});

// Subscription plans — real data, rendered server-side above. Only delete needs JS.
(function () {
  "use strict";

  var CSRF = document.querySelector('meta[name="csrf-token"]').content;

  document.getElementById("plan-grid").addEventListener("click", function (e) {
    var btn = e.target.closest("[data-delete]");
    if (!btn) return;

    Swal.fire({
      title: 'Delete the "' + btn.dataset.name + '" plan?',
      text: "This cannot be undone.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, delete",
      cancelButtonText: "Cancel",
      confirmButtonColor: "#e0413a",
      cancelButtonColor: "#8b8b94",
      reverseButtons: true,
      focusCancel: true,
    }).then(function (result) {
      if (!result.isConfirmed) return;

      fetch("{{ url('admin/plans') }}/" + btn.dataset.delete, {
        method: "DELETE",
        headers: { "Accept": "application/json", "X-CSRF-TOKEN": CSRF, "X-Requested-With": "XMLHttpRequest" }
      }).then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (body) { return { ok: res.ok, body: body }; });
      }).then(function (result) {
        if (!result.ok) {
          Swal.fire({ icon: "error", title: "Could not delete", text: result.body.message || "Please try again." });
          return;
        }
        window.location.reload();
      });
    });
  });
})();
</script>
@endpush
