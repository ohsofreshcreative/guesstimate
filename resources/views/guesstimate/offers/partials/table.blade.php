{{-- Tabela ofert: $rows (OfferSummary::rows), $filters. --}}
@if (empty($rows))
  <x-gst.empty-state
    icon="offers"
    :title="$emptyTitle ?? 'Nie znaleziono ofert'"
    :text="$emptyText ?? 'Zmień filtry albo utwórz nową ofertę.'">
    @if (! empty($canCreate))
      <x-gst.button :href="route('gst.offers.create')" icon="plus">Nowa oferta</x-gst.button>
    @endif
  </x-gst.empty-state>
@else
  <div class="relative overflow-x-auto">
    <table class="w-full min-w-4xl text-left text-sm" data-offers-table>
      <thead class="border-b border-line text-xs text-ink-soft">
        <tr>
          <th scope="col" class="px-5 py-2.5 font-medium"><x-gst.sort-link column="number" label="Numer" :filters="$filters" /></th>
          <th scope="col" class="px-3 py-2.5 font-medium"><x-gst.sort-link column="client" label="Klient" :filters="$filters" /></th>
          <th scope="col" class="px-3 py-2.5 font-medium">Rodzaj</th>
          <th scope="col" class="px-3 py-2.5 text-right font-medium"><x-gst.sort-link column="value" label="Wycena od–do" :filters="$filters" /></th>
          <th scope="col" class="px-3 py-2.5 font-medium">Wariant dla klienta</th>
          <th scope="col" class="px-3 py-2.5 font-medium">Status</th>
          <th scope="col" class="px-3 py-2.5 font-medium">Autor</th>
          <th scope="col" class="sticky right-0 z-10 bg-surface px-5 py-2.5 text-right font-medium"><span class="sr-only">Akcje</span></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-line">
        @foreach ($rows as $row)
          <tr class="group hover:bg-canvas/60" data-offer-row="{{ $row['id'] }}">
            <td class="whitespace-nowrap px-5 py-2.5 font-medium">
              <a href="{{ route('gst.offers.show', $row['id']) }}" class="hover:text-accent" title="{{ $row['project'] }}">{{ $row['number'] }}</a>
              @if ($row['version'] > 1)<span class="ml-1 text-xs text-ink-muted">v{{ $row['version'] }}</span>@endif
              <span class="block text-xs font-normal text-ink-muted">{{ $row['created'] }}</span>
            </td>
            <td class="px-3 py-2.5">{{ $row['client'] }}</td>
            <td class="px-3 py-2.5 text-ink-soft">{{ $row['types'] }}</td>
            <td @class(['whitespace-nowrap px-3 py-2.5 text-right', 'text-danger' => $row['range_error']])>{{ $row['range'] }}</td>
            <td class="whitespace-nowrap px-3 py-2.5">
              @if ($row['variant'])
                <span class="flex items-center gap-2"><x-gst.risk-dot :level="$row['variant']['key']" /> {{ $row['variant']['label'] }}</span>
                <span class="text-xs text-ink-muted">{{ $row['variant']['range'] }}</span>
              @else
                <span class="text-xs text-ink-muted">poprzedni model</span>
              @endif
            </td>
            <td class="whitespace-nowrap px-3 py-2.5" data-status-cell>
              <x-gst.offer-status :status="$row['display']" />
              @if ($row['unpublished_changes'])
                <span class="mt-0.5 block text-xs text-ink-soft" data-unpublished-changes>Zmiany nieopublikowane</span>
              @endif
              @if ($row['public_url'])
                <span x-data="copyText(@js($row['public_url']))" class="mt-0.5 block">
                  <button type="button" class="text-xs font-medium text-ink-soft underline-offset-2 hover:text-ink hover:underline" x-on:click="copy()" x-text="copied ? 'Skopiowano' : 'Kopiuj link'" data-copy-link>Kopiuj link</button>
                </span>
              @endif
            </td>
            <td class="px-3 py-2.5 text-ink-soft">{{ $row['author'] }}</td>
            {{-- Kolumna akcji przyklejona do prawej krawędzi — menu ⋯ widoczne bez przewijania szerokiej tabeli. --}}
            {{-- Komórka sticky tworzy własny kontekst nakładania — otwarte menu podnosi całą komórkę ponad kolejne wiersze. --}}
            <td class="sticky right-0 whitespace-nowrap bg-surface px-5 py-2.5 text-right shadow-panel group-hover:bg-canvas" :class="menu ? 'z-30' : 'z-10'" x-data="{ menu: false, top: 0, right: 0, toggle(e) { const r = e.currentTarget.getBoundingClientRect(); this.top = r.bottom + 4; this.right = window.innerWidth - r.right; this.menu = ! this.menu } }">
              <div class="inline-flex items-center justify-end gap-1" x-on:keydown.escape="menu = false" x-on:click.outside="menu = false" x-on:scroll.window="menu = false" x-on:resize.window="menu = false">
                <a href="{{ route('gst.offers.show', $row['id']) }}" class="rounded-control px-2 py-1 text-accent hover:bg-accent-soft">Podgląd</a>
                <button type="button" class="rounded-control px-2 py-1 text-ink-soft hover:bg-canvas hover:text-ink" x-on:click="toggle($event)" :aria-expanded="menu" aria-haspopup="menu" aria-label="Więcej akcji dla {{ $row['number'] }}" data-row-menu>⋯</button>
                <div x-show="menu" x-cloak :style="`top: ${top}px; right: ${right}px`" class="fixed z-40 w-52 rounded-panel border border-line bg-surface py-1 text-left shadow-overlay" role="menu">
                  @if ($row['editable'])
                    <a href="{{ route('gst.offers.wizard', ['offer' => $row['id'], 'step' => 1]) }}" class="block px-4 py-2 hover:bg-canvas" role="menuitem">Edytuj</a>
                  @endif
                  @if ($row['can_document'])
                    <a href="{{ route('gst.offers.document', $row['id']) }}" class="block px-4 py-2 hover:bg-canvas" role="menuitem">Edytuj dokument</a>
                  @endif
                  @if (! empty($canCreate))
                    <form method="post" action="{{ route('gst.offers.duplicate', $row['id']) }}">
                      <x-gst.csrf />
                      <button type="submit" class="block w-full px-4 py-2 text-left hover:bg-canvas" role="menuitem">Duplikuj</button>
                    </form>
                  @endif
                  @if ($row['can_trash'])
                    <button type="button" class="block w-full px-4 py-2 text-left text-danger hover:bg-danger-soft" role="menuitem"
                      x-on:click="menu = false; $dispatch('gst-trash', { url: @js(route('gst.offers.trash.move', $row['id'])), number: @js($row['number']) })">Przenieś do kosza</button>
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
