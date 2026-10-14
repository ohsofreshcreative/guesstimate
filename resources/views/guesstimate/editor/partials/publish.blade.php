{{-- Publikacja w edytorze: klient widzi zapisany snapshot; „Opublikuj nową wersję” aktualizuje dokument klienta. --}}
<div class="{{ $section }} space-y-3">
  <div class="flex items-center gap-2">
    <h2 class="text-lg font-semibold">Oferta online</h2>
    <span @class([
      'rounded-full px-2.5 py-0.5 text-sm font-medium',
      'bg-canvas text-ink-soft' => ! $published && ! $withdrawn,
      'bg-accent-soft text-accent-strong' => $published,
      'bg-danger-soft text-danger' => $withdrawn,
    ])>{{ $status->label() }}</span>
  </div>

  @if ($publication['published_version'])
    <p class="text-sm text-ink-muted">Klient widzi publikację wersji {{ $publication['published_version'] }} z {{ $publication['published_at'] }} ({{ $publication['published_by'] }}).</p>
  @else
    <p class="text-sm text-ink-muted">Oferta nie była jeszcze publikowana. Klient zobaczy ją pod bezpiecznym linkiem, bez godzin, stawek i notatek wewnętrznych.</p>
  @endif

  <div class="rounded-control border border-risk-mid/30 bg-risk-mid-soft px-3 py-2 text-sm" x-show="dirty" x-cloak>Masz niezapisane zmiany — zapisz je, zanim opublikujesz nową wersję.</div>
  @if ($published)
    <div class="rounded-control border border-risk-mid/30 bg-risk-mid-soft px-3 py-2 text-sm" x-show="! dirty && outdated" @if (! $publication['outdated']) x-cloak @endif data-outdated>
      Wersja robocza różni się od tego, co widzi klient. Kliknij „Opublikuj nową wersję”, aby zaktualizować dokument klienta.
    </div>
  @endif

  <template x-if="blockers.length">
    <div class="rounded-control border border-risk-mid/30 bg-risk-mid-soft px-3 py-2 text-sm" data-blockers>
      <p class="font-medium">Przed publikacją:</p>
      <ul class="mt-1 list-disc pl-5"><template x-for="blocker in blockers" :key="blocker"><li x-text="blocker"></li></template></ul>
    </div>
  </template>

  @if ($canEdit)
    <div class="flex flex-wrap gap-2">
      <form method="post" action="{{ route('gst.offers.publish', $offer) }}" x-on:submit="if (! canPublish) { $event.preventDefault(); return } publishing = true" data-publish-form>
        <x-gst.csrf /><input type="hidden" name="back" value="{{ $back }}"><input type="hidden" name="after" value="summary">
        <button type="submit" class="{{ $primary }}" :disabled="! canPublish" :aria-busy="publishing">{{ $published ? 'Opublikuj nową wersję' : ($publication['published_version'] ? 'Opublikuj aktualną wersję' : 'Opublikuj') }}</button>
      </form>
      @if ($published)
        <form method="post" action="{{ route('gst.offers.withdraw', $offer) }}">
          <x-gst.csrf /><input type="hidden" name="back" value="{{ $back }}">
          <button type="submit" class="{{ $ghost }}">Wycofaj</button>
        </form>
      @elseif ($withdrawn)
        <form method="post" action="{{ route('gst.offers.restore', $offer) }}">
          <x-gst.csrf /><input type="hidden" name="back" value="{{ $back }}">
          <button type="submit" class="{{ $secondary }}">Przywróć publikację</button>
        </form>
      @endif
    </div>
  @endif

  @if ($publication['url'] && ($published || $withdrawn))
    <div x-data="copyText(@js($publication['url']))" class="flex gap-2">
      <label for="f-ed-public-url" class="sr-only">Link do oferty</label>
      <input type="text" id="f-ed-public-url" readonly value="{{ $publication['url'] }}" class="{{ $input }} min-w-0 flex-1 bg-canvas text-ink-soft" x-on:focus="$el.select()">
      <button type="button" class="{{ $secondary }}" x-on:click="copy()"><span x-text="copied ? 'Skopiowano' : 'Kopiuj'"></span></button>
    </div>
  @endif
</div>

<div class="{{ $section }}">
  <dl class="grid grid-cols-3 gap-2 text-sm">
    <div><dt class="text-xs text-ink-muted">Pierwsze</dt><dd>{{ $publication['views']['first'] ?? '—' }}</dd></div>
    <div><dt class="text-xs text-ink-muted">Ostatnie</dt><dd>{{ $publication['views']['last'] ?? '—' }}</dd></div>
    <div><dt class="text-xs text-ink-muted">Wyświetlenia</dt><dd>{{ $publication['views']['count'] }}</dd></div>
  </dl>
  @if ($publication['history'])
    <details class="mt-3 rounded-control border border-line text-sm">
      <summary class="cursor-pointer px-3 py-2 font-medium">Historia publikacji ({{ count($publication['history']) }})</summary>
      <ol class="divide-y divide-line border-t border-line">
        @foreach ($publication['history'] as $row)
          <li class="px-3 py-1.5">Publikacja {{ $row['number'] }} · wersja {{ $row['version_no'] }} @if ($row['active'])<span class="text-accent">· aktywna</span>@endif<span class="block text-xs text-ink-muted">{{ $row['published_at'] }}, {{ $row['published_by'] }}@if ($row['withdrawn_at'] !== '—') · zakończona {{ $row['withdrawn_at'] }}@endif</span></li>
        @endforeach
      </ol>
    </details>
  @endif
</div>

@if ($canEdit && $offer->status->canTransitionTo(\App\Guesstimate\Offers\OfferStatus::Prepared))
  <div class="{{ $section }}">
    <form method="post" action="{{ route('gst.offers.status', $offer) }}">
      <x-gst.csrf /><input type="hidden" name="status" value="prepared"><input type="hidden" name="back" value="document">
      <button type="submit" class="{{ $secondary }}" :disabled="dirty">Oznacz ofertę jako przygotowaną</button>
    </form>
  </div>
@endif
