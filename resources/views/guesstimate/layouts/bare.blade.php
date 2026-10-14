<!doctype html>
<html lang="pl" class="h-full bg-canvas">

<head>
  @include('guesstimate.layouts.head')
</head>

<body class="flex h-full items-center justify-center px-4 font-sans text-ink antialiased">
  <main class="w-full max-w-md rounded-panel border border-line bg-surface p-8 shadow-panel">
    @yield('content')
  </main>
</body>

</html>
