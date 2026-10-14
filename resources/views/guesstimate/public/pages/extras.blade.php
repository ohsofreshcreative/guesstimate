{{-- Usługi dodatkowe: minimalistyczna tabela (bez ramek i pionowych linii, poziome separatory, ceny do prawej).
     Strona jest pomijana, gdy oferta nie ma usług (DocumentBuilder). --}}
<div class="osf-pad osf-column">
  <img src="{{ $logo }}" alt="OhSoFresh Creative" class="osf-logo self-start">
  <div class="osf-center">
    @include('guesstimate.public.partials.heading', ['page' => $page, 'edit' => $edit, 'class' => 'mt-5'])
    <div class="max-w-4xl" data-flow-body>
      @if ($doc['extras']['intro'] || $editor)
        <p class="osf-t-sm mt-3 whitespace-pre-line text-paper-soft" {{ $edit('extras_intro') }}>{{ $doc['extras']['intro'] ?: ($editor ? 'Wstęp do usług dodatkowych' : '') }}</p>
      @endif
      <div class="mt-6" role="table" aria-label="Usługi dodatkowe" data-split {{ $edit('extras') }}>
        <div class="osf-table-row osf-t-xs font-bold uppercase tracking-wider text-paper-muted" role="row">
          <span role="columnheader">Usługa</span>
          <span class="text-right" role="columnheader">Cena</span>
        </div>
          @foreach ($doc['extras']['items'] as $item)
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
      <p class="osf-t-xs mt-5 text-paper-muted">Usługi dodatkowe są opcjonalne i nie są wliczone w cenę projektu.</p>
    </div>
  </div>
</div>
