@extends('layouts.admin')

@section('title', 'CaseHub Clients')

@section('content')
<div class="page-head">
  <h1>Clients</h1>
  <p>Manage all registered client accounts.</p>
</div>

<section class="card list-card">
  <form method="GET" action="{{ route('admin.clients') }}" class="toolbar">
    <label class="search-box">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search clients..." maxlength="100" />
    </label>

    <div class="filters">
      <label>
        Status:
        <select name="status" data-autosubmit>
          <option value="">All Statuses</option>
          @foreach (\App\Enums\UserStatus::cases() as $status)
            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ ucfirst($status->value) }}</option>
          @endforeach
        </select>
      </label>
      @if (! empty($filters['q']) || ! empty($filters['status']))
        <a href="{{ route('admin.clients') }}" class="btn-view">Clear</a>
      @endif
    </div>
  </form>

  <div class="table-wrap">
    <table class="clients-table">
      <thead>
        <tr>
          <th>Client Name</th>
          <th>Email / Contact</th>
          <th>Account Status</th>
          <th>Joined Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($clients as $client)
          <tr>
            <td><strong>{{ $client->name }}</strong><small>{{ $client->reference() }}</small></td>
            <td>
              {{ $client->email }}
              <small>+91 {{ $client->mobile }}@unless ($client->hasVerifiedMobile()) &middot; mobile not verified @endunless</small>
            </td>
            <td><span class="status status-{{ $client->status->value }}">{{ ucfirst($client->status->value) }}</span></td>
            <td>{{ $client->created_at->format('d M Y') }}</td>
            <td><a href="{{ route('admin.client-details', $client->user_id) }}" class="btn-view">View</a></td>
          </tr>
        @empty
          <tr>
            <td colspan="5"><p class="empty-msg">{{ $total ? 'No clients match your search.' : 'No clients have registered yet.' }}</p></td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="table-footer">
    <span>
      @if ($clients->total())
        {{ 'Showing '.$clients->firstItem().' to '.$clients->lastItem().' of '.number_format($clients->total()).' '.($clients->total() === $total ? 'registered' : 'matching').' '.\Illuminate\Support\Str::plural('client', $clients->total()) }}
      @endif
    </span>
    {{ $clients->onEachSide(1)->links('pagination.admin') }}
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
