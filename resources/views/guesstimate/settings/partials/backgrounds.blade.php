{{-- Biblioteka teł stron dokumentu (pliki w bibliotece mediów WordPressa). --}}
<div class="max-w-6xl space-y-6">
  <section class="rounded-panel border border-line bg-surface p-6 shadow-panel">
    <h2 class="text-lg font-semibold">Dodaj tło</h2>
    <p class="mt-1 text-ink-soft">JPG, PNG lub WebP, najlepiej w proporcjach A4 poziomo (np. 2480×1754 lub 3508×2480). Inne proporcje są dopuszczalne — pokażemy ostrzeżenie. WordPress tworzy mniejsze wersje pliku, więc dokument ładuje obraz dopasowany do ekranu.</p>
    <form method="post" action="{{ route('gst.settings.backgrounds.store') }}" enctype="multipart/form-data" class="mt-5 grid items-end gap-4 md:grid-cols-4">
      <x-gst.csrf />
      <x-gst.field label="Plik" for="f-bg-file" class="md:col-span-2">
        <input type="file" id="f-bg-file" name="file" accept="image/jpeg,image/png,image/webp" required class="block w-full text-sm">
      </x-gst.field>
      <x-gst.field label="Nazwa" for="f-bg-name">
        <x-gst.input name="name" id="f-bg-name" placeholder="np. Tło 01 – okładka" maxlength="150" />
      </x-gst.field>
      <x-gst.field label="Dopasowanie" for="f-bg-fit">
        <x-gst.select name="fit" id="f-bg-fit" :options="$fits" value="cover" />
      </x-gst.field>
      <div class="md:col-span-4 flex justify-end"><x-gst.button type="submit" icon="plus">Prześlij do biblioteki mediów</x-gst.button></div>
    </form>

    @if ($recentMedia)
      <details class="mt-5 rounded-control border border-line">
        <summary class="cursor-pointer px-4 py-2.5 font-medium">Albo wybierz obraz z biblioteki mediów WordPress ({{ count($recentMedia) }})</summary>
        <div class="grid gap-3 border-t border-line p-4 sm:grid-cols-3 lg:grid-cols-6">
          @foreach ($recentMedia as $media)
            <form method="post" action="{{ route('gst.settings.backgrounds.store') }}" class="min-w-0">
              <x-gst.csrf />
              <input type="hidden" name="attachment_id" value="{{ $media['id'] }}">
              <button type="submit" class="block w-full rounded-control border border-line text-left hover:border-accent">
                @if ($media['thumb'])<img src="{{ $media['thumb'] }}" alt="" loading="lazy" class="aspect-a4 w-full rounded-t-control object-cover">@endif
                <span class="block truncate px-2 py-1 text-sm">{{ $media['name'] }}</span>
              </button>
            </form>
          @endforeach
        </div>
      </details>
    @endif
  </section>

  <section class="rounded-panel border border-line bg-surface shadow-panel">
    <h2 class="border-b border-line px-6 py-4 text-lg font-semibold">Tła w bibliotece ({{ count($backgrounds) }})</h2>
    @if (! $backgrounds)
      <x-gst.empty-state icon="settings" title="Brak teł" text="Dodaj pierwszą grafikę powyżej. Bez tła strony mają ciemny styl domyślny." />
    @else
      <div class="grid gap-5 p-6 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($backgrounds as $bg)
          <article class="min-w-0 rounded-panel border border-line">
            @if ($bg['thumb'])
              <img src="{{ $bg['thumb'] }}" alt="" loading="lazy" @class(['aspect-a4 w-full rounded-t-panel bg-ink', 'object-cover' => $bg['fit'] === 'cover', 'object-contain' => $bg['fit'] === 'contain'])>
            @else
              <div class="flex aspect-a4 items-center justify-center rounded-t-panel bg-canvas text-sm text-danger">Plik niedostępny</div>
            @endif
            <div class="space-y-3 p-4">
              <p class="flex flex-wrap items-center gap-2 text-sm text-ink-muted">
                {{ $bg['size'] }}
                @if ($bg['warning'])<span class="rounded-full bg-risk-mid-soft px-2 py-0.5 text-risk-mid">proporcje inne niż A4</span>@endif
                @foreach ($bg['defaults'] as $key)<span class="rounded-full bg-accent-soft px-2 py-0.5 text-accent-strong">domyślne: {{ $docPages[$key] }}</span>@endforeach
              </p>
              <form method="post" action="{{ route('gst.settings.backgrounds.update', $bg['id']) }}" class="flex flex-wrap items-end gap-2">
                <x-gst.csrf />
                <div class="min-w-0 flex-1">
                  <label for="bg-name-{{ $bg['id'] }}" class="sr-only">Nazwa</label>
                  <x-gst.input name="name" :id="'bg-name-' . $bg['id']" :value="$bg['name']" maxlength="150" />
                </div>
                <label for="bg-fit-{{ $bg['id'] }}" class="sr-only">Dopasowanie</label>
                <x-gst.select name="fit" :id="'bg-fit-' . $bg['id']" :options="$fits" :value="$bg['fit']" class="w-44" />
                <x-gst.button type="submit" variant="secondary">Zapisz</x-gst.button>
              </form>
              <p class="text-sm text-ink-muted">Używane: {{ $bg['usage']['published'] }} opublikowanych ofert, {{ $bg['usage']['drafts'] }} wersji roboczych.</p>
              <x-gst.button variant="ghost" class="text-danger" x-data x-on:click="$dispatch('open-modal', 'bg-delete-{{ $bg['id'] }}')">Usuń</x-gst.button>
            </div>
          </article>

          <x-gst.modal :id="'bg-delete-' . $bg['id']" title="Usuń tło">
            <form method="post" action="{{ route('gst.settings.backgrounds.destroy', $bg['id']) }}" class="space-y-4" x-data="{ file: false }">
              <x-gst.csrf />
              <p>Tło „{{ $bg['name'] }}” zniknie z biblioteki Guesstimate. Opublikowane oferty zachowają swój wygląd, a wersje robocze, które go używają, wrócą do stylu domyślnego.</p>
              <label class="flex items-start gap-3">
                <input type="checkbox" name="delete_file" value="1" x-model="file" class="mt-0.5 size-4 rounded border-line-strong text-danger focus:ring-danger">
                <span>Usuń także plik z biblioteki mediów WordPress <span class="block text-sm text-ink-muted">Nieodwracalne. @if ($bg['usage']['published'])Plik jest używany w {{ $bg['usage']['published'] }} opublikowanych ofertach — zniknie z nich obraz tła.@endif</span></span>
              </label>
              <div x-show="file" x-cloak>
                <x-gst.field label="Wpisz USUŃ, aby potwierdzić usunięcie pliku" :for="'bg-confirm-' . $bg['id']">
                  <x-gst.input name="confirm" :id="'bg-confirm-' . $bg['id']" autocomplete="off" />
                </x-gst.field>
              </div>
              <div class="flex justify-end gap-2">
                <x-gst.button variant="ghost" x-on:click="$dispatch('close-modal', 'bg-delete-{{ $bg['id'] }}')">Anuluj</x-gst.button>
                <x-gst.button type="submit">Usuń tło</x-gst.button>
              </div>
            </form>
          </x-gst.modal>
        @endforeach
      </div>
    @endif
  </section>
</div>
