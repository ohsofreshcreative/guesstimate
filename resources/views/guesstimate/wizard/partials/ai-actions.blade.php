<div class="flex flex-wrap items-center gap-1">
  <template x-for="[action, text] in [['regenerate', 'Napisz od nowa'], ['improve', 'Popraw'], ['shorten', 'Skróć'], ['expand', 'Rozwiń']]" :key="action">
    <button type="button" class="rounded-control px-2 py-1 text-sm text-accent hover:bg-accent-soft disabled:opacity-40" x-on:click="run(action, '{{ $target }}')" :disabled="busy !== null || (action !== 'regenerate' && ! hasContent('{{ $target }}'))" x-text="busy === `${action}:{{ $target }}` ? '…' : text"></button>
  </template>
</div>
