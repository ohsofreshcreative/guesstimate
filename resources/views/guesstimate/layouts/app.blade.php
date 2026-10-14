<!doctype html>
<html lang="pl" class="h-full bg-canvas">

<head>
  @include('guesstimate.layouts.head')
  <meta name="gst-nonce" content="{{ $gstNonce }}">
</head>

<body class="h-full font-sans text-ink antialiased">
  <a href="#main" class="sr-only rounded-control bg-surface px-4 py-2 focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50">
    Przejdź do treści
  </a>

  <div x-data="{ navOpen: false }" @keydown.escape.window="navOpen = false" class="min-h-full lg:flex">
    @include('guesstimate.partials.sidebar')

    <div class="flex min-w-0 flex-1 flex-col">
      <div class="sticky top-0 z-20 flex items-center gap-3 border-b border-line bg-surface px-4 py-3 lg:hidden">
        <button type="button" class="rounded-control p-2 text-ink-soft hover:bg-canvas" @click="navOpen = true" :aria-expanded="navOpen.toString()" aria-controls="gst-sidebar">
          <x-gst.icon name="menu" />
          <span class="sr-only">Otwórz menu</span>
        </button>
        <span class="font-semibold">Guesstimate</span>
      </div>

      <main id="main" class="w-full min-w-0 flex-1 px-4 py-8 sm:px-8 lg:px-12 lg:py-10">
        @if ($pendingMigrations)
          <x-gst.alert type="warning" class="mb-6">
            Baza danych aplikacji wymaga aktualizacji ({{ count($pendingMigrations) }} migr.). Uruchom w katalogu strony: <code>wp acorn migrate</code>
          </x-gst.alert>
        @endif

        @foreach ($flash as $message)
          <x-gst.alert :type="$message['type']" class="mb-6" x-data="{ show: true }" x-show="show">
            <div class="flex items-start justify-between gap-4">
              <span class="flex flex-wrap items-center gap-x-3 gap-y-1">
                {{ $message['message'] }}
                @if (! empty($message['action']))
                  <form method="post" action="{{ $message['action']['url'] }}" class="inline">
                    <x-gst.csrf />
                    <button type="submit" class="font-semibold underline underline-offset-2" data-flash-action>{{ $message['action']['label'] }}</button>
                  </form>
                @endif
              </span>
              <button type="button" class="shrink-0 opacity-70 hover:opacity-100" x-on:click="show = false"><x-gst.icon name="close" class="size-4" /><span class="sr-only">Zamknij komunikat</span></button>
            </div>
          </x-gst.alert>
        @endforeach

        @yield('content')
      </main>
    </div>
  </div>
</body>

</html>
