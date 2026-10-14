@extends('guesstimate.layouts.app')

@section('title', 'Moje integracje')

{{-- Moje integracje → ClickUp: połączenie OAuth zalogowanego użytkownika. Token nigdy nie jest wypisywany. --}}
@section('content')
  <x-gst.page-header title="Moje integracje" description="Połączenia z Twoimi kontami w zewnętrznych narzędziach. Są prywatne — inni użytkownicy (także administrator) nie korzystają z Twojego połączenia." />

  @php
    $connected = (bool) $connection?->isConnected();
    $needsReconnect = $connection && ! $connected;
    $config = $editor['config'];
    $sync = $editor['sync'];
  @endphp

  <section class="max-w-5xl rounded-panel border border-line bg-surface p-6 shadow-panel" data-clickup-integration>
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h2 class="text-lg font-semibold">ClickUp</h2>
        <p class="mt-1 text-ink-soft">Wyszukiwanie firm z Twoich list ClickUp w kreatorze wyceny. Integracja tylko odczytuje dane — Guesstimate niczego nie zmienia w ClickUp.</p>
      </div>
      <p class="rounded-full px-3 py-1 text-sm font-medium {{ $connected ? 'bg-accent-soft text-accent-strong' : ($needsReconnect ? 'bg-danger-soft text-danger' : 'bg-canvas text-ink-soft') }}" data-clickup-status>
        {{ $connected ? 'Połączono z ClickUp' : ($needsReconnect ? 'Wymaga ponownego połączenia' : 'Nie połączono') }}
      </p>
    </div>

    @if (! $app['configured'])
      <x-gst.alert type="warning" class="mt-5" data-clickup-app-missing>
        <p>Aplikacja OAuth ClickUp nie jest jeszcze skonfigurowana na tym serwerze, więc nie można połączyć konta.</p>
        @if (current_user_can('manage_options'))
          <p class="mt-1 text-sm">Brakuje: <code>{{ implode(', ', $app['missing']) }}</code>. Redirect URI do wpisania w ClickUp: <code class="break-all">{{ $app['redirect_uri'] }}</code></p>
        @else
          <p class="mt-1 text-sm">Poproś administratora o skonfigurowanie integracji.</p>
        @endif
      </x-gst.alert>
    @endif

    @if (! $connection)
      <div class="mt-6">
        <form method="post" action="{{ route('gst.integrations.clickup.connect') }}">
          <x-gst.csrf />
          <x-gst.button type="submit" :disabled="! $app['configured']" data-clickup-connect>Połącz z ClickUp</x-gst.button>
        </form>
        <p class="mt-2 text-sm text-ink-muted">Zostaniesz przekierowany do ClickUp, aby zezwolić Guesstimate na odczyt wybranych Workspace’ów.</p>
      </div>
    @else
      @if ($needsReconnect)
        <x-gst.alert type="error" class="mt-5" data-clickup-reconnect>
          <div class="flex flex-wrap items-center justify-between gap-3">
            <span>Połączenie z ClickUp wygasło lub zostało cofnięte. Połącz ponownie.</span>
            <form method="post" action="{{ route('gst.integrations.clickup.connect') }}">
              <x-gst.csrf />
              <x-gst.button type="submit" :disabled="! $app['configured']">Połącz ponownie</x-gst.button>
            </form>
          </div>
        </x-gst.alert>
      @endif

      <div x-data="clickupIntegration(@js($editor + ['missing' => $missing]))">
        <dl class="mt-6 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
          <div><dt class="text-ink-muted">Konto ClickUp</dt><dd class="font-medium" data-clickup-account>{{ $connection->clickup_username ?: '—' }}</dd></div>
          <div><dt class="text-ink-muted">Workspace</dt><dd class="font-medium" x-text="saved.team || '—'">{{ $config['team_name'] ?: '—' }}</dd></div>
          <div><dt class="text-ink-muted">Listy</dt><dd class="font-medium" x-text="saved.lists || '—'">{{ $config['lists'] ? collect($config['lists'])->pluck('name')->join(', ') : '—' }}</dd></div>
          <div><dt class="text-ink-muted">Status synchronizacji</dt><dd class="font-medium" :class="sync?.status === 'error' ? 'text-danger' : ''" x-text="statusLabel()" data-clickup-sync-label>—</dd></div>
          <div><dt class="text-ink-muted">Liczba firm</dt><dd class="font-medium" x-text="sync ? sync.companies : 0" data-clickup-companies>{{ $sync['companies'] ?? 0 }}</dd></div>
          <div><dt class="text-ink-muted">Ostatnia synchronizacja</dt><dd class="font-medium" x-text="sync?.synced_at || '—'" data-clickup-last>{{ $sync['synced_at'] ?? '—' }}</dd></div>
          <div><dt class="text-ink-muted">Następna synchronizacja</dt><dd class="font-medium" x-text="sync?.active ? 'po zakończeniu bieżącej' : (sync?.next_sync_at ? `ok. ${sync.next_sync_at}` : '—')" data-clickup-next>—</dd></div>
          <div class="sm:col-span-2"><dt class="text-ink-muted">Tryb</dt><dd class="font-medium">Automatyczna synchronizacja co 15 minut <span class="font-normal text-ink-muted">(zmiany od ostatniej synchronizacji; pełna synchronizacja raz na dobę<span x-show="sync?.full_synced_at" x-text="`, ostatnia: ${sync?.full_synced_at}`"></span>)</span></dd></div>
          <div><dt class="text-ink-muted">Połączono</dt><dd class="font-medium">{{ $connection->connected_at ? wp_date('d.m.Y H:i', $connection->connected_at->getTimestamp()) : '—' }}</dd></div>
        </dl>

        <div class="mt-4 rounded-control border border-risk-mid/30 bg-risk-mid-soft px-4 py-3 text-sm" x-show="missing.length" data-clickup-missing>
          <p class="font-medium">Do wyszukiwania brakuje:</p>
          <ul class="mt-1 list-disc pl-5"><template x-for="item in missing" :key="item"><li x-text="item"></li></template></ul>
        </div>

        @if ($connected)
          {{-- 1–3: Workspace, Space, listy --}}
          <div class="mt-8 border-t border-line pt-6">
            <h3 class="font-semibold">Konfiguracja</h3>
            <div class="mt-4 grid gap-5 lg:grid-cols-2">
              <div>
                <label for="f-cu-team" class="mb-1.5 block font-medium">1. Workspace</label>
                <div class="flex gap-2">
                  <select id="f-cu-team" x-model="teamId" x-on:change="changeTeam()" class="min-w-0 flex-1 rounded-control border-line-strong py-2 pl-3 pr-8" data-clickup-team>
                    <option value="">— wybierz —</option>
                    <template x-for="team in teams" :key="team.id"><option :value="team.id" x-text="team.name" :selected="team.id === teamId"></option></template>
                  </select>
                  <x-gst.button variant="secondary" x-on:click="loadTeams()">Odśwież</x-gst.button>
                </div>
                <p class="mt-1 text-sm text-danger" x-show="errors.team_id" x-text="errors.team_id"></p>
                <p class="mt-1 text-sm text-ink-muted">Widoczne są tylko Workspace’y autoryzowane przy łączeniu. Inny Workspace dodasz przez „Połącz ponownie”.</p>
              </div>
              <div>
                <label for="f-cu-space" class="mb-1.5 block font-medium">2. Space</label>
                <div class="flex gap-2">
                  <select id="f-cu-space" x-model="spaceId" x-on:change="changeSpace()" class="min-w-0 flex-1 rounded-control border-line-strong py-2 pl-3 pr-8" ::disabled="! teamId" data-clickup-space>
                    <option value="">— wybierz —</option>
                    <template x-for="space in spaces" :key="space.id"><option :value="space.id" x-text="space.name" :selected="space.id === spaceId"></option></template>
                  </select>
                  <x-gst.button variant="secondary" x-on:click="loadSpaces()" ::disabled="! teamId">Odśwież</x-gst.button>
                </div>
              </div>
            </div>

            <div class="mt-5">
              <p class="mb-1.5 font-medium">3. Listy z klientami</p>
              <div class="max-h-72 space-y-1 overflow-y-auto rounded-control border border-line p-3" x-show="lists.length" data-clickup-lists>
                <template x-for="list in lists" :key="list.id">
                  <label class="flex items-center gap-2"><input type="checkbox" class="size-4 rounded border-line-strong text-accent focus:ring-accent" :checked="isSelected(list.id)" x-on:change="toggleList(list)"> <span x-text="list.label"></span></label>
                </template>
              </div>
              <p class="text-sm text-ink-muted" x-show="! lists.length && spaceId && ! loading">Brak list w tym Space albo nie pobrano ich jeszcze.</p>
              <div class="mt-3 flex flex-wrap gap-2" x-show="selected.length" data-clickup-selected>
                <template x-for="list in selected" :key="list.id">
                  <span class="inline-flex items-center gap-1 rounded-full bg-canvas px-3 py-1 text-sm">
                    <span x-text="list.name"></span>
                    <button type="button" class="opacity-60 hover:opacity-100" x-on:click="removeList(list.id)" :aria-label="`Usuń listę ${list.name}`"><x-gst.icon name="close" class="size-3.5" /></button>
                  </span>
                </template>
              </div>
              <p class="mt-1 text-sm text-danger" x-show="errors.lists" x-text="errors.lists"></p>
              <p class="mt-1 text-sm text-ink-muted">Możesz wybrać listy z kilku Space’ów — zmień Space i zaznacz kolejne.</p>
            </div>

            {{-- 4: mapowanie pól --}}
            <div class="mt-6">
              <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="font-medium">4. Mapowanie pól</p>
                <x-gst.button variant="secondary" x-on:click="loadFields()" ::disabled="! selected.length">Odśwież pola</x-gst.button>
              </div>
              <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-clickup-mapping>
                @foreach ($editor['targets'] as $target => $label)
                  <div>
                    <label for="f-cu-map-{{ $target }}" class="mb-1.5 block text-sm font-medium">{{ $label }}</label>
                    <select id="f-cu-map-{{ $target }}" x-model="mapping.{{ $target }}" class="block w-full rounded-control border-line-strong py-2 pl-3 pr-8" data-target="{{ $target }}">
                      <option value="">— nie importuj —</option>
                      <option value="name">Nazwa zadania</option>
                      <template x-for="field in fields" :key="field.id"><option :value="field.id" x-text="field.name" :selected="mapping.{{ $target }} === field.id"></option></template>
                    </select>
                    <p class="mt-1 text-sm text-danger" x-show="errors['mapping.{{ $target }}']" x-text="errors['mapping.{{ $target }}']"></p>
                  </div>
                @endforeach
              </div>
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
              <x-gst.button x-on:click="save()" ::disabled="loading !== null" data-clickup-save>Zapisz konfigurację</x-gst.button>
              <span class="text-sm text-ink-muted" x-show="dirty">Masz niezapisane zmiany. Po zapisaniu zmiany list lub mapowania synchronizacja uruchomi się automatycznie.</span>
              <span class="text-sm text-accent-strong" x-show="message && ! dirty" x-text="message"></span>
            </div>
          </div>

          {{-- Operacje --}}
          <div class="mt-8 border-t border-line pt-6">
            <div class="flex flex-wrap gap-3">
              <x-gst.button variant="secondary" x-on:click="test()" ::disabled="loading !== null" data-clickup-test>Sprawdź połączenie</x-gst.button>
              <x-gst.button x-on:click="startSync()" ::disabled="loading !== null || sync?.active || dirty" data-clickup-sync>Synchronizuj teraz</x-gst.button>
            </div>
            <p class="mt-2 text-sm text-ink-muted">„Sprawdź połączenie” szybko weryfikuje konto, Workspace i dostęp do list (bez pobierania zadań). „Synchronizuj teraz” uruchamia synchronizację od razu (w tle, partiami) — nie trzeba tego robić ręcznie, firmy synchronizują się same co 15 minut. Gdy synchronizacja trwa, przycisk nie uruchamia drugiej.</p>

            <p class="mt-3 text-sm text-ink-muted" x-show="loading" x-text="loading === 'test' ? 'Sprawdzanie połączenia…' : 'Pobieranie z ClickUp…'"></p>
            <p class="mt-3 text-sm text-danger" x-show="error" x-text="error" data-clickup-error></p>

            <template x-if="report">
              <div class="mt-4 rounded-control border px-4 py-3 text-sm" :class="report.ok ? 'border-accent/30 bg-accent-soft/40' : 'border-danger/30 bg-danger-soft'" data-clickup-report>
                <p class="font-medium" :class="report.ok ? 'text-accent-strong' : 'text-danger'" x-text="report.ok ? 'Połączenie działa' : 'Problem z połączeniem'"></p>
                <dl class="mt-2 grid gap-x-6 gap-y-1 sm:grid-cols-2">
                  <div><dt class="inline text-ink-muted">Konto:</dt> <dd class="inline" x-text="report.account || '—'"></dd></div>
                  <div><dt class="inline text-ink-muted">Workspace:</dt> <dd class="inline" x-text="report.workspace ? `${report.workspace} (${report.workspace_ok ? 'dostęp OK' : 'brak dostępu'})` : 'nie wybrano'"></dd></div>
                </dl>
                <ul class="mt-2 space-y-1" x-show="report.lists.length">
                  <template x-for="list in report.lists" :key="list.id">
                    <li><span class="font-medium" x-text="list.name"></span>: <span x-text="list.ok ? 'dostęp OK' : list.error"></span></li>
                  </template>
                </ul>
                <p class="mt-2 text-danger" x-show="report.error" x-text="report.error"></p>
                <p class="mt-2" x-show="report.reconnect"><a href="{{ route('gst.integrations.index') }}" class="font-medium underline">Odśwież stronę i połącz ponownie</a></p>
              </div>
            </template>

            <template x-if="sync && sync.status !== 'never'">
              <div class="mt-4 rounded-control border border-line px-4 py-3 text-sm" data-clickup-sync-status>
                <p class="font-medium" :class="sync.status === 'error' ? 'text-danger' : ''" x-text="syncLabel()"></p>
                <p class="mt-1 text-ink-soft" x-text="stageLabel()" data-clickup-stage></p>
                <ul class="mt-2 space-y-0.5 text-ink-soft" x-show="sync.lists.length">
                  <template x-for="list in sync.lists" :key="list.name">
                    <li><span x-text="list.name"></span>: <span x-text="`${list.tasks} zadań${list.done ? (list.complete ? ' ✓' : ' (limit stron — nie wszystkie)') : ''}`"></span></li>
                  </template>
                </ul>
                <p class="mt-2 text-danger" x-show="sync.error" x-text="sync.error"></p>
              </div>
            </template>
          </div>
        @endif

        {{-- Zmiana konta / rozłączenie --}}
        <div class="mt-8 flex flex-wrap items-center gap-3 border-t border-line pt-6">
          @if ($connected)
            <form method="post" action="{{ route('gst.integrations.clickup.connect') }}">
              <x-gst.csrf />
              <x-gst.button type="submit" variant="ghost" :disabled="! $app['configured']">Zmień autoryzację (inne Workspace’y)</x-gst.button>
            </form>
          @endif
          <form method="post" action="{{ route('gst.integrations.clickup.disconnect') }}" x-data x-on:submit="if (! confirm('Rozłączyć ClickUp? Twój indeks firm zostanie usunięty. Klienci już zaimportowani do Guesstimate zostaną.')) $event.preventDefault()">
            <x-gst.csrf />
            <x-gst.button type="submit" variant="ghost" class="text-danger" data-clickup-disconnect>Rozłącz ClickUp</x-gst.button>
          </form>
        </div>
      </div>
    @endif
  </section>
@endsection
