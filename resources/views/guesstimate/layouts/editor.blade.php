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
  {{-- Nad układem, bo pełnoekranowy edytor nie ma miejsca na pasek, który przesuwałby treść. --}}
  @if ($flash)
    <div class="fixed bottom-4 right-4 z-50 max-w-md space-y-2">
      @foreach ($flash as $message)
        <x-gst.alert :type="$message['type']" x-data="{ show: true }" x-show="show" class="shadow-overlay">
          <div class="flex items-start justify-between gap-4">
            <span>{{ $message['message'] }}</span>
            <button type="button" class="shrink-0 opacity-70 hover:opacity-100" x-on:click="show = false"><x-gst.icon name="close" /><span class="sr-only">Zamknij</span></button>
          </div>
        </x-gst.alert>
      @endforeach
    </div>
  @endif
</body>

</html>
