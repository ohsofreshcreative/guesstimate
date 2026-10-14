{{-- Wyszukiwanie i filtry listy ofert (GET). --}}
<form method="get" action="{{ url()->current() }}" class="grid gap-3 border-b border-line px-6 py-4 md:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-7" role="search">
  @if (($filters['stage'] ?? '') !== '')
    <input type="hidden" name="etap" value="{{ $filters['stage'] }}">
    <p class="text-xs text-ink-muted md:col-span-2 xl:col-span-4 2xl:col-span-7" data-stage-filter>Etap oferty: {{ \App\Guesstimate\Offers\OfferStatus::from($filters['stage'])->label() }} (z pulpitu, niezależnie od publikacji online).</p>
  @endif
  <div class="md:col-span-2">
    <label for="f-q" class="sr-only">Szukaj</label>
    <x-gst.input name="q" id="f-q" :value="$filters['q']" placeholder="Szukaj: numer, klient lub projekt" />
  </div>
  <div>
    <label for="f-status" class="sr-only">Status</label>
    <x-gst.select name="status" id="f-status" :options="$statuses" :value="$filters['status']" placeholder="Wszystkie statusy" />
  </div>
  <div>
    <label for="f-type" class="sr-only">Rodzaj projektu</label>
    <x-gst.select name="type" id="f-type" :options="$types" :value="$filters['type']" placeholder="Wszystkie rodzaje" />
  </div>
  @isset($onlineFilters)
    <div>
      <label for="f-online" class="sr-only">Wyświetlenia oferty online</label>
      <x-gst.select name="online" id="f-online" :options="$onlineFilters" :value="array_key_exists($filters['online'] ?? '', $onlineFilters) ? $filters['online'] : ''" placeholder="Wyświetlenia: wszystkie" />
    </div>
  @endisset
  @if (! empty($authors))
    <div>
      <label for="f-author" class="sr-only">Autor</label>
      <x-gst.select name="author" id="f-author" :options="$authors" :value="$filters['author'] ?: ''" placeholder="Wszyscy autorzy" />
    </div>
  @endif
  @if (! empty($showDates))
    <div class="grid min-w-0 grid-cols-2 gap-2">
      <label for="f-from" class="sr-only">Utworzone od</label>
      <x-gst.input type="date" name="from" id="f-from" :value="$filters['from']" title="Utworzone od" />
      <label for="f-to" class="sr-only">Utworzone do</label>
      <x-gst.input type="date" name="to" id="f-to" :value="$filters['to']" title="Utworzone do" />
    </div>
  @endif
  <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
  <input type="hidden" name="dir" value="{{ $filters['dir'] }}">
  @if ($filters['client'])
    <input type="hidden" name="client" value="{{ $filters['client'] }}">
  @endif
  <div class="flex gap-2">
    <x-gst.button type="submit" variant="secondary">Filtruj</x-gst.button>
    @if ($filters['q'] !== '' || ($filters['online'] ?? '') !== '' || ($filters['stage'] ?? '') !== '' || $filters['status'] !== '' || $filters['type'] !== '' || $filters['author'] || $filters['from'] !== '' || $filters['to'] !== '' || $filters['client'])
      <x-gst.button :href="url()->current()" variant="ghost">Wyczyść</x-gst.button>
    @endif
  </div>
</form>
