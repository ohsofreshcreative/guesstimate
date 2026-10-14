@extends('guesstimate.wizard.layout')

@php
  $errors ??= [];
  $hasOld = isset($old);
  $f = fn ($key) => $hasOld ? data_get($old, $key) : data_get($form, $key);
  $workRows = $hasOld ? array_values((array) ($old['work_items'] ?? [])) : $workItems;
  $costRows = $hasOld ? array_values((array) ($old['costs'] ?? [])) : $costs;
  $levels = ['low' => 'Low Risk', 'mid' => 'Mid Risk', 'high' => 'High Risk'];
  $advancedErrors = collect($errors)->keys()->contains(fn ($k) => str_starts_with($k, 'rates.') || str_starts_with($k, 'split') || str_starts_with($k, 'page_hours') || in_array($k, ['workshops', 'type_units'], true));
@endphp

@section('step')
  <form method="post"
    action="{{ route('gst.offers.wizard.save', ['offer' => $offer, 'step' => 4]) }}"
    x-data="calcPreview(@js(route('gst.offers.preview', $offer)))"
    x-on:input.debounce.400ms="schedule()"
    x-on:change="schedule()"
    x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = '' }"
    novalidate>
    <x-gst.csrf />

    <section aria-labelledby="gst-preview">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <h2 id="gst-preview" class="text-lg font-semibold">Warianty cenowe — {{ $typeLabel }}</h2>
        <p class="text-sm text-ink-muted" aria-live="polite">
          <span x-show="loading" x-cloak>Przeliczanie…</span>
          <span x-show="failed" x-cloak class="text-danger">Nie udało się przeliczyć podglądu. Zapisz krok, aby zobaczyć wynik.</span>
          <span x-show="! loading && ! failed && dirty" x-cloak>Podgląd — zapisz, aby zachować zmiany</span>
        </p>
      </div>
      {{-- Gdy wycena chwilowo się nie liczy, zachowujemy wybrany wariant (zaznaczone radio ma pierwszeństwo). --}}
      <input type="hidden" name="selected_variant" value="{{ $f('selected_variant') }}">
      <div x-ref="preview">
        @include('guesstimate.wizard.partials.budget-preview', ['summary' => $summary['result'], 'error' => $summary['error']])
      </div>
      @if (! empty($errors['selected_variant']))<p class="mt-2 text-sm text-danger">{{ $errors['selected_variant'] }}</p>@endif
    </section>

    <section class="mt-6 rounded-panel border border-line bg-surface shadow-panel" aria-labelledby="gst-work"
      x-data="rowList(@js($workRows), @js(['name' => '', 'hours_min' => '', 'hours_max' => '', 'notes' => '']))">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3">
        <div>
          <h2 id="gst-work" class="font-semibold">Prace dodatkowe</h2>
          <p class="text-sm text-ink-muted">Godziny doliczane do wyceny, np. migracja treści, copywriting, szkolenie.</p>
        </div>
        <x-gst.button icon="plus" variant="secondary" x-on:click="add()">Dodaj pracę</x-gst.button>
      </div>
      <template x-if="rows.length === 0"><p class="px-5 py-5 text-ink-muted">Brak prac dodatkowych.</p></template>
      <ul class="divide-y divide-line">
        <template x-for="(row, index) in rows" :key="index">
          <li data-row class="flex flex-wrap items-start gap-3 px-5 py-3">
            <input type="text" data-first :name="`work_items[${index}][name]`" x-model="row.name" maxlength="190" placeholder="Nazwa pracy" class="min-w-48 flex-1 rounded-control border-line-strong px-3 py-1.5" aria-label="Nazwa pracy">
            <div class="flex items-center gap-1.5">
              <input type="text" inputmode="decimal" :name="`work_items[${index}][hours_min]`" x-model="row.hours_min" class="w-20 rounded-control border-line-strong px-2 py-1.5" aria-label="Godziny minimum">
              <span class="text-ink-muted">–</span>
              <input type="text" inputmode="decimal" :name="`work_items[${index}][hours_max]`" x-model="row.hours_max" class="w-20 rounded-control border-line-strong px-2 py-1.5" aria-label="Godziny maksimum">
              <span class="text-sm text-ink-muted">h</span>
            </div>
            <input type="hidden" :name="`work_items[${index}][stage_key]`" value="other">
            <input type="text" :name="`work_items[${index}][notes]`" x-model="row.notes" placeholder="Uwagi" class="min-w-40 flex-1 rounded-control border-line-strong px-3 py-1.5" aria-label="Uwagi">
            <button type="button" class="rounded-control px-2 py-1.5 text-sm text-danger hover:bg-danger-soft" x-on:click="remove(index); $nextTick(() => $root.closest('form').dispatchEvent(new Event('change')))">Usuń</button>
            <template x-for="message in [@js($errors)[`work_items.${index}.name`], @js($errors)[`work_items.${index}.hours_min`], @js($errors)[`work_items.${index}.hours_max`]].filter(Boolean)">
              <p class="w-full text-sm text-danger" x-text="message"></p>
            </template>
          </li>
        </template>
      </ul>
    </section>

    <section class="mt-6 rounded-panel border border-line bg-surface shadow-panel" aria-labelledby="gst-costs"
      x-data="rowList(@js($costRows), @js(['name' => '', 'type' => 'license', 'amount' => '', 'quantity' => '1', 'billing' => 'one_time', 'notes' => '']))">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3">
        <div>
          <h2 id="gst-costs" class="font-semibold">Koszty zewnętrzne</h2>
          <p class="text-sm text-ink-muted">Licencje, płatne wtyczki, usługi, hosting. Są pokazywane osobno — poza ceną realizacji i bez podziału na etapy.</p>
        </div>
        <x-gst.button icon="plus" variant="secondary" x-on:click="add()">Dodaj koszt</x-gst.button>
      </div>
      <template x-if="rows.length === 0"><p class="px-5 py-5 text-ink-muted">Brak kosztów zewnętrznych.</p></template>
      <ul class="divide-y divide-line">
        <template x-for="(row, index) in rows" :key="index">
          <li data-row class="flex flex-wrap items-start gap-3 px-5 py-3">
            <input type="text" data-first :name="`costs[${index}][name]`" x-model="row.name" maxlength="190" placeholder="Nazwa, np. ACF Pro" class="min-w-48 flex-1 rounded-control border-line-strong px-3 py-1.5" aria-label="Nazwa kosztu">
            <select :name="`costs[${index}][type]`" x-model="row.type" class="rounded-control border-line-strong py-1.5 pl-3 pr-8" aria-label="Rodzaj">
              @foreach ($costTypes as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
              @endforeach
            </select>
            <div class="relative">
              <input type="text" inputmode="decimal" :name="`costs[${index}][amount]`" x-model="row.amount" placeholder="Kwota netto" class="w-32 rounded-control border-line-strong py-1.5 pl-2 pr-8" aria-label="Kwota netto za sztukę">
              <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-ink-muted">{{ $version->currency === 'PLN' ? 'zł' : $version->currency }}</span>
            </div>
            <input type="text" inputmode="decimal" :name="`costs[${index}][quantity]`" x-model="row.quantity" class="w-16 rounded-control border-line-strong px-2 py-1.5" aria-label="Ilość" title="Ilość">
            <select :name="`costs[${index}][billing]`" x-model="row.billing" class="rounded-control border-line-strong py-1.5 pl-3 pr-8" aria-label="Rozliczenie">
              @foreach ($billing as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
              @endforeach
            </select>
            <input type="hidden" :name="`costs[${index}][notes]`" :value="row.notes">
            <button type="button" class="rounded-control px-2 py-1.5 text-sm text-danger hover:bg-danger-soft" x-on:click="remove(index); $nextTick(() => $root.closest('form').dispatchEvent(new Event('change')))">Usuń</button>
            <template x-for="message in [@js($errors)[`costs.${index}.name`], @js($errors)[`costs.${index}.amount`], @js($errors)[`costs.${index}.quantity`]].filter(Boolean)">
              <p class="w-full text-sm text-danger" x-text="message"></p>
            </template>
          </li>
        </template>
      </ul>
    </section>

    <details class="group mt-6 rounded-panel border border-line bg-surface shadow-panel" @if ($advancedErrors) open @endif>
      <summary class="cursor-pointer list-none px-5 py-4 font-semibold marker:hidden">
        <span class="text-ink-soft group-open:hidden">+</span><span class="hidden text-ink-soft group-open:inline">−</span>
        Ustawienia zaawansowane <span class="font-normal text-ink-muted">— stawki, warsztaty, podział i godziny na podstronę tej oferty</span>
      </summary>
      <div class="space-y-6 border-t border-line p-5">
        <div>
          <h3 class="font-medium">Stawki godzinowe tej oferty</h3>
          <p class="mt-1 text-sm text-ink-muted">Zmiana dotyczy tylko tej oferty. Przy tworzeniu: Low {{ $globalRates['low'] ?? '—' }}, Mid {{ $globalRates['mid'] ?? '—' }}, High {{ $globalRates['high'] ?? '—' }}. Kolejność Low ≤ Mid ≤ High jest wymagana.</p>
          <div class="mt-3 grid gap-4 sm:grid-cols-3">
            @foreach ($levels as $level => $label)
              <x-gst.field :label="$label" :for="'f-rate-' . $level" :error="$errors['rates.' . $level] ?? null">
                <x-gst.input :name="'rates[' . $level . ']'" :id="'f-rate-' . $level" :value="$f('rates.' . $level)" :error="$errors['rates.' . $level] ?? null" suffix="zł/h" inputmode="decimal" />
              </x-gst.field>
            @endforeach
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <x-gst.field label="Warsztaty Discovery" for="f-workshops" :error="$errors['workshops'] ?? null" hint="Kwota stała w cenie (0 = bez warsztatów).">
            <x-gst.input name="workshops" id="f-workshops" :value="$f('workshops')" :error="$errors['workshops'] ?? null" suffix="zł" inputmode="decimal" />
          </x-gst.field>
          @foreach (['ux' => 'Udział UX', 'ui' => 'Udział UI', 'dev' => 'Udział Development'] as $key => $label)
            <x-gst.field :label="$label" :for="'f-split-' . $key" :error="$errors['split.' . $key] ?? null">
              <x-gst.input :name="'split[' . $key . ']'" :id="'f-split-' . $key" :value="$f('split.' . $key)" :error="$errors['split.' . $key] ?? null" suffix="%" inputmode="decimal" />
            </x-gst.field>
          @endforeach
        </div>
        @if (! empty($errors['split']))<p class="text-sm text-danger">{{ $errors['split'] }}</p>@endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
          @foreach (['ui_min' => 'UI min / podstronę', 'ui_max' => 'UI max / podstronę', 'dev_min' => 'Dev min / podstronę', 'dev_max' => 'Dev max / podstronę'] as $key => $label)
            <x-gst.field :label="$label" :for="'f-ph-' . $key" :error="$errors['page_hours.' . $key] ?? null">
              <x-gst.input :name="'page_hours[' . $key . ']'" :id="'f-ph-' . $key" :value="$f('page_hours.' . $key)" :error="$errors['page_hours.' . $key] ?? null" suffix="h" inputmode="decimal" />
            </x-gst.field>
          @endforeach
          <x-gst.field label="Jednostki rodzaju projektu" for="f-type-units" :error="$errors['type_units'] ?? null" :hint="$typeLabel">
            <x-gst.input name="type_units" id="f-type-units" :value="$f('type_units')" :error="$errors['type_units'] ?? null" inputmode="decimal" />
          </x-gst.field>
        </div>
      </div>
    </details>

    @include('guesstimate.wizard.partials.actions')
  </form>
@endsection
