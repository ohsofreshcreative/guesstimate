@extends('guesstimate.public.layout', ['mode' => $mode ?? 'public'])

{{-- Oferta jako dokument: strony A4 poziomo jedna pod drugą, pasek nawigacji, tryb pełnoekranowy.
     Te same strony renderuje edytor (tryb „editor” i „thumbs”) — jedna implementacja wyglądu. --}}
@php $mode ??= 'public'; @endphp

@section('title', $offer['title'])

@section('body')
  <div @class(['min-h-screen', 'bg-viewer pb-24' => $mode === 'public']) data-doc-viewer @if ($beacon) data-beacon="{{ $beacon }}" @endif>
    @if ($preview && $mode === 'public')
      <p class="bg-orange px-4 py-2 text-center text-sm font-medium text-night">Podgląd z panelu — tak zobaczy ofertę klient. Ta wizyta nie jest liczona jako wyświetlenie.</p>
    @endif

    <main class="osf-doc mx-auto flex flex-col items-center gap-6 px-3 pt-6 sm:gap-8 sm:px-6 sm:pt-10" aria-label="Oferta {{ $offer['number'] }}">
      @include('guesstimate.public.partials.pages', ['offer' => $offer, 'mode' => $mode])
    </main>

    @if ($mode === 'public')
      <nav class="fixed inset-x-0 bottom-4 z-30 flex justify-center px-3" aria-label="Nawigacja dokumentu">
        <div class="flex items-center gap-1 rounded-full bg-night/90 px-2 py-1.5 text-sm text-paper shadow-page backdrop-blur">
          <button type="button" class="rounded-full px-3 py-1.5 hover:bg-night-line disabled:opacity-40" data-doc-prev aria-label="Poprzednia strona">←</button>
          <span class="min-w-28 px-2 text-center tabular-nums" aria-live="polite" data-doc-counter>Strona 1 z {{ count($offer['document']['pages']) }}</span>
          <button type="button" class="rounded-full px-3 py-1.5 hover:bg-night-line disabled:opacity-40" data-doc-next aria-label="Następna strona">→</button>
          <button type="button" class="ml-1 hidden rounded-full px-3 py-1.5 hover:bg-night-line sm:inline-flex" data-doc-fullscreen aria-label="Pełny ekran" title="Pełny ekran">⤢</button>
        </div>
      </nav>
    @endif
  </div>

  @vite(['resources/js/offer.js'])
@endsection
