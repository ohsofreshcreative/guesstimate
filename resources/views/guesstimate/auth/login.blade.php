@extends('guesstimate.auth.layout')

@section('title', 'Logowanie')

@section('content')
  <h1 class="text-3xl font-bold tracking-tight">Zaloguj się</h1>
  <p class="mt-2 text-ink-soft">Panel Guesstimate — dostęp tylko dla zespołu.</p>

  @include('guesstimate.auth.partials.alerts')

  <form method="post" action="{{ $urls['login'] }}" class="mt-8 space-y-5" data-auth-form novalidate>
    <input type="hidden" name="_gst_login" value="{{ $nonce }}">
    @if ($redirect && $redirect !== home_url('/app/'))
      <input type="hidden" name="redirect_to" value="{{ $redirect }}">
    @endif

    <div>
      <label for="gst-log" class="mb-1.5 block font-medium">E-mail lub login</label>
      <input id="gst-log" name="log" type="text" value="{{ $login }}" class="block w-full rounded-control border-line-strong bg-surface px-3 py-2.5 text-ink placeholder:text-ink-muted focus:border-accent focus:ring-accent" autocomplete="username" autocapitalize="none" spellcheck="false" required
        @if ($error) aria-invalid="true" aria-describedby="auth-error" @endif @if (! $login) autofocus @endif>
    </div>

    <div>
      <div class="mb-1.5 flex items-baseline justify-between gap-3">
        <label for="gst-pwd" class="font-medium">Hasło</label>
        <a href="{{ $urls['forgot'] }}" class="text-sm font-medium text-ink-soft underline-offset-2 hover:text-ink hover:underline" data-forgot-link>Nie pamiętasz hasła?</a>
      </div>
      <div class="relative">
        <input id="gst-pwd" name="pwd" type="password" class="block w-full rounded-control border-line-strong bg-surface px-3 py-2.5 text-ink placeholder:text-ink-muted focus:border-accent focus:ring-accent pr-20" autocomplete="current-password" required
          @if ($error) aria-invalid="true" aria-describedby="auth-error" @endif @if ($login) autofocus @endif>
        <button type="button" class="absolute inset-y-0 right-0 rounded-control px-3 text-sm font-medium text-ink-soft hover:text-ink" aria-controls="gst-pwd" aria-pressed="false" aria-label="Pokaż hasło" data-toggle-password>Pokaż</button>
      </div>
    </div>

    <label class="flex select-none items-center gap-2.5 text-ink-soft">
      <input type="checkbox" name="rememberme" value="1" class="size-4 rounded border-line-strong text-accent focus:ring-accent" data-remember>
      Nie wylogowuj mnie przez 14 dni
    </label>

    <button type="submit" class="w-full rounded-control bg-accent px-4 py-3 font-semibold text-white hover:bg-accent-strong disabled:opacity-70" data-loading="Logowanie…" data-login-submit>Zaloguj się</button>
  </form>

  <p class="mt-10 text-sm text-ink-muted">Nie masz konta albo nie możesz się zalogować? Napisz do administratora.</p>
@endsection
