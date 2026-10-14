{{-- Pasek narzędzi i pole edytora; rodzic musi mieć x-data="richText()". $label — etykieta, $id — opcjonalne id. --}}
<div class="flex flex-wrap items-center gap-1 rounded-t-control border border-b-0 border-line-strong bg-canvas px-1.5 py-1" role="toolbar" aria-label="Formatowanie: {{ $label }}">
  <button type="button" class="rounded-control px-2 py-1 text-sm font-bold hover:bg-surface" x-on:mousedown.prevent x-on:click="cmd('bold')" title="Pogrubienie (Ctrl+B)" aria-label="Pogrubienie">B</button>
  <button type="button" class="rounded-control px-2 py-1 text-sm italic hover:bg-surface" x-on:mousedown.prevent x-on:click="cmd('italic')" title="Kursywa (Ctrl+I)" aria-label="Kursywa">I</button>
  <span class="mx-1 h-5 w-px bg-line-strong" aria-hidden="true"></span>
  <button type="button" class="rounded-control px-2 py-1 text-sm hover:bg-surface" x-on:mousedown.prevent x-on:click="cmd('insertUnorderedList')" aria-label="Lista punktowana">• Lista</button>
  <button type="button" class="rounded-control px-2 py-1 text-sm hover:bg-surface" x-on:mousedown.prevent x-on:click="cmd('insertOrderedList')" aria-label="Lista numerowana">1. Lista</button>
  <button type="button" class="rounded-control px-2 py-1 text-sm hover:bg-surface" x-on:mousedown.prevent x-on:click="cmd('formatBlock', 'p')" aria-label="Akapit">Akapit</button>
  <span class="mx-1 h-5 w-px bg-line-strong" aria-hidden="true"></span>
  <button type="button" class="rounded-control px-2 py-1 text-sm hover:bg-surface" x-on:mousedown.prevent x-on:click="link()" aria-label="Dodaj link do zaznaczenia">Link</button>
  <button type="button" class="rounded-control px-2 py-1 text-sm hover:bg-surface" x-on:mousedown.prevent x-on:click="cmd('unlink')" aria-label="Usuń link">Usuń link</button>
</div>
<div x-ref="area" @isset($id) id="{{ $id }}" @endisset contenteditable="true" role="textbox" aria-multiline="true" aria-label="{{ $label }}"
  x-on:input="sync()" x-on:blur="sync()" x-on:paste="paste($event)" x-on:keydown="keydown($event)"
  class="gst-prose min-h-40 rounded-b-control border border-line-strong bg-surface px-3 py-2 focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"></div>
