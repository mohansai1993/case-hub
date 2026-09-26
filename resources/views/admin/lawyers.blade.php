@extends('layouts.admin')

@section('title', 'CaseHub Lawyers')

@section('content')
<div class="page-head">
  <h1>Lawyers</h1>
  <p>Manage all registered lawyer accounts.</p>
</div>

<section class="card list-card">
  <form method="GET" action="{{ route('admin.lawyers') }}" class="toolbar">
    <label class="search-box">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search lawyers..." maxlength="100" />
    </label>

    <div class="filters">
      <select name="status" aria-label="Account status" data-autosubmit>
        <option value="">All Statuses</option>
        @foreach (\App\Enums\UserStatus::cases() as $status)
          <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ ucfirst($status->value) }}</option>
        @endforeach
      </select>
      <select name="verification" aria-label="Verification status" data-autosubmit>
        <option value="">All Verifications</option>
        @foreach (\App\Enums\VerificationStatus::cases() as $verification)
          <option value="{{ $verification->value }}" @selected(($filters['verification'] ?? null) === $verification->value)>{{ ucfirst($verification->value) }}</option>
        @endforeach
      </select>
      <select name="practice_area" aria-label="Specialization" data-autosubmit>
        <option value="">All Specializations</option>
        @foreach ($practiceAreas as $area)
          <option value="{{ $area->id }}" @selected((string) ($filters['practice_area'] ?? '') === (string) $area->id)>{{ $area->name }}</option>
        @endforeach
      </select>
      @if (array_filter($filters))
        <a href="{{ route('admin.lawyers') }}" class="btn-view">Clear</a>
      @endif
    </div>
  </form>

  <div class="table-wrap">
    <table class="clients-table">
      <thead>
        <tr>
          <th>Lawyer Name</th>
          <th>Email / Contact</th>
          <th>Specialization</th>
          <th>Experience</th>
          <th>Account Status</th>
          <th>Verification Status</th>
          <th>Joined Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($lawyers as $lawyer)
          @php $verification = $lawyer->lawyerProfile?->verification_status; @endphp
          <tr>
            <td><strong>{{ $lawyer->name }}</strong><small>{{ $lawyer->lawyerProfile?->location }}</small></td>
            <td>
              {{ $lawyer->email }}
              <small>+91 {{ $lawyer->mobile }}@unless ($lawyer->hasVerifiedMobile()) &middot; mobile not verified @endunless</small>
            </td>
            <td>{{ $lawyer->practiceAreas->pluck('name')->join(', ') ?: '—' }}</td>
            <td>{{ $lawyer->lawyerProfile?->years_of_experience }} {{ \Illuminate\Support\Str::plural('yr', $lawyer->lawyerProfile?->years_of_experience ?? 0) }}</td>
            <td><span class="status status-{{ $lawyer->status->value }}">{{ ucfirst($lawyer->status->value) }}</span></td>
            <td>
              @if ($verification)
                <span class="verify verify-{{ $verification->value }}">{{ ucfirst($verification->value) }}</span>
              @endif
            </td>
            <td>{{ $lawyer->created_at->format('d M Y') }}</td>
            <td><a href="{{ route('admin.lawyer-details', $lawyer->user_id) }}" class="btn-view">View</a></td>
          </tr>
        @empty
          <tr>
            <td colspan="8"><p class="empty-msg">{{ $total ? 'No lawyers match your search.' : 'No lawyers have registered yet.' }}</p></td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="table-footer">
    <span>
      @if ($lawyers->total())
        {{ 'Showing '.$lawyers->firstItem().' to '.$lawyers->lastItem().' of '.number_format($lawyers->total()).' '.($lawyers->total() === $total ? 'registered' : 'matching').' '.\Illuminate\Support\Str::plural('lawyer', $lawyers->total()) }}
      @endif
    </span>
    {{ $lawyers->onEachSide(1)->links('pagination.admin') }}
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
