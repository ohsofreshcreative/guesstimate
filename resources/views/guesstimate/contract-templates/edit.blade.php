@extends('guesstimate.layouts.editor')

{{-- Edytor szablonu: pasek narzędzi | papier A4 (edycja albo podgląd stron) | ustawienia i wersje. --}}
@php
  $btn = 'inline-flex items-center justify-center gap-2 rounded-control px-3 py-1.5 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50';
  $primary = $btn . ' bg-accent text-white hover:bg-accent-strong';
  $secondary = $btn . ' border border-line-strong bg-surface text-ink hover:bg-canvas';
  $tool = 'rounded-control px-2 py-1 text-sm text-ink-soft hover:bg-canvas hover:text-ink';
  $select = 'rounded-control border-line-strong py-1 pl-2 pr-7 text-sm';
  $input = 'block w-full rounded-control border-line-strong px-3 py-1.5 text-sm focus:border-accent focus:ring-accent';
  $label = 'mb-1 block text-sm font-medium';
  $section = 'border-b border-line px-4 py-4';
@endphp

@section('title', $template->name . ' – szablon umowy')

@section('content')
<div class="flex h-full flex-col" x-data="contractEditor(@js($config))"
  x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = '' }">
  <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-surface px-4 py-2">
    <div class="flex min-w-0 items-center gap-3">
      <a href="{{ route('gst.contract-templates.index') }}" class="{{ $secondary }}">Szablony</a>
      <p class="truncate font-semibold" x-text="name"></p>
      <span class="shrink-0 text-sm text-ink-muted">v{{ $version->version_no }} · {{ $version->status->label() }}</span>
    </div>
    <div class="flex items-center gap-2">
      <span class="text-sm text-ink-muted" x-show="dirty && !status" x-cloak>Niezapisane zmiany</span>
      <span class="text-sm text-ink-muted" x-show="status" x-text="status" x-cloak></span>
      <button type="button" class="{{ $secondary }}" x-on:click="togglePreview()" x-text="mode === 'edit' ? 'Podgląd A4' : 'Wróć do edycji'"></button>
      @if ($editable)
        <button type="button" class="{{ $primary }}" x-on:click="save()" x-bind:disabled="busy">Zapisz</button>
      @endif
    </div>
  </header>

  <p class="bg-danger-soft px-4 py-2 text-sm text-danger" role="alert" x-show="error" x-text="error" x-cloak></p>

  @if ($editable)
    <div class="flex flex-wrap items-center gap-1 border-b border-line bg-surface px-4 py-1.5" x-show="mode === 'edit'" role="toolbar" aria-label="Formatowanie">
      <select class="{{ $select }}" aria-label="Styl akapitu" x-on:change="setStyle($event.target.value); $event.target.value = ''">
        <option value="">Styl…</option>
        <option value="p">Akapit</option>
        <option value="h1">Nagłówek 1</option>
        <option value="h2">Nagłówek 2</option>
        <option value="h3">Nagłówek 3</option>
        <option value="par">Paragraf § (numerowany)</option>
        <option value="small">Drobny druk</option>
      </select>
      <button type="button" class="{{ $tool }}" title="Pogrubienie" x-on:mousedown.prevent x-on:click="cmd('bold')"><strong>B</strong></button>
      <button type="button" class="{{ $tool }}" title="Kursywa" x-on:mousedown.prevent x-on:click="cmd('italic')"><em>I</em></button>
      <button type="button" class="{{ $tool }}" title="Podkreślenie" x-on:mousedown.prevent x-on:click="cmd('underline')"><u>U</u></button>
      <span class="mx-1 h-5 border-l border-line"></span>
      <button type="button" class="{{ $tool }}" x-on:mousedown.prevent x-on:click="cmd('insertUnorderedList')">Lista</button>
      <button type="button" class="{{ $tool }}" x-on:mousedown.prevent x-on:click="cmd('insertOrderedList')">Numeracja</button>
      <span class="mx-1 h-5 border-l border-line"></span>
      <button type="button" class="{{ $tool }}" x-on:mousedown.prevent x-on:click="align()">Do lewej</button>
      <button type="button" class="{{ $tool }}" x-on:mousedown.prevent x-on:click="align('ct-center')">Środek</button>
      <button type="button" class="{{ $tool }}" x-on:mousedown.prevent x-on:click="align('ct-right')">Do prawej</button>
      <button type="button" class="{{ $tool }}" x-on:mousedown.prevent x-on:click="align('ct-justify')">Wyjustuj</button>
      <span class="mx-1 h-5 border-l border-line"></span>
      <button type="button" class="{{ $tool }}" x-on:mousedown.prevent x-on:click="insertTable()">Tabela</button>
      <select class="{{ $select }}" aria-label="Działanie na tabeli" x-on:change="tableAction($event.target.value); $event.target.value = ''">
        <option value="">Tabela…</option>
        <option value="row">Dodaj wiersz</option>
        <option value="column">Dodaj kolumnę</option>
        <option value="deleteRow">Usuń wiersz</option>
        <option value="deleteColumn">Usuń kolumnę</option>
        <option value="deleteTable">Usuń tabelę</option>
      </select>
      <button type="button" class="{{ $tool }}" x-on:mousedown.prevent x-on:click="insertBreak()">Podział strony</button>
      <select class="{{ $select }}" aria-label="Wstaw zmienną" x-on:change="insertVariable($event.target.value); $event.target.value = ''">
        <option value="">Zmienna…</option>
        @foreach ($variables as $group => $items)
          <optgroup label="{{ $group }}">
            @foreach ($items as $key => $name)
              <option value="{{ $key }}">{{ $name }}</option>
            @endforeach
          </optgroup>
        @endforeach
      </select>
      <span class="mx-1 h-5 border-l border-line"></span>
      <button type="button" class="{{ $tool }}" title="Cofnij (Ctrl+Z)" x-on:mousedown.prevent x-on:click="undo()" x-bind:disabled="past.length < 2">Cofnij</button>
      <button type="button" class="{{ $tool }}" title="Ponów (Ctrl+Shift+Z)" x-on:mousedown.prevent x-on:click="redo()" x-bind:disabled="!future.length">Ponów</button>
    </div>
  @endif

  <div class="flex min-h-0 flex-1 flex-col lg:flex-row">
    <main class="min-w-0 flex-1 overflow-auto bg-canvas p-6">
      <div class="mx-auto w-fit" x-show="mode === 'edit'">
        <p class="mb-3 text-center text-sm text-ink-soft">Linie pokazują koniec strony A4 (ok. <span x-text="pageCount"></span> str.). Podział jest przybliżony, jak w podglądzie.</p>
        <div x-ref="paper" class="ct-paper ct-doc relative" :style="background ? `--ct-bg: url('${background.url}')` : ''" x-on:keydown="keydown($event)" x-on:input.debounce.400ms="record()" x-on:paste="paste($event)">
          <div x-ref="header" class="ct-region ct-region-side" data-placeholder="Nagłówek każdej strony" aria-label="Nagłówek dokumentu" @if ($editable) contenteditable="true" @endif>{!! $regions['header'] !!}</div>
          <div x-ref="body" class="ct-region ct-doc-body my-4" aria-label="Treść dokumentu" @if ($editable) contenteditable="true" @endif>{!! $regions['body'] !!}</div>
          <div x-ref="footer" class="ct-region ct-region-side" data-placeholder="Stopka każdej strony" aria-label="Stopka dokumentu" @if ($editable) contenteditable="true" @endif>{!! $regions['footer'] !!}</div>
          {{-- Znaczniki końca strony: poza częściami dokumentu, więc nie trafiają do zapisu. --}}
          <template x-for="mark in breaks" :key="mark.page">
            <div class="pointer-events-none absolute inset-x-0 border-t-2 border-dashed border-accent/50" :style="`top: ${mark.top}px`" aria-hidden="true" data-page-break>
              <span class="absolute -top-3 right-2 rounded-full bg-accent-soft px-2 text-xs font-medium text-accent-strong" x-text="`Strona ${mark.page}`"></span>
            </div>
          </template>
        </div>
        <div x-ref="measure" class="pointer-events-none invisible fixed left-0 top-0" aria-hidden="true"></div>
      </div>
      <div x-show="mode === 'preview'" x-cloak>
        <p class="mb-3 text-center text-sm text-ink-soft">
          <span x-text="pageCount"></span> str. Układ jest przybliżony: podział stron w eksporcie i w Wordzie może się różnić.
        </p>
        <p class="mb-3 text-center text-sm text-danger" x-show="overflow" x-cloak>Niektóre bloki są wyższe niż strona A4 i zostały ucięte w podglądzie.</p>
        <div x-ref="sheets" class="mx-auto flex w-fit flex-col gap-6" :style="background ? `--ct-bg: url('${background.url}')` : ''"></div>
      </div>
    </main>

    <aside class="w-full shrink-0 overflow-y-auto border-l border-line bg-surface lg:w-80">
      @if (! $isCurrent)
        <div class="{{ $section }}">
          <x-gst.alert type="warning">To starsza wersja, tylko do odczytu.</x-gst.alert>
        </div>
      @elseif ($template->isArchived())
        <div class="{{ $section }}">
          <x-gst.alert type="warning">Szablon jest w archiwum. Żeby dalej nad nim pracować, utwórz kopię.</x-gst.alert>
        </div>
      @endif

      @if ($warnings)
        <div class="{{ $section }}">
          <h2 class="mb-2 text-sm font-semibold">Czego import nie przeniósł</h2>
          <ul class="list-disc space-y-1 pl-4 text-sm text-ink-soft">
            @foreach ($warnings as $warning)
              <li>{{ $warning }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="{{ $section }} space-y-3">
        <div>
          <label for="template-name" class="{{ $label }}">Nazwa</label>
          <input id="template-name" class="{{ $input }}" maxlength="190" x-model="name" x-on:input="dirty = true; status = ''" @disabled(! $editable)>
        </div>
        <div>
          <label for="template-description" class="{{ $label }}">Opis</label>
          <input id="template-description" class="{{ $input }}" maxlength="500" x-model="description" x-on:input="dirty = true; status = ''" @disabled(! $editable)>
        </div>
      </div>

      {{-- Tło stron (np. papier firmowy): podgląd, umowa i PDF. Zatwierdzona umowa zachowuje tło z chwili zatwierdzenia. --}}
      <div class="{{ $section }}">
        <h2 class="mb-1 text-sm font-semibold">Tło stron</h2>
        <p class="mb-2 text-xs text-ink-muted">Grafika A4 pionowo z biblioteki grafik, np. papier firmowy. Widać ją w podglądzie, w umowie i w PDF (DOCX bez tła).</p>
        <div class="flex items-center gap-3">
          <span class="block aspect-a4-portrait w-16 shrink-0 overflow-hidden rounded-control border border-line bg-canvas">
            <template x-if="background"><img :src="background.thumb || background.url" alt="" class="size-full object-cover"></template>
          </span>
          @if ($editable)
            <div class="flex flex-col items-start gap-1">
              <button type="button" class="{{ $secondary }}" x-on:click="bgPicker.show()" x-text="background ? 'Zmień tło' : 'Wybierz tło'" data-contract-bg-pick></button>
              <button type="button" class="text-xs text-danger hover:underline" x-show="background" x-on:click="setBackground(null)">Usuń tło</button>
            </div>
          @else
            <span class="text-sm text-ink-muted" x-text="background ? 'Tło ustawione' : 'Bez tła'"></span>
          @endif
        </div>
      </div>

      <div class="{{ $section }} space-y-3">
        @if ($editable)
          @if ($warnings)
            <x-gst.checkbox name="reviewed" label="Szablon porównany z oryginałem" x-model="reviewed" />
          @endif
          <button type="button" class="{{ $primary }} w-full" x-on:click="approve()" x-bind:disabled="busy || mode !== 'edit' @if ($warnings) || !reviewed @endif">Zatwierdź wersję</button>
          <p class="text-sm text-ink-muted">Zatwierdzona wersja jest niezmienna. Dopiero z niej można przygotować umowę.</p>
        @elseif ($isCurrent && ! $template->isArchived())
          <form method="post" action="{{ route('gst.contract-templates.new-version', $template) }}">
            <x-gst.csrf />
            <button type="submit" class="{{ $primary }} w-full">Utwórz nową wersję</button>
          </form>
        @endif
        <form method="post" action="{{ route('gst.contract-templates.duplicate', $template) }}">
          <x-gst.csrf />
          <input type="hidden" name="version_no" value="{{ $version->version_no }}">
          <button type="submit" class="{{ $secondary }} w-full" x-bind:disabled="dirty">Kopiuj jako nowy szablon</button>
        </form>
      </div>

      <div class="{{ $section }}">
        <h2 class="mb-2 text-sm font-semibold">Wersje</h2>
        <ul class="space-y-1 text-sm">
          @foreach ($versions as $item)
            <li>
              <a href="{{ route('gst.contract-templates.edit', $template) }}?wersja={{ $item->version_no }}" @class(['hover:underline', 'font-semibold' => $item->id === $version->id])>
                v{{ $item->version_no }} · {{ $item->status->label() }}
              </a>
              @if ($item->approved_at)<span class="text-ink-muted">{{ wp_date('j.m.Y', $item->approved_at->getTimestamp()) }}</span>@endif
            </li>
          @endforeach
        </ul>
      </div>

      <p class="px-4 py-4 text-sm text-ink-muted">Edytor obsługuje prosty dokument. Nie odwzorowuje wszystkich funkcji Worda, a wklejony tekst traci formatowanie.</p>
    </aside>
  </div>

  @if ($editable)
    <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-4 py-10" x-show="bgPicker.open" x-cloak role="dialog" aria-modal="true" aria-labelledby="ct-bg-title" x-on:keydown.escape.window="bgPicker.open = false">
      <div class="fixed inset-0 bg-ink/50" x-on:click="bgPicker.open = false"></div>
      <div class="relative w-full max-w-4xl rounded-panel bg-surface shadow-overlay">
        <div class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-3">
          <h2 id="ct-bg-title" class="text-lg font-semibold">Tło stron — biblioteka</h2>
          <form class="ml-auto flex gap-2" x-on:submit.prevent="bgPicker.load(true)">
            <label for="ct-bg-q" class="sr-only">Szukaj w bibliotece</label>
            <input type="search" id="ct-bg-q" x-model="bgPicker.q" placeholder="Szukaj…" class="{{ $input }} w-52">
            <button type="submit" class="{{ $secondary }}">Szukaj</button>
          </form>
          <button type="button" class="{{ $secondary }}" x-on:click="bgPicker.open = false">Zamknij</button>
        </div>
        <div class="max-h-140 overflow-y-auto p-5">
          <p class="mb-3 text-sm text-danger" x-show="bgPicker.error" x-text="bgPicker.error"></p>
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-5">
            <template x-for="item in bgPicker.items" :key="item.id">
              <button type="button" class="text-left" x-on:click="bgPicker.pick(item); setBackground(item)">
                <span class="block aspect-a4-portrait overflow-hidden rounded-control border-2 bg-canvas" :class="background?.id === item.id ? 'border-accent' : 'border-transparent hover:border-line-strong'">
                  <img :src="item.thumb" alt="" loading="lazy" class="size-full object-cover">
                </span>
                <span class="mt-1 block truncate text-xs" x-text="item.name"></span>
              </button>
            </template>
          </div>
          <p class="py-6 text-center text-sm text-ink-muted" x-show="! bgPicker.loading && ! bgPicker.items.length">Brak obrazów w bibliotece.</p>
          <p class="py-3 text-center text-sm text-ink-muted" x-show="bgPicker.loading">Wczytywanie…</p>
          <div class="mt-4 text-center" x-show="bgPicker.more && ! bgPicker.loading"><button type="button" class="{{ $secondary }}" x-on:click="bgPicker.load()">Pokaż więcej</button></div>
        </div>
      </div>
    </div>
  @endif
</div>
@endsection
