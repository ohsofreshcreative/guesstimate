@extends('guesstimate.layouts.app')

@section('title', 'Klienci')

@section('content')
  <x-gst.page-header title="Klienci" description="Firmy, osoby kontaktowe i historia przygotowanych dla nich wycen.">
    <x-slot:actions>
      <x-gst.button icon="plus" x-data x-on:click="$dispatch('open-modal', 'client-create')">Nowy klient</x-gst.button>
    </x-slot:actions>
  </x-gst.page-header>

  {{-- Zakładki: aktywni / usunięci (soft delete, przywracanie) --}}
  <nav class="mb-4 flex gap-1 border-b border-line" aria-label="Widok klientów">
    <a href="{{ route('gst.clients.index') }}" @class(['-mb-px border-b-2 px-4 py-2 font-medium', 'border-accent text-ink' => $view === 'active', 'border-transparent text-ink-soft hover:text-ink' => $view !== 'active']) @if ($view === 'active') aria-current="page" @endif>Aktywni</a>
    <a href="{{ route('gst.clients.index', ['widok' => 'usunieci']) }}" @class(['-mb-px border-b-2 px-4 py-2 font-medium', 'border-accent text-ink' => $view === 'deleted', 'border-transparent text-ink-soft hover:text-ink' => $view !== 'deleted']) @if ($view === 'deleted') aria-current="page" @endif data-tab-deleted>Usunięci @if ($deletedCount)<span class="text-ink-muted">({{ $deletedCount }})</span>@endif</a>
  </nav>

  <section class="rounded-panel border border-line bg-surface shadow-panel">
    <form method="get" class="flex flex-wrap gap-2 border-b border-line px-6 py-4" role="search">
      @if ($view === 'deleted')<input type="hidden" name="widok" value="usunieci">@endif
      <label for="f-q" class="sr-only">Szukaj klienta</label>
      <x-gst.input name="q" id="f-q" :value="$term" placeholder="Szukaj: firma, osoba, e-mail, NIP" class="w-80 max-w-full" />
      <x-gst.button type="submit" variant="secondary">Szukaj</x-gst.button>
      @if ($term !== '')<x-gst.button :href="route('gst.clients.index', $view === 'deleted' ? ['widok' => 'usunieci'] : [])" variant="ghost">Wyczyść</x-gst.button>@endif
    </form>

    @if ($view === 'deleted')
      @if (empty($deleted))
        <x-gst.empty-state icon="clients" title="Brak usuniętych klientów" text="Usunięci klienci trafiają tutaj i można ich przywrócić." />
      @else
        <div class="relative overflow-x-auto">
          <table class="w-full min-w-max text-left" data-deleted-clients>
            <thead class="border-b border-line text-sm text-ink-soft">
              <tr>
                <th class="px-6 py-3 font-medium">Firma</th>
                <th class="px-4 py-3 font-medium">Usunięto</th>
                <th class="px-4 py-3 font-medium">Usunął</th>
                <th class="px-6 py-3"><span class="sr-only">Akcje</span></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line">
              @foreach ($deleted as $row)
                <tr>
                  <td class="px-6 py-3 font-medium">{{ $row['name'] }} @if ($row['clickup'])<span class="text-sm font-normal text-ink-muted">· z ClickUp</span>@endif</td>
                  <td class="px-4 py-3 text-ink-soft">{{ $row['deleted_at'] }}</td>
                  <td class="px-4 py-3 text-ink-soft">{{ $row['deleted_by'] }}</td>
                  <td class="px-6 py-3 text-right">
                    <form method="post" action="{{ route('gst.clients.restore', $row['id']) }}">
                      <x-gst.csrf />
                      <button type="submit" class="rounded-control px-2 py-1 text-accent hover:bg-accent-soft" data-restore-client="{{ $row['id'] }}">Przywróć</button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    @elseif ($clients->isEmpty())
      <x-gst.empty-state icon="clients"
        :title="$term !== '' ? 'Nie znaleziono klientów' : 'Nie ma jeszcze klientów'"
        :text="$term !== '' ? 'Sprawdź pisownię albo dodaj nowego klienta.' : 'Dodaj pierwszego klienta, żeby przygotować dla niego wycenę.'">
        <x-gst.button icon="plus" x-data x-on:click="$dispatch('open-modal', 'client-create')">Nowy klient</x-gst.button>
      </x-gst.empty-state>
    @else
      <div class="relative overflow-x-auto">
        <table class="w-full min-w-max text-left">
          <thead class="border-b border-line text-sm text-ink-soft">
            <tr>
              <th class="px-6 py-3 font-medium">Firma</th>
              <th class="px-4 py-3 font-medium">Kontakt</th>
              <th class="px-4 py-3 font-medium">Źródło</th>
              <th class="px-4 py-3 text-right font-medium">Oferty</th>
              <th class="px-6 py-3"><span class="sr-only">Akcje</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line">
            @foreach ($clients as $client)
              @php($inTrash = $client->offers_all_count - $client->offers_count)
              <tr class="group hover:bg-canvas/60" data-client-row="{{ $client->id }}">
                <td class="px-6 py-3 font-medium"><a href="{{ route('gst.clients.show', $client) }}" class="hover:text-accent">{{ $client->company_name }}</a>
                  @if ($client->website_url)<span class="block text-sm font-normal text-ink-muted">{{ preg_replace('#^https?://#', '', $client->website_url) }}</span>@endif
                </td>
                <td class="px-4 py-3">
                  <span class="block">{{ $client->contact_name ?: '—' }}</span>
                  <span class="block text-sm text-ink-muted">@if ($client->email)<a href="mailto:{{ $client->email }}" class="hover:text-accent">{{ $client->email }}</a>@endif{{ $client->email && $client->phone ? ' · ' : '' }}{{ $client->phone }}</span>
                </td>
                <td class="px-4 py-3 text-ink-soft">{{ $client->clickup_id ? 'ClickUp' : 'Dodany ręcznie' }}</td>
                <td class="px-4 py-3 text-right">{{ $client->offers_all_count }}@if ($inTrash > 0) <span class="block text-sm text-ink-muted">w koszu: {{ $inTrash }}</span>@endif</td>
                <td class="whitespace-nowrap px-6 py-3 text-right" x-data="{ menu: false, top: 0, right: 0, toggle(e) { const r = e.currentTarget.getBoundingClientRect(); this.top = r.bottom + 4; this.right = window.innerWidth - r.right; this.menu = ! this.menu } }">
                  <div class="inline-flex items-center justify-end gap-1" x-on:keydown.escape="menu = false" x-on:click.outside="menu = false" x-on:scroll.window="menu = false" x-on:resize.window="menu = false">
                    <a href="{{ route('gst.clients.show', $client) }}" class="rounded-control px-2 py-1 text-accent hover:bg-accent-soft">Karta klienta</a>
                    <button type="button" class="rounded-control px-2 py-1 text-ink-soft hover:bg-canvas hover:text-ink" x-on:click="toggle($event)" :aria-expanded="menu" aria-haspopup="menu" aria-label="Więcej akcji dla {{ $client->company_name }}" data-client-menu>⋯</button>
                    <div x-show="menu" x-cloak :style="`top: ${top}px; right: ${right}px`" class="fixed z-40 w-64 rounded-panel border border-line bg-surface py-1 text-left shadow-overlay" role="menu">
                      <a href="{{ route('gst.offers.create', ['klient' => $client->id]) }}" class="block px-4 py-2 hover:bg-canvas" role="menuitem">Nowa oferta</a>
                      @if ($client->offers_all_count === 0)
                        <button type="button" class="block w-full px-4 py-2 text-left text-danger hover:bg-danger-soft" role="menuitem" data-delete-client="{{ $client->id }}"
                          x-on:click="menu = false; $dispatch('gst-client-delete', { url: @js(route('gst.clients.destroy', $client)), name: @js($client->company_name) })">Usuń klienta</button>
                      @else
                        <div class="px-4 py-2 text-ink-muted" role="menuitem" aria-disabled="true" data-delete-blocked>
                          <span class="block">Usuń klienta</span>
                          <span class="block text-sm">Niedostępne — klient ma oferty ({{ $client->offers_all_count }}{{ $inTrash > 0 ? ', w tym w koszu: ' . $inTrash : '' }}).</span>
                        </div>
                      @endif
                    </div>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </section>

  @include('guesstimate.clients.partials.delete-modal')

  <x-gst.modal id="client-create" title="Nowy klient" size="lg" :open="($openModal ?? null) === 'client-create'">
    <form method="post" action="{{ route('gst.clients.store') }}" class="space-y-6" novalidate>
      <x-gst.csrf />
      @include('guesstimate.clients.partials.form', ['client' => null, 'prefix' => 'new-'])
      <div class="flex justify-end gap-2">
        <x-gst.button variant="ghost" x-on:click="$dispatch('close-modal', 'client-create')">Anuluj</x-gst.button>
        <x-gst.button type="submit">Dodaj klienta</x-gst.button>
      </div>
    </form>
  </x-gst.modal>
@endsection
