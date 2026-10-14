@extends('guesstimate.wizard.layout')

@php
  $errors ??= [];
  $initial = isset($old) ? (json_decode((string) ($old['payload'] ?? '[]'), true) ?: []) : $pages;
@endphp

@section('step')
  <form method="post"
    action="{{ route('gst.offers.wizard.save', ['offer' => $offer, 'step' => 2]) }}"
    x-data="pageList(@js($initial), @js($pageHours), @js($typeUnits))"
    x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = '' }">
    <x-gst.csrf />
    <input type="hidden" name="payload" :value="payload">

    <section class="rounded-panel border border-line bg-surface shadow-panel" aria-labelledby="gst-pages">
      <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4">
        <div>
          <h2 id="gst-pages" class="text-lg font-semibold">Mapa strony</h2>
          <p class="mt-1 text-sm text-ink-muted">Lista podstron. Każda podstrona liczy się jako jedna jednostka: UI {{ ($pageHours['ui_min'] ?? '') === ($pageHours['ui_max'] ?? '') ? $pageHours['ui_min'] : ($pageHours['ui_min'] . '–' . $pageHours['ui_max']) }} h + Dev {{ ($pageHours['dev_min'] ?? '') === ($pageHours['dev_max'] ?? '') ? $pageHours['dev_min'] : ($pageHours['dev_min'] . '–' . $pageHours['dev_max']) }} h (domyślne godziny tej oferty).</p>
        </div>
        <button type="button" class="rounded-control px-3 py-1.5 text-sm text-ink-soft hover:bg-canvas hover:text-ink" x-on:click="showBulk = ! showBulk">Wklej listę podstron</button>
      </div>

      <div x-show="showBulk" x-cloak class="border-b border-line bg-canvas/60 px-5 py-4">
        <label for="f-bulk" class="mb-1.5 block font-medium">Jedna podstrona w wierszu</label>
        <textarea id="f-bulk" x-model="bulk" rows="5" class="block w-full rounded-control border-line-strong px-3 py-2" placeholder="Strona główna&#10;O nas&#10;Oferta&#10;Kontakt"></textarea>
        <div class="mt-2 flex justify-end gap-2">
          <x-gst.button variant="ghost" x-on:click="showBulk = false; bulk = ''">Anuluj</x-gst.button>
          <x-gst.button variant="secondary" x-on:click="addBulk()">Dodaj do listy</x-gst.button>
        </div>
      </div>

      <template x-if="pages.length === 0">
        <x-gst.empty-state icon="offers" title="Brak podstron" text="Dodaj pierwszą podstronę poniżej albo wklej całą listę." />
      </template>

      <ol class="divide-y divide-line">
        <template x-for="(page, index) in pages" :key="index">
          <li class="px-5 py-2.5" draggable="true" x-on:dragstart="dragIndex = index" x-on:dragover.prevent x-on:drop.prevent="drop(index)" :class="dragIndex === index && 'opacity-50'">
            <div class="flex flex-wrap items-center gap-2">
              <span class="w-6 shrink-0 cursor-grab text-right text-sm text-ink-muted" x-text="index + 1" title="Przeciągnij, aby zmienić kolejność"></span>
              <label :for="`pg-${index}`" class="sr-only">Nazwa podstrony</label>
              <input type="text" :id="`pg-${index}`" data-page-name x-model="page.name" maxlength="190"
                class="min-w-40 flex-1 rounded-control border-transparent px-2 py-1.5 hover:border-line focus:border-accent focus:ring-accent"
                :class="! page.name.trim() && 'border-danger'">
              <span class="text-sm text-risk-mid" x-show="page.advanced && [page.hours_ui_min, page.hours_ui_max, page.hours_dev_min, page.hours_dev_max].some((v) => v !== '' && v !== null)">własne godziny</span>
              <div class="flex items-center">
                <button type="button" class="rounded-control p-1.5 text-ink-muted hover:bg-canvas hover:text-ink disabled:opacity-30" x-on:click="move(index, -1)" :disabled="index === 0" aria-label="Przenieś wyżej">↑</button>
                <button type="button" class="rounded-control p-1.5 text-ink-muted hover:bg-canvas hover:text-ink disabled:opacity-30" x-on:click="move(index, 1)" :disabled="index === pages.length - 1" aria-label="Przenieś niżej">↓</button>
                <button type="button" class="rounded-control px-2 py-1.5 text-sm text-ink-soft hover:bg-canvas hover:text-ink" x-on:click="duplicate(index)">Duplikuj</button>
                <button type="button" class="rounded-control px-2 py-1.5 text-sm text-ink-soft hover:bg-canvas hover:text-ink" x-on:click="page.advanced = ! page.advanced" :aria-expanded="page.advanced">Godziny</button>
                <button type="button" class="rounded-control px-2 py-1.5 text-sm text-danger hover:bg-danger-soft" x-on:click="remove(index)">Usuń</button>
              </div>
            </div>
            <div x-show="page.advanced" x-cloak class="ml-8 mt-2 grid gap-3 rounded-control bg-canvas/60 p-3 sm:grid-cols-2">
              <p class="text-sm text-ink-muted sm:col-span-2">Ustawienia zaawansowane — puste pole = domyślne godziny oferty.</p>
              @foreach (['ui' => 'UI', 'dev' => 'Dev'] as $part => $label)
                <div class="flex items-center gap-1.5">
                  <span class="w-10 text-sm font-medium">{{ $label }}</span>
                  <input type="text" inputmode="decimal" x-model="page.hours_{{ $part }}_min" :placeholder="defaults.{{ $part }}_min" class="w-20 rounded-control border-line-strong px-2 py-1.5" aria-label="{{ $label }} godziny minimum">
                  <span class="text-ink-muted">–</span>
                  <input type="text" inputmode="decimal" x-model="page.hours_{{ $part }}_max" :placeholder="defaults.{{ $part }}_max" class="w-20 rounded-control border-line-strong px-2 py-1.5" aria-label="{{ $label }} godziny maksimum">
                  <span class="text-sm text-ink-muted">h</span>
                </div>
              @endforeach
            </div>
          </li>
        </template>
      </ol>

      <div class="flex flex-wrap gap-2 border-t border-line px-5 py-3">
        <label for="f-new-page" class="sr-only">Nazwa nowej podstrony</label>
        <input type="text" id="f-new-page" x-model="newName" x-on:keydown.enter.prevent="add()" maxlength="190" placeholder="Nazwa podstrony, np. O nas" class="min-w-48 flex-1 rounded-control border-line-strong px-3 py-2">
        <x-gst.button icon="plus" variant="secondary" x-on:click="add()">Dodaj podstronę</x-gst.button>
      </div>
    </section>

    <section class="mt-6 rounded-panel border border-line bg-surface p-5 shadow-panel" aria-label="Podsumowanie mapy strony">
      <dl class="grid grid-cols-2 gap-4 md:grid-cols-5">
        <div><dt class="text-sm text-ink-muted">Podstrony z mapy</dt><dd class="text-2xl font-semibold" x-text="stats.pages"></dd></div>
        <div><dt class="text-sm text-ink-muted">Jednostki: {{ $typeLabel }}</dt><dd class="text-2xl font-semibold" x-text="stats.units"></dd></div>
        <div><dt class="text-sm text-ink-muted">Jednostek razem</dt><dd class="text-2xl font-semibold" x-text="stats.total"></dd></div>
        <div><dt class="text-sm text-ink-muted">Szacunek UI</dt><dd class="text-2xl font-semibold" x-text="stats.ui"></dd></div>
        <div><dt class="text-sm text-ink-muted">Szacunek Dev</dt><dd class="text-2xl font-semibold" x-text="stats.dev"></dd></div>
      </dl>
      <p class="mt-3 text-sm text-ink-muted">Jednostki rodzaju projektu są liczone osobno (nie są adresami URL). Godziny UX dochodzą z zakresu UX — nie są tu powtarzane.</p>
    </section>

    @include('guesstimate.wizard.partials.actions')
  </form>
@endsection
