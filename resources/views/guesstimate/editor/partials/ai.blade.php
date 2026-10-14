{{-- Akcje AI przy polu tekstowym. $target — wyrażenie JS z celem (np. "'situation'" albo "'block:' + index"). --}}
@if ($config['ai']['enabled'])
  <div class="mt-1.5 flex flex-wrap items-center gap-1" data-ai-actions>
    <template x-for="[action, text] in [['regenerate', 'Wygeneruj'], ['improve', 'Popraw'], ['shorten', 'Skróć'], ['expand', 'Rozwiń']]" :key="action">
      <button type="button" class="rounded-control px-2 py-0.5 text-xs font-medium text-accent hover:bg-accent-soft disabled:opacity-40"
        x-on:click="run(action, {{ $target }})" :disabled="busy !== null || (action !== 'regenerate' && ! hasText({{ $target }}))"
        x-text="busy === `${action}:${ {{ $target }} }` ? '…' : text"></button>
    </template>
  </div>
@endif
