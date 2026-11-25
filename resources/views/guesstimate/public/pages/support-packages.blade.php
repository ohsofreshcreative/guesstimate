{{-- Oferta support: pakiety w kolumnach (opis, punkty — nieobjęte przekreślone, cena). Dane: $doc['support'] (SupportPackages::present). --}}
@php
  $packages = $doc['support']['packages'] ?? [];
  $colors = ['orange' => 'text-orange', 'flame' => 'text-flame', 'magenta' => 'text-magenta', 'violet' => 'text-violet', 'indigo' => 'text-indigo'];
  $grid = [1 => 'md:grid-cols-1 sheet:grid-cols-1', 2 => 'md:grid-cols-2 sheet:grid-cols-2', 3 => 'md:grid-cols-3 sheet:grid-cols-3', 4 => 'md:grid-cols-2 sheet:grid-cols-4'][count($packages)] ?? '';
@endphp
<div class="osf-pad osf-column">
  <img src="{{ $logo }}" alt="OhSoFresh Creative" class="osf-logo self-start">
  <div class="osf-center">
    @include('guesstimate.public.partials.heading', ['page' => $page, 'edit' => $edit, 'class' => 'mt-5'])
    <div data-flow-body>
      @if (! empty($page['html']))
        <div class="osf-prose osf-t-sm mt-3 max-w-4xl" {{ $edit('html') }}>{!! $page['html'] !!}</div>
      @endif
      @if ($packages)
        {{-- Każdy pakiet zajmuje 4 wiersze wspólnej siatki (subgrid): nagłówki, opisy, listy i ceny zaczynają się na tej samej wysokości. --}}
        <div class="mt-6 grid osf-gap-lg {{ $grid }}" {{ $edit('packages') }}>
          @foreach ($packages as $package)
            @php $color = $colors[$package['color']] ?? 'text-orange'; @endphp
            <article @class(['row-span-4 grid min-w-0 grid-rows-subgrid gap-y-0', 'sheet:border-l sheet:border-night-line sheet:pl-6' => ! $loop->first])>
              <h3 class="osf-t-xl font-bold">Pakiet<br><span class="{{ $color }}">{{ $package['name'] }}</span></h3>
              <div>
                @if ($package['description'] !== '')
                  <p class="osf-t-xs mt-3 whitespace-pre-line">{{ $package['description'] }}</p>
                @endif
              </div>
              <div>
                @if ($package['points'])
                  <p class="osf-t-sm mt-4 font-bold {{ $color }}">Co zawiera pakiet?</p>
                  <ul class="osf-t-xs mt-1">
                    @foreach ($package['points'] as $point)
                      <li @class(['flex gap-2', 'line-through' => ! $point['on']])>
                        <span aria-hidden="true">▪</span><span>{{ $point['text'] }}@unless ($point['on'])<span class="sr-only"> (poza pakietem)</span>@endunless</span>
                      </li>
                    @endforeach
                  </ul>
                @endif
              </div>
              <div>
                @if ($package['price'])
                  <p class="osf-t-3xl mt-5 font-bold {{ $color }}">{{ $package['price'] }}</p>
                  @if ($package['price_note'] !== '')
                    <p class="osf-t-lg mt-1 font-bold">{{ $package['price_note'] }}</p>
                  @endif
                  @if (! empty($package['monthly']))
                    <p class="osf-t-sm mt-1 text-paper-muted">czyli {{ $package['monthly'] }}/mies.</p>
                  @endif
                @endif
              </div>
            </article>
          @endforeach
        </div>
      @elseif ($editor)
        <p class="osf-t-sm mt-6 text-paper-muted" {{ $edit('packages') }}>Dodaj pakiety w panelu.</p>
      @endif
    </div>
  </div>
</div>
