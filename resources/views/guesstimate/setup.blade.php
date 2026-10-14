@extends('guesstimate.layouts.bare')

@section('title', 'Wymagana konfiguracja')

@section('content')
  <h1 class="text-2xl font-semibold tracking-tight">Baza danych nie jest gotowa</h1>
  <p class="mt-2 text-ink-soft">Tabele aplikacji Guesstimate nie zostały jeszcze utworzone. W katalogu strony uruchom:</p>
  <pre class="mt-4 overflow-x-auto rounded-control bg-canvas px-4 py-3"><code>wp acorn migrate</code></pre>
  @if ($pending)
    <p class="mt-4 text-sm text-ink-muted">Oczekujące migracje: {{ count($pending) }}.</p>
  @endif
@endsection
