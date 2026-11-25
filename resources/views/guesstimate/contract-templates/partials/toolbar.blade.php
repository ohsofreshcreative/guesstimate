{{-- Pasek formatowania dokumentu umowy (szablon i treść umowy). Wymaga $editable, $tool, $select, $variables. --}}
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
