{{-- Admin decisions on this account (suspend / activate / approve / reject). --}}
<section class="card">
  <div class="card-head">
    <h2 class="card-title">
      <svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/></svg>
      Account History
    </h2>
    <span class="section-label">Admin decisions</span>
  </div>

  @if ($user->accountActions->isEmpty())
    <p class="empty-msg">No admin actions have been taken on this account.</p>
  @else
    <ul class="activity">
      @foreach ($user->accountActions as $action)
        <li class="{{ in_array($action->action, ['suspended', 'lawyer_rejected'], true) ? 'c-orange' : 'c-green' }}">
          <strong>{{ $action->label() }}</strong>
          @if ($action->reason)
            <p>{{ $action->reason }}</p>
          @endif
          <time>{{ $action->created_at->format('d M Y, H:i') }} &middot; {{ $action->admin?->name ?? 'Removed admin' }}</time>
        </li>
      @endforeach
    </ul>
  @endif
</section>
