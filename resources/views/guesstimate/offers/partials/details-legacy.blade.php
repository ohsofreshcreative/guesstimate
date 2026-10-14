{{-- Pełne podsumowanie wersji: $summary (OfferSummary::version). --}}
<div class="grid gap-6 xl:grid-cols-3">
  <section class="min-w-0 rounded-panel border border-line bg-surface p-6 shadow-panel xl:col-span-2">
    <h2 class="font-semibold">Projekt</h2>
    <dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2">
      <div><dt class="text-sm text-ink-muted">Nazwa projektu</dt><dd class="mt-0.5">{{ $summary['project_name'] }}</dd></div>
      <div><dt class="text-sm text-ink-muted">Rodzaj</dt><dd class="mt-0.5">{{ implode(', ', $summary['types']) }}</dd></div>
      <div><dt class="text-sm text-ink-muted">Obecna strona</dt><dd class="mt-0.5 break-all">@if ($summary['current_url'])<a href="{{ $summary['current_url'] }}" class="text-accent hover:underline" target="_blank" rel="noopener">{{ $summary['current_url'] }}</a>@else — @endif</dd></div>
      <div><dt class="text-sm text-ink-muted">Zakres UX</dt><dd class="mt-0.5">{{ $summary['ux'] }}</dd></div>
      <div><dt class="text-sm text-ink-muted">Model kalkulacji</dt><dd class="mt-0.5">{{ $summary['model'] }}</dd></div>
      @if ($summary['stages_disabled'])
        <div><dt class="text-sm text-ink-muted">Wyłączone etapy</dt><dd class="mt-0.5">{{ implode(', ', $summary['stages_disabled']) }}</dd></div>
      @endif
      @if ($summary['description'])
        <div class="sm:col-span-2"><dt class="text-sm text-ink-muted">Opis projektu</dt><dd class="mt-0.5 whitespace-pre-line">{{ $summary['description'] }}</dd></div>
      @endif
      @if ($summary['internal_notes'])
        <div class="sm:col-span-2"><dt class="text-sm text-ink-muted">Uwagi wewnętrzne</dt><dd class="mt-0.5 whitespace-pre-line">{{ $summary['internal_notes'] }}</dd></div>
      @endif
    </dl>
  </section>

  <section class="min-w-0 rounded-panel border border-line bg-surface p-6 shadow-panel">
    <h2 class="font-semibold">Mapa strony w liczbach</h2>
    <dl class="mt-4 grid grid-cols-2 gap-4">
      <div><dt class="text-sm text-ink-muted">Wszystkie podstrony</dt><dd class="text-2xl font-semibold">{{ $summary['stats']['pages'] }}</dd></div>
      <div><dt class="text-sm text-ink-muted">Unikalne szablony</dt><dd class="text-2xl font-semibold">{{ $summary['stats']['templates'] }}</dd></div>
      <div><dt class="text-sm text-ink-muted">Podstrony indywidualne</dt><dd class="text-2xl font-semibold">{{ $summary['stats']['unique_pages'] }}</dd></div>
      <div><dt class="text-sm text-ink-muted">Elementy z treścią</dt><dd class="text-2xl font-semibold">{{ $summary['stats']['content_items'] }}</dd></div>
    </dl>
  </section>
</div>

<div class="mt-6">
  @include('guesstimate.offers.partials.result', ['summary' => $summary['result'], 'error' => $summary['error']])
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
  <section class="min-w-0 rounded-panel border border-line bg-surface shadow-panel">
    <h2 class="border-b border-line px-5 py-3 font-semibold">Mapa strony</h2>
    @if ($summary['sitemap'])
      <ul class="divide-y divide-line">
        @foreach ($summary['sitemap'] as $node)
          <li class="flex items-center justify-between gap-4 py-2 pr-5" style="padding-left: {{ 1.25 + $node['depth'] * 1.25 }}rem">
            <span class="min-w-0 truncate">@if ($node['depth'] > 0)<span class="text-ink-muted" aria-hidden="true">└ </span>@endif{{ $node['name'] }}@if ($node['instances'] > 1) <span class="text-ink-muted">× {{ $node['instances'] }}</span>@endif</span>
            <span class="shrink-0 text-sm text-ink-muted">{{ $node['type'] }}@if ($node['content']) · treść @endif</span>
          </li>
        @endforeach
      </ul>
    @else
      <p class="px-5 py-6 text-ink-muted">Mapa strony jest pusta.</p>
    @endif
  </section>

  <section class="min-w-0 rounded-panel border border-line bg-surface shadow-panel">
    <h2 class="border-b border-line px-5 py-3 font-semibold">Szablony liczone w wycenie</h2>
    @if ($summary['templates'])
      <div class="relative overflow-x-auto">
        <table class="w-full text-left">
          <thead class="text-sm text-ink-soft"><tr class="border-b border-line"><th class="px-5 py-2 font-medium">Szablon</th><th class="px-3 py-2 font-medium">Użycia</th><th class="px-3 py-2 text-right font-medium">UI</th><th class="px-5 py-2 text-right font-medium">Dev</th></tr></thead>
          <tbody class="divide-y divide-line">
            @foreach ($summary['templates'] as $template)
              <tr>
                <td class="px-5 py-2">{{ $template['name'] }} <span class="text-sm text-ink-muted">{{ $template['kind'] }}</span></td>
                <td class="px-3 py-2">{{ $template['uses'] }}</td>
                <td class="whitespace-nowrap px-3 py-2 text-right">{{ $template['ui'] }}</td>
                <td class="whitespace-nowrap px-5 py-2 text-right">{{ $template['dev'] }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <p class="px-5 py-6 text-ink-muted">Brak szablonów.</p>
    @endif
  </section>

  <section class="min-w-0 rounded-panel border border-line bg-surface shadow-panel">
    <h2 class="border-b border-line px-5 py-3 font-semibold">Funkcjonalności</h2>
    @if ($summary['features'])
      <ul class="divide-y divide-line">
        @foreach ($summary['features'] as $feature)
          <li class="flex items-start justify-between gap-4 px-5 py-2.5">
            <div class="min-w-0">
              <p>{{ $feature['name'] }}</p>
              <p class="text-sm text-ink-muted">{{ $feature['category'] }} · {{ $feature['stage'] }}@if ($feature['notes']) · {{ $feature['notes'] }}@endif</p>
            </div>
            <span class="shrink-0 whitespace-nowrap">{{ $feature['hours'] }}</span>
          </li>
        @endforeach
      </ul>
    @else
      <p class="px-5 py-6 text-ink-muted">Nie wybrano funkcjonalności.</p>
    @endif
  </section>

  <section class="min-w-0 rounded-panel border border-line bg-surface shadow-panel">
    <h2 class="border-b border-line px-5 py-3 font-semibold">Prace dodatkowe i koszty</h2>
    @if ($summary['work_items'] || $summary['costs'])
      <ul class="divide-y divide-line">
        @foreach ($summary['work_items'] as $work)
          <li class="flex items-start justify-between gap-4 px-5 py-2.5">
            <div class="min-w-0"><p>{{ $work['name'] }}</p><p class="text-sm text-ink-muted">{{ $work['stage'] }}@if ($work['notes']) · {{ $work['notes'] }}@endif</p></div>
            <span class="shrink-0 whitespace-nowrap">{{ $work['hours'] }}</span>
          </li>
        @endforeach
        @foreach ($summary['costs'] as $cost)
          <li class="flex items-start justify-between gap-4 px-5 py-2.5">
            <div class="min-w-0"><p>{{ $cost['name'] }}</p><p class="text-sm text-ink-muted">{{ $cost['type'] }} · {{ $cost['billing'] }} · ilość {{ $cost['quantity'] }}</p></div>
            <span class="shrink-0 whitespace-nowrap">{{ $cost['amount'] }}</span>
          </li>
        @endforeach
      </ul>
    @else
      <p class="px-5 py-6 text-ink-muted">Brak prac dodatkowych i kosztów.</p>
    @endif
  </section>
</div>
