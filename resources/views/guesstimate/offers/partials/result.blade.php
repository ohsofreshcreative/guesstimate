{{-- Wynik kalkulacji: $summary (ResultPresenter::present), opcjonalnie $error. --}}
@if (! empty($error))
  <x-gst.alert type="error">
    <p class="font-medium">Tej wyceny nie da się policzyć</p>
    <p class="mt-1">{{ $error }}</p>
  </x-gst.alert>
@elseif (! $summary)
  <x-gst.alert>Kalkulacja pojawi się po zapisaniu danych oferty.</x-gst.alert>
@else
  <div class="grid gap-4 md:grid-cols-3">
    @foreach ($summary['levels'] as $level)
      <section @class([
        'rounded-panel border border-line border-t-4 bg-surface p-5 shadow-panel',
        'border-t-risk-low' => $level['key'] === 'low',
        'border-t-risk-mid' => $level['key'] === 'mid',
        'border-t-risk-high' => $level['key'] === 'high',
      ])>
        <h3 class="flex items-center gap-2 font-semibold"><x-gst.risk-dot :level="$level['key']" /> {{ $level['label'] }}</h3>
        <p class="mt-3 text-2xl font-semibold tracking-tight">{{ $level['total'] }}</p>
        <dl class="mt-4 space-y-1.5 text-ink-soft">
          <div class="flex justify-between gap-4"><dt>Godziny</dt><dd class="text-ink">{{ $level['hours'] }}</dd></div>
          <div class="flex justify-between gap-4">
            <dt>Stawka</dt>
            <dd class="text-ink">{{ $level['rate'] }}@if ($level['rate_overridden'])<span class="ml-1 text-sm text-risk-mid">· indywidualna</span>@endif</dd>
          </div>
          @if ($level['rounding'])
            <div class="flex justify-between gap-4 text-sm"><dt>Zaokrąglenie</dt><dd>{{ $level['rounding'] }}</dd></div>
          @endif
        </dl>
      </section>
    @endforeach
  </div>

  @if ($summary['warnings'])
    <x-gst.alert type="warning" class="mt-4">
      <ul class="list-disc space-y-1 pl-5">
        @foreach ($summary['warnings'] as $warning)
          <li>{{ $warning }}</li>
        @endforeach
      </ul>
    </x-gst.alert>
  @endif

  <section class="mt-6 overflow-hidden rounded-panel border border-line bg-surface shadow-panel">
    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-line px-5 py-3">
      <h3 class="font-semibold">Rozbicie na etapy</h3>
      <p class="text-sm text-ink-muted">
        Model {{ $summary['model'] === 'percent' ? 'procentowy' : 'godzinowy' }}
        @if ($summary['pool_hours']) · pula godzin {{ $summary['pool_hours'] }} dzielona procentowo @endif
      </p>
    </div>
    <div class="relative overflow-x-auto">
      <table class="w-full min-w-max text-left">
        <thead class="text-sm text-ink-soft">
          <tr class="border-b border-line">
            <th scope="col" class="px-5 py-2.5 font-medium">Etap</th>
            <th scope="col" class="px-3 py-2.5 font-medium">Źródło</th>
            <th scope="col" class="px-3 py-2.5 text-right font-medium">Godz. min</th>
            <th scope="col" class="px-3 py-2.5 text-right font-medium">Godz. max</th>
            <th scope="col" class="px-3 py-2.5 text-right font-medium"><span class="inline-flex items-center gap-1.5"><x-gst.risk-dot level="low" /> Low</span></th>
            <th scope="col" class="px-3 py-2.5 text-right font-medium"><span class="inline-flex items-center gap-1.5"><x-gst.risk-dot level="mid" /> Mid</span></th>
            <th scope="col" class="px-5 py-2.5 text-right font-medium"><span class="inline-flex items-center gap-1.5"><x-gst.risk-dot level="high" /> High</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-line">
          @foreach ($summary['stages'] as $stage)
            <tr @class(['text-ink-muted' => ! $stage['enabled']])>
              <td class="px-5 py-2.5 font-medium">{{ $stage['name'] }}@if (! $stage['enabled']) <span class="font-normal">(wyłączony)</span>@endif</td>
              <td class="px-3 py-2.5 text-ink-soft">{{ $stage['enabled'] ? $stage['source'] : '—' }}</td>
              <td class="px-3 py-2.5 text-right">{{ $stage['enabled'] ? $stage['hours_min'] : '—' }}</td>
              <td class="px-3 py-2.5 text-right">{{ $stage['enabled'] ? $stage['hours_max'] : '—' }}</td>
              <td class="whitespace-nowrap px-3 py-2.5 text-right">{{ $stage['enabled'] ? $stage['amounts']['low'] : '—' }}</td>
              <td class="whitespace-nowrap px-3 py-2.5 text-right">{{ $stage['enabled'] ? $stage['amounts']['mid'] : '—' }}</td>
              <td class="whitespace-nowrap px-5 py-2.5 text-right">{{ $stage['enabled'] ? $stage['amounts']['high'] : '—' }}</td>
            </tr>
          @endforeach
          @if ($summary['one_time_costs'])
            <tr>
              <td class="px-5 py-2.5 font-medium" colspan="4">Koszty jednorazowe (licencje, usługi)</td>
              <td class="px-3 py-2.5 text-right" colspan="2">{{ $summary['one_time_costs'] }}</td>
              <td class="px-5 py-2.5 text-right">w każdym wariancie</td>
            </tr>
          @endif
        </tbody>
        <tfoot class="border-t-2 border-line-strong font-semibold">
          <tr>
            <td class="px-5 py-3" colspan="2">Razem</td>
            <td class="px-3 py-3 text-right" colspan="2">{{ $summary['hours'] }}</td>
            @foreach ($summary['levels'] as $level)
              <td @class(['whitespace-nowrap py-3 text-right', 'px-3' => $level['key'] !== 'high', 'px-5' => $level['key'] === 'high'])>{{ $level['total'] }}</td>
            @endforeach
          </tr>
        </tfoot>
      </table>
    </div>
  </section>

  @if ($summary['recurring'])
    <section class="mt-4 rounded-panel border border-dashed border-line-strong bg-surface px-5 py-4">
      <h3 class="font-semibold">Koszty cykliczne — poza ceną realizacji</h3>
      <dl class="mt-2 flex flex-wrap gap-x-8 gap-y-1 text-ink-soft">
        <div><dt class="inline">Miesięcznie:</dt> <dd class="inline font-medium text-ink">{{ $summary['recurring']['monthly'] }}</dd></div>
        <div><dt class="inline">Rocznie:</dt> <dd class="inline font-medium text-ink">{{ $summary['recurring']['yearly'] }}</dd></div>
        <div><dt class="inline">Łącznie w skali roku:</dt> <dd class="inline font-medium text-ink">{{ $summary['recurring']['annual'] }}</dd></div>
      </dl>
    </section>
  @endif
@endif
