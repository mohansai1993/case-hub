<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'CaseHub')</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('assets/admin/style.css') }}" />
  @stack('css')
</head>
<body class="dashboard-page">
  <div class="layout">

    @include('layouts.partials.admin.sidebar')

    <div class="main">

      @include('layouts.partials.admin.topbar')

      <main class="content">
        @yield('content')
      </main>
    </div>
  </div>

  @stack('modals')

  <script>
    // Mobile sidebar toggle
    const sidebar = document.getElementById("sidebar");
    const backdrop = document.getElementById("sidebar-backdrop");

    function toggleSidebar(open) {
      sidebar.classList.toggle("is-open", open);
      backdrop.classList.toggle("is-open", open);
    }

    document.getElementById("menu-btn").addEventListener("click", () => toggleSidebar(true));
    backdrop.addEventListener("click", () => toggleSidebar(false));
  </script>
  @stack('scripts')
</body>
</html>
