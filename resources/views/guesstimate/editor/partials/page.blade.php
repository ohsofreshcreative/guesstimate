{{-- Ustawienia wybranej strony: teksty, układ, zdjęcie strony, tło, widoczność. Pola = istniejące pola dokumentu. --}}
@php
  $hl = "rounded-control ring-2 ring-accent/40 ring-offset-4 ring-offset-surface";
  $field = fn (string $name) => "data-field=\"{$name}\" :class=\"field === '{$name}' ? '{$hl}' : ''\"";
@endphp

<div class="{{ $section }}">
  <div class="flex items-start justify-between gap-3">
    <div>
      <p class="text-xs font-medium uppercase tracking-wide text-ink-muted" x-text="pageNumber(selected) ? `Strona ${pageNumber(selected)}` : 'Strona ukryta'"></p>
      <h2 class="text-lg font-semibold" x-text="page.label"></h2>
    </div>
    <template x-if="page.optional">
      <label class="mt-1 flex items-center gap-2 text-sm" data-field="enabled">
        <input type="checkbox" class="size-4 rounded border-line-strong text-accent focus:ring-accent" x-model="pageState.enabled">
        Pokaż w ofercie
      </label>
    </template>
  </div>
  <p class="mt-2 text-sm text-ink-muted" x-show="hiddenReason(selected) === 'empty'">Strona nie ma jeszcze treści — klient jej nie zobaczy, dopóki jej nie uzupełnisz.</p>
  <p class="mt-2 text-sm text-ink-muted" x-show="hiddenReason(selected) === 'disabled'">Strona jest wyłączona — klient jej nie zobaczy.</p>

  <template x-if="selected === 'situation'">
    <div>
      <div class="mt-3 rounded-control border border-danger/30 bg-danger-soft px-3 py-2.5 text-sm text-danger" x-show="overflow" role="alert" data-fit-warning>
        <p class="font-medium">Tekst nie mieści się na jednej stronie A4.</p>
        <p class="mt-1">Typografia jest już zmniejszona do minimum czytelności. Skróć tekst albo wybierz układ „Treść na pełną szerokość”. Oferty z przepełnioną stroną nie można opublikować.</p>
        @if ($config['ai']['enabled'])
          <button type="button" class="mt-2 rounded-control bg-danger px-3 py-1.5 font-medium text-white hover:opacity-90 disabled:opacity-50" x-on:click="fitWithAi()" :disabled="busy !== null || ! editable" data-ai-fit>
            <span x-text="busy === 'fit:situation' ? 'AI skraca tekst…' : 'Dopasuj tekst do strony z AI'"></span>
          </button>
        @endif
      </div>
      <p class="mt-2 text-xs text-ink-muted" x-show="! overflow && report.situation?.level > 0" x-text="`Tekst dopasowany do strony (zmniejszone odstępy${report.situation?.level >= 3 ? ' i tekst' : ''}).`"></p>
    </div>
  </template>
</div>

{{-- Teksty --}}
<div class="{{ $section }} space-y-4">
  <h3 class="font-semibold">Teksty</h3>

  <template x-if="selected !== 'cover'">
    <div {!! $field('heading') !!}>
      <label class="{{ $label }}" for="f-ed-heading">Nagłówek strony</label>
      <input type="text" id="f-ed-heading" class="{{ $input }}" x-model="pageState.heading" maxlength="160" :placeholder="(page.default_heading || '').replaceAll('**', '')">
      <p class="mt-1 text-xs text-ink-muted">Puste pole = nagłówek domyślny. **słowo** = pomarańczowe.</p>
      <p class="mt-1 text-xs text-danger" x-text="fieldError(`document.pages.${selected}.heading`)"></p>
    </div>
  </template>

  <template x-if="selected === 'cover'">
    <div class="space-y-4">
      <div {!! $field('cover_heading') !!}>
        <label class="{{ $label }}" for="f-ed-cover-heading">Nagłówek okładki</label>
        <input type="text" id="f-ed-cover-heading" class="{{ $input }}" x-model="state.document.cover_heading" maxlength="160">
        <p class="mt-1 text-xs text-ink-muted">**słowo** = pomarańczowe. Klient i data uzupełnią się automatycznie.</p>
        <p class="mt-1 text-xs text-danger" x-text="fieldError('document.cover_heading')"></p>
      </div>
      <div {!! $field('cover_lead') !!}>
        <label class="{{ $label }}" for="f-ed-cover-lead">Krótki opis</label>
        <textarea id="f-ed-cover-lead" rows="3" class="{{ $input }}" x-model="state.document.cover_lead" maxlength="400"></textarea>
        @include('guesstimate.editor.partials.ai', ['target' => "'text:cover_lead'"])
      </div>
    </div>
  </template>

  <template x-if="selected === 'situation'">
    <div {!! $field('situation') !!}>
      <p class="{{ $label }}">Jak rozumiemy Twoją sytuację?</p>
      <div x-data="richText()" x-modelable="html" x-model="state.content.situation" class="min-w-0">
        @include('guesstimate.partials.rich-text-body', ['label' => 'Jak rozumiemy Twoją sytuację?', 'id' => 'f-ed-situation'])
      </div>
      @include('guesstimate.editor.partials.ai', ['target' => "'situation'"])
      <p class="mt-1 text-xs text-danger" x-text="fieldError('situation')"></p>
    </div>
  </template>

  <template x-if="selected === 'solution'">
    <div class="space-y-3">
      <p class="text-sm text-ink-muted">Od {{ $config['minBlocks'] }} do {{ $config['maxBlocks'] }} bloków. <span class="text-danger" x-text="fieldError('blocks')"></span></p>
      <template x-for="(block, index) in blocks" :key="index">
        <div class="rounded-control border border-line p-3" :data-field="`block:${index}`" :class="field === `block:${index}` ? '{{ $hl }}' : ''">
          <div class="flex items-center gap-1">
            <span class="text-xs font-medium text-ink-muted" x-text="`Blok ${index + 1}`"></span>
            <button type="button" class="ml-auto rounded-control px-1.5 text-ink-muted hover:bg-canvas disabled:opacity-30" x-on:click="moveBlock(index, -1)" :disabled="index === 0" aria-label="Przenieś wyżej">↑</button>
            <button type="button" class="rounded-control px-1.5 text-ink-muted hover:bg-canvas disabled:opacity-30" x-on:click="moveBlock(index, 1)" :disabled="index === blocks.length - 1" aria-label="Przenieś niżej">↓</button>
            <button type="button" class="rounded-control px-1.5 text-xs text-danger hover:bg-danger-soft disabled:opacity-30" x-on:click="removeBlock(index)" :disabled="blocks.length <= {{ $config['minBlocks'] }}">Usuń</button>
          </div>
          <input type="text" class="{{ $input }} mt-1.5 font-medium" x-model="block.title" maxlength="190" placeholder="Nagłówek bloku" :aria-label="`Nagłówek bloku ${index + 1}`">
          <div x-data="richText()" x-modelable="html" x-model="block.html" class="mt-1.5 min-w-0">
            @include('guesstimate.partials.rich-text-body', ['label' => 'Treść bloku'])
          </div>
          @include('guesstimate.editor.partials.ai', ['target' => "'block:' + index"])
        </div>
      </template>
      <button type="button" class="{{ $secondary }}" x-on:click="addBlock()" :disabled="blocks.length >= {{ $config['maxBlocks'] }}">+ Dodaj blok</button>
    </div>
  </template>

  <template x-if="selected === 'pricing'">
    <div class="space-y-4">
      <div class="rounded-control border border-line bg-canvas px-3 py-2.5 text-sm" {!! $field('price') !!}>
        <p class="font-medium">Cena pochodzi z kalkulatora</p>
        <p class="mt-1 text-ink-soft">W dokumencie nie można ręcznie zmienić wyliczonej ceny. Godziny, stawki, wariant i funkcjonalności zmienisz w kreatorze — podgląd pokaże nowe wartości wersji roboczej.</p>
        <a href="{{ $config['urls']['calculation'] }}" class="mt-1.5 inline-block font-medium text-accent hover:underline">Przejdź do kalkulacji →</a>
      </div>
      <div {!! $field('price_includes') !!}>
        <label class="{{ $label }}" for="f-ed-includes">Cena zawiera (jedna pozycja w wierszu)</label>
        <textarea id="f-ed-includes" rows="7" class="{{ $input }}" x-model="state.document.price_includes"></textarea>
        <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" class="size-4 rounded border-line-strong text-accent focus:ring-accent" x-model="state.document.include_features"> Dopisz funkcjonalności z oferty</label>
        <p class="mt-1 text-xs text-danger" x-text="fieldError('document.price_includes')"></p>
      </div>
    </div>
  </template>

  <template x-if="selected === 'extras'">
    <div class="space-y-3">
      <div {!! $field('extras_intro') !!}>
        <label class="{{ $label }}" for="f-ed-extras-intro">Wstęp</label>
        <textarea id="f-ed-extras-intro" rows="3" class="{{ $input }}" x-model="state.document.extras_intro" maxlength="800"></textarea>
        @include('guesstimate.editor.partials.ai', ['target' => "'text:extras_intro'"])
      </div>
      <div class="space-y-2" {!! $field('extras') !!}>
        <p class="text-sm font-medium">Usługi <span class="font-normal text-ink-muted">(ceny nie są doliczane do ceny projektu)</span></p>
        <template x-for="(row, index) in state.document.extras" :key="index">
          <div class="space-y-1.5 rounded-control border border-line p-2.5">
            <input type="text" class="{{ $input }}" x-model="row.name" maxlength="120" placeholder="Nazwa usługi" aria-label="Nazwa usługi">
            <input type="text" class="{{ $input }}" x-model="row.price" maxlength="120" placeholder="Cena (tekst)" aria-label="Cena usługi">
            <textarea rows="2" class="{{ $input }}" x-model="row.description" maxlength="600" placeholder="Opis — każda linia to punkt" aria-label="Opis usługi"></textarea>
            <button type="button" class="text-xs text-danger hover:underline" x-on:click="removeRow(state.document.extras, index)">Usuń usługę</button>
          </div>
        </template>
        <button type="button" class="{{ $secondary }}" x-on:click="addRow(state.document.extras, { name: '', description: '', price: '' })" :disabled="state.document.extras.length >= 6">+ Dodaj usługę</button>
        <p class="text-xs text-danger" x-text="fieldError('document.extras')"></p>
      </div>
    </div>
  </template>

  <template x-if="selected === 'references'">
    <div class="space-y-3">
      <div {!! $field('references_intro') !!}>
        <label class="{{ $label }}" for="f-ed-ref-intro">Wstęp</label>
        <textarea id="f-ed-ref-intro" rows="3" class="{{ $input }}" x-model="state.document.references_intro" maxlength="600"></textarea>
        <p class="mt-1 text-xs text-ink-muted">**pogrubienie**</p>
        @include('guesstimate.editor.partials.ai', ['target' => "'text:references_intro'"])
      </div>
      <div {!! $field('references') !!}>
        <p class="text-sm font-medium">Realizacje <span class="font-normal text-ink-muted" x-text="`(${state.document.reference_ids.length}/{{ $config['maxReferences'] }})`"></span></p>
        <div class="mt-1.5 space-y-1.5">
          @forelse ($config['caseStudies'] as $case)
            <label class="flex cursor-pointer items-center gap-2.5 rounded-control border border-line p-2 hover:bg-canvas has-checked:border-accent has-checked:bg-accent-soft">
              <input type="checkbox" class="size-4 rounded border-line-strong text-accent focus:ring-accent" :checked="state.document.reference_ids.includes({{ $case['id'] }})" x-on:change="toggleReference({{ $case['id'] }})" :disabled="! state.document.reference_ids.includes({{ $case['id'] }}) && state.document.reference_ids.length >= {{ $config['maxReferences'] }}">
              @if ($case['thumb'])<img src="{{ $case['thumb'] }}" alt="" class="h-8 w-12 shrink-0 rounded object-cover">@endif
              <span class="truncate text-sm">{{ $case['name'] }}</span>
            </label>
          @empty
            <p class="text-sm text-ink-muted">Brak realizacji. Administrator doda je w Ustawienia → Realizacje.</p>
          @endforelse
        </div>
      </div>
    </div>
  </template>

  <template x-if="selected === 'next_steps'">
    <div class="space-y-3">
      <div class="space-y-2" {!! $field('next_steps') !!}>
        <template x-for="(row, index) in state.document.next_steps" :key="index">
          <div class="flex gap-1.5">
            <span class="w-5 pt-1.5 text-right text-xs text-ink-muted" x-text="index + 1"></span>
            <div class="min-w-0 flex-1 space-y-1">
              <input type="text" class="{{ $input }}" x-model="row.title" maxlength="140" placeholder="Krok" aria-label="Krok">
              <input type="text" class="{{ $input }}" x-model="row.note" maxlength="140" placeholder="Dopisek, np. (1–3 dni)" aria-label="Dopisek">
            </div>
            <button type="button" class="self-start rounded-control px-1.5 py-1 text-xs text-danger hover:bg-danger-soft" x-on:click="removeRow(state.document.next_steps, index)" aria-label="Usuń krok">✕</button>
          </div>
        </template>
        <button type="button" class="{{ $secondary }}" x-on:click="addRow(state.document.next_steps, { title: '', note: '' })" :disabled="state.document.next_steps.length >= 6">+ Dodaj krok</button>
      </div>
      <div {!! $field('next_steps_note') !!}>
        <label class="{{ $label }}" for="f-ed-steps-note">Wyróżniona informacja</label>
        <input type="text" id="f-ed-steps-note" class="{{ $input }}" x-model="state.document.next_steps_note" maxlength="300">
        <p class="mt-1 text-xs text-ink-muted">**pomarańczowy fragment**</p>
        @include('guesstimate.editor.partials.ai', ['target' => "'text:next_steps_note'"])
      </div>
    </div>
  </template>

  <template x-if="selected === 'contact'">
    <div {!! $field('contact') !!}>
      <label class="{{ $label }}" for="f-ed-contact">Osoba kontaktowa</label>
      <select id="f-ed-contact" class="{{ $input }}" x-model.number="state.document.contact_user_id">
        <option value="">Kontakt domyślny z ustawień</option>
        @foreach ($config['team'] as $member)
          <option value="{{ $member['id'] }}">{{ $member['name'] }}</option>
        @endforeach
      </select>
      <p class="mt-1 text-xs text-ink-muted">Stanowisko i telefon ustawisz w Ustawienia → Użytkownicy.</p>
    </div>
  </template>
</div>

{{-- Układ strony --}}
<div class="{{ $section }}" data-field="layout">
  <h3 class="font-semibold">Układ</h3>
  <div class="mt-2 grid grid-cols-2 gap-2">
    <template x-for="layout in page.layouts" :key="layout.key">
      <button type="button" class="rounded-control border-2 p-2 text-left text-xs" :class="pageState.layout === layout.key ? 'border-accent bg-accent-soft' : 'border-line hover:border-line-strong'" x-on:click="setLayout(layout.key)" :aria-pressed="pageState.layout === layout.key" :data-layout="layout.key">
        <span class="mb-1.5 flex aspect-a4 w-full gap-1 rounded bg-ink p-1.5" aria-hidden="true">
          <template x-if="layout.key === 'image_text'"><span class="w-2/5 rounded-sm bg-accent/60"></span></template>
          <span class="flex flex-1 flex-col gap-1">
            <span class="h-1.5 w-2/3 rounded-sm bg-white/80"></span>
            <template x-if="layout.key === 'tiles'">
              <span class="grid flex-1 grid-cols-3 gap-1"><span class="rounded-sm bg-white/30"></span><span class="rounded-sm bg-white/30"></span><span class="rounded-sm bg-white/30"></span></span>
            </template>
            <template x-if="layout.key !== 'tiles'">
              <span class="flex flex-col gap-1"><span class="h-1 rounded-sm bg-white/40"></span><span class="h-1 rounded-sm bg-white/40"></span><span class="h-1 w-4/5 rounded-sm bg-white/40"></span></span>
            </template>
          </span>
          <template x-if="layout.key === 'text_image'"><span class="w-2/5 rounded-sm bg-accent/60"></span></template>
        </span>
        <span x-text="layout.label"></span>
      </button>
    </template>
  </div>
  <p class="mt-2 text-xs text-ink-muted" x-show="page.layouts.length === 1">Ta strona ma jeden układ (pełna szerokość).</p>
</div>

{{-- Zdjęcie strony (osobny element, nie tło) --}}
<div class="{{ $section }}" x-show="mediaLayout" {!! $field('image') !!}>
  <h3 class="font-semibold">Zdjęcie strony</h3>
  <p class="mt-0.5 text-xs text-ink-muted">Z biblioteki mediów WordPress. Usunięcie ze strony nie usuwa pliku z biblioteki.</p>
  <template x-if="pageState.image_id">
    <div class="mt-2 flex items-center gap-3">
      <span class="block h-16 w-24 shrink-0 overflow-hidden rounded-control bg-ink">
        <template x-if="image(pageState.image_id)?.thumb"><img :src="image(pageState.image_id).thumb" alt="" class="size-full object-cover"></template>
      </span>
      <span class="min-w-0 text-sm">
        <span class="block truncate" x-text="image(pageState.image_id)?.name"></span>
        <span class="text-xs text-danger" x-show="image(pageState.image_id)?.missing">Plik usunięty z biblioteki</span>
      </span>
    </div>
  </template>
  <div class="mt-2 flex flex-wrap gap-2">
    <button type="button" class="{{ $secondary }}" x-on:click="openPicker()" x-text="pageState.image_id ? 'Zmień zdjęcie' : 'Wybierz zdjęcie'" data-image-pick></button>
    <button type="button" class="{{ $ghost }} text-danger" x-show="pageState.image_id" x-on:click="removeImage()" data-image-remove>Usuń zdjęcie</button>
  </div>
  <p class="mt-1 text-xs text-danger" x-text="fieldError(`document.pages.${selected}.image_id`)"></p>
</div>

{{-- Tło strony --}}
<div class="{{ $section }}" {!! $field('background') !!}>
  <div class="flex items-center justify-between gap-2">
    <h3 class="font-semibold">Tło</h3>
    <button type="button" class="text-xs font-medium text-accent hover:underline" x-on:click="restoreBackground()">Przywróć domyślne</button>
  </div>
  <div class="mt-2 grid grid-cols-3 gap-2">
    <button type="button" class="text-left" x-on:click="setBackground(null)" :aria-pressed="! pageState.background_id" data-background="none">
      <span class="block aspect-a4 rounded-control border-2 bg-ink" :class="! pageState.background_id ? 'border-accent' : 'border-transparent hover:border-line-strong'"></span>
      <span class="mt-0.5 block truncate text-xs">Bez grafiki</span>
    </button>
    <template x-for="bg in library" :key="bg.id">
      <button type="button" class="text-left" x-on:click="setBackground(bg.id)" :aria-pressed="pageState.background_id === bg.id" :title="bg.warning ? 'Proporcje inne niż A4' : bg.name" :data-background="bg.id">
        <span class="relative block aspect-a4 overflow-hidden rounded-control border-2 bg-ink" :class="pageState.background_id === bg.id ? 'border-accent' : 'border-transparent hover:border-line-strong'">
          <img :src="bg.thumb" alt="" loading="lazy" class="size-full object-cover">
          <span x-show="bg.warning" class="absolute bottom-0.5 right-0.5 rounded bg-risk-mid-soft px-1 text-xs text-risk-mid">≠A4</span>
        </span>
        <span class="mt-0.5 block truncate text-xs" x-text="bg.name"></span>
      </button>
    </template>
  </div>
  <div class="mt-2 flex gap-3 text-sm" x-show="pageState.background_id">
    @foreach ($config['fits'] as $fit => $fitLabel)
      <label class="flex items-center gap-1.5"><input type="radio" value="{{ $fit }}" :checked="effectiveFit() === '{{ $fit }}'" x-on:change="pageState.fit = '{{ $fit }}'" class="text-accent focus:ring-accent">{{ $fitLabel }}</label>
    @endforeach
  </div>
  @if ($config['urls']['library'])
    <a href="{{ $config['urls']['library'] }}" class="mt-2 inline-block text-xs font-medium text-accent hover:underline">Biblioteka teł →</a>
  @endif
</div>
