@extends('guesstimate.auth.layout')

@section('title', 'Wylogowanie')

@section('content')
  <h1 class="text-3xl font-bold tracking-tight">Wylogować się?</h1>
  <p class="mt-2 text-ink-soft">Potwierdź wylogowanie z Guesstimate.</p>
  <form method="post" action="{{ $urls['logout'] }}" class="mt-8 flex flex-wrap gap-3" data-auth-form>
    <input type="hidden" name="_wpnonce" value="{{ wp_create_nonce('log-out') }}">
    <button type="submit" class="rounded-control bg-accent px-4 py-3 font-semibold text-white hover:bg-accent-strong" data-loading="Wylogowywanie…">Wyloguj się</button>
    <a href="{{ home_url('/app/') }}" class="rounded-control px-4 py-3 font-medium text-ink-soft hover:bg-canvas">Anuluj</a>
  </form>
@endsection
