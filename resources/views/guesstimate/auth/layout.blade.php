{{-- Ekrany logowania Guesstimate: lewa kolumna z marką (desktop / tablet), prawa z formularzem. --}}
<!doctype html>
<html lang="pl" class="h-full bg-surface">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="referrer" content="no-referrer">
  <title>@yield('title') – Guesstimate</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:ital,wght@0,400..800;1,400..800&display=swap" rel="stylesheet">
  @vite(['resources/css/guesstimate.css'])
</head>

<body class="min-h-full font-sans text-ink antialiased">
  <div class="grid min-h-screen md:grid-cols-5 lg:grid-cols-2">

    {{-- Marka (od tabletu) --}}
    <aside class="relative hidden flex-col justify-between overflow-hidden bg-rail p-10 text-white md:col-span-2 md:flex lg:col-span-1 xl:p-16" data-auth-brand>
      <div class="pointer-events-none absolute -bottom-32 -right-32 size-96 rounded-full bg-accent/50 blur-3xl" aria-hidden="true"></div>
      <div class="pointer-events-none absolute -left-24 top-1/3 size-72 rounded-full bg-risk-mid/20 blur-3xl" aria-hidden="true"></div>
      <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-rail-raised/60 via-transparent to-transparent" aria-hidden="true"></div>

      <x-gst.logo size="lg" :tagline="false" class="relative" />

      <div class="relative max-w-md">
        <p class="text-3xl font-bold leading-tight tracking-tight lg:text-4xl xl:text-5xl">Lepsze wyceny.<br><span class="text-accent-soft">Lepsze decyzje.</span></p>
        <p class="mt-5 text-lg leading-relaxed text-rail-ink">Twórz profesjonalne wyceny i oferty dla swoich klientów w jednym miejscu.</p>
      </div>

      <p class="relative text-sm text-rail-muted">OhSoFresh Creative — narzędzie wewnętrzne</p>
    </aside>

    {{-- Formularz --}}
    <main class="flex items-center justify-center px-4 py-10 sm:px-8 md:col-span-3 lg:col-span-1">
      <div class="w-full max-w-sm">
        <x-gst.logo :tagline="false" class="mb-10 text-ink md:hidden" />
        @yield('content')
      </div>
    </main>
  </div>

  <script>
    (function () {
      document.querySelectorAll('[data-toggle-password]').forEach(function (toggle) {
        var input = document.getElementById(toggle.getAttribute('aria-controls'));
        if (!input) return;
        toggle.addEventListener('click', function () {
          var show = input.type === 'password';
          input.type = show ? 'text' : 'password';
          toggle.textContent = show ? 'Ukryj' : 'Pokaż';
          toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
          toggle.setAttribute('aria-label', show ? 'Ukryj hasło' : 'Pokaż hasło');
          input.focus();
        });
      });
      document.querySelectorAll('form[data-auth-form]').forEach(function (form) {
        form.addEventListener('submit', function () {
          var submit = form.querySelector('[type=submit]');
          if (!submit || submit.disabled) return;
          submit.disabled = true;
          submit.setAttribute('aria-busy', 'true');
          submit.textContent = submit.getAttribute('data-loading') || 'Proszę czekać…';
        });
      });
    })();
  </script>
</body>

</html>
