@extends('layouts.admin')

@section('title', 'Notifications')

@section('content')
<div class="page-head">
  <h1>Notifications</h1>
  <p>All system alerts sent to your account.</p>
</div>

<section class="card list-card">
  <div class="toolbar">
    <button type="button" class="link-btn" id="page-mark-all" data-url="{{ route('admin.alerts.read-all') }}" @if (! $notifications->total()) disabled @endif>Mark all read</button>
  </div>

  <div class="alerts-list" id="page-alerts-list">
    @forelse ($notifications as $notification)
      <a href="{{ $notification->data['action_url'] ?? '#' }}" class="alert-item{{ $notification->read_at ? '' : ' is-unread' }}" data-id="{{ $notification->id }}" data-mark-read-url="{{ route('admin.alerts.read', $notification->id) }}">
        <span class="alert-dot"></span>
        <span class="alert-body">
          <strong>{{ $notification->data['title'] ?? '' }}</strong>
          <small>{{ $notification->data['body'] ?? '' }}</small>
          <span class="alert-time">{{ $notification->created_at->diffForHumans() }}</span>
        </span>
      </a>
    @empty
      <p class="empty-msg">No notifications yet.</p>
    @endforelse
  </div>

  <div class="table-footer">
    <span>
      @if ($notifications->total())
        {{ 'Showing '.$notifications->firstItem().' to '.$notifications->lastItem().' of '.number_format($notifications->total()).' '.\Illuminate\Support\Str::plural('notification', $notifications->total()) }}
      @endif
    </span>
    {{ $notifications->onEachSide(1)->links('pagination.admin') }}
  </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
  var CSRF = document.querySelector('meta[name="csrf-token"]').content;

  function api(url) {
    return fetch(url, {
      method: "POST",
      headers: {
        "Accept": "application/json",
        "X-CSRF-TOKEN": CSRF,
        "X-Requested-With": "XMLHttpRequest",
      },
    });
  }

  document.getElementById("page-alerts-list").addEventListener("click", function (e) {
    var item = e.target.closest(".alert-item");
    if (!item || !item.classList.contains("is-unread")) return;
    item.classList.remove("is-unread");
    api(item.dataset.markReadUrl);
  });

  document.getElementById("page-mark-all").addEventListener("click", function (e) {
    var btn = e.currentTarget;
    api(btn.dataset.url).then(function () {
      document.querySelectorAll("#page-alerts-list .alert-item.is-unread").forEach(function (item) {
        item.classList.remove("is-unread");
      });
    });
  });
})();
</script>
@endpush
