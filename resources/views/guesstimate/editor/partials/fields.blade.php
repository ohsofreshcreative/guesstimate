{{-- Układ pól strony (tylko arkusz A4): pola, wyrównanie, warstwy, siatka, historia. Przesuwanie odbywa się w podglądzie. --}}
<template x-if="editable && ! templateMode && layout.fields[selected]">
  <div class="{{ $section }} space-y-3" data-layout-panel>
    <div class="flex items-center justify-between gap-2">
      <h3 class="text-sm font-semibold">Układ pól</h3>
      <div class="flex gap-1">
        <button type="button" class="{{ $ghost }}" x-on:click="undo()" :disabled="! undoLabel" :title="undoLabel ? `Cofnij: ${undoLabel} (Ctrl+Z)` : 'Cofnij'" data-layout-undo>Cofnij</button>
        <button type="button" class="{{ $ghost }}" x-on:click="redo()" :disabled="! redoLabel" :title="redoLabel ? `Ponów: ${redoLabel} (Ctrl+Shift+Z)` : 'Ponów'" data-layout-redo>Ponów</button>
      </div>
    </div>

    <ul class="space-y-1 text-sm" aria-label="Pola strony">
      <template x-for="id in movableIds" :key="id">
        <li>
          <button type="button" class="flex w-full items-center justify-between gap-2 rounded-control px-2 py-1 text-left hover:bg-canvas" :class="field === id && 'bg-accent-soft font-medium'" x-on:click="select(selected, id, false)" :data-layout-field="id">
            <span class="min-w-0 truncate" x-text="fieldLabel(id)"></span>
            <span class="shrink-0 text-xs text-ink-muted" x-show="fieldItems.find((row) => row.id === id)?.constraints.locked">zablokowane</span>
          </button>
        </li>
      </template>
    </ul>

    <p class="text-xs text-ink-muted" x-show="! fieldActive">Kliknij pole w podglądzie albo na liście. Przeciągnij, aby przesunąć; dwuklik pozwala zaznaczyć tekst.</p>

    <template x-if="fieldActive">
      <div class="space-y-2">
        <div class="flex flex-wrap gap-1" role="group" aria-label="Wyrównanie do strony">
          <button type="button" class="{{ $secondary }}" x-on:click="alignField('left')">Lewa</button>
          <button type="button" class="{{ $secondary }}" x-on:click="alignField('center')">Środek</button>
          <button type="button" class="{{ $secondary }}" x-on:click="alignField('right')">Prawa</button>
          <button type="button" class="{{ $secondary }}" x-on:click="alignField('top')">Góra</button>
          <button type="button" class="{{ $secondary }}" x-on:click="alignField('middle')">Połowa</button>
          <button type="button" class="{{ $secondary }}" x-on:click="alignField('bottom')">Dół</button>
        </div>
        <div class="flex flex-wrap gap-1" role="group" aria-label="Wyrównanie tekstu">
          <button type="button" class="{{ $secondary }}" x-on:click="alignText('left')" :aria-pressed="fieldItem?.style.align === 'left'" data-text-align="left">Tekst do lewej</button>
          <button type="button" class="{{ $secondary }}" x-on:click="alignText('center')" :aria-pressed="fieldItem?.style.align === 'center'" data-text-align="center">Na środku</button>
          <button type="button" class="{{ $secondary }}" x-on:click="alignText('right')" :aria-pressed="fieldItem?.style.align === 'right'" data-text-align="right">Do prawej</button>
        </div>
        <div class="flex flex-wrap gap-1" role="group" aria-label="Kolejność warstw">
          <button type="button" class="{{ $secondary }}" x-on:click="layer('front')" data-layer="front">Na wierzch</button>
          <button type="button" class="{{ $secondary }}" x-on:click="layer('forward')" data-layer="forward">Wyżej</button>
          <button type="button" class="{{ $secondary }}" x-on:click="layer('backward')" data-layer="backward">Niżej</button>
          <button type="button" class="{{ $secondary }}" x-on:click="layer('back')" data-layer="back">Na spód</button>
        </div>
        <div class="flex flex-wrap gap-1">
          <button type="button" class="{{ $secondary }}" x-on:click="toggleLock()" :aria-pressed="!! fieldItem?.constraints.locked" data-layout-lock x-text="fieldItem?.constraints.locked ? 'Odblokuj' : 'Zablokuj'"></button>
          <button type="button" class="{{ $secondary }}" x-on:click="duplicateField()" :disabled="! canDuplicate" data-layout-duplicate>Duplikuj</button>
          <button type="button" class="{{ $secondary }} text-danger" x-show="fieldItem?.text !== undefined" x-on:click="removeCopy()" data-layout-remove>Usuń kopię</button>
        </div>
        <template x-if="fieldItem?.text !== undefined">
          <label class="block" :data-field="field">
            <span class="{{ $label }}">Tekst kopii</span>
            <textarea rows="3" maxlength="600" class="{{ $input }}" x-model="fieldItem.text"></textarea>
          </label>
        </template>
      </div>
    </template>

    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
      <label class="flex items-center gap-1.5"><input type="checkbox" class="size-4 rounded border-line-strong text-accent focus:ring-accent" x-model="snap" data-layout-snap>Przyciągaj do siatki</label>
      <label class="flex items-center gap-1.5"><input type="checkbox" class="size-4 rounded border-line-strong text-accent focus:ring-accent" x-model="grid" data-layout-grid>Pokaż siatkę</label>
      <button type="button" class="{{ $ghost }}" x-show="pageState.fields" x-on:click="resetLayout()" data-layout-reset>Przywróć układ strony</button>
    </div>

    <details class="text-sm" x-show="history.past.length">
      <summary class="cursor-pointer text-ink-soft">Historia zmian (<span x-text="history.past.length"></span>)</summary>
      <ol class="mt-1 list-decimal pl-5 text-ink-muted" data-layout-history>
        <template x-for="(entry, index) in [...history.past].reverse().slice(0, 10)" :key="index"><li x-text="entry.label"></li></template>
      </ol>
    </details>
  </div>
</template>
