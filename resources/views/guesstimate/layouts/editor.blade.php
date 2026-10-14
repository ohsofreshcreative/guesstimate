{{-- Pełnoekranowy układ edytora dokumentu (bez menu bocznego aplikacji — cała szerokość dla podglądu). --}}
<!doctype html>
<html lang="pl" class="h-full bg-canvas">

<head>
  @include('guesstimate.layouts.head')
  <meta name="gst-nonce" content="{{ $gstNonce }}">
</head>

<body class="h-full font-sans text-ink antialiased lg:overflow-hidden">
  @if ($pendingMigrations)
    <x-gst.alert type="warning" class="rounded-none">
      Baza danych aplikacji wymaga aktualizacji ({{ count($pendingMigrations) }} migr.). Uruchom w katalogu strony: <code>wp acorn migrate</code>
    </x-gst.alert>
  @endif
  @yield('content')
</body>

</html>
