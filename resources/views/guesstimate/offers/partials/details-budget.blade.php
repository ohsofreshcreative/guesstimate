{{-- Pełne podsumowanie wersji w modelu budżetowym: $summary (OfferSummary::version). --}}
<div class="grid gap-6 xl:grid-cols-3">
  <section class="min-w-0 rounded-panel border border-line bg-surface p-6 shadow-panel xl:col-span-2">
    <h2 class="font-semibold">Projekt</h2>
    <dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2">
      <div><dt class="text-sm text-ink-muted">Nazwa projektu</dt><dd class="mt-0.5">{{ $summary['project_name'] }}</dd></div>
      <div><dt class="text-sm text-ink-muted">Rodzaj projektu</dt><dd class="mt-0.5">{{ $summary['type'] }}</dd></div>
      <div><dt class="text-sm text-ink-muted">Dodatkowe jednostki</dt><dd class="mt-0.5">{{ (float) $summary['type_units'] > 0 ? '+' . \App\Guesstimate\Support\Format::hours($summary['type_units'], false) : '0' }}</dd></div>
      <div><dt class="text-sm text-ink-muted">Zakres UX</dt><dd class="mt-0.5">{{ $summary['ux'] }}</dd></div>
      <div><dt class="text-sm text-ink-muted">Wariant dla klienta</dt><dd class="mt-0.5 flex items-center gap-2"><x-gst.risk-dot :level="$summary['variant']" /> {{ $summary['variant_label'] }}</dd></div>
      <div><dt class="text-sm text-ink-muted">Godziny na podstronę</dt><dd class="mt-0.5">{{ $summary['page_hours'] }}</dd></div>
      @if ($summary['internal_notes'])
        <div class="sm:col-span-2"><dt class="text-sm text-ink-muted">Notatki wewnętrzne</dt><dd class="mt-0.5 whitespace-pre-line">{{ $summary['internal_notes'] }}</dd></div>
      @endif
    </dl>
  </section>

  <section class="min-w-0 rounded-panel border border-line bg-surface p-6 shadow-panel">
    <h2 class="font-semibold">Mapa strony w liczbach</h2>
    <dl class="mt-4 grid grid-cols-2 gap-4">
      <div><dt class="text-sm text-ink-muted">Podstrony</dt><dd class="text-2xl font-semibold">{{ $summary['stats']['pages'] }}</dd></div>
      <div><dt class="text-sm text-ink-muted">Jedn. rodzaju projektu</dt><dd class="text-2xl font-semibold">{{ $summary['stats']['type_units'] }}</dd></div>
      <div><dt class="text-sm text-ink-muted">UI (szacunek)</dt><dd class="text-xl font-semibold">{{ \App\Guesstimate\Support\Format::hoursRange($summary['stats']['ui']) }}</dd></div>
      <div><dt class="text-sm text-ink-muted">Dev (szacunek)</dt><dd class="text-xl font-semibold">{{ \App\Guesstimate\Support\Format::hoursRange($summary['stats']['dev']) }}</dd></div>
    </dl>
  </section>
</div>

<div class="mt-6">
  @if ($summary['error'])
    <x-gst.alert type="error"><p class="font-medium">Tej wyceny nie da się policzyć</p><p class="mt-1">{{ $summary['error'] }}</p></x-gst.alert>
  @elseif ($summary['result'])
    <div class="grid gap-4 md:grid-cols-3">
      @foreach ($summary['result']['levels'] as $level)
        <section @class([
          'rounded-panel border border-t-4 bg-surface p-5 shadow-panel',
          'border-accent ring-2 ring-accent/30' => $level['selected'],
          'border-line' => ! $level['selected'],
          'border-t-risk-low' => $level['key'] === 'low',
          'border-t-risk-mid' => $level['key'] === 'mid',
          'border-t-risk-high' => $level['key'] === 'high',
        ])>
          <h3 class="flex items-center justify-between gap-2 font-semibold"><span class="flex items-center gap-2"><x-gst.risk-dot :level="$level['key']" /> {{ $level['label'] }}</span>@if ($level['selected'])<span class="text-sm font-medium text-accent">dla klienta</span>@endif</h3>
          <p class="mt-3 text-2xl font-semibold tracking-tight">{{ $level['total'] }}</p>
          <dl class="mt-3 space-y-1 text-ink-soft">
            <div class="flex justify-between gap-4"><dt>Godziny</dt><dd class="text-ink">{{ $level['hours'] }}</dd></div>
            <div class="flex justify-between gap-4"><dt>Stawka</dt><dd class="text-ink">{{ $level['rate'] }}@if ($level['rate_overridden'])<span class="ml-1 text-sm text-risk-mid">· indywidualna</span>@endif</dd></div>
          </dl>
          <dl class="mt-3 space-y-1 border-t border-line pt-3 text-sm text-ink-soft">
            @foreach ($level['parts'] as $part)
              <div class="flex justify-between gap-4"><dt>{{ $part['label'] }}</dt><dd class="whitespace-nowrap text-ink">{{ $part['amount'] }}</dd></div>
            @endforeach
          </dl>
        </section>
      @endforeach
    </div>
    <p class="mt-3 text-sm text-ink-muted">Podział: {{ $summary['result']['split'] }} reszty po warsztatach. Godziny łącznie: {{ $summary['result']['hours'] }}.
      @if ($summary['result']['one_time_costs']) Koszty zewnętrzne poza ceną: {{ $summary['result']['one_time_costs'] }}.@endif
      @if ($summary['result']['recurring']) Koszty cykliczne: {{ $summary['result']['recurring']['annual'] }} / rok.@endif
    </p>
  @endif
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
  <section class="min-w-0 rounded-panel border border-line bg-surface shadow-panel">
    <h2 class="border-b border-line px-5 py-3 font-semibold">Mapa strony</h2>
    @if ($summary['pages'])
      <ol class="divide-y divide-line">
        @foreach ($summary['pages'] as $i => $page)
          <li class="flex justify-between gap-4 px-5 py-2"><span class="min-w-0 truncate"><span class="text-ink-muted">{{ $i + 1 }}.</span> {{ $page['name'] }}</span>@if ($page['custom'])<span class="shrink-0 text-sm text-risk-mid">własne godziny</span>@endif</li>
        @endforeach
      </ol>
    @else
      <p class="px-5 py-6 text-ink-muted">Mapa strony jest pusta.</p>
    @endif
  </section>

  <section class="min-w-0 rounded-panel border border-line bg-surface shadow-panel">
    <h2 class="border-b border-line px-5 py-3 font-semibold">Funkcjonalności</h2>
    @if ($summary['features'])
      <ul class="divide-y divide-line">
        @foreach ($summary['features'] as $feature)
          <li class="px-5 py-2.5">
            <div class="flex justify-between gap-4"><span>{{ $feature['name'] }} <span class="text-sm text-ink-muted">{{ $feature['category'] }}</span></span><span class="shrink-0 whitespace-nowrap">{{ $feature['hours'] }}</span></div>
            @if ($feature['notes'])<p class="mt-0.5 whitespace-pre-line text-sm text-ink-muted">{{ $feature['notes'] }}</p>@endif
          </li>
        @endforeach
      </ul>
    @else
      <p class="px-5 py-6 text-ink-muted">Brak funkcjonalności.</p>
    @endif
  </section>

  <section class="min-w-0 rounded-panel border border-line bg-surface shadow-panel">
    <h2 class="border-b border-line px-5 py-3 font-semibold">Prace dodatkowe</h2>
    @if ($summary['work_items'])
      <ul class="divide-y divide-line">
        @foreach ($summary['work_items'] as $work)
          <li class="flex justify-between gap-4 px-5 py-2.5"><span>{{ $work['name'] }}@if ($work['notes']) <span class="text-sm text-ink-muted">— {{ $work['notes'] }}</span>@endif</span><span class="shrink-0 whitespace-nowrap">{{ $work['hours'] }}</span></li>
        @endforeach
      </ul>
    @else
      <p class="px-5 py-6 text-ink-muted">Brak prac dodatkowych.</p>
    @endif
  </section>

  <section class="min-w-0 rounded-panel border border-line bg-surface shadow-panel">
    <h2 class="border-b border-line px-5 py-3 font-semibold">Koszty zewnętrzne <span class="font-normal text-ink-muted">(poza ceną)</span></h2>
    @if ($summary['costs'])
      <ul class="divide-y divide-line">
        @foreach ($summary['costs'] as $cost)
          <li class="flex justify-between gap-4 px-5 py-2.5"><span>{{ $cost['name'] }} <span class="text-sm text-ink-muted">{{ $cost['type'] }} · {{ $cost['billing'] }}@if ($cost['quantity'] !== '1') · × {{ $cost['quantity'] }}@endif</span></span><span class="shrink-0 whitespace-nowrap">{{ $cost['amount'] }}</span></li>
        @endforeach
      </ul>
    @else
      <p class="px-5 py-6 text-ink-muted">Brak kosztów zewnętrznych.</p>
    @endif
  </section>
</div>

@if ($summary['content_missing'])
  <x-gst.alert type="warning" class="mt-6">Treści oferty są niepełne: {{ implode(' ', $summary['content_missing']) }}</x-gst.alert>
@endif
