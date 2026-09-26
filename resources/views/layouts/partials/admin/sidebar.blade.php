@php $admin = auth('admin')->user(); @endphp
<aside class="sidebar" id="sidebar">
      <div class="side-logo">
        <img src="{{ asset('assets/admin/images/logo.svg') }}" alt="CaseHub" />
      </div>

      <nav class="side-nav">
        @if($admin->hasPermission('dashboard.view'))
        <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}">
          <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
          Dashboard
        </a>
        @endif
        @if($admin->hasPermission('clients.view'))
        <a href="{{ route('admin.clients') }}" class="nav-item {{ request()->routeIs('admin.clients', 'admin.client-details') ? 'is-active' : '' }}">
          <svg viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          Clients
        </a>
        @endif
        @if($admin->hasPermission('lawyers.view'))
        <a href="{{ route('admin.lawyers') }}" class="nav-item {{ request()->routeIs('admin.lawyers', 'admin.lawyer-details') ? 'is-active' : '' }}">
          <svg viewBox="0 0 24 24"><path d="M12 3v18M7 21h10M5 7h14"/><path d="M5 7l-3 6h6L5 7Zm14 0l-3 6h6l-3-6Z"/></svg>
          Lawyers
        </a>
        @endif
        @if($admin->hasPermission('subscriptions.view'))
        <a href="{{ route('admin.subscriptions') }}" class="nav-item {{ request()->routeIs('admin.subscriptions', 'admin.subscription-details', 'admin.create-plan') ? 'is-active' : '' }}">
          <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
          Subscriptions
        </a>
        @endif
        @if($admin->hasPermission('notifications.view'))
        <a href="{{ route('admin.notifications') }}" class="nav-item {{ request()->routeIs('admin.notifications') ? 'is-active' : '' }}">
          <svg viewBox="0 0 24 24"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg>
          Notifications
        </a>
        @endif
        @if($admin->hasPermission('settings.view'))
        <a href="{{ route('admin.settings') }}" class="nav-item {{ request()->routeIs('admin.settings') ? 'is-active' : '' }}">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/></svg>
          Settings
        </a>
        @endif
        @if($admin->isSuperAdmin())
        <a href="{{ route('admin.roles') }}" class="nav-item {{ request()->routeIs('admin.roles', 'admin.create-role', 'admin.role-details') ? 'is-active' : '' }}">
          <svg viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4Z"/><path d="M9 12l2 2 4-4"/></svg>
          Roles &amp; Permissions
        </a>
        @endif
        @if($admin->isSuperAdmin())
        <a href="{{ route('admin.staff') }}" class="nav-item {{ request()->routeIs('admin.staff', 'admin.create-staff', 'admin.staff-details') ? 'is-active' : '' }}">
          <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M16 3.1a4 4 0 0 1 0 7.8M22 21a7 7 0 0 0-4-6.3"/></svg>
          Staff
        </a>
        @endif
      </nav>
    </aside>
<div class="sidebar-backdrop" id="sidebar-backdrop"></div>
