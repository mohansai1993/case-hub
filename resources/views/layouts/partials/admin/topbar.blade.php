@php
  $admin = auth('admin')->user();
  $unreadCount = $admin->unreadNotifications()->count();
@endphp
<header class="topbar">
        <button class="menu-btn" id="menu-btn" aria-label="Open menu">
          <svg viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>

        <div class="topbar-right">
          <div class="alerts" id="alerts" data-url="{{ route('admin.alerts.index') }}" data-read-all-url="{{ route('admin.alerts.read-all') }}">
            <button class="bell-btn" id="alerts-toggle" aria-label="Notifications" aria-expanded="false">
              <svg viewBox="0 0 24 24"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg>
              <span class="bell-dot" id="alerts-dot" @if (! $unreadCount) hidden @endif></span>
            </button>

            <div class="alerts-panel" id="alerts-panel" hidden>
              <div class="alerts-head">
                <strong>Notifications</strong>
                <button type="button" class="link-btn" id="alerts-mark-all">Mark all read</button>
              </div>
              <div class="alerts-list" id="alerts-list">
                <p class="empty-msg">Loading&hellip;</p>
              </div>
            </div>
          </div>

          <div class="profile">
            <div class="avatar">{{ $admin->initials() }}</div>
            <div>
              <div class="profile-name">{{ $admin->name }}</div>
              <div class="profile-role">{{ $admin->isSuperAdmin() ? 'Super Administrator' : ($admin->role->name ?? 'Administrator') }}</div>
            </div>
          </div>

          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="logout-btn" aria-label="Log out" title="Log out">
              <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg>
            </button>
          </form>
        </div>
      </header>
