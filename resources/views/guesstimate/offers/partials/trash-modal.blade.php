{{-- Wspólny modal „Przenieś do kosza”. Otwieranie: $dispatch('gst-trash', { url, number }). --}}
<div x-data="{ open: false, url: '', number: '' }"
  x-on:gst-trash.window="url = $event.detail.url; number = $event.detail.number; open = true"
  x-on:keydown.escape.window="open = false"
  x-show="open" x-cloak
  class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-4 py-10"
  role="dialog" aria-modal="true" aria-labelledby="gst-trash-title">
  <div class="fixed inset-0 bg-ink/50" x-on:click="open = false"></div>
  <form method="post" :action="url" class="relative w-full max-w-lg rounded-panel bg-surface shadow-overlay" x-effect="if (open) $nextTick(() => $el.querySelector('[data-cancel]')?.focus())">
    <x-gst.csrf />
    <div class="border-b border-line px-6 py-4">
      <h2 id="gst-trash-title" class="text-lg font-semibold">Przenieś do kosza</h2>
    </div>
    <div class="space-y-3 px-6 py-5">
      <p>Czy na pewno chcesz przenieść tę ofertę do kosza? <span class="font-medium" x-text="number"></span></p>
      <p class="text-sm text-ink-muted">Oferta zniknie z archiwum, a jej publiczny link przestanie działać. Wersje, publikacje i historia zostają — ofertę można przywrócić.</p>
    </div>
    <div class="flex justify-end gap-2 border-t border-line px-6 py-4">
      <x-gst.button variant="ghost" data-cancel x-on:click="open = false">Anuluj</x-gst.button>
      <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-control bg-danger px-4 py-2 font-medium text-white hover:opacity-90">Przenieś do kosza</button>
    </div>
  </form>
</div>
