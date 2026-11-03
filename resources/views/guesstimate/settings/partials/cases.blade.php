{{-- Realizacje do strony „W kooperacji siła”. Wyniki wpisuje zespół — aplikacja niczego nie generuje. --}}
@php $blankMetrics = array_fill(0, \App\Guesstimate\Document\CaseStudy::MAX_METRICS, ['value' => '', 'label' => '']); @endphp
<div class="max-w-6xl space-y-6">
  <p class="text-ink-soft">Realizacje wybiera się w ofercie (krok „Treści oferty”, maks. 3). Wpisuj wyłącznie prawdziwe, sprawdzone wyniki.</p>

  @foreach (array_merge($cases, [null]) as $case)
    @php $new = $case === null; $id = $new ? 'new' : $case['id']; @endphp
    <form method="post" action="{{ $new ? route('gst.settings.cases.store') : route('gst.settings.cases.update', $case['id']) }}" enctype="multipart/form-data" class="rounded-panel border border-line bg-surface p-6 shadow-panel" @if ($new) x-data="{ open: {{ $cases ? 'false' : 'true' }} }" @endif>
      <x-gst.csrf />
      @if ($new)
        <div class="flex items-center justify-between gap-3">
          <h2 class="text-lg font-semibold">Nowa realizacja</h2>
          <x-gst.button variant="secondary" icon="plus" x-show="! open" x-on:click="open = true">Dodaj realizację</x-gst.button>
        </div>
      @endif
      <div @if ($new) x-show="open" x-cloak @endif class="grid gap-5 lg:grid-cols-3">
        {{-- Zdjęcie: nowy plik albo grafika z biblioteki (id trafia do formularza tylko po wyborze). --}}
        <div class="min-w-0" x-data="mediaPick({ url: @js(route('gst.media.index')), thumb: @js($case['thumb'] ?? null) })">
          <template x-if="thumb"><img :src="thumb" alt="" loading="lazy" class="aspect-video w-full rounded-control object-cover"></template>
          <div x-show="! thumb" class="flex aspect-video items-center justify-center rounded-control bg-canvas text-sm text-ink-muted">Brak zdjęcia</div>
          <input type="hidden" name="attachment_id" :value="id ?? ''">
          <x-gst.button variant="secondary" class="mt-3" x-on:click="show()">Wybierz z biblioteki</x-gst.button>
          <label for="case-img-{{ $id }}" class="mb-1 mt-3 block text-sm font-medium">Albo prześlij zdjęcie (JPG, PNG, WebP)</label>
          <input type="file" id="case-img-{{ $id }}" name="image" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm">
          @if (! $new && $case['thumb'])<div class="mt-2"><x-gst.checkbox name="remove_image" label="Usuń zdjęcie z realizacji" /></div>@endif

          <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-4 py-10" x-show="open" x-cloak role="dialog" aria-modal="true" aria-labelledby="case-pick-{{ $id }}-title" x-on:keydown.escape.window="open = false">
            <div class="fixed inset-0 bg-ink/50" x-on:click="open = false"></div>
            <div class="relative w-full max-w-4xl rounded-panel bg-surface shadow-overlay">
              <div class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-3">
                <h2 id="case-pick-{{ $id }}-title" class="text-lg font-semibold">Zdjęcie realizacji — biblioteka</h2>
                <div class="ml-auto flex gap-2">
                  <label for="case-pick-q-{{ $id }}" class="sr-only">Szukaj w bibliotece</label>
                  <input type="search" id="case-pick-q-{{ $id }}" x-model="q" x-on:keydown.enter.prevent="load(true)" placeholder="Szukaj…" class="block w-52 rounded-control border border-line-strong bg-surface px-3 py-2 text-ink placeholder:text-ink-muted focus:border-accent focus:ring-accent">
                  <x-gst.button variant="secondary" x-on:click="load(true)">Szukaj</x-gst.button>
                </div>
                <x-gst.button variant="ghost" x-on:click="open = false">Zamknij</x-gst.button>
              </div>
              <div class="max-h-140 overflow-y-auto p-5">
                <p class="mb-3 text-sm text-danger" x-show="error" x-text="error"></p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-5">
                  <template x-for="item in items" :key="item.id">
                    <button type="button" class="text-left" x-on:click="pick(item)">
                      <span class="block aspect-square overflow-hidden rounded-control border-2 bg-canvas" :class="id === item.id ? 'border-accent' : 'border-transparent hover:border-line-strong'">
                        <img :src="item.thumb" alt="" loading="lazy" class="size-full object-cover">
                      </span>
                      <span class="mt-1 block truncate text-xs" x-text="item.name"></span>
                    </button>
                  </template>
                </div>
                <p class="py-6 text-center text-sm text-ink-muted" x-show="! loading && ! items.length">Brak obrazów w bibliotece.</p>
                <p class="py-3 text-center text-sm text-ink-muted" x-show="loading">Wczytywanie…</p>
                <div class="mt-4 text-center" x-show="more && ! loading"><x-gst.button variant="secondary" x-on:click="load()">Pokaż więcej</x-gst.button></div>
              </div>
            </div>
          </div>
        </div>
        <div class="grid min-w-0 gap-4 sm:grid-cols-2 lg:col-span-2">
          <x-gst.field label="Klient" :for="'case-name-' . $id" required>
            <x-gst.input name="client_name" :id="'case-name-' . $id" :value="$case['client_name'] ?? ''" maxlength="150" />
          </x-gst.field>
          <x-gst.field label="Adres strony" :for="'case-url-' . $id">
            <x-gst.input name="url" :id="'case-url-' . $id" :value="$case['url'] ?? ''" placeholder="https://" />
          </x-gst.field>
          <x-gst.field label="Krótki opis" :for="'case-desc-' . $id" class="sm:col-span-2">
            <x-gst.textarea name="description" :id="'case-desc-' . $id" :value="$case['description'] ?? ''" rows="2" />
          </x-gst.field>
          <div class="sm:col-span-2">
            <p class="mb-1.5 font-medium">Wskaźniki (maks. 3)</p>
            <div class="space-y-2">
              @foreach (($case['metrics'] ?? $blankMetrics) as $m => $metric)
                <div class="grid grid-cols-3 gap-2">
                  <x-gst.input :name="'metrics[' . $m . '][value]'" :value="$metric['value'] ?? ''" placeholder="np. 370%+" maxlength="20" aria-label="Wartość wskaźnika {{ $m + 1 }}" />
                  <x-gst.input :name="'metrics[' . $m . '][label]'" :value="$metric['label'] ?? ''" placeholder="np. Wzrost współczynnika konwersji" maxlength="80" class="col-span-2" aria-label="Opis wskaźnika {{ $m + 1 }}" />
                </div>
              @endforeach
            </div>
          </div>
          <div class="flex flex-wrap items-center justify-between gap-3 sm:col-span-2">
            @if (! $new)
              <div class="flex flex-wrap gap-4">
                <x-gst.checkbox name="is_active" label="Dostępna w ofertach" :checked="$case['is_active']" />
                <x-gst.checkbox name="delete" label="Usuń realizację" />
              </div>
            @else
              <span></span>
            @endif
            <x-gst.button type="submit">{{ $new ? 'Dodaj realizację' : 'Zapisz' }}</x-gst.button>
          </div>
        </div>
      </div>
    </form>
  @endforeach
</div>
