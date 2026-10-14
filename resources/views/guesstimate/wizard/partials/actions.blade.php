{{-- Przyciski kroku kreatora. Wymaga Alpine `dirty` w formularzu. --}}
<div class="sticky bottom-0 z-10 -mx-4 mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-line bg-canvas/95 px-4 py-4 backdrop-blur sm:mx-0 sm:rounded-panel sm:border sm:bg-surface sm:px-5">
  <div class="flex items-center gap-3">
    @if ($offer && $step > 1)
      <x-gst.button :href="route('gst.offers.wizard', ['offer' => $offer, 'step' => $step - 1])" variant="ghost">Wstecz</x-gst.button>
    @endif
    <span class="text-sm text-risk-mid" x-show="dirty" x-cloak>Masz niezapisane zmiany</span>
    @if ($offer)
      <span class="text-sm text-ink-muted" x-show="! dirty">Ostatni zapis: {{ \App\Guesstimate\Support\Format::date($version->updated_at, true) }}</span>
    @endif
  </div>
  <div class="flex flex-wrap gap-2">
    @if ($offer)
      <x-gst.button type="submit" name="action" value="save" variant="secondary" x-on:click="dirty = false">Zapisz wersję roboczą</x-gst.button>
    @endif
    <x-gst.button type="submit" name="action" value="next" x-on:click="dirty = false">{{ $nextLabel ?? 'Zapisz i dalej' }}</x-gst.button>
  </div>
</div>
