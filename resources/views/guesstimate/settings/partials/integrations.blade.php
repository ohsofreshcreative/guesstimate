{{-- Integracje: OpenAI oraz stan aplikacji OAuth ClickUp. Sekrety nigdy nie są wypisywane. --}}
@php
  $ai = $settings['ai'] ?? [];
@endphp
<form method="post" action="{{ route('gst.settings.update', ['section' => 'integracje']) }}" class="max-w-5xl space-y-6" novalidate autocomplete="off">
  <x-gst.csrf />

  <section class="rounded-panel border border-line bg-surface p-6 shadow-panel">
    <h2 class="text-lg font-semibold">Asystent AI (OpenAI)</h2>
    <p class="mt-1 text-ink-soft">Generowanie treści oferty i estymator funkcjonalności. Klucz jest przechowywany zaszyfrowany po stronie serwera i nie trafia do przeglądarki. Do AI nie wysyłamy danych kontaktowych klienta, cen ani notatek wewnętrznych.</p>
    <div class="mt-5 grid gap-5 sm:grid-cols-2">
      <x-gst.field label="Klucz API" for="f-openai_api_key" :error="$errors['openai_api_key'] ?? null" :hint="$secrets['openai']['set'] ? 'Zapisany: ' . $secrets['openai']['mask'] . '. Wpisz nowy, aby zmienić.' : 'Brak klucza — asystent jest wyłączony.'">
        <x-gst.input type="password" name="openai_api_key" value="" :error="$errors['openai_api_key'] ?? null" autocomplete="new-password" placeholder="sk-…" />
      </x-gst.field>
      <x-gst.field label="Model" for="f-ai-model" :error="$errors['ai.model'] ?? null" hint="Nazwa modelu z konta OpenAI, np. gpt-4.1-mini.">
        <x-gst.input name="ai[model]" id="f-ai-model" :value="$o('ai.model', $ai['model'] ?? '')" :error="$errors['ai.model'] ?? null" />
      </x-gst.field>
      @if ($secrets['openai']['set'])
        <x-gst.checkbox name="openai_api_key_remove" label="Usuń zapisany klucz" />
      @endif
      <x-gst.field label="Instrukcje systemowe" for="f-ai-instructions" :error="$errors['ai.instructions'] ?? null" class="sm:col-span-2" hint="Styl i zasady dla wszystkich odpowiedzi AI.">
        <x-gst.textarea name="ai[instructions]" id="f-ai-instructions" :value="$o('ai.instructions', $ai['instructions'] ?? '')" rows="5" />
      </x-gst.field>
      <x-gst.field label="Dzienny limit zapytań na osobę" for="f-ai-limit" :error="$errors['ai.daily_limit'] ?? null">
        <x-gst.input type="number" name="ai[daily_limit]" id="f-ai-limit" min="1" max="500" :value="$o('ai.daily_limit', $ai['daily_limit'] ?? 40)" :error="$errors['ai.daily_limit'] ?? null" />
      </x-gst.field>
      <x-gst.field label="Limit czasu odpowiedzi" for="f-ai-timeout" :error="$errors['ai.timeout'] ?? null">
        <x-gst.input type="number" name="ai[timeout]" id="f-ai-timeout" min="10" max="120" :value="$o('ai.timeout', $ai['timeout'] ?? 45)" :error="$errors['ai.timeout'] ?? null" suffix="s" />
      </x-gst.field>
    </div>
    @if ($aiRequests)
      <p class="mt-4 text-sm text-ink-muted">Ostatnie 30 dni: {{ $aiRequests['ok'] ?? 0 }} udanych zapytań, {{ $aiRequests['error'] ?? 0 }} z błędem.</p>
    @endif
  </section>

  <section class="rounded-panel border border-line bg-surface p-6 shadow-panel" data-clickup-app>
    <h2 class="text-lg font-semibold">ClickUp (OAuth 2.0)</h2>
    <p class="mt-1 text-ink-soft">Każdy użytkownik łączy własne konto ClickUp w sekcji <a href="{{ route('gst.integrations.index') }}" class="font-medium text-accent-strong underline">Moje integracje</a>. Tokeny użytkowników są szyfrowane po stronie serwera i nie są widoczne dla administratora. Tutaj widać tylko stan aplikacji OAuth.</p>
    <dl class="mt-5 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-[auto_1fr]">
      <dt class="text-ink-muted">Aplikacja OAuth</dt>
      <dd class="font-medium {{ $clickupApp['configured'] ? 'text-accent-strong' : 'text-danger' }}">{{ $clickupApp['configured'] ? 'Skonfigurowana' : 'Nieskonfigurowana' }}</dd>
      @if ($clickupApp['missing'])
        <dt class="text-ink-muted">Brakuje</dt>
        <dd><code>{{ implode(', ', $clickupApp['missing']) }}</code> — ustaw w wp-config.php albo w zmiennych środowiskowych (nie w repozytorium).</dd>
      @endif
      <dt class="text-ink-muted">Redirect URI</dt>
      <dd><code class="break-all">{{ $clickupApp['redirect_uri'] }}</code> — wpisz dokładnie ten adres w aplikacji OAuth w ClickUp.</dd>
    </dl>
    @foreach ($clickupApp['notes'] as $note)
      <p class="mt-3 text-sm text-ink-muted">{{ $note }}</p>
    @endforeach
    @if ($secrets['clickup_legacy']['set'])
      <div class="mt-5 rounded-control border border-line px-4 py-3 text-sm">
        <p>Stary wspólny Personal API Token: zapisany ({{ $secrets['clickup_legacy']['mask'] }}), <strong>nieużywany</strong> — nowa integracja korzysta wyłącznie z połączeń OAuth użytkowników.</p>
        <div class="mt-2"><x-gst.checkbox name="clickup_legacy_token_remove" label="Usuń stary token" /></div>
      </div>
    @endif
  </section>

  <div class="flex justify-end"><x-gst.button type="submit">Zapisz integracje</x-gst.button></div>
</form>
