{{-- Pakiety i punkty oferty support (Offers\SupportPackages). Używany w edytorze dokumentu i we wzorze w ustawieniach.
     $model — wyrażenie Alpine z danymi ({packages, points}); $parts — ['packages', 'points'] albo ['points'];
     komponent musi mieć metody supportMethods (support-packages.js) i supportError(key).
     Klasy pól: $input, $label, $secondary z widoku nadrzędnego. --}}
@php
  use App\Guesstimate\Offers\SupportPackages;
  $parts ??= ['packages', 'points'];
  $dots = ['orange' => 'bg-doc-orange', 'flame' => 'bg-doc-flame', 'magenta' => 'bg-doc-magenta', 'violet' => 'bg-doc-violet', 'indigo' => 'bg-doc-indigo'];
  $move = 'rounded-control px-1.5 text-ink-muted hover:bg-canvas disabled:opacity-30';
@endphp

<div class="space-y-5">
  @if (in_array('packages', $parts, true))
    <div class="space-y-2" data-field="packages">
      <p class="text-sm font-medium" x-text="`Pakiety (${ {{ $model }}.packages.length }/{{ SupportPackages::MAX_PACKAGES }})`"></p>
      <template x-for="(pack, index) in {{ $model }}.packages" :key="index">
        <div class="space-y-1.5 rounded-control border border-line p-2.5" data-support-package>
          <div class="flex items-center gap-1">
            <span class="size-3 shrink-0 rounded-full" :class="@js($dots)[pack.color]" aria-hidden="true"></span>
            <span class="text-xs font-medium text-ink-muted" x-text="`Pakiet ${index + 1}`"></span>
            <button type="button" class="ml-auto {{ $move }}" x-on:click="supportMovePackage({{ $model }}, index, -1)" :disabled="index === 0" aria-label="Przenieś w lewo">←</button>
            <button type="button" class="{{ $move }}" x-on:click="supportMovePackage({{ $model }}, index, 1)" :disabled="index === {{ $model }}.packages.length - 1" aria-label="Przenieś w prawo">→</button>
            <button type="button" class="rounded-control px-1.5 text-xs text-danger hover:bg-danger-soft" x-on:click="supportRemovePackage({{ $model }}, index)">Usuń</button>
          </div>
          <input type="text" class="{{ $input }}" x-model="pack.name" maxlength="60" placeholder="Nazwa, np. Bezpieczeństwo" aria-label="Nazwa pakietu">
          <p class="text-xs text-danger" x-text="supportError(`packages.${index}.name`)"></p>
          <select class="{{ $input }}" x-model="pack.color" aria-label="Kolor pakietu">
            @foreach (SupportPackages::COLORS as $color => $colorLabel)
              <option value="{{ $color }}">{{ $colorLabel }}</option>
            @endforeach
          </select>
          <textarea rows="4" class="{{ $input }}" x-model="pack.description" maxlength="600" placeholder="Opis pakietu" aria-label="Opis pakietu"></textarea>
          <div class="grid grid-cols-2 gap-1.5">
            <input type="text" class="{{ $input }}" x-model="pack.price" inputmode="decimal" placeholder="Cena w zł, np. 450" aria-label="Cena pakietu w złotych">
            <label class="flex items-center gap-1.5 text-xs text-ink-soft">co
              <input type="number" class="{{ $input }}" x-model.number="pack.months" min="1" max="{{ SupportPackages::MAX_MONTHS }}" step="1" aria-label="Okres rozliczenia w miesiącach">
              mies.
            </label>
          </div>
          <input type="text" class="{{ $input }}" x-model="pack.price_note" maxlength="60" placeholder="Dopisek pod ceną, np. netto/co 3 mies." aria-label="Dopisek pod ceną">
          <p class="text-xs text-ink-muted" x-show="Number(pack.months) > 1">Pod ceną pojawi się kwota miesięczna (cena ÷ okres).</p>
          <p class="text-xs text-danger" x-text="supportError(`packages.${index}.price`) || supportError(`packages.${index}.months`)"></p>
        </div>
      </template>
      <button type="button" class="{{ $secondary }}" x-on:click="supportAddPackage({{ $model }}, {{ SupportPackages::MAX_PACKAGES }})" :disabled="{{ $model }}.packages.length >= {{ SupportPackages::MAX_PACKAGES }}">+ Dodaj pakiet</button>
      <p class="text-xs text-danger" x-text="supportError('packages')"></p>
    </div>
  @endif

  @if (in_array('points', $parts, true))
    <div class="space-y-2" data-field="points">
      <p class="text-sm font-medium" x-text="`Punkty pakietów i legenda (${ {{ $model }}.points.length }/{{ SupportPackages::MAX_POINTS }})`"></p>
      <p class="text-xs text-ink-muted">Zaznacz pakiety, które zawierają punkt — w pozostałych będzie przekreślony. Tekst w pakiecie zastępuje nazwę tylko tam (np. „Gwarantowany czas reakcji (max. 4h)”). Opis trafia do legendy.</p>
      <template x-for="(point, index) in {{ $model }}.points" :key="index">
        <div class="space-y-1.5 rounded-control border border-line p-2.5" data-support-point>
          <div class="flex items-center gap-1">
            <span class="text-xs font-medium text-ink-muted" x-text="index + 1"></span>
            <button type="button" class="ml-auto {{ $move }}" x-on:click="supportMovePoint({{ $model }}, index, -1)" :disabled="index === 0" aria-label="Przenieś wyżej">↑</button>
            <button type="button" class="{{ $move }}" x-on:click="supportMovePoint({{ $model }}, index, 1)" :disabled="index === {{ $model }}.points.length - 1" aria-label="Przenieś niżej">↓</button>
            <button type="button" class="rounded-control px-1.5 text-xs text-danger hover:bg-danger-soft" x-on:click="supportRemovePoint({{ $model }}, index)">Usuń</button>
          </div>
          <input type="text" class="{{ $input }}" x-model="point.name" maxlength="120" placeholder="Nazwa punktu" aria-label="Nazwa punktu">
          <p class="text-xs text-danger" x-text="supportError(`points.${index}.name`)"></p>
          <template x-for="(cell, cellIndex) in point.cells" :key="cellIndex">
            <div class="flex items-center gap-2">
              <label class="flex w-28 shrink-0 items-center gap-1.5 text-xs">
                <input type="checkbox" class="size-4 rounded border-line-strong text-accent focus:ring-accent" x-model="cell.on">
                <span class="size-2.5 shrink-0 rounded-full" :class="@js($dots)[{{ $model }}.packages[cellIndex]?.color]" aria-hidden="true"></span>
                <span class="truncate" x-text="{{ $model }}.packages[cellIndex]?.name || `Pakiet ${cellIndex + 1}`"></span>
              </label>
              <input type="text" class="{{ $input }}" x-model="cell.label" maxlength="120" :placeholder="cell.on ? 'Tekst jak nazwa' : 'Przekreślony'" :aria-label="`Tekst w pakiecie ${cellIndex + 1}`">
            </div>
          </template>
          <textarea rows="2" class="{{ $input }}" x-model="point.description" maxlength="600" placeholder="Opis w legendzie (opcjonalnie)" aria-label="Opis w legendzie"></textarea>
          <label class="flex items-center gap-2 text-xs text-ink-soft">Kolumna legendy
            <select class="{{ $input }} flex-1" :value="point.group ?? ''" x-on:change="supportSetGroup(point, $event.target.value)">
              <option value="">Pierwszy pakiet z tym punktem</option>
              <template x-for="(pack, packIndex) in {{ $model }}.packages" :key="packIndex">
                <option :value="packIndex" :selected="point.group === packIndex" x-text="pack.name || `Pakiet ${packIndex + 1}`"></option>
              </template>
            </select>
          </label>
        </div>
      </template>
      <button type="button" class="{{ $secondary }}" x-on:click="supportAddPoint({{ $model }}, {{ SupportPackages::MAX_POINTS }})" :disabled="{{ $model }}.points.length >= {{ SupportPackages::MAX_POINTS }}">+ Dodaj punkt</button>
      <p class="text-xs text-danger" x-text="supportError('points')"></p>
    </div>
  @endif
</div>
