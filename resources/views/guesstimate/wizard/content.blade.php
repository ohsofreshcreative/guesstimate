@extends('guesstimate.wizard.layout')

@php
  $errors ??= [];
  $initial = isset($old) ? ['situation' => (string) ($old['situation'] ?? ''), 'blocks' => array_values((array) ($old['blocks'] ?? []))] : $content;
  $d = isset($old['document']) ? array_replace($document, (array) $old['document']) : $document;
  $includesText = is_array($d['price_includes']) ? implode("\n", $d['price_includes']) : (string) $d['price_includes'];
  $docErrors = collect($errors)->keys()->contains(fn ($k) => str_starts_with($k, 'document.'));
@endphp

@section('step')
  <form method="post"
    action="{{ route('gst.offers.wizard.save', ['offer' => $offer, 'step' => 5]) }}"
    x-data="offerContent(@js($initial), @js($aiUrl))"
    x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = '' }">
    <x-gst.csrf />
    <input type="hidden" name="situation" :value="situation">
    <template x-for="(block, index) in blocks" :key="'h' + index">
      <span>
        <input type="hidden" :name="`blocks[${index}][title]`" :value="block.title">
        <input type="hidden" :name="`blocks[${index}][html]`" :value="block.html">
      </span>
    </template>

    <section class="rounded-panel border border-line bg-surface p-5 shadow-panel" aria-labelledby="gst-ai">
      <h2 id="gst-ai" class="text-lg font-semibold">Generuj z AI</h2>
      @if ($ai['enabled'])
        <p class="mt-1 text-sm text-ink-muted">AI korzysta z danych oferty: firmy i strony klienta, rodzaju projektu, mapy strony, funkcjonalności i zakresu UX. Nie dostaje danych kontaktowych, cen ani notatek wewnętrznych. Nic nie zostanie zapisane bez Twojej decyzji.</p>
        <label for="f-ai-prompt" class="mb-1.5 mt-4 block font-medium">Wskazówki dla AI <span class="font-normal text-ink-muted">(opcjonalnie)</span></label>
        <textarea id="f-ai-prompt" x-model="prompt" rows="3" maxlength="3000" class="block w-full rounded-control border-line-strong px-3 py-2" placeholder="np. Ze spotkania: restauracja w Otwocku, chcą więcej zapytań o imprezy okolicznościowe, integracja z Zjedz.my, menu edytowane przez zespół."></textarea>
        <div class="mt-3 flex flex-wrap items-center justify-end gap-3">
          <p class="text-sm text-danger" x-show="error" x-text="error"></p>
          <x-gst.button x-on:click="run('generate_all')" ::disabled="busy !== null"><span x-text="busy === 'generate_all:all' ? 'Generuję…' : 'Generuj całą treść'"></span></x-gst.button>
        </div>
      @else
        <p class="mt-1 text-sm text-ink-muted">{{ $ai['message'] }}</p>
      @endif
    </section>

    <template x-if="proposal">
      <div class="mt-6 rounded-panel border-2 border-accent bg-surface p-5 shadow-panel" role="dialog" aria-label="Propozycja AI">
        <h2 class="font-semibold">AI przygotowało nową wersję: <span x-text="label(proposal.target)"></span></h2>
        <p class="mt-1 text-sm text-ink-muted">Obecna treść zostanie zastąpiona dopiero po potwierdzeniu.</p>
        <div class="gst-prose mt-4 max-h-96 overflow-y-auto rounded-control border border-line bg-canvas/50 p-4">
          <template x-if="proposal.target === 'all'">
            <div>
              <h3>Jak rozumiemy Twoją sytuację?</h3>
              <div x-html="proposal.result.situation"></div>
              <h3>Jak osiągnąć ten cel?</h3>
              <template x-for="(b, i) in proposal.result.blocks" :key="i"><div><p><strong x-text="b.title"></strong></p><div x-html="b.html"></div></div></template>
            </div>
          </template>
          <template x-if="proposal.target !== 'all'">
            <div><p x-show="proposal.result.title"><strong x-text="proposal.result.title"></strong></p><div x-html="proposal.result.html"></div></div>
          </template>
        </div>
        <div class="mt-4 flex flex-wrap justify-end gap-2">
          <x-gst.button variant="ghost" x-on:click="reject()">Zostaw obecną treść</x-gst.button>
          <x-gst.button x-on:click="accept()">Zastąp treść</x-gst.button>
        </div>
      </div>
    </template>

    <section class="mt-6 rounded-panel border border-line bg-surface p-5 shadow-panel" aria-labelledby="gst-situation">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 id="gst-situation" class="text-lg font-semibold">1. Jak rozumiemy Twoją sytuację?</h2>
        @if ($ai['enabled'])
          @include('guesstimate.wizard.partials.ai-actions', ['target' => 'situation'])
        @endif
      </div>
      <p class="mb-3 mt-1 text-sm text-ink-muted">Kim jest klient, z czym przychodzi, co wiemy po rozmowie i czego potrzebuje.</p>
      @include('guesstimate.partials.rich-text', ['model' => 'situation', 'label' => 'Jak rozumiemy Twoją sytuację?', 'id' => 'f-situation'])
      @if (! empty($errors['situation']))<p class="mt-1.5 text-sm text-danger">{{ $errors['situation'] }}</p>@endif
    </section>

    <section class="mt-6 rounded-panel border border-line bg-surface p-5 shadow-panel" aria-labelledby="gst-blocks">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 id="gst-blocks" class="text-lg font-semibold">2. Jak osiągnąć ten cel?</h2>
          <p class="mt-1 text-sm text-ink-muted">Od 3 do 5 bloków: nagłówek i opis rozwiązania.</p>
        </div>
        <x-gst.button icon="plus" variant="secondary" x-on:click="addBlock()" ::disabled="! canAdd">Dodaj blok</x-gst.button>
      </div>
      @if (! empty($errors['blocks']))<p class="mt-2 text-sm text-danger">{{ $errors['blocks'] }}</p>@endif

      <div class="mt-4 space-y-5">
        <template x-for="(block, index) in blocks" :key="index">
          <div class="rounded-control border border-line p-4">
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-sm font-medium text-ink-muted" x-text="`Blok ${index + 1}`"></span>
              <div class="ml-auto flex flex-wrap items-center gap-1">
                @if ($ai['enabled'])
                  <template x-for="[action, text] in [['regenerate', 'Napisz od nowa'], ['improve', 'Popraw'], ['shorten', 'Skróć'], ['expand', 'Rozwiń']]" :key="action">
                    <button type="button" class="rounded-control px-2 py-1 text-sm text-accent hover:bg-accent-soft disabled:opacity-40" x-on:click="run(action, `block:${index}`)" :disabled="busy !== null || (action !== 'regenerate' && ! hasContent(`block:${index}`))" x-text="busy === `${action}:block:${index}` ? '…' : text"></button>
                  </template>
                  <span class="mx-1 h-5 w-px bg-line" aria-hidden="true"></span>
                @endif
                <button type="button" class="rounded-control p-1.5 text-ink-muted hover:bg-canvas disabled:opacity-30" x-on:click="moveBlock(index, -1)" :disabled="index === 0" aria-label="Przenieś wyżej">↑</button>
                <button type="button" class="rounded-control p-1.5 text-ink-muted hover:bg-canvas disabled:opacity-30" x-on:click="moveBlock(index, 1)" :disabled="index === blocks.length - 1" aria-label="Przenieś niżej">↓</button>
                <button type="button" class="rounded-control px-2 py-1 text-sm text-danger hover:bg-danger-soft disabled:opacity-30" x-on:click="removeBlock(index)" :disabled="! canRemove">Usuń</button>
              </div>
            </div>
            <label :for="`f-block-title-${index}`" class="sr-only">Nagłówek bloku</label>
            <input type="text" :id="`f-block-title-${index}`" x-model="block.title" maxlength="190" placeholder="Nagłówek, np. Solidne zaplecze technologiczne – WordPress + Sage 11"
              class="mb-2 mt-2 block w-full rounded-control border-line-strong px-3 py-2 font-medium">
            <div x-data="richText()" x-modelable="html" x-model="block.html" class="min-w-0">
              @include('guesstimate.partials.rich-text-body', ['label' => 'Treść bloku'])
            </div>
          </div>
        </template>
      </div>
    </section>

    <details class="group mt-6 rounded-panel border border-line bg-surface shadow-panel" @if ($docErrors) open @endif>
      <summary class="cursor-pointer list-none px-5 py-4 marker:hidden">
        <span class="text-lg font-semibold"><span class="text-ink-soft group-open:hidden">+</span><span class="hidden text-ink-soft group-open:inline">−</span> Pozostałe strony dokumentu</span>
        <span class="block text-sm text-ink-muted">Okładka, „Cena zawiera”, usługi dodatkowe, realizacje, kolejne kroki i osoba kontaktowa. Domyślne treści pochodzą z ustawień — tu zmienisz je tylko dla tej oferty.</span>
      </summary>
      <div class="space-y-8 border-t border-line p-5">
        <div class="grid gap-4 md:grid-cols-2">
          <x-gst.field label="Nagłówek okładki" for="f-doc-heading" :error="$errors['document.cover_heading'] ?? null" class="md:col-span-2" hint="Słowo w **gwiazdkach** będzie pomarańczowe. Klient i data uzupełnią się automatycznie.">
            <x-gst.input name="document[cover_heading]" id="f-doc-heading" :value="$d['cover_heading']" maxlength="160" />
          </x-gst.field>
          <x-gst.field label="Krótki opis na okładce" for="f-doc-lead" class="md:col-span-2">
            <x-gst.textarea name="document[cover_lead]" id="f-doc-lead" :value="$d['cover_lead']" rows="2" />
          </x-gst.field>
        </div>

        <div>
          <x-gst.field label="Cena zawiera (jedna pozycja w wierszu)" for="f-doc-includes" :error="$errors['document.price_includes'] ?? null">
            <x-gst.textarea name="document[price_includes]" id="f-doc-includes" :value="$includesText" rows="6" />
          </x-gst.field>
          <div class="mt-2"><x-gst.checkbox name="document[include_features]" label="Dopisz funkcjonalności z oferty do listy „Cena zawiera”" :checked="! empty($d['include_features'])" /></div>
        </div>

        <div x-data="rowList(@js(array_values($d['extras'])), @js(['name' => '', 'description' => '', 'price' => '']))">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h3 class="font-semibold">Usługi dodatkowe</h3>
              <p class="text-sm text-ink-muted">Strona opcjonalna (włączasz ją w kroku 6). Ceny usług nie są doliczane do ceny projektu.</p>
            </div>
            <x-gst.button icon="plus" variant="secondary" x-on:click="add()">Dodaj usługę</x-gst.button>
          </div>
          <x-gst.field label="Wstęp" for="f-doc-extras-intro" class="mt-3">
            <x-gst.textarea name="document[extras_intro]" id="f-doc-extras-intro" :value="$d['extras_intro']" rows="2" />
          </x-gst.field>
          <div class="mt-3 space-y-3">
            <template x-for="(row, index) in rows" :key="index">
              <div data-row class="grid gap-2 rounded-control border border-line p-3 md:grid-cols-3">
                <input type="text" data-first :name="`document[extras][${index}][name]`" x-model="row.name" maxlength="120" placeholder="Nazwa usługi" class="rounded-control border-line-strong px-3 py-1.5" aria-label="Nazwa usługi">
                <input type="text" :name="`document[extras][${index}][price]`" x-model="row.price" maxlength="120" placeholder="Cena (tekst), np. 1 000 zł netto" class="rounded-control border-line-strong px-3 py-1.5" aria-label="Cena usługi">
                <button type="button" class="justify-self-end rounded-control px-2 py-1.5 text-sm text-danger hover:bg-danger-soft" x-on:click="remove(index)">Usuń</button>
                <textarea :name="`document[extras][${index}][description]`" x-model="row.description" rows="2" placeholder="Opis — każda linia to punkt" class="rounded-control border-line-strong px-3 py-1.5 md:col-span-3" aria-label="Opis usługi"></textarea>
              </div>
            </template>
          </div>
        </div>

        <div x-data="{ selected: @js(array_map('intval', $d['reference_ids'])), max: {{ $maxReferences }} }">
          <h3 class="font-semibold">Realizacje <span class="font-normal text-ink-muted" x-text="`(${selected.length}/${max})`"></span></h3>
          @if ($caseStudies)
            <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
              @foreach ($caseStudies as $case)
                <label class="flex cursor-pointer items-center gap-3 rounded-control border border-line p-2.5 hover:bg-canvas has-checked:border-accent has-checked:bg-accent-soft">
                  <input type="checkbox" name="document[reference_ids][]" value="{{ $case['id'] }}" x-model.number="selected" :disabled="! selected.includes({{ $case['id'] }}) && selected.length >= max" class="size-4 rounded border-line-strong text-accent focus:ring-accent">
                  @if ($case['thumb'])<img src="{{ $case['thumb'] }}" alt="" class="h-9 w-14 shrink-0 rounded object-cover">@endif
                  <span class="min-w-0"><span class="block truncate font-medium">{{ $case['name'] }}</span><span class="block truncate text-sm text-ink-muted">{{ $case['metrics'] ?: $case['url'] }}</span></span>
                </label>
              @endforeach
            </div>
          @else
            <p class="mt-2 text-sm text-ink-muted">Brak realizacji. Administrator doda je w Ustawienia → Realizacje.</p>
          @endif
          @if (! empty($errors['document.reference_ids']))<p class="mt-1 text-sm text-danger">{{ $errors['document.reference_ids'] }}</p>@endif
        </div>

        <div x-data="rowList(@js(array_values($d['next_steps'])), @js(['title' => '', 'note' => '']))">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="font-semibold">Kolejne kroki</h3>
            <x-gst.button icon="plus" variant="secondary" x-on:click="add()">Dodaj krok</x-gst.button>
          </div>
          <div class="mt-3 space-y-2">
            <template x-for="(row, index) in rows" :key="index">
              <div data-row class="flex flex-wrap gap-2">
                <span class="w-6 pt-2 text-right text-sm text-ink-muted" x-text="index + 1"></span>
                <input type="text" data-first :name="`document[next_steps][${index}][title]`" x-model="row.title" maxlength="140" placeholder="Krok" class="min-w-48 flex-1 rounded-control border-line-strong px-3 py-1.5" aria-label="Krok">
                <input type="text" :name="`document[next_steps][${index}][note]`" x-model="row.note" maxlength="140" placeholder="Dopisek, np. (1–3 dni)" class="w-56 rounded-control border-line-strong px-3 py-1.5" aria-label="Dopisek">
                <button type="button" class="rounded-control px-2 py-1.5 text-sm text-danger hover:bg-danger-soft" x-on:click="remove(index)">Usuń</button>
              </div>
            </template>
          </div>
          <x-gst.field label="Wyróżniona informacja pod krokami" for="f-doc-steps-note" class="mt-3" hint="**pomarańczowy fragment**">
            <x-gst.input name="document[next_steps_note]" id="f-doc-steps-note" :value="$d['next_steps_note']" />
          </x-gst.field>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <x-gst.field label="Osoba kontaktowa (strona „Kontakt”)" for="f-doc-contact" :error="$errors['document.contact_user_id'] ?? null" hint="Stanowisko i telefon ustawisz w Ustawienia → Użytkownicy.">
            <x-gst.select name="document[contact_user_id]" id="f-doc-contact" :options="$team" :value="$d['contact_user_id'] ?? ''" placeholder="Kontakt domyślny z ustawień" />
          </x-gst.field>
          <x-gst.field label="Wstęp do realizacji" for="f-doc-ref-intro" hint="**pogrubienie**">
            <x-gst.textarea name="document[references_intro]" id="f-doc-ref-intro" :value="$d['references_intro']" rows="3" />
          </x-gst.field>
        </div>
      </div>
    </details>

    @include('guesstimate.wizard.partials.actions')
  </form>
@endsection
