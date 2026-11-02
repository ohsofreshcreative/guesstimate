{{-- Umowy oferty. Trwałe usunięcie oferty z umowami jest odrzucane. --}}
<section class="mt-6 min-w-0 rounded-panel border border-line bg-surface shadow-panel" aria-labelledby="gst-contracts">
  <div class="flex items-center justify-between gap-4 border-b border-line px-6 py-4">
    <h2 id="gst-contracts" class="text-lg font-semibold">Umowy</h2>
    @if ($canEdit && $offer->client_id)
      <x-gst.button :href="route('gst.contracts.create', $offer)" variant="secondary">Przygotuj umowę</x-gst.button>
    @endif
  </div>
  @forelse ($contracts as $contract)
    <a href="{{ route('gst.contracts.show', $contract) }}" class="flex items-center justify-between gap-4 border-b border-line px-6 py-3 last:border-b-0 hover:bg-canvas">
      <span class="min-w-0 truncate font-medium">{{ $contract->title }}</span>
      <span class="shrink-0 text-sm text-ink-muted">v{{ $contract->currentVersion?->version_no }} · {{ $contract->currentVersion?->status->label() }}</span>
    </a>
  @empty
    <p class="px-6 py-4 text-ink-soft">Ta oferta nie ma jeszcze umowy.</p>
  @endforelse
</section>
