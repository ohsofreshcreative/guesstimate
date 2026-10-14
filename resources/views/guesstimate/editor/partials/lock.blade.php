@if ($lock)
  <div class="mx-4 mt-3 rounded-control border border-risk-mid/30 bg-risk-mid-soft px-3 py-2.5 text-sm" role="status" data-editor-lock="{{ $lock }}">
    @switch($lock)
      @case('locked')
        <p class="font-medium">Wersja {{ $version->version_no }} jest zamrożona (oferta wysłana).</p>
        <p class="mt-1 text-ink-soft">Dokument zmienisz w wersji roboczej. Klient nadal widzi opublikowaną wersję, dopóki nie opublikujesz nowej.</p>
        @if ($canEdit)
          <form method="post" action="{{ route('gst.offers.version', $offer) }}" class="mt-2">
            <x-gst.csrf /><input type="hidden" name="back" value="document"><input type="hidden" name="note" value="Zmiany w dokumencie oferty">
            <button type="submit" class="rounded-control bg-accent px-3 py-1.5 font-medium text-white hover:bg-accent-strong">Utwórz wersję roboczą</button>
          </form>
        @endif
        @break
      @case('cancelled')
        <p>Anulowanej oferty nie edytujemy — najpierw przywróć ją do roboczej na stronie oferty.</p>
        @break
      @default
        <p>Możesz przeglądać dokument, ale nie możesz go edytować.</p>
    @endswitch
  </div>
@endif
