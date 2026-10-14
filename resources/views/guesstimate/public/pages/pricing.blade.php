<div class="osf-pad osf-column sheet:pr-24">
  <img src="{{ $logo }}" alt="OhSoFresh Creative" class="osf-logo self-start">
  {{-- Nagłówek, opis, zestawienie cen, suma i „Cena zawiera” = jedna grupa wyśrodkowana w pionie. --}}
  <div class="osf-center">
  @include('guesstimate.public.partials.heading', ['page' => $page, 'edit' => $edit, 'class' => 'mt-5'])
  <div data-flow-body>
    <p class="osf-t-sm mt-3 max-w-4xl text-paper">
      Poniżej prezentujemy estymację prac oraz wycenę z podziałem na etapy. Początkowa cena, wyznaczona jako <strong>pierwsza kwota z zakresu widełek</strong>, stanowi <strong>bazową kwotę</strong>, jednak w przypadku większego nakładu pracy cena może być dostosowana zgodnie z faktycznymi potrzebami projektu.
    </p>
    {{-- Cena pochodzi wyłącznie z kalkulatora wersji — w dokumencie nie da się jej edytować. --}}
    <div class="mt-5 max-w-4xl" {{ $edit('price') }}>
      <div class="grid grid-cols-2 items-end">
        <svg class="h-8 w-10 text-orange" viewBox="0 0 48 44" fill="none" aria-hidden="true">
          <rect x="3" y="3" width="42" height="28" rx="3" fill="currentColor" stroke="white" stroke-width="2.5"/>
          <path d="M12 8l14 14" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
          <path d="M16 41h16M24 31v10" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
        <p class="osf-t-base font-bold text-orange">Cena</p>
      </div>
      <dl class="mt-2">
        @foreach ($offer['price']['parts'] as $part)
          <div class="osf-t-base grid grid-cols-2 py-1 font-bold">
            <dt class="text-paper-soft">{{ $part['label'] }}</dt>
            <dd>{{ $part['text'] }}</dd>
          </div>
        @endforeach
        <div class="osf-dashed my-3 h-px" aria-hidden="true"></div>
        <div class="grid grid-cols-2 items-baseline">
          <dt class="osf-t-lg font-bold text-paper-soft">Razem</dt>
          <dd class="osf-t-2xl font-bold text-orange">{{ $offer['price']['total']['text'] }} <span class="osf-t-base text-paper">netto</span></dd>
        </div>
      </dl>
    </div>
    @if ($doc['includes'])
      <h3 class="osf-t-sm mt-6 font-bold" {{ $edit('price_includes') }}>Cena zawiera:</h3>
      <ul class="osf-t-xs mt-1 grid gap-x-10 md:grid-cols-2 sheet:grid-cols-2" data-split {{ $edit('price_includes') }}>
        @foreach ($doc['includes'] as $item)
          <li class="flex gap-2"><span aria-hidden="true">▪</span><span>{{ $item }}</span></li>
        @endforeach
      </ul>
    @endif
    @if ($offer['costs'])
      <p class="osf-t-xs mt-4 text-paper-soft">Koszty zewnętrzne (poza ceną):
        @foreach ($offer['costs'] as $cost){{ $cost['name'] }} — {{ $cost['amount'] }} netto {{ $cost['billing'] }}@if (! $loop->last); @endif @endforeach
      </p>
    @endif
  </div>
  </div>
  @if ($doc['validity_days'])
    <p class="osf-t-xs mt-6 text-right text-paper-soft sheet:absolute sheet:bottom-6 sheet:right-10">Oferta ważna {{ $doc['validity_days'] }} dni</p>
  @endif
</div>
<p class="osf-edge-label" aria-hidden="true">Wariant cenowy</p>
