{{-- Panel publikacji online. $publication (PublicationService::panel), $blockers, $back ('wizard'|'offer'), $offer, $canEdit (opcjonalnie). --}}
@php
  $canEdit ??= true;
  $status = $publication['status'];
  $published = $status === \App\Guesstimate\Offers\PublicationStatus::Published;
  $withdrawn = $status === \App\Guesstimate\Offers\PublicationStatus::Withdrawn;
@endphp
<section class="rounded-panel border border-line bg-surface shadow-panel" aria-labelledby="gst-publication">
  <div class="flex flex-wrap items-start justify-between gap-4 border-b border-line px-5 py-4">
    <div class="min-w-0">
      <h2 id="gst-publication" class="flex flex-wrap items-center gap-2 text-lg font-semibold">
        Oferta online
        <span @class([
          'rounded-full px-2.5 py-0.5 text-sm font-medium',
          'bg-canvas text-ink-soft' => ! $published && ! $withdrawn,
          'bg-accent-soft text-accent-strong' => $published,
          'bg-danger-soft text-danger' => $withdrawn,
        ])>{{ $status->label() }}</span>
      </h2>
      @if ($publication['published_version'])
        <p class="mt-1 text-sm text-ink-muted">Publikacja: wersja {{ $publication['published_version'] }}, {{ $publication['published_at'] }}, {{ $publication['published_by'] }}</p>
      @else
        <p class="mt-1 text-sm text-ink-muted">Klient zobaczy ofertę pod bezpiecznym linkiem, bez logowania. Bez godzin, stawek, innych wariantów i notatek wewnętrznych.</p>
      @endif
    </div>
    <div class="flex flex-wrap gap-2">
      <x-gst.button :href="$publication['preview_url']" variant="secondary" target="_blank" rel="noopener">Podgląd</x-gst.button>
      @if ($canEdit)
        @if (! $published)
          @if ($withdrawn)
            <form method="post" action="{{ route('gst.offers.restore', $offer) }}">
              <x-gst.csrf /><input type="hidden" name="back" value="{{ $back }}">
              <x-gst.button type="submit" variant="secondary">Przywróć publikację</x-gst.button>
            </form>
          @endif
          <form method="post" action="{{ route('gst.offers.publish', $offer) }}">
            <x-gst.csrf /><input type="hidden" name="back" value="{{ $back }}">
            <x-gst.button type="submit" :disabled="(bool) $blockers">{{ $publication['published_version'] ? 'Opublikuj aktualną wersję' : 'Opublikuj' }}</x-gst.button>
          </form>
        @else
          <form method="post" action="{{ route('gst.offers.withdraw', $offer) }}">
            <x-gst.csrf /><input type="hidden" name="back" value="{{ $back }}">
            <x-gst.button type="submit" variant="ghost">Wycofaj</x-gst.button>
          </form>
        @endif
      @endif
    </div>
  </div>

  <div class="space-y-4 px-5 py-4">
    @if ($blockers && ! $published)
      <x-gst.alert type="warning">
        <p class="font-medium">Przed publikacją:</p>
        <ul class="mt-1 list-disc pl-5">@foreach ($blockers as $blocker)<li>{{ $blocker }}</li>@endforeach</ul>
      </x-gst.alert>
    @endif

    @if ($publication['url'] && ($published || $withdrawn))
      <div x-data="copyText(@js($publication['url']))" class="flex flex-wrap items-center gap-2">
        <label for="f-public-url" class="sr-only">Link do oferty</label>
        <input type="text" id="f-public-url" readonly value="{{ $publication['url'] }}" class="min-w-0 flex-1 rounded-control border-line-strong bg-canvas px-3 py-2 text-ink-soft" x-on:focus="$el.select()">
        <x-gst.button variant="secondary" x-on:click="copy()"><span x-text="copied ? 'Skopiowano' : 'Kopiuj link'"></span></x-gst.button>
        @if ($published)<x-gst.button :href="$publication['url']" variant="ghost" target="_blank" rel="noopener">Otwórz</x-gst.button>@endif
      </div>
    @endif

    @if ($published && $publication['outdated'])
      <x-gst.alert type="warning">
        Klient widzi wersję {{ $publication['published_version'] }}. Bieżąca wersja ma zmiany, które nie są jeszcze opublikowane.
        @if ($canEdit && ! $blockers)
          <form method="post" action="{{ route('gst.offers.publish', $offer) }}" class="mt-2">
            <x-gst.csrf /><input type="hidden" name="back" value="{{ $back }}">
            <x-gst.button type="submit">Opublikuj nową wersję</x-gst.button>
          </form>
        @endif
      </x-gst.alert>
    @elseif ($published && $canEdit && ! $blockers)
      <form method="post" action="{{ route('gst.offers.publish', $offer) }}" class="text-sm text-ink-muted">
        <x-gst.csrf /><input type="hidden" name="back" value="{{ $back }}">
        Zmieniłeś treść po publikacji? Klient widzi zapisany snapshot —
        <button type="submit" class="font-medium text-accent hover:underline">opublikuj nową wersję</button>.
      </form>
    @endif

    <dl class="grid gap-4 sm:grid-cols-3">
      <div>
        <dt class="text-sm text-ink-muted">Pierwsze wyświetlenie</dt>
        <dd class="mt-0.5">{{ $publication['views']['first'] ? 'Oferta wyświetlona ' . $publication['views']['first'] : 'Jeszcze nie wyświetlona' }}</dd>
      </div>
      <div><dt class="text-sm text-ink-muted">Ostatnie wyświetlenie</dt><dd class="mt-0.5">{{ $publication['views']['last'] ?? '—' }}</dd></div>
      <div><dt class="text-sm text-ink-muted">Liczba wyświetleń</dt><dd class="mt-0.5">{{ $publication['views']['count'] }}</dd></div>
    </dl>
    <p class="text-sm text-ink-muted">Wyświetlenie oznacza otwarcie strony w przeglądarce — nie jest potwierdzeniem przeczytania. Podglądy zespołu, boty i podglądy linków w komunikatorach nie są liczone.</p>

    @if ($publication['history'])
      <details class="rounded-control border border-line">
        <summary class="cursor-pointer px-4 py-2.5 font-medium">Historia publikacji ({{ count($publication['history']) }})</summary>
        <ol class="divide-y divide-line border-t border-line">
          @foreach ($publication['history'] as $row)
            <li class="flex flex-wrap justify-between gap-2 px-4 py-2 text-sm">
              <span>Publikacja {{ $row['number'] }} · wersja {{ $row['version_no'] }} @if ($row['active'])<span class="text-accent">· aktywna</span>@endif</span>
              <span class="text-ink-muted">{{ $row['published_at'] }}, {{ $row['published_by'] }}@if ($row['withdrawn_at'] !== '—') · zakończona {{ $row['withdrawn_at'] }}@endif</span>
            </li>
          @endforeach
        </ol>
      </details>
    @endif
  </div>
</section>
