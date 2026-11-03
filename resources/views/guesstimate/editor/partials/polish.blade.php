{{-- Dopracowanie strony (Document\PagePolish). Domyślne wartości nie są zapisywane, więc strona wygląda jak w szablonie. --}}
<template x-if="pageType === 'cover'">
  <div class="{{ $section }}" data-polish-section>
    <h3 class="font-semibold">Wygląd strony</h3>
    <label class="mt-2 block text-sm">Położenie nagłówka
      <input type="range" min="-{{ \App\Guesstimate\Document\PagePolish::MAX_LIFT }}" max="{{ \App\Guesstimate\Document\PagePolish::MAX_LIFT }}" step="5" class="block w-full" :value="polishValue('heading_lift', 0)" x-on:input="setPolish('heading_lift', Number($event.target.value))" data-polish="heading_lift">
    </label>
    <p class="mt-1 text-xs text-ink-muted">Od lewej: niżej, środek strony (domyślnie), tuż pod logo. Mocno obniżony nagłówek może zachodzić na „oferta dla”. Nagłówek ustawiony ręcznie w „Układ pól” zostaje na swoim miejscu. Tylko arkusz A4.</p>
  </div>
</template>

<template x-if="pageType === 'pricing' || pageType === 'references'">
  <div class="{{ $section }} space-y-3" data-polish-section>
    <h3 class="font-semibold">Wygląd strony</h3>
    <label class="flex items-center gap-2 text-sm">
      <input type="checkbox" class="size-4 rounded border-line-strong text-accent focus:ring-accent" :checked="polishValue('hide_edge_label', false)" x-on:change="setPolish('hide_edge_label', $event.target.checked)" data-polish="hide_edge_label">
      Ukryj boczny napis
    </label>

    <template x-if="pageType === 'references'">
      <div class="space-y-3 text-sm">
        <label class="block">Wielkość tytułów realizacji
          <select class="{{ $input }}" :value="polishValue('title_size')" x-on:change="setPolish('title_size', $event.target.value)" data-polish="title_size">
            <option value="">Domyślna</option>
            <option value="large">Większa</option>
            <option value="xlarge">Duża</option>
          </select>
        </label>
        <label class="block">Proporcje grafik
          <select class="{{ $input }}" :value="polishValue('image_ratio')" x-on:change="setPolish('image_ratio', $event.target.value)" data-polish="image_ratio">
            <option value="">16:9 (domyślne)</option>
            <option value="standard">4:3</option>
            <option value="square">1:1</option>
          </select>
        </label>
        <label class="block">Dopasowanie grafik
          <select class="{{ $input }}" :value="polishValue('image_fit')" x-on:change="setPolish('image_fit', $event.target.value)" data-polish="image_fit">
            <option value="">Wypełnij ramkę</option>
            <option value="contain">Pokaż całą grafikę</option>
          </select>
        </label>

        <div class="space-y-2" x-show="state.document.reference_ids.length" data-reference-order>
          <p class="font-medium">Kolejność i kadr <span class="font-normal text-ink-muted">(plik pozostaje bez zmian)</span></p>
          <template x-for="(id, index) in state.document.reference_ids" :key="id">
            <div class="rounded-control border border-line p-2.5" :data-reference="id">
              <div class="flex items-center gap-1">
                <span class="min-w-0 truncate" x-text="caseStudy(id)?.name"></span>
                <button type="button" class="ml-auto rounded-control px-1.5 text-ink-muted hover:bg-canvas disabled:opacity-30" x-on:click="moveReference(index, -1)" :disabled="index === 0" aria-label="Przenieś wcześniej">↑</button>
                <button type="button" class="rounded-control px-1.5 text-ink-muted hover:bg-canvas disabled:opacity-30" x-on:click="moveReference(index, 1)" :disabled="index === state.document.reference_ids.length - 1" aria-label="Przenieś dalej">↓</button>
              </div>
              <template x-if="caseStudy(id)?.thumb && editable">
                <div class="mt-2 space-y-1">
                  <label class="block">W poziomie
                    <input type="range" min="0" max="100" step="1" class="block w-full" :value="referenceCrop(id, 'x')" x-on:input="setReferenceCrop(id, 'x', $event.target.value)">
                  </label>
                  <label class="block">W pionie
                    <input type="range" min="0" max="100" step="1" class="block w-full" :value="referenceCrop(id, 'y')" x-on:input="setReferenceCrop(id, 'y', $event.target.value)">
                  </label>
                  <label class="block">Powiększenie
                    <input type="range" min="1" max="4" step="0.05" class="block w-full" :value="referenceCrop(id, 'zoom')" x-on:input="setReferenceCrop(id, 'zoom', $event.target.value)">
                  </label>
                  <button type="button" class="{{ $ghost }}" x-show="pageState.polish?.image_crops?.[id]" x-on:click="resetReferenceCrop(id)">Przywróć pełny kadr</button>
                </div>
              </template>
            </div>
          </template>
        </div>
      </div>
    </template>
  </div>
</template>
