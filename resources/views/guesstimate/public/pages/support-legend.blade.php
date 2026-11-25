{{-- Oferta support: legenda punktów pakietów w kolumnach, w kolorze pakietu. Dane: $doc['support']['legend'] (SupportPackages::present). --}}
@php
  $columns = array_values(array_filter($doc['support']['legend'] ?? [], fn ($column) => $column['items']));
  $colors = ['orange' => 'text-orange', 'flame' => 'text-flame', 'magenta' => 'text-magenta', 'violet' => 'text-violet', 'indigo' => 'text-indigo'];
  $grid = [1 => 'md:grid-cols-1 sheet:grid-cols-1', 2 => 'md:grid-cols-2 sheet:grid-cols-2', 3 => 'md:grid-cols-3 sheet:grid-cols-3', 4 => 'md:grid-cols-2 sheet:grid-cols-4'][count($columns)] ?? '';
@endphp
<div class="osf-pad osf-column">
  <img src="{{ $logo }}" alt="OhSoFresh Creative" class="osf-logo self-start">
  <div class="osf-center">
    @include('guesstimate.public.partials.heading', ['page' => $page, 'edit' => $edit, 'class' => 'mt-5'])
    <div data-flow-body>
      @if (! empty($page['html']))
        <div class="osf-prose osf-t-sm mt-3 max-w-4xl" {{ $edit('html') }}>{!! $page['html'] !!}</div>
      @endif
      @if ($columns)
        <div class="mt-6 grid osf-gap-lg {{ $grid }}" {{ $edit('points') }}>
          @foreach ($columns as $column)
            <dl @class(['flex min-w-0 flex-col osf-gap', 'sheet:border-l sheet:border-night-line sheet:pl-6' => ! $loop->first])>
              @foreach ($column['items'] as $item)
                <div>
                  <dt class="osf-t-sm font-bold {{ $colors[$column['color']] ?? 'text-orange' }}">{{ $item['name'] }}</dt>
                  @if ($item['description'] !== '')
                    <dd class="osf-t-xs mt-0.5 whitespace-pre-line">{{ $item['description'] }}</dd>
                  @endif
                </div>
              @endforeach
            </dl>
          @endforeach
        </div>
      @elseif ($editor)
        <p class="osf-t-sm mt-6 text-paper-muted" {{ $edit('points') }}>Dodaj punkty pakietów w panelu.</p>
      @endif
    </div>
  </div>
</div>
