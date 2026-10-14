@extends('guesstimate.auth.layout')

@section('title', 'Nie pamiętasz hasła?')

@section('content')
  <h1 class="text-3xl font-bold tracking-tight">Nie pamiętasz hasła?</h1>
  <p class="mt-2 text-ink-soft">Podaj e-mail lub login. Wyślemy link do ustawienia nowego hasła.</p>

  @if ($sent)
    @include('guesstimate.auth.partials.alerts', ['notice' => 'Jeśli konto istnieje, wysłaliśmy na jego adres e-mail wiadomość z linkiem do ustawienia nowego hasła. Sprawdź skrzynkę (także folder spam).'])
  @else
    @include('guesstimate.auth.partials.alerts', ['notice' => null])

    <form method="post" action="{{ $urls['forgot'] }}" class="mt-8 space-y-5" data-auth-form novalidate>
      <input type="hidden" name="_gst_login" value="{{ $nonce }}">
      <div>
        <label for="gst-user-login" class="mb-1.5 block font-medium">E-mail lub login</label>
        <input id="gst-user-login" name="user_login" type="text" value="{{ $login }}" class="block w-full rounded-control border-line-strong bg-surface px-3 py-2.5 text-ink placeholder:text-ink-muted focus:border-accent focus:ring-accent" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus
          @if ($error) aria-invalid="true" aria-describedby="auth-error" @endif>
      </div>
      <button type="submit" class="w-full rounded-control bg-accent px-4 py-3 font-semibold text-white hover:bg-accent-strong disabled:opacity-70" data-loading="Wysyłanie…">Wyślij link</button>
    </form>
  @endif

  <p class="mt-8"><a href="{{ $urls['login'] }}" class="font-medium text-accent-strong underline-offset-2 hover:underline">← Wróć do logowania</a></p>
@endsection
