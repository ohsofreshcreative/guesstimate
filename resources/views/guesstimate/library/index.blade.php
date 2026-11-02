@extends('guesstimate.layouts.app')

@section('title', 'Biblioteka')

@section('content')
  <div x-data="mediaLibrary(@js($config))" x-on:dragover.prevent="dragging = canUpload" x-on:dragleave.self="dragging = false" x-on:drop.prevent="drop($event)" data-media-library>
    <x-gst.page-header title="Biblioteka" description="Wspólne grafiki do ofert. Ten sam plik możesz użyć w wielu ofertach bez ponownego przesyłania.">
      <x-slot:actions>
        @if ($config['canUpload'])
          <label class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-control bg-accent px-4 py-2 font-medium text-white transition-colors hover:bg-accent-strong">
            <x-gst.icon name="plus" class="size-4" />
            Prześlij pliki
            <input type="file" multiple accept="{{ implode(',', $config['mimes']) }}" class="sr-only" x-on:change="addFiles($event.target.files); $event.target.value = ''" data-library-upload>
          </label>
        @endif
      </x-slot:actions>
    </x-gst.page-header>

    <p class="mb-4 text-sm text-ink-muted" x-show="canUpload"><span x-text="formats"></span>, do <span x-text="limit"></span> na plik. Możesz też upuścić pliki na tę stronę.</p>
    <p class="mb-4 rounded-control border border-accent bg-accent-soft p-4 text-center text-ink" x-show="dragging" x-cloak>Upuść pliki, aby dodać je do biblioteki.</p>
    <p class="mb-4 text-sm text-danger" x-show="error" x-text="error" x-cloak role="alert"></p>
    <p class="mb-4 text-sm text-ink-soft" x-show="message && ! detail" x-text="message" x-cloak role="status"></p>

    {{-- Kolejka przesyłania --}}
    <section class="mb-4 rounded-panel border border-line bg-surface p-4 shadow-panel" x-show="queue.length" x-cloak aria-label="Przesyłane pliki" data-upload-queue>
      <ul class="divide-y divide-line">
        <template x-for="row in queue" :key="row.key">
          <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
            <span class="min-w-0 truncate" x-text="row.name"></span>
            <span :class="row.status === 'error' ? 'text-danger' : 'text-ink-muted'" x-text="row.error || row.note || (row.status === 'done' ? 'Dodano' : (row.status === 'sending' ? 'Przesyłanie…' : 'W kolejce'))"></span>
          </li>
        </template>
      </ul>
      <div class="mt-2 text-right" x-show="! uploading"><x-gst.button variant="ghost" x-on:click="clearQueue()">Wyczyść listę</x-gst.button></div>
    </section>

    <div class="grid gap-6 lg:grid-cols-4">
      {{-- Filtry --}}
      <aside class="min-w-0" aria-label="Filtry biblioteki">
        <section class="rounded-panel border border-line bg-surface p-4 shadow-panel">
          <h2 class="font-semibold">Foldery</h2>
          <ul class="mt-2 space-y-1 text-sm">
            <li><button type="button" class="w-full rounded-control px-2 py-1 text-left hover:bg-canvas" :class="! folder && ! archived && 'bg-accent-soft font-medium'" x-on:click="setFolder(null)">Wszystkie grafiki</button></li>
            <template x-for="row in folders" :key="row.id">
              <li :style="`padding-left: ${row.depth}rem`">
                <button type="button" class="flex w-full justify-between gap-2 rounded-control px-2 py-1 text-left hover:bg-canvas" :class="folder === row.id && 'bg-accent-soft font-medium'" x-on:click="setFolder(row.id)" :data-folder="row.id">
                  <span class="min-w-0 truncate" x-text="row.name"></span><span class="text-ink-muted" x-text="row.count"></span>
                </button>
              </li>
            </template>
            <li class="border-t border-line pt-1"><button type="button" class="w-full rounded-control px-2 py-1 text-left hover:bg-canvas" :class="archived && 'bg-accent-soft font-medium'" x-on:click="setArchived(! archived)" data-archive-toggle>Archiwum</button></li>
          </ul>

          @if ($config['canManage'])
            <form class="mt-3 flex gap-2 border-t border-line pt-3" x-on:submit.prevent="addFolder()">
              <label for="f-folder-name" class="sr-only">Nazwa nowego folderu</label>
              <input type="text" id="f-folder-name" x-model="folderName" maxlength="100" :placeholder="folder ? 'Nowy podfolder' : 'Nowy folder'" class="block w-full min-w-0 rounded-control border-line-strong px-2 py-1 text-sm">
              <x-gst.button type="submit" variant="secondary">Dodaj</x-gst.button>
            </form>
            <div class="mt-2 flex gap-2 text-sm" x-show="folder">
              <x-gst.button variant="ghost" x-on:click="renameFolder()">Zmień nazwę</x-gst.button>
              <x-gst.button variant="ghost" class="text-danger" x-on:click="deleteFolder()">Usuń folder</x-gst.button>
            </div>
          @endif

          <div x-show="tags.length">
            <h2 class="mt-4 font-semibold">Tagi</h2>
            <div class="mt-2 flex flex-wrap gap-1">
              <template x-for="tag in tags" :key="tag.id">
                <button type="button" class="rounded-control border px-2 py-0.5 text-sm" :class="activeTags.includes(tag.id) ? 'border-accent bg-accent-soft' : 'border-line hover:bg-canvas'" x-on:click="toggleTag(tag.id)" :aria-pressed="activeTags.includes(tag.id)" x-text="tag.name"></button>
              </template>
            </div>
          </div>
        </section>
      </aside>

      {{-- Lista --}}
      <section class="min-w-0 rounded-panel border border-line bg-surface shadow-panel lg:col-span-3" aria-label="Grafiki">
        <div class="flex flex-wrap items-center gap-2 border-b border-line px-4 py-3">
          <form class="flex min-w-0 grow gap-2" role="search" x-on:submit.prevent="load(true)">
            <label for="f-library-q" class="sr-only">Szukaj grafiki</label>
            <input type="search" id="f-library-q" x-model="q" placeholder="Szukaj po nazwie" class="block w-full min-w-0 rounded-control border-line-strong px-3 py-2">
            <x-gst.button type="submit" variant="secondary">Szukaj</x-gst.button>
          </form>
          <div class="flex gap-1" role="group" aria-label="Widok listy">
            <button type="button" class="rounded-control px-3 py-2 text-sm" :class="view === 'grid' ? 'bg-accent-soft font-medium' : 'hover:bg-canvas'" x-on:click="view = 'grid'" :aria-pressed="view === 'grid'">Siatka</button>
            <button type="button" class="rounded-control px-3 py-2 text-sm" :class="view === 'list' ? 'bg-accent-soft font-medium' : 'hover:bg-canvas'" x-on:click="view = 'list'" :aria-pressed="view === 'list'">Lista</button>
          </div>
        </div>

        <div class="p-4">
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5" x-show="view === 'grid'" data-library-grid>
            <template x-for="item in items" :key="item.id">
              <button type="button" class="min-w-0 text-left" x-on:click="open(item)" :data-media="item.id">
                <span class="block aspect-square overflow-hidden rounded-control border border-line bg-canvas hover:border-line-strong">
                  <img :src="item.thumb" :alt="item.alt" loading="lazy" class="size-full object-cover">
                </span>
                <span class="mt-1 block truncate text-xs" x-text="item.name"></span>
              </button>
            </template>
          </div>

          <div class="relative overflow-x-auto" x-show="view === 'list'" x-cloak>
            <table class="w-full min-w-max text-left text-sm" data-library-list>
              <thead class="border-b border-line text-ink-soft">
                <tr>
                  <th class="py-2 pr-3 font-medium"><span class="sr-only">Miniatura</span></th>
                  <th class="px-3 py-2 font-medium">Nazwa</th>
                  <th class="px-3 py-2 font-medium">Wymiary</th>
                  <th class="px-3 py-2 font-medium">Format</th>
                  <th class="px-3 py-2 font-medium">Dodano</th>
                  <th class="py-2 pl-3 font-medium">Tekst alternatywny</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-line">
                <template x-for="item in items" :key="item.id">
                  <tr class="cursor-pointer hover:bg-canvas" x-on:click="open(item)">
                    <td class="py-2 pr-3"><img :src="item.thumb" alt="" loading="lazy" class="size-10 rounded-control object-cover"></td>
                    <td class="px-3 py-2"><button type="button" class="font-medium text-left hover:underline" x-text="item.name"></button></td>
                    <td class="px-3 py-2 text-ink-soft" x-text="item.width && item.height ? `${item.width} × ${item.height}` : '—'"></td>
                    <td class="px-3 py-2 text-ink-soft" x-text="item.mime.replace('image/', '').toUpperCase()"></td>
                    <td class="px-3 py-2 text-ink-soft" x-text="item.date"></td>
                    <td class="py-2 pl-3 text-ink-soft" x-text="item.alt || 'Brak'"></td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>

          <p class="py-8 text-center text-ink-muted" x-show="! loading && ! items.length" x-text="archived ? 'Archiwum jest puste.' : 'Brak grafik dla tych filtrów.'"></p>
          <p class="py-3 text-center text-sm text-ink-muted" x-show="loading">Wczytywanie…</p>
          <div class="mt-4 text-center" x-show="more && ! loading"><x-gst.button variant="secondary" x-on:click="load()">Pokaż więcej</x-gst.button></div>
        </div>
      </section>
    </div>

    {{-- Podgląd i opis grafiki --}}
    <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-4 py-10" x-show="detail" x-cloak role="dialog" aria-modal="true" aria-labelledby="gst-media-title" x-on:keydown.escape.window="close()" data-media-detail>
      <div class="fixed inset-0 bg-ink/50" x-on:click="close()"></div>
      <template x-if="detail">
        <div class="relative grid w-full max-w-4xl gap-6 rounded-panel bg-surface p-6 shadow-overlay md:grid-cols-2">
          <div class="min-w-0">
            <img :src="detail.url" :alt="detail.alt" class="w-full rounded-control bg-canvas object-contain">
            <dl class="mt-3 space-y-1 text-sm text-ink-soft">
              <div class="flex justify-between gap-3"><dt>Wymiary</dt><dd x-text="detail.width && detail.height ? `${detail.width} × ${detail.height}` : '—'"></dd></div>
              <div class="flex justify-between gap-3"><dt>Rozmiar</dt><dd x-text="bytes(detail.size)"></dd></div>
              <div class="flex justify-between gap-3"><dt>Format</dt><dd x-text="detail.mime"></dd></div>
            </dl>
            <p class="mt-3 text-sm text-ink-soft" data-media-usage x-text="detail.used ? `Tej grafiki ${detail.summary}.` : 'Grafika nie jest nigdzie używana.'"></p>
          </div>

          <form class="min-w-0 space-y-3" x-on:submit.prevent="save()">
            <div class="flex items-center justify-between gap-3">
              <h2 id="gst-media-title" class="text-lg font-semibold">Opis grafiki</h2>
              <button type="button" class="rounded-control p-1.5 text-ink-muted hover:bg-canvas hover:text-ink" x-on:click="close()"><x-gst.icon name="close" /><span class="sr-only">Zamknij</span></button>
            </div>
            <fieldset class="space-y-3" :disabled="! detail.can_edit">
              <label class="block"><span class="text-sm font-medium">Nazwa</span>
                <input type="text" x-model="form.title" maxlength="190" required class="mt-1 block w-full rounded-control border-line-strong px-3 py-2">
                <span class="text-xs text-danger" x-text="errors.title"></span></label>
              <label class="block"><span class="text-sm font-medium">Tekst alternatywny</span>
                <input type="text" x-model="form.alt" maxlength="255" class="mt-1 block w-full rounded-control border-line-strong px-3 py-2">
                <span class="text-xs text-danger" x-text="errors.alt"></span></label>
              <label class="block"><span class="text-sm font-medium">Folder</span>
                <select x-model="form.folder" class="mt-1 block w-full rounded-control border-line-strong px-3 py-2">
                  <option value="">Bez folderu</option>
                  <template x-for="row in folders" :key="row.id"><option :value="row.id" :selected="Number(form.folder) === row.id" x-text="`${'— '.repeat(row.depth)}${row.name}`"></option></template>
                </select>
                <span class="text-xs text-danger" x-text="errors.folder"></span></label>
              <label class="block"><span class="text-sm font-medium">Tagi</span>
                <input type="text" x-model="form.tags" placeholder="Rozdziel przecinkami" class="mt-1 block w-full rounded-control border-line-strong px-3 py-2">
                <span class="text-xs text-danger" x-text="errors.tags"></span></label>
              <label class="block"><span class="text-sm font-medium">Autor (opcjonalnie)</span>
                <input type="text" x-model="form.author" maxlength="190" class="mt-1 block w-full rounded-control border-line-strong px-3 py-2">
                <span class="text-xs text-danger" x-text="errors.author"></span></label>
              <label class="block"><span class="text-sm font-medium">Licencja (opcjonalnie)</span>
                <select x-model="form.license" class="mt-1 block w-full rounded-control border-line-strong px-3 py-2">
                  <option value="">Nie podano</option>
                  <template x-for="(label, key) in licenses" :key="key"><option :value="key" :selected="form.license === key" x-text="label"></option></template>
                </select>
                <span class="text-xs text-danger" x-text="errors.license"></span></label>
              <label class="block"><span class="text-sm font-medium">Źródło (opcjonalnie)</span>
                <input type="url" x-model="form.source_url" maxlength="255" class="mt-1 block w-full rounded-control border-line-strong px-3 py-2">
                <span class="text-xs text-danger" x-text="errors.source_url"></span></label>
            </fieldset>
            <p class="text-sm text-ink-soft" x-show="message" x-text="message" role="status"></p>
            <p class="text-sm text-ink-muted" x-show="! detail.can_edit">Opis może zmienić osoba, która przesłała plik, albo manager.</p>
            <div class="flex flex-wrap justify-between gap-2" x-show="detail.can_edit">
              <x-gst.button type="submit" x-bind:disabled="saving" data-media-save>Zapisz</x-gst.button>
              <template x-if="detail.archived"><x-gst.button variant="secondary" x-on:click="restore()" data-media-restore>Przywróć do biblioteki</x-gst.button></template>
              <template x-if="! detail.archived">
                <x-gst.button variant="ghost" class="text-danger" x-on:click="remove()" x-bind:disabled="saving" data-media-remove>
                  <span x-text="confirming ? (detail.used ? 'Potwierdź: przenieś do archiwum' : 'Potwierdź: usuń na stałe') : (detail.used ? 'Przenieś do archiwum' : 'Usuń')"></span>
                </x-gst.button>
              </template>
            </div>
          </form>
        </div>
      </template>
    </div>
  </div>
@endsection
