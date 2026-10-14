@extends('guesstimate.layouts.app')

@section('title', $offer->number)

@section('content')
  <div class="mx-auto max-w-xl rounded-panel border border-line bg-surface p-8 shadow-panel">
    <span class="flex size-12 items-center justify-center rounded-full bg-canvas text-ink-muted"><x-gst.icon name="lock" class="size-6" /></span>
    @if ($reason === 'cancelled')
      <h1 class="mt-5 text-2xl font-semibold tracking-tight">Oferta jest anulowana</h1>
      <p class="mt-2 text-ink-soft">Anulowanej oferty {{ $offer->number }} nie można edytować. Przywróć ją do statusu „Robocza” na stronie oferty.</p>
      <div class="mt-6"><x-gst.button :href="route('gst.offers.show', $offer)" variant="secondary">Przejdź do oferty</x-gst.button></div>
    @else
      @if ($reason === 'legacy')
        <h1 class="mt-5 text-2xl font-semibold tracking-tight">Wersja {{ $version->version_no }} używa poprzedniego modelu kalkulacji</h1>
        <p class="mt-2 text-ink-soft">Ta wersja jest liczona tak jak w chwili zapisu — jej wycena się nie zmieni. Aby edytować ofertę w nowym kreatorze, utwórz nową wersję. Nowa wersja przejdzie na nowy model (cena może się zmienić), a poprzednia pozostanie bez zmian.</p>
      @else
        <h1 class="mt-5 text-2xl font-semibold tracking-tight">Wersja {{ $version->version_no }} jest zamrożona</h1>
        <p class="mt-2 text-ink-soft">Oferta {{ $offer->number }} została wysłana, więc ta wersja zachowuje wszystkie parametry kalkulacji. Aby wprowadzić zmiany, utwórz nową wersję — poprzednia pozostanie bez zmian.</p>
      @endif
      @if ($offer->status->isEditable())
        <form method="post" action="{{ route('gst.offers.version', $offer) }}" class="mt-6 space-y-4">
          <x-gst.csrf />
          <x-gst.field label="Czego dotyczy zmiana? (opcjonalnie)" for="f-note">
            <x-gst.input name="note" id="f-note" maxlength="190" />
          </x-gst.field>
          <div class="flex flex-wrap gap-2">
            <x-gst.button type="submit">Utwórz nową wersję</x-gst.button>
            <x-gst.button :href="route('gst.offers.show', $offer)" variant="ghost">Wróć do oferty</x-gst.button>
          </div>
        </form>
      @else
        <div class="mt-6"><x-gst.button :href="route('gst.offers.show', $offer)" variant="secondary">Przejdź do oferty</x-gst.button></div>
      @endif
    @endif
  </div>
@endsection
