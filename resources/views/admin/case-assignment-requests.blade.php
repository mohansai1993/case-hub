@extends('layouts.admin')

@section('title', 'CaseHub Case Assignment Requests')

@section('content')
<div class="page-head">
  <h1>Case Assignment Requests</h1>
  <p>Review which lawyer a client picked before it reaches the lawyer.</p>
</div>

<section class="card list-card">
  <form method="GET" action="{{ route('admin.case-assignment-requests') }}" class="toolbar">
    <div class="filters">
      <select name="status" aria-label="Request status" data-autosubmit>
        <option value="pending" @selected(($filters['status'] ?? 'pending') === 'pending')>Pending</option>
        <option value="approved" @selected(($filters['status'] ?? '') === 'approved')>Approved</option>
        <option value="rejected" @selected(($filters['status'] ?? '') === 'rejected')>Rejected</option>
        <option value="all" @selected(($filters['status'] ?? '') === 'all')>All</option>
      </select>
    </div>
  </form>

  <div class="table-wrap">
    <table class="clients-table">
      <thead>
        <tr>
          <th>Case</th>
          <th>Client</th>
          <th>Legal Issue</th>
          <th>Requested Lawyer</th>
          <th>Status</th>
          <th>Requested</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($requests as $assignmentRequest)
          <tr>
            <td><strong>Case #{{ $assignmentRequest->case_id }}</strong><small>{{ $assignmentRequest->case->title }}</small></td>
            <td>{{ $assignmentRequest->case->client->name }}</td>
            <td>{{ $assignmentRequest->case->practiceArea?->name ?? '—' }}</td>
            <td>{{ $assignmentRequest->lawyer->name }}</td>
            <td>
              @php
                $statusClass = match ($assignmentRequest->status->value) {
                  'approved' => 'status-active',
                  'rejected' => 'status-rejected',
                  default => 'status-pending',
                };
              @endphp
              <span class="status {{ $statusClass }}">{{ ucfirst($assignmentRequest->status->value) }}</span>
            </td>
            <td>{{ $assignmentRequest->created_at->format('d M Y') }}</td>
            <td><a href="{{ route('admin.case-assignment-request-details', $assignmentRequest->id) }}" class="btn-view">View</a></td>
          </tr>
        @empty
          <tr>
            <td colspan="7"><p class="empty-msg">{{ $total ? 'No requests match this filter.' : 'No lawyer assignment requests yet.' }}</p></td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="table-footer">
    <span>
      @if ($requests->total())
        {{ 'Showing '.$requests->firstItem().' to '.$requests->lastItem().' of '.number_format($requests->total()).' '.\Illuminate\Support\Str::plural('request', $requests->total()) }}
      @endif
    </span>
    {{ $requests->onEachSide(1)->links('pagination.admin') }}
  </div>
</section>
@endsection

@push('scripts')
<script>
document.querySelectorAll("[data-autosubmit]").forEach((el) => {
  el.addEventListener("change", () => el.form.submit());
});
</script>
@endpush
