{{-- Własna strona „Tabela”: wstęp i tabela jak w „Usługach dodatkowych”, z pozycjami tej strony (nie z oferty). --}}
<div class="osf-pad osf-column">
  <img src="{{ $logo }}" alt="OhSoFresh Creative" class="osf-logo self-start">
  <div class="osf-center">
    @if ($page['heading'] || $editor)
      @include('guesstimate.public.partials.heading', ['page' => $page, 'edit' => $edit, 'class' => 'mt-5'])
    @endif
    <div class="max-w-4xl" data-flow-body>
      @if (! empty($page['html']))
        <div class="osf-prose osf-t-sm mt-3" {{ $edit('html') }}>{!! $page['html'] !!}</div>
      @endif
      @if ($page['items'] ?? [])
        <div class="mt-6" role="table" aria-label="{{ $page['label'] }}" data-split {{ $edit('items') }}>
          <div class="osf-table-row osf-t-xs font-bold uppercase tracking-wider text-paper-muted" role="row">
            <span role="columnheader">Pozycja</span>
            <span class="text-right" role="columnheader">Cena</span>
          </div>
          @foreach ($page['items'] as $item)
            <div class="osf-table-row" role="row">
              <div class="min-w-0" role="cell">
                <p class="osf-t-base font-bold">{{ $item['name'] }}</p>
                @php $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $item['description'])), fn ($l) => $l !== '')); @endphp
                @if ($lines)
                  <p class="osf-t-xs mt-1 text-paper-soft">{{ implode(' · ', $lines) }}</p>
                @endif
              </div>
              <p class="osf-t-base max-w-xs text-right font-bold text-orange" role="cell">{{ $item['price'] }}</p>
            </div>
          @endforeach
        </div>
      @elseif ($editor)
        <p class="osf-t-sm mt-6 text-paper-muted" {{ $edit('items') }}>Dodaj pozycje tabeli w panelu.</p>
      @endif
    </div>
  </div>
</div>
