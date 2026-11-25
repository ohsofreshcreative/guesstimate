{{-- Podsumowanie oferty pakietów support: $summary (OfferSummary::version). Treść stron zmienia się w edytorze dokumentu. --}}
<section class="min-w-0 rounded-panel border border-line bg-surface p-6 shadow-panel">
  <h2 class="font-semibold">Oferta</h2>
  <dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2">
    <div><dt class="text-sm text-ink-muted">Nazwa oferty</dt><dd class="mt-0.5">{{ $summary['project_name'] }}</dd></div>
    <div><dt class="text-sm text-ink-muted">Rodzaj</dt><dd class="mt-0.5">{{ $summary['type'] }}</dd></div>
    @if ($summary['internal_notes'])
      <div class="sm:col-span-2"><dt class="text-sm text-ink-muted">Notatki wewnętrzne</dt><dd class="mt-0.5 whitespace-pre-line">{{ $summary['internal_notes'] }}</dd></div>
    @endif
  </dl>
</section>

<section class="mt-6 min-w-0 rounded-panel border border-line bg-surface shadow-panel">
  <h2 class="border-b border-line px-5 py-3 font-semibold">Pakiety</h2>
  @if ($summary['packages'])
    <div class="grid divide-y divide-line md:grid-cols-2 md:divide-y-0 xl:grid-cols-4">
      @foreach ($summary['packages'] as $package)
        <div class="min-w-0 px-5 py-4">
          <p class="font-semibold">{{ $package['name'] ?: 'Pakiet bez nazwy' }}</p>
          <p class="mt-1 text-xl font-semibold">{{ $package['price'] ?? '—' }} <span class="text-sm font-normal text-ink-muted">{{ $package['price_note'] }}</span></p>
          <ul class="mt-3 space-y-1 text-sm">
            @foreach ($package['points'] as $point)
              <li @class(['text-ink-muted line-through' => ! $point['on']])>{{ $point['text'] }}</li>
            @endforeach
          </ul>
        </div>
      @endforeach
    </div>
  @else
    <p class="px-5 py-6 text-ink-muted">Brak pakietów. Dodaj je w edytorze dokumentu.</p>
  @endif
</section>
