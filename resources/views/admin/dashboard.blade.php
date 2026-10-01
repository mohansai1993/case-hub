@extends('layouts.admin')

@section('title', 'CaseHub Dashboard')

@section('content')
<div class="page-head">
  <h1>Dashboard</h1>
  <p>Manage clients, lawyers, cases and subscriptions from one place.</p>
</div>

<!-- STAT CARDS -->
<section class="stats">
  <div class="stat-card">
    <div class="stat-top">
      <span>Total Clients</span>
      <span class="stat-icon"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M16 3.1a4 4 0 0 1 0 7.8M22 21a7 7 0 0 0-4-6.3"/></svg></span>
    </div>
    <div class="stat-value">{{ number_format($clientStats['total']) }}</div>
    <div class="stat-note"><span class="dot dot-blue"></span>{{ number_format($clientStats['active']) }} active</div>
  </div>

  <div class="stat-card">
    <div class="stat-top">
      <span>Total Lawyers</span>
      <span class="stat-icon"><svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V10M19 21V10M9 21V10M15 21V10M2 10l10-7 10 7Z"/></svg></span>
    </div>
    <div class="stat-value">{{ number_format($lawyerStats['total']) }}</div>
    <div class="stat-note"><span class="dot dot-blue"></span>{{ number_format($lawyerStats['verified']) }} verified</div>
  </div>

  <div class="stat-card">
    <div class="stat-top">
      <span>Restricted Clients</span>
      <span class="stat-icon icon-red"><svg viewBox="0 0 24 24"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg></span>
    </div>
    <div class="stat-value text-red">{{ number_format($restrictedSubscriptions) }}</div>
    <div class="stat-note text-red"><span class="dot dot-red"></span>Grace period expired</div>
  </div>

  <div class="stat-card">
    <div class="stat-top">
      <span>Active Subscriptions</span>
      <span class="stat-icon"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></span>
    </div>
    <div class="stat-value">{{ number_format($activeSubscriptions) }}</div>
    <div class="stat-note"><span class="dot dot-blue"></span>Currently subscribed</div>
  </div>
</section>

<div class="grid">

  <!-- FAILED PAYMENTS -->
  <section class="card pending">
    <div class="card-head">
      <div>
        <h2>Recent Failed Payments</h2>
        <p>Subscription charge attempts that were declined</p>
      </div>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Client</th>
            <th>Plan</th>
            <th>Amount</th>
            <th>Reason</th>
            <th>When</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($failedPayments as $charge)
            <tr>
              <td>
                @if ($charge->subscription?->client)
                  <a href="{{ route('admin.client-details', $charge->subscription->client_id) }}"><strong>{{ $charge->subscription->client->name }}</strong></a>
                @else
                  <strong>Unknown client</strong>
                @endif
              </td>
              <td>{{ $charge->plan->name }}</td>
              <td>{{ config('billing.currency_symbol') }}{{ number_format($charge->amount) }}</td>
              <td><span class="status status-danger">{{ $charge->failure_message ?? 'Declined' }}</span></td>
              <td>{{ $charge->created_at->diffForHumans() }}</td>
            </tr>
          @empty
            <tr><td colspan="5"><p class="empty-msg">No failed payments.</p></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <!-- RIGHT COLUMN -->
  <div class="side-col">

    <section class="card">
      <h2>Account Management</h2>

      <div class="acc-row">
        <div class="acc-top">
          <span class="acc-title">
            <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0"/></svg>
            Client Accounts
          </span>
          <span class="acc-total">{{ number_format($clientStats['total']) }}</span>
        </div>
        <div class="acc-meta">
          <span>Active: <b class="text-green">{{ number_format($clientStats['active']) }}</b></span>
          <span>Inactive: <b>{{ number_format($clientStats['inactive']) }}</b></span>
          <span>Suspended: <b class="text-red">{{ number_format($clientStats['suspended']) }}</b></span>
        </div>
      </div>

      <div class="acc-row">
        <div class="acc-top">
          <span class="acc-title">
            <svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V10M19 21V10M9 21V10M15 21V10M2 10l10-7 10 7Z"/></svg>
            Lawyer Accounts
          </span>
          <span class="acc-total">{{ number_format($lawyerStats['total']) }}</span>
        </div>
        <div class="acc-meta">
          <span>Verified: <b class="text-green">{{ number_format($lawyerStats['verified']) }}</b></span>
          <span>Pending: <b class="text-orange">{{ number_format($lawyerStats['pending']) }}</b></span>
          <span>Suspended: <b class="text-red">{{ number_format($lawyerStats['suspended']) }}</b></span>
        </div>
      </div>
    </section>

    <section class="card">
      <div class="card-head">
        <div>
          <h2>Case Overview</h2>
          <p>Platform-wide matter distribution</p>
        </div>
        <svg class="head-icon" viewBox="0 0 24 24"><path d="M3 7h18v13H3zM8 7V4h8v3"/></svg>
      </div>

      <div class="case-grid">
        <div class="case-box">
          <span>Pending</span>
          <strong>{{ number_format($caseStats['pending']) }}</strong>
        </div>
        <div class="case-box">
          <span>Accepted</span>
          <strong>{{ number_format($caseStats['accepted']) }}</strong>
        </div>
        <div class="case-box">
          <span>Rejected</span>
          <strong>{{ number_format($caseStats['rejected']) }}</strong>
        </div>
        <div class="case-box">
          <span>Closed</span>
          <strong>{{ number_format($caseStats['closed']) }}</strong>
        </div>
      </div>
    </section>

    <section class="card">
      <div class="card-head">
        <h2>Recent Platform Activity</h2>
        <svg class="head-icon" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/></svg>
      </div>

      @if ($recentActivity->isNotEmpty())
        <ul class="activity">
          @foreach ($recentActivity as $event)
            <li class="c-blue">
              <strong>{{ $event['title'] }}</strong>
              <p>{{ $event['body'] }}</p>
              <time>{{ $event['at']->diffForHumans() }}</time>
            </li>
          @endforeach
        </ul>
      @else
        <p class="empty-msg">Nothing has happened yet.</p>
      @endif
    </section>

  </div>
</div>
@endsection
