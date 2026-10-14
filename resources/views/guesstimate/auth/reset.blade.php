@extends('guesstimate.auth.layout')

@section('title', 'Nowe hasło')

@section('content')
  <h1 class="text-3xl font-bold tracking-tight">Ustaw nowe hasło</h1>

  @if ($invalid)
    @include('guesstimate.auth.partials.alerts', ['notice' => null, 'error' => $invalid])
    <p class="mt-8"><a href="{{ $urls['forgot'] }}" class="font-medium text-accent-strong underline-offset-2 hover:underline" data-new-link>Poproś o nowy link</a></p>
  @else
    <p class="mt-2 text-ink-soft">Konto: <span class="font-medium text-ink">{{ $login }}</span>. Hasło musi mieć co najmniej 10 znaków.</p>
    @include('guesstimate.auth.partials.alerts', ['notice' => null, 'error' => $errors['form'] ?? null])

    <form method="post" action="{{ $urls['reset'] }}" class="mt-8 space-y-5" data-auth-form novalidate>
      <input type="hidden" name="_gst_login" value="{{ $nonce }}">
      <input type="hidden" name="key" value="{{ $key }}">
      <input type="hidden" name="login" value="{{ $login }}">
      <input type="text" name="username" value="{{ $login }}" autocomplete="username" class="hidden" tabindex="-1" aria-hidden="true">

      <div>
        <label for="gst-pass1" class="mb-1.5 block font-medium">Nowe hasło</label>
        <div class="relative">
          <input id="gst-pass1" name="pass1" type="password" class="block w-full rounded-control border-line-strong bg-surface px-3 py-2.5 text-ink placeholder:text-ink-muted focus:border-accent focus:ring-accent pr-20" autocomplete="new-password" minlength="10" required autofocus
            @if (! empty($errors['pass1'])) aria-invalid="true" aria-describedby="err-pass1" @endif>
          <button type="button" class="absolute inset-y-0 right-0 rounded-control px-3 text-sm font-medium text-ink-soft hover:text-ink" aria-controls="gst-pass1" aria-pressed="false" aria-label="Pokaż hasło" data-toggle-password>Pokaż</button>
        </div>
        @if (! empty($errors['pass1']))<p id="err-pass1" class="mt-1.5 text-sm text-danger">{{ $errors['pass1'] }}</p>@endif
      </div>

      <div>
        <label for="gst-pass2" class="mb-1.5 block font-medium">Powtórz hasło</label>
        <input id="gst-pass2" name="pass2" type="password" class="block w-full rounded-control border-line-strong bg-surface px-3 py-2.5 text-ink placeholder:text-ink-muted focus:border-accent focus:ring-accent" autocomplete="new-password" required
          @if (! empty($errors['pass2'])) aria-invalid="true" aria-describedby="err-pass2" @endif>
        @if (! empty($errors['pass2']))<p id="err-pass2" class="mt-1.5 text-sm text-danger">{{ $errors['pass2'] }}</p>@endif
      </div>

      <button type="submit" class="w-full rounded-control bg-accent px-4 py-3 font-semibold text-white hover:bg-accent-strong disabled:opacity-70" data-loading="Zapisywanie…">Zapisz nowe hasło</button>
    </form>
  @endif

  <p class="mt-8"><a href="{{ $urls['login'] }}" class="font-medium text-accent-strong underline-offset-2 hover:underline">← Wróć do logowania</a></p>
@endsection
