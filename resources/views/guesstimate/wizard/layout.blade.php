@extends('guesstimate.layouts.app')

@section('title', ($offer ? $offer->number . ' – ' : 'Nowa oferta – ') . $steps[$step])

@php
  $sidebarSummary ??= null;
  $sidebarError ??= null;
  $wide ??= false;
@endphp

@section('content')
  <div class="mb-2">
    @if ($offer)
      <a href="{{ route('gst.offers.show', $offer) }}" class="text-ink-soft hover:text-ink">← {{ $offer->number }}</a>
    @else
      <a href="{{ route('gst.offers.index') }}" class="text-ink-soft hover:text-ink">← Oferty</a>
    @endif
  </div>

  <header class="mb-6">
    <h1 class="text-3xl font-semibold tracking-tight">
      @if ($offer){{ $version->project_name }}@else Nowa oferta @endif
    </h1>
    @if ($offer)
      <p class="mt-1 text-ink-soft">{{ $offer->number }} · wersja {{ $version->version_no }} · {{ $offer->status->label() }} · online: {{ ($offer->publication_status ?? \App\Guesstimate\Offers\PublicationStatus::Draft)->label() }}</p>
    @endif
  </header>

  <nav class="mb-8 overflow-x-auto" aria-label="Kroki kreatora">
    <ol class="flex min-w-max gap-1 rounded-panel border border-line bg-surface p-1.5">
      @foreach ($steps as $number => $label)
        @php $reachable = $offer && $number <= max($maxStep, $step); @endphp
        <li class="flex-1 whitespace-nowrap">
          @if ($reachable && $number !== $step)
            <a href="{{ route('gst.offers.wizard', ['offer' => $offer, 'step' => $number]) }}" class="flex items-center gap-2 rounded-control px-3 py-2 text-ink-soft hover:bg-canvas hover:text-ink">
              <span class="flex size-6 shrink-0 items-center justify-center rounded-full border border-line-strong text-sm">{{ $number }}</span>
              {{ $label }}
            </a>
          @else
            <span @class([
              'flex items-center gap-2 rounded-control px-3 py-2',
              'bg-ink font-medium text-white' => $number === $step,
              'text-ink-muted' => $number !== $step,
            ]) @if ($number === $step) aria-current="step" @endif>
              <span @class([
                'flex size-6 shrink-0 items-center justify-center rounded-full text-sm',
                'bg-white text-ink' => $number === $step,
                'border border-line' => $number !== $step,
              ])>{{ $number }}</span>
              {{ $label }}
            </span>
          @endif
        </li>
      @endforeach
    </ol>
  </nav>

  @if (! empty($errors))
    <x-gst.alert type="error" class="mb-6">
      <p class="font-medium">Nie zapisano zmian — popraw zaznaczone pola.</p>
      <ul class="mt-1 list-disc pl-5">
        @foreach (array_slice(array_unique(array_values($errors)), 0, 6) as $message)
          <li>{{ $message }}</li>
        @endforeach
      </ul>
    </x-gst.alert>
  @endif

  @if ($offer && ! $wide)
    <div class="grid gap-6 xl:grid-cols-4">
      <div class="min-w-0 xl:col-span-3">
        @yield('step')
      </div>
      <aside class="min-w-0 xl:order-last" aria-label="Podsumowanie ceny">
        @include('guesstimate.wizard.partials.price-summary', [
          'summary' => $sidebarSummary,
          'error' => $sidebarError,
          'liveUrl' => $version->isBudget() && $step <= 4 ? route('gst.offers.wizard.live', ['offer' => $offer, 'step' => $step]) : null,
        ])
      </aside>
    </div>
  @else
    @yield('step')
  @endif
@endsection
