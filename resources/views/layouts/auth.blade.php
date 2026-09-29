<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'CaseHub Login')</title>
  <link rel="icon" type="image/png" href="{{ asset('assets/admin/images/casehub-fav.png') }}" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('assets/admin/style.css') }}" />
  <link rel="stylesheet" href="{{ asset('assets/admin/login.css') }}" />
  @stack('css')
</head>
<body class="auth-page">
  @yield('content')

  <script src="{{ asset('assets/admin/toast.js') }}"></script>
  @if (session('toast'))
    <script>showToast(@json(session('toast.message')), @json(session('toast.type', 'info')));</script>
  @endif
  @if ($errors->any())
    <script>showToast(@json($errors->first()), "error");</script>
  @endif
  @stack('scripts')
</body>
</html>
