{{-- Panel „Wycena na żywo”. Pierwszy render = zapisany stan wersji; potem zawartość [data-live-body]
     podmienia skrypt live-price.js wynikiem z serwera (POST …/kreator/{krok}/wycena). $summary: BudgetPresenter::present() --}}
@php $live ??= false; @endphp
@unless ($live)
<div class="rounded-panel border border-line bg-surface p-5 shadow-panel xl:sticky xl:top-6"
  data-live-price @isset($liveUrl) data-url="{{ $liveUrl }}" @endisset aria-live="polite">
  <div class="flex items-center justify-between gap-2">
    <h2 class="text-sm font-medium text-ink-soft">Wycena na żywo</h2>
    <span class="text-xs text-ink-muted" data-live-status hidden>przeliczanie…</span>
  </div>
  <div data-live-body>
@endunless
  @if ($error)
    <p class="mt-2 text-sm text-danger" data-live-error>{{ $error }}</p>
    <p class="mt-1 text-sm text-ink-muted">Cena pojawi się po poprawieniu danych.</p>
  @elseif ($summary)
    <p class="mt-2 text-sm text-ink-muted">Wybrany wariant: {{ $summary['selected']['label'] }}</p>
    <p class="text-2xl font-semibold tracking-tight" data-live-total>{{ $summary['selected']['total'] }}</p>
    <p class="mt-1 text-sm text-ink-soft"><span data-live-hours>{{ $summary['hours'] }}</span> · {{ $summary['selected']['rate'] }}</p>
    <dl class="mt-4 space-y-1.5 border-t border-line pt-3 text-sm">
      @foreach ($summary['levels'] as $level)
        <div @class(['flex items-center justify-between gap-3', 'font-medium text-ink' => $level['selected'], 'text-ink-soft' => ! $level['selected']])>
          <dt class="flex items-center gap-2 whitespace-nowrap"><x-gst.risk-dot :level="$level['key']" /> {{ $level['label'] }}</dt>
          <dd class="whitespace-nowrap" data-live-level="{{ $level['key'] }}">{{ $level['total'] }}</dd>
        </div>
      @endforeach
    </dl>
    @if ($summary['one_time_costs'] || $summary['recurring'])
      <p class="mt-3 border-t border-line pt-3 text-sm text-ink-muted">
        Poza ceną:
        @if ($summary['one_time_costs']) koszty zewnętrzne {{ $summary['one_time_costs'] }}@endif
        @if ($summary['recurring']){{ $summary['one_time_costs'] ? ',' : '' }} cykliczne {{ $summary['recurring']['annual'] }} / rok @endif
      </p>
    @endif
  @else
    <p class="mt-2 text-sm text-ink-muted">Cena pojawi się po uzupełnieniu danych oferty.</p>
  @endif
  @if ($live)
    <p class="mt-3 text-xs text-ink-muted">Podgląd z niezapisanych danych — zapisz krok, aby zachować zmiany.</p>
  @endif
@unless ($live)
  </div>
</div>
@endunless
