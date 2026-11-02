{{-- Wybór zdjęcia strony z biblioteki (lub przesłanie nowego pliku). Ten sam plik można użyć w wielu ofertach. --}}
<div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-4 py-10" x-show="picker.open" x-cloak role="dialog" aria-modal="true" aria-labelledby="gst-picker-title" x-on:keydown.escape.window="picker.open = false">
  <div class="fixed inset-0 bg-ink/50" x-on:click="picker.open = false"></div>
  <div class="relative w-full max-w-4xl rounded-panel bg-surface shadow-overlay">
    <div class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-3">
      <h2 id="gst-picker-title" class="text-lg font-semibold">Zdjęcie strony — biblioteka</h2>
      <form class="ml-auto flex gap-2" x-on:submit.prevent="loadMedia(true)">
        <label for="f-picker-q" class="sr-only">Szukaj w bibliotece</label>
        <input type="search" id="f-picker-q" x-model="picker.q" placeholder="Szukaj…" class="{{ $input }} w-52">
        <template x-if="folders.length">
          <select x-model="picker.folder" x-on:change="loadMedia(true)" class="{{ $input }} w-44" aria-label="Folder" data-picker-folder>
            <option value="">Wszystkie foldery</option>
            <template x-for="row in folders" :key="row.id"><option :value="row.id" x-text="`${'— '.repeat(row.depth)}${row.name}`"></option></template>
          </select>
        </template>
        <button type="submit" class="{{ $secondary }}">Szukaj</button>
      </form>
      @if ($config['canUpload'])
        <label class="{{ $primary }} cursor-pointer">
          <span x-text="picker.uploading ? 'Przesyłanie…' : 'Prześlij nowe'"></span>
          <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" x-on:change="upload($event)" :disabled="picker.uploading" data-picker-upload>
        </label>
      @endif
      <button type="button" class="{{ $ghost }}" x-on:click="picker.open = false">Zamknij</button>
    </div>
    <div class="max-h-140 overflow-y-auto p-5">
      <p class="mb-3 text-sm text-danger" x-show="picker.error" x-text="picker.error"></p>
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-5">
        <template x-for="item in picker.items" :key="item.id">
          <button type="button" class="text-left" x-on:click="pickImage(item)" :data-media="item.id">
            <span class="block aspect-square overflow-hidden rounded-control border-2 bg-canvas" :class="pageState.image_id === item.id ? 'border-accent' : 'border-transparent hover:border-line-strong'">
              <img :src="item.thumb" alt="" loading="lazy" class="size-full object-cover">
            </span>
            <span class="mt-1 block truncate text-xs" x-text="item.name"></span>
          </button>
        </template>
      </div>
      <p class="py-6 text-center text-sm text-ink-muted" x-show="! picker.loading && ! picker.items.length">Brak obrazów w bibliotece.</p>
      <p class="py-3 text-center text-sm text-ink-muted" x-show="picker.loading">Wczytywanie…</p>
      <div class="mt-4 text-center" x-show="picker.more && ! picker.loading"><button type="button" class="{{ $secondary }}" x-on:click="loadMedia()">Pokaż więcej</button></div>
    </div>
  </div>
</div>
