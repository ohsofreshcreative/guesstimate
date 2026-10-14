{{-- Potwierdzenie usunięcia klienta. Otwieranie: $dispatch('gst-client-delete', { url, name, from? }). --}}
<div x-data="{ open: false, url: '', name: '', from: '' }"
  x-on:gst-client-delete.window="url = $event.detail.url; name = $event.detail.name; from = $event.detail.from || ''; open = true"
  x-on:keydown.escape.window="open = false"
  x-show="open" x-cloak
  class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-4 py-10"
  role="dialog" aria-modal="true" aria-labelledby="gst-client-delete-title" data-client-delete-modal>
  <div class="fixed inset-0 bg-ink/50" x-on:click="open = false"></div>
  <form method="post" :action="url" class="relative w-full max-w-lg rounded-panel bg-surface shadow-overlay" x-effect="if (open) $nextTick(() => $el.querySelector('[data-cancel]')?.focus())">
    <x-gst.csrf />
    <input type="hidden" name="from" :value="from">
    <div class="border-b border-line px-6 py-4">
      <h2 id="gst-client-delete-title" class="text-lg font-semibold">Usunąć klienta?</h2>
    </div>
    <div class="space-y-3 px-6 py-5">
      <p class="font-medium" x-text="name"></p>
      <p>Klient zostanie usunięty z lokalnej bazy Guesstimate. Operacja nie usunie firmy ani żadnych danych z ClickUp.</p>
      <p class="text-sm text-ink-muted">Usuniętego klienta można przywrócić w zakładce „Usunięci”.</p>
    </div>
    <div class="flex justify-end gap-2 border-t border-line px-6 py-4">
      <x-gst.button variant="ghost" data-cancel x-on:click="open = false">Anuluj</x-gst.button>
      <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-control bg-danger px-4 py-2 font-medium text-white hover:opacity-90" data-confirm-delete>Usuń klienta</button>
    </div>
  </form>
</div>
