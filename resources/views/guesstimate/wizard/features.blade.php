@extends('guesstimate.wizard.layout')

@php
  $errors ??= [];
  $initialRows = isset($old) ? array_values((array) ($old['features'] ?? [])) : $rows;
  $blank = ['feature_id' => null, 'category_name' => null, 'name' => '', 'description' => '', 'notes' => '', 'hours_min' => '', 'hours_max' => '', 'stage_key' => 'dev', 'save_to_library' => false, 'library_category_id' => ''];
@endphp

@section('step')
  <form method="post"
    action="{{ route('gst.offers.wizard.save', ['offer' => $offer, 'step' => 3]) }}"
    x-data="featurePicker(@js($initialRows), @js($blank), @js($aiUrl))"
    x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = '' }">
    <x-gst.csrf />

    <section class="rounded-panel border border-line bg-surface shadow-panel" aria-labelledby="gst-library">
      <div class="border-b border-line px-5 py-4">
        <h2 id="gst-library" class="text-lg font-semibold">Biblioteka funkcjonalności</h2>
        <p class="mt-1 text-sm text-ink-muted">Zaznacz, co wchodzi w zakres. Godziny możesz potem zmienić tylko dla tej oferty.</p>
      </div>
      @forelse ($library as $category)
        <div class="border-b border-line px-5 py-4 last:border-b-0">
          <h3 class="mb-2 text-sm font-medium text-ink-soft">{{ $category['name'] }}</h3>
          <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($category['features'] as $item)
              <label class="flex cursor-pointer items-start gap-3 rounded-control border border-line px-3 py-2.5 hover:bg-canvas" :class="has(@js($item['feature_id'])) && 'border-accent bg-accent-soft'">
                <input type="checkbox" class="mt-0.5 size-4 rounded border-line-strong text-accent focus:ring-accent" :checked="has(@js($item['feature_id']))" x-on:change="toggle(@js($item))">
                <span class="min-w-0">
                  <span class="block font-medium">{{ $item['name'] }}</span>
                  <span class="block text-sm text-ink-muted">{{ $item['hours_min'] === $item['hours_max'] ? $item['hours_min'] : $item['hours_min'] . '–' . $item['hours_max'] }} h</span>
                </span>
              </label>
            @endforeach
          </div>
        </div>
      @empty
        <p class="px-5 py-6 text-ink-muted">Biblioteka jest pusta. Administrator może ją uzupełnić w module Funkcjonalności.</p>
      @endforelse
    </section>

    <section class="mt-6 rounded-panel border border-line bg-surface p-5 shadow-panel" aria-labelledby="gst-quick">
      <h2 id="gst-quick" class="text-lg font-semibold">Własna funkcjonalność</h2>
      <p class="mt-1 text-sm text-ink-muted">Szybko dodaj pozycję spoza biblioteki. Nie znasz godzin? Opisz ją i oszacuj z AI.</p>
      <div class="mt-4 grid gap-3 md:grid-cols-4">
        <div class="md:col-span-2">
          <label for="f-quick-name" class="mb-1.5 block font-medium">Nazwa</label>
          <input type="text" id="f-quick-name" x-model="quick.name" maxlength="190" placeholder="np. Integracja z Zjedz.my" class="block w-full rounded-control border-line-strong px-3 py-2" x-on:keydown.enter.prevent="addQuick()">
        </div>
        <div class="flex items-end gap-1.5 md:col-span-2">
          <div>
            <label for="f-quick-min" class="mb-1.5 block font-medium">Godziny</label>
            <input type="text" id="f-quick-min" inputmode="decimal" x-model="quick.hours_min" placeholder="min" class="w-20 rounded-control border-line-strong px-2 py-2" aria-label="Godziny minimum">
          </div>
          <span class="pb-2 text-ink-muted">–</span>
          <input type="text" inputmode="decimal" x-model="quick.hours_max" placeholder="max" class="w-20 rounded-control border-line-strong px-2 py-2" aria-label="Godziny maksimum">
          <span class="pb-2 text-sm text-ink-muted">h</span>
        </div>
        <div class="md:col-span-4">
          <label for="f-quick-desc" class="mb-1.5 block font-medium">Opis <span class="font-normal text-ink-muted">(opcjonalnie)</span></label>
          <textarea id="f-quick-desc" x-model="quick.description" rows="2" class="block w-full rounded-control border-line-strong px-3 py-2" placeholder="Co ma robić, z czym się łączy, kto z niej korzysta…"></textarea>
        </div>
      </div>
      <div class="mt-3 flex flex-wrap justify-end gap-2">
        @if ($ai['enabled'])
          <x-gst.button variant="secondary" x-on:click="openAi()" ::disabled="! quick.name.trim() && ! quick.description.trim()">Oszacuj z AI</x-gst.button>
        @endif
        <x-gst.button icon="plus" x-on:click="addQuick()" ::disabled="! quick.name.trim()">Dodaj do oferty</x-gst.button>
      </div>
      @unless ($ai['enabled'])
        <p class="mt-2 text-sm text-ink-muted">{{ $ai['message'] }}</p>
      @endunless
    </section>

    <section class="mt-6 rounded-panel border border-line bg-surface shadow-panel" aria-labelledby="gst-selected">
      <div class="border-b border-line px-5 py-4">
        <h2 id="gst-selected" class="text-lg font-semibold">W ofercie <span class="font-normal text-ink-muted" x-text="`(${rows.length})`"></span></h2>
      </div>

      <template x-if="rows.length === 0">
        <x-gst.empty-state icon="features" title="Brak funkcjonalności" text="Zaznacz pozycje z biblioteki albo dodaj własną." />
      </template>

      <ul class="divide-y divide-line">
        <template x-for="(row, index) in rows" :key="index">
          <li class="px-5 py-3" x-data="{ more: false }">
            <input type="hidden" :name="`features[${index}][feature_id]`" :value="row.feature_id ?? ''">
            <input type="hidden" :name="`features[${index}][category_name]`" :value="row.category_name ?? ''">
            <input type="hidden" :name="`features[${index}][stage_key]`" value="dev">
            <div class="flex flex-wrap items-center gap-3">
              <div class="min-w-48 flex-1">
                <label :for="`ft-name-${index}`" class="sr-only">Nazwa</label>
                <input type="text" :id="`ft-name-${index}`" :name="`features[${index}][name]`" x-model="row.name" maxlength="190"
                  class="block w-full rounded-control border-transparent px-2 py-1.5 font-medium hover:border-line focus:border-accent focus:ring-accent">
                <p class="px-2 text-sm text-ink-muted" x-text="row.feature_id ? (row.category_name || 'Biblioteka') : 'Własna pozycja'"></p>
              </div>
              <div class="flex items-center gap-1.5">
                <input type="text" inputmode="decimal" :name="`features[${index}][hours_min]`" x-model="row.hours_min" class="w-20 rounded-control border-line-strong px-2 py-1.5" aria-label="Godziny minimum">
                <span class="text-ink-muted">–</span>
                <input type="text" inputmode="decimal" :name="`features[${index}][hours_max]`" x-model="row.hours_max" class="w-20 rounded-control border-line-strong px-2 py-1.5" aria-label="Godziny maksimum">
                <span class="text-sm text-ink-muted">h</span>
              </div>
              <div class="flex items-center">
                @if ($ai['enabled'])
                  <button type="button" class="rounded-control px-2 py-1.5 text-sm text-accent hover:bg-accent-soft" x-show="! row.feature_id" x-on:click="openAi(index)">Oszacuj z AI</button>
                @endif
                <button type="button" class="rounded-control px-2 py-1.5 text-sm text-ink-soft hover:bg-canvas hover:text-ink" x-on:click="more = ! more" :aria-expanded="more">Szczegóły</button>
                <button type="button" class="rounded-control px-2 py-1.5 text-sm text-danger hover:bg-danger-soft" x-on:click="remove(index)">Usuń</button>
              </div>
            </div>
            <div x-show="more || row.description || row.notes" class="mt-2 grid gap-3 md:grid-cols-2">
              <textarea :name="`features[${index}][description]`" x-model="row.description" rows="2" placeholder="Opis (widoczny w podsumowaniu oferty)" class="block w-full rounded-control border-line-strong px-3 py-1.5" aria-label="Opis"></textarea>
              <textarea :name="`features[${index}][notes]`" x-model="row.notes" rows="2" placeholder="Uwagi wewnętrzne" class="block w-full rounded-control border-line-strong px-3 py-1.5" aria-label="Uwagi wewnętrzne"></textarea>
            </div>
            @if ($canSaveToLibrary)
              <template x-if="! row.feature_id">
                <div class="mt-2 flex flex-wrap items-center gap-3">
                  <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" value="1" :name="`features[${index}][save_to_library]`" x-model="row.save_to_library" class="size-4 rounded border-line-strong text-accent focus:ring-accent">
                    Zapisz też w bibliotece
                  </label>
                  <select x-show="row.save_to_library" :name="`features[${index}][library_category_id]`" x-model="row.library_category_id" class="rounded-control border-line-strong py-1.5 pl-3 pr-8 text-sm" aria-label="Kategoria w bibliotece">
                    <option value="">— kategoria —</option>
                    @foreach ($categories as $id => $name)
                      <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                  </select>
                </div>
              </template>
            @endif
            <template x-for="message in [@js($errors)[`features.${index}.name`], @js($errors)[`features.${index}.hours_min`], @js($errors)[`features.${index}.hours_max`], @js($errors)[`features.${index}.library_category_id`]].filter(Boolean)">
              <p class="mt-1 text-sm text-danger" x-text="message"></p>
            </template>
          </li>
        </template>
      </ul>
    </section>

    @if ($ai['enabled'])
      <x-gst.modal id="ai-estimate" title="Oszacuj z AI" size="xl">
        <div class="grid gap-5 lg:grid-cols-2">
          <div class="min-w-0 space-y-3">
            <div>
              <label for="f-ai-name" class="mb-1 block font-medium">Funkcjonalność</label>
              <input type="text" id="f-ai-name" x-model="ai.name" maxlength="190" class="block w-full rounded-control border-line-strong px-3 py-2">
            </div>
            <div>
              <label for="f-ai-desc" class="mb-1 block font-medium">Opis</label>
              <textarea id="f-ai-desc" x-model="ai.description" rows="4" class="block w-full rounded-control border-line-strong px-3 py-2" placeholder="Opisz działanie, integracje, użytkowników, znane ograniczenia."></textarea>
            </div>
            <div class="max-h-72 space-y-2 overflow-y-auto rounded-control border border-line bg-canvas/60 p-3" aria-live="polite">
              <p class="text-sm text-ink-muted" x-show="! ai.messages.length">AI przeanalizuje opis, wskaże braki i zada pytania. Odpowiedz w polu poniżej — estymacja się doprecyzuje.</p>
              <template x-for="(message, i) in ai.messages" :key="i">
                <p class="whitespace-pre-line rounded-control px-3 py-2 text-sm" :class="message.role === 'user' ? 'ml-6 bg-surface' : 'mr-6 bg-accent-soft'" x-text="message.content"></p>
              </template>
              <p class="text-sm text-ink-muted" x-show="ai.loading">AI analizuje…</p>
            </div>
            <div>
              <label for="f-ai-input" class="sr-only">Twoja odpowiedź</label>
              <textarea id="f-ai-input" x-model="ai.input" rows="2" class="block w-full rounded-control border-line-strong px-3 py-2" placeholder="Odpowiedz na pytania albo doprecyzuj zakres (opcjonalnie)" x-on:keydown.ctrl.enter="askAi()"></textarea>
            </div>
            <p class="text-sm text-danger" x-show="ai.error" x-text="ai.error"></p>
            <div class="flex justify-end">
              <x-gst.button variant="secondary" x-on:click="askAi()" ::disabled="ai.loading || (! ai.name.trim() && ! ai.description.trim())"><span x-text="ai.messages.length ? 'Wyślij' : 'Analizuj'"></span></x-gst.button>
            </div>
          </div>

          <div class="min-w-0 rounded-control border border-line p-4">
            <template x-if="! ai.result">
              <p class="text-ink-muted">Tu pojawi się proponowany zakres, widełki godzin, ryzyka i założenia.</p>
            </template>
            <template x-if="ai.result">
              <div class="space-y-3 text-sm">
                <div>
                  <p class="text-ink-muted">Proponowane widełki</p>
                  <p class="text-2xl font-semibold" x-text="ai.result.hours_min !== null ? `${ai.result.hours_min}–${ai.result.hours_max} h` : 'Za mało informacji'"></p>
                </div>
                <template x-for="[label, key] in [['Zakres prac', 'scope'], ['Czego brakuje', 'missing'], ['Pytania', 'questions'], ['Ryzyka', 'risks'], ['Założenia', 'assumptions']]" :key="key">
                  <div x-show="ai.result[key]?.length">
                    <p class="font-medium" x-text="label"></p>
                    <ul class="mt-1 list-disc space-y-0.5 pl-5 text-ink-soft"><template x-for="(item, i) in ai.result[key]" :key="i"><li x-text="item"></li></template></ul>
                  </div>
                </template>
                <p class="text-ink-muted">To propozycja AI na podstawie opisu — nie weryfikuje dokumentacji API ani dostępów. Sprawdź ją przed zastosowaniem.</p>
              </div>
            </template>
          </div>
        </div>
        <div class="mt-5 flex flex-wrap justify-end gap-2 border-t border-line pt-4">
          <x-gst.button variant="ghost" x-on:click="$dispatch('close-modal', 'ai-estimate')">Zamknij</x-gst.button>
          <x-gst.button x-on:click="applyAi()" ::disabled="! ai.result || ai.result.hours_min === null">Zastosuj estymację</x-gst.button>
        </div>
      </x-gst.modal>
    @endif

    @include('guesstimate.wizard.partials.actions')
  </form>
@endsection
