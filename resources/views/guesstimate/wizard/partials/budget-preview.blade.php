{{-- Wynik modelu budżetowego z wyborem wariantu. $summary: BudgetPresenter::present(), $error. --}}
@if (! empty($error))
  <x-gst.alert type="error">
    <p class="font-medium">Tej wyceny nie da się policzyć</p>
    <p class="mt-1">{{ $error }}</p>
  </x-gst.alert>
@elseif (! $summary)
  <x-gst.alert>Kalkulacja pojawi się po zapisaniu danych oferty.</x-gst.alert>
@else
  <fieldset>
    <legend class="sr-only">Wariant cenowy pokazywany klientowi</legend>
    <div class="grid gap-4 md:grid-cols-3">
      @foreach ($summary['levels'] as $level)
        <label @class([
          'block cursor-pointer rounded-panel border border-t-4 bg-surface p-5 shadow-panel hover:bg-canvas/40 has-checked:border-accent has-checked:ring-2 has-checked:ring-accent/30',
          'border-line',
          'border-t-risk-low' => $level['key'] === 'low',
          'border-t-risk-mid' => $level['key'] === 'mid',
          'border-t-risk-high' => $level['key'] === 'high',
        ])>
          <span class="flex items-center justify-between gap-3">
            <span class="flex items-center gap-2 font-semibold"><x-gst.risk-dot :level="$level['key']" /> {{ $level['label'] }}</span>
            <input type="radio" name="selected_variant" value="{{ $level['key'] }}" @checked($level['selected']) class="text-accent focus:ring-accent">
          </span>
          <span class="mt-3 block text-2xl font-semibold tracking-tight">{{ $level['total'] }}</span>
          <span class="mt-3 block space-y-1 text-ink-soft">
            <span class="flex justify-between gap-4"><span>Godziny</span><span class="text-ink">{{ $level['hours'] }}</span></span>
            <span class="flex justify-between gap-4"><span>Stawka</span><span class="text-ink">{{ $level['rate'] }}@if ($level['rate_overridden'])<span class="ml-1 text-sm text-risk-mid">· indywidualna</span>@endif</span></span>
            @if ($level['rounding'])<span class="flex justify-between gap-4 text-sm"><span>Zaokrąglenie</span><span>{{ $level['rounding'] }}</span></span>@endif
          </span>
          <span @class(['mt-3 block text-sm font-medium', 'text-accent' => $level['selected'], 'text-ink-muted' => ! $level['selected']])>{{ $level['selected'] ? 'Ten wariant zobaczy klient' : 'Wybierz ten wariant' }}</span>
        </label>
      @endforeach
    </div>
  </fieldset>

  <div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="min-w-0 rounded-panel border border-line bg-surface shadow-panel">
      <h3 class="border-b border-line px-5 py-3 font-semibold">Podział ceny — {{ $summary['selected']['label'] }}</h3>
      <dl class="divide-y divide-line">
        @foreach ($summary['selected']['parts'] as $part)
          <div class="flex justify-between gap-4 px-5 py-2.5"><dt>{{ $part['label'] }}</dt><dd class="whitespace-nowrap">{{ $part['amount'] }}</dd></div>
        @endforeach
        <div class="flex justify-between gap-4 px-5 py-3 font-semibold"><dt>Razem netto</dt><dd class="whitespace-nowrap">{{ $summary['selected']['total'] }}</dd></div>
      </dl>
      <p class="border-t border-line px-5 py-3 text-sm text-ink-muted">Podział to prezentacja budżetu ({{ $summary['split'] }} reszty po warsztatach) — nie dolicza godzin. Suma części zawsze równa się cenie.</p>
    </section>

    <section class="min-w-0 rounded-panel border border-line bg-surface shadow-panel">
      <h3 class="border-b border-line px-5 py-3 font-semibold">Skąd są godziny</h3>
      <dl class="divide-y divide-line">
        @foreach ($summary['groups'] as $group)
          <div class="flex justify-between gap-4 px-5 py-2.5"><dt>{{ $group['label'] }} <span class="text-sm text-ink-muted">({{ $group['count'] }})</span></dt><dd class="whitespace-nowrap">{{ $group['hours'] }}</dd></div>
        @endforeach
        <div class="flex justify-between gap-4 px-5 py-3 font-semibold"><dt>Razem</dt><dd class="whitespace-nowrap">{{ $summary['hours'] }}</dd></div>
      </dl>
      @if ($summary['one_time_costs'] || $summary['recurring'])
        <div class="space-y-1 border-t border-line px-5 py-3 text-sm text-ink-soft">
          @if ($summary['one_time_costs'])<p>Koszty zewnętrzne jednorazowe (poza ceną): <span class="text-ink">{{ $summary['one_time_costs'] }}</span></p>@endif
          @if ($summary['recurring'])<p>Koszty cykliczne: {{ $summary['recurring']['monthly'] }} / mies. + {{ $summary['recurring']['yearly'] }} / rok (rocznie {{ $summary['recurring']['annual'] }})</p>@endif
        </div>
      @endif
    </section>
  </div>
@endif
